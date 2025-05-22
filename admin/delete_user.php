<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_panel.php");
    exit();
}

$user_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Log action
    $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details) VALUES (?, ?, ?, ?, ?)");
    $action = "Deleted user";
    $target_type = "user";
    $details = "Username: {$user['username']}";
    $stmt->bind_param("isiss", $_SESSION['admin_id'], $action, $user_id, $target_type, $details);
    $stmt->execute();
    $stmt->close();
}

header("Location: admin_panel.php");
exit();

$conn->close();
?>