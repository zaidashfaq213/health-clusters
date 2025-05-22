<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized action.";
    $redirect_url = isset($_GET['hospital_id']) ? "hospital.php?id=" . (int)$_GET['hospital_id'] : "health_center.php?id=" . (int)$_GET['health_center_id'];
    header("Location: $redirect_url");
    exit();
}

$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : null;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : null;
$topic_id = (int)$_GET['id'];

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

if ($hospital_id && $user['hospital_id'] != $hospital_id) {
    $_SESSION['error'] = "You are not authorized to manage this hospital.";
    header("Location: hospital.php?id=$hospital_id");
    exit();
}
if ($health_center_id && $user['health_center_id'] != $health_center_id) {
    $_SESSION['error'] = "You are not authorized to manage this health center.";
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Get media path for deletion
$stmt = $conn->prepare("SELECT media_type, media_path, hospital_id, health_center_id FROM topics WHERE id = ?");
$stmt->bind_param("i", $topic_id);
if ($stmt->execute()) {
    $topic = $stmt->get_result()->fetch_assoc();
    if ($topic && $topic['media_type'] !== 'external' && $topic['media_path'] && file_exists($topic['media_path'])) {
        unlink($topic['media_path']);
    }
}
$stmt->close();

if (!$topic || ($hospital_id && $topic['hospital_id'] != $hospital_id) || ($health_center_id && $topic['health_center_id'] != $health_center_id)) {
    $_SESSION['error'] = "Topic not found or unauthorized.";
    $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
    header("Location: $redirect_url");
    exit();
}

// Delete topic
$stmt = $conn->prepare("DELETE FROM topics WHERE id = ?");
$stmt->bind_param("i", $topic_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "Topic deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete topic: " . $conn->error;
}
$stmt->close();

$redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
header("Location: $redirect_url");
exit();
?>