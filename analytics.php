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
</script>

</body>
</html>
