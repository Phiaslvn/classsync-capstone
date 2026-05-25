<?php
/**
 * Send OTP for Password Reset
 * Sends verification code to user's email for password reset
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
    $email = trim($_POST['email_for_otp'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email address is required']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Verify that the email belongs to the current user
    $stmt = $conn->prepare("SELECT acc_email, fname, lname FROM account WHERE acc_id = ?");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    if (strtolower($email) !== strtolower($user['acc_email'])) {
        echo json_encode(['success' => false, 'message' => 'Email address does not match your registered email']);
        exit;
    }
    
    // Generate OTP
    $otp = sprintf('%06d', mt_rand(100000, 999999));
    
    // Store OTP in session with expiration (10 minutes)
    $_SESSION['pwd_reset_otp'] = [
        'code' => $otp,
        'expires_at' => time() + 600, // 10 minutes
        'email' => $email,
        'acc_id' => $acc_id
    ];
    
    // Send email
    $subject = 'Password Reset Verification Code - EVSU-OCC System';
    $message = "Hello " . $user['fname'] . ' ' . $user['lname'] . ",\n\n" .
              "You have requested to reset your password. Your verification code is: {$otp}\n\n" .
              "This code will expire in 10 minutes.\n\n" .
              "If you did not request this password reset, please ignore this email.\n\n" .
              "Best regards,\nEVSU-OCC Scheduling System";
    
    $headers = "From: noreply@evsu.edu.ph\r\n";
    $headers .= "Reply-To: noreply@evsu.edu.ph\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $sent = @mail($email, $subject, $message, $headers);
    
    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Verification code sent to your email']);
    } else {
        // For development/testing purposes, show the OTP if email fails
        echo json_encode([
            'success' => true, 
            'message' => 'Email sending failed. For testing purposes, your verification code is: ' . $otp
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
