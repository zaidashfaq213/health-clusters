<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_panel.php");
    exit();
}

$hospital_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id = ?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($hospital) {
    $stmt = $conn->prepare("DELETE FROM hospitals WHERE id = ?");
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $stmt->close();

    // Log action
    $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details) VALUES (?, ?, ?, ?, ?)");
    $action = "Deleted hospital";
    $target_type = "hospital";
    $details = "Name: {$hospital['name']}";
    $stmt->bind_param("isiss", $_SESSION['admin_id'], $action, $hospital_id, $target_type, $details);
    $stmt->execute();
    $stmt->close();
}

header("Location: admin_panel.php");
exit();

$conn->close();
?>