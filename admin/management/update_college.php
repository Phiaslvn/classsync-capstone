<?php
/**
 * Update College API
 * Handles updating existing colleges in the database
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

// Check permissions - only Admin Support can update colleges
if (!isAdminSupport()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only Admin Support can update colleges.'
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
    $college_id = isset($_POST['college_id']) ? (int)$_POST['college_id'] : 0;
    $college_name = isset($_POST['college_name']) ? trim($_POST['college_name']) : '';
    $college_code = isset($_POST['college_code']) ? trim($_POST['college_code']) : null;
    $college_desc = isset($_POST['college_desc']) ? trim($_POST['college_desc']) : null;
    $college_status = isset($_POST['college_status']) ? $_POST['college_status'] : 'Active';
    
    // Validate required fields
    if ($college_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid college ID.'
        ]);
        exit();
    }
    
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
    
    // Check if college exists
    $checkStmt = $conn->prepare("SELECT college_id, college_code, college_name FROM college WHERE college_id = ?");
    $checkStmt->bind_param("i", $college_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'College not found.'
        ]);
        exit();
    }
    
    $existingCollege = $result->fetch_assoc();
    $checkStmt->close();
    
    // Check if college code already exists (if provided and different from current)
    if (!empty($college_code) && $college_code !== $existingCollege['college_code']) {
        $checkStmt = $conn->prepare("SELECT college_id FROM college WHERE college_code = ? AND college_id != ?");
        $checkStmt->bind_param("si", $college_code, $college_id);
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
    
    // Check if college name already exists (if different from current)
    if ($college_name !== $existingCollege['college_name']) {
        $checkStmt = $conn->prepare("SELECT college_id FROM college WHERE college_name = ? AND college_id != ?");
        $checkStmt->bind_param("si", $college_name, $college_id);
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
    }
    
    // Normalize empty strings to NULL for optional fields
    $college_code = !empty($college_code) ? $college_code : null;
    $college_desc = !empty($college_desc) ? $college_desc : null;
    
    // Update college - always include all fields
    $stmt = $conn->prepare("UPDATE college SET college_name = ?, college_code = ?, college_desc = ?, college_status = ? WHERE college_id = ?");
    $stmt->bind_param("ssssi", $college_name, $college_code, $college_desc, $college_status, $college_id);
    
    if ($stmt->execute()) {
        // Log the action
        if (function_exists('logAdminAction')) {
            logAdminAction($_SESSION['acc_id'], 'update_college', "Updated college: $college_name (ID: $college_id)");
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'College updated successfully!',
            'data' => [
                'college_id' => $college_id,
                'college_name' => $college_name,
                'college_code' => $college_code,
                'college_desc' => $college_desc,
                'college_status' => $college_status
            ]
        ]);
    } else {
        throw new Exception("Failed to update college: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("Update College Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
}
?>

