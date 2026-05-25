<?php
session_start();
include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check authentication and authorization - only Admin Support can change roles
$roleId = (int)($_SESSION['role_id'] ?? 0);
if (!isset($_SESSION['acc_id']) || $roleId !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Only Admin Support can change roles.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$accId = (int)($_POST['acc_id'] ?? 0);
$action = trim($_POST['action'] ?? ''); // 'promote' or 'demote'

if ($accId <= 0 || !in_array($action, ['promote', 'demote'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Role hierarchy (from highest to lowest)
// 1 = Admin Support, 2 = Admin, 3 = Moderator, 4 = User/Instructor
$roleHierarchy = [1, 2, 3, 4];
$roleNames = [
    1 => 'Admin Support',
    2 => 'Admin',
    3 => 'Moderator',
    4 => 'Instructor'
];

try {
    // Get current role(s) - users can have multiple roles
    $stmt = $conn->prepare('SELECT role_id FROM user_roles WHERE acc_id = ? ORDER BY role_id ASC');
    $stmt->bind_param('i', $accId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentRoles = [];
    while ($row = $result->fetch_assoc()) {
        $currentRoles[] = (int)$row['role_id'];
    }
    $stmt->close();
    
    if (empty($currentRoles)) {
        echo json_encode(['success' => false, 'message' => 'User has no roles assigned']);
        exit();
    }
    
    // Get the primary role (lowest/highest priority role)
    $primaryRole = min($currentRoles);
    $primaryRoleIndex = array_search($primaryRole, $roleHierarchy);
    
    // Calculate new role
    if ($action === 'promote') {
        if ($primaryRoleIndex === 0) {
            echo json_encode(['success' => false, 'message' => 'User already has the highest role']);
            exit();
        }
        $newRoleIndex = $primaryRoleIndex - 1;
    } else { // demote
        if ($primaryRoleIndex === count($roleHierarchy) - 1) {
            echo json_encode(['success' => false, 'message' => 'User already has the lowest role']);
            exit();
        }
        $newRoleIndex = $primaryRoleIndex + 1;
    }
    
    $newRoleId = $roleHierarchy[$newRoleIndex];
    $oldRoleName = $roleNames[$primaryRole];
    $newRoleName = $roleNames[$newRoleId];
    
    // Remove all existing roles and assign the new one
    $deleteStmt = $conn->prepare('DELETE FROM user_roles WHERE acc_id = ?');
    $deleteStmt->bind_param('i', $accId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Insert new role
    $insertStmt = $conn->prepare('INSERT INTO user_roles (acc_id, role_id) VALUES (?, ?)');
    $insertStmt->bind_param('ii', $accId, $newRoleId);
    $insertStmt->execute();
    $insertStmt->close();
    
    // Get username for logging
    $userStmt = $conn->prepare('SELECT acc_user FROM account WHERE acc_id = ?');
    $userStmt->bind_param('i', $accId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userRow = $userResult->fetch_assoc();
    $username = $userRow['acc_user'] ?? '';
    $userStmt->close();
    
    // Log the action
    $actorId = $_SESSION['acc_id'];
    $actionText = ucfirst($action) . 'd role from ' . $oldRoleName . ' to ' . $newRoleName;
    $details = json_encode([
        'acc_id' => $accId,
        'username' => $username,
        'old_role_id' => $primaryRole,
        'old_role_name' => $oldRoleName,
        'new_role_id' => $newRoleId,
        'new_role_name' => $newRoleName,
        'action' => $action
    ]);
    
    $logStmt = $conn->prepare('INSERT INTO audit_log (acc_id, action, details) VALUES (?, ?, ?)');
    $logStmt->bind_param('iss', $actorId, $actionText, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Role ' . $action . 'd successfully',
        'old_role' => $oldRoleName,
        'new_role' => $newRoleName
    ]);
    
} catch (Exception $e) {
    error_log("Error in promote_demote_role.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating role']);
}
?>


