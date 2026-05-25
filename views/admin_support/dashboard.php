<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../config/database.php';
include '../../includes/utils/pagination.php';
require_once '../../includes/auth/security_middleware.php';

/* Gate: only Admin support can access */
if (!requireRole('Admin support', '../../admin/auth/login_admin.php')) {
    exit();
}

$acc_id = $_SESSION['acc_id'];

// Handle role permission updates (moved from manage_roles.php to prevent headers already sent error)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    // CSRF token validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "CSRF token mismatch";
    } else {
        $conn->begin_transaction();
        try {
            // Clear existing role permissions
            $stmt = $conn->prepare("DELETE FROM role_permissions");
            $stmt->execute();
            $stmt->close();
            
            // Insert new permissions
            if (isset($_POST['permissions'])) {
                $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $role_id => $permission_ids) {
                    foreach ($permission_ids as $permission_id) {
                        $stmt->bind_param("ii", $role_id, $permission_id);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
            
            $conn->commit();
            $success_message = "Role permissions updated successfully!";
            
            // Log the action
            logAdminAction($_SESSION['acc_id'], 'update_role_permissions', 'Updated system role permissions');
            
            // Redirect back to the same page to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating permissions: " . $e->getMessage();
        }
    }
}

/* ✅ Fetch username + (one) role label for header */
$query = "
    SELECT a.acc_user, r.role_name
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE a.acc_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $acc_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$username = $user ? $user['acc_user'] : "User";
$role     = $user ? $user['role_name'] : "Unknown";

$stmt->close();
?>

<?php
$baseQuery = "
    SELECT a.acc_id, a.fname, a.lname, a.acc_user, a.acc_status, a.profile_picture, r.role_name, d.dept_name
    FROM account a
    LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN department d ON a.dept_id = d.dept_id
";

$pagination = paginateQuery($conn, $baseQuery, 10);
$result = $pagination['result'];
$page = $pagination['page'];
$totalPages = $pagination['totalPages'];
$search = $pagination['search'];

$conn->close();
?>

<?php 
// Removed automatic SweetAlert on page load - user requested removal
// if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
<!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: 'Admin account has been created and email sent.',
    confirmButtonColor: '#3085d6'
  });
</script> -->
<?php // endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVSU-OCC Scheduling System</title>
  <link rel="icon" type="image/png" href="../../public/assets/img/evsu-logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/main.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
  <style>
    .as-support-dashboard .navbar { z-index: 1100; }
    .sidebar .nav-link { color:#fff !important; font-weight:500; padding:0.4rem 0.8rem; margin:0.05rem 0.4rem; border-radius:6px; transition:all 0.3s ease; display:flex; align-items:center; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar .nav-link .bi { margin-right:0.5rem; font-size:1rem; width:16px; text-align:center; flex-shrink:0; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background:rgba(255,255,255,0.15); color:#fff !important; transform:translateX(4px); }
    .sidebar .nav-section { margin-bottom:0.5rem; }
    .sidebar .nav-section small { 
        margin-bottom:0.15rem; 
        padding:0.15rem 0.8rem; 
        font-size:0.7rem !important; 
        font-weight:500 !important; 
        letter-spacing:0.5px;
    }
    .sidebar hr { margin:0.5rem 0; }
    .sidebar-offcanvas { background:#800000 !important; color:#fff !important; width:280px; max-height:100vh; overflow-y:auto; }
    /* Ensure offcanvas sidebar is hidden on desktop */
    @media (min-width: 992px) {
      .sidebar-offcanvas {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
      }
      .sidebar-offcanvas.show {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
      }
    }
    .sidebar-offcanvas .nav-section { margin-bottom:0.5rem; }
    .sidebar-offcanvas .nav-section small { 
        margin-bottom:0.15rem; 
        padding:0.15rem 0.8rem; 
        font-size:0.7rem !important; 
        font-weight:500 !important; 
        letter-spacing:0.5px;
    }
    .sidebar-offcanvas .nav-link { color:#fff !important; font-weight:500; padding:0.4rem 0.8rem; margin:0.05rem 0.4rem; border-radius:6px; transition:all 0.3s ease; display:flex; align-items:center; }
    .sidebar-offcanvas .nav-link .bi { margin-right:0.5rem; font-size:1rem; width:16px; text-align:center; flex-shrink:0; }
    .sidebar-offcanvas .nav-link.active, .sidebar-offcanvas .nav-link:hover { background:rgba(255,255,255,0.15) !important; color:#fff !important; }
    .main-content { margin-left:280px; padding-top:65px; padding-bottom:80px; min-height:calc(100vh - 170px); background:#ffffff; }
    /* Match Admin Dashboard Card Design */
    .dashboard-card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); background:#ffffff; margin-bottom:2rem; padding:1.5rem; min-height:auto; border:1px solid rgba(0, 0, 0, 0.05); position:relative; overflow:hidden; transition:transform 0.2s ease; }
    .dashboard-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .dashboard-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #800000, #f5f5dc); }
    /* Match Admin Dashboard Typography */
    .dashboard-card h5 { color:#495057; font-weight:600; font-size:1.125rem; margin-bottom:0.75rem; }
    .dashboard-card h6 { color:#495057; font-weight:600; font-size:1rem; margin-bottom:0.5rem; }
    .dashboard-card p { color:#6c757d; font-size:0.875rem; line-height:1.5; margin-bottom:0.5rem; }
    .dashboard-card .text-muted { color:#999999; font-weight:400; }
    .dashboard-card .text-primary { color:#333333; font-weight:500; }
    .dashboard-card .text-success { color:#333333; font-weight:500; }
    .dashboard-card .text-warning { color:#333333; font-weight:500; }
    .dashboard-card .text-danger { color:#333333; font-weight:500; }
    .dashboard-card .text-info { color:#333333; font-weight:500; }
    .dashboard-card .table { color:#333333; }
    /* Match Admin Dashboard Table Styling */
    .dashboard-card .table th { color:#495057; font-weight:600; border-color:#e9ecef; background:#f8f9fa; }
    .dashboard-card .table td { color:#495057; border-color:#e9ecef; font-weight:400; }
    /* Responsive tables - enable horizontal scroll on mobile with swipe support */
    .table-responsive { 
      overflow-x: auto; 
      -webkit-overflow-scrolling: touch; 
      display: block; 
      width: 100%; 
      position: relative;
      scroll-behavior: smooth;
    }
    
    /* Scroll snap for better swipe experience */
    .table-responsive {
      scroll-snap-type: x mandatory;
      scroll-padding: 0;
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
    
    /* Hide scroll indicator when scrolled to end */
    .table-responsive.scrolled-to-end::after {
      opacity: 0;
    }
    
    @media (max-width: 991.98px) {
      .table-responsive { 
        display: block !important; 
        width: 100% !important; 
        overflow-x: auto !important; 
        -webkit-overflow-scrolling: touch !important;
        scroll-snap-type: x proximity !important;
        /* Add padding for better touch targets */
        padding-bottom: 10px !important;
      }
      
      .table { 
        min-width: 600px !important; 
        width: 100% !important;
        /* Enable scroll snap on table cells */
        border-collapse: separate;
        border-spacing: 0;
      }
      
      /* Make first column sticky for better navigation */
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
    }
    
    @media (max-width: 576px) {
      .table { 
        min-width: 500px !important;
        font-size: 0.85rem !important;
      }
      
      .table th,
      .table td {
        padding: 0.5rem 0.5rem !important;
        white-space: nowrap !important;
      }
    }
    .dashboard-card .form-label { color:#333333; font-weight:500; font-size:0.9rem; }
    .dashboard-card .form-control { color:#333333; border-color:#ced4da; background:#ffffff; font-weight:400; }
    
    /* Dark background - white text */
    .dashboard-card .bg-dark, .dashboard-card .bg-maroon, .dashboard-card .bg-primary, .dashboard-card .bg-secondary, .dashboard-card .bg-success, .dashboard-card .bg-danger, .dashboard-card .bg-warning, .dashboard-card .bg-info {
        color:#ffffff !important;
    }
    .dashboard-card .bg-dark *, .dashboard-card .bg-maroon *, .dashboard-card .bg-primary *, .dashboard-card .bg-secondary *, .dashboard-card .bg-success *, .dashboard-card .bg-danger *, .dashboard-card .bg-warning *, .dashboard-card .bg-info * {
        color:#ffffff !important;
    }
    
    /* Light background - dark text */
    .dashboard-card .bg-light, .dashboard-card .bg-white, .dashboard-card .bg-transparent {
        color:#000000 !important;
    }
    .dashboard-card .bg-light *, .dashboard-card .bg-white *, .dashboard-card .bg-transparent * {
        color:#000000 !important;
    }
    .dashboard-card .form-control, .dashboard-card .form-select { border:2px solid #e9ecef; border-radius:8px; transition:all 0.3s ease; }
    .dashboard-card .form-control:focus, .dashboard-card .form-select:focus { border-color:#800000; box-shadow:0 0 0 0.2rem rgba(128,0,0,0.25); outline:none; }
    .dashboard-card .btn { font-weight:500; }
    .btn-maroon { background:linear-gradient(135deg, #800000 0%, #660000 100%); color:#fff; border:none; font-weight:600; border-radius:8px; transition:all 0.3s ease; }
    .btn-maroon:hover { background:linear-gradient(135deg, #660000 0%, #800000 100%); color:#fff; transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.12); }
    .btn-outline-maroon { color:#800000; border:2px solid #800000; background:transparent; border-radius:8px; font-weight:600; transition:all 0.3s ease; }
    .btn-outline-maroon:hover { background:#800000; color:#fff; transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.12); }
    
    /* Simple typography */
    /* Match Admin Dashboard Typography */
    .dashboard-card .card-title { color:#495057; font-weight:600; font-size:1.125rem; margin-bottom:0.75rem; }
    .dashboard-card .card-subtitle { color:#495057; font-weight:600; font-size:1rem; margin-bottom:0.5rem; }
    .dashboard-card .card-text { color:#6c757d; font-size:0.875rem; line-height:1.5; font-weight:400; }
    .dashboard-card .list-group-item { color:#333333; border-color:#e9ecef; font-weight:400; background:#ffffff; }
    .dashboard-card .list-group-item.active { background:#800000; border-color:#800000; color:#ffffff; font-weight:600; }
    .dashboard-card .badge { font-weight:600; border-radius:20px; padding:0.5rem 0.75rem; }
    .dashboard-card .alert { border:none; border-radius:8px; font-weight:400; border-left:4px solid; padding:0.875rem 1rem; }
    .dashboard-card .alert-success { background:#ffffff; color:#333333; border:1px solid #e9ecef; }
    .dashboard-card .alert-warning { background:#ffffff; color:#333333; border:1px solid #e9ecef; }
    .dashboard-card .alert-danger { background:#ffffff; color:#333333; border:1px solid #e9ecef; }
    .dashboard-card .alert-info { background:#ffffff; color:#333333; border:1px solid #e9ecef; }
    
    /* Match Admin Dashboard Stat Cards */
    .stats-card { background:#ffffff; border-left:4px solid #800000; border-radius:12px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid rgba(0, 0, 0, 0.05); transition:transform 0.2s ease; }
    .stats-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .stats-card .stat-number { color:#800000; font-size:2.5rem; font-weight:800; line-height:1; background:linear-gradient(135deg, #800000, #f5f5dc); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .stats-card .stat-label { color:#6c757d; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; }
    
    /* Simple form styling */
    .dashboard-card .form-select { color:#333333; border-color:#ced4da; background:#ffffff; font-weight:400; }
    .dashboard-card .form-select:focus { border-color:#800000; box-shadow:0 0 0 0.2rem rgba(128,0,0,0.25); }
    .dashboard-card .form-check-label { color:#333333; font-weight:400; }
    
    /* Color system matching ADMIN */
    .dashboard-card .bg-dark, .dashboard-card .bg-maroon {
        color:#ffffff;
        background:#800000;
    }
    .dashboard-card .bg-dark *, .dashboard-card .bg-maroon * {
        color:#ffffff;
    }
    
    .dashboard-card .bg-light, .dashboard-card .bg-white, .dashboard-card .bg-transparent {
        color:#212529;
        background:#ffffff;
    }
    .dashboard-card .bg-light *, .dashboard-card .bg-white *, .dashboard-card .bg-transparent * {
        color:#212529;
    }
    
    .footer { background-color:#800000; color:#fff; text-align:center; width:100%; position:fixed; left:0; bottom:0; font-size:0.9rem; padding:0.5rem 0; z-index:1080; }
    .bg-maroon { background-color:#800000 !important; }
    
    /* Notification Icon Styles */
    #notificationBtn {
      transition: all 0.3s ease;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 40px !important;
      height: 40px !important;
      padding: 0 !important;
      border: none !important;
      background: transparent !important;
    }
    
    #notificationBtn:hover {
      transform: scale(1.1);
      opacity: 0.9;
      background: rgba(255, 255, 255, 0.1) !important;
      border-radius: 50% !important;
    }
    
    #notificationBtn i {
      font-size: 1.25rem !important;
      line-height: 1 !important;
    }
    
    #notificationBadge {
      position: absolute !important;
      top: -2px !important;
      right: -2px !important;
      min-width: 18px !important;
      height: 18px !important;
      padding: 0 5px !important;
      font-size: 0.7rem !important;
      font-weight: 600 !important;
      line-height: 18px !important;
      text-align: center !important;
      z-index: 1051 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      box-shadow: 0 0 0 2px #fff !important;
      border: none !important;
      border-radius: 9px !important;
      white-space: nowrap !important;
    }
    
    /* Mobile: Make badge slightly larger */
    @media (max-width: 991.98px) {
      #notificationBadge {
        min-width: 20px !important;
        height: 20px !important;
        padding: 0 6px !important;
        font-size: 0.75rem !important;
        line-height: 20px !important;
        top: -3px !important;
        right: -3px !important;
        border-radius: 10px !important;
        box-shadow: 0 0 0 2px #fff !important;
      }
    }
    
    @media (max-width: 576px) {
      #notificationBadge {
        min-width: 22px !important;
        height: 22px !important;
        padding: 0 7px !important;
        font-size: 0.8rem !important;
        line-height: 22px !important;
        top: -4px !important;
        right: -4px !important;
        border-radius: 11px !important;
        box-shadow: 0 0 0 2px #fff !important;
      }
    }
    
    /* Ensure notification container is properly aligned */
    .navbar .d-flex.align-items-center {
      display: flex !important;
      align-items: center !important;
      flex-direction: row !important;
      flex-wrap: nowrap !important;
      gap: 0.75rem !important;
    }
    
    /* Navbar container alignment */
    .navbar .container-fluid {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      flex-wrap: nowrap !important;
    }
    
    /* Ensure navbar brand doesn't shrink */
    .navbar-brand {
      flex-shrink: 0 !important;
      white-space: nowrap !important;
    }
    
    /* Mobile responsive adjustments */
    @media (max-width: 991.98px) {
      #notificationBtn {
        width: 36px !important;
        height: 36px !important;
      }
      
      #notificationBtn i {
        font-size: 1.1rem !important;
      }
      
      .navbar .d-flex.align-items-center {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 0.5rem !important;
      }
      
      /* Ensure notification and menu icons stay horizontal */
      .navbar .d-flex.align-items-center > div,
      .navbar .d-flex.align-items-center > button {
        flex-shrink: 0 !important;
        display: inline-flex !important;
      }
      
      .navbar {
        min-height: 50px !important; /* Reduced from default */
        padding: 0.15rem 0 !important; /* Reduced vertical padding */
      }
      
      .navbar .container-fluid {
        padding-left: 0.375rem !important; /* Reduced from 0.75rem */
        padding-right: 0.375rem !important; /* Reduced from 0.75rem */
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        gap: 0.25rem 0.375rem !important; /* Reduced gap */
      }
      
      .navbar-brand img {
        width: 26px !important;
        height: 26px !important;
        margin-right: 0.375rem !important;
      }
      
      .navbar-toggler {
        padding: 0.2rem 0.4rem !important;
        margin-right: 0.375rem !important;
      }
      
      .navbar .d-flex.align-items-center {
        gap: 0.5rem !important; /* Reduced from 0.75rem */
      }
      
      .navbar .d-flex.align-items-center .btn {
        padding: 0.2rem 0.4rem !important;
      }
      
      .navbar-brand {
        font-size: 0.9rem !important;
      }
      
      .navbar-brand img {
        width: 28px !important;
        height: 28px !important;
      }
    }
    
    @media (max-width: 576px) {
      .navbar {
        min-height: 48px !important; /* Further reduced for very small screens */
      }
      
      .navbar .container-fluid {
        padding-left: 0.3rem !important;
        padding-right: 0.3rem !important;
        padding-top: 0.2rem !important;
        padding-bottom: 0.2rem !important;
        gap: 0.2rem 0.3rem !important;
      }
      
      .navbar-brand img {
        width: 24px !important;
        height: 24px !important;
        margin-right: 0.3rem !important;
      }
      
      .navbar .d-flex.align-items-center {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 0.5rem !important;
      }
      
      /* Ensure notification and menu icons stay horizontal on small screens */
      .navbar .d-flex.align-items-center > div,
      .navbar .d-flex.align-items-center > button {
        flex-shrink: 0 !important;
        display: inline-flex !important;
        margin: 0 !important;
      }
      
      #notificationBtn {
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        margin: 0 !important;
      }
      
      #notificationBtn i {
        font-size: 1rem !important;
      }
      
      .navbar-toggler {
        padding: 0.25rem !important;
        margin: 0 !important;
        flex-shrink: 0 !important;
      }
    }
    
    #notificationDropdown {
      border-radius: 14px;
      box-shadow: 0 14px 36px rgba(0,0,0,0.18);
      padding: 0 0 10px 0;
      border: 1px solid #e5e7eb;
      background: #fff;
    }
    
    /* Mobile: Make notification dropdown responsive */
    @media (max-width: 991.98px) {
      #notificationDropdown {
        min-width: calc(100vw - 2rem) !important;
        max-width: calc(100vw - 2rem) !important;
        width: calc(100vw - 2rem) !important;
        margin-left: 1rem !important;
        margin-right: 1rem !important;
        max-height: calc(100vh - 120px) !important;
        border-radius: 12px !important;
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        top: auto !important;
        transform: none !important;
      }
      
      /* Ensure dropdown is positioned correctly on mobile */
      .dropdown-menu-end {
        right: 0 !important;
        left: 0 !important;
        margin: 0.5rem 1rem !important;
      }
    }
    
    @media (max-width: 576px) {
      #notificationDropdown {
        min-width: calc(100vw - 1rem) !important;
        max-width: calc(100vw - 1rem) !important;
        width: calc(100vw - 1rem) !important;
        margin-left: 0.5rem !important;
        margin-right: 0.5rem !important;
        max-height: calc(100vh - 100px) !important;
        border-radius: 10px !important;
      }
      
      .dropdown-menu-end {
        margin: 0.5rem !important;
      }
    }
    
    #notificationDropdown .dropdown-header {
      position: sticky;
      top: 0;
      background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
      padding: 14px 16px;
      z-index: 2;
      border-bottom: 1px solid #e5e7eb;
    }
    
    #notificationDropdown .dropdown-header .fw-bold {
      color: #800000;
      font-size: 1rem;
    }
    
    #notificationDropdown .dropdown-header button {
      color: #6c757d !important;
    }
    
    #notificationList {
      padding: 10px 10px 6px 10px;
    }
    
    #notificationDropdown .dropdown-item {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #f0f0f0;
      transition: all 0.2s;
    }
    
    /* Mobile: Make notification items more readable */
    @media (max-width: 991.98px) {
      #notificationDropdown .dropdown-item {
        padding: 1rem !important;
        font-size: 0.9rem !important;
      }
      
      #notificationDropdown .notification-title {
        font-size: 0.95rem !important;
        font-weight: 600 !important;
      }
      
      #notificationDropdown .notification-message {
        font-size: 0.85rem !important;
        line-height: 1.4 !important;
      }
      
      #notificationDropdown .notification-time {
        font-size: 0.8rem !important;
        margin-top: 0.25rem !important;
      }
    }
    
    #notificationDropdown .dropdown-item:hover {
      background-color: #f8f9fa;
      transform: translateX(2px);
    }
    
    #notificationDropdown .dropdown-item.unread {
      background-color: #e7f3ff;
      font-weight: 500;
    }
    
    #notificationDropdown .dropdown-item.unread:hover {
      background-color: #d0e7ff;
    }
    
    #notificationDropdown .notification-time {
      font-size: 0.75rem;
      color: #6c757d;
    }
    
    #notificationDropdown .notification-title {
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.25rem;
    }
    
    #notificationDropdown .notification-message {
      font-size: 0.875rem;
      color: #495057;
      margin-bottom: 0;
    }
    
    /* Fluid Typography */
    html { font-size: 16px; }
    @media (max-width: 1200px) { html { font-size: 15px; } }
    @media (max-width: 992px) { html { font-size: 14px; } }
    @media (max-width: 768px) { html { font-size: 14px; } }
    @media (max-width: 576px) { html { font-size: 13px; } }
    
    /* Responsive Design */
    @media (max-width: 1400px) {
      .sidebar { width: 260px; }
      .main-content { margin-left: 260px; }
    }
    
    @media (max-width: 1200px) {
      .sidebar { width: 240px; }
      .main-content { margin-left: 240px; }
      .dashboard-card { padding: 1.25rem !important; }
    }
    
    /* Tablet and Mobile */
    @media (max-width: 991.98px) {
      .sidebar { display:none !important; visibility: hidden !important; }
      /* Ensure desktop sidebar is completely hidden on mobile */
      .sidebar.d-none.d-lg-flex {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
      }
      .main-content { 
        margin-left:0 !important; 
        padding-top:55px !important; /* Reduced from 70px to match smaller navbar */
        padding-bottom:60px !important;
        min-height:calc(100vh - 130px) !important; 
        padding-left:0.75rem !important;
        padding-right:0.75rem !important;
      }
      
      /* Hide desktop-only elements in overview on mobile */
      .main-content .d-flex.align-items-center.gap-3 {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 1rem !important;
      }
      
      .main-content .search-box {
        width: 100% !important;
      }
      
      .main-content .search-box input {
        width: 100% !important;
      }
      
      /* Stack overview cards vertically on mobile */
      .main-content .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      
      .main-content .col-lg-4,
      .main-content .col-lg-8,
      .main-content .col-md-6 {
        padding-left: 0.25rem !important; /* Reduced from 0.5rem */
        padding-right: 0.25rem !important; /* Reduced from 0.5rem */
        margin-bottom: 1rem !important;
      }
      
      /* Make dashboard cards full width on mobile */
      .main-content .dashboard-card {
        margin-bottom: 1rem !important;
      }
      
      /* Hide charts on very small screens or make them scrollable */
      .main-content canvas {
        max-width: 100% !important;
        height: auto !important;
      }
      
      /* Adjust iframe height for mobile */
      .main-content iframe {
        height: calc(100vh - 150px) !important;
        min-height: 500px !important;
        width: 100% !important;
        border: none !important;
        display: block !important;
      }
      
      /* Ensure iframe content is responsive */
      .main-content .tab-content {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
      }
      
      .main-content .tab-content iframe {
        max-width: 100% !important;
        overflow-x: hidden !important;
        display: block !important;
      }
      
      /* Responsive iframe containers */
      .tab-content {
        padding: 0 !important;
        margin: 0 !important;
      }
      
      #userManagement,
      #manageRoles,
      #activityLogs,
      #reports,
      #scheduleRecords {
        padding: 0 !important;
        margin: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
      }
      
      #userManagement iframe,
      #manageRoles iframe,
      #activityLogs iframe,
      #reports iframe,
      #scheduleRecords iframe {
        width: 100% !important;
        height: calc(100vh - 140px) !important;
        min-height: 600px !important;
      }
      
      /* Responsive cards and containers */
      .row {
        margin-left: -0.5rem !important;
        margin-right: -0.5rem !important;
      }
      
      .row > * {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
      }
      
      /* Stack columns on mobile */
      [class*="col-"] {
        margin-bottom: 1rem !important;
      }
      
      /* Responsive buttons */
      .btn-group {
        flex-wrap: wrap !important;
      }
      
      .btn-group .btn {
        flex: 1 1 auto !important;
        min-width: 0 !important;
      }
      
      /* Responsive dropdowns */
      .dropdown-menu {
        max-width: calc(100vw - 2rem) !important;
        left: auto !important;
        right: 0.5rem !important;
      }
      
      /* Responsive search boxes */
      .search-box {
        width: 100% !important;
        max-width: 100% !important;
      }
      
      .search-box input {
        width: 100% !important;
        max-width: 100% !important;
      }
      
      /* Responsive stats cards */
      .stats-card {
        width: 100% !important;
        margin-bottom: 1rem !important;
      }
      
      /* Responsive charts */
      canvas {
        max-width: 100% !important;
        height: auto !important;
      }
      
      /* Responsive modals */
      .modal-dialog {
        max-width: calc(100vw - 1rem) !important;
        margin: 0.5rem auto !important;
      }
      
      /* Responsive forms */
      .form-row,
      .row.g-3,
      .row.g-4 {
        margin-left: -0.25rem !important;
        margin-right: -0.25rem !important;
      }
      
      .form-row > *,
      .row.g-3 > *,
      .row.g-4 > * {
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
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
      
      /* Responsive badges and labels */
      .badge {
        font-size: 0.7rem !important;
        padding: 0.25rem 0.5rem !important;
        white-space: normal !important;
        word-break: break-word !important;
      }
      
      /* Responsive text */
      h1 { font-size: 1.75rem !important; }
      h2 { font-size: 1.5rem !important; }
      h3 { font-size: 1.25rem !important; }
      h4 { font-size: 1.1rem !important; }
      h5 { font-size: 1rem !important; }
      h6 { font-size: 0.9rem !important; }
      
      /* Responsive spacing */
      .mb-4 { margin-bottom: 1rem !important; }
      .mb-5 { margin-bottom: 1.5rem !important; }
      .mt-4 { margin-top: 1rem !important; }
      .mt-5 { margin-top: 1.5rem !important; }
      .py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
      .py-5 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
      .dashboard-card { 
        padding:1rem !important; 
        margin-bottom:1rem !important; 
        min-height:unset !important; 
        border-radius:8px !important;
      }
      .dashboard-card h5 { font-size:1rem !important; }
      .dashboard-card h6 { font-size:0.9rem !important; }
      .dashboard-card p { font-size:0.8rem !important; }
      .navbar { padding:0.5rem 0.75rem !important; }
      .navbar-brand { font-size:0.9rem !important; }
      .navbar-text { font-size:0.85rem !important; }
      .stats-card { padding:1rem !important; }
      .stats-card .stat-number { font-size:2rem !important; }
      .stats-card .stat-label { font-size:0.75rem !important; }
      .btn { padding:0.5rem 1rem !important; font-size:0.875rem !important; }
      .table { font-size:0.85rem !important; }
      .table th, .table td { padding:0.5rem 0.25rem !important; }
    }
    
    /* Mobile (Small screens) */
    @media (max-width: 576px) {
      body { font-size:14px !important; }
      .main-content { 
        padding-top:60px !important; 
        padding-bottom:50px !important; 
        padding-left:0.5rem !important;
        padding-right:0.5rem !important;
      }
      .dashboard-card { 
        padding:0.75rem !important; 
        margin-bottom:0.75rem !important; 
        border-radius:6px !important;
      }
      .dashboard-card h5 { font-size:0.95rem !important; margin-bottom:0.5rem !important; }
      .dashboard-card h6 { font-size:0.85rem !important; margin-bottom:0.4rem !important; }
      .dashboard-card p { font-size:0.75rem !important; margin-bottom:0.4rem !important; }
      .navbar { padding:0.4rem 0.5rem !important; }
      .navbar-brand { font-size:0.8rem !important; }
      .navbar-brand img { width:28px !important; height:28px !important; }
      .navbar-text { font-size:0.75rem !important; display:none !important; }
      .stats-card { padding:0.75rem !important; }
      .stats-card .stat-number { font-size:1.75rem !important; }
      .stats-card .stat-label { font-size:0.7rem !important; }
      .btn { 
        padding:0.4rem 0.75rem !important; 
        font-size:0.8rem !important; 
        min-width:auto !important;
      }
      .btn-sm { padding:0.3rem 0.6rem !important; font-size:0.75rem !important; }
      .table { font-size:0.75rem !important; }
      .table th, .table td { padding:0.4rem 0.2rem !important; }
      .table-responsive { margin:0 -0.5rem !important; }
      .form-control, .form-select { 
        font-size:0.85rem !important; 
        padding:0.4rem 0.6rem !important; 
      }
      .form-label { font-size:0.8rem !important; }
      .badge { font-size:0.7rem !important; padding:0.3rem 0.5rem !important; }
      .alert { padding:0.75rem !important; font-size:0.8rem !important; }
      .modal-dialog { margin:0.5rem !important; }
      .modal-content { border-radius:8px !important; }
      .modal-header { padding:0.75rem 1rem !important; }
      .modal-body { padding:1rem !important; font-size:0.85rem !important; }
      .modal-footer { padding:0.75rem 1rem !important; }
      .modal-footer .btn { padding:0.5rem 1rem !important; font-size:0.85rem !important; min-width:80px !important; }
      .footer { font-size:0.75rem !important; padding:0.4rem 0 !important; }
      /* Org Chart Mobile */
      #orgChartCanvas { 
        height:auto !important; 
        min-height:600px !important; 
        padding:5px !important; 
      }
      #orgChartContainer { 
        min-height:600px !important; 
        max-height:calc(100vh - 200px) !important;
        overflow:auto !important;
      }
      .orgchart .node[data-tags*="admin-support"] { 
        min-width:60px !important; 
        font-size:7px !important; 
        padding:2px 4px !important; 
      }
      .orgchart .node[data-tags*="moderator"] { 
        min-width:55px !important; 
        font-size:6px !important; 
        padding:2px 3px !important; 
      }
      .orgchart .node[data-tags*="dept-head"] { 
        min-width:55px !important; 
        font-size:6px !important; 
        padding:2px 3px !important; 
      }
      .orgchart .node[data-tags*="dept"] { 
        min-width:55px !important; 
        font-size:6px !important; 
        padding:2px 3px !important; 
      }
      .orgchart .node[data-tags*="user"] { 
        min-width:50px !important; 
        font-size:6px !important; 
        padding:1px 3px !important; 
      }
    }
    
    /* Extra Small Mobile */
    @media (max-width: 400px) {
      .main-content { padding-left:0.25rem !important; padding-right:0.25rem !important; }
      .dashboard-card { padding:0.5rem !important; }
      .navbar-brand { font-size:0.75rem !important; }
      .btn { padding:0.35rem 0.6rem !important; font-size:0.75rem !important; }
      .table { font-size:0.7rem !important; }
      #orgChartCanvas { height:500px !important; min-height:500px !important; }
      
      /* Extra small iframe adjustments */
      #userManagement iframe,
      #manageRoles iframe,
      #activityLogs iframe,
      #reports iframe,
      #scheduleRecords iframe {
        height: calc(100vh - 120px) !important;
        min-height: 400px !important;
      }
    }
    
    /* Landscape mobile orientation */
    @media (max-width: 991.98px) and (orientation: landscape) {
      .main-content {
        padding-top: 60px !important;
      }
      
      #userManagement iframe,
      #manageRoles iframe,
      #activityLogs iframe,
      #reports iframe,
      #scheduleRecords iframe {
        height: calc(100vh - 100px) !important;
        min-height: 400px !important;
      }
    }
    
    /* Print styles */
    @media print {
      .navbar,
      .sidebar,
      .sidebar-offcanvas,
      .footer,
      .btn,
      .navbar-toggler {
        display: none !important;
      }
      
      .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
      }
      
      .dashboard-card {
        page-break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
      }
    }
  </style>

  <!-- ✅ Make tab functions global early -->
  <!-- SweetAlert2 - Always load for manage_roles.php and other components -->
  <link rel="stylesheet" href="/assets/css/sweetalert2.min.css">
  <script src="/assets/js/sweetalert2.min.js"></script>
  <style>
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
  <!-- OrgChart JS (Balkan) for org chart rendering -->
  <!-- Local only - no CDN fallback -->
  <script>
    // Load OrgChart JS - local only (no CDN fallback)
    (function() {
      const localPath = '/assets/vendor/orgchart/orgchart.js';
      
      function loadScript() {
        const scriptSrc = localPath;
        
        const script = document.createElement('script');
        script.src = scriptSrc;
        script.onload = function() {
          // OrgChart loaded successfully
          console.log('OrgChart loaded from local file');
        };
        script.onerror = function() {
          console.error('OrgChart failed to load from local file. Please ensure orgchart.js is in /assets/vendor/orgchart/');
          const statusEl = document.getElementById('orgChartStatus');
          if (statusEl) {
            statusEl.textContent = 'Error: OrgChart library failed to load. Please ensure orgchart.js is in /assets/vendor/orgchart/';
            statusEl.classList.remove('text-muted');
            statusEl.classList.add('text-danger');
          }
        };
        document.head.appendChild(script);
      }
      loadScript();
    })();
  </script>
  <style>
    /* OrgChart JS styling - match image 2 design */
    #orgChartCanvas {
      width: 100% !important;
      height: 1000px !important;
      min-height: 1000px !important;
      overflow: visible !important;
      background: #ffffff;
      padding: 10px;
      position: relative;
      /* Disable zoom interactions */
      touch-action: pan-x pan-y !important;
      pointer-events: auto !important;
      transition: all 0.3s ease;
    }
    #orgChartContainer {
      width: 100%;
      height: 800px !important;
      min-height: 600px !important;
      max-height: calc(100vh - 300px) !important;
      overflow: auto !important;
      overflow-x: auto !important;
      overflow-y: auto !important;
      background: #ffffff;
      /* Smooth scrolling */
      -webkit-overflow-scrolling: touch;
      /* Custom scrollbar styling */
      scrollbar-width: thin;
      scrollbar-color: #800000 #f0f0f0;
      transition: all 0.3s ease;
    }
    
    /* Responsive breakpoints */
    /* Large screens (desktop) */
    @media (min-width: 1200px) {
      #orgChartContainer {
        height: 900px !important;
        max-height: calc(100vh - 250px) !important;
      }
      #orgChartCanvas {
        padding: 15px;
      }
    }
    
    /* Medium screens (tablet landscape) */
    @media (min-width: 992px) and (max-width: 1199px) {
      #orgChartContainer {
        height: 750px !important;
        max-height: calc(100vh - 280px) !important;
      }
      #orgChartCanvas {
        padding: 12px;
      }
    }
    
    /* Small screens (tablet portrait) */
    @media (min-width: 768px) and (max-width: 991px) {
      #orgChartContainer {
        height: 650px !important;
        max-height: calc(100vh - 300px) !important;
      }
      #orgChartCanvas {
        padding: 10px;
      }
    }
    
    /* Extra small screens (mobile) */
    @media (max-width: 767px) {
      #orgChartContainer {
        height: 500px !important;
        min-height: 400px !important;
        max-height: calc(100vh - 250px) !important;
      }
      #orgChartCanvas {
        padding: 8px;
        min-height: 600px !important;
      }
    }
    
    /* Zoom level adjustments */
    @media (min-resolution: 150dpi) {
      /* High DPI displays */
      #orgChartCanvas {
        padding: 12px;
      }
    }
    
    /* Zoom in (125%) */
    @media screen and (min-width: 1280px) and (max-width: 1600px) {
      #orgChartContainer {
        height: 700px !important;
      }
    }
    
    /* Zoom out (75%) */
    @media screen and (min-width: 1920px) {
      #orgChartContainer {
        height: 950px !important;
      }
    }
    /* Custom scrollbar for webkit browsers */
    #orgChartContainer::-webkit-scrollbar {
      width: 12px;
      height: 12px;
    }
    #orgChartContainer::-webkit-scrollbar-track {
      background: #f0f0f0;
      border-radius: 6px;
    }
    #orgChartContainer::-webkit-scrollbar-thumb {
      background: #800000;
      border-radius: 6px;
    }
    #orgChartContainer::-webkit-scrollbar-thumb:hover {
      background: #660000;
    }
    /* Prevent zoom gestures on the chart */
    #orgChartCanvas {
      user-select: none;
      -webkit-user-select: none;
      -moz-user-select: none;
      -ms-user-select: none;
    }
    
    /* Responsive node sizing */
    /* Large screens - slightly larger nodes */
    @media (min-width: 1200px) {
      .orgchart .node[data-tags*="admin-support"] {
        font-size: 9px !important;
        padding: 3px 6px !important;
        min-width: 80px !important;
        max-width: 110px !important;
      }
      .orgchart .node[data-tags*="moderator"],
      .orgchart .node[data-tags*="dept-head"],
      .orgchart .node[data-tags*="dept"] {
        font-size: 8px !important;
        padding: 3px 5px !important;
        min-width: 75px !important;
        max-width: 105px !important;
      }
      .orgchart .node[data-tags*="user"] {
        font-size: 8px !important;
        padding: 2px 5px !important;
        min-width: 70px !important;
        max-width: 100px !important;
      }
    }
    
    /* Medium screens - current size */
    @media (min-width: 768px) and (max-width: 1199px) {
      .orgchart .node[data-tags*="admin-support"] {
        font-size: 8px !important;
        padding: 2px 5px !important;
        min-width: 70px !important;
        max-width: 100px !important;
      }
      .orgchart .node[data-tags*="moderator"],
      .orgchart .node[data-tags*="dept-head"],
      .orgchart .node[data-tags*="dept"] {
        font-size: 7px !important;
        padding: 2px 4px !important;
        min-width: 65px !important;
        max-width: 95px !important;
      }
      .orgchart .node[data-tags*="user"] {
        font-size: 7px !important;
        padding: 2px 4px !important;
        min-width: 60px !important;
        max-width: 90px !important;
      }
    }
    
    /* Small screens - smaller nodes */
    @media (max-width: 767px) {
      .orgchart .node[data-tags*="admin-support"] {
        font-size: 7px !important;
        padding: 2px 4px !important;
        min-width: 60px !important;
        max-width: 85px !important;
      }
      .orgchart .node[data-tags*="moderator"],
      .orgchart .node[data-tags*="dept-head"],
      .orgchart .node[data-tags*="dept"] {
        font-size: 6px !important;
        padding: 2px 3px !important;
        min-width: 55px !important;
        max-width: 80px !important;
      }
      .orgchart .node[data-tags*="user"] {
        font-size: 6px !important;
        padding: 1px 3px !important;
        min-width: 50px !important;
        max-width: 75px !important;
      }
    }
    
    /* Zoom level adjustments for node spacing */
    @media screen and (min-width: 1920px) {
      /* Zoom out - more spacing */
      .orgchart {
        --node-separation: 8px;
        --level-separation: 25px;
        --sibling-separation: 6px;
      }
    }
    
    @media screen and (max-width: 1280px) {
      /* Zoom in - less spacing */
      .orgchart {
        --node-separation: 5px;
        --level-separation: 18px;
        --sibling-separation: 4px;
      }
    }
    .orgchart {
      background: transparent !important;
    }
    /* Admin Support/Root node - Extra small size */
    .orgchart .node[data-tags*="admin-support"] {
      background: #ffffff !important;
      border: 1px solid #000000 !important;
      border-radius: 2px !important;
      color: #000000 !important;
      font-weight: 600 !important;
      font-size: 8px !important;
      padding: 2px 5px !important;
      min-width: 70px !important;
      max-width: 100px !important;
      text-align: center !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    }
    .orgchart .node[data-tags*="admin-support"] .title,
    .orgchart .node[data-tags*="admin-support"] [class*="field_1"] {
      color: #333333 !important;
      font-weight: 500 !important;
      font-size: 6px !important;
      margin-top: 1px !important;
      line-height: 1.1 !important;
    }
    /* Moderator nodes - Extra small size */
    .orgchart .node[data-tags*="moderator"] {
      background: #ffffff !important;
      border: 1px solid #000000 !important;
      border-radius: 2px !important;
      color: #000000 !important;
      font-weight: 600 !important;
      font-size: 7px !important;
      padding: 2px 4px !important;
      min-width: 65px !important;
      max-width: 95px !important;
      text-align: center !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    }
    .orgchart .node[data-tags*="moderator"] .title,
    .orgchart .node[data-tags*="moderator"] [class*="field_1"] {
      color: #333333 !important;
      font-weight: 500 !important;
      font-size: 6px !important;
      margin-top: 1px !important;
      line-height: 1.1 !important;
    }
    /* Department Head nodes - Extra small size */
    .orgchart .node[data-tags*="dept-head"] {
      background: #ffffff !important;
      border: 1px solid #000000 !important;
      border-radius: 2px !important;
      color: #000000 !important;
      font-weight: 600 !important;
      font-size: 7px !important;
      padding: 2px 4px !important;
      min-width: 65px !important;
      max-width: 95px !important;
      text-align: center !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    }
    .orgchart .node[data-tags*="dept-head"] .title,
    .orgchart .node[data-tags*="dept-head"] [class*="field_1"] {
      color: #333333 !important;
      font-weight: 500 !important;
      font-size: 6px !important;
      margin-top: 1px !important;
      line-height: 1.1 !important;
    }
    /* Department headers - Extra small size */
    .orgchart .node[data-tags*="dept"] {
      background: #ffffff !important;
      border: 1px solid #000000 !important;
      border-radius: 2px !important;
      color: #000000 !important;
      font-weight: 700 !important;
      font-size: 7px !important;
      text-transform: uppercase !important;
      padding: 2px 4px !important;
      min-width: 65px !important;
      max-width: 95px !important;
      text-align: center !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
      letter-spacing: 0.1px !important;
    }
    .orgchart .node[data-tags*="dept"] .title {
      color: #000000 !important;
      font-weight: 700 !important;
      font-size: 7px !important;
    }
    /* Personnel cards - Extra small size */
    .orgchart .node[data-tags*="user"] {
      background: #ffffff !important;
      border: 1px solid #000000 !important;
      border-radius: 2px !important;
      padding: 2px 4px !important;
      min-width: 60px !important;
      max-width: 90px !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
      text-align: center !important;
      transition: all 0.2s ease !important;
    }
    .orgchart .node[data-tags*="user"]:hover {
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12) !important;
      transform: translateY(-1px) !important;
    }
    /* Target all text elements inside user nodes */
    .orgchart .node[data-tags*="user"] * {
      text-align: center !important;
    }
    .orgchart .node[data-tags*="user"] [class*="field_0"],
    .orgchart .node[data-tags*="user"] .title,
    .orgchart .node[data-tags*="user"] [class*="title"],
    .orgchart .node[data-tags*="user"] [class*="field"] {
      font-weight: 600 !important;
      color: #000000 !important;
      font-size: 7px !important;
      margin-top: 1px !important;
      line-height: 1.1 !important;
      text-align: center !important;
      display: block !important;
      width: 100% !important;
    }
    .orgchart .node[data-tags*="user"] [class*="field_1"],
    .orgchart .node[data-tags*="user"] .content,
    .orgchart .node[data-tags*="user"] [class*="content"],
    .orgchart .node[data-tags*="user"] [class*="desc"] {
      color: #333333 !important;
      font-size: 6px !important;
      margin-top: 1px !important;
      text-align: center !important;
      display: block !important;
      width: 100% !important;
      font-weight: 400 !important;
      line-height: 1.05 !important;
    }
    /* Target OrgChart JS specific elements */
    .orgchart .node[data-tags*="user"] div,
    .orgchart .node[data-tags*="user"] span,
    .orgchart .node[data-tags*="user"] p {
      text-align: center !important;
    }
    /* Connection lines - thin black lines for compact design */
    .orgchart .lines .downLine {
      background-color: #000000 !important;
      width: 1px !important;
    }
    .orgchart .lines .rightLine,
    .orgchart .lines .leftLine,
    .orgchart .lines .topLine {
      background-color: #000000 !important;
      height: 1px !important;
    }
    /* Chart background - white like image 2 */
    #orgChartCanvas {
      background: #ffffff !important;
    }
  </style>
  <link rel="stylesheet" href="/assets/css/admin_support.css">
</head>
  <script>
    // Global tab switching function
    // Mobile Detection Utility
    function isMobileViewport() {
      return window.innerWidth <= 991.98; // Bootstrap's md breakpoint
    }

    function showTab(tabId, element) {
      // Mobile Responsiveness: Hide overview on mobile when switching to other tabs
      const isMobile = isMobileViewport();
      const overviewTab = document.getElementById('overview');
      
      // CRITICAL: On mobile, hide overview FIRST before hiding other tabs
      // This ensures overview is removed from layout before showing new tab
      if (isMobile && overviewTab && tabId !== 'overview') {
        // Hide overview immediately and synchronously
        overviewTab.style.display = 'none';
        overviewTab.style.visibility = 'hidden';
        overviewTab.classList.add('mobile-tab-hidden');
        overviewTab.classList.remove('mobile-tab-active');
        // Force layout recalculation by reading offsetHeight
        void overviewTab.offsetHeight;
      }
      
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
        tab.style.visibility = 'hidden';
        // Remove mobile-specific classes
        tab.classList.remove('mobile-tab-active', 'mobile-tab-hidden');
      });
      
      // Show selected tab
      const targetTab = document.getElementById(tabId);
      if (targetTab) {
        targetTab.style.display = 'block';
        targetTab.style.visibility = 'visible';
        targetTab.classList.add('mobile-tab-active');
        
        // On mobile: Handle overview visibility
        if (isMobile && overviewTab) {
          if (tabId === 'overview') {
            // Show overview when switching back to it
            overviewTab.style.display = 'block';
            overviewTab.style.visibility = 'visible';
            overviewTab.classList.remove('mobile-tab-hidden');
            overviewTab.classList.add('mobile-tab-active');
          }
          // If switching to non-overview tab, overview is already hidden above
        }
        
        // Scroll to top of main content on mobile when switching tabs
        // Use immediate scroll first, then smooth scroll after layout update
        if (isMobile) {
          // Force immediate scroll to top (no animation)
          window.scrollTo({ top: 0, behavior: 'auto' });
          document.documentElement.scrollTop = 0;
          document.body.scrollTop = 0;
          
          // Then use requestAnimationFrame to ensure layout has fully updated
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              const mainContent = document.querySelector('.main-content') || document.querySelector('.container-fluid');
              if (mainContent) {
                // Ensure we're at the top
                window.scrollTo({ top: 0, behavior: 'auto' });
                mainContent.scrollIntoView({ behavior: 'auto', block: 'start' });
              }
            });
          });
        }
        
        // Reload iframe content when tab is shown (for iframe-based tabs)
        const iframe = targetTab.querySelector('iframe');
        if (iframe && !iframe.dataset.loaded) {
          // Reload iframe to ensure fresh content
          const currentSrc = iframe.src;
          iframe.src = '';
          setTimeout(() => {
            iframe.src = currentSrc;
            iframe.dataset.loaded = 'true';
          }, 100);
        }
      }

      // Lazy-load org chart when its tab is opened
      if (tabId === 'orgChart') {
        initOrgChart();
      }

      // Remove active class from all sidebar links
      document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link').forEach(link => {
        link.classList.remove('active');
      });
      
      // Add active class to clicked link
      if (element) {
        element.classList.add('active');
      }
    }

    // Close offcanvas function
    function closeOffcanvas() {
      const offcanvasEl = document.getElementById('sidebarOffcanvas');
      if (offcanvasEl && typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
        const instance = bootstrap.Offcanvas.getInstance(offcanvasEl) || new bootstrap.Offcanvas(offcanvasEl);
        instance.hide();
      }
    }

    // Navigate to dashboard with specific tab (URL will be cleaned by dashboard.php)
    // Works both when already on dashboard.php (internal tab switch) and from other pages (navigation)
    function navigateToDashboardTab(tab) {
      // Check if we're already on dashboard.php
      const isOnDashboard = window.location.pathname.includes('dashboard.php');
      
      if (isOnDashboard) {
        // Already on dashboard - use internal tab switching
        const targetLink = document.querySelector(`.nav-link[onclick*="navigateToDashboardTab('${tab}')"]`) ||
                          document.querySelector(`.nav-link[onclick*='navigateToDashboardTab("${tab}")']`);
        if (targetLink) {
          showTab(tab, targetLink);
        } else {
          // Fallback: directly show the tab
          showTab(tab, null);
        }
      } else {
        // Navigate to dashboard with tab parameter
        if (tab === 'overview') {
          window.location.href = 'dashboard.php';
        } else {
          window.location.href = 'dashboard.php?tab=' + tab;
        }
      }
    }
  </script>

<body class="as-support-dashboard">
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top as-navbar">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="flex-shrink: 0;">
        <img src="../../public/assets/img/evsu-logo.png" alt="EVSU Logo" width="30" height="30" class="me-2">
        <span class="fw-bold d-none d-sm-inline">EVSU Scheduling System</span>
        <span class="fw-bold d-sm-none">EVSU</span>
      </a>
      
      <!-- Right side: Notification + Dashboard Text + Mobile Menu -->
      <div class="d-flex align-items-center gap-2 gap-lg-3 ms-auto">
        <!-- Notification Icon -->
        <div class="position-relative d-flex align-items-center">
          <button type="button" class="btn btn-link text-white position-relative" id="notificationBtn" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none; border: none; background: transparent;">
            <i class="bi bi-bell"></i>
            <span class="position-absolute badge rounded-circle bg-danger" id="notificationBadge" style="display: none;"></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow" id="notificationDropdown" style="max-height: 500px; overflow-y: auto; z-index: 1050;">
            <li class="dropdown-header">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold">Notifications</span>
                <button class="btn btn-sm btn-link text-muted p-0" id="markAllReadBtn" style="font-size: 0.75rem; text-decoration: none;">Mark all as read</button>
              </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li id="notificationList">
              <div class="text-center text-muted p-4">
                <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                <small>No notifications</small>
              </div>
            </li>
          </ul>
        </div>
        
        <!-- Dashboard Title (Desktop only) -->
        <span class="navbar-text fw-semibold text-white d-none d-lg-block mb-0">Administrator Dashboard</span>
        
        <!-- Mobile Menu Toggle -->
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle navigation" style="border: none; padding: 0.25rem 0.5rem;">
          <span class="navbar-toggler-icon"></span>
        </button>
      </div>
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
        <a class="nav-link active" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#overview" role="tab" aria-controls="overview" aria-selected="true" onclick="closeOffcanvas(); return false;">
          <span class="bi bi-speedometer2 me-2"></span> Overview
        </a>
        <a class="nav-link" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#orgChart" role="tab" aria-controls="orgChart" aria-selected="false" onclick="closeOffcanvas(); return false;">
          <span class="bi bi-diagram-3 me-2"></span> Org Chart
        </a>
        </div>
        
        <!-- User Management Section -->
        <div class="nav-section mb-3">
          <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">User Management</small>
          <a class="nav-link" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#userManagement" role="tab" aria-controls="userManagement" aria-selected="false" onclick="closeOffcanvas(); return false;">
            <span class="bi bi-people me-2"></span> User Management
          </a>
          <a class="nav-link" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#manageRoles" role="tab" aria-controls="manageRoles" aria-selected="false" onclick="closeOffcanvas(); return false;">
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
        <a class="nav-link active" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#overview" role="tab" aria-controls="overview" aria-selected="true">
          <span class="bi bi-speedometer2 me-2"></span> Overview
        </a>
        <a class="nav-link" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#orgChart" role="tab" aria-controls="orgChart" aria-selected="false">
          <span class="bi bi-diagram-3 me-2"></span> Org Chart
        </a>
      </div>
      
      <!-- User Management Section -->
      <div class="nav-section mb-3">
        <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">User Management</small>
        <a class="nav-link" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#userManagement" role="tab" aria-controls="userManagement" aria-selected="false">
          <span class="bi bi-people me-2"></span> User Management
        </a>
        <a class="nav-link" href="dashboard.php" data-bs-toggle="tab" data-bs-target="#manageRoles" role="tab" aria-controls="manageRoles" aria-selected="false">
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

      <!-- Bootstrap Tab Container -->
      <div class="tab-content" id="mainTabContent">
        <!-- Overview (Default Active) -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
          <?php include 'overview.php'; ?>
        </div>

        <!-- Org Chart -->
        <div class="tab-pane fade" id="orgChart" role="tabpanel" aria-labelledby="orgChart-tab">
          <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="mb-1">Organizational Chart</h5>
                <p class="mb-0 text-muted">Visualize users by role → designation → rank.</p>
              </div>
              <div class="d-flex gap-2">
                <button id="orgChartRefresh" class="btn btn-outline-maroon btn-sm">
                  <i class="bi bi-arrow-repeat me-1"></i> Refresh
                </button>
              </div>
            </div>
            <div id="orgChartStatus" class="text-muted small mb-2">Loading...</div>
            <div id="orgChartContainer" class="p-2 bg-light rounded">
              <div id="orgChartCanvas"></div>
            </div>
          </div>
        </div>

        <!-- User Management Tab -->
        <div class="tab-pane fade" id="userManagement" role="tabpanel" aria-labelledby="userManagement-tab">
          <?php 
          $component_path = __DIR__ . '/../../admin_support/views/components/user_management.php';
          if (file_exists($component_path)) {
              include $component_path;
          } else {
              // Fallback to iframe until component is created
              echo '<iframe src="user_management.php" style="width: 100%; height: calc(100vh - 200px); border: none; min-height: 600px;" id="userManagementFrame"></iframe>';
          }
          ?>
        </div>

        <!-- Manage Roles Tab -->
        <div class="tab-pane fade" id="manageRoles" role="tabpanel" aria-labelledby="manageRoles-tab">
          <?php 
          $component_path = __DIR__ . '/../../admin_support/views/components/manage_roles.php';
          if (file_exists($component_path)) {
              include $component_path;
          } else {
              // Fallback to iframe until component is created
              echo '<iframe src="manage_roles.php" style="width: 100%; height: calc(100vh - 200px); border: none; min-height: 600px;" id="manageRolesFrame"></iframe>';
          }
          ?>
        </div>

        <!-- Activity Logs Tab -->
        <div class="tab-pane fade" id="activityLogs" role="tabpanel" aria-labelledby="activityLogs-tab">
          <?php 
          $component_path = __DIR__ . '/../../admin_support/views/components/activity_logs.php';
          if (file_exists($component_path)) {
              include $component_path;
          } else {
              // Fallback to iframe until component is created
              echo '<iframe src="activity_logs.php" style="width: 100%; height: calc(100vh - 200px); border: none; min-height: 600px;" id="activityLogsFrame"></iframe>';
          }
          ?>
        </div>

        <!-- Reports Tab -->
        <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
          <?php 
          $component_path = __DIR__ . '/../../admin_support/views/components/reports.php';
          if (file_exists($component_path)) {
              include $component_path;
          } else {
              // Fallback to iframe until component is created
              echo '<iframe src="reports.php" style="width: 100%; height: calc(100vh - 200px); border: none; min-height: 600px;" id="reportsFrame"></iframe>';
          }
          ?>
        </div>

        <!-- Schedule Records Tab -->
        <div class="tab-pane fade" id="scheduleRecords" role="tabpanel" aria-labelledby="scheduleRecords-tab">
          <?php 
          $component_path = __DIR__ . '/../../admin_support/views/components/schedule_records.php';
          if (file_exists($component_path)) {
              include $component_path;
          } else {
              // Fallback to iframe until component is created
              echo '<iframe src="schedule_records.php" style="width: 100%; height: calc(100vh - 200px); border: none; min-height: 600px;" id="scheduleRecordsFrame"></iframe>';
          }
          ?>
        </div>
      </div>

    </div>
  </main>

  <!-- Logout Modal (matches Add Department: white header, red Cancel, green primary) -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
  
  <style>
    /* When any modal is open: backdrop covers the whole dashboard (navbar, sidebar, content, footer) */
    .modal-backdrop,
    .modal-backdrop.fade,
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
        margin: 0 !important;
        padding: 0 !important;
        z-index: 1100 !important;
        background-color: rgba(0, 0, 0, 0.5) !important;
        transition: opacity 0.2s ease !important;
    }
    
    .modal-backdrop.show {
        opacity: 1 !important;
    }
    
    body.modal-open .modal-backdrop {
        z-index: 1100 !important;
    }
    
    /* Modals on top of everything – cover whole dashboard */
    .modal,
    .modal.show {
        position: fixed !important;
        top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
        width: 100% !important; height: 100% !important; min-height: 100vh !important;
        z-index: 1105 !important;
        overflow-x: hidden !important; overflow-y: auto !important;
        transition: opacity 0.2s ease !important;
    }
    
    .modal .modal-dialog {
        position: relative !important;
        z-index: 1 !important;
        pointer-events: auto !important;
        transition: transform 0.2s ease-out !important;
    }
    
    .modal .modal-content {
        position: relative !important;
        z-index: 2 !important;
        pointer-events: auto !important;
    }
    
    /* Logout modal: mobile layout only; z-index from shared admin_support.css */
    @media (max-width: 991.98px) {
        #logoutModal .modal-footer { flex-direction: column-reverse !important; gap: 0.75rem !important; }
        #logoutModal .modal-footer .btn { width: 100% !important; min-height: 44px !important; }
        #logoutModal .modal-dialog { margin: 1rem !important; max-width: calc(100% - 2rem) !important; width: calc(100% - 2rem) !important; }
        #logoutModal.modal.show { display: flex !important; align-items: center !important; justify-content: center !important; padding: 1rem !important; }
    }
  </style>

<!-- Org Chart scripts -->
<script>
  let orgChartInitialized = false;
  let orgChartLoading = false;
  let orgChartInstance = null; // Store chart instance globally for responsive updates
  let resizeTimeout = null; // Debounce resize events

  function initOrgChart() {
    const statusEl = document.getElementById('orgChartStatus');
    if (!statusEl) return;
    if (orgChartLoading) return;

    // Check if OrgChart is loaded, wait if needed
    if (typeof OrgChart === 'undefined') {
      statusEl.textContent = 'Loading OrgChart library...';
      statusEl.classList.remove('text-danger');
      statusEl.classList.add('text-muted');
      
      // Wait for OrgChart to load (max 10 seconds)
      let attempts = 0;
      const checkOrgChart = setInterval(() => {
        attempts++;
        if (typeof OrgChart !== 'undefined') {
          clearInterval(checkOrgChart);
          orgChartLoading = true;
          statusEl.textContent = 'Loading org chart data...';
          loadOrgChartData();
        } else if (attempts >= 50) { // 10 seconds (50 * 200ms)
          clearInterval(checkOrgChart);
          statusEl.textContent = 'Error: OrgChart library failed to load. Please check your internet connection or refresh the page.';
          statusEl.classList.remove('text-muted');
          statusEl.classList.add('text-danger');
          orgChartLoading = false;
        }
      }, 200);
      return;
    }

    orgChartLoading = true;
    statusEl.textContent = 'Loading org chart data...';
    statusEl.classList.remove('text-danger');
    statusEl.classList.add('text-muted');

    loadOrgChartData();
  }

  function loadOrgChartData() {
    fetch('../../admin/management/get_org_chart.php')
      .then(res => res.json())
      .then(payload => {
        if (!payload.success) {
          const detail = payload.error ? ` (${payload.error})` : '';
          throw new Error((payload.message || 'Failed to load data') + detail);
        }
        drawOrgChart(payload.data || [], payload.meta || {});
      })
      .catch(err => {
        const statusEl = document.getElementById('orgChartStatus');
        if (statusEl) {
          statusEl.textContent = 'Error: ' + (err.message || 'Failed to load org chart');
          statusEl.classList.remove('text-muted');
          statusEl.classList.add('text-danger');
        }
      })
      .finally(() => {
        orgChartLoading = false;
      });
  }

  function drawOrgChart(data, meta) {
    const statusEl = document.getElementById('orgChartStatus');
    const canvasEl = document.getElementById('orgChartCanvas');
    if (!canvasEl) return;

    // Check if OrgChart library is loaded
    if (typeof OrgChart === 'undefined') {
      if (statusEl) {
        statusEl.textContent = 'Error: OrgChart library not loaded. Please refresh the page.';
        statusEl.classList.remove('text-muted');
        statusEl.classList.add('text-danger');
      }
      orgChartLoading = false;
      return;
    }

    if (!Array.isArray(data) || data.length === 0) {
      if (statusEl) {
        statusEl.textContent = 'No users to display.';
        statusEl.classList.remove('text-danger');
        statusEl.classList.add('text-muted');
      }
      canvasEl.innerHTML = '';
      return;
    }

    const roleOrder = meta.roleOrder || ['Admin support', 'Admin', 'Moderator', 'User'];
    const designationOrder = meta.designationOrder || [
      'Vice President',
      'Campus Director',
      'Dean',
      'Director',
      'Head',
      'Chairperson/Coordinator/As Officer in Faculty Association',
      'None'
    ];
    const rankOrder = meta.rankOrder || [
      'University Professor',
      'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
      'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
      'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
      'Instructor III', 'Instructor II', 'Instructor I',
      'None'
    ];

    const orderIdx = (val, arr) => {
      const i = arr.indexOf(val);
      return i === -1 ? arr.length : i;
    };

    // Helper function to format name properly (Title Case)
    const formatName = (fname, mname, lname) => {
      const parts = [fname, mname, lname].filter(Boolean);
      return parts.map(part => {
        if (!part) return '';
        return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
      }).join(' ').trim();
    };

    // Helper function to format title (designation and rank)
    const formatTitle = (designation, rank) => {
      const parts = [];
      if (designation && designation !== 'None') {
        parts.push(designation);
      }
      if (rank && rank !== 'None') {
        parts.push(`(${rank})`);
      }
      return parts.join(' ') || '';
    };

    // Sort data for stable display
    const sorted = [...data].sort((a, b) => {
      const r = orderIdx(a.role, roleOrder) - orderIdx(b.role, roleOrder);
      if (r !== 0) return r;
      const d = orderIdx(a.designation, designationOrder) - orderIdx(b.designation, designationOrder);
      if (d !== 0) return d;
      const rk = orderIdx(a.rank, rankOrder) - orderIdx(b.rank, rankOrder);
      if (rk !== 0) return rk;
      const nameA = `${a.lname || ''}${a.fname || ''}`.toLowerCase();
      const nameB = `${b.lname || ''}${b.fname || ''}`.toLowerCase();
      return nameA.localeCompare(nameB);
    });

    const nodes = [];
    const rootId = 'admin-support-root';
    
    // Create Admin Support root node
    // Find Admin Support users - they are the root level
    const adminSupportUsers = sorted.filter(u => 
      u.role && u.role.toLowerCase() === 'admin support'
    );
    
    if (adminSupportUsers.length > 0) {
      // Use first Admin Support user as root, or create a header
      const firstAdminSupport = adminSupportUsers[0];
      const adminSupportName = formatName(firstAdminSupport.fname, firstAdminSupport.mname, firstAdminSupport.lname) || firstAdminSupport.username;
      const adminSupportTitle = formatTitle(firstAdminSupport.designation, firstAdminSupport.rank);
      nodes.push({ 
        id: rootId, 
        name: adminSupportName, 
        title: adminSupportTitle || 'Admin Support', 
        tags: ['admin-support'] 
      });
      
      // Add other Admin Support users under the first one (if multiple)
      if (adminSupportUsers.length > 1) {
        adminSupportUsers.slice(1).forEach((u, idx) => {
          const userName = formatName(u.fname, u.mname, u.lname) || u.username;
          const userTitle = formatTitle(u.designation, u.rank);
          nodes.push({
            id: `admin-support-${u.id}`,
            pid: rootId,
            name: userName,
            title: userTitle || 'Admin Support',
            tags: ['admin-support']
          });
        });
      }
    } else {
      // Fallback if no Admin Support found - create a header node
      nodes.push({ 
        id: rootId, 
        name: 'ADMIN SUPPORT', 
        title: '', 
        tags: ['admin-support'] 
      });
    }

    // Group users by department
    const deptUsers = new Map();
    sorted.forEach(u => {
      // Skip Admin Support users (already handled)
      if (u.role && u.role.toLowerCase() === 'admin support') {
        return;
      }
      const dept = (u.department || u.program || 'Unassigned').trim() || 'Unassigned';
      if (!deptUsers.has(dept)) deptUsers.set(dept, []);
      deptUsers.get(dept).push(u);
    });

    const sanitize = (s) => (s || 'none').toString().replace(/\s+/g, '_').replace(/[^A-Za-z0-9_-]/g, '').toLowerCase();

    // Process each department
    deptUsers.forEach((users, dept) => {
      // Separate users by role within department
      const admins = users.filter(u => 
        u.role && (u.role.toLowerCase() === 'admin' || 
                  (u.role.toLowerCase() === 'admin support' && u.designation && u.designation.toLowerCase().includes('head')))
      );
      
      const moderators = users.filter(u => 
        u.role && u.role.toLowerCase() === 'moderator'
      );
      
      const instructors = users.filter(u => 
        !u.role || u.role.toLowerCase() === 'user' || 
        (u.role && u.role.toLowerCase() !== 'admin' && u.role.toLowerCase() !== 'admin support' && u.role.toLowerCase() !== 'moderator')
      );

      // Find Department Head (Admin) - prioritize those with "Head" designation
      const deptHead = admins.find(u => 
        u.designation && u.designation.toLowerCase().includes('head')
      ) || admins.find(u => u.role && u.role.toLowerCase() === 'admin') || admins[0];

      if (deptHead) {
        // Create Department Name node first
        const deptId = `dept-${sanitize(dept)}`;
        const deptName = dept.toUpperCase() + (dept.includes('DEPARTMENT') ? '' : ' DEPARTMENT');
        
        // Connect Department Name to Admin Support root
        nodes.push({ 
          id: deptId, 
          pid: rootId, 
          name: deptName, 
          title: '', 
          tags: ['dept'] 
        });
        
        const headName = formatName(deptHead.fname, deptHead.mname, deptHead.lname) || deptHead.username;
        const headTitle = formatTitle(deptHead.designation || 'Head', deptHead.rank);
        const headId = `head-${deptHead.id}`;
        
        // Connect Department Head to Department Name node
        nodes.push({
          id: headId,
          pid: deptId,
          name: headName,
          title: headTitle || 'Head',
          tags: ['dept-head']
        });

        // Add Moderators under Department Head
        if (moderators.length > 0) {
          moderators.sort((a, b) => {
            const d = orderIdx(a.designation || 'None', designationOrder) - orderIdx(b.designation || 'None', designationOrder);
            if (d !== 0) return d;
            const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
            if (rnk !== 0) return rnk;
            return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
          });

          let prevModeratorId = null;
          moderators.forEach((mod, idx) => {
            const modName = formatName(mod.fname, mod.mname, mod.lname) || mod.username;
            const modTitle = formatTitle(mod.designation, mod.rank);
            const modId = `mod-${mod.id}`;
            
            // First moderator connects to head, others chain horizontally
            const modParentId = prevModeratorId || headId;
            
            nodes.push({
              id: modId,
              pid: modParentId,
              name: modName,
              title: modTitle || 'Moderator',
              tags: ['moderator']
            });
            
            prevModeratorId = modId;
          });

          // Add Users/Instructors under Moderators, organized by Designation → Rank
          if (instructors.length > 0) {
            // Group instructors by designation, then by rank
            const byDesignation = new Map();
            instructors.forEach(inst => {
              const desig = inst.designation || 'None';
              if (!byDesignation.has(desig)) byDesignation.set(desig, []);
              byDesignation.get(desig).push(inst);
            });

            // Sort designations by order
            const sortedDesignations = Array.from(byDesignation.keys()).sort((a, b) => {
              return orderIdx(a, designationOrder) - orderIdx(b, designationOrder);
            });

            // Connect instructors to first moderator (or chain them)
            let prevInstructorId = moderators.length > 0 ? `mod-${moderators[0].id}` : headId;
            
            sortedDesignations.forEach(desig => {
              const instructorsInDesig = byDesignation.get(desig);
              
              // Sort by rank within designation
              instructorsInDesig.sort((a, b) => {
                const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
                if (rnk !== 0) return rnk;
                return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
              });

              instructorsInDesig.forEach((inst) => {
                const instName = formatName(inst.fname, inst.mname, inst.lname) || inst.username;
                const instTitle = formatTitle(inst.designation, inst.rank);
                const instId = `inst-${inst.id}`;
                
                nodes.push({
                  id: instId,
                  pid: prevInstructorId,
                  name: instName,
                  title: instTitle || (inst.rank && inst.rank !== 'None' ? inst.rank : 'Instructor'),
                  tags: ['user']
                });
                
                prevInstructorId = instId;
              });
            });
          }

          // If there are instructors but no moderators, connect them directly to head
          if (moderators.length === 0 && instructors.length > 0) {
            // Group instructors by designation, then by rank
            const byDesignation = new Map();
            instructors.forEach(inst => {
              const desig = inst.designation || 'None';
              if (!byDesignation.has(desig)) byDesignation.set(desig, []);
              byDesignation.get(desig).push(inst);
            });

            const sortedDesignations = Array.from(byDesignation.keys()).sort((a, b) => {
              return orderIdx(a, designationOrder) - orderIdx(b, designationOrder);
            });

            let prevInstructorId = headId;
            sortedDesignations.forEach(desig => {
              const instructorsInDesig = byDesignation.get(desig);
              
              instructorsInDesig.sort((a, b) => {
                const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
                if (rnk !== 0) return rnk;
                return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
              });

              instructorsInDesig.forEach(inst => {
                const instName = formatName(inst.fname, inst.mname, inst.lname) || inst.username;
                const instTitle = formatTitle(inst.designation, inst.rank);
                const instId = `inst-${inst.id}`;
                
                nodes.push({
                  id: instId,
                  pid: prevInstructorId,
                  name: instName,
                  title: instTitle || (inst.rank && inst.rank !== 'None' ? inst.rank : 'Instructor'),
                  tags: ['user']
                });
                
                prevInstructorId = instId;
              });
            });
          }
        } else {
          // No moderators - connect instructors directly to head
          if (instructors.length > 0) {
            // Group instructors by designation, then by rank
            const byDesignation = new Map();
            instructors.forEach(inst => {
              const desig = inst.designation || 'None';
              if (!byDesignation.has(desig)) byDesignation.set(desig, []);
              byDesignation.get(desig).push(inst);
            });

            const sortedDesignations = Array.from(byDesignation.keys()).sort((a, b) => {
              return orderIdx(a, designationOrder) - orderIdx(b, designationOrder);
            });

            let prevInstructorId = headId;
            sortedDesignations.forEach(desig => {
              const instructorsInDesig = byDesignation.get(desig);
              
              instructorsInDesig.sort((a, b) => {
                const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
                if (rnk !== 0) return rnk;
                return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
              });

              instructorsInDesig.forEach(inst => {
                const instName = formatName(inst.fname, inst.mname, inst.lname) || inst.username;
                const instTitle = formatTitle(inst.designation, inst.rank);
                const instId = `inst-${inst.id}`;
                
                nodes.push({
                  id: instId,
                  pid: prevInstructorId,
                  name: instName,
                  title: instTitle || (inst.rank && inst.rank !== 'None' ? inst.rank : 'Instructor'),
                  tags: ['user']
                });
                
                prevInstructorId = instId;
              });
            });
          }
        }
      } else {
        // No department head found - create department header and connect users directly
        const deptId = `dept-${sanitize(dept)}`;
        const deptName = dept.toUpperCase() + (dept.includes('DEPARTMENT') ? '' : ' DEPARTMENT');
        nodes.push({ id: deptId, pid: rootId, name: deptName, title: '', tags: ['dept'] });

        // Add moderators first
        if (moderators.length > 0) {
          moderators.sort((a, b) => {
            const d = orderIdx(a.designation || 'None', designationOrder) - orderIdx(b.designation || 'None', designationOrder);
            if (d !== 0) return d;
            const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
            if (rnk !== 0) return rnk;
            return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
          });

          let prevModeratorId = deptId;
          moderators.forEach(mod => {
            const modName = formatName(mod.fname, mod.mname, mod.lname) || mod.username;
            const modTitle = formatTitle(mod.designation, mod.rank);
            const modId = `mod-${mod.id}`;
            
            nodes.push({
              id: modId,
              pid: prevModeratorId,
              name: modName,
              title: modTitle || 'Moderator',
              tags: ['moderator']
            });
            
            prevModeratorId = modId;
          });

          // Add instructors under last moderator
          if (instructors.length > 0) {
            const byDesignation = new Map();
            instructors.forEach(inst => {
              const desig = inst.designation || 'None';
              if (!byDesignation.has(desig)) byDesignation.set(desig, []);
              byDesignation.get(desig).push(inst);
            });

            const sortedDesignations = Array.from(byDesignation.keys()).sort((a, b) => {
              return orderIdx(a, designationOrder) - orderIdx(b, designationOrder);
            });

            let prevInstructorId = prevModeratorId;
            sortedDesignations.forEach(desig => {
              const instructorsInDesig = byDesignation.get(desig);
              
              instructorsInDesig.sort((a, b) => {
                const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
                if (rnk !== 0) return rnk;
                return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
              });

              instructorsInDesig.forEach(inst => {
                const instName = formatName(inst.fname, inst.mname, inst.lname) || inst.username;
                const instTitle = formatTitle(inst.designation, inst.rank);
                const instId = `inst-${inst.id}`;
                
                nodes.push({
                  id: instId,
                  pid: prevInstructorId,
                  name: instName,
                  title: instTitle || (inst.rank && inst.rank !== 'None' ? inst.rank : 'Instructor'),
                  tags: ['user']
                });
                
                prevInstructorId = instId;
              });
            });
          }
        } else if (instructors.length > 0) {
          // No moderators - connect instructors directly to department
          const byDesignation = new Map();
          instructors.forEach(inst => {
            const desig = inst.designation || 'None';
            if (!byDesignation.has(desig)) byDesignation.set(desig, []);
            byDesignation.get(desig).push(inst);
          });

          const sortedDesignations = Array.from(byDesignation.keys()).sort((a, b) => {
            return orderIdx(a, designationOrder) - orderIdx(b, designationOrder);
          });

          let prevInstructorId = deptId;
          sortedDesignations.forEach(desig => {
            const instructorsInDesig = byDesignation.get(desig);
            
            instructorsInDesig.sort((a, b) => {
              const rnk = orderIdx(a.rank || 'None', rankOrder) - orderIdx(b.rank || 'None', rankOrder);
              if (rnk !== 0) return rnk;
              return `${a.lname || ''}${a.fname || ''}`.toLowerCase().localeCompare(`${b.lname || ''}${b.fname || ''}`.toLowerCase());
            });

            instructorsInDesig.forEach(inst => {
              const instName = formatName(inst.fname, inst.mname, inst.lname) || inst.username;
              const instTitle = formatTitle(inst.designation, inst.rank);
              const instId = `inst-${inst.id}`;
              
              nodes.push({
                id: instId,
                pid: prevInstructorId,
                name: instName,
                title: instTitle || (inst.rank && inst.rank !== 'None' ? inst.rank : 'Instructor'),
                tags: ['user']
              });
              
              prevInstructorId = instId;
            });
          });
        }
      }
    });

    // Render OrgChart (Balkan)
    try {
      // Ensure container is visible and has fixed dimensions for proper initialization
      const container = canvasEl.parentElement;
      if (container) {
        container.style.display = 'block';
        container.style.width = '100%';
        // Set fixed height for initialization, will adjust after render
        container.style.height = '800px';
        container.style.minHeight = '600px';
        container.style.maxHeight = 'calc(100vh - 300px)';
        container.style.overflow = 'auto';
        container.style.overflowX = 'auto';
        container.style.overflowY = 'auto';
      }
      // Set fixed dimensions for canvas - OrgChart needs these to calculate viewBox
      canvasEl.style.width = '100%';
      canvasEl.style.height = '1000px';
      canvasEl.style.minHeight = '1000px';
      canvasEl.innerHTML = '';
      
      // Wait a bit for container to be properly sized
      setTimeout(() => {
        try {
          orgChartInstance = new OrgChart(canvasEl, {
            nodes,
            nodeBinding: {
              field_0: 'name',
              field_1: 'title',
              field_2: 'desc'
            },
            tags: {
              'admin-support': { 
                template: 'ana',
                nodeMenu: false
              },
              'dept-head': { 
                template: 'ana',
                nodeMenu: false
              },
              moderator: { 
                template: 'ana',
                nodeMenu: false
              },
              dept: { 
                template: 'ana',
                nodeMenu: false
              },
              user: { 
                template: 'ana',
                nodeMenu: false
              }
            },
            enableSearch: false,
            scaleInitial: 1.0, // Fixed default zoom level
            mouseScrool: OrgChart.action.none, // Disable mouse scroll zoom
            layout: OrgChart.mixed,
            align: OrgChart.ORIENTATION,
            nodeMouseClick: OrgChart.action.none,
            nodeMouseDbClick: OrgChart.action.none,
            toolbar: false,
            menu: false,
            // Spacing between nodes - extremely compact
            nodeSeparation: 6,
            levelSeparation: 20,
            siblingSeparation: 5,
            subtreeSeparation: 6
          });
          
          const chart = orgChartInstance; // Use stored instance
          
          // Apply styling after chart renders to match image 2
          setTimeout(() => {
            // Wait for chart to fully render and calculate dimensions
            if (chart) {
              try {
                // Wait a bit more for SVG to be fully rendered
                setTimeout(() => {
                  try {
                    // Ensure viewBox is valid before proceeding
                    const viewBox = chart.getViewBox();
                    if (viewBox && !isNaN(viewBox.x) && !isNaN(viewBox.y) && !isNaN(viewBox.width) && !isNaN(viewBox.height)) {
                      // Chart rendered successfully, now adjust canvas height to fit content
                      const svg = canvasEl.querySelector('svg');
                      if (svg && svg.getBBox) {
                        try {
                          const bbox = svg.getBBox();
                          if (bbox && bbox.height > 0) {
                            // Set canvas height to accommodate full chart with padding
                            const newHeight = Math.max(bbox.height + 200, 1000);
                            canvasEl.style.height = newHeight + 'px';
                            canvasEl.style.minHeight = newHeight + 'px';
                          }
                        } catch (bboxErr) {
                          // BBox might not be available immediately, use default
                          console.warn('BBox calculation warning:', bboxErr);
                        }
                      }
                    }
                  } catch (e) {
                    console.warn('ViewBox calculation warning:', e);
                    // Continue with styling even if viewBox has issues
                  }
                }, 300);
              } catch (e) {
                console.warn('Chart initialization warning:', e);
              }
            }
            
            // Prevent mouse wheel zoom on the chart canvas
            canvasEl.addEventListener('wheel', function(e) {
              // Only prevent default if not scrolling the container
              const container = canvasEl.parentElement;
              if (container) {
                // Allow container to scroll normally
                // Don't prevent default - let the browser handle scrolling
              }
            }, { passive: true });
            
            // Prevent pinch zoom on touch devices
            canvasEl.addEventListener('touchstart', function(e) {
              if (e.touches.length === 2) {
                e.preventDefault();
              }
            }, { passive: false });
            
            canvasEl.addEventListener('touchmove', function(e) {
              if (e.touches.length === 2) {
                e.preventDefault();
              }
            }, { passive: false });
            
            // Style all nodes to match image 2 appearance
            const allNodes = canvasEl.querySelectorAll('.orgchart .node');
            allNodes.forEach(node => {
              // Ensure proper text alignment
              const textElements = node.querySelectorAll('div, span, p, text');
              textElements.forEach(el => {
                el.style.textAlign = 'center';
                el.style.display = 'block';
                el.style.width = '100%';
              });
            });
            
            // Style connection lines - thin black lines
            const lines = canvasEl.querySelectorAll('.orgchart .lines .downLine, .orgchart .lines .rightLine, .orgchart .lines .leftLine, .orgchart .lines .topLine');
            lines.forEach(line => {
              line.style.backgroundColor = '#000000';
              if (line.classList.contains('downLine')) {
                line.style.width = '1px';
              } else {
                line.style.height = '1px';
              }
            });
            
            // Ensure chart background is white
            canvasEl.style.background = '#ffffff';
          }, 500);
          
          // Handle resize to prevent viewBox errors and make responsive
          if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver(entries => {
              try {
                if (chart && chart.getViewBox) {
                  const viewBox = chart.getViewBox();
                  if (viewBox && (isNaN(viewBox.x) || isNaN(viewBox.y) || isNaN(viewBox.width) || isNaN(viewBox.height))) {
                    // Skip invalid viewBox
                    return;
                  }
                }
                // Re-apply center alignment on resize
                const userNodes = canvasEl.querySelectorAll('.orgchart .node[data-tags*="user"]');
                userNodes.forEach(node => {
                  const allElements = node.querySelectorAll('*');
                  allElements.forEach(el => {
                    if (el.tagName === 'DIV' || el.tagName === 'SPAN' || el.tagName === 'P') {
                      el.style.textAlign = 'center';
                    }
                  });
                  node.style.textAlign = 'center';
                });
                
                // Update container height based on viewport
                updateChartContainerSize();
              } catch (e) {
                // Ignore viewBox errors during resize
                console.warn('ViewBox calculation warning:', e);
              }
            });
            resizeObserver.observe(canvasEl);
            resizeObserver.observe(container);
          }
          
          // Function to update chart container size responsively
          function updateChartContainerSize() {
            const container = canvasEl.parentElement;
            if (!container) return;
            
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            // Adjust container height based on viewport
            if (viewportWidth >= 1200) {
              // Large screens
              container.style.height = '900px';
              container.style.maxHeight = 'calc(100vh - 250px)';
            } else if (viewportWidth >= 992) {
              // Medium screens
              container.style.height = '750px';
              container.style.maxHeight = 'calc(100vh - 280px)';
            } else if (viewportWidth >= 768) {
              // Small screens
              container.style.height = '650px';
              container.style.maxHeight = 'calc(100vh - 300px)';
            } else {
              // Mobile screens
              container.style.height = '500px';
              container.style.maxHeight = 'calc(100vh - 250px)';
            }
            
            // Adjust canvas padding based on viewport
            if (viewportWidth >= 1200) {
              canvasEl.style.padding = '15px';
            } else if (viewportWidth >= 768) {
              canvasEl.style.padding = '10px';
            } else {
              canvasEl.style.padding = '8px';
            }
          }
          
          // Handle window resize events
          function handleResize() {
            if (resizeTimeout) {
              clearTimeout(resizeTimeout);
            }
            resizeTimeout = setTimeout(() => {
              updateChartContainerSize();
              // Re-render chart if needed
              if (orgChartInstance && orgChartInstance.draw) {
                try {
                  orgChartInstance.draw();
                } catch (e) {
                  console.warn('Chart redraw warning:', e);
                }
              }
            }, 250); // Debounce resize events
          }
          
          // Handle zoom events
          function handleZoom() {
            updateChartContainerSize();
            if (orgChartInstance && orgChartInstance.draw) {
              try {
                orgChartInstance.draw();
              } catch (e) {
                console.warn('Chart zoom redraw warning:', e);
              }
            }
          }
          
          // Add event listeners for responsive behavior
          window.addEventListener('resize', handleResize);
          window.addEventListener('orientationchange', handleResize);
          
          // Listen for zoom changes (using visualViewport API if available)
          if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', handleZoom);
            window.visualViewport.addEventListener('scroll', handleZoom);
          } else {
            // Fallback: detect zoom via devicePixelRatio changes
            let lastDevicePixelRatio = window.devicePixelRatio;
            setInterval(() => {
              if (window.devicePixelRatio !== lastDevicePixelRatio) {
                lastDevicePixelRatio = window.devicePixelRatio;
                handleZoom();
              }
            }, 500);
          }
          
          // Initial size update
          updateChartContainerSize();
        } catch (chartErr) {
          if (statusEl) {
            statusEl.textContent = 'Error: Failed to render chart - ' + (chartErr.message || 'Unknown error');
            statusEl.classList.remove('text-muted');
            statusEl.classList.add('text-danger');
          }
          orgChartLoading = false;
        }
      }, 100);
    } catch (err) {
      if (statusEl) {
        statusEl.textContent = 'Error: Failed to render chart - ' + (err.message || 'Unknown error');
        statusEl.classList.remove('text-muted');
        statusEl.classList.add('text-danger');
      }
      orgChartLoading = false;
      return;
    }

    if (statusEl) {
      statusEl.textContent = `Showing ${data.length} user(s).`;
      statusEl.classList.remove('text-danger');
      statusEl.classList.add('text-muted');
    }
    orgChartInitialized = true;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const refreshBtn = document.getElementById('orgChartRefresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => initOrgChart());
    }
    
    // Inject SweetAlert2 into iframes to fix "Swal is not defined" errors
    function injectSweetAlert2IntoIframe(iframe) {
      try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (!iframeDoc) return;
        
        // Check if SweetAlert2 is already loaded
        if (iframe.contentWindow.Swal) return;
        
        // Create script tag to load SweetAlert2
        const script = iframeDoc.createElement('script');
        script.src = '/assets/js/sweetalert2.min.js';
        script.onload = () => {
          console.log('SweetAlert2 injected into iframe:', iframe.id);
        };
        script.onerror = () => {
          console.warn('Failed to inject SweetAlert2 into iframe:', iframe.id);
        };
        iframeDoc.head.appendChild(script);
      } catch (e) {
        // Cross-origin or other error - try alternative method
        try {
          // Make parent window's Swal available to iframe
          iframe.contentWindow.Swal = window.Swal;
          // Also make parent window available for proper dialog rendering
          iframe.contentWindow.parentSwal = window.Swal;
          console.log('SweetAlert2 reference copied to iframe:', iframe.id);
        } catch (e2) {
          console.warn('Could not inject SweetAlert2 into iframe:', iframe.id, e2);
        }
      }
    }
    
    // Inject SweetAlert2 into all iframes when they load
    const iframes = document.querySelectorAll('iframe');
    iframes.forEach(iframe => {
      iframe.addEventListener('load', () => {
        setTimeout(() => injectSweetAlert2IntoIframe(iframe), 500);
      });
      
      // Also try immediately if already loaded
      if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
        setTimeout(() => injectSweetAlert2IntoIframe(iframe), 100);
      }
    });
    
    // Handle responsive iframe resizing
    function resizeIframes() {
      const iframes = document.querySelectorAll('.tab-content iframe');
      const viewportHeight = window.innerHeight;
      const headerHeight = 70;
      const padding = 20;
      
      iframes.forEach(iframe => {
        iframe.style.height = `${viewportHeight - headerHeight - padding}px`;
        iframe.style.minHeight = '500px';
      });
    }
    
    // Resize on load and window resize
    resizeIframes();
    window.addEventListener('resize', () => {
      clearTimeout(window.resizeTimeout);
      window.resizeTimeout = setTimeout(resizeIframes, 250);
    });
    
    // Handle orientation change on mobile
    window.addEventListener('orientationchange', () => {
      setTimeout(resizeIframes, 500);
    });
    
    // Enhanced table swipe functionality
    function initTableSwipeSupport() {
      const tableResponsiveElements = document.querySelectorAll('.table-responsive');
      
      tableResponsiveElements.forEach(tableContainer => {
        // Check if table is scrollable
        function updateScrollIndicator() {
          const isScrollable = tableContainer.scrollWidth > tableContainer.clientWidth;
          const isScrolledToEnd = tableContainer.scrollLeft + tableContainer.clientWidth >= tableContainer.scrollWidth - 10;
          
          if (isScrollable) {
            tableContainer.classList.add('scrollable');
            if (isScrolledToEnd) {
              tableContainer.classList.add('scrolled-to-end');
            } else {
              tableContainer.classList.remove('scrolled-to-end');
            }
          } else {
            tableContainer.classList.remove('scrollable', 'scrolled-to-end');
          }
        }
        
        // Initial check
        updateScrollIndicator();
        
        // Update on scroll
        tableContainer.addEventListener('scroll', updateScrollIndicator);
        
        // Update on resize
        const resizeObserver = new ResizeObserver(() => {
          updateScrollIndicator();
        });
        resizeObserver.observe(tableContainer);
        
        // Remove scroll hint after first scroll
        let hasScrolled = false;
        tableContainer.addEventListener('scroll', function() {
          if (!hasScrolled) {
            hasScrolled = true;
            tableContainer.style.setProperty('--scroll-hint-opacity', '0');
          }
        }, { once: false });
      });
    }
    
    // Initialize table swipe support
    initTableSwipeSupport();
    
    // Re-initialize for dynamically loaded tables (e.g., in iframes)
    setTimeout(initTableSwipeSupport, 1000);
  });
</script>
  <!-- Delete Confirmation Modal (white header like Add Department; danger action stays red) -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          Are you sure you want to delete <strong id="deleteUserName"></strong>?
        </div>
        <div class="modal-footer border-0 bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
          <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</a>
        </div>
      </div>
    </div>
  </div>

  <?php 
  // Removed automatic SweetAlert on page load - user requested removal
  // if (isset($_GET['status'])): ?>
  <!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      let status = "<?php echo $_GET['status']; ?>";
      let title = "", message = "", icon = "success";

      if (status === "created") { title = "Created!"; message = "The user has been created successfully."; }
      else if (status === "updated") { title = "Updated!"; message = "The user has been updated successfully."; }
      else if (status === "deleted") { title = "Deleted!"; message = "The user has been deleted successfully."; }
      else if (status === "demoted") { title = "Demoted!"; message = "The Admin Support has been demoted to Admin successfully."; }
      else if (status === "permissions_updated") { title = "Permissions Updated!"; message = "User permissions have been updated successfully."; }
      else if (status === "error") { 
        title = "Error!"; 
        message = "<?php 
          if (isset($_GET['message'])) {
            switch ($_GET['message']) {
              case 'invalid_department':
                echo 'Invalid department selected. Please select a valid department.';
                break;
              case 'duplicate_username':
                echo 'Username already exists. Please choose a different username.';
                break;
              case 'duplicate_email':
                echo 'Email address already exists. Please use a different email address.';
                break;
              default:
                echo 'An error occurred. Please try again.';
            }
          } else {
            echo 'An error occurred. Please try again.';
          }
        ?>"; 
        icon = "error"; 
      }

      Swal.fire({ title, text: message, icon, confirmButtonText: "OK" })
        .then(() => window.history.replaceState(null, null, window.location.pathname));
    });
  </script> -->
  <?php // endif; ?>

  <!-- Keep this small helper – it can pre-open a tab via ?tab=users etc. -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      // On mobile: Reset scroll position on initial page load
      // This ensures that if the page loads with scroll position restored, we reset it
      if (typeof isMobileViewport === 'function' && isMobileViewport()) {
        // Use requestAnimationFrame to ensure page is fully rendered
        requestAnimationFrame(() => {
          window.scrollTo({ top: 0, behavior: 'auto' });
          const mainContent = document.querySelector('.main-content') || document.querySelector('.container-fluid');
          if (mainContent) {
            mainContent.scrollIntoView({ behavior: 'auto', block: 'start' });
          }
        });
      }
      
      // Check for query parameter first (e.g., ?tab=users)
      const urlParams = new URLSearchParams(window.location.search);
      let tab = urlParams.get("tab");
      
      // If no query parameter, check for hash fragment (e.g., #users)
      if (!tab && window.location.hash) {
        tab = window.location.hash.substring(1); // Remove the # symbol
      }
      
      if (tab) {
        // Map old tab names to new tab IDs if needed
        const tabMapping = {
          'users': 'userManagement',
          'userManagement': 'userManagement',
          'roles': 'manageRoles',
          'manageRoles': 'manageRoles',
          'logs': 'activityLogs',
          'activityLogs': 'activityLogs',
          'reports': 'reports',
          'scheduleRecords': 'scheduleRecords',
          'schedule_records': 'scheduleRecords'
        };
        
        const actualTabId = tabMapping[tab] || tab;
        
        // Use Bootstrap tab API to show the tab
        const tabElement = document.getElementById(actualTabId);
        if (tabElement) {
          const tabTrigger = document.querySelector(`[data-bs-target="#${actualTabId}"]`);
          if (tabTrigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
          }
        }
      }
      
      // Always clean the URL after page load to remove query parameters
      if (window.history && window.history.replaceState && window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });
  </script>

  <!-- jQuery (required for DataTables and other plugins) -->
  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  
  <!-- Bootstrap JS -->
  <script src="/assets/js/bootstrap.bundle.min.js"></script>

  <!-- Notification System Script -->
  <script>
  (function() {
      const notificationBtn = document.getElementById('notificationBtn');
      const notificationBadge = document.getElementById('notificationBadge');
      const notificationList = document.getElementById('notificationList');
      const markAllReadBtn = document.getElementById('markAllReadBtn');
      
      if (!notificationBtn || !notificationBadge || !notificationList) {
        return; // Exit if elements don't exist
      }
      
      // Ensure Bootstrap dropdown instance is initialized for the notification button
      function ensureNotificationDropdownInstance() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
          return null;
        }
        
        // Bootstrap 5: getOrCreateInstance
        if (typeof bootstrap.Dropdown.getOrCreateInstance === 'function') {
          return bootstrap.Dropdown.getOrCreateInstance(notificationBtn);
        }
        
        // Bootstrap 4-style constructor fallback
        try {
          return new bootstrap.Dropdown(notificationBtn);
        } catch (e) {
          return null;
        }
      }
      
      // Attach explicit click handler to reliably toggle the dropdown
      notificationBtn.addEventListener('click', function(e) {
        const dropdownInstance = ensureNotificationDropdownInstance();
        if (dropdownInstance && typeof dropdownInstance.toggle === 'function') {
          e.preventDefault();
          dropdownInstance.toggle();
        }
      });
      
      // Initialize notification system
      function initNotifications() {
        loadNotifications();
        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);
      }
      
      // Load notifications
      function loadNotifications() {
        fetch('../../shared/notifications/get_notifications.php')
          .then(response => response.json())
          .then(data => {
            if (data.success && data.data) {
              if (data.data.length === 0) {
                notificationList.innerHTML = `
                  <div class="text-center text-muted p-4">
                    <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                    <small>No notifications</small>
                  </div>
                `;
                notificationBadge.style.display = 'none';
              } else {
                renderNotifications(data.data);
                // Use unread_count from API response if available, otherwise calculate from array
                const unreadCount = (typeof data.unread_count !== 'undefined') ? data.unread_count : data.data.filter(n => !n.read).length;
                updateBadge(unreadCount);
              }
            } else {
              notificationList.innerHTML = `
                <div class="text-center text-muted p-4">
                  <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                  <small>No notifications</small>
                </div>
              `;
              notificationBadge.style.display = 'none';
            }
          })
          .catch(error => {
            console.error('Error loading notifications:', error);
            notificationList.innerHTML = `
              <div class="text-center text-muted p-4">
                <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                <small>Error loading notifications</small>
              </div>
            `;
            notificationBadge.style.display = 'none';
          });
      }
      
      // Render notifications
      function renderNotifications(notifications) {
        notificationList.innerHTML = '';
        notifications.forEach(notification => {
          const item = document.createElement('li');
          const typeIcon = {
            'success': 'bi-check-circle-fill text-success',
            'warning': 'bi-exclamation-triangle-fill text-warning',
            'danger': 'bi-x-circle-fill text-danger',
            'info': 'bi-info-circle-fill text-info'
          }[notification.type] || 'bi-info-circle-fill text-info';
          
          item.className = `dropdown-item ${notification.read ? '' : 'unread'}`;
          item.style.cursor = notification.target_tab ? 'pointer' : 'default';
          const clickableIndicator = notification.target_tab ? '<i class="bi bi-arrow-right text-muted ms-auto"></i>' : '';
          item.innerHTML = `
            <div class="d-flex align-items-start">
              <i class="bi ${typeIcon} me-2 mt-1"></i>
              <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="notification-title">${escapeHtml(notification.title)}</div>
                  ${clickableIndicator}
                </div>
                <div class="notification-message">${escapeHtml(notification.message)}</div>
                <div class="notification-time">
                  <small>${formatTime(notification.created_at)}</small>
                  ${notification.actor ? `<small class="text-muted ms-2">• ${escapeHtml(notification.actor)}</small>` : ''}
                </div>
              </div>
            </div>
          `;
          item.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            handleNotificationClick(notification);
          });
          notificationList.appendChild(item);
        });
      }
      
      // Handle notification click - navigate to appropriate tab/page
      function handleNotificationClick(notification) {
        // Mark as read
        markAsRead(notification.id);
        
        // Close dropdown
        const dropdown = bootstrap.Dropdown.getInstance(notificationBtn);
        if (dropdown) {
          dropdown.hide();
        }
        
        // Get notification details for specific navigation
        const details = notification.details || {};
        const accId = details.acc_id || details.user_id || null;
        const roomId = details.room_id || null;
        const roomRequestId = details.room_request_id || null;
        
        // Navigate to target tab if specified
        if (notification.target_tab) {
          navigateToTab(notification.target_tab, notification, details);
        } else if (notification.target_url) {
          window.location.href = notification.target_url;
        }
      }
      
      // Navigate to a specific tab and optionally open specific details
      function navigateToTab(tabId, notification = null, details = {}) {
        // Map notification target tabs to admin support dashboard tabs
        const tabMapping = {
          'userManagement': 'userManagement',
          'manageRoles': 'manageRoles',
          'activityLogs': 'activityLogs',
          'reports': 'reports',
          'scheduleRecords': 'scheduleRecords',
          'roles': 'manageRoles',
          'room_requests': 'room_requests',
          'room_requests_rooms': 'room_requests'
        };
        
        const actualTabId = tabMapping[tabId] || tabId;
        
        // Use Bootstrap tab API to show the tab
        const tabElement = document.getElementById(actualTabId);
        if (tabElement) {
          const tabTrigger = document.querySelector(`[data-bs-target="#${actualTabId}"]`);
          if (tabTrigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
          }
        }
        
        // After tab is shown, open specific details if available
        setTimeout(() => {
          openNotificationDetails(actualTabId, notification, details);
        }, 500); // Wait for tab to load
      }
      
      // Open specific details based on notification type and details
      function openNotificationDetails(tabId, notification, details) {
        const accId = details.acc_id || details.user_id || null;
        const roomId = details.room_id || null;
        const roomRequestId = details.room_request_id || null;
        const action = notification?.action || '';
        
        // Handle user-related notifications
        if (tabId === 'userManagement' && accId) {
          // Check if viewUserDetails function exists (from user_management.php iframe)
          const userManagementFrame = document.querySelector('iframe[src*="user_management.php"]');
          if (userManagementFrame && userManagementFrame.contentWindow) {
            try {
              // Call viewUserDetails in the iframe
              if (typeof userManagementFrame.contentWindow.viewUserDetails === 'function') {
                userManagementFrame.contentWindow.viewUserDetails(accId);
              } else if (typeof userManagementFrame.contentWindow.editUser === 'function') {
                // Fallback to editUser if viewUserDetails not available
                userManagementFrame.contentWindow.editUser(accId);
              }
            } catch (e) {
              console.warn('Could not open user details in iframe:', e);
              // Fallback: try to search/filter for the user
              try {
                const searchInput = userManagementFrame.contentDocument?.querySelector('input[type="search"], input[name*="search"]');
                if (searchInput) {
                  // Could trigger search, but better to wait for iframe load
                  userManagementFrame.addEventListener('load', () => {
                    setTimeout(() => {
                      if (typeof userManagementFrame.contentWindow.viewUserDetails === 'function') {
                        userManagementFrame.contentWindow.viewUserDetails(accId);
                      }
                    }, 500);
                  });
                }
              } catch (e2) {
                console.warn('Could not access iframe content:', e2);
              }
            }
          } else {
            // If not in iframe, try to call directly (if on same page)
            if (typeof window.viewUserDetails === 'function') {
              window.viewUserDetails(accId);
            } else if (typeof window.editUser === 'function') {
              window.editUser(accId);
            }
          }
        }
        
        // Handle room request notifications
        if ((tabId === 'room_requests' || tabId.includes('room')) && (roomRequestId || roomId)) {
          // Check if there's a room request frame or modal
          const roomRequestFrame = document.querySelector('iframe[src*="room"]');
          if (roomRequestFrame && roomRequestFrame.contentWindow) {
            try {
              // Try to open specific room request
              if (typeof roomRequestFrame.contentWindow.viewRoomRequest === 'function') {
                roomRequestFrame.contentWindow.viewRoomRequest(roomRequestId || roomId);
              } else if (typeof roomRequestFrame.contentWindow.openRoomRequestModal === 'function') {
                roomRequestFrame.contentWindow.openRoomRequestModal(roomRequestId || roomId);
              }
            } catch (e) {
              console.warn('Could not open room request in iframe:', e);
            }
          }
        }
        
        // Handle role/permission notifications
        if (tabId === 'manageRoles' && accId) {
          // For role-related notifications, could highlight the user in the role assignment table
          const manageRolesFrame = document.querySelector('iframe[src*="manage_roles.php"]');
          if (manageRolesFrame && manageRolesFrame.contentWindow) {
            try {
              // Could scroll to user in role assignment table
              const userRow = manageRolesFrame.contentDocument?.querySelector(`tr[data-user-id="${accId}"], tr:has(input[value="${accId}"])`);
              if (userRow) {
                userRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                userRow.style.backgroundColor = '#fff3cd';
                setTimeout(() => {
                  userRow.style.backgroundColor = '';
                }, 2000);
              }
            } catch (e) {
              console.warn('Could not highlight user in manage roles:', e);
            }
          }
        }
      }
      
      // Mark notification as read
      function markAsRead(notificationId) {
        fetch('../../shared/notifications/mark_as_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Reload notifications to update read status
            loadNotifications();
          }
        })
        .catch(error => {
          console.error('Error marking notification as read:', error);
        });
      }
      
      // Mark all as read
      if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          fetch('../../shared/notifications/mark_all_as_read.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              loadNotifications();
            }
          })
          .catch(error => {
            console.error('Error marking all as read:', error);
          });
        });
      }
      
      // Update badge - accepts unread count directly
      function updateBadge(unreadCount) {
        // Ensure unreadCount is a number
        const count = typeof unreadCount === 'number' ? unreadCount : 0;
        
        if (count > 0) {
          // Show badge with count number
          notificationBadge.textContent = count > 99 ? '99+' : count.toString();
          notificationBadge.style.display = 'flex';
          // Adjust border-radius for single digit vs double digit
          if (count < 10) {
            notificationBadge.style.borderRadius = '50%';
            notificationBadge.style.minWidth = notificationBadge.style.height;
          } else {
            notificationBadge.style.borderRadius = '9px';
          }
        } else {
          notificationBadge.style.display = 'none';
          notificationBadge.textContent = '';
        }
      }
      
      // Helper functions
      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
      
      function formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        return date.toLocaleDateString();
      }
      
      // Initialize on page load
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotifications);
      } else {
        initNotifications();
      }
    })();
  </script>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p class="mb-0">&copy; 2025 EVSU-OCC Scheduling System. All rights reserved.</p>
    </div>
  </footer>

</body>
</html>