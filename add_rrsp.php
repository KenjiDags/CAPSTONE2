<?php
require 'config.php';
require 'functions.php';

// Handle submission (standard form POST)
if($_SERVER['REQUEST_METHOD']==='POST'){
  header('Content-Type: application/json');
  $rrsp_no = trim($_POST['rrsp_no'] ?? '');
  // Generate RRSP number in format YYYY-MM-SSSS if not provided
  if($rrsp_no==='') { $rrsp_no = date('Y') . '-' . date('m') . '-' . '0001'; }
  $entity = trim($_POST['entity_name'] ?? '');
  $fund = trim($_POST['fund_cluster'] ?? '');
  $date_prepared = $_POST['date_prepared'] ?? date('Y-m-d');
  $returned_by = trim($_POST['returned_by'] ?? '');
  $returned_date = $_POST['returned_date'] ?? null;
  $received_by = trim($_POST['received_by'] ?? '');
  $received_date = $_POST['received_date'] ?? null;
  $remarks = trim($_POST['remarks'] ?? '');
  $items_json = $_POST['items_json'] ?? '[]';
  $items = json_decode($items_json,true) ?: [];
  if($rrsp_no===''){ echo json_encode(['success'=>false,'message'=>'RRSP number required']); exit; }
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
          $ist->execute();
        }
        $ist->close();
      }
    }
    echo json_encode(['success'=>true,'rrsp_id'=>$rrsp_id]); exit;
  } else { echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
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
          <tbody></tbody>
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
// Auto-generate RRSP number in format YYYY-MM-SSSS
function generateRRSPNo(){
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const s = '0001'; // Default serial, will be auto-incremented by backend if needed
  document.getElementById('rrsp_no').value = `${y}-${m}-${s}`;
}
generateRRSPNo();
function filterPicker(){ const q=(document.getElementById('itemSearch').value||'').toLowerCase(); const sel=document.getElementById('semi_select'); [...sel.options].forEach(o=>{ if(o.value==='') return; const t=o.textContent.toLowerCase(); o.style.display=t.includes(q)?'':'none'; }); }
function formatMoney(v){ return '₱'+v.toFixed(2); }
function recomputeTotal(){ let sum=0; document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{ sum+=parseFloat(r.getAttribute('data-total')||'0'); }); document.getElementById('grand_total').textContent=formatMoney(sum); }
function addItemRow(){
  const sel=document.getElementById('semi_select'); const opt=sel.options[sel.selectedIndex]; if(!opt||!opt.value){ alert('Select item'); return; }
  const qty=parseInt(document.getElementById('qty_input').value||'0',10); if(!qty||qty<1){ alert('Enter quantity'); return; }
  const endUser=document.getElementById('end_user_input').value.trim()||opt.getAttribute('data-end')||'';
  const remarks=document.getElementById('remarks_input').value.trim();
  const desc=opt.getAttribute('data-desc'); const ics=opt.getAttribute('data-ics'); const unitCost=parseFloat(opt.getAttribute('data-cost')||'0'); const total=qty*unitCost;
  const tr=document.createElement('tr'); tr.setAttribute('data-total', total.toString());
  tr.innerHTML=`<td>${desc}</td><td style='text-align:right;'>${qty}</td><td>${ics}</td><td>${endUser}</td><td>${remarks}</td><td style='text-align:right;'>${formatMoney(unitCost)}</td><td style='text-align:right;font-weight:600;'>${formatMoney(total)}</td><td><button type='button' onclick='this.closest("tr").remove(); recomputeTotal();'>✖</button></td>`;
  document.querySelector('#itemsTable tbody').appendChild(tr); recomputeTotal(); document.getElementById('qty_input').value=''; document.getElementById('remarks_input').value=''; sel.selectedIndex=0;
}
function collectItems(){ const arr=[]; document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{ const c=r.children; arr.push({ description:c[0].textContent.trim(), quantity:parseInt(c[1].textContent.trim(),10)||0, ics_no:c[2].textContent.trim(), end_user:c[3].textContent.trim(), remarks:c[4].textContent.trim(), unit_cost: parseFloat(c[5].textContent.replace(/[^0-9.\-]/g,'')||'0') }); }); return arr; }
async function submitRRSP(){
  const fd=new FormData(); fd.append('rrsp_no', document.getElementById('rrsp_no').value); fd.append('entity_name', document.getElementById('entity_name').value); fd.append('fund_cluster', document.getElementById('fund_cluster').value); fd.append('date_prepared', document.getElementById('date_prepared').value); fd.append('returned_by', document.getElementById('returned_by').value); fd.append('returned_date', document.getElementById('returned_date').value); fd.append('received_by', document.getElementById('received_by').value); fd.append('received_date', document.getElementById('received_date').value); fd.append('remarks', document.getElementById('remarks').value); fd.append('items_json', JSON.stringify(collectItems()));
  try { const res=await fetch('add_rrsp.php',{method:'POST', body:fd}); const j=await res.json(); if(!j.success){ alert(j.message||'Save failed'); return; } window.location.href='rrsp.php'; } catch(e){ alert('Error: '+e.message); }
}
</script>
</body>
</html>
