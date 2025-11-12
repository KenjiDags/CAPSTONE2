<?php
require 'config.php';
require 'functions.php';
header('Content-Type: application/json');

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

function toNumber($s){ if($s===null) return 0; if(is_numeric($s)) return $s+0; $s=(string)$s; $s=str_replace(['₱',',',' '],'',$s); $s=preg_replace('/[^0-9.\-]/','',$s); if($s===''||$s==='-') return 0; return (float)$s; }
function toDate($s){ if(!$s) return null; $ts=strtotime($s); if($ts===false) return null; return date('Y-m-d',$ts); }

$itr_id = (int)($data['itr_id'] ?? 0);
$itr_no = trim($data['itr_no'] ?? '');
$itr_date = trim($data['itr_date'] ?? '');
$from_accountable = trim($data['from_accountable'] ?? '');
$to_accountable = trim($data['to_accountable'] ?? '');
if ($itr_id<=0 || $itr_no==='' || $itr_date==='' || $from_accountable==='' || $to_accountable==='') {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Missing required fields.']);
    exit;
}

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS itr (itr_id INT AUTO_INCREMENT PRIMARY KEY, itr_no VARCHAR(32) NOT NULL, itr_date DATE NOT NULL, entity_name VARCHAR(255), fund_cluster VARCHAR(255), from_accountable VARCHAR(255), to_accountable VARCHAR(255), transfer_type VARCHAR(50), transfer_other VARCHAR(255), reason TEXT, remarks TEXT NULL, approved_name VARCHAR(255), approved_designation VARCHAR(255), approved_date DATE, released_name VARCHAR(255), released_designation VARCHAR(255), released_date DATE, received_name VARCHAR(255), received_designation VARCHAR(255), received_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY itr_no_unique (itr_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS itr_items (itr_item_id INT AUTO_INCREMENT PRIMARY KEY, itr_id INT NOT NULL, date_acquired DATE NULL, item_no VARCHAR(255) NULL, ics_info VARCHAR(255) NULL, description TEXT NULL, amount DECIMAL(15,2) DEFAULT 0, transfer_qty INT NOT NULL DEFAULT 0, ics_id INT NULL, ics_item_id INT NULL, cond VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_itr_items_ics_item (ics_item_id), INDEX idx_itr_items_ics (ics_id), CONSTRAINT fk_itr_items_itr FOREIGN KEY (itr_id) REFERENCES itr(itr_id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
// Ensure transfer_qty exists for older installations
try { $res=$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='itr_items' AND COLUMN_NAME='transfer_qty' LIMIT 1"); $has=$res && $res->num_rows>0; if($res){$res->close();} if(!$has){ $conn->query("ALTER TABLE itr_items ADD COLUMN transfer_qty INT NOT NULL DEFAULT 0 AFTER amount"); } } catch (Throwable $e) { /* ignore */ }
// Ensure ics linkage columns exist
try {
    $r=$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='itr_items' AND COLUMN_NAME='ics_id' LIMIT 1"); $hasI=$r && $r->num_rows>0; if($r){$r->close();} if(!$hasI){ $conn->query("ALTER TABLE itr_items ADD COLUMN ics_id INT NULL AFTER transfer_qty"); }
    $r2=$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='itr_items' AND COLUMN_NAME='ics_item_id' LIMIT 1"); $hasII=$r2 && $r2->num_rows>0; if($r2){$r2->close();} if(!$hasII){ $conn->query("ALTER TABLE itr_items ADD COLUMN ics_item_id INT NULL AFTER ics_id"); }
} catch (Throwable $e) { /* ignore */ }

try {
    if (method_exists($conn,'begin_transaction')) { $conn->begin_transaction(); }

    // Check unique itr_no conflict excluding this record
    $chk = $conn->prepare("SELECT itr_id FROM itr WHERE itr_no = ? AND itr_id <> ? LIMIT 1");
    $chk->bind_param('si', $itr_no, $itr_id);
    $chk->execute();
    $cr = $chk->get_result();
    if ($cr && $cr->num_rows>0) { if (method_exists($conn,'rollback')) $conn->rollback(); http_response_code(409); echo json_encode(['success'=>false,'message'=>'ITR No. already exists.']); exit; }
    $chk->close();

    // Update header
    $stmt = $conn->prepare("UPDATE itr SET itr_no=?, itr_date=?, entity_name=?, fund_cluster=?, from_accountable=?, to_accountable=?, transfer_type=?, transfer_other=?, reason=?, remarks=?, approved_name=?, approved_designation=?, approved_date=?, released_name=?, released_designation=?, released_date=?, received_name=?, received_designation=?, received_date=? WHERE itr_id=?");
    $entity_name_val = $data['entity_name'] ?? null;
    $fund_cluster_val = $data['fund_cluster'] ?? null;
    $transfer_type_val = $data['transfer_type'] ?? null;
    $transfer_other_val = $data['transfer_other'] ?? null;
    $reason_val = $data['reason'] ?? null;
    $remarks_val = $data['remarks'] ?? '';
    $approved_name_val = $data['approved']['name'] ?? null;
    $approved_designation_val = $data['approved']['designation'] ?? null;
    $approved_date_val = ($data['approved']['date'] ?? null) ? toDate($data['approved']['date']) : null;
    $released_name_val = $data['released']['name'] ?? null;
    $released_designation_val = $data['released']['designation'] ?? null;
    $released_date_val = ($data['released']['date'] ?? null) ? toDate($data['released']['date']) : null;
    $received_name_val = $data['received']['name'] ?? null;
    $received_designation_val = $data['received']['designation'] ?? null;
    $received_date_val = ($data['received']['date'] ?? null) ? toDate($data['received']['date']) : null;
    $stmt->bind_param(
        'sssssssssssssssssssi',
        $itr_no, $itr_date, $entity_name_val, $fund_cluster_val, $from_accountable, $to_accountable,
        $transfer_type_val, $transfer_other_val, $reason_val, $remarks_val,
        $approved_name_val, $approved_designation_val, $approved_date_val,
        $released_name_val, $released_designation_val, $released_date_val,
        $received_name_val, $received_designation_val, $received_date_val,
        $itr_id
    );
    if (!$stmt->execute()) { throw new Exception('Failed to update ITR: '.$stmt->error); }
    $stmt->close();

    // Build helpers for numeric parsing and unit amount lookup
    $toNum = function($s){ if($s===null) return 0.0; if(is_numeric($s)) return (float)$s; $s=(string)$s; $s=str_replace(['₱',',',' '],'',$s); $s=preg_replace('/[^0-9.\-]/','',$s); if($s===''||$s==='-') return 0.0; return (float)$s; };
    $getUnitAmount = function($stockNo) use ($conn){
        if(!$stockNo) return 0.0;
        // Prefer Semi unit amount if present
        $ua = 0.0;
        $q = $conn->prepare("SELECT amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
        if ($q) {
            $q->bind_param('s', $stockNo);
            $q->execute();
            $res = $q->get_result();
            if ($res && $res->num_rows>0) { $row = $res->fetch_assoc(); $ua = (float)($row['amount'] ?? 0); }
            $q->close();
        }
        if ($ua>0) return $ua;
        // Fallback: ICS latest unit cost or derived
        $q2 = $conn->prepare("SELECT unit_cost, total_cost, quantity FROM ics_items WHERE stock_number = ? ORDER BY ics_item_id DESC LIMIT 1");
        if ($q2) {
            $q2->bind_param('s', $stockNo);
            $q2->execute();
            $res2 = $q2->get_result();
            if ($res2 && $res2->num_rows>0) {
                $r = $res2->fetch_assoc();
                $uc = (float)($r['unit_cost'] ?? 0);
                if ($uc <= 0) {
                    $tot = (float)($r['total_cost'] ?? 0);
                    $qty = max(1.0, (float)($r['quantity'] ?? 1));
                    $uc = $tot / $qty;
                }
                $ua = $uc;
            }
            $q2->close();
        }
        return $ua>0 ? $ua : 0.0;
    };

    // Resolve an ITR item_no to a Semi property number (stock_number) using Semi/ICS lookups
    $resolveStockNo = function($itemNo) use ($conn){
        $key = trim((string)$itemNo);
        if ($key === '') return $key;
        // If it already exists as a Semi property number, accept
        if ($q = $conn->prepare("SELECT 1 FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1")) {
            $q->bind_param('s', $key);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->num_rows > 0) { $q->close(); return $key; }
            $q->close();
        }
        // Try ICS by inventory_item_no -> stock_number
        if ($q2 = $conn->prepare("SELECT stock_number FROM ics_items WHERE inventory_item_no = ? ORDER BY ics_item_id DESC LIMIT 1")) {
            $q2->bind_param('s', $key);
            $q2->execute();
            $r2 = $q2->get_result();
            if ($r2 && $r2->num_rows > 0) { $row = $r2->fetch_assoc(); $q2->close(); return (string)($row['stock_number'] ?? $key); }
            $q2->close();
        }
        // Try ICS where stock_number equals key
        if ($q3 = $conn->prepare("SELECT 1 FROM ics_items WHERE stock_number = ? LIMIT 1")) {
            $q3->bind_param('s', $key);
            $q3->execute();
            $r3 = $q3->get_result();
            if ($r3 && $r3->num_rows > 0) { $q3->close(); return $key; }
            $q3->close();
        }
        // Fallback: return as-is
        return $key;
    };

    // 0) Load previous items for ICS delta and compute previous implied quantities per stock number for Semi delta
    $prevRows = [];
    $pstmt = $conn->prepare("SELECT itr_item_id, item_no, amount, transfer_qty, ics_id, ics_item_id FROM itr_items WHERE itr_id = ?");
    if ($pstmt) {
        $pstmt->bind_param('i', $itr_id);
        $pstmt->execute();
        $pr = $pstmt->get_result();
        while ($pr && ($row = $pr->fetch_assoc())) { $prevRows[] = $row; }
        $pstmt->close();
    }
    // 1) Compute previous implied quantities per stock number from existing ITR items
    $prevQty = [];
    foreach ($prevRows as $row) {
        $stockRaw = trim((string)($row['item_no'] ?? ''));
        $stock = $resolveStockNo($stockRaw);
        if ($stock==='') continue;
        $unitAmt = $getUnitAmount($stock);
        $amt = $toNum($row['amount'] ?? 0);
        $tq = isset($row['transfer_qty']) ? (int)$row['transfer_qty'] : 0;
        if ($tq <= 0) { $tq = ($unitAmt>0) ? (int)floor($amt / $unitAmt) : 0; }
        if (!isset($prevQty[$stock])) $prevQty[$stock] = 0;
        $prevQty[$stock] += max(0,$tq);
    }

    // 1b) Revert previous ICS deductions per row (add back quantities)
    foreach ($prevRows as $row) {
        $stockNo = trim((string)($row['item_no'] ?? ''));
        $unitAmtTmp = $getUnitAmount($stockNo);
        $amtTmp = $toNum($row['amount'] ?? 0);
        $tq = (int)($row['transfer_qty'] ?? 0);
        if ($tq <= 0) { $tq = ($unitAmtTmp>0) ? (int)floor($amtTmp / $unitAmtTmp) : 0; }
        if ($tq <= 0) continue;
        $ics_item = (int)($row['ics_item_id'] ?? 0);
        $ics_id_row = (int)($row['ics_id'] ?? 0);
        $target_ics_item_id = null; $current_qty = null; $current_uc = null;
        if ($ics_item > 0) {
            if ($q = $conn->prepare("SELECT ics_item_id, quantity, unit_cost FROM ics_items WHERE ics_item_id = ? LIMIT 1")) {
                $q->bind_param('i', $ics_item);
                $q->execute(); $res = $q->get_result();
                if ($res && $res->num_rows>0) { $r=$res->fetch_assoc(); $target_ics_item_id=(int)$r['ics_item_id']; $current_qty=(float)$r['quantity']; $current_uc=(float)$r['unit_cost']; }
                $q->close();
            }
        }
        if ($target_ics_item_id===null && $ics_id_row>0 && $stockNo!=='') {
            if ($q2 = $conn->prepare("SELECT ics_item_id, quantity, unit_cost FROM ics_items WHERE ics_id = ? AND stock_number = ? ORDER BY ics_item_id DESC LIMIT 1")) {
                $q2->bind_param('is', $ics_id_row, $stockNo);
                $q2->execute(); $res2=$q2->get_result();
                if ($res2 && $res2->num_rows>0) { $r2=$res2->fetch_assoc(); $target_ics_item_id=(int)$r2['ics_item_id']; $current_qty=(float)$r2['quantity']; $current_uc=(float)$r2['unit_cost']; }
                $q2->close();
            }
        }
        if ($target_ics_item_id===null && $stockNo!=='') {
            if ($q3 = $conn->prepare("SELECT ics_item_id, quantity, unit_cost FROM ics_items WHERE stock_number = ? ORDER BY ics_item_id DESC LIMIT 1")) {
                $q3->bind_param('s', $stockNo);
                $q3->execute(); $res3=$q3->get_result();
                if ($res3 && $res3->num_rows>0) { $r3=$res3->fetch_assoc(); $target_ics_item_id=(int)$r3['ics_item_id']; $current_qty=(float)$r3['quantity']; $current_uc=(float)$r3['unit_cost']; }
                $q3->close();
            }
        }
        if ($target_ics_item_id!==null && $current_qty!==null) {
            $uc = (float)$current_uc;
            $new_qty = (float)$current_qty + (float)$tq; if ($new_qty < 0) $new_qty = 0.0;
            $new_total = $uc * $new_qty;
            if ($u = $conn->prepare("UPDATE ics_items SET quantity = ?, total_cost = ? WHERE ics_item_id = ?")) {
                $u->bind_param('ddi', $new_qty, $new_total, $target_ics_item_id);
                @$u->execute();
                $u->close();
            }
        }
    }

    // 2) Compute new quantities per stock number from payload
    $newQty = [];
    $rows = $data['items'] ?? [];
    foreach ($rows as $row) {
        $stock = trim((string)($row['stock_number'] ?? $row['item_no'] ?? ''));
        if ($stock==='') continue;
        $tq = isset($row['transfer_qty']) ? (int)$row['transfer_qty'] : 0;
        if ($tq <= 0) {
            // derive from amount and unit cost if not present
            $unitAmt = isset($row['unit_cost']) ? (float)$row['unit_cost'] : 0.0;
            if ($unitAmt <= 0) { $unitAmt = $getUnitAmount($stock); }
            $amt = $toNum($row['amount'] ?? 0);
            $tq = ($unitAmt>0) ? (int)floor($amt / $unitAmt) : 0;
        }
        if (!isset($newQty[$stock])) $newQty[$stock] = 0;
        $newQty[$stock] += max(0, (int)$tq);
    }

    // 3) Delta per stock and update Semi table accordingly
    ensure_semi_expendable_history($conn);
    $allStocks = array_unique(array_merge(array_keys($prevQty), array_keys($newQty)));
    $semiUpdatesApplied = 0;
    foreach ($allStocks as $stock) {
        $old = (int)($prevQty[$stock] ?? 0);
        $new = (int)($newQty[$stock] ?? 0);
        $delta = $new - $old;
        if ($delta === 0) { continue; }
        // Load semi row
        $semi = $conn->prepare("SELECT id, item_description, quantity, quantity_issued, quantity_reissued, quantity_disposed, amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
        if (!$semi) { continue; }
        $semi->bind_param('s', $stock);
        $semi->execute();
        $sr = $semi->get_result();
        $semiRow = ($sr && $sr->num_rows>0) ? $sr->fetch_assoc() : null;
        $semi->close();
        if (!$semiRow) { continue; }
        $semi_id = (int)$semiRow['id'];
        $qty = (int)($semiRow['quantity'] ?? 0);
        $issued = (int)($semiRow['quantity_issued'] ?? 0);
        $reissued = (int)($semiRow['quantity_reissued'] ?? 0);
        $disposed = (int)($semiRow['quantity_disposed'] ?? 0);
        $unitAmt = (float)($semiRow['amount'] ?? 0);
        if ($unitAmt <= 0) { $unitAmt = $getUnitAmount($stock); }

        // Apply delta: move between issued and reissued
        $newReissued = $reissued + $delta; if ($newReissued < 0) $newReissued = 0; $maxReissued = max(0, $qty - $disposed); if ($newReissued > $maxReissued) $newReissued = $maxReissued;
        $newIssued = $issued - $delta; if ($newIssued < 0) $newIssued = 0; $avail = max(0, $qty - ($newReissued + $disposed)); if ($newIssued > $avail) $newIssued = $avail;
        $balance = max(0, $qty - ($newIssued + $newReissued + $disposed));

        $u = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_reissued = ?, quantity_balance = ? WHERE id = ?");
        if ($u) {
            $u->bind_param('iiii', $newIssued, $newReissued, $balance, $semi_id);
            if (!$u->execute()) { $u->close(); throw new Exception('Failed to update Semi record: ' . $u->error); }
            $u->close();
            $semiUpdatesApplied++;
        }

        // History snapshot after edit adjustment
        try {
            $h = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_reissued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($h) {
                $amount_total = round(($unitAmt ?: 0) * $qty, 2);
                // Removed explicit remark per request; leave blank to avoid auto-tagging history entries
                $remarks = '';
                $h->bind_param('issiiiiisdds', $semi_id, $itr_date, $itr_no, $qty, $newIssued, $newReissued, $disposed, $balance, $to_accountable, $unitAmt, $amount_total, $remarks);
                @$h->execute();
                $h->close();
            }
        } catch (Throwable $e) { /* ignore non-fatal */ }
    }

    // Fallback: if no deltas were applied but items were submitted, record officer change without altering quantities
    if ($semiUpdatesApplied === 0 && !empty($newQty)) {
        foreach ($newQty as $stock => $_qtyIgnored) {
            $semi = $conn->prepare("SELECT id, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, amount, office_officer_reissued FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
            if (!$semi) continue;
            $semi->bind_param('s', $stock);
            $semi->execute();
            $sr = $semi->get_result();
            $semiRow = ($sr && $sr->num_rows>0) ? $sr->fetch_assoc() : null;
            $semi->close();
            if (!$semiRow) continue;
            $semi_id = (int)$semiRow['id'];
            $qty = (int)($semiRow['quantity'] ?? 0);
            $issued = (int)($semiRow['quantity_issued'] ?? 0);
            $reissued = (int)($semiRow['quantity_reissued'] ?? 0);
            $disposed = (int)($semiRow['quantity_disposed'] ?? 0);
            $balance = (int)($semiRow['quantity_balance'] ?? max(0, $qty - ($issued + $reissued + $disposed)));
            $unitAmt = (float)($semiRow['amount'] ?? 0);
            if ($unitAmt <= 0) { $unitAmt = $getUnitAmount($stock); }
            $currentOfficer = (string)($semiRow['office_officer_reissued'] ?? '');

            // Update officer on the property record if different
            if ($currentOfficer !== $to_accountable) {
                if ($up = $conn->prepare("UPDATE semi_expendable_property SET office_officer_reissued = ?, ics_rrsp_no = ? WHERE id = ?")) {
                    $up->bind_param('ssi', $to_accountable, $itr_no, $semi_id);
                    @$up->execute();
                    $up->close();
                }
            }

            // Always append a history snapshot to reflect officer change for this ITR
            try {
                if ($h = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_reissued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                    $amount_total = round(($unitAmt ?: 0) * $qty, 2);
                    $remarks = '';
                    $h->bind_param('issiiiiisdds', $semi_id, $itr_date, $itr_no, $qty, $issued, $reissued, $disposed, $balance, $to_accountable, $unitAmt, $amount_total, $remarks);
                    @$h->execute();
                    $h->close();
                }
            } catch (Throwable $e) { /* ignore non-fatal */ }
        }
    }

    // Replace items (persist transfer_qty and ics linkage)
    $conn->query("DELETE FROM itr_items WHERE itr_id = $itr_id");
    $it = $conn->prepare("INSERT INTO itr_items (itr_id, date_acquired, item_no, ics_info, description, amount, transfer_qty, ics_id, ics_item_id, cond) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $rows = $data['items'] ?? [];
    foreach ($rows as $row) {
        $date_acq = toDate($row['date_acquired'] ?? null);
        $item_no = $row['item_no'] ?? null;
        $ics_info = $row['ics_info'] ?? null;
        $description = $row['description'] ?? null;
        $amount = toNumber($row['amount'] ?? 0);
        $tq = (int)($row['transfer_qty'] ?? 0);
        if ($tq <= 0) { $uc = isset($row['unit_cost']) ? (float)$row['unit_cost'] : 0.0; if ($uc > 0) { $tq = (int)floor($amount / $uc); } }
        $cond = $row['cond'] ?? ($row['condition'] ?? null);
        $ics_id_in = isset($row['ics_id']) ? (int)$row['ics_id'] : null;
        $ics_item_id_in = isset($row['ics_item_id']) ? (int)$row['ics_item_id'] : null;
        $it->bind_param('issssdiiss', $itr_id, $date_acq, $item_no, $ics_info, $description, $amount, $tq, $ics_id_in, $ics_item_id_in, $cond);
        if (!$it->execute()) { throw new Exception('Failed to insert item: '.$it->error); }
    }
    $it->close();

    // 4) Apply ICS deductions for new rows
    foreach ($rows as &$row) {
        $transfer_qty = (int)($row['transfer_qty'] ?? 0);
        if ($transfer_qty <= 0) continue;
        $unit_cost = $toNum($row['unit_cost'] ?? 0);
        $stock_no = $row['stock_number'] ?? ($row['item_no'] ?? null);
        $ics_item_id = isset($row['ics_item_id']) ? (int)$row['ics_item_id'] : 0;
        $ics_id_in = isset($row['ics_id']) ? (int)$row['ics_id'] : 0;
        if (!$stock_no) continue;

        $current_qty = null; $current_uc = null; $target_ics_item_id = null;
        if ($ics_item_id > 0) {
            $q = $conn->prepare("SELECT ics_item_id, ics_id, stock_number, quantity, unit_cost FROM ics_items WHERE ics_item_id = ? LIMIT 1");
            $q->bind_param('i', $ics_item_id);
            $q->execute(); $res = $q->get_result();
            if ($res && $res->num_rows > 0) { $r = $res->fetch_assoc(); $target_ics_item_id = (int)$r['ics_item_id']; $current_qty = (float)$r['quantity']; $current_uc = (float)$r['unit_cost']; }
            $q->close();
        }
        if ($target_ics_item_id === null && $ics_id_in > 0) {
            $q = $conn->prepare("SELECT ics_item_id, quantity, unit_cost FROM ics_items WHERE ics_id = ? AND stock_number = ? ORDER BY ics_item_id DESC LIMIT 1");
            $q->bind_param('is', $ics_id_in, $stock_no);
            $q->execute(); $res = $q->get_result();
            if ($res && $res->num_rows > 0) { $r = $res->fetch_assoc(); $target_ics_item_id = (int)$r['ics_item_id']; $current_qty = (float)$r['quantity']; $current_uc = (float)$r['unit_cost']; }
            $q->close();
        }
        if ($target_ics_item_id === null) {
            $q = $conn->prepare("SELECT ics_item_id, quantity, unit_cost FROM ics_items WHERE stock_number = ? ORDER BY ics_item_id DESC LIMIT 1");
            $q->bind_param('s', $stock_no);
            $q->execute(); $res = $q->get_result();
            if ($res && $res->num_rows > 0) { $r = $res->fetch_assoc(); $target_ics_item_id = (int)$r['ics_item_id']; $current_qty = (float)$r['quantity']; $current_uc = (float)$r['unit_cost']; }
            $q->close();
        }
        if ($target_ics_item_id !== null && $current_qty !== null) {
            $uc = ($unit_cost > 0) ? $unit_cost : ($current_uc ?? 0);
            $effective = min((float)$transfer_qty, max(0.0, (float)$current_qty));
            $new_qty = max(0.0, (float)$current_qty - $effective);
            $new_total = $uc * $new_qty;
            $u = $conn->prepare("UPDATE ics_items SET quantity = ?, total_cost = ? WHERE ics_item_id = ?");
            $u->bind_param('ddi', $new_qty, $new_total, $target_ics_item_id);
            if (!$u->execute()) { $u->close(); throw new Exception('Failed to update ICS item: ' . $u->error); }
            $u->close();
        }
    }

    if (method_exists($conn,'commit')) { $conn->commit(); }
    echo json_encode(['success'=>true, 'itr_id'=>$itr_id]);
} catch (Throwable $e) {
    if (method_exists($conn,'rollback')) { $conn->rollback(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
