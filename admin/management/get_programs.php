<?php
/**
 * Get Programs API
 * Fetches, filters, and paginates programs for the admin's department.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

/**
 * Sends a JSON response and exits the script.
 * @param bool $success
 * @param string $message
 * @param array $data
 * @param int $statusCode
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

// Permission check - Allow both view and manage permissions (moderators can view, admins can manage)
if (!hasPermission('manage_curriculum') && !hasPermission('manage_programs') && !hasPermission('view_programs')) {
    // Also allow if user has subject management permission (they need to see programs)
    if (!hasPermission('manage_subjects')) {
        http_response_code(403);
        json_response(false, 'Unauthorized access. You do not have permission to view course/programs.', [], 403);
    }
}

// Check if we should show all programs (for course management page)
$showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// If show_all is requested (for course management), show ALL programs regardless of department
// This bypasses all department filtering
if ($showAll) {
    $dept_id = 0; // Set to 0 to indicate no filtering
} else {
    // Normal filtering logic (for other pages that need department filtering)
    $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : $userDeptId;
    
    if ($isAdminSupport && $dept_id <= 0) {
        // Admin Support can see all, so no filter needed
        $dept_id = 0;
    } elseif (!$isAdminSupport && $dept_id <= 0) {
        json_response(false, 'User department not set.', [], 400);
    }
}

// --- Filtering and Pagination Parameters ---
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build the query
    $selectClause = "SELECT p.program_id, p.program_code, p.program_name, p.effective_academic_year, p.program_type, p.total_units_required, p.major_track, p.program_desc, p.program_years, p.program_status, p.created_at, p.updated_at, d.dept_name, c.college_name";
    $fromClause = "FROM program p 
                   LEFT JOIN department d ON p.dept_id = d.dept_id 
                   LEFT JOIN college c ON d.college_id = c.college_id";
    
    $whereClauses = [];
    $params = [];
    $types = "";
    
    // Only add department filter if NOT showing all programs
    // When show_all=1, we want ALL programs from ALL departments
    if (!$showAll && $dept_id > 0) {
        // If Admin Support and no specific dept_id in GET, don't filter
        if ($isAdminSupport && !isset($_GET['dept_id'])) {
            // Admin Support sees all by default, no filter
        } else {
            // Filter by department for regular admins
            $whereClauses[] = "p.dept_id = ?";
            $params[] = $dept_id;
            $types .= "i";
        }
    }
    // When showAll is true, no department filter is added, so all programs are returned

    // --- Apply Filters ---
    if (!empty($status)) {
        $whereClauses[] = "p.program_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if (!empty($search)) {
        $whereClauses[] = "(p.program_code LIKE ? OR p.program_name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }

    // Build the complete query
    $dataQuery = $selectClause . " " . $fromClause;
    if (!empty($whereClauses)) {
        $dataQuery .= " WHERE " . implode(" AND ", $whereClauses);
    }
    // Order by department name first (handle NULLs), then program name
    $dataQuery .= " ORDER BY COALESCE(d.dept_name, 'Unknown') ASC, p.program_name ASC";
    
    // Debug logging
    error_log("Get Programs Query: " . $dataQuery);
    error_log("Get Programs - showAll: " . ($showAll ? 'true' : 'false'));
    error_log("Get Programs - dept_id: " . $dept_id);
    error_log("Get Programs - isAdminSupport: " . ($isAdminSupport ? 'true' : 'false'));

    $data_stmt = $conn->prepare($dataQuery);
    if (!$data_stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }
    
    // Only bind parameters if there are any
    if (!empty($params) && !empty($types)) {
        $data_stmt->bind_param($types, ...$params);
    }
    
    $data_stmt->execute();
    $result = $data_stmt->get_result();
    
    if (!$result) {
        throw new Exception("Query execution failed: " . $data_stmt->error);
    }
    
    $programs = $result->fetch_all(MYSQLI_ASSOC);
    $data_stmt->close();
    
    // Log for debugging - count programs by department
    $deptCounts = [];
    foreach ($programs as $prog) {
        $deptName = $prog['dept_name'] ?? 'Unknown';
        $deptCounts[$deptName] = ($deptCounts[$deptName] ?? 0) + 1;
    }
    error_log("Get Programs: Found " . count($programs) . " total programs");
    error_log("Get Programs by Department: " . json_encode($deptCounts));
    
    // Also log first few programs for debugging
    if (count($programs) > 0) {
        error_log("Get Programs Sample (first 3): " . json_encode(array_slice($programs, 0, 3)));
    }

    json_response(true, 'Programs fetched successfully.', [
        'programs' => $programs
    ]);

} catch (Exception $e) {
    error_log("Get Programs Error: " . $e->getMessage());
    error_log("Get Programs Error Trace: " . $e->getTraceAsString());
    json_response(false, 'A database error occurred while fetching programs: ' . $e->getMessage(), [], 500);
}
?>