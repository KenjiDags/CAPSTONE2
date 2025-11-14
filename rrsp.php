<?php
require 'config.php';
require 'functions.php';

// RRSP listing page (mirrors ICS listing). If deletion requested, process first.
if (isset($_GET['delete_rrsp_id'])) {
    $del_id = (int)$_GET['delete_rrsp_id'];
    $conn->query("DELETE FROM rrsp_items WHERE rrsp_id = $del_id");
    $conn->query("DELETE FROM rrsp WHERE rrsp_id = $del_id");
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header("Location: rrsp.php" . $sortParam);
    exit();
}

// Sorting logic similar to ICS
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
switch ($sort_by) {
    case 'rrsp_no':
        $order_clause = "ORDER BY rrsp_no ASC"; break;
    case 'date_oldest':
        $order_clause = "ORDER BY date_prepared ASC"; break;
    case 'amount_highest':
        $order_clause = "ORDER BY total_amount DESC"; break;
    case 'amount_lowest':
        $order_clause = "ORDER BY total_amount ASC"; break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY date_prepared DESC"; break;
}

// Fetch RRSP forms with computed total (sum of quantities * optional unit_cost if stored)
$result = $conn->query("SELECT r.*, (
    SELECT COALESCE(SUM(ri.quantity * ri.unit_cost),0) FROM rrsp_items ri WHERE ri.rrsp_id = r.rrsp_id
) AS total_amount FROM rrsp r $order_clause");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RRSP - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    @media screen {
        .header-controls { display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:24px; }
        .sort-container { display:flex; align-items:center; }
        .sort-pill { display:inline-flex; align-items:center; gap:10px; background:#f3f7ff; border:1px solid #dbeafe; border-radius:9999px; padding:6px 12px; box-shadow:0 4px 12px rgba(2,6,23,0.06), inset 0 1px 1px rgba(0,0,0,0.03); height:44px; }
        .rrsp-page .header-controls > button { display:inline-flex; align-items:center; gap:8px; height:44px; padding:0 16px; }
        .sort-select-container { position:relative; }
        .sort-select { height:36px; line-height:36px; padding:0 28px 0 10px; appearance:none; background:#fff; border:1px solid #dbeafe; border-radius:12px; font-size:14px; min-width:210px; }
        .sort-select:focus { border-color:#60a5fa; box-shadow:0 0 0 3px rgba(59,130,246,0.2); }
        .sort-select-chevron { position:absolute; right:8px; top:50%; transform:translateY(-50%); pointer-events:none; color:#64748b; font-size:12px; }
    }
    table.rrsp-table { width:100%; border-collapse:collapse; }
    table.rrsp-table th, table.rrsp-table td { border:1px solid #e5e7eb; padding:6px 8px; }
    table.rrsp-table thead th { background: var(--blue-gradient); color:#fff; position:sticky; top:0; }
    .rrsp-table-wrapper { max-height:420px; overflow:auto; scrollbar-gutter:stable; background:#fff; border:1px solid #e5e7eb; border-radius:8px; }
    .form-inline { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:18px; }
    .form-inline .form-group { display:flex; flex-direction:column; }
    </style>
</head>
<body class="rrsp-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Receipt of Returned Semi-Expendable Property (RRSP)</h2>
    <div class="header-controls">
        <button onclick="window.location.href='add_rrsp.php'"><i class="fas fa-plus"></i> Add RRSP Form</button>
        <div class="sort-container">
            <div class="sort-pill">
                <label for="sort-select"><i class="fas fa-sort" style="color:#0b4abf"></i><span>Sort by:</span></label>
                <div class="sort-select-container">
                    <select id="sort-select" class="sort-select" onchange="sortRRSP(this.value)">
                        <option value="date_newest" <?= ($sort_by==='date_newest')?'selected':''; ?>>Date (Newest First)</option>
                        <option value="date_oldest" <?= ($sort_by==='date_oldest')?'selected':''; ?>>Date (Oldest First)</option>
                        <option value="rrsp_no" <?= ($sort_by==='rrsp_no')?'selected':''; ?>>RRSP No. (A-Z)</option>
                        <option value="amount_highest" <?= ($sort_by==='amount_highest')?'selected':''; ?>>Total Amount (Highest)</option>
                        <option value="amount_lowest" <?= ($sort_by==='amount_lowest')?'selected':''; ?>>Total Amount (Lowest)</option>
                    </select>
                    <span class="sort-select-chevron"><i class="fas fa-chevron-down"></i></span>
                </div>
            </div>
        </div>
        <div style="flex:1; min-width:240px;">
            <input type="text" id="searchInput" placeholder="Search RRSP..." style="width:100%; padding:10px 12px; border:1px solid #dbeafe; border-radius:8px;">
        </div>
    </div>
    <table class="rrsp-table" id="rrsp-list">
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> RRSP No.</th>
                <th><i class="fas fa-calendar"></i> Date Prepared</th>
                <th><i class="fas fa-user"></i> Returned By</th>
                <th><i class="fas fa-user-check"></i> Received By</th>
                <th><i class="fas fa-building"></i> Fund Cluster</th>
                <th><i class="fas fa-dollar-sign"></i> Total Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if($result && $result->num_rows>0): while($row=$result->fetch_assoc()): ?>
            <tr data-text="<?= htmlspecialchars(strtolower(($row['rrsp_no']??'').' '.($row['returned_by']??'').' '.($row['received_by']??''))) ?>">
                <td><strong><?= htmlspecialchars($row['rrsp_no']) ?></strong></td>
                <td><?= htmlspecialchars(date('M d, Y', strtotime($row['date_prepared']))) ?></td>
                <td><?= htmlspecialchars($row['returned_by']) ?></td>
                <td><?= htmlspecialchars($row['received_by']) ?></td>
                <td><?= htmlspecialchars($row['fund_cluster']) ?></td>
                <td>₱<?= number_format($row['total_amount'],2) ?></td>
                <td>
                    <a href="view_rrsp.php?rrsp_id=<?= (int)$row['rrsp_id'] ?>"><i class="fas fa-eye"></i> View</a>
                    <a href="edit_rrsp.php?rrsp_id=<?= (int)$row['rrsp_id'] ?>"><i class="fas fa-edit"></i> Edit</a>
                    <a href="export_rrsp.php?rrsp_id=<?= (int)$row['rrsp_id'] ?>"><i class="fas fa-download"></i> Export</a>
                    <a href="rrsp.php?delete_rrsp_id=<?= (int)$row['rrsp_id'] ?>" onclick="return confirm('Delete this RRSP form?')"><i class="fas fa-trash"></i> Delete</a>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="7" style="text-align:center; padding:32px; font-style:italic; color:#64748b;">No RRSP forms found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
function sortRRSP(val){ const url=new URL(window.location); url.searchParams.set('sort',val); window.location.href=url.toString(); }
document.getElementById('searchInput').addEventListener('input', function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('#rrsp-list tbody tr').forEach(r=>{ const txt=(r.getAttribute('data-text')||'').toLowerCase(); r.style.display=(q===''||txt.includes(q))?'':'none'; });
});
</script>
</body>
</html>
