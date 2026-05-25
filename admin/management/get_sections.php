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

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => [], 'class_info' => null];
$class_id = (int)($_GET['class_id'] ?? 0);

if (empty($class_id)) {
    $response['message'] = 'Class ID is required.';
    echo json_encode($response);
    exit;
}

try {
    // Get sections
    $stmt = $conn->prepare("SELECT sec_id, sec_num, sec_name FROM section WHERE class_id = ? ORDER BY sec_num ASC");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $response['data'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get class info (total sections allowed)
    $stmt = $conn->prepare("SELECT class_secno FROM class WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $response['class_info'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>