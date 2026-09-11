<?php
/**
 * PVR Media Reviews — Renewal License Lookup
 *
 * POST /api/renew/lookup.php
 *
 * Accepts a license key OR a domain. Returns non-sensitive info
 * (masked key, domain, updates deadline) so the customer can confirm
 * what they are renewing. The full key is never returned here.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../license/provision.php';

require_method('POST');

$body = json_body();

$query = trim((string)($body['query'] ?? ''));
if ($query === '') {
    error_response('Enter your license key or domain.');
}
if (strlen($query) > 255) {
    error_response('Input is too long.');
}

// Heuristic: license keys contain dashes and no dots
// (XXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX). Everything else is treated as domain.
$isKey = preg_match('/^[A-Za-z0-9]+(-[A-Za-z0-9]+)+$/', $query) === 1 && strpos($query, '.') === false;

$found = null;
if ($isKey) {
    $found = provision_lookup_license($query, '');
} else {
    $domain = normalize_domain($query);
    if ($domain === '') {
        error_response('Invalid domain. Please enter a valid domain (e.g., example.com).');
    }
    // Local DB first (has customer email for key resend), then the licensing server.
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT l.admin_license_id, l.admin_license_key, l.domain, l.updates_until, l.status
            FROM licenses l
            WHERE l.status = 'active' AND l.domain = ?
            ORDER BY l.id DESC LIMIT 1
        ");
        $stmt->execute([$domain]);
        $local = $stmt->fetch();
        if ($local) {
            $found = [
                'license_id'      => (int)$local['admin_license_id'],
                'license_status'  => 0,
                'domain'          => $local['domain'],
                'key_mask'        => $local['admin_license_key'] !== '' ? substr($local['admin_license_key'], 0, 8) . '...' : '',
                'updates_expires' => $local['updates_until'] ? strtotime($local['updates_until']) : 0,
                '_local_email'    => true,
            ];
        }
    } catch (PDOException $e) {
        // Fall through to the licensing server lookup.
    }

    if ($found === null) {
        $found = provision_lookup_license('', $domain);
        if ($found !== null && isset($found['error'])) {
            $found = null;
        }
    }
}

// Уточняем срок лицензии у сервера лицензий (локальная база его не хранит
// для вечных лицензий): 'never' = lifetime, timestamp = датированная.
$expiresRaw = (string)($found['expires'] ?? '');
if ($expiresRaw === '' && $found !== null) {
    $enrich = null;
    if ($isKey) {
        $enrich = provision_lookup_license($query, '');
    } elseif (isset($domain)) {
        $enrich = provision_lookup_license('', $domain);
    }
    if ($enrich !== null && isset($enrich['expires'])) {
        $found['expires'] = $enrich['expires'];
        $expiresRaw = (string)$enrich['expires'];
    }
}

if ($found === null || !isset($found['license_id']) || (int)$found['license_id'] <= 0) {
    json_response(['status' => 'not_found']);
}

if ($expiresRaw === 'never' || $expiresRaw === '') {
    // Вечная лицензия: продлевать нечего — обновления включены навсегда.
    json_response([
        'status'         => 'found',
        'type'           => $isKey ? 'key' : 'domain',
        'license_id'     => (int)$found['license_id'],
        'domain'         => (string)($found['domain'] ?? ''),
        'key_mask'       => (string)($found['key_mask'] ?? ''),
        'license_status' => (int)($found['license_status'] ?? 0),
        'updates_until'  => null,
        'updates_expired'=> false,
        'unlimited'      => true,
        'renewable'      => false,
        'can_resend'     => isset($found['_local_email']),
    ]);
}

$expiresTs = (int)$expiresRaw;
json_response([
    'status'         => 'found',
    'type'           => $isKey ? 'key' : 'domain',
    'license_id'     => (int)$found['license_id'],
    'domain'         => (string)($found['domain'] ?? ''),
    'key_mask'       => (string)($found['key_mask'] ?? ''),
    'license_status' => (int)($found['license_status'] ?? 0),
    'updates_until'  => gmdate('Y-m-d', $expiresTs),
    'updates_expired'=> $expiresTs < time(),
    'unlimited'      => false,
    'renewable'      => true,
    'can_resend'     => isset($found['_local_email']),
]);
