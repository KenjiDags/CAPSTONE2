<?php
require 'auth.php';
require 'config.php';
require_once 'archive_helpers.php';
ensureArchiveTable($conn);
if (empty($_SESSION['archive_csrf'])) $_SESSION['archive_csrf'] = bin2hex(random_bytes(32));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['archive_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request. Reload Archive and try again.');
    }
    try {
        $action = (string)($_POST['action'] ?? '');
        archiveAction($conn, (int)($_POST['archive_id'] ?? 0), $action);
        if ($action === 'restore') {
            require_once 'inventory_count_cache.php';
            invalidateInventoryCount();
        }
        $_SESSION['archive_notice'] = ['success', $action === 'restore' ? 'Record restored successfully.' : 'Archived record permanently deleted.'];
    } catch (Throwable $error) {
        error_log('Archive action failed: ' . $error->getMessage());
        $_SESSION['archive_notice'] = ['error', 'Unable to complete the action. The archive was kept. A conflicting live record or missing related record may prevent restoration.'];
    }
    header('Location: archive.php?' . http_build_query(['search' => (string)($_POST['search'] ?? ''), 'type' => (string)($_POST['type'] ?? '')]));
    exit;
}
$notice = $_SESSION['archive_notice'] ?? null;
unset($_SESSION['archive_notice']);
function archiveEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$search = trim((string)($_GET['search'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$detail = null;
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM deleted_records WHERE archive_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    if (!$detail) { http_response_code(404); }
}
$like = '%' . $search . '%';
$stmt = $conn->prepare("SELECT archive_id, record_type, record_label, deleted_at, deleted_by FROM deleted_records WHERE (record_label LIKE ? OR record_type LIKE ?) AND (? = '' OR record_type = ? OR (? = 'RSMI' AND record_type = 'RIS') OR (? = 'SC' AND record_type = 'Supply')) ORDER BY deleted_at DESC, archive_id DESC LIMIT 200");
$stmt->bind_param('ssssss', $like, $like, $type, $type, $type, $type);
$stmt->execute();
$records = $stmt->get_result();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Archive - TESDA Inventory</title>
<link rel="stylesheet" href="css/styles.css"><link rel="stylesheet" href="css/PPE.css">
<style>
.archive-actions-menu { min-width:185px; }
.archive-actions-menu form { margin:0; }
.archive-actions-menu a,
.archive-actions-menu button { display:flex !important; align-items:center !important; justify-content:flex-start !important; gap:8px; width:100%; margin:0 !important; padding:8px !important; border:0 !important; border-radius:4px !important; background:transparent !important; color:#374151 !important; font:inherit; font-size:12px !important; font-weight:400 !important; text-align:left; text-decoration:none; cursor:pointer; transform:none !important; box-shadow:none !important; transition:none !important; }
.archive-actions-menu .delete-action { color:#dc2626 !important; }
body.dark-mode .archive-actions-menu a,
body.dark-mode .archive-actions-menu button { color:#e2e8f0 !important; }
body.dark-mode .archive-actions-menu .delete-action { color:#fca5a5 !important; }
.archive-actions-menu :focus-visible { outline:2px solid #3b82f6; outline-offset:-2px; }
.archive-notice { margin:20px; padding:12px 16px; border:1px solid #86b89b; border-radius:8px; color:#166534; background:#edf7f0; }
.archive-notice.error { color:#991b1b; background:#fef2f2; border-color:#fca5a5; }
.archive-list table tbody tr,
.archive-list table tbody tr:hover { background:#fff; transform:none !important; box-shadow:none !important; transition:none !important; }
.archive-list table tbody tr:nth-child(even),
.archive-list table tbody tr:nth-child(even):hover { background:#f8fbff; }
body.dark-mode .container .archive-list table tbody tr td,
body.dark-mode .container .archive-list table tbody tr:hover td { background:#1e293b !important; }

.archive-intro { color: #64748b; margin: 0 20px 20px; }
.archive-filters { display:flex; flex-wrap:wrap; gap:12px; margin:20px; align-items:end; }
.archive-filters label { display:grid; gap:6px; font-weight:600; }
.archive-filters input,.archive-filters select,.archive-filters button { padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; }
.archive-filters button { background:#1e40af; color:white; cursor:pointer; }
.archive-detail { margin:20px; padding:24px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; color:#1e293b; min-width:0; }
.archive-detail h3 { margin:16px 0 8px; overflow-wrap:anywhere; }
.archive-back { display:inline-block; color:#1d4ed8; font-weight:700; }
.archive-detail details { border-top:1px solid #cbd5e1; margin-top:20px; }
.archive-record { margin:12px 0; padding:18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; min-width:0; }
.archive-record h4 { margin:0 0 14px; }
.archive-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr)); gap:18px 24px; margin:0; }
.archive-field { min-width:0; }
.archive-fields dt { color:#64748b; font-size:12px; font-weight:700; margin-bottom:5px; }
.archive-fields dd { margin:0; font-size:14px; line-height:1.6; white-space:pre-wrap; overflow-wrap:anywhere; }
body.dark-mode .archive-detail { background:#1e293b; color:#e2e8f0; border-color:#475569; }
body.dark-mode .archive-record { background:#0f172a; border-color:#475569; }
body.dark-mode .archive-fields dt { color:#cbd5e1; }
body.dark-mode .archive-back { color:#93c5fd; }
@media(max-width:600px) { .archive-detail { margin:12px 0; padding:14px; } .archive-record { padding:12px; } }
.archive-detail summary { cursor:pointer; font-weight:700; padding:12px 0; }
</style></head><body>
<?php include 'sidebar.php'; ?>
<main class="container"><h2>Archive</h2>
<?php if ($notice): ?><p class="archive-notice <?= archiveEscape($notice[0]) ?>" role="status"><?= archiveEscape($notice[1]) ?></p><?php endif; ?>
<?php if (!$detail): ?>
<form class="archive-filters" method="get"><label>Search<input name="search" value="<?= archiveEscape($search) ?>" placeholder="Record number or type"></label>
<label>Record type<select name="type"><option value="">All types</option><?php foreach (['SC', 'RIS', 'RSMI', 'Supply', 'ICS', 'ITR', 'PTR', 'PAR', 'PC', 'IIRUP', 'RRSP', 'IIRUSP', 'PPE', 'Semi-expendable', 'ICT'] as $option): ?><option value="<?= archiveEscape($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= archiveEscape($option) ?></option><?php endforeach; ?></select></label><button type="submit">Filter</button><a href="archive.php">Reset</a></form>
<?php endif; ?>
<?php if ($id && !$detail): ?><p class="archive-intro">Archived record not found.</p><?php endif; ?>
<?php if ($detail): ?>
<section class="archive-detail" aria-label="Archived record details"><a class="archive-back" href="archive.php?<?= archiveEscape(http_build_query(['search' => $search, 'type' => $type])) ?>">&larr; Back to Archive</a><h3><?= archiveEscape($detail['record_type'] . ' — ' . $detail['record_label']) ?></h3><p>Deleted <?= archiveEscape($detail['deleted_at']) ?></p>
<?php foreach (json_decode($detail['snapshot'], true, 512, JSON_THROW_ON_ERROR) as $table => $rows): ?>
<details open><summary><?= archiveEscape(ucwords(str_replace('_', ' ', $table))) ?> (<?= count($rows) ?>)</summary>
<?php if (!$rows): ?><p>No related entries.</p><?php else: ?>
<?php foreach ($rows as $index => $row): ?>
<article class="archive-record" aria-label="Entry <?= $index + 1 ?>">
<?php if (count($rows) > 1): ?><h4>Entry <?= $index + 1 ?></h4><?php endif; ?>
<dl class="archive-fields">
<?php foreach ($row as $column => $value): ?>
<div class="archive-field"><dt><?= archiveEscape(ucwords(str_replace('_', ' ', $column))) ?></dt><dd><?= $value === null || $value === '' ? '&mdash;' : archiveEscape($value) ?></dd></div>
<?php endforeach; ?>
</dl></article>
<?php endforeach; ?>
<?php endif; ?></details><?php endforeach; ?></section><?php endif; ?>
<?php if (!$detail): ?>
<div class="table-container archive-list"><table><thead><tr><th>Type</th><th>Record</th><th>Deleted at</th><th>Deleted by (user ID)</th><th>Action</th></tr></thead><tbody>
<?php if (!$records->num_rows): ?><tr><td colspan="5">No archived records found.</td></tr><?php endif; ?>
<?php foreach ($records as $record): ?><tr><td><?= archiveEscape($record['record_type']) ?></td><td><?= archiveEscape($record['record_label']) ?></td><td><?= archiveEscape($record['deleted_at']) ?></td><td><?= archiveEscape($record['deleted_by']) ?></td>
<td class="actions-cell"><div class="actions-menu">
<button type="button" class="actions-menu-toggle" aria-label="Actions for <?= archiveEscape($record['record_label']) ?>" aria-expanded="false" aria-controls="archive-actions-<?= (int)$record['archive_id'] ?>"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>
<div class="actions-menu-list archive-actions-menu" id="archive-actions-<?= (int)$record['archive_id'] ?>">
<a href="archive.php?<?= archiveEscape(http_build_query(['id' => $record['archive_id'], 'search' => $search, 'type' => $type])) ?>"><i class="fas fa-eye" aria-hidden="true"></i> View</a>
<?php foreach (['delete' => 'Delete permanently', 'restore' => 'Restore'] as $action => $label): ?>
<form method="post" action="archive.php" data-archive-action="<?= $action ?>">
<input type="hidden" name="csrf" value="<?= archiveEscape($_SESSION['archive_csrf']) ?>">
<input type="hidden" name="archive_id" value="<?= (int)$record['archive_id'] ?>">
<input type="hidden" name="action" value="<?= $action ?>">
<input type="hidden" name="search" value="<?= archiveEscape($search) ?>">
<input type="hidden" name="type" value="<?= archiveEscape($type) ?>">
<button type="submit" class="<?= $action === 'delete' ? 'delete-action' : 'restore-action' ?>"><i class="fas <?= $action === 'delete' ? 'fa-trash' : 'fa-undo' ?>" aria-hidden="true"></i> <?= $label ?></button>
</form><?php endforeach; ?>
</div></div></td></tr><?php endforeach; ?>
</tbody></table></div><p class="archive-intro">Showing up to 200 matching records. Use search to narrow results. Records deleted before archiving was enabled may not be available.</p>
<?php endif; ?>
</main>
<script src="js/actions_menu.js"></script>
<script>
document.addEventListener('submit', function (event) {
    const form = event.target.closest('[data-archive-action]');
    if (!form) return;
    const message = form.dataset.archiveAction === 'delete'
        ? 'Permanently delete this archived record? This cannot be undone.'
        : 'Restore this record and its related entries?';
    if (!confirm(message)) event.preventDefault();
});
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    const toggle = document.querySelector('.archive-list .actions-menu.is-open .actions-menu-toggle');
    if (toggle) { toggle.click(); toggle.focus(); }
});
</script></body></html>
