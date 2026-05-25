<?php
/**
 * Room Access Management API
 * Allows department heads to grant/revoke access to their rooms for other departments
 */

// Prevent any output before JSON
ob_start();

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Only Admin (Department Head) and Admin Support can manage room access
if (!isAdmin() && !isAdminSupport()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Only department heads can manage room access.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

try {
    switch ($method) {
        case 'GET':
            // Get room access information
            $rm_id = (int)($_GET['rm_id'] ?? 0);
            
            if ($rm_id > 0) {
                // Get access list for a specific room
                $query = "SELECT ra.access_id, ra.granted_to_dept_id, d.dept_name, 
                                 ra.granted_by_acc_id, a.fname, a.lname,
                                 ra.granted_at, ra.status
                          FROM room_access ra
                          JOIN department d ON ra.granted_to_dept_id = d.dept_id
                          LEFT JOIN account a ON ra.granted_by_acc_id = a.acc_id
                          WHERE ra.rm_id = ? AND ra.status = 'Active'
                          ORDER BY ra.granted_at DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $rm_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $accessList = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'data' => $accessList
                ]);
            } else {
                // Get all rooms with their access status for the user's department
                $query = "SELECT r.rm_id, r.rm_name, r.dept_id, d.dept_name,
                                 (SELECT COUNT(*) FROM room_access ra 
                                  WHERE ra.rm_id = r.rm_id AND ra.status = 'Active') as access_count
                          FROM room r
                          LEFT JOIN department d ON r.dept_id = d.dept_id";
                
                if (!$isAdminSupport && $userDeptId > 0) {
                    $query .= " WHERE r.dept_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $userDeptId);
                } else {
                    $stmt = $conn->prepare($query);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $rooms = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'data' => $rooms
                ]);
            }
            break;
            
        case 'POST':
            // Grant access to a department
            $input = json_decode(file_get_contents('php://input'), true);
            $rm_id = (int)($input['rm_id'] ?? 0);
            $granted_to_dept_id = (int)($input['granted_to_dept_id'] ?? 0);
            
            if ($rm_id <= 0 || $granted_to_dept_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Room ID and Department ID are required.']);
                exit;
            }
            
            // Verify the room belongs to the user's department (unless Admin Support)
            $roomCheck = $conn->prepare("SELECT dept_id FROM room WHERE rm_id = ?");
            $roomCheck->bind_param("i", $rm_id);
            $roomCheck->execute();
            $roomResult = $roomCheck->get_result();
            $room = $roomResult->fetch_assoc();
            $roomCheck->close();
            
            if (!$room) {
                echo json_encode(['success' => false, 'message' => 'Room not found.']);
                exit;
            }
            
            if (!$isAdminSupport && $room['dept_id'] != $userDeptId) {
                echo json_encode(['success' => false, 'message' => 'You can only grant access to rooms in your department.']);
                exit;
            }
            
            // Check if access already exists
            $checkQuery = "SELECT access_id FROM room_access 
                          WHERE rm_id = ? AND granted_to_dept_id = ? AND status = 'Active'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $rm_id, $granted_to_dept_id);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Access has already been granted to this department.']);
                exit;
            }
            
            // Grant access
            $grantQuery = "INSERT INTO room_access (rm_id, granted_to_dept_id, granted_by_dept_id, granted_by_acc_id, status) 
                          VALUES (?, ?, ?, ?, 'Active')";
            $grantStmt = $conn->prepare($grantQuery);
            $granted_by_dept_id = $room['dept_id'];
            $granted_by_acc_id = $_SESSION['acc_id'];
            $grantStmt->bind_param("iiii", $rm_id, $granted_to_dept_id, $granted_by_dept_id, $granted_by_acc_id);
            
            if ($grantStmt->execute()) {
                // Get room name and department names for better notification details
                $infoQuery = "SELECT r.rm_name, 
                                    d1.dept_name as granted_by_dept_name,
                                    d2.dept_name as granted_to_dept_name
                             FROM room r
                             JOIN department d1 ON r.dept_id = d1.dept_id
                             JOIN department d2 ON d2.dept_id = ?
                             WHERE r.rm_id = ?";
                $infoStmt = $conn->prepare($infoQuery);
                $infoStmt->bind_param("ii", $granted_to_dept_id, $rm_id);
                $infoStmt->execute();
                $infoResult = $infoStmt->get_result();
                $info = $infoResult->fetch_assoc();
                $infoStmt->close();
                
                // Log the action with detailed information
                $action = "Granted room access: Room ID $rm_id to Department ID $granted_to_dept_id";
                $details = json_encode([
                    'room_id' => $rm_id,
                    'room_name' => $info['rm_name'] ?? 'Unknown Room',
                    'granted_to_dept_id' => $granted_to_dept_id,
                    'granted_to_dept_name' => $info['granted_to_dept_name'] ?? 'Unknown Department',
                    'granted_by_dept_id' => $granted_by_dept_id,
                    'granted_by_dept_name' => $info['granted_by_dept_name'] ?? 'Unknown Department',
                    'granted_by' => $_SESSION['acc_id']
                ]);
                
                $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
                $logStmt->bind_param("iss", $_SESSION['acc_id'], $action, $details);
                $logStmt->execute();
                $logStmt->close();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Room access granted successfully.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to grant access.']);
            }
            $grantStmt->close();
            break;
            
        case 'DELETE':
            // Revoke access
            $input = json_decode(file_get_contents('php://input'), true);
            $access_id = (int)($input['access_id'] ?? 0);
            
            if ($access_id <= 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Access ID is required.']);
                exit;
            }
            
            // Verify the access record and room ownership
            $verifyQuery = "SELECT ra.rm_id, ra.granted_to_dept_id, r.dept_id 
                           FROM room_access ra
                           JOIN room r ON ra.rm_id = r.rm_id
                           WHERE ra.access_id = ?";
            $verifyStmt = $conn->prepare($verifyQuery);
            $verifyStmt->bind_param("i", $access_id);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            $access = $verifyResult->fetch_assoc();
            $verifyStmt->close();
            
            if (!$access) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Access record not found.']);
                exit;
            }
            
            if (!$isAdminSupport && $access['dept_id'] != $userDeptId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'You can only revoke access to rooms in your department.']);
                exit;
            }
            
            // Revoke access by deleting the record (since unique constraint prevents multiple 'Revoked' records)
            // First, check if there's already a 'Revoked' record for this room-dept combination
            $checkRevokedQuery = "SELECT access_id FROM room_access 
                                  WHERE rm_id = ? AND granted_to_dept_id = ? AND status = 'Revoked'";
            $checkRevokedStmt = $conn->prepare($checkRevokedQuery);
            $checkRevokedStmt->bind_param("ii", $access['rm_id'], $access['granted_to_dept_id']);
            $checkRevokedStmt->execute();
            $revokedResult = $checkRevokedStmt->get_result();
            $existingRevoked = $revokedResult->fetch_assoc();
            $checkRevokedStmt->close();
            
            // Delete the current access record
            $revokeQuery = "DELETE FROM room_access WHERE access_id = ?";
            $revokeStmt = $conn->prepare($revokeQuery);
            $revokeStmt->bind_param("i", $access_id);
            
            if ($revokeStmt->execute()) {
                // If there was an existing 'Revoked' record, we can optionally keep it for history
                // But since we're deleting, we'll just log the action
                
                // Log the action
                $action = "Revoked room access: Access ID $access_id";
                $details = json_encode([
                    'access_id' => $access_id,
                    'room_id' => $access['rm_id'],
                    'granted_to_dept_id' => $access['granted_to_dept_id'],
                    'revoked_by' => $_SESSION['acc_id']
                ]);
                
                $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
                $logStmt->bind_param("iss", $_SESSION['acc_id'], $action, $details);
                $logStmt->execute();
                $logStmt->close();
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Room access revoked successfully.'
                ]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to revoke access.']);
            }
            $revokeStmt->close();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
            break;
    }
} catch (Exception $e) {
    error_log("Room Access Management Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Ensure no output before JSON
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit;
}
?>

