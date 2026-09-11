<?php
/**
 * PVR Media Reviews — Unified Webhook Router
 *
 * POST /api/webhooks/router.php
 *
 * Routes incoming webhooks to the correct payment provider handler.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../providers/factory.php';
define('PVR_WEBHOOK_ROUTED', true);
require_once __DIR__ . '/../webhooks/lemon-squeezy.php';

$provider = getPaymentProvider();

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo 'Empty body.';
    exit;
}

if (!$provider->verifyWebhook($rawBody, $_SERVER)) {
    http_response_code(401);
    echo 'Invalid signature.';
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'Invalid JSON.';
    exit;
}


$order = $provider->parseWebhook($payload);


if (empty($order['order_id'])) {
    http_response_code(400);
    echo 'No order ID.';
    exit;
}

$pdo = db();

$providerName = defined('PAYMENT_PROVIDER') ? strtolower(PAYMENT_PROVIDER) : 'lemonsqueezy';
$providerEventId = $providerName . '_' . ($order['order_id'] ?? 'unknown');
$stmt = $pdo->prepare("SELECT id FROM webhook_events WHERE provider_event_id = ?");
$stmt->execute([$providerEventId]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'already_processed']);
    exit;
}

$pdo->exec("PRAGMA journal_mode=DELETE");
$stmt = $pdo->prepare("
    INSERT INTO webhook_events (provider, provider_event_id, event_type, payload_hash)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$providerName, $providerEventId, $order['event_type'], hash('sha256', $rawBody)]);

switch ($order['event_type']) {
    case 'order_created':
        $customData = ['session_token' => $order['session_token']];

        $sessionToken = $order['session_token'];
        $sessionEmail = $order['email'] ?? '';
        $sessionDomain = $order['domain'] ?? '';

        if ($sessionToken) {
            $stmt = $pdo->prepare("SELECT id, normalized_domain, email FROM checkout_sessions WHERE token = ?");
            $stmt->execute([$sessionToken]);
            $cs = $stmt->fetch();

            if ($cs) {
                if (empty($sessionDomain) && $cs['normalized_domain'] !== '') {
                    $sessionDomain = $cs['normalized_domain'];
                    $order['domain'] = $sessionDomain;
                }
                if (empty($sessionEmail) && $cs['email'] !== '') {
                    $sessionEmail = $cs['email'];
                }
            }
        }

        $attrs = [
            'id'               => $order['order_id'],
            'user_email'       => $sessionEmail,
            'user_name'        => $order['name'],
            'status'           => $order['status'] === 'completed' ? 'paid' : $order['status'],
            'total'            => $order['total'],
            'currency'         => $order['currency'],
            'customer_id'      => '',
            'first_order_item' => [
                'product_name' => $order['product_name'],
                'variant_name' => $order['variant_name'],
            ],
        ];

        if (!empty($order['domain']) && $sessionToken) {
            $stmt = $pdo->prepare("SELECT id, normalized_domain FROM checkout_sessions WHERE token = ?");
            $stmt->execute([$sessionToken]);
            $cs = $stmt->fetch();
            if ($cs && $cs['normalized_domain'] === '') {
                $pdo->prepare("UPDATE checkout_sessions SET normalized_domain = ? WHERE token = ?")->execute([$order['domain'], $sessionToken]);
            }
        }

        processOrderCreated($pdo, $attrs, $customData, $order['license_key'] ?? null);
        break;

    case 'order_refunded':
        processOrderRefunded($pdo, [
            'id'            => $order['order_id'],
            'refunded_at'   => $order['refunded_at'],
        ]);
        break;

    default:
        break;
}

$pdo->prepare("UPDATE webhook_events SET processed_at = datetime('now') WHERE provider_event_id = ?")->execute([$providerEventId]);

echo json_encode(['status' => 'ok']);
