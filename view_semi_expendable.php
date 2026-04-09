<?php 
    require 'auth.php';
    require 'config.php'; 
    require_once 'functions.php';
    ensure_semi_expendable_history($conn);
    
    if (!isset($_GET['id'])) {
        die("❌ Error: item not found.");
    }
    $id = (int)$_GET['id'];
    if ($id <= 0) {
        die("❌ Invalid item ID.");
    }
    $result = $conn->query("SELECT * FROM semi_expendable_property WHERE id = $id");
    if (!$result) {
        die("❌ Database error: " . $conn->error);
    }
    if ($result->num_rows === 0) {
        die("❌ No record found for Item ID $id.");
    }
    $item = $result->fetch_assoc();

    // Back URL: prefer validated return target, fallback to category list
    $backUrl = 'semi_expendible.php?category=' . urlencode($item['category']);
    $returnGet = $_GET['return'] ?? '';
    if (is_string($returnGet) && preg_match('/^[A-Za-z0-9_\-]+\.php(\?.*)?$/', $returnGet)) {
        $backUrl = $returnGet;
    }

    // Summary values
    $baseQty = (int)($item['quantity'] ?? ($item['quantity_issued'] ?? 0));
    $summary_officer = '';
    $summary_label = 'Office/Officer Issued';
    if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
        $summary_officer = $item['office_officer_returned'] ?? '';
        $summary_label = 'Office/Officer Returned';
    } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
        $summary_officer = $item['office_officer_reissued'] ?? '';
        $summary_label = 'Office/Officer Re-issued';
    } else {
        $summary_officer = $item['office_officer_issued'] ?? '';
    }

    // Stock card values
    $qtyIssued = (int)($item['quantity'] ?? ($item['quantity_issued'] ?? 0));
    $issueQty = (int)($item['quantity_issued'] ?? 0) + (int)($item['quantity_disposed'] ?? 0);
    $officeOfficer = '';
    if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
        $officeOfficer = $item['office_officer_returned'] ?? '';
    } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
        $officeOfficer = $item['office_officer_reissued'] ?? '';
    } else {
        $officeOfficer = $item['office_officer_issued'] ?? '';
    }

    // Fetch history rows (oldest first) and hide pre-ICS snapshot rows
    $hist_rows = [];
    $hist_q = $conn->prepare("SELECT * FROM semi_expendable_history WHERE semi_id = ? ORDER BY created_at ASC, id ASC");
    if ($hist_q) {
        $hist_q->bind_param("i", $id);
        $hist_q->execute();
        $hist_res = $hist_q->get_result();
        while ($hr = $hist_res->fetch_assoc()) { $hist_rows[] = $hr; }
        $hist_q->close();
    }
    if (!empty($hist_rows)) {
        $hist_rows = array_values(array_filter($hist_rows, function($hr){
            $remarks = isset($hr['remarks']) ? trim($hr['remarks']) : '';
            return strcasecmp($remarks, 'Pre-ICS Snapshot') !== 0;
        }));
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Semi-Expendable Property - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .container h2::before {
            content: "\f06e";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
        <h2>Viewing Semi-Expendable Property No. <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></h2>

        <div class="ris-actions">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <a href="PC_semi.php" class="btn btn-primary">
                <i class="fas fa-table"></i> Property Card
            </a>
            <a href="semi_expendable_export.php?id=<?php echo (int)$id; ?>" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>

        <h3><i class="fas fa-info-circle"></i> Property Details</h3>
        <div class="ris-details">
            <p><strong>Entity Name:</strong> <span>TESDA</span></p>
            <p><strong>Property No.:</strong> <span><?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></span></p>
            <p><strong>Description:</strong> <span><?php echo htmlspecialchars($item['item_description']); ?></span></p>
            <p><strong>Category:</strong> <span><?php echo htmlspecialchars($item['category']); ?></span></p>
            <p><strong>Quantity:</strong> <span><?php echo number_format($baseQty); ?></span></p>
            <p><strong>Quantity Issued:</strong> <span><?php echo htmlspecialchars($item['quantity_issued']); ?></span></p>
            <p><strong><?php echo htmlspecialchars($summary_label); ?>:</strong> <span><?php echo htmlspecialchars($summary_officer); ?></span></p>
            <p><strong>Quantity Returned:</strong> <span><?php echo htmlspecialchars($item['quantity_returned']); ?></span></p>
            <p><strong>Quantity Re-issued:</strong> <span><?php echo htmlspecialchars($item['quantity_reissued']); ?></span></p>
            <p><strong>Quantity Disposed:</strong> <span><?php echo htmlspecialchars($item['quantity_disposed']); ?></span></p>
            <p><strong>Quantity Balance:</strong> <span><?php echo htmlspecialchars($item['quantity_balance']); ?></span></p>
            <p><strong>Amount (Total):</strong> <span>₱<?php echo number_format((float)$item['amount_total'], 2); ?></span></p>
            <p><strong>Fund Cluster:</strong> <span><?php echo htmlspecialchars($item['fund_cluster']); ?></span></p>
            <p><strong>Estimated Useful Life:</strong> <span><?php echo htmlspecialchars($item['estimated_useful_life']); ?> years</span></p>
            <p><strong>Date:</strong> <span><?php echo date('m/d/Y', strtotime($item['date'])); ?></span></p>
            <p><strong>Remarks:</strong> <span><?php echo htmlspecialchars($item['remarks']); ?></span></p>
        </div>

        <h3><i class="fas fa-box"></i> Stock Card History</h3>
        <div class="table-container" style="margin: 25px 0;">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width:10%;">Date</th>
                        <th rowspan="2">Reference</th>
                        <th rowspan="2">Description</th>
                        <th>Receipt</th>
                        <th colspan="2">Issue/Transfer/Disposal</th>
                        <th rowspan="2">Balance Qty.</th>
                    </tr>
                    <tr>
                        <th>Qty.</th>
                        <th>Qty.</th>
                        <th>Office</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($item['date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['ics_rrsp_no'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></td>
                        <td><?php echo $qtyIssued ?: ''; ?></td>
                        <td><?php echo $issueQty ?: ''; ?></td>
                        <td><?php echo htmlspecialchars($officeOfficer); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity_balance'] ?? ''); ?></td>
                    </tr>
                    <?php if (!empty($hist_rows)): ?>
                        <?php foreach ($hist_rows as $hr):
                            $h_issueQty = (int)($hr['quantity_issued'] ?? 0) + (int)($hr['quantity_disposed'] ?? 0);
                            $h_office = '';
                            $rtext = strtolower(trim($hr['remarks'] ?? ''));
                            if (strpos($rtext, 'returned') !== false || (isset($hr['quantity_returned']) && (int)$hr['quantity_returned'] > 0)) {
                                $h_office = $hr['office_officer_returned'] ?? '';
                            } elseif (strpos($rtext, 're-issue') !== false || strpos($rtext, 'reissued') !== false || (isset($hr['quantity_reissued']) && (int)$hr['quantity_reissued'] > 0)) {
                                $h_office = $hr['office_officer_reissued'] ?? '';
                            } elseif (strpos($rtext, 'issued') !== false || (isset($hr['quantity_issued']) && (int)$hr['quantity_issued'] > 0)) {
                                $h_office = $hr['office_officer_issued'] ?? '';
                            } else {
                                $h_office = $hr['office_officer_issued'] ?: ($hr['office_officer_returned'] ?: ($hr['office_officer_reissued'] ?? ''));
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($hr['date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($hr['ics_rrsp_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></td>
                            <td><?php echo (int)($hr['quantity_issued'] ?? 0) ?: ''; ?></td>
                            <td><?php echo $h_issueQty ?: ''; ?></td>
                            <td><?php echo htmlspecialchars($h_office); ?></td>
                            <td><?php echo htmlspecialchars($hr['quantity_balance'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
