<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/par_helpers.php';
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$profile = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(user_full_name), ''), full_name, '') AS full_name, COALESCE(user_position, '') AS position FROM users WHERE user_id = ?");
$profile->bind_param('i', $_SESSION['user_id']);
$profile->execute();
$user = $profile->get_result()->fetch_assoc();
$profile->close();
$values = ['entity_name' => 'TESDA Regional Office', 'fund_cluster' => '101', 'received_by' => '', 'received_by_designation' => '', 'received_by_date' => $today, 'issued_by' => $user['full_name'] ?? '', 'issued_by_designation' => $user['position'] ?? '', 'issued_by_date' => $today];
$error = '';
$selected = [];
if (empty($_SESSION['ppe_par_csrf'])) $_SESSION['ppe_par_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['ppe_par_csrf'], $_POST['csrf'])) throw new InvalidArgumentException('Your form expired. Please try again.');
        foreach ($values as $field => $default) {
            if (!is_string($_POST[$field] ?? null)) throw new InvalidArgumentException('Please complete all required fields.');
            $values[$field] = trim($_POST[$field]);
            $limit = $field === 'fund_cluster' ? 100 : 255;
            if ($values[$field] === '' || mb_strlen($values[$field]) > $limit) throw new InvalidArgumentException('Please complete all required fields within their length limits.');
        }
        foreach (['received_by_date', 'issued_by_date'] as $field) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values[$field]);
            if (!$date || $date->format('Y-m-d') !== $values[$field]) throw new InvalidArgumentException('Enter valid signature dates.');
        }
        if (!is_array($_POST['item_ids'] ?? null)) throw new InvalidArgumentException('Select at least one available property.');
        foreach ($_POST['item_ids'] as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) throw new InvalidArgumentException('Invalid property selection.');
            $selected[] = (int)$id;
        }
        $selected = array_values(array_unique($selected));
        $conn->begin_transaction();
        // The unique PAR number protects concurrent saves; retry collisions.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $prefix = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('y-m-');
            $numberStmt = $conn->prepare('SELECT par_no FROM ppe_par WHERE par_no LIKE ?');
            $pattern = $prefix . '%';
            $numberStmt->bind_param('s', $pattern);
            $numberStmt->execute();
            $sequence = 0;
            foreach ($numberStmt->get_result() as $row) {
                $suffix = substr($row['par_no'], strlen($prefix));
                if (ctype_digit($suffix)) $sequence = max($sequence, (int)$suffix);
            }
            $numberStmt->close();
            $values['par_no'] = $prefix . str_pad((string)($sequence + 1), 2, '0', STR_PAD_LEFT);
            try {
                ppeSavePar($conn, $values, $selected);
                $conn->commit();
                $_SESSION['ppe_par_csrf'] = bin2hex(random_bytes(32));
                header('Location: ' . APP_ROOT_URL . 'PPE/PPE_PAR.php?added=1', true, 303);
                exit;
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                if ((int)$exception->getCode() !== 1062 || $attempt === 2) throw $exception;
                $conn->begin_transaction();
            }
        }
    } catch (Throwable $exception) {
        $conn->rollback();
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to save the PAR. Please try again.';
        if (!($exception instanceof InvalidArgumentException)) error_log('PAR save failed: ' . $exception->getMessage());
    }
}
$available = [];
$result = $conn->query('SELECT p.*, EXISTS(SELECT 1 FROM ppe_par_items pi WHERE pi.ppe_id = p.id) OR EXISTS(SELECT 1 FROM ppe_ptr_items ti WHERE ti.ppe_id = p.id) AS has_assignment FROM ppe_property p ORDER BY item_name, PPE_no');
foreach ($result as $item) if (ppeParEligible($item)) $available[] = $item;
$officers = $conn->query('SELECT officer_name, officer_position FROM officers ORDER BY officer_name')->fetch_all(MYSQLI_ASSOC);
function parEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en"><head>
<base href="<?= parEscape(APP_ROOT_URL) ?>">
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Issue PPE — Add PAR</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body class="add-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<main class="container"><div class="form-container">
<header class="page-header"><h1 class="form-page-title"><i class="fas fa-file-invoice" aria-hidden="true"></i> Issue PPE — Add PAR</h1><p class="form-page-subtitle">Create a Property Acknowledgment Receipt and assign properties to an end user.</p></header>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= parEscape($error) ?></div><?php endif; ?>
<form method="post" class="inventory-item-form">
<input type="hidden" name="csrf" value="<?= parEscape($_SESSION['ppe_par_csrf']) ?>">
<p class="required-fields-note"><span class="required-indicator">*</span> indicates a required field.</p>
<section class="section-card"><h3 class="form-section-heading">Receipt details</h3><div class="form-grid">
<?php foreach (['entity_name' => 'Entity Name', 'fund_cluster' => 'Fund Cluster'] as $field => $label): ?>
<div class="form-group"><label for="<?= $field ?>"><?= $label ?> <span class="required-indicator">*</span></label><input id="<?= $field ?>" name="<?= $field ?>" maxlength="<?= $field === 'fund_cluster' ? 100 : 255 ?>" value="<?= parEscape($values[$field]) ?>" required></div>
<?php endforeach; ?></div></section>
<section class="section-card"><h3 class="form-section-heading">Select properties</h3>
<label class="compact-search-field compact-search-field-short" for="parItemSearch"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="parItemSearch" class="search-input" aria-label="Search available properties" placeholder="Search PPE number, item, or description..."></label>
<div class="table-frame"><div class="table-viewport"><table class="scrollable-document-table"><thead><tr><th>Select</th><th>PPE No.</th><th>Item / Description</th><th>Quantity</th><th>Unit</th><th>Amount</th></tr></thead><tbody>
<?php foreach ($available as $item): ?><tr data-par-item><td><input type="checkbox" name="item_ids[]" value="<?= (int)$item['id'] ?>" aria-label="Select <?= parEscape($item['PPE_no']) ?>" <?= in_array((int)$item['id'], $selected, true) ? 'checked' : '' ?>></td><td><?= parEscape($item['PPE_no']) ?></td><td><strong><?= parEscape($item['item_name']) ?></strong><br><?= parEscape($item['item_description']) ?></td><td><?= number_format($item['quantity']) ?></td><td><?= parEscape($item['unit']) ?></td><td class="currency">₱<?= number_format($item['amount'], 2) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<p class="form-page-subtitle" id="parItemsEmpty" <?= $available ? 'hidden' : '' ?>>No available properties<?= $available ? ' match your search' : ' to issue' ?>.</p><p class="form-page-subtitle" id="parSelectionCount" role="status"></p>
</section>
<div class="form-grid">
<?php foreach (['received' => 'Received by', 'issued' => 'Issued by'] as $prefix => $title): ?>
<section class="section-card"><h3 class="form-section-heading"><?= $title ?></h3>
<?php foreach (['_by' => 'Full name', '_by_designation' => 'Position / Office', '_by_date' => 'Date'] as $suffix => $label): $field = $prefix . $suffix; ?>
<div class="form-group"><label for="<?= $field ?>"><?= $label ?> <span class="required-indicator">*</span></label>
<?php if ($field === 'received_by'): ?>
<div class="officer-name-field"><input type="text" id="received_by" name="received_by" data-officer-autocomplete data-designation-target="received_by_designation" autocomplete="off" maxlength="255" value="<?= parEscape($values[$field]) ?>" required><div id="received_by_dropdown" class="autocomplete-dropdown"></div></div>
<?php else: ?>
<input type="<?= $suffix === '_by_date' ? 'date' : 'text' ?>" id="<?= $field ?>" name="<?= $field ?>" <?= $suffix === '_by' ? 'list="parOfficers"' : '' ?> maxlength="255" value="<?= parEscape($values[$field]) ?>" required>
<?php endif; ?></div>
<?php endforeach; ?></section><?php endforeach; ?>
</div>
<datalist id="parOfficers"><?php foreach ($officers as $officer): ?><option value="<?= parEscape($officer['officer_name']) ?>"><?= parEscape($officer['officer_position']) ?></option><?php endforeach; ?></datalist>
<div class="button-group"><button type="submit" class="btn btn-primary" <?= !$available ? 'disabled' : '' ?>>Save PAR &amp; Issue Items</button><a class="btn btn-secondary" href="PPE/PPE_PAR.php">Cancel</a></div>
</form></div></main>
<?php require dirname(__DIR__) . '/officer_dropdown_options.php'; ?>
<script>
const parRows = [...document.querySelectorAll('[data-par-item]')];
document.getElementById('parItemSearch').addEventListener('input', event => {
  document.getElementById('parItemsEmpty').hidden = inventoryControls.filterRows(parRows, event.target.value) > 0;
});
function updateParCount() {
  document.getElementById('parSelectionCount').textContent = document.querySelectorAll('[name="item_ids[]"]:checked').length + ' properties selected';
}
document.querySelectorAll('[name="item_ids[]"]').forEach(input => input.addEventListener('change', updateParCount));
updateParCount();
document.querySelector('form.inventory-item-form').addEventListener('submit', event => {
  if (!document.querySelector('[name="item_ids[]"]:checked')) { event.preventDefault(); document.getElementById('parSelectionCount').textContent = 'Select at least one property to issue.'; }
});
</script>
</body></html>
