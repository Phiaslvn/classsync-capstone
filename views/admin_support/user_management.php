<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

// Check if user is logged in and has admin support role
requireRole('Admin support', '../../index.php');

// Handle Demote (Admin Support -> Admin)
if (isset($_GET['demote_id'])) {
    // Validate CSRF token for GET requests (passed as query parameter)
    if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
        echo '<script>alert("Security error: Invalid request token."); window.history.back();</script>';
        exit;
    }
    
    $demote_id = intval($_GET['demote_id']);
    
    // Log the action before executing
    logAdminAction($_SESSION['acc_id'], 'demote_user', "Demoted user ID $demote_id from Admin Support to Admin");
    
    $stmt = $conn->prepare("
        UPDATE user_roles
        SET role_id = (SELECT id FROM roles WHERE role_name = 'Admin' LIMIT 1)
        WHERE acc_id = ?
    ");
    $stmt->bind_param("i", $demote_id);
    $stmt->execute();
    $stmt->close();

    // Redirect to user management page
    header("Location: user_management.php?update=success");
    exit;
}

// Handle Status Updates
if (isset($_POST['update_status'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo '<script>alert("Security error: Invalid request token."); window.history.back();</script>';
        exit;
    }
    
    $user_id = intval($_POST['user_id']);
    $new_status = sanitizeInput($_POST['new_status']);
    
    if (in_array($new_status, ['Active', 'Inactive', 'Pending', 'Deleted'])) {
        $stmt = $conn->prepare("UPDATE account SET acc_status = ? WHERE acc_id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();
        $stmt->close();
        
        logAdminAction($_SESSION['acc_id'], 'update_user_status', "Updated user ID $user_id status to $new_status");
        // Redirect to user management page
        header("Location: user_management.php?status_updated=success");
        exit;
    }
}

// Handle Bulk Actions
if (isset($_POST['bulk_action'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo '<script>alert("Security error: Invalid request token."); window.history.back();</script>';
        exit;
    }
    
    $action = sanitizeInput($_POST['bulk_action']);
    $selected_users = $_POST['selected_users'] ?? [];
    
    if (!empty($selected_users) && in_array($action, ['activate', 'deactivate', 'delete', 'export'])) {
        $user_ids = array_map('intval', $selected_users);
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        
        switch ($action) {
            case 'activate':
                $stmt = $conn->prepare("UPDATE account SET acc_status = 'Active' WHERE acc_id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
                $stmt->execute();
                logAdminAction($_SESSION['acc_id'], 'bulk_activate_users', "Activated " . count($user_ids) . " users");
                break;
            case 'deactivate':
                $stmt = $conn->prepare("UPDATE account SET acc_status = 'Inactive' WHERE acc_id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
                $stmt->execute();
                logAdminAction($_SESSION['acc_id'], 'bulk_deactivate_users', "Deactivated " . count($user_ids) . " users");
                break;
            case 'delete':
                $stmt = $conn->prepare("UPDATE account SET acc_status = 'Deleted' WHERE acc_id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
                $stmt->execute();
                logAdminAction($_SESSION['acc_id'], 'bulk_delete_users', "Deleted " . count($user_ids) . " users");
                break;
        }
        $stmt->close();
        // Redirect to user management page
        header("Location: user_management.php?bulk_action=success");
        exit;
    }
}

// Handle Role Changes
if (isset($_POST['change_role'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo '<script>alert("Security error: Invalid request token."); window.history.back();</script>';
        exit;
    }
    
    $user_id = intval($_POST['user_id']);
    $new_role = sanitizeInput($_POST['new_role']);
    
    $stmt = $conn->prepare("
        UPDATE user_roles 
        SET role_id = (SELECT id FROM roles WHERE role_name = ? LIMIT 1) 
        WHERE acc_id = ?
    ");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();
    $stmt->close();
    
    logAdminAction($_SESSION['acc_id'], 'change_user_role', "Changed user ID $user_id role to $new_role");
    // Use JavaScript redirect since we're included in dashboard.php
    header("Location: user_management.php?role_changed=success");
    exit;
}

// Handle Get User Data for Edit Modal
if (isset($_POST['action']) && $_POST['action'] === 'get_user_data') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Security error: Invalid request token.'
        ]);
        exit;
    }
    
    header('Content-Type: application/json');
    $user_id = intval($_POST['user_id']);
    
    $stmt = $conn->prepare("
        SELECT 
            a.acc_id, a.fname, a.lname, a.minitial, a.suffix, a.acc_user, a.acc_email, a.acc_status, a.dept_id, a.profile_picture,
            r.id as role_id, r.role_name,
            i.inst_id, i.rank, i.designation, i.inst_status, i.program_id, i.inst_phone,
            COALESCE(i.administration_hours, 0) AS administration_hours,
            COALESCE(i.instruction_hours, 0) AS instruction_hours,
            COALESCE(i.research_hours, 0) AS research_hours,
            COALESCE(i.extension_hours, 0) AS extension_hours,
            COALESCE(i.instructional_functions_hours, 0) AS instructional_functions_hours,
            COALESCE(i.consultation_hours, 0) AS consultation_hours
        FROM account a
        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN instructor i ON a.acc_user = i.inst_user
        WHERE a.acc_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Build profile picture path
        $profile_path = null;
        if (!empty($user['profile_picture'])) {
            $profile_path = '/' . $user['profile_picture'];
        }
        $user['profile_path'] = $profile_path;
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
    $stmt->close();
    exit;
}

// Handle Edit User Form Submission
if (isset($_POST['edit_user_id'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo '<script>alert("Security error: Invalid request token."); window.history.back();</script>';
        exit;
    }
    
    $user_id = intval($_POST['edit_user_id']);
    $first_name = sanitizeInput($_POST['edit_first_name']);
    $last_name = sanitizeInput($_POST['edit_last_name']);
    $middle_initial = sanitizeInput($_POST['edit_middle_initial']);
    $suffix = sanitizeInput($_POST['edit_suffix'] ?? '');
    $username = sanitizeInput($_POST['edit_username']);
    $email = sanitizeInput($_POST['edit_email']);
    $role_id = intval($_POST['edit_role']);
    
    // Get workload fields
    $rank = sanitizeInput($_POST['edit_rank'] ?? '');
    $designation = sanitizeInput($_POST['edit_designation'] ?? 'None');
    $inst_status = sanitizeInput($_POST['edit_inst_status'] ?? 'Regular');
    $program_id = !empty($_POST['edit_program_id']) ? intval($_POST['edit_program_id']) : null;
    $inst_phone = sanitizeInput($_POST['edit_inst_phone'] ?? '');
    $administration_hours = intval($_POST['edit_administration_hours'] ?? 0);
    $instruction_hours = intval($_POST['edit_instruction_hours'] ?? 0);
    $research_hours = intval($_POST['edit_research_hours'] ?? 0);
    $extension_hours = intval($_POST['edit_extension_hours'] ?? 0);
    $instructional_functions_hours = intval($_POST['edit_instructional_functions_hours'] ?? 0);
    $consultation_hours = intval($_POST['edit_consultation_hours'] ?? 0);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($role_id)) {
        echo '<script>alert("Please fill in all required fields."); window.history.back();</script>';
        exit;
    }
    
    // Check if username or email already exists (excluding current user)
    $check_stmt = $conn->prepare("
        SELECT acc_id FROM account 
        WHERE (acc_user = ? OR acc_email = ?) AND acc_id != ?
    ");
    $check_stmt->bind_param("ssi", $username, $email, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        echo '<script>alert("Username or email already exists."); window.history.back();</script>';
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update account table
        $update_stmt = $conn->prepare("
            UPDATE account 
            SET fname = ?, lname = ?, minitial = ?, suffix = ?, acc_user = ?, acc_email = ?
            WHERE acc_id = ?
        ");
        $update_stmt->bind_param("ssssssi", $first_name, $last_name, $middle_initial, $suffix, $username, $email, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Update user role
        $role_stmt = $conn->prepare("
            UPDATE user_roles 
            SET role_id = ? 
            WHERE acc_id = ?
        ");
        $role_stmt->bind_param("ii", $role_id, $user_id);
        $role_stmt->execute();
        $role_stmt->close();
        
        // Update or insert instructor record with workload hours
        // First check if instructor record exists
        $check_inst_stmt = $conn->prepare("SELECT inst_id FROM instructor WHERE inst_user = ?");
        $check_inst_stmt->bind_param("s", $username);
        $check_inst_stmt->execute();
        $inst_result = $check_inst_stmt->get_result();
        $check_inst_stmt->close();
        
        if ($inst_result->num_rows > 0) {
            // Update existing instructor record
            $inst_row = $inst_result->fetch_assoc();
            $inst_id = $inst_row['inst_id'];
            $update_inst_stmt = $conn->prepare("
                UPDATE instructor 
                SET rank = ?, designation = ?, inst_status = ?, program_id = ?, inst_phone = ?,
                    administration_hours = ?, instruction_hours = ?, 
                    research_hours = ?, extension_hours = ?,
                    instructional_functions_hours = ?, consultation_hours = ?
                WHERE inst_id = ?
            ");
            $update_inst_stmt->bind_param("sssissiiiiii", 
                $rank, $designation, $inst_status, $program_id, $inst_phone,
                $administration_hours, $instruction_hours,
                $research_hours, $extension_hours,
                $instructional_functions_hours, $consultation_hours,
                $inst_id
            );
            $update_inst_stmt->execute();
            $update_inst_stmt->close();
        } else {
            // Insert new instructor record
            $insert_inst_stmt = $conn->prepare("
                INSERT INTO instructor (inst_user, inst_fname, inst_lname, inst_mname, rank, designation, inst_status, program_id, inst_phone,
                    administration_hours, instruction_hours, research_hours, extension_hours,
                    instructional_functions_hours, consultation_hours)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_inst_stmt->bind_param("sssssssissiiiii",
                $username, $first_name, $last_name, $middle_initial,
                $rank, $designation, $inst_status, $program_id, $inst_phone,
                $administration_hours, $instruction_hours,
                $research_hours, $extension_hours,
                $instructional_functions_hours, $consultation_hours
            );
            $insert_inst_stmt->execute();
            $insert_inst_stmt->close();
        }
        
        // Log the action
        $log_message = "Updated user account: $first_name $last_name ($username)";
        if (!empty($password)) {
            $log_message .= " - Password changed";
        }
        logAdminAction($_SESSION['acc_id'], 'edit_user', $log_message);
        
        // Commit transaction
        $conn->commit();
        
        // Redirect with success message
        header("Location: user_management.php?user_updated=success");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo '<script>alert("Error updating user: ' . $e->getMessage() . '"); window.history.back();</script>';
        exit;
    }
}

/* ----------------------------- PAGINATION + SEARCH SETTINGS ----------------------------- */
$records_per_page = 10;

// Add CSRF token for AJAX requests
$csrf_token = generateCSRFToken();

// Search (top-left). Keep it simple: searches first/last/username/email
$search = sanitizeInput($_GET['search'] ?? '');
$searchWhere = '';
$searchParams = [];
$searchTypes = '';
if ($search !== '') {
    $searchWhere = " AND (a.fname LIKE ? OR a.lname LIKE ? OR a.acc_user LIKE ? OR a.acc_email LIKE ?) ";
    $searchPattern = "%{$search}%";
    $searchParams = [$searchPattern, $searchPattern, $searchPattern, $searchPattern];
    $searchTypes = 'ssss';
}

/* ----------------------------- Admin Support Pagination ----------------------------- */
$page_support = isset($_GET['page_support']) ? max(1, intval($_GET['page_support'])) : 1;
$offset_support = ($page_support - 1) * $records_per_page;

// Count query for Admin Support with search
$total_support_query = "
    SELECT COUNT(*) AS cnt
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name = 'Admin support' AND a.acc_status != 'Deleted'
    {$searchWhere}
";
$stmt_count_support = $conn->prepare($total_support_query);
if (!empty($searchParams)) {
    $stmt_count_support->bind_param($searchTypes, ...$searchParams);
}
$stmt_count_support->execute();
$total_support = $stmt_count_support->get_result()->fetch_assoc()['cnt'];
$stmt_count_support->close();

$total_pages_support = max(1, ceil($total_support / $records_per_page));

// Data query for Admin Support with search
$supportQuery = "
    SELECT a.acc_id, a.acc_user, a.acc_status,
           a.fname, a.lname, a.minitial, a.acc_email,
           d.dept_name, r.role_name
    FROM account a
    LEFT JOIN department d ON a.dept_id = d.dept_id
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name = 'Admin support' AND a.acc_status != 'Deleted'
    {$searchWhere}
    LIMIT ? OFFSET ?
";

$stmt_support = $conn->prepare($supportQuery);
if (!empty($searchParams)) {
    $stmt_support->bind_param($searchTypes . "ii", ...array_merge($searchParams, [$records_per_page, $offset_support]));
} else {
$stmt_support->bind_param("ii", $records_per_page, $offset_support);
}
$stmt_support->execute();
$supportResult = $stmt_support->get_result();
$stmt_support->close();

/* ----------------------------- Admin Pagination ----------------------------- */
$page_admin = isset($_GET['page_admin']) ? max(1, intval($_GET['page_admin'])) : 1;
$offset_admin = ($page_admin - 1) * $records_per_page;

// Count query for Admin with search (using prepared statement)
$total_admin_query = "
    SELECT COUNT(*) AS cnt
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name = 'Admin' AND a.acc_status != 'Deleted'
    {$searchWhere}
";
$stmt_count_admin = $conn->prepare($total_admin_query);
if (!empty($searchParams)) {
    $stmt_count_admin->bind_param($searchTypes, ...$searchParams);
}
$stmt_count_admin->execute();
$total_admin = $stmt_count_admin->get_result()->fetch_assoc()['cnt'];
$stmt_count_admin->close();

$total_pages_admin = max(1, ceil($total_admin / $records_per_page));

// Data query for Admin with search (using prepared statement)
$adminQuery = "
    SELECT a.acc_id, a.acc_user, a.acc_status,
           a.fname, a.lname, a.minitial, a.acc_email,
           d.dept_name, r.role_name
    FROM account a
    LEFT JOIN department d ON a.dept_id = d.dept_id
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name = 'Admin' AND a.acc_status != 'Deleted'
    {$searchWhere}
    LIMIT ? OFFSET ?
";
$stmt_admin = $conn->prepare($adminQuery);
if (!empty($searchParams)) {
    $stmt_admin->bind_param($searchTypes . "ii", ...array_merge($searchParams, [$records_per_page, $offset_admin]));
} else {
$stmt_admin->bind_param("ii", $records_per_page, $offset_admin);
}
$stmt_admin->execute();
$adminResult = $stmt_admin->get_result();
$stmt_admin->close();

/* ----------------------------- Fetch departments + roles for modals ----------------------------- */
$dept_stmt = $conn->prepare("SELECT dept_id, dept_name FROM department");
$dept_stmt->execute();
$deptResult = $dept_stmt->get_result();
$dept_stmt->close();

// Fetch roles into array (initialize first)
$roleIds = [];
$role_stmt = $conn->prepare("SELECT id, role_name FROM roles");
$role_stmt->execute();
$roleResult = $role_stmt->get_result();
while ($role = $roleResult->fetch_assoc()) {
    $roleIds[$role['role_name']] = $role['id'];
}
$role_stmt->close();
$adminRoleId = $roleIds['Admin'] ?? null;
$adminSupportRoleId = $roleIds['Admin support'] ?? $roleIds['Admin Support'] ?? null; // handle slight naming differences
$moderatorRoleId = $roleIds['Moderator'] ?? null;

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

// When included as dashboard tab component, output only main content (no DOCTYPE/head/body/navbar/sidebar)
if (defined('ADMIN_SUPPORT_USER_MGMT_CONTENT_ONLY')) {
    $content_file = __DIR__ . '/user_management_content.php';
    if (file_exists($content_file)) {
        include $content_file;
    } else {
        echo '<div class="alert alert-info">User Management content file is being set up. Full content will appear here once user_management_content.php is created.</div>';
    }
    return;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVSU-OCC Scheduling System - User Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/main.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
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
    
    .sidebar { 
        background:#800000; 
        color:#fff; 
        min-height:100vh; 
        position:fixed !important; 
        top:70px !important; 
        left:0 !important; 
        width:280px !important; 
        padding-top:0.5rem; 
        padding-bottom:0.5rem; 
        z-index:1090 !important; 
        overflow-y:auto; 
        max-height:calc(100vh - 70px);
        /* Ensure sidebar is never affected by modal state */
        transform: none !important;
        transition: none !important;
    }
    
    /* Ensure sidebar is always visible and properly positioned, even when modal is open */
    body.modal-open .sidebar {
        position: fixed !important;
        left: 0 !important;
        top: 70px !important;
        width: 280px !important;
        z-index: 1090 !important;
        transform: none !important;
        transition: none !important;
    }
    
    /* When modal is open - let Bootstrap handle backdrop naturally */
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
    .dashboard-card { border-radius:4px; background:#ffffff; margin-bottom:1.5rem; padding:1.5rem; border:1px solid #dee2e6; }
    .dashboard-card h5 { color:#212529; font-weight:600; font-size:1.125rem; margin-bottom:0.75rem; }
    .dashboard-card h6 { color:#212529; font-weight:600; font-size:1rem; margin-bottom:0.5rem; }
    .dashboard-card p { color:#6c757d; font-size:0.875rem; line-height:1.5; margin-bottom:0.5rem; }
    .stats-card { background:#ffffff; border-left:3px solid #800000; border-radius:4px; padding:1.5rem; border:1px solid #dee2e6; }
    .stats-card .stat-number { color:#800000; font-size:2rem; font-weight:700; line-height:1; }
    .stats-card .stat-label { color:#6c757d; font-size:0.875rem; font-weight:500; }
    .btn-maroon { background-color:#800000; color:#fff; border:none; font-weight:500; border-radius:4px; }
    .btn-maroon:hover { background-color:#660000; color:#fff; }
    .footer { background-color:#800000; color:#fff; text-align:center; width:100%; position:fixed; left:0; bottom:0; font-size:0.9rem; padding:0.5rem 0; z-index:1080; }
    .bg-maroon { background-color:#800000 !important; }
    /* =====================================================
       MODAL STYLES - Match admin side exactly
       ===================================================== */
    
    /* Fix modal z-index to appear above sidebar - Match admin side - ONLY when shown */
    .modal-backdrop.show {
        z-index: 1100 !important;
        background-color: rgba(0, 0, 0, 0.5) !important;
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
    }
    
    /* When backdrop is not shown, it should not block interactions */
    .modal-backdrop:not(.show) {
        display: none !important;
        pointer-events: none !important;
    }
    
    /* All modals should have higher z-index and cover full page - ONLY when shown */
    .modal.show {
        z-index: 1105 !important;
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        padding: 0 !important;
        margin: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        right: 0 !important;
        bottom: 0 !important;
    }
    
    /* When modal is not shown, it should be hidden and not block interactions */
    .modal:not(.show) {
        display: none !important;
        pointer-events: none !important;
    }
    
    /* Ensure modal dialog is centered */
    .modal-dialog {
        margin: 1.75rem auto !important;
        position: relative !important;
        max-width: 90vw !important;
    }
    
    /* Modal Centering and Styling - Match admin side */
    .modal-dialog-centered {
        display: flex;
        align-items: center;
        min-height: calc(100% - 1rem);
    }
    
    .modal-dialog-centered .modal-content {
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border: none;
    }
    
    /* Modal content styling - Match admin side */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    /* Modal header - Match admin side */
    .modal-header {
        background: linear-gradient(135deg, #800000, #660000);
        color: white;
        border-radius: 15px 15px 0 0;
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    
    .modal-header.bg-maroon,
    .modal-header.bg-primary {
        background: linear-gradient(135deg, #800000, #660000) !important;
    }
    
    .modal-header .modal-title {
        color: white;
        font-weight: 600;
    }
    
    .modal-header .btn-close,
    .modal-header .btn-close-white {
        filter: invert(1);
        opacity: 0.8;
    }
    
    .modal-header .btn-close:hover,
    .modal-header .btn-close-white:hover {
        opacity: 1;
    }
    
    /* Modal body - Match admin side */
    .modal-body {
        padding: 2rem;
        color: #212529;
        background: #fff;
    }
    
    /* Modal footer - Match admin side */
    .modal-footer {
        border-top: 1px solid #e9ecef;
        padding: 1rem 2rem;
        background-color: #f8f9fa;
        border-radius: 0 0 15px 15px;
    }
    
    /* Specific styling for Add User, Edit User, and View Details modals - Match logout modal - ONLY when shown */
    #addUserModal.show,
    #editUserModal.show,
    #userDetailsModal.show {
        z-index: 1105 !important;
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        padding: 0 !important;
        margin: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    /* When these modals are not shown, they should be hidden */
    #addUserModal:not(.show),
    #editUserModal:not(.show),
    #userDetailsModal:not(.show) {
        display: none !important;
        pointer-events: none !important;
    }
    
    /* Add User, Edit User, and User Details Modals - Full Screen */
    #addUserModal .modal-dialog,
    #editUserModal .modal-dialog,
    #userDetailsModal .modal-dialog {
        z-index: 1106 !important;
        margin: 0 !important;
        position: relative !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: 100vh !important;
        max-height: 100vh !important;
    }
    
    /* Add User, Edit User, and User Details Modal Content - Full Screen */
    #addUserModal .modal-content,
    #editUserModal .modal-content,
    #userDetailsModal .modal-content {
        border: none !important;
        border-radius: 0 !important;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5) !important;
        position: relative !important;
        z-index: 1107 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
    }
    
    #addUserModal .modal-header,
    #editUserModal .modal-header,
    #userDetailsModal .modal-header {
        flex-shrink: 0 !important;
        border-radius: 0 !important;
    }
    
    #addUserModal .modal-body,
    #editUserModal .modal-body,
    #userDetailsModal .modal-body {
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    
    #addUserModal .modal-footer,
    #editUserModal .modal-footer,
    #userDetailsModal .modal-footer {
        flex-shrink: 0 !important;
        border-radius: 0 !important;
    }
    
    /* Add User, Edit User, and User Details Modals - Full Screen Centered */
    #addUserModal .modal-dialog.modal-dialog-centered,
    #editUserModal .modal-dialog.modal-dialog-centered,
    #userDetailsModal .modal-dialog.modal-dialog-centered {
        display: flex !important;
        align-items: stretch !important;
        justify-content: stretch !important;
        height: 100vh !important;
        min-height: 100vh !important;
    }
    
    /* Enhanced backdrop for all modals to cover everything including sidebar */
    body.modal-open #addUserModal ~ .modal-backdrop,
    body.modal-open #editUserModal ~ .modal-backdrop,
    body.modal-open #userDetailsModal ~ .modal-backdrop,
    #addUserModal.show ~ .modal-backdrop,
    #editUserModal.show ~ .modal-backdrop,
    #userDetailsModal.show ~ .modal-backdrop {
        z-index: 1100 !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background-color: rgba(0, 0, 0, 0.6) !important;
    }
    
    /* Prevent body scroll when modal is open */
    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }
    
    /* CRITICAL: Ensure sidebar container is never affected by body.modal-open state or any other state */
    .sidebar {
        position: fixed !important;
        left: 0 !important;
        top: 70px !important;
        width: 280px !important;
        z-index: 1030 !important; /* Always below modal backdrop (9998) */
        transform: none !important;
        transition: none !important;
        pointer-events: auto !important;
        will-change: auto !important;
        backface-visibility: visible !important;
    }
    
    /* Ensure sidebar stays behind modal backdrop when modal is open - Match admin side behavior */
    body.modal-open .sidebar {
        position: fixed !important;
        left: 0 !important;
        top: 70px !important;
        width: 280px !important;
        z-index: 1030 !important; /* Lower than modal backdrop (9998) so it appears behind */
        transform: none !important;
        transition: none !important;
        pointer-events: none !important; /* Disable pointer events when modal is open */
    }
    
    /* Sidebar content should also be non-interactive when modal is open */
    body.modal-open .sidebar * {
        pointer-events: none !important;
        transform: none !important;
        transition: none !important;
    }
    
    /* When modal is NOT open, ensure sidebar and main content are fully interactive */
    body:not(.modal-open) .sidebar {
        pointer-events: auto !important;
    }
    
    body:not(.modal-open) .sidebar * {
        pointer-events: auto !important;
    }
    
    body:not(.modal-open) .main-content {
        pointer-events: auto !important;
    }
    
    body:not(.modal-open) .main-content * {
        pointer-events: auto !important;
    }
    
    /* CRITICAL: Disable ALL hover effects and transitions when modal is open */
    /* BUT ensure modal buttons and inputs remain clickable */
    body.modal-open *:not(.modal):not(.modal *):not(.sidebar):not(.sidebar *):not(button):not(input):not(select):not(textarea):not(a) {
        transition: none !important;
        animation: none !important;
        transform: none !important;
    }
    
    /* Ensure modal buttons are always clickable */
    .modal button,
    .modal input,
    .modal select,
    .modal textarea,
    .modal a,
    .modal label {
        pointer-events: auto !important;
        cursor: pointer !important;
    }
    .modal input[type="text"],
    .modal input[type="email"],
    .modal input[type="password"],
    .modal input[type="number"],
    .modal textarea {
        cursor: text !important;
    }
    
    /* Prevent any hover effects on page elements when modal is open */
    body.modal-open .card:hover,
    body.modal-open .dashboard-card:hover,
    body.modal-open .stats-card:hover,
    body.modal-open .ultra-modern-card:hover,
    body.modal-open table tr:hover {
        transform: none !important;
        box-shadow: none !important;
        background-color: inherit !important;
    }
    /* Multi-step form styling - hide non-active steps */
    .form-step { display: none; }
    .form-step.active { display: block; }
    /* Progress Steps Styling */
    /* Progress Steps Styling - Match admin side exactly */
    .progress-steps {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 1rem 0;
    }
    .progress-steps .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }
    .progress-steps .step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #e9ecef;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.875rem;
        border: 2px solid #e9ecef;
        /* No transition - prevents blinking */
    }
    .progress-steps .step.active .step-circle {
        background-color: #0d6efd;
        color: white;
        border-color: #0d6efd;
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
    }
    .progress-steps .step.completed .step-circle {
        background-color: #28a745;
        color: white;
        border-color: #28a745;
    }
    .progress-steps .step.completed .step-circle::after {
        content: '✓';
        font-size: 1.2rem;
    }
    .progress-steps .step-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6c757d;
        margin-top: 0.5rem;
        text-align: center;
        /* No transition - prevents blinking */
    }
    .progress-steps .step.active .step-label {
        color: #0d6efd;
    }
    .progress-steps .step.completed .step-label {
        color: #28a745;
    }
    .progress-steps .step-line {
        width: 60px;
        height: 2px;
        background-color: #e9ecef;
        margin: 0 1rem;
        /* No transition - prevents blinking */
    }
    .progress-steps .step.completed + .step-line {
        background-color: #28a745;
    }
    
    /* Comprehensive Responsive Styles */
    @media (max-width: 1400px) {
      .sidebar { width: 260px; }
      .main-content { margin-left: 260px; }
    }
    
    @media (max-width: 1200px) {
      .sidebar { width: 240px; }
      .main-content { margin-left: 240px; }
      .dashboard-card { padding: 1.25rem !important; }
      .modal-dialog.modal-xl { max-width: 900px !important; }
      .modal-dialog.modal-lg { max-width: 700px !important; }
    }
    
    @media (max-width: 991.98px) {
      .sidebar { display:none !important; }
      .main-content { 
        margin-left:0 !important; 
        padding-top:0.75rem !important; /* Reduced from 1rem */
        padding-bottom:1rem !important; 
        padding-left: 0.5rem !important; /* Reduced horizontal padding */
        padding-right: 0.5rem !important; /* Reduced horizontal padding */
        min-height:calc(100vh - 100px) !important; 
      }
      .dashboard-card { 
        padding:0.875rem !important; /* Reduced from 1rem */
        margin-bottom:1rem !important; 
        min-height:unset !important; 
        border-radius:8px !important;
      }
      .container-fluid { 
        padding-left: 0.5rem !important; /* Reduced from 0.75rem */
        padding-right: 0.5rem !important; /* Reduced from 0.75rem */
      }
      
      /* Reduce Bootstrap column padding on mobile */
      .main-content .row {
        margin-left: -0.25rem !important;
        margin-right: -0.25rem !important;
      }
      
      .main-content .row > [class*="col-"] {
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
      }
      
      /* Responsive tables with swipe support */
      .table-responsive {
        display: block !important;
        width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        scroll-behavior: smooth !important;
        scroll-snap-type: x proximity !important;
        position: relative !important;
        padding-bottom: 10px !important;
      }
      
      /* Visual scroll indicator */
      .table-responsive::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: 30px;
        background: linear-gradient(to left, rgba(255, 255, 255, 0.9), transparent);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 10;
      }
      
      .table-responsive.scrollable::after {
        opacity: 1;
      }
      
      .table-responsive.scrolled-to-end::after {
        opacity: 0;
      }
      
      .table {
        min-width: 600px !important;
        font-size: 0.85rem !important;
        border-collapse: separate;
        border-spacing: 0;
      }
      
      /* Sticky first column for better navigation */
      .table thead th:first-child,
      .table tbody td:first-child {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 5;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
      }
      
      .table thead th:first-child {
        background: #f8f9fa;
        z-index: 6;
      }
      
      .table th,
      .table td {
        padding: 0.5rem 0.5rem !important;
        white-space: nowrap !important;
      }
      
      /* Scroll hint for mobile */
      .table-responsive::before {
        content: '← Swipe to see more →';
        position: absolute;
        top: 50%;
        right: 20px;
        transform: translateY(-50%);
        background: rgba(128, 0, 0, 0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 20;
        pointer-events: none;
        opacity: 0;
        animation: fadeInOut 3s ease-in-out;
      }
      
      @keyframes fadeInOut {
        0%, 100% { opacity: 0; }
        10%, 90% { opacity: 1; }
      }
      
      /* Responsive forms */
      .form-control,
      .form-select {
        font-size: 0.9rem !important;
        padding: 0.5rem 0.75rem !important;
      }
      
      .form-label {
        font-size: 0.85rem !important;
        margin-bottom: 0.5rem !important;
      }
      
      /* Responsive buttons */
      .btn {
        padding: 0.5rem 1rem !important;
        font-size: 0.875rem !important;
      }
      
      .btn-sm {
        padding: 0.375rem 0.75rem !important;
        font-size: 0.8rem !important;
      }
      
      /* Responsive modals */
      .modal-dialog {
        max-width: calc(100vw - 1rem) !important;
        margin: 0.5rem auto !important;
      }
      
      .modal-body {
        padding: 1rem !important;
        font-size: 0.9rem !important;
        max-height: calc(100vh - 150px) !important;
      }
      
      .modal-header {
        padding: 0.75rem 1rem !important;
      }
      
      .modal-header .modal-title {
        font-size: 1rem !important;
      }
      
      .modal-footer {
        padding: 0.75rem 1rem !important;
        flex-wrap: wrap !important;
      }
      
      .modal-footer .btn {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        margin: 0.25rem !important;
      }
      
      /* Responsive cards */
      .stats-card {
        padding: 1rem !important;
      }
      
      .stats-card .stat-number {
        font-size: 1.75rem !important;
      }
      
      .stats-card .stat-label {
        font-size: 0.8rem !important;
      }
      
      /* Responsive search and filters */
      .d-flex.gap-2,
      .d-flex.gap-3 {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
      }
      
      /* Responsive pagination */
      .pagination {
        flex-wrap: wrap !important;
        justify-content: center !important;
      }
      
      .pagination .page-link {
        padding: 0.375rem 0.5rem !important;
        font-size: 0.8rem !important;
      }
      
      /* Responsive badges */
      .badge {
        font-size: 0.7rem !important;
        padding: 0.25rem 0.5rem !important;
      }
      
      /* Responsive progress steps */
      .progress-steps {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
      }
      
      .progress-steps .step-line {
        width: 40px !important;
        margin: 0 0.5rem !important;
      }
      
      .progress-steps .step-circle {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.75rem !important;
      }
      
      .progress-steps .step-label {
        font-size: 0.7rem !important;
      }
    }
    
    @media (max-width: 768px) {
      .main-content {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
      }
      
      .dashboard-card {
        padding: 0.75rem !important;
      }
      
      .dashboard-card h5 {
        font-size: 1rem !important;
      }
      
      .dashboard-card h6 {
        font-size: 0.9rem !important;
      }
      
      .table {
        font-size: 0.8rem !important;
      }
      
      .table th,
      .table td {
        padding: 0.4rem 0.4rem !important;
      }
      
      .btn {
        padding: 0.4rem 0.75rem !important;
        font-size: 0.8rem !important;
      }
      
      .form-control,
      .form-select {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.6rem !important;
      }
      
      .modal-body {
        padding: 0.75rem !important;
        font-size: 0.85rem !important;
      }
      
      .modal-header {
        padding: 0.5rem 0.75rem !important;
      }
      
      .modal-footer {
        padding: 0.5rem 0.75rem !important;
      }
    }
    
    @media (max-width: 576px) {
      .main-content {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
      }
      
      .dashboard-card {
        padding: 0.5rem !important;
        margin-bottom: 0.75rem !important;
      }
      
      .table {
        font-size: 0.75rem !important;
        min-width: 500px !important;
      }
      
      .table th,
      .table td {
        padding: 0.3rem 0.3rem !important;
      }
      
      .btn {
        padding: 0.35rem 0.6rem !important;
        font-size: 0.75rem !important;
      }
      
      .btn-sm {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.7rem !important;
      }
      
      .form-control,
      .form-select {
        font-size: 0.8rem !important;
        padding: 0.35rem 0.5rem !important;
      }
      
      .modal-dialog {
        margin: 0.25rem !important;
        max-width: calc(100vw - 0.5rem) !important;
      }
      
      .modal-body {
        padding: 0.5rem !important;
        font-size: 0.8rem !important;
        max-height: calc(100vh - 120px) !important;
      }
      
      .modal-header {
        padding: 0.5rem !important;
      }
      
      .modal-header .modal-title {
        font-size: 0.9rem !important;
      }
      
      .modal-footer {
        padding: 0.5rem !important;
      }
      
      .stats-card {
        padding: 0.75rem !important;
      }
      
      .stats-card .stat-number {
        font-size: 1.5rem !important;
      }
      
      .progress-steps .step-circle {
        width: 28px !important;
        height: 28px !important;
        font-size: 0.7rem !important;
      }
      
      .progress-steps .step-line {
        width: 30px !important;
      }
    }
    
    @media (max-width: 400px) {
      .table {
        min-width: 400px !important;
        font-size: 0.7rem !important;
      }
      
      .btn {
        padding: 0.3rem 0.5rem !important;
        font-size: 0.7rem !important;
      }
      
      .modal-body {
        font-size: 0.75rem !important;
      }
    }
    
    /* Iframe mode specific responsive adjustments */
    html.iframe-mode .main-content,
    body.iframe-mode .main-content {
      padding-left: 0.5rem !important;
      padding-right: 0.5rem !important;
    }
    
    @media (max-width: 991.98px) {
      html.iframe-mode .main-content,
      body.iframe-mode .main-content {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
      }
    }
  </style>
  <script>
    // Detect if page is loaded in an iframe and add class immediately
    // This must run before the page renders to hide sidebar properly
    (function() {
      if (window.self !== window.top) {
        // Page is loaded in an iframe - add class to html and body immediately
        document.documentElement.classList.add('iframe-mode');
        if (document.body) {
          document.body.classList.add('iframe-mode');
        } else {
          // If body doesn't exist yet, wait for it
          document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('iframe-mode');
          });
        }
      }
    })();
    </script>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top shadow-sm" style="height:70px;">
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
  <div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
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
          <a class="nav-link active" href="user_management.php" onclick="closeOffcanvas(); return false;">
            <span class="bi bi-people me-2"></span> User Management
          </a>
          <a class="nav-link" href="manage_roles.php" onclick="closeOffcanvas(); return false;">
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
  <div class="sidebar d-none d-lg-flex flex-column">
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
        <a class="nav-link active" href="user_management.php">
          <span class="bi bi-people me-2"></span> User Management
        </a>
        <a class="nav-link" href="manage_roles.php">
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
  <main class="main-content">
    <div class="container-fluid">

<?php include __DIR__ . '/user_management_content.php'; ?>

    </div>
  </main>

</body>
</html>