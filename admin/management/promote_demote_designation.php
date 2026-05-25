<?php
session_start();
include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check authentication and authorization
$roleId = (int)($_SESSION['role_id'] ?? 0);
if (!isset($_SESSION['acc_id']) || !in_array($roleId, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$accId = (int)($_POST['acc_id'] ?? 0);
$action = trim($_POST['action'] ?? ''); // 'promote' or 'demote'

if ($accId <= 0 || !in_array($action, ['promote', 'demote'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Designation hierarchy (from highest to lowest)
$designationHierarchy = [
    'Vice President',
    'Campus Director',
    'Dean',
    'Director',
    'Head',
    'Chairperson/Coordinator/As Officer in Faculty Association',
    'None'
];

try {
    // Get current designation
    $stmt = $conn->prepare('SELECT i.designation, i.inst_id, a.acc_user FROM instructor i JOIN account a ON i.inst_user = a.acc_user WHERE a.acc_id = ?');
    $stmt->bind_param('i', $accId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row || !$row['inst_id']) {
        echo json_encode(['success' => false, 'message' => 'Instructor record not found']);
        exit();
    }
    
    $currentDesignation = $row['designation'] ?? 'None';
    $instId = $row['inst_id'];
    $username = $row['acc_user'];
    
    // Find current designation index
    $currentIndex = array_search($currentDesignation, $designationHierarchy);
    
    if ($currentIndex === false) {
        // If designation not in hierarchy, default to lowest
        $currentIndex = count($designationHierarchy) - 1;
    }
    
    // Calculate new designation
    if ($action === 'promote') {
        if ($currentIndex === 0) {
            echo json_encode(['success' => false, 'message' => 'User already has the highest designation']);
            exit();
        }
        $newIndex = $currentIndex - 1;
    } else { // demote
        if ($currentIndex === count($designationHierarchy) - 1) {
            echo json_encode(['success' => false, 'message' => 'User already has the lowest designation']);
            exit();
        }
        $newIndex = $currentIndex + 1;
    }
    
    $newDesignation = $designationHierarchy[$newIndex];
    
    // Update designation
    $updateStmt = $conn->prepare('UPDATE instructor SET designation = ? WHERE inst_id = ?');
    $updateStmt->bind_param('si', $newDesignation, $instId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Log the action
    $actorId = $_SESSION['acc_id'];
    $actionText = ucfirst($action) . 'd designation from ' . $currentDesignation . ' to ' . $newDesignation;
    $details = json_encode([
        'acc_id' => $accId,
        'username' => $username,
        'old_designation' => $currentDesignation,
        'new_designation' => $newDesignation,
        'action' => $action
    ]);
    
    $logStmt = $conn->prepare('INSERT INTO audit_log (acc_id, action, details) VALUES (?, ?, ?)');
    $logStmt->bind_param('iss', $actorId, $actionText, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Designation ' . $action . 'd successfully',
        'old_designation' => $currentDesignation,
        'new_designation' => $newDesignation
    ]);
    
} catch (Exception $e) {
    error_log("Error in promote_demote_designation.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating designation']);
}
?>


