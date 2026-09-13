<?php
require 'config.php';
require 'auth.php';

header('Content-Type: application/json');

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

    $criticalSql = "
        SELECT i.item_id, i.stock_number, i.item_name, i.description,
               d.depleted_at,
               COALESCE(d.depleted_at, li.last_issued_at, ie.created_at) AS fallback_at
        FROM items i
        LEFT JOIN (
            SELECT h.item_id, MAX(h.changed_at) AS depleted_at
            FROM item_history h
            WHERE h.quantity_on_hand = 0
              AND NOT EXISTS (
                  SELECT 1 FROM item_history newer
                  WHERE newer.item_id = h.item_id
                    AND newer.changed_at > h.changed_at
                    AND newer.quantity_on_hand > 0
              )
            GROUP BY h.item_id
        ) d ON d.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, MAX(changed_at) AS last_issued_at
            FROM item_history
            WHERE quantity_change < 0 OR change_direction = 'decrease'
            GROUP BY item_id
        ) li ON li.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, MAX(created_at) AS created_at
            FROM inventory_entries
            GROUP BY item_id
        ) ie ON ie.item_id = i.item_id
        WHERE i.quantity_on_hand = 0
        ORDER BY COALESCE(d.depleted_at, li.last_issued_at, ie.created_at) ASC, i.item_name ASC
    ";
    $criticalResult = $conn->query($criticalSql);
    $criticalItems = [];
    $today = new DateTimeImmutable('today');
    while ($row = $criticalResult->fetch_assoc()) {
        $depletedAt = trim((string)($row['depleted_at'] ?? ''));
        $fallbackAt = trim((string)($row['fallback_at'] ?? ''));
        $dateSource = 'unrecorded';
        $stockoutDate = $depletedAt !== '' ? $depletedAt : $fallbackAt;
        $daysEmpty = 1;
        if ($stockoutDate !== '') {
            try {
                $depletedDate = (new DateTimeImmutable($stockoutDate))->setTime(0, 0, 0);
                // Use whole calendar days and keep today's/missing dates visible.
                $daysEmpty = max(1, (int)floor(($today->getTimestamp() - $depletedDate->getTimestamp()) / 86400));
                $dateSource = $depletedAt !== '' ? 'stockout' : 'fallback';
            } catch (Exception $exception) {
                error_log('Invalid stock date for item ' . (int)$row['item_id'] . ': ' . $stockoutDate);
                $stockoutDate = '';
            }
        }
        $criticalItems[] = [
            'item_id' => (int)$row['item_id'],
            'stock_number' => $row['stock_number'],
            'item_name' => $row['item_name'],
            'description' => $row['description'],
            'depleted_at' => $stockoutDate !== '' ? substr($stockoutDate, 0, 10) : null,
            'date_stock_reached_zero' => $stockoutDate !== '' ? substr($stockoutDate, 0, 10) : null,
            'date_source' => $dateSource,
            'days_empty' => $daysEmpty
        ];
    }
    $response['critical_depletion'] = $criticalItems;

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