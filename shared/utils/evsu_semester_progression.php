<?php
/**
 * EVSU Semester Auto-Progression System
 * Automatically progresses semesters based on EVSU academic calendar
 * 
 * Rules:
 * - 1st Semester: August to December
 * - 2nd Semester: January to May
 * - Mid-Year: June to July (optional)
 * 
 * Progression:
 * - After 1st Semester ends → 2nd Semester
 * - After 2nd Semester ends → 1st Semester of next SY
 */

// Database connection should be provided by calling file
// require_once __DIR__ . '/../../config/database.php';

/**
 * Calculate semester dates based on EVSU calendar
 * 
 * @param string $syYear Academic Year (e.g., "2025 - 2026")
 * @param string $semester Semester ("1st Semester", "2nd Semester", "Mid-Year")
 * @return array ['start_date' => string, 'end_date' => string]
 */
function calculateSemesterDates($syYear, $semester) {
    // Extract start year from SY (e.g., "2025 - 2026" -> 2025)
    $startYear = (int)trim(explode('-', $syYear)[0]);
    
    switch ($semester) {
        case '1st Semester':
            // August 1 to December 31
            return [
                'start_date' => $startYear . '-08-01',
                'end_date' => $startYear . '-12-31'
            ];
            
        case '2nd Semester':
            // January 1 to May 31
            return [
                'start_date' => ($startYear + 1) . '-01-01',
                'end_date' => ($startYear + 1) . '-05-31'
            ];
            
        case 'Mid-Year':
            // June 1 to July 31
            return [
                'start_date' => ($startYear + 1) . '-06-01',
                'end_date' => ($startYear + 1) . '-07-31'
            ];
            
        default:
            return null;
    }
}

/**
 * Get next semester based on current semester
 * 
 * @param string $currentSemester Current semester
 * @param string $currentSY Current school year
 * @return array ['sy_year' => string, 'semester' => string]
 */
function getNextSemester($currentSemester, $currentSY) {
    $startYear = (int)trim(explode('-', $currentSY)[0]);
    
    switch ($currentSemester) {
        case '1st Semester':
            // Next is 2nd Semester of same SY
            return [
                'sy_year' => $currentSY,
                'semester' => '2nd Semester'
            ];
            
        case '2nd Semester':
        case 'Mid-Year':
            // Next is 1st Semester of next SY
            $nextStartYear = $startYear + 1;
            $nextEndYear = $nextStartYear + 1;
            return [
                'sy_year' => $nextStartYear . ' - ' . $nextEndYear,
                'semester' => '1st Semester'
            ];
            
        default:
            return null;
    }
}

/**
 * Check if current semester has ended
 * 
 * @param string $endDate End date (Y-m-d format)
 * @return bool True if semester has ended
 */
function hasSemesterEnded($endDate) {
    $endTimestamp = strtotime($endDate);
    $currentTimestamp = time();
    
    // Semester ends at end of day, so check if current date is after end date
    return $currentTimestamp > strtotime($endDate . ' 23:59:59');
}

/**
 * Auto-progress to next semester if current has ended
 * 
 * @param mysqli|null $conn Database connection (optional, uses global if not provided)
 * @return array Result with success status and message
 */
function autoProgressSemester($conn = null) {
    global $conn;
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection not available', 'progressed' => false];
    }
    
    // Verify connection is a mysqli object
    if (!($conn instanceof mysqli)) {
        error_log("Invalid connection type in autoProgressSemester. Expected mysqli, got: " . gettype($conn));
        return ['success' => false, 'message' => 'Invalid database connection type', 'progressed' => false];
    }
    try {
        $conn->begin_transaction();
        
        // Get current active semester
        $stmt = $conn->prepare("
            SELECT sy_id, sy_year, semester, end_date, is_current
            FROM schoolyear
            WHERE is_current = 1
            LIMIT 1
        ");
        
        // Check if prepare() succeeded
        if ($stmt === false) {
            $conn->rollback();
            error_log("SQL Prepare Error in autoProgressSemester: " . $conn->error);
            return [
                'success' => false, 
                'message' => 'Failed to prepare SQL query: ' . $conn->error,
                'progressed' => false
            ];
        }
        
        if (!$stmt->execute()) {
            $conn->rollback();
            error_log("SQL Execute Error in autoProgressSemester: " . $stmt->error);
            return [
                'success' => false,
                'message' => 'Failed to execute SQL query: ' . $stmt->error,
                'progressed' => false
            ];
        }
        $result = $stmt->get_result();
        $current = $result->fetch_assoc();
        
        if (!$current) {
            // No current semester set, create one based on current date
            return initializeCurrentSemester($conn);
        }
        
        // Check if current semester has ended
        if (!hasSemesterEnded($current['end_date'])) {
            $conn->commit();
            return [
                'success' => true,
                'message' => 'Current semester is still active.',
                'current' => $current,
                'progressed' => false
            ];
        }
        
        // Semester has ended, progress to next
        $next = getNextSemester($current['semester'], $current['sy_year']);
        $dates = calculateSemesterDates($next['sy_year'], $next['semester']);
        
        // Check if next semester record exists
        $stmt = $conn->prepare("
            SELECT sy_id FROM schoolyear
            WHERE sy_year = ? AND semester = ?
            LIMIT 1
        ");
        
        if ($stmt === false) {
            $conn->rollback();
            error_log("SQL Prepare Error (check existing): " . $conn->error);
            return [
                'success' => false,
                'message' => 'Failed to prepare SQL query: ' . $conn->error,
                'progressed' => false
            ];
        }
        
        $stmt->bind_param("ss", $next['sy_year'], $next['semester']);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            error_log("SQL Execute Error (check existing): " . $stmt->error);
            return [
                'success' => false,
                'message' => 'Failed to execute SQL query: ' . $stmt->error,
                'progressed' => false
            ];
        }
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE schoolyear
                SET is_current = 1,
                    start_date = ?,
                    end_date = ?,
                    updated_at = NOW()
                WHERE sy_id = ?
            ");
            
            if ($stmt === false || !$stmt->bind_param("ssi", $dates['start_date'], $dates['end_date'], $existing['sy_id']) || !$stmt->execute()) {
                $conn->rollback();
                error_log("SQL Error (update existing): " . ($stmt ? $stmt->error : $conn->error));
                return [
                    'success' => false,
                    'message' => 'Failed to update semester: ' . ($stmt ? $stmt->error : $conn->error),
                    'progressed' => false
                ];
            }
            $newSyId = $existing['sy_id'];
        } else {
            // Create new record
            $stmt = $conn->prepare("
                INSERT INTO schoolyear (sy_year, semester, start_date, end_date, is_current, curr_def, sy_name, created_at)
                VALUES (?, ?, ?, ?, 1, 1, ?, NOW())
            ");
            
            if ($stmt === false) {
                $conn->rollback();
                error_log("SQL Prepare Error (insert new): " . $conn->error);
                return [
                    'success' => false,
                    'message' => 'Failed to prepare INSERT query: ' . $conn->error,
                    'progressed' => false
                ];
            }
            
            $syName = $next['sy_year'] . ' - ' . $next['semester'];
            
            if (!$stmt->bind_param("sssss", $next['sy_year'], $next['semester'], $dates['start_date'], $dates['end_date'], $syName) || !$stmt->execute()) {
                $conn->rollback();
                error_log("SQL Execute Error (insert new): " . $stmt->error);
                return [
                    'success' => false,
                    'message' => 'Failed to insert new semester: ' . $stmt->error,
                    'progressed' => false
                ];
            }
            
            $newSyId = $conn->insert_id;
        }
        
        // Deactivate previous semester
        $stmt = $conn->prepare("UPDATE schoolyear SET is_current = 0 WHERE sy_id = ?");
        
        if ($stmt === false || !$stmt->bind_param("i", $current['sy_id']) || !$stmt->execute()) {
            $conn->rollback();
            error_log("SQL Error (deactivate previous): " . ($stmt ? $stmt->error : $conn->error));
            return [
                'success' => false,
                'message' => 'Failed to deactivate previous semester: ' . ($stmt ? $stmt->error : $conn->error),
                'progressed' => false
            ];
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Semester auto-progressed successfully.',
            'previous' => $current,
            'current' => [
                'sy_id' => $newSyId,
                'sy_year' => $next['sy_year'],
                'semester' => $next['semester'],
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date']
            ],
            'progressed' => true
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error auto-progressing semester: ' . $e->getMessage(),
            'progressed' => false
        ];
    }
}

/**
 * Initialize current semester if none exists
 * 
 * @param mysqli|null $conn Database connection (optional, uses global if not provided)
 * @return array Result with success status
 */
function initializeCurrentSemester($conn = null) {
    global $conn;
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection not available'];
    }
    try {
        // Calculate current semester based on date
        require_once __DIR__ . '/evsu_academic_period.php';
        $academicPeriod = getEVSUAcademicPeriodArray();
        
        // Extract SY year and semester
        $syYear = $academicPeriod['academic_year']; // "2025 - 2026"
        $semester = $academicPeriod['semester']; // "1st Semester", "2nd Semester", or "Mid-Year"
        
        // Calculate dates
        $dates = calculateSemesterDates($syYear, $semester);
        
        // Check if record exists
        $stmt = $conn->prepare("
            SELECT sy_id FROM schoolyear
            WHERE sy_year = ? AND semester = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $syYear, $semester);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing
            $stmt = $conn->prepare("
                UPDATE schoolyear
                SET is_current = 1,
                    start_date = ?,
                    end_date = ?,
                    updated_at = NOW()
                WHERE sy_id = ?
            ");
            $stmt->bind_param("ssi", $dates['start_date'], $dates['end_date'], $existing['sy_id']);
            $stmt->execute();
        } else {
            // Create new
            $stmt = $conn->prepare("
                INSERT INTO schoolyear (sy_year, semester, start_date, end_date, is_current, curr_def, sy_name, created_at)
                VALUES (?, ?, ?, ?, 1, 1, ?, NOW())
            ");
            $syName = $syYear . ' - ' . $semester;
            $stmt->bind_param("sssss", $syYear, $semester, $dates['start_date'], $dates['end_date'], $syName);
            $stmt->execute();
        }
        
        return [
            'success' => true,
            'message' => 'Current semester initialized.',
            'current' => [
                'sy_year' => $syYear,
                'semester' => $semester,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date']
            ],
            'progressed' => false
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error initializing semester: ' . $e->getMessage(),
            'progressed' => false
        ];
    }
}

/**
 * Get current active semester from database
 * Auto-progresses if needed
 * 
 * @param mysqli|null $conn Database connection (optional, uses global if not provided)
 * @return array Current semester data or null
 */
function getCurrentActiveSemester($conn = null) {
    global $conn;
    if (!$conn) {
        return null;
    }
    // First, check and auto-progress if needed
    autoProgressSemester($conn);
    
    // Get current active semester
    $stmt = $conn->prepare("
        SELECT sy_id, sy_year, semester, start_date, end_date, is_current
        FROM schoolyear
        WHERE is_current = 1
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

