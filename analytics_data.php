<?php
require 'config.php';
require 'auth.php';

header('Content-Type: application/json');

// Get category parameter (default to office-supplies)
$category = $_GET['category'] ?? 'office-supplies';

// If an item_id is provided, return that item's history (stock card)
if (!empty($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    
    if ($category === 'semi-expendables') {
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(changed_at, '%Y-%m-%d') AS date, 
                   quantity_balance AS qty
            FROM semi_expendable_history
            WHERE item_id = ? AND changed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ORDER BY changed_at ASC
        ");
    } elseif ($category === 'ppe') {
        // For PPE, we'll track by id changes (you may need to create a history table for PPE)
        // For now, returning empty data if no history exists
        echo json_encode(['labels' => [], 'data' => []]);
        exit;
    } else {
        // Office supplies (items table)
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
    }
    
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

// --- For Semi Expendables and PPE, return full item list ---
if ($category === 'semi-expendables') {
    $itemsSql = "
        SELECT id,
               semi_expendable_property_no,
               item_description,
               office_officer_issued,
               quantity_balance,
               CASE 
                   WHEN quantity_balance > 0 THEN 'Active'
                   ELSE 'Depleted'
               END as status
        FROM semi_expendable_property
        ORDER BY item_description ASC
    ";
    
    $items = [];
    $res = $conn->query($itemsSql);
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'property_no' => $row['semi_expendable_property_no'],
            'item_name' => $row['item_description'],
            'status' => $row['status'],
            'officer' => $row['office_officer_issued'] ?: 'Unassigned',
            'quantity' => (int)$row['quantity_balance']
        ];
    }
    $response['items'] = $items;
    
} elseif ($category === 'ppe') {
    $itemsSql = "
        SELECT id,
               par_no,
               item_name,
               custodian,
               officer_incharge,
               status,
               quantity,
               `condition`
        FROM ppe_property
        ORDER BY item_name ASC
    ";
    
    $items = [];
    $res = $conn->query($itemsSql);
    while ($row = $res->fetch_assoc()) {
        $officer = $row['officer_incharge'] ?: $row['custodian'];
        $items[] = [
            'property_no' => $row['par_no'],
            'item_name' => $row['item_name'],
            'condition' => $row['condition'],
            'status' => $row['status'],
            'officer' => $officer ?: 'Unassigned',
            'quantity' => (int)$row['quantity']
        ];
    }
    $response['items'] = $items;
    
} else {
    // Office supplies - keep the existing chart data
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
}

echo json_encode($response);
?>