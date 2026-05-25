<?php
/**
 * Add College API
 * Handles adding new colleges to the database
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

// Check permissions - only Admin Support can add colleges
if (!isAdminSupport()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only Admin Support can add colleges.'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit();
}

try {
    // Get and validate input
    $college_name = isset($_POST['college_name']) ? trim($_POST['college_name']) : '';
    $college_code = isset($_POST['college_code']) ? trim($_POST['college_code']) : null;
    $college_desc = isset($_POST['college_desc']) ? trim($_POST['college_desc']) : null;
    $college_status = isset($_POST['college_status']) ? $_POST['college_status'] : 'Active';
    
    // Validate required fields
    if (empty($college_name)) {
        echo json_encode([
            'success' => false,
            'message' => 'College name is required.'
        ]);
        exit();
    }
    
    // Validate college name length
    if (strlen($college_name) > 100) {
        echo json_encode([
            'success' => false,
            'message' => 'College name must not exceed 100 characters.'
        ]);
        exit();
    }
    
    // Validate status
    if (!in_array($college_status, ['Active', 'Inactive'])) {
        $college_status = 'Active';
    }
    
    // Validate college code length if provided
    if (!empty($college_code) && strlen($college_code) > 10) {
        echo json_encode([
            'success' => false,
            'message' => 'College code must not exceed 10 characters.'
        ]);
        exit();
    }
    
    // Check if college code already exists (if provided)
    if (!empty($college_code)) {
        $checkStmt = $conn->prepare("SELECT college_id FROM college WHERE college_code = ?");
        $checkStmt->bind_param("s", $college_code);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $checkStmt->close();
            echo json_encode([
                'success' => false,
                'message' => 'College code already exists. Please use a different code.'
            ]);
            exit();
        }
        $checkStmt->close();
    }
    
    // Check if college name already exists
    $checkStmt = $conn->prepare("SELECT college_id FROM college WHERE college_name = ?");
    $checkStmt->bind_param("s", $college_name);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $checkStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'College name already exists. Please use a different name.'
        ]);
        exit();
    }
    $checkStmt->close();
    
    // Normalize empty strings to NULL for optional fields
    $college_code = !empty($college_code) ? $college_code : null;
    $college_desc = !empty($college_desc) ? $college_desc : null;
    
    // Insert college - always include all fields, using NULL for empty optional fields
    $stmt = $conn->prepare("INSERT INTO college (college_name, college_code, college_desc, college_status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $college_name, $college_code, $college_desc, $college_status);
    
    if ($stmt->execute()) {
        $college_id = $conn->insert_id;
        
        // Log the action
        if (function_exists('logAdminAction')) {
            logAdminAction($_SESSION['acc_id'], 'create_college', "Created college: $college_name (ID: $college_id)");
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'College added successfully!',
            'data' => [
                'college_id' => $college_id,
                'college_name' => $college_name,
                'college_code' => $college_code,
                'college_desc' => $college_desc,
                'college_status' => $college_status
            ]
        ]);
    } else {
        throw new Exception("Failed to insert college: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("Add College Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
}
?>

