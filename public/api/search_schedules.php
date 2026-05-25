<?php
/**
 * Public Schedule Search API
 * Allows visitors/students to search for instructor and class schedules
 * No authentication required
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/database.php';

// Get search parameters
$searchQuery = $_GET['q'] ?? '';
$searchType = $_GET['type'] ?? 'all'; // 'instructor', 'class', 'department', 'section', 'program', 'all'
$instructorName = $_GET['instructor'] ?? '';
$programName = $_GET['program'] ?? '';
$departmentName = $_GET['department'] ?? '';
$sectionName = $_GET['section'] ?? '';
$day = $_GET['day'] ?? '';

// If search type is specific and q is provided but specific param is empty, use q
if ($searchType === 'instructor' && empty($instructorName) && !empty($searchQuery)) {
    $instructorName = $searchQuery;
}
if ($searchType === 'program' && empty($programName) && !empty($searchQuery)) {
    $programName = $searchQuery;
}
if ($searchType === 'department' && empty($departmentName) && !empty($searchQuery)) {
    $departmentName = $searchQuery;
}
if ($searchType === 'section' && empty($sectionName) && !empty($searchQuery)) {
    $sectionName = $searchQuery;
}

// Build the query
$query = "
    SELECT 
        s.schd_id,
        s.schd_start,
        s.schd_end,
        s.schd_day,
        s.schd_type,
        subj.subj_code,
        subj.subj_desc,
        subj.subj_lec,
        subj.subj_lab,
        subj.subj_unit,
        sec.sec_name,
        sec.sec_num,
        CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
        i.inst_id,
        r.rm_name,
        b.bd_desc,
        p.program_name,
        p.program_code,
        COALESCE(s.year_level, cls.class_lvl) as year_level,
        TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time,
        TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time,
        COALESCE(d.dept_name, 'N/A') as dept_name,
        COALESCE(d.dept_code, '') as dept_code
    FROM schedule s
    JOIN subject subj ON s.subj_id = subj.subj_id
    JOIN curriculum curr ON subj.curr_id = curr.curr_id
    JOIN section sec ON s.sec_id = sec.sec_id
    JOIN class cls ON sec.class_id = cls.class_id
    JOIN program p ON subj.program_id = p.program_id
    JOIN instructor i ON s.inst_id = i.inst_id
    JOIN room r ON s.rm_id = r.rm_id
    JOIN building b ON r.bd_id = b.bd_id
    LEFT JOIN department d ON COALESCE(s.dept_id, curr.dept_id, p.dept_id) = d.dept_id
    WHERE s.schd_status = 'Active'
";

$whereClauses = [];
$params = [];
$types = '';

// Search by instructor name
if (!empty($instructorName)) {
    $whereClauses[] = "(CONCAT(i.inst_fname, ' ', i.inst_lname) LIKE ? OR i.inst_fname LIKE ? OR i.inst_lname LIKE ?)";
    $searchTerm = '%' . $instructorName . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

// Search by program
if (!empty($programName)) {
    $whereClauses[] = "(p.program_name LIKE ? OR p.program_code LIKE ?)";
    $searchTerm = '%' . $programName . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

// Search by department
if (!empty($departmentName)) {
    $whereClauses[] = "(d.dept_name LIKE ? OR d.dept_code LIKE ?)";
    $searchTerm = '%' . $departmentName . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

// Search by section/class
if (!empty($sectionName)) {
    // Normalize the search term - remove extra spaces and handle dash variations
    $normalizedSection = preg_replace('/\s+/', ' ', trim($sectionName)); // Normalize spaces
    $normalizedSection = str_replace(' - ', '-', $normalizedSection); // Handle " - " to "-"
    $normalizedSection = str_replace(' -', '-', $normalizedSection); // Handle " -" to "-"
    $normalizedSection = str_replace('- ', '-', $normalizedSection); // Handle "- " to "-"
    
    // Try to parse year level if it's a number
    $yearLevel = is_numeric($sectionName) ? (int)$sectionName : null;
    
    // Create multiple search patterns for flexibility
    $searchPatterns = [];
    
    // Original search term
    $searchPatterns[] = '%' . $sectionName . '%';
    
    // Normalized version
    if ($normalizedSection !== $sectionName) {
        $searchPatterns[] = '%' . $normalizedSection . '%';
    }
    
    // Try variations: "BSIT - 1A" -> "BSIT 1-A", "BSIT 1A" -> "BSIT 1-A"
    $variations = [];
    $variations[] = str_replace([' - ', ' -', '- '], '-', $sectionName); // Remove spaces around dash
    $variations[] = preg_replace('/\s*-\s*/', ' ', $sectionName); // Remove dash entirely
    $variations[] = preg_replace('/\s+/', '-', $sectionName); // Replace spaces with dash
    
    foreach ($variations as $variation) {
        if ($variation !== $sectionName && $variation !== $normalizedSection) {
            $searchPatterns[] = '%' . $variation . '%';
        }
    }
    
    // Remove duplicates
    $searchPatterns = array_unique($searchPatterns);
    
    if ($yearLevel !== null) {
        // Search by section name variations OR year level
        $likeConditions = [];
        foreach ($searchPatterns as $pattern) {
            $likeConditions[] = "sec.sec_name LIKE ?";
            $params[] = $pattern;
            $types .= 's';
        }
        $whereClauses[] = "(" . implode(' OR ', $likeConditions) . " OR COALESCE(s.year_level, cls.class_lvl) = ?)";
        $params[] = $yearLevel;
        $types .= 'i';
    } else {
        // Search only by section name with variations
        $likeConditions = [];
        foreach ($searchPatterns as $pattern) {
            $likeConditions[] = "sec.sec_name LIKE ?";
            $params[] = $pattern;
            $types .= 's';
        }
        $whereClauses[] = "(" . implode(' OR ', $likeConditions) . ")";
    }
}

// Search by day
if (!empty($day)) {
    $whereClauses[] = "s.schd_day = ?";
    $params[] = $day;
    $types .= 's';
}

// General search query (searches across multiple fields)
if (!empty($searchQuery)) {
    $searchTerm = '%' . $searchQuery . '%';
    $searchConditions = [];
    
    // Search in instructor name
    $searchConditions[] = "CONCAT(i.inst_fname, ' ', i.inst_lname) LIKE ?";
    $params[] = $searchTerm;
    $types .= 's';
    
    // Search in subject code/description
    $searchConditions[] = "(subj.subj_code LIKE ? OR subj.subj_desc LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
    
    // Search in program name/code
    $searchConditions[] = "(p.program_name LIKE ? OR p.program_code LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
    
    // Search in section name
    $searchConditions[] = "sec.sec_name LIKE ?";
    $params[] = $searchTerm;
    $types .= 's';
    
    // Search in room name
    $searchConditions[] = "r.rm_name LIKE ?";
    $params[] = $searchTerm;
    $types .= 's';
    
    // Search in department name/code
    $searchConditions[] = "(d.dept_name LIKE ? OR d.dept_code LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
    
    $whereClauses[] = "(" . implode(' OR ', $searchConditions) . ")";
}

// Apply search type filter
if ($searchType === 'instructor' && empty($instructorName) && empty($searchQuery)) {
    // If searching by instructor type but no query, return empty
    $whereClauses[] = "1 = 0";
} elseif ($searchType === 'class' && empty($searchQuery)) {
    // If searching by class type but no query, return empty
    $whereClauses[] = "1 = 0";
} elseif ($searchType === 'department' && empty($departmentName) && empty($searchQuery)) {
    // If searching by department type but no query, return empty
    $whereClauses[] = "1 = 0";
} elseif ($searchType === 'section' && empty($sectionName) && empty($searchQuery)) {
    // If searching by section type but no query, return empty
    $whereClauses[] = "1 = 0";
} elseif ($searchType === 'program' && empty($programName) && empty($searchQuery)) {
    // If searching by program type but no query, return empty
    $whereClauses[] = "1 = 0";
}

if (count($whereClauses) > 0) {
    $query .= " AND " . implode(' AND ', $whereClauses);
}

// Order by day and time
$query .= " ORDER BY 
    FIELD(s.schd_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    s.schd_start ASC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        "success" => true,
        "data" => $data,
        "count" => count($data)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error searching schedules: " . $e->getMessage(),
        "data" => []
    ]);
}
?>

