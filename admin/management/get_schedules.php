<?php
// Start output buffering to prevent any premature output
ob_start();

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once '../../config/api_headers.php'; // Include cache prevention headers

// Clean any output that might have been generated
ob_clean();

// Get user info first to check if they're an instructor
$userInfo = getUserInfo();
$userRole = getUserRole();
$isInstructor = ($userRole && strtolower($userRole['role_name']) === 'user');

// Allow both view and manage permissions (moderators can view, admins can manage)
// Instructors can always view their own schedules (they're filtered by instructor ID below)
if (!$isInstructor && !hasPermission('view_schedules') && !hasPermission('manage_schedules') && !hasPermission('assign_schedules')) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to view schedules.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Get department info
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// For instructors, get their instructor ID and all their departments
$instructorId = null;
$instructorDeptIds = []; // Array to store all department IDs for instructor

if ($isInstructor && isset($_SESSION['acc_user'])) {
    $accUser = $_SESSION['acc_user'];
    // Instructor table links to account via inst_user = account.acc_user
    $instQuery = "SELECT i.inst_id, a.acc_id FROM instructor i 
                  INNER JOIN account a ON a.acc_user = i.inst_user 
                  WHERE i.inst_user = ? LIMIT 1";
    $instStmt = $conn->prepare($instQuery);
    if ($instStmt) {
        $instStmt->bind_param("s", $accUser);
        $instStmt->execute();
        $instResult = $instStmt->get_result();
        if ($instRow = $instResult->fetch_assoc()) {
            $instructorId = (int)$instRow['inst_id'];
            $accId = (int)$instRow['acc_id'];
            
            // Get all departments for this instructor from account_departments
            $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
            $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
            
            if ($hasAccountDepartmentsTable) {
                $deptStmt = $conn->prepare("SELECT dept_id FROM account_departments WHERE acc_id = ?");
                $deptStmt->bind_param("i", $accId);
                $deptStmt->execute();
                $deptResult = $deptStmt->get_result();
                while ($deptRow = $deptResult->fetch_assoc()) {
                    $instructorDeptIds[] = (int)$deptRow['dept_id'];
                }
                $deptStmt->close();
            }
            
            // If no departments from account_departments, use account.dept_id as fallback
            if (empty($instructorDeptIds) && $userDeptId > 0) {
                $instructorDeptIds[] = $userDeptId;
            }
        }
        $instStmt->close();
    }
}

// Filter values
$syFilter = $_GET['sy'] ?? '';
$termFilter = $_GET['term'] ?? '';
$instructorFilter = $_GET['instructor'] ?? '';
$roomFilter = $_GET['room'] ?? '';
$dayFilter = $_GET['day'] ?? '';
$programFilter = $_GET['program'] ?? '';
$yearLevelFilter = $_GET['year_level'] ?? '';
$sectionFilter = $_GET['section'] ?? '';
$deptFilter = $_GET['department'] ?? $_GET['dept_filter'] ?? ''; // For instructors: filter to one department

// Automatically filter by active school year and semester when filters are not explicitly provided
// This ensures schedules are shown for the current active semester by default
// If user explicitly sets filters, those take priority
$shouldUseActiveSySemester = empty($syFilter) && empty($termFilter);

if ($shouldUseActiveSySemester) {
    // Get active school year and semester from active_school_year_semester table
    $activeSyStmt = $conn->prepare("
        SELECT asys.sy_id, asys.semester
        FROM active_school_year_semester asys
        WHERE asys.is_active = 1
        ORDER BY asys.created_at DESC
        LIMIT 1
    ");
    
    if ($activeSyStmt) {
        $activeSyStmt->execute();
        $activeSyResult = $activeSyStmt->get_result();
        
        if ($activeSyRow = $activeSyResult->fetch_assoc()) {
            // Set filters with active school year and semester
            $syFilter = (int)$activeSyRow['sy_id'];
            
            // Convert semester enum to term ID: '1st' -> 1, '2nd' -> 2, 'Summer' -> 3
            $semester = $activeSyRow['semester'];
            if ($semester === '1st') {
                $termFilter = 1;
            } elseif ($semester === '2nd') {
                $termFilter = 2;
            } elseif ($semester === 'Summer') {
                $termFilter = 3;
            }
            
            // Log for debugging
            error_log("Auto-filtering schedules by active SY: {$syFilter}, Active Term: {$termFilter} (from semester: {$semester})");
        }
        $activeSyStmt->close();
    }
}

// For instructors, always filter by their instructor ID (they can only see their own schedules)
// Note: This is separate from the active SY/semester filtering above

// Base query - join with curriculum, class, and program to support filtering
$query = "
    SELECT 
        s.schd_id,
        s.schd_start,
        s.inst_id,
        s.schd_end,
        s.schd_type,
        subj.subj_code,
        s.is_overtime,
        subj.subj_desc,
        subj.subj_lec,
        subj.subj_lab,
        subj.subj_unit,
        sec.sec_id,
        sec.sec_name,
        sec.sec_num,
        CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
        r.rm_name,
        r.rm_capacity,
        b.bd_desc,
        s.schd_day,
        TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time,
        TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time,
        COALESCE(s.program_id, p.program_id) as program_id,
        p.program_name,
        p.program_code,
        COALESCE(s.year_level, cls.class_lvl) as year_level,
        COALESCE(s.dept_id, curr.dept_id, p.dept_id) as dept_id,
        COALESCE(d.dept_name, d_curr.dept_name, d_prog.dept_name) as dept_name,
        s.schd_min,
        i.inst_status,
        i.instruction_hours,
        -- Policy hours now come only from Rank; Designation no longer affects hours
        COALESCE(wp_rank.total_hours, 40) as policy_total_hours,
        -- Use instruction_hours for Part-Time/Contractual, otherwise use policy total_hours
        CASE 
            WHEN i.inst_status IN ('Part-Time', 'Contractual') AND i.instruction_hours IS NOT NULL THEN i.instruction_hours
            ELSE COALESCE(i.instruction_hours, wp_rank.total_hours, 40)
        END as total_hours
    FROM schedule s
    JOIN subject subj ON s.subj_id = subj.subj_id
    JOIN curriculum curr ON subj.curr_id = curr.curr_id
    JOIN section sec ON s.sec_id = sec.sec_id
    JOIN class cls ON sec.class_id = cls.class_id
    JOIN program p ON subj.program_id = p.program_id
    JOIN instructor i ON s.inst_id = i.inst_id
    JOIN room r ON s.rm_id = r.rm_id
    JOIN building b ON r.bd_id = b.bd_id
    LEFT JOIN department d ON s.dept_id = d.dept_id
    LEFT JOIN department d_curr ON curr.dept_id = d_curr.dept_id
    LEFT JOIN department d_prog ON p.dept_id = d_prog.dept_id
    LEFT JOIN workload_policy wp_rank ON wp_rank.name = i.rank AND wp_rank.policy_type = 'Rank'
    LEFT JOIN workload_policy wp_designation ON wp_designation.name = i.designation AND wp_designation.policy_type = 'Designation'
";

// Filtering
$whereClauses = ["s.schd_status = 'Active'"];
$params = [];
$types = '';

// Department filtering - use schedule.dept_id directly (with fallback to curriculum/instructor for backward compatibility)
// Admin Support can see all, others see only their department(s)
if (!$isAdminSupport) {
    // For instructors, use all their departments from account_departments (or filter to one if department param set)
    if ($isInstructor && !empty($instructorDeptIds)) {
        $deptFilterId = !empty($deptFilter) ? (int)$deptFilter : 0;
        if ($deptFilterId > 0 && in_array($deptFilterId, $instructorDeptIds, true)) {
            // Instructor chose a specific department: filter to that department only
            $whereClauses[] = "(s.dept_id = ? OR (s.dept_id IS NULL AND (curr.dept_id = ? OR i.dept_id = ?)))";
            $params[] = $deptFilterId;
            $params[] = $deptFilterId;
            $params[] = $deptFilterId;
            $types .= 'iii';
        } else {
            // All departments (or invalid dept_filter): use full list
            $placeholders = implode(',', array_fill(0, count($instructorDeptIds), '?'));
            $whereClauses[] = "(s.dept_id IN ($placeholders) OR (s.dept_id IS NULL AND (curr.dept_id IN ($placeholders) OR i.dept_id IN ($placeholders))))";
            foreach ($instructorDeptIds as $deptId) {
                $params[] = $deptId;
                $types .= 'i';
            }
            foreach ($instructorDeptIds as $deptId) {
                $params[] = $deptId;
                $types .= 'i';
            }
            foreach ($instructorDeptIds as $deptId) {
                $params[] = $deptId;
                $types .= 'i';
            }
        }
    } elseif ($userDeptId > 0) {
        // For admins/moderators, use their single department
        $whereClauses[] = "(s.dept_id = ? OR (s.dept_id IS NULL AND (curr.dept_id = ? OR i.dept_id = ?)))";
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $types .= 'iii';
    }
}

if (!empty($syFilter)) { $whereClauses[] = "s.sy_id = ?"; $params[] = $syFilter; $types .= 'i'; }
if (!empty($termFilter)) { $whereClauses[] = "s.schd_term = ?"; $params[] = $termFilter; $types .= 'i'; }
if (!empty($programFilter)) { 
    $whereClauses[] = "(s.program_id = ? OR (s.program_id IS NULL AND p.program_id = ?))"; 
    $params[] = $programFilter; 
    $params[] = $programFilter; 
    $types .= 'ii'; 
}
if (!empty($yearLevelFilter)) { 
    $whereClauses[] = "(s.year_level = ? OR (s.year_level IS NULL AND cls.class_lvl = ?))"; 
    $params[] = $yearLevelFilter; 
    $params[] = $yearLevelFilter; 
    $types .= 'ii'; 
}
if (!empty($sectionFilter)) { $whereClauses[] = "s.sec_id = ?"; $params[] = $sectionFilter; $types .= 'i'; }

// For instructors, automatically filter by their instructor ID (they can only see their own schedules)
if ($isInstructor && $instructorId !== null) {
    $whereClauses[] = "s.inst_id = ?";
    $params[] = $instructorId;
    $types .= 'i';
} elseif (!empty($instructorFilter)) {
    // Only allow instructor filter for non-instructors (admins/moderators)
    $whereClauses[] = "s.inst_id = ?";
    $params[] = $instructorFilter;
    $types .= 'i';
}

if (!empty($roomFilter)) { $whereClauses[] = "s.rm_id = ?"; $params[] = $roomFilter; $types .= 'i'; }
if (!empty($dayFilter)) { $whereClauses[] = "s.schd_day = ?"; $params[] = $dayFilter; $types .= 's'; }

if (count($whereClauses) > 0) {
    $query .= " WHERE " . implode(' AND ', $whereClauses);
}

try {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // For instructors, attach list of their departments (for department filter dropdown)
    $instructor_departments = [];
    if ($isInstructor && !empty($instructorDeptIds)) {
        $safeIds = array_map('intval', $instructorDeptIds);
        $ph = implode(',', $safeIds);
        $deptNamesStmt = $conn->prepare("SELECT dept_id, dept_name FROM department WHERE dept_id IN ($ph) ORDER BY dept_name");
        if ($deptNamesStmt) {
            $deptNamesStmt->execute();
            $dr = $deptNamesStmt->get_result();
            while ($row = $dr->fetch_assoc()) {
                $instructor_departments[] = ['dept_id' => (int)$row['dept_id'], 'dept_name' => $row['dept_name']];
            }
            $deptNamesStmt->close();
        }
    }
    
    $response = ["success" => true, "data" => $data];
    if (!empty($instructor_departments)) {
        $response['instructor_departments'] = $instructor_departments;
    }
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>