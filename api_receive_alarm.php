<?php
require_once './includes/config.php';
require_once './NotificationService.php';

// Load environment variables
$env = parse_ini_file('.env');
$API_KEY = $env['API_KEY'] ?? null;
if (!$API_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: API key not set']);
    error_log("api_receive_alarm.php: API key not configured");
    exit();
}

// Validate API key
$headers = getallheaders();
$provided_api_key = $headers['X-API-Key'] ?? '';
if ($provided_api_key !== $API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid API key']);
    error_log("api_receive_alarm.php: Invalid API key provided");
    exit();
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    error_log("api_receive_alarm.php: Invalid JSON input");
    exit();
}

// Validate input
$hospital_id = isset($input['hospital_id']) ? (int)$input['hospital_id'] : null;
$health_center_id = isset($input['health_center_id']) ? (int)$input['health_center_id'] : null;
$alarm_type = trim($input['alarm_type'] ?? '');
$location = trim($input['location'] ?? '');
$status = trim($input['status'] ?? 'Active');
$alarm_time = trim($input['time'] ?? '');

if (!$hospital_id && !$health_center_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Hospital ID or Health Center ID required']);
    error_log("api_receive_alarm.php: Missing hospital_id or health_center_id");
    exit();
}
if (!in_array($alarm_type, ['Fire', 'Smoke', 'Heat'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid alarm type']);
    error_log("api_receive_alarm.php: Invalid alarm_type: $alarm_type");
    exit();
}
if (empty($location) || strlen($location) > 255) {
    http_response_code(400);
    echo json_encode(['error' => 'Location must be 1-255 characters']);
    error_log("api_receive_alarm.php: Invalid location length: " . strlen($location));
    exit();
}
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $alarm_time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid time format']);
    error_log("api_receive_alarm.php: Invalid time format: $alarm_time");
    exit();
}
$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $alarm_time);
if (!$dateTime || $dateTime > new DateTime('now +1 day') || $dateTime < new DateTime('2000-01-01')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or unrealistic time']);
    error_log("api_receive_alarm.php: Invalid alarm_time: $alarm_time");
    exit();
}
if (!in_array($status, ['Active', 'Resolved'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    error_log("api_receive_alarm.php: Invalid status: $status");
    exit();
}

// Validate hospital_id or health_center_id
if ($hospital_id) {
    $stmt = $conn->prepare("SELECT id FROM hospitals WHERE id = ?");
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid hospital ID']);
        error_log("api_receive_alarm.php: Invalid hospital_id: $hospital_id");
        exit();
    }
    $stmt->close();
} elseif ($health_center_id) {
    $stmt = $conn->prepare("SELECT id FROM health_centers WHERE id = ?");
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid health center ID']);
        error_log("api_receive_alarm.php: Invalid health_center_id: $health_center_id");
        exit();
    }
    $stmt->close();
}

// Initialize NotificationService
$notificationService = new NotificationService($conn, $env);

// Store alarm
$stmt = $conn->prepare(
    "INSERT INTO alarms (hospital_id, health_center_id, alarm_type, location, status, alarm_time) 
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("iissss", $hospital_id, $health_center_id, $alarm_type, $location, $status, $alarm_time);
if ($stmt->execute()) {
    $alarm_id = $conn->insert_id;
    error_log("api_receive_alarm.php: Alarm stored successfully, alarm_id=$alarm_id, hospital_id=$hospital_id, health_center_id=$health_center_id");

    // Create notification
    $message = "🚨 $alarm_type alarm triggered at $location on $alarm_time. Please evacuate and take necessary actions.";
    $notification_id = $notificationService->createNotification(
        'alarm',
        null,
        $alarm_id,
        $hospital_id,
        $health_center_id,
        $message
    );

    if ($notification_id) {
        $emails = $notificationService->getManagerEmails($hospital_id, $health_center_id);
        if ($notificationService->sendEmail($emails, "🚨 Fire Alarm Triggered", $message)) {
            $notificationService->updateNotificationStatus($notification_id, 'sent');
        }
        // Send SMS (optional)
        $phone_numbers = $env['SAFETY_TEAM_NUMBERS'] ? explode(',', $env['SAFETY_TEAM_NUMBERS']) : [];
        $notificationService->sendSMS($phone_numbers, $message);
    }

    http_response_code(200);
    echo json_encode(['message' => 'Alarm received and stored', 'alarm_id' => $alarm_id, 'notification_id' => $notification_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to store alarm']);
    error_log("api_receive_alarm.php: Execute failed: " . $stmt->error);
}
$stmt->close();
$conn->close();
?>