<?php
/**
 * Unified Login System
 * Handles authentication for all user roles (Admin, Admin Support, Instructor, Moderator)
 */

// Include session configuration at the very beginning
require_once '../../config/session.php';
include '../../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

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

            // For instructors (User role), get instructor-specific data and multi-department support
            if ($role === 'User') {
                // Get instructor information
                $inst_stmt = $conn->prepare("
                    SELECT inst_id, inst_status, dept_id as inst_dept_id
                    FROM instructor 
                    WHERE inst_user = ?
                ");
                $inst_stmt->bind_param("s", $username);
                $inst_stmt->execute();
                $inst_result = $inst_stmt->get_result();
                
                if ($inst_result->num_rows === 1) {
                    $instructor = $inst_result->fetch_assoc();
                    
                    // Get all departments for this account from account_departments table
                    $departments = [];
                    $primaryDeptId = $dept_id; // Default to account.dept_id
                    $primaryDeptName = $dept_name;
                    
                    // Check if account_departments table exists
                    $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
                    $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
                    
                    if ($hasAccountDepartmentsTable) {
                        // Get all departments from account_departments
                        $deptStmt = $conn->prepare("
                            SELECT d.dept_id, d.dept_name 
                            FROM account_departments ad
                            JOIN department d ON ad.dept_id = d.dept_id
                            WHERE ad.acc_id = ?
                            ORDER BY d.dept_name
                        ");
                        $deptStmt->bind_param("i", $acc_id);
                        $deptStmt->execute();
                        $deptResult = $deptStmt->get_result();
                        
                        while ($deptRow = $deptResult->fetch_assoc()) {
                            $departments[] = [
                                'dept_id' => (int)$deptRow['dept_id'],
                                'dept_name' => $deptRow['dept_name']
                            ];
                        }
                        $deptStmt->close();
                        
                        // If we have departments from account_departments, use the first one as primary
                        if (!empty($departments)) {
                            $primaryDeptId = $departments[0]['dept_id'];
                            $primaryDeptName = $departments[0]['dept_name'];
                        } else if ($primaryDeptId && !$primaryDeptName) {
                            // Fallback: get department name from account.dept_id
                            $deptNameStmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
                            $deptNameStmt->bind_param("i", $primaryDeptId);
                            $deptNameStmt->execute();
                            $deptNameResult = $deptNameStmt->get_result();
                            if ($deptNameRow = $deptNameResult->fetch_assoc()) {
                                $primaryDeptName = $deptNameRow['dept_name'];
                                $departments[] = [
                                    'dept_id' => $primaryDeptId,
                                    'dept_name' => $primaryDeptName
                                ];
                            }
                            $deptNameStmt->close();
                        }
                    } else {
                        // Fallback: use account.dept_id if account_departments table doesn't exist
                        if ($primaryDeptId && !$primaryDeptName) {
                            $deptNameStmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
                            $deptNameStmt->bind_param("i", $primaryDeptId);
                            $deptNameStmt->execute();
                            $deptNameResult = $deptNameStmt->get_result();
                            if ($deptNameRow = $deptNameResult->fetch_assoc()) {
                                $primaryDeptName = $deptNameRow['dept_name'];
                                $departments[] = [
                                    'dept_id' => $primaryDeptId,
                                    'dept_name' => $primaryDeptName
                                ];
                            }
                            $deptNameStmt->close();
                        }
                    }
                    
                    // Store instructor-specific session data
                    $_SESSION['inst_id'] = $instructor['inst_id'];
                    $_SESSION['inst_status'] = $instructor['inst_status'];
                    $_SESSION['dept_id'] = $primaryDeptId; // Update to primary department
                    $_SESSION['dept_name'] = $primaryDeptName; // Update to primary department name
                    $_SESSION['departments'] = $departments; // All departments (for multi-department support)
                }
                $inst_stmt->close();
            }

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | IT Scheduling System</title>
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

    .login-card {
      max-width: 400px;
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
    }

    .btn-maroon:hover,
    .btn-maroon:focus {
      background-color: var(--evsu-maroon-dark);
      color: var(--evsu-white);
      transform: scale(1.05);
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

    .navbar .nav-link,
    .navbar .dropdown-item {
      color: var(--evsu-white) !important;
    }

    .navbar .dropdown-menu {
      background-color: var(--evsu-maroon);
      border: none;
    }

    .navbar .dropdown-item:hover,
    .navbar .dropdown-item:focus {
      background-color: var(--evsu-maroon-dark);
      color: var(--evsu-white);
    }

    @media (max-width: 575.98px) {
      .login-card {
        margin-top: 90px;
        padding: 1rem;
        width: 100%;
        max-width: calc(100% - 2rem);
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
            <a class="nav-link" href="../../public/index.php">Back to Home</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Login Form -->
  <div class="login-card">
    <div class="text-center mb-4">
      <img src="../../public/assets/img/evsu-logo.png" alt="EVSU Logo" width="64" height="64" class="mb-3">
      <h4 class="fw-bold text-maroon">Login</h4>
      <p class="text-muted">Sign in to your account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>
      <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-maroon w-100">Sign In</button>
    </form>

    <div class="text-center mt-3">
      <small class="text-muted">
        <a href="../../public/index.php" class="text-decoration-none">Back to Home</a>
      </small>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <span>&copy; 2024 Eastern Visayas State University - Ormoc Campus. All rights reserved.</span>
    </div>
  </footer>

  <script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>