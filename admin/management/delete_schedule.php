<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if (!hasPermission('manage_schedules')) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schd_id = intval($_POST['schd_id'] ?? 0);

    if ($schd_id > 0) {
        try {
            // Hard delete: permanently remove schedule row from database
            $stmt = $conn->prepare("DELETE FROM schedule WHERE schd_id = ?");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }

            $stmt->bind_param("i", $schd_id);
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();

                if ($affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Schedule deleted successfully.';
                } else {
                    $response['message'] = 'Schedule not found or already deleted.';
                }
            } else {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to execute statement: ' . $error);
            }
        } catch (Exception $e) {
            error_log('Delete schedule error: ' . $e->getMessage());
            $response['message'] = 'Failed to delete schedule: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Invalid schedule ID.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>