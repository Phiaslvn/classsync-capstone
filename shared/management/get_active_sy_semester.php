<?php
/**
 * Get Active School Year and Semester
 * Returns the currently active school year and semester
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once '../utils/academic_period_sync.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => null];

try {
    // Calendar sync only seeds empty settings; manual admin SY is not overwritten here.
    syncActiveAcademicPeriod($conn, isset($_SESSION['acc_id']) ? (int)$_SESSION['acc_id'] : null, true);

    // Get the active school year and semester
    $stmt = $conn->prepare("
        SELECT 
            asys.id,
            asys.sy_id,
            sy.sy_name,
            sy.sy_year,
            asys.semester,
            asys.is_active,
            asys.created_at
        FROM active_school_year_semester asys
        JOIN schoolyear sy ON asys.sy_id = sy.sy_id
        WHERE asys.is_active = 1
        ORDER BY asys.created_at DESC
        LIMIT 1
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response['data'] = $row;
        $response['success'] = true;
        $response['message'] = 'Active school year and semester fetched successfully.';
    } else {
        $response['data'] = null;
        $response['success'] = true;
        $response['message'] = 'No active school year and semester found.';
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

