<?php
require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC - Property Card</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f02d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
            margin-right: 12px;
        }
        
        .export-section {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .export-btn,
        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .export-btn:hover,
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }
        
        .export-btn:hover {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
            color: white;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .add-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }
        
        .info-section {
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        
        .info-section h4 {
            color: #1e40af;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .info-section p {
            margin-bottom: 8px;
            font-size: 14px;
            color: #1e3a8a;
        }
        
        .table-responsive {
            overflow-x: auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 0;
        }
        
        .table th {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            font-weight: 600;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 12px 8px;
            text-align: center;
            font-size: 13px;
        }
        
        .table td {
            border: 1px solid #e5e7eb;
            padding: 10px 8px;
            font-size: 13px;
        }
        
        .table tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .currency { text-align: right; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>Property Card (PC)</h2>

        <!-- Export and Action Section -->
        <div class="export-section">
            <a href="add_pc.php" class="add-btn">
                <i class="fas fa-plus"></i> Add New Item
            </a>
            <a href="pc_export.php" class="export-btn" target="_blank">
                📄 Export to PDF
            </a>
        </div>

        <!-- Property Cards Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th rowspan="2">Entity Name</th>
                        <th rowspan="2">Fund Cluster</th>
                        <th rowspan="2">Property, Plant & Equipment</th>
                        <th rowspan="2">Description</th>
                        <th rowspan="2">Property Number</th>
                        <th rowspan="2">Date</th>
                        <th rowspan="2">Reference/PAR No.</th>
                        <th>Receipt</th>
                        <th colspan="2">Issue/Transfer/Disposal</th>
                        <th>Balance</th>
                        <th rowspan="2">Amount</th>
                        <th rowspan="2">Remarks</th>
                        <th rowspan="2">Actions</th>
                    </tr>
                    <tr>
                        <th>Qty.</th>
                        <th>Qty.</th>
                        <th>Office/Officer</th>
                        <th>Qty.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    require 'config.php';

                    // Query for Property Card entries
                    // Note: You'll need to create this table based on your database structure
                    $result = $conn->query("
                        SELECT pc.*, 
                            COALESCE(pc.receipt_qty, 0) as receipt_qty,
                            COALESCE(pc.issue_qty, 0) as issue_qty,
                            COALESCE(pc.receipt_qty, 0) - COALESCE(pc.issue_qty, 0) as balance_qty
                        FROM property_cards pc
                        ORDER BY pc.date_created DESC
                    ");

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['entity_name'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['fund_cluster'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['ppe_type'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['description'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['property_number'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['transaction_date'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['reference_par_no'] ?? '') . '</td>';
                            echo '<td class="text-center">' . htmlspecialchars($row['receipt_qty'] ?? '0') . '</td>';
                            echo '<td class="text-center">' . htmlspecialchars($row['issue_qty'] ?? '0') . '</td>';
                            echo '<td>' . htmlspecialchars($row['office_officer'] ?? '') . '</td>';
                            echo '<td class="text-center">' . htmlspecialchars($row['balance_qty'] ?? '0') . '</td>';
                            echo '<td class="currency">₱ ' . number_format($row['amount'] ?? 0, 2) . '</td>';
                            echo '<td>' . htmlspecialchars($row['remarks'] ?? '') . '</td>';
                            echo '<td class="text-center">';
                            echo '<a href="pc_edit.php?id=' . $row['pc_id'] . '" class="btn btn-sm btn-primary">Edit</a> ';
                            echo '<a href="pc_delete.php?id=' . $row['pc_id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure?\')">Delete</a>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="14" class="text-center">No Property Card entries found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Summary Section -->
        <div class="row mt-4">
            <div class="col-md-6">
                <h3>Summary by Property Type</h3>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Property Type</th>
                            <th>Total Quantity</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $summary = $conn->query("
                            SELECT ppe_type,
                                SUM(COALESCE(receipt_qty, 0) - COALESCE(issue_qty, 0)) as total_qty,
                                SUM(COALESCE(amount, 0)) as total_amount
                            FROM property_cards
                            GROUP BY ppe_type
                            ORDER BY ppe_type
                        ");

                        if ($summary && $summary->num_rows > 0) {
                            while ($row = $summary->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['ppe_type']) . '</td>';
                                echo '<td class="text-center">' . htmlspecialchars($row['total_qty']) . '</td>';
                                echo '<td class="currency">₱ ' . number_format($row['total_amount'], 2) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="3" class="text-center">No summary data available.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h3>Recent Transactions</h3>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Property</th>
                            <th>Transaction</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recent = $conn->query("
                            SELECT transaction_date, ppe_type, 
                                CASE 
                                    WHEN receipt_qty > 0 THEN 'Receipt'
                                    WHEN issue_qty > 0 THEN 'Issue/Transfer'
                                    ELSE 'Other'
                                END as transaction_type,
                                COALESCE(receipt_qty, issue_qty, 0) as qty
                            FROM property_cards
                            ORDER BY transaction_date DESC
                            LIMIT 10
                        ");

                        if ($recent && $recent->num_rows > 0) {
                            while ($row = $recent->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['transaction_date']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['ppe_type']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['transaction_type']) . '</td>';
                                echo '<td class="text-center">' . htmlspecialchars($row['qty']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="4" class="text-center">No recent transactions.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Add mobile sidebar toggle functionality if needed
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Add event listener for mobile menu button if you have one
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('.menu-button');
            if (menuButton) {
                menuButton.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html>