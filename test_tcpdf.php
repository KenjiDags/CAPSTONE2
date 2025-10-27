<?php
// Test TCPDF installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define TCPDF path
define('TCPDF_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tcpdf' . DIRECTORY_SEPARATOR);

// Check if TCPDF directory exists
if (!is_dir(TCPDF_PATH)) {
    die('TCPDF directory not found at: ' . TCPDF_PATH);
}

// Check if main TCPDF file exists
if (!file_exists(TCPDF_PATH . 'tcpdf.php')) {
    die('tcpdf.php not found in: ' . TCPDF_PATH);
}

// Try to include TCPDF
require_once(TCPDF_PATH . 'tcpdf.php');

// Try to create a PDF
try {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('TCPDF Test');
    $pdf->SetAuthor('Test Author');
    $pdf->SetTitle('TCPDF Test Document');

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 16);

    // Add content
    $pdf->Cell(0, 10, 'TCPDF is working correctly!', 0, 1, 'C');

    // Output PDF
    $pdf->Output('test.pdf', 'D');
    
} catch (Exception $e) {
    die('Error creating PDF: ' . $e->getMessage());
}
?>