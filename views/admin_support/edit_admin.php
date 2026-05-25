<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../config/database.php';

if (!isset($_SESSION['acc_id'])) {
    header("Location: user_management.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['acc_id'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $minitial = $_POST['minitial'];
    $acc_user = $_POST['acc_user'];
    $acc_role = $_POST['acc_role'];
    $dept_name = $_POST['dept_id'];
    $acc_status = $_POST['acc_status'];

    $stmt = $conn->prepare("UPDATE account 
        SET fname=?, lname=?, minitial=?, acc_user=?, acc_role=?, dept_id=?, acc_status=? 
        WHERE acc_id=?");
    $stmt->bind_param("sssssssi", $fname, $lname, $minitial, $acc_user, $acc_role, $dept_name, $acc_status, $id);
    $stmt->execute();
    $stmt->close();

header("Location: dashboard.php?tab=users&status=updated");
    exit;
}
?>
