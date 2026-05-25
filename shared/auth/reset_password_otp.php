<?php
/**
 * Reset Password with OTP Verification
 * Verifies OTP and resets user password
 */

// Set content type to JSON
header('Content-Type: application/json');

require_once 'includes/db_connect.php';
require_once 'includes/dashboard_permissions.php';

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
    $otp_code = trim($_POST['otp_code'] ?? '');
    $new_password = $_POST['reset_new_password'] ?? '';
    $confirm_password = $_POST['reset_confirm_password'] ?? '';
    
    // Validation
    if (empty($otp_code) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
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
    
    // Check if OTP session exists
    if (!isset($_SESSION['pwd_reset_otp'])) {
        echo json_encode(['success' => false, 'message' => 'No verification code found. Please request a new one.']);
        exit;
    }
    
    $otp_data = $_SESSION['pwd_reset_otp'];
    
    // Verify OTP
    if ($otp_data['acc_id'] != $acc_id) {
        echo json_encode(['success' => false, 'message' => 'Verification code does not match this account']);
        exit;
    }
    
    if (time() > $otp_data['expires_at']) {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
        unset($_SESSION['pwd_reset_otp']);
        exit;
    }
    
    if ($otp_code !== $otp_data['code']) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
        exit;
    }
    
    // Check if new password is different from current password
    $stmt = $conn->prepare("SELECT acc_pass FROM account WHERE acc_id = ?");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user && password_verify($new_password, $user['acc_pass'])) {
        echo json_encode(['success' => false, 'message' => 'New password must be different from your current password']);
        exit;
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE account SET acc_pass = ? WHERE acc_id = ?");
    $stmt->bind_param("si", $hashed_password, $acc_id);
    
    if ($stmt->execute()) {
        // Clear OTP session
        unset($_SESSION['pwd_reset_otp']);
        
        // Log the password reset
        try {
            $log_stmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
            if ($log_stmt) {
                $action = 'Password reset via OTP verification';
                $details = json_encode(['method' => 'email_otp', 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                $log_stmt->bind_param("iss", $acc_id, $action, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } catch (Exception $e) {
            // Log error but don't fail the password reset
            error_log("Audit logging failed: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
