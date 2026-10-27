<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';

if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    http_response_code(400);
    die('Invalid item ID.');
}

$item_id = (int) $_GET['item_id'];

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return 'PHP ' . number_format((float) $value, 2);
}

function dt($value)
{
    if (empty($value)) {
        return 'N/A';
    }

    $ts = strtotime((string) $value);
    if ($ts === false) {
        return h($value);
    }

    return date('M d, Y h:i A', $ts);
}

$item_sql = "
    SELECT
        i.*,
        COALESCE(i.calculated_quantity, i.quantity_on_hand) AS display_quantity,
        (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) AS entry_count,
        COALESCE((SELECT SUM(ie.quantity) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0) AS entry_quantity_total,
        (
            COALESCE(i.calculated_quantity, i.quantity_on_hand) *
            CASE
                WHEN i.calculated_unit_cost IS NOT NULL THEN i.calculated_unit_cost
                WHEN (i.initial_quantity > 0 AND (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) > 0)
                    THEN ((i.initial_quantity * i.unit_cost) + COALESCE((SELECT SUM(ie.quantity * ie.unit_cost) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0))
                         / NULLIF((i.initial_quantity + COALESCE((SELECT SUM(ie.quantity) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0)), 0)
                ELSE i.unit_cost
            END
        ) AS total_cost
    FROM items i
    WHERE i.item_id = ?
    LIMIT 1
";

$item_stmt = $conn->prepare($item_sql);
$item_stmt->bind_param('i', $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$item = $item_result->fetch_assoc();
$item_stmt->close();

if (!$item) {
    http_response_code(404);
    die('Item not found.');
}

$entries_stmt = $conn->prepare("SELECT entry_id, quantity, created_at FROM inventory_entries WHERE item_id = ? ORDER BY created_at DESC, entry_id DESC");
$entries_stmt->bind_param('i', $item_id);
$entries_stmt->execute();
$entries_result = $entries_stmt->get_result();
$entries = [];
while ($row = $entries_result->fetch_assoc()) {
    $entries[] = $row;
}
$entries_stmt->close();

$history_stmt = $conn->prepare("SELECT item_name, description, unit, reorder_point, quantity_on_hand, quantity_change, change_direction, change_type, ris_id, changed_at FROM item_history WHERE item_id = ? ORDER BY changed_at DESC, history_id DESC");
$history_stmt->bind_param('i', $item_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history_rows = [];
while ($row = $history_result->fetch_assoc()) {
    $history_rows[] = $row;
}
$history_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html data-item-detail-styles data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Item - TESDA Inventory Management System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="item-detail-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <h2>View Item: <?= h($item['item_name']) ?></h2>

    <div class="view-actions">
        <a href="office_supplies/inventory.php" class="btn btn-secondary" onclick="if (!event.ctrlKey &amp;&amp; !event.metaKey &amp;&amp; !event.shiftKey &amp;&amp; !event.altKey &amp;&amp; window.history.length > 1) { event.preventDefault(); window.history.back(); }"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="office_supplies/add_multiple_items.php" class="btn btn-success"><i class="fas fa-box"></i> Restock Items</a>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-circle-info"></i> Item Overview</h3>
        <div class="item-grid">
            <div class="field"><span class="label">Stock Number</span><span class="value"><?= h($item['stock_number']) ?></span></div>
            <div class="field"><span class="label">IAR</span><span class="value"><?= h($item['iar']) ?></span></div>
            <div class="field"><span class="label">Item Name</span><span class="value"><?= h($item['item_name']) ?></span></div>
            <div class="field"><span class="label">Description</span><span class="value"><?= h($item['description']) ?></span></div>
            <div class="field"><span class="label">Unit</span><span class="value"><?= h($item['unit']) ?></span></div>
            <div class="field"><span class="label">Reorder Point</span><span class="value"><?= h($item['reorder_point']) ?></span></div>
            <div class="field"><span class="label">Quantity on Hand</span><span class="value"><?= h($item['quantity_on_hand']) ?></span></div>
            <div class="field"><span class="label">Average Unit Cost</span><span class="value"><?= money($item['average_unit_cost']) ?></span></div>
            <div class="field"><span class="label">Total Cost</span><span class="value"><?= money($item['total_cost']) ?></span></div>
            <div class="field"><span class="label">Created At</span><span class="value"><?= dt($item['created_at'] ?? null) ?></span></div>
        </div>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-list"></i> Entries</h3>
        <div class="table-wrap">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Quantity</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ((int) ($item['initial_quantity'] ?? 0) > 0): ?>
                        <tr>
                            <td>Initial Quantity</td>
                            <td><?= h($item['initial_quantity']) ?></td>
                            <td><?= dt($item['created_at'] ?? null) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (count($entries) > 0): ?>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td>Entry #<?= h($entry['entry_id']) ?></td>
                                <td><?= h($entry['quantity']) ?></td>
                                <td><?= dt($entry['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif ((int) ($item['initial_quantity'] ?? 0) <= 0): ?>
                        <tr>
                            <td colspan="3" style="text-align:center;" class="mini-note">No entries found for this item.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-clock-rotate-left"></i> Item History</h3>
        <div class="table-wrap">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Changed At</th>
                        <th>Type</th>
                        <th>Direction</th>
                        <th>Qty Change</th>
                        <th>Qty on Hand</th>
                        <th>Reorder Point</th>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history_rows) > 0): ?>
                        <?php foreach ($history_rows as $history): ?>
                            <tr>
                                <td><?= dt($history['changed_at']) ?></td>
                                <td><?= h($history['change_type']) ?></td>
                                <td><?= h($history['change_direction']) ?></td>
                                <td><?= h($history['quantity_change']) ?></td>
                                <td><?= h($history['quantity_on_hand']) ?></td>
                                <td><?= h($history['reorder_point']) ?></td>
                                <td><?= h($history['item_name']) ?></td>
                                <td><?= h($history['description']) ?></td>
                                <td><?= h($history['unit']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align:center;" class="mini-note">No history records found for this item.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
