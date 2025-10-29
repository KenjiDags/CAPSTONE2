<?php
// Patch: Add 'remarks' TEXT column to itr and ics tables if not present
require_once __DIR__ . '/../config.php';

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1";
    $res = $conn->query($sql);
    $exists = $res && $res->num_rows > 0;
    if ($res) { $res->close(); }
    return $exists;
}

$results = [
    'itr' => null,
    'ics' => null,
];

try {
    // ITR
    $res = $conn->query("SHOW TABLES LIKE 'itr'");
    $itrExists = $res && $res->num_rows > 0; if ($res) { $res->close(); }
    if ($itrExists && !columnExists($conn, 'itr', 'remarks')) {
        $ok = $conn->query("ALTER TABLE itr ADD COLUMN remarks TEXT NULL AFTER reason");
        $results['itr'] = $ok ? 'added' : ('failed: ' . $conn->error);
    } else if ($itrExists) {
        $results['itr'] = 'exists';
    } else {
        $results['itr'] = 'table not found';
    }

    // ICS
    $res2 = $conn->query("SHOW TABLES LIKE 'ics'");
    $icsExists = $res2 && $res2->num_rows > 0; if ($res2) { $res2->close(); }
    if ($icsExists && !columnExists($conn, 'ics', 'remarks')) {
        $ok = $conn->query("ALTER TABLE ics ADD COLUMN remarks TEXT NULL AFTER received_from_position");
        $results['ics'] = $ok ? 'added' : ('failed: ' . $conn->error);
    } else if ($icsExists) {
        $results['ics'] = 'exists';
    } else {
        $results['ics'] = 'table not found';
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'results' => $results]);
} catch (Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
