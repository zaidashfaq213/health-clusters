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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
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
                <p class="section-intro">Learn critical safety skills with our expertly designed resources.</p>

                <!-- Course Announcements -->
                <div class="courses" data-aos="fade-up" data-aos-delay="200">
                    <h2>Upcoming Courses</h2>
                    <div class="course-list">
                        <div class="course-item" data-aos="fade-up" data-aos-delay="300">
                            <h3>Fire Safety Training</h3>
                            <p>Master fire extinguisher use and safety protocols.</p>
                            <p><strong>Date:</strong> June 10, 2025</p>
                            <p><strong>Location:</strong> North Medical Tower Hospital</p>
                            <a href="signup.php" class="btn">Register Now</a>
                        </div>
                        <div class="course-item" data-aos="fade-up" data-aos-delay="400">
                            <h3>Emergency Evacuation Training</h3>
                            <p>Learn safe evacuation techniques with expert guidance.</p>
                            <p><strong>Date:</strong> June 15, 2025</p>
                            <p><strong>Location:</strong> Rafha General Hospital</p>
                            <a href="signup.php" class="btn">Register Now</a>
                        </div>
                    </div>
                </div>

                <!-- Promotional Media -->
                <div class="promo-media" data-aos="fade-up" data-aos-delay="400">
                    <h2>Educational Resources</h2>
                    <div class="media-grid">
                        <!-- Fire Extinguisher PASS Guide -->
                        <div class="media-item" data-aos="fade-up" data-aos-delay="100">
                            <div class="media-content">
                                <div class="media-image">
                                    <img src="/assets/images/trainign.jpg" alt="Fire Extinguisher">
                                </div>
                                <h3>PASS Technique</h3>
                                <div class="media-steps">
                                    <p data-aos="fade-up" data-aos-delay="200"><i class="fas fa-hand-paper"></i> <strong>Pull / سحب</strong>: Unlock the pin. <span class="arabic">اسحب الدبوس لفك القفل.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="300"><i class="fas fa-crosshairs"></i> <strong>Aim / توجيه</strong>: Target the fire’s base. <span class="arabic">وجّه إلى قاعدة النار.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="400"><i class="fas fa-hand-rock"></i> <strong>Squeeze / اضغط</strong>: Release the agent. <span class="arabic">اضغط لإطلاق المادة.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="500"><i class="fas fa-arrows-alt-h"></i> <strong>Sweep / امسح</strong>: Move side to side. <span class="arabic">اكتسح من جانب لآخر.</span></p>
                                </div>
                                <a href="signup.php" class="btn">Learn More</a>
                            </div>
                        </div>
                        <!-- Fire Extinguisher Training -->
                        <div class="media-item" data-aos="fade-up" data-aos-delay="200">
                            <div class="media-content">
                                <div class="media-image">
                                    <img src="/assets/images/training.jpg" alt="Fire Extinguisher Training">
                                </div>
                                <h3>Fire Safety Training</h3>
                                <div class="media-steps">
                                    <p data-aos="fade-up" data-aos-delay="200"><i class="fas fa-fire-extinguisher"></i> <strong>Learn / تعلم</strong>: Operate extinguishers safely. <span class="arabic">تشغيل طفايات الحريق بأمان.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="300"><i class="fas fa-shield-alt"></i> <strong>Protect / حماية</strong>: Follow safety protocols. <span class="arabic">اتباع بروتوكولات السلامة.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="400"><i class="fas fa-chalkboard-teacher"></i> <strong>Practice / ممارسة</strong>: Train in real scenarios. <span class="arabic">التدرب في سيناريوهات حقيقية.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="500"><i class="fas fa-certificate"></i> <strong>Certify / شهادة</strong>: Earn a certificate. <span class="arabic">الحصول على شهادة.</span></p>
                                </div>
                                <a href="/assets/images/YASIR SALEH ALREHAILI.pdf" class="btn certificate-btn" download>
                                    <i class="fas fa-file-pdf"></i> Download Certificate
                                </a>
                            </div>
                        </div>
                        <!-- Fire Extinguisher Guide -->
                        <div class="media-item highlight-card" data-aos="fade-up" data-aos-delay="300">
                            <div class="media-content">
                                <div class="media-image video-preview">
                                    <img src="/assets/images/ex.jpg" alt="Fire Extinguisher Guide">
                                    <i class="fas fa-play play-icon"></i>
                                </div>
                                <h3>Fire Extinguisher Guide</h3>
                                <div class="media-steps">
                                    <p data-aos="fade-up" data-aos-delay="200"><i class="fas fa-video"></i> <strong>Watch / مشاهدة</strong>: View animated guide. <span class="arabic">مشاهدة الدليل المتحرك.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="300"><i class="fas fa-fire"></i> <strong>Learn / تعلم</strong>: Master PASS technique. <span class="arabic">إتقان تقنية PASS.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="400"><i class="fas fa-check-circle"></i> <strong>Apply / تطبيق</strong>: Use in emergencies. <span class="arabic">الاستخدام في حالات الطوارئ.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="500"><i class="fas fa-play-circle"></i> <strong>Share / مشاركة</strong>: Spread safety knowledge. <span class="arabic">نشر المعرفة بالسلامة.</span></p>
                                </div>
                                <a href="/assets/videos/fire-extinguisher.mp4" class="btn video-btn">Watch Video</a>
                            </div>
                        </div>
                        <!-- Evacuation Plan -->
                        <div class="media-item" data-aos="fade-up" data-aos-delay="400">
                            <div class="media-content">
                                <div class="media-image">
                                    <img src="/assets/images/evacuation.jpg" alt="Emergency Evacuation Plan">
                                </div>
                                <h3>RACE Evacuation Plan</h3>
                                <div class="media-steps">
                                    <p data-aos="fade-up" data-aos-delay="200"><i class="fas fa-running"></i> <strong>Rescue / إنقاذ</strong>: Save those in danger. <span class="arabic">إنقاذ الأشخاص في خطر.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="300"><i class="fas fa-bell"></i> <strong>Alert / تنبيه</strong>: Sound the alarm. <span class="arabic">إطلاق الإنذار.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="400"><i class="fas fa-door-closed"></i> <strong>Contain / احتواء</strong>: Limit the hazard. <span class="arabic">الحد من المخاطر.</span></p>
                                    <p data-aos="fade-up" data-aos-delay="500"><i class="fas fa-sign-out-alt"></i> <strong>Evacuate / إخلاء</strong>: Exit safely. <span class="arabic">الخروج بأمان.</span></p>
                                </div>
                                <a href="/assets/files/evacuation-plan.pdf" class="btn" download>Download Plan</a>
                            </div>
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

    <!-- JavaScript -->
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            offset: 100,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Toggle mobile menu
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        });
    </script>
</body>
</html>