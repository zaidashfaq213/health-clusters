function openEditUser(id, username, email, role, hospital_id) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-role').value = role;
    document.getElementById('edit-hospital_id').value = hospital_id || '';
    document.getElementById('edit-user-modal').style.display = 'flex';
}

function openEditHospital(id, name, leader) {
    document.getElementById('edit-hospital-id').value = id;
    document.getElementById('edit-hospital-name').value = name;
    document.getElementById('edit-leader').value = leader;
    document.getElementById('edit-hospital-modal').style.display = 'flex';
}

function openEditTopic(id, title, content, status, hospital_id) {
    document.getElementById('edit-topic-id').value = id;
    document.getElementById('edit-title').value = title;
    document.getElementById('edit-content').value = content;
    document.getElementById('edit-status').value = status;
    document.getElementById('edit-hospital_id_topic').value = hospital_id;
    document.getElementById('edit-topic-modal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        button.classList.add('active');
        document.getElementById(button.dataset.tab).classList.add('active');
    });
});