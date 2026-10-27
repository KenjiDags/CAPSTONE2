<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';

function iiruspItemDetails(array $item): array {
    $details = [];
    foreach (['accumulated_impairment', 'carrying_amount', 'appraised_value', 'sales_amount'] as $field) {
        $value = $item[$field] ?? '0';
        if (!is_scalar($value) || !preg_match('/^\d{1,13}(\.\d{1,2})?$/D', (string)$value)) throw new InvalidArgumentException('Enter valid nonnegative item amounts with at most two decimal places.');
        $details[$field] = (float)$value;
    }
    $details['disposal_mode'] = $item['disposal_mode'] ?? 'Destruction';
    if (!in_array($details['disposal_mode'], ['Sale', 'Transfer', 'Destruction', 'Other'], true)) throw new InvalidArgumentException('Invalid disposal mode.');
    foreach (['or_no' => 100, 'disposal_others' => 255] as $field => $limit) {
        if (!is_string($item[$field] ?? '')) throw new InvalidArgumentException('Invalid item details.');
        $details[$field] = trim($item[$field] ?? '');
        if (strlen($details[$field]) > $limit) throw new InvalidArgumentException('Item details are too long.');
    }
    if ($details['disposal_mode'] !== 'Sale') { $details['or_no'] = ''; $details['sales_amount'] = 0; }
    if ($details['disposal_mode'] !== 'Other') $details['disposal_others'] = '';
    if ($details['disposal_mode'] === 'Other' && $details['disposal_others'] === '') throw new InvalidArgumentException('Specify the other disposal mode.');
    return $details;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean buffer to ensure JSON valid response
    ob_start();
    require dirname(__DIR__) . '/config.php';
    require dirname(__DIR__) . '/functions.php';
    ob_clean(); 
    header('Content-Type: application/json');
    ini_set('display_errors', 0); 

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        // Ensure tables exist
        ensure_iirusp_tables($conn);
        ensure_iirusp_history($conn);
        ensure_semi_expendable_history($conn);

        // A. Basic Validations
        $iirusp_no = trim($_POST['iirusp_no'] ?? '');
        if (!$iirusp_no) throw new Exception("IIRUSP No. is required");

        $items_json = $_POST['items_json'] ?? '[]';
        $items = json_decode($items_json, true);

        if (empty($items) || !is_array($items)) {
            throw new Exception("No items selected for disposal.");
        }

        // Start transaction
        $conn->begin_transaction();

        // B. Insert Header
        $stmt = $conn->prepare("INSERT INTO iirusp (iirusp_no, as_at, entity_name, fund_cluster, accountable_officer_name, accountable_officer_designation, accountable_officer_station, requested_by, requested_by_designation, approved_by, approved_by_designation, inspection_officer, witness) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $as_at = $_POST['as_at'] ?? date('Y-m-d');
        $ent = $_POST['entity_name'] ?? '';
        $fund = $_POST['fund_cluster'] ?? '';
        $acc_n = $_POST['accountable_officer_name'] ?? '';
        $acc_d = $_POST['accountable_officer_designation'] ?? '';
        $acc_s = $_POST['accountable_officer_station'] ?? '';
        $req = $_POST['requested_by'] ?? '';
        $req_d = $_POST['requested_by_designation'] ?? '';
        $app = $_POST['approved_by'] ?? '';
        $app_d = $_POST['approved_by_designation'] ?? '';
        $insp = $_POST['inspection_officer'] ?? '';
        $wit = $_POST['witness'] ?? '';

        $stmt->bind_param("sssssssssssss", $iirusp_no, $as_at, $ent, $fund, $acc_n, $acc_d, $acc_s, $req, $req_d, $app, $app_d, $insp, $wit);

        if (!$stmt->execute()) throw new Exception("Header Error: " . $stmt->error);
        $iirusp_id = $stmt->insert_id;
        $stmt->close();

        // C. Process Items
        $item_stmt = $conn->prepare("INSERT INTO iirusp_items (iirusp_id, date_acquired, particulars, semi_expendable_property_no, quantity, unit, unit_cost, total_cost, disposal_sale, disposal_transfer, disposal_destruction, disposal_total, remarks, accumulated_impairment, carrying_amount, appraised_value, or_no, sales_amount, disposal_others) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Prepare Master Update (Deduct Stock)
        $update_master = $conn->prepare("UPDATE semi_expendable_property SET quantity = quantity - ?, quantity_balance = quantity_balance - ?, quantity_disposed = quantity_disposed + ? WHERE semi_expendable_property_no = ? AND quantity >= ? AND quantity_balance >= ?");

        // Prepare History Insert
        $history_stmt = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity_disposed, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $prop_no = $item['property_no'];
            $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($qty === false) { throw new Exception('Disposal quantities must be positive whole numbers.'); }
            $cost = (float)$item['unit_cost'];
            $total = $qty * $cost;

            // 1. Validate: Get current balance and check disposal qty
            $check_stmt = $conn->prepare("SELECT id, quantity, quantity_balance, unit, office_officer_reissued, office_officer_issued FROM semi_expendable_property WHERE semi_expendable_property_no = ? AND (quantity > 0 AND quantity_balance > 0) LIMIT 1 FOR UPDATE");
            $check_stmt->bind_param("s", $prop_no);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Item $prop_no not found");
            }

            $row = $result->fetch_assoc();
            $semi_id = $row['id'];
            $available_qty = min((int)$row['quantity'], (int)$row['quantity_balance']);
            $unit_val = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : ($row['unit'] ?? '');
            $check_stmt->close();

            if ($qty > $available_qty) {
                throw new Exception("Item $prop_no: Cannot dispose $qty units. Only $available_qty available.");
            }

            // 2. Insert Item to IIRUSP Table
            $details = iiruspItemDetails($item);
            $d_sale = $details['disposal_mode'] === 'Sale' ? (float)$qty : 0.0;
            $d_trans = $details['disposal_mode'] === 'Transfer' ? (float)$qty : 0.0;
            $d_destr = $details['disposal_mode'] === 'Destruction' ? (float)$qty : 0.0;
            $d_tot = (float)$qty; // Total disposed (must be float for bind_param 'd')

            $unit_val = isset($item['unit']) ? $item['unit'] : '';
            $item_stmt->bind_param("isssisddddddsdddsds",
                $iirusp_id, $item['date_acquired'], $item['particulars'], $prop_no, $qty, $unit_val, 
                $cost, $total, $d_sale, $d_trans, $d_destr, $d_tot, $item['remarks'], $details['accumulated_impairment'], $details['carrying_amount'], $details['appraised_value'], $details['or_no'], $details['sales_amount'], $details['disposal_others']
            );

            if (!$item_stmt->execute()) {
                throw new Exception("Failed to insert item: " . $item_stmt->error);
            }

            // 3. Update Master Stock (SAFE USING PREPARED STATEMENT)
            $update_master->bind_param("iiisii", $qty, $qty, $qty, $prop_no, $qty, $qty);
            if (!$update_master->execute() || $update_master->affected_rows !== 1) {
                throw new Exception("Failed to update master: " . $update_master->error);
            }

            // 4. Add History Entry
            $holder = !empty($row['office_officer_reissued']) ? $row['office_officer_reissued'] :
                      (!empty($row['office_officer_issued']) ? $row['office_officer_issued'] : 'Stock Room');
            $rem = "Disposed via IIRUSP " . $iirusp_no;
            $history_stmt->bind_param("issisdds", $semi_id, $as_at, $iirusp_no, $qty, $holder, $cost, $total, $rem);

            if (!$history_stmt->execute()) {
                throw new Exception("Failed to log history: " . $history_stmt->error);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'IIRUSP saved successfully']);

    } catch (Exception $e) {
        if ($conn) $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If we reach here, it's a GET request to display the form
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/functions.php';
ensure_iirusp_tables($conn);
$current_user_full_name = '';
$current_user_position = '';
$userStmt = $conn->prepare('SELECT user_full_name, user_position FROM users WHERE user_id = ? LIMIT 1');
$userStmt->bind_param('i', $_SESSION['user_id']);
$userStmt->execute();
$currentUser = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
$current_user_full_name = $currentUser['user_full_name'] ?? '';
$current_user_position = $currentUser['user_position'] ?? '';

$officer_names = [];
$officer_positions = [];
$officer_result = $conn->query('SELECT DISTINCT officer_name, officer_position FROM officers WHERE LENGTH(TRIM(officer_name)) > 0 ORDER BY officer_name');
if ($officer_result) {
    while ($officer = $officer_result->fetch_assoc()) {
        $officer_names[] = $officer['officer_name'];
        $officer_positions[$officer['officer_name']] = $officer['officer_position'] ?? '';
    }
    $officer_names = array_values(array_unique($officer_names));
    $officer_result->free();
}
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Add IIRUSP Form</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="add-page iirusp-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <div class="form-container inventory-item-form no-number-spinners">
        <header class="page-header">
            <h1 class="form-page-title"> <i class="fas fa-file-invoice"></i>Add IIRUSP (Inventory & Inspection Report - Unserviceable Semi-Expendable Properties)</h1>
            <p class="form-page-subtitle">Create a new Inventory & Inspection Report</p>
        </header>   

        <!-- HEADER FORM -->
        <div class="section-card">
            <h3 class="form-section-heading"> <i class="fas fa-info-circle"></i>IIRUSP Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>IIRUSP No.:</label>
                    <input type="text" id="iirusp_no" value="<?php echo date('Y-m').'-0001'; ?>">
                </div>
                <div class="form-group">
                    <label>As At (Date):</label>
                    <input type="date" id="as_at" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Entity Name:</label>
                    <input type="text" id="entity_name" value="TESDA Regional Office">
                </div>
                <div class="form-group">
                    <label>Fund Cluster:</label>
                    <input type="text" id="fund_cluster" value="101">
                </div>
            </div>

            <hr style="margin:20px 0; border:0; border-top:1px solid;">

            <div class="form-grid">
                <div class="form-group">
                    <label>Accountable Officer:</label>
                    <input type="text" id="accountable_officer_name" value="<?= htmlspecialchars($current_user_full_name, ENT_QUOTES, 'UTF-8') ?>" placeholder="Name">
                </div>
                <div class="form-group">
                    <label>Designation:</label>
                    <input type="text" id="accountable_officer_designation" value="<?= htmlspecialchars($current_user_position, ENT_QUOTES, 'UTF-8') ?>" placeholder="Position">
                </div>
                <div class="form-group">
                    <label>Station:</label>
                    <input type="text" id="accountable_officer_station" placeholder="Office/Station">
                </div>
            </div>
        </div>

        <!-- GRID STYLE UI FOR SELECTING ITEMS -->
        <div class="section-card">
            <h3 class="form-section-heading"> <i class="fas fa-list"></i>Unserviceable Items (Search & Select)</h3>

            <!-- Search Bar -->
            <div style="margin-bottom: 10px;">
                <label class="compact-search-field" for="itemSearch"><i class="fas fa-search" aria-hidden="true"></i><input aria-label="Search items" type="text" id="itemSearch" class="search-input"
                    placeholder="Search Property No, Item Description, or Holder Name..." 
                    onkeyup="filterTable()"></label>
            </div>

            <div class="table-frame">
                <div class="table-viewport">
                    <table id="itemsTable" class="bordered-cell-inputs scrollable-document-table">
                        <thead>
                            <tr>
                                <th>Select</th><th style="min-width:90px;">Date Acq.</th>
                                <th>Property No.</th>
                                <th>Description</th>
                                <th style="width:120px;">Held By</th>
                                <th style="width:60px;">On Hand</th>
                                <th style="width:100px;">Cost</th>
                                <th style="width:80px; background-color:#ef4444;">Disposal Qty</th>
                                <th>Remarks</th>
                                <th style="width:100px;">Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            // Pull items directly from semi_expendable_property for disposal
                            $sql = "SELECT id, date, ics_rrsp_no, semi_expendable_property_no, item_description, unit, estimated_useful_life, quantity, quantity_issued, office_officer_issued, quantity_returned, office_officer_returned, quantity_reissued, office_officer_reissued, quantity_disposed, quantity_balance, amount, amount_total, category, fund_cluster, remarks FROM semi_expendable_property WHERE quantity > 0 AND quantity_balance > 0 AND (NOT EXISTS (SELECT 1 FROM iirusp_items disposed_item WHERE disposed_item.semi_expendable_property_no COLLATE utf8mb4_general_ci = semi_expendable_property.semi_expendable_property_no COLLATE utf8mb4_general_ci) OR (COALESCE(semi_expendable_property.quantity, 0) > 0 AND (COALESCE(semi_expendable_property.quantity_balance, 0) > 0 OR COALESCE(semi_expendable_property.quantity_issued, 0) > 0 OR COALESCE(semi_expendable_property.quantity_reissued, 0) > 0))) ORDER BY item_description";
                            $res = $conn->query($sql);
                            if ($res && $res->num_rows > 0) {
                                while ($row = $res->fetch_assoc()) {
                                    $search = strtolower($row['semi_expendable_property_no'] . " " . $row['item_description']);
    ?>
        <tr class="item-row" data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" data-semi-id="<?= (int)$row['id'] ?>" data-stock-quantity="<?= max(0, min((int)$row['quantity'], (int)$row['quantity_balance'])) ?>"><td><input type="checkbox" class="item-checkbox" aria-label="Select <?= htmlspecialchars($row['item_description'], ENT_QUOTES, 'UTF-8') ?>" onchange="toggleIiruspItem(this)"></td>
            <td><?= htmlspecialchars($row['date']) ?></td>
            <td class="prop-cell"><?= htmlspecialchars($row['semi_expendable_property_no']) ?></td>
            <td class="desc-cell"><?= htmlspecialchars($row['item_description']) ?></td>
            <td class="holder-cell" style="font-weight:bold;">Inventory</td>
            <td class="allocation-available" style="text-align:center; font-weight:bold; background:#e0f2fe;">
                <?= max(0, min((int)$row['quantity'], (int)$row['quantity_balance'])) ?>
            </td>
            <td class="cost-cell" data-val="<?= $row['amount'] ?>">₱<?= number_format($row['amount'], 2) ?></td>
            <td>
                <input type="number" class="disposal-qty" min="1" step="1" disabled max="<?= (int)$row['quantity_balance'] ?>" placeholder="0">
            </td>
            <td>
                <input type="text" class="remarks-input" placeholder="e.g. Broken">
            </td>
            <td>
                <select class="mode-select" onchange="syncIiruspFields(this.closest('tr'))">
                    <option value="Destruction">Destruction</option>
                    <option value="Sale">Sale</option>
                    <option value="Transfer">Transfer</option><option value="Other">Other</option>
                </select>
            </td>
            <td class="unit-cell" style="display:none;">
                <?= htmlspecialchars($row['unit']) ?>
            </td>
        </tr>
        <tr class="item-sale-details" hidden>
            <td colspan="10"><div class="form-grid">
                <?php foreach (['accumulated_impairment' => 'Accumulated Impairment Losses', 'carrying_amount' => 'Carrying Amount', 'appraised_value' => 'Appraised Value'] as $field => $label): ?>
                <div class="form-group">
                    <label for="<?= $field ?>-<?= (int)$row['id'] ?>"><?= $label ?>:</label>
                    <input type="number" id="<?= $field ?>-<?= (int)$row['id'] ?>" class="item-<?= $field ?>" min="0" step="0.01" value="0" disabled>
                </div>
                <?php endforeach; ?>
                <div class="form-group" data-disposal-mode="Sale" hidden>
                    <label for="or_no-<?= (int)$row['id'] ?>">O.R. No.:</label>
                    <input type="text" id="or_no-<?= (int)$row['id'] ?>" class="sale-or-no" maxlength="100" placeholder="Official receipt number" disabled>
                </div>
                <div class="form-group" data-disposal-mode="Sale" hidden>
                    <label for="sales_amount-<?= (int)$row['id'] ?>">Amount:</label>
                    <input type="number" id="sales_amount-<?= (int)$row['id'] ?>" class="sale-amount" min="0" step="0.01" placeholder="0.00" disabled>
                </div>
                <div class="form-group" data-disposal-mode="Other" hidden>
                    <label for="disposal_others-<?= (int)$row['id'] ?>">Other:</label>
                    <input type="text" id="disposal_others-<?= (int)$row['id'] ?>" class="other-specification" maxlength="255" placeholder="Specify..." required disabled>
                </div>
            </div></td>
        </tr>
    <?php
                                }
                            } else {
                                echo '<tr><td colspan="10">No items available for disposal.</td></tr>';
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="text-align:right; margin-bottom:30px;">
            <h3 style="display:inline-block; font-size:18px; margin: 0 !important;">Total Value: <span id="grand_total_display" style="color:#0056b3;">₱0.00</span></h3>
        </div>

        <!-- FOOTER SIGNATORIES -->
        <div class="section-card">
            <h3 class="form-section-heading"> <i class="fas fa-pen-nib"></i>Signatories</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Requested By:</label>
                    <div class="officer-name-field"><input type="text" id="requested_by" data-designation-target="requested_by_designation" data-officer-autocomplete autocomplete="off"><div id="requested_by_dropdown" class="autocomplete-dropdown"></div></div>
                </div>
                <div class="form-group">
                    <label>Requested By Designation:</label>
                    <input type="text" id="requested_by_designation" placeholder="Position">
                </div>
                <div class="form-group">
                    <label>Approved By:</label>
                    <div class="officer-name-field"><input type="text" id="approved_by" data-designation-target="approved_by_designation" data-officer-autocomplete autocomplete="off"><div id="approved_by_dropdown" class="autocomplete-dropdown"></div></div>
                </div>
                <div class="form-group">
                    <label>Approved By Designation:</label>
                    <input type="text" id="approved_by_designation" placeholder="Position">
                </div>
                <div class="form-group">
                    <label>Inspection Officer:</label>
                    <div class="officer-name-field"><input type="text" id="inspection_officer" data-officer-autocomplete autocomplete="off"><div id="inspection_officer_dropdown" class="autocomplete-dropdown"></div></div>
                </div>
                <div class="form-group">
                    <label>Witness:</label>
                    <div class="officer-name-field"><input type="text" id="witness" data-officer-autocomplete autocomplete="off"><div id="witness_dropdown" class="autocomplete-dropdown"></div></div>
                </div>
            </div>
        </div>

            <button onclick="submitIIRUSP()" class="pill-btn pill-add" type="submit">
                <i class="fas fa-save"></i>SUBMIT IIRUSP
            </button>
            <a href="Semi_Expendables/iirusp.php">
                <button type="button" class="pill-btn pill-view"><i class="fas fa-ban"></i>Cancel</button>
            </a>
    </div>
</div>


<!-- JS LOGIC -->
<?php require dirname(__DIR__) . '/officer_dropdown_options.php'; ?>
<script>
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
    syncIiruspFields(row);
    return row;
}
function updateItemAllocations(semiId) {
    let rows = Array.from(document.querySelectorAll('.item-row')).filter(row => row.dataset.semiId === semiId);
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
        syncIiruspFields(row);
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
    if (event.target.matches('.disposal-qty')) updateItemAllocations(event.target.closest('.item-row').dataset.semiId);
});

    function syncIiruspFields(row) {
        const selected = row.querySelector('.item-checkbox').checked;
        const details = row.nextElementSibling;
        const mode = row.querySelector('.mode-select').value;
        const quantity = row.querySelector('.disposal-qty');
        quantity.disabled = !selected;
        quantity.required = selected;
        details.hidden = !selected || row.style.display === 'none';
        details.querySelectorAll('input').forEach(input => { input.disabled = !selected; });
        details.querySelectorAll('[data-disposal-mode]').forEach(group => {
            const active = selected && group.dataset.disposalMode === mode;
            group.hidden = !active;
            group.querySelectorAll('input').forEach(input => { input.disabled = !active; });
        });
    }
    function toggleIiruspItem(checkbox) {
        const row = checkbox.closest('tr');
        const quantity = row.querySelector('.disposal-qty');
        if (checkbox.checked && !quantity.value && Number(quantity.max) > 0) quantity.value = '1';
        if (!checkbox.checked) quantity.value = '';
        syncIiruspFields(row);
        quantity.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.getElementById('itemsTable').addEventListener('focusin', event => {
        if (event.target.matches('.item-sale-details input[type="number"]') && Number(event.target.value) === 0) event.target.select();
    });
    // 1. FILTERING
    function filterTable() {
        const input = document.getElementById('itemSearch').value.toLowerCase().trim();
        document.querySelectorAll('.item-row').forEach(row => {
            if (row.dataset.search.includes(input)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
            syncIiruspFields(row);
        });
    }

    // 2. LIVE TOTALS
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('disposal-qty')) {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseInt(row.querySelector('.disposal-qty').value) || 0;
                const cost = parseFloat(row.querySelector('.cost-cell').dataset.val) || 0;
                total += (qty * cost);

                // UX Highlight
                row.style.background = (qty > 0) ? '#fff5f5' : ''; 
            });
            document.getElementById('grand_total_display').innerText = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        }
    });

    // 3. SUBMIT
    async function submitIIRUSP() {
        const semiIds = new Set(Array.from(document.querySelectorAll('.item-row'), row => row.dataset.semiId));
        semiIds.forEach(updateItemAllocations);
        const selected = Array.from(document.querySelectorAll('.item-row')).filter(row => row.querySelector('.item-checkbox').checked);
        for (const row of selected) {
            syncIiruspFields(row);
            for (const input of [row.querySelector('.disposal-qty'), ...row.nextElementSibling.querySelectorAll('input:not(:disabled)')]) {
                if (!input.reportValidity()) return;
            }
        }
        const items = [];

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseInt(row.querySelector('.disposal-qty').value) || 0;

            if (qty > 0 && row.querySelector('.item-checkbox').checked) {
                const mode = row.querySelector('.mode-select').value;
                items.push({
                    date_acquired: row.cells[1].innerText.trim(),
                    property_no: row.querySelector('.prop-cell').innerText.trim(),
                    particulars: row.querySelector('.desc-cell').innerText.trim(),
                    unit: row.querySelector('.unit-cell').innerText.trim(),
                    unit_cost: row.querySelector('.cost-cell').dataset.val,
                    quantity: qty,
                    remarks: row.querySelector('.remarks-input').value.trim(),

                    disposal_mode: mode,
                    accumulated_impairment: row.nextElementSibling.querySelector('.item-accumulated_impairment').value || '0',
                    carrying_amount: row.nextElementSibling.querySelector('.item-carrying_amount').value || '0',
                    appraised_value: row.nextElementSibling.querySelector('.item-appraised_value').value || '0',
                    or_no: mode === 'Sale' ? row.nextElementSibling.querySelector('.sale-or-no').value.trim() : '',
                    sales_amount: mode === 'Sale' ? (row.nextElementSibling.querySelector('.sale-amount').value || '0') : '0',
                    disposal_others: mode === 'Other' ? row.nextElementSibling.querySelector('.other-specification').value.trim() : '',
                    // Map Modes
                    disposal_destruction: (mode === 'Destruction') ? qty : 0,
                    disposal_sale: (mode === 'Sale') ? qty : 0,
                    disposal_transfer: (mode === 'Transfer') ? qty : 0
                });
            }
        });

        if (items.length === 0) { alert('Please select items to dispose.'); return; }

        const fd = new FormData();
        // Header
        fd.append('iirusp_no', document.getElementById('iirusp_no').value);
        fd.append('as_at', document.getElementById('as_at').value);
        fd.append('entity_name', document.getElementById('entity_name').value);
        fd.append('fund_cluster', document.getElementById('fund_cluster').value);
        // Signatories
        fd.append('accountable_officer_name', document.getElementById('accountable_officer_name').value);
        fd.append('accountable_officer_designation', document.getElementById('accountable_officer_designation').value);
        fd.append('accountable_officer_station', document.getElementById('accountable_officer_station').value);
        fd.append('requested_by', document.getElementById('requested_by').value);
        fd.append('requested_by_designation', document.getElementById('requested_by_designation').value);
        fd.append('approved_by', document.getElementById('approved_by').value);
        fd.append('approved_by_designation', document.getElementById('approved_by_designation').value);
        fd.append('inspection_officer', document.getElementById('inspection_officer').value);
        fd.append('witness', document.getElementById('witness').value);

        // Items
        fd.append('items_json', JSON.stringify(items));

        try {
            const res = await fetch('Semi_Expendables/add_iirusp.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                alert('Success! IIRUSP saved.');
                window.location.href = 'Semi_Expendables/iirusp.php';
            } else {
                alert('Error: ' + json.message);
            }        } catch (e) {
            alert('Network error. Check console.');
            console.error(e);
        }
    }
</script>

</body>
</html>