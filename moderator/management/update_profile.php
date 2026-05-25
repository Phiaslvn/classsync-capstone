<?php
/**
 * Moderator Profile Update Script
 * Handles profile updates for moderator users
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

    // Check if at least one field has been provided
    if (empty($fname) && empty($lname) && empty($acc_user) && empty($acc_email)) {
        echo json_encode(['success' => false, 'message' => 'At least one field must be provided for update']);
        exit;
    }

    // Validate email format only if email is provided
    if (!empty($acc_email) && !filter_var($acc_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Check if username or email already exists for another user (only if they're being updated)
    if (!empty($acc_user) || !empty($acc_email)) {
        $stmt = $conn->prepare("SELECT acc_id FROM account WHERE (acc_user = ? OR acc_email = ?) AND acc_id != ?");
        $stmt->bind_param("ssi", $acc_user, $acc_email, $acc_id);
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
    
    if (!empty($fname)) {
        $updateFields[] = "fname = ?";
        $updateValues[] = $fname;
        $updateTypes .= 's';
    }
    
    if (!empty($lname)) {
        $updateFields[] = "lname = ?";
        $updateValues[] = $lname;
        $updateTypes .= 's';
    }
    
    if (!empty($minitial)) {
        $updateFields[] = "minitial = ?";
        $updateValues[] = $minitial;
        $updateTypes .= 's';
    }
    
    if (!empty($acc_user)) {
        $updateFields[] = "acc_user = ?";
        $updateValues[] = $acc_user;
        $updateTypes .= 's';
    }
    
    if (!empty($acc_email)) {
        $updateFields[] = "acc_email = ?";
        $updateValues[] = $acc_email;
        $updateTypes .= 's';
    }
    
    // Add acc_id to the end
    $updateValues[] = $acc_id;
    $updateTypes .= 'i';
    
    // Create the update query
    $updateQuery = "UPDATE account SET " . implode(", ", $updateFields) . " WHERE acc_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($updateTypes, ...$updateValues);

    if ($stmt->execute()) {
        // Update session info - only update fields that were changed
        if (!isset($_SESSION['user_info'])) {
            $_SESSION['user_info'] = [];
        }
        
        if (!empty($fname)) {
            $_SESSION['user_info']['fname'] = $fname;
        }
        if (!empty($lname)) {
            $_SESSION['user_info']['lname'] = $lname;
        }
        if (!empty($minitial)) {
            $_SESSION['user_info']['minitial'] = $minitial;
        }
        if (!empty($acc_user)) {
            $_SESSION['user_info']['acc_user'] = $acc_user;
        }
        if (!empty($acc_email)) {
            $_SESSION['user_info']['acc_email'] = $acc_email;
        }

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>

