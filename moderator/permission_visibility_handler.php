<?php
/**
 * Permission Visibility Handler for Moderator
 * Handles real-time permission visibility updates for moderator dashboard
 */

require_once __DIR__ . '/../includes/security_middleware.php';
require_once __DIR__ . '/../includes/permission_visibility.php';

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

// Check if user is logged in and has moderator role
if (!isLoggedIn() || !hasRole('Moderator')) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
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
            'permissions_updated' => false,
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
        $permissions = getVisiblePermissions();
        $navigation = getVisibleNavigation();
        $components = getVisibleComponents();
        $status = getPermissionStatus();
        
        // Log the refresh action
        logAdminAction($_SESSION['acc_id'], 'refresh_dashboard', 'Moderator refreshed dashboard permissions');
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions,
            'navigation' => $navigation,
            'components' => $components,
            'status' => $status,
            'refreshed_at' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

