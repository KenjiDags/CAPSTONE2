<?php include 'sidebar.php'; ?>
<?php require 'config.php'; 
    
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
</head>
<body class="view-semi-expendable-page">
    <div class="content">
        <h2>📋 Viewing Semi-Expendable Property No. <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></h2>
        <div class="ris-actions">
            <a href="semi_expendible.php?category=<?php echo urlencode($item['category']); ?>" class="btn btn-secondary">← Back to List</a>
            <form action="semi_expendable_export.php" method="get" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="btn btn-primary">📄 Export PDF</button>
            </form>
        </div>
        <div class="ris-details">
            <p><strong>Entity Name:</strong> TESDA</p>
            <p><strong>Property No.:</strong> <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($item['item_description']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?></p>
            <p><strong>Quantity Issued:</strong> <?php echo htmlspecialchars($item['quantity_issued']); ?></p>
            <p><strong>Officer/Office Issued:</strong> <?php echo htmlspecialchars($item['office_officer_issued']); ?></p>
            <p><strong>Quantity Returned:</strong> <?php echo htmlspecialchars($item['quantity_returned']); ?></p>
            <p><strong>Quantity Re-issued:</strong> <?php echo htmlspecialchars($item['quantity_reissued']); ?></p>
            <p><strong>Quantity Disposed:</strong> <?php echo htmlspecialchars($item['quantity_disposed']); ?></p>
            <p><strong>Quantity Balance:</strong> <?php echo htmlspecialchars($item['quantity_balance']); ?></p>
            <p><strong>Amount (Total):</strong> ₱<?php echo number_format($item['amount_total'], 2); ?></p>
            <p><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($item['fund_cluster']); ?></p>
            <p><strong>Estimated Useful Life:</strong> <?php echo htmlspecialchars($item['estimated_useful_life']); ?> years</p>
            <p><strong>Date:</strong> <?php echo date('m/d/Y', strtotime($item['date'])); ?></p>
            <p><strong>Remarks:</strong> <?php echo htmlspecialchars($item['remarks']); ?></p>
        </div>
    </div>
</body>
</html>
