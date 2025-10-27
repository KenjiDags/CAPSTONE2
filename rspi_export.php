<?php
require 'config.php';
require_once 'functions.php';
require_once('tcpdf/tcpdf.php');

if (!isset($_GET['id'])) {
    die("❌ Error: RSPI record not found.");
}

$id = (int)$_GET['id'];
if ($id <= 0) {
    die("❌ Invalid RSPI ID.");
}

// Fetch RSPI record
$stmt = $conn->prepare("
    SELECT r.*, COUNT(i.id) as item_count 
    FROM rspi_reports r
    LEFT JOIN rspi_items i ON r.id = i.rspi_id
    WHERE r.id = ?
    GROUP BY r.id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die("❌ No record found for RSPI ID $id.");
}
$rspi = $result->fetch_assoc();
$stmt->close();

// Fetch RSPI items
$stmt = $conn->prepare("
    SELECT * FROM rspi_items 
    WHERE rspi_id = ?
    ORDER BY id ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Extend TCPDF to create custom header/footer
class RSPIPDF extends TCPDF {
    public function Header() {
        // Logo
        if (file_exists('images/logo.png')) {
            $this->Image('images/logo.png', 15, 10, 20);
        }
        
        // Header text
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 5, 'Republic of the Philippines', 0, 1, 'C');
        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 5, 'TESDA Regional Training Center - Iligan', 0, 1, 'C');
        $this->Cell(0, 5, 'Typed - Iligan City', 0, 1, 'C');
        
        // Report title
        $this->Ln(5);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'REPORT OF SEMI-EXPENDABLE PROPERTY ISSUED', 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 5, '(RSPI)', 0, 1, 'C');
        
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Create new PDF document
$pdf = new RSPIPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Inventory Management System');
$pdf->SetAuthor('TESDA RTC Iligan');
$pdf->SetTitle('RSPI Report - ' . $rspi['serial_no']);

// Set margins
$pdf->SetMargins(15, 45, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Report details
$pdf->SetFont('helvetica', '', 10);

// RSPI Information table
$pdf->Cell(40, 7, 'RSPI No:', 0, 0);
$pdf->Cell(60, 7, $rspi['serial_no'], 0, 0);
$pdf->Cell(30, 7, 'Date:', 0, 0);
$pdf->Cell(60, 7, date('F d, Y', strtotime($rspi['report_date'])), 0, 1);

$pdf->Cell(40, 7, 'Entity Name:', 0, 0);
$pdf->Cell(60, 7, $rspi['entity_name'], 0, 0);
$pdf->Cell(30, 7, 'Fund Cluster:', 0, 0);
$pdf->Cell(60, 7, $rspi['fund_cluster'], 0, 1);

$pdf->Ln(5);

// Items table header
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(25, 7, 'ICS No.', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'Property No.', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'Description', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Unit', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Quantity', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Unit Cost', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Amount', 1, 1, 'C', true);

// Items table content
$pdf->SetFont('helvetica', '', 9);
if (count($items) > 0) {
    $total_amount = 0;
    foreach ($items as $item) {
        $pdf->Cell(25, 6, $item['ics_no'], 1, 0, 'C');
        $pdf->Cell(30, 6, $item['property_no'], 1, 0, 'C');
        $pdf->Cell(45, 6, $item['item_description'], 1, 0, 'L');
        $pdf->Cell(20, 6, $item['unit'], 1, 0, 'C');
        $pdf->Cell(20, 6, number_format($item['quantity_issued']), 1, 0, 'R');
        $pdf->Cell(25, 6, number_format($item['unit_cost'], 2), 1, 0, 'R');
        $pdf->Cell(25, 6, number_format($item['amount'], 2), 1, 1, 'R');
        $total_amount += $item['amount'];
    }
    
    // Total row
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(140, 7, 'TOTAL', 1, 0, 'R');
    $pdf->Cell(25, 7, number_format($total_amount, 2), 1, 1, 'R');
} else {
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(190, 10, 'No items available for this RSPI', 1, 1, 'C');
}

$pdf->Ln(15);

// Certification and signatures
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'I hereby certify to the correctness of the above information.', 0, 1, 'L');

$pdf->Ln(15);

// Signature lines
$pdf->Cell(95, 5, 'Certified by:', 0, 0, 'C');
$pdf->Cell(95, 5, 'Posted by:', 0, 1, 'C');

$pdf->Ln(15);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(95, 5, strtoupper($rspi['custodian_name']), 0, 0, 'C');
$pdf->Cell(95, 5, strtoupper($rspi['posted_by']), 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(95, 5, 'Property and/or Supply Custodian', 0, 0, 'C');
$pdf->Cell(95, 5, 'Designated Accounting Staff', 0, 1, 'C');

// Output PDF
$pdf->Output('RSPI_' . $rspi['serial_no'] . '.pdf', 'I');