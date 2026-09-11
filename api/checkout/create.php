<?php
/**
 * PVR Media Reviews — Create Checkout Session
 *
 * POST /api/checkout/create.php
 *
 * Accepts a website URL, validates it, creates a checkout session,
 * and returns the payment provider checkout URL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../providers/factory.php';

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

if (empty($body['website_url']) || !is_string($body['website_url'])) {
    error_response('Website URL is required.');
}

$websiteUrl = trim($body['website_url']);
if (strlen($websiteUrl) > 2048) {
    error_response('Website URL is too long.');
}

$domain = normalize_domain($websiteUrl);
if ($domain === '') {
    error_response('Invalid website URL. Please enter a valid domain (e.g., example.com).');
}

$customerEmail = isset($body['email']) ? trim((string)$body['email']) : '';
if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    error_response('Invalid email address.');
}

$provider = getPaymentProvider();
if (!$provider->isConfigured()) {
    error_response('Checkout is not configured. Please contact support.', 500);
}

$token = generate_token(32);
$expiresAt = date('Y-m-d H:i:s', time() + 1800);

try {
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO checkout_sessions (token, email, website_url, normalized_domain, status, expires_at)
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([$token, $customerEmail, $websiteUrl, $domain, $expiresAt]);
} catch (PDOException $e) {
    error_response('Failed to create checkout session.', 500);
}

try {
    $result = $provider->createCheckout($token, $domain, $customerEmail, ['session_token' => $token, 'email' => $customerEmail]);
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
    'token' => $token,
]);
