<?php
/**
 * List or upsert per-department instructor employment (appointment_status + instruction_hours).
 * Use this to set different Part-Time vs Regular lines per department after migration.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/instructor_department_appointments.php';

header('Content-Type: application/json');

if (!isset($_SESSION['acc_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
if (!in_array($roleId, [1, 2, 3], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!ida_appointments_table_exists($conn)) {
    echo json_encode(['success' => false, 'message' => 'Table instructor_department_appointment does not exist. Run the migration SQL first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $inst_id = (int) ($_GET['inst_id'] ?? 0);
    if ($inst_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'inst_id is required']);
        exit;
    }
    $stmt = $conn->prepare(
        'SELECT a.id, a.dept_id, d.dept_name, a.appointment_status, a.instruction_hours
         FROM instructor_department_appointment a
         JOIN department d ON d.dept_id = a.dept_id
         WHERE a.inst_id = ?
         ORDER BY d.dept_name'
    );
    $stmt->bind_param('i', $inst_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'appointments' => $rows]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inst_id = (int) ($_POST['inst_id'] ?? 0);
    $dept_id = (int) ($_POST['dept_id'] ?? 0);
    $appointment_status = trim($_POST['appointment_status'] ?? '');
    $instruction_hours = (int) ($_POST['instruction_hours'] ?? 0);

    if ($inst_id <= 0 || $dept_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'inst_id and dept_id are required']);
        exit;
    }

    $valid = ['Regular', 'Part-Time', 'Contractual'];
    if (!in_array($appointment_status, $valid, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid appointment_status']);
        exit;
    }

    $ok = ida_upsert_appointment($conn, $inst_id, $dept_id, $appointment_status, $instruction_hours);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Department appointment saved.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save appointment.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
