<?php
// Isolated database only; never write fixture records into tesda_inventory.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../db/stockout_tracking.php';
$conn = new mysqli('localhost', 'root', '');
$database = 'stockout_test_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$database`");
$conn->select_db($database);
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function episode($conn, $id) {
    return $conn->query("SELECT * FROM item_stockouts WHERE item_id = $id")->fetch_assoc();
}
try {
    $conn->query('CREATE TABLE items (item_id INT PRIMARY KEY, quantity_on_hand INT) ENGINE=InnoDB');
    $conn->query('CREATE TABLE item_history (history_id INT, item_id INT, changed_at DATETIME, quantity_on_hand INT)');
    $conn->query('CREATE TABLE item_history_archive LIKE item_history');
    $conn->query('INSERT INTO items VALUES (1,0),(2,0),(3,0),(4,5),(5,0),(6,0)');
    $conn->query("INSERT INTO item_history VALUES
        (1,1,'2026-09-01 08:00:00',5),(2,1,'2026-09-03 08:00:00',0),(3,1,'2026-09-09 08:00:00',0),
        (4,2,'2026-09-01 08:00:00',0),(5,2,'2026-09-05 08:00:00',10),(6,2,'2026-09-08 08:00:00',0),
        (7,5,'2026-09-01 08:00:00',0),(8,5,'2026-09-01 08:00:00',10),(9,5,'2026-09-04 08:00:00',0)");
    $conn->query("INSERT INTO item_history_archive VALUES (10,6,'2026-09-02 08:00:00',0)");
    installStockoutTracking($conn);
    foreach ([1 => '03', 2 => '08', 5 => '04', 6 => '02'] as $id => $day) {
        check(episode($conn, $id)['started_at'] === "2026-09-$day 08:00:00", 'History episode recovery failed');
    }
    $unknown = episode($conn, 3);
    check($unknown['date_source'] === 'observed', 'Missing history must be labeled observed');
    check(episode($conn, 4) === null, 'In-stock item must have no episode');
    installStockoutTracking($conn);
    check(episode($conn, 3) === $unknown, 'Rerunning migration reset the clock');
    $conn->query('DELETE FROM item_history WHERE item_id = 1');
    $conn->query('UPDATE items SET quantity_on_hand = 0 WHERE item_id = 1');
    check(episode($conn, 1)['started_at'] === '2026-09-03 08:00:00', 'Archive/edit reset the clock');
    $conn->query('UPDATE items SET quantity_on_hand = 7 WHERE item_id = 1');
    check(episode($conn, 1) === null, 'Restocking must clear the episode');
    $conn->query('UPDATE items SET quantity_on_hand = 0 WHERE item_id = 1');
    check(episode($conn, 1)['date_source'] === 'recorded', 'Depletion was not recorded');
    check(episode($conn, 1)['started_at'] !== '2026-09-03 08:00:00', 'New episode reused old date');
    $conn->query('INSERT INTO items VALUES (7,0),(8,10)');
    check(episode($conn, 7)['date_source'] === 'recorded', 'Initial zero balance was not recorded');
    check(episode($conn, 8) === null, 'Positive insert must not start an episode');
    $conn->begin_transaction();
    $conn->query('UPDATE items SET quantity_on_hand = 0 WHERE item_id = 8');
    $conn->rollback();
    check(episode($conn, 8) === null, 'Rolled-back depletion left an episode');
    $conn->query('DELETE FROM items WHERE item_id = 7');
    check(episode($conn, 7) === null, 'Item deletion left an episode');
    require __DIR__ . '/../mrp_dates.php';
    $start = substr($unknown['started_at'], 0, 10);
    check(mrpDaysOutOfStock($start, mrpDate($start)->modify('+3 days')->format('Y-m-d')) === 3,
        'Observed date must count forward');
    echo "Stockout migration, history recovery, triggers, rollback, and counter checks passed.\n";
} finally {
    $conn->query("DROP DATABASE `$database`");
}
