<?php
require_once './includes/config.php';

$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

if ($hospital_id <= 0 || $section_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid hospital ID or section ID']);
    exit();
}

$response = [
    'sub_sections' => [],
    'section_ids' => [$section_id]
];

// Fetch sub-sections
$stmt = $conn->prepare("SELECT id, name FROM sections WHERE parent_section_id = ?");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $response['sub_sections'][] = ['id' => $row['id'], 'name' => $row['name']];
    $response['section_ids'][] = $row['id'];
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode($response);
?>