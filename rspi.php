<?php
ob_start();
require_once 'config.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all RSPI reports with their items
$query = "
    SELECT 
        r.id,
        r.serial_no,
        r.entity_name,
        r.fund_cluster,
        r.report_date,
        r.custodian_name,
        r.posted_by,
        r.created_at,
        COUNT(i.id) as item_count,
        SUM(i.quantity_issued) as total_quantity,
        SUM(i.amount) as total_amount
    FROM rspi_reports r
    LEFT JOIN rspi_items i ON r.id = i.rspi_id
    GROUP BY r.id
    ORDER BY r.created_at DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSPI Reports - Inventory Management System</title>

    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- App styles (load last so our rules override Bootstrap on this page only) -->
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    
    
    <style>
        .actions-column {
            white-space: nowrap;
            width: 120px;
        }
        .dataTables_filter {
            margin-bottom: 5px;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .status-badge {
            font-size: 0.875rem;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.075);
        }
        @media (max-width: 768px) {
            .actions-column {
                width: auto;
            }
            .btn-action {
                padding: 0.375rem 0.75rem;
            }
        }
    </style>
</head>
<body class="rspi-page">
    <?php include 'sidebar.php'; ?>

    <div class="content">
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2>RSPI Reports</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="add_rspi.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New RSPI
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="rspiTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>RSPI No.</th>
                                <th>Entity Name</th>
                                <th>Fund Cluster</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total Qty</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['serial_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['entity_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['fund_cluster']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['report_date'])); ?></td>
                                <td><?php echo $row['item_count']; ?></td>
                                <td><?php echo number_format($row['total_quantity']); ?></td>
                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-success">Posted</span>
                                </td>
                                <td class="actions-column">
                                    <button class="btn btn-info btn-action btn-sm" 
                                            onclick="viewRSPI(<?php echo $row['id']; ?>)" 
                                            title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-warning btn-action btn-sm" 
                                            onclick="editRSPI(<?php echo $row['id']; ?>)" 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-action btn-sm" 
                                            onclick="deleteRSPI(<?php echo $row['id']; ?>)" 
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">RSPI Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printRSPI()">Print</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this RSPI report?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#rspiTable').DataTable({
                responsive: true,
                order: [[3, 'desc']], // Sort by date descending
                columnDefs: [
                    { targets: -1, orderable: false }, // Disable sorting on actions column
                    { responsivePriority: 1, targets: [0, 3, -1] }, // Keep these columns visible on mobile
                    { responsivePriority: 2, targets: [4, 5, 6] }
                ],
                language: {
                    search: "Search RSPI reports:",
                    lengthMenu: "Show _MENU_ reports per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ RSPI reports"
                }
            });
        });

        function viewRSPI(id) {
            // Load RSPI details via AJAX
            fetch(`get_rspi_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('viewModalBody').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewModal')).show();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Failed to load RSPI details', 'error');
                });
        }

        function editRSPI(id) {
            window.location.href = `edit_rspi.php?id=${id}`;
        }

        function deleteRSPI(id) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
            
            document.getElementById('confirmDelete').onclick = function() {
                fetch(`delete_rspi.php?id=${id}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    modal.hide();
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'RSPI report deleted successfully'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    modal.hide();
                    Swal.fire('Error', 'Failed to delete RSPI report', 'error');
                });
            };
        }

        function printRSPI() {
            const modalContent = document.getElementById('viewModalBody').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>RSPI Report</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            @media print {
                                .no-print { display: none; }
                                @page { margin: 1cm; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container mt-4">
                            ${modalContent}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>