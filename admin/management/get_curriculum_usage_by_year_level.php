<?php
/**
 * Get Curriculum Usage by Year Level API
 * Returns which curriculum each year level is currently using for a given program
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/program_year_level_curriculum.php';

header('Content-Type: application/json');

function json_response($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

if (!hasPermission('manage_subjects') && !hasPermission('view_subjects')) {
    json_response(false, 'Unauthorized access.', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', null, 405);
}

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if ($program_id <= 0) {
    json_response(false, 'Invalid program ID.', null, 400);
}

// Get user's department ID
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

try {
    $programStmt = $conn->prepare("SELECT dept_id FROM program WHERE program_id = ? LIMIT 1");
    if (!$programStmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }
    $programStmt->bind_param("i", $program_id);
    $programStmt->execute();
    $programRow = $programStmt->get_result()->fetch_assoc();
    $programStmt->close();

    if (!$programRow) {
        json_response(false, 'Program not found.', null, 404);
    }
    if (!$isAdminSupport && ($userDeptId <= 0 || (int)$programRow['dept_id'] !== $userDeptId)) {
        json_response(false, 'Unauthorized access to this program.', null, 403);
    }

    // Check if mapping table exists, if not fall back to old method
    $table_check = $conn->query("SHOW TABLES LIKE 'program_year_level_curriculum'");
    $table_exists = $table_check && $table_check->num_rows > 0;
    if ($table_check) $table_check->close();
    
    $usage = [];
    
    if ($table_exists) {
        if (pylcurriculum_has_sy_id_column($conn)) {
            $picked = pylcurriculum_pick_curr_ids_by_level($conn, $program_id);
            if (!empty($picked)) {
                $count_stmt = $conn->prepare("
                    SELECT COUNT(*) as subject_count FROM subject s
                    WHERE s.program_id = ? AND s.curr_id = ? AND s.subj_lvl = ?
                ");
                $curr_stmt = $conn->prepare("SELECT curr_name, curr_code, curr_yr FROM curriculum WHERE curr_id = ? LIMIT 1");
                if (!$count_stmt || !$curr_stmt) {
                    throw new Exception("SQL Prepare failed: " . $conn->error);
                }
                foreach ($picked as $level => $curr_id_val) {
                    $cid = (int)$curr_id_val;
                    $lvl = (int)$level;
                    $curr_stmt->bind_param("i", $cid);
                    $curr_stmt->execute();
                    $crow = $curr_stmt->get_result()->fetch_assoc();
                    $count_stmt->bind_param("iii", $program_id, $cid, $lvl);
                    $count_stmt->execute();
                    $cnt_row = $count_stmt->get_result()->fetch_assoc();
                    $subject_count = (int)($cnt_row['subject_count'] ?? 0);
                    $curriculumName = null;
                    $constructedName = 'Curriculum ' . $cid;
                    if ($crow) {
                        $cn = $crow['curr_name'] ?? '';
                        $cc = $crow['curr_code'] ?? '';
                        $cy = $crow['curr_yr'] ?? '';
                        if (!empty($cn) && trim($cn) !== $constructedName) {
                            $curriculumName = trim($cn);
                        } elseif (!empty($cc)) {
                            $curriculumName = trim($cc);
                        } elseif (!empty($cy)) {
                            $curriculumName = trim($cy);
                        }
                    }
                    if ($curriculumName === null || $curriculumName === '') {
                        $curriculumName = 'Curriculum #' . $cid;
                    }
                    $usage[$lvl] = [
                        'curr_id' => $cid,
                        'curriculum_name' => $curriculumName,
                        'subject_count' => $subject_count
                    ];
                }
                $curr_stmt->close();
                $count_stmt->close();
            }
            if (empty($usage)) {
                error_log("No curriculum mapping found in program_year_level_curriculum for program {$program_id}, trying fallback method...");
            }
        } else {
            $query = "SELECT 
                        pyc.year_level,
                        pyc.curr_id,
                        COALESCE(c.curr_name, c.curr_code, CONCAT('Curriculum ', pyc.curr_id)) as curr_name,
                        c.curr_code,
                        c.curr_yr,
                        (SELECT COUNT(*) FROM subject s 
                         WHERE s.program_id = pyc.program_id 
                         AND s.curr_id = pyc.curr_id 
                         AND s.subj_lvl = pyc.year_level) as subject_count
                      FROM program_year_level_curriculum pyc
                      LEFT JOIN curriculum c ON pyc.curr_id = c.curr_id
                      WHERE pyc.program_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("SQL Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $program_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $level = (int)$row['year_level'];
                $curriculumName = null;
                $constructedName = 'Curriculum ' . $row['curr_id'];
                if (!empty($row['curr_name']) && trim($row['curr_name']) !== $constructedName) {
                    $curriculumName = trim($row['curr_name']);
                } elseif (!empty($row['curr_code'])) {
                    $curriculumName = trim($row['curr_code']);
                } elseif (!empty($row['curr_yr'])) {
                    $curriculumName = trim($row['curr_yr']);
                } elseif (!empty($row['curr_id'])) {
                    $curriculumName = 'Curriculum #' . $row['curr_id'];
                }
                $usage[$level] = [
                    'curr_id' => (int)$row['curr_id'],
                    'curriculum_name' => $curriculumName,
                    'subject_count' => (int)$row['subject_count']
                ];
            }
            $stmt->close();
            if (empty($usage)) {
                error_log("No curriculum mapping found in program_year_level_curriculum for program {$program_id}, trying fallback method...");
            }
        }
    }
    
    // FALLBACK: If mapping table doesn't exist OR no mappings found, get from subjects
    if (empty($usage)) {
        // Get most common curriculum per year level from subjects
        $query = "SELECT 
                    s.subj_lvl,
                    s.curr_id,
                    COALESCE(c.curr_name, c.curr_code, CONCAT('Curriculum ', s.curr_id)) as curr_name,
                    COUNT(DISTINCT s.subj_id) as subject_count
                  FROM subject s
                  LEFT JOIN curriculum c ON s.curr_id = c.curr_id
                  WHERE s.program_id = ?";
        
        $params = [$program_id];
        $types = 'i';
        
        // Filter by department if not admin support
        if (!$isAdminSupport && $userDeptId > 0) {
            $query .= " AND (s.dept_id = ? OR c.dept_id = ?)";
            $params[] = $userDeptId;
            $params[] = $userDeptId;
            $types .= 'ii';
        }
        
        $query .= " GROUP BY s.subj_lvl, s.curr_id, c.curr_name, c.curr_code
                    ORDER BY s.subj_lvl, subject_count DESC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("SQL Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Organize by year level - take the curriculum with most subjects for each year level
        $yearLevelData = [];
        
        while ($row = $result->fetch_assoc()) {
            $level = (int)$row['subj_lvl'];
            if (!isset($yearLevelData[$level]) || $yearLevelData[$level]['subject_count'] < $row['subject_count']) {
                $curriculumName = null;
                $constructedName = 'Curriculum ' . ($row['curr_id'] ?? '');
                if (!empty($row['curr_name']) && trim($row['curr_name']) !== $constructedName && strpos(trim($row['curr_name']), 'Curriculum ') !== 0) {
                    $curriculumName = trim($row['curr_name']);
                } elseif (!empty($row['curr_id'])) {
                    $curriculumName = 'Curriculum #' . $row['curr_id'];
                }
                
                $yearLevelData[$level] = [
                    'curr_id' => (int)$row['curr_id'],
                    'curriculum_name' => $curriculumName,
                    'subject_count' => (int)$row['subject_count']
                ];
            }
        }
        
        $stmt->close();
        
        // Format as array indexed by year level (1-5) - merge with existing usage
        for ($level = 1; $level <= 5; $level++) {
            if (isset($yearLevelData[$level])) {
                // Only add if not already in usage (mapping table takes priority)
                if (!isset($usage[$level])) {
                    $usage[$level] = $yearLevelData[$level];
                }
            }
        }
    }
    
    json_response(true, 'Curriculum usage retrieved successfully.', ['usage' => $usage], 200);
    
} catch (Exception $e) {
    error_log("Get Curriculum Usage Error: " . $e->getMessage());
    json_response(false, 'A database error occurred: ' . $e->getMessage(), null, 500);
}
?>