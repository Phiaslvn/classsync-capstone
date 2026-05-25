/**
 * EVSU Academic Year and Semester Calculator (JavaScript)
 * Automatically calculates and formats the current Academic Year and Semester
 * 
 * Rules:
 * - 1st Semester: August to December
 * - 2nd Semester: January to May
 * - Mid-Year: June to July
 * 
 * Output Format: "2025 - 2026 | 1st Semester"
 */

(function() {
    'use strict';

    /**
     * Calculate current Academic Year
     * Academic Year runs from August to July
     * Format: YYYY - YYYY (e.g., 2025 - 2026)
     */
    function getEVSUAcademicYear() {
        const now = new Date();
        const currentMonth = now.getMonth() + 1; // 0-11 -> 1-12
        const currentYear = now.getFullYear();
        
        // Academic Year starts in August
        if (currentMonth >= 8) {
            // August–December: Academic Year is currentYear - (currentYear+1)
            return currentYear + ' - ' + (currentYear + 1);
        } else {
            // January–July: Academic Year is (currentYear-1) - currentYear
            return (currentYear - 1) + ' - ' + currentYear;
        }
    }

    /**
     * Calculate current Semester based on month
     */
    function getEVSUSemester() {
        const now = new Date();
        const currentMonth = now.getMonth() + 1; // 0-11 -> 1-12
        
        // 1st Semester: August (8) to December (12)
        if (currentMonth >= 8 && currentMonth <= 12) {
            return '1st Semester';
        }
        // 2nd Semester: January (1) to May (5)
        else if (currentMonth >= 1 && currentMonth <= 5) {
            return '2nd Semester';
        }
        // Mid-Year: June (6) to July (7)
        else {
            return 'Mid-Year';
        }
    }

    /**
     * Get formatted Academic Year and Semester
     * Output Format: "2025 - 2026 | 1st Semester"
     */
    function getEVSUAcademicPeriod() {
        const academicYear = getEVSUAcademicYear();
        const semester = getEVSUSemester();
        
        return academicYear + ' | ' + semester;
    }

    /**
     * Get Academic Year and Semester as object
     */
    function getEVSUAcademicPeriodObject() {
        return {
            academicYear: getEVSUAcademicYear(),
            semester: getEVSUSemester(),
            formatted: getEVSUAcademicPeriod()
        };
    }

    /**
     * Auto-fill input field or display element with Academic Period
     * 
     * @param {string} elementId - ID of the element to fill
     * @param {boolean} focusField - Whether to focus on the field (for input fields)
     */
    function setEVSUAcademicPeriod(elementId, focusField = false) {
        const element = document.getElementById(elementId);
        
        if (!element) {
            console.warn('Element not found:', elementId);
            return;
        }
        
        const academicPeriod = getEVSUAcademicPeriod();
        
        // Check if element is input/textarea or display element
        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
            // Only fill if empty (allow manual override)
            if (!element.value || element.value.trim() === '') {
                element.value = academicPeriod;
                
                if (focusField) {
                    setTimeout(function() {
                        element.focus();
                        element.select();
                    }, 100);
                }
            }
        } else {
            // For display elements (div, span, p, etc.)
            element.textContent = academicPeriod;
        }
        
        return academicPeriod;
    }

    /**
     * Auto-fill multiple elements
     * 
     * @param {Object} options - Configuration options
     * @param {string} options.inputFieldId - ID of input field to fill
     * @param {string} options.displayElementId - ID of display element to fill
     * @param {boolean} options.focusField - Whether to focus on input field
     * @param {boolean} options.autoFillOnLoad - Whether to auto-fill on page load
     */
    function initEVSUAcademicPeriod(options = {}) {
        const config = {
            inputFieldId: options.inputFieldId || null,
            displayElementId: options.displayElementId || null,
            focusField: options.focusField !== undefined ? options.focusField : true,
            autoFillOnLoad: options.autoFillOnLoad !== undefined ? options.autoFillOnLoad : true
        };
        
        const academicPeriod = getEVSUAcademicPeriod();
        
        // Auto-fill on page load
        if (config.autoFillOnLoad) {
            const initFunction = function() {
                if (config.inputFieldId) {
                    setEVSUAcademicPeriod(config.inputFieldId, config.focusField);
                }
                if (config.displayElementId) {
                    setEVSUAcademicPeriod(config.displayElementId, false);
                }
            };
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initFunction);
            } else {
                initFunction();
            }
        }
        
        // Return function for manual triggering
        return function() {
            if (config.inputFieldId) {
                return setEVSUAcademicPeriod(config.inputFieldId, config.focusField);
            }
            if (config.displayElementId) {
                return setEVSUAcademicPeriod(config.displayElementId, false);
            }
            return academicPeriod;
        };
    }

    // Export functions to global scope
    window.EVSUAcademicPeriod = {
        getAcademicYear: getEVSUAcademicYear,
        getSemester: getEVSUSemester,
        getFormatted: getEVSUAcademicPeriod,
        getObject: getEVSUAcademicPeriodObject,
        setField: setEVSUAcademicPeriod,
        init: initEVSUAcademicPeriod
    };

    // Auto-initialize if default field exists
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Check for common field IDs
            const commonIds = ['academic_period', 'evsu_academic_period', 'current_academic_period'];
            for (let id of commonIds) {
                if (document.getElementById(id)) {
                    initEVSUAcademicPeriod({
                        inputFieldId: id,
                        focusField: true,
                        autoFillOnLoad: true
                    });
                    break;
                }
            }
        });
    } else {
        // DOM already loaded
        const commonIds = ['academic_period', 'evsu_academic_period', 'current_academic_period'];
        for (let id of commonIds) {
            if (document.getElementById(id)) {
                initEVSUAcademicPeriod({
                    inputFieldId: id,
                    focusField: true,
                    autoFillOnLoad: true
                });
                break;
            }
        }
    }

})();

