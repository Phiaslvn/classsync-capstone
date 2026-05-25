<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';

// Only allow Admin Support users to manage departments
requireRole('Admin support', 'login_admin.php');

$acc_id = $_SESSION['acc_id'];
$username = $_SESSION['acc_user'] ?? 'User';
$dept_id = isset($_SESSION['dept_id']) ? (int)$_SESSION['dept_id'] : null;
$dept_name = $_SESSION['dept_name'] ?? 'All Departments';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "CSRF token mismatch";
    } else if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add_department') {
            $dept_name = sanitizeInput($_POST['dept_name']);
            $dept_desc = sanitizeInput($_POST['dept_desc']);
            
            if (!empty($dept_name)) {
                $stmt = $conn->prepare("INSERT INTO department (dept_name, dept_desc) VALUES (?, ?)");
                $stmt->bind_param("ss", $dept_name, $dept_desc);
                if ($stmt->execute()) {
                    $success_message = "Department added successfully!";
                    // Log the action
                    logAdminAction($_SESSION['acc_id'], 'create_department', "Created department: $dept_name");
                } else {
                    $error_message = "Failed to add department.";
                }
                $stmt->close();
            } else {
                $error_message = "Department name is required.";
            }
        }
    }
}

// Get all departments
$stmt = $conn->prepare("SELECT * FROM department ORDER BY dept_name");
$stmt->execute();
$result = $stmt->get_result();
$departments = [];
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Department Management - EVSU-OCC Scheduling System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
  <link rel="stylesheet" href='../shared/assets/css/style.css'>
  <style>
    body { font-family: 'Poppins', Arial, sans-serif; background: #f8f9fa; margin:0; padding:0; }
    .navbar { z-index:1100; }
    .sidebar { background:#800000; color:#fff; min-height:100vh; position:fixed; top:56px; left:0; width:220px; padding-top:1rem; z-index:1090; }
    .sidebar .nav-link { color:#fff !important; font-weight:500; margin-bottom:8px; border-radius:8px; transition:background 0.2s; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background:#660000; color:#fff !important; }
    .main-content { margin-left:220px; padding-top:80px; padding-bottom:70px; min-height:calc(100vh - 150px); }
    .dashboard-card { border-radius:16px; box-shadow:0 4px 24px rgba(128,0,0,0.08); background:#fff; margin-bottom:2rem; padding:2rem; min-height:180px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .dashboard-card:hover { transform: translateY(-2px); box-shadow:0 8px 32px rgba(128,0,0,0.12); }
    .dashboard-card h5 { color:#800000; font-weight:700; }
    .btn-maroon { background:#800000; color:#fff; }
    .btn-maroon:hover { background:#660000; color:#fff; }
    .footer { background-color:#800000; color:#fff; text-align:center; width:100%; position:fixed; left:0; bottom:0; font-size:0.9rem; padding:0.5rem 0; z-index:1080; }
    .bg-maroon { background-color:#800000 !important; }
    @media (max-width: 991.98px) {
      .sidebar { display:none !important; }
      .main-content { margin-left:0 !important; padding-top:80px !important; padding-bottom:70px !important; }
      .dashboard-card { padding:1rem; margin-bottom:1.5rem; min-height:unset; }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top shadow-sm" style="height:70px;">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="../index.php">
        <img src="../assets/img/evsu-logo.png" alt="EVSU Logo" width="30" height="30" class="me-2">
        <span class="fw-bold">EVSU-OCC Scheduling System</span>
      </a>
      <span class="navbar-text ms-auto fw-semibold text-white d-none d-lg-block">Department Management</span>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <div class="container-fluid">
      <!-- Header -->
      <div class="dashboard-card mb-4">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h4 class="mb-2">Department Management</h4>
            <p class="text-muted mb-0">Manage departments and their configurations</p>
          </div>
          <div class="col-md-4 text-end">
            <a href="admin_dashboard.php" class="btn btn-outline-maroon">
              <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
          </div>
        </div>
      </div>

      <!-- Success/Error Messages -->
      <?php if (isset($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>

      <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>

      <!-- Add Department Form -->
      <div class="dashboard-card mb-4">
        <h5 class="mb-3">Add New Department</h5>
        <form method="POST">
          <?= getCSRFTokenInput() ?>
          <input type="hidden" name="action" value="add_department">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="dept_name" class="form-label">Department Name</label>
              <input type="text" class="form-control" id="dept_name" name="dept_name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="dept_desc" class="form-label">Description</label>
              <textarea class="form-control" id="dept_desc" name="dept_desc" rows="3"></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-maroon">
            <i class="bi bi-plus-circle me-2"></i>Add Department
          </button>
        </form>
      </div>

      <!-- Departments List -->
      <div class="dashboard-card">
        <h5 class="mb-3">Existing Departments</h5>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>Department Name</th>
                <th>Description</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($departments as $dept): ?>
              <tr>
                <td><?= htmlspecialchars($dept['dept_id']) ?></td>
                <td>
                  <strong><?= htmlspecialchars($dept['dept_name']) ?></strong>
                </td>
                <td><?= htmlspecialchars($dept['dept_desc'] ?? 'No description') ?></td>
                <td>
                  <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editDepartment(<?= $dept['dept_id'] ?>, '<?= htmlspecialchars($dept['dept_name']) ?>', '<?= htmlspecialchars($dept['dept_desc'] ?? '') ?>')">
                      <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="viewDepartmentDetails(<?= $dept['dept_id'] ?>)">
                      <i class="bi bi-eye"></i> View
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container-fluid">
      <span>&copy; 2025 EVSU-OCC Scheduling System. All rights reserved.</span>
    </div>
  </footer>

  <!-- jQuery (required for DataTables and other plugins) -->
  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  
  <!-- Bootstrap JS -->
  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script>
    function editDepartment(id, name, desc) {
      // Simple edit functionality - you can enhance this with a modal
      const newName = prompt('Edit Department Name:', name);
      if (newName && newName !== name) {
        // Here you would typically make an AJAX call to update the department
        alert('Edit functionality would be implemented here. Department: ' + newName);
      }
    }

    function viewDepartmentDetails(id) {
      // Redirect to department details page or show modal
      alert('View department details for ID: ' + id);
    }
  </script>
</body>
</html>
