<?php
/**
 * Set Current School Year and Semester in schoolyear table
 * Handles setting the active school year and semester with auto-progression logic
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

$syYear = isset($_POST['sy_year']) ? trim($_POST['sy_year']) : '';
$semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
$user_id = isset($_SESSION['acc_id']) ? intval($_SESSION['acc_id']) : null;

if (empty($syYear) || empty($semester)) {
    $response['message'] = 'School Year and Semester are required.';
    echo json_encode($response);
    exit;
}

// Validate semester
$validSemesters = ['1st Semester', '2nd Semester', 'Mid-Year'];
if (!in_array($semester, $validSemesters)) {
    $response['message'] = 'Invalid semester. Must be 1st Semester, 2nd Semester, or Mid-Year.';
    echo json_encode($response);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Create the school year name with semester
    $syName = $syYear . ' - ' . $semester;
    
    // Check if school year + semester combination already exists (by sy_name to include semester)
    // This ensures each semester for the same school year gets its own record
    $stmt = $conn->prepare("
        SELECT sy_id FROM schoolyear
        WHERE sy_name = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $syName);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    $sy_id = null;
    if ($existing) {
        // School year + semester combination already exists, reuse it
        $sy_id = $existing['sy_id'];
    } else {
        // Create new school year + semester record (each semester gets its own record)
        $stmt = $conn->prepare("
            INSERT INTO schoolyear (sy_year, curr_def, sy_name)
            VALUES (?, 1, ?)
        ");
        $stmt->bind_param("ss", $syYear, $syName);
        $stmt->execute();
        $sy_id = $conn->insert_id;
    }
    
    // Store semester in settings table (consolidated from system_settings)
    // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
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
    
    // Store active school year ID in settings (consolidated from system_settings)
    $sy_id_str = (string)$sy_id;
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
        VALUES ('active_school_year_id', ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $stmt->bind_param("si", $sy_id_str, $user_id);
    $stmt->execute();
    
    // Also update active_school_year_semester table for consistency
    // Convert semester format: "1st Semester" -> "1st", "2nd Semester" -> "2nd", "Mid-Year" -> "Summer"
    $semesterShort = $semester;
    if ($semester === '1st Semester') {
        $semesterShort = '1st';
    } elseif ($semester === '2nd Semester') {
        $semesterShort = '2nd';
    } elseif ($semester === 'Mid-Year') {
        $semesterShort = 'Summer';
    }
    
    // Deactivate all previous active records
    $stmt = $conn->prepare("UPDATE active_school_year_semester SET is_active = 0");
    $stmt->execute();
    
    // Check if this combination already exists
    $stmt = $conn->prepare("
        SELECT id FROM active_school_year_semester
        WHERE sy_id = ? AND semester = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $sy_id, $semesterShort);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Reactivate existing record
        $stmt = $conn->prepare("
            UPDATE active_school_year_semester 
            SET is_active = 1, created_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $existing['id']);
        $stmt->execute();
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO active_school_year_semester (sy_id, semester, is_active, created_at)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->bind_param("is", $sy_id, $semesterShort);
        $stmt->execute();
    }
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'School Year and Semester set successfully.';
    $response['data'] = [
        'sy_year' => $syYear,
        'semester' => $semester,
        'formatted' => $syYear . ' | ' . $semester
    ];
    
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Database error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

