<?php
// Start output buffering to prevent any premature output
ob_start();

// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

// Check if user is logged in and has admin support role
requireRole('Admin support', '../../public/index.php');

// Clean any output that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Check if user_id is provided
if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit;
}

$user_id = intval($_POST['user_id']);

try {
    $stmt = $conn->prepare("
        SELECT 
            a.acc_id, a.fname, a.lname, a.minitial, a.acc_user, a.acc_email, a.acc_status, a.dept_id, a.profile_picture,
            r.id as role_id, r.role_name
        FROM account a
        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE a.acc_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Build profile picture path
        $profile_path = null;
        if (!empty($user['profile_picture'])) {
            $profile_path = '../../' . $user['profile_picture'];
        }
        $user['profile_path'] = $profile_path;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
    $stmt->close();
} catch (Exception $e) {
    ob_clean();
    error_log("Error in get_user_data_for_edit.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading user data'
    ]);
}
exit;
?>

