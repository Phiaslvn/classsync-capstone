<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['acc_id']) || !hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check if user_id is provided
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

$user_id = (int)$_GET['user_id'];

try {
    // Get user permissions
    $sql = "SELECT 
                p.id, p.permission_key, p.permission_name, 
                COALESCE(up.allowed, 0) as allowed
            FROM permissions p
            LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.acc_id = ?
            ORDER BY p.permission_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $permissions = [];
    
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    // If no permissions found, get all permissions with default denied status
    if (empty($permissions)) {
        $sql = "SELECT id, permission_key, permission_name, 0 as allowed FROM permissions ORDER BY permission_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $permissions = [];
        
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'permissions' => $permissions]);
    
} catch (Exception $e) {
    error_log("Error in get_user_permissions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
