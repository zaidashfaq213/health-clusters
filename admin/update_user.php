<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: admin_panel.php");
    exit();
}

$user_id = (int)$_POST['user_id'];
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$role = $_POST['role'];
$hospital_id = !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null;
$health_center_id = !empty($_POST['health_center_id']) ? (int)$_POST['health_center_id'] : null;

// Validate hospital_id
if ($hospital_id) {
    $stmt = $conn->prepare("SELECT id FROM hospitals WHERE id = ?");
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Invalid hospital ID.";
        header("Location: admin_panel.php");
        $stmt->close();
        exit();
    }
    $stmt->close();
}

// Validate health_center_id
if ($health_center_id) {
    $stmt = $conn->prepare("SELECT id FROM health_centers WHERE id = ?");
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Invalid health center ID.";
        header("Location: admin_panel.php");
        $stmt->close();
        exit();
    }
    $stmt->close();
}

// Update user
$stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, hospital_id = ?, health_center_id = ? WHERE id = ?");
$stmt->bind_param("sssiii", $username, $email, $role, $hospital_id, $health_center_id, $user_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "User updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update user: " . $conn->error;
    error_log("update_user.php: Failed to update user ID=$user_id: " . $conn->error);
}
$stmt->close();

// Log action
$stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details) VALUES (?, ?, ?, ?, ?)");
$action = "Updated user";
$target_type = "user";
$details = "Username: $username, Role: $role" . 
           ($hospital_id ? ", Hospital ID: $hospital_id" : "") . 
           ($health_center_id ? ", Health Center ID: $health_center_id" : "");
$admin_id = $_SESSION['admin_id'];
$stmt->bind_param("isiss", $admin_id, $action, $user_id, $target_type, $details);
$stmt->execute();
$stmt->close();

header("Location: admin_panel.php");
exit();

$conn->close();
?>