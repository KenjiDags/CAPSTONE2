<?php
// get_ict_entry.php - For fetching single entry data for editing
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

require_once 'config.php';

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM ict_registry WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Entry not found']);
}

$stmt->close();
$conn->close();
?>