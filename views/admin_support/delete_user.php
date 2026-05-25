<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

// Ensure user has permission to manage users (Admin Support area)
requirePermissionWithAudit('manage_users', '../../public/index.php');

// Handle both GET and POST requests
$user_id = null;
if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
} elseif (isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // Validate CSRF token for POST requests
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("CSRF token mismatch");
    }
}

if (!$user_id) {
    if (isset($_POST['delete_user'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    } else {
        header("Location: index.php?tab=users&error=invalid_id");
    }
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
    if (isset($_POST['delete_user'])) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    } else {
        header("Location: index.php?tab=users&error=user_not_found");
    }
    exit;
}

// Prevent deletion of the current user
if ($user_id == $_SESSION['acc_id']) {
    if (isset($_POST['delete_user'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
    } else {
        header("Location: index.php?tab=users&error=cannot_delete_self");
    }
    exit;
}

// Archive the user instead of hard delete (set status to 'Deleted')
$stmt = $conn->prepare("UPDATE account SET acc_status = 'Deleted' WHERE acc_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    // Log the deletion action
    $user_name = $user_info['fname'] . ' ' . $user_info['lname'];
    logAdminAction($_SESSION['acc_id'], 'delete_user', "Archived user: $user_name (ID: $user_id)");
    
    if (isset($_POST['delete_user'])) {
        // Return JSON response for AJAX
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        // Redirect for GET requests
        header("Location: index.php?tab=users&status=deleted");
    }
} else {
    if (isset($_POST['delete_user'])) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    } else {
        header("Location: index.php?tab=users&error=delete_failed");
    }
}

$stmt->close();
exit;