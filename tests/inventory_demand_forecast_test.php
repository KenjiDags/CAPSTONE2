<?php
require __DIR__ . '/../inventory_demand_forecast.php';
$cases = [
    [[], null], [[10, 20], null], [[10, 20, 30], 20],
    [[999, 10, 20, 30], 20], [[30, 0, 0], 10], [[0, 0, 0], 0], [[1, 2, 2], 2],
];
foreach ($cases as [$history, $expected]) {
    if (inventorySimpleMovingAverage($history) !== $expected) {
        throw new RuntimeException('Unexpected SMA for ' . json_encode($history));
    }
}
$actual = [10, 20, 30, 900];
if (inventorySimpleMovingAverage(array_slice($actual, 0, 3)) !== 20) {
    throw new RuntimeException('Target month leaked into forecast');
}
if (inventorySimpleMovingAverage([10, 20, 30], 2) !== 25) {
    throw new RuntimeException('Window ignored');
}
try {
    inventorySimpleMovingAverage([10], 0);
    throw new RuntimeException('Invalid window accepted');
} catch (InvalidArgumentException $expected) {
}
echo "Inventory SMA checks passed.\n";
