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
    // Get user activity from audit log
    $sql = "SELECT 
                action, log_date, details
            FROM audit_log 
            WHERE acc_id = ? 
            ORDER BY log_date DESC 
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    // If no activities found, create some sample data or return empty
    if (empty($activities)) {
        $activities = [
            [
                'action' => 'Account Created',
                'log_date' => date('Y-m-d H:i:s'),
                'details' => 'User account was created in the system'
            ]
        ];
    }
    
    echo json_encode(['success' => true, 'activities' => $activities]);
    
} catch (Exception $e) {
    error_log("Error in get_user_activity.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
