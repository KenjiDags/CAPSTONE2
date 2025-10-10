// Handle mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (mobileToggle && sidebar) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    this.classList.toggle('active');
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768 && 
                        !sidebar.contains(e.target) && 
                        !mobileToggle.contains(e.target) &&
                        sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        mobileToggle.classList.remove('active');
                    }
                });

                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('active');
                        mobileToggle.classList.remove('active');
                    }
                });
            }
        });

        // Add confirmation for delete actions (if you add delete functionality)
        function confirmDelete(risNo) {
            return confirm(`Are you sure you want to delete RIS ${risNo}? This action cannot be undone.`);
        }

        // Print functionality
        function printRIS() {
            window.print();
        }