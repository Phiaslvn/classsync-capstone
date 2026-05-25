<?php
/**
 * Get room requests for the current user's department
 * Shows:
 * 1. Incoming requests: Requests from other departments wanting to use this department's rooms
 * 2. Outgoing requests: Requests from this department to use other departments' rooms
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Allow viewing accepted requests for a specific room without full permissions
// This is for the "View Accepted Requests" feature in room schedule modal
$isViewOnlyRequest = isset($_GET['room']) && isset($_GET['status']) && $_GET['status'] === 'Accepted';
$hasFullPermission = hasPermission('manage_rooms') || hasPermission('approve_room_requests');

// Get user info
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();
$userRole = $_SESSION['role'] ?? '';

// Permission check:
// 1. View-only request (specific room accepted requests) - always allowed
// 2. Full permissions (manage_rooms or approve_room_requests) - allowed
// 3. Admin role - allowed to view room requests for their department's rooms (read-only)
// 4. Otherwise - denied
$isAdminViewingOwnRooms = ($userRole === 'Admin' && $userDeptId > 0);

if (!$isViewOnlyRequest && !$hasFullPermission && !$isAdminViewingOwnRooms) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    // Get optional filters
    $statusFilter = $_GET['status'] ?? '';
    $roomFilter = $_GET['room'] ?? '';
    $searchFilter = $_GET['search'] ?? '';
    $excludeArchived = isset($_GET['exclude_archived']) && $_GET['exclude_archived'] == '1';
    
    // Query to get room requests:
    // 1. For rooms owned by the user's department (incoming requests)
    // 2. Made by users from the user's department (outgoing requests)
    // Use $userDeptId directly in CASE since it's a safe integer
    $userDeptIdSafe = (int)$userDeptId; // Ensure it's an integer for SQL injection safety
    
    $query = "
        SELECT 
            rr.req_id,
            rr.req_date,
            rr.req_status,
            rr.req_comment,
            rr.schd_day,
            rr.schd_start,
            rr.schd_end,
            r.rm_id,
            r.rm_name,
            b.bd_desc as building_name,
            CONCAT(b.bd_desc, ' - ', r.rm_name) as room_display,
            r.dept_id as room_dept_id,
            rd.dept_name as room_dept_name,
            i.inst_id,
            CONCAT(i.inst_fname, ' ', i.inst_lname) as requester_name,
            a.acc_id,
            a.dept_id as requester_dept_id,
            d.dept_name as requester_dept_name,
            DATE(rr.req_date) as request_date_only,
            TIME_FORMAT(rr.schd_start, '%h:%i %p') as start_time_formatted,
            TIME_FORMAT(rr.schd_end, '%h:%i %p') as end_time_formatted,
            CASE 
                WHEN rr.schd_start IS NOT NULL AND rr.schd_end IS NOT NULL 
                THEN CONCAT(TIME_FORMAT(rr.schd_start, '%h:%i %p'), ' - ', TIME_FORMAT(rr.schd_end, '%h:%i %p'))
                ELSE NULL
            END as time_display,
            CASE 
                WHEN r.dept_id = $userDeptIdSafe THEN 'incoming'
                WHEN a.dept_id = $userDeptIdSafe THEN 'outgoing'
                ELSE 'unknown'
            END as request_type
        FROM room_request rr
        JOIN room r ON rr.rm_id = r.rm_id
        JOIN building b ON r.bd_id = b.bd_id
        JOIN instructor i ON rr.inst_id = i.inst_id
        JOIN account a ON i.inst_user = a.acc_user
        LEFT JOIN department d ON a.dept_id = d.dept_id
        LEFT JOIN department rd ON r.dept_id = rd.dept_id
        WHERE 1=1
        -- Exclude expired Make Up Class requests (expired if next Monday after request date has passed OR time has passed today)
        AND (
            -- Regular Class: Always included (no automatic expiration)
            rr.req_comment NOT LIKE '%[Class Type: Make Up Class]%'
            OR
            -- Make Up Class: Check both date expiration AND time expiration
            (
                rr.req_comment LIKE '%[Class Type: Make Up Class]%'
                -- Check if date hasn't expired (next Monday hasn't passed)
                AND DATE(rr.req_date) + INTERVAL (
                    CASE DAYOFWEEK(DATE(rr.req_date))
                        WHEN 2 THEN 7  -- Monday: next Monday is 7 days away
                        ELSE (9 - DAYOFWEEK(DATE(rr.req_date))) % 7
                    END
                ) DAY > CURDATE()
                -- For Make Up Class: If today, check if time slot has passed
                AND (
                    -- If request date is NOT today, include it (future date)
                    DATE(rr.req_date) != CURDATE()
                    OR
                    -- If request date IS today, only include if current time < schd_end
                    (DATE(rr.req_date) = CURDATE() AND TIME(NOW()) < rr.schd_end)
                )
            )
        )";
    
    $params = [];
    $types = '';
    
    // Filter: Show both incoming (rooms owned by user's dept) and outgoing (requests from user's dept) requests
    // Unless Admin Support, then show all
    // Skip department filter for view-only requests (viewing accepted requests for a specific room)
    // This allows showing ALL accepted requests for that room regardless of department
    if (!$isViewOnlyRequest && !$isAdminSupport && $userDeptId > 0) {
        // Show requests where room belongs to user's dept OR requester belongs to user's dept
        $query .= " AND (r.dept_id = ? OR a.dept_id = ?)";
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $types .= 'ii';
    }
    
    // Filter by status
    if (!empty($statusFilter)) {
        $query .= " AND rr.req_status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    } elseif ($excludeArchived) {
        // Exclude archived requests from main table if exclude_archived is set
        $query .= " AND rr.req_status != 'Archived'";
    }
    
    // Filter by room
    if (!empty($roomFilter)) {
        $query .= " AND r.rm_id = ?";
        $params[] = $roomFilter;
        $types .= 'i';
    }
    
    // Search filter
    if (!empty($searchFilter)) {
        $query .= " AND (
            r.rm_name LIKE ? OR 
            b.bd_desc LIKE ? OR
            CONCAT(i.inst_fname, ' ', i.inst_lname) LIKE ? OR
            d.dept_name LIKE ? OR
            rr.req_comment LIKE ?
        )";
        $searchTerm = '%' . $searchFilter . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sssss';
    }
    
    $query .= " ORDER BY rr.req_date DESC";
    
    // Debug logging (remove in production)
    error_log("Room Requests Query: " . $query);
    error_log("Room Requests Params: " . json_encode($params));
    error_log("Room Requests Types: " . $types);
    error_log("User Dept ID: " . $userDeptId);
    error_log("Status Filter: " . $statusFilter);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    if (!empty($params)) {
        $bindResult = $stmt->bind_param($types, ...$params);
        if (!$bindResult) {
            throw new Exception("Failed to bind parameters: " . $stmt->error);
        }
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Debug logging
    error_log("Room Requests Found: " . count($requests));
    
    // Process requests
    $processedRequests = [];
    foreach ($requests as $request) {
        // Calculate duration in hours (handle NULL values)
        $durationHours = 0;
        if (!empty($request['schd_start']) && !empty($request['schd_end'])) {
            $startTime = strtotime($request['schd_start']);
            $endTime = strtotime($request['schd_end']);
            if ($startTime !== false && $endTime !== false && $endTime > $startTime) {
                $durationMinutes = ($endTime - $startTime) / 60;
                $durationHours = round($durationMinutes / 60, 1);
            }
        }
        
        $processedRequests[] = [
            'req_id' => $request['req_id'],
            'req_date' => $request['req_date'],
            'request_date' => $request['request_date_only'],
            'request_time' => $request['time_display'] ?? '-',
            'req_status' => $request['req_status'],
            'req_comment' => $request['req_comment'],
            'rm_id' => $request['rm_id'],
            'rm_name' => $request['rm_name'],
            'building_name' => $request['building_name'],
            'room_display' => $request['room_display'],
            'room_dept_id' => $request['room_dept_id'],
            'room_dept_name' => $request['room_dept_name'] ?? '',
            'requester_name' => $request['requester_name'],
            'requester_dept_id' => $request['requester_dept_id'],
            'requester_dept_name' => $request['requester_dept_name'],
            'day' => $request['schd_day'] ?? '',
            'duration' => $durationHours,
            'request_type' => $request['request_type'] ?? 'unknown'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processedRequests
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching room requests: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching room requests.'
    ]);
}
?>

