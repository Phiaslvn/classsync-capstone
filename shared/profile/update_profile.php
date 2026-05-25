<?php
/**
 * Unified Profile Update Script
 * Works for all user types (Admin, Admin Support, Instructor, Moderator)
 * Updates profile fields and optional password (same rules as views/admin/update_profile.php)
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userInfo = getUserInfo();
if (!$userInfo) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User information not found']);
    exit;
}

$acc_id = $userInfo['acc_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $minitial = trim($_POST['minitial'] ?? '');
    $acc_user = trim($_POST['acc_user'] ?? '');
    $acc_email = trim($_POST['acc_email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = $_POST['confirm_password'] ?? '';

    $hasProfileData = $fname !== '' || $lname !== '' || $acc_user !== '' || $acc_email !== '';
    $hasPasswordChange = $new_password !== '';

    if (!$hasProfileData && !$hasPasswordChange) {
        echo json_encode(['success' => false, 'message' => 'At least one field must be provided for update']);
        exit;
    }

    // Validate email format only if email is provided
    if ($acc_email !== '' && !filter_var($acc_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Password change: validate and verify current password before building UPDATE
    if ($hasPasswordChange) {
        if ($current_password === '') {
            echo json_encode(['success' => false, 'message' => 'Current password is required to change password']);
            exit;
        }
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
            exit;
        }
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
            exit;
        }

        $stmt = $conn->prepare('SELECT acc_pass FROM account WHERE acc_id = ?');
        $stmt->bind_param('i', $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current_password, $row['acc_pass'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
    }

    // Check if username or email already exists for another user (only if they're being updated)
    if ($acc_user !== '' || $acc_email !== '') {
        $stmt = $conn->prepare('SELECT acc_id FROM account WHERE (acc_user = ? OR acc_email = ?) AND acc_id != ?');
        $stmt->bind_param('ssi', $acc_user, $acc_email, $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username or email already taken by another account']);
            exit;
        }
        $stmt->close();
    }

    // Build dynamic update query based on provided fields
    $updateFields = [];
    $updateValues = [];
    $updateTypes = '';

    if ($fname !== '') {
        $updateFields[] = 'fname = ?';
        $updateValues[] = $fname;
        $updateTypes .= 's';
    }

    if ($lname !== '') {
        $updateFields[] = 'lname = ?';
        $updateValues[] = $lname;
        $updateTypes .= 's';
    }

    if (!empty($minitial)) {
        $updateFields[] = 'minitial = ?';
        $updateValues[] = $minitial;
        $updateTypes .= 's';
    }

    if ($acc_user !== '') {
        $updateFields[] = 'acc_user = ?';
        $updateValues[] = $acc_user;
        $updateTypes .= 's';
    }

    if ($acc_email !== '') {
        $updateFields[] = 'acc_email = ?';
        $updateValues[] = $acc_email;
        $updateTypes .= 's';
    }

    if ($hasPasswordChange) {
        $updateFields[] = 'acc_pass = ?';
        $updateValues[] = password_hash($new_password, PASSWORD_DEFAULT);
        $updateTypes .= 's';
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'Nothing to update']);
        exit;
    }

    $updateValues[] = $acc_id;
    $updateTypes .= 'i';

    $updateQuery = 'UPDATE account SET ' . implode(', ', $updateFields) . ' WHERE acc_id = ?';
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($updateTypes, ...$updateValues);

    if ($stmt->execute()) {
        // Session updates for fields we changed
        if ($fname !== '') {
            $_SESSION['fname'] = $fname;
            if (!isset($_SESSION['user_info'])) {
                $_SESSION['user_info'] = [];
            }
            $_SESSION['user_info']['fname'] = $fname;
        }
        if ($lname !== '') {
            $_SESSION['lname'] = $lname;
            if (!isset($_SESSION['user_info'])) {
                $_SESSION['user_info'] = [];
            }
            $_SESSION['user_info']['lname'] = $lname;
        }
        if (!empty($minitial)) {
            $_SESSION['minitial'] = $minitial;
            if (!isset($_SESSION['user_info'])) {
                $_SESSION['user_info'] = [];
            }
            $_SESSION['user_info']['minitial'] = $minitial;
        }
        if ($acc_user !== '') {
            $_SESSION['acc_user'] = $acc_user;
            if (!isset($_SESSION['user_info'])) {
                $_SESSION['user_info'] = [];
            }
            $_SESSION['user_info']['acc_user'] = $acc_user;
        }
        if ($acc_email !== '') {
            $_SESSION['acc_email'] = $acc_email;
            if (!isset($_SESSION['user_info'])) {
                $_SESSION['user_info'] = [];
            }
            $_SESSION['user_info']['acc_email'] = $acc_email;
        }

        $msg = $hasPasswordChange
            ? 'Profile and password updated successfully'
            : 'Profile updated successfully';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
