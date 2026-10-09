<?php
// Each complete month has equal weight, including months with zero issuance.
function inventorySimpleMovingAverage(array $history, int $window = 3): ?int {
    if ($window < 1) throw new InvalidArgumentException('SMA window must be positive.');
    if (count($history) < $window) return null;
    return max(0, (int)round(array_sum(array_slice($history, -$window)) / $window));
}
// Forecast office supplies from RIS issuances only. Receipts are excluded.
function inventoryDemandForecast(mysqli $conn, int $months = 12, ?DateTimeImmutable $rangeStart = null, ?DateTimeImmutable $rangeEnd = null, bool $customRange = false): array {
    $timezone = new DateTimeZone('Asia/Manila');

    $currentMonth = new DateTimeImmutable('first day of this month 00:00:00', $timezone);
    $nextMonth = $currentMonth->modify('+1 month');

    if ($rangeStart !== null) {
        $start = $rangeStart->setTimezone($timezone)->modify('first day of this month');
    } else {
        $start = $currentMonth->modify('-' . $months . ' months');
    }

    if ($rangeEnd !== null) {
        $endMonth = $rangeEnd->setTimezone($timezone)->modify('first day of this month');
        $until = $endMonth->modify('+1 month')->format('Y-m-d');
    } else {
        $until = $nextMonth->format('Y-m-d');
    }

    $monthly = [];

    $lastMonth = $rangeEnd !== null
        ? $rangeEnd->setTimezone($timezone)->modify('first day of this month')
        : $currentMonth;

    $monthly = [];

    // Include the preceding three months for predictions at the start of the
    // selected range, then remove that context from the displayed series.
    $displayStart = $start->format('Y-m');
    $calculationStart = $start->modify('-3 months');
    for ($date = $calculationStart; $date <= $lastMonth; $date = $date->modify('+1 month')) {
        $monthly[$date->format('Y-m')] = 0;
    }

    // RIS is the authoritative issuance document for office supplies. Its linked
    // item_history rows describe the same event and must not be summed again.
    $sql = "SELECT DATE_FORMAT(r.date_requested, '%Y-%m') AS month, SUM(ri.issued_quantity) AS quantity
            FROM ris_items ri JOIN ris r ON r.ris_id = ri.ris_id
            WHERE r.date_requested >= ? AND r.date_requested < ? AND ri.issued_quantity > 0
            GROUP BY DATE_FORMAT(r.date_requested, '%Y-%m')";
    $statement = $conn->prepare($sql);

    if (!$statement) {
        throw new RuntimeException('Demand forecast query failed: ' . $conn->error);
    }
    $from = $calculationStart->format('Y-m-d');
    $statement->bind_param('ss', $from, $until);
    $statement->execute();
    foreach ($statement->get_result() as $row) {
        if (array_key_exists($row['month'], $monthly)) $monthly[$row['month']] += (int)$row['quantity'];
    }
    $statement->close();

    // Exclude only the current incomplete month; historical ranges retain their final month.
    $values = array_values($monthly);
    $historyValues = array_values(array_filter(
        $monthly,
        static fn($month) => $month < $currentMonth->format('Y-m'),
        ARRAY_FILTER_USE_KEY
    ));



    // Predict each target using only its preceding complete calendar months.
    $monthlyForecasts = [];
    foreach (array_keys($monthly) as $index => $month) {
        $monthlyForecasts[] = $month <= $currentMonth->format('Y-m')
            ? inventorySimpleMovingAverage(array_slice($values, 0, $index))
            : null;
    }
    $displayOffset = count(array_filter(array_keys($monthly), static fn($month) => $month < $displayStart));
    $displayMonths = array_slice(array_keys($monthly), $displayOffset);
    $values = array_slice($values, $displayOffset);
    $monthlyForecasts = array_slice($monthlyForecasts, $displayOffset);
    // Custom ranges return only actuals and SMA predictions for selected months.
    // No next-month projection, replenishment summary, or month-to-month comparisons.
    if ($customRange) {
        return [
            'months' => $displayMonths, 'actual' => $values,
            'monthlyForecasts' => $monthlyForecasts,
            'forecastMonth' => null, 'forecast' => null,
            'method' => 'Simple Moving Average (SMA)', 'window' => 3,
        ];
    }
    $forecast = inventorySimpleMovingAverage($historyValues);
    // Stock additions provide replenishment context only; they never enter demand.
    $receiptSql = "SELECT COALESCE(SUM(quantity_change), 0) AS quantity FROM item_history
        WHERE changed_at >= ? AND changed_at < ? AND quantity_change > 0
        AND change_type IN ('restock', 'entry', 'add')";
    $receiptStatement = $conn->prepare($receiptSql);

        if (!$receiptStatement) {
            throw new RuntimeException('Receipt query failed: ' . $conn->error);
        }
    $forecastDate = $lastMonth < $currentMonth ? $lastMonth->modify('+1 month') : $currentMonth;
    $previousStart = $forecastDate->modify('-1 month')->format('Y-m-d');
    $receiptUntil = $forecastDate->format('Y-m-d');
    $receiptStatement->bind_param('ss', $previousStart, $receiptUntil);
    $receiptStatement->execute();
    $receipts = (int)($receiptStatement->get_result()->fetch_assoc()['quantity'] ?? 0);
    $receiptStatement->close();

    // Previous completed month, used for the trend calculation.
    $previousMonth = $historyValues[count($historyValues) - 1] ?? 0;

    $displayHistory = array_values(array_filter($monthly,
        static fn($month) => $month >= $displayStart && $month < $currentMonth->format('Y-m'),
        ARRAY_FILTER_USE_KEY));
    $firstActive = 0;
    while ($firstActive < count($displayHistory) && $displayHistory[$firstActive] === 0) $firstActive++;
    $observed = array_slice($displayHistory, $firstActive);

    $average = $observed
        ? (int)round(array_sum($observed) / count($observed))
        : 0;

    $change = $forecast !== null && $previousMonth > 0
        ? round(($forecast - $previousMonth) / $previousMonth * 100, 1)
        : null;

    $trend = $change === null
        ? 'Unavailable'
        : ($change > 5
            ? 'Increasing'
            : ($change < -5 ? 'Decreasing' : 'Relatively Stable'));
    return [
        'months' => $displayMonths,
        'actual' => $values, 'monthlyForecasts' => $monthlyForecasts,
        'forecastMonth' => $forecastDate->format('Y-m'), 'forecast' => $forecast,
        'average' => $average, 'previous' => $previousMonth, 'receiptsPreviousMonth' => $receipts, 'changePercent' => $change,
        'trend' => $trend, 'method' => 'Simple Moving Average (SMA)', 'window' => 3
    ];
}
