<?php
// Suppress any output before JSON - MUST be first line after opening tag
ob_start();

// Suppress error display (we'll handle errors ourselves)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set error handler to catch any PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  // Log the error but don't output it
  error_log("PHP Error in add_account.php: [$errno] $errstr in $errfile on line $errline");
  return true; // Suppress default error handler
});

session_start();

// Debug: Log session info
error_log("Add Account - Session ID: " . session_id());
error_log("Add Account - Session data: " . json_encode($_SESSION));

include '../../config/database.php';
require_once __DIR__ . '/../../includes/utils/instructor_department_appointments.php';

// Check for database connection errors BEFORE any output
if (isset($db_connection_error) || !isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) {
  ob_clean();
  header('Content-Type: application/json');
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database connection failed']);
  ob_end_flush();
  exit();
}

require '../../vendor/autoload.php';
require '../../includes/database/send_email.php';

// Clear any output that might have been generated (warnings, notices, etc.)
ob_clean();

header('Content-Type: application/json');

// Only allow Admin (Department Head) and IT users
if (!isset($_SESSION['acc_id'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    ob_end_flush();
    exit();
}

// Check user role from database using the new role system
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

// Log for debugging
error_log("Add Account - User ID: " . $_SESSION['acc_id'] . ", Role ID: " . ($user['role_id'] ?? 'null') . ", Role Name: " . ($user['role_name'] ?? 'null'));

if (!$user || !in_array($user['role_id'], [1, 2, 3])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin, Admin Support, or Moderator access required. Current role: ' . ($user['role_name'] ?? 'null')]);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit();
}

try {
    // Log received data for debugging
    error_log("Add Account - Received POST data: " . json_encode($_POST));
    error_log("Add Account - POST role_ids: " . (isset($_POST['role_ids']) ? json_encode($_POST['role_ids']) : 'NOT SET'));
    error_log("Add Account - POST role_id: " . (isset($_POST['role_id']) ? json_encode($_POST['role_id']) : 'NOT SET'));
    
    // Test database connection
    if (!$conn) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        ob_end_flush();
        exit();
    }
    
    // Test database query
    $testQuery = $conn->query("SELECT 1");
    if (!$testQuery) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database query failed: ' . $conn->error]);
        ob_end_flush();
        exit();
    }
    
    // Get form data
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $minitial = trim($_POST['minitial'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $acc_user = trim($_POST['acc_user'] ?? '');
    $acc_email = trim($_POST['acc_email'] ?? '');
    
    // Handle multiple role_ids (can be array or single value for backward compatibility)
    $role_ids = [];
    
    // Debug: Log what we received
    error_log("Add Account - Received role_ids: " . json_encode($_POST['role_ids'] ?? 'not set'));
    error_log("Add Account - Received role_id: " . json_encode($_POST['role_id'] ?? 'not set'));
    error_log("Add Account - Full POST data keys: " . json_encode(array_keys($_POST)));
    
    // Check for role_ids array (from checkboxes with name="role_ids[]")
    // PHP automatically converts role_ids[] to $_POST['role_ids'] as an array
    if (isset($_POST['role_ids']) && is_array($_POST['role_ids'])) {
        // Multiple roles from checkboxes
        $role_ids = array_map('intval', $_POST['role_ids']);
        $role_ids = array_filter($role_ids); // Remove empty/zero values
        $role_ids = array_values($role_ids); // Re-index array
        error_log("Add Account - Processed role_ids array: " . json_encode($role_ids));
    } elseif (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
        // Single role for backward compatibility
        $role_ids = [(int)$_POST['role_id']];
        error_log("Add Account - Processed single role_id: " . json_encode($role_ids));
    } else {
        // If still empty, this is an error - but don't fail yet, log it
        error_log("Add Account - WARNING: No role_ids found in POST data");
        error_log("Add Account - Available POST keys: " . implode(', ', array_keys($_POST)));
        
        // Check if there's a raw input we can parse
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            error_log("Add Account - Raw input (first 500 chars): " . substr($rawInput, 0, 500));
            // Try to parse as URL-encoded data
            parse_str($rawInput, $parsed);
            if (isset($parsed['role_ids']) && is_array($parsed['role_ids'])) {
                $role_ids = array_map('intval', $parsed['role_ids']);
                $role_ids = array_filter($role_ids);
                $role_ids = array_values($role_ids);
                error_log("Add Account - Parsed role_ids from raw input: " . json_encode($role_ids));
            }
        }
    }
    
    // FALLBACK: If no roles were selected, default to Instructor (role_id = 4)
    // This ensures every account has at least one role, similar to admin_support behavior
    if (empty($role_ids)) {
        error_log("Add Account - WARNING: No roles selected, defaulting to Instructor (role_id = 4)");
        $role_ids = [4]; // Default to Instructor role
    }
    
    $acc_pass = 'evsu-occ'; // Always use default password
    $acc_status = 'Pending'; // Set to Pending to require OTP verification
    $inst_status = trim($_POST['inst_status'] ?? 'Regular'); // Default to Regular
    
    // Get workload hours from form (manual input) - MUST be read first
    $administration_hours = (int)($_POST['administration_hours'] ?? 0);
    $instruction_hours = (int)($_POST['instruction_hours'] ?? 0);
    
    // Handle rank and designation based on roles and employment status
    // Part-Time users cannot have designation - force to 'None'
    // If user has Moderator role (3), use Moderator logic; otherwise use default
    // Note: Part-Time instructors can have a rank (per campus policy)
    $hasModeratorRole = in_array(3, $role_ids);
    
    // Part-Time users cannot have designation
    if ($inst_status === 'Part-Time') {
        $designation = 'None'; // Force to None for Part-Time
        $rank = trim($_POST['rank'] ?? ''); // Allow rank for Part-Time
    } elseif ($hasModeratorRole) { // Has Moderator role
        $rank = trim($_POST['rank'] ?? ''); // Allow rank for moderators who teach
        $designation = trim($_POST['designation'] ?? 'None'); // Allow designation
    } else { // Admin or Instructor role only
        $rank = trim($_POST['rank'] ?? 'Instructor I'); // Default to Instructor I
        $designation = trim($_POST['designation'] ?? 'None'); // Default to None
    }
    
    // Auto-load all 6 workload fields from designation or rank (if form values are 0)
    // Priority: Designation (if Regular and designation exists) > Rank > Employment Status
    // Part-Time: Only use rank (designation is forced to 'None')
    // Note: Frontend should auto-populate, but this serves as a backend fallback
    $workloadData = null;
    
    if ($inst_status === 'Part-Time') {
        // Part-Time users: Only use rank (designation is always 'None')
        if (!empty($rank)) {
            // Get from workload_policy table
            $stmt = $conn->prepare("
                SELECT administration_hours, instruction_hours, research_hours, extension_hours, production_hours, consultation_hours
                FROM workload_policy 
                WHERE policy_type = 'Rank' 
                AND name = ? 
                LIMIT 1
            ");
            $stmt->bind_param("s", $rank);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $workloadData = $result->fetch_assoc();
                error_log("Add Account - Part-Time: Rank-based workload data from policy table for: " . $rank);
            }
            $stmt->close();
        }
    } else {
        // Regular/Contractual: Check designation first, then rank
        // First priority: Check designation if it exists and is not 'None' (for Regular users)
        if (!empty($designation) && $designation !== 'None' && $inst_status === 'Regular') {
            // Query workload_policy table for designation's all 6 workload fields
            $stmt = $conn->prepare("
                SELECT administration_hours, instruction_hours, research_hours, extension_hours, production_hours, consultation_hours
                FROM workload_policy 
                WHERE policy_type = 'Designation' 
                AND name = ? 
                LIMIT 1
            ");
            $stmt->bind_param("s", $designation);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $workloadData = $result->fetch_assoc();
                error_log("Add Account - Designation-based workload data found for: " . $designation);
            }
            $stmt->close();
        }
        
        // Second priority: Check rank if designation didn't provide data or designation is 'None'
        if (!$workloadData && !empty($rank)) {
            // First try to get from workload_policy table
            $stmt = $conn->prepare("
                SELECT administration_hours, instruction_hours, research_hours, extension_hours, production_hours, consultation_hours
                FROM workload_policy 
                WHERE policy_type = 'Rank' 
                AND name = ? 
                LIMIT 1
            ");
            $stmt->bind_param("s", $rank);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $workloadData = $result->fetch_assoc();
                error_log("Add Account - Rank-based workload data from policy table for: " . $rank);
            } else {
                // Fallback to hardcoded instruction hours only (for backward compatibility)
                // Other fields will remain 0 if not in policy table
                $workloadData = ['instruction_hours' => 0];
                switch ($rank) {
                    case 'University Professor':
                        $workloadData['instruction_hours'] = 6;
                        break;
                    case 'Professor I':
                    case 'Professor II':
                    case 'Professor III':
                    case 'Professor IV':
                    case 'Professor V':
                    case 'Professor VI':
                        $workloadData['instruction_hours'] = 9;
                        break;
                    case 'Associate Professor I':
                    case 'Associate Professor II':
                    case 'Associate Professor III':
                    case 'Associate Professor IV':
                    case 'Associate Professor V':
                        $workloadData['instruction_hours'] = 12;
                        break;
                    case 'Assistant Professor I':
                    case 'Assistant Professor II':
                    case 'Assistant Professor III':
                    case 'Assistant Professor IV':
                        $workloadData['instruction_hours'] = 15;
                        break;
                    case 'Instructor I':
                    case 'Instructor II':
                    case 'Instructor III':
                        $workloadData['instruction_hours'] = 18;
                        break;
                }
                if ($workloadData['instruction_hours'] > 0) {
                    error_log("Add Account - Rank-based instruction hours from hardcoded values: " . $workloadData['instruction_hours'] . " for rank: " . $rank);
                }
            }
            $stmt->close();
        }
    }
    
    // Apply workload data if found and form values are 0 (fallback only)
    // This applies to both Part-Time and Regular/Contractual
    if ($workloadData) {
        if ($instruction_hours === 0 && isset($workloadData['instruction_hours'])) {
            $instruction_hours = (int)($workloadData['instruction_hours'] ?? 0);
        }
        if ($administration_hours === 0 && isset($workloadData['administration_hours'])) {
            $administration_hours = (int)($workloadData['administration_hours'] ?? 0);
        }
        if ($research_hours === 0 && isset($workloadData['research_hours'])) {
            $research_hours = (int)($workloadData['research_hours'] ?? 0);
        }
        if ($extension_hours === 0 && isset($workloadData['extension_hours'])) {
            $extension_hours = (int)($workloadData['extension_hours'] ?? 0);
        }
        if ($instructional_functions_hours === 0 && isset($workloadData['production_hours'])) {
            // Map production_hours to instructional_functions_hours
            $instructional_functions_hours = (int)($workloadData['production_hours'] ?? 0);
        }
        if ($consultation_hours === 0 && isset($workloadData['consultation_hours'])) {
            $consultation_hours = (int)($workloadData['consultation_hours'] ?? 0);
        }
    }
    
    // Set instruction hours (workload limit) based on employment status if not provided and no rank-based value set
    // Regular: 40 hours, Part-Time: 20 hours, Contractual: 15 hours (adjust as needed)
    // Only apply defaults if instruction_hours is still 0 or empty
    // IMPORTANT: Use the actual form value ($instruction_hours) for database insertion
    if ($instruction_hours === 0) {
        switch ($inst_status) {
            case 'Regular':
                $instruction_hours = 40; // Full-time regular faculty
                break;
            case 'Part-Time':
                $instruction_hours = 20; // Part-time faculty (can be manually adjusted)
                break;
            case 'Contractual':
                $instruction_hours = 15; // Contractual faculty (adjust as per policy)
                break;
            default:
                $instruction_hours = 40; // Default to Regular hours
        }
    }
    // Get all workload hours from form (manual input) - MUST be read before auto-load logic
    $research_hours = (int)($_POST['research_hours'] ?? 0);
    $extension_hours = (int)($_POST['extension_hours'] ?? 0);
    $instructional_functions_hours = (int)($_POST['instructional_functions_hours'] ?? 0);
    $consultation_hours = (int)($_POST['consultation_hours'] ?? 0);
    
    $inst_phone = trim($_POST['inst_phone'] ?? '');
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null; // Program/Course ID (optional)
    $workload_policy = (int)($_POST['workload_policy'] ?? 1); // Default workload policy
    $sy_id = (int)($_POST['sy_id'] ?? 1);
    
    // Get department from form (required field)
    $dept_id = null;
    if (!empty($_POST['dept_id']) && is_numeric($_POST['dept_id'])) {
        $dept_id = (int)$_POST['dept_id'];
        // Validate department exists
        $stmt = $conn->prepare("SELECT dept_id FROM department WHERE dept_id = ?");
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Selected department does not exist.']);
            ob_end_flush();
            exit();
        }
        $stmt->close();
    } else {
        // Use current user's department as default if not provided in form
        $stmt = $conn->prepare("SELECT dept_id FROM account WHERE acc_id = ?");
        $stmt->bind_param("i", $_SESSION['acc_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentUser = $result->fetch_assoc();
        $stmt->close();
        $dept_id = $currentUser['dept_id'] ?? 1; // Default to department 1 if not found
    }
    
    $created_by = $_SESSION['acc_id'];
    
    // Log processed data
    error_log("Add Account - Processed data: fname=$fname, lname=$lname, acc_user=$acc_user, acc_email=$acc_email, role_ids=" . json_encode($role_ids) . ", dept_id=$dept_id, inst_status=$inst_status, instruction_hours=$instruction_hours, rank=$rank, designation=$designation");

    // Validation
    if (empty($fname) || empty($lname) || empty($acc_user) || empty($acc_email) || empty($role_ids) || empty($dept_id)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled. Please ensure at least one role is selected and department is selected.']);
        ob_end_flush();
        exit();
    }

    // Validate email format
    if (!filter_var($acc_email, FILTER_VALIDATE_EMAIL)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        ob_end_flush();
        exit();
    }

    // No need to validate provided password length since we enforce a default temporary password

    // Validate roles (allow Admin, Moderator, or User/Instructor roles)
    // All these roles can be instructors
    $validRoleIds = [2, 3, 4];
    foreach ($role_ids as $rid) {
        if (!in_array($rid, $validRoleIds)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid role selected: ' . $rid]);
            ob_end_flush();
            exit();
        }
    }

    // Validate instructor status
    $validStatuses = ['Regular', 'Part-Time', 'Contractual'];
    if (!in_array($inst_status, $validStatuses)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid instructor status']);
        ob_end_flush();
        exit();
    }

    // Validate account status
    $validAccountStatuses = ['Active', 'Inactive', 'Pending'];
    if (!in_array($acc_status, $validAccountStatuses)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid account status']);
        ob_end_flush();
        exit();
    }

    // Check if account_departments table exists (for multi-department support)
    $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
    $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
    
    $existingAccountId = null;
    $isAddingDepartment = false;
    
    // Check if username already exists - if so, use that account and add department
    $stmt = $conn->prepare("SELECT acc_id, fname, lname FROM account WHERE acc_user = ?");
    $stmt->bind_param("s", $acc_user);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existingAccount = $result->fetch_assoc();
        $existingAccountId = $existingAccount['acc_id'];
        $isAddingDepartment = true;
        error_log("Add Account - Username '$acc_user' exists. Will add department to existing account ID: $existingAccountId");
    }
    $stmt->close();

    // Check if email already exists - if so, use that account and add department
    if (!$existingAccountId) {
        $stmt = $conn->prepare("SELECT acc_id, acc_email, fname, lname FROM account WHERE acc_email = ?");
        $stmt->bind_param("s", $acc_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $existingAccount = $result->fetch_assoc();
            $existingAccountId = $existingAccount['acc_id'];
            $isAddingDepartment = true;
            error_log("Add Account - Email '$acc_email' exists. Will add department to existing account ID: $existingAccountId");
        }
        $stmt->close();
    }

    // If account exists (by username or email), check if it already has this department
    if ($existingAccountId && $hasAccountDepartmentsTable) {
        // Check if account already has this department association
        $stmt = $conn->prepare("SELECT id FROM account_departments WHERE acc_id = ? AND dept_id = ?");
        $stmt->bind_param("ii", $existingAccountId, $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'This account is already associated with this department.'
            ]);
            ob_end_flush();
            exit();
        }
        $stmt->close();
    }

    // Check for duplicate name + role_id combination in the SAME department
    // Only check if we're NOT adding to existing account (to prevent duplicates in same dept)
    if (!$isAddingDepartment) {
        $checkRoleIds = empty($role_ids) ? [4] : $role_ids;
        
        foreach ($checkRoleIds as $checkRoleId) {
            // Check if name + role exists in this department (using account_departments if available)
            if ($hasAccountDepartmentsTable) {
                $duplicateCheckQuery = "
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
                $duplicateCheckQuery = "
                    SELECT a.acc_id, a.fname, a.lname, a.minitial, a.suffix, r.role_name, r.id as role_id
                    FROM account a
                    JOIN user_roles ur ON a.acc_id = ur.acc_id
                    JOIN roles r ON ur.role_id = r.id
                    WHERE LOWER(TRIM(a.fname)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(a.lname)) = LOWER(TRIM(?))
                    AND ur.role_id = ?
                    AND a.dept_id = ?
                ";
            }
            
            $params = [$fname, $lname, $checkRoleId, $dept_id];
            $types = 'ssii';
            
            // Include middle initial in check if provided
            if (!empty($minitial)) {
                $duplicateCheckQuery .= " AND LOWER(TRIM(a.minitial)) = LOWER(TRIM(?))";
                $params[] = $minitial;
                $types .= 's';
            } else {
                $duplicateCheckQuery .= " AND (a.minitial IS NULL OR a.minitial = '')";
            }
            
            $stmt = $conn->prepare($duplicateCheckQuery);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $existingAccount = $result->fetch_assoc();
                    $existingName = trim($existingAccount['fname'] . ' ' . (!empty($existingAccount['minitial']) ? $existingAccount['minitial'] . '. ' : '') . $existingAccount['lname'] . (!empty($existingAccount['suffix']) ? ' ' . $existingAccount['suffix'] : ''));
                    $roleName = $existingAccount['role_name'];
                    $roleId = $existingAccount['role_id'];
                    $stmt->close();
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'name_exists' => true,
                        'existing_name' => $existingName,
                        'duplicate_roles' => [
                            [
                                'role_id' => $roleId,
                                'role_name' => $roleName
                            ]
                        ],
                        'message' => "A user with the name \"$existingName\" already exists with the role: $roleName in this department. Cannot create duplicate in the same department."
                    ]);
                    ob_end_flush();
                    exit();
                }
                $stmt->close();
            }
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
    // If account already exists (by username/email), use it; otherwise create new account
    if ($existingAccountId) {
        // Use existing account
        $acc_id = $existingAccountId;
        error_log("Add Account - Using existing account ID: $acc_id, adding department: $dept_id");
    } else {
        // Create new account
        // Hash password
        $hashed_password = password_hash($acc_pass, PASSWORD_DEFAULT);

        // Insert into account table (without role_id since we use user_roles table)
        // No verification_token needed - we use OTP system instead
        // Set dept_id as primary department (for backward compatibility)
        $stmt = $conn->prepare("
            INSERT INTO account (fname, lname, minitial, suffix, dept_id, acc_user, acc_pass, acc_email, acc_status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssissssi", $fname, $lname, $minitial, $suffix, $dept_id, $acc_user, $hashed_password, $acc_email, $acc_status, $created_by);
        $stmt->execute();
        $acc_id = $conn->insert_id;
        $stmt->close();
        error_log("Add Account - Created new account ID: $acc_id");
    }
    
    // Add department association to account_departments table (if table exists)
    if ($hasAccountDepartmentsTable) {
        // Check if association already exists (shouldn't happen due to earlier check, but double-check)
        $stmt = $conn->prepare("SELECT id FROM account_departments WHERE acc_id = ? AND dept_id = ?");
        $stmt->bind_param("ii", $acc_id, $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            // Add department association
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO account_departments (acc_id, dept_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $acc_id, $dept_id);
            $stmt->execute();
            error_log("Add Account - Added department association: acc_id=$acc_id, dept_id=$dept_id");
        }
        $stmt->close();
    }

    // Handle instructor table
    // Check if instructor record already exists for this account
    $stmt = $conn->prepare("SELECT inst_id FROM instructor WHERE inst_user = ?");
    $stmt->bind_param("s", $acc_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingInstructor = $result->fetch_assoc();
    $stmt->close();
    
    if ($existingInstructor) {
        // Instructor record exists, use it
        $inst_id = $existingInstructor['inst_id'];
        error_log("Add Account - Using existing instructor ID: $inst_id");
        
        // Update instructor's dept_id if it's different (set to new department as primary)
        // Note: instructor.dept_id is kept for backward compatibility, but account_departments is the source of truth
        if ($existingAccountId) {
            // Only update if adding to existing account (don't overwrite if creating new)
            $updateStmt = $conn->prepare("UPDATE instructor SET dept_id = ? WHERE inst_id = ?");
            $updateStmt->bind_param("ii", $dept_id, $inst_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
    } else {
        // Create new instructor record
        // Check if program_id column exists in instructor table
        $checkColumn = $conn->query("SHOW COLUMNS FROM instructor LIKE 'program_id'");
        $hasProgramId = $checkColumn && $checkColumn->num_rows > 0;
        
        if ($hasProgramId) {
            // Insert with program_id and workload hours if column exists
            $stmt = $conn->prepare("
                INSERT INTO instructor (inst_user, inst_lname, inst_fname, inst_mname, inst_suffix, inst_status, instruction_hours, administration_hours, research_hours, extension_hours, instructional_functions_hours, consultation_hours, dept_id, rank, designation, inst_phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssssiiiiiiisss", $acc_user, $lname, $fname, $minitial, $suffix, $inst_status, $instruction_hours, $administration_hours, $research_hours, $extension_hours, $instructional_functions_hours, $consultation_hours, $dept_id, $rank, $designation, $inst_phone);
        } else {
            // Insert without program_id but with workload hours if column doesn't exist
            $stmt = $conn->prepare("
                INSERT INTO instructor (inst_user, inst_lname, inst_fname, inst_mname, inst_suffix, inst_status, instruction_hours, administration_hours, research_hours, extension_hours, instructional_functions_hours, consultation_hours, dept_id, rank, designation, inst_phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssssiiiiiiisss", $acc_user, $lname, $fname, $minitial, $suffix, $inst_status, $instruction_hours, $administration_hours, $research_hours, $extension_hours, $instructional_functions_hours, $consultation_hours, $dept_id, $rank, $designation, $inst_phone);
        }
        $stmt->execute();
        $inst_id = $conn->insert_id;
        
        // Set program_id separately if needed (only if column exists and value provided)
        if ($hasProgramId && $program_id) {
            $updateStmt = $conn->prepare("UPDATE instructor SET program_id = ? WHERE inst_id = ?");
            $updateStmt->bind_param("ii", $program_id, $inst_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
        $stmt->close();
        error_log("Add Account - Created new instructor ID: $inst_id");
    }

    if (!empty($inst_id) && ida_appointments_table_exists($conn)) {
        ida_sync_primary_appointment_from_instructor($conn, (int) $inst_id);
    }

    // Assign roles to user_roles table (support multiple roles)
    // Only add roles that don't already exist for this account
    if (empty($role_ids)) {
        throw new Exception("No roles selected. At least one role must be assigned.");
    }
    
    error_log("Add Account - About to insert " . count($role_ids) . " roles for acc_id: $acc_id");
    
    // Check which roles already exist
    $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE acc_id = ?");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingRoleIds = [];
    while ($row = $result->fetch_assoc()) {
        $existingRoleIds[] = (int)$row['role_id'];
    }
    $stmt->close();
    
    // Only insert roles that don't already exist
    $newRoleIds = array_diff($role_ids, $existingRoleIds);
    if (!empty($newRoleIds)) {
        $stmt = $conn->prepare("INSERT INTO user_roles (acc_id, role_id) VALUES (?, ?)");
        foreach ($newRoleIds as $rid) {
            $stmt->bind_param("ii", $acc_id, $rid);
            if (!$stmt->execute()) {
                error_log("Add Account - ERROR inserting role_id $rid for acc_id $acc_id: " . $stmt->error);
                throw new Exception("Failed to assign role: " . $stmt->error);
            }
            error_log("Add Account - Successfully inserted role_id $rid for acc_id $acc_id");
        }
        $stmt->close();
        error_log("Add Account - Added " . count($newRoleIds) . " new roles. Total roles for account: " . count($role_ids));
    } else {
        error_log("Add Account - All roles already assigned to account. No new roles to add.");
    }

        // Assign workload policy if specified and school year exists
        if ($workload_policy > 0) {
            // Check if the school year exists
            $stmt = $conn->prepare("SELECT sy_id FROM schoolyear WHERE sy_id = ?");
            $stmt->bind_param("i", $sy_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows > 0) {
                // School year exists, insert workload
                $stmt = $conn->prepare("
                    INSERT INTO faculty_workload (inst_id, sy_id, total_minutes, overload_flag, underload_flag) 
                    VALUES (?, ?, 0, 'No', 'No')
                ");
                $stmt->bind_param("ii", $inst_id, $sy_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // No school year exists, skip workload insertion
                error_log("Add Account - Skipping faculty_workload insertion: School year ID $sy_id does not exist");
            }
        }

        // Commit transaction
        $conn->commit();

        // Send OTP verification email only for new accounts (not when adding department to existing account)
        $emailSent = false;
        if (!$isAddingDepartment) {
            require_once '../../includes/auth/verification/send_otp_email.php';
            
            // Get department name for email
            $dept_stmt = $conn->prepare("SELECT dept_name FROM department WHERE dept_id = ?");
            $dept_stmt->bind_param("i", $dept_id);
            $dept_stmt->execute();
            $dept_result = $dept_stmt->get_result();
            $dept_name = $dept_result->fetch_assoc()['dept_name'] ?? 'Unknown Department';
            $dept_stmt->close();
            
            // Determine role names for email (show all roles)
            $role_names = [
                2 => 'Admin (Department Head)',
                3 => 'Moderator (Faculty Staff)',
                4 => 'User (Instructor)'
            ];
            $roleNameList = array_map(function($rid) use ($role_names) {
                return $role_names[$rid] ?? 'User';
            }, $role_ids);
            $role_name = implode(', ', $roleNameList);
            
            // Send OTP email with username and password
            $emailSent = sendVerificationOTPEmail($acc_id, $acc_email, $fname, $lname, $dept_name, $role_name, $acc_user, $acc_pass);
        }

        // Log the action
        if ($isAddingDepartment) {
            $action = "Added department association to existing account: $acc_user ($fname $lname) - Department ID: $dept_id";
        } else {
            $action = "Added new instructor account: $acc_user ($fname $lname)";
        }
        $details = json_encode([
            'account_id' => $acc_id,
            'instructor_id' => $inst_id,
            'username' => $acc_user,
            'email' => $acc_email,
            'department_id' => $dept_id,
            'instructor_status' => $inst_status,
            'academic_rank' => $rank,
            'designation' => $designation,
            'role_ids' => $role_ids,
            'account_status' => $acc_status,
            'workload_policy' => $workload_policy,
            'school_year' => $sy_id,
            'is_adding_department' => $isAddingDepartment,
            'new_account' => !$isAddingDepartment
        ]);
        $stmt = $conn->prepare("INSERT INTO audit_log (acc_id, action, log_date, details) VALUES (?, ?, NOW(), ?)");
        $stmt->bind_param("iss", $created_by, $action, $details);
        $stmt->execute();
        $stmt->close();

        if ($isAddingDepartment) {
            $message = 'Department association added successfully to existing account';
        } else {
            $message = 'Account created successfully';
            if ($emailSent) {
                $message .= ' and verification email sent';
            } else {
                $message .= ' but email could not be sent';
            }
        }

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'account_id' => $acc_id,
            'instructor_id' => $inst_id,
            'username' => $acc_user,
            'email_sent' => $emailSent,
            'verification_required' => $isAddingDepartment ? false : true, // OTP only for new accounts
            'account_status' => $acc_status,
            'instructor_status' => $inst_status,
            'academic_rank' => $rank,
            'designation' => $designation,
            'department_added' => $isAddingDepartment,
            'new_account' => !$isAddingDepartment
        ]);
        ob_end_flush();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Add Account Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while creating account: ' . $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    ob_end_flush();
} catch (Throwable $e) {
    // Log the error for debugging
    error_log("Add Account Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while creating account: ' . $e->getMessage()
    ]);
    ob_end_flush();
} finally {
    // Restore error handler
    restore_error_handler();
}
?>
