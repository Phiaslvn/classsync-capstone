<?php
// Start output buffering
ob_start();

session_start();
include '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Clean any output
ob_clean();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['acc_id'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

try {
    $stats = [];
    
    // Get basic user count (no filtering)
    $result = mysqli_query($conn, "
        SELECT 
            COUNT(CASE WHEN acc_status = 'Active' THEN 1 END) as active_users,
            COUNT(CASE WHEN acc_status = 'Pending' THEN 1 END) as pending_users,
            COUNT(CASE WHEN acc_status = 'Inactive' THEN 1 END) as inactive_users,
            COUNT(*) as total_users
        FROM account
    ");
    
    if ($result) {
        $stats['users'] = mysqli_fetch_assoc($result);
    } else {
        $stats['users'] = [
            'total_users' => 0,
            'active_users' => 0,
            'pending_users' => 0,
            'inactive_users' => 0
        ];
    }
    
    // Get basic instructor count
    $result = mysqli_query($conn, "SELECT COUNT(*) as total_instructors FROM instructor");
    if ($result) {
        $instructorData = mysqli_fetch_assoc($result);
        $stats['instructors'] = [
            'total_instructors' => $instructorData['total_instructors']
        ];
    } else {
        $stats['instructors'] = ['total_instructors' => 0];
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'message' => 'Basic statistics loaded for instructor'
    ], JSON_UNESCAPED_UNICODE);
    exit();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load basic statistics: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
