<?php
/**
 * Mark a notification as read
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Get user info
$userInfo = getUserInfo();

if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Get notification ID from request
$input = json_decode(file_get_contents('php://input'), true);
$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;

if ($notificationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
    exit;
}

try {
    // Mark notification as read by updating audit_log.details JSON column
    // Add marked_as_read timestamp to the notification's details
    $currentTimestamp = date('Y-m-d H:i:s');
    
    // Check if notification exists and get current details
    $checkStmt = $conn->prepare("SELECT details FROM audit_log WHERE log_id = ?");
    $checkStmt->bind_param("i", $notificationId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Notification not found.']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $details = $row['details'] ?? '{}';
    $detailsArray = json_decode($details, true) ?? [];
    
    // Only update if not already marked as read
    if (!isset($detailsArray['marked_as_read'])) {
        // Update the details JSON to add marked_as_read timestamp
        $updateStmt = $conn->prepare("UPDATE audit_log SET details = JSON_SET(COALESCE(details, '{}'), '$.marked_as_read', ?) WHERE log_id = ?");
        $updateStmt->bind_param("si", $currentTimestamp, $notificationId);
        
        if ($updateStmt->execute()) {
            $updateStmt->close();
            $checkStmt->close();
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read.'
            ]);
        } else {
            $updateStmt->close();
            $checkStmt->close();
            throw new Exception("Failed to update notification");
        }
    } else {
        // Already marked as read
        $checkStmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Notification already marked as read.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while marking notification as read.'
    ]);
}
?>

