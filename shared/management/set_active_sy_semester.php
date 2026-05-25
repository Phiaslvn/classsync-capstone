<?php
/**
 * Set Active School Year and Semester
 * Handles setting the active school year and semester with validation
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

// Check if user has admin privileges
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Admin support')) {
    $response['message'] = 'Unauthorized access.';
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
    $current_year = date('Y');
    
    // Extract year from SY (e.g., "2024-2025" -> 2024)
    $sy_start_year = intval(explode('-', $sy_year)[0]);
    
    // Check if a school year for the current calendar year already exists and is active
    $stmt = $conn->prepare("
        SELECT asys.id, asys.sy_id, sy.sy_year
        FROM active_school_year_semester asys
        JOIN schoolyear sy ON asys.sy_id = sy.sy_id
        WHERE asys.is_active = 1
        AND YEAR(asys.created_at) = ?
    ");
    $stmt->bind_param("i", $current_year);
    $stmt->execute();
    $active_check = $stmt->get_result();
    
    if ($active_check->num_rows > 0) {
        $active_row = $active_check->fetch_assoc();
        // Check if trying to set a different SY for the same calendar year
        if ($active_row['sy_id'] != $sy_id) {
            $conn->rollback();
            $response['message'] = 'A School Year for ' . $current_year . ' has already been set. You cannot set a different School Year for the same calendar year.';
            echo json_encode($response);
            exit;
        }
    }
    
    // Check if this specific semester already exists for this SY
    $stmt = $conn->prepare("
        SELECT id FROM active_school_year_semester
        WHERE sy_id = ? AND semester = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $sy_id, $semester);
    $stmt->execute();
    $existing_semester = $stmt->get_result();
    $semester_exists = $existing_semester->num_rows > 0;
    
    // If semester doesn't exist, check if we can add it (max 2-3 semesters per SY)
    if (!$semester_exists) {
        // Check how many distinct semesters the current SY already has
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT semester) as semester_count
            FROM active_school_year_semester
            WHERE sy_id = ?
        ");
        $stmt->bind_param("i", $sy_id);
        $stmt->execute();
        $semester_count_result = $stmt->get_result();
        $semester_count_data = $semester_count_result->fetch_assoc();
        $semester_count = intval($semester_count_data['semester_count']);
        
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
    }
    
    // Deactivate all previous active records (only one active at a time)
    $stmt = $conn->prepare("UPDATE active_school_year_semester SET is_active = 0");
    $stmt->execute();
    
    // If semester exists, reactivate it; otherwise insert new
    if ($semester_exists) {
        $existing_row = $existing_semester->fetch_assoc();
        $stmt = $conn->prepare("
            UPDATE active_school_year_semester 
            SET is_active = 1, created_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $existing_row['id']);
        $stmt->execute();
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO active_school_year_semester (sy_id, semester, is_active, created_at)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->bind_param("is", $sy_id, $semester);
        $stmt->execute();
    }
    
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

