<?php
require 'config.php';
include 'sidebar.php';

// Fetch semi-expendable items from database
$items = [];
$sql = "SELECT category, item_description, semi_expendable_property_no, unit, amount, quantity_balance, remarks FROM semi_expendable_property ORDER BY item_description";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    error_log('RRSP DB error: ' . $conn->error);
}
?>
    <script>
    function openExport() {
        // Collect all form values
        const params = new URLSearchParams({
            entity_name: document.getElementById('entity_name').value,
            rrsp_date: document.getElementById('rrsp_date').value,
            rrsp_no: document.getElementById('rrsp_no').value,
            returned_by: document.querySelector('input[name="returned_by"]').value,
            returned_date: document.querySelector('input[name="returned_date"]').value,
            received_by: document.querySelector('input[name="received_by"]').value,
            received_date: document.querySelector('input[name="received_date"]').value
        });
        window.location.href = './rrsp_export.php?' + params.toString();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        if (limitSelect) {
            const wrapper = document.querySelector('.rpci-table-wrapper');
            const table = document.querySelector('.rpci-table');
            const thead = table.querySelector('thead');
            function applyLimit() {
                const val = limitSelect.value;
                const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])');
                if (!wrapper || !thead || !sampleRow) return;
                const headerHeight = thead.getBoundingClientRect().height;
                const rowHeight = sampleRow.getBoundingClientRect().height;
                if (val === 'all') { wrapper.style.maxHeight = 'none'; }
                else { wrapper.style.maxHeight = `${headerHeight + rowHeight * parseInt(val,10)}px`; }
            }
            limitSelect.addEventListener('change', applyLimit);
            applyLimit();
            window.addEventListener('resize', applyLimit);
        }
        // Searchbar JS (matches rpci behavior)
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                const filter = this.value.toLowerCase();
                const rows = document.querySelectorAll('#rpci-table tbody tr');
                rows.forEach(row => {
                    const desc = (row.cells[0]?.textContent || '').toLowerCase();
                    const qty = (row.cells[1]?.textContent || '').toLowerCase();
                    const ics = (row.cells[2]?.textContent || '').toLowerCase();
                    const enduser = (row.cells[3]?.textContent || '').toLowerCase();
                    const remarks = (row.cells[4]?.textContent || '').toLowerCase();
                    const match = desc.includes(filter) || qty.includes(filter) || ics.includes(filter) || enduser.includes(filter) || remarks.includes(filter);
                    row.style.display = match ? '' : 'none';
                });
            });
        }
    });
    </script>
                    <li>Entity Name – the name of the agency/entity</li>
                    <li>Date – date of preparation of the RRSP</li>
                    <li>RRSP No. – shall be numbered by the Property and/or Supply Division/Unit as follows: <br>
                        <span style="font-family:monospace;">0000 - 00 - 000</span> (Serial number - Month - Year)
                    </li>
                    <li>Item Description – brief description of the returned semi-expendable property</li>
                    <li>Quantity – quantity of the returned semi-expendable property</li>
                    <li>ICS No. – Inventory Custodian Slip (ICS) number of the returned semi-expendable property</li>
                    <li>End-user – name of accountable officer/end-user returning the property</li>
                    <li>Remarks – comments (e.g. reason for the return, cancelled ICS, and other info)</li>
                    <li>Returned by – signature over printed name of the accountable officer/end-user</li>
                    <li>Received by – signature over printed name of the designated Head, Property and/or Supply Division/Unit</li>
                </ol>
            </div>
            <form method="POST" action="">
                <div style="display:flex; gap:24px; flex-wrap:wrap; margin-bottom:12px;">
                    <div>
                        <label for="entity_name"><strong>Entity Name:</strong></label><br>
                        <input type="text" id="entity_name" name="entity_name" style="width:220px;">
                    </div>
                    <div>
                        <label for="rrsp_date"><strong>Date:</strong></label><br>
                        <input type="date" id="rrsp_date" name="rrsp_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label for="rrsp_no"><strong>RRSP No.:</strong></label><br>
                        <input type="text" id="rrsp_no" name="rrsp_no" style="width:140px;" placeholder="0000-00-000">
                    </div>
                </div>
                <div class="rpci-table-wrapper">
                    <table class="rpci-table" id="rrsp-table">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th>Quantity</th>
                                <th>ICS No.</th>
                                <th>End-user</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="5" style="text-align:center; padding: 32px; color: var(--text-gray); font-style: italic;">No returned semi-expendable items found</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['item_description'] ?? '') ?></td>
                                        <td><?= htmlspecialchars((string)($row['quantity_balance'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($row['semi_expendable_property_no'] ?? '') ?></td>
                                        <td></td>
                                        <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex; gap:32px; margin-top:24px; flex-wrap:wrap;">
                    <div>
                        <label><strong>Returned by:</strong></label><br>
                        <input type="text" name="returned_by" style="width:220px;">
                        <div>End User</div>
                        <input type="date" name="returned_date" style="width:140px;">
                    </div>
                    <div>
                        <label><strong>Received by:</strong></label><br>
                        <input type="text" name="received_by" style="width:220px;">
                        <div>Head, Property and/or Supply Division/Unit</div>
                        <input type="date" name="received_date" style="width:140px;">
                    </div>
                </div>
                <div style="text-align:center; margin-top: 18px;">
                    <button type="button" class="export-btn" onclick="openExport()">📄 Export to PDF</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        const wrapper = document.querySelector('.rpci-table-wrapper');
        const table = document.querySelector('.rpci-table');
        const thead = table.querySelector('thead');
        function applyLimit() {
            const val = limitSelect.value;
            const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])');
            if (!wrapper || !thead || !sampleRow) return;
            const headerHeight = thead.getBoundingClientRect().height;
            const rowHeight = sampleRow.getBoundingClientRect().height;
            if (val === 'all') { wrapper.style.maxHeight = 'none'; }
            else { wrapper.style.maxHeight = `${headerHeight + rowHeight * parseInt(val,10)}px`; }
        }
        limitSelect.addEventListener('change', applyLimit);
        applyLimit();
        window.addEventListener('resize', applyLimit);
    });
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rpci-table tbody tr');
        rows.forEach(row => {
            const category = (row.cells[1]?.textContent || '').toLowerCase();
            const desc = (row.cells[2]?.textContent || '').toLowerCase();
            const propno = (row.cells[3]?.textContent || '').toLowerCase();
            const unit = (row.cells[4]?.textContent || '').toLowerCase();
            const match = category.includes(filter) || desc.includes(filter) || propno.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
    </script>
</body>
</html>
