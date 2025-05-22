<?php
session_start();
require_once './includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to pin a topic.";
    header("Location: login.php");
    exit();
}

// Get input parameters
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : 0;

// Validate inputs
if ($topic_id <= 0 || ($hospital_id <= 0 && $health_center_id <= 0)) {
    $_SESSION['error'] = "Invalid topic or facility ID.";
    header("Location: index.php");
    exit();
}

// Determine facility type
$facility_type = $hospital_id > 0 ? 'hospital' : 'health_center';
$facility_id = $hospital_id > 0 ? $hospital_id : $health_center_id;

// Verify topic exists and belongs to the specified facility
$stmt = $conn->prepare("SELECT id FROM topics WHERE id = ? AND " . ($facility_type === 'hospital' ? 'hospital_id = ?' : 'health_center_id = ?'));
$stmt->bind_param("ii", $topic_id, $facility_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $_SESSION['error'] = "Topic not found or does not belong to this facility.";
    header("Location: index.php");
    exit();
}
$stmt->close();

// Check if topic is already pinned
$stmt = $conn->prepare("SELECT id FROM pinned_topics WHERE user_id = ? AND topic_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $topic_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Topic is already pinned.";
    header("Location: " . ($facility_type === 'hospital' ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id"));
    exit();
}
$stmt->close();

// Insert into pinned_topics
$stmt = $conn->prepare("INSERT INTO pinned_topics (user_id, topic_id, hospital_id, health_center_id) VALUES (?, ?, ?, ?)");
$null = null;
if ($facility_type === 'hospital') {
    $stmt->bind_param("iiii", $_SESSION['user_id'], $topic_id, $hospital_id, $null);
} else {
    $stmt->bind_param("iiii", $_SESSION['user_id'], $topic_id, $null, $health_center_id);
}

if ($stmt->execute()) {
    $_SESSION['success'] = "Topic pinned successfully.";
} else {
    $_SESSION['error'] = "Error pinning topic: " . $stmt->error;
    error_log("pin_topic.php: Error pinning topic ID=$topic_id for user {$_SESSION['user_id']}: " . $stmt->error);
}
$stmt->close();

// Redirect to the appropriate page
header("Location: " . ($facility_type === 'hospital' ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id"));
exit();
?>