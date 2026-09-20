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

Stockout episodes are stored in `item_stockouts`, independently of visible stock
cards. Database triggers record zero-balance inserts and stock transitions,
preserve the date during zero-balance edits, and clear it on restocking. Dates
use Manila time regardless of the database connection timezone. RIS edits that
temporarily restore and reissue the same stock preserve an ongoing episode.

After importing the database, run once before serving the updated application
(pause inventory writes during installation):

```text
C:\xampp\php\php.exe db/migrate_stockout_tracking.php
```

The migration recovers the earliest zero balance after the latest nonzero
balance from active and archived history, breaking timestamp ties by history
ID. Items without recoverable history start at installation time with
`stockoutDateSource: 'observed'`; their tooltip explicitly says the actual
duration may be longer. No earlier date is invented. Rerunning the migration
preserves existing episode dates. The installer needs CREATE TABLE and TRIGGER
privileges; ordinary dashboard requests perform no schema changes.

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
C:\xampp\php\php.exe tests/stockout_tracking_test.php
```

The Node tests require Node 22.13+ with `node:sqlite`. They test calendar dates,
strict status boundaries, date projections, SQL stockout episodes using an
in-memory fixture database, and actual chart-renderer transitions. They do not
modify the live database. PHP date tests use explicit dates for repeatability.
The stockout integration test creates and removes a uniquely named fixture
database on local MySQL/MariaDB; it checks backfill, archived history, repeat
installation, inserts, depletion, restocking, and transactional rollback.
