<?php
function ppeReturnTarget($value): string {
    if (!is_string($value)) return 'PPE/PPE.php';
    $parts = parse_url($value);
    if (!$parts || isset($parts['scheme']) || isset($parts['host']) || !in_array($parts['path'] ?? '', ['PPE/PPE_PC.php', 'PPE/PPE.php'], true)) return 'PPE/PPE.php';
    parse_str($parts['query'] ?? '', $query);
    $filters = [];
    foreach (['search', 'sort'] as $key) if (isset($query[$key]) && is_string($query[$key])) $filters[$key] = $query[$key];
    return $parts['path'] . ($filters ? '?' . http_build_query($filters) : '');
}