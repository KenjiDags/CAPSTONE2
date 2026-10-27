<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Invalid PPE IIRUP ID.');
}

$iirup_id = (int) $_GET['id'];

$header_stmt = $conn->prepare('SELECT * FROM ppe_iirup WHERE id = ? LIMIT 1');
$header_stmt->bind_param('i', $iirup_id);
$header_stmt->execute();
$header_result = $header_stmt->get_result();
$iirup = $header_result->fetch_assoc();
$header_stmt->close();

if (!$iirup) {
    http_response_code(404);
    die('PPE IIRUP record not found.');
}

$items_stmt = $conn->prepare('SELECT * FROM ppe_iirup_items WHERE ppe_iirup_id = ? ORDER BY id ASC');
$items_stmt->bind_param('i', $iirup_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$items_stmt->close();

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return 'PHP ' . number_format((float) ($value ?? 0), 2);
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
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PPE IIRUP</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="detail-view-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <h2><i class="fas fa-file-alt" aria-hidden="true"></i> View PPE IIRUP #<?= (int) $iirup['id'] ?></h2>

    <div class="ris-actions">
        <a href="PPE/PPE_iirup.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="edit_ppe_iirup.php?id=<?= (int) $iirup['id'] ?>" class="btn btn-success"><i class="fas fa-edit"></i> Edit IIRUP</a>
        <a href="PPE/PPE_iirup_export.php?id=<?= (int) $iirup['id'] ?>" class="btn btn-success"><i class="fas fa-download"></i> Export</a>
    </div>

    <h3><i class="fas fa-circle-info"></i> Report Header</h3>
    <div class="ris-details">
            <p><strong>Date Reported:</strong> <span><?= date_or_na($iirup['date_reported'] ?? null) ?></span></p>
            <p><strong>Entity Name:</strong> <span><?= h($iirup['entity_name'] ?? 'TESDA Regional Office') ?></span></p>
            <p><strong>Accountable Officer:</strong> <span><?= h($iirup['accountable_officer_name'] ?? '') ?></span></p>
            <p><strong>Accountable Officer Designation:</strong> <span><?= h($iirup['accountable_officer_designation'] ?? '') ?></span></p>
            <p><strong>Accountable Officer Station:</strong> <span><?= h($iirup['accountable_officer_station'] ?? '') ?></span></p>
            <p><strong>PPE No.:</strong> <span><?= h($iirup['PPE_no'] ?? '') ?></span></p>
            <p><strong>Particulars:</strong> <span><?= h($iirup['particulars'] ?? '') ?></span></p>
            <p><strong>Quantity:</strong> <span><?= h($iirup['quantity'] ?? 0) ?></span></p>
            <p><strong>Carrying Amount:</strong> <span class="currency"><?= money($iirup['carrying_amount'] ?? 0) ?></span></p>
            <p><strong>Total Cost:</strong> <span class="currency"><?= money($iirup['total_cost'] ?? 0) ?></span></p>
            <p><strong>Amount:</strong> <span class="currency"><?= money($iirup['amount'] ?? 0) ?></span></p>
            <p><strong>Remarks:</strong> <span><?= h($iirup['remarks'] ?? 'N/A') ?></span></p>
    </div>

    <h3><i class="fas fa-users"></i> Signatories</h3>
    <div class="ris-details">
            <p><strong>Requested By:</strong> <span><?= h($iirup['requested_by_name'] ?? '') ?></span></p>
            <p><strong>Requested By Designation:</strong> <span><?= h($iirup['requested_by_designation'] ?? '') ?></span></p>
            <p><strong>Requested By Station:</strong> <span><?= h($iirup['requested_by_station'] ?? '') ?></span></p>
            <p><strong>Approved By:</strong> <span><?= h($iirup['approved_by_name'] ?? '') ?></span></p>
            <p><strong>Approved By Designation:</strong> <span><?= h($iirup['approved_by_designation'] ?? '') ?></span></p>
            <p><strong>Inspection Officer:</strong> <span><?= h($iirup['inspection_officer_name'] ?? '') ?></span></p>
            <p><strong>Inspection Officer Designation:</strong> <span><?= h($iirup['inspection_officer_designation'] ?? '') ?></span></p>
            <p><strong>Witness:</strong> <span><?= h($iirup['witness_name'] ?? '') ?></span></p>
            <p><strong>Witness Designation:</strong> <span><?= h($iirup['witness_designation'] ?? '') ?></span></p>
    </div>

    <h3><i class="fas fa-list"></i> IIRUP Items</h3>
    <div class="table-container table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date Acquired</th>
                        <th>Particulars</th>
                        <th>Qty</th>
                        <th>Depreciation</th>
                        <th>Impairment Loss</th>
                        <th>Carrying Amount</th>
                        <th>Sale</th>
                        <th>Transfer</th>
                        <th>Destruction</th>
                        <th>Other</th>
                        <th>Total</th>
                        <th>Appraised Value</th>
                        <th>OR No.</th>
                        <th>Amount</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= date_or_na($item['date_acquired'] ?? null) ?></td>
                                <td><?= h($item['particulars'] ?? '') ?></td>
                                <td><?= h($item['quantity'] ?? 0) ?></td>
                                <td class="currency"><?= money($item['depreciation'] ?? 0) ?></td>
                                <td class="currency"><?= money($item['impairment_loss'] ?? 0) ?></td>
                                <td class="currency"><?= money($item['carrying_amount'] ?? 0) ?></td>
                                <td><?= h($item['sale'] ?? 0) ?></td>
                                <td><?= h($item['transfer'] ?? 0) ?></td>
                                <td><?= h($item['destruction'] ?? 0) ?></td>
                                <td><?= h($item['other'] ?? 0) ?><?php if (!empty($item['other_specification'])): ?><br><?= h($item['other_specification']) ?><?php endif; ?></td>
                                <td><?= h($item['total'] ?? 0) ?></td>
                                <td class="currency"><?= money($item['appraised_value'] ?? 0) ?></td>
                                <td><?= h($item['or_no'] ?? '') ?></td>
                                <td class="currency"><?= money($item['sale_amount'] ?? $item['amount'] ?? 0) ?></td>
                                <td><?= h($item['remarks'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="15" style="text-align:center;">No line items found for this record.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
</div>

</body>
</html>
