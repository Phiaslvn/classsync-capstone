<?php
/**
 * Save Schedule
 * Handles creation and updates of schedules with workload validation.
 */

require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/instructor_department_appointments.php';

header('Content-Type: application/json');

// Check if user has permission to manage schedules
if (!hasPermission('manage_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$schd_id = isset($_POST['schd_id']) ? (int)$_POST['schd_id'] : 0;
$sy_id = (int)($_POST['sy_id'] ?? 0);
$schd_term = (int)($_POST['schd_term'] ?? 0);
$subj_id = (int)($_POST['subj_id'] ?? 0);
$sec_id = (int)($_POST['sec_id'] ?? 0);
$inst_id = (int)($_POST['inst_id'] ?? 0);
$rm_id = (int)($_POST['rm_id'] ?? 0);
$schd_type = trim($_POST['schd_type'] ?? '');
// Get days as array (from checkboxes)
$schd_days = isset($_POST['schd_day']) ? (is_array($_POST['schd_day']) ? $_POST['schd_day'] : [$_POST['schd_day']]) : [];
$schd_start = trim($_POST['schd_start'] ?? '');
$schd_end = trim($_POST['schd_end'] ?? '');
$schd_status = trim($_POST['schd_status'] ?? 'Active');

// --- Validation ---
if (empty($schd_days)) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one day.']);
    exit();
}
$required = [$sy_id, $schd_term, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_start, $schd_end];
if (in_array(0, $required, true) || in_array('', $required, true)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// Calculate duration in minutes
$start_time = new DateTime($schd_start);
$end_time = new DateTime($schd_end);
$duration = $end_time->getTimestamp() - $start_time->getTimestamp();
$schd_min = $duration / 60;

if ($schd_min <= 0) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time.']);
    exit();
}

try {
    // --- Load subject (needed for hour limits and per-department workload policy) ---
    $subj_stmt = $conn->prepare("SELECT dept_id, subj_lec, subj_lab, subj_code, subj_desc FROM subject WHERE subj_id = ?");
    $subj_stmt->bind_param("i", $subj_id);
    $subj_stmt->execute();
    $subject = $subj_stmt->get_result()->fetch_assoc();
    $subj_stmt->close();

    if (!$subject) {
        echo json_encode(['success' => false, 'message' => 'Subject not found.']);
        exit();
    }

    // --- Instructor Workload Validation (per subject department when appointments exist) ---
    $stmt = $conn->prepare("SELECT inst_status, instruction_hours FROM instructor WHERE inst_id = ?");
    $stmt->bind_param("i", $inst_id);
    $stmt->execute();
    $instructor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$instructor) {
        echo json_encode(['success' => false, 'message' => 'Instructor not found.']);
        exit();
    }

    $subj_dept_id = (int) ($subject['dept_id'] ?? 0);
    $policy = ida_get_workload_policy_for_subject_dept($conn, $inst_id, $subj_dept_id, $instructor);
    $policy_dept_label = $subj_dept_id > 0 ? ida_department_name($conn, $subj_dept_id) : '';

    $current_minutes = ida_sum_scheduled_minutes_for_department(
        $conn,
        $inst_id,
        $sy_id,
        $schd_term,
        $subj_dept_id,
        $schd_id
    );

    // For new schedules, multiply by number of days; for updates, use single day
    $total_minutes_for_all_days = $schd_id > 0 ? $schd_min : ($schd_min * count($schd_days));
    $new_total_minutes = $current_minutes + $total_minutes_for_all_days;
    $limit_minutes = (float) $policy['instruction_hours'] * 60;

    $is_overtime = 'No'; // Default value

    if ($policy['inst_status'] === 'Part-Time' || $policy['inst_status'] === 'Contractual') {
        if ($limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $current_hours = round($current_minutes / 60, 2);
            $new_schedule_hours = round($schd_min / 60, 2);
            $total_hours = round($new_total_minutes / 60, 2);
            $excess_hours = round(($new_total_minutes - $limit_minutes) / 60, 2);
            $dept_note = $policy_dept_label !== ''
                ? "\n🏢 Policy department: {$policy_dept_label}\n"
                : '';

            $workloadMessage = sprintf(
                '🚫 WORKLOAD LIMIT EXCEEDED!\n\n' .
                'The instructor\'s workload limit for this teaching assignment has been exceeded:\n%s' .
                '📊 Workload Details:\n' .
                '   • Maximum Allowed: %s hours\n' .
                '   • Current Load (this department): %s hours\n' .
                '   • New Schedule: %s hours\n' .
                '   • Total Would Be: %s hours\n' .
                '   • Exceeds By: %s hours\n\n' .
                '⚠️ %s instructors cannot exceed their assigned workload limit for this department line.\n\n' .
                '💡 Solution: Please reduce the schedule duration, remove another schedule, assign a different instructor, or adjust per-department employment in user management.',
                $dept_note,
                $policy['instruction_hours'],
                $current_hours,
                $new_schedule_hours,
                $total_hours,
                $excess_hours,
                ucfirst($policy['inst_status'])
            );

            echo json_encode([
                'success' => false,
                'message' => $workloadMessage,
                'conflict_type' => 'workload_limit',
                'conflict_element' => 'Workload/Time'
            ]);
            exit();
        }
    } elseif ($policy['inst_status'] === 'Regular') {
        if ($limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $is_overtime = 'Yes';
        }
    }

    // --- Subject Hour Limit Validation ---
    // Check if adding this schedule would exceed the subject's required Lec/Lab hours
    if ($subject) {
        $subj_lec = (int)$subject['subj_lec'];
        $subj_lab = (int)$subject['subj_lab'];
        $subj_code = $subject['subj_code'] ?? 'Unknown';
        $subj_desc = $subject['subj_desc'] ?? '';
        
        // Get total scheduled hours for this subject in this section (excluding current schedule if updating)
        $total_sched_stmt = $conn->prepare("
            SELECT SUM(CASE WHEN schd_type = 'Lec' THEN schd_min ELSE 0 END) / 60.0 as total_scheduled_lec_hours,
                   SUM(CASE WHEN schd_type = 'Lab' THEN schd_min ELSE 0 END) / 60.0 as total_scheduled_lab_hours
            FROM schedule
            WHERE sy_id = ? 
              AND schd_term = ? 
              AND subj_id = ? 
              AND sec_id = ?
              AND schd_status = 'Active'
        ");
        $total_sched_params = [$sy_id, $schd_term, $subj_id, $sec_id];
        $total_sched_types = "iiii";
        
        // Exclude current schedule if updating
        if ($schd_id > 0) {
            $total_sched_stmt = $conn->prepare("
                SELECT SUM(CASE WHEN schd_type = 'Lec' THEN schd_min ELSE 0 END) / 60.0 as total_scheduled_lec_hours,
                       SUM(CASE WHEN schd_type = 'Lab' THEN schd_min ELSE 0 END) / 60.0 as total_scheduled_lab_hours
                FROM schedule
                WHERE sy_id = ? 
                  AND schd_term = ? 
                  AND subj_id = ? 
                  AND sec_id = ?
                  AND schd_status = 'Active'
                  AND schd_id != ?
            ");
            $total_sched_params[] = $schd_id;
            $total_sched_types .= "i";
        }
        
        $total_sched_stmt->bind_param($total_sched_types, ...$total_sched_params);
        $total_sched_stmt->execute();
        $total_scheduled = $total_sched_stmt->get_result()->fetch_assoc();
        $total_sched_stmt->close();
        
        $total_scheduled_lec = (float)($total_scheduled['total_scheduled_lec_hours'] ?? 0);
        $total_scheduled_lab = (float)($total_scheduled['total_scheduled_lab_hours'] ?? 0);
        
        // Calculate new total hours after adding this schedule
        $new_schedule_hours = $schd_min / 60.0; // Convert minutes to hours
        $new_total_lec = $total_scheduled_lec;
        $new_total_lab = $total_scheduled_lab;
        
        // Add the new schedule hours based on type
        if ($schd_type === 'Lec') {
            $new_total_lec += $new_schedule_hours;
        } elseif ($schd_type === 'Lab') {
            $new_total_lab += $new_schedule_hours;
        } elseif ($schd_type === 'Special') {
            // For Special type, check both Lec and Lab limits
            // Assume it counts towards whichever has remaining hours, or Lec if both are full
            if ($subj_lec > 0 && $total_scheduled_lec < $subj_lec) {
                $new_total_lec += $new_schedule_hours;
            } elseif ($subj_lab > 0 && $total_scheduled_lab < $subj_lab) {
                $new_total_lab += $new_schedule_hours;
            } else {
                // Both are full, count towards Lec by default
                $new_total_lec += $new_schedule_hours;
            }
        }
        
        // Check if Lec hours would be exceeded
        if ($subj_lec > 0 && $new_total_lec > $subj_lec) {
            $excess_lec = round($new_total_lec - $subj_lec, 2);
            $subjectHourMessage = sprintf(
                '🚫 SUBJECT HOUR LIMIT EXCEEDED!\n\n' .
                'The subject\'s required Lecture hours would be exceeded:\n\n' .
                '📚 Subject: %s - %s\n' .
                '📊 Lecture Hours:\n' .
                '   • Required: %s hours\n' .
                '   • Currently Scheduled: %s hours\n' .
                '   • New Schedule: %s hours\n' .
                '   • Total Would Be: %s hours\n' .
                '   • Exceeds By: %s hours\n\n' .
                '⚠️ You cannot schedule more hours than the subject requires.\n\n' .
                '💡 Solution: Please reduce the schedule duration or remove an existing schedule.',
                htmlspecialchars($subj_code),
                htmlspecialchars($subj_desc),
                $subj_lec,
                round($total_scheduled_lec, 2),
                round($new_schedule_hours, 2),
                round($new_total_lec, 2),
                $excess_lec
            );
            
            echo json_encode([
                'success' => false,
                'message' => $subjectHourMessage,
                'conflict_type' => 'subject_hour_limit',
                'conflict_element' => 'Subject Hours (Lec)'
            ]);
            exit();
        }
        
        // Check if Lab hours would be exceeded
        if ($subj_lab > 0 && $new_total_lab > $subj_lab) {
            $excess_lab = round($new_total_lab - $subj_lab, 2);
            $subjectHourMessage = sprintf(
                '🚫 SUBJECT HOUR LIMIT EXCEEDED!\n\n' .
                'The subject\'s required Laboratory hours would be exceeded:\n\n' .
                '📚 Subject: %s - %s\n' .
                '📊 Laboratory Hours:\n' .
                '   • Required: %s hours\n' .
                '   • Currently Scheduled: %s hours\n' .
                '   • New Schedule: %s hours\n' .
                '   • Total Would Be: %s hours\n' .
                '   • Exceeds By: %s hours\n\n' .
                '⚠️ You cannot schedule more hours than the subject requires.\n\n' .
                '💡 Solution: Please reduce the schedule duration or remove an existing schedule.',
                htmlspecialchars($subj_code),
                htmlspecialchars($subj_desc),
                $subj_lab,
                round($total_scheduled_lab, 2),
                round($new_schedule_hours, 2),
                round($new_total_lab, 2),
                $excess_lab
            );
            
            echo json_encode([
                'success' => false,
                'message' => $subjectHourMessage,
                'conflict_type' => 'subject_hour_limit',
                'conflict_element' => 'Subject Hours (Lab)'
            ]);
            exit();
        }
    }

    // --- Schedule Conflict Detection ---
    // Check for overlapping schedules on the same day and time
    // This ensures no conflicts for: Section, Instructor, Room, Day, and Time
    // Order of checks: Section -> Instructor -> Room (most specific to least specific)
    
    // For updates, use first selected day; for inserts, check all days
    $days_to_check = $schd_id > 0 ? [$schd_days[0]] : $schd_days;
    
    foreach ($days_to_check as $schd_day) {
        // 1. Check for Section Conflict (same section cannot have overlapping schedules on same day/time)
    // Skip section conflict check if the new schedule is in a virtual room
    $roomCheckStmt = $conn->prepare("SELECT b.bd_desc FROM room r JOIN building b ON r.bd_id = b.bd_id WHERE r.rm_id = ?");
    $roomCheckStmt->bind_param("i", $rm_id);
    $roomCheckStmt->execute();
    $roomCheckResult = $roomCheckStmt->get_result();
    $roomData = $roomCheckResult->fetch_assoc();
    $roomCheckStmt->close();
    
    // Check if building allows overlapping schedules (Virtual or School Ground)
    $allowsOverlap = false;
    if ($roomData) {
        $buildingName = strtoupper(trim($roomData['bd_desc']));
        $allowsOverlap = ($buildingName === 'VIRTUAL' || $buildingName === 'SCHOOL GROUND' || $buildingName === 'SCHOOL GROUNDS');
    }
    
    // Only check for section conflicts if the building does NOT allow overlapping schedules
    if (!$allowsOverlap) {
        $conflictQuery = "SELECT s.schd_id, subj.subj_code, subj.subj_desc, 
                                 CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
                                 r.rm_name, b.bd_desc as building_name,
                                 TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, 
                                 TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time
                          FROM schedule s
                          JOIN subject subj ON s.subj_id = subj.subj_id
                          JOIN instructor i ON s.inst_id = i.inst_id
                          JOIN room r ON s.rm_id = r.rm_id
                          JOIN building b ON r.bd_id = b.bd_id
                          WHERE s.sy_id = ? 
                            AND s.schd_term = ? 
                            AND s.schd_day = ? 
                            AND s.sec_id = ? 
                            AND s.schd_status = 'Active'
                            AND s.schd_start < ? 
                            AND s.schd_end > ?
                            AND UPPER(TRIM(b.bd_desc)) NOT IN ('VIRTUAL', 'SCHOOL GROUND', 'SCHOOL GROUNDS')";
        
        $conflictParams = [$sy_id, $schd_term, $schd_day, $sec_id, $schd_end, $schd_start];
        $conflictTypes = "iiisiss";
        
        // Exclude current schedule if updating
        if ($schd_id > 0) {
            $conflictQuery .= " AND s.schd_id != ?";
            $conflictParams[] = $schd_id;
            $conflictTypes .= "i";
        }
        
        $conflictStmt = $conn->prepare($conflictQuery);
        $conflictStmt->bind_param($conflictTypes, ...$conflictParams);
        $conflictStmt->execute();
        $conflictResult = $conflictStmt->get_result();
        
        if ($conflictResult->num_rows > 0) {
            $conflict = $conflictResult->fetch_assoc();
            $dayName = ucfirst(strtolower($schd_day));
            if (strlen($dayName) > 3) {
                $dayName = substr($dayName, 0, 3) . '.';
            }
            
            $conflictMessage = sprintf(
                '🚫 SECTION CONFLICT DETECTED!\n\n' .
                'The selected section (BSIT 1-A) already has a scheduled class on %s with overlapping time:\n\n' .
                '📚 Conflicting Subject: %s - %s\n' .
                '👤 Instructor: %s\n' .
                '🏢 Location: %s - %s\n' .
                '⏱️ Time Slot: %s to %s\n\n' .
                '⚠️ A section cannot have multiple classes scheduled at the same day and time.\n\n' .
                '💡 Solution: Please choose a different day, time slot, or section.',
                $dayName,
                $conflict['subj_code'],
                $conflict['subj_desc'],
                $conflict['instructor_name'],
                $conflict['building_name'],
                $conflict['rm_name'],
                $conflict['start_time'],
                $conflict['end_time']
            );
            
            $conflictStmt->close();
            echo json_encode([
                'success' => false,
                'message' => $conflictMessage,
                'conflict_type' => 'section_time',
                'conflict_element' => 'Section'
            ]);
            exit();
        }
        $conflictStmt->close();
    }
    
        // 2. Check for Instructor Conflict (same instructor, same day, overlapping time)
    $conflictQuery = "SELECT s.schd_id, subj.subj_code, subj.subj_desc, 
                             sec.sec_name,
                             r.rm_name, b.bd_desc as building_name,
                             TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, 
                             TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time
                      FROM schedule s
                      JOIN subject subj ON s.subj_id = subj.subj_id
                      JOIN section sec ON s.sec_id = sec.sec_id
                      JOIN room r ON s.rm_id = r.rm_id
                      JOIN building b ON r.bd_id = b.bd_id
                      WHERE s.sy_id = ? 
                        AND s.schd_term = ? 
                        AND s.schd_day = ? 
                        AND s.inst_id = ? 
                        AND s.schd_status = 'Active'
                        AND s.schd_start < ? 
                        AND s.schd_end > ?";
    
    $conflictParams = [$sy_id, $schd_term, $schd_day, $inst_id, $schd_end, $schd_start];
    $conflictTypes = "iiisiss";
    
    // Exclude current schedule if updating
    if ($schd_id > 0) {
        $conflictQuery .= " AND s.schd_id != ?";
        $conflictParams[] = $schd_id;
        $conflictTypes .= "i";
    }
    
    $conflictStmt = $conn->prepare($conflictQuery);
    $conflictStmt->bind_param($conflictTypes, ...$conflictParams);
    $conflictStmt->execute();
    $conflictResult = $conflictStmt->get_result();
    
    if ($conflictResult->num_rows > 0) {
        $conflict = $conflictResult->fetch_assoc();
        $dayName = ucfirst(strtolower($schd_day));
        if (strlen($dayName) > 3) {
            $dayName = substr($dayName, 0, 3) . '.';
        }
        
        $conflictMessage = sprintf(
            '🚫 INSTRUCTOR CONFLICT DETECTED!\n\n' .
            'The selected instructor is already scheduled for another class on %s with overlapping time:\n\n' .
            '📚 Conflicting Subject: %s - %s\n' .
            '👥 Section: %s\n' .
            '🏢 Location: %s - %s\n' .
            '⏱️ Time Slot: %s to %s\n\n' .
            '⚠️ An instructor cannot teach multiple classes at the same day and time.\n\n' .
            '💡 Solution: Please choose a different day, time slot, or instructor.',
            $dayName,
            $conflict['subj_code'],
            $conflict['subj_desc'],
            $conflict['sec_name'],
            $conflict['building_name'],
            $conflict['rm_name'],
            $conflict['start_time'],
            $conflict['end_time']
        );
        
        $conflictStmt->close();
        echo json_encode([
            'success' => false,
            'message' => $conflictMessage,
            'conflict_type' => 'instructor_time',
            'conflict_element' => 'Instructor'
        ]);
        exit();
    }
    $conflictStmt->close();
    
        // 3. Check for Room Conflict (same room, same day, overlapping time)
    // Skip room conflict check if the room is in a "Virtual" building (online classes)
    $roomCheckStmt = $conn->prepare("SELECT b.bd_desc FROM room r JOIN building b ON r.bd_id = b.bd_id WHERE r.rm_id = ?");
    $roomCheckStmt->bind_param("i", $rm_id);
    $roomCheckStmt->execute();
    $roomCheckResult = $roomCheckStmt->get_result();
    $roomData = $roomCheckResult->fetch_assoc();
    $roomCheckStmt->close();
    
    // Check if building allows overlapping schedules (Virtual or School Ground)
    $allowsOverlap = false;
    if ($roomData) {
        $buildingName = strtoupper(trim($roomData['bd_desc']));
        $allowsOverlap = ($buildingName === 'VIRTUAL' || $buildingName === 'SCHOOL GROUND' || $buildingName === 'SCHOOL GROUNDS');
    }
    
    // Only check for room conflicts if the building does NOT allow overlapping schedules
    if (!$allowsOverlap) {
        $conflictQuery = "SELECT s.schd_id, subj.subj_code, subj.subj_desc, 
                                 sec.sec_name,
                                 CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
                                 TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, 
                                 TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time
                          FROM schedule s
                          JOIN subject subj ON s.subj_id = subj.subj_id
                          JOIN section sec ON s.sec_id = sec.sec_id
                          JOIN instructor i ON s.inst_id = i.inst_id
                          JOIN room r ON s.rm_id = r.rm_id
                          JOIN building b ON r.bd_id = b.bd_id
                          WHERE s.sy_id = ? 
                            AND s.schd_term = ? 
                            AND s.schd_day = ? 
                            AND s.rm_id = ? 
                            AND s.schd_status = 'Active'
                            AND s.schd_start < ? 
                            AND s.schd_end > ?
                            AND UPPER(TRIM(b.bd_desc)) NOT IN ('VIRTUAL', 'SCHOOL GROUND', 'SCHOOL GROUNDS')";
        
        $conflictParams = [$sy_id, $schd_term, $schd_day, $rm_id, $schd_end, $schd_start];
        $conflictTypes = "iiisiss";
        
        // Exclude current schedule if updating
        if ($schd_id > 0) {
            $conflictQuery .= " AND s.schd_id != ?";
            $conflictParams[] = $schd_id;
            $conflictTypes .= "i";
        }
        
        $conflictStmt = $conn->prepare($conflictQuery);
        $conflictStmt->bind_param($conflictTypes, ...$conflictParams);
        $conflictStmt->execute();
        $conflictResult = $conflictStmt->get_result();
        
        if ($conflictResult->num_rows > 0) {
            $conflict = $conflictResult->fetch_assoc();
            $dayName = ucfirst(strtolower($schd_day));
            if (strlen($dayName) > 3) {
                $dayName = substr($dayName, 0, 3) . '.';
            }
            
            $conflictMessage = sprintf(
                '🚫 ROOM CONFLICT DETECTED!\n\n' .
                'The selected room is already booked on %s with overlapping time:\n\n' .
                '📚 Conflicting Subject: %s - %s\n' .
                '👥 Section: %s\n' .
                '👤 Instructor: %s\n' .
                '⏱️ Time Slot: %s to %s\n\n' .
                '⚠️ A room cannot accommodate multiple classes at the same day and time.\n\n' .
                '💡 Solution: Please choose a different room, day, or time slot.',
                $dayName,
                $conflict['subj_code'],
                $conflict['subj_desc'],
                $conflict['sec_name'],
                $conflict['instructor_name'],
                $conflict['start_time'],
                $conflict['end_time']
            );
            
            $conflictStmt->close();
            echo json_encode([
                'success' => false,
                'message' => $conflictMessage,
                'conflict_type' => 'room_time',
                'conflict_element' => 'Room'
            ]);
            exit();
        }
        $conflictStmt->close();
    }
    } // End foreach days_to_check

    // --- Room Access Validation (Automatic Room Usage System) ---
    // Check if user's department has access to this room (owns it or has room_access grant)
    // If access exists, allow automatic scheduling without approval (skip room_request restrictions)
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    $isAdminSupport = isAdminSupport();
    
    $hasRoomAccess = false;
    if ($isAdminSupport) {
        // Admin Support has access to all rooms
        $hasRoomAccess = true;
    } elseif ($userDeptId > 0) {
        // Check if room belongs to user's department
        $roomOwnerCheck = $conn->prepare("SELECT dept_id FROM room WHERE rm_id = ?");
        $roomOwnerCheck->bind_param("i", $rm_id);
        $roomOwnerCheck->execute();
        $roomOwnerResult = $roomOwnerCheck->get_result();
        $roomData = $roomOwnerResult->fetch_assoc();
        $roomOwnerCheck->close();
        
        if ($roomData && (int)$roomData['dept_id'] === $userDeptId) {
            // User's department owns the room
            $hasRoomAccess = true;
        } else {
            // Check if user's department has room_access grant
            $accessCheckQuery = "SELECT 1 FROM room_access 
                                WHERE rm_id = ? AND granted_to_dept_id = ? AND status = 'Active'";
            $accessCheckStmt = $conn->prepare($accessCheckQuery);
            $accessCheckStmt->bind_param("ii", $rm_id, $userDeptId);
            $accessCheckStmt->execute();
            $accessResult = $accessCheckStmt->get_result();
            $hasRoomAccess = $accessResult->num_rows > 0;
            $accessCheckStmt->close();
        }
    }
    
    // --- Room Request Access Validation ---
    // Only apply room_request restrictions if user's department does NOT have room_access
    // If hasRoomAccess is true, skip room_request validation (automatic allocation allowed)
    if (!$hasRoomAccess) {
        // For updates, use first selected day; for inserts, check all days
        $schd_day = $schd_id > 0 ? $schd_days[0] : null;
        // Check if this room has an active request from another department
        // If so, only allow scheduling on the requested day and time range
        $roomRequestQuery = "SELECT rr.req_id, rr.schd_day, rr.schd_start, rr.schd_end, 
                                d.dept_name as requester_dept_name,
                                CONCAT(i.inst_fname, ' ', i.inst_lname) as requester_name,
                                TIME_FORMAT(rr.schd_start, '%h:%i %p') as start_time_formatted,
                                TIME_FORMAT(rr.schd_end, '%h:%i %p') as end_time_formatted
                         FROM room_request rr
                         JOIN room r ON rr.rm_id = r.rm_id
                         JOIN instructor i ON rr.inst_id = i.inst_id
                         JOIN account a ON i.inst_user = a.acc_user
                         LEFT JOIN department d ON a.dept_id = d.dept_id
                         WHERE rr.rm_id = ? 
                           AND rr.req_status = 'Accepted'
                           AND r.dept_id != a.dept_id
                           AND (
                               -- Regular Class: Always included (no automatic expiration)
                               rr.req_comment NOT LIKE '%[Class Type: Make Up Class]%'
                               OR
                               -- Make Up Class: Check both date expiration AND time expiration
                               (
                                   rr.req_comment LIKE '%[Class Type: Make Up Class]%'
                                   -- Check if date hasn't expired (next Monday hasn't passed)
                                   AND DATE(rr.req_date) + INTERVAL (
                                       CASE DAYOFWEEK(DATE(rr.req_date))
                                           WHEN 2 THEN 7
                                           ELSE (9 - DAYOFWEEK(DATE(rr.req_date))) % 7
                                       END
                                   ) DAY > CURDATE()
                                   -- For Make Up Class: If today, check if time slot has passed
                                   AND (
                                       -- If request date is NOT today, include it (future date)
                                       DATE(rr.req_date) != CURDATE()
                                       OR
                                       -- If request date IS today, only include if current time < schd_end
                                       (DATE(rr.req_date) = CURDATE() AND TIME(NOW()) < rr.schd_end)
                                   )
                               )
                           )";
    
    $roomRequestStmt = $conn->prepare($roomRequestQuery);
    $roomRequestStmt->bind_param("i", $rm_id);
    $roomRequestStmt->execute();
    $roomRequestResult = $roomRequestStmt->get_result();
    
    if ($roomRequestResult->num_rows > 0) {
        // Room has active requests from other departments
        $allowedDays = [];
        $allowedTimeRanges = [];
        
        while ($request = $roomRequestResult->fetch_assoc()) {
            $requestDay = $request['schd_day'];
            
            // Store allowed day and time range
            if (!in_array($requestDay, $allowedDays)) {
                $allowedDays[] = $requestDay;
            }
            $allowedTimeRanges[$requestDay][] = [
                'start' => $request['schd_start'],
                'end' => $request['schd_end'],
                'start_formatted' => $request['start_time_formatted'],
                'end_formatted' => $request['end_time_formatted'],
                'requester_dept' => $request['requester_dept_name'],
                'requester_name' => $request['requester_name']
            ];
        }
        
        // Check each selected day against room requests
        $days_to_check_room = $schd_id > 0 ? [$schd_days[0]] : $schd_days;
        foreach ($days_to_check_room as $check_day) {
            $conflictFound = false;
            
            // Check if the schedule day is in allowed days
            if (!in_array($check_day, $allowedDays)) {
                $conflictFound = true;
                $dayName = ucfirst(strtolower($check_day));
                if (strlen($dayName) > 3) {
                    $dayName = substr($dayName, 0, 3) . '.';
                }
                $allowedDaysList = implode(', ', array_map(function($day) {
                    $dName = ucfirst(strtolower($day));
                    if (strlen($dName) > 3) {
                        $dName = substr($dName, 0, 3) . '.';
                    }
                    return $dName;
                }, $allowedDays));
                
                $primaryRequest = reset($allowedTimeRanges);
                $primaryDay = key($allowedTimeRanges);
                $primaryRange = reset($primaryRequest);
                
                $roomRequestStmt->close();
                echo json_encode([
                    'success' => false,
                    'message' => sprintf(
                        'Room Access Restriction<br><br>' .
                        'This room has been requested by %s for %s only.<br><br>' .
                        'You attempted to schedule on: %s<br>' .
                        'Please change the day to: %s',
                        htmlspecialchars($primaryRange['requester_dept']),
                        htmlspecialchars($allowedDaysList),
                        htmlspecialchars($dayName),
                        htmlspecialchars($allowedDaysList)
                    ),
                    'conflict_type' => 'room_request_access',
                    'conflict_element' => 'Room Request'
                ]);
                exit();
            } else {
                // Day matches, check if time overlaps
                $scheduleStart = new DateTime($schd_start);
                $scheduleEnd = new DateTime($schd_end);
                
                foreach ($allowedTimeRanges[$check_day] as $timeRange) {
                    $requestStartTime = new DateTime($timeRange['start']);
                    $requestEndTime = new DateTime($timeRange['end']);
                    
                    // Check if schedule time is within requested time range
                    if ($scheduleStart < $requestStartTime || $scheduleEnd > $requestEndTime) {
                        $conflictFound = true;
                        $dayName = ucfirst(strtolower($check_day));
                        if (strlen($dayName) > 3) {
                            $dayName = substr($dayName, 0, 3) . '.';
                        }
                        
                        $roomRequestStmt->close();
                        echo json_encode([
                            'success' => false,
                            'message' => sprintf(
                                'Room Access Restriction<br><br>' .
                                'This room has been requested by %s for %s, %s to %s only.<br><br>' .
                                'You attempted to schedule: %s to %s<br>' .
                                'Please adjust the time to fall within %s to %s',
                                htmlspecialchars($timeRange['requester_dept']),
                                htmlspecialchars($dayName),
                                htmlspecialchars($timeRange['start_formatted']),
                                htmlspecialchars($timeRange['end_formatted']),
                                htmlspecialchars(date('h:i A', strtotime($schd_start))),
                                htmlspecialchars(date('h:i A', strtotime($schd_end))),
                                htmlspecialchars($timeRange['start_formatted']),
                                htmlspecialchars($timeRange['end_formatted'])
                            ),
                            'conflict_type' => 'room_request_access',
                            'conflict_element' => 'Room Request'
                        ]);
                        exit();
                    }
                }
            }
        }
        
        if (false) { // This block is now handled above
                $allowedDaysList = implode(', ', array_map(function($day) {
                    $dayName = ucfirst(strtolower($day));
                    if (strlen($dayName) > 3) {
                        $dayName = substr($dayName, 0, 3) . '.';
                    }
                    return $dayName;
                }, $allowedDays));
                
                // Get the first (primary) request details for the main message
                $primaryRequest = reset($allowedTimeRanges);
                $primaryDay = key($allowedTimeRanges);
                $primaryRange = reset($primaryRequest);
                
                $dayName = ucfirst(strtolower($primaryDay));
                if (strlen($dayName) > 3) {
                    $dayName = substr($dayName, 0, 3) . '.';
                }
                
                $scheduleDayName = ucfirst(strtolower($schd_day));
                if (strlen($scheduleDayName) > 3) {
                    $scheduleDayName = substr($scheduleDayName, 0, 3) . '.';
                }
                
                // Simple, clear error message (using HTML line breaks for SweetAlert)
                if ($schd_day !== $primaryDay) {
                    // Day mismatch
                    $conflictMessage = sprintf(
                        'Room Access Restriction<br><br>' .
                        'This room has been requested by %s for %s only.<br><br>' .
                        'You attempted to schedule on: %s<br>' .
                        'Please change the day to: %s',
                        htmlspecialchars($primaryRange['requester_dept']),
                        htmlspecialchars($dayName),
                        htmlspecialchars($scheduleDayName),
                        htmlspecialchars($dayName)
                    );
                } else {
                    // Time mismatch
                    $conflictMessage = sprintf(
                        'Room Access Restriction<br><br>' .
                        'This room has been requested by %s for %s, %s to %s only.<br><br>' .
                        'You attempted to schedule: %s to %s<br>' .
                        'Please adjust the time to fall within %s to %s',
                        htmlspecialchars($primaryRange['requester_dept']),
                        htmlspecialchars($dayName),
                        htmlspecialchars($primaryRange['start_formatted']),
                        htmlspecialchars($primaryRange['end_formatted']),
                        htmlspecialchars(date('h:i A', strtotime($schd_start))),
                        htmlspecialchars(date('h:i A', strtotime($schd_end))),
                        htmlspecialchars($primaryRange['start_formatted']),
                        htmlspecialchars($primaryRange['end_formatted'])
                    );
                }
            
            $roomRequestStmt->close();
            echo json_encode([
                'success' => false,
                'message' => $conflictMessage,
                'conflict_type' => 'room_request_access',
                'conflict_element' => 'Room Request'
            ]);
            exit();
        }
        }
        $roomRequestStmt->close();
    } // End if (!$hasRoomAccess) - room_request validation only if no room_access

    // --- Save to Database ---
    if ($schd_id > 0) { // Update - use first selected day
        $schd_day = $schd_days[0];
        $stmt = $conn->prepare("UPDATE schedule SET sy_id=?, subj_id=?, sec_id=?, inst_id=?, rm_id=?, schd_type=?, schd_term=?, schd_day=?, schd_start=?, schd_end=?, schd_min=?, schd_status=?, is_overtime=? WHERE schd_id=?");
        $stmt->bind_param("iiiiisisssissi", $sy_id, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_term, $schd_day, $schd_start, $schd_end, $schd_min, $schd_status, $is_overtime, $schd_id);
        
        if ($stmt->execute()) {
            $message = 'Schedule updated successfully.';
            if ($is_overtime === 'Yes' && $policy['inst_status'] === 'Regular') {
                $message .= ' Note: Instructor is now in overtime.';
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save schedule: ' . $stmt->error]);
        }
        $stmt->close();
    } else { // Insert - create multiple records for each day
        $stmt = $conn->prepare("INSERT INTO schedule (sy_id, subj_id, sec_id, inst_id, rm_id, schd_type, schd_term, schd_day, schd_start, schd_end, schd_min, schd_status, is_overtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $created_count = 0;
        $errors = [];
        
        foreach ($schd_days as $schd_day) {
            $stmt->bind_param("iiiiisisssiss", $sy_id, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_term, $schd_day, $schd_start, $schd_end, $schd_min, $schd_status, $is_overtime);
            
            if ($stmt->execute()) {
                $created_count++;
            } else {
                $errors[] = "Failed to create schedule for " . ucfirst(strtolower($schd_day)) . ": " . $stmt->error;
            }
        }
        $stmt->close();
        
        if ($created_count > 0) {
            $dayNames = array_map(function($day) {
                return ucfirst(strtolower($day));
            }, $schd_days);
            $message = 'Schedule created successfully for ' . implode(', ', $dayNames) . '!';
            if ($is_overtime === 'Yes' && $policy['inst_status'] === 'Regular') {
                $message .= ' Note: Instructor is now in overtime.';
            }
            if (count($errors) > 0) {
                $message .= ' However, some days failed: ' . implode('; ', $errors);
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create schedules: ' . implode('; ', $errors)]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>