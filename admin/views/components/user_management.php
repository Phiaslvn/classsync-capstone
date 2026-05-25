<?php
/**
 * User Management Component
 * User listing, editing, and management
 */
?>

<div class="dashboard-card user-management-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div class="flex-grow-1" style="min-width: 220px;">
            <h2 class="h4 mb-1 fw-bold text-dark">
                <i class="bi bi-people-fill me-2 text-primary"></i>Users
            </h2>
            <p class="text-muted small mb-2">Accounts, roles, and instructor records for your administration scope.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end flex-shrink-0">
            <?php if ($dashboardController->hasPermission('manage_users')): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                <i class="bi bi-person-plus me-1"></i>Add User
            </button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExistingInstructorModal">
                <i class="bi bi-person-add me-1"></i>Add Existing Instructor
            </button>
            <?php endif; ?>
            <?php if ($dashboardController->hasPermission('manage_users') || $dashboardController->hasPermission('view_users')): ?>
            <div class="btn-group">
                <button class="btn btn-success btn-sm" onclick="showPrintWorkloadOptions()" title="Print instructor workload forms">
                    <i class="bi bi-printer me-1"></i>Print Workload Forms
                </button>
                <button type="button" class="btn btn-success btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="batchPrintWorkloadForms(); return false;">
                        <i class="bi bi-printer-fill me-2"></i>Batch Print (All Instructors)
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="showSinglePrintModal(); return false;">
                        <i class="bi bi-person me-2"></i>Single Print (Selected Instructor)
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="row g-2 mb-4">
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="totalUsersCount">-</div>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="moderatorsCount">-</div>
                    <small class="text-muted">Moderators</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="instructorsCount">-</div>
                    <small class="text-muted">Instructors</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="activeUsersCount">-</div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="pendingUsersCount">-</div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="regularUsersCount">-</div>
                    <small class="text-muted">Regular</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="partTimeUsersCount">-</div>
                    <small class="text-muted">Part Time</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3 fw-bold text-muted">
                <i class="bi bi-funnel me-2"></i>Filter Options
            </h6>
            <div class="row g-3">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">
                        <i class="bi bi-search me-1"></i>Search
                    </label>
                    <input type="text" class="form-control form-control-sm" id="accountsSearch" placeholder="Search users...">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">
                        <i class="bi bi-sort-down me-1"></i>Sort By
                    </label>
                    <select class="form-select form-select-sm" id="accountsSort">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name_asc">Name A-Z</option>
                        <option value="name_desc">Name Z-A</option>
                        <option value="rank_desc">Highest-Lowest Rank</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">
                        <i class="bi bi-info-circle me-1"></i>Status
                    </label>
                    <select class="form-select form-select-sm" id="accountsStatusFilter">
                        <option value="">All Status</option>
                        <option value="Regular">Regular</option>
                        <option value="Part-Time">Part-Time</option>
                        <option value="Contractual">Contractual</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">
                        <i class="bi bi-person-badge me-1"></i>Role
                    </label>
                    <select class="form-select form-select-sm" id="accountsRoleFilter">
                        <option value="">All Roles</option>
                        <?php if ($dashboardController->hasPermission('manage_users') || $dashboardController->hasPermission('view_users') || isAdminSupport()): ?>
                        <option value="2">Admin</option>
                        <?php endif; ?>
                        <option value="3">Moderator</option>
                        <option value="4">Instructor</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">
                        <i class="bi bi-award me-1"></i>Rank
                    </label>
                    <select class="form-select form-select-sm" id="accountsRankFilter">
                        <option value="">All Ranks</option>
                        <!-- Rank options will be populated dynamically from workload_policy table -->
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">
                        <i class="bi bi-briefcase me-1"></i>Designation
                    </label>
                    <select class="form-select form-select-sm" id="accountsDesignationFilter">
                        <option value="">All Designations</option>
                        <!-- Designation options will be populated dynamically from workload_policy table -->
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Information Panel (visible in console) -->
    <script>
        console.log('🔵 [DEBUG] ========== USER MANAGEMENT COMPONENT LOADED ==========');
        console.log('🔵 [DEBUG] Component loaded at:', new Date().toISOString());
        console.log('🔵 [DEBUG] Dashboard Controller available:', typeof window.dashboardController !== 'undefined' ? 'Yes' : 'No');
        console.log('🔵 [DEBUG] Current User ID:', typeof window.currentUserId !== 'undefined' ? window.currentUserId : 'Not set');
        console.log('🔵 [DEBUG] Current Dept ID:', typeof window.currentDeptId !== 'undefined' ? window.currentDeptId : 'Not set');
        console.log('🔵 [DEBUG] Is Admin Support:', typeof window.isAdminSupport !== 'undefined' ? window.isAdminSupport : 'Not set');
        
        // Helper function to force load accounts (bypasses tab visibility check)
        window.forceLoadAccounts = function() {
            console.log('🔵 [DEBUG] forceLoadAccounts() called - bypassing tab visibility check');
            if (typeof loadAccounts === 'function') {
                return loadAccounts();
            } else {
                console.error('❌ [DEBUG] loadAccounts function not found');
                return Promise.reject('loadAccounts function not found');
            }
        };
        
        // Check if table elements exist
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔵 [DEBUG] DOM Content Loaded - Checking table elements...');
            const tableBody = document.getElementById('usersTableBody');
            const tableContainer = document.getElementById('usersTableContainer');
            const inactiveTableBody = document.getElementById('inactiveUsersTableBody');
            
            console.log('🔵 [DEBUG] usersTableBody found:', !!tableBody);
            console.log('🔵 [DEBUG] usersTableContainer found:', !!tableContainer);
            console.log('🔵 [DEBUG] inactiveUsersTableBody found:', !!inactiveTableBody);
            
            if (tableBody) {
                console.log('🔵 [DEBUG] usersTableBody parent:', tableBody.parentElement?.tagName);
            }
            if (tableContainer) {
                console.log('🔵 [DEBUG] usersTableContainer display:', window.getComputedStyle(tableContainer).display);
            }
            
            // Check if loadAccounts function exists
            console.log('🔵 [DEBUG] loadAccounts function exists:', typeof loadAccounts === 'function');
            console.log('🔵 [DEBUG] applyAccountsFilters function exists:', typeof applyAccountsFilters === 'function');
            console.log('🔵 [DEBUG] renderUsersTable function exists:', typeof renderUsersTable === 'function');
            
            // Try to find the users/roles tab link
            const usersTabSelectors = [
                'a[onclick*="roles"]',
                'a[href="#roles"]',
                'a[href*="roles"]',
                'a[onclick*="showTab(\'roles\']',
                'a:contains("Users")',
                '.sidebar a, .offcanvas-body a'
            ];
            
            console.log('🔵 [DEBUG] Searching for users tab link...');
            usersTabSelectors.forEach(selector => {
                try {
                    const links = document.querySelectorAll(selector);
                    links.forEach(link => {
                        const text = link.textContent?.trim() || '';
                        const href = link.href || '';
                        const onclick = link.onclick?.toString() || '';
                        if (text.toLowerCase().includes('user') || href.includes('roles') || onclick.includes('roles')) {
                            console.log('🔵 [DEBUG] Found potential users tab:', {
                                selector: selector,
                                text: text,
                                href: href,
                                onclick: onclick.substring(0, 100),
                                visible: link.offsetParent !== null,
                                display: window.getComputedStyle(link).display
                            });
                        }
                    });
                } catch (e) {
                    // Invalid selector, skip
                }
            });
            
            console.log('🔵 [DEBUG] ====================================================');
            console.log('🔵 [DEBUG] To manually load accounts, run: forceLoadAccounts()');
        });
    </script>

    <!-- Users Table -->
    <style>
        /* Duplicate Alert Modal Styling */
        .duplicate-alert-popup {
            border-radius: 16px !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.05) !important;
            padding: 0 !important;
            overflow: hidden !important;
            max-width: 550px !important;
        }
        
        .duplicate-alert-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            color: #dc3545 !important;
            margin-bottom: 1rem !important;
            padding: 1.5rem 2rem 0.5rem 2rem !important;
            border-bottom: 2px solid #f8d7da !important;
        }
        
        .duplicate-alert-container {
            padding: 1.5rem 2rem !important;
        }
        
        .duplicate-alert-modal {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .duplicate-alert-message {
            font-size: 1rem !important;
            color: #495057 !important;
            margin-bottom: 1rem !important;
            line-height: 1.6 !important;
            font-weight: 500 !important;
        }
        
        .duplicate-alert-content {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            border-radius: 12px;
            padding: 1.25rem;
            border: 2px solid #f8d7da;
            margin-top: 1rem;
        }
        
        .duplicate-alert-content.mt-3 {
            margin-top: 1.5rem !important;
        }
        
        .duplicate-user-info {
            background: #ffffff;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #f5c6cb;
        }
        
        .duplicate-section-header {
            display: flex;
            align-items: center;
            font-size: 1rem;
            color: #800000;
            font-weight: 700;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f5c6cb;
            margin-bottom: 0.5rem;
        }
        
        .duplicate-section-header i {
            font-size: 1.2rem;
        }
        
        .user-name-display {
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            color: #212529;
            padding: 0.5rem 0;
        }
        
        .user-name-display i {
            color: #800000;
            font-size: 1.25rem;
        }
        
        .user-name-display strong {
            color: #212529;
            font-weight: 600;
        }
        
        .user-email-display {
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            color: #212529;
            padding: 0.5rem 0;
        }
        
        .user-email-display i {
            color: #800000;
            font-size: 1.25rem;
        }
        
        .user-email-display strong {
            color: #212529;
            font-weight: 600;
            word-break: break-all;
        }
        
        .user-roles-display {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding-top: 0.5rem;
        }
        
        .user-roles-display .badge {
            font-size: 0.875rem;
            padding: 0.5rem 0.875rem;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.25);
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        
        .user-roles-display .badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.35);
        }
        
        .alert-warning-box {
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 0.875rem 1rem;
            color: #856404;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .alert-warning-box i {
            color: #ffc107;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .duplicate-alert-button {
            background: linear-gradient(135deg, #800000, #a00000) !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3) !important;
        }
        
        .duplicate-alert-button:hover {
            background: linear-gradient(135deg, #a00000, #c00000) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(128, 0, 0, 0.4) !important;
        }
        
        .duplicate-alert-button:active {
            transform: translateY(0) !important;
        }
        
        /* SweetAlert2 Error Icon Styling */
        .swal2-icon.swal2-error {
            border-color: #dc3545 !important;
            color: #dc3545 !important;
        }
        
        .swal2-icon.swal2-error [class^=swal2-x-mark-line] {
            background-color: #dc3545 !important;
        }
        
        .swal2-icon.swal2-error .swal2-x-mark {
            flex-shrink: 0;
        }
        
        /* Animation for modal appearance */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translate3d(0, -20px, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }
        
        @keyframes fadeOutUp {
            from {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
            to {
                opacity: 0;
                transform: translate3d(0, -20px, 0);
            }
        }
        
        .animate__faster {
            animation-duration: 0.3s !important;
        }
        
        /* Force maroon header for users table */
        #usersTable thead.table-header-maroon,
        #usersTable thead.table-header-maroon tr,
        #usersTable thead.table-header-maroon th {
            background: linear-gradient(135deg, #800000, #a00000) !important;
            background-color: #800000 !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        #usersTable thead.table-header-maroon th {
            background: transparent !important;
            background-color: transparent !important;
        }
        
        /* Force maroon header for inactive users table */
        #inactiveUsersTable thead.table-header-maroon,
        #inactiveUsersTable thead.table-header-maroon tr,
        #inactiveUsersTable thead.table-header-maroon th {
            background: linear-gradient(135deg, #800000, #a00000) !important;
            background-color: #800000 !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        #inactiveUsersTable thead.table-header-maroon th {
            background: transparent !important;
            background-color: transparent !important;
        }
        
        /* Override any Bootstrap table-dark class */
        #usersTable thead.table-dark,
        #usersTable thead.table-dark th {
            background: linear-gradient(135deg, #800000, #a00000) !important;
            background-color: #800000 !important;
            color: #ffffff !important;
        }
        
        /* Schedule Grid Styles for Modal */
        #viewSchedulesModal .calendar-container {
            position: relative;
            margin-top: 1rem;
        }
        #viewSchedulesModal .calendar-grid {
            display: flex;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow-x: auto;
            overflow-y: auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        #viewSchedulesModal .time-column {
            width: 100px;
            flex-shrink: 0;
            background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
            border-right: 2px solid #e0e0e0;
            position: sticky;
            left: 0;
            z-index: 10;
        }
        #viewSchedulesModal .time-slot {
            height: 60px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
            position: relative;
        }
        #viewSchedulesModal .time-slot::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 30px;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #d0d7de 50%, transparent 100%);
            border-top: 1px dashed #d0d7de;
        }
        #viewSchedulesModal .time-slot-half {
            position: absolute;
            top: 32px;
            font-size: 0.65rem;
            color: #868e96;
            font-weight: 400;
        }
        #viewSchedulesModal .days-wrapper {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            flex-grow: 1;
            background-color: #ffffff;
        }
        #viewSchedulesModal .day-column {
            position: relative;
            border-right: 1px solid #e9ecef;
            background-color: #ffffff;
        }
        #viewSchedulesModal .day-column:last-child {
            border-right: none;
        }
        #viewSchedulesModal .day-column-content {
            position: relative;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 59px,
                #f8f9fa 59px,
                #f8f9fa 60px
            );
        }
        #viewSchedulesModal .calendar-header {
            text-align: center;
            padding: 1rem 0.5rem;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #800000 0%, #990000 100%);
            color: #ffffff;
            border-bottom: 2px solid #660000;
            position: sticky;
            top: 0;
            z-index: 5;
            text-transform: uppercase;
        }
        #viewSchedulesModal .schedule-block {
            position: absolute;
            left: 8px;
            right: 8px;
            background: #800000;
            color: #ffffff;
            border-radius: 0 8px 8px 0;
            padding: 0;
            font-size: 0.75rem;
            overflow: hidden;
            cursor: pointer;
            border-left: 5px solid #660000;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            z-index: 2;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3), 0 2px 6px rgba(0, 0, 0, 0.2);
            min-height: 60px;
        }
        #viewSchedulesModal .schedule-block:hover {
            z-index: 10;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        #viewSchedulesModal .schedule-block-content {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            gap: 2px;
            height: 100%;
            justify-content: space-between;
            padding: 10px 12px;
            box-sizing: border-box;
        }
        #viewSchedulesModal .subj-code-simple {
            font-weight: 800;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 4px;
            color: #ffffff;
        }
        #viewSchedulesModal .instructor-simple,
        #viewSchedulesModal .room-simple,
        #viewSchedulesModal .time-simple {
            font-size: 0.65rem;
            color: #ffffff;
        }
        #viewSchedulesModal .type-simple {
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: auto;
            color: #ffffff;
        }
        /* Modal Overall Styling */
        #viewSchedulesModal .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        #viewSchedulesModal .modal-header {
            background: linear-gradient(135deg, #800000 0%, #a00000 50%, #800000 100%);
            border-bottom: 3px solid #660000;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        #viewSchedulesModal .modal-header .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        #viewSchedulesModal .modal-body {
            background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%);
            padding: 2rem;
        }
        
        #viewSchedulesModal .modal-footer {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 1rem 2rem;
        }
        
        /* Instructor Name Display */
        #viewSchedulesModal #instructorNameDisplay {
            font-size: 1.25rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        #viewSchedulesModal #schedulesCount {
            font-size: 0.95rem;
            color: #495057;
            font-weight: 600;
        }
        
        /* Navigation Tabs */
        #viewSchedulesModal .nav-tabs {
            border-bottom: 3px solid #e9ecef;
            margin-bottom: 1.5rem;
            background: transparent;
        }
        
        #viewSchedulesModal .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            border-radius: 8px 8px 0 0;
            background: transparent;
        }
        
        #viewSchedulesModal .nav-tabs .nav-link:hover {
            border-bottom-color: #800000;
            color: #800000;
            background: rgba(128, 0, 0, 0.05);
        }
        
        #viewSchedulesModal .nav-tabs .nav-link.active {
            color: #800000;
            background: rgba(128, 0, 0, 0.08);
            border-bottom-color: #800000;
            font-weight: 700;
        }
        
        #viewSchedulesModal .tab-content {
            padding-top: 0.5rem;
        }
        
        /* Information Tab Cards */
        #viewSchedulesModal .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        #viewSchedulesModal .card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12), 0 4px 8px rgba(0, 0, 0, 0.06);
            transform: translateY(-2px);
        }
        
        #viewSchedulesModal .card-header {
            background: linear-gradient(135deg, #800000 0%, #990000 100%);
            border-bottom: 2px solid #660000;
            padding: 1rem 1.5rem;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        #viewSchedulesModal .card-body {
            padding: 1.5rem;
            background: #ffffff;
        }
        
        #viewSchedulesModal .card-body .form-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        #viewSchedulesModal .card-body p {
            color: #343a40;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        #viewSchedulesModal .card-body .fw-semibold {
            color: #212529;
            font-weight: 600;
        }
        
        #viewSchedulesModal .card-body .h5 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        #viewSchedulesModal .card-body .h5.text-primary {
            color: #0d6efd !important;
        }
        
        #viewSchedulesModal .card-body .h5.text-info {
            color: #0dcaf0 !important;
        }
        
        #viewSchedulesModal .card-body .h5.text-success {
            color: #198754 !important;
        }
        
        #viewSchedulesModal .card-body .h5.text-danger {
            color: #dc3545 !important;
        }
        
        /* Schedule Grid Styling */
        #viewSchedulesModal .calendar-container {
            position: relative;
            margin-top: 1rem;
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        /* Footer Button */
        #viewSchedulesModal .modal-footer .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
        }
        
        #viewSchedulesModal .modal-footer .btn-secondary {
            background: #6c757d;
            border: none;
            color: #ffffff;
        }
        
        #viewSchedulesModal .modal-footer .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        /* Loading and Error States */
        #viewSchedulesModal #schedulesLoading {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 3rem;
        }
        
        #viewSchedulesModal #schedulesError {
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }
        
        #viewSchedulesModal #noSchedules {
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e7ff 100%);
            border-left: 4px solid #0d6efd;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #viewSchedulesModal .modal-header {
                padding: 1rem 1.5rem;
            }
            
            #viewSchedulesModal .modal-body {
                padding: 1.5rem;
            }
            
            #viewSchedulesModal .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- Loading State -->
            <div id="usersLoadingState" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading users...</p>
            </div>

            <!-- Empty State -->
            <div id="usersEmptyState" class="text-center py-5" style="display: none;">
                <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No users found</h5>
                <p class="text-muted mb-0">Try adjusting your filters or add a new user.</p>
            </div>

            <!-- Users Table -->
            <div id="usersTableContainer">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usersTable">
                        <thead class="table-header-maroon" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                            <tr>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ID</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Name</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Username</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Email</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Role</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Status</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Employment</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Rank</th>
                                <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Designation</th>
                                <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Users will be loaded here via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inactive Users Table -->
            <div class="mt-5 pt-4 border-top">
                <h6 class="mb-3 text-dark fw-semibold">
                    <i class="bi bi-person-x me-2 text-muted"></i>Inactive Users
                </h6>
                <div id="inactiveUsersTableContainer">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="inactiveUsersTable">
                            <thead class="table-header-maroon" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                <tr>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">ID</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Name</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Username</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Email</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Role</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Status</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Employment</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Rank</th>
                                    <th class="fw-semibold text-white small text-uppercase" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Designation</th>
                                <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Administration Hours">Admin. Hrs</th>
                                <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Instruction Hours">Inst. Hrs</th>
                                <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Research Hours">Res. Hrs</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Extension Hours">Ext. Hrs</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Production Hours">Prod. Hrs</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Consultation Hours">Cons. Hrs</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;" title="Total Hours">Total Hrs</th>
                                    <th class="fw-semibold text-white small text-uppercase text-center" style="background: transparent !important; background-color: transparent !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inactiveUsersTableBody">
                                <!-- Inactive users will be loaded here via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="inactiveUsersEmptyState" class="text-center py-4" style="display: none;">
                    <p class="text-muted">
                        <i class="bi bi-inbox me-2"></i>No inactive users found.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <div>
                    <h5 class="modal-title mb-0" id="editAccountModalLabel">Edit Account</h5>
                    <small class="text-white-50">Update instructor account information</small>
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

                <form id="editAccountForm">
                    <input type="hidden" name="acc_id" id="edit_acc_id">
                    
                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" data-step="1">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                    <i class="bi bi-person me-2"></i>Personal Information
                                </h6>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_fname" class="form-label fw-semibold">
                                    <i class="bi bi-person me-1"></i>First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" id="edit_fname" name="fname" placeholder="Enter first name" required>
                                <div class="invalid-feedback">Please provide a valid first name.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_lname" class="form-label fw-semibold">
                                    <i class="bi bi-person me-1"></i>Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" id="edit_lname" name="lname" placeholder="Enter last name" required>
                                <div class="invalid-feedback">Please provide a valid last name.</div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_minitial" class="form-label fw-semibold">
                                    <i class="bi bi-person me-1"></i>Middle Initial
                                </label>
                                <input type="text" class="form-control form-control-lg" id="edit_minitial" name="minitial" placeholder="M" maxlength="1" style="text-transform: uppercase;">
                                <div class="form-text">Optional middle initial</div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_suffix" class="form-label fw-semibold">
                                    <i class="bi bi-person me-1"></i>Suffix
                                </label>
                                <input type="text" class="form-control form-control-lg" id="edit_suffix" name="suffix" placeholder="Jr, Sr, II, III, etc." maxlength="10">
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
                                <label for="edit_acc_user" class="form-label fw-semibold">
                                    <i class="bi bi-at me-1"></i>Username <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" id="edit_acc_user" name="acc_user" placeholder="Enter username" required>
                                <div class="invalid-feedback">Please provide a valid username.</div>
                                <div class="form-text">Username must be unique and 3-20 characters long</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_acc_email" class="form-label fw-semibold">
                                    <i class="bi bi-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control form-control-lg" id="edit_acc_email" name="acc_email" placeholder="user@evsu.edu.ph" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                                <div class="form-text">Use official EVSU email address</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_dept_id" class="form-label fw-semibold">
                                    <i class="bi bi-building me-1"></i>Department
                                </label>
                                <select class="form-select form-select-lg" id="edit_dept_id" name="dept_id">
                                    <option value="">Select Department</option>
                                    <option value="1">Computer Studies</option>
                                    <option value="2">Industrial Technology</option>
                                    <option value="3">Teacher Education</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_program_id" class="form-label fw-semibold">
                                    <i class="bi bi-book me-1"></i>Program/Course
                                </label>
                                <select class="form-select form-select-lg" id="edit_program_id" name="program_id">
                                    <option value="">Select Program/Course</option>
                                    <!-- Options will be populated dynamically -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_inst_status" class="form-label fw-semibold">
                                    <i class="bi bi-briefcase me-1"></i>Employment Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-lg" id="edit_inst_status" name="inst_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Part-Time">Part-Time</option>
                                    <option value="Contractual">Contractual</option>
                                </select>
                                <div class="invalid-feedback">Please select employment status.</div>
                                <small class="form-text text-muted" id="inst_status_hint" style="display: none;">
                                    <i class="bi bi-info-circle"></i> <span id="inst_status_hint_text"></span>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_rank" class="form-label fw-semibold">
                                    <i class="bi bi-award me-1"></i>Academic Rank
                                </label>
                                <select class="form-select form-select-lg" id="edit_rank" name="rank">
                                    <option value="">Select Rank</option>
                                    <!-- Options will be populated dynamically from workload_policy table -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_designation" class="form-label fw-semibold">
                                    <i class="bi bi-briefcase-fill me-1"></i>Designation
                                </label>
                                <select class="form-select form-select-lg" id="edit_designation" name="designation">
                                    <option value="None">None</option>
                                    <!-- Options will be populated dynamically from workload_policy table -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_inst_phone" class="form-label fw-semibold">
                                    <i class="bi bi-phone me-1"></i>Phone Number
                                </label>
                                <input type="tel" class="form-control form-control-lg" id="edit_inst_phone" name="inst_phone" placeholder="+63 912 345 6789" maxlength="20">
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
                                <label for="edit_administration_hours" class="form-label fw-semibold">
                                    <i class="bi bi-building me-1"></i>Administrative Hours <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="edit_administration_hours" name="administration_hours" min="0" value="0" required>
                                <div class="invalid-feedback">Please enter administrative hours.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_instruction_hours" class="form-label fw-semibold">
                                    <i class="bi bi-book-half me-1"></i>Instruction Hours <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="edit_instruction_hours" name="instruction_hours" min="0" value="0" required>
                                <div class="invalid-feedback">Please enter instruction hours.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_research_hours" class="form-label fw-semibold">
                                    <i class="bi bi-search me-1"></i>Research Hours <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="edit_research_hours" name="research_hours" min="0" value="0" required>
                                <div class="invalid-feedback">Please enter research hours.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_extension_hours" class="form-label fw-semibold">
                                    <i class="bi bi-people me-1"></i>Extension Hours <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="edit_extension_hours" name="extension_hours" min="0" value="0" required>
                                <div class="invalid-feedback">Please enter extension hours.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_instructional_functions_hours" class="form-label fw-semibold">
                                    <i class="bi bi-mortarboard me-1"></i>Instructional Functions Hours <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="edit_instructional_functions_hours" name="instructional_functions_hours" min="0" value="0" required>
                                <div class="invalid-feedback">Please enter instructional functions hours.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_consultation_hours" class="form-label fw-semibold">
                                    <i class="bi bi-chat-dots me-1"></i>Consultation Hours <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="edit_consultation_hours" name="consultation_hours" min="0" value="0" required>
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
                                                <p class="mb-1"><strong>Name:</strong> <span id="edit_reviewName">-</span></p>
                                                <p class="mb-1"><strong>Email:</strong> <span id="edit_reviewEmail">-</span></p>
                                                <p class="mb-0"><strong>Phone:</strong> <span id="edit_reviewPhone">-</span></p>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="fw-bold text-dark mb-2">Account Details</h6>
                                                <p class="mb-1"><strong>Username:</strong> <span id="edit_reviewUsername">-</span></p>
                                                <p class="mb-1"><strong>Employment Status:</strong> <span id="edit_reviewStatus">-</span></p>
                                                <p class="mb-1"><strong>Program:</strong> <span id="edit_reviewProgram">-</span></p>
                                                <p class="mb-1"><strong>Rank:</strong> <span id="edit_reviewRank">-</span></p>
                                                <p class="mb-0"><strong>Designation:</strong> <span id="edit_reviewDesignation">-</span></p>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="fw-bold text-dark mb-2">Workload Hours</h6>
                                                <p class="mb-1"><strong>Administrative:</strong> <span id="edit_reviewAdminHours">-</span></p>
                                                <p class="mb-1"><strong>Instruction:</strong> <span id="edit_reviewInstructionHours">-</span></p>
                                                <p class="mb-1"><strong>Research:</strong> <span id="edit_reviewResearchHours">-</span></p>
                                                <p class="mb-1"><strong>Extension:</strong> <span id="edit_reviewExtensionHours">-</span></p>
                                                <p class="mb-1"><strong>Instructional Functions:</strong> <span id="edit_reviewInstFuncHours">-</span></p>
                                                <p class="mb-0"><strong>Consultation:</strong> <span id="edit_reviewConsultationHours">-</span></p>
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
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="edit_prevStepBtn" style="display: none;" onclick="return handleEditAccountPrev(event);">
                        <i class="bi bi-arrow-left me-2"></i>Previous
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-outline-primary rounded-pill px-4" id="edit_nextStepBtn" onclick="return handleEditAccountNext(event);">
                            Next <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                        <button type="button" class="btn btn-success rounded-pill px-4" id="edit_submitBtn" style="display: none;" onclick="return saveAccountEdits(event)">
                            <i class="bi bi-check-lg me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Instructor Schedules Modal -->
<div class="modal fade" id="viewSchedulesModal" tabindex="-1" aria-labelledby="viewSchedulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSchedulesModalLabel">
                    <i class="bi bi-person-circle me-2"></i>User Information
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="schedulesLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading schedules...</p>
                </div>
                <div id="schedulesError" class="alert alert-danger" style="display: none;"></div>
                <div id="schedulesContent" style="display: none;">
                    <div class="mb-3">
                        <h6 id="instructorNameDisplay" class="text-primary"></h6>
                        <small class="text-muted" id="schedulesCount"></small>
                    </div>
                    
                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs mb-3" id="instructorModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="information-tab" data-bs-toggle="tab" data-bs-target="#information-pane" type="button" role="tab" aria-controls="information-pane" aria-selected="true" onclick="handleInformationTabClick()">
                                <i class="bi bi-person-circle me-2"></i>Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule-pane" type="button" role="tab" aria-controls="schedule-pane" aria-selected="false" onclick="handleScheduleTabClick()">
                                <i class="bi bi-calendar-week me-2"></i>Schedule
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="instructorModalTabContent">
                        <!-- Information Tab Pane -->
                        <div class="tab-pane fade show active" id="information-pane" role="tabpanel" aria-labelledby="information-tab">
                            <div class="row g-3">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                            <h6 class="mb-0 text-white"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Full Name</label>
                                                <p class="mb-0 fw-semibold" id="infoFullName">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Username</label>
                                                <p class="mb-0" id="infoUsername">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Email</label>
                                                <p class="mb-0" id="infoEmail">-</p>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label small text-muted mb-1">Department</label>
                                                <p class="mb-0" id="infoDepartment">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Employment Details -->
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                            <h6 class="mb-0 text-white"><i class="bi bi-briefcase me-2"></i>Employment Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Employment Status</label>
                                                <p class="mb-0" id="infoEmploymentStatus">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Rank</label>
                                                <p class="mb-0" id="infoRank">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Designation</label>
                                                <p class="mb-0" id="infoDesignation">-</p>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label small text-muted mb-1">Phone</label>
                                                <p class="mb-0" id="infoPhone">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Workload Summary -->
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                            <h6 class="mb-0 text-white"><i class="bi bi-clock-history me-2"></i>Workload Summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Administration Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoAdministrationHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Instruction Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoInstructionHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Research Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoResearchHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Extension Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoExtensionHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Production Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoProductionHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Consultation Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoConsultationHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Total Hours</label>
                                                    <p class="mb-0 h5 text-dark fw-bold" id="infoTotalHours">-</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Schedule Tab Pane -->
                        <div class="tab-pane fade" id="schedule-pane" role="tabpanel" aria-labelledby="schedule-tab">
                            <!-- Schedule Grid Container -->
                            <div class="calendar-container" style="margin-top: 1rem;">
                                <div class="calendar-grid" style="max-height: 60vh; overflow-y: auto;">
                                    <div class="time-column" id="instructorScheduleTimeColumn" style="position: sticky; left: 0; z-index: 10;">
                                        <div class="calendar-header" style="position: sticky; top: 0; z-index: 11;">Time</div>
                                    </div>
                                    <div class="days-wrapper" id="instructorScheduleDaysWrapper">
                                        <!-- Day columns will be generated here -->
                                    </div>
                                </div>
                            </div>
                            <div id="noSchedules" class="alert alert-info mt-3" style="display: none;">
                                <i class="bi bi-info-circle me-2"></i>No schedules assigned to this instructor.
                            </div>
                        </div>
                        
                        <!-- Information Tab Pane -->
                        <div class="tab-pane fade" id="information-pane" role="tabpanel" aria-labelledby="information-tab">
                            <div class="row g-3">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                            <h6 class="mb-0 text-white"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Full Name</label>
                                                <p class="mb-0 fw-semibold" id="infoFullName">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Username</label>
                                                <p class="mb-0" id="infoUsername">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Email</label>
                                                <p class="mb-0" id="infoEmail">-</p>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label small text-muted mb-1">Department</label>
                                                <p class="mb-0" id="infoDepartment">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Employment Details -->
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                            <h6 class="mb-0 text-white"><i class="bi bi-briefcase me-2"></i>Employment Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Employment Status</label>
                                                <p class="mb-0" id="infoEmploymentStatus">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Rank</label>
                                                <p class="mb-0" id="infoRank">-</p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Designation</label>
                                                <p class="mb-0" id="infoDesignation">-</p>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label small text-muted mb-1">Phone</label>
                                                <p class="mb-0" id="infoPhone">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Workload Summary -->
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-maroon text-white" style="background: linear-gradient(135deg, #800000, #a00000) !important; background-color: #800000 !important;">
                                            <h6 class="mb-0 text-white"><i class="bi bi-clock-history me-2"></i>Workload Summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Administration Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoAdministrationHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Instruction Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoInstructionHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Research Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoResearchHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Extension Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoExtensionHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Production Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoProductionHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Consultation Hours</label>
                                                    <p class="mb-0 h5 text-dark" id="infoConsultationHours">-</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Total Hours</label>
                                                    <p class="mb-0 h5 text-dark fw-bold" id="infoTotalHours">-</p>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-lg px-4" onclick="printInstructorSchedule()">
                    <i class="bi bi-printer me-2"></i>Print
                </button>
                <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Single Print Workload Form Modal -->
<div class="modal fade" id="singlePrintWorkloadModal" tabindex="-1" aria-labelledby="singlePrintWorkloadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="singlePrintWorkloadModalLabel">
                    <i class="bi bi-printer me-2"></i>Print Single Instructor Workload Form
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Please select an instructor from the table below to print their workload form.
                </div>
                <div class="mb-3">
                    <label for="singlePrintInstructorSelect" class="form-label fw-semibold">
                        <i class="bi bi-person me-1"></i>Select Instructor
                    </label>
                    <select class="form-select form-select-lg" id="singlePrintInstructorSelect">
                        <option value="">-- Select an instructor --</option>
                        <!-- Options will be populated dynamically -->
                    </select>
                    <div class="form-text">Only instructors with active schedules are shown.</div>
                </div>
                <div id="selectedInstructorInfo" class="card border-primary" style="display: none;">
                    <div class="card-body">
                        <h6 class="card-title text-primary">
                            <i class="bi bi-person-check me-2"></i>Selected Instructor
                        </h6>
                        <p class="mb-1"><strong>Name:</strong> <span id="selectedInstructorName">-</span></p>
                        <p class="mb-1"><strong>Email:</strong> <span id="selectedInstructorEmail">-</span></p>
                        <p class="mb-0"><strong>Department:</strong> <span id="selectedInstructorDept">-</span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmSinglePrintBtn" onclick="confirmSinglePrint()" disabled>
                    <i class="bi bi-printer me-2"></i>Print Workload Form
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Existing Instructor to My Department Modal -->
<div class="modal fade" id="addExistingInstructorModal" tabindex="-1" aria-labelledby="addExistingInstructorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title" id="addExistingInstructorModalLabel">
                    <i class="bi bi-person-add me-2"></i>Add Existing Instructor to My Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> Search for an existing instructor by name. They will be added to your department while keeping their existing account.
                </div>
                
                <div class="mb-3">
                    <label for="existingInstructorSearch" class="form-label fw-semibold">
                        <i class="bi bi-search me-1"></i>Search Instructor by Name
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="existingInstructorSearch" 
                               placeholder="Type instructor name (at least 2 characters)..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="clearExistingInstructorSearch" style="display: none;">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div id="existingInstructorSearchResults" class="list-group mt-2" style="display: none; max-height: 300px; overflow-y: auto; position: relative; z-index: 1050;"></div>
                </div>
                
                <div id="selectedInstructorInfo" class="card border-primary" style="display: none;">
                    <div class="card-body">
                        <h6 class="card-title text-primary">
                            <i class="bi bi-person-check me-2"></i>Selected Instructor
                        </h6>
                        <p class="mb-1"><strong>Name:</strong> <span id="selectedInstructorName">-</span></p>
                        <p class="mb-1"><strong>Current Departments:</strong> <span id="selectedInstructorDepts">-</span></p>
                        <p class="mb-0 text-muted small">
                            <i class="bi bi-info-circle me-1"></i>This instructor will be added to your department.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="addInstructorToDeptBtn" onclick="addInstructorToDepartment()" disabled>
                    <i class="bi bi-person-plus me-2"></i>Add to My Department
                </button>
            </div>
        </div>
    </div>
</div>
