<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

if (!requireRole('Admin support', '../../admin/auth/login_admin.php')) {
    exit();
}

$acc_id = $_SESSION['acc_id'];

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

// Get filter parameters
$report_type = sanitizeInput($_GET['report_type'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$department = sanitizeInput($_GET['department'] ?? '');

// Get statistics for reports
$stats = [];

// Total Users
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM account WHERE acc_status != 'Deleted'");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_users'] = $result->fetch_assoc()['total'];
$stmt->close();

// Active Users
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM account WHERE acc_status = 'Active'");
$stmt->execute();
$result = $stmt->get_result();
$stats['active_users'] = $result->fetch_assoc()['total'];
$stmt->close();

// Total Schedules
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM schedule WHERE schd_status = 'Active'");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_schedules'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total Activity Logs
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_log");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_logs'] = $result->fetch_assoc()['total'];
$stmt->close();

// Get departments for filter
$stmt = $conn->prepare("SELECT dept_id, dept_name FROM department ORDER BY dept_name");
$stmt->execute();
$departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVSU-OCC Scheduling System - System Reports</title>
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
    .main-content { margin-left:280px; padding-top:90px; padding-bottom:80px; min-height:calc(100vh - 170px); background:#ffffff; }
    .dashboard-card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); background:#ffffff; margin-bottom:2rem; padding:1.5rem; min-height:auto; border:1px solid rgba(0, 0, 0, 0.05); position:relative; overflow:hidden; }
    .dashboard-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .dashboard-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #800000, #f5f5dc); }
    .dashboard-card h5 { color:#495057; font-weight:600; font-size:1.125rem; margin-bottom:0.75rem; }
    .dashboard-card h6 { color:#495057; font-weight:600; font-size:1rem; margin-bottom:0.5rem; }
    .dashboard-card p { color:#6c757d; font-size:0.875rem; line-height:1.5; margin-bottom:0.5rem; }
    .btn-maroon { background:linear-gradient(135deg, #800000 0%, #660000 100%); color:#fff; border:none; font-weight:600; border-radius:8px; }
    .btn-maroon:hover { background:linear-gradient(135deg, #660000 0%, #800000 100%); color:#fff; box-shadow:0 4px 16px rgba(0,0,0,0.12); }
    .btn-outline-maroon { border:2px solid #800000; color:#800000; font-weight:600; border-radius:8px; background:transparent; }
    .btn-outline-maroon:hover { background:#800000; color:#fff; }
    .footer { background-color:#800000; color:#fff; text-align:center; width:100%; position:fixed; left:0; bottom:0; font-size:0.9rem; padding:0.5rem 0; z-index:1080; }
    .bg-maroon { background-color:#800000 !important; }
    .stats-card { background:#ffffff; border-left:4px solid #800000; border-radius:12px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid rgba(0, 0, 0, 0.05); }
    .stats-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .stats-card .stat-number { color:#800000; font-size:2.5rem; font-weight:800; line-height:1; background:linear-gradient(135deg, #800000, #f5f5dc); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .stats-card .stat-label { color:#6c757d; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; }
    .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }
    .modal-content { border:none; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.2); }
    .modal-header { background:linear-gradient(135deg, #800000 0%, #660000 100%); color:#fff; border-radius:16px 16px 0 0; border-bottom:2px solid rgba(255,255,255,0.1); padding:1.25rem 1.5rem; }
    .modal-header .modal-title { color:#fff; font-weight:600; font-size:1.1rem; }
    .modal-header .btn-close-white { filter:brightness(0) invert(1); opacity:0.9; }
    .modal-header .btn-close-white:hover { opacity:1; }
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
  </style>
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
          <a class="nav-link" href="user_management.php" onclick="closeOffcanvas(); return false;">
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
        <a class="nav-link" href="user_management.php">
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
<!-- Reports Content -->
      <div class="dashboard-card">
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
            <h5><i class="bi bi-bar-chart me-2"></i>System Reports</h5>
            <p class="text-muted mb-0">Generate and view system-wide reports across all departments.</p>
  </div>
          <span class="badge bg-maroon fs-6">Admin Support</span>
</div>

<!-- Statistics Cards -->
        <div class="row">
  <div class="col-lg-3 col-md-6 mb-3">
    <div class="stats-card">
      <div class="text-center">
        <i class="bi bi-people" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
        <div class="stat-label">Total Users</div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 mb-3">
    <div class="stats-card">
      <div class="text-center">
        <i class="bi bi-person-check" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <div class="stat-number"><?= number_format($stats['active_users']) ?></div>
        <div class="stat-label">Active Users</div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 mb-3">
    <div class="stats-card">
      <div class="text-center">
        <i class="bi bi-calendar-week" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <div class="stat-number"><?= number_format($stats['total_schedules']) ?></div>
        <div class="stat-label">Total Schedules</div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 mb-3">
    <div class="stats-card">
      <div class="text-center">
        <i class="bi bi-journal-text" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <div class="stat-number"><?= number_format($stats['total_logs']) ?></div>
        <div class="stat-label">Activity Logs</div>
      </div>
    </div>
  </div>
</div>

<!-- Report Filters -->
<div class="dashboard-card mb-4">
  <h6 class="mb-3" style="color: #495057; font-weight: 600; font-size: 1rem;"><i class="bi bi-funnel me-2"></i>Generate Report</h6>
  <form method="GET" class="row g-3">
    <div class="col-md-3">
      <label class="form-label" style="color: #495057; font-weight: 500; font-size: 0.9rem;">Report Type</label>
      <select class="form-select" name="report_type" style="border: 2px solid #e9ecef; border-radius: 8px;">
        <option value="">All Reports</option>
        <option value="users" <?= $report_type === 'users' ? 'selected' : '' ?>>User Reports</option>
        <option value="schedules" <?= $report_type === 'schedules' ? 'selected' : '' ?>>Schedule Reports</option>
        <option value="activity" <?= $report_type === 'activity' ? 'selected' : '' ?>>Activity Reports</option>
        <option value="system" <?= $report_type === 'system' ? 'selected' : '' ?>>System Reports</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" style="color: #495057; font-weight: 500; font-size: 0.9rem;">Department</label>
      <select class="form-select" name="department" style="border: 2px solid #e9ecef; border-radius: 8px;">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= $dept['dept_id'] ?>" <?= $department == $dept['dept_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($dept['dept_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label" style="color: #495057; font-weight: 500; font-size: 0.9rem;">From Date</label>
      <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="border: 2px solid #e9ecef; border-radius: 8px;">
    </div>
    <div class="col-md-2">
      <label class="form-label" style="color: #495057; font-weight: 500; font-size: 0.9rem;">To Date</label>
      <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="border: 2px solid #e9ecef; border-radius: 8px;">
    </div>
    <div class="col-md-2">
      <label class="form-label">&nbsp;</label>
      <div class="d-grid">
        <button type="submit" class="btn btn-maroon">
          <i class="bi bi-search me-1"></i>Generate
        </button>
      </div>
    </div>
  </form>
</div>

<!-- Report Cards -->
<div class="row mb-4">
  <div class="col-lg-4 col-md-6 mb-3">
    <div class="stats-card" style="cursor: pointer;" onclick="generateReport('users')">
      <div class="text-center">
        <i class="bi bi-people" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <h6 class="mb-2" style="color: #495057; font-weight: 600; font-size: 1rem;">User Reports</h6>
        <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">View user activity, registration, and management reports.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 mb-3">
    <div class="stats-card" style="cursor: pointer;" onclick="generateReport('schedules')">
      <div class="text-center">
        <i class="bi bi-calendar-week" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <h6 class="mb-2" style="color: #495057; font-weight: 600; font-size: 1rem;">Schedule Reports</h6>
        <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">Generate schedule utilization and conflict reports.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 mb-3">
    <div class="stats-card" style="cursor: pointer;" onclick="generateReport('activity')">
      <div class="text-center">
        <i class="bi bi-journal-text" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
        <h6 class="mb-2" style="color: #495057; font-weight: 600; font-size: 1rem;">Activity Reports</h6>
        <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">View system activity and audit log reports.</p>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="dashboard-card">
  <h6 class="mb-3" style="color: #495057; font-weight: 600; font-size: 1rem;">Quick Report Actions</h6>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-maroon" onclick="exportReport('pdf')">
      <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
    </button>
    <button class="btn btn-outline-maroon" onclick="exportReport('excel')">
      <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
    </button>
    <button class="btn btn-outline-maroon" onclick="exportReport('csv')">
      <i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV
    </button>
    <button class="btn btn-outline-maroon" onclick="window.print()">
      <i class="bi bi-printer me-2"></i>Print Report
    </button>
  </div>
</div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <span>&copy; 2024 EVSU-OCC Scheduling System. All rights reserved.</span>
    </div>
  </footer>

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

  <!-- Bootstrap JS -->
  <script src="../../public/assets/js/bootstrap.bundle.min.js"></script>

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
    
function generateReport(type) {
  const params = new URLSearchParams(window.location.search);
  params.set('report_type', type);
  window.location.search = params.toString();
}

function exportReport(format) {
  const params = new URLSearchParams(window.location.search);
  params.set('export', format);
  window.location.href = 'export_report.php?' + params.toString();
}
</script>

<script>
// Detect if page is loaded in an iframe and hide sidebar
(function() {
  if (window.self !== window.top) {
    // Page is loaded in an iframe
    document.documentElement.classList.add('iframe-mode');
    document.body.classList.add('iframe-mode');
  }
})();
</script>

</body>
</html>
