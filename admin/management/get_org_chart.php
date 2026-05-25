<?php
session_start();
include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Enable verbose errors for this endpoint (safe for admin-only use)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Allow only admin support / admin (role name or role_id)
$sessionRole = strtolower($_SESSION['role'] ?? '');
$sessionRoleId = (int)($_SESSION['role_id'] ?? 0);
$isAllowed = in_array($sessionRole, ['admin support', 'admin'], true) || in_array($sessionRoleId, [1, 2], true);
if (!isset($_SESSION['acc_id']) || !$isAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Role/designation/rank ordering (reuses existing lists in the app)
$roleOrder = ['Admin support', 'Admin', 'Moderator', 'User'];
$designationOrder = [
    'Vice President',
    'Campus Director',
    'Dean',
    'Director',
    'Head',
    'Chairperson/Coordinator/As Officer in Faculty Association',
    'None'
];
$rankOrder = [
    'University Professor',
    'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
    'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
    'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
    'Instructor III', 'Instructor II', 'Instructor I',
    'None'
];

// Helper to get ordering index
function idx($value, $orderList) {
    $i = array_search($value, $orderList, true);
    return $i === false ? count($orderList) : $i;
}

try {
    // Fetch user + instructor details
    $query = "
        SELECT 
            a.acc_id,
            a.fname,
            a.minitial,
            a.lname,
            a.acc_user,
            a.acc_status,
            r.role_name,
            i.designation,
            i.rank,
            d.dept_name,
            p.program_name
        FROM account a
        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN instructor i ON i.inst_user = a.acc_user
        LEFT JOIN department d ON a.dept_id = d.dept_id
        LEFT JOIN program p ON i.program_id = p.program_id
        WHERE a.acc_status IN ('Active', 'Inactive', 'Pending')
    ";
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception('DB error: ' . $conn->error);
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['acc_id'],
            'username' => $row['acc_user'],
            'fname' => $row['fname'],
            'mname' => $row['minitial'],
            'lname' => $row['lname'],
            'status' => $row['acc_status'],
            'role' => $row['role_name'] ?? 'User',
            'designation' => $row['designation'] ?? 'None',
            'rank' => $row['rank'] ?? 'None',
            'department' => $row['dept_name'] ?? '',
            'program' => $row['program_name'] ?? ''
        ];
    }

    // Sort users by role > designation > rank > name
    usort($users, function ($a, $b) use ($roleOrder, $designationOrder, $rankOrder) {
        $r = idx($a['role'], $roleOrder) <=> idx($b['role'], $roleOrder);
        if ($r !== 0) return $r;
        $d = idx($a['designation'], $designationOrder) <=> idx($b['designation'], $designationOrder);
        if ($d !== 0) return $d;
        $rk = idx($a['rank'], $rankOrder) <=> idx($b['rank'], $rankOrder);
        if ($rk !== 0) return $rk;
        return strcasecmp($a['lname'] . $a['fname'], $b['lname'] . $b['fname']);
    });

    echo json_encode([
        'success' => true,
        'data' => $users,
        'meta' => [
            'roleOrder' => $roleOrder,
            'designationOrder' => $designationOrder,
            'rankOrder' => $rankOrder
        ]
    ]);
} catch (Exception $e) {
    error_log("Error in get_org_chart.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load org chart data',
        'error' => $e->getMessage()
    ]);
}

