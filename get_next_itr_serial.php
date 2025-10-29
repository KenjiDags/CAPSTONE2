<?php
require 'config.php';
header('Content-Type: application/json');
// Prevent caching so the latest serial is always fetched
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function pad4($n){
    $num = intval($n);
    if ($num <= 0) $num = 1;
    return str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

try {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
    if ($month < 1 || $month > 12) { $month = (int)date('m'); }
    if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }

    $mm = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
    $yy = (string)$year;

    // If itr table doesn't exist yet -> 0001
    $probe = $conn->query("SHOW TABLES LIKE 'itr'");
    if (!$probe || $probe->num_rows === 0) {
        echo json_encode(['success'=>true,'next_serial'=>'0001']);
        exit;
    }

    // Primary: compute max numeric serial for the month/year
    $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(itr_no, '-', 1) AS UNSIGNED)) AS max_serial
            FROM itr
            WHERE itr_no LIKE CONCAT('%-', ?, '-', ?)";
    $stmt = $conn->prepare($sql);
    $next = 1; $maxSerial = 0;
    if ($stmt) {
        $stmt->bind_param('ss', $mm, $yy);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            $maxSerial = isset($row['max_serial']) ? (int)$row['max_serial'] : 0;
            if ($maxSerial > 0) { $next = $maxSerial + 1; }
        }
        $stmt->close();
    }

    // Fallback like ICS: get last itr_no string for that month/year and increment its leading number
    if ($maxSerial === 0) {
        $suffix = '-' . $mm . '-' . $yy;
        $sql2 = "SELECT itr_no FROM itr WHERE itr_no LIKE CONCAT('%', ?) ORDER BY itr_no DESC LIMIT 1";
        $st2 = $conn->prepare($sql2);
        if ($st2) {
            $st2->bind_param('s', $suffix);
            $st2->execute();
            $r2 = $st2->get_result();
            if ($r2 && $r2->num_rows > 0) {
                $row2 = $r2->fetch_assoc();
                if (preg_match('/^(\d{1,4})-/', $row2['itr_no'], $m)) {
                    $candidate = (int)$m[1];
                    if ($candidate > 0) { $next = $candidate + 1; }
                }
            }
            $st2->close();
        }
    }

    echo json_encode(['success'=>true,'next_serial'=>pad4($next)]);
} catch (Throwable $e) {
    echo json_encode(['success'=>true,'next_serial'=>'0001']);
}
