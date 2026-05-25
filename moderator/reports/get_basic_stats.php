<?php
// Basic statistics endpoint for moderators
// This provides minimal data for display purposes

include '../config/database.php';

try {
    $stats = [];
    
    // Get basic user count (no filtering)
    $result = mysqli_query($GLOBALS['conn'], "
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
    $result = mysqli_query($GLOBALS['conn'], "SELECT COUNT(*) as total_instructors FROM instructor");
    if ($result) {
        $instructorData = mysqli_fetch_assoc($result);
        $stats['instructors'] = [
            'total_instructors' => $instructorData['total_instructors']
        ];
    } else {
        $stats['instructors'] = ['total_instructors' => 0];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'message' => 'Basic statistics loaded for moderator'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load basic statistics: ' . $e->getMessage()
    ]);
}
?>

