<?php
/**
 * Academic Year and Semester Calculator
 * Automatically calculates the current Academic Year and Semester based on date
 * 
 * Rules:
 * - 1st Semester: August–December
 * - 2nd Semester: January–May
 * - Mid-Year (Summer): June–July
 * - Academic Year Format: YYYY–YYYY (e.g., 2025–2026)
 */

/**
 * Calculate current Academic Year
 * Academic Year runs from August to July
 * Format: YYYY–YYYY (e.g., 2025–2026)
 * 
 * @return string Academic Year in format YYYY–YYYY
 */
function calculateCurrentAcademicYear() {
    $currentMonth = (int)date('n'); // 1-12
    $currentYear = (int)date('Y');
    
    // Academic Year starts in August
    // If current month is August or later, it's the start of a new academic year
    if ($currentMonth >= 8) {
        // August–December: Academic Year is currentYear–(currentYear+1)
        $academicYear = $currentYear . '–' . ($currentYear + 1);
    } else {
        // January–July: Academic Year is (currentYear-1)–currentYear
        $academicYear = ($currentYear - 1) . '–' . $currentYear;
    }
    
    return $academicYear;
}

/**
 * Calculate current Semester based on month
 * 
 * @return string Semester: '1st', '2nd', or 'Mid-Year'
 */
function calculateCurrentSemester() {
    $currentMonth = (int)date('n'); // 1-12
    
    // 1st Semester: August (8) to December (12)
    if ($currentMonth >= 8 && $currentMonth <= 12) {
        return '1st';
    }
    // 2nd Semester: January (1) to May (5)
    elseif ($currentMonth >= 1 && $currentMonth <= 5) {
        return '2nd';
    }
    // Mid-Year (Summer): June (6) to July (7)
    else {
        return 'Mid-Year';
    }
}

/**
 * Get Academic Year and Semester as array
 * 
 * @return array ['academic_year' => string, 'semester' => string]
 */
function getCurrentAcademicPeriod() {
    return [
        'academic_year' => calculateCurrentAcademicYear(),
        'semester' => calculateCurrentSemester()
    ];
}

/**
 * Validate if a given month falls within a specific semester
 * 
 * @param int $month Month (1-12)
 * @param string $semester Semester to check
 * @return bool True if month is in semester
 */
function isMonthInSemester($month, $semester) {
    $month = (int)$month;
    
    switch ($semester) {
        case '1st':
            return $month >= 8 && $month <= 12;
        case '2nd':
            return $month >= 1 && $month <= 5;
        case 'Mid-Year':
            return $month >= 6 && $month <= 7;
        default:
            return false;
    }
}

/**
 * Get Academic Year from a specific date
 * 
 * @param string $date Date string (Y-m-d format) or timestamp
 * @return string Academic Year in format YYYY–YYYY
 */
function getAcademicYearFromDate($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp);
    
    if ($month >= 8) {
        return $year . '–' . ($year + 1);
    } else {
        return ($year - 1) . '–' . $year;
    }
}

/**
 * Get Semester from a specific date
 * 
 * @param string $date Date string (Y-m-d format) or timestamp
 * @return string Semester: '1st', '2nd', or 'Mid-Year'
 */
function getSemesterFromDate($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $month = (int)date('n', $timestamp);
    
    if ($month >= 8 && $month <= 12) {
        return '1st';
    } elseif ($month >= 1 && $month <= 5) {
        return '2nd';
    } else {
        return 'Mid-Year';
    }
}

