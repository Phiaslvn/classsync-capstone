<?php
/**
 * Get Complete Workload Data for an Instructor
 * Retrieves all schedule assignments and computes workload statistics
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('view_users') && !hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$inst_id = isset($_GET['inst_id']) ? (int)$_GET['inst_id'] : 0;

if ($inst_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Instructor ID required']);
    exit;
}

try {
    // Get active school year and semester
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'active_school_year_id'
        LIMIT 1
    ");
    $stmt->execute();
    $sy_result = $stmt->get_result();
    $sy_row = $sy_result->fetch_assoc();
    $active_sy_id = $sy_row ? intval($sy_row['setting_value']) : 0;
    
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'active_semester'
        LIMIT 1
    ");
    $stmt->execute();
    $semester_result = $stmt->get_result();
    $semester_row = $semester_result->fetch_assoc();
    $active_semester = $semester_row ? $semester_row['setting_value'] : null;
    
    $active_term = 0;
    if ($active_semester === '1st Semester') {
        $active_term = 1;
    } elseif ($active_semester === '2nd Semester') {
        $active_term = 2;
    } elseif ($active_semester === 'Mid-Year') {
        $active_term = 3;
    }
    
    // Get instructor basic info
    $stmt = $conn->prepare("
        SELECT 
            i.inst_id,
            CONCAT(a.fname, ' ', COALESCE(a.minitial, ''), ' ', a.lname, 
                   CASE WHEN a.suffix IS NOT NULL AND a.suffix != '' THEN CONCAT(' ', a.suffix) ELSE '' END) as full_name,
            a.fname,
            a.lname,
            a.minitial,
            a.suffix,
            a.acc_email,
            i.inst_status,
            i.rank,
            i.designation,
            i.administration_hours,
            i.instruction_hours,
            i.research_hours,
            i.extension_hours,
            i.instructional_functions_hours,
            i.consultation_hours,
            d.dept_id,
            d.dept_name,
            p.program_id,
            p.program_name,
            p.program_code
        FROM instructor i
        INNER JOIN account a ON i.inst_user = a.acc_user
        LEFT JOIN department d ON COALESCE(i.dept_id, a.dept_id) = d.dept_id
        LEFT JOIN program p ON i.program_id = p.program_id
        WHERE i.inst_id = ?
    ");
    $stmt->bind_param('i', $inst_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Instructor not found']);
        exit;
    }
    
    $instructor = $result->fetch_assoc();
    $stmt->close();
    
    // Get all schedules for this instructor
    $stmt = $conn->prepare("
        SELECT 
            s.schd_id,
            s.schd_day,
            s.schd_start,
            s.schd_end,
            s.schd_type,
            s.schd_min,
            s.is_overtime,
            subj.subj_id,
            subj.subj_code,
            subj.subj_desc,
            subj.subj_unit,
            subj.subj_lec,
            subj.subj_lab,
            sec.sec_id,
            sec.sec_name,
            sec.sec_num,
            cls.class_id,
            cls.class_lvl,
            r.rm_id,
            r.rm_name,
            b.bd_desc,
            p.program_id,
            p.program_name,
            p.program_code,
            curr.curr_id,
            curr.curr_name
        FROM schedule s
        INNER JOIN subject subj ON s.subj_id = subj.subj_id
        INNER JOIN section sec ON s.sec_id = sec.sec_id
        INNER JOIN class cls ON sec.class_id = cls.class_id
        INNER JOIN room r ON s.rm_id = r.rm_id
        INNER JOIN building b ON r.bd_id = b.bd_id
        LEFT JOIN program p ON COALESCE(s.program_id, subj.program_id) = p.program_id
        LEFT JOIN curriculum curr ON subj.curr_id = curr.curr_id
        WHERE s.inst_id = ?
          AND s.sy_id = ?
          AND s.schd_term = ?
          AND s.schd_status = 'Active'
        ORDER BY 
            CASE s.schd_day
                WHEN 'Mon' THEN 1
                WHEN 'Tue' THEN 2
                WHEN 'Wed' THEN 3
                WHEN 'Thu' THEN 4
                WHEN 'Fri' THEN 5
                WHEN 'Sat' THEN 6
                WHEN 'Sun' THEN 7
            END,
            s.schd_start
    ");
    $stmt->bind_param('iii', $inst_id, $active_sy_id, $active_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    $regular_units = 0;
    $overload_units = 0;
    $regular_lec_hours = 0;
    $regular_lab_hours = 0;
    $overload_lec_hours = 0;
    $overload_lab_hours = 0;
    $regular_minutes = 0;
    $overload_minutes = 0;
    $unique_subjects = [];
    $unique_sections = [];
    
    while ($row = $result->fetch_assoc()) {
        $is_overtime = $row['is_overtime'] === 'Yes';
        $schd_type = $row['schd_type'] ?? 'Lec';
        $subj_unit = (int)$row['subj_unit'];
        $subj_lec = (int)$row['subj_lec'];
        $subj_lab = (int)$row['subj_lab'];
        $schd_min = (int)$row['schd_min'];
        
        // Track unique subjects and sections
        $subject_key = $row['subj_id'] . '_' . $row['sec_id'];
        if (!isset($unique_subjects[$subject_key])) {
            $unique_subjects[$subject_key] = true;
            if ($is_overtime) {
                $overload_units += $subj_unit;
            } else {
                $regular_units += $subj_unit;
            }
        }
        
        // Track unique sections
        $section_key = $row['sec_id'];
        if (!isset($unique_sections[$section_key])) {
            $unique_sections[$section_key] = true;
        }
        
        // Calculate lecture and lab hours
        if ($schd_type === 'Lec') {
            if ($is_overtime) {
                $overload_lec_hours += $subj_lec;
            } else {
                $regular_lec_hours += $subj_lec;
            }
        } elseif ($schd_type === 'Lab') {
            if ($is_overtime) {
                $overload_lab_hours += $subj_lab;
            } else {
                $regular_lab_hours += $subj_lab;
            }
        }
        
        if ($is_overtime) {
            $overload_minutes += $schd_min;
        } else {
            $regular_minutes += $schd_min;
        }
        
        $schedules[] = [
            'schedule_id' => (int)$row['schd_id'],
            'day' => $row['schd_day'],
            'start_time' => $row['schd_start'],
            'end_time' => $row['schd_end'],
            'type' => $schd_type,
            'minutes' => $schd_min,
            'is_overtime' => $is_overtime,
            'subject' => [
                'id' => (int)$row['subj_id'],
                'code' => $row['subj_code'],
                'name' => $row['subj_desc'],
                'units' => $subj_unit,
                'lec_hours' => $subj_lec,
                'lab_hours' => $subj_lab
            ],
            'section' => [
                'id' => (int)$row['sec_id'],
                'name' => $row['sec_name'],
                'number' => $row['sec_num']
            ],
            'class' => [
                'id' => (int)$row['class_id'],
                'level' => (int)$row['class_lvl']
            ],
            'room' => [
                'id' => (int)$row['rm_id'],
                'name' => $row['rm_name'],
                'building' => $row['bd_desc']
            ],
            'program' => [
                'id' => $row['program_id'] ? (int)$row['program_id'] : null,
                'name' => $row['program_name'],
                'code' => $row['program_code']
            ],
            'curriculum' => [
                'id' => $row['curr_id'] ? (int)$row['curr_id'] : null,
                'name' => $row['curr_name']
            ]
        ];
    }
    
    $stmt->close();
    
    // Calculate totals
    $total_units = $regular_units + $overload_units;
    $total_lec_hours = $regular_lec_hours + $overload_lec_hours;
    $total_lab_hours = $regular_lab_hours + $overload_lab_hours;
    $total_teaching_hours = $total_lec_hours + $total_lab_hours;
    $regular_teaching_hours = $regular_lec_hours + $regular_lab_hours;
    $overload_teaching_hours = $overload_lec_hours + $overload_lab_hours;
    $total_minutes = $regular_minutes + $overload_minutes;
    $regular_hours = round($regular_minutes / 60, 2);
    $overload_hours = round($overload_minutes / 60, 2);
    $total_hours = round($total_minutes / 60, 2);
    $num_classes = count($unique_sections);
    $num_preparations = count($unique_subjects);
    
    // Get school year details
    $stmt = $conn->prepare("SELECT sy_id, sy_name, sy_year FROM schoolyear WHERE sy_id = ?");
    $stmt->bind_param('i', $active_sy_id);
    $stmt->execute();
    $sy_result = $stmt->get_result();
    $sy_data = $sy_result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'instructor' => [
            'inst_id' => (int)$instructor['inst_id'],
            'full_name' => $instructor['full_name'],
            'fname' => $instructor['fname'],
            'lname' => $instructor['lname'],
            'minitial' => $instructor['minitial'],
            'suffix' => $instructor['suffix'],
            'email' => $instructor['acc_email'],
            'inst_status' => $instructor['inst_status'],
            'rank' => $instructor['rank'],
            'designation' => $instructor['designation'],
            'administration_hours' => (int)$instructor['administration_hours'],
            'instruction_hours' => (int)$instructor['instruction_hours'],
            'research_hours' => (int)$instructor['research_hours'],
            'extension_hours' => (int)$instructor['extension_hours'],
            'instructional_functions_hours' => (int)$instructor['instructional_functions_hours'],
            'consultation_hours' => (int)$instructor['consultation_hours'],
            'dept_id' => (int)$instructor['dept_id'],
            'dept_name' => $instructor['dept_name'],
            'program_id' => $instructor['program_id'] ? (int)$instructor['program_id'] : null,
            'program_name' => $instructor['program_name'],
            'program_code' => $instructor['program_code']
        ],
        'school_year' => [
            'sy_id' => $active_sy_id,
            'sy_name' => $sy_data['sy_name'] ?? '',
            'sy_year' => $sy_data['sy_year'] ?? '',
            'semester' => $active_semester,
            'term' => $active_term
        ],
        'schedules' => $schedules,
        'workload_summary' => [
            'total_units' => $total_units,
            'regular_units' => $regular_units,
            'overload_units' => $overload_units,
            'total_lec_hours' => $total_lec_hours,
            'regular_lec_hours' => $regular_lec_hours,
            'overload_lec_hours' => $overload_lec_hours,
            'total_lab_hours' => $total_lab_hours,
            'regular_lab_hours' => $regular_lab_hours,
            'overload_lab_hours' => $overload_lab_hours,
            'total_teaching_hours' => $total_teaching_hours,
            'regular_teaching_hours' => $regular_teaching_hours,
            'overload_teaching_hours' => $overload_teaching_hours,
            'total_hours' => $total_hours,
            'regular_hours' => $regular_hours,
            'overload_hours' => $overload_hours,
            'num_classes' => $num_classes,
            'num_preparations' => $num_preparations,
            'total_schedules' => count($schedules)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

