<?php
/**
 * PVR Media Reviews — Configuration & Bootstrap
 *
 * Loads .env, defines constants, provides database connection.
 * This file is never directly accessible from the web.
 */

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

// Load .env file
$envPath = PROJECT_ROOT . '/.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    exit('Configuration error.');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    if (strpos($line, '=') === false) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    if (!defined($key)) {
        define($key, $value);
    }
}

// App constants
define('APP_DEBUG', defined('APP_ENV') && APP_ENV === 'development');
define('DB_PATH', defined('SQLITE_DB_PATH') ? SQLITE_DB_PATH : PROJECT_ROOT . '/storage/pvr.db');

/**
 * Runtime settings stored in the DB (edited via /api/admin.php) override
 * .env constants. Wrapped defensively: on a fresh DB the settings table
 * does not exist yet and .env values are used as-is.
 */
try {
    if (file_exists(DB_PATH)) {
        $settingsPdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $settingsRows = $settingsPdo
            ->query("SELECT key, value FROM settings")
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settingsRows as $row) {
            if (isset($row['key'], $row['value']) && $row['key'] !== '' && !defined($row['key'])) {
                define($row['key'], $row['value']);
            }
        }
        unset($settingsPdo, $settingsRows);
    }
} catch (Throwable $e) {
    // No settings table yet — .env values are in effect.
}

/**
 * Get PDO connection to SQLite database.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!file_exists(DB_PATH)) {
            http_response_code(500);
            exit('Database not initialized.');
        }
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    return $pdo;
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a plain error response.
 */
function error_response(string $message, int $status = 400): void
{
    json_response(['error' => $message], $status);
}

/**
 * Read the raw request body.
 */
function raw_body(): string
{
    $body = file_get_contents('php://input');
    if ($body === false || $body === '') {
        error_response('Empty request body.');
    }
    return $body;
}

/**
 * Parse JSON body and return decoded array.
 */
function json_body(): array
{
    $body = raw_body();
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_response('Invalid JSON.');
    }
    if (!is_array($data)) {
        error_response('Invalid JSON structure.');
    }
    return $data;
}

/**
 * Generate a cryptographically secure random hex token.
 */
function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * Normalize a website URL to a bare hostname.
 *
 * - Strips protocol, path, query, fragment, port
 * - Removes www. prefix
 * - Lowercases
 * - Validates it looks like a real hostname
 */
function normalize_domain(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    // Reject dangerous schemes
    if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
        return '';
    }

    // Add protocol if missing so parse_url works
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $parsed = parse_url($url);
    if ($parsed === false || empty($parsed['host'])) {
        return '';
    }

    $host = strtolower($parsed['host']);

    // Remove port
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }

    // Remove www.
    if (preg_match('/^www\./i', $host)) {
        $host = substr($host, 4);
    }

    // Basic hostname validation: must contain at least one dot, only valid chars
    if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $host)) {
        return '';
    }

    // Reject localhost, IP addresses
    if ($host === 'localhost' || preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
        return '';
    }

    return $host;
}

/**
 * Require a specific HTTP method.
 */
function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405);
        header('Allow: ' . $method);
        exit;
    }
}
