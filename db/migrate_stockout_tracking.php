<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../config.php';
require __DIR__ . '/stockout_tracking.php';
installStockoutTracking($conn);
echo "Stockout tracking installed; existing dates preserved.\n";
