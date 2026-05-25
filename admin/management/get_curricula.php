<?php
/**
 * Get Curricula API
 * Returns all curricula for the logged-in user's department.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

/**
 * Sends a JSON response and exits the script.
 */
function json_response($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

// Allow both view and manage permissions (moderators can view, admins can manage)
// Check for any curriculum-related permission
if (!hasPermission('manage_curriculum') && !hasPermission('view_curriculum')) {
    // Also allow if user has subject management permission (they need to see curricula)
    if (!hasPermission('manage_subjects')) {
        http_response_code(403);
        json_response(false, 'Unauthorized access. You do not have permission to view curriculum.', 403);
    }
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Read dept_id from the GET request, falling back to user's department if not provided.
$requestedDeptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$dept_id = $requestedDeptId > 0 ? $requestedDeptId : $userDeptId;

// Admin Support can see all, but if no dept_id specified, use their own
if ($isAdminSupport && $dept_id <= 0) {
    // Admin Support can see all, so no filter needed
    $dept_id = 0;
} elseif (!$isAdminSupport && $dept_id <= 0) {
    json_response(false, 'User department not set.', 400);
}
if (!$isAdminSupport && $requestedDeptId > 0 && $requestedDeptId !== $userDeptId) {
    json_response(false, 'Unauthorized department scope.', [], 403);
}

try {
    $query = "SELECT c.curr_id, c.curr_code, c.curr_name, c.curr_type, c.curr_version, c.curr_desc, c.curr_objective, c.curr_total_units, 
                c.curr_lvl, c.curr_yr, c.curr_effective_start_year, c.curr_effective_end_year, c.curr_status, c.dept_id, d.dept_name,
                c.program_id, p.program_code, p.program_name
         FROM curriculum c
         INNER JOIN department d ON c.dept_id = d.dept_id
         LEFT JOIN program p ON c.program_id = p.program_id";
    
    $params = [];
    $types = '';
    
    // Filter by department if not Admin Support or if specific dept_id requested
    if (!$isAdminSupport || $dept_id > 0) {
        $query .= " WHERE c.dept_id = ?";
        $params[] = $dept_id;
        $types = 'i';
    }

    if ($program_id > 0) {
        $query .= empty($params) ? " WHERE" : " AND";
        $query .= " (c.program_id IS NULL OR c.program_id = ?)";
        $params[] = $program_id;
        $types .= 'i';
    }
    
    // Order by effective start year (if available), then by name
    // Handle empty curr_yr by using effective_start_year as fallback
    $query .= " ORDER BY COALESCE(NULLIF(c.curr_yr, ''), CAST(c.curr_effective_start_year AS CHAR)) DESC, c.curr_name ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $curricula = [];
    
    // Process results and ensure proper data types
    while ($row = $result->fetch_assoc()) {
        // Ensure program_id is an integer (or null)
        if (isset($row['program_id']) && $row['program_id'] !== null) {
            $row['program_id'] = (int)$row['program_id'];
        } else {
            $row['program_id'] = null;
        }
        
        // Ensure program_code and program_name are strings (or null)
        $row['program_code'] = !empty($row['program_code']) ? trim($row['program_code']) : null;
        $row['program_name'] = !empty($row['program_name']) ? trim($row['program_name']) : null;
        
        $curricula[] = $row;
    }
    $stmt->close();

    // Debug: Log sample curriculum data to verify program data is being fetched
    if (!empty($curricula)) {
        error_log("Sample curriculum data: " . json_encode($curricula[0]));
        // Count curricula with programs
        $withPrograms = array_filter($curricula, function($c) {
            return $c['program_id'] !== null && ($c['program_name'] || $c['program_code']);
        });
        error_log("Curricula with programs: " . count($withPrograms) . " out of " . count($curricula));
    }

    json_response(true, 'Curricula fetched successfully.', ['curricula' => $curricula]);
} catch (Exception $e) {
    error_log("Get Curricula Error: " . $e->getMessage());
    json_response(false, 'A database error occurred.', [], 500);
}
?>