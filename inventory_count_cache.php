<?php
function inventoryCountCachePath(): string {
    return sys_get_temp_dir() . '/tesda-stock-count-' . hash('sha256', __DIR__) . '.json';
}

function cachedInventoryCount(callable $query, ?string $path = null): int {
    $file = @fopen($path ?? inventoryCountCachePath(), 'c+');
    if (!$file) return (int)$query();
    try {
        if (!flock($file, LOCK_EX)) return (int)$query();
        $cached = json_decode(stream_get_contents($file), true);
        if (is_array($cached) && isset($cached['expires'], $cached['count'])
            && $cached['expires'] > time() && is_int($cached['count']) && $cached['count'] >= 0) {
            return $cached['count'];
        }
        $count = (int)$query();
        rewind($file);
        ftruncate($file, 0);
        fwrite($file, json_encode(['count' => $count, 'expires' => time() + 5]));
        fflush($file);
        return $count;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function invalidateInventoryCount(?string $path = null): void {
    $file = @fopen($path ?? inventoryCountCachePath(), 'c+');
    if (!$file) return;
    try {
        if (flock($file, LOCK_EX)) {
            ftruncate($file, 0);
            fflush($file);
        }
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function invalidateInventoryCountAfterWrite(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        register_shutdown_function('invalidateInventoryCount');
    }
}
