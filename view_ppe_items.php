<?php
require 'auth.php';
require 'config.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PPE Item</title>
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
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>View PPE Item: <?= h($item['item_name']) ?></h2>

    <div class="view-actions">
        <a href="PPE.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to PPE List</a>
        <a href="edit_ppe.php?id=<?= (int) $item['id'] ?>" class="btn btn-success"><i class="fas fa-edit"></i> Edit Item</a>
    </div>

    <div class="view-card">
        <h3><i class="fas fa-circle-info"></i> Item Overview</h3>
        <div class="item-grid">
            <div class="field"><span class="label">PPE No</span><span class="value"><?= h($item['PPE_no'] ?? '') ?></span></div>
            <div class="field"><span class="label">PAR No</span><span class="value"><?= h($item['par_no'] ?? '') ?></span></div>
            <div class="field"><span class="label">Item Name</span><span class="value"><?= h($item['item_name'] ?? '') ?></span></div>
            <div class="field"><span class="label">Description</span><span class="value"><?= h($item['item_description'] ?? '') ?></span></div>
            <div class="field"><span class="label">Quantity</span><span class="value"><?= h($item['quantity'] ?? 0) ?></span></div>
            <div class="field"><span class="label">Unit</span><span class="value"><?= h($item['unit'] ?? '') ?></span></div>
            <div class="field"><span class="label">Date Acquired</span><span class="value"><?= date_or_na($item['date_acquired'] ?? null) ?></span></div>
            <div class="field"><span class="label">Custodian</span><span class="value"><?= h($item['custodian'] ?? '') ?></span></div>
            <div class="field"><span class="label">Officer In Charge</span><span class="value"><?= h($item['officer_incharge'] ?? '') ?></span></div>
            <div class="field"><span class="label">Status</span><span class="value"><?= h($item['status'] ?? '') ?></span></div>
            <div class="field"><span class="label">Condition</span><span class="value"><?= h($item['condition'] ?? '') ?></span></div>
            <div class="field"><span class="label">Amount</span><span class="value"><?= money($item['amount'] ?? 0) ?></span></div>
            <div class="field"><span class="label">Remarks</span><span class="value"><?= h($item['remarks'] ?? 'N/A') ?></span></div>
        </div>
    </div>
</div>

</body>
</html>
