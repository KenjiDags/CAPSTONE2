<?php
require 'auth.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Property Card (Semi-Expendables)</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .page-header { display:flex; justify-content:center; align-items:center; margin-bottom:16px; text-align:center; }
    .page-header h2 { margin:0; }
    .export-section { display:flex; gap:10px; }
    .btn { padding:8px 12px; border:none; border-radius:4px; text-decoration:none; cursor:pointer; display:inline-block; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-secondary { background:#6b7280; color:#fff; }
    .table-wrapper { overflow-x:auto; background:transparent; border-radius:8px; box-shadow:none; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .currency { text-align: right; }
  .filters { margin-bottom:12px; display:flex; gap:12px; align-items:center; flex-wrap: wrap; }
  .filters .control { display:flex; align-items:center; gap:10px; }
  .filters select, .filters input {
    height: 38px;
    padding: 8px 14px;
    border-radius: 9999px;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    color: #111827;
    font-size: 14px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
  }
  .filters input::placeholder { color: #9ca3af; }
  .filters select:hover, .filters input:hover { background-color: #ffffff; }
  .filters select:focus, .filters input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    background-color: #ffffff;
  }
  .filters select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    padding-right: 38px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px 18px;
  }
  .filters .pill-btn { height: 38px; padding: 0 16px; }
  .filters #searchInput { width: 400px; max-width: 65vw; }
    .pill-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 9999px;
      color: #fff !important;
      font-weight: 600;
      border: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.12);
      transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    }
    .pill-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,0.18); text-decoration: none; opacity: 0.95; }
    .pill-view { background: linear-gradient(135deg, #67a8ff 0%, #3b82f6 100%); }
    .pill-export { background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%); }
    .pill-btn .fas, .pill-btn .fa-solid { font-size: 0.95em; }
  .actions-col { width: 1%; white-space: nowrap; }
  table th.actions-col, table td.actions-col { padding-left: 4px; padding-right: 4px; }
  .action-stack { display: inline-flex; flex-direction: column; gap: 8px; align-items: center; }
    .amount-col { white-space: nowrap; min-width: 140px; }
  </style>
</head>
<body>
  <?php include 'sidebar.php'; ?>
  <div class="content">
    <div class="page-header">
      <h2>Property Card (Semi-Expendables)</h2>
    </div>

  <form id="pc-semi-filters" method="get" class="filters">
      <?php
        $category = isset($_GET['category']) ? $_GET['category'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $valid_categories = ['Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures'];
      ?>
      <div class="control">
        <label for="category-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
          <i class="fas fa-filter"></i> Category:
        </label>
        <select id="category-select" name="category" onchange="this.form.submit()">
          <option value="">All</option>
          <?php foreach ($valid_categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search description or property no..." />
        <a href="semi_expendable_export_all.php<?= ($category!==''||$search!=='') ? ('?' . http_build_query(array_filter(['category'=>$category,'search'=>$search], fn($v)=>$v!==''))) : '' ?>" target="_blank" class="pill-btn pill-export">
          <i class="fas fa-file-export"></i> Export All
        </a>
      </div>
    </form>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Description</th>
            <th>Property Number</th>
            <th>Date</th>
            <th>Reference/ICS No.</th>
            <th>Receipt Qty.</th>
            <th>Issue Qty.</th>
            <th>Balance Qty.</th>
            <th class="amount-col">Amount</th>
            <th class="actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $params = [];
            $types = '';
            $where = [];
            if ($category !== '') { $where[] = 'category = ?'; $params[] = $category; $types .= 's'; }
            if ($search !== '') { $where[] = '(item_description LIKE ? OR semi_expendable_property_no LIKE ? OR ics_rrsp_no LIKE ?)'; $like = "%$search%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss'; }
            $sql = "SELECT id, date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity, quantity_issued, office_officer_issued, quantity_returned, office_officer_returned, quantity_reissued, office_officer_reissued, quantity_disposed, quantity_balance, amount_total, category, fund_cluster, remarks FROM semi_expendable_property";
            if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
            $sql .= ' ORDER BY date DESC, id DESC';

            if (!empty($params)) {
              $stmt = $conn->prepare($sql);
              if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
              } else {
                $result = false;
              }
            } else {
              $result = $conn->query($sql);
            }

            if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $description = $row['item_description'] ?? '';
                $propertyNo = $row['semi_expendable_property_no'] ?? '';
                $txDate = $row['date'] ?? '';
                $referenceNo = $row['ics_rrsp_no'] ?? '';
                $receiptQty = (int)($row['quantity'] ?? 0);
                $issueQty = (int)($row['quantity_issued'] ?? 0);
                $balanceQty = (int)($row['quantity_balance'] ?? 0);
                $amount = (float)($row['amount_total'] ?? 0);
                echo '<tr>';
                echo '<td>' . htmlspecialchars($description) . '</td>';
                echo '<td>' . htmlspecialchars($propertyNo) . '</td>';
                echo '<td>' . htmlspecialchars($txDate) . '</td>';
                echo '<td>' . htmlspecialchars($referenceNo) . '</td>';
                echo '<td class="text-center">' . ($receiptQty ?: '') . '</td>';
                echo '<td class="text-center">' . ($issueQty ?: '') . '</td>';
                echo '<td class="text-center">' . ($balanceQty ?: '') . '</td>';
                echo '<td class="currency amount-col">' . ($amount ? ('₱ ' . number_format($amount, 2)) : '') . '</td>';
                $returnUrl = 'PC_semi.php';
                $qs = [];
                if ($category !== '') { $qs['category'] = $category; }
                if ($search !== '') { $qs['search'] = $search; }
                if (!empty($qs)) { $returnUrl .= '?' . http_build_query($qs); }
                echo '<td class="text-center actions-col">';
                echo '<div class="action-stack">';
                echo '<a href="view_semi_expendable.php?id=' . (int)$row['id'] . '&return=' . urlencode($returnUrl) . '" class="pill-btn pill-view"><i class="fas fa-eye"></i> View</a>';
                echo '<a href="semi_expendable_export.php?id=' . (int)$row['id'] . '" target="_blank" class="pill-btn pill-export"><i class="fas fa-download"></i> Export</a>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
              }
            } else {
              echo '<tr><td colspan="9" class="text-center">No Semi-Expendable entries found.</td></tr>';
            }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
