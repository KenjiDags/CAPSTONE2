<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
require_once 'inventory_count_cache.php';
invalidateInventoryCountAfterWrite();

// ---------- PROCESS RESTOCK WHEN FORM IS SUBMITTED ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    restockItems($conn, $_POST); // one clean function call
    // Redirect to inventory.php after saving entries
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
        /* An explicit shell keeps navigation and the scrollable page side by side. */
        body.restock-page {
            margin: 0 !important;
            width: 100%;
        }
        .restock-page .container {
            flex: 1 1 0%;
            width: 0;
            min-width: 0;
            padding: clamp(12px, 2vw, 28px);
        }
        .restock-page .form-container {
            width: 100%;
            max-width: none;
            min-width: 0;
            padding: clamp(12px, 2vw, 30px);
        }
        /* Keep horizontal scrolling inside the table on narrow screens. */
        .restock-page .form-container,
        .restock-page form,
        .restock-page .table-container {
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }
        .restock-page .table-container {
            width: 100%;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow-x: auto;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }
        .restock-page .filter-container > * { min-width: 0; max-width: 100%; }
        .restock-page .page-header h1 { flex-wrap: wrap; overflow-wrap: anywhere; }
        table {
            width: 100%;
            min-width: 1250px;
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
            white-space: nowrap;
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
        .restock-page .filter-container { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        #restockSearch { flex: 1 1 280px; min-width: 0; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        #restockSearch:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        body.dark-mode {
            background: var(--background-gradient) !important;
            color: #e2e8f0;
        }
        body.dark-mode .container {
            background: transparent !important;
        }
        body.dark-mode .form-container {
            background: #1e293b !important;
            color: #e2e8f0;
        }
        body.dark-mode .page-header h1,
        body.dark-mode .filter-container label {
            color: #e2e8f0 !important;
        }
        body.dark-mode table {
            background: #1e293b;
            color: #e2e8f0;
        }
        body.dark-mode td {
            border-bottom-color: #334155;
            color: #e2e8f0;
        }
        body.dark-mode tbody tr:hover {
            background-color: #334155;
        }
        body.dark-mode input[type="number"],
        body.dark-mode #restockSearch,
        body.dark-mode .filter-dropdown {
            background-color: #0f172a;
            border-color: #475569;
            color: #e2e8f0;
        }
    </style>
</head>
<body class="restock-page">
<div class="app-layout restock-layout">
<?php require 'sidebar.php'; ?>

<main class="container app-main">
    <div class="form-container">
        <header class="page-header">
            <h1>
                <i class="fas fa-boxes" aria-hidden="true"></i>
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
            <label for="restockSearch">Search items:</label>
            <input id="restockSearch" type="search" placeholder="Stock number, item name, or description..." autocomplete="off" aria-controls="restockTable">
        </div>
        <p id="restockNoResults" role="status" hidden>No items match your search or stock filter.</p>

        <!-- FORM NOW SUBMITS TO THIS SAME PAGE -->
        <form method="POST">

            <div class="table-container" tabindex="0" role="region" aria-label="Restock inventory table, scroll horizontally to see all columns">
                <table id="restockTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-barcode"></i> Stock #</th>
                            <th><i class="fas fa-tag"></i> Item Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-ruler"></i> Unit</th>
                            <th><i class="fas fa-cubes"></i> Current Qty</th>
                            <th style="text-align:center;"><i class="fas fa-plus-circle"></i> Qty to Add</th>
                            <th><i class="fas fa-dollar-sign"></i> Unit Cost</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $items->fetch_assoc()): ?>
                            <tr data-item-id="<?= (int)$row['item_id'] ?>" data-quantity="<?= $row['quantity_on_hand'] ?>" data-reorder-point="<?= $row['reorder_point'] ?>">
                                <td><strong><?= htmlspecialchars($row['stock_number']) ?></strong></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><?= htmlspecialchars($row['unit']) ?></td>
                                <td><?= htmlspecialchars($row['quantity_on_hand']) ?></td>

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
</main>
</div>

<script>
    const filterDropdown = document.getElementById('stockFilter');
    const searchInput = document.getElementById('restockSearch');
    const tableRows = document.querySelectorAll('#restockTable tbody tr');
    const searchableRows = new Map(Array.from(tableRows, row => [row,
        Array.from(row.cells).slice(0, 3).map(cell => cell.textContent).join(' ').toLowerCase()
    ]));

    function filterRestockItems() {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;
        tableRows.forEach(row => {
            const matchesStock = filterDropdown.value === 'all' || Number(row.dataset.quantity) <= Number(row.dataset.reorderPoint);
            const visible = matchesStock && searchableRows.get(row).includes(query);
            row.classList.toggle('hidden-row', !visible);
            if (visible) visibleCount++;
        });
        document.getElementById('restockNoResults').hidden = visibleCount > 0;
    }
    filterDropdown.addEventListener('change', filterRestockItems);
    searchInput.addEventListener('input', filterRestockItems);
    filterRestockItems();
    // Deep links from MRP focus the requested item without submitting stock changes.
    const selectedItemId = new URLSearchParams(window.location.search).get('item_id');
    const selectedRow = Array.from(tableRows).find(row => row.dataset.itemId === selectedItemId);
    if (selectedRow) {
        selectedRow.style.outline = '2px solid #2563eb';
        selectedRow.scrollIntoView({ block: 'center' });
        selectedRow.querySelector('input[name="quantity_on_hand[]"]')?.focus({ preventScroll: true });
    }
</script>

</body>
</html>
