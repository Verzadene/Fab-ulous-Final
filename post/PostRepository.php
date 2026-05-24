<?php
/**
 * PostRepository — all database logic for posts, likes, and comments.
 *
 * Cross-database rule (CLAUDE.md §1):
 *   On Hostinger each micro-database has a SEPARATE MySQL user with no
 *   cross-database privileges. Fully-qualified JOIN syntax (Pattern A) therefore
 *   silently returns zero rows for users who lack access to the referenced DB.
 *
 *   getFeed() was rewritten to use APPLICATION-LEVEL AGGREGATION (Pattern B):
 *   each domain is queried through its own db_connect() handle and the results
 *   are merged in PHP. Every other method already isolated writes to a single DB.
 */
class PostRepository {
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
    // Feed
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetches the main social feed for a user (own posts + accepted friends' posts).
     *
     * Uses APPLICATION-LEVEL AGGREGATION (Pattern B from CLAUDE.md) across all
     * five micro-databases involved: friendships, posts, accounts, likes, comments.
     *
     * WHY: On Hostinger each DB has its own MySQL user with no cross-DB privileges,
     * so the previous fully-qualified JOIN (Pattern A) silently returned zero rows,
     * leaving the feed stuck on "Loading feed..." forever.
     *
     * Step 1: Fetch accepted friend IDs          → friendships DB
     * Step 2: Fetch posts for allowed authors     → posts DB
     * Step 3: Fetch author details                → accounts DB
     * Step 4: Fetch like counts + user_liked flag → likes DB
     * Step 5: Fetch comment counts                → comments DB
     * Step 6: Merge in PHP, return sorted by created_at DESC
     */
    public function getFeed(int $userID, int $limit = 20): array {
        // ── Step 1: Accepted friend IDs ───────────────────────────────────────
        $allowedUserIDs  = [$userID];
        $connFriendships = $this->getConnection('friendships');

        $hasFriendships = (bool) $connFriendships->query("SHOW TABLES LIKE 'friendships'")->num_rows;
        if ($hasFriendships) {
            $stmtFriends = $connFriendships->prepare(
                "SELECT user1_id, user2_id FROM friendships
                 WHERE status = 'accepted' AND (user1_id = ? OR user2_id = ?)"
            );
            $stmtFriends->bind_param('ii', $userID, $userID);
            $stmtFriends->execute();
            $friendshipResults = $stmtFriends->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFriends->close();

            foreach ($friendshipResults as $fs) {
                $allowedUserIDs[] = ((int)$fs['user1_id'] === $userID)
                    ? (int)$fs['user2_id']
                    : (int)$fs['user1_id'];
            }
            $allowedUserIDs = array_values(array_unique($allowedUserIDs));
        }

        // ── Step 2: Posts for allowed authors ─────────────────────────────────
        $connPosts    = $this->getConnection('posts');
        $placeholders = implode(',', array_fill(0, count($allowedUserIDs), '?'));
        $types        = str_repeat('i', count($allowedUserIDs));

        $stmtPosts = $connPosts->prepare(
            "SELECT postID, userID, caption, image_url, created_at
             FROM posts
             WHERE userID IN ({$placeholders})
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $bindArgs = array_merge($allowedUserIDs, [$limit]);
        $stmtPosts->bind_param($types . 'i', ...$bindArgs);
        $stmtPosts->execute();
        $posts = $stmtPosts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPosts->close();

        if (empty($posts)) {
            return [];
        }

        $postIDs   = array_column($posts, 'postID');
        $authorIDs = array_values(array_unique(array_column($posts, 'userID')));

        // ── Step 3: Author info (username, profile_pic, bio) ──────────────────
        $connAccounts    = $this->getConnection('accounts');
        $accPlaceholders = implode(',', array_fill(0, count($authorIDs), '?'));
        $accTypes        = str_repeat('i', count($authorIDs));

        $stmtAccounts = $connAccounts->prepare(
            "SELECT id, username, profile_pic, bio
             FROM accounts
             WHERE id IN ({$accPlaceholders})"
        );
        $stmtAccounts->bind_param($accTypes, ...$authorIDs);
        $stmtAccounts->execute();
        $accountRows = $stmtAccounts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAccounts->close();

        $accountMap = [];
        foreach ($accountRows as $acc) {
            $accountMap[(int)$acc['id']] = $acc;
        }

        // ── Step 4: Like counts + user_liked flag ─────────────────────────────
        $connLikes        = $this->getConnection('likes');
        $likePlaceholders = implode(',', array_fill(0, count($postIDs), '?'));
        $likeTypes        = str_repeat('i', count($postIDs));

        $stmtLikes = $connLikes->prepare(
            "SELECT postID,
                    COUNT(*) AS like_count,
                    SUM(CASE WHEN userID = ? THEN 1 ELSE 0 END) AS user_liked
             FROM likes
             WHERE postID IN ({$likePlaceholders})
             GROUP BY postID"
        );
        $likeArgs = array_merge([$userID], $postIDs);
        $stmtLikes->bind_param('i' . $likeTypes, ...$likeArgs);
        $stmtLikes->execute();
        $likeRows = $stmtLikes->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtLikes->close();

        $likeMap = [];
        foreach ($likeRows as $row) {
            $likeMap[(int)$row['postID']] = [
                'like_count' => (int)$row['like_count'],
                'user_liked' => (bool)$row['user_liked'],
            ];
        }

        // ── Step 5: Comment counts ────────────────────────────────────────────
        $connComments        = $this->getConnection('comments');
        $commentPlaceholders = implode(',', array_fill(0, count($postIDs), '?'));
        $commentTypes        = str_repeat('i', count($postIDs));

        $stmtComments = $connComments->prepare(
            "SELECT postID, COUNT(*) AS comment_count
             FROM comments
             WHERE postID IN ({$commentPlaceholders})
             GROUP BY postID"
        );
        $stmtComments->bind_param($commentTypes, ...$postIDs);
        $stmtComments->execute();
        $commentRows = $stmtComments->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtComments->close();

        $commentMap = [];
        foreach ($commentRows as $row) {
            $commentMap[(int)$row['postID']] = (int)$row['comment_count'];
        }

        // ── Step 6: Merge into the shape post.php JS expects ──────────────────
        $result = [];
        foreach ($posts as $post) {
            $pid   = (int)$post['postID'];
            $uid   = (int)$post['userID'];
            $acc   = $accountMap[$uid]  ?? [];
            $likes = $likeMap[$pid]     ?? ['like_count' => 0, 'user_liked' => false];

            $result[] = [
                'postID'        => $pid,
                'caption'       => $post['caption'],
                'image_url'     => $post['image_url'],
                'created_at'    => $post['created_at'],
                'authorID'      => $uid,
                'author'        => $acc['username']    ?? 'Unknown',
                'author_pic'    => $acc['profile_pic'] ?? null,
                'author_bio'    => $acc['bio']         ?? null,
                'like_count'    => $likes['like_count'],
                'user_liked'    => $likes['user_liked'],
                'comment_count' => $commentMap[$pid]   ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Fetches the public feed — all posts from every user, newest first.
     * Same pipeline as getFeed() but skips the friendship filter (Step 1).
     * Steps 2-6 (posts, author info, likes, comments, merge) are identical.
     */
    public function getPublicFeed(int $userID, int $limit = 20): array {
        $connPosts = $this->getConnection('posts');

        $stmtPosts = $connPosts->prepare(
            "SELECT postID, userID, caption, image_url, created_at
             FROM posts
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmtPosts->bind_param('i', $limit);
        $stmtPosts->execute();
        $posts = $stmtPosts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPosts->close();

        if (empty($posts)) {
            return [];
        }

        $postIDs   = array_column($posts, 'postID');
        $authorIDs = array_values(array_unique(array_column($posts, 'userID')));

        // Author info
        $connAccounts    = $this->getConnection('accounts');
        $accPlaceholders = implode(',', array_fill(0, count($authorIDs), '?'));
        $accTypes        = str_repeat('i', count($authorIDs));
        $stmtAccounts    = $connAccounts->prepare(
            "SELECT id, username, profile_pic, bio FROM accounts WHERE id IN ({$accPlaceholders})"
        );
        $stmtAccounts->bind_param($accTypes, ...$authorIDs);
        $stmtAccounts->execute();
        $accountRows = $stmtAccounts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAccounts->close();

        $accountMap = [];
        foreach ($accountRows as $acc) {
            $accountMap[(int)$acc['id']] = $acc;
        }

        // Like counts + user_liked flag
        $connLikes        = $this->getConnection('likes');
        $likePlaceholders = implode(',', array_fill(0, count($postIDs), '?'));
        $likeTypes        = str_repeat('i', count($postIDs));
        $stmtLikes        = $connLikes->prepare(
            "SELECT postID,
                    COUNT(*) AS like_count,
                    SUM(CASE WHEN userID = ? THEN 1 ELSE 0 END) AS user_liked
             FROM likes
             WHERE postID IN ({$likePlaceholders})
             GROUP BY postID"
        );
        $likeArgs = array_merge([$userID], $postIDs);
        $stmtLikes->bind_param('i' . $likeTypes, ...$likeArgs);
        $stmtLikes->execute();
        $likeRows = $stmtLikes->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtLikes->close();

        $likeMap = [];
        foreach ($likeRows as $row) {
            $likeMap[(int)$row['postID']] = [
                'like_count' => (int)$row['like_count'],
                'user_liked' => (bool)$row['user_liked'],
            ];
        }

        // Comment counts
        $connComments        = $this->getConnection('comments');
        $commentPlaceholders = implode(',', array_fill(0, count($postIDs), '?'));
        $commentTypes        = str_repeat('i', count($postIDs));
        $stmtComments        = $connComments->prepare(
            "SELECT postID, COUNT(*) AS comment_count
             FROM comments
             WHERE postID IN ({$commentPlaceholders})
             GROUP BY postID"
        );
        $stmtComments->bind_param($commentTypes, ...$postIDs);
        $stmtComments->execute();
        $commentRows = $stmtComments->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtComments->close();

        $commentMap = [];
        foreach ($commentRows as $row) {
            $commentMap[(int)$row['postID']] = (int)$row['comment_count'];
        }

        // Merge
        $result = [];
        foreach ($posts as $post) {
            $pid   = (int)$post['postID'];
            $uid   = (int)$post['userID'];
            $acc   = $accountMap[$uid]  ?? [];
            $likes = $likeMap[$pid]     ?? ['like_count' => 0, 'user_liked' => false];

            $result[] = [
                'postID'        => $pid,
                'caption'       => $post['caption'],
                'image_url'     => $post['image_url'],
                'created_at'    => $post['created_at'],
                'authorID'      => $uid,
                'author'        => $acc['username']    ?? 'Unknown',
                'author_pic'    => $acc['profile_pic'] ?? null,
                'author_bio'    => $acc['bio']         ?? null,
                'like_count'    => $likes['like_count'],
                'user_liked'    => $likes['user_liked'],
                'comment_count' => $commentMap[$pid]   ?? 0,
            ];
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Posts CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Validates, uploads (if any), inserts the post, and writes an audit log entry
     * with the poster's username, full name, post ID, and caption preview.
     *
     * @param int    $userID    Poster's account ID
     * @param string $caption   Raw caption text
     * @param array|null $imageFile $_FILES['image'] entry, or null
     * @param string $username  Poster's @username (from session)
     * @param string $firstName Poster's first name (from session)
     * @param string $lastName  Poster's last name (from session)
     */
    public function processCreatePost(
        int $userID,
        string $caption,
        ?array $imageFile,
        string $username = '',
        string $firstName = '',
        string $lastName = ''
    ): array {
        $imageURL = null;

        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/posts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext     = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                return ['ok' => false, 'error' => 'invalid_image_type'];
            }

            if ($imageFile['size'] > 5 * 1024 * 1024) {
                return ['ok' => false, 'error' => 'image_too_large'];
            }

            $filename = uniqid('post_', true) . '.' . $ext;
            if (move_uploaded_file($imageFile['tmp_name'], $uploadDir . $filename)) {
                $imageURL = '../uploads/posts/' . $filename;
            }
        }

        if (empty($caption) && !$imageURL) {
            return ['ok' => false, 'error' => 'empty_post'];
        }

        $newPostID = $this->createPost($userID, $caption, $imageURL);
        if ($newPostID === 0) {
            return ['ok' => false, 'error' => 'db_error'];
        }

        // Audit log — record post creation with poster identity, post ID, and caption preview
        if ($username !== '') {
            $captionPreview = mb_substr($caption, 0, 100);
            $hasImage       = $imageURL ? ' [+image]' : '';
            $fullName       = trim($firstName . ' ' . $lastName);
            $nameTag        = $fullName !== '' ? " ({$fullName})" : '';
            $auditAction    = "Post created: @{$username}{$nameTag} posted #{$newPostID}{$hasImage}"
                            . ($captionPreview !== '' ? " — \"{$captionPreview}\"" : ' [no caption]');
            $this->logAuditAction($userID, $username, $auditAction, $newPostID);
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Inserts a new post row and returns the new postID (0 on failure).
     */
    public function createPost(int $userID, string $caption, ?string $imageURL): int {
        $connPosts = $this->getConnection('posts');
        $stmt = $connPosts->prepare("INSERT INTO posts (userID, caption, image_url) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userID, $caption, $imageURL);
        $ok = $stmt->execute();
        $newId = $ok ? (int)$connPosts->insert_id : 0;
        $stmt->close();
        return $newId;
    }

    public function processEditPost(int $postID, int $userID, string $caption): bool {
        $caption = mb_substr(trim($caption), 0, 2000);
        if ($caption === '') return false;
        return $this->editPost($postID, $userID, $caption);
    }

    public function editPost(int $postID, int $userID, string $caption): bool {
        $connPosts = $this->getConnection('posts');
        $stmt = $connPosts->prepare('UPDATE posts SET caption = ? WHERE postID = ? AND userID = ?');
        $stmt->bind_param('sii', $caption, $postID, $userID);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $ok && $affected > 0;
    }

    public function processDeletePost(int $postID, int $userID, string $actorUsername): bool {
        $ok = $this->deletePost($postID, $userID);
        if ($ok) {
            $action = "User deleted their own post (ID: #{$postID})";
            $this->logAuditAction($userID, $actorUsername, $action, $postID);
        }
        return $ok;
    }

    public function deletePost(int $postID, int $userID): bool {
        $connPosts = $this->getConnection('posts');
        $stmt = $connPosts->prepare('DELETE FROM posts WHERE postID = ? AND userID = ?');
        $stmt->bind_param('ii', $postID, $userID);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $ok && $affected > 0;
    }

    public function getPostOwner(int $postID): int {
        $connPosts = $this->getConnection('posts');
        $stmt = $connPosts->prepare("SELECT userID FROM posts WHERE postID = ?");
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
        $connLikes = $this->getConnection('likes');
        $check = $connLikes->prepare("SELECT likeID FROM likes WHERE postID = ? AND userID = ?");
        $check->bind_param("ii", $postID, $userID);
        $check->execute();
        $check->store_result();
        $alreadyLiked = $check->num_rows > 0;
        $check->close();

        if ($alreadyLiked) {
            $del = $connLikes->prepare("DELETE FROM likes WHERE postID = ? AND userID = ?");
            $del->bind_param("ii", $postID, $userID);
            $del->execute();
            $del->close();
            return false; // now unliked
        } else {
            $ins = $connLikes->prepare("INSERT INTO likes (postID, userID) VALUES (?, ?)");
            $ins->bind_param("ii", $postID, $userID);
            $ins->execute();
            $ins->close();
            return true; // now liked
        }
    }

    /**
     * Returns the most-recent likers for a post (up to $limit).
     *
     * Pattern B: likes DB → accounts DB (application-level aggregation).
     * Returns an array of usernames in reverse-chronological order.
     */
    public function getLikers(int $postID, int $limit = 10): array {
        // Step 1: fetch userIDs from likes DB
        $connLikes = $this->getConnection('likes');
        $stmt = $connLikes->prepare(
            'SELECT userID FROM likes WHERE postID = ? ORDER BY likeID DESC LIMIT ?'
        );
        $stmt->bind_param('ii', $postID, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows)) return [];

        // Step 2: fetch usernames from accounts DB
        $userIDs      = array_column($rows, 'userID');
        $placeholders = implode(',', array_fill(0, count($userIDs), '?'));
        $types        = str_repeat('i', count($userIDs));
        $connAccounts = $this->getConnection('accounts');
        $accStmt      = $connAccounts->prepare(
            "SELECT id, username FROM accounts WHERE id IN ({$placeholders})"
        );
        $accStmt->bind_param($types, ...$userIDs);
        $accStmt->execute();
        $accountRows = $accStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $accStmt->close();

        // Step 3: merge — preserve recency order from likes DB
        $usernameMap = array_column($accountRows, 'username', 'id');
        $likers = [];
        foreach ($userIDs as $uid) {
            if (isset($usernameMap[$uid])) {
                $likers[] = $usernameMap[$uid];
            }
        }
        return $likers;
    }

    public function getLikeCount(int $postID): int {
        $connLikes = $this->getConnection('likes');
        $stmt = $connLikes->prepare("SELECT COUNT(*) AS c FROM likes WHERE postID = ?");
        $stmt->bind_param("i", $postID);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $count;
    }

    public function processLike(int $postID, int $userID): array {
        $liked       = $this->toggleLike($postID, $userID);
        $postOwnerID = $this->getPostOwner($postID);

        if ($liked && $postOwnerID && $postOwnerID !== $userID) {
            $this->addNotification($postOwnerID, $userID, 'like', $postID);
        }

        return ['liked' => $liked, 'like_count' => $this->getLikeCount($postID)];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Comments
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetch comments for a post.
     *
     * Application-level aggregation:
     *   1. Fetch comments (commentID, userID, comment_text, created_at) from the comments DB.
     *   2. Collect unique userIDs, fetch usernames from the accounts DB.
     *   3. Merge in PHP.
     *
     * NOTE: The canonical column name from setup_micro_dbs.sql is `comment_text`, NOT `content`.
     */
    public function getComments(int $postID, int $limit = 50): array {
        $connComments = $this->getConnection('comments');

        // Step 1: fetch comments
        $cstmt = $connComments->prepare(
            "SELECT commentID, userID, comment_text AS content, gif_url, created_at
             FROM comments
             WHERE postID = ?
             ORDER BY created_at ASC
             LIMIT ?"
        );
        $cstmt->bind_param("ii", $postID, $limit);
        $cstmt->execute();
        $commentRows = $cstmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cstmt->close();

        if (empty($commentRows)) return [];

        // Step 2: fetch author names from accounts DB
        $userIds      = array_values(array_unique(array_column($commentRows, 'userID')));
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types        = str_repeat('i', count($userIds));
        $connAccounts = $this->getConnection('accounts');

        $accStmt = $connAccounts->prepare(
            "SELECT id, username FROM accounts WHERE id IN ({$placeholders})"
        );
        $accStmt->bind_param($types, ...$userIds);
        $accStmt->execute();
        $accountRows = $accStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $accStmt->close();

        // Step 3: merge
        $usernames = array_column($accountRows, 'username', 'id');

        foreach ($commentRows as &$row) {
            $row['username'] = $usernames[$row['userID']] ?? 'Unknown User';
        }

        return $commentRows;
    }

    public function addComment(int $postID, int $userID, string $content, ?string $gifUrl = null): bool {
        $connComments = $this->getConnection('comments');
        // gif_url is NULL for plain-text comments; a Giphy CDN URL for GIF comments.
        $stmt = $connComments->prepare(
            "INSERT INTO comments (postID, userID, comment_text, gif_url) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("iiss", $postID, $userID, $content, $gifUrl);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getCommentOwner(int $commentID): int {
        $connComments = $this->getConnection('comments');
        $stmt = $connComments->prepare("SELECT userID FROM comments WHERE commentID = ?");
        $stmt->bind_param("i", $commentID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['userID'] : 0;
    }

    public function editComment(int $commentID, int $userID, string $content): bool {
        $connComments = $this->getConnection('comments');
        $stmt = $connComments->prepare("UPDATE comments SET comment_text = ? WHERE commentID = ? AND userID = ?");
        $stmt->bind_param("sii", $content, $commentID, $userID);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteComment(int $commentID, int $userID): bool {
        $connComments = $this->getConnection('comments');
        $stmt = $connComments->prepare("DELETE FROM comments WHERE commentID = ? AND userID = ?");
        $stmt->bind_param("ii", $commentID, $userID);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function processAddComment(int $postID, int $userID, string $content): bool {
        $ok          = $this->addComment($postID, $userID, $content);
        $postOwnerID = $this->getPostOwner($postID);

        if ($ok && $postOwnerID && $postOwnerID !== $userID) {
            $this->addNotification($postOwnerID, $userID, 'comment', $postID);
        }

        return $ok;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function addNotification(int $userID, int $actorID, string $type, int $postID): void {
        if ($userID === $actorID) return;
        create_notification($userID, $actorID, $type, $postID);
    }

    public function logAuditAction(int $userId, string $username, string $action, int $targetId): void {
        $connAudit = $this->getConnection('audit_log');
        $log = $connAudit->prepare(
            "INSERT INTO audit_log (admin_id, admin_username, action, target_type, target_id, visibility_role)
             VALUES (?, ?, ?, 'post', ?, 'admin')"
        );
        if ($log) {
            $log->bind_param('issi', $userId, $username, $action, $targetId);
            $log->execute();
            $log->close();
        }
    }
}