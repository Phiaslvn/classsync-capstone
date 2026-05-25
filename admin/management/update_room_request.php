<?php
/**
 * Update room request status (Approve/Decline)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

if (!hasPermission('manage_rooms') && !hasPermission('approve_room_requests')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get user info
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();
$acc_id = $_SESSION['acc_id'] ?? 0;

// Get form data
$req_id = (int)($_POST['req_id'] ?? 0);
$req_status = trim($_POST['req_status'] ?? '');
$action = trim($_POST['action'] ?? '');

if ($req_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required.']);
    exit;
}

// Handle archive action
if ($action === 'archive') {
    $req_status = 'Archived';
}

if (!in_array($req_status, ['Accepted', 'Declined', 'Archived'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status. Must be Accepted, Declined, or Archived.']);
    exit;
}

try {
    // First, verify the request exists and the room belongs to the user's department
    $checkQuery = "SELECT rr.req_id, r.dept_id, r.rm_name, i.inst_fname, i.inst_lname, a.dept_id as requester_dept_id
                   FROM room_request rr
                   JOIN room r ON rr.rm_id = r.rm_id
                   JOIN instructor i ON rr.inst_id = i.inst_id
                   JOIN account a ON i.inst_user = a.acc_user
                   WHERE rr.req_id = ?";
    
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $req_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $request = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Room request not found.']);
        exit;
    }
    
    // Verify room ownership (unless Admin Support)
    if (!$isAdminSupport && $userDeptId > 0) {
        if ($request['dept_id'] != $userDeptId) {
            echo json_encode(['success' => false, 'message' => 'You can only approve/decline requests for rooms in your department.']);
            exit;
        }
        
        // Prevent self-approval: Cannot approve/decline requests from your own department
        // Only approve requests from OTHER departments that want to use your rooms
        if ($request['requester_dept_id'] == $userDeptId) {
            echo json_encode(['success' => false, 'message' => 'You cannot approve or decline requests from your own department.']);
            exit;
        }
    }
    
    // Update request status
    $updateQuery = "UPDATE room_request SET req_status = ? WHERE req_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $req_status, $req_id);
    
    if ($updateStmt->execute()) {
        // Log the action
        $logAction = $req_status === 'Accepted' 
            ? "Approved room request: Request ID $req_id" 
            : ($req_status === 'Archived' 
                ? "Archived room request: Request ID $req_id"
                : "Declined room request: Request ID $req_id");
        $details = json_encode([
            'req_id' => $req_id,
            'room_name' => $request['rm_name'],
            'requester' => $request['inst_fname'] . ' ' . $request['inst_lname'],
            'requester_dept_id' => $request['requester_dept_id'],
            'status' => $req_status,
            'updated_by' => $acc_id
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $logStmt->bind_param("iss", $acc_id, $logAction, $details);
        $logStmt->execute();
        $logStmt->close();
        
        $message = $req_status === 'Archived' 
            ? "Room request archived successfully."
            : "Room request {$req_status} successfully.";
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update room request status.']);
    }
    
    $updateStmt->close();
    
} catch (Exception $e) {
    error_log("Error updating room request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the room request.'
    ]);
}
?>

