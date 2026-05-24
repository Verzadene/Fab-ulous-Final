<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/MessageRepository.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['mfa_verified'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$msgRepo = new MessageRepository('db_connect');
$userId = (int) $_SESSION['user']['id'];

$schema = $msgRepo->getMessagesSchema();
if (!$schema['ready']) {
    echo json_encode(['success' => false, 'error' => $schema['error'], 'unavailable' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'conversation';
    $friendId = (int) ($_GET['friend_id'] ?? 0);

    if ($action !== 'conversation' || !$friendId) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit;
    }

    $result = $msgRepo->processGetConversation($userId, $friendId, $schema);
    
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $friendId = (int) ($_POST['friend_id'] ?? 0);
    $message  = trim($_POST['message_text'] ?? '');
    $gifUrl   = trim($_POST['gif_url'] ?? '') ?: null;

    if ($action !== 'send') {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit;
    }

    // Handle optional image upload
    $imageUrl = null;
    $imageFile = $_FILES['image'] ?? null;
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['success' => false, 'error' => 'Only JPG, JPEG, PNG, and WebP images are accepted.']);
            exit;
        }
        if ($imageFile['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Image must be 5 MB or smaller.']);
            exit;
        }
        $uploadDir = __DIR__ . '/../uploads/messages/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = uniqid('msg_', true) . '.' . $ext;
        if (move_uploaded_file($imageFile['tmp_name'], $uploadDir . $filename)) {
            $imageUrl = '../uploads/messages/' . $filename;
        } else {
            echo json_encode(['success' => false, 'error' => 'Image upload failed. Please try again.']);
            exit;
        }
    }

    $result = $msgRepo->processSendMessage($userId, $friendId, $message, $schema, $gifUrl, $imageUrl);

    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unsupported request method.']);
