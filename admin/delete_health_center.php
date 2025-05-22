<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: admin_panel.php");
    exit();
}

$health_center_id = (int)$_GET['id'];

$stmt = $conn->prepare("DELETE FROM health_centers WHERE id = ?");
$stmt->bind_param("i", $health_center_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Health Center deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete health center: " . $conn->error;
    error_log("delete_health_center.php: Failed to delete health center - " . $conn->error);
}

$stmt->close();
$conn->close();
header("Location: admin_panel.php");
exit();
?>