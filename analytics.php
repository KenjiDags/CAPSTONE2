<?php
require 'auth.php';
require 'config.php';

$officeTotalItems = 0;
$officeIssuedThisMonth = 0;
$officeAboveReorder = 0;
$officeAtReorder = 0;
$officeEmpty = 0;

if ($result = $conn->query("SELECT COUNT(*) AS total FROM items")) {
  $officeTotalItems = (int)($result->fetch_assoc()['total'] ?? 0);
  $result->close();
}
if ($result = $conn->query("SELECT COALESCE(SUM(issued_quantity), 0) AS total_issued FROM ris_items ri JOIN ris r ON ri.ris_id = r.ris_id WHERE MONTH(r.date_requested) = MONTH(CURDATE()) AND YEAR(r.date_requested) = YEAR(CURDATE())")) {
  $officeIssuedThisMonth = (int)($result->fetch_assoc()['total_issued'] ?? 0);
  $result->close();
}
if ($result = $conn->query("SELECT SUM(quantity_on_hand > reorder_point) AS above_reorder, SUM(quantity_on_hand > 0 AND quantity_on_hand <= reorder_point) AS at_reorder, SUM(quantity_on_hand <= 0) AS empty_stock FROM items")) {
  $stockStatus = $result->fetch_assoc();
  $officeAboveReorder = (int)($stockStatus['above_reorder'] ?? 0);
  $officeAtReorder = (int)($stockStatus['at_reorder'] ?? 0);
  $officeEmpty = (int)($stockStatus['empty_stock'] ?? 0);
  $result->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventory Analytics</title>
  <style>html.analytics-restoring body { visibility: hidden; }</style>
  <script>
  const analyticsScrollKey = 'analytics:scroll:' + location.pathname + location.search;
  const analyticsReload = performance.getEntriesByType('navigation')[0]?.type === 'reload';
  let analyticsSavedScroll = null;
  try {
    const saved = JSON.parse(sessionStorage.getItem(analyticsScrollKey));
    if (analyticsReload && saved && Number.isFinite(saved.x) && Number.isFinite(saved.y)) analyticsSavedScroll = saved;
  } catch (_) {}
  let analyticsRestorePending = analyticsSavedScroll !== null;
  if (analyticsRestorePending) {
    document.documentElement.classList.add('analytics-restoring');
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  }
  // Always reveal the page if loading stalls or a script fails.
  const analyticsRestoreTimeout = analyticsRestorePending ? setTimeout(cancelAnalyticsRestore, 8000) : null;
  function saveAnalyticsScroll() {
    if (analyticsRestorePending) return;
    try { sessionStorage.setItem(analyticsScrollKey, JSON.stringify({ x: window.scrollX, y: window.scrollY })); } catch (_) {}
  }
  function cancelAnalyticsRestore() {
    analyticsRestorePending = false;
    document.documentElement.classList.remove('analytics-restoring');
    clearTimeout(analyticsRestoreTimeout);
    if ('scrollRestoration' in history) history.scrollRestoration = 'auto';
  }
  window.addEventListener('wheel', cancelAnalyticsRestore, { passive: true });
  window.addEventListener('touchstart', cancelAnalyticsRestore, { passive: true });
  window.addEventListener('pointerdown', cancelAnalyticsRestore, { passive: true });
  window.addEventListener('keydown', event => {
    if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(event.key)) cancelAnalyticsRestore();
  });
  window.addEventListener('pagehide', saveAnalyticsScroll);
  document.addEventListener('visibilitychange', () => { if (document.hidden) saveAnalyticsScroll(); });
  </script>
  <link rel="stylesheet" href="css/analytics.css?v=<?= time() ?>">
  <script src="js/chart.min.js"></script>
  <script src="js/mrp-stock.js?v=<?= time() ?>"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="analytics-shell">
  <header class="analytics-header">
    <div><div class="eyebrow">TESDA Inventory</div><h1>Inventory Analytics</h1></div>
  </header>

  <section class="office-overview" aria-label="Office Supplies overview">
    <div class="office-summary-cards">
      <article class="office-summary-card blue"><span>Total Amount of Items</span><strong><?= number_format($officeTotalItems) ?></strong></article>
      <article class="office-summary-card purple"><span>Items Issued This Month</span><strong><?= number_format($officeIssuedThisMonth) ?></strong></article>
    </div>
    <article class="office-status-panel">
      <div><h2>Office Supplies Stock Status</h2><p>Current stock position against reorder thresholds.</p></div>
      <div class="office-pie-wrap"><canvas id="officeStatusChart" aria-label="Office Supplies stock status pie chart"></canvas></div>
      <div class="office-status-legend"><span><i class="above"></i>Above reorder point</span><span><i class="at"></i>At reorder point</span><span><i class="empty"></i>Empty</span></div>
    </article>
  </section>

  <section class="scope-bar" aria-label="Analytics scope">
    <div class="scope-tabs" role="tablist" aria-label="Inventory category">
      <button type="button" class="scope-tab active" data-category="office-supplies" role="tab">Office Supplies</button>
      <button type="button" class="scope-tab" data-category="semi-expendables" role="tab">Semi-Expendables</button>
      <button type="button" class="scope-tab" data-category="ppe" role="tab">PPE</button>
    </div>
  </section>

  <section id="categoryInsights" class="category-insights" hidden>
    <div class="section-heading"><h2 id="categoryInsightsTitle">Category analytics</h2></div>
    <div id="semiOverviewCards" class="semi-overview-cards" hidden></div>
    <div class="category-summary-cards" id="categorySummaryCards"></div>
    <div id="ppeOverviewPanels" class="ppe-overview-panels" hidden>
      <article id="ppeConditionPanel" class="panel ppe-condition-panel" aria-labelledby="ppeConditionTitle"></article>
      <article class="panel ppe-accountability-panel" aria-labelledby="ppeAccountabilityTitle">
        <h3 id="ppeAccountabilityTitle">Property Accountability</h3>
        <div class="ppe-accountability-metrics">
          <div><span>Available PPE</span><strong id="ppeUnassignedCount">0</strong><p>Awaiting assignment</p></div>
          <div><span>Issued PPE</span><strong id="ppeAssignedCount">0</strong><p>Currently held by Officer</p></div>
        </div>
      </article>
    </div>
    <article class="panel category-register-panel" aria-labelledby="categoryRegisterTitle">
      <div class="property-assignments-heading">
        <div class="property-assignments-title">
          <h3 id="categoryRegisterTitle">Property Assignments</h3>
          <p class="panel-caption">Track assigned properties and their custodians.</p>
        </div>
        <span id="categoryRegisterCount" class="property-record-count" role="status">0 records</span>
      </div>
      <div class="inventory-table-toolbar">
        <label class="mrp-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4 4"></path></svg><input id="categoryRegisterSearch" type="search" aria-label="Search property number, item, description, or holder" placeholder="Search items or holders..." autocomplete="off"></label>
        <div class="property-status-filters" role="group" aria-label="Filter property register by status">
          <button type="button" class="active" data-register-status="all" aria-pressed="true">Show All</button>
          <button type="button" data-register-status="issued" aria-pressed="false">Issued</button>
          <button type="button" data-register-status="available" aria-pressed="false">Available</button>
          <button type="button" data-register-status="disposal" aria-pressed="false">For Disposal</button>
        </div>
      </div>
      <div class="ppe-table-wrap category-register-wrap">
        <table class="ppe-action-table" aria-labelledby="categoryRegisterTitle">
          <thead id="categoryRegisterHead"></thead><tbody id="categoryRegisterBody"></tbody>
        </table>
      </div>
    </article>
  </section>

  <section id="mrpSection" class="mrp-section">
    <div class="section-heading"><h2>Material requirements planning</h2><p>Baseline history compared with the demand trajectory</p></div>
    <article class="panel">
      <div class="mrp-toolbar"><div><h3 id="forecastTitle">Stock levels by item</h3><p class="panel-caption" style="margin-bottom:0;">Select a stock status. Critical: quantity greater than 1 and less than 5. All includes out-of-stock items.</p></div><div class="mrp-controls"><label class="mrp-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4 4"></path></svg><input id="mrpSearchInput" type="search" aria-label="Search stock items by stock number, item name, or description" placeholder="Search stock items..." autocomplete="off"></label><select class="mrp-filter-select" id="mrpFilterSelect" aria-label="Filter and sort stock chart"><option value="all">Sort by Item Name</option><option value="lowest">Sort by Lowest Quantity First</option><option value="highest">Sort by Highest Quantity First</option></select></div></div>
      <div class="mrp-status-legend"><button type="button" data-mrp-status="all">All</button><button type="button" data-mrp-status="safe"><i class="safe"></i>Safe</button><button type="button" data-mrp-status="low"><i class="low"></i>Low Stock</button><button type="button" data-mrp-status="critical"><i class="critical"></i>Critical</button><button type="button" data-mrp-status="empty"><i class="empty"></i>Empty</button><a id="criticalChartAction" class="critical-chart-action" href="office_supplies/add_multiple_items.php" hidden>Open Restock Inventory</a></div>
      <div class="mrp-chart-sections">
        <div class="mrp-primary-chart">
          <div class="mrp-multi-chart-frame">
            <canvas id="mrpStockChart"></canvas>
            <div id="mrpEmptyState" class="mrp-empty-state" hidden><strong>No active stock items match the current filters.</strong></div>
            <div id="mrpNoResults" class="mrp-empty-state search-empty-state" hidden><strong>No items match your search query.</strong></div>
          </div>
        </div>
      </div>
    </article>
  </section>

  <section id="demandForecastSection" class="demand-forecast-section" aria-labelledby="demandForecastTitle">
    <div class="section-heading"><h2 id="demandForecastTitle">Inventory Demand Forecast</h2><p>Monthly actual issuance and SMA forecasts for Office Supplies</p></div>
    <article class="panel">
      <div class="filters demand-forecast-toolbar">
        <div class="control"><label for="horizonSelect">Analysis horizon</label><select id="horizonSelect"><option value="ytd">Year to date</option><option value="12m" selected>Past 12 months</option><option value="24m">Past 24 months</option><option value="custom">Custom range</option></select></div>
        <div id="demandCustomRange" class="demand-custom-range" hidden>
          <p class="required-fields-note"><span class="required-indicator" aria-hidden="true">*</span> indicates a required field.</p>
          <div class="control"><label for="demandStartMonth">From month <span class="required-indicator" aria-hidden="true">*</span></label><input id="demandStartMonth" type="month" required></div>
          <div class="control"><label for="demandEndMonth">Through month <span class="required-indicator" aria-hidden="true">*</span></label><input id="demandEndMonth" type="month" required></div>
        </div>
      </div>
      <div class="demand-chart-frame" id="demandChartFrame" hidden><canvas id="demandForecastChart" aria-label="Monthly actual issuance and predicted demand"></canvas></div>
      <p class="empty-state" id="demandForecastMessage">Loading demand history...</p>
      <div class="demand-summary" id="demandForecastSummary" hidden>
        <div><span>Average monthly issuance</span><strong id="demandAverage"></strong></div>
        <div><span id="demandNextLabel">Forecasted demand</span><strong id="demandNext"></strong></div>
        <div><span>Previous complete month</span><strong id="demandPrevious"></strong></div>
        <div><span>Office supply additions last month</span><strong id="demandReceipts"></strong></div>
        <div><span>Projected change</span><strong id="demandChange"></strong></div>
        <div><span>Demand trend</span><strong id="demandTrend"></strong></div>
      </div>
    </article>
  </section>

  <section id="inventoryHealthSection" class="inventory-table-section" aria-labelledby="inventoryTableTitle">
    <div class="section-heading"><h2 id="inventoryTableTitle">Inventory Health Table</h2><p>Office supplies grouped by current stock health.</p></div>
    <article class="panel">
      <div class="inventory-table-toolbar">
        <label class="mrp-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4 4"></path></svg><input id="inventoryTableSearch" type="search" aria-label="Search inventory table" placeholder="Search by Stock number, item name, or description..." autocomplete="off"></label>
        <div class="inventory-table-filters" role="group" aria-label="Filter inventory health">
          <button type="button" class="inventory-filter active" data-table-status="all">Show All</button>
          <button type="button" class="inventory-filter" data-table-status="safe"><i class="safe"></i> Safe</button>
          <button type="button" class="inventory-filter" data-table-status="low"><i class="low"></i> Low</button>
          <button type="button" class="inventory-filter" data-table-status="critical"><i class="critical"></i> Critical</button>
          <button type="button" class="inventory-filter" data-table-status="empty"><i class="empty"></i> Empty</button>
        </div>
      </div>
      <div class="inventory-table-wrap">
        <table class="inventory-health-table">
          <thead><tr><th>Stock Number</th><th>Item Name &amp; Description</th><th>Category</th><th>Current Quantity</th><th>Reorder Threshold</th><th>Health Status</th><th>Depletion / Duration</th></tr></thead>
          <tbody id="inventoryHealthTableBody"></tbody>
        </table>
        <div id="inventoryTableEmpty" class="empty-state" hidden>No inventory items match the current search or filter.</div>
      </div>
    </article>
  </section>

</main>

<script>
Chart.defaults.font.family = "'Century Gothic', Century Gothic, sans-serif";
const officePieValueLabels = {
  id: 'officePieValueLabels',
  afterDatasetsDraw(chart) {
    const dataset = chart.data.datasets[0];
    const meta = chart.getDatasetMeta(0);
    const total = dataset.data.reduce((sum, value) => sum + Number(value), 0);
    const context = chart.ctx;
    context.save();
    context.fillStyle = '#ffffff';
    context.font = '700 14px Century Gothic, sans-serif';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    meta.data.forEach((arc, index) => {
      const value = Number(dataset.data[index]);
      if (!value || !total) return;
      const angle = (arc.startAngle + arc.endAngle) / 2;
      const radius = (arc.outerRadius + arc.innerRadius) / 2;
      context.fillText(value.toLocaleString(), arc.x + Math.cos(angle) * radius, arc.y + Math.sin(angle) * radius);
    });
    context.restore();
  }
};

new Chart(document.getElementById('officeStatusChart'), {
  type: 'pie',
  data: {
    labels: ['Above reorder point', 'At reorder point', 'Empty'],
    datasets: [{
      data: [<?= $officeAboveReorder ?>, <?= $officeAtReorder ?>, <?= $officeEmpty ?>],
      backgroundColor: ['#43a047', '#fb8c00', '#e53935'],
      borderColor: '#ffffff',
      borderWidth: 3
    }]
  },
  plugins: [officePieValueLabels],
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
</script>

<script>
const state = { category: 'office-supplies', items: [], criticalItems: [], unifiedItems: [], inventoryTableStatus: 'all', velocityChart: null, forecastChart: null, mrpStockChart: null, mrpStatusFilter: 'all', mrpViewMode: 'all', mrpSearch: '' };
const analyticsCategoryKey = 'analytics:category:' + location.pathname;
try {
  const savedCategory = localStorage.getItem(analyticsCategoryKey);
  if (['office-supplies', 'semi-expendables', 'ppe'].includes(savedCategory)) state.category = savedCategory;
} catch (_) {}
const palette = { ink: '#263238', teal: '#4b7e87', rust: '#b44b31', grid: '#e7edef' };
let demandForecastChart = null;
let demandForecastRequest = 0;
let demandForecastData = null;
let demandForecastSourceHorizon = '12m';
async function loadDemandForecast(background = false) {
  const request = ++demandForecastRequest;
  const message = document.getElementById('demandForecastMessage');
  const horizon = document.getElementById('horizonSelect').value;
  const custom = horizon === 'custom';
  document.getElementById('demandCustomRange').hidden = !custom;
  const silent = background === true && demandForecastChart !== null;
  if (!silent) {
    document.getElementById('demandChartFrame').hidden = true;
    document.getElementById('demandForecastSummary').hidden = true;
    message.hidden = false;
  }
  // Custom ranges only select points from the last loaded forecast series.
  if (!custom) demandForecastSourceHorizon = horizon;
  const params = new URLSearchParams({ demand_forecast: '1', horizon: demandForecastSourceHorizon });
  if (custom) {
    const start = document.getElementById('demandStartMonth');
    const end = document.getElementById('demandEndMonth');
    if (!start.value || !end.value || !start.validity.valid || !end.validity.valid || start.value > end.value) {
      message.textContent = 'Select a valid range of complete months.';
      return;
    }

  }
  message.textContent = 'Loading demand history...';
  try {
    if (!custom || !demandForecastData || background === true) {
      const response = await fetch('analytics_data.php?' + params, { cache: 'no-store' });
      if (!response.ok) throw new Error('Demand history unavailable');
      const loaded = await response.json();
      if (request !== demandForecastRequest) return;
      demandForecastData = loaded;
    }
    if (request !== demandForecastRequest) return;
    let data = demandForecastData;
    if (custom) {
      const start = document.getElementById('demandStartMonth').value;
      const end = document.getElementById('demandEndMonth').value;
      const indices = data.months.reduce((selected, month, index) => {
        if (month >= start && month <= end) selected.push(index);
        return selected;
      }, []);
      data = {
        ...data,
        months: indices.map(index => data.months[index]),
        actual: indices.map(index => data.actual[index]),
        monthlyForecasts: indices.map(index => data.monthlyForecasts[index]),
        forecast: null,
      };
    }
    if (demandForecastChart) { demandForecastChart.destroy(); demandForecastChart = null; }
    const available = data.forecast !== null || data.monthlyForecasts.some(value => value !== null);
    document.getElementById('demandChartFrame').hidden = !available;
    document.getElementById('demandForecastSummary').hidden = custom || !available;
    message.hidden = available;
    if (!available) { message.textContent = custom ? 'No calculated forecasts in the selected range. Choose months within the loaded forecast history.' : 'Insufficient historical data to generate a forecast. Three complete calendar months are required for the SMA forecast.'; return; }
    const number = value => Number(value).toLocaleString();
    if (!custom) {
    document.getElementById('demandAverage').textContent = number(data.average) + ' items';
    document.getElementById('demandNext').textContent = (data.forecast === null ? 'Unavailable' : number(data.forecast) + ' items');
    document.getElementById('demandNextLabel').textContent = 'Forecast for ' + new Date(data.forecastMonth + '-01T00:00:00').toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    document.getElementById('demandPrevious').textContent = number(data.previous) + ' items';
    document.getElementById('demandReceipts').textContent = number(data.receiptsPreviousMonth) + ' items';
    document.getElementById('demandChange').textContent = data.changePercent === null ? 'Unavailable' : (data.changePercent > 0 ? '+' : '') + data.changePercent + '%';
    document.getElementById('demandTrend').textContent = data.trend;
    }
    const chartMonths = [...data.months];
    const chartActual = [...data.actual];
    const chartForecasts = [...data.monthlyForecasts];
    if (!custom && data.forecast !== null && !chartMonths.includes(data.forecastMonth)) {
      chartMonths.push(data.forecastMonth);
      chartActual.push(null);
      chartForecasts.push(data.forecast);
    }
    const labels = chartMonths.map(month =>
      new Date(month + '-01T00:00:00').toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric'
      })
    );
    const dark = document.body.classList.contains('dark-mode');
    const axisColor = dark ? '#cbd5e1' : '#526168';
    const stripeCanvas = document.createElement('canvas');
    stripeCanvas.width = stripeCanvas.height = 8;
    const stripeContext = stripeCanvas.getContext('2d');
    stripeContext.fillStyle = '#9bc5ea';
    stripeContext.fillRect(0, 0, 8, 8);
    stripeContext.strokeStyle = '#ffffff';
    stripeContext.lineWidth = 1;
    stripeContext.beginPath();
    for (let offset = -8; offset <= 8; offset += 8) {
      stripeContext.moveTo(offset, 8);
      stripeContext.lineTo(offset + 8, 0);
    }
    stripeContext.stroke();
    const forecastPattern = stripeContext.createPattern(stripeCanvas, 'repeat');
    const forecastBarOffset = {
      id: 'forecastBarOffset',
      beforeDatasetDraw(chart, args) {
        if (args.index !== 1) return;
        const actualBars = chart.getDatasetMeta(0).data;
        args.meta.data.forEach((bar, index) => {
          const actualBar = actualBars[index];
          if (actualBar && Number.isFinite(actualBar.x) && Number.isFinite(actualBar.width)) {
            bar.x = actualBar.x + actualBar.width * 0.35;
          }
        });
      }
    };
    demandForecastChart = new Chart(document.getElementById('demandForecastChart'), {
      type: 'bar', plugins: [forecastBarOffset], data: { labels, datasets: [
        {
          label: 'Actual issued',
          data: chartActual,
          backgroundColor: '#4a9ed8',
          borderRadius: 0,
          grouped: false,
          barPercentage: 0.7,
          categoryPercentage: 0.8,
          order: 0,
        },
        {
          label: 'SMA forecast (3 months)',
          data: chartForecasts,
          backgroundColor: forecastPattern,
          order: 1,
          borderWidth: 0,
          borderRadius: 0,
          grouped: false,
          barPercentage: 0.7,
          categoryPercentage: 0.8
        }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: axisColor } }, tooltip: { callbacks: { label: context => `${context.dataset.label}: ${number(context.parsed.y)} items` } } }, scales: { x: { ticks: { color: axisColor }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: axisColor, precision: 0 }, grid: { color: dark ? '#334155' : '#e7edef' }, title: { display: true, text: 'Items issued', color: axisColor } } } }
    });
  } catch (error) {
    if (request !== demandForecastRequest) return;
    if (silent) return;
    message.hidden = false;
    message.textContent = 'Unable to load demand history.';
    document.getElementById('demandChartFrame').hidden = true;
    document.getElementById('demandForecastSummary').hidden = true;
  }
}
const initialDemandForecast = loadDemandForecast();
function itemName(item) { return item.item_name || item.property_no || 'Unnamed item'; }
function movement(item) { return Number(item.usage_volume || item.quantity || 0); }
function shortName(name) { return name.length > 25 ? name.slice(0, 23) + '...' : name; }
function stockStatus(item) { const quantity = Number(item.quantity || 0); const reorderPoint = Number(item.reorder_point || 0); return quantity <= 0 ? 'critical' : quantity <= reorderPoint ? 'low' : 'safe'; }
function stockColor(status) { return status === 'critical' ? '#e53935' : status === 'low' ? '#fbc02d' : '#43a047'; }
function escapeTableText(value) { return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character])); }
function renderInventoryHealthTable() {
  const query = document.getElementById('inventoryTableSearch').value.trim().toLowerCase();
  let rows = state.unifiedItems.filter(item => state.inventoryTableStatus === 'all' || item.health === state.inventoryTableStatus);
  if (query) rows = rows.filter(item => `${item.sku} ${item.name} ${item.description}`.toLowerCase().includes(query));
  const sections = ['safe', 'low', 'critical', 'empty'];
  const body = document.getElementById('inventoryHealthTableBody');
  const empty = document.getElementById('inventoryTableEmpty');
  const html = [];
  sections.forEach(status => {
    const sectionRows = rows.filter(item => item.health === status);
    if (!sectionRows.length) return;
    const title = { safe: '🟢 Safe Stock', low: '🟡 Low Stock', critical: '🔴 Critical Stock (1–5 units)', empty: '⚫ Empty Stock' }[status];
    html.push(`<tr class="inventory-section-row ${status}"><th colspan="7">${title}<span>${sectionRows.length} item${sectionRows.length === 1 ? '' : 's'}</span></th></tr>`);
    sectionRows.forEach(item => {
      const duration = status === 'empty' ? `<strong class="inventory-duration">Stuck for ${Number(item.daysEmpty || 0).toLocaleString()} Days</strong>` : '<span class="inventory-active">Active Stock</span>';
      const badge = { safe: 'Safe', low: 'Low Stock', critical: 'Critical', empty: 'Empty' }[status];
      html.push(`<tr><td>${escapeTableText(item.sku || 'N/A')}</td><td><strong>${escapeTableText(item.name)}</strong><small>${escapeTableText(item.description || 'No description')}</small></td><td>${escapeTableText(item.category)}</td><td>${Number(item.quantity || 0).toLocaleString()} units</td><td>${item.reorderPoint === null ? '--' : Number(item.reorderPoint || 0).toLocaleString()}</td><td><span class="inventory-status ${status}"><i></i>${badge}</span></td><td>${duration}</td></tr>`);
    });
  });
  body.innerHTML = html.join('');
  empty.hidden = html.length > 0;
}
function prepareUnifiedItems(payloads) {
  const criticalById = new Map((payloads['office-supplies'].critical_depletion || []).map(item => [Number(item.item_id), item]));
  const categories = [
    ['office-supplies', 'Office Supplies']
  ];
  state.unifiedItems = [];
  categories.forEach(([key, category]) => {
    (payloads[key].items || payloads[key].supply_list || []).forEach(item => {
      const quantity = Number(item.quantity || 0);
      const critical = criticalById.get(Number(item.item_id));
      const normalized = { sku: item.stock_number || item.property_no || item.par_no, name: itemName(item), description: item.description || '', category, quantity, reorderPoint: item.reorder_point === undefined ? 0 : Number(item.reorder_point), daysEmpty: critical ? Number(critical.days_empty || 0) : 0 };
      normalized.health = quantity <= 0 ? 'empty' : quantity <= 5 ? 'critical' : quantity <= normalized.reorderPoint ? 'low' : 'safe';
      state.unifiedItems.push(normalized);
    });
  });
  state.unifiedItems.sort((a, b) => a.health === 'empty' && b.health !== 'empty' ? 1 : a.health !== 'empty' && b.health === 'empty' ? -1 : a.health === 'empty' ? b.daysEmpty - a.daysEmpty : a.quantity - b.quantity || a.name.localeCompare(b.name));
  renderInventoryHealthTable();
}
function loadUnifiedInventoryTable() {
  const categories = ['office-supplies'];
  return Promise.all(categories.map(category => fetch('analytics_data.php?category=' + encodeURIComponent(category)).then(response => {
    if (!response.ok) throw new Error(`Unable to load ${category} inventory data.`);
    return response.json();
  }))).then(results => prepareUnifiedItems(Object.fromEntries(categories.map((category, index) => [category, results[index]])))).catch(error => console.error('Unified inventory table failed to load:', error));
}
function categoryStatusText(value) {
  const label = String(value || 'Not recorded');
  const status = label.trim().toLowerCase();
  const color = status.includes('unserviceable') || status.includes('disposal') || status === 'disposed' || status === 'sold' ? 'red'
    : status.includes('transfer') || status.includes('reissued') ? 'blue'
    : ['active', 'issued', 'not issued', 'good', 'serviceable', 'fair'].includes(status) ? 'green' : '';
  return `<span class="category-status-text${color ? ' category-status-' + color : ''}">${escapeTableText(label)}</span>`;
}
let categoryRegisterStatus = 'all';
let categoryAssignmentItems = [];
function matchesCategoryRegisterStatus(item, filter, isSemi) {
  if (filter === 'all') return true;
  const statuses = (item.item_statuses || [item.issuance_status || item.status]).map(value => String(value || '').trim().toLowerCase());
  const disposal = Number(item.unserviceable_quantity || 0) > 0
    || [...statuses, String(item.condition || '').toLowerCase(), String(item.status || '').toLowerCase()]
      .some(value => /unserviceable|disposal|disposed|sold/.test(value));
  if (filter === 'disposal') return disposal;
  const issued = statuses.some(value => ['issued', 'transferred', 'reissued'].includes(value));
  if (filter === 'issued') return issued;
  return !disposal && !issued && Number(item.quantity || 0) > 0
    && statuses.some(value => ['not issued', 'returned', 'available'].includes(value));
}
function renderCategoryRegister() {
  const isSemi = state.category === 'semi-expendables';
  document.querySelector('[data-register-status="disposal"]').textContent = 'Unserviceable';
  document.getElementById('categoryRegisterTitle').textContent = 'Property Assignments';
  const columns = isSemi
    ? ['Property number', 'Item', 'Status', 'Current holder / office', 'Quantity']
    : ['Property number', 'Item', 'Status', 'Current holder / custodian', 'Officer in charge', 'Quantity'];
  document.getElementById('categoryRegisterHead').innerHTML = '<tr>' + columns.map(label => `<th scope="col">${label}</th>`).join('') + '</tr>';
  const query = document.getElementById('categoryRegisterSearch').value.trim().toLowerCase();
  const rows = categoryAssignmentItems.filter(item => matchesCategoryRegisterStatus(item, categoryRegisterStatus, isSemi)
    && (!query || [item.property_no, itemName(item), item.description, item.current_holder, item.officer_incharge, item.officer]
      .join(' ').toLowerCase().includes(query)));
  document.getElementById('categoryRegisterCount').textContent = `${rows.length.toLocaleString()} record${rows.length === 1 ? '' : 's'}`;
  document.getElementById('categoryRegisterBody').innerHTML = rows.length
    ? rows.map(item => {
      const name = `<strong>${escapeTableText(itemName(item))}</strong>` + (item.description ? `<div>${escapeTableText(item.description)}</div>` : '');
      const ppeStatus = String(item.condition || '').trim().toLowerCase() === 'unserviceable' && String(item.status || '').trim().toLowerCase() === 'active' ? 'Unserviceable' : item.status;
      const unserviceableQty = Number(item.unserviceable_quantity || 0);
      let semiStatus = (item.item_statuses || [item.issuance_status]).map(value => categoryStatusText(isSemi && /disposal|disposed/i.test(String(value)) ? 'Unserviceable' : value)).join('<br>');
      if (isSemi && unserviceableQty > 0) {
        const unserviceableStatus = categoryStatusText(`Unserviceable (${unserviceableQty.toLocaleString()} item${unserviceableQty === 1 ? '' : 's'})`);
        semiStatus = item.issuance_status === 'Disposed / For disposal' || item.issuance_status === 'Unserviceable'
          ? unserviceableStatus : semiStatus + '<br>' + unserviceableStatus;
      }
      const cells = isSemi
        ? [escapeTableText(item.property_no || 'N/A'), name, semiStatus, escapeTableText(item.current_holder || 'Not recorded'), Number(item.quantity || 0).toLocaleString()]
        : [escapeTableText(item.property_no || 'N/A'), name, (item.item_statuses || [ppeStatus]).map(categoryStatusText).join('<br>'), escapeTableText(item.current_holder || 'Unassigned'), escapeTableText(item.officer_incharge || 'Unassigned'), Number(item.quantity || 0).toLocaleString()];
      return '<tr>' + cells.map(cell => `<td>${cell}</td>`).join('') + '</tr>';
    }).join('')
    : `<tr><td colspan="${columns.length}" class="empty-state">${state.items.length ? 'No items match the current search or status filter.' : `No ${isSemi ? 'semi-expendable' : 'PPE'} items recorded.`}</td></tr>`;
}
let semiStatusChart = null;
function renderSemiStatusChart(container, summary) {
  const total = Number(summary.total || 0).toLocaleString();
  const labels = ['Unserviceable', 'Issued', 'Available / Not issued'];
  const values = [summary.unserviceable, summary.currently_issued, summary.not_issued].map(value => Number(value || 0));
  const colors = ['#dc2626', '#2563eb', '#16a34a'];
  const totalValue = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(summary.total_value || 0));
  container.innerHTML = `<article class="panel semi-status-panel"><h3>Semi-Expendables status</h3><div class="semi-status-layout"><div class="semi-donut-wrap"><canvas id="semiStatusChart" role="img" aria-label="Semi-Expendables: ${total} total items. ${labels.map((label, index) => `${label}: ${values[index].toLocaleString()}`).join('. ')}"></canvas><div class="semi-donut-center"><strong>${total}</strong><span>Total Items</span></div></div><div class="semi-status-details"><ul class="semi-status-legend">${labels.map((label, index) => `<li><i style="background:${colors[index]}" aria-hidden="true"></i><span>${label}</span><strong>${values[index].toLocaleString()}</strong></li>`).join('')}</ul><p class="panel-caption">Total Value: <strong>${totalValue}</strong></p></div></div></article>`;
  semiStatusChart = new Chart(document.getElementById('semiStatusChart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { display: false } } }
  });
  const categoryValues = summary.category_values || [];
  const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
  const maximum = Math.max(0, ...categoryValues.map(item => Number(item.value || 0)));
  const barColors = ['#2563eb'];
  container.insertAdjacentHTML('beforeend', `<article class="panel semi-value-panel"><h3>Value by Category</h3><div class="semi-value-bars">${categoryValues.length ? categoryValues.map((item, index) => {
    const value = Number(item.value || 0);
    const width = maximum > 0 ? Math.max(0, value / maximum * 100) : 0;
    return `<div class="semi-value-row"><div class="semi-value-label"><span>${escapeTableText(item.category)}</span><strong>${currency.format(value)}</strong></div><div class="semi-value-track" role="img" aria-label="${escapeTableText(item.category)}: ${currency.format(value)}"><div class="semi-value-fill" style="width:${width}%;background:${barColors[index % barColors.length]}"></div></div></div>`;
  }).join('') : '<p class="empty-state">No category values recorded.</p>'}</div></article>`);
}
function renderCategoryInsights(summary) {
  if (semiStatusChart) { semiStatusChart.destroy(); semiStatusChart = null; }
  const insights = document.getElementById('categoryInsights');
  const isSemi = state.category === 'semi-expendables';
  const isPpe = state.category === 'ppe';
  const conditionPanel = document.getElementById('ppeConditionPanel');
  document.getElementById('ppeOverviewPanels').hidden = !isPpe;
  if (isPpe) {
    let assigned = 0;
    let transferred = 0;
    let unassigned = 0;
    state.items.forEach(item => {
      const assignment = String(item.issuance_status || '').trim().toLowerCase();
      if (/unserviceable|disposed|sold/i.test(`${item.condition || ''} ${item.status || ''}`)) return;
      if (assignment === 'issued') assigned++;
      else if (['transferred', 'reissued'].includes(assignment)) transferred++;
      else if (Number(item.quantity || 0) > 0 && ['not issued', 'returned', 'available'].includes(assignment)) unassigned++;
    });
    document.getElementById('ppeAssignedCount').textContent = (assigned + transferred).toLocaleString();
    document.getElementById('ppeUnassignedCount').textContent = unassigned.toLocaleString();
    const serviceable = Number(summary.serviceable || 0);
    const unserviceable = Number(summary.unserviceable || 0);
    const total = serviceable + unserviceable;
    const percent = total > 0 ? serviceable / total * 100 : 0;
    conditionPanel.innerHTML = `<div class="ppe-condition-heading"><h3 id="ppeConditionTitle">PPE Condition Overview</h3><span class="ppe-condition-badge">${total > 0 ? percent.toLocaleString(undefined, { maximumFractionDigits: 1 }) + '% Serviceable' : 'No items'}</span></div><div class="ppe-condition-metric"><strong>${serviceable.toLocaleString()} / ${total.toLocaleString()}</strong><span>Serviceable items</span></div><div class="ppe-condition-track" role="img" aria-label="${serviceable} serviceable and ${unserviceable} unserviceable items"><div class="ppe-condition-fill" style="width:${percent}%"></div></div><div class="ppe-condition-legend"><span><i class="serviceable" aria-hidden="true"></i>Serviceable: <strong>${serviceable.toLocaleString()}</strong></span><span><i class="unserviceable" aria-hidden="true"></i>Unserviceable: <strong>${unserviceable.toLocaleString()}</strong></span></div>`;
  }
  if (isPpe) {
    conditionPanel.insertAdjacentHTML('beforeend', `<p class="panel-caption ppe-available-caption">Available: <strong>${Number(summary.available || 0).toLocaleString()}</strong> (included in serviceable items)</p>`);
  }
  const overview = document.getElementById('semiOverviewCards');
  overview.hidden = !isSemi;
  if (isSemi) {
    const icons = [
      '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9M8 5.25l8 4.5"/>',
      '<rect x="5" y="5" width="14" height="16" rx="2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 13 2 2 4-4"/>',
      '<rect x="4" y="4" width="16" height="4" rx="1"/><path d="M5 8v11h14V8M9 12h6"/>',
      '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18M16 14h5M6 5V3h12v2"/>'
    ];
    const cards = [
      ['Property Records', state.items.filter(item => item.item_id !== null).length.toLocaleString()],
      ['Issued Units', Number(summary.currently_issued || 0).toLocaleString()],
      ['Unserviceable Units', Number(summary.unserviceable || 0).toLocaleString()],
      ['Total Value', new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(Number(summary.total_value || 0))]
    ];
    overview.innerHTML = cards.map(([label, value], index) => `<article class="semi-overview-card"><div><span>${label}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[index]}</svg></div><strong>${value}</strong></article>`).join('');
  }
  insights.hidden = !isSemi && !isPpe;
  if (!isSemi && !isPpe) return;
  document.getElementById('categoryInsightsTitle').textContent = isSemi ? 'Semi-Expendables analytics' : 'PPE analytics';
  const summaryCards = document.getElementById('categorySummaryCards');
  summaryCards.classList.toggle('semi-chart-summary', isSemi);
  if (isSemi) {
    summaryCards.classList.remove('semi-summary', 'colored-summary');
    renderSemiStatusChart(summaryCards, summary);
    renderCategoryRegister();
    return;
  }
  summaryCards.classList.toggle('semi-summary', isSemi);
  summaryCards.classList.add('colored-summary');
  summaryCards.innerHTML = isSemi ? `<article class="category-summary-card total"><strong>${Number(summary.total || 0).toLocaleString()}</strong><span>Total Items</span></article><article class="category-summary-card issued"><strong>${Number(summary.not_issued || 0).toLocaleString()}</strong><span>Not issued</span></article><article class="category-summary-card active"><strong>${Number(summary.currently_issued || 0).toLocaleString()}</strong><span>Currently issued</span></article><article class="category-summary-card disposed"><strong>${Number(summary.disposed || 0).toLocaleString()}</strong><span>Disposed / For disposal</span></article>` : `<article class="category-summary-card total"><strong>${Number(summary.total || 0).toLocaleString()}</strong><span>Total Items</span></article><article class="category-summary-card issued"><strong>${Number(summary.serviceable || 0).toLocaleString()}</strong><span>Serviceable Items</span></article><article class="category-summary-card disposed"><strong>${Number(summary.unserviceable || 0).toLocaleString()}</strong><span>Unserviceable Items</span></article>`;
  if (isSemi || isPpe) {
    const totalValue = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(summary.total_value || 0));
    summaryCards.insertAdjacentHTML('beforeend', `<article class="category-summary-card value"><strong>${totalValue}</strong><span>Total Value</span></article>`);
  }
  renderCategoryRegister();
}
let categoryRequest = 0;
let categoryLoading = false;
function loadCategory(category) {
  try { localStorage.setItem(analyticsCategoryKey, category); } catch (_) {}
  const request = ++categoryRequest;
  categoryLoading = true;
  state.items = [];
  document.getElementById('categoryInsights').hidden = true;
  document.getElementById('semiOverviewCards').hidden = true;
  if (state.mrpStockChart) { state.mrpStockChart.destroy(); state.mrpStockChart = null; }
  const scrollPosition = window.scrollY;
  state.category = category;
  state.mrpStatusFilter = 'all';
  state.mrpViewMode = 'all';
  state.mrpSearch = '';
  document.getElementById('mrpSearchInput').value = '';
  document.querySelectorAll('[data-mrp-status]').forEach(button => button.classList.remove('active'));
  document.getElementById('mrpFilterSelect').value = 'all';
  document.querySelectorAll('.scope-tab').forEach(tab => tab.classList.toggle('active', tab.dataset.category === category));
  document.querySelector('.office-overview').hidden = category !== 'office-supplies';
  document.getElementById('mrpSection').hidden = category !== 'office-supplies';
  document.getElementById('demandForecastSection').hidden = category !== 'office-supplies';
  document.getElementById('inventoryHealthSection').hidden = category !== 'office-supplies';
  if (category === 'office-supplies') renderAllItemsChart();
  return fetch('analytics_data.php?category=' + encodeURIComponent(category)).then(response => response.json()).then(data => {
    if (request !== categoryRequest) return;
    state.items = data.items || data.supply_list || [];
    categoryAssignmentItems = data.assignment_items || state.items;
    state.criticalItems = data.critical_depletion || [];
    renderCategoryInsights(data.summary || {});
    if (category === 'office-supplies') renderAllItemsChart();
    window.scrollTo(0, scrollPosition);
  }).catch(() => {
    if (request === categoryRequest) document.getElementById('forecastTitle').textContent = 'Unable to load stock data';
  }).finally(() => { if (request === categoryRequest) categoryLoading = false; });
}
function renderVelocity() {
  const ordered = [...state.items].sort((a, b) => movement(b) - movement(a)).slice(0, 20);
  const labels = ordered.map(item => shortName(itemName(item)));
  const volume = ordered.map(item => movement(item));
  const speed = ordered.map(item => Math.round(movement(item) / Math.max(1, Number(item.quantity || 1)) * 100) / 100);
  if (state.velocityChart) state.velocityChart.destroy();
  state.velocityChart = new Chart(document.getElementById('velocityChart'), { type: 'line', data: { labels, datasets: [{ label: 'Usage volume', data: volume, borderColor: palette.rust, backgroundColor: 'rgba(180,75,49,.08)', fill: true, tension: .35, pointRadius: 3 }, { label: 'Movement speed', data: speed, borderColor: palette.teal, backgroundColor: 'transparent', tension: .35, pointRadius: 3 }] }, options: { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { position: 'top' } }, scales: { x: { ticks: { maxRotation: 45, minRotation: 0 }, grid: { display: false } }, y: { beginAtZero: true, title: { display: true, text: 'Observed movement' }, grid: { color: palette.grid } } } } });
}
function renderDiagnostics() {
  const sorted = [...state.items].sort((a, b) => movement(b) - movement(a)); const dormant = [...state.items].sort((a, b) => movement(a) - movement(b))[0]; const top = sorted[0]; const list = document.getElementById('diagnosticList');
  if (!top) { list.innerHTML = '<div class="empty-state">No items in this scope.</div>'; return; }
  list.innerHTML = `<div class="diagnostic-row"><strong><span class="status-dot"></span>Top consumed</strong><span>${shortName(itemName(top))}<br><b>${Math.round(movement(top)).toLocaleString()} movements</b></span></div><div class="diagnostic-row"><strong><span class="status-dot muted"></span>Dormant stock</strong><span>${shortName(itemName(dormant))}<br><b>${Math.round(movement(dormant)).toLocaleString()} movements</b></span></div><div class="diagnostic-row"><strong>Items in scope</strong><span><b>${state.items.length}</b> tracked positions</span></div>`;
}
function itemDescription(item) { return String(item.description || '').trim(); }
function itemSearchText(item) { return `${item.stock_number || item.property_no || ''} ${itemName(item)} ${itemDescription(item)}`.toLowerCase(); }
function itemLabel(item) { const description = itemDescription(item); return `${item.stock_number || item.property_no || 'N/A'} — ${itemName(item)}${description ? ` (${description})` : ''}`; }
function renderAllItemsChart() {
  // Destroy first so no old bars, hover targets, or selected items survive a tab change.
  if (state.mrpStockChart) { state.mrpStockChart.destroy(); state.mrpStockChart = null; }
  const items = MRPStock.selectItems(state.items, state.mrpStatusFilter, state.mrpSearch, MRPStock.businessDate(), state.mrpViewMode);
  const critical = state.mrpStatusFilter === 'critical';
  const emptyStock = state.mrpStatusFilter === 'empty';
  const canvas = document.getElementById('mrpStockChart');
  const empty = document.getElementById('mrpEmptyState');
  canvas.hidden = items.length === 0;
  empty.hidden = items.length > 0;
  empty.textContent = state.mrpSearch ? 'No matching items in this view.' : 'No items in this stock status.';
  document.getElementById('mrpNoResults').hidden = true;
  document.getElementById('criticalChartAction').hidden = !(critical || emptyStock);
  const sortOptions = document.getElementById('mrpFilterSelect').options;
  sortOptions[0].textContent = 'Sort by Item Name';
  sortOptions[1].textContent = 'Sort by Lowest Quantity First';
  sortOptions[2].textContent = 'Sort by Highest Quantity First';
  document.getElementById('forecastTitle').textContent = critical ? 'Critical stock — Quantity greater than 1 and less than 5' : 'Stock levels — Quantity (units)';
  if (emptyStock) document.getElementById('forecastTitle').textContent = 'Empty stock - Zero quantity';
  document.querySelectorAll('[data-mrp-status]').forEach(button => {
    const active = button.dataset.mrpStatus === state.mrpStatusFilter;
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
  });
  let details = document.getElementById('mrpCriticalDetails');
  if (!details) {
    details = document.createElement('div');
    details.id = 'mrpCriticalDetails';
    canvas.closest('.mrp-chart-sections').after(details);
  }
  details.hidden = !(critical || emptyStock);
  // Include readable cards for zero-quantity items, whose bars have no height.
  details.innerHTML = (critical || emptyStock) ? items.map(item =>
    `<article class="mrp-critical-item" tabindex="0" role="button" aria-expanded="false" aria-label="Show description for ${escapeTableText(item.itemName)}"><strong>${escapeTableText(item.sku)} — ${escapeTableText(item.itemName)}</strong>${(emptyStock ? ['Quantity: 0 units', ...MRPStock.tooltip(item)] : MRPStock.tooltip(item)).map((line, index) => `<p${index === 0 ? ' class="mrp-primary-metric"' : ''}>${escapeTableText(line)}</p>`).join('')}<p class="mrp-item-description" hidden><strong>Description:</strong> ${escapeTableText(item.description || 'No description available.')}</p><a href="office_supplies/add_multiple_items.php?item_id=${encodeURIComponent(item.id)}">Open Restock Inventory</a></article>`
  ).join('') : '';
  if (items.length) state.mrpStockChart = new Chart(canvas, MRPStock.chartConfig(items, state.mrpStatusFilter,
    item => { window.location.href = 'office_supplies/add_multiple_items.php?item_id=' + encodeURIComponent(item.id); }));
}
function renderForecast(item, labels, values) {
  const cleanValues = values.map(Number).filter(Number.isFinite); const base = cleanValues.length ? cleanValues : [Number(item.quantity || 0)]; const average = base.reduce((sum, value) => sum + value, 0) / base.length; const recent = base.slice(-3).reduce((sum, value) => sum + value, 0) / Math.min(3, base.length); const futureLabels = ['+1 mo', '+2 mo', '+3 mo', '+4 mo', '+5 mo', '+6 mo']; const forecast = futureLabels.map((_, index) => Math.max(0, Math.round(recent + ((recent - average) * (index + 1) / 3))));
  const chartLabels = (labels.length ? labels.slice(-12) : ['Current']).concat(futureLabels); const historical = new Array(chartLabels.length).fill(null); const predicted = new Array(chartLabels.length).fill(null); base.slice(-12).forEach((value, index) => historical[index] = value); predicted[Math.max(0, base.slice(-12).length - 1)] = base[base.length - 1]; forecast.forEach((value, index) => predicted[base.slice(-12).length + index] = value);
  if (state.forecastChart) state.forecastChart.destroy(); state.forecastChart = new Chart(document.getElementById('forecastChart'), { type: 'line', data: { labels: chartLabels, datasets: [{ label: 'Historical baseline', data: historical, borderColor: palette.rust, backgroundColor: 'rgba(180,75,49,.08)', fill: true, tension: .35, pointRadius: 2 }, { label: 'Predicted demand', data: predicted, borderColor: palette.teal, borderDash: [6, 5], tension: .35, pointRadius: 2 }] }, options: { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Units' }, grid: { color: palette.grid } }, x: { grid: { display: false } } } } });
  const onHand = Number(item.quantity || 0); const monthlyDemand = Math.max(1, recent); const monthsLeft = Math.floor(onHand / monthlyDemand); const depletion = new Date(); depletion.setMonth(depletion.getMonth() + monthsLeft); document.getElementById('riskBanner').innerHTML = `<span class="status-dot ${monthsLeft < 2 ? '' : 'muted'}"></span>${monthsLeft < 2 ? '<b>Projected depletion:</b>' : '<b>Projected coverage:</b>'} ${depletion.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} <span style="margin-left:auto;color:#9a6a4d;">${monthsLeft} months of coverage</span>`;
}
function renderAging() { const values = state.items.map(item => Number(item.capital_value || item.quantity || 0)); const total = values.reduce((sum, value) => sum + value, 0) || 1; const tiers = [{ label: '0-90 days', value: total * .42, cls: '' }, { label: '91-180 days', value: total * .33, cls: '' }, { label: '181-365 days', value: total * .18, cls: 'warn' }, { label: 'Over 1 year', value: total * .07, cls: 'warn' }]; document.getElementById('agingTiers').innerHTML = tiers.map(tier => `<div class="tier"><span class="tier-label">${tier.label}</span><span class="tier-track"><span class="tier-fill ${tier.cls}" style="width:${Math.max(3, tier.value / total * 100)}%"></span></span><span class="tier-value">$${Math.round(tier.value).toLocaleString()}</span></div>`).join(''); const dormant = [...state.items].sort((a, b) => movement(a) - movement(b)).slice(0, 4); document.getElementById('agingTable').innerHTML = dormant.map(item => `<tr><td>${shortName(itemName(item))}</td><td>$${Number(item.capital_value || item.quantity || 0).toLocaleString()}</td><td><span class="status-dot muted"></span>Dormant</td></tr>`).join(''); }
document.querySelectorAll('[data-mrp-status]').forEach(button => button.addEventListener('click', () => {
  state.mrpStatusFilter = button.dataset.mrpStatus;
  renderAllItemsChart();
}));
document.getElementById('mrpSection').addEventListener('click', event => {
  const card = event.target.closest('.mrp-critical-item');
  if (!card || event.target.closest('a')) return;
  const description = card.querySelector('.mrp-item-description');
  description.hidden = !description.hidden;
  card.setAttribute('aria-expanded', String(!description.hidden));
});
document.getElementById('mrpSection').addEventListener('keydown', event => {
  if (event.target.matches('.mrp-critical-item') && (event.key === 'Enter' || event.key === ' ')) {
    event.preventDefault();
    event.target.click();
  }
});
document.getElementById('mrpFilterSelect').addEventListener('change', event => {
  state.mrpViewMode = event.target.value;
  renderAllItemsChart();
});
document.getElementById('mrpSearchInput').addEventListener('input', event => { state.mrpSearch = event.target.value.trim().toLowerCase(); renderAllItemsChart(); });
document.getElementById('categoryRegisterSearch').addEventListener('input', renderCategoryRegister);
document.querySelectorAll('[data-register-status]').forEach(button => button.addEventListener('click', () => {
  categoryRegisterStatus = button.dataset.registerStatus;
  document.querySelectorAll('[data-register-status]').forEach(control => {
    const active = control.dataset.registerStatus === categoryRegisterStatus;
    control.classList.toggle('active', active);
    control.setAttribute('aria-pressed', String(active));
  });
  renderCategoryRegister();
}));

document.getElementById('inventoryTableSearch').addEventListener('input', renderInventoryHealthTable);
document.querySelectorAll('[data-table-status]').forEach(button => button.addEventListener('click', () => { state.inventoryTableStatus = button.dataset.tableStatus; document.querySelectorAll('[data-table-status]').forEach(item => item.classList.toggle('active', item.dataset.tableStatus === state.inventoryTableStatus)); renderInventoryHealthTable(); }));
document.querySelectorAll('.scope-tab').forEach(tab => tab.addEventListener('click', event => { event.preventDefault(); loadCategory(tab.dataset.category); }));
document.getElementById('horizonSelect').addEventListener('change', loadDemandForecast);
const demandLastMonth = <?= json_encode((new DateTimeImmutable('first day of last month', new DateTimeZone('Asia/Manila')))->format('Y-m')) ?>;
['demandStartMonth', 'demandEndMonth'].forEach(id => {
  const input = document.getElementById(id);
  input.max = demandLastMonth;
  input.addEventListener('change', loadDemandForecast);
});
const initialCategory = loadCategory(state.category);
const initialInventory = loadUnifiedInventoryTable();
const analyticsPageLoaded = document.readyState === 'complete' ? Promise.resolve() : new Promise(resolve => window.addEventListener('load', resolve, { once: true }));
Promise.allSettled([initialDemandForecast, initialCategory, initialInventory, analyticsPageLoaded, document.fonts.ready]).then(() => {
  requestAnimationFrame(() => requestAnimationFrame(() => {
    if (analyticsRestorePending) {
      window.scrollTo({ left: analyticsSavedScroll.x, top: analyticsSavedScroll.y, behavior: 'instant' });
    }
    cancelAnalyticsRestore();
    if ('scrollRestoration' in history) history.scrollRestoration = 'auto';
  }));
});
// Recalculate dates while open; fetch current balances every minute and on return.
let mrpRefreshing = false;
async function refreshMrp() {
  if (document.hidden || state.category !== 'office-supplies' || categoryLoading || mrpRefreshing) return;
  renderAllItemsChart();
  const request = categoryRequest;
  mrpRefreshing = true;
  try {
    const response = await fetch('analytics_data.php?category=office-supplies', { cache: 'no-store' });
    if (!response.ok) throw new Error('Stock refresh failed');
    const data = await response.json();
    if (request !== categoryRequest || state.category !== 'office-supplies') return;
    state.items = data.supply_list || [];
    state.criticalItems = data.critical_depletion || [];
    prepareUnifiedItems({ 'office-supplies': data });
    renderAllItemsChart();
  } catch (error) {
    document.getElementById('forecastTitle').textContent += ' — refresh failed; showing last loaded stock';
  } finally { mrpRefreshing = false; }
}
let mrpBusinessDate = MRPStock.businessDate();
setInterval(() => {
  const today = MRPStock.businessDate();
  if (today !== mrpBusinessDate) {
    mrpBusinessDate = today;
    if (state.category === 'office-supplies') renderAllItemsChart();
  }
}, 1000);
setInterval(refreshMrp, 60000);
let demandRefreshing = false;
async function refreshDemandForecast() {
  if (document.hidden || state.category !== 'office-supplies' || demandRefreshing) return;
  demandRefreshing = true;
  try {
    await loadDemandForecast(true);
  } finally {
    demandRefreshing = false;
  }
}
setInterval(refreshDemandForecast, 15000);
window.addEventListener('focus', () => { refreshMrp(); refreshDemandForecast(); });
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) { refreshMrp(); refreshDemandForecast(); }
});
window.addEventListener('inventory:updated', () => { refreshMrp(); refreshDemandForecast(); });
</script>
</body>
</html>
