# MRP stock chart

The PHP dashboard uses the existing Chart.js distribution and `js/mrp-stock.js`.
`js/MRPStockChart.jsx` provides the requested React version with `useMemo` for
filtered rows and chart configuration. It is a reusable component, not mounted
in the PHP app, which does not have a React build pipeline.

## React integration

Use a React project with `react` and `chart.js` and CommonJS import support:

```jsx
import MRPStockChart from './js/MRPStockChart.jsx';

<MRPStockChart
  items={data.supply_list}
  onRestock={item => {
    window.location.href = `add_multiple_items.php?item_id=${encodeURIComponent(item.id)}`;
  }}
/>
```

Replace `onRestock` with your PO creation handler when that workflow exists.
The parent owns API fetching: pass a new `items` array after stock changes.
The component updates the business date while open and destroys the previous
chart when its inputs change. The PHP dashboard polls stock every minute,
refreshes on focus/visibility return and `inventory:updated`, and checks date
rollover every second. Failed polling leaves the last snapshot with a warning.

## Data contract

```js
{
  id: 42,
  sku: 'SUP-042',
  itemName: 'Printer paper',
  status: 'critical', // always recalculated from onHandQty and safetyStock
  onHandQty: 0,
  safetyStock: 20,
  stockoutDate: '2026-09-10',
  leadTimeDays: 14,
  netRequirement: 20,
  poExpectedDeliveryDate: '2026-09-26' // optional committed PO date
}
```

Dates are ISO calendar dates in Asia/Manila. Days out of stock is
`max(0, today - stockoutDate)` in calendar days. A stockout today is zero;
unknown or invalid dates remain `null`, not zero or one. Expected resolution
is stockout date plus supplier lead time. The committed PO delivery date is
displayed separately because it may differ from that projection.

The SQL selects the earliest zero-balance history record after the most recent
positive balance, including history-ID ordering for equal timestamps. It does
not substitute unrelated receipt/issue dates. Accurate duration depends on
complete balance history; a missing episode start is not reconstructed.

The current schema has no supplier lead times, PO delivery dates, demand plan,
or open receipt quantities. The API returns null planning dates and uses the
reorder point as safety stock. Its net requirement is explicitly labeled as the
safety-stock deficit. Supply real `leadTimeDays`, `poExpectedDeliveryDate`, and
MRP-calculated `netRequirement` from a future procurement integration; the
shared chart and React component already accept them. No PO records are created
by a chart click. The current action focuses the item in Restock Inventory.

## Checks

```text
node tests/mrp-stock.test.cjs
C:\xampp\php\php.exe tests/mrp_dates_test.php
```

The Node tests require Node 22.13+ with `node:sqlite`. They test calendar dates,
strict status boundaries, date projections, SQL stockout episodes using an
in-memory fixture database, and actual chart-renderer transitions. They do not
modify the live database. PHP date tests use explicit dates for repeatability.
