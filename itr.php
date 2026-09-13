<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

// Handle delete BEFORE any output so redirects work
if (isset($_GET['delete_itr_id'])) {
    $del_id = (int)$_GET['delete_itr_id'];
    try {
        // Ensure tables exist defensively
        @$conn->query("DELETE FROM itr_items WHERE itr_id = $del_id");
        @$conn->query("DELETE FROM itr WHERE itr_id = $del_id");
    } catch (Throwable $e) {
        // swallow errors to avoid breaking UI; deletion attempt made
    }
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header('Location: itr.php' . $sortParam);
    exit();
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITR - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f0ec";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
  .filters { margin-bottom:12px; display:flex; gap:12px; align-items:center; flex-wrap: wrap; }
  .filters .control { display:flex; align-items:center; gap:10px; }
  .filters select, .filters input {
    height: 38px;
    padding: 8px 14px;
    border-radius: 9999px;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    color: #111827;
    font-size: 14px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
  }
  .filters input::placeholder { color: #9ca3af; }
  .filters select:hover, .filters input:hover { background-color: #ffffff; }
  .filters select:focus, .filters input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    background-color: #ffffff;
  }
  .filters select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    padding-right: 38px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px 18px;
  }
  .filters .pill-btn { height: 38px; padding: 0 16px; }
  .filters #searchInput { width: 400px; max-width: 65vw; }
    .clickable-row { cursor: pointer; }
    .clickable-row:hover { background: #f8fafc; }

/* Actions Menu */
.actions-cell {
    display: table-cell !important;
    width: auto !important;
    min-width: 70px !important;
    text-align: left !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
}

body.dark-mode .actions-cell {
    background: #1e293b !important;
}

.actions-menu {
    position: relative;
    display: inline-block;
}

.actions-menu-toggle {
    width: 30px;
    height: 30px;
    padding: 0;
    border: 0;
    border-radius: 5px;
    background: #fff;
    color: #6b7280;
    cursor: pointer;
}

.actions-menu-toggle:hover,
.actions-menu.is-open .actions-menu-toggle {
    color: #2563eb;
}

.actions-menu-list {
    display: none;
    position: fixed;
    z-index: 10000;
    min-width: 120px;
    padding: 4px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 6px 16px rgba(15, 23, 42, .12);
}

.actions-menu.is-open .actions-menu-list {
    display: block;
}

.actions-menu-list button,
.actions-menu-list a {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 8px;
    padding: 7px 8px;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: #374151;
    cursor: pointer;
    font-size: 12px;
    text-align: left;
    text-decoration: none;
}

.actions-menu-list button:hover,
.actions-menu-list a:hover {
    background: #f3f4f6;
}

.actions-menu-list .delete-action {
    color: #dc2626;
}
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>Inventory Transfer Report (ITR)</h2>

    <?php // (delete handled before output) ?>

  <form id="itr-filters" method="get" class="filters">
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1;">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <?php $sort = $_GET['sort'] ?? 'date_newest'; ?>
          <option value="date_newest" <?= ($sort == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
          <option value="itr_no" <?= ($sort == 'itr_no') ? 'selected' : '' ?>>ITR No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
        </select>
      </div>
        <div class="control">
            <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
            <i class="fas fa-search"></i> Search:
            </label>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search description or ITR no..." />
        </div>
    </div> 
        <div style="margin-left:auto;">
            <a href="add_itr.php" class="pill-btn pill-add" style="border-radius: 8px !important;">
            <i class="fas fa-plus"></i> Add ITR Form
            </a>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> ITR No.</th>
                <th><i class="fas fa-calendar"></i> Date</th>
                <th><i class="fas fa-user"></i> From</th>
                <th><i class="fas fa-user"></i> To</th>
                <th><i class="fas fa-dollar-sign"></i> Total Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Check if tables exist
            $hasItr = false;
            try {
                $res = $conn->query("SHOW TABLES LIKE 'itr'");
                $hasItr = $res && $res->num_rows > 0;
                if ($res) { $res->close(); }
            } catch (Throwable $e) { $hasItr = false; }

            if ($hasItr) {
                $sort = $_GET['sort'] ?? 'date_newest';
                $order = 'i.itr_date DESC, i.itr_id DESC';
                if ($sort === 'date_oldest') $order = 'i.itr_date ASC, i.itr_id ASC';
                if ($sort === 'itr_no') $order = 'i.itr_no ASC';
                // amount sorting computed after join
                $orderAmount = '';
                if ($sort === 'amount_highest') $orderAmount = ' ORDER BY total_amount DESC';
                if ($sort === 'amount_lowest') $orderAmount = ' ORDER BY total_amount ASC';

                $whereClause = '';
                if ($search !== '') {
                    $esc = $conn->real_escape_string($search);
                    $whereClause = " WHERE (i.itr_no LIKE '%$esc%' OR i.from_accountable LIKE '%$esc%' OR i.to_accountable LIKE '%$esc%')";
                }

                $sql = "SELECT i.*, IFNULL(SUM(it.amount),0) AS total_amount
                        FROM itr i
                        LEFT JOIN itr_items it ON it.itr_id = i.itr_id
                        $whereClause
                        GROUP BY i.itr_id
                        ";
                // Default order by date unless amount-specific requested
                if ($orderAmount) {
                    $sql .= $orderAmount;
                } else {
                    $sql .= " ORDER BY $order";
                }

                $rs = $conn->query($sql);
                if ($rs && $rs->num_rows > 0) {
                    while ($row = $rs->fetch_assoc()) {
                        echo '<tr class="clickable-row" data-view-url="view_itr.php?itr_id=' . (int)$row['itr_id'] . '">';
                        echo '<td>' . htmlspecialchars($row['itr_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['itr_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['from_accountable']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['to_accountable']) . '</td>';
                    echo '<td class="currency">₱' . number_format((float)$row['total_amount'], 2) . '</td>';
                    echo '<td class="actions-cell">';
                    echo '    <div class="actions-menu">';
                    echo '        <button type="button" class="actions-menu-toggle" aria-label="Open actions" aria-expanded="false">';
                    echo '            <i class="fas fa-ellipsis-v"></i>';
                    echo '        </button>';

                    echo '        <div class="actions-menu-list">';

                    echo '            <a href="view_itr.php?itr_id=' . (int)$row['itr_id'] . '" class="view-action">';
                    echo '                <i class="fas fa-eye"></i> View';
                    echo '            </a>';

                    echo '            <a href="edit_itr.php?itr_id=' . (int)$row['itr_id'] . '" class="edit-action">';
                    echo '                <i class="fas fa-edit"></i> Edit';
                    echo '            </a>';

                    echo '            <a href="export_itr.php?itr_id=' . (int)$row['itr_id'] . '" class="export-action">';
                    echo '                <i class="fas fa-download"></i> Export';
                    echo '            </a>';

                    echo '            <a href="itr.php?delete_itr_id=' . (int)$row['itr_id'] .
                        (isset($_GET['sort']) ? ('&sort=' . urlencode($_GET['sort'])) : '') . '" ' .
                        'class="delete-action" ' .
                        'onclick="return confirm(\'Are you sure you want to delete this ITR?\')">';
                    echo '                <i class="fas fa-trash"></i> Delete';
                    echo '            </a>';

                    echo '        </div>';
                    echo '    </div>';
                    echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6" style="text-align:center; padding:16px;"><i class="fas fa-inbox"></i> No ITR records found.</td></tr>';
                }
                if ($rs) { $rs->close(); }
            } else {
                echo '<tr><td colspan="6" style="text-align:center; padding:16px;"><i class="fas fa-inbox"></i> No ITR records found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('tr.clickable-row[data-view-url]');

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

    function positionActionsMenu(menu, menuList) {
        const toggleRect = menu.querySelector('.actions-menu-toggle').getBoundingClientRect();

        menuList.style.right = 'auto';
        menuList.style.left = `${Math.max(4, toggleRect.right - 120)}px`;
        menuList.style.top = `${toggleRect.bottom + 4}px`;

        const listRect = menuList.getBoundingClientRect();

        // If there isn't enough room below, open upward
        if (listRect.bottom > window.innerHeight - 4) {
            menuList.style.top = `${Math.max(4, toggleRect.top - listRect.height - 4)}px`;
        }
    }

    function closeActionsMenu(menu) {
        const menuList = menu._actionsMenuList || menu.querySelector('.actions-menu-list');

        menuList.style.display = '';
        menuList.style.left = '';
        menuList.style.right = '';
        menuList.style.top = '';

        menu.appendChild(menuList);
        menu.classList.remove('is-open');

        const toggle = menu.querySelector('.actions-menu-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('click', function(event) {
        const menuToggle = event.target.closest('.actions-menu-toggle');

        if (menuToggle) {
            event.stopPropagation();

            const menu = menuToggle.closest('.actions-menu');

            // Close any other open action menus
            document.querySelectorAll('.actions-menu.is-open').forEach(function(openMenu) {
                if (openMenu !== menu) {
                    closeActionsMenu(openMenu);
                }
            });

            const isOpen = menu.classList.toggle('is-open');

            if (isOpen) {
                const menuList = menu.querySelector('.actions-menu-list');

                menu._actionsMenuList = menuList;

                menuList.style.display = 'block';

                // Move the menu outside the table so it isn't clipped
                document.body.appendChild(menuList);

                positionActionsMenu(menu, menuList);
            } else {
                closeActionsMenu(menu);
            }

            menuToggle.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );

            return;
        }

        // Close menu when clicking elsewhere
        if (!event.target.closest('.actions-menu') &&
            !event.target.closest('.actions-menu-list')) {

            document.querySelectorAll('.actions-menu.is-open').forEach(function(menu) {
                closeActionsMenu(menu);
            });
        }
    });
</script>

</body>
</html>
