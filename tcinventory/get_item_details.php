<?php
// get_item_details.php - API endpoint to fetch item details
header('Content-Type: application/json');
require_once 'config.php'; // Your database config

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM semi_expendable_property WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        // Format date for display
        $item['date'] = date('F j, Y', strtotime($item['date']));
        
        echo json_encode([
            'success' => true,
            'item' => $item
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Item not found'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>