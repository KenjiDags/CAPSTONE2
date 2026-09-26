import React, { useEffect, useMemo, useRef, useState } from 'react';
import Chart from 'chart.js/auto';
import MRPStock from './mrp-stock.js';

// Use with a React bundler supporting CommonJS imports. Pass fresh, immutable
// items from the API and a handler that opens the restock or PO creation flow.
export default function MRPStockChart({ items, onRestock }) {
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [today, setToday] = useState(() => MRPStock.businessDate());
  const canvas = useRef(null);
  useEffect(() => {
    const tick = () => setToday(MRPStock.businessDate());
    const timer = setInterval(tick, 1000);
    window.addEventListener('focus', tick);
    document.addEventListener('visibilitychange', tick);
    return () => {
      clearInterval(timer);
      window.removeEventListener('focus', tick);
      document.removeEventListener('visibilitychange', tick);
    };
  }, []);
  const rows = useMemo(() => MRPStock.selectItems(items, status, search, today), [items, status, search, today]);
  const config = useMemo(() => MRPStock.chartConfig(rows, status, onRestock), [rows, status, onRestock]);
  useEffect(() => {
    if (!rows.length) return;
    const chart = new Chart(canvas.current, config);
    return () => chart.destroy(); // Dispose the old dataset and all hover state.
  }, [config, rows.length]);

  return <section aria-label="MRP stock chart">
    <div className="mrp-status-legend" role="group" aria-label="Stock status">
      {[['all', 'All'], ['safe', 'Safe'], ['low', 'Low Stock'], ['critical', 'Critical (MRP)'], ['empty', 'Empty']].map(([value, label]) =>
        <button key={value} type="button" aria-pressed={status === value}
          className={status === value ? 'active' : ''} onClick={() => setStatus(value)}>{label}</button>)}
    </div>
    <label>Search stock items <input type="search" value={search} onChange={event => setSearch(event.target.value)} /></label>
    <p>Quantity (units). Critical: greater than 1 and less than 5.</p>
    {rows.length ? <div style={{ height: 390, position: 'relative' }}>
      <canvas ref={canvas} role="img" aria-label={`${status} stock chart`} />
    </div> : <p role="status">No matching items in this view.</p>}
    {(status === 'critical' || status === 'empty') && <div className="mrp-critical-details">
      {rows.map(item => <article className="mrp-critical-item" key={item.id}>
        <strong>{item.sku} — {item.itemName}</strong>
        {(status === 'empty' ? ['Quantity: 0 units', ...MRPStock.tooltip(item)] : MRPStock.tooltip(item)).map((line, index) => <p key={line} className={index === 0 ? 'mrp-primary-metric' : undefined}>{line}</p>)}
        <button type="button" onClick={() => onRestock(item)}>Open Restock Inventory</button>
      </article>)}
    </div>}
  </section>;
}
