document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const parent = toggle.closest('.dropdown');
        parent.classList.toggle('open');
    });
});

// Logo click handler - go back if on user_settings page
const sidebarLogo = document.getElementById('sidebar-logo');
if (sidebarLogo) {
    sidebarLogo.addEventListener('click', function(e) {
        const currentPage = window.location.pathname.split('/').pop();
        if (currentPage === 'user_settings.php') {
            e.preventDefault();
            window.history.back();
        }
    });
}
