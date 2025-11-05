<?php
require 'config.php';
require 'functions.php';

// Compute initial next ITR serial on the server to ensure the page shows the correct next number even if JS fetch is blocked/cached
$todayY = date('Y');
$todayM = date('m');
$initial_serial = '0001';
try {
  $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(itr_no, '-', 1) AS UNSIGNED)) AS max_serial FROM itr WHERE itr_no LIKE CONCAT('%-', ?, '-', ?)");
  if ($stmt) {
    $stmt->bind_param('ss', $todayM, $todayY);
    $stmt->execute();
    $res = $stmt->get_result();
    $max = 0;
    if ($res) { $row = $res->fetch_assoc(); $max = isset($row['max_serial']) ? (int)$row['max_serial'] : 0; }
    $stmt->close();
    if ($max > 0) { $initial_serial = str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT); }
  }
  if ($initial_serial === '0001') {
    $suffix = '-' . $todayM . '-' . $todayY;
    $stmt2 = $conn->prepare("SELECT itr_no FROM itr WHERE itr_no LIKE CONCAT('%', ?) ORDER BY itr_no DESC LIMIT 1");
    if ($stmt2) {
      $stmt2->bind_param('s', $suffix);
      $stmt2->execute();
      $r2 = $stmt2->get_result();
      if ($r2 && $r2->num_rows > 0) {
        $row2 = $r2->fetch_assoc();
        if (preg_match('/^(\\d{1,4})-/', $row2['itr_no'], $m)) {
          $cand = (int)$m[1];
          if ($cand > 0) { $initial_serial = str_pad((string)($cand + 1), 4, '0', STR_PAD_LEFT); }
        }
      }
      $stmt2->close();
    }
  }
} catch (Throwable $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add ITR Form</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
    .section-card h3 { margin:0 0 12px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-grid .form-group { display:flex; flex-direction:column; }
    @media (max-width: 800px) { .form-grid { grid-template-columns:1fr; } }

    /* Items table frame and viewport (match Add ICS look, no top white gap) */
    .table-frame { border:1px solid #e5e7eb; border-top:0; border-radius:0 0 8px 8px; overflow:hidden; }
  .table-viewport { max-height:420px; overflow-y:auto; overflow-x:auto; scrollbar-gutter:stable; background: var(--white); }
  #itemsTable { overflow:visible !important; width:100%; border-collapse:collapse; background:transparent !important; border-radius:0 !important; margin-top:0 !important; }
  #itemsTable thead th { position:sticky; top:0; z-index:3; background: var(--blue-gradient); color:#fff; }
    .table-frame, .table-viewport, #itemsTable { border-top-left-radius:0 !important; border-top-right-radius:0 !important; }
    #itemsTable td, #itemsTable th { border:1px solid #e5e7eb; padding:6px 8px; }

    .actions { display:flex; gap:10px; align-items:center; margin-top:14px; }

    /* Validation visuals similar to Add Semi behavior */
    .field-error { color:#b91c1c; font-size:0.85rem; margin-top:6px; }
    .has-error input,
    .has-error textarea,
    .has-error select { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,0.15); }
  </style>
</head>
<body>
  <?php include 'sidebar.php'; ?>
  <div class="edit-ics-page content edit-ris-page">
    <h2>Add Inventory Transfer Report (ITR)</h2>

    <!-- Instructions (Annex A.5) - collapsible helper to match the reference image
    <div class="section-card" style="background:#f9fbff; border-color:#dbeafe;">
      <details>
        <summary style="cursor:pointer; font-weight:600; color:#0038a8;">Instructions (Annex A.5)</summary>
        <div style="margin-top:10px; color:#374151; font-size:0.95rem; line-height:1.5;">
          <p style="margin:6px 0;">A. Use this form for inventory transfers such as donation, reassignment, relocation, etc.</p>
          <p style="margin:6px 0;">B. Accomplish as follows:</p>
          <ol style="padding-left:20px; margin:6px 0;">
            <li>Entity Name – name of the agency/entity</li>
            <li>Fund Cluster – based on UACS</li>
            <li>From Accountable Officer/Agency/Fund Cluster – where the inventory is located</li>
            <li>To Accountable Officer/Agency/Fund Cluster – where the inventory is transferred</li>
            <li>ITR No. – Serial-Month-Year (e.g., 0001-<?= date('m') ?>-<?= date('Y') ?>)</li>
            <li>Date – date of preparation of the ITR</li>
            <li>Transfer Type – check one</li>
            <li>Date Acquired, Item No., ICS No./Date, Description, Amount, Condition of Inventory</li>
            <li>Reason/s for Transfer</li>
            <li>Approved by, Released/Issued by, Received by – with names/designations and dates</li>
          </ol>
        </div>
      </details>
    </div> -->

    <!-- Header: Entity, Fund, From/To, ITR No/Date, Transfer Type -->
    <div class="section-card">
      <h3>ITR Details</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Entity Name:</label>
          <input type="text" id="entity_name" value="TESDA Regional Office" />
        </div>
        <div class="form-group">
          <label>Fund Cluster:</label>
          <input type="text" id="fund_cluster" value="" />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>From Accountable Officer/Agency/Fund Cluster:</label>
          <input type="text" id="from_accountable" placeholder="From..." />
        </div>
        <div class="form-group">
          <label>To Accountable Officer/Agency/Fund Cluster:</label>
          <input type="text" id="to_accountable" placeholder="To..." />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>ITR No. (Serial-Month-Year):</label>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="text" id="itr_serial" value="<?= htmlspecialchars($initial_serial) ?>" style="max-width:110px;" readonly />
            <span>-</span>
            <input type="text" id="itr_month" value="<?= date('m'); ?>" style="max-width:70px;" readonly />
            <span>-</span>
            <input type="text" id="itr_year" value="<?= date('Y'); ?>" style="max-width:90px;" readonly />
          </div>
          <small style="display:block;color:#6b7280;margin-top:6px;">Preview: <code id="itr_no_preview"><?= $initial_serial ?>-<?= date('m') ?>-<?= date('Y') ?></code></small>
          <input type="hidden" id="itr_no" />
        </div>
        <div class="form-group">
          <label>Date:</label>
          <input type="date" id="itr_date" value="<?= date('Y-m-d'); ?>" />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Transfer Type (check only one):</label>
          <div style="display:flex; gap:16px; flex-wrap:wrap; padding-top:6px;">
            <label><input type="radio" name="transfer_type" value="Donation"> Donation</label>
            <label><input type="radio" name="transfer_type" value="Reassignment"> Reassignment</label>
            <label><input type="radio" name="transfer_type" value="Relocate"> Relocate</label>
            <label><input type="radio" name="transfer_type" value="Others"> Others</label>
            <input type="text" id="transfer_other" placeholder="Specify (if Others)" style="min-width:220px;" />
          </div>
        </div>
      </div>
    </div>

    <!-- Items from ICS -->
    <div class="section-card">
      <h3>Items (from ICS)</h3>
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
                <th>Balance</th>
                <th>Amount</th>
                <th>Condition of Inventory</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Pull items from ICS + ICS Items
              $sql = "SELECT ii.*, i.ics_no, i.date_issued
                        FROM ics_items ii
                        INNER JOIN ics i ON i.ics_id = ii.ics_id
                        ORDER BY i.date_issued DESC, ii.ics_item_id DESC";
              $result = $conn->query($sql);
              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      $date_acquired = $row['date_issued'] ?? '';
                      $item_no = $row['inventory_item_no'] ?? $row['stock_number'] ?? '';
                      $ics_info = ($row['ics_no'] ?? '') . (isset($row['date_issued']) ? (' / ' . $row['date_issued']) : '');
                      $desc = $row['description'] ?? '';
                      $unit_cost = isset($row['unit_cost']) ? (float)$row['unit_cost'] : 0.0;
                      // Fallback: if unit_cost missing but total_cost and quantity present
                      if ($unit_cost <= 0 && isset($row['total_cost']) && isset($row['quantity']) && (float)$row['quantity'] > 0) {
                        $unit_cost = ((float)$row['total_cost']) / max(1, (float)$row['quantity']);
                      }
                      $qty_on_hand = isset($row['quantity']) ? (float)$row['quantity'] : 1;
                      $rowText = strtolower(($item_no ?: '') . ' ' . ($ics_info ?: '') . ' ' . ($desc ?: ''));
                      echo '<tr class="ics-row" data-text="' . htmlspecialchars($rowText) . '" data-unit-cost="' . htmlspecialchars(number_format($unit_cost,2,'.','')) . '" data-qty-on-hand="' . htmlspecialchars((string)$qty_on_hand) . '" data-ics-id="' . (int)$row['ics_id'] . '" data-ics-item-id="' . (int)$row['ics_item_id'] . '" data-stock-number="' . htmlspecialchars($row['stock_number'] ?? $item_no) . '">';
                      echo '<td class="date-cell">' . htmlspecialchars($date_acquired) . '</td>';
                      echo '<td class="itemno-cell">' . htmlspecialchars($item_no) . '</td>';
                      echo '<td class="icsinfo-cell">' . htmlspecialchars($ics_info) . '</td>';
                      echo '<td class="desc-cell">' . htmlspecialchars($desc) . '</td>';
                      echo '<td class="unitcost-cell">₱' . number_format($unit_cost, 2) . '</td>';
                      echo '<td class="qtyonhand-cell">' . htmlspecialchars((string)$qty_on_hand) . '</td>';
                      echo '<td class="transferqty-cell"><input type="number" class="qty-input" value="" min="0" max="' . htmlspecialchars((string)$qty_on_hand) . '" step="1" placeholder="0"></td>';
                      echo '<td class="balance-cell">' . htmlspecialchars((string)$qty_on_hand) . '</td>';
                      echo '<td class="amount-cell">₱0.00</td>';
                      echo '<td class="cond-cell"><input type="text" class="cond-input" placeholder="e.g., Good, For repair" /></td>';
                      echo '</tr>';
                  }
              } else {
                  echo '<tr id="no-items-row"><td colspan="10">No ICS items found.</td></tr>';
              }
              ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="8" style="text-align:right; font-weight:600;">Total Amount:</td>
                <td id="itr-total-amount" style="font-weight:700;">₱0.00</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="form-group" style="margin-top:12px;">
        <label>Reasons for Transfer:</label>
        <textarea id="reason" rows="4" placeholder="Enter detailed reasons for transfer..."></textarea>
      </div>
    </div>

    <!-- Signatories (Approved / Released / Received) to match Annex A.5 -->
    <div class="section-card">
      <h3>Signatories</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Approved by (Agency/Entity Head):</label>
          <input type="text" id="approved_name" placeholder="Full name" />
          <input type="text" id="approved_designation" placeholder="Designation" style="margin-top:8px;" />
          <input type="date" id="approved_date" style="margin-top:8px;" />
        </div>
        <div class="form-group">
          <label>Released/Issued by (Property/Supply Unit):</label>
          <input type="text" id="released_name" placeholder="Full name" />
          <input type="text" id="released_designation" placeholder="Designation" style="margin-top:8px;" />
          <input type="date" id="released_date" style="margin-top:8px;" />
        </div>
        <div class="form-group">
          <label>Received by (Receiving Officer/Employee):</label>
          <input type="text" id="received_name" placeholder="Full name" />
          <input type="text" id="received_designation" placeholder="Designation" style="margin-top:8px;" />
          <input type="date" id="received_date" style="margin-top:8px;" />
        </div>
      </div>
    </div>

    <div class="actions">
      <button type="button" onclick="submitITR()"><i class="fas fa-paper-plane"></i> Submit ITR</button>
      <button type="button" onclick="cancelITR()"><i class="fas fa-times"></i> Cancel</button>
    </div>
  </div>

  <script>
    function filterItems() {
      const q = (document.getElementById('itemSearch').value || '').toLowerCase().trim();
      const rows = document.querySelectorAll('#itemsTable tbody tr.ics-row');
      let visible = 0;
      rows.forEach(r => {
        const text = (r.getAttribute('data-text') || '').toLowerCase();
        const show = q === '' || text.includes(q);
        r.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      const none = document.getElementById('no-items-row');
      if (none) none.style.display = (visible === 0) ? 'table-row' : 'none';
    }

    function collectForm() {
      const transferType = document.querySelector('input[name="transfer_type"]:checked');
      const items = [];
      document.querySelectorAll('#itemsTable tbody tr.ics-row').forEach(r => {
        const qty = parseFloat(r.querySelector('.qty-input')?.value || '0') || 0;
        if (qty > 0) {
          const dateCell = r.querySelector('.date-cell');
          const itemNoCell = r.querySelector('.itemno-cell');
          const icsInfoCell = r.querySelector('.icsinfo-cell');
          const descCell = r.querySelector('.desc-cell');
          const amtCell = r.querySelector('.amount-cell');
          items.push({
            date_acquired: (dateCell?.textContent || '').trim(),
            item_no: (itemNoCell?.textContent || '').trim(),
            ics_info: (icsInfoCell?.textContent || '').trim(),
            description: (descCell?.textContent || '').trim(),
            amount: (amtCell?.textContent || '').trim(),
            condition: r.querySelector('.cond-input')?.value || '',
            transfer_qty: qty,
            unit_cost: parseFloat(r.getAttribute('data-unit-cost') || '0') || 0,
            stock_number: r.getAttribute('data-stock-number') || (itemNoCell?.textContent || '').trim(),
            ics_id: parseInt(r.getAttribute('data-ics-id') || '0', 10) || 0,
            ics_item_id: parseInt(r.getAttribute('data-ics-item-id') || '0', 10) || 0
          });
        }
      });
      return {
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

    function previewITR() {
      const data = collectForm();
      // For now, just open a simple print preview; can be replaced with a styled PDF later
      const w = window.open('', '_blank');
      w.document.write('<html><head><title>ITR Preview</title><style>body{font-family:Arial; padding:16px;} table{width:100%; border-collapse:collapse;} th,td{border:1px solid #ddd; padding:6px 8px;} th{background:#eef5ff;}</style></head><body>');
      w.document.write('<h2>Inventory Transfer Report</h2>');
      w.document.write('<p><strong>Entity:</strong> ' + (data.entity_name||'') + ' &nbsp; <strong>Fund Cluster:</strong> ' + (data.fund_cluster||'') + '</p>');
      w.document.write('<p><strong>From:</strong> ' + (data.from_accountable||'') + '<br/><strong>To:</strong> ' + (data.to_accountable||'') + '</p>');
  w.document.write('<p><strong>ITR No.:</strong> ' + (data.itr_no||'') + ' &nbsp; <strong>Date:</strong> ' + (data.itr_date||'') + '</p>');
      w.document.write('<p><strong>Transfer Type:</strong> ' + (data.transfer_type||'') + (data.transfer_type==='Others' ? (' - ' + (data.transfer_other||'')) : '') + '</p>');
      w.document.write('<table><thead><tr><th>Date Acquired</th><th>Item No.</th><th>ICS No./Date</th><th>Description</th><th>Amount</th><th>Condition</th></tr></thead><tbody>');
      if (data.items.length === 0) {
        w.document.write('<tr><td colspan="6" style="text-align:center;">No items selected.</td></tr>');
      } else {
        data.items.forEach(it => {
          w.document.write('<tr>' +
            '<td>' + it.date_acquired + '</td>' +
            '<td>' + it.item_no + '</td>' +
            '<td>' + it.ics_info + '</td>' +
            '<td>' + it.description + '</td>' +
            '<td>' + it.amount + '</td>' +
            '<td>' + it.condition + '</td>' +
          '</tr>');
        });
      }
      w.document.write('</tbody></table>');
      w.document.write('<h3>Reasons for Transfer</h3><p>' + (data.reason||'') + '</p>');
      w.document.write('<h3>Signatories</h3>');
      w.document.write('<table><tbody>' +
        '<tr><th>Approved by</th><td>' + (data.approved?.name||'') + ' — ' + (data.approved?.designation||'') + ' ' + (data.approved?.date||'') + '</td></tr>' +
        '<tr><th>Released/Issued by</th><td>' + (data.released?.name||'') + ' — ' + (data.released?.designation||'') + ' ' + (data.released?.date||'') + '</td></tr>' +
        '<tr><th>Received by</th><td>' + (data.received?.name||'') + ' — ' + (data.received?.designation||'') + ' ' + (data.received?.date||'') + '</td></tr>' +
      '</tbody></table>');
      w.document.write('</body></html>');
      w.document.close();
      w.focus();
      try { w.print(); } catch(e){}
    }

    function saveDraft() {
      const data = collectForm();
      try {
        localStorage.setItem('itr_draft', JSON.stringify(data));
        alert('Draft saved locally.');
      } catch (e) {
        alert('Unable to save draft locally.');
      }
    }

    function pad4(n){
      // Accept numeric string or number; output four digits with leading zeros
      if (n === null || n === undefined) return '0001';
      const num = parseInt(String(n).replace(/[^0-9]/g,''), 10);
      const safe = (!isNaN(num) && num > 0) ? num : 1;
      return String(safe).padStart(4, '0');
    }
    function updateItrNoPreview(){
      const serial = pad4(document.getElementById('itr_serial').value);
      const d = document.getElementById('itr_date').value || '<?= date('Y-m-d'); ?>';
      const dt = new Date(d.replace(/-/g,'/'));
      const mm = String((dt.getMonth()+1)||<?= (int)date('m') ?>).padStart(2,'0');
      const yy = String(dt.getFullYear()||<?= (int)date('Y') ?>);
      const composite = serial + '-' + mm + '-' + yy;
      document.getElementById('itr_month').value = mm;
      document.getElementById('itr_year').value = yy;
      document.getElementById('itr_no_preview').textContent = composite;
      document.getElementById('itr_no').value = composite;
    }
    async function refreshSerialForCurrentDate(){
      try {
        const d = document.getElementById('itr_date').value || '<?= date('Y-m-d'); ?>';
        const dt = new Date(d.replace(/-/g,'/'));
        const mm = String((dt.getMonth()+1)||<?= (int)date('m') ?>).padStart(2,'0');
        const yy = String(dt.getFullYear()||<?= (int)date('Y') ?>);
        const resp = await fetch(`get_next_itr_serial.php?month=${mm}&year=${yy}&t=${Date.now()}`); // Fetch the next serial
        const json = await resp.json().catch(()=>({success:false}));
        if (json && json.success && json.next_serial) {
          document.getElementById('itr_serial').value = pad4(json.next_serial);
        } else {
          document.getElementById('itr_serial').value = pad4(document.getElementById('itr_serial').value||'0001');
        }
      } catch(e) {
        document.getElementById('itr_serial').value = pad4(document.getElementById('itr_serial').value||'0001');
      } finally {
        updateItrNoPreview();
      }
    }
    function formatSerial(){
      const inp = document.getElementById('itr_serial');
      if (!inp) return;
      // Keep as two-digit string if ever changed programmatically
      inp.value = pad4(inp.value);
      updateItrNoPreview();
    }

    function clearErrors() {
      document.querySelectorAll('.field-error').forEach(el => el.remove());
      document.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
    }

    function showError(input, message) {
      if (!input) return;
      const group = input.closest('.form-group') || input.parentElement || input;
      group.classList.add('has-error');
      // Avoid duplicating the same error
      if (!group.querySelector('.field-error')) {
        const div = document.createElement('div');
        div.className = 'field-error';
        div.textContent = message;
        group.appendChild(div);
      }
    }

    function validateITR() {
      clearErrors();
      let firstInvalid = null;

      // Required basics
      const fromEl = document.getElementById('from_accountable');
      const toEl = document.getElementById('to_accountable');
      const dateEl = document.getElementById('itr_date');
      if (!fromEl.value.trim()) { showError(fromEl, 'This field is required.'); firstInvalid = firstInvalid || fromEl; }
      if (!toEl.value.trim()) { showError(toEl, 'This field is required.'); firstInvalid = firstInvalid || toEl; }
      if (!dateEl.value) { showError(dateEl, 'Please choose a date.'); firstInvalid = firstInvalid || dateEl; }

      // Transfer type required; if Others, require specify
      const selectedType = document.querySelector('input[name="transfer_type"]:checked');
      if (!selectedType) {
        const anyRadio = document.querySelector('input[name="transfer_type"]');
        showError(anyRadio, 'Please select a transfer type.');
        firstInvalid = firstInvalid || anyRadio;
      } else if (selectedType.value === 'Others') {
        const other = document.getElementById('transfer_other');
        if (!other.value.trim()) {
          showError(other, 'Please specify the transfer type.');
          firstInvalid = firstInvalid || other;
        }
      }

      // At least one item selected
      const rows = Array.from(document.querySelectorAll('#itemsTable tbody tr.ics-row'));
      const selectedRows = rows.filter(r => (parseFloat(r.querySelector('.qty-input')?.value || '0') || 0) > 0);
      if (selectedRows.length === 0) {
        const anyQty = document.querySelector('#itemsTable .qty-input');
        // Place the error on the table frame for visibility
        const tableFrame = document.querySelector('.table-frame');
        if (tableFrame && !tableFrame.querySelector('.field-error')) {
          const warn = document.createElement('div');
          warn.className = 'field-error';
          warn.style.margin = '8px 12px';
          warn.textContent = 'Please select at least one item to transfer.';
          tableFrame.appendChild(warn);
        }
        firstInvalid = firstInvalid || anyQty || document.getElementById('itemSearch');
      } else {
        // For each selected: require transfer qty > 0 and condition
        for (const r of selectedRows) {
          const qtyInput = r.querySelector('.qty-input');
          const max = parseFloat(qtyInput?.getAttribute('max') || '0');
          let val = parseFloat(qtyInput?.value || '');
          if (isNaN(val) || val <= 0) {
            showError(qtyInput, 'Enter a transfer quantity greater than 0.');
            firstInvalid = firstInvalid || qtyInput;
            break;
          }
          if (val > max) {
            qtyInput.value = String(max);
            showError(qtyInput, 'Transfer quantity exceeds available balance. Clamped to max.');
            firstInvalid = firstInvalid || qtyInput;
            break;
          }
          const cond = r.querySelector('.cond-input');
          if (!cond || !cond.value.trim()) {
            showError(cond, 'Please provide the inventory condition.');
            firstInvalid = firstInvalid || cond;
            break;
          }
        }
      }

      if (firstInvalid) {
        // Ensure ITR No. is updated before stop
        updateItrNoPreview();
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        try { firstInvalid.focus({ preventScroll: true }); } catch(e) { try { firstInvalid.focus(); } catch(_){} }
        return false;
      }
      return true;
    }

    async function submitITR(didRetry=false) {
      // Validate required fields similar to Add Semi behavior
      if (!validateITR()) {
        return; // focus/scroll handled in validateITR
      }
      const data = collectForm();
      if (!data.itr_no) { updateItrNoPreview(); data.itr_no = document.getElementById('itr_no').value; }
      try {
        const res = await fetch('submit_itr.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        const json = await res.json().catch(()=>({success:false,message:'Invalid server response'}));
        if (!res.ok || !json.success) {
          // Handle duplicate ITR No.: refresh serial and retry once automatically
          const msg = (json && json.message) ? String(json.message) : '';
          if (!didRetry && (res.status === 409 || /already exists/i.test(msg))) {
            await refreshSerialForCurrentDate();
            return submitITR(true);
          }
          throw new Error(msg || ('Request failed with status ' + res.status));
        }
        try { localStorage.removeItem('itr_draft'); } catch(e){}
        // No notification on success; just redirect to ITR list
        window.location.href = 'itr.php';
      } catch (err) {
        alert('Failed to submit ITR: ' + (err.message || err));
      }
    }

    function cancelITR() {
      if (confirm('Discard changes and leave this page?')) {
        try { localStorage.removeItem('itr_draft'); } catch(e){}
        window.location.href = 'itr.php';
      }
    }

    // Restore draft if available
    function attachQtyHandlers() {
      const recomputeGrandTotal = () => {
        let sum = 0;
        document.querySelectorAll('#itemsTable tbody tr.ics-row .amount-cell').forEach(cell => {
          const txt = (cell.textContent || '').replace(/[^0-9.\-]/g, '');
          const val = parseFloat(txt || '0') || 0;
          sum += val;
        });
        const totalEl = document.getElementById('itr-total-amount');
        if (totalEl) totalEl.textContent = '₱' + sum.toFixed(2);
      };
      const rows = document.querySelectorAll('#itemsTable tbody tr.ics-row');
      rows.forEach(r => {
        const qtyInput = r.querySelector('.qty-input');
        const amountCell = r.querySelector('.amount-cell');
        const balanceCell = r.querySelector('.balance-cell');
        const unitCost = parseFloat(r.getAttribute('data-unit-cost') || '0');
        const onHand = parseFloat(r.getAttribute('data-qty-on-hand') || '0');
        if (!qtyInput) return;
        const recalc = () => {
          let v = parseFloat(qtyInput.value || '');
          if (isNaN(v) || v < 0) v = 0;
          const max = parseFloat(qtyInput.getAttribute('max') || '0');
          if (max > 0 && v > max) v = max;
          // Update UI
          if (amountCell) amountCell.textContent = '₱' + (unitCost * v).toFixed(2);
          if (balanceCell) balanceCell.textContent = String(Math.max(0, onHand - v));
          recomputeGrandTotal();
        };
        qtyInput.addEventListener('input', recalc);
        qtyInput.addEventListener('blur', recalc);
        // Initialize
        recalc();
      });
      // Initial grand total in case there are prefilled values
      setTimeout(recomputeGrandTotal, 0);
    }

    document.addEventListener('DOMContentLoaded', () => {
      // Try to restore draft, but do NOT exit early if none
      try {
        const raw = localStorage.getItem('itr_draft');
        if (raw) {
          const d = JSON.parse(raw);
          if (d) {
            document.getElementById('entity_name').value = d.entity_name || '';
            document.getElementById('fund_cluster').value = d.fund_cluster || '';
            document.getElementById('from_accountable').value = d.from_accountable || '';
            document.getElementById('to_accountable').value = d.to_accountable || '';
            if (d.itr_no) {
              // Try to split saved ITR No. into parts (1 to 4-digit serial)
              const m = String(d.itr_no).match(/^(\d{1,4})-(\d{2})-(\d{4})$/);
              if (m) {
                document.getElementById('itr_serial').value = pad4(m[1]);
                document.getElementById('itr_month').value = m[2];
                document.getElementById('itr_year').value = m[3];
              }
              document.getElementById('itr_no').value = d.itr_no;
              document.getElementById('itr_no_preview').textContent = d.itr_no;
            }
            document.getElementById('itr_date').value = d.itr_date || document.getElementById('itr_date').value;
            if (d.transfer_type) {
              const r = document.querySelector('input[name="transfer_type"][value="' + d.transfer_type + '"]');
              if (r) r.checked = true;
            }
            document.getElementById('transfer_other').value = d.transfer_other || '';
            document.getElementById('reason').value = d.reason || '';

            // Signatories
            document.getElementById('approved_name').value = d.approved?.name || '';
            document.getElementById('approved_designation').value = d.approved?.designation || '';
            document.getElementById('approved_date').value = d.approved?.date || '';
            document.getElementById('released_name').value = d.released?.name || '';
            document.getElementById('released_designation').value = d.released?.designation || '';
            document.getElementById('released_date').value = d.released?.date || '';
            document.getElementById('received_name').value = d.received?.name || '';
            document.getElementById('received_designation').value = d.received?.designation || '';
            document.getElementById('received_date').value = d.received?.date || '';
          }
        }
      } catch(e){}

      // Initialize serial and ITR number preview from server
      refreshSerialForCurrentDate();

      // On date change, get next serial for that month/year then update preview
      const dt = document.getElementById('itr_date');
      if (dt) dt.addEventListener('change', () => { refreshSerialForCurrentDate(); });
      // Serial is read-only; keep value normalized just in case
      formatSerial();
      // Setup qty handlers for dynamic amount/balance like ICS
      attachQtyHandlers();
    });
  </script>
</body>
</html>
