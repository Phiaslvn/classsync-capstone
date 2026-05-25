<?php
/**
 * Remove Instructor from Department
 * Removes an instructor from the current admin's department (only removes department association, not the account)
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
    // Get account ID (can be passed as acc_id or inst_id)
    $acc_id = !empty($_POST['acc_id']) ? (int)$_POST['acc_id'] : 0;
    $inst_id = !empty($_POST['inst_id']) ? (int)$_POST['inst_id'] : 0;
    
    // If inst_id is provided, get acc_id from instructor
    if ($inst_id > 0 && $acc_id <= 0) {
        $instStmt = $conn->prepare("
            SELECT a.acc_id 
            FROM instructor i
            INNER JOIN account a ON a.acc_user = i.inst_user
            WHERE i.inst_id = ?
            LIMIT 1
        ");
        $instStmt->bind_param("i", $inst_id);
        $instStmt->execute();
        $instResult = $instStmt->get_result();
        if ($instRow = $instResult->fetch_assoc()) {
            $acc_id = (int)$instRow['acc_id'];
        }
        $instStmt->close();
    }
    
    if ($acc_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid account ID or instructor ID']);
        exit();
    }
    
    // Get admin's department
    $userInfo = getUserInfo();
    $adminDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    
    if ($adminDeptId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unable to determine your department']);
        exit();
    }
    
    // Get account info for logging
    $accStmt = $conn->prepare("SELECT acc_id, fname, lname, acc_user FROM account WHERE acc_id = ? LIMIT 1");
    $accStmt->bind_param("i", $acc_id);
    $accStmt->execute();
    $accResult = $accStmt->get_result();
    
    if ($accResult->num_rows === 0) {
        $accStmt->close();
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit();
    }
    
    $account = $accResult->fetch_assoc();
    $accStmt->close();
    
    // Check if account_departments table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
    $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
    
    if (!$hasAccountDepartmentsTable) {
        echo json_encode(['success' => false, 'message' => 'Multi-department feature is not available. account_departments table not found.']);
        exit();
    }
    
    // Check if account has this department
    $checkStmt = $conn->prepare("SELECT id FROM account_departments WHERE acc_id = ? AND dept_id = ?");
    $checkStmt->bind_param("ii", $acc_id, $adminDeptId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        echo json_encode([
            'success' => false, 
            'message' => 'This instructor is not in your department.'
        ]);
        exit();
    }
    $checkStmt->close();
    
    // Check how many departments this account has
    $countStmt = $conn->prepare("SELECT COUNT(*) as dept_count FROM account_departments WHERE acc_id = ?");
    $countStmt->bind_param("i", $acc_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $deptCount = 0;
    if ($countRow = $countResult->fetch_assoc()) {
        $deptCount = (int)$countRow['dept_count'];
    }
    $countStmt->close();
    
    // Prevent removing if this is the only department (safety check)
    // But allow it if admin wants to (they can add them back)
    // Actually, let's allow it - maybe they want to remove and the account will just have account.dept_id
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove department association
        $deleteStmt = $conn->prepare("DELETE FROM account_departments WHERE acc_id = ? AND dept_id = ?");
        $deleteStmt->bind_param("ii", $acc_id, $adminDeptId);
        $deleteStmt->execute();
        $deleteStmt->close();

        if ($inst_id <= 0) {
            $instLookup = $conn->prepare(
                'SELECT inst_id FROM instructor i JOIN account a ON a.acc_user = i.inst_user WHERE a.acc_id = ? LIMIT 1'
            );
            $instLookup->bind_param('i', $acc_id);
            $instLookup->execute();
            $ir = $instLookup->get_result()->fetch_assoc();
            $instLookup->close();
            if ($ir) {
                $inst_id = (int) $ir['inst_id'];
            }
        }
        if ($inst_id > 0 && ida_appointments_table_exists($conn)) {
            ida_delete_appointment($conn, $inst_id, $adminDeptId);
        }
        
        // Log the action
        $instructorName = trim($account['fname'] . ' ' . $account['lname']);
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
            'account_id' => $acc_id,
            'instructor_name' => $instructorName,
            'department_id' => $adminDeptId,
            'department_name' => $deptName,
            'remaining_departments' => $deptCount - 1
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $logAction = "Removed instructor from department: {$instructorName} from {$deptName}";
        $logStmt->bind_param("iss", $_SESSION['acc_id'], $logAction, $actionDetails);
        $logStmt->execute();
        $logStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Instructor '{$instructorName}' has been successfully removed from your department.",
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
