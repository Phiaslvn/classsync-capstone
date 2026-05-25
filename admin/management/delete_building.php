<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bd_id = (int)($_POST['bd_id'] ?? 0);

    if (empty($bd_id)) {
        $response['message'] = 'Building ID is required.';
    } else {
        try {
            // Check if there are rooms associated with this building
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM room WHERE bd_id = ?");
            $check_stmt->bind_param("i", $bd_id);
            $check_stmt->execute();
            $check_stmt->bind_result($room_count);
            $check_stmt->fetch();
            $check_stmt->close();

            if ($room_count > 0) {
                $response['message'] = 'Cannot delete building. It has ' . $room_count . ' room(s) associated with it. Please delete or reassign the rooms first.';
            } else {
                $stmt = $conn->prepare("DELETE FROM building WHERE bd_id = ?");
                $stmt->bind_param("i", $bd_id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Building deleted successfully!';
                } else {
                    $response['message'] = 'Failed to delete building.';
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
?>