<?php
session_start();
include '../../config/database.php';
require '../../vendor/autoload.php';
require '../../includes/database/send_email.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $fname      = $_POST['fname'];
    $lname      = $_POST['lname'];
    $minitial   = $_POST['minitial'];
    $acc_user   = $_POST['acc_user'];
    $default_pass = 'evsu-occ';
    $acc_pass   = password_hash($default_pass, PASSWORD_DEFAULT);
    $acc_email  = $_POST['acc_email'];
    $dept_id    = $_POST['dept_id'];
    $acc_role   = $_POST['acc_role'];
    $acc_status = 'Pending';

    // Generate a unique token
    $verify_token = bin2hex(random_bytes(16));

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO account 
        (fname, lname, minitial, acc_user, acc_pass, acc_email, dept_id, acc_role, acc_status, verification_token) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssisss",
        $fname, $lname, $minitial, $acc_user, $acc_pass, 
        $acc_email, $dept_id, $acc_role, $acc_status, $verify_token
    );
    $stmt->execute();
    $stmt->close();

    // ✅ IMPORTANT: adjust to your actual verify.php path
    $verify_link = "http://localhost/GitHub/EVSU-OCC-Scheduling-System/admin_support/verify.php?token=" . urlencode($verify_token);

    // Email content
    $subject = "Account Verification";
    $body = "
        Hello $fname,<br><br>
        Your account has been created. Please 
        <a href='$verify_link'>click here to verify your email</a> 
        and activate your account.<br><br>
        Thank you!
    ";

    sendEmail($acc_email, $subject, $body);

    header("Location: dashboard.php?tab=users&status=created");
    exit;
}
?>