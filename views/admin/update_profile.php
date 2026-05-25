<?php
session_start();
include '../../config/database.php';

// Allow all authenticated roles
if (!isset($_SESSION['acc_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$acc_id = $_SESSION['acc_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get form data
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $minitial = trim($_POST['minitial'] ?? '');
    $acc_user = trim($_POST['acc_user'] ?? '');
    $acc_email = trim($_POST['acc_email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fname) || empty($lname) || empty($acc_user) || empty($acc_email)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }

    // Validate email format
    if (!filter_var($acc_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    // Verify current password only if provided (for password changes)
    if (!empty($current_password)) {
        $stmt = $conn->prepare("SELECT acc_pass FROM account WHERE acc_id = ?");
        $stmt->bind_param("i", $acc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Account not found']);
            exit();
        }
        
        if (!password_verify($current_password, $row['acc_pass'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit();
        }
        $stmt->close();
    }

    // Check if username is already taken by another user
    $stmt = $conn->prepare("SELECT acc_id FROM account WHERE acc_user = ? AND acc_id != ?");
    $stmt->bind_param("si", $acc_user, $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit();
    }
    $stmt->close();

    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT acc_id FROM account WHERE acc_email = ? AND acc_id != ?");
    $stmt->bind_param("si", $acc_email, $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already taken']);
        exit();
    }
    $stmt->close();

    // Handle profile photo upload
    $profile_photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_photo']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed']);
            exit();
        }

        $file_size = $_FILES['profile_photo']['size'];
        if ($file_size > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB']);
            exit();
        }

        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $acc_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
            $profile_photo_path = 'assets/uploads/profile_pictures/' . $new_filename;
        }
    }

    // Prepare update query
    $update_fields = "fname = ?, lname = ?, minitial = ?, acc_user = ?, acc_email = ?";
    $params = [$fname, $lname, $minitial, $acc_user, $acc_email];
    $param_types = "sssss";

    // Add password update if new password provided
    if (!empty($new_password)) {
        if (empty($current_password)) {
            echo json_encode(['success' => false, 'message' => 'Current password is required to change password']);
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit();
        }
        
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
            exit();
        }
        
        $update_fields .= ", acc_pass = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        $param_types .= "s";
    }

    // Add profile photo update if uploaded
    if ($profile_photo_path) {
        $update_fields .= ", profile_picture = ?";
        $params[] = $profile_photo_path;
        $param_types .= "s";
    }

    $params[] = $acc_id;
    $param_types .= "i";

    $stmt = $conn->prepare("UPDATE account SET $update_fields WHERE acc_id = ?");
    $stmt->bind_param($param_types, ...$params);

    if ($stmt->execute()) {
        // Update session data
        $_SESSION['fname'] = $fname;
        $_SESSION['lname'] = $lname;
        $_SESSION['minitial'] = $minitial;
        $_SESSION['acc_user'] = $acc_user;
        $_SESSION['acc_email'] = $acc_email;

        $response = [
            'success' => true,
            'message' => 'Profile updated successfully'
        ];

        if ($profile_photo_path) {
            $response['profile_photo'] = '../../public/' . $profile_photo_path;
        }

        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

