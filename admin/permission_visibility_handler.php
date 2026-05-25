<?php
/**
 * Permission Visibility Handler
 * Handles real-time permission visibility updates for user dashboards
 */

require_once __DIR__ . '/../includes/auth/security_middleware.php';
require_once __DIR__ . '/../includes/utils/permission_visibility.php';

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
    case 'check_permissions':
        $permissions = getVisiblePermissions();
        $navigation = getVisibleNavigation();
        $components = getVisibleComponents();
        $status = getPermissionStatus();
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions,
            'navigation' => $navigation,
            'components' => $components,
            'status' => $status,
            'permissions_updated' => false, // This would be determined by comparing with previous state
            'last_checked' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'get_permission_details':
        $permissions = getVisiblePermissions();
        $status = getPermissionStatus();
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions,
            'status' => $status,
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'refresh_dashboard':
        // Force refresh of dashboard components
        $permissions = getVisiblePermissions();
        $navigation = getVisibleNavigation();
        $components = getVisibleComponents();
        $status = getPermissionStatus();
        
        // Log the refresh action
        logAdminAction($_SESSION['acc_id'], 'refresh_dashboard', 'User refreshed dashboard permissions');
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions,
            'navigation' => $navigation,
            'components' => $components,
            'status' => $status,
            'refreshed_at' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'get_component_status':
        $componentId = $_POST['component_id'] ?? '';
        $permissions = getVisiblePermissions();
        
        if (empty($componentId)) {
            echo json_encode(['success' => false, 'message' => 'Component ID required']);
            exit();
        }
        
        // Check if user has permission for this component
        $hasPermission = false;
        $componentPermissions = [];
        
        switch ($componentId) {
            case 'schedule_management':
                $hasPermission = hasVisiblePermission('manage_schedules', $_SESSION['acc_id']) || 
                                hasVisiblePermission('assign_schedules', $_SESSION['acc_id']);
                $componentPermissions = ['manage_schedules', 'assign_schedules'];
                break;
            case 'subject_management':
                $hasPermission = hasVisiblePermission('manage_subjects', $_SESSION['acc_id']) || 
                                hasVisiblePermission('manage_curriculum', $_SESSION['acc_id']);
                $componentPermissions = ['manage_subjects', 'manage_curriculum'];
                break;
            case 'room_management':
                $hasPermission = hasVisiblePermission('manage_rooms', $_SESSION['acc_id']) ||
                                hasVisiblePermission('view_rooms', $_SESSION['acc_id']) ||
                                hasVisiblePermission('approve_room_requests', $_SESSION['acc_id']);
                $componentPermissions = ['manage_rooms', 'view_rooms', 'approve_room_requests'];
                break;
            case 'user_management':
                $hasPermission = hasVisiblePermission('manage_users', $_SESSION['acc_id']);
                $componentPermissions = ['manage_users'];
                break;
            case 'role_management':
                $hasPermission = hasVisiblePermission('manage_roles', $_SESSION['acc_id']);
                $componentPermissions = ['manage_roles'];
                break;
        }
        
        echo json_encode([
            'success' => true,
            'component_id' => $componentId,
            'has_permission' => $hasPermission,
            'required_permissions' => $componentPermissions,
            'user_permissions' => $permissions,
            'checked_at' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

