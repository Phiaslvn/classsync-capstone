<?php
/**
 * Check Workload Conflict
 * 
 * This endpoint checks if assigning a new schedule would cause a workload conflict
 * for an instructor. It provides detailed analysis of current workload vs. limits.
 * 
 * Endpoint: admin/management/check_workload_conflict.php
 * Method: GET or POST
 * Parameters:
 *   - inst_id (required): The instructor ID
 *   - sy_id (required): School Year ID
 *   - schd_term (required): Term (1=First Sem, 2=Second Sem, 3=Summer)
 *   - new_schedule_minutes (optional): Minutes of the new schedule to check
 *   - exclude_schd_id (optional): Schedule ID to exclude from calculation (for updates)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
// Check if user is authenticated
if (!isset($_SESSION['acc_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Get parameters from GET or POST
$inst_id = isset($_REQUEST['inst_id']) ? (int)$_REQUEST['inst_id'] : 0;
$sy_id = isset($_REQUEST['sy_id']) ? (int)$_REQUEST['sy_id'] : 0;
$schd_term = isset($_REQUEST['schd_term']) ? (int)$_REQUEST['schd_term'] : 0;
$new_schedule_minutes = isset($_REQUEST['new_schedule_minutes']) ? (float)$_REQUEST['new_schedule_minutes'] : 0;
$exclude_schd_id = isset($_REQUEST['exclude_schd_id']) ? (int)$_REQUEST['exclude_schd_id'] : 0;

// Validation
if ($inst_id <= 0 || $sy_id <= 0 || $schd_term <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters. inst_id, sy_id, and schd_term are required.'
    ]);
    exit;
}

try {
    // Get instructor details
    $stmt = $conn->prepare("
        SELECT 
            inst_id,
            CONCAT(inst_fname, ' ', inst_lname) as instructor_name,
            inst_status,
            instruction_hours,
            administration_hours,
            research_hours,
            extension_hours,
            instructional_functions_hours,
            consultation_hours,
            rank,
            designation
        FROM instructor 
        WHERE inst_id = ?
    ");
    $stmt->bind_param('i', $inst_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Instructor not found'
        ]);
        exit;
    }
    
    $instructor = $result->fetch_assoc();
    $stmt->close();
    
    // Get detailed teaching workload breakdown (Regular vs Overload, Lec vs Lab)
    $sql = "SELECT 
                s.schd_id,
                s.schd_type,
                s.is_overtime,
                s.schd_min,
                subj.subj_code,
                subj.subj_desc,
                subj.subj_lec,
                subj.subj_lab,
                subj.subj_unit,
                sec.sec_name,
                s.schd_day,
                TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time,
                TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time,
                r.rm_name
            FROM schedule s
            JOIN subject subj ON s.subj_id = subj.subj_id
            JOIN section sec ON s.sec_id = sec.sec_id
            JOIN room r ON s.rm_id = r.rm_id
            WHERE s.inst_id = ? 
            AND s.sy_id = ? 
            AND s.schd_term = ? 
            AND s.schd_status = 'Active'";
    
    $params = [$inst_id, $sy_id, $schd_term];
    $types = "iii";
    
    if ($exclude_schd_id > 0) {
        $sql .= " AND s.schd_id != ?";
        $params[] = $exclude_schd_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY s.is_overtime, subj.subj_code, s.schd_day, s.schd_start";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $schedules_result = $stmt->get_result();
    $schedules = [];
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();
    
    // Calculate workload breakdown
    $regular_lec_hours = 0;
    $regular_lab_hours = 0;
    $regular_total_minutes = 0;
    $regular_subject_units = 0;
    $regular_schedules = [];
    
    $overload_lec_hours = 0;
    $overload_lab_hours = 0;
    $overload_total_minutes = 0;
    $overload_subject_units = 0;
    $overload_schedules = [];
    
    foreach ($schedules as $schedule) {
        $is_overtime = $schedule['is_overtime'] === 'Yes';
        $schd_type = $schedule['schd_type'] ?? 'Lec';
        $schd_min = (float)($schedule['schd_min'] ?? 0);
        $schd_hours = round($schd_min / 60, 2);
        
        // Determine Lec/Lab hours based on schedule type and subject hours
        $lec_hours = 0;
        $lab_hours = 0;
        
        if ($schd_type === 'Lec') {
            // For Lec schedules, use subj_lec hours (not schd_min)
            $lec_hours = (int)($schedule['subj_lec'] ?? 0);
        } elseif ($schd_type === 'Lab') {
            // For Lab schedules, use subj_lab hours (not schd_min)
            $lab_hours = (int)($schedule['subj_lab'] ?? 0);
        }
        
        if ($is_overtime) {
            $overload_lec_hours += $lec_hours;
            $overload_lab_hours += $lab_hours;
            $overload_total_minutes += $schd_min;
            $overload_subject_units += (int)($schedule['subj_unit'] ?? 0);
            $overload_schedules[] = $schedule;
        } else {
            $regular_lec_hours += $lec_hours;
            $regular_lab_hours += $lab_hours;
            $regular_total_minutes += $schd_min;
            $regular_subject_units += (int)($schedule['subj_unit'] ?? 0);
            $regular_schedules[] = $schedule;
        }
    }
    
    // Calculate totals
    $current_teaching_minutes = $regular_total_minutes + $overload_total_minutes;
    $current_teaching_hours = round($current_teaching_minutes / 60, 2);
    $schedule_count = count($schedules);
    
    // Calculate other workload components (in hours)
    // Note: instruction_hours is used as the limit, so it's excluded from other_hours
    $other_hours = (int)$instructor['administration_hours'] +
                   (int)$instructor['research_hours'] +
                   (int)$instructor['extension_hours'] +
                   (int)$instructor['instructional_functions_hours'] +
                   (int)$instructor['consultation_hours'];
    
    // Calculate totals
    $current_total_hours = $current_teaching_hours + $other_hours;
    $limit_hours = (int)$instructor['instruction_hours'];
    $limit_minutes = $limit_hours * 60;
    
    // If checking a new schedule
    $new_teaching_minutes = $current_teaching_minutes;
    $new_teaching_hours = $current_teaching_hours;
    $new_total_hours = $current_total_hours;
    
    if ($new_schedule_minutes > 0) {
        $new_teaching_minutes = $current_teaching_minutes + $new_schedule_minutes;
        $new_teaching_hours = round($new_teaching_minutes / 60, 2);
        $new_total_hours = $new_teaching_hours + $other_hours;
    }
    
    // Determine conflict status
    $conflict_status = 'none';
    $conflict_severity = 'none';
    $conflict_message = '';
    $can_assign = true;
    
    // Check for conflicts
    if ($limit_hours > 0) {
        if ($new_total_hours > $limit_hours) {
            $excess_hours = round($new_total_hours - $limit_hours, 2);
            $excess_percentage = round(($excess_hours / $limit_hours) * 100, 1);
            
            if ($instructor['inst_status'] === 'Part-Time' || $instructor['inst_status'] === 'Contractual') {
                // Hard conflict for Part-Time/Contractual
                $conflict_status = 'exceeded';
                $conflict_severity = 'critical';
                $can_assign = false;
                $conflict_message = sprintf(
                    'Workload limit exceeded by %s hours (%s%%). %s instructors cannot exceed their assigned workload limit.',
                    $excess_hours,
                    $excess_percentage,
                    ucfirst($instructor['inst_status'])
                );
            } else {
                // Soft conflict for Regular (overtime allowed)
                $conflict_status = 'exceeded';
                $conflict_severity = 'warning';
                $can_assign = true;
                $conflict_message = sprintf(
                    'Workload limit exceeded by %s hours (%s%%). This will be marked as overtime.',
                    $excess_hours,
                    $excess_percentage
                );
            }
        } elseif ($new_total_hours >= ($limit_hours * 0.9)) {
            // Approaching limit (within 10%)
            $remaining_hours = round($limit_hours - $new_total_hours, 2);
            $conflict_status = 'approaching';
            $conflict_severity = 'warning';
            $can_assign = true;
            $conflict_message = sprintf(
                'Approaching workload limit. Only %s hours remaining.',
                $remaining_hours
            );
        } elseif ($new_total_hours < ($limit_hours * 0.5)) {
            // Underload (less than 50% of limit)
            $underload_hours = round($limit_hours - $new_total_hours, 2);
            $conflict_status = 'underload';
            $conflict_severity = 'info';
            $can_assign = true;
            $conflict_message = sprintf(
                'Instructor is underloaded. %s hours available for assignment.',
                $underload_hours
            );
        } else {
            // Optimal workload
            $remaining_hours = round($limit_hours - $new_total_hours, 2);
            $conflict_status = 'optimal';
            $conflict_severity = 'none';
            $can_assign = true;
            $conflict_message = sprintf(
                'Workload is within optimal range. %s hours remaining.',
                $remaining_hours
            );
        }
    }
    
    // Calculate percentages
    $current_percentage = $limit_hours > 0 ? round(($current_total_hours / $limit_hours) * 100, 1) : 0;
    $new_percentage = $limit_hours > 0 ? round(($new_total_hours / $limit_hours) * 100, 1) : 0;
    
    // Build response with detailed breakdown matching the workload form structure
    $response = [
        'success' => true,
        'instructor' => [
            'id' => $instructor['inst_id'],
            'name' => $instructor['instructor_name'],
            'status' => $instructor['inst_status'],
            'rank' => $instructor['rank'],
            'designation' => $instructor['designation']
        ],
        'workload' => [
            'limit_hours' => $limit_hours,
            'limit_minutes' => $limit_minutes,
            'current' => [
                'teaching_hours' => $current_teaching_hours,
                'teaching_minutes' => $current_teaching_minutes,
                'other_hours' => $other_hours,
                'total_hours' => $current_total_hours,
                'percentage' => $current_percentage,
                'schedule_count' => $schedule_count
            ],
            // Regular Load Breakdown (matching form structure)
            'regular' => [
                'lec_hours' => $regular_lec_hours,
                'lab_hours' => $regular_lab_hours,
                'total_hours' => $regular_lec_hours + $regular_lab_hours,
                'total_minutes' => $regular_total_minutes,
                'subject_units' => $regular_subject_units,
                'schedule_count' => count($regular_schedules),
                'schedules' => $regular_schedules
            ],
            // Overload/Part-Time Load Breakdown (matching form structure)
            'overload' => [
                'lec_hours' => $overload_lec_hours,
                'lab_hours' => $overload_lab_hours,
                'total_hours' => $overload_lec_hours + $overload_lab_hours,
                'total_minutes' => $overload_total_minutes,
                'subject_units' => $overload_subject_units,
                'schedule_count' => count($overload_schedules),
                'schedules' => $overload_schedules
            ],
            // Other In-School Involvement/Assignment Per Week (matching form structure)
            // Note: instruction_hours is used as the limit, so it's shown separately
            'other_involvement' => [
                'administration_hours' => (int)$instructor['administration_hours'],
                'instruction_hours' => (int)$instructor['instruction_hours'],
                'research_hours' => (int)$instructor['research_hours'],
                'extension_hours' => (int)$instructor['extension_hours'],
                'instructional_functions_hours' => (int)$instructor['instructional_functions_hours'],
                'consultation_hours' => (int)$instructor['consultation_hours'],
                'total_hours' => $other_hours + (int)$instructor['instruction_hours']
            ],
            // Legacy components (for backward compatibility)
            'components' => [
                'administration_hours' => (int)$instructor['administration_hours'],
                'instruction_hours' => (int)$instructor['instruction_hours'],
                'research_hours' => (int)$instructor['research_hours'],
                'extension_hours' => (int)$instructor['extension_hours'],
                'instructional_functions_hours' => (int)$instructor['instructional_functions_hours'],
                'consultation_hours' => (int)$instructor['consultation_hours']
            ]
        ],
        'conflict' => [
            'status' => $conflict_status,
            'severity' => $conflict_severity,
            'message' => $conflict_message,
            'can_assign' => $can_assign
        ]
    ];
    
    // If checking new schedule, add projected values
    if ($new_schedule_minutes > 0) {
        // Note: For projected values, we can't determine Lec/Lab breakdown without knowing the schedule type
        // This would need to be passed as a parameter or calculated separately
        $response['projected'] = [
            'new_schedule_minutes' => $new_schedule_minutes,
            'new_schedule_hours' => round($new_schedule_minutes / 60, 2),
            'new_teaching_hours' => $new_teaching_hours,
            'new_total_hours' => $new_total_hours,
            'new_percentage' => $new_percentage,
            'excess_hours' => $new_total_hours > $limit_hours ? round($new_total_hours - $limit_hours, 2) : 0,
            'remaining_hours' => $new_total_hours < $limit_hours ? round($limit_hours - $new_total_hours, 2) : 0
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>




