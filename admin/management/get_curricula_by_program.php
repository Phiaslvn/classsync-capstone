<?php
/**
 * Get Curricula by Program API
 * Returns all curricula associated with a specific program ID.
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
    json_response(false, 'Unauthorized to view curricula.', [], 403);
}

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if ($program_id <= 0) {
    json_response(false, 'Program ID is required.', [], 400);
}

try {
    // A curriculum is linked to a department. A program is also linked to a department.
    // We find curricula that belong to the same department as the selected program.
    $stmt = $conn->prepare(
        "SELECT c.curr_id, c.curr_name, c.curr_yr 
         FROM curriculum c
         WHERE c.dept_id = (SELECT p.dept_id FROM program p WHERE p.program_id = ? LIMIT 1)
         ORDER BY c.curr_yr DESC, c.curr_name ASC"
    );
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $curricula = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    json_response(true, 'Curricula fetched successfully.', ['curricula' => $curricula]);
} catch (Exception $e) {
    error_log("Get Curricula by Program Error: " . $e->getMessage());
    json_response(false, 'A database error occurred.', [], 500);
}
?>