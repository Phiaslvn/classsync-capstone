<?php
/**
 * Bulk Update Subject Curriculum API
 * 
 * IMPORTANT: Curriculum is the source of truth for subjects.
 * Year levels do NOT own copies of subjects - they only reference a curriculum.
 * 
 * This function updates the curriculum mapping for selected year level(s).
 * Subjects keep their original curr_id - we only update which curriculum each year level uses.
 * 
 * Flow:
 * 1. User selects year level(s) and new curriculum
 * 2. Update program_year_level_curriculum mapping table (does NOT change subject curr_id)
 * 3. When displaying subjects, filter by the curriculum that the year level uses
 * 4. Subjects shown = exactly what's in the new curriculum (no copying, no merging)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/program_year_level_curriculum.php';

header('Content-Type: application/json');

function json_response($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

if (!hasPermission('manage_subjects')) {
    json_response(false, 'Unauthorized to update subjects.', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}

// Get form data
$year_levels = $_POST['year_levels'] ?? [];
$program_id = (int)($_POST['program_id'] ?? 0);
$curr_id = (int)($_POST['curr_id'] ?? 0);

// Validate inputs
if (empty($year_levels) || !is_array($year_levels)) {
    json_response(false, 'Please select at least one year level.', null, 400);
}

if ($program_id <= 0) {
    json_response(false, 'Please select a valid program.', null, 400);
}

if ($curr_id <= 0) {
    json_response(false, 'Please select a valid curriculum.', null, 400);
}

// Get user's department ID
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)($userInfo['dept_id'] ?? 0) : 0;
$isAdminSupport = function_exists('isAdminSupport') ? isAdminSupport() : false;
if (!$isAdminSupport && $userDeptId <= 0) {
    json_response(false, 'Unable to determine your department.', null, 400);
}

try {
    $programDeptStmt = $conn->prepare("SELECT dept_id FROM program WHERE program_id = ? LIMIT 1");
    $programDeptStmt->bind_param("i", $program_id);
    $programDeptStmt->execute();
    $programRow = $programDeptStmt->get_result()->fetch_assoc();
    $programDeptStmt->close();
    if (!$programRow) {
        json_response(false, 'Selected program does not exist.', null, 400);
    }
    $programDeptId = (int)$programRow['dept_id'];
    if (!$isAdminSupport && $programDeptId !== $userDeptId) {
        json_response(false, 'Selected program does not belong to your department.', null, 403);
    }
} catch (Exception $e) {
    error_log("Verify Program Error: " . $e->getMessage());
    json_response(false, 'Error verifying program.', null, 500);
}

// Verify curriculum belongs to user's department
try {
    $verify_curr = $conn->prepare("SELECT curr_id, dept_id, program_id FROM curriculum WHERE curr_id = ? LIMIT 1");
    $verify_curr->bind_param("i", $curr_id);
    $verify_curr->execute();
    $curriculumRow = $verify_curr->get_result()->fetch_assoc();
    $verify_curr->close();
    
    if (!$curriculumRow) {
        json_response(false, 'Selected curriculum does not exist.', null, 400);
    }
    if (!$isAdminSupport && (int)$curriculumRow['dept_id'] !== $userDeptId) {
        json_response(false, 'Selected curriculum does not belong to your department.', null, 403);
    }
    if ((int)$curriculumRow['dept_id'] !== $programDeptId) {
        json_response(false, 'Program and curriculum must belong to the same department.', null, 400);
    }
    if (!empty($curriculumRow['program_id']) && (int)$curriculumRow['program_id'] !== $program_id) {
        json_response(false, 'Selected curriculum is not linked to the selected program.', null, 400);
    }
} catch (Exception $e) {
    error_log("Verify Curriculum Error: " . $e->getMessage());
    json_response(false, 'Error verifying curriculum.', null, 500);
}

// Process year levels
$year_levels_to_update = [];
if (in_array('all', $year_levels)) {
    // If "all" is selected, update all year levels (1-5)
    $year_levels_to_update = [1, 2, 3, 4, 5];
} else {
    // Convert string values to integers and filter valid year levels
    foreach ($year_levels as $level) {
        $level_int = (int)$level;
        if ($level_int >= 1 && $level_int <= 5) {
            $year_levels_to_update[] = $level_int;
        }
    }
}

if (empty($year_levels_to_update)) {
    json_response(false, 'Please select at least one valid year level.', null, 400);
}

try {
    // Get curriculum name for the response message
    $curr_name_query = "SELECT curr_name FROM curriculum WHERE curr_id = ?";
    $curr_name_stmt = $conn->prepare($curr_name_query);
    $curr_name_stmt->bind_param("i", $curr_id);
    $curr_name_stmt->execute();
    $curr_name_result = $curr_name_stmt->get_result();
    $curr_name_row = $curr_name_result->fetch_assoc();
    $curr_name = $curr_name_row ? $curr_name_row['curr_name'] : 'Selected Curriculum';
    $curr_name_stmt->close();
    
    // Get old curriculum mappings (before update) for logging — same resolution as runtime (active SY scoped)
    $old_curricula = [];
    $old_curr_names = [];
    foreach ($year_levels_to_update as $yl) {
        $old_curr_id = pylcurriculum_get_curr_id($conn, $program_id, (int)$yl);
        if ($old_curr_id !== null && $old_curr_id !== $curr_id) {
            $old_name_stmt = $conn->prepare("SELECT curr_name FROM curriculum WHERE curr_id = ?");
            $old_name_stmt->bind_param("i", $old_curr_id);
            $old_name_stmt->execute();
            $old_name_result = $old_name_stmt->get_result();
            $old_name_row = $old_name_result->fetch_assoc();
            $old_curr_name = $old_name_row ? $old_name_row['curr_name'] : "Curriculum ID {$old_curr_id}";
            $old_name_stmt->close();
            $old_curricula[$yl] = $old_curr_id;
            $old_curr_names[$yl] = $old_curr_name;
        }
    }

    // Update the mapping table — scoped to active school year when sy_id column exists
    $affected_rows = 0;
    $year_level_counts = [];
    foreach ($year_levels_to_update as $year_level) {
        if (pylcurriculum_upsert_mapping($conn, $program_id, (int)$year_level, $curr_id)) {
            $affected_rows++;
            $count_query = "SELECT COUNT(*) as count 
                           FROM subject 
                           WHERE program_id = ? 
                           AND curr_id = ? 
                           AND subj_lvl = ?";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param("iii", $program_id, $curr_id, $year_level);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            $year_level_counts[$year_level] = (int)$count_row['count'];
            $count_stmt->close();
        }
    }
    
    $year_level_names = [
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        5 => '5th Year'
    ];
    
    $details = [];
    $oldCurriculaInfo = [];
    
    foreach ($year_levels_to_update as $level) {
        $count = $year_level_counts[$level] ?? 0;
        $details[] = $year_level_names[$level] . ': ' . $count . ' subject(s) from ' . $curr_name;
        
        if (isset($old_curricula[$level]) && $old_curricula[$level] != $curr_id) {
            $oldCurriculaInfo[] = $year_level_names[$level] . " changed from '{$old_curr_names[$level]}' to '{$curr_name}'";
        }
    }
    
    $oldCurriculaText = !empty($oldCurriculaInfo) ? " (" . implode(", ", $oldCurriculaInfo) . ")" : "";
    
    // Log the action
    $acc_id = $_SESSION['acc_id'] ?? 0;
    $action = "Updated curriculum mapping for year level(s) " . implode(', ', $year_levels_to_update) . 
              " in program $program_id to use curriculum '$curr_name'";
    $log_details = json_encode([
        'program_id' => $program_id,
        'year_levels' => $year_levels_to_update,
        'curriculum_id' => $curr_id,
        'curriculum_name' => $curr_name,
        'old_mappings' => $old_curricula
    ]);
    
    $log_stmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("iss", $acc_id, $action, $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    json_response(
        true, 
        "Successfully updated curriculum mapping for selected year level(s) in this program. " .
        "They now use '{$curr_name}'. Subjects shown will be exactly what's defined in this curriculum (subject curr_id unchanged).{$oldCurriculaText}",
        [
            'affected_rows' => $affected_rows,
            'year_levels' => $year_levels_to_update,
            'curriculum_name' => $curr_name,
            'curriculum_id' => $curr_id,
            'program_id' => $program_id,
            'old_curricula' => $oldCurriculaInfo,
            'details' => $details,
            'message' => "Year level(s) in this program now use '{$curr_name}'. Subjects shown = exactly what's in this curriculum."
        ],
        200
    );
} catch (Exception $e) {
    error_log("Bulk Update Subject Curriculum Error: " . $e->getMessage());
    json_response(false, 'A database error occurred while updating subjects: ' . $e->getMessage(), null, 500);
}
?>

