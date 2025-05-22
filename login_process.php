<?php
session_start();
require_once './includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $errors = [];

    // Validate inputs
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // Debug input
    error_log("login_process.php: Attempting login for username='$username'");

    if (empty($errors)) {
        // Query user
        $stmt = $conn->prepare("SELECT id, username, password, role, hospital_id, health_center_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            $errors[] = "Database error: " . $conn->error;
            error_log("login_process.php: Database error for username='$username': " . $conn->error);
        } else {
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // Debug query result
            error_log("login_process.php: Query result for username='$username': " . json_encode($user));

            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['hospital_id'] = isset($user['hospital_id']) ? (int)$user['hospital_id'] : null;
                $_SESSION['health_center_id'] = isset($user['health_center_id']) ? (int)$user['health_center_id'] : null;

                // Debug session
                error_log("login_process.php: Session set - User ID={$user['id']}, Username={$user['username']}, Role={$user['role']}, Hospital ID=" . ($_SESSION['hospital_id'] ?? 'NULL') . ", Health Center ID=" . ($_SESSION['health_center_id'] ?? 'NULL'));

                // Redirect
                if ($user['role'] === 'admin') {
                    header("Location: admin_panel.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $errors[] = "Invalid username or password.";
                error_log("login_process.php: Invalid login for username='$username'");
            }
        }
    }

    // Store errors
    $_SESSION['errors'] = $errors;
    header("Location: login.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request method.";
    error_log("login_process.php: Non-POST request detected");
    header("Location: login.php");
    exit();
}

$conn->close();
?>