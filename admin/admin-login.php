<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_panel.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="../assets/css/signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Admin Login Section -->
    <section class="auth-section">
        <div class="overlay">
            <div class="container">
                <div class="auth-box">
                    <div class="logo-circle">
                        <img src="../assets/images/logo.png" alt="Northern Borders Health Cluster Logo">
                    </div>
                    <h2>Admin Login</h2>
                    <?php if (isset($_SESSION['error'])): ?>
                        <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
                    <?php endif; ?>
                    <form action="admin_login_process.php" method="POST">
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="Admin Email" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <button type="submit" class="auth-btn">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/js/admin.js"></script>
</body>
</html>