<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
ensure_iirusp_tables($conn);
ensure_iirusp_history($conn);
ensure_semi_expendable_history($conn);

$iirusp_id = isset($_GET['iirusp_id']) ? (int)$_GET['iirusp_id'] : 0;
$iirusp = null; 
$items = [];

if ($iirusp_id) {
    $stmt = $conn->prepare("SELECT * FROM iirusp WHERE iirusp_id=? LIMIT 1");
    $stmt->bind_param('i', $iirusp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $iirusp = $result->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM iirusp_items WHERE iirusp_id=? ORDER BY iirusp_item_id ASC");
    $stmt->bind_param('i', $iirusp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

if (!$iirusp) {
    echo 'IIRUSP not found.';
    exit;
}

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    ob_clean();
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    
    try {
        // Reverse previous disposals for this IIRUSP
        $prev_items = [];
        $stmt = $conn->prepare("SELECT * FROM iirusp_items WHERE iirusp_id = ?");
        $stmt->bind_param('i', $iirusp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $prev_items[] = $row;
        }
        $stmt->close();
        
        // Reverse stock deductions
        foreach ($prev_items as $item) {
            $prop_no = $item['semi_expendable_property_no'];
            $qty = (int)$item['quantity'];
            
            if (!empty($prop_no) && $qty > 0) {
                // Add back disposed qty to semi_expendable_property
                $stmt = $conn->prepare("UPDATE semi_expendable_property SET quantity = quantity + ?, quantity_disposed = GREATEST(0, quantity_disposed - ?), quantity_balance = quantity_balance + ? WHERE semi_expendable_property_no = ?");
                $stmt->bind_param("iiis", $qty, $qty, $qty, $prop_no);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->begin_transaction();
        
        // Update IIRUSP header
        $iirusp_no = trim($_POST['iirusp_no'] ?? '');
        $as_at = $_POST['as_at'] ?? date('Y-m-d');
        $entity_name = trim($_POST['entity_name'] ?? '');
        $fund_cluster = trim($_POST['fund_cluster'] ?? '');
        $accountable_officer_name = trim($_POST['accountable_officer_name'] ?? '');
        $accountable_officer_designation = trim($_POST['accountable_officer_designation'] ?? '');
        $accountable_officer_station = trim($_POST['accountable_officer_station'] ?? '');
        $requested_by = trim($_POST['requested_by'] ?? '');
        $approved_by = trim($_POST['approved_by'] ?? '');
        $inspection_officer = trim($_POST['inspection_officer'] ?? '');
        $witness = trim($_POST['witness'] ?? '');
        
        $stmt = $conn->prepare("UPDATE iirusp SET iirusp_no=?, as_at=?, entity_name=?, fund_cluster=?, accountable_officer_name=?, accountable_officer_designation=?, accountable_officer_station=?, requested_by=?, approved_by=?, inspection_officer=?, witness=? WHERE iirusp_id=?");
        $stmt->bind_param("sssssssssssi", $iirusp_no, $as_at, $entity_name, $fund_cluster, $accountable_officer_name, $accountable_officer_designation, $accountable_officer_station, $requested_by, $approved_by, $inspection_officer, $witness, $iirusp_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Update Error: " . $stmt->error);
        }
        $stmt->close();
        
        // Remove old items
        $conn->query("DELETE FROM iirusp_items WHERE iirusp_id=" . $iirusp_id);
        
        // Insert new items and update stock
        if (!empty($_POST['items_json'])) {
            $items_json = $_POST['items_json'];
            $items = json_decode($items_json, true);
            
            if (!empty($items) && is_array($items)) {
                $item_stmt = $conn->prepare("INSERT INTO iirusp_items (iirusp_id, date_acquired, particulars, semi_expendable_property_no, quantity, unit, unit_cost, total_cost, disposal_sale, disposal_transfer, disposal_destruction, disposal_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $update_master = $conn->prepare("UPDATE semi_expendable_property SET quantity = GREATEST(0, quantity - ?), quantity_balance = GREATEST(0, quantity_balance - ?), quantity_disposed = quantity_disposed + ? WHERE semi_expendable_property_no = ?");
                
                $history_stmt = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity_disposed, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($items as $item) {
                    $prop_no = $item['property_no'];
                    $qty = (int)$item['quantity'];
                    $cost = (float)$item['unit_cost'];
                    $total = $qty * $cost;
                    
                    // Validate: Get current balance
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
                    
                    // Insert Item to IIRUSP Table
                    $d_sale = (float)($item['disposal_sale'] ?? 0);
                    $d_trans = (float)($item['disposal_transfer'] ?? 0);
                    $d_destr = (float)($item['disposal_destruction'] ?? 0);
                    $d_tot = (float)$qty;
                    
                    $item_stmt->bind_param("isssisdddddds", 
                        $iirusp_id, $item['date_acquired'], $item['particulars'], $prop_no, $qty, $item['unit'], 
                        $cost, $total, $d_sale, $d_trans, $d_destr, $d_tot, $item['remarks']
                    );
                    
                    if (!$item_stmt->execute()) {
                        throw new Exception("Failed to insert item: " . $item_stmt->error);
                    }
                    
                    // Update Master Stock
                    $update_master->bind_param("iiis", $qty, $qty, $qty, $prop_no);
                    if (!$update_master->execute()) {
                        throw new Exception("Failed to update master: " . $update_master->error);
                    }
                    
                    // Add History Entry
                    $holder = !empty($row['office_officer_reissued']) ? $row['office_officer_reissued'] : 
                              (!empty($row['office_officer_issued']) ? $row['office_officer_issued'] : 'Stock Room');
                    $rem = "Disposed via IIRUSP " . $iirusp_no;
                    $history_stmt->bind_param("issisdds", $semi_id, $as_at, $iirusp_no, $qty, $holder, $cost, $total, $rem);
                    
                    if (!$history_stmt->execute()) {
                        throw new Exception("Failed to log history: " . $history_stmt->error);
                    }
                }
                
                $item_stmt->close();
                $update_master->close();
                $history_stmt->close();
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'IIRUSP updated successfully']);
        
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
<title>Edit IIRUSP Form</title>
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
    .btn-back { background: #6c757d; color: white; }
    .btn-back:hover { background: #5a6268; }
</style>
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>

<div class="content">
    <h2>Edit IIRUSP (Inventory & Inspection Report - Unserviceable Semi-Expendable Properties)</h2>

    <!-- HEADER FORM -->
    <div class="section-card">
        <h3>IIRUSP Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>IIRUSP No.:</label>
                <input type="text" id="iirusp_no" value="<?php echo htmlspecialchars($iirusp['iirusp_no'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>As At (Date):</label>
                <input type="date" id="as_at" value="<?php echo $iirusp['as_at'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Entity Name:</label>
                <input type="text" id="entity_name" value="<?php echo htmlspecialchars($iirusp['entity_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Fund Cluster:</label>
                <input type="text" id="fund_cluster" value="<?php echo htmlspecialchars($iirusp['fund_cluster'] ?? ''); ?>">
            </div>
        </div>
        
        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
        
        <div class="form-grid">
            <div class="form-group">
                <label>Accountable Officer:</label>
                <input type="text" id="accountable_officer_name" value="<?php echo htmlspecialchars($iirusp['accountable_officer_name'] ?? ''); ?>" placeholder="Name">
            </div>
            <div class="form-group">
                <label>Designation:</label>
                <input type="text" id="accountable_officer_designation" value="<?php echo htmlspecialchars($iirusp['accountable_officer_designation'] ?? ''); ?>" placeholder="Position">
            </div>
            <div class="form-group">
                <label>Station:</label>
                <input type="text" id="accountable_officer_station" value="<?php echo htmlspecialchars($iirusp['accountable_officer_station'] ?? ''); ?>" placeholder="Office/Station">
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Requested By:</label>
                <input type="text" id="requested_by" value="<?php echo htmlspecialchars($iirusp['requested_by'] ?? ''); ?>" placeholder="Name">
            </div>
            <div class="form-group">
                <label>Approved By:</label>
                <input type="text" id="approved_by" value="<?php echo htmlspecialchars($iirusp['approved_by'] ?? ''); ?>" placeholder="Name">
            </div>
            <div class="form-group">
                <label>Inspection Officer:</label>
                <input type="text" id="inspection_officer" value="<?php echo htmlspecialchars($iirusp['inspection_officer'] ?? ''); ?>" placeholder="Name">
            </div>
            <div class="form-group">
                <label>Witness:</label>
                <input type="text" id="witness" value="<?php echo htmlspecialchars($iirusp['witness'] ?? ''); ?>" placeholder="Name">
            </div>
        </div>
    </div>

    <!-- ITEMS SECTION -->
    <div class="section-card">
        <h3>Current Disposed Items</h3>
        <?php if (count($items) > 0): ?>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px;">
            <thead>
                <tr style="background: #f0f0f0; border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px; text-align: left;">Date Acquired</th>
                    <th style="padding: 10px; text-align: left;">Property No.</th>
                    <th style="padding: 10px; text-align: left;">Particulars</th>
                    <th style="padding: 10px; text-align: right;">Quantity</th>
                    <th style="padding: 10px; text-align: left;">Unit</th>
                    <th style="padding: 10px; text-align: right;">Unit Cost</th>
                    <th style="padding: 10px; text-align: right;">Total Cost</th>
                    <th style="padding: 10px; text-align: left;">Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;"><?php echo $item['date_acquired'] ? date('m/d/Y', strtotime($item['date_acquired'])) : '-'; ?></td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($item['particulars'] ?? ''); ?></td>
                    <td style="padding: 10px; text-align: right;"><?php echo $item['quantity']; ?></td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($item['unit'] ?? ''); ?></td>
                    <td style="padding: 10px; text-align: right;">₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                    <td style="padding: 10px; text-align: right;">₱<?php echo number_format($item['total_cost'], 2); ?></td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($item['remarks'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No items currently in this IIRUSP.</p>
        <?php endif; ?>
    </div>

    <div class="button-group">
        <a href="view_iirusp.php?iirusp_id=<?php echo $iirusp_id; ?>" class="btn-back" style="display: inline-block; padding: 12px 25px; text-decoration: none; border-radius: 5px;">Back to View</a>
        <button class="btn-submit" onclick="submitForm()">Update IIRUSP</button>
    </div>
</div>

<script>
function submitForm() {
    const data = new FormData();
    data.append('iirusp_no', document.getElementById('iirusp_no').value);
    data.append('as_at', document.getElementById('as_at').value);
    data.append('entity_name', document.getElementById('entity_name').value);
    data.append('fund_cluster', document.getElementById('fund_cluster').value);
    data.append('accountable_officer_name', document.getElementById('accountable_officer_name').value);
    data.append('accountable_officer_designation', document.getElementById('accountable_officer_designation').value);
    data.append('accountable_officer_station', document.getElementById('accountable_officer_station').value);
    data.append('requested_by', document.getElementById('requested_by').value);
    data.append('approved_by', document.getElementById('approved_by').value);
    data.append('inspection_officer', document.getElementById('inspection_officer').value);
    data.append('witness', document.getElementById('witness').value);
    data.append('items_json', JSON.stringify([])); // Empty items for now, can be extended
    
    fetch(window.location.href, {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('IIRUSP updated successfully!');
            window.location.href = 'view_iirusp.php?iirusp_id=<?php echo $iirusp_id; ?>';
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

</body>
</html>
