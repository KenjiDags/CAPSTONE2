const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = {}, documentEvents = {};
const badge = { hidden: true, textContent: '', setAttribute(name, value) { this[name] = value; } };
const warning = { ...badge, textContent: '!' };
let count = 5, requests = 0, fail = false, unauthorized = false, poll;
const context = {
    URL, AbortController, setTimeout, clearTimeout, console,
    setInterval(fn) { poll = fn; },
    localStorage: { setItem() {} },
    document: { baseURI: 'http://localhost/CAPSTONE2/inventory.php', hidden: false,
        getElementById: id => id === 'office-stock-badge' ? badge : warning, addEventListener(name, fn) { documentEvents[name] = fn; } },
    window: { addEventListener(name, fn) { events[name] = fn; } },
    async fetch(url) {
        requests++;
        assert.equal(url.pathname, '/CAPSTONE2/api/inventory/out-of-stock-count.php');
        if (fail) throw new Error('offline');
        return { ok: !unauthorized, status: unauthorized ? 401 : 200, json: async () => ({ count }) };
    }
};
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    vm.runInNewContext(fs.readFileSync('js/inventory-badge.js', 'utf8'), context);
    await flush();
    assert.equal(badge.hidden, false);
    assert.equal(badge.textContent, '5');
    assert.equal(warning.textContent, '!');
    assert.equal(warning.hidden, false);
    assert.equal(requests, 1);
    assert.equal(badge['aria-label'], '5 office supply items out of stock');
    count = 0; events['inventory:updated'](); await flush();
    assert.equal(badge.hidden, true);
    assert.equal(warning.hidden, true);
    count = 12; events.focus(); await flush();
    assert.equal(badge.hidden, false);
    assert.equal(warning.hidden, false);
    assert.equal(badge.textContent, '12');
    assert.equal(badge['aria-label'], '12 office supply items out of stock');
    fail = true; poll(); await flush();
    assert.equal(badge.textContent, '12');
    assert.equal(warning.hidden, false);
    fail = false; count = 3; poll(); await flush();
    assert.equal(badge['aria-label'], '3 office supply items out of stock');
    const before = requests;
    context.document.hidden = true; poll(); await flush();
    assert.equal(requests, before);
    context.document.hidden = false; count = 2;
    documentEvents.visibilitychange(); await flush();
    assert.equal(badge['aria-label'], '2 office supply items out of stock');
    count = 105;
    events.storage({ key: 'tesda:inventory-updated:/CAPSTONE2/api/inventory/out-of-stock-count.php' });
    await flush();
    assert.equal(badge.textContent, '105');
    assert.equal(warning.textContent, '!');
    assert.equal(warning['aria-label'], '105 office supply items out of stock');
    unauthorized = true; poll(); await flush();
    assert.equal(badge.hidden, true);
    assert.equal(warning.hidden, true);
    console.log('Badge count, hide, mutation, polling, failure, visibility, and auth checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
