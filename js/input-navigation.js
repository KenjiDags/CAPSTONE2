(() => {
    if (window.inventoryInputNavigation) return;
    window.inventoryInputNavigation = true;
    const selector = 'input:not([type]), input[type="text"], input[type="number"], input[type="email"], input[type="password"], input[type="search"], input[type="tel"], input[type="url"], textarea';
    function editable(field) {
        return field.matches(selector) && !field.disabled && !field.readOnly
            && field.tabIndex >= 0 && field.getClientRects().length > 0
            && getComputedStyle(field).visibility !== 'hidden';
    }
    // Select zero defaults so the first typed digit replaces them without changing stored values.
    const isEntryPage = /(?:^|\/)(?:add[^/]*|edit[^/]*)\.php$/i.test(location.pathname);
    if (isEntryPage) {
        const selectZero = event => {
            const field = event.target;
            if (!(field instanceof HTMLInputElement) || !editable(field)) return;
            const numeric = field.type === 'number'
                || (['text', ''].includes(field.type) && ['numeric', 'decimal'].includes(field.inputMode));
            if (!numeric || !/^[+-]?0+(?:\.0+)?$/.test(field.value.trim())) return;
            field.select();
        };
        document.addEventListener('focusin', selectZero);
        // A mouse click can collapse the selection made by focusin; restore it afterward.
        document.addEventListener('click', selectZero);
    }
    document.addEventListener('keydown', event => {
        if (event.defaultPrevented || event.isComposing || event.altKey || event.ctrlKey
            || event.metaKey || event.shiftKey
            || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        const field = event.target;
        if (!(field instanceof HTMLElement) || !editable(field)
            || field.getAttribute('aria-expanded') === 'true'
            || field.getAttribute('role') === 'combobox'
            || field.closest('[data-arrow-navigation="off"]')) return;
        const horizontal = event.key === 'ArrowLeft' || event.key === 'ArrowRight';
        const forward = event.key === 'ArrowRight' || event.key === 'ArrowDown';
        const gridNavigation = field.closest('[data-arrow-navigation="grid"]');
        // Keep normal cursor movement and selection inside entered text.
        if (horizontal && !gridNavigation && field.value && field.selectionStart !== null) {
            if (field.selectionStart !== field.selectionEnd) return;
            if (forward ? field.selectionEnd < field.value.length : field.selectionStart > 0) return;
        }
        if (!horizontal && field.tagName === 'TEXTAREA' && field.value) return;
        let scope = field.closest('table') || field.form;
        let fields = scope ? Array.from(scope.querySelectorAll(selector)).filter(editable) : [];
        if (fields.length < 2) {
            scope = field.closest('main, .container, .content, .settings-section') || document.body;
            fields = Array.from(scope.querySelectorAll(selector)).filter(editable);
        }
        const origin = field.getBoundingClientRect();
        const x = origin.left + origin.width / 2;
        const y = origin.top + origin.height / 2;
        let next = null;
        let best = Infinity;
        fields.forEach(candidate => {
            if (candidate === field) return;
            const rect = candidate.getBoundingClientRect();
            const dx = rect.left + rect.width / 2 - x;
            const dy = rect.top + rect.height / 2 - y;
            const primary = horizontal ? dx : dy;
            const cross = Math.abs(horizontal ? dy : dx);
            if ((forward ? primary : -primary) < 4) return;
            // Horizontal navigation stays on the same visual row.
            if (horizontal && (rect.bottom <= origin.top || rect.top >= origin.bottom)) return;
            const score = Math.abs(primary) + cross * 4;
            if (score < best) { best = score; next = candidate; }
        });
        if (next) {
            event.preventDefault();
            next.focus({ preventScroll: true });
            next.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        } else if (!horizontal && field.type === 'number') {
            // At the edge of the form, do not increment/decrement the value.
            event.preventDefault();
        }
    });
})();