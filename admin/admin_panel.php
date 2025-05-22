<?php
require_once '../includes/config.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Please log in as an admin.";
    header("Location: admin-login.php");
    exit();
}

// Debugging: Log session data
error_log("admin_panel.php: Session Data: " . print_r($_SESSION, true));

// Handle section addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name'] ?? '');
    $parent_section_id = !empty($_POST['parent_section_id']) ? (int)$_POST['parent_section_id'] : null;
    $is_fixed = isset($_POST['is_fixed']) ? 1 : 0;

    if (empty($section_name)) {
        $_SESSION['error'] = "Section name is required.";
    } elseif (!isset($_SESSION['admin_id']) || !is_numeric($_SESSION['admin_id'])) {
        $_SESSION['error'] = "Admin session invalid. Please log in again.";
        error_log("admin_panel.php: Admin session invalid during section addition");
    } else {
        // Insert into sections
        $section_stmt = $conn->prepare("INSERT INTO sections (name, parent_section_id, is_fixed) VALUES (?, ?, ?)");
        $section_stmt->bind_param("sii", $section_name, $parent_section_id, $is_fixed);
        if ($section_stmt->execute()) {
            $section_id = $conn->insert_id;
            // Insert audit log
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $action = 'add_section';
            $target_type = 'section';
            $details = "Admin {$_SESSION['admin_id']} added global section: $section_name";
            $admin_id = (int)$_SESSION['admin_id'];
            $audit_stmt->bind_param("isiss", $admin_id, $action, $section_id, $target_type, $details);
            if ($audit_stmt->execute()) {
                $_SESSION['success'] = "Section added successfully.";
            } else {
                $_SESSION['error'] = "Error logging audit: " . $audit_stmt->error;
                error_log("admin_panel.php: Error logging audit: " . $audit_stmt->error);
            }
            $audit_stmt->close();
        } else {
            $_SESSION['error'] = "Error adding section: " . $section_stmt->error;
            error_log("admin_panel.php: Error adding section: " . $section_stmt->error);
        }
        $section_stmt->close();
    }
    header("Location: admin_panel.php");
    exit();
}

// Handle section editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_section'])) {
    $section_id = (int)($_POST['section_id'] ?? 0);
    $section_name = trim($_POST['section_name'] ?? '');
    $is_fixed = isset($_POST['is_fixed']) ? 1 : 0;

    if ($section_id <= 0) {
        $_SESSION['error'] = "Invalid section ID.";
    } elseif (empty($section_name)) {
        $_SESSION['error'] = "Section name is required.";
    } elseif (!isset($_SESSION['admin_id']) || !is_numeric($_SESSION['admin_id'])) {
        $_SESSION['error'] = "Admin session invalid. Please log in again.";
        error_log("admin_panel.php: Admin session invalid during section editing");
    } else {
        $update_stmt = $conn->prepare("UPDATE sections SET name = ?, is_fixed = ? WHERE id = ?");
        $update_stmt->bind_param("sii", $section_name, $is_fixed, $section_id);
        if ($update_stmt->execute()) {
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $action = 'edit_section';
            $target_type = 'section';
            $details = "Admin {$_SESSION['admin_id']} edited global section ID: $section_id to name: $section_name";
            $admin_id = (int)$_SESSION['admin_id'];
            $audit_stmt->bind_param("isiss", $admin_id, $action, $section_id, $target_type, $details);
            if ($audit_stmt->execute()) {
                $_SESSION['success'] = "Section updated successfully.";
            } else {
                $_SESSION['error'] = "Error logging audit: " . $audit_stmt->error;
                error_log("admin_panel.php: Error logging audit: " . $audit_stmt->error);
            }
            $audit_stmt->close();
        } else {
            $_SESSION['error'] = "Error updating section: " . $update_stmt->error;
            error_log("admin_panel.php: Error updating section: " . $update_stmt->error);
        }
        $update_stmt->close();
    }
    header("Location: admin_panel.php");
    exit();
}

// Handle section deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_section'])) {
    $section_id = (int)($_POST['section_id'] ?? 0);

    if ($section_id <= 0) {
        $_SESSION['error'] = "Invalid section ID.";
    } elseif (!isset($_SESSION['admin_id']) || !is_numeric($_SESSION['admin_id'])) {
        $_SESSION['error'] = "Admin session invalid. Please log in again.";
        error_log("admin_panel.php: Admin session invalid during section deletion");
    } else {
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sections WHERE parent_section_id = ?");
        $check_stmt->bind_param("i", $section_id);
        $check_stmt->execute();
        $sub_sections = $check_stmt->get_result()->fetch_assoc()['count'];
        $check_stmt->close();

        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM topics WHERE section_id = ?");
        $check_stmt->bind_param("i", $section_id);
        $check_stmt->execute();
        $topics = $check_stmt->get_result()->fetch_assoc()['count'];
        $check_stmt->close();

        $check_stmt = $conn->prepare("SELECT name, is_fixed FROM sections WHERE id = ?");
        $check_stmt->bind_param("i", $section_id);
        $check_stmt->execute();
        $section = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($sub_sections > 0 || $topics > 0) {
            $_SESSION['error'] = "Cannot delete section with sub-sections or topics.";
        } elseif ($section['is_fixed']) {
            $_SESSION['error'] = "Cannot delete fixed section.";
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $delete_stmt->bind_param("i", $section_id);
            if ($delete_stmt->execute()) {
                $audit_stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $action = 'delete_section';
                $target_type = 'section';
                $details = "Admin {$_SESSION['admin_id']} deleted global section: {$section['name']} (ID: $section_id)";
                $admin_id = (int)$_SESSION['admin_id'];
                $audit_stmt->bind_param("isiss", $admin_id, $action, $section_id, $target_type, $details);
                if ($audit_stmt->execute()) {
                    $_SESSION['success'] = "Section deleted successfully.";
                } else {
                    $_SESSION['error'] = "Error logging audit: " . $audit_stmt->error;
                    error_log("admin_panel.php: Error logging audit: " . $audit_stmt->error);
                }
                $audit_stmt->close();
            } else {
                $_SESSION['error'] = "Error deleting section: " . $delete_stmt->error;
                error_log("admin_panel.php: Error deleting section: " . $delete_stmt->error);
            }
            $delete_stmt->close();
        }
    }
    header("Location: admin_panel.php");
    exit();
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file_id = (int)($_POST['file_id'] ?? 0);

    if ($file_id <= 0) {
        $_SESSION['error'] = "Invalid file ID.";
    } elseif (!isset($_SESSION['admin_id']) || !is_numeric($_SESSION['admin_id'])) {
        $_SESSION['error'] = "Admin session invalid. Please log in again.";
        error_log("admin_panel.php: Admin session invalid during file deletion");
    } else {
        // Fetch file path
        $select_stmt = $conn->prepare("SELECT file_path FROM form_uploads WHERE id = ?");
        $select_stmt->bind_param("i", $file_id);
        $select_stmt->execute();
        $file = $select_stmt->get_result()->fetch_assoc();
        $select_stmt->close();

        if ($file) {
            $file_path = $file['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Delete from form_uploads
            $delete_stmt = $conn->prepare("DELETE FROM form_uploads WHERE id = ?");
            $delete_stmt->bind_param("i", $file_id);
            if ($delete_stmt->execute()) {
                // Insert into audit_logs
                $audit_stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $action = 'delete_file';
                $target_type = 'form_upload';
                $details = "Admin {$_SESSION['admin_id']} deleted file ID: $file_id";
                $admin_id = (int)$_SESSION['admin_id'];
                $audit_stmt->bind_param("isiss", $admin_id, $action, $file_id, $target_type, $details);
                if ($audit_stmt->execute()) {
                    $_SESSION['success'] = "File deleted successfully.";
                } else {
                    $_SESSION['error'] = "Error logging audit: " . $audit_stmt->error;
                    error_log("admin_panel.php: Error logging audit: " . $audit_stmt->error);
                }
                $audit_stmt->close();
            } else {
                $_SESSION['error'] = "Error deleting file: " . $delete_stmt->error;
                error_log("admin_panel.php: Error deleting file: " . $delete_stmt->error);
            }
            $delete_stmt->close();
        } else {
            $_SESSION['error'] = "File not found.";
        }
    }
    header("Location: admin_panel.php");
    exit();
}

// Handle alarm deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_alarm'])) {
    $alarm_id = (int)($_POST['alarm_id'] ?? 0);

    if ($alarm_id <= 0) {
        $_SESSION['error'] = "Invalid alarm ID.";
    } elseif (!isset($_SESSION['admin_id']) || !is_numeric($_SESSION['admin_id'])) {
        $_SESSION['error'] = "Admin session invalid. Please log in again.";
        error_log("admin_panel.php: Admin session invalid during alarm deletion");
    } else {
        // Fetch alarm details for audit log
        $select_stmt = $conn->prepare("SELECT alarm_type, location FROM alarms WHERE id = ?");
        $select_stmt->bind_param("i", $alarm_id);
        $select_stmt->execute();
        $alarm = $select_stmt->get_result()->fetch_assoc();
        $select_stmt->close();

        if ($alarm) {
            // Delete alarm
            $delete_stmt = $conn->prepare("DELETE FROM alarms WHERE id = ?");
            $delete_stmt->bind_param("i", $alarm_id);
            if ($delete_stmt->execute()) {
                // Insert into audit_logs
                $audit_stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_id, target_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $action = 'delete_alarm';
                $target_type = 'alarm';
                $details = "Admin {$_SESSION['admin_id']} deleted alarm ID: $alarm_id ({$alarm['alarm_type']} at {$alarm['location']})";
                $admin_id = (int)$_SESSION['admin_id'];
                $audit_stmt->bind_param("isiss", $admin_id, $action, $alarm_id, $target_type, $details);
                if ($audit_stmt->execute()) {
                    $_SESSION['success'] = "Alarm deleted successfully.";
                } else {
                    $_SESSION['error'] = "Error logging audit: " . $audit_stmt->error;
                    error_log("admin_panel.php: Error logging audit: " . $audit_stmt->error);
                }
                $audit_stmt->close();
            } else {
                $_SESSION['error'] = "Error deleting alarm: " . $delete_stmt->error;
                error_log("admin_panel.php: Error deleting alarm: " . $delete_stmt->error);
            }
            $delete_stmt->close();
        } else {
            $_SESSION['error'] = "Alarm not found.";
        }
    }
    header("Location: admin_panel.php");
    exit();
}

// Fetch hospitals for dropdowns
$stmt = $conn->query("SELECT id, name FROM hospitals ORDER BY name");
$hospitals = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all sections globally
$sections = [];
$stmt = $conn->prepare("SELECT id, name, parent_section_id, is_fixed FROM sections WHERE parent_section_id IS NULL");
$stmt->execute();
$result = $stmt->get_result();
while ($section = $result->fetch_assoc()) {
    $sub_stmt = $conn->prepare("SELECT id, name, is_fixed FROM sections WHERE parent_section_id = ?");
    $sub_stmt->bind_param("i", $section['id']);
    $sub_stmt->execute();
    $section['sub_sections'] = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sub_stmt->close();
    $sections[] = $section;
}
$stmt->close();

// Fetch uploaded files
$uploads = [];
$form_types = ['safety_systems', 'employee_training', 'environmental_tours', 'evacuation_plan', 'meeting_committee', 'building_safety'];
foreach ($form_types as $form_type) {
    $stmt = $conn->prepare("SELECT id, file_path, uploaded_at, form_type, hospital_id FROM form_uploads WHERE form_type = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("s", $form_type);
    $stmt->execute();
    $uploads[$form_type] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch alarms
$alarms = [];
$stmt = $conn->query("SELECT a.id, a.hospital_id, a.health_center_id, a.alarm_type, a.location, a.status, a.alarm_time, h.name as hospital_name, hc.name as health_center_name 
                      FROM alarms a 
                      LEFT JOIN hospitals h ON a.hospital_id = h.id 
                      LEFT JOIN health_centers hc ON a.health_center_id = hc.id 
                      ORDER BY a.alarm_time DESC");
$alarms = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .alarm-list { margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; }
        .alarm { border: 1px solid #ffeeba; padding: 10px; margin-bottom: 10px; border-radius: 5px; }
        .alarm.active { background: #f8d7da; }
        .alarm.resolved { background: #d4edda; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container container">
            <div class="logo-circle">
                <img src="../assets/images/logo.png" alt="Northern Borders Health Cluster Logo">
            </div>
            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
            <ul class="nav-menu">
                <li><a href="admin_panel.php" class="active">Dashboard</a></li>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Admin Panel Section -->
    <section class="admin-panel">
        <div class="container">
            <h1>Admin Dashboard</h1>
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" data-tab="users">Users</button>
                <button class="tab-btn" data-tab="hospitals">Hospitals</button>
                <button class="tab-btn" data-tab="health-centers">Health Centers</button>
                <button class="tab-btn" data-tab="sections">Sections</button>
                <button class="tab-btn" data-tab="forms">Forms</button>
                <button class="tab-btn" data-tab="alarms">Alarms</button>
                <button class="tab-btn" data-tab="logs">Audit Logs</button>
            </div>

            <!-- Users Tab -->
            <div class="tab-content active" id="users">
                <div class="panel-card">
                    <h2>Manage Users</h2>
                    <!-- Add User -->
                    <form action="add_user.php" method="POST" class="admin-form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" placeholder="Enter username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" placeholder="Enter email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" placeholder="Enter password" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select name="role" id="role" required>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hospital_id">Assign Hospital (for Managers)</label>
                            <select name="hospital_id" id="hospital_id">
                                <option value="">-- None --</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo $hospital['id']; ?>"><?php echo htmlspecialchars($hospital['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="health_center_id">Assign Health Center (for Managers)</label>
                            <select name="health_center_id" id="health_center_id">
                                <option value="">-- None --</option>
                                <?php
                                $stmt = $conn->query("SELECT id, name FROM health_centers ORDER BY name");
                                while ($health_center = $stmt->fetch_assoc()) {
                                    echo "<option value='{$health_center['id']}'>" . htmlspecialchars($health_center['name']) . "</option>";
                                }
                                $stmt->close();
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="action-btn primary-btn">Add User</button>
                    </form>

                    <!-- Users List -->
                    <div class="list-section">
                        <h3>Current Users</h3>
                        <?php
                        $stmt = $conn->query("SELECT u.id, u.username, u.email, u.role, h.id AS hospital_id, h.name AS hospital_name, hc.id AS health_center_id, hc.name AS health_center_name FROM users u LEFT JOIN hospitals h ON u.hospital_id = h.id LEFT JOIN health_centers hc ON u.health_center_id = hc.id");
                        if ($stmt->num_rows === 0) {
                            echo "<p>No users found.</p>";
                        } else {
                            while ($user = $stmt->fetch_assoc()) {
                                ?>
                                <div class="list-item">
                                    <span>
                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>) - 
                                        <?php echo ucfirst($user['role']); ?>
                                        <?php
                                        if ($user['hospital_name']) {
                                            echo " (Hospital: " . htmlspecialchars($user['hospital_name']) . ")";
                                        } elseif ($user['health_center_name']) {
                                            echo " (Health Center: " . htmlspecialchars($user['health_center_name']) . ")";
                                        }
                                        ?>
                                    </span>
                                    <div class="actions">
                                        <button class="action-btn edit-btn" onclick="openEditUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $user['hospital_id'] ? $user['hospital_id'] : 'null'; ?>, <?php echo $user['health_center_id'] ? $user['health_center_id'] : 'null'; ?>)">Edit</button>
                                        <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        $stmt->close();
                        ?>
                    </div>
                </div>
            </div>

            <!-- Hospitals Tab -->
            <div class="tab-content" id="hospitals">
                <div class="panel-card">
                    <h2>Manage Hospitals</h2>
                    <!-- Add Hospital -->
                    <form action="add_hospital.php" method="POST" class="admin-form">
                        <div class="form-group">
                            <label for="hospital_name">Hospital Name</label>
                            <input type="text" name="hospital_name" id="hospital_name" placeholder="Enter hospital name" required>
                        </div>
                        <div class="form-group">
                            <label for="leader">Leader</label>
                            <input type="text" name="leader" id="leader" placeholder="Enter leader name" required>
                        </div>
                        <button type="submit" class="action-btn primary-btn">Add Hospital</button>
                    </form>

                    <!-- Hospitals List -->
                    <div class="list-section">
                        <h3>Current Hospitals</h3>
                        <?php
                        $stmt = $conn->query("SELECT id, name, leader FROM hospitals ORDER BY name");
                        if ($stmt->num_rows === 0) {
                            echo "<p>No hospitals found.</p>";
                        } else {
                            while ($hospital = $stmt->fetch_assoc()) {
                                ?>
                                <div class="list-item">
                                    <span><?php echo htmlspecialchars($hospital['name']); ?> - <?php echo htmlspecialchars($hospital['leader']); ?></span>
                                    <div class="actions">
                                        <button class="action-btn edit-btn" onclick="openEditHospital(<?php echo $hospital['id']; ?>, '<?php echo htmlspecialchars($hospital['name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($hospital['leader'], ENT_QUOTES, 'UTF-8'); ?>')">Edit</button>
                                        <a href="delete_hospital.php?id=<?php echo $hospital['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this hospital?');">Delete</a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        $stmt->close();
                        ?>
                    </div>
                </div>
            </div>

            <!-- Health Centers Tab -->
            <div class="tab-content" id="health-centers">
                <div class="panel-card">
                    <h2>Manage Health Centers</h2>
                    <!-- Add Health Center -->
                    <form action="add_health_center.php" method="POST" class="admin-form">
                        <div class="form-group">
                            <label for="health_center_name">Health Center Name</label>
                            <input type="text" name="health_center_name" id="health_center_name" placeholder="Enter health center name" required>
                        </div>
                        <div class="form-group">
                            <label for="leader">Leader</label>
                            <input type="text" name="leader" id="leader" placeholder="Enter leader name" required>
                        </div>
                        <div class="form-group">
                            <label for="region">Region</label>
                            <select name="region" id="region" required>
                                <option value="Arar">Arar</option>
                                <option value="Rafha">Rafha</option>
                                <option value="Turaif">Turaif</option>
                                <option value="Al-Uwayqilah">Al-Uwayqilah</option>
                                <option value="Rawdat Habbas">Rawdat Habbas</option>
                            </select>
                        </div>
                        <button type="submit" class="action-btn primary-btn">Add Health Center</button>
                    </form>

                    <!-- Health Centers List -->
                    <div class="list-section">
                        <h3>Current Health Centers</h3>
                        <?php
                        $stmt = $conn->query("SELECT id, name, leader, region FROM health_centers ORDER BY name");
                        if ($stmt->num_rows === 0) {
                            echo "<p>No health centers found.</p>";
                        } else {
                            while ($health_center = $stmt->fetch_assoc()) {
                                ?>
                                <div class="list-item">
                                    <span><?php echo htmlspecialchars($health_center['name']); ?> - <?php echo htmlspecialchars($health_center['leader']); ?> (<?php echo htmlspecialchars($health_center['region']); ?>)</span>
                                    <div class="actions">
                                        <button class="action-btn edit-btn" onclick="openEditHealthCenter(<?php echo $health_center['id']; ?>, '<?php echo htmlspecialchars($health_center['name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($health_center['leader'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($health_center['region'], ENT_QUOTES, 'UTF-8'); ?>')">Edit</button>
                                        <a href="delete_health_center.php?id=<?php echo $health_center['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this health center?');">Delete</a>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        $stmt->close();
                        ?>
                    </div>
                </div>
            </div>

            <!-- Sections Tab -->
            <div class="tab-content" id="sections">
                <div class="panel-card">
                    <h2>Manage Sections</h2>
                    <!-- Add Section -->
                    <form action="admin_panel.php" method="POST" class="admin-form">
                        <input type="hidden" name="add_section" value="1">
                        <div class="form-group">
                            <label for="section_name">Section Name</label>
                            <input type="text" name="section_name" id="section_name" placeholder="Enter section name" required>
                        </div>
                        <div class="form-group">
                            <label for="parent_section_id">Parent Section (Optional)</label>
                            <select name="parent_section_id" id="parent_section_id">
                                <option value="">None</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="is_fixed"> Fixed Section (Only editable by admins)</label>
                        </div>
                        <button type="submit" class="action-btn primary-btn">Add Section</button>
                    </form>

                    <!-- Sections List -->
                    <div class="list-section">
                        <h3>All Sections</h3>
                        <?php if (empty($sections)): ?>
                            <p>No sections found.</p>
                        <?php else: ?>
                            <?php foreach ($sections as $section): ?>
                                <div class="list-item">
                                    <span>
                                        <?php echo htmlspecialchars($section['name']); ?>
                                        <?php if ($section['is_fixed']): ?>
                                            <span class="status-fixed">Fixed</span>
                                        <?php endif; ?>
                                    </span>
                                    <div class="actions">
                                        <button class="action-btn edit-btn" onclick="openEditSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $section['is_fixed'] ? '1' : '0'; ?>)">Edit</button>
                                        <form action="admin_panel.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_section" value="1">
                                            <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                            <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this section?');" <?php echo $section['is_fixed'] ? 'disabled' : ''; ?>>Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <?php foreach ($section['sub_sections'] as $sub_section): ?>
                                    <div class="list-item sub-section">
                                        <span>
                                            -- <?php echo htmlspecialchars($sub_section['name']); ?>
                                            <?php if ($sub_section['is_fixed']): ?>
                                                <span class="status-fixed">Fixed</span>
                                            <?php endif; ?>
                                        </span>
                                        <div class="actions">
                                            <button class="action-btn edit-btn" onclick="openEditSection(<?php echo $sub_section['id']; ?>, '<?php echo htmlspecialchars($sub_section['name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $sub_section['is_fixed'] ? '1' : '0'; ?>)">Edit</button>
                                            <form action="admin_panel.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="delete_section" value="1">
                                                <input type="hidden" name="section_id" value="<?php echo $sub_section['id']; ?>">
                                                <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this sub-section?');" <?php echo $sub_section['is_fixed'] ? 'disabled' : ''; ?>>Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Forms Tab -->
            <div class="tab-content" id="forms">
                <div class="panel-card">
                    <h2>Manage Forms</h2>
                    <!-- Add Form -->
                    <form action="upload_form.php" method="POST" enctype="multipart/form-data" class="admin-form">
                        <div class="form-group">
                            <label for="hospital_id">Select Hospital</label>
                            <select id="hospital_id" name="hospital_id" required>
                                <option value="">Choose a hospital</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo htmlspecialchars($hospital['id']); ?>">
                                        <?php echo htmlspecialchars($hospital['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form_type">Form Type</label>
                            <select id="form_type" name="form_type" required>
                                <option value="">Select Form Type</option>
                                <option value="safety_systems">Safety Systems Follow-up</option>
                                <option value="employee_training">Employee Training</option>
                                <option value="environmental_tours">Environmental Tours</option>
                                <option value="evacuation_plan">Evacuation Plan</option>
                                <option value="meeting_committee">Meeting Committee</option>
                                <option value="building_safety">Building Safety Walkthrough</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form_file">Upload File</label>
                            <input type="file" id="form_file" name="form_file" accept=".pdf,.doc,.docx" required>
                        </div>
                        <button type="submit" class="action-btn primary-btn">Upload Form</button>
                    </form>

                    <!-- Uploaded Forms List -->
                    <div class="list-section">
                        <h3>Uploaded Forms</h3>
                        <?php foreach ($form_types as $form_type): ?>
                            <h4><?php echo ucwords(str_replace('_', ' ', $form_type)); ?></h4>
                            <?php if (empty($uploads[$form_type])): ?>
                                <p>No files uploaded.</p>
                            <?php else: ?>
                                <?php foreach ($uploads[$form_type] as $upload): ?>
                                    <div class="list-item">
                                        <span>
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                                <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                            </a>
                                            (Uploaded: <?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?>)
                                            <?php
                                            foreach ($hospitals as $hospital) {
                                                if ($hospital['id'] == $upload['hospital_id']) {
                                                    echo " - Hospital: " . htmlspecialchars($hospital['name']);
                                                }
                                            }
                                            ?>
                                        </span>
                                        <div class="actions">
                                            <form action="admin_panel.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="delete_file" value="1">
                                                <input type="hidden" name="file_id" value="<?php echo $upload['id']; ?>">
                                                <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this file?');">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Alarms Tab -->
            <div class="tab-content" id="alarms">
                <div class="panel-card">
                    <h2>Manage Alarms</h2>
                    <div class="list-section">
                        <h3>Fire Alarms</h3>
                        <?php if (empty($alarms)): ?>
                            <p>No alarms found.</p>
                        <?php else: ?>
                            <?php foreach ($alarms as $alarm): ?>
                                <div class="list-item alarm <?php echo strtolower($alarm['status']); ?>">
                                    <span>
                                        <strong>Type:</strong> <?php echo htmlspecialchars($alarm['alarm_type']); ?><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($alarm['location']); ?><br>
                                        <strong>Time:</strong> <?php echo date('Y-m-d H:i', strtotime($alarm['alarm_time'])); ?><br>
                                        <strong>Status:</strong> <?php echo htmlspecialchars($alarm['status']); ?><br>
                                        <strong>Location:</strong> 
                                        <?php 
                                        echo $alarm['hospital_name'] 
                                            ? htmlspecialchars($alarm['hospital_name']) . ' (Hospital)'
                                            : htmlspecialchars($alarm['health_center_name']) . ' (Health Center)';
                                        ?>
                                    </span>
                                    <div class="actions">
                                        <form action="admin_panel.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_alarm" value="1">
                                            <input type="hidden" name="alarm_id" value="<?php echo $alarm['id']; ?>">
                                            <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this alarm?');">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Audit Logs Tab -->
            <div class="tab-content" id="logs">
                <div class="panel-card">
                    <h2>Audit Logs</h2>
                    <div class="list-section">
                        <h3>Recent Admin Actions</h3>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT admin_id, action, target_id, target_type, details, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 50");
                            if ($stmt->num_rows === 0) {
                                echo "<p>No logs found.</p>";
                            } else {
                                while ($log = $stmt->fetch_assoc()) {
                                    ?>
                                    <div class="list-item">
                                        <span>
                                            <?php echo htmlspecialchars($log['action']); ?> 
                                            (<?php echo ucfirst($log['target_type']); ?>) 
                                            at <?php echo htmlspecialchars($log['created_at']); ?>
                                            <br><small><?php echo htmlspecialchars($log['details'] ?? 'No details available'); ?></small>
                                        </span>
                                    </div>
                                    <?php
                                }
                            }
                            $stmt->close();
                        } catch (Exception $e) {
                            echo "<p>Error fetching logs: " . htmlspecialchars($e->getMessage()) . "</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Edit Modals -->
            <div class="modal" id="edit-user-modal">
                <div class="modal-content">
                    <h2>Edit User</h2>
                    <form action="update_user.php" method="POST" class="admin-form">
                        <input type="hidden" name="user_id" id="edit-user-id">
                        <div class="form-group">
                            <label for="edit-username">Username</label>
                            <input type="text" name="username" id="edit-username" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-email">Email</label>
                            <input type="email" name="email" id="edit-email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-role">Role</label>
                            <select name="role" id="edit-role" required>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-hospital_id">Assign Hospital</label>
                            <select name="hospital_id" id="edit-hospital_id">
                                <option value="">-- None --</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo $hospital['id']; ?>"><?php echo htmlspecialchars($hospital['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-health_center_id">Assign Health Center</label>
                            <select name="health_center_id" id="edit-health_center_id">
                                <option value="">-- None --</option>
                                <?php
                                $stmt = $conn->query("SELECT id, name FROM health_centers ORDER BY name");
                                while ($health_center = $stmt->fetch_assoc()) {
                                    echo "<option value='{$health_center['id']}'>" . htmlspecialchars($health_center['name']) . "</option>";
                                }
                                $stmt->close();
                                ?>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="action-btn primary-btn">Save</button>
                            <button type="button" class="action-btn cancel-btn" onclick="closeModal('edit-user-modal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal" id="edit-hospital-modal">
                <div class="modal-content">
                    <h2>Edit Hospital</h2>
                    <form action="update_hospital.php" method="POST" class="admin-form">
                        <input type="hidden" name="hospital_id" id="edit-hospital-id">
                        <div class="form-group">
                            <label for="edit-hospital-name">Hospital Name</label>
                            <input type="text" name="hospital_name" id="edit-hospital-name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-leader">Leader</label>
                            <input type="text" name="leader" id="edit-leader" required>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="action-btn primary-btn">Save</button>
                            <button type="button" class="action-btn cancel-btn" onclick="closeModal('edit-hospital-modal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal" id="edit-health-center-modal">
                <div class="modal-content">
                    <h2>Edit Health Center</h2>
                    <form action="update_health_center.php" method="POST" class="admin-form">
                        <input type="hidden" name="health_center_id" id="edit-health-center-id">
                        <div class="form-group">
                            <label for="edit-health-center-name">Health Center Name</label>
                            <input type="text" name="health_center_name" id="edit-health-center-name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-leader">Leader</label>
                            <input type="text" name="leader" id="edit-leader" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-region">Region</label>
                            <select name="region" id="edit-region" required>
                                <option value="Arar">Arar</option>
                                <option value="Rafha">Rafha</option>
                                <option value="Turaif">Turaif</option>
                                <option value="Al-Uwayqilah">Al-Uwayqilah</option>
                                <option value="Rawdat Habbas">Rawdat Habbas</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="action-btn primary-btn">Save</button>
                            <button type="button" class="action-btn cancel-btn" onclick="closeModal('edit-health-center-modal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal" id="edit-section-modal">
                <div class="modal-content">
                    <h2>Edit Section</h2>
                    <form action="admin_panel.php" method="POST" class="admin-form">
                        <input type="hidden" name="edit_section" value="1">
                        <input type="hidden" name="section_id" id="edit-section-id">
                        <div class="form-group">
                            <label for="edit-section-name">Section Name</label>
                            <input type="text" name="section_name" id="edit-section-name" required>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="is_fixed" id="edit-is-fixed"> Fixed Section (Only editable by admins)</label>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="action-btn primary-btn">Save</button>
                            <button type="button" class="action-btn cancel-btn" onclick="closeModal('edit-section-modal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript -->
    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });

        // Menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const navMenu = document.querySelector('.nav-menu');
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }

        // Modal handling
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Edit User
        function openEditUser(id, username, email, role, hospitalId, healthCenterId) {
            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            document.getElementById('edit-hospital_id').value = hospitalId || '';
            document.getElementById('edit-health_center_id').value = healthCenterId || '';
            openModal('edit-user-modal');
        }

        // Edit Hospital
        function openEditHospital(id, name, leader) {
            document.getElementById('edit-hospital-id').value = id;
            document.getElementById('edit-hospital-name').value = name;
            document.getElementById('edit-leader').value = leader;
            openModal('edit-hospital-modal');
        }

        // Edit Health Center
        function openEditHealthCenter(id, name, leader, region) {
            document.getElementById('edit-health-center-id').value = id;
            document.getElementById('edit-health-center-name').value = name;
            document.getElementById('edit-leader').value = leader;
            document.getElementById('edit-region').value = region;
            openModal('edit-health-center-modal');
        }

        // Edit Section
        function openEditSection(id, name, isFixed) {
            document.getElementById('edit-section-id').value = id;
            document.getElementById('edit-section-name').value = name;
            document.getElementById('edit-is-fixed').checked = isFixed;
            openModal('edit-section-modal');
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>