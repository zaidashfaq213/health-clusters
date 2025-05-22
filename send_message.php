<?php
session_start();
require_once './includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$facility_type = isset($_POST['facility_type']) ? $_POST['facility_type'] : '';
$facility_id = isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : 0;

error_log("send_message.php: Received request: user_id={$_SESSION['user_id']}, receiver_id=$receiver_id, facility_type=$facility_type, facility_id=$facility_id, content=" . substr($content, 0, 50));

if ($receiver_id <= 0 || !in_array($facility_type, ['hospital', 'health_center'])) {
    error_log("send_message.php: Invalid input: receiver_id=$receiver_id, facility_type=$facility_type");
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid receiver or facility type']));
}

// Verify receiver is a manager in the same facility type
if ($facility_type === 'hospital') {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'manager' AND hospital_id IS NOT NULL");
} else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'manager' AND health_center_id IS NOT NULL");
}
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("send_message.php: Receiver validation failed: receiver_id=$receiver_id, facility_type=$facility_type");
    $stmt->close();
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Receiver is not a valid manager for this facility type']));
}
$stmt->close();

$file_path = null;
if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'Uploads/messages/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("send_message.php: Failed to create directory: $upload_dir");
            exit(json_encode(['success' => false, 'error' => 'Failed to create upload directory']));
        }
    }
    if (!is_writable($upload_dir)) {
        error_log("send_message.php: Directory not writable: $upload_dir");
        exit(json_encode(['success' => false, 'error' => 'Upload directory is not writable']));
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $file_type = mime_content_type($_FILES['file']['tmp_name']);
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $file_name = time() . '_' . uniqid() . '.' . $ext;
    $file_path = $upload_dir . $file_name;

    if (!in_array($file_type, $allowed_types)) {
        error_log("send_message.php: Invalid file type: $file_type");
        exit(json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: jpg, png, gif, pdf, doc, docx']));
    }
    if ($_FILES['file']['size'] > $max_size) {
        error_log("send_message.php: File size exceeds 5MB: size={$_FILES['file']['size']}");
        exit(json_encode(['success' => false, 'error' => 'File size exceeds 5MB']));
    }
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
        error_log("send_message.php: Failed to upload file: name={$_FILES['file']['name']}");
        exit(json_encode(['success' => false, 'error' => 'Failed to upload file']));
    }
    error_log("send_message.php: File uploaded successfully: path=$file_path");
}

if (!$content && !$file_path) {
    error_log("send_message.php: No content or file provided");
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Message content or file required']));
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, file_path, hospital_id, health_center_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$hospital_id = $facility_type === 'hospital' ? $facility_id : null;
$health_center_id = $facility_type === 'health_center' ? $facility_id : null;
$stmt->bind_param("iissii", $_SESSION['user_id'], $receiver_id, $content, $file_path, $hospital_id, $health_center_id);
if ($stmt->execute()) {
    error_log("send_message.php: Message inserted successfully: sender_id={$_SESSION['user_id']}, receiver_id=$receiver_id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    $error = $stmt->error;
    error_log("send_message.php: Failed to insert message: sender_id={$_SESSION['user_id']}, receiver_id=$receiver_id, error=$error");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . $error]);
}
$stmt->close();
?>