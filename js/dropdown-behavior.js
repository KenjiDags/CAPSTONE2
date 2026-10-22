(() => {
    if (window.inventoryDropdownBehavior) return;
    window.inventoryDropdownBehavior = true;
    const pointerState = new WeakMap();
    function pairs() {
        return Array.from(document.querySelectorAll('.autocomplete-dropdown[id]')).map(menu => {
            const inputId = menu.id.replace(/_dropdown$/, '');
            const input = document.getElementById(inputId);
            return { input, menu };
        }).filter(pair => pair.input instanceof HTMLInputElement
            && !pair.input.hasAttribute('data-officer-autocomplete'));
    }
    function closeOthers(active) {
        pairs().forEach(pair => {
            if (pair.input !== active) pair.menu.style.display = 'none';
        });
    }
    // Capture runs before page-specific click handlers that stop propagation.
    document.addEventListener('pointerdown', event => {
        const entries = pairs();
        const current = entries.find(pair => pair.input === event.target || pair.menu.contains(event.target));
        if (current?.input === event.target) {
            pointerState.set(current.input, getComputedStyle(current.menu).display !== 'none');
        }
        closeOthers(current?.input);
    }, true);
    document.addEventListener('focusin', event => {
        const current = pairs().find(pair => pair.input === event.target || pair.menu.contains(event.target));
        closeOthers(current?.input);
    }, true);
    document.addEventListener('click', event => {
        const pair = pairs().find(entry => entry.input === event.target);
        if (!pair) return;
        const wasOpen = event.detail === 0
            ? getComputedStyle(pair.menu).display !== 'none'
            : pointerState.get(pair.input);
        pointerState.delete(pair.input);
        closeOthers(pair.input);
        if (wasOpen) {
            pair.menu.style.display = 'none';
            // Prevent the original handler from reopening it on this click.
            event.stopImmediatePropagation();
        }
    }, true);
})();