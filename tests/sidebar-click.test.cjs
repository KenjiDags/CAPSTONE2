const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element extends EventTarget {
  constructor() {
    super(); this.attrs = {}; const values = new Set();
    this.classList = { contains: name => values.has(name), remove: name => values.delete(name),
      toggle(name, force = !values.has(name)) { if (force) values.add(name); else values.delete(name); return force; } };
  }
  setAttribute(key, value) { this.attrs[key] = value; }
  getAttribute(key) { return this.attrs[key]; }
  focus() {}
}
const sidebar = new Element(), button = new Element(), toggle = new Element(), folder = new Element();
const ppeToggle = new Element(), ppeFolder = new Element();
ppeToggle.closest = () => ppeFolder;
ppeToggle.setAttribute('aria-controls', 'ppe-menu');
toggle.closest = () => folder;
toggle.setAttribute('aria-controls', 'office-menu');
sidebar.querySelector = () => button;
sidebar.querySelectorAll = () => [toggle, ppeToggle];
const document = new Element(), window = new Element();
document.querySelector = () => sidebar;
document.body = new Element();
document.baseURI = 'http://localhost/CAPSTONE2/inventory.php';
sidebar.contains = target => [sidebar, toggle, ppeToggle, button].includes(target);
const storage = new Map();
const context = { document, window, AbortController, URL, localStorage: { getItem: () => null },
    sessionStorage: { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value) } };
const source = fs.readFileSync('js/sidebar_script.js', 'utf8');
vm.runInNewContext(source, context);
assert.equal(sidebar.classList.contains('is-open'), false);
sidebar.dispatchEvent(new Event('mouseenter'));
assert.equal(sidebar.classList.contains('is-open'), false);
toggle.dispatchEvent(new Event('click'));
assert.equal(sidebar.classList.contains('is-open'), true);
assert.equal(folder.classList.contains('open'), true);
ppeToggle.dispatchEvent(new Event('click'));
assert.equal(folder.classList.contains('open'), false, 'Opening PPE closes Office Supplies');
assert.equal(toggle.attrs['aria-expanded'], 'false');
assert.equal(ppeFolder.classList.contains('open'), true);
assert.equal(ppeToggle.attrs['aria-expanded'], 'true');
ppeToggle.dispatchEvent(new Event('click'));
assert.equal(ppeFolder.classList.contains('open'), false, 'Active category toggles closed');
toggle.dispatchEvent(new Event('click'));
assert.equal(folder.classList.contains('open'), true);
assert.equal(ppeFolder.classList.contains('open'), false);
const linkClick = new Event('click', { bubbles: true, cancelable: true });
let propagationStopped = false;
linkClick.stopPropagation = () => { propagationStopped = true; };
sidebar.dispatchEvent(linkClick);
assert.equal(propagationStopped, true);
assert.equal(linkClick.defaultPrevented, false, 'Links must still navigate');
assert.equal(folder.classList.contains('open'), true);
const inside = new Event('click');
Object.defineProperty(inside, 'target', { value: toggle });
document.dispatchEvent(inside);
assert.equal(folder.classList.contains('open'), true);
document.dispatchEvent(new Event('click'));
assert.equal(sidebar.classList.contains('is-open'), false);
assert.equal(folder.classList.contains('open'), false);
assert.equal(toggle.attrs['aria-expanded'], 'false');
toggle.dispatchEvent(new Event('click'));
assert.equal(sidebar.classList.contains('is-open'), true);
assert.equal(folder.classList.contains('open'), true);
vm.runInNewContext(source, context);
assert.equal(sidebar.classList.contains('is-open'), true, 'Remount must preserve open sidebar');
assert.equal(folder.classList.contains('open'), true, 'Remount must preserve open submenu');
toggle.dispatchEvent(new Event('click'));
assert.equal(folder.classList.contains('open'), false, 'Remount must not double-toggle');
assert.equal(sidebar.classList.contains('is-open'), true, 'Closing a category should keep drawer expanded');
toggle.dispatchEvent(new Event('click'));
// Simulate a new PHP page: window state disappears, session storage survives.
window.sidebarNavigationState = undefined;
vm.runInNewContext(source, context);
assert.equal(sidebar.classList.contains('is-open'), true, 'Navigation must preserve sidebar');
assert.equal(folder.classList.contains('open'), true, 'Navigation must preserve submenu');
window.destroySidebarNavigation();
document.dispatchEvent(new Event('click'));
assert.equal(sidebar.classList.contains('is-open'), true, 'Cleanup must remove outside listener');
assert.ok(!fs.readFileSync('css/hover-sidebar.css', 'utf8').includes(':is(:hover, :has(:focus-visible))'));
// Legacy flex/display toggles must never override the animated grid sidebar.
const baseStyles = fs.readFileSync('css/styles.css', 'utf8');
assert.ok(!baseStyles.includes('.sidebar .dropdown.open .dropdown-menu { display: flex; }'));
assert.match(fs.readFileSync('css/hover-sidebar.css', 'utf8'), /\.sidebar\.sidebar--hover\.is-open \.dropdown\.open \.dropdown-menu\s*\{\s*display: grid;/);
console.log('Sidebar click, outside click, submenu, remount, and cleanup checks passed.');
