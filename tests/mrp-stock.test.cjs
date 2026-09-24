const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const mrp = require('../js/mrp-stock.js');

assert.equal(mrp.daysOutOfStock('2026-09-19', '2026-09-19'), 0);
assert.equal(mrp.daysOutOfStock('2026-09-10', '2026-09-19'), 9);
assert.equal(mrp.daysOutOfStock('2026-09-20', '2026-09-19'), 0);
assert.equal(mrp.daysOutOfStock(null, '2026-09-19'), null);
assert.equal(mrp.daysOutOfStock('2026-02-30', '2026-09-19'), null);
assert.equal(mrp.daysOutOfStock('2024-02-28', '2024-03-01'), 2);
assert.equal(mrp.businessDate(new Date('2026-09-18T16:00:00Z')), '2026-09-19');
assert.equal(mrp.expectedResolution('2026-12-30', 4), '2027-01-03');
assert.equal(mrp.expectedResolution('2026-09-19', 0), '2026-09-19');
assert.equal(mrp.expectedResolution('2026-09-19', null), null);
const items = [
  { id: 1, sku: 'SAFE', itemName: 'Safe', onHandQty: 11, safetyStock: 10 },
  { id: 2, sku: 'LOW', itemName: 'Low', onHandQty: 10, safetyStock: 10 },
  { id: 3, sku: 'ZERO', itemName: 'Zero', onHandQty: 0, safetyStock: 10, stockoutDate: '2026-09-10', leadTimeDays: 14, poExpectedDeliveryDate: '2026-09-26' },
  { id: 4, itemName: 'Unknown date', onHandQty: 0, safetyStock: 10 },
  { id: 5, itemName: 'Invalid balance', onHandQty: -1, safetyStock: 10 },
];
assert.deepEqual(mrp.selectItems(items, 'safe').map(item => item.id), [1]);
assert.deepEqual(mrp.selectItems(items, 'low').map(item => item.id), [2]);
const critical = mrp.selectItems(items, 'critical', '', '2026-09-19');
assert.deepEqual(critical.map(item => item.id), [3, 4]);
assert.deepEqual(mrp.selectItems(items, 'critical', '', '2026-09-19', 'lowest').map(item => item.id), [4, 3]);
assert.equal(critical[0].daysOutOfStock, 9);
assert.equal(critical[0].expectedResolutionDate, '2026-09-24');
assert.equal(critical[0].poExpectedDeliveryDate, '2026-09-26');
assert.equal(critical[0].netRequirement, 10);
assert.equal(mrp.normalize({ ...items[2], onHandQty: 5 }).stockoutDate, null);
assert.equal(mrp.selectItems(items, 'critical', 'ZERO').length, 1);
const config = mrp.chartConfig(critical, 'critical', () => {});
assert.deepEqual(config.data.datasets[0].data, [9, null]);
assert.equal(config.options.scales.y.title.text, 'Days Out of Stock');
assert.equal(mrp.chartConfig([], 'safe').options.scales.y.title.text, 'Quantity (units)');

// Verify the actual SQL against isolated transaction fixtures, never production data.
const { DatabaseSync } = require('node:sqlite');
const db = new DatabaseSync(':memory:');
db.exec(`CREATE TABLE items (item_id INTEGER, quantity_on_hand INTEGER);
CREATE TABLE item_history (history_id INTEGER, item_id INTEGER, changed_at TEXT, quantity_on_hand INTEGER);
CREATE TABLE item_stockouts (item_id INTEGER, started_at TEXT, date_source TEXT);
INSERT INTO item_stockouts VALUES (1,'2026-09-03 08:00:00','history'),
 (2,'2026-09-08 08:00:00','history'), (5,'2026-09-04 08:00:00','history');
INSERT INTO items VALUES (1,0),(2,0),(3,0),(4,5),(5,0);
INSERT INTO item_history VALUES
 (1,1,'2026-09-01 08:00:00',5), (2,1,'2026-09-03 08:00:00',0), (3,1,'2026-09-09 08:00:00',0),
 (4,2,'2026-09-01 08:00:00',0), (5,2,'2026-09-05 08:00:00',10), (6,2,'2026-09-08 08:00:00',0),
 (7,5,'2026-09-01 08:00:00',0), (8,5,'2026-09-01 08:00:00',10), (9,5,'2026-09-04 08:00:00',0);`);
const backend = fs.readFileSync('analytics_data.php', 'utf8');
const query = backend.match(/\$criticalSql = "([\s\S]*?)";/)[1];
const episodes = db.prepare(query).all();
assert.deepEqual(episodes.map(row => [row.item_id, row.depleted_at]), [
  [1, '2026-09-03 08:00:00'], [2, '2026-09-08 08:00:00'], [3, null], [5, '2026-09-04 08:00:00'],
]);
db.close();
const observed = mrp.normalize({ ...items[2], stockoutDateSource: 'observed' }, '2026-09-13');
assert.equal(observed.daysOutOfStock, 3);
assert.equal(mrp.tooltip(observed)[0], 'Days Out of Stock: 3');
for (const missing of [null, undefined, 0, '0', 'Not recorded', '']) {
  assert.deepEqual(mrp.tooltip({ daysOutOfStock: 0, netRequirement: missing,
    leadTimeDays: missing, expectedResolutionDate: missing, poExpectedDeliveryDate: missing }),
    ['Days Out of Stock: 0']);
}
assert.deepEqual(mrp.tooltip(critical[0]), [
  'Days Out of Stock: 9', 'Net Requirement Qty: 10 units', 'Projected Lead Time: 14 days',
  'Expected Resolution Date: 2026-09-24', 'PO Expected Replenishment: 2026-09-26'
]);
assert.equal(mrp.normalize(observed, '2026-09-14').daysOutOfStock, 4);

// Syntax-check inline dashboard JS after replacing PHP expressions with literals.
const page = fs.readFileSync('analytics.php', 'utf8');
for (const match of page.matchAll(/<script>([\s\S]*?)<\/script>/g)) {
  new vm.Script(match[1].replace(/<\?=[\s\S]*?\?>/g, '0'));
}
// Run the actual chart renderer through critical -> safe -> empty transitions.
const elements = new Map();
function element() { return { hidden: false, textContent: '', innerHTML: '', classList: { toggle() {} }, setAttribute() {} }; }
const buttons = ['safe', 'low', 'critical'].map(status => ({ ...element(), dataset: { mrpStatus: status } }));
let creations = 0, destructions = 0;
const state = { items, mrpStatusFilter: 'critical', mrpSearch: '', mrpViewMode: 'all', mrpStockChart: null };
const context = { state, MRPStock: mrp, escapeTableText: String,
  document: { getElementById(id) { if (!elements.has(id)) elements.set(id, { ...element(), options: [{}, {}, {}] }); return elements.get(id); }, querySelectorAll: () => buttons },
  Chart: function (_, chartConfig) { creations++; this.config = chartConfig; this.destroy = () => destructions++; },
};
vm.createContext(context);
vm.runInContext(page.slice(page.indexOf('function renderAllItemsChart()'), page.indexOf('function renderForecast(')), context);
context.renderAllItemsChart();
assert.equal(state.mrpStockChart.config.options.scales.y.title.text, 'Days Out of Stock');
state.mrpStatusFilter = 'safe'; context.renderAllItemsChart();
assert.equal(destructions, 1);
assert.deepEqual(state.mrpStockChart.config.data.datasets[0].data, [11]);
state.mrpSearch = 'NO MATCH'; context.renderAllItemsChart();
assert.equal(destructions, 2);
assert.equal(creations, 2);
assert.equal(state.mrpStockChart, null);
console.log('MRP date, filtering, stockout SQL, and chart transition checks passed.');
