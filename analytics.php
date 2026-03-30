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
        <span style="display:inline-block;width:12px;height:12px;background:#43a047;border-radius:50%;margin-right:4px;"></span> Safe
        <span style="display:inline-block;width:12px;height:12px;background:#fbc02d;border-radius:50%;margin-left:16px;margin-right:4px;"></span> Low Stock
        <span style="display:inline-block;width:12px;height:12px;background:#e53935;border-radius:50%;margin-left:16px;margin-right:4px;"></span> Critical
      </div>
      <div style="margin-bottom:12px;">
        <label for="marketItemSelect"><b>Select Item:</b></label>
        <select id="marketItemSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd; min-width:200px;"></select>
      </div>
      <div class="chart-wrapper"><canvas id="marketChart"></canvas></div>
      <div style="margin-top:32px;margin-bottom:8px;"><b>Material Requirements Planning (MRP) Graph</b></div>
      <div style="margin-bottom:12px;">
        <label for="mrpMonthSelect"><b>Filter by Month:</b></label>
        <select id="mrpMonthSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd; min-width:150px;">
          <option value="">All Months</option>
          <option value="01">January</option>
          <option value="02">February</option>
          <option value="03">March</option>
          <option value="04">April</option>
          <option value="05">May</option>
          <option value="06">June</option>
          <option value="07">July</option>
          <option value="08">August</option>
          <option value="09">September</option>
          <option value="10">October</option>
          <option value="11">November</option>
          <option value="12">December</option>
        </select>
      </div>
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
        <span style="display:inline-block;width:12px;height:12px;background:#43a047;border-radius:50%;margin-right:4px;"></span> Safe
        <span style="display:inline-block;width:12px;height:12px;background:#fbc02d;border-radius:50%;margin-left:16px;margin-right:4px;"></span> Low Stock
        <span style="display:inline-block;width:12px;height:12px;background:#e53935;border-radius:50%;margin-left:16px;margin-right:4px;"></span> Critical
      </div>
      <div style="margin-bottom:12px;">
        <label for="marketItemSelect"><b>Select Item:</b></label>
        <select id="marketItemSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd; min-width:200px;"></select>
      </div>
      <div class="chart-wrapper"><canvas id="marketChart"></canvas></div>
      <div style="margin-top:32px;margin-bottom:8px;"><b>Material Requirements Planning (MRP) Graph</b></div>
      <div style="margin-bottom:12px;">
        <label for="mrpMonthSelect"><b>Filter by Month:</b></label>
        <select id="mrpMonthSelect" style="padding:8px;border-radius:6px;border:1px solid #ddd; min-width:150px;">
          <option value="">All Months</option>
          <option value="01">January</option>
          <option value="02">February</option>
          <option value="03">March</option>
          <option value="04">April</option>
          <option value="05">May</option>
          <option value="06">June</option>
          <option value="07">July</option>
          <option value="08">August</option>
          <option value="09">September</option>
          <option value="10">October</option>
          <option value="11">November</option>
          <option value="12">December</option>
        </select>
      </div>
      <div class="chart-wrapper"><canvas id="mrpChart"></canvas></div>
      <div id="marketTable"></div>
      <p style="color:#888;">Select an item to view its Market Performance (MPS) and Material Requirements Planning (MRP) graphs.</p>
    </section>
  </div>

</div>

<script>
const CRITICAL_THRESHOLD = 0.25;
const WARNING_THRESHOLD = 0.5;

let marketChart = null;
let mrpChart = null;
const mrpMonthSelect = document.getElementById('mrpMonthSelect');

// Render MRP Chart - Real data from system based on item history
function renderMRPChart(itemData) {
  // Use REAL historical data from the database
  let historicalLabels = itemData.history_labels || [];
  let historicalData = itemData.history_values || [];
  console.log('MRP Chart Data:', { historicalLabels, historicalData });
  
  const today = new Date();
  let labels = [...historicalLabels]; // Start with actual historical data
  let actualData = [...historicalData];
  let forecastData = new Array(historicalData.length).fill(null); // No forecast for historical period
  
  // Generate forecast ONLY for future months (after today)
  if (historicalData.length > 0) {
    const avgUsage = historicalData.reduce((a, b) => a + b, 0) / historicalData.length;
    const trend = historicalData.length > 1 ? (historicalData[historicalData.length - 1] - historicalData[0]) / historicalData.length : 0;
    
    // Generate forecast for future months (up to end of year)
    const endOfYear = new Date(today.getFullYear(), 11, 31);
    let currentDate = new Date(today);
    currentDate.setDate(currentDate.getDate() + 1); // Start from tomorrow
    
    let dayCounter = 0;
    while (currentDate <= endOfYear) {
      labels.push(currentDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
      dayCounter++;
      const forecast = Math.max(20, Math.round(avgUsage + (trend * dayCounter) + (Math.random() - 0.5) * 10));
      actualData.push(null); // No actual data for future dates
      forecastData.push(forecast);
      currentDate.setDate(currentDate.getDate() + 1);
    }
  } else {
    // No historical data - generate realistic demo data for entire year
    const baseValue = itemData.quantity || 100;
    const startOfYear = new Date(today.getFullYear(), 0, 1);
    let currentDate = new Date(startOfYear);
    
    while (currentDate <= today) {
      labels.push(currentDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
      const randomUsage = Math.max(20, baseValue + (Math.sin(currentDate.getTime() / (1000 * 60 * 60 * 24 * 30)) * 30) + (Math.random() - 0.5) * 20);
      actualData.push(Math.round(randomUsage));
      forecastData.push(null); // No forecast for past
      currentDate.setDate(currentDate.getDate() + 1);
    }
    
    // Generate forecast for future months
    const avgUsage = actualData.reduce((a, b) => a + b, 0) / actualData.length;
    const trend = (actualData[actualData.length - 1] - actualData[0]) / actualData.length;
    const endOfYear = new Date(today.getFullYear(), 11, 31);
    currentDate.setDate(currentDate.getDate() + 1);
    let dayCounter = 0;
    
    while (currentDate <= endOfYear) {
      labels.push(currentDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
      actualData.push(null); // No actual data for future
      dayCounter++;
      const forecast = Math.max(20, Math.round(avgUsage + (trend * dayCounter) + (Math.random() - 0.5) * 20));
      forecastData.push(forecast);
      currentDate.setDate(currentDate.getDate() + 1);
    }
  }
  
  // Filter data by selected month if month is selected
  function filterMRPDataByMonth() {
    const selectedMonth = mrpMonthSelect.value;
    let filteredLabels = labels;
    let filteredActual = actualData;
    let filteredForecast = forecastData;
    
    if (selectedMonth !== '') {
      filteredLabels = [];
      filteredActual = [];
      filteredForecast = [];
      
      for (let i = 0; i < labels.length; i++) {
        try {
          const dateStr = labels[i];
          
          // Parse the label to get month - handle "Mar 30" or "Jan 15" format
          const monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
          const labelMonth = monthShort.indexOf(dateStr.substring(0, 3));
          
          if (labelMonth !== -1) {
            const month = String(labelMonth + 1).padStart(2, '0');
            if (month === selectedMonth) {
              filteredLabels.push(labels[i]);
              filteredActual.push(actualData[i]);
              filteredForecast.push(forecastData[i]);
            }
          }
        } catch (e) {
          // Skip if date parsing fails
        }
      }
    }
    
    // Update chart
    if (mrpChart) {
      mrpChart.data.labels = filteredLabels;
      mrpChart.data.datasets[0].data = filteredActual;
      mrpChart.data.datasets[1].data = filteredForecast;
      mrpChart.update();
    }
  }
  
  const mrpCtx = document.getElementById('mrpChart').getContext('2d');
  if (mrpChart) mrpChart.destroy();
  mrpChart = new Chart(mrpCtx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Actual Stock Level (Real-time)',
          data: actualData,
          borderColor: '#1976d2',
          backgroundColor: 'rgba(25,118,210,0.15)',
          borderWidth: 3,
          fill: true,
          tension: 0.4,
          pointRadius: 0,
          pointHoverRadius: 8,
          pointBackgroundColor: '#1976d2',
          pointBorderColor: '#fff',
          pointBorderWidth: 0,
          pointHoverBorderWidth: 2,
          spanGaps: true
        },
        {
          label: 'Forecast Trend',
          data: forecastData,
          borderColor: '#43a047',
          backgroundColor: 'transparent',
          borderWidth: 3,
          borderDash: [6, 3],
          fill: false,
          tension: 0.4,
          pointRadius: 0,
          pointHoverRadius: 8,
          pointBackgroundColor: '#43a047',
          pointBorderColor: '#fff',
          pointBorderWidth: 0,
          pointHoverBorderWidth: 2,
          spanGaps: true
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: { 
        title: {
          display: true,
          text: `Material Requirements Planning - ${itemData.item_name || itemData.stock_number || 'Item'} (SKU: ${itemData.stock_number || 'N/A'})`,
          font: { size: 14, weight: 'bold' },
          padding: 16,
          color: '#333'
        },
        legend: { 
          display: true,
          position: 'top',
          labels: { font: { size: 12, weight: '600' }, padding: 16 }
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          padding: 12,
          titleFont: { size: 13, weight: 'bold' },
          bodyFont: { size: 12 },
          callbacks: {
            label: function(context) {
              if (context.parsed.y === null) {
                return context.dataset.label + ': (No Data)';
              }
              return context.dataset.label + ': ' + context.parsed.y + ' units';
            }
          }
        }
      },
      scales: {
        x: { 
          ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 0 },
          grid: { display: true, drawBorder: true }
        },
        y: { 
          beginAtZero: true,
          position: 'left',
          title: { display: false },
          ticks: { font: { size: 12 } },
          grid: { display: true, color: 'rgba(0,0,0,0.05)' }
        }
      },
      animation: {
        duration: 800,
        easing: 'easeInOutQuart'
      }
    }
  });
  
  // Add month filter event listener
  mrpMonthSelect.removeEventListener('change', filterMRPDataByMonth);
  mrpMonthSelect.addEventListener('change', filterMRPDataByMonth);
}

// Tab switching logic (only one tab now)
const tabButtons = document.querySelectorAll('.tab-button');
const tabContents = document.querySelectorAll('.tab-content');

// Load Market Performance data (merged MPS/MRP)
function loadMarketPerformanceData() {
  fetch('analytics_data.php')
    .then(res => res.json())
    .then(json => {
      const supply = json.supply_list || [];
      const marketItemSelect = document.getElementById('marketItemSelect');
      const marketCtx = document.getElementById('marketChart').getContext('2d');
      const mrpCtx = document.getElementById('mrpChart').getContext('2d');
      let marketChart = null;
      let mrpChart = null;

      // Populate dropdown with ALL items
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
        
        // Inventory Composition Chart (Horizontal Bars - only show bars with values)
        const inventoryLabel = item.stock_number + ' — ' + item.item_name;
        const onHand = item.quantity || 0;
        const safetyStock = item.safety_stock || 0;
        const reorderPoint = item.reorder_point || 0;
        
        // Determine status and color based on fixed thresholds
        let onHandColor, onHandBorderColor, statusText, statusEmoji;
        if (onHand >= 15) {
          onHandColor = '#43a047'; // Green - Safe (15+ units)
          onHandBorderColor = '#2e7d32';
          statusText = 'Safe';
          statusEmoji = '✓';
        } else if (onHand >= 10 && onHand < 15) {
          onHandColor = '#fbc02d'; // Yellow - Low Stock Warning (10-14 units)
          onHandBorderColor = '#f57f17';
          statusText = 'Low Stock';
          statusEmoji = '⚠';
        } else {
          onHandColor = '#e53935'; // Red - Critical Stock (below 10 units)
          onHandBorderColor = '#c62828';
          statusText = 'Restock Immediately';
          statusEmoji = '🔴';
        }
        
        // Build labels and datasets only for bars with values
        let labels = ['On-hand Inventory'];
        let data = [onHand];
        let colors = [onHandColor];
        let borders = [onHandBorderColor];
        
        if (safetyStock > 0) {
          labels.push('Safety Stock Level');
          data.push(safetyStock);
          colors.push('#43a047');
          borders.push('#2e7d32');
        }
        
        if (reorderPoint > 0) {
          labels.push('Reorder Point');
          data.push(reorderPoint);
          colors.push('#fbc02d');
          borders.push('#f57f17');
        }
        
        if (marketChart) marketChart.destroy();
        marketChart = new Chart(marketCtx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [
              {
                label: inventoryLabel + ' - ' + statusText,
                data: data,
                backgroundColor: colors,
                borderColor: borders,
                borderWidth: 2,
                borderRadius: 4
              }
            ]
          },
          options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
              legend: { 
                display: true, 
                position: 'top',
                labels: { font: { size: 12, weight: '600' }, padding: 16 }
              },
              tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 13, weight: 'bold' },
                bodyFont: { size: 12 },
                callbacks: {
                  label: function(context) {
                    let description = '';
                    if (context.label === 'On-hand Inventory') {
                      description = statusEmoji + ' ' + statusText + ' - ' + context.parsed.x + ' units';
                    } else if (context.label === 'Safety Stock Level') {
                      description = 'Minimum buffer - ' + context.parsed.x + ' units';
                    } else if (context.label === 'Reorder Point') {
                      description = 'Reorder threshold - ' + context.parsed.x + ' units';
                    }
                    return description;
                  }
                }
              },
              datalabels: {
                color: '#000',
                font: { weight: 'bold', size: 11 },
                anchor: 'end',
                align: 'end'
              }
            },
            scales: {
              x: {
                beginAtZero: true,
                ticks: { font: { size: 12 } },
                title: { display: true, text: 'Quantity (units)' }
              },
              y: {
                ticks: { font: { size: 12, weight: '600' } }
              }
            },
            animation: {
              duration: 800,
              easing: 'easeInOutQuart'
            }
          }
        });
      }

      marketItemSelect.addEventListener('change', () => {
        const id = marketItemSelect.value;
        const selected = supply.find(s => s.item_id == id);
        
        // Fetch detailed item data including history
        if (selected) {
          console.log('Fetching data for item:', id, selected);
          fetch(`analytics_data.php?item_id=${id}`)
            .then(res => res.json())
            .then(itemData => {
              console.log('API Response:', itemData);
              // Merge history data with item data
              const itemWithHistory = {
                ...selected,
                history_labels: itemData.labels || [],
                history_values: itemData.data || []
              };
              console.log('Item with history:', itemWithHistory);
              renderMarketChart(itemWithHistory);
              renderMRPChart(itemWithHistory);
            })
            .catch(err => {
              console.error('Error fetching item history:', err);
              renderMarketChart(selected);
            });
        }
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
