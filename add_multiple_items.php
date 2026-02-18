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
$items = $conn->query("SELECT i.*, 
    CASE 
        WHEN i.calculated_unit_cost IS NOT NULL THEN i.calculated_unit_cost
        WHEN (i.initial_quantity > 0 AND (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) > 0)
        THEN ((i.initial_quantity * i.unit_cost) + COALESCE((SELECT SUM(ie.quantity * ie.unit_cost) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0)) / (i.initial_quantity + COALESCE((SELECT SUM(ie.quantity) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0))
        ELSE i.unit_cost 
    END as display_unit_cost
    FROM items i 
    ORDER BY i.item_name ASC");
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
        .filter-container {
            margin-bottom: 20px;
        }
        .filter-dropdown {
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }
        .filter-dropdown:focus {
            outline: none;
            border-color: #28a745;
        }
        .hidden-row {
            display: none;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>Restock Inventory</h2>

    <!-- FILTER DROPDOWN -->
    <div class="filter-container">
        <select id="stockFilter" class="filter-dropdown">
            <option value="all">Show All Items</option>
            <option value="low-stock">Show Low-Stock Items</option>
        </select>
    </div>

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
                    <th>IAR</th>
                    <th style="text-align:center;">Qty to Add</th>
                    <th>Unit Cost</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $items->fetch_assoc()): ?>
                    <tr data-quantity="<?= $row['quantity_on_hand'] ?>" data-reorder-point="<?= $row['reorder_point'] ?>">
                        <td><?= htmlspecialchars($row['stock_number']) ?></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['unit']) ?></td>
                        <td><?= htmlspecialchars($row['quantity_on_hand']) ?></td>

                        <td><?= htmlspecialchars($row['iar']) ?></td>

                        <!-- HIDDEN FIELD FOR STOCK NUMBER -->
                        <input type="hidden" name="stock_number[]" value="<?= $row['stock_number'] ?>">

                        <td style="text-align:center;">
                            <input 
                                type="number" 
                                name="quantity_on_hand[]" 
                                min="0" 
                                placeholder="0"
                            >
                        </td>

                        <td>
                            <input 
                                type="number" 
                                name="unit_cost[]" 
                                value="<?= number_format($row['display_unit_cost'], 2, '.', '') ?>"
                                step="0.01"
                                min="0"
                                style="width: 100px;"
                                placeholder="Unit Cost"
                            >
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>

        </table>

        <button class="save-btn" type="submit">Save Restock Entries</button>
    </form>
</div>

<script>
    const filterDropdown = document.getElementById('stockFilter');
    const tableRows = document.querySelectorAll('tbody tr');
    
    filterDropdown.addEventListener('change', function() {
        const filterValue = this.value;
        
        tableRows.forEach(row => {
            const quantity = parseInt(row.getAttribute('data-quantity'));
            const reorderPoint = parseInt(row.getAttribute('data-reorder-point'));
            
            if (filterValue === 'all') {
                row.classList.remove('hidden-row');
            } else if (filterValue === 'low-stock') {
                // Show items at or below their reorder point
                if (quantity <= reorderPoint) {
                    row.classList.remove('hidden-row');
                } else {
                    row.classList.add('hidden-row');
                }
            }
        });
    });
</script>

</body>
</html>