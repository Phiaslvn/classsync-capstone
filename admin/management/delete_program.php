<?php
/**
 * Delete Program API
 * Handles deleting an existing program with validation and integrity checks.
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
header('Content-Type: application/json');

function json_response($success, $message, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Check if user has permission to manage curriculum
if (!hasPermission('manage_curriculum')) {
    json_response(false, 'Unauthorized to delete program.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

$program_id = (int)($_POST['program_id'] ?? 0);

if ($program_id <= 0) {
    json_response(false, 'Invalid Program ID provided.', 400);
}

try {
    // Check for dependencies: Are there subjects linked to this program?
    $check_stmt = $conn->prepare("SELECT COUNT(*) as subject_count FROM subject WHERE program_id = ?");
    $check_stmt->bind_param("i", $program_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($result['subject_count'] > 0) {
        json_response(false, 'Cannot delete this program because it has ' . $result['subject_count'] . ' subject(s) associated with it. Please reassign or delete the subjects first.', 409); // 409 Conflict
    }

    // Proceed with deletion
    $stmt = $conn->prepare("DELETE FROM program WHERE program_id = ?");
    if (!$stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $program_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            json_response(true, 'Program deleted successfully.');
        } else {
            json_response(false, 'Program not found or already deleted.', 404);
        }
    } else {
        throw new Exception("SQL Execute failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Delete Program Error: " . $e->getMessage());
    json_response(false, 'A database error occurred while deleting the program.', 500);
}
?>