<?php
require __DIR__ . '/../mrp_dates.php';
$cases = [
    ['2026-09-19', '2026-09-19', 0],
    ['2026-09-10', '2026-09-19', 9],
    ['2026-09-20', '2026-09-19', 0],
    [null, '2026-09-19', null],
    ['2026-02-30', '2026-09-19', null],
    ['2024-02-28', '2024-03-01', 2],
];
foreach ($cases as [$start, $today, $expected]) {
    if (mrpDaysOutOfStock($start, $today) !== $expected) throw new RuntimeException('Stockout date calculation failed');
}
if (mrpExpectedResolution('2026-12-30', 4) !== '2027-01-03') throw new RuntimeException('Lead time rollover failed');
if (mrpExpectedResolution('2026-09-19', null) !== null) throw new RuntimeException('Missing lead time must stay unknown');
echo "PHP MRP date checks passed.\n";
