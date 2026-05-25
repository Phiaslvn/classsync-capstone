<?php
/**
 * Set Active School Year and Semester in Settings
 * Handles setting the active school year and semester with validation
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

// Check if user has admin privileges
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Admin support')) {
    $response['message'] = 'Unauthorized access. Only Admin and Admin Support can set School Year and Semester.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$sy_id = isset($_POST['sy_id']) ? intval($_POST['sy_id']) : 0;
$semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
$user_id = isset($_SESSION['acc_id']) ? intval($_SESSION['acc_id']) : null;

if ($sy_id <= 0 || empty($semester)) {
    $response['message'] = 'School Year and Semester are required.';
    echo json_encode($response);
    exit;
}

if (!in_array($semester, ['1st', '2nd', 'Summer'])) {
    $response['message'] = 'Invalid semester. Must be 1st, 2nd, or Summer.';
    echo json_encode($response);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get school year information
    $stmt = $conn->prepare("SELECT sy_id, sy_name, sy_year FROM schoolyear WHERE sy_id = ?");
    $stmt->bind_param("i", $sy_id);
    $stmt->execute();
    $sy_result = $stmt->get_result();
    
    if ($sy_result->num_rows === 0) {
        throw new Exception('School Year not found.');
    }
    
    $sy_data = $sy_result->fetch_assoc();
    $sy_year = $sy_data['sy_year'];
    
    // Extract start year from SY (e.g., "2024-2025" -> 2024)
    $sy_start_year = intval(explode('-', $sy_year)[0]);
    $current_year = date('Y');
    
    // Check if a school year for the current academic year already exists in settings
    $stmt = $conn->prepare("
        SELECT s.setting_value, sy.sy_year
        FROM settings s
        JOIN schoolyear sy ON s.setting_value = sy.sy_id
        WHERE s.setting_key = 'active_school_year_id'
        AND YEAR(s.updated_at) = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $current_year);
    $stmt->execute();
    $active_check = $stmt->get_result();
    
    if ($active_check->num_rows > 0) {
        $active_row = $active_check->fetch_assoc();
        $active_sy_id = intval($active_row['setting_value']);
        // Check if trying to set a different SY for the same calendar year
        if ($active_sy_id != $sy_id) {
            $active_sy_year = $active_row['sy_year'];
            $conn->rollback();
            $response['message'] = 'A School Year (' . $active_sy_year . ') has already been set for this academic year. You cannot set a different School Year for the same academic year.';
            echo json_encode($response);
            exit;
        }
    }
    
    // Check how many distinct semesters the current SY already has
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT semester) as semester_count
        FROM school_year_semesters
        WHERE sy_id = ?
    ");
    $stmt->bind_param("i", $sy_id);
    $stmt->execute();
    $semester_count_result = $stmt->get_result();
    $semester_count_data = $semester_count_result->fetch_assoc();
    $semester_count = intval($semester_count_data['semester_count']);
    
    // Check if this specific semester already exists for this SY
    $stmt = $conn->prepare("
        SELECT id FROM school_year_semesters
        WHERE sy_id = ? AND semester = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $sy_id, $semester);
    $stmt->execute();
    $existing_semester = $stmt->get_result();
    $semester_exists = $existing_semester->num_rows > 0;
    
    // If semester doesn't exist, check if we can add it (max 2-3 semesters per SY)
    if (!$semester_exists) {
        // Check if max semesters reached (2 or 3)
        if ($semester_count >= 3) {
            $conn->rollback();
            $response['message'] = 'Maximum number of semesters (3) has been reached for this School Year.';
            echo json_encode($response);
            exit;
        }
        
        // If we have 2 semesters and trying to add a 3rd, check if it's Summer
        if ($semester_count >= 2 && $semester !== 'Summer') {
            $conn->rollback();
            $response['message'] = 'Maximum number of regular semesters (2) reached. Only Summer semester can be added as the 3rd semester.';
            echo json_encode($response);
            exit;
        }
        
        // Insert new semester record
        $stmt = $conn->prepare("
            INSERT INTO school_year_semesters (sy_id, semester)
            VALUES (?, ?)
        ");
        $stmt->bind_param("is", $sy_id, $semester);
        $stmt->execute();
    }
    
    // Update or insert active school year setting
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
        VALUES ('active_school_year_id', ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $sy_id_str = (string)$sy_id;
    $stmt->bind_param("si", $sy_id_str, $user_id);
    $stmt->execute();
    
    // Update or insert active semester setting
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
        VALUES ('active_semester', ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $stmt->bind_param("si", $semester, $user_id);
    $stmt->execute();
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'School Year and Semester set successfully.';
    
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

