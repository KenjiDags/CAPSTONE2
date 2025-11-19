<?php
// Handle submission FIRST before any output
if($_SERVER['REQUEST_METHOD']==='POST'){
  require 'config.php';
  require 'functions.php';
  
  header('Content-Type: application/json');
  $iirusp_no = trim($_POST['iirusp_no'] ?? '');
  if($iirusp_no==='') { $iirusp_no = date('Y') . '-' . date('m') . '-' . '0001'; }
  
  $as_at = $_POST['as_at'] ?? date('Y-m-d');
  $entity = trim($_POST['entity_name'] ?? '');
  $fund = trim($_POST['fund_cluster'] ?? '');
  $acc_officer_name = trim($_POST['accountable_officer_name'] ?? '');
  $acc_officer_designation = trim($_POST['accountable_officer_designation'] ?? '');
  $acc_officer_station = trim($_POST['accountable_officer_station'] ?? '');
  $requested_by = trim($_POST['requested_by'] ?? '');
  $approved_by = trim($_POST['approved_by'] ?? '');
  $inspection_officer = trim($_POST['inspection_officer'] ?? '');
  $witness = trim($_POST['witness'] ?? '');
  $items_json = $_POST['items_json'] ?? '[]';
  $items = json_decode($items_json,true) ?: [];
  
  if($iirusp_no===''){ echo json_encode(['success'=>false,'message'=>'IIRUSP number required']); exit; }
  
  ensure_iirusp_history($conn); // ensure history table exists
  if($stmt=$conn->prepare("INSERT INTO iirusp (iirusp_no,as_at,entity_name,fund_cluster,accountable_officer_name,accountable_officer_designation,accountable_officer_station,requested_by,approved_by,inspection_officer,witness) VALUES (?,?,?,?,?,?,?,?,?,?,?)")){
    $stmt->bind_param('sssssssssss',$iirusp_no,$as_at,$entity,$fund,$acc_officer_name,$acc_officer_designation,$acc_officer_station,$requested_by,$approved_by,$inspection_officer,$witness);
    if(!$stmt->execute()){ echo json_encode(['success'=>false,'message'=>'Failed to save IIRUSP header']); exit; }
    $iirusp_id = $stmt->insert_id; $stmt->close();
    
    if(!empty($items)){
      if($ist=$conn->prepare("INSERT INTO iirusp_items (iirusp_id,date_acquired,particulars,semi_expendable_property_no,quantity,unit,unit_cost,total_cost,accumulated_impairment,carrying_amount,remarks,disposal_sale,disposal_transfer,disposal_destruction,disposal_others,disposal_total,appraised_value,or_no,sales_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")){
        foreach($items as $it){
          $date_acq = $it['date_acquired'] ?? null;
          $particulars = trim($it['particulars']??'');
          $prop_no = trim($it['property_no']??'');
          $qty = (int)($it['quantity']??0);
          $unit = trim($it['unit']??'');
          $uc = (float)($it['unit_cost']??0);
          $tc = $qty * $uc;
          $accum_imp = (float)($it['accumulated_impairment']??0);
          $carry_amt = $tc - $accum_imp;
          $rem = trim($it['remarks']??'');
          $disp_sale = (float)($it['disposal_sale']??0);
          $disp_transfer = (float)($it['disposal_transfer']??0);
          $disp_destruction = (float)($it['disposal_destruction']??0);
          $disp_others = trim($it['disposal_others']??'');
          $disp_total = $disp_sale + $disp_transfer + $disp_destruction;
          $appraised = (float)($it['appraised_value']??0);
          $or_no = trim($it['or_no']??'');
          $sales_amt = (float)($it['sales_amount']??0);
          
          $ist->bind_param('isssissddssdddsdds',$iirusp_id,$date_acq,$particulars,$prop_no,$qty,$unit,$uc,$tc,$accum_imp,$carry_amt,$rem,$disp_sale,$disp_transfer,$disp_destruction,$disp_others,$disp_total,$appraised,$or_no,$sales_amt);
          if($ist->execute()){
            $iirusp_item_id = $ist->insert_id;
            // Log to iirusp_history
            $hStmt = $conn->prepare("INSERT INTO iirusp_history (iirusp_id,iirusp_item_id,semi_expendable_property_no,particulars,quantity,unit,unit_cost,total_cost,accumulated_impairment,carrying_amount,remarks,disposal_sale,disposal_transfer,disposal_destruction,disposal_others,disposal_total,appraised_value,or_no,sales_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if($hStmt){
              $hStmt->bind_param('iississddssdddsdds',$iirusp_id,$iirusp_item_id,$prop_no,$particulars,$qty,$unit,$uc,$tc,$accum_imp,$carry_amt,$rem,$disp_sale,$disp_transfer,$disp_destruction,$disp_others,$disp_total,$appraised,$or_no,$sales_amt);
              $hStmt->execute();
              $hStmt->close();
            }
          }
        }
        $ist->close();
      }
    }
    echo json_encode(['success'=>true,'iirusp_id'=>$iirusp_id]); exit;
  } else { echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
}

// If we reach here, it's a GET request to display the form
require 'config.php';
require 'functions.php';

// Build semi-expendable list
$semi=[]; $q=$conn->query("SELECT semi_expendable_property_no,item_description,amount,date,unit FROM semi_expendable_property ORDER BY item_description");
if($q){ while($r=$q->fetch_assoc()){ $semi[]=$r; } }
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
  .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
  .section-card h3 { margin-top:0; margin-bottom:12px; }
  .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
  .form-grid .form-group { display:flex; flex-direction:column; }
  @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } }
  .table-frame { border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px; overflow: hidden; }
  .table-viewport { max-height: 420px; overflow-y: auto; overflow-x: auto; scrollbar-gutter: stable; background: var(--white); }
  #itemsTable { overflow: visible !important; width: 100%; border-collapse: collapse; background: transparent !important; border-radius: 0 !important; margin-top: 0 !important; }
  #itemsTable thead th { position: sticky; top: 0; z-index: 3; background: var(--blue-gradient); color: #fff; height: 44px; padding: 10px 8px; font-size:12px; }
  #itemsTable td { padding: 8px; font-size:12px; }
  .search-container { margin: 8px 0 12px !important; }
  .form-grid .form-group input, .form-grid .form-group select, .form-grid .form-group textarea { padding: 8px 10px; }
  .picker { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
</style>
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>
<div class="content edit-ics-page edit-ris-page">
  <h2>Add IIRUSP Form</h2>

  <div class="section-card">
    <h3>IIRUSP Details</h3>
    <div class="form-grid">
      <div class="form-group">
        <label>IIRUSP No.:</label>
        <input type="text" id="iirusp_no" readonly style="background-color: #f5f5f5;">
        <small style="color:#6b7280;">Format: Year-Month-Serial (e.g., 2025-11-0001)</small>
      </div>
      <div class="form-group">
        <label>As At (Date):</label>
        <input type="date" id="as_at" value="<?= date('Y-m-d') ?>" />
      </div>
    </div>
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
        <label>Accountable Officer Name:</label>
        <input type="text" id="accountable_officer_name" />
      </div>
      <div class="form-group">
        <label>Designation:</label>
        <input type="text" id="accountable_officer_designation" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Station:</label>
        <input type="text" id="accountable_officer_station" />
      </div>
    </div>
  </div>

  <div class="section-card">
    <h3>Unserviceable Items</h3>
    
    <div class="search-container">
      <input type="text" id="itemSearch" class="search-input" placeholder="Start typing to search items..." onkeyup="filterPicker()">
    </div>
    
    <div class="picker">
      <select id="semi_select">
        <option value="">Select item...</option>
        <?php foreach($semi as $s): ?>
          <option value="<?= htmlspecialchars($s['semi_expendable_property_no']) ?>" 
                  data-desc="<?= htmlspecialchars($s['item_description']) ?>" 
                  data-cost="<?= htmlspecialchars($s['amount']) ?>"
                  data-date="<?= htmlspecialchars($s['date']) ?>"
                  data-unit="<?= htmlspecialchars($s['unit']??'') ?>">
            <?= htmlspecialchars($s['item_description']) ?> (<?= htmlspecialchars($s['semi_expendable_property_no']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <input type="number" id="qty_input" placeholder="Qty" min="1" />
      <input type="text" id="remarks_input" placeholder="Remarks" />
      <button type="button" onclick="addItemRow()"><i class="fas fa-plus"></i> Add Item</button>
    </div>
    
    <div class="table-frame">
      <div class="table-viewport">
        <table id="itemsTable" tabindex="-1">
          <thead>
            <tr>
              <th style="min-width:80px;">Date Acq.</th>
              <th style="min-width:150px;">Particulars</th>
              <th style="min-width:100px;">Property No.</th>
              <th style="min-width:60px;">Qty</th>
              <th style="min-width:60px;">Unit</th>
              <th style="min-width:90px;">Unit Cost</th>
              <th style="min-width:100px;">Total Cost</th>
              <th style="min-width:100px;">Accum. Impairment</th>
              <th style="min-width:100px;">Carrying Amount</th>
              <th style="min-width:120px;">Remarks</th>
              <th style="min-width:60px;">Remove</th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot>
            <tr>
              <td colspan="6" style="text-align:right;font-weight:600;">Grand Total:</td>
              <td id="grand_total" style="font-weight:700;">₱0.00</td>
              <td colspan="4"></td>
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
        <label>Requested By (Accountable Officer):</label>
        <input type="text" id="requested_by" />
      </div>
      <div class="form-group">
        <label>Approved By:</label>
        <input type="text" id="approved_by" />
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Inspection Officer:</label>
        <input type="text" id="inspection_officer" />
      </div>
      <div class="form-group">
        <label>Witness:</label>
        <input type="text" id="witness" />
      </div>
    </div>
  </div>

  <button type="button" onclick="submitIIRUSP()">Submit IIRUSP</button>
  <a href="iirusp.php" style="margin-left: 10px;">
    <button type="button">Cancel</button>
  </a>
</div>
<script>
function generateIIRUSPNo(){
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const s = '0001';
  document.getElementById('iirusp_no').value = `${y}-${m}-${s}`;
}
generateIIRUSPNo();
function filterPicker(){ const q=(document.getElementById('itemSearch').value||'').toLowerCase(); const sel=document.getElementById('semi_select'); [...sel.options].forEach(o=>{ if(o.value==='') return; const t=o.textContent.toLowerCase(); o.style.display=t.includes(q)?'':'none'; }); }
function formatMoney(v){ return '₱'+v.toFixed(2); }
function recomputeTotal(){ let sum=0; document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{ sum+=parseFloat(r.getAttribute('data-total')||'0'); }); document.getElementById('grand_total').textContent=formatMoney(sum); }
function addItemRow(){
  const sel=document.getElementById('semi_select'); const opt=sel.options[sel.selectedIndex]; if(!opt||!opt.value){ alert('Select item'); return; }
  const qty=parseInt(document.getElementById('qty_input').value||'0',10); if(!qty||qty<1){ alert('Enter quantity'); return; }
  const remarks=document.getElementById('remarks_input').value.trim();
  const desc=opt.getAttribute('data-desc');
  const propNo=opt.value;
  const unitCost=parseFloat(opt.getAttribute('data-cost')||'0');
  const dateAcq=opt.getAttribute('data-date')||'';
  const unit=opt.getAttribute('data-unit')||'';
  const totalCost=qty*unitCost;
  const accumImp=0;
  const carryAmt=totalCost-accumImp;
  
  const tr=document.createElement('tr'); tr.setAttribute('data-total', totalCost.toString());
  tr.innerHTML=`<td>${dateAcq}</td><td>${desc}</td><td>${propNo}</td><td style='text-align:right;'>${qty}</td><td>${unit}</td><td style='text-align:right;'>${formatMoney(unitCost)}</td><td style='text-align:right;font-weight:600;'>${formatMoney(totalCost)}</td><td style='text-align:right;'>₱0.00</td><td style='text-align:right;'>${formatMoney(carryAmt)}</td><td>${remarks}</td><td><button type='button' onclick='this.closest("tr").remove(); recomputeTotal();'>✖</button></td>`;
  document.querySelector('#itemsTable tbody').appendChild(tr); recomputeTotal(); document.getElementById('qty_input').value=''; document.getElementById('remarks_input').value=''; sel.selectedIndex=0;
}
function collectItems(){ const arr=[]; document.querySelectorAll('#itemsTable tbody tr').forEach(r=>{ const c=r.children; arr.push({ date_acquired:c[0].textContent.trim(), particulars:c[1].textContent.trim(), property_no:c[2].textContent.trim(), quantity:parseInt(c[3].textContent.trim(),10)||0, unit:c[4].textContent.trim(), unit_cost: parseFloat(c[5].textContent.replace(/[^0-9.\-]/g,'')||'0'), accumulated_impairment:0, remarks:c[9].textContent.trim() }); }); return arr; }
async function submitIIRUSP(){
  const fd=new FormData();
  fd.append('iirusp_no', document.getElementById('iirusp_no').value);
  fd.append('as_at', document.getElementById('as_at').value);
  fd.append('entity_name', document.getElementById('entity_name').value);
  fd.append('fund_cluster', document.getElementById('fund_cluster').value);
  fd.append('accountable_officer_name', document.getElementById('accountable_officer_name').value);
  fd.append('accountable_officer_designation', document.getElementById('accountable_officer_designation').value);
  fd.append('accountable_officer_station', document.getElementById('accountable_officer_station').value);
  fd.append('requested_by', document.getElementById('requested_by').value);
  fd.append('approved_by', document.getElementById('approved_by').value);
  fd.append('inspection_officer', document.getElementById('inspection_officer').value);
  fd.append('witness', document.getElementById('witness').value);
  fd.append('items_json', JSON.stringify(collectItems()));
  try { const res=await fetch('add_iirusp.php',{method:'POST', body:fd}); const j=await res.json(); if(!j.success){ alert(j.message||'Save failed'); return; } window.location.href='iirusp.php'; } catch(e){ alert('Error: '+e.message); }
}
</script>
</body>
</html>
