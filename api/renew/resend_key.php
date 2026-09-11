<?php
/**
 * PVR Media Reviews — Resend License Key by Email
 *
 * POST /api/renew/resend_key.php
 *
 * For customers who lost their key: enter the domain of the licensed site,
 * and the key is re-sent to the email recorded at purchase time.
 * Rate-limited to prevent email bombing.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email.php';

require_method('POST');

$body = json_body();

$domainInput = trim((string)($body['domain'] ?? ''));
if ($domainInput === '') {
    error_response('Domain is required.');
}

$domain = normalize_domain($domainInput);
if ($domain === '') {
    error_response('Invalid domain. Please enter a valid domain (e.g., example.com).');
}

// --- Simple rate limit: 3 requests per domain per hour (SQLite) ---
try {
    $pdo = db();
} catch (RuntimeException $e) {
    error_response('Service unavailable. Please try again later.', 500);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS resend_throttle (
    domain TEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("DELETE FROM resend_throttle WHERE sent_at < datetime('now', '-1 hour')");

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM resend_throttle WHERE domain = ?");
$stmt->execute([$domain]);
if ((int)$stmt->fetch()['c'] >= 3) {
    error_response('Too many requests for this domain. Please try again in an hour.', 429);
}

// --- Find the license locally (has the full key + customer email) ---
$stmt = $pdo->prepare("
    SELECT l.license_key, l.domain, l.updates_until, c.email AS customer_email
    FROM licenses l
    JOIN orders o ON l.order_id = o.id
    JOIN customers c ON l.customer_id = c.id
    WHERE l.status = 'active' AND l.domain = ?
    ORDER BY l.id DESC LIMIT 1
");
$stmt->execute([$domain]);
$license = $stmt->fetch();

if (!$license || $license['license_key'] === '' || $license['customer_email'] === '') {
    // Do not reveal whether the domain exists.
    json_response(['status' => 'sent', 'message' => 'If this domain has an active license, the key has been sent to the registered email.']);
}

// --- Record the request BEFORE sending (anti-bombing) ---
$stmt = $pdo->prepare("INSERT INTO resend_throttle (domain) VALUES (?)");
$stmt->execute([$domain]);

$sent = sendLicenseEmail(
    $license['customer_email'],
    $license['domain'],
    $license['license_key'],
    $license['updates_until'] !== '' ? gmdate('Y-m-d', strtotime($license['updates_until'])) : ''
);

if (!$sent) {
    error_response('Failed to send the email. Please try again later.', 500);
}

json_response(['status' => 'sent', 'message' => 'The license key has been sent to the email registered with this purchase.']);
