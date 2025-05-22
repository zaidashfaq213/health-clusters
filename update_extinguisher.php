<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: index.php");
    exit();
}

$hospital_id = isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : NULL;
$health_center_id = isset($_POST['health_center_id']) ? (int)$_POST['health_center_id'] : NULL;
$extinguisher_id = isset($_POST['extinguisher_id']) ? (int)$_POST['extinguisher_id'] : 0;
$code = trim($_POST['code'] ?? '');
$location = trim($_POST['location'] ?? '');
$type = trim($_POST['type'] ?? '');
$status = trim($_POST['status'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
$remove_qr_code = isset($_POST['remove_qr_code']) && $_POST['remove_qr_code'] == '1';

// Validate inputs
if ($extinguisher_id <= 0 || ($hospital_id === NULL && $health_center_id === NULL)) {
    $_SESSION['error'] = "Invalid facility or extinguisher ID.";
    error_log("update_extinguisher.php: Invalid inputs: extinguisher_id=$extinguisher_id, hospital_id=$hospital_id, health_center_id=$health_center_id");
    header("Location: index.php");
    exit();
}

if (empty($code) || empty($location) || empty($type) || empty($status)) {
    $_SESSION['error'] = "Code, location, type, and status are required.";
    header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
    exit();
}

if (!in_array($type, ['Water', 'Foam', 'CO2', 'Dry Powder'])) {
    $_SESSION['error'] = "Invalid extinguisher type.";
    header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
    exit();
}

if (!in_array($status, ['Green', 'Red'])) {
    $_SESSION['error'] = "Invalid status.";
    header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
    exit();
}

// Determine facility type
$facility_type = $hospital_id !== NULL ? 'hospital' : 'health_center';
$facility_id = $hospital_id !== NULL ? $hospital_id : $health_center_id;

// Verify manager's facility affiliation
$stmt = $conn->prepare("SELECT hospital_id FROM users WHERE id = ? AND role = 'manager'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($hospital_id !== NULL && $user['hospital_id'] != $hospital_id) {
    $_SESSION['error'] = "You are not authorized to manage this hospital.";
    header("Location: hospital.php?id=$hospital_id");
    exit();
}
// Note: Add health_center_id check if users table supports it

// Fetch current extinguisher details
$stmt = $conn->prepare("SELECT image_url, qr_code_url FROM fireextinguishers WHERE id = ? AND (hospital_id = ? OR health_center_id = ?)");
$stmt->bind_param("iii", $extinguisher_id, $hospital_id, $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("update_extinguisher.php: Database error for extinguisher_id=$extinguisher_id: " . $conn->error);
    header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
    exit();
}
$current_extinguisher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_extinguisher) {
    $_SESSION['error'] = "Extinguisher not found.";
    error_log("update_extinguisher.php: Extinguisher not found for id=$extinguisher_id");
    header("Location: extinguishers.php?{$facility_type}_id=$facility_id");
    exit();
}

// Handle file uploads
$image_url = $current_extinguisher['image_url'];
$qr_code_url = $current_extinguisher['qr_code_url'];
$upload_dir = 'Uploads/extinguishers/';
$qr_upload_dir = 'Uploads/qrcodes/';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (!is_dir($qr_upload_dir)) {
    mkdir($qr_upload_dir, 0777, true);
}

// Process image upload
if (!empty($_FILES['image']['name'])) {
    $file_name = time() . '_' . basename($_FILES['image']['name']);
    $file_path = $upload_dir . $file_name;
    $file_type = mime_content_type($_FILES['image']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid image format. Only JPEG, PNG, or GIF allowed.";
        header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
        exit();
    }
    if ($_FILES['image']['size'] > $max_size) {
        $_SESSION['error'] = "Image size exceeds 5MB.";
        header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
        exit();
    }
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
        $_SESSION['error'] = "Failed to upload image.";
        error_log("update_extinguisher.php: Failed to upload image for extinguisher_id=$extinguisher_id");
        header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
        exit();
    }
    $image_url = $file_path;
} elseif ($remove_image && $current_extinguisher['image_url']) {
    if (file_exists($current_extinguisher['image_url'])) {
        unlink($current_extinguisher['image_url']);
    }
    $image_url = null;
}

// Process QR code upload
if (!empty($_FILES['qr_code']['name'])) {
    $qr_file_name = time() . '_' . basename($_FILES['qr_code']['name']);
    $qr_file_path = $qr_upload_dir . $qr_file_name;
    $qr_file_type = mime_content_type($_FILES['qr_code']['tmp_name']);

    if (!in_array($qr_file_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid QR code image format. Only JPEG, PNG, or GIF allowed.";
        // Clean up uploaded image if any
        if ($image_url && $image_url != $current_extinguisher['image_url'] && file_exists($image_url)) {
            unlink($image_url);
        }
        header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
        exit();
    }
    if ($_FILES['qr_code']['size'] > $max_size) {
        $_SESSION['error'] = "QR code image size exceeds 5MB.";
        // Clean up uploaded image if any
        if ($image_url && $image_url != $current_extinguisher['image_url'] && file_exists($image_url)) {
            unlink($image_url);
        }
        header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
        exit();
    }
    if (!move_uploaded_file($_FILES['qr_code']['tmp_name'], $qr_file_path)) {
        $_SESSION['error'] = "Failed to upload QR code image.";
        error_log("update_extinguisher.php: Failed to upload QR code for extinguisher_id=$extinguisher_id");
        // Clean up uploaded image if any
        if ($image_url && $image_url != $current_extinguisher['image_url'] && file_exists($image_url)) {
            unlink($image_url);
        }
        header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
        exit();
    }
    $qr_code_url = $qr_file_path;
} elseif ($remove_qr_code && $current_extinguisher['qr_code_url']) {
    if (file_exists($current_extinguisher['qr_code_url'])) {
        unlink($current_extinguisher['qr_code_url']);
    }
    $qr_code_url = null;
}

// Update extinguisher in database
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE fireextinguishers SET code = ?, location = ?, type = ?, status = ?, notes = ?, image_url = ?, qr_code_url = ? WHERE id = ? AND (hospital_id = ? OR health_center_id = ?)");
    $stmt->bind_param("sssssssiis", $code, $location, $type, $status, $notes, $image_url, $qr_code_url, $extinguisher_id, $hospital_id, $health_center_id);
    if (!$stmt->execute()) {
        throw new Exception("Error updating extinguisher: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();
    $_SESSION['success'] = "Extinguisher updated successfully.";
    header("Location: extinguishers.php?{$facility_type}_id=$facility_id");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    error_log("update_extinguisher.php: Transaction failed for extinguisher_id=$extinguisher_id: " . $e->getMessage());
    // Clean up uploaded files if transaction fails
    if ($image_url && $image_url != $current_extinguisher['image_url'] && file_exists($image_url)) {
        unlink($image_url);
    }
    if ($qr_code_url && $qr_code_url != $current_extinguisher['qr_code_url'] && file_exists($qr_code_url)) {
        unlink($qr_code_url);
    }
    header("Location: edit_extinguisher.php?id=$extinguisher_id&{$facility_type}_id=$facility_id");
    exit();
}

$conn->close();
?>