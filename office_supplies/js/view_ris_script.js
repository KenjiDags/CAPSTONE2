        // Add confirmation for delete actions (if you add delete functionality)
        function confirmDelete(risNo) {
            return confirm(`Are you sure you want to delete RIS ${risNo}? This action cannot be undone.`);
        }

        // Print functionality
        function printRIS() {
            window.print();
        }