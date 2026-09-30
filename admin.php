<?php
define('ADMIN_PAGE', true);
require 'auth.php';

if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
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
            }
            if ($sql !== null) {
                $update = $conn->prepare($sql);
                $update->bind_param('i', $targetId);
                if ($update->execute()) {
                    $message = 'Account updated.';
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

$accounts = $conn->query("SELECT user_id, username, email, COALESCE(NULLIF(full_name, ''), NULLIF(user_full_name, ''), username) AS display_name, role, status FROM users ORDER BY FIELD(status, 'pending', 'approved', 'active', 'disabled', 'rejected'), user_id DESC")->fetch_all(MYSQLI_ASSOC);
$counts = ['pending' => 0, 'active' => 0, 'disabled' => 0];
foreach ($accounts as $account) if (isset($counts[$account['status']])) $counts[$account['status']]++;

function admin_action(int $id, string $action, string $label, string $csrf, string $class = ''): void
{
    echo '<form method="post" class="action-form">';
    echo '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf) . '">';
    echo '<input type="hidden" name="user_id" value="' . $id . '">';
    echo '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">';
    if ($action === 'approve') echo '<label class="verify"><input type="checkbox" name="verified_identity" value="1" required> Identity verified</label>';
    echo '<button class="' . htmlspecialchars($class) . '" type="submit">' . htmlspecialchars($label) . '</button>';
    echo '</form>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Account Administration - TESDA Inventory</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #f2f5fa; color: #1e293b; font-family: 'Century Gothic', Arial, sans-serif; }
    .admin-header { background: #123d80; color: white; }
    .admin-header-inner { max-width: 1180px; margin: 0 auto; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
    .admin-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .admin-brand strong { font-size: 19px; line-height: 1.2; }
    .admin-brand-label { border: 1px solid #ffffff66; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .admin-session { display: flex; align-items: center; gap: 16px; min-width: 0; }
    .admin-username { color: #dbeafe; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .admin-logout { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 8px 14px; border: 1px solid #ffffff99; border-radius: 8px; color: white; font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap; transition: background .2s, border-color .2s; }
    .admin-logout:hover, .admin-logout:focus-visible { background: #ffffff22; border-color: white; }
    .admin-logout:focus-visible { outline: 2px solid white; outline-offset: 3px; }
    .admin-logout svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    main { max-width: 1180px; margin: 0 auto; padding: 32px 24px 60px; }
    h1 { margin: 0 0 8px; color: #123d80; } .intro { margin: 0 0 28px; color: #64748b; }
    .cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 28px; }
    .card, .panel { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 3px 12px #1e293b0a; }
    .card { padding: 20px; } .card span { color: #64748b; } .card strong { display: block; font-size: 30px; margin-top: 8px; color: #123d80; }
    .panel { overflow: hidden; } .panel h2 { padding: 20px; margin: 0; font-size: 19px; }
    .table-wrap { overflow-x: auto; } table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 14px 18px; border-top: 1px solid #e2e8f0; vertical-align: middle; }
    th { background: #f8fafc; color: #475569; font-size: 13px; } td small { display: block; color: #64748b; margin-top: 4px; }
    .badge { display: inline-block; border-radius: 20px; padding: 4px 9px; background: #e8eef8; color: #174b8f; font-size: 12px; text-transform: capitalize; }
    .badge.pending { background: #fff3d6; color: #92400e; } .badge.disabled, .badge.rejected { background: #fee2e2; color: #991b1b; }
    .actions { display: flex; flex-wrap: wrap; gap: 6px; } .action-form { margin: 0; }
    .verify { display: block; font-size: 12px; margin-bottom: 5px; white-space: nowrap; }
    button { border: 1px solid #bad0ed; background: #f3f7ff; color: #16468a; border-radius: 6px; padding: 7px 10px; cursor: pointer; font: inherit; font-size: 12px; }
    button.primary { border-color: #1554a6; background: #1554a6; color: white; } button.danger { border-color: #fecaca; background: #fff4f4; color: #9f1239; }
    .notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; background: #e9f8ef; color: #166534; } .notice.error { background: #fee2e2; color: #991b1b; }
    @media (max-width: 640px) { .cards { grid-template-columns: 1fr; } .admin-header-inner { flex-wrap: wrap; gap: 12px; } .admin-session { width: 100%; justify-content: space-between; } }
  </style>
</head>
<body>
<header class="admin-header">
  <div class="admin-header-inner">
    <div class="admin-brand"><strong>TESDA Inventory</strong><span class="admin-brand-label">Administration</span></div>
    <div class="admin-session">
      <span class="admin-username">Signed in as <?= htmlspecialchars($_SESSION['username']) ?></span>
      <a class="admin-logout" href="logout.php">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Log out
      </a>
    </div>
  </div>
</header>
<main>
  <h1>Account access</h1>
  <p class="intro">Review registration requests and control who can use the inventory system. Confirm each applicant against institutional records before approving.</p>
  <?php if ($message): ?><div class="notice" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="cards">
    <div class="card"><span>Pending requests</span><strong><?= $counts['pending'] ?></strong></div>
    <div class="card"><span>Active accounts</span><strong><?= $counts['active'] ?></strong></div>
    <div class="card"><span>Disabled accounts</span><strong><?= $counts['disabled'] ?></strong></div>
  </div>
  <section class="panel">
    <h2>Accounts</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $account): ?>
        <tr>
          <td><strong><?= htmlspecialchars($account['display_name']) ?></strong><small><?= htmlspecialchars($account['username']) ?><?= $account['email'] ? ' · ' . htmlspecialchars($account['email']) : '' ?></small></td>
          <td><span class="badge"><?= htmlspecialchars($account['role']) ?></span></td>
          <td><span class="badge <?= htmlspecialchars($account['status']) ?>"><?= htmlspecialchars($account['status']) ?></span></td>
          <td><div class="actions">
          <?php if ((int)$account['user_id'] !== (int)$_SESSION['user_id']): ?>
            <?php if ($account['status'] === 'pending'): ?>
              <?php admin_action((int)$account['user_id'], 'approve', 'Approve', $_SESSION['admin_csrf'], 'primary'); ?>
              <?php admin_action((int)$account['user_id'], 'reject', 'Reject', $_SESSION['admin_csrf'], 'danger'); ?>
            <?php elseif (in_array($account['status'], ['active', 'approved'], true)): ?>
              <?php admin_action((int)$account['user_id'], 'disable', 'Disable', $_SESSION['admin_csrf'], 'danger'); ?>
              <?php if ($account['status'] === 'active'): ?>
                <?php admin_action((int)$account['user_id'], $account['role'] === 'admin' ? 'demote' : 'promote', $account['role'] === 'admin' ? 'Make user' : 'Make admin', $_SESSION['admin_csrf']); ?>
              <?php endif; ?>
            <?php elseif ($account['status'] === 'disabled'): ?>
              <?php admin_action((int)$account['user_id'], 'enable', 'Enable', $_SESSION['admin_csrf']); ?>
            <?php endif; ?>
          <?php else: ?>Your account<?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</main>
</body>
</html>
