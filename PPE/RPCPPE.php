<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';
$search = trim((string)($_GET['search'] ?? ''));
$sort = $_GET['sort'] ?? 'date_newest';
$orders = ['date_newest' => 'r.report_date DESC, r.rpcppe_id DESC', 'date_oldest' => 'r.report_date ASC, r.rpcppe_id ASC', 'amount_highest' => 'report_total DESC, r.rpcppe_id DESC', 'amount_lowest' => 'report_total ASC, r.rpcppe_id DESC'];
if (!isset($orders[$sort])) $sort = 'date_newest';
$error = '';
if (empty($_SESSION['rpcppe_delete_csrf'])) $_SESSION['rpcppe_delete_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['rpcppe_delete_csrf'], $_POST['csrf']) || !$id || $id < 1) {
        http_response_code(400);
        $error = 'Invalid delete request. Reload the page and try again.';
    } else {
        try {
            require_once dirname(__DIR__) . '/archive_helpers.php';
            archiveRecord($conn, 'RPCPPE', $id);
            $_SESSION['success'] = 'RPCPPE report moved to Archive.';
            header('Location: ' . APP_ROOT_URL . 'PPE/RPCPPE.php?' . http_build_query(['search' => $search, 'sort' => $sort]));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$sql = "SELECT r.*, CASE WHEN r.snapshot IS NOT NULL THEN r.item_count ELSE (SELECT COUNT(*) FROM rpcppe_items i WHERE i.rpcppe_id = r.rpcppe_id) END AS report_items, CASE WHEN r.snapshot IS NOT NULL THEN r.total_amount ELSE (SELECT COALESCE(SUM(p.amount * p.quantity), 0) FROM rpcppe_items i JOIN ppe_property p ON p.id = i.ppe_id WHERE i.rpcppe_id = r.rpcppe_id) END AS report_total FROM rpcppe r";
if ($search !== '') $sql .= ' WHERE CAST(r.rpcppe_id AS CHAR) LIKE ? OR r.accountable_officer LIKE ? OR r.entity_name LIKE ? OR r.fund_cluster LIKE ?';
$sql .= ' ORDER BY ' . $orders[$sort];
$stmt = $conn->prepare($sql);
if ($search !== '') { $like = '%' . $search . '%'; $stmt->bind_param('ssss', $like, $like, $like, $like); }
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>
<!doctype html>
<html lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saved RPCPPE Reports</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<main class="container">
    <h2>Report on Physical Count of Property, Plant and Equipment (RPCPPE)</h2>
    <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="get" action="PPE/RPCPPE.php" class="filters report-list-filters">
        <div class="inventory-controls">
            <div class="control-sort">
                <label for="reportSort"><i class="fas fa-sort" aria-hidden="true"></i> Sort by:</label>
                <select id="reportSort" name="sort" onchange="this.form.submit()">
                    <?php foreach (['date_newest'=>'Date (Newest First)', 'date_oldest'=>'Date (Oldest First)', 'amount_highest'=>'Total Value (Highest)', 'amount_lowest'=>'Total Value (Lowest)'] as $value=>$label): ?>
                    <option value="<?= $value ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="control-search">
                <label for="reportSearch"><i class="fas fa-search" aria-hidden="true"></i> Search:</label>
                <input id="reportSearch" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search report ID, officer, entity, or fund...">
            </div>
            <div class="control-actions">
                <a href="PPE/add_RPCPPE.php" class="pill-btn pill-add"><i class="fas fa-plus" aria-hidden="true"></i> Add RPCPPE</a>
            </div>
        </div>
    </form>
    <div class="table-container">
        <table>
            <thead><tr><th>Report ID</th><th>Report Date</th><th>Accountable Officer</th><th>Fund Cluster</th><th>Items</th><th>Total Inventory Value</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td>RPCPPE-<?= (int)$report['rpcppe_id'] ?></td>
                    <td><?= htmlspecialchars($report['report_date']) ?></td>
                    <td><?= htmlspecialchars($report['accountable_officer'] ?? '') ?></td>
                    <td><?= htmlspecialchars($report['fund_cluster'] ?? '') ?></td>
                    <td><?= number_format((int)$report['report_items']) ?></td>
                    <td class="currency">₱<?= number_format((float)$report['report_total'], 2) ?></td>
                    <td class="actions-cell">
                        <form id="delete-rpcppe-<?= (int)$report['rpcppe_id'] ?>" method="post" action="PPE/RPCPPE.php?<?= htmlspecialchars(http_build_query(['search'=>$search, 'sort'=>$sort]), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Move this RPCPPE report to Archive?');">
                            <input type="hidden" name="delete_id" value="<?= (int)$report['rpcppe_id'] ?>">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['rpcppe_delete_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        </form>
                        <div class="actions-menu">
                            <button type="button" class="actions-menu-toggle" aria-label="Open report actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="actions-menu-list">
                                <a href="PPE/view_rpcppe.php?id=<?= (int)$report['rpcppe_id'] ?>"><i class="fas fa-eye"></i> View</a>
                                <a href="PPE/export_rpcppe.php?id=<?= (int)$report['rpcppe_id'] ?>"><i class="fas fa-download"></i> Export</a>
                                <button type="submit" form="delete-rpcppe-<?= (int)$report['rpcppe_id'] ?>" class="delete-action"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$reports): ?><tr><td colspan="7">No saved RPCPPE reports found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script src="js/actions_menu.js"></script>
</body>
</html>