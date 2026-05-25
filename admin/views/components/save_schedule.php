<?php
/**
 * Save Schedule
 * Handles creation and updates of schedules with workload validation.
 */

require_once '../../includes/auth/security_middleware.php';

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
$schd_day = trim($_POST['schd_day'] ?? '');
$schd_start = trim($_POST['schd_start'] ?? '');
$schd_end = trim($_POST['schd_end'] ?? '');
$schd_status = trim($_POST['schd_status'] ?? 'Active');

// --- Validation ---
$required = [$sy_id, $schd_term, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_day, $schd_start, $schd_end];
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
    // --- Instructor Workload Validation ---
    $stmt = $conn->prepare("SELECT inst_status, inst_working_hours FROM instructor WHERE inst_id = ?");
    $stmt->bind_param("i", $inst_id);
    $stmt->execute();
    $instructor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$instructor) {
        echo json_encode(['success' => false, 'message' => 'Instructor not found.']);
        exit();
    }

    // Get current workload, excluding the schedule being edited
    $sql = "SELECT SUM(schd_min) as total_minutes FROM schedule WHERE inst_id = ? AND sy_id = ? AND schd_term = ? AND schd_status = 'Active'";
    $params = [$inst_id, $sy_id, $schd_term];
    $types = "iii";
    if ($schd_id > 0) {
        $sql .= " AND schd_id != ?";
        $params[] = $schd_id;
        $types .= "i";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $workload = $stmt->get_result()->fetch_assoc();
    $current_minutes = (int)($workload['total_minutes'] ?? 0);
    $stmt->close();

    $new_total_minutes = $current_minutes + $schd_min;
    $limit_minutes = $instructor['inst_working_hours'] * 60;

    $is_overtime = 'No'; // Default value

    if ($instructor['inst_status'] === 'Part-Time' || $instructor['inst_status'] === 'Contractual') {
        if ($limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            echo json_encode(['success' => false, 'message' => 'Workload Limit Exceeded! This schedule exceeds the instructor\'s limit of ' . $instructor['inst_working_hours'] . ' hours. Current load: ' . round($current_minutes / 60, 2) . ' hrs.']);
            exit();
        }
    } elseif ($instructor['inst_status'] === 'Regular') {
        // Mark as overtime only if this schedule causes the total to exceed the limit.
        if ($limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $is_overtime = 'Yes';
        }
    }

    // --- Save to Database ---
    if ($schd_id > 0) { // Update
        $stmt = $conn->prepare("UPDATE schedule SET sy_id=?, subj_id=?, sec_id=?, inst_id=?, rm_id=?, schd_type=?, schd_term=?, schd_day=?, schd_start=?, schd_end=?, schd_min=?, schd_status=?, is_overtime=? WHERE schd_id=?");
        $stmt->bind_param("iiiiisisssissi", $sy_id, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_term, $schd_day, $schd_start, $schd_end, $schd_min, $schd_status, $is_overtime, $schd_id);
    } else { // Insert
        $stmt = $conn->prepare("INSERT INTO schedule (sy_id, subj_id, sec_id, inst_id, rm_id, schd_type, schd_term, schd_day, schd_start, schd_end, schd_min, schd_status, is_overtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiisisssiss", $sy_id, $subj_id, $sec_id, $inst_id, $rm_id, $schd_type, $schd_term, $schd_day, $schd_start, $schd_end, $schd_min, $schd_status, $is_overtime);
    }

    if ($stmt->execute()) {
        $message = $schd_id > 0 ? 'Schedule updated successfully.' : 'Schedule created successfully.';
        if ($is_overtime === 'Yes' && $instructor['inst_status'] === 'Regular') {
            $message .= ' Note: Instructor is now in overtime.';
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save schedule: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>