<?php
/**
 * Verify OTP Handler
 * Processes OTP verification requests
 */

session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth/security_middleware.php';
require_once __DIR__ . '/otp_generator.php';

// Set content type to JSON for AJAX requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');

// Validate input
if (empty($email) || empty($otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and OTP code are required']);
    exit;
}

// Validate OTP format (6 digits)
if (!preg_match('/^\d{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format. Please enter a 6-digit code.']);
    exit;
}

try {
    // Get account by email
    $user = getAccountByEmailForOTP($email);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }
    
    // Check if account is already verified
    if ($user['acc_status'] === 'Active') {
        // Clear any remaining OTP
        clearOTP($user['acc_id']);
        echo json_encode([
            'success' => true, 
            'message' => 'Account is already verified',
            'already_verified' => true
        ]);
        exit;
    }
    
    // Verify OTP
    $verifiedUser = verifyOTP($user['acc_id'], $otp);
    
    if (!$verifiedUser) {
        // Check if OTP exists but expired
        if ($user['verification_otp'] && $user['verification_otp_expires_at']) {
            $now = date('Y-m-d H:i:s');
            if ($user['verification_otp_expires_at'] < $now) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'OTP code has expired. Please request a new one.']);
                exit;
            }
        }
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please check and try again.']);
        exit;
    }
    
    // Update account status to Active and clear OTP
    $updateStmt = $conn->prepare("
        UPDATE account 
        SET acc_status = 'Active', 
            verification_otp = NULL, 
            verification_otp_expires_at = NULL,
            verification_token = NULL 
        WHERE acc_id = ?
    ");
    $updateStmt->bind_param("i", $verifiedUser['acc_id']);
    
    if ($updateStmt->execute()) {
        // Log the verification action to audit_log
        $action = "Email verified via OTP: {$verifiedUser['fname']} {$verifiedUser['lname']}";
        $details = json_encode([
            'account_id' => $verifiedUser['acc_id'],
            'email' => $verifiedUser['acc_email'],
            'verification_method' => 'OTP'
        ]);
        
        $logStmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        if ($logStmt) {
            $logStmt->bind_param("iss", $verifiedUser['acc_id'], $action, $details);
            $logStmt->execute();
            $logStmt->close();
        }
        
        $updateStmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Account verified successfully! You can now log in.',
            'redirect' => 'index.php?message=verification_success'
        ]);
    } else {
        $updateStmt->close();
        error_log("Failed to update account status: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to verify account. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("OTP Verification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during verification. Please try again.']);
}
?>