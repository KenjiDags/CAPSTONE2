<?php $adminSidebarView = $adminSidebarView ?? 'overview'; ?>
<aside class="sidebar sidebar--hover" id="admin-sidebar" aria-label="Admin navigation" data-query-navigation="true">
  <div class="logo-text"><div class="logo"><img src="images/tesda_logo.png" alt="TESDA logo" width="64" height="64"><h3>TESDA Inventory</h3></div></div>
  <nav id="admin-menu" aria-label="Administration">
    <a href="admin.php?view=overview" class="<?= $adminSidebarView === 'overview' ? 'active' : '' ?>" <?= $adminSidebarView === 'overview' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg><span>Overview</span>
    </a>
    <a href="admin.php?view=accounts" class="<?= $adminSidebarView === 'accounts' ? 'active' : '' ?>" <?= $adminSidebarView === 'accounts' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v1"/></svg><span>Accounts</span>
    </a>
    <a href="admin.php?view=recovery" class="<?= $adminSidebarView === 'recovery' ? 'active' : '' ?>" <?= $adminSidebarView === 'recovery' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M8 14h8M12 10l-4 4 4 4"/></svg><span>Recover accounts</span>
    </a>
    <a href="user_settings.php?view=admin" class="<?= $adminSidebarView === 'settings' ? 'active' : '' ?>" <?= $adminSidebarView === 'settings' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6 19v-2a6 6 0 0 1 12 0v2"/></svg><span>User Settings</span>
    </a>
  </nav>
  <div class="logout-wrapper"><a href="logout.php" class="logout-btn"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M9 12h12M17 8l4 4-4 4"/></svg><span>Logout</span></a></div>
</aside>
<button type="button" class="sidebar-edge-toggle" aria-label="Open sidebar" aria-expanded="false" aria-controls="admin-menu"><svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
<script src="js/sidebar_script.js?v=<?= filemtime(__DIR__ . '/js/sidebar_script.js') ?>"></script>
