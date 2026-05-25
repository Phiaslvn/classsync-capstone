<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sec_id = (int)($_POST['sec_id'] ?? 0);
    $sec_num = (int)($_POST['sec_num'] ?? 0);
    $sec_name = trim($_POST['sec_name'] ?? '');

    if (empty($sec_id) || empty($sec_num) || empty($sec_name)) {
        $response['message'] = 'All fields are required.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE section SET sec_num = ?, sec_name = ? WHERE sec_id = ?");
            $stmt->bind_param("isi", $sec_num, $sec_name, $sec_id);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Section updated successfully!';
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
?>