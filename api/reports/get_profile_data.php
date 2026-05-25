<?php
// Start output buffering to prevent any premature output
ob_start();

session_start();
include '../../config/database.php';

// Clean any output that might have been generated
ob_clean();

// Set JSON header first
header('Content-Type: application/json');

// Allow all authenticated roles
if (!isset($_SESSION['acc_id'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit();
}

$acc_id = $_SESSION['acc_id'];

try {
    // Check database connection
    if (!isset($conn)) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection not initialized. Check database.php include path.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if ($conn->connect_error) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Get profile data with instructor rank and department
    // Fetch instructor name from instructor table if available, otherwise use account table
    $stmt = $conn->prepare("
        SELECT 
            a.acc_user, a.acc_email, a.profile_picture,
            a.fname as account_fname, a.lname as account_lname, a.minitial as account_minitial,
            i.inst_fname, i.inst_lname, i.inst_mname, i.inst_suffix,
            i.rank,
            COALESCE(d.dept_name, d2.dept_name) as dept_name,
            COALESCE(c.college_name, c2.college_name) as college_name
        FROM account a
        LEFT JOIN instructor i ON i.inst_user = a.acc_user
        LEFT JOIN department d ON a.dept_id = d.dept_id
        LEFT JOIN department d2 ON i.dept_id = d2.dept_id
        LEFT JOIN college c ON d.college_id = c.college_id
        LEFT JOIN college c2 ON d2.college_id = c2.college_id
        WHERE a.acc_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Use profile_picture field and construct proper path
        $profilePicture = null;
        if (!empty($row['profile_picture'])) {
            // If path doesn't start with http, make it relative
            if (strpos($row['profile_picture'], 'http') !== 0) {
                $profilePicture = '../../' . ltrim($row['profile_picture'], '/');
            } else {
                $profilePicture = $row['profile_picture'];
            }
        }
        
        // Get name - prioritize instructor table, fallback to account table
        // Use instructor table name if available and not empty, otherwise use account table
        $fname = (!empty($row['inst_fname'])) ? $row['inst_fname'] : ($row['account_fname'] ?? '');
        $lname = (!empty($row['inst_lname'])) ? $row['inst_lname'] : ($row['account_lname'] ?? '');
        $minitial = (!empty($row['inst_mname'])) ? $row['inst_mname'] : ($row['account_minitial'] ?? '');
        $suffix = $row['inst_suffix'] ?? '';
        $rank = $row['rank'] ?? '';
        
        // If name or rank is still missing, try direct query from instructor table
        if ((empty($fname) || empty($lname) || empty($rank)) && !empty($row['acc_user'])) {
            $instructorStmt = $conn->prepare("
                SELECT inst_fname, inst_lname, inst_mname, inst_suffix, rank
                FROM instructor 
                WHERE inst_user = ? 
                LIMIT 1
            ");
            if ($instructorStmt) {
                $instructorStmt->bind_param("s", $row['acc_user']);
                $instructorStmt->execute();
                $instructorResult = $instructorStmt->get_result();
                if ($instructorRow = $instructorResult->fetch_assoc()) {
                    // Only use instructor table values if they're not empty
                    if (!empty($instructorRow['inst_fname']) && empty($fname)) $fname = $instructorRow['inst_fname'];
                    if (!empty($instructorRow['inst_lname']) && empty($lname)) $lname = $instructorRow['inst_lname'];
                    if (!empty($instructorRow['inst_mname']) && empty($minitial)) $minitial = $instructorRow['inst_mname'];
                    if (!empty($instructorRow['inst_suffix']) && empty($suffix)) $suffix = $instructorRow['inst_suffix'];
                    if (!empty($instructorRow['rank']) && empty($rank)) $rank = $instructorRow['rank'];
                }
                $instructorStmt->close();
            }
        }
        
        // Log for debugging (remove in production)
        error_log("Profile data for acc_id $acc_id: fname=$fname, lname=$lname, rank=$rank");
        
        $response = [
            'success' => true,
            'fname' => trim($fname),
            'lname' => trim($lname),
            'minitial' => trim($minitial),
            'suffix' => trim($suffix),
            'acc_user' => $row['acc_user'] ?? '',
            'acc_email' => $row['acc_email'] ?? '',
            'rank' => trim($rank),
            'dept_name' => $row['dept_name'] ?? '',
            'college_name' => $row['college_name'] ?? '',
            'profile_photo' => $profilePicture ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iNDAiIGZpbGw9IiM4MDAwMDAiLz4KPGNpcmNsZSBjeD0iNDAiIGN5PSIzMCIgcj0iMTIiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGQ9Ik0yMCA2MEMyMCA1MC4zNTg5IDI4LjM1ODkgNDIgMzggNDJINjJDNTMuNjQxMSA0MiA2MCA1MC4zNTg5IDYwIDYwVjY4SDIwVjYwWiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+'
        ];
    } else {
        $response = ['success' => false, 'message' => 'Profile not found'];
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    ob_clean();
    error_log("get_profile_data.php Exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    ob_clean();
    error_log("get_profile_data.php Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}

// Ensure response is set
if (!isset($response)) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unexpected error: Response not set'], JSON_UNESCAPED_UNICODE);
    exit();
}

ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
