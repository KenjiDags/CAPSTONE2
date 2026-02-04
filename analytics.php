<?php
require 'auth.php';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics Dashboard</title>
  <link rel="stylesheet" href="css/analytics.css?v=<?= time() ?>">
  <script src="js/chart.min.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
  <h2>Analytics Dashboard</h2>

  <!-- Supply Chart -->
  <section style="margin-bottom:28px;">
    <h3>Supply List (Top items by quantity)</h3>
    <div class="chart-wrapper"><canvas id="supplyChart"></canvas></div>
  </section>

  <!-- Stock Card -->
  <section style="margin-bottom:28px;">
    <h3>Stock Card (select item)</h3>
    <div style="display:flex;gap:12px;align-items:center;">
      <select id="itemSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd;">
        <option value="">-- Select item --</option>
      </select>
      <div id="selectedInfo" style="color:#666;font-weight:600"></div>
    </div>
    <div class="chart-wrapper" style="margin-top:12px;"><canvas id="stockCardChart"></canvas></div>
  </section>

  <!-- Low Stock Items -->
  <section>
    <h3>Low Stock Items</h3>
    <!-- Severity legend -->
    <div class="stock-legend">
      <span class="legend-critical"></span><span class="legend-label">Critical</span>
      <span class="legend-warning"></span><span class="legend-label">Warning</span>
      <span class="legend-ok"></span><span class="legend-label">OK</span>
    </div>
    <div style="margin-bottom:8px;color:#666;font-weight:600;">Items at or below reorder point</div>
    <div id="lowStock"></div>
  </section>

</div>

<script>
const CRITICAL_THRESHOLD = 0.25;
const WARNING_THRESHOLD = 0.5;

fetch('analytics_data.php')
  .then(res => res.json())
  .then(json => {
    const supply = json.supply_list || [];
    const low = json.low_stock || [];

    // --- Supply Chart ---
    const supplyLabels = supply.map(i => i.stock_number + ' - ' + i.item_name);
    const supplyData = supply.map(i => i.quantity);
    const supplyCtx = document.getElementById('supplyChart').getContext('2d');
    const maxSupply = supplyData.length ? Math.max(...supplyData) : 0;
    const supplyGradient = supplyCtx.createLinearGradient(0, 0, 0, 300);
    supplyGradient.addColorStop(0, 'rgba(58,123,200,0.85)');
    supplyGradient.addColorStop(1, 'rgba(58,123,200,0.25)');

    new Chart(supplyCtx, {
      type: 'bar',
      data: {
        labels: supplyLabels,
        datasets: [{
          label: 'Quantity',
          data: supplyData,
          backgroundColor: supplyGradient,
          borderColor: 'rgba(58,123,200,0.9)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { maxRotation: 45, minRotation: 0 } },
          y: { beginAtZero: true, suggestedMax: Math.max(5, Math.ceil(maxSupply * 1.15)) }
        }
      }
    });

    // --- Stock Card Chart ---
    const itemSelect = document.getElementById('itemSelect');
    const selectedInfo = document.getElementById('selectedInfo');
    const stockCtx = document.getElementById('stockCardChart').getContext('2d');
    let stockChart = null;

    function renderStockChart(labels, data) {
      const maxVal = data.length ? Math.max(...data) : 0;
      const blueGrad = stockCtx.createLinearGradient(0, 0, 0, 300);
      blueGrad.addColorStop(0, 'rgba(58,123,235,0.35)');
      blueGrad.addColorStop(1, 'rgba(58,123,235,0.02)');

      if (stockChart) stockChart.destroy();
      stockChart = new Chart(stockCtx, {
        type: 'line',
        data: { labels, datasets: [{ label: 'Stock Level', data, borderColor: 'rgb(58,123,235)', backgroundColor: blueGrad, fill: true, tension: 0.1, pointRadius: 4, pointHoverRadius: 7, borderWidth: 3, pointBackgroundColor: 'rgb(58,123,235)', pointBorderColor: '#fff', pointBorderWidth: 2 }] },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 15 } } },
          scales: {
            y: { beginAtZero: true, suggestedMax: Math.max(5, Math.ceil(maxVal * 1.15)), title: { display: true, text: 'Quantity' }, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false }, title: { display: true, text: 'Date' }, ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 15 } }
          },
          interaction: { mode: 'nearest', axis: 'x', intersect: false }
        }
      });
    }

    // Populate item select
    supply.forEach(it => {
      const opt = document.createElement('option');
      opt.value = it.item_id;
      opt.textContent = `${it.stock_number} — ${it.item_name}`;
      itemSelect.appendChild(opt);
    });

    // Item selection event
    itemSelect.addEventListener('change', () => {
      const id = itemSelect.value;
      if (!id) {
        selectedInfo.textContent = '';
        if (stockChart) { stockChart.destroy(); stockChart = null; }
        return;
      }
      const selected = supply.find(s => s.item_id == id);
      selectedInfo.textContent = `${selected.stock_number} — ${selected.item_name}`;
      fetch(`analytics_data.php?item_id=${encodeURIComponent(id)}`)
        .then(r => r.json())
        .then(d => renderStockChart(d.labels || [], d.data || []))
        .catch(err => console.error(err));
    });

    // Auto-select first item
    if (supply.length > 0) {
      itemSelect.value = supply[0].item_id;
      itemSelect.dispatchEvent(new Event('change'));
    }

// --- Low Stock Table with Color Coding ---
const lowDiv = document.getElementById('lowStock');
if (low.length === 0) {
    lowDiv.innerHTML = '<p>No low-stock items.</p>';
} else {
    // Sort alphabetically by item_name
    low.sort((a, b) => a.item_name.localeCompare(b.item_name));

    const table = document.createElement('table');
    table.className = 'analytics-table';

    // Table header
    const thead = document.createElement('thead');
    thead.innerHTML = `
      <tr>
        <th>Stock #</th>
        <th>Item Name</th>
        <th>Quantity</th>
        <th>Reorder Point</th>
      </tr>
    `;
    table.appendChild(thead);

    // Table body
    const tbody = document.createElement('tbody');

    low.forEach(item => {
        const tr = document.createElement('tr');

        // Determine severity
        let severity;

        if (item.quantity === 0) {
            severity = 'critical';
        } else if (item.reorder_point === 0) {
            severity = 'ok';
        } else {
            const ratio = item.quantity / item.reorder_point;
            if (ratio <= CRITICAL_THRESHOLD) severity = 'critical';
            else if (ratio <= WARNING_THRESHOLD) severity = 'warning';
            else severity = 'ok';
        }

        tr.className = severity;

        // Row content
        tr.innerHTML = `
          <td>${item.stock_number}</td>
          <td>${item.item_name}</td>
          <td>${item.quantity}</td>
          <td>${item.reorder_point}</td>
        `;
        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    lowDiv.appendChild(table);
}


  })
  .catch(err => {
    console.error(err);
    alert('Error loading analytics data — check console for details.');
  });
</script>

</body>
</html>
