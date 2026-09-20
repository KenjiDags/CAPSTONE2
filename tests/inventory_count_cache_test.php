<?php
require __DIR__ . '/../inventory_count_cache.php';
$path = tempnam(sys_get_temp_dir(), 'stock-count-test-');
$calls = 0;
$quantity = 5;
$query = static function () use (&$calls, &$quantity): int { $calls++; return $quantity; };
function checkCount($actual, $expected) {
    if ($actual !== $expected) throw new RuntimeException("Expected $expected, got $actual");
}
try {
    checkCount(cachedInventoryCount($query, $path), 5);
    $quantity = 0;
    checkCount(cachedInventoryCount($query, $path), 5);
    checkCount($calls, 1);
    invalidateInventoryCount($path);
    checkCount(cachedInventoryCount($query, $path), 0);
    checkCount(cachedInventoryCount($query, $path), 0);
    checkCount($calls, 2);
    file_put_contents($path, json_encode(['count' => 99, 'expires' => time() - 1]));
    checkCount(cachedInventoryCount($query, $path), 0);
    checkCount($calls, 3);
    file_put_contents($path, 'invalid json');
    checkCount(cachedInventoryCount($query, $path), 0);
    echo "Count cache hit, zero, invalidation, expiry, and recovery checks passed.\n";
} finally { unlink($path); }
