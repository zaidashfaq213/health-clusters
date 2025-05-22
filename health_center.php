<?php
session_start();
require_once './includes/config.php';

// Debugging: Log session data
error_log("health_center.php: Session Data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    error_log("health_center.php: User not logged in, redirecting to login.php");
    header("Location: login.php");
    exit();
}

// Verify user exists in users table
$stmt = $conn->prepare("SELECT id, username, profile_picture, work_number, health_center_id, role FROM users WHERE id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error: Unable to prepare user query.";
    error_log("health_center.php: Prepare failed for user query: " . $conn->error);
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: Unable to execute user query.";
    error_log("health_center.php: Execute failed for user query: " . $stmt->error);
    $stmt->close();
    header("Location: login.php");
    exit();
}
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    $_SESSION['error'] = "User not found.";
    error_log("health_center.php: User not found for user_id={$_SESSION['user_id']}, redirecting to login.php");
    session_destroy();
    header("Location: login.php");
    exit();
}

// Clear any residual error message after successful user validation
unset($_SESSION['error']);

// Validate health center ID
$health_center_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($health_center_id <= 0) {
    error_log("health_center.php: Invalid health center ID: $health_center_id, redirecting to index.php");
    header("Location: index.php");
    exit();
}

// Fetch health center details
$stmt = $conn->prepare("SELECT name FROM health_centers WHERE id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error: Unable to prepare health center query.";
    error_log("health_center.php: Prepare failed for health center query: " . $conn->error);
    header("Location: index.php");
    exit();
}
$stmt->bind_param("i", $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: Unable to execute health center query.";
    error_log("health_center.php: Execute failed for health center query: " . $stmt->error);
    $stmt->close();
    header("Location: index.php");
    exit();
}
$health_center = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$health_center) {
    $_SESSION['error'] = "Health center not found.";
    error_log("health_center.php: Health center not found for health_center_id=$health_center_id, redirecting to index.php");
    header("Location: index.php");
    exit();
}

// Fetch manager's health center name for profile section
$display_health_center_name = $health_center['name'];
if ($user['role'] === 'manager' && $user['health_center_id']) {
    $stmt = $conn->prepare("SELECT name FROM health_centers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user['health_center_id']);
        if ($stmt->execute()) {
            $manager_health_center = $stmt->get_result()->fetch_assoc();
            if ($manager_health_center) {
                $display_health_center_name = $manager_health_center['name'];
            }
        } else {
            error_log("health_center.php: Execute failed for manager health center query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("health_center.php: Prepare failed for manager health center query: " . $conn->error);
    }
}

// Clear error message again after successful health center validation
unset($_SESSION['error']);

// Notification creation function
function createNotification($conn, $health_center_id, $type, $message, $alarm_id = null, $extinguisher_id = null, $due_date = null) {
    $status = 'pending';
    $stmt = $conn->prepare("INSERT INTO notifications (type, alarm_id, extinguisher_id, health_center_id, message, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("siissss", $type, $alarm_id, $extinguisher_id, $health_center_id, $message, $due_date, $status);
        if (!$stmt->execute()) {
            error_log("health_center.php: Failed to create notification for type=$type, alarm_id=$alarm_id, extinguisher_id=$extinguisher_id: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("health_center.php: Prepare failed for notification insert: " . $conn->error);
    }
}

// Handle profile picture upload/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile_picture'])) {
    if (!empty($_FILES['profile_picture']['name']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'Uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_name = time() . '_' . uniqid() . '.' . pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $file_path = $upload_dir . $file_name;
        $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid image format. Only JPEG, PNG, or GIF allowed.";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $_SESSION['error'] = "Image size exceeds 2MB.";
        } elseif (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
            $_SESSION['error'] = "Failed to upload image.";
            error_log("health_center.php: Failed to upload profile picture for user_id={$_SESSION['user_id']}");
        } else {
            // Delete old profile picture if exists
            if ($user['profile_picture'] && file_exists($user['profile_picture'])) {
                unlink($user['profile_picture']);
            }
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $file_path, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Profile picture updated successfully.";
                    $user['profile_picture'] = $file_path; // Update local user data
                } else {
                    $_SESSION['error'] = "Error updating profile picture: " . $stmt->error;
                    error_log("health_center.php: Error updating profile picture for user_id={$_SESSION['user_id']}: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Database error: Unable to prepare profile picture update query.";
                error_log("health_center.php: Prepare failed for profile picture update: " . $conn->error);
            }
        }
    } else {
        $_SESSION['error'] = "Please select an image to upload.";
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle profile edit (username and work number)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_work_number = trim($_POST['work_number'] ?? '');

    if (empty($new_username)) {
        $_SESSION['error'] = "Username is required.";
    } else {
        // Check if username is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_username, $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Username is already taken.";
            } else {
                // Update username and work number
                $update_stmt = $conn->prepare("UPDATE users SET username = ?, work_number = ? WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("ssi", $new_username, $new_work_number, $_SESSION['user_id']);
                    if ($update_stmt->execute()) {
                        $_SESSION['success'] = "Profile updated successfully.";
                        $user['username'] = $new_username;
                        $user['work_number'] = $new_work_number;
                        // Log the action
                        $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                        if ($log_stmt) {
                            $action = 'edit_profile';
                            $details = "Updated profile: username=$new_username, work_number=$new_work_number";
                            $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    } else {
                        $_SESSION['error'] = "Error updating profile: " . $update_stmt->error;
                        error_log("health_center.php: Error updating profile for user_id={$_SESSION['user_id']}: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                } else {
                    $_SESSION['error'] = "Database error: Unable to prepare profile update query.";
                    error_log("health_center.php: Prepare failed for profile update: " . $conn->error);
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: Unable to prepare username check query.";
            error_log("health_center.php: Prepare failed for username check: " . $conn->error);
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle alarm submission (manager only, own health center)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_alarm']) && $user['role'] === 'manager' && $user['health_center_id'] == $health_center_id) {
    $alarm_type = trim($_POST['alarm_type'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if (empty($alarm_type) || empty($location) || empty($status)) {
        $_SESSION['error'] = "Alarm type, location, and status are required.";
    } elseif (!in_array($status, ['Active', 'Resolved'])) {
        $_SESSION['error'] = "Invalid status.";
    } else {
        $stmt = $conn->prepare("INSERT INTO alarms (health_center_id, alarm_type, location, status, alarm_time) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $health_center_id, $alarm_type, $location, $status);
            if ($stmt->execute()) {
                $alarm_id = $conn->insert_id;
                // Log the action
                $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                if ($log_stmt) {
                    $action = 'add_alarm';
                    $details = "Added alarm: $alarm_type at $location, status: $status";
                    $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                // Create notification for active alarms
                if ($status === 'Active') {
                    $message = "New active fire alarm: $alarm_type at $location in {$health_center['name']}.";
                    $due_date = date('Y-m-d', strtotime('+1 day'));
                    createNotification($conn, $health_center_id, 'alarm', $alarm_id, null, $message, $due_date);
                }
                $_SESSION['success'] = "Alarm added successfully.";
            } else {
                $_SESSION['error'] = "Error adding alarm: " . $stmt->error;
                error_log("health_center.php: Error adding alarm: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: Unable to prepare alarm insert query.";
            error_log("health_center.php: Prepare failed for alarm insert: " . $conn->error);
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle alarm status update (manager only, own health center)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_alarm_status']) && $user['role'] === 'manager' && $user['health_center_id'] == $health_center_id) {
    $alarm_id = (int)($_POST['alarm_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    if ($alarm_id <= 0) {
        $_SESSION['error'] = "Invalid alarm ID.";
    } elseif (!in_array($new_status, ['Active', 'Resolved'])) {
        $_SESSION['error'] = "Invalid status.";
    } else {
        $stmt = $conn->prepare("UPDATE alarms SET status = ? WHERE id = ? AND health_center_id = ?");
        if ($stmt) {
            $stmt->bind_param("sii", $new_status, $alarm_id, $health_center_id);
            if ($stmt->execute()) {
                $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                if ($log_stmt) {
                    $action = 'update_alarm_status';
                    $details = "Updated alarm ID: $alarm_id to status: $new_status";
                    $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                // Create notification for status change to Active
                if ($new_status === 'Active') {
                    $message = "Fire alarm ID: $alarm_id status updated to Active in {$health_center['name']}.";
                    $due_date = date('Y-m-d', strtotime('+1 day'));
                    createNotification($conn, $health_center_id, 'alarm', $alarm_id, null, $message, $due_date);
                }
                $_SESSION['success'] = "Alarm status updated successfully.";
            } else {
                $_SESSION['error'] = "Error updating alarm status: " . $stmt->error;
                error_log("health_center.php: Error updating alarm ID=$alarm_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: Unable to prepare alarm status update query.";
            error_log("health_center.php: Prepare failed for alarm status update: " . $conn->error);
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle alarm deletion (manager only, own health center)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_alarm']) && $user['role'] === 'manager' && $user['health_center_id'] == $health_center_id) {
    $alarm_id = (int)($_POST['alarm_id'] ?? 0);

    if ($alarm_id <= 0) {
        $_SESSION['error'] = "Invalid alarm ID.";
    } else {
        $stmt = $conn->prepare("DELETE FROM alarms WHERE id = ? AND health_center_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $alarm_id, $health_center_id);
            if ($stmt->execute()) {
                $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                if ($log_stmt) {
                    $action = 'delete_alarm';
                    $details = "Deleted alarm ID: $alarm_id";
                    $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                // Create notification for deletion
                $message = "Fire alarm ID: $alarm_id deleted in {$health_center['name']}.";
                createNotification($conn, $health_center_id, 'alarm', $alarm_id, null, $message);
                $_SESSION['success'] = "Alarm deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting alarm: " . $stmt->error;
                error_log("health_center.php: Error deleting alarm ID=$alarm_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: Unable to prepare alarm delete query.";
            error_log("health_center.php: Prepare failed for alarm delete: " . $conn->error);
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle extinguisher submission (manager only, own health center)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_extinguisher']) && $user['role'] === 'manager' && $user['health_center_id'] == $health_center_id) {
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $last_inspection = trim($_POST['last_inspection'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '');

    // Hardcode Fire Safety sub-section ID (Fire Safety, ID 5)
    $section_id = 5;
    $hospital_id = null; // Set hospital_id to NULL for health centers

    if (empty($code) || empty($location) || empty($type) || empty($status)) {
        $_SESSION['error'] = "Code, location, type, and status are required.";
    } elseif ($last_inspection && !validateDate($last_inspection)) {
        $_SESSION['error'] = "Invalid last inspection date. Use YYYY-MM-DD format.";
    } else {
        $image_url = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/extinguishers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024;
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $file_path = $upload_dir . $file_name;
            $file_type = mime_content_type($_FILES['image']['tmp_name']);

            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = "Invalid image format. Only JPEG, PNG, or GIF allowed.";
            } elseif ($_FILES['image']['size'] > $max_size) {
                $_SESSION['error'] = "Image size exceeds 5MB.";
            } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $_SESSION['error'] = "Failed to upload image.";
                error_log("health_center.php: Failed to upload extinguisher image for code=$code");
            } else {
                $image_url = $file_path;
            }
        }

        if (!isset($_SESSION['error'])) {
            // Check for duplicate code
            $stmt = $conn->prepare("SELECT id FROM fireextinguishers WHERE code = ?");
            if ($stmt) {
                $stmt->bind_param("s", $code);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $_SESSION['error'] = "Code is already in use.";
                    $stmt->close();
                    header("Location: health_center.php?id=$health_center_id");
                    exit();
                }
                $stmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO fireextinguishers (health_center_id, section_id, hospital_id, code, location, type, status, last_inspection, notes, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiisssssss", $health_center_id, $section_id, $hospital_id, $code, $location, $type, $status, $last_inspection, $notes, $image_url);
                if ($stmt->execute()) {
                    $extinguisher_id = $conn->insert_id;
                    $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                    if ($log_stmt) {
                        $action = 'add_extinguisher';
                        $details = "Added extinguisher: $code";
                        $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    // Create notification for Red status
                    if ($status === 'Red') {
                        $message = "New fire extinguisher $code needs maintenance at $location in {$health_center['name']}.";
                        $due_date = $last_inspection ? date('Y-m-d', strtotime($last_inspection . '+1 year')) : date('Y-m-d', strtotime('+30 days'));
                        createNotification($conn, $health_center_id, 'extinguisher', null, $extinguisher_id, $message, $due_date);
                    }
                    $_SESSION['success'] = "Extinguisher added successfully.";
                } else {
                    $_SESSION['error'] = "Error adding extinguisher: " . $stmt->error;
                    error_log("health_center.php: Error adding extinguisher code=$code: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Database error: Unable to prepare extinguisher insert query.";
                error_log("health_center.php: Prepare failed for extinguisher insert: " . $conn->error);
            }
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle section submission (manager only, own health center)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section']) && $user['role'] === 'manager' && $user['health_center_id'] == $health_center_id) {
    $section_name = trim($_POST['section_name'] ?? '');
    $parent_section_id = (int)($_POST['parent_section_id'] ?? 0);
    if (empty($section_name)) {
        $_SESSION['error'] = "Section name is required.";
    } elseif ($parent_section_id <= 0) {
        $_SESSION['error'] = "Invalid parent section selected.";
    } else {
        // Check if parent section exists
        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $parent_section_id);
            $stmt->execute();
            $parent_section = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$parent_section) {
                $_SESSION['error'] = "Parent section does not exist.";
            } else {
                // Insert new sub-section
                $insert_stmt = $conn->prepare("INSERT INTO sections (name, parent_section_id, created_by) VALUES (?, ?, 'manager')");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("si", $section_name, $parent_section_id);
                    if ($insert_stmt->execute()) {
                        $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                        if ($log_stmt) {
                            $action = 'add_section';
                            $details = "Added section: $section_name under parent ID: $parent_section_id";
                            $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                        $_SESSION['success'] = "Section added successfully.";
                    } else {
                        $_SESSION['error'] = "Failed to add sub-section.";
                        error_log("health_center.php: Failed to insert section: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                } else {
                    $_SESSION['error'] = "Database error: Unable to prepare insert query.";
                    error_log("health_center.php: Prepare failed for section insert: " . $conn->error);
                }
            }
        } else {
            $_SESSION['error'] = "Database error: Unable to verify parent section.";
            error_log("health_center.php: Prepare failed for parent section check: " . $conn->error);
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle topic submission (any logged-in user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_topic'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $indicator_input = trim($_POST['indicator'] ?? '');
    $section_id = (int)($_POST['section_id'] ?? 0);
    $media_type = null;
    $media_path = null;
    $table_data = null;

    $indicator_map = [
        'critical' => -1,
        'normal' => 0,
        'positive' => 1
    ];

    if (array_key_exists(strtolower($indicator_input), $indicator_map)) {
        $indicator = $indicator_map[strtolower($indicator_input)];
    } else {
        $_SESSION['error'] = "Invalid indicator value.";
        header("Location: health_center.php?id=$health_center_id");
        exit();
    }

    if (empty($title)) {
        $_SESSION['error'] = "Topic title is required.";
    } elseif ($section_id <= 0) {
        $_SESSION['error'] = "Invalid section selected.";
    } else {
        // Verify section_id exists
        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                $_SESSION['error'] = "Invalid section selected.";
                $stmt->close();
                header("Location: health_center.php?id=$health_center_id");
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: Unable to prepare section check query.";
            error_log("health_center.php: Prepare failed for section check: " . $conn->error);
            header("Location: health_center.php?id=$health_center_id");
            exit();
        }

        // Handle file upload
        $upload_dir = 'Uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!empty($_FILES['media_file']['name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['media_file'];
            $allowed_types = [
                'image/jpeg', 'image/png', 'image/gif',
                'video/mp4', 'video/mov',
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $max_size = 10 * 1024 * 1024;

            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error'] = "Invalid file type. Allowed: jpg, png, gif, mp4, mov, pdf, doc, docx.";
                error_log("health_center.php: Invalid file type - {$file['type']}");
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = "File size exceeds 10MB limit.";
                error_log("health_center.php: File size too large - {$file['size']}");
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'topic_' . time() . '_' . uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
                        $media_type = 'image';
                    } elseif (in_array($file['type'], ['video/mp4', 'video/mov'])) {
                        $media_type = 'video';
                    } else {
                        $media_type = 'file';
                    }
                    $media_path = $upload_path;
                    error_log("health_center.php: File uploaded - path=$upload_path, type=$media_type");
                } else {
                    $_SESSION['error'] = "Failed to upload file.";
                    error_log("health_center.php: Failed to move uploaded file to $upload_path");
                }
            }
        } elseif (!empty($_POST['external_url'])) {
            $external_url = trim($_POST['external_url']);
            if (filter_var($external_url, FILTER_VALIDATE_URL)) {
                $media_type = 'external';
                $media_path = $external_url;
                error_log("health_center.php: External URL set - $external_url");
            } else {
                $_SESSION['error'] = "Invalid external URL.";
                error_log("health_center.php: Invalid external URL - $external_url");
            }
        }

        // Handle table data
        if (!empty($_POST['table_headers']) && !empty($_POST['table_data'])) {
            $table_data = [
                'headers' => array_filter(array_map('trim', $_POST['table_headers']), 'strlen'),
                'rows' => []
            ];
            foreach ($_POST['table_data'] as $row) {
                $filtered_row = array_filter(array_map('trim', $row), 'strlen');
                if (!empty($filtered_row)) {
                    $table_data['rows'][] = $filtered_row;
                }
            }
            if (!empty($table_data['headers']) && !empty($table_data['rows'])) {
                $table_data = json_encode($table_data);
            } else {
                $table_data = null;
            }
        }

        if (!isset($_SESSION['error'])) {
            $stmt = $conn->prepare("INSERT INTO topics (health_center_id, section_id, user_id, title, content, indicator, media_type, media_path, table_data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'visible')");
            if ($stmt) {
                $stmt->bind_param("iiissssss", $health_center_id, $section_id, $_SESSION['user_id'], $title, $content, $indicator, $media_type, $media_path, $table_data);
                if ($stmt->execute()) {
                    $topic_id = $conn->insert_id;
                    $log_stmt = $conn->prepare("INSERT INTO manager_actions (health_center_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                    if ($log_stmt) {
                        $action = 'add_topic';
                        $details = "Added topic: $title in section ID: $section_id";
                        $log_stmt->bind_param("iiss", $health_center_id, $_SESSION['user_id'], $action, $details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    $_SESSION['success'] = "Topic added successfully.";
                } else {
                    $_SESSION['error'] = "Error adding topic: " . $stmt->error;
                    error_log("health_center.php: Error adding topic for health_center_id=$health_center_id, title=$title: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Database error: Unable to prepare topic insert query.";
                error_log("health_center.php: Prepare failed for topic insert: " . $conn->error);
            }
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Handle mark notification as acknowledged (manager only, own health center)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_acknowledged']) && $user['role'] === 'manager' && $user['health_center_id'] == $health_center_id) {
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    if ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET status = 'acknowledged' WHERE id = ? AND health_center_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $notification_id, $health_center_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Notification marked as acknowledged.";
            } else {
                $_SESSION['error'] = "Error marking notification as acknowledged: " . $stmt->error;
                error_log("health_center.php: Error marking notification ID=$notification_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: Unable to prepare notification update query.";
            error_log("health_center.php: Prepare failed for notification update: " . $conn->error);
        }
    }
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Verify manager role and health center access
$is_manager = $user['role'] === 'manager';
$is_own_health_center = $is_manager && $user['health_center_id'] == $health_center_id;

// Fetch topic count and performance score
$stmt = $conn->prepare("SELECT COUNT(*) as topic_count FROM topics WHERE health_center_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    $topic_count = $stmt->get_result()->fetch_assoc()['topic_count'];
    $stmt->close();
} else {
    $topic_count = 0;
    error_log("health_center.php: Prepare failed for topic count query: " . $conn->error);
}

$stmt = $conn->prepare("SELECT MAX(topic_count) as max_topics FROM (SELECT COUNT(*) as topic_count FROM topics GROUP BY health_center_id) as counts");
if ($stmt) {
    $stmt->execute();
    $max_topics = $stmt->get_result()->fetch_assoc()['max_topics'] ?? 1;
    $stmt->close();
} else {
    $max_topics = 1;
    error_log("health_center.php: Prepare failed for max topics query: " . $conn->error);
}
$performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;

// Fetch sections (main sections: Safety, Security, Incidents)
$sections = [];
$stmt = $conn->prepare("SELECT id, name, parent_section_id, is_fixed FROM sections WHERE parent_section_id IS NULL AND name IN ('Safety', 'Security', 'Incidents') ORDER BY name");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($section = $result->fetch_assoc()) {
        $section['sub_sections'] = [];
        $sub_stmt = $conn->prepare("SELECT id, name, is_fixed FROM sections WHERE parent_section_id = ? ORDER BY name");
        if ($sub_stmt) {
            $sub_stmt->bind_param("i", $section['id']);
            $sub_stmt->execute();
            $section['sub_sections'] = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $sub_stmt->close();
        }
        $sections[] = $section;
    }
    $stmt->close();
} else {
    error_log("health_center.php: Prepare failed for sections query: " . $conn->error);
}

// If no sections exist, display a message
if (empty($sections)) {
    $_SESSION['error'] = "No sections configured.";
    error_log("health_center.php: No sections found, redirecting to index.php");
    header("Location: index.php");
    exit();
}

// Fetch Fire Safety sub-section ID (Fire Safety, ID 5)
$fire_safety_section_id = 5;

// Fetch topics grouped by user
$topics_by_user = [];
$stmt = $conn->prepare("SELECT t.id, t.title, t.content, t.indicator, t.media_type, t.media_path, t.table_data, t.status, t.created_at, t.updated_at, t.section_id, u.id as user_id, u.username, u.profile_picture 
                        FROM topics t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.health_center_id = ? AND t.status != 'archived' 
                        ORDER BY t.created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    $topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($topics as $topic) {
        $topics_by_user[$topic['user_id']][] = $topic;
    }
} else {
    error_log("health_center.php: Prepare failed for topics query: " . $conn->error);
}

// Fetch archived topics (manager only, own health center)
$archive_topics = [];
if ($is_manager && $is_own_health_center) {
    $stmt = $conn->prepare("SELECT t.id, t.title, t.content, t.indicator, t.media_type, t.media_path, t.table_data, t.status, t.created_at, t.updated_at, u.username, u.profile_picture 
                            FROM topics t 
                            JOIN users u ON t.user_id = u.id 
                            WHERE t.health_center_id = ? AND t.status = 'archived' 
                            ORDER BY t.id DESC");
    if ($stmt) {
        $stmt->bind_param("i", $health_center_id);
        $stmt->execute();
        $archive_topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("health_center.php: Prepare failed for archived topics query: " . $conn->error);
    }
}

// Fetch extinguishers for the health center
$stmt = $conn->prepare("SELECT id, code, location, type, status, last_inspection, notes, image_url 
                        FROM fireextinguishers 
                        WHERE health_center_id = ? AND section_id = ? 
                        ORDER BY code");
if ($stmt) {
    $stmt->bind_param("ii", $health_center_id, $fire_safety_section_id);
    $stmt->execute();
    $extinguishers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $extinguishers = [];
    error_log("health_center.php: Prepare failed for extinguishers query: " . $conn->error);
}

// Calculate extinguisher statistics
$extinguisher_stats = [
    'total' => count($extinguishers),
    'green' => 0,
    'red' => 0,
    'inspected' => 0,
    'due' => 0
];
foreach ($extinguishers as $ext) {
    if ($ext['status'] === 'Green') {
        $extinguisher_stats['green']++;
    } elseif ($ext['status'] === 'Red') {
        $extinguisher_stats['red']++;
    }
    if ($ext['last_inspection']) {
        $extinguisher_stats['inspected']++;
    } else {
        $extinguisher_stats['due']++;
    }
}

// Fetch alarms for the health center
$alarms = [];
$stmt = $conn->prepare("SELECT id, alarm_type, location, status, alarm_time FROM alarms WHERE health_center_id = ? ORDER BY alarm_time DESC");
if ($stmt) {
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    $alarms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("health_center.php: Prepare failed for alarms query: " . $conn->error);
}

// Calculate alarm statistics
$alarm_stats = [
    'total' => count($alarms),
    'active' => 0,
    'resolved' => 0
];
foreach ($alarms as $alarm) {
    if ($alarm['status'] === 'Active') {
        $alarm_stats['active']++;
    } elseif ($alarm['status'] === 'Resolved') {
        $alarm_stats['resolved']++;
    }
}

// Fetch pinned topics
$pinned_topics = [];
$stmt = $conn->prepare("SELECT topic_id FROM pinned_topics WHERE user_id = ? AND health_center_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $_SESSION['user_id'], $health_center_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pinned_topics[] = $row['topic_id'];
    }
    $stmt->close();
} else {
    error_log("health_center.php: Prepare failed for pinned topics query: " . $conn->error);
}

// Fetch notifications (manager only, own health center)
$notifications = [];
$notification_count = 0;
if ($is_manager && $is_own_health_center) {
    $stmt = $conn->prepare("SELECT n.id, n.type, n.message, n.status, n.created_at, n.due_date, n.alarm_id, n.extinguisher_id, 
                            a.alarm_type, a.location AS alarm_location, 
                            e.code AS extinguisher_code, e.location AS extinguisher_location 
                            FROM notifications n 
                            LEFT JOIN alarms a ON n.alarm_id = a.id AND n.type = 'alarm'
                            LEFT JOIN fireextinguishers e ON n.extinguisher_id = e.id AND n.type = 'extinguisher'
                            WHERE n.health_center_id = ?
                            ORDER BY n.created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $health_center_id);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $notification_count = count(array_filter($notifications, function($n) { return $n['status'] === 'pending'; }));
    } else {
        error_log("health_center.php: Prepare failed for notifications query: " . $conn->error);
    }
}

// Function to validate date
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date && $d->format('Y') >= 1900 && $d->format('Y') <= (date('Y') + 10);
}

// Get section name by ID
function getSectionName($sections, $section_id) {
    foreach ($sections as $section) {
        if ($section['id'] == $section_id) {
            return $section['name'];
        }
        foreach ($section['sub_sections'] as $sub_section) {
            if ($sub_section['id'] == $section_id) {
                return $sub_section['name'];
            }
        }
    }
    return 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($health_center['name'], ENT_QUOTES, 'UTF-8'); ?> - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="/assets/css/hospital.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container container">
            <div class="logo-circle">
                <img src="assets/images/logo.png" alt="Northern Borders Health Cluster Logo">
            </div>
            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#hospitals">Hospitals</a></li>
                <li><a href="index.php#health-centers">Health Centers</a></li>
                 <?php if ($user['role'] === 'manager'): ?>
                      <li><a href="messages.php" class="hover:underline">Chat</a></li>
                  <?php endif; ?>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile-section">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-picture-container">
                            <img src="<?php echo htmlspecialchars($user['profile_picture'] ?: 'assets/images/default_user.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" class="profile-picture">
                        </div>
                        <div class="profile-info">
                            <h4><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p><strong>Health Center:</strong> <?php echo htmlspecialchars($display_health_center_name, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Work Number:</strong> <?php echo htmlspecialchars($user['work_number'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Role:</strong> <?php echo $user['role'] === 'manager' ? 'Manager' : 'User'; ?></p>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="action-btn primary-btn" data-section="profile-picture-form"><i class="fas fa-camera"></i> Upload Profile Picture</button>
                        <button class="action-btn primary-btn" data-section="edit-profile-form"><i class="fas fa-edit"></i> Edit Profile</button>
                        <button class="action-btn primary-btn" data-section="topic-form"><i class="fas fa-plus"></i> Add Topic</button>
                        <?php if ($is_manager && $is_own_health_center): ?>
                            <button class="action-btn primary-btn" data-section="extinguisher-form"><i class="fas fa-fire-extinguisher"></i> Add Extinguisher</button>
                            <button class="action-btn primary-btn" data-section="section-form"><i class="fas fa-folder-plus"></i> Add Sub-Section</button>
                        <?php endif; ?>
                        <button class="action-btn primary-btn" data-section="dashboard"><i class="fas fa-tachometer-alt"></i> View Dashboard</button>
                    </div>
                    <!-- Notification Section -->
                    <?php if ($is_manager && $is_own_health_center): ?>
                        <div class="notification-section">
                            <div class="notification-toggle">
                                <span>Notifications</span>
                                <span class="notification-count"><?php echo $notification_count; ?></span>
                            </div>
                            <div class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-item">No notifications.</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo $notification['status'] === 'pending' ? 'unread' : ''; ?>">
                                            <p><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($notification['type']), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php if ($notification['type'] === 'alarm' && $notification['alarm_type'] && $notification['alarm_location']): ?>
                                                <p><strong>Alarm:</strong> <?php echo htmlspecialchars($notification['alarm_type'] . ' at ' . $notification['alarm_location'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php elseif ($notification['type'] === 'extinguisher' && $notification['extinguisher_code'] && $notification['extinguisher_location']): ?>
                                                <p><strong>Extinguisher:</strong> <?php echo htmlspecialchars($notification['extinguisher_code'] . ' at ' . $notification['extinguisher_location'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Due Date:</strong> <?php echo $notification['due_date'] ? date('M d, Y', strtotime($notification['due_date'])) : 'N/A'; ?></p>
                                            <p><small>Created: <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small></p>
                                            <?php if ($notification['status'] === 'pending'): ?>
                                                <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST">
                                                    <input type="hidden" name="mark_notification_acknowledged" value="1">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit">Mark as Acknowledged</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Feedback Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <!-- Tabs Navigation -->
                <div class="tabs-navigation">
                    <button class="tab-btn active" data-tab="details">Details</button>
                    <button class="tab-btn" data-tab="forms">Forms</button>
                    <button class="tab-btn" data-tab="alarms">Fire Alarms</button>
                </div>

                <!-- Forms Section -->
                <div class="tab-content" id="forms">
                    <section class="forms-section" id="forms-section" style="display: none;">
                        <!-- Profile Picture Form -->
                        <div class="form-content" id="profile-picture-form" style="display: none;">
                            <h3><?php echo $user['profile_picture'] ? 'Change Profile Picture' : 'Upload Profile Picture'; ?></h3>
                            <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="upload_profile_picture" value="1">
                                <div class="form-group">
                                    <label for="profile_picture">Choose Image (JPEG, PNG, GIF, max 2MB)</label>
                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                                </div>
                                <button type="submit" class="action-btn primary-btn">Upload</button>
                            </form>
                        </div>
                        <!-- Edit Profile Form -->
                        <div class="form-content" id="edit-profile-form" style="display: none;">
                            <h3>Edit Profile</h3>
                            <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST">
                                <input type="hidden" name="edit_profile" value="1">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="work_number">Work Number</label>
                                    <input type="text" id="work_number" name="work_number" value="<?php echo htmlspecialchars($user['work_number'] ?: '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter work number">
                                </div>
                                <button type="submit" class="action-btn primary-btn">Update Profile</button>
                            </form>
                        </div>
                        <!-- Topic Form -->
                        <div class="form-content horizontal-form" id="topic-form" style="display: none;">
                            <h3>Add New Topic</h3>
                            <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST" enctype="multipart/form-data" class="topic-form-grid">
                                <input type="hidden" name="add_topic" value="1">
                                <div class="form-group">
                                    <label for="section_id">Section/Sub-Section</label>
                                    <select id="section_id" name="section_id" required>
                                        <?php 
                                        $all_sections = [];
                                        foreach ($sections as $section) {
                                            $all_sections[] = $section;
                                            foreach ($section['sub_sections'] as $sub_section) {
                                                $all_sections[] = $sub_section + ['parent_section_id' => $section['id']];
                                            }
                                        }
                                        usort($all_sections, function($a, $b) {
                                            return strcmp($a['name'], $b['name']);
                                        });
                                        foreach ($all_sections as $section): ?>
                                            <option value="<?php echo $section['id']; ?>">
                                                <?php echo htmlspecialchars($section['parent_section_id'] ? '-- ' . $section['name'] : $section['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="title">Topic Title</label>
                                    <input type="text" id="title" name="title" placeholder="Enter topic title" required>
                                </div>
                                <div class="form-group">
                                    <label for="content">Content</label>
                                    <textarea id="content" name="content" placeholder="Enter topic content"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="indicator">Indicator</label>
                                    <select id="indicator" name="indicator" required>
                                        <option value="critical">Critical</option>
                                        <option value="normal">Normal</option>
                                        <option value="positive">Positive</option>
                                    </select>
                                </div>
                                <div class="form-group media-upload">
                                    <label for="media_file">Upload Media</label>
                                    <input type="file" id="media_file" name="media_file" accept="image/*,video/mp4,video/mov,application/pdf,.doc,.docx">
                                </div>
                                <div class="form-group media-upload">
                                    <label for="external_url">Or External URL</label>
                                    <input type="url" id="external_url" name="external_url" placeholder="https://example.com">
                                </div>
                                <div class="form-group table-input">
                                    <label>Table Data</label>
                                    <div id="table-input">
                                        <div class="table-headers">
                                            <label>Headers</label>
                                            <input type="text" name="table_headers[]" placeholder="Header 1" class="table-header-input">
                                            <input type="text" name="table_headers[]" placeholder="Header 2" class="table-header-input">
                                            <button type="button" class="action-btn small-btn" onclick="addHeader()">Add</button>
                                        </div>
                                        <div class="table-rows">
                                            <label>Rows</label>
                                            <div id="table-rows">
                                                <div class="table-row">
                                                    <input type="text" name="table_data[0][]" placeholder="Cell 1" class="table-cell-input">
                                                    <input type="text" name="table_data[0][]" placeholder="Cell 2" class="table-cell-input">
                                                    <button type="button" class="action-btn small-btn delete-btn" onclick="removeRow(this)">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" class="action-btn small-btn" onclick="addRow()">Add Row</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="action-btn primary-btn submit-btn">Add Topic</button>
                            </form>
                        </div>
                        <!-- Extinguisher Form (Manager Only) -->
                        <?php if ($is_manager && $is_own_health_center): ?>
                            <div class="form-content horizontal-form" id="extinguisher-form" style="display: none;">
                                <h3>Add New Fire Extinguisher</h3>
                                <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST" enctype="multipart/form-data" class="extinguisher-form-grid">
                                    <input type="hidden" name="add_extinguisher" value="1">
                                    <div class="form-group">
                                        <label for="code">Extinguisher Code</label>
                                        <input type="text" id="code" name="code" placeholder="Enter code" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="location">Location</label>
                                        <input type="text" id="location" name="location" placeholder="Enter location" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="type">Type</label>
                                        <input type="text" id="type" name="type" placeholder="Enter type" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select id="status" name="status" required>
                                            <option value="Green">Green (OK)</option>
                                            <option value="Red">Red (Needs Maintenance)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="last_inspection">Last Inspection</label>
                                        <input type="date" id="last_inspection" name="last_inspection">
                                    </div>
                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <textarea id="notes" name="notes" placeholder="Enter notes"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="image">Upload Image</label>
                                        <input type="file" id="image" name="image" accept="image/*">
                                    </div>
                                    <button type="submit" class="action-btn primary-btn submit-btn">Add Extinguisher</button>
                                </form>
                            </div>
                       <!-- Add Section Form (Manager Only) -->
                        <div class="form-content" id="section-form" style="display: none;">
                            <h3>Add New Sub-Section</h3>
                            <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST">
                                <input type="hidden" name="add_section" value="1">
                                <div class="form-group">
                                    <label for="section_name">Sub-Section Name</label>
                                    <input type="text" id="section_name" name="section_name" placeholder="Enter sub-section name" required>
                                </div>
                                <div class="form-group">
                                    <label for="parent_section_id">Parent Section</label>
                                    <select id="parent_section_id" name="parent_section_id" required>
                                        <?php
                                        $stmt = $conn->prepare("SELECT id, name FROM sections WHERE parent_section_id IS NULL ORDER BY name");
                                        if ($stmt) {
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $parent_sections = $result->fetch_all(MYSQLI_ASSOC);
                                            $result->free();
                                            $stmt->close();
                                        } else {
                                            $parent_sections = [];
                                            error_log("health_center.php: Failed to prepare parent sections query for section-form: " . $conn->error);
                                        }
                                        if (empty($parent_sections)) {
                                            echo '<option value="" disabled>No parent sections available</option>';
                                        } else {
                                            foreach ($parent_sections as $section) {
                                                echo '<option value="' . htmlspecialchars($section['id']) . '">' . htmlspecialchars($section['name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <button type="submit" class="action-btn primary-btn">Add Sub-Section</button>
                            </form>
                        </div>
                                                <?php endif; ?>
                    </section>
                </div>

                <!-- Dashboard Section -->
                <div class="tab-content active" id="details">
                    <section class="dashboard-section" id="dashboard-section">
                        <!-- Health Center Dashboard -->
                        <div class="health-center-details">
                            <h2><?php echo htmlspecialchars($health_center['name'], ENT_QUOTES, 'UTF-8'); ?> Dashboard</h2>

                            <!-- Section Navigation -->
                            <div class="section-navigation">
                                <?php foreach ($sections as $index => $section): ?>
                                    <button class="section-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-section-id="<?php echo $section['id']; ?>">
                                        <?php echo htmlspecialchars($section['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <!-- Sub-Section Navigation -->
                            <div class="sub-section-navigation" id="sub-sections" style="display: none;">
                                <!-- Sub-sections populated dynamically via JavaScript -->
                            </div>

                            <!-- Topics List -->
                            <div class="topics-list">
                                <h3 id="topics-title">Topics</h3>
                                <!-- Extinguisher List (Visible only for Fire Safety, ID 5) -->
                                <div class="extinguisher-list" id="extinguisher-list" style="display: none;">
                                    <h3>Fire Extinguishers</h3>
                                    <div class="extinguisher-stats-card">
                                        <div class="stat-box">
                                            <h4>Total Extinguishers</h4>
                                            <p><?php echo $extinguisher_stats['total']; ?></p>
                                        </div>
                                        <div class="stat-box">
                                            <h4>Green (OK)</h4>
                                            <p><?php echo $extinguisher_stats['green']; ?></p>
                                        </div>
                                        <div class="stat-box">
                                            <h4>Red (Needs Maintenance)</h4>
                                            <p><?php echo $extinguisher_stats['red']; ?></p>
                                        </div>
                                        <div class="stat-box">
                                            <h4>Inspected</h4>
                                            <p><?php echo $extinguisher_stats['inspected']; ?></p>
                                        </div>
                                        <div class="stat-box">
                                            <h4>Due for Inspection</h4>
                                            <p><?php echo $extinguisher_stats['due']; ?></p>
                                        </div>
                                    </div>
                                    <?php if (empty($extinguishers)): ?>
                                        <p>No fire extinguishers found for this health center.</p>
                                    <?php else: ?>
                                        <?php foreach ($extinguishers as $ext): ?>
                                            <div class="extinguisher">
                                                <h4><?php echo htmlspecialchars($ext['code'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <p><strong>Location:</strong> <?php echo htmlspecialchars($ext['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p><strong>Type:</strong> <?php echo htmlspecialchars($ext['type'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p><strong>Status:</strong> 
                                                    <span class="status-<?php echo strtolower($ext['status']); ?>">
                                                        <?php echo htmlspecialchars($ext['status'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </p>
                                                <p><strong>Last Inspection:</strong> 
                                                    <?php echo $ext['last_inspection'] ? date('M d, Y', strtotime($ext['last_inspection'])) : 'N/A'; ?>
                                                </p>
                                                <a href="extinguisher_details.php?id=<?php echo $ext['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="action-btn view">View Details</a>
                                                <?php if ($is_manager && $is_own_health_center): ?>
                                                    <a href="edit_extinguisher.php?id=<?php echo $ext['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="action-btn edit">Edit</a>
                                                    <a href="delete_extinguisher.php?id=<?php echo $ext['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this extinguisher?');">Delete</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <!-- Topics Container -->
<div class="topics-container" id="topics-container">
    <?php foreach ($topics_by_user as $user_id => $topics): ?>
        <?php
        $stmt = $conn->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $topic_user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $topic_user = ['username' => 'Unknown', 'profile_picture' => 'assets/images/default_user.png'];
            error_log("health_center.php: Prepare failed for topic user query: " . $conn->error);
        }
        ?>
        <div class="user-topics" data-user-id="<?php echo $user_id; ?>">
            <h4><?php echo htmlspecialchars($topic_user['username'], ENT_QUOTES, 'UTF-8'); ?>'s Topics</h4>
            <?php foreach ($topics as $topic): ?>
                <div class="topic-card" data-section-id="<?php echo $topic['section_id']; ?>">
                    <div class="topic-content">
                        <a href="topic_details.php?id=<?php echo $topic['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="topic-link">
                            <div class="topic-header">
                                <h4><?php echo htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <span class="indicator indicator-<?php echo $topic['indicator'] == -1 ? 'critical' : ($topic['indicator'] == 0 ? 'info' : 'success'); ?>">
                                    <?php echo $topic['indicator'] == -1 ? 'Critical' : ($topic['indicator'] == 0 ? 'Normal' : 'Positive'); ?>
                                </span>
                            </div>
                            <div class="topic-body">
                                <p><?php echo $topic['content'] ? htmlspecialchars(substr($topic['content'], 0, 50), ENT_QUOTES, 'UTF-8') . '...' : 'No content provided.'; ?></p>
                                <p><strong>Section:</strong> <?php echo htmlspecialchars(getSectionName($sections, $topic['section_id']), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>Posted:</strong> <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?></p>
                                <?php if ($topic['media_type'] == 'image' && $topic['media_path']): ?>
                                    <img src="<?php echo htmlspecialchars($topic['media_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Topic Image" style="max-width: 100px;">
                                <?php elseif ($topic['media_type'] == 'video' && $topic['media_path']): ?>
                                    <video width="100" controls>
                                        <source src="<?php echo htmlspecialchars($topic['media_path'], ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php elseif ($topic['media_type'] == 'file' && $topic['media_path']): ?>
                                    <a href="<?php echo htmlspecialchars($topic['media_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">View File</a>
                                <?php elseif ($topic['media_type'] == 'external' && $topic['media_path']): ?>
                                    <a href="<?php echo htmlspecialchars($topic['media_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">View External Link</a>
                                <?php endif; ?>
                                <?php if ($topic['table_data']): ?>
                                    <?php $table = json_decode($topic['table_data'], true); ?>
                                    <table class="table-data">
                                        <thead>
                                            <tr>
                                                <?php foreach ($table['headers'] as $header): ?>
                                                    <th><?php echo htmlspecialchars($header, ENT_QUOTES, 'UTF-8'); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($table['rows'] as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $cell): ?>
                                                        <td><?php echo htmlspecialchars($cell, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="topic-actions">
                            <?php if (in_array($topic['id'], $pinned_topics)): ?>
                                <a href="unpin_topic.php?topic_id=<?php echo $topic['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="unpin-btn">Unpin</a>
                            <?php else: ?>
                                <a href="pin_topic.php?topic_id=<?php echo $topic['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="pin-btn">Pin</a>
                            <?php endif; ?>
                            <?php if ($is_manager && $is_own_health_center): ?>
                                <a href="archive_topic.php?id=<?php echo $topic['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to archive this topic?');">Archive</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php if (empty($topics_by_user)): ?>
        <p>No topics found for this section.</p>
    <?php endif; ?>
</div>

<!-- Archived Topics (Manager Only) -->
<?php if ($is_manager && $is_own_health_center && !empty($archive_topics)): ?>
    <div class="archived-topics">
        <h3>Archived Topics</h3>
        <?php foreach ($archive_topics as $topic): ?>
            <div class="topic-card archived">
                <div class="topic-content">
                    <a href="topic_details.php?id=<?php echo $topic['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="topic-link">
                        <div class="topic-header">
                            <h4><?php echo htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8'); ?> (Archived)</h4>
                            <span class="indicator indicator-<?php echo $topic['indicator'] == -1 ? 'critical' : ($topic['indicator'] == 0 ? 'info' : 'success'); ?>">
                                <?php echo $topic['indicator'] == -1 ? 'Critical' : ($topic['indicator'] == 0 ? 'Normal' : 'Positive'); ?>
                            </span>
                        </div>
                        <div class="topic-body">
                            <p><?php echo $topic['content'] ? htmlspecialchars(substr($topic['content'], 0, 50), ENT_QUOTES, 'UTF-8') . '...' : 'No content provided.'; ?></p>
                            <p><strong>Posted by:</strong> <?php echo htmlspecialchars($topic['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Posted:</strong> <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?></p>
                        </div>
                    </a>
                    <div class="topic-actions">
                        <a href="restore_topic.php?id=<?php echo $topic['id']; ?>&health_center_id=<?php echo $health_center_id; ?>" class="action-btn primary-btn" onclick="return confirm('Are you sure you want to restore this topic?');">Restore</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
</section>
</div>

<!-- Fire Alarms Section -->
<div class="tab-content" id="alarms">
    <section class="alarms-section">
        <h2>Fire Alarms</h2>
        <?php if ($is_manager && $is_own_health_center): ?>
            <div class="form-content">
                <h3>Add New Fire Alarm</h3>
                <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST">
                    <input type="hidden" name="add_alarm" value="1">
                    <div class="form-group">
                        <label for="alarm_type">Alarm Type</label>
                        <input type="text" id="alarm_type" name="alarm_type" placeholder="Enter alarm type" required>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="Enter location" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                    <button type="submit" class="action-btn primary-btn">Add Alarm</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="alarm-list">
            <h3>Fire Alarms</h3>
            <div class="alarm-stats-card">
                <div class="stat-box">
                    <h4>Total Alarms</h4>
                    <p><?php echo $alarm_stats['total']; ?></p>
                </div>
                <div class="stat-box">
                    <h4>Active</h4>
                    <p><?php echo $alarm_stats['active']; ?></p>
                </div>
                <div class="stat-box">
                    <h4>Resolved</h4>
                    <p><?php echo $alarm_stats['resolved']; ?></p>
                </div>
            </div>
            <?php if (empty($alarms)): ?>
                <p>No fire alarms found for this health center.</p>
            <?php else: ?>
                <?php foreach ($alarms as $alarm): ?>
                    <div class="alarm">
                        <h4><?php echo htmlspecialchars($alarm['alarm_type'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($alarm['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="status-<?php echo strtolower($alarm['status']); ?>">
                                <?php echo htmlspecialchars($alarm['status'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </p>
                        <p><strong>Time:</strong> <?php echo date('M d, Y H:i', strtotime($alarm['alarm_time'])); ?></p>
                        <?php if ($is_manager && $is_own_health_center): ?>
                            <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST" style="display: inline;">
                                <input type="hidden" name="update_alarm_status" value="1">
                                <input type="hidden" name="alarm_id" value="<?php echo $alarm['id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="Active" <?php echo $alarm['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Resolved" <?php echo $alarm['status'] == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </form>
                            <form action="health_center.php?id=<?php echo $health_center_id; ?>" method="POST" style="display: inline;">
                                <input type="hidden" name="delete_alarm" value="1">
                                <input type="hidden" name="alarm_id" value="<?php echo $alarm['id']; ?>">
                                <button type="submit" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this alarm?');">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

</div>
</main>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>&copy; 2025 Northern Borders Health Cluster. All rights reserved.</p>
    </div>
</footer>

<script>
    // Tab Navigation
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.dataset.tab).classList.add('active');
        });
    });

    // Sidebar Buttons
    document.querySelectorAll('.action-btn[data-section]').forEach(button => {
        button.addEventListener('click', () => {
            const sectionId = button.dataset.section;
            document.querySelectorAll('.form-content').forEach(form => form.style.display = 'none');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('forms').classList.add('active');
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector('.tab-btn[data-tab="forms"]').classList.add('active');
            document.getElementById(sectionId).style.display = 'block';
            document.getElementById('forms-section').style.display = 'block';
        });
    });

    // Section Navigation
    document.querySelectorAll('.section-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.section-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const sectionId = button.dataset.sectionId;
            const subSectionsDiv = document.getElementById('sub-sections');
            subSectionsDiv.innerHTML = '';
            <?php foreach ($sections as $section): ?>
                if (sectionId == '<?php echo $section['id']; ?>') {
                    <?php if (!empty($section['sub_sections'])): ?>
                        <?php foreach ($section['sub_sections'] as $sub_section): ?>
                            subSectionsDiv.innerHTML += `
                                <button class="sub-section-btn" data-sub-section-id="<?php echo $sub_section['id']; ?>">
                                    <?php echo htmlspecialchars($sub_section['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            `;
                        <?php endforeach; ?>
                        subSectionsDiv.style.display = 'block';
                    <?php else: ?>
                        subSectionsDiv.style.display = 'none';
                    <?php endif; ?>
                    // Show extinguisher list only for Fire Safety (ID 5)
                    if (sectionId == '5') {
                        document.getElementById('extinguisher-list').style.display = 'block';
                    } else {
                        document.getElementById('extinguisher-list').style.display = 'none';
                    }
                    // Filter topics
                    document.querySelectorAll('.topic-card').forEach(card => {
                        card.style.display = card.dataset.sectionId == sectionId ? 'block' : 'none';
                    });
                    document.getElementById('topics-title').textContent = '<?php echo htmlspecialchars($section['name'], ENT_QUOTES, 'UTF-8'); ?> Topics';
                }
            <?php endforeach; ?>
            // Activate first sub-section if available
            const firstSubSection = subSectionsDiv.querySelector('.sub-section-btn');
            if (firstSubSection) {
                firstSubSection.classList.add('active');
                filterTopicsBySubSection(firstSubSection.dataset.subSectionId);
            }
        });
    });

    // Sub-Section Navigation
    document.getElementById('sub-sections').addEventListener('click', (e) => {
        if (e.target.classList.contains('sub-section-btn')) {
            document.querySelectorAll('.sub-section-btn').forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
            filterTopicsBySubSection(e.target.dataset.subSectionId);
        }
    });

    function filterTopicsBySubSection(subSectionId) {
        document.querySelectorAll('.topic-card').forEach(card => {
            card.style.display = card.dataset.sectionId == subSectionId ? 'block' : 'none';
        });
        // Show extinguisher list only for Fire Safety (ID 5)
        if (subSectionId == '5') {
            document.getElementById('extinguisher-list').style.display = 'block';
        } else {
            document.getElementById('extinguisher-list').style.display = 'none';
        }
    }

    // Table Input Handling
    let rowCount = 1;
    function addHeader() {
        const headersDiv = document.querySelector('.table-headers');
        const headerInputs = headersDiv.querySelectorAll('.table-header-input');
        if (headerInputs.length < 5) {
            const newHeader = document.createElement('input');
            newHeader.type = 'text';
            newHeader.name = 'table_headers[]';
            newHeader.placeholder = `Header ${headerInputs.length + 1}`;
            newHeader.className = 'table-header-input';
            headersDiv.insertBefore(newHeader, headersDiv.querySelector('button'));
            updateTableRows();
        } else {
            alert('Maximum 5 headers allowed.');
        }
    }

    function addRow() {
        const rowsDiv = document.getElementById('table-rows');
        const headerInputs = document.querySelectorAll('.table-header-input');
        const rowDiv = document.createElement('div');
        rowDiv.className = 'table-row';
        for (let i = 0; i < headerInputs.length; i++) {
            const input = document.createElement('input');
            input.type = 'text';
            input.name = `table_data[${rowCount}][]`;
            input.placeholder = `Cell ${i + 1}`;
            input.className = 'table-cell-input';
            rowDiv.appendChild(input);
        }
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'action-btn small-btn delete-btn';
        deleteBtn.textContent = 'Remove';
        deleteBtn.onclick = () => removeRow(deleteBtn);
        rowDiv.appendChild(deleteBtn);
        rowsDiv.appendChild(rowDiv);
        rowCount++;
    }

    function removeRow(button) {
        const row = button.parentElement;
        row.remove();
        updateRowNames();
    }

    function updateRowNames() {
        const rows = document.querySelectorAll('.table-row');
        rows.forEach((row, index) => {
            const inputs = row.querySelectorAll('.table-cell-input');
            inputs.forEach(input => {
                input.name = `table_data[${index}][]`;
            });
        });
        rowCount = rows.length;
    }

    function updateTableRows() {
        const headerInputs = document.querySelectorAll('.table-header-input');
        const rows = document.querySelectorAll('.table-row');
        rows.forEach(row => {
            const currentInputs = row.querySelectorAll('.table-cell-input');
            const deleteBtn = row.querySelector('.delete-btn');
            row.innerHTML = '';
            for (let i = 0; i < headerInputs.length; i++) {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = `table_data[${row.dataset.index}][]`;
                input.placeholder = `Cell ${i + 1}`;
                input.className = 'table-cell-input';
                input.value = currentInputs[i] ? currentInputs[i].value : '';
                row.appendChild(input);
            }
            row.appendChild(deleteBtn);
        });
    }

    // Initialize first section
    document.querySelector('.section-btn')?.click();

    // Notification Toggle
    const notificationToggle = document.querySelector('.notification-toggle');
    const notificationList = document.querySelector('.notification-list');
    if (notificationToggle && notificationList) {
        notificationToggle.addEventListener('click', () => {
            notificationList.classList.toggle('active');
        });
    }
</script>
</body>
</html>