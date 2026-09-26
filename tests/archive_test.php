<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../archive_helpers.php';
$conn = new mysqli('localhost', 'root', '');
$database = 'archive_test_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$database`");
$conn->select_db($database);
$_SESSION['user_id'] = 7;
function archiveCheck($condition, $message) { if (!$condition) throw new RuntimeException($message); }
try {
    $conn->query('CREATE TABLE items (item_id INT PRIMARY KEY, stock_number VARCHAR(30), item_name VARCHAR(50), description TEXT, unit VARCHAR(20), quantity_on_hand INT) ENGINE=InnoDB');
    $conn->query('CREATE TABLE ris (ris_id INT PRIMARY KEY, ris_no VARCHAR(30)) ENGINE=InnoDB');
    $conn->query('CREATE TABLE ris_items (ris_id INT, stock_number VARCHAR(30), issued_quantity INT) ENGINE=InnoDB');
    $conn->query('CREATE TABLE item_history (history_id INT PRIMARY KEY, item_id INT, ris_id INT) ENGINE=InnoDB');
    $conn->query('CREATE TABLE item_history_archive LIKE item_history');
    $conn->query("INSERT INTO items VALUES (1,'S-1','Paper','A4','ream',8)");
    $conn->query("INSERT INTO ris VALUES (1,'RIS-1'),(2,'RIS-2')");
    $conn->query("INSERT INTO ris_items VALUES (1,'S-1',2)");
    $conn->query('INSERT INTO item_history VALUES (1,1,1)');
    archiveRecord($conn, 'RIS', 1);
    $record = $conn->query('SELECT * FROM deleted_records')->fetch_assoc();
    $snapshot = json_decode($record['snapshot'], true);
    archiveCheck(count($snapshot['ris_items']) === 1 && $snapshot['RSMI'][0]['item_name'] === 'Paper', 'RIS and RSMI details preserved');
    archiveCheck($conn->query('SELECT * FROM ris WHERE ris_id=1')->num_rows === 0, 'RIS removed');
    archiveCheck((int)$record['deleted_by'] === 7, 'Actor recorded');
    $conn->query('CREATE TABLE blocked (ris_id INT, FOREIGN KEY (ris_id) REFERENCES ris(ris_id)) ENGINE=InnoDB');
    $conn->query('INSERT INTO blocked VALUES (2)');
    try { archiveRecord($conn, 'RIS', 2); throw new LogicException('Expected foreign key rejection'); }
    catch (mysqli_sql_exception $expected) {}
    archiveCheck($conn->query('SELECT * FROM ris WHERE ris_id=2')->num_rows === 1, 'Failed deletion retains source');
    archiveCheck($conn->query('SELECT * FROM deleted_records')->num_rows === 1, 'Failed deletion rolls back snapshot');
    $conn->query('INSERT INTO item_history VALUES (2,1,NULL)');
    archiveRecord($conn, 'SC', 1, false);
    archiveCheck($conn->query('SELECT * FROM item_history')->num_rows === 0, 'SC history cleared');
    archiveCheck($conn->query('SELECT * FROM item_history_archive')->num_rows === 1, 'SC undo history retained');
    archiveCheck((int)$conn->query('SELECT quantity_on_hand FROM items')->fetch_assoc()['quantity_on_hand'] === 8, 'SC clear leaves stock balance intact');
    $scId = (int)$conn->query("SELECT archive_id FROM deleted_records WHERE record_type='SC'")->fetch_assoc()['archive_id'];
    $conn->query('UPDATE items SET quantity_on_hand=19 WHERE item_id=1');
    archiveAction($conn, $scId, 'restore');
    archiveCheck($conn->query('SELECT * FROM item_history WHERE history_id=2')->num_rows === 1, 'SC history restored');
    archiveCheck($conn->query('SELECT * FROM item_history_archive')->num_rows === 0, 'Legacy undo copy cleared');
    archiveCheck((int)$conn->query('SELECT quantity_on_hand FROM items')->fetch_assoc()['quantity_on_hand'] === 19, 'SC restore preserves current balance');
    archiveAction($conn, (int)$record['archive_id'], 'restore');
    archiveCheck($conn->query('SELECT * FROM ris WHERE ris_id=1')->num_rows === 1, 'RIS header restored');
    archiveCheck($conn->query('SELECT * FROM ris_items WHERE ris_id=1')->num_rows === 1, 'RIS lines restored');
    archiveCheck($conn->query('SELECT * FROM item_history WHERE history_id=1')->num_rows === 1, 'RIS history restored');
    archiveCheck($conn->query('SELECT * FROM deleted_records')->num_rows === 0, 'Restored archives removed');
    archiveRecord($conn, 'RIS', 1);
    $conflictId = (int)$conn->query('SELECT archive_id FROM deleted_records')->fetch_assoc()['archive_id'];
    $conn->query("INSERT INTO ris VALUES (1,'NEW-RIS')");
    try { archiveAction($conn, $conflictId, 'restore'); throw new LogicException('Expected conflict rejection'); }
    catch (RuntimeException $expected) { archiveCheck(!($expected instanceof LogicException), 'Conflict detected'); }
    archiveCheck($conn->query('SELECT * FROM deleted_records')->num_rows === 1, 'Conflict retains archive');
    archiveCheck($conn->query('SELECT * FROM ris_items WHERE ris_id=1')->num_rows === 0, 'Conflict does not partially restore');
    archiveAction($conn, $conflictId, 'delete');
    archiveCheck($conn->query('SELECT * FROM deleted_records')->num_rows === 0, 'Permanent delete removes archived copy');
    archiveCheck($conn->query('SELECT ris_no FROM ris WHERE ris_id=1')->fetch_assoc()['ris_no'] === 'NEW-RIS', 'Permanent delete does not touch live records');
    archiveRecord($conn, 'SC', 1, false);
    $scId = (int)$conn->query('SELECT archive_id FROM deleted_records')->fetch_assoc()['archive_id'];
    archiveAction($conn, $scId, 'delete');
    archiveCheck($conn->query('SELECT * FROM item_history_archive')->num_rows === 0, 'Permanent SC delete removes legacy undo copy');
    $conn->query('CREATE TABLE inventory_entries (id INT PRIMARY KEY, item_id INT, FOREIGN KEY(item_id) REFERENCES items(item_id) ON DELETE CASCADE) ENGINE=InnoDB');
    $conn->query('CREATE TABLE item_stockouts (item_id INT PRIMARY KEY, started_at DATETIME, date_source VARCHAR(20), FOREIGN KEY(item_id) REFERENCES items(item_id) ON DELETE CASCADE) ENGINE=InnoDB');
    $conn->query("CREATE TRIGGER archive_test_stockout AFTER INSERT ON items FOR EACH ROW INSERT INTO item_stockouts VALUES (NEW.item_id, NOW(), 'recorded')");
    $conn->query("INSERT INTO item_stockouts VALUES (1,'2026-01-01','recorded')");
    $conn->query('INSERT INTO inventory_entries VALUES (1,1)');
    archiveRecord($conn, 'Supply', 1);
    $supplyId = (int)$conn->query('SELECT archive_id FROM deleted_records')->fetch_assoc()['archive_id'];
    archiveAction($conn, $supplyId, 'restore');
    archiveCheck($conn->query('SELECT started_at FROM item_stockouts')->fetch_assoc()['started_at'] === '2026-01-01 00:00:00', 'Restore preserves stockout history despite insert trigger');
    archiveCheck($conn->query('SELECT * FROM inventory_entries')->num_rows === 1, 'Dependent inventory entries restored');
    // A child conflict after the parent INSERT must roll back that INSERT too.
    archiveRecord($conn, 'Supply', 1);
    $supplyId = (int)$conn->query('SELECT archive_id FROM deleted_records')->fetch_assoc()['archive_id'];
    $conn->query("INSERT INTO items VALUES (2,'S-2','Other','Other','each',4)");
    $conn->query('INSERT INTO inventory_entries VALUES (1,2)');
    try { archiveAction($conn, $supplyId, 'restore'); throw new LogicException('Expected child conflict'); }
    catch (RuntimeException $expected) { archiveCheck(!($expected instanceof LogicException), 'Child conflict detected'); }
    archiveCheck($conn->query('SELECT * FROM items WHERE item_id=1')->num_rows === 0, 'Child conflict rolls back parent');
    archiveCheck($conn->query('SELECT * FROM deleted_records')->num_rows === 1, 'Child conflict preserves archive');
    $conn->query('CREATE TABLE ics (ics_id INT PRIMARY KEY, ics_no VARCHAR(30)) ENGINE=MyISAM');
    $conn->query('CREATE TABLE ics_items (id INT PRIMARY KEY, ics_id INT, quantity INT) ENGINE=MyISAM');
    $conn->query("INSERT INTO ics VALUES (1,'ICS-1')");
    $conn->query('INSERT INTO ics_items VALUES (1,1,3)');
    archiveRecord($conn, 'ICS', 1);
    $icsId = (int)$conn->query("SELECT archive_id FROM deleted_records WHERE record_type='ICS'")->fetch_assoc()['archive_id'];
    archiveAction($conn, $icsId, 'restore');
    archiveCheck($conn->query('SELECT * FROM ics_items')->num_rows === 1, 'Legacy ICS tables support restoration');
    $engine = $conn->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ics'")->fetch_assoc()['ENGINE'];
    archiveCheck($engine === 'InnoDB', 'Legacy storage upgraded for atomic restore');
    echo "Archive tests passed\n";
} finally {
    $conn->query("DROP DATABASE `$database`");
}
