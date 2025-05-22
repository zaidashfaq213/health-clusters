<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Hardcoded admin credentials
    $admin_email = 'zaidabbasi933@gmail.com';
    $admin_password = 'zaidali213';

    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['admin_id'] = 1; // Static ID for admin
        $_SESSION['admin_email'] = $admin_email;
        header("Location: admin_panel.php");
        exit();
    } else {
        $_SESSION['error'] = "Invalid admin credentials.";
        header("Location: admin-login.php");
        exit();
    }
}
?>