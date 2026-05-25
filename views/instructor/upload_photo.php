<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['acc_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$acc_id = $_SESSION['acc_id'];
$response = ['success' => false, 'error' => 'Unknown error'];

try {
    // Check if this is a clear photo request
    if (isset($_POST['clear_photo']) && $_POST['clear_photo'] === '1') {
        // Clear the profile picture
        require_once __DIR__ . '/../../config/database.php';
        
        $stmt = $conn->prepare('UPDATE account SET profile_picture = NULL WHERE acc_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $acc_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Profile picture cleared successfully'];
            } else {
                $response = ['success' => false, 'error' => 'Failed to clear profile picture'];
            }
            $stmt->close();
        } else {
            $response = ['success' => false, 'error' => 'Database error'];
        }
    }
    // Check if this is a captured photo (data URL)
    elseif (isset($_POST['image_data'])) {
        $imageData = $_POST['image_data'];
        
        // Validate that it's a data URL
        if (strpos($imageData, 'data:image/') !== 0) {
            $response = ['success' => false, 'error' => 'Invalid image data'];
        } else {
            // Extract image data from data URL
            $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
            $imageData = str_replace('data:image/png;base64,', '', $imageData);
            $imageData = str_replace('data:image/gif;base64,', '', $imageData);
            $imageData = str_replace('data:image/webp;base64,', '', $imageData);
            
            $imageData = base64_decode($imageData);
            
            if ($imageData === false) {
                $response = ['success' => false, 'error' => 'Invalid base64 image data'];
            } else {
                // Create uploads directory if it doesn't exist (use same directory as unified handler)
                $uploadDir = __DIR__ . '/../../public/assets/uploads/profile_pictures/';
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0775, true)) {
                        $response = ['success' => false, 'error' => 'Failed to create upload directory'];
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($uploadDir)) {
                    $response = ['success' => false, 'error' => 'Upload directory is not writable'];
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                // Generate unique filename
                $filename = 'profile_' . $acc_id . '_' . time() . '.jpg';
                $filepath = $uploadDir . $filename;
                
                // Save the image
                if (file_put_contents($filepath, $imageData) !== false) {
                    // Update database
                    require_once __DIR__ . '/../../config/database.php';
                    
                    $relativePath = 'assets/uploads/profile_pictures/' . $filename;
                    $stmt = $conn->prepare('UPDATE account SET profile_picture = ? WHERE acc_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('si', $relativePath, $acc_id);
                        if ($stmt->execute()) {
                            $response = [
                                'success' => true, 
                                'message' => 'Photo uploaded successfully',
                                'url' => '../public/' . $relativePath,
                                'image_path' => $relativePath,
                                'profile_picture' => $relativePath
                            ];
                        } else {
                            // Remove uploaded file if database update fails
                            if (file_exists($filepath)) {
                                unlink($filepath);
                            }
                            $response = ['success' => false, 'error' => 'Failed to update database'];
                        }
                        $stmt->close();
                    } else {
                        // Remove uploaded file if database error
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                        $response = ['success' => false, 'error' => 'Database error'];
                    }
                } else {
                    $response = ['success' => false, 'error' => 'Failed to save image file. Check directory permissions.'];
                }
            }
        }
    }
    // Check if this is a file upload - support both 'photo' and 'profile_picture' for backward compatibility
    elseif ((isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) || 
            (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK)) {
        // Prefer 'profile_picture' over 'photo' for consistency
        $file = isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK 
                ? $_FILES['profile_picture'] 
                : $_FILES['photo'];
        
        // Validate file type - check both MIME type and actual file content for security
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            $response = ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.'];
        } else {
            // Additional security: verify actual file content
            $actualMimeType = mime_content_type($file['tmp_name']);
            if (!in_array($actualMimeType, $allowedTypes)) {
                $response = ['success' => false, 'error' => 'Invalid file type. File content does not match the file extension.'];
        } else {
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $response = ['success' => false, 'error' => 'File size too large. Maximum size is 5MB.'];
            } else {
                    // Create uploads directory if it doesn't exist (use same directory as unified handler)
                    $uploadDir = __DIR__ . '/../../public/assets/uploads/profile_pictures/';
                    if (!file_exists($uploadDir)) {
                        if (!mkdir($uploadDir, 0775, true)) {
                            $response = ['success' => false, 'error' => 'Failed to create upload directory'];
                            header('Content-Type: application/json');
                            echo json_encode($response);
                            exit;
                        }
                    }
                    
                    // Check if directory is writable
                    if (!is_writable($uploadDir)) {
                        $response = ['success' => false, 'error' => 'Upload directory is not writable'];
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                }
                
                    // Generate unique filename (use lowercase extension)
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'profile_' . $acc_id . '_' . time() . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update database
                    require_once __DIR__ . '/../../config/database.php';
                    
                    $relativePath = 'assets/uploads/profile_pictures/' . $filename;
                    $stmt = $conn->prepare('UPDATE account SET profile_picture = ? WHERE acc_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('si', $relativePath, $acc_id);
                        if ($stmt->execute()) {
                            $response = [
                                'success' => true, 
                                'message' => 'Photo uploaded successfully',
                                    'url' => '../public/' . $relativePath,
                                    'image_path' => $relativePath,
                                    'profile_picture' => $relativePath
                            ];
                        } else {
                                // Remove uploaded file if database update fails
                                if (file_exists($filepath)) {
                                    unlink($filepath);
                                }
                            $response = ['success' => false, 'error' => 'Failed to update database'];
                        }
                        $stmt->close();
                    } else {
                            // Remove uploaded file if database error
                            if (file_exists($filepath)) {
                                unlink($filepath);
                            }
                        $response = ['success' => false, 'error' => 'Database error'];
                    }
                } else {
                        $response = ['success' => false, 'error' => 'Failed to move uploaded file. Check directory permissions.'];
                    }
                }
            }
        }
    } else {
        $response = ['success' => false, 'error' => 'No photo data received'];
    }
    
} catch (Exception $e) {
    error_log('Photo upload error: ' . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

// Always return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
