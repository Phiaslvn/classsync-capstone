<?php
/**
 * Update Curriculum API
 * Handles updating an existing curriculum with validation and logging.
 */

// Forcefully suppress any stray error output
error_reporting(0);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// --- Debugging Setup ---
$debug_info = [];
ob_start(); // Start output buffering

// Custom error handler to capture notices and warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$debug_info) {
    $debug_info[] = "Error: [$errno] $errstr in $errfile on line $errline";
    return true; // Prevent default PHP error handler
}, E_ALL);

header('Content-Type: application/json');

function json_response($success, $message, $statusCode = 200, $data = []) {
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response['data'] = $data;
    }

    // Add debug info to the response
    global $debug_info;
    $buffered_output = ob_get_clean(); // Get any stray output
    if (!empty($debug_info) || !empty($buffered_output)) {
        $response['debug']['captured_errors'] = $debug_info;
        $response['debug']['buffered_output'] = $buffered_output;
    }
    echo json_encode($response);
    exit();
}

// Check if user has permission to manage curriculum
if (!hasPermission('manage_curriculum')) {
    json_response(false, 'Unauthorized to update curriculum.', 403);
}

// Validate input
$curr_id = (int)($_POST['curr_id'] ?? 0);
$curr_name = trim($_POST['curr_name'] ?? '');
$curr_code = trim($_POST['curr_code'] ?? '');
$curr_type = trim($_POST['curr_type'] ?? ''); // Optional now
$curr_version = trim($_POST['curr_version'] ?? '');
$curr_yr = trim($_POST['curr_yr'] ?? '');
$curr_effective_start_year = !empty($_POST['curr_effective_start_year']) ? (int)$_POST['curr_effective_start_year'] : null;
$curr_effective_end_year = !empty($_POST['curr_effective_end_year']) ? (int)$_POST['curr_effective_end_year'] : null;
$curr_status = trim($_POST['curr_status'] ?? 'active'); // Default to active if not provided
$curr_lvl = !empty($_POST['curr_lvl']) ? intval($_POST['curr_lvl']) : null;
$curr_desc = trim($_POST['curr_desc'] ?? '');
$curr_objective = trim($_POST['curr_objective'] ?? '');
$total_units = isset($_POST['curr_total_units']) && $_POST['curr_total_units'] !== '' ? (int)$_POST['curr_total_units'] : null;
$dept_id = (int)($_POST['dept_id'] ?? 0);
$program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

// Only require name and department (type is optional, status defaults to active)
if (empty($curr_id) || empty($curr_name) || $dept_id <= 0) {
    json_response(false, 'Curriculum name and department are required.', 400);
}

// Validate effective years if both are provided
if ($curr_effective_start_year !== null && $curr_effective_end_year !== null && $curr_effective_end_year < $curr_effective_start_year) {
    json_response(false, 'Effective End Year must be greater than or equal to Effective Start Year', 400);
}

try {
    // Prepare values for optional fields
    $curr_code_val = !empty($curr_code) ? $curr_code : null;
    $curr_version_val = !empty($curr_version) ? $curr_version : null;
    $curr_yr_val = !empty($curr_yr) ? $curr_yr : null;
    $curr_desc_val = !empty($curr_desc) ? $curr_desc : null;
    $curr_objective_val = !empty($curr_objective) ? $curr_objective : null;
    
    // Check if program_id column exists in curriculum table
    $checkColumnQuery = "SHOW COLUMNS FROM curriculum LIKE 'program_id'";
    $checkResult = $conn->query($checkColumnQuery);
    $hasProgramIdColumn = $checkResult && $checkResult->num_rows > 0;
    
    if ($hasProgramIdColumn) {
        $stmt = $conn->prepare(
            "UPDATE curriculum 
             SET curr_name = ?, curr_code = ?, curr_type = ?, curr_version = ?, curr_desc = ?, curr_objective = ?, curr_lvl = ?, curr_yr = ?, curr_effective_start_year = ?, curr_effective_end_year = ?, curr_status = ?, curr_total_units = ?, dept_id = ?, program_id = ?
             WHERE curr_id = ?"
        );

        if (!$stmt) {
            throw new Exception("SQL Prepare failed: " . $conn->error);
        }

        // Types: 8 strings (name, code, type, version, desc, objective, lvl, yr), 2 ints (start, end), 1 string (status), 4 ints (total_units, dept_id, program_id, curr_id)
        $stmt->bind_param(
            "ssssssssiisiiii",
            $curr_name,
            $curr_code_val,
            $curr_type,
            $curr_version_val,
            $curr_desc_val,
            $curr_objective_val,
            $curr_lvl,
            $curr_yr_val,
            $curr_effective_start_year,
            $curr_effective_end_year,
            $curr_status,
            $total_units,
            $dept_id,
            $program_id,
            $curr_id
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE curriculum 
             SET curr_name = ?, curr_code = ?, curr_type = ?, curr_version = ?, curr_desc = ?, curr_objective = ?, curr_lvl = ?, curr_yr = ?, curr_effective_start_year = ?, curr_effective_end_year = ?, curr_status = ?, curr_total_units = ?, dept_id = ?
             WHERE curr_id = ?"
        );

        if (!$stmt) {
            throw new Exception("SQL Prepare failed: " . $conn->error);
        }

        // Types: 8 strings (name, code, type, version, desc, objective, lvl, yr), 2 ints (start, end), 1 string (status), 3 ints (total_units, dept_id, curr_id)
        $stmt->bind_param(
            "ssssssssiisiii",
            $curr_name,
            $curr_code_val,
            $curr_type,
            $curr_version_val,
            $curr_desc_val,
            $curr_objective_val,
            $curr_lvl,
            $curr_yr_val,
            $curr_effective_start_year,
            $curr_effective_end_year,
            $curr_status,
            $total_units,
            $dept_id,
            $curr_id
        );
    }

    if ($stmt->execute()) {
        json_response(true, 'Curriculum updated successfully.', 200);
    } else {
        // Add statement error to debug info before throwing exception
        global $debug_info;
        $debug_info[] = "SQL Execute Error: " . $stmt->error;
        throw new Exception("SQL Execute failed.");
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Update Curriculum Error: " . $e->getMessage());
    json_response(false, 'A database error occurred while updating the curriculum.', 500);
}
?>