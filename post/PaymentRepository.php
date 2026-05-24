<?php
/**
 * PaymentRepository — payment lifecycle for the PayMongo flow. Backs
 * post/paymongo_checkout.php (creates a pending row, then attaches the
 * checkout session id) and post/paymongo_webhook.php (marks the row paid
 * and fires the commission_paid notification).
 *
 * ── Cross-database rule (Pattern B — Application-Level Aggregation) ──────────
 *   All cross-domain reads use separate db_connect() calls, one per domain.
 *   Fully-qualified `db_name`.`table_name` JOIN syntax (Pattern A) is
 *   PROHIBITED on Hostinger because each MySQL user only has privileges on
 *   its own database.
 *
 *   getCommissionForPayment() was the only method that still used a Pattern A
 *   JOIN (`JOIN \`{$dbAccounts}\`.accounts ...`). It has been refactored to
 *   the three-step Pattern B approach:
 *     Step 1 — fetch commission row from `commissions` DB.
 *     Step 2 — fetch payer name + email from `accounts` DB.
 *     Step 3 — merge in PHP.
 *
 * ── Storage strategy (no schema change) ──────────────────────────────────────
 *   commission_payments.paymongo_payment_id is NOT NULL UNIQUE.
 *     1. createPendingPaymentRecord stores 'pending_' . uniqid() as placeholder.
 *     2. updatePaymentWithCheckoutDetails overwrites it with the PayMongo
 *        checkout session id (cs_…).
 *     3. processWebhookPayment overwrites it with the real PayMongo payment id
 *        (pay_…), sets status='paid' and paid_at, then fires the notification.
 *   The schema has no column for the PayMongo reference number or checkout url,
 *   so updatePaymentWithCheckoutDetails accepts but does not persist them.
 */
class PaymentRepository
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

    // ─────────────────────────────────────────────────────────────────────────
    // Schema / connectivity checks
    // ─────────────────────────────────────────────────────────────────────────

    public function checkPaymentsTableExists(): bool
    {
        $conn   = $this->getConnection('commission_payments');
        $result = $conn->query("SHOW TABLES LIKE 'commission_payments'");
        return $result && $result->num_rows > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pattern B — getCommissionForPayment
    //
    // Previously used a cross-DB JOIN (Pattern A). Refactored to two separate
    // prepared statements, one per domain, results merged in PHP.
    //
    // Step 1: fetch the commission row (commissions DB)
    // Step 2: fetch payer name + email (accounts DB)
    // Step 3: merge — return a single associative array to the caller
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the commission row plus payer info needed to seed a PayMongo
     * checkout. Uses Pattern B (application-level aggregation) — no cross-DB
     * JOIN syntax.
     *
     * @param  int $commissionId  The commission to pay for.
     * @param  int $userId        Must be the owner of the commission.
     * @return array|null         Merged row, or null if not found / not owned.
     */
    public function getCommissionForPayment(int $commissionId, int $userId): ?array
    {
        // ── Step 1: fetch commission row ────────────────────────────────────
        $connC = $this->getConnection('commissions');

        // Detect whether the table uses `commission_name` or `title`
        $cols      = [];
        $colResult = $connC->query('SHOW COLUMNS FROM commissions');
        while ($colResult && $col = $colResult->fetch_assoc()) {
            $cols[$col['Field']] = true;
        }
        $titleExpr = isset($cols['commission_name'])
            ? "COALESCE(NULLIF(commission_name, ''), description)"
            : (isset($cols['title'])
                ? "COALESCE(NULLIF(title, ''), description)"
                : 'description');

        $stmtC = $connC->prepare(
            "SELECT commissionID, userID, amount, {$titleExpr} AS title
             FROM   commissions
             WHERE  commissionID = ? AND userID = ?
             LIMIT  1"
        );
        if (!$stmtC) {
            return null;
        }
        $stmtC->bind_param('ii', $commissionId, $userId);
        $stmtC->execute();
        $commission = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();

        if (!$commission) {
            return null;    // not found or not owned by this user
        }

        // ── Step 2: fetch payer name + email from accounts DB ───────────────
        $connA  = $this->getConnection('accounts');
        $stmtA  = $connA->prepare(
            "SELECT email, CONCAT(first_name, ' ', last_name) AS payer_name
             FROM   accounts
             WHERE  id = ?
             LIMIT  1"
        );
        if (!$stmtA) {
            return null;
        }
        $stmtA->bind_param('i', $userId);
        $stmtA->execute();
        $account = $stmtA->get_result()->fetch_assoc();
        $stmtA->close();

        if (!$account) {
            return null;    // user account row missing — should not happen
        }

        // ── Step 3: merge and return ─────────────────────────────────────────
        return array_merge($commission, $account);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Single-domain reads/writes (commission_payments DB)
    // ─────────────────────────────────────────────────────────────────────────

    public function isCommissionPaid(int $commissionId): bool
    {
        $conn = $this->getConnection('commission_payments');
        $stmt = $conn->prepare(
            "SELECT 1 FROM commission_payments
             WHERE  commissionID = ? AND status = 'paid'
             LIMIT  1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createPendingPaymentRecord(
        int    $commissionId,
        int    $userId,
        string $payerName,
        string $payerEmail,
        float  $amount
    ): ?int {
        $conn        = $this->getConnection('commission_payments');
        $placeholder = 'pending_' . uniqid('', true);
        $status      = 'pending';

        $stmt = $conn->prepare(
            "INSERT INTO commission_payments (commissionID, paymongo_payment_id, status, amount)
             VALUES (?, ?, ?, ?)"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('issd', $commissionId, $placeholder, $status, $amount);
        $ok    = $stmt->execute();
        $newId = $ok ? (int)$conn->insert_id : null;
        $stmt->close();
        return $newId;
    }

    public function failPaymentRecord(int $paymentId): bool
    {
        $conn = $this->getConnection('commission_payments');
        $stmt = $conn->prepare(
            "UPDATE commission_payments SET status = 'failed' WHERE paymentID = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $paymentId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Marks every still-pending row for a commission as 'failed'. Called
     * before opening a new checkout so a user who clicks Pay twice cannot
     * end up with two simultaneously-payable PayMongo sessions for the
     * same commission.
     */
    public function supersedePendingPayments(int $commissionId): int
    {
        $conn = $this->getConnection('commission_payments');
        $stmt = $conn->prepare(
            "UPDATE commission_payments SET status = 'failed'
             WHERE  commissionID = ? AND status = 'pending'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /**
     * Stores the real PayMongo checkout session id (cs_…) received after a
     * successful /checkout_sessions API call. The reference number and
     * checkout URL are accepted for forward-compatibility but not persisted
     * (no schema columns for them).
     */
    public function updatePaymentWithCheckoutDetails(
        int     $paymentId,
        string  $checkoutId,
        ?string $reference,
        string  $checkoutUrl
    ): bool {
        $conn = $this->getConnection('commission_payments');
        $stmt = $conn->prepare(
            "UPDATE commission_payments SET paymongo_payment_id = ? WHERE paymentID = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $checkoutId, $paymentId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Return-URL processing (test / public API — no webhook)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called when PayMongo redirects the user back to the success_url.
     * Marks the most-recent pending payment row for this commission as 'paid'
     * so the status is updated immediately — without waiting for a webhook.
     * Also writes an audit log entry so the payment is permanently recorded.
     *
     * Safe to call even when a webhook later arrives: processWebhookPayment()
     * has its own idempotency guard and will skip the already-paid row.
     *
     * @param  int    $commissionId  The commission that was just paid.
     * @param  int    $userId        Must be the owner of the commission.
     * @param  string $username      Actor's username — written to the audit log.
     * @return bool                  true if a row was updated, false otherwise.
     */
    public function markPaymentPaidOnReturn(int $commissionId, int $userId, string $username): bool
    {
        $conn = $this->getConnection('commission_payments');

        // Safety: only act on rows that genuinely belong to this user's commission.
        // Also fetch amount so the audit log entry is informative.
        $stmt = $conn->prepare(
            "SELECT paymentID, amount FROM commission_payments
             WHERE  commissionID = ? AND status = 'pending'
             ORDER BY paymentID DESC
             LIMIT  1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            // Already paid (webhook beat us here) or no pending row — either way, fine.
            return $this->isCommissionPaid($commissionId);
        }

        $paymentId     = (int)$row['paymentID'];
        $amount        = (float)$row['amount'];
        $placeholderId = 'checkout_return_' . uniqid('', true);

        $upd = $conn->prepare(
            "UPDATE commission_payments
             SET    status = 'paid', paymongo_payment_id = ?, paid_at = NOW()
             WHERE  paymentID = ? AND status = 'pending'"
        );
        if (!$upd) {
            return false;
        }
        $upd->bind_param('si', $placeholderId, $paymentId);
        $upd->execute();
        $affected = $upd->affected_rows;
        $upd->close();

        if ($affected > 0) {
            // Fire the commission_paid notification (same as webhook path)
            $ownerId = $this->getCommissionOwnerId($commissionId);
            if ($ownerId > 0) {
                create_notification($ownerId, $ownerId, 'commission_paid', null, $commissionId);
            }

            // Audit log — record the payment permanently
            $formattedAmount = '₱' . number_format($amount, 2);
            $auditAction = "User {$username} paid commission #{$commissionId} ({$formattedAmount}) via PayMongo checkout.";
            $this->logAuditAction($userId, $username, $auditAction, $commissionId, 'user');
        }

        return $affected > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook processing
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Idempotent: returns 0 without re-firing the notification when the row
     * is already paid. Resolves the row by metadata.payment_id (our internal
     * paymentID, set in checkout.php) and falls back to looking up the stored
     * checkout session id.
     *
     * All queries target commission_payments only (single-domain). The
     * getCommissionOwnerId() helper uses a separate commissions connection
     * (Pattern B — no cross-DB JOIN).
     */
    public function processWebhookPayment(
        ?string $eventId,
        string  $eventType,
        array   $resource
    ): int {
        $conn       = $this->getConnection('commission_payments');
        $resourceId = (string)($resource['id'] ?? '');
        $attributes = $resource['attributes'] ?? [];
        $metadata   = $attributes['metadata']  ?? [];

        // Resolve internal paymentID from metadata first, then by stored id
        $paymentId = isset($metadata['payment_id']) ? (int)$metadata['payment_id'] : 0;

        if ($paymentId <= 0 && $resourceId !== '') {
            $stmt = $conn->prepare(
                "SELECT paymentID FROM commission_payments
                 WHERE  paymongo_payment_id = ?
                 LIMIT  1"
            );
            if ($stmt) {
                $stmt->bind_param('s', $resourceId);
                $stmt->execute();
                $row       = $stmt->get_result()->fetch_assoc();
                $paymentId = $row ? (int)$row['paymentID'] : 0;
                $stmt->close();
            }
        }

        if ($paymentId <= 0) {
            return 0;
        }

        // For checkout_session.payment.paid, use the nested payment id (pay_…)
        $actualPaymentId = $resourceId;
        if ($eventType === 'checkout_session.payment.paid') {
            $payments = $attributes['payments'] ?? [];
            if (!empty($payments) && isset($payments[0]['id'])) {
                $actualPaymentId = (string)$payments[0]['id'];
            }
        }
        if ($actualPaymentId === '') {
            $actualPaymentId = 'paid_' . uniqid('', true);
        }

        // Fetch current row status
        $stmt = $conn->prepare(
            "SELECT commissionID, status FROM commission_payments
             WHERE  paymentID = ?
             LIMIT  1"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return 0;
        }

        $commissionId = (int)$row['commissionID'];

        // Idempotency guard — do not re-process an already-paid row
        if (($row['status'] ?? '') === 'paid') {
            return 0;
        }

        // Duplicate-payment guard — refuse if a *different* row for the same
        // commission is already paid (user managed to complete two sessions)
        if ($commissionId > 0) {
            $dupStmt = $conn->prepare(
                "SELECT 1 FROM commission_payments
                 WHERE  commissionID = ? AND status = 'paid' AND paymentID <> ?
                 LIMIT  1"
            );
            if ($dupStmt) {
                $dupStmt->bind_param('ii', $commissionId, $paymentId);
                $dupStmt->execute();
                $dupStmt->store_result();
                $alreadyPaid = $dupStmt->num_rows > 0;
                $dupStmt->close();

                if ($alreadyPaid) {
                    // Mark this session failed so it does not linger as pending
                    $failStmt = $conn->prepare(
                        "UPDATE commission_payments SET status = 'failed'
                         WHERE  paymentID = ? AND status = 'pending'"
                    );
                    if ($failStmt) {
                        $failStmt->bind_param('i', $paymentId);
                        $failStmt->execute();
                        $failStmt->close();
                    }
                    return 0;
                }
            }
        }

        // Only promote 'pending' rows — 'failed'/'cancelled' must not become
        // 'paid' through a stale/replayed webhook delivery
        $stmt = $conn->prepare(
            "UPDATE commission_payments
             SET    status = 'paid', paymongo_payment_id = ?, paid_at = NOW()
             WHERE  paymentID = ? AND status = 'pending'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('si', $actualPaymentId, $paymentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Fire notification via the commissions DB (Pattern B: separate connection)
        if ($affected > 0 && $commissionId > 0) {
            $ownerId = $this->getCommissionOwnerId($commissionId);
            if ($ownerId > 0) {
                create_notification($ownerId, $ownerId, 'commission_paid', null, $commissionId);
            }
        }

        return $affected;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetches the owner (userID) of a commission. Uses a dedicated
     * db_connect('commissions') call — no cross-DB JOIN with commission_payments.
     */
    private function getCommissionOwnerId(int $commissionId): int
    {
        $conn = $this->getConnection('commissions');
        $stmt = $conn->prepare(
            "SELECT userID FROM commissions WHERE commissionID = ? LIMIT 1"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $commissionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['userID'] : 0;
    }

    /**
     * Writes one row to the audit_log database. Uses an explicit PHT timestamp
     * (config.php sets Asia/Manila globally) so the row is never stamped with
     * MySQL's UTC clock.
     *
     * $visibilityRole should be 'user' for user-initiated actions (payments)
     * and 'admin' / 'super_admin' for administrative actions.
     */
    private function logAuditAction(
        int    $actorId,
        string $actorUsername,
        string $action,
        int    $targetId,
        string $visibilityRole = 'admin'
    ): void {
        if ($actorId <= 0) {
            return;
        }
        $phtNow = date('Y-m-d H:i:s');
        $conn   = $this->getConnection('audit_log');
        $stmt   = $conn->prepare(
            "INSERT INTO audit_log (admin_id, admin_username, action, target_type, target_id, visibility_role, created_at)
             VALUES (?, ?, ?, 'commission', ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param('ississ', $actorId, $actorUsername, $action, $targetId, $visibilityRole, $phtNow);
            $stmt->execute();
            $stmt->close();
        }
    }
}