<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
// Page-local RPCSP functions; saving reports does not change inventory.
function rpcspH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rpcspSchema(): string {
    return "CREATE TABLE IF NOT EXISTS rpcsp_reports (
        rpcsp_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_date DATE NOT NULL, entity_name VARCHAR(255) NOT NULL,
        accountable_officer VARCHAR(255) NOT NULL, item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL, created_by INT NOT NULL, save_token CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY rpcsp_save_token (save_token), KEY rpcsp_report_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

$conn->query(rpcspSchema());
if (empty($_SESSION['rpcsp_delete_csrf'])) $_SESSION['rpcsp_delete_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['rpcsp_delete_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid request. Reload the RPCSP page and try again.');
    }
    $id = filter_var($_POST['delete_rpcsp_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$id) throw new InvalidArgumentException('Invalid RPCSP document.');
        require_once dirname(__DIR__) . '/archive_helpers.php';
        archiveRecord($conn, 'RPCSP', $id);
        $_SESSION['rpcsp_notice'] = ['success', 'RPCSP document deleted. You can restore it from Archives.'];
    } catch (Throwable $error) {
        error_log('RPCSP archive failed: ' . $error->getMessage());
        $_SESSION['rpcsp_notice'] = ['error', 'Unable to delete the RPCSP document. Please reload and try again.'];
    }
    header('Location: rpcsp.php?' . http_build_query([
        'search' => is_string($_POST['search'] ?? '') ? ($_POST['search'] ?? '') : '',
        'sort' => is_string($_POST['sort'] ?? '') ? ($_POST['sort'] ?? 'newest') : 'newest',
    ]));
    exit;
}
$notice = $_SESSION['rpcsp_notice'] ?? null;
unset($_SESSION['rpcsp_notice']);
$search = is_string($_GET['search'] ?? '') ? trim($_GET['search'] ?? '') : '';
$sort = is_string($_GET['sort'] ?? '') ? ($_GET['sort'] ?? 'newest') : 'newest';
$orders = ['newest' => 'report_date DESC, rpcsp_id DESC', 'oldest' => 'report_date ASC, rpcsp_id ASC', 'document_no' => 'rpcsp_id ASC'];
if (!isset($orders[$sort])) $sort = 'newest';
$stmt = $conn->prepare("SELECT rpcsp_id, report_date, entity_name, accountable_officer, item_count, created_at
    FROM rpcsp_reports
    WHERE entity_name LIKE ? OR accountable_officer LIKE ?
       OR CONCAT('RPCSP-', LPAD(rpcsp_id, GREATEST(4, CHAR_LENGTH(rpcsp_id)), '0')) LIKE ?
    ORDER BY " . $orders[$sort]);
$like = '%' . $search . '%';
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= rpcspH(APP_ROOT_URL) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPCSP Documents</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <h2>Report on the Physical Count of Semi-Expendable Property</h2>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">RPCSP document saved successfully.</div><?php endif; ?>
    <?php if ($notice): ?><div class="alert <?= $notice[0] === 'success' ? 'alert-success' : 'alert-error' ?>" role="status"><?= rpcspH($notice[1]) ?></div><?php endif; ?>
    <form method="get" action="Semi_Expendables/rpcsp.php" class="filters">
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
                <input type="search" id="searchInput" name="search" value="<?= rpcspH($search) ?>" placeholder="Search document no., entity, or accountable officer...">

            </div>
            <div class="control-actions">
                <a href="Semi_Expendables/add_rpcsp.php" class="pill-btn pill-add"><i class="fas fa-plus"></i> Add RPCSP Document</a>
            </div>
        </div>
    </form>
    <div class="table-container">
        <table>
            <thead><tr><th>Document No.</th><th>Report Date</th><th>Entity Name</th><th>Accountable Officer</th><th>Rows</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><strong>RPCSP-<?= str_pad((string)$report['rpcsp_id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= rpcspH($report['report_date']) ?></td>
                    <td><?= rpcspH($report['entity_name']) ?></td>
                    <td><?= rpcspH($report['accountable_officer']) ?></td>
                    <td><?= (int)$report['item_count'] ?></td>
                    <td class="actions-cell">
                        <div class="actions-menu">
                            <button type="button" class="actions-menu-toggle" aria-label="Open RPCSP actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="actions-menu-list">
                                <a href="Semi_Expendables/view_rpcsp.php?rpcsp_id=<?= (int)$report['rpcsp_id'] ?>"><i class="fas fa-eye"></i> View</a>
                                <a href="Semi_Expendables/rpcsp_export.php?rpcsp_id=<?= (int)$report['rpcsp_id'] ?>"><i class="fas fa-download"></i> Export</a>
                                <form method="post" action="Semi_Expendables/rpcsp.php" onsubmit="return confirm('Delete this RPCSP document? You can restore it from Archives.');">
                                    <input type="hidden" name="csrf" value="<?= rpcspH($_SESSION['rpcsp_delete_csrf']) ?>">
                                    <input type="hidden" name="delete_rpcsp_id" value="<?= (int)$report['rpcsp_id'] ?>">
                                    <input type="hidden" name="search" value="<?= rpcspH($search) ?>">
                                    <input type="hidden" name="sort" value="<?= rpcspH($sort) ?>">
                                    <button type="submit" class="delete-action"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$reports): ?><tr><td colspan="6"><?= $search !== '' ? 'No matching RPCSP documents found.' : 'No saved RPCSP documents yet. Click Add RPCSP Document to create one.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="js/actions_menu.js"></script>
<script>
document.querySelectorAll('.container .alert-success').forEach(message => {
    window.setTimeout(() => message.remove(), 4000);
});
if (new URLSearchParams(window.location.search).has('saved')) {
    const url = new URL(window.location.href);
    url.searchParams.delete('saved');
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
}
</script>
</body>
</html>