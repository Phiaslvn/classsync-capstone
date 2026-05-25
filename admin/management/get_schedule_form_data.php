<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once '../../config/api_headers.php'; // Include cache prevention headers

// Get user role to check if instructor
$userRole = getUserRole();
$isInstructor = ($userRole && strtolower($userRole['role_name']) === 'user');

// Allow both view and manage permissions (moderators can view, admins can manage)
// Instructors may not have explicit permissions but can view their schedules
// Form data is optional for instructors (they mainly view, not create/edit)
if (!$isInstructor && !hasPermission('view_schedules') && !hasPermission('manage_schedules') && !hasPermission('assign_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. You do not have permission to view schedules.']);
    exit;
}

try {
    // Get user info for department filtering
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    $data = [];

    // School Years - no department filtering needed (global)
    $result = $conn->query("SELECT sy_id, sy_name, sy_year FROM schoolyear ORDER BY sy_name DESC");
    $data['school_years'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get current active school year ID - check both methods for compatibility
    $current_sy_id = null;
    $current_term = null;
    
    // Method 1: Check settings table (preferred method)
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_school_year_id' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $sy_result = $stmt->get_result();
        if ($sy_row = $sy_result->fetch_assoc()) {
            $current_sy_id = intval($sy_row['setting_value']);
        }
        $stmt->close();
    }
    
    // Get active semester from settings and convert to term ID
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_semester' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $semester_result = $stmt->get_result();
        if ($semester_row = $semester_result->fetch_assoc()) {
            $semester = $semester_row['setting_value'];
            // Convert semester string to term ID: "1st Semester" -> 1, "2nd Semester" -> 2, "Mid-Year" -> 3
            if ($semester === '1st Semester') {
                $current_term = 1;
            } elseif ($semester === '2nd Semester') {
                $current_term = 2;
            } elseif ($semester === 'Mid-Year') {
                $current_term = 3;
            }
        }
        $stmt->close();
    }
    
    // Method 2: Fallback to active_school_year_semester table if settings table has no data
    if (!$current_sy_id) {
        $stmt = $conn->prepare("
            SELECT sy_id, semester
            FROM active_school_year_semester 
            WHERE is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->execute();
            $sy_result = $stmt->get_result();
            if ($sy_row = $sy_result->fetch_assoc()) {
                $current_sy_id = intval($sy_row['sy_id']);
                // Convert semester from active_school_year_semester table (format: "1st", "2nd", "Summer")
                $semester_short = $sy_row['semester'] ?? '';
                if ($semester_short === '1st') {
                    $current_term = 1;
                } elseif ($semester_short === '2nd') {
                    $current_term = 2;
                } elseif ($semester_short === 'Summer') {
                    $current_term = 3;
                }
            }
            $stmt->close();
        }
    }

    // Instructors - fetch from account table (includes admins who can be instructors)
    // Join with instructor table to get inst_id, and filter by roles that can be instructors
    // Include instructors from account_departments table (multi-department support)
    
    // Check if account_departments table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
    $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
    
    // Only User and Moderator roles; Admin is excluded (instructor dropdown is for assigning teaching load to users)
    $instructorQuery = "SELECT DISTINCT i.inst_id, CONCAT(a.fname, ' ', a.lname) as name 
                       FROM account a
                       LEFT JOIN instructor i ON i.inst_user = a.acc_user
                       JOIN user_roles ur ON a.acc_id = ur.acc_id
                       JOIN roles r ON ur.role_id = r.id
                       WHERE a.acc_status = 'Active' 
                         AND r.role_name IN ('User', 'Moderator')
                         AND i.inst_id IS NOT NULL";
    $instructorParams = [];
    $instructorTypes = '';
    
    if (!$isAdminSupport && $userDeptId > 0) {
        // Include instructors from:
        // 1. account.dept_id = userDeptId (primary department)
        // 2. account_departments table (if exists) where dept_id = userDeptId
        if ($hasAccountDepartmentsTable) {
            $instructorQuery .= " AND (
                a.dept_id = ? 
                OR EXISTS (
                    SELECT 1 FROM account_departments ad 
                    WHERE ad.acc_id = a.acc_id 
                    AND ad.dept_id = ?
                )
            )";
            $instructorParams[] = $userDeptId;
            $instructorParams[] = $userDeptId;
            $instructorTypes = 'ii';
        } else {
            // Fallback to old method if account_departments table doesn't exist
            $instructorQuery .= " AND a.dept_id = ?";
            $instructorParams[] = $userDeptId;
            $instructorTypes = 'i';
        }
    }
    
    $instructorQuery .= " ORDER BY a.lname, a.fname";
    
    if (!empty($instructorParams)) {
        $stmt = $conn->prepare($instructorQuery);
        $stmt->bind_param($instructorTypes, ...$instructorParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $data['instructors'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($instructorQuery);
        $data['instructors'] = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Rooms - filter by department and access permissions
    // Show rooms that:
    // 1. Belong to the user's department (r.dept_id = user_dept_id)
    // 2. OR have an APPROVED room request (req_status = 'Accepted') from the user's department
    // Admin Support sees all rooms
    $roomQuery = "SELECT DISTINCT r.rm_id, r.rm_name, 
                         CONCAT(b.bd_desc, ' - ', r.rm_name) as rm_name_with_building,
                         r.dept_id, d.dept_name,
                         CASE 
                             WHEN r.dept_id = ? THEN 'Own'
                             ELSE 'Shared'
                         END as access_type
                  FROM room r 
                  JOIN building b ON r.bd_id = b.bd_id
                  LEFT JOIN department d ON r.dept_id = d.dept_id
                  WHERE r.rm_status = 'Used'";
    
    $roomParams = [];
    $roomTypes = '';
    
    if ($isAdminSupport) {
        // Admin Support sees all rooms
        $roomQuery .= " ORDER BY b.bd_desc, r.rm_name";
        $result = $conn->query($roomQuery);
        $data['rooms'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        // Regular admins see:
        // 1. Their own department's rooms
        // 2. Rooms from other departments that have APPROVED room requests (req_status = 'Accepted')
        if ($userDeptId > 0) {
            $roomQuery .= " AND (
                r.dept_id = ? 
                OR EXISTS (
                    SELECT 1 FROM room_request rr
                    JOIN instructor i ON rr.inst_id = i.inst_id
                    JOIN account a ON i.inst_user = a.acc_user
                    WHERE rr.rm_id = r.rm_id 
                    AND a.dept_id = ?
                    AND rr.req_status = 'Accepted'
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
                                    WHEN 2 THEN 7
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
                    )
                )
            )";
            $roomParams = [$userDeptId, $userDeptId, $userDeptId];
            $roomTypes = 'iii';
        } else {
            // No department assigned, show no rooms
            $data['rooms'] = [];
        }
        
        $roomQuery .= " ORDER BY b.bd_desc, r.rm_name";
        
        if (!empty($roomParams)) {
            $stmt = $conn->prepare($roomQuery);
            if ($stmt) {
                $stmt->bind_param($roomTypes, ...$roomParams);
                $stmt->execute();
                $result = $stmt->get_result();
    $data['rooms'] = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } else {
                error_log("Failed to prepare room query: " . $conn->error);
                $data['rooms'] = [];
            }
        }
    }

    // Subjects - filter by user's department unless Admin Support
    $subjectQuery = "SELECT subj_id, CONCAT(subj_code, ' - ', subj_desc) as subj_desc FROM subject";
    $subjectParams = [];
    $subjectTypes = '';
    
    if (!$isAdminSupport && $userDeptId > 0) {
        $subjectQuery .= " WHERE dept_id = ?";
        $subjectParams[] = $userDeptId;
        $subjectTypes = 'i';
    }
    
    $subjectQuery .= " ORDER BY subj_code";
    
    if (!empty($subjectParams)) {
        $stmt = $conn->prepare($subjectQuery);
        $stmt->bind_param($subjectTypes, ...$subjectParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $data['subjects'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($subjectQuery);
        $data['subjects'] = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Sections - filter by department through class -> curriculum -> dept_id
    // Also filter by current school year and term to avoid duplicates
    $sectionQuery = "
        SELECT DISTINCT sec.sec_id, sec.sec_name 
        FROM section sec
        JOIN class cls ON sec.class_id = cls.class_id
        JOIN curriculum curr ON cls.curr_id = curr.curr_id
        WHERE 1=1
    ";
    $sectionParams = [];
    $sectionTypes = '';
    
    // Filter by current school year if available
    if ($current_sy_id) {
        $sectionQuery .= " AND cls.sy_id = ?";
        $sectionParams[] = $current_sy_id;
        $sectionTypes .= 'i';
    }
    
    // Filter by current term if available
    if ($current_term !== null) {
        $sectionQuery .= " AND cls.class_term = ?";
        $sectionParams[] = $current_term;
        $sectionTypes .= 'i';
    }
    
    // Filter by department
    if (!$isAdminSupport && $userDeptId > 0) {
        $sectionQuery .= " AND curr.dept_id = ?";
        $sectionParams[] = $userDeptId;
        $sectionTypes .= 'i';
    }
    
    $sectionQuery .= " ORDER BY sec.sec_name";
    
    if (!empty($sectionParams)) {
        $stmt = $conn->prepare($sectionQuery);
        $stmt->bind_param($sectionTypes, ...$sectionParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $data['sections'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($sectionQuery);
        $data['sections'] = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Programs - filter by user's department if not Admin Support
    // Format similar to subjects: "Program Code - Program Name"
    // IMPORTANT: Regular admins should ONLY see programs from their department
    $programQuery = "SELECT p.program_id, p.program_code, p.program_name, 
                            CONCAT(p.program_code, ' - ', p.program_name) as program_display,
                            d.dept_name, p.dept_id 
                     FROM program p 
                     LEFT JOIN department d ON p.dept_id = d.dept_id 
                     WHERE p.program_status = 'Active'";
    $programParams = [];
    $programTypes = '';
    
    // For regular admins (not Admin Support), filter strictly by their department
    if (!$isAdminSupport && $userDeptId > 0) {
        $programQuery .= " AND p.dept_id = ?";
        $programParams[] = $userDeptId;
        $programTypes = 'i';
    }
    // Admin Support sees all programs (no additional filter)
    
    $programQuery .= " ORDER BY p.program_code, p.program_name";
    
    if (!empty($programParams)) {
        $stmt = $conn->prepare($programQuery);
        if ($stmt) {
            $stmt->bind_param($programTypes, ...$programParams);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['programs'] = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            error_log("Failed to prepare program query: " . $conn->error);
            $data['programs'] = [];
        }
    } else {
        $result = $conn->query($programQuery);
        if ($result) {
            $data['programs'] = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Failed to execute program query: " . $conn->error);
            $data['programs'] = [];
        }
    }

    // Get default program (first program from user's department) for auto-selection
    $defaultProgramId = null;
    if (!$isAdminSupport && $userDeptId > 0 && !empty($data['programs'])) {
        // Get the first program from the user's department
        $defaultProgramId = $data['programs'][0]['program_id'];
    } elseif ($isAdminSupport && !empty($data['programs'])) {
        // For Admin Support, get the first program (or could be null)
        $defaultProgramId = $data['programs'][0]['program_id'];
    }

    // Debug logging (can be removed in production)
    error_log("Schedule Form Data - User Dept ID: " . $userDeptId . ", Is Admin Support: " . ($isAdminSupport ? 'Yes' : 'No') . ", Programs Count: " . count($data['programs']));
    
    // Log program details for debugging
    if (!empty($data['programs'])) {
        foreach ($data['programs'] as $prog) {
            error_log("Program: ID=" . $prog['program_id'] . ", Code=" . $prog['program_code'] . ", Name=" . $prog['program_name'] . ", Dept ID=" . ($prog['dept_id'] ?? 'NULL'));
        }
    }
    
    // Debug logging (can be removed in production)
    error_log("Schedule Form Data - Current SY ID: " . ($current_sy_id ?? 'NULL'));
    
    echo json_encode([
        'success' => true,
        'school_years' => $data['school_years'],
        'instructors' => $data['instructors'],
        'rooms' => $data['rooms'],
        'subjects' => $data['subjects'],
        'sections' => $data['sections'],
        'programs' => $data['programs'],
        'default_program_id' => $defaultProgramId,
        'current_sy_id' => $current_sy_id,
        'current_term' => $current_term,
        'user_dept_id' => $userDeptId,
        'user_dept_name' => $userInfo ? ($userInfo['dept_name'] ?? 'Unknown') : 'Unknown',
        'is_admin_support' => $isAdminSupport
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred: ' . $e->getMessage()
    ]);
}
?>