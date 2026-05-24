<?php
session_start();
require_once __DIR__ . '/../config.php';

$connAccounts = db_connect('accounts');

if (trim(GOOGLE_CLIENT_SECRET) === '') {
    header('Location: ../login/login.php?error=google_oauth_config');
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: ../login/login.php?error=oauth_exchange_failed');
    exit;
}

// ── Stale-session guard ───────────────────────────────────────────────────────
// The browser's Back-Forward Cache (bfcache) can replay a previous admin page
// that still holds a partial or mismatched $_SESSION from an earlier flow.
// Unset any in-progress auth state before touching the session so that a
// Back-button event during OAuth cannot carry stale 'user' data into the new
// login, which would cause admin_id = 0 entries in the audit log.
unset(
    $_SESSION['user'],
    $_SESSION['mfa_verified'],
    $_SESSION['pending_mfa_user'],
    $_SESSION['pending_mfa_sent_at'],
    $_SESSION['oauth_state']
);
// ─────────────────────────────────────────────────────────────────────────────

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://oauth2.googleapis.com/token',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$tokenResponse = curl_exec($ch);
$tokenError = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

if ($tokenError) {
    header('Location: ../login/login.php?error=oauth_exchange_failed');
    exit;
}

$tokenData = json_decode((string) $tokenResponse, true);
if (empty($tokenData['access_token'])) {
    header('Location: ../login/login.php?error=oauth_exchange_failed');
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenData['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$userInfoRaw = curl_exec($ch);
$userInfoError = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

if ($userInfoError) {
    header('Location: ../login/login.php?error=oauth_exchange_failed');
    exit;
}

$gUser = json_decode((string) $userInfoRaw, true);
if (
    empty($gUser['id'])
    || empty($gUser['email'])
    || (isset($gUser['verified_email']) && !$gUser['verified_email'])
) {
    header('Location: ../login/login.php?error=oauth_exchange_failed');
    exit;
}

$googleId = $gUser['id'];
$fullName = trim($gUser['name'] ?? 'Google User');
$email = strtolower(trim($gUser['email']));
$nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
$firstName = $nameParts[0] ?? 'Google';
$lastName = $nameParts[1] ?? 'User';

// Validate email domain against whitelist
if (!is_email_domain_allowed($email)) {
    header('Location: ../login/login.php?error=oauth_unsupported_domain');
    exit;
}

$check = $connAccounts->prepare('SELECT * FROM accounts WHERE google_id = ? OR email = ? LIMIT 1');
$check->bind_param('ss', $googleId, $email);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if (!$existing) {
    // No FABulous account for this Google email yet.
    if (($_SESSION['oauth_intent'] ?? '') === 'login') {
        // Login flow: reject — user must register first.
        header('Location: ../login/login.php?error=google_account_missing');
        exit;
    }
    // Register flow: prefill the form and redirect to registration.
    prime_google_registration_prefill($email, $fullName, $googleId);
    header('Location: ../register/register.html');
    exit;
}

if (!empty($existing['banned'])) {
    header('Location: ../login/login.php?error=banned');
    exit;
}

if (empty($existing['google_id'])) { // Link Google ID if not already linked
    $link = $connAccounts->prepare('UPDATE accounts SET google_id = ? WHERE id = ?');
    $link->bind_param('si', $googleId, $existing['id']);
    $link->execute();
    $link->close();
    $existing['google_id'] = $googleId;
}

clear_google_registration_prefill();

// Validate the account row has a usable ID before creating the session.
// If the DB row is somehow malformed, abort rather than store id = 0.
$resolvedId = (int)($existing['id'] ?? 0);
if ($resolvedId <= 0) {
    error_log('FABulous oauth2callback: resolved account ID is 0 or missing for email ' . $email);
    header('Location: ../login/login.php?error=oauth_exchange_failed');
    exit;
}

begin_user_session([
    'id' => $resolvedId,
    'username' => $existing['username'],
    'email' => $existing['email'],
    'first_name' => $existing['first_name'] ?? $firstName,
    'last_name' => $existing['last_name'] ?? $lastName,
    'role' => $existing['role'] ?? 'user',
    'google_id' => $existing['google_id'] ?? $googleId,
], true, 'google');

// ── Verify session was committed correctly before writing the audit log ───────
// begin_user_session() calls session_regenerate_id(true) which rewrites the
// session file. Confirm the ID and username are actually present so the audit
// entry is never written with admin_id = 0.
$sessionUserId   = (int)($_SESSION['user']['id'] ?? 0);
$sessionUsername = (string)($_SESSION['user']['username'] ?? '');

if ($sessionUserId <= 0 || $sessionUsername === '') {
    // Session did not commit correctly — log and continue to dashboard anyway;
    // do not write a corrupt audit entry.
    error_log('FABulous oauth2callback: session missing id/username after begin_user_session for email ' . $email);
    header('Location: ' . dashboard_path_for_role($existing['role'] ?? 'user'));
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

// ── AUDIT LOG: User Login via Google OAuth ────────────────────────────────
// Use an explicit PHP-computed PHT timestamp so the row is never stamped by
// MySQL's UTC clock. visibility_role reflects the user's actual role.
$oauthLoginRole   = $existing['role'] ?? 'user';
$oauthLoginVis    = ($oauthLoginRole === 'super_admin') ? 'super_admin' : 'admin';
$oauthLoginPhtNow = date('Y-m-d H:i:s');
try {
    $connAudit = db_connect('audit_log');
    $logStmt = $connAudit->prepare(
        "INSERT INTO audit_log
            (admin_id, admin_username, action, target_type, target_id, visibility_role, created_at)
         VALUES
            (?, ?, 'User Login via Google OAuth', 'account', ?, ?, ?)"
    );
    if ($logStmt) {
        $logStmt->bind_param('isiss', $sessionUserId, $sessionUsername, $sessionUserId, $oauthLoginVis, $oauthLoginPhtNow);
        $logStmt->execute();
        $logStmt->close();
    }
} catch (Throwable $e) {
    // Audit failure must never block login
    error_log('FABulous audit google-login error: ' . $e->getMessage());
}
// ─────────────────────────────────────────────────────────────────────────────

header('Location: ' . dashboard_path_for_role($existing['role'] ?? 'user'));
exit;
