<?php
session_start();
require_once './includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['error'] = "Access denied. Managers only.";
    error_log("messages.php: Access denied for user_id={$_SESSION['user_id']}, role={$_SESSION['role']}");
    header("Location: login.php");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("SELECT id, username, profile_picture, hospital_id, health_center_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    session_destroy();
    header("Location: login.php");
    exit();
}

// Log user details
error_log("messages.php: Logged in user_id={$user['id']}, username={$user['username']}, hospital_id={$user['hospital_id']}, health_center_id={$user['health_center_id']}");

// Determine facility type
$facility_type = $user['hospital_id'] ? 'hospital' : ($user['health_center_id'] ? 'health_center' : null);
if (!$facility_type) {
    $_SESSION['error'] = "User not associated with any facility.";
    error_log("messages.php: User_id={$_SESSION['user_id']} not associated with hospital or health center");
    header("Location: login.php");
    exit();
}

// Fetch managers based on facility type
$managers = [];
if ($facility_type === 'hospital') {
    $stmt = $conn->prepare("SELECT u.id, u.username, u.profile_picture, h.name as facility_name, u.hospital_id, u.health_center_id 
                            FROM users u 
                            JOIN hospitals h ON u.hospital_id = h.id 
                            WHERE u.role = 'manager' AND u.id != ? AND u.hospital_id IS NOT NULL");
} else {
    $stmt = $conn->prepare("SELECT u.id, u.username, u.profile_picture, hc.name as facility_name, u.hospital_id, u.health_center_id 
                            FROM users u 
                            JOIN health_centers hc ON u.health_center_id = hc.id 
                            WHERE u.role = 'manager' AND u.id != ? AND u.health_center_id IS NOT NULL");
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$managers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log fetched managers
if (empty($managers)) {
    error_log("messages.php: No managers found for user_id={$_SESSION['user_id']}, facility_type=$facility_type");
} else {
    foreach ($managers as $manager) {
        error_log("messages.php: Fetched manager id={$manager['id']}, username={$manager['username']}, hospital_id={$manager['hospital_id']}, health_center_id={$manager['health_center_id']}, facility_name={$manager['facility_name']}, facility_type=$facility_type");
    }
}

// Fetch unread message counts
$unread_counts = [];
foreach ($managers as $manager) {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages 
                            WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $_SESSION['user_id'], $manager['id']);
    $stmt->execute();
    $unread_counts[$manager['id']] = $stmt->get_result()->fetch_assoc()['unread'];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Chat - Northern Borders Health Cluster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f0f2f5; }
        .chat-container { display: flex; height: calc(100vh - 64px); }
        .manager-list { width: 320px; background: #fff; border-right: 1px solid #e0e0e0; overflow-y: auto; }
        .manager-item { padding: 12px 16px; display: flex; align-items: center; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; }
        .manager-item:hover { background: #f5f5f5; }
        .manager-item.active { background: #e6f0fa; }
        .manager-avatar { width: 48px; height: 48px; border-radius: 50%; margin-right: 12px; object-fit: cover; }
        .manager-info { flex: 1; }
        .manager-name { font-weight: 500; color: #111b21; }
        .facility-name { font-size: 0.85rem; color: #667781; }
        .unread-badge { background: #25d366; color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-left: auto; }
        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 12px 16px; background: #075e54; color: #fff; display: flex; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .chat-header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 12px; }
        .chat-header .status-dot { width: 10px; height: 10px; background: #25d366; border-radius: 50%; margin-left: 8px; }
        .chat-messages { flex: 1; padding: 16px; overflow-y: auto; }
        .message { max-width: 50%; padding: 8px 12px; margin: 6px 8px; border-radius: 8px; position: relative; animation: fadeIn 0.3s ease-in; }
        .message.sent { background: #d9fdd3; margin-left: auto; border-bottom-right-radius: 2px; }
        .message.sent::after { content: ''; position: absolute; bottom: 0; right: -8px; border: 8px solid transparent; border-bottom-color: #d9fdd3; border-right-color: #d9fdd3; }
        .message.received { background: #fff; border-bottom-left-radius: 2px; }
        .message.received::after { content: ''; position: absolute; bottom: 0; left: -8px; border: 8px solid transparent; border-bottom-color: #fff; border-left-color: #fff; }
        .message img { max-width: 100%; border-radius: 8px; margin-top: 4px; }
        .message a.file-link { color: #075e54; text-decoration: underline; display: flex; align-items: center; }
        .message a.file-link i { margin-right: 6px; }
        .message-timestamp { font-size: 10px; color: #667781; margin-top: 4px; text-align: right; }
        .chat-input { padding: 12px 16px; background: #f0f2f5; border-top: 1px solid #e0e0e0; }
        .chat-input form { display: flex; align-items: center; background: #fff; border-radius: 20px; padding: 8px; }
        .chat-input input[type="text"] { flex: 1; padding: 8px; border: none; outline: none; font-size: 14px; }
        .chat-input input[type="file"] { display: none; }
        .chat-input label { cursor: pointer; color: #667781; margin: 0 8px; }
        .chat-input button { background: #25d366; color: #fff; border: none; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .chat-input button:hover { background: #20b95a; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) {
            .chat-container { flex-direction: column; }
            .manager-list { width: 100%; height: 200px; }
            .chat-area { height: calc(100vh - 264px); }
        }
        .no-managers { padding: 16px; text-align: center; color: #667781; }
    </style>
</head>
<body>
    <nav class="navbar bg-gray-800 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="logo-circle">
                <img src="assets/images/logo.png" alt="Northern Borders Health Cluster Logo" class="w-10 h-10">
            </div>
            <ul class="flex space-x-4">
                <li><a href="index.php" class="hover:underline">Home</a></li>
                <li><a href="index.php#hospitals" class="hover:underline">Hospitals</a></li>
                <li><a href="index.php#health-centers" class="hover:underline">Health Centers</a></li>
                <li><a href="logout.php" class="bg-red-500 px-4 py-2 rounded hover:bg-red-600">Logout</a></li>
            </ul>
        </div>
    </nav>
    <div class="chat-container">
        <div class="manager-list">
            <?php if (empty($managers)): ?>
                <div class="no-managers">No other managers available to chat.</div>
            <?php else: ?>
                <?php foreach ($managers as $manager): ?>
                    <div class="manager-item" data-manager-id="<?php echo $manager['id']; ?>">
                        <img src="<?php echo htmlspecialchars($manager['profile_picture'] ?: 'assets/images/default_user.png'); ?>" alt="<?php echo htmlspecialchars($manager['username']); ?>" class="manager-avatar">
                        <div class="manager-info">
                            <p class="manager-name"><?php echo htmlspecialchars($manager['username']); ?></p>
                            <p class="facility-name"><?php echo htmlspecialchars($manager['facility_name']); ?> (<?php echo $facility_type === 'hospital' ? 'Hospital' : 'Health Center'; ?>)</p>
                        </div>
                        <?php if ($unread_counts[$manager['id']] > 0): ?>
                            <span class="unread-badge"><?php echo $unread_counts[$manager['id']]; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="chat-area">
            <div class="chat-header">
                <p class="font-semibold">Select a manager to start chatting</p>
            </div>
            <div class="chat-messages" id="chat-messages"></div>
            <div class="chat-input">
                <form id="chat-form" enctype="multipart/form-data">
                    <label for="file-input"><i class="fas fa-paperclip"></i></label>
                    <input type="file" id="file-input" name="file" accept="image/*,.pdf,.doc,.docx">
                    <input type="text" id="message-input" placeholder="Type a message..." disabled>
                    <button type="submit"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>
    <script>
        const userId = <?php echo $_SESSION['user_id']; ?>;
        const facilityId = <?php echo $facility_type === 'hospital' ? $user['hospital_id'] : $user['health_center_id']; ?>;
        const facilityType = '<?php echo $facility_type; ?>';
        let currentManagerId = null;
        let lastMessageId = 0;

        function fetchMessages() {
            if (!currentManagerId) return;
            fetch(`get_messages.php?manager_id=${currentManagerId}&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(messages => {
                    messages.forEach(message => {
                        displayMessage(message);
                        if (message.message_id > lastMessageId) {
                            lastMessageId = message.message_id;
                        }
                        if (message.sender_id != userId && message.is_read == 0) {
                            markMessageAsRead(message.message_id);
                        }
                    });
                })
                .catch(error => console.error('Error fetching messages:', error));
        }

        function displayMessage(message) {
            const chatMessages = document.getElementById('chat-messages');
            const div = document.createElement('div');
            div.className = `message ${message.sender_id == userId ? 'sent' : 'received'}`;
            let content = message.content ? `<p>${message.content}</p>` : '';
            if (message.file_path) {
                const ext = message.file_path.split('.').pop().toLowerCase();
                if (['png', 'jpg', 'jpeg', 'gif'].includes(ext)) {
                    content += `<img src="${message.file_path}" alt="Image">`;
                } else {
                    content += `<a href="${message.file_path}" class="file-link" target="_blank"><i class="fas fa-file"></i>${message.file_path.split('/').pop()}</a>`;
                }
            }
            div.innerHTML = `
                ${content}
                <p class="message-timestamp">${new Date(message.created_at).toLocaleTimeString()}</p>
            `;
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function markMessageAsRead(messageId) {
            fetch('mark_message_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `message_id=${messageId}`
            }).then(() => {
                updateUnreadCounts();
            });
        }

        function updateUnreadCounts() {
            document.querySelectorAll('.manager-item').forEach(item => {
                const managerId = item.dataset.managerId;
                fetch(`get_unread_count.php?manager_id=${managerId}`)
                    .then(response => response.json())
                    .then(data => {
                        const badge = item.querySelector('.unread-badge');
                        if (data.unread > 0) {
                            if (badge) {
                                badge.textContent = data.unread;
                            } else {
                                const span = document.createElement('span');
                                span.className = 'unread-badge';
                                span.textContent = data.unread;
                                item.appendChild(span);
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            });
        }

        document.querySelectorAll('.manager-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.manager-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                currentManagerId = parseInt(item.dataset.managerId);
                document.querySelector('.chat-header').innerHTML = `
                    <img src="${item.querySelector('img').src}" alt="Avatar">
                    <div>
                        <p class="font-semibold">${item.querySelector('.manager-name').textContent}</p>
                        <span class="status-dot"></span>
                    </div>
                `;
                document.getElementById('message-input').disabled = false;
                document.getElementById('file-input').disabled = false;
                document.querySelector('#chat-form button').disabled = false;
                lastMessageId = 0;
                document.getElementById('chat-messages').innerHTML = '';
                fetchMessages();
            });
        });

        document.getElementById('chat-form').addEventListener('submit', (e) => {
            e.preventDefault();
            if (!currentManagerId) {
                alert('Please select a manager to send a message.');
                return;
            }
            const input = document.getElementById('message-input');
            const fileInput = document.getElementById('file-input');
            const content = input.value.trim();
            const formData = new FormData();
            formData.append('receiver_id', currentManagerId);
            formData.append('content', content);
            formData.append('facility_type', facilityType);
            formData.append('facility_id', facilityId);
            if (fileInput.files.length > 0) {
                formData.append('file', fileInput.files[0]);
            }
            if (!content && fileInput.files.length === 0) {
                alert('Please enter a message or select a file.');
                return;
            }
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      input.value = '';
                      fileInput.value = '';
                      fetchMessages();
                  } else {
                      alert('Error sending message: ' + (data.error || 'Unknown error'));
                  }
              })
              .catch(error => {
                  console.error('Error sending message:', error);
                  alert('Error sending message: Network or server error');
              });
        });

        setInterval(fetchMessages, 3000);
        setInterval(updateUnreadCounts, 5000);
    </script>
</body>
</html>