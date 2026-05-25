<?php
/**
 * Get Colleges API
 * Returns all colleges for dropdowns
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['acc_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in.'
    ]);
    exit();
}

if (!isAdminSupport()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit();
}

try {
    // Get all colleges with description
    $result = mysqli_query($conn, "SELECT college_id, college_name, college_code, college_desc, college_status, created_at, updated_at FROM college ORDER BY college_name");
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($conn));
    }
    
    $colleges = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $colleges[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'colleges' => $colleges
    ]);
    
} catch (Exception $e) {
    error_log("Get Colleges Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch colleges: ' . $e->getMessage()
    ]);
}
?>

