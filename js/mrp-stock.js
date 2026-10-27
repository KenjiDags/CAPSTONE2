/* Shared utility for the PHP dashboard and React component. Dates are calendar days. */
(function (root, factory) {
  const api = factory(typeof module === 'object' && module.exports ? require('./stock-categories.js') : root.StockCategories);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.MRPStock = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (StockCategories) {
  const DAY = 86400000;
  function businessDate(now = new Date()) {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now);
    const part = type => parts.find(entry => entry.type === type).value;
    return `${part('year')}-${part('month')}-${part('day')}`;
  }
  function dateValue(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const time = Date.parse(`${value}T00:00:00Z`);
    return Number.isFinite(time) && new Date(time).toISOString().slice(0, 10) === value ? time : null;
  }
  function daysOutOfStock(stockoutDate, today = businessDate()) {
    const start = dateValue(stockoutDate), end = dateValue(today);
    return start === null || end === null ? null : Math.max(0, Math.floor((end - start) / DAY));
  }
  function expectedResolution(stockoutDate, leadTimeDays) {
    const start = dateValue(stockoutDate);
    return start === null || !Number.isInteger(leadTimeDays) || leadTimeDays < 0
      ? null : new Date(start + leadTimeDays * DAY).toISOString().slice(0, 10);
  }
  function normalize(item, today = businessDate()) {
    const onHandQty = Number(item.onHandQty ?? item.quantity);
    const safetyStock = Math.max(0, Number(item.safetyStock ?? item.reorder_point ?? 0));
    const status = onHandQty === 0 ? 'empty' : onHandQty > 1 && onHandQty < 5 ? 'critical' : onHandQty > safetyStock ? 'safe' : onHandQty > 0 ? 'low' : 'invalid';
    const stockoutDate = status === 'empty' ? (item.stockoutDate ?? null) : null;
    return { ...item, id: item.id ?? item.item_id, sku: item.sku ?? item.stock_number ?? '',
      itemName: item.itemName ?? item.item_name ?? 'Unnamed item', onHandQty, safetyStock, status, stockoutDate,
      leadTimeDays: item.leadTimeDays ?? null,
      netRequirement: item.netRequirement ?? Math.max(0, safetyStock - onHandQty),
      daysOutOfStock: status === 'empty' ? daysOutOfStock(stockoutDate, today) : 0,
      expectedResolutionDate: expectedResolution(stockoutDate, item.leadTimeDays),
      poExpectedDeliveryDate: dateValue(item.poExpectedDeliveryDate) === null ? null : item.poExpectedDeliveryDate };
  }
  function selectItems(items, status, search = '', today = businessDate(), sort = 'all', category = 'all') {
    const query = search.trim().toLowerCase();
    return items.map(item => normalize(item, today)).filter(item => (status === 'all' || item.status === status) && Number.isFinite(item.onHandQty) &&
      `${item.id} ${item.sku} ${item.itemName} ${item.description ?? ''}`.toLowerCase().includes(query) &&
      StockCategories.matches(`${item.itemName} ${item.description ?? ''}`, category))
      .sort((a, b) => (sort === 'lowest' ? a.onHandQty - b.onHandQty
        : sort === 'highest' ? b.onHandQty - a.onHandQty : 0) || a.itemName.localeCompare(b.itemName));
  }
  function itemWindow(items, start = 0, limit = 20) {
    const size = Math.max(1, Math.floor(Number(limit) || 20));
    const offset = Math.min(Math.max(0, Math.floor(Number(start) || 0)), Math.max(0, items.length - size));
    return { items: items.slice(offset, offset + size), start: offset, total: items.length };
  }
  function scrollOffset(scrollLeft, maxScroll, total, limit = 20) {
    if (maxScroll <= 0 || total <= limit) return 0;
    return Math.round(Math.max(0, Math.min(1, scrollLeft / maxScroll)) * (total - limit));
  }
  function tooltip(item) {
    const lines = [item.onHandQty > 0 ? `Quantity: ${item.onHandQty} units` : `Days Out of Stock: ${item.daysOutOfStock ?? 'Not recorded'}`];
    if (Number.isFinite(Number(item.netRequirement)) && Number(item.netRequirement) > 0)
      lines.push(`Net Requirement Qty: ${item.netRequirement} units`);
    if (Number.isFinite(Number(item.leadTimeDays)) && Number(item.leadTimeDays) > 0)
      lines.push(`Projected Lead Time: ${item.leadTimeDays} days`);
    if (dateValue(item.expectedResolutionDate) !== null)
      lines.push(`Expected Resolution Date: ${item.expectedResolutionDate}`);
    if (dateValue(item.poExpectedDeliveryDate) !== null)
      lines.push(`PO Expected Replenishment: ${item.poExpectedDeliveryDate}`);
    return lines;
  }
  function renderNameLabels(chart) {
    const frame = chart.canvas?.parentNode?.closest('.mrp-multi-chart-frame');
    const labels = frame?.querySelector('.mrp-name-labels');
    if (!labels) return;
    labels.replaceChildren();
    const enabled = chart.options.plugins.mrpNameLabels?.enabled;
    labels.hidden = !enabled;
    if (!enabled) { frame.style.setProperty('--mrp-chart-height', '360px'); return; }
    const bars = chart.getDatasetMeta(0).data;
    const slot = chart.chartArea.width / Math.max(1, bars.length);
    const top = Math.ceil(chart.scales.x.top + 12);
    labels.style.top = top + 'px';
    chart.data.labels.forEach((name, index) => {
      const label = document.createElement('span'); label.textContent = name;
      label.style.left = bars[index].x + 'px';
      label.style.width = Math.max(1, slot - 4) + 'px'; labels.append(label);
    });
    const height = Math.max(0, ...Array.from(labels.children, label => label.offsetHeight));
    frame.style.setProperty('--mrp-chart-height', Math.max(360, top + height + 12) + 'px');
  }
  function createItemPanel(canvas) {
    const panel = document.createElement('aside'), hoverCard = document.createElement('div');
    panel.className = 'mrp-item-panel'; panel.hidden = true; panel.tabIndex = -1;
    panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'Inventory item details');
    hoverCard.className = 'mrp-item-panel mrp-item-hover'; hoverCard.hidden = true;
    hoverCard.setAttribute('role', 'tooltip');
    canvas.parentNode.append(panel, hoverCard);
    let selectedChart = null;
    function position(card, anchor) {
      const width = canvas.parentNode.clientWidth || canvas.clientWidth;
      const height = canvas.parentNode.clientHeight || canvas.clientHeight;
      card.style.right = 'auto';
      card.style.left = Math.max(8, Math.min(anchor.x + 12, width - card.offsetWidth - 8)) + 'px';
      card.style.top = Math.max(8, Math.min(anchor.y + 12, height - card.offsetHeight - 8)) + 'px';
    }
    function close(restoreFocus = false) {
      panel.hidden = true;
      if (selectedChart) {
        selectedChart.$mrpSelectedIndex = null;
        if (selectedChart.canvas && !selectedChart.$mrpDestroying) selectedChart.update('none');
        selectedChart = null;
      }
      hoverCard.hidden = true;
      if (restoreFocus) canvas.focus({ preventScroll: true });
    }
    function populate(card, item, interactive) {
      card.replaceChildren();
      const header = document.createElement('div'); header.className = 'mrp-item-heading';
      const title = document.createElement('strong'); title.textContent = item.itemName; header.append(title);
      const badge = document.createElement('span'); badge.className = 'mrp-item-status ' + item.status;
      badge.textContent = { safe: 'Safe', low: 'At reorder point', critical: 'Critical', empty: 'Empty', invalid: 'Invalid balance' }[item.status];
      if (interactive) {
        const dismiss = document.createElement('button'); dismiss.type = 'button';
        dismiss.className = 'mrp-item-close'; dismiss.textContent = '×';
        dismiss.setAttribute('aria-label', 'Close item details');
        dismiss.addEventListener('click', () => close(true)); header.append(dismiss);
      } else header.append(badge);
      const reference = document.createElement('p'); reference.className = 'mrp-item-reference';
      reference.textContent = (interactive ? '' : 'Stock No. ') + (item.sku || String(item.id));
      card.append(header, reference);
      if (interactive) {
        const description = document.createElement('p'); description.className = 'mrp-item-description-text';
        description.textContent = item.description || 'No description available.';
        card.append(badge, description);
      }
      const category = StockCategories.categories.find(entry => StockCategories.matches(item.itemName + ' ' + (item.description || ''), entry.id));
      const metrics = document.createElement('dl');
      [['On hand', item.onHandQty.toLocaleString() + ' units'], ['Reorder point', item.safetyStock.toLocaleString() + ' units'], ['Category', category ? category.label : 'Miscellaneous']].forEach(([label, value]) => {
        const row = document.createElement('div'), name = document.createElement('dt'), result = document.createElement('dd');
        name.textContent = label; result.textContent = value; row.append(name, result); metrics.append(row);
      });
      card.append(metrics);
      if (interactive) {
        const view = document.createElement('a'); view.className = 'mrp-item-inventory-link';
        view.href = 'office_supplies/view_item.php?item_id=' + encodeURIComponent(item.id);
        view.textContent = 'View in Inventory ↗'; card.append(view);
      }
    }
    function open(item, anchor = { x: 8, y: 8 }, chart = null) {
      selectedChart = chart;
      hoverCard.hidden = true;
      populate(panel, item, true); panel.hidden = false; position(panel, anchor);
      panel.focus({ preventScroll: true });
    }
    function hover(item, anchor) {
      if (!item || !panel.hidden) { hoverCard.hidden = true; return; }
      populate(hoverCard, item, false); hoverCard.hidden = false; position(hoverCard, anchor);
    }
    const outside = event => { if (!panel.hidden && !panel.contains(event.target) && event.target !== canvas) close(); };
    const escape = event => { if (!panel.hidden && event.key === 'Escape') { event.preventDefault(); close(true); } };
    document.addEventListener('pointerdown', outside); document.addEventListener('keydown', escape);
    return { open, hover, close, destroy() {
      close();
      document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', escape);
      panel.remove(); hoverCard.remove();
    } };
  }
  function chartConfig(items, status, onSelect, { dark = false, labelMode = 'id', onDismiss, onHoverItem } = {}) {
    const metric = 'Quantity (units)';
    const axisColor = dark ? '#ffffff' : '#526168';
    const colors = { safe: '#15803d', low: '#d97706', critical: '#dc2626', empty: '#64748b', invalid: '#7c3aed' };
    return { type: 'bar', data: { labels: items.map(item => String(labelMode === 'name' ? item.itemName : (item.sku || item.id))), datasets: [{
      label: metric, data: items.map(item => item.onHandQty),
      backgroundColor: items.map(item => colors[item.status]), borderRadius: 4,
      borderColor: context => context.chart.$mrpSelectedIndex === context.dataIndex ? (dark ? '#ffffff' : '#1d4ed8') : 'transparent',
      borderWidth: context => context.chart.$mrpSelectedIndex === context.dataIndex ? 3 : 0
    }] }, plugins: [{ id: 'mrpNameLabels', afterRender: renderNameLabels, beforeDestroy(chart) {
      const frame = chart.canvas?.parentNode?.closest('.mrp-multi-chart-frame');
      const labels = frame?.querySelector('.mrp-name-labels');
      if (labels) { labels.replaceChildren(); labels.hidden = true; }
      if (frame) frame.style.setProperty('--mrp-chart-height', '360px');
    } }, { id: 'mrpItemPanelCleanup', beforeDestroy(chart) { chart.$mrpDestroying = true; if (onDismiss) onDismiss(); if (onHoverItem) onHoverItem(null); } }, { id: 'mrpZeroQuantities', afterDatasetsDraw(chart) {
      const context = chart.ctx;
      const bars = chart.getDatasetMeta(0).data;
      const baseline = chart.scales.y.getPixelForValue(0);
      context.save();
      context.font = '11px Century Gothic, sans-serif';
      context.fillStyle = chart.options.scales.y.ticks.color;
      context.textAlign = 'center';
      context.textBaseline = 'bottom';
      chart.data.datasets[0].data.forEach((quantity, index) => {
        if (quantity === 0 && bars[index] && Number.isFinite(bars[index].x)) {
          context.fillText('0', bars[index].x, baseline - 5);
        }
      });
      context.restore();
    } }], options: { responsive: true, maintainAspectRatio: false, animation: false,
      interaction: { mode: 'index', intersect: false },
      onClick: (event, _, chart) => {
        const hits = chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, false);
        let index = hits.length ? hits[0].index : -1;
        // Zero-height bars are selected by clicking their baseline marker.
        if (index < 0 && Math.abs(event.y - chart.scales.y.getPixelForValue(0)) <= 16) {
          index = chart.getDatasetMeta(0).data.findIndex((bar, i) => items[i].onHandQty === 0 && Math.abs(event.x - bar.x) <= Math.max(8, bar.width / 2));
        }
        if (index >= 0 && onSelect) {
          const tip = chart.tooltip;
          const bar = chart.getDatasetMeta(0).data[index];
          const anchor = tip && tip.opacity && tip.dataPoints?.[0]?.dataIndex === index
            ? { x: tip.caretX, y: tip.caretY }
            : (bar.tooltipPosition ? bar.tooltipPosition() : { x: event.x, y: event.y });
          chart.$mrpSelectedIndex = index;
          chart.update('none');
          onSelect(items[index], anchor, chart);
        }
        else if (onDismiss) onDismiss();
      },
      plugins: { mrpNameLabels: { enabled: labelMode === 'name' }, legend: { display: false }, tooltip: { enabled: false, external: ({ tooltip: model }) => {
        if (!onHoverItem) return;
        if (!model.opacity || !model.dataPoints?.length) { onHoverItem(null); return; }
        onHoverItem(items[model.dataPoints[0].dataIndex], { x: model.caretX, y: model.caretY });
      } } },
      scales: {
        x: { position: 'bottom', afterFit: scale => { scale.height = 44; }, grid: { display: false }, ticks: { color: axisColor, autoSkip: false, maxRotation: 0, minRotation: 0, callback: function (value) { return labelMode === 'name' ? '' : this.getLabelForValue(value); }, font: { size: 12 }, padding: 10 }, title: { display: false, text: 'Stock No.', color: axisColor } },
        y: { beginAtZero: true, grid: { color: dark ? '#334155' : '#e7edef' }, ticks: { color: axisColor, precision: 0 }, title: { display: true, text: metric, color: axisColor } }
      }
    } };
  }
  return { businessDate, daysOutOfStock, expectedResolution, normalize, selectItems, itemWindow, scrollOffset, tooltip, createItemPanel, chartConfig };
});
