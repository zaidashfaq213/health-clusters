<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="/assets/css/signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                <li><a href="login.php" class="btn">Login</a></li>
                <li><a href="signup.php" class="btn signup">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="auth-section">
        <div class="overlay">
            <div class="container">
                <div class="auth-box">
                    <h2>Login</h2>
                 
                    <form action="login_process.php" method="POST">
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <button type="submit" class="auth-btn">Login</button>
                        <p class="auth-link">Don't have an account? <a href="signup.php">Sign Up</a></p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>© 2025 Northern Borders Health Cluster. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>