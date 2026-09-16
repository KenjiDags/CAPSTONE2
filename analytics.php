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
  <link rel="stylesheet" href="css/analytics.css?v=<?= time() ?>">
  <script src="js/chart.min.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="analytics-shell">
  <header class="analytics-header">
    <div><div class="eyebrow">TESDA Inventory / Intelligence</div><h1>Inventory Analytics</h1></div>
    <p class="header-note">Movement signals, demand horizons, and capital exposure<br>for informed replenishment planning.</p>
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
    <div class="scope-control"><label for="horizonSelect">Analysis horizon</label><select id="horizonSelect"><option value="ytd">Year to date</option><option value="12m" selected>Past 12 months</option><option value="24m">Past 24 months</option><option value="custom">Custom range</option></select></div>
  </section>

  <section id="categoryInsights" class="category-insights" hidden>
    <div class="section-heading"><h2 id="categoryInsightsTitle">Category analytics</h2><p id="categoryInsightsSubtitle">Current asset condition and action signals</p></div>
    <div class="category-summary-cards" id="categorySummaryCards"></div>
    <div id="semiInsights" class="category-view" hidden>
      <article class="panel category-chart-panel"><h3>Semi-Expendables condition</h3><p class="panel-caption">Current balance across active and depleted property records.</p><div class="category-chart-frame"><canvas id="semiStatusChart"></canvas></div></article>
      <article class="panel"><h3>Inventory action queue</h3><p class="panel-caption">Semi-expendable records with no remaining balance.</p><div class="category-list" id="semiActionList"></div></article>
    </div>
    <div id="ppeInsights" class="category-view" hidden>
      <article class="panel category-chart-panel"><h3>Serviceability health</h3><p class="panel-caption">Equipment condition across the current PPE register.</p><div class="category-chart-frame"><canvas id="ppeServiceabilityChart"></canvas></div></article>
      <article class="panel ppe-actions-panel"><h3>Equipment maintenance &amp; action</h3><p class="panel-caption">Unserviceable equipment requiring repair or replacement.</p><div class="ppe-table-wrap"><table class="ppe-action-table"><thead><tr><th>Asset code</th><th>Equipment</th><th>Location / holder</th><th>Reported issue</th><th>Action</th></tr></thead><tbody id="ppeActionTable"></tbody></table></div></article>
    </div>
  </section>

  <section id="mrpSection" class="mrp-section">
    <div class="section-heading"><h2>Material requirements planning</h2><p>Baseline history compared with the demand trajectory</p></div>
    <article class="panel">
      <div class="mrp-toolbar"><div><h3 id="forecastTitle">Stock levels by item</h3><p class="panel-caption" style="margin-bottom:0;">All items are shown together. Select a bar for its deep-dive details.</p></div><div class="mrp-controls"><label class="mrp-search"><span aria-hidden="true">&#128269;</span><input id="mrpSearchInput" type="search" aria-label="Search stock items" placeholder="Search by SKU, item name, or description..." autocomplete="off"></label><select class="mrp-filter-select" id="mrpFilterSelect" aria-label="Filter and sort stock chart"><option value="all">Show All Items</option><option value="attention">Show Low &amp; Critical Stock Only</option><option value="lowest">Sort by Lowest Quantity First</option></select></div></div>
      <div class="mrp-status-legend"><button type="button" data-mrp-status="safe"><i class="safe"></i>Safe</button><button type="button" data-mrp-status="low"><i class="low"></i>Low Stock</button><button type="button" data-mrp-status="critical"><i class="critical"></i>Critical</button><a id="criticalChartAction" class="critical-chart-action" href="add_multiple_items.php" hidden>Open Restock Inventory</a></div>
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

  <section class="inventory-table-section" aria-labelledby="inventoryTableTitle">
    <div class="section-heading"><h2 id="inventoryTableTitle">Inventory Health Table</h2><p>All inventory grouped by current stock health.</p></div>
    <article class="panel">
      <div class="inventory-table-toolbar">
        <label class="mrp-search inventory-table-search"><span aria-hidden="true">&#128269;</span><input id="inventoryTableSearch" type="search" aria-label="Search inventory table" placeholder="Search by SKU, item name, or description..." autocomplete="off"></label>
        <div class="inventory-table-filters" role="group" aria-label="Filter inventory health">
          <button type="button" class="inventory-filter active" data-table-status="all">Show All</button>
          <button type="button" class="inventory-filter" data-table-status="safe"><i class="safe"></i> Safe</button>
          <button type="button" class="inventory-filter" data-table-status="low"><i class="low"></i> Low</button>
          <button type="button" class="inventory-filter" data-table-status="critical"><i class="critical"></i> Critical</button>
        </div>
      </div>
      <div class="inventory-table-wrap">
        <table class="inventory-health-table">
          <thead><tr><th>SKU / Stock Number</th><th>Item Name &amp; Description</th><th>Category</th><th>Current Quantity</th><th>Reorder Threshold</th><th>Health Status</th><th>Depletion / Duration</th></tr></thead>
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
const state = { category: 'office-supplies', items: [], criticalItems: [], unifiedItems: [], inventoryTableStatus: 'all', velocityChart: null, forecastChart: null, mrpStockChart: null, semiStatusChart: null, ppeServiceabilityChart: null, mrpStatusFilter: 'all', mrpViewMode: 'all', mrpSearch: '' };
const palette = { ink: '#263238', teal: '#4b7e87', rust: '#b44b31', grid: '#e7edef' };
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
  const sections = ['safe', 'low', 'critical'];
  const body = document.getElementById('inventoryHealthTableBody');
  const empty = document.getElementById('inventoryTableEmpty');
  const html = [];
  sections.forEach(status => {
    const sectionRows = rows.filter(item => item.health === status);
    if (!sectionRows.length) return;
    const title = status === 'safe' ? '🟢 Safe Stock' : status === 'low' ? '🟡 Low Stock' : '🔴 Critical Out of Stock';
    html.push(`<tr class="inventory-section-row ${status}"><th colspan="7">${title}<span>${sectionRows.length} item${sectionRows.length === 1 ? '' : 's'}</span></th></tr>`);
    sectionRows.forEach(item => {
      const duration = status === 'critical' ? `<strong class="inventory-duration">Stuck for ${Number(item.daysEmpty || 0).toLocaleString()} Days</strong>` : '<span class="inventory-active">Active Stock</span>';
      const badge = status === 'safe' ? 'Safe' : status === 'low' ? 'Low Stock' : 'Critical';
      html.push(`<tr><td>${escapeTableText(item.sku || 'N/A')}</td><td><strong>${escapeTableText(item.name)}</strong><small>${escapeTableText(item.description || 'No description')}</small></td><td>${escapeTableText(item.category)}</td><td>${Number(item.quantity || 0).toLocaleString()} units</td><td>${item.reorderPoint === null ? '--' : Number(item.reorderPoint || 0).toLocaleString()}</td><td><span class="inventory-status ${status}"><i></i>${badge}</span></td><td>${duration}</td></tr>`);
    });
  });
  body.innerHTML = html.join('');
  empty.hidden = html.length > 0;
}
function prepareUnifiedItems(payloads) {
  const criticalById = new Map((payloads['office-supplies'].critical_depletion || []).map(item => [Number(item.item_id), item]));
  const categories = [
    ['office-supplies', 'Office Supplies'],
    ['semi-expendables', 'Semi-Expendables'],
    ['ppe', 'PPE']
  ];
  state.unifiedItems = [];
  categories.forEach(([key, category]) => {
    (payloads[key].items || payloads[key].supply_list || []).forEach(item => {
      const quantity = Number(item.quantity || 0);
      const critical = criticalById.get(Number(item.item_id));
      const normalized = { sku: item.stock_number || item.property_no || item.par_no, name: itemName(item), description: item.description || '', category, quantity, reorderPoint: item.reorder_point === undefined ? 0 : Number(item.reorder_point), daysEmpty: critical ? Number(critical.days_empty || 0) : 0 };
      normalized.health = stockStatus({ quantity: normalized.quantity, reorder_point: normalized.reorderPoint });
      state.unifiedItems.push(normalized);
    });
  });
  state.unifiedItems.sort((a, b) => a.health === 'critical' && b.health !== 'critical' ? 1 : a.health !== 'critical' && b.health === 'critical' ? -1 : a.health === 'critical' ? b.daysEmpty - a.daysEmpty : a.quantity - b.quantity || a.name.localeCompare(b.name));
  renderInventoryHealthTable();
}
function loadUnifiedInventoryTable() {
  const horizon = document.getElementById('horizonSelect').value;
  const categories = ['office-supplies', 'semi-expendables', 'ppe'];
  Promise.all(categories.map(category => fetch('analytics_data.php?category=' + encodeURIComponent(category) + '&horizon=' + encodeURIComponent(horizon)).then(response => {
    if (!response.ok) throw new Error(`Unable to load ${category} inventory data.`);
    return response.json();
  }))).then(results => prepareUnifiedItems(Object.fromEntries(categories.map((category, index) => [category, results[index]])))).catch(error => console.error('Unified inventory table failed to load:', error));
}
function renderCategoryInsights(summary) {
  const insights = document.getElementById('categoryInsights');
  const semiView = document.getElementById('semiInsights');
  const ppeView = document.getElementById('ppeInsights');
  const isSemi = state.category === 'semi-expendables';
  const isPpe = state.category === 'ppe';
  insights.hidden = !isSemi && !isPpe;
  semiView.hidden = !isSemi;
  ppeView.hidden = !isPpe;
  if (!isSemi && !isPpe) return;
  document.getElementById('categoryInsightsTitle').textContent = isSemi ? 'Semi-Expendables analytics' : 'PPE analytics';
  document.getElementById('categoryInsightsSubtitle').textContent = isSemi ? 'Condition distribution and balance actions' : 'Serviceability health and maintenance actions';
  const summaryCards = document.getElementById('categorySummaryCards');
  summaryCards.innerHTML = isSemi ? `<article class="category-summary-card total"><strong>${Number(summary.total || 0).toLocaleString()}</strong><span>Total Items</span></article><article class="category-summary-card issued"><strong>${Number(summary.not_issued || 0).toLocaleString()}</strong><span>Not issued</span></article><article class="category-summary-card active"><strong>${Number(summary.currently_issued || 0).toLocaleString()}</strong><span>Currently issued</span></article><article class="category-summary-card disposed"><strong>${Number(summary.disposed || 0).toLocaleString()}</strong><span>Disposed / For disposal</span></article>` : `<article class="category-summary-card total"><strong>${Number(summary.total || 0).toLocaleString()}</strong><span>Total Items</span></article><article class="category-summary-card issued"><strong>${Number(summary.serviceable || 0).toLocaleString()}</strong><span>Serviceable Items</span></article><article class="category-summary-card disposed"><strong>${Number(summary.unserviceable || 0).toLocaleString()}</strong><span>Unserviceable Items</span></article>`;
  if (isSemi) renderSemiInsights();
  if (isPpe) renderPpeInsights();
}
function renderSemiInsights() {
  const active = state.items.filter(item => String(item.status).toLowerCase() !== 'depleted').length;
  const depleted = state.items.length - active;
  if (state.semiStatusChart) state.semiStatusChart.destroy();
  state.semiStatusChart = new Chart(document.getElementById('semiStatusChart'), { type: 'doughnut', data: { labels: ['Active balance', 'Depleted'], datasets: [{ data: [active, depleted], backgroundColor: ['#43a047', '#d28a3d'], borderColor: '#ffffff', borderWidth: 3 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom' } } } });
  const depletedItems = state.items.filter(item => String(item.status).toLowerCase() === 'depleted').slice(0, 6);
  document.getElementById('semiActionList').innerHTML = depletedItems.length ? depletedItems.map(item => `<div class="category-list-row"><span>${item.property_no || 'N/A'} - ${itemName(item)}</span><strong>Replenish</strong></div>`).join('') : '<div class="empty-state">No depleted semi-expendable records.</div>';
}
function renderPpeInsights() {
  const serviceable = state.items.filter(item => ['good', 'serviceable', 'fair'].includes(String(item.condition || '').toLowerCase()) && String(item.status || '').toLowerCase() !== 'unserviceable').length;
  const unserviceable = state.items.length - serviceable;
  if (state.ppeServiceabilityChart) state.ppeServiceabilityChart.destroy();
  state.ppeServiceabilityChart = new Chart(document.getElementById('ppeServiceabilityChart'), { type: 'doughnut', data: { labels: ['Serviceable Items', 'Unserviceable Items'], datasets: [{ data: [serviceable, unserviceable], backgroundColor: ['#43a047', '#e53935'], borderColor: '#ffffff', borderWidth: 3 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom' } } } });
  const actionItems = state.items.filter(item => !(['good', 'serviceable', 'fair'].includes(String(item.condition || '').toLowerCase())) || String(item.status || '').toLowerCase() === 'unserviceable');
  document.getElementById('ppeActionTable').innerHTML = actionItems.length ? actionItems.map(item => { const repair = ['for repair', 'fair'].includes(String(item.status || item.condition || '').toLowerCase()); return `<tr><td>${item.property_no || 'N/A'}</td><td>${itemName(item)}</td><td>${item.officer || 'Unassigned'}</td><td>${item.remarks || item.condition || 'Unserviceable'}</td><td><span class="action-pill ${repair ? 'repair' : ''}">${repair ? 'Under Repair' : 'Marked for Replacement'}</span></td></tr>`; }).join('') : '<tr><td colspan="5">No unserviceable equipment requires action.</td></tr>';
}
function loadCategory(category) {
  const scrollPosition = window.scrollY;
  state.category = category;
  state.mrpStatusFilter = 'all';
  state.mrpViewMode = 'all';
  state.mrpSearch = '';
  document.getElementById('mrpSearchInput').value = '';
  document.querySelectorAll('[data-mrp-status]').forEach(button => button.classList.remove('active'));
  document.getElementById('mrpFilterSelect').value = 'all';
  document.querySelectorAll('.scope-tab').forEach(tab => tab.classList.toggle('active', tab.dataset.category === category));
  document.getElementById('mrpSection').hidden = category !== 'office-supplies';
  const horizon = document.getElementById('horizonSelect').value;
  fetch('analytics_data.php?category=' + encodeURIComponent(category) + '&horizon=' + encodeURIComponent(horizon)).then(response => response.json()).then(data => {
    state.items = data.items || data.supply_list || [];
    state.criticalItems = data.critical_depletion || [];
    renderCategoryInsights(data.summary || {});
    if (category === 'office-supplies') renderAllItemsChart();
    window.scrollTo(0, scrollPosition);
  }).catch(() => {});
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
  const isCriticalView = state.mrpStatusFilter === 'critical';
  let displayItems = isCriticalView ? state.criticalItems.map(item => ({ ...item, quantity: Number(item.days_empty || 1), reorder_point: 0, usage_volume: 0 })) : [...state.items];
  if (state.mrpSearch) displayItems = displayItems.filter(item => itemSearchText(item).includes(state.mrpSearch));
  if (state.mrpStatusFilter === 'low') displayItems = displayItems.filter(item => stockStatus(item) === 'low');
  if (state.mrpStatusFilter === 'attention') displayItems = displayItems.filter(item => ['low', 'critical'].includes(stockStatus(item)));
  if (state.mrpStatusFilter === 'safe') displayItems = displayItems.filter(item => stockStatus(item) === 'safe');
  if (isCriticalView) displayItems.sort((a, b) => Number(b.quantity || 0) - Number(a.quantity || 0) || itemName(a).localeCompare(itemName(b)));
  if (state.mrpViewMode === 'lowest') displayItems.sort((a, b) => Number(a.quantity || 0) - Number(b.quantity || 0));
  const canvas = document.getElementById('mrpStockChart');
  const emptyState = document.getElementById('mrpEmptyState');
  const noResults = document.getElementById('mrpNoResults');
  const criticalChartAction = document.getElementById('criticalChartAction');
  canvas.hidden = false;
  emptyState.hidden = true;
  noResults.hidden = true;
  criticalChartAction.hidden = state.mrpStatusFilter !== 'critical';
  if (!displayItems.length) {
    if (state.mrpStockChart) state.mrpStockChart.destroy();
    canvas.hidden = true;
    const lowStockEmpty = state.mrpStatusFilter === 'low' && !state.mrpSearch;
    const criticalStockEmpty = state.mrpStatusFilter === 'critical' && !state.mrpSearch;
    emptyState.hidden = !(criticalStockEmpty || (!lowStockEmpty && !state.mrpSearch));
    noResults.hidden = lowStockEmpty || !state.mrpSearch;
    return;
  }
  const labels = displayItems.map(item => shortName(itemName(item)));
  const quantities = displayItems.map(item => Number(item.quantity || 0));
  const colors = displayItems.map(item => isCriticalView ? '#e53935' : stockColor(stockStatus(item)));
  if (state.mrpStockChart) state.mrpStockChart.destroy();
  state.mrpStockChart = new Chart(document.getElementById('mrpStockChart'), { type: 'bar', data: { labels, datasets: [{ label: isCriticalView ? 'Days out of stock' : 'Quantity in units', data: quantities, backgroundColor: colors, borderColor: colors, borderWidth: 1, borderRadius: 4, borderSkipped: false, minBarLength: 4, barPercentage: .78, categoryPercentage: .84 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { title: contexts => itemLabel(displayItems[contexts[0].dataIndex]), label: context => { const item = displayItems[context.dataIndex]; if (isCriticalView) return `${Number(item.quantity || 0).toLocaleString()} days out of stock`; const status = stockStatus(item); return [`Quantity: ${Number(item.quantity || 0).toLocaleString()} units`, `Status: ${status === 'low' ? 'Low Stock' : 'Safe'}`, `Movement: ${movement(item).toLocaleString()} units`]; } } } }, scales: { x: { title: { display: true, text: isCriticalView ? 'Critical stock items' : 'Active stock items' }, ticks: { autoSkip: true, maxRotation: 55, minRotation: 35 }, grid: { display: false } }, y: { beginAtZero: true, title: { display: true, text: isCriticalView ? 'Days out of stock' : 'Quantity (units)' }, grid: { color: palette.grid } } } } });
}
function renderForecast(item, labels, values) {
  const cleanValues = values.map(Number).filter(Number.isFinite); const base = cleanValues.length ? cleanValues : [Number(item.quantity || 0)]; const average = base.reduce((sum, value) => sum + value, 0) / base.length; const recent = base.slice(-3).reduce((sum, value) => sum + value, 0) / Math.min(3, base.length); const futureLabels = ['+1 mo', '+2 mo', '+3 mo', '+4 mo', '+5 mo', '+6 mo']; const forecast = futureLabels.map((_, index) => Math.max(0, Math.round(recent + ((recent - average) * (index + 1) / 3))));
  const chartLabels = (labels.length ? labels.slice(-12) : ['Current']).concat(futureLabels); const historical = new Array(chartLabels.length).fill(null); const predicted = new Array(chartLabels.length).fill(null); base.slice(-12).forEach((value, index) => historical[index] = value); predicted[Math.max(0, base.slice(-12).length - 1)] = base[base.length - 1]; forecast.forEach((value, index) => predicted[base.slice(-12).length + index] = value);
  if (state.forecastChart) state.forecastChart.destroy(); state.forecastChart = new Chart(document.getElementById('forecastChart'), { type: 'line', data: { labels: chartLabels, datasets: [{ label: 'Historical baseline', data: historical, borderColor: palette.rust, backgroundColor: 'rgba(180,75,49,.08)', fill: true, tension: .35, pointRadius: 2 }, { label: 'Predicted demand', data: predicted, borderColor: palette.teal, borderDash: [6, 5], tension: .35, pointRadius: 2 }] }, options: { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Units' }, grid: { color: palette.grid } }, x: { grid: { display: false } } } } });
  const onHand = Number(item.quantity || 0); const monthlyDemand = Math.max(1, recent); const monthsLeft = Math.floor(onHand / monthlyDemand); const depletion = new Date(); depletion.setMonth(depletion.getMonth() + monthsLeft); document.getElementById('riskBanner').innerHTML = `<span class="status-dot ${monthsLeft < 2 ? '' : 'muted'}"></span>${monthsLeft < 2 ? '<b>Projected depletion:</b>' : '<b>Projected coverage:</b>'} ${depletion.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} <span style="margin-left:auto;color:#9a6a4d;">${monthsLeft} months of coverage</span>`;
}
function renderAging() { const values = state.items.map(item => Number(item.capital_value || item.quantity || 0)); const total = values.reduce((sum, value) => sum + value, 0) || 1; const tiers = [{ label: '0-90 days', value: total * .42, cls: '' }, { label: '91-180 days', value: total * .33, cls: '' }, { label: '181-365 days', value: total * .18, cls: 'warn' }, { label: 'Over 1 year', value: total * .07, cls: 'warn' }]; document.getElementById('agingTiers').innerHTML = tiers.map(tier => `<div class="tier"><span class="tier-label">${tier.label}</span><span class="tier-track"><span class="tier-fill ${tier.cls}" style="width:${Math.max(3, tier.value / total * 100)}%"></span></span><span class="tier-value">$${Math.round(tier.value).toLocaleString()}</span></div>`).join(''); const dormant = [...state.items].sort((a, b) => movement(a) - movement(b)).slice(0, 4); document.getElementById('agingTable').innerHTML = dormant.map(item => `<tr><td>${shortName(itemName(item))}</td><td>$${Number(item.capital_value || item.quantity || 0).toLocaleString()}</td><td><span class="status-dot muted"></span>Dormant</td></tr>`).join(''); }
document.querySelectorAll('[data-mrp-status]').forEach(button => button.addEventListener('click', () => { const status = button.dataset.mrpStatus; state.mrpStatusFilter = state.mrpStatusFilter === status ? 'all' : status; document.querySelectorAll('[data-mrp-status]').forEach(item => item.classList.toggle('active', item.dataset.mrpStatus === state.mrpStatusFilter)); document.getElementById('mrpFilterSelect').value = 'all'; state.mrpViewMode = 'all'; renderAllItemsChart(); }));
document.getElementById('mrpFilterSelect').addEventListener('change', event => { state.mrpStatusFilter = event.target.value === 'attention' ? 'attention' : 'all'; state.mrpViewMode = event.target.value === 'lowest' ? 'lowest' : 'all'; document.querySelectorAll('[data-mrp-status]').forEach(item => item.classList.remove('active')); renderAllItemsChart(); });
document.getElementById('mrpSearchInput').addEventListener('input', event => { state.mrpSearch = event.target.value.trim().toLowerCase(); renderAllItemsChart(); });
document.getElementById('inventoryTableSearch').addEventListener('input', renderInventoryHealthTable);
document.querySelectorAll('[data-table-status]').forEach(button => button.addEventListener('click', () => { state.inventoryTableStatus = button.dataset.tableStatus; document.querySelectorAll('[data-table-status]').forEach(item => item.classList.toggle('active', item.dataset.tableStatus === state.inventoryTableStatus)); renderInventoryHealthTable(); }));
document.querySelectorAll('.scope-tab').forEach(tab => tab.addEventListener('click', event => { event.preventDefault(); loadCategory(tab.dataset.category); })); document.getElementById('horizonSelect').addEventListener('change', () => { loadCategory(state.category); loadUnifiedInventoryTable(); }); loadCategory(state.category);
loadUnifiedInventoryTable();
</script>
</body>
</html>
