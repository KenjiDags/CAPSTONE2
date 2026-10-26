<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    set_exception_handler(function($e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    });
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $items_json = $_POST['items_json'] ?? '[]';
        $items = json_decode($items_json, true);
        if (empty($items) || !is_array($items)) {
            throw new Exception("No items selected for disposal.");
        }
        $conn->begin_transaction();
        // Get first item's PPE_no and particulars for the header
        $ppe_no_header = $items[0]['PPE_no'] ?? '';
        $particulars_header = $items[0]['particulars'] ?? '';
        // Insert Header (update to use PPE_no and remove unused fields)
        $stmt = $conn->prepare("INSERT INTO ppe_iirup (date_reported, PPE_no, particulars, quantity, depreciation, impairment_loss, carrying_amount, remarks, sale, transfer, destruction, other, total, appraised_value, or_no, amount, requested_by_name, requested_by_designation, requested_by_station, approved_by_name, approved_by_designation, inspection_officer_name, inspection_officer_designation, witness_name, witness_designation, entity_name, total_cost, accountable_officer_name, accountable_officer_designation, accountable_officer_station) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $date_reported = $_POST['date_reported'] ?? date('Y-m-d');
        $quantity = array_sum(array_column($items, 'quantity'));
        $depreciation = array_sum(array_column($items, 'depreciation'));
        $impairment_loss = array_sum(array_column($items, 'impairment_loss'));
        $carrying_amount = array_sum(array_column($items, 'carrying_amount'));
        $remarks = $items[0]['remarks'] ?? '';
        $sale = array_sum(array_column($items, 'sale'));
        $transfer = array_sum(array_column($items, 'transfer'));
        $destruction = array_sum(array_column($items, 'destruction'));
        $other = array_sum(array_column($items, 'other'));
        $total = array_sum(array_column($items, 'quantity'));
        $appraised_value = array_sum(array_column($items, 'appraised_value'));
        $or_no = count($items) === 1 && (int)($items[0]['sale'] ?? 0) > 0 ? trim($items[0]['or_no'] ?? '') : '';
        $amount = array_sum(array_map(static fn($item) => (int)($item['sale'] ?? 0) > 0 ? (float)($item['sale_amount'] ?? 0) : 0, $items));
        $requested_by_name = $_POST['requested_by_name'] ?? '';
        $requested_by_designation = $_POST['requested_by_designation'] ?? '';
        $requested_by_station = $_POST['requested_by_station'] ?? '';
        $approved_by_name = $_POST['approved_by_name'] ?? '';
        $approved_by_designation = $_POST['approved_by_designation'] ?? '';
        $inspection_officer_name = $_POST['inspection_officer_name'] ?? '';
        $inspection_officer_designation = $_POST['inspection_officer_designation'] ?? '';
        $witness_name = $_POST['witness_name'] ?? '';
        $witness_designation = $_POST['witness_designation'] ?? '';
        $entity_name = $_POST['entity_name'] ?? 'TESDA Regional Office';
        $total_cost = array_sum(array_column($items, 'amount'));
        $accountable_officer_name = trim($_POST['accountable_officer_name'] ?? '');
        $accountable_officer_designation = trim($_POST['accountable_officer_designation'] ?? '');
        $accountable_officer_station = trim($_POST['accountable_officer_station'] ?? '');
        $stmt->bind_param("sssidddsiiiiidsdssssssssssdsss", $date_reported, $ppe_no_header, $particulars_header, $quantity, $depreciation, $impairment_loss, $carrying_amount, $remarks, $sale, $transfer, $destruction, $other, $total, $appraised_value, $or_no, $amount, $requested_by_name, $requested_by_designation, $requested_by_station, $approved_by_name, $approved_by_designation, $inspection_officer_name, $inspection_officer_designation, $witness_name, $witness_designation, $entity_name, $total_cost, $accountable_officer_name, $accountable_officer_designation, $accountable_officer_station);
        if (!$stmt->execute()) throw new Exception("Header Error: " . $stmt->error);
        $ppe_iirup_id = $stmt->insert_id;
        $stmt->close();
        // Insert Items
        $item_stmt = $conn->prepare("INSERT INTO ppe_iirup_items (ppe_iirup_id, date_acquired, quantity, depreciation, impairment_loss, carrying_amount, remarks, sale, transfer, destruction, other, total, appraised_value, or_no, amount, particulars, PPE_no, sale_amount, other_specification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $update_stmt = $conn->prepare("UPDATE ppe_property SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
        foreach ($items as $item) {
            if (filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new Exception('Disposal quantities must be positive whole numbers.');
            }
            foreach (['depreciation', 'impairment_loss', 'carrying_amount', 'appraised_value'] as $field) {
                $value = $item[$field] ?? 0;
                if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (float)$value > 9999999999999.99) {
                    throw new Exception('Item financial values must be valid non-negative amounts.');
                }
                $item[$field] = (float)$value;
            }
            $particulars = $item['particulars'] ?? '';
            $item_ppe_no = trim($item['PPE_no'] ?? '');
            $is_sale = (int)($item['sale'] ?? 0) > 0;
            $item_or_no = $is_sale ? trim($item['or_no'] ?? '') : '';
            $item_sale_amount = $is_sale ? ($item['sale_amount'] ?? 0) : 0;
            $item_other_specification = (int)($item['other'] ?? 0) > 0 ? trim($item['other_specification'] ?? '') : '';
            if (mb_strlen($item_other_specification) > 255) throw new Exception('Other specification must not exceed 255 characters.');
            if (!is_numeric($item_sale_amount) || !is_finite((float)$item_sale_amount) || (float)$item_sale_amount < 0 || (float)$item_sale_amount > 9999999999999.99) {
                throw new Exception('Sale amount must be a valid non-negative amount.');
            }
            if (strlen($item_or_no) > 100) throw new Exception('O.R. number must not exceed 100 characters.');
            $item_stmt->bind_param("isidddsiiiiidsdssds",
                $ppe_iirup_id,
                $item['date_acquired'],
                $item['quantity'],
                $item['depreciation'],
                $item['impairment_loss'],
                $item['carrying_amount'],
                $item['remarks'],
                $item['sale'],
                $item['transfer'],
                $item['destruction'],
                $item['other'],
                $item['total'],
                $item['appraised_value'],
                $item_or_no,
                $item['amount'],
                $particulars,
                $item_ppe_no,
                $item_sale_amount,
                $item_other_specification
            );
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to insert item: " . $item_stmt->error);
            }
            // Update ppe_property to subtract disposed quantity
            $ppe_id = $item['ppe_id'];
            $disposed_qty = $item['quantity'];
            $update_stmt->bind_param("iii", $disposed_qty, $ppe_id, $disposed_qty);
            if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                throw new Exception("Cannot dispose more PPE than the available quantity.");
            }
        }
        $item_stmt->close();
        $update_stmt->close();
        $conn->commit();
        restore_error_handler();
        restore_exception_handler();
        echo json_encode(['success' => true, 'message' => 'PPE IIRUP saved successfully']);
    } catch (Throwable $e) {
        if ($conn) $conn->rollback();
        restore_error_handler();
        restore_exception_handler();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
// Defaults for the signed-in user's accountable-officer details.
$profile_stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(user_full_name), ''), full_name, '') AS officer_name, COALESCE(user_position, '') AS officer_position FROM users WHERE user_id = ? LIMIT 1");
$profile_stmt->bind_param('i', $_SESSION['user_id']);
$profile_stmt->execute();
$current_profile = $profile_stmt->get_result()->fetch_assoc() ?: [];
$profile_stmt->close();

// Reuse the shared signatory name and designation autocomplete.
$officer_names = [];
$officer_positions = [];
$officer_result = $conn->query('SELECT DISTINCT officer_name, officer_position FROM officers WHERE LENGTH(TRIM(officer_name)) > 0 ORDER BY officer_name');
while ($officer = $officer_result->fetch_assoc()) {
    $officer_names[] = $officer['officer_name'];
    $officer_positions[$officer['officer_name']] = $officer['officer_position'] ?? '';
}
$officer_names = array_values(array_unique($officer_names));
$officer_result->free();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Add PPE IIRUP Form</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="add-page iirup-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <div class="form-container inventory-item-form no-number-spinners">
        <header class="page-header">
            <h1 class="form-page-title"> <i class="fas fa-file-invoice"></i>Add PPE IIRUP (Inventory & Inspection Report - Unserviceable PPE)</h1>
            <p class="form-page-subtitle">Create a new Inventory & Inspection Report</p>
        </header>
        <div class="section-card">
            <h3 class="form-section-heading"><i class="fas fa-user"></i> Accountable Officer</h3>
            <div class="form-grid">

                <div class="form-group">
                    <label for="accountable_officer_name">Name of Accountable Officer:</label>
                    <input type="text" id="accountable_officer_name" name="accountable_officer_name" value="<?= htmlspecialchars($current_profile['officer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label for="accountable_officer_designation">Designation:</label>
                    <input type="text" id="accountable_officer_designation" name="accountable_officer_designation" value="<?= htmlspecialchars($current_profile['officer_position'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255" placeholder="Designation">
                </div>
                <div class="form-group">
                    <label for="accountable_officer_station">Station:</label>
                    <input type="text" id="accountable_officer_station" name="accountable_officer_station" maxlength="255" placeholder="Office or station">
                </div>
                <div class="form-group">
                    <label for="date_reported">Date Reported:</label>
                    <input type="date" id="date_reported" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>
        <section class="iirup-items-section" aria-labelledby="iirup-items-title">
            <h3 id="iirup-items-title" class="form-section-heading inventory-section-heading"><i class="fas fa-list"></i> Unserviceable PPE Items (Search & Select)</h3>
            <label class="compact-search-field" for="itemSearch">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="itemSearch" class="search-input" aria-label="Search PPE number, item description, or holder name" placeholder="Search PPE No, Item Description, or Holder Name..." oninput="filterTable()">
            </label>
            <div class="table-frame">
                <div class="table-viewport">
                    <table id="itemsTable" class="bordered-cell-inputs scrollable-document-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Date Acq.</th>
                                <th>PPE No.</th>
                                <th>Description</th>
                                <th>Custodian</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Disposal Qty</th>
                                <th>Remarks</th>
                                <th>Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $sql = "SELECT p.id, p.date_acquired, p.PPE_no, p.item_name, p.item_description, p.unit, p.quantity, p.custodian, p.amount, p.remarks FROM ppe_property p WHERE p.quantity > 0 ORDER BY p.item_name";
                        $res = $conn->query($sql);
                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $search = strtolower($row['PPE_no'] . " " . $row['item_name'] . " " . $row['item_description']);
                        ?>
                        <tr class="item-row" data-search="<?= $search ?>" data-ppe-id="<?= $row['id'] ?>" data-stock-quantity="<?= (int)$row['quantity'] ?>" data-date-acquired="<?= htmlspecialchars($row['date_acquired']) ?>" data-item-name="<?= htmlspecialchars($row['item_name']) ?>" data-item-description="<?= htmlspecialchars($row['item_description']) ?>">
                            <td><input type="checkbox" class="item-checkbox" onclick="fillInputsFromRow(this)"></td>
                            <td><?= htmlspecialchars($row['date_acquired']) ?></td>
                            <td class="prop-cell"><?= htmlspecialchars($row['PPE_no']) ?></td>
                            <td class="desc-cell"><?= htmlspecialchars($row['item_name'] . ' - ' . $row['item_description']) ?></td>
                            <td class="holder-cell"><?= htmlspecialchars($row['custodian']) ?></td>
                            <td class="allocation-available" style="text-align:center; font-weight:bold; background:#e0f2fe;">
                                <?= (int)$row['quantity'] ?>
                            </td>
                            <td class="currency" data-val="<?= $row['amount'] ?>">₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                                <input type="number" class="disposal-qty" min="1" max="<?= (int)$row['quantity'] ?>" placeholder="0">
                            </td>
                            <td>
                                <input type="text" class="remarks-input" placeholder="e.g. Broken">
                            </td>
                            <td>
                                <select class="mode-select" onchange="syncDisposalFields(this.closest('tr'))">
                                    <option value="Destruction">Destruction</option>
                                    <option value="Sale">Sale</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="Other">Other</option>
                                </select>
                            </td>
                            <td class="unit-cell" style="display:none;">
                                <?= htmlspecialchars($row['unit']) ?>
                            </td>
                        </tr>
                        <tr class="item-sale-details" hidden>
                            <td colspan="10">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="depreciation-<?= (int)$row['id'] ?>">Depreciation:</label>
                                        <input type="number" id="depreciation-<?= (int)$row['id'] ?>" class="item-depreciation" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="impairment_loss-<?= (int)$row['id'] ?>">Impairment Loss:</label>
                                        <input type="number" id="impairment_loss-<?= (int)$row['id'] ?>" class="item-impairment_loss" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="carrying_amount-<?= (int)$row['id'] ?>">Carrying Amount:</label>
                                        <input type="number" id="carrying_amount-<?= (int)$row['id'] ?>" class="item-carrying_amount" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="appraised_value-<?= (int)$row['id'] ?>">Appraised Value:</label>
                                        <input type="number" id="appraised_value-<?= (int)$row['id'] ?>" class="item-appraised_value" min="0" step="0.01" value="0">
                                    </div>

                                    <div class="form-group" data-disposal-mode="Sale" hidden>
                                        <label for="sale-or-<?= (int)$row['id'] ?>">O.R. No.:</label>
                                        <input type="text" id="sale-or-<?= (int)$row['id'] ?>" class="sale-or-no" maxlength="100" placeholder="Official receipt number" disabled>
                                    </div>
                                    <div class="form-group" data-disposal-mode="Sale" hidden>
                                        <label for="sale-amount-<?= (int)$row['id'] ?>">Amount:</label>
                                        <input type="number" id="sale-amount-<?= (int)$row['id'] ?>" class="sale-amount" min="0" step="0.01" placeholder="0.00" disabled>
                                    </div>
                                    <div class="form-group" data-disposal-mode="Other" hidden>
                                        <label for="other-specification-<?= (int)$row['id'] ?>">Other:</label>
                                        <input type="text" id="other-specification-<?= (int)$row['id'] ?>" class="other-specification" maxlength="255" placeholder="Specify..." disabled>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="10">No PPE items available for disposal.</td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="section-card">
            <h3 class="form-section-heading"> <i class="fas fa-pen-fancy"></i>Signatories</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <h4 class="form-section-heading" style="margin: 0 0 12px 0; font-size: 13px; font-weight: bold;">Requested By</h4>
                    <div class="form-group">
                        <label>Name:</label>
                        <div class="officer-name-field"><input type="text" id="requested_by_name" placeholder="Name" data-officer-autocomplete data-designation-target="requested_by_designation" autocomplete="off"><div id="requested_by_name_dropdown" class="autocomplete-dropdown"></div></div>
                    </div>
                    <div class="form-group">
                        <label>Designation:</label>
                        <input type="text" id="requested_by_designation" placeholder="Designation">
                    </div>
                    <div class="form-group">
                        <label>Station:</label>
                        <input type="text" id="requested_by_station" placeholder="Station">
                    </div>
                </div>
                <div>
                    <h4 class="form-section-heading" style="margin: 0 0 12px 0; font-size: 13px; font-weight: bold;">Approved By</h4>
                    <div class="form-group">
                        <label>Name:</label>
                        <div class="officer-name-field"><input type="text" id="approved_by_name" placeholder="Name" data-officer-autocomplete data-designation-target="approved_by_designation" autocomplete="off"><div id="approved_by_name_dropdown" class="autocomplete-dropdown"></div></div>
                    </div>
                    <div class="form-group">
                        <label>Designation:</label>
                        <input type="text" id="approved_by_designation" placeholder="Designation">
                    </div>
                </div>
                <div>
                    <h4 class="form-section-heading" style="margin: 0 0 12px 0; font-size: 13px; font-weight: bold;">Inspection Officer</h4>
                    <div class="form-group">
                        <label>Name:</label>
                        <div class="officer-name-field"><input type="text" id="inspection_officer_name" placeholder="Name" data-officer-autocomplete data-designation-target="inspection_officer_designation" autocomplete="off"><div id="inspection_officer_name_dropdown" class="autocomplete-dropdown"></div></div>
                    </div>
                    <div class="form-group">
                        <label>Designation:</label>
                        <input type="text" id="inspection_officer_designation" placeholder="Designation">
                    </div>
                </div>
            </div>
            <div>
                <h4 class="form-section-heading" style="margin: 0 0 12px 0; font-size: 13px; font-weight: bold;">Witness</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Name:</label>
                        <div class="officer-name-field"><input type="text" id="witness_name" placeholder="Name" data-officer-autocomplete data-designation-target="witness_designation" autocomplete="off"><div id="witness_name_dropdown" class="autocomplete-dropdown"></div></div>
                    </div>
                    <div class="form-group">
                        <label>Designation:</label>
                        <input type="text" id="witness_designation" placeholder="Designation">
                    </div>
                </div>
            </div>
        </div>


        <button onclick="submitIIRUP()" class="pill-btn pill-add" type="submit">
            <i class="fas fa-save"></i>SUBMIT PPE IIRUP
        </button>
        <a href="PPE/PPE_iirup.php">
            <button type="button" class="pill-btn pill-view"><i class="fas fa-ban"></i>Cancel</button>
        </a>
    </div>
</div>
<?php require dirname(__DIR__) . '/officer_dropdown_options.php'; ?>
<script>
// Replace a default zero immediately when typing into an item amount field.
function selectDefaultZero(event) {
    const input = event.target;
    if (input.matches('input[type="number"]') && input.value !== '' && Number(input.value) === 0) {
        input.select();
    }
}
document.getElementById('itemsTable').addEventListener('focusin', selectDefaultZero);
document.getElementById('itemsTable').addEventListener('click', selectDefaultZero);
let allocationSequence = 0;
function appendRemainingAllocation(sourceRow) {
    const row = sourceRow.cloneNode(true);
    const details = sourceRow.nextElementSibling.cloneNode(true);
    const suffix = '-allocation-' + (++allocationSequence);
    row.dataset.splitAllocation = 'true';
    row.querySelector('.item-checkbox').checked = false;
    row.querySelector('.disposal-qty').value = '';
    row.querySelector('.disposal-qty').setCustomValidity('');
    row.querySelector('.remarks-input').value = '';
    row.querySelector('.mode-select').value = 'Destruction';
    details.querySelectorAll('input').forEach(input => {
        const oldId = input.id;
        input.id = oldId + suffix;
        details.querySelectorAll('label').forEach(label => {
            if (label.htmlFor === oldId) label.htmlFor = input.id;
        });
        input.value = input.classList.contains('sale-or-no') || input.classList.contains('other-specification') || input.classList.contains('sale-amount') ? '' : '0';
        input.setCustomValidity('');
    });
    sourceRow.nextElementSibling.after(row, details);
    syncDisposalFields(row);
    return row;
}
function updateItemAllocations(ppeId) {
    let rows = Array.from(document.querySelectorAll('.item-row')).filter(row => row.dataset.ppeId === ppeId);
    if (!rows.length) return;
    let remaining = Number(rows[0].dataset.stockQuantity);
    rows.forEach(row => {
        const selected = row.querySelector('.item-checkbox').checked;
        const input = row.querySelector('.disposal-qty');
        const available = Math.max(0, remaining);
        if (row.dataset.splitAllocation && !selected && available === 0) {
            row.nextElementSibling.remove();
            row.remove();
            return;
        }
        row.querySelector('.allocation-available').textContent = available;
        input.max = available;
        input.setCustomValidity('');
        const quantity = Number(input.value);
        if (selected && Number.isInteger(quantity) && quantity > 0) {
            if (quantity > available) input.setCustomValidity('The combined disposal quantities exceed the available stock for this item.');
            remaining -= quantity;
        }
        syncDisposalFields(row);
    });
    rows = rows.filter(row => row.isConnected);
    const last = rows[rows.length - 1];
    const lastInput = last.querySelector('.disposal-qty');
    if (remaining > 0 && last.querySelector('.item-checkbox').checked && Number(lastInput.value) > 0 && lastInput.checkValidity()) {
        const copy = appendRemainingAllocation(last);
        copy.querySelector('.allocation-available').textContent = remaining;
        copy.querySelector('.disposal-qty').max = remaining;
    }
}
document.getElementById('itemsTable').addEventListener('input', event => {
    if (event.target.matches('.disposal-qty')) updateItemAllocations(event.target.closest('.item-row').dataset.ppeId);
});
function syncDisposalFields(row) {
    const details = row.nextElementSibling;
    const mode = row.querySelector('.mode-select').value;
    const selected = row.querySelector('.item-checkbox').checked;
    row.querySelector('.disposal-qty').required = selected;
    details.hidden = !selected || row.style.display === 'none';
    details.querySelectorAll('input').forEach(input => { input.disabled = !selected; });
    details.querySelectorAll('[data-disposal-mode]').forEach(group => {
        const active = selected && group.dataset.disposalMode === mode;
        group.hidden = !active;
        group.querySelectorAll('input').forEach(input => { input.disabled = !active; });
    });
}
function filterTable() {
    const input = document.getElementById('itemSearch').value.toLowerCase().trim();
    document.querySelectorAll('.item-row').forEach(row => {
        if (row.dataset.search.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
        syncDisposalFields(row);
    });
}
function submitIIRUP() {
    const ppeIds = new Set(Array.from(document.querySelectorAll('.item-row'), row => row.dataset.ppeId));
    ppeIds.forEach(updateItemAllocations);
    const items = [];
    let invalidItemInput = null;
    document.querySelectorAll('.item-row').forEach(row => {
        if (!row.querySelector('.item-checkbox').checked) return;
        const quantityInput = row.querySelector('.disposal-qty');
        const qty = Number(quantityInput.value);
        if (!quantityInput.checkValidity() || !Number.isInteger(qty) || qty < 1) {
            invalidItemInput = quantityInput;
            return;
        }
        if (row.querySelector('.item-checkbox').checked && qty > 0) {
            const mode = row.querySelector('.mode-select').value;
            row.nextElementSibling.querySelectorAll('input:not(:disabled)').forEach(input => {
                if (!input.checkValidity()) invalidItemInput = input;
            });
            const unitCost = parseFloat(row.querySelector('.currency').dataset.val) || 0;
            const itemName = row.dataset.itemName || '';
            const itemDescription = row.dataset.itemDescription || '';
            const particulars = itemName + (itemDescription ? ', ' + itemDescription : '');
            items.push({
                ppe_id: row.dataset.ppeId,
                date_acquired: row.dataset.dateAcquired,
                PPE_no: row.querySelector('.prop-cell').innerText.trim(),
                quantity: qty,
                remarks: row.querySelector('.remarks-input').value.trim(),
                particulars: particulars,
                depreciation: Number(row.nextElementSibling.querySelector('.item-depreciation').value || 0),
                impairment_loss: Number(row.nextElementSibling.querySelector('.item-impairment_loss').value || 0),
                carrying_amount: Number(row.nextElementSibling.querySelector('.item-carrying_amount').value || 0),
                sale: (mode === 'Sale') ? qty : 0,
                transfer: (mode === 'Transfer') ? qty : 0,
                destruction: (mode === 'Destruction') ? qty : 0,
                other: mode === 'Other' ? qty : 0,
                other_specification: mode === 'Other' ? row.nextElementSibling.querySelector('.other-specification').value.trim() : '',
                total: qty,
                appraised_value: Number(row.nextElementSibling.querySelector('.item-appraised_value').value || 0),
                or_no: mode === 'Sale' ? row.nextElementSibling.querySelector('.sale-or-no').value.trim() : '',
                sale_amount: mode === 'Sale' ? Number(row.nextElementSibling.querySelector('.sale-amount').value || 0) : 0,
                amount: qty * unitCost
            });
        }
    });
    if (invalidItemInput) { invalidItemInput.reportValidity(); return; }
    if (items.length === 0) { alert('Please select items to dispose.'); return; }
    const fd = new FormData();
    fd.append('date_reported', document.getElementById('date_reported').value);
    fd.append('quantity', items.reduce((sum, item) => sum + item.quantity, 0));
    fd.append('depreciation', items.reduce((sum, item) => sum + item.depreciation, 0));
    fd.append('impairment_loss', items.reduce((sum, item) => sum + item.impairment_loss, 0));
    fd.append('carrying_amount', items.reduce((sum, item) => sum + item.carrying_amount, 0));
    fd.append('other', items.reduce((sum, item) => sum + item.other, 0));
    fd.append('total', items.reduce((sum, item) => sum + item.quantity, 0));
    fd.append('appraised_value', items.reduce((sum, item) => sum + item.appraised_value, 0));
    fd.append('or_no', items.length === 1 ? items[0].or_no : '');
    fd.append('amount', items.reduce((sum, item) => sum + item.sale_amount, 0));
    fd.append('accountable_officer_name', document.getElementById('accountable_officer_name').value);
    fd.append('accountable_officer_designation', document.getElementById('accountable_officer_designation').value);
    fd.append('accountable_officer_station', document.getElementById('accountable_officer_station').value);
    fd.append('requested_by_name', document.getElementById('requested_by_name').value);
    fd.append('requested_by_designation', document.getElementById('requested_by_designation').value);
    fd.append('requested_by_station', document.getElementById('requested_by_station').value);
    fd.append('approved_by_name', document.getElementById('approved_by_name').value);
    fd.append('approved_by_designation', document.getElementById('approved_by_designation').value);
    fd.append('inspection_officer_name', document.getElementById('inspection_officer_name').value);
    fd.append('inspection_officer_designation', document.getElementById('inspection_officer_designation').value);
    fd.append('witness_name', document.getElementById('witness_name').value);
    fd.append('witness_designation', document.getElementById('witness_designation').value);
    fd.append('entity_name', 'TESDA Regional Office');
    fd.append('total_cost', items.reduce((sum, item) => sum + item.amount, 0));
    fd.append('items_json', JSON.stringify(items));
    fetch('PPE/add_iirup.php', {
        method: 'POST',
        body: fd
    }).then(res => res.json()).then(json => {
        if (json.success) {
            alert('Success! PPE IIRUP saved.');
            window.location.href = 'PPE/PPE_iirup.php';
        } else {
            alert('Error: ' + json.message);
        }
    }).catch(e => {
        alert('Network error. Check console.');
        console.error(e);
    });
}
function fillInputsFromRow(checkbox) {
    updateItemAllocations(checkbox.closest('tr').dataset.ppeId);
}
</script>

</body>
</html>
