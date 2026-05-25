<?php
/**
 * Permission Visibility System
 * Ensures permissions granted by Admin Support are immediately visible in user dashboards
 */

require_once __DIR__ . '/../auth/security_middleware.php';

/**
 * Get user's visible permissions with real-time updates
 */
function getVisiblePermissions($acc_id = null) {
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
    
    // Get role-based permissions (real-time)
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(p.permission_key, p.permission_name) as permission_key, 
                   p.permission_name, p.permission_display_name, p.module, p.description
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_key']] = [
                'name' => $row['permission_name'],
                'display_name' => $row['permission_display_name'],
                'module' => $row['module'],
                'description' => $row['description'],
                'granted' => true,
                'source' => 'role'
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Permission visibility error: " . $e->getMessage());
        return [];
    }
    
    // Apply user-specific overrides (if any)
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(p.permission_key, p.permission_name) as permission_key, 
                   p.permission_name, p.permission_display_name, p.module, p.description, up.allowed
            FROM user_permissions up
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE up.acc_id = ?
        ");
        $stmt->bind_param("i", $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_key']] = [
                'name' => $row['permission_name'],
                'display_name' => $row['permission_display_name'],
                'module' => $row['module'],
                'description' => $row['description'],
                'granted' => (bool)$row['allowed'],
                'source' => 'user_override'
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        // user_permissions table might not exist, continue with role permissions only
        error_log("User permissions table access failed: " . $e->getMessage());
    }
    
    return $permissions;
}

/**
 * Check if user has a specific permission (real-time check)
 */
function hasVisiblePermission($permissionKey, $acc_id = null) {
    $permissions = getVisiblePermissions($acc_id);
    return isset($permissions[$permissionKey]) && $permissions[$permissionKey]['granted'] === true;
}

/**
 * Get dashboard navigation based on visible permissions
 */
function getVisibleNavigation($acc_id = null) {
    $permissions = getVisiblePermissions($acc_id);
    $role = getUserRole($acc_id);
    $roleName = $role['role_name'] ?? 'Unknown';
    
    $navigation = [
        'overview' => [
            'icon' => 'bi-speedometer2',
            'label' => 'Overview',
            'href' => '#overview',
            'show' => true,
            'description' => 'Dashboard overview and statistics'
        ]
    ];
    
    // Dynamic navigation based on permissions
    if (hasVisiblePermission('manage_schedules', $acc_id) || hasVisiblePermission('assign_schedules', $acc_id)) {
        $navigation['schedules'] = [
            'icon' => 'bi-calendar-week',
            'label' => 'Schedules',
            'href' => '#schedules',
            'show' => true,
            'description' => 'Manage class schedules and assignments'
        ];
    }
    
    if (hasVisiblePermission('manage_subjects', $acc_id) || hasVisiblePermission('manage_curriculum', $acc_id)) {
        $navigation['subjects'] = [
            'icon' => 'bi-book',
            'label' => 'Subjects',
            'href' => '#subjects',
            'show' => true,
            'description' => 'Manage subjects and course offerings'
        ];
    }
    
    if (hasVisiblePermission('manage_rooms', $acc_id) || hasVisiblePermission('view_rooms', $acc_id) || hasVisiblePermission('approve_room_requests', $acc_id)) {
        $navigation['rooms'] = [
            'icon' => 'bi-building',
            'label' => 'Rooms',
            'href' => '#rooms',
            'show' => true,
            'description' => 'Manage rooms and building assignments'
        ];
    }
    
    if (hasVisiblePermission('manage_users', $acc_id)) {
        $navigation['users'] = [
            'icon' => 'bi-people',
            'label' => 'Users',
            'href' => '#users',
            'show' => true,
            'description' => 'Manage user accounts and roles'
        ];
    }
    
    if (hasVisiblePermission('view_audit_logs', $acc_id)) {
        $navigation['logs'] = [
            'icon' => 'bi-journal-text',
            'label' => 'Activity Logs',
            'href' => '#logs',
            'show' => true,
            'description' => 'View system activity and audit logs'
        ];
    }
    
    if (hasVisiblePermission('manage_roles', $acc_id)) {
        $navigation['roles'] = [
            'icon' => 'bi-shield-check',
            'label' => 'Role Management',
            'href' => '#roles',
            'show' => true,
            'description' => 'Manage user roles and permissions'
        ];
    }
    
    // Always show profile
    $navigation['profile'] = [
        'icon' => 'bi-person-gear',
        'label' => 'Profile',
        'href' => '#profile',
        'show' => true,
        'description' => 'Manage your profile and account settings'
    ];
    
    // Filter out items that shouldn't be shown
    return array_filter($navigation, function($item) {
        return $item['show'];
    });
}

/**
 * Get dashboard components based on visible permissions
 */
function getVisibleComponents($acc_id = null) {
    $permissions = getVisiblePermissions($acc_id);
    $components = [];
    
    // Schedule Management
    if (hasVisiblePermission('manage_schedules', $acc_id) || hasVisiblePermission('assign_schedules', $acc_id)) {
        $components['schedule_management'] = [
            'file' => 'views/components/schedule_management.php',
            'title' => 'Schedule Management',
            'description' => 'Manage class schedules and assignments',
            'permissions' => ['manage_schedules', 'assign_schedules'],
            'icon' => 'bi-calendar-week',
            'color' => 'primary'
        ];
    }
    
    // Subject Management
    if (hasVisiblePermission('manage_subjects', $acc_id) || hasVisiblePermission('manage_curriculum', $acc_id)) {
        $components['subject_management'] = [
            'file' => 'views/components/subject_management.php',
            'title' => 'Subject Management',
            'description' => 'Manage subjects and course offerings',
            'permissions' => ['manage_subjects', 'manage_curriculum'],
            'icon' => 'bi-book',
            'color' => 'success'
        ];
    }
    
    // Room Management
    if (hasVisiblePermission('manage_rooms', $acc_id) || hasVisiblePermission('view_rooms', $acc_id) || hasVisiblePermission('approve_room_requests', $acc_id)) {
        $components['room_management'] = [
            'file' => 'views/components/room_management.php',
            'title' => 'Room Management',
            'description' => 'Manage rooms and building assignments',
            'permissions' => ['manage_rooms', 'view_rooms', 'approve_room_requests'],
            'icon' => 'bi-building',
            'color' => 'info'
        ];
    }
    
    // User Management
    if (hasVisiblePermission('manage_users', $acc_id)) {
        $components['user_management'] = [
            'file' => 'views/components/user_management.php',
            'title' => 'User Management',
            'description' => 'Manage user accounts and roles',
            'permissions' => ['manage_users'],
            'icon' => 'bi-people',
            'color' => 'warning'
        ];
    }
    
    // Role Management
    if (hasVisiblePermission('manage_roles', $acc_id)) {
        $components['role_management'] = [
            'file' => 'views/components/role_management.php',
            'title' => 'Role Management',
            'description' => 'Manage user roles and permissions',
            'permissions' => ['manage_roles'],
            'icon' => 'bi-shield-check',
            'color' => 'danger'
        ];
    }
    
    return $components;
}

/**
 * Get permission status for dashboard display
 */
function getPermissionStatus($acc_id = null) {
    $permissions = getVisiblePermissions($acc_id);
    $grantedCount = count(array_filter($permissions, function($p) { return $p['granted']; }));
    $totalCount = count($permissions);
    
    return [
        'total_permissions' => $totalCount,
        'granted_permissions' => $grantedCount,
        'permission_percentage' => $totalCount > 0 ? round(($grantedCount / $totalCount) * 100, 1) : 0,
        'last_updated' => date('Y-m-d H:i:s')
    ];
}

/**
 * Notify user of permission changes
 */
function notifyPermissionChange($acc_id, $permission_key, $granted) {
    // Log the permission change notification
    logAdminAction($_SESSION['acc_id'] ?? 0, 'permission_change_notification', 
        "Permission '$permission_key' " . ($granted ? 'granted' : 'revoked') . " for user $acc_id");
    
    // You could add email notifications, push notifications, etc. here
    return true;
}

/**
 * Update all user dashboards when permissions change
 */
function updateAllDashboards($permission_key, $granted) {
    global $conn;
    
    try {
        // Get all users who might be affected by this permission change
        $stmt = $conn->prepare("
            SELECT DISTINCT a.acc_id, a.fname, a.lname, a.acc_email
            FROM account a
            INNER JOIN user_roles ur ON a.acc_id = ur.acc_id
            INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE p.permission_key = ? OR p.permission_name = ?
        ");
        $stmt->bind_param("ss", $permission_key, $permission_key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $affectedUsers = [];
        while ($user = $result->fetch_assoc()) {
            $affectedUsers[] = $user;
            // Notify each user of the permission change
            notifyPermissionChange($user['acc_id'], $permission_key, $granted);
        }
        $stmt->close();
        
        return $affectedUsers;
    } catch (Exception $e) {
        error_log("Dashboard update error: " . $e->getMessage());
        return [];
    }
}

?>
