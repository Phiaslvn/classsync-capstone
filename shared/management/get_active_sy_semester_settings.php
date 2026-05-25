<?php
/**
 * Get Active School Year and Semester from Settings
 * Returns the currently active school year and semester from settings table
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => null];

try {
    // Get active SY from settings
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'active_school_year_id'
        LIMIT 1
    ");
    $stmt->execute();
    $sy_result = $stmt->get_result();
    
    if ($sy_row = $sy_result->fetch_assoc()) {
        $sy_id = intval($sy_row['setting_value']);
        
        // Get active semester from settings
        $stmt = $conn->prepare("
            SELECT setting_value 
            FROM settings 
            WHERE setting_key = 'active_semester'
            LIMIT 1
        ");
        $stmt->execute();
        $semester_result = $stmt->get_result();
        $semester_row = $semester_result->fetch_assoc();
        $semester = $semester_row ? $semester_row['setting_value'] : null;
        
        if ($sy_id > 0) {
            // Get school year details
            $stmt = $conn->prepare("SELECT sy_id, sy_name, sy_year FROM schoolyear WHERE sy_id = ?");
            $stmt->bind_param("i", $sy_id);
            $stmt->execute();
            $sy_details = $stmt->get_result();
            
            if ($sy_data = $sy_details->fetch_assoc()) {
                $response['data'] = [
                    'sy_id' => $sy_data['sy_id'],
                    'sy_name' => $sy_data['sy_name'],
                    'sy_year' => $sy_data['sy_year'],
                    'semester' => $semester
                ];
                $response['success'] = true;
                $response['message'] = 'Active school year and semester fetched successfully.';
            }
        }
    }
    
    if (!$response['success']) {
        $response['data'] = null;
        $response['success'] = true;
        $response['message'] = 'No active school year and semester found.';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

