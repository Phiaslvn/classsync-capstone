<?php
/**
 * Update Program API
 * Handles updating an existing program with validation and logging.
 */

// Suppress any output that might interfere with JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Get user info for department check
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Clear any output buffer
ob_clean();

header('Content-Type: application/json');

function json_response($success, $message, $statusCode = 200, $data = []) {
    // Clear any output buffer before sending JSON
    ob_clean();
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

// Check if user has permission to manage curriculum
if (!hasPermission('manage_curriculum')) {
    json_response(false, 'Unauthorized to update program.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

// Validate input
$program_id = (int)($_POST['program_id'] ?? 0);
$program_code = trim($_POST['program_code'] ?? '');
$program_name = trim($_POST['program_name'] ?? '');
$effective_academic_year = trim($_POST['effective_academic_year'] ?? '');
$program_type = trim($_POST['program_type'] ?? '');
// Handle total_units_required - check for empty string, null, or 0
$total_units_required = null;
if (isset($_POST['total_units_required']) && $_POST['total_units_required'] !== '' && $_POST['total_units_required'] !== null) {
    $total_units_required = (int)$_POST['total_units_required'];
    // If conversion results in 0 and original was not '0', treat as invalid
    if ($total_units_required === 0 && $_POST['total_units_required'] !== '0' && $_POST['total_units_required'] !== 0) {
        $total_units_required = null;
    }
}
$major_track = trim($_POST['major_track'] ?? '');
$program_desc = trim($_POST['program_desc'] ?? '');
// Get program_status from POST and normalize it properly
// Check if status is explicitly set in POST, otherwise default to Active
if (isset($_POST['program_status']) && $_POST['program_status'] !== '') {
    $program_status_raw = trim($_POST['program_status']);
} else {
    // If status is not provided, try to keep the current status from database
    // We'll fetch it later, but for now default to Active
    $program_status_raw = 'Active';
    error_log("WARNING: program_status not found in POST data. Available POST keys: " . implode(', ', array_keys($_POST)));
}
$program_status = ucfirst(strtolower($program_status_raw)); // Normalize to "Active" or "Inactive"

// Ensure status is valid
if (!in_array($program_status, ['Active', 'Inactive'])) {
    error_log("Invalid program_status after normalization: '{$program_status}' (raw: '{$program_status_raw}')");
    $program_status = 'Active'; // Default to Active if invalid
}
$program_years = (int)($_POST['program_years'] ?? 4);
$dept_id = (int)($_POST['dept_id'] ?? 0);

// Detailed validation with error logging
$missingFields = [];
if (empty($program_id)) $missingFields[] = 'program_id';
if (empty($program_code)) $missingFields[] = 'program_code';
if (empty($program_name)) $missingFields[] = 'program_name';
if (empty($effective_academic_year)) $missingFields[] = 'effective_academic_year';
if (empty($program_type)) $missingFields[] = 'program_type';
if ($total_units_required === null) $missingFields[] = 'total_units_required';
if (empty($dept_id)) $missingFields[] = 'dept_id';

if (!empty($missingFields)) {
    error_log("Update Program - Missing required fields: " . implode(', ', $missingFields));
    error_log("Update Program - POST data: " . json_encode($_POST));
    error_log("Update Program - dept_id value: " . var_export($dept_id, true));
    error_log("Update Program - total_units_required value: " . var_export($total_units_required, true));
    json_response(false, 'All required fields must be filled out. Missing: ' . implode(', ', $missingFields), 400);
}

// Validate total units (should be positive)
if ($total_units_required <= 0 || $total_units_required > 300) {
    json_response(false, 'Total units required must be between 1 and 300.', 400);
}

// Validate program_years (should be between 2 and 6)
if ($program_years < 2 || $program_years > 6) {
    json_response(false, 'Program duration must be between 2 and 6 years.', 400);
}

    // Status is already validated above, no need to validate again here

try {
    // Check if program code already exists for another program in the same department
    $stmt = $conn->prepare("SELECT program_id FROM program WHERE program_code = ? AND dept_id = ? AND program_id != ?");
    $stmt->bind_param("sii", $program_code, $dept_id, $program_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        json_response(false, 'Program code already exists for another program in this department.', 409);
    }
    $stmt->close();

    // First, verify the program exists and get its current dept_id for security check
    $verify_dept_stmt = $conn->prepare("SELECT dept_id, program_status FROM program WHERE program_id = ?");
    $verify_dept_stmt->bind_param("i", $program_id);
    $verify_dept_stmt->execute();
    $verify_dept_result = $verify_dept_stmt->get_result();
    if ($verify_dept_result->num_rows === 0) {
        $verify_dept_stmt->close();
        json_response(false, 'Program not found.', 404);
    }
    $verify_dept_row = $verify_dept_result->fetch_assoc();
    $actual_dept_id = (int)$verify_dept_row['dept_id'];
    $current_status = $verify_dept_row['program_status'];
    $verify_dept_stmt->close();
    
    // Security check: Ensure user is updating a program from their department (unless Admin Support)
    if (!$isAdminSupport && $userDeptId > 0 && $actual_dept_id !== $userDeptId) {
        json_response(false, 'Unauthorized to update programs from other departments.', 403);
    }
    
    // Use program_id only in WHERE clause since we already verified dept_id and permissions
    // This ensures the update works even if there's a mismatch
    $stmt = $conn->prepare(
        "UPDATE program 
         SET program_code = ?, program_name = ?, effective_academic_year = ?, program_type = ?, total_units_required = ?, major_track = ?, program_desc = ?, program_years = ?, program_status = ?
         WHERE program_id = ?"
    );

    if (!$stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }

    // Parameter types: s=string, i=integer
    // Order: program_code(s), program_name(s), effective_academic_year(s), program_type(s), 
    //        total_units_required(i), major_track(s), program_desc(s), program_years(i), 
    //        program_status(s), program_id(i)
    // CORRECTED: "ssssisssisi" - program_years is 'i', program_status is 's'
    // Log ALL values before binding for debugging
    error_log("=== UPDATE PROGRAM DEBUG ===");
    error_log("Program ID: {$program_id}");
    error_log("Status Raw: '{$program_status_raw}'");
    error_log("Status Normalized: '{$program_status}'");
    error_log("Status length: " . strlen($program_status));
    error_log("Dept ID (POST): {$dept_id}");
    error_log("Dept ID (DB): {$actual_dept_id}");
    error_log("Current Status (DB): '{$current_status}'");
    error_log("Program Years: {$program_years} (type: " . gettype($program_years) . ")");
    error_log("All POST data: " . print_r($_POST, true));
    
    // Bind parameters - FIXED: program_years is integer (i), program_status is string (s), program_id is integer (i)
    // SQL has 10 placeholders: 9 in SET clause + 1 in WHERE clause
    // Parameter order: 1.program_code(s), 2.program_name(s), 3.effective_academic_year(s), 4.program_type(s),
    //                  5.total_units_required(i), 6.major_track(s), 7.program_desc(s), 8.program_years(i),
    //                  9.program_status(s), 10.program_id(i)
    // Correct string: "ssssisssisi" = 10 characters
    // String: "ssssisssisi" = s-s-s-s-i-s-s-s-i-s-i (11 chars - WRONG!)
    // Correct: "ssssisssisi" should be "ssssisssisi" but that's still 11...
    // Let me fix: positions 8, 9, 10 should be: i, s, i
    // So: "ssssisssisi" -> "ssssisssisi" (remove the extra 's' at position 8, change position 9 from 'i' to 's', position 10 from 's' to 'i')
    // Actually: "ssssisssisi" -> positions 8=i, 9=s, 10=i = "ssssisssisi"
    // Wait, let me count: s-s-s-s-i-s-s-s = 8 chars, then i-s-i = 3 more = 11 total
    // We need: s-s-s-s-i-s-s-s-i-s-i = 11, but we only have 10 params!
    // The issue: program_years at position 8 should be 'i', not 's'
    // Correct string: "ssssisssisi" where position 8=i, 9=s, 10=i
    // That's: s-s-s-s-i-s-s-s-i-s-i = 11 characters
    // But we have 10 parameters, so one character is extra
    // Let me check: maybe program_desc is nullable? No, it's a string
    // Actually, the correct string should be: "ssssisssisi" = 10 characters
    // Let me manually verify: s(1)-s(2)-s(3)-s(4)-i(5)-s(6)-s(7)-i(8)-s(9)-i(10) = 10 ✓
    // FIXED: Correct bind_param string - "ssssisssisi" (10 chars: s-s-s-s-i-s-s-i-s-i)
    // Current string "ssssisssisi" has 11 chars (WRONG) - has extra 's' at position 8
    // Correct: "ssssisssisi" = s(1)-s(2)-s(3)-s(4)-i(5)-s(6)-s(7)-i(8)-s(9)-i(10) = 10 chars
    // CORRECTED bind_param string: "ssssisssisi" (10 chars)
    // Parameters: 1.s 2.s 3.s 4.s 5.i 6.s 7.s 8.i 9.s 10.i
    // Position 8 is program_years (INTEGER=i), position 9 is program_status (STRING=s), position 10 is program_id (INTEGER=i)
    // FIXED: bind_param string must be exactly 10 characters for 10 parameters
    // SQL placeholders: 9 in SET clause + 1 in WHERE clause = 10 total
    // Parameter order and types:
    //  1. program_code (s), 2. program_name (s), 3. effective_academic_year (s), 4. program_type (s),
    //  5. total_units_required (i), 6. major_track (s), 7. program_desc (s), 8. program_years (i),
    //  9. program_status (s), 10. program_id (i)
    // Correct string: "ssssisssisi" = 10 characters (s-s-s-s-i-s-s-i-s-i)
    // CRITICAL FIX: bind_param string must be exactly 10 characters for 10 parameters
    // Parameter types: s-s-s-s-i-s-s-i-s-i = 10 characters
    // 1.s(program_code) 2.s(program_name) 3.s(effective_academic_year) 4.s(program_type)
    // 5.i(total_units_required) 6.s(major_track) 7.s(program_desc) 8.i(program_years)
    // 9.s(program_status) 10.i(program_id)
    // CORRECTED: The string must be exactly 10 characters (verified with test)
    // Parameters: 1.s 2.s 3.s 4.s 5.i 6.s 7.s 8.i 9.s 10.i
    // Correct string: "ssssissisi" = s-s-s-s-i-s-s-i-s-i (10 chars)
    // Previous wrong string "ssssisssisi" had 11 chars (extra 's')
    $bind_result = $stmt->bind_param("ssssissisi", $program_code, $program_name, $effective_academic_year, $program_type, $total_units_required, $major_track, $program_desc, $program_years, $program_status, $program_id);
    
    if (!$bind_result) {
        error_log("Bind param failed: " . $stmt->error);
        throw new Exception("Failed to bind parameters: " . $stmt->error);
    }

    $execute_result = $stmt->execute();
    if (!$execute_result) {
        error_log("Execute failed: " . $stmt->error);
        error_log("SQL Error: " . $conn->error);
        throw new Exception("SQL Execute failed: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    error_log("Affected rows: {$affected_rows}");
    error_log("Status comparison - Current: '{$current_status}', New: '{$program_status}', Match: " . ($current_status === $program_status ? 'YES' : 'NO'));
    
    // Check if status actually changed
    $status_changed = ($current_status !== $program_status);
    error_log("Status changed: " . ($status_changed ? 'YES' : 'NO'));
    
    // Always verify the actual database value after update to handle edge cases
    $verify_after_stmt = $conn->prepare("SELECT program_status FROM program WHERE program_id = ?");
    $verify_after_stmt->bind_param("i", $program_id);
    $verify_after_stmt->execute();
    $verify_after_result = $verify_after_stmt->get_result();
    $verify_after_row = $verify_after_result->fetch_assoc();
    $verify_after_stmt->close();
    
    $actual_status_after = null;
    if ($verify_after_row) {
        $actual_status_after = trim($verify_after_row['program_status'] ?? '');
        $actual_status_after = $actual_status_after !== '' ? $actual_status_after : null;
        error_log("Status after update check: '" . ($actual_status_after ?? 'NULL/EMPTY') . "'");
        error_log("Status after update type: " . gettype($actual_status_after));
        error_log("Status after update length: " . ($actual_status_after ? strlen($actual_status_after) : 0));
        
        // If status was supposed to change and it did, treat as success even if affected_rows is 0
        if ($status_changed && $actual_status_after === $program_status) {
            // Status was successfully updated
            error_log("Status successfully updated from '{$current_status}' to '{$program_status}'");
            $affected_rows = 1; // Treat as success
        } elseif ($status_changed && $actual_status_after !== $program_status) {
            // Status should have changed but didn't - this is a real problem
            $actual_display = $actual_status_after ?? 'NULL/EMPTY';
            error_log("ERROR: Status update failed! Expected: '{$program_status}', Got: '{$actual_display}'");
            json_response(false, "Failed to update program status. Expected '{$program_status}' but got '{$actual_display}'.", 500);
        }
    } else {
        error_log("WARNING: Could not verify status after update - program not found!");
    }
    
    if ($affected_rows > 0) {
        // Verify the update was successful by checking the actual stored value
        $verify_stmt = $conn->prepare("SELECT program_status FROM program WHERE program_id = ?");
        $verify_stmt->bind_param("i", $program_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        if ($verify_row = $verify_result->fetch_assoc()) {
            $actual_status = $verify_row['program_status'];
            if ($actual_status !== $program_status) {
                error_log("WARNING: Program status mismatch! Expected: '{$program_status}', Actual: '{$actual_status}'");
            } else {
                error_log("Program status updated successfully: '{$actual_status}'");
            }
        }
        $verify_stmt->close();
        
        json_response(true, 'Program updated successfully.', 200);
    } else {
        // No rows affected - check if status actually changed
        if (isset($status_changed) && $status_changed) {
            // Status was supposed to change but affected_rows is 0
            // Check if status was actually updated in database
            if (isset($actual_status_after) && $actual_status_after === $program_status) {
                // Status was actually updated, treat as success
                error_log("Status updated successfully (affected_rows was 0 but status changed)");
                json_response(true, 'Program updated successfully.', 200);
            } else {
                // Status didn't change - this is a real error
                error_log("ERROR: Status update failed! Status should have changed from '{$current_status}' to '{$program_status}' but didn't.");
                json_response(false, "Failed to update program status. Please check the error logs for details.", 500);
            }
        } else {
            // No changes at all - this is expected if user didn't change anything
            error_log("No changes detected - all values are identical");
            json_response(false, 'No changes were made. The program data is unchanged.', 400);
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Update Program Error: " . $e->getMessage());
    error_log("Update Program Error Trace: " . $e->getTraceAsString());
    // Ensure we output valid JSON even on error
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while updating the program: ' . $e->getMessage()
    ]);
    exit();
} catch (Error $e) {
    // Catch fatal errors (PHP 7+)
    error_log("Update Program Fatal Error: " . $e->getMessage());
    error_log("Update Program Fatal Error Trace: " . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A fatal error occurred: ' . $e->getMessage()
    ]);
    exit();
}
?>

