<?php
/**
 * Submit Room Request
 * Allows admins to request to use rooms from other departments
 */

// Register shutdown function to catch any fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'A fatal error occurred. Please check server logs.',
            'debug' => (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] : null
        ]);
        ob_end_flush();
    }
});

// Start output buffering FIRST - before any includes
ob_start();

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL); // Still log errors, just don't display them

// Start session and include files
session_start();

// Include files - any output will be captured by ob_start
require_once '../../config/database.php';

// Check for database connection error
if (isset($db_connection_error) || !isset($conn) || $conn->connect_error) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please contact administrator.']);
    ob_end_flush();
    exit;
}

require_once '../../includes/auth/security_middleware.php';

// Clear any output that might have been generated (including PHP errors, warnings, notices)
$buffer = ob_get_contents();
if (!empty($buffer) && !preg_match('/^\s*$/', $buffer)) {
    error_log("Unexpected output before JSON: " . substr($buffer, 0, 500));
}
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check permissions
if (!hasPermission('view_schedules')) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    ob_end_flush();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    ob_end_flush();
    exit;
}

// Get user info
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$acc_id = $_SESSION['acc_id'] ?? 0;

if ($acc_id <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    ob_end_flush();
    exit;
}

// Get account info
$accQuery = "SELECT acc_user, fname, lname, minitial, dept_id FROM account WHERE acc_id = ?";
$accStmt = $conn->prepare($accQuery);
$accStmt->bind_param("i", $acc_id);
$accStmt->execute();
$accResult = $accStmt->get_result();
$account = $accResult->fetch_assoc();
$accStmt->close();

if (!$account) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    ob_end_flush();
    exit;
}

// Get or create instructor ID for the current user
$instQuery = "SELECT inst_id FROM instructor WHERE inst_user = ?";
$instStmt = $conn->prepare($instQuery);
$param_acc_user = $account['acc_user']; // Store array value in variable for bind_param
$instStmt->bind_param("s", $param_acc_user);
$instStmt->execute();
$instResult = $instStmt->get_result();
$instructor = $instResult->fetch_assoc();
$instStmt->close();

if (!$instructor || !$instructor['inst_id']) {
    // Create instructor record if it doesn't exist (for admins who don't have one yet)
    try {
        $createInstQuery = "INSERT INTO instructor (inst_user, inst_lname, inst_fname, inst_mname, inst_status, instruction_hours, dept_id, rank, designation) 
                            VALUES (?, ?, ?, ?, 'Regular', 40, ?, 'Instructor I', 'None')";
        $createInstStmt = $conn->prepare($createInstQuery);
        
        // Store array values in variables for bind_param (must be passed by reference)
        $param_acc_user = $account['acc_user'];
        $param_lname = $account['lname'];
        $param_fname = $account['fname'];
        $param_minitial = $account['minitial'] ?? '';
        $param_dept_id = $account['dept_id'];
        
        $createInstStmt->bind_param("ssssi", 
            $param_acc_user, 
            $param_lname, 
            $param_fname, 
            $param_minitial, 
            $param_dept_id
        );
        $createInstStmt->execute();
        $inst_id = $conn->insert_id;
        $createInstStmt->close();
    } catch (Exception $e) {
        error_log("Error creating instructor record: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to create instructor record. Please contact administrator.']);
        ob_end_flush();
        exit;
    }
} else {
    $inst_id = $instructor['inst_id'];
}

// Get form data
$rm_id = (int)($_POST['rm_id'] ?? 0);
$request_date = trim($_POST['request_date'] ?? '');
$day = trim($_POST['day'] ?? '');
$time_slot = trim($_POST['time_slot'] ?? '');
$start_time = trim($_POST['start_time'] ?? ''); // New: direct start time (24-hour format)
$end_time = trim($_POST['end_time'] ?? ''); // New: direct end time (24-hour format)
$duration = floatval($_POST['duration'] ?? 1);
$class_type = trim($_POST['class_type'] ?? 'Regular Class');
$comment = trim($_POST['comment'] ?? '');

// Validation
if ($rm_id <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Room ID is required.']);
    ob_end_flush();
    exit;
}

// Validate request date
if (empty($request_date)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Request date is required.']);
    ob_end_flush();
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $request_date)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    ob_end_flush();
    exit;
}

// Validate that date is not in the past
$selectedDate = strtotime($request_date);
$today = strtotime(date('Y-m-d'));
if ($selectedDate < $today) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Request date cannot be in the past.']);
    ob_end_flush();
    exit;
}

// Auto-populate day from date if not provided
if (empty($day)) {
    $dayOfWeek = date('w', $selectedDate); // 0 = Sunday, 1 = Monday, etc.
    $dayMap = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
    $day = $dayMap[$dayOfWeek] ?? '';
}

if (empty($day)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Day is required.']);
    ob_end_flush();
    exit;
}

// Prefer start_time and end_time if provided, otherwise parse from time_slot
if (!empty($start_time) && !empty($end_time)) {
    // Use direct start_time and end_time (already in 24-hour format HH:MM)
    // Validate format
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) || 
        !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid time format. Please use HH:MM format.']);
        ob_end_flush();
        exit;
    }
    
    // Convert to H:i:s format
    $startTime = $start_time . ':00';
    $endTime = $end_time . ':00';
    
    // Validate that end time is after start time
    $startTimestamp = strtotime($startTime);
    $endTimestamp = strtotime($endTime);
    
    if ($endTimestamp <= $startTimestamp) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'End time must be after start time.']);
        ob_end_flush();
        exit;
    }
} else if (!empty($time_slot)) {
    // Fallback: Parse time slot (format: "07:00 AM - 10:00 AM")
    $timeParts = explode(' - ', $time_slot);
    if (count($timeParts) !== 2) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid time slot format.']);
        ob_end_flush();
        exit;
    }
    
    $startTimeStr = trim($timeParts[0]);
    $endTimeStr = trim($timeParts[1]);
    
    // Parse times - handle both 12-hour and 24-hour formats
    $startTimestamp = strtotime($startTimeStr);
    $endTimestamp = strtotime($endTimeStr);
    
    if ($startTimestamp === false || $endTimestamp === false) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid time format. Please select a valid time slot.']);
        ob_end_flush();
        exit;
    }
    
    $startTime = date('H:i:s', $startTimestamp);
    $endTime = date('H:i:s', $endTimestamp);
    
    // Adjust end time based on duration if needed (but only if duration is different from the slot)
    if ($duration > 0) {
        $calculatedEndTimestamp = $startTimestamp + ($duration * 3600);
        // Only use duration if it's different from the slot's natural end time
        if (abs($calculatedEndTimestamp - $endTimestamp) > 60) { // More than 1 minute difference
            $endTime = date('H:i:s', $calculatedEndTimestamp);
        }
    }
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Either time slot or start/end time is required.']);
    ob_end_flush();
    exit;
}

// Use the selected request date combined with start time for req_date (datetime field)
// This allows filtering by specific date to check for conflicts
$requestDateTime = $request_date . ' ' . $startTime;

try {
    // Verify room exists and user has access
    $roomCheckQuery = "SELECT r.rm_id, r.dept_id, d.dept_name, r.rm_name
                       FROM room r 
                       LEFT JOIN department d ON r.dept_id = d.dept_id
                       WHERE r.rm_id = ?";
    $roomCheckStmt = $conn->prepare($roomCheckQuery);
    $roomCheckStmt->bind_param("i", $rm_id);
    $roomCheckStmt->execute();
    $roomResult = $roomCheckStmt->get_result();
    $room = $roomResult->fetch_assoc();
    $roomCheckStmt->close();
    
    if (!$room) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Room not found.']);
        ob_end_flush();
        exit;
    }
    
    // Check if user has access to this room
    $isAdminSupport = isAdminSupport();
    $hasAccess = false;
    
    if ($isAdminSupport) {
        $hasAccess = true;
    } elseif ($room['dept_id'] == $userDeptId) {
        $hasAccess = true;
    } else {
        // Check if access has been granted
        $accessCheckQuery = "SELECT 1 FROM room_access 
                            WHERE rm_id = ? AND granted_to_dept_id = ? AND status = 'Active'";
        $accessCheckStmt = $conn->prepare($accessCheckQuery);
        $accessCheckStmt->bind_param("ii", $rm_id, $userDeptId);
        $accessCheckStmt->execute();
        $accessResult = $accessCheckStmt->get_result();
        $hasAccess = $accessResult->num_rows > 0;
        $accessCheckStmt->close();
    }
    
    if (!$hasAccess) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'You do not have access to request this room.']);
        ob_end_flush();
        exit;
    }
    
    // Check for conflicts with existing room requests or schedules on the same date and time
    // Check conflicts with other room requests (same room, same date, overlapping time, Accepted status)
    // Exclude expired Make Up Class requests (expired if next Monday after request date has passed)
    $conflictQuery = "SELECT rr.req_id, rr.schd_day, 
                            TIME_FORMAT(rr.schd_start, '%h:%i %p') as start_time,
                            TIME_FORMAT(rr.schd_end, '%h:%i %p') as end_time,
                            CONCAT(i.inst_fname, ' ', i.inst_lname) as requester_name
                      FROM room_request rr
                      JOIN instructor i ON rr.inst_id = i.inst_id
                      WHERE rr.rm_id = ? 
                        AND DATE(rr.req_date) = ?
                        AND rr.req_status = 'Accepted'
                        AND rr.schd_day = ?
                        AND (
                            (rr.schd_start < ? AND rr.schd_end > ?)
                            OR (rr.schd_start >= ? AND rr.schd_end <= ?)
                        )
                        AND (
                            -- Include Regular Class requests (they don't expire automatically)
                            rr.req_comment NOT LIKE '%[Class Type: Make Up Class]%'
                            OR
                            -- Include Make Up Class only if not expired (next Monday hasn't passed AND time hasn't passed today)
                            -- DAYOFWEEK: 1=Sunday, 2=Monday, 3=Tuesday, ..., 7=Saturday
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
    $conflictStmt = $conn->prepare($conflictQuery);
    $conflictStmt->bind_param("issssss", $rm_id, $request_date, $day, $endTime, $startTime, $startTime, $endTime);
    $conflictStmt->execute();
    $conflictResult = $conflictStmt->get_result();
    
    if ($conflictResult->num_rows > 0) {
        $conflict = $conflictResult->fetch_assoc();
        $conflictStmt->close();
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => "This time slot is already reserved on {$request_date}. " .
                        "Time conflict: {$conflict['start_time']} - {$conflict['end_time']} " .
                        "(Requested by: {$conflict['requester_name']}). " .
                        "Please choose a different time or date."
        ]);
        ob_end_flush();
        exit;
    }
    $conflictStmt->close();
    
    // Also check for conflicts with regular schedules on the same day and time
    // (Note: This checks by day of week, not specific date, since schedules are recurring)
    $scheduleConflictQuery = "SELECT s.schd_id, subj.subj_code, sec.sec_name,
                                     TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time,
                                     TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time,
                                     CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name
                              FROM schedule s
                              JOIN subject subj ON s.subj_id = subj.subj_id
                              JOIN section sec ON s.sec_id = sec.sec_id
                              JOIN instructor i ON s.inst_id = i.inst_id
                              WHERE s.rm_id = ?
                                AND s.schd_day = ?
                                AND s.schd_status = 'Active'
                                AND (
                                    (s.schd_start < ? AND s.schd_end > ?)
                                    OR (s.schd_start >= ? AND s.schd_end <= ?)
                                )";
    $scheduleConflictStmt = $conn->prepare($scheduleConflictQuery);
    $scheduleConflictStmt->bind_param("isssss", $rm_id, $day, $endTime, $startTime, $startTime, $endTime);
    $scheduleConflictStmt->execute();
    $scheduleConflictResult = $scheduleConflictStmt->get_result();
    
    if ($scheduleConflictResult->num_rows > 0) {
        $scheduleConflict = $scheduleConflictResult->fetch_assoc();
        $scheduleConflictStmt->close();
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => "This time slot conflicts with an existing schedule on {$day}. " .
                        "Time conflict: {$scheduleConflict['start_time']} - {$scheduleConflict['end_time']} " .
                        "({$scheduleConflict['subj_code']} - {$scheduleConflict['sec_name']}, " .
                        "Instructor: {$scheduleConflict['instructor_name']}). " .
                        "Please choose a different time."
        ]);
        ob_end_flush();
        exit;
    }
    $scheduleConflictStmt->close();
    
    // Check if the new columns exist, if not, use the old format
    $columnsExist = false;
    try {
        // Check for all three columns - use DESCRIBE which is more reliable
        $checkColumnsQuery = "DESCRIBE room_request";
        $checkResult = $conn->query($checkColumnsQuery);
        if ($checkResult) {
            $existingColumns = [];
            while ($row = $checkResult->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }
            $checkResult->close();
            
            // Check if all three new columns exist
            $columnsExist = in_array('schd_day', $existingColumns) && 
                           in_array('schd_start', $existingColumns) && 
                           in_array('schd_end', $existingColumns);
            
            error_log("Room Request - Existing columns: " . implode(', ', $existingColumns));
            error_log("Room Request - New columns exist: " . ($columnsExist ? 'YES' : 'NO'));
        }
    } catch (Exception $e) {
        error_log("Error checking columns: " . $e->getMessage());
        // Assume columns don't exist and use fallback
        $columnsExist = false;
    }
    
    // Build final comment including class type (so it appears in Purpose/Comments columns)
    // For Make Up Class, add expiration date (next Monday) to the comment
    $finalComment = $comment;
    if (!empty($class_type)) {
        $prefix = "[Class Type: {$class_type}]";
        
        // For Make Up Class, calculate expiration date (next Monday after request date)
        if ($class_type === 'Make Up Class') {
            $requestDateObj = new DateTime($request_date);
            $dayOfWeek = (int)$requestDateObj->format('w'); // 0 = Sunday, 1 = Monday, etc.
            
            // Calculate days until next Monday
            // If today is Monday (1), next Monday is in 7 days
            // If today is Tuesday-Sunday, next Monday is (8 - dayOfWeek) days away
            $daysUntilMonday = $dayOfWeek == 1 ? 7 : (8 - $dayOfWeek) % 7;
            if ($daysUntilMonday == 0) $daysUntilMonday = 7; // If it's Monday, next Monday is 7 days away
            
            $expirationDate = clone $requestDateObj;
            $expirationDate->modify("+{$daysUntilMonday} days");
            $expirationDateStr = $expirationDate->format('Y-m-d');
            
            $prefix .= " [Expires: {$expirationDateStr}]";
        }
        
        $finalComment = $comment ? "{$prefix} - {$comment}" : $prefix;
    }
    
    // Debug logging
    error_log("Room Request Data: rm_id=$rm_id, inst_id=$inst_id, day=$day, startTime=$startTime, endTime=$endTime, date=$date, requestDateTime=$requestDateTime, columnsExist=" . ($columnsExist ? 'true' : 'false'));
    error_log("POST data received: " . json_encode($_POST));
    
    if ($columnsExist) {
        // Insert room request with new columns
        // Query has 7 placeholders: rm_id, inst_id, req_date, schd_day, schd_start, schd_end, req_comment
        // req_status is automatically set to 'Accepted' (automatic approval system)
        $insertQuery = "INSERT INTO room_request (rm_id, inst_id, req_date, schd_day, schd_start, schd_end, req_status, req_comment) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Accepted', ?)";
        $insertStmt = $conn->prepare($insertQuery);
        if (!$insertStmt) {
            $error = $conn->error ?: 'Unknown database error';
            error_log("Failed to prepare INSERT statement: " . $error);
            throw new Exception("Failed to prepare statement: " . $error);
        }
        
        // Store values in variables to pass by reference
        // 7 parameters: rm_id (i), inst_id (i), req_date (s), schd_day (s), schd_start (s), schd_end (s), req_comment (s)
        $param_rm_id = $rm_id;
        $param_inst_id = $inst_id;
        $param_req_date = $requestDateTime;
        $param_day = $day;
        $param_start = $startTime;
        $param_end = $endTime;
        $param_comment = $finalComment;
        
        // Type string: "iisssss" = 7 characters matching 7 parameters
        $bindResult = $insertStmt->bind_param("iisssss", $param_rm_id, $param_inst_id, $param_req_date, $param_day, $param_start, $param_end, $param_comment);
        if (!$bindResult) {
            error_log("Failed to bind parameters: " . $insertStmt->error);
            throw new Exception("Failed to bind parameters: " . $insertStmt->error);
        }
    } else {
        // Fallback to old format without new columns
        // Note: We'll store day and time info in the comment field as a workaround
        $commentWithDetails = $finalComment;
        if (!empty($day) && !empty($startTime) && !empty($endTime)) {
            $timeInfo = "\n[Requested: $day, $startTime - $endTime]";
            $commentWithDetails = ($comment ? $comment . $timeInfo : trim($timeInfo));
        }
        
        $insertQuery = "INSERT INTO room_request (rm_id, inst_id, req_date, req_status, req_comment) 
                        VALUES (?, ?, ?, 'Accepted', ?)";
        $insertStmt = $conn->prepare($insertQuery);
        if (!$insertStmt) {
            $error = $conn->error ?: 'Unknown database error';
            error_log("Failed to prepare INSERT statement: " . $error);
            throw new Exception("Failed to prepare statement: " . $error);
        }
        
        // Store values in variables to pass by reference
        $param_rm_id = $rm_id;
        $param_inst_id = $inst_id;
        $param_req_date = $requestDateTime;
        $param_comment = $commentWithDetails;
        
        $bindResult = $insertStmt->bind_param("iiss", $param_rm_id, $param_inst_id, $param_req_date, $param_comment);
        if (!$bindResult) {
            error_log("Failed to bind parameters: " . $insertStmt->error);
            throw new Exception("Failed to bind parameters: " . $insertStmt->error);
        }
    }
    
    if ($insertStmt->execute()) {
        // Get requester info for notification
        $requesterQuery = "SELECT i.inst_fname, i.inst_lname, a.dept_id, d.dept_name 
                          FROM instructor i 
                          JOIN account a ON i.inst_user = a.acc_user 
                          LEFT JOIN department d ON a.dept_id = d.dept_id
                          WHERE i.inst_id = ?";
        $requesterStmt = $conn->prepare($requesterQuery);
        $requesterStmt->bind_param("i", $inst_id);
        $requesterStmt->execute();
        $requesterResult = $requesterStmt->get_result();
        $requester = $requesterResult->fetch_assoc();
        $requesterStmt->close();
        
        // Log the action - Use "submitted" for proper notification display, even though it's auto-approved
        // This ensures admins get "New Room Request" notification instead of generic "Room Request Update"
        $action = "Room request submitted: Room ID $rm_id for " . date('Y-m-d H:i', strtotime($requestDateTime)) . " (automatically approved)";
        $details = json_encode([
            'room_id' => $rm_id,
            'room_name' => $room['rm_name'],
            'request_date' => $requestDateTime,
            'day' => $day,
            'duration' => $duration,
            'class_type' => $class_type,
            'comment' => $comment,
            'requester' => ($requester['inst_fname'] ?? '') . ' ' . ($requester['inst_lname'] ?? ''),
            'requester_dept_id' => $requester['dept_id'] ?? 0,
            'requester_dept_name' => $requester['dept_name'] ?? 'Unknown Department',
            'status' => 'Accepted',
            'auto_approved' => true
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        if ($logStmt) {
            // Store values in variables for bind_param (must be passed by reference)
            $param_acc_id = $acc_id;
            $param_action = $action;
            $param_details = $details;
            $logStmt->bind_param("iss", $param_acc_id, $param_action, $param_details);
            $logStmt->execute();
            $logStmt->close();
        }
        
        ob_clean();
        header('Content-Type: application/json');
        $response = json_encode([
            'success' => true,
            'message' => 'Room request automatically approved and submitted successfully. Status: Accepted.'
        ]);
        if ($response === false) {
            error_log("JSON encode failed: " . json_last_error_msg());
            $response = json_encode(['success' => false, 'message' => 'Failed to encode response.']);
        }
        echo $response;
        ob_end_flush();
        exit; // Explicitly exit after success
    } else {
        $errorMsg = $insertStmt->error;
        $errorCode = $insertStmt->errno;
        error_log("SQL Error ($errorCode): " . $errorMsg);
        error_log("Query: " . $insertQuery);
        error_log("Parameters: rm_id=$rm_id, inst_id=$inst_id, req_date=$requestDateTime, day=$day, startTime=$startTime, endTime=$endTime, comment=" . substr($comment, 0, 50));
        error_log("MySQL Error: " . $conn->error);
        throw new Exception("SQL execution failed: " . $errorMsg . " (Error Code: $errorCode). MySQL Error: " . $conn->error);
    }
    
    $insertStmt->close();
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    header('Content-Type: application/json');
    
    $errorMessage = $e->getMessage();
    error_log("Error submitting room request: " . $errorMessage);
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("POST data: " . json_encode($_POST));
    http_response_code(500);
    
    // Show detailed error in development, generic in production
    $displayMessage = 'An error occurred while submitting the room request.';
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
        $displayMessage .= ' Error: ' . $errorMessage;
    }
    
    echo json_encode([
        'success' => false,
        'message' => $displayMessage,
        'debug' => (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? $errorMessage : null
    ]);
    ob_end_flush();
    exit;
} catch (Error $e) {
    // Catch fatal errors (PHP 7+)
    ob_clean();
    header('Content-Type: application/json');
    
    $errorMessage = $e->getMessage();
    error_log("Fatal error submitting room request: " . $errorMessage);
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'message' => 'A fatal error occurred while submitting the room request.',
        'debug' => (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? $errorMessage . ' in ' . $e->getFile() . ':' . $e->getLine() : null
    ]);
    ob_end_flush();
    exit;
}

// This should never be reached, but just in case
ob_clean();
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Unexpected script termination. Please try again.'
]);
ob_end_flush();
?>

