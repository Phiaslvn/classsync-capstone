<?php
/**
 * Get Subjects API
 * Handles server-side processing for DataTables to fetch subjects.
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/program_year_level_curriculum.php';

header('Content-Type: application/json');

// Allow both view and manage permissions (moderators can view, admins can manage)
$hasPermission = hasPermission('manage_subjects') || 
                 hasPermission('view_subjects') || 
                 hasPermission('manage_curriculum');

if (!$hasPermission) {
    // Return DataTables-compatible error response
    http_response_code(403);
    echo json_encode([
        'draw' => intval($_REQUEST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Unauthorized access. You do not have permission to view subjects.'
    ]);
    exit();
}

$request = $_REQUEST;

// Normalize global search: DataTables sends search[value]; filter bar sends flat search=
$searchText = '';
if (isset($request['search']) && is_array($request['search']) && isset($request['search']['value'])) {
    $searchText = trim((string) $request['search']['value']);
}
if ($searchText === '' && isset($request['search']) && is_string($request['search'])) {
    $searchText = trim($request['search']);
}

// Base query
$sql = "
    SELECT s.subj_id, s.subj_code, s.subj_desc, s.subj_lec, s.subj_lab, s.subj_unit, s.subj_lvl, s.subj_term, p.program_name, c.curr_name
    FROM subject s
    LEFT JOIN curriculum c ON s.curr_id = c.curr_id
    LEFT JOIN program p ON s.program_id = p.program_id
";

// Filtering
$where = [];
$params = [];
$types = '';

// Department filter (important for security)
// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Department scope: subject.dept_id is the owning department (aligned with stats below)
if (!$isAdminSupport && $userDeptId > 0) {
    $where[] = "s.dept_id = ?";
    $params[] = $userDeptId;
    $types .= 'i';
}

// Check if mapping table exists (needed for curriculum mapping logic)
$table_check = $conn->query("SHOW TABLES LIKE 'program_year_level_curriculum'");
$mapping_table_exists = $table_check && $table_check->num_rows > 0;
if ($table_check) $table_check->close();

// Initialize curr_id_to_use variable
$curr_id_to_use = null;

// If program_id and level are provided AND no explicit curr_id filter, check mapping table
// If user explicitly selects a curriculum filter, use that instead of mapping table
if ($mapping_table_exists && !empty($request['program_id']) && !empty($request['level']) && empty($request['curr_id'])) {
    $curr_id_to_use = pylcurriculum_get_curr_id($conn, (int)$request['program_id'], (int)$request['level']);
}

if (!empty($request['category'])) {
    $where[] = "s.subj_category = ?";
    $params[] = $request['category'];
    $types .= 's';
}
if (!empty($request['program_id'])) {
    $where[] = "s.program_id = ?";
    $params[] = $request['program_id'];
    $types .= 'i';
}
// Use explicit curr_id if provided (user selected curriculum filter), otherwise use mapping table
if (!empty($request['curr_id'])) {
    // User explicitly selected a curriculum filter - use it
    $where[] = "s.curr_id = ?";
    $params[] = $request['curr_id'];
    $types .= 'i';
} else if ($curr_id_to_use !== null) {
    // No explicit curriculum filter, but mapping table has a curriculum for this year level
    $where[] = "s.curr_id = ?";
    $params[] = $curr_id_to_use;
    $types .= 'i';
}
if (!empty($request['term'])) {
    $where[] = "s.subj_term = ?";
    $params[] = $request['term'];
    $types .= 'i';
}
if (!empty($request['level'])) {
    $where[] = "s.subj_lvl = ?";
    $params[] = $request['level'];
    $types .= 'i';
}

// Global search (DataTables search[value] and/or filter bar ?search=)
if ($searchText !== '') {
    $searchValue = '%' . $searchText . '%';
    $where[] = "(s.subj_code LIKE ? OR s.subj_desc LIKE ? OR p.program_name LIKE ? OR c.curr_name LIKE ?)";
    $params = array_merge($params, [$searchValue, $searchValue, $searchValue, $searchValue]);
    $types .= 'ssss';
}

if (count($where) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

try {
    // Total records
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalRecords = $stmt->get_result()->num_rows;
    $stmt->close();
} catch (Exception $e) {
    error_log("Get Subjects Error (count): " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
    exit();
}

// Ordering (must match DataTables column indexes: code, title, lec, lab, unit, actions)
$columns = ['s.subj_code', 's.subj_desc', 's.subj_lec', 's.subj_lab', 's.subj_unit'];
if (isset($request['order']) && count($request['order'])) {
    $order = $request['order'][0];
    if (isset($columns[$order['column']])) {
        $sql .= " ORDER BY " . $columns[$order['column']] . " " . mysqli_real_escape_string($conn, $order['dir']);
    }
} else {
    $sql .= " ORDER BY s.subj_lvl, s.subj_term, s.subj_code";
}

// Pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $request['length'] ?? 10;
$params[] = $request['start'] ?? 0;
$types .= 'ii';

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Get Subjects Error (fetch): " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
    exit();
}

    // Get statistics for the cards - use the SAME filters as the main query
    // This ensures statistics match what's displayed in the table
    $stats_query = "
        SELECT s.subj_category, COUNT(*) as count 
        FROM subject s
        LEFT JOIN curriculum c ON s.curr_id = c.curr_id
        LEFT JOIN program p ON s.program_id = p.program_id
    ";
    
    // Use the SAME where conditions as the main query
    $stats_where = [];
    $stats_params = [];
    $stats_types = '';
    
    // Department filter — must match main query (subject.dept_id)
    if (!$isAdminSupport && $userDeptId > 0) {
        $stats_where[] = "s.dept_id = ?";
        $stats_params[] = $userDeptId;
        $stats_types .= 'i';
    }
    
    // Apply the same filters as the main query
    if (!empty($request['category'])) {
        $stats_where[] = "s.subj_category = ?";
        $stats_params[] = $request['category'];
        $stats_types .= 's';
    }
    if (!empty($request['program_id'])) {
        $stats_where[] = "s.program_id = ?";
        $stats_params[] = $request['program_id'];
        $stats_types .= 'i';
    }
    // Use the same curr_id logic as main query
    // IMPORTANT: For statistics when no filters are applied, we want ALL subjects
    // Only apply curriculum filter if explicitly requested or if we're filtering by program+level
    if (!empty($request['curr_id'])) {
        // User explicitly selected a curriculum filter - use it
        $stats_where[] = "s.curr_id = ?";
        $stats_params[] = $request['curr_id'];
        $stats_types .= 'i';
    } else if ($curr_id_to_use !== null && !empty($request['program_id']) && !empty($request['level'])) {
        // Only use mapping table curriculum if BOTH program_id AND level are specified
        // This ensures that when loading ALL subjects (no filters), we don't filter by curriculum
        $stats_where[] = "s.curr_id = ?";
        $stats_params[] = $curr_id_to_use;
        $stats_types .= 'i';
    }
    if (!empty($request['term'])) {
        $stats_where[] = "s.subj_term = ?";
        $stats_params[] = $request['term'];
        $stats_types .= 'i';
    }
    if (!empty($request['level'])) {
        $stats_where[] = "s.subj_lvl = ?";
        $stats_params[] = $request['level'];
        $stats_types .= 'i';
    }
    
    // Global search (same normalization as main query)
    if ($searchText !== '') {
        $searchValue = '%' . $searchText . '%';
        $stats_where[] = "(s.subj_code LIKE ? OR s.subj_desc LIKE ? OR p.program_name LIKE ? OR c.curr_name LIKE ?)";
        $stats_params = array_merge($stats_params, [$searchValue, $searchValue, $searchValue, $searchValue]);
        $stats_types .= 'ssss';
    }
    
    // Add WHERE clause if we have any filters
    if (count($stats_where) > 0) {
        $stats_query .= " WHERE " . implode(' AND ', $stats_where);
    }
    
    $stats_query .= " GROUP BY s.subj_category";
    
    try {
        // Log the statistics query for debugging
        error_log("Statistics Query: " . $stats_query);
        error_log("Statistics Params: " . json_encode($stats_params));
        
        $stats_stmt = $conn->prepare($stats_query);
        if (!$stats_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        if (!empty($stats_params)) {
            $stats_stmt->bind_param($stats_types, ...$stats_params);
        }
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        
        $stats = ['total' => 0, 'major' => 0, 'minor' => 0, 'gened' => 0];
        while ($row = $stats_result->fetch_assoc()) {
            $category = $row['subj_category'];
            $count = (int)$row['count'];
            if ($category === 'Major') $stats['major'] = $count;
            if ($category === 'Minor') $stats['minor'] = $count;
            if ($category === 'GENED') $stats['gened'] = $count;
            $stats['total'] += $count;
        }
        $stats_stmt->close();
        
        // Log the calculated statistics
        error_log("Calculated Statistics: " . json_encode($stats));
    } catch (Exception $e) {
        error_log("Get Subjects Error (stats): " . $e->getMessage());
        error_log("Stats Query that failed: " . $stats_query);
        // Use default stats if stats query fails
        $stats = ['total' => 0, 'major' => 0, 'minor' => 0, 'gened' => 0];
    }

// DataTables expects data array directly, but we also need to include statistics
// We'll use a custom dataSrc function in JavaScript to extract both
$response = [
    "draw" => intval($request['draw'] ?? 0),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $subjects, // Direct array for DataTables
    "statistics" => $stats, // Additional data for stats cards
    "curricula" => [] // You can populate this if needed for filters
];

// Ensure we always return valid JSON
header('Content-Type: application/json');
echo json_encode($response);

// Don't close connection here - let PHP handle it
?>