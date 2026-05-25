<?php
/**
 * Mail configuration for EVSU-OCC Scheduling System
 *
 * For local/production setup:
 * - Copy config/mail.example.php to config/mail.local.php and set real SMTP values, OR
 * - Edit the constants below directly on the deployment server.
 */

if (is_readable(__DIR__ . '/mail.local.php')) {
    require_once __DIR__ . '/mail.local.php';
    return;
}

define('MAIL_ENABLED', true);

define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USERNAME', 'your-email@gmail.com');
define('MAIL_SMTP_PASSWORD', 'YOUR_SMTP_APP_PASSWORD_HERE');
define('MAIL_SMTP_SECURE', 'tls');

define('MAIL_FROM_ADDRESS', 'noreply@your-domain.edu.ph');
define('MAIL_FROM_NAME', 'EVSU-OCC Scheduling System');
define('MAIL_REPLY_TO', 'support@your-domain.edu.ph');
define('MAIL_REPLY_TO_NAME', 'EVSU-OCC Support');

define('MAIL_OTP_SUBJECT', 'Password Change Verification Code - EVSU-OCC Scheduling System');
define('MAIL_OTP_TEMPLATE', 'Hello {name},

You have requested to change your password for the EVSU-OCC Scheduling System.

Your verification code is: {otp}

This code will expire in 10 minutes.

If you did not request this password change, please ignore this email.

Best regards,
EVSU-OCC Scheduling System Team');

define('MAIL_ERROR_DISABLED', 'Email sending is currently disabled. Please contact the administrator.');
define('MAIL_ERROR_SMTP', 'SMTP configuration error. Please contact the administrator.');
define('MAIL_ERROR_SEND', 'Failed to send email. Please try again later.');

define('MAIL_DEBUG', false);
define('MAIL_DEBUG_OUTPUT', 'html');

if (!MAIL_ENABLED) {
    error_log('Mail system is disabled in mail.php');
}

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    error_log('PHPMailer class not found. Run: composer install in project root.');
}
