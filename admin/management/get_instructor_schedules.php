<?php
/**
 * Get all schedules assigned to a specific instructor
 * 
 * Endpoint: admin/management/get_instructor_schedules.php
 * Method: GET
 * Parameters:
 *   - instructor_id (required): The instructor ID (inst_id)
 * 
 * Access Rules:
 *   - Admin Support (role_id = 1): Can fetch schedules of any instructor
 *   - Regular Admins and Moderators: Can only fetch schedules of instructors within their own department
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['acc_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Check permissions - allow view_schedules, manage_schedules, or assign_schedules
if (!hasPermission('view_schedules') && !hasPermission('manage_schedules') && !hasPermission('assign_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to view schedules.']);
    exit;
}

// Get instructor_id from request
$instructor_id = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : 0;

if ($instructor_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid instructor ID. Please provide a valid instructor_id parameter.']);
    exit;
}

try {
    // Get user info for department filtering
    $userInfo = getUserInfo();
    $userRole = getUserRole();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    // Check if user is Admin Support (role_id = 1)
    $roleId = $userRole ? (int)$userRole['role_id'] : 0;
    $isAdminSupportFlag = ($roleId === 1 || $isAdminSupport) ? 1 : 0;
    
    // Verify instructor exists and get their department for access control
    $instCheckQuery = "
        SELECT i.inst_id, a.dept_id 
        FROM instructor i
        INNER JOIN account a ON a.acc_user = i.inst_user
        WHERE i.inst_id = ?
    ";
    $instStmt = $conn->prepare($instCheckQuery);
    $instStmt->bind_param('i', $instructor_id);
    $instStmt->execute();
    $instResult = $instStmt->get_result();
    
    if ($instResult->num_rows === 0) {
        $instStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Instructor not found.']);
        exit;
    }
    
    $instructorData = $instResult->fetch_assoc();
    $instDeptId = (int)$instructorData['dept_id'];
    $instStmt->close();
    
    // Apply department-based access control
    // Admin Support can access any instructor, others can only access instructors from their department
    if ($isAdminSupportFlag === 0 && $userDeptId > 0 && $instDeptId !== $userDeptId) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Access denied. You can only view schedules of instructors from your own department.'
        ]);
        exit;
    }
    
    // Build the main query to fetch schedules
    // Note: Subject table has program_id directly, so we join: subject → program
    // Curriculum is joined separately via: subject → curriculum
    // This matches the actual database schema (subject has both curr_id and program_id)
    $query = "
        SELECT 
            s.schd_id,
            s.inst_id,
            CONCAT(i.inst_fname, ' ', i.inst_lname) AS instructor_name,
            subj.subj_code,
            subj.subj_desc AS subj_name,
            subj.subj_unit AS units,
            subj.subj_lec AS lec_hours,
            subj.subj_lab AS lab_hours,
            c.curr_name,
            p.program_code,
            p.program_name,
            s.schd_day AS day,
            s.schd_type,
            TIME_FORMAT(s.schd_start, '%H:%i') AS start_time,
            TIME_FORMAT(s.schd_end, '%H:%i') AS end_time,
            s.is_overtime,
            r.rm_name AS room_name,
            r.rm_type AS room_type,
            d.dept_name
        FROM schedule s
        INNER JOIN instructor i ON s.inst_id = i.inst_id
        INNER JOIN account a ON a.acc_user = i.inst_user
        INNER JOIN subject subj ON subj.subj_id = s.subj_id
        INNER JOIN curriculum c ON c.curr_id = subj.curr_id
        INNER JOIN program p ON p.program_id = subj.program_id
        LEFT JOIN room r ON r.rm_id = s.rm_id
        LEFT JOIN department d ON d.dept_id = a.dept_id
        WHERE s.inst_id = ?
          AND s.schd_status = 'Active'
    ";
    
    // Add department filter conditionally (Admin Support bypasses this)
    if ($isAdminSupportFlag === 0 && $userDeptId > 0) {
        $query .= " AND a.dept_id = ?";
    }
    
    $query .= "
        ORDER BY 
            CASE s.schd_day
                WHEN 'Sun' THEN 1
                WHEN 'Mon' THEN 2
                WHEN 'Tue' THEN 3
                WHEN 'Wed' THEN 4
                WHEN 'Thu' THEN 5
                WHEN 'Fri' THEN 6
                WHEN 'Sat' THEN 7
            END,
            s.schd_start
    ";
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    // Bind parameters based on whether we need department filter
    if ($isAdminSupportFlag === 0 && $userDeptId > 0) {
        $stmt->bind_param('ii', $instructor_id, $userDeptId);
    } else {
        $stmt->bind_param('i', $instructor_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = [
            'schedule_id' => (int)$row['schd_id'],
            'instructor_id' => (int)$row['inst_id'],
            'instructor_name' => $row['instructor_name'],
            'subject' => [
                'code' => $row['subj_code'],
                'name' => $row['subj_name'],
                'units' => (int)$row['units'],
                'lec_hours' => (int)$row['lec_hours'],
                'lab_hours' => (int)$row['lab_hours']
            ],
            'curriculum' => [
                'name' => $row['curr_name']
            ],
            'program' => [
                'code' => $row['program_code'],
                'name' => $row['program_name']
            ],
            'schedule' => [
                'day' => $row['day'],
                'type' => $row['schd_type'] ?? 'Lec',
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'is_overtime' => $row['is_overtime'] ?? 'No'
            ],
            'room' => [
                'name' => $row['room_name'] ?? null,
                'type' => $row['room_type'] ?? null
            ],
            'department' => [
                'name' => $row['dept_name'] ?? null
            ]
        ];
    }
    
    $stmt->close();
    
    // Return success response with schedules
    echo json_encode([
        'success' => true,
        'instructor_id' => $instructor_id,
        'instructor_name' => !empty($schedules) ? $schedules[0]['instructor_name'] : null,
        'total_schedules' => count($schedules),
        'schedules' => $schedules
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

