<?php
// Include session configuration at the very beginning
require_once '../../config/session.php';
include '../../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // ✅ Ensure database connection is available before preparing the statement
    if (!$conn || !empty($db_connection_error)) {
        // Gracefully handle DB connection issues instead of fatal errors
        $error = "Unable to connect to the database. Please try again later.";
    } else {
        // ✅ Fetch user + role + department in one query (using user_roles table)
        $stmt = $conn->prepare("
            SELECT a.acc_id, a.acc_pass, a.acc_status, a.dept_id, a.acc_email, ur.role_id, d.dept_name, r.role_name
            FROM account a
            JOIN user_roles ur ON a.acc_id = ur.acc_id
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN department d ON a.dept_id = d.dept_id
            WHERE a.acc_user = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $acc_id = $row['acc_id'];
            $hashed_password = $row['acc_pass'];
            $acc_status = $row['acc_status'];
            $role = $row['role_name'];
            $role_id = $row['role_id'];
            $dept_id = $row['dept_id'];
            $dept_name = $row['dept_name'];
            $acc_email = $row['acc_email'];

            if ($acc_status === 'Pending') {
                // Redirect to OTP verification page
                header("Location: ../../public/verify-otp.php?email=" . urlencode($acc_email));
                exit();
            } elseif ($acc_status === 'Inactive') {
                $error = "Account is inactive. Please contact your administrator.";
            } elseif ($acc_status === 'Deleted') {
                $error = "Account has been deleted.";
            } elseif (password_verify($password, $hashed_password)) {
                // ✅ Secure session
                session_regenerate_id(true);

                $_SESSION['acc_id'] = $acc_id;
                $_SESSION['acc_user'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['role_id'] = $role_id;
                $_SESSION['dept_id'] = $dept_id;
                $_SESSION['dept_name'] = $dept_name;

                // ✅ Redirect based on role
                if ($role === 'Admin support') {
                    header("Location: ../admin_support/index.php");
                } elseif ($role === 'Admin') {
                    header("Location: ../admin/dashboard.php");
                } elseif ($role === 'Moderator') {
                    header("Location: ../moderator/dashboard.php");
                } elseif ($role === 'User') {
                    header("Location: ../instructor/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | IT Scheduling System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
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
      --font-weight-medium: 500;
      --font-weight-semibold: 600;
      --font-weight-bold: 700;
      
      /* Spacing */
      --spacing-xs: 0.25rem;
      --spacing-sm: 0.5rem;
      --spacing-md: 1rem;
      --spacing-lg: 1.5rem;
      --spacing-xl: 2rem;
      --spacing-2xl: 3rem;
      
      /* Border Radius */
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --radius-xl: 20px;
      
      /* Shadows */
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
      --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.16);
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: var(--font-family);
      background: linear-gradient(135deg, var(--evsu-maroon) 0%, var(--evsu-maroon-dark) 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: var(--spacing-md);
      margin: 0;
      position: relative;
    }

    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('../../public/assets/img/image.png') no-repeat center center/cover;
      opacity: 0.6;
      z-index: 1;
    }

    .login-card {
      max-width: 420px;
      width: 100%;
      background: var(--evsu-white);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-lg);
      padding: var(--spacing-2xl);
      position: relative;
      z-index: 2;
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
    }

    .login-header {
      text-align: center;
      margin-bottom: var(--spacing-2xl);
    }

    .login-header h2 {
      color: var(--evsu-maroon);
      font-weight: var(--font-weight-bold);
      font-size: 1.75rem;
      margin-bottom: var(--spacing-sm);
    }

    .login-header p {
      color: #6c757d;
      font-size: var(--font-size-sm);
      margin: 0;
    }

    .form-group {
      margin-bottom: var(--spacing-lg);
    }

    .form-label {
      font-weight: var(--font-weight-semibold);
      color: var(--evsu-maroon);
      font-size: var(--font-size-sm);
      margin-bottom: var(--spacing-sm);
      display: block;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid #e9ecef;
      border-radius: var(--radius-md);
      font-size: var(--font-size-base);
      transition: all 0.3s ease;
      background: var(--evsu-white);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--evsu-maroon);
      box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.15);
    }

    .btn-maroon {
      background: linear-gradient(135deg, var(--evsu-maroon) 0%, var(--evsu-maroon-dark) 100%);
      color: var(--evsu-white);
      border: none;
      border-radius: var(--radius-md);
      padding: 0.875rem 2rem;
      font-weight: var(--font-weight-semibold);
      font-size: var(--font-size-base);
      width: 100%;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-maroon::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .btn-maroon:hover::before {
      left: 100%;
    }

    .btn-maroon:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-maroon:focus {
      outline: none;
      box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
    }

    .forgot-password {
      text-align: center;
      margin-top: var(--spacing-lg);
    }

    .forgot-password a {
      color: var(--evsu-maroon);
      text-decoration: none;
      font-weight: var(--font-weight-medium);
      font-size: var(--font-size-sm);
      transition: color 0.3s ease;
    }

    .forgot-password a:hover {
      color: var(--evsu-maroon-dark);
      text-decoration: underline;
    }

    .navbar {
      background: var(--evsu-maroon) !important;
      box-shadow: var(--shadow-sm);
    }

    .navbar .navbar-brand {
      color: var(--evsu-white) !important;
      font-weight: var(--font-weight-bold);
      font-size: var(--font-size-lg);
    }

    .navbar .nav-link {
      color: var(--evsu-white) !important;
      font-weight: var(--font-weight-medium);
      transition: color 0.3s ease;
    }

    .navbar .nav-link:hover {
      color: var(--evsu-beige) !important;
    }

    .btn-outline-maroon {
      border: 2px solid var(--evsu-white);
      color: var(--evsu-white);
      background: transparent;
      border-radius: var(--radius-md);
      padding: 0.5rem 1rem;
      font-weight: var(--font-weight-medium);
      transition: all 0.3s ease;
    }

    .btn-outline-maroon:hover {
      background: var(--evsu-white);
      color: var(--evsu-maroon);
    }

    .footer {
      background: var(--evsu-maroon);
      color: var(--evsu-white);
      text-align: center;
      padding: var(--spacing-md) 0;
      width: 100%;
      position: fixed;
      left: 0;
      bottom: 0;
      z-index: 1030;
      font-size: var(--font-size-sm);
      font-weight: var(--font-weight-medium);
    }

    .alert {
      border-radius: var(--radius-md);
      border: none;
      padding: 0.875rem 1rem;
      margin-bottom: var(--spacing-lg);
      font-size: var(--font-size-sm);
    }

    .alert-danger {
      background: rgba(220, 53, 69, 0.1);
      color: #721c24;
      border-left: 4px solid #dc3545;
    }

    @media (max-width: 575.98px) {
      .login-card {
        margin-top: 80px;
        padding: var(--spacing-lg);
      }
      
      .footer {
        font-size: 0.8rem;
        padding: var(--spacing-sm) 0;
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
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item">
            <a href="../../public/index.php" class="btn btn-outline-maroon d-flex align-items-center gap-2">
              <span class="bi bi-arrow-left"></span> Back
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Login Form Section -->
  <div class="login-card">
    <div class="login-header">
      <h2>Staff Login</h2>
      <p>Authorized personnel only</p>
    </div>
    
    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form action="" method="POST">
      <div class="form-group">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required autocomplete="username" placeholder="Enter your username">
      </div>
      
      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
      </div>
      
      <button type="submit" class="btn btn-maroon">Sign In</button>
      
      <div class="forgot-password">
        <a href="forgot_password.php">Forgot your password?</a>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p class="mb-0">&copy; 2025 IT Scheduling System. All rights reserved.</p>
    </div>
  </footer>

  <script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>