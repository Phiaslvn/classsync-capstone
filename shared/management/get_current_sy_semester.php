<?php
/**
 * Get Current School Year and Semester from settings
 * Returns formatted: "2025 - 2026 | 1st Semester"
 * Updated to use settings table (consolidated from system_settings)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once '../utils/academic_period_sync.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.', 'data' => null];

try {
    // Calendar bootstrap only when settings empty; manual SY is preserved.
    syncActiveAcademicPeriod($conn, isset($_SESSION['acc_id']) ? (int)$_SESSION['acc_id'] : null, true);

    // Get active SY ID from settings (consolidated table)
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'active_school_year_id'
        LIMIT 1
    ");
    $stmt->execute();
    $sy_result = $stmt->get_result();
    $sy_row = $sy_result->fetch_assoc();
    
    if ($sy_row) {
        $sy_id = intval($sy_row['setting_value']);
        
        // Get active semester from settings (consolidated table)
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
        
        if ($sy_id > 0 && $semester) {
            // Get school year details from schoolyear table
            $stmt = $conn->prepare("
                SELECT sy_id, sy_year, sy_name 
                FROM schoolyear 
                WHERE sy_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $sy_id);
            $stmt->execute();
            $sy_data_result = $stmt->get_result();
            $sy_data = $sy_data_result->fetch_assoc();
            
            if ($sy_data) {
                // Format: "2025 - 2026 | 1st Semester"
                $formatted = $sy_data['sy_year'] . ' | ' . $semester;
                
                $response['data'] = [
                    'sy_id' => $sy_data['sy_id'],
                    'sy_year' => $sy_data['sy_year'],
                    'semester' => $semester,
                    'formatted' => $formatted
                ];
                $response['success'] = true;
                $response['message'] = 'Current school year and semester fetched successfully.';
            } else {
                $response['data'] = null;
                $response['success'] = true;
                $response['message'] = 'School year not found.';
            }
        } else {
            $response['data'] = null;
            $response['success'] = true;
            $response['message'] = 'No active school year and semester found.';
        }
    } else {
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

