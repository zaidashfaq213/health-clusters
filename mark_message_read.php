<?php
session_start();
require_once './includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    exit();
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
if ($message_id <= 0) {
    http_response_code(400);
    exit();
}

$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
$stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

http_response_code(200);
?>