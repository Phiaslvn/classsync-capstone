<?php
/**
 * Mark all notifications as read
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Get user info
$userInfo = getUserInfo();

if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

try {
    // Mark all notifications as read by updating audit_log.details JSON column
    // Add marked_as_read timestamp to each notification's details
    
    // Get all recent notification IDs (last 30 days)
    $acc_id = $_SESSION['acc_id'] ?? 0;
    $userRole = $_SESSION['role'] ?? '';
    $isAdminSupport = isAdminSupport();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $currentTimestamp = date('Y-m-d H:i:s');
    
    // Fetch recent notification IDs and details based on role (same logic as get_notifications.php)
    if ($isAdminSupport) {
        $query = "
            SELECT log_id, details
            FROM audit_log
            WHERE log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY log_date DESC
            LIMIT 50
        ";
        $stmt = $conn->prepare($query);
    } elseif ($userRole === 'Admin') {
        $query = "
            SELECT DISTINCT al.log_id, al.details
            FROM audit_log al
            JOIN account a ON al.acc_id = a.acc_id
            LEFT JOIN room r ON JSON_EXTRACT(al.details, '$.room_id') = r.rm_id
            WHERE al.log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND (
                (al.action LIKE '%room request%' AND r.dept_id = ? AND JSON_EXTRACT(al.details, '$.requester_dept_id') != ?)
                OR
                ((al.action LIKE '%Approved room request%' OR al.action LIKE '%Declined room request%') 
                 AND JSON_EXTRACT(al.details, '$.requester_dept_id') = ?)
                OR
                (al.action LIKE '%verified%' AND a.dept_id = ?)
                OR
                (al.action LIKE '%Added new instructor account%' AND JSON_EXTRACT(al.details, '$.department_id') = ?)
                OR
                (a.dept_id = ?)
                OR
                ((al.action LIKE '%Granted room access%' OR al.action LIKE '%granted access%') 
                 AND JSON_EXTRACT(al.details, '$.granted_to_dept_id') = ?)
            )
            ORDER BY al.log_date DESC
            LIMIT 50
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiiiii", $userDeptId, $userDeptId, $userDeptId, $userDeptId, $userDeptId, $userDeptId, $userDeptId);
    } else {
        $query = "
            SELECT al.log_id, al.details
            FROM audit_log al
            JOIN account a ON al.acc_id = a.acc_id
            WHERE al.log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND (
                ((al.action LIKE '%Approved room request%' OR al.action LIKE '%Declined room request%') 
                 AND JSON_EXTRACT(al.details, '$.requester_dept_id') = ?)
                OR
                ((al.action LIKE '%room request submitted%' OR al.action LIKE '%Room request submitted%') 
                 AND JSON_EXTRACT(al.details, '$.requester_dept_id') = ?)
                OR
                (al.acc_id = ? AND al.action LIKE '%verified%')
                OR
                ((al.action LIKE '%Granted room access%' OR al.action LIKE '%granted access%') 
                 AND JSON_EXTRACT(al.details, '$.granted_to_dept_id') = ?)
            )
            ORDER BY al.log_date DESC
            LIMIT 50
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiii", $userDeptId, $userDeptId, $acc_id, $userDeptId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Update each notification's details JSON to add marked_as_read timestamp
    $updateStmt = $conn->prepare("UPDATE audit_log SET details = JSON_SET(COALESCE(details, '{}'), '$.marked_as_read', ?) WHERE log_id = ?");
    
    while ($row = $result->fetch_assoc()) {
        $notificationId = (int)$row['log_id'];
        $details = $row['details'] ?? '{}';
        
        // Only update if not already marked as read
        $detailsArray = json_decode($details, true) ?? [];
        if (!isset($detailsArray['marked_as_read'])) {
            $updateStmt->bind_param("si", $currentTimestamp, $notificationId);
            $updateStmt->execute();
        }
    }
    
    $updateStmt->close();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'All notifications marked as read.'
    ]);
    
} catch (Exception $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while marking notifications as read.'
    ]);
}
?>

