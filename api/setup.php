<?php
/**
 * PVR Media Reviews — Database Initialization
 *
 * Run this script once to create the SQLite database and tables.
 * php /var/www/pv-reviews.site/api/setup.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Only allow CLI execution — no web-based setup
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden. Run from CLI only.');
}

$dbPath = DB_PATH;
$dir = dirname($dbPath);

if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

if (file_exists($dbPath)) {
    echo "Database already exists at {$dbPath}\n";
    echo "To re-initialize, delete the file first.\n";
    exit(0);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec('PRAGMA journal_mode=WAL');

$pdo->exec("
    CREATE TABLE customers (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        email           TEXT NOT NULL UNIQUE,
        name            TEXT,
        ls_customer_id  TEXT,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE checkout_sessions (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        token           TEXT NOT NULL UNIQUE,
        email           TEXT,
        website_url     TEXT NOT NULL,
        normalized_domain TEXT NOT NULL,
        status          TEXT DEFAULT 'pending' CHECK(status IN ('pending','completed','expired','failed')),
        ls_checkout_id  TEXT,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at      DATETIME
    );

    CREATE TABLE orders (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        ls_order_id     TEXT NOT NULL UNIQUE,
        customer_id     INTEGER REFERENCES customers(id),
        session_id      INTEGER REFERENCES checkout_sessions(id),
        product_name    TEXT,
        variant_name    TEXT,
        amount          INTEGER,
        currency        TEXT DEFAULT 'USD',
        status          TEXT DEFAULT 'pending' CHECK(status IN ('pending','completed','refunded','failed')),
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE licenses (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key     TEXT UNIQUE,
        order_id        INTEGER REFERENCES orders(id),
        customer_id     INTEGER REFERENCES customers(id),
        domain          TEXT NOT NULL,
        status          TEXT DEFAULT 'active' CHECK(status IN ('active','expired','revoked')),
        updates_until   DATETIME,
        support_until   DATETIME,
        admin_license_id INTEGER,
        admin_license_key TEXT,
        ls_subscription_id TEXT,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE webhook_events (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        provider        TEXT NOT NULL DEFAULT 'lemonsqueezy',
        provider_event_id TEXT UNIQUE,
        event_type      TEXT NOT NULL,
        payload_hash    TEXT NOT NULL,
        processed_at    DATETIME,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX idx_checkout_sessions_token ON checkout_sessions(token);
    CREATE INDEX idx_checkout_sessions_domain ON checkout_sessions(normalized_domain);
    CREATE INDEX idx_orders_ls_order_id ON orders(ls_order_id);
    CREATE INDEX idx_orders_customer_id ON orders(customer_id);
    CREATE INDEX idx_licenses_key ON licenses(license_key);
    CREATE INDEX idx_licenses_domain ON licenses(domain);
    CREATE INDEX idx_licenses_customer ON licenses(customer_id);
    CREATE INDEX idx_webhook_events_event_id ON webhook_events(provider_event_id);
");

// Set permissions
chmod($dbPath, 0640);

echo "Database initialized successfully at {$dbPath}\n";
echo "Tables created: customers, checkout_sessions, orders, licenses, webhook_events\n";
