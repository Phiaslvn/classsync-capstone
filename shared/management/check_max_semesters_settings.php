<?php
/**
 * Check Maximum Semesters for Settings
 * Checks if the maximum number of semesters has been reached for a school year
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'max_reached' => false];

$sy_id = isset($_GET['sy_id']) ? intval($_GET['sy_id']) : 0;

if ($sy_id <= 0) {
    $response['message'] = 'Invalid School Year ID.';
    echo json_encode($response);
    exit;
}

try {
    // Count distinct semesters for this SY
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT semester) as semester_count
        FROM school_year_semesters
        WHERE sy_id = ?
    ");
    $stmt->bind_param("i", $sy_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $semester_count = intval($data['semester_count']);
    
    // Maximum is 3 semesters (1st, 2nd, and optional Summer)
    if ($semester_count >= 3) {
        $response['max_reached'] = true;
        $response['message'] = 'Maximum number of semesters (3) has been reached for this School Year.';
    } else {
        $response['max_reached'] = false;
        $response['message'] = 'More semesters can be added.';
    }
    
    $response['success'] = true;
    $response['semester_count'] = $semester_count;
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

