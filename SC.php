<?php 
    require 'auth.php';
    require 'config.php';

// SORT LOGIC
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'stock_number';
$order_clause = '';

switch ($sort_by) {
    case 'item_name':
        $order_clause = "ORDER BY item_name ASC";
        break;
    case 'quantity_low':
        $order_clause = "ORDER BY quantity_on_hand ASC";
        break;
    case 'quantity_high':
        $order_clause = "ORDER BY quantity_on_hand DESC";
        break;
    case 'reorder_point':
        $order_clause = "ORDER BY reorder_point DESC";
        break;
    case 'stock_number':
    default:
        $order_clause = "ORDER BY stock_number ASC";
        break;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (stock_number LIKE '%$esc%' OR item_name LIKE '%$esc%' OR description LIKE '%$esc%' OR unit LIKE '%$esc%' OR iar LIKE '%$esc%')";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Card - TESDA Inventory System</title>
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
        }

        .clickable-row {
            cursor: pointer;
        }

        .clickable-row:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>Stock Card (SC)</h2>

    <form id="sc-filters" method="get" class="filters">
        <div class="control">
            <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                <i class="fas fa-sort"></i> Sort by:
            </label>
            <select id="sort-select" name="sort" onchange="this.form.submit()">
                <option value="stock_number" <?= ($sort_by == 'stock_number') ? 'selected' : '' ?>>Stock Number (A-Z)</option>
                <option value="item_name" <?= ($sort_by == 'item_name') ? 'selected' : '' ?>>Item Name (A-Z)</option>
                <option value="quantity_low" <?= ($sort_by == 'quantity_low') ? 'selected' : '' ?>>Quantity (Low to High)</option>
                <option value="quantity_high" <?= ($sort_by == 'quantity_high') ? 'selected' : '' ?>>Quantity (High to Low)</option>
                <option value="reorder_point" <?= ($sort_by == 'reorder_point') ? 'selected' : '' ?>>Reorder Point (High to Low)</option>
            </select>
        </div>
        <div class="control" style="flex: 1; display: flex; align-items: center; gap: 10px; justify-content: flex-start; padding-left: 20px;">
            <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                <i class="fas fa-search"></i> Search:
            </label>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by stock number, item, description, or unit..." />
        </div>
        <div class="control" style="display: flex; align-items: center; gap: 10px;">
            <a href="sc_export_all.php" class="pill-btn pill-export" title="Export All Items" style=" border-radius: 8px !important;">
                <i class="fas fa-file-export"></i> Export All
            </a>
        </div>
    </form>

    <div class="table-container">
        <table id="scTable">
        <thead>
            <tr>
                <th><i class="fas fa-barcode"></i> Stock No.</th>
                <th><i class="fas fa-file-invoice"></i> I.A.R</th>
                <th><i class="fas fa-tag"></i> Item</th>
                <th><i class="fas fa-align-left"></i> Description</th>
                <th><i class="fas fa-ruler"></i> Unit of Measurement</th>
                <th><i class="fas fa-cubes"></i> Quantity</th>
                <th><i class="fas fa-exclamation-triangle"></i> Reorder Point</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $sql = "SELECT * FROM items $whereClause $order_clause";
                $result = $conn->query($sql);            
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr data-id='{$row['item_id']}' data-view-url='view_sc.php?item_id={$row['item_id']}' class='clickable-row'>
                            <td><strong>{$row['stock_number']}</strong></td>
                            <td>{$row['iar']}</td>
                            <td>{$row['item_name']}</td>
                            <td>{$row['description']}</td>
                            <td>{$row['unit']}</td>
                            <td>{$row['quantity_on_hand']}</td>
                            <td>{$row['reorder_point']}</td>
                            <td>
                                <a class='scexport' href='sc_export.php?item_id={$row['item_id']}' title='Export SC'>
                                    <i class='fas fa-download'></i> Export
                                </a>
                            
                            </td>
                            
                        </tr>";
                }
            } else {
                echo '<tr><td colspan="5">
                        <i class="fas fa-inbox"></i> Item not found.
                      </td></tr>';
            }
            ?>
        </tbody>
    </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#scTable tbody tr[data-view-url]');

    function filterRows() {
        if (!searchInput) {
            return;
        }

        const term = searchInput.value.toLowerCase().trim();

        rows.forEach(function(row) {
            const stockNo = row.children[0] ? row.children[0].textContent.toLowerCase() : '';
            const iar = row.children[1] ? row.children[1].textContent.toLowerCase() : '';
            const item = row.children[2] ? row.children[2].textContent.toLowerCase() : '';
            const description = row.children[3] ? row.children[3].textContent.toLowerCase() : '';
            const unit = row.children[4] ? row.children[4].textContent.toLowerCase() : '';

            const isMatch = term === '' ||
                stockNo.includes(term) ||
                iar.includes(term) ||
                item.includes(term) ||
                description.includes(term) ||
                unit.includes(term);

            row.style.display = isMatch ? '' : 'none';
        });
    }

    if (searchInput) {
        if (searchInput.value.length > 0) {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }

        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        searchInput.addEventListener('input', filterRows);
        filterRows();
    }

    rows.forEach(function(row) {
        row.addEventListener('click', function(event) {
            if (event.target.closest('a, button, input, select, textarea')) {
                return;
            }

            const viewUrl = row.getAttribute('data-view-url');
            if (viewUrl) {
                window.location.href = viewUrl;
            }
        });
    });
});
</script>

</body>
</html>