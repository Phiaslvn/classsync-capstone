<?php
/**
 * Get notifications for the current user
 * Notifications are generated from audit_log entries based on user role and department
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Get user info
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$userRole = $_SESSION['role'] ?? '';
$acc_id = $_SESSION['acc_id'] ?? 0;
$isAdminSupport = isAdminSupport();

if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

try {
    $notifications = [];
    
    // Get notifications based on user role
    if ($isAdminSupport) {
        // Admin Support sees all notifications
        $query = "
            SELECT 
                al.log_id as id,
                al.action,
                -- Convert to local time (server likely stores UTC; adjust as needed)
                CONVERT_TZ(al.log_date, '+00:00', '+08:00') as created_at,
                al.details,
                a.acc_id,
                a.fname,
                a.lname,
                a.dept_id,
                d.dept_name
            FROM audit_log al
            JOIN account a ON al.acc_id = a.acc_id
            LEFT JOIN department d ON a.dept_id = d.dept_id
            WHERE al.log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY al.log_date DESC
            LIMIT 50
        ";
    } elseif ($userRole === 'Admin') {
        // Admin (Department Head) sees:
        // 1. Room requests for their department's rooms (need to check room ownership)
        // 2. Room request approvals/declines for requests made by their department members
        // 3. Instructor account verifications in their department
        // 4. Account additions in their department
        // 5. Any audit entry whose actor account belongs to their department
        // 6. Room access grants where their department is the granted_to_dept_id
        $query = "
            SELECT DISTINCT
                al.log_id as id,
                al.action,
                CONVERT_TZ(al.log_date, '+00:00', '+08:00') as created_at,
                al.details,
                a.acc_id,
                a.fname,
                a.lname,
                a.dept_id,
                d.dept_name
            FROM audit_log al
            JOIN account a ON al.acc_id = a.acc_id
            LEFT JOIN department d ON a.dept_id = d.dept_id
            LEFT JOIN room r ON JSON_EXTRACT(al.details, '$.room_id') = r.rm_id
            WHERE al.log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND (
                -- Room requests for this department's rooms (room belongs to admin's dept)
                (al.action LIKE '%room request%' AND r.dept_id = ? AND JSON_EXTRACT(al.details, '$.requester_dept_id') != ?)
                OR
                -- Room request approvals/declines for requests made by this department's members
                ((al.action LIKE '%Approved room request%' OR al.action LIKE '%Declined room request%') 
                 AND JSON_EXTRACT(al.details, '$.requester_dept_id') = ?)
                OR
                -- Instructor verifications in this department
                (al.action LIKE '%verified%' AND a.dept_id = ?)
                OR
                -- Account additions in this department
                (al.action LIKE '%Added new instructor account%' AND JSON_EXTRACT(al.details, '$.department_id') = ?)
                OR
                -- All audit entries by accounts in this department (not only moderators)
                (a.dept_id = ?)
                OR
                -- Room access grants where this department is the granted_to_dept_id
                ((al.action LIKE '%Granted room access%' OR al.action LIKE '%granted access%') 
                 AND JSON_EXTRACT(al.details, '$.granted_to_dept_id') = ?)
            )
            ORDER BY al.log_date DESC
            LIMIT 50
        ";
    } else {
        // Moderators and Instructors see their own notifications
        $query = "
            SELECT 
                al.log_id as id,
                al.action,
                CONVERT_TZ(al.log_date, '+00:00', '+08:00') as created_at,
                al.details,
                a.acc_id,
                a.fname,
                a.lname,
                a.dept_id,
                d.dept_name
            FROM audit_log al
            JOIN account a ON al.acc_id = a.acc_id
            LEFT JOIN department d ON a.dept_id = d.dept_id
            WHERE al.log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND (
                -- Room request approvals/declines for requests made by this user's department
                ((al.action LIKE '%Approved room request%' OR al.action LIKE '%Declined room request%') 
                 AND JSON_EXTRACT(al.details, '$.requester_dept_id') = ?)
                OR
                -- Room request submissions (if they submitted it themselves)
                ((al.action LIKE '%room request submitted%' OR al.action LIKE '%Room request submitted%') 
                 AND JSON_EXTRACT(al.details, '$.requester_dept_id') = ?)
                OR
                -- Account verification
                (al.acc_id = ? AND al.action LIKE '%verified%')
                OR
                -- Room access grants where this department is the granted_to_dept_id
                ((al.action LIKE '%Granted room access%' OR al.action LIKE '%granted access%') 
                 AND JSON_EXTRACT(al.details, '$.granted_to_dept_id') = ?)
                OR
                -- Added to a different department (check if details contains instructor's account_id)
                ((al.action LIKE '%Added existing instructor to department%' OR al.action LIKE '%Added existing instructor%')
                 AND JSON_EXTRACT(al.details, '$.account_id') = ?)
            )
            ORDER BY al.log_date DESC
            LIMIT 50
        ";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($isAdminSupport) {
        // No parameters needed
    } elseif ($userRole === 'Admin') {
        $stmt->bind_param("iiiiiii", $userDeptId, $userDeptId, $userDeptId, $userDeptId, $userDeptId, $userDeptId, $userDeptId);
    } else {
        $stmt->bind_param("iiiii", $userDeptId, $userDeptId, $acc_id, $userDeptId, $acc_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $details = json_decode($row['details'], true) ?? [];
        
        // Generate notification title and message based on action
        $notification = generateNotification($row['action'], $details, $row, $userDeptId, $isAdminSupport);
        
        if ($notification) {
            $notifications[] = $notification;
        }
    }
    
    $stmt->close();
    
    // Check which notifications are read based on marked_as_read in details JSON
    // Also check session for backward compatibility
    if (!isset($_SESSION['read_notifications'])) {
        $_SESSION['read_notifications'] = [];
    }
    
    // Mark notifications as read if marked_as_read exists in details or in session
    foreach ($notifications as &$notification) {
        $isMarkedAsRead = false;
        
        // Check if marked_as_read exists in details JSON
        if (isset($notification['details']['marked_as_read']) && !empty($notification['details']['marked_as_read'])) {
            $isMarkedAsRead = true;
        }
        
        // Also check session for backward compatibility
        if (!$isMarkedAsRead && in_array($notification['id'], $_SESSION['read_notifications'])) {
            $isMarkedAsRead = true;
        }
        
        $notification['read'] = $isMarkedAsRead;
    }
    unset($notification);
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'count' => count($notifications),
        'unread_count' => count(array_filter($notifications, function($n) { return !$n['read']; }))
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching notifications.'
    ]);
}

/**
 * Generate notification object from audit log entry
 * @param string $action - The action from audit log
 * @param array $details - Decoded JSON details from audit log
 * @param array $row - The audit log row data
 * @param int $viewerDeptId - The department ID of the user viewing the notification
 * @param bool $isAdminSupportViewer - Whether the viewer is Admin Support
 */
function generateNotification($action, $details, $row, $viewerDeptId = 0, $isAdminSupportViewer = false) {
    $notification = [
        'id' => $row['id'],
        'title' => '',
        'message' => '',
        'type' => 'info',
        'created_at' => $row['created_at'],
        'read' => false, // Can be enhanced with read_notifications table
        'action' => $action,
        'actor' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
        'department' => $row['dept_name'] ?? 'Unknown',
        'target_tab' => null, // Tab ID to navigate to
        'target_url' => null,  // URL to navigate to (if needed)
        'details' => $details  // Include details for navigation (acc_id, room_id, etc.)
    ];
    
    // Parse different action types and set navigation targets
    // IMPORTANT: For Admin Support viewers, some modules (like room management) don't exist,
    // so we avoid setting target_tab for those to prevent "dead" clicks.
    if (stripos($action, 'room request') !== false && !$isAdminSupportViewer) {
        $notification['target_tab'] = 'room_requests';
        $requesterDeptId = isset($details['requester_dept_id']) ? (int)$details['requester_dept_id'] : 0;
        $isRequesterDept = ($viewerDeptId > 0 && $requesterDeptId > 0 && $viewerDeptId == $requesterDeptId);
        
        if (stripos($action, 'Approved') !== false) {
            $notification['title'] = 'Room Request Approved';
            $roomName = $details['room_name'] ?? 'a room';
            if ($isRequesterDept) {
                $notification['message'] = "Your room request for {$roomName} has been approved.";
            } else {
                $requester = $details['requester'] ?? 'A user';
                $notification['message'] = "Room request from {$requester} for {$roomName} has been approved.";
            }
            $notification['type'] = 'success';
        } elseif (stripos($action, 'Declined') !== false) {
            $notification['title'] = 'Room Request Declined';
            $roomName = $details['room_name'] ?? 'a room';
            if ($isRequesterDept) {
                $notification['message'] = "Your room request for {$roomName} has been declined.";
            } else {
                $requester = $details['requester'] ?? 'A user';
                $notification['message'] = "Room request from {$requester} for {$roomName} has been declined.";
            }
            $notification['type'] = 'warning';
        } elseif (stripos($action, 'submitted') !== false || stripos($action, 'Room request submitted') !== false || 
                   (stripos($action, 'automatically approved') !== false && isset($details['auto_approved']) && $details['auto_approved'])) {
            $notification['title'] = 'New Room Request';
            $requester = $details['requester'] ?? ($row['fname'] . ' ' . $row['lname']);
            $deptName = $details['requester_dept_name'] ?? $row['dept_name'] ?? 'Unknown Department';
            $roomName = $details['room_name'] ?? 'a room';
            $notification['message'] = "{$requester} from {$deptName} requested {$roomName}.";
            $notification['type'] = 'info';
        } elseif (stripos($action, 'Archived') !== false) {
            $notification['title'] = 'Room Request Archived';
            $roomName = $details['room_name'] ?? 'a room';
            $notification['message'] = "Room request for {$roomName} has been archived.";
            $notification['type'] = 'info';
        } else {
            $notification['title'] = 'Room Request Update';
            $notification['message'] = $action;
            $notification['type'] = 'info';
        }
    } elseif (stripos($action, 'verified') !== false || stripos($action, 'Email verified') !== false) {
        // Map to correct tab based on dashboard type
        // Admin Support uses ?tab=roles, others use internal "roles" tab
        $notification['target_tab'] = 'roles';
        $notification['title'] = 'Account Verified';
        $instructorName = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
        $notification['message'] = "Instructor {$instructorName} has verified their account.";
        $notification['type'] = 'success';
    } elseif (stripos($action, 'Added existing instructor to department') !== false || stripos($action, 'Added existing instructor') !== false) {
        // Notification for instructor when they are added to a new department
        $notification['target_tab'] = null; // No specific tab to navigate to
        $notification['title'] = 'Added to New Department';
        $departmentName = $details['department_name'] ?? 'a department';
        $notification['message'] = "You have been added to {$departmentName}. You now have access to schedules and resources from this department.";
        $notification['type'] = 'info';
    } elseif (stripos($action, 'Added new instructor account') !== false || stripos($action, 'Added new user') !== false || stripos($action, 'Created user account') !== false) {
        // Map to correct tab based on dashboard type
        // Admin Support uses ?tab=users, others use internal "roles" tab
        $notification['target_tab'] = $isAdminSupportViewer ? 'users' : 'roles';
        $notification['title'] = 'New User Account';
        $username = $details['username'] ?? 'Unknown';
        $fname = $details['fname'] ?? '';
        $lname = $details['lname'] ?? '';
        $name = trim("{$fname} {$lname}") ?: $username;
        $notification['message'] = "New user account created: {$username} ({$name}).";
        $notification['type'] = 'info';
    } elseif (stripos($action, 'Updated user') !== false || stripos($action, 'Edited user') !== false) {
        $notification['target_tab'] = $isAdminSupportViewer ? 'users' : null;
        $notification['title'] = 'User Updated';
        $username = $details['username'] ?? 'Unknown';
        $notification['message'] = "User account {$username} has been updated.";
        $notification['type'] = 'info';
    } elseif (stripos($action, 'Deleted user') !== false || stripos($action, 'Removed user') !== false || stripos($action, 'bulk_delete_users') !== false) {
        $notification['target_tab'] = $isAdminSupportViewer ? 'users' : null;
        $notification['title'] = 'User Deleted';
        $username = $details['username'] ?? 'Unknown';
        $count = $details['count'] ?? '';
        $notification['message'] = $count ? "{$count} user(s) have been deleted." : "User account {$username} has been deleted.";
        $notification['type'] = 'warning';
    } elseif (stripos($action, 'Assigned') !== false && stripos($action, 'role') !== false || stripos($action, 'assign_user_role') !== false) {
        $notification['target_tab'] = $isAdminSupportViewer ? 'roles' : 'roles';
        $notification['title'] = 'Role Assigned';
        $username = $details['username'] ?? 'Unknown';
        $roleName = $details['role_name'] ?? 'Unknown';
        $notification['message'] = "Role '{$roleName}' has been assigned to {$username}.";
        $notification['type'] = 'info';
    } elseif (stripos($action, 'Updated system role permissions') !== false || stripos($action, 'update_role_permissions') !== false) {
        $notification['target_tab'] = $isAdminSupportViewer ? 'roles' : null;
        $notification['title'] = 'Role Permissions Updated';
        $notification['message'] = "System role permissions have been updated.";
        $notification['type'] = 'info';
    } elseif (stripos($action, 'Demoted') !== false || stripos($action, 'demote') !== false || stripos($action, 'demote_user') !== false) {
        $notification['target_tab'] = $isAdminSupportViewer ? 'users' : null;
        $notification['title'] = 'User Demoted';
        $username = $details['username'] ?? 'Unknown';
        $notification['message'] = "User {$username} has been demoted from Admin Support to Admin.";
        $notification['type'] = 'warning';
    } elseif (stripos($action, 'bulk_activate_users') !== false || stripos($action, 'bulk_deactivate_users') !== false) {
        $notification['target_tab'] = $isAdminSupportViewer ? 'users' : null;
        $count = $details['count'] ?? 'multiple';
        if (stripos($action, 'activate') !== false) {
            $notification['title'] = 'Users Activated';
            $notification['message'] = "{$count} user(s) have been activated.";
            $notification['type'] = 'success';
        } else {
            $notification['title'] = 'Users Deactivated';
            $notification['message'] = "{$count} user(s) have been deactivated.";
            $notification['type'] = 'warning';
        }
    } elseif ((stripos($action, 'Granted room access') !== false || stripos($action, 'granted access') !== false) && !$isAdminSupportViewer) {
        // Use special target to navigate to room_requests tab and activate the rooms sub-tab
        $notification['target_tab'] = 'room_requests_rooms';
        $notification['title'] = 'Room Access Granted';
        
        // Check if the viewer's department is the one that was granted access
        $grantedToDeptId = isset($details['granted_to_dept_id']) ? (int)$details['granted_to_dept_id'] : 0;
        $isGrantedToViewerDept = ($viewerDeptId > 0 && $grantedToDeptId > 0 && $viewerDeptId == $grantedToDeptId);
        
        if ($isGrantedToViewerDept) {
            // This department was granted access
            $roomName = $details['room_name'] ?? 'a room';
            $grantedByDeptName = $details['granted_by_dept_name'] ?? 'another department';
            $notification['message'] = "Your department has been granted access to request {$roomName} from {$grantedByDeptName}.";
            $notification['type'] = 'success';
        } else {
            // Another department was granted access (for the department that owns the room)
            $roomName = $details['room_name'] ?? 'a room';
            $grantedToDeptName = $details['granted_to_dept_name'] ?? 'another department';
            $notification['message'] = "Access to {$roomName} has been granted to {$grantedToDeptName}.";
            $notification['type'] = 'info';
        }
    } elseif (stripos($action, 'Revoked room access') !== false && !$isAdminSupportViewer || stripos($action, 'revoked access') !== false && !$isAdminSupportViewer) {
        $notification['target_tab'] = 'room_requests';
        $notification['title'] = 'Room Access Revoked';
        $notification['message'] = "Room access has been revoked.";
        $notification['type'] = 'warning';
    } elseif (stripos($action, 'Added new program') !== false || stripos($action, 'Updated program') !== false || stripos($action, 'Deleted program') !== false) {
        // Admin support doesn't have course_management tab, so don't set target_tab
        $notification['target_tab'] = $isAdminSupportViewer ? null : 'course_management';
        if (stripos($action, 'Added new program') !== false) {
            $notification['title'] = 'New Program Added';
            $programName = $details['program_name'] ?? 'Unknown';
            $deptName = $details['department_name'] ?? 'Unknown Department';
            $notification['message'] = "New program '{$programName}' has been added to {$deptName}.";
            $notification['type'] = 'info';
        } elseif (stripos($action, 'Updated program') !== false) {
            $notification['title'] = 'Program Updated';
            $programName = $details['program_name'] ?? 'Unknown';
            $notification['message'] = "Program '{$programName}' has been updated.";
            $notification['type'] = 'info';
        } else {
            $notification['title'] = 'Program Deleted';
            $notification['message'] = "A program has been deleted.";
            $notification['type'] = 'warning';
        }
    } elseif (stripos($action, 'room request') !== false && $isAdminSupportViewer) {
        // Admin support doesn't have room management, so don't set target_tab
        $notification['target_tab'] = null;
        if (stripos($action, 'Approved') !== false) {
            $notification['title'] = 'Room Request Approved';
            $roomName = $details['room_name'] ?? 'a room';
            $requester = $details['requester'] ?? 'A user';
            $notification['message'] = "Room request from {$requester} for {$roomName} has been approved.";
            $notification['type'] = 'success';
        } elseif (stripos($action, 'Declined') !== false) {
            $notification['title'] = 'Room Request Declined';
            $roomName = $details['room_name'] ?? 'a room';
            $requester = $details['requester'] ?? 'A user';
            $notification['message'] = "Room request from {$requester} for {$roomName} has been declined.";
            $notification['type'] = 'warning';
        } elseif (stripos($action, 'submitted') !== false || 
                   (stripos($action, 'automatically approved') !== false && isset($details['auto_approved']) && $details['auto_approved'])) {
            $notification['title'] = 'New Room Request';
            $requester = $details['requester'] ?? ($row['fname'] . ' ' . $row['lname']);
            $deptName = $details['requester_dept_name'] ?? $row['dept_name'] ?? 'Unknown Department';
            $roomName = $details['room_name'] ?? 'a room';
            $notification['message'] = "{$requester} from {$deptName} requested {$roomName}.";
            $notification['type'] = 'info';
        } else {
            $notification['title'] = 'Room Request Update';
            $notification['message'] = $action;
            $notification['type'] = 'info';
        }
    } elseif (stripos($action, 'Granted room access') !== false && $isAdminSupportViewer) {
        // Admin support doesn't have room management
        $notification['target_tab'] = null;
        $notification['title'] = 'Room Access Granted';
        $roomName = $details['room_name'] ?? 'a room';
        $grantedToDeptName = $details['granted_to_dept_name'] ?? 'another department';
        $notification['message'] = "Access to {$roomName} has been granted to {$grantedToDeptName}.";
        $notification['type'] = 'info';
    } elseif (stripos($action, 'Revoked room access') !== false && $isAdminSupportViewer) {
        // Admin support doesn't have room management
        $notification['target_tab'] = null;
        $notification['title'] = 'Room Access Revoked';
        $notification['message'] = "Room access has been revoked.";
        $notification['type'] = 'warning';
    } else {
        // Generic notification - no specific target
        $notification['title'] = 'System Update';
        $notification['message'] = $action;
        $notification['type'] = 'info';
    }
    
    return $notification;
}
?>


