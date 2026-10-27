<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once __DIR__ . '/rsmi_helpers.php';
rsmiEnsureTable($conn);
if (empty($_SESSION['rsmi_delete_csrf'])) $_SESSION['rsmi_delete_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['rsmi_delete_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request. Reload the RSMI page and try again.');
    }
    $id = filter_var($_POST['delete_rsmi_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$id) throw new InvalidArgumentException('Invalid RSMI document.');
        require_once dirname(__DIR__) . '/archive_helpers.php';
        archiveRecord($conn, 'RSMI', $id);
        $_SESSION['rsmi_notice'] = ['success', 'RSMI document deleted. You can restore it from Archives.'];
    } catch (Throwable $e) {
        error_log('RSMI archive failed: ' . $e->getMessage());
        $_SESSION['rsmi_notice'] = ['error', 'Unable to delete the RSMI document. Please reload and try again.'];
    }
    header('Location: rsmi.php?' . http_build_query([
        'search' => (string)($_POST['search'] ?? ''),
        'sort' => (string)($_POST['sort'] ?? 'date_newest'),
    ]));
    exit;
}
$notice = $_SESSION['rsmi_notice'] ?? null;
unset($_SESSION['rsmi_notice']);
$search = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'date_newest');
$orders = ['date_newest' => 'report_date DESC, rsmi_id DESC', 'date_oldest' => 'report_date ASC, rsmi_id ASC',
    'serial_no' => 'serial_no ASC', 'amount_highest' => 'total_amount DESC, rsmi_id DESC'];
if (!isset($orders[$sort])) $sort = 'date_newest';
$stmt = $conn->prepare('SELECT rsmi_id, serial_no, report_date, custodian_name, accounting_staff, total_issued, total_amount, item_count, created_at FROM rsmi_reports
    WHERE serial_no LIKE ? OR custodian_name LIKE ? OR accounting_staff LIKE ? ORDER BY ' . $orders[$sort]);
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
    <title>RSMI Documents - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <h2>Report on the Stock of Materials and Supplies Issued (RSMI)</h2>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">RSMI document saved successfully.</div><?php endif; ?>
    <?php if ($notice): ?><div class="alert <?= $notice[0] === 'success' ? 'alert-success' : 'alert-error' ?>" role="status"><?= rsmiH($notice[1]) ?></div><?php endif; ?>
    <form method="get" class="filters">
        <div class="inventory-controls">
            <div class="control-sort">
                <label for="sort-select"><i class="fas fa-sort"></i> Sort By:</label>
                <select id="sort-select" name="sort" onchange="this.form.submit()">
                    <?php foreach (['date_newest' => 'Date (Newest First)', 'date_oldest' => 'Date (Oldest First)', 'serial_no' => 'RSMI No. (A-Z)', 'amount_highest' => 'Total Amount (Highest)'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="control-search">
                <label for="searchInput"><i class="fas fa-search"></i> Search:</label>
                <input type="search" id="searchInput" name="search" value="<?= rsmiH($search) ?>" placeholder="Search RSMI no. or signatories...">
            </div>
            <div class="control-actions">
                <a href="office_supplies/add_rsmi.php" class="pill-btn pill-add"><i class="fas fa-plus"></i> Add RSMI Document</a>
            </div>
        </div>
    </form>
    <div class="table-container">
        <table id="rsmiTable">
            <thead>
                <tr>
                    <th scope="col"><i class="fas fa-hashtag" aria-hidden="true"></i> RSMI No.</th>
                    <th scope="col"><i class="fas fa-calendar" aria-hidden="true"></i> Report Date</th>
                    <th scope="col"><i class="fas fa-user" aria-hidden="true"></i> Custodian</th>
                    <th scope="col"><i class="fas fa-user-tie" aria-hidden="true"></i> Accounting Staff</th>
                    <th scope="col"><i class="fas fa-list" aria-hidden="true"></i> Entries</th>
                    <th scope="col"><i class="fas fa-boxes" aria-hidden="true"></i> Quantity Issued</th>
                    <th scope="col"><i class="fas fa-coins" aria-hidden="true"></i> Total Amount</th>
                    <th scope="col"><i class="fas fa-cogs" aria-hidden="true"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><strong><?= rsmiH($report['serial_no']) ?></strong></td>
                    <td><?= rsmiH(date('M d, Y', strtotime($report['report_date']))) ?></td>
                    <td><?= rsmiH($report['custodian_name']) ?></td>
                    <td><?= rsmiH($report['accounting_staff']) ?></td>
                    <td><?= (int)$report['item_count'] ?></td>
                    <td><?= rsmiH((float)$report['total_issued']) ?></td>
                    <td><span class="currency">&#8369; <?= number_format((float)$report['total_amount'], 2) ?></span></td>
                    <td class="actions-cell">
                        <div class="actions-menu">
                            <button type="button" class="actions-menu-toggle" aria-label="Open actions for <?= rsmiH($report['serial_no']) ?>" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="actions-menu-list">
                                <a href="office_supplies/rsmi_export.php?rsmi_id=<?= (int)$report['rsmi_id'] ?>" class="export-action"><i class="fas fa-download"></i> Export</a>
                                <form method="post" action="office_supplies/rsmi.php" onsubmit="return confirm('Delete this RSMI document? You can restore it from Archives.');">
                                    <input type="hidden" name="csrf" value="<?= rsmiH($_SESSION['rsmi_delete_csrf']) ?>">
                                    <input type="hidden" name="delete_rsmi_id" value="<?= (int)$report['rsmi_id'] ?>">
                                    <input type="hidden" name="search" value="<?= rsmiH($search) ?>">
                                    <input type="hidden" name="sort" value="<?= rsmiH($sort) ?>">
                                    <button type="submit" class="delete-action"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reports): ?><tr><td colspan="8"><i class="fas fa-inbox"></i> <?= $search !== '' ? 'No matching RSMI documents found.' : 'No saved RSMI documents yet. Click Add RSMI Document to create one.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="js/actions_menu.js"></script>
</body>
</html>
