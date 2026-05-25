<?php
/**
 * Get School Years for Modal Dropdown
 * Returns distinct school years for selection
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => []];

try {
    // Get distinct school years
    $stmt = $conn->prepare("
        SELECT DISTINCT sy_year 
        FROM schoolyear 
        ORDER BY sy_year DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schoolYears = [];
    while ($row = $result->fetch_assoc()) {
        $schoolYears[] = $row['sy_year'];
    }
    
    $response['data'] = $schoolYears;
    $response['success'] = true;
    $response['message'] = 'School years fetched successfully.';
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

