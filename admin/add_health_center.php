<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_POST['health_center_name']) || !isset($_POST['leader']) || !isset($_POST['region'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: admin_panel.php");
    exit();
}

$name = trim($_POST['health_center_name']);
$leader = trim($_POST['leader']);
$region = trim($_POST['region']);

$stmt = $conn->prepare("INSERT INTO health_centers (name, leader, region, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sss", $name, $leader, $region);

if ($stmt->execute()) {
    $_SESSION['success'] = "Health Center added successfully.";
} else {
    $_SESSION['error'] = "Failed to add health center: " . $conn->error;
    error_log("add_health_center.php: Failed to add health center - " . $conn->error);
}

$stmt->close();
$conn->close();
header("Location: admin_panel.php");
exit();
?>