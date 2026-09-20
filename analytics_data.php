<?php
require 'config.php';
require 'auth.php';
require_once 'mrp_dates.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Get category parameter (default to office-supplies)
$category = $_GET['category'] ?? 'office-supplies';
$horizon = $_GET['horizon'] ?? '12m';
$historyInterval = $horizon === '24m' ? 24 : 12;

// If an item_id is provided, return that item's history (stock card)
if (!empty($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    
    if ($category === 'semi-expendables') {
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(changed_at, '%Y-%m-%d') AS date, 
                   quantity_balance AS qty
            FROM semi_expendable_history
            WHERE semi_id = ? AND changed_at >= DATE_SUB(CURDATE(), INTERVAL $historyInterval MONTH)
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
            WHERE item_id = ? AND changed_at >= DATE_SUB(CURDATE(), INTERVAL $historyInterval MONTH)
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
               amount_total,
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
            'item_id' => (int)$row['id'],
            'property_no' => $row['semi_expendable_property_no'],
            'item_name' => $row['item_description'],
            'description' => '',
            'status' => $row['status'],
            'officer' => $row['office_officer_issued'] ?: 'Unassigned',
            'quantity' => (int)$row['quantity_balance'],
            'capital_value' => (float)$row['amount_total']
        ];
    }
    $response['items'] = $items;
    $summaryResult = $conn->query("SELECT COALESCE(SUM(quantity + quantity_disposed), 0) AS total_items, COALESCE(SUM(quantity - quantity_issued - quantity_reissued), 0) AS not_issued, COALESCE(SUM(quantity_issued + quantity_reissued), 0) AS currently_issued, COALESCE(SUM(quantity_disposed), 0) AS disposed FROM semi_expendable_property");
    $summary = $summaryResult->fetch_assoc();
    $response['summary'] = [
        'total' => max(0, (int)($summary['total_items'] ?? 0)),
        'not_issued' => max(0, (int)($summary['not_issued'] ?? 0)),
        'currently_issued' => max(0, (int)($summary['currently_issued'] ?? 0)),
        'disposed' => max(0, (int)($summary['disposed'] ?? 0))
    ];
    
} elseif ($category === 'ppe') {
    $itemsSql = "
        SELECT id,
               PPE_no AS par_no,
               item_name,
               item_description,
               custodian,
               officer_incharge,
               status,
               quantity,
               amount,
               remarks,
               `condition`
        FROM ppe_property
        ORDER BY item_name ASC
    ";
    
    $items = [];
    $res = $conn->query($itemsSql);
    while ($row = $res->fetch_assoc()) {
        $officer = $row['officer_incharge'] ?: $row['custodian'];
        $items[] = [
            'item_id' => (int)$row['id'],
            'property_no' => $row['par_no'],
            'item_name' => $row['item_name'],
            'description' => $row['item_description'],
            'condition' => $row['condition'],
            'remarks' => $row['remarks'],
            'status' => $row['status'],
            'officer' => $officer ?: 'Unassigned',
            'quantity' => (int)$row['quantity'],
            'capital_value' => (float)$row['amount']
        ];
    }
    $response['items'] = $items;
    $summaryResult = $conn->query("SELECT COUNT(*) AS total, SUM(LOWER(`condition`) IN ('good', 'fair', 'serviceable')) AS serviceable, SUM(LOWER(`condition`) NOT IN ('good', 'fair', 'serviceable')) AS unserviceable FROM ppe_property");
    $summary = $summaryResult->fetch_assoc();
    $response['summary'] = [
        'total' => (int)($summary['total'] ?? 0),
        'serviceable' => (int)($summary['serviceable'] ?? 0),
        'unserviceable' => (int)($summary['unserviceable'] ?? 0)
    ];
    
} else {
    // Office supplies - keep the existing chart data
    // --- Supply list: top 50 items by current quantity ---
    $supplySql = "
         SELECT item_id, stock_number, item_name, description,
             quantity_on_hand AS quantity,
             reorder_point,
             unit_cost,
             COALESCE((SELECT SUM(ABS(quantity_change)) FROM item_history h
                    WHERE h.item_id = items.item_id
                    AND h.changed_at >= DATE_SUB(CURDATE(), INTERVAL $historyInterval MONTH)), 0) AS usage_volume
        FROM items
        ORDER BY quantity_on_hand DESC
    ";
    $res = $conn->query($supplySql);
    $supply = [];
    while ($row = $res->fetch_assoc()) {
        $supply[] = [
            'item_id' => (int)$row['item_id'],
            'stock_number' => $row['stock_number'],
            'item_name' => $row['item_name'],
            'description' => $row['description'],
            'quantity' => (int)$row['quantity'],
            'reorder_point' => (int)$row['reorder_point'],
            'usage_volume' => (int)$row['usage_volume'],
            'capital_value' => (float)$row['quantity'] * (float)$row['unit_cost']
        ];
    }
    $response['supply_list'] = $supply;

    // Durable episode dates survive stock-card archiving and dashboard refreshes.
    $criticalSql = "
        SELECT i.item_id, s.started_at AS depleted_at, s.date_source
        FROM items i
        LEFT JOIN item_stockouts s ON s.item_id = i.item_id
        WHERE i.quantity_on_hand = 0
    ";
    $criticalResult = $conn->query($criticalSql);
    $stockoutDates = [];
    $stockoutSources = [];
    while ($row = $criticalResult->fetch_assoc()) {
        $date = mrpDate($row['depleted_at']);
        $stockoutDates[(int)$row['item_id']] = $date ? $date->format('Y-m-d') : null;
        $stockoutSources[(int)$row['item_id']] = $row['date_source'];
    }
    $criticalItems = [];
    foreach ($supply as &$item) {
        $quantity = $item['quantity'];
        $safetyStock = max(0, $item['reorder_point']);
        $stockoutDate = $quantity === 0 ? ($stockoutDates[$item['item_id']] ?? null) : null;
        $item += [
            'id' => $item['item_id'], 'sku' => $item['stock_number'],
            'itemName' => $item['item_name'], 'onHandQty' => $quantity,
            'safetyStock' => $safetyStock,
            'status' => $quantity === 0 ? 'critical' : ($quantity > $safetyStock ? 'safe' : ($quantity > 0 ? 'low' : 'invalid')),
            'stockoutDate' => $stockoutDate,
            'stockoutDateSource' => $quantity === 0 ? ($stockoutSources[$item['item_id']] ?? null) : null,
            // Supplier and PO records are not present in the current schema.
            'leadTimeDays' => null, 'poExpectedDeliveryDate' => null,
            'expectedResolutionDate' => mrpExpectedResolution($stockoutDate, null),
            'netRequirement' => max(0, $safetyStock - $quantity),
            'netRequirementBasis' => 'Safety stock deficit; demand and open POs are not recorded',
            'daysOutOfStock' => $quantity === 0 ? mrpDaysOutOfStock($stockoutDate) : 0,
        ];
        if ($quantity === 0) {
            $criticalItems[] = $item + [
                'depleted_at' => $stockoutDate, 'date_stock_reached_zero' => $stockoutDate,
                'date_source' => $item['stockoutDateSource'] ?? 'unrecorded',
                'days_empty' => $item['daysOutOfStock'],
            ];
        }
    }
    unset($item);
    $response['supply_list'] = $supply;
    $response['critical_depletion'] = $criticalItems;
    $response['businessDate'] = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    // --- Low stock: items at or below reorder point ---
    $lowSql = "
        SELECT item_id, stock_number, item_name,
               quantity_on_hand AS quantity,
               reorder_point
        FROM items
        WHERE quantity_on_hand > 0 AND quantity_on_hand <= reorder_point
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
