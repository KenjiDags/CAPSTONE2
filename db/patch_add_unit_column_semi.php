<?php
// Patch: Add 'unit' column to semi_expendable_property if not present
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

header('Content-Type: application/json');

try {
    $res = $conn->query("SHOW TABLES LIKE 'semi_expendable_property'");
    $exists = $res && $res->num_rows > 0; if ($res) { $res->close(); }
    if (!$exists) {
        echo json_encode(['success' => false, 'message' => "Table 'semi_expendable_property' not found."]);
        exit;
    }

    if (!columnExists($conn, 'semi_expendable_property', 'unit')) {
        $ok = $conn->query("ALTER TABLE semi_expendable_property ADD COLUMN unit VARCHAR(64) NULL AFTER item_description");
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $conn->error]);
            exit;
        }
        echo json_encode(['success' => true, 'result' => 'added']);
        exit;
    }

    echo json_encode(['success' => true, 'result' => 'exists']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
