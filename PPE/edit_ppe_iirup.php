<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    header('Location: PPE_iirup.php');
    exit;
}

$fromList = ($_GET['from'] ?? '') === 'list';
$editQuery = ['id' => $id];
$returnPath = "view_ppe_iirup.php?id=$id";
if ($fromList) {
    $filters = array_filter(
        array_intersect_key($_GET, array_flip(['sort', 'search'])),
        'is_string'
    );
    $editQuery += ['from' => 'list'] + $filters;
    $returnPath = 'PPE_iirup.php' . ($filters ? '?' . http_build_query($filters) : '');
}

function iirupEditInput(array $source, string $field, int $maxLength = 255): string
{
    $value = $source[$field] ?? null;
    if (!is_string($value) || mb_strlen($value) > $maxLength) {
        throw new DomainException('Check the form for missing or overly long values.');
    }
    return trim($value);
}

function iirupEditEscape($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$headerSections = [
    'Report Details' => ['date_reported' => 'Date Reported', 'entity_name' => 'Entity Name'],
    'Accountable Officer' => [
        'accountable_officer_name' => 'Name', 'accountable_officer_designation' => 'Designation',
        'accountable_officer_station' => 'Station',
    ],
    'Signatories' => [
        'requested_by_name' => 'Requested By', 'requested_by_designation' => 'Requested By Designation',
        'requested_by_station' => 'Requested By Station', 'approved_by_name' => 'Approved By',
        'approved_by_designation' => 'Approved By Designation', 'inspection_officer_name' => 'Inspection Officer',
        'inspection_officer_designation' => 'Inspection Officer Designation', 'witness_name' => 'Witness',
        'witness_designation' => 'Witness Designation',
    ],
];
$moneyFields = [
    'depreciation' => 'Depreciation', 'impairment_loss' => 'Impairment Loss',
    'carrying_amount' => 'Carrying Amount', 'appraised_value' => 'Appraised Value',
    'sale_amount' => 'Sale Amount',
];
$modes = ['sale' => 'Sale Qty', 'transfer' => 'Transfer Qty', 'destruction' => 'Destruction Qty', 'other' => 'Other Qty'];

$saving = $_SERVER['REQUEST_METHOD'] === 'POST';
$_SESSION['ppe_iirup_edit_token'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['ppe_iirup_edit_token'];
$error = '';
$submittedItems = [];
$submittedHeader = [];
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($saving) {
    $conn->begin_transaction();
}

try {
    $lock = $saving ? ' FOR UPDATE' : '';
    $stmt = $conn->prepare("SELECT * FROM ppe_iirup WHERE id = ?$lock");
    $stmt->execute([$id]);
    $report = $stmt->get_result()->fetch_assoc();
    if (!$report) {
        if ($saving) {
            $conn->rollback();
        }
        http_response_code(404);
        exit('PPE IIRUP record not found.');
    }

    $stmt = $conn->prepare("SELECT * FROM ppe_iirup_items WHERE ppe_iirup_id = ? ORDER BY id$lock");
    $stmt->execute([$id]);
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $revision = hash('sha256', serialize([$report, $items]));

    if ($saving) {
        if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($token, $_POST['csrf_token'])) {
            throw new DomainException('The request could not be verified. Reload the page and try again.');
        }
        if (!is_string($_POST['revision'] ?? null) || !hash_equals($revision, $_POST['revision'])) {
            throw new DomainException('This report changed after you opened it. Review the latest values and try again.');
        }

        $submittedHeader = $_POST;
        $submittedItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $headerValues = [];
        foreach ($headerSections as $fields) {
            foreach ($fields as $field => $label) {
                $headerValues[$field] = iirupEditInput($submittedHeader, $field);
            }
        }
        $date = $headerValues['date_reported'];
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            throw new DomainException('Enter a valid date reported.');
        }

        if (count($submittedItems) !== count($items)) {
            throw new DomainException('Reload the report and submit all of its item lines.');
        }
        if ($items) {
            $updateItem = $conn->prepare(
                'UPDATE ppe_iirup_items SET depreciation = ?, impairment_loss = ?, carrying_amount = ?,
                 appraised_value = ?, sale_amount = ?, sale = ?, transfer = ?, destruction = ?, other = ?,
                 remarks = ?, or_no = ?, other_specification = ?, total = quantity WHERE id = ? AND ppe_iirup_id = ?'
            );
            foreach ($items as $item) {
                $input = $submittedItems[$item['id']] ?? null;
                if (!is_array($input)) {
                    throw new DomainException('Reload the report and submit all of its item lines.');
                }
                $allocations = [];
                foreach ($modes as $field => $label) {
                    $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($value === false || $value > (int)$item['quantity']) {
                        throw new DomainException('Disposal allocations must be whole numbers within the recorded quantity.');
                    }
                    $allocations[$field] = $value;
                }
                if (array_sum($allocations) !== (int)$item['quantity']) {
                    throw new DomainException('The disposal allocations for each line must add up to its recorded quantity.');
                }
                $amounts = [];
                foreach ($moneyFields as $field => $label) {
                    $value = iirupEditInput($input, $field, 16);
                    if (!preg_match('/^[0-9]{1,13}(\.[0-9]{1,2})?$/D', $value)) {
                        throw new DomainException("$label must be a non-negative amount with up to two decimal places.");
                    }
                    $amounts[$field] = $value;
                }
                $orNo = $allocations['sale'] ? iirupEditInput($input, 'or_no', 100) : '';
                $other = $allocations['other'] ? iirupEditInput($input, 'other_specification') : '';
                if (!$allocations['sale']) {
                    $amounts['sale_amount'] = '0';
                }
                $updateItem->execute([
                    ...array_values($amounts), ...array_values($allocations),
                    iirupEditInput($input, 'remarks', 65535), $orNo, $other, $item['id'], $id,
                ]);
            }

            $stmt = $conn->prepare(
                'SELECT SUM(quantity) AS quantity, SUM(depreciation) AS depreciation,
                 SUM(impairment_loss) AS impairment_loss, SUM(carrying_amount) AS carrying_amount,
                 SUM(sale) AS sale, SUM(transfer) AS transfer, SUM(destruction) AS destruction,
                 SUM(other) AS other, SUM(quantity) AS total, SUM(appraised_value) AS appraised_value,
                 SUM(sale_amount) AS amount, COALESCE(SUM(amount), 0) AS total_cost,
                 CASE WHEN COUNT(*) = 1 THEN MAX(or_no) ELSE \'\' END AS or_no
                 FROM ppe_iirup_items WHERE ppe_iirup_id = ?'
            );
            $stmt->execute([$id]);
            $headerValues += $stmt->get_result()->fetch_assoc();
            $stmt = $conn->prepare('SELECT PPE_no, particulars, remarks FROM ppe_iirup_items WHERE id = ?');
            $stmt->execute([$items[0]['id']]);
            $headerValues += $stmt->get_result()->fetch_assoc();
        }

        $assignments = implode(', ', array_map(static fn($field) => "`$field` = ?", array_keys($headerValues)));
        $stmt = $conn->prepare("UPDATE ppe_iirup SET $assignments WHERE id = ?");
        $stmt->execute([...array_values($headerValues), $id]);
        $conn->commit();
        if ($fromList) {
            $_SESSION['ppe_iirup_saved']['list'] = $id;
        } else {
            $_SESSION['ppe_iirup_saved']['detail'][$id] = true;
        }
        header('Location: ' . $returnPath, true, 303);
        exit;
    }
} catch (Throwable $exception) {
    if ($saving) {
        $conn->rollback();
    }
    if ($exception instanceof DomainException) {
        $error = $exception->getMessage();
    } else {
        error_log($exception->getMessage());
        http_response_code(500);
        if (!isset($revision)) {
            exit('Unable to load the PPE IIRUP report.');
        }
        $error = 'Unable to save the PPE IIRUP report. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= iirupEditEscape(APP_ROOT_URL) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit PPE IIRUP</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= filemtime(dirname(__DIR__) . '/css/styles.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="add-page iirup-page ppe-page item-entry-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<main class="container">
    <div class="form-container inventory-item-form no-number-spinners">
        <header class="page-header">
            <h1 class="form-page-title"><i class="fas fa-edit" aria-hidden="true"></i> Edit PPE IIRUP #<?= $id ?></h1>
        </header>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= iirupEditEscape($error) ?></div>
        <?php endif; ?>
        <form method="post" action="PPE/edit_ppe_iirup.php?<?= iirupEditEscape(http_build_query($editQuery)) ?>">
            <input type="hidden" name="csrf_token" value="<?= iirupEditEscape($token) ?>">
            <input type="hidden" name="revision" value="<?= iirupEditEscape($revision) ?>">
            <?php foreach ($headerSections as $heading => $fields): ?>
                <section class="section-card">
                    <h2 class="form-section-heading"><?= iirupEditEscape($heading) ?></h2>
                    <div class="form-grid">
                        <?php foreach ($fields as $field => $label): ?>
                            <div class="form-group">
                                <label for="<?= $field ?>"><?= iirupEditEscape($label) ?></label>
                                <input id="<?= $field ?>" name="<?= $field ?>"
                                       value="<?= iirupEditEscape($submittedHeader[$field] ?? $report[$field] ?? '') ?>"
                                       <?= $field === 'date_reported' ? 'type="date" required' : 'type="text" maxlength="255"' ?>>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <h2 class="form-section-heading">Unserviceable PPE Items</h2>
            <?php if ($items): ?>
                <p>Allocate each item's recorded disposal quantity across the applicable disposal methods.</p>
            <?php else: ?>
                <p>This report has no saved item lines.</p>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php
                $itemId = (int)$item['id'];
                $values = (array)($submittedItems[$itemId] ?? []) + $item;
                ?>
                <section class="section-card">
                    <h3 class="form-section-heading"><?= iirupEditEscape($item['PPE_no']) ?> · <?= iirupEditEscape($item['particulars']) ?></h3>
                    <p>Recorded disposal quantity <strong><?= (int)$item['quantity'] ?></strong></p>
                    <div class="form-grid">
                        <?php foreach ($modes + $moneyFields as $field => $label): ?>
                            <div class="form-group">
                                <label for="item-<?= $itemId ?>-<?= $field ?>"><?= iirupEditEscape($label) ?></label>
                                <input type="number" id="item-<?= $itemId ?>-<?= $field ?>" name="items[<?= $itemId ?>][<?= $field ?>]"
                                       min="0" <?= isset($modes[$field]) ? 'step="1" max="' . (int)$item['quantity'] . '"' : 'step="0.01" max="9999999999999.99"' ?>
                                       value="<?= iirupEditEscape($values[$field] ?? '0') ?>" required>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (['or_no' => 'O.R. No.', 'other_specification' => 'Other Specification'] as $field => $label): ?>
                            <div class="form-group">
                                <label for="item-<?= $itemId ?>-<?= $field ?>"><?= iirupEditEscape($label) ?></label>
                                <input type="text" id="item-<?= $itemId ?>-<?= $field ?>" name="items[<?= $itemId ?>][<?= $field ?>]"
                                       maxlength="<?= $field === 'or_no' ? 100 : 255 ?>" value="<?= iirupEditEscape($values[$field] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-group">
                        <label for="item-<?= $itemId ?>-remarks">Remarks</label>
                        <textarea id="item-<?= $itemId ?>-remarks" name="items[<?= $itemId ?>][remarks]" maxlength="65535"><?= iirupEditEscape($values['remarks'] ?? '') ?></textarea>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="button-group">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save Changes</button>
                <a href="PPE/<?= iirupEditEscape($returnPath) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
