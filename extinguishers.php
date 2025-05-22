<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : 0;
$user_role = $_SESSION['role'] ?? 'user';

if ($hospital_id === 0 && $health_center_id === 0) {
    $_SESSION['error'] = "Invalid hospital or health center ID.";
    error_log("extinguishers.php: Missing hospital_id or health_center_id in URL");
    header("Location: index.php");
    exit();
}

// Debug session data
error_log("extinguishers.php: Session Data - User ID={$_SESSION['user_id']}, Role=$user_role, Hospital ID=$hospital_id, Health Center ID=$health_center_id");

// Fetch facility details
$facility = null;
$facility_type = '';
if ($hospital_id > 0) {
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
    error_log("extinguishers.php: Database error for {$facility_type}_id=" . ($hospital_id ?: $health_center_id) . ": " . $conn->error);
    header("Location: index.php");
    exit();
}
$facility = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$facility) {
    $_SESSION['error'] = ucfirst($facility_type) . " not found.";
    error_log("extinguishers.php: " . ucfirst($facility_type) . " not found for id=" . ($hospital_id ?: $health_center_id));
    header("Location: index.php");
    exit();
}

// Check if user is a manager for this facility
$is_manager = false;
if ($user_role === 'manager') {
    $facility_id = $hospital_id > 0 ? $hospital_id : $health_center_id; // Assign to a variable
    $stmt = $conn->prepare(
        $hospital_id > 0
            ? "SELECT id FROM users WHERE id = ? AND hospital_id = ?"
            : "SELECT id FROM users WHERE id = ? AND health_center_id = ?"
    );
    $stmt->bind_param("ii", $_SESSION['user_id'], $facility_id); // Use the variable
    if (!$stmt->execute()) {
        error_log("extinguishers.php: Error checking manager role for user_id={$_SESSION['user_id']}, {$facility_type}_id=$facility_id: " . $conn->error);
    } else {
        $result = $stmt->get_result();
        $is_manager = $result->num_rows > 0;
    }
    $stmt->close();

    if (!$is_manager) {
        error_log("extinguishers.php: User {$_SESSION['user_id']} not authorized for {$facility_type}_id=$facility_id");
    }
} else {
    error_log("extinguishers.php: User {$_SESSION['user_id']} is not a manager, role=$user_role");
}

// Fetch extinguishers
if ($hospital_id > 0) {
    $stmt = $conn->prepare("SELECT id, code, location, type, status, last_inspection, image_url, qr_code_url FROM fireextinguishers WHERE hospital_id = ? ORDER BY code");
    $stmt->bind_param("i", $hospital_id);
} else {
    $stmt = $conn->prepare("SELECT id, code, location, type, status, last_inspection, image_url, qr_code_url FROM fireextinguishers WHERE health_center_id = ? ORDER BY code");
    $stmt->bind_param("i", $health_center_id);
}
if (!$stmt->execute()) {
    $_SESSION['error'] = "Error fetching extinguishers: " . $conn->error;
    error_log("extinguishers.php: Error fetching extinguishers for {$facility_type}_id=" . ($hospital_id ?: $health_center_id) . ": " . $conn->error);
}
$extinguishers = $stmt->get_result();
$stmt->close();

// Fetch extinguisher status counts for chart
$extinguisher_status = ['Green' => 0, 'Red' => 0];
if ($hospital_id > 0) {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM fireextinguishers WHERE hospital_id = ? GROUP BY status");
    $stmt->bind_param("i", $hospital_id);
} else {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM fireextinguishers WHERE health_center_id = ? GROUP BY status");
    $stmt->bind_param("i", $health_center_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $extinguisher_status[$row['status']] = $row['count'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fire Extinguishers - <?php echo htmlspecialchars($facility['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Root Variables for Consistent Color Scheme */
        :root {
            --primary-color: #1E3A8A; /* Deep Blue */
            --secondary-color: #3B82F6; /* Bright Blue */
            --accent-color: #F59E0B; /* Warm Amber */
            --text-color: #1F2937; /* Dark Gray */
            --background-color: #F9FAFB; /* Light Gray */
            --white: #FFFFFF; /* White */
            --shadow-color: rgba(0, 0, 0, 0.1); /* Subtle shadow */
            --success-color: #10B981;
            --error-color: #EF4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 6px var(--shadow-color);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
        }

        .logo-circle {
            width: 50px;
            height: 50px;
            overflow: hidden;
            border-radius: 50%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .logo-circle img {
            width: 100%;
            height: auto;
        }

        .menu-toggle {
            display: none;
            font-size: 24px;
            color: var(--white);
            cursor: pointer;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 24px;
        }

        .nav-menu li a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .nav-menu li a:hover {
            color: var(--accent-color);
        }

        .nav-menu .btn {
            background: var(--accent-color);
            color: var(--primary-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .nav-menu .btn:hover {
            background: var(--white);
            color: var(--primary-color);
        }

        .nav-menu .signup {
            background: var(--white);
            color: var(--primary-color);
        }

        /* Extinguisher Content */
        .extinguisher-content {
            padding: 40px 0;
        }

        .extinguisher-content h2 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 24px;
            text-align: center;
            font-weight: 700;
        }

        .success, .error {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 1rem;
            text-align: center;
        }

        .success {
            background: var(--success-color);
            color: var(--white);
        }

        .error {
            background: var(--error-color);
            color: var(--white);
        }

        .chart-container {
            max-width: 500px;
            margin: 0 auto 40px;
            background: var(--white);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        .cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-bottom: 32px;
        }

        .cta-btn {
            background: var(--primary-color);
            color: var(--white);
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        .cta-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .extinguisher-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 20px;
            padding: 0 10px;
        }

        .extinguisher-list p {
            grid-column: 1 / -1;
            font-size: 1.2rem;
            text-align: center;
            color: #6b7280;
            margin: 20px 0;
            font-weight: 500;
        }

        .extinguisher-card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 12px 32px var(--shadow-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .extinguisher-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
        }

        .extinguisher-card h4 {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .extinguisher-card p {
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-color);
        }

        .extinguisher-card p strong {
            width: 130px;
            font-weight: 600;
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .indicator {
            padding: 6px 14px;
            border-radius: 20px;
            color: var(--white);
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .status-green {
            background: var(--success-color);
        }

        .status-red {
            background: var(--error-color);
        }

        .extinguisher-card img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            border: 2px solid var(--primary-color);
            margin-top: 12px;
        }

        .qr-code {
            max-width: 40px; /* Reduced from 60px to make QR code smaller */
            border: 2px solid var(--primary-color);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 20px 0;
            text-align: center;
            margin-top: 40px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .nav-menu {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 0;
                width: 100%;
                background: var(--primary-color);
                padding: 20px;
            }
            .nav{
                
            }

            .nav-menu.active {
                display: flex;
            }

            .nav-menu li {
                margin: 10px 0;
            }

            .extinguisher-list {
                grid-template-columns: 1fr;
            }

            .chart-container {
                max-width: 100%;
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php" class="btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn">Login</a></li>
                    <li><a href="signup.php" class="btn signup">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Extinguisher Content -->
    <section class="extinguisher-content">
        <div class="container">
            <h2>Fire Extinguishers - <?php echo htmlspecialchars($facility['name']); ?></h2>
            <!-- Feedback Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <!-- Extinguisher Status Chart -->
            <div class="chart-container">
                <canvas id="extinguisherStatusChart"></canvas>
            </div>

            <!-- Manager Options -->
            <?php if ($is_manager): ?>
                <div class="cta-buttons">
                    <a href="add_extinguisher.php?<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>" class="cta-btn">Add New Extinguisher</a>
                </div>
            <?php endif; ?>

            <!-- Extinguisher List -->
            <div class="extinguisher-list">
                <?php if ($extinguishers->num_rows === 0): ?>
                    <p>No fire extinguishers found for this <?php echo $facility_type; ?>.</p>
                <?php else: ?>
                    <?php while ($extinguisher = $extinguishers->fetch_assoc()): ?>
                        <div class="extinguisher-card">
                            <h4><?php echo htmlspecialchars($extinguisher['code']); ?></h4>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($extinguisher['location']); ?></p>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars($extinguisher['type']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="indicator status-<?php echo strtolower($extinguisher['status']); ?>">
                                    <?php echo htmlspecialchars($extinguisher['status']); ?>
                                </span>
                            </p>
                            <p><strong>Last Inspection:</strong> 
                                <?php echo $extinguisher['last_inspection'] ? date('M d, Y', strtotime($extinguisher['last_inspection'])) : 'N/A'; ?>
                            </p>
                            <?php if ($extinguisher['qr_code_url']): ?>
                                <img src="<?php echo htmlspecialchars($extinguisher['qr_code_url']); ?>" alt="QR Code" class="qr-code">
                            <?php endif; ?>
                            <?php if ($extinguisher['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($extinguisher['image_url']); ?>" alt="Extinguisher Image">
                            <?php endif; ?>
                            <div class="extinguisher-actions cta-buttons">
                                <a href="extinguisher_details.php?id=<?php echo $extinguisher['id']; ?>&<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>" class="cta-btn">View Details</a>
                                <?php if ($is_manager): ?>
                                    <a href="edit_extinguisher.php?id=<?php echo $extinguisher['id']; ?>&<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>" class="cta-btn">Edit</a>
                                    <a href="delete_extinguisher.php?id=<?php echo $extinguisher['id']; ?>&<?php echo $facility_type . '_id=' . ($hospital_id ?: $health_center_id); ?>" class="cta-btn" onclick="return confirm('Are you sure you want to delete this extinguisher?');">Delete</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
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
// Extinguisher Status Chart
const extCtx = document.getElementById('extinguisherStatusChart');
if (extCtx) {
    new Chart(extCtx.getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['Green (OK)', 'Red (Needs Maintenance)'],
            datasets: [{
                data: [<?php echo $extinguisher_status['Green']; ?>, <?php echo $extinguisher_status['Red']; ?>],
                backgroundColor: ['#2ecc71', '#e74c3c'],
                borderColor: ['#2ecc71', '#e74c3c'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 12 }, color: '#006d77' } },
                tooltip: { backgroundColor: '#006d77', callbacks: { label: context => `${context.label}: ${context.parsed} extinguishers` } }
            }
        }
    });
}
    </script>
</body>
</html>
<?php $conn->close(); ?>