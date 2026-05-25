<?php
session_start();
include '../../config/database.php';

// Allow all authenticated roles
if (!isset($_SESSION['acc_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$acc_id = $_SESSION['acc_id'];

try {
    // Get admin profile data
    $stmt = $conn->prepare("
        SELECT fname, lname, minitial, acc_user, acc_email, profile_photo 
        FROM account 
        WHERE acc_id = ?
    ");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response = [
            'success' => true,
            'fname' => $row['fname'],
            'lname' => $row['lname'],
            'minitial' => $row['minitial'],
            'acc_user' => $row['acc_user'],
            'acc_email' => $row['acc_email'],
            'profile_photo' => $row['profile_photo'] ? '../../public/' . $row['profile_photo'] : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iNDAiIGZpbGw9IiM4MDAwMDAiLz4KPGNpcmNsZSBjeD0iNDAiIGN5PSIzMCIgcj0iMTIiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGQ9Ik0yMCA2MEMyMCA1MC4zNTg5IDI4LjM1ODkgNDIgMzggNDJINjJDNTMuNjQxMSA0MiA2MCA1MC4zNTg5IDYwIDYwVjY4SDIwVjYwWiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+'
        ];
    } else {
        $response = ['success' => false, 'message' => 'Profile not found'];
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
