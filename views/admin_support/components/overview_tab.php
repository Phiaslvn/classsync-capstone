<?php
/**
 * Admin Support – Overview tab
 * Shows department statistics and provides Add Department entry point.
 */

// Summary stats per department
$deptStatsStmt = $conn->prepare("
    SELECT 
        d.dept_id,
        d.dept_name,
        d.dept_status,
        COUNT(a.acc_id) AS total_users,
        SUM(CASE WHEN a.acc_status = 'Active' THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN a.acc_status = 'Inactive' THEN 1 ELSE 0 END) AS inactive_users,
        SUM(CASE WHEN a.acc_status = 'Pending' THEN 1 ELSE 0 END) AS pending_users
    FROM department d
    LEFT JOIN account a ON a.dept_id = d.dept_id
    GROUP BY d.dept_id, d.dept_name, d.dept_status
    ORDER BY d.dept_name
");
$deptStatsStmt->execute();
$deptRows = $deptStatsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$deptStatsStmt->close();

$totalDepartments = count($deptRows);
$activeDepartments = 0;
$totalUsersAll = 0;
foreach ($deptRows as $row) {
    if (($row['dept_status'] ?? 'Active') === 'Active') {
        $activeDepartments++;
    }
    $totalUsersAll += (int)($row['total_users'] ?? 0);
}
?>

<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-1"><i class="bi bi-speedometer2 me-2"></i>Admin Support Overview</h5>
        <p class="text-muted mb-0">Quick view of departments and their users.</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-maroon btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
          <i class="bi bi-building me-1"></i> + ADD DEPARTMENT
        </button>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="stats-card">
      <div class="stat-number"><?= htmlspecialchars($totalDepartments) ?></div>
      <div class="stat-label">Total Departments</div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="stats-card">
      <div class="stat-number"><?= htmlspecialchars($activeDepartments) ?></div>
      <div class="stat-label">Active Departments</div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="stats-card">
      <div class="stat-number"><?= htmlspecialchars($totalUsersAll) ?></div>
      <div class="stat-label">Users with Departments</div>
    </div>
  </div>
</div>

<div class="dashboard-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Departments and Users</h6>
    <span class="text-muted small">Shows all departments with user counts</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Department</th>
          <th>Status</th>
          <th class="text-center">Total Users</th>
          <th class="text-center">Active</th>
          <th class="text-center">Inactive</th>
          <th class="text-center">Pending</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($deptRows)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              No departments found. Use <strong>+ ADD DEPARTMENT</strong> to create one.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($deptRows as $dept): ?>
            <?php
              $status = $dept['dept_status'] ?? 'Active';
              $badgeClass = $status === 'Active' ? 'success' : 'secondary';
            ?>
            <tr>
              <td><?= htmlspecialchars($dept['dept_name']) ?></td>
              <td>
                <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
              </td>
              <td class="text-center"><?= (int)$dept['total_users'] ?></td>
              <td class="text-center text-success"><?= (int)$dept['active_users'] ?></td>
              <td class="text-center text-secondary"><?= (int)$dept['inactive_users'] ?></td>
              <td class="text-center text-warning"><?= (int)$dept['pending_users'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

