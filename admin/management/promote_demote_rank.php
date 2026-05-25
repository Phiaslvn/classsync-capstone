<?php
session_start();
include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check authentication and authorization
$roleId = (int)($_SESSION['role_id'] ?? 0);
if (!isset($_SESSION['acc_id']) || !in_array($roleId, [1, 2])) {
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
$action = trim($_POST['action'] ?? ''); // 'promote' or 'demote'

if ($accId <= 0 || !in_array($action, ['promote', 'demote'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Rank hierarchy (from highest to lowest)
$rankHierarchy = [
    'University Professor',
    'Professor VI', 'Professor V', 'Professor IV', 'Professor III', 'Professor II', 'Professor I',
    'Associate Professor V', 'Associate Professor IV', 'Associate Professor III', 'Associate Professor II', 'Associate Professor I',
    'Assistant Professor IV', 'Assistant Professor III', 'Assistant Professor II', 'Assistant Professor I',
    'Instructor III', 'Instructor II', 'Instructor I',
    'None' // Explicitly represent no rank as the lowest point in the hierarchy
];

try {
    // Get current rank
    $stmt = $conn->prepare('SELECT i.rank, i.inst_id, a.acc_user FROM instructor i JOIN account a ON i.inst_user = a.acc_user WHERE a.acc_id = ?');
    $stmt->bind_param('i', $accId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row || !$row['inst_id']) {
        echo json_encode(['success' => false, 'message' => 'Instructor record not found']);
        exit();
    }
    
    $currentRank = $row['rank'];
    if ($currentRank === null || $currentRank === '') {
        $currentRank = 'None';
    }
    $instId = $row['inst_id'];
    $username = $row['acc_user'];
    
    // Find current rank index
    $currentIndex = array_search($currentRank, $rankHierarchy);
    
    if ($currentIndex === false) {
        // If rank not in hierarchy, default to lowest
        $currentIndex = count($rankHierarchy) - 1;
    }
    
    // Calculate new rank
    if ($action === 'promote') {
        if ($currentIndex === 0) {
            echo json_encode(['success' => false, 'message' => 'User already has the highest rank']);
            exit();
        }
        $newIndex = $currentIndex - 1;
    } else { // demote
        if ($currentIndex === count($rankHierarchy) - 1) {
            echo json_encode(['success' => false, 'message' => 'User already has the lowest rank']);
            exit();
        }
        $newIndex = $currentIndex + 1;
    }
    
    $newRank = $rankHierarchy[$newIndex];
    
    // Update rank
    $updateStmt = $conn->prepare('UPDATE instructor SET rank = ? WHERE inst_id = ?');
    $updateStmt->bind_param('si', $newRank, $instId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Log the action
    $actorId = $_SESSION['acc_id'];
    $actionText = ucfirst($action) . 'd rank from ' . $currentRank . ' to ' . $newRank;
    $details = json_encode([
        'acc_id' => $accId,
        'username' => $username,
        'old_rank' => $currentRank,
        'new_rank' => $newRank,
        'action' => $action
    ]);
    
    $logStmt = $conn->prepare('INSERT INTO audit_log (acc_id, action, details) VALUES (?, ?, ?)');
    $logStmt->bind_param('iss', $actorId, $actionText, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Rank ' . $action . 'd successfully',
        'old_rank' => $currentRank,
        'new_rank' => $newRank
    ]);
    
} catch (Exception $e) {
    error_log("Error in promote_demote_rank.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating rank']);
}
?>


