<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'instructors' => []];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $searchTerm = trim($_GET['search'] ?? '');
    
    if (empty($searchTerm) || strlen($searchTerm) < 2) {
        $response['message'] = 'Please enter at least 2 characters to search.';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Get all instructors from all departments (for cross-department assignment)
        // Search by name (first name, last name, or full name)
        $searchPattern = '%' . $conn->real_escape_string($searchTerm) . '%';
        
        // Check if account_departments table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'account_departments'");
        $hasAccountDepartmentsTable = $checkTable && $checkTable->num_rows > 0;
        
        $query = "SELECT 
                    i.inst_id, 
                    a.acc_id,
                    CONCAT(a.fname, ' ', a.lname) as name,
                    d.dept_name,
                    d.dept_id,
                    CASE 
                        WHEN a.dept_id IS NOT NULL THEN d.dept_name
                        ELSE 'No Department'
                    END as department_display
                  FROM account a
                  LEFT JOIN instructor i ON i.inst_user = a.acc_user
                  JOIN user_roles ur ON a.acc_id = ur.acc_id
                  JOIN roles r ON ur.role_id = r.id
                  LEFT JOIN department d ON a.dept_id = d.dept_id
                  WHERE a.acc_status = 'Active' 
                    AND r.role_name IN ('User', 'Moderator')
                    AND i.inst_id IS NOT NULL
                    AND (
                        a.fname LIKE ? OR 
                        a.lname LIKE ? OR 
                        CONCAT(a.fname, ' ', a.lname) LIKE ?
                    )
                  ORDER BY a.lname, a.fname
                  LIMIT 50";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sss', $searchPattern, $searchPattern, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $instructors = [];
        while ($row = $result->fetch_assoc()) {
            $acc_id = (int)$row['acc_id'];
            $inst_id = (int)$row['inst_id'];
            
            // Get all departments for this account from account_departments
            $departments = [];
            if ($hasAccountDepartmentsTable) {
                $deptStmt = $conn->prepare("
                    SELECT d.dept_id, d.dept_name 
                    FROM account_departments ad
                    JOIN department d ON ad.dept_id = d.dept_id
                    WHERE ad.acc_id = ?
                    ORDER BY d.dept_name
                ");
                $deptStmt->bind_param("i", $acc_id);
                $deptStmt->execute();
                $deptResult = $deptStmt->get_result();
                while ($deptRow = $deptResult->fetch_assoc()) {
                    $departments[] = [
                        'dept_id' => (int)$deptRow['dept_id'],
                        'dept_name' => $deptRow['dept_name']
                    ];
                }
                $deptStmt->close();
            }
            
            // If no departments from account_departments, use account.dept_id as fallback
            if (empty($departments) && !empty($row['dept_id'])) {
                $departments[] = [
                    'dept_id' => (int)$row['dept_id'],
                    'dept_name' => $row['dept_name']
                ];
            }
            
            $instructors[] = [
                'inst_id' => $inst_id,
                'acc_id' => $acc_id,
                'name' => $row['name'],
                'dept_name' => $row['department_display'], // Primary department for display
                'dept_id' => (int)($row['dept_id'] ?? 0),
                'departments' => $departments, // All departments
                'display' => $row['name'] . ' (' . $row['department_display'] . ')'
            ];
        }
        
        $stmt->close();
        
        $response['success'] = true;
        $response['instructors'] = $instructors;
        $response['count'] = count($instructors);
        
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>

