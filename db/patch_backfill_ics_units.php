<?php
// Backfill ICS items' unit from semi_expendable_property.unit
// Safe to run multiple times. Outputs JSON.

header('Content-Type: application/json');

$result = [
    'success' => false,
    'updated' => 0,
    'messages' => [],
];

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../functions.php';

    if (!$conn) { throw new Exception('No DB connection'); }

    // Ensure ics_items has a unit column (idempotent)
    try {
        if (function_exists('columnExists') && !columnExists($conn, 'ics_items', 'unit')) {
            @$conn->query("ALTER TABLE ics_items ADD COLUMN unit VARCHAR(64) NULL AFTER quantity");
            $result['messages'][] = 'Added ics_items.unit column';
        }
    } catch (Throwable $e) {
        $result['messages'][] = 'unit ensure check failed (non-fatal): ' . $e->getMessage();
    }

    // First pass: join on inventory_item_no
    $sql1 = "UPDATE ics_items ii
             JOIN semi_expendable_property sp ON ii.inventory_item_no = sp.semi_expendable_property_no
             SET ii.unit = sp.unit
             WHERE (ii.unit IS NULL OR ii.unit = '') AND sp.unit IS NOT NULL AND sp.unit <> ''";
    $conn->query($sql1);
    $updated1 = $conn->affected_rows;

    // Second pass: join on stock_number (some rows may only have stock_number)
    $sql2 = "UPDATE ics_items ii
             JOIN semi_expendable_property sp ON ii.stock_number = sp.semi_expendable_property_no
             SET ii.unit = sp.unit
             WHERE (ii.unit IS NULL OR ii.unit = '') AND sp.unit IS NOT NULL AND sp.unit <> ''";
    $conn->query($sql2);
    $updated2 = $conn->affected_rows;

    $result['success'] = true;
    $result['updated'] = max(0, (int)$updated1) + max(0, (int)$updated2);
    $result['messages'][] = 'inventory_item_no matched: ' . (int)$updated1;
    $result['messages'][] = 'stock_number matched: ' . (int)$updated2;
} catch (Throwable $e) {
    $result['success'] = false;
    $result['messages'][] = 'Error: ' . $e->getMessage();
}

echo json_encode($result);
