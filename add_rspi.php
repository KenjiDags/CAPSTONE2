<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Generate RSPI serial number (YYYY-MM-0000 format)
$year = date('Y');
$month = date('m');
$sql = "SELECT MAX(CAST(SUBSTRING_INDEX(serial_no, '-', -1) AS UNSIGNED)) as max_serial 
        FROM rspi_reports 
        WHERE serial_no LIKE '$year-$month-%'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$next_serial = str_pad(($row['max_serial'] + 1), 4, '0', STR_PAD_LEFT);
$serial_no = "$year-$month-$next_serial";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report of Semi-Expendable Property Issued (RSPI)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .signature-section {
            margin-top: 2rem;
            border-top: 1px solid #dee2e6;
            padding-top: 1rem;
        }
        .signature-box {
            border-top: 1px solid #000;
            margin-top: 2rem;
            padding-top: 0.5rem;
            text-align: center;
            width: 300px;
        }
        .table th {
            text-align: center;
            vertical-align: middle;
        }
        .amount-column {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="form-header">
            <h6 class="text-end">Annex A.7</h6>
            <h4>REPORT OF SEMI-EXPENDABLE PROPERTY ISSUED</h4>
            <h5>(RSPI)</h5>
        </div>

        <form id="rspiForm" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="entityName" class="form-label">Entity Name:</label>
                        <input type="text" class="form-control" id="entityName" name="entityName" required>
                    </div>
                    <div class="mb-3">
                        <label for="fundCluster" class="form-label">Fund Cluster:</label>
                        <input type="text" class="form-control" id="fundCluster" name="fundCluster" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="serialNo" class="form-label">Serial No.:</label>
                        <input type="text" class="form-control" id="serialNo" name="serialNo" value="<?php echo $serial_no; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="date" class="form-label">Date:</label>
                        <input type="date" class="form-control" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="rspiTable">
                    <thead>
                        <tr>
                            <th>ICS No.</th>
                            <th>Responsibility Center Code</th>
                            <th>Semi-expendable Property No.</th>
                            <th>Item Description</th>
                            <th>Unit</th>
                            <th>Quantity Issued</th>
                            <th>Unit Cost</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" class="form-control" name="ics_no[]" required></td>
                            <td><input type="text" class="form-control" name="resp_center[]" required></td>
                            <td><input type="text" class="form-control" name="property_no[]" required></td>
                            <td><input type="text" class="form-control" name="description[]" required></td>
                            <td><input type="text" class="form-control" name="unit[]" required></td>
                            <td><input type="number" class="form-control quantity" name="quantity[]" required min="1"></td>
                            <td><input type="number" class="form-control unit-cost" name="unit_cost[]" required min="0" step="0.01"></td>
                            <td><input type="text" class="form-control amount-column" name="amount[]" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-center mb-4">
                <button type="button" class="btn btn-primary" id="addRow">Add Row</button>
            </div>

            <div class="row signature-section">
                <div class="col-md-6">
                    <h6>Certification:</h6>
                    <p>I hereby certify to the correctness of the above information.</p>
                    <div class="signature-box">
                        <input type="text" class="form-control border-0" name="custodian_name" required>
                        <small>Property and/or Supply Custodian</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6>Posted by:</h6>
                    <div class="signature-box">
                        <input type="text" class="form-control border-0" name="posted_by" required>
                        <small>Designated Accounting Staff</small>
                    </div>
                </div>
            </div>

            <div class="row mt-4 mb-5">
                <div class="col-md-12 text-center">
                    <button type="submit" class="btn btn-secondary me-2" formaction="rspi_save.php">Save Draft</button>
                    <button type="submit" class="btn btn-primary me-2" formaction="export_rspi.php">Generate PDF</button>
                    <button type="submit" class="btn btn-success" id="submitReport">Submit Report</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add row functionality
            document.getElementById('addRow').addEventListener('click', function() {
                const tbody = document.querySelector('#rspiTable tbody');
                const newRow = tbody.rows[0].cloneNode(true);
                
                // Clear input values
                newRow.querySelectorAll('input').forEach(input => {
                    input.value = '';
                });
                
                tbody.appendChild(newRow);
                attachEventListeners(newRow);
            });

            // Remove row functionality
            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-row')) {
                    const tbody = document.querySelector('#rspiTable tbody');
                    if (tbody.rows.length > 1) {
                        e.target.closest('tr').remove();
                    }
                }
            });

            // Calculate amount functionality
            function calculateAmount(row) {
                const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                const unitCost = parseFloat(row.querySelector('.unit-cost').value) || 0;
                row.querySelector('[name="amount[]"]').value = (quantity * unitCost).toFixed(2);
            }

            function attachEventListeners(row) {
                row.querySelector('.quantity').addEventListener('input', () => calculateAmount(row));
                row.querySelector('.unit-cost').addEventListener('input', () => calculateAmount(row));
            }

            // Attach event listeners to initial row
            document.querySelectorAll('#rspiTable tbody tr').forEach(attachEventListeners);

            // Form submission handling
            document.getElementById('rspiForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const action = e.submitter.formAction;
                
                if (action.includes('export_rspi.php')) {
                    // Handle PDF generation
                    window.open('export_rspi.php?' + new URLSearchParams(new FormData(this)).toString(), '_blank');
                    return;
                }

                // For save and submit actions
                const formData = new FormData(this);
                
                // Client-side validation
                let isValid = true;
                const requiredFields = ['entityName', 'fundCluster', 'date', 'custodian_name', 'posted_by'];
                
                requiredFields.forEach(field => {
                    if (!formData.get(field)) {
                        isValid = false;
                        document.getElementById(field).classList.add('is-invalid');
                    } else {
                        document.getElementById(field).classList.remove('is-invalid');
                    }
                });

                // Validate table rows
                const tbody = document.querySelector('#rspiTable tbody');
                tbody.querySelectorAll('tr').forEach((row, index) => {
                    row.querySelectorAll('input[required]').forEach(input => {
                        if (!input.value) {
                            isValid = false;
                            input.classList.add('is-invalid');
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });
                });

                if (!isValid) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please fill in all required fields'
                    });
                    return;
                }

                // Submit form via AJAX
                fetch(action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message
                        }).then((result) => {
                            if (result.isConfirmed) {
                                if (action.includes('rspi_save.php')) {
                                    // For draft save, just clear the form
                                    document.getElementById('rspiForm').reset();
                                    // Keep only one row in the table
                                    const tbody = document.querySelector('#rspiTable tbody');
                                    while (tbody.rows.length > 1) {
                                        tbody.deleteRow(1);
                                    }
                                    // Clear the first row
                                    tbody.rows[0].querySelectorAll('input').forEach(input => {
                                        input.value = '';
                                        input.classList.remove('is-invalid');
                                    });
                                } else {
                                    // For final submit, redirect to view page
                                    window.location.href = 'view_rspi.php?id=' + data.rspiId;
                                }
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while saving the form'
                    });
                });
            });
        });
    </script>
</body>
</html>