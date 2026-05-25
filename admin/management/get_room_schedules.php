<?php
/**
 * Get schedules for a specific room
 * For room owners: Returns OCCUPIED schedules
 * For other departments: Returns AVAILABLE time slots
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

if (!hasPermission('view_schedules')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get user info
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Get room ID
$rm_id = (int)($_GET['rm_id'] ?? 0);

if ($rm_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Room ID is required.']);
    exit;
}

try {
    // First, verify the user has access to this room
    $roomCheckQuery = "SELECT r.rm_id, r.dept_id, d.dept_name 
                       FROM room r 
                       LEFT JOIN department d ON r.dept_id = d.dept_id
                       WHERE r.rm_id = ?";
    $roomCheckStmt = $conn->prepare($roomCheckQuery);
    $roomCheckStmt->bind_param("i", $rm_id);
    $roomCheckStmt->execute();
    $roomResult = $roomCheckStmt->get_result();
    $room = $roomResult->fetch_assoc();
    $roomCheckStmt->close();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room not found.']);
        exit;
    }
    
    // Check access
    $hasAccess = false;
    if ($isAdminSupport) {
        $hasAccess = true;
    } elseif ($room['dept_id'] == $userDeptId) {
        $hasAccess = true;
    } else {
        // Check if access has been granted
        $accessCheckQuery = "SELECT 1 FROM room_access 
                            WHERE rm_id = ? AND granted_to_dept_id = ? AND status = 'Active'";
        $accessCheckStmt = $conn->prepare($accessCheckQuery);
        $accessCheckStmt->bind_param("ii", $rm_id, $userDeptId);
        $accessCheckStmt->execute();
        $accessResult = $accessCheckStmt->get_result();
        $hasAccess = $accessResult->num_rows > 0;
        $accessCheckStmt->close();
    }
    
    if (!$hasAccess) {
        echo json_encode(['success' => false, 'message' => 'You do not have access to view schedules for this room.']);
        exit;
    }
    
    // Determine if user is the room owner (from same department)
    $isRoomOwner = false;
    if ($isAdminSupport) {
        $isRoomOwner = true; // Admin Support sees occupied schedules by default
    } elseif ($room['dept_id'] == $userDeptId) {
        $isRoomOwner = true;
    }
    
    // Get optional filters
    $syFilter = $_GET['sy'] ?? '';
    $termFilter = $_GET['term'] ?? '';
    $dateFilter = $_GET['date'] ?? ''; // Filter by specific date for room requests
    
    if ($isRoomOwner) {
        // For room owner: Show OCCUPIED schedules
        $query = "
            SELECT 
                s.schd_id,
                s.schd_start,
                s.schd_end,
                s.schd_type,
                s.schd_day,
                TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time,
                TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time,
                subj.subj_code,
                subj.subj_desc,
                sec.sec_name,
                CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
                p.program_name,
                p.program_code,
                cls.class_lvl as year_level,
                sy.sy_name,
                s.schd_term
            FROM schedule s
            JOIN subject subj ON s.subj_id = subj.subj_id
            JOIN section sec ON s.sec_id = sec.sec_id
            JOIN class cls ON sec.class_id = cls.class_id
            JOIN program p ON subj.program_id = p.program_id
            JOIN instructor i ON s.inst_id = i.inst_id
            JOIN schoolyear sy ON s.sy_id = sy.sy_id
            WHERE s.rm_id = ? AND s.schd_status = 'Active'";
        
        $params = [$rm_id];
        $types = 'i';
        
        if (!empty($syFilter)) {
            $query .= " AND s.sy_id = ?";
            $params[] = $syFilter;
            $types .= 'i';
        }
        
        if (!empty($termFilter)) {
            $query .= " AND s.schd_term = ?";
            $params[] = $termFilter;
            $types .= 'i';
        }
        
        $query .= " ORDER BY s.schd_day, s.schd_start";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedules = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Also calculate available time slots for room owners
        // Define working hours (7:00 AM to 8:30 PM)
        $workStart = strtotime('07:00:00');
        $workEnd = strtotime('20:30:00');
        
        // Group occupied schedules by day
        $occupiedByDay = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
            'Fri' => [],
            'Sat' => [],
            'Sun' => []
        ];
        
        foreach ($schedules as $schedule) {
            $day = $schedule['schd_day'];
            if (isset($occupiedByDay[$day])) {
                $occupiedByDay[$day][] = [
                    'start' => strtotime($schedule['schd_start']),
                    'end' => strtotime($schedule['schd_end'])
                ];
            }
        }
        
        // Always include accepted and pending room requests as occupied slots
        // Room requests are recurring (they have schd_day), so they apply to all matching days
        // Exclude expired Make Up Class requests (expired if next Monday after request date has passed)
        $requestQuery = "SELECT schd_day, schd_start, schd_end 
                         FROM room_request 
                         WHERE rm_id = ? 
                           AND req_status IN ('Accepted', 'Pending')
                           AND schd_day IS NOT NULL
                           AND schd_start IS NOT NULL
                           AND schd_end IS NOT NULL
                           AND (
                               req_comment NOT LIKE '%[Class Type: Make Up Class]%'
                               OR
                               (
                                   req_comment LIKE '%[Class Type: Make Up Class]%'
                                   AND DATE(req_date) + INTERVAL (
                                       CASE DAYOFWEEK(DATE(req_date))
                                           WHEN 2 THEN 7  -- Monday: next Monday is 7 days away
                                           ELSE (9 - DAYOFWEEK(DATE(req_date))) % 7
                                       END
                                   ) DAY > CURDATE()
                               )
                           )";
        $requestStmt = $conn->prepare($requestQuery);
        $requestStmt->bind_param("i", $rm_id);
        $requestStmt->execute();
        $requestResult = $requestStmt->get_result();
        
        while ($request = $requestResult->fetch_assoc()) {
            $requestDay = $request['schd_day'];
            if (isset($occupiedByDay[$requestDay])) {
                $occupiedByDay[$requestDay][] = [
                    'start' => strtotime($request['schd_start']),
                    'end' => strtotime($request['schd_end'])
                ];
            }
        }
        $requestStmt->close();
        
        // Sort and merge occupied times for each day (schedules + requests)
        foreach ($occupiedByDay as $day => &$times) {
            usort($times, function($a, $b) {
                return $a['start'] - $b['start'];
            });
            
            if (count($times) > 1) {
                $merged = [];
                $current = $times[0];
                for ($i = 1; $i < count($times); $i++) {
                    if ($times[$i]['start'] <= $current['end']) {
                        // Overlapping or adjacent
                        $current['end'] = max($current['end'], $times[$i]['end']);
                    } else {
                        $merged[] = $current;
                        $current = $times[$i];
                    }
                }
                $merged[] = $current;
                $times = $merged;
            }
        }
        unset($times);
        
        // Calculate available time slots for each day
        $availableSlots = [];
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        // If date filter is provided, only show available slots for that date's day of week
        $filterDay = null;
        if (!empty($dateFilter)) {
            try {
                $filterDateObj = new DateTime($dateFilter);
                $dayOfWeek = (int)$filterDateObj->format('w'); // 0 = Sunday, 1 = Monday, etc.
                $dayMap = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                $filterDay = $dayMap[$dayOfWeek] ?? null;
            } catch (Exception $e) {
                error_log("Error parsing date filter: " . $e->getMessage());
            }
        }
        
        foreach ($days as $day) {
            // If date filter is set, only process the day that matches the filter date
            if ($filterDay !== null && $day !== $filterDay) {
                continue;
            }
            $occupied = $occupiedByDay[$day];
            $available = [];
            
            // If no occupied times, the whole day is available
            if (empty($occupied)) {
                $available[] = [
                    'start' => $workStart,
                    'end' => $workEnd,
                    'start_formatted' => date('h:i A', $workStart),
                    'end_formatted' => date('h:i A', $workEnd)
                ];
            } else {
                // Check time before first occupied slot
                if ($occupied[0]['start'] > $workStart) {
                    $available[] = [
                        'start' => $workStart,
                        'end' => $occupied[0]['start'],
                        'start_formatted' => date('h:i A', $workStart),
                        'end_formatted' => date('h:i A', $occupied[0]['start'])
                    ];
                }
                
                // Check gaps between occupied slots
                for ($i = 0; $i < count($occupied) - 1; $i++) {
                    $currentEnd = $occupied[$i]['end'];
                    $nextStart = $occupied[$i + 1]['start'];
                    
                    if ($nextStart > $currentEnd) {
                        $available[] = [
                            'start' => $currentEnd,
                            'end' => $nextStart,
                            'start_formatted' => date('h:i A', $currentEnd),
                            'end_formatted' => date('h:i A', $nextStart)
                        ];
                    }
                }
                
                // Check time after last occupied slot
                $lastOccupied = end($occupied);
                if ($lastOccupied['end'] < $workEnd) {
                    $available[] = [
                        'start' => $lastOccupied['end'],
                        'end' => $workEnd,
                        'start_formatted' => date('h:i A', $lastOccupied['end']),
                        'end_formatted' => date('h:i A', $workEnd)
                    ];
                }
            }
            
            // Add all available slots (even if empty)
            $availableSlots[$day] = $available;
        }
        
        echo json_encode([
            'success' => true,
            'view_type' => 'occupied', // Indicate this is occupied schedules view
            'data' => $schedules, // Occupied schedules
            'available_slots' => $availableSlots, // Available time slots
            'room' => [
                'rm_id' => $room['rm_id'],
                'dept_name' => $room['dept_name'] ?? 'Shared'
            ],
            'is_room_owner' => $isRoomOwner // Indicate if user is the actual room owner
        ]);
        
    } else {
        // This branch should no longer be reached since we show occupied for all with access
        // But keeping it as fallback for edge cases: Show AVAILABLE time slots
        $query = "
            SELECT 
                s.schd_day,
                s.schd_start,
                s.schd_end
            FROM schedule s
            WHERE s.rm_id = ? AND s.schd_status = 'Active'";
        
        $params = [$rm_id];
        $types = 'i';
        
        if (!empty($syFilter)) {
            $query .= " AND s.sy_id = ?";
            $params[] = $syFilter;
            $types .= 'i';
        }
        
        if (!empty($termFilter)) {
            $query .= " AND s.schd_term = ?";
            $params[] = $termFilter;
            $types .= 'i';
        }
        
        $query .= " ORDER BY s.schd_day, s.schd_start";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $occupiedSchedules = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Define working hours (7:00 AM to 8:00 PM)
        $workStart = strtotime('07:00:00');
        $workEnd = strtotime('20:00:00');
        
        // Group occupied schedules by day
        $occupiedByDay = [
            'Mon' => [],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
            'Fri' => [],
            'Sat' => [],
            'Sun' => []
        ];
        
        foreach ($occupiedSchedules as $schedule) {
            $day = $schedule['schd_day'];
            if (isset($occupiedByDay[$day])) {
                $occupiedByDay[$day][] = [
                    'start' => strtotime($schedule['schd_start']),
                    'end' => strtotime($schedule['schd_end'])
                ];
            }
        }
        
        // Always include accepted and pending room requests as occupied slots
        // Room requests are recurring (they have schd_day), so they apply to all matching days
        // Exclude expired Make Up Class requests (expired if next Monday after request date has passed)
        $requestQuery = "SELECT schd_day, schd_start, schd_end
                        FROM room_request
                        WHERE rm_id = ? 
                          AND req_status IN ('Accepted', 'Pending')
                          AND schd_day IS NOT NULL
                          AND schd_start IS NOT NULL
                          AND schd_end IS NOT NULL
                          AND (
                              req_comment NOT LIKE '%[Class Type: Make Up Class]%'
                              OR
                              (
                                  req_comment LIKE '%[Class Type: Make Up Class]%'
                                  AND DATE(req_date) + INTERVAL (
                                      CASE DAYOFWEEK(DATE(req_date))
                                          WHEN 2 THEN 7  -- Monday: next Monday is 7 days away
                                          ELSE (9 - DAYOFWEEK(DATE(req_date))) % 7
                                      END
                                  ) DAY > CURDATE()
                              )
                          )";
        $requestStmt = $conn->prepare($requestQuery);
        $requestStmt->bind_param("i", $rm_id);
        $requestStmt->execute();
        $requestResult = $requestStmt->get_result();
        
        while ($request = $requestResult->fetch_assoc()) {
            $day = $request['schd_day'];
            if (isset($occupiedByDay[$day])) {
                $occupiedByDay[$day][] = [
                    'start' => strtotime($request['schd_start']),
                    'end' => strtotime($request['schd_end'])
                ];
            }
        }
        $requestStmt->close();
        
        // Sort occupied times for each day
        foreach ($occupiedByDay as $day => &$times) {
            usort($times, function($a, $b) {
                return $a['start'] - $b['start'];
            });
            
            // Merge overlapping time slots
            if (count($times) > 1) {
                $merged = [];
                $current = $times[0];
                for ($i = 1; $i < count($times); $i++) {
                    if ($times[$i]['start'] <= $current['end']) {
                        // Overlapping or adjacent, merge
                        $current['end'] = max($current['end'], $times[$i]['end']);
                    } else {
                        // No overlap, add current and start new
                        $merged[] = $current;
                        $current = $times[$i];
                    }
                }
                $merged[] = $current;
                $times = $merged;
            }
        }
        unset($times);
        
        // Calculate available time slots for each day
        $availableSlots = [];
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        // If date filter is provided, only show available slots for that date's day of week
        $filterDay = null;
        if (!empty($dateFilter)) {
            try {
                $filterDateObj = new DateTime($dateFilter);
                $dayOfWeek = (int)$filterDateObj->format('w'); // 0 = Sunday, 1 = Monday, etc.
                $dayMap = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                $filterDay = $dayMap[$dayOfWeek] ?? null;
            } catch (Exception $e) {
                error_log("Error parsing date filter: " . $e->getMessage());
            }
        }
        
        foreach ($days as $day) {
            // If date filter is set, only process the day that matches the filter date
            if ($filterDay !== null && $day !== $filterDay) {
                continue;
            }
            $occupied = $occupiedByDay[$day];
            $available = [];
            
            // If no occupied times, the whole day is available
            if (empty($occupied)) {
                $available[] = [
                    'start' => $workStart,
                    'end' => $workEnd,
                    'start_formatted' => date('h:i A', $workStart),
                    'end_formatted' => date('h:i A', $workEnd)
                ];
            } else {
                // Check time before first occupied slot
                if ($occupied[0]['start'] > $workStart) {
                    $available[] = [
                        'start' => $workStart,
                        'end' => $occupied[0]['start'],
                        'start_formatted' => date('h:i A', $workStart),
                        'end_formatted' => date('h:i A', $occupied[0]['start'])
                    ];
                }
                
                // Check gaps between occupied slots
                for ($i = 0; $i < count($occupied) - 1; $i++) {
                    $currentEnd = $occupied[$i]['end'];
                    $nextStart = $occupied[$i + 1]['start'];
                    
                    if ($nextStart > $currentEnd) {
                        $available[] = [
                            'start' => $currentEnd,
                            'end' => $nextStart,
                            'start_formatted' => date('h:i A', $currentEnd),
                            'end_formatted' => date('h:i A', $nextStart)
                        ];
                    }
                }
                
                // Check time after last occupied slot
                $lastOccupied = end($occupied);
                if ($lastOccupied['end'] < $workEnd) {
                    $available[] = [
                        'start' => $lastOccupied['end'],
                        'end' => $workEnd,
                        'start_formatted' => date('h:i A', $lastOccupied['end']),
                        'end_formatted' => date('h:i A', $workEnd)
                    ];
                }
            }
            
            // Only add day if there are available slots
            if (!empty($available)) {
                $availableSlots[$day] = $available;
            }
        }
        
        echo json_encode([
            'success' => true,
            'view_type' => 'available', // Indicate this is available slots view
            'is_room_owner' => false, // User is not the room owner, so show available slots
            'data' => $availableSlots,
            'room' => [
                'rm_id' => $room['rm_id'],
                'dept_name' => $room['dept_name'] ?? 'Shared'
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error fetching room schedules: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching room schedules.'
    ]);
}
?>
