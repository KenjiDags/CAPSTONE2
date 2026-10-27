<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';

require_once __DIR__ . '/rpcppe_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    try {
        rpcppeSaveReport($conn, $_POST);
        $_SESSION['success'] = 'RPCPPE report saved successfully!';
        header('Location: ' . APP_ROOT_URL . 'PPE/RPCPPE.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
// Fetch PPE items from database
$ppe_items = [];
$sql = "SELECT id, item_name, item_description, PPE_no, unit, amount, quantity, officer_incharge, custodian FROM ppe_property WHERE quantity > 0 AND COALESCE(status, '') NOT IN ('Unserviceable', 'Disposed') AND COALESCE(`condition`, '') <> 'Unserviceable' ORDER BY PPE_no";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ppe_items[] = $row;
    }
} else {
    // Handle database error
    error_log("Database error: " . $conn->error);
    $ppe_items = [];
}

// Default accountable officer to the logged-in user's full name
$current_user_full_name = '';
$current_user_position = '';
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt_user = $conn->prepare("SELECT COALESCE(NULLIF(full_name, ''), NULLIF(user_full_name, '')) AS full_name, COALESCE(NULLIF(user_position, ''), '') AS user_position FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $uid);
        $stmt_user->execute();
        $user_res = $stmt_user->get_result();
        if ($user_res && $user_res->num_rows > 0) {
            $user_data = $user_res->fetch_assoc();
            $current_user_full_name = $user_data['full_name'] ?? '';
            $current_user_position = $user_data['user_position'] ?? '';
        }
        $stmt_user->close();
    }
}
?>

<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add RPCPPE - Report on Physical Count of Property, Plant and Equipment</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="add-page inventory-report-page ppe-page">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="container">
    <div class="form-container rpcppe-form inventory-report-form inventory-item-form">
        <header class="page-header">
            <h1 class="form-page-title"><i class="fas fa-cubes" aria-hidden="true"></i> Add RPCPPE</h1>
            <p class="form-page-subtitle">Report on the Physical Count of Property, Plant and Equipment</p>
        </header>
        <?php if (isset($_SESSION['success'])): ?>
                    <div style="background: #d1fae5; border: 2px solid #10b981; color: #065f46; padding: 12px; border-radius: 8px; margin: 10px 0;">
                        ✓ <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div style="background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 12px; border-radius: 8px; margin: 10px 0;">
                        ✗ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>


        <form id="rpcppe-report-form" method="POST" action="PPE/add_RPCPPE.php">
            <section class="section-card">
                <h3 class="form-section-heading"><i class="fas fa-file-alt" aria-hidden="true"></i> Report Details</h3>
                <div class="form-grid">
                    <div class="form-group"><label for="report_date">As at:</label><input type="date" id="report_date" name="report_date" required value="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group"><label for="fund_cluster">Fund Cluster:</label><input type="text" id="fund_cluster" name="fund_cluster" placeholder="Enter Fund Cluster"></div>
                    <div class="form-group"><label for="accountable_officer">Name of Accountable Officer:</label><input type="text" id="accountable_officer" name="accountable_officer" value="<?= htmlspecialchars($current_user_full_name) ?>" placeholder="Name of Accountable Officer"></div>
                    <div class="form-group"><label for="official_designation">Official Designation:</label><input type="text" id="official_designation" name="official_designation" value="<?= htmlspecialchars($current_user_position) ?>" placeholder="Official Designation"></div>
                    <div class="form-group"><label for="entity_name">Entity Name:</label><input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" placeholder="Entity Name"></div>
                    <div class="form-group"><label for="assumption_date">Accountability Assumed On:</label><input type="date" id="assumption_date" name="assumption_date" value="<?= date('Y-m-d') ?>"></div>
                </div>
            </section>
            <h3 class="form-section-heading inventory-section-heading"><i class="fas fa-list" aria-hidden="true"></i> PPE Items</h3>
                    <div class="table-controls count-search-toolbar">
                            <label for="row_limit">Show:</label>
                            <select id="row_limit" aria-label="Rows to display">
                                <option value="5" selected>5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="all">All</option>
                            </select>
                            <div class="search-container">
                                <label class="compact-search-field" for="searchInput"><i class="fas fa-search" aria-hidden="true"></i><input aria-label="Search items" type="text" id="searchInput" class="search-input" placeholder="Search by PAR number, item name, or description..."></label>
                            </div>
            <div class="button-tooltip">
                <button type="button" id="match-balance" class="pill-btn pill-view" aria-describedby="match-balance-tooltip"><i class="fas fa-equals" aria-hidden="true"></i> Match Balance</button>
                <span class="button-tooltip-text" id="match-balance-tooltip" role="tooltip">Set the counted quantity equal to the recorded balance</span>
            </div>
                    </div>

            <div class="table-frame"><div class="table-viewport rpcppe-table-wrapper">
                <table class="rpcppe-table scrollable-document-table" id="rpcppe-table">
                    <thead>
                        <tr>
                            <th rowspan="2"><i class="fas fa-cube"></i> Article</th>
                            <th rowspan="2"><i class="fas fa-align-left"></i> Description</th>
                            <th rowspan="2"><i class="fas fa-file-alt"></i> Property Number</th>
                            <th rowspan="2"><i class="fas fa-ruler"></i> Unit of Measure</th>
                            <th rowspan="2"><i class="fas fa-dollar-sign"></i> Unit Value</th>
                            <th rowspan="2"><i class="fas fa-clipboard"></i> Balance Per Card<br>(Quantity)</th>
                            <th rowspan="2"><i class="fas fa-hand-paper"></i> On Hand Per Count<br>(Quantity)</th>
                            <th colspan="2"><i class="fas fa-chart-line"></i> Shortage/Overage</th>
                            <th rowspan="2"><i class="fas fa-comment"></i> Remarks</th>
                        </tr>
                        <tr>
                            <th>Qty</th>
                            <th>Value</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        if (empty($ppe_items)) {
                            echo '<tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-gray); font-style: italic;">No PPE items found</td></tr>';
                        } else {
                            foreach ($ppe_items as $item) {
                                $ppe_id = $item['id'];
                                echo '<tr>';
                                // Article
                                echo '<td>PPE</td>';
                                // Description
                                $description = htmlspecialchars($item['item_name'] ?? '');
                                if (!empty($item['item_description'])) {
                                    $description .= ' - ' . htmlspecialchars($item['item_description']);
                                }
                                echo '<td>' . $description . '</td>';
                                // Property Number (PAR No)
                                echo '<td>' . htmlspecialchars($item['PPE_no'] ?? '') . '</td>';
                                // Unit of Measure
                                echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
                                // Unit Value
                                echo '<td class="currency">' . '&#8369;' . number_format((float)($item['amount'] ?? 0), 2) . '</td>';
                                // Balance Per Card
                                echo '<td>' . htmlspecialchars($item['quantity'] ?? '') . '</td>';
                                // Input cells for manual entry with name attributes
                                echo '<td><input type="number" name="on_hand_count[' . $ppe_id . ']" data-recorded-balance="' . (int)($item['quantity'] ?? 0) . '" min="0" step="1" class="count-input" /></td>';
                                echo '<td><input type="number" name="shortage_qty[' . $ppe_id . ']" step="1" class="count-input" readonly /></td>';
                                echo '<td><input type="number" name="shortage_value[' . $ppe_id . ']" step="0.01" class="count-input" /></td>';
                                echo '<td><input type="text" name="remarks[' . $ppe_id . ']" class="count-input" /></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                </div></div>

                <div class="signature-section">
                    <div class="signature-box">
                        <h4>Certified Correct by:</h4>
                        <div class="officer-name-field">
                            <input type="text" class="signature-input" id="signature_name_1" name="signature_name_1" placeholder="Signature over Printed Name" autocomplete="off">
                            <div id="signature_name_1_dropdown" class="autocomplete-dropdown"></div>
                        </div>
                        <div class="signature-text">
                            Signature over Printed Name of Inventory<br>
                            Committee Chair and Members
                        </div>
                    </div>

                    <div class="signature-box">
                        <h4>Approved by:</h4>
                        <div class="officer-name-field">
                            <input type="text" class="signature-input" id="signature_name_2" name="signature_name_2" placeholder="Signature over Printed Name" autocomplete="off">
                            <div id="signature_name_2_dropdown" class="autocomplete-dropdown"></div>
                        </div>
                        <div class="signature-text">
                            Signature over Printed Name of Head of Agency/Entity<br>
                            or Authorized Representative
                        </div>
                    </div>

                    <div class="signature-box">
                        <h4>Verified by:</h4>
                        <div class="officer-name-field">
                            <input type="text" class="signature-input" id="signature_name_3" name="signature_name_3" placeholder="Signature over Printed Name" autocomplete="off">
                            <div id="signature_name_3_dropdown" class="autocomplete-dropdown"></div>
                        </div>
                        <div class="signature-text">
                            Signature over Printed Name of COA Representative
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" name="save_report" value="1" class="pill-btn pill-add"><i class="fas fa-save"></i> Save Report</button>
                    <a href="PPE/RPCPPE.php" class="pill-btn pill-view">Back to Reports</a>
                </div>
            </form>
        </div>
    </div>

    <?php require dirname(__DIR__) . '/officer_dropdown_options.php'; ?>
<script>
document.querySelectorAll('#rpcppe-table input[data-recorded-balance]').forEach(input => {
    const difference = input.closest('tr').querySelector('input[name^="shortage_qty["]');
    function updateDifference() {
        difference.value = input.value === '' || !input.validity.valid ? ''
            : String(Number(input.value) - Number(input.dataset.recordedBalance));
    }
    input.addEventListener('input', updateDifference);
    input.addEventListener('change', updateDifference);
    updateDifference();
});
const matchBalanceButton = document.getElementById('match-balance');
if (matchBalanceButton) matchBalanceButton.addEventListener('click', function () {
    document.querySelectorAll('#rpcppe-table input[data-recorded-balance]').forEach(input => {
        if (input.readOnly) return;
        input.value = input.dataset.recordedBalance;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
});



        document.addEventListener('DOMContentLoaded', function() {
            setupAutocomplete('signature_name_1', 'signature_name_1_dropdown');
            setupAutocomplete('signature_name_2', 'signature_name_2_dropdown');
            setupAutocomplete('signature_name_3', 'signature_name_3_dropdown');
        });

</script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        const wrapper = document.querySelector('.rpcppe-table-wrapper');
        const table = document.querySelector('.rpcppe-table');
        const thead = table.querySelector('thead');

        function applyLimit() {
            const val = limitSelect.value;
            const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])');
            if (!wrapper || !thead || !sampleRow) return;

            const headerHeight = thead.getBoundingClientRect().height;
            const rowHeight = sampleRow.getBoundingClientRect().height;

            if (val === 'all') {
                wrapper.style.maxHeight = 'none';
            } else {
                const count = parseInt(val, 10);
                wrapper.style.maxHeight = `${headerHeight + rowHeight * count}px`;
            }
        }

        limitSelect.addEventListener('change', applyLimit);
        applyLimit();

        window.addEventListener('resize', applyLimit);
    });

    // Searchbar JS
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rpcppe-table tbody tr');

        rows.forEach(row => {
            const article = row.cells[0].textContent.toLowerCase();
            const description = row.cells[1].textContent.toLowerCase();
            const propertyNo = row.cells[2].textContent.toLowerCase();
            const unit = row.cells[3].textContent.toLowerCase();

            const match = article.includes(filter) || description.includes(filter) || propertyNo.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const firstRow = document.querySelector('.rpcppe-table thead tr:first-child');
        const secondRowThs = document.querySelectorAll('.rpcppe-table thead tr:nth-child(2) th');
        if (!firstRow || secondRowThs.length === 0) return;
        function alignHeaderRows() {
            const firstRowHeight = firstRow.getBoundingClientRect().height;
            secondRowThs.forEach(th => { th.style.top = firstRowHeight + 'px'; });
        }
        alignHeaderRows();
        window.addEventListener('resize', alignHeaderRows);
    });
    </script>

</body>
</html>
