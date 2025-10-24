<?php ob_start(); include 'sidebar.php'; ?>
<?php require 'config.php'; ?>

<?php
require 'functions.php';

// Handle form submission (Edit only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (method_exists($conn, 'begin_transaction')) { $conn->begin_transaction(); }
    try {
        // Force edit mode
        if (!isset($_POST['ics_id']) || empty($_POST['ics_id'])) {
            throw new Exception('ICS ID is required for editing.');
        }
        $is_editing = true;
        $ics_id = (int)$_POST['ics_id'];

        // ICS Header fields
        $ics_no = $_POST['ics_no'];
        $entity_name = $_POST['entity_name'];
        $fund_cluster = $_POST['fund_cluster'];
        $date_issued = $_POST['date_issued'];
        $received_by = $_POST['received_by'];
        $received_by_position = $_POST['received_by_position'];
        $received_from = $_POST['received_from'];
        $received_from_position = $_POST['received_from_position'];

        // Update existing ICS header
        $stmt = $conn->prepare("UPDATE ics SET entity_name = ?, fund_cluster = ?, date_issued = ?, 
                               received_by = ?, received_by_position = ?, received_from = ?, received_from_position = ? 
                               WHERE ics_id = ?");
        $stmt->bind_param("sssssssi", $entity_name, $fund_cluster, $date_issued, 
                         $received_by, $received_by_position, $received_from, $received_from_position, $ics_id);
        if (!$stmt->execute()) { throw new Exception('Failed to update ICS header: ' . $stmt->error); }
        $stmt->close();

        // Load previous items for reversal of semi balances
        $old_items = [];
        $stmt = $conn->prepare("SELECT stock_number, quantity, unit_cost FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        if (!$stmt->execute()) { throw new Exception('Failed to load old ICS items: ' . $stmt->error); }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $old_items[$row['stock_number']] = [
                'qty' => (float)$row['quantity'],
                'unit_cost' => isset($row['unit_cost']) ? (float)$row['unit_cost'] : null,
            ];
        }
        $stmt->close();

        // Delete old items
        $stmt = $conn->prepare("DELETE FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        if (!$stmt->execute()) { throw new Exception('Failed to delete old ICS items: ' . $stmt->error); }
        $stmt->close();

        // Reverse previous issuances in semi table
        foreach ($old_items as $stock_no => $old) {
            $stmt = $conn->prepare("SELECT id, quantity, quantity_issued, quantity_reissued, quantity_disposed, amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
            $stmt->bind_param("s", $stock_no);
            if (!$stmt->execute()) { throw new Exception('Failed to load semi-expendable for reversal: ' . $stmt->error); }
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $semi_id = (int)$row['id'];
                $qty = (int)$row['quantity'];
                $issued = max(0, (int)$row['quantity_issued'] - (int)$old['qty']);
                $reissued = (int)$row['quantity_reissued'];
                $disposed = (int)$row['quantity_disposed'];
                $balance = max(0, $qty - ($issued + $reissued + $disposed));
                $u = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_balance = ? WHERE id = ?");
                $u->bind_param("iii", $issued, $balance, $semi_id);
                if (!$u->execute()) { $u->close(); throw new Exception('Failed to restore semi-expendable stock: ' . $u->error); }
                $u->close();
                // Removed reversal history snapshot per request
            }
        }

        // Arrays from form
        $stock_numbers = $_POST['stock_number'];
        $issued_quantities = $_POST['issued_quantity'];
        $estimated_useful_lives = $_POST['estimated_useful_life'];
        $serial_numbers = $_POST['serial_number'];

        // Re-insert current items and update semi balances
        for ($i = 0; $i < count($stock_numbers); $i++) {
            $stock_no = $stock_numbers[$i];
            $issued_qty = (float)$issued_quantities[$i];
            $useful_life = $estimated_useful_lives[$i];
            $serial_no = $serial_numbers[$i];

            if ($issued_qty > 0) {
                $stmt = $conn->prepare("SELECT id, item_description, remarks, estimated_useful_life, amount, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance FROM semi_expendable_property WHERE semi_expendable_property_no = ?");
                $stmt->bind_param("s", $stock_no);
                if (!$stmt->execute()) { throw new Exception('Failed to fetch item data: ' . $stmt->error); }
                $result = $stmt->get_result();
                $item_data = $result->fetch_assoc();
                $stmt->close();

                if ($item_data) {
                    $available = (float)$item_data['quantity_balance'];
                    if ($issued_qty > $available) { $issued_qty = $available; }
                    if ($issued_qty <= 0) { continue; }
                    $unit_cost = (float)$item_data['amount'];
                    $total_cost = $issued_qty * $unit_cost;

                    $stmt = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, description, inventory_item_no, estimated_useful_life, serial_number)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $unitVal = '';
                    $descVal = isset($item_data['remarks']) && $item_data['remarks'] !== '' ? $item_data['remarks'] : $item_data['item_description'];
                    $usefulVal = (string)($item_data['estimated_useful_life'] ?? $useful_life);
                    $stmt->bind_param("isdsddssss", $ics_id, $stock_no, $issued_qty, $unitVal, $unit_cost, $total_cost, $descVal, $stock_no, $usefulVal, $serial_no);
                    if (!$stmt->execute()) { throw new Exception('Failed to insert ICS item: ' . $stmt->error); }
                    $stmt->close();

                    $semi_id = (int)$item_data['id'];
                    $qty = (int)$item_data['quantity'];
                    $issued = (int)$item_data['quantity_issued'] + (int)$issued_qty;
                    $reissued = (int)$item_data['quantity_reissued'];
                    $disposed = (int)$item_data['quantity_disposed'];
                    $balance = max(0, $qty - ($issued + $reissued + $disposed));
                    $u = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_balance = ?, ics_rrsp_no = ?, office_officer_issued = ?, fund_cluster = ? WHERE id = ?");
                    $u->bind_param("iisssi", $issued, $balance, $ics_no, $received_by, $fund_cluster, $semi_id);
                    if (!$u->execute()) { $u->close(); throw new Exception('Failed to update semi-expendable stock: ' . $u->error); }
                    $u->close();

                    $amount_total = round($item_data['amount'] * $qty, 2);
                    $h = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $remarks = 'ICS Issued';
                    $h->bind_param("issiiiiisdds", $semi_id, $date_issued, $ics_no, $qty, $issued, $reissued, $disposed, $balance, $received_by, $unit_cost, $amount_total, $remarks);
                    @ $h->execute();
                    $h->close();
                }
            }
        }

        if (method_exists($conn, 'commit')) { $conn->commit(); }
        // Redirect back to ICS list without notifier
        if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_clean(); }
        header('Location: ics.php');
        exit();
    } catch (Throwable $e) {
        if (method_exists($conn, 'rollback')) { $conn->rollback(); }
        echo '<div style="color:#b91c1c; background:#fee2e2; padding:12px; border:1px solid #fca5a5; margin:10px 0;">'
            . 'ICS edit failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Require ICS ID for editing
if (!isset($_GET['ics_id']) || empty($_GET['ics_id'])) {
    header('Location: ics.php');
    exit();
}
$is_editing = true;
$ics_id = (int)$_GET['ics_id'];

// Load existing ICS header and items
$ics_data = [];
$ics_items = [];
$selected_category = 'All';
$category_options = [];

$stmt = $conn->prepare("SELECT * FROM ics WHERE ics_id = ?");
$stmt->bind_param("i", $ics_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $ics_data = $result->fetch_assoc();
    $stmt = $conn->prepare("SELECT * FROM ics_items WHERE ics_id = ?");
    $stmt->bind_param("i", $ics_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    while ($item = $items_result->fetch_assoc()) {
        $ics_items[$item['stock_number']] = $item;
    }
} else {
    header('Location: ics.php');
    exit();
}

if (isset($_GET['filter_category']) && $_GET['filter_category'] !== '') {
    $selected_category = $_GET['filter_category'];
}
if (columnExists($conn, 'semi_expendable_property', 'category')) {
    $catRes = $conn->query("SELECT DISTINCT category FROM semi_expendable_property WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
    if ($catRes) { while ($r = $catRes->fetch_assoc()) { $category_options[] = $r['category']; } }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit ICS Form</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
        .section-card h3 { margin-top:0; margin-bottom:12px; }
        .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        .form-grid .form-group { display:flex; flex-direction:column; }
        @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } }
    /* Frame: remove top border, flatten top corners (avoid white arc) */
    .table-frame { border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px; overflow: hidden; }
    /* Viewport: gradient behind sticky header */
    .table-viewport { max-height: 420px; overflow-y: auto; overflow-x: auto; scrollbar-gutter: stable; background: var(--white); }
    /* Prevent sticky header clipping */
    #itemsTable { overflow: visible !important; width: 100%; border-collapse: collapse; background: transparent !important; border-radius: 0 !important; margin-top: 0 !important; }
    /* Sticky header inside viewport */
    #itemsTable thead th { position: sticky; top: 0; z-index: 3; background: var(--blue-gradient); color: #fff; }
    /* Flatten top radius across elements */
    .table-frame, .table-viewport, #itemsTable { border-top-left-radius: 0 !important; border-top-right-radius: 0 !important; }
    </style>
    </head>
<body>
    <div class="edit-ics-page content edit-ris-page">
        <h2>Edit ICS Form</h2>

        <form method="post" action="">
            <input type="hidden" name="ics_id" value="<?php echo $ics_id; ?>">
            <input type="hidden" name="is_editing" value="1">
            
            <div class="section-card">
                <h3>ICS Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Entity Name:</label>
                        <input type="text" name="entity_name" value="<?php echo htmlspecialchars($ics_data['entity_name'] ?? 'TESDA Regional Office'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fund Cluster:</label>
                        <input type="text" name="fund_cluster" value="<?php echo htmlspecialchars($ics_data['fund_cluster'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ICS No.:</label>
                        <input type="text" name="ics_no" value="<?php echo htmlspecialchars($ics_data['ics_no'] ?? ''); ?>" readonly style="background-color: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label>Date Issued:</label>
                        <input type="date" name="date_issued" value="<?php echo $ics_data['date_issued'] ?? date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <h3>ICS Items</h3>
                <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px; flex-wrap:wrap;">
                    <label for="filter_category" style="font-weight:600; color:#0038a8;">Category:</label>
                    <select id="filter_category" name="filter_category" style="padding:8px 12px; border:2px solid #e8f0fe; border-radius:8px; background:#f8fbff;">
                        <option value="All" <?php echo ($selected_category==='All')?'selected':''; ?>>All</option>
                        <?php foreach ($category_options as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($selected_category===$cat)?'selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="clamp-notice" style="display:none; margin:0 0 10px; padding:8px 10px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; border-radius:4px;"></div>

                <div class="search-container">
                    <input type="text" id="itemSearch" class="search-input" placeholder="Start typing to search items..." onkeyup="filterItems()">
                </div>

                <div class="table-frame">
                    <div class="table-viewport">
                        <table id="itemsTable" tabindex="-1">
                            <thead>
                                <tr>
                                    <th>Stock No.</th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th>Unit</th>
                                    <th>Quantity on Hand</th>
                                    <th>Unit Cost</th>
                                    <th>Issued Qty</th>
                                    <th>Estimated Useful Life</th>
                                    <th>Serial Number</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($selected_category !== 'All' && columnExists($conn, 'semi_expendable_property', 'category')) {
                                    $stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE category = ? ORDER BY date DESC, id DESC");
                                    $stmt->bind_param("s", $selected_category);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $stmt->close();
                                } else {
                                    $result = $conn->query("SELECT * FROM semi_expendable_property ORDER BY date DESC, id DESC");
                                }
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $stock_number = $row['semi_expendable_property_no'];
                                        $existing_item = $ics_items[$stock_number] ?? null;
                                        $qtyOnHand = (int)$row['quantity_balance'];
                                        // When editing, show available as current balance + already issued in this ICS
                                        $issuedInThisIcs = $existing_item ? (int)$existing_item['quantity'] : 0;
                                        $displayQtyOnHand = $qtyOnHand + $issuedInThisIcs;
                                        $unitCost = (float)($row['amount'] ?? 0);
                                        $remarks = $row['remarks'] ?? '';
                                        $existingDesc = $existing_item && isset($existing_item['description']) ? (string)$existing_item['description'] : '';
                                        $displayDesc = $existingDesc !== '' ? $existingDesc : (($remarks !== '' ? $remarks : $row['item_description']));

                                        $catVal = isset($row['category']) ? $row['category'] : '';
                                        echo '<tr class="item-row" data-stock="' . htmlspecialchars(strtolower($stock_number)) . '" data-item_name="' . htmlspecialchars(strtolower($row['item_description'])) . '" data-description="' . htmlspecialchars(strtolower($displayDesc)) . '" data-unit="-" data-category="' . htmlspecialchars(strtolower($catVal)) . '">';
                                        echo '<td><input type="hidden" name="stock_number[]" value="' . htmlspecialchars($stock_number) . '">' . htmlspecialchars($stock_number) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['item_description']) . '</td>';
                                        echo '<td>' . htmlspecialchars($displayDesc) . '</td>';
                                        echo '<td>-</td>';
                                        echo '<td>' . htmlspecialchars($displayQtyOnHand) . '</td>';
                                        echo '<td>₱' . number_format($unitCost, 2) . '</td>';
                                        echo '<td><input type="number" name="issued_quantity[]" value="' . ($existing_item ? htmlspecialchars($existing_item['quantity']) : '') . '" min="0" max="' . htmlspecialchars($displayQtyOnHand) . '" step="1"></td>';
                                        echo '<td><input type="text" name="estimated_useful_life[]" value="' . ($existing_item ? htmlspecialchars($existing_item['estimated_useful_life']) : htmlspecialchars($row['estimated_useful_life'])) . '" placeholder="e.g., 5 years"></td>';
                                        echo '<td><input type="text" name="serial_number[]" value="' . ($existing_item ? htmlspecialchars($existing_item['serial_number']) : '') . '" placeholder="Serial No."></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr id="no-items-row"><td colspan="9">No semi-expendable items found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <h3>Signatories</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Received By:</label>
                        <input type="text" name="received_by" value="<?php echo htmlspecialchars($ics_data['received_by'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Received By Position:</label>
                        <input type="text" name="received_by_position" value="<?php echo htmlspecialchars($ics_data['received_by_position'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Received From:</label>
                        <input type="text" name="received_from" value="<?php echo htmlspecialchars($ics_data['received_from'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Received From Position:</label>
                        <input type="text" name="received_from_position" value="<?php echo htmlspecialchars($ics_data['received_from_position'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <button type="submit">Update ICS</button>
            <a href="<?php echo 'ics.php?ics_id=' . $ics_id; ?>" style="margin-left: 10px;">
                <button type="button">Cancel</button>
            </a>
        </form>
    </div>

    <script>
        function showClampNotice(message) {
            const notice = document.getElementById('clamp-notice');
            if (!notice) return;
            notice.textContent = message;
            notice.style.display = 'block';
            clearTimeout(window.__clampNoticeTimer);
            window.__clampNoticeTimer = setTimeout(() => { notice.style.display = 'none'; }, 2500);
        }
        function attachIssuedQtyGuards() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const qtyInput = row.querySelector('input[name="issued_quantity[]"]');
                if (!qtyInput) return;
                const stockCell = row.querySelector('td');
                const stockNo = stockCell ? stockCell.innerText.trim() : '(unknown)';
                const handleClamp = () => {
                    const max = parseFloat(qtyInput.max || '0');
                    let val = parseFloat(qtyInput.value);
                    if (isNaN(val)) { qtyInput.value = ''; return; }
                    if (val < 0) { qtyInput.value = '0'; showClampNotice(`Issued Qty for ${stockNo} cannot be negative. Set to 0.`); return; }
                    if (max > 0 && val > max) { qtyInput.value = String(max); showClampNotice(`Requested Issued Qty for ${stockNo} exceeded available stock. Set to ${max}.`); }
                };
                qtyInput.addEventListener('input', handleClamp);
                qtyInput.addEventListener('blur', handleClamp);
            });
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', () => {
                    const inputs = form.querySelectorAll('input[name="issued_quantity[]"]');
                    inputs.forEach(inp => {
                        const max = parseFloat(inp.max || '0');
                        let val = parseFloat(inp.value);
                        if (isNaN(val) || val < 0) { inp.value = val < 0 ? '0' : ''; return; }
                        if (max > 0 && val > max) { inp.value = String(max); }
                    });
                });
            }
        }
        document.addEventListener('DOMContentLoaded', attachIssuedQtyGuards);
        function filterItems() {
            const searchValue = document.getElementById('itemSearch').value.toLowerCase().trim();
            const itemRows = document.querySelectorAll('.item-row');
            let visibleRows = 0;
            itemRows.forEach(function(row) {
                const stockNumber = row.getAttribute('data-stock') || '';
                const item_name = row.getAttribute('data-item_name') || '';
                const description = row.getAttribute('data-description') || '';
                const unit = row.getAttribute('data-unit') || '';
                const match = searchValue === '' || stockNumber.includes(searchValue) || item_name.includes(searchValue) || description.includes(searchValue) || unit.includes(searchValue);
                row.style.display = match ? '' : 'none';
                if (match) visibleRows++;
            });
            const noItemsRow = document.getElementById('no-items-row');
            if (noItemsRow) {
                noItemsRow.style.display = visibleRows === 0 ? 'table-row' : 'none';
                if (visibleRows === 0) { noItemsRow.innerHTML = '<td colspan="9">No items match your search criteria.</td>'; }
            }
        }
        document.addEventListener('DOMContentLoaded', function(){
            try { if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; } } catch(e){}
            const saved = sessionStorage.getItem('ics_scroll');
            if (saved) {
                const y = parseInt(saved, 10); if (!isNaN(y)) { setTimeout(() => window.scrollTo(0, y), 0); }
                sessionStorage.removeItem('ics_scroll');
            }
            var sel = document.getElementById('filter_category');
            if (!sel) return;
            sel.addEventListener('change', function(){
                try { sessionStorage.setItem('ics_scroll', String(window.scrollY || window.pageYOffset || 0)); } catch(e){}
                var url = new URL(window.location.href);
                url.searchParams.set('filter_category', this.value);
                url.searchParams.set('ics_id', '<?php echo (int)$ics_id; ?>');
                url.hash = 'itemsTable';
                window.location.href = url.toString();
            });
        });
    </script>
</body>
</html>
