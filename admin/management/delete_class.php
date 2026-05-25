<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)($_POST['class_id'] ?? 0);

    if (empty($class_id)) {
        $response['message'] = 'Class ID is required.';
    } else {
        try {
            // Check for associated sections
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM section WHERE class_id = ?");
            $check_stmt->bind_param("i", $class_id);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();

            if ($count > 0) {
                $response['message'] = 'Cannot delete. This class has ' . $count . ' section(s) associated with it.';
            } else {
                $stmt = $conn->prepare("DELETE FROM class WHERE class_id = ?");
                $stmt->bind_param("i", $class_id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Class deleted successfully!';
                }
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
?>