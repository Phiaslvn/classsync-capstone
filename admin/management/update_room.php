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
    $rm_name = trim($_POST['rm_name'] ?? '');
    $rm_type = trim($_POST['rm_type'] ?? '');
    $rm_capacity = (int)($_POST['rm_capacity'] ?? 0);
    $rm_status = trim($_POST['rm_status'] ?? '');
    $rm_features = trim($_POST['rm_features'] ?? null);
    $dept_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;

    if (empty($rm_id) || empty($rm_name) || empty($rm_type) || empty($rm_capacity) || empty($rm_status)) {
        $response['message'] = 'All required fields must be filled.';
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
                
                if (!$room || $room['dept_id'] != $userDeptId) {
                    $response['message'] = 'You can only edit rooms in your department.';
                    echo json_encode($response);
                    exit;
                }
            }
            
            // Update room (only allow dept_id change for Admin Support)
            if ($isAdminSupport && $dept_id !== null) {
                $stmt = $conn->prepare("UPDATE room SET rm_name = ?, rm_type = ?, rm_capacity = ?, rm_status = ?, rm_features = ?, dept_id = ? WHERE rm_id = ?");
                $stmt->bind_param("ssissii", $rm_name, $rm_type, $rm_capacity, $rm_status, $rm_features, $dept_id, $rm_id);
            } else {
                $stmt = $conn->prepare("UPDATE room SET rm_name = ?, rm_type = ?, rm_capacity = ?, rm_status = ?, rm_features = ? WHERE rm_id = ?");
                $stmt->bind_param("ssissi", $rm_name, $rm_type, $rm_capacity, $rm_status, $rm_features, $rm_id);
            }

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Room updated successfully!';
            } else {
                $response['message'] = 'Failed to update room.';
            }
            $stmt->close();
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