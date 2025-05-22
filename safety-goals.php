<?php
session_start();
require_once './includes/config.php';

// Fetch uploaded files for each form type
$uploads = [];
$form_types = ['safety_systems', 'employee_training', 'environmental_tours', 'evacuation_plan', 'meeting_committee', 'building_safety'];
foreach ($form_types as $form_type) {
    $stmt = $conn->prepare("SELECT id, file_path, uploaded_at FROM form_uploads WHERE form_type = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("s", $form_type);
    $stmt->execute();
    $uploads[$form_type] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forms - Northern Borders Health Cluster</title>
    <link rel="stylesheet" href="assets/css/safety-goals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* General Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        color: #1e293b;
        background: linear-gradient(160deg, #f9fbfe 0%, #e5e9f2 100%);
        overflow-x: hidden;
        position: relative;
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

    /* Container */
    .container {
        width: 90%;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Navbar */
    .navbar {
        position: fixed;
        top: 0;
        width: 100%;
        background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
        padding: 12px 0;
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .nav-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .logo-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid #5b4eff;
    }

    .logo-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .nav-menu {
        display: flex;
        list-style: none;
    }

    .nav-menu li {
        margin-left: 20px;
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

    .nav-menu .btn {
        padding: 8px 18px;
        background: #ffffff;
        color: #1e293b;
        border-radius: 20px;
        font-weight: 600;
        transition: background 0.3s, color 0.3s, transform 0.3s ease;
    }

    .nav-menu .btn:hover {
        background: #e0e7ff;
        color: #1e293b;
        transform: scale(1.05);
    }

    .nav-menu .signup {
        background: linear-gradient(45deg, #1e293b, #5b4eff);
        color: #ffffff;
    }

    .nav-menu .signup:hover {
        background: linear-gradient(45deg, #5b4eff, #818cf8);
        color: #ffffff;
    }

    .menu-toggle {
        display: none;
        font-size: 1.4rem;
        color: #ffffff;
        cursor: pointer;
    }

    /* Forms Section */
    .forms-section {
        padding: 80px 0;
        background: transparent;
    }

    .forms-section h1 {
        text-align: center;
        font-size: 2.8rem;
        color: #1e293b;
        margin-bottom: 20px;
        position: relative;
        font-weight: 800;
        background: linear-gradient(45deg, #1e293b, #5b4eff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .forms-section p.subtitle {
        text-align: center;
        font-size: 1.5rem;
        color: #475569;
        margin-bottom: 50px;
        font-weight: 400;
    }

    .forms-section h1::after {
        content: '';
        width: 100px;
        height: 4px;
        background: #5b4eff;
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        border-radius: 2px;
    }

    /* Forms Grid */
    .forms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .form-card {
        background: #f9fbfe;
        padding: 20px;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        border-left: 4px solid #5b4eff;
        text-align: center;
        border-radius: 12px;
    }

    .form-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .form-card .form-icon {
        font-size: 2.5rem;
        color: #5b4eff;
        margin-bottom: 15px;
        transition: color 0.3s;
    }

    .form-card:hover .form-icon {
        color: #818cf8;
    }

    .form-card h3 {
        font-size: 1.6rem;
        color: #1e293b;
        margin: 10px 0;
        font-weight: 700;
        background: linear-gradient(45deg, #1e293b, #5b4eff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .form-card p {
        font-size: 1rem;
        color: #475569;
        margin-bottom: 20px;
        font-weight: 400;
    }

    /* Uploaded Files Section */
    .uploaded-files {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
        text-align: left;
    }

    .uploaded-files h4 {
        font-size: 1.2rem;
        color: #1e293b;
        margin-bottom: 10px;
        font-weight: 700;
        background: linear-gradient(45deg, #1e293b, #5b4eff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .uploaded-files ul {
        list-style: none;
        padding: 0;
    }

    .uploaded-files li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.3s;
    }

    .uploaded-files li:hover {
        background: #e0e7ff;
    }

    .uploaded-files a {
        color: #5b4eff;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: color 0.3s;
    }

    .uploaded-files a:hover {
        color: #818cf8;
    }

    .uploaded-files .file-date {
        font-size: 0.8rem;
        color: #475569;
        font-weight: 400;
    }

    /* Feedback Messages */
    .success, .error {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 12px;
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
        color: #ffffff;
    }

    .success {
        background: linear-gradient(45deg, #10b981, #34d399);
    }

    .error {
        background: linear-gradient(45deg, #ef4444, #f87171);
    }

    /* Footer */
    footer {
        background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
        color: #ffffff;
        text-align: center;
        padding: 20px 0;
    }

    footer p {
        font-size: 0.9rem;
        font-weight: 400;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .menu-toggle {
            display: block;
        }

        .nav-menu {
            position: fixed;
            top: 74px;
            right: -100%;
            width: 250px;
            height: 100vh;
            background: linear-gradient(90deg, #1e293b 0%, #5b4eff 100%);
            flex-direction: column;
            padding: 20px;
            transition: right 0.3s;
        }

        .nav-menu.active {
            right: 0;
        }

        .nav-menu li {
            margin: 15px 0;
        }

        .forms-section h1 {
            font-size: 2.2rem;
        }

        .forms-section p.subtitle {
            font-size: 1.2rem;
        }

        .forms-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .form-card h3 {
            font-size: 1.4rem;
        }

        .form-card p {
            font-size: 0.9rem;
        }

        .form-card .form-icon {
            font-size: 2rem;
        }
    }

    @media (max-width: 480px) {
        .forms-section h1 {
            font-size: 1.8rem;
        }

        .forms-section p.subtitle {
            font-size: 1rem;
        }

        .form-card h3 {
            font-size: 1.2rem;
        }

        .form-card p {
            font-size: 0.85rem;
        }

        .form-card .form-icon {
            font-size: 1.8rem;
        }

        .uploaded-files h4 {
            font-size: 1rem;
        }

        .uploaded-files a {
            font-size: 0.85rem;
        }

        .uploaded-files .file-date {
            font-size: 0.75rem;
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php" class="btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn">Login</a></li>
                    <li><a href="signup.php" class="btn signup">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Forms Section -->
    <section class="forms-section">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <h1>النماذج</h1>
            <p class="subtitle">Forms</p>
            <div class="forms-grid">
                <!-- Safety Systems Follow-up Form -->
                <div class="form-card">
                    <i class="fas fa-check-circle fa-2x form-icon"></i>
                    <h3>نموذج متابعة أنظمة السلامة</h3>
                    <p>Safety Systems Follow-up Form</p>
                    <div class="uploaded-files">
                        <h4>Uploaded Files</h4>
                        <ul>
                            <?php foreach ($uploads['safety_systems'] as $upload): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                    </a>
                                    <span class="file-date"><?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- Employee Training Form -->
                <div class="form-card">
                    <i class="fas fa-user-graduate fa-2x form-icon"></i>
                    <h3>نموذج تدريب الموظفين</h3>
                    <p>Employee Training Form</p>
                    <div class="uploaded-files">
                        <h4>Uploaded Files</h4>
                        <ul>
                            <?php foreach ($uploads['employee_training'] as $upload): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                    </a>
                                    <span class="file-date"><?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- Environmental Tours Form -->
                <div class="form-card">
                    <i class="fas fa-tree fa-2x form-icon"></i>
                    <h3>نموذج الجولات البيئية</h3>
                    <p>Environmental Tours Form</p>
                    <div class="uploaded-files">
                        <h4>Uploaded Files</h4>
                        <ul>
                            <?php foreach ($uploads['environmental_tours'] as $upload): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                    </a>
                                    <span class="file-date"><?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- Evacuation Plan Template -->
                <div class="form-card">
                    <i class="fas fa-map-signs fa-2x form-icon"></i>
                    <h3>نموذج خطة الإخلاء</h3>
                    <p>Evacuation Plan Template</p>
                    <div class="uploaded-files">
                        <h4>Uploaded Files</h4>
                        <ul>
                            <?php foreach ($uploads['evacuation_plan'] as $upload): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                    </a>
                                    <span class="file-date"><?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- Meeting Committee Form -->
                <div class="form-card">
                    <i class="fas fa-users fa-2x form-icon"></i>
                    <h3>نموذج لجنة الاجتماعات</h3>
                    <p>Meeting Committee Form</p>
                    <div class="uploaded-files">
                        <h4>Uploaded Files</h4>
                        <ul>
                            <?php foreach ($uploads['meeting_committee'] as $upload): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                    </a>
                                    <span class="file-date"><?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- Building Safety Walkthrough -->
                <div class="form-card">
                    <i class="fas fa-building fa-2x form-icon"></i>
                    <h3>نموذج جولات سلامة المبنى</h3>
                    <p>Building Safety Walkthrough</p>
                    <div class="uploaded-files">
                        <h4>Uploaded Files</h4>
                        <ul>
                            <?php foreach ($uploads['building_safety'] as $upload): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" download>
                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                    </a>
                                    <span class="file-date"><?php echo date('Y-m-d H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
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