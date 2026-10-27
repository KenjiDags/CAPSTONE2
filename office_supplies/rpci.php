<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once __DIR__ . '/rpci_helpers.php';
rpciEnsureTable($conn);
if (empty($_SESSION['rpci_delete_csrf'])) $_SESSION['rpci_delete_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['rpci_delete_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid request. Reload the RPCI page and try again.');
    }
    $id = filter_var($_POST['delete_rpci_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$id) throw new InvalidArgumentException('Invalid RPCI document.');
        require_once dirname(__DIR__) . '/archive_helpers.php';
        archiveRecord($conn, 'RPCI', $id);
        $_SESSION['rpci_notice'] = ['success', 'RPCI document deleted. You can restore it from Archives.'];
    } catch (Throwable $error) {
        error_log('RPCI archive failed: ' . $error->getMessage());
        $_SESSION['rpci_notice'] = ['error', 'Unable to delete the RPCI document. Please reload and try again.'];
    }
    header('Location: rpci.php?' . http_build_query([
        'search' => is_string($_POST['search'] ?? '') ? ($_POST['search'] ?? '') : '',
        'sort' => is_string($_POST['sort'] ?? '') ? ($_POST['sort'] ?? 'date_newest') : 'date_newest',
    ]));
    exit;
}
$notice = $_SESSION['rpci_notice'] ?? null;
unset($_SESSION['rpci_notice']);
$search = is_string($_GET['search'] ?? '') ? trim($_GET['search'] ?? '') : '';
$sort = is_string($_GET['sort'] ?? '') ? ($_GET['sort'] ?? 'date_newest') : 'date_newest';
$orders = ['date_newest' => 'report_date DESC, rpci_id DESC', 'date_oldest' => 'report_date ASC, rpci_id ASC', 'document_no' => 'rpci_id ASC'];
if (!isset($orders[$sort])) $sort = 'date_newest';
$stmt = $conn->prepare("SELECT rpci_id, report_date, entity_name, accountable_officer, item_count, created_at FROM rpci_reports
    WHERE entity_name LIKE ? OR accountable_officer LIKE ? OR CONCAT('RPCI-', LPAD(rpci_id, GREATEST(4, CHAR_LENGTH(rpci_id)), '0')) LIKE ?
    ORDER BY " . $orders[$sort]);
$like = '%' . $search . '%';
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPCI Documents - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <h2>Report on the Physical Count of Inventories (RPCI)</h2>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">RPCI document saved successfully.</div><?php endif; ?>
    <?php if ($notice): ?><div class="alert <?= $notice[0] === 'success' ? 'alert-success' : 'alert-error' ?>" role="status"><?= rpciH($notice[1]) ?></div><?php endif; ?>
    <form method="get" class="filters">
        <div class="inventory-controls">
            <div class="control-sort">
                <label for="sort-select"><i class="fas fa-sort"></i> Sort By:</label>
                <select id="sort-select" name="sort" onchange="this.form.submit()">
                    <?php foreach (['date_newest' => 'Date (Newest First)', 'date_oldest' => 'Date (Oldest First)', 'document_no' => 'RPCI No. (Ascending)'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="control-search">
                <label for="searchInput"><i class="fas fa-search"></i> Search:</label>
                <input type="search" id="searchInput" name="search" value="<?= rpciH($search) ?>" placeholder="Search RPCI no., entity, or officer...">
            </div>
            <div class="control-actions">
                <a href="office_supplies/add_rpci.php" class="pill-btn pill-add"><i class="fas fa-plus"></i> Add RPCI Document</a>
            </div>
        </div>
    </form>
    <div class="table-container">
        <table id="rpciDocumentsTable">
            <thead><tr>
                <th scope="col"><i class="fas fa-hashtag"></i> RPCI No.</th>
                <th scope="col"><i class="fas fa-calendar"></i> Report Date</th>
                <th scope="col"><i class="fas fa-building"></i> Entity</th>
                <th scope="col"><i class="fas fa-user"></i> Accountable Officer</th>
                <th scope="col"><i class="fas fa-list"></i> Items</th>
                <th scope="col"><i class="fas fa-cogs"></i> Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><strong>RPCI-<?= str_pad((string)$report['rpci_id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= rpciH(date('M d, Y', strtotime($report['report_date']))) ?></td>
                    <td><?= rpciH($report['entity_name']) ?></td>
                    <td><?= rpciH($report['accountable_officer']) ?></td>
                    <td><?= (int)$report['item_count'] ?></td>
                    <td class="actions-cell">
                        <div class="actions-menu">
                            <button type="button" class="actions-menu-toggle" aria-label="Open RPCI actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="actions-menu-list">
                                <a href="office_supplies/rpci_export.php?rpci_id=<?= (int)$report['rpci_id'] ?>"><i class="fas fa-download"></i> Export</a>
                                <form method="post" action="office_supplies/rpci.php" onsubmit="return confirm('Delete this RPCI document? You can restore it from Archives.');">
                                    <input type="hidden" name="csrf" value="<?= rpciH($_SESSION['rpci_delete_csrf']) ?>">
                                    <input type="hidden" name="delete_rpci_id" value="<?= (int)$report['rpci_id'] ?>">
                                    <input type="hidden" name="search" value="<?= rpciH($search) ?>">
                                    <input type="hidden" name="sort" value="<?= rpciH($sort) ?>">
                                    <button type="submit" class="delete-action"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$reports): ?><tr><td colspan="6"><i class="fas fa-inbox"></i> <?= $search !== '' ? 'No matching RPCI documents found.' : 'No saved RPCI documents yet. Click Add RPCI Document to create one.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="js/actions_menu.js"></script>
</body>
</html>