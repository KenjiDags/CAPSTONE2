<?php
require 'config.php';
require_once 'functions.php';

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="RegSPI_Export_' . date('Y-m-d') . '.pdf"');

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

// Create new PDF document
$pdf = new TCPDF('L', PDF_UNIT, 'LEGAL', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('TESDA Inventory System');
$pdf->SetAuthor('TESDA-CAR');
$pdf->SetTitle('Registry of Semi-Expendable Property Issued');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(10, 10, 10);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 8);

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

// Calculate column widths (adjust as needed)
$w = array(20, 25, 30, 50, 15, 15, 25, 15, 25, 15, 25, 15, 15, 20, 30);

// Print header
foreach($headers as $i => $header) {
    $pdf->Cell($w[$i], 7, $header, 1, 0, 'C');
}
$pdf->Ln();

// Print rows
$pdf->SetFont('helvetica', '', 8);
foreach($rows as $row) {
    $pdf->Cell($w[0], 6, date('m/d/Y', strtotime($row['date'])), 1);
    $pdf->Cell($w[1], 6, $row['ics_rrsp_no'], 1);
    $pdf->Cell($w[2], 6, $row['property_no'], 1);
    $pdf->Cell($w[3], 6, $row['item_description'], 1);
    $pdf->Cell($w[4], 6, $row['useful_life'], 1);
    $pdf->Cell($w[5], 6, $row['issued_qty'], 1, 0, 'R');
    $pdf->Cell($w[6], 6, $row['issued_office'], 1);
    $pdf->Cell($w[7], 6, $row['returned_qty'], 1, 0, 'R');
    $pdf->Cell($w[8], 6, $row['returned_office'], 1);
    $pdf->Cell($w[9], 6, $row['reissued_qty'], 1, 0, 'R');
    $pdf->Cell($w[10], 6, $row['reissued_office'], 1);
    $pdf->Cell($w[11], 6, $row['disposed_qty1'], 1, 0, 'R');
    $pdf->Cell($w[12], 6, $row['balance_qty'], 1, 0, 'R');
    $pdf->Cell($w[13], 6, number_format($row['amount_total'], 2), 1, 0, 'R');
    $pdf->Cell($w[14], 6, $row['remarks'], 1);
    $pdf->Ln();
}

// Output the PDF
$pdf->Output('RegSPI_Export_' . date('Y-m-d') . '.pdf', 'D');