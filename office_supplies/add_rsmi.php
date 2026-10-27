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
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f570";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }

        /* Align export button properly in container */
        .search-add-container {
            align-items: center;
            padding: 15px 20px 0px 20px;
        }

        .rsmi-back-actions {
            background: transparent;
            margin-bottom: 16px;
        }

        .export-section {
            margin-bottom: 20px;
        }

        /* Currency cell styling - green color override */
        .currency {
            color: #059669;
        }

        .recapitulation-heading {
            margin-top: 40px !important;
        }
        .rsmi-table-viewport {
            max-height: 420px;
            overflow-y: auto;
            overflow-x: auto;
            scrollbar-gutter: stable;
        }
        .rsmi-table-viewport table {
            width: 100%;
            border-collapse: collapse;
            overflow: visible !important;
            margin: 0;
        }
        .rsmi-table-viewport thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: #fff;
        }
        body.dark-mode .rsmi-table-viewport { scrollbar-color: #475569 #0f172a; }
    </style>
</head>
<body class="add-page">
    <?php include dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="container">
        <h2>Add RSMI Document</h2>
        <div class="rsmi-back-actions"><a href="office_supplies/rsmi.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to RSMI</a></div>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <div class="table-container rsmi-table-viewport" tabindex="0" role="region" aria-label="RSMI entries, scroll to view rows">
            <table id="rsmiTable">
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

        <h2 class="recapitulation-heading">Recapitulation</h2>

        <div class="table-container rsmi-table-viewport" tabindex="0" role="region" aria-label="Recapitulation, scroll to view rows">
            <table id="recapTable">
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

        <!-- Signature Names Form -->
        <div class="section-card signatory-card" style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-top: 30px; margin-bottom: 20px;">
            <h3 style="color: #1e293b; font-size: 18px; margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-signature"></i> Signatory Information
            </h3>
            <form method="post" id="signatureForm" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label for="custodian_name" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-user"></i> Supply/Property Custodian:
                    </label>
                    <input
                        type="text"
                        id="custodian_name"
                        name="custodian_name" required maxlength="255"
                        value="<?php echo htmlspecialchars($custodian_value ?? $default_custodian, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter name"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                </div>
                <div class="form-group" style="margin: 0; position: relative;">
                    <label for="accounting_staff" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-calculator"></i> Designated Accounting Staff:
                    </label>
                    <input
                        type="text"
                        id="accounting_staff"
                        name="accounting_staff" required maxlength="255" value="<?= htmlspecialchars($accounting_value, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Enter name"
                        autocomplete="off"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                    <div id="accounting_staff_dropdown" class="autocomplete-dropdown"></div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label for="report_date" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-calendar"></i> Date:
                    </label>
                    <input
                        type="date"
                        id="report_date"
                        name="report_date" required
                        value="<?= htmlspecialchars($report_date, ENT_QUOTES) ?>"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                </div>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['rsmi_csrf'], ENT_QUOTES) ?>">
            </form>
            <style>
                #signatureForm input:hover {
                    border-color: #cbd5e1;
                }
                #signatureForm input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                .autocomplete-dropdown {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: none;
                    border-radius: 0 0 6px 6px;
                    max-height: 250px;
                    overflow-y: auto;
                    display: none;
                    z-index: 1000;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .autocomplete-item {
                    padding: 10px 12px;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .autocomplete-item:hover {
                    background: #f0f4f8;
                }
                .autocomplete-item.selected {
                    background: #3b82f6;
                    color: white;
                }
            </style>
        </div>

        <!-- Save Document -->
        <div class="search-add-container">
            <button type="submit" form="signatureForm" class="btn btn-success"><i class="fas fa-save"></i> Save RSMI Document</button>
        </div>
    </div>

    <?php require dirname(__DIR__) . '/officer_dropdown_options.php'; ?>
<script>
        // Add mobile sidebar toggle functionality if needed
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Officer names for autocomplete

        document.addEventListener('DOMContentLoaded', function() {
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
