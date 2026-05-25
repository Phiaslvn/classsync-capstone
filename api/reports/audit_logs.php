<?php
/**
 * Audit Logs Viewer for Admin Support
 * Displays system audit logs with filtering and pagination
 */

// Include unified security middleware
require_once '../includes/auth/security_middleware.php';

// Check if user is logged in and has admin support role
requireRole('Admin support', '../index.php');

$userInfo = getUserInfo();
$userRole = getUserRole();

if (!$userInfo || !$userRole) {
    header("Location: ../index.php");
    exit();
}

$acc_id = $userInfo['acc_id'];
$username = $userInfo['fname'] . ' ' . $userInfo['lname'];

// Get filter parameters
$filter_user = sanitizeInput($_GET['user'] ?? '');
$filter_action = sanitizeInput($_GET['action'] ?? '');
$filter_date_from = sanitizeInput($_GET['date_from'] ?? '');
$filter_date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_user)) {
    $where_conditions[] = "(a.fname LIKE ? OR a.lname LIKE ? OR a.acc_user LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $types .= 'sss';
}

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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) as total
    FROM audit_log al
    JOIN account a ON al.acc_id = a.acc_id
    $where_clause
";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_records / $per_page);

// Get audit logs
$sql = "
    SELECT al.*, a.fname, a.lname, a.acc_user, a.acc_email
    FROM audit_log al
    JOIN account a ON al.acc_id = a.acc_id
    $where_clause
    ORDER BY al.log_date DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();
?>

<?php include '../includes/dashboard/dashboard_header.php'; ?>

<!-- Include Role-Specific Sidebar -->
<?php include '../includes/role_sidebars.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <div class="container-fluid">
        
        <!-- Audit Logs Tab -->
        <div id="logs" class="dashboard-card tab-content fade-in">
            <h5 class="mb-4">
                <i class="bi bi-journal-text me-2"></i>
                System Audit Logs
            </h5>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Logs</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="tab" value="logs">
                        
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <input type="text" class="form-control" name="user" 
                                   value="<?= htmlspecialchars($filter_user) ?>" 
                                   placeholder="Search by name or username">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Action</label>
                            <input type="text" class="form-control" name="action" 
                                   value="<?= htmlspecialchars($filter_action) ?>" 
                                   placeholder="Search by action">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?= htmlspecialchars($filter_date_from) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?= htmlspecialchars($filter_date_to) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="?tab=logs" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Logs Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Audit Logs (<?= number_format($total_records) ?> total)
                    </h6>
                    <div class="text-muted small">
                        Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_records) ?> of <?= number_format($total_records) ?>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                            <h5 class="text-muted">No audit logs found</h5>
                            <p class="text-muted">Try adjusting your filters or check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= date('M j, Y', strtotime($log['log_date'])) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('g:i A', strtotime($log['log_date'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($log['fname'] . ' ' . $log['lname']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($log['acc_user']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($log['details'])): ?>
                                                    <div class="text-truncate" style="max-width: 300px;" 
                                                         title="<?= htmlspecialchars($log['details']) ?>">
                                                        <?= htmlspecialchars($log['details']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code class="small"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Audit logs pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/dashboard/dashboard_footer.php'; ?>
