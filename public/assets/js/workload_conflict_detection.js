/**
 * Workload Conflict Detection Module
 * 
 * This module provides functions to check workload conflicts
 * and display workload information in the UI.
 */

/**
 * Check workload conflict for an instructor
 * @param {number} instId - Instructor ID
 * @param {number} syId - School Year ID
 * @param {number} schdTerm - Term (1=First Sem, 2=Second Sem, 3=Summer)
 * @param {number} newScheduleMinutes - Minutes of new schedule (optional)
 * @param {number} excludeSchdId - Schedule ID to exclude (optional, for updates)
 * @returns {Promise<Object>} Workload analysis data
 */
async function checkWorkloadConflict(instId, syId, schdTerm, newScheduleMinutes = 0, excludeSchdId = 0) {
    try {
        const params = new URLSearchParams({
            inst_id: instId,
            sy_id: syId,
            schd_term: schdTerm
        });
        
        if (newScheduleMinutes > 0) {
            params.append('new_schedule_minutes', newScheduleMinutes);
        }
        
        if (excludeSchdId > 0) {
            params.append('exclude_schd_id', excludeSchdId);
        }
        
        const response = await fetch(`../../admin/management/check_workload_conflict.php?${params}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to check workload conflict');
        }
        
        return data;
    } catch (error) {
        console.error('Error checking workload conflict:', error);
        throw error;
    }
}

/**
 * Display workload information in a UI element
 * @param {Object} workloadData - Data from checkWorkloadConflict
 * @param {string} containerId - ID of container element to display info
 */
function displayWorkloadInfo(workloadData, containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container element not found:', containerId);
        return;
    }
    
    const { instructor, workload, conflict, projected } = workloadData;
    
    // Determine status color
    let statusColor = '#28a745'; // green
    let statusIcon = '✓';
    
    if (conflict.severity === 'critical') {
        statusColor = '#dc3545'; // red
        statusIcon = '✗';
    } else if (conflict.severity === 'warning') {
        statusColor = '#ffc107'; // yellow
        statusIcon = '⚠';
    } else if (conflict.severity === 'info') {
        statusColor = '#17a2b8'; // blue
        statusIcon = 'ℹ';
    }
    
    // Build HTML
    let html = `
        <div class="workload-info" style="border-left: 4px solid ${statusColor}; padding: 10px; margin: 10px 0; background: #f8f9fa;">
            <h5 style="margin: 0 0 10px 0; color: ${statusColor};">
                ${statusIcon} Workload Status: ${instructor.name}
            </h5>
            
            <div class="workload-details">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Current Workload:</strong>
                        <ul style="margin: 5px 0; padding-left: 20px;">
                            <li>Teaching: ${workload.current.teaching_hours} hrs</li>
                            <li>Other Duties: ${workload.workload.other_hours} hrs</li>
                            <li>Total: ${workload.current.total_hours} / ${workload.limit_hours} hrs</li>
                            <li>Percentage: ${workload.current.percentage}%</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <strong>Workload Components:</strong>
                        <ul style="margin: 5px 0; padding-left: 20px;">
                            <li>Admin: ${workload.components.administration_hours} hrs</li>
                            <li>Research: ${workload.components.research_hours} hrs</li>
                            <li>Extension: ${workload.components.extension_hours} hrs</li>
                            <li>Consultation: ${workload.components.consultation_hours} hrs</li>
                        </ul>
                    </div>
                </div>
    `;
    
    // Add projected info if available
    if (projected) {
        html += `
            <div class="projected-workload" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                <strong>After Adding New Schedule:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <li>New Schedule: ${projected.new_schedule_hours} hrs</li>
                    <li>New Total: ${projected.new_total_hours} / ${workload.limit_hours} hrs</li>
                    <li>New Percentage: ${projected.new_percentage}%</li>
                    ${projected.excess_hours > 0 ? `<li style="color: red;">Exceeds by: ${projected.excess_hours} hrs</li>` : ''}
                    ${projected.remaining_hours > 0 ? `<li style="color: green;">Remaining: ${projected.remaining_hours} hrs</li>` : ''}
                </ul>
            </div>
        `;
    }
    
    // Add conflict message
    if (conflict.message) {
        html += `
            <div class="conflict-message" style="margin-top: 10px; padding: 8px; background: ${statusColor}20; border-radius: 4px;">
                <strong>${conflict.message}</strong>
            </div>
        `;
    }
    
    // Add progress bar
    const percentage = projected ? projected.new_percentage : workload.current.percentage;
    html += `
        <div class="workload-progress" style="margin-top: 10px;">
            <div class="progress" style="height: 25px;">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: ${Math.min(percentage, 100)}%; background-color: ${statusColor};"
                     aria-valuenow="${percentage}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    ${percentage}%
                </div>
            </div>
        </div>
    `;
    
    html += `</div></div>`;
    
    container.innerHTML = html;
}

/**
 * Validate workload before form submission
 * @param {number} instId - Instructor ID
 * @param {number} syId - School Year ID
 * @param {number} schdTerm - Term
 * @param {string} startTime - Start time (HH:mm format)
 * @param {string} endTime - End time (HH:mm format)
 * @param {number} excludeSchdId - Schedule ID to exclude (optional)
 * @returns {Promise<Object>} Validation result
 */
async function validateWorkloadBeforeSubmit(instId, syId, schdTerm, startTime, endTime, excludeSchdId = 0) {
    // Calculate minutes from time difference
    const start = new Date(`2000-01-01 ${startTime}`);
    const end = new Date(`2000-01-01 ${endTime}`);
    const minutes = (end - start) / (1000 * 60);
    
    if (minutes <= 0) {
        return {
            valid: false,
            message: 'End time must be after start time'
        };
    }
    
    try {
        const workloadData = await checkWorkloadConflict(instId, syId, schdTerm, minutes, excludeSchdId);
        
        return {
            valid: workloadData.conflict.can_assign,
            message: workloadData.conflict.message,
            severity: workloadData.conflict.severity,
            data: workloadData
        };
    } catch (error) {
        return {
            valid: false,
            message: 'Error checking workload: ' + error.message
        };
    }
}

/**
 * Example: Integrate with schedule form
 * Call this when instructor is selected or schedule times change
 */
function setupWorkloadValidation() {
    // Example: When instructor dropdown changes
    const instructorSelect = document.getElementById('inst_id');
    const syIdInput = document.getElementById('sy_id');
    const termSelect = document.getElementById('schd_term');
    const startTimeInput = document.getElementById('schd_start');
    const endTimeInput = document.getElementById('schd_end');
    const workloadContainer = document.getElementById('workload-info-container');
    
    if (!instructorSelect || !workloadContainer) {
        return; // Elements not found
    }
    
    async function updateWorkloadDisplay() {
        const instId = instructorSelect.value;
        const syId = syIdInput ? syIdInput.value : null;
        const term = termSelect ? termSelect.value : null;
        const startTime = startTimeInput ? startTimeInput.value : null;
        const endTime = endTimeInput ? endTimeInput.value : null;
        
        if (!instId || !syId || !term) {
            workloadContainer.innerHTML = '';
            return;
        }
        
        try {
            let newScheduleMinutes = 0;
            if (startTime && endTime) {
                const start = new Date(`2000-01-01 ${startTime}`);
                const end = new Date(`2000-01-01 ${endTime}`);
                newScheduleMinutes = (end - start) / (1000 * 60);
            }
            
            const workloadData = await checkWorkloadConflict(instId, syId, term, newScheduleMinutes);
            displayWorkloadInfo(workloadData, 'workload-info-container');
        } catch (error) {
            workloadContainer.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        }
    }
    
    // Add event listeners
    if (instructorSelect) {
        instructorSelect.addEventListener('change', updateWorkloadDisplay);
    }
    if (startTimeInput) {
        startTimeInput.addEventListener('change', updateWorkloadDisplay);
    }
    if (endTimeInput) {
        endTimeInput.addEventListener('change', updateWorkloadDisplay);
    }
    if (termSelect) {
        termSelect.addEventListener('change', updateWorkloadDisplay);
    }
    
    // Initial load
    updateWorkloadDisplay();
}

/**
 * Example: Validate before form submission
 */
async function validateScheduleForm(formElement) {
    const formData = new FormData(formElement);
    const instId = formData.get('inst_id');
    const syId = formData.get('sy_id');
    const term = formData.get('schd_term');
    const startTime = formData.get('schd_start');
    const endTime = formData.get('schd_end');
    const schdId = formData.get('schd_id') || 0;
    
    const validation = await validateWorkloadBeforeSubmit(instId, syId, term, startTime, endTime, schdId);
    
    if (!validation.valid) {
        // Show error message
        alert(`Workload Conflict: ${validation.message}`);
        return false;
    }
    
    if (validation.severity === 'warning') {
        // Show warning but allow submission
        const confirm = window.confirm(`Warning: ${validation.message}\n\nDo you want to proceed?`);
        return confirm;
    }
    
    return true;
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        checkWorkloadConflict,
        displayWorkloadInfo,
        validateWorkloadBeforeSubmit,
        setupWorkloadValidation,
        validateScheduleForm
    };
}




