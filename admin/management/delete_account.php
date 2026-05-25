<?php
/**
 * Delete Account Endpoint
 * Handles account deletion with proper authorization and data cleanup
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
header('Content-Type: application/json');

// Check if user has permission to delete accounts
if (!hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - You do not have permission to delete accounts']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$accId = (int)($_POST['acc_id'] ?? 0);

if ($accId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit();
}

// Prevent self-deletion
if ($accId == $_SESSION['acc_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit();
}

try {
    // Get user info for logging
    $stmt = $conn->prepare("SELECT acc_user, fname, lname, acc_email FROM account WHERE acc_id = ?");
    $stmt->bind_param("i", $accId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit();
    }
    
    $userInfo = $result->fetch_assoc();
    $stmt->close();
    
    // Check if user is trying to delete an admin account (additional protection)
    $stmt = $conn->prepare("
        SELECT r.role_name 
        FROM account a
        JOIN user_roles ur ON a.acc_id = ur.acc_id
        JOIN roles r ON ur.role_id = r.id
        WHERE a.acc_id = ?
    ");
    $stmt->bind_param("i", $accId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userRole = $result->fetch_assoc();
    $stmt->close();
    
    // Prevent deletion of admin accounts unless user is IT Admin
    if (in_array($userRole['role_name'], ['Admin', 'IT Admin']) && !isAdminSupport()) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete admin accounts']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get instructor ID if instructor record exists
    $inst_id = null;
    $stmt = $conn->prepare("SELECT inst_id FROM instructor WHERE inst_user = ? LIMIT 1");
    $stmt->bind_param("s", $userInfo['acc_user']);
    $stmt->execute();
    $inst_result = $stmt->get_result();
    if ($inst_row = $inst_result->fetch_assoc()) {
        $inst_id = (int)$inst_row['inst_id'];
    }
    $stmt->close();
    
    // If instructor exists, delete from dependent tables first (in correct order)
    if ($inst_id !== null) {
        // 1. Delete from faculty_workload (references instructor via inst_id)
        $stmt = $conn->prepare("DELETE FROM faculty_workload WHERE inst_id = ?");
        $stmt->bind_param("i", $inst_id);
        $stmt->execute();
        $stmt->close();
        
        // 2. Delete from schedule (references instructor via inst_id)
        $stmt = $conn->prepare("DELETE FROM schedule WHERE inst_id = ?");
        $stmt->bind_param("i", $inst_id);
        $stmt->execute();
        $stmt->close();
        
        // 3. Delete from room_request (references instructor via inst_id)
        $stmt = $conn->prepare("DELETE FROM room_request WHERE inst_id = ?");
        $stmt->bind_param("i", $inst_id);
        $stmt->execute();
        $stmt->close();
        
        // 4. Now delete from instructor table (all dependencies removed)
        $stmt = $conn->prepare("DELETE FROM instructor WHERE inst_id = ?");
        $stmt->bind_param("i", $inst_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete from user_roles table
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE acc_id = ?");
    $stmt->bind_param("i", $accId);
    $stmt->execute();
    $stmt->close();
    
    // Delete from account table
    $stmt = $conn->prepare("DELETE FROM account WHERE acc_id = ?");
    $stmt->bind_param("i", $accId);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Log the deletion
    $action = "Deleted account: {$userInfo['acc_user']} ({$userInfo['fname']} {$userInfo['lname']})";
    $details = json_encode([
        'deleted_account_id' => $accId,
        'deleted_username' => $userInfo['acc_user'],
        'deleted_email' => $userInfo['acc_email'],
        'deleted_by' => $_SESSION['acc_id']
    ]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("iss", $_SESSION['acc_id'], $action, $details);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Account deleted successfully',
        'deleted_user' => $userInfo['fname'] . ' ' . $userInfo['lname']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Delete Account Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
