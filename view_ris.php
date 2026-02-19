<?php
    require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View RIS - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f570";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <?php require 'config.php'; ?>

    <?php
    if (!isset($_GET['ris_id'])) {
        die("❌ Error: RIS ID not specified in the URL.");
    }

    $ris_id = (int)$_GET['ris_id'];

    // Fetch RIS header
    $ris_result = $conn->query("SELECT * FROM ris WHERE ris_id = $ris_id");

    if (!$ris_result) {
        die("❌ Database error: " . $conn->error);
    }

    if ($ris_result->num_rows === 0) {
        die("❌ No RIS record found for RIS ID: $ris_id");
    }

    $ris = $ris_result->fetch_assoc();

    // Fetch only issued items for this RIS
    $item_query = "
        SELECT 
            i.stock_number,
            i.item_name,
            i.description,
            i.unit,
            i.quantity_on_hand,
            ri.stock_available,
            ri.issued_quantity,
            ri.remarks
        FROM ris_items ri
        INNER JOIN items i ON i.stock_number = ri.stock_number
        WHERE ri.ris_id = $ris_id AND ri.issued_quantity > 0
        ORDER BY i.stock_number
    ";
    
    $item_result = $conn->query($item_query);
    ?>

    <div class="container">
        <h2>Viewing RIS No. <?php echo htmlspecialchars($ris['ris_no']); ?></h2>

        <!-- Action Buttons -->
        <div class="ris-actions">
            <a href="ris.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to RIS List
            </a>
            <a href="add_ris.php?ris_id=<?php echo $ris_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit RIS
            </a>
            <a href="export_ris.php?ris_id=<?php echo $ris_id; ?>" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>

        <!-- RIS Details -->
        <h3><i class="fas fa-info-circle"></i> RIS Details</h3>
        <div class="ris-details">
            <p><strong>Entity Name:</strong> <span><?php echo htmlspecialchars($ris['entity_name']); ?></span></p>
            <p><strong>Fund Cluster:</strong> <span><?php echo htmlspecialchars($ris['fund_cluster']); ?></span></p>
            <p><strong>Division:</strong> <span><?php echo htmlspecialchars($ris['division']); ?></span></p>
            <p><strong>Office:</strong> <span><?php echo htmlspecialchars($ris['office']); ?></span></p>
            <p><strong>Responsibility Center Code:</strong> <span><?php echo htmlspecialchars($ris['responsibility_center_code']); ?></span></p>
            <p><strong>RIS No:</strong> <span><?php echo htmlspecialchars($ris['ris_no']); ?></span></p>
            <p><strong>Date:</strong> <span><?php echo htmlspecialchars($ris['date_requested']); ?></span></p>
        </div>

        <h3><i class="fas fa-box"></i> Items</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-barcode"></i> Stock No.</th>
                        <th><i class="fas fa-tag"></i> Item</th>
                        <th><i class="fas fa-align-left"></i> Description</th>
                        <th><i class="fas fa-ruler"></i> Unit</th>
                        <th><i class="fas fa-cubes"></i> Quantity on Hand</th>
                        <th><i class="fas fa-check-circle"></i> Stock Available</th>
                        <th><i class="fas fa-shopping-cart"></i> Issued Quantity</th>
                        <th><i class="fas fa-comment"></i> Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($item_result && $item_result->num_rows > 0) {
                        while ($item = $item_result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['item_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['quantity_on_hand']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_available']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['issued_quantity']) . '</td>';
                            echo '<td>' . (!empty($item['remarks']) ? htmlspecialchars($item['remarks']) : '-') . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                No items were issued in this RIS.
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Purpose Section -->
        <h3><i class="fas fa-clipboard-list"></i> Purpose</h3>
        <div class="ris-details">
            <p><strong>Purpose:</strong> <span><?php echo htmlspecialchars($ris['purpose']); ?></span></p>
        </div>

        <h3><i class="fas fa-user-check"></i> Signatories</h3>
        <div class="ris-details">
            <p><strong>Requested by:</strong> <span><?php echo htmlspecialchars($ris['requested_by']); ?></span></p>
            <p><strong>Approved by:</strong> <span><?php echo htmlspecialchars($ris['approved_by']); ?></span></p>
            <p><strong>Issued by:</strong> <span><?php echo htmlspecialchars($ris['issued_by']); ?></span></p>
            <p><strong>Received by:</strong> <span><?php echo htmlspecialchars($ris['received_by']); ?></span></p>
        </div>
    </div>
<script src="js/view_ris_script.js?v=<?= time() ?>"></script>
</body>
</html>