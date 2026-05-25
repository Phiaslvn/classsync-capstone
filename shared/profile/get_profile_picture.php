<?php
/**
 * Get Profile Picture Path
 * Returns the current profile picture path for a user
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

// Get profile picture from database
$stmt = $conn->prepare("SELECT profile_picture FROM account WHERE acc_id = ?");
$stmt->bind_param("i", $acc_id);
$stmt->execute();
$result = $stmt->get_result();
$profileData = $result->fetch_assoc();
$stmt->close();

if ($profileData && $profileData['profile_picture']) {
    $imagePath = $profileData['profile_picture'];
    
    // Check if it's a data URI (default image) or a file path
    if (strpos($imagePath, 'data:image') === 0) {
        // It's a data URI, return it as is
        echo json_encode([
            'success' => true,
            'image_path' => $imagePath
        ]);
    } else {
        // Check if file exists in possible locations
        $possiblePaths = [
            __DIR__ . '/../../' . $imagePath,
            __DIR__ . '/../../public/' . $imagePath,
            $imagePath
        ];
        
        $fileExists = false;
        foreach ($possiblePaths as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $fileExists = true;
                break;
            }
        }
        
        if ($fileExists) {
            echo json_encode([
                'success' => true,
                'image_path' => $imagePath
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Profile picture file not found on server'
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No profile picture found'
    ]);
}

$conn->close();
?>
