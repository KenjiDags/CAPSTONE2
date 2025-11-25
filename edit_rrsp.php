<?php
require 'config.php';
require 'functions.php';
$rrsp_id = isset($_GET['rrsp_id']) ? (int)$_GET['rrsp_id'] : 0;
$rrsp = null; $items = [];
if ($rrsp_id) {
    $h = $conn->prepare("SELECT * FROM rrsp WHERE rrsp_id=? LIMIT 1");
    $h->bind_param('i', $rrsp_id);
    $h->execute();
    $rr = $h->get_result();
    $rrsp = $rr->fetch_assoc();
    $h->close();
    $i = $conn->prepare("SELECT * FROM rrsp_items WHERE rrsp_id=? ORDER BY rrsp_item_id ASC");
    $i->bind_param('i', $rrsp_id);
    $i->execute();
    $res = $i->get_result();
    while ($r = $res->fetch_assoc()) { $items[] = $r; }
    $i->close();
}
if (!$rrsp) { echo 'RRSP not found.'; exit; }

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rrsp_id'])) {
  // Reverse previous ICS/semi_expendable changes for this RRSP
  $prev_items = [];
  $prevQ = $conn->prepare("SELECT * FROM rrsp_items WHERE rrsp_id = ?");
  $prevQ->bind_param('i', $_POST['rrsp_id']);
  $prevQ->execute();
  $prevRes = $prevQ->get_result();
  while ($row = $prevRes->fetch_assoc()) { $prev_items[] = $row; }
  $prevQ->close();
  foreach ($prev_items as $pit) {
    $ics = $pit['ics_no'];
    $qty = (int)$pit['quantity'];
    if ($ics !== '' && $qty > 0) {
      // 1. Add back returned qty to ICS item
      $icsItemQ = $conn->prepare("SELECT ii.ics_item_id, ii.quantity, ii.stock_number FROM ics_items ii INNER JOIN ics i ON i.ics_id = ii.ics_id WHERE i.ics_no = ? OR ii.stock_number = ? LIMIT 1");
      $icsItemQ->bind_param('ss', $ics, $ics);
      $icsItemQ->execute();
      $icsItemRes = $icsItemQ->get_result();
      $icsItem = $icsItemRes && $icsItemRes->num_rows > 0 ? $icsItemRes->fetch_assoc() : null;
      $icsItemQ->close();
      if ($icsItem) {
        $ics_item_id = (int)$icsItem['ics_item_id'];
        $ics_qty = (float)$icsItem['quantity'];
        $new_qty = $ics_qty + $qty;
        $u = $conn->prepare("UPDATE ics_items SET quantity = ? WHERE ics_item_id = ?");
        $u->bind_param('di', $new_qty, $ics_item_id);
        $u->execute();
        $u->close();
        // 2. Subtract returned qty from semi_expendable_property
        $semiQ = $conn->prepare("SELECT id, quantity_returned, quantity_balance FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
        $semiQ->bind_param('s', $icsItem['stock_number']);
        $semiQ->execute();
        $semiRes = $semiQ->get_result();
        $semi = $semiRes && $semiRes->num_rows > 0 ? $semiRes->fetch_assoc() : null;
        $semiQ->close();
        if ($semi) {
          $semi_id = (int)$semi['id'];
          $new_returned = max(0, (int)$semi['quantity_returned'] - $qty);
          $new_balance = max(0, (int)$semi['quantity_balance'] - $qty);
          $u2 = $conn->prepare("UPDATE semi_expendable_property SET quantity_returned = ?, quantity_balance = ? WHERE id = ?");
          $u2->bind_param('iii', $new_returned, $new_balance, $semi_id);
          $u2->execute();
          $u2->close();
        }
      }
    }
  }
  $rrsp_id = (int)$_POST['rrsp_id'];
  $entity = trim($_POST['entity_name'] ?? '');
  $fund = trim($_POST['fund_cluster'] ?? '');
  $rrsp_no = trim($_POST['rrsp_no'] ?? '');
  $date_prepared = $_POST['date_prepared'] ?? date('Y-m-d');
  $returned_by = trim($_POST['returned_by'] ?? '');
  $returned_date = $_POST['returned_date'] ?? null;
  $received_by = trim($_POST['received_by'] ?? '');
  $received_date = $_POST['received_date'] ?? null;
  $remarks = trim($_POST['remarks'] ?? '');

  // Update RRSP header
  $stmt = $conn->prepare("UPDATE rrsp SET rrsp_no=?, date_prepared=?, entity_name=?, fund_cluster=?, returned_by=?, received_by=?, returned_date=?, received_date=?, remarks=? WHERE rrsp_id=?");
  $stmt->bind_param('sssssssssi', $rrsp_no, $date_prepared, $entity, $fund, $returned_by, $received_by, $returned_date, $received_date, $remarks, $rrsp_id);
  $stmt->execute();
  $stmt->close();

  // Remove old items
  $conn->query("DELETE FROM rrsp_items WHERE rrsp_id=" . $rrsp_id);

  // Insert new items and update ICS/semi_expendable_property
  if (!empty($_POST['item_description']) && is_array($_POST['item_description'])) {
    $descArr = $_POST['item_description'];
    $qtyArr = $_POST['quantity'] ?? [];
    $icsArr = $_POST['ics_no'] ?? [];
    $endArr = $_POST['end_user'] ?? [];
    $remArr = $_POST['item_remarks'] ?? [];
    $ucArr = $_POST['unit_cost'] ?? [];
    $n = count($descArr);
    $ist = $conn->prepare("INSERT INTO rrsp_items (rrsp_id,item_description,quantity,ics_no,end_user,item_remarks,unit_cost,total_amount) VALUES (?,?,?,?,?,?,?,?)");
    for ($i = 0; $i < $n; $i++) {
      $desc = trim($descArr[$i] ?? '');
      $qty = (int)($qtyArr[$i] ?? 0);
      $ics = trim($icsArr[$i] ?? '');
      $end = isset($endArr[$i]) ? trim($endArr[$i]) : '';
      $iremarks = isset($remArr[$i]) ? trim($remArr[$i]) : '';
      $uc = isset($ucArr[$i]) ? (float)$ucArr[$i] : 0.0;
      $tot = $qty * $uc;
      $ist->bind_param('isisssdd', $rrsp_id, $desc, $qty, $ics, $end, $iremarks, $uc, $tot);
      $ist->execute();
      $rrsp_item_id = $conn->insert_id;

      // Add to rrsp_history
      $hStmt = $conn->prepare("INSERT INTO rrsp_history (rrsp_id, rrsp_item_id, ics_no, item_description, quantity, unit_cost, total_amount, end_user, item_remarks) VALUES (?,?,?,?,?,?,?,?,?)");
      if($hStmt){
        $hStmt->bind_param('iissiddds',$rrsp_id,$rrsp_item_id,$ics,$desc,$qty,$uc,$tot,$end,$iremarks);
        $hStmt->execute();
        $hStmt->close();
      }

      // --- ICS deduction and SEMI update logic (like add_rrsp.php) ---
      if ($ics !== '' && $qty > 0) {
        // 1. Deduct returned qty from ICS item (lookup by joining ics_items to ics for ics_no, or by stock_number)
        $icsItemQ = $conn->prepare("SELECT ii.ics_item_id, ii.quantity, ii.stock_number FROM ics_items ii INNER JOIN ics i ON i.ics_id = ii.ics_id WHERE i.ics_no = ? OR ii.stock_number = ? LIMIT 1");
        $icsItemQ->bind_param('ss', $ics, $ics);
        $icsItemQ->execute();
        $icsItemRes = $icsItemQ->get_result();
        $icsItem = $icsItemRes && $icsItemRes->num_rows > 0 ? $icsItemRes->fetch_assoc() : null;
        $icsItemQ->close();
        if ($icsItem) {
          $ics_item_id = (int)$icsItem['ics_item_id'];
          $ics_qty = (float)$icsItem['quantity'];
          $new_qty = max(0, $ics_qty - $qty);
          $u = $conn->prepare("UPDATE ics_items SET quantity = ? WHERE ics_item_id = ?");
          $u->bind_param('di', $new_qty, $ics_item_id);
          $u->execute();
          $u->close();
          // 2. Add returned qty to semi_expendable_property
          $semiQ = $conn->prepare("SELECT id, quantity_returned, quantity_balance FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
          $semiQ->bind_param('s', $icsItem['stock_number']);
          $semiQ->execute();
          $semiRes = $semiQ->get_result();
          $semi = $semiRes && $semiRes->num_rows > 0 ? $semiRes->fetch_assoc() : null;
          $semiQ->close();
          if ($semi) {
            $semi_id = (int)$semi['id'];
            $new_returned = (int)$semi['quantity_returned'] + $qty;
            $new_balance = (int)$semi['quantity_balance'] + $qty;
            $u2 = $conn->prepare("UPDATE semi_expendable_property SET quantity_returned = ?, quantity_balance = ? WHERE id = ?");
            $u2->bind_param('iii', $new_returned, $new_balance, $semi_id);
            $u2->execute();
            $u2->close();
            // Log to semi_expendable_history if needed (optional)
          }
        }
      }
      // --- END ICS/SEMI logic ---
    }
    $ist->close();
  }

  // Redirect back to RRSP list or view page
  header('Location: rrsp.php');
  exit();
}

// AJAX JSON update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['save'])) {
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) { echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }
    $stmt = $conn->prepare("UPDATE rrsp SET rrsp_no=?, date_prepared=?, entity_name=?, fund_cluster=?, returned_by=?, received_by=?, returned_date=?, received_date=?, remarks=? WHERE rrsp_id=?");
    $stmt->bind_param('sssssssssi', $data['rrsp_no'], $data['date_prepared'], $data['entity_name'], $data['fund_cluster'], $data['returned_by'], $data['received_by'], $data['returned_date'], $data['received_date'], $data['remarks'], $rrsp_id);
    if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>'Header update failed']); exit; }
    $stmt->close();
    $conn->query("DELETE FROM rrsp_items WHERE rrsp_id=".$rrsp_id);
    if (!empty($data['items'])) {
        $itstmt = $conn->prepare("INSERT INTO rrsp_items (rrsp_id,item_description,quantity,ics_no,end_user,item_remarks,unit_cost,total_amount) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($data['items'] as $it) {
            $desc = $it['item_description'];
            $qty = (int)$it['quantity'];
            $ics = $it['ics_no'];
            $end = $it['end_user'];
            $rem = $it['item_remarks'];
            $uc = (float)$it['unit_cost'];
            $tot = $qty * $uc;
            $itstmt->bind_param('isisssdd', $rrsp_id, $desc, $qty, $ics, $end, $rem, $uc, $tot);
            $itstmt->execute();
        }
        $itstmt->close();
    }
    echo json_encode(['success'=>true]); exit;
}

// Build semi-expendable quick list for ICS numbers
$semi=[]; $q=$conn->query("SELECT semi_expendable_property_no,item_description,amount,office_officer_issued FROM semi_expendable_property ORDER BY item_description");
if($q){ while($r=$q->fetch_assoc()){ $semi[]=$r; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit RRSP</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
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
    /* Spacing improvements */
    #itemsTable th, #itemsTable td { padding: 10px 12px; vertical-align: middle; }
    #itemsTable thead th { height: 44px; }
    .search-container { margin: 8px 0 12px !important; }
    .form-grid .form-group input, .form-grid .form-group select, .form-grid .form-group textarea { padding: 8px 10px; }
  </style>
</head>
<body>
  <div class="edit-ics-page content edit-ris-page">
    <h2>Edit RRSP Form</h2>

    <form method="post" action="">
      <input type="hidden" name="rrsp_id" value="<?php echo $rrsp_id; ?>">
      
      <div class="section-card">
        <h3>RRSP Details</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Entity Name:</label>
            <input type="text" id="entity_name" name="entity_name" value="<?= htmlspecialchars($rrsp['entity_name']) ?>" required />
          </div>
          <div class="form-group">
            <label>Fund Cluster:</label>
            <input type="text" id="fund_cluster" name="fund_cluster" value="<?= htmlspecialchars($rrsp['fund_cluster']) ?>" />
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>RRSP No.:</label>
            <input type="text" id="rrsp_no" name="rrsp_no" value="<?= htmlspecialchars($rrsp['rrsp_no']) ?>" readonly style="background-color: #f5f5f5;" />
          </div>
          <div class="form-group">
            <label>Date Prepared:</label>
            <input type="date" id="date_prepared" name="date_prepared" value="<?= htmlspecialchars($rrsp['date_prepared']) ?>" required />
          </div>
        </div>
      </div>

      <div class="section-card">
        <h3>RRSP Items</h3>
        
        <div class="search-container">
          <input type="text" id="itemSearch" class="search-input" placeholder="Start typing to search items..." onkeyup="filterItems()">
        </div>
        
        <div class="table-frame">
          <div class="table-viewport">
            <table id="itemsTable" tabindex="-1">
              <thead>
                <tr>
                  <th>Description</th>
                  <th>Qty on Hand</th>
                  <th>Return Qty</th>
                  <th>ICS No.</th>
                  <th>End-user</th>
                  <th>Remarks</th>
                  <th>Unit Cost</th>
                </tr>
              </thead>
              <tbody>
              <?php
              // Helper: get qty on hand for ICS item
              function get_qty_on_hand($conn, $ics_no) {
                $stmt = $conn->prepare("SELECT ii.quantity FROM ics_items ii INNER JOIN ics i ON i.ics_id = ii.ics_id WHERE i.ics_no = ? LIMIT 1");
                $stmt->bind_param('s', $ics_no);
                $stmt->execute();
                $res = $stmt->get_result();
                $qty = 0;
                if ($row = $res->fetch_assoc()) $qty = (float)$row['quantity'];
                $stmt->close();
                return $qty;
              }
              foreach($items as $idx => $it):
                $qty_on_hand = get_qty_on_hand($conn, $it['ics_no']);
                $return_qty = (int)$it['quantity'];
                $display_qty_on_hand = $qty_on_hand + $return_qty;
                $tot = $return_qty * (float)$it['unit_cost'];
              ?>
                <tr>
                  <td><input type="text" name="item_description[]" value="<?= htmlspecialchars($it['item_description']) ?>" style="width:180px;" required /></td>
                  <td><input type="number" value="<?= $display_qty_on_hand ?>" readonly style="width:80px; background:#f5f5f5;" tabindex="-1" class="qty-on-hand" /></td>
                  <td><input type="number" name="quantity[]" class="qty-input" value="<?= $return_qty ?>" min="0" max="<?= $display_qty_on_hand ?>" style="width:80px;" required /></td>
                  <td><input type="text" name="ics_no[]" value="<?= htmlspecialchars($it['ics_no']) ?>" style="width:120px;" required /></td>
                  <td><input type="text" name="end_user[]" value="<?= htmlspecialchars($it['end_user'] ?? '') ?>" style="width:120px;" /></td>
                  <td><input type="text" name="item_remarks[]" value="<?= htmlspecialchars($it['item_remarks']) ?>" style="width:120px;" /></td>
                  <td><input type="number" name="unit_cost[]" class="uc-input" value="<?= htmlspecialchars($it['unit_cost']) ?>" min="0" step="0.01" style="width:100px;" required /></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="section-card">
        <h3>Signatories</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Returned By:</label>
            <input type="text" id="returned_by" name="returned_by" value="<?= htmlspecialchars($rrsp['returned_by']) ?>" required />
          </div>
          <div class="form-group">
            <label>Returned Date:</label>
            <input type="date" id="returned_date" name="returned_date" value="<?= htmlspecialchars($rrsp['returned_date']) ?>" />
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Received By:</label>
            <input type="text" id="received_by" name="received_by" value="<?= htmlspecialchars($rrsp['received_by']) ?>" required />
          </div>
          <div class="form-group">
            <label>Received Date:</label>
            <input type="date" id="received_date" name="received_date" value="<?= htmlspecialchars($rrsp['received_date']) ?>" />
          </div>
        </div>
      </div>

      <button type="submit">Update RRSP</button>
      <a href="rrsp.php" style="margin-left: 10px;">
        <button type="button">Cancel</button>
      </a>
    </form>
  </div>

  <script>
    function filterItems() {
      const searchValue = document.getElementById('itemSearch').value.toLowerCase().trim();
      const itemRows = document.querySelectorAll('#itemsTable tbody tr');
      itemRows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
      });
    }
    // Enforce max for returned qty
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.qty-input').forEach(function(input) {
        input.addEventListener('input', function() {
          var max = parseFloat(input.max);
          var val = parseFloat(input.value);
          if (!isNaN(max) && val > max) input.value = max;
          if (val < 0) input.value = 0;
        });
      });
    });
  </script>
</body>
</html>