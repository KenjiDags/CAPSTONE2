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
  cond VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to ensure tables: ' . $e->getMessage()]);
    exit;
}

// Note: ICS-number helper functions removed because ITR no longer creates ICS.

// Insert header and create ICS within a transaction
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
    $remarks_val = $data['remarks'] ?? null;
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

    // Insert items
    $it = $conn->prepare("INSERT INTO itr_items (itr_id, date_acquired, item_no, ics_info, description, amount, cond) VALUES (?,?,?,?,?,?,?)");
    foreach ($items as $row) {
        $date_acq = toDate($row['date_acquired'] ?? null);
        $item_no = $row['item_no'] ?? null;
        $ics_info = $row['ics_info'] ?? null;
        $description = $row['description'] ?? null;
        $amount = toNumber($row['amount'] ?? 0);
        $cond = $row['condition'] ?? null;
        $it->bind_param('issssds', $itr_id, $date_acq, $item_no, $ics_info, $description, $amount, $cond);
        if (!$it->execute()) {
            throw new Exception($it->error ?: 'Insert item failed');
        }
    }
    $it->close();

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
