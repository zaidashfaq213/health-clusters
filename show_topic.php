<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized action.";
    $redirect_url = isset($_GET['hospital_id']) ? "hospital.php?id=" . (int)$_GET['hospital_id'] : "health_center.php?id=" . (int)$_GET['health_center_id'];
    header("Location: $redirect_url");
    exit();
}

$topic_id = (int)$_GET['id'];
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : null;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : null;

if (!$hospital_id && !$health_center_id) {
    $_SESSION['error'] = "Facility ID is required.";
    header("Location: index.php");
    exit();
}

// Verify manager's facility
$stmt = $conn->prepare("SELECT hospital_id, health_center_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Verify topic
$stmt = $conn->prepare("SELECT hospital_id, health_center_id FROM topics WHERE id = ? AND status = 'hidden'");
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$topic = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$topic || ($hospital_id && $topic['hospital_id'] != $user['hospital_id']) || ($health_center_id && $topic['health_center_id'] != $user['health_center_id'])) {
    $_SESSION['error'] = "You are not authorized to show this topic.";
    $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
    header("Location: $redirect_url");
    exit();
}

// Show topic
$stmt = $conn->prepare("UPDATE topics SET status = 'visible' WHERE id = ?");
$stmt->bind_param("i", $topic_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "Topic shown successfully.";
} else {
    $_SESSION['error'] = "Failed to show topic: " . $conn->error;
}
$stmt->close();

$redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
header("Location: $redirect_url");
exit();
?>