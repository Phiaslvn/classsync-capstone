<?php
/**
 * Add New Curriculum
 * Handles curriculum creation with proper validation
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user has permission to manage courses
if (!hasPermission('manage_curriculum')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - You do not have permission to manage courses']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$curr_name = trim($_POST['curr_name'] ?? '');
$curr_code = trim($_POST['curr_code'] ?? '');
$curr_type = trim($_POST['curr_type'] ?? ''); // Optional now
$curr_version = trim($_POST['curr_version'] ?? '');
// Provide default values for required database columns that are no longer in the form
// curr_lvl defaults to "4" (4 years) for typical programs
$curr_lvl = !empty($_POST['curr_lvl']) ? (string)$_POST['curr_lvl'] : '4';
// curr_yr defaults to current academic year if not provided
$currentYear = date('Y');
$defaultAcademicYear = $currentYear . '-' . ($currentYear + 1);
$curr_yr = !empty($_POST['curr_yr']) ? trim($_POST['curr_yr']) : $defaultAcademicYear;
$curr_effective_start_year = !empty($_POST['curr_effective_start_year']) ? (int)$_POST['curr_effective_start_year'] : null;
$curr_effective_end_year = !empty($_POST['curr_effective_end_year']) ? (int)$_POST['curr_effective_end_year'] : null;
$dept_id = (int)($_POST['dept_id'] ?? 0);
$curr_desc = trim($_POST['curr_desc'] ?? '');
$curr_objective = trim($_POST['curr_objective'] ?? '');
$total_units = !empty($_POST['curr_total_units']) ? (int)$_POST['curr_total_units'] : null;
$curr_status = 'active'; // Always set to active, no dropdown needed
$program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

// Validation - only require name and department (type is now optional)
if (empty($curr_name) || $dept_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Curriculum name and department are required']);
    exit();
}

// Validate effective years if both are provided
if ($curr_effective_start_year !== null && $curr_effective_end_year !== null && $curr_effective_end_year < $curr_effective_start_year) {
    echo json_encode(['success' => false, 'message' => 'Effective End Year must be greater than or equal to Effective Start Year']);
    exit();
}

// Validate level if provided
if ($curr_lvl !== null && ($curr_lvl < 1 || $curr_lvl > 5)) {
    echo json_encode(['success' => false, 'message' => 'Level must be between 1 and 5']);
    exit();
}

try {
    // Check if curriculum already exists for this department (by name)
    $stmt = $conn->prepare("SELECT curr_id FROM curriculum WHERE curr_name = ? AND dept_id = ?");
    $stmt->bind_param("si", $curr_name, $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Curriculum with this name already exists for this department']);
        exit();
    }
    $stmt->close();

    // Verify department exists
    $stmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid department']);
        exit();
    }
    $dept_name = $result->fetch_assoc()['dept_name'];
    $stmt->close();

    // Insert new curriculum (with optional fields set to NULL if not provided)
    // Prepare values for optional fields
    $curr_code_val = !empty($curr_code) ? $curr_code : null;
    $curr_version_val = !empty($curr_version) ? $curr_version : null;
    $curr_desc_val = !empty($curr_desc) ? $curr_desc : null;
    $curr_objective_val = !empty($curr_objective) ? $curr_objective : null;
    // curr_lvl and curr_yr already have defaults set above (required by database)
    
    // Check if program_id column exists in curriculum table
    $checkColumnQuery = "SHOW COLUMNS FROM curriculum LIKE 'program_id'";
    $checkResult = $conn->query($checkColumnQuery);
    $hasProgramIdColumn = $checkResult && $checkResult->num_rows > 0;
    
    if ($hasProgramIdColumn) {
        $stmt = $conn->prepare("
            INSERT INTO curriculum (curr_code, curr_name, curr_type, curr_version, curr_desc, curr_objective, curr_lvl, curr_yr, curr_effective_start_year, curr_effective_end_year, dept_id, curr_total_units, curr_status, program_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Types: 8 strings (code, name, type, version, desc, objective, lvl, yr), 4 ints (start, end, dept, total_units), 1 string (status), 1 int (program_id)
        $stmt->bind_param("ssssssssiiiisi", 
            $curr_code_val, 
            $curr_name, 
            $curr_type, 
            $curr_version_val, 
            $curr_desc_val, 
            $curr_objective_val, 
            $curr_lvl, 
            $curr_yr, 
            $curr_effective_start_year, 
            $curr_effective_end_year, 
            $dept_id, 
            $total_units, 
            $curr_status,
            $program_id
        );
    } else {
        $stmt = $conn->prepare("
            INSERT INTO curriculum (curr_code, curr_name, curr_type, curr_version, curr_desc, curr_objective, curr_lvl, curr_yr, curr_effective_start_year, curr_effective_end_year, dept_id, curr_total_units, curr_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Types: 8 strings (code, name, type, version, desc, objective, lvl, yr), 4 ints (start, end, dept, total_units), 1 string (status)
        $stmt->bind_param("ssssssssiiiis", 
            $curr_code_val, 
            $curr_name, 
            $curr_type, 
            $curr_version_val, 
            $curr_desc_val, 
            $curr_objective_val, 
            $curr_lvl, 
            $curr_yr, 
            $curr_effective_start_year, 
            $curr_effective_end_year, 
            $dept_id, 
            $total_units, 
            $curr_status
        );
    }
    
    if ($stmt->execute()) {
        $curr_id = $conn->insert_id;
        
        // Log the action
        $levelText = $curr_lvl ? " (Level {$curr_lvl})" : "";
        $action = "Added new curriculum: {$curr_name}{$levelText} in {$dept_name}";
        $details = json_encode([
            'curriculum_id' => $curr_id,
            'curriculum_code' => $curr_code,
            'curriculum_name' => $curr_name,
            'curriculum_type' => $curr_type,
            'curriculum_version' => $curr_version,
            'curriculum_level' => $curr_lvl,
            'curriculum_year' => $curr_yr,
            'effective_start_year' => $curr_effective_start_year,
            'effective_end_year' => $curr_effective_end_year,
            'department_id' => $dept_id,
            'total_units' => $total_units,
            'status' => $curr_status,
            'added_by' => $_SESSION['acc_id']
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $logStmt->bind_param("iss", $_SESSION['acc_id'], $action, $details);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Curriculum added successfully',
            'curriculum_id' => $curr_id
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Add Curriculum Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
