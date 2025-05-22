<?php
require_once './includes/config.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : 0;
if ($health_center_id <= 0) {
    echo json_encode(['error' => 'Invalid health center ID']);
    exit();
}

// Fetch notifications
$stmt = $conn->prepare(
    "SELECT n.id, n.type, n.extinguisher_id, n.alarm_id, n.message, n.due_date, n.status, n.created_at,
            f.code as extinguisher_code, f.location as extinguisher_location,
            a.alarm_type, a.location as alarm_location, a.alarm_time
     FROM notifications n
     LEFT JOIN fireextinguishers f ON n.extinguisher_id = f.id
     LEFT JOIN alarms a ON n.alarm_id = a.id
     WHERE n.health_center_id = ? AND n.status IN ('pending', 'sent')
     ORDER BY n.created_at DESC"
);
if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    error_log("get_notifications.php: Prepare failed: " . $conn->error);
    exit();
}
$stmt->bind_param("i", $health_center_id);
if ($stmt->execute()) {
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $response = ['notifications' => []];
    foreach ($notifications as $notification) {
        $response['notifications'][] = [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'message' => $notification['type'] === 'extinguisher'
                ? "Extinguisher {$notification['extinguisher_code']} at {$notification['extinguisher_location']}: {$notification['message']}"
                : "Alarm {$notification['alarm_type']} at {$notification['alarm_location']}: {$notification['message']}",
            'created_at' => date('M d, Y H:i', strtotime($notification['created_at'])),
            'status' => $notification['status']
        ];
    }
    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Failed to fetch notifications']);
    error_log("get_notifications.php: Execute failed: " . $stmt->error);
}
$stmt->close();
$conn->close();
?>