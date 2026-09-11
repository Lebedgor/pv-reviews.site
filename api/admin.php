<?php
/**
 * PVR Media Reviews — Site Admin (mini)
 *
 * /api/admin.php
 *
 * Password-protected admin for the billing site:
 *  - idempotent DB schema sync (creates/updates all tables — replaces
 *    manual setup.php / setup-migration.sql runs)
 *  - overview stats + recent orders
 *
 * Security: session-based, password from .env (ADMIN_PANEL_PASSWORD).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// --- Session hardening ---------------------------------------------------------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,      // HTTPS only
    'httponly' => true,      // no JS access
    'samesite' => 'Lax',     // CSRF mitigation
]);
session_start();

// --- Security headers -----------------------------------------------------------
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate');

// --- CSRF token -------------------------------------------------------------------
if (empty($_SESSION['pvr_csrf'])) {
    $_SESSION['pvr_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Validate the CSRF token of a POST request. On failure, abort the request.
 */
function pvr_csrf_check(): void
{
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['pvr_csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token. Go back, reload the page and try again.');
    }
}

$authenticated = !empty($_SESSION['pvr_admin_auth']);
$passwordSet = defined('ADMIN_PANEL_PASSWORD') && ADMIN_PANEL_PASSWORD !== '';

// --- Schema sync (full, idempotent) ---------------------------------------------
function pvr_site_schema_sync(PDO $pdo): array
{
    $applied = [];
    $skipped = [];

    // Tables (IF NOT EXISTS — idempotent).
    $tables = [
        'customers' => "CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            name TEXT,
            ls_customer_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'checkout_sessions' => "CREATE TABLE IF NOT EXISTS checkout_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT NOT NULL UNIQUE,
            email TEXT,
            website_url TEXT NOT NULL,
            normalized_domain TEXT NOT NULL,
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending','completed','expired','failed')),
            ls_checkout_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME
        )",
        'orders' => "CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ls_order_id TEXT UNIQUE,
            customer_id INTEGER REFERENCES customers(id),
            session_id INTEGER REFERENCES checkout_sessions(id),
            product_name TEXT,
            variant_name TEXT,
            amount INTEGER,
            currency TEXT DEFAULT 'USD',
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending','completed','refunded','failed')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'licenses' => "CREATE TABLE IF NOT EXISTS licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            license_key TEXT UNIQUE,
            order_id INTEGER REFERENCES orders(id),
            customer_id INTEGER REFERENCES customers(id),
            domain TEXT NOT NULL,
            status TEXT DEFAULT 'active' CHECK(status IN ('active','expired','revoked')),
            updates_until DATETIME,
            support_until DATETIME,
            admin_license_id INTEGER,
            admin_license_key TEXT,
            ls_subscription_id TEXT
        )",
        'webhook_events' => "CREATE TABLE IF NOT EXISTS webhook_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT NOT NULL,
            provider_event_id TEXT NOT NULL UNIQUE,
            event_type TEXT,
            payload_hash TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME
        )",
        'resend_throttle' => "CREATE TABLE IF NOT EXISTS resend_throttle (
            domain TEXT NOT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'login_throttle' => "CREATE TABLE IF NOT EXISTS login_throttle (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            success INTEGER NOT NULL DEFAULT 0,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'settings' => "CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )",
    ];

    foreach ($tables as $name => $ddl) {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($name))->fetch();
        if ($exists) {
            $skipped[] = $name;
            continue;
        }
        $pdo->exec($ddl);
        $applied[] = 'table ' . $name;
    }

    // Columns (check pragma, then ALTER).
    $columns = [
        ['licenses', 'admin_license_id',   "ALTER TABLE licenses ADD COLUMN admin_license_id INTEGER"],
        ['licenses', 'admin_license_key',  "ALTER TABLE licenses ADD COLUMN admin_license_key TEXT"],
        ['licenses', 'ls_subscription_id', "ALTER TABLE licenses ADD COLUMN ls_subscription_id TEXT"],
        ['orders',   'session_id',         "ALTER TABLE orders ADD COLUMN session_id INTEGER REFERENCES checkout_sessions(id)"],
        ['checkout_sessions', 'ls_checkout_id', "ALTER TABLE checkout_sessions ADD COLUMN ls_checkout_id TEXT"],
    ];

    foreach ($columns as [$table, $column, $ddl]) {
        $cols = $pdo->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (in_array($column, $names, true)) {
            $skipped[] = $table . '.' . $column;
            continue;
        }
        try {
            $pdo->exec($ddl);
            $applied[] = $table . '.' . $column;
        } catch (PDOException $e) {
            // Column may have been added concurrently — ignore duplicates.
            if (strpos($e->getMessage(), 'duplicate column') === false) {
                throw $e;
            }
        }
    }

    return ['applied' => $applied, 'skipped' => $skipped];
}

// --- Settings storage (DB, overrides .env) -----------------------------------------
function pvr_setting_get(string $key): ?string
{
    try {
        $stmt = db()->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false && $row['value'] !== '' ? (string)$row['value'] : null;
    } catch (Throwable $e) {
        // Table not created yet.
        return null;
    }
}

function pvr_setting_set(string $key, string $value): void
{
    $stmt = db()->prepare("INSERT INTO settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    $stmt->execute([$key, $value]);
}

/** Editable settings: name, label, secret (masked in UI), control. */
function pvr_settings_list(): array
{
    return [
        ['PAYMENT_PROVIDER', 'Payment provider', false, 'select:lemonsqueezy,getly'],
        ['LEMONSQUEEZY_API_KEY', 'Lemon Squeezy API Key', true, ''],
        ['LEMONSQUEEZY_WEBHOOK_SECRET', 'Lemon Squeezy Webhook Secret', true, ''],
        ['LEMONSQUEEZY_STORE_ID', 'Lemon Squeezy Store ID', false, ''],
        ['LEMONSQUEEZY_VARIANT_ID', 'Lemon Squeezy Variant ID (new license)', false, ''],
        ['LEMONSQUEEZY_RENEWAL_VARIANT_ID', 'Lemon Squeezy Renewal Variant ID (updates renewal)', false, ''],
        ['LEMONSQUEEZY_SUBSCRIPTION_VARIANT_ID', 'Lemon Squeezy Subscription Variant ID (recurring updates)', false, ''],
        ['GETLY_API_KEY', 'Getly API Key', true, ''],
        ['GETLY_WEBHOOK_SECRET', 'Getly Webhook Secret', true, ''],
        ['GETLY_PRODUCT_ID', 'Getly Product ID (new license)', false, ''],
        ['GETLY_RENEWAL_PRODUCT_ID', 'Getly Renewal Product ID (updates renewal)', false, ''],
        ['ADMIN_API_URL', 'License server provision URL', false, ''],
        ['ADMIN_API_KEY', 'License server API key', true, ''],
        ['RESEND_API_KEY', 'Resend API Key', true, ''],
        ['RESEND_FROM_EMAIL', 'Resend From Email', false, ''],
    ];
}

/**
 * Verify the admin password. Source of truth: DB (password_hash) when set,
 * otherwise the bootstrap ADMIN_PANEL_PASSWORD from .env.
 */
function pvr_verify_admin_password(string $password): bool
{
    $stored = pvr_setting_get('admin_password_hash');
    if ($stored !== null) {
        return password_verify($password, $stored);
    }
    $bootstrap = defined('ADMIN_PANEL_PASSWORD') ? ADMIN_PANEL_PASSWORD : '';
    return $bootstrap !== '' && hash_equals($bootstrap, $password);
}

// --- Login brute-force protection -------------------------------------------------
define('LOGIN_MAX_ATTEMPTS', 5);        // failed attempts allowed...
define('LOGIN_WINDOW_MINUTES', 15);     // ...within this time window

function pvr_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Returns true when the IP is allowed to attempt a login
 * (fewer than LOGIN_MAX_ATTEMPTS failures within the window).
 */
function pvr_login_allowed(PDO $pdo, string $ip): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_throttle
            WHERE ip = ? AND success = 0
              AND attempted_at > datetime('now', ?)
        ");
        $stmt->execute([$ip, '-' . LOGIN_WINDOW_MINUTES . ' minutes']);
        return (int)$stmt->fetchColumn() < LOGIN_MAX_ATTEMPTS;
    } catch (Throwable $e) {
        // Throttle table missing — fail open but record nothing.
        return true;
    }
}

/**
 * Record a login attempt and prune old entries.
 */
function pvr_login_record(PDO $pdo, string $ip, bool $success): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO login_throttle (ip, success) VALUES (?, ?)");
        $stmt->execute([$ip, $success ? 1 : 0]);
        $pdo->prepare("DELETE FROM login_throttle WHERE attempted_at < datetime('now', '-1 day')")->execute();
    } catch (Throwable $e) {
        // Throttling must never break login itself.
    }
}

// --- Login -------------------------------------------------------------------------
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'login') {
    pvr_csrf_check();

    $password = (string)($_POST['password'] ?? '');
    $bootstrap = defined('ADMIN_PANEL_PASSWORD') ? ADMIN_PANEL_PASSWORD : '';
    $dbHash = pvr_setting_get('admin_password_hash');

    // Brute-force protection: check the failure counter before touching the password.
    $ip = pvr_client_ip();
    try {
        $throttlePdo = db();
    } catch (Throwable $e) {
        $throttlePdo = null;
    }
    if ($throttlePdo !== null && !pvr_login_allowed($throttlePdo, $ip)) {
        usleep(500000);
        $loginError = 'Too many failed attempts. Try again in ' . LOGIN_WINDOW_MINUTES . ' minutes.';
    } elseif ($dbHash === null && $bootstrap === '') {
        // First run: no password configured anywhere — the submitted password
        // becomes the admin password.
        if (strlen($password) < 8) {
            if ($throttlePdo !== null) pvr_login_record($throttlePdo, $ip, false);
            $loginError = 'Choose a password with at least 8 characters.';
        } else {
            pvr_setting_set('admin_password_hash', password_hash($password, PASSWORD_DEFAULT));
            if ($throttlePdo !== null) pvr_login_record($throttlePdo, $ip, true);
            session_regenerate_id(true);
            $_SESSION['pvr_admin_auth'] = true;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    } elseif (($dbHash !== null && password_verify($password, $dbHash))
        || ($dbHash === null && $bootstrap !== '' && hash_equals($bootstrap, $password))) {
        if ($throttlePdo !== null) pvr_login_record($throttlePdo, $ip, true);
        session_regenerate_id(true);
        $_SESSION['pvr_admin_auth'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        if ($throttlePdo !== null) pvr_login_record($throttlePdo, $ip, false);
        usleep(500000);
        $loginError = 'Wrong password.';
    }
}

// --- Logout -------------------------------------------------------------------------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// --- Handle actions -------------------------------------------------------------
$migrationResult = null;
$migrationError = '';

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'migrate') {
    pvr_csrf_check();
    try {
        $pdo = db();
        $migrationResult = pvr_site_schema_sync($pdo);
    } catch (Exception $e) {
        $migrationError = $e->getMessage();
    }
}

// Save settings (secrets left blank keep their current value).
$settingsSaved = false;
$settingsError = '';
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'save_settings') {
    pvr_csrf_check();
    try {
        foreach (pvr_settings_list() as [$name, , $secret, $control]) {
            $value = isset($_POST[$name]) ? trim((string)$_POST[$name]) : '';
            if ($value === '') {
                continue; // empty = keep current value
            }
            if ($control === 'select:lemonsqueezy,getly') {
                $value = ($value === 'getly') ? 'getly' : 'lemonsqueezy';
            }
            pvr_setting_set($name, $value);
        }
        $settingsSaved = true;
    } catch (Throwable $e) {
        $settingsError = $e->getMessage();
    }
}

// Change admin password (stored hashed in DB).
$passwordResult = null;
$passwordError = '';
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'change_password') {
    pvr_csrf_check();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $hasDbPassword = pvr_setting_get('admin_password_hash') !== null;

    if ($hasDbPassword && !pvr_verify_admin_password($current)) {
        $passwordError = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $passwordError = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $passwordError = 'Passwords do not match.';
    } else {
        pvr_setting_set('admin_password_hash', password_hash($new, PASSWORD_DEFAULT));
        $passwordResult = true;
    }
}

// --- Stats ------------------------------------------------------------------------
$stats = null;
$recentOrders = [];
if ($authenticated) {
    try {
        $pdo = db();
        $stats = [
            'customers' => (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
            'orders' => (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'licenses' => (int)$pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn(),
            'sessions' => (int)$pdo->query("SELECT COUNT(*) FROM checkout_sessions")->fetchColumn(),
        ];
        $recentOrders = $pdo->query("
            SELECT o.ls_order_id, o.status, o.amount, o.currency, o.created_at,
                   c.email, l.domain, l.updates_until, l.status AS license_status
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN licenses l ON l.order_id = o.id
            ORDER BY o.id DESC LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // DB not initialized yet — the migrate button will create everything.
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PVR Site Admin</title>
<meta name="robots" content="noindex, nofollow">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; margin: 0; padding: 40px 16px; color: #1e293b; }
  .wrap { max-width: 860px; margin: 0 auto; }
  h1 { font-size: 22px; }
  h2 { font-size: 16px; margin-top: 0; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
  input[type=password] { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; box-sizing: border-box; }
  .btn { display: inline-block; padding: 11px 20px; border: none; border-radius: 10px; background: #4f46e5; color: #fff; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; }
  .btn:hover { background: #4338ca; }
  .err { color: #dc2626; margin-top: 10px; }
  .ok { color: #15803d; background: #dcfce7; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
  th { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; }
  .stats { display: flex; gap: 16px; flex-wrap: wrap; }
  .stat { flex: 1; min-width: 140px; background: #f8fafc; border-radius: 10px; padding: 16px; text-align: center; }
  .stat-num { font-size: 26px; font-weight: 700; color: #4f46e5; }
  .stat-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
  .muted { color: #94a3b8; }
  .logout { font-size: 13px; color: #64748b; }
</style>
</head>
<body>
<div class="wrap">
  <h1>PVR Site Admin</h1>

<?php if (!$authenticated): ?>
  <div class="card">
    <?php if (!$passwordSet && pvr_setting_get('admin_password_hash') === null): ?>
      <h2>Set up your admin password</h2>
      <p class="muted" style="font-size:13px;">First run: the password you choose here becomes the admin password (stored hashed in the database).</p>
      <?php if ($loginError): ?><p class="err"><?php echo h($loginError); ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="do" value="login">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['pvr_csrf']) ?>">
        <p><input type="password" name="password" placeholder="New admin password (min 8 chars)" autofocus></p>
        <p><button class="btn" type="submit">Create password and log in</button></p>
      </form>
    <?php else: ?>
      <?php if ($loginError): ?><p class="err"><?php echo h($loginError); ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="do" value="login">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['pvr_csrf']) ?>">
        <input type="password" name="password" placeholder="Admin password" autofocus>
        <p><button class="btn" type="submit">Log in</button></p>
      </form>
    <?php endif; ?>
  </div>

<?php else: ?>
  <p class="muted">Signed in. <a class="logout" href="?logout=1">Log out</a></p>

  <div class="card">
    <h2>Database</h2>
    <?php if ($migrationResult !== null): ?>
      <?php if (!empty($migrationResult['applied'])): ?>
        <p class="ok">Applied: <?php echo h(implode(', ', $migrationResult['applied'])); ?></p>
      <?php else: ?>
        <p class="ok">Database structure is already up to date.</p>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($migrationError): ?><p class="err"><?php echo h($migrationError); ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="do" value="migrate">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['pvr_csrf']) ?>">
      <button class="btn" type="submit">Sync Database Structure</button>
    </form>
    <p class="muted" style="font-size:13px;">Creates missing tables and columns (licenses, orders, webhook_events, ls_subscription_id, ...). Idempotent — safe to run anytime, existing data is never touched.</p>
  </div>

  <div class="card">
    <h2>Settings</h2>
    <p class="ok" <?php echo $settingsSaved ? '' : 'hidden'; ?>>Settings saved. Values take effect immediately.</p>
    <?php if ($settingsError): ?><p class="err"><?php echo h($settingsError); ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="do" value="save_settings">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['pvr_csrf']) ?>">
      <table>
        <tr><th style="width:40%;">Setting</th><th>Value</th></tr>
        <?php foreach (pvr_settings_list() as [$name, $label, $secret, $control]): ?>
        <?php
        $current = pvr_setting_get($name);
        $fallback = defined($name) ? constant($name) : '';
        $effective = $current !== null ? $current : $fallback;
        ?>
        <tr>
          <td>
            <strong><?php echo h($label); ?></strong>
            <div class="muted" style="font-size:11px;"><?php echo h($name); ?></div>
          </td>
          <td>
            <?php if ($control === 'select:lemonsqueezy,getly'): ?>
              <select name="<?php echo h($name); ?>" style="width:100%;">
                <option value="lemonsqueezy" <?php echo $effective === 'lemonsqueezy' ? 'selected' : ''; ?>>lemonsqueezy</option>
                <option value="getly" <?php echo $effective === 'getly' ? 'selected' : ''; ?>>getly</option>
              </select>
            <?php else: ?>
              <input type="text" name="<?php echo h($name); ?>"
                     value="<?php echo $secret && $effective !== '' ? '' : h($effective); ?>"
                     placeholder="<?php echo $secret ? ($effective !== '' ? str_repeat('*', 8) : 'not set') : ''; ?>"
                     style="width:100%;box-sizing:border-box;font-size:13px;"
                     <?php echo $secret ? 'autocomplete="off"' : ''; ?>>
              <?php if ($secret && $effective !== ''): ?>
                <div class="muted" style="font-size:11px;margin-top:4px;">Stored. Leave blank to keep the current value.</div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <p><button class="btn" type="submit">Save Settings</button></p>
      <p class="muted" style="font-size:13px;">Values stored here override the .env file. Empty secret fields keep the currently stored value.</p>
    </form>
  </div>

  <div class="card">
    <h2>Admin Password</h2>
    <?php if ($passwordResult): ?>
      <p class="ok">Password changed successfully.</p>
    <?php endif; ?>
    <?php if ($passwordError): ?><p class="err"><?php echo h($passwordError); ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="do" value="change_password">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['pvr_csrf']) ?>">
      <p><input type="password" name="current_password" placeholder="Current password (empty on first setup)"></p>
      <p><input type="password" name="new_password" placeholder="New password (min 8 chars)"></p>
      <p><input type="password" name="confirm_password" placeholder="Repeat new password"></p>
      <p><button class="btn" type="submit">Change Password</button></p>
    </form>
    <p class="muted" style="font-size:13px;">The password is stored hashed in the database and overrides ADMIN_PANEL_PASSWORD from .env.</p>
  </div>

  <?php if ($stats): ?>
  <div class="card">
    <h2>Overview</h2>
    <div class="stats">
      <div class="stat"><div class="stat-num"><?php echo (int)$stats['orders']; ?></div><div class="stat-label">Orders</div></div>
      <div class="stat"><div class="stat-num"><?php echo (int)$stats['licenses']; ?></div><div class="stat-label">Licenses</div></div>
      <div class="stat"><div class="stat-num"><?php echo (int)$stats['customers']; ?></div><div class="stat-label">Customers</div></div>
      <div class="stat"><div class="stat-num"><?php echo (int)$stats['sessions']; ?></div><div class="stat-label">Checkout sessions</div></div>
    </div>
  </div>

  <div class="card">
    <h2>Recent orders</h2>
    <?php if (empty($recentOrders)): ?>
      <p class="muted">No orders yet.</p>
    <?php else: ?>
      <table>
        <tr><th>Order</th><th>Email</th><th>Domain</th><th>Status</th><th>Updates until</th><th>Amount</th><th>Date</th></tr>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><?php echo h((string)$o['ls_order_id']); ?></td>
          <td><?php echo h((string)$o['email']); ?></td>
          <td><?php echo h((string)$o['domain']); ?></td>
          <td><?php echo h((string)$o['status']); ?> / <?php echo h((string)$o['license_status'] ?? '-'); ?></td>
          <td><?php echo h((string)$o['updates_until']); ?></td>
          <td><?php echo h((string)$o['amount']); ?> <?php echo h((string)$o['currency']); ?></td>
          <td><?php echo h((string)$o['created_at']); ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
