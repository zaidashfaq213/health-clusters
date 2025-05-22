<?php
session_start();
require_once './includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to unpin a topic.";
    header("Location: login.php");
    exit();
}

$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : 0;

if ($topic_id <= 0 || ($hospital_id <= 0 && $health_center_id <= 0)) {
    $_SESSION['error'] = "Invalid topic or facility ID.";
    header("Location: index.php");
    exit();
}

// Determine facility type
$facility_type = $hospital_id > 0 ? 'hospital' : 'health_center';
$facility_id = $hospital_id > 0 ? $hospital_id : $health_center_id;

// Verify topic is pinned by the user
$stmt = $conn->prepare("SELECT id FROM pinned_topics WHERE user_id = ? AND topic_id = ? AND " . ($facility_type === 'hospital' ? 'hospital_id = ?' : 'health_center_id = ?'));
$stmt->bind_param("iii", $_SESSION['user_id'], $topic_id, $facility_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $_SESSION['error'] = "Topic is not pinned.";
    header("Location: " . ($facility_type === 'hospital' ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id"));
    exit();
}
$stmt->close();

// Delete from pinned_topics
$stmt = $conn->prepare("DELETE FROM pinned_topics WHERE user_id = ? AND topic_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $topic_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "Topic unpinned successfully.";
} else {
    $_SESSION['error'] = "Error unpinning topic: " . $stmt->error;
    error_log("unpin_topic.php: Error unpinning topic ID=$topic_id for user {$_SESSION['user_id']}: " . $stmt->error);
}
$stmt->close();

header("Location: " . ($facility_type === 'hospital' ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id"));
exit();
?>