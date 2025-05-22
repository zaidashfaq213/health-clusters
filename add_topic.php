<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized action.";
    $redirect_url = isset($_POST['hospital_id']) ? "hospital.php?id=" . (int)$_POST['hospital_id'] : "health_center.php?id=" . (int)$_POST['health_center_id'];
    header("Location: $redirect_url");
    exit();
}

$hospital_id = isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null;
$health_center_id = isset($_POST['health_center_id']) ? (int)$_POST['health_center_id'] : null;
$title = trim($_POST['title']);
$content = trim($_POST['content']);
$indicator_input = trim($_POST['indicator']);
$media_type = null;
$media_path = null;
$table_data = null;

// Map indicator strings to integers
$indicator_map = [
    'critical' => -1,
    'normal' => 0,
    'positive' => 1
];

// Validate indicator
if (array_key_exists(strtolower($indicator_input), $indicator_map)) {
    $indicator = $indicator_map[strtolower($indicator_input)];
} elseif (is_numeric($indicator_input)) {
    $indicator = (int)$indicator_input; // Accept direct integers
} else {
    $_SESSION['error'] = "Invalid indicator value.";
    $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
    header("Location: $redirect_url");
    exit();
}

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

if ($_FILES['media_file']['size'] > 0) {
    $upload_dir = 'Uploads/';
    $file_name = basename($_FILES['media_file']['name']);
    $target_file = $upload_dir . time() . '_' . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    if ($file_type === 'jpg' || $file_type === 'png' || $file_type === 'jpeg') {
        $media_type = 'image';
    } elseif ($file_type === 'mp4' || $file_type === 'mov') {
        $media_type = 'video';
    } elseif ($file_type === 'pdf' || $file_type === 'doc' || $file_type === 'docx') {
        $media_type = 'file';
    }
    
    if ($media_type && move_uploaded_file($_FILES['media_file']['tmp_name'], $target_file)) {
        $media_path = $target_file;
    } else {
        $_SESSION['error'] = "Failed to upload file.";
        $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
        header("Location: $redirect_url");
        exit();
    }
} elseif (!empty($_POST['external_url'])) {
    $media_type = 'external';
    $media_path = filter_var($_POST['external_url'], FILTER_VALIDATE_URL);
    if (!$media_path) {
        $_SESSION['error'] = "Invalid URL provided.";
        $redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
        header("Location: $redirect_url");
        exit();
    }
}

if (!empty($_POST['table_headers']) && !empty($_POST['table_data'])) {
    $table_data = [
        'headers' => array_filter($_POST['table_headers'], 'trim'),
        'rows' => []
    ];
    foreach ($_POST['table_data'] as $row) {
        $filtered_row = array_filter($row, 'trim');
        if (!empty($filtered_row)) {
            $table_data['rows'][] = $filtered_row;
        }
    }
    $table_data = json_encode($table_data);
}

$stmt = $conn->prepare("INSERT INTO topics (hospital_id, health_center_id, title, content, indicator, media_type, media_path, table_data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'visible')");
$stmt->bind_param("iissssss", $hospital_id, $health_center_id, $title, $content, $indicator, $media_type, $media_path, $table_data);
if ($stmt->execute()) {
    $_SESSION['success'] = "Topic added successfully.";
} else {
    $_SESSION['error'] = "Failed to add topic: " . $conn->error;
}
$stmt->close();

$redirect_url = $hospital_id ? "hospital.php?id=$hospital_id" : "health_center.php?id=$health_center_id";
header("Location: $redirect_url");
exit();
?>