<?php
function totpTable(mysqli $conn): void {
    $conn->query('CREATE TABLE IF NOT EXISTS user_totp (user_id INT PRIMARY KEY, secret VARCHAR(64) NOT NULL, enabled_at DATETIME NULL, last_step BIGINT NULL, recovery_hashes TEXT NULL) ENGINE=InnoDB');
}
function totpSecret(): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    // 24 Base32 characters provide 120 bits of secret material.
    $bytes = random_bytes(24);
    $out = '';
    for ($i = 0; $i < 24; $i++) $out .= $alphabet[ord($bytes[$i]) & 31];
    return $out;
}
function totpBytes(string $secret): string {
    $bits = '';
    foreach (str_split(strtoupper($secret)) as $char) {
        $n = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char);
        if ($n === false) return '';
        $bits .= str_pad(decbin($n), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function totpStep(string $secret, int $step): string {
    $mac = hash_hmac('sha1', pack('N2', 0, $step), totpBytes($secret), true);
    $offset = ord($mac[19]) & 15;
    $num = unpack('N', substr($mac, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
}
function totpVerify(string $secret, string $code, ?int $lastStep = null): ?int {
    if (!preg_match('/^[0-9]{6}$/', $code)) return null;
    $current = intdiv(time(), 30);
    foreach ([$current - 1, $current, $current + 1] as $step) {
        if (($lastStep === null || $step > $lastStep) && hash_equals(totpStep($secret, $step), $code)) return $step;
    }
    return null;
}
