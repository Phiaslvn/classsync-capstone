<?php
/**
 * Get list of departments that can be granted access to rooms
 * Excludes the current user's department (can't grant access to yourself)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Only Admin (Department Head) and Admin Support can access this
if (!isAdmin() && !isAdminSupport()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    // Get all departments except the current user's department
    if ($isAdminSupport) {
        // Admin Support sees all departments
        $query = "SELECT dept_id, dept_name FROM department ORDER BY dept_name";
        $stmt = $conn->prepare($query);
    } else {
        // Regular admins see all departments except their own
        if ($userDeptId > 0) {
            $query = "SELECT dept_id, dept_name FROM department WHERE dept_id != ? ORDER BY dept_name";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $userDeptId);
        } else {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $departments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $departments
    ]);
    
} catch (Exception $e) {
    error_log("Get Available Departments Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>

