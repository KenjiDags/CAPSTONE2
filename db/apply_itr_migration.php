<?php
// Run the ITR tables migration directly against the configured database
// Usage (CLI): php db/apply_itr_migration.php

require_once __DIR__ . '/../config.php';

function runMigration($conn, $sqlFile) {
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new Exception("Migration file is empty: $sqlFile");
    }

    // Run as multi_query to execute both CREATE TABLE statements
    if (!$conn->multi_query($sql)) {
        throw new Exception('Multi query failed: ' . $conn->error);
    }
    // Flush all results
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        throw new Exception('Error after migration: ' . $conn->error);
    }
}

try {
    $file = __DIR__ . '/migrations/2025-10-28_create_itr_tables.sql';
    runMigration($conn, $file);

    // Verify tables exist
    $ok1 = $conn->query("SHOW TABLES LIKE 'itr'");
    $ok2 = $conn->query("SHOW TABLES LIKE 'itr_items'");

    $hasItr = ($ok1 && $ok1->num_rows > 0);
    $hasItrItems = ($ok2 && $ok2->num_rows > 0);
    if ($ok1) $ok1->close();
    if ($ok2) $ok2->close();

    echo "Migration applied successfully.\n";
    echo "itr table: " . ($hasItr ? 'OK' : 'MISSING') . "\n";
    echo "itr_items table: " . ($hasItrItems ? 'OK' : 'MISSING') . "\n";
    exit(0);
} catch (Throwable $e) {
    http_response_code(500);
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
