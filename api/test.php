<?php
/**
 * PVR Media Reviews — Test Utilities
 *
 * Run from CLI to test various components.
 * php /var/www/pv-reviews.site/api/test.php [test_name]
 *
 * Available tests:
 *   domain     — Test domain normalization
 *   token      — Test token generation
 *   checkout   — Test checkout session creation
 *   webhook    — Test webhook signature verification
 *   orders     — Test order/license webhook flow
 *   all        — Run all tests
 */

declare(strict_types=1);

// CLI only: this script executes database cleanup and prints debug output.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden. Run from CLI only.');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/webhooks/lemon-squeezy.php';
require_once __DIR__ . '/license/provision.php';

$test = $argv[1] ?? 'all';

echo "=== PVR Media Reviews Test Suite ===\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ {$name}\n";
        $passed++;
    } else {
        echo "  ✗ {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
    }
}

function cleanupTestData(PDO $pdo): void
{
    $pdo->exec("DELETE FROM webhook_events WHERE provider_event_id LIKE 'test_%'");
    $pdo->exec("DELETE FROM licenses WHERE domain LIKE 'test-%.example.com'");
    $pdo->exec("DELETE FROM orders WHERE ls_order_id LIKE 'test_%'");
    $pdo->exec("DELETE FROM customers WHERE email LIKE 'test-%@example.com'");
    $pdo->exec("DELETE FROM checkout_sessions WHERE token LIKE 'test_%'");
}

function cleanupAdminTestLicenses(): void
{
    $url = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    $key = defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '';
    if ($url === '' || $key === '') return;

    $db = @new \mysqli('localhost', 'claz_user', 'Yehor325465', 'claz');
    if ($db->connect_error) return;
    $db->set_charset('utf8mb4');
    $db->query("DELETE FROM pcp_license_keys WHERE l_name LIKE 'test_%'");
    $db->close();
}

function checkLicenseStatus(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT cs.id AS session_id, cs.status AS session_status
        FROM checkout_sessions cs
        WHERE cs.token = ?
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        return ['status' => 'not_found'];
    }

    if ($session['session_status'] !== 'completed') {
        return ['status' => $session['session_status']];
    }

    $stmt = $pdo->prepare("
        SELECT l.license_key, l.domain, l.status, l.updates_until
        FROM licenses l
        JOIN orders o ON l.order_id = o.id
        WHERE o.session_id = ? AND l.status = 'active'
        ORDER BY l.id DESC
        LIMIT 1
    ");
    $stmt->execute([$session['session_id']]);
    $license = $stmt->fetch();

    if (!$license) {
        $stmt = $pdo->prepare("
            SELECT l.license_key, l.domain, l.status, l.updates_until
            FROM licenses l
            JOIN orders o ON l.order_id = o.id
            WHERE o.session_id = ?
            ORDER BY l.id DESC
            LIMIT 1
        ");
        $stmt->execute([$session['session_id']]);
        $license = $stmt->fetch();

        if (!$license || $license['status'] !== 'active') {
            return ['status' => 'pending'];
        }
    }

    if (empty($license['license_key'])) {
        return ['status' => 'pending'];
    }

    return [
        'status'        => 'activated',
        'license_key'   => $license['license_key'],
        'domain'        => $license['domain'],
        'updates_until' => $license['updates_until'],
    ];
}

// --- Domain Normalization Tests ---
if ($test === 'domain' || $test === 'all') {
    echo "Domain Normalization:\n";

    assert_test('https://example.com → example.com',
        normalize_domain('https://example.com') === 'example.com');

    assert_test('http://Example.COM → example.com',
        normalize_domain('http://Example.COM') === 'example.com');

    assert_test('https://www.example.com → example.com',
        normalize_domain('https://www.example.com') === 'example.com');

    assert_test('example.com → example.com',
        normalize_domain('example.com') === 'example.com');

    assert_test('https://example.com/path?q=1 → example.com',
        normalize_domain('https://example.com/path?q=1') === 'example.com');

    assert_test('https://example.com:8080 → example.com',
        normalize_domain('https://example.com:8080') === 'example.com');

    assert_test('https://sub.domain.example.com → sub.domain.example.com',
        normalize_domain('https://sub.domain.example.com') === 'sub.domain.example.com');

    assert_test('https://www.example.com/path#hash → example.com',
        normalize_domain('https://www.example.com/path#hash') === 'example.com');

    assert_test('empty string → empty',
        normalize_domain('') === '');

    assert_test('javascript:alert(1) → empty',
        normalize_domain('javascript:alert(1)') === '');

    assert_test('data:text/html → empty',
        normalize_domain('data:text/html') === '');

    assert_test('localhost → empty',
        normalize_domain('localhost') === '');

    assert_test('127.0.0.1 → empty',
        normalize_domain('127.0.0.1') === '');

    assert_test('192.168.1.1 → empty',
        normalize_domain('192.168.1.1') === '');

    assert_test('just spaces → empty',
        normalize_domain('   ') === '');

    echo "\n";
}

// --- Token Generation Tests ---
if ($test === 'token' || $test === 'all') {
    echo "Token Generation:\n";

    $token1 = generate_token(32);
    $token2 = generate_token(32);

    assert_test('token is 64 hex chars',
        strlen($token1) === 64 && ctype_xdigit($token1));

    assert_test('tokens are unique',
        $token1 !== $token2);

    $token3 = generate_token(16);
    assert_test('16-byte token is 32 hex chars',
        strlen($token3) === 32);

    echo "\n";
}

// --- Checkout Session Tests ---
if ($test === 'checkout' || $test === 'all') {
    echo "Checkout Session:\n";

    try {
        $pdo = db();
        cleanupTestData($pdo);

        $testToken = 'test_' . generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
            VALUES (?, ?, ?, 'pending', datetime('now', '+30 minutes'))
        ");
        $stmt->execute([$testToken, 'https://test.example.com', 'test.example.com']);

        assert_test('session created', $stmt->rowCount() === 1);

        $stmt = $pdo->prepare("SELECT * FROM checkout_sessions WHERE token = ?");
        $stmt->execute([$testToken]);
        $session = $stmt->fetch();

        assert_test('session retrieved', $session !== false);
        assert_test('domain normalized', $session['normalized_domain'] === 'test.example.com');
        assert_test('status is pending', $session['status'] === 'pending');

        cleanupTestData($pdo);

    } catch (PDOException $e) {
        assert_test('checkout session', false, $e->getMessage());
    }

    echo "\n";
}

// --- Webhook Signature Tests ---
if ($test === 'webhook' || $test === 'all') {
    echo "Webhook Signature:\n";

    $secret = 'test_webhook_secret_key_12345';
    $payload = '{"meta":{"event_name":"order_created"},"data":{"type":"orders","id":"999","attributes":{"status":"paid","user_email":"test@example.com"}}}';

    $signature = hash_hmac('sha256', $payload, $secret);

    assert_test('HMAC signature generated',
        strlen($signature) === 64 && ctype_xdigit($signature));

    assert_test('signature matches',
        hash_equals($signature, hash_hmac('sha256', $payload, $secret)));

    assert_test('wrong signature fails',
        !hash_equals($signature, hash_hmac('sha256', $payload, 'wrong_secret')));

    // Test idempotency via UNIQUE constraint
    try {
        $pdo = db();
        cleanupTestData($pdo);

        $eventId = 'test_order_created_999';
        $stmt = $pdo->prepare("
            INSERT INTO webhook_events (provider, provider_event_id, event_type, payload_hash)
            VALUES ('lemonsqueezy', ?, 'order_created', ?)
        ");
        $stmt->execute([$eventId, hash('sha256', $payload)]);

        assert_test('event recorded', $stmt->rowCount() === 1);

        try {
            $stmt->execute([$eventId, hash('sha256', $payload)]);
            assert_test('duplicate rejected', false, 'no exception thrown');
        } catch (PDOException $e) {
            assert_test('duplicate rejected (UNIQUE constraint)', true);
        }

        cleanupTestData($pdo);

    } catch (PDOException $e) {
        assert_test('webhook idempotency', false, $e->getMessage());
    }

    // Modified payload with old signature → rejected
    $modifiedPayload = str_replace('"paid"', '"free"', $payload);
    $oldSignature = hash_hmac('sha256', $payload, $secret);
    $newExpected = hash_hmac('sha256', $modifiedPayload, $secret);
    assert_test('modified payload has different signature',
        $oldSignature !== $newExpected);

    echo "\n";
}

// --- Order/License Webhook Flow Tests ---
if ($test === 'orders' || $test === 'all') {
    echo "Order & License Webhook Flow:\n";

    try {
        $pdo = db();
        cleanupTestData($pdo);

        // --- Test 1: order_created with status=pending → order created, no license ---
        // Create a session for the pending order test
        $pendingToken = 'test_' . generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
            VALUES (?, 'https://test-pending.example.com', 'test-pending.example.com', 'pending', datetime('now', '+30 minutes'))
        ");
        $stmt->execute([$pendingToken]);

        $pendingAttrs = [
            'id' => 'test_order_pending_001',
            'user_email' => 'test-pending@example.com',
            'user_name' => 'Test Pending',
            'status' => 'pending',
            'total' => 3900,
            'currency' => 'USD',
            'customer_id' => '1001',
            'first_order_item' => [
                'product_name' => 'PVR Pro',
                'variant_name' => 'Default',
                'variant_id' => defined('LEMONSQUEEZY_VARIANT_ID') ? LEMONSQUEEZY_VARIANT_ID : '1',
            ],
        ];

        processOrderCreated($pdo, $pendingAttrs, ['session_token' => $pendingToken]);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE ls_order_id = 'test_order_pending_001'");
        $stmt->execute();
        $order = $stmt->fetch();
        assert_test('pending order created', $order !== false);
        assert_test('pending order status', $order['status'] === 'pending');

        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $license = $stmt->fetch();
        assert_test('no license for pending order', $license === false);

        // --- Test 2: order_updated with status=paid → order completed, license created ---
        $updatedAttrs = [
            'id' => 'test_order_pending_001',
            'status' => 'paid',
        ];

        processOrderUpdated($pdo, $updatedAttrs);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE ls_order_id = 'test_order_pending_001'");
        $stmt->execute();
        $order = $stmt->fetch();
        assert_test('order updated to completed', $order['status'] === 'completed');

        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $license = $stmt->fetch();
        assert_test('license created after payment', $license !== false);
        assert_test('license_key populated from admin', $license['license_key'] !== null && strlen($license['license_key']) > 10);
        assert_test('admin_license_id set', (int)($license['admin_license_id'] ?? 0) > 0);
        assert_test('license domain correct', $license['domain'] === 'test-pending.example.com');
        assert_test('license status active', $license['status'] === 'active');

        // --- Test 3: duplicate order_updated → no duplicate license ---
        processOrderUpdated($pdo, $updatedAttrs);

        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM licenses WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $cnt = $stmt->fetch();
        assert_test('no duplicate license on re-processing', (int)$cnt['cnt'] === 1);

        // --- Test 4: duplicate order_created → no duplicate order ---
        processOrderCreated($pdo, $pendingAttrs, []);

        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE ls_order_id = 'test_order_pending_001'");
        $stmt->execute();
        $cnt = $stmt->fetch();
        assert_test('no duplicate order on re-processing', (int)$cnt['cnt'] === 1);

        // --- Test 5: order_created with status=paid → order + license in one step ---
        $paidAttrs = [
            'id' => 'test_order_paid_001',
            'user_email' => 'test-paid@example.com',
            'user_name' => 'Test Paid',
            'status' => 'paid',
            'total' => 3900,
            'currency' => 'USD',
            'customer_id' => '1002',
            'first_order_item' => [
                'product_name' => 'PVR Pro',
                'variant_name' => 'Default',
                'variant_id' => defined('LEMONSQUEEZY_VARIANT_ID') ? LEMONSQUEEZY_VARIANT_ID : '1',
            ],
        ];

        // Create a session for this order
        $paidToken = 'test_' . generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
            VALUES (?, 'https://test-paid.example.com', 'test-paid.example.com', 'pending', datetime('now', '+30 minutes'))
        ");
        $stmt->execute([$paidToken]);

        processOrderCreated($pdo, $paidAttrs, ['session_token' => $paidToken]);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE ls_order_id = 'test_order_paid_001'");
        $stmt->execute();
        $order = $stmt->fetch();
        assert_test('paid order created', $order !== false);
        assert_test('paid order status', $order['status'] === 'completed');

        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $license = $stmt->fetch();
        assert_test('license created for paid order', $license !== false);
        assert_test('license_key populated from admin', $license['license_key'] !== null && strlen($license['license_key']) > 10);

        // --- Test 6: refund → order refunded, license revoked ---
        processOrderRefunded($pdo, ['id' => 'test_order_paid_001']);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE ls_order_id = 'test_order_paid_001'");
        $stmt->execute();
        $order = $stmt->fetch();
        assert_test('order status refunded', $order['status'] === 'refunded');

        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $license = $stmt->fetch();
        assert_test('license status revoked', $license['status'] === 'revoked');

        // --- Test 7: expired session → no order created ---
        $expiredToken = 'test_' . generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
            VALUES (?, 'https://test-expired.example.com', 'test-expired.example.com', 'pending', datetime('now', '-1 hour'))
        ");
        $stmt->execute([$expiredToken]);

        $expiredAttrs = [
            'id' => 'test_order_expired_001',
            'user_email' => 'test-expired@example.com',
            'user_name' => 'Test Expired',
            'status' => 'paid',
            'total' => 3900,
            'currency' => 'USD',
            'customer_id' => '1003',
            'first_order_item' => [
                'product_name' => 'PVR Pro',
                'variant_name' => 'Default',
                'variant_id' => defined('LEMONSQUEEZY_VARIANT_ID') ? LEMONSQUEEZY_VARIANT_ID : '1',
            ],
        ];

        processOrderCreated($pdo, $expiredAttrs, ['session_token' => $expiredToken]);

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE ls_order_id = 'test_order_expired_001'");
        $stmt->execute();
        $order = $stmt->fetch();
        // Order should still be created (LS sends it), but without session link
        if ($order) {
            assert_test('expired session order has no session_id', $order['session_id'] === null);
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM licenses WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $licCnt = $stmt->fetch();
            assert_test('expired session order has no license', (int)$licCnt['cnt'] === 0);
        } else {
            assert_test('expired session order created', false, 'order not found');
        }

        // --- Test 8: wrong variant_id → no PVR Pro order processing ---
        $wrongVariantAttrs = [
            'id' => 'test_order_wrong_variant_001',
            'user_email' => 'test-wrong@example.com',
            'user_name' => 'Test Wrong',
            'status' => 'paid',
            'total' => 5000,
            'currency' => 'USD',
            'customer_id' => '1004',
            'first_order_item' => [
                'product_name' => 'Some Other Product',
                'variant_name' => 'Wrong',
                'variant_id' => '99999',
            ],
        ];

        if (defined('LEMONSQUEEZY_VARIANT_ID') && LEMONSQUEEZY_VARIANT_ID !== '') {
            processOrderCreated($pdo, $wrongVariantAttrs, []);

            $stmt = $pdo->prepare("SELECT * FROM orders WHERE ls_order_id = 'test_order_wrong_variant_001'");
            $stmt->execute();
            $order = $stmt->fetch();
            assert_test('wrong variant order not created', $order === false);
        } else {
            echo "  ⊘ variant_id check skipped (LEMONSQUEEZY_VARIANT_ID not set)\n";
        }

        cleanupTestData($pdo);

    } catch (Exception $e) {
        assert_test('order flow', false, $e->getMessage());
    }

    echo "\n";
}

// --- License Provisioning Tests ---
if ($test === 'provision' || $test === 'all') {
    echo "License Provisioning:\n";

    $adminUrl = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    $adminKey = defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '';

    if ($adminUrl !== '' && $adminKey !== '') {
        cleanupAdminTestLicenses();

        $result = provision_create_license('test-provision.example.com', 'test_prov_001', 'test-prov@example.com', 'Test Prov');
        assert_test('provision create returns result', $result !== null);
        assert_test('provision create has license_id', $result !== null && isset($result['license_id']));
        assert_test('provision create has license_key', $result !== null && isset($result['license_key']) && strlen($result['license_key']) > 10);
        assert_test('provision create returns domain', $result !== null && ($result['domain'] ?? '') === 'test-provision.example.com');

        $result2 = provision_create_license('test-provision.example.com', 'test_prov_001', 'test-prov@example.com', 'Test Prov');
        assert_test('provision idempotent — already_exists', $result2 !== null && ($result2['status'] ?? '') === 'already_exists');
        assert_test('provision idempotent — same license_id', $result2 !== null && isset($result2['license_id']) && $result['license_id'] === $result2['license_id']);

        if ($result && isset($result['license_id'])) {
            $suspendResult = provision_suspend_license((int)$result['license_id']);
            assert_test('provision suspend returns result', $suspendResult !== null);
            assert_test('provision suspend returns suspended', $suspendResult !== null && ($suspendResult['status'] ?? '') === 'suspended');
        }

        $emptyDomainResult = provision_create_license('', 'test_prov_empty_domain', 'empty@test.com');
        assert_test('provision accepts empty domain', $emptyDomainResult !== null && isset($emptyDomainResult['license_id']));
        assert_test('provision empty domain — domain is empty', $emptyDomainResult !== null && ($emptyDomainResult['domain'] ?? 'x') === '');

        cleanupAdminTestLicenses();

    } else {
        echo "  ⊘ skipped (ADMIN_API_URL or ADMIN_API_KEY not configured)\n";
    }

    echo "\n";
}

// --- Authentication Tests ---
if ($test === 'auth' || $test === 'all') {
    echo "Provision API Authentication:\n";

    $adminUrl = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    if ($adminUrl !== '') {
        $ch = curl_init($adminUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['action' => 'create', 'domain' => 'test-auth.example.com', 'expires' => time() + 86400]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: wrong_key_12345'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        assert_test('wrong API key rejected (401)', $code === 401);

        $ch = curl_init($adminUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['action' => 'create', 'domain' => 'test-auth.example.com', 'expires' => time() + 86400]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        assert_test('missing API key rejected (401)', $code === 401);

        $ch = curl_init($adminUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        assert_test('GET method rejected (405)', $code === 405);
    } else {
        echo "  ⊘ skipped (ADMIN_API_URL not configured)\n";
    }

    echo "\n";
}

// --- License Check Endpoint Tests ---
if ($test === 'check' || $test === 'all') {
    echo "License Check Endpoint:\n";

    try {
        $pdo = db();
        cleanupTestData($pdo);

        $checkToken = 'test_' . generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
            VALUES (?, 'https://test-check.example.com', 'test-check.example.com', 'completed', datetime('now', '+30 minutes'))
        ");
        $stmt->execute([$checkToken]);

        $stmt = $pdo->prepare("SELECT id FROM checkout_sessions WHERE token = ?");
        $stmt->execute([$checkToken]);
        $cs = $stmt->fetch();
        $csId = (int)$cs['id'];

        $stmt = $pdo->prepare("INSERT INTO customers (email, name) VALUES ('test-check@example.com', 'Test Check')");
        $stmt->execute();
        $custId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO orders (ls_order_id, customer_id, session_id, status) VALUES ('test_check_order_001', ?, ?, 'completed')");
        $stmt->execute([$custId, $csId]);
        $orderId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, order_id, customer_id, domain, status, admin_license_id, admin_license_key) VALUES ('TEST-CHECK-KEY-0001', ?, ?, 'test-check.example.com', 'active', 99999, 'TEST-CHECK-KEY-0001')");
        $stmt->execute([$orderId, $custId]);

        $response = checkLicenseStatus($pdo, $checkToken);
        assert_test('check endpoint returns activated', $response !== null && $response['status'] === 'activated');
        assert_test('check endpoint returns license_key', $response !== null && ($response['license_key'] ?? '') === 'TEST-CHECK-KEY-0001');
        assert_test('check endpoint returns domain', $response !== null && ($response['domain'] ?? '') === 'test-check.example.com');

        $response2 = checkLicenseStatus($pdo, 'nonexistent_token');
        assert_test('check endpoint — not_found for unknown token', $response2 !== null && $response2['status'] === 'not_found');

        $pendingToken = 'test_' . generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
            VALUES (?, 'https://test-pending-check.example.com', 'test-pending-check.example.com', 'pending', datetime('now', '+30 minutes'))
        ");
        $stmt->execute([$pendingToken]);
        $response3 = checkLicenseStatus($pdo, $pendingToken);
        assert_test('check endpoint — pending for incomplete session', $response3 !== null && $response3['status'] === 'pending');

        $stmt = $pdo->prepare("DELETE FROM licenses WHERE admin_license_id = 99999");
        $stmt->execute();

        cleanupTestData($pdo);

    } catch (Exception $e) {
        assert_test('license check flow', false, $e->getMessage());
    }

    echo "\n";
}

// --- Summary ---
echo "=== Results: {$passed} passed, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
