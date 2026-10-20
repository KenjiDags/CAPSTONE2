<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
// get_item_details.php - API endpoint to fetch item details
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php'; // Your database config

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}



try {
    $stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
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
} catch (Throwable $e) {
    error_log('Semi-expendable item details failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load item details. Please try again.'
    ]);
}
?>
