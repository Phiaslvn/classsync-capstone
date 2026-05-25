<?php
/**
 * Send OTP Email for Account Verification
 * Sends OTP code to user's email for account verification
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../includes/database/send_email.php';
require_once __DIR__ . '/otp_generator.php';

/**
 * Send verification OTP email to user
 * 
 * @param int $acc_id Account ID
 * @param string $email Email address
 * @param string $fname First name
 * @param string $lname Last name
 * @param string $dept_name Department name (optional)
 * @param string $role_name Role name (optional)
 * @param string $username Username (optional)
 * @param string $password Password (optional)
 * @return bool True on success, false on failure
 */
function sendVerificationOTPEmail($acc_id, $email, $fname, $lname, $dept_name = '', $role_name = '', $username = '', $password = '') {
    // Generate OTP
    $otpData = generateVerificationOTP($acc_id, $email);
    
    if (!$otpData) {
        error_log("Failed to generate OTP for account ID: $acc_id");
        return false;
    }
    
    $otp = $otpData['otp'];
    $expires_at = $otpData['expires_at'];
    
    // Prepare email content
    $subject = "EVSU-OCC Scheduling System - Account Verification Code";
    
    $dept_info = $dept_name ? "<li><strong>Department:</strong> $dept_name</li>" : '';
    $role_info = $role_name ? "<li><strong>Role:</strong> $role_name</li>" : '';
    $username_info = $username ? "<li><strong>Username:</strong> $username</li>" : '';
    $password_info = $password ? "<li><strong>Password:</strong> <span style='background-color: #fff3cd; padding: 2px 6px; border-radius: 3px; font-family: monospace;'>$password</span></li>" : '';
    
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #800000; text-align: center;'>EVSU-OCC Scheduling System</h2>
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #333;'>Welcome, $fname $lname!</h3>
                <p>Your account has been created for the EVSU-OCC Scheduling System.</p>
                <p>To activate your account, please use the verification code below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='background-color: #800000; color: white; padding: 20px; border-radius: 8px; display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 5px;'>
                        $otp
                    </div>
                </div>
                
                <p style='text-align: center; color: #666; font-size: 14px;'>
                    This code will expire in 15 minutes.
                </p>
                
                <p><strong>Account Details:</strong></p>
                <ul>
                    <li><strong>Name:</strong> $fname $lname</li>
                    <li><strong>Email:</strong> $email</li>
                    $username_info
                    $password_info
                    $dept_info
                    $role_info
                </ul>
                
                <p style='margin-top: 30px; color: #666; font-size: 14px;'>
                    If you didn't create this account, please ignore this email.
                </p>
                
                <p style='color: #800000; font-weight: bold; margin-top: 20px;'>
                    Please enter this code on the verification page to activate your account.
                </p>
            </div>
            <p style='text-align: center; color: #666; font-size: 12px;'>
                © 2025 EVSU-OCC Scheduling System. All rights reserved.
            </p>
        </div>
    ";
    
    // Send email
    $result = sendEmail($email, $subject, $body);
    
    if ($result) {
        error_log("Verification OTP sent successfully to: $email (Account ID: $acc_id)");
    } else {
        error_log("Failed to send verification OTP to: $email (Account ID: $acc_id)");
    }
    
    return $result;
}
?>