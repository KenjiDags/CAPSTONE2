<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

$iirusp_id = isset($_GET['iirusp_id']) ? (int)$_GET['iirusp_id'] : 0;
$iirusp = null; $items = [];

if ($iirusp_id) {
    $stmt = $conn->prepare("SELECT * FROM iirusp WHERE iirusp_id=? LIMIT 1");
    $stmt->bind_param('i', $iirusp_id);
    $stmt->execute();
    $iirusp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM iirusp_items WHERE iirusp_id=? ORDER BY iirusp_item_id ASC");
    $stmt->bind_param('i', $iirusp_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $items[] = $r; }
    $stmt->close();
}

if (!$iirusp) { echo 'IIRUSP not found.'; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View IIRUSP</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.container h2::before {
    content: "\f022";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #3b82f6;
    margin-right: 10px;
}
.view-card { background:#fff; padding:20px; margin: 25px 0; border-radius:8px; }
.info-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px; }
.info-item { padding:8px 0; }
.info-item label { font-weight:600; color:#4b5563; display:block; margin-bottom:4px; }
.info-item div { color:#1f2937; }
table { width:100%; border-collapse:collapse; margin:16px 0; }
table th, table td { padding:10px; text-align:left; }
table th { background:var(--blue-gradient); color:#fff; font-weight:600; }
.signature-line { border-top:1px solid #000; margin:40px 20px 5px; padding-top:5px; }
table tbody td[colspan]::before {
    font-size: 35px !important;
    margin-bottom: 0 !important;
}
</style>
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>IIRUSP Details</h2>
    
    <div class="actions" style="margin-bottom:20px;">
        <a href="iirusp.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        <a href="edit_iirusp.php?iirusp_id=<?= $iirusp_id ?>" class="btn-primary"><i class="fas fa-edit"></i> Edit</a>
        <a href="export_iirusp.php?iirusp_id=<?= $iirusp_id ?>" class="btn-success"><i class="fas fa-file-pdf"></i> Export PDF</a>
    </div>

    <h3><i class="fas fa-info-circle"></i>IIRUSP Information</h3>
        <div class="view-card">
            <div class="info-grid">
                <div class="info-item"><label>IIRUSP No.:</label><div><?= htmlspecialchars($iirusp['iirusp_no']) ?></div></div>
                <div class="info-item"><label>As At:</label><div><?= date('F d, Y', strtotime($iirusp['as_at'])) ?></div></div>
                <div class="info-item"><label>Entity Name:</label><div><?= htmlspecialchars($iirusp['entity_name']) ?></div></div>
                <div class="info-item"><label>Fund Cluster:</label><div><?= htmlspecialchars($iirusp['fund_cluster']) ?></div></div>
                <div class="info-item"><label>Accountable Officer:</label><div><?= htmlspecialchars($iirusp['accountable_officer_name']) ?></div></div>
                <div class="info-item"><label>Designation:</label><div><?= htmlspecialchars($iirusp['accountable_officer_designation']) ?></div></div>
                <div class="info-item"><label>Station:</label><div><?= htmlspecialchars($iirusp['accountable_officer_station']) ?></div></div>
            </div>
        </div>

        <h3><i class="fas fa-boxes"></i>Items</h3>
        <table style="margin: 25px 0;">
            <thead>
                <tr>
                    <th>Date Acquired</th>
                    <th>Particulars</th>
                    <th>Property No.</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Unit Cost</th>
                    <th>Total Cost</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php $total = 0; foreach ($items as $item): $total += $item['total_cost']; ?>
                <tr>
                    <td><?= $item['date_acquired'] ? date('M d, Y', strtotime($item['date_acquired'])) : '-' ?></td>
                    <td><?= htmlspecialchars($item['particulars']) ?></td>
                    <td><?= htmlspecialchars($item['semi_expendable_property_no']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= htmlspecialchars($item['unit']) ?></td>
                    <td>₱<?= number_format($item['unit_cost'], 2) ?></td>
                    <td>₱<?= number_format($item['total_cost'], 2) ?></td>
                    <td><?= htmlspecialchars($item['remarks']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="6" style="text-align:right; font-weight:600; padding: 0 !important;">Grand Total:</td>
                    <td style="font-weight:700;">₱<?= number_format($total, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

    <h3><i class="fas fa-pen-nib"></i> Signatories</h3> 
        <div class="view-card">
            <div class="ris-details">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4><i class="fas fa-user"></i> Requested by:</h4>
                        <p><strong>Name:</strong> <span><?= htmlspecialchars($iirusp['requested_by']) ?></span></p>
                        <p><strong>Designation:</strong> <span><?= htmlspecialchars($iirusp['requested_by_designation'] ?? '') ?></span></p>
                    </div>
                    <div>
                        <h4><i class="fas fa-user-tie"></i> Approved by:</h4>
                        <p><strong>Name:</strong> <span><?= htmlspecialchars($iirusp['approved_by']) ?></span></p>
                        <p><strong>Designation:</strong> <span><?= htmlspecialchars($iirusp['approved_by_designation'] ?? '') ?></span></p>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <h4><i class="fas fa-user-shield"></i> Inspection Officer:</h4>
                        <p><strong>Name:</strong> <span><?= htmlspecialchars($iirusp['inspection_officer']) ?></span></p>
                        <p><strong>Designation:</strong> <span><?= htmlspecialchars($iirusp['inspection_officer_designation'] ?? '') ?></span></p>
                    </div>
                    <div>
                        <h4><i class="fas fa-user-friends"></i> Witness:</h4>
                        <p><strong>Name:</strong> <span><?= htmlspecialchars($iirusp['witness']) ?></span></p>
                        <p><strong>Designation:</strong> <span><?= htmlspecialchars($iirusp['witness_designation'] ?? '') ?></span></p>
                    </div>
                </div>
            </div>
        </div>
</div>
</body>
</html>
