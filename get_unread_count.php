<?php
session_start();
require_once './includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    exit(json_encode(['unread' => 0]));
}

$manager_id = isset($_GET['manager_id']) ? (int)$_GET['manager_id'] : 0;
if ($manager_id <= 0) {
    http_response_code(400);
    exit(json_encode(['unread' => 0]));
}

$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages 
                        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
$stmt->bind_param("ii", $_SESSION['user_id'], $manager_id);
$stmt->execute();
$unread = $stmt->get_result()->fetch_assoc()['unread'];
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['unread' => $unread]);
?>