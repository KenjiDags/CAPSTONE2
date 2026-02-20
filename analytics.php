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

<style>
  .tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 2px solid #ddd;
  }
  .tab-button {
    padding: 12px 24px;
    background: #f5f5f5;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #666;
    transition: all 0.3s;
  }
  .tab-button:hover {
    background: #e8e8e8;
  }
  .tab-button.active {
    background: white;
    color: #007cba;
    border-bottom-color: #007cba;
  }
  .tab-content {
    display: none;
  }
  .tab-content.active {
    display: block;
  }
</style>

<div class="container">
  <h2>Analytics Dashboard</h2>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-button active" data-tab="market-performance">MPS & MRP Market Performance</button>
  </div>
  <!-- Market Performance Tab (Merged MPS & MRP) -->
  <div class="tab-content" id="market-performance">
    <section>
      <h3>MPS & MRP Market Performance Dashboard</h3>
      <div style="margin-bottom:12px;">
        <span style="display:inline-block;width:12px;height:12px;background:#e53935;border-radius:50%;margin-right:4px;"></span> Restock Immediately
        <span style="display:inline-block;width:12px;height:12px;background:#ffc107;border-radius:50%;margin-left:16px;margin-right:4px;"></span> Low Stock
      </div>
      <div style="margin-bottom:12px;">
        <label for="marketItemSelect"><b>Select Item:</b></label>
        <select id="marketItemSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd; min-width:200px;"></select>
      </div>
      <div class="chart-wrapper"><canvas id="marketChart"></canvas></div>
      <div style="margin-top:32px;margin-bottom:8px;"><b>Material Requirements Planning (MRP) Graph</b></div>
      <div class="chart-wrapper"><canvas id="mrpChart"></canvas></div>
      <div id="marketTable"></div>
      <p style="color:#888;">Select an item to view its Market Performance (MPS) and Material Requirements Planning (MRP) graphs.</p>
    </section>
  </div>


  <!-- Market Performance Tab (Only Section Left) -->
  <div class="tab-content active" id="market-performance">
    <section>
      <h3>MPS & MRP Market Performance Dashboard</h3>
      <div style="margin-bottom:12px;">
        <span style="display:inline-block;width:12px;height:12px;background:#e53935;border-radius:50%;margin-right:4px;"></span> Restock Immediately
        <span style="display:inline-block;width:12px;height:12px;background:#ffc107;border-radius:50%;margin-left:16px;margin-right:4px;"></span> Low Stock
      </div>
      <div style="margin-bottom:12px;">
        <label for="marketItemSelect"><b>Select Item:</b></label>
        <select id="marketItemSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd; min-width:200px;"></select>
      </div>
      <div class="chart-wrapper"><canvas id="marketChart"></canvas></div>
      <div style="margin-top:32px;margin-bottom:8px;"><b>Material Requirements Planning (MRP) Graph</b></div>
      <div class="chart-wrapper"><canvas id="mrpChart"></canvas></div>
      <div id="marketTable"></div>
      <p style="color:#888;">Select an item to view its Market Performance (MPS) and Material Requirements Planning (MRP) graphs.</p>
    </section>
  </div>

</div>

<script>
const CRITICAL_THRESHOLD = 0.25;
const WARNING_THRESHOLD = 0.5;

// Tab switching logic (only one tab now)
const tabButtons = document.querySelectorAll('.tab-button');
const tabContents = document.querySelectorAll('.tab-content');

// Load Market Performance data (merged MPS/MRP)
function loadMarketPerformanceData() {
  fetch('analytics_data.php?category=office-supplies')
    .then(res => res.json())
    .then(json => {
      const supply = json.supply_list || [];
      const marketItemSelect = document.getElementById('marketItemSelect');
      const marketCtx = document.getElementById('marketChart').getContext('2d');
      const mrpCtx = document.getElementById('mrpChart').getContext('2d');
      let marketChart = null;
      let mrpChart = null;

      // Populate dropdown
      marketItemSelect.innerHTML = '<option value="">-- Select item --</option>';
      supply.forEach(it => {
        const opt = document.createElement('option');
        opt.value = it.item_id;
        opt.textContent = `${it.stock_number} — ${it.item_name}`;
        marketItemSelect.appendChild(opt);
      });

      function renderMarketChart(item) {
        if (!item) {
          if (marketChart) { marketChart.destroy(); marketChart = null; }
          if (mrpChart) { mrpChart.destroy(); mrpChart = null; }
          return;
        }
        // MPS Chart
        const labels = ['Quantity', 'Reorder Point'];
        const data = [item.quantity, item.reorder_point || 0];
        const barColors = [
          (item.reorder_point === 0) ? '#3a7bc8' : (item.quantity === 0 || (item.quantity / item.reorder_point) <= CRITICAL_THRESHOLD) ? '#e53935' : ((item.quantity / item.reorder_point) <= WARNING_THRESHOLD) ? '#ffc107' : '#3a7bc8',
          '#1976d2'
        ];
        if (marketChart) marketChart.destroy();
        marketChart = new Chart(marketCtx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: item.stock_number + ' — ' + item.item_name,
              data: data,
              backgroundColor: barColors,
              borderColor: barColors,
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: {
              x: { ticks: { maxRotation: 0, minRotation: 0 } },
              y: { beginAtZero: true, suggestedMax: Math.max(5, Math.ceil(Math.max(...data) * 1.15)) }
            },
            animation: false
          }
        });

        // MRP Chart (Placeholder: Simulate monthly usage and forecast)
        // In real use, fetch historical usage for the item
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        const usage = [20, 18, 22, 17, 19, 21].map(v => Math.max(0, v - Math.floor(Math.random() * 5))); // Simulated
        const forecast = usage.map(u => u + Math.floor(Math.random() * 3));
        if (mrpChart) mrpChart.destroy();
        mrpChart = new Chart(mrpCtx, {
          type: 'line',
          data: {
            labels: months,
            datasets: [
              { label: 'Historical Usage', data: usage, borderColor: '#1976d2', backgroundColor: 'rgba(25,118,210,0.1)', fill: true, tension: 0.2 },
              { label: 'Forecast', data: forecast, borderColor: '#43a047', borderDash: [5,5], fill: false, tension: 0.2 }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: {
              y: { beginAtZero: true }
            },
            animation: false
          }
        });
      }

      marketItemSelect.addEventListener('change', () => {
        const id = marketItemSelect.value;
        const selected = supply.find(s => s.item_id == id);
        renderMarketChart(selected);
      });

      // Optionally auto-select first item
      if (supply.length > 0) {
        marketItemSelect.value = supply[0].item_id;
        marketItemSelect.dispatchEvent(new Event('change'));
      }
    });
}

function switchToTab(targetTab) {
  // Remove active class from all buttons and contents
  tabButtons.forEach(btn => btn.classList.remove('active'));
  tabContents.forEach(content => content.classList.remove('active'));
  
  // Add active class to clicked button and corresponding content
  const targetButton = document.querySelector(`[data-tab="${targetTab}"]`);
  if (targetButton) {
    targetButton.classList.add('active');
    document.getElementById(targetTab).classList.add('active');
  }
  
  // Save current tab to localStorage
  localStorage.setItem('analyticsActiveTab', targetTab);
  
  // Load data for the selected tab if not already loaded
  if (targetTab === 'semi-expendables' && !window.semiDataLoaded) {
    loadSemiExpendableData();
    window.semiDataLoaded = true;
  } else if (targetTab === 'ppe' && !window.ppeDataLoaded) {
    loadPPEData();
    window.ppeDataLoaded = true;
  } else if (targetTab === 'market-performance' && !window.marketPerformanceLoaded) {
    loadMarketPerformanceData();
    window.marketPerformanceLoaded = true;
  }
}

tabButtons.forEach(button => {
  button.addEventListener('click', () => {
    const targetTab = button.getAttribute('data-tab');
    switchToTab(targetTab);
  });
});

// Only one tab, always active
switchToTab('market-performance');

// Load Office Supplies data (default)
fetch('analytics_data.php?category=office-supplies')
  .then(res => res.json())
  .then(json => {
    const supply = json.supply_list || [];
    const low = json.low_stock || [];

    // --- MPS Market Performance Chart ---
    // --- MPS Market Performance Chart with Dropdown ---
    const mpsItemSelect = document.getElementById('mpsItemSelect');
    const supplyCtx = document.getElementById('supplyChart').getContext('2d');
    let mpsChart = null;

    // Populate dropdown
    mpsItemSelect.innerHTML = '<option value="">-- Select item --</option>';
    supply.forEach(it => {
      const opt = document.createElement('option');
      opt.value = it.item_id;
      opt.textContent = `${it.stock_number} — ${it.item_name}`;
      mpsItemSelect.appendChild(opt);
    });

    function renderMPSChart(item) {
      if (!item) {
        if (mpsChart) { mpsChart.destroy(); mpsChart = null; }
        return;
      }
      const labels = ['Quantity', 'Reorder Point'];
      const data = [item.quantity, item.reorder_point || 0];
      const barColors = [
        (item.reorder_point === 0) ? '#3a7bc8' : (item.quantity === 0 || (item.quantity / item.reorder_point) <= CRITICAL_THRESHOLD) ? '#e53935' : ((item.quantity / item.reorder_point) <= WARNING_THRESHOLD) ? '#ffc107' : '#3a7bc8',
        '#888'
      ];
      if (mpsChart) mpsChart.destroy();
      mpsChart = new Chart(supplyCtx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: item.stock_number + ' — ' + item.item_name,
            data: data,
            backgroundColor: barColors,
            borderColor: barColors,
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: {
            x: { ticks: { maxRotation: 0, minRotation: 0 } },
            y: { beginAtZero: true, suggestedMax: Math.max(5, Math.ceil(Math.max(...data) * 1.15)) }
          },
          animation: false
        }
      });
    }

    mpsItemSelect.addEventListener('change', () => {
      const id = mpsItemSelect.value;
      const selected = supply.find(s => s.item_id == id);
      renderMPSChart(selected);
    });

    // Optionally auto-select first item
    if (supply.length > 0) {
      mpsItemSelect.value = supply[0].item_id;
      mpsItemSelect.dispatchEvent(new Event('change'));
    }

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
renderLowStockTable(low, 'lowStock');


  })
  .catch(err => {
    console.error(err);
    alert('Error loading analytics data — check console for details.');
  });

// Load Semi Expendable Data
function loadSemiExpendableData() {
  fetch('analytics_data.php?category=semi-expendables')
    .then(res => res.json())
    .then(json => {
      const items = json.items || [];
      const semiTableDiv = document.getElementById('semiTable');
      
      if (items.length === 0) {
        semiTableDiv.innerHTML = '<p>No semi-expendable items found.</p>';
        return;
      }

      const table = document.createElement('table');
      table.className = 'analytics-table';

      const thead = document.createElement('thead');
      thead.innerHTML = `
        <tr>
          <th>Property No.</th>
          <th>Item Description</th>
          <th>Status</th>
          <th>Current Officer</th>
          <th>Quantity Balance</th>
        </tr>
      `;
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      items.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${item.property_no || 'N/A'}</td>
          <td>${item.item_name || 'N/A'}</td>
          <td>${item.status || 'Active'}</td>
          <td>${item.officer || 'N/A'}</td>
          <td>${item.quantity || 0}</td>
        `;
        tbody.appendChild(tr);
      });

      table.appendChild(tbody);
      semiTableDiv.appendChild(table);
    })
    .catch(err => {
      console.error(err);
      document.getElementById('semiTable').innerHTML = '<p>Error loading semi-expendable data.</p>';
    });
}

// Load PPE Data
function loadPPEData() {
  fetch('analytics_data.php?category=ppe')
    .then(res => res.json())
    .then(json => {
      const items = json.items || [];
      const ppeTableDiv = document.getElementById('ppeTable');
      
      if (items.length === 0) {
        ppeTableDiv.innerHTML = '<p>No PPE items found.</p>';
        return;
      }

      const table = document.createElement('table');
      table.className = 'analytics-table';

      const thead = document.createElement('thead');
      thead.innerHTML = `
        <tr>
          <th>PAR No.</th>
          <th>Item Name</th>
          <th>Condition</th>
          <th>Status</th>
          <th>Current Officer/Custodian</th>
          <th>Quantity</th>
        </tr>
      `;
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      items.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${item.property_no || 'N/A'}</td>
          <td>${item.item_name || 'N/A'}</td>
          <td>${item.condition || 'N/A'}</td>
          <td>${item.status || 'N/A'}</td>
          <td>${item.officer || 'N/A'}</td>
          <td>${item.quantity || 0}</td>
        `;
        tbody.appendChild(tr);
      });

      table.appendChild(tbody);
      ppeTableDiv.appendChild(table);
    })
    .catch(err => {
      console.error(err);
      document.getElementById('ppeTable').innerHTML = '<p>Error loading PPE data.</p>';
    });
}


// Shared function to render low stock table
function renderLowStockTable(low, containerId) {
  const lowDiv = document.getElementById(containerId);
  if (low.length === 0) {
    lowDiv.innerHTML = '<p>No low-stock items.</p>';
  } else {
    low.sort((a, b) => a.item_name.localeCompare(b.item_name));

    const table = document.createElement('table');
    table.className = 'analytics-table';

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

    const tbody = document.createElement('tbody');

    low.forEach(item => {
      const tr = document.createElement('tr');

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
}
</script>

</body>
</html>
