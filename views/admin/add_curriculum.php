<?php
/**
 * Add New Curriculum
 * Handles curriculum creation with proper validation and transaction control.
 */

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

/**
 * Sends a JSON response and exits the script.
 * @param bool $success Whether the operation was successful.
 * @param string $message The response message.
 * @param int $statusCode The HTTP status code.
 * @param array $data Additional data to include in the response.
 */
function json_response($success, $message, $statusCode = 200, $data = []) {
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit();
}

// Check if user has permission to manage courses
if (!hasPermission('manage_curriculum')) {
    json_response(false, 'Unauthorized - You do not have permission to manage courses.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

// --- Input Sanitization and Retrieval ---
$curr_name = trim($_POST['curr_name'] ?? '');
$curr_code = trim($_POST['curr_code'] ?? '');
$curr_lvl = (int)($_POST['curr_lvl'] ?? 0);
$curr_yr = trim($_POST['curr_yr'] ?? '');
$dept_id = (int)($_POST['dept_id'] ?? 0);
$curr_desc = trim($_POST['curr_desc'] ?? '');
$curr_objective = trim($_POST['curr_objective'] ?? '');
$total_units = !empty($_POST['curr_total_units']) ? (int)$_POST['curr_total_units'] : null;
$curr_status = trim($_POST['curr_status'] ?? '');

// --- Validation ---
$errors = [];
if (empty($curr_name)) $errors[] = 'Curriculum name is required.';
if (empty($curr_code)) $errors[] = 'Curriculum code is required.';
if ($curr_lvl <= 0) $errors[] = 'Level is required.';
if (empty($curr_yr)) $errors[] = 'Academic year is required.';
if ($dept_id <= 0) $errors[] = 'Department is required.';
if (empty($curr_status)) $errors[] = 'Status is required.';

if ($curr_lvl < 1 || $curr_lvl > 5) {
    $errors[] = 'Level must be between 1 and 5.';
}

if (!in_array($curr_status, ['active', 'pending', 'inactive'])) {
    $errors[] = 'Invalid status provided.';
}

if (!empty($errors)) {
    json_response(false, implode(' ', $errors));
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if curriculum code already exists (should be unique)
    $stmt = $conn->prepare("SELECT curr_id FROM curriculum WHERE curr_code = ? AND dept_id = ?");
    $stmt->bind_param("si", $curr_code, $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        json_response(false, 'A curriculum with this code already exists in this department.');
    }
    $stmt->close();

    // Verify department exists
    $stmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        json_response(false, 'Invalid department selected.');
    }
    $dept_name = $result->fetch_assoc()['dept_name'];
    $stmt->close();

    // Insert new curriculum
    $stmt = $conn->prepare("
        INSERT INTO curriculum (curr_code, curr_name, curr_desc, curr_objective, curr_lvl, curr_yr, dept_id, curr_total_units, curr_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssisiss", $curr_code, $curr_name, $curr_desc, $curr_objective, $curr_lvl, $curr_yr, $dept_id, $total_units, $curr_status);
    
    if ($stmt->execute()) {
        $curr_id = $conn->insert_id;
        
        // Log the action
        $action = "Added new curriculum: {$curr_code} - {$curr_name}";
        $details = json_encode([
            'curriculum_id' => $curr_id,
            'curriculum_code' => $curr_code,
            'curriculum_name' => $curr_name,
            'curriculum_level' => $curr_lvl,
            'curriculum_year' => $curr_yr,
            'department_id' => $dept_id,
            'total_units' => $total_units,
            'status' => $curr_status,
            'added_by' => $_SESSION['acc_id']
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $logStmt->bind_param("iss", $_SESSION['acc_id'], $action, $details);
        $logStmt->execute();
        $logStmt->close();

        // If all queries were successful, commit the transaction
        $conn->commit();
        
        json_response(true, 'Curriculum added successfully!', 200, ['curriculum_id' => $curr_id]);
    } else {
        // If execute fails, throw an exception to be caught
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    // An error occurred, rollback the transaction
    $conn->rollback();

    error_log("Add Curriculum Error: " . $e->getMessage());
    json_response(false, 'A database error occurred. Please try again.', 500);
}
?>
