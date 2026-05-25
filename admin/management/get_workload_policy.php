<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role (Admin, Admin Support, or Moderator)
if (!isset($_SESSION['acc_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check user role - allow Admin (role_id 2), Admin Support (role_id 1), and Moderator (role_id 3)
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    $data = [];
    
    // Fetch Ranks (policy_type = 'Rank') with all 6 workload fields
    $rankQuery = "SELECT name, administration_hours, instruction_hours, research_hours, extension_hours, production_hours, consultation_hours FROM workload_policy WHERE policy_type = 'Rank' ORDER BY 
        CASE name
            WHEN 'University Professor' THEN 1
            WHEN 'Professor VI' THEN 2
            WHEN 'Professor V' THEN 3
            WHEN 'Professor IV' THEN 4
            WHEN 'Professor III' THEN 5
            WHEN 'Professor II' THEN 6
            WHEN 'Professor I' THEN 7
            WHEN 'Associate Professor V' THEN 8
            WHEN 'Associate Professor IV' THEN 9
            WHEN 'Associate Professor III' THEN 10
            WHEN 'Associate Professor II' THEN 11
            WHEN 'Associate Professor I' THEN 12
            WHEN 'Assistant Professor IV' THEN 13
            WHEN 'Assistant Professor III' THEN 14
            WHEN 'Assistant Professor II' THEN 15
            WHEN 'Assistant Professor I' THEN 16
            WHEN 'Instructor III' THEN 17
            WHEN 'Instructor II' THEN 18
            WHEN 'Instructor I' THEN 19
            ELSE 99
        END";
    
    $result = $conn->query($rankQuery);
    $ranks = [];
    $rankHours = []; // Map rank name to instruction hours (for backward compatibility)
    $rankWorkloads = []; // Map rank name to all 6 workload fields
    while ($row = $result->fetch_assoc()) {
        $ranks[] = $row['name'];
        $rankHours[$row['name']] = (int)($row['instruction_hours'] ?? 0);
        // Store all 6 workload fields
        $rankWorkloads[$row['name']] = [
            'administration_hours' => (int)($row['administration_hours'] ?? 0),
            'instruction_hours' => (int)($row['instruction_hours'] ?? 0),
            'research_hours' => (int)($row['research_hours'] ?? 0),
            'extension_hours' => (int)($row['extension_hours'] ?? 0),
            'production_hours' => (int)($row['production_hours'] ?? 0),
            'consultation_hours' => (int)($row['consultation_hours'] ?? 0)
        ];
    }
    $data['ranks'] = $ranks;
    $data['rank_hours'] = $rankHours; // Backward compatibility
    $data['rank_workloads'] = $rankWorkloads; // All 6 fields
    
    // Fetch Designations from workload_policy table with all 6 workload fields
    $designationQuery = "SELECT name, administration_hours, instruction_hours, research_hours, extension_hours, production_hours, consultation_hours FROM workload_policy WHERE policy_type = 'Designation' ORDER BY 
        CASE name
            WHEN 'Vice President' THEN 1
            WHEN 'Campus Director' THEN 2
            WHEN 'Dean' THEN 3
            WHEN 'Director' THEN 4
            WHEN 'Head' THEN 5
            WHEN 'Chairperson/Coordinator/As Officer in Faculty Association' THEN 6
            ELSE 99
        END";
    
    $result = $conn->query($designationQuery);
    $designations = ['None']; // Always include 'None'
    $designationHours = []; // Map designation name to instruction hours (for backward compatibility)
    $designationHours['None'] = 0;
    $designationWorkloads = []; // Map designation name to all 6 workload fields
    $designationWorkloads['None'] = [
        'administration_hours' => 0,
        'instruction_hours' => 0,
        'research_hours' => 0,
        'extension_hours' => 0,
        'production_hours' => 0,
        'consultation_hours' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $designations[] = $row['name'];
        $designationHours[$row['name']] = (int)($row['instruction_hours'] ?? 0);
        // Store all 6 workload fields
        $designationWorkloads[$row['name']] = [
            'administration_hours' => (int)($row['administration_hours'] ?? 0),
            'instruction_hours' => (int)($row['instruction_hours'] ?? 0),
            'research_hours' => (int)($row['research_hours'] ?? 0),
            'extension_hours' => (int)($row['extension_hours'] ?? 0),
            'production_hours' => (int)($row['production_hours'] ?? 0),
            'consultation_hours' => (int)($row['consultation_hours'] ?? 0)
        ];
    }
    
    // If no designations in policy table, use default list (for backward compatibility)
    if (count($designations) === 1) {
        $defaultDesignations = [
            'None',
            'Vice President',
            'Campus Director',
            'Dean',
            'Director',
            'Head',
            'Chairperson/Coordinator/As Officer in Faculty Association'
        ];
        $designations = $defaultDesignations;
    }
    
    $data['designations'] = $designations;
    $data['designation_hours'] = $designationHours; // Backward compatibility
    $data['designation_workloads'] = $designationWorkloads; // All 6 fields
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching workload policy: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch workload policy data.'
    ]);
}
?>

