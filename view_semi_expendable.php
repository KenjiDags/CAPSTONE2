<?php 
    require 'auth.php';
    require 'config.php'; 
    include 'sidebar.php';
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
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f06e";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* Action Buttons Container */
        .view-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(249, 250, 251, 0.9) 100%) !important;
            backdrop-filter: blur(10px) !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
            border: 1px solid rgba(203, 213, 225, 0.5) !important;
        }
        
        /* Details Container */
        .view-details {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(249, 250, 251, 0.9) 100%) !important;
            backdrop-filter: blur(10px) !important;
            padding: 24px !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
            margin-bottom: 24px !important;
            border: 1px solid rgba(203, 213, 225, 0.5) !important;
        }
        
        .view-details p {
            margin: 12px 0 !important;
            font-size: 14px !important;
            color: #1e293b !important;
            line-height: 1.6 !important;
        }
        
        .view-details p strong {
            color: #0f172a !important;
            font-weight: 600 !important;
            display: inline-block !important;
            min-width: 200px !important;
        }
        
        /* Stock Card Table */
        .stock-card-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 14px !important;
            font-size: 13px !important;
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07) !important;
        }
        
        .stock-card-table th {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
            color: white !important;
            font-weight: 600 !important;
            padding: 12px 10px !important;
            text-align: center !important;
            border: 1px solid rgba(30, 64, 175, 0.3) !important;
        }
        
        .stock-card-table td {
            border: 1px solid #e5e7eb !important;
            padding: 10px !important;
            text-align: center !important;
            background: white !important;
        }
        
        .stock-card-table tbody tr:hover {
            background-color: #f0f9ff !important;
        }
        
        .property-view-section {
            margin-top: 24px !important;
        }
        
        .property-view-section h3 {
            margin-top: 18px !important;
            margin-bottom: 12px !important;
            color: #1e293b !important;
            font-size: 18px !important;
            font-weight: 600 !important;
        }
    </style>
</head>
<body class="view-semi-expendable-page">
    <div class="container">
        <h2>Viewing Semi-Expendable Property No. <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></h2>
        <div class="view-actions">
            <?php 
              $backUrl = 'semi_expendible.php?category=' . urlencode($item['category']);
              $returnGet = $_GET['return'] ?? '';
              if (is_string($returnGet) && preg_match('/^[A-Za-z0-9_\-]+\.php(\?.*)?$/', $returnGet)) {
                  $backUrl = $returnGet;
              }
            ?>
            <a href="PC_semi.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Property Card
            </a>
            <form action="semi_expendable_export.php" method="get" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </form>
        </div>
        <div class="view-details">
            <p><strong>Entity Name:</strong> TESDA</p>
            <p><strong>Property No.:</strong> <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($item['item_description']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?></p>
            <?php $baseQty = (int)($item['quantity'] ?? ($item['quantity_issued'] ?? 0)); ?>
            <p><strong>Quantity:</strong> <?php echo number_format($baseQty); ?></p>
            <p><strong>Quantity Issued:</strong> <?php echo htmlspecialchars($item['quantity_issued']); ?></p>
            <?php 
                // Choose the appropriate Office/Officer field for the current item summary
                $summary_officer = '';
                if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
                    $summary_officer = $item['office_officer_returned'] ?? '';
                    $summary_label = 'Office/Officer Returned';
                } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
                    $summary_officer = $item['office_officer_reissued'] ?? '';
                    $summary_label = 'Office/Officer Re-issued';
                } else {
                    $summary_officer = $item['office_officer_issued'] ?? '';
                    $summary_label = 'Office/Officer Issued';
                }
            ?>
            <p><strong><?php echo $summary_label; ?>:</strong> <?php echo htmlspecialchars($summary_officer); ?></p>
            <p><strong>Quantity Returned:</strong> <?php echo htmlspecialchars($item['quantity_returned']); ?></p>
            <p><strong>Quantity Re-issued:</strong> <?php echo htmlspecialchars($item['quantity_reissued']); ?></p>
            <p><strong>Quantity Disposed:</strong> <?php echo htmlspecialchars($item['quantity_disposed']); ?></p>
            <p><strong>Quantity Balance:</strong> <?php echo htmlspecialchars($item['quantity_balance']); ?></p>
            <p><strong>Amount (Total):</strong> ₱<?php echo number_format($item['amount_total'], 2); ?></p>
            <p><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($item['fund_cluster']); ?></p>
            <p><strong>Estimated Useful Life:</strong> <?php echo htmlspecialchars($item['estimated_useful_life']); ?> years</p>
            <p><strong>Date:</strong> <?php echo date('m/d/Y', strtotime($item['date'])); ?></p>
            <p><strong>Remarks:</strong> <?php echo htmlspecialchars($item['remarks']); ?></p>
            <?php
                // Prepare computed values similar to export/PC list
                // Base quantity comes from 'quantity' column when present; fallback if needed
                $qtyIssued = (int)($item['quantity'] ?? ($item['quantity_issued'] ?? 0));
                $amountTotal = (float)($item['amount_total'] ?? 0);
                // Issue/Transfer/Disposal Qty now equals Quantity Issued + Quantity Disposed
                $issueQty = (int)($item['quantity_issued'] ?? 0) + (int)($item['quantity_disposed'] ?? 0);
                // Compute Office/Officer label for the current snapshot row
                $officeOfficer = '';
                if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
                    $officeOfficer = $item['office_officer_returned'] ?? '';
                } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
                    $officeOfficer = $item['office_officer_reissued'] ?? '';
                } else {
                    $officeOfficer = $item['office_officer_issued'] ?? '';
                }

                // Fetch history rows (latest first)
                $hist_rows = [];
                $hist_q = $conn->prepare("SELECT * FROM semi_expendable_history WHERE semi_id = ? ORDER BY created_at ASC, id ASC");
                if ($hist_q) {
                    $hist_q->bind_param("i", $id);
                    $hist_q->execute();
                    $hist_res = $hist_q->get_result();
                    while ($hr = $hist_res->fetch_assoc()) { $hist_rows[] = $hr; }
                    $hist_q->close();
                }
                // Remove only 'Pre-ICS Snapshot' rows from view rendering (show Initial Receipt)
                if (!empty($hist_rows)) {
                    $hist_rows = array_values(array_filter($hist_rows, function($hr){
                        $remarks = isset($hr['remarks']) ? trim($hr['remarks']) : '';
                        return strcasecmp($remarks, 'Pre-ICS Snapshot') !== 0;
                    }));
                }
            ?>

            <div class="property-view-section">
                <table class="stock-card-table">
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
                        <!-- Current snapshot row -->
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
                                // For history rows, Issue Qty = quantity_issued + quantity_disposed
                                $h_issueQty = (int)($hr['quantity_issued'] ?? 0) + (int)($hr['quantity_disposed'] ?? 0);
                                // Pick proper Office/Officer for this history row
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
    </div>
</body>
</html>
