<?php
session_start();
include '../../config/database.php';

header('Content-Type: application/json');

// Allow Admin Support, Admin, and Moderator to update account status
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
$newStatus = trim($_POST['status'] ?? '');

// Validate status
$validStatuses = ['Active', 'Inactive', 'Pending'];
if (!in_array($newStatus, $validStatuses)) {
  echo json_encode(['success' => false, 'message' => 'Invalid status']);
  exit();
}

if ($accId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
  exit();
}

try {
  // Ensure updater has access (non-IT limited to same department)
  if ($roleId !== 1) {
    $deptStmt = $conn->prepare('SELECT dept_id FROM account WHERE acc_id = ?');
    $deptStmt->bind_param('i', $accId);
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    $row = $deptRes->fetch_assoc();
    $deptStmt->close();
    if (!$row) { 
      echo json_encode(['success'=>false,'message'=>'Account not found']); 
      exit(); 
    }
    $targetDeptId = (int)$row['dept_id'];
    $actorDeptId = (int)($_SESSION['dept_id'] ?? 0);
    if ($roleId !== 1 && $actorDeptId !== $targetDeptId && $roleId !== 2) {
      http_response_code(403);
      echo json_encode(['success'=>false,'message'=>'Forbidden']);
      exit();
    }
  }

  // Update account status
  $stmt = $conn->prepare('UPDATE account SET acc_status = ? WHERE acc_id = ?');
  $stmt->bind_param('si', $newStatus, $accId);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['success' => true, 'message' => 'Account status updated successfully']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>


