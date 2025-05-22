<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$extinguisher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : 0;

if ($extinguisher_id <= 0 || ($hospital_id === 0 && $health_center_id === 0)) {
    $_SESSION['error'] = "Invalid extinguisher or facility ID.";
    header("Location: index.php");
    exit();
}

// Verify manager role
$is_manager = false;
if ($_SESSION['role'] === 'manager') {
    $facility_id = $hospital_id > 0 ? $hospital_id : $health_center_id;
    $stmt = $conn->prepare(
        $hospital_id > 0
            ? "SELECT id FROM users WHERE id = ? AND hospital_id = ?"
            : "SELECT id FROM users WHERE id = ? AND health_center_id = ?"
    );
    $stmt->bind_param("ii", $_SESSION['user_id'], $facility_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_manager = $result->num_rows > 0;
    $stmt->close();
}

if (!$is_manager) {
    $_SESSION['error'] = "You are not authorized to delete this extinguisher.";
    header("Location: extinguishers.php?" . ($hospital_id > 0 ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
    exit();
}

// Delete extinguisher
$stmt = $conn->prepare("DELETE FROM fireextinguishers WHERE id = ?");
$stmt->bind_param("i", $extinguisher_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "Extinguisher deleted successfully.";
} else {
    $_SESSION['error'] = "Error deleting extinguisher: " . $conn->error;
    error_log("delete_extinguisher.php: Error deleting extinguisher id=$extinguisher_id: " . $conn->error);
}
$stmt->close();

// Redirect back
header("Location: extinguishers.php?" . ($hospital_id > 0 ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
exit();
?>