<?php
/**
 * Add Department API
 * Handles adding new departments to the database
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check database connection (simple check like other working files)
if (isset($db_connection_error) || !isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please check your database configuration.'
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['acc_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in.'
    ]);
    exit();
}

// Check permissions - only Admin Support can add departments
if (!isAdminSupport()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only Admin Support can add departments.'
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
    $dept_name = isset($_POST['dept_name']) ? trim($_POST['dept_name']) : '';
    $dept_code = isset($_POST['dept_code']) ? trim($_POST['dept_code']) : null;
    $dept_status = isset($_POST['dept_status']) ? $_POST['dept_status'] : 'Active';
    
    // Validate required fields
    if (empty($dept_name)) {
        echo json_encode([
            'success' => false,
            'message' => 'Department name is required.'
        ]);
        exit();
    }

    // If no college was provided (UI no longer asks for it), fall back to the first college
    if ($college_id <= 0) {
        $defaultCollegeStmt = $conn->prepare("SELECT college_id FROM college ORDER BY college_id LIMIT 1");
        $defaultCollegeStmt->execute();
        $defaultResult = $defaultCollegeStmt->get_result();
        if ($row = $defaultResult->fetch_assoc()) {
            $college_id = (int) $row['college_id'];
        } else {
            $defaultCollegeStmt->close();
            echo json_encode([
                'success' => false,
                'message' => 'No colleges are configured yet. Please create a college first.'
            ]);
            exit();
        }
        $defaultCollegeStmt->close();
    }
    
    // Validate status
    if (!in_array($dept_status, ['Active', 'Inactive'])) {
        $dept_status = 'Active';
    }
    
    // Check if department code already exists (if provided)
    if (!empty($dept_code)) {
        $checkStmt = $conn->prepare("SELECT dept_id FROM department WHERE dept_code = ?");
        $checkStmt->bind_param("s", $dept_code);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $checkStmt->close();
            echo json_encode([
                'success' => false,
                'message' => 'Department code already exists. Please use a different code.'
            ]);
            exit();
        }
        $checkStmt->close();
    }
    
    // Check if department name already exists for this college
    $checkStmt = $conn->prepare("SELECT dept_id FROM department WHERE dept_name = ? AND college_id = ?");
    $checkStmt->bind_param("si", $dept_name, $college_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $checkStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Department name already exists for this college. Please use a different name.'
        ]);
        exit();
    }
    $checkStmt->close();
    
    // Insert department
    if (!empty($dept_code)) {
        $stmt = $conn->prepare("INSERT INTO department (college_id, dept_name, dept_code, dept_status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $college_id, $dept_name, $dept_code, $dept_status);
    } else {
        $stmt = $conn->prepare("INSERT INTO department (college_id, dept_name, dept_status) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $college_id, $dept_name, $dept_status);
    }
    
    if ($stmt->execute()) {
        $dept_id = $conn->insert_id;
        
        // Log the action
        if (function_exists('logAdminAction')) {
            logAdminAction($_SESSION['acc_id'], 'create_department', "Created department: $dept_name (ID: $dept_id)");
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully!',
            'data' => [
                'dept_id' => $dept_id,
                'dept_name' => $dept_name,
                'dept_code' => $dept_code,
                'dept_status' => $dept_status
            ]
        ]);
    } else {
        throw new Exception("Failed to insert department: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("Add Department Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
}
?>

