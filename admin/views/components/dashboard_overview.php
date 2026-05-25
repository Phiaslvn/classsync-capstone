<?php
/**
 * Dashboard Overview Component
 * Main dashboard statistics matching admin_support overview
 */

// Include database connection
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth/security_middleware.php';

// ✅ Fetch logged-in user with instructor details (rank and inst_status)
$query = "
    SELECT a.fname, a.lname, a.acc_user, r.role_name, a.dept_id, i.rank, i.inst_status
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN instructor i ON i.inst_user = a.acc_user
    WHERE a.acc_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['acc_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$fname = $user ? $user['fname'] : "Unknown";
$lname = $user ? $user['lname'] : "";
$role  = $user ? $user['role_name'] : "Unknown";
$username = $fname . " " . $lname;
$userDeptId = $user ? (int)$user['dept_id'] : 0;
$userRank = $user && !empty($user['rank']) ? $user['rank'] : null;
$userInstStatus = $user && !empty($user['inst_status']) ? $user['inst_status'] : null;

// Check if user is an instructor (for conditional display)
$isInstructor = ($role === 'User' || $role === 'Instructor');

// Get user's department name
$userDeptName = 'All Departments';
if ($userDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
    $deptStmt->bind_param("i", $userDeptId);
    $deptStmt->execute();
    $deptResult = $deptStmt->get_result();
    if ($deptRow = $deptResult->fetch_assoc()) {
        $userDeptName = $deptRow['dept_name'];
    }
    $deptStmt->close();
}

// Get room access grants - TWO types:
// 1. Rooms in user's department that have been granted to other departments (granted_by_dept_id = user's dept)
// 2. Rooms from other departments that have been granted to user's department (granted_to_dept_id = user's dept)
$roomAccessGrantsGiven = []; // Grants given by this department
$roomAccessGrantsReceived = []; // Grants received by this department

if ($userDeptId > 0) {
    // 1. Grants GIVEN: Rooms in user's department that have been granted to other departments
    // Only show rooms that have been granted access to other departments (via room_access table)
    $givenQuery = "
        SELECT 
            ra.access_id,
            ra.granted_at,
            r.rm_id,
            r.rm_name,
            b.bd_desc as building_name,
            d_granted_to.dept_name as granted_to_dept_name,
            d_granted_to.dept_id as granted_to_dept_id,
            CONCAT(a.fname, ' ', a.lname) as granted_by_name,
            'given' as grant_type,
            (SELECT COUNT(DISTINCT s.schd_id) 
             FROM schedule s 
             WHERE s.rm_id = r.rm_id 
             AND s.schd_status = 'Active') as schedule_count
        FROM room_access ra
        JOIN room r ON ra.rm_id = r.rm_id
        JOIN building b ON r.bd_id = b.bd_id
        JOIN department d_granted_to ON ra.granted_to_dept_id = d_granted_to.dept_id
        LEFT JOIN account a ON ra.granted_by_acc_id = a.acc_id
        WHERE ra.granted_by_dept_id = ?
        AND ra.status = 'Active'
        ORDER BY ra.granted_at DESC
        LIMIT 10
    ";
    $givenStmt = $conn->prepare($givenQuery);
    $givenStmt->bind_param("i", $userDeptId);
    $givenStmt->execute();
    $givenResult = $givenStmt->get_result();
    while ($givenRow = $givenResult->fetch_assoc()) {
        $roomAccessGrantsGiven[] = $givenRow;
    }
    $givenStmt->close();
    
    // 2. Grants RECEIVED: Rooms from other departments granted to user's department
    $receivedQuery = "
        SELECT 
            ra.access_id,
            ra.granted_at,
            r.rm_id,
            r.rm_name,
            b.bd_desc as building_name,
            d_granted_by.dept_name as granted_by_dept_name,
            d_granted_by.dept_id as granted_by_dept_id,
            CONCAT(a.fname, ' ', a.lname) as granted_by_name,
            'received' as grant_type
        FROM room_access ra
        JOIN room r ON ra.rm_id = r.rm_id
        JOIN building b ON r.bd_id = b.bd_id
        JOIN department d_granted_by ON ra.granted_by_dept_id = d_granted_by.dept_id
        LEFT JOIN account a ON ra.granted_by_acc_id = a.acc_id
        WHERE ra.granted_to_dept_id = ?
        AND ra.status = 'Active'
        ORDER BY ra.granted_at DESC
        LIMIT 10
    ";
    $receivedStmt = $conn->prepare($receivedQuery);
    $receivedStmt->bind_param("i", $userDeptId);
    $receivedStmt->execute();
    $receivedResult = $receivedStmt->get_result();
    while ($receivedRow = $receivedResult->fetch_assoc()) {
        $roomAccessGrantsReceived[] = $receivedRow;
    }
    $receivedStmt->close();
}

// Get current school year and semester
$sy_id = null;
$semester = null;
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_school_year_id' LIMIT 1");
$stmt->execute();
$sy_result = $stmt->get_result();
if ($sy_row = $sy_result->fetch_assoc()) {
    $sy_id = intval($sy_row['setting_value']);
}

$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_semester' LIMIT 1");
$stmt->execute();
$semester_result = $stmt->get_result();
if ($semester_row = $semester_result->fetch_assoc()) {
    $semester = $semester_row['setting_value'];
}

// Get schedule counts by department and time period - Real-time data (same as admin support)
// Map display names to department names in database
$deptMapping = [
    'Computer Studies' => 'Computer Studies Department',
    'Education' => 'Teacher Education Department',
    'Technology' => 'Industrial Technology Department'
];

$deptStats = [];

foreach ($deptMapping as $displayName => $deptName) {
    // Get department ID
    $deptStmt = $conn->prepare("SELECT dept_id FROM department WHERE dept_name = ? AND dept_status = 'Active' LIMIT 1");
    $deptStmt->bind_param("s", $deptName);
    $deptStmt->execute();
    $deptResult = $deptStmt->get_result();
    $deptRow = $deptResult->fetch_assoc();
    $deptStmt->close();
    
    if (!$deptRow) {
        // If department not found, set all counts to 0
        $deptStats[$displayName] = ['morning' => 0, 'afternoon' => 0, 'evening' => 0];
        continue;
    }
    
    $deptId = $deptRow['dept_id'];
    
    // Count Morning schedules (7:00 AM - 12:00 PM) - Real-time, no SY/semester filter
    $morningStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedule s
        WHERE s.dept_id = ? 
        AND s.schd_status = 'Active'
        AND TIME(s.schd_start) >= '07:00:00'
        AND TIME(s.schd_start) < '12:00:00'
    ");
    $morningStmt->bind_param("i", $deptId);
    $morningStmt->execute();
    $morningResult = $morningStmt->get_result();
    $morningRow = $morningResult->fetch_assoc();
    $morning = $morningRow['count'] ?? 0;
    $morningStmt->close();
    
    // Count Afternoon schedules (12:00 PM - 5:00 PM) - Real-time, no SY/semester filter
    $afternoonStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedule s
        WHERE s.dept_id = ? 
        AND s.schd_status = 'Active'
        AND TIME(s.schd_start) >= '12:00:00'
        AND TIME(s.schd_start) < '17:00:00'
    ");
    $afternoonStmt->bind_param("i", $deptId);
    $afternoonStmt->execute();
    $afternoonResult = $afternoonStmt->get_result();
    $afternoonRow = $afternoonResult->fetch_assoc();
    $afternoon = $afternoonRow['count'] ?? 0;
    $afternoonStmt->close();
    
    // Count Evening schedules (5:00 PM - 8:30 PM) - Real-time, no SY/semester filter
    $eveningStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedule s
        WHERE s.dept_id = ? 
        AND s.schd_status = 'Active'
        AND TIME(s.schd_start) >= '17:00:00'
        AND TIME(s.schd_start) <= '20:30:00'
    ");
    $eveningStmt->bind_param("i", $deptId);
    $eveningStmt->execute();
    $eveningResult = $eveningStmt->get_result();
    $eveningRow = $eveningResult->fetch_assoc();
    $evening = $eveningRow['count'] ?? 0;
    $eveningStmt->close();
    
    $deptStats[$displayName] = [
        'morning' => $morning,
        'afternoon' => $afternoon,
        'evening' => $evening
    ];
}

// Get weekly schedule data for chart - fetch ALL departments dynamically
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$weeklyDataByDept = []; // Array to store data for each department

// First, get all departments that have active schedules - Real-time data (no SY/semester filter)
$deptQuery = "
    SELECT DISTINCT d.dept_id, d.dept_name
    FROM schedule s
    JOIN department d ON s.dept_id = d.dept_id
    WHERE s.schd_status = 'Active'
    ORDER BY d.dept_name
";

$deptStmt = $conn->prepare($deptQuery);
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
$departments = [];
while ($deptRow = $deptResult->fetch_assoc()) {
    $departments[] = $deptRow;
    $weeklyDataByDept[$deptRow['dept_id']] = [
        'name' => $deptRow['dept_name'],
        'data' => []
    ];
}
$deptStmt->close();

// Now get schedule counts for each department for each day - Real-time data (no SY/semester filter)
foreach ($days as $day) {
    foreach ($departments as $dept) {
        $dayQuery = "
            SELECT COUNT(*) as count
            FROM schedule s
            WHERE s.schd_status = 'Active'
            AND s.schd_day = ?
            AND s.dept_id = ?
        ";
        
        $dayStmt = $conn->prepare($dayQuery);
        $dayStmt->bind_param("si", $day, $dept['dept_id']);
        $dayStmt->execute();
        $dayResult = $dayStmt->get_result();
        $count = $dayResult->fetch_assoc()['count'] ?? 0;
        $weeklyDataByDept[$dept['dept_id']]['data'][] = $count;
        $dayStmt->close();
    }
}

// Get department totals for donut chart
$deptTotals = [];
foreach ($deptStats as $deptName => $stats) {
    $deptTotals[$deptName] = $stats['morning'] + $stats['afternoon'] + $stats['evening'];
}
?>

<!-- Overview Dashboard Content -->
<div class="overview-management-card mb-4">
<div class="row mb-3" style="margin-left: 0; margin-right: 0;">
    <div class="col-12" style="padding-left: 0; padding-right: 0;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div class="flex-grow-1" style="min-width: 200px;">
                <h3 class="mb-1" style="color: #495057; font-weight: 600; font-size: 1.5rem;">Overview — welcome back, <?php echo htmlspecialchars($fname); ?>!</h3>
                <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">
                    <?php 
                    echo htmlspecialchars($role);
                    if ($userRank) {
                        echo ' • ' . htmlspecialchars($userRank);
                    }
                    if ($userInstStatus) {
                        echo ' • ' . htmlspecialchars($userInstStatus);
                    }
                    ?>
                </p>
                <p class="mb-0 mt-1 small text-muted">Quick snapshot: room sharing, schedules, and department info below. Other tasks use the tabs at the top.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <div class="search-box" style="position: relative;">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search users, departments, schedules..." style="border-radius: 25px; padding-left: 40px; border: 1px solid #e9ecef; width: 250px; height: 40px;" aria-label="Search overview">
                    <i class="bi bi-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d;" aria-hidden="true"></i>
                    <div id="searchResults" class="search-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e9ecef; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000; max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Department Summary Cards - HIDDEN per user request -->
<!-- Stats cards are now hidden, only showing Room Access Grants, Weekly Schedule, and EVSU-OCC Departments -->

<!-- Room Access Grants Section -->
<?php if ($userDeptId > 0): ?>
<!-- Grants Given: Rooms in your department granted to others -->
<?php if (!empty($roomAccessGrantsGiven)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1" style="color: #495057; font-weight: 600; font-size: 1.125rem;">
                        <i class="bi bi-door-open me-2" style="color: #800000;"></i>Room Access Grants Given
                    </h5>
                    <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">
                        Rooms in <?php echo htmlspecialchars($userDeptName); ?> that have been granted access to other departments (view all schedules for these rooms)
                    </p>
                </div>
                <span class="badge" style="background-color: #800000; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <?php echo count($roomAccessGrantsGiven); ?> Active Grant<?php echo count($roomAccessGrantsGiven) !== 1 ? 's' : ''; ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.875rem;">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Room</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Building</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Granted To</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Granted By</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roomAccessGrantsGiven as $grant): ?>
                        <tr style="border-bottom: 1px solid #e9ecef; cursor: pointer;" 
                            data-room-schedule-id="<?php echo (int)$grant['rm_id']; ?>"
                            data-room-schedule-name="<?php echo htmlspecialchars($grant['rm_name']); ?>"
                            onmouseover="this.style.backgroundColor='#f0f0f0'" 
                            onmouseout="this.style.backgroundColor=''"
                            onclick="if(typeof openRoomScheduleModal === 'function') { openRoomScheduleModal(<?php echo (int)$grant['rm_id']; ?>, '<?php echo htmlspecialchars($grant['rm_name'], ENT_QUOTES); ?>'); return false; }"
                            title="Click to view room schedule">
                            <td style="padding: 0.75rem;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-door-closed me-2" style="color: #800000;"></i>
                                    <strong style="color: #495057;"><?php echo htmlspecialchars($grant['rm_name']); ?></strong>
                                </div>
                            </td>
                            <td style="padding: 0.75rem; color: #6c757d;">
                                <?php echo htmlspecialchars($grant['building_name'] ?? 'N/A'); ?>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span class="badge" style="background-color: #e3f2fd; color: #1976d2; padding: 0.35rem 0.75rem;">
                                    <i class="bi bi-building me-1"></i>
                                    <?php echo htmlspecialchars($grant['granted_to_dept_name'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem; color: #6c757d;">
                                <span class="badge bg-info" style="padding: 0.35rem 0.75rem;">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    <?php echo (int)($grant['schedule_count'] ?? 0); ?> schedule(s)
                                </span>
                            </td>
                            <td style="padding: 0.75rem; color: #6c757d;">
                                <?php 
                                if (!empty($grant['granted_at']) && $grant['granted_at'] !== '0000-00-00 00:00:00') {
                                    try {
                                $grantDate = new DateTime($grant['granted_at']);
                                echo $grantDate->format('M d, Y');
                                    } catch (Exception $e) {
                                        echo 'N/A';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($roomAccessGrantsGiven) >= 10): ?>
            <div class="mt-3 text-center">
                <small class="text-muted">Showing latest 10 grants. <a href="#" onclick="showContent('schedule', this); return false;" style="color: #800000; text-decoration: none;">View all room access</a></small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Grants Received: Rooms from other departments granted to your department -->
<?php if (!empty($roomAccessGrantsReceived)): ?>
<div class="row mb-4" style="margin-left: 0; margin-right: 0;">
    <div class="col-12" style="padding-left: 0; padding-right: 0;">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1" style="color: #495057; font-weight: 600; font-size: 1.125rem;">
                        <i class="bi bi-door-open-fill me-2" style="color: #28a745;"></i>Room Access Grants Received
                    </h5>
                    <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">
                        Rooms from other departments that have been granted access to <?php echo htmlspecialchars($userDeptName); ?>
                    </p>
                </div>
                <span class="badge" style="background-color: #28a745; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <?php echo count($roomAccessGrantsReceived); ?> Active Grant<?php echo count($roomAccessGrantsReceived) !== 1 ? 's' : ''; ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.875rem;">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Room</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Building</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Granted From</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Granted By</th>
                            <th style="font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roomAccessGrantsReceived as $grant): ?>
                        <tr style="border-bottom: 1px solid #e9ecef; cursor: pointer;" 
                            data-room-schedule-id="<?php echo (int)$grant['rm_id']; ?>"
                            data-room-schedule-name="<?php echo htmlspecialchars($grant['rm_name']); ?>"
                            onmouseover="this.style.backgroundColor='#f0f0f0'" 
                            onmouseout="this.style.backgroundColor=''"
                            onclick="handleRoomAccessGrantClick(<?php echo (int)$grant['rm_id']; ?>, '<?php echo htmlspecialchars($grant['rm_name'], ENT_QUOTES); ?>'); return false;"
                            title="Click to view available time slots and request">
                            <td style="padding: 0.75rem;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-door-open me-2" style="color: #28a745;"></i>
                                    <strong style="color: #495057;"><?php echo htmlspecialchars($grant['rm_name']); ?></strong>
                                </div>
                            </td>
                            <td style="padding: 0.75rem; color: #6c757d;">
                                <?php echo htmlspecialchars($grant['building_name'] ?? 'N/A'); ?>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span class="badge" style="background-color: #fff3cd; color: #856404; padding: 0.35rem 0.75rem;">
                                    <i class="bi bi-building me-1"></i>
                                    <?php echo htmlspecialchars($grant['granted_by_dept_name']); ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem; color: #6c757d;">
                                <?php echo htmlspecialchars($grant['granted_by_name'] ?? 'System'); ?>
                            </td>
                            <td style="padding: 0.75rem; color: #6c757d;">
                                <?php 
                                $grantDate = new DateTime($grant['granted_at']);
                                echo $grantDate->format('M d, Y');
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($roomAccessGrantsReceived) >= 10): ?>
            <div class="mt-3 text-center">
                <small class="text-muted">Showing latest 10 grants. <a href="#" onclick="showContent('schedule', this); return false;" style="color: #800000; text-decoration: none;">View all room access</a></small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Show message if no grants at all -->
<?php if (empty($roomAccessGrantsGiven) && empty($roomAccessGrantsReceived)): ?>
<div class="row mb-4" style="margin-left: 0; margin-right: 0;">
    <div class="col-12" style="padding-left: 0; padding-right: 0;">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1" style="color: #495057; font-weight: 600; font-size: 1.125rem;">
                        <i class="bi bi-door-open me-2" style="color: #800000;"></i>Room Access Grants
                    </h5>
                    <p class="mb-0" style="color: #6c757d; font-size: 0.875rem;">
                        No room access grants found for <?php echo htmlspecialchars($userDeptName); ?>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Charts Row -->
<div class="row">
    <!-- Weekly Schedule Chart -->
    <div class="col-lg-8 mb-4">
        <div class="dashboard-card" style="min-height: 400px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0" style="color: #495057; font-weight: 600; font-size: 1.125rem;">Weekly Schedule</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="exportSelect" style="border-radius: 20px; border: 1px solid #e9ecef; font-size: 0.8rem; padding: 0.25rem 0.5rem;" onchange="exportChartData()">
                        <option value="">Export data</option>
                        <option value="pdf">Export as PDF</option>
                        <option value="excel">Export as Excel</option>
                        <option value="csv">Export as CSV</option>
                    </select>
                    <select class="form-select form-select-sm" id="timeRangeSelect" style="border-radius: 20px; border: 1px solid #e9ecef; font-size: 0.8rem; padding: 0.25rem 0.5rem;" onchange="updateChartTimeRange()">
                        <option value="7">Last 7 Days</option>
                        <option value="14">Last 14 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 3 Months</option>
                    </select>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- EVSU-OCC Departments Chart - Only show for Admin, Moderator, and Admin Support (NOT for Instructors) -->
    <?php if (!$isInstructor): ?>
    <div class="col-lg-4 mb-4" style="padding-left: 0; padding-right: 0;">
        <div class="dashboard-card" style="min-height: 400px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0" style="color: #495057; font-weight: 600; font-size: 1.125rem;">EVSU-OCC Departments</h5>
                <i class="bi bi-arrow-right" style="color: #6c757d; font-size: 0.875rem; cursor: pointer;" onclick="showDepartmentBreakdown()"></i>
            </div>
            <div class="text-center">
                <div style="height: 200px; position: relative;">
                    <canvas id="departmentsChart"></canvas>
                </div>
                <div class="mt-3">
                    <div class="legend-item d-flex align-items-center justify-content-center mb-2">
                        <div style="width: 10px; height: 10px; background: #800000; border-radius: 2px; margin-right: 6px;"></div>
                        <span style="font-size: 0.875rem; color: #6c757d;">Computer Studies</span>
                    </div>
                    <div class="legend-item d-flex align-items-center justify-content-center mb-2">
                        <div style="width: 10px; height: 10px; background: #4a90e2; border-radius: 2px; margin-right: 6px;"></div>
                        <span style="font-size: 0.875rem; color: #6c757d;">Education</span>
                    </div>
                    <div class="legend-item d-flex align-items-center justify-content-center">
                        <div style="width: 10px; height: 10px; background: #7bb3f0; border-radius: 2px; margin-right: 6px;"></div>
                        <span style="font-size: 0.875rem; color: #6c757d;">Technology</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="/assets/js/chart.min.js"></script>
<script>
// Chart.js configurations
document.addEventListener('DOMContentLoaded', function() {
    // Weekly Schedule Chart - Dynamic datasets for all departments
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    
    // Build datasets dynamically from PHP data
    const datasets = [];
    <?php 
    // Define color palette in PHP
    $colorPalette = [
        ['border' => '#800000', 'background' => 'rgba(128, 0, 0, 0.1)'],  // Maroon
        ['border' => '#4a90e2', 'background' => 'rgba(74, 144, 226, 0.1)'],  // Blue
        ['border' => '#7bb3f0', 'background' => 'rgba(123, 179, 240, 0.1)'],  // Light Blue
        ['border' => '#28a745', 'background' => 'rgba(40, 167, 69, 0.1)'],  // Green
        ['border' => '#ffc107', 'background' => 'rgba(255, 193, 7, 0.1)'],  // Yellow
        ['border' => '#dc3545', 'background' => 'rgba(220, 53, 69, 0.1)'],  // Red
        ['border' => '#6f42c1', 'background' => 'rgba(111, 66, 193, 0.1)'],  // Purple
        ['border' => '#fd7e14', 'background' => 'rgba(253, 126, 20, 0.1)'],  // Orange
        ['border' => '#20c997', 'background' => 'rgba(32, 201, 151, 0.1)'],  // Teal
        ['border' => '#e83e8c', 'background' => 'rgba(232, 62, 140, 0.1)']   // Pink
    ];
    
    $colorIndex = 0;
    foreach ($weeklyDataByDept as $deptId => $deptData): 
        $color = $colorPalette[$colorIndex % count($colorPalette)];
        $colorIndex++;
    ?>
    datasets.push({
        label: '<?php echo addslashes($deptData['name']); ?>',
        data: [<?php echo implode(',', $deptData['data']); ?>],
        borderColor: '<?php echo $color['border']; ?>',
        backgroundColor: '<?php echo $color['background']; ?>',
        fill: true,
        tension: 0.4
    });
    <?php endforeach; ?>
    
    // Calculate max value for Y-axis (add 10% padding)
    const allValues = datasets.flatMap(d => d.data);
    const maxValue = allValues.length > 0 ? Math.max(...allValues) : 10;
    const yAxisMax = Math.ceil(maxValue * 1.1);
    
    new Chart(weeklyCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: yAxisMax > 0 ? yAxisMax : 10,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    },
                    ticks: {
                        stepSize: Math.ceil(yAxisMax / 10) || 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                point: {
                    radius: 3,
                    hoverRadius: 5
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });

    // Departments Chart - Only initialize if element exists (not for instructors)
    const departmentsChartEl = document.getElementById('departmentsChart');
    if (departmentsChartEl) {
        const departmentsCtx = departmentsChartEl.getContext('2d');
        new Chart(departmentsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Computer Studies', 'Education', 'Technology'],
                datasets: [{
                    data: [
                        <?php echo $deptTotals['Computer Studies'] ?? 0; ?>,
                        <?php echo $deptTotals['Education'] ?? 0; ?>,
                        <?php echo $deptTotals['Technology'] ?? 0; ?>
                    ],
                        backgroundColor: ['#800000', '#4a90e2', '#7bb3f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });
    }
});

// Chart export functionality
function exportChartData() {
    const format = document.getElementById('exportSelect').value;
    if (!format) return;
    alert(`Exporting chart data as ${format.toUpperCase()}...`);
    document.getElementById('exportSelect').value = '';
}

// Chart time range update
function updateChartTimeRange() {
    const days = document.getElementById('timeRangeSelect').value;
    alert(`Updating chart to show last ${days} days...`);
}

// Department breakdown
function showDepartmentBreakdown() {
    alert('Showing detailed department breakdown...');
}

// Department details modal
function showDepartmentDetails(department) {
    alert(`Showing details for ${department} department...`);
}

// Search functionality
(function() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout = null;
    
    if (!searchInput || !searchResults) return;
    
    // Debounce function
    function debounce(func, wait) {
        return function(...args) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    
    // Perform search
    function performSearch(query) {
        if (query.length < 2) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        // Show loading state
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="p-3 text-center text-muted"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>';
        
        // Fetch search results
        fetch(`../../admin/api/search.php?q=${encodeURIComponent(query)}&limit=10`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results && data.results.length > 0) {
                    displaySearchResults(data.results);
                } else {
                    searchResults.innerHTML = '<div class="p-3 text-center text-muted"><i class="bi bi-search me-2"></i>No results found</div>';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                searchResults.innerHTML = '<div class="p-3 text-center text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error searching. Please try again.</div>';
            });
    }
    
    // Display search results
    function displaySearchResults(results) {
        let html = '';
        
        // Group results by type
        const grouped = {
            user: [],
            department: [],
            schedule: []
        };
        
        results.forEach(result => {
            if (grouped[result.type]) {
                grouped[result.type].push(result);
            }
        });
        
        // Build HTML
        if (grouped.user.length > 0) {
            html += '<div class="p-2 border-bottom"><small class="text-muted fw-bold">USERS</small></div>';
            grouped.user.forEach(result => {
                html += `
                    <div class="search-result-item p-3 border-bottom" style="cursor: pointer; transition: background 0.2s;" 
                         onmouseover="this.style.background='#f8f9fa'" 
                         onmouseout="this.style.background='white'"
                         onclick="handleSearchResult('${result.action}', ${result.action_id})">
                        <div class="d-flex align-items-start">
                            <i class="bi ${result.icon} me-3 mt-1" style="color: #800000; font-size: 1.2rem;"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="color: #212529;">${escapeHtml(result.title)}</div>
                                <div class="text-muted small">${escapeHtml(result.subtitle)}</div>
                                ${result.description ? `<div class="text-muted small mt-1">${escapeHtml(result.description)}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        if (grouped.department.length > 0) {
            html += '<div class="p-2 border-bottom"><small class="text-muted fw-bold">DEPARTMENTS</small></div>';
            grouped.department.forEach(result => {
                html += `
                    <div class="search-result-item p-3 border-bottom" style="cursor: pointer; transition: background 0.2s;" 
                         onmouseover="this.style.background='#f8f9fa'" 
                         onmouseout="this.style.background='white'"
                         onclick="handleSearchResult('${result.action}', ${result.action_id})">
                        <div class="d-flex align-items-start">
                            <i class="bi ${result.icon} me-3 mt-1" style="color: #800000; font-size: 1.2rem;"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="color: #212529;">${escapeHtml(result.title)}</div>
                                <div class="text-muted small">${escapeHtml(result.subtitle)}</div>
                                ${result.description ? `<div class="text-muted small mt-1">${escapeHtml(result.description)}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        if (grouped.schedule.length > 0) {
            html += '<div class="p-2 border-bottom"><small class="text-muted fw-bold">SCHEDULES</small></div>';
            grouped.schedule.forEach(result => {
                html += `
                    <div class="search-result-item p-3 border-bottom" style="cursor: pointer; transition: background 0.2s;" 
                         onmouseover="this.style.background='#f8f9fa'" 
                         onmouseout="this.style.background='white'"
                         onclick="handleSearchResult('${result.action}', ${result.action_id})">
                        <div class="d-flex align-items-start">
                            <i class="bi ${result.icon} me-3 mt-1" style="color: #800000; font-size: 1.2rem;"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="color: #212529;">${escapeHtml(result.title)}</div>
                                <div class="text-muted small">${escapeHtml(result.subtitle)}</div>
                                ${result.description ? `<div class="text-muted small mt-1">${escapeHtml(result.description)}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        searchResults.innerHTML = html;
    }
    
    // Handle search result click
    window.handleSearchResult = function(action, id) {
        // Hide search results
        searchResults.style.display = 'none';
        searchInput.value = '';
        
        // Navigate based on action type
        if (action === 'viewUser') {
            // Navigate to Users tab and open user details
            if (typeof showTab === 'function') {
                showTab('roles', null);
                setTimeout(() => {
                    if (typeof viewUserDetails === 'function') {
                        viewUserDetails(id);
                    } else if (typeof editAccount === 'function') {
                        editAccount(id);
                    }
                }, 500);
            }
        } else if (action === 'viewDepartment') {
            // Navigate to appropriate tab for department
            if (typeof showTab === 'function') {
                showTab('overview', null);
            }
        } else if (action === 'viewSchedule') {
            // Navigate to Schedules tab
            if (typeof showTab === 'function') {
                showTab('schedule', null);
            }
        }
    };
    
    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Event listeners
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        debounce(() => performSearch(query), 300)();
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
    
    // Show results on focus if there's a query
    searchInput.addEventListener('focus', function() {
        if (searchInput.value.trim().length >= 2) {
            performSearch(searchInput.value.trim());
        }
    });
})();

// Handler for room access grants click - ensures function is loaded
window.handleRoomAccessGrantClick = function(rmId, rmName) {
    if (typeof openRoomAccessGrantsListModal === 'function') {
        openRoomAccessGrantsListModal(rmId, rmName);
    } else {
        // Function not loaded yet, wait a bit and try again
        setTimeout(function() {
            if (typeof openRoomAccessGrantsListModal === 'function') {
                openRoomAccessGrantsListModal(rmId, rmName);
            } else {
                console.error('openRoomAccessGrantsListModal not found after timeout');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Function Not Available',
                        text: 'Please refresh the page to load all required scripts.',
                        confirmButtonColor: '#800000'
                    });
                } else {
                    alert('List modal function not available. Please refresh the page.');
                }
            }
        }, 500);
    }
};

// Store current room ID for accepted requests
let currentRoomIdForAcceptedRequests = null;

// Show "View Accepted Requests" button when room schedule modal opens
document.addEventListener('DOMContentLoaded', function() {
    const roomScheduleModal = document.getElementById('roomScheduleModal');
    if (roomScheduleModal) {
        // Show button when modal starts to show
        roomScheduleModal.addEventListener('show.bs.modal', function() {
            // Get room ID from the modal title or stored variable
            const modalLabel = document.getElementById('roomScheduleModalLabel');
            if (modalLabel) {
                // Extract room ID from stored variables (set by openRoomScheduleModal)
                const rmId = window.currentRoomScheduleRmId || window.activeRoomId || currentRoomIdForAcceptedRequests || window.currentRoomIdForAcceptedRequests;
                const viewBtn = document.getElementById('viewAcceptedRequestsBtn');
                if (viewBtn && rmId) {
                    currentRoomIdForAcceptedRequests = rmId;
                    window.currentRoomIdForAcceptedRequests = rmId;
                    // Store room ID in button data attribute as backup
                    viewBtn.setAttribute('data-room-id', rmId);
                    viewBtn.dataset.roomId = rmId;
                    // Show button by removing d-none class and adding d-inline-flex
                    viewBtn.classList.remove('d-none');
                    viewBtn.classList.add('d-inline-flex');
                    viewBtn.style.visibility = 'visible';
                    viewBtn.style.opacity = '1';
                    console.log('View Room Reservations button shown via show.bs.modal event for room ID:', rmId);
                } else if (viewBtn) {
                    viewBtn.classList.add('d-none');
                    viewBtn.classList.remove('d-inline-flex');
                } else {
                    console.warn('View Room Reservations button not found in DOM');
                }
            }
        });
        
        // Also ensure button is visible after modal is fully shown
        roomScheduleModal.addEventListener('shown.bs.modal', function() {
            const rmId = window.currentRoomScheduleRmId || window.activeRoomId || currentRoomIdForAcceptedRequests || window.currentRoomIdForAcceptedRequests;
            const viewBtn = document.getElementById('viewAcceptedRequestsBtn');
            if (viewBtn && rmId) {
                // Store room ID in button data attribute as backup
                viewBtn.setAttribute('data-room-id', rmId);
                viewBtn.dataset.roomId = rmId;
                // Show button by removing d-none class and adding d-inline-flex
                viewBtn.classList.remove('d-none');
                viewBtn.classList.add('d-inline-flex');
                viewBtn.style.visibility = 'visible';
                viewBtn.style.opacity = '1';
                console.log('View Room Reservations button verified visible via shown.bs.modal event for room ID:', rmId);
            }
        });
        
        roomScheduleModal.addEventListener('hidden.bs.modal', function() {
            currentRoomIdForAcceptedRequests = null;
            window.currentRoomIdForAcceptedRequests = null;
            const viewBtn = document.getElementById('viewAcceptedRequestsBtn');
            if (viewBtn) {
                viewBtn.classList.add('d-none');
                viewBtn.classList.remove('d-inline-flex');
            }
        });
    }
});

// Function to view accepted room requests
window.viewAcceptedRoomRequests = function() {
    // Try multiple ways to get the room ID
    let rmId = currentRoomIdForAcceptedRequests || 
               window.currentRoomIdForAcceptedRequests || 
               window.currentRoomScheduleRmId || 
               window.activeRoomId;
    
    // If still not found, try to get it from the modal title or data attributes
    if (!rmId) {
        const modalLabel = document.getElementById('roomScheduleModalLabel');
        if (modalLabel) {
            // Try to extract from title if it contains room info
            const titleText = modalLabel.textContent || '';
            // Check if there's a data attribute on the modal
            const modal = document.getElementById('roomScheduleModal');
            if (modal) {
                rmId = modal.dataset.roomId || modal.getAttribute('data-room-id');
            }
        }
    }
    
    // Last resort: check if room ID is stored in the button itself
    if (!rmId) {
        const viewBtn = document.getElementById('viewAcceptedRequestsBtn');
        if (viewBtn) {
            rmId = viewBtn.dataset.roomId || viewBtn.getAttribute('data-room-id');
        }
    }
    
    console.log('Attempting to get room ID:', {
        currentRoomIdForAcceptedRequests,
        window_currentRoomIdForAcceptedRequests: window.currentRoomIdForAcceptedRequests,
        currentRoomScheduleRmId: window.currentRoomScheduleRmId,
        activeRoomId: window.activeRoomId,
        found: rmId
    });
    
    if (!rmId) {
        console.error('No room ID available for accepted requests');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Unable to determine room ID. Please close and reopen the room schedule modal.',
                confirmButtonColor: '#800000'
            });
        }
        return;
    }
    
    console.log('Loading accepted room requests for room ID:', rmId);
    
    // Show the accepted requests modal
    const acceptedModalEl = document.getElementById('acceptedRoomRequestsModal');
    if (!acceptedModalEl) {
        console.error('Accepted room requests modal not found');
        return;
    }
    
    // Set higher z-index to appear above the sidebar (1090) and schedule modal (1095)
    // Sidebar is at 1090, so accepted requests modal should be higher (1100)
    acceptedModalEl.style.zIndex = '1100';
    const modalDialog = acceptedModalEl.querySelector('.modal-dialog');
    if (modalDialog) {
        modalDialog.style.zIndex = '1101';
    }
    
    // Ensure modal is clickable
    acceptedModalEl.style.pointerEvents = 'auto';
    if (modalDialog) {
        modalDialog.style.pointerEvents = 'auto';
    }
    
    // Show loading state first
    const loadingEl = document.getElementById('acceptedRequestsLoading');
    const contentEl = document.getElementById('acceptedRequestsContent');
    const emptyEl = document.getElementById('acceptedRequestsEmpty');
    const tableBody = document.getElementById('acceptedRequestsTableBody');
    
    if (loadingEl) loadingEl.style.display = 'block';
    if (contentEl) contentEl.style.display = 'none';
    if (emptyEl) emptyEl.style.display = 'none';
    if (tableBody) tableBody.innerHTML = '';
    
    // Update modal title with room name
    const modalLabel = document.getElementById('acceptedRoomRequestsModalLabel');
    const roomName = window.activeRoomName || 'Room';
    if (modalLabel) {
        modalLabel.innerHTML = `<i class="bi bi-check-circle me-2"></i>Room Reservations - ${roomName}`;
    }
    
    // Ensure modal is in the DOM
    if (acceptedModalEl.parentElement !== document.body) {
        document.body.appendChild(acceptedModalEl);
    }
    
    // Remove any existing modal instances to avoid conflicts
    const existingInstance = bootstrap.Modal.getInstance(acceptedModalEl);
    if (existingInstance) {
        try {
            existingInstance.dispose();
        } catch (e) {
            console.warn('Error disposing existing modal instance:', e);
        }
    }
    
    // Adjust existing backdrop z-index before opening new modal
    const existingBackdrop = document.querySelector('.modal-backdrop');
    if (existingBackdrop) {
        // Keep schedule modal backdrop above sidebar (1090) but below schedule modal (1095)
        existingBackdrop.style.zIndex = '1094';
    }
    
    // Create fresh modal instance
    const acceptedModal = new bootstrap.Modal(acceptedModalEl, {
        backdrop: true,
        keyboard: true,
        focus: true
    });
    
    // Store globally for close handlers
    window.acceptedRoomRequestsModalInstance = acceptedModal;
    
    // Set up MutationObserver to catch when Bootstrap creates the backdrop
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.classList && node.classList.contains('modal-backdrop')) {
                    // Backdrop was just created - ensure it's lower than modal
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    const lastBackdrop = backdrops[backdrops.length - 1];
                    if (lastBackdrop === node) {
                        // This is the backdrop for our modal - force it to be lower
                        lastBackdrop.style.setProperty('z-index', '1098', 'important');
                        lastBackdrop.style.setProperty('position', 'fixed', 'important');
                        console.log('✅ Observer: Set backdrop z-index to 1098 (above sidebar, below modal)');
                    }
                    
                    // Always ensure modal is higher than sidebar (1090)
                    acceptedModalEl.style.setProperty('z-index', '1100', 'important');
                    acceptedModalEl.style.setProperty('position', 'fixed', 'important');
                }
            });
        });
    });
    
    // Start observing
    observer.observe(document.body, { childList: true, subtree: true });
    
    // Store observer for cleanup
    window.acceptedRequestsModalObserver = observer;
    
    // Cleanup observer after 5 seconds (backdrop should be created by then)
    setTimeout(() => {
        if (window.acceptedRequestsModalObserver) {
            window.acceptedRequestsModalObserver.disconnect();
            window.acceptedRequestsModalObserver = null;
        }
    }, 5000);
    
    // Show the modal
    console.log('Attempting to show accepted requests modal...');
    console.log('Modal element:', acceptedModalEl);
    console.log('Modal parent:', acceptedModalEl.parentElement);
    
    // Set modal z-index BEFORE showing (so it's ready) - above sidebar (1090)
    acceptedModalEl.style.setProperty('z-index', '1100', 'important');
    acceptedModalEl.style.setProperty('position', 'fixed', 'important');
    acceptedModalEl.style.display = 'flex';
    acceptedModalEl.style.alignItems = 'center';
    acceptedModalEl.style.justifyContent = 'center';
    
    try {
        acceptedModal.show();
        console.log('Modal show() called successfully');
        
        // IMMEDIATELY set z-index after modal.show() - Bootstrap creates backdrop instantly
        // Use multiple strategies to catch the backdrop
        const fixZIndex = () => {
            // Set modal z-index first - above sidebar (1090)
            acceptedModalEl.style.setProperty('z-index', '1100', 'important');
            acceptedModalEl.style.setProperty('position', 'fixed', 'important');
            
            // Then ensure ALL backdrops are above sidebar but below modal
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach((backdrop, index) => {
                if (backdrops.length > 1 && index === backdrops.length - 1) {
                    // Last backdrop (for this modal) - above sidebar (1090) but below modal (1100)
                    backdrop.style.setProperty('z-index', '1098', 'important');
                    backdrop.style.setProperty('position', 'fixed', 'important');
                } else if (backdrops.length === 1 && acceptedModalEl.classList.contains('show')) {
                    // Single backdrop - above sidebar (1090) but below modal (1100)
                    backdrop.style.setProperty('z-index', '1098', 'important');
                    backdrop.style.setProperty('position', 'fixed', 'important');
                }
            });
            
            if (modalDialog) {
                modalDialog.style.setProperty('z-index', '1101', 'important');
                modalDialog.style.margin = 'auto';
            }
        };
        
        // Try immediately, then with delays to catch async backdrop creation
        fixZIndex();
        requestAnimationFrame(fixZIndex);
        setTimeout(fixZIndex, 0);
        setTimeout(fixZIndex, 10);
        setTimeout(fixZIndex, 50);
        
        // Verify modal is actually shown
        setTimeout(() => {
            if (acceptedModalEl.classList.contains('show')) {
                console.log('✅ Modal is visible (has show class)');
            } else {
                console.warn('⚠️ Modal does not have show class, manually adding...');
                acceptedModalEl.classList.add('show');
                acceptedModalEl.style.display = 'flex';
                acceptedModalEl.style.alignItems = 'center';
                acceptedModalEl.style.justifyContent = 'center';
            }
        }, 100);
    } catch (error) {
        console.error('Error showing modal:', error);
        // Fallback: manually show
        acceptedModalEl.classList.add('show');
        acceptedModalEl.style.display = 'flex'; // Use flex for centering
        acceptedModalEl.style.alignItems = 'center'; // Vertical centering
        acceptedModalEl.style.justifyContent = 'center'; // Horizontal centering
        acceptedModalEl.style.zIndex = '1100'; // Higher than sidebar (1090) and backdrop (1098)
        acceptedModalEl.setAttribute('aria-hidden', 'false');
        acceptedModalEl.setAttribute('aria-modal', 'true');
        
        // Ensure backdrop exists
        let backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length < 2) {
            const newBackdrop = document.createElement('div');
            newBackdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(newBackdrop);
        }
    }
    
    // After modal is shown, adjust z-index and ensure interactivity
    setTimeout(() => {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        const scheduleModal = document.getElementById('roomScheduleModal');
        
        // Ensure schedule modal stays below the accepted-requests modal/backdrop when both are open
        if (scheduleModal) {
            // Above sidebar (1090), below accepted backdrop (1098) and modal (1100)
            scheduleModal.style.zIndex = '1095';
            const scheduleDialog = scheduleModal.querySelector('.modal-dialog');
            if (scheduleDialog) {
                scheduleDialog.style.zIndex = '1096';
            }
        }
        
        // CRITICAL: Force backdrop z-index to be LOWER than modal
        // Process all backdrops
        backdrops.forEach((backdrop, index) => {
            if (backdrops.length > 1) {
                if (index === 0) {
                    // First backdrop (schedule modal)
                    backdrop.style.setProperty('z-index', '1094', 'important');
                } else if (index === backdrops.length - 1) {
                    // Last backdrop (accepted requests modal) - Above sidebar (1090) but below modal (1100)
                    backdrop.style.setProperty('z-index', '1098', 'important');
                    backdrop.style.setProperty('position', 'fixed', 'important');
                    console.log('✅ Set last backdrop z-index to 1098');
                }
            } else if (backdrops.length === 1 && acceptedModalEl.classList.contains('show')) {
                // Single backdrop when accepted modal is shown - Above sidebar (1090) but below modal (1100)
                backdrop.style.setProperty('z-index', '1098', 'important');
                backdrop.style.setProperty('position', 'fixed', 'important');
                console.log('✅ Set single backdrop z-index to 1098');
            }
        });
        
        // CRITICAL: Ensure accepted modal is on top - MUST be higher than sidebar (1090) and backdrop (1098)
        acceptedModalEl.style.setProperty('z-index', '1100', 'important'); // Higher than sidebar (1090) and backdrop (1098)
        acceptedModalEl.style.setProperty('position', 'fixed', 'important'); // Fixed positioning
        acceptedModalEl.style.pointerEvents = 'auto';
        acceptedModalEl.style.display = 'flex'; // Enable flexbox for centering
        acceptedModalEl.style.alignItems = 'center'; // Vertical centering
        acceptedModalEl.style.justifyContent = 'center'; // Horizontal centering
        console.log('✅ Set modal z-index to 1100');
        
        if (modalDialog) {
            modalDialog.style.setProperty('z-index', '1101', 'important'); // Higher than modal container
            modalDialog.style.pointerEvents = 'auto';
            modalDialog.style.margin = 'auto'; // Ensure centering
            modalDialog.style.position = 'relative'; // Ensure proper stacking
        }
        
        // Final verification: Log current z-indexes for debugging
        console.log('Current z-indexes:', {
            modal: acceptedModalEl.style.zIndex,
            modalDialog: modalDialog ? modalDialog.style.zIndex : 'N/A',
            backdrops: Array.from(backdrops).map((b, i) => ({ index: i, zIndex: b.style.zIndex }))
        });
        
        // Set up continuous check to enforce z-index while modal is open
        // This will run every 100ms to ensure backdrop never covers modal
        const enforceZIndexInterval = setInterval(() => {
            if (!acceptedModalEl.classList.contains('show')) {
                // Modal is closed, stop checking
                clearInterval(enforceZIndexInterval);
                return;
            }
            
            // Always ensure modal is higher than sidebar (1090) and backdrop
            acceptedModalEl.style.setProperty('z-index', '1100', 'important');
            acceptedModalEl.style.setProperty('position', 'fixed', 'important');
            
            const currentBackdrops = document.querySelectorAll('.modal-backdrop');
            currentBackdrops.forEach((backdrop, index) => {
                if (currentBackdrops.length > 1 && index === currentBackdrops.length - 1) {
                    // Last backdrop - above sidebar (1090) but below modal (1100)
                    backdrop.style.setProperty('z-index', '1098', 'important');
                } else if (currentBackdrops.length === 1) {
                    backdrop.style.setProperty('z-index', '1098', 'important');
                }
            });
            
            if (modalDialog) {
                modalDialog.style.setProperty('z-index', '1101', 'important');
            }
        }, 100);
        
        // Clean up interval when modal closes
        acceptedModalEl.addEventListener('hidden.bs.modal', () => {
            clearInterval(enforceZIndexInterval);
        }, { once: true });
        
        // Handle backdrop clicks - only top backdrop should close accepted requests modal
        // Since we're using backdrop: 'static', Bootstrap won't auto-close, so we handle it manually
        backdrops.forEach((backdrop, index) => {
            if (index === backdrops.length - 1) {
                // Top backdrop - make it clickable to close accepted requests modal
                backdrop.style.pointerEvents = 'auto';
                backdrop.style.cursor = 'pointer';
                // Remove any existing listeners by cloning
                const newBackdrop = backdrop.cloneNode(false);
                backdrop.parentNode.replaceChild(newBackdrop, backdrop);
                // Add click handler to close accepted requests modal
                newBackdrop.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    console.log('Top backdrop clicked - closing accepted requests modal');
                    if (acceptedModalEl.classList.contains('show')) {
                        const modalInstance = window.acceptedRoomRequestsModalInstance || bootstrap.Modal.getInstance(acceptedModalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        } else {
                            // Manual hide
                            acceptedModalEl.classList.remove('show');
                            acceptedModalEl.style.display = 'none';
                            acceptedModalEl.setAttribute('aria-hidden', 'true');
                            acceptedModalEl.setAttribute('aria-modal', 'false');
                            newBackdrop.remove();
                        }
                    }
                }, { capture: true });
            } else {
                // Lower backdrops - prevent clicks (they're for schedule modal)
                backdrop.style.pointerEvents = 'none';
            }
        });
        
        // Force enable pointer events on entire modal
        acceptedModalEl.style.pointerEvents = 'auto';
        if (modalDialog) {
            modalDialog.style.pointerEvents = 'auto';
        }
        const acceptedModalContent = acceptedModalEl.querySelector('.modal-content');
        if (acceptedModalContent) {
            acceptedModalContent.style.pointerEvents = 'auto';
            acceptedModalContent.style.position = 'relative';
            acceptedModalContent.style.zIndex = '1072';
        }
        
        // Ensure close button is clickable - use direct event handler
        const closeBtn = acceptedModalEl.querySelector('.btn-close');
        if (closeBtn) {
            // Remove Bootstrap's data-bs-dismiss to handle manually
            closeBtn.removeAttribute('data-bs-dismiss');
            closeBtn.style.pointerEvents = 'auto';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.zIndex = '1073';
            closeBtn.style.position = 'relative';
            // Add direct click handler
            closeBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                console.log('Close button clicked - hiding accepted requests modal');
                const modalInstance = window.acceptedRoomRequestsModalInstance || bootstrap.Modal.getInstance(acceptedModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    // Manual hide
                    acceptedModalEl.classList.remove('show');
                    acceptedModalEl.style.display = 'none';
                    acceptedModalEl.setAttribute('aria-hidden', 'true');
                    acceptedModalEl.setAttribute('aria-modal', 'false');
                    // Remove top backdrop only
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops[backdrops.length - 1].remove();
                    }
                }
                return false;
            };
        }
        
        // Ensure footer close button works
        const footerCloseBtn = acceptedModalEl.querySelector('.modal-footer .btn-secondary');
        if (footerCloseBtn && footerCloseBtn.textContent.includes('Close')) {
            footerCloseBtn.style.pointerEvents = 'auto';
            footerCloseBtn.style.cursor = 'pointer';
            footerCloseBtn.removeAttribute('data-bs-dismiss');
            footerCloseBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                console.log('Footer close button clicked - hiding accepted requests modal');
                const modalInstance = window.acceptedRoomRequestsModalInstance || bootstrap.Modal.getInstance(acceptedModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    // Manual hide
                    acceptedModalEl.classList.remove('show');
                    acceptedModalEl.style.display = 'none';
                    acceptedModalEl.setAttribute('aria-hidden', 'true');
                    acceptedModalEl.setAttribute('aria-modal', 'false');
                    // Remove top backdrop only
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops[backdrops.length - 1].remove();
                    }
                }
                return false;
            };
        }
        
        // Ensure all interactive elements are clickable
        const allButtons = acceptedModalEl.querySelectorAll('button, .btn, input, select, textarea, a');
        allButtons.forEach(btn => {
            btn.style.pointerEvents = 'auto';
            if (btn.tagName === 'BUTTON' || btn.classList.contains('btn')) {
                btn.style.cursor = 'pointer';
            }
        });
        
        console.log('Modal interactivity enabled:', {
            closeBtn: !!closeBtn,
            footerCloseBtn: !!footerCloseBtn,
            allButtons: allButtons.length
        });
        
        console.log('Modal z-index adjusted:', {
            scheduleModal: scheduleModal ? scheduleModal.style.zIndex : 'not found',
            acceptedModal: acceptedModalEl.style.zIndex,
            backdrops: backdrops.length
        });
    }, 200);
    
    // Fetch accepted room requests
    // Determine correct API path based on current location
    const isInstructorDashboard = window.location.pathname.includes('/views/instructor/');
    const apiPath = '../../admin/management/get_room_requests.php';
    
    const params = new URLSearchParams();
    params.append('room', rmId);
    params.append('status', 'Accepted');
    
    // For instructors, also filter by their own requests
    if (isInstructorDashboard && window.currentUserId) {
        // The API should automatically filter by instructor, but we can add it as a hint
        console.log('Instructor dashboard detected, user ID:', window.currentUserId);
    }
    
    console.log('Fetching accepted room requests from:', apiPath, 'for room ID:', rmId);
    
    fetch(`${apiPath}?${params.toString()}`, {
        credentials: 'same-origin' // Include session cookies
    })
        .then(response => {
            console.log('API response status:', response.status);
            if (!response.ok) {
                // Try to get error message from response
                return response.text().then(text => {
                    console.error('API error response:', text);
                    try {
                        const errorData = JSON.parse(text);
                        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                    } catch (e) {
                        throw new Error(`HTTP error! status: ${response.status}. ${text.substring(0, 100)}`);
                    }
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Accepted room requests response:', data);
            
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';
            
            // Check for permission errors
            if (!data.success) {
                const errorMsg = data.message || 'Failed to load accepted room requests';
                console.error('API error:', errorMsg);
                
                // If unauthorized, show helpful message
                if (errorMsg.includes('Unauthorized') || errorMsg.includes('permission')) {
                    if (emptyEl) {
                        emptyEl.innerHTML = `
                            <i class="bi bi-shield-exclamation display-4 text-warning"></i>
                            <p class="mt-3 text-muted">You don't have permission to view room requests.</p>
                            <p class="text-muted small">Please contact your administrator if you need access.</p>
                        `;
                        emptyEl.style.display = 'block';
                    }
                } else {
                    if (emptyEl) {
                        emptyEl.innerHTML = `
                            <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                            <p class="mt-3 text-muted">Error loading accepted room requests.</p>
                            <p class="text-muted small">${errorMsg}</p>
                        `;
                        emptyEl.style.display = 'block';
                    }
                }
                if (tableBody) tableBody.innerHTML = '';
                return;
            }
            
            if (!data.data || data.data.length === 0) {
                // Show empty state
                if (emptyEl) emptyEl.style.display = 'block';
                if (tableBody) tableBody.innerHTML = '';
                return;
            }
            
            // Hide empty state
            if (emptyEl) emptyEl.style.display = 'none';
            
            // Populate table
            if (tableBody) {
                tableBody.innerHTML = '';
                
                data.data.forEach(request => {
                    const row = document.createElement('tr');
                    
                    // Format date
                    const requestDate = request.request_date || request.req_date || '-';
                    let formattedDate = requestDate;
                    if (requestDate !== '-') {
                        try {
                            const dateObj = new Date(requestDate);
                            formattedDate = dateObj.toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'short', 
                                day: 'numeric' 
                            });
                        } catch (e) {
                            formattedDate = requestDate;
                        }
                    }
                    
                    // Format time
                    const timeDisplay = request.request_time || request.time_display || '-';
                    
                    // Day
                    const dayNames = {
                        'Mon': 'Monday',
                        'Tue': 'Tuesday',
                        'Wed': 'Wednesday',
                        'Thu': 'Thursday',
                        'Fri': 'Friday',
                        'Sat': 'Saturday',
                        'Sun': 'Sunday'
                    };
                    const day = dayNames[request.day] || request.day || '-';
                    
                    // Duration
                    const duration = request.duration ? `${request.duration} hour${request.duration !== 1 ? 's' : ''}` : '-';
                    
                    // Requester
                    const requester = request.requester_name || '-';
                    
                    // Department
                    const dept = request.requester_dept_name || '-';
                    
                    // Comments
                    const comments = request.req_comment || '-';
                    
                    row.innerHTML = `
                        <td>${formattedDate}</td>
                        <td>${day}</td>
                        <td>${timeDisplay}</td>
                        <td>${duration}</td>
                        <td>${requester}</td>
                        <td>${dept}</td>
                        <td>${comments}</td>
                    `;
                    
                    tableBody.appendChild(row);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching accepted room requests:', error);
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';
            if (emptyEl) {
                let errorMessage = 'Error loading accepted room requests. Please try again.';
                if (error.message) {
                    errorMessage = error.message;
                }
                emptyEl.innerHTML = `
                    <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                    <p class="mt-3 text-muted">${errorMessage}</p>
                    <p class="text-muted small">If this persists, please contact your administrator.</p>
                `;
                emptyEl.style.display = 'block';
            }
            if (tableBody) tableBody.innerHTML = '';
        });
};
</script>

<style>
/* Custom styles for the EVSU-OCC dashboard */
.stats-card {
    background: #fff;
    border-left: 4px solid #800000;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-number {
    color: #800000;
    font-size: 1.25rem;
    font-weight: 700;
}

.dept-stats .stat-item {
    padding: 0.5rem 0;
}

.dashboard-card {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    background: #ffffff;
    padding: 1.5rem;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

/* Ensure accepted requests modal appears above schedule modal */
/* Schedule modal: z-index 1095 (above sidebar 1090, below accepted modal/backdrop) */
#roomScheduleModal {
    z-index: 1095 !important;
}

#roomScheduleModal .modal-dialog {
    z-index: 1096 !important;
}

/* Accepted requests modal: z-index 1100 (higher than sidebar 1090 and schedule modal 1095) */
#acceptedRoomRequestsModal {
    z-index: 1100 !important;
    pointer-events: auto !important;
}

#acceptedRoomRequestsModal .modal-dialog {
    z-index: 1101 !important;
    pointer-events: auto !important;
}

#acceptedRoomRequestsModal .modal-content {
    pointer-events: auto !important;
}

/* Ensure all interactive elements are clickable */
#acceptedRoomRequestsModal button,
#acceptedRoomRequestsModal .btn,
#acceptedRoomRequestsModal .btn-close,
#acceptedRoomRequestsModal input,
#acceptedRoomRequestsModal select,
#acceptedRoomRequestsModal textarea {
    pointer-events: auto !important;
}

/* Backdrop for schedule modal */
#roomScheduleModal.show ~ .modal-backdrop:first-of-type,
body.modal-open .modal-backdrop:first-of-type {
    z-index: 1094 !important;
}

/* Backdrop for accepted requests modal (should be above schedule modal backdrop but BELOW the modal itself) */
#acceptedRoomRequestsModal.show ~ .modal-backdrop:last-of-type,
body.modal-open .modal-backdrop:last-of-type,
body.modal-open #acceptedRoomRequestsModal.show ~ .modal-backdrop,
/* Catch all possible backdrop scenarios */
body:has(#acceptedRoomRequestsModal.show) .modal-backdrop:last-of-type,
.modal-backdrop:has(+ #acceptedRoomRequestsModal.show) {
    z-index: 1098 !important; /* Above sidebar (1090) but below modal (1100) */
    pointer-events: auto !important;
}

/* Universal fallback: Any backdrop when accepted modal is shown */
body.modal-open:has(#acceptedRoomRequestsModal.show) .modal-backdrop,
#acceptedRoomRequestsModal.show ~ .modal-backdrop {
    z-index: 1098 !important;
}

/* Lower backdrop (schedule modal) should not block clicks to accepted requests modal */
body.modal-open .modal-backdrop:first-of-type {
    pointer-events: none !important;
}

/* Ensure modal content is always interactive and above backdrop */
#acceptedRoomRequestsModal.show .modal-content {
    pointer-events: auto !important;
    position: relative !important;
    z-index: 1072 !important;
}

/* Ensure all buttons are clickable */
#acceptedRoomRequestsModal.show button,
#acceptedRoomRequestsModal.show .btn {
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative !important;
    z-index: 1103 !important;
}

/* When both modals are open, ensure accepted requests is on top */
body.modal-open #acceptedRoomRequestsModal.show,
#acceptedRoomRequestsModal.show {
    z-index: 1100 !important; /* Higher than sidebar (1090) and backdrop (1098) */
    display: flex !important; /* Ensure flexbox for centering */
    align-items: center !important; /* Vertical centering */
    justify-content: center !important; /* Horizontal centering */
    position: fixed !important; /* Ensure it's positioned above backdrop */
}

body.modal-open #acceptedRoomRequestsModal.show .modal-dialog,
#acceptedRoomRequestsModal.show .modal-dialog {
    z-index: 1101 !important; /* Higher than modal container */
    margin: auto !important; /* Ensure centering */
    position: relative !important; /* Ensure proper stacking */
}

body.modal-open #roomScheduleModal.show {
    z-index: 1095 !important;
}

body.modal-open #roomScheduleModal.show .modal-dialog {
    z-index: 1096 !important;
}

/* Ensure button is visible when shown */
#viewAcceptedRequestsBtn.d-inline-flex {
    display: inline-flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Compact styles for Room Request Modal */
#roomRequestModal .modal-body {
    padding: 1rem 1.25rem !important;
}

#roomRequestModal .form-label {
    margin-bottom: 0.25rem !important;
    font-size: 0.875rem !important;
}

#roomRequestModal .form-control,
#roomRequestModal .form-select {
    padding: 0.375rem 0.5rem !important;
    font-size: 0.875rem !important;
    height: calc(1.5em + 0.5rem + 2px) !important;
}

#roomRequestModal .input-group-text {
    padding: 0.375rem 0.5rem !important;
    font-size: 0.875rem !important;
}

#roomRequestModal .form-text {
    font-size: 0.75rem !important;
    margin-top: 0.25rem !important;
}

#roomRequestModal .alert {
    padding: 0.5rem 0.75rem !important;
    font-size: 0.75rem !important;
    margin-bottom: 0 !important;
}

#roomRequestModal .modal-header {
    padding: 0.75rem 1.25rem !important;
}

#roomRequestModal .modal-footer {
    padding: 0.75rem 1.25rem !important;
}

#roomRequestModal .modal-title {
    font-size: 1rem !important;
}
</style>

<!-- Room Schedule View Modal -->
<!-- Note: This modal is used by the Room Access Grants table. Ensure only one instance exists in the DOM. -->
<div class="modal fade" id="roomScheduleModal" tabindex="-1" aria-labelledby="roomScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title mb-0" id="roomScheduleModalLabel">Room Schedule</h5>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-info btn-sm d-none" id="viewAcceptedRequestsBtn" onclick="viewAcceptedRoomRequests()">
                        <i class="bi bi-check-circle me-1"></i>View Room Reservations
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body" style="max-height: 65vh; overflow-y: auto; padding: 1.5rem;">
                <!-- Date Filter -->
                <div class="mb-3">
                    <label for="roomScheduleCalendarDateFilter" class="form-label">
                        <i class="bi bi-calendar3 me-2"></i>Filter by Date
                    </label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="roomScheduleCalendarDateFilter" min="<?php echo date('Y-m-d'); ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearRoomScheduleCalendarDateFilter()">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                        <button class="btn btn-primary" type="button" onclick="applyRoomScheduleCalendarDateFilter()">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                    <small class="form-text text-muted">Select a date to filter schedules and room requests on that date</small>
                </div>
                
                <!-- Loading indicator -->
                <div class="text-center py-4" id="roomScheduleLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading room schedule...</p>
                </div>
                <!-- Calendar grid container (hidden until data is loaded) -->
                <div id="roomScheduleContent" style="display: none;">
                    <div class="alert alert-info mb-3" id="roomScheduleAlert">
                        <i class="bi bi-calendar-check me-2"></i>
                        <strong>Room Schedules</strong> - The following schedules are assigned to this room.
                    </div>
                    <div class="calendar-container" style="margin-top: 1rem;">
                        <div class="calendar-grid" style="display: flex; border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 70vh; overflow-y: auto; margin: 0; padding: 0; box-sizing: border-box; align-items: flex-start;">
                            <div class="time-column" id="roomScheduleTimeColumn" style="width: 100px; flex-shrink: 0; background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); border-right: 2px solid #e0e0e0; position: sticky; left: 0; z-index: 10; margin: 0; padding: 0; box-sizing: border-box; display: flex; flex-direction: column;">
                                <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11; box-sizing: border-box; margin: 0; flex-shrink: 0;">Time</div>
                            </div>
                            <div class="days-wrapper" id="roomScheduleDaysWrapper" style="display: grid; grid-template-columns: repeat(7, 1fr); flex-grow: 1; background-color: #ffffff; margin: 0; padding: 0; box-sizing: border-box;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" onclick="printRoomSchedule()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Room Schedule List Modal (for Room Access Grants Received) -->
<div class="modal fade" id="roomScheduleListModal" tabindex="-1" aria-labelledby="roomScheduleListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomScheduleListModalLabel">Available Time Slots</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 65vh; overflow-y: auto; padding: 1.5rem;">
                <!-- Date Filter -->
                <div class="mb-3">
                    <label for="roomScheduleDateFilter" class="form-label">
                        <i class="bi bi-calendar3 me-2"></i>Filter by Date
                    </label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="roomScheduleDateFilter" min="<?php echo date('Y-m-d'); ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearRoomScheduleDateFilter()">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                        <button class="btn btn-primary" type="button" onclick="applyRoomScheduleDateFilter()">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                    <small class="form-text text-muted">Select a date to check available time slots (excludes schedules and room requests on that date)</small>
                </div>
                
                <!-- Loading indicator -->
                <div class="text-center py-4" id="roomScheduleListLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading room schedule...</p>
                </div>
                <!-- List/Table container (hidden until data is loaded) -->
                <div id="roomScheduleListContent" style="display: none;">
                    <!-- Content will be dynamically generated as a table/list -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Room Request Modal -->
<div class="modal fade" id="roomRequestModal" tabindex="-1" aria-labelledby="roomRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomRequestModalLabel">Request To Use Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="roomRequestForm">
                    <input type="hidden" id="request_rm_id" name="rm_id">
                    <input type="hidden" id="request_rm_name" name="rm_name">
                    
                    <div class="mb-2">
                        <label class="form-label">Room</label>
                        <input type="text" class="form-control" id="request_room_display" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label for="request_date" class="form-label">Request Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="request_date" name="request_date" required min="<?php echo date('Y-m-d'); ?>">
                            <small class="form-text text-muted">Select the date you need to use the room</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="request_day" class="form-label">Day of Week <span class="text-danger">*</span></label>
                            <select class="form-select" id="request_day" name="day" required>
                                <option value="">Select Day</option>
                                <option value="Mon">Monday</option>
                                <option value="Tue">Tuesday</option>
                                <option value="Wed">Wednesday</option>
                                <option value="Thu">Thursday</option>
                                <option value="Fri">Friday</option>
                                <option value="Sat">Saturday</option>
                                <option value="Sun">Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="request_time_slot" class="form-label">Available Time Slot <span class="text-danger">*</span></label>
                            <select class="form-select" id="request_time_slot" name="time_slot" required>
                                <option value="">Select Time Slot</option>
                            </select>
                            <small class="form-text text-muted">Select an available time slot to see the time range</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="request_class_type" class="form-label">Class Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="request_class_type" name="class_type" required>
                                <?php if ($isInstructor): ?>
                                    <!-- Instructor: Only Make Up Class -->
                                    <option value="Make Up Class" selected>Make Up Class</option>
                                <?php else: ?>
                                    <!-- Admin: Both options with Regular Class as default -->
                                    <option value="Regular Class" selected>Regular Class</option>
                                    <option value="Make Up Class">Make Up Class</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label for="request_start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="request_start_time" name="start_time" required>
                            <small class="form-text text-muted">Select start time within the available slot</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="request_end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="request_end_time" name="end_time" required>
                            <small class="form-text text-muted">Select end time within the available slot</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label for="request_duration" class="form-label">Duration <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="request_duration" name="duration" min="0.5" max="8" step="0.5" value="1" readonly>
                                <span class="input-group-text">hours</span>
                            </div>
                            <small class="form-text text-muted">Automatically calculated from start and end time</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="alert alert-info mb-0" id="time_range_info" style="display: none;">
                                <small><i class="bi bi-info-circle me-1"></i><span id="time_range_text">Available time range will appear here</span></small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRoomRequest()">
                    <i class="bi bi-send me-1"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Accepted Room Requests Modal -->
<div class="modal fade" id="acceptedRoomRequestsModal" tabindex="-1" aria-labelledby="acceptedRoomRequestsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="acceptedRoomRequestsModalLabel">
                    <i class="bi bi-check-circle me-2"></i>Room Reservations
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 65vh; overflow-y: auto; padding: 1.5rem;">
                <!-- Loading indicator -->
                <div class="text-center py-4" id="acceptedRequestsLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading accepted room requests...</p>
                </div>
                
                <!-- Content container (hidden until data is loaded) -->
                <div id="acceptedRequestsContent" style="display: none;">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Room Reservations</strong> - The following room requests have been accepted for this room.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Requester</th>
                                    <th>Department</th>
                                    <th>Purpose/Comments</th>
                                </tr>
                            </thead>
                            <tbody id="acceptedRequestsTableBody">
                                <!-- Data will be populated here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="acceptedRequestsEmpty" class="text-center py-5" style="display: none;">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="mt-3 text-muted">No accepted room requests found for this room.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>