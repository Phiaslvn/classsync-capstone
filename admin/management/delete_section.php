<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sec_id = (int)($_POST['sec_id'] ?? 0);

    if (empty($sec_id)) {
        $response['message'] = 'Section ID is required.';
    } else {
        try {
            // Check for dependencies: Only count Active schedules (ignore Deleted ones)
            // Also check for Deleted schedules to optionally clean them up
            $check_stmt = $conn->prepare("SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN schd_status = 'Active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN schd_status = 'Deleted' THEN 1 ELSE 0 END) as deleted_count
                FROM schedule WHERE sec_id = ?");
            $check_stmt->bind_param("i", $sec_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            $active_count = (int)($result['active_count'] ?? 0);
            $deleted_count = (int)($result['deleted_count'] ?? 0);

            // If there are active schedules, prevent deletion
            if ($active_count > 0) {
                $message = 'Cannot delete. This section is used in ' . $active_count . ' active schedule(s)';
                if ($deleted_count > 0) {
                    $message .= ' and ' . $deleted_count . ' deleted schedule(s)';
                }
                $message .= '. Please remove or delete the active schedules first.';
                $response['message'] = $message;
            } else {
                // If there are no active schedules, delete ALL schedules for this section
                // (regardless of status) to satisfy foreign key constraint
                $deleted_schedules_count = 0;
                $total_count = (int)($result['total_count'] ?? 0);
                
                if ($total_count > 0) {
                    // Delete ALL schedules for this section (not just Deleted ones)
                    // This is necessary because foreign key constraint requires all schedules to be removed
                    $delete_schedules_stmt = $conn->prepare("DELETE FROM schedule WHERE sec_id = ?");
                    if ($delete_schedules_stmt) {
                        $delete_schedules_stmt->bind_param("i", $sec_id);
                        if ($delete_schedules_stmt->execute()) {
                            $deleted_schedules_count = $delete_schedules_stmt->affected_rows;
                        }
                        $delete_schedules_stmt->close();
                    }
                }

                // Now delete the section
                $stmt = $conn->prepare("DELETE FROM section WHERE sec_id = ?");
                if (!$stmt) {
                    throw new Exception("Failed to prepare section deletion statement: " . $conn->error);
                }
                
                $stmt->bind_param("i", $sec_id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $message = 'Section deleted successfully!';
                    if ($deleted_schedules_count > 0) {
                        $message .= ' Also removed ' . $deleted_schedules_count . ' related schedule(s).';
                    }
                    $response['message'] = $message;
                    $stmt->close();
                } else {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception("Failed to delete section: " . $error);
                }
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
?>