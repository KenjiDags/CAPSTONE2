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

function regspiSourceRows(mysqli $conn, string $category): array {
    $sql = <<<'SQL'

    SELECT
        i.date_issued AS date,
        i.ics_no AS ics_rrsp_no,
        ii.stock_number AS property_no,
        COALESCE(NULLIF(ii.description, ''), sep.item_description, '') AS item_description,
        COALESCE(sep.estimated_useful_life, ii.estimated_useful_life, '') AS useful_life,
        ii.quantity AS issued_qty,
        i.received_by AS issued_office,
        0 AS returned_qty,
        '' AS returned_office,
        0 AS reissued_qty,
        '' AS reissued_office,
        0 AS disposed_qty1,
        GREATEST(0, COALESCE(sep.quantity, 0) - (COALESCE(sep.quantity_issued, 0) + COALESCE(sep.quantity_reissued, 0) + COALESCE(sep.quantity_disposed, 0))) AS balance_qty,
        COALESCE(sep.amount, ii.unit_cost) * ii.quantity AS amount_total,
        '' AS remarks,
        0 AS row_type, sep.category AS category
    FROM ics i
    INNER JOIN ics_items ii ON ii.ics_id = i.ics_id
    LEFT JOIN semi_expendable_property sep 
        ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
    WHERE ii.quantity > 0

    UNION ALL

    SELECT
        r.date_prepared AS date,
        r.rrsp_no AS ics_rrsp_no,
        rii.ics_no AS property_no,
        COALESCE(NULLIF(rii.item_description, ''), sep.item_description, '') AS item_description,
        COALESCE(sep.estimated_useful_life, '') AS useful_life,
        0 AS issued_qty,
        '' AS issued_office,
        rii.quantity AS returned_qty,
        r.returned_by AS returned_office,
        0 AS reissued_qty,
        '' AS reissued_office,
        0 AS disposed_qty1,
        GREATEST(0, COALESCE(sep.quantity, 0) - (COALESCE(sep.quantity_issued, 0) + COALESCE(sep.quantity_reissued, 0) + COALESCE(sep.quantity_disposed, 0))) AS balance_qty,
        COALESCE(sep.amount, rii.unit_cost) * rii.quantity AS amount_total,
        r.remarks AS remarks,
        1 AS row_type, sep.category AS category
    FROM rrsp r
    INNER JOIN rrsp_items rii ON rii.rrsp_id = r.rrsp_id
    LEFT JOIN semi_expendable_property sep 
        ON sep.semi_expendable_property_no = rii.ics_no COLLATE utf8mb4_general_ci
    WHERE rii.quantity > 0

    UNION ALL

    -- Imported issued stock may have no ICS header/item. Include it once as a summary.
    SELECT
        sep.date AS date,
        COALESCE(sep.ics_rrsp_no, '') AS ics_rrsp_no,
        sep.semi_expendable_property_no AS property_no,
        COALESCE(sep.item_description, '') AS item_description,
        COALESCE(sep.estimated_useful_life, '') AS useful_life,
        COALESCE(sep.quantity_issued, 0) AS issued_qty,
        COALESCE(sep.office_officer_issued, '') AS issued_office,
        COALESCE(sep.quantity_returned, 0) AS returned_qty,
        COALESCE(sep.office_officer_returned, '') AS returned_office,
        COALESCE(sep.quantity_reissued, 0) AS reissued_qty,
        COALESCE(sep.office_officer_reissued, '') AS reissued_office,
        COALESCE(sep.quantity_disposed, 0) AS disposed_qty1,
        GREATEST(0, COALESCE(sep.quantity, 0) - (COALESCE(sep.quantity_issued, 0) + COALESCE(sep.quantity_reissued, 0) + COALESCE(sep.quantity_disposed, 0))) AS balance_qty,
        COALESCE(sep.amount, 0) * COALESCE(sep.quantity_issued, 0) AS amount_total,
        COALESCE(sep.remarks, '') AS remarks,
        2 AS row_type,
        sep.category AS category
    FROM semi_expendable_property sep
    WHERE sep.quantity_issued > 0
      AND NOT EXISTS (
          SELECT 1 FROM ics_items imported_ii
          INNER JOIN ics imported_i ON imported_i.ics_id = imported_ii.ics_id
          WHERE imported_ii.quantity > 0 AND (
              imported_ii.stock_number COLLATE utf8mb4_general_ci = sep.semi_expendable_property_no
              OR imported_ii.inventory_item_no COLLATE utf8mb4_general_ci = sep.semi_expendable_property_no
          )
      )
SQL;
    // Apply category filtering to every branch, including ICS and imported stock.
    $sql = "SELECT * FROM ($sql) registry_rows WHERE (NOT EXISTS (SELECT 1 FROM iirusp_items disposed_item WHERE disposed_item.semi_expendable_property_no COLLATE utf8mb4_general_ci = registry_rows.property_no COLLATE utf8mb4_general_ci) OR EXISTS (SELECT 1 FROM semi_expendable_property serviceable WHERE serviceable.semi_expendable_property_no COLLATE utf8mb4_general_ci = registry_rows.property_no COLLATE utf8mb4_general_ci AND (COALESCE(serviceable.quantity, 0) > 0 AND (COALESCE(serviceable.quantity_balance, 0) > 0 OR COALESCE(serviceable.quantity_issued, 0) > 0 OR COALESCE(serviceable.quantity_reissued, 0) > 0))))";
    if ($category !== '') $sql .= ' AND category = ?';
    $sql .= ' ORDER BY date ASC, row_type ASC, ics_rrsp_no ASC, property_no ASC';
    $stmt = $conn->prepare($sql);
    if ($category !== '') $stmt->bind_param('s', $category);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        if (preg_match('/initial\s+(receipt|report)/i', (string)$row['remarks'])) $row['remarks'] = '';
    }
    unset($row);
    return $rows;
}

function regspiMetadata(array $input, string $category): array {
    $data = ['category' => $category];
    foreach (['report_date', 'entity_name', 'fund_cluster'] as $field) {
        if (!is_string($input[$field] ?? null)) throw new InvalidArgumentException('Enter the report date, entity name, and fund cluster.');
        $data[$field] = trim($input[$field]);
        if ($data[$field] === '' || strlen($data[$field]) > 255) throw new InvalidArgumentException('Report fields are required and must be 255 characters or fewer.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['report_date']);
    if (!$date || $date->format('Y-m-d') !== $data['report_date']) throw new InvalidArgumentException('Enter a valid report date.');
    return $data;
}

function regspiSave(mysqli $conn, array $metadata, array $rows, int $userId, string $token): int {
    if (!$rows) throw new InvalidArgumentException('There are no issued items to save.');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new InvalidArgumentException('Reload the form and try again.');
    $snapshot = json_encode(['metadata' => $metadata, 'rows' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $count = count($rows);
    $stmt = $conn->prepare('INSERT INTO regspi_reports (report_date, entity_name, fund_cluster, category, item_count, snapshot, created_by, save_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE regspi_id = LAST_INSERT_ID(regspi_id)');
    $stmt->bind_param('ssssisis', $metadata['report_date'], $metadata['entity_name'], $metadata['fund_cluster'], $metadata['category'], $count, $snapshot, $userId, $token);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function regspiLoad(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM regspi_reports WHERE regspi_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) return null;
    $report['data'] = json_decode($report['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    return $report;
}

regspiEnsureTable($conn);
$viewOnly = !empty($regspiViewMode);
$error = '';
$report = null;
$saveToken = '';
$metadata = ['report_date' => date('Y-m-d'), 'entity_name' => 'TESDA Regional Office', 'fund_cluster' => '101', 'category' => ''];
if (empty($_SESSION['regspi_csrf'])) $_SESSION['regspi_csrf'] = bin2hex(random_bytes(32));
if ($viewOnly) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Method not allowed.'); }
    $id = filter_var($_GET['regspi_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $report = $id ? regspiLoad($conn, $id) : null;
    if (!$report) { http_response_code(404); exit('RegSPI document not found.'); }
    $metadata = $report['data']['metadata'];
    $rows = $report['data']['rows'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saveToken = is_string($_POST['save_token'] ?? null) ? $_POST['save_token'] : '';
    $draft = $_SESSION['regspi_drafts'][$saveToken] ?? null;
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['regspi_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid request. Reload the form and try again.');
    }
    if (!$draft) { http_response_code(400); exit('This form expired. Return to Add RegSPI Document and reload the items.'); }
    $rows = $draft['rows'];
    $metadata['category'] = $draft['category'];
    foreach (['report_date', 'entity_name', 'fund_cluster'] as $field) {
        if (is_string($_POST[$field] ?? null)) $metadata[$field] = $_POST[$field];
    }
    try {
        $metadata = regspiMetadata($_POST, $draft['category']);
        $id = regspiSave($conn, $metadata, $rows, (int)$_SESSION['user_id'], $saveToken);
        header('Location: regspi.php?saved=' . $id);
        exit;
    } catch (InvalidArgumentException $exception) {
        http_response_code(400);
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('RegSPI save failed: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'Unable to save this document. Please try again.';
    }
} else {
    $category = is_string($_GET['category'] ?? '') ? trim($_GET['category'] ?? '') : '';
    if ($category === 'All') $category = '';
    $metadata['category'] = $category;
    $rows = regspiSourceRows($conn, $category);
    $saveToken = bin2hex(random_bytes(32));
    $_SESSION['regspi_drafts'][$saveToken] = ['category' => $category, 'rows' => $rows];
    while (count($_SESSION['regspi_drafts']) > 10) array_shift($_SESSION['regspi_drafts']);
}
$categories = [];
if (!$viewOnly) {
    $categories = $conn->query("SELECT DISTINCT category FROM semi_expendable_property WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);
}
$title = $viewOnly ? 'REGSPI-' . str_pad((string)$report['regspi_id'], 4, '0', STR_PAD_LEFT) : 'Add RegSPI Document';
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= regspiH(APP_ROOT_URL) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= regspiH($title) ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="add-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="add-ris-page content">
    <div class="form-container inventory-item-form">
        <header class="page-header">
            <h1 class="form-page-title"><i class="fas fa-file-invoice"></i><?= regspiH($title) ?></h1>
            <p class="form-page-subtitle"><?= $viewOnly ? 'View the saved registry of semi-expendable property issued' : 'Create a registry of semi-expendable property issued' ?></p>
        </header>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= regspiH($error) ?></div><?php endif; ?>
        <?php if (!$viewOnly): ?>
        <form method="get" action="Semi_Expendables/add_regspi.php" id="regspi-category-form"></form>
        <form method="post" action="Semi_Expendables/add_regspi.php" id="regspi-save-form">
            <input type="hidden" name="csrf" value="<?= regspiH($_SESSION['regspi_csrf']) ?>">
            <input type="hidden" name="save_token" value="<?= regspiH($saveToken) ?>">
            <p class="required-fields-note"><span class="required-indicator" aria-hidden="true">*</span> indicates a required field.</p>
        <?php endif; ?>
        <div class="section-card">
            <h3 class="form-section-heading"><i class="fas fa-info-circle"></i> RegSPI Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="entity_name">Entity Name: <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" id="entity_name" name="entity_name" maxlength="255" value="<?= regspiH($metadata['entity_name']) ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster: <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" id="fund_cluster" name="fund_cluster" maxlength="255" value="<?= regspiH($metadata['fund_cluster']) ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="report_date">Report Date: <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="date" id="report_date" name="report_date" value="<?= regspiH($metadata['report_date']) ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                </div>
            </div>
        </div>
        <div class="section-card">
            <h3 class="form-section-heading"><i class="fas fa-boxes-stacked"></i> RegSPI Items</h3>
            <?php if (!$viewOnly): ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="registry-category">Semi-Expendable Property:</label>
                    <select id="registry-category" name="category" form="regspi-category-form" onchange="this.form.submit()">
                        <option value="">All</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= regspiH($category['category']) ?>" <?= $metadata['category'] === $category['category'] ? 'selected' : '' ?>><?= regspiH($category['category']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            <p class="form-muted-text">Property category: <?= regspiH($metadata['category'] ?: 'All') ?> &middot; Rows: <?= count($rows) ?></p>
    <div class="table-frame"><div class="table-viewport rsep-table-wrapper">
        <table class="rsep-table compact-document-table">
            <thead>
                <tr>
                    <th rowspan="2">Date</th><th colspan="2">Reference</th>
                    <th rowspan="2">Item Description</th><th rowspan="2">Estimated Useful Life</th>
                    <th colspan="2">Issued</th><th colspan="2">Returned</th><th colspan="2">Re-issued</th>
                    <th rowspan="2">Disposed Qty.</th><th rowspan="2">Balance Qty.</th><th rowspan="2">Amount (TOTAL)</th><th rowspan="2">Remarks</th>
                </tr>
                <tr><th>ICS/RRSP No.</th><th>Semi-Expendable Property No.</th><th>Qty.</th><th>Office/Officer</th><th>Qty.</th><th>Office/Officer</th><th>Qty.</th><th>Office/Officer</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= regspiH($row['date']) ?></td>
                    <td><?= regspiH($row['ics_rrsp_no']) ?></td>
                    <td><?= regspiH($row['property_no']) ?></td>
                    <td><?= regspiH($row['item_description']) ?></td>
                    <td><?= regspiH($row['useful_life']) ?></td>
                    <td><?= (int)$row['issued_qty'] ?></td><td><?= regspiH($row['issued_office']) ?></td>
                    <td><?= (int)$row['returned_qty'] ?></td><td><?= regspiH($row['returned_office']) ?></td>
                    <td><?= (int)$row['reissued_qty'] ?></td><td><?= regspiH($row['reissued_office']) ?></td>
                    <td><?= (int)$row['disposed_qty1'] ?></td><td><?= (int)$row['balance_qty'] ?></td>
                    <td class="currency">&#8369;<?= number_format((float)$row['amount_total'], 2) ?></td>
                    <td><?= regspiH($row['remarks']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="15">No issued items found for this category.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
        </div>
        <div class="actions">
            <?php if (!$viewOnly): ?>
            <button type="submit" class="pill-btn pill-add" <?= !$rows ? 'disabled' : '' ?>><i class="fas fa-save"></i> Save Document</button>
            <a href="Semi_Expendables/regspi.php" class="pill-btn pill-view"><i class="fas fa-ban"></i> Cancel</a>
            <?php else: ?>
            <a class="pill-btn pill-export" href="Semi_Expendables/export_regspi.php?regspi_id=<?= (int)$report['regspi_id'] ?>"><i class="fas fa-file-pdf"></i> Export to PDF</a>
            <a href="Semi_Expendables/regspi.php" class="pill-btn pill-view"><i class="fas fa-arrow-left"></i> Back to Documents</a>
            <?php endif; ?>
        </div>
        <?php if (!$viewOnly): ?></form><?php endif; ?>
    </div>
</div>
<script>
const saveForm = document.getElementById('regspi-save-form');
if (saveForm) saveForm.addEventListener('submit', function () {
    const button = saveForm.querySelector('button[type="submit"]:not([form])');
    button.disabled = true;
    button.textContent = 'Saving...';
});
</script>
</body>
</html>