/**
 * Subject Form Dropdown Functions
 * Handles populating dropdowns for department, program, and curriculum in subject forms.
 */

/**
 * Populates the department dropdown, sets it to the admin's department, and disables it.
 */
function populateSubjectDepartmentDropdown() {
    const deptHiddenInput = document.getElementById('add_subject_dept_id');
    if (!deptHiddenInput) {
        console.error('Department hidden input field not found.');
        return;
    }

    // Set the value of the hidden input from the global variable
    if (window.currentDeptId) {
        deptHiddenInput.value = window.currentDeptId;
        console.log('Department ID set to:', window.currentDeptId);
    } else {
        console.error('Current Department ID (window.currentDeptId) is not available.');
        deptHiddenInput.value = ''; // Ensure it's empty if not set
    }
}

/**
 * Populates the program dropdown with all available programs.
 */
function populateAllProgramsDropdown() {
    const programDropdown = document.getElementById('add_subject_program_id');
    if (!programDropdown) return;

    programDropdown.innerHTML = '<option value="">Loading Programs...</option>';

    // Fetch all programs (API should be adjusted to return all if no dept_id is passed)
    fetch(`../../admin/management/get_programs_by_department.php`)
        .then(response => response.json())
        .then(data => {
            programDropdown.innerHTML = '<option value="">Select Program</option>';
            if (data.success && data.programs.length > 0) {
                data.programs.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program.program_id;
                    option.textContent = `${program.program_name} (${program.dept_name})`;
                    programDropdown.appendChild(option);
                });
            } else {
                programDropdown.innerHTML = '<option value="">No Programs Found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading programs:', error);
            programDropdown.innerHTML = '<option value="">Error Loading Programs</option>';
        });
}

/**
 * Populates the curriculum dropdown with all available curricula.
 */
function populateAllCurriculaDropdown(selectedProgramId = '') {
    const curriculumDropdown = document.getElementById('add_subject_curr_id');
    if (!curriculumDropdown) return;

    curriculumDropdown.innerHTML = '<option value="">Loading Curricula...</option>';

    const curriculaUrl = selectedProgramId
        ? `../../admin/management/get_curricula.php?program_id=${encodeURIComponent(selectedProgramId)}`
        : `../../admin/management/get_curricula.php`;
    fetch(curriculaUrl)
        .then(response => response.json())
        .then(data => {
            curriculumDropdown.innerHTML = '<option value="">Select Curriculum</option>';
            const curricula = data && data.success && data.data && Array.isArray(data.data.curricula)
                ? data.data.curricula
                : [];
            if (curricula.length > 0) {
                const filteredCurricula = selectedProgramId
                    ? curricula.filter(curriculum =>
                        !curriculum.program_id || String(curriculum.program_id) === String(selectedProgramId)
                    )
                    : curricula;

                filteredCurricula.forEach(curriculum => {
                    // Use curr_yr if available, otherwise fallback to curr_effective_start_year, or show nothing
                    let yearDisplay = '';
                    if (curriculum.curr_yr && curriculum.curr_yr.trim() !== '') {
                        yearDisplay = ` (${curriculum.curr_yr})`;
                    } else if (curriculum.curr_effective_start_year) {
                        yearDisplay = ` (${curriculum.curr_effective_start_year})`;
                    }
                    const option = document.createElement('option');
                    option.value = curriculum.curr_id;
                    option.textContent = `${curriculum.curr_name}${yearDisplay}`;
                    curriculumDropdown.appendChild(option);
                });
                if (filteredCurricula.length === 0) {
                    curriculumDropdown.innerHTML = '<option value="">No Curricula Found for Selected Program</option>';
                }
            } else {
                curriculumDropdown.innerHTML = '<option value="">No Curricula Found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading curricula:', error);
            curriculumDropdown.innerHTML = '<option value="">Error Loading Curricula</option>';
        });
}


// Initialize dropdowns when the "Add Subject" modal is shown.
document.addEventListener('DOMContentLoaded', function() {
    const addSubjectModal = document.getElementById('addSubjectModal');
    if (addSubjectModal) {
        addSubjectModal.addEventListener('show.bs.modal', function() {
            console.log('Add Subject modal is opening. Populating dropdowns.');
            populateSubjectDepartmentDropdown();
            populateAllProgramsDropdown();
            populateAllCurriculaDropdown();
        });
        
        // Add event listeners for category, program, and curriculum changes to trigger validation
        // These listeners persist even when modal is closed/reopened
        setTimeout(function() {
            const categorySelect = document.getElementById('subj_category');
            const programSelect = document.getElementById('add_subject_program_id');
            const curriculumSelect = document.getElementById('add_subject_curr_id');
            
            if (categorySelect && !categorySelect.dataset.validationListenerAdded) {
                categorySelect.addEventListener('change', function() {
                    if (typeof checkSubjectCodeDebounced === 'function') {
                        checkSubjectCodeDebounced();
                    }
                });
                categorySelect.dataset.validationListenerAdded = 'true';
            }
            
            if (programSelect && !programSelect.dataset.validationListenerAdded) {
                programSelect.addEventListener('change', function() {
                    populateAllCurriculaDropdown(this.value);
                    if (typeof checkSubjectCodeDebounced === 'function') {
                        checkSubjectCodeDebounced();
                    }
                });
                programSelect.dataset.validationListenerAdded = 'true';
            }
            
            if (curriculumSelect && !curriculumSelect.dataset.validationListenerAdded) {
                curriculumSelect.addEventListener('change', function() {
                    if (typeof checkSubjectCodeDebounced === 'function') {
                        checkSubjectCodeDebounced();
                    }
                });
                curriculumSelect.dataset.validationListenerAdded = 'true';
            }
        }, 100);
    }
    
    // Initialize Update Curriculum Modal
    const updateCurriculumModal = document.getElementById('updateCurriculumModal');
    if (updateCurriculumModal) {
        updateCurriculumModal.addEventListener('show.bs.modal', function() {
            console.log('Update Curriculum modal is opening. Populating dropdowns.');
            populateUpdateCurriculumDropdowns();
            setupYearLevelCheckboxes();
        });
        
        updateCurriculumModal.addEventListener('hidden.bs.modal', function() {
            // Reset form when modal is closed
            document.getElementById('updateCurriculumForm').reset();
            document.getElementById('updateCurriculumPreview').style.display = 'none';
            // Uncheck all year level checkboxes
            document.querySelectorAll('.year-level-checkbox, #yearLevelAll').forEach(cb => {
                cb.checked = false;
            });
        });
    }
});

function populateDropdown(selectElement, items, valueKey, textKey, defaultText) {
    if (!selectElement) return;
    selectElement.innerHTML = `<option value="">${defaultText}</option>`;
    items.forEach(item => {
        const option = new Option(item[textKey], item[valueKey]);
        selectElement.add(option);
    });
}

/**
 * Populates dropdowns for the Update Curriculum modal
 */
function populateUpdateCurriculumDropdowns() {
    // Populate program dropdown
    const programDropdown = document.getElementById('update_curr_program_id');
    if (programDropdown) {
        programDropdown.innerHTML = '<option value="">Loading Programs...</option>';
        if (!programDropdown.dataset.curriculumUsageListenerAdded) {
            programDropdown.addEventListener('change', function() {
                const programId = this.value;
                if (programId) {
                    loadCurrentCurriculumUsage(programId);
                } else {
                    document.getElementById('currentCurriculumDisplay').style.display = 'none';
                }
            });
            programDropdown.dataset.curriculumUsageListenerAdded = 'true';
        }
        fetch(`../../admin/management/get_programs_by_department.php`)
            .then(response => response.json())
            .then(data => {
                programDropdown.innerHTML = '<option value="">Select Program</option>';
                if (data.success && data.programs.length > 0) {
                    data.programs.forEach(program => {
                        const option = document.createElement('option');
                        option.value = program.program_id;
                        option.textContent = `${program.program_name} (${program.dept_name})`;
                        programDropdown.appendChild(option);
                    });
                } else {
                    programDropdown.innerHTML = '<option value="">No Programs Found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading programs:', error);
                programDropdown.innerHTML = '<option value="">Error Loading Programs</option>';
            });
    }
    
    // Populate curriculum dropdown
    const curriculumDropdown = document.getElementById('update_curr_curr_id');
    if (curriculumDropdown) {
        curriculumDropdown.innerHTML = '<option value="">Loading Curricula...</option>';
        fetch(`../../admin/management/get_curricula.php`)
            .then(response => response.json())
            .then(data => {
                curriculumDropdown.innerHTML = '<option value="">Select Curriculum</option>';
                if (data.success && data.data.curricula.length > 0) {
                    data.data.curricula.forEach(curriculum => {
                        // Use curr_yr if available, otherwise fallback to curr_effective_start_year, or show nothing
                        let yearDisplay = '';
                        if (curriculum.curr_yr && curriculum.curr_yr.trim() !== '') {
                            yearDisplay = ` (${curriculum.curr_yr})`;
                        } else if (curriculum.curr_effective_start_year) {
                            yearDisplay = ` (${curriculum.curr_effective_start_year})`;
                        }
                        const option = document.createElement('option');
                        option.value = curriculum.curr_id;
                        option.textContent = `${curriculum.curr_name}${yearDisplay}`;
                        curriculumDropdown.appendChild(option);
                    });
                } else {
                    curriculumDropdown.innerHTML = '<option value="">No Curricula Found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading curricula:', error);
                curriculumDropdown.innerHTML = '<option value="">Error Loading Curricula</option>';
            });
    }
}

/**
 * Sets up year level checkbox interactions
 */
function setupYearLevelCheckboxes() {
    const yearLevelAll = document.getElementById('yearLevelAll');
    const yearLevelCheckboxes = document.querySelectorAll('.year-level-checkbox');
    
    if (yearLevelAll && !yearLevelAll.dataset.previewListenerAdded) {
        yearLevelAll.addEventListener('change', function() {
            if (this.checked) {
                // Uncheck all individual year level checkboxes
                yearLevelCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
            }
            updatePreview();
        });
        yearLevelAll.dataset.previewListenerAdded = 'true';
    }
    
    yearLevelCheckboxes.forEach(checkbox => {
        if (checkbox.dataset.previewListenerAdded) return;
        checkbox.addEventListener('change', function() {
            if (this.checked && yearLevelAll) {
                yearLevelAll.checked = false;
            }
            updatePreview();
        });
        checkbox.dataset.previewListenerAdded = 'true';
    });
    
    // Update preview when program or curriculum changes
    const programSelect = document.getElementById('update_curr_program_id');
    const curriculumSelect = document.getElementById('update_curr_curr_id');
    
    if (programSelect && !programSelect.dataset.previewListenerAdded) {
        programSelect.addEventListener('change', updatePreview);
        programSelect.dataset.previewListenerAdded = 'true';
    }
    if (curriculumSelect && !curriculumSelect.dataset.previewListenerAdded) {
        curriculumSelect.addEventListener('change', updatePreview);
        curriculumSelect.dataset.previewListenerAdded = 'true';
    }
}

/**
 * Loads and displays current curriculum usage by year level for the selected program
 */
function loadCurrentCurriculumUsage(programId) {
    const displayDiv = document.getElementById('currentCurriculumDisplay');
    const infoDiv = document.getElementById('currentCurriculumInfo');
    
    if (!displayDiv || !infoDiv) return;
    
    infoDiv.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Loading current curriculum usage...';
    displayDiv.style.display = 'block';
    
    // Fetch current curriculum usage by year level
    fetch(`../../admin/management/get_curriculum_usage_by_year_level.php?program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.usage) {
                let html = '<div class="row g-2">';
                const yearLevelNames = {
                    1: '1st Year',
                    2: '2nd Year',
                    3: '3rd Year',
                    4: '4th Year',
                    5: '5th Year'
                };
                
                for (let level = 1; level <= 5; level++) {
                    const usage = data.usage[level] || null;
                    const curriculumName = usage ? usage.curriculum_name : 'Not Set';
                    const curriculumId = usage ? usage.curr_id : null;
                    const subjectCount = usage ? usage.subject_count : 0;
                    
                    html += `<div class="col-md-6">
                        <div class="border rounded p-2">
                            <strong>${yearLevelNames[level]}:</strong><br>
                            <span class="${curriculumId ? 'text-primary' : 'text-muted'}">
                                ${curriculumName} ${curriculumId ? `(${subjectCount} subject${subjectCount !== 1 ? 's' : ''})` : ''}
                            </span>
                        </div>
                    </div>`;
                }
                
                html += '</div>';
                infoDiv.innerHTML = html;
            } else {
                infoDiv.innerHTML = '<em class="text-muted">No curriculum usage data found for this program.</em>';
            }
        })
        .catch(error => {
            console.error('Error loading curriculum usage:', error);
            infoDiv.innerHTML = '<em class="text-danger">Error loading curriculum usage data.</em>';
        });
}

/**
 * Updates the preview text in the modal
 */
function updatePreview() {
    const previewDiv = document.getElementById('updateCurriculumPreview');
    const previewText = document.getElementById('updateCurriculumPreviewText');
    
    if (!previewDiv || !previewText) return;
    
    const yearLevelAll = document.getElementById('yearLevelAll');
    const selectedYearLevels = [];
    
    if (yearLevelAll && yearLevelAll.checked) {
        selectedYearLevels.push('All Years');
    } else {
        document.querySelectorAll('.year-level-checkbox:checked').forEach(cb => {
            const level = cb.value;
            const levelNames = {
                '1': '1st Year',
                '2': '2nd Year',
                '3': '3rd Year',
                '4': '4th Year',
                '5': '5th Year'
            };
            if (levelNames[level]) {
                selectedYearLevels.push(levelNames[level]);
            }
        });
    }
    
    const programSelect = document.getElementById('update_curr_program_id');
    const curriculumSelect = document.getElementById('update_curr_curr_id');
    
    const programName = programSelect && programSelect.selectedOptions[0] ? programSelect.selectedOptions[0].text : '';
    const curriculumName = curriculumSelect && curriculumSelect.selectedOptions[0] ? curriculumSelect.selectedOptions[0].text : '';
    
    if (selectedYearLevels.length > 0 && programName && curriculumName) {
        previewText.textContent = `Update curriculum to "${curriculumName}" for ${selectedYearLevels.join(', ')} subjects in program "${programName}"`;
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }
}

/**
 * Submits the update curriculum form
 */
function submitUpdateCurriculum() {
    const form = document.getElementById('updateCurriculumForm');
    if (!form) {
        Swal.fire('Error', 'Form not found.', 'error');
        return;
    }
    
    // Get selected year levels
    const yearLevelAll = document.getElementById('yearLevelAll');
    const selectedYearLevels = [];
    
    if (yearLevelAll && yearLevelAll.checked) {
        selectedYearLevels.push('all');
    } else {
        document.querySelectorAll('.year-level-checkbox:checked').forEach(cb => {
            selectedYearLevels.push(cb.value);
        });
    }
    
    if (selectedYearLevels.length === 0) {
        Swal.fire('Validation Error', 'Please select at least one year level.', 'warning');
        return;
    }
    
    const programId = document.getElementById('update_curr_program_id').value;
    const currId = document.getElementById('update_curr_curr_id').value;
    
    if (!programId || !currId) {
        Swal.fire('Validation Error', 'Please select both program and curriculum.', 'warning');
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    selectedYearLevels.forEach(level => {
        formData.append('year_levels[]', level);
    });
    formData.append('program_id', programId);
    formData.append('curr_id', currId);
    
    // Show confirmation dialog
    const yearLevelText = selectedYearLevels.includes('all') ? 'All Years' : selectedYearLevels.map(l => {
        const names = {'1': '1st', '2': '2nd', '3': '3rd', '4': '4th', '5': '5th'};
        return names[l] + ' Year';
    }).join(', ');
    
    const programName = document.getElementById('update_curr_program_id').selectedOptions[0].text;
    const curriculumName = document.getElementById('update_curr_curr_id').selectedOptions[0].text;
    
    Swal.fire({
        title: 'Confirm Curriculum Reference Update',
        html: `Update <strong>${yearLevelText}</strong> in <strong>${programName}</strong> to reference curriculum <strong>${curriculumName}</strong>?<br><br>
               <strong>Note:</strong> Only selected year level(s) will be updated. Unselected year levels remain unchanged.<br><br>
               <small class="text-muted">This action cannot be undone.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#eb1e1e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Update Reference',
        cancelButtonText: 'Cancel',
        width: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Updating Curriculum Reference...',
                text: 'Changing curriculum reference for selected year level(s). Subjects will now come from the new curriculum.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit the form
            fetch('../../admin/management/bulk_update_subject_curriculum.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const newCurriculumName = data.data && data.data.curriculum_name ? data.data.curriculum_name : curriculumName;
                    const message = data.data && data.data.message ? data.data.message : data.message;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Curriculum Reference Updated!',
                        html: `<strong>${message}</strong><br><br>
                               <strong>Result:</strong><br>
                               ${data.data && data.data.details ? data.data.details.join('<br>') : ''}<br><br>
                               <small class="text-muted">
                               <strong>Selected year level(s)</strong> now show exactly the subjects defined in <strong>${newCurriculumName}</strong>.<br>
                               <strong>Unselected year levels</strong> continue using their existing curriculum (unchanged).
                               </small>`,
                        confirmButtonText: 'OK',
                        width: '600px'
                    }).then(() => {
                        // Close modal first
                        const modal = bootstrap.Modal.getInstance(document.getElementById('updateCurriculumModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Wait a moment for modal to close, then force recreation of subject tables
                        // This ensures tables use the new curriculum from program_year_level_curriculum mapping
                        setTimeout(() => {
                            console.log('🔄 Curriculum mapping updated - recreating subject tables...');
                            
                            // Use refreshAllSubjectTables with forceRecreate flag
                            if (typeof refreshAllSubjectTables === 'function') {
                                refreshAllSubjectTables(true); // true = force recreation
                            } else {
                                // Fallback: manual recreation
                                const container = document.getElementById('filteredSubjectsContainer');
                                if (container) {
                                    const yearLevelGroupWrapper = container.querySelector('.year-level-group-wrapper');
                                    const mainTableWrapper = container.querySelector('.filtered-table-wrapper:not(.year-level-group-wrapper .filtered-table-wrapper)');
                                    
                                    if (yearLevelGroupWrapper) yearLevelGroupWrapper.remove();
                                    if (mainTableWrapper) mainTableWrapper.remove();
                                    
                                    if (typeof addFilteredSubjectTable === 'function') {
                                        addFilteredSubjectTable(true);
                                    }
                                }
                            }
                        }, 300); // Small delay to ensure modal is closed
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to update curriculum.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred while updating the curriculum.', 'error');
            });
        }
    });
}