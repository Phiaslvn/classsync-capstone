<?php
/**
 * Utility script to clean up broken profile picture references in the database
 * This script finds profile pictures in the database that don't exist on the filesystem
 * and sets them to NULL
 * 
 * Usage: Run this script from command line or via browser (with proper authentication)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/dashboard/dashboard_permissions.php';

// Check if user is logged in and is admin/admin support
if (!isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized: Please log in first');
}

$userInfo = getUserInfo();
$userRole = getUserRole();

// Only allow admin support to run this
if (!$userRole || $userRole['role_name'] !== 'Admin support') {
    http_response_code(403);
    die('Forbidden: Only Admin Support can run this script');
}

header('Content-Type: application/json');

// Get all accounts with profile pictures
$stmt = $conn->prepare("SELECT acc_id, profile_picture FROM account WHERE profile_picture IS NOT NULL AND profile_picture != ''");
$stmt->execute();
$result = $stmt->get_result();

$brokenImages = [];
$fixedCount = 0;
$baseDir = __DIR__ . '/../../';

while ($row = $result->fetch_assoc()) {
    $dbPath = $row['profile_picture'];
    $accId = $row['acc_id'];
    
    // Skip data URIs (default images)
    if (strpos($dbPath, 'data:image') === 0) {
        continue;
    }
    
    // Check if file exists in any possible location
    $cleanPath = ltrim($dbPath, '/');
    $possiblePaths = [
        $baseDir . 'public/assets/uploads/profile_pictures/' . basename($cleanPath),
        $baseDir . 'assets/uploads/profile_pictures/' . basename($cleanPath),
        $baseDir . 'public/' . $cleanPath,
        $baseDir . $cleanPath
    ];
    
    $fileExists = false;
    foreach ($possiblePaths as $filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            $fileExists = true;
            break;
        }
    }
    
    if (!$fileExists) {
        // File doesn't exist - mark for cleanup
        $brokenImages[] = [
            'acc_id' => $accId,
            'profile_picture' => $dbPath
        ];
        
        // Update database to set profile_picture to NULL
        $updateStmt = $conn->prepare("UPDATE account SET profile_picture = NULL WHERE acc_id = ?");
        $updateStmt->bind_param("i", $accId);
        if ($updateStmt->execute()) {
            $fixedCount++;
        }
        $updateStmt->close();
    }
}

$stmt->close();

echo json_encode([
    'success' => true,
    'message' => "Cleanup completed. Fixed $fixedCount broken image references.",
    'broken_images_found' => count($brokenImages),
    'fixed_count' => $fixedCount,
    'broken_images' => $brokenImages
], JSON_PRETTY_PRINT);

$conn->close();
?>

