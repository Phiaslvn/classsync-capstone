<?php
/**
 * Instructor Change Password Script
 * Handles password changes for instructor users
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userInfo = getUserInfo();
if (!$userInfo) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User information not found']);
    exit;
}

$acc_id = $userInfo['acc_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
        exit;
    }

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
        exit;
    }

    // Get current password from database
    $stmt = $conn->prepare("SELECT acc_pass FROM account WHERE acc_id = ?");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify current password
    if (!password_verify($current_password, $user['acc_pass'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password in database
    $stmt = $conn->prepare("UPDATE account SET acc_pass = ? WHERE acc_id = ?");
    $stmt->bind_param("si", $hashed_password, $acc_id);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . $stmt->error]);
    } elseif ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Password was not updated. Please try again or contact support.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>

