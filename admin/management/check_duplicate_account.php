<?php
/**
 * Check for Duplicate Account
 * Checks if a name with the same role or email already exists in the database
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Only allow Admin (Department Head), Admin Support, and Moderator users
if (!isset($_SESSION['acc_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check user role from database
$stmt = $conn->prepare("
    SELECT r.id as role_id, r.role_name
    FROM account a 
    JOIN user_roles ur ON a.acc_id = ur.acc_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE a.acc_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['acc_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array($user['role_id'], [1, 2, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get form data
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $minitial = trim($_POST['minitial'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $acc_email = trim($_POST['email'] ?? $_POST['acc_email'] ?? '');
    $acc_id = !empty($_POST['acc_id']) ? (int)$_POST['acc_id'] : null; // For edit mode, exclude current account
    
    // Handle multiple role_ids (can be array or single value for backward compatibility)
    $role_ids = [];
    
    // Check for role_ids array (from checkboxes with name="role_ids[]")
    if (isset($_POST['role_ids']) && is_array($_POST['role_ids'])) {
        // Multiple roles from checkboxes
        $role_ids = array_map('intval', $_POST['role_ids']);
        $role_ids = array_filter($role_ids); // Remove empty/zero values
        $role_ids = array_values($role_ids); // Re-index array
    } elseif (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
        // Single role for backward compatibility
        $role_ids = [(int)$_POST['role_id']];
    }
    
    // FALLBACK: If no roles were selected, consider as Instructor (role_id = 4)
    if (empty($role_ids)) {
        $role_ids = [4]; // Default to Instructor role
    }
    
    $response = [
        'success' => true,
        'name_exists' => false,
        'email_exists' => false,
        'message' => '',
        'duplicate_roles' => []
    ];
    
    // Check if name exists with the SAME role_id AND SAME department for EACH role being assigned
    // Allow same name + role in different departments (instructors can teach in multiple departments)
    $dept_id = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
    
    // Check if account_departments table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
    $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
    
    if (!empty($fname) && !empty($lname) && !empty($role_ids) && $dept_id) {
        $duplicateRoles = [];
        
        foreach ($role_ids as $role_id) {
            // Check if name exists with the SPECIFIC role being created AND in the SAME department
            // Use account_departments table if available, otherwise fall back to account.dept_id
            if ($hasAccountDepartmentsTable) {
                $nameCheckQuery = "
                    SELECT a.acc_id, a.fname, a.lname, a.minitial, a.suffix, r.role_name, r.id as role_id
                    FROM account a
                    JOIN user_roles ur ON a.acc_id = ur.acc_id
                    JOIN roles r ON ur.role_id = r.id
                    JOIN account_departments ad ON a.acc_id = ad.acc_id
                    WHERE LOWER(TRIM(a.fname)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(a.lname)) = LOWER(TRIM(?))
                    AND ur.role_id = ?
                    AND ad.dept_id = ?
                ";
            } else {
                // Fallback to old method if table doesn't exist
                $nameCheckQuery = "
                    SELECT a.acc_id, a.fname, a.lname, a.minitial, a.suffix, r.role_name, r.id as role_id, a.dept_id
                    FROM account a
                    JOIN user_roles ur ON a.acc_id = ur.acc_id
                    JOIN roles r ON ur.role_id = r.id
                    WHERE LOWER(TRIM(a.fname)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(a.lname)) = LOWER(TRIM(?))
                    AND ur.role_id = ?
                    AND a.dept_id = ?
                ";
            }
            
            $params = [$fname, $lname, $role_id, $dept_id];
            $types = 'ssii';
            
            // Include middle initial in check if provided
            if (!empty($minitial)) {
                $nameCheckQuery .= " AND LOWER(TRIM(a.minitial)) = LOWER(TRIM(?))";
                $params[] = $minitial;
                $types .= 's';
            } else {
                $nameCheckQuery .= " AND (a.minitial IS NULL OR a.minitial = '')";
            }
            
            // Exclude current account if editing
            if ($acc_id) {
                $nameCheckQuery .= " AND a.acc_id != ?";
                $params[] = $acc_id;
                $types .= 'i';
            }
            
            $stmt = $conn->prepare($nameCheckQuery);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $existingAccount = $result->fetch_assoc();
                    $response['name_exists'] = true;
                    $response['existing_name'] = trim($existingAccount['fname'] . ' ' . (!empty($existingAccount['minitial']) ? $existingAccount['minitial'] . '. ' : '') . $existingAccount['lname'] . (!empty($existingAccount['suffix']) ? ' ' . $existingAccount['suffix'] : ''));
                    $duplicateRoles[] = [
                        'role_id' => $role_id,
                        'role_name' => $existingAccount['role_name']
                    ];
                }
                $stmt->close();
            }
        }
        
        if (!empty($duplicateRoles)) {
            $response['duplicate_roles'] = $duplicateRoles;
            $response['is_duplicate'] = true;
        }
    }
    
    // Check if email exists
    // If email exists AND we're adding to a different department, that's OK (not a duplicate)
    // Only block if email exists AND account already has this department
    if (!empty($acc_email)) {
        $emailCheckQuery = "SELECT acc_id, acc_email FROM account WHERE LOWER(TRIM(acc_email)) = LOWER(TRIM(?))";
        $params = [$acc_email];
        $types = 's';
        
        // Exclude current account if editing
        if ($acc_id) {
            $emailCheckQuery .= " AND acc_id != ?";
            $params[] = $acc_id;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare($emailCheckQuery);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existingAccount = $result->fetch_assoc();
                $existingAccId = (int)$existingAccount['acc_id'];
                
                // Check if this account already has the target department
                // If dept_id is provided and account_departments table exists, check if association already exists
                if ($dept_id && $hasAccountDepartmentsTable) {
                    $deptCheckStmt = $conn->prepare("SELECT id FROM account_departments WHERE acc_id = ? AND dept_id = ?");
                    $deptCheckStmt->bind_param("ii", $existingAccId, $dept_id);
                    $deptCheckStmt->execute();
                    $deptResult = $deptCheckStmt->get_result();
                    
                    if ($deptResult->num_rows > 0) {
                        // Account already has this department - it's a duplicate
                        $response['email_exists'] = true;
                        $response['existing_email'] = $existingAccount['acc_email'];
                        $response['email_already_in_dept'] = true;
                    } else {
                        // Account exists but doesn't have this department - OK to add (not a duplicate)
                        $response['email_exists'] = false; // Don't treat as duplicate
                        $response['email_exists_but_new_dept'] = true; // Flag for frontend info
                    }
                    $deptCheckStmt->close();
                } else {
                    // Fallback: If no dept_id or account_departments table doesn't exist, use old behavior
                    // Check if account.dept_id matches (for backward compatibility)
                    if ($dept_id) {
                        $deptCheckStmt = $conn->prepare("SELECT acc_id FROM account WHERE acc_id = ? AND dept_id = ?");
                        $deptCheckStmt->bind_param("ii", $existingAccId, $dept_id);
                        $deptCheckStmt->execute();
                        $deptResult = $deptCheckStmt->get_result();
                        
                        if ($deptResult->num_rows > 0) {
                            // Account already has this department - it's a duplicate
                            $response['email_exists'] = true;
                            $response['existing_email'] = $existingAccount['acc_email'];
                            $response['email_already_in_dept'] = true;
                        } else {
                            // Account exists but doesn't have this department - OK to add (not a duplicate)
                            $response['email_exists'] = false;
                            $response['email_exists_but_new_dept'] = true;
                        }
                        $deptCheckStmt->close();
                    } else {
                        // No dept_id provided - use old behavior (block if email exists)
                        $response['email_exists'] = true;
                        $response['existing_email'] = $existingAccount['acc_email'];
                    }
                }
            }
            $stmt->close();
        }
    }
    
    // Set message based on what was found
    $messages = [];
    
    if ($response['name_exists'] && !empty($response['duplicate_roles'])) {
        $roleNames = array_map(function($r) { return $r['role_name']; }, $response['duplicate_roles']);
        $messages[] = 'A user with the name "' . trim($fname . ' ' . (!empty($minitial) ? $minitial . '. ' : '') . $lname . (!empty($suffix) ? ' ' . $suffix : '')) . '" already exists with the role(s): ' . implode(', ', $roleNames);
        $response['success'] = false;
    }
    
    // Only treat email as duplicate if account already has this department
    // If email exists but account doesn't have this department, it's OK (adding to new dept)
    if ($response['email_exists'] && !empty($response['email_already_in_dept'])) {
        $messages[] = 'Email already exists in this department';
        $response['success'] = false;
    } elseif (!empty($response['email_exists_but_new_dept'])) {
        // Email exists but it's a different department - this is OK, don't block
        // The add_account.php will handle reusing the account and adding the department
        $response['success'] = true; // Allow it
    }
    
    if (!empty($messages)) {
        $response['message'] = implode('\n', $messages);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}


