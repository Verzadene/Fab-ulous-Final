<?php
/**
 * CommissionRepository — all database logic for commissions and commission payments.
 *
 * Cross-database rule (CLAUDE.md §1 — Hostinger production):
 *   On Hostinger each micro-database has a SEPARATE MySQL user with no
 *   cross-database privileges.  The fully-qualified `db.table` JOIN syntax
 *   (Pattern A) that getAllCommissions() previously used therefore silently
 *   returns zero rows — the commissions user cannot see fab_ulous_accounts or
 *   fab_ulous_commission_payments.
 *
 *   getAllCommissions() has been rewritten to use APPLICATION-LEVEL AGGREGATION
 *   (Pattern B):
 *
 *     Step 1  →  Fetch commission rows from 'commissions' DB (plain table names).
 *     Step 2  →  Collect unique userIDs; fetch account details from 'accounts' DB.
 *     Step 3  →  Collect unique commissionIDs; fetch latest payment status from
 *                'commission_payments' DB.
 *     Step 4  →  Merge everything in PHP and return.
 *
 *   Every other method already targets a single DB and needs no change.
 */
class CommissionRepository {
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
    // Single commission
    // ──────────────────────────────────────────────────────────────────────────

    public function getCommissionById(int $commissionId): ?array {
        $conn = $this->getConnection('commissions');
        $stmt = $conn->prepare(
            "SELECT userID, status, amount,
                    COALESCE(NULLIF(commission_name, ''), description) AS title,
                    description
             FROM commissions WHERE commissionID = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Fetches a commission row for editing, but ONLY if it belongs to $userId
     * AND its current status is exactly 'Pending'.
     *
     * Returns the row array on success, or null if:
     *   - The commission does not exist
     *   - It does not belong to $userId
     *   - Its status is anything other than 'Pending'
     *
     * This is the server-side gate for the user-facing edit flow.
     */
    public function getCommissionForEdit(int $commissionId, int $userId): ?array {
        $conn = $this->getConnection('commissions');
        $stmt = $conn->prepare(
            "SELECT commissionID, userID, status, amount,
                    COALESCE(NULLIF(commission_name, ''), description) AS title,
                    description, stl_file_url AS attachment_url, admin_note
             FROM commissions
             WHERE commissionID = ? AND userID = ? AND status = 'Pending'
             LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('ii', $commissionId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function updateCommission(int $commissionId, string $status, string $adminNote, float $amount): bool {
        $conn = $this->getConnection('commissions');
        $stmt = $conn->prepare('UPDATE commissions SET status = ?, admin_note = ?, amount = ? WHERE commissionID = ?');
        if (!$stmt) return false;
        $stmt->bind_param('ssdi', $status, $adminNote, $amount, $commissionId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Updates the editable fields (title, description, attachment) of a commission.
     * Intentionally does NOT touch status, amount, or admin_note — those are admin-only.
     */
    public function updateCommissionFields(int $commissionId, string $title, string $description, ?string $attachUrl): bool {
        $conn = $this->getConnection('commissions');
        if ($attachUrl !== null) {
            $stmt = $conn->prepare(
                'UPDATE commissions SET commission_name = ?, description = ?, stl_file_url = ? WHERE commissionID = ?'
            );
            if (!$stmt) return false;
            $stmt->bind_param('sssi', $title, $description, $attachUrl, $commissionId);
        } else {
            $stmt = $conn->prepare(
                'UPDATE commissions SET commission_name = ?, description = ? WHERE commissionID = ?'
            );
            if (!$stmt) return false;
            $stmt->bind_param('ssi', $title, $description, $commissionId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * User-facing edit of a commission.
     *
     * Business rules (mirrors the task spec):
     *  1. The commission MUST belong to $userId.
     *  2. The commission's current status MUST be 'Pending'.
     *     Any other status (Accepted, Ongoing, Delayed, Completed, Cancelled)
     *     causes an immediate server-side rejection — no UPDATE is executed.
     *  3. Only title, description, and (optionally) the attachment may be changed.
     *     Status, amount, and admin_note are admin-only fields and are never
     *     touched here.
     *
     * @param int         $commissionId  ID of the commission to edit
     * @param int         $userId        Session user ID (ownership check)
     * @param string      $title         New title
     * @param string      $description   New description
     * @param array|null  $file          $_FILES['attachment'] entry, or null if unchanged
     * @param string      $username      For audit log
     */
    public function processEditCommission(
        int $commissionId,
        int $userId,
        string $title,
        string $description,
        ?array $file,
        string $username = ''
    ): array {
        // ── Sanitise inputs ────────────────────────────────────────────────────
        $title       = mb_substr(trim($title), 0, 255);
        $description = mb_substr(trim($description), 0, 2000);

        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }
        if ($description === '') {
            return ['success' => false, 'error' => 'Description is required.'];
        }

        // ── SERVER-SIDE STATUS GATE ────────────────────────────────────────────
        // getCommissionForEdit() returns null if:
        //   • the row doesn't exist
        //   • it doesn't belong to $userId
        //   • status is NOT 'Pending'
        // This check runs regardless of what the UI rendered.
        $existing = $this->getCommissionForEdit($commissionId, $userId);
        if ($existing === null) {
            return ['success' => false, 'error' => 'Editing is not allowed. The commission may have already been actioned or does not belong to you.'];
        }

        // ── Optional new attachment ────────────────────────────────────────────
        $attachUrl = null;
        if (!empty($file['name'])) {
            $uploadErr = (int)($file['error'] ?? UPLOAD_ERR_OK);
            if ($uploadErr !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error' => 'File upload failed. Please try again.'];
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'error' => 'Attachment must be smaller than 10 MB.'];
            }
            $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'stl', 'dwg'];
            if (!in_array($ext, $allowedExts, true)) {
                return ['success' => false, 'error' => 'Only PDF, STL, and DWG files are allowed.'];
            }
            if ($file['size'] === 0) {
                return ['success' => false, 'error' => 'The uploaded file is empty.'];
            }

            $uploadDir = __DIR__ . '/../uploads/commissions/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return ['success' => false, 'error' => 'Could not prepare upload folder.'];
            }
            $safeFilename = $userId . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeFilename)) {
                return ['success' => false, 'error' => 'Failed to save attachment.'];
            }
            $attachUrl = 'uploads/commissions/' . $safeFilename;
        }

        // ── Persist update ─────────────────────────────────────────────────────
        $ok = $this->updateCommissionFields($commissionId, $title, $description, $attachUrl);
        if (!$ok) {
            return ['success' => false, 'error' => 'Could not save changes. Please try again.'];
        }

        // ── Audit log ──────────────────────────────────────────────────────────
        if ($username !== '') {
            $attachTag   = $attachUrl ? ' [new attachment]' : '';
            $auditAction = "Commission edited by owner: @{$username} — #{$commissionId} \"{$title}\"{$attachTag}";
            $this->logAuditAction($userId, $username, $auditAction, $commissionId);
        }

        return ['success' => true, 'message' => 'Commission updated successfully.'];
    }

    public function createCommission(int $userId, string $title, string $description, ?string $attachUrl): ?int {
        $conn = $this->getConnection('commissions');
        $ins = $conn->prepare(
            "INSERT INTO commissions (userID, commission_name, description, stl_file_url, status)
             VALUES (?, ?, ?, ?, 'Pending')"
        );
        if (!$ins) return null;
        $ins->bind_param('isss', $userId, $title, $description, $attachUrl);
        $ok = $ins->execute();
        $insertId = $ok ? (int)$conn->insert_id : null;
        $ins->close();
        return $insertId;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function getAdminIds(): array {
        $conn = $this->getConnection('accounts');
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE role IN (?, ?) AND banned = 0");
        if (!$stmt) {
            error_log("CommissionRepository::getAdminIds prepare failed: " . $conn->error);
            return [];
        }
        $r1 = 'admin';
        $r2 = 'super_admin';
        $stmt->bind_param('ss', $r1, $r2);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
        $stmt->close();
        return $ids;
    }

    public function logAuditAction(
        int $adminId,
        string $adminUsername,
        string $action,
        int $targetId,
        string $visibilityRole = 'admin'
    ): void {
        // Supply an explicit PHT timestamp (config.php sets Asia/Manila globally)
        // so the row is never stamped with the MySQL server clock (UTC on Hostinger).
        // The optional $visibilityRole param lets callers distinguish super_admin
        // commission actions from regular admin ones (Bug 3 fix).
        $phtNow = date('Y-m-d H:i:s');
        $conn   = $this->getConnection('audit_log');
        $log    = $conn->prepare(
            "INSERT INTO audit_log (admin_id, admin_username, action, target_type, target_id, visibility_role, created_at)
             VALUES (?, ?, ?, 'commission', ?, ?, ?)"
        );
        if ($log) {
            $log->bind_param('ississ', $adminId, $adminUsername, $action, $targetId, $visibilityRole, $phtNow);
            $log->execute();
            $log->close();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Schema introspection helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Detects which optional columns exist in the commissions table.
     * Returns an array of booleans keyed by column name.
     */
    private function detectCommissionColumns(mysqli $conn): array {
        $columns = [];
        $result  = $conn->query('SHOW COLUMNS FROM commissions');
        while ($result && $col = $result->fetch_assoc()) {
            $columns[$col['Field']] = true;
        }
        return $columns;
    }

    /**
     * Determines whether the commission_payments table exists and is reachable.
     */
    private function hasPaymentsTable(): bool {
        try {
            $conn = $this->getConnection('commission_payments');
            return (bool)$conn->query("SHOW TABLES LIKE 'commission_payments'")->num_rows;
        } catch (\Throwable $e) {
            error_log("CommissionRepository::hasPaymentsTable: " . $e->getMessage());
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Listing — APPLICATION-LEVEL AGGREGATION (Pattern B)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns all commissions (admin) or the current user's commissions (user).
     *
     * Previously this used fully-qualified db.table JOINs (Pattern A), which
     * silently broke on Hostinger because each database has a dedicated MySQL
     * user with no SELECT privilege on other databases.
     *
     * The rewrite follows the same three-step aggregation pattern used by
     * PostRepository::getFeed(), MessageRepository::getConversation(), and
     * NotificationRepository::getUnreadNotifications():
     *
     *   Step 1  Fetch commission rows from 'commissions' DB.
     *   Step 2  Fetch account details for all unique userIDs from 'accounts' DB.
     *   Step 3  Fetch latest payment status for all commissionIDs from
     *           'commission_payments' DB (skipped if the table is absent).
     *   Step 4  Merge and return.
     *
     * @param bool $isAdmin  true → fetch all platform commissions (admin view).
     *                       false → fetch only commissions for $userId (client view).
     * @param int  $userId   The signed-in user's ID.
     */
    public function getAllCommissions(bool $isAdmin, int $userId, int $limit = 0): array {
        // ── Step 1: Commission rows ────────────────────────────────────────────
        $connCommissions = $this->getConnection('commissions');
        $columns         = $this->detectCommissionColumns($connCommissions);

        // Resolve schema-evolution expressions to plain column names
        $titleSelect  = isset($columns['commission_name'])
            ? "COALESCE(NULLIF(commission_name, ''), description)"
            : (isset($columns['title']) ? "COALESCE(NULLIF(title, ''), description)" : 'description');

        $noteSelect   = isset($columns['admin_note'])   ? 'admin_note'            : "'' AS admin_note";
        $attachSelect = isset($columns['stl_file_url']) ? 'stl_file_url AS attachment_url' : "'' AS attachment_url";

        $limitClause = ($limit > 0) ? " LIMIT {$limit}" : '';

        if ($isAdmin) {
            $stmt = $connCommissions->prepare(
                "SELECT commissionID,
                        userID,
                        {$titleSelect} AS title,
                        description,
                        amount,
                        status,
                        created_at,
                        {$noteSelect},
                        {$attachSelect}
                 FROM commissions
                 ORDER BY created_at DESC{$limitClause}"
            );
            if (!$stmt) {
                error_log("CommissionRepository::getAllCommissions (admin) prepare failed: " . $connCommissions->error);
                return [];
            }
            $stmt->execute();
        } else {
            $stmt = $connCommissions->prepare(
                "SELECT commissionID,
                        userID,
                        {$titleSelect} AS title,
                        description,
                        amount,
                        status,
                        created_at,
                        {$noteSelect},
                        {$attachSelect}
                 FROM commissions
                 WHERE userID = ?
                 ORDER BY created_at DESC"
            );
            if (!$stmt) {
                error_log("CommissionRepository::getAllCommissions (user) prepare failed: " . $connCommissions->error);
                return [];
            }
            $stmt->bind_param('i', $userId);
            $stmt->execute();
        }

        $commissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($commissions)) {
            return [];
        }

        // ── Step 2: Account details for each unique userID ────────────────────
        $userIds      = array_values(array_unique(array_column($commissions, 'userID')));
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types        = str_repeat('i', count($userIds));

        $connAccounts = $this->getConnection('accounts');
        $accStmt      = $connAccounts->prepare(
            "SELECT id,
                    username,
                    CONCAT(first_name, ' ', last_name) AS requester_name,
                    profile_pic,
                    email
             FROM accounts
             WHERE id IN ({$placeholders})"
        );

        $accountMap = [];
        if ($accStmt) {
            $accStmt->bind_param($types, ...$userIds);
            $accStmt->execute();
            $accRows = $accStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $accStmt->close();
            foreach ($accRows as $acc) {
                $accountMap[(int)$acc['id']] = $acc;
            }
        } else {
            error_log("CommissionRepository::getAllCommissions accounts fetch failed: " . $connAccounts->error);
        }

        // ── Step 3: Payment status for each commissionID ──────────────────────
        $paymentMap = [];
        if ($this->hasPaymentsTable()) {
            $commissionIds     = array_values(array_unique(array_column($commissions, 'commissionID')));
            $payPlaceholders   = implode(',', array_fill(0, count($commissionIds), '?'));
            $payTypes          = str_repeat('i', count($commissionIds));

            $connPayments = $this->getConnection('commission_payments');
            // Fetch the most-recent row per commissionID; one query covers all IDs.
            $payStmt = $connPayments->prepare(
                "SELECT commissionID, status AS payment_status, paid_at
                 FROM commission_payments
                 WHERE commissionID IN ({$payPlaceholders})
                 ORDER BY created_at DESC"
            );

            if ($payStmt) {
                $payStmt->bind_param($payTypes, ...$commissionIds);
                $payStmt->execute();
                $payRows = $payStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $payStmt->close();

                // Keep only the first (most-recent) row per commissionID
                foreach ($payRows as $pay) {
                    $cid = (int)$pay['commissionID'];
                    if (!isset($paymentMap[$cid])) {
                        $paymentMap[$cid] = [
                            'payment_status' => $pay['payment_status'],
                            'paid_at'        => $pay['paid_at'],
                        ];
                    }
                }
            } else {
                error_log("CommissionRepository::getAllCommissions payments fetch failed: " . $connPayments->error);
            }
        }

        // ── Step 4: Merge ──────────────────────────────────────────────────────
        foreach ($commissions as &$commission) {
            $uid = (int)$commission['userID'];
            $cid = (int)$commission['commissionID'];

            $acc = $accountMap[$uid] ?? [];
            $commission['requester_username'] = $acc['username']       ?? 'Unknown';
            $commission['requester_name']     = $acc['requester_name'] ?? '';
            $commission['requester_pic']      = $acc['profile_pic']    ?? null;
            $commission['requester_email']    = $acc['email']          ?? '';

            $pay = $paymentMap[$cid] ?? [];
            $commission['payment_status'] = $pay['payment_status'] ?? '';
            $commission['paid_at']        = $pay['paid_at']        ?? null;
        }
        unset($commission);

        return $commissions;
    }

    public function getCommissionsWithStats(bool $isAdmin, int $userId): array {
        $commissions = $this->getAllCommissions($isAdmin, $userId);

        $stats = ['total' => count($commissions), 'pending' => 0, 'active' => 0, 'completed' => 0, 'spent' => 0.0];
        foreach ($commissions as $c) {
            $stats['spent'] += (float)($c['amount'] ?? 0);
            $s = $c['status'] ?? '';
            if ($s === 'Pending')                                            $stats['pending']++;
            elseif (in_array($s, ['Accepted', 'Ongoing', 'Delayed'], true)) $stats['active']++;
            elseif ($s === 'Completed')                                      $stats['completed']++;
        }

        return ['commissions' => $commissions, 'stats' => $stats];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Process methods (used by endpoint controllers)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Validates, uploads attachment (if any), inserts the commission row,
     * and writes an audit log entry with requester identity, title, and description.
     *
     * @param int    $userId      Requester's account ID
     * @param string $title       Commission title
     * @param string $description Commission description
     * @param array|null $file    $_FILES['attachment'] entry, or null
     * @param string $username    Requester's @username (from session)
     * @param string $firstName   Requester's first name (from session)
     * @param string $lastName    Requester's last name (from session)
     */
    public function processSubmitCommission(
        int $userId,
        string $title,
        string $description,
        ?array $file,
        string $username = '',
        string $firstName = '',
        string $lastName = ''
    ): array {
        $title       = mb_substr(trim($title), 0, 255);
        $description = mb_substr(trim($description), 0, 2000);
        $attachUrl   = null;

        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }
        if ($description === '') {
            return ['success' => false, 'error' => 'Description is required.'];
        }
        if (empty($file['name'])) {
            return ['success' => false, 'error' => 'An attachment (PDF, STL, or DWG) is required.'];
        }

        if (!empty($file['name'])) {
            $uploadErr = (int)($file['error'] ?? UPLOAD_ERR_OK);

            if ($uploadErr !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error' => 'File upload failed. Please try again.'];
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'error' => 'Attachment must be smaller than 10 MB.'];
            }

            $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'stl', 'dwg'];

            if (!in_array($ext, $allowedExts, true)) {
                return ['success' => false, 'error' => 'Only PDF, STL, and DWG files are allowed.'];
            } elseif ($file['size'] === 0) {
                return ['success' => false, 'error' => 'The uploaded file is empty.'];
            }

            $uploadDir = __DIR__ . '/../uploads/commissions/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return ['success' => false, 'error' => 'Could not prepare upload folder.'];
            }

            $safeFilename = $userId . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeFilename)) {
                return ['success' => false, 'error' => 'Failed to save attachment.'];
            }
            $attachUrl = 'uploads/commissions/' . $safeFilename;
        }

        $newId = $this->createCommission($userId, $title, $description, $attachUrl);
        if ($newId !== null) {
            // Audit log — record commission submission with requester identity, title, and description
            if ($username !== '') {
                $fullName       = trim($firstName . ' ' . $lastName);
                $nameTag        = $fullName !== '' ? " ({$fullName})" : '';
                $titleTag       = $title !== '' ? " \"{$title}\"" : '';
                $descPreview    = mb_substr($description, 0, 100);
                $hasAttach      = $attachUrl ? ' [+attachment]' : '';
                $auditAction    = "Commission submitted: @{$username}{$nameTag} — #{$newId}{$titleTag}{$hasAttach}"
                                . " — \"{$descPreview}\"";
                $this->logAuditAction($userId, $username, $auditAction, $newId);
            }
            return ['success' => true, 'message' => 'Commission request submitted successfully!'];
        }

        return ['success' => false, 'error' => 'Could not submit request. Please try again.'];
    }

    public function processUpdateCommission(int $commissionId, string $status, string $adminNote, float $amount, int $adminId, string $adminUsername, array $allowedStatuses, string $adminRole = 'admin'): array {
        $status    = trim($status);
        $adminNote = mb_substr(trim($adminNote), 0, 500);
        $amount    = max(0, round($amount, 2));

        if (!$commissionId || !in_array($status, $allowedStatuses, true)) {
            return ['success' => false, 'error' => 'Invalid data.'];
        }

        $existing       = $this->getCommissionById($commissionId);
        $ownerId        = $existing ? (int)($existing['userID'] ?? 0) : 0;
        $previousStatus = $existing ? (string)($existing['status'] ?? '') : '';

        if ($this->updateCommission($commissionId, $status, $adminNote, $amount)) {
            if ($ownerId > 0 && $previousStatus !== $status) {
                $notifType = $status === 'Accepted' ? 'commission_approved' : 'commission_updated';
                create_notification($ownerId, $adminId, $notifType, null, $commissionId);
            }

            // Build a rich audit action string
            $commissionTitle = mb_substr(trim($existing['title'] ?? ''), 0, 80);
            $titleTag        = $commissionTitle !== '' ? " \"{$commissionTitle}\"" : '';
            $previousAmount  = (float)($existing['amount'] ?? 0);
            $amountChanged   = abs($amount - $previousAmount) >= 0.01;
            $amountTag       = $amountChanged
                ? sprintf(' | Amount: ₱%s → ₱%s', number_format($previousAmount, 2), number_format($amount, 2))
                : ($amount > 0 ? sprintf(' | Amount: ₱%s', number_format($amount, 2)) : '');
            $statusTag       = $previousStatus !== '' && $previousStatus !== $status
                ? " | Status: {$previousStatus} → {$status}"
                : " | Status: {$status}";

            $auditAction = "Commission updated: #{$commissionId}{$titleTag}{$statusTag}{$amountTag}";
            $this->logAuditAction($adminId, $adminUsername, $auditAction, $commissionId, $adminRole);

            return ['success' => true, 'status' => $status, 'amount' => $amount, 'amount_formatted' => '₱' . number_format($amount, 2)];
        }

        return ['success' => false, 'error' => 'Update failed.'];
    }
}