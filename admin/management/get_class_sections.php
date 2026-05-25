<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions - allow both manage and view permissions for rooms
// Also allow view_schedules since sections are used in scheduling
// Instructors (User role) can view their own schedules, so allow them to see sections
$userRole = getUserRole();
$isInstructor = ($userRole && strtolower($userRole['role_name']) === 'user');

if (!$isInstructor && !hasPermission('manage_rooms') && !hasPermission('view_rooms') && !hasPermission('view_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to view rooms.']);
    exit;
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// For instructors, get their instructor ID to filter sections
$instructorId = null;
if ($isInstructor && isset($_SESSION['acc_user'])) {
    $accUser = $_SESSION['acc_user'];
    // Instructor table links to account via inst_user = account.acc_user
    $instQuery = "SELECT inst_id FROM instructor WHERE inst_user = ? LIMIT 1";
    $instStmt = $conn->prepare($instQuery);
    if ($instStmt) {
        $instStmt->bind_param("s", $accUser);
        $instStmt->execute();
        $instResult = $instStmt->get_result();
        if ($instRow = $instResult->fetch_assoc()) {
            $instructorId = (int)$instRow['inst_id'];
        }
        $instStmt->close();
    }
}

// Get active school year, term, and program from filters or use defaults
$syFilter = $_GET['sy'] ?? '';
$termFilter = $_GET['term'] ?? '';
$programFilter = $_GET['program'] ?? ''; // Filter by program_id if provided

// Automatically filter by active school year and semester when filters are not explicitly provided
// This ensures sections are shown for the current active semester by default
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
            error_log("Auto-filtering class sections by active SY: {$syFilter}, Active Term: {$termFilter} (from semester: {$semester})");
        }
        $activeSyStmt->close();
    }
}

try {
    // Get classes with sections, grouped by year level
    // Check if program_id column exists in section table for optimized query
    $check_col = $conn->query("SHOW COLUMNS FROM section LIKE 'program_id'");
    $has_program_id = $check_col && $check_col->num_rows > 0;
    
    if ($has_program_id) {
        // Use direct program_id join (faster and simpler)
        $query = "
            SELECT DISTINCT
                cls.class_lvl as year_level,
                sec.sec_id,
                sec.sec_name,
                sec.sec_num,
                cls.class_id,
                cls.class_term,
                cls.class_secno,
                sy.sy_id,
                sy.sy_name,
                curr.curr_id,
                curr.curr_name,
                curr.dept_id,
                COALESCE(p.program_code, 'CLASS') as program_code,
                sec.program_id
            FROM class cls
            JOIN schoolyear sy ON cls.sy_id = sy.sy_id
            JOIN curriculum curr ON cls.curr_id = curr.curr_id
            JOIN section sec ON cls.class_id = sec.class_id
            LEFT JOIN program p ON sec.program_id = p.program_id
            WHERE 1=1
        ";
    } else {
        // Fallback: Use complex subquery (backward compatibility)
        $query = "
            SELECT DISTINCT
                cls.class_lvl as year_level,
                sec.sec_id,
                sec.sec_name,
                sec.sec_num,
                cls.class_id,
                cls.class_term,
                cls.class_secno,
                sy.sy_id,
                sy.sy_name,
                curr.curr_id,
                curr.curr_name,
                curr.dept_id,
                COALESCE(
                    (SELECT p.program_code 
                     FROM subject subj 
                     JOIN program p ON subj.program_id = p.program_id 
                     WHERE subj.curr_id = curr.curr_id 
                     AND p.program_status = 'Active' 
                     LIMIT 1),
                    (SELECT p.program_code 
                     FROM program p 
                     WHERE p.dept_id = curr.dept_id 
                     AND p.program_status = 'Active' 
                     ORDER BY p.program_code ASC 
                     LIMIT 1),
                    'CLASS'
                ) as program_code
            FROM class cls
            JOIN schoolyear sy ON cls.sy_id = sy.sy_id
            JOIN curriculum curr ON cls.curr_id = curr.curr_id
            JOIN section sec ON cls.class_id = sec.class_id
            WHERE 1=1
        ";
    }
    
    $params = [];
    $types = '';
    
    // Filter by department
    if (!$isAdminSupport && $userDeptId > 0) {
        $query .= " AND curr.dept_id = ?";
        $params[] = $userDeptId;
        $types .= 'i';
    }
    
    // Filter by school year if provided
    if (!empty($syFilter)) {
        $query .= " AND cls.sy_id = ?";
        $params[] = $syFilter;
        $types .= 'i';
    }
    
    // Filter by term if provided
    if (!empty($termFilter)) {
        $query .= " AND cls.class_term = ?";
        $params[] = $termFilter;
        $types .= 'i';
    }
    
    // Filter by program if provided
    if (!empty($programFilter)) {
        $programFilterInt = (int)$programFilter; // Ensure it's an integer
        
        if ($has_program_id) {
            // Direct filter using program_id column (faster and most accurate)
            $query .= " AND sec.program_id = ?";
            $params[] = $programFilterInt;
            $types .= 'i';
        } else {
            // Fallback: Filter through subjects -> program
            // Match sections to program by checking if the curriculum has subjects from that program
            // This is the most accurate way without program_id in section table
            $query .= " AND EXISTS (
                SELECT 1 FROM subject subj 
                WHERE subj.curr_id = curr.curr_id 
                AND subj.program_id = ? 
                AND subj.program_id IN (
                    SELECT program_id FROM program 
                    WHERE program_status = 'Active'
                )
            )";
            $params[] = $programFilterInt;
            $types .= 'i';
        }
    }
    
    // For instructors, only show sections where they have assigned schedules
    if ($isInstructor && $instructorId !== null) {
        $query .= " AND EXISTS (
            SELECT 1 FROM schedule sch 
            WHERE sch.sec_id = sec.sec_id 
            AND sch.inst_id = ? 
            AND sch.schd_status = 'Active'
        )";
        $params[] = $instructorId;
        $types .= 'i';
    }
    
    $query .= " ORDER BY cls.class_lvl ASC, sec.sec_num ASC, sec.sec_name ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Log for debugging
    error_log("get_class_sections.php - Found " . count($rows) . " sections. SY Filter: " . $syFilter . ", Term Filter: " . $termFilter . ", Program Filter: " . ($programFilter ?: 'None') . ", Dept ID: " . $userDeptId . ", Is Admin Support: " . ($isAdminSupport ? 'Yes' : 'No') . ", Has program_id column: " . ($has_program_id ? 'Yes' : 'No'));
    
    // Group by year level and create class buttons
    // Use a map to track unique sections by sec_name + year_level + sy_id + class_term to prevent duplicates
    $seenSections = [];
    $grouped = [];
    foreach ($rows as $row) {
        $yearLevel = $row['year_level'];
        if (!isset($grouped[$yearLevel])) {
            $grouped[$yearLevel] = [];
        }
        
        // Create a unique key to identify duplicate sections
        // Same section name in same school year, term, and year level should be considered duplicate
        $uniqueKey = $row['sec_name'] . '_' . $row['sy_id'] . '_' . $row['class_term'] . '_' . $yearLevel;
        
        // Skip if we've already seen this section
        if (isset($seenSections[$uniqueKey])) {
            error_log("Skipping duplicate section: {$row['sec_name']} (sec_id: {$row['sec_id']}, class_id: {$row['class_id']})");
            continue;
        }
        
        $seenSections[$uniqueKey] = true;
        
        // Create section label (e.g., "Class 1 - A", "Class 1 - B")
        // Use section number to determine letter (1=A, 2=B, 3=C, etc.)
        $secNum = (int)$row['sec_num'];
        $sectionLetter = '';
        
        if ($secNum <= 26) {
            // For sections 1-26, use single letters A-Z
            $sectionLetter = chr(64 + $secNum); // 65 is 'A' in ASCII
        } else {
            // For sections > 26, use multiple letters (AA, AB, etc.)
            $firstLetter = chr(64 + (int)(($secNum - 1) / 26));
            $secondLetter = chr(65 + (($secNum - 1) % 26));
            $sectionLetter = $firstLetter . $secondLetter;
        }
        
        // Get program code for the label (e.g., "BEED 1-A" instead of "Class 1-A")
        $programCode = $row['program_code'] ?? 'CLASS';
        
        $grouped[$yearLevel][] = [
            'sec_id' => $row['sec_id'],
            'sec_name' => $row['sec_name'],
            'sec_num' => $row['sec_num'],
            'class_id' => $row['class_id'],
            'year_level' => $yearLevel,
            'program_code' => $programCode,
            'label' => "{$programCode} {$yearLevel}-{$sectionLetter}", // e.g., "BEED 1-A", "BSIT 1-A"
            'display_name' => $row['sec_name'], // Actual section name like "BC-1"
            'dept_id' => $row['dept_id'] // Include department ID for reference
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $grouped,
        'total_sections' => count($rows),
        'filters_applied' => [
            'sy' => $syFilter,
            'term' => $termFilter,
            'program' => $programFilter,
            'dept_id' => $userDeptId,
            'is_admin_support' => $isAdminSupport,
            'has_program_id_column' => $has_program_id
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

