// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('tableBody');
    const rows = tableBody.getElementsByTagName('tr');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        Array.from(rows).forEach(row => {
            const propertyNo = row.cells[2]?.textContent.toLowerCase() || '';
            const description = row.cells[3]?.textContent.toLowerCase() || '';
            const usefulLife = row.cells[4]?.textContent.toLowerCase() || '';
            
            if (propertyNo.includes(searchTerm) || 
                description.includes(searchTerm) || 
                usefulLife.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

// Export functionality
function openExport() {
    // Create form to submit for export
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_regspi.php';
    
    // Add current filter state if needed
    const categorySelect = document.getElementById('semi-expendable-property');
    if (categorySelect) {
        const hiddenCategory = document.createElement('input');
        hiddenCategory.type = 'hidden';
        hiddenCategory.name = 'category';
        hiddenCategory.value = categorySelect.value;
        form.appendChild(hiddenCategory);
    }
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Row limit handling
document.getElementById('rsep_row_limit').addEventListener('change', function() {
    const limit = this.value;
    const rows = document.querySelectorAll('#tableBody tr');
    
    rows.forEach((row, index) => {
        if (limit === 'all' || index < parseInt(limit)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});