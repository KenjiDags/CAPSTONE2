<?php
require_once __DIR__ . '/bootstrap.php'; 
    require dirname(__DIR__) . '/auth.php';
    require dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/archive_helpers.php';
    if (empty($_SESSION['archive_csrf'])) $_SESSION['archive_csrf'] = bin2hex(random_bytes(32));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sc_id'])) {
        if (!hash_equals($_SESSION['archive_csrf'], (string)($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Invalid request. Reload the page and try again.');
        }
        $id = filter_var($_POST['delete_sc_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        try {
            if (!$id) throw new InvalidArgumentException('Invalid stock card.');
            archiveRecord($conn, 'SC', $id, false);
            $_SESSION['sc_notice'] = 'Stock card history moved to Archives. You can restore it there. The supply item and quantity are preserved.';
        } catch (Throwable $error) {
            error_log('SC archive failed: ' . $error->getMessage());
            $_SESSION['sc_notice'] = 'Unable to archive the stock card. Please reload and try again.';
        }
        header('Location: SC.php?' . http_build_query([
            'search' => (string)($_POST['search'] ?? ''),
            'sort' => (string)($_POST['sort'] ?? 'stock_number'),
        ]));
        exit;
    }
    $notice = $_SESSION['sc_notice'] ?? null;
    unset($_SESSION['sc_notice']);
    $csrf = htmlspecialchars($_SESSION['archive_csrf'], ENT_QUOTES, 'UTF-8');

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
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Card - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

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
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <h2>Stock Card (SC)</h2>
    <?php if ($notice !== null): ?>
        <p role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form id="sc-filters" method="get" class="filters">

        <div class="inventory-controls">

            <!-- Sort By -->
            <div class="control-sort">
                <label for="sort-select">
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

            <!-- Search -->
            <div class="control-search">
                <label for="searchInput">
                    <i class="fas fa-search"></i> Search:
                </label>

                <input
                    type="text"
                    id="searchInput"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search by stock number, item, description, or unit..."
                />
            </div>

            <!-- Actions -->
            <div class="control-actions">
                <a href="office_supplies/sc_export_all.php" class="pill-btn pill-export" title="Export All Items" style="border-radius: 8px !important;">
                    <i class="fas fa-file-export"></i> Export All
                </a>
            </div>

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
                $deleteSearch = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
                $deleteSort = htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8');

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='sc-item-row'>
                            <td><strong>{$row['stock_number']}</strong></td>
                            <td>{$row['iar']}</td>
                            <td>{$row['item_name']}</td>
                            <td>{$row['description']}</td>
                            <td>{$row['unit']}</td>
                            <td>{$row['quantity_on_hand']}</td>
                            <td>{$row['reorder_point']}</td>

                            <td class='actions-cell'>
                                <div class='actions-menu'>
                                    <button type='button' class='actions-menu-toggle' aria-label='Open actions' aria-expanded='false'>
                                        <i class='fas fa-ellipsis-v'></i>
                                    </button>

                                    <div class='actions-menu-list'>
                                        <a href='office_supplies/view_sc.php?item_id={$row['item_id']}' class='view-action'>
                                            <i class='fas fa-eye'></i> View
                                        </a>

                                        <a href='office_supplies/sc_export.php?item_id={$row['item_id']}' class='export-action'>
                                            <i class='fas fa-download'></i> Export
                                        </a>
                                        <form method='post' action='office_supplies/SC.php' onsubmit='return confirm(&quot;Delete this stock card history and move it to Archives? The supply item and quantity will be preserved.&quot;)'>
                                            <input type='hidden' name='delete_sc_id' value='{$row['item_id']}'>
                                            <input type='hidden' name='csrf' value='{$csrf}'>
                                            <input type='hidden' name='search' value='{$deleteSearch}'>
                                            <input type='hidden' name='sort' value='{$deleteSort}'>
                                            <button type='submit' class='delete-action'><i class='fas fa-trash'></i> Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </td>

                        </tr>";
                }
            } else {
                echo '<tr><td colspan="8">
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
    const rows = document.querySelectorAll('#scTable tbody tr.sc-item-row');

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

});
</script>

<script src="js/actions_menu.js"></script>

</body>
</html>