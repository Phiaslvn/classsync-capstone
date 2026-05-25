<?php
// Profile Picture Upload Handler
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Check if user is logged in and has admin support role
if (!isset($_SESSION['acc_id']) || $_SESSION['role'] !== 'Admin support') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the user ID and file
$user_id = intval($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action !== 'upload_profile_picture' || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_picture'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.']);
    exit;
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../assets/uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Update database
    $relative_path = 'assets/uploads/profile_pictures/' . $new_filename;
    
    $stmt = $conn->prepare("UPDATE account SET profile_picture = ? WHERE acc_id = ?");
    $stmt->bind_param("si", $relative_path, $user_id);
    
    if ($stmt->execute()) {
        // Log the action
        logAdminAction($_SESSION['acc_id'], 'upload_profile_picture', "Uploaded profile picture for user ID $user_id");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile picture uploaded successfully',
            'profile_picture' => $relative_path
        ]);
    } else {
        // Delete the uploaded file if database update failed
        unlink($upload_path);
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}

$conn->close();
?>

