<?php
// Prevent any output before JSON
ob_start();

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/program_year_level_curriculum.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Allow both view and manage permissions (moderators can view, admins can manage)
if (!hasPermission('view_schedules') && !hasPermission('manage_schedules') && !hasPermission('assign_schedules')) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Filter values
$syFilter = $_GET['sy'] ?? '';
$termFilter = $_GET['term'] ?? '';
$programFilter = $_GET['program'] ?? '';
$yearLevelFilter = $_GET['year_level'] ?? '';
$sectionFilter = $_GET['section'] ?? '';

try {
    // Base query to get subjects that don't have schedules
    // Get subjects from curriculum, and check if they have schedules
    $query = "
        SELECT DISTINCT
            s.subj_id,
            s.subj_code,
            s.subj_desc,
            s.subj_unit,
            s.subj_lec,
            s.subj_lab,
            s.subj_category,
            curr.curr_id,
            curr.curr_name,
            curr.dept_id
        FROM subject s
        JOIN curriculum curr ON s.curr_id = curr.curr_id
        WHERE 1=1
    ";
    
    $whereClauses = [];
    $params = [];
    $types = '';
    
    // Department filtering
    if (!$isAdminSupport && $userDeptId > 0) {
        $whereClauses[] = "curr.dept_id = ?";
        $params[] = $userDeptId;
        $types .= 'i';
    }
    
    // Filter by section if provided (this takes priority and gets section's curriculum/program)
    if (!empty($sectionFilter)) {
        // First, get section's basic info (program, year level, department)
        $sec_stmt = $conn->prepare("
            SELECT c.curr_id as class_curr_id, c.dept_id, sec.program_id, cl.class_lvl as year_level, cl.class_id
            FROM section sec
            JOIN class cl ON sec.class_id = cl.class_id
            JOIN curriculum c ON cl.curr_id = c.curr_id
            WHERE sec.sec_id = ?
        ");
        $sec_stmt->bind_param("i", $sectionFilter);
        $sec_stmt->execute();
        $sec_result = $sec_stmt->get_result();
        $section = $sec_result->fetch_assoc();
        $sec_stmt->close();
        
        if ($section) {
            // IMPORTANT: Use program_year_level_curriculum mapping table to get the correct curriculum
            // This ensures unassigned subjects match what's shown in the Subjects tab
            $curr_id_to_use = null;
            
            // Check if program_year_level_curriculum table exists and has a mapping
            $table_check = $conn->query("SHOW TABLES LIKE 'program_year_level_curriculum'");
            $mapping_table_exists = $table_check && $table_check->num_rows > 0;
            if ($table_check) $table_check->close();
            
            if ($mapping_table_exists && !empty($section['program_id']) && !empty($section['year_level'])) {
                $curr_id_to_use = pylcurriculum_get_curr_id($conn, (int)$section['program_id'], (int)$section['year_level']);
                if ($curr_id_to_use !== null) {
                    error_log("Section {$sectionFilter} - Using curriculum from program_year_level_curriculum: {$curr_id_to_use} (program: {$section['program_id']}, year_level: {$section['year_level']})");
                }
            }
            
            // Fallback to class's curriculum if mapping table doesn't have entry (backward compatibility)
            if ($curr_id_to_use === null && !empty($section['class_curr_id'])) {
                $curr_id_to_use = (int)$section['class_curr_id'];
                error_log("Section {$sectionFilter} - Using curriculum from class (fallback): {$curr_id_to_use}");
            }
            
            // Filter by curriculum (from mapping table or class fallback)
            if ($curr_id_to_use !== null) {
                $whereClauses[] = "s.curr_id = ?";
                $params[] = $curr_id_to_use;
                $types .= 'i';
                error_log("Section {$sectionFilter} - Filtering subjects by curriculum ID: {$curr_id_to_use}");
            } else {
                // If no curriculum found, fall back to department and year level filtering only
                error_log("Warning: Section {$sectionFilter} has no curriculum assigned - using department and year level filter only");
                // Don't add curriculum filter, but still filter by department if available
                if (!empty($section['dept_id']) && !$isAdminSupport) {
                    // Department filter will be added below
                }
            }
            
            // Also filter by department from section (for security)
            if (!$isAdminSupport && !empty($section['dept_id'])) {
                // Filter by department
                $whereClauses[] = "curr.dept_id = ?";
                $params[] = $section['dept_id'];
                $types .= 'i';
                error_log("Filtering by department ID: {$section['dept_id']}");
            }
            
            // Filter by section's program if available (subjects in the same program)
            // If a program filter is provided, use that instead to respect user's selection
            if (!empty($programFilter)) {
                // When program filter is selected, only show subjects from that program (exclude NULL)
                $whereClauses[] = "s.program_id = ?";
                $params[] = $programFilter;
                $types .= 'i';
                error_log("Filtering by program ID from filter: {$programFilter}");
            } elseif (!empty($section['program_id'])) {
                // If no program filter, allow section's program or NULL (shared subjects)
                $whereClauses[] = "(s.program_id = ? OR s.program_id IS NULL)";
                $params[] = $section['program_id'];
                $types .= 'i';
                error_log("Filtering by section's program ID: {$section['program_id']} or NULL");
            }
            
            // Filter by section's year level (subjects for the same year level)
            // But allow year level filter to override if provided
            if (!empty($yearLevelFilter)) {
                // Use year level from filter if provided (takes priority)
                $whereClauses[] = "s.subj_lvl = ?";
                $params[] = $yearLevelFilter;
                $types .= 'i';
                error_log("Filtering by year level from filter: {$yearLevelFilter}");
            } elseif (!empty($section['year_level'])) {
                // Otherwise use section's year level
                $whereClauses[] = "s.subj_lvl = ?";
                $params[] = $section['year_level'];
                $types .= 'i';
                error_log("Filtering by year level from section: {$section['year_level']}");
            }
            
            // Filter by term if provided (subjects for the same term)
            // This allows filtering unassigned subjects by term
            if (!empty($termFilter)) {
                $whereClauses[] = "s.subj_term = ?";
                $params[] = $termFilter;
                $types .= 'i';
                error_log("Filtering by term: {$termFilter}");
            }
            
            // Log section details for debugging
            error_log("Section details - curr_id (from mapping/class): " . ($curr_id_to_use ?? 'NULL') . 
                     ", class_curr_id: " . ($section['class_curr_id'] ?? 'NULL') .
                     ", dept_id: " . ($section['dept_id'] ?? 'NULL') . 
                     ", program_id: " . ($section['program_id'] ?? 'NULL') . 
                     ", year_level: " . ($section['year_level'] ?? 'NULL') .
                     ", term_filter: " . ($termFilter ?? 'NULL') .
                     ", year_level_filter: " . ($yearLevelFilter ?? 'NULL'));
        } else {
            // Section not found - log and return empty or use fallback filters
            error_log("Warning: Section {$sectionFilter} not found in database");
        }
    }
    
    // Filter by program if provided (when section is not provided, or to override section's program)
    if (!empty($programFilter) && empty($sectionFilter)) {
        // Filter by program if provided (only if section not provided)
        // Get program's department
        $prog_stmt = $conn->prepare("SELECT dept_id FROM program WHERE program_id = ?");
        $prog_stmt->bind_param("i", $programFilter);
        $prog_stmt->execute();
        $prog_result = $prog_stmt->get_result();
        $program = $prog_result->fetch_assoc();
        $prog_stmt->close();
        
        if ($program) {
            $whereClauses[] = "curr.dept_id = ?";
            $params[] = $program['dept_id'];
            $types .= 'i';
        }
        
        // Also filter by program_id if provided
        $whereClauses[] = "s.program_id = ?";
        $params[] = $programFilter;
        $types .= 'i';
    }
    
    // Filter by year level if provided (and section not provided)
    if (empty($sectionFilter) && !empty($yearLevelFilter)) {
        $whereClauses[] = "s.subj_lvl = ?";
        $params[] = $yearLevelFilter;
        $types .= 'i';
        error_log("Filtering by year level (no section): {$yearLevelFilter}");
    }
    
    // Filter by term if provided (and section not provided, or to override section's term)
    // Note: When section is provided, term filter is applied above in the section block
    if (empty($sectionFilter) && !empty($termFilter)) {
        $whereClauses[] = "s.subj_term = ?";
        $params[] = $termFilter;
        $types .= 'i';
        error_log("Filtering by term (no section): {$termFilter}");
    }
    
    // Build conditions to check if total assigned hours meet required hours
    // Logic: A subject should be in unassigned if:
    // - It has both Lec and Lab hours: if total Lec hours < subj_lec OR total Lab hours < subj_lab
    // - It has only Lec hours: if total Lec hours < subj_lec
    // - It has only Lab hours: if total Lab hours < subj_lab
    // This allows subjects with multiple Lec/Lab classes per week to stay unassigned until ALL required hours are scheduled
    $notExistsConditions = [];
    
    if (!empty($sectionFilter)) {
        // For a specific section: subjects that don't have enough scheduled hours for THIS section
        // Build condition based on whether subject requires Lec, Lab, or both
        
        // Build base WHERE clause for schedule checks
        $scheduleWhere = "sch.subj_id = s.subj_id AND sch.sec_id = ? AND sch.schd_status = 'Active'";
        $scheduleParams = [$sectionFilter];
        $scheduleTypes = 'i';
        
        if (!empty($syFilter)) {
            $scheduleWhere .= " AND sch.sy_id = ?";
            $scheduleParams[] = $syFilter;
            $scheduleTypes .= 'i';
        }
        if (!empty($termFilter)) {
            $scheduleWhere .= " AND sch.schd_term = ?";
            $scheduleParams[] = $termFilter;
            $scheduleTypes .= 'i';
        }
        
        // Check if subject requires both Lec and Lab
        // Subject is unassigned if total Lec hours < subj_lec OR total Lab hours < subj_lab
        // IMPORTANT: Also check schedule count - if subj_lec >= 3, require at least 2 Lec schedules
        // This handles subjects that need multiple classes per week (e.g., IT 343A needs 2 classes)
        $bothRequiredCondition = "(
            (s.subj_lec > 0 AND s.subj_lab > 0) AND (
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ), 0) < s.subj_lec
                OR
                (s.subj_lec >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ) < 2)
                OR
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ), 0) < s.subj_lab
                OR
                (s.subj_lab >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ) < 2)
            )
        )";
        
        // Check if subject requires only Lec
        // IMPORTANT: If subj_lec >= 3, require at least 2 Lec schedules (handles subjects needing multiple classes)
        $lecOnlyCondition = "(
            (s.subj_lec > 0 AND s.subj_lab = 0) AND (
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ), 0) < s.subj_lec
                OR
                (s.subj_lec >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ) < 2)
            )
        )";
        
        // Check if subject requires only Lab
        // IMPORTANT: If subj_lab >= 3, require at least 2 Lab schedules (handles subjects needing multiple classes)
        $labOnlyCondition = "(
            (s.subj_lec = 0 AND s.subj_lab > 0) AND (
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ), 0) < s.subj_lab
                OR
                (s.subj_lab >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ) < 2)
            )
        )";
        
        // Combine all conditions with OR
        $notExistsConditions[] = "({$bothRequiredCondition} OR {$lecOnlyCondition} OR {$labOnlyCondition})";
        
        // Add schedule parameters to main params array
        // Parameters are needed for each subquery that uses $scheduleWhere:
        // - bothRequiredCondition: Lec SUM (1 set) + Lec COUNT (1 set) + Lab SUM (1 set) + Lab COUNT (1 set) = 4 sets
        // - lecOnlyCondition: Lec SUM (1 set) + Lec COUNT (1 set) = 2 sets
        // - labOnlyCondition: Lab SUM (1 set) + Lab COUNT (1 set) = 2 sets
        // Total: 8 sets needed (all possible paths)
        // Add parameters for Lec SUM check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lec COUNT check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab SUM check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab COUNT check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lec SUM check in lecOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lec COUNT check in lecOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab SUM check in labOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab COUNT check in labOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        
    } else {
        // General check: subjects that don't have enough scheduled hours
        // Build condition based on whether subject requires Lec, Lab, or both
        
        // Build base WHERE clause for schedule checks
        $scheduleWhere = "sch.subj_id = s.subj_id AND sch.schd_status = 'Active'";
        $scheduleParams = [];
        $scheduleTypes = '';
        
        if (!empty($syFilter)) {
            $scheduleWhere .= " AND sch.sy_id = ?";
            $scheduleParams[] = $syFilter;
            $scheduleTypes .= 'i';
        }
        if (!empty($termFilter)) {
            $scheduleWhere .= " AND sch.schd_term = ?";
            $scheduleParams[] = $termFilter;
            $scheduleTypes .= 'i';
        }
        
        // Check if subject requires both Lec and Lab
        // Subject is unassigned if total Lec hours < subj_lec OR total Lab hours < subj_lab
        // IMPORTANT: Also check schedule count - if subj_lec >= 3, require at least 2 Lec schedules
        // This handles subjects that need multiple classes per week (e.g., IT 343A needs 2 classes)
        $bothRequiredCondition = "(
            (s.subj_lec > 0 AND s.subj_lab > 0) AND (
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ), 0) < s.subj_lec
                OR
                (s.subj_lec >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ) < 2)
                OR
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ), 0) < s.subj_lab
                OR
                (s.subj_lab >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ) < 2)
            )
        )";
        
        // Check if subject requires only Lec
        // IMPORTANT: If subj_lec >= 3, require at least 2 Lec schedules (handles subjects needing multiple classes)
        $lecOnlyCondition = "(
            (s.subj_lec > 0 AND s.subj_lab = 0) AND (
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ), 0) < s.subj_lec
                OR
                (s.subj_lec >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lec'
                ) < 2)
            )
        )";
        
        // Check if subject requires only Lab
        // IMPORTANT: If subj_lab >= 3, require at least 2 Lab schedules (handles subjects needing multiple classes)
        $labOnlyCondition = "(
            (s.subj_lec = 0 AND s.subj_lab > 0) AND (
                COALESCE((
                    SELECT SUM(sch.schd_min) / 60.0 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ), 0) < s.subj_lab
                OR
                (s.subj_lab >= 3 AND (
                    SELECT COUNT(*) 
                    FROM schedule sch 
                    WHERE {$scheduleWhere} AND sch.schd_type = 'Lab'
                ) < 2)
            )
        )";
        
        // Combine all conditions with OR
        $notExistsConditions[] = "({$bothRequiredCondition} OR {$lecOnlyCondition} OR {$labOnlyCondition})";
        
        // Add schedule parameters to main params array
        // Parameters are needed for each subquery that uses $scheduleWhere:
        // - bothRequiredCondition: Lec SUM (1 set) + Lec COUNT (1 set) + Lab SUM (1 set) + Lab COUNT (1 set) = 4 sets
        // - lecOnlyCondition: Lec SUM (1 set) + Lec COUNT (1 set) = 2 sets
        // - labOnlyCondition: Lab SUM (1 set) + Lab COUNT (1 set) = 2 sets
        // Total: 8 sets needed (all possible paths)
        // Add parameters for Lec SUM check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lec COUNT check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab SUM check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab COUNT check in bothRequiredCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lec SUM check in lecOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lec COUNT check in lecOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab SUM check in labOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
        // Add parameters for Lab COUNT check in labOnlyCondition
        foreach ($scheduleParams as $param) {
            $params[] = $param;
        }
        $types .= $scheduleTypes;
    }
    
    // Combine all conditions
    if (count($whereClauses) > 0) {
        $query .= " AND " . implode(' AND ', $whereClauses);
    }
    
    // Add NOT EXISTS conditions
    if (count($notExistsConditions) > 0) {
        $query .= " AND " . implode(' AND ', $notExistsConditions);
    }
    
    $query .= " ORDER BY s.subj_code, s.subj_desc";
    
    // Debug: Log the query and parameters
    error_log("Unassigned Subjects Query: " . $query);
    error_log("Unassigned Subjects Params: " . json_encode($params));
    error_log("Unassigned Subjects Types: " . $types);
    
    // Debug: If section filter is provided, check what schedules exist for debugging
    if (!empty($sectionFilter)) {
        $debugQuery = "
            SELECT 
                sch.schd_id,
                sch.subj_id,
                subj.subj_code,
                subj.subj_lec,
                subj.subj_lab,
                sch.schd_type,
                sch.schd_min,
                sch.schd_min / 60.0 as schd_hours,
                sch.schd_status,
                sch.schd_term,
                sch.sy_id
            FROM schedule sch
            JOIN subject subj ON sch.subj_id = subj.subj_id
            WHERE sch.sec_id = ?
        ";
        $debugParams = [$sectionFilter];
        $debugTypes = 'i';
        
        if (!empty($syFilter)) {
            $debugQuery .= " AND sch.sy_id = ?";
            $debugParams[] = $syFilter;
            $debugTypes .= 'i';
        }
        if (!empty($termFilter)) {
            $debugQuery .= " AND sch.schd_term = ?";
            $debugParams[] = $termFilter;
            $debugTypes .= 'i';
        }
        
        $debugQuery .= " ORDER BY subj.subj_code, sch.schd_type";
        
        $debugStmt = $conn->prepare($debugQuery);
        if ($debugStmt) {
            $debugStmt->bind_param($debugTypes, ...$debugParams);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            $debugSchedules = $debugResult->fetch_all(MYSQLI_ASSOC);
            $debugStmt->close();
            
            error_log("Debug - Schedules for section {$sectionFilter}: " . json_encode($debugSchedules));
            
            // Group by subject and calculate totals
            $subjectTotals = [];
            foreach ($debugSchedules as $sch) {
                $subjCode = $sch['subj_code'];
                if (!isset($subjectTotals[$subjCode])) {
                    $subjectTotals[$subjCode] = [
                        'subj_lec' => $sch['subj_lec'],
                        'subj_lab' => $sch['subj_lab'],
                        'lec_hours' => 0,
                        'lab_hours' => 0,
                        'lec_count' => 0,
                        'lab_count' => 0
                    ];
                }
                if ($sch['schd_type'] === 'Lec') {
                    $subjectTotals[$subjCode]['lec_hours'] += $sch['schd_hours'];
                    $subjectTotals[$subjCode]['lec_count']++;
                } elseif ($sch['schd_type'] === 'Lab') {
                    $subjectTotals[$subjCode]['lab_hours'] += $sch['schd_hours'];
                    $subjectTotals[$subjCode]['lab_count']++;
                }
            }
            
            foreach ($subjectTotals as $subjCode => $totals) {
                error_log("Debug - Subject {$subjCode}: Required Lec={$totals['subj_lec']}, Lab={$totals['subj_lab']}, Scheduled Lec={$totals['lec_hours']} ({$totals['lec_count']} schedules), Lab={$totals['lab_hours']} ({$totals['lab_count']} schedules)");
            }
        }
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    error_log("Unassigned Subjects Found: " . count($data));
    
    // If no results and section filter is provided, collect debug info for JSON response
    $debug_info = null;
    if (count($data) === 0 && !empty($sectionFilter) && isset($section)) {
        error_log("=== DEBUG: No unassigned subjects found for section {$sectionFilter} ===");
        error_log("Section info: " . json_encode($section));
        error_log("Filters applied: SY={$syFilter}, Term={$termFilter}, Program={$programFilter}, YearLevel={$yearLevelFilter}");
        
        // Initialize debug info array
        $debug_info = [
            'section_id' => $sectionFilter,
            'filters_applied' => [
                'sy' => $syFilter,
                'term' => $termFilter,
                'program' => $programFilter,
                'year_level' => $yearLevelFilter
            ],
            'section_info' => $section
        ];
        
        // Check if there are any subjects in the section's curriculum
        // Use the same logic as above to get the correct curriculum
        $debug_curr_id = null;
        
        // Store both curricula for comparison
        $class_curr_id = !empty($section['class_curr_id']) ? (int)$section['class_curr_id'] : null;
        $mapping_curr_id = null;
        
        // Check if program_year_level_curriculum table exists
        $debug_table_check = $conn->query("SHOW TABLES LIKE 'program_year_level_curriculum'");
        $debug_mapping_table_exists = $debug_table_check && $debug_table_check->num_rows > 0;
        if ($debug_table_check) $debug_table_check->close();
        
        if ($debug_mapping_table_exists && !empty($section['program_id']) && !empty($section['year_level'])) {
            $mapping_curr_id = pylcurriculum_get_curr_id($conn, (int)$section['program_id'], (int)$section['year_level']);
            if ($mapping_curr_id !== null) {
                error_log("Using curriculum from mapping table: {$mapping_curr_id}");
            }
        }
        
        // Determine which curriculum to use for the query (prioritize mapping table)
        $debug_curr_id = $mapping_curr_id ?? $class_curr_id;
        if ($debug_curr_id === null && !empty($section['class_curr_id'])) {
            $debug_curr_id = (int)$section['class_curr_id'];
            error_log("Using curriculum from class (fallback): {$debug_curr_id}");
        }
        
        // CURRICULUM COMPARISON: Always check both curricula when they exist
        $debug_info['curriculum_analysis'] = [
            'class_curriculum_id' => $class_curr_id,
            'mapping_curriculum_id' => $mapping_curr_id,
            'curriculum_used_for_query' => $debug_curr_id,
            'note' => ($class_curr_id !== null && $mapping_curr_id !== null && $class_curr_id !== $mapping_curr_id) 
                    ? 'Curricula differ - using mapping table curriculum for query' 
                    : (($mapping_curr_id !== null) ? 'Using mapping table curriculum' : 'Using class curriculum (fallback)')
        ];
        
        // CURRICULUM COMPARISON: Check both curricula if they differ OR always check class curriculum for completeness
        if ($class_curr_id !== null && $mapping_curr_id !== null && $class_curr_id !== $mapping_curr_id) {
            $debug_info['curriculum_comparison'] = [
                'class_curriculum_id' => $class_curr_id,
                'mapping_curriculum_id' => $mapping_curr_id,
                'curriculum_used' => $mapping_curr_id,
                'note' => 'Curricula differ - using mapping table curriculum for query'
            ];
            
            // Check both curricula for Year 1 Term 2 subjects
            foreach ([$class_curr_id, $mapping_curr_id] as $curr_id_to_check) {
                $curr_name = ($curr_id_to_check == $class_curr_id) ? 'class_curriculum' : 'mapping_curriculum';
                
                $comp_stmt = $conn->prepare("SELECT curr_name FROM curriculum WHERE curr_id = ?");
                $comp_stmt->bind_param("i", $curr_id_to_check);
                $comp_stmt->execute();
                $comp_result = $comp_stmt->get_result();
                $comp_data = $comp_result->fetch_assoc();
                $comp_stmt->close();
                
                $curr_info = [
                    'curriculum_id' => $curr_id_to_check,
                    'curriculum_name' => $comp_data['curr_name'] ?? 'Unknown'
                ];
                
                // Total subjects
                $comp_total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ?");
                $comp_total_stmt->bind_param("i", $curr_id_to_check);
                $comp_total_stmt->execute();
                $comp_total_result = $comp_total_stmt->get_result()->fetch_assoc();
                $comp_total_stmt->close();
                $curr_info['total_subjects'] = (int)$comp_total_result['total'];
                
                // Year 1 subjects
                if (!empty($section['year_level'])) {
                    $comp_yl_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ?");
                    $comp_yl_stmt->bind_param("ii", $curr_id_to_check, $section['year_level']);
                    $comp_yl_stmt->execute();
                    $comp_yl_result = $comp_yl_stmt->get_result()->fetch_assoc();
                    $comp_yl_stmt->close();
                    $curr_info['year_level_' . $section['year_level'] . '_total'] = (int)$comp_yl_result['total'];
                    
                    // Year 1 Term 2 subjects (if term filter provided)
                    if (!empty($termFilter)) {
                        $comp_yl_term_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ? AND subj_term = ?");
                        $comp_yl_term_stmt->bind_param("iii", $curr_id_to_check, $section['year_level'], $termFilter);
                        $comp_yl_term_stmt->execute();
                        $comp_yl_term_result = $comp_yl_term_stmt->get_result()->fetch_assoc();
                        $comp_yl_term_stmt->close();
                        $curr_info['year_level_' . $section['year_level'] . '_term_' . $termFilter . '_total'] = (int)$comp_yl_term_result['total'];
                        
                        // List Year 1 Term 2 subjects in this curriculum
                        $comp_list_stmt = $conn->prepare("
                            SELECT subj_id, subj_code, subj_desc, subj_lec, subj_lab, subj_term, subj_category 
                            FROM subject 
                            WHERE curr_id = ? AND subj_lvl = ? AND subj_term = ?
                            ORDER BY subj_code
                        ");
                        $comp_list_stmt->bind_param("iii", $curr_id_to_check, $section['year_level'], $termFilter);
                        $comp_list_stmt->execute();
                        $comp_list_result = $comp_list_stmt->get_result();
                        $curr_info['year_level_' . $section['year_level'] . '_term_' . $termFilter . '_subjects'] = $comp_list_result->fetch_all(MYSQLI_ASSOC);
                        $comp_list_stmt->close();
                    }
                    
                    // Term breakdown
                    $comp_breakdown_stmt = $conn->prepare("SELECT subj_term, COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ? GROUP BY subj_term ORDER BY subj_term");
                    $comp_breakdown_stmt->bind_param("ii", $curr_id_to_check, $section['year_level']);
                    $comp_breakdown_stmt->execute();
                    $comp_breakdown_result = $comp_breakdown_stmt->get_result();
                    $comp_term_breakdown = [];
                    while ($row = $comp_breakdown_result->fetch_assoc()) {
                        $comp_term_breakdown['term_' . $row['subj_term']] = (int)$row['total'];
                    }
                    $comp_breakdown_stmt->close();
                    $curr_info['term_breakdown'] = $comp_term_breakdown;
                }
                
                $debug_info['curriculum_comparison'][$curr_name] = $curr_info;
            }
            
            error_log("CURRICULUM COMPARISON: Class curriculum ({$class_curr_id}) vs Mapping curriculum ({$mapping_curr_id})");
        }
        
        // ALWAYS check class curriculum (38) for Year 1 Term 2 subjects if it differs from mapping curriculum
        // This helps identify if there's a data consistency issue
        if ($class_curr_id !== null && $class_curr_id !== $debug_curr_id && !empty($section['year_level']) && !empty($termFilter)) {
            // Class curriculum differs from what's being used - check it separately
            $class_curr_name_stmt = $conn->prepare("SELECT curr_name FROM curriculum WHERE curr_id = ?");
            $class_curr_name_stmt->bind_param("i", $class_curr_id);
            $class_curr_name_stmt->execute();
            $class_curr_name_result = $class_curr_name_stmt->get_result();
            $class_curr_name_data = $class_curr_name_result->fetch_assoc();
            $class_curr_name_stmt->close();
            
            $class_curr_info = [
                'curriculum_id' => $class_curr_id,
                'curriculum_name' => $class_curr_name_data['curr_name'] ?? 'Unknown',
                'note' => 'This is the section\'s class curriculum (different from mapping table)'
            ];
            
            // Check Year 1 Term 2 subjects in class curriculum
            $class_term_check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ? AND subj_term = ?");
            $class_term_check_stmt->bind_param("iii", $class_curr_id, $section['year_level'], $termFilter);
            $class_term_check_stmt->execute();
            $class_term_check_result = $class_term_check_stmt->get_result()->fetch_assoc();
            $class_term_check_stmt->close();
            $class_curr_info['year_level_' . $section['year_level'] . '_term_' . $termFilter . '_total'] = (int)$class_term_check_result['total'];
            
            // List Year 1 Term 2 subjects in class curriculum
            $class_list_stmt = $conn->prepare("
                SELECT subj_id, subj_code, subj_desc, subj_lec, subj_lab, subj_term, subj_category 
                FROM subject 
                WHERE curr_id = ? AND subj_lvl = ? AND subj_term = ?
                ORDER BY subj_code
            ");
            $class_list_stmt->bind_param("iii", $class_curr_id, $section['year_level'], $termFilter);
            $class_list_stmt->execute();
            $class_list_result = $class_list_stmt->get_result();
            $class_curr_info['year_level_' . $section['year_level'] . '_term_' . $termFilter . '_subjects'] = $class_list_result->fetch_all(MYSQLI_ASSOC);
            $class_list_stmt->close();
            
            // Term breakdown for class curriculum
            $class_breakdown_stmt = $conn->prepare("SELECT subj_term, COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ? GROUP BY subj_term ORDER BY subj_term");
            $class_breakdown_stmt->bind_param("ii", $class_curr_id, $section['year_level']);
            $class_breakdown_stmt->execute();
            $class_breakdown_result = $class_breakdown_stmt->get_result();
            $class_term_breakdown = [];
            while ($row = $class_breakdown_result->fetch_assoc()) {
                $class_term_breakdown['term_' . $row['subj_term']] = (int)$row['total'];
            }
            $class_breakdown_stmt->close();
            $class_curr_info['term_breakdown'] = $class_term_breakdown;
            
            $debug_info['class_curriculum_analysis'] = $class_curr_info;
            
            error_log("CLASS CURRICULUM ({$class_curr_id}) ANALYSIS: Year {$section['year_level']} Term {$termFilter} subjects = " . $class_curr_info['year_level_' . $section['year_level'] . '_term_' . $termFilter . '_total']);
            
            if ($class_curr_info['year_level_' . $section['year_level'] . '_term_' . $termFilter . '_total'] > 0) {
                error_log("WARNING: Class curriculum ({$class_curr_id}) HAS Year {$section['year_level']} Term {$termFilter} subjects, but mapping curriculum ({$debug_curr_id}) does not!");
                error_log("This indicates a data consistency issue - subjects exist in class curriculum but not in mapping curriculum.");
            }
        }
        
        if ($debug_curr_id !== null) {
            // Total subjects in curriculum
            $check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ?");
            $check_stmt->bind_param("i", $debug_curr_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            $total_in_curr = (int)$check_result['total'];
            error_log("Total subjects in curriculum {$debug_curr_id}: " . $total_in_curr);
            $debug_info['curriculum_id'] = $debug_curr_id;
            $debug_info['total_subjects_in_curriculum'] = $total_in_curr;
            
            // Subjects with matching year level
            if (!empty($section['year_level'])) {
                $check_stmt2 = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ?");
                $check_stmt2->bind_param("ii", $debug_curr_id, $section['year_level']);
                $check_stmt2->execute();
                $check_result2 = $check_stmt2->get_result()->fetch_assoc();
                $check_stmt2->close();
                $total_year_level = (int)$check_result2['total'];
                error_log("Total subjects in curriculum {$debug_curr_id} with year level {$section['year_level']}: " . $total_year_level);
                $debug_info['subjects_year_level_' . $section['year_level']] = $total_year_level;
                
                // TERM-SPECIFIC CHECK: Subjects with matching year level + term filter
                if (!empty($termFilter)) {
                    $check_stmt3 = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ? AND subj_term = ?");
                    $check_stmt3->bind_param("iii", $debug_curr_id, $section['year_level'], $termFilter);
                    $check_stmt3->execute();
                    $check_result3 = $check_stmt3->get_result()->fetch_assoc();
                    $check_stmt3->close();
                    $total_year_term = (int)$check_result3['total'];
                    error_log("TERM FILTER CHECK: Total subjects in curriculum {$debug_curr_id} with year level {$section['year_level']} AND term {$termFilter}: " . $total_year_term);
                    $debug_info['subjects_year_level_' . $section['year_level'] . '_term_' . $termFilter] = $total_year_term;
                    
                    // List subjects that match year level + term
                    $list_stmt = $conn->prepare("SELECT subj_id, subj_code, subj_desc, subj_lec, subj_lab, subj_term FROM subject WHERE curr_id = ? AND subj_lvl = ? AND subj_term = ?");
                    $list_stmt->bind_param("iii", $debug_curr_id, $section['year_level'], $termFilter);
                    $list_stmt->execute();
                    $list_result = $list_stmt->get_result();
                    $matching_subjects = $list_result->fetch_all(MYSQLI_ASSOC);
                    $list_stmt->close();
                    error_log("Subjects matching year level {$section['year_level']} + term {$termFilter}: " . count($matching_subjects));
                    
                    $subject_analysis = [];
                    foreach ($matching_subjects as $subj) {
                        error_log("  - {$subj['subj_code']} ({$subj['subj_desc']}): Lec={$subj['subj_lec']}, Lab={$subj['subj_lab']}, Term={$subj['subj_term']}");
                        
                        $subj_analysis = [
                            'subj_id' => $subj['subj_id'],
                            'subj_code' => $subj['subj_code'],
                            'subj_desc' => $subj['subj_desc'],
                            'required_lec' => (int)$subj['subj_lec'],
                            'required_lab' => (int)$subj['subj_lab'],
                            'subj_term' => (int)$subj['subj_term']
                        ];
                        
                        // Check if this subject has schedules for this section, SY, and term
                        if (!empty($syFilter) && !empty($termFilter)) {
                            $sched_check_stmt = $conn->prepare("
                                SELECT 
                                    SUM(CASE WHEN schd_type = 'Lec' THEN schd_min / 60.0 ELSE 0 END) as lec_hours,
                                    SUM(CASE WHEN schd_type = 'Lab' THEN schd_min / 60.0 ELSE 0 END) as lab_hours,
                                    COUNT(CASE WHEN schd_type = 'Lec' THEN 1 END) as lec_count,
                                    COUNT(CASE WHEN schd_type = 'Lab' THEN 1 END) as lab_count
                                FROM schedule 
                                WHERE subj_id = ? AND sec_id = ? AND sy_id = ? AND schd_term = ? AND schd_status = 'Active'
                            ");
                            $sched_check_stmt->bind_param("iiii", $subj['subj_id'], $sectionFilter, $syFilter, $termFilter);
                            $sched_check_stmt->execute();
                            $sched_check_result = $sched_check_stmt->get_result();
                            $sched_totals = $sched_check_result->fetch_assoc();
                            $sched_check_stmt->close();
                            
                            if ($sched_totals) {
                                $lec_hours = (float)($sched_totals['lec_hours'] ?? 0);
                                $lab_hours = (float)($sched_totals['lab_hours'] ?? 0);
                                $lec_count = (int)($sched_totals['lec_count'] ?? 0);
                                $lab_count = (int)($sched_totals['lab_count'] ?? 0);
                                $req_lec = (int)$subj['subj_lec'];
                                $req_lab = (int)$subj['subj_lab'];
                                $req_lec_count_needed = ($req_lec >= 3) ? 2 : 1;
                                $req_lab_count_needed = ($req_lab >= 3) ? 2 : 1;
                                
                                $is_lec_ok = ($req_lec == 0 || ($lec_hours >= $req_lec && ($req_lec < 3 || $lec_count >= 2)));
                                $is_lab_ok = ($req_lab == 0 || ($lab_hours >= $req_lab && ($req_lab < 3 || $lab_count >= 2)));
                                $is_fully_scheduled = $is_lec_ok && $is_lab_ok;
                                
                                $subj_analysis['scheduled_lec_hours'] = $lec_hours;
                                $subj_analysis['scheduled_lab_hours'] = $lab_hours;
                                $subj_analysis['scheduled_lec_count'] = $lec_count;
                                $subj_analysis['scheduled_lab_count'] = $lab_count;
                                $subj_analysis['is_fully_scheduled'] = $is_fully_scheduled;
                                $subj_analysis['should_appear_as_unassigned'] = !$is_fully_scheduled;
                                $subj_analysis['reason'] = $is_fully_scheduled ? 'Fully scheduled' : 'Partially scheduled or unscheduled';
                                
                                error_log("    Schedule check (SY={$syFilter}, Term={$termFilter}):");
                                error_log("      Required: Lec={$req_lec}h ({$req_lec_count_needed} classes), Lab={$req_lab}h ({$req_lab_count_needed} classes)");
                                error_log("      Scheduled: Lec={$lec_hours}h ({$lec_count} classes), Lab={$lab_hours}h ({$lab_count} classes)");
                                error_log("      Status: " . ($is_fully_scheduled ? "FULLY SCHEDULED (excluded)" : "PARTIALLY SCHEDULED or UNSCHEDULED (should appear)"));
                            } else {
                                $subj_analysis['scheduled_lec_hours'] = 0;
                                $subj_analysis['scheduled_lab_hours'] = 0;
                                $subj_analysis['scheduled_lec_count'] = 0;
                                $subj_analysis['scheduled_lab_count'] = 0;
                                $subj_analysis['is_fully_scheduled'] = false;
                                $subj_analysis['should_appear_as_unassigned'] = true;
                                $subj_analysis['reason'] = 'No schedules found';
                                error_log("    No schedules found for this subject in SY {$syFilter}, Term {$termFilter} (subject should appear as unassigned)");
                            }
                        } else {
                            $subj_analysis['error'] = 'SY or Term filter not provided for schedule check';
                            error_log("    Cannot check schedules: SY or Term filter not provided");
                        }
                        
                        $subject_analysis[] = $subj_analysis;
                    }
                    
                    $debug_info['matching_subjects'] = $matching_subjects;
                    $debug_info['subject_analysis'] = $subject_analysis;
                    
                    // Summary
                    $unassigned_count = 0;
                    $fully_scheduled_count = 0;
                    foreach ($subject_analysis as $analysis) {
                        if (isset($analysis['should_appear_as_unassigned']) && $analysis['should_appear_as_unassigned']) {
                            $unassigned_count++;
                        } elseif (isset($analysis['is_fully_scheduled']) && $analysis['is_fully_scheduled']) {
                            $fully_scheduled_count++;
                        }
                    }
                    
                    $debug_info['summary'] = [
                        'total_matching_subjects' => count($matching_subjects),
                        'should_appear_as_unassigned' => $unassigned_count,
                        'fully_scheduled' => $fully_scheduled_count,
                        'expected_unassigned_count' => $unassigned_count
                    ];
                } else {
                    error_log("No term filter provided - cannot check term-specific subjects");
                    $debug_info['error'] = 'Term filter not provided';
                }
                
                // Breakdown by term for this year level
                $term_breakdown_stmt = $conn->prepare("SELECT subj_term, COUNT(*) as total FROM subject WHERE curr_id = ? AND subj_lvl = ? GROUP BY subj_term ORDER BY subj_term");
                $term_breakdown_stmt->bind_param("ii", $debug_curr_id, $section['year_level']);
                $term_breakdown_stmt->execute();
                $term_breakdown_result = $term_breakdown_stmt->get_result();
                $term_breakdown = [];
                while ($row = $term_breakdown_result->fetch_assoc()) {
                    $term_breakdown['term_' . $row['subj_term']] = (int)$row['total'];
                }
                $term_breakdown_stmt->close();
                error_log("Year level {$section['year_level']} subjects breakdown by term: " . json_encode($term_breakdown));
                $debug_info['term_breakdown'] = $term_breakdown;
            }
            
            // Check program filter
            if (!empty($section['program_id'])) {
                $prog_check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subject WHERE curr_id = ? AND (program_id = ? OR program_id IS NULL)");
                $prog_check_stmt->bind_param("ii", $debug_curr_id, $section['program_id']);
                $prog_check_stmt->execute();
                $prog_check_result = $prog_check_stmt->get_result()->fetch_assoc();
                $prog_check_stmt->close();
                $prog_total = (int)$prog_check_result['total'];
                error_log("Subjects matching curriculum {$debug_curr_id} and program {$section['program_id']} (or NULL): " . $prog_total);
                $debug_info['subjects_matching_program_' . $section['program_id']] = $prog_total;
            }
        } else {
            $debug_info['error'] = 'No curriculum found for this section';
        }
        
        // Check if there are any subjects at all
        $total_subjects = $conn->query("SELECT COUNT(*) as total FROM subject")->fetch_assoc()['total'];
        error_log("Total subjects in database: " . $total_subjects);
        $debug_info['total_subjects_in_database'] = (int)$total_subjects;
        
        // Check if there are any schedules at all
        $total_schedules = $conn->query("SELECT COUNT(*) as total FROM schedule WHERE schd_status = 'Active'")->fetch_assoc()['total'];
        error_log("Total active schedules in database: " . $total_schedules);
        $debug_info['total_active_schedules'] = (int)$total_schedules;
        
        error_log("=== END DEBUG ===");
    }
    
    // Ensure no output before JSON
    ob_clean();
    
    $response = [
        'success' => true,
        'data' => $data,
        'count' => count($data),
        'debug' => [
            'section_filter' => $sectionFilter,
            'query_params_count' => count($params),
            'where_clauses_count' => count($whereClauses),
            'section_info' => $section ?? null
        ]
    ];
    
    // Include detailed debug info if no results found (helps diagnose issues)
    if ($debug_info !== null) {
        $response['debug']['detailed_diagnostics'] = $debug_info;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
    
} catch (Exception $e) {
    error_log("Error in get_unassigned_subjects.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Ensure no output before JSON
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching unassigned subjects: ' . $e->getMessage(),
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

