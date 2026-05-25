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

if (isset($_GET['id'])) {
    $schd_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE schd_id = ?");
    $stmt->bind_param("i", $schd_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($schedule = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['schedule'] = $schedule;
    } else {
        $response['message'] = 'Schedule not found.';
    }
}

echo json_encode($response);
?>