<?php
echo "TCPDF Installer Script\n";
echo "====================\n\n";

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$tcpdfVersion = '6.6.2';
$downloadUrl = "https://github.com/tecnickcom/TCPDF/archive/refs/tags/{$tcpdfVersion}.zip";
$projectRoot = __DIR__;
$tcpdfDir = $projectRoot . DIRECTORY_SEPARATOR . 'tcpdf';
$tempZip = $projectRoot . DIRECTORY_SEPARATOR . 'tcpdf_temp.zip';

// Function to show status messages
function showStatus($message, $isError = false) {
    echo ($isError ? "❌ " : "✅ ") . $message . "\n";
}

// Check requirements
if (!extension_loaded('zip')) {
    showStatus("PHP ZIP extension is required but not installed.", true);
    exit(1);
}

if (!function_exists('curl_init')) {
    showStatus("PHP cURL extension is required but not installed.", true);
    exit(1);
}

// Create tcpdf directory if it doesn't exist
if (!file_exists($tcpdfDir)) {
    if (!mkdir($tcpdfDir, 0755, true)) {
        showStatus("Failed to create directory: {$tcpdfDir}", true);
        exit(1);
    }
    showStatus("Created TCPDF directory");
} else {
    showStatus("TCPDF directory already exists");
}

// Download TCPDF
echo "\nDownloading TCPDF {$tcpdfVersion}...\n";
$ch = curl_init($downloadUrl);
$fp = fopen($tempZip, 'wb');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
if (curl_exec($ch)) {
    showStatus("Downloaded TCPDF successfully");
} else {
    showStatus("Failed to download TCPDF: " . curl_error($ch), true);
    fclose($fp);
    curl_close($ch);
    exit(1);
}
fclose($fp);
curl_close($ch);

// Extract files
echo "\nExtracting files...\n";
$zip = new ZipArchive;
if ($zip->open($tempZip) === TRUE) {
    // First, get the name of the root directory in the zip
    $rootDir = $zip->getNameIndex(0);
    
    // Extract to temporary location
    $tempExtractPath = $projectRoot . DIRECTORY_SEPARATOR . 'tcpdf_temp_extract';
    $zip->extractTo($tempExtractPath);
    $zip->close();
    
    // Move files from the extracted directory to tcpdf directory
    $extractedDir = $tempExtractPath . DIRECTORY_SEPARATOR . rtrim($rootDir, '/');
    $success = true;
    
    // Copy all files from extracted directory to tcpdf directory
    function copyDirectory($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    
    // Clear existing tcpdf directory
    if (is_dir($tcpdfDir)) {
        function removeDirectory($dir) {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir . "/" . $object)) {
                            removeDirectory($dir . "/" . $object);
                        } else {
                            unlink($dir . "/" . $object);
                        }
                    }
                }
                rmdir($dir);
            }
        }
        removeDirectory($tcpdfDir);
        mkdir($tcpdfDir);
    }
    
    // Copy files
    copyDirectory($extractedDir, $tcpdfDir);
    
    // Clean up
    removeDirectory($tempExtractPath);
    unlink($tempZip);
    
    showStatus("Extracted TCPDF successfully");
} else {
    showStatus("Failed to extract TCPDF", true);
    exit(1);
}

// Verify installation
echo "\nVerifying installation...\n";
$requiredFiles = [
    'tcpdf.php',
    'config/tcpdf_config.php',
    'fonts/helvetica.php'
];

$allFilesExist = true;
foreach ($requiredFiles as $file) {
    $fullPath = $tcpdfDir . DIRECTORY_SEPARATOR . $file;
    if (file_exists($fullPath)) {
        showStatus("Found: {$file}");
    } else {
        showStatus("Missing: {$file}", true);
        $allFilesExist = false;
    }
}

if ($allFilesExist) {
    echo "\n✨ TCPDF was successfully installed!\n";
    echo "\nYou can now use TCPDF in your project. Test the installation by visiting:\n";
    echo "http://localhost/Inventory-Project/test_tcpdf.php\n\n";
} else {
    echo "\n❌ Installation verification failed. Please check the errors above.\n";
}

// Create a simple test file
$testFile = <<<'PHP'
<?php
require_once(__DIR__ . '/tcpdf/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('TCPDF Installation Test');
$pdf->SetAuthor('TESDA Inventory System');
$pdf->SetTitle('TCPDF Installation Test');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 16);

// Add content
$pdf->Cell(0, 10, 'TCPDF is working!', 0, 1, 'C');
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');

// Output the PDF
$pdf->Output('tcpdf_test.pdf', 'I');
PHP;

file_put_contents($projectRoot . DIRECTORY_SEPARATOR . 'test_tcpdf.php', $testFile);
showStatus("Created test file: test_tcpdf.php");
?>