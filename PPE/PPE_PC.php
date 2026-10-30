<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/config.php';
$pcReturnUrl = 'PPE/PPE_PC.php?' . http_build_query([
    'search' => is_string($_GET['search'] ?? '') ? ($_GET['search'] ?? '') : '',
    'sort' => is_string($_GET['sort'] ?? '') ? ($_GET['sort'] ?? 'date_newest') : 'date_newest',
]);

// DELETE LOGIC (moved from pc_delete.php)
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
	$id = (int)$_GET['delete_id'];
    require_once dirname(__DIR__) . '/archive_helpers.php';
    archiveRecord($conn, 'PC', $id);
	// Redirect back to the return URL or PPE_PC.php
	$return = isset($_GET['return']) ? $_GET['return'] : 'PPE_PC.php';
	header('Location: ' . (is_string($return) && str_starts_with($return, 'PPE/') ? APP_ROOT_URL : '') . $return);
	exit();
}
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Property Card (PPE)</title>
	<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">

	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
		.container h2::before {
			content: "\f15c";
			font-family: "Font Awesome 6 Free";
			font-weight: 900;
			color: #3b82f6;
			margin-right: 12px;
		}
		.export-section { display:flex; gap:10px; }
		.table-wrapper { 
			overflow-x:auto;
			background: rgba(255, 255, 255, 0.95);
			border-radius: 12px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.08);
			padding: 0;
		}
		.text-center { text-align: center; }
		.text-right { text-align: right; }
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
		.description-col { width: 280px; }
		.actions-col { width: 100px; white-space: nowrap; text-align: left !important; }
		table th.actions-col, table td.actions-col { padding-left: 12px; padding-right: 12px; }
		.action-stack { display: inline-flex; flex-direction: row; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: center; }
		.amount-col { white-space: nowrap; min-width: 100px; }
        #ppe-property-cards { table-layout: fixed; min-width: 1100px; }
        #ppe-property-cards .description-col { width: auto; white-space: normal; overflow-wrap: anywhere; }
        #ppe-property-cards :is(th, td):nth-child(n+5):nth-child(-n+7) { width: auto; min-width: 0; padding-inline: 6px !important; text-align: center; }
        #ppe-property-cards thead th:nth-child(n+5):nth-child(-n+7) { white-space: normal; overflow-wrap: normal; font-size: 12px; }
        #ppe-property-cards .actions-col, #ppe-property-cards .amount-col { width: auto; min-width: 0; }
        body.dark-mode .table-wrapper:has(#ppe-property-cards) { background: #1e293b; }
        body.dark-mode #ppe-property-cards :is(tbody tr, tbody td) { border-color: #334155 !important; }
        body.dark-mode #ppe-property-cards tbody tr { box-shadow: none; }
	</style>
</head>
<body class="ppe-page">
	<?php include dirname(__DIR__) . '/sidebar.php'; ?>
	<div class="container">
		<h2>Property Card (PPE)</h2>

		<form id="ppe-filters" method="get" class="filters" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
			<?php
				$search = isset($_GET['search']) ? $_GET['search'] : '';
				$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
			?>
			<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1;">
                <div class="control">
                    <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                        <i class="fas fa-sort"></i> Sort by:
                    </label>
                    <select id="sort-select" name="sort" onchange="this.form.submit()">
                        <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
                        <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
                        <option value="property_no" <?= ($sort_by == 'property_no') ? 'selected' : '' ?>>Property No. (A-Z)</option>
                        <option value="amount_highest" <?= ($sort_by == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
                        <option value="amount_lowest" <?= ($sort_by == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
                    </select>
                </div>
				<div class="control">
					<label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
						<i class="fas fa-search"></i> Search:
					</label>
					<input type="text" id="searchInput" name="search"
								value="<?= htmlspecialchars($search) ?>"
								placeholder="Search description or property no..." />
				</div>
			</div>
			<a href="PPE/PPE_PC_export_all.php?export_all=1"
				class="pill-btn pill-export" style="margin-left:auto; border-radius: 8px !important;">
					<i class="fas fa-file-export"></i> Export All
			</a>
		</form>

		<div class="table-wrapper">
			<table id="ppe-property-cards" style="margin-bottom: 0px !important;">
<colgroup><col style="width:34%"><col style="width:10%"><col style="width:9%"><col style="width:13%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:11%"><col style="width:8%"></colgroup>
				<thead>
					<tr>
						<th>Description</th>
						<th>Property No.</th>
						<th>Date</th>
						<th>Reference/PAR No.</th>
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
						if ($search !== '') {
							$where[] = '(item_name LIKE ? OR description LIKE ? OR PAR_number LIKE ? OR officer_incharge LIKE ?)';
							$like = "%$search%";
							$params = array_fill(0, 4, $like);
							$types = str_repeat('s', 4);
						}
						$sql = "SELECT * FROM item_history_ppe";
						if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
						$sql = "SELECT h.*, p.property_no, p.id AS ppe_id FROM item_history_ppe h INNER JOIN (
							SELECT PPE_no, MAX(changed_at) AS max_changed_at
							FROM item_history_ppe
							GROUP BY PPE_no
						) latest ON h.PPE_no = latest.PPE_no AND h.changed_at = latest.max_changed_at
						LEFT JOIN ppe_property p ON h.PPE_no = p.PPE_no";
						if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
						$sql .= ' ORDER BY h.changed_at DESC, h.id DESC';

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
								$itemName = $row['item_name'] ?? '';
								$itemDescription = $row['description'] ?? '';
								$propertyNo = $row['property_no'] ?? '';
								$txDate = $row['changed_at'] ?? '';
								$displayDate = '';
								if ($txDate) {
									$displayDate = date('Y-m-d', strtotime($txDate));
								}
								$referenceNo = $row['PAR_number'] ?? $row['PPE_no'] ?? '';
								$receiptQty = (int)($row['receipt_qty'] ?? 0);
								$issueQty = (int)($row['issue_qty'] ?? 0);
								$balanceQty = (int)($row['balance_qty'] ?? 0);
								$amount = (float)($row['unit_cost'] ?? 0) * (int)($row['quantity_on_hand'] ?? 0);
								$rowClass = !empty($row['ppe_id']) ? ' class="clickable-row"' : '';
								$rowViewAttr = !empty($row['ppe_id']) ? ' data-view-url="PPE/view_ppe_items.php?id=' . (int)$row['ppe_id'] . '&return=' . urlencode($pcReturnUrl) . '"' : '';
								echo '<tr' . $rowClass . $rowViewAttr . '>';
								echo '<td class="description-col">' . htmlspecialchars($itemName);
								if ($itemDescription) {
									echo ', ' . htmlspecialchars($itemDescription);
								}
								echo '</td>';
								echo '<td>' . htmlspecialchars($propertyNo) . '</td>';
								echo '<td>' . htmlspecialchars($displayDate) . '</td>';
								echo '<td>' . htmlspecialchars($referenceNo) . '</td>';
								echo '<td class="text-center">' . ($receiptQty ?: '') . '</td>';
								echo '<td class="text-center">' . ($issueQty ?: '') . '</td>';
								echo '<td class="text-center">' . ($balanceQty ?: '') . '</td>';
								echo '<td class="currency amount-col">' . ($amount ? ('₱ ' . number_format($amount, 2)) : '') . '</td>';
								$returnUrl = 'PPE/PPE_PC.php';
								$qs = [];
								if ($search !== '') { $qs['search'] = $search; }
								if (!empty($qs)) { $returnUrl .= '?' . http_build_query($qs); }
								echo '<td class="actions-col">';
								echo '<div class="actions-menu">';
								echo '<button type="button" class="actions-menu-toggle" aria-label="Open actions" aria-expanded="false">';
								echo '<i class="fas fa-ellipsis-v"></i>';
								echo '</button>';

								echo '<div class="actions-menu-list">';

								echo '<a href="PPE/view_ppe_items.php?id=' . (int)$row['ppe_id'] . '&return=' . urlencode($pcReturnUrl) . '" class="view-action">';
								echo '<i class="fas fa-eye"></i> View';
								echo '</a>';

								echo '<a href="PPE/PPE_PC_export.php?id=' . (int)$row['id'] . '" class="export-action">';
								echo '<i class="fas fa-download"></i> Export';
								echo '</a>';

								echo '<a href="PPE/PPE_PC.php?delete_id=' . (int)$row['id'] . '&return=' . urlencode($returnUrl) . '" class="delete-action" onclick="return confirm(\'Are you sure you want to delete this entry?\')">';
								echo '<i class="fas fa-trash"></i> Delete';
								echo '</a>';

								echo '</div>';
								echo '</div>';
								echo '</td>';
								echo '</tr>';
							}
						} else {
							echo '<tr><td colspan="9" class="text-center">No PPE history entries found.</td></tr>';
						}
					?>
				</tbody>
			</table>
		</div>
	</div>

	<script src="js/actions_menu.js"></script>

</body>
</html>
