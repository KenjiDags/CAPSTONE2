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
                  <th>Quantity</th>
                  <th>ICS No.</th>
                  <th>End-user</th>
                  <th>Remarks</th>
                  <th>Unit Cost</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach($items as $it): $tot=(float)$it['quantity']*(float)$it['unit_cost']; ?>
                <tr>
                  <td><?= htmlspecialchars($it['item_description']) ?><input type="hidden" name="item_description[]" value="<?= htmlspecialchars($it['item_description']) ?>" /></td>
                  <td><input type="number" name="quantity[]" value="<?= (int)$it['quantity'] ?>" min="0" style="width:80px;" /></td>
                  <td><?= htmlspecialchars($it['ics_no']) ?><input type="hidden" name="ics_no[]" value="<?= htmlspecialchars($it['ics_no']) ?>" /></td>
                  <td><?= htmlspecialchars($rrsp['returned_by']) ?></td>
                  <td><input type="text" name="item_remarks[]" value="<?= htmlspecialchars($it['item_remarks']) ?>" /></td>
                  <td>₱<?= number_format($it['unit_cost'],2) ?><input type="hidden" name="unit_cost[]" value="<?= htmlspecialchars($it['unit_cost']) ?>" /></td>
                  <td>₱<?= number_format($tot,2) ?></td>
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
      <a href="<?php echo 'view_rrsp.php?rrsp_id=' . $rrsp_id; ?>" style="margin-left: 10px;">
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
  </script>
</body>
</html>