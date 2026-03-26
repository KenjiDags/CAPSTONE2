<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
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
        $stmt = $conn->prepare("INSERT INTO ppe_iirup (date_reported, PPE_no, particulars, quantity, depreciation, impairment_loss, carrying_amount, remarks, sale, transfer, destruction, other, total, appraised_value, or_no, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $date_reported = $_POST['date_reported'] ?? date('Y-m-d');
        $quantity = $_POST['quantity'] ?? 0;
        $depreciation = $_POST['depreciation'] ?? 0;
        $impairment_loss = $_POST['impairment_loss'] ?? 0;
        $carrying_amount = $_POST['carrying_amount'] ?? 0;
        $remarks = $_POST['remarks'] ?? '';
        $sale = $_POST['sale'] ?? 0;
        $transfer = $_POST['transfer'] ?? 0;
        $destruction = $_POST['destruction'] ?? 0;
        $other = $_POST['other'] ?? 0;
        $total = $_POST['total'] ?? 0;
        $appraised_value = $_POST['appraised_value'] ?? 0;
        $or_no = $_POST['or_no'] ?? '';
        $amount = $_POST['amount'] ?? 0;
        $stmt->bind_param("sssidddsiiiiidsd", $date_reported, $ppe_no_header, $particulars_header, $quantity, $depreciation, $impairment_loss, $carrying_amount, $remarks, $sale, $transfer, $destruction, $other, $total, $appraised_value, $or_no, $amount);
        if (!$stmt->execute()) throw new Exception("Header Error: " . $stmt->error);
        $ppe_iirup_id = $stmt->insert_id;
        $stmt->close();
        // Insert Items
        $item_stmt = $conn->prepare("INSERT INTO ppe_iirup_items (ppe_iirup_id, date_acquired, quantity, depreciation, impairment_loss, carrying_amount, remarks, sale, transfer, destruction, other, total, appraised_value, or_no, amount, particulars) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $update_stmt = $conn->prepare("UPDATE ppe_property SET quantity = quantity - ? WHERE id = ?");
        foreach ($items as $item) {
            $particulars = $item['particulars'] ?? '';
            $item_stmt->bind_param("isiddddsiiiddsss",
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
                $item['or_no'],
                $item['amount'],
                $particulars
            );
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to insert item: " . $item_stmt->error);
            }
            // Update ppe_property to subtract disposed quantity
            $ppe_id = $item['ppe_id'];
            $disposed_qty = $item['quantity'];
            $update_stmt->bind_param("ii", $disposed_qty, $ppe_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update item quantity: " . $update_stmt->error);
            }
        }
        $item_stmt->close();
        $update_stmt->close();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'PPE IIRUP saved successfully']);
    } catch (Exception $e) {
        if ($conn) $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Add PPE IIRUP Form</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    .section-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px; color: #555; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    .table-frame { border: 1px solid #ccc; max-height: 400px; overflow: hidden; border-radius: 4px; }
    .table-viewport { overflow-y: auto; max-height: 400px; }
    #itemsTable { font-size: 12px; margin-top: 0 !important; }
    #itemsTable thead tr { position: sticky; top: 0; z-index: 10; background: linear-gradient(180deg, #0056b3 0%, #004494 100%); color: white; }
    #itemsTable th { padding: 10px; text-align: left; font-weight: normal; }
    #itemsTable td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    #itemsTable tr:hover { background-color: #f0f7ff; }
    .search-input { width: 100%; padding: 10px; font-size: 14px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
    .search-input:focus { border-color: #0056b3; outline: none; }
    .prop-cell { font-weight: bold; color: #0056b3; }
    .holder-cell { font-style: italic; color: #666; }
    .button-group { text-align: right; margin-top: 20px; }
    .button-group button { padding: 12px 25px; margin-left: 10px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    .btn-submit { background: #28a745; color: white; }
    .btn-submit:hover { background: #218838; }
    .form-container h2::before { content: "\f1f8"; font-family: "Font Awesome 6 Free"; font-weight: 900; color: #3b82f6; margin-right: 12px; }
    .section-card h3 { border-bottom: 2px solid #3b82f6; padding-bottom: 8px; }
</style>
</head>
<body class="iirup-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <div class="form-container">
        <header class="page-header">
            <h1> <i class="fas fa-file-invoice"></i>Add PPE IIRUP (Inventory & Inspection Report - Unserviceable PPE)</h1>
            <p>Create a new Inventory & Inspection Report</p>
        </header>
        <div class="section-card">
            <h3> <i class="fas fa-info-circle"></i>PPE IIRUP Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date Reported:</label>
                    <input type="date" id="date_reported" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>PPE No.:</label>
                    <input type="text" id="PPE_no" placeholder="PPE No." readonly>
                </div>
                <div class="form-group">
                    <label>Property No.:</label>
                    <input type="text" id="property_no" placeholder="Property No." readonly>
                </div>
                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" id="quantity" min="0" value="0">
                </div>

                <div class="form-group">
                    <label>Depreciation:</label>
                    <input type="number" id="depreciation" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Impairment Loss:</label>
                    <input type="number" id="impairment_loss" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Carrying Amount:</label>
                    <input type="number" id="carrying_amount" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Remarks:</label>
                    <input type="text" id="remarks" placeholder="Remarks">
                </div>
                <div class="form-group">
                    <label>Sale:</label>
                    <input type="number" id="sale" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Transfer:</label>
                    <input type="number" id="transfer" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Destruction:</label>
                    <input type="number" id="destruction" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Other:</label>
                    <input type="number" id="other" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Total:</label>
                    <input type="number" id="total" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Appraised Value:</label>
                    <input type="number" id="appraised_value" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>OR No.:</label>
                    <input type="text" id="or_no" placeholder="OR No.">
                </div>
                <div class="form-group">
                    <label>Amount:</label>
                    <input type="number" id="amount" min="0" step="0.01" value="0">
                </div>
            </div>
        </div>
        <div class="section-card">
            <h3> <i class="fas fa-list"></i>Unserviceable PPE Items (Search & Select)</h3>
            <div style="margin-bottom: 10px;">
                <input type="text" id="itemSearch" class="search-input" placeholder="Search Property No, Item Description, or Holder Name..." onkeyup="filterTable()">
            </div>
            <div class="table-frame">
                <div class="table-viewport">
                    <table id="itemsTable">
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
                        $sql = "SELECT p.id, p.date_acquired, p.PPE_no, p.item_name, p.item_description, p.unit, p.quantity, p.custodian, p.amount, p.remarks, pc.property_no FROM ppe_property p LEFT JOIN ppe_pc pc ON p.PPE_no = pc.ppe_property_no WHERE p.quantity > 0 ORDER BY p.item_name";
                        $res = $conn->query($sql);
                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $search = strtolower($row['PPE_no'] . " " . $row['item_name'] . " " . $row['item_description']);
                        ?>
                        <tr class="item-row" data-search="<?= $search ?>" data-ppe-id="<?= $row['id'] ?>" data-date-acquired="<?= htmlspecialchars($row['date_acquired']) ?>" data-property-no="<?= htmlspecialchars($row['property_no']) ?>" data-item-name="<?= htmlspecialchars($row['item_name']) ?>" data-item-description="<?= htmlspecialchars($row['item_description']) ?>">
                            <td><input type="checkbox" class="item-checkbox" onclick="fillInputsFromRow(this)"></td>
                            <td><?= htmlspecialchars($row['date_acquired']) ?></td>
                            <td class="prop-cell"><?= htmlspecialchars($row['PPE_no']) ?></td>
                            <td class="desc-cell"><?= htmlspecialchars($row['item_name'] . ' - ' . $row['item_description']) ?></td>
                            <td class="holder-cell"><?= htmlspecialchars($row['custodian']) ?></td>
                            <td style="text-align:center; font-weight:bold; background:#e0f2fe;">
                                <?= (int)$row['quantity'] ?>
                            </td>
                            <td class="currency" data-val="<?= $row['amount'] ?>">₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                                <input type="number" class="disposal-qty" min="0" max="<?= (int)$row['quantity'] ?>" placeholder="0">
                            </td>
                            <td>
                                <input type="text" class="remarks-input" placeholder="e.g. Broken">
                            </td>
                            <td>
                                <select class="mode-select">
                                    <option value="Destruction">Destruction</option>
                                    <option value="Sale">Sale</option>
                                    <option value="Transfer">Transfer</option>
                                </select>
                            </td>
                            <td class="unit-cell" style="display:none;">
                                <?= htmlspecialchars($row['unit']) ?>
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
        </div>
        <button onclick="submitIIRUP()" class="pill-btn pill-add" type="submit">
            <i class="fas fa-save"></i>SUBMIT PPE IIRUP
        </button>
        <a href="PPE_iirup.php">
            <button type="button" class="pill-btn pill-view"><i class="fas fa-ban"></i>Cancel</button>
        </a>
    </div>
</div>
<script>
function filterTable() {
    const input = document.getElementById('itemSearch').value.toLowerCase().trim();
    document.querySelectorAll('.item-row').forEach(row => {
        if (row.dataset.search.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
function submitIIRUP() {
    const items = [];
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseInt(row.querySelector('.disposal-qty').value) || 0;
        if (qty > 0) {
            const mode = row.querySelector('.mode-select').value;
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
                sale: (mode === 'Sale') ? qty : 0,
                transfer: (mode === 'Transfer') ? qty : 0,
                destruction: (mode === 'Destruction') ? qty : 0,
                other: 0,
                total: qty,
                appraised_value: 0,
                or_no: '',
                amount: qty * unitCost
            });
        }
    });
    if (items.length === 0) { alert('Please select items to dispose.'); return; }
    const fd = new FormData();
    fd.append('date_reported', document.getElementById('date_reported').value);
    fd.append('quantity', document.getElementById('quantity').value);
    fd.append('depreciation', document.getElementById('depreciation').value);
    fd.append('impairment_loss', document.getElementById('impairment_loss').value);
    fd.append('carrying_amount', document.getElementById('carrying_amount').value);
    fd.append('remarks', document.getElementById('remarks').value);
    fd.append('sale', document.getElementById('sale').value);
    fd.append('transfer', document.getElementById('transfer').value);
    fd.append('destruction', document.getElementById('destruction').value);
    fd.append('other', document.getElementById('other').value);
    fd.append('total', document.getElementById('total').value);
    fd.append('appraised_value', document.getElementById('appraised_value').value);
    fd.append('or_no', document.getElementById('or_no').value);
    fd.append('amount', document.getElementById('amount').value);
    fd.append('items_json', JSON.stringify(items));
    fetch('add_iirup.php', {
        method: 'POST',
        body: fd
    }).then(res => res.json()).then(json => {
        if (json.success) {
            alert('Success! PPE IIRUP saved.');
            window.location.href = 'PPE_iirup.php';
        } else {
            alert('Error: ' + json.message);
        }
    }).catch(e => {
        alert('Network error. Check console.');
        console.error(e);
    });
}
function fillInputsFromRow(checkbox) {
    if (!checkbox.checked) return;
    const row = checkbox.closest('tr');
    // Extract item_name and item_description from the desc-cell
    const descCell = row.querySelector('.desc-cell').textContent.trim();
    let itemName = '', itemDescription = '';
    if (descCell.includes(' - ')) {
        [itemName, itemDescription] = descCell.split(' - ', 2);
    } else {
        itemName = descCell;
        itemDescription = '';
    }
    // Set particulars as item_name + item_description
    document.getElementById('date_reported').value = row.dataset.dateAcquired;
    document.getElementById('PPE_no').value = row.querySelector('.prop-cell').textContent.trim();
    document.getElementById('property_no').value = row.dataset.propertyNo;
    document.getElementById('quantity').value = row.cells[5].textContent.trim();
    // No unit cost input to fill anymore
    document.getElementById('remarks').value = row.querySelector('.remarks-input').value.trim();
}
</script>
</body>
</html>
