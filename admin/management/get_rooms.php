<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('manage_rooms') && !hasPermission('view_rooms')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to view rooms.']);
    exit;
}

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => []];
$bd_id = (int)($_GET['bd_id'] ?? 0);

if (empty($bd_id)) {
    $response['message'] = 'Building ID is required.';
    echo json_encode($response);
    exit;
}

try {
    // Get user info for department filtering
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    // Query rooms with department and access filtering
    if ($isAdminSupport) {
        // Admin Support sees all rooms
        $stmt = $conn->prepare("SELECT r.rm_id, r.rm_name, r.rm_type, r.rm_capacity, r.rm_status, r.rm_features, r.dept_id, d.dept_name 
                                FROM room r 
                                LEFT JOIN department d ON r.dept_id = d.dept_id
                                WHERE r.bd_id = ? 
                                ORDER BY r.rm_name ASC");
        $stmt->bind_param("i", $bd_id);
    } else {
        // Regular admins see:
        // 1. Their own department's rooms
        // 2. Rooms from other departments that have APPROVED room requests (req_status = 'Accepted')
        if ($userDeptId > 0) {
            $stmt = $conn->prepare("SELECT DISTINCT r.rm_id, r.rm_name, r.rm_type, r.rm_capacity, r.rm_status, r.rm_features, r.dept_id, d.dept_name
                                    FROM room r 
                                    LEFT JOIN department d ON r.dept_id = d.dept_id
                                    WHERE r.bd_id = ? 
                                    AND (
                                        r.dept_id = ? 
                                        OR EXISTS (
                                            SELECT 1 FROM room_request rr
                                            JOIN instructor i ON rr.inst_id = i.inst_id
                                            JOIN account a ON i.inst_user = a.acc_user
                                            WHERE rr.rm_id = r.rm_id 
                                            AND a.dept_id = ?
                                            AND rr.req_status = 'Accepted'
                                            AND (
                                                -- Regular Class: Always included (no automatic expiration)
                                                rr.req_comment NOT LIKE '%[Class Type: Make Up Class]%'
                                                OR
                                                -- Make Up Class: Check both date expiration AND time expiration
                                                (
                                                    rr.req_comment LIKE '%[Class Type: Make Up Class]%'
                                                    -- Check if date hasn't expired (next Monday hasn't passed)
                                                    AND DATE(rr.req_date) + INTERVAL (
                                                        CASE DAYOFWEEK(DATE(rr.req_date))
                                                            WHEN 2 THEN 7
                                                            ELSE (9 - DAYOFWEEK(DATE(rr.req_date))) % 7
                                                        END
                                                    ) DAY > CURDATE()
                                                    -- For Make Up Class: If today, check if time slot has passed
                                                    AND (
                                                        -- If request date is NOT today, include it (future date)
                                                        DATE(rr.req_date) != CURDATE()
                                                        OR
                                                        -- If request date IS today, only include if current time < schd_end
                                                        (DATE(rr.req_date) = CURDATE() AND TIME(NOW()) < rr.schd_end)
                                                    )
                                                )
                                            )
                                        )
                                    )
                                    ORDER BY r.rm_name ASC");
            $stmt->bind_param("iii", $bd_id, $userDeptId, $userDeptId);
        } else {
            $response['message'] = 'No department assigned.';
            echo json_encode($response);
            exit;
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $rooms = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response['success'] = true;
    $response['data'] = $rooms;
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>