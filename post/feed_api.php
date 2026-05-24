<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PostRepository.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['mfa_verified'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$userID = (int)$_SESSION['user']['id'];
$filter = ($_GET['filter'] ?? 'friends') === 'public' ? 'public' : 'friends';

$postRepo = new PostRepository('db_connect');
$posts = $filter === 'public'
    ? $postRepo->getPublicFeed($userID)
    : $postRepo->getFeed($userID);

echo json_encode(['status' => 'success', 'data' => ['posts' => $posts]]);
?>