<?php
/**
 * Unified Security Middleware for EVSU-OCC Scheduling System
 * Consolidates permission checking, CSRF protection, audit logging, and input validation
 */

// Include database connection (with error handling)
// Note: Database connection failure should not prevent functions from being defined
$db_path = __DIR__ . '/../../config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
    // Verify connection was established
    if (!isset($conn) || !$conn) {
        error_log('Database connection not established in security_middleware.php');
    }
} else {
    error_log('Database config file not found: ' . $db_path);
    // Set $conn to null to prevent errors
    $conn = null;
}

// ============================================
// SESSION HANDLING
// ============================================

// Ensure session is started (session_config.php should be included first)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CSRF PROTECTION
// ============================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input
 */
function getCSRFTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// ============================================
// INPUT VALIDATION & SANITIZATION
// ============================================

/**
 * Sanitize input data
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate required fields
 */
function validateRequired($data, $required_fields) {
    $errors = [];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

// ============================================
// UNIFIED PERMISSION SYSTEM
// ============================================

/**
 * Get user's role information
 */
function getUserRole($acc_id = null) {
    global $conn;
    
    // Check if database connection exists and is valid
    if (!isset($conn) || !$conn) {
        error_log('Database connection not available in getUserRole()');
        return null;
    }
    
    // Check for connection errors
    if (method_exists($conn, 'connect_error') && $conn->connect_error) {
        error_log('Database connection error in getUserRole(): ' . $conn->connect_error);
        return null;
    }
    
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    if (!$acc_id) {
        return null;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT r.id as role_id, r.role_name
            FROM account a 
            JOIN user_roles ur ON a.acc_id = ur.acc_id 
            JOIN roles r ON ur.role_id = r.id 
            WHERE a.acc_id = ? 
            LIMIT 1
        ");
        
        if (!$stmt) {
            error_log('Failed to prepare statement in getUserRole(): ' . ($conn->error ?? 'Unknown error'));
            return null;
        }
        
        $stmt->bind_param("i", $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $role = $result->fetch_assoc();
        $stmt->close();
        
        return $role;
    } catch (Exception $e) {
        error_log('Error in getUserRole(): ' . $e->getMessage());
        return null;
    } catch (Error $e) {
        error_log('Fatal error in getUserRole(): ' . $e->getMessage());
        return null;
    }
}

/**
 * Get user's effective permissions (role + user overrides)
 */
function getUserEffectivePermissions($acc_id = null) {
    global $conn;
    
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    if (!$acc_id) {
        return [];
    }
    
    $permissions = [];
    
    // Get user's role
    $role = getUserRole($acc_id);
    if (!$role) {
        return $permissions;
    }
    
    $roleId = $role['role_id'];
    
    // 1. Get role-based permissions
    try {
    $stmt = $conn->prepare("
            SELECT COALESCE(p.permission_key, p.permission_name) as permission_key, p.permission_name
        FROM role_permissions rp
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
    ");
    } catch (Exception $e) {
        // If permissions table doesn't exist, return empty permissions
        return [];
    }
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $permissions[$row['permission_key']] = true;
    }
    $stmt->close();
    
    // 2. Apply user-specific overrides (if user_permissions table exists)
    // IMPORTANT: User overrides take precedence over role permissions
    // If allowed = 0, the permission is explicitly denied even if role grants it
    // If allowed = 1, the permission is granted (even if role doesn't grant it)
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(p.permission_key, p.permission_name) as permission_key, up.allowed, p.permission_name
            FROM user_permissions up
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE up.acc_id = ?
        ");
        $stmt->bind_param("i", $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $permissionKey = $row['permission_key'];
            $allowed = (int)$row['allowed']; // Ensure it's an integer for comparison
            
            // Explicitly set permission based on allowed value
            // allowed = 0 → false (denied), allowed = 1 → true (granted)
            // CRITICAL: If allowed = 1, grant permission even if role doesn't have it
            if ($allowed === 1) {
                $permissions[$permissionKey] = true;
            } elseif ($allowed === 0) {
                // Explicitly deny permission even if role grants it
                $permissions[$permissionKey] = false;
            }
            // Note: If allowed is neither 0 nor 1, we don't modify the permission
        }
        $stmt->close();
    } catch (Exception $e) {
        // user_permissions table might not exist, continue with role permissions only
        error_log("User permissions table access failed: " . $e->getMessage());
    }
    
    return $permissions;
}

/**
 * Check if user has a specific permission
 * Returns true if:
 * - Permission is in array AND value is true (explicitly granted)
 * - Permission is NOT in array but user has user_permissions entry with allowed = 1
 */
function hasPermission($permissionKey, $acc_id = null) {
    $permissions = getUserEffectivePermissions($acc_id);
    
    // If permission is explicitly set in array, return its value
    if (isset($permissions[$permissionKey])) {
        return $permissions[$permissionKey] === true;
    }
    
    // If permission is not in array, check user_permissions directly
    // This handles cases where permission_key might not match exactly
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    if ($acc_id) {
        global $conn;
        try {
            $stmt = $conn->prepare("
                SELECT up.allowed
                FROM user_permissions up
                INNER JOIN permissions p ON up.permission_id = p.id
                WHERE up.acc_id = ? 
                AND (COALESCE(p.permission_key, p.permission_name) = ? OR p.permission_name = ?)
                LIMIT 1
            ");
            $stmt->bind_param("iss", $acc_id, $permissionKey, $permissionKey);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $allowed = (int)$row['allowed'];
                // If allowed = 1, grant permission
                return $allowed === 1;
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error checking user permission directly: " . $e->getMessage());
        }
    }
    
    // Default: no permission
    return false;
}

/**
 * Check if user is Admin Support
 */
function isAdminSupport($acc_id = null) {
    $role = getUserRole($acc_id);
    return $role && $role['role_name'] === 'Admin support';
}

/**
 * Check if user is Admin
 */
function isAdmin($acc_id = null) {
    $role = getUserRole($acc_id);
    return $role && $role['role_name'] === 'Admin';
}

/**
 * Check if user is Moderator
 */
function isModerator($acc_id = null) {
    $role = getUserRole($acc_id);
    return $role && $role['role_name'] === 'Moderator';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['acc_id']) && !empty($_SESSION['acc_id']);
}

/**
 * Require login and redirect if not logged in
 * When included (via $_GET['included'] or $_POST['included']), returns false instead of redirecting
 */
function requireLogin($redirectUrl = '../index.php') {
    // Check if we're being included (not standalone page)
    $isIncluded = isset($_GET['included']) || isset($_POST['included']);
    
    if (!isLoggedIn()) {
        if ($isIncluded) {
            return false; // Return false when included, don't redirect
        }
        header("Location: $redirectUrl");
        exit();
    }
    
    return true;
}

/**
 * Require specific role
 * When included (via $_GET['included'] or $_POST['included']), returns false instead of redirecting
 * This allows content to be included in dashboard tabs without breaking the page
 */
function requireRole($requiredRole, $redirectUrl = '../index.php') {
    // Check if we're being included (not standalone page)
    $isIncluded = isset($_GET['included']) || isset($_POST['included']);
    
    // First check if user is logged in
    if (!requireLogin($redirectUrl)) {
        return false; // requireLogin handles the included flag
    }
    
    // Check role
    $role = getUserRole();
    if (!$role || $role['role_name'] !== $requiredRole) {
        if ($isIncluded) {
            return false; // Return false when included, don't redirect
        }
        header("Location: $redirectUrl");
        exit();
    }
    
    // Role check passed
    return true;
}

/**
 * Require specific permission
 */
function requirePermission($permissionKey, $redirectUrl = '../index.php') {
    requireLogin($redirectUrl);
    
    if (!hasPermission($permissionKey)) {
        header("Location: $redirectUrl");
        exit();
    }
}

// ============================================
// AUDIT LOGGING
// ============================================

/**
 * Log admin action to audit log
 */
function logAdminAction($acc_id, $action, $details = '') {
    global $conn;
    
    if (!isset($conn) || !$conn) {
        error_log("logAdminAction: no database connection");
        return false;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_log (acc_id, action, log_date, details) 
            VALUES (?, ?, NOW(), ?)
        ");
        
        if (!$stmt) {
            error_log("logAdminAction: prepare failed: " . ($conn->error ?? ''));
            return false;
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $details_full = $details;
        if ($ip_address !== '' || $user_agent !== '') {
            $details_full .= ' | IP: ' . $ip_address . ' | UA: ' . substr($user_agent, 0, 200);
        }
        
        $stmt->bind_param("iss", $acc_id, $action, $details_full);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Audit logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log unauthorized access attempt
 */
function logUnauthorizedAccess($acc_id, $attempted_action, $details = '') {
    global $conn;
    
    if (!isset($conn) || !$conn) {
        error_log("logUnauthorizedAccess: no database connection");
        return false;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_log (acc_id, action, log_date, details) 
            VALUES (?, 'UNAUTHORIZED_ACCESS', NOW(), ?)
        ");
        
        if (!$stmt) {
            error_log("logUnauthorizedAccess: prepare failed: " . ($conn->error ?? ''));
            return false;
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $full_details = "Attempted: $attempted_action. " . $details;
        if ($ip_address !== '' || $user_agent !== '') {
            $full_details .= ' | IP: ' . $ip_address . ' | UA: ' . substr($user_agent, 0, 200);
        }
        
        $stmt->bind_param("is", $acc_id, $full_details);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Unauthorized access logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Require specific permission with audit logging for failures
 */
function requirePermissionWithAudit($permissionKey, $redirectUrl = '../index.php') {
    requireLogin($redirectUrl);
    
    if (!hasPermission($permissionKey)) {
        // Log the unauthorized attempt
        logUnauthorizedAccess($_SESSION['acc_id'], $permissionKey, "Direct access attempt");
        
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Get audit logs for a user
 */
function getAuditLogs($acc_id = null, $limit = 50) {
    global $conn;
    
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    if (!$acc_id) {
        return [];
    }
    
    $stmt = $conn->prepare("
        SELECT al.*, a.fname, a.lname, a.acc_user
        FROM audit_log al
        JOIN account a ON al.acc_id = a.acc_id
        WHERE al.acc_id = ?
        ORDER BY al.log_date DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $acc_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    
    return $logs;
}

// ============================================
// USER INFORMATION
// ============================================

/**
 * Get user's basic information
 */
function getUserInfo($acc_id = null) {
    global $conn;
    
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    if (!$acc_id) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT a.acc_id, a.fname, a.lname, a.minitial, a.acc_user, a.acc_email, 
               a.acc_status, a.profile_picture, d.dept_name, d.dept_id
        FROM account a 
        LEFT JOIN department d ON a.dept_id = d.dept_id
        WHERE a.acc_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // If session has multi-department info (from login), use it to override/enhance the result
    // This ensures instructors added via "Add Existing Instructor" see the correct department
    if ($acc_id === ($_SESSION['acc_id'] ?? null) && isset($_SESSION['dept_id']) && isset($_SESSION['dept_name'])) {
        // Use session department data (which comes from account_departments during login)
        $user['dept_id'] = $_SESSION['dept_id'];
        $user['dept_name'] = $_SESSION['dept_name'];
        
        // Also include all departments if available in session
        if (isset($_SESSION['departments']) && is_array($_SESSION['departments'])) {
            $user['departments'] = $_SESSION['departments'];
        }
    }
    
    return $user;
}

/**
 * Get user permissions from database using the correct SQL query
 */
function getUserPermissions($userId, $conn = null) {
    if (!$conn) {
        global $conn;
        if (!$conn) {
            require_once __DIR__ . '/../../config/database.php';
        }
    }
    
    try {
        // First check if permissions table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'permissions'");
        if ($checkTable->num_rows === 0) {
            // Permissions table doesn't exist, return empty array
            return [];
        }
        
        // Check if permission_key column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM permissions LIKE 'permission_key'");
        if ($checkColumn->num_rows === 0) {
            // permission_key column doesn't exist, use permission_name as key
            $stmt = $conn->prepare("
                SELECT p.permission_name, p.permission_name as permission_key
                FROM account a
                JOIN user_roles ur ON a.acc_id = ur.acc_id
                JOIN roles r ON ur.role_id = r.id
                JOIN role_permissions rp ON r.id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE a.acc_id = ?
            ");
        } else {
            // permission_key column exists, use it
    $stmt = $conn->prepare("
        SELECT p.permission_name, p.permission_key
        FROM account a
        JOIN user_roles ur ON a.acc_id = ur.acc_id
        JOIN roles r ON ur.role_id = r.id
        JOIN role_permissions rp ON r.id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE a.acc_id = ?
    ");
        }
        
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    $stmt->close();
    return $permissions;
    } catch (Exception $e) {
        // If there's any error with permissions system, return empty array
        error_log("Error getting user permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has a specific permission (session-based)
 */
function hasSessionPermission($permName) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    foreach ($_SESSION['permissions'] as $p) {
        if ($p['permission_key'] === $permName) {
            return true;
        }
    }
    return false;
}

/**
 * Load user permissions into session during login
 */
function loadUserPermissions($userId) {
    $permissions = getUserPermissions($userId);
    $_SESSION['permissions'] = $permissions;
    
    // If no permissions found, set basic permissions based on role
    if (empty($permissions)) {
        $role = getUserRole($userId);
        if ($role) {
            $roleName = $role['role_name'];
            $basicPermissions = [];
            
            // Set basic permissions based on role
            switch ($roleName) {
                case 'Admin support':
                    $basicPermissions = [
                        ['permission_key' => 'manage_users', 'permission_name' => 'Manage Users'],
                        ['permission_key' => 'manage_roles', 'permission_name' => 'Manage Roles'],
                        ['permission_key' => 'view_reports', 'permission_name' => 'View Reports']
                    ];
                    break;
                case 'Admin':
                    $basicPermissions = [
                        ['permission_key' => 'view_reports', 'permission_name' => 'View Reports']
                    ];
                    break;
                case 'User':
                case 'Instructor':
                    $basicPermissions = [
                        ['permission_key' => 'view_profile', 'permission_name' => 'View Profile']
                    ];
                    break;
            }
            
            $_SESSION['permissions'] = $basicPermissions;
        }
    }
}

/**
 * Get dashboard navigation based on user permissions
 */
function getDashboardNavigation($acc_id = null) {
    $permissions = getUserEffectivePermissions($acc_id);
    $role = getUserRole($acc_id);
    $roleName = $role['role_name'] ?? 'Unknown';
    
    $navigation = [
        'overview' => [
            'icon' => 'bi-speedometer2',
            'label' => 'Overview',
            'href' => '#overview',
            'show' => true
        ]
    ];
    
    // Role-specific navigation
    switch ($roleName) {
        case 'Admin support':
            $navigation = array_merge($navigation, [
                'users' => [
                    'icon' => 'bi-people',
                    'label' => 'User Management',
                    'href' => '#users',
                    'show' => hasPermission('manage_users', $acc_id)
                ],
                'settings' => [
                    'icon' => 'bi-gear',
                    'label' => 'System Settings',
                    'href' => '#settings',
                    'show' => hasPermission('manage_settings', $acc_id)
                ],
                'logs' => [
                    'icon' => 'bi-journal-text',
                    'label' => 'Activity Logs',
                    'href' => '#logs',
                    'show' => hasPermission('view_audit_logs', $acc_id)
                ],
                'reports' => [
                    'icon' => 'bi-bar-chart',
                    'label' => 'Reports',
                    'href' => '#reports',
                    'show' => hasPermission('view_reports', $acc_id)
                ],
                'schedules' => [
                    'icon' => 'bi-calendar-week',
                    'label' => 'Schedule Records',
                    'href' => '#schedules',
                    'show' => hasPermission('manage_schedules', $acc_id) || hasPermission('view_schedules', $acc_id)
                ],
                'profile' => [
                    'icon' => 'bi-person-gear',
                    'label' => 'Profile',
                    'href' => '#profile',
                    'show' => true
                ]
            ]);
            break;
            
        case 'Admin':
            $navigation = array_merge($navigation, [
                'schedules' => [
                    'icon' => 'bi-calendar-week',
                    'label' => 'Schedules',
                    'href' => '#schedules',
                    'show' => hasPermission('manage_schedules', $acc_id) || hasPermission('view_schedules', $acc_id) || hasPermission('approve_schedules', $acc_id)
                ],
                'subjects' => [
                    'icon' => 'bi-book',
                    'label' => 'Subjects',
                    'href' => '#subjects',
                    'show' => hasPermission('manage_curriculum', $acc_id) || hasPermission('manage_subjects', $acc_id)
                ],
                'rooms' => [
                    'icon' => 'bi-building',
                    'label' => 'Rooms',
                    'href' => '#rooms',
                    'show' => hasPermission('manage_rooms', $acc_id) || hasPermission('view_rooms', $acc_id) || hasPermission('approve_room_requests', $acc_id)
                ],
                'users' => [
                    'icon' => 'bi-people',
                    'label' => 'Users',
                    'href' => '#users',
                    'show' => hasPermission('manage_users', $acc_id)
                ],
                'logs' => [
                    'icon' => 'bi-journal-text',
                    'label' => 'Activity Logs',
                    'href' => '#logs',
                    'show' => hasPermission('view_audit_logs', $acc_id)
                ],
                'profile' => [
                    'icon' => 'bi-person-gear',
                    'label' => 'Profile',
                    'href' => '#profile',
                    'show' => true
                ]
            ]);
            break;
            
        case 'Moderator':
            // Same capability model as Department Admin (department-scoped in endpoints)
            $navigation = array_merge($navigation, [
                'schedules' => [
                    'icon' => 'bi-calendar-week',
                    'label' => 'Schedules',
                    'href' => '#schedules',
                    'show' => hasPermission('manage_schedules', $acc_id) || hasPermission('view_schedules', $acc_id) || hasPermission('approve_schedules', $acc_id) || hasPermission('assign_schedules', $acc_id)
                ],
                'subjects' => [
                    'icon' => 'bi-book',
                    'label' => 'Subjects',
                    'href' => '#subjects',
                    'show' => hasPermission('manage_curriculum', $acc_id) || hasPermission('manage_subjects', $acc_id)
                ],
                'rooms' => [
                    'icon' => 'bi-building',
                    'label' => 'Rooms',
                    'href' => '#rooms',
                    'show' => hasPermission('manage_rooms', $acc_id) || hasPermission('view_rooms', $acc_id) || hasPermission('approve_room_requests', $acc_id)
                ],
                'users' => [
                    'icon' => 'bi-people',
                    'label' => 'Users',
                    'href' => '#users',
                    'show' => hasPermission('manage_users', $acc_id) || hasPermission('view_users', $acc_id)
                ],
                'profile' => [
                    'icon' => 'bi-person-gear',
                    'label' => 'Profile',
                    'href' => '#profile',
                    'show' => true
                ]
            ]);
            break;
            
        case 'User':
        case 'Instructor':
            $navigation = array_merge($navigation, [
                'schedules' => [
                    'icon' => 'bi-calendar-week',
                    'label' => 'My Schedules',
                    'href' => '#schedules',
                    'show' => hasPermission('view_own_schedule', $acc_id) || hasPermission('view_schedules', $acc_id) || hasPermission('manage_schedules', $acc_id) || hasPermission('assign_schedules', $acc_id)
                ],
                'profile' => [
                    'icon' => 'bi-person-gear',
                    'label' => 'Profile',
                    'href' => '#profile',
                    'show' => true
                ]
            ]);
            break;
    }
    
    // Filter out items that shouldn't be shown
    return array_filter($navigation, function($item) {
        return $item['show'];
    });
}

/**
 * Get dashboard title based on user role
 */
function getDashboardTitle($acc_id = null) {
    $role = getUserRole($acc_id);
    $roleName = $role['role_name'] ?? 'User';
    
    switch ($roleName) {
        case 'Admin support':
            return 'Administrator Dashboard';
        case 'Admin':
            return 'Department Head Dashboard';
        case 'Moderator':
            return 'Moderator Dashboard';
        case 'User':
        case 'Instructor':
            return 'Instructor Dashboard';
        default:
            return 'User Dashboard';
    }
}

// ============================================
// DEPARTMENT SCOPING
// ============================================

/**
 * Check if user can access department data
 */
function canAccessDepartment($dept_id, $acc_id = null) {
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    // Admin Support can access all departments
    if (isAdminSupport($acc_id)) {
        return true;
    }
    
    // Admin can only access their own department
    if (isAdmin($acc_id)) {
        $userInfo = getUserInfo($acc_id);
        return $userInfo && $userInfo['dept_id'] == $dept_id;
    }
    
    // Other roles have no department access
    return false;
}

/**
 * Get user's accessible departments
 */
function getUserDepartments($acc_id = null) {
    if ($acc_id === null) {
        $acc_id = $_SESSION['acc_id'] ?? null;
    }
    
    // Admin Support can access all departments
    if (isAdminSupport($acc_id)) {
        global $conn;
        $stmt = $conn->prepare("SELECT dept_id, dept_name FROM department ORDER BY dept_name");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        $stmt->close();
        
        return $departments;
    }
    
    // Admin can only access their own department
    if (isAdmin($acc_id)) {
        $userInfo = getUserInfo($acc_id);
        if ($userInfo && $userInfo['dept_id']) {
            return [['dept_id' => $userInfo['dept_id'], 'dept_name' => $userInfo['dept_name']]];
        }
    }
    
    return [];
}

/**
 * Get default permissions for a specific role
 */
function getDefaultRolePermissions($roleId) {
    global $conn;
    
    if (!$roleId) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT p.id, COALESCE(p.permission_key, p.permission_name) as permission_key, p.permission_name, p.description
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
            ORDER BY p.permission_name
        ");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
        $stmt->close();
        
        return $permissions;
    } catch (Exception $e) {
        error_log("Failed to get default role permissions: " . $e->getMessage());
        return [];
    }
}
