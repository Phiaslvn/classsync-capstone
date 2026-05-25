<?php
/**
 * Resend OTP Handler
 * Resends verification OTP to user's email
 */

session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/send_otp_email.php';
require_once __DIR__ . '/otp_generator.php';

// Set content type to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

// Validate input
if (empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email address is required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
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
        echo json_encode([
            'success' => true, 
            'message' => 'Account is already verified',
            'already_verified' => true
        ]);
        exit;
    }
    
    // Get department and role info for email
    $dept_name = '';
    $role_name = '';
    
    if ($user['dept_id']) {
        $dept_stmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
        $dept_stmt->bind_param("i", $user['dept_id']);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        $dept_data = $dept_result->fetch_assoc();
        $dept_name = $dept_data['dept_name'] ?? '';
        $dept_stmt->close();
    }
    
    // Get role name
    $role_stmt = $conn->prepare("
        SELECT r.role_name 
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.id 
        WHERE ur.acc_id = ? 
        LIMIT 1
    ");
    $role_stmt->bind_param("i", $user['acc_id']);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    $role_data = $role_result->fetch_assoc();
    $role_name = $role_data['role_name'] ?? '';
    $role_stmt->close();
    
    // Get username from account (password is not retrievable, so we'll skip it for resend)
    $username = $user['acc_user'] ?? '';
    $default_password = 'evsu-occ'; // Default password for new accounts
    
    // Send new OTP
    $result = sendVerificationOTPEmail(
        $user['acc_id'],
        $user['acc_email'],
        $user['fname'],
        $user['lname'],
        $dept_name,
        $role_name,
        $username,
        $default_password
    );
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'A new verification code has been sent to your email address.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send verification code. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Resend OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>