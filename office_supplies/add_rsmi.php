<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once __DIR__ . '/rsmi_helpers.php';
rsmiEnsureTable($conn);
if (empty($_SESSION['rsmi_csrf'])) $_SESSION['rsmi_csrf'] = bin2hex(random_bytes(32));
$error = '';
$rows = rsmiSourceRows($conn);
$uacs_values = $_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['uacs'] ?? null) ? $_POST['uacs'] : [];
$uacs_error = '';
foreach ($rows as &$sourceRow) {
    $value = $uacs_values[base64_encode($sourceRow['stock_number'])] ?? '';
    if (!is_string($value) || mb_strlen($value) > 50) {
        $uacs_error = 'UACS Object Codes must be 50 characters or fewer.';
        $value = '';
    }
    $sourceRow['uacs_object_code'] = trim($value);
}
unset($sourceRow);
$report_date = date('Y-m-d');
$custodian_value = null;
$accounting_value = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['rsmi_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request. Reload the page and try again.');
    }
    $report_date = trim((string)($_POST['report_date'] ?? ''));
    $custodian_value = trim((string)($_POST['custodian_name'] ?? ''));
    $accounting_value = trim((string)($_POST['accounting_staff'] ?? ''));
    $date = DateTime::createFromFormat('!Y-m-d', $report_date);
    if ($uacs_error !== '') {
        $error = $uacs_error;
    } elseif (!$date || $date->format('Y-m-d') !== $report_date) {
        $error = 'Enter a valid report date.';
    } elseif ($custodian_value === '' || $accounting_value === '') {
        $error = 'Enter both signatory names.';
    } elseif (mb_strlen($custodian_value) > 255 || mb_strlen($accounting_value) > 255) {
        $error = 'Signatory names must be 255 characters or fewer.';
    } elseif (!$rows) {
        $error = 'There are no issued supplies to save.';
    } else {
        try {
            $id = rsmiSave($conn, $rows, $report_date, $custodian_value, $accounting_value, (int)$_SESSION['user_id']);
            unset($_SESSION['rsmi_csrf']);
            header('Location: rsmi.php?saved=' . $id);
            exit;
        } catch (Throwable $e) {
            error_log('RSMI save failed: ' . $e->getMessage());
            $error = 'Unable to save the report. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add RSMI - Report on Stock of Materials and Supplies Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rsmi-form-page { max-width: 1120px; margin-inline: auto; }
        .rsmi-form-page .currency { white-space: nowrap; }
        .rsmi-form-page .table-viewport input { min-width: 10rem; }
        .rsmi-form-page #signatureForm {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
        }
    </style>
</head>
<body class="add-page item-entry-page">
    <?php include dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="add-ris-page content">
    <div class="form-container inventory-item-form rsmi-form-page">
        <header class="page-header">
            <h1 class="form-page-title"><i class="fas fa-file-invoice" aria-hidden="true"></i> Add RSMI Document</h1>
            <p class="form-page-subtitle">Review issued supplies and prepare the report for signing.</p>
        </header>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <section class="section-card" aria-labelledby="rsmi-entries-heading">
            <h3 class="form-section-heading" id="rsmi-entries-heading"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Issued Supplies</h3>
            <div class="table-frame"><div class="table-viewport" tabindex="0" role="region" aria-label="RSMI entries, scroll to view rows">
            <table id="rsmiTable" class="compact-document-table">
            <thead>
                <tr>
                    <th><i class="fas fa-hashtag"></i> RIS No.</th>
                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                    <th><i class="fas fa-tag"></i> Item</th>
                    <th><i class="fas fa-ruler"></i> Unit</th>
                    <th><i class="fas fa-cubes"></i> Quantity Issued</th>
                    <th><i class="fas fa-dollar-sign"></i> Unit Cost</th>
                    <th><i class="fas fa-calculator"></i> Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php


                // Fetch all officer names for autocomplete
                $officer_names = [];
                $officers_result = $conn->query("SELECT officer_name FROM officers ORDER BY officer_name ASC");
                if ($officers_result && $officers_result->num_rows > 0) {
                    while ($row = $officers_result->fetch_assoc()) {
                        $officer_names[] = $row['officer_name'];
                    }
                }
                $officer_names_json = json_encode($officer_names, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

                // Fetch current user's full name for custodian default
                $default_custodian = '';
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result_user = $stmt->get_result();
                if ($result_user && $result_user->num_rows > 0) {
                    $user_data = $result_user->fetch_assoc();
                    $default_custodian = $user_data['full_name'] ?? '';
                }
                $stmt->close();

                if ($rows) {

                    foreach ($rows as $row) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['ris_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['issued_quantity']) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['unit_cost'], 2) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['amount'], 2) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="7">No RSMI entries found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        </div>

        </div></section>
        <section class="section-card" aria-labelledby="rsmi-recap-heading">
            <h3 class="form-section-heading" id="rsmi-recap-heading"><i class="fas fa-list-alt" aria-hidden="true"></i> Recapitulation</h3>

        <div class="table-frame"><div class="table-viewport" tabindex="0" role="region" aria-label="Recapitulation, scroll to view rows">
            <table id="recapTable" class="compact-document-table">
            <thead>
                <tr>
                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                    <th><i class="fas fa-cubes"></i> Total Quantity Issued</th>
                    <th><i class="fas fa-code"></i> UACS Object Code</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recap = rsmiRecap($rows);

                if ($recap) {

                    foreach ($recap as $row) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['total_issued']) . '</td>';
                        echo '<td><input type="text" form="signatureForm" name="uacs[' . htmlspecialchars(base64_encode($row['stock_number']), ENT_QUOTES, 'UTF-8') . ']" maxlength="50" value="' . rsmiH($row['uacs_object_code'] ?? '') . '" aria-label="UACS Object Code for ' . rsmiH($row['stock_number']) . '" placeholder="Enter UACS Object Code"></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">No recapitulation data found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        </div>

        </div></section>
        <section class="section-card signatory-card" aria-labelledby="rsmi-signatory-heading">
            <h3 class="form-section-heading" id="rsmi-signatory-heading"><i class="fas fa-signature" aria-hidden="true"></i> Signatory Information</h3>
            <p class="required-fields-note"><span class="required-indicator" aria-hidden="true">*</span> indicates a required field.</p>
            <form method="post" id="signatureForm" class="form-grid">
                <div class="form-group">
                    <label for="custodian_name">
                        <i class="fas fa-user"></i> Supply/Property Custodian: <span class="required-indicator" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="custodian_name"
                        name="custodian_name" required maxlength="255"
                        value="<?php echo htmlspecialchars($custodian_value ?? $default_custodian, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter name"

                    >
                </div>
                <div class="form-group officer-name-field">
                    <label for="accounting_staff">
                        <i class="fas fa-calculator"></i> Designated Accounting Staff: <span class="required-indicator" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="accounting_staff"
                        name="accounting_staff" required maxlength="255" value="<?= htmlspecialchars($accounting_value, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Enter name"
                        autocomplete="off"

                    >
                    <div id="accounting_staff_dropdown" class="autocomplete-dropdown officer-dropdown"></div>
                </div>
                <div class="form-group">
                    <label for="report_date">
                        <i class="fas fa-calendar"></i> Date: <span class="required-indicator" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="date"
                        id="report_date"
                        name="report_date" required
                        value="<?= htmlspecialchars($report_date, ENT_QUOTES) ?>"

                    >
                </div>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['rsmi_csrf'], ENT_QUOTES) ?>">
            </form>
        </section>
        <div class="actions">
            <button type="submit" form="signatureForm" class="pill-btn pill-add"><i class="fas fa-save" aria-hidden="true"></i> Save Document</button>
            <a href="office_supplies/rsmi.php" class="pill-btn pill-view"><i class="fas fa-ban" aria-hidden="true"></i> Cancel</a>
        </div>
    </div>
    </div>

    <?php require_once dirname(__DIR__) . '/functions.php'; renderOfficerDropdownOptions($conn); ?>
<script>
        // Add mobile sidebar toggle functionality if needed
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Officer names for autocomplete

        document.addEventListener('DOMContentLoaded', function() {
            const recapTable = document.getElementById('recapTable');
            const recapViewport = recapTable.closest('.table-viewport');
            const sixthRecapRow = recapTable.tBodies[0].rows[5];
            if (sixthRecapRow) {
                const fitRecapRows = () => {
                    const rowsHeight = sixthRecapRow.getBoundingClientRect().bottom - recapTable.getBoundingClientRect().top;
                    const scrollbarHeight = recapViewport.offsetHeight - recapViewport.clientHeight;
                    recapViewport.style.maxHeight = Math.ceil(rowsHeight + scrollbarHeight) + 'px';
                };
                fitRecapRows();
                new ResizeObserver(fitRecapRows).observe(recapTable);
            }

            const menuButton = document.querySelector('.menu-button');
            if (menuButton) {
                menuButton.addEventListener('click', toggleSidebar);
            }
            // Initialize autocomplete for accounting staff
            setupAutocomplete('accounting_staff', 'accounting_staff_dropdown');
        });
    </script>
</body>
</html>
