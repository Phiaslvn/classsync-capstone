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
    // Get user details with related information including workload fields
    $sql = "SELECT 
                a.acc_id, a.fname, a.lname, a.minitial, a.acc_user, a.acc_email, 
                a.profile_picture, a.acc_status, a.created_at,
                d.dept_name, c.college_name, r.role_name,
                creator.fname as created_by_fname, creator.lname as created_by_lname,
                i.inst_id, i.rank, i.designation, i.inst_status,
                COALESCE(i.administration_hours, 0) AS administration_hours,
                COALESCE(i.instruction_hours, 0) AS instruction_hours,
                COALESCE(i.research_hours, 0) AS research_hours,
                COALESCE(i.extension_hours, 0) AS extension_hours,
                COALESCE(i.instructional_functions_hours, 0) AS instructional_functions_hours,
                COALESCE(i.consultation_hours, 0) AS consultation_hours
            FROM account a
            LEFT JOIN department d ON a.dept_id = d.dept_id
            LEFT JOIN college c ON d.college_id = c.college_id
            LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
            LEFT JOIN roles r ON ur.role_id = r.id
            LEFT JOIN account creator ON a.created_by = creator.acc_id
            LEFT JOIN instructor i ON a.acc_user = i.inst_user
            WHERE a.acc_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Format the data
    $user['created_by_name'] = $user['created_by_fname'] && $user['created_by_lname'] 
        ? $user['created_by_fname'] . ' ' . $user['created_by_lname'] 
        : 'System';
    
    // Get last login (if you have a login tracking table)
    $user['last_login'] = 'Never logged in'; // You can implement this based on your login tracking
    
    echo json_encode(['success' => true, 'user' => $user]);
    
} catch (Exception $e) {
    error_log("Error in get_user_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
