<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

// Ensure user has permission to manage users (Admin Support area)
requirePermissionWithAudit('manage_users', '../../public/index.php');

// Handle POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Prevent deletion of the current user
if ($user_id == $_SESSION['acc_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot permanently delete your own account']);
    exit;
}

// Get user information before deletion for logging
$user_stmt = $conn->prepare("SELECT fname, lname, acc_user, acc_email FROM account WHERE acc_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_info) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Start transaction for data integrity
$conn->begin_transaction();

try {
    // Delete from user_roles first (foreign key constraint)
    $stmt1 = $conn->prepare("DELETE FROM user_roles WHERE acc_id = ?");
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();
    $stmt1->close();
    
    // Delete from user_permissions if exists
    $stmt2 = $conn->prepare("DELETE FROM user_permissions WHERE acc_id = ?");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $stmt2->close();
    
    // Delete from audit_log if exists
    $stmt3 = $conn->prepare("DELETE FROM audit_log WHERE acc_id = ?");
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();
    $stmt3->close();
    
    // Finally delete from account table
    $stmt4 = $conn->prepare("DELETE FROM account WHERE acc_id = ?");
    $stmt4->bind_param("i", $user_id);
    $stmt4->execute();
    $stmt4->close();
    
    // Commit transaction
    $conn->commit();
    
    // Log the permanent deletion action
    $user_name = $user_info['fname'] . ' ' . $user_info['lname'];
    logAdminAction($_SESSION['acc_id'], 'permanent_delete_user', "Permanently deleted user: $user_name (ID: $user_id)");
    
    echo json_encode(['success' => true, 'message' => 'User permanently deleted']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Permanent delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to permanently delete user']);
}

exit;
