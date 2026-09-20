(() => {
    const badge = document.getElementById('office-stock-badge');
    const warning = document.getElementById('office-stock-warning');
    if (!badge || !warning) return;
    const endpoint = new URL('api/inventory/out-of-stock-count.php?category=office-supplies', document.baseURI);
    const storageKey = 'tesda:inventory-updated:' + endpoint.pathname;
    let pending = false;
    let refreshAgain = false;
    let signedOut = false;
    let outOfStockCount = 0;

    function renderBadges() {
        const hasOutOfStockItems = outOfStockCount > 0;
        const text = String(outOfStockCount);
        if (badge.textContent !== text) badge.textContent = text;
        badge.hidden = warning.hidden = !hasOutOfStockItems;
        const label = `${outOfStockCount} office supply items out of stock`;
        for (const element of [badge, warning]) {
            element.setAttribute('aria-label', label);
            element.title = label;
        }
    }

    async function refresh() {
        if (document.hidden || signedOut) return;
        if (pending) { refreshAgain = true; return; }
        pending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            if (response.status === 401) {
                signedOut = true;
                outOfStockCount = 0;
                renderBadges();
                return;
            }
            if (!response.ok) throw new Error('Count unavailable');
            const data = await response.json();
            if (!Number.isInteger(data.count) || data.count < 0) throw new Error('Invalid count');
            outOfStockCount = data.count;
            renderBadges();
        } catch (_) {
            // Keep the last known count during a temporary failure; retry on the next poll.
        } finally {
            clearTimeout(timeout);
            pending = false;
            if (refreshAgain) { refreshAgain = false; refresh(); }
        }
    }

    window.addEventListener('inventory:updated', () => {
        refresh();
        try { localStorage.setItem(storageKey, Date.now() + ':' + Math.random()); } catch (_) {}
    });
    window.addEventListener('storage', event => { if (event.key === storageKey) refresh(); });
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    setInterval(refresh, 10000);
    refresh();
})();
