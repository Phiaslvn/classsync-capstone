<?php
/**
 * OTP Verification Page
 * User interface for entering OTP code for account verification
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth/verification/otp_generator.php';

// Get email from query parameter or session
$email = $_GET['email'] ?? $_SESSION['verification_email'] ?? '';

// If no email provided, redirect to login
if (empty($email)) {
    header("Location: index.php?error=verification_required");
    exit;
}

// Store email in session for resend functionality
$_SESSION['verification_email'] = $email;

// Get account info
$user = getAccountByEmailForOTP($email);

if (!$user) {
    header("Location: index.php?error=account_not_found");
    exit;
}

// If already verified, redirect
if ($user['acc_status'] === 'Active') {
    header("Location: index.php?message=already_verified");
    exit;
}

$fname = $user['fname'];
$lname = $user['lname'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - EVSU-OCC Scheduling System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .otp-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        .otp-input:focus {
            border-color: #800000;
            outline: none;
        }
        .otp-display {
            font-size: 32px;
            font-weight: bold;
            color: #800000;
            text-align: center;
            letter-spacing: 5px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="otp-container">
            <div class="text-center mb-4">
                <img src="assets/img/evsu-logo.png" alt="EVSU Logo" width="60" height="60" class="mb-3">
                <h2 class="text-maroon fw-bold">Verify Your Account</h2>
                <p class="text-muted">Hello, <strong><?php echo htmlspecialchars($fname . ' ' . $lname); ?></strong></p>
                <p class="text-muted">We've sent a verification code to:</p>
                <p class="fw-semibold"><?php echo htmlspecialchars($email); ?></p>
            </div>

            <div id="alert-container"></div>

            <form id="otp-verification-form">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                
                <div class="mb-4">
                    <label for="otp" class="form-label fw-semibold">Enter Verification Code</label>
                    <input type="text" 
                           class="form-control form-control-lg text-center" 
                           id="otp" 
                           name="otp" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           placeholder="000000"
                           required
                           autocomplete="off"
                           style="font-size: 24px; letter-spacing: 5px; font-weight: bold;">
                    <small class="text-muted">Enter the 6-digit code sent to your email</small>
                </div>

                <button type="submit" class="btn btn-maroon w-100 btn-lg mb-3" id="verify-btn">
                    <i class="bi bi-check-circle me-2"></i>Verify Account
                </button>

                <div class="text-center">
                    <p class="text-muted mb-2">Didn't receive the code?</p>
                    <button type="button" class="btn btn-link text-maroon" id="resend-btn">
                        <i class="bi bi-arrow-clockwise me-1"></i>Resend Code
                    </button>
                </div>

                <div class="text-center mt-4">
                    <a href="index.php" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format OTP input (numbers only)
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Handle form submission
        document.getElementById('otp-verification-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = this;
            const verifyBtn = document.getElementById('verify-btn');
            const otpInput = document.getElementById('otp');
            const alertContainer = document.getElementById('alert-container');
            const otp = otpInput.value.trim();
            
            // Validate OTP format
            if (!/^\d{6}$/.test(otp)) {
                showAlert('Please enter a valid 6-digit code.', 'danger');
                return;
            }
            
            // Disable button and show loading
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
            
            try {
                const formData = new FormData(form);
                const response = await fetch('../includes/auth/verification/verify_otp.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.href = 'index.php?message=verification_success';
                        }
                    }, 2000);
                } else {
                    showAlert(data.message, 'danger');
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify Account';
                    otpInput.focus();
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify Account';
            }
        });
        
        // Handle resend OTP
        document.getElementById('resend-btn').addEventListener('click', async function(e) {
            e.preventDefault();
            const btn = this;
            const alertContainer = document.getElementById('alert-container');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            
            try {
                const response = await fetch('../includes/auth/verification/resend_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: '<?php echo htmlspecialchars($email); ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Resend Code';
            }
        });
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    </script>
</body>
</html>