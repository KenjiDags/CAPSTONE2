<?php
// Legacy bookmarks remain usable; the retired overview is never rendered.
require 'auth.php';
if (($_GET['view'] ?? '') === 'restock') {
    $itemId = filter_var($_GET['item_id'] ?? null, FILTER_VALIDATE_INT);
    $target = 'add_multiple_items.php' . ($itemId && $itemId > 0 ? '?item_id=' . $itemId : '');
    header('Location: ' . $target, true, 307);
    exit;
}
header('Location: analytics.php');
exit;
