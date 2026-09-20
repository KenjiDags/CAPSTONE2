<?php
// Install once via db/migrate_stockout_tracking.php, not during dashboard reads.
function installStockoutTracking(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS item_stockouts (
        item_id INT NOT NULL PRIMARY KEY,
        started_at DATETIME NOT NULL,
        date_source VARCHAR(20) NOT NULL,
        CONSTRAINT fk_stockout_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Triggers cover every writer, including imports and edits that bypass history.
    $triggers = [
        'items_stockout_insert' => "AFTER INSERT ON items FOR EACH ROW
            BEGIN
                IF NEW.quantity_on_hand = 0 THEN
                    INSERT INTO item_stockouts VALUES (NEW.item_id, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 8 HOUR), 'recorded');
                END IF;
            END",
        'items_stockout_update' => "AFTER UPDATE ON items FOR EACH ROW
            BEGIN
                IF NEW.quantity_on_hand = 0 THEN
                    INSERT INTO item_stockouts VALUES (NEW.item_id, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 8 HOUR),
                        IF(OLD.quantity_on_hand = 0, 'observed', 'recorded'))
                    ON DUPLICATE KEY UPDATE item_id = NEW.item_id;
                ELSE
                    DELETE FROM item_stockouts WHERE item_id = NEW.item_id;
                END IF;
            END",
    ];
    foreach ($triggers as $name => $body) {
        $result = $conn->query("SELECT 1 FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = '$name'");
        if ($result->num_rows === 0) $conn->query("CREATE TRIGGER $name $body");
    }

    // Include archived stock cards; clearing visible history must not lose dates.
    // Materialize the union once so it works on both MySQL and MariaDB.
    $conn->query("CREATE TEMPORARY TABLE stockout_history AS
        SELECT history_id, item_id, changed_at, quantity_on_hand FROM item_history
        UNION SELECT history_id, item_id, changed_at, quantity_on_hand FROM item_history_archive");
    $conn->query("ALTER TABLE stockout_history ADD INDEX (item_id, changed_at, history_id)");
    // MySQL cannot reopen a temporary table under a second alias.
    $conn->query("CREATE TEMPORARY TABLE stockout_history_newer AS SELECT * FROM stockout_history");
    $conn->query("ALTER TABLE stockout_history_newer ADD INDEX (item_id, changed_at, history_id)");
    try {
        $conn->query("INSERT INTO item_stockouts (item_id, started_at, date_source)
            SELECT i.item_id,
                COALESCE(MIN(h.changed_at), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 8 HOUR)),
                IF(MIN(h.changed_at) IS NULL, 'observed', 'history')
            FROM items i
            LEFT JOIN stockout_history h ON h.item_id = i.item_id
                AND h.quantity_on_hand = 0 AND h.changed_at >= '1000-01-01'
                AND NOT EXISTS (
                    SELECT 1 FROM stockout_history_newer newer
                    WHERE newer.item_id = h.item_id AND newer.quantity_on_hand <> 0
                      AND (newer.changed_at > h.changed_at OR
                          (newer.changed_at = h.changed_at AND newer.history_id > h.history_id)))
            WHERE i.quantity_on_hand = 0
            GROUP BY i.item_id
            ON DUPLICATE KEY UPDATE item_id = VALUES(item_id)");
    } finally {
        $conn->query('DROP TEMPORARY TABLE stockout_history, stockout_history_newer');
    }
}
