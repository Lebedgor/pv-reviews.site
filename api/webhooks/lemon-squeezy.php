<?php
/**
 * PVR Media Reviews — Lemon Squeezy Webhook Handler
 *
 * POST /api/webhooks/lemon-squeezy.php
 *
 * Receives and processes Lemon Squeezy webhook events.
 * Verifies HMAC signature, processes order events, creates customers/orders/licenses.
 * Idempotent: duplicate webhooks are safely ignored.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../license/provision.php';

// Only process entry-point code when accessed directly (not included from router.php or test.php)
if (PHP_SAPI !== 'cli' && !defined('PVR_WEBHOOK_ROUTED')) {

// Only accept POST
require_method('POST');

// --- 1. Read raw body and signature ---
$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    exit('Empty body.');
}

$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if ($signature === '') {
    http_response_code(401);
    exit('Missing signature.');
}

$secret = defined('LEMONSQUEEZY_WEBHOOK_SECRET') ? LEMONSQUEEZY_WEBHOOK_SECRET : '';
if ($secret === '') {
    error_log('PVR webhook: LEMONSQUEEZY_WEBHOOK_SECRET not configured.');
    http_response_code(500);
    exit('Webhook not configured.');
}

// --- 2. Verify HMAC signature ---
$expectedSig = hash_hmac('sha256', $rawBody, $secret);

// Constant-time comparison
if (strlen($expectedSig) !== strlen($signature)) {
    http_response_code(401);
    exit('Invalid signature.');
}

$valid = hash_equals($expectedSig, $signature);
if (!$valid) {
    http_response_code(401);
    exit('Invalid signature.');
}

// --- 3. Parse JSON after signature verification ---
$payload = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    error_response('Invalid JSON payload.');
}

// --- 4. Validate structure ---
if (empty($payload['meta']['event_name'])) {
    error_response('Missing event name.');
}

if (empty($payload['data']) || empty($payload['data']['type'])) {
    error_response('Invalid payload structure.');
}

$eventName = $payload['meta']['event_name'];
$customData = $payload['meta']['custom_data'] ?? [];
$eventType = $payload['data']['type'];
$attributes = $payload['data']['attributes'] ?? [];
$dataId = $payload['data']['id'] ?? null;


if (empty($attributes)) {
    error_response('Missing event attributes.');
}

// --- 5. Build event identifier for idempotency ---
// Use event_name + data id as unique identifier
$providerEventId = $eventName . '_' . ($dataId ?? 'unknown');
$payloadHash = hash('sha256', $rawBody);

try {
    $pdo = db();

    // --- 6. Check for duplicate event ---
    $stmt = $pdo->prepare("SELECT id FROM webhook_events WHERE provider_event_id = ?");
    $stmt->execute([$providerEventId]);
    if ($stmt->fetch()) {
        // Already processed — return 200 (idempotent)
        json_response(['status' => 'already_processed']);
    }

    // --- 7. Record the webhook event (unprocessed) ---
    $stmt = $pdo->prepare("
        INSERT INTO webhook_events (provider, provider_event_id, event_type, payload_hash)
        VALUES ('lemonsqueezy', ?, ?, ?)
    ");
    $stmt->execute([$providerEventId, $eventName, $payloadHash]);
    $webhookEventId = $pdo->lastInsertId();

    // --- 8. Process based on event type ---
    // Lemon Squeezy puts order ID in data.id, not data.attributes.id
    if ($dataId && empty($attributes['id'])) {
        $attributes['id'] = $dataId;
    }

    switch ($eventName) {
        case 'order_created':
            // Renewal payment: custom_data.renew_license_id marks a subscription
            // extension (from /renew or a recurring plan). Extend instead of
            // creating a new license. When the license cannot be resolved
            // (e.g., initial subscription purchase), fall through to the
            // normal creation flow.
            $renewLicenseId = isset($customData['renew_license_id']) ? (int)$customData['renew_license_id'] : 0;
            $subscriptionId = isset($attributes['subscription_id']) ? (int)$attributes['subscription_id'] : 0;
            $renewed = false;
            if ($renewLicenseId > 0 || $subscriptionId > 0) {
                $renewed = processRenewalPayment($pdo, $attrs, $renewLicenseId, $subscriptionId);
            }
            if (!$renewed) {
                processOrderCreated($pdo, $attributes, $customData);
            }
            break;

        case 'order_updated':
            processOrderUpdated($pdo, $attributes);
            break;

        case 'order_refunded':
            processOrderRefunded($pdo, $attributes);
            break;

        case 'license_key_created':
        case 'license_key_updated':
            // Not processed — we manage our own licenses
            break;

        case 'subscription_created':
            processSubscriptionCreated($pdo, $attributes, $customData);
            break;

        case 'subscription_updated':
            // Renewal date changes are handled by order_created payments.
            break;

        case 'subscription_cancelled':
        case 'subscription_expired':
            // License is lifetime — nothing to revoke. The update deadline
            // simply stops being extended (notice in the plugin takes over).
            $subId = isset($attributes['id']) ? (int)$attributes['id'] : 0;
            if ($subId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM licenses WHERE ls_subscription_id = ? LIMIT 1");
                $stmt->execute([$subId]);
                $licId = (int)($stmt->fetch()['id'] ?? 0);
                if ($licId > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO webhook_events (provider, provider_event_id, event_type, payload_hash, processed_at)
                        VALUES ('lemonsqueezy', ?, 'subscription_ended_note', ?, CURRENT_TIMESTAMP)
                    ");
                    $subEventId = $eventName . '_' . $subId . '_note';
                    $stmt->execute([$subEventId, hash('sha256', (string)$subEventId)]);
                }
            }
            break;

        case 'customer_updated':
            processCustomerUpdated($pdo, $attributes);
            break;

        default:
            error_log("PVR webhook: Unhandled event type: {$eventName}");
            break;
    }

    // --- 9. Mark event as processed ---
    $stmt = $pdo->prepare("
        UPDATE webhook_events SET processed_at = CURRENT_TIMESTAMP WHERE id = ?
    ");
    $stmt->execute([$webhookEventId]);

    json_response(['status' => 'ok']);

} catch (PDOException $e) {
    error_log('PVR webhook DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('Processing error.');
}

} // end PHP_SAPI !== 'cli' guard

// =====================================================
// Event Processing Functions (available for test.php inclusion)
// =====================================================

/**
 * Validate that the webhook is for the expected PVR Pro variant.
 * Returns true if variant_id matches, false otherwise.
 */
function validateVariantId(array $attrs): bool
{
    if (!defined('LEMONSQUEEZY_VARIANT_ID') || LEMONSQUEEZY_VARIANT_ID === '') {
        return true;
    }

    $firstItem = $attrs['first_order_item'] ?? [];
    $webhookVariantId = $firstItem['variant_id'] ?? null;

    if ($webhookVariantId === null) {
        return true;
    }

    return (string) $webhookVariantId === (string) LEMONSQUEEZY_VARIANT_ID;
}

/**
 * Look up checkout session by token and validate it.
 * Returns [sessionId, normalizedDomain] or [null, null] if invalid.
 */
function resolveCheckoutSession(PDO $pdo, ?string $sessionToken): array
{
    if (!$sessionToken) {
        return [null, null];
    }

    $stmt = $pdo->prepare("
        SELECT id, normalized_domain, status, expires_at
        FROM checkout_sessions
        WHERE token = ?
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    if (!$session) {
        return [null, null];
    }

    if ($session['status'] !== 'pending') {
        return [null, null];
    }

    if ($session['expires_at'] !== null) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt = new DateTime($session['expires_at'], new DateTimeZone('UTC'));
        if ($now > $expiresAt) {
            return [null, null];
        }
    }

    return [(int)$session['id'], $session['normalized_domain']];
}

/**
 * Create a license record for a completed order.
 * New model: a single perpetual license with updates included. When a
 * license already exists for the same domain/key, the licensing server
 * extends it (status 'renewed') and the existing key keeps working.
 */
function createLicenseRecord(PDO $pdo, int $orderId, int $customerId, string $domain, string $lsOrderId = '', string $email = '', ?string $providerLicenseKey = null): void
{
    $stmt = $pdo->prepare("SELECT id FROM licenses WHERE order_id = ?");
    $stmt->execute([$orderId]);
    if ($stmt->fetch()) {
        return;
    }

    $adminLicenseId = null;
    $adminLicenseKey = $providerLicenseKey;

    if ($lsOrderId !== '' && $providerLicenseKey === null) {
        $licenseName = $domain !== '' ? "{$domain} ({$lsOrderId})" : (string)$lsOrderId;
        $adminResult = provision_create_license($domain, $lsOrderId, $email, $licenseName);
        if ($adminResult !== null && isset($adminResult['license_id'])) {
            $adminLicenseId = (int)$adminResult['license_id'];
            $adminLicenseKey = $adminResult['license_key'] ?? null;

            if (($adminResult['status'] ?? 'created') === 'renewed') {
                // Покупка для уже существующей лицензии — сервер продлил её
                // и вернул прежний ключ. Не создаём дубль в локальной базе:
                // обновляем существующую запись или заводим её с новым заказом.
                renewExistingLocalLicense($pdo, $adminLicenseId, $adminLicenseKey, $orderId, $customerId, $domain, $email);
                return;
            }
        } else {
            error_log("PVR webhook: admin provisioning failed for order {$lsOrderId}");
        }
    }

    // Лицензия вечная, обновления включены — локальные даты не заполняем.
    $stmt = $pdo->prepare("
        INSERT INTO licenses (license_key, order_id, customer_id, domain, status, admin_license_id, admin_license_key)
        VALUES (?, ?, ?, ?, 'active', ?, ?)
    ");
    $stmt->execute([
        $adminLicenseKey,
        $orderId,
        $customerId,
        $domain,
        $adminLicenseId,
        $adminLicenseKey,
    ]);

    if ($adminLicenseKey && $email !== '') {
        require_once __DIR__ . '/../email.php';
        sendLicenseEmail($email, $domain, $adminLicenseKey, '');
    }
}

/**
 * Purchase matched an existing license (server-side dedup). Reuse the
 * local record when it exists (the customer keeps the same key), otherwise
 * register it locally with the new order. Sends the license email so the
 * customer has the key at hand.
 */
function renewExistingLocalLicense(PDO $pdo, int $adminLicenseId, string $adminLicenseKey, int $orderId, int $customerId, string $domain, string $email): void
{
    $stmt = $pdo->prepare("SELECT id FROM licenses WHERE admin_license_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$adminLicenseId]);
    $existingId = (int)($stmt->fetchColumn() ?: 0);

    if ($existingId > 0) {
        $stmt = $pdo->prepare("UPDATE licenses SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$existingId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO licenses (license_key, order_id, customer_id, domain, status, admin_license_id, admin_license_key)
            VALUES (?, ?, ?, ?, 'active', ?, ?)
        ");
        $stmt->execute([$adminLicenseKey, $orderId, $customerId, $domain, $adminLicenseId, $adminLicenseKey]);
    }

    if ($adminLicenseKey !== '' && $email !== '') {
        require_once __DIR__ . '/../email.php';
        sendLicenseEmail($email, $domain, $adminLicenseKey, '');
    }
}

/**
 * Process an order_created event.
 */
function processOrderCreated(PDO $pdo, array $attrs, array $customData, ?string $providerLicenseKey = null): void
{

    $lsOrderId = $attrs['id'] ?? null;
    $email = $attrs['user_email'] ?? '';
    $name = $attrs['user_name'] ?? '';
    $status = $attrs['status'] ?? '';
    $total = (int)($attrs['total'] ?? 0);
    $currency = $attrs['currency'] ?? 'USD';
    $firstItem = $attrs['first_order_item'] ?? [];
    $productName = $firstItem['product_name'] ?? '';
    $variantName = $firstItem['variant_name'] ?? '';
    $lsCustomerId = $attrs['customer_id'] !== null ? (string)($attrs['customer_id'] ?? '') : null;


    if (!$lsOrderId || $email === '') {
        error_log('PVR webhook: order_created missing required fields.');
        return;
    }

    // Validate variant_id
    if (!validateVariantId($attrs)) {
        error_log("PVR webhook: order_created variant_id mismatch for order {$lsOrderId}. webhook=" . var_export($attrs['first_order_item']['variant_id'] ?? null, true) . " config=" . LEMONSQUEEZY_VARIANT_ID);
        return;
    }


    $sessionToken = $customData['session_token'] ?? null;

    if (!$sessionToken && !empty($attrs['redirect_url'])) {
        $parsedUrl = parse_url($attrs['redirect_url']);
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            $sessionToken = $queryParams['token'] ?? null;
        }
    }

    $pdo->beginTransaction();

    try {
        // Resolve checkout session (with expiration check).
        // No fallback: if Lemon Squeezy drops custom_data, the license is
        // created WITHOUT a domain rather than bound to a stranger's session.
        // The domain can be bound later from the thank-you page or manually.
        [$sessionId, $normalizedDomain] = resolveCheckoutSession($pdo, $sessionToken);

        error_log("PVR webhook: order_created {$lsOrderId} — session_id=" . var_export($sessionId, true) . " domain=" . var_export($normalizedDomain, true));

        // Find or create customer
        try {
            $customerId = findOrCreateCustomer($pdo, $email, $name, $lsCustomerId);
        } catch (Throwable $t) {
            throw $t;
        }

        // Check for duplicate order
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE ls_order_id = ?");
        $stmt->execute([$lsOrderId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return;
        }

        // Map LS status to our status
        $orderStatus = mapOrderStatus($status);

        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (ls_order_id, customer_id, session_id, product_name, variant_name, amount, currency, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$lsOrderId, $customerId, $sessionId, $productName, $variantName, $total, $currency, $orderStatus]);
        $orderId = (int)$pdo->lastInsertId();
        error_log("PVR webhook: order inserted id={$orderId}");

        // Update checkout session
        if ($sessionId) {
            $stmt = $pdo->prepare("
                UPDATE checkout_sessions SET status = 'completed', email = ? WHERE id = ?
            ");
            $stmt->execute([$email, $sessionId]);
        }

        // Create license only if order is paid and domain is available
        if ($orderStatus === 'completed' && $normalizedDomain) {
            createLicenseRecord($pdo, $orderId, $customerId, $normalizedDomain, (string)$lsOrderId, $email, $providerLicenseKey);
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('PVR webhook: order_created processing error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Process an order_updated event.
 *
 * Handles the scenario where order_created arrives with status=pending,
 * then order_updated arrives with status=paid.
 */
function processOrderUpdated(PDO $pdo, array $attrs): void
{
    $lsOrderId = $attrs['id'] ?? null;
    $status = $attrs['status'] ?? '';

    if (!$lsOrderId) {
        return;
    }

    $orderStatus = mapOrderStatus($status);

    $pdo->beginTransaction();

    try {
        // Find existing order
        $stmt = $pdo->prepare("SELECT id, customer_id, session_id FROM orders WHERE ls_order_id = ?");
        $stmt->execute([$lsOrderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            return;
        }

        // Update order status
        $stmt = $pdo->prepare("
            UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE ls_order_id = ?
        ");
        $stmt->execute([$orderStatus, $lsOrderId]);

        // If order became completed and no license exists, create one
            if ($orderStatus === 'completed' && $order['session_id']) {
                $stmt = $pdo->prepare("SELECT normalized_domain FROM checkout_sessions WHERE id = ?");
                $stmt->execute([(int)$order['session_id']]);
                $session = $stmt->fetch();

            if ($session && $session['normalized_domain']) {
                $customerEmail = '';
                $custStmt = $pdo->prepare("SELECT email FROM customers WHERE id = ?");
                $custStmt->execute([(int)$order['customer_id']]);
                $custRow = $custStmt->fetch();
                if ($custRow) {
                    $customerEmail = $custRow['email'];
                }
                createLicenseRecord($pdo, (int)$order['id'], (int)$order['customer_id'], $session['normalized_domain'], (string)$lsOrderId, $customerEmail);
            }
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('PVR webhook: order_updated processing error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Process an order_refunded event.
 */
function processOrderRefunded(PDO $pdo, array $attrs): void
{
    $lsOrderId = $attrs['id'] ?? null;
    if (!$lsOrderId) {
        return;
    }

    $pdo->beginTransaction();

    try {
        // Find order
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE ls_order_id = ?");
        $stmt->execute([$lsOrderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            return;
        }

        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', updated_at = CURRENT_TIMESTAMP WHERE ls_order_id = ?");
        $stmt->execute([$lsOrderId]);

        // Revoke associated license and suspend on admin.claz.site
        $stmt = $pdo->prepare("
            UPDATE licenses SET status = 'revoked', updated_at = CURRENT_TIMESTAMP
            WHERE order_id = ?
        ");
        $stmt->execute([$order['id']]);

        $stmt = $pdo->prepare("SELECT admin_license_id FROM licenses WHERE order_id = ? AND admin_license_id IS NOT NULL LIMIT 1");
        $stmt->execute([$order['id']]);
        $licRow = $stmt->fetch();
        if ($licRow && (int)$licRow['admin_license_id'] > 0) {
            provision_suspend_license((int)$licRow['admin_license_id']);
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('PVR webhook: order_refunded processing error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Process a customer_updated event.
 */
function processCustomerUpdated(PDO $pdo, array $attrs): void
{
    $lsCustomerId = $attrs['id'] ?? null;
    $email = $attrs['email'] ?? '';
    $name = $attrs['name'] ?? '';

    if (!$lsCustomerId) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE customers SET name = ?, email = ?, updated_at = CURRENT_TIMESTAMP
        WHERE ls_customer_id = ?
    ");
    $stmt->execute([$name, $email, $lsCustomerId]);
}

/**
 * Find or create a customer by email.
 */
function findOrCreateCustomer(PDO $pdo, string $email, string $name, ?string $lsCustomerId): int
{
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        $stmt = $pdo->prepare("
            UPDATE customers SET
                name = COALESCE(NULLIF(?, ''), name),
                ls_customer_id = COALESCE(NULLIF(?, ''), ls_customer_id),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$name, $lsCustomerId, $row['id']]);
        return (int)$row['id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, ls_customer_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, $name, $lsCustomerId]);
    return (int)$pdo->lastInsertId();
}

/**
 * Map Lemon Squeezy order status to our status.
 */
function mapOrderStatus(string $lsStatus): string
{
    return match ($lsStatus) {
        'paid' => 'completed',
        'pending' => 'pending',
        'failed' => 'failed',
        'refunded' => 'refunded',
        default => 'pending',
    };
}

/**
 * Process a renewal payment (manual renewal from /renew or a recurring
 * subscription charge).
 *
 * @param PDO   $pdo
 * @param array $attrs          Order attributes.
 * @param int   $renewLicenseId Explicit license id from custom_data (manual renewal).
 * @param int   $subscriptionId Subscription id — license resolved by ls_subscription_id.
 */
function processRenewalPayment(PDO $pdo, array $attrs, int $renewLicenseId, int $subscriptionId): bool
{
    $lsOrderId = (string)($attrs['id'] ?? '');
    $email = $attrs['user_email'] ?? '';
    $status = mapOrderStatus($attrs['status'] ?? '');

    if ($status !== 'completed') {
        // Only paid payments extend the deadline.
        return true;
    }

    // Resolve the license: explicit id wins, otherwise by subscription.
    $licenseId = $renewLicenseId;
    $subscriptionRef = $subscriptionId;
    if ($licenseId <= 0 && $subscriptionRef > 0) {
        $stmt = $pdo->prepare("SELECT id FROM licenses WHERE ls_subscription_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$subscriptionRef]);
        $row = $stmt->fetch();
        $licenseId = (int)($row['id'] ?? 0);
    }

    if ($licenseId <= 0) {
        error_log("PVR webhook: renewal payment {$lsOrderId} — license not found (renew_license_id={$renewLicenseId}, subscription_id={$subscriptionId}); falling back to license creation");
        return false;
    }

    // Extend on the licensing server (+1 year; the server sums with the
    // remaining term, so early renewal keeps the days).
    $result = provision_renew_license($licenseId, 365);
    if ($result === null || !isset($result['expires'])) {
        error_log("PVR webhook: renewal provisioning failed for license {$licenseId} (order {$lsOrderId})");
        return false;
    }

    $expiresRaw = (string)$result['expires'];
    if ($expiresRaw === 'never') {
        // Вечная лицензия — продлевать нечего (защита от повторной оплаты
        // по lifetime-ключу). Считаем платёж обработанным.
        error_log("PVR webhook: renewal payment {$lsOrderId} — license {$licenseId} is lifetime, nothing to extend");
        return true;
    }

    $licenseUntil = gmdate('Y-m-d H:i:s', (int)$expiresRaw);

    $stmt = $pdo->prepare("
        UPDATE licenses SET updates_until = ?, support_until = ?, updated_at = CURRENT_TIMESTAMP
        WHERE admin_license_id = ?
    ");
    $stmt->execute([$licenseUntil, $licenseUntil, $licenseId]);

    // Renewal confirmation email (when the customer email is known).
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT domain FROM licenses WHERE admin_license_id = ? LIMIT 1");
        $stmt->execute([$licenseId]);
        $licRow = $stmt->fetch();
        sendRenewalEmail(
            $email,
            (string)($licRow['domain'] ?? ''),
            gmdate('Y-m-d', (int)$expiresRaw),
            $subscriptionRef > 0
        );
    }

    return true;
}

/**
 * Link a newly created subscription to its license.
 * The initial subscription order also arrives as order_created; this handler
 * only attaches the subscription id so future recurring charges can be
 * matched to the license.
 */
function processSubscriptionCreated(PDO $pdo, array $attrs, array $customData): void
{
    $subscriptionId = (int)($attrs['id'] ?? 0);
    if ($subscriptionId <= 0) {
        return;
    }

    $orderId = (string)($attrs['order_id'] ?? '');

    if ($orderId !== '') {
        $stmt = $pdo->prepare("
            SELECT l.id FROM licenses l
            JOIN orders o ON l.order_id = o.id
            WHERE o.ls_order_id = ? ORDER BY l.id DESC LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $pdo->prepare("UPDATE licenses SET ls_subscription_id = ? WHERE id = ?");
            $stmt->execute([$subscriptionId, (int)$row['id']]);
            return;
        }
    }

    // Fallback: session token from checkout custom_data.
    $sessionToken = $customData['session_token'] ?? '';
    if ($sessionToken !== '') {
        $stmt = $pdo->prepare("
            SELECT l.id FROM licenses l
            JOIN orders o ON l.order_id = o.id
            WHERE o.session_id = (SELECT id FROM checkout_sessions WHERE token = ?)
            ORDER BY l.id DESC LIMIT 1
        ");
        $stmt->execute([$sessionToken]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $pdo->prepare("UPDATE licenses SET ls_subscription_id = ? WHERE id = ?");
            $stmt->execute([$subscriptionId, (int)$row['id']]);
        }
    }
}

/**
 * Send a renewal confirmation email.
 */
function sendRenewalEmail(string $to, string $domain, string $updatesUntil, bool $isSubscription): bool
{
    $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
    $fromEmail = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : 'noreply@pv-reviews.site';

    if ($apiKey === '' || $to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $kind = $isSubscription ? 'subscription renewed' : 'license renewed';
    $subject = "PVR Media Reviews — license renewed until {$updatesUntil}";

    $htmlBody = '<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif; max-width:560px; margin:0 auto; padding:40px 20px; color:#1e293b;">
  <h1 style="font-size:20px; text-align:center;">Your ' . htmlspecialchars($kind) . '</h1>
  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:24px;text-align:center;">
    <p style="font-size:14px;color:#64748b;margin:0 0 8px;">Domain: <strong>' . htmlspecialchars($domain) . '</strong></p>
    <p style="font-size:14px;color:#64748b;margin:0;">License &amp; Updates Until: <strong style="color:#10b981;">' . htmlspecialchars($updatesUntil) . '</strong></p>
  </div>
  <p style="font-size:13px;color:#94a3b8;text-align:center;margin-top:32px;">Questions? Just reply to this email.</p>
</body></html>';

    $payload = json_encode([
        'from'    => 'PVR Media Reviews <' . $fromEmail . '>',
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $htmlBody,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}
