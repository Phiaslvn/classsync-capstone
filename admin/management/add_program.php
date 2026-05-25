<?php
/**
 * Add New Program
 * Handles program creation with proper validation
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

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

$program_code = trim($_POST['program_code'] ?? '');
$program_name = trim($_POST['program_name'] ?? '');
$effective_academic_year = trim($_POST['effective_academic_year'] ?? '');
$program_type = trim($_POST['program_type'] ?? '');
$total_units_required = !empty($_POST['total_units_required']) ? (int)$_POST['total_units_required'] : null;
$major_track = trim($_POST['major_track'] ?? '');
$dept_id = (int)($_POST['dept_id'] ?? 0);
$program_desc = trim($_POST['program_desc'] ?? '');
$program_status = trim($_POST['program_status'] ?? 'Active');
$program_years = (int)($_POST['program_years'] ?? 4);

// Validation
if (empty($program_code) || empty($program_name) || empty($effective_academic_year) || empty($program_type) || $total_units_required === null || $dept_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Program code, name, effective academic year, program type, total units required, and department are required']);
    exit();
}

// Validate total units (should be positive)
if ($total_units_required <= 0 || $total_units_required > 300) {
    echo json_encode(['success' => false, 'message' => 'Total units required must be between 1 and 300']);
    exit();
}

// Validate program_years (should be between 2 and 6)
if ($program_years < 2 || $program_years > 6) {
    echo json_encode(['success' => false, 'message' => 'Program duration must be between 2 and 6 years']);
    exit();
}

if (!in_array($program_status, ['Active', 'Inactive'])) {
    $program_status = 'Active';
}

try {
    // Check if program code already exists
    $stmt = $conn->prepare("SELECT program_id FROM program WHERE program_code = ?");
    $stmt->bind_param("s", $program_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Program code already exists']);
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

    // Insert new program
    $stmt = $conn->prepare("
        INSERT INTO program (program_code, program_name, effective_academic_year, program_type, total_units_required, major_track, program_desc, program_years, dept_id, program_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssisssis", $program_code, $program_name, $effective_academic_year, $program_type, $total_units_required, $major_track, $program_desc, $program_years, $dept_id, $program_status);
    
    if ($stmt->execute()) {
        $program_id = $conn->insert_id;
        
        // Log the action
        $action = "Added new program: {$program_code} ({$program_name}) in {$dept_name}";
        $details = json_encode([
            'program_id' => $program_id,
            'program_code' => $program_code,
            'program_name' => $program_name,
            'effective_academic_year' => $effective_academic_year,
            'program_type' => $program_type,
            'total_units_required' => $total_units_required,
            'major_track' => $major_track,
            'program_years' => $program_years,
            'department_id' => $dept_id,
            'department_name' => $dept_name,
            'added_by' => $_SESSION['acc_id']
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $logStmt->bind_param("iss", $_SESSION['acc_id'], $action, $details);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Program added successfully',
            'program_id' => $program_id
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Add Program Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
