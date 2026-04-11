<?php
require 'auth.php';
require 'config.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PPE IIRUP</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .container h2::before {
            content: "\f03a";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }

        .view-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .view-card {
            background: #fff;
            border: 1px solid #dbe2ef;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        .view-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
            color: #0f172a;
            font-size: 1.05rem;
        }

        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px 16px;
        }

        .item-grid .field {
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 8px;
        }

        .item-grid .label {
            display: block;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .item-grid .value {
            color: #0f172a;
            font-weight: 600;
            word-break: break-word;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table.compact-table {
            width: 100%;
        }

        table.compact-table th,
        table.compact-table td {
            font-size: 0.88rem;
            padding: 10px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>View PPE IIRUP #<?= (int) $iirup['id'] ?></h2>

    <div class="view-actions">
        <a href="PPE_iirup.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to PPE IIRUP List</a>
        <a href="edit_ppe_iirup.php?id=<?= (int) $iirup['id'] ?>" class="btn btn-success"><i class="fas fa-edit"></i> Edit IIRUP</a>
        <a href="PPE_iirup_export.php?id=<?= (int) $iirup['id'] ?>" class="btn btn-warning"><i class="fas fa-download"></i> Export</a>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-circle-info"></i> Report Header</h3>
        <div class="item-grid">
            <div class="field"><span class="label">Date Reported</span><span class="value"><?= date_or_na($iirup['date_reported'] ?? null) ?></span></div>
            <div class="field"><span class="label">Entity Name</span><span class="value"><?= h($iirup['entity_name'] ?? 'TESDA Regional Office') ?></span></div>
            <div class="field"><span class="label">PPE No.</span><span class="value"><?= h($iirup['PPE_no'] ?? '') ?></span></div>
            <div class="field"><span class="label">Particulars</span><span class="value"><?= h($iirup['particulars'] ?? '') ?></span></div>
            <div class="field"><span class="label">Quantity</span><span class="value"><?= h($iirup['quantity'] ?? 0) ?></span></div>
            <div class="field"><span class="label">Carrying Amount</span><span class="value"><?= money($iirup['carrying_amount'] ?? 0) ?></span></div>
            <div class="field"><span class="label">Total Cost</span><span class="value"><?= money($iirup['total_cost'] ?? 0) ?></span></div>
            <div class="field"><span class="label">Amount</span><span class="value"><?= money($iirup['amount'] ?? 0) ?></span></div>
            <div class="field"><span class="label">Remarks</span><span class="value"><?= h($iirup['remarks'] ?? 'N/A') ?></span></div>
        </div>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-users"></i> Signatories</h3>
        <div class="item-grid">
            <div class="field"><span class="label">Requested By</span><span class="value"><?= h($iirup['requested_by_name'] ?? '') ?></span></div>
            <div class="field"><span class="label">Requested By Designation</span><span class="value"><?= h($iirup['requested_by_designation'] ?? '') ?></span></div>
            <div class="field"><span class="label">Requested By Station</span><span class="value"><?= h($iirup['requested_by_station'] ?? '') ?></span></div>
            <div class="field"><span class="label">Approved By</span><span class="value"><?= h($iirup['approved_by_name'] ?? '') ?></span></div>
            <div class="field"><span class="label">Approved By Designation</span><span class="value"><?= h($iirup['approved_by_designation'] ?? '') ?></span></div>
            <div class="field"><span class="label">Inspection Officer</span><span class="value"><?= h($iirup['inspection_officer_name'] ?? '') ?></span></div>
            <div class="field"><span class="label">Inspection Officer Designation</span><span class="value"><?= h($iirup['inspection_officer_designation'] ?? '') ?></span></div>
            <div class="field"><span class="label">Witness</span><span class="value"><?= h($iirup['witness_name'] ?? '') ?></span></div>
            <div class="field"><span class="label">Witness Designation</span><span class="value"><?= h($iirup['witness_designation'] ?? '') ?></span></div>
        </div>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-list"></i> IIRUP Items</h3>
        <div class="table-wrap">
            <table class="compact-table">
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
                                <td><?= money($item['depreciation'] ?? 0) ?></td>
                                <td><?= money($item['impairment_loss'] ?? 0) ?></td>
                                <td><?= money($item['carrying_amount'] ?? 0) ?></td>
                                <td><?= h($item['sale'] ?? 0) ?></td>
                                <td><?= h($item['transfer'] ?? 0) ?></td>
                                <td><?= h($item['destruction'] ?? 0) ?></td>
                                <td><?= h($item['other'] ?? 0) ?></td>
                                <td><?= h($item['total'] ?? 0) ?></td>
                                <td><?= money($item['appraised_value'] ?? 0) ?></td>
                                <td><?= h($item['or_no'] ?? '') ?></td>
                                <td><?= money($item['amount'] ?? 0) ?></td>
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
</div>

</body>
</html>
