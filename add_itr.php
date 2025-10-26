<?php
require 'config.php';
require 'functions.php';
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
            <input type="number" id="itr_serial" min="1" step="1" value="1" style="max-width:110px;" oninput="formatSerial()" />
            <span>-</span>
            <input type="text" id="itr_month" value="<?= date('m'); ?>" style="max-width:70px;" readonly />
            <span>-</span>
            <input type="text" id="itr_year" value="<?= date('Y'); ?>" style="max-width:90px;" readonly />
          </div>
          <small style="display:block;color:#6b7280;margin-top:6px;">Preview: <code id="itr_no_preview">0001-<?= date('m') ?>-<?= date('Y') ?></code></small>
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
                <th>Select</th>
                <th>Date Acquired</th>
                <th>Item No.</th>
                <th>ICS No./Date</th>
                <th>Description</th>
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
                      $amount = isset($row['total_cost']) ? (float)$row['total_cost'] : ((isset($row['unit_cost']) ? (float)$row['unit_cost'] : 0));
                      echo '<tr class="ics-row" data-text="' . htmlspecialchars(strtolower($item_no . ' ' . $ics_info . ' ' . $desc)) . '">';
                      echo '<td><input type="checkbox" class="chk-include"></td>';
                      echo '<td>' . htmlspecialchars($date_acquired) . '</td>';
                      echo '<td>' . htmlspecialchars($item_no) . '</td>';
                      echo '<td>' . htmlspecialchars($ics_info) . '</td>';
                      echo '<td>' . htmlspecialchars($desc) . '</td>';
                      echo '<td>₱' . number_format($amount, 2) . '</td>';
                      echo '<td><input type="text" class="cond-input" placeholder="e.g., Good, For repair" /></td>';
                      echo '</tr>';
                  }
              } else {
                  echo '<tr id="no-items-row"><td colspan="7">No ICS items found.</td></tr>';
              }
              ?>
            </tbody>
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
      <button type="button" onclick="previewITR()"><i class="fas fa-eye"></i> Preview/Print</button>
      <button type="button" onclick="saveDraft()"><i class="fas fa-save"></i> Save Draft (Local)</button>
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
        const chk = r.querySelector('.chk-include');
        if (chk && chk.checked) {
          items.push({
            date_acquired: r.children[1].textContent.trim(),
            item_no: r.children[2].textContent.trim(),
            ics_info: r.children[3].textContent.trim(),
            description: r.children[4].textContent.trim(),
            amount: r.children[5].textContent.trim(),
            condition: r.querySelector('.cond-input')?.value || ''
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

    function pad4(n){ n = parseInt(n||'0',10); if (isNaN(n) || n < 1) n = 1; return String(n).padStart(4,'0'); }
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
    function formatSerial(){
      const inp = document.getElementById('itr_serial');
      if (!inp) return; inp.value = inp.value.replace(/[^0-9]/g,'');
      updateItrNoPreview();
    }

    // Restore draft if available
    document.addEventListener('DOMContentLoaded', () => {
      try {
        const raw = localStorage.getItem('itr_draft');
        if (!raw) return;
        const d = JSON.parse(raw);
        if (!d) return;
        document.getElementById('entity_name').value = d.entity_name || '';
        document.getElementById('fund_cluster').value = d.fund_cluster || '';
        document.getElementById('from_accountable').value = d.from_accountable || '';
        document.getElementById('to_accountable').value = d.to_accountable || '';
        if (d.itr_no) {
          // Try to split saved ITR No. into parts
          const m = String(d.itr_no).match(/^(\d{4})-(\d{2})-(\d{4})$/);
          if (m) {
            document.getElementById('itr_serial').value = parseInt(m[1],10);
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
      } catch(e){}

      // Initialize ITR number preview
      updateItrNoPreview();

      // Recompute ITR No on date change
      const dt = document.getElementById('itr_date');
      if (dt) dt.addEventListener('change', updateItrNoPreview);
      const ser = document.getElementById('itr_serial');
      if (ser) ser.addEventListener('input', formatSerial);
    });
  </script>
</body>
</html>
