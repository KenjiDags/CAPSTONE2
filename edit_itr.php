<?php
require 'config.php';
require 'functions.php';

if (!isset($_GET['itr_id'])) { header('Location: itr.php'); exit(); }
$itr_id = (int)$_GET['itr_id'];

// Load existing ITR header and items
$itr = null; $itr_items = [];
$res = $conn->query("SELECT * FROM itr WHERE itr_id = $itr_id");
if ($res && $res->num_rows > 0) { $itr = $res->fetch_assoc(); }
if (!$itr) { echo '❌ ITR not found.'; exit; }
$rs = $conn->query("SELECT * FROM itr_items WHERE itr_id = $itr_id");
if ($rs) { while ($row = $rs->fetch_assoc()) { $itr_items[] = $row; } }

// Build quick lookup by item_no (case-insensitive)
$prefill_map = [];
foreach ($itr_items as $it) {
    $key = strtolower(trim($it['item_no'] ?? ''));
    if ($key !== '') { $prefill_map[$key] = $it; }
}

// Parse existing ITR No. into parts
$serial = ''; $mm = ''; $yy = '';
if (preg_match('/^(\d{1,4})-(\d{2})-(\d{4})$/', (string)$itr['itr_no'], $m)) {
    $serial = str_pad($m[1], 4, '0', STR_PAD_LEFT);
    $mm = $m[2];
    $yy = $m[3];
} else {
    // fallback derive from date
    $serial = '0001';
    $dt = $itr['itr_date'] ?? date('Y-m-d');
    $mm = date('m', strtotime($dt));
    $yy = date('Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit ITR Form</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
    .section-card h3 { margin:0 0 12px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-grid .form-group { display:flex; flex-direction:column; }
    @media (max-width: 800px) { .form-grid { grid-template-columns:1fr; } }
    .table-frame { border:1px solid #e5e7eb; border-top:0; border-radius:0 0 8px 8px; overflow:hidden; }
    .table-viewport { max-height:420px; overflow-y:auto; overflow-x:auto; scrollbar-gutter:stable; background: var(--white); }
    #itemsTable { overflow:visible !important; width:100%; border-collapse:collapse; background:transparent !important; border-radius:0 !important; margin-top:0 !important; }
    #itemsTable thead th { position:sticky; top:0; z-index:3; background: var(--blue-gradient); color:#fff; }
    .table-frame, .table-viewport, #itemsTable { border-top-left-radius:0 !important; border-top-right-radius:0 !important; }
    #itemsTable td, #itemsTable th { border:1px solid #e5e7eb; padding:6px 8px; }
    .actions { display:flex; gap:10px; align-items:center; margin-top:14px; }
    .field-error { color:#b91c1c; font-size:0.85rem; margin-top:6px; }
    .has-error input, .has-error textarea, .has-error select { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,0.15); }
  </style>
</head>
<body>
  <?php include 'sidebar.php'; ?>
  <div class="edit-ics-page content edit-ris-page">
    <h2>Edit Inventory Transfer Report (ITR)</h2>

    <input type="hidden" id="itr_id" value="<?= (int)$itr_id ?>" />

    <div class="section-card">
      <h3>ITR Details</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Entity Name:</label>
          <input type="text" id="entity_name" value="<?= htmlspecialchars($itr['entity_name'] ?? 'TESDA Regional Office') ?>" />
        </div>
        <div class="form-group">
          <label>Fund Cluster:</label>
          <input type="text" id="fund_cluster" value="<?= htmlspecialchars($itr['fund_cluster'] ?? '') ?>" />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>From Accountable Officer/Agency/Fund Cluster:</label>
          <input type="text" id="from_accountable" value="<?= htmlspecialchars($itr['from_accountable'] ?? '') ?>" />
        </div>
        <div class="form-group">
          <label>To Accountable Officer/Agency/Fund Cluster:</label>
          <input type="text" id="to_accountable" value="<?= htmlspecialchars($itr['to_accountable'] ?? '') ?>" />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>ITR No. (Serial-Month-Year):</label>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="text" id="itr_serial" value="<?= htmlspecialchars($serial) ?>" style="max-width:110px;" />
            <span>-</span>
            <input type="text" id="itr_month" value="<?= htmlspecialchars($mm) ?>" style="max-width:70px;" />
            <span>-</span>
            <input type="text" id="itr_year" value="<?= htmlspecialchars($yy) ?>" style="max-width:90px;" />
          </div>
          <small style="display:block;color:#6b7280;margin-top:6px;">Preview: <code id="itr_no_preview"></code></small>
          <input type="hidden" id="itr_no" value="<?= htmlspecialchars($itr['itr_no']) ?>" />
        </div>
        <div class="form-group">
          <label>Date:</label>
          <input type="date" id="itr_date" value="<?= htmlspecialchars($itr['itr_date'] ?? date('Y-m-d')) ?>" />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Transfer Type (check only one):</label>
          <div style="display:flex; gap:16px; flex-wrap:wrap; padding-top:6px;">
            <?php $t = $itr['transfer_type'] ?? ''; ?>
            <label><input type="radio" name="transfer_type" value="Donation" <?= $t==='Donation'?'checked':'' ?>> Donation</label>
            <label><input type="radio" name="transfer_type" value="Reassignment" <?= $t==='Reassignment'?'checked':'' ?>> Reassignment</label>
            <label><input type="radio" name="transfer_type" value="Relocate" <?= $t==='Relocate'?'checked':'' ?>> Relocate</label>
            <label><input type="radio" name="transfer_type" value="Others" <?= $t==='Others'?'checked':'' ?>> Others</label>
            <input type="text" id="transfer_other" placeholder="Specify (if Others)" value="<?= htmlspecialchars($itr['transfer_other'] ?? '') ?>" style="min-width:220px;" />
          </div>
        </div>
      </div>
    </div>

    <div class="section-card">
      <h3>Items in this ITR</h3>
      <div class="search-container" style="margin-bottom:10px;">
        <input type="text" id="itemSearch" class="search-input" placeholder="Search ICS items..." onkeyup="filterItems()">
      </div>
      <div class="table-frame">
        <div class="table-viewport">
          <table id="itemsTable">
            <thead>
              <tr>
                <th>Date Acquired</th>
                <th>Item No.</th>
                <th>ICS No./Date</th>
                <th>Description</th>
                <th>Unit Cost</th>
                <th>Qty on Hand</th>
                <th>Transfer Qty</th>
                <th>Amount</th>
                <th>Condition of Inventory</th>
              </tr>
            </thead>
      <tbody>
        <?php
        // Render rows directly from itr_items, and enrich with ICS details for unit cost and on-hand.
        if (!empty($itr_items)) {
          // Build a lookup of ICS items for the item_nos in this ITR
          $keys = [];
          foreach ($itr_items as $it) {
            $k = trim((string)($it['item_no'] ?? ''));
            if ($k !== '') { $keys[$k] = true; }
          }
          $icsMap = [];
          if (!empty($keys)) {
            $esc = [];
            foreach (array_keys($keys) as $k) { $esc[] = "'".$conn->real_escape_string($k)."'"; }
            $in = implode(',', $esc);
            $q = $conn->query("SELECT ii.*, i.ics_no, i.date_issued FROM ics_items ii INNER JOIN ics i ON i.ics_id = ii.ics_id WHERE ii.stock_number IN ($in) OR ii.inventory_item_no IN ($in) ORDER BY ii.ics_item_id DESC");
            if ($q) { while ($r = $q->fetch_assoc()) {
              $sn = trim((string)($r['stock_number'] ?? ''));
              $inv = trim((string)($r['inventory_item_no'] ?? ''));
              if ($sn !== '') { if (!isset($icsMap[$sn])) $icsMap[$sn] = $r; }
              if ($inv !== '') { if (!isset($icsMap[$inv])) $icsMap[$inv] = $r; }
            } }
          }

          $printed = 0;
          foreach ($itr_items as $saved) {
            $item_no = trim((string)($saved['item_no'] ?? ''));
            if ($item_no === '') { continue; }
            $row = $icsMap[$item_no] ?? null;
            $date_acquired = $saved['date_acquired'] ?? ($row['date_issued'] ?? '');
            $desc = ($saved['description'] ?? '') !== '' ? $saved['description'] : ($row['description'] ?? '');
            $ics_info = ($saved['ics_info'] ?? '') !== '' ? $saved['ics_info'] : ((($row['ics_no'] ?? '') !== '') ? (($row['ics_no']) . ((isset($row['date_issued']) && $row['date_issued']!=='') ? (' / ' . $row['date_issued']) : '')) : '');
            $unit_cost = isset($row['unit_cost']) ? (float)$row['unit_cost'] : 0.0;
            if ($unit_cost <= 0 && isset($row['total_cost']) && isset($row['quantity']) && (float)$row['quantity'] > 0) {
              $unit_cost = ((float)$row['total_cost']) / max(1, (float)$row['quantity']);
            }
            $qty_on_hand = isset($row['quantity']) ? (float)$row['quantity'] : 0;
            // Prefer stored transfer_qty if present else derive from amount
            $prefQty = '';
            if (isset($saved['transfer_qty']) && (int)$saved['transfer_qty'] > 0) {
              $prefQty = (string)((int)$saved['transfer_qty']);
            } else {
              $savedAmt = (float)($saved['amount'] ?? 0);
              $calcQty = ($unit_cost > 0) ? floor($savedAmt / $unit_cost) : 0;
              if ($calcQty > 0) { $prefQty = (string)$calcQty; }
            }
            $prefCond = (string)($saved['cond'] ?? '');

            $rowText = strtolower(($item_no ?: '') . ' ' . ($ics_info ?: '') . ' ' . ($desc ?: ''));
            $data_unit = htmlspecialchars(number_format($unit_cost,2,'.',''));
            $data_onhand = htmlspecialchars((string)$qty_on_hand);
            $ics_id_attr = isset($row['ics_id']) ? (int)$row['ics_id'] : 0;
            $ics_item_id_attr = isset($row['ics_item_id']) ? (int)$row['ics_item_id'] : 0;
            $stock_attr = htmlspecialchars($row['stock_number'] ?? $item_no);

            echo '<tr class="ics-row" data-text="' . htmlspecialchars($rowText) . '" data-unit-cost="' . $data_unit . '" data-qty-on-hand="' . $data_onhand . '" data-ics-id="' . $ics_id_attr . '" data-ics-item-id="' . $ics_item_id_attr . '" data-stock-number="' . $stock_attr . '">';
            echo '<td class="date-cell">' . htmlspecialchars($date_acquired) . '</td>';
            echo '<td class="itemno-cell">' . htmlspecialchars($item_no) . '</td>';
            echo '<td class="icsinfo-cell">' . htmlspecialchars($ics_info) . '</td>';
            echo '<td class="desc-cell">' . htmlspecialchars($desc) . '</td>';
            echo '<td class="unitcost-cell">₱' . number_format($unit_cost, 2) . '</td>';
            // Display-only: show Qty on Hand + Transfer Qty (initial value); does not update dynamically
            $initT = (int)($prefQty!=='' ? $prefQty : 0);
            $displayOnHand = (int)$qty_on_hand + $initT;
            echo '<td class="onhand-cell">' . htmlspecialchars((string)$displayOnHand) . '</td>';
            $maxAttr = (int)max((float)$displayOnHand, (float)($prefQty!==''?$prefQty:0));
            echo '<td class="transferqty-cell"><input type="number" class="qty-input" value="' . htmlspecialchars($prefQty) . '" min="0" max="' . $maxAttr . '" step="1" placeholder="0"></td>';
            $initAmt = ($prefQty !== '' ? ($unit_cost * (int)$prefQty) : 0);
            echo '<td class="amount-cell">₱' . number_format($initAmt, 2) . '</td>';
            echo '<td class="cond-cell"><input type="text" class="cond-input" value="' . htmlspecialchars($prefCond) . '" placeholder="e.g., Good, For repair" /></td>';
            echo '</tr>';
            $printed++;
          }
          if ($printed === 0) { echo '<tr id="no-items-row"><td colspan="9">No items found for this ITR.</td></tr>'; }
        } else {
          echo '<tr id="no-items-row"><td colspan="9">No items found for this ITR.</td></tr>';
        }
        ?>
      </tbody>
            <tfoot>
              <tr>
                <td colspan="7" style="text-align:right; font-weight:600;">Total Amount:</td>
                <td id="itr-total-amount" style="font-weight:700;">₱0.00</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="form-group" style="margin-top:12px;">
        <label>Reasons for Transfer:</label>
        <textarea id="reason" rows="4" placeholder="Enter detailed reasons for transfer..."><?= htmlspecialchars($itr['reason'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="section-card">
      <h3>Signatories</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Approved by (Agency/Entity Head):</label>
          <input type="text" id="approved_name" value="<?= htmlspecialchars($itr['approved_name'] ?? '') ?>" />
          <input type="text" id="approved_designation" value="<?= htmlspecialchars($itr['approved_designation'] ?? '') ?>" style="margin-top:8px;" />
          <input type="date" id="approved_date" value="<?= htmlspecialchars($itr['approved_date'] ?? '') ?>" style="margin-top:8px;" />
        </div>
        <div class="form-group">
          <label>Released/Issued by (Property/Supply Unit):</label>
          <input type="text" id="released_name" value="<?= htmlspecialchars($itr['released_name'] ?? '') ?>" />
          <input type="text" id="released_designation" value="<?= htmlspecialchars($itr['released_designation'] ?? '') ?>" style="margin-top:8px;" />
          <input type="date" id="released_date" value="<?= htmlspecialchars($itr['released_date'] ?? '') ?>" style="margin-top:8px;" />
        </div>
        <div class="form-group">
          <label>Received by (Receiving Officer/Employee):</label>
          <input type="text" id="received_name" value="<?= htmlspecialchars($itr['received_name'] ?? '') ?>" />
          <input type="text" id="received_designation" value="<?= htmlspecialchars($itr['received_designation'] ?? '') ?>" style="margin-top:8px;" />
          <input type="date" id="received_date" value="<?= htmlspecialchars($itr['received_date'] ?? '') ?>" style="margin-top:8px;" />
        </div>
      </div>
    </div>

    <div class="actions">
      <button type="button" onclick="submitITREdit()"><i class="fas fa-save"></i> Save Changes</button>
      <button type="button" onclick="cancelITR()"><i class="fas fa-times"></i> Cancel</button>
    </div>
  </div>

  <script>
    const isEdit = true;
    function pad4(n){ const num = parseInt(String(n||'').replace(/[^0-9]/g,''),10); return String(!isNaN(num)&&num>0?num:1).padStart(4,'0'); }
    function updateItrNoPreview(){
      const serial = pad4(document.getElementById('itr_serial').value);
      const mm = String(document.getElementById('itr_month').value||'').padStart(2,'0');
      const yy = String(document.getElementById('itr_year').value||'');
      const composite = serial + '-' + mm + '-' + yy;
      document.getElementById('itr_no_preview').textContent = composite;
      document.getElementById('itr_no').value = composite;
    }
    function filterItems(){
      const q=(document.getElementById('itemSearch').value||'').toLowerCase().trim();
      const rows=document.querySelectorAll('#itemsTable tbody tr.ics-row');
      let visible=0; rows.forEach(r=>{ const text=(r.getAttribute('data-text')||'').toLowerCase(); const show=(q===''||text.includes(q)); r.style.display=show?'':'none'; if(show) visible++; });
      const none=document.getElementById('no-items-row'); if(none) none.style.display=(visible===0)?'table-row':'none';
    }
    function attachQtyHandlers(){
      // Recompute totals
  const recomputeGrandTotal=()=>{ let sum=0; document.querySelectorAll('#itemsTable tbody tr.ics-row .amount-cell').forEach(cell=>{ const txt=(cell.textContent||'').replace(/[^0-9.\-]/g,''); const val=parseFloat(txt||'0')||0; sum+=val;}); document.getElementById('itr-total-amount').textContent='₱'+sum.toFixed(2); };
      document.querySelectorAll('#itemsTable tbody tr.ics-row').forEach(r=>{
        const qtyInput=r.querySelector('.qty-input'); const amountCell=r.querySelector('.amount-cell');
  const unitCost=parseFloat(r.getAttribute('data-unit-cost')||'0'); const onHand=parseFloat(r.getAttribute('data-qty-on-hand')||'0');
        if(!qtyInput) return;
        const recalc=()=>{
          let v=parseFloat(qtyInput.value||'');
          if(isNaN(v)||v<0) v=0;
          const max=parseFloat(qtyInput.getAttribute('max')||'0');
          if(max>0&&v>max) v=max;
          if(amountCell) amountCell.textContent='₱'+(unitCost*v).toFixed(2);
          recomputeGrandTotal();
        };
        // Prevent accidental changes while scrolling the page over number inputs
        qtyInput.addEventListener('wheel', (e)=>{ e.preventDefault(); }, {passive:false});
        qtyInput.addEventListener('keydown', (e)=>{ if(e.key==='ArrowUp'||e.key==='ArrowDown'){ e.preventDefault(); } });
        qtyInput.addEventListener('input', recalc);
        recalc();
      });
      setTimeout(recomputeGrandTotal,0);
    }
    function collectForm(){
      const transferType = document.querySelector('input[name="transfer_type"]:checked');
      const items=[]; document.querySelectorAll('#itemsTable tbody tr.ics-row').forEach(r=>{
        const qty=parseFloat(r.querySelector('.qty-input')?.value||'0')||0; if(qty>0){ const dateCell=r.querySelector('.date-cell'); const itemNoCell=r.querySelector('.itemno-cell'); const icsInfoCell=r.querySelector('.icsinfo-cell'); const descCell=r.querySelector('.desc-cell'); const amtCell=r.querySelector('.amount-cell');
          items.push({
            date_acquired:(dateCell?.textContent||'').trim(),
            item_no:(itemNoCell?.textContent||'').trim(),
            ics_info:(icsInfoCell?.textContent||'').trim(),
            description:(descCell?.textContent||'').trim(),
            amount:(amtCell?.textContent||'').trim(),
            condition:r.querySelector('.cond-input')?.value||'',
            transfer_qty: qty,
            unit_cost: parseFloat(r.getAttribute('data-unit-cost')||'0')||0,
            stock_number: r.getAttribute('data-stock-number') || (itemNoCell?.textContent||'').trim(),
            ics_id: parseInt(r.getAttribute('data-ics-id')||'0',10)||0,
            ics_item_id: parseInt(r.getAttribute('data-ics-item-id')||'0',10)||0
          }); }
      });
      return {
        itr_id: document.getElementById('itr_id').value,
        entity_name: document.getElementById('entity_name').value,
        fund_cluster: document.getElementById('fund_cluster').value,
        from_accountable: document.getElementById('from_accountable').value,
        to_accountable: document.getElementById('to_accountable').value,
        itr_no: document.getElementById('itr_no').value,
        itr_date: document.getElementById('itr_date').value,
        transfer_type: transferType ? transferType.value : '',
        transfer_other: document.getElementById('transfer_other').value,
        reason: document.getElementById('reason').value,
        approved: { name: document.getElementById('approved_name').value, designation: document.getElementById('approved_designation').value, date: document.getElementById('approved_date').value },
        released: { name: document.getElementById('released_name').value, designation: document.getElementById('released_designation').value, date: document.getElementById('released_date').value },
        received: { name: document.getElementById('received_name').value, designation: document.getElementById('received_designation').value, date: document.getElementById('received_date').value },
        items
      };
    }
    function clearErrors(){ document.querySelectorAll('.field-error').forEach(el=>el.remove()); document.querySelectorAll('.has-error').forEach(el=>el.classList.remove('has-error')); }
    function showError(input,message){ if(!input) return; const group=input.closest('.form-group')||input.parentElement||input; group.classList.add('has-error'); if(!group.querySelector('.field-error')){ const div=document.createElement('div'); div.className='field-error'; div.textContent=message; group.appendChild(div);} }
    function validateITR(){
      clearErrors(); let firstInvalid=null;
      const fromEl=document.getElementById('from_accountable'); const toEl=document.getElementById('to_accountable'); const dateEl=document.getElementById('itr_date');
      if(!fromEl.value.trim()){ showError(fromEl,'This field is required.'); firstInvalid=firstInvalid||fromEl; }
      if(!toEl.value.trim()){ showError(toEl,'This field is required.'); firstInvalid=firstInvalid||toEl; }
      if(!dateEl.value){ showError(dateEl,'Please choose a date.'); firstInvalid=firstInvalid||dateEl; }
      const selectedType=document.querySelector('input[name="transfer_type"]:checked');
      if(!selectedType){ const anyRadio=document.querySelector('input[name="transfer_type"]'); showError(anyRadio,'Please select a transfer type.'); firstInvalid=firstInvalid||anyRadio; } else if(selectedType.value==='Others'){ const other=document.getElementById('transfer_other'); if(!other.value.trim()){ showError(other,'Please specify the transfer type.'); firstInvalid=firstInvalid||other; } }
      const rows=Array.from(document.querySelectorAll('#itemsTable tbody tr.ics-row'));
      const selectedRows=rows.filter(r=> (parseFloat(r.querySelector('.qty-input')?.value||'0')||0) > 0);
      if(selectedRows.length===0){ const tableFrame=document.querySelector('.table-frame'); if(tableFrame && !tableFrame.querySelector('.field-error')){ const warn=document.createElement('div'); warn.className='field-error'; warn.style.margin='8px 12px'; warn.textContent='Please select at least one item to transfer.'; tableFrame.appendChild(warn);} firstInvalid=firstInvalid||document.getElementById('itemSearch'); }
      if(firstInvalid){ updateItrNoPreview(); firstInvalid.scrollIntoView({behavior:'smooth', block:'center'}); try{ firstInvalid.focus({preventScroll:true}); }catch(e){} return false; }
      return true;
    }
    async function submitITREdit(){
      if(!validateITR()) return;
      const full = collectForm();
      // Send full items including transfer_qty, unit_cost, and identifiers so backend can update Semi table accurately
      const payload = {
        itr_id: full.itr_id,
        itr_no: full.itr_no,
        itr_date: full.itr_date,
        entity_name: full.entity_name,
        fund_cluster: full.fund_cluster,
        from_accountable: full.from_accountable,
        to_accountable: full.to_accountable,
        transfer_type: full.transfer_type,
        transfer_other: full.transfer_other,
        reason: full.reason,
        approved: full.approved,
        released: full.released,
        received: full.received,
        items: full.items
      };
      try{
        const res = await fetch('update_itr.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const json = await res.json().catch(()=>({success:false,message:'Invalid server response'}));
        if(!res.ok || !json.success){ const msg=(json && json.message)?json.message:('Update failed with status '+res.status); alert(msg); return; }
        window.location.href='itr.php';
      }catch(e){ alert('Failed to update ITR: '+(e.message||e)); }
    }
    function cancelITR(){ if(confirm('Discard changes and leave this page?')){ window.location.href='itr.php'; } }
    document.addEventListener('DOMContentLoaded', ()=>{
      updateItrNoPreview();
      attachQtyHandlers();
      ['itr_serial','itr_month','itr_year'].forEach(id=>{ const el=document.getElementById(id); if(el){ el.addEventListener('input', updateItrNoPreview); el.addEventListener('blur', updateItrNoPreview);} });
    });
  </script>
</body>
</html>
