<?php
header('Content-Type: application/json; charset=utf-8');
// Cache the shared count on the server, never an authenticated response in a proxy.
header('Cache-Control: private, no-store');
session_start();
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || empty($_SESSION['user_id'])
    || (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'GET required']);
    exit;
}
if (($_GET['category'] ?? 'office-supplies') !== 'office-supplies') {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported category']);
    exit;
}
try {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../inventory_count_cache.php';
    $count = cachedInventoryCount(static function () use ($conn): int {
        // This project stores office supplies in items, and PPE/semi-expendables separately.
        $result = $conn->query('SELECT COUNT(*) AS total FROM items WHERE quantity_on_hand = 0');
        if (!$result) throw new RuntimeException('Inventory count query failed');
        return (int)$result->fetch_assoc()['total'];
    });
    echo json_encode(['category' => 'office-supplies', 'count' => $count]);
} catch (Throwable $error) {
    error_log('Inventory count endpoint: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Inventory count temporarily unavailable']);
}
