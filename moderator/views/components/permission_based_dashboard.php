<?php
/**
 * Permission-Based Dashboard Component for Moderator
 * Dynamically shows/hides components based on user permissions
 */

require_once __DIR__ . '/../../../includes/permission_visibility.php';

$currentUserId = $_SESSION['acc_id'];
$visiblePermissions = getVisiblePermissions($currentUserId);
$visibleNavigation = getVisibleNavigation($currentUserId);
$visibleComponents = getVisibleComponents($currentUserId);
$permissionStatus = getPermissionStatus($currentUserId);
?>

<div class="permission-dashboard">
    <!-- Permission Status Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="permission-status-card">
                <div class="d-flex align-items-center">
                    <div class="permission-icon me-3">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Your Permissions</h5>
                        <p class="text-muted mb-0">
                            <?= $permissionStatus['granted_permissions'] ?> of <?= $permissionStatus['total_permissions'] ?> permissions granted
                            (<?= $permissionStatus['permission_percentage'] ?>%)
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="permission-refresh-card">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshPermissions()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Permissions
                </button>
                <small class="text-muted d-block mt-1">Last updated: <?= $permissionStatus['last_updated'] ?></small>
            </div>
        </div>
    </div>

    <!-- Dynamic Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="permission-navigation">
                <h6 class="mb-3">Available Features</h6>
                <div class="row">
                    <?php foreach ($visibleNavigation as $key => $item): ?>
                        <?php if ($key !== 'overview' && $key !== 'profile'): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="permission-nav-card" onclick="showComponent('<?= $key ?>')">
                                    <div class="d-flex align-items-center">
                                        <div class="nav-icon me-3">
                                            <i class="bi bi-<?= $item['icon'] ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?= $item['label'] ?></h6>
                                            <small class="text-muted"><?= $item['description'] ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Components -->
    <div class="permission-components">
        <?php foreach ($visibleComponents as $key => $component): ?>
            <div id="<?= $key ?>" class="permission-component" style="display: none;">
                <div class="component-header">
                    <div class="d-flex align-items-center">
                        <div class="component-icon me-3">
                            <i class="bi bi-<?= $component['icon'] ?>"></i>
                        </div>
                        <div>
                            <h5 class="mb-1"><?= $component['title'] ?></h5>
                            <p class="text-muted mb-0"><?= $component['description'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="component-body">
                    <?php if (file_exists($component['file'])): ?>
                        <?php include $component['file']; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Component file not found: <?= $component['file'] ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Permission Details Modal -->
    <div class="modal fade" id="permissionDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Your Permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <?php foreach ($visiblePermissions as $permission => $data): ?>
                            <div class="col-md-6 mb-3">
                                <div class="permission-item">
                                    <div class="d-flex align-items-center">
                                        <div class="permission-status me-3">
                                            <i class="bi bi-<?= $data['granted'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?= $data['display_name'] ?></h6>
                                            <small class="text-muted"><?= $data['description'] ?></small>
                                            <br><small class="badge bg-<?= $data['source'] === 'role' ? 'primary' : 'secondary' ?>">
                                                <?= $data['source'] === 'role' ? 'Role-based' : 'User-specific' ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.permission-dashboard {
    padding: 1rem 0;
}

.permission-status-card {
    background: linear-gradient(135deg, #800000, #a00000);
    color: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
}

.permission-icon {
    font-size: 2rem;
    opacity: 0.9;
}

.permission-refresh-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #e9ecef;
}

.permission-navigation {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.permission-nav-card {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s ease;
}

.permission-nav-card:hover {
    background: #e3f2fd;
    border-color: #2196f3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.nav-icon {
    font-size: 1.5rem;
    color: #800000;
}

.permission-component {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
    overflow: hidden;
}

.component-header {
    background: linear-gradient(135deg, #800000, #a00000);
    color: white;
    padding: 1.5rem;
}

.component-icon {
    font-size: 1.5rem;
    opacity: 0.9;
}

.component-body {
    padding: 2rem;
}

.permission-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.permission-status {
    font-size: 1.2rem;
}
</style>

<script>
// Permission-based dashboard functionality for Moderator
function showComponent(componentId) {
    // Hide all components
    document.querySelectorAll('.permission-component').forEach(component => {
        component.style.display = 'none';
    });
    
    // Show selected component
    const component = document.getElementById(componentId);
    if (component) {
        component.style.display = 'block';
        
        // Scroll to component
        component.scrollIntoView({ behavior: 'smooth' });
    }
}

function refreshPermissions() {
    // Show loading
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Refreshing...';
    button.disabled = true;
    
    // Simulate refresh (in real implementation, this would make an AJAX call)
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        
        // Show success message
        Swal.fire({
            title: 'Permissions Refreshed',
            text: 'Your permissions have been updated successfully.',
            icon: 'success',
            confirmButtonColor: '#800000',
            timer: 2000
        });
        
        // Reload the page to show updated permissions
        setTimeout(() => {
            location.reload();
        }, 1000);
    }, 1500);
}

// Auto-refresh permissions every 30 seconds
setInterval(function() {
    // Check for permission updates
    fetch('permission_visibility_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_permissions&csrf_token=<?= generateCSRFToken() ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.permissions_updated) {
            // Show notification that permissions have been updated
            Swal.fire({
                title: 'Permissions Updated',
                text: 'Your permissions have been updated. Refreshing dashboard...',
                icon: 'info',
                confirmButtonColor: '#800000',
                timer: 3000
            }).then(() => {
                location.reload();
            });
        }
    })
    .catch(error => {
        console.log('Permission check failed:', error);
    });
}, 30000);

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('Moderator permission-based dashboard initialized');
    console.log('Visible permissions:', <?= json_encode($visiblePermissions) ?>);
    console.log('Visible components:', <?= json_encode($visibleComponents) ?>);
});
</script>

