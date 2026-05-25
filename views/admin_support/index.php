<?php
/**
 * Admin Support Dashboard – User Management & Manage Roles
 * Connected to admin, instructor, moderator via shared DB (account, user_roles, roles, permissions, role_permissions, user_permissions, department).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

if (!requireRole('Admin support', __DIR__ . '/../../admin/auth/login_admin.php')) {
    exit;
}

$acc_id = (int) $_SESSION['acc_id'];

// ---------- Handle role permission updates (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $role_message = ['error', 'CSRF token mismatch'];
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM role_permissions");
            $stmt->execute();
            $stmt->close();

            if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
                $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $role_id => $permission_ids) {
                    $role_id = (int) $role_id;
                    if ($role_id <= 0) continue;
                    foreach ((array) $permission_ids as $pid) {
                        $pid = (int) $pid;
                        if ($pid > 0) {
                            $stmt->bind_param("ii", $role_id, $pid);
                            $stmt->execute();
                        }
                    }
                }
                $stmt->close();
            }
            $conn->commit();
            logAdminAction($acc_id, 'update_role_permissions', 'Updated system role permissions');
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=roles&success=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $role_message = ['error', $e->getMessage()];
        }
    }
}

// ---------- Current user for header ----------
$stmt = $conn->prepare("
    SELECT a.acc_user, r.role_name
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE a.acc_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $acc_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$username = $row ? $row['acc_user'] : 'User';
$role_name = $row ? $row['role_name'] : 'Admin support';

// ---------- Data for User Management tab ----------
$users_result = $conn->query("
    SELECT a.acc_id, a.fname, a.lname, a.acc_user, a.acc_email, a.acc_status, a.profile_picture,
           r.id AS role_id, r.role_name, d.dept_id, d.dept_name
    FROM account a
    LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN department d ON a.dept_id = d.dept_id
    ORDER BY a.lname, a.fname
");
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

$roles_result = $conn->query("SELECT id, role_name FROM roles ORDER BY id");
$roles = $roles_result ? $roles_result->fetch_all(MYSQLI_ASSOC) : [];

$dept_result = $conn->query("SELECT dept_id, dept_name FROM department WHERE dept_status = 'Active' ORDER BY dept_name");
$departments = $dept_result ? $dept_result->fetch_all(MYSQLI_ASSOC) : [];

// ---------- Data for Manage Roles tab ----------
$perms_result = $conn->query("
    SELECT id, permission_key, permission_name, permission_display_name, module
    FROM permissions
    ORDER BY module, permission_display_name
");
$permissions = $perms_result ? $perms_result->fetch_all(MYSQLI_ASSOC) : [];

$rp_result = $conn->query("SELECT role_id, permission_id FROM role_permissions");
$role_permissions = [];
if ($rp_result) {
    while ($r = $rp_result->fetch_assoc()) {
        $rid = (int) $r['role_id'];
        $pid = (int) $r['permission_id'];
        if (!isset($role_permissions[$rid])) $role_permissions[$rid] = [];
        $role_permissions[$rid][] = $pid;
    }
}

$csrf_token = generateCSRFToken();

// Determine which tab to show
$allowed_tabs = ['overview', 'users', 'roles'];
$requested_tab = $_GET['tab'] ?? 'overview';
$current_tab = in_array($requested_tab, $allowed_tabs, true) ? $requested_tab : 'overview';

$success_param = isset($_GET['success']) ? (int) $_GET['success'] : 0;
$status_param = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Support – EVSU OCC Scheduling System</title>

  <!-- Bootstrap & Icons -->
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">

  <!-- Shared styles (same as admin side) -->
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/main.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <link rel="stylesheet" href="/assets/css/admin_support.css">
</head>
<body class="as-support-dashboard">
  <?php
    // Use the same header and unified sidebar shell as the admin dashboard
    require_once __DIR__ . '/../../config/session.php';

    // Header (top navbar)
    include __DIR__ . '/../../includes/dashboard/dashboard_header.php';

    // Prepare variables expected by sidebar_base.php
    $userDisplayData = [
        'username'  => $username,
        'role_name' => $role_name,
        'dept_name' => '' // Admin support is system-wide; no specific department label
    ];

    $profileData = [
        'profile_picture' => null // Can be wired later if needed
    ];

    $usernameSidebar   = $userDisplayData['username'];
    $roleNameSidebar   = $userDisplayData['role_name'];
    $deptNameSidebar   = $userDisplayData['dept_name'];
    $profileImageSidebar = $profileData['profile_picture'];

    // Sidebar (role-aware, permission-aware)
    $username   = $usernameSidebar;
    $roleName   = $roleNameSidebar;
    $dept_name  = $deptNameSidebar;
    $profileImage = $profileImageSidebar;

    include __DIR__ . '/../../includes/layout/sidebar_base.php';
  ?>

  <div class="main-content">
    <div class="container-fluid py-4">
      <?php if ($current_tab === 'roles' && $success_param === 1): ?>
        <div class="alert alert-success alert-dismissible fade show">
          Role permissions saved.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($current_tab === 'users' && $status_param === 'created'): ?>
        <div class="alert alert-success alert-dismissible fade show">
          User created successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($current_tab === 'users' && $status_param === 'updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">
          User updated successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($current_tab === 'users' && $status_param === 'perm_saved'): ?>
        <div class="alert alert-success alert-dismissible fade show">
          User permissions saved.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($current_tab === 'roles' && !empty($role_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($role_message[1]) ?></div>
      <?php endif; ?>

      <?php
        if ($current_tab === 'overview') {
            include __DIR__ . '/components/overview_tab.php';
        } elseif ($current_tab === 'users') {
            include __DIR__ . '/components/user_management_tab.php';
        } elseif ($current_tab === 'roles') {
            include __DIR__ . '/components/manage_roles_tab.php';
        }
      ?>

      <?php
        // Make sure Add Department modal is available on all tabs that might use it
        include __DIR__ . '/components/modal_department.php';
      ?>
    </div>
  </div>

  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/sweetalert2.all.min.js"></script>
  <script src="/assets/js/admin_support_departments.js"></script>
  <script>
    // Safety: if any stale Bootstrap backdrop or modal-open state leaks in, clear it on load
    document.addEventListener('DOMContentLoaded', function () {
      document.body.classList.remove('modal-open');
      document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.remove();
      });
    });
  </script>
</body>
</html>
