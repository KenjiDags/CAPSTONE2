<?php
require 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('Inventory Management System')
    ->setLastModifiedBy('TESDA RTC Iligan')
    ->setTitle('RSPI Records Export')
    ->setSubject('RSPI Complete List')
    ->setDescription('Export of all RSPI records with items');

// Add header row
$headers = [
    'A1' => 'RSPI No.',
    'B1' => 'ICS No.',
    'C1' => 'Property No.',
    'D1' => 'Item Description',
    'E1' => 'Unit',
    'F1' => 'Quantity',
    'G1' => 'Unit Cost',
    'H1' => 'Total Amount',
    'I1' => 'Entity Name',
    'J1' => 'Fund Cluster',
    'K1' => 'Date',
    'L1' => 'Remarks'
];

// Style the header row
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

// Set headers and apply styles
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

// Fetch all RSPI records with their items
$query = "
    SELECT 
        r.serial_no,
        r.entity_name,
        r.fund_cluster,
        r.report_date,
        i.ics_no,
        i.property_no,
        i.item_description,
        i.unit,
        i.quantity_issued,
        i.unit_cost,
        i.amount,
        CONCAT('Posted by: ', r.posted_by) as remarks
    FROM rspi_reports r
    LEFT JOIN rspi_items i ON r.id = i.rspi_id
    ORDER BY r.report_date DESC, r.serial_no ASC
";

$result = $conn->query($query);
$row = 2; // Start from row 2 (after headers)

// Data style
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

// Number format style
$numberStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_RIGHT
    ],
    'numberFormat' => [
        'formatCode' => '#,##0.00'
    ]
];

while ($record = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $record['serial_no']);
    $sheet->setCellValue('B' . $row, $record['ics_no']);
    $sheet->setCellValue('C' . $row, $record['property_no']);
    $sheet->setCellValue('D' . $row, $record['item_description']);
    $sheet->setCellValue('E' . $row, $record['unit']);
    $sheet->setCellValue('F' . $row, $record['quantity_issued']);
    $sheet->setCellValue('G' . $row, $record['unit_cost']);
    $sheet->setCellValue('H' . $row, $record['amount']);
    $sheet->setCellValue('I' . $row, $record['entity_name']);
    $sheet->setCellValue('J' . $row, $record['fund_cluster']);
    $sheet->setCellValue('K' . $row, date('m/d/Y', strtotime($record['report_date'])));
    $sheet->setCellValue('L' . $row, $record['remarks']);
    
    // Apply number formatting to numeric columns
    $sheet->getStyle('F' . $row)->applyFromArray($numberStyle);
    $sheet->getStyle('G' . $row . ':H' . $row)->applyFromArray($numberStyle);
    
    $row++;
}

// Auto-size columns
foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Apply borders to all data
$sheet->getStyle('A2:L' . ($row - 1))->applyFromArray($dataStyle);

// Freeze pane
$sheet->freezePane('A2');

// Set title row
$sheet->insertNewRowBefore(1);
$sheet->mergeCells('A1:L1');
$sheet->setCellValue('A1', 'REPORT OF SEMI-EXPENDABLE PROPERTY ISSUED (RSPI) - COMPLETE LIST');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Create Excel file
$writer = new Xlsx($spreadsheet);

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="RSPI_Complete_List_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Output file to browser
$writer->save('php://output');