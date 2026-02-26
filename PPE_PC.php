<?php
require 'auth.php';
require_once 'config.php';

// DELETE LOGIC (moved from pc_delete.php)
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
	$id = (int)$_GET['delete_id'];
	// Delete the entry from item_history_ppe
	$conn->query("DELETE FROM item_history_ppe WHERE id = $id");
	// Redirect back to the return URL or PPE_PC.php
	$return = isset($_GET['return']) ? $_GET['return'] : 'PPE_PC.php';
	header('Location: ' . $return);
	exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Property Card (PPE)</title>
	<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
	<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
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
		.actions-col { width: 300px; white-space: nowrap; }
		table th.actions-col, table td.actions-col { padding-left: 12px; padding-right: 12px; }
		.action-stack { display: inline-flex; flex-direction: row; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: center; }
		.amount-col { white-space: nowrap; min-width: 100px; }
	</style>
</head>
<body>
	<?php include 'sidebar.php'; ?>
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
			<a href="PPE_PC_export_all.php?export_all=1"
				class="pill-btn pill-export" style="margin-left:auto; border-radius: 8px !important;">
					<i class="fas fa-file-export"></i> Export All
			</a>
		</form>

		<div class="table-wrapper">
			<table  style="margin-bottom: 0px !important;">
				<thead>
					<tr>
						<th>Description</th>
						<th>Property Number</th>
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
						// Only show latest entry per property_no
						$sql = "SELECT h.* FROM item_history_ppe h INNER JOIN (
							SELECT property_no, MAX(changed_at) AS max_changed_at
							FROM item_history_ppe
							GROUP BY property_no
						) latest ON h.property_no = latest.property_no AND h.changed_at = latest.max_changed_at";
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
								// Format to show only date (YYYY-MM-DD)
								$displayDate = '';
								if ($txDate) {
									$displayDate = date('Y-m-d', strtotime($txDate));
								}
								$referenceNo = $row['PAR_number'] ?? $row['refference_no'] ?? '';
								$receiptQty = (int)($row['receipt_qty'] ?? 0);
								$issueQty = (int)($row['issue_qty'] ?? 0);
								$balanceQty = (int)($row['balance_qty'] ?? 0);
								$amount = (float)($row['unit_cost'] ?? 0) * (int)($row['quantity_on_hand'] ?? 0);
								echo '<tr>';
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
								$returnUrl = 'PPE_PC.php';
								$qs = [];
								if ($search !== '') { $qs['search'] = $search; }
								if (!empty($qs)) { $returnUrl .= '?' . http_build_query($qs); }
								echo '<td class="text-center actions-col">';
								echo '<div class="action-stack">';
								echo '<a href="PPE_PC_export.php?id=' . (int)$row['id'] . '" class="pill-btn pill-export"><i class="fas fa-download"></i> Export</a>';
								echo '<a href="PPE_PC.php?delete_id=' . (int)$row['id'] . '&return=' . urlencode($returnUrl) . '" class="pill-btn pill-delete" onclick="return confirm(\'Are you sure you want to delete this entry?\')"><i class="fas fa-trash"></i> Delete</a>';
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
</body>
</html>
