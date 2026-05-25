<?php
session_start();
include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Allow all authenticated roles
if (!isset($_SESSION['acc_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $dashboard_data = [];
    
    // Get user role and info using the proper functions
    $userRole = getUserRole();
    $userInfo = getUserInfo();
    
    $roleId = $userRole ? (int)$userRole['role_id'] : null;
    $departmentId = $userInfo ? (int)$userInfo['dept_id'] : null;
    $departmentName = $userInfo ? $userInfo['dept_name'] : 'All Departments';
    
    // Add department info to response
    $dashboard_data['department'] = [
        'dept_id' => $departmentId,
        'dept_name' => $departmentName
    ];

    // Build optional department scope for instructor-related queries
    $instrWhere = '';
    $instrTypes = '';
    $instrParams = [];
    if (!empty($departmentId) && $roleId !== 1) { // role 1 (IT) sees all
        $instrWhere = ' WHERE i.dept_id = ? ';
        $instrTypes = 'i';
        $instrParams[] = $departmentId;
    }

    // 1. Total Users (scoped by account.dept_id for non-IT)
    $sqlUsers = "
        SELECT 
            COUNT(CASE WHEN acc_status = 'Active' THEN 1 END) as active_users,
            COUNT(CASE WHEN acc_status = 'Pending' THEN 1 END) as pending_users,
            COUNT(CASE WHEN acc_status = 'Inactive' THEN 1 END) as inactive_users,
            COUNT(*) as total_users
        FROM account
    ";
    $userTypes = '';
    $userParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlUsers .= ' WHERE dept_id = ?';
        $userTypes = 'i';
        $userParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlUsers);
    if ($userTypes) { $stmt->bind_param($userTypes, ...$userParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['users'] = $result->fetch_assoc();
    $stmt->close();

    // 1b. User counts by role (for user management component)
    $sqlUserRoles = "
        SELECT 
            COUNT(CASE WHEN r.role_name = 'Moderator' THEN 1 END) as moderators_count,
            COUNT(CASE WHEN r.role_name = 'User' THEN 1 END) as users_count,
            COUNT(CASE WHEN r.role_name = 'Admin' THEN 1 END) as admins_count,
            COUNT(CASE WHEN r.role_name = 'Admin support' THEN 1 END) as admin_support_count
        FROM account a
        JOIN user_roles ur ON a.acc_id = ur.acc_id
        JOIN roles r ON ur.role_id = r.id
    ";
    $userRoleTypes = '';
    $userRoleParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlUserRoles .= ' WHERE a.dept_id = ?';
        $userRoleTypes = 'i';
        $userRoleParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlUserRoles);
    if ($userRoleTypes) { $stmt->bind_param($userRoleTypes, ...$userRoleParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $roleCounts = $result->fetch_assoc();
    $stmt->close();
    
    // Merge role counts into users data
    $dashboard_data['users'] = array_merge($dashboard_data['users'], $roleCounts);

    // 2. Total Instructors (scoped by department if available)
    $sqlInstructors = "
        SELECT 
            COUNT(*) as total_instructors,
            COUNT(CASE WHEN inst_status = 'Regular' THEN 1 END) as regular_instructors,
            COUNT(CASE WHEN inst_status = 'Part-Time' THEN 1 END) as parttime_instructors,
            COUNT(CASE WHEN inst_status = 'Contractual' THEN 1 END) as contractual_instructors,
            COUNT(CASE WHEN inst_status = 'Coordinator' THEN 1 END) as coordinators,
            COUNT(CASE WHEN inst_status = 'DepartmentHead' THEN 1 END) as department_heads,
            COUNT(CASE WHEN inst_status = 'Pending' THEN 1 END) as pending_instructors
        FROM instructor i
    ";
    if ($instrWhere) { $sqlInstructors .= $instrWhere; }
    $stmt = $conn->prepare($sqlInstructors);
    if ($instrWhere) { $stmt->bind_param($instrTypes, ...$instrParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['instructors'] = $result->fetch_assoc();
    $stmt->close();

    // 2b. Instructors by Program (via schedule -> subject -> curriculum)
    $dashboard_data['instructors']['by_program'] = [];
    $byProgram = [];
    $sqlProg = "
        SELECT c.curr_name AS program, COUNT(DISTINCT s.inst_id) AS count
        FROM schedule s
        INNER JOIN subject subj ON subj.subj_id = s.subj_id
        INNER JOIN curriculum c ON c.curr_id = subj.curr_id
    ";
    $params = [];
    $types = '';
    $where = [];
    // Scope by department through curriculum.dept_id if available and not IT
    if (!empty($departmentId) && $roleId !== 1) {
        $where[] = 'c.dept_id = ?';
        $types .= 'i';
        $params[] = $departmentId;
    }
    if (!empty($where)) {
        $sqlProg .= ' WHERE ' . implode(' AND ', $where);
    }
    $sqlProg .= ' GROUP BY c.curr_name ORDER BY count DESC';
    if ($stmt = $conn->prepare($sqlProg)) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $byProgram[] = $row; }
        }
        $stmt->close();
    }
    $dashboard_data['instructors']['by_program'] = $byProgram;

    // 3. Total Students (via section -> class -> curriculum scoped by dept_id)
    $sqlStudents = "
        SELECT COUNT(*) AS total_students
        FROM student st
        INNER JOIN section sec ON sec.sec_id = st.sec_id
        INNER JOIN class cls ON cls.class_id = sec.class_id
        INNER JOIN curriculum c ON c.curr_id = cls.curr_id
    ";
    $studTypes = '';
    $studParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlStudents .= ' WHERE c.dept_id = ?';
        $studTypes = 'i';
        $studParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlStudents);
    if ($studTypes) { $stmt->bind_param($studTypes, ...$studParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['students'] = $result->fetch_assoc();
    $stmt->close();

    // 4. Room Statistics (rooms are not department-specific)
    $sqlRooms = "
        SELECT 
            COUNT(*) as total_rooms,
            COUNT(CASE WHEN rm_type = 'Lab' THEN 1 END) as lab_rooms,
            COUNT(CASE WHEN rm_type = 'Lec' THEN 1 END) as lecture_rooms,
            COUNT(CASE WHEN rm_type = 'Special' THEN 1 END) as special_rooms,
            COUNT(CASE WHEN rm_status = 'Used' THEN 1 END) as used_rooms,
            COUNT(CASE WHEN rm_status = 'Unused' THEN 1 END) as unused_rooms
        FROM room r
    ";
    $stmt = $conn->prepare($sqlRooms);
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['rooms'] = $result->fetch_assoc();
    $stmt->close();

    // 4b. Building Statistics (buildings are not department-specific)
    $sqlBuildings = "
        SELECT 
            COUNT(*) as total_buildings,
            COUNT(CASE WHEN bd_status = 'Used' THEN 1 END) as used_buildings,
            COUNT(CASE WHEN bd_status = 'Unused' THEN 1 END) as unused_buildings
        FROM building b
    ";
    $stmt = $conn->prepare($sqlBuildings);
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['buildings'] = $result->fetch_assoc();
    $stmt->close();

    // 5. Schedule Statistics (via subject -> curriculum dept scope)
    $sqlSched = "
        SELECT 
            COUNT(*) as total_schedules,
            COUNT(CASE WHEN schd_status = 'Active' THEN 1 END) as active_schedules,
            COUNT(CASE WHEN schd_type = 'Lab' THEN 1 END) as lab_schedules,
            COUNT(CASE WHEN schd_type = 'Lec' THEN 1 END) as lecture_schedules,
            COUNT(CASE WHEN schd_type = 'Special' THEN 1 END) as special_schedules
        FROM schedule s
        INNER JOIN subject subj ON subj.subj_id = s.subj_id
        INNER JOIN curriculum c ON c.curr_id = subj.curr_id
    ";
    $schedTypes = '';
    $schedParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlSched .= ' WHERE c.dept_id = ?';
        $schedTypes = 'i';
        $schedParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlSched);
    if ($schedTypes) { $stmt->bind_param($schedTypes, ...$schedParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['schedules'] = $result->fetch_assoc();
    $stmt->close();

    // 6. Room Requests (join instructor to scope by instructor.dept_id)
    $sqlReq = "
        SELECT 
            COUNT(*) as total_requests,
            COUNT(CASE WHEN req_status = 'Pending' THEN 1 END) as pending_requests,
            COUNT(CASE WHEN req_status = 'Accepted' THEN 1 END) as accepted_requests,
            COUNT(CASE WHEN req_status = 'Declined' THEN 1 END) as declined_requests
        FROM room_request rr
        INNER JOIN instructor i ON i.inst_id = rr.inst_id
    ";
    $reqTypes = '';
    $reqParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlReq .= ' WHERE i.dept_id = ?';
        $reqTypes = 'i';
        $reqParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlReq);
    if ($reqTypes) { $stmt->bind_param($reqTypes, ...$reqParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['room_requests'] = $result->fetch_assoc();
    $stmt->close();

    // 7. Subject Statistics (via curriculum dept scope)
    $sqlSubj = "
        SELECT 
            COUNT(*) as total_subjects,
            COUNT(CASE WHEN subj_category = 'Major' THEN 1 END) as major_subjects,
            COUNT(CASE WHEN subj_category = 'Minor' THEN 1 END) as minor_subjects,
            COUNT(CASE WHEN subj_category = 'GENED' THEN 1 END) as gened_subjects
        FROM subject subj
        INNER JOIN curriculum c ON c.curr_id = subj.curr_id
    ";
    $subjTypes = '';
    $subjParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlSubj .= ' WHERE c.dept_id = ?';
        $subjTypes = 'i';
        $subjParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlSubj);
    if ($subjTypes) { $stmt->bind_param($subjTypes, ...$subjParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['subjects'] = $result->fetch_assoc();
    $stmt->close();

    // 8. Department Statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total_departments FROM department");
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['departments'] = $result->fetch_assoc();
    $stmt->close();

    // 8b. Curriculum Statistics (department-specific)
    $sqlCurr = "
        SELECT 
            COUNT(*) as total_curricula,
            COUNT(CASE WHEN curr_lvl = 1 THEN 1 END) as first_year_curricula,
            COUNT(CASE WHEN curr_lvl = 2 THEN 1 END) as second_year_curricula,
            COUNT(CASE WHEN curr_lvl = 3 THEN 1 END) as third_year_curricula,
            COUNT(CASE WHEN curr_lvl = 4 THEN 1 END) as fourth_year_curricula
        FROM curriculum c
    ";
    $currTypes = '';
    $currParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlCurr .= ' WHERE c.dept_id = ?';
        $currTypes = 'i';
        $currParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlCurr);
    if ($currTypes) { $stmt->bind_param($currTypes, ...$currParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['curricula'] = $result->fetch_assoc();
    $stmt->close();

    // 8c. Classes and Sections (department-specific)
    $sqlClasses = "
        SELECT 
            COUNT(DISTINCT cls.class_id) as total_classes,
            COUNT(DISTINCT sec.sec_id) as total_sections,
            COUNT(DISTINCT CASE WHEN cls.class_term = 1 THEN cls.class_id END) as first_term_classes,
            COUNT(DISTINCT CASE WHEN cls.class_term = 2 THEN cls.class_id END) as second_term_classes
        FROM class cls
        INNER JOIN section sec ON sec.class_id = cls.class_id
        INNER JOIN curriculum c ON c.curr_id = cls.curr_id
    ";
    $classTypes = '';
    $classParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlClasses .= ' WHERE c.dept_id = ?';
        $classTypes = 'i';
        $classParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlClasses);
    if ($classTypes) { $stmt->bind_param($classTypes, ...$classParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['classes'] = $result->fetch_assoc();
    $stmt->close();

    // 9. Recent Activity (last 7/30 days, scoped by account.dept_id)
    $sqlRecent = "
        SELECT 
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_week,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_month
        FROM account
    ";
    $recTypes = '';
    $recParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlRecent .= ' WHERE dept_id = ?';
        $recTypes = 'i';
        $recParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlRecent);
    if ($recTypes) { $stmt->bind_param($recTypes, ...$recParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['recent_activity'] = $result->fetch_assoc();
    $stmt->close();

    // 10. Schedule Conflicts (scope via schedule->subject->curriculum)
    $sqlConf = "
        SELECT COUNT(*) as total_conflicts
        FROM conflict cf
        INNER JOIN schedule s ON s.schd_id = cf.schd_id
        INNER JOIN subject subj ON subj.subj_id = s.subj_id
        INNER JOIN curriculum c ON c.curr_id = subj.curr_id
    ";
    $confTypes = '';
    $confParams = [];
    if (!empty($departmentId) && $roleId !== 1) {
        $sqlConf .= ' WHERE c.dept_id = ?';
        $confTypes = 'i';
        $confParams[] = $departmentId;
    }
    $stmt = $conn->prepare($sqlConf);
    if ($confTypes) { $stmt->bind_param($confTypes, ...$confParams); }
    $stmt->execute();
    $result = $stmt->get_result();
    $dashboard_data['conflicts'] = $result->fetch_assoc();
    $stmt->close();

    // 11. Department-Specific Summary (comprehensive overview)
    $dashboard_data['department_summary'] = [
        'department_id' => $departmentId,
        'department_name' => $departmentName,
        'role_id' => $roleId,
        'is_it_user' => ($roleId === 1),
        'total_entities' => [
            'users' => $dashboard_data['users']['total_users'] ?? 0,
            'instructors' => $dashboard_data['instructors']['total_instructors'] ?? 0,
            'students' => $dashboard_data['students']['total_students'] ?? 0,
            'rooms' => $dashboard_data['rooms']['total_rooms'] ?? 0,
            'buildings' => $dashboard_data['buildings']['total_buildings'] ?? 0,
            'schedules' => $dashboard_data['schedules']['total_schedules'] ?? 0,
            'subjects' => $dashboard_data['subjects']['total_subjects'] ?? 0,
            'curricula' => $dashboard_data['curricula']['total_curricula'] ?? 0,
            'classes' => $dashboard_data['classes']['total_classes'] ?? 0
        ]
    ];

    echo json_encode(['success' => true, 'data' => $dashboard_data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

