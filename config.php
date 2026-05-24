<?php
/**
 * FABulous — config.php
 * =====================
 * CENTRALISED SOURCE OF TRUTH
 *
 * Pattern: config.local.php is loaded FIRST.
 * PHP constants cannot be redefined, so whatever the local file sets "wins."
 * This file only fills in safe, generic defaults for anything not yet defined.
 *
 * Include from any subdirectory with:
 *   require_once __DIR__ . '/../config.php';
 */

// ── 1. Load environment-specific overrides FIRST ─────────────────────────────
$_localConfig = __DIR__ . '/config.local.php';
if (is_file($_localConfig)) {
    require_once $_localConfig;
}
unset($_localConfig);

// ── 1b. Timezone — Philippine Time (PHT / UTC+8) ──────────────────────────────
// Set globally so all date(), DateTime, and strtotime() calls across the
// application produce PHT timestamps. This is the single source of truth for
// time zone; do NOT override it in individual files or repository classes.
date_default_timezone_set('Asia/Manila');

// ── 2. Google OAuth ───────────────────────────────────────────────────────────
defined('GOOGLE_CLIENT_ID')       || define('GOOGLE_CLIENT_ID',       getenv('GOOGLE_CLIENT_ID')       ?: '');
defined('GOOGLE_CLIENT_SECRET')   || define('GOOGLE_CLIENT_SECRET',   getenv('GOOGLE_CLIENT_SECRET')   ?: '');
defined('GOOGLE_REDIRECT_URI')    || define('GOOGLE_REDIRECT_URI',    getenv('GOOGLE_REDIRECT_URI')    ?: 'http://localhost/Fab-ulous/oauth/oauth2callback.php');

// ── 3. App environment ────────────────────────────────────────────────────────
defined('APP_ENV') || define('APP_ENV', getenv('APP_ENV') ?: 'local');
defined('APP_URL') || define('APP_URL', getenv('APP_URL') ?: 'http://localhost/Fab-ulous');

// ── 4. PayMongo ───────────────────────────────────────────────────────────────
defined('PAYMONGO_SECRET_KEY')           || define('PAYMONGO_SECRET_KEY',           getenv('PAYMONGO_SECRET_KEY')           ?: '');
defined('PAYMONGO_WEBHOOK_SECRET')       || define('PAYMONGO_WEBHOOK_SECRET',       getenv('PAYMONGO_WEBHOOK_SECRET')       ?: '');
defined('PAYMONGO_API_BASE')             || define('PAYMONGO_API_BASE',             getenv('PAYMONGO_API_BASE')             ?: 'https://api.paymongo.com/v1');
defined('PAYMONGO_PAYMENT_METHOD_TYPES') || define('PAYMONGO_PAYMENT_METHOD_TYPES', getenv('PAYMONGO_PAYMENT_METHOD_TYPES') ?: 'card,gcash');

// ── 4c. Giphy API ─────────────────────────────────────────────────────────────
// GIPHY_API_KEY → public key used in client-side fetch calls to api.giphy.com.
// For production, override in config.local.php so the key is never hardcoded
// in a committed file.
// Docs: https://developers.giphy.com/docs/api/
defined('GIPHY_API_KEY') || define('GIPHY_API_KEY', getenv('GIPHY_API_KEY') ?: '0yFOakYWQIIlRFSCVs1ztIkmaytA3Gfc');

// ── 4b. Google reCAPTCHA v2 (Checkbox) ───────────────────────────────────────
// RECAPTCHA_SITE_KEY   → public key; rendered in HTML/JS (safe to expose)
// RECAPTCHA_SECRET_KEY → private key; used ONLY for server-side token verification,
//                        never output to the browser
//
// Set both in config.local.php for production. The empty-string defaults here
// prevent fatal "undefined constant" errors on fresh checkouts before config.local.php
// exists. reCAPTCHA is MANDATORY on all manual credential entry points:
//   login/login.php, admin/admin_login.php, register/register.php
// It is NOT applied to the Google OAuth redirect flow.
defined('RECAPTCHA_SITE_KEY')   || define('RECAPTCHA_SITE_KEY',   getenv('RECAPTCHA_SITE_KEY')   ?: '6LeK3OQsAAAAAP9o4mdG55eUWUXb6hgaX5ssLb0C');
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// ── 5. DB_CONFIG ──────────────────────────────────────────────────────────────
// Each domain carries its own host/user/pass/name/port.
// config.local.php overrides this entire constant for production (Hostinger)
// where every database has a distinct MySQL user.
//
// LOCAL defaults use the XAMPP single-root pattern.
// PRODUCTION values are injected wholesale by config.local.php — see that file.
//
// db_connect() always reads $config['user'] and $config['pass'] from this array,
// so swapping environments requires only a config.local.php change — zero code edits.
defined('DB_CONFIG') || define('DB_CONFIG', [
    // domain key            host          user    pass  name                              port
    'accounts'            => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_accounts',             'port' => 3306],
    'posts'               => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_posts',                'port' => 3306],
    'likes'               => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_likes',                'port' => 3306],
    'comments'            => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_comments',             'port' => 3306],
    'commissions'         => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_commissions',          'port' => 3306],
    'commission_payments' => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_commission_payments',  'port' => 3306],
    'friendships'         => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_friendships',          'port' => 3306],
    'notifications'       => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_notifications',        'port' => 3306],
    'messages'            => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_messages',             'port' => 3306],
    'pending_registrations' => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_pending_registrations', 'port' => 3306],
    'password_resets'     => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_password_resets',     'port' => 3306],
    'audit_log'           => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_audit_log',           'port' => 3306],
]);

// ── 6. Connection pool ────────────────────────────────────────────────────────
$GLOBALS['db_connections'] = [];

// ── 7. SMTP / Email ───────────────────────────────────────────────────────────
defined('SMTP_HOST')         || define('SMTP_HOST',         getenv('SMTP_HOST')         ?: 'smtp.gmail.com');
defined('SMTP_PORT')         || define('SMTP_PORT',         (int)(getenv('SMTP_PORT')   ?: 465));
defined('SMTP_ENCRYPTION')   || define('SMTP_ENCRYPTION',   getenv('SMTP_ENCRYPTION')   ?: 'ssl');
defined('SMTP_USERNAME')     || define('SMTP_USERNAME',     getenv('SMTP_USERNAME')     ?: '');
defined('SMTP_PASSWORD')     || define('SMTP_PASSWORD',     getenv('SMTP_PASSWORD')     ?: '');
defined('MAIL_FROM_ADDRESS') || define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: '');
defined('MAIL_FROM_NAME')    || define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME')    ?: 'FABulous');

// ── 8. MFA ────────────────────────────────────────────────────────────────────
defined('MFA_CODE_TTL_MINUTES')        || define('MFA_CODE_TTL_MINUTES',        10);
defined('MFA_RESEND_COOLDOWN_SECONDS') || define('MFA_RESEND_COOLDOWN_SECONDS', 60);

// ── 9. Email Domain Whitelist ─────────────────────────────────────────────────
// Allowed domains for registration and Google OAuth sign-in (see CLAUDE.md §13).
// Override in config.local.php if needed (define BEFORE loading this file).
defined('ALLOWED_EMAIL_DOMAINS') || define('ALLOWED_EMAIL_DOMAINS', [
    'gmail.com',
    'dlsud.edu.ph',
    'outlook.com',
]);

$GLOBALS['FABULOUS_LAST_MAIL_ERROR'] = '';

// ═════════════════════════════════════════════════════════════════════════════
// DATABASE
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Returns a cached MySQLi connection for the given logical database domain.
 *
 * Credentials are read exclusively from DB_CONFIG[$domain], which means:
 *   - Local:      single root user, no password (XAMPP default).
 *   - Production: per-domain Hostinger user injected via config.local.php.
 *
 * No global DB_USER / DB_PASS constants are consulted — the array is the
 * single authority, making environment switching a config-only operation.
 *
 * @param  string $domain  Logical name matching a key in DB_CONFIG
 *                         (e.g. 'accounts', 'posts', 'audit_log').
 * @return mysqli          Active, UTF-8 configured connection.
 * @throws RuntimeException If the domain is unknown or the connection fails.
 */
function db_connect(string $domain): mysqli
{
    // Return cached connection if still alive
    if (
        isset($GLOBALS['db_connections'][$domain])
        && $GLOBALS['db_connections'][$domain] instanceof mysqli
        && $GLOBALS['db_connections'][$domain]->ping()
    ) {
        return $GLOBALS['db_connections'][$domain];
    }

    // Resolve per-domain config — intentionally no global DB_USER fallback
    $config = DB_CONFIG[$domain] ?? null;
    if ($config === null) {
        http_response_code(500);
        throw new RuntimeException("No DB_CONFIG entry for domain '{$domain}'.");
    }

    $conn = new mysqli(
        $config['host'],
        $config['user'],   // ← per-domain user, not global DB_USER
        $config['pass'],   // ← per-domain password, not global DB_PASS
        $config['name'],
        $config['port']
    );

    if ($conn->connect_error) {
        http_response_code(500);
        throw new RuntimeException(
            "DB connection failed for domain '{$domain}': " . $conn->connect_error
        );
    }

    $conn->set_charset('utf8mb4');
    $GLOBALS['db_connections'][$domain] = $conn;
    return $conn;
}

// Close all pooled connections on script shutdown
register_shutdown_function(static function (): void {
    foreach ($GLOBALS['db_connections'] as $conn) {
        if ($conn instanceof mysqli && !$conn->connect_error) {
            $conn->close();
        }
    }
});

// ═════════════════════════════════════════════════════════════════════════════
// AUTH / SESSION HELPERS
// ═════════════════════════════════════════════════════════════════════════════

function dashboard_path_for_role(string $role): string
{
    return in_array($role, ['admin', 'super_admin'], true)
        ? '../admin/admin.php'
        : '../post/post.php';
}

function login_lockout_remaining(string $bucket): int
{
    $key = $bucket . '_lockout_until';
    if (!isset($_SESSION[$key])) {
        return 0;
    }
    $until = (int) $_SESSION[$key];
    if ($until <= time()) {
        unset($_SESSION[$key], $_SESSION[$bucket . '_attempts']);
        return 0;
    }
    return $until - time();
}

function record_login_failure(string $bucket, int $maxAttempts = 5, int $lockoutSeconds = 60): int
{
    $attemptKey = $bucket . '_attempts';
    $_SESSION[$attemptKey] = (int)($_SESSION[$attemptKey] ?? 0) + 1;
    if ($_SESSION[$attemptKey] >= $maxAttempts) {
        $_SESSION[$bucket . '_lockout_until'] = time() + $lockoutSeconds;
        unset($_SESSION[$attemptKey]);
        return $lockoutSeconds;
    }
    return 0;
}

function clear_login_lockout(string $bucket): void
{
    unset($_SESSION[$bucket . '_attempts'], $_SESSION[$bucket . '_lockout_until']);
}

function begin_user_session(array $user, bool $mfaVerified = true, string $authMethod = 'password'): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'          => (int) $user['id'],
        'username'    => $user['username'],
        'email'       => $user['email'],
        'name'        => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'role'        => $user['role'] ?? 'user',
        'google_id'   => $user['google_id'] ?? null,
        'profile_pic' => $user['profile_pic'] ?? null,
        'auth_method' => $authMethod,
    ];
    $_SESSION['mfa_verified'] = $mfaVerified;
    unset($_SESSION['pending_mfa_user'], $_SESSION['pending_mfa_sent_at']);
}

function clear_pending_auth(): void
{
    unset($_SESSION['pending_mfa_user'], $_SESSION['pending_mfa_sent_at']);
}

function get_current_user_avatar(): ?string
{
    if (empty($_SESSION['user']['id'])) {
        return null;
    }
    $pic = $_SESSION['user']['profile_pic'] ?? null;
    if ($pic === null && empty($_SESSION['user']['profile_pic_synced'])) {
        $conn     = db_connect('accounts');
        $colCheck = $conn->query("SHOW COLUMNS FROM accounts LIKE 'profile_pic'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $stmt = $conn->prepare('SELECT profile_pic FROM accounts WHERE id = ?');
            if ($stmt) {
                $uid = (int) $_SESSION['user']['id'];
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row && !empty($row['profile_pic'])) {
                    $pic = $row['profile_pic'];
                    $_SESSION['user']['profile_pic'] = $pic;
                }
                $stmt->close();
            }
        }
        $_SESSION['user']['profile_pic_synced'] = true;
    }
    if ($pic) {
        $avatarPath = __DIR__ . '/uploads/profile_pics/' . $pic;
        $v = file_exists($avatarPath) ? filemtime($avatarPath) : time();
        return '../uploads/profile_pics/' . rawurlencode($pic) . '?v=' . $v;
    }
    return null;
}

// ═════════════════════════════════════════════════════════════════════════════
// NOTIFICATION HELPERS
// ═════════════════════════════════════════════════════════════════════════════

function notification_type_supported(string $type): bool
{
    static $supportedTypes = null;
    if ($supportedTypes === null) {
        $supportedTypes = [];
        $conn       = db_connect('notifications');
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return false;
        }
        $columnCheck    = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
        $column         = $columnCheck ? $columnCheck->fetch_assoc() : null;
        $typeDefinition = $column['Type'] ?? '';
        if (preg_match_all("/'([^']+)'/", $typeDefinition, $matches)) {
            $supportedTypes = array_fill_keys($matches[1], true);
        }
    }
    return isset($supportedTypes[$type]);
}

function create_notification(
    int $recipientId,
    int $actorId,
    string $type,
    ?int $postId = null,
    ?int $refId  = null
): bool {
    if ($recipientId <= 0 || $actorId <= 0 || !notification_type_supported($type)) {
        return false;
    }
    $conn = db_connect('notifications');
    $stmt = $conn->prepare(
        'INSERT INTO notifications (userID, actor_id, type, post_id, ref_id, is_read)
         VALUES (?, ?, ?, ?, ?, 0)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iisii', $recipientId, $actorId, $type, $postId, $refId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ═════════════════════════════════════════════════════════════════════════════
// GOOGLE OAUTH REGISTRATION PREFILL
// ═════════════════════════════════════════════════════════════════════════════

function prime_google_registration_prefill(string $email, string $fullName, string $googleId): void
{
    $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];
    $_SESSION['google_registration_prefill'] = [
        'email'      => strtolower(trim($email)),
        'full_name'  => trim($fullName),
        'first_name' => $parts[0] ?? '',
        'last_name'  => $parts[1] ?? '',
        'google_id'  => $googleId,
    ];
}

function get_google_registration_prefill(): array
{
    return $_SESSION['google_registration_prefill'] ?? [];
}

function clear_google_registration_prefill(): void
{
    unset($_SESSION['google_registration_prefill']);
}

// ═════════════════════════════════════════════════════════════════════════════
// MFA HELPERS
// ═════════════════════════════════════════════════════════════════════════════

function accounts_support_mfa(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $conn    = db_connect('accounts');
    $required = ['mfa_code' => false, 'mfa_code_expires_at' => false];
    $columns = $conn->query('SHOW COLUMNS FROM accounts');
    if (!$columns) {
        return $cached = false;
    }
    while ($col = $columns->fetch_assoc()) {
        if (array_key_exists($col['Field'], $required)) {
            $required[$col['Field']] = true;
        }
    }
    return $cached = !in_array(false, $required, true);
}

function store_mfa_code(int $userId, string $code): bool
{
    $conn = db_connect('accounts');
    // mfa_code_expires_at is a DATETIME column. We compute the expiry string
    // in PHP using date(), which respects the Asia/Manila timezone set globally
    // in config.php. This avoids relying on the MySQL server clock (DATE_ADD /
    // NOW()) which may be out of sync with PHP on Hostinger shared hosting.
    $expiresAt = date('Y-m-d H:i:s', time() + (MFA_CODE_TTL_MINUTES * 60));
    $stmt = $conn->prepare(
        'UPDATE accounts
         SET mfa_code = ?, mfa_code_expires_at = ?
         WHERE id = ?'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssi', $code, $expiresAt, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function clear_mfa_code(int $userId): void
{
    $conn = db_connect('accounts');
    $stmt = $conn->prepare(
        'UPDATE accounts SET mfa_code = NULL, mfa_code_expires_at = NULL WHERE id = ?'
    );
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// UTILITY HELPERS
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Verifies a Google reCAPTCHA v2 token against the siteverify API.
 *
 * Must be called server-side before processing any manual login or registration
 * form submission. Returns false if the token is empty, if RECAPTCHA_SECRET_KEY
 * is not yet configured, or if Google's API returns success:false.
 *
 * Uses file_get_contents() with a stream context — no cURL dependency required,
 * which keeps this compatible with Hostinger's shared PHP environment.
 *
 * @param  string $token  Value of $_POST['g-recaptcha-response'] from the form.
 * @return bool           true only when Google confirms success:true.
 */
function verify_recaptcha(string $token): bool
{
    if ($token === '' || RECAPTCHA_SECRET_KEY === '') {
        return false;
    }

    $payload = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);

    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($raw === false) {
        return false;
    }

    $result = json_decode($raw, true);
    return isset($result['success']) && $result['success'] === true;
}

/**
 * Returns true if the email's domain is in the ALLOWED_EMAIL_DOMAINS whitelist.
 * Case-insensitive. Called by register.php and oauth2callback.php.
 *
 * @param  string $email  Full email address (e.g. user@gmail.com)
 * @return bool
 */
function is_email_domain_allowed(string $email): bool
{
    $parts = explode('@', strtolower(trim($email)), 2);
    if (count($parts) !== 2 || $parts[1] === '') {
        return false;
    }
    return in_array($parts[1], array_map('strtolower', ALLOWED_EMAIL_DOMAINS), true);
}

function mask_email_address(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }
    $local       = $parts[0];
    $visible     = substr($local, 0, 2);
    $hiddenLen   = max(2, strlen($local) - 2);
    return $visible . str_repeat('*', $hiddenLen) . '@' . $parts[1];
}

function set_last_mail_error(string $message): void  { $GLOBALS['FABULOUS_LAST_MAIL_ERROR'] = $message; }
function get_last_mail_error(): string               { return (string)($GLOBALS['FABULOUS_LAST_MAIL_ERROR'] ?? ''); }

function smtp_is_configured(): bool
{
    return SMTP_HOST !== '' && SMTP_PORT > 0
        && SMTP_USERNAME !== '' && SMTP_PASSWORD !== ''
        && MAIL_FROM_ADDRESS !== '';
}

// ═════════════════════════════════════════════════════════════════════════════
// SMTP ENGINE
// ═════════════════════════════════════════════════════════════════════════════

function smtp_read_response($socket): array
{
    $message = '';
    $code    = 0;
    while (($line = fgets($socket, 515)) !== false) {
        $message .= $line;
        if (preg_match('/^(\d{3})([\s-])/', $line, $m)) {
            $code = (int) $m[1];
            if ($m[2] === ' ') break;
        } else {
            break;
        }
    }
    return [$code, trim($message)];
}

function smtp_expect($socket, array $allowedCodes): bool
{
    [$code, $message] = smtp_read_response($socket);
    if (!in_array($code, $allowedCodes, true)) {
        set_last_mail_error($message !== '' ? $message : 'Unexpected SMTP response.');
        return false;
    }
    return true;
}

function smtp_write($socket, string $cmd): bool
{
    if (fwrite($socket, $cmd . "\r\n") === false) {
        set_last_mail_error('Could not write to SMTP server.');
        return false;
    }
    return true;
}

function smtp_format_header(string $label, string $value): string { return $label . ': ' . $value; }
function smtp_encode_header(string $value): string                { return '=?UTF-8?B?' . base64_encode($value) . '?='; }

function smtp_normalize_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n.", "\n..", $body);
    return str_replace("\n", "\r\n", $body);
}

function send_smtp_mail(string $toEmail, string $toName, string $subject, string $body): bool
{
    if (!smtp_is_configured()) {
        set_last_mail_error('SMTP is not configured. Add SMTP settings to config.local.php.');
        return false;
    }
    set_last_mail_error('');

    $scheme  = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '';
    $context = stream_context_create(['ssl' => [
        'verify_peer'      => APP_ENV !== 'local',
        'verify_peer_name' => APP_ENV !== 'local',
        'allow_self_signed'=> APP_ENV === 'local',
    ]]);

    $socket = @stream_socket_client(
        $scheme . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context
    );
    if (!$socket) {
        set_last_mail_error('SMTP connection failed: ' . $errstr);
        return false;
    }
    stream_set_timeout($socket, 20);

    if (!smtp_expect($socket, [220])) { fclose($socket); return false; }
    if (!smtp_write($socket, 'EHLO localhost') || !smtp_expect($socket, [250])) { fclose($socket); return false; }

    if (SMTP_ENCRYPTION === 'tls') {
        if (!smtp_write($socket, 'STARTTLS') || !smtp_expect($socket, [220])) { fclose($socket); return false; }
        if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            set_last_mail_error('Could not enable TLS.');
            fclose($socket); return false;
        }
        if (!smtp_write($socket, 'EHLO localhost') || !smtp_expect($socket, [250])) { fclose($socket); return false; }
    }

    if (!smtp_write($socket, 'AUTH LOGIN')                     || !smtp_expect($socket, [334])) { fclose($socket); return false; }
    if (!smtp_write($socket, base64_encode(SMTP_USERNAME))     || !smtp_expect($socket, [334])) { fclose($socket); return false; }
    if (!smtp_write($socket, base64_encode(SMTP_PASSWORD))     || !smtp_expect($socket, [235])) { fclose($socket); return false; }
    if (!smtp_write($socket, 'MAIL FROM:<' . MAIL_FROM_ADDRESS . '>') || !smtp_expect($socket, [250])) { fclose($socket); return false; }
    if (!smtp_write($socket, 'RCPT TO:<' . $toEmail . '>')     || !smtp_expect($socket, [250, 251])) { fclose($socket); return false; }
    if (!smtp_write($socket, 'DATA')                           || !smtp_expect($socket, [354])) { fclose($socket); return false; }

    $headers = [
        smtp_format_header('Date',                     date(DATE_RFC2822)),
        smtp_format_header('From',                     smtp_encode_header(MAIL_FROM_NAME) . ' <' . MAIL_FROM_ADDRESS . '>'),
        smtp_format_header('To',                       ($toName !== '' ? smtp_encode_header($toName) . ' ' : '') . '<' . $toEmail . '>'),
        smtp_format_header('Subject',                  smtp_encode_header($subject)),
        smtp_format_header('MIME-Version',             '1.0'),
        smtp_format_header('Content-Type',             'text/plain; charset=UTF-8'),
        smtp_format_header('Content-Transfer-Encoding','8bit'),
    ];

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . smtp_normalize_body($body) . "\r\n.";
    if (!smtp_write($socket, $payload) || !smtp_expect($socket, [250])) { fclose($socket); return false; }

    smtp_write($socket, 'QUIT');
    fclose($socket);
    return true;
}

// ═════════════════════════════════════════════════════════════════════════════
// MAIL TEMPLATES
// ═════════════════════════════════════════════════════════════════════════════

function send_mfa_code_email(string $email, string $displayName, string $code): bool
{
    return send_smtp_mail($email, $displayName, 'Your FABulous verification code',
        "Hello {$displayName},\n\n"
        . "Your FABulous verification code is: {$code}\n\n"
        . 'This code expires in ' . MFA_CODE_TTL_MINUTES . " minutes.\n"
        . 'Sign-in page: ' . APP_URL . "/login/verify_mfa.php\n\n"
        . "If you did not request this login, you can ignore this email.\n\n"
        . 'FABulous Security'
    );
}

function send_password_reset_email(string $email, string $displayName, string $code): bool
{
    return send_smtp_mail($email, $displayName, 'Reset your FABulous password',
        "Hello {$displayName},\n\n"
        . "Your FABulous password reset code is: {$code}\n\n"
        . "This code expires in 30 minutes.\n"
        . 'Reset page: ' . APP_URL . "/login/reset_password.php\n\n"
        . "If you did not request a password reset, you can ignore this email.\n\n"
        . 'FABulous Security'
    );
}

function send_registration_verification_email(string $email, string $displayName, string $code): bool
{
    return send_smtp_mail($email, $displayName, 'Verify your FABulous account',
        "Hello {$displayName},\n\n"
        . "Your FABulous account verification code is: {$code}\n\n"
        . "This code expires in 60 minutes.\n"
        . 'Verification page: ' . APP_URL . "/register/verify_registration.php\n\n"
        . "If you did not request this, you can safely ignore this email.\n\n"
        . 'FABulous Team'
    );
}

function send_account_deletion_email(string $email, string $displayName, string $reason): bool
{
    return send_smtp_mail($email, $displayName, 'Your FABulous Account Has Been Deleted',
        "Hello {$displayName},\n\n"
        . "We are writing to inform you that your FABulous account has been permanently deleted.\n\n"
        . "Reason for deletion:\n"
        . "─────────────────────────────────────\n"
        . $reason . "\n"
        . "─────────────────────────────────────\n\n"
        . "All associated data including posts, messages, and profile information have been removed.\n\n"
        . "If you believe this was done in error, please contact our support team.\n\n"
        . 'FABulous Moderation Team'
    );
}

function send_post_removal_email(
    string $email, string $displayName, int $postId,
    string $captionPreview, string $reason
): bool {
    $preview = $captionPreview !== ''
        ? "Post caption preview:\n\"" . mb_substr($captionPreview, 0, 200)
          . (mb_strlen($captionPreview) > 200 ? '…' : '') . "\"\n\n"
        : '';
    return send_smtp_mail($email, $displayName, 'Your FABulous Post Has Been Removed',
        "Hello {$displayName},\n\n"
        . "Your post (Post ID: #{$postId}) has been removed by a FABulous administrator.\n\n"
        . $preview
        . "Reason for removal:\n"
        . "─────────────────────────────────────\n"
        . $reason . "\n"
        . "─────────────────────────────────────\n\n"
        . "If you believe this was done in error, please contact our support team.\n\n"
        . 'FABulous Moderation Team'
    );
}

function send_commission_deletion_email(
    string $email,
    string $displayName,
    string $commissionName,
    string $reason
): bool {
    $nameTag = $commissionName !== '' ? " \"{$commissionName}\"" : '';
    return send_smtp_mail($email, $displayName, 'Your FABulous Commission Request Has Been Deleted',
        "Hello {$displayName},\n\n"
        . "We are writing to inform you that your commission request{$nameTag} has been permanently deleted by a FABulous administrator.\n\n"
        . "Reason for deletion:\n"
        . "─────────────────────────────────────\n"
        . $reason . "\n"
        . "─────────────────────────────────────\n\n"
        . "If you believe this was done in error or have any questions, please contact our support team.\n\n"
        . 'FABulous Moderation Team'
    );
}