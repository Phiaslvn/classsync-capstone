<?php
/**
 * Unified Profile Picture Upload Script
 * Works for all user types (Admin, Admin Support, Instructor, Moderator)
 */

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

require_once '../../config/database.php';
require_once '../../includes/dashboard/dashboard_permissions.php';

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

// Check if file was uploaded
if (!isset($_FILES['profile_picture'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded. Available files: ' . implode(', ', array_keys($_FILES))]);
    exit;
}

$file = $_FILES['profile_picture'];

// Debug: Log file information
error_log('Upload debug - File info: ' . print_r($file, true));
error_log('Upload debug - POST data: ' . print_r($_POST, true));

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $error_message = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Unknown upload error';
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

$acc_id = $userInfo['acc_id'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
    exit;
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB.']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = '../../public/assets/uploads/profile_pictures/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Check if directory is writable
if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
    exit;
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $acc_id . '_' . time() . '.' . $fileExtension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update database with new profile picture path
    $dbPath = 'assets/uploads/profile_pictures/' . $filename;
    $stmt = $conn->prepare("UPDATE account SET profile_picture = ? WHERE acc_id = ?");
    $stmt->bind_param("si", $dbPath, $acc_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture uploaded successfully',
            'image_path' => 'assets/uploads/profile_pictures/' . $filename
        ]);
    } else {
        // Remove uploaded file if database update fails
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        echo json_encode(['success' => false, 'message' => 'Failed to update database: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check directory permissions.']);
}

$conn->close();
?>