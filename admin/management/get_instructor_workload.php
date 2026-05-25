<?php
/**
 * Get instructor workload hours directly from instructor table
 * 
 * Endpoint: admin/management/get_instructor_workload.php
 * Method: GET
 * Parameters:
 *   - inst_id (required): The instructor ID
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['acc_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Get inst_id from request
$inst_id = isset($_GET['inst_id']) ? (int)$_GET['inst_id'] : 0;

if ($inst_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid instructor ID']);
    exit;
}

try {
    // Fetch workload hours directly from instructor table
    $stmt = $conn->prepare("
        SELECT 
            administration_hours,
            instruction_hours,
            research_hours,
            extension_hours,
            instructional_functions_hours,
            consultation_hours,
            instruction_hours
        FROM instructor 
        WHERE inst_id = ?
    ");
    $stmt->bind_param('i', $inst_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Instructor not found'
        ]);
        exit;
    }
    
    $workload = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'workload' => [
            'administration_hours' => (int)($workload['administration_hours'] ?? 0),
            'research_hours' => (int)($workload['research_hours'] ?? 0),
            'extension_hours' => (int)($workload['extension_hours'] ?? 0),
            'instructional_functions_hours' => (int)($workload['instructional_functions_hours'] ?? 0),
            'consultation_hours' => (int)($workload['consultation_hours'] ?? 0),
            'instruction_hours' => (int)($workload['instruction_hours'] ?? 0)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>









