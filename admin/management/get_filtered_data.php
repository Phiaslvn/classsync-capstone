<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

if (!hasPermission('manage_schedules')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

$type = $_GET['type'] ?? '';
$sy_id = $_GET['sy_id'] ?? '';
$program_id = $_GET['program_id'] ?? '';
$year_level = $_GET['year_level'] ?? '';
$term = $_GET['term'] ?? '';

$data = [];

try {
    switch ($type) {
        case 'terms':
            // Get terms for a school year (from class table)
            if (empty($sy_id)) {
                echo json_encode(['success' => false, 'message' => 'School year ID required']);
                exit;
            }
            
            $query = "SELECT DISTINCT class_term as term_value, 
                             CASE class_term 
                                 WHEN 1 THEN '1st Term'
                                 WHEN 2 THEN '2nd Term'
                                 WHEN 3 THEN 'Summer'
                                 ELSE CONCAT(class_term, ' Term')
                             END as term_name
                      FROM class 
                      WHERE sy_id = ? 
                      ORDER BY class_term";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $sy_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            break;
            
        case 'year_levels':
            // Get year levels for a program (from subject table)
            if (empty($program_id)) {
                echo json_encode(['success' => false, 'message' => 'Program ID required']);
                exit;
            }
            
            $query = "SELECT DISTINCT subj_lvl as year_level 
                      FROM subject 
                      WHERE program_id = ?";
            
            $params = [$program_id];
            $types = 'i';
            
            if (!$isAdminSupport && $userDeptId > 0) {
                $query .= " AND dept_id = ?";
                $params[] = $userDeptId;
                $types .= 'i';
            }
            
            $query .= " ORDER BY subj_lvl";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $yearLevels = $result->fetch_all(MYSQLI_ASSOC);
            
            // Format year levels
            $data = [];
            foreach ($yearLevels as $yl) {
                $lvl = (int)$yl['year_level'];
                $suffixes = ['', 'st', 'nd', 'rd', 'th', 'th'];
                $suffix = $suffixes[min($lvl, 5)] ?? 'th';
                $data[] = [
                    'year_level' => $lvl,
                    'year_level_name' => $lvl . $suffix . ' Year'
                ];
            }
            $stmt->close();
            break;
            
        case 'subjects':
            // Get subjects by program and year level
            // Filter out subjects already assigned to the instructor in the current school year and term
            if (empty($program_id)) {
                echo json_encode(['success' => false, 'message' => 'Program ID required']);
                exit;
            }
            
            // Get optional instructor_id and school year/term for filtering
            $inst_id = $_GET['inst_id'] ?? '';
            $sy_id_param = $_GET['sy_id'] ?? '';
            $term_param = $_GET['term'] ?? '';
            
            $query = "SELECT subj_id, subj_code, subj_desc, 
                             CONCAT(subj_code, ' - ', subj_desc) as subj_display,
                             subj_unit, subj_lec, subj_lab, subj_category
                      FROM subject 
                      WHERE program_id = ?";
            
            $params = [$program_id];
            $types = 'i';
            
            if (!empty($year_level)) {
                $query .= " AND subj_lvl = ?";
                $params[] = $year_level;
                $types .= 'i';
            }
            
            if (!$isAdminSupport && $userDeptId > 0) {
                $query .= " AND dept_id = ?";
                $params[] = $userDeptId;
                $types .= 'i';
            }
            
            // Filter out subjects already assigned to the instructor
            // Apply with SY and term if available, otherwise just by instructor
            if (!empty($inst_id)) {
                if (!empty($sy_id_param) && !empty($term_param)) {
                    // Full filter: specific instructor in specific SY and term
                    $query .= " AND subj_id NOT IN (
                        SELECT DISTINCT subj_id 
                        FROM schedule 
                        WHERE inst_id = ? 
                          AND sy_id = ? 
                          AND schd_term = ? 
                          AND schd_status = 'Active'
                    )";
                    $params[] = $inst_id;
                    $params[] = $sy_id_param;
                    $params[] = $term_param;
                    $types .= 'iii';
                } else {
                    // Partial filter: exclude any subject assigned to this instructor (in any SY/term)
                    // This provides immediate feedback when instructor is selected
                    $query .= " AND subj_id NOT IN (
                        SELECT DISTINCT subj_id 
                        FROM schedule 
                        WHERE inst_id = ? 
                          AND schd_status = 'Active'
                    )";
                    $params[] = $inst_id;
                    $types .= 'i';
                }
            }
            
            $query .= " ORDER BY subj_code";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            break;
            
        case 'sections':
            // Get sections by program, year level, school year, and term
            if (empty($program_id) || empty($sy_id) || empty($term)) {
                echo json_encode(['success' => false, 'message' => 'Program ID, School Year, and Term required']);
                exit;
            }
            
            $query = "SELECT DISTINCT sec.sec_id, sec.sec_name, sec.sec_num,
                             cls.class_lvl as year_level
                      FROM section sec
                      JOIN class cls ON sec.class_id = cls.class_id
                      JOIN curriculum curr ON cls.curr_id = curr.curr_id
                      WHERE cls.sy_id = ? 
                        AND cls.class_term = ?
                        AND (sec.program_id = ? OR sec.program_id IS NULL)";
            
            $params = [$sy_id, $term, $program_id];
            $types = 'iii';
            
            if (!empty($year_level)) {
                $query .= " AND cls.class_lvl = ?";
                $params[] = $year_level;
                $types .= 'i';
            }
            
            if (!$isAdminSupport && $userDeptId > 0) {
                $query .= " AND curr.dept_id = ?";
                $params[] = $userDeptId;
                $types .= 'i';
            }
            
            $query .= " ORDER BY sec.sec_name";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            break;
            
        case 'instructors':
            // Get instructors filtered by program's department
            // Only User and Moderator roles; Admin excluded (for assigning teaching load to users)
            // Include account_departments so instructors from other depts added to this program's dept are shown
            if (empty($program_id)) {
                echo json_encode(['success' => false, 'message' => 'Program ID required']);
                exit;
            }
            
            // First, get the program's department
            $programQuery = "SELECT dept_id FROM program WHERE program_id = ?";
            $programStmt = $conn->prepare($programQuery);
            $programStmt->bind_param('i', $program_id);
            $programStmt->execute();
            $programResult = $programStmt->get_result();
            
            if ($programResult->num_rows === 0) {
                $programStmt->close();
                echo json_encode(['success' => false, 'message' => 'Program not found']);
                exit;
            }
            
            $program = $programResult->fetch_assoc();
            $programDeptId = (int)$program['dept_id'];
            $programStmt->close();
            
            $checkAdTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
            $hasAccountDepartmentsTable = $checkAdTable && $checkAdTable->num_rows > 0;
            
            if ($hasAccountDepartmentsTable) {
                $query = "SELECT i.inst_id, CONCAT(a.fname, ' ', a.lname) as name 
                          FROM account a
                          LEFT JOIN instructor i ON i.inst_user = a.acc_user
                          JOIN user_roles ur ON a.acc_id = ur.acc_id
                          JOIN roles r ON ur.role_id = r.id
                          WHERE a.acc_status = 'Active' 
                            AND (a.dept_id = ? OR EXISTS (SELECT 1 FROM account_departments ad WHERE ad.acc_id = a.acc_id AND ad.dept_id = ?))
                            AND r.role_name IN ('User', 'Moderator')
                            AND i.inst_id IS NOT NULL
                          ORDER BY a.lname, a.fname";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ii', $programDeptId, $programDeptId);
            } else {
                $query = "SELECT i.inst_id, CONCAT(a.fname, ' ', a.lname) as name 
                          FROM account a
                          LEFT JOIN instructor i ON i.inst_user = a.acc_user
                          JOIN user_roles ur ON a.acc_id = ur.acc_id
                          JOIN roles r ON ur.role_id = r.id
                          WHERE a.acc_status = 'Active' 
                            AND a.dept_id = ?
                            AND r.role_name IN ('User', 'Moderator')
                            AND i.inst_id IS NOT NULL
                          ORDER BY a.lname, a.fname";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $programDeptId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            break;

        case 'classes':
            // Get classes by program and year level (for section creation form)
            if (empty($program_id) || empty($year_level)) {
                echo json_encode(['success' => false, 'message' => 'Program ID and Year Level required']);
                exit;
            }

            $query = "SELECT cls.class_id, cls.sy_id, cls.class_lvl, cls.class_term, curr.program_id,
                             CONCAT('SY ', sy.sy_code, ' - ', term.term_name, ' (Yr Level ', cls.class_lvl, ')') as class_display
                      FROM class cls
                      JOIN school_year sy ON cls.sy_id = sy.sy_id
                      JOIN schoolyear_term term ON cls.class_term = term.term_id
                      JOIN curriculum curr ON cls.curr_id = curr.curr_id
                      WHERE curr.program_id = ? AND cls.class_lvl = ?
                      ORDER BY sy.sy_code DESC, term.term_name DESC";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $program_id, $year_level);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred: ' . $e->getMessage()
    ]);
}
?>

