<?php
session_start();
require_once './includes/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Education - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Cairo:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/edu.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" data-aos="fade-down" data-aos-duration="800">
        <div class="nav-container container">
            <div class="logo-circle">
                <img src="assets/images/logo.png" alt="Northern Borders Health Cluster Logo">
            </div>
            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#performance-overview">Performance-Graph</a></li>
                <li><a href="education.php" class="active">Education</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php" class="btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn">Login</a></li>
                    <li><a href="signup.php" class="btn signup">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Education Section -->
        <section class="education-section" data-aos="fade-up" data-aos-duration="1000">
            <div class="container">
                <h1>Education & Training</h1>
                <p class="section-intro">Explore our educational resources to learn life-saving skills and safety procedures.</p>

                <!-- Course Announcements -->
                <div class="courses" data-aos="fade-up" data-aos-delay="200">
                    <h2>Course Announcements</h2>
                    <div class="course-list">
                        <div class="course-item">
                            <h3>Fire Safety Training</h3>
                            <p>Join our upcoming workshop on using fire extinguishers and fire safety protocols.</p>
                            <p><strong>Date:</strong> June 10, 2025</p>
                            <p><strong>Location:</strong> North Medical Tower Hospital</p>
                            <a href="signup.php" class="btn">Register Now</a>
                        </div>
                        <div class="course-item">
                            <h3>Emergency Evacuation Training</h3>
                            <p>Learn how to safely evacuate during emergencies with our expert-led course.</p>
                            <p><strong>Date:</strong> June 15, 2025</p>
                            <p><strong>Location:</strong> Rafha General Hospital</p>
                            <a href="signup.php" class="btn">Register Now</a>
                        </div>
                    </div>
                </div>

                <!-- Promotional Media -->
                <div class="promo-media" data-aos="fade-up" data-aos-delay="400">
                    <h2>Educational Media</h2>
                    <div class="media-grid">
                        <!-- Fire Extinguisher PASS Guide -->
                        <div class="media-item pass-guide" id="pass-guide">
                            <h3>How to Use a Fire Extinguisher (PASS Technique)</h3>
                            <div class="pass-container">
                                <div class="pass-image" data-aos="zoom-in" data-aos-delay="100">
                                    <img src="/assets/images/ex.jpg" alt="Fire Extinguisher">
                                    
                                </div> 
                                <div class="pass-steps">
                                    <div class="pass-step" data-aos="pop-in" data-aos-delay="200">
                                        <i class="fas fa-hand-paper pass-icon"></i>
                                        <h4>Pull / سحب</h4>
                                        <p>Pull pin to unlock.</p>
                                        <p class="arabic">اسحب الدبوس لفك القفل.</p>
                                    </div> 
                                    <div class="pass-step" data-aos="pop-in" data-aos-delay="300">
                                        <i class="fas fa-crosshairs pass-icon"></i>
                                        <h4>Aim / توجيه</h4>
                                        <p>Aim at fire’s base.</p>
                                        <p class="arabic">وجّه إلى قاعدة النار.</p>
                                    </div>
                                    <div class="pass-step" data-aos="pop-in" data-aos-delay="400">
                                        <i class="fas fa-hand-rock pass-icon"></i>
                                        <h4>Squeeze / اضغط</h4>
                                        <p>Squeeze to release.</p>
                                        <p class="arabic">اضغط لإطلاق المادة.</p>
                                    </div>
                                    <div class="pass-step" data-aos="pop-in" data-aos-delay="500">
                                        <i class="fas fa-arrows-alt-h pass-icon"></i>
                                        <h4>Sweep / امسح</h4>
                                        <p>Sweep side to side.</p>
                                        <p class="arabic">اكتسح من جانب لآخر.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Fire Extinguisher Training -->
                        <div class="media-item training-guide" id="training-guide">
                            <h3>Fire Extinguisher Training Conducted</h3>
                            <div class="training-container">
                                <div class="training-image" data-aos="zoom-in" data-aos-delay="100">
                                    <img src="/assets/images/trainign.jpg" alt="Fire Extinguisher Training">
                                </div>
                                <p>We conducted fire extinguisher training for employees.</p>
                                <p class="arabic">أجرينا تدريبًا على استخدام طفايات الحريق للموظفين.</p>
                                <div class="certificate-link" data-aos="fade-up" data-aos-delay="200">
                                    <a href="/assets/images/YASIR SALEH ALREHAILI.pdf" class="btn certificate-btn" download>
                                        <i class="fas fa-file-pdf"></i> Download Certificate
                                    </a>
                                    <p class="arabic">تحميل الشهادة</p>
                                </div>
                            </div>
                        </div>
                        <!-- Fire Extinguisher Animation -->
                        <div class="media-item" id="fire-extinguisher">
                            <h3>How to Use a Fire Extinguisher</h3>
                            <video controls poster="/assets/images/fire-extinguisher-poster.jpg">
                                <source src="/assets/videos/fire-extinguisher.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            <p>Learn the PASS technique with this animated guide.
                                Learn how to use an Extenguisher and be safe.
                            </p>
                        </div>
                        <!-- Evacuation Plan -->
                        <div class="media-item" id="evacuation-plan">
                            <h3>Evacuation plan RACE</h3>
                            <img src="/assets/images/evacuation.jpg" alt="Emergency Evacuation Plan">
                            <p>Understand key steps for safe evacuation during emergencies.</p>
                            <a href="/assets/files/evacuation-plan.pdf" class="btn" download>Download Plan</a>
                        </div>
                      
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer data-aos="fade-up" data-aos-duration="800">
        <div class="container">
            <p>© 2025 Northern Borders Health Cluster. All Rights Reserved.</p>
        </div>
    </footer>
         

    <!-- JavaScript code-->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Toggle mobile menu
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        });
    </script>
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            offset: 100,
            duration: 800,
            easing: 'ease-in-out'
        });
    </script>
</body>
</html>