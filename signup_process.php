<?php
session_start();
require_once './includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: signup.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: signup.php");
        exit();
    }

    // Check if username or email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username or email already exists.";
        header("Location: signup.php");
        exit();
    }
    $stmt->close();

    // Hash password and insert user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $username, $email, $hashed_password);
    if ($stmt->execute()) {
        // Get the newly created user's ID
        $user_id = $conn->insert_id;

        // Set session variables to log the user in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user'; // Default role
        $_SESSION['hospital_id'] = null; // Set if applicable, based on your schema

        // Redirect to index.php
        header("Location: index.php");
    } else {
        $_SESSION['error'] = "Error creating account. Please try again.";
        header("Location: signup.php");
    }
    $stmt->close();
}

$conn->close();
?>