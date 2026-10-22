<?php
require_once 'auth.php';
require_once 'config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$ppePcActive = $currentPage === 'PPE_PC.php' || (in_array($currentPage, ['view_ppe_items.php', 'edit_ppe.php'], true) && isset($ppeReturnUrl) && str_starts_with($ppeReturnUrl, 'PPE/PPE_PC.php'));
$ppeInventoryActive = $currentPage === 'PPE.php' || (in_array($currentPage, ['view_ppe_items.php', 'edit_ppe.php', 'add_ppe.php'], true) && !$ppePcActive);
$sidebarIcon = static function ($name) {
    $paths = [
        'folder' => '<path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'semi' => '<rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/><path d="M7 7h3M7 17h3"/>',
        'equipment' => '<rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'archive' => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8M10 12h4"/>',
        'list' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'chart' => '<path d="M3 3v18h18M8 16v-5M13 16V7M18 16v-8"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M9 12h12M17 8l4 4-4 4"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
    ];
    return '<svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
};
?>

<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/hover-sidebar.css?v=<?= time() ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script>
try {
    const sidebarStateKey = 'tesda:sidebar:' + new URL('.', document.baseURI).pathname;
    if (JSON.parse(sessionStorage.getItem(sidebarStateKey) || 'null')?.open) {
        document.documentElement.classList.add('sidebar-restore-open');
    }
} catch (_) {}
</script>
    <aside class="sidebar sidebar--hover" aria-label="Sidebar navigation">
        <div class="logo-text" id="sidebar-logo">
            <div class="logo">
                <img src="images/tesda_logo.png" alt="TESDA logo" width="64" height="64">
                <h3>TESDA Inventory</h3>
            </div>
        </div>

            <?php
                    $dropdownActive = in_array($currentPage, ['inventory.php', 'ris.php', 'add_ris.php', 'view_ris.php', 'rsmi.php', 'add_rsmi.php', 'SC.php', 'view_sc.php', 'rpci.php', 'add_rpci.php']);
                    // Semi Expendables dropdown should be open for ICS and its subpages
                    $icsPages = ['ics.php', 'add_ics.php', 'edit_ics.php', 'view_ics.php', 'export_ics.php'];
                    $expendablesDropdownActive = in_array($currentPage, array_merge(['semi_expendible.php', 'PC.php', 'PC_semi.php', 'itr.php', 'rspi.php', 'regspi.php', 'rpcsp.php', 'rrsp.php', 'iirusp.php'], $icsPages));
                    $ppeDropdownActive = in_array($currentPage, ['PPE.php', 'add_ppe.php', 'edit_ppe.php', 'view_ppe.php', 'view_ppe_items.php', 'PPE_PC.php', 'PPE_PTR.php', 'add_ptr.php', 'edit_ptr.php', 'view_ptr.php', 'export_ptr.php', 'PPE_PAR.php', 'view_par.php', 'edit_par.php', 'export_par.php', 'RPCPPE.php', 'add_RPCPPE.php', 'view_rpcppe.php', 'export_rpcppe.php', 'PPE_iirup.php']);

                ?>

                <nav id="sidebar-navigation" aria-label="Main navigation">
                    <a href="analytics.php" class="<?= $currentPage == 'analytics.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'analytics.php' ? 'page' : '' ?>"><?= $sidebarIcon('chart') ?><span>Analytics</span></a>

                    <div class="dropdown">
                        <button type="button" class="dropdown-toggle <?= $dropdownActive ? 'active' : '' ?>" aria-expanded="false" aria-controls="office-menu">
                            <?= $sidebarIcon('folder') ?><span>Office Supplies</span><span id="office-stock-warning" class="stock-count-badge stock-warning-badge" hidden aria-label="Office supplies out of stock">!</span><span class="nav-chevron"><?= $sidebarIcon('chevron') ?></span>
                        </button>
                        <div class="dropdown-menu" id="office-menu"><div class="submenu-content">
                            <a href="office_supplies/inventory.php" class="<?= $currentPage == 'inventory.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'inventory.php' ? 'page' : '' ?>"><?= $sidebarIcon('list') ?><span>Supply List</span><span id="office-stock-badge" class="stock-count-badge" hidden role="status" aria-live="polite" aria-atomic="true"></span></a>
                            <a href="office_supplies/ris.php" class="<?= in_array($currentPage, ['ris.php', 'add_ris.php', 'view_ris.php']) ? 'active' : '' ?>" aria-current="<?= in_array($currentPage, ['ris.php', 'add_ris.php', 'view_ris.php']) ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RIS</span></a>
                            <a href="office_supplies/rsmi.php" class="<?= in_array($currentPage, ['rsmi.php', 'add_rsmi.php']) ? 'active' : '' ?>" aria-current="<?= in_array($currentPage, ['rsmi.php', 'add_rsmi.php']) ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RSMI</span></a>
                            <a href="office_supplies/SC.php" class="<?= in_array($currentPage, ['SC.php', 'view_sc.php']) ? 'active' : '' ?>" aria-current="<?= in_array($currentPage, ['SC.php', 'view_sc.php']) ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>SC</span></a>
                            <a href="office_supplies/rpci.php" class="<?= in_array($currentPage, ['rpci.php', 'add_rpci.php']) ? 'active' : '' ?>" aria-current="<?= in_array($currentPage, ['rpci.php', 'add_rpci.php']) ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RPCI</span></a>
                        </div></div>
                    </div>

                    <!-- Semi Expendables Dropdown -->
                    <div class="dropdown">
                        <button type="button" class="dropdown-toggle <?= $expendablesDropdownActive ? 'active' : '' ?>" aria-expanded="false" aria-controls="semi-menu">
                            <?= $sidebarIcon('semi') ?><span>Semi Expendables</span><span class="nav-chevron"><?= $sidebarIcon('chevron') ?></span>
                        </button>
                        <div class="dropdown-menu" id="semi-menu"><div class="submenu-content">
                            <a href="Semi_Expendables/semi_expendible.php" class="<?= $currentPage == 'semi_expendible.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'semi_expendible.php' ? 'page' : '' ?>"><?= $sidebarIcon('list') ?><span>Inventory List</span></a>
                            <a href="Semi_Expendables/PC_semi.php" class="<?= $currentPage == 'PC_semi.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'PC_semi.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>SPC</span></a>
                            <a href="Semi_Expendables/ics.php" class="<?= in_array($currentPage, ['ics.php', 'add_ics.php', 'edit_ics.php', 'view_ics.php', 'export_ics.php']) ? 'active' : '' ?>" aria-current="<?= in_array($currentPage, ['ics.php', 'add_ics.php', 'edit_ics.php', 'view_ics.php', 'export_ics.php']) ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>ICS</span></a>
                            <a href="Semi_Expendables/regspi.php" class="<?= $currentPage == 'regspi.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'regspi.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RegSPI</span></a>
                            <a href="Semi_Expendables/itr.php" class="<?= $currentPage == 'itr.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'itr.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>ITR</span></a>
                            <a href="Semi_Expendables/rspi.php" class="<?= $currentPage == 'rspi.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'rspi.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RSPI</span></a>
                            <a href="Semi_Expendables/rpcsp.php" class="<?= $currentPage == 'rpcsp.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'rpcsp.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RPCSP</span></a>
                            <a href="Semi_Expendables/rrsp.php" class="<?= $currentPage == 'rrsp.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'rrsp.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RRSP</span></a>
                            <a href="Semi_Expendables/iirusp.php" class="<?= $currentPage == 'iirusp.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'iirusp.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>IIRUSP</span></a>
                        </div></div>
                    </div>

                    <!-- PPE Dropdown -->
                    <div class="dropdown">
                        <button type="button" class="dropdown-toggle <?= $ppeDropdownActive ? 'active' : '' ?>" aria-expanded="false" aria-controls="ppe-menu">
                            <?= $sidebarIcon('equipment') ?><span>PPE</span><span class="nav-chevron"><?= $sidebarIcon('chevron') ?></span>
                        </button>
                        <div class="dropdown-menu" id="ppe-menu"><div class="submenu-content">
                            <a href="PPE/PPE.php" class="<?= $ppeInventoryActive ? 'active' : '' ?>" aria-current="<?= $ppeInventoryActive ? 'page' : '' ?>"><?= $sidebarIcon('list') ?><span>Inventory List</span></a>
                            <a href="PPE/PPE_PC.php" class="<?= $ppePcActive ? 'active' : '' ?>" aria-current="<?= $ppePcActive ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>PC</span></a>
                            <a href="PPE/PPE_PTR.php" class="<?= $currentPage == 'PPE_PTR.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'PPE_PTR.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>PTR</span></a>
                            <a href="PPE/PPE_PAR.php" class="<?= $currentPage == 'PPE_PAR.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'PPE_PAR.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>PAR</span></a>
                            <a href="PPE/RPCPPE.php" class="<?= in_array($currentPage, ['RPCPPE.php', 'add_RPCPPE.php', 'view_rpcppe.php', 'export_rpcppe.php'], true) ? 'active' : '' ?>" aria-current="<?= in_array($currentPage, ['RPCPPE.php', 'add_RPCPPE.php', 'view_rpcppe.php', 'export_rpcppe.php'], true) ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>RPCPPE</span></a>
                            <a href="PPE/PPE_iirup.php" class="<?= $currentPage == 'PPE_iirup.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'PPE_iirup.php' ? 'page' : '' ?>"><?= $sidebarIcon('file') ?><span>IIRUP</span></a>
                        </div></div>
                    </div>


                    <a href="archive.php" class="<?= $currentPage == 'archive.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'archive.php' ? 'page' : '' ?>"><?= $sidebarIcon('archive') ?><span>Archive</span></a>

                    <a href="user_settings.php" class="<?= $currentPage == 'user_settings.php' ? 'active' : '' ?>" aria-current="<?= $currentPage == 'user_settings.php' ? 'page' : '' ?>">
                        <?= $sidebarIcon('user') ?><span>User Settings</span>
                    </a>
                </nav>

                    <!-- Logout -->
                    <div class="logout-wrapper">
                        <a href="logout.php" class="logout-btn">
                            <?= $sidebarIcon('logout') ?><span>Logout</span>
                        </a>
                    </div>

        </aside>
        <button type="button" class="sidebar-edge-toggle" aria-label="Open sidebar" aria-expanded="false" aria-controls="sidebar-navigation">
            <?= $sidebarIcon('chevron') ?>
        </button>

    <script src="js/password.js?v=<?= time() ?>"></script>
    <script src="js/input-navigation.js?v=<?= time() ?>" defer></script>
    <script src="js/dropdown-behavior.js?v=<?= time() ?>" defer></script>
    <script src="js/sidebar_script.js?v=<?= time() ?>"></script>
    <script src="js/inventory-badge.js?v=<?= time() ?>" defer></script>
