<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

if (!hasPermission('view_schedules')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$sectionId = $_GET['section_id'] ?? '';

if (empty($sectionId)) {
    echo json_encode(['success' => false, 'message' => 'Section ID required']);
    exit;
}

try {
    // Get section details with all related information
    $query = "
        SELECT 
            sec.sec_id,
            sec.sec_name,
            sec.class_id,
            sec.program_id,
            cls.curr_id,
            cls.class_lvl as year_level,
            cls.class_term,
            cls.sy_id,
            sy.sy_name,
            sy.sy_year,
            curr.curr_name,
            curr.dept_id,
            p.program_id,
            p.program_code,
            p.program_name
        FROM section sec
        JOIN class cls ON sec.class_id = cls.class_id
        JOIN schoolyear sy ON cls.sy_id = sy.sy_id
        JOIN curriculum curr ON cls.curr_id = curr.curr_id
        LEFT JOIN program p ON sec.program_id = p.program_id
        WHERE sec.sec_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sectionData = $result->fetch_assoc();
    $stmt->close();
    
    if (!$sectionData) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $sectionData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred: ' . $e->getMessage()
    ]);
}
?>

