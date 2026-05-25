<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';
require_once '../../includes/utils/permission_sync.php';

/**
 * Trigger permission synchronization for a user
 */
function triggerPermissionSync($user_id) {
    // This function can be extended to send real-time notifications
    // For now, we'll just log the sync trigger
    logAdminAction($_SESSION['acc_id'], 'trigger_sync', "Permission sync triggered for user ID: $user_id");
    
    // You could add WebSocket notifications or other real-time mechanisms here
    return true;
}

/**
 * Get module icon for display
 */
function getModuleIcon($module) {
    $icons = [
        'user' => 'person',
        'schedule' => 'calendar-week',
        'room' => 'building',
        'subject' => 'book',
        'report' => 'file-earmark-text',
        'audit' => 'journal-text',
        'role' => 'shield-check'
    ];
    return $icons[$module] ?? 'gear';
}

/**
 * Get role description
 */
function getRoleDescription($roleName) {
    $descriptions = [
        'Admin support' => 'Full system access and management',
        'Admin' => 'Department-level administration',
        'Moderator' => 'Content and user moderation',
        'Instructor' => 'Teaching and course management'
    ];
    return $descriptions[$roleName] ?? 'System role';
}

/**
 * Get module description
 */
function getModuleDescription($module) {
    $descriptions = [
        'user' => 'User account management and access control',
        'schedule' => 'Class scheduling and timetable management',
        'room' => 'Room allocation and facility management',
        'subject' => 'Course and curriculum management',
        'report' => 'Analytics and reporting tools',
        'audit' => 'System logs and activity tracking',
        'role' => 'Role and permission management'
    ];
    return $descriptions[$module] ?? 'System module';
}

/**
 * Get permission level
 */
function getPermissionLevel($permissionKey) {
    $levels = [
        'manage_users' => 'Admin',
        'manage_curriculum' => 'Admin',
        'assign_schedules' => 'Admin',
        'approve_room_requests' => 'Moderator',
        'view_reports' => 'User',
        'manage_rooms' => 'Admin',
        'manage_subjects' => 'Admin',
        'manage_roles' => 'Admin Support'
    ];
    return $levels[$permissionKey] ?? 'Standard';
}

/**
 * Get role level
 */
function getRoleLevel($roleName) {
    $levels = [
        'Admin support' => 'Level 4 - Full Access',
        'Admin' => 'Level 3 - Department Admin',
        'Moderator' => 'Level 2 - Content Moderator',
        'Instructor' => 'Level 1 - Teaching Staff'
    ];
    return $levels[$roleName] ?? 'Level 0 - Basic';
}

/**
 * Update all user dashboards when permissions change
 */
function updateAllDashboards($permission_key, $granted) {
    global $conn;
    
    try {
        // Get all users who might be affected by this permission change
        $stmt = $conn->prepare("
            SELECT DISTINCT a.acc_id, a.fname, a.lname, a.acc_email, r.role_name
            FROM account a
            INNER JOIN user_roles ur ON a.acc_id = ur.acc_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE a.acc_status = 'Active'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $affectedUsers = [];
        while ($user = $result->fetch_assoc()) {
            $affectedUsers[] = $user;
            // Log the dashboard update notification
            logAdminAction($_SESSION['acc_id'], 'dashboard_update', 
                "Dashboard updated for {$user['role_name']}: {$user['fname']} {$user['lname']}");
        }
        $stmt->close();
        
        return $affectedUsers;
    } catch (Exception $e) {
        error_log("Dashboard update error: " . $e->getMessage());
        return [];
    }
}

// Check if user is logged in and has admin support role
requireRole('Admin support', '../../public/index.php');

// Check for success message from redirect
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Role permissions updated successfully!";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "CSRF token mismatch";
    } else {
        // Role permission updates are now handled in dashboard.php to prevent headers already sent error
        
        // Handle user role assignment
        if (isset($_POST['assign_user_role'])) {
            $user_id = intval($_POST['user_id']);
            $new_role_id = intval($_POST['new_role_id']);
            
            if ($user_id > 0 && $new_role_id > 0) {
                $conn->begin_transaction();
                try {
                    // Check if user already has a role
                    $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE acc_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Update existing role
                        $stmt = $conn->prepare("UPDATE user_roles SET role_id = ? WHERE acc_id = ?");
                        $stmt->bind_param("ii", $new_role_id, $user_id);
                        $stmt->execute();
                    } else {
                        // Insert new role assignment
                        $stmt = $conn->prepare("INSERT INTO user_roles (acc_id, role_id) VALUES (?, ?)");
                        $stmt->bind_param("ii", $user_id, $new_role_id);
                        $stmt->execute();
                    }
                    $stmt->close();
                    
                    $conn->commit();
                    $success_message = "User role updated successfully!";
                    
                    // Log the action
                    $userInfo = getUserInfo($user_id);
                    $userName = $userInfo ? $userInfo['fname'] . ' ' . $userInfo['lname'] : "User ID $user_id";
                    logAdminAction($_SESSION['acc_id'], 'assign_user_role', "Assigned new role to user: $userName");
                    
                    // Trigger permission sync for the user
                    triggerPermissionSync($user_id);
                    
                    // Update all affected dashboards
                    updateAllDashboards('role_assignment', true);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Failed to update user role: " . $e->getMessage();
                }
            }
        }
        
        // Handle permission synchronization
        if (isset($_POST['sync_user_permissions'])) {
            $user_id = intval($_POST['user_id']);
            $permissions = $_POST['permissions'] ?? [];
            
            if ($user_id > 0) {
                $conn->begin_transaction();
                try {
                    // Clear existing user permissions
                    $stmt = $conn->prepare("DELETE FROM user_permissions WHERE acc_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Insert new permissions
                    if (!empty($permissions)) {
                        $stmt = $conn->prepare("INSERT INTO user_permissions (acc_id, permission_id, allowed) VALUES (?, ?, ?)");
                        foreach ($permissions as $permission_id => $allowed) {
                            $stmt->bind_param("iii", $user_id, $permission_id, $allowed);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }
                    
                    $conn->commit();
                    $success_message = "User permissions synchronized successfully!";
                    
                    // Log the action
                    $userInfo = getUserInfo($user_id);
                    $userName = $userInfo ? $userInfo['fname'] . ' ' . $userInfo['lname'] : "User ID $user_id";
                    logAdminAction($_SESSION['acc_id'], 'sync_permissions', "Synchronized permissions for user: $userName");
                    
                    // Trigger permission sync
                    triggerPermissionSync($user_id);
                    
                    // Update all affected dashboards
                    updateAllDashboards('permission_sync', true);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Failed to sync permissions: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch roles
$roles = $conn->query("SELECT * FROM roles ORDER BY id");

// Fetch permissions grouped by module
$permissions = [];
$permResult = $conn->query("SELECT * FROM permissions ORDER BY module, permission_display_name");
while ($perm = $permResult->fetch_assoc()) {
    $permissions[$perm['module']][] = $perm;
}

// Fetch role-permission mapping
$rolePermissions = [];
$rpResult = $conn->query("SELECT * FROM role_permissions");
while ($rp = $rpResult->fetch_assoc()) {
    $rolePermissions[$rp['role_id']][] = $rp['permission_id'];
}

// Fetch all users with their current roles
$users = [];
$userResult = $conn->query("
    SELECT a.acc_id, a.fname, a.lname, a.acc_user, a.acc_email, a.acc_status, r.role_name, r.id as role_id, d.dept_name
    FROM account a
    LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN department d ON a.dept_id = d.dept_id
    ORDER BY a.fname, a.lname
");
while ($user = $userResult->fetch_assoc()) {
    $users[] = $user;
}

// Get user data for header (fname, lname, role, username)
$user_query = $conn->prepare("
    SELECT a.fname, a.lname, a.acc_user, r.role_name
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE a.acc_id = ?
    LIMIT 1
");
$user_query->bind_param("i", $_SESSION['acc_id']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_row = $user_result->fetch_assoc();
$fname = $user_row ? $user_row['fname'] : "Unknown";
$lname = $user_row ? $user_row['lname'] : "";
$role = $user_row ? $user_row['role_name'] : "Unknown";
$username = $user_row ? ($user_row['fname'] . " " . $user_row['lname']) : "User";
$user_query->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVSU-OCC Scheduling System - Manage Roles</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../public/assets/css/design-system.css">
  <link rel="stylesheet" href="../../public/assets/css/main.css">
  <link rel="stylesheet" href="../../public/assets/css/admin.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="../../public/assets/css/sweetalert2.min.css">
  <link rel="stylesheet" href="/assets/css/admin_support.css">
  <style>
    body { font-family: 'Poppins', Arial, sans-serif; background:#f8f9fa; margin:0; padding:0; }
    .navbar { z-index:1100; }
    
    /* Hide sidebars and navbar when page is loaded in an iframe */
    html.iframe-mode .sidebar,
    body.iframe-mode .sidebar,
    html.iframe-mode .sidebar-offcanvas,
    body.iframe-mode .sidebar-offcanvas,
    html.iframe-mode .navbar,
    body.iframe-mode .navbar {
      display: none !important;
      visibility: hidden !important;
      opacity: 0 !important;
      pointer-events: none !important;
    }
    
    html.iframe-mode .main-content,
    body.iframe-mode .main-content {
      margin-left: 0 !important;
      padding-top: 1rem !important;
      padding-bottom: 1rem !important;
    }
    
    html.iframe-mode .footer,
    body.iframe-mode .footer {
      display: none !important;
    }
    
    .sidebar { background:#800000; color:#fff; min-height:100vh; position:fixed; top:70px; left:0; width:280px; padding-top:0.5rem; padding-bottom:0.5rem; z-index:1090; overflow-y:auto; max-height:calc(100vh - 70px); }
    .sidebar .nav-link { color:#fff !important; font-weight:500; padding:0.4rem 0.8rem; margin:0.05rem 0.4rem; border-radius:6px; display:flex; align-items:center; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar .nav-link .bi { margin-right:0.5rem; font-size:1rem; width:16px; text-align:center; flex-shrink:0; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background:rgba(255,255,255,0.15); color:#fff !important; }
    .sidebar .nav-section { margin-bottom:0.5rem; }
    .sidebar .nav-section small { margin-bottom:0.25rem; padding:0.25rem 0.8rem; }
    .sidebar hr { margin:0.5rem 0; }
    .sidebar-offcanvas { background:#800000 !important; color:#fff !important; width:280px; max-height:100vh; overflow-y:auto; }
    .sidebar-offcanvas .nav-link { color:#fff !important; font-weight:500; padding:0.4rem 0.8rem; margin:0.05rem 0.4rem; border-radius:6px; display:flex; align-items:center; }
    .sidebar-offcanvas .nav-link .bi { margin-right:0.5rem; font-size:1rem; width:16px; text-align:center; flex-shrink:0; }
    .sidebar-offcanvas .nav-link.active, .sidebar-offcanvas .nav-link:hover { background:rgba(255,255,255,0.15) !important; color:#fff !important; }
    .main-content { margin-left:280px; padding-top:90px; padding-bottom:80px; min-height:calc(100vh - 170px); background:#f8f9fa; }
    .dashboard-card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); background:#ffffff; margin-bottom:2rem; padding:1.5rem; min-height:auto; border:1px solid rgba(0, 0, 0, 0.05); position:relative; overflow:hidden; }
    .dashboard-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .dashboard-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #800000, #f5f5dc); }
    .dashboard-card h5 { color:#495057; font-weight:600; font-size:1.125rem; margin-bottom:0.75rem; }
    .dashboard-card h6 { color:#495057; font-weight:600; font-size:1rem; margin-bottom:0.5rem; }
    .dashboard-card p { color:#6c757d; font-size:0.875rem; line-height:1.5; margin-bottom:0.5rem; }
    .stats-card { background:#ffffff; border-left:4px solid #800000; border-radius:12px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid rgba(0, 0, 0, 0.05); }
    .stats-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .stats-card .stat-number { color:#800000; font-size:2.5rem; font-weight:800; line-height:1; background:linear-gradient(135deg, #800000, #f5f5dc); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .stats-card .stat-label { color:#6c757d; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; }
    .btn-maroon { background:linear-gradient(135deg, #800000 0%, #660000 100%); color:#fff; border:none; font-weight:600; border-radius:8px; }
    .btn-maroon:hover { background:linear-gradient(135deg, #660000 0%, #800000 100%); color:#fff; box-shadow:0 4px 16px rgba(0,0,0,0.12); }
    .footer { background-color:#800000; color:#fff; text-align:center; width:100%; position:fixed; left:0; bottom:0; font-size:0.9rem; padding:0.5rem 0; z-index:1080; }
    .bg-maroon { background-color:#800000 !important; }
    .modal-body { padding:1.75rem 1.5rem; color:#212529; background:#fff; }
    .modal-footer { padding:1.25rem 1.5rem; border-top:1px solid #dee2e6; background:#f8f9fa; border-radius:0 0 16px 16px; gap:0.75rem; }
    
    @media (max-width: 1200px) {
      .sidebar { width:260px; }
      .main-content { margin-left:260px; }
    }
    @media (max-width: 991.98px) {
      .sidebar { display:none !important; }
      .main-content { 
        margin-left:0 !important; 
        padding-top:60px !important; /* Reduced from 90px to match smaller navbar (50px navbar + 10px buffer) */ 
        padding-bottom:80px !important; 
        min-height:calc(100vh - 170px) !important; 
      }
      .dashboard-card { padding:1rem; margin-bottom:1.5rem; min-height:unset; }
      .container-fluid { padding-left: 1rem !important; padding-right: 1rem !important; }
    }
    
    /* Ensure SweetAlert2 dialogs appear centered and above all content */
    .swal2-container {
      z-index: 10000 !important;
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    
    .swal2-popup {
      z-index: 10001 !important;
      position: relative !important;
      margin: 0 !important;
      max-width: 500px !important;
      border-radius: 0.5rem !important;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
    }
    
    .swal2-backdrop-show {
      background-color: rgba(0, 0, 0, 0.4) !important;
    }
    
    /* Ensure proper SweetAlert2 styling */
    .swal2-title {
      font-size: 1.5rem !important;
      font-weight: 600 !important;
      color: #212529 !important;
      margin-bottom: 0.75rem !important;
    }
    
    .swal2-content {
      font-size: 1rem !important;
      color: #6c757d !important;
      line-height: 1.5 !important;
    }
    
    .swal2-actions {
      margin-top: 1.5rem !important;
      gap: 0.5rem !important;
    }
    
    .swal2-confirm {
      background-color: #800000 !important;
      border-color: #800000 !important;
      border-radius: 0.375rem !important;
      padding: 0.625rem 1.5rem !important;
      font-weight: 500 !important;
    }
    
    .swal2-confirm:hover {
      background-color: #660000 !important;
      border-color: #660000 !important;
    }
    
    .swal2-cancel {
      background-color: #6c757d !important;
      border-color: #6c757d !important;
      border-radius: 0.375rem !important;
      padding: 0.625rem 1.5rem !important;
      font-weight: 500 !important;
    }
    
    .swal2-cancel:hover {
      background-color: #5a6268 !important;
      border-color: #5a6268 !important;
    }
  </style>
</head>
<body class="as-support-dashboard">
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top as-navbar">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
        <img src="../../public/assets/img/evsu-logo.png" alt="EVSU Logo" width="30" height="30" class="me-2">
        <span class="fw-bold">EVSU Scheduling System</span>
      </a>
      <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <span class="navbar-toggler-icon"></span>
      </button>
      <span class="navbar-text ms-auto fw-semibold text-white d-none d-lg-block">Administrator Dashboard</span>
    </div>
  </nav>

  <!-- Mobile Offcanvas Sidebar -->
  <div class="offcanvas offcanvas-start sidebar-offcanvas as-sidebar d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="sidebarLabel">Menu</h5>
      <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-3">
      <nav class="nav flex-column">
        <!-- Dashboard Section -->
        <div class="nav-section mb-3">
          <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Dashboard</small>
          <a class="nav-link" href="dashboard.php" onclick="navigateToDashboardTab('overview'); closeOffcanvas(); return false;">
            <span class="bi bi-speedometer2 me-2"></span> Overview
          </a>
        </div>
        
        <!-- User Management Section -->
        <div class="nav-section mb-3">
          <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">User Management</small>
          <a class="nav-link" href="user_management.php" onclick="closeOffcanvas(); return false;">
            <span class="bi bi-people me-2"></span> User Management
          </a>
          <a class="nav-link active" href="manage_roles.php" onclick="closeOffcanvas(); return false;">
            <span class="bi bi-gear me-2"></span> Manage Roles
          </a>
        </div>
        
        
        <!-- Divider -->
        <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
        
        <!-- Account Section -->
        <div class="nav-section">
          <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <span class="bi bi-box-arrow-right me-2"></span> Logout
          </a>
        </div>
      </nav>
    </div>
  </div>

  <!-- Desktop Sidebar -->
  <div class="sidebar as-sidebar d-none d-lg-flex flex-column">
    <div class="text-center mb-4">
      <span class="bi bi-person-circle" style="font-size:3rem; color:#fff;"></span>
      <div class="fw-semibold mt-2"><?= htmlspecialchars($username) ?></div>
    </div>
    <nav class="nav flex-column px-3">
      <!-- Dashboard Section -->
      <div class="nav-section mb-3">
        <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Dashboard</small>
        <a class="nav-link" href="dashboard.php" onclick="navigateToDashboardTab('overview'); return false;">
          <span class="bi bi-speedometer2 me-2"></span> Overview
        </a>
      </div>
      
      <!-- User Management Section -->
      <div class="nav-section mb-3">
        <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">User Management</small>
        <a class="nav-link" href="user_management.php">
          <span class="bi bi-people me-2"></span> User Management
        </a>
        <a class="nav-link active" href="manage_roles.php">
          <span class="bi bi-gear me-2"></span> Manage Roles
        </a>
      </div>
      
      
      <!-- Divider -->
      <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
      
      <!-- Account Section -->
      <div class="nav-section">
        <a class="nav-link" href="#logoutModal" data-bs-toggle="modal" data-bs-target="#logoutModal">
          <span class="bi bi-box-arrow-right me-2"></span> Logout
        </a>
      </div>
    </nav>
  </div>

  <!-- Main Content -->
  <main class="main-content as-main">
    <div class="container-fluid">

<!-- Enhanced Interface with Better Styling -->
<style>
/* Match Admin Dashboard Container */
.role-management-container {
    background: #ffffff;
    min-height: 100vh;
    padding: 2rem 0;
}

.manage-roles-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.role-header {
    background: #ffffff;
    color: #1a1a1a;
    padding: 0;
    border-radius: 0;
    margin-bottom: 0;
    box-shadow: none;
    border: none;
}

/* Match Admin Dashboard Typography */
.role-header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
}

.role-header p {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0;
}

/* Match Admin Dashboard Card Design */
.enhanced-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
    overflow: hidden;
    transition: transform 0.2s ease;
}

.enhanced-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.enhanced-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 1.25rem 1.5rem;
    margin-bottom: 0;
    font-weight: 600;
    font-size: 1rem;
    color: #495057;
    border-radius: 12px 12px 0 0;
}

.enhanced-card .card-body {
    padding: 1.5rem;
}

.permission-matrix {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
    margin: 1rem 0;
}

.permission-matrix table {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.permission-matrix thead th {
    background: linear-gradient(135deg, #800000, #a00000);
    color: white;
    border: none;
    padding: 1.25rem 0.75rem;
    font-weight: 600;
    text-align: center;
    vertical-align: middle;
    position: relative;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}

.permission-matrix thead th:first-child {
    border-top-left-radius: 15px;
    text-align: left;
    padding-left: 1.5rem;
}

.permission-matrix thead th:last-child {
    border-top-right-radius: 15px;
}

.permission-matrix thead th:not(:first-child) {
    border-left: 1px solid rgba(255, 255, 255, 0.2);
}

.permission-matrix tbody td {
    padding: 1rem 0.75rem;
    border: 1px solid #f1f3f4;
    text-align: center;
    vertical-align: middle;
    transition: all 0.3s ease;
    position: relative;
}

.permission-matrix tbody td:first-child {
    text-align: left;
    padding-left: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-left: 4px solid #800000;
    font-weight: 500;
}

.permission-matrix tbody td:not(:first-child) {
    border-left: 1px solid #e9ecef;
}

.permission-matrix tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f1f3f4;
}

.permission-matrix tbody tr:nth-child(even) {
    background-color: #fafbfc;
}

.permission-matrix tbody tr:hover {
    background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.permission-matrix tbody tr:hover td:first-child {
    background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
    border-left-color: #1976d2;
}

/* Enhanced form switches */
.permission-matrix .form-check-input {
    width: 2.5rem;
    height: 1.25rem;
    border-radius: 1rem;
    background-color: #dee2e6;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.permission-matrix .form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.permission-matrix .form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
}

.permission-matrix .form-check-input:not(:checked) {
    background-color: #dc3545;
}

/* Module header styling */
.module-header {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    padding: 0.75rem;
    font-weight: 600;
    text-align: center;
    border-radius: 8px 8px 0 0;
    margin-bottom: 0;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.module-header i {
    font-size: 1.1rem;
    margin-right: 0.5rem;
}

/* Permission column headers */
.permission-header {
    background: transparent;
    color: white;
    padding: 0.75rem 0.5rem;
    font-weight: 500;
    text-align: center;
    font-size: 0.8rem;
    border-left: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 120px;
}

/* Role cell styling */
.role-cell {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-left: 4px solid #800000;
    padding: 1rem 1.5rem;
    font-weight: 500;
    min-width: 200px;
}

.role-cell .role-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #800000, #a00000);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1rem;
    margin-right: 1rem;
    box-shadow: 0 2px 8px rgba(128, 0, 0, 0.3);
}

.role-cell .role-info {
    flex: 1;
}

.role-cell .role-name {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.role-cell .role-description {
    font-size: 0.8rem;
    color: #6c757d;
    margin: 0;
}

/* Permission cell styling */
.permission-cell {
    text-align: center;
    padding: 1rem 0.5rem;
    border-left: 1px solid #e9ecef;
    background: white;
    transition: all 0.3s ease;
}

.permission-cell:hover {
    background: #f8f9fa;
}

/* Enhanced switch container */
.switch-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0.5rem;
}

/* Enhanced visual effects */
.permission-matrix::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #800000, #a00000, #800000);
    border-radius: 15px 15px 0 0;
}

.permission-matrix {
    position: relative;
}

/* Enhanced switch animations */
.permission-matrix .form-check-input {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: scale(1);
}

.permission-matrix .form-check-input:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.permission-matrix .form-check-input:active {
    transform: scale(0.95);
}

/* Enhanced row hover effects */
.permission-matrix tbody tr {
    position: relative;
    overflow: hidden;
}

.permission-matrix tbody tr::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.5s;
}

.permission-matrix tbody tr:hover::before {
    left: 100%;
}

/* Enhanced module headers */
.module-header {
    position: relative;
    overflow: hidden;
}

.module-header::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.module-header:hover::after {
    left: 100%;
}

/* Enhanced permission headers */
.permission-header {
    position: relative;
    transition: all 0.3s ease;
}

.permission-header:hover {
    background: transparent;
    transform: translateY(-1px);
}

/* Enhanced role avatars */
.role-avatar {
    position: relative;
    overflow: hidden;
}

.role-avatar::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transform: rotate(45deg);
    transition: all 0.5s;
    opacity: 0;
}

.role-avatar:hover::before {
    opacity: 1;
    animation: shine 0.5s ease-in-out;
}

@keyframes shine {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

/* Enhanced save button area */
.permission-matrix + .d-flex {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border: 1px solid #e9ecef;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Enhanced buttons */
.btn-enhanced {
    position: relative;
    overflow: hidden;
}

.btn-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-enhanced:hover::before {
    left: 100%;
}

/* Modern Card-Based Permission Visualization */
/* Match Admin Dashboard Grid Container */
.modern-permission-grid {
    background: #ffffff;
    border-radius: 0;
    padding: 0;
    box-shadow: none;
    border: none;
    border-top: none;
}

/* Pagination Controls */
.pagination-controls {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background: transparent;
    padding: 0;
    border-radius: 0;
    border: none;
}

.pagination-text {
    font-weight: 500;
    color: #1a1a1a;
    font-size: 0.9375rem;
}

.pagination-role-info {
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 400;
    background: transparent;
    padding: 0;
    border-radius: 0;
    border: none;
}

.pagination-buttons {
    display: flex;
    gap: 0.5rem;
    background: transparent;
    padding: 0;
    border-radius: 0;
    border: none;
}

.pagination-buttons .btn {
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 0.625rem 1.25rem;
    border: 1px solid #d1d5db;
    color: #495057;
    background: #ffffff;
    transition: all 0.2s ease;
}

.pagination-buttons .btn:hover:not(:disabled) {
    background: #800000;
    color: white;
    border-color: #800000;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(128, 0, 0, 0.2);
}

.pagination-buttons .btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #f9fafb;
    color: #9ca3af;
    border-color: #e5e5e5;
}

/* Page Indicators */
.page-indicators {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
    padding: 0.75rem 0;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
}

.page-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #d1d5db;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.page-dot:hover {
    background: #9ca3af;
}

.page-dot.active {
    background: #800000;
    width: 24px;
    border-radius: 4px;
}

.roles-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-bottom: 2rem;
    min-height: 400px;
    position: relative;
}

.role-card {
    background: transparent;
    border-radius: 0;
    box-shadow: none;
    overflow: visible;
    transition: none;
    border: none;
    display: flex;
    flex-direction: column;
    min-height: auto;
    padding-bottom: 0;
    margin-bottom: 0;
}

/* Match Admin Dashboard Card Header Design */
.role-card-header {
    background: #ffffff;
    color: #1a1a1a;
    padding: 1.5rem;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 0;
    border-radius: 12px 12px 0 0;
    box-shadow: none;
}

.role-avatar-large {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: #800000;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    font-weight: 600;
    flex-shrink: 0;
}

.role-info {
    flex: 1;
}

/* Match Admin Dashboard Typography */
/* Match Admin Dashboard Typography */
.role-name {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
    letter-spacing: -0.01em;
}

/* Match Admin Dashboard Typography */
.role-description {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.role-level-badge {
    background: #eff6ff;
    color: #1e40af;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.role-actions {
    display: flex;
    gap: 0.5rem;
}

/* Tabs Container */
/* Match Admin Dashboard Tab Container Design */
.modules-tabs-container {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

/* Match Admin Dashboard Tab Navigation */
.modules-tabs-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    overflow-x: auto;
    scrollbar-width: thin;
}

.modules-tabs-nav::-webkit-scrollbar {
    height: 6px;
}

.modules-tabs-nav::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.modules-tabs-nav::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.module-tab-button {
    padding: 0.625rem 1.25rem;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #495057;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.module-tab-button:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #495057;
}

.module-tab-button.active {
    background: #800000;
    color: #ffffff;
    border-color: #800000;
}

/* Match Admin Dashboard Tab Content Design */
.module-tab-content {
    display: none;
    padding: 1.5rem;
    min-height: 400px;
    background: #ffffff;
}

.module-tab-content.active {
    display: block;
}

.modules-container {
    padding: 0;
    display: none;
}

.module-section {
    display: none;
}

/* Match Admin Dashboard Tab Header Design */
/* Match Admin Dashboard Tab Header */
.module-tab-header {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.module-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #800000;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.module-info {
    flex: 1;
}

/* Match Admin Dashboard Typography */
/* Match Admin Dashboard Typography */
.module-name {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #495057;
    letter-spacing: -0.01em;
}

.module-description {
    font-size: 0.875rem;
    color: #6c757d;
    margin: 0;
    font-weight: 400;
}

.module-toggle {
    display: flex;
    gap: 0.5rem;
}

/* Match Admin Dashboard Grid Design */
.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    padding: 0;
    max-height: none;
    overflow: visible;
}

@media (max-width: 768px) {
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}

/* Sticky Footer */
/* Match Admin Dashboard Footer Design */
.sticky-footer-actions {
    position: sticky;
    bottom: 0;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    padding: 1rem 1.5rem;
    margin-top: 2rem;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
    z-index: 100;
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

/* Match Admin Dashboard Card Design */
.permission-card {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.2s ease;
    position: relative;
    overflow: visible;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-height: auto;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.permission-card:last-child {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.permission-card:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.permission-card.permission-enabled {
    background: transparent;
    border-color: #e5e5e5;
}

.permission-card.permission-disabled {
    background: transparent;
    border-color: #e5e5e5;
    opacity: 1;
}

.permission-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    margin-bottom: 0;
    min-width: 0;
}

.permission-icon {
    display: none;
}

.permission-info {
    flex: 1;
    min-width: 0;
}

/* Match Admin Dashboard Typography */
.permission-name {
    font-size: 0.9375rem;
    font-weight: 600;
    margin-bottom: 0.125rem;
    color: #495057;
    line-height: 1.4;
}

.permission-key {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
    font-family: 'SF Mono', 'Monaco', 'Cascadia Code', 'Roboto Mono', monospace;
    font-weight: 400;
    line-height: 1.4;
}

.permission-level {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    margin-left: auto;
}

.permission-info {
    flex: 1;
    min-width: 0;
}

.permission-control {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    flex-shrink: 0;
    margin-right: 0.5rem;
}

.permission-switch {
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background-color: #d1d5db;
    border: none;
    transition: all 0.2s ease;
    cursor: pointer;
    flex-shrink: 0;
}

.permission-switch:checked {
    background-color: #10b981;
}

.permission-switch:focus {
    box-shadow: none !important;
    outline: none !important;
}

.permission-switch:checked:focus {
    box-shadow: none !important;
    outline: none !important;
}

.permission-switch:hover {
    box-shadow: none !important;
    outline: none !important;
}

/* Remove background highlight from toggle switches and wrappers */
.form-check.form-switch,
.form-check.form-switch:hover,
.form-check.form-switch:focus,
.form-check.form-switch:active,
.permission-control,
.permission-control:hover,
.permission-control:focus,
.permission-control:active {
    background: transparent !important;
    box-shadow: none !important;
}

/* Remove any wrapper highlights around toggles */
.permission-card:hover .permission-switch,
.permission-card.permission-enabled:hover .permission-switch,
.form-check.form-switch:hover .permission-switch {
    box-shadow: none !important;
    outline: none !important;
}

/* Additional specificity for form-check-input class */
.form-check-input.permission-switch:focus,
.form-check-input.permission-switch:active,
.form-check-input.permission-switch:hover,
.form-check-input.permission-switch:checked:focus,
.form-check-input.permission-switch:checked:active,
.form-check-input.permission-switch:checked:hover {
    box-shadow: none !important;
    outline: none !important;
}

/* Remove background highlight from toggle switches and wrappers */
.form-check.form-switch,
.form-check.form-switch:hover,
.form-check.form-switch:focus,
.form-check.form-switch:active,
.permission-control,
.permission-control:hover,
.permission-control:focus,
.permission-control:active {
    background: transparent !important;
    box-shadow: none !important;
}

/* Remove any wrapper highlights around toggles */
.permission-card:hover .permission-switch,
.permission-card.permission-enabled:hover .permission-switch,
.form-check.form-switch:hover .permission-switch {
    box-shadow: none !important;
    outline: none !important;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .role-card {
        flex-direction: column;
        min-height: auto;
    }
    
    .role-card-header {
        min-width: auto;
        flex-direction: row;
        justify-content: space-between;
    }
    
    .modules-container {
        flex-direction: column;
        overflow-x: visible;
    }
    
    .module-section {
        min-width: auto;
    }
}

@media (max-width: 768px) {
    .modern-permission-grid {
        padding: 1rem;
    }
    
    .role-card {
        flex-direction: column;
    }
    
    .role-card-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
        min-width: auto;
    }
    
    .role-actions {
        width: 100%;
        justify-content: center;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modules-container {
        flex-direction: column;
        overflow-x: visible;
    }
    
    .module-section {
        min-width: auto;
    }
    
    .module-header {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .permission-card {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .permission-header {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}

/* Match Admin Dashboard Card Design */
.user-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease;
}

.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #800000, #a00000);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
    margin-right: 1rem;
}

.btn-enhanced {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
}

.btn-enhanced:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stats-card {
    background: transparent;
    color: #1a1a1a;
    border-radius: 0;
    padding: 0;
    text-align: left;
    margin-bottom: 0;
}

.stats-card h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #800000;
}

.stats-card p {
    margin-bottom: 0;
    color: #6c757d;
    font-size: 0.875rem;
}

.sync-status {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.sync-status.synced {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.sync-status.pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.sync-status.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<!-- Manage Roles Content -->
<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5><i class="bi bi-shield-check me-2"></i>Role Management System</h5>
            <p class="text-muted mb-0">Manage user roles and permissions across the entire system with real-time synchronization</p>
        </div>
        <div class="stats-card" style="min-width: 150px; text-align: center;">
            <div class="stat-number" style="font-size: 2rem;"><?= count($users) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <span class="badge bg-maroon fs-6">Admin Support</span>
    </div>

<!-- Success/Error Messages -->
<?php if (isset($success_message)): ?>
<script>
Swal.fire({
    title: 'Success!',
    text: '<?= addslashes($success_message) ?>',
    icon: 'success',
    confirmButtonText: 'OK',
    confirmButtonColor: '#800000',
    timer: 3000,
    timerProgressBar: true,
    showConfirmButton: true
});
</script>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<script>
Swal.fire({
    title: 'Error!',
    text: '<?= addslashes($error_message) ?>',
    icon: 'error',
    confirmButtonText: 'OK',
    confirmButtonColor: '#dc3545',
    showConfirmButton: true
});
</script>
<?php endif; ?>

    <div class="row">
        <div class="col-12">
    
            <!-- Enhanced Role Permissions Management -->
            <div class="enhanced-card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-shield-lock me-2"></i>
                            <div>
                                <h6 class="mb-0">Role Permissions Matrix</h6>
                                <small class="opacity-75">Configure system access levels for each role</small>
                            </div>
                        </div>
                        <div class="text-white">
                            <small>Matrix View</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Configure permissions for each role. Changes will be applied system-wide and synchronized in real-time.
                    </p>

<form method="POST" action="dashboard.php" id="permissionsForm">
    <?= getCSRFTokenInput() ?>
    <input type="hidden" name="update_permissions" value="1">
                        <!-- Modern Card-Based Permission Visualization with Pagination -->
                        <div class="modern-permission-grid">
                            <!-- Pagination Info (Hidden - moved to role header) -->
                            <div class="pagination-controls" style="display: none;">
                                <div class="pagination-info">
                                    <div class="pagination-text">
                                        <i class="bi bi-file-earmark-text me-2"></i>
                                        Page <span id="currentPage" class="fw-bold text-primary">1</span> of <span id="totalPages" class="fw-bold">1</span>
                                    </div>
                                    <div class="pagination-role-info">
                                        <i class="bi bi-people me-2"></i>
                                        Showing Role <span id="currentRole" class="fw-bold text-primary">1</span> of <span id="totalRoles" class="fw-bold"><?= $roles->num_rows ?></span> Total Roles
                                    </div>
                                </div>
                            </div>

                            <!-- Role Cards Container -->
                            <div class="roles-container" id="rolesContainer">
                                <?php $roles->data_seek(0); // Reset pointer ?>
                                <?php $roleIndex = 0; ?>
                                <?php while ($role = $roles->fetch_assoc()): ?>
                                    <div class="role-card" data-role-id="<?= $role['id'] ?>" data-role-index="<?= $roleIndex ?>" style="display: none;">
                                        <!-- Role Header -->
                                        <div class="role-card-header">
                                            <div class="role-avatar-large">
                                                <?= strtoupper(substr($role['role_name'], 0, 2)) ?>
                                            </div>
                                            <div class="role-info">
                                                <h5 class="role-name">
                                                    <i class="bi bi-shield-check me-2"></i>
                                                    <?= htmlspecialchars($role['role_name']) ?>
                                                </h5>
                                                <p class="role-description">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <?= getRoleDescription($role['role_name']) ?>
                                                </p>
                                                <div class="d-flex align-items-center gap-2 mt-2">
                                                    <span class="role-level-badge">
                                                        <i class="bi bi-star me-1"></i>
                                                        <?= getRoleLevel($role['role_name']) ?>
                                                    </span>
                                                    <span style="color: #9ca3af; font-size: 0.8125rem;">
                                                        <i class="bi bi-clock me-1"></i>
                                                        Last updated: <?= date('M d, Y') ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="role-actions">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="changePage(-1)" id="prevPage" style="border-radius: 8px; font-weight: 500;">
                                                    <i class="bi bi-chevron-left me-1"></i> Previous
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="changePage(1)" id="nextPage" style="border-radius: 8px; font-weight: 500;">
                                                    Next <i class="bi bi-chevron-right ms-1"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Module Tabs -->
                                        <div class="modules-tabs-container">
                                            <!-- Tab Navigation -->
                                            <div class="modules-tabs-nav">
                                                <?php $moduleIndex = 0; ?>
                                                <?php foreach ($permissions as $module => $modulePermissions): ?>
                                                    <button type="button" 
                                                            class="module-tab-button <?= $moduleIndex === 0 ? 'active' : '' ?>" 
                                                            onclick="showModuleTab('<?= $module ?>', <?= $role['id'] ?>)"
                                                            data-module="<?= $module ?>">
                                                        <i class="bi bi-<?= getModuleIcon($module) ?>"></i>
                                                        <?= ucfirst($module) ?>
                                                    </button>
                                                    <?php $moduleIndex++; ?>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- Tab Contents -->
                                            <?php $moduleIndex = 0; ?>
                                            <?php foreach ($permissions as $module => $modulePermissions): ?>
                                                <div class="module-tab-content <?= $moduleIndex === 0 ? 'active' : '' ?>" id="tab-<?= $module ?>-<?= $role['id'] ?>" data-role-id="<?= $role['id'] ?>">
                                                    <div class="module-tab-header">
                                                        <div>
                                                            <h6 class="mb-1" style="font-size: 1rem; font-weight: 600; color: #495057;">
                                                                <?= ucfirst($module) ?> Module
                                                            </h6>
                                                            <p class="mb-0" style="font-size: 0.875rem; color: #6c757d;">
                                                                <?= getModuleDescription($module) ?>
                                                            </p>
                                                        </div>
                                                        <div class="module-toggle">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleModulePermissions(<?= $role['id'] ?>, '<?= $module ?>')" style="border-radius: 8px; font-weight: 500; border-color: #d1d5db; color: #495057;">
                                                                <i class="bi bi-check-circle me-1"></i> Enable All
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Permission Cards -->
                                                    <div class="permissions-grid">
                                                        <?php foreach ($modulePermissions as $perm): ?>
                                                            <?php $checked = in_array($perm['id'], $rolePermissions[$role['id']] ?? []) ? "checked" : ""; ?>
                                                            <div class="permission-card <?= $checked ? 'permission-enabled' : 'permission-disabled' ?>">
                                                                <div class="permission-control" style="flex-shrink: 0;">
                                                                    <div class="form-check form-switch mb-0">
                                                                        <input class="form-check-input permission-switch" 
                                                                               type="checkbox" 
                                                                               name="permissions[<?= $role['id'] ?>][]" 
                                                                               value="<?= $perm['id'] ?>" 
                                                                               <?= $checked ?>
                                                                               id="perm_<?= $role['id'] ?>_<?= $perm['id'] ?>"
                                                                               data-role-id="<?= $role['id'] ?>"
                                                                               data-module="<?= $module ?>"
                                                                               data-permission="<?= $perm['id'] ?>"
                                                                               onchange="updatePermissionCard(this)">
                                                                    </div>
                                                                </div>
                                                                <div class="permission-header flex-grow-1">
                                                                    <div class="permission-info">
                                                                        <h6 class="permission-name mb-0">
                                                                            <?= htmlspecialchars($perm['permission_display_name']) ?>
                                                                        </h6>
                                                                        <p class="permission-key mb-0" style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.125rem;">
                                                                            <?= htmlspecialchars($perm['permission_key']) ?>
                                                                        </p>
                                                                    </div>
                                                                    <div class="permission-level" style="flex-shrink: 0;">
                                                                        <?php 
                                                                        $level = getPermissionLevel($perm['permission_key']);
                                                                        if ($level === 'Admin' || $level === 'Admin Support') {
                                                                            $badgeClass = 'bg-danger';
                                                                        } elseif ($level === 'Standard') {
                                                                            $badgeClass = 'bg-primary';
                                                                        } else {
                                                                            $badgeClass = 'bg-info';
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?= $badgeClass ?>" style="font-size: 0.6875rem; font-weight: 500; padding: 0.25rem 0.625rem; border-radius: 12px;">
                                                                            <?= strtoupper($level) ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php $moduleIndex++; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php $roleIndex++; ?>
          <?php endwhile; ?>
                            </div>

                            <!-- Page Indicators -->
                            <div class="page-indicators" id="pageIndicators">
                                <!-- Page dots will be generated by JavaScript -->
                            </div>
                        </div>

                        <!-- Sticky Footer Actions -->
                        <div class="sticky-footer-actions">
                            <div class="d-flex align-items-center" style="color: #6c757d;">
                                <i class="bi bi-clock me-2"></i>
                                <div class="d-flex flex-column">
                                    <span class="fw-medium" style="font-size: 0.875rem;">Last Updated</span>
                                    <small style="font-size: 0.75rem;"><?= date('M d, Y H:i') ?></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllPermissions()" title="Select all permissions for all roles" style="border: 1px solid #d1d5db; color: #495057; border-radius: 8px; font-weight: 500;">
                                    <i class="bi bi-check-all me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllPermissions()" title="Clear all permissions for all roles" style="border: 1px solid #d1d5db; color: #495057; border-radius: 8px; font-weight: 500;">
                                    <i class="bi bi-x-square me-1"></i>Clear All
                                </button>
                                <button type="submit" name="update_permissions" class="btn btn-primary btn-sm" title="Save all permission changes" style="background: #800000; border-color: #800000; border-radius: 8px; font-weight: 500; padding: 0.625rem 1.5rem;">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

            <!-- User Role Assignment - Table Format -->
            <div class="dashboard-card mt-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-0 fw-bold" style="color: #495057;">
                                <i class="bi bi-people me-2"></i>User Role Assignment
                            </h6>
                            <small class="text-muted">Assign and modify user access levels</small>
                        </div>
                        <div>
                            <small class="text-muted">Total Users: <?= count($users) ?></small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                <tr>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ID</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">NAME</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">USERNAME</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">DEPARTMENT</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">STATUS</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">CURRENT ROLE</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ASSIGN ROLE</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;"><?= $user['acc_id'] ?></td>
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;">
                                            <?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>
                                        </td>
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;">
                                            <i class="bi bi-at me-1 text-muted"></i><?= htmlspecialchars($user['acc_user']) ?>
                                        </td>
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;">
                                            <?= htmlspecialchars($user['dept_name'] ?? 'N/A') ?>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <span class="badge <?= $user['acc_status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 12px;">
                                                <?= htmlspecialchars($user['acc_status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <span class="badge bg-primary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 12px;">
                                                <?= htmlspecialchars($user['role_name'] ?? 'No Role Assigned') ?>
                                            </span>
                                            <small class="d-block text-muted mt-1" style="font-size: 0.75rem;">
                                                <?= getRoleDescription($user['role_name'] ?? 'No Role') ?>
                                            </small>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <form method="POST" class="d-inline" id="roleForm_<?= $user['acc_id'] ?>">
                                                <?= getCSRFTokenInput() ?>
                                                <input type="hidden" name="user_id" value="<?= $user['acc_id'] ?>">
                                                <select name="new_role_id" class="form-select form-select-sm" style="min-width: 200px;">
                                                    <option value="">Choose New Role</option>
                                                    <?php
                                                    $roles->data_seek(0);
                                                    while ($role = $roles->fetch_assoc()):
                                                        $selected = ($role['id'] == $user['role_id']) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= $role['id'] ?>" <?= $selected ?>>
                                                            <?= htmlspecialchars($role['role_name']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                        </td>
                                        <td class="text-center" style="padding: 0.75rem;">
                                                <button type="submit" name="assign_user_role" class="btn btn-primary btn-sm" title="Update user access level" style="background: #800000; border-color: #800000; font-size: 0.75rem; padding: 0.25rem 0.75rem;">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Permission Management - Table Format -->
            <div class="dashboard-card mt-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-0 fw-bold" style="color: #495057;">
                                <i class="bi bi-shield-check me-2"></i>User Permission
                            </h6>
                            <small class="text-muted">Enable or disable individual permissions for each user</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                <tr>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ID</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">NAME</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">USERNAME</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">DEPARTMENT</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ROLE</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">STATUS</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;"><?= $user['acc_id'] ?></td>
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;">
                                            <?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>
                                        </td>
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;">
                                            <i class="bi bi-at me-1 text-muted"></i><?= htmlspecialchars($user['acc_user']) ?>
                                        </td>
                                        <td style="padding: 0.75rem; color: #495057; font-size: 0.875rem;">
                                            <?= htmlspecialchars($user['dept_name'] ?? 'N/A') ?>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <span class="badge bg-primary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 12px;">
                                                <?= htmlspecialchars($user['role_name'] ?? 'No Role') ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <span class="badge <?= $user['acc_status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 12px;">
                                                <?= htmlspecialchars($user['acc_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center" style="padding: 0.75rem;">
                                            <button type="button" class="btn btn-primary btn-sm manage-permissions-btn" data-user-id="<?= $user['acc_id'] ?>" data-user-name="<?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>" title="Manage Permissions" style="background: #800000; border-color: #800000; font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                                <i class="bi bi-gear me-1"></i>Manage
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
  </div>
</div>

<!-- User Permission Management Modal (same large size as Add New Department) -->
<div class="modal fade" id="userPermissionModal" tabindex="-1" aria-labelledby="userPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userPermissionModalLabel">
                    <i class="bi bi-shield-check me-2"></i>Manage User Permissions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="permissionModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading permissions...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-maroon" id="saveUserPermissionsBtn"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Backdrop: cover whole dashboard when modal is open */
.modal-backdrop,
.modal-backdrop.show {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    min-width: 100% !important;
    min-height: 100% !important;
    z-index: 1100 !important;
    background-color: rgba(0, 0, 0, 0.45) !important;
}
body.modal-open .modal-backdrop {
    z-index: 1100 !important;
}

/* Modal on top of everything (matches shared admin_support.css) */
#userPermissionModal,
#userPermissionModal.modal {
    z-index: 1105 !important;
}
#userPermissionModal .modal-dialog,
#userPermissionModal .modal-content {
    position: relative;
    z-index: 1;
}
#userPermissionModal .modal-content { z-index: 2; }
</style>

<script>
// Define viewUserPermissions function first - before DOMContentLoaded
function viewUserPermissions(userId) {
    console.log('viewUserPermissions called with userId:', userId);
    
    // Wait for SweetAlert2 to load if not immediately available
    function waitForSwal(callback, maxAttempts = 10, attempt = 0) {
        if (typeof Swal !== 'undefined') {
            callback();
        } else if (attempt < maxAttempts) {
            setTimeout(() => waitForSwal(callback, maxAttempts, attempt + 1), 100);
        } else {
            alert('SweetAlert2 failed to load. Please refresh the page.');
            console.error('SweetAlert2 is not available after waiting');
        }
    }
    
    // Execute the function after ensuring Swal is loaded
    waitForSwal(() => {
        executeViewUserPermissions(userId);
    });
}

function executeViewUserPermissions(userId) {
    console.log('executeViewUserPermissions called with userId:', userId);
    
    // Show loading
    Swal.fire({
        title: 'Loading...',
        text: 'Please wait while we fetch user permissions',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Get the correct path - use relative path from current location
    // Try multiple path strategies for reliability
    const currentPath = window.location.pathname;
    let handlerPath = '';
    
    // Strategy 1: If we're in views/admin_support/, go up two levels then to admin_support/
    if (currentPath.includes('/views/admin_support/')) {
        handlerPath = '../../admin_support/permission_sync_handler.php';
    } 
    // Strategy 2: If we're in admin_support/, go up one level then to admin_support/
    else if (currentPath.includes('/admin_support/')) {
        handlerPath = '../admin_support/permission_sync_handler.php';
    }
    // Strategy 3: Fallback - try relative path
    else {
        handlerPath = '../../admin_support/permission_sync_handler.php';
    }
    
    console.log('Current path:', currentPath);
    console.log('Base path:', basePath);
    console.log('Fetching from path:', handlerPath);
    
    // Fetch user permissions
    fetch(handlerPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_user_permission_status&target_user_id=${userId}&csrf_token=<?= generateCSRFToken() ?>`
    })
    .then(async response => {
        // Get response text first to check if it's JSON
        const responseText = await response.text();
        console.log('Response status:', response.status);
        console.log('Response text:', responseText.substring(0, 200)); // First 200 chars for debugging
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}. Response: ${responseText.substring(0, 100)}`);
        }
        
        // Try to parse as JSON
        try {
            return JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            console.error('Response was:', responseText);
            throw new Error('Server returned invalid JSON. Check console for details.');
        }
    })
    .then(data => {
        if (data.success) {
            const status = data.status;
            let permissionsHtml = '';
            
            if (status.permissions && Object.keys(status.permissions).length > 0) {
                Object.keys(status.permissions).forEach(permission => {
                    const perm = status.permissions[permission];
                    const badgeClass = perm.granted ? 'bg-success' : 'bg-danger';
                    permissionsHtml += `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>${perm.display_name || permission}</span>
                            <span class="badge ${badgeClass}">${perm.granted ? 'Granted' : 'Denied'}</span>
                        </div>
                    `;
                });
            } else {
                permissionsHtml = '<p class="text-muted">No permissions found for this user.</p>';
            }
            
            Swal.fire({
                title: `Permissions for User ID: ${userId}`,
                html: `
                    <div class="text-start">
                        <p><strong>Total Permissions:</strong> ${status.total_permissions || 0}</p>
                        <p><strong>Granted Permissions:</strong> ${status.granted_permissions || 0}</p>
                        <p><strong>Last Updated:</strong> ${status.last_updated || 'N/A'}</p>
                        <hr>
                        <h6>Permission Details:</h6>
                        <div style="max-height: 300px; overflow-y: auto;">
                            ${permissionsHtml}
                        </div>
                    </div>
                `,
                width: '600px',
                confirmButtonColor: '#800000',
                confirmButtonText: 'Close'
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message || 'Failed to fetch user permissions',
                icon: 'error',
                confirmButtonColor: '#800000'
            });
        }
    })
    .catch(error => {
        console.error('Error fetching permissions:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred while fetching permissions: ' + error.message,
            icon: 'error',
            confirmButtonColor: '#800000'
        });
    });
}

// User Permission Management Functions
let currentEditingUserId = null;

function openUserPermissionModal(userId, userName) {
    currentEditingUserId = userId;
    const modal = new bootstrap.Modal(document.getElementById('userPermissionModal'));
    document.getElementById('userPermissionModalLabel').innerHTML = `<i class="bi bi-shield-check me-2"></i>Manage Permissions: ${userName}`;
    
    // Show loading state
    document.getElementById('permissionModalContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading permissions...</p>
        </div>
    `;
    
    modal.show();
    loadUserPermissions(userId);
}

function loadUserPermissions(userId) {
    // Get the correct path - use relative path for reliability
    const currentPath = window.location.pathname;
    let handlerPath = '';
    
    if (currentPath.includes('/views/admin_support/')) {
        handlerPath = '../../admin_support/permissions_backend/get_user_permissions.php?acc_id=' + userId;
    } else if (currentPath.includes('/admin_support/')) {
        handlerPath = '../admin_support/permissions_backend/get_user_permissions.php?acc_id=' + userId;
    } else {
        // Fallback
        handlerPath = '../../admin_support/permissions_backend/get_user_permissions.php?acc_id=' + userId;
    }
    
    fetch(handlerPath)
        .then(async response => {
            const responseText = await response.text();
            console.log('User permissions response:', responseText);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // The response is just an array of permission IDs
            const userPermIds = JSON.parse(responseText);
            return Array.isArray(userPermIds) ? userPermIds : [];
        })
        .then(userPermIds => {
            // Get all permissions
            getAllPermissions(userId, userPermIds);
        })
        .catch(error => {
            console.error('Error loading permissions:', error);
            document.getElementById('permissionModalContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Error loading permissions: ${error.message}
                </div>
            `;
        });
}

function getAllPermissions(userId, userPerms) {
    // Use the permissions data from PHP that's already loaded on the page
    renderPermissionModalFromPage(userId, userPerms || []);
}

function renderPermissionModalFromPage(userId, userPermIds) {
    // Get permissions from PHP that's already loaded on the page
    const permissionsByModule = <?= json_encode($permissions) ?>;
    const userPermIdsArray = Array.isArray(userPermIds) ? userPermIds.map(id => parseInt(id)) : [];
    
    // Module icon mapping
    const moduleIcons = {
        'user': 'person',
        'schedule': 'calendar-week',
        'room': 'building',
        'subject': 'book',
        'report': 'file-earmark-text',
        'audit': 'journal-text',
        'role': 'shield-check',
        'academic': 'mortarboard',
        'conflicts': 'exclamation-triangle',
        'system': 'gear'
    };
    
    let html = `<div class="mb-3">
        <p class="text-muted mb-3" style="font-size: 0.875rem;">Enable or disable permissions for this user. Changes will override role-based permissions.</p>
    </div>`;
    
    // Group permissions by module and render
    Object.keys(permissionsByModule).forEach(module => {
        const modulePerms = permissionsByModule[module];
        if (modulePerms && modulePerms.length > 0) {
            const icon = moduleIcons[module.toLowerCase()] || 'gear';
            const moduleName = module.charAt(0).toUpperCase() + module.slice(1);
            html += `<div class="mb-4">
                <h6 class="fw-bold mb-3" style="color: #495057; font-size: 0.95rem;">
                    <i class="bi bi-${icon} me-2"></i>${moduleName} Module
                </h6>
                <div class="permissions-list" style="background: #f8f9fa; border-radius: 8px; padding: 0.5rem;">`;
            
            modulePerms.forEach(perm => {
                const isChecked = userPermIdsArray.includes(parseInt(perm.id));
                html += `
                    <div class="d-flex align-items-center justify-content-between py-2 px-2 border-bottom" style="border-color: #e9ecef !important; background: #fff; border-radius: 4px; margin-bottom: 0.25rem;">
                        <div class="flex-grow-1">
                            <div class="fw-medium" style="color: #495057; font-size: 0.875rem;">${escapeHtml(perm.permission_display_name || perm.permission_name || '')}</div>
                            <small class="text-muted" style="font-size: 0.75rem;">${escapeHtml(perm.permission_key || '')}</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input permission-toggle" type="checkbox" 
                                   data-permission-id="${perm.id}" 
                                   ${isChecked ? 'checked' : ''}
                                   style="width: 44px; height: 24px; cursor: pointer;">
                        </div>
                    </div>
                `;
            });
            
            html += `</div></div>`;
        }
    });
    
    if (Object.keys(permissionsByModule).length === 0) {
        html = `<div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No permissions available.
        </div>`;
    }
    
    document.getElementById('permissionModalContent').innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderPermissionModal(allPerms, userPerms) {
    // Group permissions by module
    const permissionsByModule = {};
    allPerms.forEach(perm => {
        const module = perm.module || 'other';
        if (!permissionsByModule[module]) {
            permissionsByModule[module] = [];
        }
        permissionsByModule[module].push(perm);
    });
    
    const userPermIds = Array.isArray(userPerms) ? userPerms : [];
    
    let html = `<div class="mb-3">
        <p class="text-muted mb-3">Enable or disable permissions for this user. Changes will override role-based permissions.</p>
    </div>`;
    
    Object.keys(permissionsByModule).forEach(module => {
        html += `<div class="mb-4">
            <h6 class="fw-bold mb-3" style="color: #495057; font-size: 0.95rem;">
                <i class="bi bi-${getModuleIcon(module)} me-2"></i>${module.charAt(0).toUpperCase() + module.slice(1)} Module
            </h6>
            <div class="permissions-list">`;
        
        permissionsByModule[module].forEach(perm => {
            const isChecked = userPermIds.includes(parseInt(perm.id));
            html += `
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color: #e9ecef !important;">
                    <div class="flex-grow-1">
                        <div class="fw-medium" style="color: #495057; font-size: 0.875rem;">${perm.permission_display_name || perm.permission_name}</div>
                        <small class="text-muted" style="font-size: 0.75rem;">${perm.permission_key || ''}</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input permission-toggle" type="checkbox" 
                               data-permission-id="${perm.id}" 
                               ${isChecked ? 'checked' : ''}
                               style="width: 44px; height: 24px; cursor: pointer;">
                    </div>
                </div>
            `;
        });
        
        html += `</div></div>`;
    });
    
    document.getElementById('permissionModalContent').innerHTML = html;
}

// Save user permissions - use event delegation since modal is dynamically shown
document.addEventListener('click', function(e) {
    if (e.target.closest('#saveUserPermissionsBtn')) {
        e.preventDefault();
        saveUserPermissions();
    }
});

function saveUserPermissions() {
    if (!currentEditingUserId) return;
    
    const selectedPermissions = [];
    document.querySelectorAll('#userPermissionModal .permission-toggle:checked').forEach(toggle => {
        selectedPermissions.push(parseInt(toggle.getAttribute('data-permission-id')));
    });
    
    // Get the correct path - use relative path for reliability
    const currentPath = window.location.pathname;
    let savePath = '';
    
    if (currentPath.includes('/views/admin_support/')) {
        savePath = '../../admin_support/permissions_backend/save_permissions.php';
    } else if (currentPath.includes('/admin_support/')) {
        savePath = '../admin_support/permissions_backend/save_permissions.php';
    } else {
        // Fallback
        savePath = '../../admin_support/permissions_backend/save_permissions.php';
    }
    
    const formData = new URLSearchParams();
    formData.append('acc_id', currentEditingUserId);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    selectedPermissions.forEach(permId => {
        formData.append('permissions[]', permId);
    });
    
    // Show loading
    const saveBtn = document.getElementById('saveUserPermissionsBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }
    
    fetch(savePath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
    })
    .then(async response => {
        const responseText = await response.text();
        console.log('Save response:', responseText);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // Check if response is JSON
        try {
            return JSON.parse(responseText);
        } catch (e) {
            // Might be a redirect or HTML, treat as success
            return { success: true };
        }
    })
    .then(data => {
        if (data.error || !data.success) {
            throw new Error(data.error || 'Failed to save permissions');
        }
        
        Swal.fire({
            title: 'Success!',
            text: 'User permissions have been updated successfully.',
            icon: 'success',
            confirmButtonColor: '#800000'
        }).then(() => {
            bootstrap.Modal.getInstance(document.getElementById('userPermissionModal')).hide();
            location.reload();
        });
    })
    .catch(error => {
        console.error('Error saving permissions:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Failed to save permissions: ' + error.message,
            icon: 'error',
            confirmButtonColor: '#800000'
        });
    })
    .finally(() => {
        const saveBtn = document.getElementById('saveUserPermissionsBtn');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Changes';
        }
    });
}

// Enhanced Permission Management System
document.addEventListener('DOMContentLoaded', function() {
    // Initialize pagination
    initializePagination();
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add smooth animations to cards
    const cards = document.querySelectorAll('.user-card, .enhanced-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add event listeners for manage permissions buttons using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.manage-permissions-btn')) {
            e.preventDefault();
            e.stopPropagation();
            const btn = e.target.closest('.manage-permissions-btn');
            const userId = btn.getAttribute('data-user-id');
            const userName = btn.getAttribute('data-user-name');
            if (userId) {
                openUserPermissionModal(parseInt(userId), userName);
            }
        }
    });
});

function updateSystemStatus() {
    // Update sync status indicators
    const statusElements = document.querySelectorAll('.sync-status');
    statusElements.forEach(element => {
        element.classList.remove('synced', 'pending', 'error');
        element.classList.add('synced');
        element.innerHTML = '<i class="bi bi-check-circle me-1"></i>System Active';
    });
}

// Permission synchronization functions
function syncAllPermissions() {
    Swal.fire({
        title: 'Sync All Permissions?',
        text: 'This will synchronize permissions for all users. Continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#800000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Sync All',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Syncing...',
                text: 'Please wait while we sync all permissions',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Perform sync for all users
            fetch('permission_sync_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=sync_all_permissions&csrf_token=<?= generateCSRFToken() ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'All permissions have been synchronized successfully.',
                        icon: 'success',
                        confirmButtonColor: '#800000'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to sync permissions',
                        icon: 'error',
                        confirmButtonColor: '#800000'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while syncing permissions',
                    icon: 'error',
                    confirmButtonColor: '#800000'
                });
            });
        }
    });
}

function syncUserPermissions(userId) {
    Swal.fire({
        title: 'Sync User Permissions?',
        text: 'This will synchronize permissions for this specific user. Continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#800000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Sync',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Syncing...',
                text: 'Please wait while we sync permissions',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Perform sync for specific user
            fetch('permission_sync_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=sync_user_permissions&user_id=${userId}&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'User permissions have been synchronized successfully.',
                        icon: 'success',
                        confirmButtonColor: '#800000'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to sync permissions',
                        icon: 'error',
                        confirmButtonColor: '#800000'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while syncing permissions',
                    icon: 'error',
                    confirmButtonColor: '#800000'
                });
            });
        }
    });
}

;

// Auto-refresh permissions every 30 seconds
setInterval(function() {
    // This could trigger a background sync check
    console.log('Checking for permission updates...');
    updateSystemStatus();
}, 30000);

// Enhanced form validation
function validateRoleAssignment(form) {
    const roleSelect = form.querySelector('select[name="new_role_id"]');
    if (!roleSelect.value) {
        Swal.fire({
            title: 'No Role Selected',
            text: 'Please select a role before updating.',
            icon: 'warning',
            confirmButtonColor: '#800000'
        });
        return false;
    }
    return true;
}

// Add form validation to all role assignment forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        if (form.querySelector('select[name="new_role_id"]')) {
            form.addEventListener('submit', function(e) {
                if (!validateRoleAssignment(this)) {
                    e.preventDefault();
                }
            });
        }
    });
});

// Enhanced permission matrix interactions
function togglePermissionGroup(roleId, module) {
    const checkboxes = document.querySelectorAll(`input[name="permissions[${roleId}][]"]`);
    const moduleCheckboxes = Array.from(checkboxes).filter(cb => {
        const permissionId = cb.value;
        // This would need to be enhanced to properly identify module-specific permissions
        return true; // Simplified for now
    });
    
    const allChecked = moduleCheckboxes.every(cb => cb.checked);
    moduleCheckboxes.forEach(cb => cb.checked = !allChecked);
}

// Handle form submission - work in both iframe and standalone mode
document.addEventListener('DOMContentLoaded', function() {
    const permissionsForm = document.getElementById('permissionsForm');
    if (permissionsForm) {
        permissionsForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default to use AJAX
            
            const formData = new FormData(permissionsForm);
            
            // Show loading state
            const saveButton = permissionsForm.querySelector('button[name="update_permissions"]');
            const originalText = saveButton ? saveButton.innerHTML : '';
            
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            }
            
            // Determine the correct path to dashboard.php
            let submitPath = 'dashboard.php';
            if (window.self !== window.top) {
                // We're in an iframe - submit to parent's dashboard
                const parentPath = window.parent.location.pathname;
                if (parentPath.includes('dashboard.php')) {
                    submitPath = parentPath;
                } else {
                    // Try to construct the path
                    const currentPath = window.location.pathname;
                    if (currentPath.includes('/views/admin_support/')) {
                        submitPath = '../../views/admin_support/dashboard.php';
                    } else {
                        submitPath = '../views/admin_support/dashboard.php';
                    }
                }
            }
            
            // Submit via AJAX
            fetch(submitPath, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.text().then(text => {
                        // Check if response contains success indicator
                        if (text.includes('success') || response.url.includes('success=1')) {
                            return { success: true };
                        }
                        // Try to parse as JSON
                        try {
                            return JSON.parse(text);
                        } catch {
                            return { success: true };
                        }
                    });
                } else {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            })
            .then(data => {
                if (data.success !== false) {
                    // Success - show notification and reload
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Role permissions updated successfully!',
                            icon: 'success',
                            confirmButtonColor: '#800000',
                            timer: 2000,
                            showConfirmButton: true
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert('Role permissions updated successfully!');
                        window.location.reload();
                    }
                } else {
                    throw new Error(data.error || 'Failed to save permissions');
                }
            })
            .catch(error => {
                console.error('Error saving permissions:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to save permissions: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#800000'
                    });
                } else {
                    alert('Failed to save permissions: ' + error.message);
                }
            })
            .finally(() => {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalText;
                }
            });
        });
    }
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save permissions
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const saveButton = document.querySelector('button[name="update_permissions"]');
        if (saveButton) {
            saveButton.click();
        }
    }
    
    // Ctrl+A to sync all permissions
    if (e.ctrlKey && e.key === 'a' && e.shiftKey) {
        e.preventDefault();
        if (typeof syncAllPermissions === 'function') {
            syncAllPermissions();
        }
    }
});

// Add loading states for better UX
function showLoading(element, text = 'Processing...') {
    const originalContent = element.innerHTML;
    element.innerHTML = `<i class="bi bi-hourglass-split me-2"></i>${text}`;
    element.disabled = true;
    return originalContent;
}

function hideLoading(element, originalContent) {
    element.innerHTML = originalContent;
    element.disabled = false;
}

// Pagination functionality
let currentPage = 1;
let totalPages = 1;
let totalRoles = 0;

function initializePagination() {
    const roleCards = document.querySelectorAll('.role-card');
    totalRoles = roleCards.length;
    totalPages = Math.ceil(totalRoles / 1); // Show 1 role per page
    
    // Update pagination info
    document.getElementById('totalRoles').textContent = totalRoles;
    document.getElementById('totalPages').textContent = totalPages;
    
    // Show first role
    showPage(1);
    
    // Generate page indicators
    generatePageIndicators();
    
    // Update button states
    updatePaginationButtons();
}

function showPage(page) {
    const roleCards = document.querySelectorAll('.role-card');
    
    // Hide all role cards
    roleCards.forEach(card => {
        card.style.display = 'none';
    });
    
    // Show the role for the current page
    const roleIndex = page - 1;
    if (roleCards[roleIndex]) {
        roleCards[roleIndex].style.display = 'flex';
    }
    
    // Update pagination info
    document.getElementById('currentPage').textContent = page;
    document.getElementById('currentRole').textContent = page;
    
    // Update page indicators
    updatePageIndicators(page);
    
    // Update button states
    updatePaginationButtons();
}

function changePage(direction) {
    const newPage = currentPage + direction;
    
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        showPage(currentPage);
        
        // Smooth scroll to top of role card
        const rolesContainer = document.getElementById('rolesContainer');
        rolesContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        showPage(currentPage);
        
        // Smooth scroll to top of role card
        const rolesContainer = document.getElementById('rolesContainer');
        rolesContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function generatePageIndicators() {
    const indicatorsContainer = document.getElementById('pageIndicators');
    indicatorsContainer.innerHTML = '';
    
    for (let i = 1; i <= totalPages; i++) {
        const dot = document.createElement('div');
        dot.className = 'page-dot';
        if (i === 1) dot.classList.add('active');
        dot.onclick = () => goToPage(i);
        indicatorsContainer.appendChild(dot);
    }
}

function updatePageIndicators(activePage) {
    const dots = document.querySelectorAll('.page-dot');
    dots.forEach((dot, index) => {
        dot.classList.toggle('active', index + 1 === activePage);
    });
}

function updatePaginationButtons() {
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages;
}

// Enhanced permission matrix functionality
function selectAllPermissions() {
    const checkboxes = document.querySelectorAll('.permission-switch');
    checkboxes.forEach(checkbox => {
        if (checkbox && checkbox.type === 'checkbox' && checkbox.closest('.permission-card')) {
            checkbox.checked = true;
            updatePermissionCard(checkbox);
        }
    });
    
    // Show success message
    if (typeof showNotification === 'function') {
        showNotification('All permissions selected', 'success');
    }
}

function clearAllPermissions() {
    const checkboxes = document.querySelectorAll('.permission-switch');
    checkboxes.forEach(checkbox => {
        if (checkbox && checkbox.type === 'checkbox' && checkbox.closest('.permission-card')) {
            checkbox.checked = false;
            updatePermissionCard(checkbox);
        }
    });
    
    // Show success message
    if (typeof showNotification === 'function') {
        showNotification('All permissions cleared', 'info');
    }
}

// Modern card-based permission functions
function toggleAllPermissions(roleId) {
    const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
    if (!roleCard) {
        console.warn(`Role card not found for roleId: ${roleId}`);
        return;
    }
    
    const checkboxes = roleCard.querySelectorAll('.permission-switch');
    if (checkboxes.length === 0) {
        console.warn(`No checkboxes found for roleId: ${roleId}`);
        return;
    }
    
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        if (checkbox && checkbox.type === 'checkbox' && checkbox.closest('.permission-card')) {
            checkbox.checked = !allChecked;
            updatePermissionCard(checkbox);
        }
    });
    
    if (typeof showNotification === 'function') {
        showNotification(`All permissions ${allChecked ? 'cleared' : 'enabled'} for role`, 'info');
    }
}

function clearAllPermissionsForRole(roleId) {
    const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
    if (!roleCard) {
        console.warn(`Role card not found for roleId: ${roleId}`);
        return;
    }
    
    const checkboxes = roleCard.querySelectorAll('.permission-switch');
    if (checkboxes.length === 0) {
        console.warn(`No checkboxes found for roleId: ${roleId}`);
        return;
    }
    
    checkboxes.forEach(checkbox => {
        if (checkbox && checkbox.type === 'checkbox' && checkbox.closest('.permission-card')) {
            checkbox.checked = false;
            updatePermissionCard(checkbox);
        }
    });
    
    if (typeof showNotification === 'function') {
        showNotification('All permissions cleared for role', 'info');
    }
}

function toggleModulePermissions(roleId, module) {
    const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
    if (!roleCard) {
        console.warn(`Role card not found for roleId: ${roleId}`);
        return;
    }
    
    // Only select checkboxes with the specific module, not all elements
    const moduleCheckboxes = roleCard.querySelectorAll(`input.permission-switch[data-module="${module}"]`);
    
    if (moduleCheckboxes.length === 0) {
        console.warn(`No checkboxes found for roleId: ${roleId}, module: ${module}`);
        return;
    }
    
    const allChecked = Array.from(moduleCheckboxes).every(cb => cb.checked);
    
    moduleCheckboxes.forEach(checkbox => {
        if (checkbox && checkbox.type === 'checkbox') {
            checkbox.checked = !allChecked;
            // Only call updatePermissionCard if checkbox has a permission-card parent
            if (checkbox.closest('.permission-card')) {
                updatePermissionCard(checkbox);
            }
        }
    });
    
    if (typeof showNotification === 'function') {
        showNotification(`Module permissions ${allChecked ? 'cleared' : 'enabled'}`, 'info');
    }
}

function updatePermissionCard(checkbox) {
    if (!checkbox) {
        console.warn('updatePermissionCard: checkbox is null');
        return;
    }
    
    const permissionCard = checkbox.closest('.permission-card');
    if (!permissionCard) {
        console.warn('updatePermissionCard: permission-card not found for checkbox', checkbox);
        return;
    }
    
    const isEnabled = checkbox.checked;
    
    // Update card appearance
    if (isEnabled) {
        permissionCard.classList.remove('permission-disabled');
        permissionCard.classList.add('permission-enabled');
        
        // Update icon if it exists
        const icon = permissionCard.querySelector('.permission-icon i');
        if (icon) {
            icon.className = 'bi bi-check-circle-fill';
        }
        
        // Add animation
        permissionCard.style.transform = 'scale(1.02)';
        setTimeout(() => {
            permissionCard.style.transform = 'scale(1)';
        }, 200);
    } else {
        permissionCard.classList.remove('permission-enabled');
        permissionCard.classList.add('permission-disabled');
        
        // Update icon if it exists
        const icon = permissionCard.querySelector('.permission-icon i');
        if (icon) {
            icon.className = 'bi bi-circle';
        }
    }
    
    // Update role card summary
    if (checkbox.dataset && checkbox.dataset.roleId) {
        updateRoleCardSummary(checkbox.dataset.roleId);
    }
    
    // Trigger change event to ensure form state is updated
    const changeEvent = new Event('change', { bubbles: true });
    checkbox.dispatchEvent(changeEvent);
}

function updateRoleCardSummary(roleId) {
    if (!roleId) {
        return;
    }
    
    const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
    if (!roleCard) {
        return;
    }
    
    const checkboxes = roleCard.querySelectorAll('.permission-switch');
    if (checkboxes.length === 0) {
        return;
    }
    
    const enabledCount = Array.from(checkboxes).filter(cb => cb && cb.checked).length;
    const totalCount = checkboxes.length;
    
    // Update role card header with summary
    const roleActions = roleCard.querySelector('.role-actions');
    if (roleActions) {
        const summary = roleActions.querySelector('.permission-summary');
        if (summary) {
            summary.textContent = `${enabledCount}/${totalCount} enabled`;
        } else {
            const summaryElement = document.createElement('small');
            summaryElement.className = 'permission-summary text-white opacity-75';
            summaryElement.textContent = `${enabledCount}/${totalCount} enabled`;
            roleActions.appendChild(summaryElement);
        }
    }
}

// Add visual feedback for permission changes
document.querySelectorAll('.permission-matrix input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const row = this.closest('tr');
        if (this.checked) {
            row.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);
        } else {
            row.style.backgroundColor = '#ffeaea';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);
        }
    });
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        selectAllPermissions();
    }
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        clearAllPermissions();
    }
});

// Add tooltips for better UX
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Tab switching function
function showModuleTab(moduleName, roleId) {
    // Hide all tab contents for this role
    const allTabs = document.querySelectorAll(`.module-tab-content[data-role-id="${roleId}"]`);
    allTabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(`tab-${moduleName}-${roleId}`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update tab button states
    const roleCard = document.querySelector(`.role-card[data-role-id="${roleId}"]`);
    if (roleCard) {
        const allButtons = roleCard.querySelectorAll('.module-tab-button');
        allButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-module') === moduleName) {
                btn.classList.add('active');
            }
        });
    }
}
</script>
</div>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <span>&copy; 2024 EVSU-OCC Scheduling System. All rights reserved.</span>
    </div>
  </footer>

  <!-- Logout Modal (same style as Add Department) -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">Are you sure you want to logout?</div>
        <div class="modal-footer border-0 bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
          <a href="../auth/logout.php" class="btn btn-maroon"><i class="bi bi-check-lg me-1"></i>Logout</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="../../public/assets/js/bootstrap.bundle.min.js"></script>
  <!-- SweetAlert2 JS -->
  <script src="../../public/assets/js/sweetalert2.min.js"></script>
  
  <script>
    // Close offcanvas function
    function closeOffcanvas() {
      const offcanvasEl = document.getElementById('sidebarOffcanvas');
      if (offcanvasEl && typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
        const instance = bootstrap.Offcanvas.getInstance(offcanvasEl) || new bootstrap.Offcanvas(offcanvasEl);
        instance.hide();
      }
    }

    // Navigate to dashboard with specific tab (URL will be cleaned by dashboard.php)
    function navigateToDashboardTab(tab) {
      if (tab === 'overview') {
        window.location.href = 'dashboard.php';
      } else {
        window.location.href = 'dashboard.php?tab=' + tab;
      }
    }

    // Search functionality
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        const resultsContainer = document.getElementById('searchResults');
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
          if (resultsContainer) resultsContainer.style.display = 'none';
          return;
        }
        
        if (resultsContainer) {
          resultsContainer.innerHTML = '<div class="p-3 text-center"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>';
          resultsContainer.style.display = 'block';
        }
        
        searchTimeout = setTimeout(() => {
          performSearch(query, resultsContainer);
        }, 300);
      });

      // Close search results when clicking outside
      document.addEventListener('click', function(e) {
        if (searchInput && resultsContainer && !e.target.closest('.search-box')) {
          resultsContainer.style.display = 'none';
        }
      });
    }

    function performSearch(query, resultsContainer) {
      if (!resultsContainer) return;
      
      // For now, show a simple message. You can implement actual search API later
      resultsContainer.innerHTML = '<div class="p-3 text-muted text-center"><i class="bi bi-search me-2"></i>Search functionality coming soon</div>';
      resultsContainer.style.display = 'block';
    }

    // Notification functionality
    function toggleNotifications() {
      const dropdown = document.getElementById('notificationDropdown');
      if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
      }
    }

    // User menu functionality
    function toggleUserMenu() {
      const menu = document.getElementById('userMenu');
      if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
      }
    }

    // User menu functions
    function showProfileModal() {
      alert('Opening profile modal...');
      const menu = document.getElementById('userMenu');
      if (menu) menu.style.display = 'none';
    }

    function showSettingsModal() {
      alert('Opening settings modal...');
      const menu = document.getElementById('userMenu');
      if (menu) menu.style.display = 'none';
    }

    function logout() {
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = '../../views/auth/logout.php';
      }
      const menu = document.getElementById('userMenu');
      if (menu) menu.style.display = 'none';
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      const notificationDropdown = document.getElementById('notificationDropdown');
      const userMenu = document.getElementById('userMenu');
      
      if (notificationDropdown && !e.target.closest('.notification')) {
        notificationDropdown.style.display = 'none';
      }
      if (userMenu && !e.target.closest('.user-profile')) {
        userMenu.style.display = 'none';
      }
    });
  </script>
  
<script>
// Helper function to ensure SweetAlert2 dialogs are properly positioned
window.ensureSwalPosition = function() {
    // Ensure SweetAlert2 container is properly positioned
    const swalContainer = document.querySelector('.swal2-container');
    if (swalContainer) {
        swalContainer.style.position = 'fixed';
        swalContainer.style.top = '0';
        swalContainer.style.left = '0';
        swalContainer.style.width = '100%';
        swalContainer.style.height = '100%';
        swalContainer.style.zIndex = '10000';
        swalContainer.style.display = 'flex';
        swalContainer.style.alignItems = 'center';
        swalContainer.style.justifyContent = 'center';
    }
};

// Override Swal.fire to ensure proper positioning
if (typeof Swal !== 'undefined') {
    const originalFire = Swal.fire;
    Swal.fire = function(...args) {
        const result = originalFire.apply(this, args);
        // Ensure positioning after dialog is shown
        setTimeout(() => {
            window.ensureSwalPosition();
        }, 10);
        return result;
    };
}

// Detect if page is loaded in an iframe and hide sidebar
(function() {
  if (window.self !== window.top) {
    // Page is loaded in an iframe
    document.documentElement.classList.add('iframe-mode');
    document.body.classList.add('iframe-mode');
    
    // Ensure SweetAlert2 positioning works in iframe
    document.addEventListener('DOMContentLoaded', function() {
        // Monitor for SweetAlert2 containers
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && (node.classList.contains('swal2-container') || node.querySelector('.swal2-container'))) {
                            window.ensureSwalPosition();
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
  }
})();
</script>

</body>
</html>