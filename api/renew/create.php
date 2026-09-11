<?php
/**
 * PVR Media Reviews — Create Renewal Checkout Session
 *
 * POST /api/renew/create.php
 *
 * Accepts a license key OR domain, validates the license, and returns
 * the payment provider checkout URL. The webhook recognizes the renewal
 * by custom_data.renew_license_id and extends the subscription instead
 * of creating a new license.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../providers/factory.php';
require_once __DIR__ . '/../license/provision.php';

require_method('POST');

if (!defined('APP_URL') || APP_URL === '') {
    error_response('Checkout is not configured. Please contact support.', 500);
}
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$body = json_body();

$query = trim((string)($body['query'] ?? ''));
if ($query === '') {
    error_response('Enter your license key or domain.');
}

$isKey = preg_match('/^[A-Za-z0-9]+(-[A-Za-z0-9]+)+$/', $query) === 1 && strpos($query, '.') === false;

// --- Resolve the license (server is the source of truth) ---
if ($isKey) {
    $found = provision_lookup_license($query, '');
} else {
    $domain = normalize_domain($query);
    if ($domain === '') {
        error_response('Invalid domain.');
    }
    // Local DB first: the checkout session knows the domain even when the
    // license has not been activated on the site yet (admin l_domain empty).
    $found = null;
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT admin_license_id, admin_license_key
            FROM licenses
            WHERE status = 'active' AND domain = ? AND admin_license_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$domain]);
        $local = $stmt->fetch();
        if ($local) {
            $found = [
                'license_id'     => (int)$local['admin_license_id'],
                'license_status' => 0,
                'domain'         => $domain,
            ];
        }
    } catch (PDOException $e) {
        // Fall through to the licensing server lookup.
    }

    if ($found === null) {
        $found = provision_lookup_license('', $domain);
    }
}

if ($found === null || !isset($found['license_id']) || (int)$found['license_id'] <= 0) {
    error_response('License not found. Check the key or domain and try again.');
}
if ((int)($found['license_status'] ?? 0) !== 0) {
    error_response('This license is not active. Please contact support.');
}

// Уточняем срок у сервера лицензий: вечная лицензия в продлении не нуждается.
$expiresRaw = (string)($found['expires'] ?? '');
if ($expiresRaw === '') {
    $enrich = $isKey ? provision_lookup_license($query, '') : provision_lookup_license('', $domain);
    if ($enrich !== null && isset($enrich['expires'])) {
        $expiresRaw = (string)$enrich['expires'];
        $found = array_merge($found, $enrich);
    }
}
if ($expiresRaw === 'never') {
    error_response('This is a lifetime license — updates are included forever. Nothing to renew.');
}

$licenseId = (int)$found['license_id'];
$domain    = (string)($found['domain'] ?? '');
$licenseKey = (string)($found['license_key'] ?? ($isKey ? $query : ''));

$provider = getPaymentProvider();
if (!$provider->isConfigured()) {
    error_response('Checkout is not configured. Please contact support.', 500);
}

// --- Create a checkout session tagged as a renewal ---
$token = generate_token(32);
$expiresAt = date('Y-m-d H:i:s', time() + 1800);

try {
    $pdo = db();
    // status stays 'pending' (CHECK constraint); the renewal is recognized
    // by custom_data.renew_license_id in the webhook.
    $stmt = $pdo->prepare("
        INSERT INTO checkout_sessions (token, website_url, normalized_domain, status, expires_at)
        VALUES (?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([$token, $domain, $domain, $expiresAt]);
} catch (PDOException $e) {
    error_response('Failed to create checkout session.', 500);
}

try {
    $result = $provider->createCheckout($token, $domain, '', [
        'session_token'      => $token,
        'renew_license_id'   => $licenseId,
        'renew_license_key'  => $licenseKey,
    ]);
    $checkoutUrl = $result['checkout_url'];
    $providerSessionId = $result['provider_session_id'] ?? '';
} catch (RuntimeException $e) {
    $stmt = $pdo->prepare("UPDATE checkout_sessions SET status = 'failed' WHERE token = ?");
    $stmt->execute([$token]);
    error_response('Payment provider error. Please try again.', 502);
}

if ($providerSessionId) {
    $stmt = $pdo->prepare("UPDATE checkout_sessions SET ls_checkout_id = ? WHERE token = ?");
    $stmt->execute([$providerSessionId, $token]);
}

json_response([
    'checkout_url' => $checkoutUrl,
    'token'        => $token,
    'license_id'   => $licenseId,
]);
