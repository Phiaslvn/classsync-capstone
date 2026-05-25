<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('manage_rooms')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to manage rooms.']);
    exit;
}

// Check database connection
if (isset($db_connection_error) || !isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user info for department assignment
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    $bd_id = (int)($_POST['bd_id'] ?? 0);
    $rm_name = trim($_POST['rm_name'] ?? '');
    $rm_type = trim($_POST['rm_type'] ?? '');
    $rm_capacity = (int)($_POST['rm_capacity'] ?? 0);
    $rm_status = trim($_POST['rm_status'] ?? '');
    $rm_features = trim($_POST['rm_features'] ?? null);
    $dept_id = (int)($_POST['dept_id'] ?? 0);
    
    // Set department: use provided dept_id if Admin Support, otherwise use user's department
    if ($isAdminSupport && $dept_id > 0) {
        $final_dept_id = $dept_id;
    } else {
        $final_dept_id = $userDeptId > 0 ? $userDeptId : null;
    }

    if (empty($bd_id) || empty($rm_name) || empty($rm_type) || empty($rm_capacity) || empty($rm_status)) {
        $response['message'] = 'All required fields must be filled.';
    } else {
        try {
            // Check for duplicate room name within the same building
            $duplicate_check = $conn->prepare("SELECT rm_id FROM room WHERE bd_id = ? AND rm_name = ?");
            $duplicate_check->bind_param("is", $bd_id, $rm_name);
            $duplicate_check->execute();
            $duplicate_result = $duplicate_check->get_result();
            $duplicate_check->close();

            if ($duplicate_result->num_rows > 0) {
                $response['message'] = 'A room with this name already exists in this building. Please use a different name.';
            } else {
            $stmt = $conn->prepare("INSERT INTO room (bd_id, dept_id, rm_name, rm_type, rm_capacity, rm_status, rm_features) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iississ", $bd_id, $final_dept_id, $rm_name, $rm_type, $rm_capacity, $rm_status, $rm_features);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Room added successfully!';
            } else {
                    $response['message'] = 'Failed to add room. Database error occurred.';
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
exit;