<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/config.php';
$ptr_id = filter_var($_GET['ptr_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$ptr_id) { header('Location: PPE_PTR.php'); exit; }
$stmt = $conn->prepare('SELECT * FROM ppe_ptr WHERE ptr_id = ?');
$stmt->bind_param('i', $ptr_id);
$stmt->execute();
$ptr = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ptr) { http_response_code(404); exit('PTR document not found.'); }
$stmt = $conn->prepare('SELECT p.*, pi.ppe_id AS linked_ppe_id FROM ppe_ptr_items pi LEFT JOIN ppe_property p ON p.id = pi.ppe_id WHERE pi.ptr_id = ? ORDER BY p.property_no, pi.ppe_id');
$stmt->bind_param('i', $ptr_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
function ptrViewH($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <meta charset="UTF-8">
    <base href="<?= ptrViewH(APP_ROOT_URL) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PTR <?= ptrViewH($ptr['ptr_no']) ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="detail-view-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <h2><i class="fas fa-exchange-alt" aria-hidden="true"></i> Viewing PTR No. <?= ptrViewH($ptr['ptr_no']) ?></h2>
    <div class="ris-actions">
        <a href="PPE/PPE_PTR.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="PPE/edit_ptr.php?ptr_id=<?= $ptr_id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
        <a href="PPE/export_ptr.php?ptr_id=<?= $ptr_id ?>" class="btn btn-success"><i class="fas fa-download"></i> Export</a>
    </div>
    <h3><i class="fas fa-info-circle"></i> PTR Details</h3>
    <div class="ris-details">
        <?php foreach (['ptr_no' => 'PTR No.', 'transfer_date' => 'Transfer Date', 'entity_name' => 'Entity Name', 'fund_cluster' => 'Fund Cluster', 'from_officer' => 'From Accountable Officer', 'to_officer' => 'To Accountable Officer', 'transfer_type' => 'Transfer Type'] as $field => $label): ?>
        <p><strong><?= $label ?>:</strong> <span><?= ptrViewH($ptr[$field] ?? '') ?></span></p>
        <?php endforeach; ?>
    </div>
    <h3><i class="fas fa-box"></i> Transferred Items</h3>
    <div class="table-container">
        <table>
            <thead><tr>
                <th scope="col"><i class="fas fa-calendar"></i> Date Acquired</th>
                <th scope="col"><i class="fas fa-barcode"></i> PPE No.</th>
                <th scope="col"><i class="fas fa-hashtag"></i> Property No.</th>
                <th scope="col"><i class="fas fa-tag"></i> Item</th>
                <th scope="col"><i class="fas fa-align-left"></i> Description</th>
                <th scope="col"><i class="fas fa-coins"></i> Amount</th>
                <th scope="col"><i class="fas fa-check-circle"></i> Condition</th>
            </tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= ptrViewH($item['date_acquired'] ?? '') ?></td>
                    <td><?= ptrViewH($item['PPE_no'] ?? '') ?></td>
                    <td><?= ptrViewH($item['property_no'] ?? '') ?></td>
                    <td><?= ptrViewH($item['item_name'] ?? ('PPE item #' . $item['linked_ppe_id'] . ' unavailable')) ?></td>
                    <td><?= ptrViewH($item['item_description'] ?? '') ?></td>
                    <td class="currency"><?= $item['amount'] !== null ? '&#8369; ' . number_format((float)$item['amount'], 2) : '' ?></td>
                    <td><?= ptrViewH($item['condition'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?><tr><td colspan="7"><i class="fas fa-inbox"></i> No items are linked to this PTR.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <h3><i class="fas fa-clipboard-list"></i> Reason for Transfer</h3>
    <div class="ris-details"><p><?= nl2br(ptrViewH($ptr['reason'] ?? '')) ?></p></div>
    <h3><i class="fas fa-user-check"></i> Signatories</h3>
    <div class="ris-details">
        <?php foreach (['approved_by' => 'Approved by', 'released_by' => 'Released by', 'received_by' => 'Received by'] as $field => $label): ?>
        <p><strong><?= $label ?>:</strong> <span><?= ptrViewH($ptr[$field] ?? '') ?></span></p>
        <p><strong>Designation:</strong> <span><?= ptrViewH($ptr[$field . '_designation'] ?? '') ?></span></p>
        <p><strong>Date:</strong> <span><?= ptrViewH($ptr[$field . '_date'] ?? '') ?></span></p>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>