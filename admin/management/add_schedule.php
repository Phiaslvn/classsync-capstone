<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/instructor_department_appointments.php';

header('Content-Type: application/json');
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
$response = ['success' => false, 'message' => 'An error occurred.'];

if (!hasPermission('manage_schedules')) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sy_id = $_POST['sy_id'];
    $program_id = $_POST['program_id'] ?? null;
    $year_level = $_POST['year_level'] ?? null;
    $subj_id = $_POST['subj_id'];
    $sec_id = $_POST['sec_id'];
    $inst_id = $_POST['inst_id'];
    $rm_id = $_POST['rm_id'];
    $schd_type = $_POST['schd_type'];
    $schd_term = $_POST['schd_term'];
    // Get days as array (from checkboxes)
    $schd_days = isset($_POST['schd_day']) ? (is_array($_POST['schd_day']) ? $_POST['schd_day'] : [$_POST['schd_day']]) : [];
    $schd_start = $_POST['schd_start'];
    $schd_end = $_POST['schd_end'];

    // Basic validation
    if (empty($sy_id) || empty($program_id) || empty($year_level) || empty($subj_id) || empty($sec_id) || empty($inst_id) || empty($rm_id) || empty($schd_days) || empty($schd_start) || empty($schd_end)) {
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit;
    }

    if ($schd_start >= $schd_end) {
        $response['message'] = 'End time must be after start time.';
        echo json_encode($response);
        exit;
    }

    // Calculate duration in minutes
    $start_time = new DateTime($schd_start);
    $end_time = new DateTime($schd_end);
    $duration = $end_time->getTimestamp() - $start_time->getTimestamp();
    $schd_min = $duration / 60;
    $total_minutes_for_all_days = $schd_min * count($schd_days);

    try {
        $subj_stmt = $conn->prepare("SELECT dept_id, subj_lec, subj_lab, subj_code, subj_desc FROM subject WHERE subj_id = ?");
        $subj_stmt->bind_param("i", $subj_id);
        $subj_stmt->execute();
        $subject = $subj_stmt->get_result()->fetch_assoc();
        $subj_stmt->close();

        if (!$subject) {
            $response['message'] = 'Subject not found.';
            echo json_encode($response);
            exit;
        }

        // --- Prevent duplicate subject assignment for instructor in same SY & term ---
        $dup_stmt = $conn->prepare("
            SELECT schd_id 
            FROM schedule 
            WHERE sy_id = ? 
              AND schd_term = ? 
              AND inst_id = ? 
              AND subj_id = ? 
              AND schd_status = 'Active'
            LIMIT 1
        ");
        $dup_stmt->bind_param("iiii", $sy_id, $schd_term, $inst_id, $subj_id);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        $existing = $dup_result->fetch_assoc();
        $dup_stmt->close();

        if ($existing) {
            $response['message'] = 'This subject is already assigned to the selected instructor for the specified school year and term.';
            echo json_encode($response);
            exit;
        }

        // --- Check if subject has remaining hours and another instructor is already assigned ---
        if ($subject) {
            $subj_lec = (int)$subject['subj_lec'];
            $subj_lab = (int)$subject['subj_lab'];
            
            // Get total scheduled hours for this subject (all instructors combined)
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
            $total_sched_stmt->bind_param("iiii", $sy_id, $schd_term, $subj_id, $sec_id);
            $total_sched_stmt->execute();
            $total_sched_result = $total_sched_stmt->get_result();
            $total_scheduled = $total_sched_result->fetch_assoc();
            $total_sched_stmt->close();
            
            $total_scheduled_lec = (float)($total_scheduled['total_scheduled_lec_hours'] ?? 0);
            $total_scheduled_lab = (float)($total_scheduled['total_scheduled_lab_hours'] ?? 0);
            
            // Calculate remaining hours
            $remaining_lec = max(0, $subj_lec - $total_scheduled_lec);
            $remaining_lab = max(0, $subj_lab - $total_scheduled_lab);
            
            // Check if there are remaining hours for the schedule type being added
            $has_remaining_hours = false;
            if ($schd_type === 'Lec' && $remaining_lec > 0) {
                $has_remaining_hours = true;
            } elseif ($schd_type === 'Lab' && $remaining_lab > 0) {
                $has_remaining_hours = true;
            } elseif ($schd_type === 'Special') {
                // For Special type, check if either Lec or Lab has remaining hours
                $has_remaining_hours = ($remaining_lec > 0 || $remaining_lab > 0);
            }
            
            // Check if adding this schedule would exceed the subject's required hours
            $new_schedule_hours = $total_minutes_for_all_days / 60.0; // Convert minutes to hours
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
                $subj_code = $subject['subj_code'] ?? 'Unknown';
                $subj_desc = $subject['subj_desc'] ?? '';
                
                $response['message'] = sprintf(
                    'Subject Hour Limit Exceeded! The subject\'s required Lecture hours would be exceeded.\n\n' .
                    'Subject: %s - %s\n' .
                    'Required Lec: %s hours\n' .
                    'Currently Scheduled: %s hours\n' .
                    'New Schedule: %s hours\n' .
                    'Total Would Be: %s hours (exceeds by %s hours)\n\n' .
                    'You cannot schedule more hours than the subject requires.',
                    htmlspecialchars($subj_code),
                    htmlspecialchars($subj_desc),
                    $subj_lec,
                    round($total_scheduled_lec, 2),
                    round($new_schedule_hours, 2),
                    round($new_total_lec, 2),
                    $excess_lec
                );
                echo json_encode($response);
                exit;
            }
            
            // Check if Lab hours would be exceeded
            if ($subj_lab > 0 && $new_total_lab > $subj_lab) {
                $excess_lab = round($new_total_lab - $subj_lab, 2);
                $subj_code = $subject['subj_code'] ?? 'Unknown';
                $subj_desc = $subject['subj_desc'] ?? '';
                
                $response['message'] = sprintf(
                    'Subject Hour Limit Exceeded! The subject\'s required Laboratory hours would be exceeded.\n\n' .
                    'Subject: %s - %s\n' .
                    'Required Lab: %s hours\n' .
                    'Currently Scheduled: %s hours\n' .
                    'New Schedule: %s hours\n' .
                    'Total Would Be: %s hours (exceeds by %s hours)\n\n' .
                    'You cannot schedule more hours than the subject requires.',
                    htmlspecialchars($subj_code),
                    htmlspecialchars($subj_desc),
                    $subj_lab,
                    round($total_scheduled_lab, 2),
                    round($new_schedule_hours, 2),
                    round($new_total_lab, 2),
                    $excess_lab
                );
                echo json_encode($response);
                exit;
            }
            
            // Check if another instructor (different from current) is already assigned
            if ($has_remaining_hours) {
                $other_instructor_stmt = $conn->prepare("
                    SELECT DISTINCT s.inst_id, 
                           CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name
                    FROM schedule s
                    JOIN instructor i ON s.inst_id = i.inst_id
                    WHERE s.sy_id = ? 
                      AND s.schd_term = ? 
                      AND s.subj_id = ? 
                      AND s.sec_id = ?
                      AND s.schd_status = 'Active'
                      AND s.inst_id != ?
                    LIMIT 1
                ");
                $other_instructor_stmt->bind_param("iiiii", $sy_id, $schd_term, $subj_id, $sec_id, $inst_id);
                $other_instructor_stmt->execute();
                $other_instructor_result = $other_instructor_stmt->get_result();
                $other_instructor = $other_instructor_result->fetch_assoc();
                $other_instructor_stmt->close();
                
                if ($other_instructor) {
                    $response['message'] = sprintf(
                        'This subject is already assigned to instructor %s for the specified school year, term, and section. ' .
                        'There are still remaining class hours (Lec: %.2f hrs, Lab: %.2f hrs). ' .
                        'No other instructor can be scheduled for this subject while there are remaining hours.',
                        htmlspecialchars($other_instructor['instructor_name']),
                        $remaining_lec,
                        $remaining_lab
                    );
                    echo json_encode($response);
                    exit;
                }
            }
        }

        // --- Instructor Workload Validation (per subject department) ---
        $instructor_stmt = $conn->prepare("SELECT inst_status, instruction_hours FROM instructor WHERE inst_id = ?");
        $instructor_stmt->bind_param("i", $inst_id);
        $instructor_stmt->execute();
        $instructor = $instructor_stmt->get_result()->fetch_assoc();
        $instructor_stmt->close();

        if (!$instructor) {
            $response['message'] = 'Instructor not found.';
            echo json_encode($response);
            exit;
        }

        $subj_dept_id = (int) ($subject['dept_id'] ?? 0);
        $policy = ida_get_workload_policy_for_subject_dept($conn, (int) $inst_id, $subj_dept_id, $instructor);

        $current_minutes = ida_sum_scheduled_minutes_for_department(
            $conn,
            (int) $inst_id,
            (int) $sy_id,
            (int) $schd_term,
            $subj_dept_id,
            0
        );

        $new_total_minutes = $current_minutes + $total_minutes_for_all_days;
        $limit_minutes = (float) $policy['instruction_hours'] * 60;

        if (($policy['inst_status'] === 'Part-Time' || $policy['inst_status'] === 'Contractual') && $limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $current_hours = round($current_minutes / 60, 2);
            $new_schedule_hours = round($total_minutes_for_all_days / 60, 2);
            $total_hours = round($new_total_minutes / 60, 2);
            $excess_hours = round(($new_total_minutes - $limit_minutes) / 60, 2);

            $response['message'] = 'Workload Limit Exceeded! The instructor\'s workload limit for this department line is ' . $policy['instruction_hours'] . ' hours. ' .
                                  'Current load (this department): ' . $current_hours . ' hrs. ' .
                                  'New schedule: ' . $new_schedule_hours . ' hrs. ' .
                                  'Total would be: ' . $total_hours . ' hrs (exceeds by ' . $excess_hours . ' hrs).';
            echo json_encode($response);
            exit;
        }

        $is_overtime = 'No';
        if ($policy['inst_status'] === 'Regular' && $limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $is_overtime = 'Yes';
        }

        // --- Conflict Check for each day ---
        foreach ($schd_days as $schd_day) {
            // 1. Check for Room Conflict
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
                $conflict_stmt = $conn->prepare(
                    "SELECT s.schd_id, subj.subj_code, sec.sec_name, CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name, TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time 
                    FROM schedule s
                    JOIN subject subj ON s.subj_id = subj.subj_id
                    JOIN section sec ON s.sec_id = sec.sec_id
                    JOIN instructor i ON s.inst_id = i.inst_id
                    JOIN room r ON s.rm_id = r.rm_id
                    JOIN building b ON r.bd_id = b.bd_id
                    WHERE s.sy_id = ? AND s.schd_term = ? AND s.schd_day = ? AND s.rm_id = ? AND s.schd_status = 'Active' AND s.schd_start < ? AND s.schd_end > ?
                    AND UPPER(TRIM(b.bd_desc)) NOT IN ('VIRTUAL', 'SCHOOL GROUND', 'SCHOOL GROUNDS')"
                );
                $conflict_stmt->bind_param("iisiss", $sy_id, $schd_term, $schd_day, $rm_id, $schd_end, $schd_start);
                $conflict_stmt->execute();
                $result = $conflict_stmt->get_result();
                if ($result->num_rows > 0) {
                    $conflict = $result->fetch_assoc();
                    $dayName = ucfirst(strtolower($schd_day));
                    $response['message'] = sprintf(
                        'Room Conflict on %s: The selected room is already booked for %s (%s) with instructor %s from %s to %s.',
                        $dayName,
                        $conflict['subj_code'],
                        $conflict['sec_name'],
                        $conflict['instructor_name'],
                        $conflict['start_time'],
                        $conflict['end_time']
                    );
                    echo json_encode($response);
                    exit;
                }
                $conflict_stmt->close();
            }

            // 2. Check for Instructor Conflict
            $conflict_stmt = $conn->prepare(
                "SELECT s.schd_id, subj.subj_code, sec.sec_name, r.rm_name, TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time 
                FROM schedule s
                JOIN subject subj ON s.subj_id = subj.subj_id
                JOIN section sec ON s.sec_id = sec.sec_id
                JOIN room r ON s.rm_id = r.rm_id
                WHERE s.sy_id = ? AND s.schd_term = ? AND s.schd_day = ? AND s.inst_id = ? AND s.schd_status = 'Active' AND s.schd_start < ? AND s.schd_end > ?"
            );
            $conflict_stmt->bind_param("iisiss", $sy_id, $schd_term, $schd_day, $inst_id, $schd_end, $schd_start);
            $conflict_stmt->execute();
            $result = $conflict_stmt->get_result();
            if ($result->num_rows > 0) {
                $conflict = $result->fetch_assoc();
                $dayName = ucfirst(strtolower($schd_day));
                $response['message'] = sprintf(
                    'Instructor Conflict on %s: The selected instructor is already scheduled for %s (%s) in room %s from %s to %s.',
                    $dayName,
                    $conflict['subj_code'],
                    $conflict['sec_name'],
                    $conflict['rm_name'],
                    $conflict['start_time'],
                    $conflict['end_time']
                );
                echo json_encode($response);
                exit;
            }
            $conflict_stmt->close();

            // 3. Check for Section Conflict
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
                $conflict_stmt = $conn->prepare(
                    "SELECT s.schd_id, subj.subj_code, r.rm_name, CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name, TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time 
                    FROM schedule s
                    JOIN subject subj ON s.subj_id = subj.subj_id
                    JOIN room r ON s.rm_id = r.rm_id
                    JOIN instructor i ON s.inst_id = i.inst_id
                    JOIN building b ON r.bd_id = b.bd_id
                    WHERE s.sy_id = ? AND s.schd_term = ? AND s.schd_day = ? AND s.sec_id = ? AND s.schd_status = 'Active' AND s.schd_start < ? AND s.schd_end > ?
                    AND UPPER(TRIM(b.bd_desc)) NOT IN ('VIRTUAL', 'SCHOOL GROUND', 'SCHOOL GROUNDS')"
                );
                $conflict_stmt->bind_param("iisiss", $sy_id, $schd_term, $schd_day, $sec_id, $schd_end, $schd_start);
                $conflict_stmt->execute();
                $result = $conflict_stmt->get_result();
                if ($result->num_rows > 0) {
                    $conflict = $result->fetch_assoc();
                    $dayName = ucfirst(strtolower($schd_day));
                    $response['message'] = sprintf(
                        'Section Conflict on %s: The selected section is already scheduled for %s with %s in room %s from %s to %s.',
                        $dayName,
                        $conflict['subj_code'], $conflict['instructor_name'], $conflict['rm_name'], $conflict['start_time'], $conflict['end_time']
                    );
                    echo json_encode($response);
                    exit;
                }
                $conflict_stmt->close();
            }
        }

        // --- Room Request Access Validation ---
        // Check if this room has an active request from another department
        // Only check requests where req_date hasn't passed (active/future requests)
        // After req_date has passed, fixed schedules can be added
        $roomRequestQuery = "SELECT rr.req_id, rr.schd_day, rr.schd_start, rr.schd_end, 
                                    rr.req_date,
                                    d.dept_name as requester_dept_name,
                                    CONCAT(i.inst_fname, ' ', i.inst_lname) as requester_name,
                                    TIME_FORMAT(rr.schd_start, '%h:%i %p') as start_time_formatted,
                                    TIME_FORMAT(rr.schd_end, '%h:%i %p') as end_time_formatted,
                                    DATE_FORMAT(rr.req_date, '%Y-%m-%d %h:%i %p') as req_date_formatted
                             FROM room_request rr
                             JOIN room r ON rr.rm_id = r.rm_id
                             JOIN instructor i ON rr.inst_id = i.inst_id
                             JOIN account a ON i.inst_user = a.acc_user
                             LEFT JOIN department d ON a.dept_id = d.dept_id
                             WHERE rr.rm_id = ? 
                               AND rr.req_status = 'Accepted'
                               AND rr.req_date >= NOW()
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
            
            // Get current user's department to check if they're the requester
            $userInfo = getUserInfo();
            $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
            
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
                    'requester_name' => $request['requester_name'],
                    'req_date' => $request['req_date'],
                    'req_date_formatted' => $request['req_date_formatted']
                ];
            }
            
            // Get requester's department ID from the first request
            $getRequesterDeptQuery = "SELECT a.dept_id as requester_dept_id
                                     FROM room_request rr
                                     JOIN instructor i ON rr.inst_id = i.inst_id
                                     JOIN account a ON i.inst_user = a.acc_user
                                     WHERE rr.rm_id = ? 
                                       AND rr.req_status = 'Accepted'
                                       AND rr.req_date >= NOW()
                                       LIMIT 1";
            $getRequesterDeptStmt = $conn->prepare($getRequesterDeptQuery);
            $getRequesterDeptStmt->bind_param("i", $rm_id);
            $getRequesterDeptStmt->execute();
            $requesterDeptResult = $getRequesterDeptStmt->get_result();
            $requesterDeptRow = $requesterDeptResult->fetch_assoc();
            $requesterDeptId = $requesterDeptRow ? (int)$requesterDeptRow['requester_dept_id'] : 0;
            $getRequesterDeptStmt->close();
            
            // Check each selected day against room requests
            foreach ($schd_days as $schd_day) {
                $conflictFound = false;
                
                // If user is not from the requester's department, block fixed schedules completely
                // Fixed schedules can only be added after req_date has passed
                if ($userDeptId !== $requesterDeptId && $requesterDeptId > 0) {
                    $conflictFound = true;
                    $primaryRequest = reset($allowedTimeRanges);
                    $primaryDay = key($allowedTimeRanges);
                    $primaryRange = reset($primaryRequest);
                    $dayName = ucfirst(strtolower($schd_day));
                    if (strlen($dayName) > 3) {
                        $dayName = substr($dayName, 0, 3) . '.';
                    }
                    
                    $roomRequestStmt->close();
                    $response['message'] = sprintf(
                        'Room Access Restriction<br><br>' .
                        'This room has been requested by %s and is reserved until %s.<br><br>' .
                        'Fixed schedules cannot be added while there is an active room request.<br>' .
                        'Please wait until after the request date (%s) to add a fixed schedule.',
                        htmlspecialchars($primaryRange['requester_dept']),
                        htmlspecialchars($primaryRange['req_date_formatted']),
                        htmlspecialchars($primaryRange['req_date_formatted'])
                    );
                    echo json_encode($response);
                    exit;
                }
                
                // If user is from the requester's department, check if day and time match
                if ($userDeptId === $requesterDeptId) {
                    // Check if the schedule day is in allowed days
                    if (!in_array($schd_day, $allowedDays)) {
                        $conflictFound = true;
                        $dayName = ucfirst(strtolower($schd_day));
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
                        $response['message'] = sprintf(
                            'Room Access Restriction<br><br>' .
                            'This room has been requested by %s for %s only.<br><br>' .
                            'You attempted to schedule on: %s<br>' .
                            'Please change the day to: %s',
                            htmlspecialchars($primaryRange['requester_dept']),
                            htmlspecialchars($allowedDaysList),
                            htmlspecialchars($dayName),
                            htmlspecialchars($allowedDaysList)
                        );
                        echo json_encode($response);
                        exit;
                    } else {
                        // Day matches, check if time overlaps
                        $scheduleStart = new DateTime($schd_start);
                        $scheduleEnd = new DateTime($schd_end);
                        
                        foreach ($allowedTimeRanges[$schd_day] as $timeRange) {
                            $requestStartTime = new DateTime($timeRange['start']);
                            $requestEndTime = new DateTime($timeRange['end']);
                            
                            // Check if schedule time is within requested time range
                            if ($scheduleStart < $requestStartTime || $scheduleEnd > $requestEndTime) {
                                $conflictFound = true;
                                $dayName = ucfirst(strtolower($schd_day));
                                if (strlen($dayName) > 3) {
                                    $dayName = substr($dayName, 0, 3) . '.';
                                }
                                
                                $roomRequestStmt->close();
                                $response['message'] = sprintf(
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
                                );
                                echo json_encode($response);
                                exit;
                            }
                        }
                    }
                }
            }
        }
        $roomRequestStmt->close();

        // Get dept_id from subject
        $dept_stmt = $conn->prepare("SELECT dept_id FROM subject WHERE subj_id = ?");
        $dept_stmt->bind_param("i", $subj_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        $subject = $dept_result->fetch_assoc();
        $dept_id = $subject ? $subject['dept_id'] : null;
        $dept_stmt->close();

        // Create a schedule record for each selected day
        $stmt = $conn->prepare("INSERT INTO schedule (sy_id, program_id, year_level, dept_id, subj_id, sec_id, inst_id, rm_id, schd_type, schd_term, schd_day, schd_start, schd_end, schd_min, schd_status, is_overtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
        $created_count = 0;
        $errors = [];
        
        foreach ($schd_days as $schd_day) {
            $stmt->bind_param("iiiiiiiisssssis", $sy_id, $program_id, $year_level, $dept_id, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_term, $schd_day, $schd_start, $schd_end, $schd_min, $is_overtime);
            
            if ($stmt->execute()) {
                $created_count++;
            } else {
                $errors[] = "Failed to create schedule for " . ucfirst(strtolower($schd_day)) . ": " . $stmt->error;
            }
        }
        $stmt->close();
        
        if ($created_count > 0) {
            $response['success'] = true;
            $dayNames = array_map(function($day) {
                return ucfirst(strtolower($day));
            }, $schd_days);
            $response['message'] = 'Schedule created successfully for ' . implode(', ', $dayNames) . '!';
            if ($is_overtime === 'Yes') {
                $response['message'] .= ' Note: Instructor is now in overtime.';
            }
            if (count($errors) > 0) {
                $response['message'] .= ' However, some days failed: ' . implode('; ', $errors);
            }
        } else {
            $response['message'] = 'Failed to create schedules: ' . implode('; ', $errors);
        }
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>