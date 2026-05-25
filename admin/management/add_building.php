<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('manage_rooms')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to manage rooms.']);
    exit;
}

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bd_desc = trim($_POST['bd_desc'] ?? '');
    $bd_status = trim($_POST['bd_status'] ?? '');

    if (empty($bd_desc) || empty($bd_status)) {
        $response['message'] = 'Building name and status are required.';
    } else {
        try {
            // Check for duplicate building name
            $duplicate_check = $conn->prepare("SELECT bd_id FROM building WHERE bd_desc = ?");
            $duplicate_check->bind_param("s", $bd_desc);
            $duplicate_check->execute();
            $duplicate_result = $duplicate_check->get_result();
            $duplicate_check->close();

            if ($duplicate_result->num_rows > 0) {
                $response['message'] = 'A building with this name already exists. Please use a different name.';
            } else {
                $stmt = $conn->prepare("INSERT INTO building (bd_desc, bd_status) VALUES (?, ?)");
                $stmt->bind_param("ss", $bd_desc, $bd_status);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Building added successfully!';
                } else {
                    $response['message'] = 'Failed to add building.';
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