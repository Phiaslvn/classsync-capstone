<!-- EVSU-OCC User Management System v3.0 -->
<!-- Hidden CSRF token for AJAX requests -->
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

<!-- User Management Content -->
<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
            <h5><i class="bi bi-people me-2"></i>User Management</h5>
            <p class="text-muted mb-0">Manage user accounts, roles, and permissions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-maroon btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus me-1"></i> + ADD USER
                        </button>
                    </div>
        <span class="badge bg-maroon fs-6">Admin Support</span>
</div>

<!-- Real-time Statistics Dashboard -->
<?php
// Get comprehensive user statistics
$user_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN acc_status != 'Deleted' THEN 1 END) as total_users,
        SUM(CASE WHEN acc_status = 'Active' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN acc_status = 'Inactive' THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN acc_status = 'Pending' THEN 1 ELSE 0 END) as pending_users,
        SUM(CASE WHEN acc_status = 'Deleted' THEN 1 ELSE 0 END) as deleted_users,
        SUM(CASE WHEN DATE(created_at) = CURDATE() AND acc_status != 'Deleted' THEN 1 ELSE 0 END) as new_today,
        SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND acc_status != 'Deleted' THEN 1 ELSE 0 END) as new_this_week
    FROM account
");
$user_stats_stmt->execute();
$user_stats = $user_stats_stmt->get_result()->fetch_assoc();
$user_stats_stmt->close();

$role_stats_stmt = $conn->prepare("
    SELECT 
        r.role_name,
        COUNT(*) as count,
        SUM(CASE WHEN a.acc_status = 'Active' THEN 1 ELSE 0 END) as active_count
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE a.acc_status != 'Deleted'
    GROUP BY r.role_name, r.id
    ORDER BY count DESC
");
$role_stats_stmt->execute();
$role_stats = $role_stats_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$role_stats_stmt->close();

$activity_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT acc_id) as active_users_today
    FROM audit_log
    WHERE DATE(log_date) = CURDATE()
");
$activity_stats_stmt->execute();
$activity_stats = $activity_stats_stmt->get_result()->fetch_assoc();
$activity_stats_stmt->close();
?>

<!-- Simplified Statistics Dashboard -->
<div class="row mb-3 g-2">
    <div class="col-lg-3 col-md-6">
        <div class="card border h-100" style="border-left: 3px solid #800000 !important;">
            <div class="card-body text-center p-3">
                <div class="mb-2">
                    <i class="bi bi-people text-maroon fs-3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= number_format($user_stats['total_users']) ?></h4>
                <p class="text-muted mb-0 small">Total Users</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card border h-100" style="border-left: 3px solid #800000 !important;">
            <div class="card-body text-center p-3">
                <div class="mb-2">
                    <i class="bi bi-check-circle text-maroon fs-3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= number_format($user_stats['active_users']) ?></h4>
                <p class="text-muted mb-0 small">Active Users</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card border h-100" style="border-left: 3px solid #800000 !important;">
            <div class="card-body text-center p-3">
                <div class="mb-2">
                    <i class="bi bi-pause-circle text-maroon fs-3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= number_format($user_stats['inactive_users']) ?></h4>
                <p class="text-muted mb-0 small">Inactive Users</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card border h-100" style="border-left: 3px solid #800000 !important;">
            <div class="card-body text-center p-3">
                <div class="mb-2">
                    <i class="bi bi-shield-check text-maroon fs-3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= number_format($role_stats[0]['count'] ?? 0) ?></h4>
                <p class="text-muted mb-0 small">Admin Support</p>
            </div>
        </div>
    </div>
</div>

<!-- Simplified User Management Panel -->
<div class="card border mb-3">
    <div class="card-header bg-white border-bottom py-2">
        <div class="d-flex align-items-center">
            <div class="me-2">
                <i class="bi bi-shield-check text-maroon"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold text-dark">User Management</h6>
                <small class="text-muted">Search and manage user accounts</small>
            </div>
        </div>
    </div>
    <div class="card-body p-3">
        <!-- Simplified Search Section -->
        <div class="row g-2">
            <div class="col-12 mb-2">
                <h6 class="fw-bold text-dark mb-2 small">Search &amp; filter</h6>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small mb-1">Search Users</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="searchUsers" placeholder="Search by name, username, or email..." onkeyup="applyFilters()">
                    <button class="btn btn-outline-secondary border-start-0" onclick="clearSearch()" type="button">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold text-dark small mb-1">DEPARTMENT</label>
                <select class="form-select" id="deptFilter" onchange="applyFilters()">
                    <option value="">All Departments</option>
                    <?php
                    // Reset the result set to fetch departments
                    $dept_stmt_filter = $conn->prepare("SELECT DISTINCT dept_name FROM department WHERE dept_name IS NOT NULL AND dept_name != '' ORDER BY dept_name");
                    $dept_stmt_filter->execute();
                    $deptResult_filter = $dept_stmt_filter->get_result();
                    while ($dept = $deptResult_filter->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($dept['dept_name']) ?>"><?= htmlspecialchars($dept['dept_name']) ?></option>
                    <?php 
                    endwhile;
                    $dept_stmt_filter->close();
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small mb-1">ROLE</label>
                <select class="form-select" id="roleFilter" onchange="applyFilters()">
                    <option value="">All Roles</option>
                    <option value="Admin support">Admin Support</option>
                    <option value="Admin">Administrator</option>
                    <option value="Moderator">Moderator</option>
                    <option value="Instructor">Instructor</option>
                    <option value="User">Standard User</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small mb-1">STATUS</label>
                <select class="form-select" id="statusFilter" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Pending">Pending</option>
                    <option value="Deleted">Archived</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Success Messages -->
<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>User added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['status_updated']) && $_GET['status_updated'] == 'success'): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i>User status updated successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['role_changed']) && $_GET['role_changed'] == 'success'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-shield-check me-2"></i>User role changed successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php 
// Removed automatic SweetAlert on page load - user requested removal
// if (isset($_GET['user_updated']) && $_GET['user_updated'] == 'success'): ?>
<!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Success!',
            text: 'User account updated successfully',
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#8B0000',
            timer: 2500,
            timerProgressBar: true,
            showConfirmButton: true,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                content: 'swal2-content-custom'
            }
        }).then((result) => {
            // Remove the success parameter from URL to prevent re-triggering
            if (window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]user_updated=success/, ''));
            }
        });
    });
</script>
<style>
    .swal2-popup-custom {
        border-radius: 15px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
    }
    .swal2-title-custom {
        color: #8B0000 !important;
        font-weight: 600 !important;
    }
    .swal2-content-custom {
        color: #333 !important;
    }
</style> -->
<?php // endif; ?>

<?php if (isset($_GET['bulk_action']) && $_GET['bulk_action'] == 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>Bulk action completed successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Professional User Account Management System -->
<div class="card border mb-3">
    <div class="card-header bg-maroon text-white border-0 py-2">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="me-2">
                    <i class="bi bi-person-badge text-white"></i>
                </div>
                <div>
                    <h6 class="mb-0 text-white fw-bold">User Account Registry</h6>
                    <small class="text-white-50">Administrative interface for user account management and oversight</small>
                </div>
            </div>
            <div>
                <button class="btn btn-light btn-sm" onclick="clearSelection()">
                    CLEAR
                </button>
            </div>
        </div>
    </div>


    <div class="card-body p-0">
        <style>
            #usersTable {
                border-collapse: separate;
                border-spacing: 0;
            }
            #usersTable thead th {
                background: #f8f9fa;
                border: none;
                border-bottom: 1px solid #dee2e6;
                color: #495057;
                font-weight: 600;
                padding: 0.75rem;
                position: sticky;
                top: 0;
                z-index: 10;
                font-size: 0.875rem;
            }
            #usersTable tbody tr {
                border-bottom: 1px solid #e9ecef;
                transition: background-color 0.15s ease;
            }
            #usersTable tbody tr:hover {
                background-color: #f8f9fa;
            }
            #usersTable tbody td {
                border: none;
                padding: 0.75rem;
                vertical-align: middle;
                font-size: 0.875rem;
            }
            .user-avatar {
                background: #800000;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
                color: white;
                font-weight: 600;
                font-size: 0.85rem;
                border: 1px solid #dee2e6;
            }
            .user-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
            }
            
            /* Ensure perfect circular display */
            .user-avatar, .avatar-container {
                aspect-ratio: 1;
                min-width: 45px;
                min-height: 45px;
            }
            
            .user-avatar img, .avatar-container img {
                aspect-ratio: 1;
                object-position: center;
            }
            .avatar-container {
                background: #800000;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
                color: white;
                font-weight: 600;
                font-size: 0.85rem;
                border: 1px solid #dee2e6;
            }
            .avatar-container img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
            }
            .badge {
                font-size: 0.75rem;
                font-weight: 500;
                border-radius: 4px;
            }
            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
            }
            .dropdown-menu {
                border-radius: 4px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.1);
                border: 1px solid #dee2e6;
            }
        </style>
        <div class="table-responsive user-mgmt-table-wrap">
            <table class="table table-hover mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th width="50" class="text-center" style="border: none; border-bottom: 1px solid #dee2e6;">
                            <div class="form-check">
                                <input type="checkbox" id="selectAllUsers" class="form-check-input" onchange="toggleAllUsers(this)">
                            </div>
                        </th>
                        <th class="fw-semibold sortable" onclick="sortTable('name')" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            USER INFORMATION
                        </th>
                        <th class="fw-semibold sortable" onclick="sortTable('role')" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            ROLE
                        </th>
                        <th class="fw-semibold sortable" onclick="sortTable('department')" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            DEPARTMENT
                        </th>
                        <th class="fw-semibold sortable" onclick="sortTable('status')" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            STATUS
                        </th>
                        <th class="fw-semibold sortable" onclick="sortTable('activity')" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            ACTIVITY
                        </th>
                        <th class="fw-semibold sortable" onclick="sortTable('created')" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            JOINED
                        </th>
                        <th class="fw-semibold text-center" style="border: none; border-bottom: 1px solid #dee2e6; color: #495057;">
                            ACTIONS
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get all users with enhanced data including profile pictures and workload info
                    $allUsersQuery = "
                        SELECT 
                            a.acc_id, a.fname, a.lname, a.minitial, a.acc_user, a.acc_email, a.acc_status, a.created_at, a.profile_picture,
                            r.role_name,
                            d.dept_name,
                            c.college_name,
                            i.rank, i.designation,
                            COALESCE(i.administration_hours, 0) AS administration_hours,
                            COALESCE(i.instruction_hours, 0) AS instruction_hours,
                            COALESCE(i.research_hours, 0) AS research_hours,
                            COALESCE(i.extension_hours, 0) AS extension_hours,
                            COALESCE(i.instructional_functions_hours, 0) AS instructional_functions_hours,
                            COALESCE(i.consultation_hours, 0) AS consultation_hours,
                            (SELECT COUNT(*) FROM audit_log al WHERE al.acc_id = a.acc_id) as total_actions,
                            (SELECT MAX(log_date) FROM audit_log al WHERE al.acc_id = a.acc_id) as last_activity
                        FROM account a
                        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        LEFT JOIN department d ON a.dept_id = d.dept_id
                        LEFT JOIN college c ON d.college_id = c.college_id
                        LEFT JOIN instructor i ON a.acc_user = i.inst_user
                        WHERE a.acc_status != 'Deleted'
                        ORDER BY a.acc_id DESC
                        LIMIT 50
                    ";
                    $allUsers_stmt = $conn->prepare($allUsersQuery);
                    $allUsers_stmt->execute();
                    $allUsersResult = $allUsers_stmt->get_result();
                    while ($user = $allUsersResult->fetch_assoc()): 
                    ?>
                        <tr class="user-row" data-user-id="<?= $user['acc_id'] ?>" data-role="<?= $user['role_name'] ?>" data-status="<?= $user['acc_status'] ?>" data-dept="<?= $user['dept_name'] ?>" data-name="<?= strtolower($user['fname'] . ' ' . $user['lname']) ?>" data-email="<?= strtolower($user['acc_email']) ?>" data-rank="<?= htmlspecialchars($user['rank'] ?? '') ?>" data-designation="<?= htmlspecialchars($user['designation'] ?? '') ?>" data-administration-hours="<?= $user['administration_hours'] ?? 0 ?>" data-instruction-hours="<?= $user['instruction_hours'] ?? 0 ?>" data-research-hours="<?= $user['research_hours'] ?? 0 ?>" data-extension-hours="<?= $user['extension_hours'] ?? 0 ?>" data-instructional-functions-hours="<?= $user['instructional_functions_hours'] ?? 0 ?>" data-consultation-hours="<?= $user['consultation_hours'] ?? 0 ?>" style="border-bottom: 1px solid #e9ecef;">
                            <td class="text-center" style="border: none;">
                                <div class="form-check">
                                    <input type="checkbox" name="selected_users[]" value="<?= $user['acc_id'] ?>" class="form-check-input user-checkbox">
                                </div>
                            </td>
                            <td style="border: none;">
                                <div class="d-flex align-items-center">
                                    <?php
                                    // Enhanced profile picture handling with online status
                                    $profile_picture = $user['profile_picture'] ?? null;
                                    $has_profile_picture = false;
                                    $avatar_content = '';
                                    $avatar_class = 'user-avatar me-3';
                                    
                                    // Debug: Uncomment the line below to see profile picture data
                                    // echo "<!-- Debug: User {$user['acc_id']} profile_picture: " . ($profile_picture ?? 'NULL') . " -->";
                                    
                                    // Add online status class (you can implement actual online detection)
                                    $online_status = 'online'; // This could be determined by last activity
                                    $avatar_class .= ' ' . $online_status;
                                    
                                    // Check if profile picture exists and is accessible
                                    if ($profile_picture && !empty($profile_picture)) {
                                        $profile_path = '/' . $profile_picture;
                                        $has_profile_picture = true;
                                        $avatar_class = 'user-avatar me-3 ' . $online_status;
                                        $avatar_content = '<img src="' . htmlspecialchars($profile_path) . '" 
                                                           alt="' . htmlspecialchars($user['fname'] . ' ' . $user['lname']) . '" 
                                                           class="rounded-circle" 
                                                           style="width: 100%; height: 100%; object-fit: cover;"
                                                           onerror="if(this.nextElementSibling){this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';}">';
                                    }
                                    
                                    // Fallback to initials if no profile picture
                                    if (!$has_profile_picture) {
                                        $avatar_class = 'user-avatar me-3 no-image enhanced-avatar text-white rounded-circle d-flex align-items-center justify-content-center';
                                        $avatar_content = strtoupper(substr($user['fname'], 0, 1));
                                    }
                                    ?>
                                    <div class="<?= $avatar_class ?>" style="width: 40px; height: 40px; overflow: hidden; position: relative; background: #800000; border: 1px solid #dee2e6;">
                                        <?= $avatar_content ?>
                                        <?php if (!$has_profile_picture): ?>
                                        <div class="fallback-initials d-flex align-items-center justify-content-center" style="width: 100%; height: 100%; display: none;">
                                            <?= strtoupper(substr($user['fname'], 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold text-dark mb-1" style="font-size: 0.95rem;"><?= htmlspecialchars($user['fname'] . " " . $user['lname']) ?></div>
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="bi bi-person me-1" style="color: #6c757d; font-size: 0.8rem;"></i>
                                            <small class="text-muted" style="font-size: 0.8rem;">@<?= htmlspecialchars($user['acc_user']) ?></small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-envelope me-1" style="color: #6c757d; font-size: 0.8rem;"></i>
                                            <small class="text-muted text-truncate" style="max-width: 180px; font-size: 0.8rem;"><?= htmlspecialchars($user['acc_email']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="border: none;">
                                <?php
                                // Unified badge colors - using maroon theme and neutral grays
                                // All badges use white text for visibility on dark backgrounds
                                $role_class = match($user['role_name']) {
                                    'Admin support' => 'bg-maroon text-white',
                                    'Admin' => 'bg-secondary text-white',
                                    'Moderator' => 'bg-secondary text-white',
                                    'User' => 'bg-secondary text-white',
                                    default => 'bg-secondary text-white'
                                };
                                $role_icon = match($user['role_name']) {
                                    'Admin support' => 'bi-shield-check',
                                    'Admin' => 'bi-person-gear',
                                    'Moderator' => 'bi-person-badge',
                                    'User' => 'bi-person',
                                    default => 'bi-person'
                                };
                                ?>
                                <div class="d-flex flex-column">
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                    <span class="badge <?= $role_class ?> px-2 py-1 d-inline-flex align-items-center" style="font-size: 0.75rem; width: fit-content; color: #ffffff !important; background-color: <?= $user['role_name'] === 'Admin support' ? '#800000' : '#6c757d' ?> !important;">
                                        <i class="bi <?= $role_icon ?> me-1" style="font-size: 0.7rem; color: #ffffff !important;"></i><?= htmlspecialchars($user['role_name']) ?>
                                    </span>
                                        <?php if ($user['role_name'] !== 'Admin support'): ?>
                                        <button class="btn btn-xs btn-outline-success p-0" onclick="promoteRole(<?= $user['acc_id'] ?>, '<?= htmlspecialchars(addslashes($user['fname'] . ' ' . $user['lname'])) ?>')" title="Promote Role" style="width: 20px; height: 20px; font-size: 10px; padding: 0; line-height: 1;">
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($user['role_name'] !== 'User'): ?>
                                        <button class="btn btn-xs btn-outline-warning p-0" onclick="demoteRole(<?= $user['acc_id'] ?>, '<?= htmlspecialchars(addslashes($user['fname'] . ' ' . $user['lname'])) ?>')" title="Demote Role" style="width: 20px; height: 20px; font-size: 10px; padding: 0; line-height: 1;">
                                            <i class="bi bi-arrow-down"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        <i class="bi bi-key me-1"></i><?= $user['role_name'] === 'Admin support' ? 'Full Access' : 'Limited' ?>
                                    </small>
                                </div>
                            </td>
                            <td style="border: none;">
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold text-dark d-flex align-items-center" style="font-size: 0.9rem;">
                                        <i class="bi bi-building me-1" style="color: #800000; font-size: 0.8rem;"></i>
                                        <?= htmlspecialchars($user['dept_name'] ?? 'N/A') ?>
                                    </div>
                                    <small class="text-muted d-flex align-items-center mt-1" style="font-size: 0.7rem;">
                                        <i class="bi bi-mortarboard me-1"></i>
                                        <?= htmlspecialchars($user['college_name'] ?? 'No College') ?>
                                    </small>
                                </div>
                            </td>
                            <td style="border: none;">
                                <?php 
                                // Unified status badge colors - using maroon for active, gray for others
                                $status_class = match($user['acc_status']) {
                                    'Active' => 'bg-maroon text-white',
                                    'Inactive' => 'bg-secondary text-white',
                                    'Pending' => 'bg-secondary text-white',
                                    'Deleted' => 'bg-secondary text-white',
                                    default => 'bg-secondary text-white'
                                };
                                $status_icon = match($user['acc_status']) {
                                    'Active' => 'bi-check-circle-fill',
                                    'Inactive' => 'bi-pause-circle-fill',
                                    'Pending' => 'bi-clock-fill',
                                    'Deleted' => 'bi-trash-fill',
                                    default => 'bi-question-circle-fill'
                                };
                                ?>
                                <div class="d-flex flex-column">
                                    <span class="badge <?= $status_class ?> px-2 py-1 d-inline-flex align-items-center" style="font-size: 0.75rem; width: fit-content; color: #ffffff !important;">
                                        <i class="bi <?= $status_icon ?> me-1" style="font-size: 0.7rem; color: #ffffff !important;"></i><?= $user['acc_status'] ?>
                                    </span>
                                    <small class="text-muted mt-1" style="font-size: 0.7rem;">
                                        <i class="bi bi-circle-fill me-1" style="color: <?= $user['acc_status'] === 'Active' ? '#28a745' : '#6c757d' ?>;"></i>
                                        <?= $user['acc_status'] === 'Active' ? 'Online' : 'Offline' ?>
                                    </small>
                                </div>
                            </td>
                            <td style="border: none;">
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold text-dark d-flex align-items-center" style="font-size: 0.9rem;">
                                        <i class="bi bi-activity me-1" style="color: #17a2b8; font-size: 0.8rem;"></i>
                                        <span><?= number_format($user['total_actions']) ?></span>
                                        <?php if ($user['total_actions'] > 10): ?>
                                            <span class="badge bg-maroon text-white ms-1" style="font-size: 0.6rem; color: #ffffff !important;">Active</span>
                                        <?php elseif ($user['total_actions'] > 0): ?>
                                            <span class="badge bg-secondary text-white ms-1" style="font-size: 0.6rem; color: #ffffff !important;">Moderate</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white ms-1" style="font-size: 0.6rem; color: #ffffff !important;">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted d-flex align-items-center mt-1" style="font-size: 0.7rem;">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= $user['last_activity'] ? date('M j, g:i A', strtotime($user['last_activity'])) : 'Never' ?>
                                    </small>
                                </div>
                            </td>
                            <td style="border: none;">
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold text-dark d-flex align-items-center" style="font-size: 0.9rem;">
                                        <i class="bi bi-calendar-event me-1" style="color: #800000; font-size: 0.8rem;"></i>
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                    </div>
                                    <small class="text-muted d-flex align-items-center mt-1" style="font-size: 0.7rem;">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= date('g:i A', strtotime($user['created_at'])) ?>
                                    </small>
                                </div>
                            </td>
                            <td class="text-center" style="border: none;">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Primary Actions -->
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewUserDetails(<?= $user['acc_id'] ?>)" title="View Details" data-bs-toggle="tooltip" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                        <i class="bi bi-eye" style="font-size: 0.8rem;"></i>
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="editUser(<?= $user['acc_id'] ?>)" title="Edit User" data-bs-toggle="tooltip" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                        <i class="bi bi-pencil" style="font-size: 0.8rem;"></i>
                                    </button>
                                    
                                    <!-- Actions Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" title="More Actions" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                            <i class="bi bi-three-dots" style="font-size: 0.8rem;"></i>
                                        </button>
                                        <ul class="dropdown-menu border dropdown-menu-end" style="font-size: 0.8rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                                            <li><h6 class="dropdown-header" style="font-size: 0.7rem;">Status Actions</h6></li>
                                            <li><a class="dropdown-item py-2" href="#" onclick="changeUserStatus(<?= $user['acc_id'] ?>, 'Active')" style="font-size: 0.8rem;">
                                                <i class="bi bi-check-circle me-2 text-success"></i>Activate
                                            </a></li>
                                            <li><a class="dropdown-item py-2" href="#" onclick="changeUserStatus(<?= $user['acc_id'] ?>, 'Inactive')" style="font-size: 0.8rem;">
                                                <i class="bi bi-pause-circle me-2 text-warning"></i>Deactivate
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header" style="font-size: 0.7rem;">Management</h6></li>
                                            <li><a class="dropdown-item py-2" href="#" onclick="changeUserRole(<?= $user['acc_id'] ?>)" style="font-size: 0.8rem;">
                                                <i class="bi bi-shield-check me-2 text-info"></i>Change Role
                                            </a></li>
                                            <li><a class="dropdown-item py-2" href="#" onclick="resetUserPassword(<?= $user['acc_id'] ?>)" style="font-size: 0.8rem;">
                                                <i class="bi bi-key me-2 text-warning"></i>Reset Password
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item py-2 text-danger" href="#" 
                                                   onclick="deleteUser(<?= $user['acc_id'] ?>, '<?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>')" style="font-size: 0.8rem;">
                                                <i class="bi bi-trash me-2"></i>Delete User
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($allUsersResult->num_rows == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="empty-state">
                                <i class="bi bi-people text-muted fs-1 d-block mb-3"></i>
                                <h6 class="text-muted">No users found</h6>
                                <p class="text-muted">Add new users to get started with the system.</p>
                                    <button class="btn btn-maroon mt-3" onclick="showAddUserModal()">
                                        <i class="bi bi-person-plus me-2"></i>Add First User
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Professional Table Footer -->
        <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center py-3">
            <div class="d-flex align-items-center">
                <span class="text-muted me-3" id="rowCount">
                    <?= $allUsersResult->num_rows ?> registered accounts
                </span>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Click column headers to sort data
                </small>
            </div>
            <div class="d-flex align-items-center">
                <small class="text-muted">
                    <i class="bi bi-shield-check me-1"></i>
                    Administrative controls integrated below
                </small>
            </div>
        </div>
    </div>

    <!-- Pagination for Admin Support -->
    <div class="card-footer bg-white border-0">
        <nav aria-label="Admin support pages">
            <ul class="pagination justify-content-center mb-0">
                <?php
                // Prev
                $qsBase = "search=" . urlencode($search) . "&page_admin={$page_admin}";
                if ($page_support > 1):
                ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= $qsBase ?>&page_support=<?= $page_support - 1 ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages_support; $i++): ?>
                    <li class="page-item <?= ($i == $page_support) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= $qsBase ?>&page_support=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page_support < $total_pages_support): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= $qsBase ?>&page_support=<?= $page_support + 1 ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
</div>

    <!-- Administrative Actions - Integrated into User Account Registry -->
    <div class="card-footer bg-light border-top d-flex flex-wrap justify-content-center gap-3 py-3">
        <!-- Bulk Account Operations -->
        <button class="btn btn-outline-success btn-sm" onclick="bulkAction('activate')">
            <i class="bi bi-check-circle me-1"></i>Activate Accounts
        </button>
        <button class="btn btn-outline-warning btn-sm" onclick="bulkAction('deactivate')">
            <i class="bi bi-pause-circle me-1"></i>Deactivate Accounts
    </button>
        <button class="btn btn-outline-danger btn-sm" onclick="bulkAction('delete')">
            <i class="bi bi-trash me-1"></i>Archive Accounts
        </button>
    </div>
    </div>

    <!-- Archived Accounts Table -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border">
                <div class="card-header bg-maroon text-white">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-archive fs-5 me-2"></i>
                        <div>
                            <h5 class="mb-1 fw-bold text-white">Archived Accounts</h5>
                            <small class="text-white-50">Deleted and archived user accounts</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Get archived users (status = 'Deleted')
                    $archivedQuery = "
                        SELECT 
                            a.acc_id, a.fname, a.lname, a.minitial, a.acc_user, a.acc_email, a.acc_status, a.created_at, a.profile_picture,
                            r.role_name,
                            d.dept_name,
                            c.college_name
                        FROM account a
                        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        LEFT JOIN department d ON a.dept_id = d.dept_id
                        LEFT JOIN college c ON d.college_id = c.college_id
                        WHERE a.acc_status = 'Deleted'
                        ORDER BY a.created_at DESC
                    ";
                    
                    $archived_stmt = $conn->prepare($archivedQuery);
                    $archived_stmt->execute();
                    $archivedResult = $archived_stmt->get_result();
                    $archivedUsers = [];
                    if ($archivedResult && $archivedResult->num_rows > 0) {
                        while ($user = $archivedResult->fetch_assoc()) {
                            $archivedUsers[] = $user;
                        }
                    }
                    $archived_stmt->close();
                    ?>
                    
                    <?php if (empty($archivedUsers)): ?>
                        <!-- Empty State -->
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-archive text-muted" style="font-size: 3rem;"></i>
                            </div>
                            <h6 class="text-muted mb-2">No Archived Accounts</h6>
                            <p class="text-muted mb-0">All user accounts are currently active.</p>
                        </div>
                    <?php else: ?>
                        <!-- Archived Users Table -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="fw-bold">Account Information</th>
                                        <th class="fw-bold">Access Level</th>
                                        <th class="fw-bold">Organizational Unit</th>
                                        <th class="fw-bold">Registration Date</th>
                                        <th class="fw-bold text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archivedUsers as $user): ?>
                                    <tr class="user-row" data-user-id="<?= $user['acc_id'] ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $has_profile_picture = false;
                                                $avatar_class = 'avatar-container';
                                                $avatar_content = '';
                                                
                                                if (!empty($user['profile_picture'])) {
                                                    $profile_picture = $user['profile_picture'];
                                                    $profile_path = '/' . $profile_picture;
                                                    $has_profile_picture = true;
                                                    $avatar_class = 'avatar-container';
                                                    $avatar_content = '<img src="' . htmlspecialchars($profile_path) . '" 
                                                                       alt="' . htmlspecialchars($user['fname'] . ' ' . $user['lname']) . '" 
                                                                       class="rounded-circle" 
                                                                       style="width: 100%; height: 100%; object-fit: cover;"
                                                                       onerror="if(this.nextElementSibling){this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';}">';
                                                }
                                                
                                                if (!$has_profile_picture) {
                                                    $avatar_class = 'avatar-container no-image enhanced-avatar-archived text-white rounded-circle d-flex align-items-center justify-content-center';
                                                    $avatar_content = strtoupper(substr($user['fname'], 0, 1));
                                                }
                                                ?>
                                                <div class="<?= $avatar_class ?>" style="width: 45px; height: 45px; overflow: hidden; position: relative; border: 1px solid #dee2e6;">
                                                    <?= $avatar_content ?>
                                                    <?php if (!$has_profile_picture): ?>
                                                    <div class="fallback-initials d-flex align-items-center justify-content-center" style="width: 100%; height: 100%; display: none;">
                                                        <?= strtoupper(substr($user['fname'], 0, 1)) ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <div class="fw-bold text-muted me-2"><?= htmlspecialchars($user['fname'] . " " . $user['lname']) ?></div>
                                                        <span class="badge bg-secondary text-white border">ID: <?= $user['acc_id'] ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center mb-1">
                                                        <i class="bi bi-person me-1 text-muted"></i>
                                                        <small class="text-muted">@<?= htmlspecialchars($user['acc_user']) ?></small>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-envelope me-1 text-muted"></i>
                                                        <small class="text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($user['acc_email']) ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-white">
                                                <i class="bi bi-shield-x me-1"></i><?= htmlspecialchars($user['role_name'] ?? 'No Role') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-muted">
                                                <div class="fw-semibold"><?= htmlspecialchars($user['dept_name'] ?? 'No Department') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($user['college_name'] ?? 'No College') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button class="btn btn-outline-success btn-sm action-btn" onclick="restoreUser(<?= $user['acc_id'] ?>, '<?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>')" title="Restore User" data-bs-toggle="tooltip">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm action-btn" onclick="permanentlyDeleteUser(<?= $user['acc_id'] ?>, '<?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>')" title="Permanently Delete" data-bs-toggle="tooltip">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- ✅ Shared Modals (only once at bottom) -->
<?php include '../../admin_support/partials/edit_modals.php'; ?>

<!-- JS to populate modals -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    // Let Bootstrap handle modal behavior naturally - no custom interference needed

    // Admin Support
    document.querySelectorAll(".editSupportBtn").forEach(btn => {
        btn.addEventListener("click", () => {
            if (document.getElementById("supportAccId")) {
                document.getElementById("supportAccId").value = btn.dataset.id || '';
                document.getElementById("supportFname").value = btn.dataset.fname || '';
                document.getElementById("supportLname").value = btn.dataset.lname || '';
                document.getElementById("supportMinitial").value = btn.dataset.minitial || '';
                document.getElementById("supportUser").value = btn.dataset.user || '';
                document.getElementById("supportEmail").value = btn.dataset.email || '';
                // If role element expects id, you'll need to convert role name to id on server; this fills text.
                if (document.getElementById("supportRole")) document.getElementById("supportRole").value = btn.dataset.role || '';
                if (document.getElementById("supportStatus")) document.getElementById("supportStatus").value = btn.dataset.status || '';
            }
        });
    });

    // Admin
    document.querySelectorAll(".editAdminBtn").forEach(btn => {
        btn.addEventListener("click", () => {
            if (document.getElementById("adminAccId")) {
                document.getElementById("adminAccId").value = btn.dataset.id || '';
                document.getElementById("adminFname").value = btn.dataset.fname || '';
                document.getElementById("adminLname").value = btn.dataset.lname || '';
                document.getElementById("adminMinitial").value = btn.dataset.minitial || '';
                document.getElementById("adminUser").value = btn.dataset.user || '';
                document.getElementById("adminEmail").value = btn.dataset.email || '';
                if (document.getElementById("adminRole")) document.getElementById("adminRole").value = btn.dataset.role || '';
                if (document.getElementById("adminStatus")) document.getElementById("adminStatus").value = btn.dataset.status || '';
                if (document.getElementById("adminDept")) document.getElementById("adminDept").value = btn.dataset.dept || '';
            }
        });
    });

    // Delete handlers
    const deleteBtns = document.querySelectorAll('.deleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteUserName = document.getElementById('deleteUserName');
    let deleteUrl = '';

    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            if (deleteUserName) deleteUserName.textContent = userName;
            deleteUrl = `delete_user.php?id=${userId}`;
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (deleteUrl) window.location.href = deleteUrl;
        });
    }
});
</script>

<!-- Small styles kept in page for convenience -->
<style>
.bg-maroon { background-color: #800000 !important; }
.table-maroon { background-color: #a00000 !important; }
.btn-maroon { background-color: #800000; color: #fff; }
.perm-badge {
  font-size: 0.75em;
  vertical-align: middle;
}
</style>


<!-- Custom CSS & small helper scripts (kept as-is) -->
<style>
/* ... keep your CSS as before ... */
.bg-maroon { background-color: #800000 !important; }
.table-maroon { background-color: #a00000 !important; }
.user-mgmt-table-wrap table {
  table-layout: fixed;
  width: 100%;
  min-width: 500px;
}
.user-mgmt-table-wrap .table th,
.user-mgmt-table-wrap .table td {
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12px;
}
.user-mgmt-table-wrap .table tbody {
  display: block;
  max-height: 400px;
  min-height: 400px;
  overflow-y: auto;
}
.user-mgmt-table-wrap .table thead,
.user-mgmt-table-wrap .table tbody tr {
  display: table;
  width: 100%;
  table-layout: fixed;
}
.user-mgmt-table-wrap {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-x: contain;
}
.card + .card { margin-top:1.5rem; }

@media (max-width: 767.98px) {
  .user-mgmt-table-wrap .table tbody {
    max-height: 280px;
    min-height: 200px;
  }
  .user-mgmt-table-wrap table {
    min-width: 360px;
  }
  .user-mgmt-table-wrap .table th,
  .user-mgmt-table-wrap .table td {
    white-space: normal;
    word-break: break-word;
    vertical-align: top;
  }
  .user-mgmt-table-wrap .table td:last-child {
    white-space: nowrap;
  }
  .user-mgmt-table-wrap .table .form-check-input {
    width: 1.15rem;
    height: 1.15rem;
    margin-top: 0.2rem;
  }
  .user-mgmt-table-wrap small.text-truncate {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    max-width: 100% !important;
  }
}

@media (max-width:576px) {
  .user-mgmt-table-wrap .table tbody {
    max-height: 260px;
    min-height: 180px;
  }
  .user-mgmt-table-wrap .table th,
  .user-mgmt-table-wrap .table td {
    font-size: 0.85rem;
    padding: 0.4rem;
  }
  .user-mgmt-table-wrap .btn-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.4rem;
  }
}
</style>

<!-- Edit Permissions Modal (same large size as Add New Department) -->
<div class="modal fade" id="editPermissionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="permissions_backend/save_permissions.php" id="permissionsForm">
        <?= getCSRFTokenInput() ?>
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-shield-lock me-2"></i>
            Edit Permissions for <span id="permUserName"></span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="acc_id" id="permUserId">

          <!-- Loading -->
          <div id="permissionsLoading" class="text-center py-4" style="display: none;">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading permissions...</p>
          </div>

          <!-- Permissions list -->
          <div id="permissionsContent" style="display:none;">
            <div class="alert alert-info">
              <i class="bi bi-info-circle"></i>
              Individual user permissions override role-based permissions.
            </div>

            <div class="row">
              <?php
              $perm_stmt = $conn->prepare("SELECT id, permission_name FROM permissions ORDER BY permission_name");
              $perm_stmt->execute();
              $permResult = $perm_stmt->get_result();
              while ($perm = $permResult->fetch_assoc()):
                $display = ucwords(str_replace('_', ' ', $perm['permission_name']));
              ?>
                <div class="col-md-6 mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="permissions[]"
                           value="<?= $perm['id'] ?>"
                           id="perm<?= $perm['id'] ?>">
                    <label class="form-check-label" for="perm<?= $perm['id'] ?>">
                      <?= htmlspecialchars($display) ?>
                    </label>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
          <button type="submit" class="btn btn-maroon" id="savePermissionsBtn"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById('editPermissionsModal');
  const loading = document.getElementById('permissionsLoading');
  const content = document.getElementById('permissionsContent');
  const saveBtn = document.getElementById('savePermissionsBtn');

  modal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const userId = button.getAttribute('data-user-id');
    const userName = button.getAttribute('data-user-name');

    document.getElementById('permUserId').value = userId;
    document.getElementById('permUserName').textContent = userName;

    // reset state
    loading.style.display = 'block';
    content.style.display = 'none';
    saveBtn.disabled = true;
    modal.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);

    // fetch effective permissions
    fetch('permissions_backend/get_effective_permissions.php?acc_id=' + userId)
      .then(res => res.json())
      .then(effective => {
        // fetch user overrides
        fetch('permissions_backend/get_user_permissions.php?acc_id=' + userId)
          .then(res => res.json())
          .then(overrides => {
            loading.style.display = 'none';
            content.style.display = 'block';
            saveBtn.disabled = false;

            // Show checkboxes and badges
            modal.querySelectorAll('input[type=checkbox]').forEach(cb => {
              const pid = parseInt(cb.value);
              cb.checked = overrides.includes(pid);

              // Remove old badge
              let badge = cb.parentElement.querySelector('.perm-badge');
              if (badge) badge.remove();

              // Add badge for effective permission
              if (effective[pid]) {
                const span = document.createElement('span');
                span.className = 'perm-badge badge bg-maroon text-white ms-2';
                span.textContent = 'Granted';
                cb.parentElement.appendChild(span);
              } else {
                const span = document.createElement('span');
                span.className = 'perm-badge badge bg-secondary text-white ms-2';
                span.textContent = 'Not granted';
                cb.parentElement.appendChild(span);
              }
            });
          });
      })
      .catch(() => {
        loading.innerHTML = `<div class="alert alert-danger">Failed to load permissions.</div>`;
      });
  });
});
</script>



<!-- Add Admin Support Modal (same large size as Add New Department) -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="save_user.php" id="addUserForm" enctype="multipart/form-data" novalidate>
                <?= getCSRFTokenInput() ?>
                <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="addUserModalLabel">Add New Administrative Support</h5>
                            <small class="text-muted">Create a new administrative support account</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Progress Indicator -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-center">
                                <div class="progress-steps">
                                    <div class="step active" data-step="1">
                                        <div class="step-circle">1</div>
                                        <div class="step-label">Personal Info</div>
                                    </div>
                                    <div class="step-line"></div>
                                    <div class="step" data-step="2">
                                        <div class="step-circle">2</div>
                                        <div class="step-label">Account Details</div>
                                    </div>
                                    <div class="step-line"></div>
                                    <div class="step" data-step="3">
                                        <div class="step-circle">3</div>
                                        <div class="step-label">Review Info</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" data-step="1">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                    <i class="bi bi-person me-2"></i>Personal Information
                                </h6>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-person me-1"></i>First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" name="fname" id="fname" 
                                       placeholder="Enter first name" required autocomplete="given-name">
                                <div class="invalid-feedback">Please provide a valid first name.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-person me-1"></i>Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" name="lname" id="lname" 
                                       placeholder="Enter last name" required autocomplete="family-name">
                                <div class="invalid-feedback">Please provide a valid last name.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-person me-1"></i>Middle Initial
                                </label>
                                <input type="text" class="form-control form-control-lg" name="minitial" id="minitial" 
                                       placeholder="M" maxlength="1" style="text-transform: uppercase;">
                                <div class="form-text">Optional middle initial</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-person me-1"></i>Suffix
                                </label>
                                <input type="text" class="form-control form-control-lg" name="suffix" id="suffix" 
                                       placeholder="Jr, Sr, II, III, etc." maxlength="10">
                                <div class="form-text">Optional suffix (e.g., Jr, Sr, II, III, VII)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Account Details -->
                    <div class="form-step" data-step="2">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                    <i class="bi bi-shield-check me-2"></i>Account Details
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-at me-1"></i>Username <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" name="acc_user" id="acc_user" 
                                       placeholder="Enter username" required autocomplete="username">
                                <div class="invalid-feedback">Please provide a valid username.</div>
                                <div class="form-text">Username must be unique and 3-20 characters long</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control form-control-lg" name="acc_email" id="acc_email" 
                                       placeholder="user@evsu.edu.ph" required autocomplete="email">
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                                <div class="form-text">Use official EVSU email address</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-shield me-1"></i>Role <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-lg" name="role_id" id="role_id" required>
                                    <?php
                                    // Get only Admin role for dropdown
                                    $roleQuery = "SELECT id, role_name FROM roles WHERE role_name = 'Admin' LIMIT 1";
                                    $role_stmt_dropdown = $conn->prepare($roleQuery);
                                    $role_stmt_dropdown->execute();
                                    $roleResult = $role_stmt_dropdown->get_result();
                                    if ($roleResult && $roleResult->num_rows > 0) {
                                        $role = $roleResult->fetch_assoc();
                                        echo "<option value='{$role['id']}' selected>{$role['role_name']}</option>";
                                    }
                                    $role_stmt_dropdown->close();
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select a role.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">
                                    <i class="bi bi-book me-1"></i>Department
                                </label>
                                <select class="form-select form-select-lg" name="dept_id" id="dept_id">
                                    <option value="">Select Department</option>
                                    <!-- Options will be populated dynamically -->
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <div>
                                        <strong>Default Password:</strong> The system will automatically set a secure default password. 
                                        The user will be required to change it on first login.
                                    </div>
                                </div>
                                <input type="hidden" name="acc_pass" value="evsu-occ">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Review Info -->
                    <div class="form-step" data-step="3">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                    <i class="bi bi-check-circle me-2"></i>Review Information
                                </h6>
                            </div>
                            <div class="col-12">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <h6 class="fw-bold text-dark mb-2">Personal Information</h6>
                                                <p class="mb-1"><strong>Name:</strong> <span id="reviewName">-</span></p>
                                                <p class="mb-0"><strong>Email:</strong> <span id="reviewEmail">-</span></p>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="fw-bold text-dark mb-2">Account Details</h6>
                                                <p class="mb-1"><strong>Username:</strong> <span id="reviewUsername">-</span></p>
                                                <p class="mb-0"><strong>Role:</strong> <span id="reviewRole">-</span></p>
                                                <p class="mb-0"><strong>Department:</strong> <span id="reviewProgram">-</span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Role selection is now in the form above -->
                </div>
                <div class="modal-footer border-0 bg-light">
                    <div class="d-flex justify-content-between w-100">
                        <button type="button" class="btn btn-outline-secondary" id="prevStep" style="display: none;">
                            <i class="bi bi-arrow-left me-2"></i>Previous
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-maroon" id="nextStep">
                                Next <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                            <button type="submit" class="btn btn-maroon" name="add_user" id="submitBtn" style="display: none;">
                                <i class="bi bi-check-lg me-2"></i>Create Account
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Admin Modal (same large size as Add New Department) -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="save_user.php">
                <?= getCSRFTokenInput() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addAdminModalLabel"><i class="bi bi-person-plus me-2"></i>Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">First Name</label>
                            <input type="text" class="form-control" name="fname" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Last Name</label>
                            <input type="text" class="form-control" name="lname" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">M.I.</label>
                            <input type="text" class="form-control" name="minitial">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" class="form-control" value="evsu-occ" disabled>
                            <input type="hidden" name="acc_pass" value="evsu-occ">
                            <small class="form-text text-muted">Default password is set automatically.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" class="form-control" name="acc_user" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="acc_email" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Department</label>
                            <select class="form-select" name="dept_id" required>
                                <option value="" disabled selected>Select Department</option>
                                <?php 
                                $dept_stmt_add = $conn->prepare("SELECT dept_id, dept_name FROM department");
                                $dept_stmt_add->execute();
                                $deptResultAdd = $dept_stmt_add->get_result();
                                while ($dept = $deptResultAdd->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($dept['dept_id']) ?>"><?= htmlspecialchars($dept['dept_name']) ?></option>
                                <?php 
                                endwhile; 
                                $dept_stmt_add->close();
                                ?>
                            </select>
                        </div>

                        <!-- Fixed Role: Admin -->
                        <input type="hidden" name="role_id" value="<?= htmlspecialchars($adminRoleId) ?>">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                    <button type="submit" class="btn btn-maroon" name="add_user"><i class="bi bi-check-lg me-1"></i>Save Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal (compact; same header/footer style) -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content text-center">
      <div class="modal-header">
        <h5 class="modal-title">Success</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:2rem;"></i>
        <h5 class="mt-3">Action completed successfully!</h5>
      </div>
      <div class="modal-footer border-0 bg-light justify-content-center">
        <button type="button" class="btn btn-maroon" data-bs-dismiss="modal"><i class="bi bi-check-lg me-1"></i>Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Enhanced Edit User Modal - Full Screen -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="editUserModalLabel">Edit Account</h5>
                    <small class="text-muted">Update instructor account information</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="editUserForm" method="POST" enctype="multipart/form-data" onsubmit="return validateEditUser()">
                <div class="modal-body p-0">
                    <input type="hidden" name="edit_user_id" id="editUserId">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <!-- Progress Indicator -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-center">
                                <div class="progress-steps">
                                    <div class="step active" data-step="1">
                                        <div class="step-circle">1</div>
                                        <div class="step-label">Personal Info</div>
                                    </div>
                                    <div class="step-line"></div>
                                    <div class="step" data-step="2">
                                        <div class="step-circle">2</div>
                                        <div class="step-label">Account Details</div>
                                    </div>
                                    <div class="step-line"></div>
                                    <div class="step" data-step="3">
                                        <div class="step-circle">3</div>
                                        <div class="step-label">Workload Hours</div>
                                    </div>
                                    <div class="step-line"></div>
                                    <div class="step" data-step="4">
                                        <div class="step-circle">4</div>
                                        <div class="step-label">Review Info</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Personal Information -->
                    <div class="step-content" id="step1">
                        <div class="container-fluid p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                        <i class="bi bi-person me-2"></i>Personal Information
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <label for="editFirstName" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-person me-1"></i>First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="editFirstName" name="edit_first_name" placeholder="Enter first name" required autocomplete="given-name">
                                    <div class="invalid-feedback">Please provide a valid first name.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="editLastName" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-person me-1"></i>Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="editLastName" name="edit_last_name" placeholder="Enter last name" required autocomplete="family-name">
                                    <div class="invalid-feedback">Please provide a valid last name.</div>
                                </div>
                                <div class="col-md-2">
                                    <label for="editMiddleInitial" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-person me-1"></i>M.I.
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="editMiddleInitial" name="edit_middle_initial" placeholder="M" maxlength="1" style="text-transform: uppercase;">
                                    <div class="form-text">Optional</div>
                                </div>
                                <div class="col-md-2">
                                    <label for="editSuffix" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-person me-1"></i>Suffix
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="editSuffix" name="edit_suffix" placeholder="Jr, Sr, II, etc." maxlength="10">
                                    <div class="form-text">Optional</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Account Details -->
                    <div class="step-content d-none" id="step2">
                        <div class="container-fluid p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                        <i class="bi bi-shield-check me-2"></i>Account Details
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <label for="editUsername" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-at me-1"></i>Username <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="editUsername" name="edit_username" placeholder="Enter username" required autocomplete="username">
                                    <div class="invalid-feedback">Please provide a valid username.</div>
                                    <div class="form-text">Username must be unique and 3-20 characters long</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editEmail" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control form-control-lg" id="editEmail" name="edit_email" placeholder="user@evsu.edu.ph" required autocomplete="email">
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                    <div class="form-text">Use official EVSU email address</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editRole" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-shield me-1"></i>Role <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="editRole" name="edit_role" required>
                                        <option value="">Select Role</option>
                                        <?php
                                        $role_stmt_edit = $conn->prepare("SELECT id, role_name FROM roles ORDER BY role_name");
                                        $role_stmt_edit->execute();
                                        $roleResult = $role_stmt_edit->get_result();
                                        $role_stmt_edit->close();
                                        while ($role = $roleResult->fetch_assoc()):
                                        ?>
                                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a role.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editInstStatus" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-briefcase me-1"></i>Employment Status <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="editInstStatus" name="edit_inst_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Part-Time">Part-Time</option>
                                        <option value="Contractual">Contractual</option>
                                        <option value="Pending">Pending</option>
                                    </select>
                                    <div class="invalid-feedback">Please select employment status.</div>
                                    <div class="form-text">Select the employment status for this user</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editProgramId" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-book me-1"></i>Department
                                    </label>
                                    <select class="form-select form-select-lg" id="editProgramId" name="edit_program_id">
                                        <option value="">Select Department</option>
                                        <?php
                                        $programQuery = "SELECT program_id, program_name, program_code FROM program WHERE program_status = 'Active' ORDER BY program_name";
                                        $programResult = $conn->query($programQuery);
                                        if ($programResult && $programResult->num_rows > 0) {
                                            while ($program = $programResult->fetch_assoc()) {
                                                echo "<option value='{$program['program_id']}'>{$program['program_code']} - {$program['program_name']}</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                    <div class="form-text">Select the department for this user</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editRank" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-award me-1"></i>Academic Rank <span class="text-danger rank-asterisk">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="editRank" name="edit_rank" required>
                                        <option value="">Select Rank</option>
                                        <option value="None">None</option>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                    <div class="invalid-feedback">Please select academic rank.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editDesignation" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-briefcase-fill me-1"></i>Designation
                                    </label>
                                    <select class="form-select form-select-lg" id="editDesignation" name="edit_designation">
                                        <option value="None">None</option>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                    <div class="form-text">Select the designation for this user</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="editInstPhone" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-phone me-1"></i>Phone Number
                                    </label>
                                    <input type="tel" class="form-control form-control-lg" id="editInstPhone" name="edit_inst_phone" placeholder="+63 912 345 6789">
                                    <div class="form-text">Optional phone number</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Workload Hours -->
                    <div class="step-content d-none" id="step3">
                        <div class="container-fluid p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                        <i class="bi bi-clock me-2"></i>Workload Hours
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <label for="editAdministrationHours" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-building me-1"></i>Administrative Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="editAdministrationHours" name="edit_administration_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter administrative hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="editInstructionHours" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-book-half me-1"></i>Instruction Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="editInstructionHours" name="edit_instruction_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter instruction hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="editResearchHours" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-search me-1"></i>Research Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="editResearchHours" name="edit_research_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter research hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="editExtensionHours" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-people me-1"></i>Extension Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="editExtensionHours" name="edit_extension_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter extension hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="editInstructionalFunctionsHours" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-mortarboard me-1"></i>Instructional Functions Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="editInstructionalFunctionsHours" name="edit_instructional_functions_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter instructional functions hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="editConsultationHours" class="form-label fw-semibold text-dark">
                                        <i class="bi bi-chat-dots me-1"></i>Consultation Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="editConsultationHours" name="edit_consultation_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter consultation hours.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Review Info -->
                    <div class="step-content d-none" id="step4">
                        <div class="container-fluid p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                        <i class="bi bi-check-circle me-2"></i>Review Information
                                    </h6>
                                </div>
                                <div class="col-12">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <h6 class="fw-bold text-dark mb-2">Personal Information</h6>
                                                    <div id="reviewPersonalInfo">
                                                        <!-- Review content will be populated here -->
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6 class="fw-bold text-dark mb-2">Account Details</h6>
                                                    <div id="reviewAccountInfo">
                                                        <!-- Review content will be populated here -->
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6 class="fw-bold text-dark mb-2">Workload Hours</h6>
                                                    <div id="reviewWorkloadInfo">
                                                        <!-- Review content will be populated here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 bg-light">
                    <div class="d-flex justify-content-between w-100">
                        <button type="button" class="btn btn-outline-secondary" id="editPrevStep" style="display: none;">
                            <i class="bi bi-arrow-left me-2"></i>Previous
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-maroon" id="editNextStep">
                                Next <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                            <button type="submit" class="btn btn-maroon" id="editSaveUser" style="display: none;">
                                <i class="bi bi-check-lg me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User Details Modal - Full Screen -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="userDetailsModalLabel">User Details</h5>
                    <small class="text-muted">Complete user information and activity.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="userDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                        <p class="mt-3 text-muted fs-5">Loading user details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>Close
                </button>
                <button type="button" class="btn btn-maroon" id="editUserFromDetails">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </button>
                <button type="button" class="btn btn-outline-secondary" id="exportUserDetails">
                    <i class="bi bi-download me-2"></i>Export Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirmation script (defensive) -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const deleteBtns = document.querySelectorAll('.deleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteUserName = document.getElementById('deleteUserName');
    let deleteUrl = '';

    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            if (deleteUserName) deleteUserName.textContent = userName || '';
            deleteUrl = `delete_user.php?id=${encodeURIComponent(userId)}`;
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (deleteUrl) window.location.href = deleteUrl;
        });
    }
});
</script>

<!-- jQuery (required for DataTables and other plugins) -->
<script src="/assets/js/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JavaScript Bundle (must be loaded before custom scripts that use Bootstrap) -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

<!-- Enhanced JavaScript Functionality -->
<script>
// User Management Functions - Version 2.0
// Global functions must be defined outside DOMContentLoaded for onclick handlers

// Helper function to get Swal instance (no iframe-specific logic)
window.getSwal = function() {
    return typeof Swal !== 'undefined' ? Swal : null;
};

// No-op for component/tab context (iframe-specific code removed)
window.ensureSwalPosition = function() {};

// Helper function to show alerts using SweetAlert2 if available, otherwise fallback to native alert
// This must be defined before DOMContentLoaded to be available immediately
window.showAlert = function(message, type = 'info') {
    window.ensureSwalPosition();
    const swalInstance = window.getSwal();
    
    if (swalInstance) {
        swalInstance.fire({
            icon: type,
            title: type === 'error' ? 'Error' : type === 'success' ? 'Success' : type === 'warning' ? 'Warning' : 'Information',
            text: message,
            confirmButtonColor: '#800000',
            confirmButtonText: 'OK',
            allowOutsideClick: true,
            allowEscapeKey: true
        });
    } else {
        // Fallback to native alert if Swal not loaded yet
        try {
            alert(message);
        } catch (e) {
            console.error('Alert blocked:', e);
        }
    }
};

// Helper function to show confirm dialogs using SweetAlert2 if available, otherwise fallback to native confirm
window.showConfirm = function(message, title = 'Confirm') {
    return new Promise((resolve) => {
        window.ensureSwalPosition();
        const swalInstance = window.getSwal();
        
        if (swalInstance) {
            swalInstance.fire({
                title: title,
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#800000',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                allowOutsideClick: true,
                allowEscapeKey: true
            }).then((result) => {
                resolve(result.isConfirmed);
            });
        } else {
            // Fallback to native confirm if Swal not loaded yet
            try {
                resolve(confirm(message));
            } catch (e) {
                console.error('Confirm blocked:', e);
                resolve(false);
            }
        }
    });
};

document.addEventListener("DOMContentLoaded", function() {
    console.log('EVSU-OCC User Management System v3.0 initialized');
    // Modals use standard Bootstrap 5 - no custom backdrop/sidebar/cleanup logic (was causing blinking)

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize counter animations
    initializeCounterAnimations();

    // Multi-Step Form Functionality
    initializeMultiStepForm();
    
    // Initialize modern effects
    initializeModernEffects();

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    
    // Add smooth scrolling to table
    const table = document.getElementById('usersTable');
    if (table) {
        table.style.scrollBehavior = 'smooth';
    }
    
    // Initialize row count
    updateRowCount();
    
    // Add loading states to buttons
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const originalContent = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            this.disabled = true;
            
            setTimeout(() => {
                this.innerHTML = originalContent;
                this.disabled = false;
            }, 1000);
        });
    });
});

// Counter animation function
function initializeCounterAnimations() {
    const counters = document.querySelectorAll('.counter');
    
    const animateCounter = (counter) => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000; // 2 seconds
        const increment = target / (duration / 16); // 60fps
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            counter.textContent = Math.floor(current);
        }, 16);
    };
    
    // Intersection Observer for counter animation
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    });
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
}

// Modern effects initialization - disabled for stability
function initializeModernEffects() {
    // Card hover effects disabled to prevent modal interference
    // If you want hover effects on cards outside modals, uncomment below
    /*
    const cards = document.querySelectorAll('.ultra-modern-card:not(.modal *)');
    cards.forEach(card => {
        if (card.closest('.modal')) return;
        card.addEventListener('mouseenter', function() {
            if (!document.body.classList.contains('modal-open')) {
                this.style.transform = 'translateY(-5px)';
            }
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    */
    
    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.modern-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

// Multi-Step Form Functionality
function initializeMultiStepForm() {
    const form = document.getElementById('addUserForm');
    if (!form) return;

    let currentStep = 1;
    const totalSteps = 3;
    const nextBtn = document.getElementById('nextStep');
    const prevBtn = document.getElementById('prevStep');
    const submitBtn = document.getElementById('submitBtn');

    // Step navigation
    nextBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (validateCurrentStep()) {
            if (currentStep < totalSteps) {
                currentStep++;
                updateStepDisplay();
            }
        } else {
            // Validation failed - error messages are already shown by validateField
            // Optionally show a toast/alert if needed
        }
    });

    prevBtn?.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            updateStepDisplay();
        }
    });

    // Username generation
    document.getElementById('generateUsername')?.addEventListener('click', () => {
        const fname = document.getElementById('fname').value.trim();
        const lname = document.getElementById('lname').value.trim();
        const minitial = document.getElementById('minitial').value.trim();
        
        if (fname && lname) {
            let username = fname.toLowerCase() + lname.toLowerCase();
            if (minitial) {
                username += minitial.toLowerCase();
            }
            // Add random number to ensure uniqueness
            username += Math.floor(Math.random() * 1000);
            document.getElementById('acc_user').value = username;
        }
    });

    // Real-time validation
    const inputs = form.querySelectorAll('input[required], select[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearFieldError);
        input.addEventListener('change', clearFieldError);
    });

    // Form submission - Convert to AJAX to prevent page reload
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!validateAllSteps()) {
            return false;
        }
        
        // Disable submit button to prevent double submission
        const submitBtn = document.getElementById('submitBtn');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
        }
        
        // Create FormData from form
        const formData = new FormData(form);
        
        try {
            const response = await fetch('save_user.php', {
                method: 'POST',
                body: formData,
                redirect: 'follow' // Follow redirects
            });
            
            // Check response status
            if (response.ok || response.redirected) {
                // Success - close modal first
                const modalElement = document.getElementById('addUserModal');
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
                
                // Reset form
                form.reset();
                currentStep = 1;
                updateStepDisplay();
                
                // Show success notification and reload page to refresh user table
                // Use a simple approach: show a brief message then reload
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                successMsg.style.zIndex = '9999';
                successMsg.innerHTML = `
                    <strong>Success!</strong> Account created successfully. Verification email has been sent.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(successMsg);
                
                // Reload page after a short delay to show the message and refresh user table
                setTimeout(() => {
                    // Clear any modal state before reload
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    
                    // Remove any backdrops
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                    
                    // Reload current page to refresh the user table
                    window.location.reload();
                }, 1500);
            } else {
                // Error response
                const errorText = await response.text();
                console.error('Error response:', errorText);
                
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                errorMsg.style.zIndex = '9999';
                errorMsg.innerHTML = `
                    <strong>Error!</strong> Failed to create account. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(errorMsg);
                
                // Auto-dismiss error message after 5 seconds
                setTimeout(() => {
                    if (errorMsg.parentNode) {
                        errorMsg.remove();
                    }
                }, 5000);
                
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        } catch (error) {
            console.error('Form submission error:', error);
            
            // Show error message
            const errorMsg = document.createElement('div');
            errorMsg.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            errorMsg.style.zIndex = '9999';
            errorMsg.innerHTML = `
                <strong>Error!</strong> An error occurred while creating the account. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(errorMsg);
            
            // Auto-dismiss error message after 5 seconds
            setTimeout(() => {
                if (errorMsg.parentNode) {
                    errorMsg.remove();
                }
            }, 5000);
            
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });

    function updateStepDisplay() {
        // Update progress steps
        document.querySelectorAll('.step').forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNum < currentStep) {
                step.classList.add('completed');
            } else if (stepNum === currentStep) {
                step.classList.add('active');
            }
        });

        // Update form steps - hide all, show only current
        document.querySelectorAll('.form-step').forEach((step, index) => {
            step.classList.remove('active');
            if (index + 1 === currentStep) {
                step.classList.add('active');
                step.style.display = 'block';
            } else {
                step.style.display = 'none';
            }
        });

        // Update buttons
        prevBtn.style.display = currentStep > 1 ? 'block' : 'none';
        nextBtn.style.display = currentStep < totalSteps ? 'block' : 'none';
        submitBtn.style.display = currentStep === totalSteps ? 'block' : 'none';

        // Update review information
        if (currentStep === 3) {
            updateReviewInfo();
        }
    }

    function validateCurrentStep() {
        const currentStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
        if (!currentStepElement) {
            // If step element not found, allow navigation (shouldn't happen, but be safe)
            return true;
        }
        
        // Check both input and select elements with required attribute
        const requiredInputs = currentStepElement.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        let firstInvalidField = null;

        // If no required fields, step is valid
        if (requiredInputs.length === 0) {
            return true;
        }

        requiredInputs.forEach(input => {
            if (!validateField({ target: input })) {
                isValid = false;
                // Focus on first invalid field
                if (!firstInvalidField) {
                    firstInvalidField = input;
                }
            }
        });

        // Scroll to first invalid field if validation failed
        if (!isValid && firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidField.focus();
        }

        return isValid;
    }

    function validateAllSteps() {
        let isValid = true;
        for (let step = 1; step <= totalSteps; step++) {
            const stepElement = document.querySelector(`.form-step[data-step="${step}"]`);
            const requiredInputs = stepElement.querySelectorAll('input[required]');
            
            requiredInputs.forEach(input => {
                if (!validateField({ target: input })) {
                    isValid = false;
                }
            });
        }
        return isValid;
    }

    function validateField(e) {
        const input = e.target;
        const value = input.value.trim();
        let isValid = true;
        let errorMessage = '';

        // Required field validation
        if (input.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required.';
        }

        // Email validation - must be EVSU email
        if (input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address.';
            } else if (!value.toLowerCase().endsWith('@evsu.edu.ph')) {
                isValid = false;
                errorMessage = 'Please use an official EVSU email address (@evsu.edu.ph).';
            }
        }

        // Username validation
        if (input.name === 'acc_user' && value) {
            if (value.length < 3 || value.length > 20) {
                isValid = false;
                errorMessage = 'Username must be 3-20 characters long.';
            }
            // Check for invalid characters (no @ symbol, no spaces)
            if (value.includes('@') || value.includes(' ')) {
                isValid = false;
                errorMessage = 'Username cannot contain @ or spaces.';
            }
        }

        // Select field validation
        if (input.tagName === 'SELECT' && input.hasAttribute('required')) {
            if (!value || value === '') {
                isValid = false;
                errorMessage = 'Please select an option.';
            }
        }

        // Name validation
        if ((input.name === 'fname' || input.name === 'lname') && value) {
            if (value.length < 2) {
                isValid = false;
                errorMessage = 'Name must be at least 2 characters long.';
            }
        }

        // Update field appearance
        input.classList.remove('is-valid', 'is-invalid');
        if (value || input.hasAttribute('required')) {
            input.classList.add(isValid ? 'is-valid' : 'is-invalid');
        }

        // Show/hide error message
        let feedback = input.nextElementSibling;
        // For input-group, find the next sibling after the parent
        if (input.closest('.input-group')) {
            const inputGroup = input.closest('.input-group');
            feedback = inputGroup.nextElementSibling;
        }
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = errorMessage;
            feedback.style.display = isValid ? 'none' : 'block';
        }

        return isValid;
    }

    function clearFieldError(e) {
        const input = e.target;
        input.classList.remove('is-invalid');
        const feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.style.display = 'none';
        }
    }

    function updateReviewInfo() {
        const fname = document.getElementById('fname')?.value.trim() || '';
        const lname = document.getElementById('lname')?.value.trim() || '';
        const minitial = document.getElementById('minitial')?.value.trim() || '';
        const suffix = document.getElementById('suffix')?.value.trim() || '';
        const email = document.getElementById('acc_email')?.value.trim() || '';
        const username = document.getElementById('acc_user')?.value.trim() || '';
        const roleId = document.getElementById('role_id')?.value || '';
        const deptId = document.getElementById('dept_id')?.value || '';

        // Build full name with suffix
        let fullName = `${fname} ${minitial ? minitial + '.' : ''} ${lname}`.trim();
        if (suffix) {
            fullName += ` ${suffix}`;
        }
        
        // Get role name
        const roleSelectElem = document.getElementById('role_id');
        const selectedRole = roleSelectElem ? roleSelectElem.options[roleSelectElem.selectedIndex] : null;
        const roleName = selectedRole ? selectedRole.text : '-';
        
        // Get department name
        const deptSelect = document.getElementById('dept_id');
        const selectedDept = deptSelect ? deptSelect.options[deptSelect.selectedIndex] : null;
        const deptName = selectedDept && selectedDept.value ? selectedDept.text : '-';
        
        // Update review section
        document.getElementById('reviewName').textContent = fullName || '-';
        document.getElementById('reviewEmail').textContent = email || '-';
        document.getElementById('reviewUsername').textContent = username || '-';
        document.getElementById('reviewRole').textContent = roleName;
        document.getElementById('reviewProgram').textContent = deptName;
    }

    // Function to load rank and designation options from API
    function loadRankOptions() {
        const rankSelect = document.getElementById('rank');
        const designationSelect = document.getElementById('designation');
        
        if (!rankSelect) {
            console.warn('Rank select element not found');
            return;
        }

        // Fetch rank and designation options from API
        fetch('../../admin/management/get_workload_policy.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Workload policy data:', data);
                
                // Load rank options
                if (data.success && data.data && data.data.ranks && data.data.ranks.length > 0) {
                    // Clear existing options except the first one
                    rankSelect.innerHTML = '<option value="">Select Rank</option>';

                    // Add rank options
                    data.data.ranks.forEach(rank => {
                        const option = document.createElement('option');
                        option.value = rank;
                        option.textContent = rank;
                        rankSelect.appendChild(option);
                    });
                    console.log('Rank options loaded successfully:', data.data.ranks.length, 'ranks');
                } else {
                    console.warn('No rank options found in response:', data);
                    // Fallback: add common ranks if API fails
                    const fallbackRanks = [
                        'University Professor',
                        'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
                        'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
                        'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
                        'Instructor III', 'Instructor II', 'Instructor I'
                    ];
                    rankSelect.innerHTML = '<option value="">Select Rank</option>';
                    fallbackRanks.forEach(rank => {
                        const option = document.createElement('option');
                        option.value = rank;
                        option.textContent = rank;
                        rankSelect.appendChild(option);
                    });
                    console.log('Using fallback rank options');
                }
                
                // Load designation options
                if (designationSelect && data.success && data.data && data.data.designations && data.data.designations.length > 0) {
                    // Clear existing options
                    designationSelect.innerHTML = '';

                    // Add designation options
                    data.data.designations.forEach(designation => {
                        const option = document.createElement('option');
                        option.value = designation;
                        option.textContent = designation;
                        designationSelect.appendChild(option);
                    });
                    console.log('Designation options loaded successfully:', data.data.designations.length, 'designations');
                } else if (designationSelect) {
                    console.warn('No designation options found in response, using fallback');
                    // Fallback: add common designations if API fails
                    const fallbackDesignations = [
                        'None',
                        'Vice President',
                        'Campus Director',
                        'Dean',
                        'Director',
                        'Head',
                        'Chairperson/Coordinator/As Officer in Faculty Association'
                    ];
                    designationSelect.innerHTML = '';
                    fallbackDesignations.forEach(designation => {
                        const option = document.createElement('option');
                        option.value = designation;
                        option.textContent = designation;
                        designationSelect.appendChild(option);
                    });
                    console.log('Using fallback designation options');
                }
            })
            .catch(error => {
                console.error('Error loading workload policy options:', error);
                
                // Fallback: add common ranks if API fails
                const fallbackRanks = [
                    'University Professor',
                    'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
                    'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
                    'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
                    'Instructor III', 'Instructor II', 'Instructor I'
                ];
                rankSelect.innerHTML = '<option value="">Select Rank</option>';
                fallbackRanks.forEach(rank => {
                    const option = document.createElement('option');
                    option.value = rank;
                    option.textContent = rank;
                    rankSelect.appendChild(option);
                });
                
                // Fallback: add common designations if API fails
                if (designationSelect) {
                    const fallbackDesignations = [
                        'None',
                        'Vice President',
                        'Campus Director',
                        'Dean',
                        'Director',
                        'Head',
                        'Chairperson/Coordinator/As Officer in Faculty Association'
                    ];
                    designationSelect.innerHTML = '';
                    fallbackDesignations.forEach(designation => {
                        const option = document.createElement('option');
                        option.value = designation;
                        option.textContent = designation;
                        designationSelect.appendChild(option);
                    });
                }
                console.log('Using fallback options due to error');
            });
    }

    // Function to load department options from API
    function loadDepartmentOptions() {
        const departmentSelect = document.getElementById('dept_id');
        
        if (!departmentSelect) {
            console.warn('Department select element not found');
            return;
        }

        // Fetch department options from API
        fetch('../../admin/management/get_available_departments.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Department data:', data);
                
                // Clear existing options
                departmentSelect.innerHTML = '<option value="">Select Department</option>';
                
                if (data.success && data.data && data.data.length > 0) {
                    // Add department options
                    data.data.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.dept_id;
                        option.textContent = dept.dept_name;
                        departmentSelect.appendChild(option);
                    });
                    console.log('Department options loaded successfully:', data.data.length, 'departments');
                } else {
                    console.warn('No department options found in response:', data);
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No departments available';
                    option.disabled = true;
                    departmentSelect.appendChild(option);
                }
            })
            .catch(error => {
                console.error('Error loading department options:', error);
                departmentSelect.innerHTML = '<option value="">Error Loading Departments</option>';
            });
    }

    // Add event listeners for all fields to update review section
    const fieldsToWatch = ['fname', 'lname', 'minitial', 'suffix', 'acc_email', 'acc_user', 
                          'role_id', 'dept_id'];
    
    fieldsToWatch.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', updateReviewInfo);
            field.addEventListener('change', updateReviewInfo);
        }
    });

    // Load options when modal opens
    const addUserModal = document.getElementById('addUserModal');
    if (addUserModal) {
        addUserModal.addEventListener('show.bs.modal', function() {
            // Reset to step 1 when modal opens
            currentStep = 1;
            updateStepDisplay();
            loadDepartmentOptions();
        });
        
        addUserModal.addEventListener('hidden.bs.modal', function() {
            // Reset form when modal closes - match admin side
            const form = document.getElementById('addUserForm');
            if (form) {
                form.reset();
            }
            currentStep = 1;
            updateStepDisplay();
            // Remove validation classes
            document.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                el.classList.remove('is-invalid', 'is-valid');
            });
        });
    }

    // Initialize
    updateStepDisplay();
}

// Profile picture preview function
function previewProfilePicture(input) {
    const file = input.files[0];
    const preview = document.getElementById('profilePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (file) {
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select a valid image file');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}


// Unified filter functionality - applies all filters together
// Make function globally accessible
window.applyFilters = function applyFilters() {
    const searchTerm = (document.getElementById('searchUsers')?.value || '').toLowerCase();
    const roleFilter = document.getElementById('roleFilter')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    const deptFilter = document.getElementById('deptFilter')?.value || '';
    const sortBy = document.getElementById('sortBy')?.value || '';
    const sortOrder = document.getElementById('sortOrder')?.value || '';
    
    const rows = document.querySelectorAll('.user-row');
    
    rows.forEach(row => {
        let show = true;
        
        // Apply search filter
        if (searchTerm) {
            const name = (row.dataset.name || '').toLowerCase();
            const email = (row.dataset.email || '').toLowerCase();
            const username = (row.dataset.username || '').toLowerCase();
            const role = (row.dataset.role || '').toLowerCase();
            const status = (row.dataset.status || '').toLowerCase();
            const dept = (row.dataset.dept || '').toLowerCase();
            const usernameFromDom = (row.querySelector('small')?.textContent || '').toLowerCase().replace(/^@/, '');
            
            const matchesSearch = name.includes(searchTerm) || 
                                email.includes(searchTerm) || 
                                username.includes(searchTerm) ||
                                usernameFromDom.includes(searchTerm) ||
                                role.includes(searchTerm) || 
                                status.includes(searchTerm) || 
                                dept.includes(searchTerm);
            
            if (!matchesSearch) show = false;
        }
        
        // Apply role filter
        if (roleFilter && row.dataset.role !== roleFilter) {
            show = false;
        }
        
        // Apply status filter
        if (statusFilter && row.dataset.status !== statusFilter) {
            show = false;
        }
        
        // Apply department filter
        if (deptFilter) {
            const dept = (row.dataset.dept || '').trim();
            if (!dept || dept !== deptFilter) {
                show = false;
            }
        }
        
        row.style.display = show ? '' : 'none';
    });
    
    // Update row count
    updateRowCount();
    
    // Apply sorting
    if (sortBy) {
        sortTable(sortBy);
    }
    
    // Apply order sorting
    if (sortOrder) {
        sortTableByOrder(sortOrder);
    }
}

window.clearFilters = function clearFilters() {
    document.getElementById('roleFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('deptFilter').value = '';
    document.getElementById('searchUsers').value = '';
    
    // Apply filters to show all rows (since all filters are now empty)
    window.applyFilters();
    
    // Reset sorting if needed
    const sortBy = document.getElementById('sortBy');
    const sortOrder = document.getElementById('sortOrder');
    if (sortBy) sortBy.value = '';
    if (sortOrder) sortOrder.value = '';
}

// User selection functions - Attach to window for iframe access
window.selectAllUsers = function selectAllUsers() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const selectAll = document.getElementById('selectAllUsers');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    if (selectAll) selectAll.checked = true;
}

window.clearSelection = function clearSelection() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const selectAll = document.getElementById('selectAllUsers');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    if (selectAll) selectAll.checked = false;
}

window.toggleAllUsers = function toggleAllUsers(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

// Status change functionality - Attach to window for iframe access
window.changeUserStatus = async function changeUserStatus(userId, newStatus) {
    const confirmed = await window.showConfirm(
        `Are you sure you want to change this user's status to ${newStatus}?`,
        'Change User Status'
    );
    
    if (confirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        form.innerHTML = `
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="new_status" value="${newStatus}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Role change functionality - Attach to window for iframe access
window.changeUserRole = async function changeUserRole(userId) {
    let newRole = null;
    
    window.ensureSwalPosition();
    const swalInstance = window.getSwal();
    
    if (swalInstance) {
        const { value } = await swalInstance.fire({
            title: 'Change User Role',
            text: 'Enter new role',
            input: 'select',
            inputOptions: {
                'Admin support': 'Admin support',
                'Admin': 'Admin',
                'Moderator': 'Moderator',
                'User': 'User'
            },
            inputPlaceholder: 'Select a role',
            showCancelButton: true,
            confirmButtonColor: '#800000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Change Role',
            cancelButtonText: 'Cancel',
            allowOutsideClick: true,
            allowEscapeKey: true
        });
        newRole = value;
    } else {
        newRole = prompt('Enter new role (Admin support, Admin, Moderator, User):');
    }
    
    if (newRole && ['Admin support', 'Admin', 'Moderator', 'User'].includes(newRole)) {
        const confirmed = await window.showConfirm(
            `Are you sure you want to change this user's role to ${newRole}?`,
            'Change User Role'
        );
        
        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            form.innerHTML = `
                <input type="hidden" name="change_role" value="1">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="new_role" value="${newRole}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
}

// Bulk actions - Attach to window for iframe access
window.bulkAction = async function bulkAction(action) {
    const selectedUsers = Array.from(document.querySelectorAll('input[name="selected_users[]"]:checked'))
        .map(checkbox => checkbox.value);
    
    if (selectedUsers.length === 0) {
        window.showAlert('Please select at least one user.', 'warning');
        return;
    }
    
    const actionText = {
        'activate': 'activate',
        'deactivate': 'deactivate', 
        'delete': 'delete',
        'export': 'export'
    }[action];
    
    const confirmed = await window.showConfirm(
        `Are you sure you want to ${actionText} ${selectedUsers.length} user(s)?`,
        'Confirm Action'
    );
    
    if (confirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="bulk_action" value="${action}">
            <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]')?.value || ''}">
            ${selectedUsers.map(id => `<input type="hidden" name="selected_users[]" value="${id}">`).join('')}
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Reset user password - Attach to window for iframe access
window.resetUserPassword = async function resetUserPassword(userId) {
    const confirmed = await window.showConfirm(
        'Are you sure you want to reset this user\'s password? They will need to set a new password on next login.',
        'Reset Password'
    );
    
    if (!confirmed) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    // Make AJAX request to reset password
    fetch('reset_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.showAlert('Password reset successfully! The user will need to set a new password on next login.', 'success');
            // Optionally reload the page to refresh data
            // window.location.reload();
        } else {
            window.showAlert('Error: ' + (data.message || 'Failed to reset password'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.showAlert('An error occurred while resetting the password.', 'error');
    });
}

// Delete user (archive) - Attach to window for iframe access
window.deleteUser = async function deleteUser(userId, userName) {
    const confirmed = await window.showConfirm(
        `Are you sure you want to archive user "${userName}"? This will mark them as deleted but can be restored later.`,
        'Archive User'
    );
    
    if (!confirmed) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    // Make AJAX request to delete/archive user
    fetch('delete_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.showAlert('User archived successfully!', 'success');
            window.location.reload();
        } else {
            window.showAlert('Error: ' + (data.message || 'Failed to archive user'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to form submission if AJAX fails
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_user.php';
        form.innerHTML = `
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// Restore user - Attach to window for iframe access
window.restoreUser = async function restoreUser(userId, userName) {
    const confirmed = await window.showConfirm(
        `Are you sure you want to restore user "${userName}"?`,
        'Restore User'
    );
    
    if (!confirmed) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    // Make AJAX request to restore user
    fetch('restore_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.showAlert('User restored successfully!', 'success');
            window.location.reload();
        } else {
            window.showAlert('Error: ' + (data.message || 'Failed to restore user'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.showAlert('An error occurred while restoring the user.', 'error');
    });
}

// Permanently delete user - Attach to window for iframe access
window.permanentlyDeleteUser = async function permanentlyDeleteUser(userId, userName) {
    const firstConfirm = await window.showConfirm(
        `WARNING: Are you sure you want to PERMANENTLY delete user "${userName}"? This action cannot be undone and will remove all user data from the system.`,
        'Permanent Deletion Warning'
    );
    
    if (!firstConfirm) {
        return;
    }
    
    // Double confirmation for permanent deletion
    const secondConfirm = await window.showConfirm(
        'This is your last chance to cancel. Click Yes to permanently delete this user.',
        'Final Confirmation'
    );
    
    if (!secondConfirm) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    // Make AJAX request to permanently delete user
    fetch('permanent_delete_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.showAlert('User permanently deleted successfully!', 'success');
            window.location.reload();
        } else {
            window.showAlert('Error: ' + (data.message || 'Failed to permanently delete user'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.showAlert('An error occurred while permanently deleting the user.', 'error');
    });
}

// Edit user function
let editCurrentStep = 1;
const editTotalSteps = 4;

window.editUser = function editUser(userId) {
    // Reset step to 1
    editCurrentStep = 1;
    
    // Show loading state
    const modal = document.getElementById('editUserModal');
    if (!modal) {
        console.error('Edit user modal not found');
        return;
    }
    
    // Reset form steps
    
    // Reset form steps within the modal
    modal.querySelectorAll('.step-content').forEach(step => {
        step.classList.add('d-none');
    });
    const step1 = modal.querySelector('#step1');
    if (step1) step1.classList.remove('d-none');
    
    // Track which fields are originally required (for validation)
    const allRequiredFields = modal.querySelectorAll('[required]');
    allRequiredFields.forEach(field => {
        field.setAttribute('data-original-required', 'true');
    });
    
    // Reset progress indicator within the modal
    modal.querySelectorAll('.step').forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index === 0) {
            step.classList.add('active');
        }
    });
    
    // Update buttons
    const prevBtn = modal.querySelector('#editPrevStep');
    const nextBtn = modal.querySelector('#editNextStep');
    const saveBtn = modal.querySelector('#editSaveUser');
    if (prevBtn) prevBtn.style.display = 'none';
    if (nextBtn) nextBtn.style.display = 'block';
    if (saveBtn) saveBtn.style.display = 'none';
    
    // Fetch user data
    fetch('user_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'get_user_data',
            user_id: userId,
            csrf_token: document.querySelector('input[name="csrf_token"]')?.value || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.user) {
            const user = data.user;
            
            // Populate form fields
            document.getElementById('editUserId').value = user.acc_id;
            document.getElementById('editFirstName').value = user.fname || '';
            document.getElementById('editLastName').value = user.lname || '';
            document.getElementById('editMiddleInitial').value = user.minitial || '';
            const suffixField = document.getElementById('editSuffix');
            if (suffixField) suffixField.value = user.suffix || '';
            document.getElementById('editUsername').value = user.acc_user || '';
            document.getElementById('editEmail').value = user.acc_email || '';
            document.getElementById('editRole').value = user.role_id || '';
            const instStatusField = document.getElementById('editInstStatus');
            if (instStatusField) instStatusField.value = user.inst_status || 'Regular';
            const programField = document.getElementById('editProgramId');
            if (programField) programField.value = user.program_id || '';
            const phoneField = document.getElementById('editInstPhone');
            if (phoneField) phoneField.value = user.inst_phone || '';
            
            // Populate workload hours if available
            if (user.administration_hours !== undefined) {
                document.getElementById('editAdministrationHours').value = user.administration_hours || 0;
            }
            if (user.instruction_hours !== undefined) {
                document.getElementById('editInstructionHours').value = user.instruction_hours || 0;
            }
            if (user.research_hours !== undefined) {
                document.getElementById('editResearchHours').value = user.research_hours || 0;
            }
            if (user.extension_hours !== undefined) {
                document.getElementById('editExtensionHours').value = user.extension_hours || 0;
            }
            if (user.instructional_functions_hours !== undefined) {
                document.getElementById('editInstructionalFunctionsHours').value = user.instructional_functions_hours || 0;
            }
            if (user.consultation_hours !== undefined) {
                document.getElementById('editConsultationHours').value = user.consultation_hours || 0;
            }
            
            // Load rank and designation if available
            // Pass user data to the function so it can set the values after loading
            loadEditRankOptions(userId, user.rank, user.designation);
            
            // Show modal - match admin side exactly
            const bsModal = new bootstrap.Modal(modal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            bsModal.show();
            
            // Initialize edit modal step navigation if not already initialized
            if (!window.editModalInitialized) {
                initEditModalSteps();
                window.editModalInitialized = true;
            }
        } else {
            alert('Failed to load user data: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error loading user data:', error);
        alert('Error loading user data. Please try again.');
    });
}

// Initialize edit modal step navigation
function initEditModalSteps() {
    const nextBtn = document.getElementById('editNextStep');
    const prevBtn = document.getElementById('editPrevStep');
    const saveBtn = document.getElementById('editSaveUser');
    
    if (nextBtn && !nextBtn.hasAttribute('data-listener-added')) {
        nextBtn.addEventListener('click', editNextStep);
        nextBtn.setAttribute('data-listener-added', 'true');
    }
    if (prevBtn && !prevBtn.hasAttribute('data-listener-added')) {
        prevBtn.addEventListener('click', editPrevStep);
        prevBtn.setAttribute('data-listener-added', 'true');
    }
    
    // Store original required state of fields when modal opens
    // This helps us track which fields should be required
    const editForm = document.getElementById('editUserForm');
    if (editForm && !editForm.hasAttribute('data-required-tracked')) {
        const modal = document.getElementById('editUserModal');
        if (modal) {
            // Mark all fields that have required attribute
            const allRequiredFields = modal.querySelectorAll('[required]');
            allRequiredFields.forEach(field => {
                field.setAttribute('data-original-required', 'true');
            });
        }
        editForm.setAttribute('data-required-tracked', 'true');
    }
}

// Next step function for edit modal
function editNextStep() {
    if (validateEditCurrentStep()) {
        if (editCurrentStep < editTotalSteps) {
            const modal = document.getElementById('editUserModal');
            if (!modal) return;
            
            // Hide current step
            const currentStepEl = modal.querySelector(`#step${editCurrentStep}`);
            if (currentStepEl) currentStepEl.classList.add('d-none');
            
            // Show next step
            editCurrentStep++;
            const nextStepEl = modal.querySelector(`#step${editCurrentStep}`);
            if (nextStepEl) nextStepEl.classList.remove('d-none');
            
            // Update progress indicator
            updateEditProgressIndicator();
            
            // Update buttons
            updateEditStepButtons();
            
            // If moving to review step, populate review content
            if (editCurrentStep === 4) {
                populateEditReviewStep();
            }
        }
    }
}

// Previous step function for edit modal
function editPrevStep() {
    if (editCurrentStep > 1) {
        const modal = document.getElementById('editUserModal');
        if (!modal) return;
        
        // Hide current step
        const currentStepEl = modal.querySelector(`#step${editCurrentStep}`);
        if (currentStepEl) currentStepEl.classList.add('d-none');
        
        // Show previous step
        editCurrentStep--;
        const prevStepEl = modal.querySelector(`#step${editCurrentStep}`);
        if (prevStepEl) prevStepEl.classList.remove('d-none');
        
        // Update progress indicator
        updateEditProgressIndicator();
        
        // Update buttons
        updateEditStepButtons();
    }
}

// Update progress indicator for edit modal
function updateEditProgressIndicator() {
    const modal = document.getElementById('editUserModal');
    if (!modal) return;
    
    const steps = modal.querySelectorAll('.step');
    steps.forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index + 1 < editCurrentStep) {
            step.classList.add('completed');
        } else if (index + 1 === editCurrentStep) {
            step.classList.add('active');
        }
    });
}

// Update step buttons for edit modal
function updateEditStepButtons() {
    const modal = document.getElementById('editUserModal');
    if (!modal) return;
    
    const prevBtn = modal.querySelector('#editPrevStep');
    const nextBtn = modal.querySelector('#editNextStep');
    const saveBtn = modal.querySelector('#editSaveUser');
    
    if (prevBtn) {
        prevBtn.style.display = editCurrentStep > 1 ? 'block' : 'none';
    }
    
    if (nextBtn) {
        nextBtn.style.display = editCurrentStep < editTotalSteps ? 'block' : 'none';
    }
    
    if (saveBtn) {
        saveBtn.style.display = editCurrentStep === editTotalSteps ? 'block' : 'none';
    }
}

// Validate current step for edit modal
function validateEditCurrentStep() {
    let isValid = true;
    const modal = document.getElementById('editUserModal');
    if (!modal) return false;
    
    if (editCurrentStep === 1) {
        // Validate personal information
        const firstName = modal.querySelector('#editFirstName');
        const lastName = modal.querySelector('#editLastName');
        
        if (firstName && !firstName.value.trim()) {
            firstName.classList.add('is-invalid');
            isValid = false;
        } else if (firstName) {
            firstName.classList.remove('is-invalid');
        }
        
        if (lastName && !lastName.value.trim()) {
            lastName.classList.add('is-invalid');
            isValid = false;
        } else if (lastName) {
            lastName.classList.remove('is-invalid');
        }
    } else if (editCurrentStep === 2) {
        // Validate account settings
        const username = modal.querySelector('#editUsername');
        const email = modal.querySelector('#editEmail');
        const status = modal.querySelector('#editStatus');
        const role = modal.querySelector('#editRole');
        
        if (username && !username.value.trim()) {
            username.classList.add('is-invalid');
            isValid = false;
        } else if (username) {
            username.classList.remove('is-invalid');
        }
        
        if (email && !email.value.trim()) {
            email.classList.add('is-invalid');
            isValid = false;
        } else if (email) {
            email.classList.remove('is-invalid');
        }
        
        if (status && !status.value) {
            status.classList.add('is-invalid');
            isValid = false;
        } else if (status) {
            status.classList.remove('is-invalid');
        }
        
        if (role && !role.value) {
            role.classList.add('is-invalid');
            isValid = false;
        } else if (role) {
            role.classList.remove('is-invalid');
        }
    }
    
    return isValid;
}

// Validate edit user form before submission
function validateEditUser() {
    const form = document.getElementById('editUserForm');
    if (!form) return false;
    
    const modal = document.getElementById('editUserModal');
    if (!modal) return false;
    
    // First, remove required attribute from all hidden fields to prevent browser validation error
    // This is the key fix - browser can't validate hidden required fields
    const hiddenRequiredFields = [];
    for (let step = 1; step <= editTotalSteps; step++) {
        const stepElement = modal.querySelector(`#step${step}`);
        if (stepElement && stepElement.classList.contains('d-none')) {
            const requiredFields = stepElement.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (field.hasAttribute('required')) {
                    hiddenRequiredFields.push(field);
                    field.removeAttribute('required');
                }
            });
        }
    }
    
    // Now validate all required fields manually (including those in hidden steps)
    let isValid = true;
    const invalidFields = [];
    
    for (let step = 1; step <= editTotalSteps; step++) {
        const stepElement = modal.querySelector(`#step${step}`);
        if (!stepElement) continue;
        
        // Get all fields that should be required
        // Check for data-original-required (set when modal opens) or if field currently has required
        const allFields = stepElement.querySelectorAll('input, select, textarea');
        
        allFields.forEach(field => {
            // Check if field should be required
            // A field should be required if:
            // 1. It has data-original-required attribute (tracked when modal opens)
            // 2. It currently has required attribute (for visible fields)
            // 3. It's the editRank field (always required based on HTML)
            const shouldBeRequired = field.hasAttribute('data-original-required') || 
                                   field.hasAttribute('required') ||
                                   field.id === 'editRank' ||
                                   field.name === 'edit_rank';
            
            if (shouldBeRequired) {
                // Validate the field
                let fieldValid = true;
                if (field.tagName === 'SELECT') {
                    const value = field.value;
                    fieldValid = value !== '' && 
                                value !== null && 
                                value !== undefined &&
                                !value.includes('Select Rank') && 
                                !value.includes('Select Status');
                } else if (field.type === 'checkbox' || field.type === 'radio') {
                    fieldValid = field.checked;
                } else {
                    fieldValid = field.value.trim() !== '';
                }
                
                if (!fieldValid) {
                    isValid = false;
                    const fieldLabel = field.previousElementSibling?.querySelector('label')?.textContent?.trim() ||
                                     field.closest('.form-label')?.textContent?.trim() ||
                                     field.name || 
                                     field.id || 
                                     'field';
                    
                    invalidFields.push({
                        field: field,
                        step: step,
                        name: fieldLabel
                    });
                    
                    // Add invalid class for styling
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            }
        });
    }
    
    // Restore required attributes to hidden fields (for next time)
    hiddenRequiredFields.forEach(field => {
        field.setAttribute('required', '');
    });
    
    // If validation failed, show the first step with invalid fields and scroll to it
    if (!isValid && invalidFields.length > 0) {
        const firstInvalid = invalidFields[0];
        const firstInvalidStep = firstInvalid.step;
        
        // Show the step with the invalid field
        if (firstInvalidStep !== editCurrentStep) {
            // Hide current step
            const currentStepEl = modal.querySelector(`#step${editCurrentStep}`);
            if (currentStepEl) currentStepEl.classList.add('d-none');
            
            // Show step with invalid field
            editCurrentStep = firstInvalidStep;
            const invalidStepEl = modal.querySelector(`#step${editCurrentStep}`);
            if (invalidStepEl) invalidStepEl.classList.remove('d-none');
            
            // Update progress indicator and buttons
            updateEditProgressIndicator();
            updateEditStepButtons();
        }
        
        // Scroll to and focus the invalid field
        setTimeout(() => {
            firstInvalid.field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.field.focus();
        }, 100);
        
        // Show error message
        alert(`Please fill in all required fields.\n\nMissing field: ${firstInvalid.name}`);
        
        return false;
    }
    
    // If validation passed, remove required from hidden fields one final time before submission
    // This ensures browser won't try to validate them
    for (let step = 1; step <= editTotalSteps; step++) {
        const stepElement = modal.querySelector(`#step${step}`);
        if (stepElement && stepElement.classList.contains('d-none')) {
            const requiredFields = stepElement.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                field.removeAttribute('required');
            });
        }
    }
    
    return true;
}

// Populate review step for edit modal
function populateEditReviewStep() {
    const modal = document.getElementById('editUserModal');
    if (!modal) return;
    
    const firstName = modal.querySelector('#editFirstName')?.value || '';
    const lastName = modal.querySelector('#editLastName')?.value || '';
    const middleInitial = modal.querySelector('#editMiddleInitial')?.value || '';
    const suffix = modal.querySelector('#editSuffix')?.value || '';
    const username = modal.querySelector('#editUsername')?.value || '';
    const email = modal.querySelector('#editEmail')?.value || '';
    const phone = modal.querySelector('#editInstPhone')?.value || '-';
    const instStatus = modal.querySelector('#editInstStatus');
    const instStatusText = instStatus?.options[instStatus.selectedIndex]?.text || '-';
    const program = modal.querySelector('#editProgramId');
    const programText = program?.options[program.selectedIndex]?.text || '-';
    const rank = modal.querySelector('#editRank');
    const rankText = rank?.options[rank.selectedIndex]?.text || '-';
    const designation = modal.querySelector('#editDesignation');
    const designationText = designation?.options[designation.selectedIndex]?.text || 'None';
    
    const reviewPersonalInfo = modal.querySelector('#reviewPersonalInfo');
    const reviewAccountInfo = modal.querySelector('#reviewAccountInfo');
    const reviewWorkloadInfo = modal.querySelector('#reviewWorkloadInfo');
    
    // Build full name with suffix
    let fullName = `${firstName} ${middleInitial ? middleInitial + '.' : ''} ${lastName}`.trim();
    if (suffix) {
        fullName += ` ${suffix}`;
    }
    
    // Get workload hours
    const adminHours = modal.querySelector('#editAdministrationHours')?.value || '0';
    const instructionHours = modal.querySelector('#editInstructionHours')?.value || '0';
    const researchHours = modal.querySelector('#editResearchHours')?.value || '0';
    const extensionHours = modal.querySelector('#editExtensionHours')?.value || '0';
    const instFuncHours = modal.querySelector('#editInstructionalFunctionsHours')?.value || '0';
    const consultationHours = modal.querySelector('#editConsultationHours')?.value || '0';
    
    if (reviewPersonalInfo) {
        reviewPersonalInfo.innerHTML = `
            <p class="mb-1"><strong>Name:</strong> ${fullName}</p>
            <p class="mb-1"><strong>Email:</strong> ${email}</p>
            <p class="mb-0"><strong>Phone:</strong> ${phone}</p>
        `;
    }
    
    if (reviewAccountInfo) {
        reviewAccountInfo.innerHTML = `
            <p class="mb-1"><strong>Username:</strong> ${username}</p>
            <p class="mb-1"><strong>Employment Status:</strong> ${instStatusText}</p>
            <p class="mb-1"><strong>Program:</strong> ${programText}</p>
            <p class="mb-1"><strong>Rank:</strong> ${rankText}</p>
            <p class="mb-0"><strong>Designation:</strong> ${designationText}</p>
        `;
    }
    
    if (reviewWorkloadInfo) {
        reviewWorkloadInfo.innerHTML = `
            <p class="mb-1"><strong>Administrative:</strong> ${adminHours}</p>
            <p class="mb-1"><strong>Instruction:</strong> ${instructionHours}</p>
            <p class="mb-1"><strong>Research:</strong> ${researchHours}</p>
            <p class="mb-1"><strong>Extension:</strong> ${extensionHours}</p>
            <p class="mb-1"><strong>Instructional Functions:</strong> ${instFuncHours}</p>
            <p class="mb-0"><strong>Consultation:</strong> ${consultationHours}</p>
        `;
    }
}

// Load rank and designation options for edit modal
function loadEditRankOptions(userId, userRank = null, userDesignation = null) {
    fetch('../../admin/management/get_workload_policy.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Workload policy data for edit:', data);
            
            // Load rank options
            const rankSelect = document.getElementById('editRank');
            if (rankSelect) {
                const appendRanks = (ranks) => {
                    rankSelect.innerHTML = '<option value="">Select Rank</option><option value="None">None</option>';
                    ranks.forEach(rank => {
                        const option = document.createElement('option');
                        option.value = rank;
                        option.textContent = rank;
                        rankSelect.appendChild(option);
                    });
                };

                if (data.success && data.data && data.data.ranks && data.data.ranks.length > 0) {
                    appendRanks(data.data.ranks);
                    console.log('Rank options loaded successfully:', data.data.ranks.length, 'ranks');
                } else {
                    console.warn('No rank options found in response, using fallback');
                    // Fallback: add common ranks if API fails
                    const fallbackRanks = [
                        'University Professor',
                        'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
                        'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
                        'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
                        'Instructor III', 'Instructor II', 'Instructor I'
                    ];
                    appendRanks(fallbackRanks);
                }
            }
            
            // Load designation options
            const designationSelect = document.getElementById('editDesignation');
            if (designationSelect) {
                if (data.success && data.data && data.data.designations && data.data.designations.length > 0) {
                    designationSelect.innerHTML = '';
                    data.data.designations.forEach(designation => {
                        const option = document.createElement('option');
                        option.value = designation;
                        option.textContent = designation;
                        designationSelect.appendChild(option);
                    });
                    console.log('Designation options loaded successfully:', data.data.designations.length, 'designations');
                } else {
                    console.warn('No designation options found in response, using fallback');
                    // Fallback: add common designations if API fails
                    const fallbackDesignations = [
                        'None',
                        'Vice President',
                        'Campus Director',
                        'Dean',
                        'Director',
                        'Head',
                        'Chairperson/Coordinator/As Officer in Faculty Association'
                    ];
                    designationSelect.innerHTML = '';
                    fallbackDesignations.forEach(designation => {
                        const option = document.createElement('option');
                        option.value = designation;
                        option.textContent = designation;
                        designationSelect.appendChild(option);
                    });
                }
            }
            
            // Try to get user's current rank and designation from the table row or user data
            const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
            let rank = userRank || '';
            let designation = userDesignation || '';
            
            if (!rank && userRow) {
                rank = userRow.dataset.rank || '';
            }
            if (!designation && userRow) {
                designation = userRow.dataset.designation || '';
            }
            
            // Set rank and designation values after a short delay to ensure options are loaded
            setTimeout(() => {
                if (rankSelect) {
                    if (rank) {
                        rankSelect.value = rank;
                        console.log('Set rank value to:', rank);
                    } else {
                        rankSelect.value = 'None';
                        console.log('Defaulted rank value to None');
                    }
                }
                
                if (designation && designationSelect) {
                    designationSelect.value = designation;
                    console.log('Set designation value to:', designation);
                }
            }, 200);
        })
        .catch(error => {
            console.error('Error loading rank and designation options:', error);
            
            // Fallback: add common ranks if API fails
            const rankSelect = document.getElementById('editRank');
            if (rankSelect) {
                const fallbackRanks = [
                    'University Professor',
                    'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
                    'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
                    'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
                    'Instructor III', 'Instructor II', 'Instructor I'
                ];
                rankSelect.innerHTML = '<option value=\"\">Select Rank</option><option value=\"None\">None</option>';
                fallbackRanks.forEach(rank => {
                    const option = document.createElement('option');
                    option.value = rank;
                    option.textContent = rank;
                    rankSelect.appendChild(option);
                });
            }
            
            // Fallback: add common designations if API fails
            const designationSelect = document.getElementById('editDesignation');
            if (designationSelect) {
                const fallbackDesignations = [
                    'None',
                    'Vice President',
                    'Campus Director',
                    'Dean',
                    'Director',
                    'Head',
                    'Chairperson/Coordinator/As Officer in Faculty Association'
                ];
                designationSelect.innerHTML = '';
                fallbackDesignations.forEach(designation => {
                    const option = document.createElement('option');
                    option.value = designation;
                    option.textContent = designation;
                    designationSelect.appendChild(option);
                });
            }
            console.log('Using fallback options due to error');
        });
}

// Enhanced view user details - match admin side behavior
window.viewUserDetails = function viewUserDetails(userId) {
    // Create a detailed user view modal - match admin side exactly
    const modalElement = document.getElementById('userDetailsModal');
    if (!modalElement) {
        console.error('User details modal not found');
        return;
    }
    
    document.getElementById('userDetailsContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 text-muted fs-5">Loading user details...</p>
        </div>
    `;
    
    // Use exact same approach as admin side
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: true,
        keyboard: true,
        focus: true
    });
    modal.show();
    
    // Try to fetch from API first, fallback to table data
    fetch(`get_user_details.php?user_id=${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.user) {
                // Use API data
                displayUserDetails(data.user);
            } else {
                // Fallback to table data
                console.warn('API returned no data, using table data');
                displayUserDetailsFromTable(userId);
            }
        })
        .catch(error => {
            console.error('Error fetching user details from API:', error);
            // Fallback to table data
            displayUserDetailsFromTable(userId);
        });
}

// Function to display user details by extracting data from the existing table
function displayUserDetailsFromTable(userId) {
    // Find the user row in the table
    const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (!userRow) {
        displayError('User data not found in table');
        return;
    }
    
    // Extract data from the table row - columns are:
    // 0: Checkbox, 1: Account Info, 2: Access Level, 3: Org Unit, 4: Status, 5: Activity, 6: Date, 7: Account System, 8: Actions
    const cells = userRow.querySelectorAll('td');
    if (cells.length < 8) {
        displayError('Insufficient data in table row');
        return;
    }
    
    // Get user info from Account Information column (index 1)
    const accountInfoCell = cells[1];
    // Try to get name from the table cell first
    const nameElement = accountInfoCell.querySelector('.flex-grow-1 .fw-semibold');
    let nameText = nameElement ? nameElement.textContent.trim() : '';
    
    // Fallback: Try to get name from data attribute on the row
    if (!nameText || nameText === '') {
        const dataName = userRow.getAttribute('data-name');
        if (dataName) {
            // Convert "first last" format to proper case
            nameText = dataName.split(' ').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
            ).join(' ');
        } else {
            nameText = 'Unknown User';
        }
    }
    
    // Get username and email from the small elements
    const smallElements = accountInfoCell.querySelectorAll('small');
    let usernameText = 'undefined';
    let emailText = 'No email';
    
    if (smallElements.length >= 2) {
        // First small element is username (@username)
        usernameText = smallElements[0].textContent.replace('@', '').trim();
        // Second small element is email
        emailText = smallElements[1].textContent.trim();
    }
    
    // Get profile picture from Account Information column
    const profileImg = accountInfoCell.querySelector('img');
    const profilePicture = profileImg ? profileImg.src : null;
    
    // Get role info from Access Level column (index 2)
    const roleCell = cells[2];
    const roleText = roleCell.textContent.trim();
    
    // Get department info from Organizational Unit column (index 3)
    const deptCell = cells[3];
    const deptText = deptCell.textContent.trim();
    
    // Get status from Account Status column (index 4)
    const statusCell = cells[4];
    const statusBadge = statusCell.querySelector('.badge');
    const statusText = statusBadge ? statusBadge.textContent.trim() : 'Unknown';
    
    // Get registration date from Registration Date column (index 6)
    const dateCell = cells[6];
    const dateText = dateCell.textContent.trim();
    
    // Parse name into first and last name
    const nameParts = nameText.split(' ');
    const fname = nameParts[0] || 'Unknown';
    const lname = nameParts.slice(1).join(' ') || 'User';
    
    // Get rank and designation from data attributes if available
    const rank = userRow.dataset.rank || '';
    const designation = userRow.dataset.designation || '';
    
    // Get workload hours from data attributes (fallback if database fetch fails)
    const administrationHours = parseInt(userRow.dataset.administrationHours) || 0;
    const instructionHours = parseInt(userRow.dataset.instructionHours) || 0;
    const researchHours = parseInt(userRow.dataset.researchHours) || 0;
    const extensionHours = parseInt(userRow.dataset.extensionHours) || 0;
    const instructionalFunctionsHours = parseInt(userRow.dataset.instructionalFunctionsHours) || 0;
    const consultationHours = parseInt(userRow.dataset.consultationHours) || 0;
    
    // Create user object
    const user = {
        acc_id: userId,
        fname: fname,
        lname: lname,
        minitial: '',
        acc_user: usernameText,
        acc_email: emailText,
        profile_picture: profilePicture,
        acc_status: statusText,
        dept_name: deptText,
        role_name: roleText,
        rank: rank,
        designation: designation,
        administration_hours: administrationHours,
        instruction_hours: instructionHours,
        research_hours: researchHours,
        extension_hours: extensionHours,
        instructional_functions_hours: instructionalFunctionsHours,
        consultation_hours: consultationHours,
        created_at: dateText,
        last_login: 'Never logged in'
    };
    
    // Display the user details
    displayUserDetails(user);
}

// Display user details in the modal
function displayUserDetails(user) {
    const statusBadge = user.acc_status === 'Active' ? 
        '<span class="badge bg-maroon text-white">Active</span>' : 
        '<span class="badge bg-secondary text-white">' + user.acc_status + '</span>';
    
    const profilePicSrc = (user.profile_path || (user.profile_picture ? '/' + user.profile_picture : ''));
    const profilePicture = profilePicSrc ? 
        `<img src="${profilePicSrc}" alt="Profile" class="rounded-circle border border-3 border-white shadow-lg" style="width: 120px; height: 120px; object-fit: cover;">` :
        `<div class="avatar-lg bg-gradient-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 border border-3 border-white shadow-lg" style="width: 120px; height: 120px;">
            <i class="bi bi-person fs-1"></i>
        </div>`;
    
    // Calculate total workload hours
    const admin = parseInt(user.administration_hours) || 0;
    const instruction = parseInt(user.instruction_hours) || 0;
    const research = parseInt(user.research_hours) || 0;
    const extension = parseInt(user.extension_hours) || 0;
    const instructional = parseInt(user.instructional_functions_hours) || 0;
    const consultation = parseInt(user.consultation_hours) || 0;
    const totalHours = admin + instruction + research + extension + instructional + consultation;
    
    document.getElementById('userDetailsContent').innerHTML = `
        <div class="container-fluid p-4">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            ${profilePicture}
                            <div class="ms-4">
                                <h4 class="mb-1">${user.fname} ${user.lname} ${user.minitial ? user.minitial + '.' : ''}</h4>
                                <p class="text-muted mb-2">${user.acc_user} • ${user.acc_email}</p>
                                <div class="d-flex align-items-center gap-2">
                                    ${statusBadge}
                                    <span class="badge bg-info">${user.role_name || 'No Role'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">User ID: ${user.acc_id}</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs mb-4" id="userDetailsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                        <i class="bi bi-person me-2"></i>Personal Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                        <i class="bi bi-shield-lock me-2"></i>Account Details
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">
                        <i class="bi bi-key me-2"></i>Permissions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="workload-tab" data-bs-toggle="tab" data-bs-target="#workload" type="button" role="tab">
                        <i class="bi bi-briefcase me-2"></i>Workload
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                        <i class="bi bi-graph-up me-2"></i>Activity
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="userDetailsTabContent">
                <!-- Personal Information Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Basic Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Full Name</label>
                                            <p class="mb-0">${user.fname} ${user.lname} ${user.minitial ? user.minitial + '.' : ''}</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Email Address</label>
                                            <p class="mb-0">
                                                <a href="mailto:${user.acc_email}" class="text-decoration-none">${user.acc_email}</a>
                                            </p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Username</label>
                                            <p class="mb-0">${user.acc_user}</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Account Status</label>
                                            <p class="mb-0">${statusBadge}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-building me-2"></i>Organization</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Department</label>
                                            <p class="mb-0">${user.dept_name || 'Not assigned'}</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Role</label>
                                            <p class="mb-0">${user.role_name || 'No Role'}</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Created At</label>
                                            <p class="mb-0">${user.created_at || 'Unknown'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Details Tab -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Account Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Username</label>
                                    <p class="mb-0">${user.acc_user}</p>
                                        </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Email Address</label>
                                    <p class="mb-0">${user.acc_email}</p>
                                        </div>
                                <div class="col-md-6">
                                            <label class="form-label fw-semibold text-muted">Account Status</label>
                                            <p class="mb-0">${statusBadge}</p>
                        </div>
                        <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Last Login</label>
                                    <p class="mb-0">${user.last_login || 'Never logged in'}</p>
                                </div>
                                        </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Tab -->
                <div class="tab-pane fade" id="permissions" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-key me-2"></i>User Permissions</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Permissions are managed through role assignments.</p>
                                </div>
                            </div>
                        </div>

                <!-- Workload Tab -->
                <div class="tab-pane fade" id="workload" role="tabpanel">
                    <div class="row g-4">
                        <!-- Employment Information -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-briefcase me-2"></i>Employment Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Academic Rank</label>
                                            <p class="mb-0">${user.rank || 'Not assigned'}</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-muted">Designation</label>
                                            <p class="mb-0">${user.designation && user.designation !== 'None' ? user.designation : 'None'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Workload Summary -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                    <h6 class="mb-0 text-white"><i class="bi bi-clock-history me-2"></i>Workload Summary</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Administration Hours</label>
                                            <p class="mb-0 h5 text-dark" id="workloadAdministrationHours">${user.administration_hours !== null && user.administration_hours !== undefined ? user.administration_hours : 0}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Instruction Hours</label>
                                            <p class="mb-0 h5 text-dark" id="workloadInstructionHours">${user.instruction_hours !== null && user.instruction_hours !== undefined ? user.instruction_hours : 0}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Research Hours</label>
                                            <p class="mb-0 h5 text-dark" id="workloadResearchHours">${user.research_hours !== null && user.research_hours !== undefined ? user.research_hours : 0}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Extension Hours</label>
                                            <p class="mb-0 h5 text-dark" id="workloadExtensionHours">${user.extension_hours !== null && user.extension_hours !== undefined ? user.extension_hours : 0}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Instructional Functions Hours</label>
                                            <p class="mb-0 h5 text-dark" id="workloadInstructionalFunctionsHours">${user.instructional_functions_hours !== null && user.instructional_functions_hours !== undefined ? user.instructional_functions_hours : 0}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Consultation Hours</label>
                                            <p class="mb-0 h5 text-dark" id="workloadConsultationHours">${user.consultation_hours !== null && user.consultation_hours !== undefined ? user.consultation_hours : 0}</p>
                                        </div>
                                        <div class="col-12 mt-3 pt-3 border-top">
                                            <label class="form-label small text-muted mb-1">Total Hours</label>
                                            <p class="mb-0 h4 text-primary fw-bold" id="workloadTotalHours">${totalHours}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Recent Activity</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Activity logs are being loaded...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Setup modal event handlers
    setupUserDetailsModalHandlers(user.acc_id);
}

// Display error message
function displayError(message) {
    document.getElementById('userDetailsContent').innerHTML = `
        <div class="alert alert-danger m-4">
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
        </div>
    `;
}

// Update row count display
function updateRowCount() {
    const visibleRows = document.querySelectorAll('.user-row:not([style*="display: none"])').length;
    const totalRows = document.querySelectorAll('.user-row').length;
    // You can update a counter element if it exists
    const counterElement = document.getElementById('rowCount');
    if (counterElement) {
        counterElement.textContent = `${visibleRows} of ${totalRows} users`;
    }
}

// Setup user details modal handlers
function setupUserDetailsModalHandlers(userId) {
    // Edit user button
    const editBtn = document.getElementById('editUserFromDetails');
    if (editBtn) {
        editBtn.onclick = function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('userDetailsModal'));
            if (modal) modal.hide();
        setTimeout(() => editUser(userId), 300);
    };
    }
    
    // Export user details button
    const exportBtn = document.getElementById('exportUserDetails');
    if (exportBtn) {
        exportBtn.onclick = function() {
        exportUserDetails(userId);
    };
    }
}

// Export user details
function exportUserDetails(userId) {
    const userData = {
        userId: userId,
        exportDate: new Date().toISOString(),
        exportedBy: 'Admin'
    };
    
    const dataStr = JSON.stringify(userData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = `user_${userId}_details.json`;
    link.click();
    
    URL.revokeObjectURL(url);
    alert('User details exported successfully!');
}

// Function to show Add User Modal - match admin side behavior exactly
// Admin side uses: new bootstrap.Modal(document.getElementById('addAccountModal')).show()
window.showAddUserModal = function() {
    const modalElement = document.getElementById('addUserModal');
    if (modalElement) {
        // Use exact same approach as admin side
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
        modal.show();
    }
};

// Promote Role function
async function promoteRole(accId, userName) {
    const confirmed = await showConfirm(
        `Are you sure you want to promote ${userName}'s role?`,
        'Promote Role'
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('acc_id', accId);
    formData.append('action', 'promote');
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('../../admin/management/promote_demote_role.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('success', data.message || 'Role promoted successfully');
            } else {
                alert(data.message || 'Role promoted successfully');
            }
            // Reload the page to refresh the table
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            if (typeof showToast === 'function') {
                showToast('error', data.message || 'Failed to promote role');
            } else {
                alert(data.message || 'Failed to promote role');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showToast === 'function') {
            showToast('error', 'An error occurred while promoting role');
        } else {
            alert('An error occurred while promoting role');
        }
    });
}

// Demote Role function
async function demoteRole(accId, userName) {
    const confirmed = await showConfirm(
        `Are you sure you want to demote ${userName}'s role?`,
        'Demote Role'
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('acc_id', accId);
    formData.append('action', 'demote');
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('../../admin/management/promote_demote_role.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('success', data.message || 'Role demoted successfully');
            } else {
                alert(data.message || 'Role demoted successfully');
            }
            // Reload the page to refresh the table
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            if (typeof showToast === 'function') {
                showToast('error', data.message || 'Failed to demote role');
            } else {
                alert(data.message || 'Failed to demote role');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showToast === 'function') {
            showToast('error', 'An error occurred while demoting role');
        } else {
            alert('An error occurred while demoting role');
        }
    });
}

// Enhanced table swipe functionality
document.addEventListener('DOMContentLoaded', function() {
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
            if (typeof ResizeObserver !== 'undefined') {
                const resizeObserver = new ResizeObserver(() => {
                    updateScrollIndicator();
                });
                resizeObserver.observe(tableContainer);
            }
            
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
    
    // Re-initialize for dynamically loaded content
    setTimeout(initTableSwipeSupport, 500);
});
</script>

<!-- Logout Modal (same style as Add Department; compact size) -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-box-arrow-right text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 mb-0">Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer border-0 bg-light justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <a href="../auth/logout.php" class="btn btn-maroon"><i class="bi bi-check-lg me-1"></i>Logout</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/components/modal_department.php'; ?>

<script>
// Load colleges for department modal
function loadCollegesForDepartment() {
    fetch('../../admin/management/get_colleges.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Colleges data received:', data); // Debug log
            const collegeSelect = document.getElementById('dept_college_id');
            if (collegeSelect) {
                // Clear existing options except the first one
                collegeSelect.innerHTML = '<option value="">Select College...</option>';
                
                // Check if data has colleges array
                if (data.success && data.colleges && data.colleges.length > 0) {
                    // Add colleges
                    data.colleges.forEach(college => {
                        const option = document.createElement('option');
                        option.value = college.college_id;
                        option.textContent = college.college_name + (college.college_code ? ' (' + college.college_code + ')' : '');
                        collegeSelect.appendChild(option);
                    });
                    console.log('Loaded ' + data.colleges.length + ' colleges'); // Debug log
                } else {
                    console.warn('No colleges found in response:', data); // Debug log
                    // Add a message option if no colleges found
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No colleges available';
                    option.disabled = true;
                    collegeSelect.appendChild(option);
                }
            } else {
                console.error('College select element not found');
            }
        })
        .catch(error => {
            console.error('Error loading colleges:', error);
            const collegeSelect = document.getElementById('dept_college_id');
            if (collegeSelect) {
                collegeSelect.innerHTML = '<option value="">Error loading colleges</option>';
            }
        });
}

// Add Department modal behavior is handled in /assets/js/admin_support_departments.js