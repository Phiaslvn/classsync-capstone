<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $class_secno = (int)($_POST['class_secno'] ?? 0);

    if (empty($class_id) || empty($class_secno)) {
        $response['message'] = 'Class ID and number of sections are required.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE class SET class_secno = ? WHERE class_id = ?");
            $stmt->bind_param("ii", $class_secno, $class_id);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Class updated successfully!';
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
?>