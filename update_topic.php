<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Unauthorized action.";
    error_log("update_topic.php: Unauthorized. User ID=" . ($_SESSION['user_id'] ?? 'none') . ", Role=" . ($_SESSION['role'] ?? 'none'));
    $redirect_url = isset($_POST['hospital_id']) ? "hospital.php?id=" . (int)$_POST['hospital_id'] : (isset($_POST['health_center_id']) ? "health_center.php?id=" . (int)$_POST['health_center_id'] : "index.php");
    header("Location: $redirect_url");
    exit();
}

// Set charset to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    error_log("update_topic.php: Error setting charset: " . $conn->error);
    $_SESSION['error'] = "Database error.";
    header("Location: index.php");
    exit();
}

$hospital_id = isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : NULL;
$health_center_id = isset($_POST['health_center_id']) ? (int)$_POST['health_center_id'] : NULL;
$topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
$title = mb_convert_encoding(trim($_POST['title'] ?? ''), 'UTF-8', 'auto');
$content = mb_convert_encoding(trim($_POST['content'] ?? ''), 'UTF-8', 'auto');
$indicator_input = trim($_POST['indicator'] ?? '');
$status = mb_convert_encoding(trim($_POST['status'] ?? 'visible'), 'UTF-8', 'auto');
$external_url = mb_convert_encoding(trim($_POST['external_url'] ?? ''), 'UTF-8', 'auto');
$remove_media = isset($_POST['remove_media']) && $_POST['remove_media'] === '1';

// Validate facility and topic IDs
if ($topic_id <= 0 || ($hospital_id === NULL && $health_center_id === NULL)) {
    $_SESSION['error'] = "Invalid facility or topic ID.";
    error_log("update_topic.php: Invalid hospital_id=$hospital_id, health_center_id=$health_center_id, topic_id=$topic_id");
    header("Location: index.php");
    exit();
}

// Verify manager's facility affiliation
$stmt = $conn->prepare("SELECT hospital_id FROM users WHERE id = ? AND role = 'manager'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($hospital_id !== NULL && (!$user || $user['hospital_id'] != $hospital_id)) {
    $_SESSION['error'] = "You are not authorized to manage this hospital.";
    error_log("update_topic.php: Hospital mismatch. User Hospital ID=" . ($user['hospital_id'] ?? 'none') . ", Requested Hospital ID=$hospital_id");
    header("Location: hospital.php?id=$hospital_id");
    exit();
}
// Note: Assuming managers can edit health center topics (no strict check for health_center_id affiliation for simplicity)
// Add stricter checks if users table has health_center_id

// Get existing topic
$stmt = $conn->prepare("SELECT media_type, media_path, hospital_id, health_center_id FROM topics WHERE id = ? AND (hospital_id = ? OR health_center_id = ?)");
$stmt->bind_param("iii", $topic_id, $hospital_id, $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("update_topic.php: Topic query error: " . $conn->error);
    $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
    header("Location: $redirect_url");
    $stmt->close();
    exit();
}
$topic = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$topic) {
    $_SESSION['error'] = "Topic not found.";
    error_log("update_topic.php: Topic not found - id=$topic_id, hospital_id=$hospital_id, health_center_id=$health_center_id");
    $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
    header("Location: $redirect_url");
    exit();
}

// Map indicator strings to integers
$indicator_map = [
    'critical' => -1,
    'normal' => 0,
    'positive' => 1
];

// Validate inputs
if (empty($title)) {
    $_SESSION['error'] = "Topic title is required.";
    error_log("update_topic.php: Missing title for topic_id=$topic_id");
    header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
    exit();
}
if (!array_key_exists(strtolower($indicator_input), $indicator_map)) {
    $_SESSION['error'] = "Invalid indicator value.";
    error_log("update_topic.php: Invalid indicator - $indicator_input");
    header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
    exit();
}
$indicator = $indicator_map[strtolower($indicator_input)];

// Validate status
$valid_statuses = ['visible', 'hidden', 'archived'];
if (!in_array($status, $valid_statuses)) {
    $_SESSION['error'] = "Invalid status value.";
    error_log("update_topic.php: Invalid status - $status");
    header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
    exit();
}

// Handle table data
$table_data = null;
if (!empty($_POST['table_headers']) && !empty($_POST['table_data'])) {
    $table_data_new = [
        'headers' => array_filter(array_map('trim', $_POST['table_headers']), 'strlen'),
        'rows' => []
    ];
    foreach ($_POST['table_data'] as $row) {
        $filtered_row = array_filter(array_map('trim', $row), 'strlen');
        if (!empty($filtered_row)) {
            $table_data_new['rows'][] = $filtered_row;
        }
    }
    if (!empty($table_data_new['headers']) && !empty($table_data_new['rows'])) {
        $table_data = json_encode($table_data_new, JSON_UNESCAPED_UNICODE);
        error_log("update_topic.php: Table data processed - " . substr($table_data, 0, 100));
    } else {
        $table_data = null;
        error_log("update_topic.php: Invalid table data - empty headers or rows");
    }
}

// Handle media update
$media_type = $topic['media_type'];
$media_path = $topic['media_path'];
$upload_dir = 'Uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($remove_media) {
    if ($media_path && file_exists($media_path) && $topic['media_type'] !== 'external') {
        unlink($media_path);
        error_log("update_topic.php: Deleted media file - $media_path");
    }
    $media_type = null;
    $media_path = null;
} elseif (!empty($_FILES['media_file']['name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['media_file'];
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif',
        'video/mp4', 'video/mov',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    $max_size = 10 * 1024 * 1024; // 10MB

    error_log("update_topic.php: File upload - name={$file['name']}, type={$file['type']}, size={$file['size']}");

    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Allowed: jpg, png, gif, mp4, mov, pdf, doc, docx.";
        error_log("update_topic.php: Invalid file type - {$file['type']}");
        header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
        exit();
    }
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File size exceeds 10MB limit.";
        error_log("update_topic.php: File size too large - {$file['size']}");
        header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
        exit();
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'topic_' . time() . '_' . uniqid() . '.' . $ext;
    $upload_path = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $_SESSION['error'] = "Failed to upload file.";
        error_log("update_topic.php: Failed to move uploaded file to $upload_path");
        header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
        exit();
    }

    if ($media_path && file_exists($media_path) && $topic['media_type'] !== 'external') {
        unlink($media_path);
        error_log("update_topic.php: Deleted old file - $media_path");
    }

    $media_type = in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif']) ? 'image' :
                  (in_array($file['type'], ['video/mp4', 'video/mov']) ? 'video' : 'file');
    $media_path = $upload_path;
    error_log("update_topic.php: File uploaded - path=$upload_path, type=$media_type");
} elseif (!empty($external_url)) {
    if (filter_var($external_url, FILTER_VALIDATE_URL)) {
        $media_type = 'external';
        $media_path = $external_url;
        if ($media_path && file_exists($media_path) && $topic['media_type'] !== 'external') {
            unlink($media_path);
            error_log("update_topic.php: Deleted old file - $media_path");
        }
        error_log("update_topic.php: External URL set - $external_url");
    } else {
        $_SESSION['error'] = "Invalid external URL.";
        error_log("update_topic.php: Invalid external URL - $external_url");
        header("Location: edit_topic.php?topic_id=$topic_id&" . ($hospital_id ? "hospital_id=$hospital_id" : "health_center_id=$health_center_id"));
        exit();
    }
}

// Update topic in database
$stmt = $conn->prepare("UPDATE topics SET title = ?, content = ?, indicator = ?, media_type = ?, media_path = ?, table_data = ?, status = ?, hospital_id = ?, health_center_id = ? WHERE id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Prepare failed: " . $conn->error;
    error_log("update_topic.php: Prepare failed: " . $conn->error);
    $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
    header("Location: $redirect_url");
    exit();
}
$stmt->bind_param("ssissssiii", $title, $content, $indicator, $media_type, $media_path, $table_data, $status, $hospital_id, $health_center_id, $topic_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "Topic updated successfully.";
    error_log("update_topic.php: Topic updated - id=$topic_id");
} else {
    $_SESSION['error'] = "Failed to update topic: " . $stmt->error;
    error_log("update_topic.php: Update error: " . $stmt->error);
}
$stmt->close();

$redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
header("Location: $redirect_url");
exit();
?>