<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
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
function rpcspH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$conn->query(rpcspSchema());
function rpcspValidateMetadata(array $input): array {
    $data = [];
    foreach (['report_date', 'fund_cluster', 'accountable_officer', 'official_designation', 'entity_name', 'assumption_date', 'signature_name_1', 'signature_name_2', 'signature_name_3'] as $field) {
        if (!is_string($input[$field] ?? '')) throw new InvalidArgumentException('Invalid report details.');
        $data[$field] = trim($input[$field] ?? '');
        if (strlen($data[$field]) > 255) throw new InvalidArgumentException('Report fields must be 255 characters or fewer.');
    }
    if ($data['entity_name'] === '') throw new InvalidArgumentException('Enter the entity name.');
    foreach (['report_date', 'assumption_date'] as $field) {
        if ($field === 'assumption_date' && $data[$field] === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]);
        if (!$date || $date->format('Y-m-d') !== $data[$field]) throw new InvalidArgumentException('Enter valid report and assumption dates.');
    }
    return $data;
}
function rpcspValidateCounts(array $rows, $encoded): array {
    if (!is_string($encoded)) throw new InvalidArgumentException('Invalid item counts.');
    try { $input = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new InvalidArgumentException('Invalid item counts.'); }
    if (!is_array($input)) throw new InvalidArgumentException('Invalid item counts.');
    $counts = [];
    foreach (['on_hand_count', 'shortage_qty', 'shortage_value'] as $field) {
        if (!is_array($input[$field] ?? [])) throw new InvalidArgumentException('Invalid item counts.');
        $counts[$field] = [];
        foreach ($rows as $row) {
            $value = $input[$field][$row['id']] ?? '';
            if (!is_string($value)) throw new InvalidArgumentException('Invalid item count.');
            $value = trim($value);
            $pattern = $field === 'shortage_value' ? '/^-?\d{1,12}(\.\d{1,2})?$/D' : '/^-?\d{1,12}$/D';
            if ($value !== '' && (!preg_match($pattern, $value) || ($field === 'on_hand_count' && (float)$value < 0))) {
                throw new InvalidArgumentException('Enter whole quantities, nonnegative on-hand counts, and monetary values with at most two decimals.');
            }
            $counts[$field][$row['id']] = $value;
        }
    }
    // Derive the difference from the trusted draft balance rather than posted totals.
    foreach ($rows as $row) {
        $onHand = $counts['on_hand_count'][$row['id']];
        $counts['shortage_qty'][$row['id']] = $onHand === '' ? ''
            : (string)((int)$onHand - (int)($row['quantity_balance'] ?? 0));
    }
    return $counts;
}
function rpcspSave(mysqli $conn, array $metadata, array $items, array $counts, int $userId, string $token): int {
    if (!$items) throw new InvalidArgumentException('There are no semi-expendable items to save.');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new InvalidArgumentException('Reload the form and try again.');
    $snapshot = json_encode(['metadata' => $metadata, 'rows' => $items, 'counts' => $counts], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $count = count($items);
    $stmt = $conn->prepare('INSERT INTO rpcsp_reports (report_date, entity_name, accountable_officer, item_count, snapshot, created_by, save_token) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rpcsp_id = LAST_INSERT_ID(rpcsp_id)');
    $stmt->bind_param('sssisis', $metadata['report_date'], $metadata['entity_name'], $metadata['accountable_officer'], $count, $snapshot, $userId, $token);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}
$viewOnly = !empty($rpcspViewMode);
$error = '';
$saveToken = '';
$report = null;
$counts = ['on_hand_count' => [], 'shortage_qty' => [], 'shortage_value' => []];
$metadata = ['report_date' => date('Y-m-d'), 'fund_cluster' => '', 'accountable_officer' => '', 'official_designation' => '', 'entity_name' => 'TESDA Regional Office', 'assumption_date' => '', 'signature_name_1' => '', 'signature_name_2' => '', 'signature_name_3' => ''];
if (empty($_SESSION['rpcsp_csrf'])) $_SESSION['rpcsp_csrf'] = bin2hex(random_bytes(32));
if ($viewOnly) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Method not allowed.'); }
    $id = filter_var($_GET['rpcsp_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { http_response_code(404); exit('RPCSP document not found.'); }
    $stmt = $conn->prepare('SELECT * FROM rpcsp_reports WHERE rpcsp_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) { http_response_code(404); exit('RPCSP document not found.'); }
    $data = json_decode($report['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    $items = $data['rows'];
    $metadata = $data['metadata'];
    $counts = $data['counts'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['rpcsp_csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Invalid request. Reload the form and try again.');
    }
    $saveToken = is_string($_POST['save_token'] ?? null) ? $_POST['save_token'] : '';
    $items = $_SESSION['rpcsp_drafts'][$saveToken] ?? null;
    if ($items === null) { http_response_code(400); exit('This form expired. Return to Add RPCSP Document to reload the items.'); }
    foreach ($metadata as $field => $value) {
        if (is_string($_POST[$field] ?? null)) $metadata[$field] = $_POST[$field];
    }
    try {
        $counts = rpcspValidateCounts($items, $_POST['row_data'] ?? '');
        $metadata = rpcspValidateMetadata($_POST);
        $id = rpcspSave($conn, $metadata, $items, $counts, (int)$_SESSION['user_id'], $saveToken);
        header('Location: rpcsp.php?saved=' . $id); exit;
    } catch (InvalidArgumentException $exception) {
        http_response_code(400); $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('RPCSP save failed: ' . $exception->getMessage());
        http_response_code(500); $error = 'Unable to save this document. Please try again.';
    }
} else {
    $items = $conn->query('SELECT id, category, item_description, semi_expendable_property_no, unit, amount, quantity_balance, remarks FROM semi_expendable_property WHERE (NOT EXISTS (SELECT 1 FROM iirusp_items disposed_item WHERE disposed_item.semi_expendable_property_no COLLATE utf8mb4_general_ci = semi_expendable_property.semi_expendable_property_no COLLATE utf8mb4_general_ci) OR (COALESCE(semi_expendable_property.quantity, 0) > 0 AND (COALESCE(semi_expendable_property.quantity_balance, 0) > 0 OR COALESCE(semi_expendable_property.quantity_issued, 0) > 0 OR COALESCE(semi_expendable_property.quantity_reissued, 0) > 0))) ORDER BY item_description, id')->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare('SELECT user_full_name, user_position FROM users WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $metadata['accountable_officer'] = $user['user_full_name'] ?? '';
    $metadata['official_designation'] = $user['user_position'] ?? '';
    $saveToken = bin2hex(random_bytes(32));
    $_SESSION['rpcsp_drafts'][$saveToken] = $items;
    while (count($_SESSION['rpcsp_drafts']) > 10) array_shift($_SESSION['rpcsp_drafts']);
}
$officer_names = [];
if (!$viewOnly) {
    $result = $conn->query('SELECT officer_name FROM officers ORDER BY officer_name');
    while ($row = $result->fetch_assoc()) $officer_names[] = $row['officer_name'];
}
$officer_names_json = json_encode($officer_names, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$title = $viewOnly ? 'RPCSP-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT) : 'Add RPCSP Document';
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= rpcspH(APP_ROOT_URL) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= rpcspH($title) ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="add-page item-entry-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="add-ris-page content">
<div class="form-container inventory-item-form">
    <header class="page-header">
        <h1 class="form-page-title"><i class="fas fa-clipboard-list"></i><?= rpcspH($title) ?></h1>
        <p class="form-page-subtitle">Report on the Physical Count of Semi-Expendable Property</p>
    </header>
    <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= rpcspH($error) ?></div><?php endif; ?>
    <?php if (!$viewOnly): ?>
    <form method="post" action="Semi_Expendables/add_rpcsp.php" id="rpcsp-save-form">
        <input type="hidden" name="csrf" value="<?= rpcspH($_SESSION['rpcsp_csrf']) ?>">
        <input type="hidden" name="save_token" value="<?= rpcspH($saveToken) ?>">
        <input type="hidden" name="row_data" id="rpcsp-row-data">
        <p class="required-fields-note"><span class="required-indicator" aria-hidden="true">*</span> indicates a required field.</p>
    <?php endif; ?>
    <div class="section-card">
        <h3 class="form-section-heading"><i class="fas fa-info-circle"></i> RPCSP Details</h3>
        <div class="form-grid">
            <?php foreach (['entity_name' => 'Entity Name', 'fund_cluster' => 'Fund Cluster', 'report_date' => 'As at', 'accountable_officer' => 'Accountable Officer', 'official_designation' => 'Official Designation', 'assumption_date' => 'Accountability Assumption Date'] as $field => $label): ?>
            <div class="form-group">
                <label for="<?= $field ?>"><?= $label ?>: <?php if (in_array($field, ['entity_name', 'report_date'], true)): ?><span class="required-indicator" aria-hidden="true">*</span><?php endif; ?></label>
                <input type="<?= in_array($field, ['report_date', 'assumption_date'], true) ? 'date' : 'text' ?>" id="<?= $field ?>" name="<?= $field ?>" value="<?= rpcspH($metadata[$field]) ?>" maxlength="255" <?= in_array($field, ['entity_name', 'report_date'], true) ? 'required' : '' ?> <?= $viewOnly ? 'readonly' : '' ?>>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="section-card">
        <h3 class="form-section-heading"><i class="fas fa-boxes-stacked"></i> RPCSP Items</h3>
        <div class="table-controls">
            <label for="row_limit">Show:</label>
            <select id="row_limit" aria-label="Rows to display"><option value="5">5</option><option value="10" selected>10</option><option value="25">25</option><option value="all">All</option></select>
        </div>
        <div class="search-container table-controls count-search-toolbar">
            <label class="compact-search-field" for="searchInput"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="searchInput" class="search-input" aria-label="Search items" placeholder="Search by property no., category, or description..."></label>
            <?php if (!$viewOnly): ?>
            <div class="button-tooltip">
                <button type="button" id="match-balance" class="pill-btn pill-view" aria-describedby="match-balance-tooltip"><i class="fas fa-equals" aria-hidden="true"></i> Match Balance</button>
                <span class="button-tooltip-text" id="match-balance-tooltip" role="tooltip">Set the counted quantity equal to the recorded balance</span>
            </div>
            <?php endif; ?>
        </div>
            <div class="rpci-table-wrapper">
                <table class="rpci-table" id="rpci-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Article</th>
                            <th rowspan="2">Item</th>
                            <th rowspan="2">Description</th>
                            <th rowspan="2">Property Number</th>
                            <th rowspan="2">Unit of Measure</th>
                            <th rowspan="2">Unit Value</th>
                            <th rowspan="2">Balance Per Card<br>(Quantity)</th>
                            <th rowspan="2">On Hand Per Count<br>(Quantity)</th>
                            <th colspan="2">Shortage/Overage</th>
                            <th rowspan="2">Remarks</th>
                        </tr>
                        <tr>
                            <th>Quantity</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="11" style="text-align:center; padding: 32px; color: var(--text-gray); font-style: italic;">No semi-expendable items found</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $row): ?>
                                <tr>
                                    <td>Semi-Expendable</td>
                                    <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
                                    <td class="text-left"><?= htmlspecialchars($row['item_description'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['semi_expendable_property_no'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['unit'] ?? '') ?></td>
                                    <td class="currency">₱ <?= htmlspecialchars(isset($row['amount']) ? number_format((float)$row['amount'], 2) : '') ?></td>
                                    <td><?= htmlspecialchars((string)($row['quantity_balance'] ?? '')) ?></td>
                                    <td><input type="number" class="count-input" name="on_hand_count[<?= (int)$row['id'] ?>]" data-recorded-balance="<?= (int)($row['quantity_balance'] ?? 0) ?>" value="<?= rpcspH($counts['on_hand_count'][$row['id']] ?? '') ?>" <?= $viewOnly ? 'readonly' : '' ?> min="0" step="1" aria-label="On Hand Per Count"></td>
                                    <td><input type="number" class="count-input" name="shortage_qty[<?= (int)$row['id'] ?>]" value="<?= rpcspH($counts['shortage_qty'][$row['id']] ?? '') ?>" readonly step="1" aria-label="Shortage or Overage Quantity"></td>
                                    <td><input type="number" class="count-input currency" name="shortage_value[<?= (int)$row['id'] ?>]" value="<?= rpcspH($counts['shortage_value'][$row['id']] ?? '') ?>" <?= $viewOnly ? 'readonly' : '' ?> step="0.01" aria-label="Shortage or Overage Value"></td>
                                    <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </div>
    <div class="signature-section rpcsp-signatures">
        <?php foreach ([
            'signature_name_1' => ['Certified Correct by', 'Signature over Printed Name of Inventory<br>Committee Chair and Members'],
            'signature_name_2' => ['Approved by', 'Signature over Printed Name of Head of Agency/Entity<br>or Authorized Representative'],
            'signature_name_3' => ['Verified by', 'Signature over Printed Name of COA Representative']
        ] as $field => [$label, $description]): ?>
        <div class="signature-box">
            <h4 id="<?= $field ?>_label"><?= $label ?>:</h4>
            <div class="officer-name-field">
                <input type="text" class="signature-input" id="<?= $field ?>" name="<?= $field ?>" value="<?= rpcspH($metadata[$field]) ?>" maxlength="255" placeholder="Signature over Printed Name" aria-labelledby="<?= $field ?>_label" autocomplete="off" <?= $viewOnly ? 'readonly' : '' ?>>
                <?php if (!$viewOnly): ?><div id="<?= $field ?>_dropdown" class="autocomplete-dropdown"></div><?php endif; ?>
            </div>
            <div class="signature-text"><?= $description ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="actions">
        <?php if (!$viewOnly): ?>
        <button type="submit" id="save-rpcsp" class="pill-btn pill-add" <?= !$items ? 'disabled' : '' ?>><i class="fas fa-save"></i> Save Document</button>
        <a href="Semi_Expendables/rpcsp.php" class="pill-btn pill-view"><i class="fas fa-ban"></i> Cancel</a>
        <?php else: ?>
        <a href="Semi_Expendables/rpcsp_export.php?rpcsp_id=<?= (int)$id ?>" class="pill-btn pill-export"><i class="fas fa-file-pdf"></i> Export to PDF</a>
        <a href="Semi_Expendables/rpcsp.php" class="pill-btn pill-view"><i class="fas fa-arrow-left"></i> Back to Documents</a>
        <?php endif; ?>
    </div>
    <?php if (!$viewOnly): ?></form><?php endif; ?>
</div>
</div>
<?php require dirname(__DIR__) . '/officer_dropdown_options.php'; ?>
<script>
// Keep the displayed quantity difference in sync, including Match Balance.
if (document.getElementById('rpcsp-save-form')) {
    document.querySelectorAll('#rpci-table input[data-recorded-balance]').forEach(input => {
        const difference = input.closest('tr').querySelector('input[name^="shortage_qty["]');
        function updateDifference() {
            difference.value = input.value === '' || !input.validity.valid ? ''
                : String(Number(input.value) - Number(input.dataset.recordedBalance));
        }
        input.addEventListener('input', updateDifference);
        input.addEventListener('change', updateDifference);
        updateDifference();
    });
}
const matchBalanceButton = document.getElementById('match-balance');
if (matchBalanceButton) matchBalanceButton.addEventListener('click', function () {
    document.querySelectorAll('#rpci-table input[data-recorded-balance]').forEach(input => {
        if (input.readOnly) return;
        input.value = input.dataset.recordedBalance;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
});
const rpcspForm = document.getElementById('rpcsp-save-form');
if (rpcspForm) rpcspForm.addEventListener('submit', function () {
    const counts = {on_hand_count: {}, shortage_qty: {}, shortage_value: {}};
    rpcspForm.querySelectorAll('#rpci-table input[name]').forEach(input => {
        const match = input.name.match(/^(on_hand_count|shortage_qty|shortage_value)\[(\d+)\]$/);
        if (match) counts[match[1]][match[2]] = input.value;
    });
    document.getElementById('rpcsp-row-data').value = JSON.stringify(counts);
    // Counts travel in one JSON field so large reports are not truncated by max_input_vars.
    rpcspForm.querySelectorAll('#rpci-table input[name]').forEach(input => input.removeAttribute('name'));
    const button = document.getElementById('save-rpcsp');
    button.disabled = true;
    button.textContent = 'Saving...';
});
</script><script>


        document.addEventListener('DOMContentLoaded', function() {
            setupAutocomplete('signature_name_1', 'signature_name_1_dropdown');
            setupAutocomplete('signature_name_2', 'signature_name_2_dropdown');
            setupAutocomplete('signature_name_3', 'signature_name_3_dropdown');
        });

    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        const wrapper = document.querySelector('.rpci-table-wrapper');
        const table = document.querySelector('.rpci-table');
        const thead = table.querySelector('thead');

        function applyLimit() {
            const val = limitSelect.value;
            const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])');
            if (!wrapper || !thead || !sampleRow) return;
            const headerHeight = thead.getBoundingClientRect().height;
            const rowHeight = sampleRow.getBoundingClientRect().height;
            if (val === 'all') { wrapper.style.maxHeight = 'none'; }
            else { wrapper.style.maxHeight = `${headerHeight + rowHeight * parseInt(val,10)}px`; }
        }

        limitSelect.addEventListener('change', applyLimit);
        applyLimit();
        window.addEventListener('resize', applyLimit);
    });

    // Searchbar JS (matches rpci behavior)
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rpci-table tbody tr');

        rows.forEach(row => {
            const category = (row.cells[1]?.textContent || '').toLowerCase();
            const desc = (row.cells[2]?.textContent || '').toLowerCase();
            const propno = (row.cells[3]?.textContent || '').toLowerCase();
            const unit = (row.cells[4]?.textContent || '').toLowerCase();

            const match = category.includes(filter) || desc.includes(filter) || propno.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
    </script>
</body>
</html>
