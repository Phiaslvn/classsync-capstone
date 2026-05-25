<?php
/**
 * Custom Admin Dashboard Sidebar
 * Provides navigation for the existing admin dashboard structure
 */

// Include the dashboard controller which handles all data preparation
require_once __DIR__ . '/../../admin/controllers/dashboard_controller.php';

// Include permission visibility system for real-time permission checking
require_once __DIR__ . '/../utils/permission_visibility.php';

// Data is now provided by the controller
$username = $userDisplayData['username'];
$roleName = $userDisplayData['role_name'];
$dept_name = $userDisplayData['dept_name'];

// Get real-time permissions for dynamic navigation
$currentUserId = $_SESSION['acc_id'];
$visiblePermissions = getVisiblePermissions($currentUserId);
?>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarLabel">Menu</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-3">
        <!-- Mobile Profile Section -->
        <div class="text-center mb-4">
            <?php 
            // Profile picture data is now provided by the controller
            $imagePath = $profileData['profile_picture'];
            ?>
            <div class="position-relative d-inline-block mb-3" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } if(typeof closeOffcanvas === 'function') { closeOffcanvas(); } return false;" title="Click to edit profile">
                <img id="mobileAdminProfileImg" src="<?= $imagePath ?>" alt="Profile Picture" class="rounded-circle" width="60" height="60" style="object-fit: cover; border: 2px solid #fff; transition: transform 0.2s ease;">
                <button id="mobileAdminEditProfileBtn" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 rounded-circle" style="width: 24px; height: 24px; padding: 0; font-size: 10px; pointer-events: none;" title="Edit Profile" type="button">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <div class="fw-semibold text-white" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } if(typeof closeOffcanvas === 'function') { closeOffcanvas(); } return false;" title="Click to edit profile"><?= htmlspecialchars($username) ?></div>
            <small class="text-light opacity-75"><?= htmlspecialchars($roleName) ?></small>
        </div>
        
        <nav class="nav flex-column">
            <!-- Dashboard Section -->
            <div class="nav-section mb-3">
                <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Dashboard</small>
                <a class="nav-link active" href="#overview" onclick="showTab('overview', this)">
                    <span class="bi bi-speedometer2 me-2"></span> Overview
                </a>
            </div>
            
            <!-- Academic Management Section -->
            <div class="nav-section mb-3">
                <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Academic</small>
                <?php if (hasVisiblePermission('assign_schedules', $currentUserId) || hasVisiblePermission('manage_schedules', $currentUserId)): ?>
                <a class="nav-link" href="#schedule" onclick="showTab('schedule', this)">
                    <span class="bi bi-calendar-week me-2"></span> Schedules
                </a>
                <?php endif; ?>
                
                <?php if (hasVisiblePermission('manage_curriculum', $currentUserId) || hasVisiblePermission('manage_subjects', $currentUserId)): ?>
                <a class="nav-link" href="#curriculum" onclick="showTab('curriculum', this)">
                    <span class="bi bi-book me-2"></span> Subjects
                </a>
                
                <a class="nav-link" href="#curriculum_management" onclick="showTab('curriculum_management', this)">
                    <span class="bi bi-mortarboard me-2"></span> Curriculum
                </a>
                
                <a class="nav-link" href="#course_management" onclick="showTab('course_management', this)">
                    <span class="bi bi-diagram-3 me-2"></span> Course
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Resource Management Section -->
            <div class="nav-section mb-3">
                <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Resources</small>
                <?php if (hasVisiblePermission('approve_room_requests', $currentUserId) || hasVisiblePermission('manage_rooms', $currentUserId) || hasVisiblePermission('view_rooms', $currentUserId)): ?>
                <a class="nav-link" href="#room_requests" onclick="showTab('room_requests', this)">
                    <span class="bi bi-building me-2"></span> Rooms
                </a>
                <?php endif; ?>
                
                <?php if (hasVisiblePermission('manage_users', $currentUserId)): ?>
                <a class="nav-link" href="#roles" onclick="showTab('roles', this)">
                    <span class="bi bi-people me-2"></span> Users
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Reports Section -->
            <!-- <div class="nav-section mb-3">
                <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Analytics</small>
                <?php if (hasVisiblePermission('view_reports', $currentUserId)): ?>
                <a class="nav-link" href="#reports" onclick="showTab('reports', this)">
                    <span class="bi bi-file-earmark-text me-2"></span> Reports
                </a>
                <?php endif; ?>
            </div> -->
            
            <!-- Divider -->
            <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
            
            <!-- Account Section -->
            <div class="nav-section">
                <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Account</small>
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
        <div class="position-relative d-inline-block mb-3" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } return false;" title="Click to edit profile">
            <img id="adminProfileImg" src="<?= $imagePath ?>" alt="Profile Picture" class="rounded-circle" width="80" height="80" style="object-fit: cover; border: 3px solid #fff; transition: transform 0.2s ease;">
            <button id="adminEditProfileBtn" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 rounded-circle" style="width: 28px; height: 28px; padding: 0; font-size: 12px; pointer-events: none;" title="Edit Profile" type="button">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
        <div class="fw-semibold mt-2" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } return false;" title="Click to edit profile"><?= htmlspecialchars($username) ?></div>
        <small class="text-light opacity-75"><?= htmlspecialchars($roleName) ?></small>
        <small class="text-light opacity-75 d-block"><?= htmlspecialchars($dept_name) ?></small>
    </div>
    <nav class="nav flex-column px-3">
        <!-- Dashboard Section -->
        <div class="nav-section mb-3">
            <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Dashboard</small>
            <a class="nav-link active" href="#overview" onclick="showTab('overview', this)">
                <span class="bi bi-speedometer2 me-2"></span> Overview
            </a>
        </div>
        
        <!-- Academic Management Section -->
        <div class="nav-section mb-3">
            <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Academic</small>
            <?php if (hasVisiblePermission('assign_schedules', $currentUserId) || hasVisiblePermission('manage_schedules', $currentUserId)): ?>
            <a class="nav-link" href="#schedule" onclick="showTab('schedule', this)">
                <span class="bi bi-calendar-week me-2"></span> Schedules
            </a>
            <?php endif; ?>
            
            <?php if (hasVisiblePermission('manage_curriculum', $currentUserId) || hasVisiblePermission('manage_subjects', $currentUserId)): ?>
            <a class="nav-link" href="#curriculum" onclick="showTab('curriculum', this)">
                <span class="bi bi-book me-2"></span> Subjects
            </a>
            
            <a class="nav-link" href="#curriculum_management" onclick="showTab('curriculum_management', this)">
                <span class="bi bi-mortarboard me-2"></span> Curriculum
            </a>
            
            <a class="nav-link" href="#course_management" onclick="showTab('course_management', this)">
                <span class="bi bi-diagram-3 me-2"></span> Course
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Resource Management Section -->
        <div class="nav-section mb-3">
            <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Resources</small>
            <?php if (hasVisiblePermission('approve_room_requests', $currentUserId) || hasVisiblePermission('manage_rooms', $currentUserId) || hasVisiblePermission('view_rooms', $currentUserId)): ?>
            <a class="nav-link" href="#room_requests" onclick="showTab('room_requests', this)">
                <span class="bi bi-building me-2"></span> Rooms
            </a>
            <?php endif; ?>
            
            <?php if (hasVisiblePermission('manage_users', $currentUserId)): ?>
            <a class="nav-link" href="#roles" onclick="showTab('roles', this)">
                <span class="bi bi-people me-2"></span> Users
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Divider -->
        <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
        
        <!-- Account Section -->
        <div class="nav-section">
            <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Account</small>
            <!-- ✅ Fixed logout button -->
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <span class="bi bi-box-arrow-right me-2"></span> Logout
            </a>
        </div>
    </nav>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-maroon text-white">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">Are you sure you want to logout?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="../auth/logout.php" class="btn btn-maroon">Logout</a>
      </div>
    </div>
  </div>
</div>
<style>
    /* Ensure logout modal is above backdrop (1100) and other modals */
    #logoutModal {
        z-index: 1105 !important;
    }
    
    #logoutModal.modal {
        z-index: 1105 !important;
    }
    
    #logoutModal .modal-dialog {
        z-index: 1106 !important;
    }
    
    /* Logout Modal Styling - Clean card design with proper spacing */
    #logoutModal .modal-dialog {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: calc(100% - 1rem) !important;
        margin: 0.5rem auto !important;
    }
    
    #logoutModal .modal-content {
        border: none !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
        max-width: 450px !important;
        width: 100% !important;
        overflow: hidden !important;
        position: relative !important;
        z-index: 1107 !important;
    }
    
    #logoutModal .modal-header {
        background: #800000 !important;
        background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
        color: #ffffff !important;
        padding: 1.25rem 1.5rem !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 16px 16px 0 0 !important;
    }
    
    #logoutModal .modal-title {
        font-weight: 600 !important;
        font-size: 1.1rem !important;
        margin: 0 !important;
        color: #ffffff !important;
    }
    
    #logoutModal .btn-close-white {
        filter: brightness(0) invert(1) !important;
        opacity: 0.9 !important;
    }
    
    #logoutModal .btn-close-white:hover {
        opacity: 1 !important;
    }
    
    #logoutModal .modal-body {
        padding: 1.75rem 1.5rem !important;
        color: #212529 !important;
        font-size: 1rem !important;
        line-height: 1.5 !important;
        background: #ffffff !important;
    }
    
    #logoutModal .modal-footer {
        padding: 1.25rem 1.5rem !important;
        border-top: 1px solid #dee2e6 !important;
        background: #f8f9fa !important;
        gap: 0.75rem !important;
        display: flex !important;
        justify-content: flex-end !important;
    }
    
    #logoutModal .modal-footer .btn {
        padding: 0.625rem 1.25rem !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        font-size: 0.95rem !important;
        min-width: 100px !important;
        height: 42px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.2s ease !important;
    }
    
    #logoutModal .modal-footer .btn-secondary {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        color: #ffffff !important;
    }
    
    #logoutModal .modal-footer .btn-secondary:hover {
        background-color: #5a6268 !important;
        border-color: #545b62 !important;
        color: #ffffff !important;
    }
    
    #logoutModal .modal-footer .btn-maroon {
        background: #800000 !important;
        background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
        border: none !important;
        color: #ffffff !important;
        text-decoration: none !important;
    }
    
    #logoutModal .modal-footer .btn-maroon:hover {
        background: #660000 !important;
        background: linear-gradient(135deg, #660000 0%, #800000 100%) !important;
        color: #ffffff !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3) !important;
    }
    
    /* Mobile: Touch-friendly logout buttons */
    @media (max-width: 991.98px) {
        #logoutModal .modal-footer .btn {
            min-height: 44px !important; /* Touch-friendly minimum height */
            padding: 0.75rem 1.5rem !important; /* Larger touch target */
            font-size: 1rem !important; /* Slightly larger text for readability */
            min-width: 120px !important; /* Wider buttons for easier tapping */
        }
        
        #logoutModal .modal-footer {
            flex-direction: column-reverse !important; /* Stack buttons vertically on mobile */
            gap: 0.75rem !important;
        }
        
        #logoutModal .modal-footer .btn {
            width: 100% !important; /* Full-width buttons on mobile */
        }
        
        /* Ensure logout link in sidebar is touch-friendly */
        .sidebar-offcanvas .nav-link[data-bs-target="#logoutModal"],
        .sidebar .nav-link[data-bs-target="#logoutModal"],
        .nav-link[data-bs-target="#logoutModal"] {
            min-height: 44px !important;
            padding: 0.75rem 1rem !important;
            display: flex !important;
            align-items: center !important;
        }
        
        #logoutModal .modal-dialog {
            margin: 1rem !important;
            max-width: calc(100% - 2rem) !important;
            width: calc(100% - 2rem) !important;
        }
        
        #logoutModal.modal.show {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 1rem !important;
        }
    }
    
    @media (max-width: 480px) {
        #logoutModal .modal-content {
            width: calc(100% - 2rem) !important;
            max-width: calc(100% - 2rem) !important;
            margin: 0 1rem !important;
        }
        
        #logoutModal .modal-header,
        #logoutModal .modal-body,
        #logoutModal .modal-footer {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
    }
    
    /* Sidebar Scrollable Styling */
    .sidebar {
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }
    
    /* Mobile offcanvas scrollable */
    .sidebar-offcanvas {
        height: 100vh;
    }
    
    .sidebar-offcanvas .offcanvas-body {
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 0.5rem;
    }
    
    .sidebar-offcanvas .offcanvas-body::-webkit-scrollbar {
        width: 4px;
    }
    
    .sidebar-offcanvas .offcanvas-body::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
    }
    
    .sidebar-offcanvas .offcanvas-body::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
    }
    
    .sidebar-offcanvas .offcanvas-body::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }
    
    /* Navigation Section Styling */
    .nav-section {
        position: relative;
    }
    
    .nav-section small {
        font-size: 0.7rem;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    
    .nav-section .nav-link {
        padding-left: 1.5rem;
        margin-bottom: 0.25rem;
        border-radius: 0.375rem;
        transition: none; /* No transition for instant hover */
    }
    
    /* Instant hover effect - ZERO delay */
    .nav-section .nav-link:hover:not(.active) {
        background-color: rgba(255, 255, 255, 0.1) !important;
        transform: translateX(2px) !important;
        /* NO transition - instant hover */
        transition: none !important;
    }
    
    /* Active state - PERSISTS when clicked */
    .nav-section .nav-link.active {
        background-color: rgba(255, 255, 255, 0.25) !important;
        border-left: 3px solid #fff !important;
        font-weight: 600 !important;
        transition: background-color 0.08s ease, transform 0.08s ease, border-left 0.08s ease !important;
    }
    
    /* Active link hover - maintains active styling */
    .nav-section .nav-link.active:hover {
        background-color: rgba(255, 255, 255, 0.25) !important;
        border-left: 3px solid #fff !important;
        transform: translateX(2px) !important;
        transition: none !important;
    }
    
    /* Mobile sidebar adjustments */
    .sidebar-offcanvas .nav-section small {
        color: rgba(255, 255, 255, 0.6);
    }
    
    .sidebar-offcanvas .nav-section .nav-link {
        color: rgba(255, 255, 255, 0.9);
    }
    
    .sidebar-offcanvas .nav-section .nav-link:hover {
        color: #fff;
    }
    
    /* Ensure proper spacing for scrollable content */
    .sidebar .nav {
        padding-bottom: 2rem;
    }
    
    .sidebar-offcanvas .nav {
        padding-bottom: 2rem;
    }
    
    /* Profile section clickable styling */
    .sidebar .text-center.mb-4 > div[onclick],
    .sidebar-offcanvas .text-center.mb-4 > div[onclick] {
        transition: all 0.2s ease;
    }
    
    .sidebar .text-center.mb-4 > div[onclick]:hover,
    .sidebar-offcanvas .text-center.mb-4 > div[onclick]:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    
    .sidebar .text-center.mb-4 > div[onclick]:hover img,
    .sidebar-offcanvas .text-center.mb-4 > div[onclick]:hover img {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }
    
    .sidebar .text-center.mb-4 .fw-semibold[onclick],
    .sidebar-offcanvas .text-center.mb-4 .fw-semibold[onclick] {
        transition: all 0.2s ease;
    }
    
    .sidebar .text-center.mb-4 .fw-semibold[onclick]:hover,
    .sidebar-offcanvas .text-center.mb-4 .fw-semibold[onclick]:hover {
        color: #fff !important;
        text-decoration: underline;
    }
</style>

<script>
// Navigation functions for admin dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Handle navigation clicks - let showTab function manage active states
    // This listener only handles sidebar closing and prevents conflicts
    document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const target = this.getAttribute('data-bs-target');
            
            // Handle logout modal - ensure it works properly on mobile
            if (target === '#logoutModal') {
                e.preventDefault(); // ✅ Prevent href issues
                e.stopPropagation();
                
                // Close mobile offcanvas if open before showing modal
                const offcanvasEl = document.getElementById('sidebarOffcanvas');
                if (offcanvasEl && typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                    const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                    if (bsOffcanvas && bsOffcanvas._isShown) {
                        // Close offcanvas first
                        bsOffcanvas.hide();
                        
                        // Wait for offcanvas to close, then show modal
                        const showModalAfterOffcanvas = function() {
                            offcanvasEl.removeEventListener('hidden.bs.offcanvas', showModalAfterOffcanvas);
                            
                            // Small delay to ensure offcanvas is fully closed
                            setTimeout(() => {
                                const logoutModalEl = document.getElementById('logoutModal');
                                if (logoutModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                    // Remove any existing modal instance
                                    const existingInstance = bootstrap.Modal.getInstance(logoutModalEl);
                                    if (existingInstance) {
                                        try {
                                            existingInstance.dispose();
                                        } catch (e) {
                                            console.warn('Error disposing existing modal instance:', e);
                                        }
                                    }
                                    
                                    // Create new modal instance
                                    const logoutModal = new bootstrap.Modal(logoutModalEl, {
                                        backdrop: true,
                                        keyboard: true,
                                        focus: true
                                    });
                                    
                                    // Ensure proper z-index
                                    logoutModalEl.style.zIndex = '1105';
                                    if (logoutModalEl.querySelector('.modal-dialog')) {
                                        logoutModalEl.querySelector('.modal-dialog').style.zIndex = '1106';
                                    }
                                    if (logoutModalEl.querySelector('.modal-content')) {
                                        logoutModalEl.querySelector('.modal-content').style.zIndex = '1107';
                                    }
                                    
                                    // Show modal
                                    logoutModal.show();
                                    
                                    // Ensure buttons are clickable
                                    const buttons = logoutModalEl.querySelectorAll('.btn, a.btn');
                                    buttons.forEach(btn => {
                                        btn.style.pointerEvents = 'auto';
                                        btn.style.cursor = 'pointer';
                                        btn.style.zIndex = '1108';
                                    });
                                }
                            }, 150);
                        };
                        
                        offcanvasEl.addEventListener('hidden.bs.offcanvas', showModalAfterOffcanvas, { once: true });
                        return;
                    }
                }
                
                // If offcanvas is not open, ensure proper z-index for modal
                const logoutModalEl = document.getElementById('logoutModal');
                if (logoutModalEl) {
                    logoutModalEl.style.zIndex = '1105';
                    if (logoutModalEl.querySelector('.modal-dialog')) {
                        logoutModalEl.querySelector('.modal-dialog').style.zIndex = '1106';
                    }
                    if (logoutModalEl.querySelector('.modal-content')) {
                        logoutModalEl.querySelector('.modal-content').style.zIndex = '1107';
                    }
                }
                
                // Don't add active class to logout link
                return;
            }
            
            // Don't manage active state here - let showTab function handle it
            // This prevents multiple handlers from conflicting
            
            // Close mobile sidebar if open
            const offcanvasEl = document.getElementById('sidebarOffcanvas');
            if (offcanvasEl && window.bootstrap && bootstrap.Offcanvas) {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                if (bsOffcanvas) {
                    bsOffcanvas.hide();
                }
            }
        });
    });
    
    // Ensure logout modal buttons are always clickable when modal is shown
    const logoutModalEl = document.getElementById('logoutModal');
    if (logoutModalEl) {
        logoutModalEl.addEventListener('shown.bs.modal', function() {
            // Ensure all buttons are clickable
            const buttons = logoutModalEl.querySelectorAll('.btn, a.btn, button');
            buttons.forEach(btn => {
                btn.style.pointerEvents = 'auto';
                btn.style.cursor = 'pointer';
                btn.style.zIndex = '1108';
                btn.style.position = 'relative';
            });
            
            // Ensure modal content is clickable
            const modalContent = logoutModalEl.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.pointerEvents = 'auto';
                modalContent.style.zIndex = '1107';
            }
            
            // Ensure backdrop doesn't block clicks
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = '1100';
            }
        });
    }
    
    // Ensure logout modal buttons are always clickable when modal is shown
    const logoutModalEl = document.getElementById('logoutModal');
    if (logoutModalEl) {
        logoutModalEl.addEventListener('shown.bs.modal', function() {
            // Ensure all buttons are clickable
            const buttons = logoutModalEl.querySelectorAll('.btn, a.btn, button');
            buttons.forEach(btn => {
                btn.style.pointerEvents = 'auto';
                btn.style.cursor = 'pointer';
                btn.style.zIndex = '1108';
                btn.style.position = 'relative';
            });
            
            // Ensure modal content is clickable
            const modalContent = logoutModalEl.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.pointerEvents = 'auto';
                modalContent.style.zIndex = '1107';
            }
            
            // Ensure backdrop doesn't block clicks
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = '1100';
            }
        });
    }
    
    // Real-time permission refresh functionality
    refreshPermissions();
    setInterval(refreshPermissions, 30000); // Check every 30 seconds
    
    // Enhanced scrollable sidebar functionality
    const sidebar = document.querySelector('.sidebar');
    const mobileSidebar = document.querySelector('.sidebar-offcanvas .offcanvas-body');
    
    // Smooth scrolling for desktop sidebar
    if (sidebar) {
        sidebar.style.scrollBehavior = 'smooth';
        
        // Auto-scroll to active item on load
        const activeLink = sidebar.querySelector('.nav-link.active');
        if (activeLink) {
            setTimeout(() => {
                activeLink.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 100);
        }
    }
    
    // Smooth scrolling for mobile sidebar
    if (mobileSidebar) {
        mobileSidebar.style.scrollBehavior = 'smooth';
        
        // Auto-scroll to active item on mobile
        const activeLink = mobileSidebar.querySelector('.nav-link.active');
        if (activeLink) {
            setTimeout(() => {
                activeLink.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 100);
        }
    }
    
    // Handle window resize to maintain scroll position
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Recalculate scroll position on resize
            const activeLink = document.querySelector('.nav-link.active');
            if (activeLink && (sidebar || mobileSidebar)) {
                activeLink.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        }, 250);
    });
});

// Real-time permission refresh function
function refreshPermissions() {
    fetch('../../admin/permission_visibility_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_permissions&csrf_token=<?= generateCSRFToken() ?>'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.success && data.permissions_updated) {
            // Show notification that permissions have been updated
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Permissions Updated',
                    text: 'Your permissions have been updated. Refreshing navigation...',
                    icon: 'info',
                    confirmButtonColor: '#800000',
                    timer: 3000
                }).then(() => {
                    location.reload();
                });
            } else {
                // Fallback if SweetAlert2 is not available
                alert('Your permissions have been updated. Refreshing page...');
                location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Permission check failed:', error);
        // Don't show error to user for background permission checks
    });
}

// Profile picture update functions for admin dashboard
window.updateAdminSidebarProfilePicture = function(imagePath) {
    const desktopImg = document.getElementById('adminProfileImg');
    if (desktopImg) {
        desktopImg.src = '../../public/' + imagePath + '?t=' + new Date().getTime();
    }
    const mobileImg = document.getElementById('mobileAdminProfileImg');
    if (mobileImg) {
        mobileImg.src = '../../public/' + imagePath + '?t=' + new Date().getTime();
    }
};

window.updateAdminSidebarProfilePictureToDefault = function() {
    const defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+';
    const desktopImg = document.getElementById('adminProfileImg');
    if (desktopImg) {
        desktopImg.src = defaultImage;
    }
    const mobileImg = document.getElementById('mobileAdminProfileImg');
    if (mobileImg) {
        mobileImg.src = defaultImage;
    }
};
</script>
