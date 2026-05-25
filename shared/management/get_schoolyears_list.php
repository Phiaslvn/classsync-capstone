<?php
/**
 * Get List of School Years
 * Returns all available school years for dropdown
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => []];

try {
    $stmt = $conn->prepare("SELECT sy_id, sy_name, sy_year FROM schoolyear ORDER BY sy_year DESC, sy_name DESC");
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

