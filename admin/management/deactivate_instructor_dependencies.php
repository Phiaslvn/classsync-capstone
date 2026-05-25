<?php
session_start();
include '../../config/database.php';

header('Content-Type: application/json');
if (!$conn) {
  die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
// Security: only allow logged-in roles that can manage users
$roleId = (int)($_SESSION['role_id'] ?? 0);
if (!isset($_SESSION['acc_id']) || !in_array($roleId, [1, 2, 3])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit();
}

$accId = (int)($_POST['acc_id'] ?? 0);

if ($accId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
  exit();
}

try {
  // Get account user info (username) for mapping to instructor
  $stmt = $conn->prepare("SELECT acc_user FROM account WHERE acc_id = ? LIMIT 1");
  $stmt->bind_param("i", $accId);
  $stmt->execute();
  $result = $stmt->get_result();
  $userInfo = $result->fetch_assoc();
  $stmt->close();

  if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    exit();
  }

  // Find related instructor by username
  $inst_id = null;
  $stmt = $conn->prepare("SELECT inst_id FROM instructor WHERE inst_user = ? LIMIT 1");
  $stmt->bind_param("s", $userInfo['acc_user']);
  $stmt->execute();
  $instResult = $stmt->get_result();
  if ($row = $instResult->fetch_assoc()) {
    $inst_id = (int)$row['inst_id'];
  }
  $stmt->close();

  if ($inst_id === null) {
    // No instructor record, nothing to clean up
    echo json_encode([
      'success' => true,
      'has_schedules' => false,
      'removed_schedules' => 0,
      'removed_workloads' => 0,
      'removed_requests' => 0
    ]);
    exit();
  }

  // Count existing related records first
  $scheduleCount = 0;
  $workloadCount = 0;
  $requestCount = 0;

  $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM schedule WHERE inst_id = ?");
  $stmt->bind_param("i", $inst_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $scheduleCount = (int)($res['cnt'] ?? 0);
  $stmt->close();

  $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM faculty_workload WHERE inst_id = ?");
  $stmt->bind_param("i", $inst_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $workloadCount = (int)($res['cnt'] ?? 0);
  $stmt->close();

  $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM room_request WHERE inst_id = ?");
  $stmt->bind_param("i", $inst_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $requestCount = (int)($res['cnt'] ?? 0);
  $stmt->close();

  // If nothing to remove, just report back
  if ($scheduleCount === 0 && $workloadCount === 0 && $requestCount === 0) {
    echo json_encode([
      'success' => true,
      'has_schedules' => false,
      'removed_schedules' => 0,
      'removed_workloads' => 0,
      'removed_requests' => 0
    ]);
    exit();
  }

  // Remove dependent records in safe order
  $conn->begin_transaction();

  // 1. Delete from faculty_workload
  if ($workloadCount > 0) {
    $stmt = $conn->prepare("DELETE FROM faculty_workload WHERE inst_id = ?");
    $stmt->bind_param("i", $inst_id);
    $stmt->execute();
    $stmt->close();
  }

  // 2. Delete from schedule
  if ($scheduleCount > 0) {
    $stmt = $conn->prepare("DELETE FROM schedule WHERE inst_id = ?");
    $stmt->bind_param("i", $inst_id);
    $stmt->execute();
    $stmt->close();
  }

  // 3. Delete from room_request
  if ($requestCount > 0) {
    $stmt = $conn->prepare("DELETE FROM room_request WHERE inst_id = ?");
    $stmt->bind_param("i", $inst_id);
    $stmt->execute();
    $stmt->close();
  }

  $conn->commit();

  echo json_encode([
    'success' => true,
    'has_schedules' => ($scheduleCount + $workloadCount + $requestCount) > 0,
    'removed_schedules' => $scheduleCount,
    'removed_workloads' => $workloadCount,
    'removed_requests' => $requestCount
  ]);
} catch (Exception $e) {
  if ($conn->errno) {
    $conn->rollback();
  }
  error_log('Error in deactivate_instructor_dependencies.php: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error while cleaning up schedules.']);
}




