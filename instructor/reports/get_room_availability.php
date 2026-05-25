<?php
/**
 * Get Room Availability
 * Returns available and occupied rooms based on schedules and access grants
 * For instructors to view room availability with filters
 */

ob_start();
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['acc_id'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $userInfo = getUserInfo();
    $userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
    
    if ($userDeptId <= 0) {
        echo json_encode(['success' => false, 'message' => 'No department assigned.']);
        exit();
    }
    
    // Get filters
    $filterDate = $_GET['date'] ?? date('Y-m-d'); // Default to today
    $filterStartTime = $_GET['start_time'] ?? null;
    $filterEndTime = $_GET['end_time'] ?? null;
    $filterDeptId = isset($_GET['dept_id']) && $_GET['dept_id'] !== '' ? (int)$_GET['dept_id'] : null;
    $filterRoomType = $_GET['room_type'] ?? null;
    
    // Get day of week from date
    $dayOfWeek = date('l', strtotime($filterDate));
    $dayMapping = [
        'Monday' => 'Mon',
        'Tuesday' => 'Tue',
        'Wednesday' => 'Wed',
        'Thursday' => 'Thu',
        'Friday' => 'Fri',
        'Saturday' => 'Sat',
        'Sunday' => 'Sun'
    ];
    $filterDay = $dayMapping[$dayOfWeek] ?? 'Mon';
    
    // Get current time for availability check
    $currentTime = date('H:i:s');
    $currentDateTime = new DateTime();
    
    // Build query to get all rooms accessible to user's department
    // Rooms accessible if:
    // 1. Room belongs to user's department (r.dept_id = user_dept_id)
    // 2. OR room_access grants access to user's department (room_access.granted_to_dept_id = user_dept_id)
    $roomQuery = "
        SELECT DISTINCT
            r.rm_id,
            r.rm_name,
            r.rm_type,
            r.rm_capacity,
            r.rm_features,
            r.dept_id as room_dept_id,
            d.dept_name as room_dept_name,
            b.bd_id,
            b.bd_desc as building_name,
            CASE 
                WHEN r.dept_id = ? THEN 'Own'
                ELSE 'Shared'
            END as access_type
        FROM room r
        JOIN building b ON r.bd_id = b.bd_id
        LEFT JOIN department d ON r.dept_id = d.dept_id
        WHERE r.rm_status = 'Used'
        AND (
            r.dept_id = ?
            OR EXISTS (
                SELECT 1 FROM room_access ra
                WHERE ra.rm_id = r.rm_id
                AND ra.granted_to_dept_id = ?
                AND ra.status = 'Active'
            )
        )
    ";
    
    $roomParams = [$userDeptId, $userDeptId, $userDeptId];
    $roomTypes = 'iii';
    
    // Apply filters
    if ($filterRoomType) {
        $roomQuery .= " AND r.rm_type = ?";
        $roomParams[] = $filterRoomType;
        $roomTypes .= 's';
    }
    
    if ($filterDeptId) {
        $roomQuery .= " AND r.dept_id = ?";
        $roomParams[] = $filterDeptId;
        $roomTypes .= 'i';
    }
    
    $roomQuery .= " ORDER BY b.bd_desc, r.rm_name";
    
    $stmt = $conn->prepare($roomQuery);
    $stmt->bind_param($roomTypes, ...$roomParams);
    $stmt->execute();
    $result = $stmt->get_result();
    $rooms = [];
    
    // Get active school year and semester for schedule filtering
    $syStmt = $conn->query("SELECT sy_id, term FROM active_school_year_semester LIMIT 1");
    $syData = $syStmt->fetch_assoc();
    $activeSyId = $syData['sy_id'] ?? null;
    $activeTerm = $syData['term'] ?? null;
    $syStmt->close();
    
    while ($room = $result->fetch_assoc()) {
        $rmId = (int)$room['rm_id'];
        
        // Check for conflicts at the specified date/time
        // Get schedules for this room on the specified day and active SY/semester
        $scheduleQuery = "
            SELECT 
                s.schd_id,
                s.schd_day,
                s.schd_start,
                s.schd_end,
                s.schd_status,
                subj.subj_code,
                subj.subj_desc,
                sec.sec_name,
                CONCAT(a.fname, ' ', a.lname) as instructor_name
            FROM schedule s
            LEFT JOIN subject subj ON s.subj_id = subj.subj_id
            LEFT JOIN section sec ON s.sec_id = sec.sec_id
            LEFT JOIN instructor i ON s.inst_id = i.inst_id
            LEFT JOIN account a ON i.inst_user = a.acc_user
            WHERE s.rm_id = ?
            AND s.schd_day = ?
            AND s.schd_status = 'Active'
        ";
        
        $scheduleParams = [$rmId, $filterDay];
        $scheduleTypes = 'is';
        
        if ($activeSyId) {
            $scheduleQuery .= " AND s.sy_id = ?";
            $scheduleParams[] = $activeSyId;
            $scheduleTypes .= 'i';
        }
        
        if ($activeTerm) {
            $scheduleQuery .= " AND s.schd_term = ?";
            $scheduleParams[] = $activeTerm;
            $scheduleTypes .= 'i';
        }
        
        // If time filters provided, check for conflicts
        if ($filterStartTime && $filterEndTime) {
            $scheduleQuery .= " AND (
                (s.schd_start <= ? AND s.schd_end > ?)
                OR (s.schd_start < ? AND s.schd_end >= ?)
                OR (s.schd_start >= ? AND s.schd_end <= ?)
            )";
            $scheduleParams[] = $filterEndTime;
            $scheduleParams[] = $filterStartTime;
            $scheduleParams[] = $filterEndTime;
            $scheduleParams[] = $filterStartTime;
            $scheduleParams[] = $filterStartTime;
            $scheduleParams[] = $filterEndTime;
            $scheduleTypes .= 'ssssss';
        }
        
        $scheduleQuery .= " ORDER BY s.schd_start";
        
        $scheduleStmt = $conn->prepare($scheduleQuery);
        $scheduleStmt->bind_param($scheduleTypes, ...$scheduleParams);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();
        
        $schedules = [];
        $isOccupied = false;
        $currentOccupancy = null;
        
        while ($schedule = $scheduleResult->fetch_assoc()) {
            $scheduleStart = $schedule['schd_start'];
            $scheduleEnd = $schedule['schd_end'];
            
            // Check if this schedule is currently active (for current date/time)
            if ($filterDate === date('Y-m-d')) {
                $scheduleStartTime = strtotime($scheduleStart);
                $scheduleEndTime = strtotime($scheduleEnd);
                $currentTimeStamp = strtotime($currentTime);
                
                if ($currentTimeStamp >= $scheduleStartTime && $currentTimeStamp < $scheduleEndTime) {
                    $isOccupied = true;
                    $currentOccupancy = $schedule;
                }
            }
            
            // If time filters provided, check if this schedule conflicts
            if ($filterStartTime && $filterEndTime) {
                $filterStart = strtotime($filterStartTime);
                $filterEnd = strtotime($filterEndTime);
                $schdStart = strtotime($scheduleStart);
                $schdEnd = strtotime($scheduleEnd);
                
                // Check for overlap
                if (!($filterEnd <= $schdStart || $filterStart >= $schdEnd)) {
                    $isOccupied = true;
                    if (!$currentOccupancy) {
                        $currentOccupancy = $schedule;
                    }
                }
            }
            
            $schedules[] = [
                'schd_id' => (int)$schedule['schd_id'],
                'schd_day' => $schedule['schd_day'],
                'schd_start' => $schedule['schd_start'],
                'schd_end' => $schedule['schd_end'],
                'subj_code' => $schedule['subj_code'] ?? '',
                'subj_desc' => $schedule['subj_desc'] ?? '',
                'sec_name' => $schedule['sec_name'] ?? '',
                'instructor_name' => $schedule['instructor_name'] ?? ''
            ];
        }
        $scheduleStmt->close();
        
        // Also check room requests for the specified date (if date filter is provided)
        // These should also be considered as occupied/unavailable
        $roomRequests = [];
        if ($filterDate) {
            $requestQuery = "SELECT schd_day, schd_start, schd_end, req_comment
                            FROM room_request
                            WHERE rm_id = ? 
                              AND DATE(req_date) = ?
                              AND req_status = 'Accepted'
                              AND (
                                  -- Regular Class: Always included (no automatic expiration)
                                  req_comment NOT LIKE '%[Class Type: Make Up Class]%'
                                  OR
                                  -- Make Up Class: Check both date expiration AND time expiration
                                  (
                                      req_comment LIKE '%[Class Type: Make Up Class]%'
                                      -- Check if date hasn't expired (next Monday hasn't passed)
                                      AND DATE(req_date) + INTERVAL (
                                          CASE DAYOFWEEK(DATE(req_date))
                                              WHEN 2 THEN 7
                                              ELSE (9 - DAYOFWEEK(DATE(req_date))) % 7
                                          END
                                      ) DAY > CURDATE()
                                      -- For Make Up Class: If today, check if time slot has passed
                                      AND (
                                          -- If request date is NOT today, include it (future date)
                                          DATE(req_date) != CURDATE()
                                          OR
                                          -- If request date IS today, only include if current time < schd_end
                                          (DATE(req_date) = CURDATE() AND TIME(NOW()) < schd_end)
                                      )
                                  )
                              )";
            $requestStmt = $conn->prepare($requestQuery);
            $requestStmt->bind_param("is", $rmId, $filterDate);
            $requestStmt->execute();
            $requestResult = $requestStmt->get_result();
            
            while ($request = $requestResult->fetch_assoc()) {
                // Only include requests that match the day of week for the filter date
                if ($request['schd_day'] === $filterDay) {
                    $roomRequests[] = [
                        'schd_day' => $request['schd_day'],
                        'schd_start' => $request['schd_start'],
                        'schd_end' => $request['schd_end'],
                        'req_comment' => $request['req_comment'] ?? '',
                        'type' => 'room_request' // Mark as room request, not regular schedule
                    ];
                }
            }
            $requestStmt->close();
        }
        
        // Combine schedules and room requests for occupancy check
        $allOccupied = array_merge($schedules, $roomRequests);
        
        // Calculate available time slots for the day
        // Define working hours (7:00 AM to 8:30 PM)
        $workStart = strtotime('07:00:00');
        $workEnd = strtotime('20:30:00');
        $availableSlots = [];
        
        // Group occupied schedules and room requests by time
        $occupiedTimes = [];
        foreach ($allOccupied as $occupancy) {
            $occupiedTimes[] = [
                'start' => strtotime($occupancy['schd_start']),
                'end' => strtotime($occupancy['schd_end'])
            ];
        }
        
        // Sort occupied times
        usort($occupiedTimes, function($a, $b) {
            return $a['start'] - $b['start'];
        });
        
        // Merge overlapping time slots
        if (count($occupiedTimes) > 1) {
            $merged = [];
            $current = $occupiedTimes[0];
            for ($i = 1; $i < count($occupiedTimes); $i++) {
                if ($occupiedTimes[$i]['start'] <= $current['end']) {
                    // Overlapping or adjacent, merge
                    $current['end'] = max($current['end'], $occupiedTimes[$i]['end']);
                } else {
                    // No overlap, add current and start new
                    $merged[] = $current;
                    $current = $occupiedTimes[$i];
                }
            }
            $merged[] = $current;
            $occupiedTimes = $merged;
        }
        
        // Calculate gaps (available slots)
        if (empty($occupiedTimes)) {
            // Whole day is available
            $availableSlots[] = [
                'start' => date('H:i:s', $workStart),
                'end' => date('H:i:s', $workEnd),
                'start_formatted' => date('h:i A', $workStart),
                'end_formatted' => date('h:i A', $workEnd)
            ];
        } else {
            // Check time before first occupied slot
            if ($occupiedTimes[0]['start'] > $workStart) {
                $availableSlots[] = [
                    'start' => date('H:i:s', $workStart),
                    'end' => date('H:i:s', $occupiedTimes[0]['start']),
                    'start_formatted' => date('h:i A', $workStart),
                    'end_formatted' => date('h:i A', $occupiedTimes[0]['start'])
                ];
            }
            
            // Check gaps between occupied slots
            for ($i = 0; $i < count($occupiedTimes) - 1; $i++) {
                $currentEnd = $occupiedTimes[$i]['end'];
                $nextStart = $occupiedTimes[$i + 1]['start'];
                
                if ($nextStart > $currentEnd) {
                    $availableSlots[] = [
                        'start' => date('H:i:s', $currentEnd),
                        'end' => date('H:i:s', $nextStart),
                        'start_formatted' => date('h:i A', $currentEnd),
                        'end_formatted' => date('h:i A', $nextStart)
                    ];
                }
            }
            
            // Check time after last occupied slot
            $lastOccupied = end($occupiedTimes);
            if ($lastOccupied['end'] < $workEnd) {
                $availableSlots[] = [
                    'start' => date('H:i:s', $lastOccupied['end']),
                    'end' => date('H:i:s', $workEnd),
                    'start_formatted' => date('h:i A', $lastOccupied['end']),
                    'end_formatted' => date('h:i A', $workEnd)
                ];
            }
        }
        
        // Determine availability status
        $availability = 'Available';
        if ($isOccupied) {
            $availability = 'Occupied';
        } elseif (!empty($schedules)) {
            // Has schedules but not currently occupied (if time filter provided)
            if ($filterStartTime && $filterEndTime) {
                $availability = 'Available'; // Available at the requested time
            } else {
                $availability = 'Scheduled'; // Has schedules but not at current time
            }
        }
        
        $rooms[] = [
            'rm_id' => $rmId,
            'rm_name' => $room['rm_name'],
            'rm_type' => $room['rm_type'],
            'rm_capacity' => (int)$room['rm_capacity'],
            'rm_features' => $room['rm_features'] ?? '',
            'building_id' => (int)$room['bd_id'],
            'building_name' => $room['building_name'],
            'dept_id' => (int)$room['room_dept_id'],
            'dept_name' => $room['room_dept_name'] ?? '',
            'access_type' => $room['access_type'],
            'availability' => $availability,
            'is_occupied' => $isOccupied,
            'current_occupancy' => $currentOccupancy,
            'schedules' => $schedules,
            'room_requests' => $roomRequests, // Room requests for the filtered date
            'schedule_count' => count($schedules),
            'room_request_count' => count($roomRequests),
            'available_slots' => $availableSlots // Available time slots for the day (excluding both schedules and room requests)
        ];
    }
    $stmt->close();
    
    // Separate rooms into available and occupied
    $availableRooms = array_filter($rooms, function($room) {
        return !$room['is_occupied'];
    });
    $occupiedRooms = array_filter($rooms, function($room) {
        return $room['is_occupied'];
    });
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'all_rooms' => array_values($rooms),
            'available_rooms' => array_values($availableRooms),
            'occupied_rooms' => array_values($occupiedRooms),
            'filters' => [
                'date' => $filterDate,
                'day' => $filterDay,
                'start_time' => $filterStartTime,
                'end_time' => $filterEndTime,
                'dept_id' => $filterDeptId,
                'room_type' => $filterRoomType
            ],
            'summary' => [
                'total' => count($rooms),
                'available' => count($availableRooms),
                'occupied' => count($occupiedRooms)
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}
?>

