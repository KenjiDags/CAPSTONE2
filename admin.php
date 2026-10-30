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
            if ($action === 'approve' && $target['status'] === 'pending' && ($_POST['reviewed_account'] ?? '') === '1') {
                $sql = "UPDATE users SET status = 'approved', role = 'user' WHERE user_id = ?";
            } elseif ($action === 'reject' && $target['status'] === 'pending') {
                $sql = "UPDATE users SET status = 'rejected' WHERE user_id = ?";
            } elseif ($action === 'disable' && in_array($target['status'], ['active', 'approved'], true)) {
                $sql = "UPDATE users SET status = 'disabled' WHERE user_id = ?";
            } elseif ($action === 'enable' && $target['status'] === 'disabled') {
                $sql = "UPDATE users SET status = 'active' WHERE user_id = ?";
            } elseif ($action === 'demote' && $target['role'] === 'admin' && $target['status'] === 'active') {
                $sql = "UPDATE users SET role = 'user' WHERE user_id = ?";
            } elseif ($action === 'remove' && $target['status'] !== 'removed') {
                $sql = "UPDATE users SET removed_from_status = status, removed_at = NOW(), status = 'removed' WHERE user_id = ?";
            } elseif ($action === 'restore' && $target['status'] === 'removed') {
                $sql = "UPDATE users SET status = 'pending', removed_from_status = NULL, removed_at = NULL WHERE user_id = ?";
            }
            if ($sql !== null) {
                $update = $conn->prepare($sql);
                $update->bind_param('i', $targetId);
                if ($update->execute()) {
                    $message = $action === 'remove' ? 'Account moved to Removed accounts.' : ($action === 'restore' ? 'Account restored to Pending for administrator review.' : 'Account updated.');
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

$accounts = $conn->query("SELECT user_id, username, email, user_position, COALESCE(NULLIF(full_name, ''), NULLIF(user_full_name, '')) AS display_name, role, status, removed_at, removed_from_status FROM users ORDER BY FIELD(status, 'pending', 'approved', 'active', 'disabled', 'rejected', 'removed'), user_id DESC")->fetch_all(MYSQLI_ASSOC);
$counts = ['pending' => 0, 'active' => 0, 'disabled' => 0, 'removed' => 0];
foreach ($accounts as $account) if (isset($counts[$account['status']])) $counts[$account['status']]++;
$visibleAccounts = array_values(array_filter($accounts, static function ($account) use ($view) {
    if ($view === 'accounts' && $account['role'] === 'admin') return false;
    if ($view === 'recovery') return $account['status'] === 'removed';
    if ($view === 'overview') return $account['status'] === 'pending';
    return $account['status'] !== 'removed';
}));

function admin_review_button(array $account): void
{
    $details = ['id' => (int)$account['user_id'], 'name' => $account['display_name'] ?: 'Full name not provided', 'username' => $account['username'], 'email' => $account['email'] ?: 'Not provided', 'position' => $account['user_position'] ?: 'Not provided', 'role' => $account['role'], 'status' => $account['status']];
    echo '<button type="button" class="primary review-approve-trigger" data-account="' . htmlspecialchars(json_encode($details, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') . '">Review &amp; Approve</button>';
}
function admin_action(int $id, string $action, string $label, string $csrf, string $view, string $class = ''): void
{
    echo '<form method="post" id="account-action-' . htmlspecialchars($action) . '-' . $id . '" class="action-form action-form--' . htmlspecialchars($action) . '">';
    echo '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf) . '">';
    echo '<input type="hidden" name="user_id" value="' . $id . '">';
    echo '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">';
    echo '<input type="hidden" name="return_view" value="' . htmlspecialchars($view) . '">';
    $confirmation = $action === 'remove' ? ' onclick="return confirm(\'Move this account to Removed accounts? You can recover it later.\')"' : '';
    $icon = $action === 'enable' ? '<svg class="action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>' : '';
    echo '<button class="' . htmlspecialchars($class) . '" type="submit"' . $confirmation . '>' . $icon . htmlspecialchars($label) . '</button>';
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

  <script>
    try {
      const sidebarStateKey = 'tesda:sidebar:' + new URL('.', document.baseURI).pathname;
      if (JSON.parse(sessionStorage.getItem(sidebarStateKey) || 'null')?.open) {
        document.documentElement.classList.add('sidebar-restore-open');
      }
    } catch (_) {}
  </script>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #f2f5fa; color: #1e293b; font-family: 'Century Gothic', Arial, sans-serif; }
    .admin-main { min-width: 0; min-height: 100vh; min-height: 100dvh; background: #f2f5fa; }
    .admin-content { max-width: 1440px; margin: 0 auto; padding: 32px 24px 60px; }
    .admin-header { background: #123d80; color: white; }
    .admin-header-inner { width: 100%; margin: 0; padding: 20px 24px; }
    .admin-header-title { margin: 0; color: white; font-size: 20px; font-weight: 700; line-height: 1.2; }
    .admin-content h1 { margin: 0 0 8px; color: #123d80; font-size: 32px; text-align: left; text-shadow: none; } .intro { margin: 0 0 28px; color: #64748b; }
    .cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .card, .panel { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 3px 12px #1e293b0a; }
    .card { padding: 20px; } .card span { color: #64748b; } .card strong { display: block; font-size: 30px; margin-top: 8px; color: #123d80; }
    .admin-content .card-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .admin-content .card-icon { width: 24px; height: 24px; flex: 0 0 24px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
    .admin-content .card--pending .card-icon { color: #b78a36; }
    .admin-content .card--active .card-icon { color: #4b927c; }
    .admin-content .card--disabled .card-icon { color: #bd7179; }
    .admin-content .card--removed .card-icon { color: #8491a5; }
    body.dark-mode .card--pending .card-icon { color: #d6b16c; }
    body.dark-mode .card--active .card-icon { color: #7dbba4; }
    body.dark-mode .card--disabled .card-icon { color: #d99aa1; }
    body.dark-mode .card--removed .card-icon { color: #a5b1c3; }
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
    .admin-header { background: #1c2a40; border-bottom: 1px solid #334155; }
    .admin-header-inner { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding-block: 14px; }
    .admin-header-title { font-size: 16px; }
    .admin-header-subtitle { margin: 5px 0 0; color: #b8c5d8; font-size: 13px; }
    .admin-role { display: inline-flex; align-items: center; gap: 9px; font-size: 13px; white-space: nowrap; }
    .admin-role svg { width: 17px; height: 17px; color: #60a5fa; }
    .pending-panel { padding: 20px; }
    .pending-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .pending-panel .pending-heading h2 { padding: 0; font-size: 16px; }
    .pending-request { padding: 16px; border: 1px solid #dbe2ec; border-radius: 9px; }
    .pending-request + .pending-request { margin-top: 12px; }
    .request-top { display: flex; align-items: flex-start; gap: 12px; }
    .request-avatar { display: grid; place-items: center; width: 40px; height: 40px; flex: 0 0 40px; border-radius: 50%; background: #e8eef8; color: #386495; }
    .request-avatar svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; }
    .request-person { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .request-person .person-email { display: block; margin-top: 4px; }
    .request-footer { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 16px; }
    .request-footer .account-actions { margin-left: auto; width: auto; }
    .request-footer .account-actions button { border-radius: 20px; padding: 6px 16px; font-size: 13px; }
    .request-verification { margin-top: 12px; color: #64748b; font-size: 13px; }
    .request-verification .verify { display: flex; margin: 12px 0; }
    .request-note { margin: 12px 0 0; color: #64748b; font-size: 13px; }
    .account-actions button:disabled { opacity: .45; cursor: not-allowed; }
    body.dark-mode .pending-request { border-color: #3b4d65; }
    body.dark-mode .request-avatar { background: #334e76; color: #dbeafe; }
    body.dark-mode .request-note, body.dark-mode .request-verification { color: #a8b8ce; }
    @media (max-width: 480px) { .admin-header-inner { flex-wrap: wrap; gap: 10px; } .pending-panel { padding: 14px; } .request-footer .account-actions { margin-left: 0; } }
    body.dark-mode .badge.role-badge { background: #475569; color: #fff; }
    .accounts-toolbar { display: flex; align-items: end; flex-wrap: wrap; gap: 16px; margin: 24px 0; }
    .accounts-toolbar label { display: grid; gap: 6px; font-size: 12px; color: #64748b; }
    .accounts-toolbar .account-search { flex: 1 1 280px; }
    .accounts-toolbar input, .accounts-toolbar select { width: 100%; min-height: 42px; margin: 0; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; color: #1e293b; font: inherit; font-size: 14px; }
    .accounts-toolbar :is(input, select):focus-visible { outline: 2px solid #60a5fa; outline-offset: 2px; }
    #account-results { margin: 0 0 10px; font-size: 12px; color: #64748b; }
    .account-section { margin-bottom: 20px; padding: 0 20px; }
    .account-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 0; }
    .account-section .account-section-heading h2 { padding: 0; font-size: 16px; }
    .account-count { font-size: 12px; color: #64748b; }
    .account-row { padding: 22px 0; border-top: 1px solid #e2e8f0; }
    .account-row-top, .account-row-bottom { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
    .account-row-bottom { margin-top: 16px; }
    .account-row-bottom .account-actions { margin-left: auto; width: auto; max-width: none; }
    .account-row .account-actions button { border-radius: 20px; padding: 6px 16px; background: transparent; }
    .account-row .account-actions button.primary { background: #1554a6; }
    .account-row .verify { margin: 0; }
    .account-group-empty { margin: 0; padding: 18px 0; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    .badge.active, .badge.approved { background: #dcfce7; color: #15803d; }
    body.dark-mode .accounts-toolbar :is(input, select) { background: #1e293b; border-color: #475569; color: #e2e8f0; }
    body.dark-mode :is(.accounts-toolbar label, .account-count, #account-results, .account-group-empty) { color: #a8b8ce; }
    body.dark-mode :is(.account-row, .account-group-empty) { border-color: #3b4d65; }
    body.dark-mode .badge.active, body.dark-mode .badge.approved { background: #173f35; color: #4ade80; }
    body.dark-mode .badge.pending { background: #493729; color: #fb923c; }
    body.dark-mode .badge.disabled, body.dark-mode .badge.rejected { background: #482e3b; color: #fda4af; }
    .account-section[hidden], .account-row[hidden], .account-group-empty[hidden] { display: none; }
    @media (max-width: 640px) { .account-section { padding-inline: 14px; } .account-row-bottom .account-actions { width: 100%; margin-left: 0; } }
    .account-search-field { position: relative; display: block; }
    .account-search-field > svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; pointer-events: none; }
    .accounts-toolbar .account-search-field input { padding-left: 40px; }
    .account-actions .action-icon { width: 16px; height: 16px; flex-shrink: 0; vertical-align: middle; margin-right: 6px; }
    .recovery-panel { padding: 0 20px 12px; }
    .recovery-panel h2 { padding: 0; font-size: 16px; }
    .recovery-table { margin: 0; box-shadow: none; }
    .recovery-table th:first-child { width: 30%; }
    .recovery-table th:nth-child(2) { width: 15%; }
    .recovery-table th:nth-child(3) { width: 30%; }
    .recovery-table th:last-child { width: 25%; }
    .recovery-table thead th { background: transparent !important; color: #64748b !important; font-size: 11px; text-transform: uppercase; border: 0; }
    .recovery-table td { background: transparent; }
    .recovery-table .person-email { display: block; margin-top: 4px; }
    .restore-trigger { display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border: 0; border-radius: 20px; background: #e8eef8; color: #1e293b; font: inherit; font-size: 13px; cursor: pointer; }
    .restore-trigger svg, .restore-heading svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
    #restore-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; overflow-y: auto; width: min(640px, calc(100% - 32px)); max-height: calc(100dvh - 32px); padding: 24px; border: 1px solid #cbd5e1; border-radius: 16px; background: #fff; color: #1e293b; box-shadow: 0 20px 70px #0005; }
    #restore-dialog::backdrop { background: #020617aa; }
    #restore-form { margin: 0; }
    .restore-heading { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
    .restore-heading > svg { color: #3b82f6; margin-top: 4px; }
    #restore-title { margin: 0; padding: 0; font-size: 19px; text-align: left; text-shadow: none; }
    #restore-description { margin: 4px 0 0; font-size: 14px; color: #64748b; }
    .restore-details { margin: 24px 0; }
    .restore-details > div { display: flex; justify-content: space-between; gap: 20px; margin: 14px 0; }
    .restore-details dt { color: #64748b; font-size: 14px; }
    .restore-details dd { margin: 0; font-weight: 700; overflow-wrap: anywhere; }
    #restore-info { font-size: 14px; color: #64748b; line-height: 1.6; }
    .restore-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; }
    .restore-modal-actions button { padding: 9px 16px; border: 1px solid #cbd5e1; border-radius: 22px; background: transparent; color: inherit; font: inherit; font-size: 13px; cursor: pointer; }
    .restore-modal-actions .confirm-restore { background: #1554a6; border-color: #1554a6; color: white; }
    body.dark-mode #restore-dialog { background: #1e293b; border-color: #475569; color: #e2e8f0; }
    body.dark-mode :is(#restore-description, .restore-details dt, #restore-info), body.dark-mode .recovery-table thead th { color: #b8c5d8 !important; }
    body.dark-mode .restore-trigger { background: #e2e8f0; color: #172033; }
    body.dark-mode .restore-modal-actions button { border-color: #475569; }
    #restore-name { overflow-wrap: anywhere; }
    .recovery-empty { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 40px 20px; border-top: 1px solid #e2e8f0; text-align: center; }
    .recovery-empty > svg { width: 28px; height: 28px; color: #64748b; }
    .recovery-empty strong { font-size: 16px; }
    .recovery-empty p { margin: 0; color: #64748b; font-size: 14px; }
    .recovery-empty[hidden], .recovery-table tr[hidden], .recovery-panel .table-wrap[hidden] { display: none; }
    body.dark-mode .recovery-empty { border-color: #334155; }
    body.dark-mode .recovery-empty :is(svg, p) { color: #b8c5d8; }
    #recovery-results { color: #64748b; font-size: 12px; }
    body.dark-mode #recovery-results { color: #b8c5d8; }
    #approve-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; width: min(640px, calc(100% - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; padding: 28px; border: 1px solid #cbd5e1; border-radius: 16px; background: #fff; color: #1e293b; box-shadow: 0 20px 70px #0005; }
    #approve-dialog::backdrop { background: #020617aa; }
    #approve-form { margin: 0; }
    #approve-title { padding: 0 32px 0 0; margin: 0; font-size: 19px; text-align: left; text-shadow: none; }
    #approve-description { margin: 10px 0 24px; color: #64748b; font-size: 14px; }
    .approve-close { position: absolute; top: 12px; right: 12px; display: grid; place-items: center; width: 32px; height: 32px; padding: 6px; border: 0; border-radius: 50%; background: transparent; color: inherit; cursor: pointer; }
    .approve-close svg { width: 20px; height: 20px; }
    .approve-close:hover { background: #94a3b826; }
    .approve-modal-actions { justify-content: center; }
    .approve-modal-actions button { min-width: 140px; }
    #approve-dialog dd { max-width: 70%; text-align: right; }
    body.dark-mode #approve-dialog { background: #1e293b; border-color: #475569; color: #e2e8f0; }
    body.dark-mode #approve-description { color: #b8c5d8; }
    .pending-request { padding: 12px 16px; }
    .pending-request .person-position { display: block; margin-top: 2px; color: #64748b; font-size: 13px; }
    .pending-request .person-username { margin-top: 2px; }
    .pending-request .request-footer { margin-top: 10px; }
    .pending-request .request-footer .request-note { margin-top: 0; }
    body.dark-mode .pending-request .person-position { color: #cbd5e1; }
    .pending-request .request-footer .role-badge { padding: 2px 8px; line-height: 1.25; font-size: 12px; }
    .pending-request .request-top { padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; }
    body.dark-mode .pending-request .request-top { border-bottom-color: #3b4d65; }
    .account-row { padding-block: 16px; }
    .account-row .person-position { display: block; margin-top: 2px; color: #64748b; font-size: 13px; }
    .account-row .person-username { margin-top: 2px; }
    .account-row .account-row-top { padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; }
    .account-row .account-row-bottom { margin-top: 10px; }
    .account-row .role-badge { padding: 2px 8px; line-height: 1.25; font-size: 12px; }
    body.dark-mode .account-row .person-position { color: #cbd5e1; }
    body.dark-mode .account-row .account-row-top { border-bottom-color: #3b4d65; }
    .account-section .account-section-heading h2 { display: flex; align-items: center; gap: 9px; }
    .account-section-icon { width: 20px; height: 20px; flex: 0 0 20px; }
    [data-account-group="pending"] .account-section-icon { color: #b78a36; }
    [data-account-group="existing"] .account-section-icon { color: #4b927c; }
    [data-account-group="disabled"] .account-section-icon, [data-account-group="rejected"] .account-section-icon { color: #bd7179; }
    body.dark-mode [data-account-group="pending"] .account-section-icon { color: #d6b16c; }
    body.dark-mode [data-account-group="existing"] .account-section-icon { color: #7dbba4; }
    body.dark-mode [data-account-group="disabled"] .account-section-icon, body.dark-mode [data-account-group="rejected"] .account-section-icon { color: #d99aa1; }
  </style>
</head>
<body>
<aside class="sidebar sidebar--hover" id="admin-sidebar" aria-label="Admin navigation" data-query-navigation="true">
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
<button type="button" class="sidebar-edge-toggle" aria-label="Open sidebar" aria-expanded="false" aria-controls="admin-menu"><svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
<script src="js/sidebar_script.js?v=<?= filemtime(__DIR__ . '/js/sidebar_script.js') ?>"></script>
<main class="admin-main">
<header class="admin-header">
    <div class="admin-header-inner">
    <div><p class="admin-header-title">Administration</p><p class="admin-header-subtitle">Account management</p></div>
    <button type="button" id="admin-theme-toggle" class="theme-switch" role="switch" aria-checked="false" aria-label="Dark theme" title="Switch to dark theme"><svg class="switch-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg><svg class="switch-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 0 1 11.2 3 9 9 0 1 0 21 12.8Z"/></svg><span class="theme-switch-thumb" aria-hidden="true"></span></button>
  </div>
</header>
<div class="admin-content">
  <h1><?= $view === 'overview' ? 'Administration overview' : ($view === 'accounts' ? 'Manage accounts' : 'Recover accounts') ?></h1>
  <?php if ($view !== 'accounts'): ?>
  <p class="intro"><?= $view === 'recovery' ? 'Restore an account removed after this feature was added. Earlier permanent deletions require a database backup.' : 'Review registration requests and control who can use the inventory system. Confirm each applicant against institutional records before approving.' ?></p>
  <?php endif; ?>
  <?php if ($message): ?><div class="notice" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($view === 'overview'): ?>
    <div class="cards">
      <div class="card card--pending"><div class="card-heading"><span>Pending requests</span><svg class="card-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div><strong><?= $counts['pending'] ?></strong></div>
      <div class="card card--active"><div class="card-heading"><span>Active accounts</span><svg class="card-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-10 2 2 4-4"/></svg></div><strong><?= $counts['active'] ?></strong></div>
      <div class="card card--disabled"><div class="card-heading"><span>Disabled accounts</span><svg class="card-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m2-11 4 4m0-4-4 4"/></svg></div><strong><?= $counts['disabled'] ?></strong></div>
      <div class="card card--removed"><div class="card-heading"><span>Removed accounts</span><svg class="card-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8m-10 4h6"/></svg></div><strong><?= $counts['removed'] ?></strong></div>
    </div>
  <?php endif; ?>
  <?php if ($view === 'overview'): ?>
  <section class="panel pending-panel" aria-labelledby="pending-title">
    <div class="pending-heading"><h2 id="pending-title">Pending Requests</h2><span class="badge"><?= $counts['pending'] ?> pending</span></div>
    <?php if (!$visibleAccounts): ?><p class="request-note">No pending requests.</p><?php endif; ?>
    <?php foreach ($visibleAccounts as $account): ?>
    <article class="pending-request">
      <div class="request-top">
        <span class="request-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M6 21v-2a6 6 0 0 1 12 0v2"/></svg></span>
        <div class="request-person"><strong><?= htmlspecialchars($account['display_name'] ?: 'Full name not provided') ?></strong><span class="person-position">Position: <?= htmlspecialchars($account['user_position'] ?: 'Not provided') ?></span><span class="person-username">Username: <?= htmlspecialchars($account['username']) ?></span></div>
        <span class="badge pending">Pending</span>
      </div>
      <div class="request-footer"><span class="badge role-badge"><?= htmlspecialchars($account['role']) ?></span><span class="request-note">Account review required</span>
        <div class="account-actions">
        <?php if ((int)$account['user_id'] !== (int)$_SESSION['user_id']): ?>
          <?php admin_review_button($account); ?>
          <?php admin_action((int)$account['user_id'], 'reject', 'Reject', $_SESSION['admin_csrf'], $view, 'danger'); ?>
        <?php else: ?>Your account<?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </section>
  <?php elseif ($view === 'accounts'): ?>
  <div class="accounts-toolbar" role="search" aria-label="Find accounts">
    <label class="account-search">Search accounts<span class="account-search-field"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="account-search" type="search" placeholder="Name, email, or username" autocomplete="off"></span></label>
    <label>Account status<select id="account-status"><option value="all">All accounts</option><option value="pending">Pending</option><option value="existing">Existing</option><option value="disabled">Disabled</option><option value="rejected">Rejected</option></select></label>
    <p id="account-results" role="status" aria-live="polite"></p>
  </div>
  <?php
  $accountGroups = ['pending' => ['Pending accounts', ['pending']], 'existing' => ['Existing accounts', ['active', 'approved']], 'disabled' => ['Disabled accounts', ['disabled']], 'rejected' => ['Rejected accounts', ['rejected']]];
  $accountGroupIcons = [
      'pending' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'existing' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-10 2 2 4-4"/>',
      'disabled' => '<circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/>',
      'rejected' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>'
  ];
  foreach ($accountGroups as $groupKey => [$groupTitle, $groupStatuses]):
      $groupAccounts = array_values(array_filter($visibleAccounts, static fn($account) => in_array($account['status'], $groupStatuses, true)));
      if ($groupKey === 'rejected' && !$groupAccounts) continue;
  ?>
  <section class="panel account-section" data-account-group="<?= $groupKey ?>" aria-labelledby="heading-<?= $groupKey ?>">
    <div class="account-section-heading"><h2 id="heading-<?= $groupKey ?>"><svg class="account-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $accountGroupIcons[$groupKey] ?></svg><?= $groupTitle ?></h2><span class="account-count"><?= count($groupAccounts) ?> accounts</span></div>
    <?php foreach ($groupAccounts as $account): ?>
    <article class="account-row" data-role="<?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars($account['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="account-row-top"><div class="request-person"><strong><?= htmlspecialchars($account['display_name'] ?: 'Full name not provided') ?></strong><span class="person-position">Position: <?= htmlspecialchars($account['user_position'] ?: 'Not provided') ?></span><span class="person-username">@<?= htmlspecialchars($account['username']) ?></span></div><span class="badge <?= htmlspecialchars($account['status']) ?>"><?= htmlspecialchars($account['status']) ?></span></div>
      <div class="account-row-bottom"><span class="badge role-badge"><?= htmlspecialchars($account['role']) ?></span>
      <div class="account-actions">
          <?php if ((int)$account['user_id'] !== (int)$_SESSION['user_id']): ?>
            <?php if ($account['status'] === 'removed'): ?>
              <?php admin_action((int)$account['user_id'], 'restore', 'Recover', $_SESSION['admin_csrf'], $view, 'primary'); ?>
            <?php elseif ($account['status'] === 'pending'): ?>
              <?php admin_review_button($account); ?>
              <?php admin_action((int)$account['user_id'], 'reject', 'Reject', $_SESSION['admin_csrf'], $view, 'danger'); ?>
            <?php elseif (in_array($account['status'], ['active', 'approved'], true)): ?>
              <?php admin_action((int)$account['user_id'], 'disable', 'Disable', $_SESSION['admin_csrf'], $view, 'danger'); ?>
              <?php if ($account['status'] === 'active'): ?>
                <?php if ($account['role'] === 'admin'): ?>
                  <?php admin_action((int)$account['user_id'], 'demote', 'Make user', $_SESSION['admin_csrf'], $view); ?>
                <?php endif; ?>
              <?php endif; ?>
            <?php elseif ($account['status'] === 'disabled'): ?>
              <?php admin_action((int)$account['user_id'], 'enable', 'Enable', $_SESSION['admin_csrf'], $view); ?>
            <?php endif; ?>
            <?php if (!in_array($account['status'], ['removed', 'pending'], true)): ?><?php admin_action((int)$account['user_id'], 'remove', 'Remove', $_SESSION['admin_csrf'], $view, 'remove'); ?><?php endif; ?>
          <?php else: ?>Your account<?php endif; ?>

      </div></div>
    </article>
    <?php endforeach; ?>
    <p class="account-group-empty"<?= $groupAccounts ? ' hidden' : '' ?>>No accounts in this section.</p>
  </section>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="accounts-toolbar recovery-toolbar" role="search" aria-label="Find removed accounts">
    <label class="account-search">Search removed accounts<span class="account-search-field"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="recovery-search" type="search" placeholder="Name, email, or username" autocomplete="off"></span></label>
    <p id="recovery-results" role="status" aria-live="polite"></p>
  </div>
  <section class="panel recovery-panel" aria-labelledby="removed-heading">
    <div class="account-section-heading"><h2 id="removed-heading">Removed Accounts</h2><span class="account-count"><?= count($visibleAccounts) ?> <?= count($visibleAccounts) === 1 ? 'account' : 'accounts' ?></span></div>
    <?php if (!$visibleAccounts): ?>
    <div class="recovery-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v13h4m6 0h4V8m-7 13V11m-4 4 4-4 4 4"/></svg><strong>No removed accounts</strong><p>Accounts removed from the system will appear here and can be reviewed for restoration.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="recovery-table">
      <thead><tr><th>Person</th><th>Role</th><th>Removal details</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($visibleAccounts as $account): ?>
      <tr>
        <td data-label="Person"><strong><?= htmlspecialchars($account['display_name'] ?: 'Full name not provided') ?></strong><span class="person-email"><?= htmlspecialchars($account['email'] ?? '') ?></span><span class="person-username">@<?= htmlspecialchars($account['username']) ?></span></td>
        <td data-label="Role"><span class="badge role-badge"><?= htmlspecialchars($account['role']) ?></span></td>
        <td data-label="Removal details"><?= $account['removed_at'] ? htmlspecialchars(date('M j, Y', strtotime($account['removed_at']))) : 'Removal date unavailable' ?><small>Previously <?= htmlspecialchars($account['removed_from_status'] ?: 'unknown') ?></small></td>
        <td data-label="Action"><button type="button" class="restore-trigger" data-id="<?= (int)$account['user_id'] ?>" data-name="<?= htmlspecialchars($account['display_name'] ?: $account['username'], ENT_QUOTES, 'UTF-8') ?>" data-username="<?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?>" data-role="<?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?>" data-status="<?= htmlspecialchars($account['removed_from_status'] ?: 'unknown', ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2 7M3 4v7h7"/></svg>Restore</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div id="recovery-no-results" class="recovery-empty" hidden><strong>No matching accounts</strong><p>Try another name, email, or username.</p></div>
    <?php endif; ?>
  </section>
  <dialog id="restore-dialog" aria-labelledby="restore-title" aria-describedby="restore-description restore-info">
    <form method="post" id="restore-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
      <input type="hidden" name="action" value="restore"><input type="hidden" name="return_view" value="recovery"><input type="hidden" name="user_id" id="restore-user-id">
      <div class="restore-heading"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2 7M3 4v7h7"/></svg><div><h2 id="restore-title">Restore Account?</h2><p id="restore-description">Review the account before proceeding.</p></div></div>
      <strong id="restore-name"></strong>
      <dl class="restore-details"><div><dt>Username</dt><dd id="restore-username"></dd></div><div><dt>Previous role</dt><dd id="restore-role"></dd></div><div><dt>Previous status</dt><dd><span class="badge" id="restore-status"></span></dd></div></dl>
      <p id="restore-info">Restoring this account will return it to Pending status for administrator review. It will not automatically grant login access.</p>
      <div class="restore-modal-actions"><button type="button" id="restore-cancel" autofocus>Cancel</button><button type="submit" class="confirm-restore">Confirm Restore</button></div>
    </form>
  </dialog>
  <?php endif; ?>
</div>
</main>
<?php if ($view !== 'recovery'): ?>
<dialog id="approve-dialog" aria-labelledby="approve-title" aria-describedby="approve-description">
  <button type="button" class="approve-close" aria-label="Close account review" autofocus><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
  <form method="post" id="approve-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="return_view" value="<?= htmlspecialchars($view) ?>"><input type="hidden" name="reviewed_account" value="1"><input type="hidden" name="user_id" id="approve-user-id">
    <h2 id="approve-title">Review &amp; Approve Account</h2><p id="approve-description">Review the user's details before approving access to the inventory system.</p>
    <dl class="restore-details"><div><dt>Full name</dt><dd id="approve-name"></dd></div><div><dt>Username</dt><dd id="approve-username"></dd></div><div><dt>Email</dt><dd id="approve-email"></dd></div><div><dt>Position</dt><dd id="approve-position"></dd></div><div><dt>Role</dt><dd id="approve-role"></dd></div><div><dt>Status</dt><dd><span class="badge pending" id="approve-status"></span></dd></div></dl>
    <div class="restore-modal-actions approve-modal-actions"><button type="submit" class="confirm-restore">Approve</button></div>
  </form>
</dialog>
<?php endif; ?>
<script>
(() => {
  const approveDialog = document.getElementById('approve-dialog');
  if (approveDialog) {
    let approveOpener;
    document.querySelectorAll('.review-approve-trigger').forEach(button => button.addEventListener('click', () => {
      const account = JSON.parse(button.dataset.account);
      approveOpener = button;
      document.getElementById('approve-user-id').value = account.id;
      ['name', 'username', 'email', 'position', 'role', 'status'].forEach(key => { document.getElementById('approve-' + key).textContent = account[key]; });
      approveDialog.showModal();
    }));
    approveDialog.querySelector('.approve-close').addEventListener('click', () => approveDialog.close());
    approveDialog.addEventListener('close', () => approveOpener?.focus());
    document.getElementById('approve-form').addEventListener('submit', () => {
      const button = approveDialog.querySelector('[type="submit"]');
      button.disabled = true;
      button.textContent = 'Approving…';
    });
  }
  const recoverySearch = document.getElementById('recovery-search');
  if (recoverySearch) {
    const rows = [...document.querySelectorAll('.recovery-table tbody tr')];
    const tableWrap = document.querySelector('.recovery-panel .table-wrap');
    const emptySearch = document.getElementById('recovery-no-results');
    const searchable = new Map(rows.map(row => [row, row.querySelector('td').textContent.toLocaleLowerCase()]));
    const filterRemovedAccounts = () => {
      const query = recoverySearch.value.trim().toLocaleLowerCase();
      let count = 0;
      rows.forEach(row => { row.hidden = !searchable.get(row).includes(query); if (!row.hidden) count++; });
      if (tableWrap) tableWrap.hidden = count === 0;
      if (emptySearch) emptySearch.hidden = count > 0;
      document.getElementById('recovery-results').textContent = count + (count === 1 ? ' account found' : ' accounts found');
      document.querySelector('.recovery-panel .account-count').textContent = count + (count === 1 ? ' account' : ' accounts');
    };
    recoverySearch.addEventListener('input', filterRemovedAccounts);
    filterRemovedAccounts();
  }
  const restoreDialog = document.getElementById('restore-dialog');
  if (restoreDialog) {
    let restoreOpener;
    document.querySelectorAll('.restore-trigger').forEach(button => button.addEventListener('click', () => {
      restoreOpener = button;
      document.getElementById('restore-user-id').value = button.dataset.id;
      ['name', 'username', 'role'].forEach(key => { document.getElementById('restore-' + key).textContent = button.dataset[key]; });
      const status = document.getElementById('restore-status');
      status.textContent = button.dataset.status;
      status.className = 'badge';
      if (['pending', 'active', 'approved', 'disabled', 'rejected'].includes(button.dataset.status)) status.classList.add(button.dataset.status);
      restoreDialog.showModal();
    }));
    document.getElementById('restore-cancel').addEventListener('click', () => restoreDialog.close());
    restoreDialog.addEventListener('close', () => restoreOpener?.focus());
    document.getElementById('restore-form').addEventListener('submit', () => {
      restoreDialog.querySelector('.confirm-restore').disabled = true;
      restoreDialog.querySelector('.confirm-restore').textContent = 'Restoring…';
    });
  }
  const accountSearch = document.getElementById('account-search');
  if (accountSearch) {
    const statusFilter = document.getElementById('account-status');
    const sections = [...document.querySelectorAll('[data-account-group]')];
    const searchText = new Map([...document.querySelectorAll('.account-row')].map(row => [row, (row.querySelector('.request-person').textContent + ' ' + (row.dataset.email || '')).toLocaleLowerCase()]));
    const filterAccounts = () => {
      const query = accountSearch.value.trim().toLocaleLowerCase();
      let total = 0;
      sections.forEach(section => {
        const selected = statusFilter.value === 'all' || section.dataset.accountGroup === statusFilter.value;
        section.hidden = !selected;
        let count = 0;
        section.querySelectorAll('.account-row').forEach(row => {
          const matches = searchText.get(row).includes(query);
          row.hidden = !matches;
          if (matches) count++;
        });
        section.querySelector('.account-count').textContent = count + (count === 1 ? ' account' : ' accounts');
        const empty = section.querySelector('.account-group-empty');
        empty.hidden = count > 0;
        empty.textContent = query ? 'No accounts match your search and filters.' : 'No accounts in this section.';
        if (selected) total += count;
      });
      document.getElementById('account-results').textContent = total + (total === 1 ? ' account found' : ' accounts found');
    };
    accountSearch.addEventListener('input', filterAccounts);
    statusFilter.addEventListener('change', filterAccounts);
    filterAccounts();
  }

  try {
    if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
  } catch (_) {}
  const themeToggle = document.getElementById('admin-theme-toggle');
  const updateAdminTheme = () => {
    const dark = document.body.classList.contains('dark-mode');
    themeToggle.setAttribute('aria-checked', String(dark));
    themeToggle.title = dark ? 'Switch to light theme' : 'Switch to dark theme';
  };
  themeToggle.addEventListener('click', () => {
    const dark = document.body.classList.toggle('dark-mode');
    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (_) {}
    updateAdminTheme();
  });
  updateAdminTheme();
})();
</script>
</body>
</html>
