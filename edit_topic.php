<?php
require_once './includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Unauthorized action.";
    header("Location: index.php");
    exit();
}

$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : NULL;
$health_center_id = isset($_GET['health_center_id']) ? (int)$_GET['health_center_id'] : NULL;
$topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;

// Validate facility and topic IDs
if ($topic_id <= 0 || ($hospital_id === NULL && $health_center_id === NULL)) {
    $_SESSION['error'] = "Invalid facility or topic ID.";
    error_log("edit_topic.php: Invalid inputs: topic_id=$topic_id, hospital_id=$hospital_id, health_center_id=$health_center_id");
    header("Location: index.php");
    exit();
}

// Verify manager's facility affiliation (assuming users table tracks hospital_id only for now)
$stmt = $conn->prepare("SELECT hospital_id FROM users WHERE id = ? AND role = 'manager'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($hospital_id !== NULL && $user['hospital_id'] != $hospital_id) {
    $_SESSION['error'] = "You are not authorized to manage this hospital.";
    header("Location: hospital.php?id=$hospital_id");
    exit();
}
// Note: Add health_center_id check if users table supports it
if ($health_center_id !== NULL) {
    // Assuming manager can manage any health center for simplicity
    // Add stricter checks if needed (e.g., users.health_center_id)
}

// Fetch facility details
$facility = null;
$facility_type = '';
if ($hospital_id !== NULL) {
    $stmt = $conn->prepare("SELECT name FROM hospitals WHERE id = ?");
    $stmt->bind_param("i", $hospital_id);
    $facility_type = 'hospital';
} else {
    $stmt = $conn->prepare("SELECT name FROM health_centers WHERE id = ?");
    $stmt->bind_param("i", $health_center_id);
    $facility_type = 'health_center';
}
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("edit_topic.php: Database error for {$facility_type}_id=" . ($hospital_id ?: $health_center_id) . ": " . $conn->error);
    header("Location: index.php");
    exit();
}
$facility = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$facility) {
    $_SESSION['error'] = ucfirst($facility_type) . " not found.";
    error_log("edit_topic.php: " . ucfirst($facility_type) . " not found for id=" . ($hospital_id ?: $health_center_id));
    header("Location: index.php");
    exit();
}

// Fetch topic details
$stmt = $conn->prepare("SELECT title, content, indicator, media_type, media_path, table_data, status FROM topics WHERE id = ? AND (hospital_id = ? OR health_center_id = ?)");
$stmt->bind_param("iii", $topic_id, $hospital_id, $health_center_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    error_log("edit_topic.php: Database error for topic_id=$topic_id: " . $conn->error);
    header("Location: {$facility_type}.php?id=" . ($hospital_id ?: $health_center_id));
    exit();
}
$topic = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$topic) {
    $_SESSION['error'] = "Topic not found.";
    error_log("edit_topic.php: Topic not found for id=$topic_id");
    header("Location: {$facility_type}.php?id=" . ($hospital_id ?: $health_center_id));
    exit();
}

// Map indicator integers to strings for form
$indicator_map = [
    -1 => 'critical',
    0 => 'normal',
    1 => 'positive'
];
$indicator_value = $indicator_map[$topic['indicator']] ?? 'normal';

// Parse table data
$table_data = $topic['table_data'] ? json_decode($topic['table_data'], true) : ['headers' => [], 'rows' => []];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Topic - <?php echo htmlspecialchars($facility['name'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
       /* Root Variables for Consistent Color Scheme */
:root {
    --primary-color:rgb(10, 26, 70); /* Deep Blue */
    --secondary-color:rgb(7, 33, 75); /* Bright Blue */
    --accent-color: #F59E0B; /* Warm Amber */
    --text-color: #1F2937; /* Dark Gray */
    --background-color: #F9FAFB; /* Light Gray */
    --white: #FFFFFF; /* White */
    --shadow-color: rgba(0, 0, 0, 0.1); /* Subtle shadow */
    --success-color: #10B981; /* Emerald Green */
    --error-color: #EF4444; /* Red */
}

/* Reset and General Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    color: var(--text-color);
    background: var(--background-color);
    line-height: 1.6;
    overflow-x: hidden;
}

/* Container */
.container {
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

/* Navbar */
.navbar {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 15px var(--shadow-color);
    transition: background 0.3s ease;
}

.navbar.sticky {
    background: var(--primary-color);
}

.nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--accent-color);
    transition: transform 0.3s ease;
}

.logo-circle:hover {
    transform: scale(1.05);
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
    margin-left: 30px;
}

.nav-menu a {
    color: var(--white);
    text-decoration: none;
    font-weight: 500;
    font-size: 1.1rem;
    transition: color 0.3s ease, transform 0.2s ease;
}

.nav-menu a:hover {
    color: var(--accent-color);
    transform: translateY(-2px);
}

.nav-menu a.btn {
    background: var(--accent-color);
    color: var(--primary-color);
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease;
}

.nav-menu a.btn:hover {
    background: var(--white);
    color: var(--primary-color);
    transform: scale(1.05);
}

.nav-menu a.signup {
    background: var(--white);
    color: var(--primary-color);
}

.nav-menu a.signup:hover {
    background: var(--accent-color);
}

.menu-toggle {
    display: none;
    font-size: 1.8rem;
    color: var(--white);
    cursor: pointer;
}

/* Hospital Content Section */
.hospital-content {
    padding: 60px 0;
    background: linear-gradient(to bottom, var(--background-color), var(--white));
    animation: fadeIn 0.5s ease-out;
}

h2, h3 {
    color: var(--primary-color);
    margin-bottom: 20px;
    position: relative;
}

h2 {
    font-size: 2.5rem;
    text-align: center;
}

h2::after {
    content: '';
    width: 80px;
    height: 4px;
    background: var(--accent-color);
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 2px;
}

h3 {
    font-size: 1.8rem;
}

.success, .error {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.success {
    background: #d1fae5;
    color: var(--success-color);
}

.error {
    background: #fee2e2;
    color: var(--error-color);
}

/* Topic Form */
.topic-form {
    background: var(--white);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 10px 30px var(--shadow-color);
    margin-bottom: 30px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    transition: transform 0.3s ease;
}

.topic-form:hover {
    transform: translateY(-5px);
}

.topic-form form {
    display: grid;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px;
    border: 2px solid var(--primary-color);
    border-radius: 8px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #9ca3af;
    font-style: italic;
}

.form-group select {
    appearance: none;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%231E3A8A" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 12px center;
    background-size: 12px;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.media-upload p {
    margin-top: 10px;
    font-size: 0.9rem;
}

.media-upload img {
    max-width: 100px;
    border-radius: 8px;
    border: 2px solid var(--primary-color);
}

.media-upload a {
    color: var(--primary-color);
    text-decoration: underline;
}

.table-header-input,
.table-cell-input {
    width: 100%;
    margin-bottom: 10px;
}

.table-row {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 10px;
}

.table-row input {
    flex: 1;
}

.action-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
}

.action-btn:hover {
    background: linear-gradient(135deg, var(--accent-color), #fbbf24);
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px var(--shadow-color);
}

.action-btn.primary-btn {
    background: linear-gradient(135deg, var(--accent-color), #fbbf24);
    color: var(--primary-color);
}

.action-btn.primary-btn:hover {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
}

.action-btn.delete-btn {
    background: var(--error-color);
}

.action-btn.delete-btn:hover {
    background: #b91c1c;
    color: var(--white);
}

/* Footer */
footer {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    text-align: center;
    padding: 20px 0;
    margin-top: 40px;
}

footer p {
    font-size: 0.9rem;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .nav-menu {
        display: none;
        flex-direction: column;
        position: absolute;
        top: 80px;
        left: 0;
        width: 100%;
        background: var(--primary-color);
        padding: 20px;
    }

    .nav-menu.active {
        display: flex;
    }

    .nav-menu li {
        margin: 15px 0;
    }

    .menu-toggle {
        display: block;
    }

    .hospital-content {
        padding: 40px 0;
    }

    h2 {
        font-size: 2rem;
    }

    .topic-form {
        padding: 20px;
    }
}

@media (max-width: 480px) {
    h2 {
        font-size: 1.8rem;
    }

    .form-group label {
        font-size: 1rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        font-size: 0.9rem;
        padding: 10px;
    }

    .action-btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }

    .success, .error {
        font-size: 0.9rem;
        padding: 10px;
    }

    .table-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .table-row input {
        width: 100%;
    }
}
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container container">
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
                <li><a href="<?php echo $facility_type; ?>.php?id=<?php echo ($hospital_id ?: $health_center_id); ?>">Back to <?php echo ucfirst($facility_type); ?></a></li>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Edit Topic Section -->
    <section class="hospital-content">
        <div class="container">
            <h2>Edit Topic - <?php echo htmlspecialchars($facility['name'] ?? ''); ?></h2>
            <!-- Feedback Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success'] ?? ''); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error'] ?? ''); unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <div class="topic-form">
                <form action="update_topic.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="<?php echo $facility_type; ?>_id" value="<?php echo ($hospital_id ?: $health_center_id); ?>">
                    <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                    <div class="form-group">
                        <label for="title">Topic Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($topic['title'] ?? ''); ?>" placeholder="Enter topic title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" placeholder="Enter topic content"><?php echo htmlspecialchars($topic['content'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="indicator">Indicator</label>
                        <select id="indicator" name="indicator" required>
                            <option value="critical" <?php echo $indicator_value === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="normal" <?php echo $indicator_value === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="positive" <?php echo $indicator_value === 'positive' ? 'selected' : ''; ?>>Positive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="visible" <?php echo $topic['status'] === 'visible' ? 'selected' : ''; ?>>Visible</option>
                            <option value="hidden" <?php echo $topic['status'] === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                            <option value="archived" <?php echo $topic['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    <div class="form-group media-upload">
                        <label for="media_file">Upload New Media (Image, Video, File)</label>
                        <input type="file" id="media_file" name="media_file" accept="image/*,video/mp4,video/mov,application/pdf,.doc,.docx">
                        <?php if ($topic['media_type'] && $topic['media_path']): ?>
                            <p>Current Media: 
                                <?php if ($topic['media_type'] === 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($topic['media_path'] ?? ''); ?>" alt="Current Media" style="max-width: 100px; border-radius: 8px;">
                                <?php elseif ($topic['media_type'] === 'video'): ?>
                                    <a href="<?php echo htmlspecialchars($topic['media_path'] ?? ''); ?>" target="_blank">View Video</a>
                                <?php elseif ($topic['media_type'] === 'file'): ?>
                                    <a href="<?php echo htmlspecialchars($topic['media_path'] ?? ''); ?>" target="_blank">Download File</a>
                                <?php elseif ($topic['media_type'] === 'external'): ?>
                                    <a href="<?php echo htmlspecialchars($topic['media_path'] ?? ''); ?>" target="_blank">External URL</a>
                                <?php endif; ?>
                            </p>
                            <label><input type="checkbox" name="remove_media" value="1"> Remove Current Media</label>
                        <?php endif; ?>
                    </div>
                    <div class="form-group media-upload">
                        <label for="external_url">Or Enter External URL</label>
                        <input type="url" id="external_url" name="external_url" value="<?php echo $topic['media_type'] === 'external' ? htmlspecialchars($topic['media_path'] ?? '') : ''; ?>" placeholder="https://example.com">
                    </div>
                    <div class="form-group">
                        <label>Table Data</label>
                        <div id="table-input">
                            <div class="form-group">
                                <label>Table Headers</label>
                                <div id="table-headers">
                                    <?php foreach ($table_data['headers'] as $index => $header): ?>
                                        <input type="text" name="table_headers[]" value="<?php echo htmlspecialchars($header ?? ''); ?>" placeholder="Header <?php echo $index + 1; ?>" class="table-header-input">
                                    <?php endforeach; ?>
                                    <?php if (empty($table_data['headers'])): ?>
                                        <input type="text" name="table_headers[]" placeholder="Header 1" class="table-header-input">
                                        <input type="text" name="table_headers[]" placeholder="Header 2" class="table-header-input">
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="action-btn" onclick="addHeader()">Add Header</button>
                            </div>
                            <div class="form-group">
                                <label>Table Rows</label>
                                <div id="table-rows">
                                    <?php foreach ($table_data['rows'] as $row_index => $row): ?>
                                        <div class="table-row">
                                            <?php foreach ($row as $cell): ?>
                                                <input type="text" name="table_data[<?php echo $row_index; ?>][]" value="<?php echo htmlspecialchars($cell ?? ''); ?>" placeholder="Cell" class="table-cell-input">
                                            <?php endforeach; ?>
                                            <button type="button" class="action-btn delete-btn" onclick="removeRow(this)">Remove Row</button>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($table_data['rows'])): ?>
                                        <div class="table-row">
                                            <input type="text" name="table_data[0][]" placeholder="Cell 1" class="table-cell-input">
                                            <input type="text" name="table_data[0][]" placeholder="Cell 2" class="table-cell-input">
                                            <button type="button" class="action-btn delete-btn" onclick="removeRow(this)">Remove Row</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="action-btn" onclick="addRow()">Add Row</button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="action-btn primary-btn">Update Topic</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>© 2025 Northern Borders Health Cluster. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            if (menuToggle && navMenu) {
                menuToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                });
            }
        });

        let headerCount = <?php echo count($table_data['headers']) ?: 2; ?>;
        function addHeader() {
            const headersDiv = document.getElementById('table-headers');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'table_headers[]';
            input.placeholder = `Header ${headerCount + 1}`;
            input.className = 'table-header-input';
            headersDiv.appendChild(input);
            headerCount++;
            updateTableRows();
        }

        let rowCount = <?php echo count($table_data['rows']) ?: 1; ?>;
        function addRow() {
            const rowsDiv = document.getElementById('table-rows');
            const rowDiv = document.createElement('div');
            rowDiv.className = 'table-row';
            for (let i = 0; i < headerCount; i++) {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = `table_data[${rowCount}][]`;
                input.placeholder = `Cell ${i + 1}`;
                input.className = 'table-cell-input';
                rowDiv.appendChild(input);
            }
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'action-btn delete-btn';
            removeBtn.textContent = 'Remove Row';
            removeBtn.onclick = () => removeRow(removeBtn);
            rowDiv.appendChild(removeBtn);
            rowsDiv.appendChild(rowDiv);
            rowCount++;
        }

        function removeRow(btn) {
            if (rowCount > 1) {
                btn.parentElement.remove();
                rowCount--;
            }
        }

        function updateTableRows() {
            const rowsDiv = document.getElementById('table-rows');
            const rows = rowsDiv.getElementsByClassName('table-row');
            for (let i = 0; i < rows.length; i++) {
                const inputs = rows[i].getElementsByClassName('table-cell-input');
                while (inputs.length > headerCount) {
                    inputs[inputs.length - 1].remove();
                }
                while (inputs.length < headerCount) {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = `table_data[${i}][]`;
                    input.placeholder = `Cell ${inputs.length + 1}`;
                    input.className = 'table-cell-input';
                    rows[i].insertBefore(input, rows[i].lastElementChild);
                }
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>