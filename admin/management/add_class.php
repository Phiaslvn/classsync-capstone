<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sy_id = (int)($_POST['sy_id'] ?? 0);
    $curr_id = (int)($_POST['curr_id'] ?? 0);
    $class_lvl = (int)($_POST['class_lvl'] ?? 0);
    $class_term = (int)($_POST['class_term'] ?? 0);
    $class_secno = (int)($_POST['class_secno'] ?? 0);

    if (empty($sy_id) || empty($curr_id) || empty($class_lvl) || empty($class_term) || empty($class_secno)) {
        $response['message'] = 'All fields are required.';
    } else {
        try {
            // Check for duplicates
            $check_stmt = $conn->prepare("SELECT class_id FROM class WHERE sy_id = ? AND curr_id = ? AND class_lvl = ? AND class_term = ?");
            $check_stmt->bind_param("iiii", $sy_id, $curr_id, $class_lvl, $class_term);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $response['message'] = 'This class definition already exists.';
            } else {
                $stmt = $conn->prepare("INSERT INTO class (sy_id, curr_id, class_lvl, class_term, class_secno) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiii", $sy_id, $curr_id, $class_lvl, $class_term, $class_secno);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Class added successfully!';
                } else {
                    $response['message'] = 'Failed to add class.';
                }
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
?>