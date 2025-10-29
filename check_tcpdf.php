<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
echo "Checking TCPDF installation:\n";
echo "Full path being checked: " . $tcpdfPath . "\n";
echo "Directory exists: " . (is_dir(__DIR__ . '/tcpdf') ? 'Yes' : 'No') . "\n";
echo "File exists: " . (file_exists($tcpdfPath) ? 'Yes' : 'No') . "\n";
echo "Parent directory is writable: " . (is_writable(__DIR__) ? 'Yes' : 'No') . "\n";