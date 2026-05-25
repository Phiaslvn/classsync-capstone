/**
 * Academic Year and Semester Auto-fill Script
 * Automatically populates Academic Year and Semester fields based on current date
 * 
 * Rules:
 * - 1st Semester: August–December
 * - 2nd Semester: January–May
 * - Mid-Year (Summer): June–July
 * - Academic Year Format: YYYY–YYYY (e.g., 2025–2026)
 */

(function() {
    'use strict';

    /**
     * Calculate current Academic Year
     * Academic Year runs from August to July
     * Format: YYYY–YYYY (e.g., 2025–2026)
     */
    function calculateCurrentAcademicYear() {
        const now = new Date();
        const currentMonth = now.getMonth() + 1; // 0-11 -> 1-12
        const currentYear = now.getFullYear();
        
        // Academic Year starts in August
        // If current month is August or later, it's the start of a new academic year
        if (currentMonth >= 8) {
            // August–December: Academic Year is currentYear–(currentYear+1)
            return currentYear + '–' + (currentYear + 1);
        } else {
            // January–July: Academic Year is (currentYear-1)–currentYear
            return (currentYear - 1) + '–' + currentYear;
        }
    }

    /**
     * Calculate current Semester based on month
     */
    function calculateCurrentSemester() {
        const now = new Date();
        const currentMonth = now.getMonth() + 1; // 0-11 -> 1-12
        
        // 1st Semester: August (8) to December (12)
        if (currentMonth >= 8 && currentMonth <= 12) {
            return '1st';
        }
        // 2nd Semester: January (1) to May (5)
        else if (currentMonth >= 1 && currentMonth <= 5) {
            return '2nd';
        }
        // Mid-Year (Summer): June (6) to July (7)
        else {
            return 'Mid-Year';
        }
    }

    /**
     * Auto-fill Academic Year and Semester fields
     * 
     * @param {string} academicYearFieldId - ID of Academic Year input field
     * @param {string} semesterFieldId - ID of Semester select/dropdown field
     * @param {boolean} focusField - Whether to focus on Academic Year field after filling
     */
    function autoFillAcademicFields(academicYearFieldId, semesterFieldId, focusField = true) {
        const academicYearField = document.getElementById(academicYearFieldId);
        const semesterField = document.getElementById(semesterFieldId);
        
        if (!academicYearField) {
            console.warn('Academic Year field not found:', academicYearFieldId);
            return;
        }
        
        if (!semesterField) {
            console.warn('Semester field not found:', semesterFieldId);
            return;
        }
        
        // Calculate current Academic Year and Semester
        const currentAcademicYear = calculateCurrentAcademicYear();
        const currentSemester = calculateCurrentSemester();
        
        // Only fill if fields are empty (allow manual override)
        if (!academicYearField.value || academicYearField.value.trim() === '') {
            academicYearField.value = currentAcademicYear;
        }
        
        // Handle semester field (could be select or input)
        if (semesterField.tagName === 'SELECT') {
            // For select dropdown, try to find matching option
            const options = semesterField.options;
            let found = false;
            
            for (let i = 0; i < options.length; i++) {
                const optionValue = options[i].value.toLowerCase();
                const optionText = options[i].text.toLowerCase();
                const semesterLower = currentSemester.toLowerCase();
                
                // Check if option matches current semester
                if (optionValue === semesterLower || 
                    optionText.includes(semesterLower) ||
                    optionValue === 'mid-year' && currentSemester === 'Mid-Year' ||
                    optionValue === 'summer' && currentSemester === 'Mid-Year') {
                    semesterField.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            
            // If no exact match found and field is empty, set first option or leave empty
            if (!found && semesterField.selectedIndex === 0 && options[0].value === '') {
                // Leave empty if first option is empty placeholder
            }
        } else {
            // For input field, fill if empty
            if (!semesterField.value || semesterField.value.trim() === '') {
                semesterField.value = currentSemester;
            }
        }
        
        // Focus on Academic Year field if requested
        if (focusField) {
            // Use setTimeout to ensure field is ready
            setTimeout(function() {
                academicYearField.focus();
                // Select all text for easy editing
                academicYearField.select();
            }, 100);
        }
        
        console.log('Academic fields auto-filled:', {
            academicYear: currentAcademicYear,
            semester: currentSemester
        });
    }

    /**
     * Initialize auto-fill on page load
     * Can be called with custom field IDs or use default IDs
     * 
     * @param {Object} options - Configuration options
     * @param {string} options.academicYearFieldId - ID of Academic Year field (default: 'academic_year')
     * @param {string} options.semesterFieldId - ID of Semester field (default: 'semester')
     * @param {boolean} options.focusField - Whether to focus on Academic Year field (default: true)
     * @param {boolean} options.autoFillOnLoad - Whether to auto-fill on page load (default: true)
     */
    function initAcademicYearAutoFill(options = {}) {
        const config = {
            academicYearFieldId: options.academicYearFieldId || 'academic_year',
            semesterFieldId: options.semesterFieldId || 'semester',
            focusField: options.focusField !== undefined ? options.focusField : true,
            autoFillOnLoad: options.autoFillOnLoad !== undefined ? options.autoFillOnLoad : true
        };
        
        // Auto-fill on page load
        if (config.autoFillOnLoad) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    autoFillAcademicFields(
                        config.academicYearFieldId,
                        config.semesterFieldId,
                        config.focusField
                    );
                });
            } else {
                // DOM already loaded
                autoFillAcademicFields(
                    config.academicYearFieldId,
                    config.semesterFieldId,
                    config.focusField
                );
            }
        }
        
        // Return function for manual triggering
        return function() {
            autoFillAcademicFields(
                config.academicYearFieldId,
                config.semesterFieldId,
                config.focusField
            );
        };
    }

    // Export functions to global scope
    window.AcademicYearAutoFill = {
        calculateCurrentAcademicYear: calculateCurrentAcademicYear,
        calculateCurrentSemester: calculateCurrentSemester,
        autoFill: autoFillAcademicFields,
        init: initAcademicYearAutoFill
    };

    // Auto-initialize if fields exist with default IDs
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Check if default fields exist
            if (document.getElementById('academic_year') && document.getElementById('semester')) {
                initAcademicYearAutoFill();
            }
        });
    } else {
        // DOM already loaded
        if (document.getElementById('academic_year') && document.getElementById('semester')) {
            initAcademicYearAutoFill();
        }
    }

})();

