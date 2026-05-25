<?php
/**
 * Admin Dashboard - Refactored Version
 * Clean, organized structure with separated concerns
 */

// Include session configuration first
require_once __DIR__ . '/../../config/session.php';

// Include security middleware
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Require Admin role
requireRole('Admin');

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
    <title>Admin Dashboard - EVSU OCC Scheduling System</title>
    
    <!-- Bootstrap CSS -->
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="/assets/css/dataTables.bootstrap5.min.css" />
    
    <!-- SweetAlert2 CSS -->
    <link href="/assets/css/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
    <link href="/assets/css/schedule.css" rel="stylesheet">
    
    
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
        
        /* Progress Steps Styles */
        .progress-steps {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 1rem 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .step-circle {
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
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .step.completed .step-circle {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }

        .step.completed .step-circle::after {
            content: '✓';
            font-size: 1.2rem;
        }

        .step-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            margin-top: 0.5rem;
            text-align: center;
            transition: color 0.3s ease;
        }

        .step.active .step-label {
            color: #0d6efd;
        }

        .step.completed .step-label {
            color: #28a745;
        }

        .step-line {
            width: 60px;
            height: 2px;
            background-color: #e9ecef;
            margin: 0 1rem;
            transition: background-color 0.3s ease;
        }

        .step.completed + .step-line {
            background-color: #28a745;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/evsu-logo.png">
</head>
<body>
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
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Bootstrap Tab Container -->
            <div class="tab-content" id="mainTabContent">
                <!-- Dashboard Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                    <?php include __DIR__ . '/../../admin/views/components/dashboard_overview.php'; ?>
                </div>
                
                <!-- Profile Tab -->
                <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <?php include __DIR__ . '/../../admin/views/components/profile_management.php'; ?>
                </div>
                
                <!-- Users Tab -->
                <div class="tab-pane fade" id="roles" role="tabpanel" aria-labelledby="roles-tab">
                    <?php include __DIR__ . '/../../admin/views/components/user_management.php'; ?>
                </div>
                
                <!-- Schedules Tab -->
                <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                    <?php include __DIR__ . '/../../admin/views/components/schedule_management.php'; ?>
                </div>
                
                <!-- Subjects Tab -->
                <div class="tab-pane fade" id="curriculum" role="tabpanel" aria-labelledby="curriculum-tab">
                    <?php include __DIR__ . '/../../admin/views/components/subject_management.php'; ?>
                </div>
                
                <!-- Curriculum Management Tab -->
                <div class="tab-pane fade" id="curriculum_management" role="tabpanel" aria-labelledby="curriculum_management-tab">
                    <?php include __DIR__ . '/../../admin/views/components/curriculum_management.php'; ?>
                </div>
                
                <!-- Course Management Tab -->
                <div class="tab-pane fade" id="course_management" role="tabpanel" aria-labelledby="course_management-tab">
                    <?php include __DIR__ . '/../../admin/views/components/course_management.php'; ?>
                </div>
                
                <!-- Rooms Tab -->
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
    
    <!-- Admin Dashboard JS -->
    <script src="/admin/assets/js/admin_dashboard.js?v=<?php echo time(); ?>"></script>
    <script src="/admin/assets/js/room_access_grants.js?v=<?php echo time(); ?>"></script>
    <script src="/admin/assets/js/subject_dropdowns.js"></script>
    <script src="/assets/js/schedule_management.js?v=<?php echo time(); ?>"></script>
    
    <!-- Add Existing Instructor Feature -->
    <script src="/admin/assets/js/add_existing_instructor.js?v=<?php echo time(); ?>"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Set JavaScript variables from PHP
        window.currentUserId = <?= json_encode($jsUserData['acc_id']) ?>;
        window.currentDeptId = <?= json_encode($jsUserData['dept_id']) ?>;
        window.currentUserDeptId = <?= json_encode($jsUserData['dept_id']) ?>; // Alias for room access management
        window.isAdminSupport = <?= json_encode(isAdminSupport()) ?>;
        
        // Set API base path for schedule autofill functionality
        // Calculate relative path from current location to admin/management
        <?php
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $pathParts = explode('/', trim($scriptPath, '/'));
        $viewsIndex = array_search('views', $pathParts);
        if ($viewsIndex !== false) {
            $depth = count($pathParts) - $viewsIndex - 1; // Depth from views/ to current file
            $relativePath = str_repeat('../', $depth + 1); // +1 to go up from views/ to root
            $apiBasePath = $relativePath . 'admin/management/';
        } else {
            $apiBasePath = '../admin/management/';
        }
        ?>
        window.API_BASE_PATH = <?= json_encode($apiBasePath) ?>;
        
        // Initialize Bootstrap modals explicitly after Bootstrap loads
        function initializeBootstrapModals() {
            try {
                // Check if Bootstrap is available
                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded!');
                    return;
                }
                
                // Don't interfere with logout modal - it's handled by sidebar_base.php
                // Just verify Bootstrap is working
                console.log('✅ Bootstrap is loaded and ready');
            } catch (error) {
                console.error('❌ Error checking Bootstrap:', error);
            }
        }
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard initializing...');
            console.log('Current user ID:', window.currentUserId);
            console.log('Current dept ID:', window.currentDeptId);
            
            // Initialize Bootstrap modals first
            initializeBootstrapModals();
            
            loadDashboardData();
            
            // Set up tab switching
            setupTabSwitching();
            
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
        });
        
        // Also initialize modals after a short delay to ensure Bootstrap is fully loaded
        setTimeout(function() {
            initializeBootstrapModals();
        }, 100);
        
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
                        <button type="button" class="btn btn-danger" onclick="openAddCurriculumModal()">
                            <i class="bi bi-plus-lg me-1"></i>Add Curriculum
                        </button>
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

    <!-- Add Account Modal -->
    <?php if ($dashboardController->hasPermission('manage_users')): ?>
    <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <div>
                        <h5 class="modal-title mb-0" id="addAccountModalLabel">Add New Account</h5>
                        <small class="text-white-50">Create a new instructor account</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Progress Indicator -->
                    <div class="row mb-4">
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

                    <form id="addAccountForm">
                        <!-- Step 1: Personal Information -->
                        <div class="form-step active" data-step="1">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                        <i class="bi bi-person me-2"></i>Personal Information
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_fname" class="form-label fw-semibold">
                                        <i class="bi bi-person me-1"></i>First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="add_fname" name="fname" placeholder="Enter first name" required>
                                    <div class="invalid-feedback">Please provide a valid first name.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_lname" class="form-label fw-semibold">
                                        <i class="bi bi-person me-1"></i>Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="add_lname" name="lname" placeholder="Enter last name" required>
                                    <div class="invalid-feedback">Please provide a valid last name.</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="add_minitial" class="form-label fw-semibold">
                                        <i class="bi bi-person me-1"></i>Middle Initial
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="add_minitial" name="minitial" placeholder="M" maxlength="1" style="text-transform: uppercase;">
                                    <div class="form-text">Optional middle initial</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="add_suffix" class="form-label fw-semibold">
                                        <i class="bi bi-person me-1"></i>Suffix
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="add_suffix" name="suffix" placeholder="Jr, Sr, II, III, etc." maxlength="10">
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
                                    <label for="add_acc_user" class="form-label fw-semibold">
                                        <i class="bi bi-at me-1"></i>Username <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="add_acc_user" name="acc_user" placeholder="Enter username" required>
                                    <div class="invalid-feedback">Please provide a valid username.</div>
                                    <div class="form-text">Username must be unique and 3-20 characters long</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_acc_email" class="form-label fw-semibold">
                                        <i class="bi bi-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control form-control-lg" id="add_acc_email" name="acc_email" placeholder="user@evsu.edu.ph" required>
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                    <div class="form-text">Use official EVSU email address</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-shield me-1"></i>Role(s) <span class="text-danger">*</span>
                                    </label>
                                    <div class="form-control form-control-lg" style="min-height: auto; padding: 0.75rem;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="role_ids[]" id="add_role_moderator" value="3">
                                            <label class="form-check-label" for="add_role_moderator">
                                                Moderator
                                            </label>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="role_ids[]" id="add_role_instructor" value="4" checked>
                                            <label class="form-check-label" for="add_role_instructor">
                                                Instructor
                                            </label>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback" id="role_ids_error" style="display: none;">Please select at least one role.</div>
                                    <div class="form-text">You can select both Moderator and Instructor roles</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_inst_status" class="form-label fw-semibold">
                                        <i class="bi bi-briefcase me-1"></i>Employment Status <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="add_inst_status" name="inst_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Part-Time">Part-Time</option>
                                        <option value="Contractual">Contractual</option>
                                    </select>
                                    <div class="invalid-feedback">Please select employment status.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_dept_id" class="form-label fw-semibold">
                                        <i class="bi bi-building me-1"></i>Department <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="add_dept_id" name="dept_id" required>
                                        <option value="">Select Department</option>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                    <div class="invalid-feedback">Please select a department.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_rank" class="form-label fw-semibold">
                                        <i class="bi bi-award me-1"></i>Academic Rank <span class="text-danger rank-asterisk">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="add_rank" name="rank">
                                        <option value="">Select Rank</option>
                                        <!-- Options will be populated dynamically from workload_policy table -->
                                    </select>
                                    <div class="invalid-feedback">Please select academic rank.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="add_designation" class="form-label fw-semibold">
                                        <i class="bi bi-briefcase-fill me-1"></i>Designation
                                    </label>
                                    <select class="form-select form-select-lg" id="add_designation" name="designation">
                                        <option value="None">None</option>
                                        <!-- Options will be populated dynamically from workload_policy table -->
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
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Workload Hours -->
                        <div class="form-step" data-step="3">
                            <div class="row g-3">
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                        <i class="bi bi-clock me-2"></i>Workload Hours
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_administration_hours" class="form-label fw-semibold">
                                        <i class="bi bi-building me-1"></i>Administrative Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="add_administration_hours" name="administration_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter administrative hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_instruction_hours" class="form-label fw-semibold">
                                        <i class="bi bi-book-half me-1"></i>Instruction Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="add_instruction_hours" name="instruction_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter instruction hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_research_hours" class="form-label fw-semibold">
                                        <i class="bi bi-search me-1"></i>Research Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="add_research_hours" name="research_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter research hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_extension_hours" class="form-label fw-semibold">
                                        <i class="bi bi-people me-1"></i>Extension Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="add_extension_hours" name="extension_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter extension hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_instructional_functions_hours" class="form-label fw-semibold">
                                        <i class="bi bi-mortarboard me-1"></i>Instructional Functions Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="add_instructional_functions_hours" name="instructional_functions_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter instructional functions hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="add_consultation_hours" class="form-label fw-semibold">
                                        <i class="bi bi-chat-dots me-1"></i>Consultation Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-lg" id="add_consultation_hours" name="consultation_hours" min="0" value="0" required>
                                    <div class="invalid-feedback">Please enter consultation hours.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review Info -->
                        <div class="form-step" data-step="4">
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
                                                    <p class="mb-1"><strong>Email:</strong> <span id="reviewEmail">-</span></p>
                                                    <p class="mb-0"><strong>Phone:</strong> <span id="reviewPhone">-</span></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6 class="fw-bold text-dark mb-2">Account Details</h6>
                                                    <p class="mb-1"><strong>Username:</strong> <span id="reviewUsername">-</span></p>
                                                    <p class="mb-1"><strong>Role:</strong> <span id="reviewRole">-</span></p>
                                                    <p class="mb-1"><strong>Employment Status:</strong> <span id="reviewStatus">-</span></p>
                                                    <p class="mb-1"><strong>Department:</strong> <span id="reviewDepartment">-</span></p>
                                                    <p class="mb-1"><strong>Program:</strong> <span id="reviewProgram">-</span></p>
                                                    <p class="mb-1"><strong>Rank:</strong> <span id="reviewRank">-</span></p>
                                                    <p class="mb-0"><strong>Designation:</strong> <span id="reviewDesignation">-</span></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6 class="fw-bold text-dark mb-2">Workload Hours</h6>
                                                    <p class="mb-1"><strong>Administrative:</strong> <span id="reviewAdminHours">-</span></p>
                                                    <p class="mb-1"><strong>Instruction:</strong> <span id="reviewInstructionHours">-</span></p>
                                                    <p class="mb-1"><strong>Research:</strong> <span id="reviewResearchHours">-</span></p>
                                                    <p class="mb-1"><strong>Extension:</strong> <span id="reviewExtensionHours">-</span></p>
                                                    <p class="mb-1"><strong>Instructional Functions:</strong> <span id="reviewInstFuncHours">-</span></p>
                                                    <p class="mb-0"><strong>Consultation:</strong> <span id="reviewConsultationHours">-</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <div class="d-flex justify-content-between w-100">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="prevStepBtn" style="display: none;">
                            <i class="bi bi-arrow-left me-2"></i>Previous
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-outline-primary rounded-pill px-4" id="nextStepBtn">
                                Next <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                            <button type="button" class="btn btn-success rounded-pill px-4" id="submitBtn" style="display: none;" onclick="addAccount()">
                                <i class="bi bi-check-lg me-2"></i>Create Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php /* NOTE:
       Admin dashboard already loads `/admin/assets/js/admin_dashboard.js`, which defines the Users table renderer.
       Loading `../../assets/js/admin.js` here causes function collisions (e.g., renderUsersTable/createUserTableRow)
       and results in misaligned Users table columns. */ ?>
    <script>
    // Step navigation for Add Account Modal - wrapped in IIFE to avoid variable conflicts
    (function() {
        'use strict';
        
        let addAccountCurrentStep = 1;
        const addAccountTotalSteps = 4;

        // Use event delegation - attach to document so it works even if buttons don't exist yet
        document.addEventListener('click', function(e) {
            // Next button click
            if (e.target && (e.target.id === 'nextStepBtn' || e.target.closest('#nextStepBtn'))) {
                e.preventDefault();
                e.stopPropagation();
                if (validateAddAccountStep()) {
                    if (addAccountCurrentStep < addAccountTotalSteps) {
                        addAccountCurrentStep++;
                        updateAddAccountStepDisplay();
                        if (addAccountCurrentStep === 4) {
                            updateAddAccountReviewInfo();
                        }
                    }
                }
                return false;
            }

            // Previous button click
            if (e.target && (e.target.id === 'prevStepBtn' || e.target.closest('#prevStepBtn'))) {
                e.preventDefault();
                e.stopPropagation();
                if (addAccountCurrentStep > 1) {
                    addAccountCurrentStep--;
                    updateAddAccountStepDisplay();
                }
                return false;
            }
        });

        // Initialize when modal is shown
        document.addEventListener('DOMContentLoaded', function() {
            const addAccountModal = document.getElementById('addAccountModal');
            if (addAccountModal) {
                addAccountModal.addEventListener('show.bs.modal', function() {
                    addAccountCurrentStep = 1;
                    updateAddAccountStepDisplay();
                    loadDepartmentsForAddAccount();
                });
                
                // Reset form when modal is hidden
                addAccountModal.addEventListener('hidden.bs.modal', function() {
                    const form = document.getElementById('addAccountForm');
                    if (form) {
                        form.reset();
                    }
                    addAccountCurrentStep = 1;
                    updateAddAccountStepDisplay();
                    // Remove validation classes
                    document.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                        el.classList.remove('is-invalid', 'is-valid');
                    });
                });
            }
        });

        function updateAddAccountStepDisplay() {
            // Get the Add Account modal to scope queries
            const addAccountModal = document.getElementById('addAccountModal');
            if (!addAccountModal) return;
            
            // Update progress indicator (only within Add Account modal)
            addAccountModal.querySelectorAll('.progress-steps .step').forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('active', 'completed');
                if (stepNum < addAccountCurrentStep) {
                    step.classList.add('completed');
                } else if (stepNum === addAccountCurrentStep) {
                    step.classList.add('active');
                }
            });

            // Update form steps (only within Add Account modal)
            addAccountModal.querySelectorAll('.form-step').forEach((step, index) => {
                step.classList.remove('active');
                if (index + 1 === addAccountCurrentStep) {
                    step.classList.add('active');
                }
            });

            // Update buttons
            const prevBtn = document.getElementById('prevStepBtn');
            const nextBtn = document.getElementById('nextStepBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            if (prevBtn) prevBtn.style.display = addAccountCurrentStep > 1 ? 'block' : 'none';
            if (nextBtn) nextBtn.style.display = addAccountCurrentStep < addAccountTotalSteps ? 'block' : 'none';
            if (submitBtn) submitBtn.style.display = addAccountCurrentStep === addAccountTotalSteps ? 'block' : 'none';
        }

        function validateAddAccountStep() {
            const addAccountModal = document.getElementById('addAccountModal');
            if (!addAccountModal) return false;
            
            const currentStepElement = addAccountModal.querySelector(`.form-step[data-step="${addAccountCurrentStep}"]`);
            if (!currentStepElement) {
                return false;
            }
            
            let isValid = true;
            
            // Special validation for role checkboxes (Step 2)
            if (addAccountCurrentStep === 2) {
                const roleCheckboxes = addAccountModal.querySelectorAll('input[name="role_ids[]"]');
                const checkedRoles = Array.from(roleCheckboxes).filter(cb => cb.checked);
                if (checkedRoles.length === 0) {
                    isValid = false;
                    const roleError = document.getElementById("role_ids_error");
                    if (roleError) {
                        roleError.style.display = "block";
                    }
                    // Mark checkboxes container as invalid
                    roleCheckboxes.forEach(cb => {
                        cb.classList.add('is-invalid');
                    });
                } else {
                    const roleError = document.getElementById("role_ids_error");
                    if (roleError) {
                        roleError.style.display = "none";
                    }
                    roleCheckboxes.forEach(cb => {
                        cb.classList.remove('is-invalid');
                    });
                }
            }
            
            const requiredInputs = currentStepElement.querySelectorAll('input[required], select[required]');
            requiredInputs.forEach(input => {
                // Skip checkbox inputs (they're handled separately above)
                if (input.type === 'checkbox') return;
                
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                }
            });

            if (!isValid) {
                if (addAccountCurrentStep === 2) {
                    alert('Please select at least one role and fill in all required fields before proceeding.');
                } else {
                    alert('Please fill in all required fields before proceeding.');
                }
            }

            return isValid;
        }

        // Function to load departments for Add Account modal
        function loadDepartmentsForAddAccount() {
            const deptSelect = document.getElementById('add_dept_id');
            if (!deptSelect) return;
            
            deptSelect.innerHTML = '<option value="">Loading Departments...</option>';
            
            fetch('../../admin/management/get_departments.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    deptSelect.innerHTML = '<option value="">Select Department</option>';
                    
                    if (data.success && data.departments && data.departments.length > 0) {
                        console.log(`Loading ${data.departments.length} departments for Add Account modal`);
                        data.departments.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.dept_id;
                            const displayText = dept.college_name 
                                ? `${dept.dept_name} (${dept.college_name})`
                                : dept.dept_name;
                            option.textContent = displayText;
                            deptSelect.appendChild(option);
                        });

                        // Auto-select the current logged-in department, if available
                        if (typeof window.currentDeptId !== 'undefined' && window.currentDeptId) {
                            deptSelect.value = String(window.currentDeptId);
                            
                            // Trigger change event in case other logic depends on it (e.g., program dropdown)
                            const changeEvent = new Event('change', { bubbles: true });
                            deptSelect.dispatchEvent(changeEvent);
                        }
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No departments available';
                        option.disabled = true;
                        deptSelect.appendChild(option);
                    }
                })
                .catch(error => {
                    console.error('Error loading departments for Add Account:', error);
                    deptSelect.innerHTML = '<option value="">Error Loading Departments</option>';
                });
        }
        
        function updateAddAccountReviewInfo() {
            const fname = document.getElementById('add_fname').value.trim();
            const lname = document.getElementById('add_lname').value.trim();
            const minitial = document.getElementById('add_minitial').value.trim();
            const suffix = document.getElementById('add_suffix').value.trim();
            const email = document.getElementById('add_acc_email').value.trim();
            const username = document.getElementById('add_acc_user').value.trim();
            const phoneInput = document.getElementById('add_inst_phone');
            const phone = phoneInput ? phoneInput.value.trim() : '';
            // Get selected roles (checkboxes)
            const roleCheckboxes = document.querySelectorAll('input[name="role_ids[]"]:checked');
            const roleNames = [];
            roleCheckboxes.forEach(cb => {
                if (cb.value === "3") roleNames.push("Moderator");
                else if (cb.value === "4") roleNames.push("Instructor");
            });
            const roleName = roleNames.length > 0 ? roleNames.join(", ") : "-";
            
            const statusSelect = document.getElementById('add_inst_status');
            const status = statusSelect ? statusSelect.options[statusSelect.selectedIndex] ? statusSelect.options[statusSelect.selectedIndex].text : '-' : '-';
            const deptId = document.getElementById('add_dept_id').value;
            const rank = document.getElementById('add_rank').value;
            const designation = document.getElementById('add_designation').value;
            const adminHours = document.getElementById('add_administration_hours').value;
            const instructionHours = document.getElementById('add_instruction_hours').value;
            const researchHours = document.getElementById('add_research_hours').value;
            const extensionHours = document.getElementById('add_extension_hours').value;
            const instFuncHours = document.getElementById('add_instructional_functions_hours').value;
            const consultationHours = document.getElementById('add_consultation_hours').value;

            const fullName = `${fname} ${minitial ? minitial + '.' : ''} ${lname}${suffix ? ' ' + suffix : ''}`.trim();
            
            // Get department name
            const deptSelect = document.getElementById('add_dept_id');
            const deptName = deptSelect && deptSelect.options[deptSelect.selectedIndex] ? deptSelect.options[deptSelect.selectedIndex].text : '-';
            
            // Program field was removed from form, so set to '-'
            const programName = '-';
            
            // Get rank name
            const rankSelect = document.getElementById('add_rank');
            const rankName = rankSelect && rankSelect.options[rankSelect.selectedIndex] ? rankSelect.options[rankSelect.selectedIndex].text : '-';
            
            // Get designation name
            const designationSelect = document.getElementById('add_designation');
            const designationName = designationSelect && designationSelect.options[designationSelect.selectedIndex] ? designationSelect.options[designationSelect.selectedIndex].text : '-';
            
            document.getElementById('reviewName').textContent = fullName || '-';
            document.getElementById('reviewEmail').textContent = email || '-';
            document.getElementById('reviewPhone').textContent = phone || '-';
            document.getElementById('reviewUsername').textContent = username || '-';
            document.getElementById('reviewRole').textContent = roleName;
            document.getElementById('reviewStatus').textContent = status;
            document.getElementById('reviewDepartment').textContent = deptName;
            document.getElementById('reviewProgram').textContent = programName;
            document.getElementById('reviewRank').textContent = rankName;
            document.getElementById('reviewDesignation').textContent = designationName;
            document.getElementById('reviewAdminHours').textContent = adminHours || '0';
            document.getElementById('reviewInstructionHours').textContent = instructionHours || '0';
            document.getElementById('reviewResearchHours').textContent = researchHours || '0';
            document.getElementById('reviewExtensionHours').textContent = extensionHours || '0';
            document.getElementById('reviewInstFuncHours').textContent = instFuncHours || '0';
            document.getElementById('reviewConsultationHours').textContent = consultationHours || '0';
        }
    })();
    </script>
</body>
</html>