<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bd_id = (int)($_POST['bd_id'] ?? 0);
    $bd_desc = trim($_POST['bd_desc'] ?? '');
    $bd_status = trim($_POST['bd_status'] ?? '');

    if (empty($bd_id) || empty($bd_desc) || empty($bd_status)) {
        $response['message'] = 'Building ID, name, and status are required.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE building SET bd_desc = ?, bd_status = ? WHERE bd_id = ?");
            $stmt->bind_param("ssi", $bd_desc, $bd_status, $bd_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Building updated successfully!';
            } else {
                $response['message'] = 'Failed to update building.';
            }
            $stmt->close();
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