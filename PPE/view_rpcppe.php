<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/rpcppe_helpers.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = $id && $id > 0 ? rpcppeLoadReport($conn, $id) : null;
if (!$report) { http_response_code(404); exit('RPCPPE report not found.'); }
function rpcppeH($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <base href="<?= rpcppeH(APP_ROOT_URL) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPCPPE-<?= (int)$id ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="detail-view-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<main class="container">
    <h2>RPCPPE-<?= (int)$id ?></h2>
    <div class="view-actions">
        <a href="PPE/RPCPPE.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Reports</a>
        <a href="PPE/export_rpcppe.php?id=<?= (int)$id ?>" class="btn btn-success"><i class="fas fa-download"></i> Export</a>
    </div>
    <div class="ris-details">
        <?php foreach (['report_date'=>'Report Date','entity_name'=>'Entity Name','fund_cluster'=>'Fund Cluster','accountable_officer'=>'Accountable Officer','official_designation'=>'Designation','assumption_date'=>'Assumption Date','certified_by'=>'Certified Correct By','approved_by'=>'Approved By','verified_by'=>'Verified By'] as $key=>$label): ?>
        <p><strong><?= $label ?>:</strong> <span><?= rpcppeH($report[$key] ?? '') ?></span></p>
        <?php endforeach; ?>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Article</th><th>Description</th><th>Property Number</th><th>Unit</th><th>Unit Value</th><th>Balance Per Card</th><th>On Hand Per Count</th><th>Shortage/Overage Qty</th><th>Shortage/Overage Value</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($report['items'] as $item): ?>
                <tr>
                    <td><?= rpcppeH($item['article']) ?></td><td><?= rpcppeH($item['item_name'] . ' - ' . $item['item_description']) ?></td>
                    <td><?= rpcppeH($item['property_number']) ?></td><td><?= rpcppeH($item['unit']) ?></td>
                    <td class="currency">₱<?= number_format((float)$item['unit_value'], 2) ?></td>
                    <td><?= rpcppeH($item['qty_property_card']) ?></td><td><?= rpcppeH($item['qty_physical_count']) ?></td><td><?= rpcppeH($item['shortage_qty']) ?></td>
                    <td class="currency"><?= $item['shortage_value'] === '' ? '' : '₱' . number_format((float)$item['shortage_value'], 2) ?></td><td><?= rpcppeH($item['remarks']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$report['items']): ?><tr><td colspan="10">No saved items in this report.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>