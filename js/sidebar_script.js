(() => {
    if (window.destroySidebarNavigation) window.destroySidebarNavigation();
    let controller;
    let tooltip;
    function hideTooltip() { if (tooltip) tooltip.hidden = true; }
    function mount() {
        if (controller) controller.abort();
        tooltip?.remove();
        const sidebar = document.querySelector('.sidebar--hover');
        if (!sidebar) return;
        sidebar.classList.add('is-restoring');
        controller = new AbortController();
        const options = { signal: controller.signal };
        const toggles = sidebar.querySelectorAll('.dropdown-toggle');
        const edgeToggle = document.querySelector('.sidebar-edge-toggle');
        tooltip = document.createElement('div');
        tooltip.className = 'sidebar-icon-tooltip';
        tooltip.hidden = true;
        // The links already include their accessible text labels.
        tooltip.setAttribute('aria-hidden', 'true');
        document.body.appendChild(tooltip);
        function showTooltip(control) {
            if (sidebar.classList.contains('is-open')) return;
            const label = control.querySelector('span')?.textContent.trim();
            if (!label) return;
            tooltip.textContent = label;
            tooltip.hidden = false;
            const rect = control.getBoundingClientRect();
            const bubble = tooltip.getBoundingClientRect();
            const top = Math.max(8, Math.min(rect.top + (rect.height - bubble.height) / 2, window.innerHeight - bubble.height - 8));
            tooltip.style.left = `${sidebar.getBoundingClientRect().right + 10}px`;
            tooltip.style.top = `${top}px`;
        }
        sidebar.querySelectorAll('nav a, .dropdown-toggle, .logout-btn').forEach(control => {
            control.addEventListener('mouseenter', () => showTooltip(control), options);
            control.addEventListener('mouseleave', hideTooltip, options);
            control.addEventListener('focus', () => showTooltip(control), options);
            control.addEventListener('blur', hideTooltip, options);
            control.addEventListener('click', hideTooltip, options);
        });
        window.addEventListener('resize', hideTooltip, options);
        document.addEventListener('scroll', hideTooltip, { ...options, capture: true, passive: true });
        const storageKey = 'tesda:sidebar:' + new URL('.', document.baseURI).pathname;
        let activeCategory = null;
        function renderCategories() {
            sidebar.classList.toggle('is-compact', !sidebar.classList.contains('is-open') && activeCategory !== null);
            toggles.forEach(toggle => {
                const open = toggle.getAttribute('aria-controls') === activeCategory;
                toggle.closest('.dropdown').classList.toggle('open', open);
                toggle.setAttribute('aria-expanded', String(open));
            });
        }
        function saveState() {
            const state = {
                open: sidebar.classList.contains('is-open'),
                compact: sidebar.classList.contains('is-compact'),
                activeCategory
            };
            window.sidebarNavigationState = state;
            try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch (_) {}
        }
        function setOpen(open) {
            hideTooltip();
            sidebar.classList.toggle('is-open', open);
            edgeToggle?.setAttribute('aria-expanded', String(open));
            edgeToggle?.setAttribute('aria-label', open ? 'Close sidebar' : 'Open sidebar');
            if (!open) activeCategory = null;
            renderCategories();
            saveState();
        }
        let saved = window.sidebarNavigationState;
        try { saved = JSON.parse(sessionStorage.getItem(storageKey)) || saved; } catch (_) {}
        // Migrate older multi-open preferences to one valid category.
        const candidates = saved?.activeCategory ? [saved.activeCategory] : (Array.isArray(saved?.menus) ? saved.menus : []);
        activeCategory = candidates.find(id => Array.from(toggles).some(toggle => toggle.getAttribute('aria-controls') === id)) ?? null;
        // Keep the current page's parent visible when entering a related subpage.
        const currentMenu = sidebar.querySelector('nav a.active')?.closest('.dropdown-menu');
        if (currentMenu) activeCategory = currentMenu.id;
        const restoredCategory = activeCategory;
        setOpen(saved?.open === true);
        if (saved?.compact && !saved?.open && restoredCategory) {
            activeCategory = restoredCategory;
            renderCategories();
            saveState();
        }
        document.documentElement.classList.remove('sidebar-restore-open');
        void sidebar.offsetWidth;
        sidebar.classList.remove('is-restoring');
        // Keep normal link navigation, but don't bubble into global close handlers.
        sidebar.addEventListener('click', event => {
            event.stopPropagation();
            const link = event.target.closest?.('nav a[href]');
            if (!link) return;
            const target = new URL(link.href, document.baseURI);
            // Preserve filters, scroll position and unsaved work on this page.
            // Active section labels can also point to a parent list, so compare paths.
            if (target.origin === location.origin && target.pathname === location.pathname &&
                (sidebar.dataset.queryNavigation !== 'true' || target.search === location.search) &&
                !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey &&
                !link.hasAttribute('download') && (!link.target || link.target === '_self')) {
                event.preventDefault();
            }
            saveState();
        }, options);
        toggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const id = toggle.getAttribute('aria-controls');
                activeCategory = activeCategory === id ? null : id;
                hideTooltip();
                renderCategories();
                saveState();
            }, options);
        });
        edgeToggle?.addEventListener('click', event => {
            event.stopPropagation();
            setOpen(!sidebar.classList.contains('is-open'));
        }, options);
        sidebar.addEventListener('keydown', event => {
            if (event.key === 'Escape') { setOpen(false); edgeToggle?.focus(); }
        }, options);
    }
    const unmount = () => { if (controller) controller.abort(); tooltip?.remove(); };
    window.addEventListener('pagehide', unmount);
    window.addEventListener('pageshow', mount);
    window.destroySidebarNavigation = () => {
        unmount();
        window.removeEventListener('pagehide', unmount);
        window.removeEventListener('pageshow', mount);
    };
    mount();

})();
