<?php
/**
 * Get Course Management Data
 * Provides comprehensive course/program data for the admin dashboard
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check if user has permission to manage courses
if (!hasPermission('manage_curriculum')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - You do not have permission to manage courses']);
    exit();
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

try {
    $courseData = [];

    // Get colleges (all users can see all colleges)
    $result = mysqli_query($conn, "SELECT * FROM college ORDER BY college_name");
    $colleges = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $colleges[] = $row;
    }
    $courseData['colleges'] = $colleges;

    // Get departments with college info - filter by user's department unless Admin Support
    $deptFilter = "";
    if (!$isAdminSupport && $userDeptId > 0) {
        $deptFilter = " WHERE d.dept_id = " . (int)$userDeptId;
    }
    $result = mysqli_query($conn, "
        SELECT d.*, c.college_name, c.college_code 
        FROM department d 
        LEFT JOIN college c ON d.college_id = c.college_id 
        $deptFilter
        ORDER BY c.college_name, d.dept_name
    ");
    $departments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
    $courseData['departments'] = $departments;

    // Get programs with department and college info - filter by user's department unless Admin Support
    $progFilter = "";
    if (!$isAdminSupport && $userDeptId > 0) {
        $progFilter = " WHERE p.dept_id = " . (int)$userDeptId;
    }
    $result = mysqli_query($conn, "
        SELECT p.*, d.dept_name, c.college_name, c.college_code
        FROM program p 
        LEFT JOIN department d ON p.dept_id = d.dept_id 
        LEFT JOIN college c ON d.college_id = c.college_id
        $progFilter
        ORDER BY c.college_name, d.dept_name, p.program_name
    ");
    $programs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }
    $courseData['programs'] = $programs;

    // Get curriculums with department info - filter by user's department unless Admin Support
    $currFilter = "";
    if (!$isAdminSupport && $userDeptId > 0) {
        $currFilter = " WHERE c.dept_id = " . (int)$userDeptId;
    }
    $result = mysqli_query($conn, "
        SELECT c.*, d.dept_name, col.college_name
        FROM curriculum c 
        LEFT JOIN department d ON c.dept_id = d.dept_id 
        LEFT JOIN college col ON d.college_id = col.college_id
        $currFilter
        ORDER BY col.college_name, d.dept_name, c.curr_name
    ");
    $curriculums = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Get subject count for each curriculum
        $subjCount = mysqli_query($conn, "SELECT COUNT(*) as count FROM subject WHERE curr_id = " . $row['curr_id']);
        $subjCountRow = mysqli_fetch_assoc($subjCount);
        $row['subjects_count'] = $subjCountRow['count'];
        
        $curriculums[] = $row;
    }
    $courseData['curriculums'] = $curriculums;

    // Get subjects with curriculum and department info - filter by user's department unless Admin Support
    $subjFilter = "";
    if (!$isAdminSupport && $userDeptId > 0) {
        $subjFilter = " WHERE d.dept_id = " . (int)$userDeptId;
    }
    $result = mysqli_query($conn, "
        SELECT s.*, c.curr_name, d.dept_name, col.college_name
        FROM subject s 
        LEFT JOIN curriculum c ON s.curr_id = c.curr_id 
        LEFT JOIN department d ON c.dept_id = d.dept_id 
        LEFT JOIN college col ON d.college_id = col.college_id
        $subjFilter
        ORDER BY col.college_name, d.dept_name, c.curr_name, s.subj_code
    ");
    $subjects = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $subjects[] = $row;
    }
    $courseData['subjects'] = $subjects;

    // Get statistics
    $stats = [];
    $stats['total_colleges'] = count($colleges);
    $stats['total_departments'] = count($departments);
    $stats['total_programs'] = count($programs);
    $stats['total_curriculums'] = count($curriculums);
    $stats['total_subjects'] = count($subjects);

    // Get active counts
    $stats['active_colleges'] = count(array_filter($colleges, function($c) { return $c['college_status'] === 'Active'; }));
    $stats['active_departments'] = count(array_filter($departments, function($d) { return $d['dept_status'] === 'Active'; }));
    $stats['active_programs'] = count(array_filter($programs, function($p) { return $p['program_status'] === 'Active'; }));
    // Subjects don't have status column, so all subjects are considered active
    $stats['active_subjects'] = count($subjects);

    $courseData['statistics'] = $stats;

    // Create hierarchical structure
    $hierarchy = [];
    foreach ($colleges as $college) {
        $collegeData = [
            'id' => $college['college_id'],
            'name' => $college['college_name'],
            'code' => $college['college_code'],
            'status' => $college['college_status'],
            'departments' => []
        ];

        foreach ($departments as $dept) {
            if ($dept['college_id'] == $college['college_id']) {
                $deptData = [
                    'id' => $dept['dept_id'],
                    'name' => $dept['dept_name'],
                    'code' => $dept['dept_code'],
                    'status' => $dept['dept_status'],
                    'programs' => [],
                    'curriculums' => []
                ];

                // Add programs for this department
                foreach ($programs as $program) {
                    if ($program['dept_id'] == $dept['dept_id']) {
                        $deptData['programs'][] = [
                            'id' => $program['program_id'],
                            'name' => $program['program_name'],
                            'code' => $program['program_code'],
                            'status' => $program['program_status']
                        ];
                    }
                }

                // Add curriculums for this department
                foreach ($curriculums as $curr) {
                    if ($curr['dept_id'] == $dept['dept_id']) {
                        $currData = [
                            'id' => $curr['curr_id'],
                            'name' => $curr['curr_name'],
                            'level' => $curr['curr_lvl'],
                            'year' => $curr['curr_yr'],
                            'subjects_count' => $curr['subjects_count'],
                            'subjects' => []
                        ];

                        // Add subjects for this curriculum
                        foreach ($subjects as $subject) {
                            if ($subject['curr_id'] == $curr['curr_id']) {
                                $currData['subjects'][] = [
                                    'id' => $subject['subj_id'],
                                    'code' => $subject['subj_code'],
                                    'description' => $subject['subj_desc'],
                                    'units' => $subject['subj_unit'],
                                    'category' => $subject['subj_category']
                                ];
                            }
                        }

                        $deptData['curriculums'][] = $currData;
                    }
                }

                $collegeData['departments'][] = $deptData;
            }
        }

        $hierarchy[] = $collegeData;
    }

    $courseData['hierarchy'] = $hierarchy;

    echo json_encode([
        'success' => true,
        'data' => $courseData
    ]);

} catch (Exception $e) {
    error_log("Get Course Data Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
