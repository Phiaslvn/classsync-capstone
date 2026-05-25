<?php
/**
 * Delete Subject API
 * Handles deleting a subject record with validation and integrity checks.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

function json_response($success, $message, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if (!hasPermission('manage_subjects')) {
    json_response(false, 'Unauthorized to delete subject.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

$subj_id = (int)($_POST['subj_id'] ?? 0);

if ($subj_id <= 0) {
    json_response(false, 'Invalid subject ID.', 400);
}

try {
    // Check for dependencies: Are there schedules linked to this subject?
    // Check for ALL schedules (including deleted ones) because foreign key constraint applies to all
    $check_stmt = $conn->prepare("SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN schd_status != 'Deleted' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN schd_status = 'Deleted' THEN 1 ELSE 0 END) as deleted_count
        FROM schedule WHERE subj_id = ?");
    if (!$check_stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }
    
    $check_stmt->bind_param("i", $subj_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    // If there are active schedules, prevent deletion
    if ($result['active_count'] > 0) {
        $message = 'Cannot delete this subject because it has ' . $result['active_count'] . ' active schedule(s)';
        if ($result['deleted_count'] > 0) {
            $message .= ' and ' . $result['deleted_count'] . ' deleted schedule(s)';
        }
        $message .= '. Please remove or delete the active schedules first.';
        json_response(false, $message, 409); // 409 Conflict
    }

    // If there are only deleted schedules, delete them first to allow subject deletion
    if ($result['deleted_count'] > 0) {
        $delete_schedules_stmt = $conn->prepare("DELETE FROM schedule WHERE subj_id = ? AND schd_status = 'Deleted'");
        if (!$delete_schedules_stmt) {
            throw new Exception("SQL Prepare failed for schedule deletion: " . $conn->error);
        }
        
        $delete_schedules_stmt->bind_param("i", $subj_id);
        if (!$delete_schedules_stmt->execute()) {
            $delete_schedules_stmt->close();
            throw new Exception("Failed to delete related schedules: " . $delete_schedules_stmt->error);
        }
        $deleted_schedules_count = $delete_schedules_stmt->affected_rows;
        $delete_schedules_stmt->close();
    }

    // Proceed with subject deletion
    $stmt = $conn->prepare("DELETE FROM subject WHERE subj_id = ?");
    if (!$stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $subj_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message = 'Subject deleted successfully.';
            if (isset($deleted_schedules_count) && $deleted_schedules_count > 0) {
                $message .= ' Also removed ' . $deleted_schedules_count . ' related deleted schedule(s).';
            }
            json_response(true, $message);
        } else {
            json_response(false, 'Subject not found or already deleted.', 404);
        }
    } else {
        // Check if it's a foreign key constraint error
        if (strpos($stmt->error, '1451') !== false || strpos($stmt->error, 'foreign key constraint') !== false) {
            json_response(false, 'Cannot delete this subject because it is still referenced by one or more schedules. Please remove all related schedules first.', 409);
        } else {
            throw new Exception("SQL Execute failed: " . $stmt->error);
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Delete Subject Error: " . $e->getMessage());
    json_response(false, 'A database error occurred while deleting the subject.', 500);
}
?>