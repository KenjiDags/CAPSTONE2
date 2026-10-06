<?php
require 'auth.php';
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

// Remaining ICS issuances and transferred/reissued quantities are separate return sources.
function rrspReturnOptions(mysqli $conn): array {
  $options = [];
  $issued = $conn->query("SELECT ii.*, i.ics_no, i.date_issued, i.received_by,
      sp.id AS semi_id, LEAST(ii.quantity, COALESCE(sp.quantity_issued, ii.quantity)) AS available_qty
      FROM ics_items ii JOIN ics i ON i.ics_id = ii.ics_id
      LEFT JOIN semi_expendable_property sp ON sp.semi_expendable_property_no = ii.stock_number
      WHERE ii.quantity > 0 AND (sp.id IS NULL OR sp.quantity_issued > 0)
      ORDER BY i.date_issued DESC, ii.ics_item_id DESC");
  foreach ($issued as $row) {
    $options[] = ['source_type' => 'issued', 'source_id' => (int)$row['ics_item_id'],
      'stock_number' => $row['stock_number'], 'description' => $row['description'],
      'ics_no' => $row['ics_no'], 'date' => $row['date_issued'], 'status' => 'Issued',
      'quantity' => (int)$row['available_qty'], 'unit_cost' => (float)$row['unit_cost'],
      'end_user' => $row['received_by']];
  }
  $register = $conn->query("SELECT sp.* FROM semi_expendable_property sp
      WHERE sp.quantity_reissued > 0 OR (sp.quantity_issued > 0 AND NOT EXISTS
        (SELECT 1 FROM ics_items ii WHERE ii.stock_number = sp.semi_expendable_property_no AND ii.quantity > 0))
      ORDER BY sp.item_description");
  foreach ($register as $row) {
    foreach (['transferred' => 'quantity_reissued', 'issued-register' => 'quantity_issued'] as $type => $column) {
      if ((int)$row[$column] <= 0) continue;
      if ($type === 'issued-register') {
        $check = $conn->prepare('SELECT 1 FROM ics_items WHERE stock_number = ? AND quantity > 0 LIMIT 1');
        $check->bind_param('s', $row['semi_expendable_property_no']);
        $check->execute();
        $hasIcs = $check->get_result()->num_rows > 0;
        $check->close();
        if ($hasIcs) continue;
      }
      $options[] = ['source_type' => $type, 'source_id' => (int)$row['id'],
        'stock_number' => $row['semi_expendable_property_no'], 'description' => $row['item_description'],
        'ics_no' => $row['semi_expendable_property_no'], 'date' => $row['date'],
        'status' => $type === 'transferred' ? 'Transferred / Reissued' : 'Issued',
        'quantity' => (int)$row[$column], 'unit_cost' => (float)$row['amount'],
        'end_user' => $type === 'transferred' ? $row['office_officer_reissued'] : $row['office_officer_issued']];
    }
  }
  return $options;
}
function rrspStatement(mysqli $conn, string $sql, string $types, array $params): mysqli_stmt {
  $statement = $conn->prepare($sql);
  if (!$statement) throw new RuntimeException($conn->error);
  $statement->bind_param($types, ...$params);
  if (!$statement->execute()) throw new RuntimeException($statement->error);
  return $statement;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json');
  $transactionStarted = false;
  try {
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    if (!is_array($items) || !$items) throw new RuntimeException('Select at least one item to return.');
    $date_prepared = $_POST['date_prepared'] ?? date('Y-m-d');
    $rrsp_no = trim($_POST['rrsp_no'] ?? '');
    if ($rrsp_no === '' || strpos($rrsp_no, date('Y-m', strtotime($date_prepared)) . '-') !== 0) {
      $rrsp_no = get_next_rrsp_no($conn, $date_prepared);
    }
    ensure_rrsp_history($conn);
    ensure_ics_history($conn);
    ensure_semi_expendable_history($conn);
    $options = [];
    foreach (rrspReturnOptions($conn) as $option) $options[$option['source_type'] . ':' . $option['source_id']] = $option;
    $conn->begin_transaction();
    $transactionStarted = true;
    $header = rrspStatement($conn, 'INSERT INTO rrsp (rrsp_no,date_prepared,entity_name,fund_cluster,returned_by,received_by,returned_date,received_date,remarks) VALUES (?,?,?,?,?,?,?,?,?)', 'sssssssss',
      [$rrsp_no, $date_prepared, trim($_POST['entity_name'] ?? ''), trim($_POST['fund_cluster'] ?? ''), trim($_POST['returned_by'] ?? ''), trim($_POST['received_by'] ?? ''), $_POST['returned_date'] ?: null, $_POST['received_date'] ?: null, trim($_POST['remarks'] ?? '')]);
    $rrsp_id = $header->insert_id;
    $header->close();
    $seen = [];
    foreach ($items as $item) {
      $key = ($item['source_type'] ?? '') . ':' . (int)($item['source_id'] ?? 0);
      $option = $options[$key] ?? null;
      $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
      if (!$option || isset($seen[$key]) || $qty === false || $qty <= 0 || $qty > $option['quantity']) {
        throw new RuntimeException('Invalid return selection or quantity. Reload the item list and try again.');
      }
      $seen[$key] = true;
      $stock = $option['stock_number'];
      $semiStatement = rrspStatement($conn, 'SELECT * FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1 FOR UPDATE', 's', [$stock]);
      $semi = $semiStatement->get_result()->fetch_assoc();
      $semiStatement->close();
      $column = $option['source_type'] === 'transferred' ? 'quantity_reissued' : 'quantity_issued';
      if ($semi && (int)$semi[$column] < $qty) throw new RuntimeException('The remaining issued or transferred quantity has changed. Reload the item list.');
      $uc = $option['unit_cost'];
      $tot = $qty * $uc;
      $desc = $option['description'];
      $ics = $option['ics_no'];
      $end = trim($item['end_user'] ?? $option['end_user'] ?? '');
      $remarks = trim($item['remarks'] ?? '');
      if ($option['source_type'] === 'issued') {
        $icsStatement = rrspStatement($conn, 'SELECT * FROM ics_items WHERE ics_item_id = ? FOR UPDATE', 'i', [$option['source_id']]);
        $icsItem = $icsStatement->get_result()->fetch_assoc();
        $icsStatement->close();
        if (!$icsItem || (float)$icsItem['quantity'] < $qty) throw new RuntimeException('The remaining ICS quantity has changed. Reload the item list.');
        $before = (float)$icsItem['quantity'];
        $after = $before - $qty;
        rrspStatement($conn, 'UPDATE ics_items SET quantity = ?, total_cost = ? WHERE ics_item_id = ?', 'ddi', [$after, $after * $uc, $option['source_id']])->close();
        rrspStatement($conn, 'INSERT INTO ics_history (ics_id,ics_item_id,stock_number,description,unit,quantity_before,quantity_after,quantity_change,unit_cost,total_cost_before,total_cost_after,reference_type,reference_id,reference_no,reference_details) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', 'iisssdddddsisss',
          [(int)$icsItem['ics_id'], $option['source_id'], $stock, $desc . ' ($Returned)', $icsItem['unit'], $before, $after, -$qty, $uc, $before * $uc, $after * $uc, 'RRSP', $rrsp_id, $rrsp_no, json_encode(['returned_qty' => $qty, 'end_user' => $end, 'remarks' => $remarks])])->close();
      }
      if ($semi) {
        // Transferred returns reduce reissued quantity, without deducting the original ICS again.
        rrspStatement($conn, "UPDATE semi_expendable_property SET $column = $column - ?, quantity_returned = quantity_returned + ?, quantity_balance = quantity_balance + ?, office_officer_returned = ? WHERE id = ?", 'iiisi', [$qty, $qty, $qty, $end, (int)$semi['id']])->close();
        rrspStatement($conn, 'INSERT INTO semi_expendable_history (semi_id,date,ics_rrsp_no,quantity,quantity_issued,quantity_reissued,quantity_returned,quantity_disposed,quantity_balance,office_officer_issued,office_officer_returned,office_officer_reissued,amount,amount_total,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', 'issiiiiiisssdds',
          [(int)$semi['id'], $date_prepared, $rrsp_no, (int)$semi['quantity'], (int)$semi['quantity_issued'] - ($column === 'quantity_issued' ? $qty : 0), (int)$semi['quantity_reissued'] - ($column === 'quantity_reissued' ? $qty : 0), (int)$semi['quantity_returned'] + $qty, (int)$semi['quantity_disposed'], (int)$semi['quantity_balance'] + $qty, $semi['office_officer_issued'], $end, $semi['office_officer_reissued'], (float)$semi['amount'], (float)$semi['amount_total'], $remarks])->close();
      }
      $line = rrspStatement($conn, 'INSERT INTO rrsp_items (rrsp_id,item_description,quantity,ics_no,end_user,item_remarks,unit_cost,total_amount) VALUES (?,?,?,?,?,?,?,?)', 'isisssdd', [$rrsp_id, $desc, $qty, $ics, $end, $remarks, $uc, $tot]);
      $lineId = $line->insert_id;
      $line->close();
      rrspStatement($conn, 'INSERT INTO rrsp_history (rrsp_id,rrsp_item_id,ics_no,item_description,quantity,unit_cost,total_amount,end_user,item_remarks) VALUES (?,?,?,?,?,?,?,?,?)', 'iissiddss', [$rrsp_id, $lineId, $ics, $desc, $qty, $uc, $tot, $end, $remarks])->close();
    }
    $conn->commit();
    echo json_encode(['success' => true, 'rrsp_id' => $rrsp_id]);
  } catch (Throwable $error) {
    if ($transactionStarted) $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $error->getMessage()]);
  }
  exit;
}
$returnOptions = rrspReturnOptions($conn);
$officer_names = [];
$officers_result = $conn->query('SELECT DISTINCT officer_name FROM officers WHERE TRIM(officer_name) <> \'\' ORDER BY officer_name');
if ($officers_result) {
  while ($officer = $officers_result->fetch_assoc()) $officer_names[] = $officer['officer_name'];
  $officers_result->close();
}
$officer_names_json = json_encode($officer_names, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Add RRSP Form</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
  /* Page-Specific Icon */
  .container h1::before {
    content: "\f46d";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #3b82f6;
    margin-right: 12px;
  }
  .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
  .form-grid .form-group { display:flex; flex-direction:column; }
  .form-grid .form-group label {
    font-weight: 600;
    margin-bottom: 6px;
    color: #374151;
  }
  .required-indicator { color: #dc2626; font-weight: 700; }
  .required-fields-note { color: #64748b; font-size: 0.875rem; margin: 0 0 1rem; }
  .form-grid .form-group input,
  .form-grid .form-group select,
  .form-grid .form-group textarea {
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.3s ease;
  }
  .form-grid .form-group input:focus,
  .form-grid .form-group select:focus,
  .form-grid .form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  @media (max-width: 800px) {
     .form-grid { grid-template-columns: 1fr; }
    }
  .actions { display:flex;
    gap:12px;
    align-items:center;
    margin-top:20px;
  }
  /* Use the same selection wrapper and shared table styles as Add PTR. */
  .rrsp-add-page .items-selection { border: 2px solid #e5e7eb; border-radius: 8px; max-height: 400px; overflow: auto; background: #f9fafb; }
  .rrsp-add-page #itemsTable { min-width: 700px; margin-top: 0 !important; }
  .rrsp-add-page #itemsTable .qty-input { width: 60px; text-align: center; padding: 4px !important; border: 1px solid #000 !important; border-radius: 4px; }
  .rrsp-add-page #itemsTable .enduser-input, .rrsp-add-page #itemsTable .remarks-input { width: 160px; }
  .rrsp-add-page .autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: white; border: none; border-radius: 0 0 6px 6px; max-height: 250px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 6px rgba(0, 0, 0, .1); }
  .rrsp-add-page .autocomplete-item { padding: 10px 12px; cursor: pointer; transition: background .2s; }
  .rrsp-add-page .autocomplete-item:hover { background: #f0f4f8; }
  .rrsp-add-page .autocomplete-item.selected { background: #3b82f6; color: white; }
</style>

</head>
<body class="rrsp-page">
<?php include 'sidebar.php'; ?>

<div class="content">
  <div class="form-container rrsp-add-page">
      <p class="required-fields-note"><span class="required-indicator" aria-hidden="true">*</span> indicates a required field.</p>
      <header class="page-header">
        <h1><i class="fa-solid fa-file-invoice"></i>Add RRSP Form</h1>
    </header>

    <div class="section-card">
      <h3><i class="fa-solid fa-info-circle"></i> RRSP Details</h3>
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
          <label>RRSP No.: <span class="required-indicator" aria-hidden="true">*</span></label>
          <input type="text" id="rrsp_no" required>
          <small class="input-hint">Format: Year-Month-Serial (e.g., 2025-11-0001)</small>
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
      <h3><i class="fa-solid fa-boxes-stacked"></i> RRSP Items</h3>
      <div class="search-container">
        <input type="text" id="itemSearch" class="search-input" placeholder="Search issued or transferred items by property number, description, or holder..." onkeyup="filterItems()">
      </div>
      <div class="items-selection">
        <div class="table-viewport">
          <table id="itemsTable" class="table table-bordered" tabindex="-1">
            <thead>
              <tr>
                <th>Item No.</th>
                <th>ICS / Property reference</th>
                <th>Description</th>
                <th>Unit Cost</th>
                <th>Remaining with holder</th>
                <th>Return Qty</th>
                <th>Amount</th>
                <th>End-user</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($returnOptions as $option):
              $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
              $search = implode(' ', [$option['stock_number'], $option['ics_no'], $option['description'], $option['status'], $option['end_user']]);
            ?>
              <tr class="ics-row" data-text="<?= $escape($search) ?>" data-source-type="<?= $escape($option['source_type']) ?>" data-source-id="<?= $option['source_id'] ?>" data-ics-no="<?= $escape($option['ics_no']) ?>" data-unit-cost="<?= $option['unit_cost'] ?>" data-qty-on-hand="<?= $option['quantity'] ?>">
                <td><?= $escape($option['stock_number']) ?></td>
                <td class="icsinfo-cell"><?= $escape($option['ics_no']) ?><br><small><?= $escape($option['date']) ?> &middot; <?= $escape($option['status']) ?></small></td>
                <td class="desc-cell"><?= $escape($option['description']) ?></td>
                <td>&#8369;<?= number_format($option['unit_cost'], 2) ?></td>
                <td class="balance-cell"><?= $option['quantity'] ?></td>
                <td><input class="qty-input" type="number" min="0" max="<?= $option['quantity'] ?>" step="1" value="0" aria-label="Return quantity for <?= $escape($option['description']) ?>"></td>
                <td class="amount-cell">&#8369;0.00</td>
                <td><input class="enduser-input" type="text" value="<?= $escape($option['end_user']) ?>" aria-label="End-user for <?= $escape($option['description']) ?>"></td>
                <td><input class="remarks-input" type="text" aria-label="Remarks for <?= $escape($option['description']) ?>"></td>
              </tr>
            <?php endforeach; ?>
              <tr id="no-items-row"<?= $returnOptions ? ' style="display:none"' : '' ?>><td colspan="9">No issued or transferred items available for return.</td></tr>
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
      <h3><i class="fa-solid fa-pen-nib"></i> Signatories</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Returned By:</label>
          <input type="text" id="returned_by" autocomplete="off" />
          <div id="returned_by_dropdown" class="autocomplete-dropdown"></div>
        </div>
        <div class="form-group">
          <label>Returned Date:</label>
          <input type="date" id="returned_date" />
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Received By:</label>
          <input type="text" id="received_by" autocomplete="off" />
          <div id="received_by_dropdown" class="autocomplete-dropdown"></div>
        </div>
        <div class="form-group">
          <label>Received Date:</label>
          <input type="date" id="received_date" />
        </div>
      </div>
    </div>

    <div class="actions" style="margin-top:18px;">
      <button type="button" class="pill-btn pill-add" onclick="submitRRSP()"><i class="fa-solid fa-save"></i> Submit RRSP</button>
      <button type="button" class="pill-btn pill-view" onclick="window.location.href='rrsp.php'"><i class="fa-solid fa-ban"></i> Cancel</button>
    </div>
  </div>
</div>
<script>
const officerNames = <?= $officer_names_json ?>;
        function setupAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);

            if (!input || !dropdown) return;

            // Show dropdown on focus
            input.addEventListener('focus', function() {
                if (this.value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(this.value, dropdown, input);
                }
            });

            // Prevent click on input from closing dropdown
            input.addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdown.style.display !== 'block') {
                    if (this.value.trim() === '') {
                        showAllSuggestions(dropdown, input);
                    } else {
                        filterSuggestions(this.value, dropdown, input);
                    }
                }
            });

            // Filter on input
            input.addEventListener('input', function() {
                const value = this.value;
                if (value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(value, dropdown, input);
                }
            });

            // Handle keyboard navigation
            input.addEventListener('keydown', function(e) {
                if (dropdown.style.display !== 'block') return;

                const items = Array.from(dropdown.querySelectorAll('.autocomplete-item:not([style*="cursor: default"])'));
                if (items.length === 0) return;

                const selectedItem = dropdown.querySelector('.autocomplete-item.selected');
                let currentIndex = selectedItem ? items.indexOf(selectedItem) : -1;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        currentIndex = (currentIndex + 1) % items.length;
                        highlightItem(items, currentIndex, dropdown);
                        break;

                    case 'ArrowUp':
                        e.preventDefault();
                        currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
                        highlightItem(items, currentIndex, dropdown);
                        break;

                    case 'Enter':
                        e.preventDefault();
                        if (selectedItem) {
                            const text = selectedItem.textContent || selectedItem.innerText;
                            input.value = text;
                            dropdown.style.display = 'none';
                        }
                        break;

                    case 'Tab':
                        const itemToSelect = selectedItem || items[0];
                        if (itemToSelect) {
                            const text = itemToSelect.textContent || itemToSelect.innerText;
                            input.value = text;
                            dropdown.style.display = 'none';
                        }
                        break;

                    case 'Escape':
                        dropdown.style.display = 'none';
                        break;
                }
            });

            // Prevent clicks inside dropdown from closing it
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        // Highlight selected item and scroll into view
        function highlightItem(items, index, dropdown) {
            items.forEach(item => item.classList.remove('selected'));

            if (index >= 0 && index < items.length) {
                items[index].classList.add('selected');

                const item = items[index];
                const dropdownRect = dropdown.getBoundingClientRect();
                const itemRect = item.getBoundingClientRect();

                if (itemRect.bottom > dropdownRect.bottom) {
                    item.scrollIntoView({ block: 'end', behavior: 'smooth' });
                } else if (itemRect.top < dropdownRect.top) {
                    item.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            }
        }

        function showAllSuggestions(dropdown, input) {
            dropdown.innerHTML = '';

            if (officerNames.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-item" style="color: #999; cursor: default;">No officers available</div>';
                dropdown.style.display = 'block';
                return;
            }

            officerNames.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = name;
                item.addEventListener('click', function() {
                    input.value = name;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });

            dropdown.style.display = 'block';
        }

        function filterSuggestions(value, dropdown, input) {
            dropdown.innerHTML = '';
            const searchValue = value.toLowerCase();

            const filtered = officerNames.filter(name =>
                name.toLowerCase().includes(searchValue)
            );

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-item" style="color: #999; cursor: default;">No matches found</div>';
                dropdown.style.display = 'block';
                return;
            }

            filtered.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';

                const index = name.toLowerCase().indexOf(searchValue);
                if (index !== -1) {
                    const before = name.substring(0, index);
                    const match = name.substring(index, index + searchValue.length);
                    const after = name.substring(index + searchValue.length);
                    const highlighted = document.createElement('strong');
                    highlighted.textContent = match;
                    item.append(document.createTextNode(before), highlighted, document.createTextNode(after));
                } else {
                    item.textContent = name;
                }

                item.addEventListener('click', function() {
                    input.value = name;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });

            dropdown.style.display = 'block';
        }

 document.addEventListener('DOMContentLoaded', function() {
   setupAutocomplete('returned_by', 'returned_by_dropdown');
   setupAutocomplete('received_by', 'received_by_dropdown');
 });
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
      let v=parseFloat(qtyInput.value||''); if (isNaN(v)||v<0) v=0; const max=parseFloat(qtyInput.getAttribute('max')||'0'); if (max>0 && v>max) v=max; v=Math.floor(v); qtyInput.value=String(v);
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
      arr.push({ source_type: r.getAttribute('data-source-type'), source_id: Number(r.getAttribute('data-source-id')), description: desc, quantity: qty, ics_no: icsNo, end_user: endUser, remarks: remarks, unit_cost: unitCost });
    }
  });
  return arr;
}
async function submitRRSP(){
  if (!collectItems().length) { alert('Select at least one item to return.'); return; }
  const fd=new FormData(); fd.append('rrsp_no', document.getElementById('rrsp_no').value); fd.append('entity_name', document.getElementById('entity_name').value); fd.append('fund_cluster', document.getElementById('fund_cluster').value); fd.append('date_prepared', document.getElementById('date_prepared').value); fd.append('returned_by', document.getElementById('returned_by').value); fd.append('returned_date', document.getElementById('returned_date').value); fd.append('received_by', document.getElementById('received_by').value); fd.append('received_date', document.getElementById('received_date').value); fd.append('remarks', document.getElementById('remarks').value); fd.append('items_json', JSON.stringify(collectItems()));
  try { const res=await fetch('add_rrsp.php',{method:'POST', body:fd}); const j=await res.json(); if(!j.success){ alert(j.message||'Save failed'); return; } window.location.href='rrsp.php'; } catch(e){ alert('Error: '+e.message); }
}
// Initialize qty handlers for dynamic totals
document.addEventListener('DOMContentLoaded', attachQtyHandlersRRSP);
</script>
</body>
</html>
