<?php
/**
 * EVSU Academic Year and Semester Calculator
 * Automatically calculates and formats the current Academic Year and Semester
 * 
 * Rules:
 * - 1st Semester: August to December
 * - 2nd Semester: January to May
 * - Mid-Year: June to July
 * 
 * Output Format: "2025 - 2026 | 1st Semester"
 */

/**
 * Calculate current Academic Year
 * Academic Year runs from August to July
 * Format: YYYY - YYYY (e.g., 2025 - 2026)
 * 
 * @return string Academic Year in format YYYY - YYYY
 */
function getEVSUAcademicYear() {
    $currentMonth = (int)date('n'); // 1-12
    $currentYear = (int)date('Y');
    
    // Academic Year starts in August
    if ($currentMonth >= 8) {
        // August–December: Academic Year is currentYear - (currentYear+1)
        return $currentYear . ' - ' . ($currentYear + 1);
    } else {
        // January–July: Academic Year is (currentYear-1) - currentYear
        return ($currentYear - 1) . ' - ' . $currentYear;
    }
}

/**
 * Calculate current Semester based on month
 * 
 * @return string Semester: '1st Semester', '2nd Semester', or 'Mid-Year'
 */
function getEVSUSemester() {
    $currentMonth = (int)date('n'); // 1-12
    
    // 1st Semester: August (8) to December (12)
    if ($currentMonth >= 8 && $currentMonth <= 12) {
        return '1st Semester';
    }
    // 2nd Semester: January (1) to May (5)
    elseif ($currentMonth >= 1 && $currentMonth <= 5) {
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
 * 
 * @return string Formatted academic period
 */
function getEVSUAcademicPeriod() {
    $academicYear = getEVSUAcademicYear();
    $semester = getEVSUSemester();
    
    return $academicYear . ' | ' . $semester;
}

/**
 * Get Academic Year and Semester as array
 * 
 * @return array ['academic_year' => string, 'semester' => string, 'formatted' => string]
 */
function getEVSUAcademicPeriodArray() {
    $academicYear = getEVSUAcademicYear();
    $semester = getEVSUSemester();
    
    return [
        'academic_year' => $academicYear,
        'semester' => $semester,
        'formatted' => $academicYear . ' | ' . $semester
    ];
}

/**
 * Get Academic Year from a specific date
 * 
 * @param string|int $date Date string (Y-m-d format) or timestamp
 * @return string Academic Year in format YYYY - YYYY
 */
function getEVSUAcademicYearFromDate($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp);
    
    if ($month >= 8) {
        return $year . ' - ' . ($year + 1);
    } else {
        return ($year - 1) . ' - ' . $year;
    }
}

/**
 * Get Semester from a specific date
 * 
 * @param string|int $date Date string (Y-m-d format) or timestamp
 * @return string Semester: '1st Semester', '2nd Semester', or 'Mid-Year'
 */
function getEVSUSemesterFromDate($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $month = (int)date('n', $timestamp);
    
    if ($month >= 8 && $month <= 12) {
        return '1st Semester';
    } elseif ($month >= 1 && $month <= 5) {
        return '2nd Semester';
    } else {
        return 'Mid-Year';
    }
}

