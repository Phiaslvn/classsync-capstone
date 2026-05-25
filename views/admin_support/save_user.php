<?php
// Include unified security middleware
require_once '../../includes/auth/security_middleware.php';
require '../../vendor/autoload.php'; // PHPMailer
require '../../includes/database/send_email.php'; // Make sure sendEmail() is defined

// Ensure user has permission to manage users (Admin Support area)
requirePermissionWithAudit('manage_users', '../../index.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("CSRF token mismatch");
    }
    
    // Collect and sanitize data
    $lname     = sanitizeInput($_POST['lname'] ?? '');
    $fname     = sanitizeInput($_POST['fname'] ?? '');
    $minitial  = sanitizeInput($_POST['minitial'] ?? '');
    $acc_user  = sanitizeInput($_POST['acc_user'] ?? '');
    $acc_email = sanitizeInput($_POST['acc_email'] ?? '');
    $dept_id   = intval($_POST['dept_id'] ?? 0); // for Admin modal
    $role_id   = intval($_POST['role_id'] ?? 0); // passed hidden from modal

    // Default password
    $default_pass = 'evsu-occ';
    $hashed_pass = password_hash($default_pass, PASSWORD_DEFAULT);

    $acc_status = 'Pending';

    // Validation
    $required_fields = ['lname', 'fname', 'acc_user', 'acc_email'];
    $errors = validateRequired($_POST, $required_fields);
    
    if (!empty($errors) || $role_id === 0) {
        header("Location: index.php?tab=users&status=error");
        exit;
    }
    
    // Validate email format
    if (!validateEmail($acc_email)) {
        header("Location: index.php?tab=users&status=error");
        exit;
    }
    
    // Check for duplicate username
    $username_check = $conn->prepare("SELECT acc_id FROM account WHERE acc_user = ?");
    $username_check->bind_param("s", $acc_user);
    $username_check->execute();
    $username_result = $username_check->get_result();
    if ($username_result->num_rows > 0) {
        $username_check->close();
        error_log("Save User Error: Duplicate username '$acc_user' provided");
        header("Location: index.php?tab=users&status=error&message=duplicate_username");
        exit;
    }
    $username_check->close();
    
    // Check for duplicate email
    $email_check = $conn->prepare("SELECT acc_id FROM account WHERE acc_email = ?");
    $email_check->bind_param("s", $acc_email);
    $email_check->execute();
    $email_result = $email_check->get_result();
    if ($email_result->num_rows > 0) {
        $email_check->close();
        error_log("Save User Error: Duplicate email '$acc_email' provided");
        header("Location: index.php?tab=users&status=error&message=duplicate_email");
        exit;
    }
    $email_check->close();
    
    // Validate department ID exists (if provided)
    if ($dept_id > 0) {
        $dept_check = $conn->prepare("SELECT dept_id FROM department WHERE dept_id = ?");
        $dept_check->bind_param("i", $dept_id);
        $dept_check->execute();
        $dept_result = $dept_check->get_result();
        if ($dept_result->num_rows === 0) {
            $dept_check->close();
            error_log("Save User Error: Invalid department ID $dept_id provided");
            header("Location: index.php?tab=users&status=error&message=invalid_department");
            exit;
        }
        $dept_check->close();
    } else {
        // If dept_id is 0 or null, set it to NULL for the database
        $dept_id = null;
    }

    // Insert into account (no verification_token needed for OTP system)
    $stmt = $conn->prepare("
        INSERT INTO account 
        (lname, fname, minitial, acc_user, acc_pass, acc_email, dept_id, acc_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssssis", 
        $lname, $fname, $minitial, $acc_user, $hashed_pass, $acc_email, $dept_id, $acc_status
    );

    if ($stmt->execute()) {
        $newAccId = $stmt->insert_id;
        $stmt->close();

        // 🔹 Insert into user_roles
        $stmtRole = $conn->prepare("INSERT INTO user_roles (acc_id, role_id) VALUES (?, ?)");
        $stmtRole->bind_param("ii", $newAccId, $role_id);
        $stmtRole->execute();
        $stmtRole->close();
        
        // 🔹 Insert workload hours into instructor table if provided
        $rank = sanitizeInput($_POST['rank'] ?? '');
        $designation = sanitizeInput($_POST['designation'] ?? 'None');
        $inst_status = sanitizeInput($_POST['inst_status'] ?? 'Active');
        $administration_hours = intval($_POST['administration_hours'] ?? 0);
        $instruction_hours = intval($_POST['instruction_hours'] ?? 0);
        $research_hours = intval($_POST['research_hours'] ?? 0);
        $extension_hours = intval($_POST['extension_hours'] ?? 0);
        $instructional_functions_hours = intval($_POST['instructional_functions_hours'] ?? 0);
        $consultation_hours = intval($_POST['consultation_hours'] ?? 0);
        
        // Insert instructor record with workload hours
        $insert_inst_stmt = $conn->prepare("
            INSERT INTO instructor (inst_user, inst_fname, inst_lname, inst_mname, rank, designation,
                administration_hours, instruction_hours, research_hours, extension_hours,
                instructional_functions_hours, consultation_hours, inst_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert_inst_stmt->bind_param("ssssssiiiiiis",
            $acc_user, $fname, $lname, $minitial,
            $rank, $designation,
            $administration_hours, $instruction_hours,
            $research_hours, $extension_hours,
            $instructional_functions_hours, $consultation_hours,
            $inst_status
        );
        $insert_inst_stmt->execute();
        $insert_inst_stmt->close();
        
        // Log the user creation action
        $role_name = '';
        $role_stmt = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
        $role_stmt->bind_param("i", $role_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role_name = $role_result->fetch_assoc()['role_name'] ?? 'Unknown';
        $role_stmt->close();
        
        logAdminAction($_SESSION['acc_id'], 'create_user', "Created new $role_name account: $fname $lname ($acc_user)");

        // 🔹 Send verification OTP email
        require_once '../../includes/auth/verification/send_otp_email.php';
        
        // Get department name for email
        $dept_name = 'Unknown Department';
        if ($dept_id) {
            $dept_stmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
            $dept_stmt->bind_param("i", $dept_id);
            $dept_stmt->execute();
            $dept_result = $dept_stmt->get_result();
            $dept_data = $dept_result->fetch_assoc();
            $dept_name = $dept_data['dept_name'] ?? 'Unknown Department';
            $dept_stmt->close();
        }
        
        // Get role name
        $role_stmt = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
        $role_stmt->bind_param("i", $role_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role_name = $role_result->fetch_assoc()['role_name'] ?? 'User';
        $role_stmt->close();
        
        // Send OTP email with username and password
        sendVerificationOTPEmail($newAccId, $acc_email, $fname, $lname, $dept_name, $role_name, $acc_user, $default_pass);

        $conn->close();
        header("Location: index.php?tab=users&status=created");
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header("Location: index.php?tab=users&status=error");
        exit;
    }
} else {
    header("Location: index.php?tab=users&status=invalid");
    exit;
}
?>