<?php
require __DIR__ . '/../PPE/navigation.php';
$expected = 'PPE/PPE_PC.php?search=Printer&sort=property_no';
if (ppeReturnTarget($expected) !== $expected) throw new RuntimeException('Property card filters lost');
foreach (['https://example.com/PPE/PPE_PC.php', '//example.com/PPE/PPE_PC.php', '../PPE/PPE_PC.php', ['invalid']] as $invalid) {
    if (ppeReturnTarget($invalid) !== 'PPE/PPE.php') throw new RuntimeException('Invalid return target accepted');
}
$view = file_get_contents(__DIR__ . '/../PPE/view_ppe_items.php');
if (!str_contains($view, 'urlencode($ppeReturnUrl)')) throw new RuntimeException('Edit loses return context');
$list = file_get_contents(__DIR__ . '/../PPE/PPE_PC.php');
if (substr_count($list, "urlencode(\$pcReturnUrl)") !== 2) throw new RuntimeException('Row and View links must preserve return context');
echo "PPE Property Cards return navigation checks passed.\n";