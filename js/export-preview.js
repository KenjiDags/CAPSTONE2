(() => {
    'use strict';
    const config = document.querySelector('script[data-export-preview]');
    if (!config) return;
    const body = document.body;
    const originalStyle = getComputedStyle(body);
    const originalFont = { family: originalStyle.fontFamily, size: originalStyle.fontSize, lineHeight: originalStyle.lineHeight };
    const controls = Array.from(body.querySelectorAll('.no-print, .export-instructions, .button-container, .instruction-box'));
    const oldBack = controls.flatMap(control => Array.from(control.querySelectorAll('a[href]'))).find(link => !/edit/i.test(link.textContent));
    const backUrl = oldBack && oldBack.getAttribute('href') !== '#' ? oldBack.href : new URL(config.dataset.previewBack, document.baseURI).href;
    controls.forEach(control => control.classList.add('export-preview-legacy'));
    const shell = document.createElement('main');
    shell.className = 'export-preview-shell';
    const icon = name => {
        const paths = {
            document: '<rect x="5" y="3" width="14" height="18" rx="3"/><path d="M9 8h6M9 12h4"/>',
            print: '<path d="M7 8V3h10v5M7 17H4V9h16v8h-3"/><path d="M7 14h10v7H7zM17 11h.01"/>',
            back: '<path d="m12 5-7 7 7 7M5 12h14"/>',
            info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>'
        };
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.8');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.innerHTML = paths[name];
        return svg;
    };
    const header = document.createElement('header');
    header.className = 'export-preview-header export-preview-chrome';
    const titleGroup = document.createElement('div');
    const heading = document.createElement('div');
    heading.className = 'export-preview-heading';
    const title = document.createElement('h1');
    title.textContent = config.dataset.previewTitle;
    heading.append(icon('document'), title);
    const subtitle = document.createElement('p');
    subtitle.className = 'export-preview-subtitle';
    subtitle.textContent = 'Review your document before printing or saving.';
    titleGroup.append(heading, subtitle);
    const badge = document.createElement('span');
    badge.className = 'export-preview-badge';
    const params = new URL(window.location.href).searchParams;
    const reference = config.dataset.previewReference || ['rpcsp_id', 'regspi_id', 'rpci_id', 'rsmi_id', 'ics_id', 'itr_id', 'rrsp_id', 'ptr_id', 'id', 'item_id'].map(key => params.get(key)).find(Boolean) || '';
    badge.textContent = config.dataset.previewCode + (reference ? ' \u00b7 ' + reference : '');
    header.append(titleGroup, badge);
    const toolbar = document.createElement('nav');
    toolbar.className = 'export-preview-toolbar export-preview-chrome';
    toolbar.setAttribute('aria-label', 'Document preview actions');
    const print = document.createElement('button');
    print.type = 'button';
    print.className = 'export-preview-action export-preview-action-primary';
    print.append(icon('print'), document.createTextNode('Print / Save as PDF'));
    print.addEventListener('click', () => window.print());
    const back = document.createElement('a');
    back.className = 'export-preview-action export-preview-back';
    back.href = backUrl;
    back.append(icon('back'), document.createTextNode('Back'));
    toolbar.append(print, back);
    const edits = controls.flatMap(control => Array.from(control.querySelectorAll('a[href]'))).filter(link => /edit/i.test(link.textContent));
    const seenEdits = new Set();
    edits.forEach(link => {
        if (seenEdits.has(link.href)) return;
        seenEdits.add(link.href);
        const edit = document.createElement('a');
        edit.className = 'export-preview-action';
        edit.href = link.href;
        edit.textContent = 'Edit';
        toolbar.append(edit);
    });
    const info = document.createElement('button');
    info.type = 'button';
    info.id = 'export-preview-info';
    info.className = 'export-preview-action export-preview-info';
    info.setAttribute('aria-expanded', 'false');
    info.setAttribute('aria-controls', 'export-preview-instructions');
    info.append(icon('info'), document.createTextNode('Info'));
    const instructions = document.createElement('p');
    instructions.id = 'export-preview-instructions';
    instructions.className = 'export-preview-instructions export-preview-chrome';
    instructions.hidden = true;
    instructions.setAttribute('role', 'note');
    instructions.textContent = "Click Print / Save as PDF, select Save as PDF as the destination in your browser's print dialog, then save the document.";
    info.addEventListener('click', () => {
        instructions.hidden = !instructions.hidden;
        info.setAttribute('aria-expanded', String(!instructions.hidden));
        fitPreview();
    });
    toolbar.append(info);
    const zoomBar = document.createElement('div');
    zoomBar.className = 'export-preview-zoom';
    zoomBar.setAttribute('role', 'group');
    zoomBar.setAttribute('aria-label', 'Preview zoom controls');
    const zoomLabel = document.createElement('label');
    zoomLabel.htmlFor = 'export-preview-zoom-range';
    zoomLabel.textContent = 'Zoom';
    const zoomOut = document.createElement('button');
    zoomOut.type = 'button';
    zoomOut.className = 'export-preview-action';
    zoomOut.setAttribute('aria-label', 'Zoom out');
    zoomOut.textContent = '\u2212';
    const zoomRange = document.createElement('input');
    zoomRange.type = 'range';
    zoomRange.id = 'export-preview-zoom-range';
    zoomRange.min = '25';
    zoomRange.max = '200';
    zoomRange.step = '5';
    zoomRange.title = 'Scroll up to zoom in; scroll down to zoom out';
    const zoomOutput = document.createElement('output');
    zoomOutput.id = 'export-preview-zoom-value';
    zoomOutput.setAttribute('for', zoomRange.id);
    zoomOutput.setAttribute('aria-live', 'polite');
    const zoomIn = document.createElement('button');
    zoomIn.type = 'button';
    zoomIn.className = 'export-preview-action';
    zoomIn.setAttribute('aria-label', 'Zoom in');
    zoomIn.textContent = '+';
    const zoomFit = document.createElement('button');
    zoomFit.type = 'button';
    zoomFit.className = 'export-preview-action export-preview-reset';
    zoomFit.textContent = 'Reset';
    zoomFit.setAttribute('aria-label', 'Reset preview zoom');
    const zoomStorageKey = 'export-preview-zoom:' + window.location.pathname;
    let manualScale = null;
    try {
        const savedZoom = localStorage.getItem(zoomStorageKey);
        const scale = Number(savedZoom);
        if (savedZoom !== null && Number.isFinite(scale) && scale >= .25 && scale <= 2) manualScale = scale;
    } catch (_) { /* Use automatic sizing when storage is unavailable. */ }
    let currentScale = manualScale === null ? 1 : manualScale;
    function setZoom(scale) {
        manualScale = Math.max(.25, Math.min(2, scale));
        try { localStorage.setItem(zoomStorageKey, String(manualScale)); } catch (_) {}
        fitPreview();
    }
    zoomOut.addEventListener('click', () => setZoom(currentScale - .1));
    zoomIn.addEventListener('click', () => setZoom(currentScale + .1));
    zoomRange.addEventListener('input', () => setZoom(Number(zoomRange.value) / 100));
    zoomFit.addEventListener('click', () => {
        manualScale = null;
        try { localStorage.removeItem(zoomStorageKey); } catch (_) {}
        fitPreview();
    });
    zoomBar.append(zoomLabel, zoomOut, zoomRange, zoomIn, zoomOutput, zoomFit);
    const stage = document.createElement('section');
    stage.className = 'export-preview-stage';
    stage.setAttribute('aria-label', 'Document preview');
    const paper = document.createElement('div');
    paper.className = 'export-preview-paper';
    const documentView = document.createElement('div');
    documentView.className = 'export-preview-document';
    documentView.style.fontFamily = originalFont.family;
    documentView.style.fontSize = originalFont.size;
    documentView.style.lineHeight = originalFont.lineHeight;
    const width = Number(config.dataset.previewWidth) || 850;
    documentView.style.width = width + 'px';
    const entries = [];
    // Mark original positions so printing uses the original document structure.
    Array.from(body.childNodes).forEach(node => {
        if (node.nodeType === Node.COMMENT_NODE || (node.nodeType === Node.TEXT_NODE && !node.textContent.trim())) return;
        if (node.nodeType === Node.ELEMENT_NODE && (node.matches('script, style, link') || node.classList.contains('export-preview-legacy'))) return;
        const anchor = document.createComment('export document position');
        node.before(anchor);
        entries.push({ node, anchor });
        documentView.append(node);
    });
    paper.append(documentView);
    const caption = document.createElement('p');
    caption.className = 'export-preview-caption';
    stage.append(paper, caption);
    toolbar.insertBefore(zoomBar, info);
    toolbar.append(instructions);
    shell.append(header, toolbar, stage);
    body.append(shell);
    body.classList.add('export-preview-ready');
    // Mirror the document's horizontal scroll at the bottom of the viewport.
    const bottomScroll = document.createElement('div');
    bottomScroll.className = 'export-preview-bottom-scroll export-preview-chrome';
    bottomScroll.hidden = true;
    bottomScroll.tabIndex = 0;
    bottomScroll.setAttribute('role', 'region');
    bottomScroll.setAttribute('aria-label', 'Scroll document horizontally');
    const scrollTrack = document.createElement('div');
    scrollTrack.className = 'export-preview-scroll-track';
    bottomScroll.append(scrollTrack);
    shell.append(bottomScroll);
    function updateHorizontalScroll() {
        const overflowing = stage.scrollWidth > stage.clientWidth + 1;
        bottomScroll.hidden = !overflowing;
        shell.classList.toggle('export-preview-has-horizontal-scroll', overflowing);
        if (!overflowing) return;
        const bounds = stage.getBoundingClientRect();
        bottomScroll.style.left = bounds.left + 'px';
        bottomScroll.style.width = stage.clientWidth + 'px';
        scrollTrack.style.width = stage.scrollWidth + 'px';
        bottomScroll.scrollLeft = stage.scrollLeft;
    }
    stage.addEventListener('scroll', () => {
        if (bottomScroll.scrollLeft !== stage.scrollLeft) bottomScroll.scrollLeft = stage.scrollLeft;
    }, { passive: true });
    bottomScroll.addEventListener('scroll', () => {
        if (stage.scrollLeft !== bottomScroll.scrollLeft) stage.scrollLeft = bottomScroll.scrollLeft;
    }, { passive: true });

    let printing = false;
    // Wheel zoom is confined to the document preview; the rest of the page scrolls normally.
    function handleWheelZoom(event) {
        if (printing || event.deltaY === 0) return;
        event.preventDefault();
        setZoom(currentScale + (event.deltaY < 0 ? .05 : -.05));
    }
    // Capture wheel input over the range track/thumb as well as the surrounding preview.
    document.addEventListener('wheel', event => {
        if (!zoomBar.contains(event.target) && !stage.contains(event.target)) return;
        handleWheelZoom(event);
    }, { capture: true, passive: false });
    function fitPreview() {
        if (printing) return;
        documentView.style.transform = 'none';
        const contentWidth = Math.max(width, documentView.scrollWidth);
        const contentHeight = Math.max(1, documentView.scrollHeight);
        const availableWidth = Math.max(180, stage.clientWidth - 48);
        const availableHeight = Math.max(280, window.innerHeight - stage.getBoundingClientRect().top - 62);
        const scale = manualScale === null ? Math.min(1, availableWidth / contentWidth, Math.max(.55, availableHeight / contentHeight)) : manualScale;
        currentScale = scale;
        zoomRange.value = String(Math.round(scale * 100));
        zoomOutput.textContent = Math.round(scale * 100) + '%';
        zoomOut.disabled = scale <= .25;
        zoomIn.disabled = scale >= 2;
        documentView.style.transform = 'scale(' + scale + ')';
        paper.style.width = Math.ceil(contentWidth * scale + 24) + 'px';
        paper.style.height = Math.ceil(contentHeight * scale + 24) + 'px';
        updateHorizontalScroll();
    }
    function applyTheme() {
        let dark = false;
        try { dark = localStorage.getItem('theme') === 'dark'; } catch (_) {}
        document.documentElement.dataset.exportPreviewTheme = dark ? 'dark' : 'light';
        caption.textContent = 'Document preview \u2022 ' + (dark ? 'White paper preserved in dark mode' : 'Ready for printing or saving');
    }
    applyTheme();
    window.addEventListener('storage', event => { if (event.key === 'theme') applyTheme(); });
    window.addEventListener('resize', fitPreview);
    window.addEventListener('load', fitPreview);
    window.addEventListener('scroll', () => { if (!printing) updateHorizontalScroll(); }, { passive: true });
    documentView.querySelectorAll('img').forEach(img => img.addEventListener('load', fitPreview));
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitPreview);
    if ('ResizeObserver' in window) new ResizeObserver(fitPreview).observe(documentView);
    window.addEventListener('beforeprint', () => {
        printing = true;
        bottomScroll.hidden = true;
        entries.forEach(entry => entry.anchor.after(entry.node));
    });
    window.addEventListener('afterprint', () => {
        entries.forEach(entry => documentView.append(entry.node));
        printing = false;
        fitPreview();
    });
    fitPreview();
})();