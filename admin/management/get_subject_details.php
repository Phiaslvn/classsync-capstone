<?php
/**
 * Get Subject Details API
 * Fetches a single subject record by its ID.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

function json_response($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

if (!hasPermission('manage_subjects')) {
    json_response(false, 'Unauthorized to view subject details.', [], 403);
}

$subj_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($subj_id <= 0) {
    json_response(false, 'Invalid subject ID.', [], 400);
}

try {
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)($userInfo['dept_id'] ?? 0) : 0;
    $isAdminSupport = function_exists('isAdminSupport') ? isAdminSupport() : false;

    $stmt = $conn->prepare(
        "SELECT s.*, p.program_name, c.curr_name 
         FROM subject s
         LEFT JOIN program p ON s.program_id = p.program_id
         LEFT JOIN curriculum c ON s.curr_id = c.curr_id
         WHERE s.subj_id = ?"
    );
    $stmt->bind_param("i", $subj_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
        json_response(false, 'Subject not found.', [], 404);
    }

    if (!$isAdminSupport && ($userDeptId <= 0 || (int)$result['dept_id'] !== $userDeptId)) {
        json_response(false, 'Unauthorized to view this subject.', [], 403);
    }

    json_response(true, 'Subject details fetched.', $result);

} catch (Exception $e) {
    error_log("Get Subject Details Error: " . $e->getMessage());
    json_response(false, 'A database error occurred.', [], 500);
}
?>