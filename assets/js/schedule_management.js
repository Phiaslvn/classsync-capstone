/**
 * Schedule Management JavaScript
 * Handles all client-side logic for the schedule management component.
 */

/**
 * @type {Array<Object>}
 */
window.schedulesData = [];
window.allSchedulesData = []; // Store all schedules (without section filter) for remaining hours calculation
// Stores the last filters used when loading unassigned subjects
window.lastUnassignedSubjectsFilters = null;

/**
 * Determines the correct API base path based on the current page URL.
 * This ensures that API calls work correctly from any dashboard (admin, moderator, instructor).
 * @returns {string} The correct relative base path to the management API.
 */
/**
 * Cleans up modal backdrops and restores page state
 * This fixes issues where modals leave behind backdrops or body styles
 */
function cleanupModalBackdrop() {
    // Remove all modal backdrops
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    
    // Restore body styles (Bootstrap modals add overflow: hidden and padding-right)
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Remove modal-open class
    document.body.classList.remove('modal-open');
}

function getApiBasePath() {
    // Try to get base path from a global variable set by PHP (most reliable)
    if (typeof window.API_BASE_PATH !== 'undefined' && window.API_BASE_PATH) {
        return window.API_BASE_PATH;
    }
    
    const path = window.location.pathname;
    
    // Calculate base path from current location
    // Find the position of 'views' in the path to determine depth
    const viewsIndex = path.indexOf('/views/');
    
    if (viewsIndex !== -1) {
        // We're in a views subdirectory, calculate relative path
        // Count how many levels deep we are
        const pathAfterViews = path.substring(viewsIndex + 7); // +7 for '/views/'
        const depth = pathAfterViews.split('/').filter(p => p).length;
        
        // Build relative path: go up (depth + 1) levels, then to admin/management
        // depth + 1 because we need to go up from views/ to root, then to admin/management
        let relativePath = '';
        for (let i = 0; i <= depth; i++) {
            relativePath += '../';
        }
        return relativePath + 'admin/management/';
    }
    
    // Fallback: try to detect from pathname patterns
    if (path.includes('/views/admin/')) {
        return '../../admin/management/';
    }
    if (path.includes('/views/moderator/')) {
        return '../../admin/management/';
    }
    if (path.includes('/views/instructor/')) {
        return '../../admin/management/';
    }
    
    // Last resort: try to use absolute path based on document root
    // Extract base path from current URL
    const pathParts = path.split('/').filter(p => p);
    const adminIndex = pathParts.indexOf('admin');
    if (adminIndex !== -1) {
        // Build path from root to admin/management
        let basePath = '/';
        for (let i = 0; i < adminIndex; i++) {
            basePath += pathParts[i] + '/';
        }
        return basePath + 'admin/management/';
    }
    
    // Default fallback - use absolute path if we can't determine relative
    // Try to construct absolute path
    const pathToRoot = path.substring(0, path.lastIndexOf('/'));
    const levelsUp = (pathToRoot.match(/\//g) || []).length;
    if (levelsUp > 0) {
        let relativePath = '';
        for (let i = 0; i < levelsUp; i++) {
            relativePath += '../';
        }
        return relativePath + 'admin/management/';
    }
    
    return '../admin/management/';
}

/**
 * Instructor schedule UI omits #syFilter (view-only); same signal as loadClassSelector/renderClassButtons.
 * @returns {boolean}
 */
function isInstructorScheduleView() {
    const syFilterEl = document.getElementById('syFilter');
    return !syFilterEl || syFilterEl.offsetParent === null;
}

document.addEventListener('DOMContentLoaded', function () {
    generateCalendarTimeSlots();
    
    // Reset copy schedule modal when it's shown
    const copyScheduleModal = document.getElementById('copyScheduleModal');
    if (copyScheduleModal) {
        copyScheduleModal.addEventListener('show.bs.modal', function() {
            // Reset program substitution
            $('#programSubstitutionCheck').prop('checked', false);
            $('#programSubstitutionContainer').hide();
            $('#dest_program_id').prop('required', false).val('');
            // Reset the schedules container
            $('#sourceSchedulesContainer').hide();
            $('#sourceSchedulesTableBody').html(`
                <tr>
                    <td colspan="10" class="text-center text-muted py-3">
                        <i class="bi bi-calendar-x me-2"></i>Select source school year and term to load schedules
                    </td>
                </tr>
            `);
            $('#executeCopyBtn').prop('disabled', true);
            $('#selectAllCheckbox').prop('checked', false);
            // Reset instructor filter
            $('#copyByInstructorCheck').prop('checked', false);
            $('#instructorCopyContainer').hide();
            $('#copy_instructor_id').val('').prop('required', false);
        });
    }

    // Global modal cleanup - handles all modals closing
    document.addEventListener('hidden.bs.modal', function(e) {
        // Small delay to ensure Bootstrap has finished its cleanup
        setTimeout(() => {
            cleanupModalBackdrop();
        }, 100);
    });
    
    // Listen for school year updates to refresh schedule form data
    document.addEventListener('schoolYearUpdated', function() {
        console.log('School year updated event detected, refreshing schedule form data...');
        // Refresh schedule form data to update school year dropdown
        loadScheduleFormData().then(() => {
            console.log('Schedule form data refreshed after school year update');
            // Reload class selector to show sections for the new school year/semester
            loadClassSelector();
            // Also refresh section filter if school year filter is visible
            const syFilter = $('#syFilter');
            if (syFilter.length && syFilter.is(':visible')) {
                loadSectionFilter();
            }
            // Reload schedules with updated filters
            loadSchedules();
        });
    });
    
    // Expose global function to refresh schedule form data (can be called from other scripts)
    window.refreshScheduleFormData = function() {
        return loadScheduleFormData();
    };

    // Check if schedule component is visible (not in a hidden tab)
    const scheduleTab = document.getElementById('schedule');
    const isScheduleTabVisible = scheduleTab && scheduleTab.style.display !== 'none';
    
    // Load data for filters and modal dropdowns
    // For instructors, form data might not be needed (they can only view, not create/edit)
    loadScheduleFormData().then(() => {
        // Load class selector buttons first (always visible)
        loadClassSelector();
        // Load section filter based on initial School Year selection
        // Note: loadSectionFilter will handle loading unassigned subjects if a section is restored
        loadSectionFilter();
        // Only auto-load schedules if the schedule tab is currently visible
        // Otherwise, schedules will be loaded when the tab is shown
        if (isScheduleTabVisible) {
            loadSchedules();
            // Also check if a section is already selected and load unassigned subjects
            const initialSection = $('#sectionFilter').val();
            if (initialSection) {
                const filters = {
                    sy: $('#syFilter').val() || '',
                    term: window.currentActiveTerm || '',
                    program: $('#programFilter').val() || '',
                    year_level: $('#yearLevelFilter').val() || '',
                    section: initialSection
                };
                // Small delay to ensure section filter is populated first
                setTimeout(() => {
                    loadUnassignedSubjects(filters);
                }, 500);
            }
        } else {
            console.log('Schedule tab is hidden, will load when tab is shown');
        }
    }).catch(error => {
        console.warn('Could not load schedule form data (this is OK for instructors who only view schedules):', error);
        // For instructors, we can still load schedules even if form data fails
        // They don't need form data to view their schedules
        if (isScheduleTabVisible) {
            loadSchedules();
        }
    });

    // Flag to prevent change events when setting values programmatically
    let isSettingFiltersProgrammatically = false;
    
    // Add event listeners to filters
    $('#syFilter, #programFilter, #yearLevelFilter, #sectionFilter, #instructorFilter, #roomFilter, #dayFilter').on('change', function () {
        // Skip if we're setting filters programmatically (prevents race conditions)
        if (isSettingFiltersProgrammatically) {
            return;
        }
        
        loadSchedules();
        // Reload class selector when school year or program changes
        if (this.id === 'syFilter' || this.id === 'programFilter') {
            loadClassSelector();
            // Reload section filter when school year changes
            if (this.id === 'syFilter') {
                loadSectionFilter();
            }
        }
        
        // Load unassigned subjects when section filter changes
        if (this.id === 'sectionFilter') {
            // Don't hide if we're reloading the section filter (it's temporary)
            if (window.isReloadingSectionFilter) {
                return;
            }
            
            const filters = {
                sy: $('#syFilter').val() || '',
                term: window.currentActiveTerm || '',
                program: $('#programFilter').val() || '',
                year_level: $('#yearLevelFilter').val() || '',
                section: $('#sectionFilter').val() || ''
            };
            if (filters.section) {
                loadUnassignedSubjects(filters);
            } else {
                hideUnassignedSubjects();
            }
        }
        
        // Reload unassigned subjects when year level filter changes (if section is selected)
        if (this.id === 'yearLevelFilter' && $('#sectionFilter').val()) {
            const filters = {
                sy: $('#syFilter').val() || '',
                term: window.currentActiveTerm || '',
                program: $('#programFilter').val() || '',
                year_level: $('#yearLevelFilter').val() || '',
                section: $('#sectionFilter').val() || ''
            };
            console.log('Year Level filter changed, reloading unassigned subjects with filters:', filters);
            loadUnassignedSubjects(filters);
        }
    });
    
    // Auto Section Maker Modal handlers
    $('#autoSectionMakerModal').on('show.bs.modal', function() {
        loadAutoSectionMakerData();
    });
    
    // Update preview when fields change
    $('#autoSyId, #autoTerm, #autoProgramId, #autoYearLevel, #autoNumSections').on('change input', function() {
        updateSectionPreview();
    });
    
    // Also update preview when program dropdown changes (to get program code)
    $('#autoProgramId').on('change', function() {
        updateSectionPreview();
    });
    
    // Generate sections button
    $('#generateSectionsBtn').on('click', function() {
        generateSections();
    });

    // Handle modal opening for adding a new schedule
    $('#scheduleModal').on('show.bs.modal', function (e) {
        // Only reset the form if the modal was triggered by a data-bs-toggle button (i.e., the "Create" button)
        if (e.relatedTarget) {
            $('#scheduleForm')[0].reset();
            $('#schd_id').val('');
            $('#scheduleModalLabel').text('Create Schedule');
            $('#schd_status').closest('.col-md-6').hide(); // Hide status field for new schedules
            // Hide Remove button and change Save button text for new schedule
            $('#removeScheduleBtn').hide();
            $('#saveScheduleBtn').text('Save Schedule');
            
            // Clear stored edit schedule data
            window.editScheduleData = null;
            
            // Auto-fill school year and term based on active settings
            // Use stored values if available, otherwise load form data
            const autoFillSYAndTerm = () => {
                const currentSY = window.currentActiveSY;
                const currentTerm = window.currentActiveTerm;
                
                if (currentSY && currentTerm) {
                    // Set school year
                    const sySelect = $('#sy_id');
                    if (sySelect.find(`option[value="${currentSY}"]`).length > 0) {
                        sySelect.val(currentSY);
                        sySelect.trigger('change.cascade');
                        
                        // Wait for terms to load, then set term
                        setTimeout(() => {
                            const termSelect = $('#schd_term');
                            // Check if term option exists, if not add default terms
                            if (termSelect.find(`option[value="${currentTerm}"]`).length === 0) {
                                // Add default term options if not already present
                                if (termSelect.find('option[value="1"]').length === 0) {
                                    termSelect.append('<option value="1">1st Term</option>');
                                }
                                if (termSelect.find('option[value="2"]').length === 0) {
                                    termSelect.append('<option value="2">2nd Term</option>');
                                }
                                if (termSelect.find('option[value="3"]').length === 0) {
                                    termSelect.append('<option value="3">Summer</option>');
                                }
                            }
                            
                            // Set the term value
                            if (termSelect.find(`option[value="${currentTerm}"]`).length > 0) {
                                termSelect.val(currentTerm);
                                termSelect.trigger('change.cascade');
                                console.log('Auto-filled school year:', currentSY, 'and term:', currentTerm);
                            }
                        }, 300); // Wait for terms to load from API
                    }
                }
            };
            
            // Try to auto-fill immediately if data is already loaded
            if (window.currentActiveSY && window.currentActiveTerm) {
                // Small delay to ensure dropdowns are populated
                setTimeout(autoFillSYAndTerm, 100);
            } else {
                // Load form data first, then auto-fill
                loadScheduleFormData().then(data => {
                    if (data && data.current_sy_id && data.current_term) {
                        window.currentActiveSY = data.current_sy_id;
                        window.currentActiveTerm = data.current_term;
                        setTimeout(autoFillSYAndTerm, 200);
                    }
                }).catch(error => {
                    console.warn('Could not auto-fill school year and term:', error);
                });
            }
        }
        
        // Ensure schedule type dropdown is always enabled with all options available
        // This allows selecting Laboratory even if subject has subj_lab = 0
        const scheduleTypeSelect = $('#schd_type');
        scheduleTypeSelect.find('option').prop('disabled', false);
        scheduleTypeSelect.prop('disabled', false);
        
        // Set up cascading dropdown handlers
        setupCascadingDropdowns();
    });

    // Handle form submission
    $('#saveScheduleBtn').on('click', function () {
        saveSchedule();
    });

    // Handle copy schedule execution
    $('#executeCopyBtn').on('click', function() {
        executeCopySchedules();
    });

    // Handle the optional instructor filter in the copy modal
    $('#copyByInstructorCheck').on('change', function() {
        if ($(this).is(':checked')) {
            $('#instructorCopyContainer').slideDown();
            $('#copy_instructor_id').prop('required', true);
            // Ensure instructor dropdown is populated
            if ($('#copy_instructor_id option').length <= 1) {
                // Reload form data to populate instructor dropdown
                loadScheduleFormData().then(() => {
                    console.log('Instructor dropdown populated for copy modal');
                });
            }
        } else {
            $('#instructorCopyContainer').slideUp();
            $('#copy_instructor_id').prop('required', false).val('');
        }
        // Reload schedules if source is already selected
        if ($('#source_sy_id').val() && $('#source_term').val()) {
            loadSourceSchedules();
        }
    });
    
    // Handle program substitution checkbox
    $('#programSubstitutionCheck').on('change', function() {
        if ($(this).is(':checked')) {
            $('#programSubstitutionContainer').slideDown();
            $('#dest_program_id').prop('required', true);
            // Load programs if not already loaded
            if ($('#dest_program_id option').length <= 1) {
                loadScheduleFormData().then((data) => {
                    if (data && data.programs) {
                        populateDropdown('#dest_program_id', data.programs, 'program_id', 'program_display', 'Select Destination Program');
                        console.log('Program dropdown populated for copy modal');
                    }
                });
            }
        } else {
            $('#programSubstitutionContainer').slideUp();
            $('#dest_program_id').prop('required', false).val('');
        }
        // Reload source schedules if source is already selected (to apply/remove program filter)
        if ($('#source_sy_id').val() && $('#source_term').val()) {
            loadSourceSchedules();
        }
        // Update copy button state
        updateCopyButtonState();
    });
    
    // Load source schedules when source school year or term changes
    $('#source_sy_id, #source_term').on('change', function() {
        loadSourceSchedules();
    });
    
    // Handle instructor change in copy modal
    $('#copy_instructor_id').on('change', function() {
        if ($('#source_sy_id').val() && $('#source_term').val()) {
            loadSourceSchedules();
        }
    });
    
    // Handle destination program change (for program substitution)
    $('#dest_program_id').on('change', function() {
        // Reload source schedules if program substitution is enabled and source is already selected
        if ($('#programSubstitutionCheck').is(':checked') && 
            $('#source_sy_id').val() && $('#source_term').val()) {
            loadSourceSchedules();
        }
        updateCopyButtonState();
    });
    
    // Update copy button state when destination fields change
    $('#dest_sy_id, #dest_term').on('change', function() {
        updateCopyButtonState();
    });
    
    // Handle select all checkbox
    $('#selectAllCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('input[name="selected_schedules[]"]').prop('checked', isChecked);
        updateCopyButtonState();
    });
    
    // Handle individual schedule checkbox changes
    $(document).on('change', 'input[name="selected_schedules[]"]', function() {
        updateSelectAllCheckbox();
        updateCopyButtonState();
    });
    
    // Handle select all button
    $('#selectAllSchedulesBtn').on('click', function() {
        $('input[name="selected_schedules[]"]').prop('checked', true);
        $('#selectAllCheckbox').prop('checked', true);
        updateCopyButtonState();
    });
    
    // Handle deselect all button
    $('#deselectAllSchedulesBtn').on('click', function() {
        $('input[name="selected_schedules[]"]').prop('checked', false);
        $('#selectAllCheckbox').prop('checked', false);
        updateCopyButtonState();
    });
});

/**
 * Reloads the schedules DataTable.
 */
function loadSchedules() {
    // Get modal filter values from sessionStorage (priority) or from filter dropdowns
    const modalProgram = sessionStorage.getItem('scheduleFilterProgram');
    const modalYearLevel = sessionStorage.getItem('scheduleFilterYearLevel');
    
    // If a section is explicitly selected, use its year level from dropdown instead of sessionStorage
    // This prevents conflicts when clicking class buttons (e.g., BSIT 1-A should use year level 1, not 2)
    const sectionFilterValue = $('#sectionFilter').val();
    const yearLevelFromDropdown = $('#yearLevelFilter').val();
    
    // Priority: If section is selected, use dropdown year level (matches the section)
    // Otherwise, use sessionStorage value (for modal filters), then dropdown value
    let yearLevelToUse = modalYearLevel || yearLevelFromDropdown || '';
    if (sectionFilterValue && yearLevelFromDropdown) {
        // Section is selected - prioritize dropdown value to match the section
        yearLevelToUse = yearLevelFromDropdown;
    }
    
    const selectedSy = $('#syFilter').val() || '';
    const activeSy = window.currentActiveSY || null;
    const activeTerm = window.currentActiveTerm || null;
    
    // If the selected SY matches the active SY, also filter by the active term
    // This ensures schedules are filtered by the active School Year and Semester
    let termFilter = '';
    if (selectedSy && activeSy && selectedSy == activeSy && activeTerm) {
        termFilter = activeTerm;
        console.log('Active SY selected, filtering by active term:', activeTerm);
    }
    
    const filters = {
        sy: selectedSy,
        term: termFilter,
        program: modalProgram || $('#programFilter').val() || '',
        year_level: yearLevelToUse,
        section: sectionFilterValue || '',
        instructor: $('#instructorFilter').val() || '',
        room: $('#roomFilter').val() || '',
        day: $('#dayFilter').val() || ''
    };
    
    // If modal filters are set and no section is selected, update the visible filter dropdowns to match
    if (!sectionFilterValue) {
        if (modalProgram && $('#programFilter').length) {
            $('#programFilter').val(modalProgram);
        }
        if (modalYearLevel && $('#yearLevelFilter').length) {
            $('#yearLevelFilter').val(modalYearLevel);
        }
    }

    // Remove empty filter values from params to avoid sending empty strings
    const params = new URLSearchParams();
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            params.append(key, filters[key]);
        }
    });

    $('#scheduleSpinner').show();
    clearCalendar();
    // Don't hide unassigned subjects here - they will be reloaded if section is selected
    // hideUnassignedSubjects();

    // Add cache-busting parameter to ensure fresh data
    params.append('_t', Date.now());
    
    const apiUrl = `${getApiBasePath()}get_schedules.php?${params.toString()}`;
    console.log('Loading schedules from:', apiUrl);
    console.log('Filters:', filters);
    
    fetch(apiUrl, {
        cache: 'no-store', // Prevent browser caching
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        }
    })
        .then(response => {
            console.log('Schedule API response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Schedule API response:', data);
            if (data.success) {
                window.schedulesData = data.data;
                
                // Load ALL schedules (without section and year_level filters) to calculate remaining hours correctly
                // Remaining hours should be calculated across ALL sections and ALL year levels for each instructor
                // The 40/20 hour limit is a weekly total across all classes, not per year level
                const allSchedulesParams = new URLSearchParams();
                Object.keys(filters).forEach(key => {
                    // Exclude section and year_level filters to get all schedules for remaining hours calculation
                    // Only keep filters that don't affect instructor workload calculation (sy, program, instructor, room, day)
                    if (filters[key] && key !== 'section' && key !== 'year_level') {
                        allSchedulesParams.append(key, filters[key]);
                    }
                });
                allSchedulesParams.append('_t', Date.now());
                
                // Fetch all schedules (without section filter) for remaining hours calculation
                fetch(`${getApiBasePath()}get_schedules.php?${allSchedulesParams.toString()}`)
                    .then(response => response.json())
                    .then(allData => {
                        if (allData.success) {
                            window.allSchedulesData = allData.data; // Store all schedules globally
                        } else {
                            window.allSchedulesData = data.data; // Fallback to filtered data
                        }
                        
                        // Render schedules (using filtered data for display, but allSchedulesData for calculation)
                        // Always generate calendar structure first
                        if (!data.data || data.data.length === 0) {
                            // No schedules, show empty calendar
                            generateCalendarTimeSlots(); // Generate empty calendar structure
                            // Clear summary tables
                            renderClassTimeLoadTable([]);
                            renderOvertimeTable([], []);
                        } else {
                            // Has schedules, render calendar
                            renderCalendar(data.data, filters.instructor);
                            // Render summary table - pass filtered schedules for display, but use allSchedulesData for calculation
                            renderClassTimeLoadTable(data.data);
                            // Filter and render overtime schedules
                            // Pass all schedules so we can calculate total assigned hours per instructor
                            const overtimeSchedules = data.data.filter(schedule => schedule.is_overtime === 'Yes');
                            console.log('Overtime schedules found:', overtimeSchedules.length, overtimeSchedules);
                            renderOvertimeTable(overtimeSchedules, window.allSchedulesData || data.data);
                        }
                        
                        // Load unassigned subjects if section is selected (after rendering is complete)
                        const sectionFilterValue = $('#sectionFilter').val();
                        if (sectionFilterValue) {
                            console.log('Section filter has value:', sectionFilterValue, 'Loading unassigned subjects...');
                            // Rebuild filters with current dropdown values to ensure they match
                            // This is important because the dropdowns might have been updated (e.g., by class button click)
                            const currentFilters = {
                                sy: $('#syFilter').val() || '',
                                term: window.currentActiveTerm || '',
                                program: $('#programFilter').val() || '',
                                year_level: $('#yearLevelFilter').val() || '',
                                section: sectionFilterValue
                            };
                            loadUnassignedSubjects(currentFilters);
                        } else {
                            // Only hide if we're not in the middle of a class button click
                            if (!window.isClassButtonClickInProgress) {
                                console.log('No section selected, hiding unassigned subjects');
                                hideUnassignedSubjects();
                            }
                        }
                    })
                    .catch(error => {
                        console.warn('Could not load all schedules for remaining hours calculation:', error);
                        window.allSchedulesData = data.data; // Fallback to filtered data
                        
                        // Render schedules even if all schedules fetch failed
                        // Always generate calendar structure first
                        if (!data.data || data.data.length === 0) {
                            // No schedules, show empty calendar
                            generateCalendarTimeSlots();
                            renderClassTimeLoadTable([]);
                            renderOvertimeTable([], []);
                        } else {
                            // Has schedules, render calendar
                            renderCalendar(data.data, filters.instructor);
                            // Render summary table
                            renderClassTimeLoadTable(data.data);
                            // Filter and render overtime schedules
                            const overtimeSchedules = data.data.filter(schedule => schedule.is_overtime === 'Yes');
                            console.log('Overtime schedules found:', overtimeSchedules.length, overtimeSchedules);
                            renderOvertimeTable(overtimeSchedules, window.allSchedulesData || data.data);
                        }
                        
                        // Load unassigned subjects if section is selected (after rendering is complete)
                        const sectionFilterValue = $('#sectionFilter').val();
                        if (sectionFilterValue) {
                            console.log('Section filter has value:', sectionFilterValue, 'Loading unassigned subjects...');
                            // Rebuild filters with current dropdown values to ensure they match
                            // This is important because the dropdowns might have been updated (e.g., by class button click)
                            const currentFilters = {
                                sy: $('#syFilter').val() || '',
                                term: window.currentActiveTerm || '',
                                program: $('#programFilter').val() || '',
                                year_level: $('#yearLevelFilter').val() || '',
                                section: sectionFilterValue
                            };
                            loadUnassignedSubjects(currentFilters);
                        } else {
                            // Only hide if we're not in the middle of a class button click
                            if (!window.isClassButtonClickInProgress) {
                                console.log('No section selected, hiding unassigned subjects');
                                hideUnassignedSubjects();
                            }
                        }
                    });
            } else {
                showToast('error', data.message || 'Failed to load schedules.');
                generateCalendarTimeSlots();
                // Only hide if we're not in the middle of a class button click
                if (!window.isClassButtonClickInProgress) {
                    hideUnassignedSubjects();
                }
                renderClassTimeLoadTable([]);
                renderOvertimeTable([], []);
            }
        })
        .catch(error => {
            console.error('Error loading schedules:', error);
            showToast('error', 'An error occurred while loading schedules.');
            generateCalendarTimeSlots();
            // Only hide if we're not in the middle of a class button click
            if (!window.isClassButtonClickInProgress) {
                hideUnassignedSubjects();
            }
        })
        .finally(() => {
            $('#scheduleSpinner').hide();
        });
}

/**
 * Generates the time slots for the calendar view.
 */
function generateCalendarTimeSlots() {
    const timeColumn = document.getElementById('timeColumn');
    const daysWrapper = document.getElementById('daysWrapper');
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    // Clear existing content
    timeColumn.innerHTML = '<div class="calendar-header">Time</div>';
    daysWrapper.innerHTML = '';

    // Generate time slots in the time column
    for (let hour = 7; hour < 21; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        
        const hourLabel = document.createElement('span');
        hourLabel.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:00 ${hour < 12 ? 'AM' : 'PM'}`;
        timeSlot.appendChild(hourLabel);

        const halfHourLabel = document.createElement('div');
        halfHourLabel.className = 'time-slot-half';
        halfHourLabel.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:30`;
        timeSlot.appendChild(halfHourLabel);

        timeColumn.appendChild(timeSlot);
    }

    // Generate day columns
    days.forEach(day => {
        const dayColumn = document.createElement('div');
        dayColumn.className = 'day-column';
        dayColumn.id = `day-col-${day}`;
        dayColumn.innerHTML = `<div class="calendar-header">${day}</div><div class="day-column-content" id="day-content-${day}"></div>`;
        daysWrapper.appendChild(dayColumn);
    });
    
    // Add scroll hint on mobile for first-time users
    if (window.innerWidth <= 991.98) {
        const calendarContainer = document.querySelector('.calendar-container');
        if (calendarContainer) {
            // Check if user has seen the hint before
            const hasSeenHint = sessionStorage.getItem('scheduleScrollHintSeen');
            if (!hasSeenHint) {
                calendarContainer.classList.add('scroll-hint');
                // Remove hint after user scrolls
                let scrollTimeout;
                calendarContainer.addEventListener('scroll', function() {
                    calendarContainer.classList.remove('scroll-hint');
                    sessionStorage.setItem('scheduleScrollHintSeen', 'true');
                    clearTimeout(scrollTimeout);
                }, { once: true });
                // Also remove after 4 seconds
                scrollTimeout = setTimeout(() => {
                    calendarContainer.classList.remove('scroll-hint');
                    sessionStorage.setItem('scheduleScrollHintSeen', 'true');
                }, 4000);
            }
        }
    }
}

/**
 * Clears all schedule blocks from the calendar.
 */
function clearCalendar() {
    // Clear all schedule blocks from the calendar, regardless of their container
    document.querySelectorAll('.schedule-block').forEach(block => block.remove());
}

/**
 * Loads and displays unassigned subjects for the selected section
 */
function loadUnassignedSubjects(filters) {
    if (isInstructorScheduleView()) {
        return;
    }
    const container = document.getElementById('unassignedSubjectsContainer');
    const body = document.getElementById('unassignedSubjectsBody');
    
    // Remember the last filters used so other handlers (e.g., button clicks)
    // can still know which section is active even if the dropdown appears empty
    window.lastUnassignedSubjectsFilters = filters || null;

    // Keep the Section filter UI in sync with the filters being used
    if (filters && filters.section) {
        const sectionFilterEl = document.getElementById('sectionFilter');
        if (sectionFilterEl) {
            sectionFilterEl.value = String(filters.section);
        }
    }

    console.log('loadUnassignedSubjects called with filters:', filters);
    console.log('Container found:', !!container, 'Body found:', !!body);
    
    if (!container || !body) {
        console.error('Unassigned subjects container or body not found');
        console.error('Container ID:', container ? 'found' : 'NOT FOUND');
        console.error('Body ID:', body ? 'found' : 'NOT FOUND');
        return;
    }
    
    // Only load if section is selected
    if (!filters.section) {
        console.log('No section selected, hiding unassigned subjects');
        hideUnassignedSubjects();
        return;
    }
    
    console.log('Loading unassigned subjects for section:', filters.section);
    
    // Build query params - API will use section's class data if sy/term not provided
    // When section is provided, don't pass year_level filter - let API use section's own year level
    // This prevents mismatches (e.g., year_level=2 filter with BSIT 1-A section)
    const params = new URLSearchParams();
    params.append('section', filters.section);
    if (filters.sy) params.append('sy', filters.sy);
    if (filters.term) params.append('term', filters.term);
    if (filters.program) params.append('program', filters.program);
    // Only pass year_level if section is NOT provided (for general filtering)
    // When section is provided, API will get year level from section itself
    if (filters.year_level && !filters.section) {
        params.append('year_level', filters.year_level);
    }
    
    // Show loading state
    body.innerHTML = '<div class="text-primary"><i class="bi bi-hourglass-split me-1"></i>Loading unassigned subjects...</div>';
    // Force container to be visible - override any inline styles
    showUnassignedSubjectsContainer();
    
    const apiUrl = `${getApiBasePath()}get_unassigned_subjects.php?${params.toString()}`;
    console.log('Fetching unassigned subjects from:', apiUrl);
    
    fetch(apiUrl)
        .then(response => {
            console.log('Unassigned subjects API response status:', response.status);
            console.log('Response headers:', response.headers);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Non-OK response body:', text);
                    throw new Error(`HTTP error! status: ${response.status}, body: ${text.substring(0, 200)}`);
                });
            }
            return response.text().then(text => {
                console.log('Raw API response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            console.log('Unassigned subjects API response:', data);
            console.log('Response structure check:', {
                hasSuccess: 'success' in data,
                successValue: data.success,
                hasData: 'data' in data,
                dataType: Array.isArray(data.data) ? 'array' : typeof data.data,
                dataLength: Array.isArray(data.data) ? data.data.length : 'N/A',
                dataContent: data.data
            });
            
            if (data.success) {
                // Log detailed diagnostics if available (when count is 0)
                if (data.debug && data.debug.detailed_diagnostics) {
                    console.log('=== DETAILED DIAGNOSTICS FOR UNASSIGNED SUBJECTS ===');
                    console.log('Diagnostics:', data.debug.detailed_diagnostics);
                    if (data.debug.detailed_diagnostics.summary) {
                        console.log('Summary:', data.debug.detailed_diagnostics.summary);
                        console.log('Expected unassigned count:', data.debug.detailed_diagnostics.summary.expected_unassigned_count);
                        if (data.debug.detailed_diagnostics.summary.expected_unassigned_count === 0) {
                            console.log('✅ All subjects are fully scheduled - this is expected behavior');
                        } else if (data.debug.detailed_diagnostics.summary.expected_unassigned_count > 0) {
                            console.warn('⚠️ ' + data.debug.detailed_diagnostics.summary.expected_unassigned_count + ' subjects should appear but don\'t - investigate!');
                            if (data.debug.detailed_diagnostics.subject_analysis) {
                                console.log('Subject Analysis:', data.debug.detailed_diagnostics.subject_analysis);
                            }
                        }
                    }
                    if (data.debug.detailed_diagnostics.term_breakdown) {
                        console.log('Term breakdown:', data.debug.detailed_diagnostics.term_breakdown);
                    }
                    console.log('=== END DIAGNOSTICS ===');
                }
                
                // Check if data exists and is an array
                if (data.data && Array.isArray(data.data) && data.data.length > 0) {
                    console.log('Found', data.data.length, 'unassigned subjects');
                    console.log('First subject sample:', data.data[0]);
                    displayUnassignedSubjects(data.data);
                    // Force container to be visible
                    showUnassignedSubjectsContainer();
                    console.log('Unassigned subjects container displayed with', data.data.length, 'subjects');
                } else {
                    console.log('No unassigned subjects found. Data:', data.data);
                    console.log('Data type:', typeof data.data, 'Is array:', Array.isArray(data.data));
                    // Show a message instead of hiding completely - this helps debug
                    let debugMessage = 'Section ID: ' + filters.section + ', SY: ' + (filters.sy || 'Not set') + ', Term: ' + (filters.term || 'Not set');
                    if (data.debug && data.debug.detailed_diagnostics && data.debug.detailed_diagnostics.summary) {
                        debugMessage += '<br><small>Expected unassigned: ' + data.debug.detailed_diagnostics.summary.expected_unassigned_count + 
                                       ' | Fully scheduled: ' + data.debug.detailed_diagnostics.summary.fully_scheduled + '</small>';
                    }
                    body.innerHTML = '<div class="text-info p-2 border rounded"><i class="bi bi-info-circle me-1"></i>No unassigned subjects found for this section. <small class="d-block mt-1 text-muted">' + debugMessage + '</small></div>';
                    showUnassignedSubjectsContainer();
                }
            } else {
                console.warn('API returned success=false:', data.message);
                body.innerHTML = `<div class="text-warning p-2 border rounded"><i class="bi bi-exclamation-triangle me-1"></i>${data.message || 'Failed to load unassigned subjects'}</div>`;
                showUnassignedSubjectsContainer();
            }
        })
        .catch(error => {
            console.error('Error loading unassigned subjects:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            body.innerHTML = '<div class="text-danger p-2 border rounded"><i class="bi bi-exclamation-triangle me-1"></i>Error loading unassigned subjects: ' + error.message + '</div>';
            showUnassignedSubjectsContainer();
        });
}

/**
 * Loads and displays available classes
 */
function loadAvailableClasses(filters) {
    const container = document.getElementById('unassignedSubjectsContainer');
    const body = document.getElementById('unassignedSubjectsBody');
    
    if (!container || !body) return;
    
    // Build query params for classes
    const params = new URLSearchParams();
    if (filters.sy) params.append('sy', filters.sy);
    
    fetch(`${getApiBasePath()}get_class_sections.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                // Flatten the grouped data into a single array
                const allClasses = [];
                Object.keys(data.data).forEach(yearLevel => {
                    allClasses.push(...data.data[yearLevel]);
                });
                
                if (allClasses.length > 0) {
                    displayAvailableClasses(allClasses);
                    container.style.display = 'block';
                } else {
                    body.innerHTML = '<div class="no-unassigned-subjects">No classes available.</div>';
                    container.style.display = 'block';
                }
            } else {
                body.innerHTML = '<div class="no-unassigned-subjects">No classes available.</div>';
                container.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading classes:', error);
            body.innerHTML = '<div class="no-unassigned-subjects">Error loading classes.</div>';
            container.style.display = 'block';
        });
}

/**
 * Displays unassigned subjects as buttons
 */
function displayUnassignedSubjects(subjects) {
    const body = document.getElementById('unassignedSubjectsBody');
    const container = document.getElementById('unassignedSubjectsContainer');
    
    console.log('displayUnassignedSubjects called with', subjects.length, 'subjects');
    console.log('Body element:', body);
    console.log('Container element:', container);
    
    if (!body) {
        console.error('unassignedSubjectsBody not found!');
        return;
    }
    
    if (!container) {
        console.error('unassignedSubjectsContainer not found!');
        return;
    }
    
    // Clear body but keep container visible
    body.innerHTML = '';
    body.style.display = 'flex';
    body.style.visibility = 'visible';
    body.style.opacity = '1';
    
    // Sort subjects by code
    subjects.sort((a, b) => {
        const codeA = (a.subj_code || '').toUpperCase();
        const codeB = (b.subj_code || '').toUpperCase();
        return codeA.localeCompare(codeB);
    });
    
    console.log('Creating buttons for', subjects.length, 'subjects');
    
    subjects.forEach((subject, index) => {
        console.log(`Creating button ${index + 1} for subject:`, subject);
        
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-primary unassigned-subject-item me-2 mb-2';
        // Display only subject code
        button.textContent = subject.subj_code || 'Unknown';
        button.setAttribute('data-subj-id', subject.subj_id);
        button.setAttribute('data-subj-code', subject.subj_code || '');
        button.setAttribute('data-subj-desc', subject.subj_desc || '');
        button.setAttribute('data-subj-unit', subject.subj_units || subject.subj_unit || '0');
        button.setAttribute('data-subj-lec', subject.subj_lec || '0');
        button.setAttribute('data-subj-lab', subject.subj_lab || '0');
        button.setAttribute('data-subj-category', subject.subj_category || '');
        // Full description in tooltip
        const tooltip = `${subject.subj_code || 'Unknown'}: ${subject.subj_desc || 'No description'}\n${subject.subj_units || subject.subj_unit || '0'} units`;
        button.setAttribute('title', tooltip);
        
        // Add click handler to open schedule modal with this subject pre-selected
        button.addEventListener('click', function() {
            // Get current section filter; if empty, fall back to the last filters
            let sectionId = $('#sectionFilter').val();
            if (!sectionId && window.lastUnassignedSubjectsFilters) {
                sectionId = window.lastUnassignedSubjectsFilters.section || '';
            }
            
            if (!sectionId) {
                showToast('warning', 'Please select a section first');
                return;
            }
            
            // Reset form and set to create mode
            $('#scheduleForm')[0].reset();
            $('#schd_id').val('');
            $('#scheduleModalLabel').text('Create Schedule');
            $('#schd_status').closest('.col-md-6').hide();
            $('#removeScheduleBtn').hide();
            $('#saveScheduleBtn').text('Save Schedule');
            
            // Clear schedule-specific fields (instructor, room, type, day, times)
            $('#inst_id').val('');
            $('#rm_id').val('');
            $('#schd_type').val('Lec'); // Reset to default
            // Uncheck all day checkboxes
            $('input[name="schd_day[]"]').prop('checked', false);
            $('#schd_start').val('');
            $('#schd_end').val('');
            
            // Fetch section details to get all necessary information
            fetch(`${getApiBasePath()}get_section_details.php?section_id=${sectionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const section = data.data;
                        
                        // Open the schedule modal
                        const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                        modal.show();
                        
                        // Store subject ID for later use
                        const selectedSubjectId = subject.subj_id;
                        
                        // Wait for modal to be shown, then populate fields
                        $('#scheduleModal').one('shown.bs.modal', function() {
                            // Set up cascading dropdowns
                            setupCascadingDropdowns();
                            
                            // Set school year first, then trigger cascade
                            if (section.sy_id) {
                                $('#sy_id').val(section.sy_id);
                                $('#sy_id').trigger('change.cascade');
                            }
                            
                            // Wait for terms to load, then set term
                            setTimeout(() => {
                                if (section.class_term) {
                                    $('#schd_term').val(section.class_term);
                                    $('#schd_term').trigger('change.cascade');
                                }
                                
                                // Wait for term to process, then set program
                                setTimeout(() => {
                                    if (section.program_id) {
                                        $('#program_id').val(section.program_id);
                                        $('#program_id').trigger('change.cascade');
                                        
                                        // Wait for program to process, then set year level
                                        setTimeout(() => {
                                            if (section.year_level) {
                                                $('#year_level').val(section.year_level);
                                                $('#year_level').trigger('change.cascade');
                                                
                                                // Wait for year level to process, then set section and subject
                                                setTimeout(() => {
                                                    if (section.sec_id) {
                                                        $('#sec_id').val(section.sec_id);
                                                    }
                                                    if (selectedSubjectId) {
                                                        $('#subj_id').val(selectedSubjectId);
                                                    }
                                                }, 400);
                                            }
                                        }, 400);
                                    }
                                }, 400);
                            }, 400);
                        });
                    } else {
                        showToast('error', data.message || 'Failed to load section details');
                    }
                })
                .catch(error => {
                    console.error('Error loading section details:', error);
                    showToast('error', 'An error occurred while loading section details');
                });
            
            // Highlight the clicked button
            document.querySelectorAll('.unassigned-subject-item').forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');
        });
        
        body.appendChild(button);
        console.log('Button appended:', button.textContent);
    });
    
    // Ensure container and body are visible with explicit styles
    if (container) {
        container.style.display = 'block';
        container.style.visibility = 'visible';
        container.style.opacity = '1';
        // Force a reflow to ensure visibility
        container.offsetHeight;
    }
    
    if (body) {
        body.style.display = 'flex';
        body.style.visibility = 'visible';
        body.style.opacity = '1';
        // Force a reflow to ensure visibility
        body.offsetHeight;
    }
    
    console.log('Container display set to block, total buttons:', body.children.length);
    console.log('Body innerHTML length:', body.innerHTML.length);
    console.log('Body children details:', Array.from(body.children).map(c => ({
        text: c.textContent,
        visible: c.offsetParent !== null,
        classes: c.className,
        display: window.getComputedStyle(c).display
    })));
    
    // Force a reflow to ensure visibility
    if (body && body.children.length > 0) {
        const firstButton = body.firstElementChild;
        if (firstButton) {
            firstButton.offsetHeight; // Force reflow
            console.log('Forced reflow, first button details:', {
                text: firstButton.textContent,
                visible: firstButton.offsetParent !== null,
                computedDisplay: window.getComputedStyle(firstButton).display,
                computedVisibility: window.getComputedStyle(firstButton).visibility
            });
        }
    }
    
    // If no buttons were created, show a message
    if (body.children.length === 0) {
        console.warn('No buttons were created! Subjects array:', subjects);
        body.innerHTML = '<div class="text-warning p-2"><i class="bi bi-exclamation-triangle me-1"></i>No unassigned subjects to display</div>';
    }
    
    // Force a reflow to ensure visibility
    if (body && body.children.length > 0) {
        body.offsetHeight; // Force reflow
        console.log('Forced reflow, buttons should be visible now');
    }
}

/**
 * Displays available classes as buttons
 */
function displayAvailableClasses(classes) {
    const body = document.getElementById('unassignedSubjectsBody');
    if (!body) return;
    
    body.innerHTML = '';
    
    // Sort classes by year level and section number
    classes.sort((a, b) => {
        if (a.year_level !== b.year_level) {
            return a.year_level - b.year_level;
        }
        return a.sec_num - b.sec_num;
    });
    
    classes.forEach(classItem => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'unassigned-subject-item';
        button.textContent = classItem.label; // e.g., "Class 1 - A"
        button.setAttribute('data-sec-id', classItem.sec_id);
        button.setAttribute('data-year-level', classItem.year_level);
        button.setAttribute('data-sec-name', classItem.display_name);
        button.setAttribute('title', `Section: ${classItem.display_name} (${classItem.label})`);
        
        // Add click handler to filter schedules by this class section
        button.addEventListener('click', function() {
            // Set the filters
            $('#yearLevelFilter').val(classItem.year_level);
            $('#sectionFilter').val(classItem.sec_id);
            
            // Reload schedules with the selected class filter
            loadSchedules();
            
            // Highlight the clicked button
            document.querySelectorAll('.unassigned-subject-item').forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');
        });
        
        body.appendChild(button);
    });
}

/**
 * Hides the unassigned subjects container
 */
function hideUnassignedSubjects() {
    const container = document.getElementById('unassignedSubjectsContainer');
    if (container) {
        // Add Bootstrap's d-none class
        container.classList.add('d-none');
        container.style.display = 'none';
        container.style.visibility = 'hidden';
    }
}

/**
 * Shows the unassigned subjects container
 */
function showUnassignedSubjectsContainer() {
    const container = document.getElementById('unassignedSubjectsContainer');
    if (container) {
        // Remove Bootstrap's d-none class
        container.classList.remove('d-none');
        container.style.display = 'block';
        container.style.visibility = 'visible';
        container.style.opacity = '1';
        container.style.height = 'auto';
    }
}

/**
 * Renders the fetched schedules onto the calendar grid.
 * @param {Array} schedules - The array of schedule objects.
 */
function renderCalendar(schedules, instructorFilterValue) {
    // Clear all existing schedule blocks before rendering new ones
    document.querySelectorAll('.schedule-block').forEach(block => block.remove());
    
    // Expanded color palette for unique instructor colors
    const baseColors = [
        '#28a745',  // Green
        '#2E5C8A',  // Blue
        '#800000',  // Maroon
        '#4B0082',  // Indigo
        '#FF6B35',  // Orange
        '#006400',  // Dark Green
        '#8B4513',  // Brown
        '#2F4F4F',  // Dark Slate Gray
        '#A0522D',  // Sienna
        '#5F9EA0',  // Cadet Blue
        '#C71585',  // Medium Violet Red
        '#556B2F',  // Dark Olive Green
        '#DC143C',  // Crimson
        '#20B2AA',  // Light Sea Green
        '#9370DB',  // Medium Purple
        '#FF6347',  // Tomato
        '#32CD32',  // Lime Green
        '#1E90FF',  // Dodger Blue
        '#FF1493',  // Deep Pink
        '#00CED1',  // Dark Turquoise
        '#FF8C00',  // Dark Orange
        '#8A2BE2',  // Blue Violet
        '#228B22',  // Forest Green
        '#CD5C5C',  // Indian Red
        '#4682B4'   // Steel Blue
    ];
    const instructorColors = {};
    let colorIndex = 0;

    // Helper function to get a unique color for an instructor based on their ID
    function getColorForInstructor(instId) {
        if (!instructorColors[instId]) {
            // Use instructor ID to determine color index for consistency
            // This ensures the same instructor always gets the same color
            const colorIdx = instId % baseColors.length;
            instructorColors[instId] = baseColors[colorIdx];
        }
        return instructorColors[instId];
    }
    
    // Helper function to get a darker shade for the border
    function darkenColor(color) {
        // Convert hex to RGB
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        
        // Darken by 20%
        const newR = Math.max(0, Math.floor(r * 0.8));
        const newG = Math.max(0, Math.floor(g * 0.8));
        const newB = Math.max(0, Math.floor(b * 0.8));
        
        // Convert back to hex
        return '#' + 
            newR.toString(16).padStart(2, '0') + 
            newG.toString(16).padStart(2, '0') + 
            newB.toString(16).padStart(2, '0');
    }

    // Calculate total assigned hours per instructor from ALL schedules to determine overtime dynamically
    // Use window.allSchedulesData if available for accurate calculation across all sections
    const allSchedulesForCalc = window.allSchedulesData && window.allSchedulesData.length > 0 
        ? window.allSchedulesData 
        : schedules; // Fallback to filtered schedules
    
    const instructorAssignedHours = {};
    allSchedulesForCalc.forEach(schedule => {
        const instId = schedule.inst_id;
        if (!instructorAssignedHours[instId]) {
            // Use total_hours from schedule (which now correctly uses instruction_hours for Part-Time/Contractual)
            // Fallback to instruction_hours if available, then policy_total_hours, then default 40
            const totalHours = schedule.total_hours || 
                              (schedule.instruction_hours && (schedule.inst_status === 'Part-Time' || schedule.inst_status === 'Contractual') ? schedule.instruction_hours : null) ||
                              schedule.policy_total_hours || 
                              40;
            instructorAssignedHours[instId] = {
                totalMinutes: 0,
                totalHours: totalHours
            };
        }
        // Add schedule minutes (schd_min is in minutes)
        instructorAssignedHours[instId].totalMinutes += parseFloat(schedule.schd_min || 0);
    });
    
    // Calculate overtime hours for each instructor
    const instructorOvertime = {};
    Object.keys(instructorAssignedHours).forEach(instId => {
        const inst = instructorAssignedHours[instId];
        const assignedHours = inst.totalMinutes / 60; // Convert minutes to hours
        const overtimeHours = Math.max(0, assignedHours - inst.totalHours); // Excess hours only
        instructorOvertime[instId] = {
            assigned: assignedHours,
            total: inst.totalHours,
            overtime: overtimeHours
        };
    });

    schedules.forEach(schedule => {
        const start = schedule.schd_start.split(':');
        const end = schedule.schd_end.split(':');
        const startHour = parseInt(start[0]);
        const startMinute = parseInt(start[1]);
        const endHour = parseInt(end[0]);
        const endMinute = parseInt(end[1]);

        // Each hour slot is 60px high, so 1px per minute.
        const PIXELS_PER_MINUTE = 1;

        // Calculate total minutes from 7:00 AM
        const totalStartMinutes = (startHour - 7) * 60 + startMinute;
        const durationMinutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute);

        const topPosition = totalStartMinutes * PIXELS_PER_MINUTE;
        const blockHeight = durationMinutes * PIXELS_PER_MINUTE;

        const dayContent = document.getElementById(`day-content-${schedule.schd_day}`);
        if (dayContent) {
            const block = document.createElement('div');
            block.className = 'schedule-block';
            block.style.top = `${topPosition}px`;
            block.style.height = `${blockHeight}px`;

            // Check if THIS SPECIFIC schedule is marked as overtime (from database)
            // Only the schedule that causes overtime should be marked, not all schedules
            const instId = schedule.inst_id;
            const isOvertime = schedule.is_overtime === 'Yes' || schedule.is_overtime === true;
            
            // Apply overtime styling ONLY if this specific schedule is overtime
            let overtimeBadge = '';
            if (isOvertime) {
                block.classList.add('overtime-block');
                overtimeBadge = '<span class="badge bg-danger mb-1">Overtime</span>';
                // Override color scheme for overtime blocks (red background)
                block.style.background = '#dc3545';
                block.style.borderLeftColor = '#bd2130';
            } else {
                // Use instructor-based unique color scheme
                const instructorColor = getColorForInstructor(instId);
                const borderColor = darkenColor(instructorColor);
                block.style.background = instructorColor;
                block.style.borderLeftColor = borderColor;
            }
            // Format instructor name (abbreviated: First Initial. Last Name)
            let instructorDisplay = '';
            if (schedule.instructor_name) {
                const nameParts = schedule.instructor_name.trim().split(' ');
                if (nameParts.length >= 2) {
                    const firstName = nameParts[0];
                    const lastName = nameParts[nameParts.length - 1];
                    instructorDisplay = `${firstName.charAt(0)}. ${lastName}`;
                } else {
                    instructorDisplay = schedule.instructor_name;
                }
            }
            
            // Format room display (just room name, no building prefix)
            let roomDisplay = schedule.rm_name || '';
            
            // Check if this is a virtual/online class
            const isVirtual = schedule.bd_desc && schedule.bd_desc.trim().toUpperCase() === 'VIRTUAL';
            const virtualBadge = isVirtual ? '<span class="badge bg-info mb-1" style="font-size: 0.65rem;"><i class="bi bi-laptop me-1"></i>Online</span>' : '';
            
            // Format time display
            const timeDisplay = `${schedule.start_time} - ${schedule.end_time}`;
            
            // Simplified block content matching the image style
            block.innerHTML = `
                <div class="schedule-block-content">
                    ${overtimeBadge}
                    ${virtualBadge}
                    <div class="subj-code-simple">${escapeHtml(schedule.subj_code)}</div>
                    <div class="instructor-simple">${escapeHtml(instructorDisplay)}</div>
                    <div class="room-simple">${escapeHtml(roomDisplay)}</div>
                    <div class="time-simple">${escapeHtml(timeDisplay)}</div>
                    <div class="type-simple">${schedule.schd_type}</div>
                </div>
            `;
            block.title = `${schedule.subj_desc}\n${schedule.instructor_name}\n${schedule.start_time} - ${schedule.end_time}`;
            block.setAttribute('data-schd-id', schedule.schd_id);
            block.setAttribute('data-type', schedule.schd_type);
            block.onclick = () => editSchedule(schedule.schd_id);
            
            dayContent.appendChild(block);
        }
    });
}

/**
 * Loads necessary data for form dropdowns (school years, instructors, rooms, etc.).
 * Returns a Promise that resolves when data is loaded.
 */
function loadScheduleFormData() {
    // Check if we're on instructor dashboard
    const isInstructorDashboard = window.location.pathname.includes('/views/instructor/');

    // Add cache-busting parameter to prevent CDN caching
    const cacheBuster = `_t=${Date.now()}`;
    const separator = getApiBasePath().includes('?') ? '&' : '?';
    const apiUrl = `${getApiBasePath()}get_schedule_form_data.php${separator}${cacheBuster}`;
    
    console.log('loadScheduleFormData called, fetching from:', apiUrl);
    
    return fetch(apiUrl, {
        cache: 'no-store', // Prevent browser caching
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        }
    })
        .then(response => {
            // If unauthorized (403), return empty data structure for instructors
            if (response.status === 403) {
                console.warn('No permission to load schedule form data (instructors may only view schedules)');
                return { success: true, school_years: [], programs: [], sections: [], instructors: [], rooms: [], subjects: [] };
            }
            if (!response.ok) {
                // For instructors, suppress errors - they may not need form data
                if (isInstructorDashboard) {
                    console.warn('Could not load schedule form data (instructors may only view schedules)');
                    return { success: true, school_years: [], programs: [], sections: [], instructors: [], rooms: [], subjects: [] };
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('loadScheduleFormData response received, current_sy_id:', data.current_sy_id);
            if (data.success) {
                // Instructors don't have filter dropdowns (syFilter, programFilter, etc.) – skip to avoid errors
                if (isInstructorDashboard) {
                    console.log('Instructor dashboard: skipping filter/modal dropdown population (view-only)');
                    return data;
                }
                // Create a new display property for school years
                data.school_years.forEach(sy => {
                    sy.sy_display = `${sy.sy_name} (${sy.sy_year})`;
                });

                // Populate filter dropdowns
                populateDropdown('#syFilter', data.school_years, 'sy_id', 'sy_display', 'All School Years');
                
                // Set current school year as default if available
                // Use multiple attempts with increasing delays to handle CDN/network latency
                if (data.current_sy_id) {
                    console.log('Attempting to set current school year as default:', data.current_sy_id);
                    console.log('Available school years:', data.school_years.map(sy => ({ id: sy.sy_id, name: sy.sy_display })));
                    
                    // Function to attempt setting the value with retries
                    const attemptSetSchoolYear = (attempt = 1, maxAttempts = 5) => {
                        const syFilter = $('#syFilter');
                        const optionExists = syFilter.find(`option[value="${data.current_sy_id}"]`).length > 0;
                        
                        if (!optionExists && attempt < maxAttempts) {
                            // Option not ready yet, retry after delay
                            console.log(`School year option not ready, retry ${attempt}/${maxAttempts}...`);
                            setTimeout(() => attemptSetSchoolYear(attempt + 1, maxAttempts), 150 * attempt);
                            return;
                        }
                        
                        if (!optionExists) {
                            console.error('❌ School year ID not found in dropdown options after', maxAttempts, 'attempts');
                            return;
                        }
                        
                        // Set the value
                        const currentValue = syFilter.val();
                        syFilter.val(data.current_sy_id);
                        
                        // Verify it was set
                        const newValue = syFilter.val();
                        console.log('Filter value after setting:', newValue, '(was:', currentValue, ')');
                        
                        if (newValue == data.current_sy_id) {
                            console.log('✅ Successfully set current school year as default');
                            // Trigger change event to load schedules with the default school year
                            // Use a small delay to ensure all dependent filters are ready
                            setTimeout(() => {
                                syFilter.trigger('change');
                            }, 50);
                        } else {
                            console.warn('⚠️ Failed to set school year. Expected:', data.current_sy_id, 'Got:', newValue);
                            if (attempt < maxAttempts) {
                                // Retry setting the value
                                setTimeout(() => attemptSetSchoolYear(attempt + 1, maxAttempts), 100);
                            }
                        }
                    };
                    
                    // Start with initial delay (increased for CDN/network latency)
                    setTimeout(() => {
                        attemptSetSchoolYear();
                    }, 200);
                } else {
                    console.log('No current_sy_id found in response data');
                }
                
                // Format: "Program Code - Program Name" (same as modal dropdown)
                populateDropdown('#programFilter', data.programs, 'program_id', 'program_display', 'All Programs');
                populateDropdown('#sectionFilter', data.sections, 'sec_id', 'sec_name', 'All Sections');
                populateDropdown('#instructorFilter', data.instructors, 'inst_id', 'name', 'All Instructors');
                populateDropdown('#roomFilter', data.rooms, 'rm_id', 'rm_name_with_building', 'All Rooms');

                // Populate modal dropdowns
                populateDropdown('#sy_id', data.school_years, 'sy_id', 'sy_display', 'Select School Year');
                // Populate Course/Program dropdown - filtered by department
                populateDropdown('#program_id', data.programs, 'program_id', 'program_display', 'Select Program');
                populateDropdown('#subj_id', data.subjects, 'subj_id', 'subj_desc', 'Select Subject');
                
                // Store current SY and term for auto-fill in create modal
                window.currentActiveSY = data.current_sy_id;
                window.currentActiveTerm = data.current_term;
                
                // Populate Schedule Filter Modal dropdowns if they exist
                if (document.getElementById('modalProgramFilter')) {
                    populateDropdown('#modalProgramFilter', data.programs, 'program_id', 'program_display', 'Select Program');
                    console.log(`Populated modal filter with ${data.programs.length} program(s) from department: ${data.user_dept_name || 'Unknown'}`);
                }

                // Populate copy modal dropdowns
                populateDropdown('#source_sy_id', data.school_years, 'sy_id', 'sy_display', 'Select Source SY');
                populateDropdown('#dest_sy_id', data.school_years, 'sy_id', 'sy_display', 'Select Destination SY');
                populateDropdown('#copy_instructor_id', data.instructors, 'inst_id', 'name', 'Select Instructor');
                // Populate destination program dropdown for program substitution
                populateDropdown('#dest_program_id', data.programs, 'program_id', 'program_display', 'Select Destination Program');
                
                // Add change handlers for destination to enable/disable copy button
                $('#dest_sy_id, #dest_term').off('change.copy').on('change.copy', function() {
                    updateCopyButtonState();
                });

                populateDropdown('#sec_id', data.sections, 'sec_id', 'sec_name', 'Select Section');
                populateDropdown('#inst_id', data.instructors, 'inst_id', 'name', 'Select Instructor');
                populateDropdown('#rm_id', data.rooms, 'rm_id', 'rm_name_with_building', 'Select Room');
                
                // Populate modal filter dropdowns if they exist
                if (document.getElementById('modalProgramFilter')) {
                    populateDropdown('#modalProgramFilter', data.programs, 'program_id', 'program_display', 'Select Program');
                }
                
                // Restore modal filter values if they exist in sessionStorage
                const storedProgram = sessionStorage.getItem('scheduleFilterProgram');
                const storedYearLevel = sessionStorage.getItem('scheduleFilterYearLevel');
                if (storedProgram && $('#programFilter').length) {
                    $('#programFilter').val(storedProgram);
                }
                if (storedYearLevel && $('#yearLevelFilter').length) {
                    $('#yearLevelFilter').val(storedYearLevel);
                }
                
                return data;
            } else {
                // For instructors, suppress errors - they may not need form data
                if (isInstructorDashboard) {
                    console.warn('Schedule form data indicates failure (suppressed for instructors):', data.message);
                    return { success: true, school_years: [], programs: [], sections: [], instructors: [], rooms: [], subjects: [] };
                }
                showToast('error', data.message || 'Failed to load form data.');
                throw new Error(data.message || 'Failed to load form data.');
            }
        })
        .catch(error => {
            console.error('Error loading schedule form data:', error);
            // For instructors, suppress error toasts - they may not need form data
            if (isInstructorDashboard) {
                console.warn('Error loading schedule form data (suppressed for instructors):', error);
                // Return empty data structure so the promise resolves
                return { success: true, school_years: [], programs: [], sections: [], instructors: [], rooms: [], subjects: [] };
            }
            showToast('error', 'An error occurred while loading form data.');
            throw error;
        });
}

/**
 * Updates the schedule term filter dropdown based on the active school year and semester
 */
function updateScheduleTermFilter() {
    // Term filter has been removed - this function is kept for compatibility but does nothing
    // The API automatically uses the active semester when term filter is not provided
    console.log('⚠️ updateScheduleTermFilter called but term filter has been removed');
}

/**
 * Helper function to populate a select dropdown.
 * For subjects, also stores subj_lec and subj_lab as data attributes.
 */
function populateDropdown(selector, items, valueField, textField, defaultOptionText) {
    const dropdown = $(selector);
    dropdown.empty();
    dropdown.append(`<option value="">${defaultOptionText}</option>`);
    items.forEach(item => {
        const option = $(`<option value="${item[valueField]}">${item[textField]}</option>`);
        
        // For subjects dropdown, store lec and lab hours as data attributes
        if (selector === '#subj_id' && (item.subj_lec !== undefined || item.subj_lab !== undefined)) {
            option.attr('data-subj-lec', item.subj_lec || 0);
            option.attr('data-subj-lab', item.subj_lab || 0);
        }
        
        dropdown.append(option);
    });
}

/**
 * Sets up cascading dropdown filters for the schedule modal
 */
function setupCascadingDropdowns() {
    // Remove existing handlers to avoid duplicates
    $('#sy_id').off('change.cascade');
    $('#program_id').off('change.cascade');
    $('#year_level').off('change.cascade');
    $('#schd_term').off('change.cascade');
    
    // School Year -> Term
    $('#sy_id').on('change.cascade', function() {
        const syId = $(this).val();
        if (syId) {
            loadFilteredData('terms', {sy_id: syId}, '#schd_term', 'term_value', 'term_name', 'Select Term');
        } else {
            $('#schd_term').empty().append('<option value="">Select Term</option>');
        }
        // Clear dependent dropdowns
        $('#program_id').val('').trigger('change.cascade');
    });
    
    // Program -> Year Level, Subject, Section, Instructor
    $('#program_id').on('change.cascade', function() {
        const programId = $(this).val();
        const currentInstId = $('#inst_id').val(); // Preserve current selection if from search
        if (programId) {
            // Load year levels
            loadFilteredData('year_levels', {program_id: programId}, '#year_level', 'year_level', 'year_level_name', 'Select Year Level');
            // Load subjects (without year level filter yet)
            loadFilteredData('subjects', {program_id: programId}, '#subj_id', 'subj_id', 'subj_display', 'Select Subject');
            // Load instructors filtered by program's department
            loadFilteredData('instructors', {program_id: programId}, '#inst_id', 'inst_id', 'name', 'Select Instructor').then(() => {
                // Restore selection if it was from search (exists in dropdown)
                if (currentInstId && $('#inst_id').find(`option[value="${currentInstId}"]`).length > 0) {
                    $('#inst_id').val(currentInstId);
                }
            });
        } else {
            $('#year_level').empty().append('<option value="">Select Year Level</option>');
            $('#subj_id').empty().append('<option value="">Select Subject</option>');
            $('#sec_id').empty().append('<option value="">Select Section</option>');
            // Only clear instructor dropdown if it wasn't selected via search
            if (!$('#instructorSearch').val()) {
                $('#inst_id').empty().append('<option value="">Select Instructor</option>');
            }
        }
        // Clear dependent dropdowns
        $('#year_level').val('').trigger('change.cascade');
        // Don't clear instructor if it was selected via search
        if (!$('#instructorSearch').val() && !currentInstId) {
            $('#inst_id').val('');
        }
    });
    
    // Year Level -> Subject, Section
    $('#year_level').on('change.cascade', function() {
        const programId = $('#program_id').val();
        const yearLevel = $(this).val();
        
        if (programId && yearLevel) {
            // Load subjects filtered by program and year level
            loadFilteredData('subjects', {
                program_id: programId,
                year_level: yearLevel
            }, '#subj_id', 'subj_id', 'subj_display', 'Select Subject');
            
            // Load sections if school year and term are also selected
            const syId = $('#sy_id').val();
            const term = $('#schd_term').val();
            if (syId && term) {
                loadFilteredData('sections', {
                    program_id: programId,
                    year_level: yearLevel,
                    sy_id: syId,
                    term: term
                }, '#sec_id', 'sec_id', 'sec_name', 'Select Section');
            }
        } else if (programId) {
            // If only program is selected, load subjects without year level filter
            loadFilteredData('subjects', {program_id: programId}, '#subj_id', 'subj_id', 'subj_display', 'Select Subject');
        } else {
            $('#subj_id').empty().append('<option value="">Select Subject</option>');
            $('#sec_id').empty().append('<option value="">Select Section</option>');
        }
    });
    
    // Term + School Year + Program + Year Level -> Section
    $('#schd_term').on('change.cascade', function() {
        const syId = $('#sy_id').val();
        const programId = $('#program_id').val();
        const yearLevel = $('#year_level').val();
        const term = $(this).val();
        
        if (syId && term && programId && yearLevel) {
            loadFilteredData('sections', {
                program_id: programId,
                year_level: yearLevel,
                sy_id: syId,
                term: term
            }, '#sec_id', 'sec_id', 'sec_name', 'Select Section');
        } else {
            $('#sec_id').empty().append('<option value="">Select Section</option>');
        }
    });
    
    // Validate schedule type based on subject hours
    // If subj_lab = 0, disable Laboratory option
    // Lecture always has hours, so it's always enabled
    // Also auto-fill year_level and instructor if empty during edit
    $('#subj_id').on('change', function() {
        const subjId = $(this).val();
        const scheduleTypeSelect = $('#schd_type');
        const schdId = $('#schd_id').val(); // Check if we're editing
        
        if (!subjId) {
            // No subject selected - enable all options
            scheduleTypeSelect.find('option').prop('disabled', false);
            return;
        }
        
        // Get subject data to check lab hours from dropdown option's data attribute
        const selectedOption = $(this).find('option:selected');
        let subjLab = parseInt(selectedOption.attr('data-subj-lab')) || 0;
        let subjLec = parseInt(selectedOption.attr('data-subj-lec')) || 0;
        const subjYearLevel = selectedOption.attr('data-year-level');
        
        // If we're editing and year_level or instructor is empty, try to auto-fill
        if (schdId && window.editScheduleData) {
            const currentYearLevel = $('#year_level').val();
            const currentInstId = $('#inst_id').val();
            const programId = $('#program_id').val();
            
            // Auto-fill year_level from original schedule data if empty
            if (!currentYearLevel && window.editScheduleData.year_level) {
                $('#year_level').val(window.editScheduleData.year_level);
                $('#year_level').trigger('change.cascade');
            }
            
            // Auto-fill instructor from original schedule data if empty
            if (!currentInstId && window.editScheduleData.inst_id) {
                // Wait a bit for instructor dropdown to be populated
                setTimeout(() => {
                    const instSelect = $('#inst_id');
                    if (instSelect.find(`option[value="${window.editScheduleData.inst_id}"]`).length > 0) {
                        instSelect.val(window.editScheduleData.inst_id);
                    }
                }, 200);
            }
            
            // Also try to auto-fill from subject option data if available
            if (!currentYearLevel && subjYearLevel) {
                $('#year_level').val(subjYearLevel);
                $('#year_level').trigger('change.cascade');
            }
        }
        
        // If data attributes are not available, fetch from API
        if (isNaN(subjLab) || isNaN(subjLec)) {
            fetchSubjectDetails(subjId).then(subjectData => {
                if (subjectData) {
                    subjLab = parseInt(subjectData.subj_lab) || 0;
                    subjLec = parseInt(subjectData.subj_lec) || 0;
                    // Store in option for future use
                    selectedOption.attr('data-subj-lec', subjLec);
                    selectedOption.attr('data-subj-lab', subjLab);
                    updateScheduleTypeOptions(subjLec, subjLab);
                } else {
                    // If fetch fails, enable all options
                    scheduleTypeSelect.find('option').prop('disabled', false);
                }
            }).catch(error => {
                console.error('Error fetching subject details:', error);
                // On error, enable all options
                scheduleTypeSelect.find('option').prop('disabled', false);
            });
        } else {
            updateScheduleTypeOptions(subjLec, subjLab);
        }
    });
    
    /**
     * Fetches subject details from API
     * @param {number} subjId - Subject ID
     * @returns {Promise<Object|null>} Subject data or null if error
     */
    function fetchSubjectDetails(subjId) {
        return fetch(`${getApiBasePath()}get_subjects.php?subj_id=${subjId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    return data.data[0]; // Return first subject
                }
                return null;
            })
            .catch(error => {
                console.error('Error fetching subject details:', error);
                return null;
            });
    }
    
    /**
     * Updates schedule type dropdown options based on subject hours
     * @param {number} subjLec - Lecture hours (always > 0)
     * @param {number} subjLab - Laboratory hours (can be 0)
     */
    function updateScheduleTypeOptions(subjLec, subjLab) {
        const scheduleTypeSelect = $('#schd_type');
        const currentValue = scheduleTypeSelect.val();
        
        // Lecture option - always enabled (lec always has hours)
        const lecOption = scheduleTypeSelect.find('option[value="Lec"]');
        lecOption.prop('disabled', false);
        
        // Laboratory option - only enable if subj_lab > 0
        const labOption = scheduleTypeSelect.find('option[value="Lab"]');
        if (subjLab > 0) {
            labOption.prop('disabled', false);
        } else {
            labOption.prop('disabled', true);
            // If Laboratory is currently selected but should be disabled, switch to Lecture
            if (currentValue === 'Lab') {
                scheduleTypeSelect.val('Lec');
            }
        }
        
        // Special option - always enabled
        const specialOption = scheduleTypeSelect.find('option[value="Special"]');
        specialOption.prop('disabled', false);
    }
    
    // Also ensure schedule type validation when modal opens
    $('#scheduleModal').on('shown.bs.modal', function() {
        const scheduleTypeSelect = $('#schd_type');
        const subjId = $('#subj_id').val();
        
        if (subjId) {
            // Trigger change to validate based on selected subject
            $('#subj_id').trigger('change');
        } else {
            // No subject selected - enable all options
            scheduleTypeSelect.find('option').prop('disabled', false);
        }
    });
    
    // Room -> Auto-fill day and time based on availability
    $('#rm_id').off('change.cascade change.room').on('change.cascade change.room', function() {
        const rmId = $(this).val();
        const syId = $('#sy_id').val();
        const term = $('#schd_term').val();
        
        console.log('Room change detected:', { rmId, syId, term });
        
        // If room changed and we previously auto-filled, clear the day/time fields
        // This allows new recommendations for the new room
        if (window.autoFilledDayTime) {
            console.log('Clearing previously auto-filled day/time for new room recommendation');
            $('input[name="schd_day[]"]').prop('checked', false);
            $('#schd_start').val('');
            $('#schd_end').val('');
            window.autoFilledDayTime = false;
        }
        
        // Auto-fill room availability if all required fields are available
        // This should happen BEFORE schedule recommendation to provide better suggestions
        if (rmId && syId && term) {
            console.log('All required fields present, attempting auto-fill');
            // Auto-fill availability when room is selected
            // Pass true to indicate this is from room change (allow override)
            autoFillRoomAvailability(rmId, syId, term, true);
        } else {
            console.log('Missing required fields for auto-fill:', {
                rmId,
                syId,
                term,
            });
        }
        
        // Trigger recommendation (may use the auto-filled values)
        requestScheduleRecommendation();
    });
    
    // Also trigger auto-fill when school year or term changes (if room is already selected)
    $('#sy_id, #schd_term').on('change.cascade', function() {
        const rmId = $('#rm_id').val();
        const syId = $('#sy_id').val();
        const term = $('#schd_term').val();
        
        // Only auto-fill if room is selected and day/time are not yet filled
        if (rmId && syId && term) {
            const selectedDays = $('input[name="schd_day[]"]:checked').length;
            const currentStart = $('#schd_start').val();
            const currentEnd = $('#schd_end').val();
            
            // Only auto-fill if user hasn't filled in day/time yet
            if (selectedDays === 0 && (!currentStart || !currentEnd)) {
                console.log('SY/Term changed, checking room availability for auto-fill');
                // Small delay to ensure dropdowns are updated
                setTimeout(() => {
                    autoFillRoomAvailability(rmId, syId, term);
                }, 200);
            }
        }
    });
    
    // Clear auto-fill flag when user manually changes day or time
    $('input[name="schd_day[]"]').on('change', function() {
        // If user manually changes day, clear the auto-fill flag
        if (!window.autoFillingInProgress) {
            window.autoFilledDayTime = false;
            console.log('User manually changed day, clearing auto-fill flag');
        }
    });
    
    $('#schd_start, #schd_end').on('change input', function() {
        // If user manually changes time, clear the auto-fill flag
        if (!window.autoFillingInProgress) {
            window.autoFilledDayTime = false;
            console.log('User manually changed time, clearing auto-fill flag');
        }
    });
    
    // Instructor search functionality for cross-department assignment
    setupInstructorSearch();
}

/**
 * Sets up instructor search functionality for cross-department assignment
 */
function setupInstructorSearch() {
    const searchInput = $('#instructorSearch');
    const searchResults = $('#instructorSearchResults');
    const clearBtn = $('#clearInstructorSearch');
    const instDropdown = $('#inst_id');
    let searchTimeout = null;
    
    // Debounced search function
    searchInput.on('input', function() {
        const searchTerm = $(this).val().trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Hide results if search is empty
        if (searchTerm.length < 2) {
            searchResults.hide().empty();
            return;
        }
        
        // Debounce search (wait 300ms after user stops typing)
        searchTimeout = setTimeout(() => {
            searchInstructors(searchTerm);
        }, 300);
    });
    
    // Clear search button
    clearBtn.on('click', function() {
        searchInput.val('');
        searchResults.hide().empty();
        clearBtn.hide();
    });
    
    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#instructorSearch, #instructorSearchResults, #clearInstructorSearch').length) {
            searchResults.hide();
        }
    });
    
    // Clear search when dropdown changes
    instDropdown.on('change', function() {
        if ($(this).val()) {
            searchInput.val('');
            searchResults.hide().empty();
            clearBtn.hide();
        }
    });
    
    // Show clear button when there's text
    searchInput.on('input', function() {
        if ($(this).val().trim().length > 0) {
            clearBtn.show();
        } else {
            clearBtn.hide();
        }
    });
    
    // Initially hide clear button
    clearBtn.hide();
}

/**
 * Searches for instructors across all departments
 */
function searchInstructors(searchTerm) {
    const searchResults = $('#instructorSearchResults');
    const instDropdown = $('#inst_id');
    
    if (searchTerm.length < 2) {
        searchResults.hide().empty();
        return;
    }
    
    // Show loading state
    searchResults.html('<div class="list-group-item text-center text-muted"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>').show();
    
    fetch(`${getApiBasePath()}search_instructors.php?search=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            searchResults.empty();
            
            if (data.success && data.instructors && data.instructors.length > 0) {
                // Display search results
                data.instructors.forEach(instructor => {
                    const item = $(`
                        <a href="#" class="list-group-item list-group-item-action instructor-search-item" 
                           data-inst-id="${instructor.inst_id}" 
                           data-inst-name="${instructor.name}">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${instructor.name}</h6>
                                <small class="text-muted">${instructor.dept_name}</small>
                            </div>
                        </a>
                    `);
                    
                    // Handle click on search result
                    item.on('click', function(e) {
                        e.preventDefault();
                        const instId = $(this).data('inst-id');
                        const instName = $(this).data('inst-name');
                        
                        // Set dropdown value
                        // First, check if instructor is already in dropdown
                        if (instDropdown.find(`option[value="${instId}"]`).length === 0) {
                            // Add option if not exists
                            instDropdown.append(`<option value="${instId}">${instName} (Other Dept)</option>`);
                        }
                        
                        // Select the instructor
                        instDropdown.val(instId).trigger('change');
                        
                        // Clear search
                        $('#instructorSearch').val('');
                        searchResults.hide().empty();
                        $('#clearInstructorSearch').hide();
                        
                        // Show success message
                        showToast('success', `Selected instructor: ${instName}`);
                    });
                    
                    searchResults.append(item);
                });
                
                searchResults.show();
            } else {
                searchResults.html('<div class="list-group-item text-center text-muted">No instructors found</div>').show();
            }
        })
        .catch(error => {
            console.error('Error searching instructors:', error);
            searchResults.html('<div class="list-group-item text-center text-danger">Error searching instructors</div>').show();
        });
}

/**
 * Auto-fills day and time based on room availability.
 * @param {number} rmId - Room ID
 * @param {number} syId - School Year ID
 * @param {number} term - Term
 * @param {boolean} forceOverride - If true, allows overriding existing values (for room changes)
 */
function autoFillRoomAvailability(rmId, syId, term, forceOverride = false) {
    console.log('autoFillRoomAvailability called with:', { rmId, syId, term, forceOverride });
    
    if (!rmId || !syId || !term) {
        console.log('Missing parameters for room availability check');
        return;
    }
    
    // Check if user has already filled in day, start time, or end time
    const selectedDays = $('input[name="schd_day[]"]:checked').length;
    const currentStart = $('#schd_start').val();
    const currentEnd = $('#schd_end').val();
    
    // If forceOverride is false and user has already selected day or times, don't override
    // This prevents overriding manual user input, but allows re-filling when room changes
    if (!forceOverride && (selectedDays > 0 || (currentStart && currentEnd))) {
        console.log('User has already filled in day/time and forceOverride is false, skipping auto-fill');
        return;
    }
    
    // Get schedule ID if editing (to exclude current schedule from overlap checks)
    const schdId = $('#schd_id').val();
    let apiUrl = `${getApiBasePath()}get_room_availability.php?rm_id=${rmId}&sy_id=${syId}&term=${term}`;
    if (schdId) {
        apiUrl += `&schd_id=${schdId}`;
    }
    
    fetch(apiUrl)
        .then(response => {
            console.log('Room availability API response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Room availability response data:', data);
            
            if (data.success && data.firstAvailable) {
                const slot = data.firstAvailable;
                console.log('Best available slot found:', slot);
                
                // Auto-select the recommended day
                const dayValue = slot.day; // e.g., "Mon", "Tue", etc.
                const dayCheckbox = $(`input[name="schd_day[]"][value="${dayValue}"]`);
                
                console.log(`Searching for day checkbox with value "${dayValue}"`, {
                    found: dayCheckbox.length,
                    selector: `input[name="schd_day[]"][value="${dayValue}"]`,
                });
                
                // Set flag to prevent clearing auto-fill flag during auto-fill
                window.autoFillingInProgress = true;
                
                // Clear all day checkboxes first (in case of room change)
                $('input[name="schd_day[]"]').prop('checked', false);
                
                if (dayCheckbox.length > 0) {
                    dayCheckbox.prop('checked', true);
                    // Trigger change after a small delay to avoid flag clearing
                    setTimeout(() => {
                        dayCheckbox.trigger('change');
                        window.autoFillingInProgress = false;
                    }, 50);
                    console.log(`Day "${dayValue}" checkbox checked and triggered`);
                } else {
                    console.warn(`Day checkbox for "${dayValue}" not found in DOM`);
                    window.autoFillingInProgress = false;
                }
                
                // Auto-fill start and end times
                // Format times correctly - should be HH:mm format for HTML5 time input
                $('#schd_start').val(slot.startTime);
                $('#schd_end').val(slot.endTime);
                
                // Trigger change events after a small delay
                setTimeout(() => {
                    $('#schd_start').trigger('change');
                    $('#schd_end').trigger('change');
                    window.autoFillingInProgress = false;
                }, 50);
                
                // Mark that we auto-filled these values
                window.autoFilledDayTime = true;
                
                console.log('Auto-filled times:', {
                    day: slot.day,
                    start: slot.startTime,
                    end: slot.endTime,
                    duration: slot.duration || 'N/A',
                    startFieldValue: $('#schd_start').val(),
                    endFieldValue: $('#schd_end').val(),
                });
                
                // Verify the duration is correct (should be 90 minutes = 1 hour 30 minutes)
                const startTime = new Date(`2000-01-01T${slot.startTime}:00`);
                const endTime = new Date(`2000-01-01T${slot.endTime}:00`);
                const actualDurationMinutes = (endTime - startTime) / 60000;
                console.log('Duration verification:', {
                    expected: slot.duration || 90,
                    actual: actualDurationMinutes,
                    start: slot.startTime,
                    end: slot.endTime
                });
                
                // Show success message with recommendation details
                const duration = slot.duration ? ` (${Math.round(slot.duration)} min)` : '';
                showToast('success', `Recommended: ${slot.day} from ${slot.startTime} to ${slot.endTime}${duration}`);
                
                // If there are multiple recommendations, log them for debugging
                if (data.topRecommendations && data.topRecommendations.length > 1) {
                    console.log('Top recommendations:', data.topRecommendations);
                }
            } else {
                console.log('No available slots found or API returned error:', data.message || 'Unknown error');
                showToast('warning', data.message || 'No available time slots for this room in the selected school year and term.');
            }
        })
        .catch(error => {
            console.error('Error getting room availability:', error);
            showToast('error', 'Error fetching room availability');
        });
}

/**
 * Requests a recommended schedule (day/time) for the selected instructor & subject
 * and auto-fills the start/end time and day fields if available.
 * Also considers selected days and room availability when applicable.
 */
function requestScheduleRecommendation() {
    const syId = $('#sy_id').val();
    const term = $('#schd_term').val();
    const subjId = $('#subj_id').val();
    const instId = $('#inst_id').val();
    const rmId = $('#rm_id').val();
    let schdType = $('#schd_type').val() || 'Lec';

    // Only recommend if core required fields are selected
    if (!syId || !term || !subjId || !instId) {
        return;
    }

    // Do not override if user already typed both start and end time
    const currentStart = $('#schd_start').val();
    const currentEnd = $('#schd_end').val();
    if (currentStart && currentEnd) {
        return;
    }

    // Get selected days
    const selectedDays = $('input[name="schd_day[]"]:checked')
        .map(function () {
            return $(this).val();
        })
        .get();

    // Build parameters for recommendation
    const params = new URLSearchParams({
        sy_id: syId,
        schd_term: term,
        subj_id: subjId,
        inst_id: instId,
        schd_type: schdType,
    });

    // Add selected days if any are selected (recommendation will check these days first)
    if (selectedDays.length > 0) {
        params.append('selected_days', selectedDays.join(','));
    }

    // Add room ID if a room is selected (for room-based recommendations in future enhancement)
    if (rmId) {
        params.append('rm_id', rmId);
    }

    fetch(
        `${getApiBasePath()}get_schedule_recommendation.php?${params.toString()}`
    )
        .then((response) => response.json())
        .then((data) => {
            if (!data || !data.success) {
                if (data && data.message) {
                    console.warn('No schedule recommendation:', data.message);
                }
                return;
            }

            // If recommendation returned a day, check it (in case it's different from selected)
            const dayValue = data.day;
            if (dayValue && selectedDays.length === 0) {
                // Only auto-check if no days were previously selected
                const checkbox = $(`input[name="schd_day[]"][value="${dayValue}"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', true);
                }
            }

            // Set start and end times (HTML5 time input expects HH:MM)
            if (data.start_time && data.end_time) {
                $('#schd_start').val(data.start_time);
                $('#schd_end').val(data.end_time);
            }
        })
        .catch((error) => {
            console.error('Error getting schedule recommendation:', error);
        });
}

/**
 * Loads filtered data and populates a dropdown
 */
function loadFilteredData(type, filters, selector, valueField, textField, defaultOptionText) {
    const params = new URLSearchParams();
    params.append('type', type);
    
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            params.append(key, filters[key]);
        }
    });
    
    return fetch(`${getApiBasePath()}get_filtered_data.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                populateDropdown(selector, data.data, valueField, textField, defaultOptionText);
            } else {
                console.error('Failed to load filtered data:', data.message);
                $(selector).empty().append(`<option value="">${defaultOptionText}</option>`);
            }
            return data;
        })
        .catch(error => {
            console.error('Error loading filtered data:', error);
            $(selector).empty().append(`<option value="">${defaultOptionText}</option>`);
            throw error;
        });
}

/**
 * Opens the modal to edit a schedule.
 * @param {number} schd_id The ID of the schedule to edit.
 */
function editSchedule(schd_id) {
    fetch(`${getApiBasePath()}get_schedule.php?id=${schd_id}`)
        .then(response => response.json())
        .then(data => {
            console.log('Edit schedule data received:', data);
            if (data.success) {
                const schedule = data.schedule;
                $('#scheduleModalLabel').text('Edit Schedule');
                $('#schd_id').val(schedule.schd_id);
                
                // Store original schedule data for auto-fill when subject changes
                window.editScheduleData = {
                    year_level: schedule.year_level,
                    inst_id: schedule.inst_id,
                    program_id: schedule.program_id
                };
                
                // Set up cascading dropdowns first
                setupCascadingDropdowns();
                
                // Set school year first
                $('#sy_id').val(schedule.sy_id);
                
                // Manually load terms and then set the value
                const syId = schedule.sy_id;
                if (syId) {
                    // Fetch terms for this school year
                    fetch(`${getApiBasePath()}get_filtered_data.php?type=terms&sy_id=${syId}`)
                        .then(response => response.json())
                        .then(termData => {
                            console.log('Terms loaded for edit:', termData);
                            
                            // Populate term dropdown
                            const termSelect = $('#schd_term');
                            termSelect.empty().append('<option value="">Select Term</option>');
                            
                            if (termData.success && termData.data && termData.data.length > 0) {
                                termData.data.forEach(term => {
                                    termSelect.append(`<option value="${term.term_value}">${term.term_name}</option>`);
                                });
                            } else {
                                // If no terms from class table, add default terms
                                termSelect.append('<option value="1">1st Term</option>');
                                termSelect.append('<option value="2">2nd Term</option>');
                                termSelect.append('<option value="3">Summer</option>');
                            }
                            
                            // Set the term value
                            termSelect.val(schedule.schd_term);
                            console.log('Term set to:', schedule.schd_term, 'Current value:', termSelect.val());
                            
                            // Now continue with program cascade
                            if (schedule.program_id) {
                                $('#program_id').val(schedule.program_id);
                                $('#program_id').trigger('change.cascade');
                                
                                // Wait for program cascade to complete
                                setTimeout(() => {
                                    // Set instructor
                                    if (schedule.inst_id) {
                                        $('#inst_id').val(schedule.inst_id);
                                    }
                                    
                                    // Set year level and trigger cascade to load subjects
                                    if (schedule.year_level) {
                                        $('#year_level').val(schedule.year_level);
                                        $('#year_level').trigger('change.cascade');
                                        
                                        // Wait for year level cascade to complete (subjects loaded)
                                        setTimeout(() => {
                                            // Load sections for this combination
                                            const sectionParams = new URLSearchParams({
                                                type: 'sections',
                                                program_id: schedule.program_id,
                                                year_level: schedule.year_level,
                                                sy_id: schedule.sy_id,
                                                term: schedule.schd_term
                                            });
                                            
                                            fetch(`${getApiBasePath()}get_filtered_data.php?${sectionParams.toString()}`)
                                                .then(response => response.json())
                                                .then(sectionData => {
                                                    console.log('Sections loaded for edit:', sectionData);
                                                    
                                                    const secSelect = $('#sec_id');
                                                    secSelect.empty().append('<option value="">Select Section</option>');
                                                    
                                                    if (sectionData.success && sectionData.data && sectionData.data.length > 0) {
                                                        sectionData.data.forEach(sec => {
                                                            secSelect.append(`<option value="${sec.sec_id}">${sec.sec_name}</option>`);
                                                        });
                                                        // Set section value
                                                        secSelect.val(schedule.sec_id);
                                                        console.log('Section set to:', schedule.sec_id, 'Current value:', secSelect.val());
                                                    } else {
                                                        // If no sections found from filter, fetch the specific section by ID
                                                        console.log('No sections from filter, fetching section by ID:', schedule.sec_id);
                                                        fetch(`${getApiBasePath()}get_section_by_id.php?sec_id=${schedule.sec_id}`)
                                                            .then(res => res.json())
                                                            .then(secData => {
                                                                if (secData.success && secData.section) {
                                                                    secSelect.append(`<option value="${secData.section.sec_id}" selected>${secData.section.sec_name}</option>`);
                                                                    console.log('Section loaded by ID:', secData.section);
                                                                }
                                                            })
                                                            .catch(e => console.error('Error fetching section by ID:', e));
                                                    }
                                                })
                                                .catch(err => console.error('Error loading sections:', err));
                                            
                                            // Set subject after subjects are loaded from year level cascade
                                            $('#subj_id').val(schedule.subj_id);
                                            // Trigger subject change to validate schedule type and auto-fill year_level/instructor if needed
                                            setTimeout(() => {
                                                $('#subj_id').trigger('change');
                                            }, 100);
                                        }, 400);
                                    } else {
                                        // No year level, set subject directly
                                        setTimeout(() => {
                                            $('#subj_id').val(schedule.subj_id);
                                            // Trigger change to auto-fill year_level and instructor if available
                                            setTimeout(() => {
                                                $('#subj_id').trigger('change');
                                            }, 100);
                                        }, 300);
                                    }
                                }, 500);
                            }
                        })
                        .catch(err => {
                            console.error('Error loading terms:', err);
                            // Fallback: add default terms
                            const termSelect = $('#schd_term');
                            termSelect.empty()
                                .append('<option value="">Select Term</option>')
                                .append('<option value="1">1st Term</option>')
                                .append('<option value="2">2nd Term</option>')
                                .append('<option value="3">Summer</option>');
                            termSelect.val(schedule.schd_term);
                        });
                }
                
                // Set other fields that don't cascade (set these immediately)
                $('#rm_id').val(schedule.rm_id);
                
                // Set schedule type and validate based on subject hours
                const scheduleTypeSelect = $('#schd_type');
                scheduleTypeSelect.prop('disabled', false);
                
                // After subject is set, validate schedule type options
                setTimeout(() => {
                    const subjId = $('#subj_id').val();
                    if (subjId) {
                        const selectedOption = $('#subj_id').find('option:selected');
                        let subjLab = parseInt(selectedOption.attr('data-subj-lab')) || 0;
                        let subjLec = parseInt(selectedOption.attr('data-subj-lec')) || 0;
                        
                        if (isNaN(subjLab) || isNaN(subjLec)) {
                            // Fetch subject details if not in dropdown
                            fetchSubjectDetails(subjId).then(subjectData => {
                                if (subjectData) {
                                    subjLab = parseInt(subjectData.subj_lab) || 0;
                                    subjLec = parseInt(subjectData.subj_lec) || 0;
                                    updateScheduleTypeOptions(subjLec, subjLab);
                                    // Set the schedule type after validation
                                    scheduleTypeSelect.val(schedule.schd_type);
                                } else {
                                    // If fetch fails, enable all and set value
                                    scheduleTypeSelect.find('option').prop('disabled', false);
                                    scheduleTypeSelect.val(schedule.schd_type);
                                }
                            });
                        } else {
                            updateScheduleTypeOptions(subjLec, subjLab);
                            // Set the schedule type after validation
                            scheduleTypeSelect.val(schedule.schd_type);
                        }
                    } else {
                        // No subject - enable all options
                        scheduleTypeSelect.find('option').prop('disabled', false);
                        scheduleTypeSelect.val(schedule.schd_type);
                    }
                }, 500); // Wait for subject to be set
                
                // Uncheck all day checkboxes first, then check the schedule's day
                $('input[name="schd_day[]"]').prop('checked', false);
                $(`input[name="schd_day[]"][value="${schedule.schd_day}"]`).prop('checked', true);
                $('#schd_start').val(schedule.schd_start);
                $('#schd_end').val(schedule.schd_end);
                $('#schd_status').val(schedule.schd_status).closest('.col-md-6').show();
                
                // Show Remove button and change Save button text for editing
                $('#removeScheduleBtn').show().attr('data-schd-id', schedule.schd_id);
                $('#saveScheduleBtn').text('Update Schedule');

                var scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                scheduleModal.show();
            } else {
                showToast('error', data.message || 'Failed to fetch schedule details.');
            }
        })
        .catch(error => {
            console.error('Error fetching schedule:', error);
            showToast('error', 'An error occurred while fetching schedule details.');
        });
}

/**
 * Handles the remove schedule action from the modal
 */
function handleRemoveSchedule() {
    const schdId = $('#removeScheduleBtn').attr('data-schd-id');
    if (schdId) {
        // Close the modal first, then show confirmation and delete
        const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
        if (modal) {
            modal.hide();
        }
        // Small delay to ensure modal is closed before showing confirmation
        setTimeout(() => {
            deleteSchedule(parseInt(schdId));
        }, 300);
    }
}

/**
 * Saves a new or edited schedule.
 */
function saveSchedule() {
    const form = document.getElementById('scheduleForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const schd_id = $('#schd_id').val();
    const url = schd_id ? `${getApiBasePath()}update_schedule.php` : `${getApiBasePath()}add_schedule.php`;

    const saveButton = $('#saveScheduleBtn');
    saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            const scheduleModalEl = document.getElementById('scheduleModal');
            const scheduleModalInstance = bootstrap.Modal.getInstance(scheduleModalEl);

            // Add an event listener to clean up the backdrop and reload data
            // after the modal is completely hidden.
            scheduleModalEl.addEventListener('hidden.bs.modal', function handler() {
                cleanupModalBackdrop();
                loadSchedules();
                // Remove the listener to prevent it from running multiple times
                scheduleModalEl.removeEventListener('hidden.bs.modal', handler);
            });

            scheduleModalInstance?.hide();

        } else {
            // Check if it's a conflict error and show a more detailed alert
            if (data.conflict_type) {
                Swal.fire({
                    icon: 'error',
                    title: 'Save Failed',
                    html: data.message, // Message already contains <br> tags from PHP
                    confirmButtonColor: '#800000',
                    width: '600px',
                    allowOutsideClick: false,
                    allowEscapeKey: true,
                    customClass: {
                        popup: 'conflict-alert-popup',
                        title: 'conflict-alert-title',
                        htmlContainer: 'conflict-alert-content'
                    },
                    didOpen: () => {
                        // Ensure SweetAlert appears above modal
                        const swalContainer = document.querySelector('.swal2-container');
                        if (swalContainer) {
                            swalContainer.style.zIndex = '1110';
                        }
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Save Failed',
                    html: (data.message || 'An unknown error occurred.').replace(/\n/g, '<br>'), // Convert \n to <br> for non-conflict errors too
                    confirmButtonColor: '#800000',
                    allowOutsideClick: false,
                    allowEscapeKey: true,
                    didOpen: () => {
                        // Ensure SweetAlert appears above modal
                        const swalContainer = document.querySelector('.swal2-container');
                        if (swalContainer) {
                            swalContainer.style.zIndex = '1110';
                        }
                    }
                });
            }
        }
    })
    .catch(error => {
        console.error('Error saving schedule:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving the schedule.',
            confirmButtonColor: '#800000'
        });
    })
    .finally(() => {
        // Restore button text based on whether we're editing or creating
        const schdId = $('#schd_id').val();
        const buttonText = schdId ? 'Update Schedule' : 'Save Schedule';
        saveButton.prop('disabled', false).html(buttonText);
    });
}

// Track ongoing delete operations to prevent duplicates
const deletingSchedules = new Set();

/**
 * Deletes a schedule.
 * @param {number} schd_id The ID of the schedule to delete.
 */
function deleteSchedule(schd_id) {
    // Prevent multiple simultaneous delete operations on the same schedule
    if (deletingSchedules.has(schd_id)) {
        console.log('Delete operation already in progress for schedule:', schd_id);
        return;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mark this schedule as being deleted
            deletingSchedules.add(schd_id);
            
            // Optimistic UI update: Remove the schedule block immediately from DOM
            // Try multiple selectors to ensure we find the block
            let scheduleBlock = document.querySelector(`.schedule-block[data-schd-id="${schd_id}"]`);
            
            // If not found by data attribute, try finding by onclick handler
            if (!scheduleBlock) {
                document.querySelectorAll('.schedule-block').forEach(block => {
                    if (block.onclick && block.onclick.toString().includes(`editSchedule(${schd_id})`)) {
                        scheduleBlock = block;
                    }
                });
            }
            
            // Remove the block immediately with fade-out animation
            if (scheduleBlock) {
                scheduleBlock.style.transition = 'opacity 0.2s ease-out, transform 0.2s ease-out';
                scheduleBlock.style.opacity = '0';
                scheduleBlock.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    scheduleBlock.remove();
                }, 200);
            } else {
                // If block not found, clear all blocks and reload (fallback)
                clearCalendar();
            }
            
            // Make the API call to delete from database
            fetch(`${getApiBasePath()}delete_schedule.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `schd_id=${schd_id}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Remove from deleting set
                deletingSchedules.delete(schd_id);
                
                if (data.success) {
                    showToast('success', data.message);
                    // Reload schedules to ensure consistency with server state
                    // Use a small delay to ensure the optimistic update has completed
                    setTimeout(() => {
                        loadSchedules();
                    }, 100);
                } else {
                    // If delete failed, reload to restore the schedule block
                    showToast('error', data.message || 'Failed to delete schedule.');
                    loadSchedules();
                }
            })
            .catch(error => {
                // Remove from deleting set on error
                deletingSchedules.delete(schd_id);
                
                console.error('Error deleting schedule:', error);
                showToast('error', 'An error occurred while deleting the schedule.');
                // Reload to restore the schedule block if deletion failed
                loadSchedules();
            });
        }
    });
}

/**
 * Loads source schedules for the copy modal
 */
function loadSourceSchedules() {
    const sourceSyId = $('#source_sy_id').val();
    const sourceTerm = $('#source_term').val();
    const instructorId = $('#copyByInstructorCheck').is(':checked') ? $('#copy_instructor_id').val() : 0;
    
    // Get program filter if program substitution is enabled
    const programSubstitutionEnabled = $('#programSubstitutionCheck').is(':checked');
    const programId = programSubstitutionEnabled ? $('#dest_program_id').val() : 0;
    
    if (!sourceSyId || !sourceTerm) {
        $('#sourceSchedulesContainer').hide();
        $('#sourceSchedulesTableBody').html(`
            <tr>
                <td colspan="10" class="text-center text-muted py-3">
                    <i class="bi bi-calendar-x me-2"></i>Select source school year and term to load schedules
                </td>
            </tr>
        `);
        $('#executeCopyBtn').prop('disabled', true);
        return;
    }
    
    // Show loading state
    $('#sourceSchedulesContainer').show();
    $('#sourceSchedulesTableBody').html(`
        <tr>
            <td colspan="10" class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                Loading schedules...
            </td>
        </tr>
    `);
    
    // Build query string
    let url = `${getApiBasePath()}get_source_schedules.php?source_sy_id=${sourceSyId}&source_term=${sourceTerm}`;
    if (instructorId) {
        url += `&instructor_id=${instructorId}`;
    }
    // Add program filter if program substitution is enabled
    if (programId) {
        url += `&program_id=${programId}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.schedules && data.schedules.length > 0) {
                let tableHtml = '';
                data.schedules.forEach(schedule => {
                    // Format time display more clearly
                    const timeDisplay = schedule.start_time && schedule.end_time 
                        ? `${schedule.start_time} - ${schedule.end_time}` 
                        : 'N/A';
                    
                    // Format room display
                    const roomDisplay = schedule.room_name 
                        ? (schedule.building_name ? `${schedule.room_name} (${schedule.building_name})` : schedule.room_name)
                        : 'N/A';
                    
                    // Format year level
                    const yearDisplay = schedule.year_level ? `${schedule.year_level}` : '-';
                    
                    // Format program display
                    const programDisplay = schedule.program_code || schedule.program_name || '-';
                    
                    tableHtml += `
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_schedules[]" value="${schedule.schd_id}" class="form-check-input schedule-checkbox" checked>
                            </td>
                            <td>
                                <strong class="text-maroon" style="color: #800000;">${schedule.subject_code || 'N/A'}</strong><br>
                                <small class="text-muted text-truncate" style="max-width: 120px; display: block;">${schedule.subject_desc || ''}</small>
                            </td>
                            <td><small class="badge" style="background-color: #800000; color: white;">${programDisplay}</small></td>
                            <td><span class="badge" style="background-color: #800000; color: white;">${yearDisplay}</span></td>
                            <td><span class="badge" style="background-color: #800000; color: white;">${schedule.section_name || 'N/A'}</span></td>
                            <td><strong>${schedule.instructor_name || 'N/A'}</strong></td>
                            <td><span class="badge" style="background-color: #800000; color: white;">${schedule.day || 'N/A'}</span></td>
                            <td><strong class="text-maroon">${timeDisplay}</strong></td>
                            <td><small>${roomDisplay}</small></td>
                            <td><span class="badge" style="background-color: #800000; color: white;">${schedule.type || 'Lec'}</span></td>
                        </tr>
                    `;
                });
                $('#sourceSchedulesTableBody').html(tableHtml);
                $('#schedulesCount').text(data.total);
                $('#selectAllCheckbox').prop('checked', true);
                updateCopyButtonState();
            } else {
                $('#sourceSchedulesTableBody').html(`
                    <tr>
                        <td colspan="10" class="text-center text-muted py-3">
                            <i class="bi bi-calendar-x me-2"></i>No schedules found for the selected source
                        </td>
                    </tr>
                `);
                $('#schedulesCount').text('0');
                $('#executeCopyBtn').prop('disabled', true);
            }
        })
        .catch(error => {
            console.error('Error loading source schedules:', error);
            $('#sourceSchedulesTableBody').html(`
                <tr>
                    <td colspan="10" class="text-center text-danger py-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>Error loading schedules. Please try again.
                    </td>
                </tr>
            `);
            $('#executeCopyBtn').prop('disabled', true);
        });
}

/**
 * Updates the select all checkbox state based on individual checkboxes
 */
function updateSelectAllCheckbox() {
    const totalCheckboxes = $('input[name="selected_schedules[]"]').length;
    const checkedCheckboxes = $('input[name="selected_schedules[]"]:checked').length;
    $('#selectAllCheckbox').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
}

/**
 * Updates the copy button state based on selected schedules
 */
function updateCopyButtonState() {
    const selectedCount = $('input[name="selected_schedules[]"]:checked').length;
    const hasSource = $('#source_sy_id').val() && $('#source_term').val();
    const hasDestination = $('#dest_sy_id').val() && $('#dest_term').val();
    const programSubstitutionEnabled = $('#programSubstitutionCheck').is(':checked');
    const hasDestProgram = !programSubstitutionEnabled || $('#dest_program_id').val();
    
    if (selectedCount > 0 && hasSource && hasDestination && hasDestProgram) {
        $('#executeCopyBtn').prop('disabled', false);
    } else {
        $('#executeCopyBtn').prop('disabled', true);
    }
}

/**
 * Executes the copy schedules operation.
 */
function executeCopySchedules() {
    const form = document.getElementById('copyScheduleForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Check if at least one schedule is selected
    const selectedSchedules = $('input[name="selected_schedules[]"]:checked').map(function() {
        return $(this).val();
    }).get();
    
    if (selectedSchedules.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Schedules Selected',
            text: 'Please select at least one schedule to copy.',
            confirmButtonColor: '#800000'
        });
        return;
    }

    const formData = new FormData(form);
    
    // Add program substitution flag if enabled
    const programSubstitutionEnabled = $('#programSubstitutionCheck').is(':checked');
    if (programSubstitutionEnabled) {
        formData.append('program_substitution', '1');
        const destProgramId = $('#dest_program_id').val();
        if (destProgramId) {
            formData.append('dest_program_id', destProgramId);
        }
    } else {
        formData.append('program_substitution', '0');
    }
    
    // Add selected schedule IDs to form data
    selectedSchedules.forEach(scheduleId => {
        formData.append('selected_schedules[]', scheduleId);
    });
    
    const copyButton = $('#executeCopyBtn');
    copyButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Copying...');

    fetch(`${getApiBasePath()}copy_schedules.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Copy Successful',
                text: data.message,
                confirmButtonColor: '#800000'
            }).then(() => {
                const copyModalEl = document.getElementById('copyScheduleModal');
                const copyModalInstance = bootstrap.Modal.getInstance(copyModalEl);
                
                // Add an event listener to clean up the backdrop after the modal is hidden
                copyModalEl.addEventListener('hidden.bs.modal', function handler() {
                    cleanupModalBackdrop();
                    // Remove the listener to prevent it from running multiple times
                    copyModalEl.removeEventListener('hidden.bs.modal', handler);
                });

                copyModalInstance?.hide();

                // Switch filters to the new term and reload
                $('#syFilter').val(formData.get('dest_sy_id'));
                $('#termFilter').val(formData.get('dest_term'));
                loadSchedules();
                
                // Reset copy modal
                $('#copyScheduleForm')[0].reset();
                $('#sourceSchedulesContainer').hide();
                $('#programSubstitutionContainer').hide();
                $('#programSubstitutionCheck').prop('checked', false);
                $('#dest_program_id').prop('required', false);
                $('#sourceSchedulesTableBody').html(`
                    <tr>
                        <td colspan="10" class="text-center text-muted py-3">
                            <i class="bi bi-calendar-x me-2"></i>Select source school year and term to load schedules
                        </td>
                    </tr>
                `);
                $('#executeCopyBtn').prop('disabled', true);
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Copy Failed',
                text: data.message || 'An unknown error occurred.',
                confirmButtonColor: '#800000'
            });
        }
    })
    .catch(error => console.error('Error copying schedules:', error))
    .finally(() => {
        copyButton.prop('disabled', false).html('Execute Copy');
    });
}

/**
 * Enhanced toast notification function with improved styling.
 * Success messages are shown as centered modals (like confirmation dialogs).
 * Other messages (error, warning, info) are shown as toast notifications.
 */
function showToast(icon, title) {
    // Show all messages as centered modal dialogs (like SweetAlert confirmation dialogs)
    const iconConfig = {
        'success': {
            icon: 'success',
            title: 'Success!',
            confirmButtonColor: '#28a745',
            confirmButtonText: 'OK'
        },
        'error': {
            icon: 'error',
            title: 'Error!',
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'OK'
        },
        'warning': {
            icon: 'warning',
            title: 'Warning!',
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'OK'
        },
        'info': {
            icon: 'info',
            title: 'Information',
            confirmButtonColor: '#17a2b8',
            confirmButtonText: 'OK'
        }
    };

    const config = iconConfig[icon] || iconConfig['info'];

    Swal.fire({
        icon: config.icon,
        title: config.title,
        text: title,
        confirmButtonColor: config.confirmButtonColor,
        confirmButtonText: config.confirmButtonText,
        allowOutsideClick: false,
        allowEscapeKey: true,
        customClass: {
            popup: 'sweet-alert-popup',
            title: 'sweet-alert-title',
            content: 'sweet-alert-content',
            confirmButton: 'sweet-alert-button'
        },
        didOpen: () => {
            // Ensure SweetAlert appears above modals
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
                swalContainer.style.zIndex = '1110';
            }
        }
    });
}

// Override the placeholder script in schedule_management.php
document.addEventListener('DOMContentLoaded', function() {
    const scheduleScriptTag = document.querySelector('script[data-schedule-script]');
    if (scheduleScriptTag) {
        scheduleScriptTag.remove();
    }
});


/**
 * Overrides for different dashboards (moderator, instructor) if needed.
 * This ensures the correct API endpoints are called based on the user's role.
 */
function adjustScheduleApiPaths() {
    // This function is now replaced by the more dynamic getApiBasePath()
    console.log("Using API base path: " + getApiBasePath());
}

/**
 * Opens the modal to add a new schedule.
 * This is triggered by the "Create Schedule" button in the UI.
 */
function openAddScheduleModal() {
    $('#scheduleForm')[0].reset();
    $('#schd_id').val('');
    $('#scheduleModalLabel').text('Create Schedule');
    $('#schd_status').closest('.col-md-6').hide();
    
    // Clear stored edit schedule data
    window.editScheduleData = null;
    
    var scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    scheduleModal.show();
}

// Replace the placeholder script in schedule_management.php with a call to this new file.
// We will also modify the dashboard files to include this new JS file.

// Event listener for the "Create Schedule" button

/**
 * Creates a print window for admin schedule view (section-based)
 * @param {string} sectionName - The name of the section
 * @param {string} semesterText - The semester text
 */
function getLogoPath() {
    // Build an absolute logo path that works across admin/moderator/instructor dashboards
    const currentPath = window.location.pathname;
    const parts = currentPath.split('/').filter(Boolean);
    // Strip the last 3 segments (views/<role>/dashboard.php) to get repo root
    const rootPath = parts.slice(0, -3).join('/');
    const base = rootPath ? `/${rootPath}` : '';
    return `${window.location.origin}${base}/assets/img/evsu-logo.png`;
}

function createPrintWindow(sectionName, semesterText) {
    console.log('=== createPrintWindow FUNCTION CALLED ===');
    // Get schedule data from window.schedulesData
    let schedules = window.schedulesData || [];
    console.log('Admin print - Schedule data count:', schedules.length);
    console.log('Admin print - Section:', sectionName, 'Semester:', semesterText);
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    if (!printWindow) {
        if (typeof showToast === 'function') {
            showToast('error', 'Please allow popups to print the schedule.');
        } else {
            alert('Please allow popups to print the schedule.');
        }
        return;
    }
    
    // Get logo path
    const logoPath = getLogoPath();
    
    // Day order matching calendar: MON, TUE, WED, THU, FRI, SAT, SUN
    const days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
    const dayMap = {
        'Mon': 'MON', 'Tue': 'TUE', 'Wed': 'WED', 'Thu': 'THU',
        'Fri': 'FRI', 'Sat': 'SAT', 'Sun': 'SUN'
    };
    
    // Generate time slots from 7:00 AM to 8:30 PM with 30-minute intervals (matching calendar)
    const timeSlots = [];
    for (let hour = 7; hour <= 20; hour++) {
        const period = hour < 12 ? 'AM' : 'PM';
        const displayHour = hour === 12 ? 12 : hour > 12 ? hour % 12 : hour;
        const hourLabel = `${displayHour}:00 ${period}`;
        
        // Add main hour slot (e.g., "7:00 AM")
        timeSlots.push({ 
            hour: hour, 
            label: hourLabel,
            minutes: 0,
            totalMinutes: hour * 60
        });
        
        // Add 30-minute slot (e.g., "7:30")
        if (hour < 20) { // Don't add :30 for 8:30 PM (last slot)
            timeSlots.push({ 
                hour: hour, 
                label: `${displayHour}:30`,
                minutes: 30,
                totalMinutes: hour * 60 + 30
            });
        }
    }
    
    // Add 8:30 PM as the last slot
    timeSlots.push({
        hour: 20,
        label: '8:30 PM',
        minutes: 30,
        totalMinutes: 20 * 60 + 30
    });
    
    // Parse time string to minutes from midnight
    const parseTimeToMinutes = (timeStr) => {
        if (!timeStr) return null;
        // Format: "10:00 AM" or "10:00AM" or "10:00 am"
        const match = timeStr.trim().match(/(\d+):(\d+)\s*(AM|PM|am|pm)/i);
        if (match) {
            let hour = parseInt(match[1]);
            const min = parseInt(match[2]);
            const period = match[3].toUpperCase();
            if (period === 'PM' && hour !== 12) hour += 12;
            if (period === 'AM' && hour === 12) hour = 0;
            return hour * 60 + min;
        }
        return null;
    };
    
    // Get instructor colors (matching calendar rendering)
    const baseColors = [
        '#28a745', '#2E5C8A', '#800000', '#4B0082', '#FF6B35', '#006400', '#8B4513',
        '#2F4F4F', '#A0522D', '#5F9EA0', '#C71585', '#556B2F', '#DC143C', '#20B2AA',
        '#9370DB', '#FF6347', '#32CD32', '#1E90FF', '#FF1493', '#00CED1', '#FF8C00',
        '#8A2BE2', '#228B22', '#CD5C5C', '#4682B4'
    ];
    const instructorColors = {};
    
    // Process schedules - convert to format we can use
    const processedSchedules = schedules.map(s => {
        const day = dayMap[s.schd_day] || s.schd_day;
        const startMinutes = parseTimeToMinutes(s.start_time);
        const endMinutes = parseTimeToMinutes(s.end_time);
        
        // Get instructor color
        const instId = s.inst_id || 0;
        if (!instructorColors[instId]) {
            const colorIdx = instId % baseColors.length;
            instructorColors[instId] = baseColors[colorIdx];
        }
        const scheduleColor = s.is_overtime === 'Yes' ? '#dc3545' : instructorColors[instId];
        
        // Format instructor name (abbreviated: First Initial. Last Name)
        let instructorDisplay = '';
        if (s.instructor_name) {
            const nameParts = s.instructor_name.trim().split(' ');
            if (nameParts.length >= 2) {
                const firstName = nameParts[0];
                const lastName = nameParts[nameParts.length - 1];
                instructorDisplay = `${firstName.charAt(0)}. ${lastName}`;
            } else {
                instructorDisplay = s.instructor_name;
            }
        }
        
        // Format room name
        const roomDisplay = s.rm_name || '';
        const isVirtual = s.bd_desc && s.bd_desc.trim().toUpperCase() === 'VIRTUAL';
        const virtualIndicator = isVirtual ? ' <span style="font-size: 0.7rem;">(Online)</span>' : '';
        
        return {
            day: day,
            subjCode: s.subj_code || '',
            instructor: instructorDisplay,
            room: roomDisplay + virtualIndicator,
            time: `${s.start_time} - ${s.end_time}`,
            type: s.schd_type || '',
            startMinutes: startMinutes,
            endMinutes: endMinutes,
            startTime: s.start_time,
            endTime: s.end_time,
            isOvertime: s.is_overtime === 'Yes',
            color: scheduleColor
        };
    }).filter(s => s.startMinutes !== null && s.endMinutes !== null && s.startMinutes < s.endMinutes);
    
    // Track which schedules have been rendered to avoid duplicates
    const renderedSchedules = new Set();
    
    // Build table rows - matching calendar format exactly
    let tableRows = '';
    timeSlots.forEach((timeSlot, slotIndex) => {
        tableRows += '<tr>';
        
        // Time cell - show full time for :00 slots, just :30 for half-hour slots
        const isHalfHour = timeSlot.minutes === 30;
        if (isHalfHour) {
            tableRows += `<td class="time-cell time-half">${timeSlot.label}</td>`;
        } else {
            tableRows += `<td class="time-cell time-main">${timeSlot.label}</td>`;
        }
        
        // Day cells
        days.forEach(day => {
            let cellContent = '';
            
            // Find schedules that START in this time slot
            const cellSchedules = processedSchedules.filter(s => {
                if (s.day !== day) return false;
                
                // Create unique ID for this schedule
                const scheduleId = `${s.day}-${s.subjCode}-${s.startMinutes}-${s.endMinutes}`;
                
                // Only show if it starts in this exact time slot and hasn't been rendered yet
                const slotStart = timeSlot.totalMinutes;
                const slotEnd = slotStart + 30; // 30-minute slots
                const startsInSlot = s.startMinutes >= slotStart && s.startMinutes < slotEnd;
                
                if (startsInSlot && !renderedSchedules.has(scheduleId)) {
                    renderedSchedules.add(scheduleId);
                    return true;
                }
                return false;
            });
            
            // Build cell content
            if (cellSchedules.length > 0) {
                cellSchedules.forEach(sched => {
                    // Calculate how many 30-minute intervals this schedule spans
                    const duration = sched.endMinutes - sched.startMinutes;
                    const intervalsToSpan = Math.max(1, Math.ceil(duration / 30));
                    // Each row is compact (about 12-14px), calculate total height
                    const rowHeight = 13; // Compact row height to fit everything
                    const totalHeight = intervalsToSpan * rowHeight - 2;
                    
                    const overtimeClass = sched.isOvertime ? 'overtime-schedule' : '';
                    // Format exactly like calendar: Subject Code (bold), Instructor, Room, Time, Type
                    cellContent += `<div class="schedule-item ${overtimeClass}" style="height: ${totalHeight}px; background: ${sched.color}; border-left: 4px solid ${sched.isOvertime ? '#bd2130' : sched.color};">
                        <div class="subj-code-print"><strong>${escapeHtml(sched.subjCode)}</strong></div>
                        <div class="instructor-print">${escapeHtml(sched.instructor)}</div>
                        <div class="room-print">${escapeHtml(sched.room)}</div>
                        <div class="time-print">${escapeHtml(sched.time)}</div>
                        <div class="type-print">${escapeHtml(sched.type)}</div>
                    </div>`;
                });
            }
            
            tableRows += `<td class="day-cell">${cellContent}</td>`;
        });
        
        tableRows += '</tr>';
    });
    
    // Write the print document
    printWindow.document.open('text/html', 'replace');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Class Schedule - Print</title>
            <style>
                @page {
                    margin: 0.3cm;
                    size: letter portrait;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Poppins', 'Arial', 'Helvetica', sans-serif;
                    font-size: 9pt;
                    line-height: 1.2;
                    color: #000;
                    background: #fff;
                    padding: 0 10px 10px 10px; /* no top padding to avoid blank space */
                }
                
                .print-header {
                    margin-top: 0;
                    margin-bottom: 8px;
                }
                
                .header-top {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 12px;
                }
                
                .logo-section {
                    margin-bottom: 8px;
                }
                
                .logo-section img {
                    height: 60px;
                    width: auto;
                }
                
                .university-info {
                    text-align: center;
                    line-height: 1.2;
                }
                
                .republic-text {
                    font-size: 10pt;
                    color: #000;
                    margin-bottom: 3px;
                }
                
                .university-name {
                    font-size: 14pt;
                    font-weight: bold;
                    color: #800000;
                    margin-bottom: 2px;
                    text-transform: uppercase;
                }
                
                .campus-name {
                    font-size: 11pt;
                    color: #000;
                    margin-bottom: 10px;
                }
                
                .office-name {
                    font-size: 12pt;
                    font-weight: bold;
                    color: #800000;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    padding-bottom: 6px;
                    border-bottom: 2px solid #800000;
                }
                
                /* Schedule Table Styles - EVSU-OCC Theme */
                .schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 2px solid #800000;
                    margin: 0 auto;
                    background: #fff;
                }
                
                .schedule-table th {
                    background: #800000;
                    color: #fff;
                    font-weight: bold;
                    padding: 4px 3px;
                    text-align: center;
                    border: 1px solid #660000;
                    font-size: 7pt;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                
                .schedule-table th.time-header {
                    background: #800000;
                    width: 70px;
                }
                
                .schedule-table td {
                    border: 1px solid #ccc;
                    padding: 1px;
                    vertical-align: top;
                    height: 10px;
                }
                
                .schedule-table td.time-cell {
                    background: #f5f5f5;
                    font-weight: 600;
                    text-align: center;
                    border-right: 2px solid #800000;
                    padding: 2px 3px;
                    width: 70px;
                    font-size: 7pt;
                }
                
                .time-main {
                    font-size: 7pt;
                    font-weight: bold;
                }
                
                .time-half {
                    font-size: 6pt;
                    color: #666;
                }
                
                .schedule-table td.day-cell {
                    background: #fff;
                    position: relative;
                    height: 10px;
                    vertical-align: top;
                    padding: 0;
                }
                
                .schedule-item {
                    border: none;
                    border-radius: 2px;
                    padding: 1px 2px;
                    margin: 0;
                    font-size: 5pt;
                    page-break-inside: avoid;
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    width: 100%;
                    box-sizing: border-box;
                    color: #fff;
                    overflow: hidden;
                }
                
                .schedule-item.overtime-schedule {
                    background: #dc3545 !important;
                    border-left: 2px solid #bd2130 !important;
                }
                
                .subj-code-print {
                    font-size: 6pt;
                    font-weight: 900;
                    margin-bottom: 0px;
                    color: #fff;
                }
                
                .instructor-print, .room-print, .time-print, .type-print {
                    font-size: 4pt;
                    line-height: 0.9;
                    color: #fff;
                    margin: 0;
                }
                
                .schedule-item strong {
                    font-weight: 900;
                }
                
                .print-footer {
                    margin-top: 10px;
                    padding-top: 8px;
                    border-top: 2px solid #800000;
                    text-align: center;
                    font-size: 7pt;
                    color: #666;
                }
                
                .print-footer p {
                    margin: 2px 0;
                }
                
                @media print {
                    body {
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
                        transform: scale(0.85);
                        transform-origin: top left;
                        width: 117.65%;
                    }
                    
                    .print-header {
                        page-break-after: avoid;
                    }
                    
                    .schedule-table {
                        page-break-inside: avoid;
                    }
                    
                    .schedule-item {
                        page-break-inside: avoid;
                    }
                    
                    * {
                        page-break-inside: avoid;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <div class="header-top">
                    <div class="logo-section">
                        <img src="${logoPath}" alt="EVSU Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="university-info">
                        <div class="republic-text">Republic of the Philippines</div>
                        <div class="university-name">EASTERN VISAYAS STATE UNIVERSITY</div>
                        <div class="campus-name">Ormoc City Campus</div>
                        <div class="office-name">Class Schedule</div>
                    </div>
                </div>
            </div>
            
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th class="time-header">TIME</th>
                        <th>MON</th>
                        <th>TUE</th>
                        <th>WED</th>
                        <th>THU</th>
                        <th>FRI</th>
                        <th>SAT</th>
                        <th>SUN</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
            
            <div class="print-footer">
                <p><strong>This is an official document generated by the EVSU-OCC Scheduling System</strong></p>
                <p>Eastern Visayas State University - Ormoc City Campus</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for content to load, then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
    
    // Fallback if onload doesn't fire
    setTimeout(() => {
        if (printWindow.document.readyState === 'complete') {
            printWindow.print();
        }
    }, 1000);
}

/**
 * Creates a formatted print view of the schedule chart/graph only (like template).
 */
function printSchedule() {
    // Get schedule data from window.schedulesData or fetch it
    let schedules = window.schedulesData || [];
    
    // If no data, try to get from calendar blocks as fallback
    if (!schedules || schedules.length === 0) {
        const scheduleBlocks = Array.from(document.querySelectorAll('.schedule-block'));
        schedules = scheduleBlocks.map(block => {
            const dayContent = block.closest('.day-column-content');
            const dayMatch = dayContent ? dayContent.id.match(/day-content-(Mon|Tue|Wed|Thu|Fri|Sat|Sun)/) : null;
            const day = dayMatch ? dayMatch[1] : '';
            
            const subjCode = block.querySelector('.subj-code-simple')?.textContent || '';
            const room = block.querySelector('.room-simple')?.textContent || '';
            const time = block.querySelector('.time-simple')?.textContent || '';
            const isOvertime = block.classList.contains('overtime-block');
            
            return {
                schd_day: day,
                subj_code: subjCode,
                room_name: room,
                start_time: time.split(' - ')[0] || '',
                end_time: time.split(' - ')[1] || '',
                is_overtime: isOvertime ? 'Yes' : 'No'
            };
        });
    }
    
    // Get section name and semester
    const sectionName = $('#sectionFilter option:selected').text() || 'All Sections';
    let semesterText = '';
    
    // Check if user is instructor (for instructor dashboard)
    const isInstructor = window.location.pathname.includes('/views/instructor/');
    
    // Fetch instructor information and current academic period (only for instructors)
    if (isInstructor) {
        Promise.all([
            fetch('../../api/reports/get_profile_data.php').then(r => r.json()).catch(() => ({ success: false })),
            fetch('../../shared/management/get_current_sy_semester.php').then(r => r.json()).catch(() => ({ success: false }))
        ]).then(([profileData, academicData]) => {
            console.log('Profile data received:', profileData);
            console.log('Academic data received:', academicData);
            
            // Get instructor information from database
            // Name comes from instructor table (inst_fname, inst_lname, inst_mname) or account table as fallback
            let instructorName = 'INSTRUCTOR NAME';
            if (profileData.success) {
                if (profileData.fname && profileData.lname) {
                    const fname = (profileData.fname || '').trim().toUpperCase();
                    const lname = (profileData.lname || '').trim().toUpperCase();
                    const minitial = (profileData.minitial || '').trim().toUpperCase();
                    const suffix = (profileData.suffix || '').trim().toUpperCase();
                    
                    // Format: FIRSTNAME LASTNAME M.I. SUFFIX
                    instructorName = `${fname} ${lname}${minitial ? ' ' + minitial + '.' : ''}${suffix ? ' ' + suffix : ''}`.trim();
                    
                    console.log('Instructor name constructed:', {
                        fname, lname, minitial, suffix, 
                        finalName: instructorName
                    });
                } else {
                    console.warn('Missing name data in profile:', profileData);
                    console.warn('Available fields:', Object.keys(profileData));
                }
            } else {
                console.error('Profile data fetch failed:', profileData);
            }
            
            // Get academic rank from database
            let instructorRank = '';
            if (profileData.success && profileData.rank) {
                instructorRank = profileData.rank.trim();
                // Don't uppercase rank - keep original format (e.g., "Assistant Professor III")
            } else if (profileData.success) {
                console.warn('No rank found in profile data for user:', profileData.acc_user);
            }
            
            // Get campus/college from database
            let campusName = 'EVSU ORMOC CAMPUS';
            if (profileData.success) {
                if (profileData.dept_name && profileData.dept_name.trim()) {
                    campusName = `EVSU ${profileData.dept_name.trim().toUpperCase()}`;
                } else if (profileData.college_name && profileData.college_name.trim()) {
                    campusName = `EVSU ${profileData.college_name.trim().toUpperCase()}`;
                }
            }
            
            // Get academic period from database
            let semester = '';
            let schoolYear = '';
            let summer = '';
            
            if (academicData.success && academicData.data) {
                const sem = (academicData.data.semester || '').trim();
                
                // Handle different semester formats
                if (sem.toLowerCase() === '1st' || sem.toLowerCase() === 'first' || sem === '1') {
                    semester = 'FIRST';
                } else if (sem.toLowerCase() === '2nd' || sem.toLowerCase() === 'second' || sem === '2') {
                    semester = 'SECOND';
                } else if (sem.toLowerCase() === 'summer' || sem.toLowerCase().includes('summer')) {
                    semester = '';
                    summer = 'SUMMER';
                } else if (sem) {
                    // Use as-is if it's a different format
                    semester = sem.toUpperCase();
                }
                
                // Get school year from database
                if (academicData.data.sy_year) {
                    schoolYear = academicData.data.sy_year.trim();
                }
                
                // Set semesterText for print display
                if (semester) {
                    semesterText = semester;
                } else if (summer) {
                    semesterText = summer;
                } else if (academicData.data.semester) {
                    semesterText = academicData.data.semester;
                }
            }
            
            // Semester is now determined from active school year/semester settings only
            // No fallback needed since term filter is removed
            if (!semester && !summer && academicData.success && academicData.data && academicData.data.semester) {
                const sem = (academicData.data.semester || '').trim();
                if (sem.toLowerCase().includes('1st') || sem.toLowerCase().includes('first')) {
                    semester = 'FIRST';
                } else if (sem.toLowerCase().includes('2nd') || sem.toLowerCase().includes('second')) {
                    semester = 'SECOND';
                } else if (sem.toLowerCase().includes('summer') || sem.toLowerCase().includes('mid-year')) {
                    semester = '';
                    summer = 'SUMMER';
                }
            }
            
            console.log('Processed instructor data:', {
                instructorName,
                instructorRank,
                campusName,
                semester,
                schoolYear,
                summer
            });
            
            // Get current date for form
            const currentDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            // Create print window with instructor data
            console.log('Calling createPrintWindowWithInstructorData with data:', {
                instructorName, instructorRank, campusName, semester, schoolYear, summer, currentDate
            });
            createPrintWindowWithInstructorData(instructorName, instructorRank, campusName, semester, schoolYear, summer, currentDate);
        }).catch((error) => {
            console.error('Error fetching instructor data for print:', error);
            console.error('Error details:', error.message, error.stack);
            
            // Try to fetch data individually if Promise.all fails
            console.log('Attempting individual data fetches...');
            
            // Fetch profile data
            fetch('../../api/reports/get_profile_data.php')
                .then(r => r.json())
                .then(profileData => {
                    console.log('Individual profile fetch:', profileData);
                    
                    // Fetch academic data
                    return fetch('../../shared/management/get_current_sy_semester.php')
                        .then(r => r.json())
                        .then(academicData => {
                            console.log('Individual academic fetch:', academicData);
                            
                            // Process data same as above
                            let instructorName = 'INSTRUCTOR NAME';
                            if (profileData.success && profileData.fname && profileData.lname) {
                                const fname = (profileData.fname || '').trim().toUpperCase();
                                const lname = (profileData.lname || '').trim().toUpperCase();
                                const minitial = (profileData.minitial || '').trim().toUpperCase();
                                instructorName = `${fname} ${lname}${minitial ? ' ' + minitial + '.' : ''}`.trim();
                            }
                            
                            let instructorRank = '';
                            if (profileData.success && profileData.rank) {
                                instructorRank = profileData.rank.trim().toUpperCase();
                            }
                            
                            let campusName = 'EVSU ORMOC CAMPUS';
                            if (profileData.success) {
                                if (profileData.dept_name && profileData.dept_name.trim()) {
                                    campusName = `EVSU ${profileData.dept_name.trim().toUpperCase()}`;
                                } else if (profileData.college_name && profileData.college_name.trim()) {
                                    campusName = `EVSU ${profileData.college_name.trim().toUpperCase()}`;
                                }
                            }
                            
                            let semester = '';
                            let schoolYear = '';
                            let summer = '';
                            
                            if (academicData.success && academicData.data) {
                                const sem = (academicData.data.semester || '').trim();
                                if (sem.toLowerCase() === '1st' || sem.toLowerCase() === 'first' || sem === '1') {
                                    semester = 'FIRST';
                                } else if (sem.toLowerCase() === '2nd' || sem.toLowerCase() === 'second' || sem === '2') {
                                    semester = 'SECOND';
                                } else if (sem.toLowerCase() === 'summer' || sem.toLowerCase().includes('summer')) {
                                    semester = '';
                                    summer = 'SUMMER';
                                } else if (sem) {
                                    semester = sem.toUpperCase();
                                }
                                
                                if (academicData.data.sy_year) {
                                    schoolYear = academicData.data.sy_year.trim();
                                }
                            }
                            
                            const currentDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                            createPrintWindowWithInstructorData(instructorName, instructorRank, campusName, semester, schoolYear, summer, currentDate);
                        });
                })
                .catch(err => {
                    console.error('Individual fetch also failed:', err);
                    // Final fallback
                    const currentDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    createPrintWindowWithInstructorData('INSTRUCTOR NAME', '', 'EVSU ORMOC CAMPUS', semesterText.toUpperCase(), '', '', currentDate);
                });
        });
    } else {
        console.log('Using admin print format (not instructor)');
        // For admin side, use original print format
        createPrintWindow(sectionName, semesterText);
    }
}

function createPrintWindowWithInstructorData(instructorName, instructorRank, campusName, semester, schoolYear, summer, currentDate) {
    // Debug logging
    console.log('=== createPrintWindowWithInstructorData CALLED ===');
    console.log('Parameters:', {
        instructorName,
        instructorRank,
        campusName,
        semester,
        schoolYear,
        summer,
        currentDate
    });
    
    // Get schedule data from window.schedulesData
    let schedules = window.schedulesData || [];
    console.log('Schedule data count:', schedules.length);
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    if (!printWindow) {
        showToast('error', 'Please allow popups to print the schedule.');
        return;
    }
    
    // Get logo path
    const logoPath = getLogoPath();
    
    // Day order matching calendar: MON, TUE, WED, THU, FRI, SAT, SUN
    const days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
    const dayMap = {
        'Mon': 'MON', 'Tue': 'TUE', 'Wed': 'WED', 'Thu': 'THU',
        'Fri': 'FRI', 'Sat': 'SAT', 'Sun': 'SUN'
    };
    
    // Generate time slots from 7:00 AM to 8:30 PM with 30-minute intervals (matching calendar)
    const timeSlots = [];
    // Start from 7:00 AM (hour 7) to 8:30 PM (hour 20, minute 30)
    for (let hour = 7; hour <= 20; hour++) {
        const period = hour < 12 ? 'AM' : 'PM';
        const displayHour = hour === 12 ? 12 : hour > 12 ? hour % 12 : hour;
        const hourLabel = `${displayHour}:00 ${period}`;
        
        // Add main hour slot (e.g., "7:00 AM")
        timeSlots.push({ 
            hour: hour, 
            label: hourLabel,
            minutes: 0,
            totalMinutes: hour * 60
        });
        
        // Add 30-minute slot (e.g., "7:30")
        if (hour < 20) { // Don't add :30 for 8:30 PM (last slot)
            timeSlots.push({ 
                hour: hour, 
                label: `${displayHour}:30`,
                minutes: 30,
                totalMinutes: hour * 60 + 30
            });
        }
    }
    
    // Add 8:30 PM as the last slot
    timeSlots.push({
        hour: 20,
        label: '8:30 PM',
        minutes: 30,
        totalMinutes: 20 * 60 + 30
    });
    
    // Parse time string to minutes from midnight
    const parseTimeToMinutes = (timeStr) => {
        if (!timeStr) return null;
        // Format: "10:00 AM" or "10:00AM" or "10:00 am"
        const match = timeStr.trim().match(/(\d+):(\d+)\s*(AM|PM|am|pm)/i);
        if (match) {
            let hour = parseInt(match[1]);
            const min = parseInt(match[2]);
            const period = match[3].toUpperCase();
            if (period === 'PM' && hour !== 12) hour += 12;
            if (period === 'AM' && hour === 12) hour = 0;
            return hour * 60 + min;
        }
        return null;
    };
    
    // Get instructor colors (matching calendar rendering)
    const baseColors = [
        '#28a745', '#2E5C8A', '#800000', '#4B0082', '#FF6B35', '#006400', '#8B4513',
        '#2F4F4F', '#A0522D', '#5F9EA0', '#C71585', '#556B2F', '#DC143C', '#20B2AA',
        '#9370DB', '#FF6347', '#32CD32', '#1E90FF', '#FF1493', '#00CED1', '#FF8C00',
        '#8A2BE2', '#228B22', '#CD5C5C', '#4682B4'
    ];
    const instructorColors = {};
    
    // Process schedules - convert to format we can use
    const processedSchedules = schedules.map(s => {
        const day = dayMap[s.schd_day] || s.schd_day;
        const startMinutes = parseTimeToMinutes(s.start_time);
        const endMinutes = parseTimeToMinutes(s.end_time);
        
        // Get instructor color
        const instId = s.inst_id || 0;
        if (!instructorColors[instId]) {
            const colorIdx = instId % baseColors.length;
            instructorColors[instId] = baseColors[colorIdx];
        }
        const scheduleColor = s.is_overtime === 'Yes' ? '#dc3545' : instructorColors[instId];
        
        // Format instructor name (abbreviated: First Initial. Last Name)
        let instructorDisplay = '';
        if (s.instructor_name) {
            const nameParts = s.instructor_name.trim().split(' ');
            if (nameParts.length >= 2) {
                const firstName = nameParts[0];
                const lastName = nameParts[nameParts.length - 1];
                instructorDisplay = `${firstName.charAt(0)}. ${lastName}`;
            } else {
                instructorDisplay = s.instructor_name;
            }
        }
        
        // Format room name (just room name, matching calendar)
        const roomDisplay = s.rm_name || '';
        const isVirtual = s.bd_desc && s.bd_desc.trim().toUpperCase() === 'VIRTUAL';
        const virtualIndicator = isVirtual ? ' <span class="badge bg-info" style="font-size: 0.7rem;"><i class="bi bi-laptop me-1"></i>Online</span>' : '';
        
        return {
            day: day,
            subjCode: s.subj_code || '',
            instructor: instructorDisplay,
            room: roomDisplay + virtualIndicator,
            time: `${s.start_time} - ${s.end_time}`,
            type: s.schd_type || '',
            startMinutes: startMinutes,
            endMinutes: endMinutes,
            startTime: s.start_time,
            endTime: s.end_time,
            isOvertime: s.is_overtime === 'Yes',
            color: scheduleColor
        };
    }).filter(s => s.startMinutes !== null && s.endMinutes !== null && s.startMinutes < s.endMinutes);
    
    // Track which schedules have been rendered to avoid duplicates
    const renderedSchedules = new Set();
    
    // Build table rows - matching calendar format exactly
    let tableRows = '';
    timeSlots.forEach((timeSlot, slotIndex) => {
        tableRows += '<tr>';
        
        // Time cell - show full time for :00 slots, just :30 for half-hour slots
        const isHalfHour = timeSlot.minutes === 30;
        if (isHalfHour) {
            tableRows += `<td class="time-cell time-half">${timeSlot.label}</td>`;
        } else {
            tableRows += `<td class="time-cell time-main">${timeSlot.label}</td>`;
        }
        
        // Day cells
        days.forEach(day => {
            let cellContent = '';
            
            // Find schedules that START in this time slot
            const cellSchedules = processedSchedules.filter(s => {
                if (s.day !== day) return false;
                
                // Create unique ID for this schedule
                const scheduleId = `${s.day}-${s.subjCode}-${s.startMinutes}-${s.endMinutes}`;
                
                // Only show if it starts in this exact time slot and hasn't been rendered yet
                const slotStart = timeSlot.totalMinutes;
                const slotEnd = slotStart + 30; // 30-minute slots
                const startsInSlot = s.startMinutes >= slotStart && s.startMinutes < slotEnd;
                
                if (startsInSlot && !renderedSchedules.has(scheduleId)) {
                    renderedSchedules.add(scheduleId);
                    return true;
                }
                return false;
            });
            
            // Build cell content
            if (cellSchedules.length > 0) {
                cellSchedules.forEach(sched => {
                    // Calculate how many 30-minute intervals this schedule spans
                    const duration = sched.endMinutes - sched.startMinutes;
                    const intervalsToSpan = Math.max(1, Math.ceil(duration / 30));
                    // Each row is compact (about 12-14px), calculate total height
                    const rowHeight = 13; // Compact row height to fit everything
                    const totalHeight = intervalsToSpan * rowHeight - 2;
                    
                    const overtimeClass = sched.isOvertime ? 'overtime-schedule' : '';
                    // Format exactly like calendar: Subject Code (bold), Instructor, Room, Time, Type
                    cellContent += `<div class="schedule-item ${overtimeClass}" style="height: ${totalHeight}px; background: ${sched.color}; border-left: 4px solid ${sched.isOvertime ? '#bd2130' : sched.color};">
                        <div class="subj-code-print"><strong>${escapeHtml(sched.subjCode)}</strong></div>
                        <div class="instructor-print">${escapeHtml(sched.instructor)}</div>
                        <div class="room-print">${escapeHtml(sched.room)}</div>
                        <div class="time-print">${escapeHtml(sched.time)}</div>
                        <div class="type-print">${escapeHtml(sched.type)}</div>
                    </div>`;
                });
            }
            
            tableRows += `<td class="day-cell">${cellContent}</td>`;
        });
        
        tableRows += '</tr>';
    });
    
    // Write the print document - matching template format
    console.log('About to write print document with Teacher Workload Form format');
    console.log('Template includes:', {
        hasFormTitle: true,
        hasControlInfo: true,
        hasFacultyInfo: true,
        hasAcademicInfo: true,
        instructorName: instructorName,
        instructorRank: instructorRank,
        campusName: campusName,
        semester: semester,
        schoolYear: schoolYear,
        summer: summer,
        currentDate: currentDate
    });
    
    printWindow.document.open('text/html', 'replace');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Class Schedule - Print</title>
            <style>
                @page {
                    margin: 0.3cm;
                    size: letter portrait;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Poppins', 'Arial', 'Helvetica', sans-serif;
                    font-size: 9pt;
                    line-height: 1.2;
                    color: #000;
                    background: #fff;
                    padding: 10px;
                }
                
                .print-header {
                    margin-bottom: 8px;
                }
                
                .header-top {
                    display: flex;
                    align-items: flex-start;
                    margin-bottom: 8px;
                }
                
                .logo-section {
                    margin-right: 15px;
                }
                
                .logo-section img {
                    height: 50px;
                    width: auto;
                }
                
                .university-info {
                    flex: 1;
                    text-align: center;
                }
                
                .republic-text {
                    font-size: 10pt;
                    color: #000;
                    margin-bottom: 3px;
                }
                
                .university-name {
                    font-size: 14pt;
                    font-weight: bold;
                    color: #800000;
                    margin-bottom: 3px;
                    text-transform: uppercase;
                }
                
                .campus-name {
                    font-size: 11pt;
                    color: #000;
                    margin-bottom: 3px;
                }
                
                .form-title-section {
                    display: flex !important;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin: 10px 0;
                    padding: 8px 0;
                    visibility: visible !important;
                }
                
                .form-title-left {
                    flex: 1;
                }
                
                .form-title-label {
                    font-size: 8pt;
                    font-weight: 600;
                    color: #000;
                    margin-bottom: 2px;
                }
                
                .form-title-value {
                    font-size: 10pt;
                    font-weight: bold;
                    color: #000;
                }
                
                .form-control-info {
                    margin-left: 20px;
                }
                
                .control-table {
                    border-collapse: collapse;
                    font-size: 7pt;
                }
                
                .control-table td {
                    padding: 2px 8px;
                    border: none;
                }
                
                .control-label {
                    font-weight: 600;
                    text-align: right;
                    padding-right: 5px;
                }
                
                .control-value {
                    text-align: left;
                    padding-left: 5px;
                }
                
                .faculty-academic-section {
                    display: flex !important;
                    justify-content: space-between;
                    margin: 12px 0;
                    padding: 8px 0;
                    border-top: 1px solid #ddd;
                    visibility: visible !important;
                }
                
                .faculty-info, .academic-info {
                    flex: 1;
                }
                
                .academic-info {
                    margin-left: 30px;
                }
                
                .info-row {
                    display: flex;
                    margin-bottom: 6px;
                    font-size: 8pt;
                }
                
                .info-label {
                    font-weight: 600;
                    min-width: 120px;
                    margin-right: 8px;
                }
                
                .info-value {
                    flex: 1;
                    border-bottom: 1px solid #000;
                    padding-bottom: 2px;
                }
                
                .form-fields {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 5px;
                }
                
                .header-field {
                    flex: 1;
                }
                
                .header-field label {
                    font-size: 7pt;
                    font-weight: 600;
                    display: block;
                    margin-bottom: 2px;
                    color: #000;
                }
                
                .header-field input {
                    border: none;
                    border-bottom: 2px solid #000;
                    font-size: 7pt;
                    padding: 1px 0;
                    width: 100%;
                    background: transparent;
                    outline: none;
                }
                
                /* Schedule Table Styles - EVSU-OCC Theme */
                .schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 2px solid #800000;
                    margin: 0 auto;
                    background: #fff;
                }
                
                .schedule-table th {
                    background: #800000;
                    color: #fff;
                    font-weight: bold;
                    padding: 4px 3px;
                    text-align: center;
                    border: 1px solid #660000;
                    font-size: 7pt;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                
                .schedule-table th.time-header {
                    background: #800000;
                    width: 70px;
                }
                
                .schedule-table td {
                    border: 1px solid #ccc;
                    padding: 1px;
                    vertical-align: top;
                    height: 10px;
                }
                
                .schedule-table td.time-cell {
                    background: #f5f5f5;
                    font-weight: 600;
                    text-align: center;
                    border-right: 2px solid #800000;
                    padding: 2px 3px;
                    width: 70px;
                    font-size: 7pt;
                }
                
                .time-main {
                    font-size: 7pt;
                    font-weight: bold;
                }
                
                .time-half {
                    font-size: 6pt;
                    color: #666;
                }
                
                .schedule-table td.day-cell {
                    background: #fff;
                    position: relative;
                    height: 10px;
                    vertical-align: top;
                    padding: 0;
                }
                
                .schedule-item {
                    border: none;
                    border-radius: 2px;
                    padding: 1px 2px;
                    margin: 0;
                    font-size: 5pt;
                    page-break-inside: avoid;
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    width: 100%;
                    box-sizing: border-box;
                    color: #fff;
                    overflow: hidden;
                }
                
                .schedule-item.overtime-schedule {
                    background: #dc3545 !important;
                    border-left: 2px solid #bd2130 !important;
                }
                
                .subj-code-print {
                    font-size: 6pt;
                    font-weight: 900;
                    margin-bottom: 0px;
                    color: #fff;
                }
                
                .instructor-print, .room-print, .time-print, .type-print {
                    font-size: 4pt;
                    line-height: 0.9;
                    color: #fff;
                    margin: 0;
                }
                
                .schedule-item strong {
                    font-weight: 900;
                }
                
                .print-footer {
                    margin-top: 10px;
                    padding-top: 8px;
                    border-top: 2px solid #800000;
                    text-align: center;
                    font-size: 7pt;
                    color: #666;
                }
                
                .print-footer p {
                    margin: 2px 0;
                }
                
                @media print {
                    body {
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
                        transform: scale(0.85);
                        transform-origin: top left;
                        width: 117.65%;
                    }
                    
                    .print-header {
                        page-break-after: avoid;
                    }
                    
                    .schedule-table {
                        page-break-inside: avoid;
                    }
                    
                    .schedule-item {
                        page-break-inside: avoid;
                    }
                    
                    * {
                        page-break-inside: avoid;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <div class="header-top">
                    <div class="logo-section">
                        <img src="${logoPath}" alt="EVSU Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="university-info">
                        <div class="republic-text">Republic of the Philippines</div>
                        <div class="university-name">EASTERN VISAYAS STATE UNIVERSITY</div>
                        <div class="campus-name">${escapeHtml(campusName.replace('EVSU ', ''))}</div>
                    </div>
                </div>
                
                <!-- Main Title: CLASS SCHEDULE -->
                <div style="text-align: center; margin: 15px 0 10px 0;">
                    <h2 style="color: #800000; font-size: 18pt; font-weight: bold; margin: 0; text-transform: uppercase;">CLASS SCHEDULE</h2>
                    </div>
                
                <!-- Form Title and Date Information -->
                <div class="form-title-section" style="display: flex !important; visibility: visible !important; justify-content: space-between;">
                    <div class="form-title-left">
                        <div class="form-title-label">Title of Form:</div>
                        <div class="form-title-value">Class Schedule</div>
                    </div>
                    <div class="form-control-info">
                        <table class="control-table">
                            <tr>
                                <td class="control-label">Date:</td>
                                <td class="control-value">${escapeHtml(currentDate)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Faculty and Academic Information -->
                <div class="faculty-academic-section" style="display: flex !important; visibility: visible !important;">
                    <div class="faculty-info">
                        <div class="info-row">
                            <span class="info-label">Faculty Member:</span>
                            <span class="info-value">${escapeHtml(instructorName || 'INSTRUCTOR NAME')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Academic Rank:</span>
                            <span class="info-value">${escapeHtml(instructorRank || '-')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">College/Campus:</span>
                            <span class="info-value">${escapeHtml(campusName || 'EVSU ORMOC CAMPUS')}</span>
                        </div>
                    </div>
                    <div class="academic-info">
                        <div class="info-row">
                            <span class="info-label">Semester:</span>
                            <span class="info-value">${escapeHtml(semester || '-')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">School Year:</span>
                            <span class="info-value">${escapeHtml(schoolYear || '-')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Summer:</span>
                            <span class="info-value">${escapeHtml(summer || '-')}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th class="time-header">TIME</th>
                        <th>MON</th>
                        <th>TUE</th>
                        <th>WED</th>
                        <th>THU</th>
                        <th>FRI</th>
                        <th>SAT</th>
                        <th>SUN</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
            
            <div class="print-footer">
                <p><strong>This is an official document generated by the EVSU-OCC Scheduling System</strong></p>
                <p>Eastern Visayas State University - Ormoc City Campus</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for content to load, then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
    
    // Fallback if onload doesn't fire
    setTimeout(() => {
        if (printWindow.document.readyState === 'complete') {
            printWindow.print();
        }
    }, 1000);
}

/**
 * Helper function to get the current week date range
 */
function getWeekRange(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
    const monday = new Date(d.setDate(diff));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    
    const formatDate = (date) => {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };
    
    return {
        startDate: formatDate(monday),
        endDate: formatDate(sunday)
    };
}

/**
 * Loads sections into the section filter dropdown based on selected School Year and Term
 */
function loadSectionFilter() {
    const sectionFilter = $('#sectionFilter');
    if (!sectionFilter.length) {
        console.warn('Section filter dropdown not found');
        return;
    }
    
    // Get current filter values
    const syFilter = $('#syFilter').val() || '';
    const termFilter = window.currentActiveTerm || '';
    
    // Build query string
    // Note: If syFilter is empty, API will automatically use active school year/semester
    const params = new URLSearchParams();
    if (syFilter) params.append('sy', syFilter);
    if (termFilter) params.append('term', termFilter);
    
    // Store current selection to restore it if still available
    const currentSelection = sectionFilter.val();
    
    // Set flag to prevent change events during reload
    window.isReloadingSectionFilter = true;
    
    // Show loading state
    sectionFilter.prop('disabled', true);
    sectionFilter.empty();
    sectionFilter.append('<option value="">Loading sections...</option>');
    
    fetch(`${getApiBasePath()}get_class_sections.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            sectionFilter.prop('disabled', false);
            sectionFilter.empty();
            sectionFilter.append('<option value="">All Sections</option>');
            
            if (data.success && data.data) {
                // Flatten the grouped data into a single array
                const allSections = [];
                Object.keys(data.data).forEach(yearLevel => {
                    allSections.push(...data.data[yearLevel]);
                });
                
                // Sort sections by year level and section number
                allSections.sort((a, b) => {
                    if (a.year_level !== b.year_level) {
                        return a.year_level - b.year_level;
                    }
                    return a.sec_num - b.sec_num;
                });
                
                // Populate dropdown
                allSections.forEach(section => {
                    // Use label if it matches display_name format, otherwise show both
                    // Normalize both to compare (remove spaces and hyphens for comparison)
                    const normalizedLabel = section.label.replace(/\s+/g, '').replace(/-/g, '');
                    const normalizedDisplay = section.display_name.replace(/\s+/g, '').replace(/-/g, '');
                    const optionText = (normalizedLabel === normalizedDisplay) 
                        ? section.label 
                        : `${section.label} (${section.display_name})`;
                    sectionFilter.append(`<option value="${section.sec_id}">${optionText}</option>`);
                });
                
                // Restore previous selection if it still exists
                if (currentSelection && allSections.some(s => s.sec_id == currentSelection)) {
                    sectionFilter.val(currentSelection);
                    // Manually trigger unassigned subjects load since change event might not fire
                    // when value is set programmatically
                    const filters = {
                        sy: syFilter,
                        term: termFilter,
                        program: $('#programFilter').val() || '',
                        year_level: $('#yearLevelFilter').val() || '',
                        section: currentSelection
                    };
                    loadUnassignedSubjects(filters);
                } else {
                    // Clear selection if previous selection is no longer available
                    sectionFilter.val('');
                    // Only hide unassigned subjects if we're not in the middle of a class button click
                    // (class button clicks will handle showing unassigned subjects)
                    if (!window.isClassButtonClickInProgress) {
                        hideUnassignedSubjects();
                    }
                }
                
                // Clear reload flag
                window.isReloadingSectionFilter = false;
                
                console.log(`Loaded ${allSections.length} sections for SY: ${syFilter}, Term: ${termFilter || 'All'}`);
            } else {
                console.warn('No sections found for selected filters');
                // Only hide unassigned subjects if we're not in the middle of a class button click
                if (!window.isClassButtonClickInProgress) {
                    hideUnassignedSubjects();
                }
                window.isReloadingSectionFilter = false;
            }
        })
        .catch(error => {
            console.error('Error loading sections for filter:', error);
            sectionFilter.prop('disabled', false);
            sectionFilter.empty();
            sectionFilter.append('<option value="">All Sections</option>');
            // Only hide unassigned subjects if we're not in the middle of a class button click
            if (!window.isClassButtonClickInProgress) {
                hideUnassignedSubjects();
            }
            window.isReloadingSectionFilter = false;
        });
}

/**
 * Loads class sections and renders them as buttons in the class selector bar
 */
function loadClassSelector() {
    const container = document.getElementById('classSelectorContainer');
    const buttonsContainer = document.getElementById('classButtonsContainer');
    
    if (!container || !buttonsContainer) {
        console.warn('Class selector container not found');
        return;
    }
    
    // Check if user is instructor (filters are hidden for instructors)
    const syFilterEl = document.getElementById('syFilter');
    const isInstructor = !syFilterEl || syFilterEl.offsetParent === null;
    
    // For instructors, always show container (they need the print button)
    if (isInstructor) {
        container.style.display = 'block';
        container.style.visibility = 'visible';
        // Load class sections for instructor (no filters needed)
        buttonsContainer.innerHTML = '<span class="text-white">Loading classes...</span>';
        fetch(`${getApiBasePath()}get_class_sections.php`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    if (typeof renderClassButtons === 'function') {
                        renderClassButtons(data.data);
                    } else {
                        buttonsContainer.innerHTML = '<span class="text-white">No classes available</span>';
                    }
                } else {
                    buttonsContainer.innerHTML = '<span class="text-white">No classes available</span>';
                    // Keep container visible even if no classes (print button needed)
                    container.style.display = 'block';
                    container.style.visibility = 'visible';
                }
            })
            .catch(error => {
                console.error('Error loading class sections:', error);
                buttonsContainer.innerHTML = '<span class="text-white">Error loading classes</span>';
                // Keep container visible even on error (print button needed)
                container.style.display = 'block';
                container.style.visibility = 'visible';
            });
        return;
    }
    
    // Get current filter values (for admin side)
    const syFilter = $('#syFilter').val() || '';
    const programFilter = $('#programFilter').val() || '';
    
    // Always show container - API will use active school year/semester if filters are empty
    container.style.display = 'block';
    
    // Build query string
    // Note: If syFilter is empty, API will automatically use active school year/semester
    const params = new URLSearchParams();
    if (syFilter) params.append('sy', syFilter);
    if (programFilter) params.append('program', programFilter);
    
    // Show loading state
    buttonsContainer.innerHTML = '<span class="text-white">Loading classes...</span>';
    
    fetch(`${getApiBasePath()}get_class_sections.php?${params.toString()}`)
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
                const programFilter = $('#programFilter').val();
                console.log(`Loaded ${totalSections} class sections`, {
                    programFilter: programFilter || 'None',
                    filters: data.filters_applied
                });
                renderClassButtons(data.data);
                // Set active class if section filter is already set
                const selectedSection = $('#sectionFilter').val();
                if (selectedSection) {
                    const activeButton = document.querySelector(`.class-btn[data-sec-id="${selectedSection}"]`);
                    if (activeButton) {
                        activeButton.classList.add('active');
                        const chip = activeButton.closest('.class-chip');
                        if (chip) chip.classList.add('active');
                    }
                }
            } else {
                console.error('Failed to load class sections:', data.message || 'Unknown error');
                buttonsContainer.innerHTML = '<span class="text-white">No classes available</span>';
            }
        })
        .catch(error => {
            console.error('Error loading class sections:', error);
            buttonsContainer.innerHTML = '<span class="text-white">Error loading classes. Check console for details.</span>';
        });
}

/**
 * Renders class buttons from grouped data
 * @param {Object} groupedData - Data grouped by year level
 */
function renderClassButtons(groupedData) {
    const buttonsContainer = document.getElementById('classButtonsContainer');
    const container = document.getElementById('classSelectorContainer');
    if (!buttonsContainer) return;
    
    // Check if user is instructor
    const syFilterEl = document.getElementById('syFilter');
    const isInstructor = !syFilterEl || syFilterEl.offsetParent === null;
    
    buttonsContainer.innerHTML = '';
    
    // Sort year levels
    const yearLevels = Object.keys(groupedData).sort((a, b) => parseInt(a) - parseInt(b));
    
    if (yearLevels.length === 0) {
        buttonsContainer.innerHTML = '<span class="text-white">No classes available</span>';
        // For instructors, keep container visible even if no classes (they need print button)
        if (isInstructor && container) {
            container.style.display = 'block';
            container.style.visibility = 'visible';
            container.classList.remove('d-none');
        }
        return;
    }
    
    // For instructors, ensure container is visible
    if (isInstructor && container) {
        container.style.display = 'block';
        container.style.visibility = 'visible';
        container.classList.remove('d-none');
    }
    
    // Track seen sections to prevent duplicates (by label)
    const seenSections = new Set();
    
    // Create buttons for each class section
    yearLevels.forEach(yearLevel => {
        const sections = groupedData[yearLevel];
        sections.forEach(section => {
            // Create unique key for deduplication (label + year level)
            const uniqueKey = `${section.label}_${section.year_level}`;
            
            // Skip if we've already seen this section
            if (seenSections.has(uniqueKey)) {
                console.warn('Skipping duplicate section:', section.label, 'sec_id:', section.sec_id);
                return;
            }
            
            seenSections.add(uniqueKey);
            
            // Create pill "chip" with integrated close button
            const chip = document.createElement('div');
            chip.className = 'class-chip';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'class-chip__label class-btn';
            button.textContent = section.label;
            button.setAttribute('data-sec-id', section.sec_id);
            button.setAttribute('data-year-level', section.year_level);
            button.setAttribute('data-sec-name', section.display_name);
            button.setAttribute('title', `Section: ${section.display_name} - Click to view schedules`);

            // Add click handler
            button.addEventListener('click', function() {
                handleClassButtonClick(this, section);
            });

            chip.appendChild(button);
            
            // Create close button (only for non-instructors)
            if (!isInstructor) {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'class-chip__close section-delete-btn';
                deleteBtn.innerHTML = '<i class="bi bi-x"></i>';
                deleteBtn.setAttribute('data-sec-id', section.sec_id);
                deleteBtn.setAttribute('data-sec-name', section.display_name);
                deleteBtn.setAttribute('title', `Remove section: ${section.display_name}`);
                deleteBtn.setAttribute('aria-label', `Remove section: ${section.display_name}`);
                deleteBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    deleteSectionFromSchedule(this);
                });
                chip.appendChild(deleteBtn);
            }
            
            buttonsContainer.appendChild(chip);
        });
    });
    
    // Container is always visible, no need to show/hide
}

/**
 * Deletes a section from the schedule page
 * @param {HTMLElement} deleteBtn - The delete button element
 */
function deleteSectionFromSchedule(deleteBtn) {
    const secId = deleteBtn.getAttribute('data-sec-id');
    const secName = deleteBtn.getAttribute('data-sec-name');
    
    if (!secId) {
        console.error('Section ID not found');
        return;
    }
    
    // Show confirmation dialog
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Delete Section?',
            html: `Are you sure you want to delete section <strong>${secName}</strong>?<br><br>
                   <small class="text-muted">This will only delete the section if it has no associated schedules.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Call delete API
                const formData = new FormData();
                formData.append('sec_id', secId);
                
                fetch('../../admin/management/delete_section.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Immediately remove the section button from DOM (optimistic update)
                        const buttonWrapper = deleteBtn.closest('.d-inline-flex');
                        if (buttonWrapper) {
                            // Add fade-out animation
                            buttonWrapper.style.transition = 'opacity 0.3s ease';
                            buttonWrapper.style.opacity = '0';
                            setTimeout(() => {
                                buttonWrapper.remove();
                            }, 300);
                        }
                        
                        // Also remove from section filter dropdown if it's selected
                        const sectionFilter = $('#sectionFilter');
                        if (sectionFilter.val() == secId) {
                            sectionFilter.val('').trigger('change');
                        }
                        
                        // Reload class selector to ensure consistency
                        if (typeof loadClassSelector === 'function') {
                            loadClassSelector();
                        }
                        
                        // Reload schedules if needed
                        if (typeof loadSchedules === 'function') {
                            loadSchedules();
                        }
                        
                        // Show success message
                        Swal.fire({
                            title: 'Deleted!',
                            text: data.message || 'Section deleted successfully.',
                            icon: 'success',
                            confirmButtonColor: '#28a745',
                            timer: 2000,
                            showConfirmButton: true
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to delete section.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error deleting section:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while deleting the section.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    } else {
        // Fallback if SweetAlert is not available
        if (confirm(`Are you sure you want to delete section "${secName}"?`)) {
            const formData = new FormData();
            formData.append('sec_id', secId);
            
            fetch('../../admin/management/delete_section.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Immediately remove the section button from DOM (optimistic update)
                    const buttonWrapper = deleteBtn.closest('.d-inline-flex');
                    if (buttonWrapper) {
                        // Add fade-out animation
                        buttonWrapper.style.transition = 'opacity 0.3s ease';
                        buttonWrapper.style.opacity = '0';
                        setTimeout(() => {
                            buttonWrapper.remove();
                        }, 300);
                    }
                    
                    // Also remove from section filter dropdown if it's selected
                    const sectionFilter = $('#sectionFilter');
                    if (sectionFilter.val() == secId) {
                        sectionFilter.val('').trigger('change');
                    }
                    
                    // Reload class selector to ensure consistency
                    if (typeof loadClassSelector === 'function') {
                        loadClassSelector();
                    }
                    
                    // Reload schedules if needed
                    if (typeof loadSchedules === 'function') {
                        loadSchedules();
                    }
                    
                    alert(data.message || 'Section deleted successfully.');
                } else {
                    alert(data.message || 'Failed to delete section.');
                }
            })
            .catch(error => {
                console.error('Error deleting section:', error);
                alert('An error occurred while deleting the section.');
            });
        }
    }
}

/**
 * Handles class button click - filters schedules by section and year level
 * @param {HTMLElement} button - The clicked button
 * @param {Object} section - Section data
 */
function handleClassButtonClick(button, section) {
    // Remove active class from all buttons in both containers
    document.querySelectorAll('.class-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Remove active class from all chips
    document.querySelectorAll('.class-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    
    // Also remove active from unassigned subject items if they exist
    document.querySelectorAll('.unassigned-subject-item').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    button.classList.add('active');
    const activeChip = button.closest('.class-chip');
    if (activeChip) activeChip.classList.add('active');
    
    const instructorView = isInstructorScheduleView();
    
    // Set flag to prevent unwanted hiding of unassigned subjects during filter changes (scheduling UI only)
    if (!instructorView) {
        window.isClassButtonClickInProgress = true;
    }
    
    // Clear sessionStorage filters to prevent conflicts when explicitly selecting a section
    // The section's year level should take priority over stored modal filters
    sessionStorage.removeItem('scheduleFilterYearLevel');
    sessionStorage.removeItem('scheduleFilterProgram');
    
    // Set filter values programmatically (prevent change events)
    isSettingFiltersProgrammatically = true;
    $('#yearLevelFilter').val(section.year_level);
    $('#sectionFilter').val(section.sec_id);
    isSettingFiltersProgrammatically = false;
    
    // Build filters object for unassigned subjects
    const filters = {
        sy: $('#syFilter').val() || '',
        term: window.currentActiveTerm || '',
        program: $('#programFilter').val() || '',
        year_level: section.year_level,
        section: section.sec_id
    };
    
    console.log('Class button clicked for section:', section.sec_id, 'Filters:', filters);
    console.log('Cleared sessionStorage filters to match section year level:', section.year_level);
    
    if (!instructorView) {
        // Load unassigned subjects immediately (before loadSchedules completes)
        loadUnassignedSubjects(filters);
        // Store the section ID in a variable that won't be cleared by loadSchedules
        window.currentSelectedSectionId = section.sec_id;
    }
    
    // Reload schedules with new filters (now sessionStorage is cleared, so dropdown value will be used)
    loadSchedules();
    
    if (instructorView) {
        return;
    }
    
    // Ensure unassigned subjects are still shown after loadSchedules completes
    // Use a longer timeout (2 seconds) to ensure loadSchedules has finished all its async operations
    setTimeout(() => {
        const currentSection = $('#sectionFilter').val();
        const expectedSection = String(window.currentSelectedSectionId);
        
        // Re-set section filter if it was cleared (shouldn't happen, but just in case)
        if (currentSection !== expectedSection) {
            console.warn('Section filter was cleared! Re-setting it. Current:', currentSection, 'Expected:', expectedSection);
            $('#sectionFilter').val(expectedSection);
        }
        
        // Reload unassigned subjects to ensure they're displayed
        console.log('Ensuring unassigned subjects are displayed after loadSchedules...');
        loadUnassignedSubjects(filters);
        
        // Clear flag after everything is done
        window.isClassButtonClickInProgress = false;
        delete window.currentSelectedSectionId;
        console.log('Class button click processing complete');
    }, 2000);
}

/**
 * Opens schedule modal with section details auto-filled
 * This function can be called when user wants to create a schedule for a specific section
 */
function openScheduleModalWithSection(sectionId) {
    // Reset form and set to create mode
    $('#scheduleForm')[0].reset();
    $('#schd_id').val('');
    $('#scheduleModalLabel').text('Create Schedule');
    $('#schd_status').closest('.col-md-6').hide();
    $('#removeScheduleBtn').hide();
    $('#saveScheduleBtn').text('Save Schedule');
    
    // Clear stored edit schedule data
    window.editScheduleData = null;
    
    // Clear schedule-specific fields (instructor, room, type, day, times)
    $('#inst_id').val('');
    $('#rm_id').val('');
    $('#schd_type').val('Lec'); // Reset to default
    // Uncheck all day checkboxes
    $('input[name="schd_day[]"]').prop('checked', false);
    $('#schd_start').val('');
    $('#schd_end').val('');
    
    // Fetch section details
    fetch(`${getApiBasePath()}get_section_details.php?section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const section = data.data;
                
                // Open the modal
                const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                modal.show();
                
                // Wait for modal to be shown, then populate fields
                $('#scheduleModal').one('shown.bs.modal', function() {
                    // Set up cascading dropdowns
                    setupCascadingDropdowns();
                    
                    // Set school year first, then trigger cascade
                    if (section.sy_id) {
                        $('#sy_id').val(section.sy_id);
                        $('#sy_id').trigger('change.cascade');
                    }
                    
                    // Wait for terms to load, then set term
                    setTimeout(() => {
                        if (section.class_term) {
                            $('#schd_term').val(section.class_term);
                            $('#schd_term').trigger('change.cascade');
                        }
                        
                        // Wait for term to process, then set program
                        setTimeout(() => {
                            if (section.program_id) {
                                $('#program_id').val(section.program_id);
                                $('#program_id').trigger('change.cascade');
                                
                                // Wait for program to process, then set year level
                                setTimeout(() => {
                                    if (section.year_level) {
                                        $('#year_level').val(section.year_level);
                                        $('#year_level').trigger('change.cascade');
                                        
                                        // Wait for year level to process, then set section
                                        setTimeout(() => {
                                            if (section.sec_id) {
                                                $('#sec_id').val(section.sec_id);
                                            }
                                        }, 400);
                                    }
                                }, 400);
                            }
                        }, 400);
                    }, 400);
                });
            } else {
                showToast('error', data.message || 'Failed to load section details');
            }
        })
        .catch(error => {
            console.error('Error loading section details:', error);
            showToast('error', 'An error occurred while loading section details');
        });
}

/**
 * Clears the class filter
 */
function clearClassFilter() {
    // Remove active class from all buttons
    document.querySelectorAll('.class-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Remove active class from all chips
    document.querySelectorAll('.class-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    
    // Clear year level and section filters
    $('#yearLevelFilter').val('');
    $('#sectionFilter').val('');
    
    // Reload schedules
    loadSchedules();
}

// Store programs data globally for preview
let programsData = [];

/**
 * Loads data for the Auto Section Maker Modal
 */
function loadAutoSectionMakerData() {
    // First, get the current active school year and semester
    const currentSyPromise = fetch('../../shared/management/get_current_sy_semester.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                return {
                    sy_id: data.data.sy_id,
                    semester: data.data.semester
                };
            }
            return null;
        })
        .catch(error => {
            console.error('Error fetching current SY & Semester:', error);
            return null;
        });
    
    // Then load the schedule form data
    loadScheduleFormData().then(data => {
        if (data.success) {
            // Store programs data for preview function
            programsData = data.programs || [];
            
            // Populate School Year dropdown
            populateDropdown('#autoSyId', data.school_years, 'sy_id', 'sy_display', 'Select School Year');
            
            // Populate Program dropdown with data attributes for program code
            const programSelect = document.getElementById('autoProgramId');
            if (programSelect && data.programs) {
                programSelect.innerHTML = '<option value="">Select Program</option>';
                data.programs.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program.program_id;
                    option.textContent = program.program_display || `${program.program_code} - ${program.program_name}`;
                    option.setAttribute('data-program-code', program.program_code || 'CLASS');
                    programSelect.appendChild(option);
                });
            }
            
            // Wait for current SY data, then set defaults
            currentSyPromise.then(currentSy => {
                let syIdToSelect = null;
                let termToSelect = null;
                
                // Priority 1: Use current active SY & Semester if available
                if (currentSy && currentSy.sy_id) {
                    syIdToSelect = currentSy.sy_id;
                    
                    // Map semester name to term value
                    // Semester values from API: "1st Semester", "2nd Semester", "Mid-Year"
                    // Term dropdown values: "1" (1st Term), "2" (2nd Term), "3" (Summer)
                    const semester = (currentSy.semester || '').toLowerCase().trim();
                    if (semester.includes('1st') || semester.includes('first')) {
                        termToSelect = '1'; // 1st Term
                    } else if (semester.includes('2nd') || semester.includes('second')) {
                        termToSelect = '2'; // 2nd Term
                    } else if (semester.includes('mid-year') || semester.includes('midyear') || semester.includes('summer')) {
                        termToSelect = '3'; // Summer
                    }
                    
                    console.log('Auto-selecting current SY & Semester:', {
                        sy_id: syIdToSelect,
                        semester: currentSy.semester,
                        term: termToSelect
                    });
                }
                
                // Priority 2: Use filter values if current SY not available
                const syFilter = $('#syFilter').val();
                const programFilter = $('#programFilter').val();
                
                if (!syIdToSelect && syFilter) {
                    syIdToSelect = syFilter;
                }
                // Term is now automatically determined from active semester settings
                
                // Set School Year dropdown - wait a bit to ensure dropdown is populated
                if (syIdToSelect) {
                    // Check if the option exists in the dropdown
                    const sySelect = document.getElementById('autoSyId');
                    if (sySelect) {
                        const optionExists = Array.from(sySelect.options).some(opt => opt.value == syIdToSelect);
                        if (optionExists) {
                            $('#autoSyId').val(syIdToSelect);
                            console.log('Set School Year to:', syIdToSelect);
                        } else {
                            console.warn('School Year ID', syIdToSelect, 'not found in dropdown options');
                        }
                    }
                }
                
                // Set Term dropdown
                if (termToSelect) {
                    $('#autoTerm').val(termToSelect);
                    console.log('Set Term to:', termToSelect);
                }
                
                // Set Program filter if available
                if (programFilter) {
                    $('#autoProgramId').val(programFilter);
                }
                
                // Update preview after a short delay to ensure dropdowns are set
                setTimeout(() => {
                    updateSectionPreview();
                }, 100);
            });
        }
    }).catch(error => {
        console.error('Error loading auto section maker data:', error);
    });
}

/**
 * Updates the section preview based on current form values
 */
function updateSectionPreview() {
    try {
        // Only run if the modal is actually open
        const modal = document.getElementById('autoSectionMakerModal');
        if (!modal || !modal.classList.contains('show')) {
            return;
        }
        
        const yearLevel = $('#autoYearLevel').val();
        const numSections = parseInt($('#autoNumSections').val()) || 0;
        const programId = $('#autoProgramId').val();
        const syId = $('#autoSyId').val();
        const term = $('#autoTerm').val();
        const previewDiv = document.getElementById('sectionPreview');
        
        if (!previewDiv) return;
        
        if (!yearLevel || numSections < 1 || !programId || !syId || !term) {
            previewDiv.innerHTML = 'Select the fields above to see a preview of sections that will be created.';
            previewDiv.className = 'text-muted';
            return;
        }
        
        // Get program code from selected program
        let programCode = 'CLASS'; // Default fallback
        if (programId) {
            const programSelect = document.getElementById('autoProgramId');
            const selectedOption = programSelect.options[programSelect.selectedIndex];
            if (selectedOption) {
                // Get program code from data attribute (more reliable)
                const codeFromAttr = selectedOption.getAttribute('data-program-code');
                if (codeFromAttr) {
                    programCode = codeFromAttr;
                } else if (selectedOption.text) {
                    // Fallback: Extract program code from display text (format: "BEED - BACHELOR OF...")
                    const displayText = selectedOption.text;
                    const match = displayText.match(/^([A-Z0-9-]+)\s*-/);
                    if (match && match[1]) {
                        programCode = match[1].trim();
                    }
                }
            }
        }
        
        // Check for existing sections to determine starting point
        // Fetch existing sections for this program/year level/school year/term
        const checkUrl = `${getApiBasePath()}get_class_sections.php?program=${programId}&sy=${syId}&term=${term}`;
        
        fetch(checkUrl)
            .then(response => response.json())
            .then(data => {
                let startSecNum = 1;
                let existingSections = [];
                
                if (data.success && data.data) {
                    // Data is grouped by year level, so get sections for the selected year level
                    // Convert yearLevel to integer for matching (API returns integer keys)
                    const yearLevelInt = parseInt(yearLevel);
                    const yearLevelSections = data.data[yearLevelInt] || data.data[yearLevel] || [];
                    existingSections = yearLevelSections;
                    
                    // Find the highest sec_num
                    if (yearLevelSections.length > 0) {
                        const secNums = yearLevelSections.map(s => parseInt(s.sec_num) || 0).filter(n => n > 0);
                        if (secNums.length > 0) {
                            const maxSecNum = Math.max(...secNums);
                            if (maxSecNum > 0) {
                                startSecNum = maxSecNum + 1;
                            }
                        }
                    }
                }
                
                // Generate preview sections starting from startSecNum
                // Only show sections that will be NEWLY created (from startSecNum to startSecNum + numSections - 1)
                const sections = [];
                const sectionLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
                
                // Get existing section names to avoid showing duplicates
                const existingSectionNames = existingSections.map(s => s.label || s.display_name || '');
                
                for (let i = 0; i < numSections; i++) {
                    const secNum = startSecNum + i;
                    let letter;
                    
                    // Calculate section letter based on sec_num
                    if (secNum <= 26) {
                        letter = sectionLetters[secNum - 1];
                    } else {
                        // For numbers > 26, use double letters
                        const firstIndex = Math.floor((secNum - 1) / 26) - 1;
                        const secondIndex = ((secNum - 1) % 26);
                        letter = sectionLetters[firstIndex] + sectionLetters[secondIndex];
                    }
                    
                    const sectionName = `${programCode} ${yearLevel}-${letter}`;
                    
                    // Only add to preview if it doesn't already exist
                    if (!existingSectionNames.includes(sectionName)) {
                        sections.push(`<span class="badge me-1 mb-1" style="background-color: #800000; color: white;">${sectionName}</span>`);
                    }
                }
                
                if (sections.length > 0) {
                    let previewText = `<div class="mb-2"><strong>New sections to be created:</strong></div>${sections.join('')}`;
                    if (existingSections.length > 0) {
                        previewText += `<div class="mt-2 small text-muted"><i class="bi bi-info-circle me-1"></i>Continuing from existing sections. ${existingSections.length} section(s) already exist (${existingSections.map(s => s.label || s.display_name || '').join(', ')}).</div>`;
                    }
                    previewDiv.innerHTML = previewText;
                    previewDiv.className = '';
                } else {
                    // All requested sections already exist
                    previewDiv.innerHTML = `<div class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>All ${numSections} requested section(s) already exist. No new sections will be created.</div>`;
                    previewDiv.className = '';
                }
            })
            .catch(error => {
                console.error('Error checking existing sections:', error);
                // Fallback: show preview without checking existing sections
                const sectionLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
                const sections = [];
                
                for (let i = 0; i < numSections && i < 26; i++) {
                    const letter = sectionLetters[i];
                    sections.push(`<span class="badge me-1 mb-1" style="background-color: #800000; color: white;">${programCode} ${yearLevel}-${letter}</span>`);
                }
                
                if (sections.length > 0) {
                    previewDiv.innerHTML = `<div class="mb-2"><strong>Sections to be created:</strong></div>${sections.join('')}`;
                    previewDiv.className = '';
                } else {
                    previewDiv.innerHTML = 'Invalid number of sections.';
                    previewDiv.className = 'text-danger';
                }
            });
    } catch (error) {
        console.error('Error in updateSectionPreview:', error);
        // Don't break the page if preview fails
        const previewDiv = document.getElementById('sectionPreview');
        if (previewDiv) {
            previewDiv.innerHTML = 'Select the fields above to see a preview of sections that will be created.';
            previewDiv.className = 'text-muted';
        }
    }
}

/**
 * Generates sections using the auto section maker
 */
function generateSections() {
    const form = document.getElementById('autoSectionMakerForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const btn = document.getElementById('generateSectionsBtn');
    const originalText = btn.innerHTML;
    
    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    
    fetch(`${getApiBasePath()}auto_generate_sections.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Auto Section Maker - Response Status:', response.status);
        return response.json();
    })
    .then(data => {
        // DEBUG: Log the full response
        console.log('Auto Section Maker - Full Response:', JSON.stringify(data, null, 2));
        console.log('Auto Section Maker - Success:', data.success);
        console.log('Auto Section Maker - Message:', data.message);
        if (data.created) {
            console.log('Auto Section Maker - Created Sections:', data.created);
        }
        if (data.skipped) {
            console.log('Auto Section Maker - Skipped Sections:', data.skipped);
        }
        if (data.debug) {
            console.log('Auto Section Maker - DEBUG INFO:', JSON.stringify(data.debug, null, 2));
            console.log('Auto Section Maker - Input Params:', data.debug.input_params);
            console.log('Auto Section Maker - Class ID:', data.debug.class_id);
            console.log('Auto Section Maker - Existing Sections Found:', data.debug.existing_sections_found);
            console.log('Auto Section Maker - Existing Section Names:', data.debug.existing_section_names);
            console.log('Auto Section Maker - Sections To Create:', data.debug.sections_to_create);
        }
        
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            showToast('success', data.message || `Successfully generated ${data.created.length} section(s).`);
            
            // Close modal and clean up backdrop
            const modalEl = document.getElementById('autoSectionMakerModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            
            if (modalInstance) {
                // Add event listener to clean up backdrop and restore page state
                modalEl.addEventListener('hidden.bs.modal', function handler() {
                    cleanupModalBackdrop();
                    // Remove the listener to prevent it from running multiple times
                    modalEl.removeEventListener('hidden.bs.modal', handler);
                });
                
                modalInstance.hide();
            } else {
                // If modal instance doesn't exist, clean up immediately
                cleanupModalBackdrop();
            }
            
            // Reset form
            form.reset();
            
            // Reload class selector to show new sections
            setTimeout(() => {
                loadClassSelector();
            }, 500);
        } else {
            console.error('Auto Section Maker - Error Response:', data);
            showToast('error', data.message || 'Failed to generate sections.');
        }
    })
    .catch(error => {
        console.error('Auto Section Maker - Fetch Error:', error);
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('error', 'An error occurred while generating sections.');
    });
}

/**
 * Renders the Class Time Load summary table below the schedule grid
 * @param {Array} schedules - The array of schedule objects
 */
function renderOvertimeTable(overtimeSchedules, allSchedules) {
    const tbody = document.getElementById('overtimeTableBody');
    if (!tbody) return;
    
    // Clear existing rows
    tbody.innerHTML = '';
    
    // Schedules are already filtered for overtime before calling this function
    let overtime = overtimeSchedules || [];
    const all = allSchedules || [];
    
    console.log('renderOvertimeTable - Input:', {
        overtimeCount: overtime.length,
        allSchedulesCount: all.length,
        windowAllSchedulesDataCount: window.allSchedulesData ? window.allSchedulesData.length : 0
    });
    
    // If no overtime schedules passed, but we should still check if there's overtime based on calculations
    // First, let's check if any schedules have is_overtime = 'Yes' in the data
    if (!overtime || overtime.length === 0) {
        // Check if there are any schedules with is_overtime flag in all schedules
        const allWithOvertimeFlag = (window.allSchedulesData || all || []).filter(s => s.is_overtime === 'Yes' || s.is_overtime === true);
        console.log('No overtime schedules passed, but found in data:', allWithOvertimeFlag.length);
        
        if (allWithOvertimeFlag.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-muted py-4">
                    <i class="bi bi-check-circle me-2"></i>No overtime schedules found.
                </td>
            </tr>
        `;
        return;
        }
        // If we found schedules with overtime flag, use those instead
        overtime = allWithOvertimeFlag;
    }
    
    // Calculate total assigned hours per instructor from ALL schedules (not just filtered ones)
    // Use window.allSchedulesData if available, otherwise use the 'all' parameter
    const allSchedulesForCalc = window.allSchedulesData && window.allSchedulesData.length > 0 
        ? window.allSchedulesData 
        : all; // Fallback to passed 'all' parameter
    
    const instructorAssignedHours = {};
    allSchedulesForCalc.forEach(schedule => {
        const instId = schedule.inst_id;
        if (!instructorAssignedHours[instId]) {
            // Use total_hours from schedule (which now correctly uses instruction_hours for Part-Time/Contractual)
            // Fallback to instruction_hours if available, then policy_total_hours, then default 40
            const totalHours = schedule.total_hours || 
                              (schedule.instruction_hours && (schedule.inst_status === 'Part-Time' || schedule.inst_status === 'Contractual') ? schedule.instruction_hours : null) ||
                              schedule.policy_total_hours || 
                              40;
            instructorAssignedHours[instId] = {
                totalMinutes: 0,
                totalHours: totalHours
            };
        }
        // Add schedule minutes (schd_min is in minutes)
        instructorAssignedHours[instId].totalMinutes += parseFloat(schedule.schd_min || 0);
    });
    
    // Calculate overtime hours for each instructor
    // Overtime = assigned hours - total hours (if assigned > total)
    const instructorOvertime = {};
    Object.keys(instructorAssignedHours).forEach(instId => {
        const inst = instructorAssignedHours[instId];
        const assignedHours = inst.totalMinutes / 60; // Convert minutes to hours
        const overtimeHours = Math.max(0, assignedHours - inst.totalHours); // Excess hours only
        instructorOvertime[instId] = {
            assigned: assignedHours,
            total: inst.totalHours,
            overtime: overtimeHours
        };
    });
    
    // Debug logging
    console.log('renderOvertimeTable - Calculation:', {
        overtimeSchedulesCount: overtime.length,
        allSchedulesCount: allSchedulesForCalc.length,
        instructorAssignedHours: instructorAssignedHours,
        instructorOvertime: instructorOvertime
    });
    
    // If no schedules with is_overtime flag, but we calculated overtime, include those schedules
    // This handles cases where the database field might not be set correctly
    let validOvertimeSchedules = overtime;
    
    // Also check if there are schedules that should be overtime based on calculation
    if (validOvertimeSchedules.length === 0) {
        // Find schedules for instructors who have calculated overtime
        const calculatedOvertimeSchedules = allSchedulesForCalc.filter(schedule => {
        const instId = schedule.inst_id;
        const overtimeInfo = instructorOvertime[instId];
        return overtimeInfo && overtimeInfo.overtime > 0;
    });
        
        if (calculatedOvertimeSchedules.length > 0) {
            console.log('Found', calculatedOvertimeSchedules.length, 'schedules with calculated overtime (database field may be outdated)');
            validOvertimeSchedules = calculatedOvertimeSchedules;
        }
    } else {
        // Filter out schedules where the instructor no longer has overtime (overtime <= 0)
        validOvertimeSchedules = overtime.filter(schedule => {
            const instId = schedule.inst_id;
            const overtimeInfo = instructorOvertime[instId];
            const isValid = overtimeInfo && overtimeInfo.overtime > 0;
            if (!isValid) {
                console.log('Filtering out schedule (no overtime):', {
                    schedule_id: schedule.schd_id,
                    inst_id: instId,
                    overtimeInfo: overtimeInfo
                });
            }
            return isValid;
        });
    }
    
    console.log('Valid overtime schedules after filtering/calculation:', validOvertimeSchedules.length);
    
    // If no valid overtime schedules after filtering, show empty message
    if (!validOvertimeSchedules || validOvertimeSchedules.length === 0) {
        console.log('No valid overtime schedules - showing empty message');
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-muted py-4">
                    <i class="bi bi-check-circle me-2"></i>No overtime schedules found.
                </td>
            </tr>
        `;
        return;
    }
    
    // Sort schedules by subject code, then by day, then by time
    // Group schedules by subject code and section
    const groupedSchedules = {};
    validOvertimeSchedules.forEach(schedule => {
        const groupKey = `${schedule.subj_code}_${schedule.sec_name || 'NONE'}_${schedule.inst_id}`;
        if (!groupedSchedules[groupKey]) {
            groupedSchedules[groupKey] = [];
        }
        groupedSchedules[groupKey].push(schedule);
    });
    
    // Sort groups by subject code, then by section
    const sortedGroups = Object.keys(groupedSchedules).sort((a, b) => {
        const [subjA, secA] = a.split('_');
        const [subjB, secB] = b.split('_');
        if (subjA !== subjB) {
            return subjA.localeCompare(subjB);
        }
        return (secA || '').localeCompare(secB || '');
    });
    
    // Render each grouped schedule as a table row
    sortedGroups.forEach(groupKey => {
        const groupSchedules = groupedSchedules[groupKey];
        // Sort schedules within group by day and time
        const dayOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        groupSchedules.sort((a, b) => {
            const dayDiff = dayOrder.indexOf(a.schd_day) - dayOrder.indexOf(b.schd_day);
            if (dayDiff !== 0) return dayDiff;
            return a.schd_start.localeCompare(b.schd_start);
        });
        
        // Use first schedule for common fields
        const firstSchedule = groupSchedules[0];
        
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        // Use first schedule's ID for edit (or could use a comma-separated list)
        row.onclick = () => editSchedule(firstSchedule.schd_id);
        row.title = 'Click to edit schedule';
        
        // Get Lec and Lab from subject (not summed across schedules)
        // Use the subject's total Lec/Lab hours, not per-schedule hours
        const lecHours = firstSchedule.subj_lec || 0;
        const labHours = firstSchedule.subj_lab || 0;
        const units = firstSchedule.subj_unit || 0;
        
        // Combine day and time displays - format: "Mon 9:00AM-10:30AM, Wed 9:00AM-10:30AM"
        const dayTimePairs = groupSchedules.map(s => {
            const timeDisplay = `${s.start_time} - ${s.end_time}`;
            return `${s.schd_day} ${timeDisplay}`;
        });
        const dayTimeDisplay = dayTimePairs.join(', ');
        
        // Combine day displays (just days)
        const dayDisplay = groupSchedules.map(s => s.schd_day).join(', ');
        
        // Combine time displays
        const timeDisplay = groupSchedules.map(s => `${s.start_time} - ${s.end_time}`).join(', ');
        
        // Combine room displays (show unique rooms with virtual indicator)
        const uniqueRooms = [...new Set(groupSchedules.map(s => {
            const isVirtual = s.bd_desc && s.bd_desc.trim().toUpperCase() === 'VIRTUAL';
            const virtualIndicator = isVirtual ? ' <span class="badge bg-info" style="font-size: 0.7rem;"><i class="bi bi-laptop me-1"></i>Online</span>' : '';
            return s.bd_desc ? `${s.bd_desc} - ${s.rm_name}${virtualIndicator}` : `${s.rm_name}${virtualIndicator}`;
        }))];
        const roomDisplay = uniqueRooms.join(', ');
        
        // Get total hours (from first schedule)
        const totalHours = firstSchedule.total_hours || 
                          (firstSchedule.inst_working_hours && (firstSchedule.inst_status === 'Part-Time' || firstSchedule.inst_status === 'Contractual') ? firstSchedule.inst_working_hours : null) ||
                          firstSchedule.policy_total_hours || 
                          40;
        
        // Get overtime hours for this instructor
        const instId = firstSchedule.inst_id;
        const overtimeInfo = instructorOvertime[instId] || { overtime: 0, assigned: 0, total: totalHours };
        const overtimeHours = overtimeInfo.overtime.toFixed(2);
        
        // Display overtime hours in red to indicate it's overtime
        row.innerHTML = `
            <td>${escapeHtml(firstSchedule.subj_code)}</td>
            <td>${escapeHtml(firstSchedule.subj_desc || '-')}</td>
            <td class="text-center">${lecHours}</td>
            <td class="text-center">${labHours}</td>
            <td class="text-center">${units}</td>
            <td>${escapeHtml(firstSchedule.instructor_name || '-')}</td>
            <td>${escapeHtml(firstSchedule.sec_name || '-')}</td>
            <td>${escapeHtml(dayDisplay)}</td>
            <td>${escapeHtml(timeDisplay)}</td>
            <td>${escapeHtml(roomDisplay)}</td>
            <td class="text-center">${totalHours}</td>
            <td class="text-center text-danger fw-bold">${overtimeHours}</td>
        `;
        
        tbody.appendChild(row);
    });
}

function renderClassTimeLoadTable(schedules) {
    const tbody = document.getElementById('classTimeLoadTableBody');
    if (!tbody) return;
    
    // Clear existing rows
    tbody.innerHTML = '';
    
    if (!schedules || schedules.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x me-2"></i>No schedules found. Please select filters or create a schedule.
                </td>
            </tr>
        `;
        return;
    }
    
    // Calculate assigned hours per instructor using ALL schedules (not just filtered ones)
    // This ensures remaining hours is calculated across all sections, not just the current filter
    const allSchedules = window.allSchedulesData && window.allSchedulesData.length > 0 
        ? window.allSchedulesData 
        : schedules; // Fallback to filtered schedules if allSchedulesData not available
    
    const instructorAssignedHours = {};
    allSchedules.forEach(schedule => {
        const instId = schedule.inst_id;
        if (!instructorAssignedHours[instId]) {
            // Use total_hours from schedule (which now correctly uses instruction_hours for Part-Time/Contractual)
            // Fallback to instruction_hours if available, then policy_total_hours, then default 40
            const totalHours = schedule.total_hours || 
                              (schedule.instruction_hours && (schedule.inst_status === 'Part-Time' || schedule.inst_status === 'Contractual') ? schedule.instruction_hours : null) ||
                              schedule.policy_total_hours || 
                              40;
            instructorAssignedHours[instId] = {
                totalMinutes: 0,
                totalHours: totalHours
            };
        }
        // Add schedule minutes (schd_min is in minutes)
        instructorAssignedHours[instId].totalMinutes += parseFloat(schedule.schd_min || 0);
    });
    
    // Calculate remaining hours for each instructor
    const instructorRemainingHours = {};
    Object.keys(instructorAssignedHours).forEach(instId => {
        const inst = instructorAssignedHours[instId];
        const assignedHours = inst.totalMinutes / 60; // Convert minutes to hours
        const remainingHours = Math.max(0, inst.totalHours - assignedHours); // Ensure non-negative
        instructorRemainingHours[instId] = {
            assigned: assignedHours,
            remaining: remainingHours,
            total: inst.totalHours
        };
    });
    
    // Group schedules by subject code and section
    const groupedSchedules = {};
    schedules.forEach(schedule => {
        const groupKey = `${schedule.subj_code}_${schedule.sec_name || 'NONE'}_${schedule.inst_id}`;
        if (!groupedSchedules[groupKey]) {
            groupedSchedules[groupKey] = [];
        }
        groupedSchedules[groupKey].push(schedule);
    });
    
    // Sort groups by subject code, then by section
    const sortedGroups = Object.keys(groupedSchedules).sort((a, b) => {
        const [subjA, secA] = a.split('_');
        const [subjB, secB] = b.split('_');
        if (subjA !== subjB) {
            return subjA.localeCompare(subjB);
        }
        return (secA || '').localeCompare(secB || '');
    });
    
    // Render each grouped schedule as a table row
    sortedGroups.forEach(groupKey => {
        const groupSchedules = groupedSchedules[groupKey];
        // Sort schedules within group by day and time
        const dayOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        groupSchedules.sort((a, b) => {
            const dayDiff = dayOrder.indexOf(a.schd_day) - dayOrder.indexOf(b.schd_day);
            if (dayDiff !== 0) return dayDiff;
            return a.schd_start.localeCompare(b.schd_start);
        });
        
        // Use first schedule for common fields
        const firstSchedule = groupSchedules[0];
        
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        // Use first schedule's ID for edit (or could use a comma-separated list)
        row.onclick = () => editSchedule(firstSchedule.schd_id);
        row.title = 'Click to edit schedule';
        
        // Get Lec and Lab from subject (not summed across schedules)
        // Use the subject's total Lec/Lab hours, not per-schedule hours
        const lecHours = firstSchedule.subj_lec || 0;
        const labHours = firstSchedule.subj_lab || 0;
        const units = firstSchedule.subj_unit || 0;
        
        // Combine day displays (just days)
        const dayDisplay = groupSchedules.map(s => s.schd_day).join(', ');
        
        // Combine time displays
        const timeDisplay = groupSchedules.map(s => `${s.start_time} - ${s.end_time}`).join(', ');
        
        // Combine room displays (show unique rooms with virtual indicator)
        const uniqueRooms = [...new Set(groupSchedules.map(s => {
            const isVirtual = s.bd_desc && s.bd_desc.trim().toUpperCase() === 'VIRTUAL';
            const virtualIndicator = isVirtual ? ' <span class="badge bg-info" style="font-size: 0.7rem;"><i class="bi bi-laptop me-1"></i>Online</span>' : '';
            return s.bd_desc ? `${s.bd_desc} - ${s.rm_name}${virtualIndicator}` : `${s.rm_name}${virtualIndicator}`;
        }))];
        const roomDisplay = uniqueRooms.join(', ');
        
        // Get total hours (from first schedule)
        const totalHours = firstSchedule.total_hours || 
                          (firstSchedule.inst_working_hours && (firstSchedule.inst_status === 'Part-Time' || firstSchedule.inst_status === 'Contractual') ? firstSchedule.inst_working_hours : null) ||
                          firstSchedule.policy_total_hours || 
                          40;
        
        // Get remaining unassigned hours for this instructor
        const instId = firstSchedule.inst_id;
        const remainingInfo = instructorRemainingHours[instId] || { remaining: totalHours, assigned: 0, total: totalHours };
        const remainingHours = remainingInfo.remaining;
        
        // Format remaining hours (round to 2 decimal places)
        const remainingHoursDisplay = remainingHours.toFixed(2);
        
        // Add color coding: red if negative/overloaded, yellow if low (< 5 hours), green if good
        let remainingClass = '';
        if (remainingHours < 0) {
            remainingClass = 'text-danger fw-bold';
        } else if (remainingHours < 5) {
            remainingClass = 'text-warning fw-semibold';
        } else {
            remainingClass = 'text-success';
        }
        
        row.innerHTML = `
            <td>${escapeHtml(firstSchedule.subj_code)}</td>
            <td>${escapeHtml(firstSchedule.subj_desc || '-')}</td>
            <td class="text-center">${lecHours}</td>
            <td class="text-center">${labHours}</td>
            <td class="text-center">${units}</td>
            <td>${escapeHtml(firstSchedule.instructor_name || '-')}</td>
            <td>${escapeHtml(firstSchedule.sec_name || '-')}</td>
            <td>${escapeHtml(dayDisplay)}</td>
            <td>${escapeHtml(timeDisplay)}</td>
            <td>${escapeHtml(roomDisplay)}</td>
            <td class="text-center">${totalHours}</td>
            <td class="text-center ${remainingClass}">${remainingHoursDisplay}</td>
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Prints the Class Time Load table
 */
function printClassTimeLoad() {
    const table = document.getElementById('classTimeLoadTable');
    if (!table) {
        showToast('error', 'Class Time Load table not found.');
        return;
    }
    
    // Get section name and semester info
    const sectionName = $('#sectionFilter option:selected').text() || 'All Sections';
    const semesterText = ''; // Semester is now determined from active school year/semester settings
    const yearLevel = $('#yearLevelFilter option:selected').text() || 'All Year Levels';
    const program = $('#programFilter option:selected').text() || 'All Programs';
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    if (!printWindow) {
        showToast('error', 'Please allow popups to print the Class Time Load table.');
        return;
    }
    
    // Get current date
    const currentDate = new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Get logo path - reuse helper if available
    const logoPath = (typeof getLogoPath === 'function')
        ? getLogoPath()
        : '/scheduling-evsu-occ_v7.4/public/assets/images/evsu-logo.png';
    
    // Clone the tables
    const clonedTable = table.cloneNode(true);
    const overtimeTable = document.getElementById('overtimeTable');
    const clonedOvertimeTable = overtimeTable ? overtimeTable.cloneNode(true) : null;
    
    // Remove onclick handlers from cloned tables
    const rows = clonedTable.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.removeAttribute('onclick');
        row.removeAttribute('style');
        row.style.cursor = 'default';
    });
    if (clonedOvertimeTable) {
        const oRows = clonedOvertimeTable.querySelectorAll('tbody tr');
        oRows.forEach(row => {
            row.removeAttribute('onclick');
            row.removeAttribute('style');
            row.style.cursor = 'default';
        });
    }
    
    // Build print HTML
    const printHTML = `
<!DOCTYPE html>
<html>
<head>
    <title>Class Time Load - ${sectionName}</title>
    <style>
        @page {
            size: legal portrait;
            margin: 0.5cm;
        }
        body {
            font-family: 'Poppins', 'Arial', 'Helvetica', sans-serif;
            margin: 0;
            padding: 8px;
            font-size: 8pt;
        }
        .print-header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid #800000;
        }
        .print-header img {
            height: 55px;
            width: auto;
            margin-bottom: 6px;
        }
        .print-header h1 {
            margin: 2px 0;
            color: #800000;
            font-size: 12pt;
        }
        .print-header h2 {
            margin: 2px 0;
            color: #333;
            font-size: 9pt;
            font-weight: 600;
        }
        .print-info {
            margin: 6px 0 4px 0;
            font-size: 8pt;
            color: #666;
        }
        .print-info strong {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 7pt;
            border: 2px solid #800000;
        }
        thead {
            background-color: #800000 !important;
        }
        thead th {
            background-color: #800000 !important;
            color: #ffffff !important;
            padding: 3px 2px;
            text-align: left;
            border: 1px solid #600000 !important;
            font-weight: bold;
            font-size: 7pt;
        }
        tbody td {
            padding: 3px;
            border: 1px solid #c0c0c0 !important;
            vertical-align: middle;
            font-size: 7pt;
            background-color: #ffffff;
            color: #000000;
        }
        tbody tr {
            background-color: #ffffff;
        }
        tbody tr:nth-child(even) {
            background-color: #ffffff;
        }
        .text-center {
            text-align: center;
        }
        .text-danger {
            color: #dc3545;
            font-weight: bold;
        }
        .text-warning {
            color: #ffc107;
            font-weight: bold;
        }
        .text-success {
            color: #28a745;
        }
        @media print {
            @page {
                size: legal portrait;
                margin: 0.5cm;
            }
            html, body {
                height: auto !important;
                width: 100% !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body {
                padding: 8px;
            }
            .print-header {
                page-break-after: avoid;
            }
            table {
                page-break-inside: auto;
            }
            tbody tr {
                page-break-inside: auto;
            }
            * {
                page-break-inside: auto !important;
                page-break-after: auto !important;
                page-break-before: auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <img src="${logoPath}" alt="EVSU Logo" onerror="this.style.display='none'">
        <h1>EASTERN VISAYAS STATE UNIVERSITY</h1>
        <h2>Ormoc City Campus</h2>
        <h2>Class Time Load Report</h2>
    </div>
    
    <div class="print-info">
        <strong>Program:</strong> ${program} | 
        <strong>Year Level:</strong> ${yearLevel} | 
        <strong>Section:</strong> ${sectionName}${semesterText ? ' | <strong>Semester:</strong> ' + semesterText : ''}
        <br>
        <strong>Date Printed:</strong> ${currentDate}
    </div>
    ${clonedTable.outerHTML}

    <div style="margin-top: 12px; font-size: 7pt; font-weight: bold; color: #800000; text-align: left;">Overtime</div>
    ${
        clonedOvertimeTable 
            ? clonedOvertimeTable.outerHTML 
            : '<p style="font-size: 6pt; color: #666; margin: 4px 0 8px 0;">No overtime schedules found.</p>'
    }
    
    <div style="margin-top: 10px; font-size: 6pt; color: #666; text-align: center;">
        <p>© ${new Date().getFullYear()} EVSU-OCC Scheduling System. All rights reserved.</p>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>`;
    
    printWindow.document.open('text/html', 'replace');
    printWindow.document.write(printHTML);
    printWindow.document.close();
    
    // Wait for content to load, then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
    
    // Fallback if onload doesn't fire
    setTimeout(() => {
        if (printWindow.document.readyState === 'complete') {
            printWindow.print();
        }
    }, 1000);
}

/**
 * Prints the Overtime table
 */
function printOvertime() {
    const table = document.getElementById('overtimeTable');
    if (!table) {
        showToast('error', 'Overtime table not found.');
        return;
    }
    
    // Check if table has any data (not just the "No overtime schedules found" message)
    const tbody = table.querySelector('tbody');
    const rows = tbody ? tbody.querySelectorAll('tr') : [];
    const hasData = rows.length > 0 && !rows[0].querySelector('td[colspan]');
    
    if (!hasData) {
        showToast('info', 'No overtime schedules to print.');
        return;
    }
    
    // Get section name and semester info
    const sectionName = $('#sectionFilter option:selected').text() || 'All Sections';
    const semesterText = ''; // Semester is now determined from active school year/semester settings
    const yearLevel = $('#yearLevelFilter option:selected').text() || 'All Year Levels';
    const program = $('#programFilter option:selected').text() || 'All Programs';
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    if (!printWindow) {
        showToast('error', 'Please allow popups to print the Overtime table.');
        return;
    }
    
    // Set document type
    printWindow.document.open('text/html', 'replace');
    
    // Get current date
    const currentDate = new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Get logo path - use same path as printSchedule function
    const logoPath = '/scheduling-evsu-occ_v7.4/public/assets/images/evsu-logo.png';
    
    // Clone the table
    const clonedTable = table.cloneNode(true);
    
    // Remove onclick handlers from cloned table
    const clonedRows = clonedTable.querySelectorAll('tbody tr');
    clonedRows.forEach(row => {
        row.removeAttribute('onclick');
        row.removeAttribute('style');
        row.style.cursor = 'default';
    });
    
    // Build print HTML
    const printHTML = `
<!DOCTYPE html>
<html>
<head>
    <title>Overtime Schedules - ${sectionName}</title>
    <style>
        @page {
            size: legal portrait;
            margin: 0.3cm;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 3px;
            font-size: 6pt;
        }
        .print-header {
            text-align: center;
            margin-bottom: 5px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 3px;
        }
        .print-header img {
            height: 30px;
            margin-bottom: 3px;
        }
        .print-header h1 {
            margin: 1px 0;
            color: #800000;
            font-size: 10pt;
        }
        .print-header h2 {
            margin: 1px 0;
            color: #dc3545;
            font-size: 8pt;
            font-weight: bold;
        }
        .print-header h3 {
            margin: 1px 0;
            color: #333;
            font-size: 7pt;
            font-weight: normal;
        }
        .print-info {
            margin: 5px 0;
            font-size: 6pt;
            color: #666;
        }
        .print-info strong {
            color: #333;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 5px;
            margin: 8px 0;
            font-size: 7pt;
        }
        .warning-box strong {
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            font-size: 6pt;
        }
        thead {
            background-color: #800000 !important;
        }
        thead th {
            background-color: #800000 !important;
            color: #ffffff !important;
            padding: 2px 2px;
            text-align: left;
            border: 1px solid #ffffff;
            font-weight: bold;
            font-size: 6pt;
        }
        tbody td {
            padding: 2px;
            border: 1px solid #ddd;
            vertical-align: middle;
            font-size: 6pt;
        }
        tbody tr {
            background-color: #fff5f5;
        }
        tbody tr:nth-child(even) {
            background-color: #ffe0e0;
        }
        .text-center {
            text-align: center;
        }
        .text-danger {
            color: #dc3545;
            font-weight: bold;
        }
        @media print {
            @page {
                size: legal portrait;
                margin: 0.3cm;
            }
            html, body {
                height: auto !important;
                width: 100% !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body {
                padding: 3px;
            }
            .print-header {
                page-break-after: avoid;
            }
            .warning-box {
                page-break-after: avoid;
            }
            table {
                page-break-inside: auto;
            }
            tbody tr {
                page-break-inside: auto;
            }
            * {
                page-break-inside: auto !important;
                page-break-after: auto !important;
                page-break-before: auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <img src="${logoPath}" alt="EVSU Logo" onerror="this.style.display='none'">
        <h1>EASTERN VISAYAS STATE UNIVERSITY</h1>
        <h3>Ormoc City Campus</h3>
        <h2>Overtime Schedules Report</h2>
    </div>
    
    <div class="warning-box">
        <strong>⚠ Warning:</strong> The following schedules exceed the instructor's weekly workload limit (40 hours for Regular, 20 hours for Part-Time).
    </div>
    
    <div class="print-info">
        <strong>Program:</strong> ${program} | 
        <strong>Year Level:</strong> ${yearLevel} | 
        <strong>Section:</strong> ${sectionName}${semesterText ? ' | <strong>Semester:</strong> ' + semesterText : ''}
        <br>
        <strong>Date Printed:</strong> ${currentDate}
    </div>
    
    ${clonedTable.outerHTML}
    
    <div style="margin-top: 10px; font-size: 6pt; color: #666; text-align: center;">
        <p>© ${new Date().getFullYear()} EVSU-OCC Scheduling System. All rights reserved.</p>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>`;
    
    printWindow.document.open('text/html', 'replace');
    printWindow.document.write(printHTML);
    printWindow.document.close();
    
    // Wait for content to load, then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
    
    // Fallback if onload doesn't fire
    setTimeout(() => {
        if (printWindow.document.readyState === 'complete') {
            printWindow.print();
        }
    }, 1000);
}

/**
 * Escapes HTML to prevent XSS
 * @param {string} str - String to escape
 * @returns {string} Escaped string
 */
function escapeHtml(str) {
    if (!str) return '-';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}