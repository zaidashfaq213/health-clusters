<?php
require_once './includes/config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate topic ID and health center ID
if (!isset($_GET['id']) || !isset($_GET['health_center_id'])) {
    $_SESSION['error'] = "Topic ID or Health Center ID is missing.";
    header("Location: index.php");
    exit();
}

$topic_id = (int)$_GET['id'];
$health_center_id = (int)$_GET['health_center_id'];

// Fetch topic details
$stmt = $conn->prepare("SELECT title, content, indicator, media_type, media_path, table_data, created_at FROM topics WHERE id = ? AND health_center_id = ? AND status = 'visible'");
$stmt->bind_param("ii", $topic_id, $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

$topic = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$topic) {
    $_SESSION['error'] = "Topic not found or not visible.";
    header("Location: health_center.php?id=$health_center_id");
    exit();
}

// Fetch health center name for breadcrumb
$stmt = $conn->prepare("SELECT name FROM health_centers WHERE id = ?");
$stmt->bind_param("i", $health_center_id);
$stmt->execute();
$health_center = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$health_center) {
    $_SESSION['error'] = "Health Center not found.";
    header("Location: index.php");
    exit();
}

// Indicator mapping (aligned with health_center.php)
$indicator_text = [
    -1 => 'Critical',
    0 => 'Warning',
    1 => 'Info',
    2 => 'Success'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($topic['title']); ?> - <?php echo htmlspecialchars($health_center['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* General Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    color: #1F2937;
    background: #F9FAFB;
    overflow-x: hidden;
}

/* Container */
.container {
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
}

/* Navbar */
.navbar {
    position: fixed;
    top: 0;
    width: 100%;
    background: rgba(41, 59, 133, 0.9);
    padding: 15px 0;
    z-index: 1000;
    transition: all 0.3s ease;
}

.navbar.sticky {
    background: #293b85;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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
    border: 3px solid #F59E0B;
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

.nav-menu li a {
    color: #FFFFFF;
    text-decoration: none;
    font-weight: 500;
    font-size: 1.1rem;
    transition: color 0.3s;
}

.nav-menu li a:hover {
    color: #F59E0B;
}

.nav-menu .btn {
    padding: 10px 20px;
    background: #F59E0B;
    color: #293b85;
    border-radius: 25px;
    font-weight: 600;
}

.nav-menu .btn:hover {
    background: #FFFFFF;
    color: #293b85;
}

.menu-toggle {
    display: none;
    font-size: 1.5rem;
    color: #FFFFFF;
    cursor: pointer;
}

/* Header */
.hospital-header {
    position: relative;
    margin-top: 90px; /* Offset for fixed navbar */
    height: 300px;
    background: url('assets/images/home-bg.jpg') no-repeat center center/cover;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #FFFFFF;
}

.hospital-header .overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(41, 59, 133, 0.6);
}

.hospital-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 1;
}

.hospital-header p {
    font-size: 1.1rem;
    margin-top: 10px;
    position: relative;
    z-index: 1;
}

.hospital-header p a {
    color: #F59E0B;
    text-decoration: none;
}

.hospital-header p a:hover {
    color: #FFFFFF;
}

/* Content Section */
.hospital-content {
    padding: 40px 0;
    background: #FFFFFF;
}

.error {
    color: #EF4444;
    background: #FEE2E2;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
    text-align: center;
}

.topic-details {
    background: #F9FAFB;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.topic-details h2 {
    font-size: 2rem;
    color: #293b85;
    margin-bottom: 20px;
}

.meta-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 0.9rem;
    color: #6B7280;
}

.meta-info .indicator {
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 12px;
}

.indicator--1 {
    background: #EF4444;
    color: #FFFFFF;
}

.indicator-0 {
    background: #F59E0B;
    color: #1F2937;
}

.indicator-1 {
    background: #3B82F6;
    color: #FFFFFF;
}

.indicator-2 {
    background: #10B981;
    color: #FFFFFF;
}

.content {
    line-height: 1.6;
    margin-bottom: 20px;
}

.media-preview {
    margin: 20px 0;
    max-width: 100%;
}

.media-preview img,
.media-preview video {
    max-width: 40%;
    height: auto;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.media-preview .file-link,
.media-preview a {
    display: inline-block;
    padding: 10px 20px;
    background: #293b85;
    color: #FFFFFF;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    transition: background 0.3s;
}

.media-preview .file-link:hover,
.media-preview a:hover {
    background: #F59E0B;
    color: #293b85;
}

.topic-table {
    margin: 20px 0;
    overflow-x: auto;
}

.topic-table table {
    width: 100%;
    border-collapse: collapse;
    background: #FFFFFF;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.topic-table th,
.topic-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #D1D5DB;
}

.topic-table th {
    background: #293b85;
    color: #FFFFFF;
    font-weight: 600;
}

.topic-table tr:hover {
    background: #E5E7EB;
}

/* Footer */
footer {
    background: #293b85;
    color: #FFFFFF;
    text-align: center;
    padding: 20px 0;
}

footer p {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .navbar {
        padding: 10px 0;
    }

    .menu-toggle {
        display: block;
    }

    .nav-menu {
        position: fixed;
        top: 80px;
        right: -100%;
        width: 250px;
        height: 100vh;
        background: #293b85;
        flex-direction: column;
        padding: 20px;
        transition: right 0.3s;
    }

    .nav-menu.active {
        right: 0;
    }

    .nav-menu li {
        margin: 20px 0;
    }

    .hospital-header {
        height: 250px;
        margin-top: 80px;
    }

    .hospital-header h1 {
        font-size: 2rem;
    }

    .hospital-header p {
        font-size: 1rem;
    }

    .topic-details h2 {
        font-size: 1.8rem;
    }

    .meta-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .hospital-header {
        height: 200px;
        margin-top: 70px;
    }

    .hospital-header h1 {
        font-size: 1.5rem;
    }

    .topic-details {
        padding: 20px;
    }

    .topic-details h2 {
        font-size: 1.5rem;
    }

    .meta-info {
        font-size: 0.8rem;
    }

    .topic-table th,
    .topic-table td {
        padding: 8px 10px;
        font-size: 0.9rem;
    }
}
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
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

    <!-- Topic Header -->
    <header class="hospital-header">
        <div class="overlay">
            <div class="container">
                <h1><?php echo htmlspecialchars($topic['title']); ?></h1>
                <p><a href="health_center.php?id=<?php echo $health_center_id; ?>"><?php echo htmlspecialchars($health_center['name']); ?></a></p>
            </div>
        </div>
    </header>

    <!-- Topic Content -->
    <section class="hospital-content">
        <div class="container">
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <div class="topic-details">
                <h2><?php echo htmlspecialchars($topic['title']); ?></h2>
                <div class="meta-info">
                    <span>Created: <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?></span>
                    <span class="indicator indicator-<?php echo $topic['indicator']; ?>">
                        <?php echo htmlspecialchars($indicator_text[$topic['indicator']] ?? $topic['indicator']); ?>
                    </span>
                </div>

                <div class="content">
                    <p><?php echo nl2br(htmlspecialchars($topic['content'])); ?></p>
                </div>

                <?php if ($topic['media_type'] && $topic['media_path']): ?>
                    <div class="media-preview">
                        <?php
                        $media_path = str_replace('\\', '/', $topic['media_path']);
                        if ($topic['media_type'] === 'image' && file_exists($media_path)): ?>
                            <img src="<?php echo htmlspecialchars($media_path); ?>" alt="Topic Media">
                        <?php elseif ($topic['media_type'] === 'video' && file_exists($media_path)): ?>
                            <video controls>
                                <source src="<?php echo htmlspecialchars($media_path); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php elseif ($topic['media_type'] === 'file' && file_exists($media_path)): ?>
                            <a href="<?php echo htmlspecialchars($media_path); ?>" target="_blank" class="file-link">Download File</a>
                        <?php elseif ($topic['media_type'] === 'external'): ?>
                            <a href="<?php echo htmlspecialchars($topic['media_path']); ?>" target="_blank">View External Media</a>
                        <?php else: ?>
                            <p class="error">Media not available.</p>
                            <?php error_log("health_center_topic_details.php: Media not found - type={$topic['media_type']}, path=$media_path"); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($topic['table_data']): 
                    $table_data = json_decode($topic['table_data'], true);
                    if ($table_data && isset($table_data['headers']) && isset($table_data['rows'])): ?>
                        <div class="topic-table">
                            <table>
                                <thead>
                                    <tr>
                                        <?php foreach ($table_data['headers'] as $header): ?>
                                            <th><?php echo htmlspecialchars($header); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_data['rows'] as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                                <td><?php echo htmlspecialchars($cell); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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

    <!-- JavaScript for Navbar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');

            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });

            // Sticky navbar
            window.addEventListener('scroll', () => {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.classList.add('sticky');
                } else {
                    navbar.classList.remove('sticky');
                }
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>