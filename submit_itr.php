<?php
require 'config.php';
require 'functions.php'; // for columnExists helper if needed
header('Content-Type: application/json');
// Per request: permanently disable any automatic ICS creation from ITR submissions.
// The ITR flow must NOT write to the ICS tables anymore.
$ENABLE_ITR_TO_ICS = false; // kept for clarity; ICS block below removed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Helpers
function toNumber($s) {
    if ($s === null) return 0;
    if (is_numeric($s)) return $s + 0;
    $s = (string)$s;
    $s = str_replace(['₱', ',', ' '], '', $s);
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    if ($s === '' || $s === '-') return 0;
    return (float)$s;
}
function toDate($s) {
    if (!$s) return null;
    $ts = strtotime($s);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

// Basic validations
$itr_no = trim($data['itr_no'] ?? '');
$itr_date = trim($data['itr_date'] ?? '');
$from_accountable = trim($data['from_accountable'] ?? '');
$to_accountable = trim($data['to_accountable'] ?? '');
$items = $data['items'] ?? [];

if ($itr_no === '' || $itr_date === '' || $from_accountable === '' || $to_accountable === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required fields (ITR No., Date, From, To).']);
    exit;
}
if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select at least one item.']);
    exit;
}

// Create tables if not exist
$createItr = "CREATE TABLE IF NOT EXISTS itr (
  itr_id INT AUTO_INCREMENT PRIMARY KEY,
  itr_no VARCHAR(32) NOT NULL,
  itr_date DATE NOT NULL,
  entity_name VARCHAR(255) NULL,
  fund_cluster VARCHAR(255) NULL,
  from_accountable VARCHAR(255) NULL,
  to_accountable VARCHAR(255) NULL,
  transfer_type VARCHAR(50) NULL,
  transfer_other VARCHAR(255) NULL,
  reason TEXT NULL,
        remarks TEXT NULL,
  approved_name VARCHAR(255) NULL,
  approved_designation VARCHAR(255) NULL,
  approved_date DATE NULL,
  released_name VARCHAR(255) NULL,
  released_designation VARCHAR(255) NULL,
  released_date DATE NULL,
  received_name VARCHAR(255) NULL,
  received_designation VARCHAR(255) NULL,
  received_date DATE NULL,
    created_ics_no VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY itr_no_unique (itr_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$createItrItems = "CREATE TABLE IF NOT EXISTS itr_items (
    itr_item_id INT AUTO_INCREMENT PRIMARY KEY,
    itr_id INT NOT NULL,
    date_acquired DATE NULL,
    item_no VARCHAR(255) NULL,
    ics_info VARCHAR(255) NULL,
    description TEXT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    transfer_qty INT NOT NULL DEFAULT 0,
    ics_id INT NULL,
    ics_item_id INT NULL,
    cond VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_itr_items_ics_item (ics_item_id),
    INDEX idx_itr_items_ics (ics_id),
    CONSTRAINT fk_itr_items_itr FOREIGN KEY (itr_id) REFERENCES itr(itr_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $conn->query($createItr);
    // Backfill created_ics_no if migration already exists without it (portable)
    try {
        if (function_exists('columnExists')) {
            if (!columnExists($conn, 'itr', 'created_ics_no')) {
                $conn->query("ALTER TABLE itr ADD COLUMN created_ics_no VARCHAR(64) NULL");
            }
            if (!columnExists($conn, 'itr', 'remarks')) {
                $conn->query("ALTER TABLE itr ADD COLUMN remarks TEXT NULL AFTER reason");
            }
        } else {
            // Fallback: probe INFORMATION_SCHEMA manually
            $res = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itr' AND COLUMN_NAME = 'created_ics_no' LIMIT 1");
            $exists = $res && $res->num_rows > 0; if ($res) { $res->close(); }
            if (!$exists) { $conn->query("ALTER TABLE itr ADD COLUMN created_ics_no VARCHAR(64) NULL"); }
            $res2 = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itr' AND COLUMN_NAME = 'remarks' LIMIT 1");
            $exists2 = $res2 && $res2->num_rows > 0; if ($res2) { $res2->close(); }
            if (!$exists2) { $conn->query("ALTER TABLE itr ADD COLUMN remarks TEXT NULL AFTER reason"); }
        }
    } catch (Throwable $e) { /* ignore non-fatal */ }
    $conn->query($createItrItems);
    // Ensure new columns exist on older databases
    try {
        $res = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='itr_items' AND COLUMN_NAME='ics_id' LIMIT 1");
        $hasIcsId = $res && $res->num_rows > 0; if ($res) { $res->close(); }
        if (!$hasIcsId) { $conn->query("ALTER TABLE itr_items ADD COLUMN ics_id INT NULL AFTER transfer_qty"); }
        $res2 = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='itr_items' AND COLUMN_NAME='ics_item_id' LIMIT 1");
        $hasIcsItem = $res2 && $res2->num_rows > 0; if ($res2) { $res2->close(); }
        if (!$hasIcsItem) { $conn->query("ALTER TABLE itr_items ADD COLUMN ics_item_id INT NULL AFTER ics_id"); }
    } catch (Throwable $e) { /* ignore */ }
    // Ensure transfer_qty exists on older databases
    try {
        $res = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='itr_items' AND COLUMN_NAME='transfer_qty' LIMIT 1");
        $has = $res && $res->num_rows > 0; if ($res) { $res->close(); }
        if (!$has) { $conn->query("ALTER TABLE itr_items ADD COLUMN transfer_qty INT NOT NULL DEFAULT 0 AFTER amount"); }
    } catch (Throwable $e) { /* ignore */ }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to ensure tables: ' . $e->getMessage()]);
    exit;
}

// Note: ICS-number helper functions removed because ITR no longer creates ICS.
// Note: ICS-number helper functions removed because ITR no longer creates ICS.

// Insert header and then apply ICS/Semi/REGSPI updates within a transaction
try {
    if (method_exists($conn, 'begin_transaction')) { $conn->begin_transaction(); }
    $stmt = $conn->prepare("INSERT INTO itr (
        itr_no, itr_date, entity_name, fund_cluster, from_accountable, to_accountable,
        transfer_type, transfer_other, reason, remarks,
        approved_name, approved_designation, approved_date,
        released_name, released_designation, released_date,
        received_name, received_designation, received_date,
        created_ics_no
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // Prepare bound variables to avoid by-reference errors
    $entity_name_val = $data['entity_name'] ?? null;
    $fund_cluster_val = $data['fund_cluster'] ?? null;
    $transfer_type_val = $data['transfer_type'] ?? null;
    $transfer_other_val = $data['transfer_other'] ?? null;
    $reason_val = $data['reason'] ?? null;
    // Per request: store blank remarks regardless of input
    $remarks_val = '';
    $approved_name_val = $data['approved']['name'] ?? null;
    $approved_designation_val = $data['approved']['designation'] ?? null;
    $approved_date_val = ($data['approved']['date'] ?? null) ? toDate($data['approved']['date']) : null;
    $released_name_val = $data['released']['name'] ?? null;
    $released_designation_val = $data['released']['designation'] ?? null;
    $released_date_val = ($data['released']['date'] ?? null) ? toDate($data['released']['date']) : null;
    $received_name_val = $data['received']['name'] ?? null;
    $received_designation_val = $data['received']['designation'] ?? null;
    $received_date_val = ($data['received']['date'] ?? null) ? toDate($data['received']['date']) : null;
    // Placeholder for created ICS no; will be updated after ICS creation
    $created_ics_no_placeholder = null;

    $stmt->bind_param(
    'ssssssssssssssssssss',
        $itr_no,
        $itr_date,
        $entity_name_val,
        $fund_cluster_val,
        $from_accountable,
        $to_accountable,
        $transfer_type_val,
        $transfer_other_val,
        $reason_val,
        $remarks_val,
        $approved_name_val,
        $approved_designation_val,
        $approved_date_val,
        $released_name_val,
        $released_designation_val,
        $released_date_val,
        $received_name_val,
        $received_designation_val,
        $received_date_val,
        $created_ics_no_placeholder
    );
    $ok = $stmt->execute();
    if (!$ok) { throw new Exception($stmt->error ?: 'Insert failed'); }
    $itr_id = $stmt->insert_id;
    $stmt->close();

    // Insert items (persist transfer_qty for accurate edit prefill)
    $it = $conn->prepare("INSERT INTO itr_items (itr_id, date_acquired, item_no, ics_info, description, amount, transfer_qty, ics_id, ics_item_id, cond) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $insertedItrItemIds = [];
    foreach ($items as $idx => $row) {
        $date_acq = toDate($row['date_acquired'] ?? null);
        $item_no = $row['item_no'] ?? null;
        $ics_info = $row['ics_info'] ?? null;
        $description = $row['description'] ?? null;
        $amount = toNumber($row['amount'] ?? 0);
        $tq = (int)($row['transfer_qty'] ?? 0);
        if ($tq <= 0) {
            $unit_cost = toNumber($row['unit_cost'] ?? 0);
            if ($unit_cost > 0) { $tq = (int)floor($amount / $unit_cost); }
        }
        $cond = $row['condition'] ?? null;
        $ics_id_in = isset($row['ics_id']) ? (int)$row['ics_id'] : null;
        $ics_item_id_in = isset($row['ics_item_id']) ? (int)$row['ics_item_id'] : null;
        $it->bind_param('issssdiiss', $itr_id, $date_acq, $item_no, $ics_info, $description, $amount, $tq, $ics_id_in, $ics_item_id_in, $cond);
        if (!$it->execute()) {
            throw new Exception($it->error ?: 'Insert item failed');
        }
        $insertedItrItemIds[$idx] = $it->insert_id;
    }
    $it->close();

    // Ensure REGSPI tables exist (idempotent)
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS regspi (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  entity_name VARCHAR(255) NOT NULL,\n  fund_cluster VARCHAR(100) NULL,\n  semi_expendable_property VARCHAR(100) NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $conn->query("CREATE TABLE IF NOT EXISTS regspi_entries (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  regspi_id INT NOT NULL,\n  `date` DATE NOT NULL,\n  ics_rrsp_no VARCHAR(100) NOT NULL,\n  property_no VARCHAR(100) NOT NULL,\n  item_description TEXT NOT NULL,\n  useful_life VARCHAR(100) NOT NULL,\n  issued_qty INT NOT NULL DEFAULT 0,\n  issued_office VARCHAR(255) NULL,\n  returned_qty INT NOT NULL DEFAULT 0,\n  returned_office VARCHAR(255) NULL,\n  reissued_qty INT NOT NULL DEFAULT 0,\n  reissued_office VARCHAR(255) NULL,\n  disposed_qty1 INT NOT NULL DEFAULT 0,\n  disposed_qty2 INT NOT NULL DEFAULT 0,\n  balance_qty INT NOT NULL DEFAULT 0,\n  amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,\n  remarks TEXT NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n  INDEX idx_regspi_id (regspi_id),\n  INDEX idx_date (`date`),\n  INDEX idx_ics_rrsp_no (ics_rrsp_no),\n  INDEX idx_property_no (property_no),\n  CONSTRAINT fk_regspi_entries_header FOREIGN KEY (regspi_id)\n    REFERENCES regspi(id) ON DELETE CASCADE ON UPDATE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) { /* ignore; next inserts may fail and be caught */ }

    // Ensure history tables exist
    if (function_exists('ensure_semi_expendable_history')) { ensure_semi_expendable_history($conn); }
    if (function_exists('ensure_ics_history')) { ensure_ics_history($conn); }
    if (function_exists('ensure_itr_history')) { ensure_itr_history($conn); }

    $icsHistoryInsertSql = "INSERT INTO ics_history (ics_id, ics_item_id, stock_number, description, unit, quantity_before, quantity_after, quantity_change, unit_cost, total_cost_before, total_cost_after, reference_type, reference_id, reference_no, reference_details) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    // Apply ICS quantity deduction, SEMI reissue increment, and REGSPI entry per item
    foreach ($items as $idx => $row) {
        $transfer_qty = (int)($row['transfer_qty'] ?? 0);
        if ($transfer_qty <= 0) { continue; }
        $unit_cost = toNumber($row['unit_cost'] ?? 0);
        $stock_no = $row['stock_number'] ?? ($row['item_no'] ?? null);
        $ics_item_id = isset($row['ics_item_id']) ? (int)$row['ics_item_id'] : 0;
        $ics_id_in = isset($row['ics_id']) ? (int)$row['ics_id'] : 0;
        if (!$stock_no) { continue; }

        // 1) Deduct from ICS item quantity
        $current_qty = null;
        $current_uc = null;
        $current_total_cost = null;
        $current_unit = null;
        $current_description = null;
        $current_stock_number = $stock_no;
        $current_ics_id_val = ($ics_id_in > 0) ? $ics_id_in : null;
        $target_ics_item_id = null;
        $icsRow = null;

        if ($ics_item_id > 0) {
            $q = $conn->prepare("SELECT ics_item_id, ics_id, stock_number, quantity, unit_cost, total_cost, unit, description FROM ics_items WHERE ics_item_id = ? LIMIT 1");
            $q->bind_param('i', $ics_item_id);
            $q->execute();
            $res = $q->get_result();
            if ($res && $res->num_rows > 0) {
                $icsRow = $res->fetch_assoc();
            }
            if ($res) { $res->free(); }
            $q->close();
        }
        if (!$icsRow && $ics_id_in > 0) {
            $q = $conn->prepare("SELECT ics_item_id, ics_id, stock_number, quantity, unit_cost, total_cost, unit, description FROM ics_items WHERE ics_id = ? AND stock_number = ? ORDER BY ics_item_id DESC LIMIT 1");
            $q->bind_param('is', $ics_id_in, $stock_no);
            $q->execute();
            $res = $q->get_result();
            if ($res && $res->num_rows > 0) {
                $icsRow = $res->fetch_assoc();
            }
            if ($res) { $res->free(); }
            $q->close();
        }

        if ($icsRow) {
            $target_ics_item_id = (int)$icsRow['ics_item_id'];
            $current_qty = isset($icsRow['quantity']) ? (float)$icsRow['quantity'] : null;
            $current_uc = isset($icsRow['unit_cost']) ? (float)$icsRow['unit_cost'] : null;
            $current_total_cost = isset($icsRow['total_cost']) ? (float)$icsRow['total_cost'] : null;
            $current_unit = $icsRow['unit'] ?? null;
            $current_description = $icsRow['description'] ?? null;
            $current_stock_number = $icsRow['stock_number'] ?? $current_stock_number;
            $current_ics_id_val = isset($icsRow['ics_id']) ? (int)$icsRow['ics_id'] : $current_ics_id_val;
        }

        if ($target_ics_item_id !== null && $current_qty !== null) {
            $unitCostBase = ($unit_cost > 0) ? $unit_cost : ($current_uc ?? 0);
            $effective = min((float)$transfer_qty, max(0.0, (float)$current_qty));
            $new_qty = max(0.0, (float)$current_qty - $effective);
            $new_total = $unitCostBase * $new_qty;
            $u = $conn->prepare("UPDATE ics_items SET quantity = ?, total_cost = ? WHERE ics_item_id = ?");
            $u->bind_param('ddi', $new_qty, $new_total, $target_ics_item_id);
            if (!$u->execute()) {
                $err = $u->error;
                $u->close();
                throw new Exception('Failed to update ICS item: ' . $err);
            }
            $u->close();

            if (!empty($icsHistoryInsertSql)) {
                $historyStmt = $conn->prepare($icsHistoryInsertSql);
                if (!$historyStmt) {
                    throw new Exception('Failed to prepare ICS history insert: ' . $conn->error);
                }
                    $quantity_before = (float)$current_qty;
                    $quantity_after = (float)$new_qty;
                    $quantity_change = $quantity_after - $quantity_before;
                    $unit_cost_history = ($unitCostBase > 0) ? $unitCostBase : 0.0;
                    $total_cost_before = ($current_total_cost !== null) ? (float)$current_total_cost : ($quantity_before * $unit_cost_history);
                    $total_cost_after = (float)$new_total;
                    $reference_type = 'ITR';
                    $reference_id = (int)$itr_id;
                    $reference_no = $itr_no;
                    $detailParts = [];
                    $effectiveText = (fmod($effective, 1.0) == 0.0) ? (string)(int)$effective : rtrim(rtrim(number_format($effective, 4, '.', ''), '0'), '.');
                    $detailParts[] = 'Transfer Qty: ' . $effectiveText;
                    if (!empty($transfer_type_val)) { $detailParts[] = 'Type: ' . $transfer_type_val; }
                    if (!empty($transfer_other_val) && (empty($transfer_type_val) || strcasecmp($transfer_type_val, 'Others') === 0)) {
                        $detailParts[] = 'Other: ' . $transfer_other_val;
                    }
                    if (!empty($to_accountable)) { $detailParts[] = 'To: ' . $to_accountable; }
                    $reference_details = implode(' | ', array_filter($detailParts));

                    $histIcsId = ($current_ics_id_val !== null) ? (int)$current_ics_id_val : 0;
                    $histStock = (string)($current_stock_number ?? '');
                    $histDesc = (string)($current_description ?? '');
                    $histUnit = (string)($current_unit ?? '');

                    $historyBindTypes = 'iisss' . str_repeat('d', 6) . 'siss';
                    $historyStmt->bind_param(
                        $historyBindTypes,
                        $histIcsId,
                        $target_ics_item_id,
                        $histStock,
                        $histDesc,
                        $histUnit,
                        $quantity_before,
                        $quantity_after,
                        $quantity_change,
                        $unit_cost_history,
                        $total_cost_before,
                        $total_cost_after,
                        $reference_type,
                        $reference_id,
                        $reference_no,
                        $reference_details
                    );
                if (!$historyStmt->execute()) {
                    $err = $historyStmt->error;
                    $historyStmt->close();
                    throw new Exception('Failed to record ICS history: ' . $err);
                }
                $historyStmt->close();
            }

            // Use effective qty for downstream updates
            $transfer_qty = (int)round($effective);

            // Record ITR history per item (actual executed transfer)
            if (function_exists('ensure_itr_history')) { ensure_itr_history($conn); }
            $itr_item_id_link = isset($insertedItrItemIds[$idx]) ? (int)$insertedItrItemIds[$idx] : null;
            $histStmt = $conn->prepare("INSERT INTO itr_history (itr_id, itr_item_id, ics_id, ics_item_id, item_no, stock_number, description, unit, transfer_qty, unit_cost, amount, from_accountable, to_accountable, transfer_type, transfer_other, reference_no, reference_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $descHist = (string)($current_description ?? ($row['description'] ?? ''));
            $unitHist = (string)($current_unit ?? '');
            $itemNoHist = (string)($row['item_no'] ?? '');
            $amountHist = (float)$unitCostBase * (int)$transfer_qty;
            $refDate = toDate($itr_date);
            $histStmt->bind_param(
                'iiiiiissddsssssss',
                $itr_id,
                $itr_item_id_link,
                $current_ics_id_val,
                $target_ics_item_id,
                $itemNoHist,
                $current_stock_number,
                $descHist,
                $unitHist,
                $transfer_qty,
                $unitCostBase,
                $amountHist,
                $from_accountable,
                $to_accountable,
                $transfer_type_val,
                $transfer_other_val,
                $itr_no,
                $refDate
            );
            @$histStmt->execute();
            $histStmt->close();
        }

        // 2) Update Semi-Expendable: increment reissued and recalc balance
    $semi = $conn->prepare("SELECT id, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, amount, estimated_useful_life, item_description FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
        $semi->bind_param('s', $stock_no);
        $semi->execute();
        $semiRes = $semi->get_result();
        $semiRow = $semiRes && $semiRes->num_rows > 0 ? $semiRes->fetch_assoc() : null;
        $semi->close();
        $updatedBalance = null;
        if ($semiRow) {
            $semi_id = (int)$semiRow['id'];
            $qty = (int)$semiRow['quantity'];
            // Move quantity from issued to reissued so remaining ICS is reflected correctly in semi
            $issued = max(0, (int)$semiRow['quantity_issued'] - (int)$transfer_qty);
            $reissued = (int)$semiRow['quantity_reissued'] + (int)$transfer_qty;
            $disposed = (int)$semiRow['quantity_disposed'];
            $balance = max(0, $qty - ($issued + $reissued + $disposed));
            $updatedBalance = $balance;
            $u = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_reissued = ?, quantity_balance = ? WHERE id = ?");
            $u->bind_param('iiii', $issued, $reissued, $balance, $semi_id);
            if (!$u->execute()) { $u->close(); throw new Exception('Failed to update semi-expendable reissue: ' . $u->error); }
            $u->close();

            // History snapshot for reissue
            try {
                $h = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_reissued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $unit_amount = ($semiRow['amount'] !== null) ? (float)$semiRow['amount'] : ($unit_cost ?: 0);
                $amount_total = round($unit_amount * $qty, 2);
                $remarks = '';
                $h->bind_param('issiiiiisdds', $semi_id, $itr_date, $itr_no, $qty, $issued, $reissued, $disposed, $balance, $to_accountable, $unit_amount, $amount_total, $remarks);
                @$h->execute();
                $h->close();
            } catch (Throwable $e) { /* ignore non-fatal */ }
        }

        // 3) Insert REGSPI entry
        try {
            // Find or create header
            $regspi_id = null;
            $find = $conn->prepare("SELECT id FROM regspi WHERE entity_name = ? AND (fund_cluster <=> ?) LIMIT 1");
            $find->bind_param('ss', $entity_name_val, $fund_cluster_val);
            $find->execute();
            $fr = $find->get_result();
            if ($fr && $fr->num_rows > 0) { $regspi_id = (int)$fr->fetch_assoc()['id']; }
            $find->close();
            if (!$regspi_id) {
                $ins = $conn->prepare("INSERT INTO regspi (entity_name, fund_cluster, semi_expendable_property) VALUES (?, ?, ?)");
                $sep = null; // unknown category here
                $ins->bind_param('sss', $entity_name_val, $fund_cluster_val, $sep);
                if ($ins->execute()) { $regspi_id = $ins->insert_id; }
                $ins->close();
            }
            if ($regspi_id) {
                $desc = $semiRow['item_description'] ?? ($row['description'] ?? '');
                $useful = $semiRow['estimated_useful_life'] ?? '';
                $reissued_office = $to_accountable ?: null;
                $issued_office = $from_accountable ?: null;
                $amt = ($unit_cost ?: ($semiRow['amount'] ?? 0)) * (int)$transfer_qty;
                $balance_qty = $updatedBalance ?? 0;
                $insE = $conn->prepare("INSERT INTO regspi_entries (regspi_id, `date`, ics_rrsp_no, property_no, item_description, useful_life, issued_qty, issued_office, returned_qty, returned_office, reissued_qty, reissued_office, disposed_qty1, disposed_qty2, balance_qty, amount, remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $disposed1 = 0; $disposed2 = 0; $issuedQty = 0; $returnedQty = 0; $returnedOffice = null; $remarks = '';
                $insE->bind_param('isssssisisisiiids', $regspi_id, $itr_date, $itr_no, $stock_no, $desc, $useful, $issuedQty, $issued_office, $returnedQty, $returnedOffice, $transfer_qty, $reissued_office, $disposed1, $disposed2, $balance_qty, $amt, $remarks);
                @$insE->execute();
                $insE->close();
            }
        } catch (Throwable $e) { /* ignore non-fatal */ }
    }

    // ICS integration removed: no ICS header/items will be created from ITR.

    if (method_exists($conn, 'commit')) { $conn->commit(); }

    $response = ['success' => true, 'itr_id' => $itr_id];
    echo json_encode($response);
} catch (Throwable $e) {
    if (method_exists($conn, 'rollback')) { $conn->rollback(); }
    http_response_code(500);
    $msg = $e->getMessage();
    if (strpos($msg, 'itr_no_unique') !== false || strpos($msg, 'for key') !== false && strpos($msg, 'itr_no_unique') !== false) {
        $msg = 'ITR No. already exists. Please adjust the serial.';
        http_response_code(409);
    }
    echo json_encode(['success' => false, 'message' => $msg]);
}
