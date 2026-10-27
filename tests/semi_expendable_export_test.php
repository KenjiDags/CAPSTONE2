<?php
// Exercise the real export body without authentication or database writes.
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../Semi_Expendables/semi_expendable_export.php');
$start = strpos($source, '<tbody>');
$end = strpos($source, '</tbody>', $start);
$template = substr($source, $start, $end + strlen('</tbody>') - $start);
$cases = [
    'receipt without history' => [[], '5', '', '', '125.00'],
    'issued without history' => [['quantity_issued' => 2], '', 'PROP-1', '2', ''],
    'reissued without history' => [['quantity_reissued' => 3], '', 'PROP-1', '3', ''],
    'disposed without history' => [['quantity_disposed' => 1], '', 'PROP-1', '1', ''],
    'nullable fields without history' => [['quantity_issued' => null, 'quantity_reissued' => null, 'quantity_disposed' => null], '5', '', '', '125.00'],
];
foreach ($cases as $name => [$changes, $receipt, $property, $issued, $total]) {
    $item = array_replace([
        'quantity' => 5, 'amount' => 25, 'amount_total' => 125,
        'quantity_balance' => 5, 'semi_expendable_property_no' => 'PROP-1',
    ], $changes);
    $hist_rows = [];
    ob_start();
    try {
        eval('?>' . $template);
        $html = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    preg_match('/<tr>(.*?)<\/tr>/s', $html, $row);
    preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row[1], $matches);
    $cells = array_map(fn($cell) => trim(strip_tags($cell)), $matches[1]);
    foreach ([2 => $receipt, 4 => $total, 5 => $property, 6 => $issued] as $column => $expected) {
        // The existing template prints zero for the snapshot's action quantity.
        if ($column === 6 && $expected === '') $expected = '0';
        if ($cells[$column] !== $expected) {
            throw new RuntimeException($name . ': unexpected value in column ' . $column);
        }
    }
    echo 'PASS: ' . $name . PHP_EOL;
}
restore_error_handler();