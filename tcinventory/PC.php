<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC - Property Card</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .export-section {
            padding: 15px;
            border-radius: 5px;
        }
        
        .export-btn {
            background-color: #17a2b8;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .export-btn:hover {
            background-color: #138496;
            color: white;
            text-decoration: none;
        }
        
        .add-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .add-btn:hover {
            background-color: #218838;
            color: white;
            text-decoration: none;
        }
        
        .info-section {
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-section h4 {
            color: #004085;
            margin-bottom: 10px;
        }
        
        .info-section p {
            margin-bottom: 5px;
            font-size: 14px;
            color: #004085;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: center;
            font-size: 12px;
        }
        
        .table td {
            border: 1px solid #dee2e6;
            padding: 6px;
            font-size: 12px;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .currency { text-align: right; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <h2>Property Card (PC) </h2>

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