(() => {
    if (window.destroySidebarNavigation) window.destroySidebarNavigation();
    let controller;
    function mount() {
        if (controller) controller.abort();
        const sidebar = document.querySelector('.sidebar--hover');
        if (!sidebar) return;
        controller = new AbortController();
        const options = { signal: controller.signal };
        const toggles = sidebar.querySelectorAll('.dropdown-toggle');
        const storageKey = 'tesda:sidebar:' + new URL('.', document.baseURI).pathname;
        let activeCategory = null;
        function renderCategories() {
            toggles.forEach(toggle => {
                const open = sidebar.classList.contains('is-open') && toggle.getAttribute('aria-controls') === activeCategory;
                toggle.closest('.dropdown').classList.toggle('open', open);
                toggle.setAttribute('aria-expanded', String(open));
            });
        }
        function saveState() {
            const state = {
                open: sidebar.classList.contains('is-open'),
                activeCategory
            };
            window.sidebarNavigationState = state;
            try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch (_) {}
        }
        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            if (!open) activeCategory = null;
            renderCategories();
            saveState();
        }
        let saved = window.sidebarNavigationState;
        try { saved = JSON.parse(sessionStorage.getItem(storageKey)) || saved; } catch (_) {}
        // Migrate older multi-open preferences to one valid category.
        const candidates = saved?.activeCategory ? [saved.activeCategory] : (Array.isArray(saved?.menus) ? saved.menus : []);
        activeCategory = candidates.find(id => Array.from(toggles).some(toggle => toggle.getAttribute('aria-controls') === id)) ?? null;
        setOpen(saved?.open === true);
        // Keep normal link navigation, but don't bubble into global close handlers.
        sidebar.addEventListener('click', event => {
            event.stopPropagation();
            // Direct navigation from the icon rail also retains the expanded drawer.
            if (event.target.closest?.('a')) setOpen(true);
        }, options);
        toggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const id = toggle.getAttribute('aria-controls');
                activeCategory = activeCategory === id ? null : id;
                setOpen(true);
            }, options);
        });
        document.addEventListener('click', event => {
            if (!sidebar.contains(event.target)) setOpen(false);
        }, options);
        sidebar.addEventListener('keydown', event => {
            if (event.key === 'Escape') { setOpen(false); toggles[0]?.focus(); }
        }, options);
    }
    const unmount = () => { if (controller) controller.abort(); };
    window.addEventListener('pagehide', unmount);
    window.addEventListener('pageshow', mount);
    window.destroySidebarNavigation = () => {
        unmount();
        window.removeEventListener('pagehide', unmount);
        window.removeEventListener('pageshow', mount);
    };
    mount();
    try {
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
    } catch (_) { /* Keep the default theme when storage is unavailable. */ }
})();
