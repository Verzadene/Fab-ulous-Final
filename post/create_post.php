<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PostRepository.php';

if (empty($_SESSION['user']) || empty($_SESSION['mfa_verified'])) {
    header('Location: ../login/login.php');
    exit;
}

$userID    = (int)$_SESSION['user']['id'];
$username  = $_SESSION['user']['username']  ?? '';
$firstName = $_SESSION['user']['first_name'] ?? '';
$lastName  = $_SESSION['user']['last_name']  ?? '';
$caption   = trim($_POST['caption'] ?? '');
$image     = $_FILES['image'] ?? null;

$repo   = new PostRepository('db_connect');
$result = $repo->processCreatePost($userID, $caption, $image, $username, $firstName, $lastName);

if (!$result['ok'] && $result['error'] !== '') {
    header('Location: post.php?post_error=' . urlencode($result['error']));
    exit;
}

header('Location: post.php');
exit;
?>