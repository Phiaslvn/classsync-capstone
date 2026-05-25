<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => []];

try {
    // Prepare and execute the query to get school years, ordering by the most recent first.
    $stmt = $conn->prepare("SELECT sy_id, sy_name FROM schoolyear ORDER BY sy_name DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    $response['message'] = 'School years fetched successfully.';
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>