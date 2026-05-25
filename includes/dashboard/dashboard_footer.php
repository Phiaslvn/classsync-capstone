<?php
/**
 * Shared Dashboard Footer Component
 * Provides consistent footer across all dashboards
 */
?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="mb-0">&copy; 2025 EVSU-OCC Scheduling System. All rights reserved.</p>
        </div>
    </footer>

    <!-- jQuery (required for DataTables and other plugins) -->
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 for notifications -->
    <script src="/assets/js/sweetalert2.min.js"></script>
    
    <!-- Custom Dashboard Scripts -->
    <script>
        // Handle success/error messages from URL parameters
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const error = urlParams.get('error');
            
            if (status) {
                let title = "", message = "", icon = "success";
                
                switch (status) {
                    case "created":
                        title = "Created!";
                        message = "The item has been created successfully.";
                        break;
                    case "updated":
                        title = "Updated!";
                        message = "The item has been updated successfully.";
                        break;
                    case "deleted":
                        title = "Deleted!";
                        message = "The item has been deleted successfully.";
                        break;
                    case "permissions_updated":
                        title = "Permissions Updated!";
                        message = "User permissions have been updated successfully.";
                        break;
                    default:
                        title = "Success!";
                        message = "Operation completed successfully.";
                }
                
                Swal.fire({
                    title: title,
                    text: message,
                    icon: icon,
                    confirmButtonText: "OK",
                    confirmButtonColor: "#800000"
                }).then(() => {
                    // Clean URL
                    window.history.replaceState(null, null, window.location.pathname);
                });
            }
            
            if (error) {
                let title = "Error!", message = "An error occurred. Please try again.", icon = "error";
                
                switch (error) {
                    case "permissions_failed":
                        message = "Failed to update permissions. Please try again.";
                        break;
                    case "invalid_user":
                        message = "Invalid user selected.";
                        break;
                    case "unauthorized":
                        message = "You don't have permission to perform this action.";
                        break;
                }
                
                Swal.fire({
                    title: title,
                    text: message,
                    icon: icon,
                    confirmButtonText: "OK",
                    confirmButtonColor: "#800000"
                }).then(() => {
                    // Clean URL
                    window.history.replaceState(null, null, window.location.pathname);
                });
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener("DOMContentLoaded", function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (window.bootstrap && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    } else if (alert && alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 5000);
            });
        });
        
        // Loading states for form submissions
        document.addEventListener("DOMContentLoaded", function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after 3 seconds (in case of errors)
                        setTimeout(function() {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            });
        });
        
        // Confirmation dialogs for delete actions
        document.addEventListener("DOMContentLoaded", function() {
            const deleteLinks = document.querySelectorAll('a[href*="delete"], .delete-btn');
            deleteLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    const itemName = this.getAttribute('data-name') || 'this item';
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        text: `You are about to delete ${itemName}. This action cannot be undone!`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
