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

try {
    // Get user info for department filtering
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    // Check if building table has dept_id column
    $checkColumn = $conn->query("SHOW COLUMNS FROM `building` LIKE 'dept_id'");
    $hasDeptIdColumn = $checkColumn && $checkColumn->num_rows > 0;
    
    if ($isAdminSupport) {
        // Admin Support sees all buildings
        if ($hasDeptIdColumn) {
            $stmt = $conn->prepare("SELECT b.bd_id, b.bd_desc, b.bd_status, b.dept_id, d.dept_name 
                                    FROM building b 
                                    LEFT JOIN department d ON b.dept_id = d.dept_id 
                                    ORDER BY b.bd_desc ASC");
        } else {
            // If dept_id doesn't exist in building table, get department from rooms
            $stmt = $conn->prepare("SELECT DISTINCT b.bd_id, b.bd_desc, b.bd_status, 
                                    MAX(r.dept_id) as dept_id, 
                                    MAX(d.dept_name) as dept_name
                                    FROM building b 
                                    LEFT JOIN room r ON b.bd_id = r.bd_id 
                                    LEFT JOIN department d ON r.dept_id = d.dept_id
                                    GROUP BY b.bd_id, b.bd_desc, b.bd_status
                                    ORDER BY b.bd_desc ASC");
        }
        $stmt->execute();
    } else {
        // Regular admins see:
        // 1. Buildings from their department
        // 2. Buildings that have rooms with granted access to their department
        if ($userDeptId > 0) {
            if ($hasDeptIdColumn) {
                // Filter by building's dept_id OR buildings with rooms that have granted access
                $stmt = $conn->prepare("SELECT DISTINCT b.bd_id, b.bd_desc, b.bd_status, b.dept_id, d.dept_name 
                                        FROM building b 
                                        LEFT JOIN department d ON b.dept_id = d.dept_id 
                                        LEFT JOIN room r ON b.bd_id = r.bd_id
                                        LEFT JOIN room_access ra ON r.rm_id = ra.rm_id AND ra.granted_to_dept_id = ? AND ra.status = 'Active'
                                        WHERE (b.dept_id = ? OR b.dept_id IS NULL OR ra.rm_id IS NOT NULL)
                                        ORDER BY b.bd_desc ASC");
                $stmt->bind_param("ii", $userDeptId, $userDeptId);
            } else {
                // If dept_id doesn't exist in building table yet, filter by rooms
                // Show buildings that have:
                // 1. Rooms belonging to user's department, OR
                // 2. Rooms with granted access to user's department
                // Also get department name from rooms for display
                $stmt = $conn->prepare("SELECT DISTINCT b.bd_id, b.bd_desc, b.bd_status, 
                                        MAX(r.dept_id) as dept_id, 
                                        MAX(d.dept_name) as dept_name
                                        FROM building b 
                                        INNER JOIN room r ON b.bd_id = r.bd_id 
                                        LEFT JOIN department d ON r.dept_id = d.dept_id
                                        LEFT JOIN room_access ra ON r.rm_id = ra.rm_id AND ra.granted_to_dept_id = ? AND ra.status = 'Active'
                                        WHERE (r.dept_id = ? OR ra.rm_id IS NOT NULL)
                                        GROUP BY b.bd_id, b.bd_desc, b.bd_status
                                        ORDER BY b.bd_desc ASC");
                $stmt->bind_param("ii", $userDeptId, $userDeptId);
            }
            $stmt->execute();
        } else {
            // No department assigned, return empty array
            $response['success'] = true;
            $response['message'] = 'No department assigned.';
            $response['data'] = [];
            echo json_encode($response);
            exit;
        }
    }
    
    $result = $stmt->get_result();
    $buildings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Buildings fetched successfully.';
    $response['data'] = $buildings;
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>