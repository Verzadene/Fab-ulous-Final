<?php
/**
 * MessageRepository — all database logic for direct messages and the contacts directory.
 *
 * Schema reference (fab_ulous_messages.messages):
 *   - senderID   (formerly sender_id)
 *   - receiverID (formerly receiver_id)
 *   - message_text
 *   - is_read    TINYINT(1) DEFAULT 0  (added by migration_messages_read_status.sql)
 *
 * Cross-database rule (CLAUDE.md §1 — Hostinger production):
 *   On Hostinger, each micro-database has a SEPARATE MySQL user with no cross-database
 *   privileges. Every method that reads from two domains follows Pattern B:
 *     Step 1  Query domain A through getConnection('a').
 *     Step 2  Collect IDs; query domain B through getConnection('b').
 *     Step 3  Merge in PHP.
 *
 * Pattern B audit (all methods):
 *   getContacts()          accounts → friendships  ✅ Pattern B
 *   getConversation()      messages → accounts     ✅ Pattern B
 *   getUnreadCount()       messages (single-domain) ✅ no cross-domain needed
 *   markThreadAsRead()     messages → notifications ✅ Pattern B (two separate connections)
 *   checkUserExists()      accounts (single-domain) ✅
 *   sendMessage()          messages (single-domain) ✅
 */
class MessageRepository {
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
    // Schema introspection
    // ──────────────────────────────────────────────────────────────────────────

    public function getMessagesSchema(): array {
        static $schema = null;
        if ($schema !== null) return $schema;

        $conn = $this->getConnection('messages');

        if (!(bool)$conn->query("SHOW TABLES LIKE 'messages'")->num_rows) {
            $schema = ['ready' => false, 'error' => 'The messages table does not exist yet.'];
            return $schema;
        }

        $columns = [];
        $result  = $conn->query('SHOW COLUMNS FROM messages');
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }

        // Canonical column is `message_text`; fall back to `content` for old schemas
        $messageColumn = isset($columns['message_text']) ? 'message_text'
            : (isset($columns['content']) ? 'content' : null);
        // Canonical time column is `created_at`; fall back to `timestamp`
        $timeColumn = isset($columns['created_at']) ? 'created_at'
            : (isset($columns['timestamp']) ? 'timestamp' : null);

        if (!$messageColumn || !$timeColumn || !isset($columns['senderID']) || !isset($columns['receiverID'])) {
            $schema = ['ready' => false, 'error' => 'The messages table is missing expected columns (senderID, receiverID, message_text/content, created_at/timestamp).'];
            return $schema;
        }

        $schema = [
            'ready'          => true,
            'message_column' => $messageColumn,
            'time_column'    => $timeColumn,
            'has_is_read'    => isset($columns['is_read']),
            'has_image_url'  => isset($columns['image_url']),
        ];
        return $schema;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Contacts directory
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetches all contacts with friendship status merged in.
     *
     * Application-level aggregation (Pattern B):
     *   1. Fetch all eligible accounts from the accounts DB.
     *   2. Fetch friendships for this user from the friendships DB (if enabled).
     *   3. Merge and sort in PHP.
     */
    public function getContacts(int $userId, bool $hasFriendships): array {
        // Step 1: accounts
        $connAccounts = $this->getConnection('accounts');
        $contStmt = $connAccounts->prepare(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS name, username, profile_pic, bio
             FROM accounts
             WHERE id != ? AND banned = 0
             ORDER BY username ASC"
        );
        $contStmt->bind_param('i', $userId);
        $contStmt->execute();
        $contacts = $contStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $contStmt->close();

        if (empty($contacts)) return [];

        // Default friendship fields
        foreach ($contacts as &$contact) {
            $contact['friend_status']    = 'none';
            $contact['friendship_id']    = 0;
            $contact['friend_requester'] = 0;
        }
        unset($contact);

        if (!$hasFriendships) return $contacts;

        // Step 2: friendships for this user
        $connFriendships = $this->getConnection('friendships');
        $fsStmt = $connFriendships->prepare(
            "SELECT friendshipID, user1_id, user2_id, status
             FROM friendships
             WHERE user1_id = ? OR user2_id = ?"
        );
        $fsStmt->bind_param('ii', $userId, $userId);
        $fsStmt->execute();
        $fsRows = $fsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $fsStmt->close();

        // Build lookup by the other user's ID
        $fsMap = [];
        foreach ($fsRows as $fs) {
            $otherId = ((int)$fs['user1_id'] === $userId) ? (int)$fs['user2_id'] : (int)$fs['user1_id'];
            $fsMap[$otherId] = $fs;
        }

        // Step 3: merge
        foreach ($contacts as &$contact) {
            $fs = $fsMap[(int)$contact['id']] ?? null;
            if ($fs) {
                $contact['friend_status']    = $fs['status'];
                $contact['friendship_id']    = (int)$fs['friendshipID'];
                $contact['friend_requester'] = (int)$fs['user1_id']; // user1_id is always the requester
            }
        }
        unset($contact);

        // Sort: accepted first, pending second, then alphabetical
        usort($contacts, static function (array $a, array $b) use ($userId): int {
            $rankA = $a['friend_status'] === 'accepted' ? 0 : ($a['friend_status'] === 'pending' ? 1 : 2);
            $rankB = $b['friend_status'] === 'accepted' ? 0 : ($b['friend_status'] === 'pending' ? 1 : 2);
            if ($rankA !== $rankB) return $rankA - $rankB;
            return strcasecmp($a['username'], $b['username']);
        });

        return $contacts;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reads
    // ──────────────────────────────────────────────────────────────────────────

    public function checkUserExists(int $userId): bool {
        $conn = $this->getConnection('accounts');
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE id = ? AND banned = 0 LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Returns the total number of unread messages addressed to $userId.
     *
     * Single-domain read — messages DB only. No cross-domain join required.
     * Falls back gracefully if the is_read column is not yet present (pre-migration schema).
     */
    public function getUnreadCount(int $userId): int {
        $schema = $this->getMessagesSchema();
        if (!$schema['ready'] || !$schema['has_is_read']) {
            return 0;
        }

        $conn = $this->getConnection('messages');
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM messages WHERE receiverID = ? AND is_read = 0"
        );
        if (!$stmt) return 0;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $count;
    }

    /**
     * Marks all messages in a thread as read and clears the associated message notifications.
     *
     * Application-level aggregation (Pattern B):
     *   Step 1  UPDATE messages SET is_read = 1 via getConnection('messages').
     *   Step 2  DELETE matching 'message' notifications via getConnection('notifications').
     *
     * The two operations use separate connections because on Hostinger the messages
     * DB user has no access to the notifications DB and vice-versa.
     *
     * Silently skips the is_read update if the column is not yet present (pre-migration schema).
     *
     * @param int $userId    The receiving user (messages addressed TO this user are marked read).
     * @param int $friendId  The sending user (the other participant in the thread).
     */
    public function markThreadAsRead(int $userId, int $friendId): void {
        $schema = $this->getMessagesSchema();

        // Step 1: mark messages as read (messages DB)
        if ($schema['ready'] && $schema['has_is_read']) {
            $connMessages = $this->getConnection('messages');
            $stmt = $connMessages->prepare(
                "UPDATE messages SET is_read = 1
                 WHERE receiverID = ? AND senderID = ? AND is_read = 0"
            );
            if ($stmt) {
                $stmt->bind_param('ii', $userId, $friendId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Step 2: clear 'message' notifications from this sender (notifications DB)
        // ref_id on message notifications holds the sender's userID (see create_notification() in config.php).
        $connNotif = $this->getConnection('notifications');
        $stmt = $connNotif->prepare(
            "DELETE FROM notifications
             WHERE userID = ? AND actor_id = ? AND type = 'message'"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $friendId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Fetches a conversation between two users.
     *
     * Application-level aggregation (Pattern B):
     *   1. Fetch messages from the messages DB.
     *   2. Collect unique senderIDs, fetch display names from the accounts DB.
     *   3. Merge in PHP.
     */
    public function getConversation(int $userId, int $friendId, string $messageColumn, string $timeColumn, int $limit = 150, bool $hasImageUrl = false): array {
        $connMessages = $this->getConnection('messages');
        $imageCol = $hasImageUrl ? ', image_url' : '';
        $sql = "SELECT senderID, receiverID,
                       `{$messageColumn}` AS message_text,
                       gif_url{$imageCol},
                       `{$timeColumn}`    AS sent_at
                FROM messages
                WHERE (senderID = ? AND receiverID = ?)
                   OR (senderID = ? AND receiverID = ?)
                ORDER BY `{$timeColumn}` ASC
                LIMIT ?";
        $stmt = $connMessages->prepare($sql);
        $stmt->bind_param('iiiii', $userId, $friendId, $friendId, $userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows)) return [];

        // Fetch sender display names
        $senderIds    = array_values(array_unique(array_column($rows, 'senderID')));
        $placeholders = implode(',', array_fill(0, count($senderIds), '?'));
        $types        = str_repeat('i', count($senderIds));
        $connAccounts = $this->getConnection('accounts');
        $nameStmt     = $connAccounts->prepare(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name FROM accounts WHERE id IN ({$placeholders})"
        );
        $nameStmt->bind_param($types, ...$senderIds);
        $nameStmt->execute();
        $nameRows = $nameStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $nameStmt->close();

        $nameMap = array_column($nameRows, 'full_name', 'id');
        foreach ($rows as &$row) {
            $row['sender_name'] = $nameMap[(int)$row['senderID']] ?? 'Unknown';
        }
        unset($row);

        return $rows;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Writes
    // ──────────────────────────────────────────────────────────────────────────

    public function sendMessage(int $senderId, int $receiverId, string $message, string $messageColumn, ?string $gifUrl = null, ?string $imageUrl = null, bool $hasImageUrl = false): bool {
        $conn = $this->getConnection('messages');
        if ($hasImageUrl) {
            $stmt = $conn->prepare(
                "INSERT INTO messages (senderID, receiverID, `{$messageColumn}`, gif_url, image_url) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iisss', $senderId, $receiverId, $message, $gifUrl, $imageUrl);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO messages (senderID, receiverID, `{$messageColumn}`, gif_url) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('iiss', $senderId, $receiverId, $message, $gifUrl);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Process methods (used by endpoint controllers)
    // ──────────────────────────────────────────────────────────────────────────

    public function processGetConversation(int $userId, int $friendId, array $schema): array {
        if (!$schema['ready']) {
            return ['success' => false, 'error' => $schema['error']];
        }
        if (!$this->checkUserExists($friendId)) {
            return ['success' => false, 'error' => 'That account does not exist.'];
        }

        // Mark thread as read when the conversation is loaded
        $this->markThreadAsRead($userId, $friendId);

        $rows = $this->getConversation($userId, $friendId, $schema['message_column'], $schema['time_column'], 150, $schema['has_image_url'] ?? false);

        $messages = array_map(static function (array $row) use ($userId): array {
            return [
                'message_text' => $row['message_text'],
                'gif_url'      => $row['gif_url'] ?? null,
                'image_url'    => $row['image_url'] ?? null,
                'sender_name'  => $row['sender_name'],
                'sent_at'      => date('M d, Y H:i', strtotime($row['sent_at'])),
                'is_mine'      => (int)$row['senderID'] === $userId,
            ];
        }, $rows);

        return ['success' => true, 'messages' => $messages];
    }

    public function processSendMessage(int $userId, int $friendId, string $message, array $schema, ?string $gifUrl = null, ?string $imageUrl = null): array {
        if (!$schema['ready']) {
            return ['success' => false, 'error' => $schema['error']];
        }
        // Allow blank message text if a GIF or image is attached
        if (!$friendId || ($message === '' && $gifUrl === null && $imageUrl === null)) {
            return ['success' => false, 'error' => 'Message data is incomplete.'];
        }
        if (!$this->checkUserExists($friendId)) {
            return ['success' => false, 'error' => 'That account does not exist.'];
        }

        // Sanitise gif_url — must be a Giphy CDN URL or null
        if ($gifUrl !== null) {
            $parsed = parse_url($gifUrl);
            $host   = $parsed['host'] ?? '';
            $allowedHosts = [
                'media.giphy.com', 'media0.giphy.com', 'media1.giphy.com',
                'media2.giphy.com', 'media3.giphy.com', 'media4.giphy.com',
                'i.giphy.com',
            ];
            if (!in_array($host, $allowedHosts, true)) {
                return ['success' => false, 'error' => 'Invalid GIF source.'];
            }
            $gifUrl = mb_substr($gifUrl, 0, 255);
        }

        if ($imageUrl !== null) {
            $imageUrl = mb_substr($imageUrl, 0, 500);
        }

        $message = mb_substr($message, 0, 1000);
        $success = $this->sendMessage($userId, $friendId, $message, $schema['message_column'], $gifUrl, $imageUrl, $schema['has_image_url'] ?? false);

        if ($success) {
            create_notification($friendId, $userId, 'message', null, $userId);
        }

        return ['success' => $success];
    }

    /**
     * Returns the unread message count for the current user.
     * Intended for use by badge endpoints (e.g. messages.php badge polling).
     */
    public function processGetUnreadCount(int $userId): array {
        return ['success' => true, 'unread_count' => $this->getUnreadCount($userId)];
    }
}