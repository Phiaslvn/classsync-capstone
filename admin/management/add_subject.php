<?php
/**
 * Add Subject API
 * Handles creating a new subject record.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

function json_response($success, $message, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if (!hasPermission('manage_subjects')) {
    json_response(false, 'Unauthorized to add subject.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

$curr_id = (int)($_POST['curr_id'] ?? 0);
$dept_id = (int)($_POST['dept_id'] ?? 0);
$program_id = (int)($_POST['program_id'] ?? 0);
$subj_code = trim($_POST['subj_code'] ?? '');
$subj_desc = trim($_POST['subj_desc'] ?? '');
$subj_lec = (int)($_POST['subj_lec'] ?? 0);
$subj_lab = (int)($_POST['subj_lab'] ?? 0);
$subj_unit = (int)($_POST['subj_unit'] ?? 0);
$subj_lvl = (int)($_POST['subj_lvl'] ?? 0);
$subj_term = (int)($_POST['subj_term'] ?? 0);
$subj_category = trim($_POST['subj_category'] ?? '');
$subj_min = (int)($_POST['subj_min'] ?? 75);

if (empty($curr_id) || empty($dept_id) || empty($program_id) || empty($subj_code) || empty($subj_desc) || $subj_lvl <= 0 || $subj_term <= 0 || empty($subj_category)) {
    json_response(false, 'All required fields must be filled out.', 400);
}

try {
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)($userInfo['dept_id'] ?? 0) : 0;
    $isAdminSupport = function_exists('isAdminSupport') ? isAdminSupport() : false;

    if (!$isAdminSupport && ($userDeptId <= 0 || $dept_id !== $userDeptId)) {
        json_response(false, 'Department mismatch for subject creation.', 403);
    }

    $programCheckStmt = $conn->prepare("SELECT dept_id FROM program WHERE program_id = ? LIMIT 1");
    if (!$programCheckStmt) {
        throw new Exception("Program validation prepare failed: " . $conn->error);
    }
    $programCheckStmt->bind_param("i", $program_id);
    $programCheckStmt->execute();
    $programCheck = $programCheckStmt->get_result()->fetch_assoc();
    $programCheckStmt->close();

    if (!$programCheck) {
        json_response(false, 'Selected program was not found.', 400);
    }
    if ((int)$programCheck['dept_id'] !== $dept_id) {
        json_response(false, 'Selected program does not belong to the selected department.', 400);
    }

    $curriculumCheckStmt = $conn->prepare("SELECT dept_id, program_id FROM curriculum WHERE curr_id = ? LIMIT 1");
    if (!$curriculumCheckStmt) {
        throw new Exception("Curriculum validation prepare failed: " . $conn->error);
    }
    $curriculumCheckStmt->bind_param("i", $curr_id);
    $curriculumCheckStmt->execute();
    $curriculumCheck = $curriculumCheckStmt->get_result()->fetch_assoc();
    $curriculumCheckStmt->close();

    if (!$curriculumCheck) {
        json_response(false, 'Selected curriculum was not found.', 400);
    }
    if ((int)$curriculumCheck['dept_id'] !== $dept_id) {
        json_response(false, 'Selected curriculum does not belong to the selected department.', 400);
    }
    if (!empty($curriculumCheck['program_id']) && (int)$curriculumCheck['program_id'] !== $program_id) {
        json_response(false, 'Selected curriculum is not linked to the selected program.', 400);
    }

    // Check for duplicate subject code
    // For GEN. ED. subjects: Allow same code in different programs, but prevent duplicate within same program+curriculum
    // For other subjects: Prevent duplicate within same curriculum (existing behavior)
    if ($subj_category === 'GENED') {
        // GEN. ED. subjects: Check for duplicate within same program + curriculum combination
        $check_query = "SELECT subj_id FROM subject WHERE subj_code = ? AND program_id = ? AND curr_id = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("SQL Prepare failed for duplicate check: " . $conn->error);
        }
        $check_stmt->bind_param("sii", $subj_code, $program_id, $curr_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result && $check_result->num_rows > 0) {
            $check_stmt->close();
            json_response(false, "A subject with the code '{$subj_code}' already exists in this program and curriculum.", 409); // 409 Conflict
        }
        $check_stmt->close();
    } else {
        // Major/Minor subjects: Check for duplicate within the same curriculum (existing behavior)
        $check_query = "SELECT subj_id FROM subject WHERE subj_code = ? AND curr_id = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("SQL Prepare failed for duplicate check: " . $conn->error);
        }
        $check_stmt->bind_param("si", $subj_code, $curr_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result && $check_result->num_rows > 0) {
            $check_stmt->close();
            json_response(false, "A subject with the code '{$subj_code}' already exists in this curriculum.", 409); // 409 Conflict
        }
        $check_stmt->close();
    }

    $stmt = $conn->prepare(
        "INSERT INTO subject (curr_id, dept_id, program_id, subj_code, subj_desc, subj_lec, subj_lab, subj_unit, subj_min, subj_lvl, subj_term, subj_category) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) throw new Exception("SQL Prepare failed: " . $conn->error);

    $stmt->bind_param("iiisssiiiiis", $curr_id, $dept_id, $program_id, $subj_code, $subj_desc, $subj_lec, $subj_lab, $subj_unit, $subj_min, $subj_lvl, $subj_term, $subj_category);

    if ($stmt->execute()) json_response(true, 'Subject added successfully.');
    else throw new Exception("Execute failed: " . $stmt->error);
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Add Subject Error: " . $e->getMessage());
    json_response(false, 'A database error occurred while adding the subject.', 500);
}
?>