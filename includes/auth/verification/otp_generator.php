<?php
/**
 * OTP Generator for Email Verification
 * Generates and stores OTP codes for account verification
 */

require_once __DIR__ . '/../../../config/database.php';

/**
 * Generate and store OTP for account verification
 * 
 * @param int $acc_id Account ID
 * @param string $email Email address to send OTP to
 * @return array|false Returns array with 'otp' and 'expires_at' on success, false on failure
 */
function generateVerificationOTP($acc_id, $email) {
    global $conn;
    
    try {
        // Generate 6-digit OTP
        $otp = sprintf('%06d', mt_rand(100000, 999999));
        
        // Set expiration time (15 minutes from now)
        $expires_at = date('Y-m-d H:i:s', time() + 900); // 15 minutes = 900 seconds
        
        // Store OTP in database
        $stmt = $conn->prepare("
            UPDATE account 
            SET verification_otp = ?, 
                verification_otp_expires_at = ?,
                verification_token = NULL 
            WHERE acc_id = ? AND acc_email = ?
        ");
        
        $stmt->bind_param("ssis", $otp, $expires_at, $acc_id, $email);
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'otp' => $otp,
                'expires_at' => $expires_at
            ];
        } else {
            $stmt->close();
            error_log("OTP Generation Error: " . $conn->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("OTP Generation Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify OTP code
 * 
 * @param int $acc_id Account ID
 * @param string $otp OTP code to verify
 * @return array|false Returns user data on success, false on failure
 */
function verifyOTP($acc_id, $otp) {
    global $conn;
    
    try {
        // Get user with matching OTP
        $stmt = $conn->prepare("
            SELECT acc_id, fname, lname, acc_email, acc_status, 
                   verification_otp, verification_otp_expires_at 
            FROM account 
            WHERE acc_id = ? AND verification_otp = ?
        ");
        
        $stmt->bind_param("is", $acc_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            return false; // Invalid OTP
        }
        
        // Check if OTP has expired
        $now = date('Y-m-d H:i:s');
        if ($user['verification_otp_expires_at'] < $now) {
            return false; // OTP expired
        }
        
        return $user;
    } catch (Exception $e) {
        error_log("OTP Verification Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear OTP after successful verification
 * 
 * @param int $acc_id Account ID
 * @return bool
 */
function clearOTP($acc_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE account 
            SET verification_otp = NULL, 
                verification_otp_expires_at = NULL,
                verification_token = NULL 
            WHERE acc_id = ?
        ");
        
        $stmt->bind_param("i", $acc_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Clear OTP Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Get account by email for OTP verification
 * 
 * @param string $email Email address
 * @return array|false Returns user data on success, false on failure
 */
function getAccountByEmailForOTP($email) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT acc_id, fname, lname, acc_email, acc_status, dept_id,
                   verification_otp, verification_otp_expires_at 
            FROM account 
            WHERE acc_email = ?
        ");
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user ? $user : false;
    } catch (Exception $e) {
        error_log("Get Account by Email Exception: " . $e->getMessage());
        return false;
    }
}
?>