<?php
session_start();
require_once __DIR__ . '/../config.php';

// ── AUDIT LOG: User Logout ─────────────────────────────────────────────────
// Record the logout event BEFORE destroying the session, while we still have
// the user identity.  We only log when a real authenticated session exists.
if (!empty($_SESSION['user']) && !empty($_SESSION['mfa_verified'])) {
    $userId   = (int) ($_SESSION['user']['id']       ?? 0);
    $username = (string) ($_SESSION['user']['username'] ?? '');
    $userRole = (string) ($_SESSION['user']['role']     ?? 'user');

    if ($userId > 0 && $username !== '') {
        // Role-aware visibility: super_admin logout entries visible only to
        // super admins; everyone else's entries visible to all admins.
        $logoutVis    = ($userRole === 'super_admin') ? 'super_admin' : 'admin';
        $logoutPhtNow = date('Y-m-d H:i:s');
        try {
            $connAudit = db_connect('audit_log');
            $stmt = $connAudit->prepare(
                "INSERT INTO audit_log
                    (admin_id, admin_username, action, target_type, target_id, visibility_role, created_at)
                 VALUES
                    (?, ?, 'User Logout', 'account', ?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param('isiss', $userId, $username, $userId, $logoutVis, $logoutPhtNow);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Audit failure must never block logout
            error_log('FABulous audit logout error: ' . $e->getMessage());
        }
    }
}
// ───────────────────────────────────────────────────────────────────────────

session_destroy();
header('Location: ../login/login.php');
exit;