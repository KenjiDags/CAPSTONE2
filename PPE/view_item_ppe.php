<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/navigation.php';
$ppeReturnUrl = ppeReturnTarget($_GET['return'] ?? null);
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Invalid PPE item ID.');
}

$item_id = (int) $_GET['id'];

$stmt = $conn->prepare('SELECT * FROM ppe_property WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    http_response_code(404);
    die('PPE item not found.');
}

$historyStatement = $conn->prepare('SELECT * FROM item_history_ppe WHERE PPE_no = ? ORDER BY changed_at ASC, id ASC');
$historyStatement->bind_param('s', $item['PPE_no']);
$historyStatement->execute();
$historyRows = $historyStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStatement->close();
function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return 'PHP ' . number_format((float) $value, 2);
}

function date_or_na($value)
{
    if (empty($value)) {
        return 'N/A';
    }

    $ts = strtotime((string) $value);
    if ($ts === false) {
        return h($value);
    }

    return date('M d, Y', $ts);
}
?>
<!DOCTYPE html>
<html data-item-detail-styles data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PPE Item</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="item-detail-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <h2>View PPE Item: <?= h($item['item_name']) ?></h2>

    <div class="view-actions">
        <a href="<?= htmlspecialchars($ppeReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" onclick="if (!event.ctrlKey &amp;&amp; !event.metaKey &amp;&amp; !event.shiftKey &amp;&amp; !event.altKey &amp;&amp; window.history.length > 1) { event.preventDefault(); window.history.back(); }"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="PPE/edit_ppe.php?id=<?= (int) $item['id'] ?>&amp;return=<?= urlencode($ppeReturnUrl) ?>" class="btn btn-success"><i class="fas fa-edit"></i> Edit Item</a>
    </div>

    <div class="view-card">
<h3><i class="fas fa-info-circle" aria-hidden="true"></i> Item Details</h3>
<div class="item-grid">
        <?php foreach (['PPE_no' => 'PPE No.', 'par_no' => 'PAR No.', 'item_name' => 'Item Name', 'item_description' => 'Description', 'quantity' => 'Quantity', 'unit' => 'Unit'] as $field => $label): ?>
        <div class="field"><span class="label"><?= $label ?></span><span class="value"><?= h($item[$field] ?? '') ?></span></div>
        <?php endforeach; ?>
        <div class="field"><span class="label">Date Acquired</span><span class="value"><?= date_or_na($item['date_acquired'] ?? null) ?></span></div>
    </div></div>

    <div class="view-card">
<h3><i class="fas fa-user-check" aria-hidden="true"></i> Assignment and Condition</h3>
<div class="item-grid">
        <?php foreach (['custodian' => 'Custodian', 'officer_incharge' => 'Officer In Charge', 'status' => 'Status', 'condition' => 'Condition'] as $field => $label): ?>
        <div class="field"><span class="label"><?= $label ?></span><span class="value"><?= h($item[$field] ?? '') ?></span></div>
        <?php endforeach; ?>
    </div></div>

    <div class="view-card">
<h3><i class="fas fa-coins" aria-hidden="true"></i> Value and Remarks</h3>
<div class="item-grid">
        <div class="field"><span class="label">Amount</span><span class="value currency"><?= money($item['amount'] ?? 0) ?></span></div>
        <div class="field"><span class="label">Remarks</span><span class="value"><?= nl2br(h($item['remarks'] ?? 'N/A')) ?></span></div>
    </div></div>
    <div class="view-card"><h3><i class="fas fa-history" aria-hidden="true"></i> Item History</h3>
    <p class="mini-note">Saved additions, issuances, and transfers for this PPE number, from oldest to newest.</p>
    <div class="table-wrap">
        <table class="compact-table">
            <thead><tr><th>Date</th><th>Transaction</th><th>Reference</th><th>Item / Description</th><th>Receipt Qty.</th><th>Issue Qty.</th><th>Balance Qty.</th><th>Officer in Charge</th><th>Unit Cost</th></tr></thead>
            <tbody>
                <?php if (!$historyRows): ?>
                <tr><td colspan="9" style="text-align:center;">No saved history for this item.</td></tr>
                <?php else: ?>
                <?php foreach ($historyRows as $historyRow): ?>
                <tr>
                    <td><?= h($historyRow['changed_at'] ?? '') ?></td>
                    <td><?= h($historyRow['change_type'] ?? '') ?></td>
                    <td><?= h(($historyRow['PAR_number'] ?? '') ?: (($historyRow['refference_no'] ?? '') ?: ($historyRow['PPE_no'] ?? ''))) ?></td>
                    <td><?= h($historyRow['item_name'] ?? '') ?><br><?= h($historyRow['description'] ?? '') ?></td>
                    <td><?= h($historyRow['receipt_qty'] ?? '') ?></td>
                    <td><?= h($historyRow['issue_qty'] ?? '') ?></td>
                    <td><?= h($historyRow['balance_qty'] ?? '') ?></td>
                    <td><?= h($historyRow['officer_incharge'] ?? '') ?></td>
                    <td class="currency"><?= money($historyRow['unit_cost'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

</body>
</html>
