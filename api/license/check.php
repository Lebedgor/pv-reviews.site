<?php
/**
 * PVR Media Reviews — License Status Check
 *
 * POST /api/license/check.php
 *
 * Used by thank-you.html to poll for license activation status.
 * Returns license details once the webhook has processed the order.
 *
 * @package PhotoVideoReviews
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

require_method('POST');

$body = json_body();

$token = trim((string)($body['token'] ?? ''));
if ($token === '') {
    error_response('Token is required.');
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT cs.id AS session_id, cs.status AS session_status
        FROM checkout_sessions cs
        WHERE cs.token = ?
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        json_response(['status' => 'not_found']);
    }

    if ($session['session_status'] === 'pending') {
        json_response(['status' => 'pending']);
    }

    if ($session['session_status'] === 'failed') {
        json_response(['status' => 'failed']);
    }

    if ($session['session_status'] === 'expired') {
        json_response(['status' => 'expired']);
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

        if (!$license) {
            json_response(['status' => 'pending']);
        }

        if ($license['status'] !== 'active') {
            json_response(['status' => 'pending']);
        }
    }

    if (empty($license['license_key'])) {
        json_response(['status' => 'pending']);
    }

    json_response([
        'status'        => 'activated',
        'license_key'   => $license['license_key'],
        'domain'        => $license['domain'],
        'updates_until' => $license['updates_until'],
        'support_until' => $license['support_until'],
    ]);

} catch (PDOException $e) {
    error_log('PVR license check DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('Processing error.');
}
