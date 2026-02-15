<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

// ---------- PROCESS RESTOCK WHEN FORM IS SUBMITTED ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    restockItems($conn, $_POST); // one clean function call

    header("Location: inventory.php?success=restocked");
    exit;
}

// ---------- FETCH ALL ITEMS FOR DISPLAY ----------
$items = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Restock Items</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        input[type="number"] {
            width: 80px;
            padding: 5px;
        }
        .save-btn {
            margin-top: 20px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        .save-btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>Restock Inventory</h2>
    <p>Enter the quantity you want to add for each item.</p>

    <!-- FORM NOW SUBMITS TO THIS SAME PAGE -->
    <form method="POST">

        <table>
            <thead>
                <tr>
                    <th>Stock #</th>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Unit</th>
                    <th>Current Qty</th>
                    <th>Unit Cost</th>
                    <th>IAR</th>
                    <th style="text-align:center;">Qty to Add</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['stock_number']) ?></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['unit']) ?></td>
                        <td><?= htmlspecialchars($row['quantity_on_hand']) ?></td>
                        <td><?= htmlspecialchars($row['unit_cost']) ?></td>
                        <td><?= htmlspecialchars($row['iar']) ?></td>

                        <!-- HIDDEN FIELDS USED BY restockItems() -->
                        <input type="hidden" name="stock_number[]" value="<?= $row['stock_number'] ?>">
                        <input type="hidden" name="unit_cost[]" value="<?= $row['unit_cost'] ?>">

                        <td style="text-align:center;">
                            <input 
                                type="number" 
                                name="quantity_on_hand[]" 
                                min="0" 
                                placeholder="0"
                            >
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>

        </table>

        <button class="save-btn" type="submit">Save Restock Entries</button>
    </form>
</div>
</body>
</html>