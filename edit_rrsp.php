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
  <h2>Edit RRSP Form</h2>

  <div class="section-card">
    <h3>RRSP Details</h3>
    <div class="form-grid">
      <div class="form-group">
        <label>Entity Name:</label>
        <input type="text" id="entity_name" value="<?= htmlspecialchars($rrsp['entity_name']) ?>" />
      </div>
      <div class="form-group">
        <label>Fund Cluster:</label>
        <input type="text" id="fund_cluster" value="<?= htmlspecialchars($rrsp['fund_cluster']) ?>" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>RRSP No.:</label>
        <input type="text" id="rrsp_no" value="<?= htmlspecialchars($rrsp['rrsp_no']) ?>" />
        <small style="color:#6b7280;">Format: Year-Month-Serial (e.g., 2025-11-0001)</small>
      </div>
      <div class="form-group">
        <label>Date Prepared:</label>
        <input type="date" id="date_prepared" value="<?= htmlspecialchars($rrsp['date_prepared']) ?>" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>General Remarks:</label>
        <textarea id="remarks" rows="3" placeholder="Reason / context for returns..."><?= htmlspecialchars($rrsp['remarks']) ?></textarea>
      </div>
    </div>
  </div>

  <div class="section-card">
    <h3>RRSP Items</h3>
    
    <!-- Search Container -->
    <div class="search-container">
      <input type="text" id="itemSearch" class="search-input" placeholder="Start typing to search items..." onkeyup="filterPicker()">
    </div>
    
    <div class="picker">
      <select id="semi_select">
        <option value="">Select item...</option>
        <?php foreach($semi as $s): ?>
          <option value="<?= htmlspecialchars($s['semi_expendable_property_no']) ?>" data-desc="<?= htmlspecialchars($s['item_description']) ?>" data-ics="<?= htmlspecialchars($s['semi_expendable_property_no']) ?>" data-cost="<?= htmlspecialchars($s['amount']) ?>" data-end="<?= htmlspecialchars($s['office_officer_issued']) ?>">
            <?= htmlspecialchars($s['item_description']) ?> (<?= htmlspecialchars($s['semi_expendable_property_no']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <input type="number" id="qty_input" placeholder="Qty" min="1" />
      <input type="text" id="end_user_input" placeholder="End-user" />
      <input type="text" id="remarks_input" placeholder="Item remarks" />
      <button type="button" onclick="addItemRow()"><i class="fas fa-plus"></i> Add Item</button>
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
              <th>Remove</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($items as $it): $tot=(float)$it['quantity']*(float)$it['unit_cost']; ?>
            <tr>
              <td><input type="text" value="<?= htmlspecialchars($it['item_description']) ?>" class="desc" /></td>
              <td><input type="number" value="<?= (int)$it['quantity'] ?>" class="qty" min="0" style="width:80px;" /></td>
              <td><input type="text" value="<?= htmlspecialchars($it['ics_no']) ?>" class="ics" style="width:120px;" /></td>
              <td><input type="text" value="<?= htmlspecialchars($it['end_user']) ?>" class="enduser" /></td>
              <td><input type="text" value="<?= htmlspecialchars($it['item_remarks']) ?>" class="iremarks" /></td>
              <td><input type="number" step="0.01" value="<?= htmlspecialchars($it['unit_cost']) ?>" class="ucost" style="width:100px;" /></td>
              <td class="total">₱<?= number_format($tot,2) ?></td>
              <td><button type="button" onclick="this.closest('tr').remove(); recomputeTotals();">✖</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6" style="text-align:right;font-weight:600;">Grand Total:</td>
              <td id="grand_total" style="font-weight:700;">₱0.00</td>
              <td></td>
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
        <input type="text" id="returned_by" value="<?= htmlspecialchars($rrsp['returned_by']) ?>" />
      </div>
      <div class="form-group">
        <label>Returned Date:</label>
        <input type="date" id="returned_date" value="<?= htmlspecialchars($rrsp['returned_date']) ?>" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Received By:</label>
        <input type="text" id="received_by" value="<?= htmlspecialchars($rrsp['received_by']) ?>" />
      </div>
      <div class="form-group">
        <label>Received Date:</label>
        <input type="date" id="received_date" value="<?= htmlspecialchars($rrsp['received_date']) ?>" />
      </div>
    </div>
  </div>

  <button type="button" onclick="saveRRSP()">Save Changes</button>
  <a href="view_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>" style="margin-left: 10px;">
    <button type="button">Cancel</button>
  </a>
</div>
<script>
function formatMoney(v){ return '₱'+v.toFixed(2); }
function recomputeTotals(){ let sum=0; document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{ const qty=parseFloat(r.querySelector('.qty').value)||0; const uc=parseFloat(r.querySelector('.ucost').value)||0; const tot=qty*uc; const totalCell=r.querySelector('.total'); if(totalCell){ totalCell.textContent=formatMoney(tot); } sum+=tot; }); document.getElementById('grand_total').textContent=formatMoney(sum); }
recomputeTotals();
// Attach listeners for dynamic totals
document.querySelectorAll('.qty, .ucost').forEach(el=>el.addEventListener('input', recomputeTotals));
function filterPicker(){ const q=(document.getElementById('itemSearch').value||'').toLowerCase(); const sel=document.getElementById('semi_select'); [...sel.options].forEach(o=>{ if(o.value==='') return; const t=o.textContent.toLowerCase(); o.style.display=t.includes(q)?'':'none'; }); }
function addItemRow(){
  const sel=document.getElementById('semi_select'); const opt=sel.options[sel.selectedIndex]; if(!opt||!opt.value){ alert('Select item'); return; }
  const qty=parseInt(document.getElementById('qty_input').value||'0',10); if(!qty||qty<1){ alert('Enter quantity'); return; }
  const endUser=document.getElementById('end_user_input').value.trim()||opt.getAttribute('data-end')||'';
  const remarks=document.getElementById('remarks_input').value.trim();
  const desc=opt.getAttribute('data-desc'); const ics=opt.getAttribute('data-ics'); const unitCost=parseFloat(opt.getAttribute('data-cost')||'0'); const tot=qty*unitCost;
  const tr=document.createElement('tr');
  tr.innerHTML=`<td><input type='text' value='${desc}' class='desc' /></td>`+
               `<td><input type='number' value='${qty}' class='qty' min='0' style='width:80px;' /></td>`+
               `<td><input type='text' value='${ics}' class='ics' style='width:120px;' /></td>`+
               `<td><input type='text' value='${endUser}' class='enduser' /></td>`+
               `<td><input type='text' value='${remarks}' class='iremarks' /></td>`+
               `<td><input type='number' step='0.01' value='${unitCost}' class='ucost' style='width:100px;' /></td>`+
               `<td class='total'>${formatMoney(tot)}</td>`+
               `<td><button type='button' onclick='this.closest("tr").remove(); recomputeTotals();'>✖</button></td>`;
  document.querySelector('#itemsTable tbody').appendChild(tr);
  tr.querySelectorAll('.qty, .ucost').forEach(el=>el.addEventListener('input', recomputeTotals));
  recomputeTotals();
  document.getElementById('qty_input').value=''; document.getElementById('end_user_input').value=''; document.getElementById('remarks_input').value=''; sel.selectedIndex=0;
}
async function saveRRSP(){
  const items=[];
  document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{
    items.push({
      item_description: r.querySelector('.desc').value.trim(),
      quantity: parseInt(r.querySelector('.qty').value||'0',10),
      ics_no: r.querySelector('.ics').value.trim(),
      end_user: r.querySelector('.enduser').value.trim(),
      item_remarks: r.querySelector('.iremarks').value.trim(),
      unit_cost: parseFloat(r.querySelector('.ucost').value||'0')
    });
  });
  const payload={
    rrsp_no: document.getElementById('rrsp_no').value.trim(),
    date_prepared: document.getElementById('date_prepared').value,
    entity_name: document.getElementById('entity_name').value.trim(),
    fund_cluster: document.getElementById('fund_cluster').value.trim(),
    returned_by: document.getElementById('returned_by').value.trim(),
    received_by: document.getElementById('received_by').value.trim(),
    returned_date: document.getElementById('returned_date').value,
    received_date: document.getElementById('received_date').value,
    remarks: document.getElementById('remarks').value.trim(),
    items: items
  };
  try {
    const res=await fetch('edit_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>&save=1',{method:'POST', body: JSON.stringify(payload)});
    const j=await res.json();
    if(!j.success){ alert(j.message||'Save failed'); return; }
    window.location.href='view_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>';
  } catch(e){ alert('Error: '+e.message); }
}
</script>
</body>
</html>