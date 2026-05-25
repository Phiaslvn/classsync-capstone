<?php
/**
 * Delete Curriculum API
 * Handles deleting a curriculum record.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

function json_response($success, $message, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Check if user has permission
if (!hasPermission('manage_curriculum')) {
    json_response(false, 'Unauthorized to delete curriculum.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$curr_id = $data['curr_id'] ?? 0;

if (empty($curr_id)) {
    json_response(false, 'Curriculum ID is required.', 400);
}

try {
    // Note: You might want to check for related subjects before deleting
    // For now, we will proceed with a direct deletion.
    $stmt = $conn->prepare("DELETE FROM curriculum WHERE curr_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $curr_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            json_response(true, 'Curriculum deleted successfully.');
        } else {
            json_response(false, 'Curriculum not found or already deleted.', 404);
        }
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Delete Curriculum Error: " . $e->getMessage());
    // Check for foreign key constraint violation
    if ($conn->errno === 1451) {
        json_response(false, 'Cannot delete this curriculum because it has subjects or other records associated with it.', 409);
    }
    json_response(false, 'A database error occurred.', 500);
}
?>