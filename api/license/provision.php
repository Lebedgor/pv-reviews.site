<?php
/**
 * PVR Media Reviews — License Provisioning Client
 *
 * Server-to-server HTTP client for creating/suspending licenses
 * on the admin.claz.site licensing system.
 *
 * @package PhotoVideoReviews
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Create a license on the licensing server.
 *
 * New model: a single perpetual license with updates included
 * (l_updates_expires = 0). If a license already exists for the same
 * domain (or exact license_key), the server extends it instead of
 * creating a duplicate and responds with status 'renewed'.
 *
 * @return array{status?:string, license_id:int, license_key:string, expires?:mixed}|null
 */
function provision_create_license(string $domain, string $orderId, string $email = '', string $name = '', string $licenseKey = ''): ?array
{
    $url = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    $key = defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '';

    if ($url === '' || $key === '') {
        error_log('PVR provision: ADMIN_API_URL or ADMIN_API_KEY not configured.');
        return null;
    }

    $payload = [
        'action'          => 'create',
        'domain'          => $domain,
        'expires'         => 'never',
        'external_id'     => $orderId,
        'customer_email'  => $email,
        'customer_name'   => $name,
        'method_id'       => 1,
        'max_instances'   => 1,
    ];

    if ($licenseKey !== '') {
        $payload['license_key'] = $licenseKey;
    }

    $response = provision_post($url, $key, $payload);

    if ($response === null) {
        return null;
    }

    return $response;
}

function provision_suspend_license(int $licenseId): ?array
{
    $url = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    $key = defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '';

    if ($url === '' || $key === '') {
        error_log('PVR provision: ADMIN_API_URL or ADMIN_API_KEY not configured.');
        return null;
    }

    $payload = [
        'action'     => 'suspend',
        'license_id' => $licenseId,
    ];

    $response = provision_post($url, $key, $payload);

    if ($response === null) {
        return null;
    }

    return $response;
}

/**
 * Look up a license by key OR domain (public, non-sensitive fields only).
 *
 * @return array{license_id:int, license_status:int, domain:string, key_mask:string, expires:string, updates_expires:int}|null
 */
function provision_lookup_license(string $key = '', string $domain = ''): ?array
{
    $url = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    $key_ = defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '';

    if ($url === '' || $key_ === '') {
        error_log('PVR provision: ADMIN_API_URL or ADMIN_API_KEY not configured.');
        return null;
    }

    $payload = [
        'action' => 'lookup',
        'key'    => $key,
        'domain' => $domain,
    ];

    return provision_post($url, $key_, $payload);
}

/**
 * Extend the license term (l_expires) by the given period.
 *
 * The server sums with the remaining term, so early renewal keeps the
 * days. Eternal licenses ('never') are returned unchanged.
 *
 * @return array{license_id:int, expires:mixed, until?:string}|null
 */
function provision_renew_license(int $licenseId, int $days = 365): ?array
{
    $url = defined('ADMIN_API_URL') ? ADMIN_API_URL : '';
    $key = defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '';

    if ($url === '' || $key === '') {
        error_log('PVR provision: ADMIN_API_URL or ADMIN_API_KEY not configured.');
        return null;
    }

    if ($licenseId <= 0 || $days <= 0) {
        return null;
    }

    $payload = [
        'action'     => 'renew',
        'license_id' => $licenseId,
        'days'       => $days,
    ];

    return provision_post($url, $key, $payload);
}

function provision_post(string $url, string $apiKey, array $payload): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('PVR provision: curl error — ' . $curlError);
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("PVR provision: HTTP {$httpCode} — " . substr($response, 0, 500));
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('PVR provision: invalid JSON response.');
        return null;
    }

    return $data;
}
