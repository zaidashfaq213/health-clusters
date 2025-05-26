<?php
session_start();
require_once './includes/config.php';

// Fetch topic counts for all hospitals to calculate performance
$hospital_topics = [];
$hospital_names = [
    1 => 'North Medical Tower Hospital',
    2 => 'Maternity & Children Hospital in Arar',
    4 => 'Jadeedat Arar Hospital',
    5 => 'Prince Abdulaziz Bin Mosaad Bin Jallowy Hospital',
    6 => 'Eradah Complex And Mental Health in Arar',
    7 => 'Turaif General Hospital',
    8 => 'Rafha General Hospital',
    9 => 'Maternity & Children Hospital in Rafha',
    10 => 'Al-Uwayqilah General Hospital',
    11 => 'Shoabat Nusab General Hospital',
    12 => 'Convalescent and Medical Rehabilitation Hospital',
    13 => 'Prince Abdullah bin Abdulaziz Center for Cardiac Medicine and Surgery'
];
$max_topics = 0;
$stmt = $conn->prepare("SELECT hospital_id, COUNT(*) as topic_count FROM topics GROUP BY hospital_id");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $hospital_topics[$row['hospital_id']] = $row['topic_count'];
        if ($row['topic_count'] > $max_topics) {
            $max_topics = $row['topic_count'];
        }
    }
    $stmt->close();
}

// Prepare data for Hospital Performance Chart
$chart_labels = array_values($hospital_names);
$chart_data = [];
$colors = [
    '#FF6633', '#FFB399', '#FF33FF', '#00B3E6', '#E6B33',
    '#3366E6', '#99FF99', '#B34D4D', '#80B300', '#809900',
    '#E6B3B3', '#6680B3', '#66991A'
];
foreach ($hospital_names as $id => $name) {
    $topic_count = $hospital_topics[$id] ?? 0;
    $performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;
    $chart_data[] = $performance_score;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="#performance-overview">performance-graph</a></li>
                <li><a href="education.php">Education</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php" class="btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn">Login</a></li>
                    <li><a href="signup.php" class="btn signup">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" data-aos="fade-right" data-aos-duration="800">
        <div class="sidebar-menu">
            <!-- Hospitals Menu -->
            <div class="sidebar-item">
                <div class="sidebar-title" aria-expanded="false" aria-controls="hospitals-submenu">
                    <i class="fas fa-hospital"></i>
                    <span>Hospitals</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="sidebar-submenu" id="hospitals-submenu">
                    <!-- Arar -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="arar-submenu">
                            <span>Arar</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="arar-submenu">
                            <?php
                            $arar_hospitals = [
                                1 => 'North Medical Tower Hospital',
                                2 => 'Maternity & Children Hospital in Arar',
                                4 => 'Jadeedat Arar Hospital',
                                5 => 'Prince Abdulaziz Bin Mosaad Bin Jallowy Hospital',
                                6 => 'Eradah Complex And Mental Health in Arar',
                                12 => 'Convalescent and Medical Rehabilitation Hospital',
                                13 => 'Prince Abdullah bin Abdulaziz Center for Cardiac Medicine and Surgery'
                            ];
                            foreach ($arar_hospitals as $id => $name) {
                                $topic_count = $hospital_topics[$id] ?? 0;
                                $performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;
                                $badge_class = $performance_score >= 50 ? 'high' : 'low';
                                echo "<a href='hospital.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-hospital-alt'></i>
                                        <span>$name</span>
                                        <span class='performance-badge $badge_class'>$performance_score%</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- Rafha -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="rafha-submenu">
                            <span>Rafha</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="rafha-submenu">
                            <?php
                            $rafha_hospitals = [
                                8 => 'Rafha General Hospital',
                                9 => 'Maternity & Children Hospital in Rafha'
                            ];
                            foreach ($rafha_hospitals as $id => $name) {
                                $topic_count = $hospital_topics[$id] ?? 0;
                                $performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;
                                $badge_class = $performance_score >= 50 ? 'high' : 'low';
                                echo "<a href='hospital.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-hospital-alt'></i>
                                        <span>$name</span>
                                        <span class='performance-badge $badge_class'>$performance_score%</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- Turaif -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="turaif-submenu">
                            <span>Turaif</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="turaif-submenu">
                            <?php
                            $topic_count = $hospital_topics[7] ?? 0;
                            $performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;
                            $badge_class = $performance_score >= 50 ? 'high' : 'low';
                            echo "<a href='hospital.php?id=7' class='submenu-link'>
                                    <i class='fas fa-hospital-alt'></i>
                                    <span>Turaif General Hospital</span>
                                    <span class='performance-badge $badge_class'>$performance_score%</span>
                                  </a>";
                            ?>
                        </div>
                    </div>
                    <!-- Al-Uwayqilah -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="uwayqilah-submenu">
                            <span>Al-Uwayqilah</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="uwayqilah-submenu">
                            <?php
                            $topic_count = $hospital_topics[10] ?? 0;
                            $performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;
                            $badge_class = $performance_score >= 50 ? 'high' : 'low';
                            echo "<a href='hospital.php?id=10' class='submenu-link'>
                                    <i class='fas fa-hospital-alt'></i>
                                    <span>Al-Uwayqilah General Hospital</span>
                                    <span class='performance-badge $badge_class'>$performance_score%</span>
                                  </a>";
                            ?>
                        </div>
                    </div>
                    <!-- Shoabat Nusab -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="nusab-submenu">
                            <span>Shoabat Nusab</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="nusab-submenu">
                            <?php
                            $topic_count = $hospital_topics[11] ?? 0;
                            $performance_score = $max_topics > 0 ? round(($topic_count / $max_topics) * 100) : 0;
                            $badge_class = $performance_score >= 50 ? 'high' : 'low';
                            echo "<a href='hospital.php?id=11' class='submenu-link'>
                                    <i class='fas fa-hospital-alt'></i>
                                    <span>Shoabat Nusab General Hospital</span>
                                    <span class='performance-badge $badge_class'>$performance_score%</span>
                                  </a>";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Health Centers Menu -->
            <div class="sidebar-item">
                <div class="sidebar-title" aria-expanded="false" aria-controls="health-centers-submenu">
                    <i class="fas fa-clinic-medical"></i>
                    <span>Health Centers</span>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="sidebar-submenu" id="health-centers-submenu">
                    <!-- Arar -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="arar-health-submenu">
                            <span>Arar</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="arar-health-submenu">
                            <?php
                            $arar_health_centers = [
                                1 => 'Al-Aziziyah Primary Healthcare Center',
                                2 => 'Al-Khalediah Primary Healthcare Center',
                                3 => 'Al-Mohammadiah Primary Healthcare Center',
                                4 => 'Al-Jawharah Primary Healthcare Center',
                                5 => 'Alsalihia Primary Healthcare Center',
                                6 => 'Al-Mansouriah Primary Healthcare Center',
                                7 => 'Al-Sulaimaniah Primary Healthcare Center',
                                8 => 'Al-Salmaniah Primary Healthcare Center',
                                9 => 'Jadeedath Arar Primary Healthcare Center',
                                10 => 'Al Badanah Primary Healthcare Center',
                                11 => 'Al Faisaliah Primary Healthcare Center',
                                12 => 'Um Khansar Primary Healthcare Center',
                                13 => 'Rabwa Primary Healthcare Center',
                                14 => 'Suburb Primary Healthcare Center'
                            ];
                            foreach ($arar_health_centers as $id => $name) {
                                echo "<a href='health_center.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-clinic-medical'></i>
                                        <span>$name</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- Rafha -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="rafha-health-submenu">
                            <span>Rafha</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="rafha-health-submenu">
                            <?php
                            $rafha_health_centers = [
                                15 => 'Tal’at al-Temiat Primary Healthcare Center',
                                16 => 'Western Rafhaa Primary Healthcare Center',
                                17 => 'East Rafhaa Primary Healthcare Center',
                                18 => 'Northern Rafhaa Primary Healthcare Center',
                                19 => 'Bin Shreaim Primary Healthcare Center',
                                20 => 'Linah Primary Healthcare Center',
                                21 => 'Al-Jemima Primary Healthcare Center',
                                22 => 'Al-Ajramiah Primary Healthcare Center',
                                23 => 'Lawqa Primary Healthcare Center',
                                24 => 'Qesomat Fehan Primary Healthcare Center',
                                25 => 'Al-Jibhan Primary Healthcare Center',
                                26 => 'Al-Kheshaibi Primary Health Center'
                            ];
                            foreach ($rafha_health_centers as $id => $name) {
                                echo "<a href='health_center.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-clinic-medical'></i>
                                        <span>$name</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- Turaif hospital -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="turaif-health-submenu">
                            <span>Turaif</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="turaif-health-submenu">
                            <?php
                            $turaif_health_centers = [
                                28 => 'Central Tareef Primary Healthcare Center',
                                29 => 'Western Tareef Primary Healthcare Center',
                                30 => 'East Tareef Primary Healthcare Center',
                                31 => 'Al-Salehiah Turaif Primary Healthcare Center',
                                32 => 'Hazm Al-Jalameed Primary Healthcare Center'
                            ];
                            foreach ($turaif_health_centers as $id => $name) {
                                echo "<a href='health_center.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-clinic-medical'></i>
                                        <span>$name</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- Al-Uwayqilah -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="uwayqilah-health-submenu">
                            <span>Al-Uwayqilah</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="uwayqilah-health-submenu">
                            <?php
                            $uwayqilah_health_centers = [
                                31 => 'Al-Owaikelah Primary Healthcare Center',
                                32 => 'Almnar Primary Healthcare Center',
                                33 => 'Al-Markouz Primary Healthcare Center',
                                34 => 'Al-Kaseb Primary Healthcare Center',
                                35 => 'Zahwah Primary Healthcare Center',
                                36 => 'Al-Daydab Primary Healthcare Center'
                            ];
                            foreach ($uwayqilah_health_centers as $id => $name) {
                                echo "<a href='health_center.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-clinic-medical'></i>
                                        <span>$name</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- Rawdat Habbas -->
                    <div class="submenu-item">
                        <div class="submenu-title" aria-expanded="false" aria-controls="habbas-health-submenu">
                            <span>Rawdat Habbas</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="submenu-content" id="habbas-health-submenu">
                            <?php
                            $rawdat_habbas_health_centers = [
                                37 => 'Rawdat Habbas Primary Healthcare Center',
                                38 => 'Nsab Primary Healthcare Center'
                            ];
                            foreach ($rawdat_habbas_health_centers as $id => $name) {
                                echo "<a href='health_center.php?id=$id' class='submenu-link'>
                                        <i class='fas fa-clinic-medical'></i>
                                        <span>$name</span>
                                      </a>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content" data-aos="fade-up" data-aos-duration="1000">
                <h1>Northern Borders Health Cluster</h1>
                <p class="slogan">Your Safety First</p>
                <div class="hero-text">
                    <p class="safety-goals-link">From <a href="safety-goals.php">(النماذج)</a></p>
                </div>
                <div class="bottom-links" data-aos="fade-up" data-aos-delay="400" data-aos-duration="800">
                    <a href="contact.php" class="bottom-link">Yasser Alharbi</a>
                    <a href="contact.php" class="bottom-link">Reema Almulla</a>
                </div>
            </div>
        </section>

        <!-- Educational Promo Section -->
        <section class="education-promo" data-aos="fade-up" data-aos-duration="1000">
            <div class="container">
                <h2>Learn Life-Saving Skills</h2>
                <div class="promo-content">
                    <div class="promo-item" data-aos="fade-right" data-aos-delay="200">
                        <h3>How to Use a Fire Extinguisher</h3>
                        <p>Master the PASS technique: Pull, Aim, Squeeze, Sweep.</p>
                        <a href="education.php#fire-extinguisher" class="btn">Watch Animation</a>
                    </div>
                    <div class="promo-item" data-aos="fade-left" data-aos-delay="400">
                        <h3>Evacuation Plan</h3>
                        <p>Learn how to safely evacuate during emergencies.</p>
                        <a href="education.php#evacuation-plan" class="btn">View Plan</a>
                    </div>
                </div>
            </div>
            <style>
                .education-promo {
                    padding: 60px 0;
                    background: linear-gradient(160deg, #f9fbfe 0%, #e5e9f2 100%);
                    text-align: center;
                }
                .education-promo h2 {
                    font-size: 2.8rem;
                    color: #1e293b;
                    margin-bottom: 40px;
                    font-weight: 800;
                    background: linear-gradient(45deg, #1e293b, #5b4eff);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    position: relative;
                }
                .education-promo h2::after {
                    content: '';
                    width: 80px;
                    height: 4px;
                    background: #5b4eff;
                    position: absolute;
                    bottom: -10px;
                    left: 50%;
                    transform: translateX(-50%);
                    border-radius: 2px;
                }
                .promo-content {
                    display: flex;
                    justify-content: center;
                    gap: 30px;
                    flex-wrap: wrap;
                }
                .promo-item {
                    background: #ffffff;
                    padding: 20px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    max-width: 350px;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                }
                .promo-item:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
                }
                .promo-item h3 {
                    font-size: 1.5rem;
                    color: #1e293b;
                    margin-bottom: 10px;
                    font-weight: 600;
                }
                .promo-item p {
                    font-size: 1rem;
                    color: #4b5563;
                    margin-bottom: 15px;
                }
                .promo-item .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: linear-gradient(45deg, #1e293b, #5b4eff);
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 20px;
                    font-weight: 600;
                    transition: background 0.3s, transform 0.3s;
                }
                .promo-item .btn:hover {
                    background: linear-gradient(45deg, #5b4eff, #818cf8);
                    transform: scale(1.05);
                }
                @media (max-width: 768px) {
                    .education-promo h2 {
                        font-size: 2.2rem;
                    }
                    .promo-item {
                        max-width: 100%;
                    }
                    .promo-content {
                        flex-direction: column;
                        align-items: center;
                    }
                }
                @media (max-width: 480px) {
                    .education-promo h2 {
                        font-size: 1.8rem;
                    }
                    .promo-item h3 {
                        font-size: 1.3rem;
                    }
                    .promo-item p {
                        font-size: 0.9rem;
                    }
                    .promo-item .btn {
                        padding: 8px 15px;
                        font-size: 0.9rem;
                    }
                }
            </style>
        </section>

        <!-- Performance Overview Section -->
        <section id="performance-overview" class="performance-overview">
            <div class="container">
                <h2 data-aos="fade-up" data-aos-duration="800">Hospital Performance Overview</h2>
                <div class="chart-container" data-aos="zoom-in" data-aos-duration="1000" data-aos-delay="200">
                    <canvas id="hospitalPerformanceChart"></canvas>
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
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Toggle mobile menu
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });

            // Sidebar toggle functionality
            const sidebarTitles = document.querySelectorAll('.sidebar-title');
            const submenuTitles = document.querySelectorAll('.submenu-title');

            sidebarTitles.forEach(title => {
                title.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const submenu = title.nextElementSibling;
                    const toggleIcon = title.querySelector('.toggle-icon');
                    const isExpanded = title.getAttribute('aria-expanded') === 'true';
                    title.setAttribute('aria-expanded', !isExpanded);
                    submenu.classList.toggle('active');
                    toggleIcon.classList.toggle('fa-chevron-down');
                    toggleIcon.classList.toggle('fa-chevron-up');
                });
            });

            submenuTitles.forEach(title => {
                title.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const submenuContent = title.nextElementSibling;
                    const toggleIcon = title.querySelector('.toggle-icon');
                    const isExpanded = title.getAttribute('aria-expanded') === 'true';
                    title.setAttribute('aria-expanded', !isExpanded);
                    submenuContent.classList.toggle('active');
                    toggleIcon.classList.toggle('fa-chevron-down');
                    toggleIcon.classList.toggle('fa-chevron-up');
                });
            });

            // Hospital Performance Chart
            const ctx = document.getElementById('hospitalPerformanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Performance Score (%)',
                        data: <?php echo json_encode($chart_data); ?>,
                        backgroundColor: <?php echo json_encode($colors); ?>,
                        borderColor: <?php echo json_encode($colors); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { stepSize: 10, callback: value => value + '%' },
                            title: { display: true, text: 'Performance Score (%)', font: { size: 14, weight: 'bold' }, color: '#293b85' }
                        },
                        x: {
                            ticks: { autoSkip: false, maxRotation: 45, minRotation: 45, font: { size: 10 } },
                            title: { display: true, text: 'Hospitals', font: { size: 14, weight: 'bold' }, color: '#293b85' }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { font: { size: 12 }, color: '#293b85' } },
                        tooltip: { backgroundColor: '#293b85', callbacks: { label: context => `${context.dataset.label}: ${context.parsed.y}%` } }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    barThickness: 20
                }
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