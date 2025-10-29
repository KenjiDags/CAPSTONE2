<?php
require 'config.php';
require 'functions.php'; // for columnExists helper if needed
header('Content-Type: application/json');
<<<<<<< HEAD
=======
// Per request: permanently disable any automatic ICS creation from ITR submissions.
// The ITR flow must NOT write to the ICS tables anymore.
$ENABLE_ITR_TO_ICS = false; // kept for clarity; ICS block below removed
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e

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
<<<<<<< HEAD
=======
    remarks TEXT NULL,
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
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
<<<<<<< HEAD
=======
            if (!columnExists($conn, 'itr', 'remarks')) {
                $conn->query("ALTER TABLE itr ADD COLUMN remarks TEXT NULL AFTER reason");
            }
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
        } else {
            // Fallback: probe INFORMATION_SCHEMA manually
            $res = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itr' AND COLUMN_NAME = 'created_ics_no' LIMIT 1");
            $exists = $res && $res->num_rows > 0; if ($res) { $res->close(); }
            if (!$exists) { $conn->query("ALTER TABLE itr ADD COLUMN created_ics_no VARCHAR(64) NULL"); }
<<<<<<< HEAD
=======
            $res2 = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itr' AND COLUMN_NAME = 'remarks' LIMIT 1");
            $exists2 = $res2 && $res2->num_rows > 0; if ($res2) { $res2->close(); }
            if (!$exists2) { $conn->query("ALTER TABLE itr ADD COLUMN remarks TEXT NULL AFTER reason"); }
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
        }
    } catch (Throwable $e) { /* ignore non-fatal */ }
    $conn->query($createItrItems);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to ensure tables: ' . $e->getMessage()]);
    exit;
}

<<<<<<< HEAD
// Helpers for ICS number aligned with add_ics.php: new format "NN-YY"
function generateICSNumber($conn) {
    $yy = date('y');
    // Prefer new-format ICS for this year
    $sql = "SELECT ics_no
            FROM ics
            WHERE ics_no REGEXP '^[0-9]+-[0-9]{2}$' AND RIGHT(ics_no, 2) = ?
            ORDER BY CAST(SUBSTRING_INDEX(ics_no, '-', 1) AS UNSIGNED) DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return '01-' . $yy;
    }
    $stmt->bind_param('s', $yy);
    if (!$stmt->execute()) {
        $stmt->close();
        return '01-' . $yy;
    }
    $res = $stmt->get_result();
    $next = 1;
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $last_serial_str = explode('-', $row['ics_no'])[0];
        $last_serial = (int)$last_serial_str;
        $next = $last_serial + 1;
    }
    $stmt->close();
    $serial_formatted = str_pad((string)$next, 2, '0', STR_PAD_LEFT);
    return $serial_formatted . '-' . $yy;
}

function generateICSNumberSimple($conn) {
    $year = (int)date('Y');
    $yy = date('y');
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ics WHERE YEAR(date_issued) = ?");
    if (!$stmt) { return '01-' . $yy; }
    $stmt->bind_param('i', $year);
    if (!$stmt->execute()) { $stmt->close(); return '01-' . $yy; }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $count = $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;
    $next = $count + 1;
    $serial_formatted = str_pad((string)$next, 2, '0', STR_PAD_LEFT);
    return $serial_formatted . '-' . $yy;
}
=======
// Note: ICS-number helper functions removed because ITR no longer creates ICS.
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e

// Insert header and create ICS within a transaction
try {
    if (method_exists($conn, 'begin_transaction')) { $conn->begin_transaction(); }
    $stmt = $conn->prepare("INSERT INTO itr (
        itr_no, itr_date, entity_name, fund_cluster, from_accountable, to_accountable,
<<<<<<< HEAD
        transfer_type, transfer_other, reason,
=======
        transfer_type, transfer_other, reason, remarks,
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
        approved_name, approved_designation, approved_date,
        released_name, released_designation, released_date,
        received_name, received_designation, received_date,
        created_ics_no
<<<<<<< HEAD
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
=======
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
    // Prepare bound variables to avoid by-reference errors
    $entity_name_val = $data['entity_name'] ?? null;
    $fund_cluster_val = $data['fund_cluster'] ?? null;
    $transfer_type_val = $data['transfer_type'] ?? null;
    $transfer_other_val = $data['transfer_other'] ?? null;
    $reason_val = $data['reason'] ?? null;
<<<<<<< HEAD
=======
    $remarks_val = $data['remarks'] ?? null;
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
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
<<<<<<< HEAD
        'sssssssssssssssssss',
=======
        'ssssssssssssssssssss',
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
        $itr_no,
        $itr_date,
        $entity_name_val,
        $fund_cluster_val,
        $from_accountable,
        $to_accountable,
        $transfer_type_val,
        $transfer_other_val,
        $reason_val,
<<<<<<< HEAD
=======
        $remarks_val,
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
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

<<<<<<< HEAD
    // Create ICS header and items representing this transfer
    // Compute ICS No with retry-on-duplicate once for safety
    $ics_no = generateICSNumber($conn);
    $entity_name = $data['entity_name'] ?? 'TESDA Regional Office';
    $fund_cluster = $data['fund_cluster'] ?? '';
    $date_issued = $itr_date;
    $received_by = $to_accountable; // destination accountable
    $received_by_position = '';
    $received_from = $from_accountable; // source accountable
    $received_from_position = '';

    // Ensure ICS tables exist (defensive)
    $conn->query("CREATE TABLE IF NOT EXISTS ics (
        ics_id INT AUTO_INCREMENT PRIMARY KEY,
        ics_no VARCHAR(50) NOT NULL,
        entity_name VARCHAR(255) NULL,
        fund_cluster VARCHAR(255) NULL,
        date_issued DATE NULL,
        received_by VARCHAR(255) NULL,
        received_by_position VARCHAR(255) NULL,
        received_from VARCHAR(255) NULL,
        received_from_position VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ics_no (ics_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->query("CREATE TABLE IF NOT EXISTS ics_items (
        ics_item_id INT AUTO_INCREMENT PRIMARY KEY,
        ics_id INT NOT NULL,
        stock_number VARCHAR(255) NULL,
        quantity DECIMAL(15,2) DEFAULT 0,
        unit VARCHAR(64) NULL,
        unit_cost DECIMAL(15,2) DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0,
        description TEXT NULL,
        inventory_item_no VARCHAR(255) NULL,
        estimated_useful_life VARCHAR(64) NULL,
        serial_number VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_ics_items_ics FOREIGN KEY (ics_id) REFERENCES ics(ics_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Insert ICS header with one duplicate retry
    $attempts = 0;
    while (true) {
        $hdr = $conn->prepare("INSERT INTO ics (ics_no, entity_name, fund_cluster, date_issued, received_by, received_by_position, received_from, received_from_position) VALUES (?,?,?,?,?,?,?,?)");
        $hdr->bind_param('ssssssss', $ics_no, $entity_name, $fund_cluster, $date_issued, $received_by, $received_by_position, $received_from, $received_from_position);
        if ($hdr->execute()) {
            $ics_id = $hdr->insert_id;
            $hdr->close();
            break;
        } else {
            $err = $hdr->error;
            $hdr->close();
            if ($attempts < 1 && strpos($err, 'Duplicate') !== false) {
                $ics_no = generateICSNumber($conn); // recompute and retry once
                $attempts++;
                continue;
            }
            throw new Exception('Failed to create ICS header: ' . $err);
        }
    }

    // Ensure semi tables have required columns and history exists
    if (function_exists('ensure_semi_expendable_amount_columns')) { ensure_semi_expendable_amount_columns($conn); }
    if (function_exists('ensure_semi_expendable_history')) { ensure_semi_expendable_history($conn); }

    // Insert ICS items and reflect transfer into semi_expendable_property and history (issue 1 per selected row, clamped by balance)
    $insItem = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, description, inventory_item_no, estimated_useful_life, serial_number) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $semiFetch = $conn->prepare("SELECT id, quantity, quantity_issued, quantity_reissued, quantity_disposed, amount, estimated_useful_life FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
    $semiUpdate = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_balance = ?, ics_rrsp_no = ?, office_officer_issued = ?, fund_cluster = ? WHERE id = ?");
    $histIns = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($items as $row) {
        $item_no = $row['item_no'] ?? '';
        $desc = $row['description'] ?? '';
        $unit_cost = toNumber($row['amount'] ?? 0);
        $qty_issue = 1.0; // default 1 per selection
        $unit = '';
        $eul = null; $serial = null;

        // Read semi record to clamp and enrich
        $semi_id = null; $qty_base = null; $qty_issued = null; $qty_reissued = null; $qty_disposed = null; $semi_amount = null; $semi_eul = null;
        if ($item_no && $semiFetch) {
            $semiFetch->bind_param('s', $item_no);
            if ($semiFetch->execute()) {
                $semi_res = $semiFetch->get_result();
                if ($semi_res && $semi_res->num_rows > 0) {
                    $semi = $semi_res->fetch_assoc();
                    $semi_id = (int)$semi['id'];
                    $qty_base = (int)$semi['quantity'];
                    $qty_issued = (int)$semi['quantity_issued'];
                    $qty_reissued = (int)$semi['quantity_reissued'];
                    $qty_disposed = (int)$semi['quantity_disposed'];
                    $semi_amount = (float)($semi['amount'] ?? 0);
                    $semi_eul = $semi['estimated_useful_life'] ?? null;
                    $available = max(0, $qty_base - ($qty_issued + $qty_reissued + $qty_disposed));
                    if ($available <= 0) { continue; }
                    if ($available < $qty_issue) { $qty_issue = (float)$available; }
                    if (!$unit_cost || $unit_cost <= 0) { $unit_cost = $semi_amount; }
                    if ($semi_eul) { $eul = $semi_eul; }
                }
            }
        }

        $total_cost = $qty_issue * $unit_cost;
        // Create ICS item
        $insItem->bind_param('isdsddssss', $ics_id, $item_no, $qty_issue, $unit, $unit_cost, $total_cost, $desc, $item_no, $eul, $serial);
        if (!$insItem->execute()) { throw new Exception('Failed to create ICS item: ' . $insItem->error); }

        // If we found a semi record, update balances and history for the transfer
        if ($semi_id) {
            $new_issued = $qty_issued + (int)$qty_issue;
            $new_balance = max(0, $qty_base - ($new_issued + $qty_reissued + $qty_disposed));
            $semiUpdate->bind_param('iisssi', $new_issued, $new_balance, $ics_no, $received_by, $fund_cluster, $semi_id);
            if (!$semiUpdate->execute()) { throw new Exception('Failed to update semi_expendable_property: ' . $semiUpdate->error); }

            // History snapshot
            $amount_total_hist = round(($semi_amount ?? $unit_cost) * $qty_base, 2);
            $remarks_hist = 'ITR Transfer';
            $histIns->bind_param('issiiiiisdds', $semi_id, $date_issued, $ics_no, $qty_base, $new_issued, $qty_reissued, $qty_disposed, $new_balance, $received_by, $unit_cost, $amount_total_hist, $remarks_hist);
            @ $histIns->execute();
        }
    }
    if ($insItem) $insItem->close();
    if ($semiFetch) $semiFetch->close();
    if ($semiUpdate) $semiUpdate->close();
    if ($histIns) $histIns->close();

    // Update ITR with created ICS number
    $upd = $conn->prepare("UPDATE itr SET created_ics_no = ? WHERE itr_id = ?");
    $upd->bind_param('si', $ics_no, $itr_id);
    if (!$upd->execute()) { throw new Exception('Failed to update ITR with ICS No.: ' . $upd->error); }
    $upd->close();

    if (method_exists($conn, 'commit')) { $conn->commit(); }

    echo json_encode(['success' => true, 'itr_id' => $itr_id, 'ics_no' => $ics_no]);
=======
    // ICS integration removed: no ICS header/items will be created from ITR.

    if (method_exists($conn, 'commit')) { $conn->commit(); }

    $response = ['success' => true, 'itr_id' => $itr_id];
    echo json_encode($response);
>>>>>>> cda79f2e5558555862d2f0fac50fd6938ecc3e8e
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
