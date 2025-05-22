<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] !== 1) {
    $_SESSION['error'] = "Please log in as an admin.";
    header("Location: admin-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospital_id = (int)($_POST['hospital_id'] ?? 0);
    $form_type = trim($_POST['form_type'] ?? '');
    $admin_id = (int)$_SESSION['admin_id']; // Should be 1
    $allowed_types = ['safety_systems', 'employee_training', 'environmental_tours', 'evacuation_plan', 'meeting_committee', 'building_safety'];

    // Log for debugging
    error_log("upload_form.php: admin_id=$admin_id, hospital_id=$hospital_id, form_type=$form_type");

    if ($hospital_id <= 0) {
        $_SESSION['error'] = "Please select a hospital.";
    } elseif (!in_array($form_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid form type.";
    } elseif (!isset($_FILES['form_file']) || $_FILES['form_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = "Please select a file to upload.";
    } else {
        $file = $_FILES['form_file'];
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $_SESSION['error'] = "Only PDF, DOC, and DOCX files are allowed.";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $_SESSION['error'] = "File size exceeds 5MB limit.";
        } else {
            $upload_dir = '../Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . $form_type . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                error_log("upload_form.php: File moved to $file_path");
                $stmt = $conn->prepare("INSERT INTO form_uploads (hospital_id, form_type, file_path, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt === false) {
                    $_SESSION['error'] = "Error preparing query: " . $conn->error;
                    error_log("upload_form.php: Error preparing query: " . $conn->error);
                    header("Location: admin_panel.php");
                    exit();
                }
                $stmt->bind_param("issi", $hospital_id, $form_type, $file_path, $admin_id);
                if ($stmt->execute()) {
                    error_log("upload_form.php: File inserted successfully");
                    $stmt->close(); // Close the form_uploads statement
                    // Insert into audit_logs
                    $stmt = $conn->prepare("INSERT INTO audit_logs (action, target_type, details, admin_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if ($stmt === false) {
                        unlink($file_path);
                        $_SESSION['error'] = "Error preparing audit query: " . $conn->error;
                        error_log("upload_form.php: Error preparing audit query: " . $conn->error);
                        header("Location: admin_panel.php");
                        exit();
                    }
                    $action = 'upload_form';
                    $target_type = 'form_upload';
                    $details = "Admin {$admin_id} uploaded file: $file_name for form type: $form_type, hospital ID: $hospital_id";
                    $admin_id = (int)$_SESSION['admin_id'];
                    $stmt->bind_param("sssi", $action, $target_type, $details, $admin_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "File uploaded successfully.";
                    } else {
                        unlink($file_path);
                        $_SESSION['error'] = "Error logging audit: " . $stmt->error;
                        error_log("upload_form.php: Error logging audit: " . $stmt->error);
                    }
                    $stmt->close(); // Close the audit_logs statement
                } else {
                    unlink($file_path);
                    $_SESSION['error'] = "Error saving file to database: " . $stmt->error;
                    error_log("upload_form.php: Error saving file: " . $stmt->error);
                    $stmt->close(); // Close the form_uploads statement in case of error
                }
            } else {
                $_SESSION['error'] = "Error uploading file.";
                error_log("upload_form.php: Error moving uploaded file: " . $file['error']);
            }
        }
    }
}

header("Location: admin_panel.php");
exit();
?>