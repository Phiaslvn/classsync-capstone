<?php
/**
 * Get Source Schedules for Copy
 * Returns schedules from a source school year/term for selection in copy modal
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('manage_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$source_sy_id = isset($_GET['source_sy_id']) ? (int)$_GET['source_sy_id'] : 0;
$source_term = isset($_GET['source_term']) ? (int)$_GET['source_term'] : 0;
$instructor_id = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : 0;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0; // Optional program filter (for program substitution)

if ($source_sy_id <= 0 || $source_term <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select source school year and term.']);
    exit;
}

try {
    // Get user info for department filtering
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    // Build query to fetch source schedules with all necessary details
    // Includes all fields needed for copying: program_id, year_level, dept_id, sec_id, inst_id, rm_id, schd_type, schd_term, schd_day, schd_start, schd_end, schd_status, is_overtime
    $sql = "
        SELECT 
            s.schd_id,
            s.sy_id,
            s.subj_id,
            s.sec_id,
            s.inst_id,
            s.rm_id,
            s.schd_type,
            s.schd_term,
            s.schd_day,
            s.schd_start,
            s.schd_end,
            s.schd_min,
            s.schd_status,
            s.is_overtime,
            COALESCE(s.program_id, p.program_id) as program_id,
            COALESCE(s.year_level, cls.class_lvl) as year_level,
            COALESCE(s.dept_id, curr.dept_id, p.dept_id) as dept_id,
            TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time_formatted,
            TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time_formatted,
            subj.subj_code,
            subj.subj_desc,
            subj.subj_lec,
            subj.subj_lab,
            subj.subj_unit,
            sec.sec_name,
            sec.sec_num,
            CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
            r.rm_name,
            b.bd_desc,
            p.program_name,
            p.program_code,
            d.dept_name,
            sy.sy_name
        FROM schedule s
        JOIN subject subj ON s.subj_id = subj.subj_id
        JOIN curriculum curr ON subj.curr_id = curr.curr_id
        JOIN section sec ON s.sec_id = sec.sec_id
        JOIN class cls ON sec.class_id = cls.class_id
        JOIN program p ON subj.program_id = p.program_id
        JOIN instructor i ON s.inst_id = i.inst_id
        JOIN room r ON s.rm_id = r.rm_id
        JOIN building b ON r.bd_id = b.bd_id
        JOIN schoolyear sy ON s.sy_id = sy.sy_id
        LEFT JOIN department d ON COALESCE(s.dept_id, curr.dept_id, p.dept_id) = d.dept_id
        WHERE s.sy_id = ? 
          AND s.schd_term = ? 
          AND s.schd_status = 'Active'
    ";
    
    $params = [$source_sy_id, $source_term];
    $types = "ii";
    
    // Optional instructor filter
    if ($instructor_id > 0) {
        $sql .= " AND s.inst_id = ?";
        $params[] = $instructor_id;
        $types .= "i";
    }
    
    // Optional program filter (for program substitution feature)
    if ($program_id > 0) {
        $sql .= " AND (COALESCE(s.program_id, p.program_id) = ?)";
        $params[] = $program_id;
        $types .= "i";
    }
    
    // Department filtering (unless Admin Support)
    if (!$isAdminSupport && $userDeptId > 0) {
        $sql .= " AND (s.dept_id = ? OR (s.dept_id IS NULL AND (curr.dept_id = ? OR i.dept_id = ?)))";
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $types .= "iii";
    }
    
    $sql .= " ORDER BY i.inst_fname, i.inst_lname, s.schd_day, s.schd_start";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = [
            // Schedule IDs and keys
            'schd_id' => (int)$row['schd_id'],
            'sy_id' => (int)$row['sy_id'],
            'subj_id' => (int)$row['subj_id'],
            'sec_id' => (int)$row['sec_id'],
            'inst_id' => (int)$row['inst_id'],
            'rm_id' => (int)$row['rm_id'],
            
            // Schedule details
            'schd_type' => $row['schd_type'],
            'schd_term' => (int)$row['schd_term'],
            'schd_day' => $row['schd_day'],
            'schd_start' => $row['schd_start'],
            'schd_end' => $row['schd_end'],
            'schd_min' => (int)$row['schd_min'],
            'schd_status' => $row['schd_status'],
            'is_overtime' => $row['is_overtime'],
            
            // Additional fields for copy
            'program_id' => $row['program_id'] ? (int)$row['program_id'] : null,
            'year_level' => $row['year_level'] ? (int)$row['year_level'] : null,
            'dept_id' => $row['dept_id'] ? (int)$row['dept_id'] : null,
            
            // Display fields
            'subject_code' => $row['subj_code'],
            'subject_desc' => $row['subj_desc'],
            'subject_lec' => (int)$row['subj_lec'],
            'subject_lab' => (int)$row['subj_lab'],
            'subject_unit' => (int)$row['subj_unit'],
            'section_name' => $row['sec_name'],
            'section_num' => (int)$row['sec_num'],
            'instructor_name' => $row['instructor_name'],
            'instructor_id' => (int)$row['inst_id'],
            'room_name' => $row['rm_name'],
            'building_name' => $row['bd_desc'],
            'program_name' => $row['program_name'],
            'program_code' => $row['program_code'],
            'dept_name' => $row['dept_name'],
            'school_year' => $row['sy_name'],
            
            // Formatted display fields
            'day' => $row['schd_day'],
            'type' => $row['schd_type'],
            'start_time' => $row['start_time_formatted'],
            'end_time' => $row['end_time_formatted'],
            'minutes' => (int)$row['schd_min']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'schedules' => $schedules,
        'total' => count($schedules)
    ]);
    
} catch (Exception $e) {
    error_log("Get Source Schedules Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>

