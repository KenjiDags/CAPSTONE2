<?php 
    require 'config.php'; 
    require 'auth.php';
    include 'sidebar.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history']) && isset($_POST['item_id'])) {
        $item_id_to_clear = (int)$_POST['item_id'];
        $conn->query("INSERT INTO item_history_archive SELECT * FROM item_history WHERE item_id = $item_id_to_clear");
        $conn->query("DELETE FROM item_history WHERE item_id = $item_id_to_clear");

        if ($conn->affected_rows > 0) {
            header("Location: view_sc.php?item_id=" . $item_id_to_clear . "&cleared=1");
            exit;
        } else {
            echo "<script>alert('❌ Failed to clear history');</script>";
        }
    }

        if (isset($_GET['cleared']) && $_GET['cleared'] == 1) {
            echo "<script>alert('✅ History cleared successfully');</script>";
        }

        if (isset($_GET['undo']) && isset($_GET['item_id'])) {
        $item_id_to_restore = (int)$_GET['item_id'];

        // Move history back from archive
        $conn->query("INSERT INTO item_history SELECT * FROM item_history_archive WHERE item_id = $item_id_to_restore");
        $conn->query("DELETE FROM item_history_archive WHERE item_id = $item_id_to_restore");

        echo "<script>alert('✅ History restored successfully');</script>";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View SC - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Page-Specific Icon */
    .container h2::before {
        content: "\f022";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        color: #3b82f6;
        margin-right: 10px;
    }
    
    /* Page-specific container with left margin */
    .container {
        margin-left: 240px; 
    }

    #clearHistoryBtn {
        background-color: #facc15 !important;
        border-color: #eab308 !important;
        color: #1e293b !important;
    }

    #clearHistoryBtn:hover {
        background-color: #eab308 !important;
    }

    .actions a {
        margin-right: 0 !important;
    }

    </style>
</head>
<body>

<?php
        if (!isset($_GET['item_id'])) {
            die("❌ Error: item not found.");
        }

        $item_id = (int)$_GET['item_id'];
        if ($item_id <= 0) {
            die("❌ Invalid item ID.");
        }

        if (isset($_GET['cleared']) && $_GET['cleared'] == 1) {
            echo "<script>
                if (confirm('✅ History cleared successfully. Undo?')) {
                    window.location.href = 'view_sc.php?item_id=" . $item_id . "&undo=1';
                }
            </script>";
        }

        $result = $conn->query("SELECT * FROM items WHERE item_id = $item_id");

        if (!$result) {
            die("❌ Database error: " . $conn->error);
        }

        if ($result->num_rows === 0) {
            die("❌ No record found for Item ID $item_id.");
        }

        $items = $result->fetch_assoc();

        $history_sql = "SELECT * FROM item_history WHERE item_id = $item_id ORDER BY changed_at DESC";
        $history_result = $conn->query($history_sql);

        $history_rows = [];
        if ($history_result && $history_result->num_rows > 0) {
            while ($row = $history_result->fetch_assoc()) {
                $history_rows[] = $row;
            }
        }
    ?>

    <div class="container">
        <h2>Viewing Stock Card - Item No. <?php echo htmlspecialchars($items['item_id']); ?></h2>

        <div class="actions" style="margin-bottom:20px;">
			<a href="SC.php" class="btn btn-secondary">
				<i class="fas fa-arrow-left"></i> Back to SC List
			</a>
			<a href="sc_export.php?item_id=<?php echo $item_id; ?>" class="btn btn-success">
				<i class="fas fa-file-pdf"></i> Export PDF
			</a>

            <?php if (!empty($history_rows)): ?>
                <a href="view_sc.php?item_id=<?= $item_id ?>&clear_history=1" 
                   class="btn btn-danger"
                   id="clearHistoryBtn"
                   data-item-id="<?= $item_id ?>"
                   onclick="return confirm('Are you sure you want to delete this item\'s history?')">
                    <i class="fas fa-trash"></i> Clear History
                </a>
            <?php endif; ?>
        </div>

        <h3><i class="fas fa-info-circle"></i> Item Details</h3>
        <div class="ris-details">
            <p><strong>Entity Name:</strong> <span><?php echo "TESDA"; ?></span></p>
            <p><strong>Item:</strong> <span><?php echo htmlspecialchars($items['item_name']); ?></span></p>
            <p><strong>Description:</strong> <span><?php echo htmlspecialchars($items['description']); ?></span></p>
            <p><strong>Quantity:</strong> <span><?php echo htmlspecialchars($items['quantity_on_hand']); ?></span></p>
            <p><strong>Stock No.:</strong> <span><?php echo htmlspecialchars($items['stock_number']); ?></span></p>
            <p><strong>Unit of Measurement:</strong> <span><?php echo htmlspecialchars($items['unit']); ?></span></p>
            <p><strong>Re-order Point:</strong> <span><?php echo htmlspecialchars($items['reorder_point']); ?></span></p>
            <p><strong>Date:</strong> <span><?php echo date('m/d/Y'); ?></span></p>
        </div>

        <h3><i class="fas fa-history"></i> Item History</h3>
        <div class="ris-details">
            <?php if (count($history_rows) > 0): ?>
                <div class="history-table">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-calendar"></i> Date</th>
                                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                                    <th><i class="fas fa-tag"></i> Item</th>
                                    <th><i class="fas fa-align-left"></i> Description</th>
                                    <th><i class="fas fa-ruler"></i> Unit</th>
                                    <th><i class="fas fa-exclamation-triangle"></i> Reorder Point</th>
                                    <th><i class="fas fa-dollar-sign"></i> Unit Cost (₱)</th>
                                    <th><i class="fas fa-cubes"></i> Quantity</th>
                                    <th><i class="fas fa-exchange-alt"></i> Qty Change</th>
                                    <th><i class="fas fa-list"></i> Type</th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php 
                            // Map change_type to more readable text
                            $change_labels = [
                                'add' => 'Added New Item',
                                'entry' => 'New Stock Entry',
                                'update' => 'Updated Item',
                                'cleared' => 'Cleared History',
                                'delete' => 'Deleted Item',
                                'issue' => 'Issued Item',
                            ];
                        
                                    foreach ($history_rows as $h): 
                                        $type_label = $change_labels[$h['change_type']] ?? ucfirst($h['change_type']);
                                    ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($h['changed_at'])) ?></td>
                                        <td><?= htmlspecialchars($h['stock_number']) ?></td>
                                        <td><?= htmlspecialchars($h['item_name']) ?></td>
                                        <td><?= htmlspecialchars($h['description']) ?></td>
                                        <td><?= htmlspecialchars($h['unit']) ?></td>
                                        <td><?= htmlspecialchars($h['reorder_point']) ?></td>
                                        <td><?= number_format($h['unit_cost'], 2) ?></td>
                                        <td><?= htmlspecialchars($h['quantity_on_hand']) ?></td>
                                        <td style="color: <?= $h['quantity_change'] > 0 ? '#10b981' : ($h['quantity_change'] < 0 ? '#ef4444' : '#6b7280') ?>; font-weight: 600;">
                                            <?= $h['quantity_change'] > 0 ? '+' : '' ?><?= $h['quantity_change'] ?>
                                        </td>
                                        <td><?= htmlspecialchars($type_label) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p class="empty-state">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                    No history found for this item.
                </p>
            <?php endif; ?>

        </div>

    </div>

<script src="js/view_ris_script.js?v=<?= time() ?>"></script>
</body>
</html>