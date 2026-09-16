// ACTION MENU

document.addEventListener('DOMContentLoaded', function () {

    let activeMenu = null;
    let activeToggle = null;
    let activeMenuList = null;
    let originalParent = null;
    let originalNextSibling = null;

    function closeActionMenu() {

        if (!activeMenu || !activeMenuList) {
            return;
        }

        activeMenu.classList.remove('is-open');

        if (activeToggle) {
            activeToggle.setAttribute('aria-expanded', 'false');
        }

        // Return the menu list to its original location
        if (originalParent) {

            if (originalNextSibling) {
                originalParent.insertBefore(activeMenuList, originalNextSibling);
            } else {
                originalParent.appendChild(activeMenuList);
            }

        }

        // Clear inline positioning
        activeMenuList.style.position = '';
        activeMenuList.style.left = '';
        activeMenuList.style.top = '';
        activeMenuList.style.zIndex = '';
        activeMenuList.style.display = '';
        activeMenuList.style.visibility = '';

        activeMenu = null;
        activeToggle = null;
        activeMenuList = null;
        originalParent = null;
        originalNextSibling = null;
    }


    function positionActionMenu() {

        if (!activeToggle || !activeMenuList) {
            return;
        }

        const toggleRect = activeToggle.getBoundingClientRect();

        // Make sure the menu is measurable
        activeMenuList.style.display = 'block';
        activeMenuList.style.visibility = 'hidden';

        const menuRect = activeMenuList.getBoundingClientRect();

        let left = toggleRect.right - menuRect.width;
        let top = toggleRect.bottom + 6;

        const padding = 10;

        // Keep menu inside the right side of the screen
        if (left + menuRect.width > window.innerWidth - padding) {
            left = window.innerWidth - menuRect.width - padding;
        }

        // Keep menu inside the left side of the screen
        if (left < padding) {
            left = padding;
        }

        // If there isn't enough room below, place it above
        if (top + menuRect.height > window.innerHeight - padding) {
            top = toggleRect.top - menuRect.height - 6;
        }

        // Prevent it from going above the screen
        if (top < padding) {
            top = padding;
        }

        activeMenuList.style.left = left + 'px';
        activeMenuList.style.top = top + 'px';
        activeMenuList.style.visibility = 'visible';
    }


    function openActionMenu(toggle) {

        const menu = toggle.closest('.actions-menu');

        if (!menu) {
            return;
        }

        const menuList = menu.querySelector('.actions-menu-list');

        if (!menuList) {
            return;
        }

        // Close currently open menu first
        if (activeMenu && activeMenu !== menu) {
            closeActionMenu();
        }

        // If this menu is already open, close it
        if (menu.classList.contains('is-open')) {
            closeActionMenu();
            return;
        }

        activeMenu = menu;
        activeToggle = toggle;
        activeMenuList = menuList;

        // Remember where the menu originally was
        originalParent = menuList.parentElement;
        originalNextSibling = menuList.nextSibling;

        // Open state
        menu.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');

        // Move dropdown directly under <body>
        document.body.appendChild(menuList);

        // Position it relative to the button
        activeMenuList.style.position = 'fixed';
        activeMenuList.style.zIndex = '999999';

        positionActionMenu();
    }


    // Handle clicks
    document.addEventListener('click', function (event) {

        const toggle = event.target.closest('.actions-menu-toggle');

        if (toggle) {
            event.preventDefault();
            event.stopPropagation();

            openActionMenu(toggle);
            return;
        }

        // Clicking inside the currently open menu
        if (activeMenuList && event.target.closest('.actions-menu-list') === activeMenuList) {
            return;
        }

        // Clicking somewhere else
        if (activeMenu) {
            closeActionMenu();
        }

    });


    // Reposition when scrolling
    window.addEventListener('scroll', function () {

        if (activeMenu) {
            positionActionMenu();
        }

    }, true);


    // Reposition when window is resized
    window.addEventListener('resize', function () {

        if (activeMenu) {
            positionActionMenu();
        }

    });

});