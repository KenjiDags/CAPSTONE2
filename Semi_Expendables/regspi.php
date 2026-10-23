<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
// Page-local RegSPI functions; saving reports does not change inventory.
function regspiH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function regspiSchema(): string {
    return "CREATE TABLE IF NOT EXISTS regspi_reports (
        regspi_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_date DATE NOT NULL,
        entity_name VARCHAR(255) NOT NULL,
        fund_cluster VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        save_token CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY regspi_save_token (save_token),
        KEY regspi_report_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

function regspiEnsureTable(mysqli $conn): void {
    $conn->query(regspiSchema());
}

regspiEnsureTable($conn);
$search = is_string($_GET['search'] ?? '') ? trim($_GET['search'] ?? '') : '';
$sort = is_string($_GET['sort'] ?? '') ? ($_GET['sort'] ?? 'newest') : 'newest';
$orders = ['newest' => 'report_date DESC, regspi_id DESC', 'oldest' => 'report_date ASC, regspi_id ASC', 'document_no' => 'regspi_id ASC'];
if (!isset($orders[$sort])) $sort = 'newest';
$stmt = $conn->prepare("SELECT regspi_id, report_date, entity_name, fund_cluster, category, item_count, created_at
    FROM regspi_reports
    WHERE entity_name LIKE ? OR category LIKE ? OR fund_cluster LIKE ?
       OR CONCAT('REGSPI-', LPAD(regspi_id, GREATEST(4, CHAR_LENGTH(regspi_id)), '0')) LIKE ?
    ORDER BY " . $orders[$sort]);
$like = '%' . $search . '%';
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= regspiH(APP_ROOT_URL) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RegSPI Documents</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <h2>Registry of Semi-Expendable Property Issued</h2>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">RegSPI document saved successfully.</div><?php endif; ?>
    <form method="get" action="Semi_Expendables/regspi.php" class="filters">
        <div class="inventory-controls">
            <div class="control-sort">
                <label for="sort-select"><i class="fas fa-sort"></i> Sort By:</label>
                <select id="sort-select" name="sort" onchange="this.form.submit()">
                    <?php foreach (['newest' => 'Date (Newest First)', 'oldest' => 'Date (Oldest First)', 'document_no' => 'Document No. (Ascending)'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="control-search">
                <label for="searchInput"><i class="fas fa-search"></i> Search:</label>
                <input type="search" id="searchInput" name="search" value="<?= regspiH($search) ?>" placeholder="Search document no., entity, fund, or category...">
                <button type="submit" class="pill-btn pill-view">Search</button>
            </div>
            <div class="control-actions">
                <a href="Semi_Expendables/add_regspi.php" class="pill-btn pill-add"><i class="fas fa-plus"></i> Add RegSPI Document</a>
            </div>
        </div>
    </form>
    <div class="table-container">
        <table>
            <thead><tr><th>Document No.</th><th>Report Date</th><th>Entity Name</th><th>Fund Cluster</th><th>Property Category</th><th>Rows</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><strong>REGSPI-<?= str_pad((string)$report['regspi_id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= regspiH($report['report_date']) ?></td>
                    <td><?= regspiH($report['entity_name']) ?></td>
                    <td><?= regspiH($report['fund_cluster']) ?></td>
                    <td><?= regspiH($report['category'] ?: 'All') ?></td>
                    <td><?= (int)$report['item_count'] ?></td>
                    <td class="actions-cell">
                        <div class="actions-menu">
                            <button type="button" class="actions-menu-toggle" aria-label="Open RegSPI actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="actions-menu-list">
                                <a href="Semi_Expendables/view_regspi.php?regspi_id=<?= (int)$report['regspi_id'] ?>"><i class="fas fa-eye"></i> View</a>
                                <a href="Semi_Expendables/export_regspi.php?regspi_id=<?= (int)$report['regspi_id'] ?>"><i class="fas fa-download"></i> Export</a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$reports): ?><tr><td colspan="7"><?= $search !== '' ? 'No matching RegSPI documents found.' : 'No saved RegSPI documents yet. Click Add RegSPI Document to create one.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="js/actions_menu.js"></script>
</body>
</html>