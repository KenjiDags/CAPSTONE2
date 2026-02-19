<?php
    require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View PAR - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f15b";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #10b981;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <?php require 'config.php'; ?>

    <?php
    if (!isset($_GET['id'])) {
        die("❌ Error: PAR ID not specified in the URL.");
    }

    $par_id = (int)$_GET['id'];

    // Fetch PAR header
    $par_stmt = $conn->prepare("SELECT * FROM ppe_par WHERE par_id = ?");
    $par_stmt->bind_param("i", $par_id);
    $par_stmt->execute();
    $par_result = $par_stmt->get_result();

    if (!$par_result) {
        die("❌ Database error: " . $conn->error);
    }

    if ($par_result->num_rows === 0) {
        die("❌ No PAR record found for PAR ID: $par_id");
    }

    $par = $par_result->fetch_assoc();

    // Fetch property items linked to this PAR (same as export)
    $items_query = "SELECT p.* FROM ppe_par_items pi 
                    JOIN ppe_property p ON pi.ppe_id = p.id 
                    WHERE pi.par_id = ? 
                    ORDER BY p.par_no";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("i", $par_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();

    // Calculate total amount
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += floatval($item['amount']);
    }
    ?>

    <div class="container">
        <h2>Viewing PAR No. <?php echo htmlspecialchars($par['par_no']); ?></h2>

        <!-- Action Buttons -->
        <div class="ris-actions">
            <a href="PPE_PAR.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to PAR List
            </a>
            <a href="PPE_PAR_export.php?id=<?php echo $par_id; ?>" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Export PAR
            </a>
        </div>

        <!-- PAR Details -->
        <h3><i class="fas fa-info-circle"></i> PAR Details</h3>
        <div class="ris-details">
            <p><strong>PAR No:</strong> <span><?php echo htmlspecialchars($par['par_no']); ?></span></p>
            <p><strong>Entity Name:</strong> <span><?php echo htmlspecialchars($par['entity_name']); ?></span></p>
            <p><strong>Fund Cluster:</strong> <span><?php echo htmlspecialchars($par['fund_cluster']); ?></span></p>
            <p><strong>Date Acquired:</strong> <span><?php echo htmlspecialchars($par['date_acquired']); ?></span></p>
        </div>

        <h3><i class="fas fa-box"></i> Property Items</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> Quantity</th>
                        <th><i class="fas fa-ruler"></i> Unit</th>
                        <th><i class="fas fa-align-left"></i> Description</th>
                        <th><i class="fas fa-barcode"></i> PAR No.</th>
                        <th><i class="fas fa-calendar"></i> Date Acquired</th>
                        <th><i class="fas fa-dollar-sign"></i> Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($items) > 0) {
                        foreach ($items as $item) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($item['quantity']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit'] ?? 'unit') . '</td>';
                            echo '<td>' . htmlspecialchars($item['item_name'] . ' - ' . $item['item_description']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['par_no']) . '</td>';
                            echo '<td>' . ($par['received_by_date'] ? date('m/d/Y', strtotime($par['received_by_date'])) : '') . '</td>';
                            echo '<td>₱ ' . number_format($item['amount'], 2) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                No property items linked to this PAR.
                              </td></tr>';
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align: right; font-weight: bold;">Total Amount:</td>
                        <td style="font-weight: bold;">₱ <?php echo number_format($total_amount, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Signatories Section -->
        <h3><i class="fas fa-user-check"></i> Signatories</h3>
        <div class="ris-details">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4><i class="fas fa-user"></i> Received by:</h4>
                    <p><strong>Name:</strong> <span><?php echo htmlspecialchars($par['received_by']); ?></span></p>
                    <p><strong>Designation:</strong> <span><?php echo htmlspecialchars($par['received_by_designation']); ?></span></p>
                    <p><strong>Date:</strong> <span><?php echo htmlspecialchars($par['received_by_date']); ?></span></p>
                </div>
                <div>
                    <h4><i class="fas fa-user-tie"></i> Issued by:</h4>
                    <p><strong>Name:</strong> <span><?php echo htmlspecialchars($par['issued_by']); ?></span></p>
                    <p><strong>Designation:</strong> <span><?php echo htmlspecialchars($par['issued_by_designation']); ?></span></p>
                    <p><strong>Date:</strong> <span><?php echo htmlspecialchars($par['issued_by_date']); ?></span></p>
                </div>
            </div>
        </div>

        <!-- Property Assignment Info -->
        <?php
        // Get the officer/custodian info from the first item (they should all be the same for a PAR)
        if (count($items) > 0) {
            $first_item = $items[0];
            if (!empty($first_item['officer_incharge']) || !empty($first_item['custodian'])) {
        ?>
        <h3><i class="fas fa-users"></i> Property Assignment</h3>
        <div class="ris-details">
            <?php if (!empty($first_item['officer_incharge'])): ?>
            <p><strong>Accountable Officer:</strong> <span><?php echo htmlspecialchars($first_item['officer_incharge']); ?></span></p>
            <?php endif; ?>
            <?php if (!empty($first_item['custodian'])): ?>
            <p><strong>Custodian:</strong> <span><?php echo htmlspecialchars($first_item['custodian']); ?></span></p>
            <?php endif; ?>
        </div>
        <?php
            }
        }
        ?>
    </div>
</body>
</html>
