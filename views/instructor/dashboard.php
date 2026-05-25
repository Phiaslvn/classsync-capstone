<?php
/**
 * Instructor Dashboard - Matches Admin/Moderator Dashboard Structure
 * Same appearance as admin and moderator dashboards with instructor-specific functionality
 */

// Include session configuration first
require_once __DIR__ . '/../../config/session.php';

// Include security middleware
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Require login (instructors are assigned User role)
requireLogin();

// Check if user has User/Instructor role
$userRole = getUserRole();
if (!$userRole || (strtolower($userRole['role_name']) !== 'user' && strtolower($userRole['role_name']) !== 'instructor')) {
    // Redirect to public index if not instructor
    header("Location: ../../public/index.php");
    exit();
}

// Include the dashboard controller
require_once __DIR__ . '/../../admin/controllers/dashboard_controller.php';

// Get user data from controller
$userDisplayData = $dashboardController->getUserDisplayData();
$profileData = $dashboardController->getUserProfileData();
$jsUserData = $dashboardController->getJavaScriptUserData();

// Set JavaScript variables
$currentUserId = $jsUserData['acc_id'];
$currentDeptId = $jsUserData['dept_id'];

// Calculate base URL for shared resources
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /views/instructor/dashboard.php

// More reliable method: Find 'views' in path and get everything before it
$pathParts = explode('/', trim($scriptPath, '/'));
$viewsIndex = array_search('views', $pathParts);

if ($viewsIndex !== false) {
    // Get all parts before 'views'
    $rootParts = array_slice($pathParts, 0, $viewsIndex);
    $basePath = '/' . implode('/', $rootParts);
} else {
    // Fallback: go up 3 levels
    $basePath = dirname(dirname(dirname($scriptPath)));
}

// Normalize base path
if (empty($basePath) || $basePath === '/') {
    $basePath = '/';
} else {
    $basePath = rtrim($basePath, '/') . '/';
}

$baseUrl = $protocol . '://' . $host . $basePath;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - EVSU OCC Scheduling System</title>
    
    <!-- Bootstrap CSS -->
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link href="/assets/css/sweetalert2.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="/assets/css/dataTables.bootstrap5.min.css" />
    
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
        
        /* ============================================
           MOBILE RESPONSIVE SCHEDULE STYLES
           Enhanced for instructor dashboard mobile view
           ============================================ */
        
        /* Mobile View Toggle Button */
        .mobile-view-toggle {
            display: none;
            margin-bottom: 1rem;
        }
        
        .mobile-view-toggle .btn-group {
            width: 100%;
        }
        
        .mobile-view-toggle .btn {
            flex: 1;
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-radius: 0;
        }
        
        .mobile-view-toggle .btn:first-child {
            border-radius: 8px 0 0 8px;
        }
        
        .mobile-view-toggle .btn:last-child {
            border-radius: 0 8px 8px 0;
        }
        
        .mobile-view-toggle .btn.active {
            background-color: #800000;
            border-color: #800000;
            color: white;
        }
        
        /* Mobile Schedule Cards Container */
        .mobile-schedule-container {
            display: none;
        }
        
        /* Mobile Day Accordion */
        .mobile-day-accordion {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .mobile-day-header {
            background: linear-gradient(135deg, #800000 0%, #990000 100%);
            color: white;
            padding: 1rem 1.25rem;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            width: 100%;
            text-align: left;
        }
        
        .mobile-day-header:hover {
            background: linear-gradient(135deg, #990000 0%, #b30000 100%);
        }
        
        .mobile-day-header.collapsed {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }
        
        .mobile-day-header .schedule-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .mobile-day-header .chevron-icon {
            transition: transform 0.3s ease;
        }
        
        .mobile-day-header.collapsed .chevron-icon {
            transform: rotate(-90deg);
        }
        
        .mobile-day-content {
            background: #f8f9fa;
            padding: 0;
        }
        
        /* Mobile Schedule Card */
        .mobile-schedule-card {
            background: white;
            border-left: 5px solid #800000;
            margin: 0.75rem;
            border-radius: 0 12px 12px 0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .mobile-schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }
        
        .mobile-schedule-card.overtime-card {
            background: #fff5f5;
            border-left-color: #dc3545;
        }
        
        .mobile-schedule-card-header {
            background: linear-gradient(135deg, #800000 0%, #990000 100%);
            color: white;
            padding: 0.875rem 1rem;
        }
        
        .mobile-schedule-card.overtime-card .mobile-schedule-card-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .mobile-schedule-card-header .subject-code {
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mobile-schedule-card-header .subject-code::before {
            content: '📚';
            font-size: 1rem;
        }
        
        .mobile-schedule-card-header .section-name {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .mobile-schedule-card-body {
            padding: 1rem;
        }
        
        .mobile-schedule-card-body .info-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .mobile-schedule-card-body .info-row:last-child {
            margin-bottom: 0;
        }
        
        .mobile-schedule-card-body .info-icon {
            width: 28px;
            height: 28px;
            min-width: 28px;
            background: #f8f9fa;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }
        
        .mobile-schedule-card-body .info-content {
            flex: 1;
        }
        
        .mobile-schedule-card-body .info-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.125rem;
        }
        
        .mobile-schedule-card-body .info-value {
            font-weight: 600;
            color: #212529;
        }
        
        .mobile-schedule-card-footer {
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e9ecef;
        }
        
        .mobile-schedule-card-footer .type-badge {
            background: #800000;
            color: white;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .mobile-schedule-card.overtime-card .mobile-schedule-card-footer .type-badge {
            background: #dc3545;
        }
        
        .mobile-schedule-card-footer .overtime-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.625rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        /* Empty Day State */
        .mobile-empty-day {
            padding: 2rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        
        .mobile-empty-day i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }
        
        .mobile-empty-day p {
            margin: 0;
            font-size: 0.95rem;
        }
        
        /* Mobile Responsive Breakpoints */
        @media (max-width: 991.98px) {
            .mobile-view-toggle {
                display: block;
            }
            
            /* Hide desktop calendar by default on mobile, show mobile view */
            .calendar-container.mobile-hidden {
                display: none !important;
            }
            
            .mobile-schedule-container.mobile-visible {
                display: block !important;
            }
            
            /* Class selector improvements for mobile */
            .class-selector-container {
                padding: 0.5rem;
            }
            
            .class-selector-wrapper {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .class-buttons-container {
                width: 100%;
                justify-content: flex-start;
                padding-bottom: 0.5rem;
            }
            
            .class-btn {
                padding: 0.625rem 1rem;
                font-size: 0.85rem;
                min-width: 90px;
            }
            
            .class-print-btn,
            .class-option-btn {
                width: auto;
                flex-shrink: 0;
            }
            
            /* Dashboard card adjustments */
            .dashboard-card {
                padding: 0.875rem !important; /* Reduced from 1rem */
                margin-bottom: 1rem;
            }
            
            /* Reduce container-fluid padding on mobile */
            .main-content .container-fluid {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            /* Reduce main-content padding on mobile */
            .main-content {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            /* Reduce Bootstrap column padding on mobile */
            .main-content .row {
                margin-left: -0.25rem !important;
                margin-right: -0.25rem !important;
            }
            
            .main-content .row > [class*="col-"] {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }
            
            .dashboard-card h2 {
                font-size: 1.25rem;
            }
            
            /* Table responsive improvements */
            #classTimeLoadTable,
            #overtimeTable {
                font-size: 0.75rem;
            }
            
            #classTimeLoadTable th,
            #classTimeLoadTable td,
            #overtimeTable th,
            #overtimeTable td {
                padding: 0.5rem 0.375rem;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 767.98px) {
            /* Further mobile optimizations */
            .mobile-day-header {
                padding: 0.875rem 1rem;
                font-size: 0.95rem;
            }
            
            .mobile-schedule-card {
                margin: 0.5rem;
            }
            
            .mobile-schedule-card-header {
                padding: 0.75rem;
            }
            
            .mobile-schedule-card-header .subject-code {
                font-size: 1rem;
            }
            
            .mobile-schedule-card-body {
                padding: 0.875rem;
            }
            
            .mobile-schedule-card-body .info-row {
                font-size: 0.9rem;
            }
            
            /* Improve touch targets */
            .class-btn {
                min-height: 44px;
                min-width: 80px;
            }
            
            .btn {
                min-height: 44px;
            }
        }
        
        @media (max-width: 575.98px) {
            /* Extra small screen optimizations */
            .mobile-schedule-card-body .info-row {
                flex-direction: column;
            }
            
            .mobile-schedule-card-body .info-icon {
                margin-bottom: 0.375rem;
                margin-right: 0;
            }
            
            .mobile-schedule-card-footer {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .d-flex.gap-2 {
                flex-direction: column;
                width: 100%;
            }
            
            .d-flex.gap-2 .btn {
                width: 100%;
            }
        }
        
        /* Smooth scroll indicator for calendar on tablet */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .calendar-container::after {
                content: '← Scroll horizontally to view all days →';
                display: block;
                text-align: center;
                padding: 0.5rem;
                background: linear-gradient(135deg, #800000 0%, #990000 100%);
                color: white;
                font-size: 0.8rem;
                font-weight: 600;
                border-radius: 0 0 8px 8px;
                margin-top: -1px;
            }
        }
        
        /* Print styles - always show full calendar */
        @media print {
            .mobile-view-toggle,
            .mobile-schedule-container {
                display: none !important;
            }
            
            .calendar-container {
                display: block !important;
                overflow: visible !important;
            }
            
            .calendar-grid {
                min-width: 100% !important;
                width: 100% !important;
            }
        }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/evsu-logo.png">
</head>
<body>
    <!-- Include Header -->
    <?php include __DIR__ . '/../../includes/dashboard/dashboard_header.php'; ?>
    
    <!-- Prepare sidebar variables -->
    <?php
    $username = $userDisplayData['username'];
    $roleName = $userDisplayData['role_name'];
    $dept_name = $userDisplayData['dept_name'];
    // Fix profile image path for instructor dashboard - convert to absolute URL
    $profileImagePath = $profileData['profile_picture'];
    
    // If it's not a data URI and not an absolute URL, convert to absolute URL
    if ($profileImagePath && !str_starts_with($profileImagePath, 'data:') && !str_starts_with($profileImagePath, 'http')) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Calculate base path (root of the application)
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $pathParts = explode('/', trim($scriptPath, '/'));
        $viewsIndex = array_search('views', $pathParts);
        
        if ($viewsIndex !== false) {
            $rootParts = array_slice($pathParts, 0, $viewsIndex);
            $basePath = empty($rootParts) ? '/' : '/' . implode('/', $rootParts) . '/';
        } else {
            $basePath = '/';
        }
        
        // Clean the profile path - remove ../ and normalize
        $cleanPath = $profileImagePath;
        $cleanPath = str_replace('../', '', $cleanPath);
        $cleanPath = ltrim($cleanPath, '/');
        
        // Ensure path starts with 'public/' if it's an assets path
        if (str_starts_with($cleanPath, 'assets/')) {
            $cleanPath = 'public/' . $cleanPath;
        }
        
        // Build absolute URL
        $profileImage = $protocol . '://' . $host . $basePath . $cleanPath;
    } else {
        $profileImage = $profileImagePath;
    }
    ?>
    
    <!-- Include Unified Sidebar -->
    <?php include __DIR__ . '/../../includes/layout/sidebar_base.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Dashboard Overview Tab -->
            <div id="overview" class="tab-content">
                <?php include __DIR__ . '/../../admin/views/components/dashboard_overview.php'; ?>
            </div>
            
            <!-- Profile Tab -->
            <div id="profile" class="tab-content" style="display: none;">
                <?php include __DIR__ . '/../../admin/views/components/profile_management.php'; ?>
            </div>
            
            <!-- My Schedules Tab -->
            <div id="schedule" class="tab-content" style="display: none;">
                <?php include __DIR__ . '/../../admin/views/components/schedule_management.php'; ?>
            </div>
            
            <!-- Reports Tab -->
            <div id="reports" class="tab-content" style="display: none;">
                <?php include __DIR__ . '/../../admin/views/components/reports.php'; ?>
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
    
    <!-- Admin Dashboard JS (required for dashboard overview component) -->
    <script src="/admin/assets/js/admin_dashboard.js?v=<?php echo time(); ?>"></script>
    <script src="/admin/assets/js/room_access_grants.js?v=<?php echo time(); ?>"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Set JavaScript variables from PHP
        window.currentUserId = <?= json_encode($jsUserData['acc_id']) ?>;
        window.currentDeptId = <?= json_encode($jsUserData['dept_id']) ?>;
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Instructor Dashboard initializing...');
            console.log('Current user ID:', window.currentUserId);
            console.log('Current dept ID:', window.currentDeptId);
            
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
            
            // Remove any existing error banners and toasts that might have been created before override
            removeDashboardErrorBanners();
            removeErrorToasts();
            
            // Test connection first
            testConnection();
            
            loadDashboardData();
            
            // Set up tab switching
            setupTabSwitching();
        });
        
        // Override testConnection to use correct path for instructor dashboard
        function testConnection() {
            console.log('Testing connection...');
            fetch('../../instructor/reports/get_basic_stats.php')
                .then(response => {
                    console.log('Connection test response status:', response.status);
                    if (response.ok) {
                        console.log('✅ Connection test successful');
                    } else {
                        console.log('❌ Connection test failed:', response.status);
                    }
                })
                .catch(error => {
                    console.log('❌ Connection test error:', error);
                });
        }
        
        // Note: loadDashboardData and loadBasicStats are overridden after admin.js loads
        
        // Override the updateProfile function to use correct path
        function updateProfile() {
            console.log('updateProfile function called');
            const form = document.getElementById('profileForm');
            if (!form) {
                console.error('Profile form not found');
                return;
            }

            const formData = new FormData(form);
            const newPassword = (formData.get('new_password') || '').toString();
            const currentPassword = (formData.get('current_password') || '').toString();
            const confirmPassword = (formData.get('confirm_password') || '').toString();

            if (newPassword) {
                if (!currentPassword) {
                    Swal.fire({ icon: 'warning', title: 'Validation', text: 'Current password is required to change password.', confirmButtonColor: '#800000' });
                    document.getElementById('current_password')?.focus();
                    return;
                }
                if (newPassword !== confirmPassword) {
                    Swal.fire({ icon: 'warning', title: 'Validation', text: 'New passwords do not match.', confirmButtonColor: '#800000' });
                    document.getElementById('confirm_password')?.focus();
                    return;
                }
                if (newPassword.length < 6) {
                    Swal.fire({ icon: 'warning', title: 'Validation', text: 'New password must be at least 6 characters.', confirmButtonColor: '#800000' });
                    document.getElementById('new_password')?.focus();
                    return;
                }
            }
            
            // Show loading
            Swal.fire({
                title: 'Updating Profile...',
                text: 'Please wait while we update your profile.',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            console.log('Sending fetch request to update_profile.php');
            // Use shared profile update endpoint (same as admin/moderator)
            const baseUrl = <?= json_encode($baseUrl) ?>;
            const cleanBaseUrl = baseUrl.replace(/\/+$/, ''); // Remove trailing slashes
            const updatePath = cleanBaseUrl + '/shared/profile/update_profile.php';
            console.log('Update path:', updatePath);
            fetch(updatePath, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response.status, response.statusText);
                return response.text(); // Get text first to see raw response
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON data:', data);
                    Swal.close();
                    if (data.success) {
                        const cp = document.getElementById('current_password');
                        const np = document.getElementById('new_password');
                        const cf = document.getElementById('confirm_password');
                        if (cp) cp.value = '';
                        if (np) np.value = '';
                        if (cf) cf.value = '';
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'Profile updated successfully!',
                            confirmButtonColor: '#800000',
                            confirmButtonText: 'OK',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: true
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message || 'Failed to update profile.',
                            confirmButtonColor: '#800000',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Invalid response from server: ' + text.substring(0, 100),
                        confirmButtonColor: '#800000',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while updating your profile: ' + error.message,
                    confirmButtonColor: '#800000',
                    confirmButtonText: 'OK'
                });
            });
        }
        
        // Base URL from PHP (more reliable than JavaScript calculation)
        const baseUrl = <?= json_encode($baseUrl) ?>;
        console.log('Base URL from PHP:', baseUrl);
        
        // Helper function to get base path for shared resources
        function getSharedProfilePath(filename) {
            // Use PHP-generated base URL for reliability
            const fullUrl = baseUrl + '/shared/profile/' + filename;
            console.log('getSharedProfilePath - Full URL:', fullUrl);
            return fullUrl;
        }
        
        // Profile Picture Management Functions
        // Override the uploadProfilePicture function from admin.js to use correct path
        window.uploadProfilePicture = function(input) {
            if (!input.files || !input.files.length) return;
            
            const file = input.files[0];
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: 'Please select a valid image file (JPEG, PNG, or WebP).',
                    confirmButtonColor: '#800000',
                    confirmButtonText: 'OK'
                });
                input.value = '';
                return;
            }
            
            // Validate file size (5MB max)
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Please select an image smaller than 5MB.',
                    confirmButtonColor: '#800000',
                    confirmButtonText: 'OK'
                });
                input.value = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('profile_picture', file);
            
            // Show loading
            Swal.fire({
                title: 'Uploading Picture...',
                text: 'Please wait while we upload your profile picture.',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const uploadPath = getSharedProfilePath('upload_profile_picture.php');
            console.log('Current page path:', window.location.pathname);
            console.log('Final upload path:', uploadPath);
            
            fetch(uploadPath, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Upload response status:', response.status);
                console.log('Upload response headers:', response.headers);
                
                // Try to parse as JSON, but handle non-JSON responses
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (!response.ok) {
                            return Promise.reject(data);
                        }
                        return data;
                    });
                } else {
                    // If not JSON, get text response
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        return Promise.reject({
                            success: false,
                            message: 'Server returned non-JSON response. Status: ' + response.status + '. Response: ' + text.substring(0, 200)
                        });
                    });
                }
            })
            .then(data => {
                Swal.close();
                console.log('Upload response data:', data);
                
                if (data.success) {
                    // Update profile picture immediately without reload
                    const imagePath = data.image_path || data.profile_picture;
                    if (imagePath) {
                        const profilePic = document.getElementById('profilePicture');
                        if (profilePic) {
                            profilePic.src = '../../public/' + imagePath + '?t=' + new Date().getTime();
                        }
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message || 'Profile picture uploaded successfully!',
                        confirmButtonColor: '#800000',
                        confirmButtonText: 'OK',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to upload profile picture.',
                        confirmButtonColor: '#800000',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Upload error details:', error);
                console.error('Error stack:', error.stack);
                
                let errorMessage = 'An error occurred while uploading your profile picture.';
                if (error.message) {
                    errorMessage = error.message;
                } else if (error.success === false && error.message) {
                    errorMessage = error.message;
                } else if (typeof error === 'string') {
                    errorMessage = error;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed!',
                    html: '<div style="text-align: left;">' +
                          '<strong>Error Details:</strong><br>' +
                          errorMessage +
                          '<br><br><small>Check the browser console (F12) for more details.</small>' +
                          '</div>',
                    confirmButtonColor: '#800000',
                    confirmButtonText: 'OK',
                    width: '500px'
                });
            });
        }
        
        function removeProfilePicture() {
            Swal.fire({
                title: 'Remove Profile Picture?',
                text: 'Are you sure you want to remove your profile picture?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#800000',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, remove it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Removing Picture...',
                        text: 'Please wait while we remove your profile picture.',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const removePath = getSharedProfilePath('remove_profile_picture.php');
                    console.log('Remove profile picture path:', removePath);
                    fetch(removePath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ acc_id: window.currentUserId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message || 'Profile picture removed successfully!',
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: true
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message || 'Failed to remove profile picture.',
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while removing your profile picture.',
                            confirmButtonColor: '#800000',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        }
        
        function setupTabSwitching() {
            // Add click event listeners to all tab buttons
            const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
            tabButtons.forEach(button => {
                button.addEventListener('shown.bs.tab', function(event) {
                    const targetTab = event.target.getAttribute('data-bs-target');
                        console.log('Tab switched to:', targetTab);
                    
                    // Load specific data based on the active tab
                    if (targetTab === '#schedule') {
                        console.log('Loading schedule data...');
                        // Wait a bit for the schedule component to be ready
                        setTimeout(() => {
                            // For instructors, just load class selector and schedules directly (no filters needed)
                            // The backend automatically filters by instructor ID
                            if (typeof loadClassSelector === 'function') {
                                loadClassSelector();
                            }
                            if (typeof loadSchedules === 'function') {
                                loadSchedules();
                            }
                        }, 300);
                    }
                });
            });
        }
        
        // Function to refresh dashboard
        function refreshDashboard() {
            console.log('Refreshing dashboard...');
            loadDashboardData();
        }
        
        // Function to show content sections
        function showContent(contentId, element) {
            // Hide all content sections
            document.querySelectorAll('.tab-content').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected content
            const content = document.getElementById(contentId);
            if (content) {
                content.style.display = 'block';
            }
            
            // Update navigation active states
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            if (element) {
                element.classList.add('active');
            }
        }
        
        // Pre-emptively override showDashboardError before admin.js loads to prevent errors from showing
        function showDashboardError(message) {
            console.log('Dashboard error (instructor pre-emptive override - suppressed):', message);
            // Don't show error banner for instructor dashboard
            // Remove any existing error banners that might have been created
            removeDashboardErrorBanners();
        }
        
        // Function to remove all dashboard error banners
        function removeDashboardErrorBanners() {
            const errorSelectors = [
                '.alert-danger',
                '.alert.alert-danger',
                '[class*="alert-danger"]'
            ];
            
            errorSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(error => {
                    const text = error.textContent || error.innerText || '';
                    if (text.includes('Failed to load dashboard data') || 
                        text.includes('Error: Failed to load dashboard data') ||
                        text.includes('Failed to load any dashboard data')) {
                        error.remove();
                    }
                });
            });
        }
        
        // Set up a MutationObserver to remove error banners as they're added
        if (typeof MutationObserver !== 'undefined') {
            const errorObserver = new MutationObserver(function(mutations) {
                removeDashboardErrorBanners();
            });
            
            // Start observing when DOM is ready
            if (document.body) {
                errorObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    errorObserver.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                });
            }
        }
        
        // Also set up an interval to periodically remove error banners (fallback)
        setInterval(removeDashboardErrorBanners, 500);
        
        // Override showToast to suppress form data errors for instructors
        // This needs to be set up before schedule_management.js loads
        (function() {
            const originalShowToast = window.showToast;
            window.showToast = function(icon, title) {
                // Suppress error toasts related to form data loading for instructors
                if (icon === 'error' && title && (
                    title.includes('An error occurred while loading form data') ||
                    title.includes('Failed to load form data')
                )) {
                    console.warn('Form data error suppressed for instructor:', title);
                    return; // Don't show the toast
                }
                // For other toasts, use the original function if it exists
                if (originalShowToast && typeof originalShowToast === 'function') {
                    return originalShowToast.call(this, icon, title);
                }
                // Fallback: use SweetAlert2 directly
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                    Toast.fire({ icon: icon, title: title });
                }
            };
        })();
        
        // Function to remove existing error toasts
        function removeErrorToasts() {
            // Remove SweetAlert2 toasts
            const swalContainers = document.querySelectorAll('.swal2-toast');
            swalContainers.forEach(toast => {
                const title = toast.textContent || toast.innerText || '';
                if (title.includes('An error occurred while loading form data') ||
                    title.includes('Failed to load form data')) {
                    toast.remove();
                }
            });
        }
        
        // Remove error toasts periodically
        setInterval(removeErrorToasts, 300);
        
        // Note: updateDashboardUI, updateStatElement, and other functions are overridden after admin.js loads
    </script>
    
    <!-- Override fetch BEFORE admin.js loads to intercept all API calls -->
    <script>
        // Override fetch to redirect relative API paths to correct instructor endpoints
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(url, options) {
                // Convert URL to string if it's a Request object
                let urlString = typeof url === 'string' ? url : (url.url || url.toString());
                
                // If it's a relative path to get_dashboard_data.php, redirect to instructor endpoint
                if (urlString.includes('get_dashboard_data.php') && !urlString.includes('../') && !urlString.includes('http') && !urlString.includes('instructor')) {
                    console.log('Redirecting get_dashboard_data.php to instructor endpoint');
                    urlString = '../../instructor/reports/get_dashboard_data.php';
                    url = urlString;
                }
                // If it's a relative path to get_profile_data.php, redirect to api endpoint
                if (urlString.includes('get_profile_data.php') && !urlString.includes('../') && !urlString.includes('http') && !urlString.includes('api')) {
                    console.log('Redirecting get_profile_data.php to api endpoint');
                    urlString = '../../api/reports/get_profile_data.php';
                    url = urlString;
                }
                // If it's a relative path to get_accounts.php, suppress it for instructors
                if (urlString.includes('get_accounts.php') && !urlString.includes('../') && !urlString.includes('http')) {
                    console.log('get_accounts.php call suppressed for instructors');
                    return Promise.resolve(new Response(JSON.stringify({success: true, accounts: []}), {
                        status: 200,
                        headers: {'Content-Type': 'application/json'}
                    }));
                }
                return originalFetch.call(this, url, options);
            };
        })();
    </script>
    
    <!-- Pre-emptively override loadProfileData BEFORE admin.js loads to prevent it from breaking images -->
    <script>
        // Override loadProfileData immediately to prevent admin.js from using relative paths
        (function() {
            const baseUrl = <?= json_encode($baseUrl) ?>;
            
            window.loadProfileData = function() {
                console.log('Loading profile data for instructor (override)...');
                
                // Don't actually load anything - the profile data is already set by PHP
                // This override just prevents admin.js from breaking the image
                return Promise.resolve();
            };
            
            console.log('✅ loadProfileData function pre-emptively overridden for instructor dashboard');
        })();
    </script>
    
    <!-- Prevent admin.js from resetting tabs after user interaction -->
    <script>
        // Track user-initiated tab changes
        let userSelectedTab = null;
        let userHasInteracted = false;
        let adminJsInitialized = false;
        
        // Monitor clicks on nav links to detect user selections
        document.addEventListener('click', function(e) {
            const navLink = e.target.closest('.nav-link');
            if (navLink) {
                const onclick = navLink.getAttribute('onclick');
                if (onclick) {
                    const match = onclick.match(/showTab\s*\(\s*['"]([^'"]+)['"]/);
                    if (match) {
                        userSelectedTab = match[1];
                        userHasInteracted = true;
                        console.log('✅ User clicked on tab:', userSelectedTab, 'Element:', navLink);
                        
                        // Ensure the element reference is available for showTab
                        // Store it temporarily so showTab can access it
                        navLink._clickedTab = userSelectedTab;
                        
                        // Save to localStorage immediately
                        try {
                            localStorage.setItem('admin_active_tab', userSelectedTab);
                        } catch (e) {}
                        
                        // Small delay to ensure showTab gets called with the element
                        setTimeout(() => {
                            // Verify tab switch happened
                            const targetTab = document.getElementById(userSelectedTab);
                            if (targetTab && targetTab.style.display === 'none') {
                                console.warn('⚠️ Tab switch may have failed, forcing display');
                                targetTab.style.display = 'block';
                                targetTab.style.visibility = 'visible';
                            }
                        }, 100);
                    }
                }
            }
        }, true);
    </script>
    
    <!-- Override showTab BEFORE admin.js loads -->
    <script>
        // Store original showTab if it exists
        let originalShowTab = window.showTab;
        
        // Track last tab switch to prevent infinite loops
        let lastTabSwitch = { tabId: null, timestamp: 0 };
        const TAB_SWITCH_DEBOUNCE = 100; // milliseconds
        
        // Mobile Detection Utility
        function isMobileViewport() {
            return window.innerWidth <= 991.98; // Bootstrap's md breakpoint
        }

        // Override showTab to track and prevent unwanted resets
        window.showTab = function(tabId, element) {
            // Prevent rapid repeated calls (infinite loop protection)
            const now = Date.now();
            if (lastTabSwitch.tabId === tabId && (now - lastTabSwitch.timestamp) < TAB_SWITCH_DEBOUNCE) {
                console.log('🚫 Blocking duplicate showTab call for:', tabId, 'within', (now - lastTabSwitch.timestamp), 'ms');
                return; // Exit early to prevent loop
            }
            lastTabSwitch = { tabId: tabId, timestamp: now };
            
            // Mobile Responsiveness: Hide overview on mobile when switching to other tabs
            const isMobile = isMobileViewport();
            const overviewTab = document.getElementById('overview');
            
            // If this is a user click (element is provided), track it and ALWAYS allow the switch
            if (element) {
                userSelectedTab = tabId;
                userHasInteracted = true;
                console.log('✅ showTab called with user click:', tabId);
                // Save to localStorage
                try {
                    localStorage.setItem('admin_active_tab', tabId);
                } catch (e) {}
                
                // Check if tab is already shown to avoid unnecessary work
                const targetTab = document.getElementById(tabId);
                const isAlreadyActive = targetTab && targetTab.style.display === 'block' && 
                                       element.classList.contains('active');
                
                if (isAlreadyActive) {
                    console.log('ℹ️ Tab already active, skipping switch:', tabId);
                    return; // Already showing, no need to switch
                }
                
                // User clicks should always be allowed - proceed with tab switch
                // Always perform the tab switch first
                console.log('🔄 Switching to tab:', tabId);
                
                // Hide all tab content
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.style.display = 'none';
                    tab.style.visibility = 'hidden';
                    // Remove mobile-specific classes
                    tab.classList.remove('mobile-tab-active', 'mobile-tab-hidden');
                });
                
                // Show target tab
                if (targetTab) {
                    targetTab.style.display = 'block';
                    targetTab.style.visibility = 'visible';
                    targetTab.classList.add('mobile-tab-active');
                    console.log('✅ Tab content shown:', tabId);
                    
                        // On mobile: Hide overview when switching to other tabs, show it when switching back
                        if (isMobile && overviewTab) {
                            if (tabId === 'overview') {
                                // Show overview when switching back to it
                                overviewTab.style.display = 'block';
                                overviewTab.style.visibility = 'visible';
                                overviewTab.classList.remove('mobile-tab-hidden');
                                overviewTab.classList.add('mobile-tab-active');
                            } else {
                                // Hide overview when switching to other tabs
                                overviewTab.style.display = 'none';
                                overviewTab.style.visibility = 'hidden';
                                overviewTab.classList.add('mobile-tab-hidden');
                                overviewTab.classList.remove('mobile-tab-active');
                            }
                        }
                        
                        // Scroll to top of main content on mobile when switching tabs
                        // Use requestAnimationFrame to ensure DOM has updated before scrolling
                        if (isMobile) {
                            // First, force immediate scroll to top (no smooth animation for instant positioning)
                            window.scrollTo({ top: 0, behavior: 'auto' });
                            
                            // Then use requestAnimationFrame to ensure layout has updated
                            requestAnimationFrame(() => {
                                // Wait one more frame to ensure overview is fully hidden from layout
                                requestAnimationFrame(() => {
                                    const mainContent = document.querySelector('.main-content') || document.querySelector('.container-fluid');
                                    if (mainContent) {
                                        // Scroll main content to top
                                        mainContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                        // Also ensure window is at top
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    }
                                });
                            });
                        }
                } else {
                    console.error('❌ Target tab not found:', tabId);
                    return; // Exit if tab doesn't exist
                }
                
                // Update active nav links - be more thorough
                const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link, .nav-link');
                allNavLinks.forEach(link => {
                    link.classList.remove('active');
                    // Also remove any Bootstrap active classes
                    link.classList.remove('show');
                });
                
                // Add active class to clicked element
                if (element) {
                    if (element.classList.contains('nav-link')) {
                        element.classList.add('active');
                        console.log('✅ Active class added to nav-link element');
                    } else {
                        // Try to find the nav-link parent or related element
                        const navLink = element.closest('.nav-link') || 
                                       document.querySelector(`.nav-link[onclick*="${tabId}"]`) ||
                                       document.querySelector(`.nav-link[href="#${tabId}"]`);
                        if (navLink) {
                            navLink.classList.add('active');
                            console.log('✅ Active class added to found nav-link');
                        } else {
                            console.warn('⚠️ Could not find nav-link element for tab:', tabId);
                        }
                    }
                }
                
                // Also call original function if it exists (for any additional logic)
                // BUT only if it won't cause a loop - check if it's different
                if (originalShowTab && typeof originalShowTab === 'function' && originalShowTab !== window.showTab) {
                    try {
                        originalShowTab.call(this, tabId, element);
                    } catch (e) {
                        console.warn('Error calling originalShowTab:', e);
                    }
                }
                return; // Exit early for user clicks
            }
            
            // If user has interacted and admin.js tries to reset to overview AUTOMATICALLY (no element), block it
            if (!element && userHasInteracted && userSelectedTab && tabId === 'overview' && userSelectedTab !== 'overview' && adminJsInitialized) {
                console.log('🚫 Blocking automatic reset to overview, keeping:', userSelectedTab);
                // Restore user's selected tab immediately
                setTimeout(() => {
                    const tabEl = document.getElementById(userSelectedTab);
                    if (tabEl) {
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.style.display = 'none';
                        });
                        tabEl.style.display = 'block';
                        
                        const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
                        allNavLinks.forEach(link => link.classList.remove('active'));
                        const activeLink = document.querySelector(`.nav-link[onclick*="${userSelectedTab}"]`);
                        if (activeLink) {
                            activeLink.classList.add('active');
                        }
                    }
                }, 10);
                return;
            }
            
            // Call original function if it exists
            if (originalShowTab) {
                originalShowTab.call(this, tabId, element);
            } else {
                // Fallback implementation
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.style.display = 'none';
                });
                const targetTab = document.getElementById(tabId);
                if (targetTab) {
                    targetTab.style.display = 'block';
                }
                const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
                allNavLinks.forEach(link => link.classList.remove('active'));
                if (element && element.classList.contains('nav-link')) {
                    element.classList.add('active');
                }
            }
        };
        
        console.log('✅ showTab function overridden for instructor dashboard');
    </script>
    
    <script src="../../public/assets/js/admin.js"></script>
    
    <!-- Password changes use Profile → Update Profile (shared/profile/update_profile.php); block admin.js modal -->
    <script>
        window.changePassword = function () {
            Swal.fire({
                icon: 'info',
                title: 'Change password',
                text: 'Open the Profile tab, enter your current and new password in the optional section, then click Update Profile.',
                confirmButtonColor: '#800000'
            });
        };
    </script>
    
    <!-- Override removeProfilePicture from admin.js AFTER it loads -->
    <script>
        // Override removeProfilePicture function from admin.js to use correct path for instructor
        (function() {
            const baseUrl = <?= json_encode($baseUrl) ?>;
            
            function getSharedProfilePath(filename) {
                const cleanBaseUrl = baseUrl.replace(/\/+$/, '');
                return cleanBaseUrl + '/shared/profile/' + filename;
            }
            
            window.removeProfilePicture = function() {
                Swal.fire({
                    title: 'Remove Profile Picture?',
                    text: 'Are you sure you want to remove your profile picture?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#800000',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, remove it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Removing Picture...',
                            text: 'Please wait while we remove your profile picture.',
                            icon: 'info',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        const removePath = getSharedProfilePath('remove_profile_picture.php');
                        console.log('Remove profile picture path (after admin.js):', removePath);
                        fetch(removePath, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({})
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            Swal.close();
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message || 'Profile picture removed successfully!',
                                    confirmButtonColor: '#800000',
                                    confirmButtonText: 'OK',
                                    timer: 3000,
                                    timerProgressBar: true,
                                    showConfirmButton: true
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.message || 'Failed to remove profile picture.',
                                    confirmButtonColor: '#800000',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.close();
                            console.error('Remove profile picture error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'An error occurred while removing your profile picture: ' + error.message,
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK'
                            });
                        });
                    }
                });
            };
            console.log('✅ removeProfilePicture function overridden after admin.js loads');
        })();
    </script>
    
    <!-- Override admin.js's showTab AFTER it loads and watch for tab changes -->
    <script>
        // After admin.js loads, override its showTab again
        (function() {
            // Store admin.js's showTab
            originalShowTab = window.showTab;
            adminJsInitialized = true;
            
            // Override showTab again to ensure our version is used
            window.showTab = function(tabId, element) {
                // Prevent rapid repeated calls (infinite loop protection)
                const now = Date.now();
                if (lastTabSwitch.tabId === tabId && (now - lastTabSwitch.timestamp) < TAB_SWITCH_DEBOUNCE) {
                    console.log('🚫 Blocking duplicate showTab call (admin.js override) for:', tabId);
                    return; // Exit early to prevent loop
                }
                lastTabSwitch = { tabId: tabId, timestamp: now };
                
                // Mobile Responsiveness: Hide overview on mobile when switching to other tabs
                const isMobile = isMobileViewport();
                const overviewTab = document.getElementById('overview');
                
                // If this is a user click, track it and ALWAYS allow the switch
                if (element) {
                    userSelectedTab = tabId;
                    userHasInteracted = true;
                    console.log('✅ showTab (admin.js override) called with user click:', tabId);
                    try {
                        localStorage.setItem('admin_active_tab', tabId);
                    } catch (e) {}
                    
                    // Check if tab is already shown to avoid unnecessary work
                    const targetTab = document.getElementById(tabId);
                    const isAlreadyActive = targetTab && targetTab.style.display === 'block' && 
                                           element.classList.contains('active');
                    
                    if (isAlreadyActive) {
                        console.log('ℹ️ Tab already active (admin.js override), skipping switch:', tabId);
                        return; // Already showing, no need to switch
                    }
                    
                    // User clicks should always be allowed - proceed with tab switch
                    // Always perform the tab switch first
                    console.log('🔄 Switching to tab (admin.js override):', tabId);
                    
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
                    
                    // Hide all tab content
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.style.display = 'none';
                        tab.style.visibility = 'hidden';
                        // Remove mobile-specific classes
                        tab.classList.remove('mobile-tab-active', 'mobile-tab-hidden');
                    });
                    
                    // Show target tab
                    if (targetTab) {
                        targetTab.style.display = 'block';
                        targetTab.style.visibility = 'visible';
                        targetTab.classList.add('mobile-tab-active');
                        console.log('✅ Tab content shown:', tabId);
                        
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
                    } else {
                        console.error('❌ Target tab not found:', tabId);
                        return; // Exit if tab doesn't exist
                    }
                    
                    // Update active nav links - be more thorough
                    const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link, .nav-link');
                    allNavLinks.forEach(link => {
                        link.classList.remove('active');
                        link.classList.remove('show');
                    });
                    
                    // Add active class to clicked element
                    if (element) {
                        if (element.classList.contains('nav-link')) {
                            element.classList.add('active');
                            console.log('✅ Active class added to nav-link element');
                        } else {
                            // Try to find the nav-link parent or related element
                            const navLink = element.closest('.nav-link') || 
                                           document.querySelector(`.nav-link[onclick*="${tabId}"]`) ||
                                           document.querySelector(`.nav-link[href="#${tabId}"]`);
                            if (navLink) {
                                navLink.classList.add('active');
                                console.log('✅ Active class added to found nav-link');
                            } else {
                                console.warn('⚠️ Could not find nav-link element for tab:', tabId);
                            }
                        }
                    }
                    
                    // Also call original function if it exists (for any additional logic)
                    // BUT only if it won't cause a loop - check if it's different
                    if (originalShowTab && typeof originalShowTab === 'function' && originalShowTab !== window.showTab) {
                        try {
                            originalShowTab.call(this, tabId, element);
                        } catch (e) {
                            console.warn('Error calling originalShowTab:', e);
                        }
                    }
                    return; // Exit early for user clicks
                }
                
                // Block automatic resets to overview after user interaction (only if no element provided)
                if (!element && userHasInteracted && userSelectedTab && tabId === 'overview' && userSelectedTab !== 'overview') {
                    console.log('🚫 Blocking automatic reset to overview, keeping:', userSelectedTab);
                    setTimeout(() => {
                        const tabEl = document.getElementById(userSelectedTab);
                        if (tabEl) {
                            document.querySelectorAll('.tab-content').forEach(tab => {
                                tab.style.display = 'none';
                            });
                            tabEl.style.display = 'block';
                            
                            const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
                            allNavLinks.forEach(link => link.classList.remove('active'));
                            const activeLink = document.querySelector(`.nav-link[onclick*="${userSelectedTab}"]`);
                            if (activeLink) {
                                activeLink.classList.add('active');
                            }
                        }
                    }, 10);
                    return;
                }
                
                // Call admin.js's original showTab
                if (originalShowTab) {
                    originalShowTab.call(this, tabId, element);
                }
            };
            
            // Watch for tab visibility changes and restore user's tab if needed
            // Only restore if overview was shown automatically (not by user click)
            const observer = new MutationObserver(function(mutations) {
                // Check if overview link is active - if it is, user clicked it, so don't restore
                const overviewLink = document.querySelector('.nav-link[onclick*="overview"], .nav-link[data-tab="overview"]');
                const isOverviewLinkActive = overviewLink && overviewLink.classList.contains('active');
                
                // Only restore if user selected a non-overview tab AND overview link is not active (meaning it was automatic)
                if (userHasInteracted && userSelectedTab && userSelectedTab !== 'overview' && !isOverviewLinkActive) {
                    const overviewTab = document.getElementById('overview');
                    const userTab = document.getElementById(userSelectedTab);
                    
                    // If overview is visible but user selected a different tab (and didn't click overview), restore it
                    if (overviewTab && overviewTab.style.display === 'block' && userTab) {
                        console.log('🔄 Detected automatic overview showing, restoring user tab:', userSelectedTab);
                        setTimeout(() => {
                            document.querySelectorAll('.tab-content').forEach(tab => {
                                tab.style.display = 'none';
                            });
                            userTab.style.display = 'block';
                            
                            const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
                            allNavLinks.forEach(link => link.classList.remove('active'));
                            const activeLink = document.querySelector(`.nav-link[onclick*="${userSelectedTab}"]`);
                            if (activeLink) {
                                activeLink.classList.add('active');
                            }
                        }, 50);
                    }
                }
            });
            
            // Observe all tab content elements
            document.querySelectorAll('.tab-content').forEach(tab => {
                observer.observe(tab, {
                    attributes: true,
                    attributeFilter: ['style']
                });
            });
            
            // Also restore tab periodically in case something else resets it
            // Only restore if overview was shown automatically (not by user click)
            setInterval(() => {
                // Check if overview link is active - if it is, user clicked it, so don't restore
                const overviewLink = document.querySelector('.nav-link[onclick*="overview"], .nav-link[data-tab="overview"]');
                const isOverviewLinkActive = overviewLink && overviewLink.classList.contains('active');
                
                // Only restore if user selected a non-overview tab AND overview link is not active
                if (userHasInteracted && userSelectedTab && userSelectedTab !== 'overview' && !isOverviewLinkActive) {
                    const tabEl = document.getElementById(userSelectedTab);
                    const visibleTab = document.querySelector('.tab-content[style*="block"]');
                    
                    if (tabEl && visibleTab && visibleTab.id === 'overview') {
                        console.log('🔄 Periodic check: Restoring user-selected tab:', userSelectedTab);
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.style.display = 'none';
                        });
                        tabEl.style.display = 'block';
                        
                        const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
                        allNavLinks.forEach(link => link.classList.remove('active'));
                        const activeLink = document.querySelector(`.nav-link[onclick*="${userSelectedTab}"]`);
                        if (activeLink) {
                            activeLink.classList.add('active');
                        }
                    }
                }
            }, 500);
        })();
    </script>
    
    <!-- Override uploadProfilePicture and removeProfilePicture from admin.js to use correct path for instructor -->
    <script>
        // Override removeProfilePicture function from admin.js to use correct path for instructor
        (function() {
            const baseUrl = <?= json_encode($baseUrl) ?>;
            
            function getSharedProfilePathLocal(filename) {
                const cleanBaseUrl = baseUrl.replace(/\/+$/, '');
                return cleanBaseUrl + '/shared/profile/' + filename;
            }
            
            window.removeProfilePicture = function() {
                Swal.fire({
                    title: 'Remove Profile Picture?',
                    text: 'Are you sure you want to remove your profile picture?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#800000',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, remove it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Removing Picture...',
                            text: 'Please wait while we remove your profile picture.',
                            icon: 'info',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        const removePath = getSharedProfilePathLocal('remove_profile_picture.php');
                        console.log('Remove profile picture path (override after admin.js):', removePath);
                    fetch(removePath, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({})
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message || 'Profile picture removed successfully!',
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: true
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message || 'Failed to remove profile picture.',
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Remove profile picture error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while removing your profile picture: ' + error.message,
                            confirmButtonColor: '#800000',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        };
        })();
        
        // Override uploadProfilePicture function from admin.js to use correct path for instructor
        (function() {
            const baseUrl = <?= json_encode($baseUrl) ?>;
            
            window.uploadProfilePicture = function(input) {
                if (!input.files || !input.files[0]) return;
                
                const file = input.files[0];
                
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    return;
                }
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    return;
                }
                
                const formData = new FormData();
                formData.append('profile_picture', file);
                
                // Use correct absolute path for instructor dashboard
                // Remove trailing slash from baseUrl if present, then add path
                const cleanBaseUrl = baseUrl.replace(/\/+$/, ''); // Remove trailing slashes
                const uploadPath = cleanBaseUrl + '/shared/profile/upload_profile_picture.php';
                console.log('Instructor override - Base URL:', baseUrl);
                console.log('Instructor override - Clean Base URL:', cleanBaseUrl);
                console.log('Instructor override - Upload path:', uploadPath);
                
                // Show loading state
                const uploadBtn = input.previousElementSibling;
                const originalText = uploadBtn ? uploadBtn.innerHTML : '';
                if (uploadBtn) {
                    uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
                    uploadBtn.disabled = true;
                }
                
                fetch(uploadPath, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Upload response status:', response.status);
                    if (!response.ok) {
                        return response.json().then(err => Promise.reject(err));
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Upload response data:', data);
                    if (data.success) {
                        // Update profile picture
                        const imagePath = data.image_path || data.profile_picture;
                        const fullImageUrl = baseUrl + '/public/' + imagePath + '?t=' + new Date().getTime();
                        
                        // Update main profile picture in profile management component
                        const profilePic = document.getElementById('profilePicture');
                        if (profilePic) {
                            profilePic.src = fullImageUrl;
                        }
                        
                        // Update sidebar profile pictures (both mobile and desktop) with absolute URL
                        const sidebarProfileImg = document.getElementById('adminProfileImg');
                        if (sidebarProfileImg) {
                            sidebarProfileImg.src = fullImageUrl;
                        }
                        const mobileSidebarProfileImg = document.getElementById('mobileAdminProfileImg');
                        if (mobileSidebarProfileImg) {
                            mobileSidebarProfileImg.src = fullImageUrl;
                        }
                        
                        // Update sidebar profile pictures if functions exist
                        if (typeof updateSidebarProfilePicture === 'function') {
                            updateSidebarProfilePicture(fullImageUrl);
                        }
                        if (typeof updateAdminSidebarProfilePicture === 'function') {
                            updateAdminSidebarProfilePicture(fullImageUrl);
                        }
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message || 'Profile picture uploaded successfully!',
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK',
                                timer: 3000,
                                timerProgressBar: true
                            });
                        } else {
                            alert('Profile picture updated successfully!');
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message || 'Failed to upload profile picture.',
                                confirmButtonColor: '#800000',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert('Error: ' + (data.message || 'Failed to upload profile picture.'));
                        }
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    const errorMessage = error.message || (error.success === false ? error.message : 'An error occurred while uploading your profile picture.');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMessage,
                            confirmButtonColor: '#800000',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Error: ' + errorMessage);
                    }
                })
                .finally(() => {
                    if (uploadBtn) {
                        uploadBtn.innerHTML = originalText;
                        uploadBtn.disabled = false;
                    }
                });
            };
            
            console.log('✅ uploadProfilePicture function overridden for instructor dashboard');
        })();
    </script>
    
    <script src="/assets/js/schedule_management.js?v=<?= time() ?>"></script>
    
    <!-- Override updateAdminSidebarProfilePicture to use absolute URLs -->
    <script>
        // Override updateAdminSidebarProfilePicture function to use absolute URLs for instructor
        (function() {
            const baseUrl = <?= json_encode($baseUrl) ?>;
            
            window.updateAdminSidebarProfilePicture = function(imagePath) {
                // If imagePath is already an absolute URL, use it directly
                // Otherwise, build absolute URL from baseUrl
                let fullImageUrl;
                if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
                    fullImageUrl = imagePath;
                } else {
                    // Remove any relative path prefixes (../../ or ../)
                    let cleanPath = imagePath.replace(/^(\.\.\/)+/g, '').replace(/^\.\//g, '');
                    cleanPath = cleanPath.trim();
                    
                    // Normalize path - remove any double slashes and ensure it starts with 'public/'
                    cleanPath = cleanPath.replace(/\/+/g, '/');
                    if (!cleanPath.startsWith('public/')) {
                        // If path starts with 'assets/', add 'public/' prefix
                        if (cleanPath.startsWith('assets/')) {
                            cleanPath = 'public/' + cleanPath;
                        } else {
                            cleanPath = 'public/' + cleanPath;
                        }
                    }
                    
                    // Remove any remaining '../' patterns that might have been created
                    cleanPath = cleanPath.replace(/\/\.\.\//g, '/').replace(/\/\.\.$/g, '');
                    
                    const cleanBaseUrl = baseUrl.replace(/\/+$/, '');
                    fullImageUrl = cleanBaseUrl + '/' + cleanPath + '?t=' + new Date().getTime();
                }
                
                const desktopImg = document.getElementById('adminProfileImg');
                if (desktopImg) {
                    desktopImg.src = fullImageUrl;
                }
                const mobileImg = document.getElementById('mobileAdminProfileImg');
                if (mobileImg) {
                    mobileImg.src = fullImageUrl;
                }
                
                console.log('Updated sidebar profile picture to:', fullImageUrl);
            };
            
            console.log('✅ updateAdminSidebarProfilePicture function overridden for instructor dashboard');
        })();
    </script>
    
    <!-- Override functions after scripts load to ensure they take effect -->
    <script>
        // Additional overrides for specific functions
        (function() {
            
            // Override loadProfileData to use correct path with absolute URLs
            window.loadProfileData = function() {
                console.log('Loading profile data for instructor...');
                const baseUrl = <?= json_encode($baseUrl) ?>;
                
                return fetch('../../api/reports/get_profile_data.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.success) {
                            // Update profile form if it exists
                            if (document.getElementById('profile_fname')) {
                                document.getElementById('profile_fname').value = data.fname || '';
                            }
                            if (document.getElementById('profile_lname')) {
                                document.getElementById('profile_lname').value = data.lname || '';
                            }
                            if (document.getElementById('profile_minitial')) {
                                document.getElementById('profile_minitial').value = data.minitial || '';
                            }
                            if (document.getElementById('profile_acc_user')) {
                                document.getElementById('profile_acc_user').value = data.acc_user || '';
                            }
                            if (document.getElementById('profile_acc_email')) {
                                document.getElementById('profile_acc_email').value = data.acc_email || '';
                            }
                            // Update profile images if they exist - convert to absolute URL
                            if (data.profile_photo) {
                                // Convert relative path to absolute URL
                                let fullImageUrl;
                                if (data.profile_photo.startsWith('http://') || data.profile_photo.startsWith('https://') || data.profile_photo.startsWith('data:')) {
                                    fullImageUrl = data.profile_photo;
                                } else {
                                    // Remove any relative path prefixes (../../ or ../)
                                    let cleanPath = data.profile_photo.replace(/^(\.\.\/)+/g, '').replace(/^\.\//g, '');
                                    cleanPath = cleanPath.trim();
                                    
                                    // Normalize path - remove any double slashes and '../' patterns
                                    cleanPath = cleanPath.replace(/\/+/g, '/');
                                    cleanPath = cleanPath.replace(/\/\.\.\//g, '/').replace(/\/\.\.$/g, '');
                                    
                                    // Remove 'public/' if it exists (we'll add it back correctly)
                                    if (cleanPath.startsWith('public/')) {
                                        cleanPath = cleanPath.substring(7); // Remove 'public/' prefix
                                    }
                                    
                                    // Ensure path starts with 'public/assets/'
                                    if (cleanPath.startsWith('assets/')) {
                                        cleanPath = 'public/' + cleanPath;
                                    } else {
                                        cleanPath = 'public/' + cleanPath;
                                    }
                                    
                                    // Final cleanup - remove any '../' that might have been created
                                    cleanPath = cleanPath.replace(/\/\.\.\//g, '/').replace(/\/\.\.$/g, '');
                                    
                                    const cleanBaseUrl = baseUrl.replace(/\/+$/, '');
                                    fullImageUrl = cleanBaseUrl + '/' + cleanPath + '?t=' + new Date().getTime();
                                    console.log('Path conversion:', data.profile_photo, '→', fullImageUrl);
                                }
                                
                                const profileImg = document.getElementById('adminProfileImg');
                                if (profileImg) {
                                    profileImg.src = fullImageUrl;
                                    console.log('Updated adminProfileImg to:', fullImageUrl);
                                }
                                const mobileImg = document.getElementById('mobileAdminProfileImg');
                                if (mobileImg) {
                                    mobileImg.src = fullImageUrl;
                                    console.log('Updated mobileAdminProfileImg to:', fullImageUrl);
                                }
                                
                                // Also update main profile picture if it exists
                                const profilePic = document.getElementById('profilePicture');
                                if (profilePic) {
                                    profilePic.src = fullImageUrl;
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.warn('Error loading profile data (suppressed for instructors):', error);
                    });
            };
            
            // Suppress loadAccounts for instructors (they don't need to see accounts list)
            window.loadAccounts = function() {
                console.log('loadAccounts suppressed for instructors (not needed)');
                return Promise.resolve();
            };
            
            // Override loadSchedules for instructors - filter by section if selected
            // The backend automatically filters by instructor ID and active school year/semester
            // This override must be defined AFTER schedule_management.js loads
            const originalLoadSchedules = window.loadSchedules;
            window.loadSchedules = function() {
                console.log('Loading schedules for instructor...');
                
                // Check if a section is selected (from class button click)
                const activeButton = document.querySelector('.class-btn.active');
                const sectionId = activeButton ? activeButton.getAttribute('data-sec-id') : null;
                
                // Get API base path - use the function from schedule_management.js if available
                let apiBasePath = '../../admin/management/';
                if (typeof getApiBasePath === 'function') {
                    apiBasePath = getApiBasePath();
                } else {
                    // Fallback: determine path from current location
                    const path = window.location.pathname;
                    if (path.includes('/views/instructor/')) {
                        apiBasePath = '../../admin/management/';
                    }
                }
                
                // For instructors, backend filters by instructor ID, active SY/term, optional department
                const params = new URLSearchParams();
                if (sectionId) {
                    params.append('section', sectionId);
                    console.log('Filtering by section:', sectionId);
                }
                const deptEl = document.getElementById('instructorDeptFilter');
                if (deptEl && deptEl.value) {
                    params.append('department', deptEl.value);
                    console.log('Filtering by department:', deptEl.value);
                }
                params.append('_t', Date.now()); // Cache busting
                
                const apiUrl = `${apiBasePath}get_schedules.php?${params.toString()}`;
                console.log('Loading instructor schedules from:', apiUrl);
                console.log('Section filter:', sectionId || 'None');
                
                // Show spinner if it exists
                const spinner = document.getElementById('scheduleSpinner');
                if (spinner) spinner.style.display = 'block';
                
                // Clear calendar
                if (typeof clearCalendar === 'function') {
                    clearCalendar();
                }
                
                return fetch(apiUrl)
                    .then(response => {
                        console.log('Schedule API response status:', response.status);
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('API Error Response:', text);
                                throw new Error(`HTTP error! status: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Schedules loaded:', data);
                        if (data && data.instructor_departments && data.instructor_departments.length && document.getElementById('instructorDeptFilter')) {
                            const sel = document.getElementById('instructorDeptFilter');
                            const cur = sel.value;
                            sel.innerHTML = '<option value="">All Departments</option>' + data.instructor_departments.map(function(d) {
                                const name = String(d.dept_name || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                                return '<option value="' + (d.dept_id || '') + '">' + name + '</option>';
                            }).join('');
                            if (cur) sel.value = cur;
                            if (!sel.hasAttribute('data-dept-filter-bound')) {
                                sel.setAttribute('data-dept-filter-bound', '1');
                                sel.addEventListener('change', function() { if (typeof window.loadSchedules === 'function') window.loadSchedules(); });
                            }
                        }
                        if (data && data.success && data.data) {
                            window.schedulesData = data.data;
                            console.log('Found', data.data.length, 'schedules' + (sectionId ? ` for section ${sectionId}` : ''));
                            
                            // For overtime calculation, we need ALL schedules (without section filter)
                            // Fetch all schedules for the instructor to calculate overtime properly
                            const allSchedulesParams = new URLSearchParams();
                            // Don't include section filter for all schedules fetch
                            allSchedulesParams.append('_t', Date.now());
                            
                            fetch(`${apiBasePath}get_schedules.php?${allSchedulesParams.toString()}`)
                                .then(response => response.json())
                                .then(allData => {
                                    if (allData.success && allData.data) {
                                        window.allSchedulesData = allData.data; // Store all schedules globally for overtime calculation
                                        console.log('Loaded', allData.data.length, 'total schedules for overtime calculation');
                                    } else {
                                        window.allSchedulesData = data.data; // Fallback to filtered data
                                    }
                                    
                                    // Now render everything with proper overtime calculation
                                    if (typeof renderCalendar === 'function') {
                                        renderCalendar(data.data);
                                    }
                                    if (typeof renderClassTimeLoadTable === 'function') {
                                        renderClassTimeLoadTable(data.data);
                                    }
                                    // Filter and render overtime schedules - pass all schedules for calculation
                                    if (typeof renderOvertimeTable === 'function') {
                                        const overtimeSchedules = data.data.filter(schedule => schedule.is_overtime === 'Yes');
                                        renderOvertimeTable(overtimeSchedules, window.allSchedulesData || data.data);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading all schedules for overtime:', error);
                                    // Fallback: render with available data
                                    window.allSchedulesData = data.data;
                                    if (typeof renderCalendar === 'function') {
                                        renderCalendar(data.data);
                                    }
                                    if (typeof renderClassTimeLoadTable === 'function') {
                                        renderClassTimeLoadTable(data.data);
                                    }
                                    if (typeof renderOvertimeTable === 'function') {
                                        const overtimeSchedules = data.data.filter(schedule => schedule.is_overtime === 'Yes');
                                        renderOvertimeTable(overtimeSchedules, data.data);
                                    }
                                });
                        } else {
                            console.warn('No schedules found or error in response:', data);
                            window.schedulesData = [];
                            window.allSchedulesData = [];
                            if (typeof clearCalendar === 'function') {
                                clearCalendar();
                            }
                            if (typeof renderClassTimeLoadTable === 'function') {
                                renderClassTimeLoadTable([]);
                            }
                            if (typeof renderOvertimeTable === 'function') {
                                renderOvertimeTable([], []);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading schedules:', error);
                        window.schedulesData = [];
                        window.allSchedulesData = [];
                        if (typeof clearCalendar === 'function') {
                            clearCalendar();
                        }
                        if (typeof renderClassTimeLoadTable === 'function') {
                            renderClassTimeLoadTable([]);
                        }
                        if (typeof renderOvertimeTable === 'function') {
                            renderOvertimeTable([], []);
                        }
                    })
                    .finally(() => {
                        if (spinner) spinner.style.display = 'none';
                    });
            };
        })();
        
            // Re-apply loadSchedules override AFTER schedule_management.js loads to ensure it takes precedence
        setTimeout(function() {
            console.log('Re-applying loadSchedules override for instructor...');
            
            // Override loadSchedules for instructors - filter by section if selected
            window.loadSchedules = function() {
                console.log('Loading schedules for instructor (override)...');
                
                // Get API base path - use the function from schedule_management.js if available
                let apiBasePath = '../../admin/management/';
                if (typeof getApiBasePath === 'function') {
                    apiBasePath = getApiBasePath();
                } else {
                    // Fallback: determine path from current location
                    const path = window.location.pathname;
                    if (path.includes('/views/instructor/')) {
                        apiBasePath = '../../admin/management/';
                    }
                }
                
                // Check if a section is selected (from class button click)
                const activeButton = document.querySelector('.class-btn.active');
                const sectionId = activeButton ? activeButton.getAttribute('data-sec-id') : null;
                
                // For instructors, backend filters by instructor ID, active SY/term, optional department
                const params = new URLSearchParams();
                if (sectionId) {
                    params.append('section', sectionId);
                    console.log('Filtering by section:', sectionId);
                }
                const deptEl = document.getElementById('instructorDeptFilter');
                if (deptEl && deptEl.value) {
                    params.append('department', deptEl.value);
                    console.log('Filtering by department:', deptEl.value);
                }
                params.append('_t', Date.now()); // Cache busting
                
                const apiUrl = `${apiBasePath}get_schedules.php?${params.toString()}`;
                console.log('Loading instructor schedules from:', apiUrl);
                console.log('Section filter:', sectionId || 'None');
                
                // Show spinner if it exists
                const spinner = document.getElementById('scheduleSpinner');
                if (spinner) spinner.style.display = 'block';
                
                // Clear calendar
                if (typeof clearCalendar === 'function') {
                    clearCalendar();
                }
                
                return fetch(apiUrl)
                    .then(response => {
                        console.log('Schedule API response status:', response.status);
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('API Error Response:', text);
                                throw new Error(`HTTP error! status: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Schedules loaded:', data);
                        if (data && data.instructor_departments && data.instructor_departments.length && document.getElementById('instructorDeptFilter')) {
                            const sel = document.getElementById('instructorDeptFilter');
                            const cur = sel.value;
                            sel.innerHTML = '<option value="">All Departments</option>' + data.instructor_departments.map(function(d) {
                                const name = String(d.dept_name || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                                return '<option value="' + (d.dept_id || '') + '">' + name + '</option>';
                            }).join('');
                            if (cur) sel.value = cur;
                            if (!sel.hasAttribute('data-dept-filter-bound')) {
                                sel.setAttribute('data-dept-filter-bound', '1');
                                sel.addEventListener('change', function() { if (typeof window.loadSchedules === 'function') window.loadSchedules(); });
                            }
                        }
                        if (data && data.success && data.data) {
                            window.schedulesData = data.data;
                            console.log('Found', data.data.length, 'schedules' + (sectionId ? ` for section ${sectionId}` : ''));
                            
                            // For overtime calculation, we need ALL schedules (without section filter)
                            // Fetch all schedules for the instructor to calculate overtime properly
                            const allSchedulesParams = new URLSearchParams();
                            // Don't include section filter for all schedules fetch
                            allSchedulesParams.append('_t', Date.now());
                            
                            fetch(`${apiBasePath}get_schedules.php?${allSchedulesParams.toString()}`)
                                .then(response => response.json())
                                .then(allData => {
                                    if (allData.success && allData.data) {
                                        window.allSchedulesData = allData.data; // Store all schedules globally for overtime calculation
                                        console.log('Loaded', allData.data.length, 'total schedules for overtime calculation');
                                    } else {
                                        window.allSchedulesData = data.data; // Fallback to filtered data
                                    }
                                    
                                    // Now render everything with proper overtime calculation
                                    if (typeof renderCalendar === 'function') {
                                        renderCalendar(data.data);
                                    }
                                    if (typeof renderClassTimeLoadTable === 'function') {
                                        renderClassTimeLoadTable(data.data);
                                    }
                                    // Filter and render overtime schedules - pass all schedules for calculation
                                    if (typeof renderOvertimeTable === 'function') {
                                        const overtimeSchedules = data.data.filter(schedule => schedule.is_overtime === 'Yes');
                                        renderOvertimeTable(overtimeSchedules, window.allSchedulesData || data.data);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading all schedules for overtime:', error);
                                    // Fallback: render with available data
                                    window.allSchedulesData = data.data;
                                    if (typeof renderCalendar === 'function') {
                                        renderCalendar(data.data);
                                    }
                                    if (typeof renderClassTimeLoadTable === 'function') {
                                        renderClassTimeLoadTable(data.data);
                                    }
                                    if (typeof renderOvertimeTable === 'function') {
                                        const overtimeSchedules = data.data.filter(schedule => schedule.is_overtime === 'Yes');
                                        renderOvertimeTable(overtimeSchedules, data.data);
                                    }
                                });
                        } else {
                            console.warn('No schedules found or error in response:', data);
                            window.schedulesData = [];
                            window.allSchedulesData = [];
                            if (typeof clearCalendar === 'function') {
                                clearCalendar();
                            }
                            if (typeof renderClassTimeLoadTable === 'function') {
                                renderClassTimeLoadTable([]);
                            }
                            if (typeof renderOvertimeTable === 'function') {
                                renderOvertimeTable([], []);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading schedules:', error);
                        window.schedulesData = [];
                        window.allSchedulesData = [];
                        if (typeof clearCalendar === 'function') {
                            clearCalendar();
                        }
                        if (typeof renderClassTimeLoadTable === 'function') {
                            renderClassTimeLoadTable([]);
                        }
                        if (typeof renderOvertimeTable === 'function') {
                            renderOvertimeTable([], []);
                        }
                    })
                    .finally(() => {
                        if (spinner) spinner.style.display = 'none';
                    });
            };
            
            console.log('loadSchedules override re-applied successfully');
        }, 200);
        
        // Override loadClassSelector for instructors - no filters needed, backend auto-filters by instructor
        setTimeout(function() {
            console.log('Re-applying loadClassSelector override for instructor...');
            
            const originalLoadClassSelector = window.loadClassSelector;
            const instructorLoadClassSelector = function() {
                console.log('Loading class selector for instructor (override - no filters needed)...');
                
                const container = document.getElementById('classSelectorContainer');
                const buttonsContainer = document.getElementById('classButtonsContainer');
                
                if (!container || !buttonsContainer) {
                    console.warn('Class selector container not found');
                    return;
                }
                
                // Always show container for instructors (they need the print button)
                container.style.display = 'block';
                container.style.visibility = 'visible';
                container.classList.remove('d-none');
                
                // Get API base path
                let apiBasePath = '../../admin/management/';
                if (typeof getApiBasePath === 'function') {
                    apiBasePath = getApiBasePath();
                } else {
                    const path = window.location.pathname;
                    if (path.includes('/views/instructor/')) {
                        apiBasePath = '../../admin/management/';
                    }
                }
                
                // Show loading state
                buttonsContainer.innerHTML = '<span class="text-white">Loading classes...</span>';
                
                // For instructors, backend automatically filters sections by instructor ID
                // No need to pass any filter parameters
                const apiUrl = `${apiBasePath}get_class_sections.php`;
                console.log('Loading class sections from:', apiUrl);
                
                return fetch(apiUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Class sections response:', data);
                        if (data.success && data.data) {
                            const totalSections = Object.values(data.data).reduce((sum, arr) => sum + arr.length, 0);
                            console.log(`Loaded ${totalSections} class sections for instructor`);
                            
                            // Use the renderClassButtons function from schedule_management.js
                            if (typeof renderClassButtons === 'function') {
                                renderClassButtons(data.data);
                            } else {
                                console.error('renderClassButtons function not found');
                                buttonsContainer.innerHTML = '<span class="text-white">Error: renderClassButtons not found</span>';
                            }
                        } else {
                            console.error('Failed to load class sections:', data.message || 'Unknown error');
                            buttonsContainer.innerHTML = '<span class="text-white">No classes available</span>';
                            // Keep container visible even if no classes (print button needed)
                            container.style.display = 'block';
                            container.style.visibility = 'visible';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading class sections:', error);
                        buttonsContainer.innerHTML = '<span class="text-white">Error loading classes. Check console for details.</span>';
                        // Keep container visible even on error (print button needed)
                        container.style.display = 'block';
                        container.style.visibility = 'visible';
                    });
            };
            
            window.loadClassSelector = instructorLoadClassSelector;
            console.log('loadClassSelector override re-applied successfully');
        }, 200);
        
        // Re-apply showToast override after schedule_management.js loads (in case it overwrote it)
        (function() {
            const originalShowToast = window.showToast;
            if (originalShowToast) {
                window.showToast = function(icon, title) {
                    // Suppress error toasts related to form data loading for instructors
                    if (icon === 'error' && title && (
                        title.includes('An error occurred while loading form data') ||
                        title.includes('Failed to load form data')
                    )) {
                        console.warn('Form data error suppressed for instructor:', title);
                        return; // Don't show the toast
                    }
                    // For other toasts, use the original function
                    return originalShowToast.call(this, icon, title);
                };
            }
        })();
        
        // Disable edit/delete functionality for instructors (view-only access)
        (function() {
            // Override editSchedule function to prevent editing
            window.editSchedule = function(schd_id) {
                console.log('Edit schedule disabled for instructors (view-only access)');
                if (typeof showToast === 'function') {
                    showToast('info', 'You have view-only access. Editing schedules is not permitted.');
                }
                return false;
            };
            
            // Override deleteSchedule function to prevent deletion
            window.deleteSchedule = function(schd_id) {
                console.log('Delete schedule disabled for instructors (view-only access)');
                if (typeof showToast === 'function') {
                    showToast('info', 'You have view-only access. Deleting schedules is not permitted.');
                }
                return false;
            };
            
            // Override handleRemoveSchedule function
            window.handleRemoveSchedule = function() {
                console.log('Remove schedule disabled for instructors (view-only access)');
                if (typeof showToast === 'function') {
                    showToast('info', 'You have view-only access. Removing schedules is not permitted.');
                }
                return false;
            };
            
            // Override saveSchedule function to prevent saving
            if (typeof window.saveSchedule === 'function') {
                const originalSaveSchedule = window.saveSchedule;
                window.saveSchedule = function() {
                    console.log('Save schedule disabled for instructors (view-only access)');
                    if (typeof showToast === 'function') {
                        showToast('info', 'You have view-only access. Creating or editing schedules is not permitted.');
                    }
                    return false;
                };
            }
            
            // Hide instructor filter dropdown for instructors (they can only see their own schedules)
            document.addEventListener('DOMContentLoaded', function() {
                const instructorFilterLabel = document.querySelector('label[for="instructorFilter"]');
                const instructorFilter = document.getElementById('instructorFilter');
                
                if (instructorFilterLabel && instructorFilter) {
                    // Hide the instructor filter - instructors can only see their own schedules
                    const filterRow = instructorFilterLabel.closest('.col-6, .col-md-3, .col-lg');
                    if (filterRow) {
                        filterRow.style.display = 'none';
                    }
                }
                
                // Remove onclick handlers from schedule blocks to prevent editing
                // This will be called after schedules are loaded
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                // Remove onclick handlers from schedule blocks
                                const scheduleBlocks = node.querySelectorAll ? node.querySelectorAll('[onclick*="editSchedule"], [onclick*="deleteSchedule"]') : [];
                                scheduleBlocks.forEach(function(block) {
                                    block.onclick = null;
                                    block.style.cursor = 'default';
                                    block.title = 'View-only: Editing is not permitted';
                                });
                                
                                // Also check if the node itself is a schedule block
                                if (node.onclick && (node.onclick.toString().includes('editSchedule') || node.onclick.toString().includes('deleteSchedule'))) {
                                    node.onclick = null;
                                    node.style.cursor = 'default';
                                    if (node.title) {
                                        node.title = 'View-only: Editing is not permitted';
                                    }
                                }
                            }
                        });
                    });
                });
                
                // Start observing the schedule container
                const scheduleContainer = document.getElementById('scheduleContainer') || document.querySelector('.schedule-container');
                if (scheduleContainer) {
                    observer.observe(scheduleContainer, {
                        childList: true,
                        subtree: true
                    });
                }
                
                // Also observe the class time load table
                const classTimeTable = document.getElementById('classTimeLoadTable');
                if (classTimeTable) {
                    observer.observe(classTimeTable, {
                        childList: true,
                        subtree: true
                    });
                }
            });
        })();
    </script>
    
    <!-- Override admin.js functions for instructor dashboard -->
    <script>
        // Override admin's loadDashboardData function AFTER admin.js loads
        // This must override BOTH the function definition and any calls to it
        window.loadDashboardData = function() {
            console.log('Loading dashboard data for instructor (override)...');
            return fetch('../../instructor/reports/get_dashboard_data.php')
                .then(response => {
                    console.log('Dashboard data response status:', response.status);
                    if (!response.ok) {
                        // For non-OK responses, try to get error message from response
                        return response.json().then(errData => {
                            throw new Error(errData.message || 'Failed to load dashboard data');
                        }).catch(() => {
                            throw new Error('Failed to load dashboard data');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Dashboard data loaded:', data);
                    if (data && data.success) {
                        // Call updateDashboardStatistics if it exists (from admin.js)
                        if (typeof updateDashboardStatistics === 'function') {
                            updateDashboardStatistics(data.data);
                        }
                        // Also call updateDashboardUI if it exists
                        if (typeof updateDashboardUI === 'function') {
                            updateDashboardUI(data);
                        }
                    } else {
                        console.warn('Dashboard data response indicates failure:', data);
                        // Silently fail for instructors - they may not have access to all data
                        if (typeof updateDashboardUI === 'function') {
                            updateDashboardUI({ success: true, data: {} });
                        }
                    }
                })
                .catch(error => {
                    console.warn('Error loading dashboard data (suppressed for instructors):', error);
                    // Silently fail for instructors - don't show error messages
                    // Try basic stats as fallback, but don't show errors if that fails too
                    if (typeof loadBasicStats === 'function') {
                        loadBasicStats();
                    }
                });
        };
        
        // Override admin's loadBasicStats function AFTER admin.js loads
        function loadBasicStats() {
            console.log('Loading basic stats as fallback for instructor (override)...');
            fetch('../../instructor/reports/get_basic_stats.php')
                .then(response => {
                    console.log('Basic stats response status:', response.status);
                    if (response.ok) {
                        return response.json();
                    } else {
                        throw new Error('Failed to load basic stats');
                    }
                })
                .then(data => {
                    console.log('Basic stats loaded:', data);
                    updateDashboardUI(data);
                })
                .catch(error => {
                    console.error('Error loading basic stats:', error);
                    // Don't show error for instructor - they may not have access to all data
                    console.log('Instructor dashboard - basic stats failed, but this is expected');
                });
        }
        
        // Override admin's showDashboardError function AFTER admin.js loads
        function showDashboardError(message) {
            console.log('Dashboard error (instructor override - suppressed):', message);
            // Don't show error banner for instructor dashboard
            // The instructor role may not have access to all dashboard data
            // Remove any existing error banners that might have been created
            removeDashboardErrorBanners();
        }
        
        // Override loadBasicStats to prevent it from creating error banners
        const originalLoadBasicStats = window.loadBasicStats;
        if (originalLoadBasicStats) {
            window.loadBasicStats = function() {
                console.log('loadBasicStats called - using instructor override');
                // Call the instructor version instead
                if (typeof loadBasicStats === 'function') {
                    // This will be the overridden version defined below
                }
            };
        }
        
        // Override showTab function to load schedules when schedule tab is shown
        // This must be the FINAL override to ensure schedule loading works
        (function() {
            const originalShowTabForSchedule = window.showTab;
            let scheduleLoadingInProgress = false;
            
            if (originalShowTabForSchedule) {
                window.showTab = function(tabId, element) {
                    // Prevent infinite loops - check if we're already processing this tab
                    const now = Date.now();
                    if (lastTabSwitch.tabId === tabId && (now - lastTabSwitch.timestamp) < TAB_SWITCH_DEBOUNCE) {
                        console.log('🚫 Final override: Blocking duplicate call for:', tabId);
                        return; // Exit early to prevent loop
                    }
                    
                    console.log('🔵 Final showTab override called:', { tabId, element: !!element, hasClass: element?.classList?.contains('nav-link') });
                    
                    // Only call original if it's different from current function to prevent recursion
                    if (originalShowTabForSchedule && originalShowTabForSchedule !== window.showTab) {
                        originalShowTabForSchedule.call(this, tabId, element);
                    }
                    
                    // Load schedules when schedule tab is shown
                    // For instructors, just load schedules directly (no filters needed)
                    if (tabId === 'schedule' && !scheduleLoadingInProgress) {
                        scheduleLoadingInProgress = true;
                        console.log('📅 Schedule tab activated, preparing to load schedules...');
                        
                        // Double-check that the schedule tab is visible
                        const scheduleTab = document.getElementById('schedule');
                        if (scheduleTab) {
                            scheduleTab.style.display = 'block';
                            scheduleTab.style.visibility = 'visible';
                            console.log('✅ Schedule tab made visible');
                        }
                        
                        // Wait a bit to ensure the schedule component is fully rendered
                        setTimeout(() => {
                            // Check if schedule component elements exist
                            const syFilter = document.getElementById('syFilter');
                            const classSelectorContainer = document.getElementById('classSelectorContainer');
                            const scheduleContainer = document.getElementById('schedule');
                            
                            console.log('📊 Schedule tab elements check:', {
                                scheduleTabVisible: scheduleContainer?.style.display !== 'none',
                                syFilter: !!syFilter,
                                classSelectorContainer: !!classSelectorContainer,
                                loadSchedules: typeof loadSchedules,
                                loadClassSelector: typeof loadClassSelector
                            });
                            
                            // Ensure schedule tab is visible
                            if (scheduleContainer) {
                                scheduleContainer.style.display = 'block';
                                scheduleContainer.style.visibility = 'visible';
                                scheduleContainer.classList.remove('d-none');
                            }
                            
                            // Ensure class selector container is visible for instructors (they need print button)
                            if (classSelectorContainer) {
                                classSelectorContainer.style.display = 'block';
                                classSelectorContainer.style.visibility = 'visible';
                                classSelectorContainer.classList.remove('d-none');
                            }
                            
                            // Load class selector and schedules
                            if (typeof loadClassSelector === 'function') {
                                console.log('🔄 Loading class selector...');
                                loadClassSelector();
                            } else {
                                console.warn('⚠️ loadClassSelector function not found');
                            }
                            
                            if (typeof loadSchedules === 'function') {
                                console.log('🔄 Loading schedules...');
                                loadSchedules();
                            } else {
                                console.warn('⚠️ loadSchedules function not found');
                            }
                            
                            scheduleLoadingInProgress = false;
                        }, 300); // Reduced delay for faster response
                    }
                };
                console.log('✅ Final showTab override installed for schedule tab');
            }
        })();
        
        // Ensure class selector container is always visible for instructors on page load
        function ensureClassSelectorVisible() {
            const classSelectorContainer = document.getElementById('classSelectorContainer');
            if (classSelectorContainer) {
                classSelectorContainer.style.display = 'block';
                classSelectorContainer.style.visibility = 'visible';
                classSelectorContainer.classList.remove('d-none');
                console.log('Ensured class selector container is visible for instructor');
            }
        }
        
        // Run immediately and also after a delay
        ensureClassSelectorVisible();
        setTimeout(ensureClassSelectorVisible, 500);
        setTimeout(ensureClassSelectorVisible, 1000);
        setTimeout(ensureClassSelectorVisible, 2000);
        
        // Also ensure it's visible when schedule tab is clicked
        const scheduleTab = document.querySelector('[data-tab="schedule"]');
        if (scheduleTab) {
            scheduleTab.addEventListener('click', function() {
                setTimeout(ensureClassSelectorVisible, 100);
            });
        }
        
        // Override admin's updateDashboardUI function AFTER admin.js loads
        function updateDashboardUI(data) {
            console.log('Updating dashboard UI with data (instructor override):', data);
            
            if (data.success && data.data) {
                const stats = data.data;
                
                // Update individual stat elements
                updateStatElement('totalUsers', stats.users?.total_users || 0);
                updateStatElement('activeUsers', stats.users?.active_users || 0);
                updateStatElement('pendingUsers', stats.users?.pending_users || 0);
                updateStatElement('inactiveUsers', stats.users?.inactive_users || 0);
                
                updateStatElement('totalInstructors', stats.instructors?.total_instructors || 0);
                updateStatElement('regularInstructors', stats.instructors?.regular_instructors || 0);
                updateStatElement('parttimeInstructors', stats.instructors?.parttime_instructors || 0);
                updateStatElement('pendingInstructors', stats.instructors?.pending_instructors || 0);
                
                updateStatElement('totalRooms', stats.rooms?.total_rooms || 0);
                updateStatElement('usedRooms', stats.rooms?.used_rooms || 0);
                updateStatElement('availableRooms', stats.rooms?.available_rooms || 0);
                updateStatElement('labRooms', stats.rooms?.lab_rooms || 0);
                
                updateStatElement('totalSchedules', stats.schedules?.total_schedules || 0);
                updateStatElement('activeSchedules', stats.schedules?.active_schedules || 0);
                updateStatElement('conflictSchedules', stats.schedules?.conflict_schedules || 0);
                updateStatElement('requestSchedules', stats.schedules?.request_schedules || 0);
                
                console.log('Dashboard statistics updated successfully');
            } else {
                console.error('Failed to load dashboard data:', data);
                // Show error state in a more user-friendly way
                showDashboardError();
            }
        }
        
        function updateStatElement(elementId, value) {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = value;
            }
        }
        
        // ============================================
        // MOBILE SCHEDULE VIEW FUNCTIONALITY
        // ============================================
        
        /**
         * Mobile Schedule View Manager
         * Provides a mobile-friendly card-based view of schedules
         */
        const MobileScheduleView = {
            currentView: 'calendar', // 'calendar' or 'list'
            initialized: false,
            
            /**
             * Initialize mobile view components
             */
            init: function() {
                if (this.initialized) return;
                
                // Create mobile view toggle and container
                this.createMobileElements();
                
                // Set up event listeners
                this.setupEventListeners();
                
                // Check initial screen size
                this.handleResize();
                
                this.initialized = true;
                console.log('Mobile Schedule View initialized');
            },
            
            /**
             * Create mobile view toggle and container elements
             */
            createMobileElements: function() {
                const calendarView = document.getElementById('calendarView');
                if (!calendarView) return;
                
                // Create view toggle buttons
                const toggleHtml = `
                    <div class="mobile-view-toggle" id="mobileViewToggle">
                        <div class="btn-group" role="group" aria-label="Schedule view toggle">
                            <button type="button" class="btn btn-outline-secondary active" data-view="calendar" onclick="MobileScheduleView.switchView('calendar')">
                                <i class="bi bi-calendar3 me-2"></i>Calendar View
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-view="list" onclick="MobileScheduleView.switchView('list')">
                                <i class="bi bi-list-ul me-2"></i>List View
                            </button>
                        </div>
                    </div>
                `;
                
                // Create mobile schedule container
                const mobileContainerHtml = `
                    <div class="mobile-schedule-container" id="mobileScheduleContainer">
                        <div id="mobileScheduleContent">
                            <!-- Mobile schedule cards will be rendered here -->
                        </div>
                    </div>
                `;
                
                // Insert toggle before calendar view
                calendarView.insertAdjacentHTML('beforebegin', toggleHtml);
                
                // Insert mobile container after calendar view
                calendarView.insertAdjacentHTML('afterend', mobileContainerHtml);
            },
            
            /**
             * Set up event listeners
             */
            setupEventListeners: function() {
                // Listen for window resize
                window.addEventListener('resize', () => this.handleResize());
                
                // Override renderCalendar to also update mobile view
                const originalRenderCalendar = window.renderCalendar;
                window.renderCalendar = function(schedules) {
                    // Call original render
                    if (originalRenderCalendar) {
                        originalRenderCalendar(schedules);
                    }
                    // Also render mobile view
                    MobileScheduleView.renderMobileSchedules(schedules);
                };
            },
            
            /**
             * Handle window resize
             */
            handleResize: function() {
                const isMobile = window.innerWidth < 992;
                const toggle = document.getElementById('mobileViewToggle');
                
                if (toggle) {
                    toggle.style.display = isMobile ? 'block' : 'none';
                }
                
                // If on mobile and list view is selected, show mobile container
                if (isMobile && this.currentView === 'list') {
                    this.showMobileView();
                } else if (!isMobile) {
                    this.showCalendarView();
                }
            },
            
            /**
             * Switch between calendar and list view
             */
            switchView: function(view) {
                this.currentView = view;
                
                // Update button states
                const buttons = document.querySelectorAll('#mobileViewToggle .btn');
                buttons.forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.view === view);
                });
                
                if (view === 'list') {
                    this.showMobileView();
                    // Re-render with current data
                    if (window.schedulesData) {
                        this.renderMobileSchedules(window.schedulesData);
                    }
                } else {
                    this.showCalendarView();
                }
            },
            
            /**
             * Show mobile list view
             */
            showMobileView: function() {
                const calendarView = document.getElementById('calendarView');
                const mobileContainer = document.getElementById('mobileScheduleContainer');
                
                if (calendarView) {
                    calendarView.classList.add('mobile-hidden');
                }
                if (mobileContainer) {
                    mobileContainer.classList.add('mobile-visible');
                }
            },
            
            /**
             * Show calendar view
             */
            showCalendarView: function() {
                const calendarView = document.getElementById('calendarView');
                const mobileContainer = document.getElementById('mobileScheduleContainer');
                
                if (calendarView) {
                    calendarView.classList.remove('mobile-hidden');
                }
                if (mobileContainer) {
                    mobileContainer.classList.remove('mobile-visible');
                }
            },
            
            /**
             * Render mobile schedule cards
             */
            renderMobileSchedules: function(schedules) {
                const container = document.getElementById('mobileScheduleContent');
                if (!container) return;
                
                if (!schedules || schedules.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x display-4 text-muted"></i>
                            <p class="mt-3 text-muted">No schedules found.</p>
                        </div>
                    `;
                    return;
                }
                
                // Group schedules by day
                const dayOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                const dayNames = {
                    'Mon': 'Monday',
                    'Tue': 'Tuesday',
                    'Wed': 'Wednesday',
                    'Thu': 'Thursday',
                    'Fri': 'Friday',
                    'Sat': 'Saturday',
                    'Sun': 'Sunday'
                };
                
                const schedulesByDay = {};
                dayOrder.forEach(day => {
                    schedulesByDay[day] = [];
                });
                
                schedules.forEach(schedule => {
                    const day = schedule.schd_day;
                    if (schedulesByDay[day]) {
                        schedulesByDay[day].push(schedule);
                    }
                });
                
                // Sort each day's schedules by start time
                Object.keys(schedulesByDay).forEach(day => {
                    schedulesByDay[day].sort((a, b) => {
                        return this.timeToMinutes(a.schd_start_time) - this.timeToMinutes(b.schd_start_time);
                    });
                });
                
                // Build HTML
                let html = '';
                
                dayOrder.forEach((day, index) => {
                    const daySchedules = schedulesByDay[day];
                    const hasSchedules = daySchedules.length > 0;
                    const isExpanded = hasSchedules; // Expand days with schedules by default
                    
                    html += `
                        <div class="mobile-day-accordion" id="dayAccordion${day}">
                            <button class="mobile-day-header ${!isExpanded ? 'collapsed' : ''}" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#dayContent${day}"
                                    aria-expanded="${isExpanded}"
                                    aria-controls="dayContent${day}">
                                <span>
                                    <i class="bi bi-calendar-day me-2"></i>
                                    ${dayNames[day]}
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="schedule-count">${daySchedules.length} ${daySchedules.length === 1 ? 'class' : 'classes'}</span>
                                    <i class="bi bi-chevron-down chevron-icon"></i>
                                </span>
                            </button>
                            <div id="dayContent${day}" class="collapse ${isExpanded ? 'show' : ''}" data-bs-parent="#mobileScheduleContent">
                                <div class="mobile-day-content">
                                    ${hasSchedules ? this.renderDaySchedules(daySchedules) : this.renderEmptyDay()}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            },
            
            /**
             * Render schedule cards for a day
             */
            renderDaySchedules: function(schedules) {
                return schedules.map(schedule => this.renderScheduleCard(schedule)).join('');
            },
            
            /**
             * Render a single schedule card
             */
            renderScheduleCard: function(schedule) {
                const isOvertime = schedule.is_overtime === 'Yes';
                const startTime = this.formatTime(schedule.schd_start_time);
                const endTime = this.formatTime(schedule.schd_end_time);
                const duration = this.calculateDuration(schedule.schd_start_time, schedule.schd_end_time);
                
                return `
                    <div class="mobile-schedule-card ${isOvertime ? 'overtime-card' : ''}">
                        <div class="mobile-schedule-card-header">
                            <div class="subject-code">${schedule.subj_code || 'N/A'}</div>
                            <div class="section-name">${schedule.sec_name || ''}</div>
                        </div>
                        <div class="mobile-schedule-card-body">
                            <div class="info-row">
                                <div class="info-icon">👤</div>
                                <div class="info-content">
                                    <div class="info-label">Instructor</div>
                                    <div class="info-value">${schedule.instructor_name || 'TBA'}</div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon">🕐</div>
                                <div class="info-content">
                                    <div class="info-label">Time</div>
                                    <div class="info-value">${startTime} - ${endTime} <span class="text-muted">(${duration})</span></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon">📍</div>
                                <div class="info-content">
                                    <div class="info-label">Location</div>
                                    <div class="info-value">${schedule.bldg_name || ''} ${schedule.rm_name || 'TBA'}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mobile-schedule-card-footer">
                            <span class="type-badge">${schedule.schd_type || 'Lec'}</span>
                            ${isOvertime ? '<span class="overtime-badge"><i class="bi bi-exclamation-triangle me-1"></i>Overtime</span>' : ''}
                        </div>
                    </div>
                `;
            },
            
            /**
             * Render empty day message
             */
            renderEmptyDay: function() {
                return `
                    <div class="mobile-empty-day">
                        <i class="bi bi-calendar-check d-block"></i>
                        <p>No classes scheduled</p>
                    </div>
                `;
            },
            
            /**
             * Format time to 12-hour format
             */
            formatTime: function(time) {
                if (!time) return '';
                const [hours, minutes] = time.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour % 12 || 12;
                return `${hour12}:${minutes} ${ampm}`;
            },
            
            /**
             * Convert time string to minutes
             */
            timeToMinutes: function(time) {
                if (!time) return 0;
                const [hours, minutes] = time.split(':').map(Number);
                return hours * 60 + minutes;
            },
            
            /**
             * Calculate duration between two times
             */
            calculateDuration: function(startTime, endTime) {
                const startMinutes = this.timeToMinutes(startTime);
                const endMinutes = this.timeToMinutes(endTime);
                const durationMinutes = endMinutes - startMinutes;
                
                const hours = Math.floor(durationMinutes / 60);
                const minutes = durationMinutes % 60;
                
                if (hours > 0 && minutes > 0) {
                    return `${hours}h ${minutes}m`;
                } else if (hours > 0) {
                    return `${hours}h`;
                } else {
                    return `${minutes}m`;
                }
            }
        };
        
        // Initialize mobile view when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for schedule component to be ready
            setTimeout(function() {
                MobileScheduleView.init();
            }, 500);
        });
        
        // Also initialize when schedule tab is shown
        document.addEventListener('click', function(e) {
            const navLink = e.target.closest('.nav-link');
            if (navLink) {
                const onclick = navLink.getAttribute('onclick');
                if (onclick && onclick.includes('schedule')) {
                    console.log('📱 Mobile schedule view: Schedule tab clicked');
                    setTimeout(function() {
                        MobileScheduleView.init();
                        // Re-render if data exists
                        if (window.schedulesData) {
                            MobileScheduleView.renderMobileSchedules(window.schedulesData);
                        }
                    }, 300);
                }
            }
        });
        
        // Additional direct handler for schedule tab to ensure it works
        // This runs in capture phase BEFORE admin_dashboard.js can prevent it
        let scheduleClickHandled = false;
        document.addEventListener('click', function(e) {
            const navLink = e.target.closest('.nav-link');
            if (navLink) {
                const onclick = navLink.getAttribute('onclick');
                const href = navLink.getAttribute('href');
                
                // Check if this is a schedule tab link
                if (onclick && onclick.includes('showTab') && onclick.includes('schedule') && href === '#schedule') {
                    // Prevent infinite loops - only handle once per click
                    if (scheduleClickHandled) {
                        console.log('🚫 Schedule click already handled, ignoring');
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        e.stopPropagation();
                        return false;
                    }
                    scheduleClickHandled = true;
                    setTimeout(() => { scheduleClickHandled = false; }, 500);
                    
                    console.log('🔵 Direct schedule tab click detected (capture phase)');
                    
                    // Prevent default and stop ALL propagation to prevent onclick from running
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    
                    // Remove the onclick handler temporarily to prevent it from executing
                    const originalOnclick = onclick;
                    navLink.removeAttribute('onclick');
                    
                    // Call showTab directly with the nav link element
                    if (typeof showTab === 'function') {
                        console.log('✅ Calling showTab for schedule with element');
                        showTab('schedule', navLink);
                    } else {
                        console.warn('⚠️ showTab function not available yet');
                        // Fallback: manually switch tabs
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.style.display = 'none';
                            tab.style.visibility = 'hidden';
                        });
                        const scheduleTab = document.getElementById('schedule');
                        if (scheduleTab) {
                            scheduleTab.style.display = 'block';
                            scheduleTab.style.visibility = 'visible';
                        }
                        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                        navLink.classList.add('active');
                    }
                    
                    // Restore onclick after a delay (but it won't execute because we stopped propagation)
                    setTimeout(() => {
                        if (originalOnclick) {
                            navLink.setAttribute('onclick', originalOnclick);
                        }
                    }, 100);
                    
                    return false;
                }
            }
        }, true); // Use capture phase (true) to run BEFORE other handlers
        
        // Also set up handlers after DOM loads
        document.addEventListener('DOMContentLoaded', function() {
            // Find all nav links with schedule onclick
            const scheduleLinks = document.querySelectorAll('.nav-link[onclick*="schedule"], .nav-link[href="#schedule"]');
            scheduleLinks.forEach(link => {
                // Add a direct click handler as backup
                link.addEventListener('click', function(e) {
                    // Only handle if onclick didn't work
                    if (e.defaultPrevented) return;
                    
                    const onclick = this.getAttribute('onclick');
                    if (onclick && onclick.includes('showTab')) {
                        console.log('🔵 Backup schedule link click handler');
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Call showTab with the element
                        if (typeof showTab === 'function') {
                            showTab('schedule', this);
                        }
                        
                        return false;
                    }
                }, false); // Use bubble phase as backup
            });
            console.log('✅ Direct schedule tab handlers installed:', scheduleLinks.length);
        });
    </script>
</body>
</html>