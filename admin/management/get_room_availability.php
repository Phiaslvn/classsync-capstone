<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'availableSlots' => []];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rm_id = (int)($_GET['rm_id'] ?? 0);
    $sy_id = (int)($_GET['sy_id'] ?? 0);
    $schd_term = (int)($_GET['term'] ?? 0);

    if (empty($rm_id) || empty($sy_id) || empty($schd_term)) {
        $response['message'] = 'Room ID, School Year, and Term required';
        echo json_encode($response);
        exit;
    }

    try {
        // Get all days of the week
        $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        // Standard business hours
        $startHour = 7;  // 7:00 AM
        $endHour = 20;   // 8:00 PM (extended for evening classes)
        $preferredStartHour = 8; // Prefer 8:00 AM start times
        $minSlotDuration = 60; // Minimum 60 minutes per slot
        $preferredSlotDuration = 90; // Prefer 90-minute slots

        $availableSlots = [];

        // For each day of the week
        foreach ($daysOfWeek as $day) {
            // Get all schedules for this room on this day
            // Also exclude the current schedule being edited (if schd_id is provided)
            $schd_id = (int)($_GET['schd_id'] ?? 0);
            $query = "
                SELECT schd_start, schd_end
                FROM schedule
                WHERE rm_id = ? 
                  AND sy_id = ? 
                  AND schd_term = ? 
                  AND schd_day = ?
                  AND schd_status = 'Active'
            ";
            if ($schd_id > 0) {
                $query .= " AND schd_id != ?";
            }
            $query .= " ORDER BY schd_start ASC";
            
            $schedStmt = $conn->prepare($query);
            if ($schd_id > 0) {
                $schedStmt->bind_param("iiisi", $rm_id, $sy_id, $schd_term, $day, $schd_id);
            } else {
                $schedStmt->bind_param("iiss", $rm_id, $sy_id, $schd_term, $day);
            }
            $schedStmt->execute();
            $schedResult = $schedStmt->get_result();
            $schedules = $schedResult->fetch_all(MYSQLI_ASSOC);
            $schedStmt->close();

            // Get all accepted and pending room requests for this room on this day
            // These should also be treated as occupied time slots
            // Exclude expired Make Up Class requests (expired if next Monday after request date has passed OR time has passed today)
            $requestQuery = "
                SELECT schd_start, schd_end
                FROM room_request
                WHERE rm_id = ? 
                  AND schd_day = ?
                  AND req_status IN ('Accepted', 'Pending')
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
                                  WHEN 2 THEN 7  -- Monday: next Monday is 7 days away
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
                  )
            ";
            $requestStmt = $conn->prepare($requestQuery);
            $requestStmt->bind_param("is", $rm_id, $day);
            $requestStmt->execute();
            $requestResult = $requestStmt->get_result();
            $roomRequests = $requestResult->fetch_all(MYSQLI_ASSOC);
            $requestStmt->close();

            // Merge schedules and room requests into a single array
            // Room requests should be treated the same as regular schedules for availability checking
            $allOccupiedSlots = array_merge($schedules, $roomRequests);
            
            // Sort by start time
            usort($allOccupiedSlots, function($a, $b) {
                return strcmp($a['schd_start'], $b['schd_start']);
            });

            // Find available slots for this day
            $daySlots = [];

            // Use the merged list of schedules and room requests
            $schedules = $allOccupiedSlots;

            if (empty($schedules)) {
                // No schedules on this day, recommend preferred start time
                $availableStart = sprintf('%02d:00', $preferredStartHour);
                // Properly calculate end time by adding 90 minutes (1 hour 30 minutes)
                $startTimestamp = mktime($preferredStartHour, 0, 0);
                $endTimestamp = $startTimestamp + ($preferredSlotDuration * 60); // Add 90 minutes in seconds
                $availableEnd = date('H:i', $endTimestamp);
                $daySlots[] = [
                    'day' => $day,
                    'startTime' => $availableStart,
                    'endTime' => $availableEnd,
                    'duration' => $preferredSlotDuration,
                    'priority' => 10 // High priority - no conflicts
                ];
            } else {
                // Check gaps between schedules
                // Use a fixed date (2000-01-01) for consistent time calculations
                $baseDate = '2000-01-01';
                $currentTime = strtotime($baseDate . ' ' . sprintf('%02d:00:00', $startHour));
                $endTime = strtotime($baseDate . ' ' . sprintf('%02d:00:00', $endHour));
                
                foreach ($schedules as $index => $schedule) {
                    // Parse schedule times using the same base date for consistency
                    // Handle both 'HH:MM:SS' and 'HH:MM' formats
                    $schedStartStr = $schedule['schd_start'];
                    $schedEndStr = $schedule['schd_end'];
                    
                    // Ensure time format includes seconds
                    if (strlen($schedStartStr) == 5) {
                        $schedStartStr .= ':00';
                    }
                    if (strlen($schedEndStr) == 5) {
                        $schedEndStr .= ':00';
                    }
                    
                    $schedStartTime = strtotime($baseDate . ' ' . $schedStartStr);
                    $schedEndTime = strtotime($baseDate . ' ' . $schedEndStr);
                    
                    // Validate time parsing
                    if ($schedStartTime === false || $schedEndTime === false) {
                        error_log("Failed to parse schedule time: {$schedule['schd_start']} or {$schedule['schd_end']}");
                        continue;
                    }
                    
                    // Check if there's a gap before this schedule
                    // The gap must be large enough and the slot must end BEFORE the schedule starts (no overlap)
                    if ($currentTime < $schedStartTime) {
                        $gapDuration = $schedStartTime - $currentTime;
                        
                        // Only recommend if gap is at least minimum duration
                        if ($gapDuration >= ($minSlotDuration * 60)) {
                            $slotStart = date('H:i', $currentTime);
                            
                            // Calculate maximum slot duration that fits in the gap
                            // The slot must end BEFORE or AT the schedule start (no overlap)
                            // Maximum duration is the gap itself (can't exceed the gap)
                            $maxSlotDuration = $gapDuration; // Maximum is the full gap in seconds
                            $slotDuration = min($preferredSlotDuration * 60, $maxSlotDuration);
                            
                            // CRITICAL: Ensure slot duration never exceeds the gap
                            // This prevents recommending slots that would overlap
                            if ($slotDuration > $gapDuration) {
                                $slotDuration = $gapDuration;
                            }
                            
                            // Calculate end time
                            $slotEndTime = $currentTime + $slotDuration;
                            
                            // CRITICAL: Ensure the slot ends BEFORE or AT the next schedule starts (no overlap)
                            // Two schedules overlap if: slot_start < sched_end AND slot_end > sched_start
                            // To avoid overlap: slot_end must be <= sched_start
                            if ($slotEndTime > $schedStartTime) {
                                // Slot would overlap with existing schedule - this should not happen if logic is correct
                                // But as a safety measure, reduce duration to fit exactly before schedule
                                $slotDuration = $schedStartTime - $currentTime;
                                $slotEndTime = $schedStartTime;
                                
                                // Only add slot if it still meets minimum duration after adjustment
                                if ($slotDuration < ($minSlotDuration * 60)) {
                                    // Gap too small, skip this slot and move to next schedule
                                    $currentTime = $schedEndTime;
                                    continue;
                                }
                            }
                            
                            // Additional safety check: slot end must never exceed schedule start
                            if ($slotEndTime > $schedStartTime) {
                                // This should never happen, but if it does, skip this slot
                                $currentTime = $schedEndTime;
                                continue;
                            }
                            
                            // Final verification: ensure no overlap
                            // Slot end must be <= schedule start (strictly no overlap)
                            // This is the primary check - slot must end before or at the next schedule start
                            if ($slotEndTime <= $schedStartTime) {
                                $slotEnd = date('H:i', $slotEndTime);
                                $actualDuration = ($slotEndTime - $currentTime) / 60;
                                
                                // Double-check: verify the slot doesn't overlap with ANY schedule on this day
                                // This is a safety check to catch any edge cases
                                $hasOverlap = false;
                                foreach ($schedules as $checkSchedule) {
                                    $checkStart = strtotime($baseDate . ' ' . $checkSchedule['schd_start']);
                                    $checkEnd = strtotime($baseDate . ' ' . $checkSchedule['schd_end']);
                                    
                                    // Check overlap: Two time ranges overlap if: start1 < end2 AND end1 > start2
                                    // For our slot to NOT overlap: slot_end <= check_start OR slot_start >= check_end
                                    // Overlap occurs if: slot_start < check_end AND slot_end > check_start
                                    if ($currentTime < $checkEnd && $slotEndTime > $checkStart) {
                                        $hasOverlap = true;
                                        error_log("Overlap detected: Slot {$slotStart}-{$slotEnd} overlaps with schedule {$checkSchedule['schd_start']}-{$checkSchedule['schd_end']}");
                                        break;
                                    }
                                }
                                
                                // Only add slot if there's absolutely no overlap with any schedule
                                if (!$hasOverlap && $actualDuration >= $minSlotDuration) {
                                    // Calculate priority: prefer morning slots (8-12) and longer durations
                                    $priority = 5;
                                    $hour = (int)date('H', $currentTime);
                                    if ($hour >= 8 && $hour < 12) {
                                        $priority = 8; // Morning preference
                                    } elseif ($hour >= 12 && $hour < 17) {
                                        $priority = 6; // Afternoon
                                    }
                                    
                                    if ($actualDuration >= 90) {
                                        $priority += 2; // Prefer longer slots
                                    }
                                    
                                    $daySlots[] = [
                                        'day' => $day,
                                        'startTime' => $slotStart,
                                        'endTime' => $slotEnd,
                                        'duration' => $actualDuration,
                                        'priority' => $priority
                                    ];
                                }
                            }
                        }
                    }
                    
                    // Move to after this schedule (use end time, not start time)
                    $currentTime = $schedEndTime;
                }

                // Check if there's time after the last schedule
                if ($currentTime < $endTime) {
                    $remainingDuration = $endTime - $currentTime;
                    if ($remainingDuration >= ($minSlotDuration * 60)) {
                        $slotStart = date('H:i', $currentTime);
                        // Use preferred duration (90 minutes) if gap is large enough, otherwise use available gap
                        $slotDuration = min($preferredSlotDuration * 60, $remainingDuration);
                        $slotEndTime = $currentTime + $slotDuration;
                        $slotEnd = date('H:i', $slotEndTime);
                        $actualDuration = ($slotEndTime - $currentTime) / 60;
                        
                        $hour = (int)date('H', $currentTime);
                        $priority = ($hour >= 8 && $hour < 12) ? 7 : 5;
                        
                        $daySlots[] = [
                            'day' => $day,
                            'startTime' => $slotStart,
                            'endTime' => $slotEnd,
                            'duration' => $actualDuration,
                            'priority' => $priority
                        ];
                    }
                }
            }

            // Add all slots for this day
            $availableSlots = array_merge($availableSlots, $daySlots);
        }

        // Sort slots by priority (highest first), then by day (Mon-Fri preferred)
        usort($availableSlots, function($a, $b) {
            $dayOrder = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
            if ($a['priority'] != $b['priority']) {
                return $b['priority'] - $a['priority']; // Higher priority first
            }
            return ($dayOrder[$a['day']] ?? 99) - ($dayOrder[$b['day']] ?? 99);
        });

        // Return the best available slot overall
        if (!empty($availableSlots)) {
            $response['success'] = true;
            $response['availableSlots'] = $availableSlots;
            // Set firstAvailable to the highest priority slot (first in sorted array)
            $response['firstAvailable'] = $availableSlots[0];
            // Also provide top 3 recommendations
            $response['topRecommendations'] = array_slice($availableSlots, 0, 3);
        } else {
            $response['message'] = 'No available time slots found for this room in the selected school year and term.';
        }

    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
