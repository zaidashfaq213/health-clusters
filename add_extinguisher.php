<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';
session_start();

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: login.php");
    exit();
}

$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : NULL;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : NULL;

if ($hospital_id === NULL && $health_center_id === NULL) {
    $_SESSION['error'] = "Invalid hospital or health center ID.";
    error_log("add_extinguisher.php: Missing hospital_id or health_center_id in URL");
    header("Location: index.php");
    exit();
}

// Validate facility and manager access
$facility = null;
$facility_type = '';
if ($hospital_id !== NULL) {
    $stmt = $conn->prepare("SELECT name FROM hospitals WHERE id = ?");
    $stmt->bind_param("i", $hospital_id);
    $facility_type = 'hospital';
} else {
    $stmt = $conn->prepare("SELECT name FROM health_centers WHERE id = ?");
    $stmt->bind_param("i", $health_center_id);
    $facility_type = 'health_center';
}
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("add_extinguisher.php: Database error for {$facility_type}_id=" . ($hospital_id ?: $health_center_id) . ": " . $conn->error);
    header("Location: index.php");
    exit();
}
$facility = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$facility) {
    $_SESSION['error'] = ucfirst($facility_type) . " not found.";
    error_log("add_extinguisher.php: " . ucfirst($facility_type) . " not found for id=" . ($hospital_id ?: $health_center_id));
    header("Location: index.php");
    exit();
}

if ($hospital_id !== NULL) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND hospital_id = ? AND role = 'manager'");
    $stmt->bind_param("ii", $_SESSION['user_id'], $hospital_id);
} else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND health_center_id = ? AND role = 'manager'");
    $stmt->bind_param("ii", $_SESSION['user_id'], $health_center_id);
}
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $_SESSION['error'] = "You do not have permission to add extinguishers for this " . $facility_type . ".";
    error_log("add_extinguisher.php: Unauthorized manager access for {$facility_type}_id=" . ($hospital_id ?: $health_center_id));
    header("Location: index.php");
    exit();
}
$stmt->close();

// Fetch user profile picture
$stmt = $conn->prepare("SELECT username, role, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$profile_picture = $user['profile_picture'] ? htmlspecialchars($user['profile_picture']) : 'assets/images/default_profile.png';

// Fetch notifications for extinguishers in this facility
$notifications = [];
if ($hospital_id !== NULL) {
    $stmt = $conn->prepare("SELECT n.*, f.code FROM notifications n JOIN fireextinguishers f ON n.extinguisher_id = f.id WHERE f.hospital_id = ? AND n.status = 'pending' AND n.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt->bind_param("i", $hospital_id);
} else {
    $stmt = $conn->prepare("SELECT n.*, f.code FROM notifications n JOIN fireextinguishers f ON n.extinguisher_id = f.id WHERE f.health_center_id = ? AND n.status = 'pending' AND n.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt->bind_param("i", $health_center_id);
}
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to validate date
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date && $d->format('Y') >= 1900 && $d->format('Y') <= (date('Y') + 10);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $status = trim($_POST['status'] ?? 'Green');
    $last_inspection = !empty($_POST['last_inspection']) ? $_POST['last_inspection'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $image_url = '';
    $qr_code_url = '';
    $file_path = '';
    $qr_code_file = '';

    // Validate inputs
    if (empty($code) || empty($location) || empty($type)) {
        $_SESSION['error'] = "Code, location, and type are required.";
    } elseif ($last_inspection !== null && !validateDate($last_inspection)) {
        $_SESSION['error'] = "Invalid last inspection date. Use YYYY-MM-DD format and ensure the date is valid.";
    } else {
        // Check if code is unique
        $stmt = $conn->prepare("SELECT id FROM fireextinguishers WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Extinguisher code already exists.";
            $stmt->close();
        } else {
            $stmt->close();

            // Handle file upload
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = 'Uploads/extinguishers/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                $file_name = time() . '_' . basename($_FILES['image']['name']);
                $file_path = $upload_dir . $file_name;
                $file_type = mime_content_type($_FILES['image']['tmp_name']);

                if (!in_array($file_type, $allowed_types)) {
                    $_SESSION['error'] = "Invalid image format. Only JPEG, PNG, or GIF allowed.";
                } elseif ($_FILES['image']['size'] > $max_size) {
                    $_SESSION['error'] = "Image size exceeds 5MB.";
                } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                    $_SESSION['error'] = "Failed to upload image.";
                    error_log("add_extinguisher.php: Failed to upload image for code=$code");
                } else {
                    $image_url = $file_path;
                }
            }

            // Insert into database if no errors
            if (!isset($_SESSION['error'])) {
                $conn->begin_transaction();
                try {
                    // Insert extinguisher
                    $stmt = $conn->prepare("INSERT INTO fireextinguishers (code, location, type, status, last_inspection, notes, image_url, hospital_id, health_center_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssii", $code, $location, $type, $status, $last_inspection, $notes, $image_url, $hospital_id, $health_center_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Error adding extinguisher: " . $stmt->error);
                    }
                    $extinguisher_id = $conn->insert_id;
                    $stmt->close();

                    // Generate QR code
                    $qr_code_dir = 'Uploads/qrcodes/';
                    if (!is_dir($qr_code_dir)) {
                        mkdir($qr_code_dir, 0777, true);
                    }
                    $qr_code_file = $qr_code_dir . time() . '_' . $code . '.png';
                    $qr_code_url = "http://localhost/Hospital-managment/extinguisher_details.php?id=$extinguisher_id&{$facility_type}_id=" . ($hospital_id ?: $health_center_id);

                    $qrCode = QrCode::create($qr_code_url);
                    $writer = new PngWriter();
                    $result = $writer->write($qrCode);
                    $result->saveToFile($qr_code_file);

                    if (!file_exists($qr_code_file)) {
                        throw new Exception("Failed to generate QR code.");
                    }

                    // Update QR code URL
                    $stmt = $conn->prepare("UPDATE fireextinguishers SET qr_code_url = ? WHERE id = ?");
                    $stmt->bind_param("si", $qr_code_file, $extinguisher_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Error updating QR code: " . $stmt->error);
                    }
                    $stmt->close();

                    // Schedule notification
                    $due_date = $last_inspection ? date('Y-m-d', strtotime($last_inspection . ' +6 months')) : date('Y-m-d', strtotime('+6 months'));
                    $message = "Inspection due for extinguisher {$code} at {$location}.";
                    $stmt = $conn->prepare("INSERT INTO notifications (extinguisher_id, message, due_date, status) VALUES (?, ?, ?, 'pending')");
                    $stmt->bind_param("iss", $extinguisher_id, $message, $due_date);
                    if (!$stmt->execute()) {
                        throw new Exception("Error scheduling notification: " . $stmt->error);
                    }
                    $stmt->close();

                    $conn->commit();
                    $_SESSION['success'] = "Fire extinguisher added successfully.";
                    header("Location: extinguishers.php?{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = $e->getMessage();
                    error_log("add_extinguisher.php: Transaction failed for code=$code: " . $e->getMessage());
                    if (!empty($file_path) && file_exists($file_path)) {
                        unlink($file_path);
                    }
                    if (!empty($qr_code_file) && file_exists($qr_code_file)) {
                        unlink($qr_code_file);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Fire Extinguisher - <?php echo htmlspecialchars($facility['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reset and General Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            scroll-behavior: smooth;
        }

        body {
            background: linear-gradient(160deg, #f8fafc 0%, #e0f2fe 100%);
            color: #1b263b;
            line-height: 1.8;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.1), transparent);
            z-index: -1;
        }

        /* Container */
        .container {
            width: 90%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: linear-gradient(90deg, #1b263b 0%, #3b82f6 100%);
            padding: 15px 0;
            z-index: 1000;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            transition: background 0.3s ease;
        }

        .navbar.sticky {
            background: linear-gradient(90deg, #1b263b 0%, #2563eb 100%);
        }

        .nav-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #f97316;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .logo-circle:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(249, 115, 22, 0.5);
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 30px;
        }

        .nav-menu li a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.3s ease;
        }

        .nav-menu li a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #f97316;
            transition: width 0.4s ease;
        }

        .nav-menu li a:hover::after {
            width: 100%;
        }

        .nav-menu li a:hover {
            color: #f97316;
        }

        .notification-menu {
            position: relative;
            cursor: pointer;
        }

        .notification-icon {
            font-size: 1.4rem;
            color: #ffffff;
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: #ffffff;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            list-style: none;
            padding: 0.5rem;
            margin: 0;
            width: 300px;
            z-index: 1000;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-item {
            padding: 0.8rem;
            border-bottom: 1px solid #e5e9f2;
            font-size: 0.9rem;
            color: #1b263b;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item strong {
            color: #3b82f6;
        }

        .profile-menu {
            position: relative;
            cursor: pointer;
        }

        .profile-card-nav {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: background 0.3s ease;
        }

        .profile-card-nav:hover {
            background: rgba(249, 115, 22, 0.2);
        }

        .profile-picture-container {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #f97316;
            box-shadow: 0 0 10px rgba(249, 115, 22, 0.3);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .profile-picture-container:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(249, 115, 22, 0.5);
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h4 {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .profile-info p {
            font-size: 0.8rem;
            color: #cbd5e1;
            font-weight: 400;
            text-transform: capitalize;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: #1b263b;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            list-style: none;
            padding: 10px 0;
            min-width: 150px;
            z-index: 1000;
        }

        .profile-dropdown.active {
            display: block;
        }

        .profile-dropdown li a {
            display: block;
            padding: 10px 20px;
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .profile-dropdown li a:hover {
            background: #f97316;
        }

        .menu-toggle {
            display: none;
            font-size: 1.8rem;
            color: #ffffff;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .menu-toggle:hover {
            transform: scale(1.2);
        }

        /* Add Extinguisher Section */
        .add-extinguisher {
            padding: 80px 0;
            background: linear-gradient(135deg, #f8fafc, #e0f2fe);
            animation: fadeIn 0.5s ease-out;
        }

        h2 {
            font-size: 2.8rem;
            color: #1b263b;
            text-align: center;
            margin-bottom: 40px;
            font-weight: 800;
            background: linear-gradient(45deg, #1b263b, #2dd4bf);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
        }

        h2::after {
            content: '';
            width: 100px;
            height: 4px;
            background: #f97316;
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .success, .error {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 12px;
            text-align: center;
            font-weight: 500;
            font-size: 1rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.5s ease;
        }

        .success {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .error {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        .success::before, .error::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.2), transparent);
            z-index: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .success:hover::before, .error:hover::before {
            opacity: 1;
        }

        .success > *, .error > * {
            position: relative;
            z-index: 1;
        }

        .inspection-form {
            background: linear-gradient(135deg, #ffffff, #e0f2fe);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            max-width: 600px;
            margin: 0 auto;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .inspection-form:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.2);
        }

        .inspection-form::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.15), transparent);
            transform: rotate(45deg);
            z-index: -1;
        }

        .inspection-form form {
            display: grid;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 1.1rem;
            color: #1b263b;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid #1b263b;
            border-radius: 12px;
            font-size: 1rem;
            outline: none;
            background: #f8fafc;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #f97316;
            box-shadow: 0 0 10px rgba(249, 115, 22, 0.3);
            background: #ffffff;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #64748b;
            font-style: italic;
        }

        .form-group select {
            appearance: none;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%231b263b" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 16px center, #f8fafc;
            background-size: 12px;
            cursor: pointer;
        }

        .form-group select:focus {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%23f97316" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 16px center, #ffffff;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            padding: 10px;
            border: 2px dashed #1b263b;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.3s ease, background 0.3s ease;
        }

        .form-group input[type="file"]:focus,
        .form-group input[type="file"]:hover {
            border-color: #f97316;
            background: #ffffff;
        }

        .action-btn {
            padding: 14px 30px;
            background: linear-gradient(45deg, #f97316, #fb923c);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.5);
            background: linear-gradient(45deg, #3b82f6, #60a5fa);
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.4s ease;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        /* Footer */
        footer {
            background: linear-gradient(90deg, #1b263b, #3b82f6);
            color: #ffffff;
            text-align: center;
            padding: 30px 0;
            box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.2);
            margin-top: 40px;
        }

        footer p {
            font-size: 1rem;
            font-weight: 400;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            .nav-menu {
                position: fixed;
                top: 80px;
                right: -100%;
                width: 280px;
                height: 100vh;
                background: linear-gradient(135deg, #1b263b, #3b82f6);
                flex-direction: column;
                padding: 30px;
                transition: right 0.4s ease;
                box-shadow: -5px 0 20px rgba(0, 0, 0, 0.3);
            }
            .nav-menu.active {
                right: 0;
            }
            .nav-menu li {
                margin: 25px 0;
            }
            .profile-card-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            .profile-picture-container {
                width: 36px;
                height: 36px;
            }
            .add-extinguisher {
                padding: 60px 0;
            }
            h2 {
                font-size: 2.2rem;
            }
            .inspection-form {
                padding: 20px;
            }
            .success, .error {
                font-size: 0.95rem;
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            h2 {
                font-size: 1.8rem;
            }
            .form-group label {
                font-size: 1rem;
            }
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 0.9rem;
                padding: 10px 14px;
            }
            .action-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
            .success, .error {
                font-size: 0.9rem;
                padding: 10px;
            }
            .inspection-form {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container container">
            <div class="logo-circle">
                <img src="assets/images/logo.png" alt="Northern Borders Health Cluster Logo" loading="lazy">
            </div>
            <div class="menu-toggle" aria-label="Toggle Menu">
                <i class="fas fa-bars"></i>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#hospitals">Hospitals</a></li>
                <li><a href="index.php#health-centers">Health Centers</a></li>
                <li><a href="extinguishers.php?<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>">Fire Extinguishers</a></li>
                <li class="notification-menu">
                    <div class="notification-icon">
                       
                        <i class="fas fa-bell"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="notification-badge"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </div>
                    <ul class="notification-dropdown">
                        <?php if (empty($notifications)): ?>
                            <li class="notification-item">No pending notifications.</li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li class="notification-item">
                                    <strong><?php echo htmlspecialchars($notification['code']); ?></strong>: 
                                    <?php echo htmlspecialchars($notification['message']); ?> 
                                    (Due: <?php echo date('M d, Y', strtotime($notification['due_date'])); ?>)
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="profile-menu">
                    <div class="profile-card-nav">
                        <div class="profile-picture-container">
                            <img src="<?php echo $profile_picture; ?>" alt="Profile Picture" class="profile-picture" loading="lazy">
                        </div>
                        <div class="profile-info">
                            <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                            <p><?php echo htmlspecialchars($user['role']); ?></p>
                        </div>
                    </div>
                    <ul class="profile-dropdown">
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Add Extinguisher Form -->
    <section class="add-extinguisher">
        <div class="container">
            <h2>Add New Fire Extinguisher - <?php echo htmlspecialchars($facility['name']); ?></h2>
            <!-- Feedback Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <form action="add_extinguisher.php?<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>" method="POST" enctype="multipart/form-data" class="inspection-form">
                <div class="form-group">
                    <label for="code">Code</label>
                    <input type="text" id="code" name="code" placeholder="Enter extinguisher code (e.g., TF-OP-03)" required value="<?php echo isset($_POST['code']) ? htmlspecialchars($_POST['code']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="Enter location (e.g., Operating Room)" required value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        <option value="" disabled <?php echo !isset($_POST['type']) ? 'selected' : ''; ?>>Select Type</option>
                        <option value="CO2" <?php echo isset($_POST['type']) && $_POST['type'] === 'CO2' ? 'selected' : ''; ?>>CO2</option>
                        <option value="Dry Powder" <?php echo isset($_POST['type']) && $_POST['type'] === 'Dry Powder' ? 'selected' : ''; ?>>Dry Powder</option>
                        <option value="Foam" <?php echo isset($_POST['type']) && $_POST['type'] === 'Foam' ? 'selected' : ''; ?>>Foam</option>
                        <option value="Water" <?php echo isset($_POST['type']) && $_POST['type'] === 'Water' ? 'selected' : ''; ?>>Water</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Green" <?php echo isset($_POST['status']) && $_POST['status'] === 'Green' ? 'selected' : ''; ?>>Green (OK)</option>
                        <option value="Red" <?php echo isset($_POST['status']) && $_POST['status'] === 'Red' ? 'selected' : ''; ?>>Red (Needs Maintenance)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="last_inspection">Last Inspection Date</label>
                    <input type="date" id="last_inspection" name="last_inspection" value="<?php echo isset($_POST['last_inspection']) ? htmlspecialchars($_POST['last_inspection']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Enter any notes"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="image">Upload Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                <button type="submit" class="action-btn">Add Extinguisher</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>© 2025 Northern Borders Health Cluster. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Toggle mobile menu
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            if (menuToggle && navMenu) {
                menuToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                });
            }

            // Profile dropdown toggle
            const profileMenu = document.querySelector('.profile-menu');
            if (profileMenu) {
                profileMenu.addEventListener('click', (e) => {
                    const dropdown = profileMenu.querySelector('.profile-dropdown');
                    dropdown.classList.toggle('active');
                });
            }

            // Notification dropdown toggle
            const notificationMenu = document.querySelector('.notification-menu');
            if (notificationMenu) {
                notificationMenu.addEventListener('click', (e) => {
                    const dropdown = notificationMenu.querySelector('.notification-dropdown');
                    dropdown.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>