<?php
session_start();
require_once __DIR__ . '/../config.php';

// ── AUTH GUARD ─────────────────────────────────────────────────────────────
// Any fully-authenticated user (admin or regular) who already has an active,
// MFA-verified session should never land on a login form.
// Admins   → their admin dashboard
// Users    → post.php (their normal dashboard)
if (!empty($_SESSION['user']) && !empty($_SESSION['mfa_verified'])) {
    header('Location: ' . dashboard_path_for_role($_SESSION['user']['role'] ?? 'user'));
    exit;
}
// ───────────────────────────────────────────────────────────────────────────

$lockoutBucket    = 'fab_global_login';
$lockoutRemaining = login_lockout_remaining($lockoutBucket);

// ── SERVER-SIDE LOCKOUT GUARD ───────────────────────────────────────────────
// Reject any POST immediately if the lockout window is still active.
// This stops bypasses via DevTools "remove disabled attribute" tricks.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lockoutRemaining > 0) {
    goto render_page;
}
// ───────────────────────────────────────────────────────────────────────────

$connAccounts = db_connect('accounts');
$error        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input    = trim($_POST['username']);
    $password = $_POST['password'];

    // ── reCAPTCHA v2 verification ─────────────────────────────────────────
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    if (!verify_recaptcha($recaptchaToken)) {
        // reCAPTCHA failure counts as a failed attempt → trigger lockout.
        $lockoutRemaining = record_login_failure($lockoutBucket, 5, 60);
        $error = '';   // lockout message replaces the inline error
    } else {
        $stmt = $connAccounts->prepare(
            "SELECT * FROM accounts WHERE (username = ? OR email = ?) AND role IN ('admin', 'super_admin')"
        );
        $stmt->bind_param("ss", $input, $input);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['banned']) {
                $error = 'This admin account has been suspended.';
            } else {
                if (!accounts_support_mfa()) {
                    $error = 'MFA is not ready yet. Run the SQL update for the accounts table first.';
                } else {
                    $code = (string) random_int(100000, 999999);

                    if (!store_mfa_code((int) $user['id'], $code)) {
                        $error = 'We could not start MFA verification. Please try again.';
                    } else {
                        clear_login_lockout($lockoutBucket);
                        clear_pending_auth();
                        $_SESSION['pending_mfa_user'] = [
                            'id'          => (int) $user['id'],
                            'username'    => $user['username'],
                            'email'       => $user['email'],
                            'first_name'  => $user['first_name'],
                            'last_name'   => $user['last_name'],
                            'role'        => $user['role'],
                            'google_id'   => $user['google_id'] ?? null,
                            'profile_pic' => $user['profile_pic'] ?? null,
                        ];
                        $_SESSION['pending_mfa_sent_at'] = time();

                        $mailSent = send_mfa_code_email(
                            $user['email'],
                            trim($user['first_name'] . ' ' . $user['last_name']),
                            $code
                        );

                        if (!$mailSent) {
                            clear_pending_auth();
                            clear_mfa_code((int) $user['id']);
                            $error = get_last_mail_error() ?: 'A verification code could not be sent to this admin email address.';
                        } else {
                            header('Location: ../login/verify_mfa.php');
                            exit;
                        }
                    }
                }
            }
        } else {
            // Wrong credentials → immediate 1-attempt lockout.
            $lockoutRemaining = record_login_failure($lockoutBucket, 5, 60);
            if ($lockoutRemaining <= 0) {
                $error = 'Invalid credentials or not an admin account.';
            }
        }
    }
}

render_page:
$lockoutRemaining  = login_lockout_remaining($lockoutBucket);
$isLocked          = $lockoutRemaining > 0;
$dis               = $isLocked ? ' disabled' : '';
$maxAttempts       = 5;
$attemptsUsed      = (int) ($_SESSION['fab_global_login_attempts'] ?? 0);
$attemptsLeft      = max(0, $maxAttempts - $attemptsUsed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FABulous – Admin Login</title>
  <link rel="icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link rel="shortcut icon" type="image/png" href="../images/Top_Left_Nav_Logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="admin_login.css"/>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style>
    /* ── Lockout visual styles ─────────────────────────────────────────── */
    input:disabled,
    button:disabled,
    input[disabled],
    button[disabled] {
      opacity: 0.55 !important;
      cursor: not-allowed !important;
      background-color: #e9ecef !important;
      color: #6c757d !important;
    }
    .recaptcha-lockout-wrap {
      pointer-events: none;
      opacity: 0.5;
      user-select: none;
    }
    .lockout-countdown {
      font-weight: 600;
      color: #dc3545;
    }
    .attempts-warning {
      color: #b45309;
    }
  </style>
</head>
<body>

  <nav class="topnav">
    <img src="../images/Top_Left_Nav_Logo.png" alt="FABulous Logo" class="nav-logo"/>
  </nav>

  <div class="page-controls">
    <a href="../login/login.php" class="ctrl-btn return-btn">&#8592; User Login</a>
  </div>

  <div class="auth-viewport">
    <div class="auth-slider" id="authSlider">
      <!-- Pos 0: Login -->
      <div class="auth-slider-step"></div>
      <!-- Pos 1: Admin -->
      <div class="auth-slider-step">
        <main class="card-container">
          <div class="left-panel">
            <img src="../images/Big Logo.png" alt="FABulous Logo" class="brand-logo-img" />
            <h1 class="brand-heading">Admin Access<br/>FABulous</h1>
            <p class="brand-desc">Restricted to authorized Fab Lab administrators only.</p>
            <div class="carousel-dots">
              <span class="dot"></span><span class="dot active"></span><span class="dot"></span>
            </div>
          </div>

          <div class="right-panel">
            <span class="admin-badge">ADMIN PORTAL</span>
            <h2 class="panel-title">Admin Login</h2>

            <?php if (!empty($error) && !$isLocked): ?>
              <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <?php if (!$isLocked && $attemptsUsed > 0): ?>
              <p class="error-msg attempts-warning">
                Failed attempt <?php echo $attemptsUsed; ?> of <?php echo $maxAttempts; ?>.
                <?php echo $attemptsLeft; ?> attempt<?php echo $attemptsLeft !== 1 ? 's' : ''; ?> remaining before a 1-minute lockout.
              </p>
            <?php endif; ?>

            <div id="lockoutMsg"
                 data-remaining="<?php echo (int) $lockoutRemaining; ?>"
                 class="error-msg lockout-countdown"
                 style="<?php echo $isLocked ? '' : 'display:none;'; ?>">
              <?php if ($isLocked): ?>
                Login disabled. Please wait <span id="lockoutSecs"><?php echo (int) $lockoutRemaining; ?></span>s&hellip;
              <?php endif; ?>
            </div>

            <form method="POST" action="" class="auth-form" id="loginForm">
              <div class="input-group">
                <input type="text" name="username" class="input-field"
                       placeholder="Admin Username or Email" autocomplete="username" required<?php echo $dis; ?>/>
              </div>
              <div class="input-group">
                <input type="password" name="password" id="password" class="input-field"
                       placeholder="Password" autocomplete="current-password" required<?php echo $dis; ?>/>
              </div>
              <div class="show-password-row">
                <label class="checkbox-label">
                  <input type="checkbox" id="showPass" onchange="togglePassword()"<?php echo $dis; ?>/>
                  <span class="custom-checkbox"></span>
                  Show Password
                </label>
              </div>
              <?php if ($isLocked): ?>
                <div class="recaptcha-lockout-wrap">
                  <div class="g-recaptcha mb-3" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                </div>
              <?php else: ?>
                <div class="g-recaptcha mb-3" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
              <?php endif; ?>
              <button type="submit" class="btn-primary"<?php echo $dis; ?>>Sign In as Admin</button>
            </form>
          </div>
        </main>
      </div>
      <!-- Pos 2: Register -->
      <div class="auth-slider-step"></div>
    </div>
  </div>

  <script>
    function togglePassword() {
      document.getElementById('password').type =
        document.getElementById('showPass').checked ? 'text' : 'password';
    }

    (function () {
      const lockoutMsg = document.getElementById('lockoutMsg');
      const secsSpan   = document.getElementById('lockoutSecs');
      const form       = document.getElementById('loginForm');
      let remaining    = parseInt(lockoutMsg?.dataset.remaining || '0', 10);

      if (!remaining || !lockoutMsg) return;

      // Ensure every form element is disabled (belt-and-suspenders over PHP).
      if (form) {
        form.querySelectorAll('input, button, select, textarea').forEach(el => {
          el.disabled = true;
          el.setAttribute('tabindex', '-1');
        });
      }

      // Countdown ticker — updates the <span> inside the message.
      const timer = window.setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
          window.clearInterval(timer);
          window.location.reload();
          return;
        }
        if (secsSpan) secsSpan.textContent = remaining;
      }, 1000);
    })();

    // Auth slider animation + bfcache fix — see login/auth_slider.js
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../login/auth_slider.js"></script>
  <script>AuthSlider.init({ page: 'admin' });</script>
</body>
</html>