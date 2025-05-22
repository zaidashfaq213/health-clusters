<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized action.";
    header("Location: index.php");
    exit();
}

$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : NULL;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : NULL;
$extinguisher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate facility and extinguisher IDs
if ($extinguisher_id <= 0 || ($hospital_id === NULL && $health_center_id === NULL)) {
    $_SESSION['error'] = "Invalid facility or extinguisher ID.";
    error_log("edit_extinguisher.php: Invalid inputs: extinguisher_id=$extinguisher_id, hospital_id=$hospital_id, health_center_id=$health_center_id");
    header("Location: index.php");
    exit();
}

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
if ($health_center_id !== NULL) {
    // Assuming manager can manage any health center for simplicity
}

// Fetch facility details
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
    error_log("edit_extinguisher.php: Database error for {$facility_type}_id=" . ($hospital_id ?: $health_center_id) . ": " . $conn->error);
    header("Location: index.php");
    exit();
}
$facility = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$facility) {
    $_SESSION['error'] = ucfirst($facility_type) . " not found.";
    error_log("edit_extinguisher.php: " . ucfirst($facility_type) . " not found for id=" . ($hospital_id ?: $health_center_id));
    header("Location: index.php");
    exit();
}

// Fetch extinguisher details
$stmt = $conn->prepare("SELECT code, location, type, status, notes, image_url, qr_code_url FROM fireextinguishers WHERE id = ? AND (hospital_id = ? OR health_center_id = ?)");
$stmt->bind_param("iii", $extinguisher_id, $hospital_id, $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("edit_extinguisher.php: Database error for extinguisher_id=$extinguisher_id: " . $conn->error);
    header("Location: extinguishers.php?{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
    exit();
}
$extinguisher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$extinguisher) {
    $_SESSION['error'] = "Extinguisher not found.";
    error_log("edit_extinguisher.php: Extinguisher not found for id=$extinguisher_id");
    header("Location: extinguishers.php?{$facility_type}_id=" . ($hospital_id ?: $health_center_id));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Extinguisher - <?php echo htmlspecialchars($facility['name'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
       /* Root Variables for Consistent Color Scheme */
:root {
    --primary-color:rgb(20, 40, 93); /* Deep Blue */
    --secondary-color:rgb(16, 55, 117); /* Bright Blue */
    --accent-color: #F59E0B; /* Warm Amber */
    --text-color: #1F2937; /* Dark Gray */
    --background-color: #F9FAFB; /* Light Gray */
    --white: #FFFFFF; /* White */
    --shadow-color: rgba(0, 0, 0, 0.1); /* Subtle shadow */
    --success-color: #10B981; /* Emerald Green */
    --error-color: #EF4444; /* Red */
}

/* Reset and General Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    color: var(--text-color);
    background: var(--background-color);
    line-height: 1.6;
    overflow-x: hidden;
}

/* Container */
.container {
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

/* Navbar */
.navbar {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 15px var(--shadow-color);
    transition: background 0.3s ease;
}

.navbar.sticky {
    background: var(--primary-color);
}

.nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--accent-color);
    transition: transform 0.3s ease;
}

.logo-circle:hover {
    transform: scale(1.05);
}

.logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.nav-menu {
    display: flex;
    list-style: none;
}

.nav-menu li {
    margin-left: 30px;
}

.nav-menu a {
    color: var(--white);
    text-decoration: none;
    font-weight: 500;
    font-size: 1.1rem;
    transition: color 0.3s ease, transform 0.2s ease;
}

.nav-menu a:hover {
    color: var(--accent-color);
    transform: translateY(-2px);
}

.nav-menu a.btn {
    background: var(--accent-color);
    color: var(--primary-color);
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease;
}

.nav-menu a.btn:hover {
    background: var(--white);
    color: var(--primary-color);
    transform: scale(1.05);
}

.nav-menu a.signup {
    background: var(--white);
    color: var(--primary-color);
}

.nav-menu a.signup:hover {
    background: var(--accent-color);
}

.menu-toggle {
    display: none;
    font-size: 1.8rem;
    color: var(--white);
    cursor: pointer;
}

/* Extinguisher Form Section */
.extinguisher-content {
    padding: 60px 0;
    background: linear-gradient(to bottom, var(--background-color), var(--white));
    animation: fadeIn 0.5s ease-out;
}

h2, h3 {
    color: var(--primary-color);
    margin-bottom: 20px;
    position: relative;
}

h2 {
    font-size: 2.5rem;
    text-align: center;
}

h2::after {
    content: '';
    width: 80px;
    height: 4px;
    background: var(--accent-color);
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 2px;
}

h3 {
    font-size: 1.8rem;
}

.success, .error {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.success {
    background: #d1fae5;
    color: var(--success-color);
}

.error {
    background: #fee2e2;
    color: var(--error-color);
}

/* Extinguisher Form */
.extinguisher-form {
    background: var(--white);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 10px 30px var(--shadow-color);
    margin-bottom: 30px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    transition: transform 0.3s ease;
}

.extinguisher-form:hover {
    transform: translateY(-5px);
}

.extinguisher-form form {
    display: grid;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px;
    border: 2px solid var(--primary-color);
    border-radius: 8px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #9ca3af;
    font-style: italic;
}

.form-group select {
    appearance: none;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%231E3A8A" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 12px center;
    background-size: 12px;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.media-upload p {
    margin-top: 10px;
    font-size: 0.9rem;
}

.media-upload img {
    max-width: 100px;
    border-radius: 8px;
    border: 2px solid var(--primary-color);
}

.action-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
}

.action-btn:hover {
    background: linear-gradient(135deg, var(--accent-color), #fbbf24);
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px var(--shadow-color);
}

.action-btn.primary-btn {
    background: linear-gradient(135deg, var(--accent-color), #fbbf24);
    color: var(--primary-color);
}

.action-btn.primary-btn:hover {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
}

/* Footer */
footer {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    text-align: center;
    padding: 20px 0;
    margin-top: 40px;
}

footer p {
    font-size: 0.9rem;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .nav-menu {
        display: none;
        flex-direction: column;
        position: absolute;
        top: 80px;
        left: 0;
        width: 100%;
        background: var(--primary-color);
        padding: 20px;
    }

    .nav-menu.active {
        display: flex;
    }

    .nav-menu li {
        margin: 15px 0;
    }

    .menu-toggle {
        display: block;
    }

    .extinguisher-content {
        padding: 40px 0;
    }

    h2 {
        font-size: 2rem;
    }

    .extinguisher-form {
        padding: 20px;
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
        padding: 10px;
    }

    .action-btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }

    .success, .error {
        font-size: 0.9rem;
        padding: 10px;
    }

    .media-upload img {
        max-width: 80px;
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
                <li><a href="extinguishers.php?<?php echo $facility_type; ?>_id=<?php echo ($hospital_id ?: $health_center_id); ?>">Fire Extinguishers</a></li>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Edit Extinguisher Section -->
    <section class="extinguisher-content">
        <div class="container">
            <h2>Edit Extinguisher - <?php echo htmlspecialchars($facility['name'] ?? ''); ?></h2>
            <!-- Feedback Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success'] ?? ''); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error'] ?? ''); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <div class="extinguisher-form">
                <form action="update_extinguisher.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="<?php echo $facility_type; ?>_id" value="<?php echo ($hospital_id ?: $health_center_id); ?>">
                    <input type="hidden" name="extinguisher_id" value="<?php echo $extinguisher_id; ?>">
                    <div class="form-group">
                        <label for="code">Extinguisher Code</label>
                        <input type="text" id="code" name="code" value="<?php echo htmlspecialchars($extinguisher['code'] ?? ''); ?>" placeholder="Enter extinguisher code" required>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($extinguisher['location'] ?? ''); ?>" placeholder="Enter location" required>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select id="type" name="type" required>
                            <option value="Water" <?php echo $extinguisher['type'] === 'Water' ? 'selected' : ''; ?>>Water</option>
                            <option value="Foam" <?php echo $extinguisher['type'] === 'Foam' ? 'selected' : ''; ?>>Foam</option>
                            <option value="CO2" <?php echo $extinguisher['type'] === 'CO2' ? 'selected' : ''; ?>>CO2</option>
                            <option value="Dry Powder" <?php echo $extinguisher['type'] === 'Dry Powder' ? 'selected' : ''; ?>>Dry Powder</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="Green" <?php echo $extinguisher['status'] === 'Green' ? 'selected' : ''; ?>>Green (OK)</option>
                            <option value="Red" <?php echo $extinguisher['status'] === 'Red' ? 'selected' : ''; ?>>Red (Needs Maintenance)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Enter notes"><?php echo htmlspecialchars($extinguisher['notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group media-upload">
                        <label for="image">Upload New Image</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <?php if ($extinguisher['image_url']): ?>
                            <p>Current Image: 
                                <img src="<?php echo htmlspecialchars($extinguisher['image_url'] ?? ''); ?>" alt="Current Extinguisher Image">
                            </p>
                            <label><input type="checkbox" name="remove_image" value="1"> Remove Current Image</label>
                        <?php endif; ?>
                    </div>
                    <div class="form-group media-upload">
                        <label for="qr_code">Upload New QR Code Image</label>
                        <input type="file" id="qr_code" name="qr_code" accept="image/*">
                        <?php if ($extinguisher['qr_code_url']): ?>
                            <p>Current QR Code: 
                                <img src="<?php echo htmlspecialchars($extinguisher['qr_code_url'] ?? ''); ?>" alt="Current QR Code" style="max-width: 80px;">
                            </p>
                            <label><input type="checkbox" name="remove_qr_code" value="1"> Remove Current QR Code</label>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="action-btn primary-btn">Update Extinguisher</button>
                </form>
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
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            if (menuToggle && navMenu) {
                menuToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>