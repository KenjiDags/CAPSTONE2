<?php
define('ADMIN_PAGE', true);
require 'auth.php';

if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$view = $_GET['view'] ?? ($_POST['return_view'] ?? 'overview');
if (!in_array($view, ['overview', 'accounts', 'recovery'], true)) $view = 'overview';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!hash_equals($_SESSION['admin_csrf'], $_POST['csrf'] ?? '') || !$targetId) {
        $error = 'The request could not be verified.';
    } elseif ((int)$targetId === (int)$_SESSION['user_id']) {
        $error = 'Manage your own account through a different administrator.';
    } else {
        $stmt = $conn->prepare('SELECT role, status FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$target) {
            $error = 'Account not found.';
        } else {
            $sql = null;
            if ($action === 'approve' && $target['status'] === 'pending' && ($_POST['verified_identity'] ?? '') === '1') {
                $sql = "UPDATE users SET status = 'approved', role = 'user' WHERE user_id = ?";
            } elseif ($action === 'reject' && $target['status'] === 'pending') {
                $sql = "UPDATE users SET status = 'rejected' WHERE user_id = ?";
            } elseif ($action === 'disable' && in_array($target['status'], ['active', 'approved'], true)) {
                $sql = "UPDATE users SET status = 'disabled' WHERE user_id = ?";
            } elseif ($action === 'enable' && $target['status'] === 'disabled') {
                $sql = "UPDATE users SET status = 'active' WHERE user_id = ?";
            } elseif ($action === 'promote' && $target['role'] === 'user' && $target['status'] === 'active') {
                $sql = "UPDATE users SET role = 'admin' WHERE user_id = ?";
            } elseif ($action === 'demote' && $target['role'] === 'admin' && $target['status'] === 'active') {
                $sql = "UPDATE users SET role = 'user' WHERE user_id = ?";
            } elseif ($action === 'remove' && $target['status'] !== 'removed') {
                $sql = "UPDATE users SET removed_from_status = status, removed_at = NOW(), status = 'removed' WHERE user_id = ?";
            } elseif ($action === 'restore' && $target['status'] === 'removed') {
                $sql = "UPDATE users SET status = CASE WHEN removed_from_status IN ('pending', 'approved', 'active', 'disabled', 'rejected') THEN removed_from_status ELSE 'disabled' END, removed_from_status = NULL, removed_at = NULL WHERE user_id = ?";
            }
            if ($sql !== null) {
                $update = $conn->prepare($sql);
                $update->bind_param('i', $targetId);
                if ($update->execute()) {
                    $message = $action === 'remove' ? 'Account moved to Removed accounts.' : ($action === 'restore' ? 'Account recovered.' : 'Account updated.');
                } else {
                    $error = 'Could not update the account.';
                }
                $update->close();
            } else {
                $error = 'This action is not available for the account.';
            }
        }
    }
}

$accounts = $conn->query("SELECT user_id, username, email, COALESCE(NULLIF(full_name, ''), NULLIF(user_full_name, '')) AS display_name, role, status, removed_at, removed_from_status FROM users ORDER BY FIELD(status, 'pending', 'approved', 'active', 'disabled', 'rejected', 'removed'), user_id DESC")->fetch_all(MYSQLI_ASSOC);
$counts = ['pending' => 0, 'active' => 0, 'disabled' => 0, 'removed' => 0];
foreach ($accounts as $account) if (isset($counts[$account['status']])) $counts[$account['status']]++;
$visibleAccounts = array_values(array_filter($accounts, static function ($account) use ($view) {
    if ($view === 'recovery') return $account['status'] === 'removed';
    if ($view === 'overview') return $account['status'] === 'pending';
    return $account['status'] !== 'removed';
}));

function admin_action(int $id, string $action, string $label, string $csrf, string $view, string $class = ''): void
{
    echo '<form method="post" id="account-action-' . htmlspecialchars($action) . '-' . $id . '" class="action-form action-form--' . htmlspecialchars($action) . '">';
    echo '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf) . '">';
    echo '<input type="hidden" name="user_id" value="' . $id . '">';
    echo '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">';
    echo '<input type="hidden" name="return_view" value="' . htmlspecialchars($view) . '">';
    $confirmation = $action === 'remove' ? ' onclick="return confirm(\'Move this account to Removed accounts? You can recover it later.\')"' : '';
    echo '<button class="' . htmlspecialchars($class) . '" type="submit"' . $confirmation . '>' . htmlspecialchars($label) . '</button>';
    echo '</form>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Account Administration - TESDA Inventory</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <link rel="stylesheet" href="css/hover-sidebar.css?v=<?= time() ?>">
  <script>
    try {
      const sidebarStateKey = 'tesda:admin-sidebar:' + new URL('.', document.baseURI).pathname;
      if (JSON.parse(sessionStorage.getItem(sidebarStateKey) || 'null')?.open) {
        document.documentElement.classList.add('sidebar-restore-open');
      }
    } catch (_) {}
  </script>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #f2f5fa; color: #1e293b; font-family: 'Century Gothic', Arial, sans-serif; }
    #admin-sidebar { cursor: pointer; }
    #admin-sidebar:focus-visible { outline: 2px solid #1d4ed8; outline-offset: -2px; }
    .admin-main { min-width: 0; background: #f2f5fa; }
    .admin-content { max-width: 1180px; margin: 0 auto; padding: 32px 24px 60px; }
    .admin-header { background: #123d80; color: white; }
    .admin-header-inner { max-width: 1180px; margin: 0 auto; padding: 20px 24px; }
    .admin-header-title { margin: 0; color: white; font-size: 20px; font-weight: 700; line-height: 1.2; }
    .admin-content h1 { margin: 0 0 8px; color: #123d80; font-size: 32px; text-align: left; text-shadow: none; } .intro { margin: 0 0 28px; color: #64748b; }
    .cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .card, .panel { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 3px 12px #1e293b0a; }
    .card { padding: 20px; } .card span { color: #64748b; } .card strong { display: block; font-size: 30px; margin-top: 8px; color: #123d80; }
    .panel { overflow: hidden; } .panel h2 { padding: 20px; margin: 0; font-size: 19px; text-align: left; text-shadow: none; }
    .table-wrap { max-width: 100%; overflow-x: auto; } table { width: 100%; table-layout: fixed; border-collapse: collapse; } th, td { text-align: left; padding: 14px 16px; border-top: 1px solid #e2e8f0; vertical-align: middle; overflow-wrap: anywhere; }
    th:first-child { width: 34%; } th:nth-child(2), th:nth-child(3) { width: 14%; } th:last-child { width: 38%; }
    .account-actions-cell { min-width: 0; }
    th { background: #f8fafc; color: #475569; font-size: 13px; } td small { display: block; color: #64748b; margin-top: 4px; }
    .person-heading { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 12px; }
    .person-email { color: #64748b; font-size: 13px; font-weight: 400; overflow-wrap: anywhere; }
    .person-username { display: block; margin-top: 4px; color: #64748b; font-size: 13px; }
    .badge { display: inline-block; border-radius: 20px; padding: 4px 9px; background: #e8eef8; color: #174b8f; font-size: 12px; text-transform: capitalize; }
    .badge.pending { background: #fff3d6; color: #92400e; } .badge.disabled, .badge.rejected { background: #fee2e2; color: #991b1b; } .badge.removed { background: #e2e8f0; color: #475569; }
    .account-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; }
    .action-form { margin: 0; }
    .verify { display: inline-flex; align-items: center; gap: 7px; margin-top: 8px; color: #475569; font-size: 12px; line-height: 1.3; cursor: pointer; }
    .verify input { width: 16px; height: 16px; margin: 0; accent-color: #1554a6; }
    .account-actions button { min-height: 36px; border: 1px solid #bad0ed; background: #f3f7ff; color: #16468a; border-radius: 7px; padding: 7px 14px; cursor: pointer; font: inherit; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .account-actions--pending .action-form { flex: 1 1 90px; }
    .account-actions--pending button { width: 100%; }
    .account-actions button:hover { background: #e4eeff; }
    .account-actions button:focus-visible { outline: 2px solid #1554a6; outline-offset: 2px; }
    .account-actions button.primary { border-color: #1554a6; background: #1554a6; color: white; }
    .account-actions button.primary:hover { background: #123d80; }
    .account-actions button.danger { border-color: #fecaca; background: #fff4f4; color: #9f1239; }
    .account-actions button.danger:hover { background: #fee2e2; }
    .account-actions button.remove { border-color: #e2e8f0; background: transparent; color: #9f1239; }
    .account-actions button.remove:hover { border-color: #fecaca; background: #fff4f4; }
    .notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; background: #e9f8ef; color: #166534; } .notice.error { background: #fee2e2; color: #991b1b; }
    .admin-shortcuts { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 28px; }
    .admin-shortcuts a { display: inline-flex; align-items: center; min-height: 40px; padding: 9px 14px; border-radius: 7px; background: #e8eef8; color: #174b8f; font-size: 14px; font-weight: 700; text-decoration: none; }
    .admin-shortcuts a:hover, .admin-shortcuts a:focus-visible { background: #dbeafe; }
    .empty-state { padding: 24px; border-top: 1px solid #e2e8f0; color: #64748b; }
    body.dark-mode, body.dark-mode .admin-main { background: #0f172a; color: #e2e8f0; }
    body.dark-mode .card, body.dark-mode .panel { background: #1e293b; border-color: #334155; }
    body.dark-mode .admin-content h1, body.dark-mode .panel h2, body.dark-mode .card strong { color: #bfdbfe; }
    body.dark-mode .intro, body.dark-mode .card span, body.dark-mode td small, body.dark-mode .empty-state { color: #cbd5e1; }
    body.dark-mode .person-email, body.dark-mode .person-username { color: #cbd5e1; }
    body.dark-mode th { background: #273449; color: #e2e8f0; }
    body.dark-mode th, body.dark-mode td, body.dark-mode .empty-state { border-color: #334155; }
    body.dark-mode .account-actions button { background: #273449; border-color: #47617e; color: #dbeafe; }
    body.dark-mode .account-actions button:hover { background: #334861; }
    body.dark-mode .account-actions button.primary { background: #2563b8; border-color: #2563b8; color: white; }
    body.dark-mode .account-actions button.danger { background: #4c2633; border-color: #8b4356; color: #fecdd3; }
    body.dark-mode .verify { color: #cbd5e1; }
    body.dark-mode .account-actions button.remove { background: transparent; border-color: #475569; color: #fecdd3; }
    @media (max-width: 900px) { .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 1100px) {
      table, tbody { display: block; }
      thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
      tbody { padding: 12px; }
      tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; padding: 16px; border: 1px solid #e2e8f0; border-radius: 10px; }
      tr + tr { margin-top: 12px; }
      td { display: block; min-width: 0; padding: 0; border: 0; }
      td::before { content: attr(data-label); display: block; margin-bottom: 5px; color: #64748b; font-size: 12px; font-weight: 700; }
      td:first-child, .account-actions-cell { grid-column: 1 / -1; }
      .account-actions { max-width: 420px; }
      body.dark-mode tr { border-color: #334155; }
      body.dark-mode td::before { color: #cbd5e1; }
    }
    @media (max-width: 640px) {
      .admin-content { padding: 24px 14px 40px; }
      .admin-header-inner { padding-inline: 14px; }
      .admin-content h1 { font-size: 26px; }
      .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
      .card { padding: 14px; }
      .card strong { font-size: 25px; }
      tbody { padding: 8px; }
      tr { padding: 12px; gap: 10px; }
    }
    @media (max-width: 420px) { .cards, tr { grid-template-columns: 1fr; } td:first-child, .account-actions-cell { grid-column: auto; } }
  </style>
</head>
<body>
<aside class="sidebar sidebar--hover" id="admin-sidebar" aria-label="Admin navigation" tabindex="0">
  <div class="logo-text"><div class="logo"><img src="images/tesda_logo.png" alt="TESDA logo" width="64" height="64"><h3>TESDA Inventory</h3></div></div>
  <nav id="admin-menu" aria-label="Administration">
    <a href="admin.php?view=overview" class="<?= $view === 'overview' ? 'active' : '' ?>" <?= $view === 'overview' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg><span>Overview</span>
    </a>
    <a href="admin.php?view=accounts" class="<?= $view === 'accounts' ? 'active' : '' ?>" <?= $view === 'accounts' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v1"/></svg><span>Accounts</span>
    </a>
    <a href="admin.php?view=recovery" class="<?= $view === 'recovery' ? 'active' : '' ?>" <?= $view === 'recovery' ? 'aria-current="page"' : '' ?>>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M8 14h8M12 10l-4 4 4 4"/></svg><span>Recover accounts</span>
    </a>
  </nav>
  <div class="logout-wrapper"><a href="logout.php" class="logout-btn"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M9 12h12M17 8l4 4-4 4"/></svg><span>Logout</span></a></div>
</aside>
<main class="admin-main">
<header class="admin-header">
  <div class="admin-header-inner"><p class="admin-header-title">Administrator</p></div>
</header>
<div class="admin-content">
  <h1><?= $view === 'overview' ? 'Administration overview' : ($view === 'accounts' ? 'Manage accounts' : 'Recover accounts') ?></h1>
  <p class="intro"><?= $view === 'recovery' ? 'Restore an account removed after this feature was added. Earlier permanent deletions require a database backup.' : 'Review registration requests and control who can use the inventory system. Confirm each applicant against institutional records before approving.' ?></p>
  <?php if ($message): ?><div class="notice" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($view === 'overview'): ?>
    <div class="cards">
      <div class="card"><span>Pending requests</span><strong><?= $counts['pending'] ?></strong></div>
      <div class="card"><span>Active accounts</span><strong><?= $counts['active'] ?></strong></div>
      <div class="card"><span>Disabled accounts</span><strong><?= $counts['disabled'] ?></strong></div>
      <div class="card"><span>Removed accounts</span><strong><?= $counts['removed'] ?></strong></div>
    </div>
    <div class="admin-shortcuts"><a href="admin.php?view=accounts">Manage accounts</a><a href="admin.php?view=recovery">Recover accounts</a></div>
  <?php endif; ?>
  <section class="panel">
    <h2><?= $view === 'overview' ? 'Pending requests' : ($view === 'accounts' ? 'Accounts' : 'Removed accounts') ?></h2>
    <?php if (!$visibleAccounts): ?>
      <p class="empty-state"><?= $view === 'overview' ? 'No pending requests.' : ($view === 'accounts' ? 'No accounts to display.' : 'No removed accounts to recover.') ?></p>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($visibleAccounts as $account): ?>
        <tr>
          <td data-label="Person"><div class="person-heading"><strong><?= htmlspecialchars($account['display_name'] ?: 'Full name not provided') ?></strong><?php if ($account['email']): ?><span class="person-email"><?= htmlspecialchars($account['email']) ?></span><?php endif; ?></div>
            <span class="person-username">Username: <?= htmlspecialchars($account['username']) ?></span>
            <?php if ($account['status'] === 'pending' && (int)$account['user_id'] !== (int)$_SESSION['user_id']): ?>
              <label class="verify"><input type="checkbox" name="verified_identity" value="1" form="account-action-approve-<?= (int)$account['user_id'] ?>" required> Identity verified</label>
            <?php endif; ?>
          </td>
          <td data-label="Role"><span class="badge"><?= htmlspecialchars($account['role']) ?></span></td>
          <td data-label="Status"><span class="badge <?= htmlspecialchars($account['status']) ?>"><?= htmlspecialchars($account['status']) ?></span>
            <?php if ($account['status'] === 'removed'): ?><small>Previously <?= htmlspecialchars($account['removed_from_status'] ?: 'disabled') ?><?= $account['removed_at'] ? ' · ' . htmlspecialchars($account['removed_at']) : '' ?></small><?php endif; ?>
          </td>
          <td class="account-actions-cell" data-label="Actions"><div class="account-actions<?= $account['status'] === 'pending' ? ' account-actions--pending' : '' ?>">
          <?php if ((int)$account['user_id'] !== (int)$_SESSION['user_id']): ?>
            <?php if ($account['status'] === 'removed'): ?>
              <?php admin_action((int)$account['user_id'], 'restore', 'Recover', $_SESSION['admin_csrf'], $view, 'primary'); ?>
            <?php elseif ($account['status'] === 'pending'): ?>
              <?php admin_action((int)$account['user_id'], 'approve', 'Approve', $_SESSION['admin_csrf'], $view, 'primary'); ?>
              <?php admin_action((int)$account['user_id'], 'reject', 'Reject', $_SESSION['admin_csrf'], $view, 'danger'); ?>
            <?php elseif (in_array($account['status'], ['active', 'approved'], true)): ?>
              <?php admin_action((int)$account['user_id'], 'disable', 'Disable', $_SESSION['admin_csrf'], $view, 'danger'); ?>
              <?php if ($account['status'] === 'active'): ?>
                <?php admin_action((int)$account['user_id'], $account['role'] === 'admin' ? 'demote' : 'promote', $account['role'] === 'admin' ? 'Make user' : 'Make admin', $_SESSION['admin_csrf'], $view); ?>
              <?php endif; ?>
            <?php elseif ($account['status'] === 'disabled'): ?>
              <?php admin_action((int)$account['user_id'], 'enable', 'Enable', $_SESSION['admin_csrf'], $view); ?>
            <?php endif; ?>
            <?php if (!in_array($account['status'], ['removed', 'pending'], true)): ?><?php admin_action((int)$account['user_id'], 'remove', 'Remove', $_SESSION['admin_csrf'], $view, 'remove'); ?><?php endif; ?>
          <?php else: ?>Your account<?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
</div>
</main>
<script>
(() => {
  const sidebar = document.getElementById('admin-sidebar');
  if (!sidebar) return;
  const storageKey = 'tesda:admin-sidebar:' + new URL('.', document.baseURI).pathname;
  const setOpen = open => {
    sidebar.classList.toggle('is-open', open);
    try { sessionStorage.setItem(storageKey, JSON.stringify({ open })); } catch (_) {}
  };
  sidebar.classList.add('is-restoring');
  let savedOpen = false;
  try { savedOpen = JSON.parse(sessionStorage.getItem(storageKey) || 'null')?.open === true; } catch (_) {}
  setOpen(savedOpen);
  document.documentElement.classList.remove('sidebar-restore-open');
  void sidebar.offsetWidth;
  sidebar.classList.remove('is-restoring');

  sidebar.addEventListener('click', event => {
    event.stopPropagation();
    if (!sidebar.classList.contains('is-open')) {
      if (event.target.closest('a')) event.preventDefault();
      setOpen(true);
    }
  });
  sidebar.addEventListener('keydown', event => {
    if (event.key === 'Escape') setOpen(false);
    if (event.target === sidebar && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      setOpen(true);
    }
  });
  document.addEventListener('click', event => {
    if (!sidebar.contains(event.target)) setOpen(false);
  });
  try {
    if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
  } catch (_) {}
})();
</script>
</body>
</html>
