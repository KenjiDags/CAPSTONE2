<?php
// Ensure no output has been sent yet
if (ob_get_level()) ob_end_clean();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define TCPDF path using __DIR__
$tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';

// Check TCPDF installation
if (!file_exists($tcpdfPath)) {
    die('TCPDF not found at: ' . $tcpdfPath . '. Please install TCPDF in the tcpdf directory.');
}

// Include TCPDF
require_once($tcpdfPath);

require 'config.php';
require_once 'functions.php';

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify TCPDF is available
if (!class_exists('TCPDF')) {
    die('TCPDF class not found. Please ensure Composer dependencies are installed correctly.');
}

// Get category filter if provided
$selected_category = isset($_POST['category']) ? trim($_POST['category']) : '';
if ($selected_category === 'All') { $selected_category = ''; }

// Fetch data using the same logic as the main page
$rows = [];
$has_history = false;

try {
    $chk = $conn->query("SHOW TABLES LIKE 'semi_expendable_history'");
    $has_history = $chk && $chk->num_rows > 0;
    if ($chk) { $chk->close(); }
} catch (Throwable $e) { $has_history = false; }

if ($has_history) {
    $sql = "
        SELECT 
            h.date,
            h.ics_rrsp_no,
            sep.semi_expendable_property_no AS property_no,
            COALESCE(NULLIF(sep.remarks, ''), sep.item_description) AS item_description,
            COALESCE(sep.estimated_useful_life, '') AS useful_life,
            h.quantity_issued AS issued_qty,
            h.office_officer_issued AS issued_office,
            h.quantity_returned AS returned_qty,
            h.office_officer_returned AS returned_office,
            h.quantity_reissued AS reissued_qty,
            h.office_officer_reissued AS reissued_office,
            h.quantity_disposed AS disposed_qty1,
            0 AS disposed_qty2,
            h.quantity_balance AS balance_qty,
            COALESCE(h.amount_total, ROUND(COALESCE(sep.amount, h.amount) * h.quantity, 2)) AS amount_total,
            h.remarks
        FROM semi_expendable_history h
        INNER JOIN semi_expendable_property sep ON sep.id = h.semi_id
        WHERE 1=1";
    
    $binds = [];
    $types = '';
    if ($selected_category !== '' && columnExists($conn, 'semi_expendable_property', 'category')) {
        $sql .= " AND sep.category = ?";
        $binds[] = $selected_category;
        $types .= 's';
    }
    $sql .= " ORDER BY h.date DESC, h.id DESC";

    if (!empty($binds)) {
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($types, ...$binds);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) { $rows[] = $r; }
                $res->close();
            }
            $stmt->close();
        }
    } else {
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $res->close();
        }
    }
}

// Fallback: derive from ICS rows if no history rows found
if (!$has_history || count($rows) === 0) {
    $sql = "
        SELECT
            i.date_issued AS date,
            i.ics_no AS ics_rrsp_no,
            ii.stock_number AS property_no,
            COALESCE(NULLIF(sep.remarks, ''), sep.item_description, ii.description) AS item_description,
            COALESCE(sep.estimated_useful_life, ii.estimated_useful_life, '') AS useful_life,
            ii.quantity AS issued_qty,
            i.received_by AS issued_office,
            0 AS returned_qty,
            '' AS returned_office,
            0 AS reissued_qty,
            '' AS reissued_office,
            0 AS disposed_qty1,
            0 AS disposed_qty2,
            GREATEST(0, COALESCE(sep.quantity, 0) - (COALESCE(sep.quantity_issued, 0) + COALESCE(sep.quantity_reissued, 0) + COALESCE(sep.quantity_disposed, 0))) AS balance_qty,
            COALESCE(sep.amount, ii.unit_cost) * ii.quantity AS amount_total,
            'Derived from ICS' AS remarks
        FROM ics i
        INNER JOIN ics_items ii ON ii.ics_id = i.ics_id
        LEFT JOIN semi_expendable_property sep 
            ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
        WHERE ii.quantity > 0";
        
    $binds2 = [];
    $types2 = '';
    if ($selected_category !== '' && columnExists($conn, 'semi_expendable_property', 'category')) {
        $sql .= " AND sep.category = ?";
        $binds2[] = $selected_category;
        $types2 .= 's';
    }
    $sql .= " ORDER BY i.date_issued DESC, ii.id DESC";

    if (!empty($binds2)) {
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($types2, ...$binds2);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) { $rows[] = $r; }
                $res->close();
            }
            $stmt->close();
        }
    } else {
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $res->close();
        }
    }
}

// Generate PDF using TCPDF or similar library
require_once('tcpdf/tcpdf.php');

class REGSPI_PDF extends TCPDF {
    public function Header() {
        // No default header
    }
    
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0);
    }
}

// Create new PDF document (Landscape, mm, Legal)
$pdf = new REGSPI_PDF('L', 'mm', 'LEGAL', true, 'UTF-8', false);

// Set document properties
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('TESDA-CAR');
$pdf->SetTitle('Registry of Semi-Expendable Property Issued');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set document information
$pdf->SetCreator('TESDA Inventory System');
$pdf->SetAuthor('TESDA-CAR');
$pdf->SetTitle('Registry of Semi-Expendable Property Issued');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(10, 10, 10);

// Add a page (Landscape Legal)
$pdf->AddPage('L', 'LEGAL');

// Set font for title
$pdf->SetFont('helvetica', 'B', 12);

// Add logo if exists
if (file_exists('images/tesda_logo.png')) {
    $pdf->Image('images/tesda_logo.png', 15, 10, 20);
}

// Move to the right for title
$pdf->Cell(20); // Space after logo

// Title
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Registry of Semi-Expendable Property Issued', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'TESDA-CAR', 0, 1, 'C');
$pdf->Cell(0, 5, 'As of ' . date('F d, Y'), 0, 1, 'C');
$pdf->Ln(5);

// Table headers
$pdf->SetFont('helvetica', 'B', 8);
$headers = array(
    'Date',
    'ICS/RRSP No.',
    'Property No.',
    'Description',
    'Life',
    'Issued Qty',
    'Issued To',
    'Ret. Qty',
    'Ret. From',
    'Reissued Qty',
    'Reissued To',
    'Disp. Qty',
    'Balance',
    'Amount',
    'Remarks'
);

// Get page width excluding margins
$pageWidth = $pdf->getPageWidth() - 30; // 15mm margins on each side

// Calculate column widths as percentages of available width
$widths = array(
    'Date' => 0.08,           // 8%
    'ICS/RRSP No.' => 0.08,   // 8%
    'Property No.' => 0.1,     // 10%
    'Description' => 0.15,     // 15%
    'Life' => 0.05,           // 5%
    'Issued Qty' => 0.05,     // 5%
    'Issued To' => 0.08,      // 8%
    'Ret. Qty' => 0.05,       // 5%
    'Ret. From' => 0.08,      // 8%
    'Reissued Qty' => 0.05,   // 5%
    'Reissued To' => 0.08,    // 8%
    'Disp. Qty' => 0.05,      // 5%
    'Balance' => 0.05,        // 5%
    'Amount' => 0.07,         // 7%
    'Remarks' => 0.08         // 8%
);

// Convert percentages to actual widths
$w = array();
foreach ($widths as $col => $percentage) {
    $w[] = $pageWidth * $percentage;
}

// Print header with background color
$pdf->SetFillColor(51, 122, 183); // Bootstrap primary blue
$pdf->SetTextColor(255);  // White text
foreach($headers as $i => $header) {
    $pdf->Cell($w[$i], 8, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Reset text color to black for data
$pdf->SetTextColor(0);

// Print rows with alternate background
$pdf->SetFont('helvetica', '', 8);
$fill = false;
$total_amount = 0;

foreach($rows as $row) {
    // Set alternative row background
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
    
    // Handle potential date formatting errors
    $date = $row['date'] ? date('m/d/Y', strtotime($row['date'])) : '';
    
    // Print cells with proper alignment and overflow handling
    $pdf->Cell($w[0], 6, $date, 1, 0, 'C', $fill);
    $pdf->Cell($w[1], 6, $row['ics_rrsp_no'], 1, 0, 'L', $fill);
    $pdf->Cell($w[2], 6, $row['property_no'], 1, 0, 'L', $fill);
    
    // Multi-line description if needed
    $desc_height = $pdf->getStringHeight($w[3], $row['item_description']);
    $pdf->MultiCell($w[3], max(6, $desc_height), $row['item_description'], 1, 'L', $fill, 0);
    
    $pdf->Cell($w[4], 6, $row['useful_life'], 1, 0, 'C', $fill);
    $pdf->Cell($w[5], 6, number_format($row['issued_qty']), 1, 0, 'R', $fill);
    $pdf->Cell($w[6], 6, $row['issued_office'], 1, 0, 'L', $fill);
    $pdf->Cell($w[7], 6, number_format($row['returned_qty']), 1, 0, 'R', $fill);
    $pdf->Cell($w[8], 6, $row['returned_office'], 1, 0, 'L', $fill);
    $pdf->Cell($w[9], 6, number_format($row['reissued_qty']), 1, 0, 'R', $fill);
    $pdf->Cell($w[10], 6, $row['reissued_office'], 1, 0, 'L', $fill);
    $pdf->Cell($w[11], 6, number_format($row['disposed_qty1']), 1, 0, 'R', $fill);
    $pdf->Cell($w[12], 6, number_format($row['balance_qty']), 1, 0, 'R', $fill);
    $pdf->Cell($w[13], 6, '₱ ' . number_format($row['amount_total'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($w[14], 6, $row['remarks'], 1, 0, 'L', $fill);
    $pdf->Ln();
    
    $fill = !$fill; // Toggle fill for next row
    $total_amount += floatval($row['amount_total']);
}

// Print total row
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(array_sum(array_slice($w, 0, 13)), 6, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell($w[13], 6, '₱ ' . number_format($total_amount, 2), 1, 0, 'R', true);
$pdf->Cell($w[14], 6, '', 1, 0, 'L', true);
$pdf->Ln();

// Add signature fields at the bottom
$pdf->Ln(20);
$pdf->SetFont('helvetica', '', 10);

// Prepared by
$pdf->Cell(120, 5, 'Prepared by:', 0, 0, 'L');
// Certified Correct by
$pdf->Cell(120, 5, 'Certified Correct:', 0, 0, 'L');
// Approved by
$pdf->Cell(120, 5, 'Approved by:', 0, 1, 'L');

$pdf->Ln(15);

$pdf->SetFont('helvetica', 'B', 10);
// Add name lines
$pdf->Cell(120, 5, '___________________________', 0, 0, 'L');
$pdf->Cell(120, 5, '___________________________', 0, 0, 'L');
$pdf->Cell(120, 5, '___________________________', 0, 1, 'L');

// Add position/title lines
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(120, 5, 'Property Officer', 0, 0, 'L');
$pdf->Cell(120, 5, 'Supply Officer', 0, 0, 'L');
$pdf->Cell(120, 5, 'Head of Office', 0, 1, 'L');

// Clean any output buffers
while (ob_get_level()) ob_end_clean();

// Output the PDF
try {
    $pdf->Output('RegSPI_Export_' . date('Y-m-d') . '.pdf', 'D');
} catch (Exception $e) {
    // Log error and display user-friendly message
    error_log('PDF Generation Error: ' . $e->getMessage());
    echo 'Error generating PDF. Please try again or contact support.';
    exit;
}