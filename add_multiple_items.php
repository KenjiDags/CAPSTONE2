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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restock Items - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="css/PPE.css?v=<?= time() ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Table styling specific to restock page */
        .table-container {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        thead {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }
        th {
            padding: 14px 12px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        th i {
            margin-right: 6px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        tbody tr {
            transition: background-color 0.2s ease;
        }
        tbody tr:hover {
            background-color: #f9fafb;
        }
        
        /* Input styling */
        input[type="number"] {
            padding: 8px 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100px;
        }
        input[type="number"]:hover {
            border-color: #cbd5e1;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Hidden row */
        .hidden-row {
            display: none;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="container">
    <div class="form-container">
        <header class="page-header">
            <h1>
                <i class="fas fa-boxes"></i>
                Restock Inventory
            </h1>
        </header>

        <!-- FILTER DROPDOWN -->
        <div class="filter-container">
            <label for="stockFilter">
                <i class="fas fa-filter"></i> Filter Items:
            </label>
            <select id="stockFilter" class="filter-dropdown">
                <option value="all">Show All Items</option>
                <option value="low-stock">Show Low-Stock Items Only</option>
            </select>
        </div>

        <!-- FORM NOW SUBMITS TO THIS SAME PAGE -->
        <form method="POST">

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-barcode"></i> Stock #</th>
                            <th><i class="fas fa-tag"></i> Item Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-ruler"></i> Unit</th>
                            <th><i class="fas fa-cubes"></i> Current Qty</th>
                            <th><i class="fas fa-file-invoice"></i> IAR</th>
                            <th style="text-align:center;"><i class="fas fa-plus-circle"></i> Qty to Add</th>
                            <th><i class="fas fa-dollar-sign"></i> Unit Cost</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $items->fetch_assoc()): ?>
                            <tr data-quantity="<?= $row['quantity_on_hand'] ?>" data-reorder-point="<?= $row['reorder_point'] ?>">
                                <td><strong><?= htmlspecialchars($row['stock_number']) ?></strong></td>
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
                                        placeholder="Unit Cost"
                                    >
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>

                </table>
            </div>

                <button class="pill-btn pill-add" type="submit">
                    <i class="fas fa-plus"></i>
                    Save Entries
                </button>
                <a href="inventory.php">
                    <button type="button" class="pill-btn pill-view">
                        <i class="fas fa-ban"></i>
                        Cancel
                    </button>
                </a>
        </form>
    </div>
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