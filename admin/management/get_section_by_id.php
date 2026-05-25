<?php
/**
 * Get Section by ID
 * Returns section details for a specific section ID
 */
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('manage_schedules') && !hasPermission('view_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$sec_id = isset($_GET['sec_id']) ? (int)$_GET['sec_id'] : 0;

if ($sec_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Section ID required.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT sec.sec_id, sec.sec_name, sec.sec_num, sec.class_id, sec.program_id,
               cls.class_lvl as year_level, cls.class_term, cls.sy_id
        FROM section sec
        LEFT JOIN class cls ON sec.class_id = cls.class_id
        WHERE sec.sec_id = ?
    ");
    $stmt->bind_param("i", $sec_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($section = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'section' => $section
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Section not found.'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Get Section by ID Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred.'
    ]);
}
?>

