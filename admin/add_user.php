<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_panel.php");
    exit();
}

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = trim($_POST['password']);
$role = $_POST['role'];
$hospital_id = !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : NULL;

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password, role, hospital_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $hospital_id);
$stmt->execute();
$user_id = $conn->insert_id;
$stmt->close();

// Log action
$stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details) VALUES (?, ?, ?, ?, ?)");
$action = "Added user";
$target_type = "user";
$details = "Username: $username, Role: $role" . ($hospital_id ? ", Hospital ID: $hospital_id" : "");
$stmt->bind_param("isiss", $_SESSION['admin_id'], $action, $user_id, $target_type, $details);
$stmt->execute();
$stmt->close();

header("Location: admin_panel.php");
exit();

$conn->close();
?>