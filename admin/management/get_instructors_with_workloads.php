<?php
/**
 * Get All Instructors with Teaching Loads
 * Retrieves all instructors in the department who have teaching assignments
 * for the active school year and semester
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('view_users') && !hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

try {
    // Get active school year and semester
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'active_school_year_id'
        LIMIT 1
    ");
    $stmt->execute();
    $sy_result = $stmt->get_result();
    $sy_row = $sy_result->fetch_assoc();
    $active_sy_id = $sy_row ? intval($sy_row['setting_value']) : 0;
    
    // Get active semester
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'active_semester'
        LIMIT 1
    ");
    $stmt->execute();
    $semester_result = $stmt->get_result();
    $semester_row = $semester_result->fetch_assoc();
    $active_semester = $semester_row ? $semester_row['setting_value'] : null;
    
    // Convert semester to term ID: "1st Semester" -> 1, "2nd Semester" -> 2, "Mid-Year" -> 3
    $active_term = 0;
    if ($active_semester === '1st Semester') {
        $active_term = 1;
    } elseif ($active_semester === '2nd Semester') {
        $active_term = 2;
    } elseif ($active_semester === 'Mid-Year') {
        $active_term = 3;
    }
    
    if ($active_sy_id === 0 || $active_term === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No active school year or semester found. Please set an active school year and semester first.'
        ]);
        exit;
    }
    
    // Build query to get instructors with teaching loads
    // Join account, instructor, and schedule tables
    $sql = "
        SELECT DISTINCT
            i.inst_id,
            a.acc_id,
            CONCAT(a.fname, ' ', COALESCE(a.minitial, ''), ' ', a.lname, 
                   CASE WHEN a.suffix IS NOT NULL AND a.suffix != '' THEN CONCAT(' ', a.suffix) ELSE '' END) as full_name,
            a.fname,
            a.lname,
            a.minitial,
            a.suffix,
            i.inst_status,
            i.rank,
            i.designation,
            i.administration_hours,
            i.instruction_hours,
            i.research_hours,
            i.extension_hours,
            i.instructional_functions_hours,
            i.consultation_hours,
            d.dept_id,
            d.dept_name,
            p.program_id,
            p.program_name,
            p.program_code
        FROM instructor i
        INNER JOIN account a ON i.inst_user = a.acc_user
        LEFT JOIN department d ON COALESCE(i.dept_id, a.dept_id) = d.dept_id
        LEFT JOIN program p ON i.program_id = p.program_id
        INNER JOIN schedule s ON i.inst_id = s.inst_id
        WHERE s.sy_id = ?
          AND s.schd_term = ?
          AND s.schd_status = 'Active'
          AND a.acc_status = 'Active'
    ";
    
    $params = [$active_sy_id, $active_term];
    $types = 'ii';
    
    // Filter by department if not Admin Support
    if (!$isAdminSupport && $userDeptId > 0) {
        $sql .= " AND (COALESCE(i.dept_id, a.dept_id) = ? OR 
                       (i.program_id IS NOT NULL AND EXISTS (
                           SELECT 1 FROM program p_check 
                           WHERE p_check.program_id = i.program_id 
                           AND p_check.dept_id = ?
                       )))";
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $types .= 'ii';
    }
    
    $sql .= " ORDER BY a.lname, a.fname";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $instructors = [];
    while ($row = $result->fetch_assoc()) {
        $instructors[] = [
            'inst_id' => (int)$row['inst_id'],
            'acc_id' => (int)$row['acc_id'],
            'full_name' => $row['full_name'],
            'fname' => $row['fname'],
            'lname' => $row['lname'],
            'minitial' => $row['minitial'],
            'suffix' => $row['suffix'],
            'inst_status' => $row['inst_status'],
            'rank' => $row['rank'],
            'designation' => $row['designation'],
            'administration_hours' => (int)($row['administration_hours'] ?? 0),
            'instruction_hours' => (int)($row['instruction_hours'] ?? 0),
            'research_hours' => (int)($row['research_hours'] ?? 0),
            'extension_hours' => (int)($row['extension_hours'] ?? 0),
            'instructional_functions_hours' => (int)($row['instructional_functions_hours'] ?? 0),
            'consultation_hours' => (int)($row['consultation_hours'] ?? 0),
            'dept_id' => (int)($row['dept_id'] ?? 0),
            'dept_name' => $row['dept_name'],
            'program_id' => $row['program_id'] ? (int)$row['program_id'] : null,
            'program_name' => $row['program_name'],
            'program_code' => $row['program_code']
        ];
    }
    
    $stmt->close();
    
    // Get school year details
    $stmt = $conn->prepare("SELECT sy_id, sy_name, sy_year FROM schoolyear WHERE sy_id = ?");
    $stmt->bind_param('i', $active_sy_id);
    $stmt->execute();
    $sy_result = $stmt->get_result();
    $sy_data = $sy_result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'instructors' => $instructors,
        'school_year' => [
            'sy_id' => $active_sy_id,
            'sy_name' => $sy_data['sy_name'] ?? '',
            'sy_year' => $sy_data['sy_year'] ?? '',
            'semester' => $active_semester,
            'term' => $active_term
        ],
        'total_count' => count($instructors)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

