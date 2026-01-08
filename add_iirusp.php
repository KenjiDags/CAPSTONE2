<?php
// ==========================================
// 1. BACKEND POST HANDLER (SAVES DATA)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean buffer to ensure JSON valid response
    ob_start();
    require 'config.php';
    require 'functions.php';
    ob_clean(); // Discard any include output
    header('Content-Type: application/json');
    ini_set('display_errors', 0); // Hide PHP warnings in response

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
        $stmt = $conn->prepare("INSERT INTO iirusp (iirusp_no, as_at, entity_name, fund_cluster, accountable_officer_name, accountable_officer_designation, accountable_officer_station, requested_by, approved_by, inspection_officer, witness) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $as_at = $_POST['as_at'] ?? date('Y-m-d');
        $ent = $_POST['entity_name'] ?? '';
        $fund = $_POST['fund_cluster'] ?? '';
        $acc_n = $_POST['accountable_officer_name'] ?? '';
        $acc_d = $_POST['accountable_officer_designation'] ?? '';
        $acc_s = $_POST['accountable_officer_station'] ?? '';
        $req = $_POST['requested_by'] ?? '';
        $app = $_POST['approved_by'] ?? '';
        $insp = $_POST['inspection_officer'] ?? '';
        $wit = $_POST['witness'] ?? '';

        $stmt->bind_param("sssssssssss", $iirusp_no, $as_at, $ent, $fund, $acc_n, $acc_d, $acc_s, $req, $app, $insp, $wit);
        
        if (!$stmt->execute()) throw new Exception("Header Error: " . $stmt->error);
        $iirusp_id = $stmt->insert_id;
        $stmt->close();

        // C. Process Items
        $item_stmt = $conn->prepare("INSERT INTO iirusp_items (iirusp_id, date_acquired, particulars, semi_expendable_property_no, quantity, unit, unit_cost, total_cost, disposal_sale, disposal_transfer, disposal_destruction, disposal_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Prepare Master Update (Deduct Stock)
        $update_master = $conn->prepare("UPDATE semi_expendable_property SET quantity = GREATEST(0, quantity - ?), quantity_balance = GREATEST(0, quantity_balance - ?), quantity_disposed = quantity_disposed + ? WHERE semi_expendable_property_no = ?");
        
        // Prepare History Insert
        $history_stmt = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity_disposed, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $prop_no = $item['property_no'];
            $qty = (int)$item['quantity'];
            $cost = (float)$item['unit_cost'];
            $total = $qty * $cost;
            
            // 1. Validate: Get current balance and check disposal qty
            $check_stmt = $conn->prepare("SELECT id, quantity_balance, office_officer_reissued, office_officer_issued FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
            $check_stmt->bind_param("s", $prop_no);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Item $prop_no not found");
            }
            
            $row = $result->fetch_assoc();
            $semi_id = $row['id'];
            $available_qty = (int)$row['quantity_balance'];
            $check_stmt->close();
            
            if ($qty > $available_qty) {
                throw new Exception("Item $prop_no: Cannot dispose $qty units. Only $available_qty available.");
            }

            // 2. Insert Item to IIRUSP Table
            $d_sale = (float)$item['disposal_sale'];
            $d_trans = (float)$item['disposal_transfer'];
            $d_destr = (float)$item['disposal_destruction'];
            $d_tot = (float)$qty; // Total disposed (must be float for bind_param 'd')
            
            $item_stmt->bind_param("isssisdddddds", 
                $iirusp_id, $item['date_acquired'], $item['particulars'], $prop_no, $qty, $item['unit'], 
                $cost, $total, $d_sale, $d_trans, $d_destr, $d_tot, $item['remarks']
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to insert item: " . $item_stmt->error);
            }

            // 3. Update Master Stock (SAFE USING PREPARED STATEMENT)
            $update_master->bind_param("iiis", $qty, $qty, $qty, $prop_no);
            if (!$update_master->execute()) {
                throw new Exception("Failed to update master: " . $update_master->error);
            }

            // 4. Add History Entry
            $holder = !empty($master['office_officer_reissued']) ? $master['office_officer_reissued'] : 
                      (!empty($master['office_officer_issued']) ? $master['office_officer_issued'] : 'Stock Room');
            $rem = "Disposed via IIRUSP " . $iirusp_no;
            $history_stmt->bind_param("issisdds", $semi_id, $as_at, $iirusp_no, $qty, $holder, $uc, $tc, $rem);
            
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
require 'config.php';
require 'functions.php';
ensure_iirusp_tables($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Add IIRUSP Form</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    .section-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .section-card h3 { margin-top: 0; color: #333; border-bottom: 2px solid #0056b3; display: inline-block; padding-bottom: 5px; margin-bottom: 15px; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px; color: #555; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    
    .table-frame { border: 1px solid #ccc; max-height: 400px; overflow: hidden; border-radius: 4px; }
    .table-viewport { overflow-y: auto; max-height: 400px; }
    
    #itemsTable { width: 100%; border-collapse: collapse; font-size: 12px; }
    #itemsTable thead tr { position: sticky; top: 0; z-index: 10; background: linear-gradient(180deg, #0056b3 0%, #004494 100%); color: white; }
    #itemsTable th { padding: 10px; text-align: left; font-weight: normal; }
    #itemsTable td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    #itemsTable tr:hover { background-color: #f0f7ff; }

    .search-input { width: 100%; padding: 10px; font-size: 14px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
    .search-input:focus { border-color: #0056b3; outline: none; }
    
    .cost-cell { font-family: monospace; font-weight: bold; color: #444; }
    .prop-cell { font-weight: bold; color: #0056b3; }
    .holder-cell { font-style: italic; color: #666; }
    
    .button-group { text-align: right; margin-top: 20px; }
    .button-group button { padding: 12px 25px; margin-left: 10px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    .btn-submit { background: #28a745; color: white; }
    .btn-submit:hover { background: #218838; }
</style>
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>

<div class="content">
    <h2>Add IIRUSP (Inventory & Inspection Report - Unserviceable Semi-Expendable Properties)</h2>

    <!-- HEADER FORM -->
    <div class="section-card">
        <h3>IIRUSP Details</h3>
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
                <input type="text" id="entity_name" value="TESDA-CAR">
            </div>
            <div class="form-group">
                <label>Fund Cluster:</label>
                <input type="text" id="fund_cluster" value="101">
            </div>
        </div>
        
        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
        
        <div class="form-grid">
            <div class="form-group">
                <label>Accountable Officer:</label>
                <input type="text" id="accountable_officer_name" placeholder="Name">
            </div>
            <div class="form-group">
                <label>Designation:</label>
                <input type="text" id="accountable_officer_designation" placeholder="Position">
            </div>
            <div class="form-group">
                <label>Station:</label>
                <input type="text" id="accountable_officer_station" placeholder="Office/Station">
            </div>
        </div>
    </div>

    <!-- GRID STYLE UI FOR SELECTING ITEMS -->
    <div class="section-card">
        <h3>Unserviceable Items (Search & Select)</h3>

        <!-- Search Bar -->
        <div style="margin-bottom: 10px;">
            <input type="text" id="itemSearch" class="search-input" 
                   placeholder="Search Property No, Item Description, or Holder Name..." 
                   onkeyup="filterTable()">
        </div>

        <div class="table-frame">
            <div class="table-viewport">
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th style="min-width:90px;">Date Acq.</th>
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
                        // --- NEW LOGIC: Calculate Balances per Officer (Like RegSPI) ---
                        $sql = "
                            SELECT 
                                h.date, 
                                p.semi_expendable_property_no, 
                                p.item_description, 
                                p.amount,
                                p.unit,
                                h.quantity_issued, h.office_officer_issued,
                                h.quantity_returned, h.office_officer_returned,
                                h.quantity_reissued, h.office_officer_reissued,
                                h.quantity_disposed
                            FROM semi_expendable_history h
                            JOIN semi_expendable_property p ON h.semi_id = p.id
                            WHERE (h.quantity_issued > 0 OR h.quantity_reissued > 0 OR h.quantity_returned > 0 OR h.quantity_disposed > 0)
                            ORDER BY p.item_description
                        ";
                        
                        $res = $conn->query($sql);
                        $accountability_map = [];

                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $p_no = $row['semi_expendable_property_no'];
                                
                                // Logic to find WHO moved the item
                                $officer = '';
                                $change = 0;

                                if ($row['quantity_issued'] > 0) {
                                    $officer = $row['office_officer_issued']; 
                                    $change = (int)$row['quantity_issued'];
                                } 
                                elseif ($row['quantity_reissued'] > 0) {
                                    // REISSUED means a Transfer TO someone (Like Gasmon)
                                    $officer = $row['office_officer_reissued'];
                                    $change = (int)$row['quantity_reissued'];
                                }
                                elseif ($row['quantity_returned'] > 0) {
                                    // RETURN means deducting from someone (Like Darylle)
                                    $officer = $row['office_officer_returned'] ?: $row['office_officer_issued'];
                                    $change = -1 * (int)$row['quantity_returned'];
                                }
                                elseif ($row['quantity_disposed'] > 0) {
                                    // DISPOSED means removing liability
                                    $officer = $row['office_officer_issued'];
                                    $change = -1 * (int)$row['quantity_disposed'];
                                }

                                // Standardize Key: "PropertyNo + Name"
                                if (!$officer) $officer = "Unassigned"; 
                                $key = $p_no . '||' . strtolower(trim($officer));

                                // Initialize
                                if (!isset($accountability_map[$key])) {
                                    $accountability_map[$key] = [
                                        'prop' => $p_no,
                                        'desc' => $row['item_description'],
                                        'holder' => $officer,
                                        'balance' => 0,
                                        'cost' => $row['amount'],
                                        'date' => $row['date'],
                                        'unit' => $row['unit']
                                    ];
                                }

                                // Apply Math
                                $accountability_map[$key]['balance'] += $change;
                            }
                        }

                        // --- RENDER ROWS ONLY FOR THOSE WHO HAVE ITEMS ---
                        foreach ($accountability_map as $item) {
                            if ($item['balance'] > 0) { // Only show if they actually hold items
                                $search = strtolower($item['prop'] . " " . $item['desc'] . " " . $item['holder']);
                    ?>
                        <tr class="item-row" data-search="<?= $search ?>">
                            <td><?= $item['date'] ?></td>
                            <td class="prop-cell"><?= htmlspecialchars($item['prop']) ?></td>
                            <td class="desc-cell"><?= htmlspecialchars($item['desc']) ?></td>
                            <td class="holder-cell" style="font-weight:bold; color:#0056b3;"><?= htmlspecialchars($item['holder']) ?></td>
                            
                            <!-- This now shows SPECIFIC QUANTITY for that Person -->
                            <td style="text-align:center; font-weight:bold; background:#e0f2fe;">
                                <?= $item['balance'] ?>
                            </td>
                            
                            <td class="cost-cell" data-val="<?= $item['cost'] ?>">₱<?= number_format($item['cost'], 2) ?></td>
                            
                            <td>
                                <!-- Max allows disposal only up to what they own -->
                                <input type="number" class="disposal-qty" min="0" max="<?= $item['balance'] ?>" placeholder="0">
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

                            <td class="unit-cell" style="display:none;"><?= $item['unit'] ?></td>
                        </tr>
                    <?php 
                            } 
                        } 
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div style="text-align:right; margin-bottom:30px;">
        <h3 style="display:inline-block; margin-right:20px;">Total Value: <span id="grand_total_display" style="color:#0056b3;">₱0.00</span></h3>
        <button onclick="submitIIRUSP()" style="padding:15px 30px; background:#28a745; color:white; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">
            SUBMIT IIRUSP
        </button>
    </div>

    <!-- FOOTER SIGNATORIES -->
    <div class="section-card">
        <h3>Signatories</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Requested By:</label>
                <input type="text" id="requested_by">
            </div>
            <div class="form-group">
                <label>Approved By:</label>
                <input type="text" id="approved_by">
            </div>
            <div class="form-group">
                <label>Inspection Officer:</label>
                <input type="text" id="inspection_officer">
            </div>
            <div class="form-group">
                <label>Witness:</label>
                <input type="text" id="witness">
            </div>
        </div>
    </div>

</div>

<!-- JS LOGIC -->
<script>
    // 1. FILTERING
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
        const items = [];
        
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseInt(row.querySelector('.disposal-qty').value) || 0;
            
            if (qty > 0) {
                const mode = row.querySelector('.mode-select').value;
                items.push({
                    date_acquired: row.cells[0].innerText.trim(),
                    property_no: row.querySelector('.prop-cell').innerText.trim(),
                    particulars: row.querySelector('.desc-cell').innerText.trim(),
                    unit: row.querySelector('.unit-cell').innerText.trim(),
                    unit_cost: row.querySelector('.cost-cell').dataset.val,
                    quantity: qty,
                    remarks: row.querySelector('.remarks-input').value.trim(),
                    
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
        fd.append('approved_by', document.getElementById('approved_by').value);
        fd.append('inspection_officer', document.getElementById('inspection_officer').value);
        fd.append('witness', document.getElementById('witness').value);

        // Items
        fd.append('items_json', JSON.stringify(items));

        try {
            const res = await fetch('add_iirusp.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                alert('Success! IIRUSP saved.');
                window.location.href = 'iirusp.php';
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