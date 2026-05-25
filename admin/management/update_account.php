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
  error_log("PHP Error in update_account.php: [$errno] $errstr in $errfile on line $errline");
  return true; // Suppress default error handler
});

session_start();

// Include database config
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

// Clear any output that might have been generated (warnings, notices, etc.)
ob_clean();

header('Content-Type: application/json');

// Allow Admin Support, Admin, and Moderator to update personal info; restrict by department for non-IT
$roleId = (int)($_SESSION['role_id'] ?? 0);
if (!isset($_SESSION['acc_id']) || !in_array($roleId, [1, 2, 3])) {
  ob_clean();
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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

$accId = (int)($_POST['acc_id'] ?? 0);
$fname = trim($_POST['fname'] ?? '');
$lname = trim($_POST['lname'] ?? '');
$minitial = trim($_POST['minitial'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');
$acc_user = trim($_POST['acc_user'] ?? '');
$acc_email = trim($_POST['acc_email'] ?? '');
$dept_id = (int)($_POST['dept_id'] ?? 0);
$inst_status = trim($_POST['inst_status'] ?? '');

// Get workload hours from form - MUST be read first
$administration_hours = (int)($_POST['administration_hours'] ?? 0);
$instruction_hours = (int)($_POST['instruction_hours'] ?? 0);
$research_hours = (int)($_POST['research_hours'] ?? 0);
$extension_hours = (int)($_POST['extension_hours'] ?? 0);
$instructional_functions_hours = (int)($_POST['instructional_functions_hours'] ?? 0);
$consultation_hours = (int)($_POST['consultation_hours'] ?? 0);

// Note: Part-Time instructors can have a rank (per campus policy)
// Part-Time users cannot have designation - force to 'None'
if ($inst_status === 'Part-Time') {
    $designation = 'None'; // Force to None for Part-Time
    $rank = trim($_POST['rank'] ?? '');
} else {
    $rank = trim($_POST['rank'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
}

// Debug logging - log all POST data related to inst_status
error_log("Update Account - ========== START UPDATE ==========");
error_log("Update Account - Raw POST inst_status: " . var_export($_POST['inst_status'] ?? 'NOT SET', true));
error_log("Update Account - Trimmed POST inst_status: " . var_export($inst_status, true));
error_log("Update Account - Rank: " . var_export($rank, true));
error_log("Update Account - Instruction hours from form: " . $instruction_hours);
error_log("Update Account - All POST keys: " . implode(', ', array_keys($_POST)));
error_log("Update Account - Full POST data: " . json_encode($_POST));

// Validate inst_status - must be one of the valid ENUM values
$validStatuses = ['Regular', 'Part-Time', 'Contractual'];
if (!empty($inst_status) && !in_array($inst_status, $validStatuses)) {
  error_log("Update Account - Invalid inst_status provided: " . $inst_status);
  ob_clean();
  echo json_encode(['success' => false, 'message' => 'Invalid employment status: ' . $inst_status]);
  ob_end_flush();
  exit();
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
            error_log("Update Account - Part-Time: Rank-based workload data from policy table for: " . $rank);
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
            error_log("Update Account - Designation-based workload data found for: " . $designation);
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
            error_log("Update Account - Rank-based workload data from policy table for: " . $rank);
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
                error_log("Update Account - Rank-based instruction hours from hardcoded values: " . $workloadData['instruction_hours'] . " for rank: " . $rank);
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

// Set instruction hours limit based on employment status ONLY if form value is 0 or empty and no rank-based value set
// IMPORTANT: Use the actual form value ($instruction_hours) for database updates
// Only apply defaults if user didn't provide a value (0 or empty) and no rank was applied
$instruction_hours_limit = $instruction_hours; // Use the form value as the limit
if ($instruction_hours_limit === 0 && !empty($inst_status)) {
    switch ($inst_status) {
        case 'Regular':
            $instruction_hours_limit = 40; // Full-time regular faculty
            break;
        case 'Part-Time':
            $instruction_hours_limit = 20; // Part-time faculty (can be manually adjusted)
            break;
        case 'Contractual':
            $instruction_hours_limit = 15; // Contractual faculty (adjust as per policy)
            break;
    }
    // Also update $instruction_hours to match the limit when default is applied
    $instruction_hours = $instruction_hours_limit;
    error_log("Update Account - Employment status-based instruction hours set: " . $instruction_hours . " for status: " . $inst_status);
}

$inst_email = trim($_POST['inst_email'] ?? '');
$inst_phone = trim($_POST['inst_phone'] ?? '');
$program_id = (int)($_POST['program_id'] ?? 0);

if ($accId <= 0 || $fname === '' || $lname === '' || $acc_user === '' || $acc_email === '') {
  ob_clean();
  echo json_encode(['success' => false, 'message' => 'Missing required fields']);
  ob_end_flush();
  exit();
}

if (!filter_var($acc_email, FILTER_VALIDATE_EMAIL)) {
  ob_clean();
  echo json_encode(['success' => false, 'message' => 'Invalid email']);
  ob_end_flush();
  exit();
}

try {
  // Ensure updater has access (non-IT limited to same department)
  if ($roleId !== 1) {
    $deptStmt = $conn->prepare('SELECT dept_id FROM account WHERE acc_id = ?');
    $deptStmt->bind_param('i', $accId);
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    $row = $deptRes->fetch_assoc();
    $deptStmt->close();
    if (!$row) { 
      ob_clean();
      echo json_encode(['success'=>false,'message'=>'Account not found']); 
      ob_end_flush();
      exit(); 
    }
    $targetDeptId = (int)$row['dept_id'];
    $actorDeptId = (int)($_SESSION['dept_id'] ?? 0);
    if ($roleId !== 1 && $actorDeptId !== $targetDeptId && $roleId !== 2) {
      http_response_code(403);
      ob_clean();
      echo json_encode(['success'=>false,'message'=>'Forbidden']);
      ob_end_flush();
      exit();
    }
  }

  // Check duplicates for username/email excluding current account
  $stmt = $conn->prepare('SELECT acc_id FROM account WHERE (acc_user = ? OR acc_email = ?) AND acc_id <> ?');
  $stmt->bind_param('ssi', $acc_user, $acc_email, $accId);
  $stmt->execute();
  $dup = $stmt->get_result();
  if ($dup->num_rows > 0) { 
    ob_clean();
    echo json_encode(['success'=>false,'message'=>'Username or email already in use']); 
    ob_end_flush();
    exit(); 
  }
  $stmt->close();

  $conn->begin_transaction();

  // Get the original username BEFORE updating (to find instructor record)
  $originalUserStmt = $conn->prepare('SELECT acc_user FROM account WHERE acc_id = ?');
  $originalUserStmt->bind_param('i', $accId);
  $originalUserStmt->execute();
  $originalUserResult = $originalUserStmt->get_result();
  $originalUserRow = $originalUserResult->fetch_assoc();
  $originalUserStmt->close();
  
  if (!$originalUserRow) {
    $conn->rollback();
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    ob_end_flush();
    exit();
  }
  
  $original_inst_user = $originalUserRow['acc_user'];
  
  // Check if instructor record exists and get inst_id and current inst_status
  $checkStmt = $conn->prepare('SELECT inst_id, inst_status FROM instructor WHERE inst_user = ?');
  $checkStmt->bind_param('s', $original_inst_user);
  $checkStmt->execute();
  $instructorResult = $checkStmt->get_result();
  $instructorRow = $instructorResult->fetch_assoc();
  $instructorExists = $instructorResult->num_rows > 0;
  $inst_id = $instructorRow ? (int)$instructorRow['inst_id'] : null;
  $current_inst_status = $instructorRow ? (trim($instructorRow['inst_status'] ?? '')) : '';
  $checkStmt->close();
  
  // Determine the final inst_status value to use
  // Priority: 1) POST value if valid, 2) existing DB value if POST is empty, 3) empty
  $final_inst_status = '';
  $shouldUpdateStatus = false;
  
  // CRITICAL: If POST has a valid status, ALWAYS use it and update
  if (!empty($inst_status) && in_array($inst_status, $validStatuses)) {
    $final_inst_status = $inst_status;
    $shouldUpdateStatus = true;
        // Calculate instruction hours limit for POST value
    switch ($final_inst_status) {
      case 'Regular':
            $instruction_hours_limit = 40;
        break;
      case 'Part-Time':
            $instruction_hours_limit = 20;
        break;
      case 'Contractual':
            $instruction_hours_limit = 15;
        break;
    }
        error_log("Update Account - Using POST inst_status: " . $final_inst_status . ", instruction_hours: " . $instruction_hours_limit);
  } else if (empty($inst_status) && !empty($current_inst_status)) {
    // POST is empty but DB has a value - preserve it
    $final_inst_status = $current_inst_status;
    $shouldUpdateStatus = true;
    error_log("Update Account - Preserving existing inst_status: " . $final_inst_status);
    // Recalculate instruction hours limit based on preserved status
    switch ($final_inst_status) {
      case 'Regular':
        $instruction_hours_limit = 40;
        break;
      case 'Part-Time':
        $instruction_hours_limit = 20;
        break;
      case 'Contractual':
        $instruction_hours_limit = 15;
        break;
      default:
        $instruction_hours_limit = null;
    }
  } else {
    // Both POST and DB are empty - don't update status
    error_log("Update Account - No inst_status to update. POST: " . var_export($inst_status, true) . ", Current: " . var_export($current_inst_status, true));
  }
  
  // Check if new username already exists in instructor table (for different instructor)
  if ($acc_user !== $original_inst_user) {
    $dupCheckStmt = $conn->prepare('SELECT inst_id FROM instructor WHERE inst_user = ? AND inst_id != ?');
    if ($inst_id) {
      $dupCheckStmt->bind_param('si', $acc_user, $inst_id);
    } else {
      // If no instructor record exists yet, just check if username exists
      $dupCheckStmt = $conn->prepare('SELECT inst_id FROM instructor WHERE inst_user = ?');
      $dupCheckStmt->bind_param('s', $acc_user);
    }
    $dupCheckStmt->execute();
    $dupResult = $dupCheckStmt->get_result();
    if ($dupResult->num_rows > 0) {
      $dupCheckStmt->close();
      $conn->rollback();
      ob_clean();
      echo json_encode(['success' => false, 'message' => 'Username already exists in instructor table']);
      ob_end_flush();
      exit();
    }
    $dupCheckStmt->close();
  }

  // Update account table
  $stmt = $conn->prepare('UPDATE account SET fname = ?, lname = ?, minitial = ?, suffix = ?, acc_user = ?, acc_email = ? WHERE acc_id = ?');
  $stmt->bind_param('ssssssi', $fname, $lname, $minitial, $suffix, $acc_user, $acc_email, $accId);
  $stmt->execute();
  $stmt->close();

  // Update instructor table if exists
  if ($instructorExists && $inst_id) {
    // Check if program_id column exists in instructor table
    $checkColumn = $conn->query("SHOW COLUMNS FROM instructor LIKE 'program_id'");
    $hasProgramId = $checkColumn && $checkColumn->num_rows > 0;
    
    // Check if workload hours columns exist
    $checkWorkloadColumns = $conn->query("SHOW COLUMNS FROM instructor LIKE 'administration_hours'");
    $hasWorkloadHours = $checkWorkloadColumns && $checkWorkloadColumns->num_rows > 0;
    
    // Use the final_inst_status we determined earlier
    // If we have a valid status (either from POST or preserved from DB), update it
    error_log("Update Account - About to update instructor. Final inst_status: " . var_export($final_inst_status, true) . ", shouldUpdateStatus: " . var_export($shouldUpdateStatus, true) . ", inst_id: " . $inst_id);
    error_log("Update Account - POST inst_status: " . var_export($inst_status, true) . ", Current DB inst_status: " . var_export($current_inst_status, true));
    
    // CRITICAL FIX: If POST has a valid inst_status, ALWAYS update it, even if logic above didn't set it
    if (!empty($inst_status) && in_array($inst_status, $validStatuses)) {
      $final_inst_status = $inst_status;
      $shouldUpdateStatus = true;
      // Ensure instruction hours limit is set
      if ($instruction_hours_limit === null) {
        switch ($final_inst_status) {
          case 'Regular':
            $instruction_hours_limit = 40;
            break;
          case 'Part-Time':
            $instruction_hours_limit = 20;
            break;
          case 'Contractual':
            $instruction_hours_limit = 15;
            break;
        }
      }
      error_log("Update Account - FORCED UPDATE: POST inst_status provided, forcing update to: " . $final_inst_status);
    }
    
    // Always update inst_status if we have a valid value
    if ($shouldUpdateStatus && !empty($final_inst_status)) {
      error_log("Update Account - EXECUTING UPDATE with inst_status: " . $final_inst_status . ", instruction_hours: " . var_export($instruction_hours, true));
      // Update with inst_status and instruction_hours
      // Always update instruction_hours (it will have either form value or default)
      if ($instruction_hours > 0) {
        if ($hasWorkloadHours && $hasProgramId) {
          // Update with workload hours and program_id
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, instruction_hours = ?, administration_hours = ?, research_hours = ?, extension_hours = ?, instructional_functions_hours = ?, consultation_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
          // 19 placeholders: s(6) + i(7) + s(4) + i(2) = 19 total
          $stmt->bind_param('ssssssiiiiiiissssii', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $instruction_hours, $administration_hours, $research_hours, $extension_hours, $instructional_functions_hours, $consultation_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
        } else if ($hasWorkloadHours) {
          // Update with workload hours but no program_id
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, instruction_hours = ?, administration_hours = ?, research_hours = ?, extension_hours = ?, instructional_functions_hours = ?, consultation_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
          $stmt->bind_param('ssssssiiiiiiiisssss', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $instruction_hours, $administration_hours, $research_hours, $extension_hours, $instructional_functions_hours, $consultation_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
        } else if ($hasProgramId) {
          // Update without workload hours but with program_id
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, instruction_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
          $stmt->bind_param('sssssiisssssii', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $instruction_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
        } else {
          // Update without workload hours and without program_id
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, instruction_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
          $stmt->bind_param('sssssiisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $instruction_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
        }
      } else {
        // Update without changing instruction hours limit (but still update status)
        if ($hasWorkloadHours && $hasProgramId) {
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, administration_hours = ?, research_hours = ?, extension_hours = ?, instructional_functions_hours = ?, consultation_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
          $stmt->bind_param('ssssssiiiiiisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $administration_hours, $research_hours, $extension_hours, $instructional_functions_hours, $consultation_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
        } else if ($hasWorkloadHours) {
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, administration_hours = ?, research_hours = ?, extension_hours = ?, instructional_functions_hours = ?, consultation_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
          $stmt->bind_param('ssssssiiiiiisssss', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $administration_hours, $research_hours, $extension_hours, $instructional_functions_hours, $consultation_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
        } else if ($hasProgramId) {
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
          $stmt->bind_param('sssssisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
        } else {
          $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
          $stmt->bind_param('sssssissssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
        }
      }
    } else {
      // This else block should NOT execute if POST has a valid inst_status (due to force check above)
      // But if it does, log a warning
      if (!empty($inst_status) && in_array($inst_status, $validStatuses)) {
        error_log("Update Account - ERROR: Valid POST inst_status provided but UPDATE not executing! This should not happen!");
        // Force update anyway
        $final_inst_status = $inst_status;
        $shouldUpdateStatus = true;
        if ($instruction_hours_limit === null) {
          switch ($final_inst_status) {
            case 'Regular':
              $instruction_hours_limit = 40;
              break;
            case 'Part-Time':
              $instruction_hours_limit = 20;
              break;
            case 'Contractual':
              $instruction_hours_limit = 15;
              break;
          }
        }
        // Re-execute the UPDATE with status
        if ($instruction_hours > 0) {
          if ($hasProgramId) {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, instruction_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssiisssssii', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $instruction_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
          } else {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, instruction_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssiisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $instruction_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
          }
        } else {
          if ($hasProgramId) {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
          } else {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, inst_status = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssissssi', $acc_user, $fname, $lname, $minitial, $suffix, $final_inst_status, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
          }
        }
      } else {
        // Don't update inst_status if it's empty or invalid - only update other fields (preserve existing inst_status)
        if ($instruction_hours > 0) {
          // Update instruction hours limit without changing status
          if ($hasProgramId) {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, instruction_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssisssssii', $acc_user, $fname, $lname, $minitial, $suffix, $instruction_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
          } else {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, instruction_hours = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $instruction_hours, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
          }
        } else {
          // Update without status or working hours - preserve existing inst_status
          if ($hasProgramId) {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ?, program_id = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssisssssi', $acc_user, $fname, $lname, $minitial, $suffix, $dept_id, $rank, $designation, $inst_email, $inst_phone, $program_id, $inst_id);
          } else {
            $stmt = $conn->prepare('UPDATE instructor SET inst_user = ?, inst_fname = ?, inst_lname = ?, inst_mname = ?, inst_suffix = ?, dept_id = ?, rank = ?, designation = ?, inst_email = ?, inst_phone = ? WHERE inst_id = ?');
            $stmt->bind_param('sssssissssi', $acc_user, $fname, $lname, $minitial, $suffix, $dept_id, $rank, $designation, $inst_email, $inst_phone, $inst_id);
          }
        }
      }
    }
    $stmt->execute();
    if ($stmt->error) {
      error_log("Update Account - SQL Error: " . $stmt->error);
      throw new Exception("SQL Error: " . $stmt->error);
    }
    $affectedRows = $stmt->affected_rows;
    error_log("Update Account - UPDATE executed. Affected rows: " . $affectedRows . ", inst_status: " . var_export($final_inst_status, true));
    
    // Verify the update by querying the database immediately after
    $verifyStmt = $conn->prepare('SELECT inst_status FROM instructor WHERE inst_id = ?');
    $verifyStmt->bind_param('i', $inst_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $verifyRow = $verifyResult->fetch_assoc();
    $verifyStmt->close();
    error_log("Update Account - VERIFIED inst_status in DB after update: " . var_export($verifyRow['inst_status'] ?? 'NULL', true));
    
    $stmt->close();
  } else {
    error_log("Update Account - Skipping inst_status update (empty or invalid). final_inst_status: " . var_export($final_inst_status, true));
  }

  if (!empty($inst_id) && ida_appointments_table_exists($conn)) {
    ida_sync_primary_appointment_from_instructor($conn, (int) $inst_id);
  }

  $conn->commit();

  ob_clean();
  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  ob_end_flush();
  exit();
} catch (Exception $e) {
  if (isset($conn)) {
  $conn->rollback();
  }
  ob_clean();
  header('Content-Type: application/json');
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  ob_end_flush();
  exit();
} catch (Throwable $e) {
  if (isset($conn)) {
    $conn->rollback();
  }
  ob_clean();
  header('Content-Type: application/json');
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
  ob_end_flush();
  exit();
} finally {
  // Restore error handler
  restore_error_handler();
}

