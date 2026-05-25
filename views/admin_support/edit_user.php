<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

requirePermissionWithAudit('manage_users', '../../admin/auth/login_admin.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    $acc_id    = (int) $_POST['acc_id'];
    $fname     = sanitizeInput($_POST['fname'] ?? '');
    $lname     = sanitizeInput($_POST['lname'] ?? '');
    $minitial  = sanitizeInput($_POST['minitial'] ?? '');
    $acc_user  = sanitizeInput($_POST['acc_user'] ?? '');
    $acc_status= sanitizeInput($_POST['acc_status'] ?? 'Active');
    $role_id   = (int) ($_POST['role_id'] ?? 0);

    // 1. Validate role exists
    $check = $conn->prepare("SELECT id FROM roles WHERE id = ?");
    $check->bind_param("i", $role_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        die("Invalid role selected.");
    }
    $check->close();

    // 2. Update account details (without role_id)
    $stmt = $conn->prepare("
        UPDATE account 
        SET fname=?, lname=?, minitial=?, acc_user=?, acc_status=? 
        WHERE acc_id=?
    ");
    $stmt->bind_param("sssssi", 
        $fname, $lname, $minitial, $acc_user, $acc_status, $acc_id
    );

    if (!$stmt->execute()) {
        die("Error updating account: " . $stmt->error);
    }
    $stmt->close();

    // 3. Update role in user_roles (replace old one)
    // Remove existing roles
    $del = $conn->prepare("DELETE FROM user_roles WHERE acc_id=?");
    $del->bind_param("i", $acc_id);
    $del->execute();
    $del->close();

    // Insert new role
    $ins = $conn->prepare("INSERT INTO user_roles (acc_id, role_id) VALUES (?, ?)");
    $ins->bind_param("ii", $acc_id, $role_id);
    $ins->execute();
    $ins->close();

    // 4. Redirect back
    header("Location: index.php?tab=users&status=updated");
    exit;
}
?>
