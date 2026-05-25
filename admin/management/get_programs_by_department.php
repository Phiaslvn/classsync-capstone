<?php
/**
 * Get Programs by Department API
 * Returns all programs for a specific department, or all programs if no department is specified.
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Use the security middleware to check for permission.
    if (!hasPermission('manage_curriculum') && !hasPermission('manage_subjects')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized to view programs.']);
        exit();
    }

    // Get department ID from query parameter, falling back to the user's session department ID.
    $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : ($_SESSION['dept_id'] ?? 0);
    
    // Base query
    $query = " 
        SELECT 
            p.program_id,
            p.program_name,
            p.program_desc,
            p.program_status,
            d.dept_name,
            p.dept_id
        FROM program p
        JOIN department d ON p.dept_id = d.dept_id
    ";
    
    // If a department ID is provided (from GET or session), filter by it.
    if ($dept_id > 0) {
        $query .= " WHERE p.dept_id = ? AND p.program_status = 'Active' ORDER BY p.program_name";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $dept_id);
    } else {
        // Otherwise, get all active programs. This branch is useful for super-admins.
        $query .= " WHERE p.program_status = 'Active' ORDER BY d.dept_name, p.program_name";
        $stmt = $conn->prepare($query);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $programs = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'programs' => $programs
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Get Programs by Department Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch programs'
    ]);
}

?>
