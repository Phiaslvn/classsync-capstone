<?php
/**
 * Instructor Login Processing
 * Handles instructor authentication with email verification checking
 */

session_start();
require_once '../../config/database.php';

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $errorMessage = 'Please enter both username and password.';
    } else {
        // Check if user exists and get account details
        $stmt = $conn->prepare("
            SELECT a.acc_id, a.acc_user, a.acc_pass, a.acc_status, a.fname, a.lname, a.minitial, 
                   a.acc_email, a.dept_id, r.role_name
            FROM account a
            LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE a.acc_user = ? AND a.acc_status != 'Deleted'
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['acc_pass'])) {
                // Check if account is verified (not pending)
                if ($user['acc_status'] === 'Pending') {
                    // Redirect to OTP verification page
                    header("Location: ../../public/verify-otp.php?email=" . urlencode($user['acc_email']));
                    exit();
                } else {
                    // Check if user has any valid role (instructors are typically assigned 'User' role)
                    if (in_array($user['role_name'], ['User', 'Moderator', 'Admin', 'Admin support'])) {
                        // Get instructor information - FIX: Use inst_user instead of acc_id
                        // The instructor table links via inst_user = account.acc_user, not acc_id
                        $inst_stmt = $conn->prepare("
                            SELECT inst_id, inst_status, dept_id as inst_dept_id
                            FROM instructor 
                            WHERE inst_user = ?
                        ");
                        $inst_stmt->bind_param("s", $user['acc_user']);
                        $inst_stmt->execute();
                        $inst_result = $inst_stmt->get_result();
                        
                        if ($inst_result->num_rows === 1) {
                            $instructor = $inst_result->fetch_assoc();
                            
                            // Check if instructor status is also verified (not pending)
                            if ($instructor['inst_status'] === 'Pending') {
                                // Redirect to OTP verification page
                                header("Location: ../../public/verify-otp.php?email=" . urlencode($user['acc_email']));
                                exit();
                            } else {
                                // Get all departments for this account from account_departments table
                                $departments = [];
                                $primaryDeptId = $user['dept_id']; // Default to account.dept_id
                                $primaryDeptName = null;
                                
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
                                    $deptStmt->bind_param("i", $user['acc_id']);
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
                                    // Otherwise, fall back to account.dept_id
                                    if (!empty($departments)) {
                                        $primaryDeptId = $departments[0]['dept_id'];
                                        $primaryDeptName = $departments[0]['dept_name'];
                                    } else if ($primaryDeptId) {
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
                                    if ($primaryDeptId) {
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
                                
                                // Login successful - set session data
                                $_SESSION['acc_id'] = $user['acc_id'];
                                $_SESSION['acc_user'] = $user['acc_user'];
                                $_SESSION['fname'] = $user['fname'];
                                $_SESSION['lname'] = $user['lname'];
                                $_SESSION['minitial'] = $user['minitial'];
                                $_SESSION['acc_email'] = $user['acc_email'];
                                $_SESSION['dept_id'] = $primaryDeptId; // Primary department
                                $_SESSION['dept_name'] = $primaryDeptName; // Primary department name
                                $_SESSION['departments'] = $departments; // All departments (for multi-department support)
                                $_SESSION['role'] = $user['role_name'];
                                $_SESSION['inst_id'] = $instructor['inst_id']; // FIX: Use actual inst_id, not acc_id
                                $_SESSION['inst_status'] = $instructor['inst_status'];
                                $_SESSION['login_time'] = time();
                                
                                // Log successful login
                                error_log("Instructor login successful: " . $username . " (ID: " . $user['acc_id'] . ", Inst ID: " . $instructor['inst_id'] . ", Departments: " . count($departments) . ")");
                                
                                // Redirect to instructor dashboard
                                header("Location: ../views/instructor/dashboard.php");
                                exit();
                            }
                        } else {
                            $errorMessage = 'Instructor record not found. Please contact the administrator.';
                        }
                        $inst_stmt->close();
                    } else {
                        $errorMessage = 'You do not have permission to access the instructor portal.';
                    }
                }
            } else {
                $errorMessage = 'Invalid username or password.';
            }
        } else {
            $errorMessage = 'Invalid username or password.';
        }
        $stmt->close();
    }
}

// If we reach here, there was an error or no POST data
// Redirect back to login page with error message
$redirect_url = "login_instructor.php";
if (!empty($errorMessage)) {
    $redirect_url .= "?error=" . urlencode($errorMessage);
}
header("Location: " . $redirect_url);
exit();
?>