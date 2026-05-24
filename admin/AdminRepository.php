<?php

// AdminRepository.php
// This class is responsible for all database interactions related to admin functionalities,
// especially user management (ban, unban, delete) and audit logging.
// It uses the multi-database connection logic from config.php.
//
// Cross-database rule (CLAUDE.md §1 — Hostinger production):
//   getAllPosts() and searchAuditLogs() previously used fully-qualified
//   `db_name`.table JOIN syntax (Pattern A).  On Hostinger each database has
//   a dedicated MySQL user with no cross-database privileges, so those queries
//   returned zero rows or threw a fatal error — causing HTTP 500 on admin.php.
//
//   Both methods have been rewritten to Application-Level Aggregation (Pattern B):
//     getAllPosts():       posts → accounts (username/email) + likes count + comments count
//     searchAuditLogs():  audit_log rows → accounts (first_name, last_name)

class AdminRepository
{
    private $dbConnectFactory;

    public function __construct(callable $dbConnectFactory)
    {
        $this->dbConnectFactory = $dbConnectFactory;
    }

    private function getConnection(string $domain): mysqli
    {
        return call_user_func($this->dbConnectFactory, $domain);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // User Deletion (cascading across all 12 micro-databases)
    // ──────────────────────────────────────────────────────────────────────────

    public function processDeleteUser(
        int $userIdToDelete,
        string $deletionReason,
        int $adminId,
        string $adminUsername,
        string $adminRole
    ): array {
        $connAccounts = $this->getConnection('accounts');

        try {
            $stmt = $connAccounts->prepare("SELECT email, first_name, last_name, username, role FROM accounts WHERE id = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $userToDelete = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$userToDelete) {
                return ['success' => false, 'error' => 'User not found.'];
            }

            // Security guard: standard admins may only delete regular user accounts
            $targetRoleCheck = $userToDelete['role'] ?? 'user';
            if ($adminRole !== 'super_admin' && $targetRoleCheck !== 'user') {
                return ['success' => false, 'error' => 'Permission denied: standard Admins may only delete regular user accounts.'];
            }
            if ($targetRoleCheck === 'super_admin') {
                return ['success' => false, 'error' => 'Super Admin accounts cannot be deleted through the dashboard.'];
            }

            $userEmail    = $userToDelete['email'];
            $userName     = trim($userToDelete['first_name'] . ' ' . $userToDelete['last_name']);
            $userUsername = $userToDelete['username'];

            $mailSent = send_account_deletion_email($userEmail, $userName, $deletionReason);
            $emailLog = $mailSent ? 'Email sent successfully.' : 'Email failed: ' . get_last_mail_error();

            // Cascade: posts
            $connPosts = $this->getConnection('posts');
            $stmt = $connPosts->prepare("DELETE FROM posts WHERE userID = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: likes
            $connLikes = $this->getConnection('likes');
            $stmt = $connLikes->prepare("DELETE FROM likes WHERE userID = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: comments
            $connComments = $this->getConnection('comments');
            $stmt = $connComments->prepare("DELETE FROM comments WHERE userID = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: commissions + their payments
            $connCommissions = $this->getConnection('commissions');
            $stmt = $connCommissions->prepare("SELECT commissionID FROM commissions WHERE userID = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $commissionIds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($commissionIds)) {
                $placeholders      = implode(',', array_fill(0, count($commissionIds), '?'));
                $commissionIdValues = array_column($commissionIds, 'commissionID');

                $connPayments = $this->getConnection('commission_payments');
                $stmt = $connPayments->prepare("DELETE FROM commission_payments WHERE commissionID IN ({$placeholders})");
                $stmt->bind_param(str_repeat('i', count($commissionIdValues)), ...$commissionIdValues);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $connCommissions->prepare("DELETE FROM commissions WHERE userID = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: friendships (user1_id = requester, user2_id = receiver)
            $connFriendships = $this->getConnection('friendships');
            $stmt = $connFriendships->prepare("DELETE FROM friendships WHERE user1_id = ? OR user2_id = ?");
            $stmt->bind_param('ii', $userIdToDelete, $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: notifications
            $connNotifications = $this->getConnection('notifications');
            $stmt = $connNotifications->prepare("DELETE FROM notifications WHERE userID = ? OR actor_id = ?");
            $stmt->bind_param('ii', $userIdToDelete, $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: messages (canonical senderID / receiverID)
            $connMessages = $this->getConnection('messages');
            $stmt = $connMessages->prepare("DELETE FROM messages WHERE senderID = ? OR receiverID = ?");
            $stmt->bind_param('ii', $userIdToDelete, $userIdToDelete);
            $stmt->execute();
            $stmt->close();

            // Cascade: pending_registrations (by email)
            $connPendingReg = $this->getConnection('pending_registrations');
            $stmt = $connPendingReg->prepare("DELETE FROM pending_registrations WHERE email = ?");
            $stmt->bind_param('s', $userEmail);
            $stmt->execute();
            $stmt->close();

            // Cascade: password_resets (by email)
            $connPasswordResets = $this->getConnection('password_resets');
            $stmt = $connPasswordResets->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param('s', $userEmail);
            $stmt->execute();
            $stmt->close();

            // Finally: delete the account itself
            $stmt = $connAccounts->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->bind_param('i', $userIdToDelete);
            $stmt->execute();
            $deletedRows = $stmt->affected_rows;
            $stmt->close();

            if ($deletedRows === 0) {
                return ['success' => false, 'error' => 'Account could not be deleted (possibly already gone).'];
            }

            // Audit log — delegate to logAuditAction() so created_at is written in PHT
            // via PHP's date(), the admin_id=0 guard is applied, and target_type is resolved
            // automatically. Do NOT use a raw INSERT here (Bug 2 fix).
            $auditAction = "Deleted user account '{$userUsername}' (ID: {$userIdToDelete}). "
                         . "Reason: '{$deletionReason}'. Email status: {$emailLog}";
            $auditVis    = ($adminRole === 'super_admin') ? 'super_admin' : 'admin';
            $this->logAuditAction($adminId, $adminUsername, $auditAction, $userIdToDelete, $auditVis);

            return ['success' => true, 'message' => 'User account and all associated data deleted successfully.'];

        } catch (Exception $e) {
            error_log("Error deleting user {$userIdToDelete}: " . $e->getMessage());
            return ['success' => false, 'error' => 'An unexpected error occurred during deletion: ' . $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // User Management
    // ──────────────────────────────────────────────────────────────────────────

    public function getAllUsers(int $limit = 0): array
    {
        $connAccounts = $this->getConnection('accounts');
        $sql = "SELECT id, first_name, last_name, username, email, role, banned, created_at
             FROM accounts
             ORDER BY created_at DESC";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $stmt = $connAccounts->prepare($sql);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $users;
    }

    public function processBanUser(int $targetId, int $adminId, string $adminUsername, bool $isSuperAdmin, string $banReason): string
    {
        $connAccounts = $this->getConnection('accounts');
        $stmt = $connAccounts->prepare("SELECT role FROM accounts WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $targetRole = $stmt->get_result()->fetch_assoc()['role'] ?? 'user';
        $stmt->close();

        if ($targetRole === 'super_admin' && !$isSuperAdmin) {
            return "Only a Super Admin can ban another Super Admin.";
        }

        // Standard admins may only ban regular users
        if (!$isSuperAdmin && $targetRole !== 'user') {
            return "Permission denied: standard Admins may only ban regular user accounts.";
        }

        $stmt = $connAccounts->prepare("UPDATE accounts SET banned = 1 WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $this->logAuditAction($adminId, $adminUsername, "Banned user ID {$targetId}. Reason: {$banReason}", $targetId, $isSuperAdmin ? 'super_admin' : 'admin');
            return "User ID {$targetId} has been banned.";
        }
        return "Failed to ban user ID {$targetId}.";
    }

    public function processUnbanUser(int $targetId, int $adminId, string $adminUsername, bool $isSuperAdmin = false): string
    {
        $connAccounts = $this->getConnection('accounts');
        $stmt = $connAccounts->prepare("UPDATE accounts SET banned = 0 WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $auditVis = $isSuperAdmin ? 'super_admin' : 'admin';
            $this->logAuditAction($adminId, $adminUsername, "Unbanned user ID {$targetId}.", $targetId, $auditVis);
            return "User ID {$targetId} has been unbanned.";
        }
        return "Failed to unban user ID {$targetId}.";
    }

    public function processPromoteToAdmin(int $targetId, int $adminId, string $adminUsername): string
    {
        // Belt-and-suspenders: this action is reserved for Super Admins only
        // (enforced at POST handler level; duplicated here for direct-call safety)
        $connAccounts = $this->getConnection('accounts');

        // Verify performer is still super_admin in DB (session could be stale)
        $selfStmt = $connAccounts->prepare("SELECT role FROM accounts WHERE id = ? LIMIT 1");
        $selfStmt->bind_param('i', $adminId);
        $selfStmt->execute();
        $selfRole = $selfStmt->get_result()->fetch_assoc()['role'] ?? '';
        $selfStmt->close();
        if ($selfRole !== 'super_admin') {
            return "Permission denied: only Super Admins can promote accounts.";
        }

        // Fetch target username for the audit log before updating.
        $nameStmt = $connAccounts->prepare("SELECT username FROM accounts WHERE id = ? AND role = 'user' LIMIT 1");
        $nameStmt->bind_param('i', $targetId);
        $nameStmt->execute();
        $nameRow = $nameStmt->get_result()->fetch_assoc();
        $nameStmt->close();

        if (!$nameRow) {
            return "Failed to promote: user not found or already admin/super_admin.";
        }
        $targetUsername = $nameRow['username'];

        $stmt = $connAccounts->prepare("UPDATE accounts SET role = 'admin' WHERE id = ? AND role = 'user'");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $this->logAuditAction(
                $adminId,
                $adminUsername,
                "Role Promoted: @{$targetUsername} (ID {$targetId}) promoted from user to admin.",
                $targetId,
                'super_admin'
            );
            return "@{$targetUsername} has been promoted to Admin.";
        }
        return "Failed to promote @{$targetUsername} to admin.";
    }

    public function processDemoteToUser(int $targetId, int $adminId, string $adminUsername): string
    {
        // Prevent a Super Admin from accidentally demoting themselves (lockout guard)
        if ($targetId === $adminId) {
            return "Permission denied: you cannot demote your own account.";
        }

        $connAccounts = $this->getConnection('accounts');

        // Verify performer is still super_admin in DB
        $selfStmt = $connAccounts->prepare("SELECT role FROM accounts WHERE id = ? LIMIT 1");
        $selfStmt->bind_param('i', $adminId);
        $selfStmt->execute();
        $selfRole = $selfStmt->get_result()->fetch_assoc()['role'] ?? '';
        $selfStmt->close();
        if ($selfRole !== 'super_admin') {
            return "Permission denied: only Super Admins can demote accounts.";
        }

        // Fetch target username for the audit log before updating.
        $nameStmt = $connAccounts->prepare("SELECT username FROM accounts WHERE id = ? AND role = 'admin' LIMIT 1");
        $nameStmt->bind_param('i', $targetId);
        $nameStmt->execute();
        $nameRow = $nameStmt->get_result()->fetch_assoc();
        $nameStmt->close();

        if (!$nameRow) {
            return "Failed to demote: user not found or not an admin.";
        }
        $targetUsername = $nameRow['username'];

        $stmt = $connAccounts->prepare("UPDATE accounts SET role = 'user' WHERE id = ? AND role = 'admin'");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $this->logAuditAction(
                $adminId,
                $adminUsername,
                "Role Demoted: @{$targetUsername} (ID {$targetId}) demoted from admin to user.",
                $targetId,
                'super_admin'
            );
            return "@{$targetUsername} has been demoted to regular User.";
        }
        return "Failed to demote @{$targetUsername} to user.";
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Commission Deletion
    // ─────────────────────────────────────────────────────────────────────────

    public function processDeleteCommission(
        int $commissionId,
        string $deletionReason,
        int $adminId,
        string $adminUsername,
        string $adminRole
    ): array {
        $connCommissions = $this->getConnection('commissions');

        // Step 1 (Pattern B): fetch commission details + owner userID from commissions DB
        $stmt = $connCommissions->prepare(
            "SELECT userID, COALESCE(NULLIF(commission_name, ''), description) AS title
             FROM commissions WHERE commissionID = ? LIMIT 1"
        );
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $commission = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$commission) {
            return ['success' => false, 'error' => 'Commission not found.'];
        }

        $commissionTitle = mb_substr(trim($commission['title'] ?? ''), 0, 80);
        $ownerId         = (int)$commission['userID'];

        // Step 2 (Pattern B): fetch requester email + display name from accounts DB
        $requesterEmail = '';
        $requesterName  = '';
        try {
            $connAccounts = $this->getConnection('accounts');
            $accStmt = $connAccounts->prepare(
                "SELECT email, CONCAT(first_name, ' ', last_name) AS display_name
                 FROM accounts WHERE id = ? LIMIT 1"
            );
            $accStmt->bind_param('i', $ownerId);
            $accStmt->execute();
            $accRow = $accStmt->get_result()->fetch_assoc();
            $accStmt->close();
            $requesterEmail = $accRow['email']        ?? '';
            $requesterName  = trim($accRow['display_name'] ?? '');
        } catch (Exception $e) {
            error_log("processDeleteCommission: accounts lookup failed: " . $e->getMessage());
        }

        // Cascade: delete related payment records first
        try {
            $connPayments = $this->getConnection('commission_payments');
            $stmt = $connPayments->prepare("DELETE FROM commission_payments WHERE commissionID = ?");
            $stmt->bind_param('i', $commissionId);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("processDeleteCommission: payments delete failed: " . $e->getMessage());
        }

        // Delete the commission itself
        $stmt = $connCommissions->prepare("DELETE FROM commissions WHERE commissionID = ?");
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            return ['success' => false, 'error' => 'Commission could not be deleted (possibly already removed).'];
        }

        // Send email notification to the requester (Step 3 — after deletion so a DB error does not abort the email)
        $emailSent = false;
        if ($requesterEmail !== '') {
            try {
                $emailSent = send_commission_deletion_email(
                    $requesterEmail,
                    $requesterName !== '' ? $requesterName : $requesterEmail,
                    $commissionTitle,
                    $deletionReason
                );
            } catch (Exception $e) {
                error_log("processDeleteCommission: email send failed: " . $e->getMessage());
            }
        }

        // Audit log
        $titleTag    = $commissionTitle !== '' ? " \"$commissionTitle\"" : '';
        $emailNote   = $emailSent ? ' Email notification sent.' : ' Email notification failed or skipped.';
        $auditAction = "Deleted commission #{$commissionId}{$titleTag}. Reason: '{$deletionReason}'.{$emailNote}";
        $auditVis    = ($adminRole === 'super_admin') ? 'super_admin' : 'admin';
        $this->logAuditAction($adminId, $adminUsername, $auditAction, $commissionId, $auditVis);

        return ['success' => true, 'message' => "Commission #{$commissionId} has been permanently deleted."];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Post Moderation — Application-Level Aggregation (Pattern B)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns all posts for the Feed Moderator tab, with owner username/email
     * and like/comment counts resolved via separate per-domain queries.
     *
     * Previously used a single query with fully-qualified cross-DB JOINs:
     *   JOIN `fab_ulous_accounts`.accounts a ON p.userID = a.id
     *   (SELECT COUNT(*) FROM `fab_ulous_likes`.likes ...)
     *   (SELECT COUNT(*) FROM `fab_ulous_comments`.comments ...)
     *
     * On Hostinger the posts user cannot see the accounts/likes/comments
     * databases, so the JOIN returned zero rows (silent failure) or a
     * permissions fatal — causing HTTP 500 on admin.php load.
     *
     * Pattern B replacement:
     *   Step 1  Fetch post rows from 'posts' DB.
     *   Step 2  Collect unique userIDs → fetch username/email from 'accounts' DB.
     *   Step 3  Collect postIDs → fetch like counts from 'likes' DB.
     *   Step 4  Collect postIDs → fetch comment counts from 'comments' DB.
     *   Step 5  Merge all four result sets in PHP.
     */
    public function getAllPosts(int $limit = 0): array
    {
        // Step 1: post rows
        $connPosts = $this->getConnection('posts');
        $sql = "SELECT postID, userID, caption, image_url, created_at
             FROM posts
             ORDER BY created_at DESC";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $result    = $connPosts->query($sql);
        if (!$result) return [];
        $posts = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($posts)) return [];

        $postIds = array_column($posts, 'postID');
        $userIds = array_values(array_unique(array_column($posts, 'userID')));

        // Step 2: account details for each unique userID
        $uPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $uTypes        = str_repeat('i', count($userIds));

        $connAccounts = $this->getConnection('accounts');
        $accStmt      = $connAccounts->prepare(
            "SELECT id, username, email FROM accounts WHERE id IN ({$uPlaceholders})"
        );
        $accountMap = [];
        if ($accStmt) {
            $accStmt->bind_param($uTypes, ...$userIds);
            $accStmt->execute();
            foreach ($accStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $acc) {
                $accountMap[(int)$acc['id']] = $acc;
            }
            $accStmt->close();
        }

        // Step 3: like counts per postID
        $pPlaceholders = implode(',', array_fill(0, count($postIds), '?'));
        $pTypes        = str_repeat('i', count($postIds));

        $connLikes = $this->getConnection('likes');
        $likeStmt  = $connLikes->prepare(
            "SELECT postID, COUNT(*) AS cnt FROM likes WHERE postID IN ({$pPlaceholders}) GROUP BY postID"
        );
        $likeMap = [];
        if ($likeStmt) {
            $likeStmt->bind_param($pTypes, ...$postIds);
            $likeStmt->execute();
            foreach ($likeStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $likeMap[(int)$row['postID']] = (int)$row['cnt'];
            }
            $likeStmt->close();
        }

        // Step 4: comment counts per postID
        $connComments  = $this->getConnection('comments');
        $commentStmt   = $connComments->prepare(
            "SELECT postID, COUNT(*) AS cnt FROM comments WHERE postID IN ({$pPlaceholders}) GROUP BY postID"
        );
        $commentMap = [];
        if ($commentStmt) {
            $commentStmt->bind_param($pTypes, ...$postIds);
            $commentStmt->execute();
            foreach ($commentStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $commentMap[(int)$row['postID']] = (int)$row['cnt'];
            }
            $commentStmt->close();
        }

        // Step 5: merge
        foreach ($posts as &$post) {
            $uid = (int)$post['userID'];
            $pid = (int)$post['postID'];

            $acc = $accountMap[$uid] ?? [];
            $post['username'] = $acc['username'] ?? 'Unknown';
            $post['email']    = $acc['email']    ?? '';
            $post['likes']    = $likeMap[$pid]    ?? 0;
            $post['comments'] = $commentMap[$pid] ?? 0;
        }
        unset($post);

        return $posts;
    }

    public function processDeletePost(int $postId, string $removalReason, int $adminId, string $adminUsername, bool $isSuperAdmin = false): string
    {
        // Fetch post details before deletion (single-domain query)
        $connPosts = $this->getConnection('posts');
        $stmt = $connPosts->prepare("SELECT userID, caption FROM posts WHERE postID = ? LIMIT 1");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $postRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$postRow) {
            return "Failed to remove post #{$postId}: post not found.";
        }

        $postOwnerId    = (int)$postRow['userID'];
        $captionPreview = mb_substr($postRow['caption'] ?? '', 0, 200);

        // Fetch owner's account details (separate query — Pattern B)
        $connAccounts = $this->getConnection('accounts');
        $stmt = $connAccounts->prepare("SELECT email, first_name, last_name, username FROM accounts WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $postOwnerId);
        $stmt->execute();
        $ownerRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $ownerEmail    = $ownerRow['email']    ?? '';
        $ownerUsername = $ownerRow['username'] ?? "user #{$postOwnerId}";
        $ownerName     = trim(($ownerRow['first_name'] ?? '') . ' ' . ($ownerRow['last_name'] ?? ''));
        if ($ownerName === '') {
            $ownerName = $ownerUsername;
        }

        // Cascade-delete: likes
        $connLikes = $this->getConnection('likes');
        $stmt = $connLikes->prepare("DELETE FROM likes WHERE postID = ?");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $stmt->close();

        // Cascade-delete: comments
        $connComments = $this->getConnection('comments');
        $stmt = $connComments->prepare("DELETE FROM comments WHERE postID = ?");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $stmt->close();

        // Delete the post itself
        $stmt = $connPosts->prepare("DELETE FROM posts WHERE postID = ?");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            return "Failed to remove post #{$postId} (already gone).";
        }

        // Email the post owner (non-blocking)
        $emailLog = '';
        if ($ownerEmail !== '') {
            $mailSent = send_post_removal_email($ownerEmail, $ownerName, $postId, $captionPreview, $removalReason);
            $emailLog = $mailSent ? ' Email sent to owner.' : ' Email failed: ' . get_last_mail_error();
        } else {
            $emailLog = ' Owner email not found — no notification sent.';
        }

        $auditAction = "Removed post #{$postId} owned by '{$ownerUsername}' (userID: {$postOwnerId})."
                     . " Reason: '{$removalReason}'." . $emailLog;
        $auditVis = $isSuperAdmin ? 'super_admin' : 'admin';
        $this->logAuditAction($adminId, $adminUsername, $auditAction, $postId, $auditVis);

        return "Post #{$postId} removed successfully.{$emailLog}";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dashboard Metrics & Audit Log
    // ──────────────────────────────────────────────────────────────────────────

    public function getDashboardMetrics(): array
    {
        $connPosts    = $this->getConnection('posts');
        $connAccounts = $this->getConnection('accounts');
        $connLikes    = $this->getConnection('likes');
        $connComments = $this->getConnection('comments');
        $connPayments = $this->getConnection('commission_payments');

        $activeProjects = (int)($connPosts->query("SELECT COUNT(*) FROM posts")->fetch_row()[0]    ?? 0);
        $totalUsers     = (int)($connAccounts->query("SELECT COUNT(*) FROM accounts")->fetch_row()[0] ?? 0);
        $totalLikes     = (int)($connLikes->query("SELECT COUNT(*) FROM likes")->fetch_row()[0]    ?? 0);
        $totalComments  = (int)($connComments->query("SELECT COUNT(*) FROM comments")->fetch_row()[0] ?? 0);
        $revenueSales   = (float)($connPayments->query("SELECT SUM(amount) FROM commission_payments WHERE status = 'paid'")->fetch_row()[0] ?? 0.0);

        $engagementRate = ($activeProjects > 0)
            ? round(($totalLikes + $totalComments) / $activeProjects, 2)
            : 0;

        return [
            'activeProjects' => $activeProjects,
            'totalUsers'     => $totalUsers,
            'engagementRate' => $engagementRate,
            'revenueSales'   => number_format($revenueSales, 2),
        ];
    }

    public function getOrderPipeline(): array
    {
        $connCommissions = $this->getConnection('commissions');
        $stmt = $connCommissions->prepare("SELECT status, COUNT(*) AS count FROM commissions GROUP BY status");
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pipeline = [
            'Pending' => 0, 'Accepted' => 0, 'Ongoing' => 0,
            'Delayed' => 0, 'Completed' => 0, 'Cancelled' => 0,
        ];
        foreach ($results as $row) {
            $pipeline[$row['status']] = (int)$row['count'];
        }
        return $pipeline;
    }

    /**
     * Returns audit log entries with actor first/last name resolved from the
     * accounts database via application-level aggregation (Pattern B).
     *
     * Previously used a cross-DB JOIN:
     *   JOIN `fab_ulous_accounts`.accounts a ON al.admin_id = a.id
     *
     * On Hostinger the audit_log user cannot see the accounts database, so
     * that JOIN caused a fatal error / HTTP 500 every time admin.php loaded.
     *
     * Pattern B replacement:
     *   Step 1  Fetch audit_log rows (filter by time window and visibility).
     *   Step 2  Collect unique admin_ids → fetch first_name/last_name from accounts DB.
     *   Step 3  Merge in PHP.
     */
    public function searchAuditLogs(bool $isSuperAdmin, string $searchTerm, int $hours, int $limit = 0, string $sort = 'desc', string $actionFilter = ''): array
    {
        // Step 1: audit_log rows (no cross-DB JOIN — single-domain query)
        $connAudit  = $this->getConnection('audit_log');
        $hasSearch  = ($searchTerm !== '');
        $likeTerm   = '%' . $searchTerm . '%';

        // Visibility filter: super_admin sees all; regular admin sees visibility_role = 'admin' only
        $visFilter  = $isSuperAdmin ? '' : " AND al.visibility_role = 'admin'";

        // Search filter: admin_username and action are in audit_log — those can be searched
        // immediately.  first_name / last_name will be resolved from accounts in Step 2;
        // to keep this query single-domain they are filtered in PHP after the merge.
        $searchSql  = $hasSearch ? " AND (al.admin_username LIKE ? OR al.action LIKE ?)" : '';

        // Action-category filter — maps the URL token to a LIKE pattern against the action text.
        // All matching is done in the single audit_log domain — no cross-DB join required.
        // '' (empty) means "all", which is the default state.
        $validFilters = ['ban', 'unban', 'delete', 'commission', 'login', 'logout'];
        $hasActionFilter = ($actionFilter !== '' && in_array($actionFilter, $validFilters, true));
        $actionLikeMap = [
            'ban'        => '%ban%',
            'unban'      => '%unban%',
            'delete'     => '%delet%',  // matches "delete", "deleted"
            'commission' => '%commission%',
            'login'      => '%login%',
            'logout'     => '%logout%',
        ];
        // "ban" must exclude "unban" rows; handled by excluding entries that also match "unban".
        // Simpler: use a NOT LIKE guard for the ban category only.
        $actionFilterSql  = '';
        $actionLikeTerm   = '';
        if ($hasActionFilter) {
            $actionLikeTerm = $actionLikeMap[$actionFilter];
            if ($actionFilter === 'ban') {
                $actionFilterSql = " AND al.action LIKE ? AND al.action NOT LIKE '%unban%'";
            } else {
                $actionFilterSql = " AND al.action LIKE ?";
            }
        }

        // Compute the cutoff in PHP (PHT via date_default_timezone_set in config.php)
        // so the filter uses the same clock as logAuditAction()'s created_at writes.
        // Never use MySQL NOW() / DATE_SUB(NOW()...) — the MySQL server clock is UTC
        // on Hostinger and cannot be corrected per-database.
        $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));

        $sql = "SELECT logID, admin_id, admin_username, action, target_type, target_id,
                       visibility_role, created_at
                FROM audit_log al
                WHERE al.created_at >= ?
                {$visFilter}
                {$searchSql}
                {$actionFilterSql}
                ORDER BY al.created_at " . ($sort === 'asc' ? 'ASC' : 'DESC') . ""
                . ($limit > 0 ? " LIMIT {$limit}" : "");

        $stmt = $connAudit->prepare($sql);
        if (!$stmt) {
            error_log("AdminRepository::searchAuditLogs prepare failed: " . $connAudit->error);
            return [];
        }

        // Build bind_param types string and values array dynamically
        $types  = 's';          // $cutoff
        $params = [$cutoff];
        if ($hasSearch) {
            $types   .= 'ss';
            $params[] = $likeTerm;
            $params[] = $likeTerm;
        }
        if ($hasActionFilter) {
            $types   .= 's';
            $params[] = $actionLikeTerm;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($logs)) return [];

        // Step 2: fetch first_name / last_name from accounts DB
        $adminIds     = array_values(array_unique(array_column($logs, 'admin_id')));
        $placeholders = implode(',', array_fill(0, count($adminIds), '?'));
        $types        = str_repeat('i', count($adminIds));

        $connAccounts = $this->getConnection('accounts');
        $nameStmt     = $connAccounts->prepare(
            "SELECT id, first_name, last_name FROM accounts WHERE id IN ({$placeholders})"
        );
        $nameMap = [];
        if ($nameStmt) {
            $nameStmt->bind_param($types, ...$adminIds);
            $nameStmt->execute();
            foreach ($nameStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $nameMap[(int)$row['id']] = [
                    'first_name' => $row['first_name'],
                    'last_name'  => $row['last_name'],
                ];
            }
            $nameStmt->close();
        }

        // Step 3: merge + optional first/last name search filter (done in PHP)
        foreach ($logs as &$log) {
            $aid = (int)$log['admin_id'];
            $log['first_name'] = $nameMap[$aid]['first_name'] ?? '';
            $log['last_name']  = $nameMap[$aid]['last_name']  ?? '';
        }
        unset($log);

        // Apply name search in PHP (search terms have already filtered by username/action in SQL)
        if ($hasSearch) {
            $lowerTerm = strtolower($searchTerm);
            $logs = array_values(array_filter($logs, static function (array $row) use ($lowerTerm): bool {
                // Rows already matched by username/action in SQL — keep those.
                // Additionally include rows whose first_name or last_name matches.
                return str_contains(strtolower($row['first_name']), $lowerTerm)
                    || str_contains(strtolower($row['last_name']),  $lowerTerm)
                    // Rows that already matched SQL conditions pass through unchanged
                    // (we can't tell which matched in SQL, so re-check all columns)
                    || str_contains(strtolower($row['admin_username']), $lowerTerm)
                    || str_contains(strtolower($row['action']),         $lowerTerm);
            }));
        }

        return $logs;
    }

    /**
     * Logs an admin action to the audit log.
     *
     * Defensive guard: if $adminId is 0 or negative the entry would be
     * meaningless (and indicates a caller bug — e.g. a bfcache-served page
     * with a stale session).  We refuse to write a corrupt row and instead
     * emit an error_log so the problem is visible in server logs without
     * silently poisoning the audit trail.
     *
     * If $adminUsername is blank we make a best-effort attempt to read it
     * from $_SESSION before falling back to an 'unknown' sentinel, so the
     * audit row is still attributable even when called from a code path that
     * did not propagate the username correctly.
     */
    public function logAuditAction(
        int $adminId,
        string $adminUsername,
        string $action,
        ?int $targetId = null,
        string $visibilityRole = 'admin'
    ): void {
        // ── Strict ID guard ──────────────────────────────────────────────────
        if ($adminId <= 0) {
            // Attempt to recover from session as a last resort
            $sessionId = (int)(($_SESSION['user']['id'] ?? 0));
            if ($sessionId > 0) {
                error_log(
                    "FABulous logAuditAction: adminId was {$adminId} — recovered {$sessionId} from session. "
                    . "Action: {$action}"
                );
                $adminId = $sessionId;
            } else {
                error_log(
                    "FABulous logAuditAction: refusing to write audit row with adminId={$adminId}. "
                    . "Action: {$action}"
                );
                return; // Do NOT write admin_id = 0 to the database
            }
        }

        // ── Username fallback ────────────────────────────────────────────────
        if ($adminUsername === '') {
            $adminUsername = (string)(($_SESSION['user']['username'] ?? 'unknown'));
        }
        // ────────────────────────────────────────────────────────────────────

        $connAudit = $this->getConnection('audit_log');

        $targetType = null;
        if ($targetId !== null) {
            if (str_contains($action, 'user') || str_contains($action, 'account')) $targetType = 'account';
            elseif (str_contains($action, 'post'))       $targetType = 'post';
            elseif (str_contains($action, 'commission')) $targetType = 'commission';
        }

        // Supply an explicit PHT timestamp so the audit row is always recorded
        // in Asia/Manila time regardless of the MySQL server's system clock.
        // config.php sets date_default_timezone_set('Asia/Manila') globally,
        // so date() here already returns a PHT value.
        $phtNow = date('Y-m-d H:i:s');

        $log = $connAudit->prepare(
            "INSERT INTO audit_log (admin_id, admin_username, action, target_type, target_id, visibility_role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($log) {
            $log->bind_param('ississs', $adminId, $adminUsername, $action, $targetType, $targetId, $visibilityRole, $phtNow);
            $log->execute();
            $log->close();
        }
    }
}