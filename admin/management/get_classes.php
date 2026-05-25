<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions - allow both manage and view permissions
if (!hasPermission('manage_rooms') && !hasPermission('view_rooms')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to view rooms.']);
    exit;
}

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => []];

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

try {
    $query = "SELECT c.class_id, c.class_lvl, c.class_term, c.class_secno, 
                sy.sy_name, curr.curr_name
         FROM class c
         JOIN schoolyear sy ON c.sy_id = sy.sy_id
         JOIN curriculum curr ON c.curr_id = curr.curr_id";
    
    $params = [];
    $types = '';
    
    // Filter by department - classes belong to curriculum which has dept_id
    if (!$isAdminSupport && $userDeptId > 0) {
        $query .= " WHERE curr.dept_id = ?";
        $params[] = $userDeptId;
        $types = 'i';
    }
    
    $query .= " ORDER BY sy.sy_name DESC, curr.curr_name ASC, c.class_lvl ASC, c.class_term ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>