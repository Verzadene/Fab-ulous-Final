<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PostRepository.php';
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['mfa_verified'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$repo   = new PostRepository('db_connect');
$userID = (int)$_SESSION['user']['id'];

// ── GET: fetch likers for hover popup ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'likers') {
        $postID = (int)($_GET['post_id'] ?? 0);
        if (!$postID) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid post']);
            exit;
        }
        echo json_encode(['status' => 'success', 'likers' => $repo->getLikers($postID, 10)]);
        exit;
    }
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}

// ── POST: toggle like ─────────────────────────────────────────────────────────
$postID = (int)($_POST['post_id'] ?? 0);

if (!$postID) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid post']);
    exit;
}

$postOwnerID = $repo->getPostOwner($postID);

if (!$postOwnerID) {
    echo json_encode(['status' => 'error', 'message' => 'Post not found']);
    exit;
}

$result = $repo->processLike($postID, $userID);

echo json_encode([
    'status' => 'success',
    'data' => $result
]);
?>