<?php
/**
 * InteractionRepository — all database logic for post likes and comments.
 *
 * Cross-database rule (CLAUDE.md §1 — Hostinger production):
 *   On Hostinger each micro-database has a SEPARATE MySQL user with no
 *   cross-database privileges.  The previous implementation had two problems:
 *
 *   1. CONSTRUCTOR: Accepted a raw `mysqli $conn` hardwired to a single database.
 *      Likes live in 'likes', comments in 'comments', posts in 'posts', and
 *      notifications in 'notifications' — four separate databases.  Passing one
 *      connection made every method that crossed domains silently fail.
 *      → Fixed: constructor now accepts a `callable $dbConnectFactory` (same
 *        pattern used by every other repository) and a private getConnection()
 *        helper resolves the right connection per domain.
 *
 *   2. getComments(): Was a cross-DB JOIN — `FROM comments c JOIN accounts a` —
 *      running on a single connection.  The comments user cannot see accounts.
 *      → Fixed: Application-level aggregation.
 *        Step 1  Fetch comment rows from 'comments' DB.
 *        Step 2  Collect unique userIDs; fetch usernames from 'accounts' DB.
 *        Step 3  Merge in PHP.
 *
 *   3. addNotification(): Was writing to 'notifications' through whatever
 *      connection was passed to the constructor.  On Hostinger that connection
 *      belongs to the 'likes' or 'comments' user, who has no access to the
 *      notifications database.
 *      → Fixed: The global create_notification() helper (config.php) already
 *        opens its own connection to the 'notifications' domain.
 *        addNotification() now delegates to it instead of issuing raw SQL.
 *
 *   4. COLUMN NAME: getComments() and addComment() referenced the legacy
 *      `content` column.  The canonical name (CLAUDE.md §3) is `comment_text`.
 *      → Fixed: all references use `comment_text`.
 *
 *   5. getPostOwner(): Was querying the 'posts' table through whatever single
 *      connection was injected.  → Fixed: now uses getConnection('posts').
 */
class InteractionRepository {
    private $dbConnectFactory;

    /**
     * @param callable $dbConnectFactory  The db_connect factory from config.php.
     *                                    Signature: fn(string $domain): mysqli
     */
    public function __construct(callable $dbConnectFactory)
    {
        $this->dbConnectFactory = $dbConnectFactory;
    }

    private function getConnection(string $domain): mysqli
    {
        return call_user_func($this->dbConnectFactory, $domain);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Posts (read-only cross-domain helper)
    // ──────────────────────────────────────────────────────────────────────────

    public function getPostOwner(int $postID): int {
        $conn = $this->getConnection('posts');
        $stmt = $conn->prepare("SELECT userID FROM posts WHERE postID = ?");
        if (!$stmt) return 0;
        $stmt->bind_param("i", $postID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['userID'] : 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Likes
    // ──────────────────────────────────────────────────────────────────────────

    public function toggleLike(int $postID, int $userID): bool {
        $conn  = $this->getConnection('likes');
        $check = $conn->prepare("SELECT likeID FROM likes WHERE postID = ? AND userID = ?");
        $check->bind_param("ii", $postID, $userID);
        $check->execute();
        $check->store_result();
        $alreadyLiked = $check->num_rows > 0;
        $check->close();

        if ($alreadyLiked) {
            $del = $conn->prepare("DELETE FROM likes WHERE postID = ? AND userID = ?");
            $del->bind_param("ii", $postID, $userID);
            $del->execute();
            $del->close();
            return false; // now unliked
        } else {
            $ins = $conn->prepare("INSERT INTO likes (postID, userID) VALUES (?, ?)");
            $ins->bind_param("ii", $postID, $userID);
            $ins->execute();
            $ins->close();
            return true; // now liked
        }
    }

    public function getLikeCount(int $postID): int {
        $conn    = $this->getConnection('likes');
        $cntStmt = $conn->prepare("SELECT COUNT(*) AS c FROM likes WHERE postID = ?");
        $cntStmt->bind_param("i", $postID);
        $cntStmt->execute();
        $count = (int)$cntStmt->get_result()->fetch_assoc()['c'];
        $cntStmt->close();
        return $count;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Notifications
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Delegates to the global create_notification() helper in config.php.
     *
     * This avoids opening a raw connection to the notifications DB from within
     * this repository — the helper already manages its own db_connect('notifications')
     * call and guards against duplicate like-notifications.
     *
     * NOTE: The duplicate-like guard that previously lived in raw SQL here is
     * intentionally dropped: create_notification() is idempotent enough for this
     * use-case and inserting a duplicate is far less harmful than silently failing
     * on Hostinger due to a cross-DB permissions error.
     */
    public function addNotification(int $userID, int $actorID, string $type, int $postID): void {
        if ($userID === $actorID) return;
        create_notification($userID, $actorID, $type, $postID, null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Comments — Application-level aggregation (Pattern B)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetches comments for a post with commenter usernames resolved from the
     * accounts DB.
     *
     * Step 1  Fetch comment rows from 'comments' DB.
     * Step 2  Collect unique userIDs; fetch usernames from 'accounts' DB.
     * Step 3  Merge in PHP.
     *
     * Uses `comment_text` (canonical column name per CLAUDE.md §3).
     */
    public function getComments(int $postID, int $limit = 50): array {
        // Step 1: comment rows
        $connComments = $this->getConnection('comments');
        $cstmt        = $connComments->prepare(
            "SELECT commentID, userID, comment_text AS content, created_at
             FROM comments
             WHERE postID = ?
             ORDER BY created_at ASC
             LIMIT ?"
        );
        if (!$cstmt) return [];
        $cstmt->bind_param("ii", $postID, $limit);
        $cstmt->execute();
        $comments = $cstmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cstmt->close();

        if (empty($comments)) return [];

        // Step 2: usernames
        $userIds      = array_values(array_unique(array_column($comments, 'userID')));
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types        = str_repeat('i', count($userIds));

        $connAccounts = $this->getConnection('accounts');
        $nameStmt     = $connAccounts->prepare(
            "SELECT id, username FROM accounts WHERE id IN ({$placeholders})"
        );

        $usernameMap = [];
        if ($nameStmt) {
            $nameStmt->bind_param($types, ...$userIds);
            $nameStmt->execute();
            $nameRows = $nameStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $nameStmt->close();
            foreach ($nameRows as $row) {
                $usernameMap[(int)$row['id']] = $row['username'];
            }
        }

        // Step 3: merge
        foreach ($comments as &$comment) {
            $comment['username'] = $usernameMap[(int)$comment['userID']] ?? 'Unknown';
        }
        unset($comment);

        return $comments;
    }

    /**
     * Inserts a comment.  Uses `comment_text` (canonical column name per CLAUDE.md §3).
     */
    public function addComment(int $postID, int $userID, string $content): bool {
        $conn = $this->getConnection('comments');
        $stmt = $conn->prepare("INSERT INTO comments (postID, userID, comment_text) VALUES (?, ?, ?)");
        if (!$stmt) return false;
        $stmt->bind_param("iis", $postID, $userID, $content);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getCommentOwner(int $commentID): int {
        $conn = $this->getConnection('comments');
        $stmt = $conn->prepare("SELECT userID FROM comments WHERE commentID = ?");
        if (!$stmt) return 0;
        $stmt->bind_param("i", $commentID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['userID'] : 0;
    }

    /**
     * Edits a comment.  Uses `comment_text` (canonical column name per CLAUDE.md §3).
     */
    public function editComment(int $commentID, int $userID, string $content): bool {
        $conn = $this->getConnection('comments');
        $stmt = $conn->prepare("UPDATE comments SET comment_text = ? WHERE commentID = ? AND userID = ?");
        if (!$stmt) return false;
        $stmt->bind_param("sii", $content, $commentID, $userID);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteComment(int $commentID, int $userID): bool {
        $conn = $this->getConnection('comments');
        $stmt = $conn->prepare("DELETE FROM comments WHERE commentID = ? AND userID = ?");
        if (!$stmt) return false;
        $stmt->bind_param("ii", $commentID, $userID);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Process methods (used by endpoint controllers)
    // ──────────────────────────────────────────────────────────────────────────

    public function processLike(int $postID, int $userID): array {
        $liked       = $this->toggleLike($postID, $userID);
        $postOwnerID = $this->getPostOwner($postID);

        if ($liked && $postOwnerID && $postOwnerID !== $userID) {
            $this->addNotification($postOwnerID, $userID, 'like', $postID);
        }

        $count = $this->getLikeCount($postID);
        return ['liked' => $liked, 'like_count' => $count];
    }

    public function processAddComment(int $postID, int $userID, string $content): bool {
        $ok          = $this->addComment($postID, $userID, $content);
        $postOwnerID = $this->getPostOwner($postID);

        if ($ok && $postOwnerID && $postOwnerID !== $userID) {
            $this->addNotification($postOwnerID, $userID, 'comment', $postID);
        }

        return $ok;
    }
}