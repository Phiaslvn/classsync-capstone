<?php
/**
 * Get Departments API
 * Returns all departments with their college information
 */

session_start();
include __DIR__ . '/../../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['acc_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }

    // Query to get departments with college information
    $query = "
        SELECT 
            d.dept_id,
            d.dept_name,
            c.college_name,
            c.college_code
        FROM department d
        JOIN college c ON d.college_id = c.college_id
        ORDER BY c.college_name, d.dept_name
    ";
    
    $result = $conn->query($query);
    $departments = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
    
} catch (Exception $e) {
    error_log("Get Departments Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch departments'
    ]);
}
?>
