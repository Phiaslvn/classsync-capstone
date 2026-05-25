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
$filter_action = sanitizeInput($_GET['action'] ?? '');
$filter_date_from = sanitizeInput($_GET['date_from'] ?? '');
$filter_date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters (show all users' activity for Admin support)
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_action)) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%$filter_action%";
    $types .= 's';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(al.log_date) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(al.log_date) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "
    SELECT COUNT(*) as total
    FROM audit_log al
    $where_clause
";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_logs = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = max(1, ceil($total_logs / $per_page));

// Get logs with pagination
$logs_sql = "
    SELECT al.*, a.fname, a.lname, a.acc_user
    FROM audit_log al
    JOIN account a ON al.acc_id = a.acc_id
    $where_clause
    ORDER BY al.log_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($logs_sql);
$limit_params = array_merge($params, [$per_page, $offset]);
$limit_types = $types . 'ii';
$stmt->bind_param($limit_types, ...$limit_params);
$stmt->execute();
$logs_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVSU-OCC Scheduling System - Activity Logs</title>
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
    .main-content { margin-left:280px; padding-top:90px; padding-bottom:80px; min-height:calc(100vh - 170px); background:#f8f9fa; }
    .dashboard-card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); background:#ffffff; margin-bottom:2rem; padding:1.5rem; min-height:auto; border:1px solid rgba(0, 0, 0, 0.05); position:relative; overflow:hidden; }
    .dashboard-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .dashboard-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #800000, #f5f5dc); }
    .dashboard-card h5 { color:#495057; font-weight:600; font-size:1.125rem; margin-bottom:0.75rem; }
    .dashboard-card h6 { color:#495057; font-weight:600; font-size:1rem; margin-bottom:0.5rem; }
    .dashboard-card p { color:#6c757d; font-size:0.875rem; line-height:1.5; margin-bottom:0.5rem; }
    .btn-maroon { background:linear-gradient(135deg, #800000 0%, #660000 100%); color:#fff; border:none; font-weight:600; border-radius:8px; }
    .btn-maroon:hover { background:linear-gradient(135deg, #660000 0%, #800000 100%); color:#fff; box-shadow:0 4px 16px rgba(0,0,0,0.12); }
    .footer { background-color:#800000; color:#fff; text-align:center; width:100%; position:fixed; left:0; bottom:0; font-size:0.9rem; padding:0.5rem 0; z-index:1080; }
    .bg-maroon { background-color:#800000 !important; }
    .log-entry { border-left: 4px solid #800000; padding: 1rem; margin-bottom: 1rem; background: #f8f9fa; border-radius: 8px; }
    .log-action { font-weight: 600; color: #495057; font-size: 0.875rem; }
    .log-date { font-size: 0.875rem; color: #6c757d; }
    .log-details { margin-top: 0.5rem; font-size: 0.875rem; color: #6c757d; }
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
      <!-- Activity Logs -->
      <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h5><i class="bi bi-journal-text me-2"></i>My Activity Logs</h5>
            <p class="text-muted mb-0">Track your administrative support activities and system interactions</p>
          </div>
          <span class="badge bg-maroon fs-6">Admin Support</span>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
          <div class="col-md-3 mb-3">
            <div class="stats-card">
              <div class="text-center">
                <i class="bi bi-journal-text" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
                <div class="stat-number"><?= number_format($total_logs) ?></div>
                <div class="stat-label">Total Activities</div>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="stats-card">
              <div class="text-center">
                <i class="bi bi-calendar-day" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
                <div class="stat-number" style="font-size: 1.5rem;"><?= date('M d, Y') ?></div>
                <div class="stat-label">Today</div>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="stats-card">
              <div class="text-center">
                <i class="bi bi-person-check" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
                <div class="stat-number" style="font-size: 1.25rem;"><?= htmlspecialchars($username) ?></div>
                <div class="stat-label">Admin Support User</div>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="stats-card">
              <div class="text-center">
                <i class="bi bi-shield-check" style="font-size: 2rem; color: #800000; margin-bottom: 0.5rem;"></i>
                <div class="stat-number" style="font-size: 1.5rem;">Active</div>
                <div class="stat-label">Status</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="dashboard-card">
              <h6 class="mb-3" style="color: #495057; font-weight: 600; font-size: 1rem;"><i class="bi bi-funnel me-2"></i>Filter Activities</h6>
              <div>
                <form method="GET" class="row g-3">
                  <div class="col-md-3">
                    <label for="action" class="form-label">Action Type</label>
                    <input type="text" class="form-control" id="action" name="action" 
                           value="<?= htmlspecialchars($filter_action) ?>" 
                           placeholder="e.g., login, manage, support">
                  </div>
                  <div class="col-md-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?= htmlspecialchars($filter_date_from) ?>">
                  </div>
                  <div class="col-md-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?= htmlspecialchars($filter_date_to) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                      <button type="submit" class="btn btn-maroon">
                        <i class="bi bi-search me-1"></i>Filter
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Logs -->
        <div class="row">
          <div class="col-12">
            <div class="dashboard-card">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0" style="color: #495057; font-weight: 600; font-size: 1rem;"><i class="bi bi-list-ul me-2"></i>Activity History</h6>
                <span class="badge bg-maroon"><?= $total_logs ?> entries</span>
              </div>
              <div>
                <?php if ($logs_result->num_rows > 0): ?>
                  <?php while ($log = $logs_result->fetch_assoc()): ?>
                    <div class="log-entry">
                      <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                          <div class="log-action">
                            <i class="bi bi-activity me-1"></i>
                            <?= htmlspecialchars($log['action']) ?>
                          </div>
                          <div class="log-date">
                            <i class="bi bi-clock me-1"></i>
                            <?= date('M d, Y g:i A', strtotime($log['log_date'])) ?>
                          </div>
                          <?php if (!empty($log['details'])): ?>
                            <div class="log-details">
                              <i class="bi bi-info-circle me-1"></i>
                              <?= htmlspecialchars($log['details']) ?>
                            </div>
                          <?php endif; ?>
                        </div>
                        <div class="text-end">
                          <span class="badge bg-secondary">
                            <?= htmlspecialchars($log['acc_user']) ?>
                          </span>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <div class="text-center py-5">
                    <i class="bi bi-journal-x fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No activity logs found</h5>
                    <p class="text-muted">Your administrative support activities will appear here.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="row mt-4">
            <div class="col-12">
              <nav aria-label="Activity logs pagination">
                <ul class="pagination justify-content-center">
                  <?php if ($page > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                      </a>
                    </li>
                  <?php endif; ?>
                  
                  <li class="page-item active">
                    <span class="page-link">
                      Page <?= $page ?> of <?= $total_pages ?>
                    </span>
                  </li>
                  
                  <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        Next <i class="bi bi-chevron-right"></i>
                      </a>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
            </div>
          </div>
        <?php endif; ?>
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
