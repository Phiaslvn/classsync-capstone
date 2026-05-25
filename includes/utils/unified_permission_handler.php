<?php
/**
 * Unified Permission Handler
 * Handles permission visibility for all user types
 */

require_once __DIR__ . '/../auth/security_middleware.php';
require_once __DIR__ . '/permission_visibility.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['acc_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

$action = $_POST['action'] ?? '';
$currentUserId = $_SESSION['acc_id'];

try {
    switch ($action) {
        case 'check_permissions':
            // Check if permissions have been updated
            $lastCheck = $_SESSION['last_permission_check'] ?? 0;
            $currentTime = time();
            
            // Get current permissions
            $currentPermissions = getVisiblePermissions($currentUserId);
            $permissionCount = count($currentPermissions);
            
            // Check if permissions have changed
            $permissionsChanged = false;
            if (!isset($_SESSION['last_permission_count']) || $_SESSION['last_permission_count'] != $permissionCount) {
                $permissionsChanged = true;
                $_SESSION['last_permission_count'] = $permissionCount;
            }
            
            // Update last check time
            $_SESSION['last_permission_check'] = $currentTime;
            
            echo json_encode([
                'success' => true,
                'permissions_updated' => $permissionsChanged,
                'permission_count' => $permissionCount,
                'permissions' => $currentPermissions,
                'last_check' => $lastCheck,
                'current_time' => $currentTime
            ]);
            break;
            
        case 'get_permission_status':
            // Get detailed permission status
            $permissionStatus = getPermissionStatus($currentUserId);
            $visiblePermissions = getVisiblePermissions($currentUserId);
            $visibleNavigation = getVisibleNavigation($currentUserId);
            $visibleComponents = getVisibleComponents($currentUserId);
            
            echo json_encode([
                'success' => true,
                'permission_status' => $permissionStatus,
                'visible_permissions' => $visiblePermissions,
                'visible_navigation' => $visibleNavigation,
                'visible_components' => $visibleComponents
            ]);
            break;
            
        case 'refresh_dashboard':
            // Force refresh dashboard components
            $visibleComponents = getVisibleComponents($currentUserId);
            $visibleNavigation = getVisibleNavigation($currentUserId);
            
            // Log the refresh action
            logAdminAction($currentUserId, 'dashboard_refresh', 'Dashboard components refreshed');
            
            echo json_encode([
                'success' => true,
                'message' => 'Dashboard refreshed successfully',
                'visible_components' => $visibleComponents,
                'visible_navigation' => $visibleNavigation
            ]);
            break;
            
        case 'get_user_info':
            // Get current user information
            $userInfo = getUserInfo($currentUserId);
            $userRole = getUserRole($currentUserId);
            $permissionStatus = getPermissionStatus($currentUserId);
            
            echo json_encode([
                'success' => true,
                'user_info' => $userInfo,
                'user_role' => $userRole,
                'permission_status' => $permissionStatus
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Unified permission handler error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>

