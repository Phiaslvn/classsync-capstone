<?php
/**
 * Search API for Admin Dashboard Overview
 * Searches across users, departments, and schedules
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Get user info
$userInfo = getUserInfo();
if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$searchTerm = '%' . $query . '%';
$results = [];

try {
    // Search users/accounts
    $userQuery = "
        SELECT 
            a.acc_id,
            a.fname,
            a.lname,
            a.minitial,
            a.acc_user,
            a.acc_email,
            r.role_name,
            d.dept_name,
            a.acc_status,
            'user' as type,
            CONCAT(a.fname, ' ', a.lname) as display_name
        FROM account a
        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN department d ON a.dept_id = d.dept_id
        WHERE (
            a.fname LIKE ? OR 
            a.lname LIKE ? OR 
            a.acc_user LIKE ? OR 
            a.acc_email LIKE ? OR
            CONCAT(a.fname, ' ', a.lname) LIKE ?
        ) AND a.acc_status = 'Active'
        ORDER BY 
            CASE 
                WHEN a.fname LIKE ? THEN 1
                WHEN a.lname LIKE ? THEN 2
                WHEN a.acc_user LIKE ? THEN 3
                ELSE 4
            END,
            a.fname ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("ssssssssi", 
        $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
        $searchTerm, $searchTerm, $searchTerm, $limit
    );
    $stmt->execute();
    $userResults = $stmt->get_result();
    
    while ($row = $userResults->fetch_assoc()) {
        $results[] = [
            'type' => 'user',
            'id' => $row['acc_id'],
            'title' => $row['display_name'],
            'subtitle' => $row['role_name'] . ($row['dept_name'] ? ' • ' . $row['dept_name'] : ''),
            'description' => $row['acc_email'],
            'icon' => 'bi-person',
            'action' => 'viewUser',
            'action_id' => $row['acc_id']
        ];
    }
    $stmt->close();
    
    // Search departments
    $deptQuery = "
        SELECT 
            dept_id,
            dept_name,
            dept_code,
            'department' as type
        FROM department
        WHERE (
            dept_name LIKE ? OR 
            dept_code LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN dept_name LIKE ? THEN 1
                ELSE 2
            END,
            dept_name ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($deptQuery);
    $stmt->bind_param("sssi", 
        $searchTerm, $searchTerm, $searchTerm, $limit
    );
    $stmt->execute();
    $deptResults = $stmt->get_result();
    
    while ($row = $deptResults->fetch_assoc()) {
        $results[] = [
            'type' => 'department',
            'id' => $row['dept_id'],
            'title' => $row['dept_name'],
            'subtitle' => 'Department',
            'description' => $row['dept_code'] ? 'Code: ' . $row['dept_code'] : '',
            'icon' => 'bi-building',
            'action' => 'viewDepartment',
            'action_id' => $row['dept_id']
        ];
    }
    $stmt->close();
    
    // Search schedules (active schedules only)
    $scheduleQuery = "
        SELECT 
            s.schd_id,
            subj.subj_code,
            subj.subj_desc,
            CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
            r.rm_name,
            b.bd_desc,
            p.program_name,
            sec.sec_name,
            s.schd_day,
            TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time,
            TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time,
            d.dept_name,
            'schedule' as type
        FROM schedule s
        JOIN subject subj ON s.subj_id = subj.subj_id
        JOIN curriculum curr ON subj.curr_id = curr.curr_id
        JOIN section sec ON s.sec_id = sec.sec_id
        JOIN class cls ON sec.class_id = cls.class_id
        JOIN program p ON subj.program_id = p.program_id
        JOIN instructor i ON s.inst_id = i.inst_id
        JOIN room r ON s.rm_id = r.rm_id
        JOIN building b ON r.bd_id = b.bd_id
        LEFT JOIN department d ON COALESCE(s.dept_id, curr.dept_id, p.dept_id) = d.dept_id
        WHERE s.schd_status = 'Active'
        AND (
            subj.subj_code LIKE ? OR
            subj.subj_desc LIKE ? OR
            CONCAT(i.inst_fname, ' ', i.inst_lname) LIKE ? OR
            p.program_name LIKE ? OR
            sec.sec_name LIKE ? OR
            r.rm_name LIKE ? OR
            d.dept_name LIKE ?
        )
        ORDER BY s.schd_day, s.schd_start
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($scheduleQuery);
    $stmt->bind_param("sssssssi", 
        $searchTerm, $searchTerm, $searchTerm, $searchTerm, 
        $searchTerm, $searchTerm, $searchTerm, $limit
    );
    $stmt->execute();
    $scheduleResults = $stmt->get_result();
    
    while ($row = $scheduleResults->fetch_assoc()) {
        $results[] = [
            'type' => 'schedule',
            'id' => $row['schd_id'],
            'title' => $row['subj_code'] . ' - ' . $row['subj_desc'],
            'subtitle' => $row['instructor_name'] . ' • ' . $row['program_name'],
            'description' => $row['schd_day'] . ' ' . $row['start_time'] . '-' . $row['end_time'] . ' • ' . $row['rm_name'] . ' (' . $row['bd_desc'] . ')',
            'icon' => 'bi-calendar-event',
            'action' => 'viewSchedule',
            'action_id' => $row['schd_id']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("Error in search API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching.'
    ]);
}
?>

