<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$extinguisher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : NULL;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : NULL;

// Validate inputs
if ($extinguisher_id <= 0 || ($hospital_id === NULL && $health_center_id === NULL)) {
    $_SESSION['error'] = "Invalid extinguisher or facility ID.";
    error_log("extinguisher_details.php: Invalid inputs: extinguisher_id=$extinguisher_id, hospital_id=$hospital_id, health_center_id=$health_center_id");
    header("Location: index.php");
    exit();
}

// Fetch extinguisher details
$stmt = $conn->prepare("SELECT * FROM fireextinguishers WHERE id = ? AND (hospital_id = ? OR health_center_id = ?)");
$stmt->bind_param("iii", $extinguisher_id, $hospital_id, $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("extinguisher_details.php: Database error for extinguisher_id=$extinguisher_id: " . $conn->error);
    header("Location: index.php");
    exit();
}
$extinguisher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$extinguisher) {
    $_SESSION['error'] = "Extinguisher not found.";
    error_log("extinguisher_details.php: Extinguisher not found for id=$extinguisher_id");
    header("Location: index.php");
    exit();
}

// Fetch facility name
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
    error_log("extinguisher_details.php: Database error for {$facility_type}_id=" . ($hospital_id ?: $health_center_id) . ": " . $conn->error);
    header("Location: index.php");
    exit();
}
$facility = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$facility) {
    $_SESSION['error'] = ucfirst($facility_type) . " not found.";
    error_log("extinguisher_details.php: " . ucfirst($facility_type) . " not found for id=" . ($hospital_id ?: $health_center_id));
    header("Location: index.php");
    exit();
}

// Fetch inspection history
$stmt = $conn->prepare("SELECT * FROM inspections WHERE extinguisher_id = ? ORDER BY inspection_date DESC");
$stmt->bind_param("i", $extinguisher_id);
$stmt->execute();
$inspections = $stmt->get_result();
$stmt->close();

// Fetch notifications for this extinguisher
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE extinguisher_id = ? AND status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stmt->bind_param("i", $extinguisher_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to validate date
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date && $d->format('Y') >= 1900 && $d->format('Y') <= (date('Y') + 10);
}

// Handle inspection form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inspector = trim($_POST['inspector'] ?? '');
    $inspection_date = trim($_POST['inspection_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $image = '';
    $file_path = '';

    // Validate inputs
    if (empty($inspector) || empty($inspection_date) || empty($status)) {
        $_SESSION['error'] = "Inspector name, inspection date, and status are required.";
    } elseif (!validateDate($inspection_date)) {
        $_SESSION['error'] = "Invalid inspection date. Use YYYY-MM-DD format.";
    } else {
        // Handle file upload
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = 'Uploads/inspections/';
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
                error_log("extinguisher_details.php: Failed to upload inspection image for extinguisher_id=$extinguisher_id");
            } else {
                $image = $file_path;
            }
        }

        // Insert inspection and schedule notification
        if (!isset($_SESSION['error'])) {
            $conn->begin_transaction();
            try {
                // Insert inspection
                $stmt = $conn->prepare("INSERT INTO inspections (extinguisher_id, inspector, inspection_date, status, note, image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $extinguisher_id, $inspector, $inspection_date, $status, $note, $image);
                if (!$stmt->execute()) {
                    throw new Exception("Error adding inspection: " . $stmt->error);
                }
                $stmt->close();

                // Update extinguisher status and last inspection
                $stmt = $conn->prepare("UPDATE fireextinguishers SET status = ?, last_inspection = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $inspection_date, $extinguisher_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error updating extinguisher: " . $stmt->error);
                }
                $stmt->close();

                // Schedule next inspection notification (6 months from inspection date)
                $due_date = date('Y-m-d', strtotime($inspection_date . ' +6 months'));
                $message = "Inspection due for extinguisher {$extinguisher['code']} at {$extinguisher['location']}.";
                $stmt = $conn->prepare("INSERT INTO notifications (extinguisher_id, message, due_date, status) VALUES (?, ?, ?, 'pending')");
                $stmt->bind_param("iss", $extinguisher_id, $message, $due_date);
                if (!$stmt->execute()) {
                    throw new Exception("Error scheduling notification: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit();
                $_SESSION['success'] = "Inspection added successfully.";
                header("Location: extinguisher_details.php?id=$extinguisher_id&{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = $e->getMessage();
                error_log("extinguisher_details.php: Transaction failed for extinguisher_id=$extinguisher_id: " . $e->getMessage());
                if (!empty($file_path) && file_exists($file_path)) {
                    unlink($file_path);
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
    <title>Extinguisher Details - <?php echo htmlspecialchars($extinguisher['code']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
     /* General Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
    scroll-behavior: smooth;
}

body {
    color: #1e293b;
    background: linear-gradient(160deg, #f9fbfe 0%, #e5e9f2 100%);
    position: relative;
    overflow-x: hidden;
    line-height: 1.6;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(91, 78, 255, 0.15), transparent);
    z-index: -1;
}

/* Container */
.container {
    width: 90%;
    max-width: 1280px;
    margin: 3rem auto;
    padding: 0 1.5rem;
}

/* Navbar */
.navbar {
    background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
    padding: 15px 0;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.nav-container {
    max-width: 1280px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo-circle img {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #5b4eff;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.logo-circle img:hover {
    transform: scale(1.1);
    box-shadow: 0 0 20px rgba(91, 78, 255, 0.5);
}

.nav-menu {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 25px;
    margin: 0;
    padding: 0;
}

.nav-menu li a {
    color: #ffffff;
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    position: relative;
    padding-bottom: 5px;
    transition: color 0.3s ease;
}

.nav-menu li a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: #e0e7ff;
    transition: width 0.4s ease;
}

.nav-menu li a:hover::after {
    width: 100%;
}

.nav-menu li a:hover {
    color: #e0e7ff;
}

.notification-menu {
    position: relative;
    cursor: pointer;
}

.notification-icon {
    font-size: 1.5rem;
    color:rgb(255, 153, 0);
    position: relative;
    transition: transform 0.3s ease;
}

.notification-icon:hover {
    transform: scale(1.2);
}

.notification-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background: #ef4444;
    color: #ffffff;
    border-radius: 50%;
    padding: 3px 7px;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
}

.notification-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    border-radius: 10px;
    list-style: none;
    padding: 0.75rem;
    margin: 0;
    width: 320px;
    z-index: 1000;
    border: 1px solid rgba(91, 78, 255, 0.2);
}

.notification-dropdown.active {
    display: block;
}

.notification-item {
    padding: 1rem;
    border-bottom: 1px solid #e5e9f2;
    font-size: 0.95rem;
    color: #1e293b;
    transition: background 0.3s ease;
}

.notification-item:hover {
    background: #f9fbfe;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item strong {
    color: #5b4eff;
    font-weight: 600;
}

.menu-toggle {
    display: none;
    color: #ffffff;
    font-size: 1.6rem;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.menu-toggle:hover {
    transform: scale(1.2);
}

/* Extinguisher Details Section */
.extinguisher-details {
    margin-top: 90px; /* Account for fixed navbar */
    animation: fadeIn 0.6s ease-out;
}

h2 {
    font-size: 2.5rem;
    color: #1e293b;
    text-align: center;
    margin-bottom: 2rem;
    font-weight: 700;
    background: linear-gradient(45deg, #1e293b, #5b4eff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    position: relative;
}

h2::after {
    content: '';
    width: 120px;
    height: 4px;
    background: linear-gradient(90deg, #5b4eff, #818cf8);
    position: absolute;
    bottom: -12px;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 2px;
}

h3 {
    font-size: 1.8rem;
    color: #1e293b;
    margin-bottom: 1.5rem;
    font-weight: 600;
    background: linear-gradient(45deg, #1e293b, #5b4eff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.success, .error {
    padding: 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    font-weight: 500;
    text-align: center;
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

/* Details Card */
.details-card {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    background: linear-gradient(135deg, #ffffff, #f9fbfe);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(91, 78, 255, 0.2);
    position: relative;
    overflow: hidden;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.details-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 50px rgba(0, 0, 0, 0.15);
}

.details-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(91, 78, 255, 0.1), transparent);
    transform: rotate(45deg);
    z-index: -1;
}

.details-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
}

.info-box {
    background: linear-gradient(145deg, #ffffff, #f9fbfe);
    padding: 1.2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(91, 78, 255, 0.15);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.info-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(91, 78, 255, 0.2);
}

.info-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #5b4eff, #818cf8);
    opacity: 0.7;
}

.info-box strong {
    display: block;
    font-weight: 600;
    color: #5b4eff;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.info-box span {
    color: #1e293b;
    font-size: 0.95rem;
    font-weight: 400;
}

.status-green {
    background: linear-gradient(45deg, #10b981, #34d399);
    color: #ffffff;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-block;
}

.status-red {
    background: linear-gradient(45deg, #ef4444, #f87171);
    color: #ffffff;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-block;
}

.details-images {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    align-items: center;
    justify-content: center;
}

.details-images img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    border: 2px solid #5b4eff;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
    background: #ffffff;
    padding: 0.5rem;
}

.details-images img:hover {
    transform: scale(1.08);
    box-shadow: 0 8px 25px rgba(91, 78, 255, 0.3);
}

.details-images .qr-code {
    max-width: 140px;
    border: 2px dashed #5b4eff;
}

/* Inspection Form */
.inspection-form {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(91, 78, 255, 0.2);
    max-width: 640px;
    margin: 2rem auto;
    position: relative;
    overflow: hidden;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.inspection-form:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 50px rgba(0, 0, 0, 0.15);
}

.inspection-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(91, 78, 255, 0.1), transparent);
    z-index: -1;
}

.inspection-form form {
    display: grid;
    gap: 1.5rem;
}

.form-group label {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.6rem;
    display: block;
    font-size: 1.1rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.9rem;
    border: 2px solid #e5e9f2;
    border-radius: 10px;
    background: #ffffff;
    color: #1e293b;
    font-size: 1rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #5b4eff;
    box-shadow: 0 0 10px rgba(91, 78, 255, 0.3);
    outline: none;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #64748b;
    font-style: italic;
}

.form-group select {
    appearance: none;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%235b4eff" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 1rem center;
    background-size: 14px;
    cursor: pointer;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-group input[type="file"] {
    padding: 0.8rem;
    border: 2px dashed #5b4eff;
    background: #f9fbfe;
    cursor: pointer;
    transition: border-color 0.3s ease, background 0.3s ease;
}

.form-group input[type="file"]:hover,
.form-group input[type="file"]:focus {
    border-color: #818cf8;
    background: #ffffff;
}

.action-btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 30px;
    background: linear-gradient(45deg, #5b4eff, #818cf8);
    color: #ffffff;
    font-weight: 600;
    font-size: 1.1rem;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(91, 78, 255, 0.4);
    background: linear-gradient(45deg, #818cf8, #a5b4fc);
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

/* Inspection History */
.inspection-history {
    background: linear-gradient(135deg, #ffffff, #f9fbfe);
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(91, 78, 255, 0.2);
    overflow-x: auto;
    position: relative;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.inspection-history:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 50px rgba(0, 0, 0, 0.15);
}

.inspection-history p {
    text-align: center;
    color: #64748b;
    font-size: 1.1rem;
    font-weight: 500;
}

.inspection-history table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
}

.inspection-history th,
.inspection-history td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e9f2;
}

.inspection-history th {
    background: linear-gradient(45deg, #1e293b, #5b4eff);
    color: #ffffff;
    font-weight: 600;
    font-size: 1rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.inspection-history td {
    color: #1e293b;
    font-size: 0.95rem;
}

.inspection-history tr:nth-child(even) {
    background: #f9fbfe;
}

.inspection-history tr:hover {
    background: rgba(91, 78, 255, 0.05);
}

.inspection-history img {
    max-width: 100px;
    height: auto;
    border-radius: 8px;
    border: 2px solid #5b4eff;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.inspection-history img:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(91, 78, 255, 0.3);
}

/* Footer */
footer {
    background: linear-gradient(90deg, #1e293b, #5b4eff);
    color: #ffffff;
    text-align: center;
    padding: 30px 0;
    margin-top: 3rem;
    box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.2);
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
    .nav-menu {
        display: none;
        flex-direction: column;
        position: fixed;
        top: 80px;
        right: -100%;
        width: 280px;
        height: 100vh;
        background: linear-gradient(135deg, #1e293b, #5b4eff);
        padding: 30px;
        transition: right 0.4s ease;
        box-shadow: -5px 0 20px rgba(0, 0, 0, 0.3);
    }

    .nav-menu.active {
        right: 0;
    }

    .nav-menu li {
        margin: 20px 0;
    }

    .menu-toggle {
        display: block;
    }

    .details-card {
        grid-template-columns: 1fr;
    }

    .details-images {
        flex-direction: row;
        justify-content: center;
        gap: 1.5rem;
    }

    .details-images img {
        max-width: 140px;
    }

    .inspection-form {
        padding: 1.5rem;
    }

    h2 {
        font-size: 2rem;
    }

    h3 {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .details-info {
        grid-template-columns: 1fr;
    }

    .info-box {
        padding: 1rem;
    }

    .inspection-history img {
        max-width: 80px;
    }

    .action-btn {
        padding: 0.8rem 1.5rem;
        font-size: 1rem;
    }

    .success, .error {
        font-size: 0.9rem;
        padding: 1rem;
    }

    .inspection-form {
        padding: 1.2rem;
    }

    h2 {
        font-size: 1.8rem;
    }

    h3 {
        font-size: 1.3rem;
    }
}
    </style>
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
                                    <strong><?php echo htmlspecialchars($extinguisher['code']); ?></strong>: 
                                    <?php echo htmlspecialchars($notification['message']); ?> 
                                    (Due: <?php echo date('M d, Y', strtotime($notification['due_date'])); ?>)
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="signup.php">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Extinguisher Details Section -->
    <section class="extinguisher-details">
        <div class="container">
            <h2>Fire Extinguisher Details - <?php echo htmlspecialchars($extinguisher['code']); ?></h2>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <!-- Extinguisher Details -->
            <div class="details-card">
                <div class="details-info">
                    <div class="info-box">
                        <strong><?php echo ucfirst($facility_type); ?></strong>
                        <span><?php echo htmlspecialchars($facility['name']); ?></span>
                    </div>
                    <div class="info-box">
                        <strong>Code</strong>
                        <span><?php echo htmlspecialchars($extinguisher['code']); ?></span>
                    </div>
                    <div class="info-box">
                        <strong>Location</strong>
                        <span><?php echo htmlspecialchars($extinguisher['location']); ?></span>
                    </div>
                    <div class="info-box">
                        <strong>Type</strong>
                        <span><?php echo htmlspecialchars($extinguisher['type']); ?></span>
                    </div>
                    <div class="info-box">
                        <strong>Status</strong>
                        <span class="status-<?php echo strtolower($extinguisher['status']); ?>">
                            <?php echo htmlspecialchars($extinguisher['status']); ?>
                        </span>
                    </div>
                    <div class="info-box">
                        <strong>Last Inspection</strong>
                        <span><?php echo $extinguisher['last_inspection'] ? date('M d, Y', strtotime($extinguisher['last_inspection'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-box">
                        <strong>Notes</strong>
                        <span><?php echo $extinguisher['notes'] ? htmlspecialchars($extinguisher['notes']) : 'None'; ?></span>
                    </div>
                </div>
                <div class="details-images">
                    <?php if ($extinguisher['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($extinguisher['image_url']); ?>" alt="Extinguisher Image">
                    <?php endif; ?>
                    <?php if ($extinguisher['qr_code_url']): ?>
                        <img src="<?php echo htmlspecialchars($extinguisher['qr_code_url']); ?>" alt="QR Code" class="qr-code">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Inspection Form -->
            <div class="inspection-form">
                <h3>Add New Inspection</h3>
                <form action="extinguisher_details.php?id=<?php echo $extinguisher_id; ?>&<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="inspector">Inspector Name</label>
                        <input type="text" id="inspector" name="inspector" placeholder="Enter inspector name" required value="<?php echo isset($_POST['inspector']) ? htmlspecialchars($_POST['inspector']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="inspection_date">Inspection Date</label>
                        <input type="date" id="inspection_date" name="inspection_date" required value="<?php echo isset($_POST['inspection_date']) ? htmlspecialchars($_POST['inspection_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="Green" <?php echo isset($_POST['status']) && $_POST['status'] === 'Green' ? 'selected' : ''; ?>>Green (OK)</option>
                            <option value="Red" <?php echo isset($_POST['status']) && $_POST['status'] === 'Red' ? 'selected' : ''; ?>>Red (Needs Maintenance)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="note">Notes</label>
                        <textarea id="note" name="note" placeholder="Enter inspection notes"><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="image">Upload Image</label>
                        <input type="file" id="image" name="image" accept="image/*">
                    </div>
                    <button type="submit" class="action-btn">Add Inspection</button>
                </form>
            </div>

            <!-- Inspection History -->
            <div class="inspection-history">
                <h3>Inspection History</h3>
                <?php if ($inspections->num_rows === 0): ?>
                    <p>No inspection records found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Inspector</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($inspection = $inspections->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($inspection['inspection_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($inspection['inspector']); ?></td>
                                    <td>
                                        <span class="status-<?php echo strtolower($inspection['status']); ?>">
                                            <?php echo htmlspecialchars($inspection['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $inspection['note'] ? htmlspecialchars($inspection['note']) : 'None'; ?></td>
                                    <td>
                                        <?php if ($inspection['image']): ?>
                                            <img src="<?php echo htmlspecialchars($inspection['image']); ?>" alt="Inspection Image">
                                        <?php else: ?>
                                            None
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
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

            // Toggle notification dropdown
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