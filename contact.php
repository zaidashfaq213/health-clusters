<?php
session_start();
require './includes/config.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; 

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $query = trim($_POST['query'] ?? '');

    // Server-side validation
    if (empty($name) || empty($email) || empty($query)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'iillxy010@gmail.com';
            $mail->Password = 'ipoyzcapxfdqyedc'; // Ensure this is the correct App Password (no spaces)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom($email, $name);
            $mail->addAddress('Yasalrehaili@gmail.com', 'Northern Borders Health Cluster');
            $mail->addReplyTo($email, $name);

            // Content
            $mail->isHTML(true); // Enable HTML email
            $mail->Subject = 'New Contact Form Submission';
            $mail->Body = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>New Contact Form Submission</title>
                    <style>
                        body {
                            font-family: Arial, Helvetica, sans-serif;
                            background-color: #F9FAFB;
                            margin: 0;
                            padding: 0;
                            color: #1F2937;
                        }
                        .container {
                            max-width: 600px;
                            margin: 20px auto;
                            background-color: #FFFFFF;
                            border-radius: 8px;
                            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                            overflow: hidden;
                        }
                        .header {
                            background: linear-gradient(135deg, #293b85, #3B82F6);
                            padding: 20px;
                            text-align: center;
                            color: #FFFFFF;
                        }
                        .header img {
                            max-width: 80px;
                            height: auto;
                            border-radius: 50%;
                            border: 2px solid #F59E0B;
                        }
                        .header h1 {
                            font-size: 24px;
                            margin: 10px 0 0;
                        }
                        .content {
                            padding: 30px;
                        }
                        .content p {
                            font-size: 16px;
                            line-height: 1.6;
                            margin: 10px 0;
                            color: #4B5563;
                        }
                        .content strong {
                            color: #293b85;
                        }
                        .content .query {
                            background-color: #F9FAFB;
                            padding: 15px;
                            border-radius: 5px;
                            border: 1px solid #E5E7EB;
                            white-space: pre-wrap;
                            word-break: break-word;
                        }
                        .footer {
                            background-color: #293b85;
                            color: #FFFFFF;
                            text-align: center;
                            padding: 15px;
                            font-size: 14px;
                        }
                        .footer a {
                            color: #F59E0B;
                            text-decoration: none;
                        }
                        .footer a:hover {
                            text-decoration: underline;
                        }
                        @media only screen and (max-width: 600px) {
                            .container {
                                margin: 10px;
                            }
                            .header img {
                                max-width: 60px;
                            }
                            .header h1 {
                                font-size: 20px;
                            }
                            .content {
                                padding: 20px;
                            }
                            .content p {
                                font-size: 14px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <img src="http://localhost/Hospital-managment/assets/images/logo.png" alt="Northern Borders Health Cluster Logo">
                            <h1>New Contact Form Submission</h1>
                        </div>
                        <div class="content">
                            <p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>
                            <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                            <p><strong>Query:</strong></p>
                            <div class="query">' . htmlspecialchars($query) . '</div>
                        </div>
                        <div class="footer">
                            <p>Northern Borders Health Cluster &copy; 2025<br>
                            Contact us at <a href="mailto:Yasalrehaili@gmail.com">Yasalrehaili@gmail.com</a></p>
                        </div>
                    </div>
                </body>
                </html>
            ';
            $mail->AltBody = "Name: $name\nEmail: $email\nQuery:\n$query"; // Plain text fallback

            // Send email
            $mail->send();
            $success = true;
        } catch (Exception $e) {
            $error = 'Failed to send your message. Error: ' . $mail->ErrorInfo;
            error_log(date('Y-m-d H:i:s') . ' - PHPMailer Error: ' . $mail->ErrorInfo . PHP_EOL, 3, 'errors.log');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F9FAFB;
            color: #1F2937;
        }
        .navbar {
            background: linear-gradient(135deg, #293b85, #3B82F6);
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .logo-circle img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid #F59E0B;
        }
        .nav-menu {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        .nav-menu li {
            margin-left: 20px;
        }
        .nav-menu a {
            color: #FFFFFF;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s;
        }
        .nav-menu a:hover {
            color: #F59E0B;
        }
        .btn {
            background-color: #F59E0B;
            color: #293b85;
            padding: 8px 15px;
            border-radius: 5px;
        }
        .btn:hover {
            background-color: #FBBF24;
        }
        .signup {
            background-color: #FFFFFF;
            color: #293b85;
        }
        .signup:hover {
            background-color: #F59E0B;
            color: #293b85;
        }
        .menu-toggle {
            display: none;
            color: #FFFFFF;
            font-size: 24px;
            cursor: pointer;
        }
        .contact-section {
            padding: 60px 20px;
            max-width: 800px;
            margin: 55px auto;
            text-align: center;
            background: #FFFFFF;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .contact-section h1 {
            font-size: 2.5rem;
            color: #293b85;
            margin-bottom: 20px;
        }
        .contact-section p {
            font-size: 1.1rem;
            color: #4B5563;
            margin-bottom: 40px;
        }
        .contact-form {
            background-color: #F9FAFB;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: left;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 1rem;
            color: #293b85;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #293b85;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #F59E0B;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
            outline: none;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        .submit-btn {
            background-color: #293b85;
            color: #FFFFFF;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s, transform 0.3s;
        }
        .submit-btn:hover {
            background-color: #F59E0B;
            color: #293b85;
            transform: translateY(-3px);
        }
        .success-message,
        .error-message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            font-size: 1rem;
        }
        .success-message {
            background-color: #10B981;
            color: #FFFFFF;
        }
        .error-message {
            background-color: #EF4444;
            color: #FFFFFF;
        }
        .whatsapp-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #25D366;
            color: #FFFFFF;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            font-size: 30px;
            transition: transform 0.3s;
            z-index: 1000;
        }
        .whatsapp-btn:hover {
            transform: scale(1.1);
        }
        footer {
            background: linear-gradient(135deg, #293b85, #3B82F6);
            color: #FFFFFF;
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
        }
        footer p {
            margin: 0;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 0;
                width: 100%;
                background: #293b85;
                padding: 20px 0;
            }
            .nav-menu.active {
                display: flex;
            }
            .nav-menu li {
                margin: 10px 0;
                text-align: center;
            }
            .menu-toggle {
                display: block;
            }
            .contact-section h1 {
                font-size: 2rem;
            }
            .contact-form {
                padding: 20px;
            }
            .whatsapp-btn {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
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
                <li><a href="contact.php">Contact Us</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php" class="btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn">Login</a></li>
                    <li><a href="signup.php" class="btn signup">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <section class="contact-section">
        <h1>Contact Us</h1>
        <p>We are here to assist you. Please fill out the form below or reach out via WhatsApp.</p>
        <div class="contact-form">
            <?php if ($success): ?>
                <div class="success-message">Your message has been sent successfully!</div>
            <?php elseif ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="contact.php" method="POST">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="query">Your Query</label>
                    <textarea id="query" name="query" required><?php echo isset($_POST['query']) ? htmlspecialchars($_POST['query']) : ''; ?></textarea>
                </div>
                <button type="submit" class="submit-btn">Send</button>
            </form>
        </div>
    </section>
    <a href="https://wa.me/+966566924637?text=Hello%20Northern%20Borders%20Health%20Cluster" class="whatsapp-btn" target="_blank">
        <i class="fab fa-whatsapp"></i>
    </a>
    <footer>
        <div class="container">
            <p>© 2025 Northern Borders Health Cluster. All Rights Reserved.</p>
        </div>
    </footer>
    <script>
        document.querySelector('.menu-toggle').addEventListener('click', () => {
            document.querySelector('.nav-menu').classList.toggle('active');
        });
    </script>
</body>
</html>