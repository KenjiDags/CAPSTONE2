<?php
// Aggregate recorded issues across inventory categories. Receipts are deliberately excluded.
function inventoryDemandForecast(mysqli $conn, int $months = 12): array {
    $timezone = new DateTimeZone('Asia/Manila');
    $currentMonth = new DateTimeImmutable('first day of this month', $timezone);
    $start = $currentMonth->modify('-' . $months . ' months');
    $monthly = [];
    for ($date = $start; $date < $currentMonth; $date = $date->modify('+1 month')) {
        $monthly[$date->format('Y-m')] = 0;
    }

    // RIS is the authoritative issuance document for office supplies. Its linked
    // item_history rows describe the same event and must not be summed again.
    $sql = "SELECT DATE_FORMAT(r.date_requested, '%Y-%m') AS month, SUM(ri.issued_quantity) AS quantity
            FROM ris_items ri JOIN ris r ON r.ris_id = ri.ris_id
            WHERE r.date_requested >= ? AND r.date_requested < ? AND ri.issued_quantity > 0
            GROUP BY DATE_FORMAT(r.date_requested, '%Y-%m')";
    $statement = $conn->prepare($sql);
    $from = $start->format('Y-m-d');
    $until = $currentMonth->format('Y-m-d');
    $statement->bind_param('ss', $from, $until);
    $statement->execute();
    foreach ($statement->get_result() as $row) {
        if (array_key_exists($row['month'], $monthly)) $monthly[$row['month']] += (int)$row['quantity'];
    }
    $statement->close();

    // Stock additions provide replenishment context only; they never enter demand.
    $receiptSql = "SELECT COALESCE(SUM(quantity_change), 0) AS quantity FROM item_history
        WHERE changed_at >= ? AND changed_at < ? AND quantity_change > 0
        AND change_type IN ('restock', 'entry', 'add')";
    $receiptStatement = $conn->prepare($receiptSql);
    $previousStart = $currentMonth->modify('-1 month')->format('Y-m-d');
    $receiptStatement->bind_param('ss', $previousStart, $until);
    $receiptStatement->execute();
    $receipts = (int)($receiptStatement->get_result()->fetch_assoc()['quantity'] ?? 0);
    $receiptStatement->close();

    // Semi-expendable history stores cumulative snapshots, sometimes twice for
    // one action. Count only positive changes in issued and reissued totals.
    $table = $conn->query("SHOW TABLES LIKE 'semi_expendable_history'");
    if ($table && $table->num_rows) {
        $history = $conn->query("SELECT semi_id, created_at, quantity_issued, quantity_reissued
            FROM semi_expendable_history WHERE created_at < '" . $conn->real_escape_string($until) . "'
            ORDER BY semi_id, created_at, id");
        $previous = [];
        while ($row = $history->fetch_assoc()) {
            $id = (int)$row['semi_id'];
            $issued = max(0, (int)$row['quantity_issued']);
            $reissued = max(0, (int)$row['quantity_reissued']);
            $prior = $previous[$id] ?? [0, 0];
            $change = max(0, $issued - $prior[0]) + max(0, $reissued - $prior[1]);
            $previous[$id] = [$issued, $reissued];
            $month = substr((string)$row['created_at'], 0, 7);
            if (array_key_exists($month, $monthly)) $monthly[$month] += $change;
        }
    }

    $values = array_values($monthly);
    $activeMonths = count(array_filter($values, static fn($value) => $value > 0));
    $forecast = null;
    $recent = array_slice($values, -3);
    if ($activeMonths >= 3 && count(array_filter($recent, static fn($value) => $value > 0)) >= 2) {
        $forecast = max(0, (int)round(array_sum($recent) / 3));
    }
    $previousMonth = $values[count($values) - 1];
    $firstActive = 0;
    while ($firstActive < count($values) && $values[$firstActive] === 0) $firstActive++;
    $observed = array_slice($values, $firstActive);
    $average = $observed ? (int)round(array_sum($observed) / count($observed)) : 0;
    $change = $forecast !== null && $previousMonth > 0
        ? round(($forecast - $previousMonth) / $previousMonth * 100, 1) : null;
    $trend = $change === null ? 'Unavailable' : ($change > 5 ? 'Increasing' : ($change < -5 ? 'Decreasing' : 'Relatively Stable'));
    return [
        'months' => array_keys($monthly), 'actual' => $values,
        'forecastMonth' => $currentMonth->format('Y-m'), 'forecast' => $forecast,
        'average' => $average, 'previous' => $previousMonth, 'receiptsPreviousMonth' => $receipts, 'changePercent' => $change,
        'trend' => $trend, 'method' => 'Three-month moving average'
    ];
}
