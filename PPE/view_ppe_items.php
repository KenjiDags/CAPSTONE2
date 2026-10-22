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
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PPE Item</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="detail-view-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <h2><i class="fas fa-box" aria-hidden="true"></i> View PPE Item: <?= h($item['item_name']) ?></h2>

    <div class="ris-actions">
        <a href="<?= htmlspecialchars($ppeReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="PPE/edit_ppe.php?id=<?= (int) $item['id'] ?>&amp;return=<?= urlencode($ppeReturnUrl) ?>" class="btn btn-success"><i class="fas fa-edit"></i> Edit Item</a>
    </div>

    <h3><i class="fas fa-info-circle" aria-hidden="true"></i> Item Details</h3>
    <div class="ris-details">
        <?php foreach (['PPE_no' => 'PPE No.', 'par_no' => 'PAR No.', 'item_name' => 'Item Name', 'item_description' => 'Description', 'quantity' => 'Quantity', 'unit' => 'Unit'] as $field => $label): ?>
        <p><strong><?= $label ?>:</strong> <span><?= h($item[$field] ?? '') ?></span></p>
        <?php endforeach; ?>
        <p><strong>Date Acquired:</strong> <span><?= date_or_na($item['date_acquired'] ?? null) ?></span></p>
    </div>

    <h3><i class="fas fa-user-check" aria-hidden="true"></i> Assignment and Condition</h3>
    <div class="ris-details">
        <?php foreach (['custodian' => 'Custodian', 'officer_incharge' => 'Officer In Charge', 'status' => 'Status', 'condition' => 'Condition'] as $field => $label): ?>
        <p><strong><?= $label ?>:</strong> <span><?= h($item[$field] ?? '') ?></span></p>
        <?php endforeach; ?>
    </div>

    <h3><i class="fas fa-coins" aria-hidden="true"></i> Value and Remarks</h3>
    <div class="ris-details">
        <p><strong>Amount:</strong> <span class="currency"><?= money($item['amount'] ?? 0) ?></span></p>
        <p><strong>Remarks:</strong> <span><?= nl2br(h($item['remarks'] ?? 'N/A')) ?></span></p>
    </div>
</div>

</body>
</html>
