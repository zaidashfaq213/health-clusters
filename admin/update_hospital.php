<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_panel.php");
    exit();
}

$hospital_id = (int)$_POST['hospital_id'];
$name = trim($_POST['hospital_name']);
$leader = trim($_POST['leader']);

$stmt = $conn->prepare("UPDATE hospitals SET name = ?, leader = ? WHERE id = ?");
$stmt->bind_param("ssi", $name, $leader, $hospital_id);
$stmt->execute();
$stmt->close();

// Log action
$stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details) VALUES (?, ?, ?, ?, ?)");
$action = "Updated hospital";
$target_type = "hospital";
$details = "Name: $name, Leader: $leader";
$stmt->bind_param("isiss", $_SESSION['admin_id'], $action, $hospital_id, $target_type, $details);
$stmt->execute();
$stmt->close();

header("Location: admin_panel.php");
exit();

$conn->close();
?>