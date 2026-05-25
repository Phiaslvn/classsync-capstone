<?php
/**
 * Moderator Dashboard - Matches Admin Dashboard Structure
 * Same appearance as admin dashboard with moderator-specific functionality
 */

// Include session configuration first
require_once __DIR__ . '/../../config/session.php';

// Include security middleware
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Require Moderator role
requireRole('Moderator');

// Include the dashboard controller
require_once __DIR__ . '/../../admin/controllers/dashboard_controller.php';

// Get user data from controller
$userDisplayData = $dashboardController->getUserDisplayData();
$profileData = $dashboardController->getUserProfileData();
$jsUserData = $dashboardController->getJavaScriptUserData();

// Set JavaScript variables
$currentUserId = $jsUserData['acc_id'];
$currentDeptId = $jsUserData['dept_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Dashboard - EVSU OCC Scheduling System</title>
    
    <!-- Bootstrap CSS (relative to views/moderator/ so app works in a subdirectory) -->
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 CSS -->
    <link href="../../assets/css/sweetalert2.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="../../assets/css/dataTables.bootstrap5.min.css" />
    
    <!-- Custom CSS -->
    <link href="../../assets/css/main.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link href="../../assets/css/schedule.css" rel="stylesheet">
   
    
    <!-- SweetAlert2 Custom Styles -->
    <style>
        .swal2-popup-custom {
            border-radius: 15px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
        }
        
        .swal2-title-custom {
            color: #800000 !important;
            font-weight: 600 !important;
            font-size: 1.5rem !important;
        }
        
        .swal2-content-custom {
            color: #333 !important;
            font-size: 1rem !important;
        }
        
        .swal2-success .swal2-success-ring {
            border-color: #28a745 !important;
        }
        
        .swal2-success [class^=swal2-success-line] {
            background-color: #28a745 !important;
        }
        
        .swal2-error .swal2-error-ring {
            border-color: #dc3545 !important;
        }
        
        .swal2-error [class^=swal2-error-line] {
            background-color: #dc3545 !important;
        }
        
        .swal2-warning .swal2-warning-ring {
            border-color: #ffc107 !important;
        }
        
        .swal2-warning [class^=swal2-warning-line] {
            background-color: #ffc107 !important;
        }
        
        /* Curriculum Modal Styles */
        .modal-xl {
            max-width: 1200px;
        }
        
        .bg-maroon {
            background-color: #800000 !important;
        }
        
        .btn-maroon {
            background-color: #800000;
            border-color: #800000;
            color: white;
        }
        
        .btn-maroon:hover {
            background-color: #660000;
            border-color: #660000;
            color: white;
        }
        
        .text-maroon {
            color: #800000 !important;
        }
        
        /* Summary Cards */
        .card {
            border-radius: 12px;
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        /* Table Styling */
        .table-hover tbody tr:hover {
            background-color: rgba(128, 0, 0, 0.05);
        }
        
        /* Status Badges */
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        
        .badge-active {
            background-color: #28a745;
        }
        
        .badge-inactive {
            background-color: #6c757d;
        }
        
        .badge-draft {
            background-color: #ffc107;
            color: #000;
        }
        
        /* Form Styling */
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/evsu-logo.png">
</head>
<body>
    <script>
        window.currentUserId = <?= json_encode($jsUserData['acc_id']) ?>;
        window.currentDeptId = <?= json_encode($jsUserData['dept_id']) ?>;
        window.currentUserDeptId = <?= json_encode($jsUserData['dept_id']) ?>;
        window.isAdminSupport = <?= json_encode(isAdminSupport()) ?>;
    </script>
    <!-- Include Header -->
    <?php include __DIR__ . '/../../includes/dashboard/dashboard_header.php'; ?>
    
    <!-- Prepare sidebar variables -->
    <?php
    $username = $userDisplayData['username'];
    $roleName = $userDisplayData['role_name'];
    $dept_name = $userDisplayData['dept_name'];
    $profileImage = $profileData['profile_picture'];
    ?>
    
    <!-- Include Unified Sidebar -->
    <?php include __DIR__ . '/../../includes/layout/sidebar_base.php'; ?>
    
    <!-- Main Content — same Bootstrap tab markup as admin dashboard (required for admin_dashboard.js / showTab) -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="tab-content" id="mainTabContent">
                <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                    <?php include __DIR__ . '/../../admin/views/components/dashboard_overview.php'; ?>
                </div>
                <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <?php include __DIR__ . '/../../admin/views/components/profile_management.php'; ?>
                </div>
                <div class="tab-pane fade" id="roles" role="tabpanel" aria-labelledby="roles-tab">
                    <?php include __DIR__ . '/../../admin/views/components/user_management.php'; ?>
                </div>
                <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                    <?php include __DIR__ . '/../../admin/views/components/schedule_management.php'; ?>
                </div>
                <div class="tab-pane fade" id="curriculum" role="tabpanel" aria-labelledby="curriculum-tab">
                    <?php include __DIR__ . '/../../admin/views/components/subject_management.php'; ?>
                </div>
                <div class="tab-pane fade" id="curriculum_management" role="tabpanel" aria-labelledby="curriculum_management-tab">
                    <?php include __DIR__ . '/../../admin/views/components/curriculum_management.php'; ?>
                </div>
                <div class="tab-pane fade" id="course_management" role="tabpanel" aria-labelledby="course_management-tab">
                    <?php include __DIR__ . '/../../admin/views/components/course_management.php'; ?>
                </div>
                <div class="tab-pane fade" id="room_requests" role="tabpanel" aria-labelledby="room_requests-tab">
                    <?php include __DIR__ . '/../../admin/views/components/room_management.php'; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include Footer -->
    <?php include __DIR__ . '/../../includes/dashboard/dashboard_footer.php'; ?>
    
    <!-- DataTables JS (jQuery + Bootstrap already loaded by dashboard_footer) -->
    <script src="/assets/js/dataTables.js"></script>
    <script src="/assets/js/dataTables.bootstrap5.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="/assets/js/sweetalert2.min.js"></script>
    
    <!-- Admin Dashboard JS (Same as Admin - Permission-based restrictions handled server-side) -->
    <script src="/admin/assets/js/admin_dashboard.js"></script>
    <script src="/admin/assets/js/subject_dropdowns.js"></script>
    <script src="/admin/assets/js/subject_management.js"></script>
    <script src="/assets/js/schedule_management.js?v=<?php echo time(); ?>"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Set JavaScript variables from PHP
        window.currentUserId = <?= json_encode($jsUserData['acc_id']) ?>;
        window.currentDeptId = <?= json_encode($jsUserData['dept_id']) ?>;
        window.currentUserDeptId = <?= json_encode($jsUserData['dept_id']) ?>; // Alias for room access management
        window.isAdminSupport = <?= json_encode(isAdminSupport()) ?>;
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔵 [DEBUG] Moderator Dashboard initializing...');
            console.log('🔵 [DEBUG] Current user ID:', window.currentUserId);
            console.log('🔵 [DEBUG] Current dept ID:', window.currentDeptId);
            
            // NOTE: Dashboard data is already loaded in PHP via dashboard_overview.php
            // No need to call loadDashboardData() - it would try to fetch get_dashboard_data.php which doesn't exist
            // All statistics are calculated server-side in the PHP component
            
            // Test connection first (optional)
            testConnection();
            
            // Set up tab switching
            setupTabSwitching();
            
            // Only load accounts if users tab is visible and user has permission
            const usersTabLink = document.querySelector('a[onclick*="roles"], a[href="#roles"]');
            if (usersTabLink && usersTabLink.offsetParent !== null) {
                // Load accounts data when users tab is accessed
                console.log('🔵 [DEBUG] Users tab is visible - will load accounts when tab is clicked');
            }
        });
        
        function setupTabSwitching() {
            // Add click event listeners to all tab buttons
            const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
            tabButtons.forEach(button => {
                button.addEventListener('shown.bs.tab', function(event) {
                    const targetTab = event.target.getAttribute('data-bs-target');
                    console.log('Tab switched to:', targetTab);
                    
                    // Load specific data based on the active tab
                    if (targetTab === '#roles-tab') {
                        console.log('Loading user management data...');
                        loadAccounts();
                    }
                });
            });
        }
    </script>
    
    <!-- Curriculum Management Modal -->
    <div class="modal fade" id="curriculumModal" tabindex="-1" aria-labelledby="curriculumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title" id="curriculumModalLabel">Curriculum Management</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Header Section -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="fw-bold mb-1">Computer Studies Curricula</h4>
                            <p class="text-muted mb-0">Manage academic curricula and programs for your department</p>
                        </div>
                        <?php if ($dashboardController->hasPermission('manage_curriculum')): ?>
                        <button type="button" class="btn btn-danger" onclick="openAddCurriculumModal()">
                            <i class="bi bi-plus-lg me-1"></i>Add Curriculum
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-primary mb-2">
                                        <i class="bi bi-folder fs-1"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="totalCurricula">0</h3>
                                    <p class="text-muted mb-0">Total Curricula</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-success mb-2">
                                        <i class="bi bi-mortarboard fs-1"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="totalPrograms">0</h3>
                                    <p class="text-muted mb-0">Programs</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-warning mb-2">
                                        <i class="bi bi-check-circle fs-1"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="activeCurricula">0</h3>
                                    <p class="text-muted mb-0">Active Curricula</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-info mb-2">
                                        <i class="bi bi-book fs-1"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="totalSubjects">0</h3>
                                    <p class="text-muted mb-0">Total Subjects</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Curricula Section -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="fw-bold mb-0">Academic Curricula</h6>
                        </div>
                        <div class="card-body">
                            <!-- Filters -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <select class="form-select" id="programFilter">
                                        <option value="">All Programs</option>
                                        <option value="BSIT">Bachelor of Science in Information Technology</option>
                                        <option value="BSCS">Bachelor of Science in Computer Science</option>
                                        <option value="BSIS">Bachelor of Science in Information Systems</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select" id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary" onclick="refreshCurricula()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                </div>
                            </div>

                            <!-- Curricula Table -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Program</th>
                                            <th>Year</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Subjects</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="curriculaTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="bi bi-folder fs-1 d-block mb-3"></i>
                                                    <h5>No curricula found</h5>
                                                    <p class="mb-0">Start by adding your first curriculum</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Curriculum Modal -->
    <?php if ($dashboardController->hasPermission('manage_curriculum')): ?>
    <div class="modal fade" id="addCurriculumModal" tabindex="-1" aria-labelledby="addCurriculumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title" id="addCurriculumModalLabel">Add New Curriculum</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCurriculumForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="curriculumProgram" class="form-label">Program *</label>
                                <select class="form-select" id="curriculumProgram" name="program" required>
                                    <option value="">Select Program</option>
                                    <option value="BSIT">Bachelor of Science in Information Technology</option>
                                    <option value="BSCS">Bachelor of Science in Computer Science</option>
                                    <option value="BSIS">Bachelor of Science in Information Systems</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="curriculumYear" class="form-label">Academic Year *</label>
                                <input type="text" class="form-control" id="curriculumYear" name="year" placeholder="e.g., 2024-2025" required>
                            </div>
                            <div class="col-12">
                                <label for="curriculumDescription" class="form-label">Description *</label>
                                <textarea class="form-control" id="curriculumDescription" name="description" rows="3" placeholder="Enter curriculum description..." required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="curriculumStatus" class="form-label">Status *</label>
                                <select class="form-select" id="curriculumStatus" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="curriculumSubjects" class="form-label">Number of Subjects</label>
                                <input type="number" class="form-control" id="curriculumSubjects" name="subjects_count" min="0" value="0">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-maroon" onclick="saveCurriculum()">
                        <i class="bi bi-check-lg me-1"></i>Save Curriculum
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Account Modal -->
    <?php if ($dashboardController->hasPermission('manage_users')): ?>
    <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAccountModalLabel">Add New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addAccountForm">
                        <!-- Personal Information -->
                        <div class="mb-4">
                            <h6 class="mb-3 text-primary">Personal Information</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="add_fname" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="add_fname" name="fname" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_minitial" class="form-label">Middle Initial</label>
                                    <input type="text" class="form-control" id="add_minitial" name="minitial" maxlength="1">
                                </div>
                                <div class="col-md-4">
                                    <label for="add_lname" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="add_lname" name="lname" required>
                                </div>
                            </div>
                        </div>

                        <!-- Account Details -->
                        <div class="mb-4">
                            <h6 class="mb-3 text-primary">Account Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="add_acc_user" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="add_acc_user" name="acc_user" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_acc_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="add_acc_email" name="acc_email" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_role_id" class="form-label">Role *</label>
                                    <select class="form-select" id="add_role_id" name="role_id" required>
                                        <option value="">Select Role</option>
                                        <option value="3">Moderator</option>
                                        <option value="4">Instructor</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_inst_status" class="form-label">Employment Status *</label>
                                    <select class="form-select" id="add_inst_status" name="inst_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Part-Time">Part-Time</option>
                                        <option value="Contractual">Contractual</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Instructor Details -->
                        <div class="mb-4">
                            <h6 class="mb-3 text-primary">Instructor Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="add_rank" class="form-label">Academic Rank *</label>
                                    <select class="form-select" id="add_rank" name="rank" required>
                                        <option value="">Select Rank</option>
                                        <!-- Options will be populated dynamically from workload_policy table -->
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_designation" class="form-label">Designation</label>
                                    <select class="form-select" id="add_designation" name="designation">
                                        <option value="None">None</option>
                                        <!-- Options will be populated dynamically from workload_policy table -->
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_inst_phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="add_inst_phone" name="inst_phone" placeholder="+63 912 345 6789">
                                </div>
                            </div>
                        </div>

                        <!-- Workload Hours -->
                        <div class="mb-4">
                            <h6 class="mb-3 text-primary">Workload Hours</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="add_administration_hours" class="form-label">Administrative Hours *</label>
                                    <input type="number" class="form-control" id="add_administration_hours" name="administration_hours" min="0" value="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_instruction_hours" class="form-label">Instruction Hours *</label>
                                    <input type="number" class="form-control" id="add_instruction_hours" name="instruction_hours" min="0" value="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_research_hours" class="form-label">Research Hours *</label>
                                    <input type="number" class="form-control" id="add_research_hours" name="research_hours" min="0" value="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_extension_hours" class="form-label">Extension Hours *</label>
                                    <input type="number" class="form-control" id="add_extension_hours" name="extension_hours" min="0" value="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_instructional_functions_hours" class="form-label">Instructional Functions Hours *</label>
                                    <input type="number" class="form-control" id="add_instructional_functions_hours" name="instructional_functions_hours" min="0" value="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_consultation_hours" class="form-label">Consultation Hours *</label>
                                    <input type="number" class="form-control" id="add_consultation_hours" name="consultation_hours" min="0" value="0" required>
                                </div>
                            </div>
                        </div>

                        <!-- Auto Settings Info -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Automatic Settings:</strong> Password will be set to "evsu-occ" and account status will be "Active"
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="submitBtn" onclick="addAccount()">Create Account</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>