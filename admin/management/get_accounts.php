<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user has permission to view accounts
// Allow either manage_users (full access) or view_users (read-only access)
$hasManageUsers = hasPermission('manage_users');
$hasViewUsers = hasPermission('view_users');

// Debug logging
error_log("get_accounts.php - Permission check: manage_users=" . ($hasManageUsers ? 'true' : 'false') . ", view_users=" . ($hasViewUsers ? 'true' : 'false'));

if (!$hasManageUsers && !$hasViewUsers) {
  http_response_code(403);
  echo json_encode([
    'success' => false, 
    'message' => 'Unauthorized access. You do not have permission to view users.',
    'debug' => [
      'hasManageUsers' => $hasManageUsers,
      'hasViewUsers' => $hasViewUsers,
      'currentUserId' => $_SESSION['acc_id'] ?? null
    ]
  ]);
  exit();
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Check if user has manage_users or view_users permission
// Regular admins with these permissions should still be filtered by their department's programs
// Only Admin Support can see all users
$hasUserPermission = hasPermission('manage_users') || hasPermission('view_users');

$deptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : $userDeptId;

// Only Admin Support can see all users (no department filter)
// Regular admins should always be filtered by their department's programs
if ($isAdminSupport && $deptId <= 0) {
    $deptId = 0;
} elseif (!$isAdminSupport && $deptId <= 0) {
    // For regular admins, use their own department if dept_id not provided
    $deptId = $userDeptId;
}

try {
  // Determine which roles to show based on user permissions
  // Admin Support can see all roles including Admin support
  // Regular Admins/Moderators with permission can see Admin, Moderator, and User roles
  $allowedRoles = ['Admin', 'Moderator', 'User'];
  if ($isAdminSupport) {
    // Admin Support can see all roles
    $allowedRoles[] = 'Admin support';
  }
  
  // Build role placeholders for WHERE clause
  $rolePlaceholders = implode(',', array_fill(0, count($allowedRoles), '?'));
  
  // Get accounts with roles (Admin, Moderator, User/Instructor), filtered by department
  // Include program information - prioritize instructor's program_id, fallback to department's first program
  // Include workload policy information based on user's rank
  // Show Admin and Moderator roles when user has manage_users or view_users permission
  $sql = "
    SELECT a.acc_id, a.fname AS first_name, a.lname AS last_name, a.minitial AS middle_initial, a.suffix,
           a.acc_user, a.acc_email, a.acc_status, a.dept_id,
           d.dept_name,
           COALESCE(
             (SELECT CONCAT(p.program_code, ' - ', p.program_name) 
              FROM program p 
              WHERE p.program_id = i.program_id 
                AND p.program_status = 'Active'
              LIMIT 1),
             (SELECT CONCAT(p.program_code, ' - ', p.program_name) 
              FROM program p 
              WHERE p.dept_id = a.dept_id 
                AND p.program_status = 'Active' 
              ORDER BY p.program_code 
              LIMIT 1)
           ) AS program_display,
           COALESCE(i.program_id, 
             (SELECT p.program_id 
              FROM program p 
              WHERE p.dept_id = a.dept_id 
                AND p.program_status = 'Active' 
              ORDER BY p.program_code 
              LIMIT 1)
           ) AS program_id,
           r.id AS role_id, r.role_name,
           i.inst_id, i.inst_status, i.inst_fname, i.inst_lname, i.inst_mname, i.inst_suffix,
           i.designation, i.rank, i.inst_phone, i.instruction_hours,
           -- Fetch workload hours directly from instructor table columns
           COALESCE(i.administration_hours, 0) AS administration_hours,
           COALESCE(i.research_hours, 0) AS research_hours,
           COALESCE(i.extension_hours, 0) AS extension_hours,
           COALESCE(i.instructional_functions_hours, 0) AS instructional_functions_hours,
           COALESCE(i.instructional_functions_hours, 0) AS production_hours,
           COALESCE(i.consultation_hours, 0) AS consultation_hours,
           -- Total hours: use instruction_hours if set, otherwise default to 40
           COALESCE(i.instruction_hours, 40) AS policy_total_hours
      FROM account a
      JOIN user_roles ur ON a.acc_id = ur.acc_id
      JOIN roles r ON ur.role_id = r.id
      LEFT JOIN department d ON a.dept_id = d.dept_id
      LEFT JOIN instructor i ON i.inst_user = a.acc_user
      LEFT JOIN program p_user ON p_user.program_id = i.program_id AND p_user.program_status = 'Active'
      LEFT JOIN workload_policy wp_designation ON wp_designation.name = i.designation AND wp_designation.policy_type = 'Designation' AND i.designation IS NOT NULL AND i.designation != 'None'
      LEFT JOIN workload_policy wp_rank ON wp_rank.name = i.rank AND wp_rank.policy_type = 'Rank' AND i.rank IS NOT NULL AND i.rank != ''
     WHERE r.role_name IN ($rolePlaceholders)
  ";
  
  $params = [];
  $types = '';
  
  // Add role parameters
  foreach ($allowedRoles as $role) {
    $params[] = $role;
    $types .= 's';
  }
  
  // Check if account_departments table exists (for multi-department support)
  $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
  $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
  
  // Filter by department if specified and user is not Admin Support
  // For regular admins, filter by program's department OR account_departments association
  // This ensures admins see users from their department (via account_departments or account.dept_id)
  // Admin Support can see all users (no filter applied)
  if ($deptId > 0 && !$isAdminSupport) {
    if ($hasAccountDepartmentsTable) {
      // Use account_departments table for filtering (supports multi-department accounts)
      $sql .= " AND (
        -- Account has department association via account_departments table
        EXISTS (
          SELECT 1 FROM account_departments ad
          WHERE ad.acc_id = a.acc_id
            AND ad.dept_id = ?
        )
        -- OR fallback: account's primary department matches (for backward compatibility)
        OR a.dept_id = ?
        -- OR for instructors with program_id: their program's department must match
        OR (i.inst_id IS NOT NULL AND i.program_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM program p_check
          WHERE p_check.program_id = i.program_id
            AND p_check.dept_id = ?
            AND p_check.program_status = 'Active'
        ))
      )";
      $params[] = $deptId; // For account_departments filter
      $params[] = $deptId; // For account.dept_id fallback
      $params[] = $deptId; // For program's department filter
      $types .= 'iii';
    } else {
      // Fallback to old method if account_departments table doesn't exist
      $sql .= " AND (
        -- For instructors with program_id: their program's department must match admin's department
        (i.inst_id IS NOT NULL AND i.program_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM program p_check
          WHERE p_check.program_id = i.program_id
            AND p_check.dept_id = ?
            AND p_check.program_status = 'Active'
        ))
        -- For instructors without program_id: their account department must match admin's department
        OR (i.inst_id IS NOT NULL AND (i.program_id IS NULL OR i.program_id = 0) AND a.dept_id = ?)
        -- For non-instructors (Admin/Moderator): their account department must match
        OR (i.inst_id IS NULL AND a.dept_id = ?)
      )";
      $params[] = $deptId; // For program's department filter (instructors with program)
      $params[] = $deptId; // For account department filter (instructors without program)
      $params[] = $deptId; // For account department filter (non-instructors)
      $types .= 'iii';
    }
  }
  
  $sql .= " ORDER BY a.acc_id DESC";
  
  $stmt = $conn->prepare($sql);
  if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) {
    // Note: Part-Time instructors can have a rank (per campus policy)
    // Rank and designation are preserved as stored in database
    
    // Calculate total hours: use instruction_hours if set, otherwise default to 40
    // For Part-Time/Contractual, always use instruction_hours
    if ($r['inst_status'] === 'Part-Time' || $r['inst_status'] === 'Contractual') {
      $r['total_hours'] = (int)($r['instruction_hours'] ?? 0);
    } else {
      // For Regular, use instruction_hours if set, otherwise default to 40
      $r['total_hours'] = (int)($r['instruction_hours'] ?? 40);
    }
    
    $rows[] = $r;
  }
  $stmt->close();

  // Debug: Log role distribution
  $roleDistribution = [];
  foreach ($rows as $row) {
    $roleName = $row['role_name'] ?? 'Unknown';
    $roleDistribution[$roleName] = ($roleDistribution[$roleName] ?? 0) + 1;
  }

  // Log filtering information for debugging
  error_log("get_accounts.php - Filter applied: deptId={$deptId}, isAdminSupport=" . ($isAdminSupport ? 'true' : 'false') . ", userDeptId={$userDeptId}, filteredCount=" . count($rows));
  
  // Additional debug: Check program distribution
  if ($deptId > 0 && !$isAdminSupport) {
    $programCounts = [];
    $instructorCounts = ['with_program' => 0, 'without_program' => 0];
    foreach ($rows as $row) {
      if ($row['inst_id']) {
        if ($row['program_id']) {
          $instructorCounts['with_program']++;
          $programCounts[$row['program_id']] = ($programCounts[$row['program_id']] ?? 0) + 1;
        } else {
          $instructorCounts['without_program']++;
        }
      }
    }
    error_log("get_accounts.php - Instructor breakdown: " . json_encode($instructorCounts));
    error_log("get_accounts.php - Program distribution: " . json_encode($programCounts));
  }
  
  echo json_encode([
    'success' => true, 
    'accounts' => $rows,
    'debug' => [
      'deptId' => $deptId,
      'userDeptId' => $userDeptId,
      'isAdminSupport' => $isAdminSupport,
      'hasUserPermission' => $hasUserPermission,
      'allowedRoles' => $allowedRoles,
      'count' => count($rows),
      'roleDistribution' => $roleDistribution,
      'filterApplied' => ($deptId > 0 && !$isAdminSupport),
      'sql' => $sql,
      'params' => $params,
      'sampleAccount' => count($rows) > 0 ? [
        'acc_id' => $rows[0]['acc_id'] ?? null,
        'role_name' => $rows[0]['role_name'] ?? null,
        'role_id' => $rows[0]['role_id'] ?? null,
        'inst_status' => $rows[0]['inst_status'] ?? null,
        'dept_id' => $rows[0]['dept_id'] ?? null,
        'program_id' => $rows[0]['program_id'] ?? null
      ] : null
    ]
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>


