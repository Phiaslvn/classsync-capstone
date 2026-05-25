<?php
/**
 * Permission Sync Handler
 * Handles real-time permission synchronization between Admin Support and Admin
 */

require_once __DIR__ . '/../includes/security_middleware.php';
require_once __DIR__ . '/../includes/permission_sync.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Set JSON response header
header('Content-Type: application/json');

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit();
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_permissions':
        $permissions = getEffectivePermissions();
        $navigation = getDynamicNavigation();
        $components = getDashboardComponents();
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions,
            'navigation' => $navigation,
            'components' => $components,
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'sync_permissions':
        $targetUserId = intval($_POST['target_user_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];
        
        if ($targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        // Check if current user can sync permissions
        $currentUserRole = getUserRole();
        if ($currentUserRole['role_name'] !== 'Admin support') {
            echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
            exit();
        }
        
        $result = syncPermissions($targetUserId, $permissions);
        
        if ($result) {
            // Log the sync action
            logAdminAction($_SESSION['acc_id'], 'sync_permissions', "Synced permissions for user ID: $targetUserId");
            
            echo json_encode([
                'success' => true,
                'message' => 'Permissions synchronized successfully',
                'target_user' => $targetUserId,
                'synced_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to sync permissions']);
        }
        break;
        
    case 'get_user_permission_status':
        $targetUserId = intval($_POST['target_user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        $status = getUserPermissionStatus($targetUserId);
        echo json_encode(['success' => true, 'status' => $status]);
        break;
        
    case 'update_dashboard_components':
        $components = getDashboardComponents();
        $navigation = getDynamicNavigation();
        
        echo json_encode([
            'success' => true,
            'components' => $components,
            'navigation' => $navigation,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

