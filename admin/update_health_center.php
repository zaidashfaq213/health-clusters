<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_POST['health_center_id']) || !isset($_POST['health_center_name']) || !isset($_POST['leader']) || !isset($_POST['region'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: admin_panel.php");
    exit();
}

$health_center_id = (int)$_POST['health_center_id'];
$name = trim($_POST['health_center_name']);
$leader = trim($_POST['leader']);
$region = trim($_POST['region']);

$stmt = $conn->prepare("UPDATE health_centers SET name = ?, leader = ?, region = ? WHERE id = ?");
$stmt->bind_param("sssi", $name, $leader, $region, $health_center_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Health Center updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update health center: " . $conn->error;
    error_log("update_health_center.php: Failed to update health center - " . $conn->error);
}

$stmt->close();
$conn->close();
header("Location: admin_panel.php");
exit();
?>