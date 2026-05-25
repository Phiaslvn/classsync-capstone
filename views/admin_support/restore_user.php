<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

// Ensure user has permission to manage users (Admin Support area)
requirePermissionWithAudit('manage_users', '../../public/index.php');

// Set content type to JSON for AJAX responses
header('Content-Type: application/json');

// Handle both GET and POST requests
$user_id = null;
if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
} elseif (isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // Validate CSRF token for POST requests
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
        exit;
    }
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Get user information before restoration for logging and validation
$user_stmt = $conn->prepare("SELECT fname, lname, acc_user, acc_email, acc_status FROM account WHERE acc_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_info) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Check if user is actually deleted/archived
if ($user_info['acc_status'] !== 'Deleted') {
    echo json_encode(['success' => false, 'message' => 'User is not archived and cannot be restored']);
    exit;
}

// Restore the user by setting status to 'Active'
$stmt = $conn->prepare("UPDATE account SET acc_status = 'Active' WHERE acc_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    // Log the restoration action
    $user_name = $user_info['fname'] . ' ' . $user_info['lname'];
    logAdminAction($_SESSION['acc_id'], 'restore_user', "Restored user: $user_name (ID: $user_id)");
    
    echo json_encode(['success' => true, 'message' => 'User restored successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to restore user']);
}

$stmt->close();
exit;

