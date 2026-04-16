<?php
require 'auth.php';
require 'config.php';

$officeSuppliesCount = 0;
$semiExpendablesCount = 0;
$ppeCount = 0;
$itemsIssuedThisMonthCount = 0;

$overallItemsCount = 0;
$officeSuppliesCategoryCount = 0;
$officeEquipmentCategoryCount = 0;
$cleaningSuppliesCategoryCount = 0;
$electronicsCategoryCount = 0;
$miscelaneousCategoryCount = 0;
$stockAboveReorderCount = 0;
$stockAtReorderCount = 0;
$stockEmptyCount = 0;
$semiOtherPPECount = 0;
$semiOfficeEquipmentCount = 0;
$semiICTEquipmentCount = 0;
$semiCommunicationEqptCount = 0;
$semiFurnaturesFixturesCount = 0;
$semiNotIssuedCount = 0;
$semiCurrentlyIssuedCount = 0;
$semiDisposedCount = 0;
$ppeServiceableCount = 0;
$ppeUnserviceableCount = 0;

if ($result = $conn->query("SELECT COUNT(*) AS total FROM items")) {
    $officeSuppliesCount = (int)($result->fetch_assoc()['total'] ?? 0);
    $result->close();
}

if ($result = $conn->query("SELECT COUNT(*) AS total FROM semi_expendable_property")) {
    $semiExpendablesCount = (int)($result->fetch_assoc()['total'] ?? 0);
    $result->close();
}

if ($result = $conn->query("SELECT COUNT(*) AS total FROM ppe_property")) {
    $ppeCount = (int)($result->fetch_assoc()['total'] ?? 0);
    $result->close();
}

if ($result = $conn->query("SELECT COALESCE(SUM(issued_quantity), 0) AS total_issued FROM ris_items ri JOIN ris r ON ri.ris_id = r.ris_id WHERE MONTH(r.date_requested) = MONTH(CURDATE()) AND YEAR(r.date_requested) = YEAR(CURDATE())")) {
    $itemsIssuedThisMonthCount = (int)($result->fetch_assoc()['total_issued'] ?? 0);
    $result->close();
}

function matchesInventoryCategory($rowText, $category, $categoryMap, $categoryExclusions)
{
    if (strpos($rowText, 'cutter paper') !== false) {
        return $category === 'office-equipment';
    }

    if (strpos($rowText, 'kitchen towel') !== false) {
        return $category === 'cleaning-supplies';
    }

    if (strpos($rowText, 'tape dispenser') !== false) {
        return $category === 'office-equipment';
    }

    $keywords = $categoryMap[$category] ?? [];
    $isMatch = false;

    foreach ($keywords as $keyword) {
        if (strpos($rowText, $keyword) !== false) {
            $isMatch = true;
            break;
        }
    }

    $exclusions = $categoryExclusions[$category] ?? [];
    if ($isMatch) {
        foreach ($exclusions as $term) {
            if (strpos($rowText, $term) !== false) {
                $isMatch = false;
                break;
            }
        }
    }

    if ($category === 'office-supplies' &&
        (strpos($rowText, 'tissue paper') !== false || strpos($rowText, 'scouring pad') !== false || strpos($rowText, 'stapler') !== false)) {
        $isMatch = false;
    }

    return $isMatch;
}

$categoryMap = [
    'office-supplies' => ['fastener', 'paper', 'bond', 'notebook', 'pad', 'ream', 'envelope', 'pen', 'ballpen', 'gel pen', 'marker', 'highlighter', 'ink', 'folder', 'file', 'filing', 'index tab', 'document holder', 'staples', 'paper clip', 'clip', 'binder clip', 'tape', 'glue', 'adhesive', 'paste', 'pin', 'note', 'book'],
    'office-equipment' => ['ruler', 'chair', 'table', 'desk', 'cabinet', 'printer', 'scanner', 'projector', 'laminator', 'shredder', 'furniture', 'cutter', 'stapler', 'staplers'],
    'cleaning-supplies' => ['conditioner', 'dishwashing', 'detergent', 'soap', 'bleach', 'mop', 'broom', 'brush', 'disinfectant', 'cleaner', 'trash bag', 'garbage', 'alcohol', 'tissue', 'towel', 'rags', 'sponge'],
    'electronics' => ['laptop', 'computer', 'monitor', 'keyboard', 'mouse', 'ups', 'router', 'calculator', 'switch', 'tablet', 'phone', 'speaker', 'camera', 'headset', 'microphone', 'usb', 'cable', 'charger', 'adapter', 'electronic', 'battery', 'led', 'bulb']
];

$categoryExclusions = [
    'office-equipment' => ['pen', 'ballpen', 'paper', 'notebook', 'folder', 'ink', 'marker', 'highlighter']
];

if ($result = $conn->query("SELECT item_name, description FROM items")) {
    while ($row = $result->fetch_assoc()) {
        $overallItemsCount++;
        $rowText = strtolower(trim(($row['item_name'] ?? '') . ' ' . ($row['description'] ?? '')));

        $isOfficeSupplies = matchesInventoryCategory($rowText, 'office-supplies', $categoryMap, $categoryExclusions);
        $isOfficeEquipment = matchesInventoryCategory($rowText, 'office-equipment', $categoryMap, $categoryExclusions);
        $isCleaningSupplies = matchesInventoryCategory($rowText, 'cleaning-supplies', $categoryMap, $categoryExclusions);
        $isElectronics = matchesInventoryCategory($rowText, 'electronics', $categoryMap, $categoryExclusions);

        if ($isOfficeSupplies) {
            $officeSuppliesCategoryCount++;
        }

        if ($isOfficeEquipment) {
            $officeEquipmentCategoryCount++;
        }

        if ($isCleaningSupplies) {
            $cleaningSuppliesCategoryCount++;
        }

        if ($isElectronics) {
            $electronicsCategoryCount++;
        }

        if (!$isOfficeSupplies && !$isOfficeEquipment && !$isCleaningSupplies && !$isElectronics) {
            $miscelaneousCategoryCount++;
        }
    }

    $result->close();
}

if ($result = $conn->query("SELECT quantity_on_hand, reorder_point FROM items")) {
    while ($row = $result->fetch_assoc()) {
        $quantityOnHand = (int)($row['quantity_on_hand'] ?? 0);
        $reorderPoint = (int)($row['reorder_point'] ?? 0);

        if ($quantityOnHand <= 0) {
            $stockEmptyCount++;
        } elseif ($quantityOnHand <= $reorderPoint) {
            $stockAtReorderCount++;
        } else {
            $stockAboveReorderCount++;
        }
    }

    $result->close();
}

if ($result = $conn->query("SELECT category, quantity_balance FROM semi_expendable_property")) {
    while ($row = $result->fetch_assoc()) {
        $categoryValue = strtolower(trim((string)($row['category'] ?? '')));
        $semiNotIssuedCount += max(0, (int)($row['quantity_balance'] ?? 0));

        if (
            strpos($categoryValue, 'other ppe') !== false ||
            strpos($categoryValue, 'other-ppe') !== false ||
            $categoryValue === 'other'
        ) {
            $semiOtherPPECount++;
        } elseif (
            strpos($categoryValue, 'office equipment') !== false ||
            strpos($categoryValue, 'office-equipment') !== false
        ) {
            $semiOfficeEquipmentCount++;
        } elseif (
            strpos($categoryValue, 'ict equipment') !== false ||
            strpos($categoryValue, 'ict eqpt') !== false ||
            strpos($categoryValue, 'ict') !== false
        ) {
            $semiICTEquipmentCount++;
        } elseif (
            strpos($categoryValue, 'communication') !== false ||
            strpos($categoryValue, 'comm') !== false
        ) {
            $semiCommunicationEqptCount++;
        } elseif (
            strpos($categoryValue, 'furnatures') !== false ||
            strpos($categoryValue, 'furniture') !== false ||
            strpos($categoryValue, 'fixtures') !== false
        ) {
            $semiFurnaturesFixturesCount++;
        }
    }

    $result->close();
}

// Calculate semi-expendable unit counts by status using quantity breakdown
if ($result = $conn->query("SELECT 
    COALESCE(SUM(quantity + quantity_disposed), 0) AS total_units,
    COALESCE(SUM(quantity - quantity_issued - quantity_reissued), 0) AS not_issued,
    COALESCE(SUM(quantity_issued + quantity_reissued), 0) AS currently_issued,
    COALESCE(SUM(quantity_disposed), 0) AS disposed
    FROM semi_expendable_property")) {
    $row = $result->fetch_assoc();
    $semiTotalUnits = max(0, (int)($row['total_units'] ?? 0));
    $semiNotIssuedCount = max(0, (int)($row['not_issued'] ?? 0));
    $semiCurrentlyIssuedCount = max(0, (int)($row['currently_issued'] ?? 0));
    $semiDisposedCount = max(0, (int)($row['disposed'] ?? 0));
    $result->close();
}

if ($result = $conn->query("SELECT `condition` FROM ppe_property")) {
    while ($row = $result->fetch_assoc()) {
        $condition = strtolower(trim((string)($row['condition'] ?? '')));

        if ($condition === 'serviceable') {
            $ppeServiceableCount++;
        } else {
            $ppeUnserviceableCount++;
        }
    }

    $result->close();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - TESDA Inventory</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <script src="js/chart.min.js"></script>
    <style>
        body, main {
            background: transparent !important;
        }

        .dashboard-wrap {
            width: 100%;
            margin: 0;
            padding: 24px;
        }

        .dashboard-subtitle {
            text-align: center;
            color: #4b5563;
            margin-top: -10px;
            margin-bottom: 24px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 220px));
            justify-content: center;
            gap: 16px;
        }

        .summary-panel {
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 24px;
        }

        .tabs-shell {
            margin-bottom: 24px;
        }

        .tab-options-panel {
            margin-bottom: 16px;
        }

        .tab-content-panel {
            margin-bottom: 0;
        }

        .summary-card {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border-radius: 14px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.18);
            padding: 18px;
            border: 1px solid #374151;
        }

        .summary-label {
            color: rgba(255, 255, 255, 0.86);
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .summary-value {
            color: #ffffff;
            font-size: 32px;
            line-height: 1;
            font-weight: 700;
        }

        .summary-title {
            color: #0038a8;
            margin: 0 0 12px;
            font-size: 20px;
        }

        .tabs-panel {
            margin-bottom: 24px;
        }

        .tab-options-panel {
            padding: 18px;
        }

        .tab-content-panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 24px rgba(0, 56, 168, 0.1);
            padding: 18px;
            border: 1px solid #e8f0fe;
        }

        .category-tabs {
            display: flex !important;
            gap: 10px !important;
            margin-bottom: 20px !important;
            border-bottom: 2px solid #e5e7eb !important;
            flex-wrap: wrap !important;
        }

        .category-tab {
            padding: 12px 20px !important;
            text-decoration: none !important;
            color: #6b7280 !important;
            border: none !important;
            border-bottom: 3px solid transparent !important;
            background: transparent !important;
            transition: all 0.3s !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            margin-bottom: -2px !important;
            cursor: pointer;
        }

        .category-tab:hover {
            color: #3b82f6 !important;
            border-bottom-color: #93c5fd !important;
        }

        .category-tab.active {
            color: #3b82f6 !important;
            border-bottom-color: #3b82f6 !important;
            font-weight: 600 !important;
        }

        .tab-content {
            display: none;
            padding-top: 8px;
        }

        .tab-content.active {
            display: block;
        }

        .pie-chart {
            max-width: 340px;
            margin: 0 auto;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
            margin-top: 14px;
            color: #4b5563;
            font-size: 14px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .legend-swatch {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            display: inline-block;
        }

        @media (max-width: 1024px) {
            .cards-grid {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }
        }

        @media (max-width: 640px) {
            .cards-grid {
                grid-template-columns: 1fr;
                justify-content: stretch;
            }

            .category-tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="dashboard-wrap">
    <h2>Dashboard</h2>

    <div class="tabs-shell">
        <section class="tab-options-panel">
            <div class="category-tabs" role="tablist" aria-label="Inventory Categories">
                <button class="category-tab active" type="button" data-tab="office-supplies" role="tab" aria-selected="true">Office Supplies</button>
                <button class="category-tab" type="button" data-tab="semi-expendables" role="tab" aria-selected="false">Semi Expendables</button>
                <button class="category-tab" type="button" data-tab="ppe" role="tab" aria-selected="false">PPE</button>
            </div>
        </section>

        <section class="summary-panel">
            <div class="cards-grid" id="summaryCards"></div>
        </section>

        <section class="tab-content-panel">
            <div class="tab-content active" id="office-supplies" role="tabpanel">
                <div class="pie-chart">
                    <canvas id="stockStatusChart" aria-label="Stock status pie chart" role="img"></canvas>
                </div>
                <div class="chart-legend" aria-hidden="true">
                    <span class="legend-item"><span class="legend-swatch" style="background:#43a047;"></span> Above reorder point</span>
                    <span class="legend-item"><span class="legend-swatch" style="background:#fb8c00;"></span> At reorder point</span>
                    <span class="legend-item"><span class="legend-swatch" style="background:#e53935;"></span> Empty</span>
                </div>
            </div>

            <div class="tab-content" id="semi-expendables" role="tabpanel">
                <div class="pie-chart">
                    <canvas id="semiCategoryChart" aria-label="Semi expendables category pie chart" role="img"></canvas>
                </div>
                <div class="chart-legend" aria-hidden="true">
                    <span class="legend-item"><span class="legend-swatch" style="background:#43a047;"></span> Not issued</span>
                    <span class="legend-item"><span class="legend-swatch" style="background:#fb8c00;"></span> Currently issued</span>
                    <span class="legend-item"><span class="legend-swatch" style="background:#e53935;"></span> Disposed / For disposal</span>
                </div>
            </div>

            <div class="tab-content" id="ppe" role="tabpanel">
                <div class="pie-chart">
                    <canvas id="ppeStatusChart" aria-label="PPE status pie chart" role="img"></canvas>
                </div>
                <div class="chart-legend" aria-hidden="true">
                    <span class="legend-item"><span class="legend-swatch" style="background:#43a047;"></span> Serviceable</span>
                    <span class="legend-item"><span class="legend-swatch" style="background:#e53935;"></span> Unserviceable</span>
                </div>
            </div>
        </section>
    </div>

</main>

<script>
    const DASHBOARD_TAB_KEY = 'dashboardActiveTab';
    const tabButtons = document.querySelectorAll('.category-tab');
    const tabContents = document.querySelectorAll('.tab-content');
    const officeChartCanvas = document.getElementById('stockStatusChart');
    const semiChartCanvas = document.getElementById('semiCategoryChart');
    const ppeChartCanvas = document.getElementById('ppeStatusChart');
    const summaryCards = document.getElementById('summaryCards');
    let stockStatusChart = null;
    let semiCategoryChart = null;
    let ppeStatusChart = null;

    const summaryData = {
        'office-supplies': {
            title: 'Office Supplies Summary',
            cards: [
                { label: 'Total Amount of Items', value: '<?= number_format($overallItemsCount) ?>', gradient: 'linear-gradient(135deg, #1e3a8a 0%, #172554 100%)', border: '#1d4ed8' },
                { label: 'Items Issued This Month', value: '<?= number_format($itemsIssuedThisMonthCount) ?>', gradient: 'linear-gradient(135deg, #7e22ce 0%, #581c87 100%)', border: '#9333ea' }
            ]
        },
        'semi-expendables': {
            title: 'Semi Expendables Summary',
            cards: [
                { label: 'Total Items', value: '<?= number_format($semiTotalUnits) ?>', gradient: 'linear-gradient(135deg, #1e3a8a 0%, #172554 100%)', border: '#1d4ed8' },
                { label: 'Not issued', value: '<?= number_format($semiNotIssuedCount) ?>', gradient: 'linear-gradient(135deg, #166534 0%, #14532d 100%)', border: '#16a34a' },
                { label: 'Currently issued', value: '<?= number_format($semiCurrentlyIssuedCount) ?>', gradient: 'linear-gradient(135deg, #92400e 0%, #78350f 100%)', border: '#f59e0b' },
                { label: 'Disposed / For disposal', value: '<?= number_format($semiDisposedCount) ?>', gradient: 'linear-gradient(135deg, #9f1239 0%, #881337 100%)', border: '#ec4899' }
            ]
        },
        'ppe': {
            title: 'PPE Summary',
            cards: [
                { label: 'Total Items', value: '<?= number_format($ppeCount) ?>', gradient: 'linear-gradient(135deg, #1e3a8a 0%, #172554 100%)', border: '#1d4ed8' },
                { label: 'Serviceable Items', value: '<?= number_format($ppeServiceableCount) ?>', gradient: 'linear-gradient(135deg, #166534 0%, #14532d 100%)', border: '#16a34a' },
                { label: 'Unserviceable Items', value: '<?= number_format($ppeUnserviceableCount) ?>', gradient: 'linear-gradient(135deg, #92400e 0%, #78350f 100%)', border: '#f59e0b' }
            ]
        }
    };

    function renderSummary(tabKey) {
        const summary = summaryData[tabKey];
        if (!summary || !summaryCards) {
            return;
        }

        summaryCards.innerHTML = summary.cards.map((card) => `
            <article class="summary-card" style="background:${card.gradient};border-color:${card.border};">
                <div class="summary-label">${card.label}</div>
                <div class="summary-value">${card.value}</div>
            </article>
        `).join('');
    }

    const valueLabelPlugin = {
        id: 'valueLabelPlugin',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const dataset = chart.data.datasets[0];
            const meta = chart.getDatasetMeta(0);
            const total = dataset.data.reduce((sum, value) => sum + value, 0);

            ctx.save();
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 14px Segoe UI, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            meta.data.forEach((arc, index) => {
                const value = dataset.data[index];
                if (!value) {
                    return;
                }

                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                const angle = (arc.startAngle + arc.endAngle) / 2;
                const radius = (arc.outerRadius + arc.innerRadius) / 2;
                const x = arc.x + Math.cos(angle) * radius;
                const y = arc.y + Math.sin(angle) * radius;
                ctx.fillText(`${percentage}%`, x, y);
            });

            ctx.restore();
        }
    };

    function createGradient(ctx, startColor, endColor) {
        const gradient = ctx.createLinearGradient(0, 0, 220, 220);
        gradient.addColorStop(0, startColor);
        gradient.addColorStop(1, endColor);
        return gradient;
    }

    function buildPieChart(canvas, labels, values, gradients) {
        if (!canvas || !window.Chart) {
            return null;
        }

        const ctx = canvas.getContext('2d');
        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: gradients,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            plugins: [valueLabelPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: true,
                radius: '88%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return `${label}: ${value}`;
                            }
                        }
                    }
                }
            }
        });
    }

    function ensureOfficeChart() {
        if (stockStatusChart || !officeChartCanvas || !window.Chart) {
            return;
        }

        const ctx = officeChartCanvas.getContext('2d');
        stockStatusChart = buildPieChart(
            officeChartCanvas,
            ['Above reorder point', 'At reorder point', 'Empty'],
            [<?= (int)$stockAboveReorderCount ?>, <?= (int)$stockAtReorderCount ?>, <?= (int)$stockEmptyCount ?>],
            [
                createGradient(ctx, '#7bd96a', '#2e7d32'),
                createGradient(ctx, '#ffd36e', '#fb8c00'),
                createGradient(ctx, '#ff8a80', '#c62828')
            ]
        );
    }

    function ensureSemiChart() {
        if (semiCategoryChart || !semiChartCanvas || !window.Chart) {
            return;
        }

        const ctx = semiChartCanvas.getContext('2d');
        semiCategoryChart = buildPieChart(
            semiChartCanvas,
            ['Not issued', 'Currently issued', 'Disposed / For disposal'],
            [
                <?= (int)$semiNotIssuedCount ?>,
                <?= (int)$semiCurrentlyIssuedCount ?>,
                <?= (int)$semiDisposedCount ?>
            ],
            [
                createGradient(ctx, '#7bd96a', '#2e7d32'),
                createGradient(ctx, '#ffd36e', '#fb8c00'),
                createGradient(ctx, '#ff8a80', '#c62828')
            ]
        );
    }

    function ensurePpeChart() {
        if (ppeStatusChart || !ppeChartCanvas || !window.Chart) {
            return;
        }

        const ctx = ppeChartCanvas.getContext('2d');
        ppeStatusChart = buildPieChart(
            ppeChartCanvas,
            ['Serviceable', 'Unserviceable'],
            [<?= (int)$ppeServiceableCount ?>, <?= (int)$ppeUnserviceableCount ?>],
            [
                createGradient(ctx, '#7bd96a', '#2e7d32'),
                createGradient(ctx, '#ff8a80', '#c62828')
            ]
        );
    }

    function activateTab(tabKey) {
        tabButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-tab') === tabKey;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        tabContents.forEach((content) => {
            content.classList.toggle('active', content.id === tabKey);
        });

        renderSummary(tabKey);

        if (tabKey === 'office-supplies') {
            ensureOfficeChart();
            if (stockStatusChart) stockStatusChart.resize();
        } else if (tabKey === 'semi-expendables') {
            ensureSemiChart();
            if (semiCategoryChart) semiCategoryChart.resize();
        } else if (tabKey === 'ppe') {
            ensurePpeChart();
            if (ppeStatusChart) ppeStatusChart.resize();
        }

        localStorage.setItem(DASHBOARD_TAB_KEY, tabKey);
    }

    const savedTab = localStorage.getItem(DASHBOARD_TAB_KEY);
    const hasSavedTab = Array.from(tabButtons).some((btn) => btn.getAttribute('data-tab') === savedTab);
    const initialTab = hasSavedTab ? savedTab : 'office-supplies';

    activateTab(initialTab);

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetTab = button.getAttribute('data-tab');
            activateTab(targetTab);
        });
    });
</script>

</body>
</html>