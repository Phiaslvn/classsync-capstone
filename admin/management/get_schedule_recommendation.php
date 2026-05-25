<?php
/**
 * Get recommended schedule day and time for an instructor & subject
 * Uses subject hours (subj_lec/subj_lab) and existing instructor schedules
 * to suggest the earliest available slot within standard teaching hours.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

if (!hasPermission('manage_schedules')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $sy_id     = isset($_GET['sy_id']) ? (int)$_GET['sy_id'] : 0;
    $schd_term = isset($_GET['schd_term']) ? (int)$_GET['schd_term'] : 0;
    $subj_id   = isset($_GET['subj_id']) ? (int)$_GET['subj_id'] : 0;
    $inst_id   = isset($_GET['inst_id']) ? (int)$_GET['inst_id'] : 0;
    $schd_type = isset($_GET['schd_type']) ? trim($_GET['schd_type']) : 'Lec';
    
    // Get selected days from request (comma-separated or array)
    $selected_days = isset($_GET['selected_days']) ? $_GET['selected_days'] : '';
    $selectedDaysArray = [];
    if (!empty($selected_days)) {
        // Handle both comma-separated string and array formats
        if (is_string($selected_days)) {
            $selectedDaysArray = array_map('trim', explode(',', $selected_days));
        } elseif (is_array($selected_days)) {
            $selectedDaysArray = array_map('trim', $selected_days);
        }
        // Filter to only valid day names
        $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $selectedDaysArray = array_intersect($selectedDaysArray, $validDays);
    }

    if (!$sy_id || !$schd_term || !$subj_id || !$inst_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters for recommendation.']);
        exit;
    }

    // Get subject hours (lec/lab)
    $subj_stmt = $conn->prepare("
        SELECT subj_lec, subj_lab 
        FROM subject 
        WHERE subj_id = ?
        LIMIT 1
    ");
    $subj_stmt->bind_param('i', $subj_id);
    $subj_stmt->execute();
    $subj_result = $subj_stmt->get_result();
    $subject = $subj_result->fetch_assoc();
    $subj_stmt->close();

    if (!$subject) {
        echo json_encode(['success' => false, 'message' => 'Subject not found.']);
        exit;
    }

    $hours = 0;
    if (strcasecmp($schd_type, 'Lab') === 0) {
        $hours = (int)($subject['subj_lab'] ?? 0);
    } else {
        // Default to lecture hours
        $hours = (int)($subject['subj_lec'] ?? 0);
    }

    if ($hours <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This subject has no defined hours for the selected type.'
        ]);
        exit;
    }

    $requiredMinutes = $hours * 60;

    // Standard teaching window (07:00 - 21:00)
    $windowStart = '07:00:00';
    $windowEnd   = '21:00:00';

    // Use selected days if provided, otherwise use all days
    $daysToCheck = !empty($selectedDaysArray) ? $selectedDaysArray : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    foreach ($daysToCheck as $day) {
        // Fetch existing schedules for this instructor on this day
        $sched_stmt = $conn->prepare("
            SELECT schd_start, schd_end
            FROM schedule
            WHERE inst_id = ?
              AND sy_id = ?
              AND schd_term = ?
              AND schd_day = ?
              AND schd_status = 'Active'
            ORDER BY schd_start ASC
        ");
        $sched_stmt->bind_param('iiis', $inst_id, $sy_id, $schd_term, $day);
        $sched_stmt->execute();
        $result = $sched_stmt->get_result();

        $occupied = [];
        while ($row = $result->fetch_assoc()) {
            $occupied[] = [
                'start' => $row['schd_start'],
                'end'   => $row['schd_end'],
            ];
        }
        $sched_stmt->close();

        // If no schedules at all for this day, use the default window start
        if (empty($occupied)) {
            $startDateTime = new DateTime($windowStart);
            $endDateTime   = clone $startDateTime;
            $endDateTime->modify("+{$requiredMinutes} minutes");

            // Ensure we don't go past window end
            if ($endDateTime->format('H:i:s') <= $windowEnd) {
                echo json_encode([
                    'success' => true,
                    'day' => $day,
                    'start_time' => $startDateTime->format('H:i'),
                    'end_time' => $endDateTime->format('H:i'),
                    'hours' => $hours,
                ]);
                exit;
            }
            // If it doesn't fit in this day, continue to next day
            continue;
        }

        // There are existing schedules; find the first available gap
        $current = new DateTime($windowStart);
        $windowEndDt = new DateTime($windowEnd);

        foreach ($occupied as $slot) {
            $slotStart = new DateTime($slot['start']);
            $slotEnd   = new DateTime($slot['end']);

            // If there is a gap between current and this slot's start
            if ($slotStart > $current) {
                $gapMinutes = ($slotStart->getTimestamp() - $current->getTimestamp()) / 60;
                if ($gapMinutes >= $requiredMinutes) {
                    $endCandidate = clone $current;
                    $endCandidate->modify("+{$requiredMinutes} minutes");

                    if ($endCandidate <= $slotStart && $endCandidate <= $windowEndDt) {
                        echo json_encode([
                            'success' => true,
                            'day' => $day,
                            'start_time' => $current->format('H:i'),
                            'end_time' => $endCandidate->format('H:i'),
                            'hours' => $hours,
                        ]);
                        exit;
                    }
                }
            }

            // Move current pointer to the end of this occupied slot if it's later
            if ($slotEnd > $current) {
                $current = clone $slotEnd;
            }
        }

        // Check gap between last occupied slot and end of window
        if ($current < $windowEndDt) {
            $gapMinutes = ($windowEndDt->getTimestamp() - $current->getTimestamp()) / 60;
            if ($gapMinutes >= $requiredMinutes) {
                $endCandidate = clone $current;
                $endCandidate->modify("+{$requiredMinutes} minutes");
                if ($endCandidate <= $windowEndDt) {
                    echo json_encode([
                        'success' => true,
                        'day' => $day,
                        'start_time' => $current->format('H:i'),
                        'end_time' => $endCandidate->format('H:i'),
                        'hours' => $hours,
                    ]);
                    exit;
                }
            }
        }
        // Otherwise, try next day
    }

    // If we reach here, no recommended slot found
    echo json_encode([
        'success' => false,
        'message' => 'No available time slot could be recommended for this instructor and subject within standard hours.'
    ]);
} catch (Exception $e) {
    error_log('Error in get_schedule_recommendation.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred while generating a recommendation.'
    ]);
}



