document.querySelectorAll('.sidebar .dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const parent = toggle.closest('.dropdown');
        const isOpen = parent.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });
});

try {
    if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
} catch (error) { /* Keep the default theme when storage is unavailable. */ }
