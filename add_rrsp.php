<?php
require 'config.php';
require 'functions.php';

// Helper: compute next RRSP number for a given prepared date (YYYY-MM-SSSS)
function get_next_rrsp_no(mysqli $conn, string $date_prepared): string {
  $ts = strtotime($date_prepared ?: date('Y-m-d'));
  $ym = date('Y-m', $ts);
  $nextSerial = 1;
  if ($stmt = $conn->prepare("SELECT MAX(rrsp_no) AS max_no FROM rrsp WHERE rrsp_no LIKE CONCAT(?, '-%')")) {
    $stmt->bind_param('s', $ym);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($res && ($row = $res->fetch_assoc())) {
        $maxNo = (string)($row['max_no'] ?? '');
        if ($maxNo !== '' && preg_match('/^'.preg_quote($ym, '/').'-(\d{4})$/', $maxNo, $m)) {
          $nextSerial = (int)$m[1] + 1;
        }
      }
      if ($res) { $res->close(); }
    }
    $stmt->close();
  }
  $serialStr = str_pad((string)$nextSerial, 4, '0', STR_PAD_LEFT);
  return $ym . '-' . $serialStr;
}

// Lightweight endpoint to fetch next rrsp_no via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'next_rrsp_no') {
  header('Content-Type: application/json');
  $dateParam = isset($_GET['date']) ? (string)$_GET['date'] : date('Y-m-d');
  $next = get_next_rrsp_no($conn, $dateParam);
  echo json_encode(['next_rrsp_no' => $next]);
  exit;
}

// Handle submission (standard form POST)
if($_SERVER['REQUEST_METHOD']==='POST'){
  if (ob_get_length()) { ob_clean(); }
  header('Content-Type: application/json');
  try {
    $rrsp_no = trim($_POST['rrsp_no'] ?? '');
    $date_prepared = $_POST['date_prepared'] ?? date('Y-m-d');
    if ($rrsp_no === '') {
      $rrsp_no = get_next_rrsp_no($conn, $date_prepared);
    } else {
      $ymFromDate = date('Y-m', strtotime($date_prepared));
      if (strpos($rrsp_no, $ymFromDate . '-') !== 0) {
        $rrsp_no = get_next_rrsp_no($conn, $date_prepared);
      }
    }
    $entity = trim($_POST['entity_name'] ?? '');
    $fund = trim($_POST['fund_cluster'] ?? '');
    $returned_by = trim($_POST['returned_by'] ?? '');
    $returned_date = $_POST['returned_date'] ?? null;
    $received_by = trim($_POST['received_by'] ?? '');
    $received_date = $_POST['received_date'] ?? null;
    $remarks = trim($_POST['remarks'] ?? '');
    $items_json = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json,true) ?: [];
    if($rrsp_no===''){ echo json_encode(['success'=>false,'message'=>'RRSP number required']); exit; }
    ensure_rrsp_history($conn);
    if($stmt=$conn->prepare("INSERT INTO rrsp (rrsp_no,date_prepared,entity_name,fund_cluster,returned_by,received_by,returned_date,received_date,remarks) VALUES (?,?,?,?,?,?,?,?,?)")){
      $stmt->bind_param('sssssssss',$rrsp_no,$date_prepared,$entity,$fund,$returned_by,$received_by,$returned_date,$received_date,$remarks);
      if(!$stmt->execute()){ echo json_encode(['success'=>false,'message'=>'Failed to save RRSP header']); exit; }
      $rrsp_id = $stmt->insert_id; $stmt->close();
      if(!empty($items)){
        if($ist=$conn->prepare("INSERT INTO rrsp_items (rrsp_id,item_description,quantity,ics_no,end_user,item_remarks,unit_cost,total_amount) VALUES (?,?,?,?,?,?,?,?)")){
          foreach($items as $it){
            $desc=trim($it['description']??'');
            $qty=(int)($it['quantity']??0);
            $ics=trim($it['ics_no']??'');
            $end=trim($it['end_user']??'');
            $iremarks=trim($it['remarks']??'');
            $uc=(float)($it['unit_cost']??0); $tot=$qty*$uc;
            $ist->bind_param('isisssdd',$rrsp_id,$desc,$qty,$ics,$end,$iremarks,$uc,$tot);
            if($ist->execute()){
              $rrsp_item_id = $ist->insert_id;
              // Log to rrsp_history
              $hStmt = $conn->prepare("INSERT INTO rrsp_history (rrsp_id, rrsp_item_id, ics_no, item_description, quantity, unit_cost, total_amount, end_user, item_remarks) VALUES (?,?,?,?,?,?,?,?,?)");
              if($hStmt){
                $hStmt->bind_param('iissiddds',$rrsp_id,$rrsp_item_id,$ics,$desc,$qty,$uc,$tot,$end,$iremarks);
                $hStmt->execute();
                $hStmt->close();
              } 
              // --- ICS deduction and SEMI update logic (match ITR logic) ---
              if ($ics !== '' && $qty > 0) {
                // Deduct returned qty from ICS item (lookup by ics_no or stock_number)
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

                  // Log to ics_history for ICS export (Returned)
                  if (function_exists('ensure_ics_history')) { ensure_ics_history($conn); }
                  $icsHistoryStmt = $conn->prepare("INSERT INTO ics_history (ics_id, ics_item_id, stock_number, description, unit, quantity_before, quantity_after, quantity_change, unit_cost, total_cost_before, total_cost_after, reference_type, reference_id, reference_no, reference_details) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                  $ics_id_val = null;
                  $unit = null;
                  $desc = $desc ?? ($icsItem['description'] ?? '');
                  // Fetch ics_id and unit if not present
                  $icsMetaQ = $conn->prepare("SELECT ics_id, unit FROM ics_items WHERE ics_item_id = ? LIMIT 1");
                  $icsMetaQ->bind_param('i', $ics_item_id);
                  $icsMetaQ->execute();
                  $icsMetaRes = $icsMetaQ->get_result();
                  if ($icsMetaRes && $icsMetaRes->num_rows > 0) {
                    $icsMeta = $icsMetaRes->fetch_assoc();
                    $ics_id_val = (int)$icsMeta['ics_id'];
                    $unit = $icsMeta['unit'] ?? '';
                  } 
                  $icsMetaQ->close();
                  $quantity_before = $ics_qty;
                  $quantity_after = $new_qty;
                  $quantity_change = $quantity_after - $quantity_before;
                  $unit_cost = $uc;
                  $total_cost_before = $ics_qty * $uc;
                  $total_cost_after = $new_qty * $uc;
                  $reference_type = 'RRSP';
                  $reference_id = $rrsp_id;
                  $reference_no = $rrsp_no;
                  $reference_details = json_encode(['returned_qty'=>$qty,'end_user'=>$end,'remarks'=>$iremarks]);
                  $descReturned = $desc . ' ($Returned)';
                  $icsHistoryStmt->bind_param(
                    'iisssdddddsisss',
                    $ics_id_val,
                    $ics_item_id,
                    $icsItem['stock_number'],
                    $descReturned,
                    $unit,
                    $quantity_before,
                    $quantity_after,
                    $quantity_change,
                    $unit_cost,
                    $total_cost_before,
                    $total_cost_after,
                    $reference_type,
                    $reference_id,
                    $reference_no,
                    $reference_details
                  );
                  $icsHistoryStmt->execute();
                  $icsHistoryStmt->close();
                  // Add returned qty to semi_expendable_property
                  $semiQ = $conn->prepare("SELECT id, quantity_returned, quantity_issued FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
                  $semiQ->bind_param('s', $icsItem['stock_number']);
                  $semiQ->execute();
                  $semiRes = $semiQ->get_result();
                  $semi = $semiRes && $semiRes->num_rows > 0 ? $semiRes->fetch_assoc() : null;
                  $semiQ->close();
                  if ($semi) {
                    $semi_id = (int)$semi['id'];
                    $new_returned = (int)$semi['quantity_returned'] + $qty; // Increment returned
                    $new_issued = max(0, (int)$semi['quantity_issued'] - $qty); // Decrement issued, never negative
                    $u2 = $conn->prepare("UPDATE semi_expendable_property SET quantity_returned = ?, quantity_issued = ? WHERE id = ?");
                    $u2->bind_param('iii', $new_returned,  $new_issued, $semi_id);
                    $u2->execute();
                    $u2->close();
                    // Log to semi_expendable_history (include all relevant fields)
                    if (function_exists('ensure_semi_expendable_history')) { ensure_semi_expendable_history($conn); }
                    $office_officer_returned = $end;
                    $amount = isset($uc) ? $uc : 0.0;
                    $amount_total = $qty * $amount;
                    $h2 = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity_returned, remarks, office_officer_returned, amount, amount_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $h2->bind_param('ississdd', $semi_id, $date_prepared, $rrsp_no, $qty, $iremarks, $office_officer_returned, $amount, $amount_total);
                    $h2->execute();
                    $h2->close();
                  }
                }
              }
              // --- END ICS/SEMI logic ---
            }
          }
          $ist->close();
        }
      }
      echo json_encode(['success'=>true,'rrsp_id'=>$rrsp_id]); exit;
    } else { echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
  } catch(Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]); exit;
  }
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
<title>Add RRSP Form</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
  /* Lightweight layout helpers for better UX */
  .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
  .section-card h3 { margin-top:0; margin-bottom:12px; }
  .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
  .form-grid .form-group { display:flex; flex-direction:column; }
  @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } }
  .actions { display:flex; gap:10px; align-items:center; margin-top:14px; }
  /* Scrollable items table, preserve existing theme/colors */
  /* Frame: remove top border and flatten top corners to avoid a visible white strip */
  .table-frame { border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px; overflow: hidden; }
  /* Viewport: use the same gradient as header behind the sticky thead */
  .table-viewport { max-height: 420px; overflow-y: auto; overflow-x: auto; scrollbar-gutter: stable; background: var(--white); }
  /* Override global table overflow so sticky headers aren't clipped */
  #itemsTable { overflow: visible !important; width: 100%; border-collapse: collapse; background: transparent !important; border-radius: 0 !important; margin-top: 0 !important; }
  /* Make header stick within the scrolling viewport */
  #itemsTable thead th { position: sticky; top: 0; z-index: 3; background: var(--blue-gradient); color: #fff; }
  /* Ensure any top corners are flat across container/viewport/table */
  .table-frame, .table-viewport, #itemsTable { border-top-left-radius: 0 !important; border-top-right-radius: 0 !important; }
  /* Spacing improvements */
  #itemsTable th, #itemsTable td { padding: 10px 12px; vertical-align: middle; }
  #itemsTable thead th { height: 44px; }
  .search-container { margin: 8px 0 12px !important; }
  .form-grid .form-group input, .form-grid .form-group select, .form-grid .form-group textarea { padding: 8px 10px; }
  .picker { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
</style>
</head>
<body class="rrsp-page">
<?php include 'sidebar.php'; ?>
<div class="content edit-ics-page edit-ris-page">
  <h2>Add RRSP Form</h2>

  <div class="section-card">
    <h3>RRSP Details</h3>
    <div class="form-grid">
      <div class="form-group">
        <label>Entity Name:</label>
        <input type="text" id="entity_name" value="TESDA Regional Office" />
      </div>
      <div class="form-group">
        <label>Fund Cluster:</label>
        <input type="text" id="fund_cluster" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>RRSP No.:</label>
        <input type="text" id="rrsp_no" readonly style="background-color: #f5f5f5;">
        <small style="color:#6b7280;">Format: Year-Month-Serial (e.g., 2025-11-0001)</small>
      </div>
      <div class="form-group">
        <label>Date Prepared:</label>
        <input type="date" id="date_prepared" value="<?= date('Y-m-d') ?>" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>General Remarks:</label>
        <textarea id="remarks" rows="3" placeholder="Reason / context for returns..."></textarea>
      </div>
    </div>
  </div>

  <div class="section-card">
    <h3>RRSP Items</h3>
    <!-- Search Container -->
    <div class="search-container">
      <input type="text" id="itemSearch" class="search-input" placeholder="Search ICS items by stock number, description, or item no..." onkeyup="filterItems()">
    </div>

    <div class="table-frame">
      <div class="table-viewport">
        <table id="itemsTable" tabindex="-1">
          <thead>
            <tr>
              <th>Item No.</th>
              <th>ICS No./Date</th>
              <th>Description</th>
              <th>Unit Cost</th>
              <th>Qty on Hand</th>
              <th>Return Qty</th>
              <th>Amount</th>
              <th>End-user</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
          <?php
          // List ICS items similar to ITR page
          $sql = "SELECT ii.*, i.ics_no, i.date_issued
                    FROM ics_items ii
                    INNER JOIN ics i ON i.ics_id = ii.ics_id
                    WHERE ii.quantity > 0
                    ORDER BY i.date_issued DESC, ii.ics_item_id DESC";
          $resICS = $conn->query($sql);
          if ($resICS && $resICS->num_rows > 0) {
            while ($row = $resICS->fetch_assoc()) {
              $date_issued = $row['date_issued'] ?? '';
              $item_no = $row['inventory_item_no'] ?? ($row['stock_number'] ?? '');
              $ics_info = ($row['ics_no'] ?? '') . (isset($row['date_issued']) ? (' / ' . $row['date_issued']) : '');
              $desc = $row['description'] ?? '';
              $unit_cost = isset($row['unit_cost']) ? (float)$row['unit_cost'] : 0.0;
              if ($unit_cost <= 0 && isset($row['total_cost']) && isset($row['quantity']) && (float)$row['quantity'] > 0) {
                $unit_cost = ((float)$row['total_cost']) / max(1, (float)$row['quantity']);
              }
              $qty_on_hand = isset($row['quantity']) ? (float)$row['quantity'] : 0;
              $rowText = strtolower(($item_no ?: '') . ' ' . ($ics_info ?: '') . ' ' . ($desc ?: ''));
              echo '<tr class="ics-row" ' .
                   'data-text="' . htmlspecialchars($rowText) . '" ' .
                   'data-unit-cost="' . htmlspecialchars(number_format($unit_cost,2,'.','')) . '" ' .
                   'data-qty-on-hand="' . htmlspecialchars((string)$qty_on_hand) . '" ' .
                   'data-ics-no="' . htmlspecialchars($row['ics_no'] ?? '') . '" ' .
                   'data-ics-id="' . (int)$row['ics_id'] . '" ' .
                   'data-ics-item-id="' . (int)$row['ics_item_id'] . '" ' .
                   'data-stock-number="' . htmlspecialchars($row['stock_number'] ?? $item_no) . '">';
              echo '<td class="itemno-cell">' . htmlspecialchars($item_no) . '</td>';
              echo '<td class="icsinfo-cell">' . htmlspecialchars($ics_info) . '</td>';
              echo '<td class="desc-cell">' . htmlspecialchars($desc) . '</td>';
              echo '<td class="unitcost-cell">₱' . number_format($unit_cost, 2) . '</td>';
              echo '<td class="qtyonhand-cell">' . htmlspecialchars((string)$qty_on_hand) . '</td>';
              echo '<td class="returnqty-cell"><input type="number" class="qty-input" value="" min="0" max="' . htmlspecialchars((string)$qty_on_hand) . '" step="1" placeholder="0"></td>';
              echo '<td class="amount-cell">₱0.00</td>';
              echo '<td class="enduser-cell"><input type="text" class="enduser-input" placeholder="End-user" /></td>';
              echo '<td class="remarks-cell"><input type="text" class="remarks-input" placeholder="Remarks" /></td>';
              echo '</tr>';
            }
          } else {
            echo '<tr id="no-items-row"><td colspan="9">No ICS items found.</td></tr>';
          }
          if ($resICS) { $resICS->close(); }
          ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6" style="text-align:right;font-weight:600;">Grand Total:</td>
              <td id="grand_total" style="font-weight:700;">₱0.00</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="section-card">
    <h3>Signatories</h3>
    <div class="form-grid">
      <div class="form-group">
        <label>Returned By:</label>
        <input type="text" id="returned_by" />
      </div>
      <div class="form-group">
        <label>Returned Date:</label>
        <input type="date" id="returned_date" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Received By:</label>
        <input type="text" id="received_by" />
      </div>
      <div class="form-group">
        <label>Received Date:</label>
        <input type="date" id="received_date" />
      </div>
    </div>
  </div>

  <button type="button" onclick="submitRRSP()">Submit RRSP</button>
  <a href="rrsp.php" style="margin-left: 10px;">
    <button type="button">Cancel</button>
  </a>
</div>
<script>
// Fetch next RRSP number from server based on selected date
async function generateRRSPNo(){
  try {
    const dateEl = document.getElementById('date_prepared');
    const dateVal = dateEl && dateEl.value ? dateEl.value : new Date().toISOString().slice(0,10);
    const res = await fetch('add_rrsp.php?action=next_rrsp_no&date=' + encodeURIComponent(dateVal));
    const j = await res.json();
    if (j && j.next_rrsp_no) {
      document.getElementById('rrsp_no').value = j.next_rrsp_no;
      return;
    }
  } catch (e) {
    // fall back to simple default if endpoint fails
  }
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  document.getElementById('rrsp_no').value = `${y}-${m}-0001`;
}
generateRRSPNo();

// Recalculate RRSP no when date changes
document.getElementById('date_prepared').addEventListener('change', generateRRSPNo);
function filterItems(){
  const q=(document.getElementById('itemSearch').value||'').toLowerCase().trim();
  const rows=document.querySelectorAll('#itemsTable tbody tr.ics-row');
  let visible=0;
  rows.forEach(r=>{ const t=(r.getAttribute('data-text')||'').toLowerCase(); const show = q==='' || t.includes(q); r.style.display = show ? '' : 'none'; if (show) visible++; });
  const none=document.getElementById('no-items-row'); if (none) none.style.display = (visible===0) ? 'table-row' : 'none';
}
function formatMoney(v){ return '₱'+Number(v||0).toFixed(2); }
function attachQtyHandlersRRSP(){
  const recomputeGrand=()=>{
    let sum=0;
    document.querySelectorAll('#itemsTable tbody tr.ics-row .amount-cell').forEach(cell=>{
      const val=parseFloat((cell.textContent||'').replace(/[^0-9.\-]/g,'')||'0')||0; sum+=val;
    });
    const gt=document.getElementById('grand_total'); if (gt) gt.textContent = formatMoney(sum);
  };
  document.querySelectorAll('#itemsTable tbody tr.ics-row').forEach(r=>{
    const qtyInput=r.querySelector('.qty-input');
    const amtCell=r.querySelector('.amount-cell');
    const balCell=r.querySelector('.balance-cell');
    const unitCost=parseFloat(r.getAttribute('data-unit-cost')||'0')||0;
    const onHand=parseFloat(r.getAttribute('data-qty-on-hand')||'0')||0;
    if (!qtyInput) return;
    const recalc=()=>{
      let v=parseFloat(qtyInput.value||''); if (isNaN(v)||v<0) v=0; const max=parseFloat(qtyInput.getAttribute('max')||'0'); if (max>0 && v>max) v=max;
      if (amtCell) amtCell.textContent = formatMoney(unitCost * v);
      if (balCell) balCell.textContent = String(Math.max(0, onHand - v));
      recomputeGrand();
    };
    qtyInput.addEventListener('input', recalc);
    qtyInput.addEventListener('blur', recalc);
    recalc();
  });
  setTimeout(recomputeGrand,0);
}
function collectItems(){
  const arr=[];
  document.querySelectorAll('#itemsTable tbody tr.ics-row').forEach(r=>{
    const qty = parseInt(r.querySelector('.qty-input')?.value||'0',10) || 0;
    if (qty>0){
      const desc = (r.querySelector('.desc-cell')?.textContent||'').trim();
      const icsNo = r.getAttribute('data-ics-no') || (r.querySelector('.icsinfo-cell')?.textContent||'').trim();
      const endUser = r.querySelector('.enduser-input')?.value || '';
      const remarks = r.querySelector('.remarks-input')?.value || '';
      const unitCost = parseFloat(r.getAttribute('data-unit-cost')||'0')||0;
      arr.push({ description: desc, quantity: qty, ics_no: icsNo, end_user: endUser, remarks: remarks, unit_cost: unitCost });
    }
  });
  return arr;
}
async function submitRRSP(){
  const fd=new FormData(); fd.append('rrsp_no', document.getElementById('rrsp_no').value); fd.append('entity_name', document.getElementById('entity_name').value); fd.append('fund_cluster', document.getElementById('fund_cluster').value); fd.append('date_prepared', document.getElementById('date_prepared').value); fd.append('returned_by', document.getElementById('returned_by').value); fd.append('returned_date', document.getElementById('returned_date').value); fd.append('received_by', document.getElementById('received_by').value); fd.append('received_date', document.getElementById('received_date').value); fd.append('remarks', document.getElementById('remarks').value); fd.append('items_json', JSON.stringify(collectItems()));
  try { const res=await fetch('add_rrsp.php',{method:'POST', body:fd}); const j=await res.json(); if(!j.success){ alert(j.message||'Save failed'); return; } window.location.href='rrsp.php'; } catch(e){ alert('Error: '+e.message); }
}
// Initialize qty handlers for dynamic totals
document.addEventListener('DOMContentLoaded', attachQtyHandlersRRSP);
</script>
</body>
</html>

