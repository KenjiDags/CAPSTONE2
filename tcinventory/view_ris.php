<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View RIS - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>
<body class="view-ris-page">
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

    // Fetch ALL items with their RIS data (LEFT JOIN to include all items)
    $item_query = "
        SELECT 
            i.stock_number,
            i.item_name,
            i.description,
            i.unit,
            i.quantity_on_hand,
            COALESCE(ri.stock_available, 'No') as stock_available,
            COALESCE(ri.issued_quantity, 0) as issued_quantity,
            COALESCE(ri.remarks, '') as remarks
        FROM items i
        LEFT JOIN ris_items ri ON i.stock_number = ri.stock_number AND ri.ris_id = $ris_id
        ORDER BY i.stock_number
    ";
    
    $item_result = $conn->query($item_query);
    ?>

    <div class="content">
        <h2>📋 Viewing RIS No. <?php echo htmlspecialchars($ris['ris_no']); ?></h2>

        <!-- Action Buttons -->
        <div class="ris-actions">
            <a href="ris.php" class="btn btn-secondary">← Back to RIS List</a>
            <a href="add_ris.php?ris_id=<?php echo $ris_id; ?>" class="btn btn-primary">✏️ Edit RIS</a>
            <a href="export_ris.php?ris_id=<?php echo $ris_id; ?>" class="btn btn-primary">📄 Export PDF</a>
        </div>

        <!-- RIS Details (Purpose removed from here) -->
        <div class="ris-details">
            <p><strong>Entity Name:</strong> <?php echo htmlspecialchars($ris['entity_name']); ?></p>
            <p><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($ris['fund_cluster']); ?></p>
            <p><strong>Division:</strong> <?php echo htmlspecialchars($ris['division']); ?></p>
            <p><strong>Office:</strong> <?php echo htmlspecialchars($ris['office']); ?></p>
            <p><strong>Responsibility Center Code:</strong> <?php echo htmlspecialchars($ris['responsibility_center_code']); ?></p>
            <p><strong>RIS No:</strong> <?php echo htmlspecialchars($ris['ris_no']); ?></p>
            <p><strong>Date:</strong> <?php echo htmlspecialchars($ris['date_requested']); ?></p>
        </div>

        <h3>📦 Items</h3>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Stock No.</th>
                        <th>Item</th>
                        <th>Description</th>
                        <th>Unit</th>
                        <th>Quantity on Hand</th>
                        <th>Stock Available</th>
                        <th>Issued Quantity</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($item_result && $item_result->num_rows > 0) {
                        while ($item = $item_result->fetch_assoc()) {
                            // Highlight rows where items were actually issued
                            $row_class = ($item['issued_quantity'] > 0) ? 'style="background-color: #f0f8ff;"' : '';
                            
                            echo '<tr ' . $row_class . '>';
                            echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['item_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['quantity_on_hand']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_available']) . '</td>';
                            echo '<td>' . ($item['issued_quantity'] > 0 ? htmlspecialchars($item['issued_quantity']) : '-') . '</td>';
                            echo '<td>' . (!empty($item['remarks']) ? htmlspecialchars($item['remarks']) : '-') . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7">No items found in inventory.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Purpose Section (moved here) -->
        <h3>📝 Purpose</h3>
        <div class="ris-details">
            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($ris['purpose']); ?></p>
        </div>

        <h3>✍️ Signatories</h3>
        <div class="ris-details">
            <p><strong>Requested by:</strong> <?php echo htmlspecialchars($ris['requested_by']); ?></p>
            <p><strong>Approved by:</strong> <?php echo htmlspecialchars($ris['approved_by']); ?></p>
            <p><strong>Issued by:</strong> <?php echo htmlspecialchars($ris['issued_by']); ?></p>
            <p><strong>Received by:</strong> <?php echo htmlspecialchars($ris['received_by']); ?></p>
        </div>
    </div>
<script src="js/view_ris_script.js?v=<?= time() ?>"></script>
</body>
</html>