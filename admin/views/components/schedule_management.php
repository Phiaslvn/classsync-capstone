<?php
/**
 * Schedule Management Component
 * Schedule creation, editing, and management with visual overtime indicators.
 */

// Check if user is an instructor (view-only access)
$userRole = getUserRole();
$isInstructor = ($userRole && strtolower($userRole['role_name']) === 'user');
$canManageSchedules = !$isInstructor && hasPermission('manage_schedules');
?>
 
<div class="dashboard-card schedule-management-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="flex-grow-1" style="min-width: 220px;">
            <h2 class="h4 mb-1">Class schedules</h2>
            <p class="text-muted small mb-2">Weekly timetable by school year, program, and section. Tables below the calendar summarize load and overtime.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-shrink-0">
            <?php if ($canManageSchedules): ?>
                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#copyScheduleModal">
                    <i class="bi bi-copy me-1"></i>Copy Schedules
                </button>
            <?php endif; ?>
            <?php if ($canManageSchedules): ?>
                <button class="btn btn-maroon btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                    <i class="bi bi-calendar-plus me-1"></i>Create Schedule
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters - Hidden for instructors (view-only) -->
    <?php if (!$isInstructor): ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">School Year</label>
            <select class="form-select form-select-sm" id="syFilter"></select>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">Course / Program</label>
            <select class="form-select form-select-sm" id="programFilter">
                <option value="">All Programs</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">Year Level</label>
            <select class="form-select form-select-sm" id="yearLevelFilter">
                <option value="">All Year Levels</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
                <option value="5">5th Year</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">Section</label>
            <select class="form-select form-select-sm" id="sectionFilter">
                <option value="">All Sections</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">Instructor</label>
            <select class="form-select form-select-sm" id="instructorFilter">
                <option value="">All Instructors</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">Room</label>
            <select class="form-select form-select-sm" id="roomFilter">
                <option value="">All Rooms</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <label class="form-label small text-muted mb-1">Day</label>
            <select class="form-select form-select-sm" id="dayFilter">
                <option value="">All Days</option>
                <option value="Mon">Monday</option>
                <option value="Tue">Tuesday</option>
                <option value="Wed">Wednesday</option>
                <option value="Thu">Thursday</option>
                <option value="Fri">Friday</option>
                <option value="Sat">Saturday</option>
                <option value="Sun">Sunday</option>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <!-- Instructor-only: Department filter (filter which department's schedules to show) -->
    <?php if ($isInstructor): ?>
    <div class="row g-2 mb-3" id="instructorDeptFilterRow">
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <label class="form-label small text-muted mb-1" for="instructorDeptFilter">Department</label>
            <select class="form-select form-select-sm" id="instructorDeptFilter" aria-label="Filter schedules by department">
                <option value="">All Departments</option>
                <!-- Options populated from get_schedules response (instructor_departments) -->
            </select>
            <div class="form-text small">Filter which department's schedules to view.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Class Selector Bar -->
    <div class="class-selector-container mb-3" id="classSelectorContainer" <?php if ($isInstructor): ?>style="display: block !important; visibility: visible !important;"<?php endif; ?>>
        <div class="class-selector-wrapper d-flex align-items-center gap-2">
            <div class="class-buttons-container d-flex align-items-center gap-1 flex-grow-1" id="classButtonsContainer">
                <span class="text-white">Loading classes...</span>
            </div>
            <?php if (!$isInstructor): ?>
            <button class="btn btn-secondary class-option-btn" id="classOptionBtn" type="button" data-bs-toggle="modal" data-bs-target="#autoSectionMakerModal">
                <i class="bi bi-list"></i> Option
            </button>
            <?php endif; ?>
            <button class="btn btn-primary class-print-btn" id="classPrintBtn" type="button" onclick="printSchedule()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <?php if (!$isInstructor): ?>
    <!-- Unassigned Subjects Container - Positioned below class selector (admin/moderator scheduling only) -->
    <div id="unassignedSubjectsContainer" class="class-selector-container mb-3 d-none">
        <div class="class-selector-wrapper d-flex align-items-center gap-2">
            <div class="class-buttons-container d-flex align-items-center gap-1 flex-grow-1" id="unassignedSubjectsBody">
                <!-- Unassigned subjects will be displayed here as buttons -->
            </div>
        </div>
    </div>
    <?php endif; ?>

<!-- New Calendar View -->
<div id="calendarView" class="calendar-container mt-4">
    <div id="scheduleSpinner" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading schedules...</p>
    </div>
    
    <div class="calendar-grid" id="scheduleCalendar">
        <div class="time-column" id="timeColumn">
            <!-- Time labels will be generated by JavaScript -->
        </div>
        <div class="days-wrapper" id="daysWrapper">
            <!-- Day columns and schedule blocks will be generated by JavaScript -->
        </div>
    </div>
</div>

<!-- Class Time Load Summary Table -->
<div class="dashboard-card mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h5 mb-0">Class Time Load</h3>
        <button class="btn btn-primary btn-sm" id="classTimeLoadPrintBtn" type="button" onclick="printClassTimeLoad()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="classTimeLoadTable">
            <thead class="table-dark">
                <tr>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Subject Code</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Subject Descriptive Title</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Lec</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Lab</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Units</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Instructor</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Class</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Day</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Time</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Room</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Total Hours</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Remaining Hours</th>
                </tr>
            </thead>
            <tbody id="classTimeLoadTableBody">
                <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x me-2"></i>No schedules found. Please select filters or create a schedule.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Overtime Summary Table -->
<div class="dashboard-card mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h5 mb-0">
            Overtime
        </h3>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="overtimeTable">
            <thead class="table-dark">
                <tr>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Subject Code</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Subject Descriptive Title</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Lec</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Lab</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Units</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Instructor</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Class</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Day</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Time</th>
                    <th class="fw-semibold text-white small text-uppercase table-header-custom">Room</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Total Hours</th>
                    <th class="fw-semibold text-white small text-uppercase text-center table-header-custom">Overtime Hours</th>
                </tr>
            </thead>
            <tbody id="overtimeTableBody">
                <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                        <i class="bi bi-check-circle me-2"></i>No overtime schedules found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    /* Maroon background for EVSU-OCC theme */
    .bg-maroon {
        background-color: #800000 !important;
    }
    
    .calendar-grid {
        position: relative;
        max-height: 75vh; /* Increased for better visibility */
        overflow-y: auto;
        overflow-x: auto;
    }
    
    /* Additional enhancements */
    #calendarView {
        background: #ffffff;
        border-radius: 12px;
        padding: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    /* Loading spinner enhancement */
    #scheduleSpinner {
        min-height: 400px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    #scheduleSpinner .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.3em;
    }
    
    /* Make all filters equal width on large screens */
    @media (min-width: 992px) {
        .row.g-2.mb-3 > div.col-lg {
            flex: 0 0 auto;
            width: 12.5%; /* 100% / 8 filters = 12.5% each */
            max-width: 12.5%;
        }
    }
    
    /* Class Selector Styles - Matching the attached image */
    .class-selector-container {
        background-color: transparent; /* Colorless background */
        padding: 8px 12px;
        border-radius: 0;
        overflow-x: auto;
        box-shadow: none;
    }
    
    /* Always show class selector container for instructors (they need print button) */
    <?php if ($isInstructor): ?>
    #classSelectorContainer {
        display: block !important;
        visibility: visible !important;
    }
    <?php endif; ?>
    
    .class-selector-wrapper {
        min-height: 40px;
        align-items: center;
        gap: 8px;
    }
    
    /* Option A: modern pill "chips" with integrated close button */
    .class-buttons-container {
        overflow-x: auto;
        flex-wrap: nowrap;
        gap: 8px !important;
        padding: 2px 8px 2px 0;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    .class-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid rgba(128, 0, 0, 0.35);
        background: #ffffff;
        color: #800000;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        white-space: nowrap;
        max-width: 100%;
    }

    .class-chip:hover {
        background: #fff5f5;
        border-color: rgba(128, 0, 0, 0.55);
        box-shadow: 0 2px 8px rgba(128, 0, 0, 0.08);
    }

    .class-chip.active {
        background: linear-gradient(135deg, #800000 0%, #660000 100%);
        border-color: #800000;
        color: #ffffff;
        box-shadow: 0 6px 16px rgba(128, 0, 0, 0.18);
    }

    .class-chip__label.class-btn {
        appearance: none;
        background: transparent;
        border: 0;
        color: inherit;
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.1;
        cursor: pointer;
        white-space: nowrap;
    }

    .class-chip__label.class-btn:focus-visible {
        outline: 2px solid rgba(128, 0, 0, 0.5);
        outline-offset: 2px;
        border-radius: 999px;
    }
    
    .class-option-btn {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 0;
        font-size: 13px;
        white-space: nowrap;
        min-width: 80px;
    }
    
    .class-option-btn:hover {
        background-color: #5a6268;
        color: white;
    }
    
    .class-print-btn {
        background-color: #0d6efd;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 0;
        font-size: 13px;
        white-space: nowrap;
        min-width: 80px;
    }
    
    .class-print-btn:hover {
        background-color: #0b5ed7;
        color: white;
    }
    
    /* keep existing scrollbar styling (updated gap/padding above) */
    
    .class-buttons-container::-webkit-scrollbar {
        height: 4px;
    }
    
    .class-buttons-container::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
    }
    
    .class-buttons-container::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.3);
    }
    
    .class-buttons-container::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.5);
    }
    
    /* Close icon inside chip */
    .class-chip__close.section-delete-btn {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px !important;
        border: 0 !important;
        background: transparent !important;
        color: inherit !important;
        opacity: 0.85;
        border-radius: 999px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        transition: background-color 0.15s ease, color 0.15s ease, opacity 0.15s ease, transform 0.15s ease;
        margin: 0 4px 0 0 !important;
        padding: 0 !important;
    }

    .class-chip__close.section-delete-btn i {
        font-size: 14px;
        line-height: 1;
    }

    .class-chip:not(.active) .class-chip__close.section-delete-btn:hover {
        background: rgba(128, 0, 0, 0.10) !important;
        color: #dc3545 !important;
        opacity: 1;
    }

    .class-chip.active .class-chip__close.section-delete-btn:hover {
        background: rgba(255, 255, 255, 0.18) !important;
        opacity: 1;
    }

    .class-chip__close.section-delete-btn:active {
        transform: scale(0.96);
    }

    .class-chip__close.section-delete-btn:focus-visible {
        outline: 2px solid rgba(255, 255, 255, 0.55);
        outline-offset: 2px;
    }
    
    @media print {
        .class-selector-container {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .class-selector-wrapper {
            flex-direction: column;
        }
        
        .class-buttons-container {
            width: 100%;
            overflow-x: auto;
        }
        
        .class-option-btn {
            width: 100%;
        }
    }
    
    /* Unassigned Subjects Container - Uses same styling as class selector */
    .unassigned-subject-item {
        background-color: #8B0000; /* Dark red buttons - same as class buttons */
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 0;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
        min-width: 100px;
        text-align: center;
        position: relative;
    }
    
    .unassigned-subject-item:hover {
        background-color: #A52A2A;
    }
    
    .unassigned-subject-item.active {
        background-color: #8B0000; /* Dark red for active - matches class buttons */
        font-weight: 600;
        box-shadow: inset 0 -2px 0 0 #fff; /* Subtle bottom border effect */
    }
    
    .unassigned-subject-item.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background-color: white;
    }
    
    /* Ensure SweetAlert appears above modals */
    .swal2-container {
        z-index: 1110 !important;
    }
    
    .swal2-popup {
        z-index: 1110 !important;
    }
    
    /* Conflict alert specific styling */
    .conflict-alert-popup {
        z-index: 1110 !important;
    }
    
    /* Success alert modal styling - matching confirmation dialog style */
    .success-alert-popup {
        z-index: 1110 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
        padding: 2rem !important;
    }
    
    .success-alert-title {
        font-size: 1.5rem !important;
        font-weight: 600 !important;
        color: #212529 !important;
        margin-bottom: 0.5rem !important;
    }
    
    .success-alert-content {
        font-size: 1rem !important;
        color: #6c757d !important;
        line-height: 1.5 !important;
    }
    
    .success-alert-button {
        padding: 0.5rem 1.5rem !important;
        font-size: 1rem !important;
        font-weight: 500 !important;
        border-radius: 4px !important;
        transition: all 0.2s ease !important;
    }
    
    .success-alert-button:hover {
        background-color: #218838 !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    }
    
    /* Success icon styling */
    .swal2-success .swal2-icon {
        border-color: #28a745 !important;
        color: #28a745 !important;
    }
    
    .swal2-success .swal2-icon .swal2-success-ring {
        border-color: rgba(40, 167, 69, 0.3) !important;
    }
    
    /* Enhanced Toast Notification Styling */
    .custom-toast-container {
        z-index: 10000 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding-top: 2rem !important;
    }
    
    /* Center toast notifications */
    .swal2-container.swal2-top {
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding-top: 2rem !important;
    }
    
    .swal2-container.swal2-top .swal2-popup {
        margin: 0 auto !important;
    }
    
    .custom-toast-popup {
        border-radius: 12px !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1) !important;
        padding: 1.5rem 2rem !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
        animation: slideInDown 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
        backdrop-filter: blur(10px);
        margin: 0 auto !important;
    }
    
    .custom-toast-title {
        font-size: 1.1rem !important;
        font-weight: 600 !important;
        line-height: 1.6 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .custom-toast-icon {
        width: 3rem !important;
        height: 3rem !important;
        margin: 0 1rem 0 0 !important;
    }
    
    .custom-toast-progress {
        background: rgba(0, 0, 0, 0.1) !important;
        height: 3px !important;
        border-radius: 0 0 12px 12px !important;
    }
    
    /* Success Toast */
    .swal2-toast.swal2-success .custom-toast-popup {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
        border: 2px solid #28a745 !important;
        color: #155724 !important;
    }
    
    .swal2-toast.swal2-success .swal2-icon {
        color: #28a745 !important;
        border-color: #28a745 !important;
    }
    
    /* Error Toast */
    .swal2-toast.swal2-error .custom-toast-popup {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
        border: 2px solid #dc3545 !important;
        color: #721c24 !important;
    }
    
    .swal2-toast.swal2-error .swal2-icon {
        color: #dc3545 !important;
        border-color: #dc3545 !important;
    }
    
    /* Warning Toast */
    .swal2-toast.swal2-warning .custom-toast-popup {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
        border: 2px solid #ffc107 !important;
        color: #856404 !important;
    }
    
    .swal2-toast.swal2-warning .swal2-icon {
        color: #ffc107 !important;
        border-color: #ffc107 !important;
    }
    
    /* Info Toast */
    .swal2-toast.swal2-info .custom-toast-popup {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%) !important;
        border: 2px solid #17a2b8 !important;
        color: #0c5460 !important;
    }
    
    .swal2-toast.swal2-info .swal2-icon {
        color: #17a2b8 !important;
        border-color: #17a2b8 !important;
    }
    
    /* Toast Animation */
    @keyframes slideInDown {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    /* Toast Hover Effect */
    .custom-toast-popup:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2), 0 6px 12px rgba(0, 0, 0, 0.15) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    
    /* Class Time Load Table Styling - Matching Overtime table style exactly */
    #classTimeLoadTable {
        font-size: 0.875rem;
    }
    
    #classTimeLoadTable thead.table-dark {
        background-color: #800000 !important;
    }
    
    #classTimeLoadTable thead.table-dark th {
        background-color: #800000 !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    #classTimeLoadTable tbody tr {
        background-color: #fff5f5;
    }
    
    #classTimeLoadTable tbody tr:hover {
        background-color: #ffe0e0;
    }
    
    /* Overtime Table Styling */
    #overtimeTable {
        font-size: 0.875rem;
    }
    
    #overtimeTable thead.table-dark {
        background-color: #800000 !important;
    }
    
    #overtimeTable thead.table-dark th {
        background-color: #800000 !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    #overtimeTable tbody tr {
        background-color: #fff5f5;
    }
    
    #overtimeTable tbody tr:hover {
        background-color: #ffe0e0;
    }
    
    /* Responsive table styling - Apply to both tables */
    @media (max-width: 768px) {
        #classTimeLoadTable,
        #overtimeTable {
            font-size: 0.75rem;
        }
        
        #classTimeLoadTable thead th,
        #classTimeLoadTable tbody td,
        #overtimeTable thead th,
        #overtimeTable tbody td {
            padding: 0.5rem 0.25rem;
        }
    }
    
    /* Enhanced Day Checkbox Styling - User-Friendly Design */
    .day-checkbox-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        transition: border-color 0.2s ease;
    }
    
    .day-checkbox-container:has(.day-checkbox:checked) {
        border-color: #800000;
        background: #fff5f5;
    }
    
    .day-checkbox-item {
        margin: 0;
        padding: 0;
        flex: 1 1 auto;
        min-width: 100px;
    }
    
    .day-checkbox-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1rem;
        margin: 0;
        cursor: pointer;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        background: #ffffff;
        transition: all 0.2s ease;
        min-height: 70px;
        width: 100%;
        font-weight: 500;
        color: #495057;
    }
    
    .day-checkbox-label:hover {
        border-color: #800000;
        background: #fff5f5;
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(128, 0, 0, 0.15);
    }
    
    .day-checkbox:checked + .day-checkbox-label {
        background: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
        border-color: #800000;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        transform: translateY(-2px);
    }
    
    .day-checkbox:focus + .day-checkbox-label {
        outline: 2px solid #800000;
        outline-offset: 2px;
    }
    
    .day-checkbox {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        margin: 0;
    }
    
    .day-abbr {
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }
    
    .day-full {
        font-size: 0.75rem;
        opacity: 0.9;
        text-align: center;
    }
    
    .day-checkbox:checked + .day-checkbox-label .day-abbr,
    .day-checkbox:checked + .day-checkbox-label .day-full {
        color: #ffffff;
    }
    
    /* Responsive design for day checkboxes */
    @media (max-width: 768px) {
        .day-checkbox-item {
            min-width: calc(50% - 0.375rem);
        }
        
        .day-checkbox-label {
            min-height: 60px;
            padding: 0.5rem 0.75rem;
        }
        
        .day-abbr {
            font-size: 0.8rem;
        }
        
        .day-full {
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 576px) {
        .day-checkbox-item {
            min-width: 100%;
        }
        
        .day-checkbox-label {
            flex-direction: row;
            justify-content: flex-start;
            gap: 0.75rem;
            min-height: 50px;
        }
        
        .day-abbr {
            margin-bottom: 0;
            min-width: 45px;
        }
    }
    
    /* Utility Classes to Replace Inline Styles */
    .form-disabled {
        pointer-events: none;
        opacity: 0.6;
    }
    
    .help-text-normal {
        font-weight: normal;
    }
    
    /* Table Header Styling - Clean CSS Class */
    .table-header-custom {
        background: transparent !important;
        background-color: transparent !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Standardized Form Label Styling */
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
    }
    
    .form-label i {
        color: #800000;
        margin-right: 0.375rem;
    }
    
    .form-label .text-danger {
        margin-left: 0.25rem;
    }
    
    /* Form Label Small Variant (for filters) */
    .form-label-sm {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    
    /* Copy Schedule Modal Enhancements - EVSU-OCC Theme Colors */
    #copyScheduleModal .modal-lg {
        max-width: 800px;
    }
    
    /* Table header - Maroon theme */
    #copyScheduleModal .table thead {
        background-color: #800000 !important;
        color: #ffffff !important;
    }
    
    #copyScheduleModal .table thead th {
        background-color: #800000 !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        white-space: nowrap;
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    /* Table body - Light maroon tint */
    #copyScheduleModal .table tbody tr {
        background-color: #fff5f5;
    }
    
    #copyScheduleModal .table td {
        vertical-align: middle;
        font-size: 0.875rem;
        border-color: rgba(128, 0, 0, 0.1);
    }
    
    /* Hover state - Slightly darker maroon tint */
    #copyScheduleModal .table tbody tr:hover {
        background-color: #ffe0e0;
    }
    
    /* Checked row - Medium maroon tint */
    #copyScheduleModal .table tbody tr:has(input:checked) {
        background-color: #ffcccc;
    }
    
    /* Checked row hover - Darker maroon tint */
    #copyScheduleModal .table tbody tr:has(input:checked):hover {
        background-color: #ffb3b3;
    }
    
    #copyScheduleModal .table-responsive {
        border: 1px solid rgba(128, 0, 0, 0.2);
        border-radius: 0.375rem;
    }
    
    #copyScheduleModal .text-maroon {
        color: #800000 !important;
        font-weight: 600;
    }
    
    /* Button styling - Maroon theme */
    #copyScheduleModal .btn-outline-danger {
        border-color: #800000;
        color: #800000;
    }
    
    #copyScheduleModal .btn-outline-danger:hover {
        background-color: #800000;
        border-color: #800000;
        color: #ffffff;
    }
    
    /* Alert info box - Maroon theme */
    #copyScheduleModal .alert {
        background-color: #fff5f5;
        border-color: #800000;
        color: #800000;
    }
    
    /* Instructor Search Styling */
    #instructorSearchResults {
        margin-top: 0.25rem;
    }
    
    .instructor-search-item {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    
    .instructor-search-item:hover {
        background-color: #f8f9fa;
    }
    
    .instructor-search-item h6 {
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 0;
        color: #212529;
    }
    
    .instructor-search-item small {
        font-size: 0.75rem;
    }
    
    #instructorSearch:focus {
        border-color: #800000;
        box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
    }
    
    /* Position search results relative to input group */
    .input-group {
        position: relative;
    }
    
    #instructorSearchResults {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        margin-top: 0.25rem;
        z-index: 1050;
    }
    
    /* Schedule Modal Compact Styling */
    #scheduleModal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
        padding: 1rem;
    }
    
    #scheduleModal .modal-body::-webkit-scrollbar {
        width: 8px;
    }
    
    #scheduleModal .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    #scheduleModal .modal-body::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    #scheduleModal .modal-body::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    #scheduleModal .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }
    
    #scheduleModal .form-label.small {
        font-size: 0.8rem;
    }
    
    #scheduleModal .form-select-sm,
    #scheduleModal .form-control-sm {
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
        line-height: 1.5;
    }
    
    #scheduleModal .day-checkbox-container {
        padding: 0.5rem;
        gap: 0.5rem;
    }
    
    #scheduleModal .day-checkbox-label {
        min-height: 55px;
        padding: 0.5rem 0.75rem;
    }
    
    #scheduleModal .day-abbr {
        font-size: 0.8rem;
    }
    
    #scheduleModal .day-full {
        font-size: 0.7rem;
    }
</style>
 
</div>
 
<!-- Add/Edit Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Create Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1rem;">
                <?php if (!$canManageSchedules): ?>
                    <div class="alert alert-info py-2 mb-2">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>View-Only Access:</strong> You have view-only access to schedules. Creating or editing schedules is not permitted.
                    </div>
                <?php endif; ?>
                <form id="scheduleForm">
                    <input type="hidden" id="schd_id" name="schd_id">
                    <div class="row g-2<?php if (!$canManageSchedules): ?> form-disabled<?php endif; ?>">
                        <div class="col-md-6">
                            <label for="sy_id" class="form-label small mb-1">
                                <i class="bi bi-calendar-year me-1"></i>School Year <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="sy_id" name="sy_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="schd_term" class="form-label small mb-1">
                                <i class="bi bi-calendar3 me-1"></i>Term <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="schd_term" name="schd_term" required>
                                <option value="">Select Term</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="program_id" class="form-label small mb-1">
                                <i class="bi bi-book me-1"></i>Course/Program <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="program_id" name="program_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="year_level" class="form-label small mb-1">
                                <i class="bi bi-123 me-1"></i>Year Level <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="year_level" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="sec_id" class="form-label small mb-1">
                                <i class="bi bi-people me-1"></i>Section <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="sec_id" name="sec_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="inst_id" class="form-label small mb-1">
                                <i class="bi bi-person-badge me-1"></i>Instructor <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="inst_id" name="inst_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="subj_id" class="form-label small mb-1">
                                <i class="bi bi-journal-text me-1"></i>Subject <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="subj_id" name="subj_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <div class="position-relative">
                                <label class="form-label small mb-1" style="font-size: 0.7rem; color: #6c757d;">
                                    <i class="bi bi-search me-1"></i>Search instructor from other departments
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control form-control-sm" id="instructorSearch" 
                                           placeholder="Search instructor by name..." autocomplete="off" style="font-size: 0.8rem;">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="clearInstructorSearch" style="font-size: 0.8rem;">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                <div id="instructorSearchResults" class="list-group mt-1" style="display: none; max-height: 150px; overflow-y: auto; position: absolute; z-index: 1050; width: 100%;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="rm_id" class="form-label small mb-1">
                                <i class="bi bi-building me-1"></i>Room <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="rm_id" name="rm_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="schd_type" class="form-label small mb-1">
                                <i class="bi bi-tag me-1"></i>Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="schd_type" name="schd_type" required>
                                <option value="Lec">Lecture</option>
                                <option value="Lab">Laboratory</option>
                                <option value="Special">Special</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small mb-1">
                                <i class="bi bi-calendar-week me-1"></i>Day <span class="text-danger">*</span>
                                <small class="text-muted d-block mt-0" style="font-size: 0.7rem;">Select one or more days for this schedule</small>
                            </label>
                            <div class="day-checkbox-container" style="padding: 0.5rem; gap: 0.5rem;">
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_mon" name="schd_day[]" value="Mon">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_mon" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Mon</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Monday</span>
                                    </label>
                                </div>
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_tue" name="schd_day[]" value="Tue">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_tue" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Tue</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Tuesday</span>
                                    </label>
                                </div>
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_wed" name="schd_day[]" value="Wed">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_wed" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Wed</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Wednesday</span>
                                    </label>
                                </div>
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_thu" name="schd_day[]" value="Thu">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_thu" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Thu</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Thursday</span>
                                    </label>
                                </div>
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_fri" name="schd_day[]" value="Fri">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_fri" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Fri</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Friday</span>
                                    </label>
                                </div>
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_sat" name="schd_day[]" value="Sat">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_sat" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Sat</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Saturday</span>
                                    </label>
                                </div>
                                <div class="form-check day-checkbox-item">
                                    <input class="form-check-input day-checkbox" type="checkbox" id="schd_day_sun" name="schd_day[]" value="Sun">
                                    <label class="form-check-label day-checkbox-label" for="schd_day_sun" style="min-height: 55px; padding: 0.5rem 0.75rem;">
                                        <span class="day-abbr" style="font-size: 0.8rem;">Sun</span>
                                        <span class="day-full" style="font-size: 0.7rem;">Sunday</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="schd_start" class="form-label small mb-1">
                                <i class="bi bi-clock me-1"></i>Start Time <span class="text-danger">*</span>
                            </label>
                            <input type="time" class="form-control form-control-sm" id="schd_start" name="schd_start" required>
                        </div>
                        <div class="col-md-4">
                            <label for="schd_end" class="form-label small mb-1">
                                <i class="bi bi-clock-fill me-1"></i>End Time <span class="text-danger">*</span>
                            </label>
                            <input type="time" class="form-control form-control-sm" id="schd_end" name="schd_end" required>
                        </div>
                        <div class="col-md-4">
                            <label for="schd_status" class="form-label small mb-1">
                                <i class="bi bi-toggle-on me-1"></i>Status
                            </label>
                            <select class="form-select form-select-sm" id="schd_status" name="schd_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <?php if ($canManageSchedules): ?>
                    <button type="button" class="btn btn-danger me-auto" id="removeScheduleBtn" onclick="handleRemoveSchedule()">
                        <i class="bi bi-trash me-1"></i>Delete Schedule
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <?php if ($canManageSchedules): ?>
                    <button type="button" class="btn btn-maroon" id="saveScheduleBtn">Save Schedule</button>
                <?php endif; ?>
            </div>
 
        </div>
    </div>
</div>

<!-- Copy Schedules Modal -->
<div class="modal fade" id="copyScheduleModal" tabindex="-1" aria-labelledby="copyScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copyScheduleModalLabel">Copy Schedules to New Term</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="copyScheduleForm">
                    <div class="mb-3">
                        <label class="form-label"><strong>Source</strong></label>
                        <div class="row g-2">
                            <div class="col">
                                <label for="source_sy_id" class="form-label-sm">School Year</label>
                                <select class="form-select" id="source_sy_id" name="source_sy_id" required></select>
                            </div>
                            <div class="col">
                                <label for="source_term" class="form-label-sm">Term</label>
                                <select class="form-select" id="source_term" name="source_term" required>
                                    <option value="">Select Term</option>
                                    <option value="1">1st Term</option>
                                    <option value="2">2nd Term</option>
                                    <option value="3">Summer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="copyByInstructorCheck">
                            <label class="form-check-label" for="copyByInstructorCheck">
                                Optional: Copy for a specific instructor only
                            </label>
                        </div>
                    </div>
                    <div class="mb-3" id="instructorCopyContainer" style="display: none;">
                        <label for="copy_instructor_id" class="form-label">
                            <i class="bi bi-person-badge me-1"></i>Select Instructor
                        </label>
                        <select class="form-select" id="copy_instructor_id" name="instructor_id">
                            <option value="">Select Instructor</option>
                        </select>
                        <small class="form-text text-muted">Select an instructor to filter and copy only their schedules</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Destination</strong></label>
                        <div class="row g-2">
                            <div class="col">
                                <label for="dest_sy_id" class="form-label-sm">School Year</label>
                                <select class="form-select" id="dest_sy_id" name="dest_sy_id" required></select>
                            </div>
                            <div class="col">
                                <label for="dest_term" class="form-label-sm">Term</label>
                                <select class="form-select" id="dest_term" name="dest_term" required>
                                          <option value="">Select Term</option>
                                    <option value="1">1st Term</option>
                                    <option value="2">2nd Term</option>
                                    <option value="3">Summer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="programSubstitutionCheck">
                            <label class="form-check-label" for="programSubstitutionCheck">
                                <strong>Map to different program</strong> (Copy schedules to a different program in destination)
                            </label>
                        </div>
                        <small class="form-text text-muted">Enable this if you want to copy schedules from one program to another. Sections will be matched by name and year level only.</small>
                    </div>
                    <div class="mb-3" id="programSubstitutionContainer" style="display: none;">
                        <label for="dest_program_id" class="form-label">
                            <i class="bi bi-diagram-3 me-1"></i>Destination Program <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="dest_program_id" name="dest_program_id">
                            <option value="">Select Destination Program</option>
                        </select>
                        <small class="form-text text-muted">Select the program whose sections should be used in the destination term. Schedules will be assigned to this program.</small>
                    </div>
                    
                    <!-- Source Schedules Selection -->
                    <div class="mb-3" id="sourceSchedulesContainer" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0"><strong>Select Schedules to Copy</strong></label>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-danger" id="selectAllSchedulesBtn">
                                    <i class="bi bi-check-square me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-danger" id="deselectAllSchedulesBtn">
                                    <i class="bi bi-square me-1"></i>Deselect All
                                </button>
                            </div>
                        </div>
                        <div class="alert mb-2 py-2" style="background-color: #fff5f5; border-color: #800000; color: #800000;">
                            <small><i class="bi bi-info-circle me-1"></i><span id="schedulesCount">0</span> schedule(s) found. Select the schedules you want to copy.</small>
                        </div>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="sticky-top" style="background-color: #800000; color: #ffffff;">
                                    <tr>
                                        <th style="width: 40px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">
                                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                        </th>
                                        <th style="min-width: 130px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Subject</th>
                                        <th style="min-width: 100px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Program</th>
                                        <th style="min-width: 50px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Year</th>
                                        <th style="min-width: 70px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Section</th>
                                        <th style="min-width: 130px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Instructor</th>
                                        <th style="min-width: 70px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Day</th>
                                        <th style="min-width: 130px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Time</th>
                                        <th style="min-width: 100px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Room</th>
                                        <th style="min-width: 60px; background-color: #800000; color: #ffffff; border-color: rgba(255, 255, 255, 0.1);">Type</th>
                                    </tr>
                                </thead>
                                <tbody id="sourceSchedulesTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-3">
                                            <i class="bi bi-calendar-x me-2"></i>Select source school year and term to load schedules
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-maroon" id="executeCopyBtn" disabled>Execute Copy</button>
            </div>
        </div>
    </div>
</div>

<!-- Auto Section Maker Modal -->
<div class="modal fade" id="autoSectionMakerModal" tabindex="-1" aria-labelledby="autoSectionMakerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title" id="autoSectionMakerModalLabel">
                    <i class="bi bi-magic me-2"></i>Auto Section Maker
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle me-2 fs-5"></i>
                    <div>
                        <strong>Auto-Generate Sections:</strong> This tool will automatically create sections (A, B, C, etc.) for the selected class. If the class doesn't exist, it will be created first.
                    </div>
                </div>
                
                <form id="autoSectionMakerForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="autoSyId" class="form-label">
                                <i class="bi bi-calendar-year me-1"></i>School Year <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="autoSyId" name="sy_id" required>
                                <option value="">Select School Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="autoTerm" class="form-label">
                                <i class="bi bi-calendar3 me-1"></i>Term <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="autoTerm" name="term" required>
                                <option value="">Select Term</option>
                                <option value="1">1st Term</option>
                                <option value="2">2nd Term</option>
                                <option value="3">Summer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="autoProgramId" class="form-label">
                                <i class="bi bi-book me-1"></i>Course/Program <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="autoProgramId" name="program_id" required>
                                <option value="">Select Program</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="autoYearLevel" class="form-label">
                                <i class="bi bi-123 me-1"></i>Year Level <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="autoYearLevel" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="autoNumSections" class="form-label">
                                <i class="bi bi-list-ol me-1"></i>Number of Sections to Generate <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="autoNumSections" name="num_sections" 
                                   min="1" max="26" value="3" required placeholder="Enter number of sections (1-26)">
                            <small class="form-text text-muted">Sections will be automatically named: A, B, C, D, etc.</small>
                        </div>
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-eye me-2"></i>Preview
                                    </h6>
                                    <div id="sectionPreview" class="text-muted">
                                        Select the fields above to see a preview of sections that will be created.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-maroon" id="generateSectionsBtn">
                    <i class="bi bi-magic me-1"></i>Generate Sections
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Add Section Modal removed - using the one from room_management.php to avoid duplicate IDs -->
