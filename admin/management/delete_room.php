<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user info for department validation
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    $rm_id = (int)($_POST['rm_id'] ?? 0);

    if (empty($rm_id)) {
        $response['message'] = 'Room ID is required.';
    } else {
        try {
            // Verify room ownership (unless Admin Support)
            if (!$isAdminSupport && $userDeptId > 0) {
                $checkStmt = $conn->prepare("SELECT dept_id FROM room WHERE rm_id = ?");
                $checkStmt->bind_param("i", $rm_id);
                $checkStmt->execute();
                $roomResult = $checkStmt->get_result();
                $room = $roomResult->fetch_assoc();
                $checkStmt->close();
                
                if (!$room) {
                    $response['message'] = 'Room not found.';
                    echo json_encode($response);
                    exit;
                }
                
                if ($room['dept_id'] != $userDeptId) {
                    $response['message'] = 'You can only delete rooms in your department.';
                    echo json_encode($response);
                    exit;
                }
            }
            
            // Check if the room is used in any active schedules
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM schedule WHERE rm_id = ? AND schd_status = 'Active'");
            $check_stmt->bind_param("i", $rm_id);
            $check_stmt->execute();
            $check_stmt->bind_result($schedule_count);
            $check_stmt->fetch();
            $check_stmt->close();

            if ($schedule_count > 0) {
                $response['message'] = 'Cannot delete room. It is used in ' . $schedule_count . ' active schedule(s). Please remove it from schedules first.';
            } else {
                $stmt = $conn->prepare("DELETE FROM room WHERE rm_id = ?");
                $stmt->bind_param("i", $rm_id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Room deleted successfully!';
                } else {
                    $response['message'] = 'Failed to delete room.';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            http_response_code(500);
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
    http_response_code(405);
}

echo json_encode($response);
?>