<?php
/**
 * Role Management Handler
 * Handles AJAX requests for role management across all user types
 */

require_once 'includes/security_middleware.php';

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
    case 'assign_user_role':
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_role_id = intval($_POST['new_role_id'] ?? 0);
        
        if ($user_id <= 0 || $new_role_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user or role ID']);
            exit();
        }
        
        // Check if current user can assign roles
        $currentUserId = $_SESSION['acc_id'];
        $currentUserRole = getUserRole($currentUserId);
        
        // Admin Support can assign any role
        if ($currentUserRole['role_name'] === 'Admin support') {
            // Allow any role assignment
        }
        // Admin can only assign Moderator and Instructor roles
        elseif ($currentUserRole['role_name'] === 'Admin') {
            if (!in_array($new_role_id, [3, 4])) {
                echo json_encode(['success' => false, 'message' => 'You can only assign Moderator or Instructor roles']);
                exit();
            }
        }
        // Other roles cannot assign roles
        else {
            echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
            exit();
        }
        
        $conn->begin_transaction();
        try {
            // Check if user already has a role
            $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE acc_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing role
                $stmt = $conn->prepare("UPDATE user_roles SET role_id = ? WHERE acc_id = ?");
                $stmt->bind_param("ii", $new_role_id, $user_id);
                $stmt->execute();
            } else {
                // Insert new role assignment
                $stmt = $conn->prepare("INSERT INTO user_roles (acc_id, role_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $user_id, $new_role_id);
                $stmt->execute();
            }
            $stmt->close();
            
            $conn->commit();
            
            // Log the action
            $userInfo = getUserInfo($user_id);
            $userName = $userInfo ? $userInfo['fname'] . ' ' . $userInfo['lname'] : "User ID $user_id";
            logAdminAction($currentUserId, 'assign_user_role', "Assigned new role to user: $userName");
            
            echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to update user role: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_user_permissions':
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        $permissions = getUserEffectivePermissions($user_id);
        echo json_encode(['success' => true, 'permissions' => $permissions]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

