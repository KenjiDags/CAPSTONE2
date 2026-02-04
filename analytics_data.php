<?php
require 'config.php';
require 'auth.php';

header('Content-Type: application/json');

// If an item_id is provided, return that item's history (stock card)
if (!empty($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(changed_at, '%Y-%m-%d') AS date, 
               quantity_on_hand AS qty,
               quantity_change,
               change_direction,
               change_type
        FROM item_history
        WHERE item_id = ? AND changed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ORDER BY changed_at ASC
    ");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $labels = [];
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $labels[] = $r['date'];
        $data[] = (int)$r['qty'];
    }
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

$response = [];

// --- Supply list: top 50 items by current quantity ---
$supplySql = "
    SELECT item_id, stock_number, item_name,
           quantity_on_hand AS quantity
    FROM items
    ORDER BY quantity_on_hand DESC
    LIMIT 50
";
$res = $conn->query($supplySql);
$supply = [];
while ($row = $res->fetch_assoc()) {
    $supply[] = [
        'item_id' => (int)$row['item_id'],
        'stock_number' => $row['stock_number'],
        'item_name' => $row['item_name'],
        'quantity' => (int)$row['quantity']
    ];
}
$response['supply_list'] = $supply;

// --- Low stock: items at or below reorder point ---
$lowSql = "
    SELECT item_id, stock_number, item_name,
           quantity_on_hand AS quantity,
           reorder_point
    FROM items
    WHERE quantity_on_hand <= reorder_point
    ORDER BY item_name ASC
    LIMIT 50
";

$low = [];
$res = $conn->query($lowSql);
while ($row = $res->fetch_assoc()) {
    $low[] = [
        'item_id' => (int)$row['item_id'],
        'stock_number' => $row['stock_number'],
        'item_name' => $row['item_name'],
        'quantity' => (int)$row['quantity'],
        'reorder_point' => (int)$row['reorder_point']
    ];
}

$response['low_stock'] = $low;

echo json_encode($response);
?>