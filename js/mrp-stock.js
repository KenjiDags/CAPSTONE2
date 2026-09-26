/* Shared utility for the PHP dashboard and React component. Dates are calendar days. */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.MRPStock = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
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
  function selectItems(items, status, search = '', today = businessDate(), sort = 'all') {
    const query = search.trim().toLowerCase();
    return items.map(item => normalize(item, today)).filter(item => (status === 'all' || item.status === status) && Number.isFinite(item.onHandQty) &&
      `${item.sku} ${item.itemName} ${item.description ?? ''}`.toLowerCase().includes(query))
      .sort((a, b) => (sort === 'lowest' ? a.onHandQty - b.onHandQty
        : sort === 'highest' ? b.onHandQty - a.onHandQty : 0) || a.itemName.localeCompare(b.itemName));
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
  function chartConfig(items, status, onRestock) {
    const critical = status === 'critical';
    const metric = 'Quantity (units)';
    const colors = { safe: '#15803d', low: '#d97706', critical: '#dc2626', empty: '#64748b', invalid: '#7c3aed' };
    const names = { safe: 'Safe', low: 'Low Stock', critical: 'Critical', empty: 'Out of Stock', invalid: 'Invalid balance' };
    return { type: 'bar', data: { labels: items.map(item => `${item.sku} — ${item.itemName}`), datasets: [{
      label: metric, data: items.map(item => item.onHandQty),
      backgroundColor: items.map(item => colors[item.status]), borderRadius: 4
    }] }, options: { responsive: true, maintainAspectRatio: false, animation: false,
      interaction: { mode: 'index', intersect: false },
      onClick: (_, elements) => { if (critical && elements.length && onRestock) onRestock(items[elements[0].index]); },
      plugins: { legend: { display: false }, tooltip: { callbacks: {
        title: contexts => contexts.length ? `${items[contexts[0].dataIndex].sku} — ${items[contexts[0].dataIndex].itemName}` : '',
        label: context => { const item = items[context.dataIndex]; return [`Status: ${names[item.status]}`, ...(critical ? tooltip(item) : [`Quantity: ${item.onHandQty} units`])]; }
      } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: metric } } } } };
  }
  return { businessDate, daysOutOfStock, expectedResolution, normalize, selectItems, tooltip, chartConfig };
});
