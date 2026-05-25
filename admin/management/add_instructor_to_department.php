<?php
/**
 * Add Existing Instructor to Department
 * Adds an existing instructor account to the current admin's department
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/instructor_department_appointments.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['acc_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check permissions - only Admin (Department Head), Admin Support, and Moderator
$stmt = $conn->prepare("
    SELECT r.id as role_id, r.role_name
    FROM account a 
    JOIN user_roles ur ON a.acc_id = ur.acc_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE a.acc_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['acc_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array($user['role_id'], [1, 2, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get instructor ID and admin's department
    $inst_id = !empty($_POST['inst_id']) ? (int)$_POST['inst_id'] : 0;
    
    if ($inst_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid instructor ID']);
        exit();
    }
    
    // Get admin's department
    $userInfo = getUserInfo();
    $adminDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    
    if ($adminDeptId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unable to determine your department']);
        exit();
    }
    
    // Get instructor's account ID
    $instStmt = $conn->prepare("
        SELECT i.inst_id, i.inst_user, a.acc_id, a.fname, a.lname
        FROM instructor i
        INNER JOIN account a ON a.acc_user = i.inst_user
        WHERE i.inst_id = ?
        LIMIT 1
    ");
    $instStmt->bind_param("i", $inst_id);
    $instStmt->execute();
    $instResult = $instStmt->get_result();
    
    if ($instResult->num_rows === 0) {
        $instStmt->close();
        echo json_encode(['success' => false, 'message' => 'Instructor not found']);
        exit();
    }
    
    $instructor = $instResult->fetch_assoc();
    $acc_id = (int)$instructor['acc_id'];
    $instStmt->close();
    
    // Verify account still exists and is active (safety check)
    $accCheckStmt = $conn->prepare("SELECT acc_id, acc_status FROM account WHERE acc_id = ? LIMIT 1");
    $accCheckStmt->bind_param("i", $acc_id);
    $accCheckStmt->execute();
    $accCheckResult = $accCheckStmt->get_result();
    
    if ($accCheckResult->num_rows === 0) {
        $accCheckStmt->close();
        echo json_encode(['success' => false, 'message' => 'Account not found. The account may have been deleted.']);
        exit();
    }
    
    $accData = $accCheckResult->fetch_assoc();
    if ($accData['acc_status'] !== 'Active') {
        $accCheckStmt->close();
        echo json_encode(['success' => false, 'message' => 'Cannot add inactive account to department. Please activate the account first.']);
        exit();
    }
    $accCheckStmt->close();
    
    // Check if account_departments table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
    $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
    
    if (!$hasAccountDepartmentsTable) {
        echo json_encode(['success' => false, 'message' => 'Multi-department feature is not available. account_departments table not found.']);
        exit();
    }
    
    // Check if account already has this department
    // Also verify the account still exists (in case of orphaned account_departments entries)
    $checkStmt = $conn->prepare("
        SELECT ad.id 
        FROM account_departments ad
        INNER JOIN account a ON ad.acc_id = a.acc_id
        WHERE ad.acc_id = ? AND ad.dept_id = ? AND a.acc_status = 'Active'
    ");
    $checkStmt->bind_param("ii", $acc_id, $adminDeptId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        echo json_encode([
            'success' => false, 
            'message' => 'This instructor is already in your department.'
        ]);
        exit();
    }
    $checkStmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Add department association
        $insertStmt = $conn->prepare("INSERT INTO account_departments (acc_id, dept_id) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $acc_id, $adminDeptId);
        $insertStmt->execute();
        $insertStmt->close();
        
        // Update instructor's dept_id if it's NULL (for backward compatibility)
        // But don't overwrite if it already has a value
        $updateInstStmt = $conn->prepare("UPDATE instructor SET dept_id = ? WHERE inst_id = ? AND (dept_id IS NULL OR dept_id = 0)");
        $updateInstStmt->bind_param("ii", $adminDeptId, $inst_id);
        $updateInstStmt->execute();
        $updateInstStmt->close();

        if (ida_appointments_table_exists($conn)) {
            $apStmt = $conn->prepare(
                'SELECT inst_status, instruction_hours FROM instructor WHERE inst_id = ? LIMIT 1'
            );
            $apStmt->bind_param('i', $inst_id);
            $apStmt->execute();
            $apRow = $apStmt->get_result()->fetch_assoc();
            $apStmt->close();
            if ($apRow) {
                ida_upsert_appointment(
                    $conn,
                    $inst_id,
                    $adminDeptId,
                    $apRow['inst_status'],
                    (int) $apRow['instruction_hours']
                );
            }
        }
        
        // Log the action
        $instructorName = trim($instructor['fname'] . ' ' . $instructor['lname']);
        $deptStmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
        $deptStmt->bind_param("i", $adminDeptId);
        $deptStmt->execute();
        $deptResult = $deptStmt->get_result();
        $deptName = 'Unknown Department';
        if ($deptRow = $deptResult->fetch_assoc()) {
            $deptName = $deptRow['dept_name'];
        }
        $deptStmt->close();
        
        $actionDetails = json_encode([
            'instructor_id' => $inst_id,
            'instructor_name' => $instructorName,
            'account_id' => $acc_id,
            'department_id' => $adminDeptId,
            'department_name' => $deptName
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $logAction = "Added existing instructor to department: {$instructorName} to {$deptName}";
        $logStmt->bind_param("iss", $_SESSION['acc_id'], $logAction, $actionDetails);
        $logStmt->execute();
        $logStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Instructor '{$instructorName}' has been successfully added to your department.",
            'instructor_name' => $instructorName,
            'department_name' => $deptName
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
