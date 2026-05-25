<?php
/**
 * Quick script to check room_request table structure
 * Run this to see what columns exist
 */

require_once '../../config/database.php';

header('Content-Type: application/json');
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
try {
    // Get all columns from room_request table
    $query = "SHOW COLUMNS FROM room_request";
    $result = $conn->query($query);
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'column_names' => array_column($columns, 'Field')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

