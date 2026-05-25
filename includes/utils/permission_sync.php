<?php
/**
 * Permission Synchronization System
 * Handles dynamic permission updates between Admin Support and Admin dashboards
 */

require_once __DIR__ . '/../auth/security_middleware.php';

/**
 * Get user's current effective permissions with real-time updates
 */
function getEffectivePermissions($acc_id = null) {
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
                   p.permission_name, p.permission_display_name, p.module
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
                'granted' => true
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Permission sync error: " . $e->getMessage());
        return [];
    }
    
    // Apply user-specific overrides (if any)
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(p.permission_key, p.permission_name) as permission_key, 
                   p.permission_name, p.permission_display_name, p.module, up.allowed
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
                'granted' => (bool)$row['allowed']
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
function hasPermissionRealTime($permissionKey, $acc_id = null) {
    $permissions = getEffectivePermissions($acc_id);
    return isset($permissions[$permissionKey]) && $permissions[$permissionKey]['granted'] === true;
}

/**
 * Get dashboard navigation based on real-time permissions
 */
function getDynamicNavigation($acc_id = null) {
    $permissions = getEffectivePermissions($acc_id);
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
    
    // Dynamic navigation based on permissions
    if (hasPermissionRealTime('manage_schedules', $acc_id) || hasPermissionRealTime('assign_schedules', $acc_id)) {
        $navigation['schedules'] = [
            'icon' => 'bi-calendar-week',
            'label' => 'Schedules',
            'href' => '#schedules',
            'show' => true
        ];
    }
    
    if (hasPermissionRealTime('manage_subjects', $acc_id) || hasPermissionRealTime('manage_curriculum', $acc_id)) {
        $navigation['subjects'] = [
            'icon' => 'bi-book',
            'label' => 'Subjects',
            'href' => '#subjects',
            'show' => true
        ];
    }
    
    if (hasPermissionRealTime('manage_rooms', $acc_id) || hasPermissionRealTime('view_rooms', $acc_id) || hasPermissionRealTime('approve_room_requests', $acc_id)) {
        $navigation['rooms'] = [
            'icon' => 'bi-building',
            'label' => 'Rooms',
            'href' => '#rooms',
            'show' => true
        ];
    }
    
    if (hasPermissionRealTime('manage_users', $acc_id)) {
        $navigation['users'] = [
            'icon' => 'bi-people',
            'label' => 'Users',
            'href' => '#users',
            'show' => true
        ];
    }
    
    if (hasPermissionRealTime('view_audit_logs', $acc_id)) {
        $navigation['logs'] = [
            'icon' => 'bi-journal-text',
            'label' => 'Activity Logs',
            'href' => '#logs',
            'show' => true
        ];
    }
    
    // Always show profile
    $navigation['profile'] = [
        'icon' => 'bi-person-gear',
        'label' => 'Profile',
        'href' => '#profile',
        'show' => true
    ];
    
    // Filter out items that shouldn't be shown
    return array_filter($navigation, function($item) {
        return $item['show'];
    });
}

/**
 * Get dashboard components based on permissions
 */
function getDashboardComponents($acc_id = null) {
    $permissions = getEffectivePermissions($acc_id);
    $components = [];
    
    // Schedule Management
    if (hasPermissionRealTime('manage_schedules', $acc_id) || hasPermissionRealTime('assign_schedules', $acc_id)) {
        $components['schedule_management'] = [
            'file' => 'views/components/schedule_management.php',
            'title' => 'Schedule Management',
            'description' => 'Manage class schedules and assignments',
            'permissions' => ['manage_schedules', 'assign_schedules']
        ];
    }
    
    // Subject Management
    if (hasPermissionRealTime('manage_subjects', $acc_id) || hasPermissionRealTime('manage_curriculum', $acc_id)) {
        $components['subject_management'] = [
            'file' => 'views/components/subject_management.php',
            'title' => 'Subject Management',
            'description' => 'Manage subjects and course offerings',
            'permissions' => ['manage_subjects', 'manage_curriculum']
        ];
    }
    
    // Room Management
    if (hasPermissionRealTime('manage_rooms', $acc_id) || hasPermissionRealTime('view_rooms', $acc_id) || hasPermissionRealTime('approve_room_requests', $acc_id)) {
        $components['room_management'] = [
            'file' => 'views/components/room_management.php',
            'title' => 'Room Management',
            'description' => 'Manage rooms and building assignments',
            'permissions' => ['manage_rooms', 'view_rooms', 'approve_room_requests']
        ];
    }
    
    // User Management
    if (hasPermissionRealTime('manage_users', $acc_id)) {
        $components['user_management'] = [
            'file' => 'views/components/user_management.php',
            'title' => 'User Management',
            'description' => 'Manage user accounts and roles',
            'permissions' => ['manage_users']
        ];
    }
    
    // Role Management
    if (hasPermissionRealTime('manage_roles', $acc_id)) {
        $components['role_management'] = [
            'file' => 'views/components/role_management.php',
            'title' => 'Role Management',
            'description' => 'Manage user roles and permissions',
            'permissions' => ['manage_roles']
        ];
    }
    
    return $components;
}

/**
 * Sync permissions when Admin Support updates them
 */
function syncPermissions($targetUserId, $permissions = []) {
    global $conn;
    
    try {
        // Clear existing user-specific permissions
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE acc_id = ?");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $stmt->close();
        
        // Insert new permissions
        if (!empty($permissions)) {
            $stmt = $conn->prepare("INSERT INTO user_permissions (acc_id, permission_id, allowed) VALUES (?, ?, ?)");
            foreach ($permissions as $permissionId => $allowed) {
                $stmt->bind_param("iii", $targetUserId, $permissionId, $allowed);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Permission sync failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get permission status for a specific user
 */
function getUserPermissionStatus($acc_id) {
    $permissions = getEffectivePermissions($acc_id);
    $status = [
        'user_id' => $acc_id,
        'permissions' => $permissions,
        'last_updated' => date('Y-m-d H:i:s'),
        'total_permissions' => count($permissions),
        'granted_permissions' => count(array_filter($permissions, function($p) { return $p['granted']; }))
    ];
    
    return $status;
}
?>

