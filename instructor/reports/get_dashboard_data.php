<?php
// Start output buffering to prevent any premature output
ob_start();

// Include session configuration first (handles session_start properly)
require_once __DIR__ . '/../../config/session.php';

// Include database connection
require_once __DIR__ . '/../../config/database.php';

// Include security middleware
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Clean any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Allow all authenticated roles
if (!isset($_SESSION['acc_id'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check database connection
if (!isset($conn) || !$conn || (is_object($conn) && property_exists($conn, 'connect_error') && $conn->connect_error)) {
    ob_clean();
    http_response_code(500);
    $errorMsg = isset($db_connection_error) ? $db_connection_error : 'Database connection failed.';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit();
}

try {
    $dashboard_data = [];
    
    // Get user role and info using the proper functions
    // Check if functions exist and handle errors gracefully
    if (!function_exists('getUserRole') || !function_exists('getUserInfo')) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Security middleware functions not available.']);
        exit();
    }
    
    $userRole = getUserRole();
    $userInfo = getUserInfo();
    
    $roleId = $userRole ? (int)$userRole['role_id'] : null;
    $departmentId = $userInfo ? (int)$userInfo['dept_id'] : null;
    $departmentName = $userInfo ? ($userInfo['dept_name'] ?? 'All Departments') : 'All Departments';
    
    // Get instructor ID for instructors (they can only see their own data)
    $instructorId = null;
    // FIX: Use session inst_id if available (set during login), otherwise look it up
    if (isset($_SESSION['inst_id'])) {
        $instructorId = (int)$_SESSION['inst_id'];
    } else if (isset($_SESSION['acc_user'])) {
        // Fallback: Look up by inst_user (instructor table links via inst_user, not acc_id)
        $accUser = $_SESSION['acc_user'];
        $instQuery = "SELECT inst_id FROM instructor WHERE inst_user = ? LIMIT 1";
        $instStmt = $conn->prepare($instQuery);
        if ($instStmt) {
            $instStmt->bind_param("s", $accUser);
            if ($instStmt->execute()) {
                $instResult = $instStmt->get_result();
                if ($instRow = $instResult->fetch_assoc()) {
                    $instructorId = (int)$instRow['inst_id'];
                }
            } else {
                error_log('Error executing instructor query: ' . $instStmt->error);
            }
            $instStmt->close();
        } else {
            error_log('Error preparing instructor query: ' . $conn->error);
        }
    }
    
    // Add department info to response
    $dashboard_data['department'] = [
        'dept_id' => $departmentId,
        'dept_name' => $departmentName
    ];

    // For instructors, only show their own schedule statistics
    if ($instructorId !== null) {
        // Schedule Statistics - Only instructor's own schedules
        $sqlSchedules = "SELECT 
            COUNT(*) as total_schedules,
            COUNT(CASE WHEN schd_status = 'Active' THEN 1 END) as active_schedules,
            COUNT(CASE WHEN schd_type = 'Lab' THEN 1 END) as lab_schedules,
            COUNT(CASE WHEN schd_type = 'Lec' THEN 1 END) as lecture_schedules,
            COUNT(CASE WHEN schd_type = 'Special' THEN 1 END) as special_schedules
            FROM schedule
            WHERE inst_id = ? AND schd_status = 'Active'";
        
        $stmt = $conn->prepare($sqlSchedules);
        if ($stmt) {
            $stmt->bind_param("i", $instructorId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    $scheduleData = $result->fetch_assoc();
                    $dashboard_data['schedules'] = $scheduleData ? $scheduleData : [
                        'total_schedules' => 0,
                        'active_schedules' => 0,
                        'lab_schedules' => 0,
                        'lecture_schedules' => 0,
                        'special_schedules' => 0
                    ];
                } else {
                    $dashboard_data['schedules'] = [
                        'total_schedules' => 0,
                        'active_schedules' => 0,
                        'lab_schedules' => 0,
                        'lecture_schedules' => 0,
                        'special_schedules' => 0
                    ];
                }
            } else {
                error_log('Error executing schedule query: ' . $stmt->error);
                $dashboard_data['schedules'] = [
                    'total_schedules' => 0,
                    'active_schedules' => 0,
                    'lab_schedules' => 0,
                    'lecture_schedules' => 0,
                    'special_schedules' => 0
                ];
            }
            $stmt->close();
        } else {
            error_log('Error preparing schedule query: ' . $conn->error);
            $dashboard_data['schedules'] = [
                'total_schedules' => 0,
                'active_schedules' => 0,
                'lab_schedules' => 0,
                'lecture_schedules' => 0,
                'special_schedules' => 0
            ];
        }
        
        // Set other stats to empty/zero for instructors (they don't need to see system-wide stats)
        $dashboard_data['users'] = [
            'total_users' => 0,
            'active_users' => 0,
            'pending_users' => 0,
            'inactive_users' => 0
        ];
        $dashboard_data['instructors'] = [
            'total_instructors' => 0,
            'regular_instructors' => 0,
            'parttime_instructors' => 0,
            'contractual_instructors' => 0
        ];
        $dashboard_data['rooms'] = [
            'total_rooms' => 0,
            'lab_rooms' => 0,
            'lecture_rooms' => 0,
            'special_rooms' => 0,
            'used_rooms' => 0,
            'unused_rooms' => 0
        ];
        $dashboard_data['subjects'] = [
            'total_subjects' => 0,
            'major_subjects' => 0,
            'minor_subjects' => 0,
            'gened_subjects' => 0
        ];
        $dashboard_data['room_requests'] = [
            'total_requests' => 0,
            'pending_requests' => 0,
            'accepted_requests' => 0,
            'declined_requests' => 0
        ];
        $dashboard_data['departments'] = ['total_departments' => 0];
        $dashboard_data['recent_activity'] = [
            'new_users_week' => 0,
            'new_users_month' => 0
        ];
    } else {
        // For non-instructors (shouldn't normally reach here, but fallback)
        $dashboard_data['schedules'] = [
            'total_schedules' => 0,
            'active_schedules' => 0,
            'lab_schedules' => 0,
            'lecture_schedules' => 0,
            'special_schedules' => 0
        ];
        $dashboard_data['users'] = [
            'total_users' => 0,
            'active_users' => 0,
            'pending_users' => 0,
            'inactive_users' => 0
        ];
        $dashboard_data['instructors'] = [
            'total_instructors' => 0,
            'regular_instructors' => 0,
            'parttime_instructors' => 0,
            'contractual_instructors' => 0
        ];
        $dashboard_data['rooms'] = [
            'total_rooms' => 0,
            'lab_rooms' => 0,
            'lecture_rooms' => 0,
            'special_rooms' => 0,
            'used_rooms' => 0,
            'unused_rooms' => 0
        ];
        $dashboard_data['subjects'] = [
            'total_subjects' => 0,
            'major_subjects' => 0,
            'minor_subjects' => 0,
            'gened_subjects' => 0
        ];
        $dashboard_data['room_requests'] = [
            'total_requests' => 0,
            'pending_requests' => 0,
            'accepted_requests' => 0,
            'declined_requests' => 0
        ];
        $dashboard_data['departments'] = ['total_departments' => 0];
        $dashboard_data['recent_activity'] = [
            'new_users_week' => 0,
            'new_users_month' => 0
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $dashboard_data], JSON_UNESCAPED_UNICODE);
    exit();

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}
?>