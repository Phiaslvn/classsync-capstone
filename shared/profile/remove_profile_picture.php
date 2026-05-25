<?php
/**
 * Unified Profile Picture Removal Script
 * Works for all user types (Admin, Admin Support, Instructor, Moderator)
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/dashboard/dashboard_permissions.php';

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

// Get current profile picture path from database
$stmt = $conn->prepare("SELECT profile_picture FROM account WHERE acc_id = ?");
$stmt->bind_param("i", $acc_id);
$stmt->execute();
$result = $stmt->get_result();
$profileData = $result->fetch_assoc();
$stmt->close();

if ($profileData && $profileData['profile_picture']) {
    $oldImagePath = $profileData['profile_picture'];

    // Delete file from server if it exists and is not the default image
    $defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+';
    
    // Check if it's not a data URI (default image)
    if (strpos($oldImagePath, 'data:image') === false) {
        // Convert relative path to absolute path
        // Profile pictures are stored in assets/uploads/profile_pictures/ or public/assets/uploads/profile_pictures/
        $possiblePaths = [
            __DIR__ . '/../../' . $oldImagePath,  // assets/uploads/profile_pictures/...
            __DIR__ . '/../../public/' . $oldImagePath,  // public/assets/uploads/profile_pictures/...
            $oldImagePath  // Already absolute path
        ];
        
        foreach ($possiblePaths as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
                break;
            }
        }
    }

    // Update database to set profile_picture to NULL
    $stmt = $conn->prepare("UPDATE account SET profile_picture = NULL WHERE acc_id = ?");
    $stmt->bind_param("i", $acc_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No profile picture to remove']);
}

$conn->close();
?>
