<?php
/**
 * Forgot Password Page
 * Allows users to reset their password via email OTP
 */

// Include session and database configuration
require_once '../../config/session.php';
include '../../config/database.php';
require_once '../../config/mail.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'request'; // request, verify, reset

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_reset'])) {
        // Step 1: Request password reset
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Email address is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Check if email exists in database
            $stmt = $conn->prepare("SELECT acc_id, acc_email, fname, lname, acc_status FROM account WHERE acc_email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                // Check if account is active
                if ($user['acc_status'] !== 'Active') {
                    $error = 'Account is not active. Please contact your administrator.';
                } else {
                    // Generate OTP (6-digit code)
                    $otp = sprintf('%06d', mt_rand(100000, 999999));
                    
                    // Store OTP in session with expiration (10 minutes)
                    $_SESSION['forgot_password_otp'] = [
                        'code' => $otp,
                        'expires_at' => time() + 600, // 10 minutes
                        'email' => $email,
                        'acc_id' => $user['acc_id']
                    ];
                    
                    // Send email with OTP using PHPMailer
                    $subject = 'Password Reset Verification Code - EVSU-OCC System';
                    
                    // Create HTML email body
                    $htmlMessage = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background-color: #800000; color: white; padding: 20px; text-align: center; }
                            .content { background-color: #f9f9f9; padding: 30px; }
                            .otp-box { background-color: #fff; border: 2px solid #800000; padding: 20px; text-align: center; margin: 20px 0; }
                            .otp-code { font-size: 32px; font-weight: bold; color: #800000; letter-spacing: 5px; }
                            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>EVSU-OCC Scheduling System</h2>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>" . htmlspecialchars($user['fname'] . ' ' . $user['lname']) . "</strong>,</p>
                                <p>You have requested to reset your password for the EVSU-OCC Scheduling System.</p>
                                <p>Please use the verification code below to proceed:</p>
                                <div class='otp-box'>
                                    <div class='otp-code'>{$otp}</div>
                                </div>
                                <p><strong>This code will expire in 10 minutes.</strong></p>
                                <p>If you did not request this password reset, please ignore this email or contact the administrator.</p>
                            </div>
                            <div class='footer'>
                                <p>Best regards,<br>EVSU-OCC Scheduling System Team</p>
                                <p>&copy; " . date('Y') . " Eastern Visayas State University - Ormoc Campus</p>
                            </div>
                        </div>
                    </body>
                    </html>";
                    
                    // Plain text version
                    $textMessage = "Hello " . $user['fname'] . ' ' . $user['lname'] . ",\n\n" .
                                  "You have requested to reset your password. Your verification code is: {$otp}\n\n" .
                                  "This code will expire in 10 minutes.\n\n" .
                                  "If you did not request this password reset, please ignore this email.\n\n" .
                                  "Best regards,\nEVSU-OCC Scheduling System";
                    
                    // Check if mail is enabled
                    if (!MAIL_ENABLED) {
                        $error = MAIL_ERROR_DISABLED;
                    } else {
                        // Send email using PHPMailer
                        $mail = new PHPMailer(true);
                        
                        try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = MAIL_SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = MAIL_SMTP_USERNAME;
                        $mail->Password = MAIL_SMTP_PASSWORD;
                        $mail->SMTPSecure = MAIL_SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port = MAIL_SMTP_PORT;
                        $mail->CharSet = 'UTF-8';
                        
                        // Enable debug if needed (for troubleshooting)
                        if (MAIL_DEBUG) {
                            $mail->SMTPDebug = 2;
                            $mail->Debugoutput = MAIL_DEBUG_OUTPUT;
                        }
                        
                        // Recipients
                        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                        $mail->addAddress($email, $user['fname'] . ' ' . $user['lname']);
                        if (defined('MAIL_REPLY_TO') && MAIL_REPLY_TO) {
                            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_REPLY_TO_NAME ?? 'EVSU-OCC Support');
                        }
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $htmlMessage;
                        $mail->AltBody = $textMessage;
                        
                            $mail->send();
                            
                            $success = 'Verification code sent to your email address. Please check your inbox.';
                            $step = 'verify';
                        } catch (Exception $e) {
                            // Log error for debugging
                            error_log("Email sending failed: {$mail->ErrorInfo}");
                            
                            // Show user-friendly error
                            $error = 'Failed to send email. Please try again later or contact the administrator.';
                            // Don't proceed to verify step if email failed
                        }
                    }
                }
            } else {
                // Don't reveal if email exists for security (don't send email)
                $success = 'If the email exists in our system, a verification code has been sent. Please check your inbox.';
                $step = 'verify';
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        // Step 2: Verify OTP
        $otp_code = trim($_POST['otp_code'] ?? '');
        
        if (empty($otp_code)) {
            $error = 'Verification code is required';
        } elseif (!isset($_SESSION['forgot_password_otp'])) {
            $error = 'No verification code found. Please request a new one.';
            $step = 'request';
        } else {
            $otp_data = $_SESSION['forgot_password_otp'];
            
            // Check expiration
            if (time() > $otp_data['expires_at']) {
                unset($_SESSION['forgot_password_otp']);
                $error = 'Verification code has expired. Please request a new one.';
                $step = 'request';
            } elseif ($otp_code !== $otp_data['code']) {
                $error = 'Invalid verification code';
            } else {
                // OTP verified, proceed to reset password
                $step = 'reset';
                $success = 'Verification code verified. Please enter your new password.';
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Step 3: Reset password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirm password do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif (!isset($_SESSION['forgot_password_otp'])) {
            $error = 'Session expired. Please start over.';
            $step = 'request';
        } else {
            $otp_data = $_SESSION['forgot_password_otp'];
            $acc_id = $otp_data['acc_id'];
            
            // Check if new password is different from current password
            $stmt = $conn->prepare("SELECT acc_pass FROM account WHERE acc_id = ?");
            $stmt->bind_param("i", $acc_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user && password_verify($new_password, $user['acc_pass'])) {
                $error = 'New password must be different from your current password';
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE account SET acc_pass = ? WHERE acc_id = ?");
                $stmt->bind_param("si", $hashed_password, $acc_id);
                
                if ($stmt->execute()) {
                    // Clear OTP session
                    unset($_SESSION['forgot_password_otp']);
                    // Redirect to login page after successful password reset
                    header("Location: login_admin.php?reset=success");
                    exit();
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | IT Scheduling System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../../public/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../public/assets/css/main.css">
  <style>
    :root {
      /* EVSU Brand Colors */
      --evsu-maroon: #800000;
      --evsu-maroon-dark: #660000;
      --evsu-maroon-light: #a00000;
      --evsu-beige: #f5f5dc;
      --evsu-beige-dark: #e6e6d1;
      --evsu-white: #ffffff;
      --evsu-gray: #f8f9fa;
      
      /* Typography */
      --font-family: 'Poppins', Arial, sans-serif;
      --font-size-sm: 0.875rem;
      --font-size-base: 1rem;
      --font-size-lg: 1.125rem;
      --font-size-xl: 1.25rem;
      --font-weight-normal: 400;
      --font-weight-semibold: 600;
      --font-weight-bold: 700;
    }

    body {
      font-family: var(--font-family);
      background: url('../../public/assets/img/image.png') no-repeat center center/cover;
      background-color: var(--evsu-gray);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 1rem;
      margin: 0;
    }

    .forgot-password-card {
      max-width: 450px;
      width: 100%;
      margin: 100px auto 0 auto;
      background: var(--evsu-white);
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(128,0,0,0.08);
      padding: 2rem;
    }

    .form-label {
      font-weight: var(--font-weight-semibold);
      color: var(--evsu-maroon);
    }

    .btn-maroon {
      background-color: var(--evsu-maroon);
      color: var(--evsu-white);
      border-radius: 50px;
      font-weight: var(--font-weight-semibold);
      transition: background 0.3s, transform 0.2s;
      border: none;
    }

    .btn-maroon:hover,
    .btn-maroon:focus {
      background-color: var(--evsu-maroon-dark);
      color: var(--evsu-white);
      transform: scale(1.05);
    }

    .btn-outline-maroon {
      border: 2px solid var(--evsu-maroon);
      color: var(--evsu-maroon);
      background-color: transparent;
      border-radius: 50px;
      font-weight: var(--font-weight-semibold);
      transition: all 0.3s;
    }

    .btn-outline-maroon:hover {
      background-color: var(--evsu-maroon);
      color: var(--evsu-white);
    }

    .footer {
      background-color: var(--evsu-maroon);
      color: var(--evsu-white);
      text-align: center;
      padding: 1rem 0;
      width: 100%;
      position: fixed;
      left: 0;
      bottom: 0;
      z-index: 1030;
      font-size: var(--font-size-base);
      letter-spacing: 0.5px;
    }

    .navbar {
      background-color: var(--evsu-maroon) !important;
    }

    .navbar .navbar-brand {
      color: var(--evsu-white) !important;
      font-weight: var(--font-weight-bold);
    }

    .navbar .nav-link {
      color: var(--evsu-white) !important;
    }

    .step-indicator {
      display: flex;
      justify-content: space-between;
      margin-bottom: 2rem;
      position: relative;
    }

    .step-indicator::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 0;
      right: 0;
      height: 2px;
      background: #e0e0e0;
      z-index: 0;
    }

    .step {
      flex: 1;
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e0e0e0;
      color: #999;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 0.5rem;
      font-weight: var(--font-weight-bold);
    }

    .step.active .step-circle {
      background: var(--evsu-maroon);
      color: var(--evsu-white);
    }

    .step.completed .step-circle {
      background: #28a745;
      color: var(--evsu-white);
    }

    .step-label {
      font-size: var(--font-size-sm);
      color: #666;
    }

    .step.active .step-label {
      color: var(--evsu-maroon);
      font-weight: var(--font-weight-semibold);
    }

    @media (max-width: 575.98px) {
      .forgot-password-card {
        margin-top: 90px;
        padding: 1rem;
      }
      .footer {
        font-size: 0.9rem;
        padding: 0.5rem 0;
      }
    }
  </style>
</head>
<body>
  <!-- Fixed Top Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="../../public/index.php">
        <img src="../../public/assets/img/evsu-logo.png" alt="EVSU Logo" width="32" height="32" class="me-2">
        <span>IT Scheduling System</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="login_admin.php">Back to Login</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Forgot Password Form -->
  <div class="forgot-password-card">
    <div class="text-center mb-4">
      <img src="../../public/assets/img/evsu-logo.png" alt="EVSU Logo" width="64" height="64" class="mb-3">
      <h4 class="fw-bold text-maroon">Forgot Password</h4>
      <p class="text-muted">Reset your account password</p>
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
      <div class="step <?php echo in_array($step, ['request', 'verify', 'reset', 'success']) ? 'completed' : ''; ?> <?php echo $step === 'request' ? 'active' : ''; ?>">
        <div class="step-circle">1</div>
        <div class="step-label">Request</div>
      </div>
      <div class="step <?php echo in_array($step, ['verify', 'reset', 'success']) ? 'completed' : ''; ?> <?php echo $step === 'verify' ? 'active' : ''; ?>">
        <div class="step-circle">2</div>
        <div class="step-label">Verify</div>
      </div>
      <div class="step <?php echo in_array($step, ['reset', 'success']) ? 'completed' : ''; ?> <?php echo $step === 'reset' ? 'active' : ''; ?>">
        <div class="step-circle">3</div>
        <div class="step-label">Reset</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success" role="alert">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <!-- Step 1: Request Reset -->
    <?php if ($step === 'request'): ?>
      <form method="POST" action="">
        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" class="form-control" id="email" name="email" required 
                 placeholder="Enter your registered email address">
        </div>
        <button type="submit" name="request_reset" class="btn btn-maroon w-100">Send Verification Code</button>
      </form>
      <div class="text-center mt-3">
        <small class="text-muted">
          <a href="login_admin.php" class="text-decoration-none">Back to Login</a>
        </small>
      </div>
    <?php endif; ?>

    <!-- Step 2: Verify OTP -->
    <?php if ($step === 'verify'): ?>
      <form method="POST" action="">
        <div class="mb-3">
          <label for="otp_code" class="form-label">Verification Code</label>
          <input type="text" class="form-control" id="otp_code" name="otp_code" required 
                 placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}">
          <small class="form-text text-muted">Check your email for the verification code</small>
        </div>
        <button type="submit" name="verify_otp" class="btn btn-maroon w-100">Verify Code</button>
      </form>
      <div class="text-center mt-3">
        <small class="text-muted">
          <a href="?step=request" class="text-decoration-none">Request new code</a> | 
          <a href="login_admin.php" class="text-decoration-none">Back to Login</a>
        </small>
      </div>
    <?php endif; ?>

    <!-- Step 3: Reset Password -->
    <?php if ($step === 'reset'): ?>
      <form method="POST" action="">
        <div class="mb-3">
          <label for="new_password" class="form-label">New Password</label>
          <input type="password" class="form-control" id="new_password" name="new_password" required 
                 placeholder="Enter new password" minlength="6">
          <small class="form-text text-muted">Password must be at least 6 characters long</small>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                 placeholder="Confirm new password" minlength="6">
        </div>
        <button type="submit" name="reset_password" class="btn btn-maroon w-100">Reset Password</button>
      </form>
      <div class="text-center mt-3">
        <small class="text-muted">
          <a href="login_admin.php" class="text-decoration-none">Back to Login</a>
        </small>
      </div>
    <?php endif; ?>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <span>&copy; 2024 Eastern Visayas State University - Ormoc Campus. All rights reserved.</span>
    </div>
  </footer>

  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-format OTP input
    const otpInput = document.getElementById('otp_code');
    if (otpInput) {
      otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
      });
    }

    // Password confirmation validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    const newPasswordInput = document.getElementById('new_password');
    if (confirmPasswordInput && newPasswordInput) {
      confirmPasswordInput.addEventListener('input', function() {
        if (this.value !== newPasswordInput.value) {
          this.setCustomValidity('Passwords do not match');
        } else {
          this.setCustomValidity('');
        }
      });
    }
  </script>
</body>
</html>

