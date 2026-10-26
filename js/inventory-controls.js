(() => {
    if (window.inventoryControls) return;
    const initialized = new WeakSet(), menus = new Set();
    const options = document.getElementById('officerDropdownPositions');
    const positions = options ? JSON.parse(options.textContent) : {};
    const names = Object.keys(positions);
    function closeOthers(active) {
        menus.forEach(menu => { if (menu !== active) menu.style.display = 'none'; });
    }
    function setupOfficer(inputId, dropdownId, designationId) {
        const input = document.getElementById(inputId), menu = document.getElementById(dropdownId);
        if (!input || !menu || initialized.has(input)) return;
        initialized.add(input); menus.add(menu);
        input.setAttribute('data-officer-autocomplete', '');
        menu.classList.add('officer-dropdown');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', dropdownId);
        const designation = document.getElementById(designationId || input.dataset.designationTarget || '');
        function fillPosition() {
            if (designation) {
                designation.value = positions[input.value] || '';
                designation.dispatchEvent(new Event('input', {bubbles: true}));
            }
        }
        function select(name) {
            input.value = name;
            fillPosition();
            input.dispatchEvent(new Event('input', {bubbles: true}));
            menu.style.display = 'none';
            input.dispatchEvent(new Event('change', {bubbles: true}));
        }
        function render() {
            closeOthers(menu); menu.replaceChildren();
            const query = input.value.trim().toLowerCase();
            const matches = names.filter(name => name.toLowerCase().includes(query));
            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'autocomplete-item officer-option-empty';
                empty.textContent = names.length ? 'No matches found' : 'No officers available';
                menu.append(empty);
            }
            matches.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item'; item.dataset.officerName = name;
                const nameLine = document.createElement('span'); nameLine.className = 'officer-option-name';
                const index = name.toLowerCase().indexOf(query);
                if (query && index >= 0) {
                    const match = document.createElement('strong'); match.textContent = name.slice(index, index + query.length);
                    nameLine.append(document.createTextNode(name.slice(0, index)), match, document.createTextNode(name.slice(index + query.length)));
                } else nameLine.textContent = name;
                item.append(nameLine);
                if (positions[name]) {
                    const position = document.createElement('small'); position.className = 'officer-option-position';
                    position.textContent = positions[name]; item.append(position);
                }
                item.addEventListener('click', () => select(name)); menu.append(item);
            });
            menu.style.display = 'block';
        }
        let openOnPointerDown = false;
        input.addEventListener('pointerdown', () => { openOnPointerDown = menu.style.display === 'block'; });
        input.addEventListener('focus', render);
        input.addEventListener('click', event => {
            const wasOpen = event.detail === 0 ? menu.style.display === 'block' : openOnPointerDown;
            openOnPointerDown = false;
            if (wasOpen) menu.style.display = 'none'; else render();
        });
        input.addEventListener('input', () => { fillPosition(); render(); });
        input.addEventListener('keydown', event => {
            if (menu.style.display !== 'block') return;
            if (event.key === 'Escape') { menu.style.display = 'none'; return; }
            const items = Array.from(menu.querySelectorAll('[data-officer-name]'));
            if (!items.length) return;
            const selected = menu.querySelector('.selected'), index = items.indexOf(selected);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const next = event.key === 'ArrowDown' ? (index + 1) % items.length : (index <= 0 ? items.length - 1 : index - 1);
                items.forEach(item => item.classList.remove('selected')); items[next].classList.add('selected');
                items[next].scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                if (event.key === 'Enter') event.preventDefault();
                const item = selected || (event.key === 'Tab' ? items[0] : null);
                if (item) select(item.dataset.officerName);
            }
        });
        document.addEventListener('click', event => {
            if (!input.contains(event.target) && !menu.contains(event.target)) menu.style.display = 'none';
        });
    }
    function filterRows(rows, query) {
        const search = query.trim().toLowerCase(); let visible = 0;
        Array.from(rows).forEach(row => { row.hidden = !row.textContent.toLowerCase().includes(search); if (!row.hidden) visible++; });
        return visible;
    }
    window.inventoryControls = {setupOfficer, filterRows};
    // Forms configure field IDs; dropdown behavior is implemented only here.
    window.setupAutocomplete = setupOfficer;
    document.addEventListener('focusin', event => {
        const active = Array.from(menus).find(menu => menu.id === event.target.id + '_dropdown' || menu.contains(event.target));
        closeOthers(active);
    });
    const initialize = () => document.querySelectorAll('[data-officer-autocomplete]').forEach(input => setupOfficer(input.id, input.id + '_dropdown'));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize); else initialize();
})();