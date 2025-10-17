<?php
// Backfill unit amount from amount_total for semi_expendable tables (safe/idempotent)
// - semi_expendable_property.amount = ROUND(amount_total / quantity, 2) when amount missing and quantity > 0
// - semi_expendable_history.amount = ROUND(amount_total / COALESCE(quantity, quantity_issued), 2) when missing

header('Content-Type: text/plain');

require_once __DIR__ . '/../config.php';

function execQuery($conn, $sql, $label) {
    if (!$conn->query($sql)) {
        throw new Exception($label . ' failed: ' . $conn->error);
    }
}

try {
    // Ensure columns exist (best effort)
    @execQuery($conn, "ALTER TABLE semi_expendable_property ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) DEFAULT 0", 'ALTER property add amount');
    @execQuery($conn, "CREATE TABLE IF NOT EXISTS semi_expendable_history (id INT PRIMARY KEY AUTO_INCREMENT, semi_id INT NOT NULL, date DATE NULL, ics_rrsp_no VARCHAR(255) NULL, quantity INT DEFAULT 0, quantity_issued INT DEFAULT 0, quantity_returned INT DEFAULT 0, quantity_reissued INT DEFAULT 0, quantity_disposed INT DEFAULT 0, quantity_balance INT DEFAULT 0, office_officer_issued VARCHAR(255) NULL, office_officer_returned VARCHAR(255) NULL, office_officer_reissued VARCHAR(255) NULL, amount DECIMAL(15,2) DEFAULT 0, amount_total DECIMAL(15,2) DEFAULT 0, remarks TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (semi_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'ensure history');
    @execQuery($conn, "ALTER TABLE semi_expendable_history ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) DEFAULT 0", 'ALTER history add amount');

    // Property table backfill (only where amount is NULL or 0, amount_total > 0, and quantity > 0)
    $sqlProp = "UPDATE semi_expendable_property
                SET amount = ROUND(amount_total / NULLIF(quantity, 0), 2)
                WHERE (amount IS NULL OR amount = 0)
                  AND amount_total > 0
                  AND quantity > 0";
    execQuery($conn, $sqlProp, 'Backfill property amount');

    // History table backfill (prefer quantity, else quantity_issued)
    $sqlHist = "UPDATE semi_expendable_history
                SET amount = ROUND(amount_total / NULLIF(COALESCE(quantity, quantity_issued, 0), 0), 2)
                WHERE (amount IS NULL OR amount = 0)
                  AND amount_total > 0
                  AND COALESCE(quantity, quantity_issued, 0) > 0";
    execQuery($conn, $sqlHist, 'Backfill history amount');

    echo "SUCCESS\nUnit amounts backfilled where possible.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

@$conn->close();
?>
