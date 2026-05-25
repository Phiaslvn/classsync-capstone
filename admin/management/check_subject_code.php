<?php
/**
 * Check Subject Code API
 * Checks if a subject code already exists for a given curriculum.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
function json_response($success, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'data' => $data]);
    exit();
}

if (!hasPermission('manage_subjects')) {
    json_response(false, ['available' => false, 'message' => 'Unauthorized'], 403);
}

$subj_code = trim($_GET['subj_code'] ?? '');
$curr_id = isset($_GET['curr_id']) ? (int)$_GET['curr_id'] : 0;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$subj_category = trim($_GET['subj_category'] ?? '');
$ignore_id = isset($_GET['ignore_id']) ? (int)$_GET['ignore_id'] : 0; // For editing

if (empty($subj_code) || $curr_id <= 0) {
    json_response(true, ['available' => true]); // Don't show error if fields are empty
    exit();
}

try {
    // For GEN. ED. subjects: Check duplicate within same program + curriculum combination
    // For other subjects: Check duplicate within same curriculum (existing behavior)
    if ($subj_category === 'GENED' && $program_id > 0) {
        $query = "SELECT subj_id FROM subject WHERE subj_code = ? AND program_id = ? AND curr_id = ?";
        $params = [$subj_code, $program_id, $curr_id];
        $types = "sii";
        $errorMessage = 'This code is already in use for this program and curriculum.';
    } else {
        // Default behavior: Check within same curriculum
        $query = "SELECT subj_id FROM subject WHERE subj_code = ? AND curr_id = ?";
        $params = [$subj_code, $curr_id];
        $types = "si";
        $errorMessage = 'This code is already in use for this curriculum.';
    }

    if ($ignore_id > 0) {
        $query .= " AND subj_id != ?";
        $params[] = $ignore_id;
        $types .= "i";
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        json_response(true, ['available' => false, 'message' => $errorMessage]);
    } else {
        json_response(true, ['available' => true, 'message' => 'Subject code is available.']);
    }
} catch (Exception $e) {
    error_log("Check Subject Code Error: " . $e->getMessage());
    json_response(false, ['available' => false, 'message' => 'Database error during validation.'], 500);
}
?>