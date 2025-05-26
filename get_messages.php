<?php
session_start();
require_once './includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    exit(json_encode([]));
}

$manager_id = isset($_GET['manager_id']) ? (int)$_GET['manager_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($manager_id <= 0) {
    http_response_code(400);
    exit(json_encode([]));
}

$stmt = $conn->prepare("
    SELECT m.id as message_id, m.sender_id, m.receiver_id, m.content, m.file_path, m.created_at, m.is_read, u.username as sender_username
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    AND m.id > ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $_SESSION['user_id'], $manager_id, $manager_id, $_SESSION['user_id'], $last_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json');
echo json_encode($messages);
?>