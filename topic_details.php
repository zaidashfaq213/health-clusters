<?php
require_once './includes/config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: login.php");
    exit();
}

// Validate topic ID and context (hospital or health center)
if (!isset($_GET['id']) || (!isset($_GET['hospital_id']) && !isset($_GET['health_center_id']))) {
    $_SESSION['error'] = "Topic ID or facility ID is missing.";
    header("Location: index.php");
    exit();
}

$topic_id = (int)$_GET['id'];
$facility_type = isset($_GET['hospital_id']) ? 'hospital' : 'health_center';
$facility_id = $facility_type === 'hospital' ? (int)$_GET['hospital_id'] : (int)$_GET['health_center_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT username, role, profile_picture, hospital_id, health_center_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$profile_picture = $user['profile_picture'] ? htmlspecialchars($user['profile_picture']) : 'assets/images/default_profile.png';

// Check if user is a manager
$is_manager = $user['role'] === 'manager' && (
    ($facility_type === 'hospital' && $user['hospital_id'] == $facility_id) ||
    ($facility_type === 'health_center' && $user['health_center_id'] == $facility_id)
);

// Fetch topic details
$facility_column = $facility_type === 'hospital' ? 'hospital_id' : 'health_center_id';
$stmt = $conn->prepare("SELECT t.title, t.content, t.indicator, t.media_type, t.media_path, t.table_data, t.created_at, s.name as section_name 
                        FROM topics t 
                        LEFT JOIN sections s ON t.section_id = s.id 
                        WHERE t.id = ? AND t.$facility_column = ? AND t.status = 'visible'");
$stmt->bind_param("ii", $topic_id, $facility_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: $facility_type.php?id=$facility_id");
    exit();
}

$topic = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$topic) {
    $_SESSION['error'] = "Topic not found or not visible.";
    header("Location: $facility_type.php?id=$facility_id");
    exit();
}

// Fetch facility name for breadcrumb
$facility_table = $facility_type === 'hospital' ? 'hospitals' : 'health_centers';
$stmt = $conn->prepare("SELECT name FROM $facility_table WHERE id = ?");
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$facility) {
    $_SESSION['error'] = ucfirst($facility_type) . " not found.";
    header("Location: index.php");
    exit();
}

// Fetch replies (including nested replies)
$stmt = $conn->prepare("SELECT r.id, r.content, r.image_path, r.created_at, r.parent_reply_id, u.username, u.profile_picture 
                        FROM replies r 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.topic_id = ? 
                        ORDER BY r.created_at DESC");
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Organize replies hierarchically
function build_reply_tree($replies, $parent_id = null) {
    $tree = [];
    foreach ($replies as $reply) {
        if ($reply['parent_reply_id'] == $parent_id) {
            $reply['children'] = build_reply_tree($replies, $reply['id']);
            $tree[] = $reply;
        }
    }
    return $tree;
}
$reply_tree = build_reply_tree($replies);

// Handle reply submission (top-level or nested)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply'])) {
    $content = trim($_POST['reply_content'] ?? '');
    $parent_reply_id = isset($_POST['parent_reply_id']) ? (int)$_POST['parent_reply_id'] : null;
    $image_path = null;

    if (empty($content)) {
        $_SESSION['error'] = "Reply content is required.";
    } else {
        // Handle image upload
        if (!empty($_FILES['reply_image']['name']) && $_FILES['reply_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/replies/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_name = time() . '_' . uniqid() . '.' . pathinfo($_FILES['reply_image']['name'], PATHINFO_EXTENSION);
            $file_path = $upload_dir . $file_name;
            $file_type = mime_content_type($_FILES['reply_image']['tmp_name']);

            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = "Invalid image format. Only JPEG, PNG, or GIF allowed.";
            } elseif ($_FILES['reply_image']['size'] > $max_size) {
                $_SESSION['error'] = "Image size exceeds 5MB.";
            } elseif (!move_uploaded_file($_FILES['reply_image']['tmp_name'], $file_path)) {
                $_SESSION['error'] = "Failed to upload image.";
                error_log("topic_details.php: Failed to upload reply image for topic_id=$topic_id");
            } else {
                $image_path = $file_path;
            }
        }

        if (!isset($_SESSION['error'])) {
            $stmt = $conn->prepare("INSERT INTO replies (topic_id, user_id, content, image_path, parent_reply_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $topic_id, $_SESSION['user_id'], $content, $image_path, $parent_reply_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Reply added successfully.";
                header("Location: topic_details.php?id=$topic_id&$facility_type" . "_id=$facility_id");
                exit();
            } else {
                $_SESSION['error'] = "Error adding reply: " . $stmt->error;
                error_log("topic_details.php: Error adding reply for topic_id=$topic_id: " . $stmt->error);
            }
            $stmt->close();
        }
    }
    header("Location: topic_details.php?id=$topic_id&$facility_type" . "_id=$facility_id");
    exit();
}

// Handle reply deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reply']) && $is_manager) {
    $reply_id = (int)$_POST['reply_id'];

    // Fetch reply and its children to delete images
    $stmt = $conn->prepare("SELECT image_path FROM replies WHERE id = ? OR parent_reply_id = ?");
    $stmt->bind_param("ii", $reply_id, $reply_id);
    $stmt->execute();
    $replies_to_delete = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($replies_to_delete as $reply) {
        if ($reply['image_path'] && file_exists($reply['image_path'])) {
            unlink($reply['image_path']);
        }
    }

    // Delete reply and its children
    $stmt = $conn->prepare("DELETE FROM replies WHERE id = ? OR parent_reply_id = ?");
    $stmt->bind_param("ii", $reply_id, $reply_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Reply and its replies deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting reply: " . $stmt->error;
        error_log("topic_details.php: Error deleting reply for reply_id=$reply_id: " . $stmt->error);
    }
    $stmt->close();

    header("Location: topic_details.php?id=$topic_id&$facility_type" . "_id=$facility_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($topic['title']); ?> - <?php echo htmlspecialchars($facility['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            color: #1e293b;
            background: linear-gradient(160deg, #f9fbfe 0%, #e5e9f2 100%);
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.15), transparent);
            z-index: -1;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
            padding: 12px 0;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-circle img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #5b4eff;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            gap: 20px;
            margin: 0;
            padding: 0;
        }

        .nav-menu li a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: color 0.3s, transform 0.3s ease;
        }

        .nav-menu li a:hover {
            color: #e0e7ff;
            transform: translateY(-2px);
        }

        .profile-menu {
            position: relative;
        }

        .profile-card-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .profile-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #5b4eff;
        }

        .profile-info h4, .profile-info p {
            color: #ffffff;
            margin: 0;
            font-weight: 500;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .profile-dropdown.active {
            display: block;
        }

        .profile-dropdown li a {
            color: #1e293b;
            padding: 0.5rem 1rem;
            display: block;
            text-decoration: none;
            font-weight: 500;
        }

        .profile-dropdown li a:hover {
            background: #e0e7ff;
            color: #5b4eff;
        }

        .menu-toggle {
            display: none;
            color: #ffffff;
            font-size: 1.4rem;
            cursor: pointer;
        }

        /* Header */
        .hospital-header {
            background: linear-gradient(135deg, #1e293b, #5b4eff);
            padding: 2rem 0;
            color: #ffffff;
            text-align: center;
            margin-top: 74px;
        }

        .hospital-header .overlay {
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
        }

        .breadcrumb a {
            color: #e0e7ff;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Container */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Topic Details */
        .topic-details {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .topic-card {
            margin-bottom: 2rem;
        }

        .meta-info {
            display: flex;
            gap: 1rem;
            color: #64748b;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .indicator {
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .indicator-critical {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: #ffffff;
        }

        .indicator-info {
            background: linear-gradient(45deg, #1e293b, #5b4eff);
            color: #ffffff;
        }

        .indicator-success {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: #ffffff;
        }

        .content p {
            line-height: 1.6;
            margin: 1rem 0;
            color: #1e293b;
        }

        .media-preview {
            margin: 1rem 0;
            text-align: center;
        }

        .topic-media, .reply-image {
            max-width: 70%;
            height: auto;
            border-radius: 8px;
            display: block;
            margin: 0 auto;
            transition: transform 0.3s ease;
        }

        .topic-media:hover, .reply-image:hover {
            transform: scale(1.05);
        }

        .file-link {
            color: #5b4eff;
            text-decoration: none;
            font-weight: 500;
        }

        .file-link:hover {
            color: #818cf8;
            text-decoration: underline;
        }

        .topic-table table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: #f9fbfe;
            border-radius: 8px;
            overflow: hidden;
        }

        .topic-table th, .topic-table td {
            border: 1px solid #e5e9f2;
            padding: 0.8rem;
            text-align: left;
        }

        .topic-table th {
            background: linear-gradient(45deg, #1e293b, #5b4eff);
            color: #ffffff;
            font-weight: 600;
        }

        .topic-table td {
            color: #1e293b;
        }

        /* Reply Form */
        .reply-form, .nested-reply-form {
            margin: 2rem 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #e5e9f2;
            border-radius: 8px;
            resize: vertical;
            background: #f9fbfe;
            color: #1e293b;
        }

        .form-group input[type="file"] {
            padding: 0.5rem;
            color: #1e293b;
        }

        .action-btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s, transform 0.3s, box-shadow 0.3s;
            margin-right: 0.5rem;
        }

        .primary-btn {
            background: linear-gradient(45deg, #1e293b, #5b4eff);
            color: #ffffff;
        }

        .primary-btn:hover {
            background: linear-gradient(45deg, #5b4eff, #818cf8);
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .delete-btn {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: #ffffff;
        }

        .delete-btn:hover {
            background: linear-gradient(45deg, #f87171, #fca5a5);
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .reply-btn {
            background: linear-gradient(45deg, #6b7280, #9ca3af);
            color: #ffffff;
        }

        .reply-btn:hover {
            background: linear-gradient(45deg, #9ca3af, #d1d5db);
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* Replies List */
        .replies-list {
            margin-top: 2rem;
        }

        .reply-card {
            background: #f9fbfe;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .reply-card.nested {
            margin-left: 2rem;
            border-left: 3px solid #5b4eff;
        }

        .reply-card:hover {
            transform: translateY(-2px);
        }

        .reply-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .reply-avatar img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #5b4eff;
        }

        .reply-user-info {
            display: flex;
            flex-direction: column;
        }

        .username {
            font-weight: 600;
            color: #1e293b;
        }

        .posted {
            color: #64748b;
            font-size: 0.9rem;
        }

        .reply-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        /* Messages */
        .error, .success {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .error {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: #ffffff;
        }

        .success {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: #ffffff;
        }

        /* Footer */
        footer {
            background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
            color: #ffffff;
            text-align: center;
            padding: 20px 0;
            margin-top: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
                flex-direction: column;
                position: fixed;
                top: 74px;
                right: -100%;
                width: 250px;
                height: 100vh;
                background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
                padding: 20px;
                transition: right 0.3s;
            }

            .nav-menu.active {
                right: 0;
            }

            .nav-menu li {
                margin: 15px 0;
            }

            .menu-toggle {
                display: block;
            }

            .topic-media, .reply-image {
                max-width: 90%;
            }

            .hospital-header {
                padding: 1.5rem 0;
            }

            .hospital-header .overlay {
                padding: 1.5rem;
            }

            .reply-card.nested {
                margin-left: 1rem;
            }
        }

        @media (max-width: 480px) {
            .topic-details {
                padding: 1rem;
            }

            .topic-media, .reply-image {
                max-width: 100%;
            }

            .action-btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }

            .hospital-header h1 {
                font-size: 1.8rem;
            }

            .meta-info {
                flex-direction: column;
                gap: 0.5rem;
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
                <li class="profile-menu">
                    <div class="profile-card-nav">
                        <div class="profile-picture-container">
                            <img src="<?php echo $profile_picture; ?>" alt="Profile Picture" class="profile-picture">
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

    <!-- Topic Header -->
    <header class="hospital-header">
        <div class="overlay">
            <div class="container">
                <h1><?php echo htmlspecialchars($topic['title']); ?></h1>
                <p class="breadcrumb">
                    <a href="<?php echo $facility_type; ?>.php?id=<?php echo $facility_id; ?>">
                        <?php echo htmlspecialchars($facility['name']); ?>
                    </a>
                    <?php if ($topic['section_name']): ?>
                        > <?php echo htmlspecialchars($topic['section_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </header>

    <!-- Topic Content -->
    <section class="hospital-content">
        <div class="container">
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>

            <div class="topic-details">
                <div class="topic-card">
                    <h2><?php echo htmlspecialchars($topic['title']); ?></h2>
                    <div class="meta-info">
                        <span>Created: <?php echo date('M d, Y H:i', strtotime($topic['created_at'])); ?></span>
                        <span class="indicator indicator-<?php echo $topic['indicator'] == -1 ? 'critical' : ($topic['indicator'] == 0 ? 'info' : 'success'); ?>">
                            <?php echo $topic['indicator'] == -1 ? 'Critical' : ($topic['indicator'] == 0 ? 'Normal' : 'Positive'); ?>
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
                                <img src="<?php echo htmlspecialchars($media_path); ?>" alt="Topic Media" class="topic-media">
                            <?php elseif ($topic['media_type'] === 'video' && file_exists($media_path)): ?>
                                <video controls class="topic-media">
                                    <source src="<?php echo htmlspecialchars($media_path); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php elseif ($topic['media_type'] === 'file' && file_exists($media_path)): ?>
                                <a href="<?php echo htmlspecialchars($media_path); ?>" target="_blank" class="file-link">Download File</a>
                            <?php elseif ($topic['media_type'] === 'external'): ?>
                                <a href="<?php echo htmlspecialchars($topic['media_path']); ?>" target="_blank" class="file-link">View External Media</a>
                            <?php else: ?>
                                <p class="error">Media not available.</p>
                                <?php error_log("topic_details.php: Media not found - type={$topic['media_type']}, path=$media_path"); ?>
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

                <!-- Reply Form -->
                <div class="reply-form">
                    <h3>Add Reply</h3>
                    <form action="topic_details.php?id=<?php echo $topic_id; ?>&<?php echo $facility_type; ?>_id=<?php echo $facility_id; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_reply" value="1">
                        <div class="form-group">
                            <label for="reply_content">Your Reply</label>
                            <textarea id="reply_content" name="reply_content" placeholder="Enter your reply" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="reply_image">Attach Image (JPEG, PNG, GIF, max 5MB)</label>
                            <input type="file" id="reply_image" name="reply_image" accept="image/*">
                        </div>
                        <button type="submit" class="action-btn primary-btn">Submit Reply</button>
                    </form>
                </div>

                <!-- Replies List -->
                <?php if (!empty($reply_tree)): ?>
                    <div class="replies-list">
                        <h3>Replies</h3>
                        <?php 
                        function render_replies($replies, $is_manager, $topic_id, $facility_type, $facility_id, $level = 0) {
                            foreach ($replies as $reply): ?>
                                <div class="reply-card <?php echo $level > 0 ? 'nested' : ''; ?>">
                                    <div class="reply-user">
                                        <div class="reply-avatar">
                                            <img src="<?php echo $reply['profile_picture'] ? htmlspecialchars($reply['profile_picture']) : 'assets/images/default_profile.png'; ?>" alt="User Avatar">
                                        </div>
                                        <div class="reply-user-info">
                                            <span class="username"><?php echo htmlspecialchars($reply['username']); ?></span>
                                            <span class="posted">Posted: <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                                    <?php if ($reply['image_path'] && file_exists($reply['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($reply['image_path']); ?>" alt="Reply Image" class="reply-image">
                                    <?php endif; ?>

                                    <!-- Manager Actions -->
                                    <?php if ($is_manager): ?>
                                        <div class="reply-actions">
                                            <form action="topic_details.php?id=<?php echo $topic_id; ?>&<?php echo $facility_type; ?>_id=<?php echo $facility_id; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this reply and its replies?');">
                                                <input type="hidden" name="delete_reply" value="1">
                                                <input type="hidden" name="reply_id" value="<?php echo $reply['id']; ?>">
                                                <button type="submit" class="action-btn delete-btn">Delete</button>
                                            </form>
                                            <button class="action-btn reply-btn" onclick="toggleNestedReplyForm(<?php echo $reply['id']; ?>)">Reply</button>
                                        </div>

                                        <!-- Nested Reply Form -->
                                        <div class="nested-reply-form" id="nested-reply-<?php echo $reply['id']; ?>" style="display:none;">
                                            <h4>Reply to <?php echo htmlspecialchars($reply['username']); ?></h4>
                                            <form action="topic_details.php?id=<?php echo $topic_id; ?>&<?php echo $facility_type; ?>_id=<?php echo $facility_id; ?>" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="add_reply" value="1">
                                                <input type="hidden" name="parent_reply_id" value="<?php echo $reply['id']; ?>">
                                                <div class="form-group">
                                                    <label for="reply_content_<?php echo $reply['id']; ?>">Your Reply</label>
                                                    <textarea id="reply_content_<?php echo $reply['id']; ?>" name="reply_content" placeholder="Enter your reply" required></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reply_image_<?php echo $reply['id']; ?>">Attach Image (JPEG, PNG, GIF, max 5MB)</label>
                                                    <input type="file" id="reply_image_<?php echo $reply['id']; ?>" name="reply_image" accept="image/*">
                                                </div>
                                                <button type="submit" class="action-btn primary-btn">Submit Reply</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Render Child Replies -->
                                    <?php if (!empty($reply['children'])): ?>
                                        <?php render_replies($reply['children'], $is_manager, $topic_id, $facility_type, $facility_id, $level + 1); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach;
                        }
                        render_replies($reply_tree, $is_manager, $topic_id, $facility_type, $facility_id);
                        ?>
                    </div>
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
            // Navigation toggle
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
                    if (dropdown) {
                        dropdown.classList.toggle('active');
                    }
                });
            }
        });

        function toggleNestedReplyForm(replyId) {
            const form = document.getElementById(`nested-reply-${replyId}`);
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>