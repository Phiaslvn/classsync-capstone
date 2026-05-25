<?php
/**
 * Generate Batch Workload Forms
 * Generates Teacher Workload Forms for all instructors with teaching loads
 * Based on TLoad-Form.docx format
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

// Check permissions
if (!hasPermission('view_users') && !hasPermission('manage_users')) {
    http_response_code(403);
    die('Unauthorized');
}

// Get user info for department filtering
$userInfo = getUserInfo();
$userDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
$isAdminSupport = isAdminSupport();

// Get format (html or pdf)
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Get instructor ID for single print (optional)
$inst_id = isset($_GET['inst_id']) ? intval($_GET['inst_id']) : 0;

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
    
    // Get school year details
    $stmt = $conn->prepare("SELECT sy_id, sy_name, sy_year FROM schoolyear WHERE sy_id = ?");
    $stmt->bind_param('i', $active_sy_id);
    $stmt->execute();
    $sy_result = $stmt->get_result();
    $sy_data = $sy_result->fetch_assoc();
    $stmt->close();
    
    // Get instructors with workloads
    // If inst_id is provided, only get that instructor; otherwise get all
    $sql = "
        SELECT DISTINCT
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
        INNER JOIN schedule s ON i.inst_id = s.inst_id
        WHERE s.sy_id = ?
          AND s.schd_term = ?
          AND s.schd_status = 'Active'
          AND a.acc_status = 'Active'
    ";
    
    $params = [$active_sy_id, $active_term];
    $types = 'ii';
    
    // If single instructor requested, filter by inst_id
    if ($inst_id > 0) {
        $sql .= " AND i.inst_id = ?";
        $params[] = $inst_id;
        $types .= 'i';
    }
    
    if (!$isAdminSupport && $userDeptId > 0) {
        $sql .= " AND (COALESCE(i.dept_id, a.dept_id) = ? OR 
                       (i.program_id IS NOT NULL AND EXISTS (
                           SELECT 1 FROM program p_check 
                           WHERE p_check.program_id = i.program_id 
                           AND p_check.dept_id = ?
                       )))";
        $params[] = $userDeptId;
        $params[] = $userDeptId;
        $types .= 'ii';
    }
    
    $sql .= " ORDER BY a.lname, a.fname";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $instructors = [];
    while ($row = $result->fetch_assoc()) {
        $instructors[] = $row;
    }
    $stmt->close();
    
    // If single instructor requested but not found, return error
    if ($inst_id > 0 && empty($instructors)) {
        http_response_code(404);
        die('Instructor not found or has no active schedules.');
    }
    
    // Generate HTML for all forms
    $html = generateWorkloadFormsHTML($instructors, $active_sy_id, $active_term, $sy_data, $active_semester, $conn);
    
    if ($format === 'pdf') {
        // Generate PDF and force download
        generatePDFDownload($html, $sy_data, $active_semester);
    } else {
        // HTML format - print-friendly
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error generating forms: ' . $e->getMessage());
}

/**
 * Generate HTML for workload forms
 */
function generateWorkloadFormsHTML($instructors, $sy_id, $term, $sy_data, $semester, $conn) {
    // Get logo path - construct from current location
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    // Remove /admin/management/generate_workload_forms.php to get root
    $rootPath = dirname(dirname(dirname($scriptPath)));
    $logoPath = $protocol . '://' . $host . $rootPath . '/evsu_logo.png';
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Workload Forms - ' . htmlspecialchars($sy_data['sy_name'] ?? '') . '</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0.4cm;
        }
        @media print {
            .page-break {
                page-break-after: always;
                page-break-inside: avoid;
                break-after: page;
            }
            .workload-form {
                page-break-inside: avoid;
                break-inside: avoid;
                height: 100vh;
                max-height: 100vh;
                overflow: hidden;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.1;
            color: #000;
            background: #fff;
            padding: 5px;
        }
        .workload-form {
            width: 100%;
            height: calc(100vh - 10px);
            max-height: calc(100vh - 10px);
            margin: 0 auto 10px;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 2px solid #000;
            flex-shrink: 0;
        }
        .logo-section {
            flex-shrink: 0;
        }
        .logo-section img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .header-center {
            flex: 1;
            text-align: center;
            padding: 0 15px;
        }
        .header-center h1 {
            font-size: 12pt;
            font-weight: bold;
            margin: 1px 0;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .header-center h2 {
            font-size: 9pt;
            font-weight: normal;
            margin: 1px 0;
            line-height: 1.1;
        }
        .header-right {
            flex-shrink: 0;
            text-align: right;
            font-size: 7pt;
        }
        .header-right div {
            margin-bottom: 2px;
            line-height: 1.1;
        }
        .instructor-info {
            margin-bottom: 6px;
            font-size: 8pt;
            flex-shrink: 0;
        }
        .info-row {
            display: flex;
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            width: 110px;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 14px;
            padding-left: 3px;
        }
        .workload-section {
            margin-bottom: 6px;
            flex-shrink: 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
            font-size: 7pt;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 2px 2px;
            text-align: left;
            vertical-align: top;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 6pt;
            padding: 3px 2px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .other-assignments {
            margin: 6px 0;
            font-size: 8pt;
            flex-shrink: 0;
        }
        .other-assignments div {
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            line-height: 1.2;
        }
        .other-assignments input[type="checkbox"] {
            margin-right: 3px;
        }
        .summary-section {
            margin-top: 6px;
            font-size: 8pt;
            flex-shrink: 0;
        }
        .summary-row {
            display: flex;
            margin-bottom: 2px;
        }
        .summary-label {
            font-weight: bold;
            width: 180px;
        }
        .signature-section {
            margin-top: 8px;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 5px;
            font-size: 7pt;
            flex-shrink: 0;
        }
        .signature-box {
            text-align: center;
        }
        .signature-name {
            font-weight: bold;
            margin-bottom: 24px;
            min-height: 30px;
            border-bottom: 1px solid #000;
            padding-top: 32px;
            padding-bottom: 6px;
            font-size: 7pt;
            line-height: 1.2;
        }
        .signature-title {
            font-size: 6pt;
            margin-top: 2px;
            line-height: 1.1;
        }
    </style>
</head>
<body>';
    
    foreach ($instructors as $instructor) {
        $workload_data = getInstructorWorkloadData($instructor['inst_id'], $sy_id, $term, $conn);
        
        $html .= '<div class="workload-form page-break">';
        $html .= generateSingleForm($instructor, $workload_data, $sy_data, $semester, $logoPath, $conn);
        $html .= '</div>';
    }
    
    $html .= '</body></html>';
    return $html;
}

/**
 * Get workload data for a single instructor
 */
function getInstructorWorkloadData($inst_id, $sy_id, $term, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            s.schd_id,
            s.schd_day,
            s.schd_start,
            s.schd_end,
            s.schd_type,
            s.schd_min,
            s.is_overtime,
            subj.subj_code,
            subj.subj_desc,
            subj.subj_unit,
            subj.subj_lec,
            subj.subj_lab,
            sec.sec_id,
            sec.sec_name,
            cls.class_lvl,
            r.rm_id,
            r.rm_name,
            b.bd_desc,
            p.program_name
        FROM schedule s
        INNER JOIN subject subj ON s.subj_id = subj.subj_id
        INNER JOIN section sec ON s.sec_id = sec.sec_id
        INNER JOIN class cls ON sec.class_id = cls.class_id
        INNER JOIN room r ON s.rm_id = r.rm_id
        INNER JOIN building b ON r.bd_id = b.bd_id
        LEFT JOIN program p ON COALESCE(s.program_id, subj.program_id) = p.program_id
        WHERE s.inst_id = ?
          AND s.sy_id = ?
          AND s.schd_term = ?
          AND s.schd_status = 'Active'
        ORDER BY 
            s.is_overtime,
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
    $stmt->bind_param('iii', $inst_id, $sy_id, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    $regular_units = 0;
    $overload_units = 0;
    $regular_lec_hours = 0;
    $regular_lab_hours = 0;
    $overload_lec_hours = 0;
    $overload_lab_hours = 0;
    $unique_subjects = [];
    
    while ($row = $result->fetch_assoc()) {
        $is_overtime = $row['is_overtime'] === 'Yes';
        $schd_type = $row['schd_type'] ?? 'Lec';
        $subj_unit = (int)$row['subj_unit'];
        $subj_lec = (int)$row['subj_lec'];
        $subj_lab = (int)$row['subj_lab'];
        
        $subject_key = $row['subj_code'] . '_' . $row['sec_name'];
        if (!isset($unique_subjects[$subject_key])) {
            $unique_subjects[$subject_key] = true;
            if ($is_overtime) {
                $overload_units += $subj_unit;
            } else {
                $regular_units += $subj_unit;
            }
        }
        
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
        
        $schedules[] = [
            'schd_id' => (int)$row['schd_id'],
            'schd_day' => $row['schd_day'],
            'schd_start' => $row['schd_start'],
            'schd_end' => $row['schd_end'],
            'schd_type' => $schd_type,
            'is_overtime' => $is_overtime ? 'Yes' : 'No',
            'subj_code' => $row['subj_code'],
            'subj_desc' => $row['subj_desc'],
            'subj_unit' => $subj_unit,
            'subj_lec' => $subj_lec,
            'subj_lab' => $subj_lab,
            'sec_id' => (int)$row['sec_id'],
            'sec_name' => $row['sec_name'],
            'rm_name' => $row['rm_name'],
            'bd_desc' => $row['bd_desc']
        ];
    }
    
    $stmt->close();
    
    return [
        'schedules' => $schedules,
        'regular_units' => $regular_units,
        'overload_units' => $overload_units,
        'regular_lec_hours' => $regular_lec_hours,
        'regular_lab_hours' => $regular_lab_hours,
        'overload_lec_hours' => $overload_lec_hours,
        'overload_lab_hours' => $overload_lab_hours,
        'num_preparations' => count($unique_subjects)
    ];
}

/**
 * Get department head name for a department
 * Returns the name of the Admin user in the specified department
 */
function getDepartmentHead($dept_id, $conn) {
    if (!$dept_id) {
        return '';
    }
    
    $stmt = $conn->prepare("
        SELECT 
            CONCAT(a.fname, ' ', 
                   COALESCE(a.minitial, ''), ' ', 
                   a.lname,
                   CASE WHEN a.suffix IS NOT NULL AND a.suffix != '' THEN CONCAT(' ', a.suffix) ELSE '' END) as full_name
        FROM account a
        INNER JOIN user_roles ur ON a.acc_id = ur.acc_id
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE a.dept_id = ?
          AND r.role_name = 'Admin'
          AND a.acc_status = 'Active'
        ORDER BY a.acc_id ASC
        LIMIT 1
    ");
    $stmt->bind_param('i', $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row && isset($row['full_name']) ? strtoupper(trim($row['full_name'])) : '';
}

/**
 * Generate HTML for a single workload form
 */
function generateSingleForm($instructor, $workload_data, $sy_data, $semester, $logoPath, $conn) {
    // Separate regular and overload schedules
    $regular_schedules = [];
    $overload_schedules = [];
    
    foreach ($workload_data['schedules'] as $schedule) {
        if ($schedule['is_overtime'] === 'Yes') {
            $overload_schedules[] = $schedule;
        } else {
            $regular_schedules[] = $schedule;
        }
    }
    
    // Group schedules by subject-section for proper display
    $regular_grouped = groupSchedulesBySubject($regular_schedules);
    $overload_grouped = groupSchedulesBySubject($overload_schedules);
    
    // Header
    $html = '<div class="form-header">
        <div class="logo-section">
            <img src="' . htmlspecialchars($logoPath) . '" alt="EVSU Logo" onerror="this.style.display=\'none\'">
        </div>
        <div class="header-center">
            <h1>EASTERN VISAYAS STATE UNIVERSITY</h1>
            <h2>Tacloban City</h2>
            <h2 style="font-weight: bold; margin-top: 5px;">Title of Form: Teacher Workload Form</h2>
        </div>
        <div class="header-right">
            <div><strong>Control No.:</strong> EVSU-ACA-F-002</div>
            <div><strong>Revision No.:</strong> 02</div>
            <div><strong>Date:</strong> ' . date('F d, Y') . '</div>
            <div style="margin-top: 8px;"><strong>Semester:</strong> ' . htmlspecialchars($semester) . '</div>
            <div><strong>School Year:</strong> ' . htmlspecialchars(trim(preg_replace('/\s*-\s*(1st|2nd)\s+Semester\s*$/i', '', $sy_data['sy_name'] ?? '')) ?: ($sy_data['sy_year'] ?? $sy_data['sy_name'] ?? '')) . '</div>
        </div>
    </div>';
    
    // Instructor Info
    $html .= '<div class="instructor-info">
        <div class="info-row">
            <div class="info-label">Faculty Member:</div>
            <div class="info-value">' . htmlspecialchars($instructor['full_name']) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Academic Rank:</div>
            <div class="info-value">' . htmlspecialchars($instructor['rank'] ?? 'N/A') . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">College/Campus:</div>
            <div class="info-value">' . htmlspecialchars($instructor['dept_name'] ?? '') . '</div>
        </div>
    </div>';
    
    // REGULAR Workload Section
    $html .= '<div class="workload-section">
        <div class="section-title">REGULAR</div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2">Course No.</th>
                    <th rowspan="2">Descriptive Title</th>
                    <th rowspan="2">Subject Units</th>
                    <th rowspan="2">TIME</th>
                    <th rowspan="2">DAYS</th>
                    <th colspan="2">No. of Hrs./Week</th>
                    <th rowspan="2">No. of Students</th>
                    <th rowspan="2">Room No.</th>
                    <th rowspan="2">Course, Yr., & Sec.</th>
                </tr>
                <tr>
                    <th>Lec</th>
                    <th>Lab</th>
                </tr>
            </thead>
            <tbody>';
    
    $regular_total_units = 0;
    $regular_total_lec = 0;
    $regular_total_lab = 0;
    
    if (empty($regular_grouped)) {
        $html .= '<tr><td colspan="10" class="text-center">No regular assignments</td></tr>';
    } else {
        foreach ($regular_grouped as $group) {
            $first_schedule = $group['schedules'][0];
            $time_str = formatTimeRange($group['times']);
            $days_str = formatDays($group['days']);
            $room_str = $first_schedule['rm_name'] ?? '';
            $course_yr_sec = $first_schedule['sec_name'] ?? '';
            
            $regular_total_units += $first_schedule['subj_unit'];
            $regular_total_lec += $first_schedule['subj_lec'];
            $regular_total_lab += $first_schedule['subj_lab'];
            
            $html .= '<tr>
                <td class="text-center">' . htmlspecialchars($first_schedule['subj_code']) . '</td>
                <td>' . htmlspecialchars($first_schedule['subj_desc']) . '</td>
                <td class="text-center">' . $first_schedule['subj_unit'] . '</td>
                <td class="text-center">' . $time_str . '</td>
                <td class="text-center">' . $days_str . '</td>
                <td class="text-center">' . $first_schedule['subj_lec'] . '</td>
                <td class="text-center">' . $first_schedule['subj_lab'] . '</td>
                <td class="text-center"></td>
                <td class="text-center">' . htmlspecialchars($room_str) . '</td>
                <td class="text-center">' . htmlspecialchars($course_yr_sec) . '</td>
            </tr>';
        }
    }
    
    $html .= '<tr style="font-weight: bold;">
        <td colspan="2" class="text-center">Total</td>
        <td class="text-center">' . $regular_total_units . '</td>
        <td colspan="2"></td>
        <td class="text-center">' . $regular_total_lec . '</td>
        <td class="text-center">' . $regular_total_lab . '</td>
        <td colspan="3"></td>
    </tr>';
    
    $html .= '</tbody></table></div>';
    
    // OVERLOAD/PART-TIME Workload Section
    $html .= '<div class="workload-section">
        <div class="section-title">OVERLOAD/PART-TIME</div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2">Course No.</th>
                    <th rowspan="2">Descriptive Title</th>
                    <th rowspan="2">Subject Units</th>
                    <th rowspan="2">TIME</th>
                    <th rowspan="2">DAYS</th>
                    <th colspan="2">No. of Hrs./Week</th>
                    <th rowspan="2">No. of Students</th>
                    <th rowspan="2">Room No.</th>
                    <th rowspan="2">Course, Yr., & Sec.</th>
                </tr>
                <tr>
                    <th>Lec</th>
                    <th>Lab</th>
                </tr>
            </thead>
            <tbody>';
    
    $overload_total_units = 0;
    $overload_total_lec = 0;
    $overload_total_lab = 0;
    
    if (empty($overload_grouped)) {
        $html .= '<tr><td colspan="10" class="text-center">No overload/part-time assignments</td></tr>';
    } else {
        foreach ($overload_grouped as $group) {
            $first_schedule = $group['schedules'][0];
            $time_str = formatTimeRange($group['times']);
            $days_str = formatDays($group['days']);
            $room_str = $first_schedule['rm_name'] ?? '';
            $course_yr_sec = $first_schedule['sec_name'] ?? '';
            
            $overload_total_units += $first_schedule['subj_unit'];
            $overload_total_lec += $first_schedule['subj_lec'];
            $overload_total_lab += $first_schedule['subj_lab'];
            
            $html .= '<tr>
                <td class="text-center">' . htmlspecialchars($first_schedule['subj_code']) . '</td>
                <td>' . htmlspecialchars($first_schedule['subj_desc']) . '</td>
                <td class="text-center">' . $first_schedule['subj_unit'] . '</td>
                <td class="text-center">' . $time_str . '</td>
                <td class="text-center">' . $days_str . '</td>
                <td class="text-center">' . $first_schedule['subj_lec'] . '</td>
                <td class="text-center">' . $first_schedule['subj_lab'] . '</td>
                <td class="text-center"></td>
                <td class="text-center">' . htmlspecialchars($room_str) . '</td>
                <td class="text-center">' . htmlspecialchars($course_yr_sec) . '</td>
            </tr>';
        }
    }
    
    $html .= '<tr style="font-weight: bold;">
        <td colspan="2" class="text-center">Total</td>
        <td class="text-center">' . $overload_total_units . '</td>
        <td colspan="2"></td>
        <td class="text-center">' . $overload_total_lec . '</td>
        <td class="text-center">' . $overload_total_lab . '</td>
        <td colspan="3"></td>
    </tr>';
    
    $html .= '</tbody></table></div>';
    
    // Other In-School Involvement/Assignment Per Week
    $html .= '<div class="other-assignments">
        <div><strong>Other In-School Involvement/Assignment Per Week:</strong></div>
        <div>
            <input type="checkbox" ' . ($instructor['administration_hours'] > 0 ? 'checked' : '') . ' disabled>
            <strong>Administrative:</strong> ' . $instructor['administration_hours'] . ' Hours
        </div>
        <div>
            <strong>Instruction:</strong> ' . $instructor['instruction_hours'] . ' Hours
        </div>
        <div>
            <strong>Research:</strong> ' . $instructor['research_hours'] . ' Hours
        </div>
        <div>
            <strong>Extension Services:</strong> ' . $instructor['extension_hours'] . ' Hours
        </div>
        <div>
            <strong>Consultation and other</strong>
        </div>
        <div>
            <strong>Instructional Functions:</strong> ' . $instructor['instructional_functions_hours'] . ' Hours
        </div>
        <div>
            <strong>Consultation (Please Specify):</strong> ' . $instructor['consultation_hours'] . ' Hours
        </div>
    </div>';
    
    // Summary Computations
    $num_classes = count(array_unique(array_column($workload_data['schedules'], 'sec_id')));
    $num_preparations = $workload_data['num_preparations'];
    
    $html .= '<div class="summary-section">
        <div class="summary-row">
            <span class="summary-label">No. of Classes:</span>
            <span>' . $num_classes . '</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">No. of Preparation:</span>
            <span>' . $num_preparations . '</span>
        </div>
    </div>';
    
    // Get department head name
    $dept_head_name = getDepartmentHead($instructor['dept_id'] ?? 0, $conn);
    
    // Fixed names for university officials
    $campus_director = 'MARICEL A. GOMEZ, Ph.D.';
    $vp_academic = 'BENEDICTO T. MILITANTE JR., Ph.D., J.D.';
    $university_president = 'DENNIS C. DE PAZ, Ph.D.';
    
    // Signature Section
    $html .= '<div class="signature-section">
        <div class="signature-box">
            <div class="signature-name">' . htmlspecialchars(strtoupper($instructor['full_name'])) . '</div>
            <div class="signature-title">Faculty</div>
        </div>
        <div class="signature-box">
            <div class="signature-name">' . htmlspecialchars($dept_head_name) . '</div>
            <div class="signature-title">Head, ' . htmlspecialchars($instructor['dept_name'] ?? 'Department') . '</div>
        </div>
        <div class="signature-box">
            <div class="signature-name">' . htmlspecialchars($campus_director) . '</div>
            <div class="signature-title">Campus Director</div>
        </div>
        <div class="signature-box">
            <div class="signature-name">' . htmlspecialchars($vp_academic) . '</div>
            <div class="signature-title">Vice President for Academic Affairs</div>
        </div>
        <div class="signature-box">
            <div class="signature-name">' . htmlspecialchars($university_president) . '</div>
            <div class="signature-title">University President</div>
        </div>
    </div>';
    
    return $html;
}

/**
 * Group schedules by subject-section
 */
function groupSchedulesBySubject($schedules) {
    $grouped = [];
    
    foreach ($schedules as $schedule) {
        $key = $schedule['subj_code'] . '_' . $schedule['sec_name'];
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'schedules' => [],
                'times' => [],
                'days' => []
            ];
        }
        
        $grouped[$key]['schedules'][] = $schedule;
        $time_str = date('g:i A', strtotime($schedule['schd_start'])) . '-' . date('g:i A', strtotime($schedule['schd_end']));
        if (!in_array($time_str, $grouped[$key]['times'])) {
            $grouped[$key]['times'][] = $time_str;
        }
        if (!in_array($schedule['schd_day'], $grouped[$key]['days'])) {
            $grouped[$key]['days'][] = $schedule['schd_day'];
        }
    }
    
    // Sort by subject code and section
    uasort($grouped, function($a, $b) {
        $a_code = $a['schedules'][0]['subj_code'];
        $b_code = $b['schedules'][0]['subj_code'];
        if ($a_code === $b_code) {
            $a_sec = $a['schedules'][0]['sec_name'];
            $b_sec = $b['schedules'][0]['sec_name'];
            return strcmp($a_sec, $b_sec);
        }
        return strcmp($a_code, $b_code);
    });
    
    return array_values($grouped);
}

/**
 * Format time range
 */
function formatTimeRange($times) {
    if (empty($times)) return '';
    return implode('<br>', $times);
}

/**
 * Format days
 */
function formatDays($days) {
    if (empty($days)) return '';
    $day_map = [
        'Mon' => 'Mon',
        'Tue' => 'Tue',
        'Wed' => 'Wed',
        'Thu' => 'Thu',
        'Fri' => 'Fri',
        'Sat' => 'Sat',
        'Sun' => 'Sun'
    ];
    $formatted = [];
    foreach ($days as $day) {
        if (isset($day_map[$day])) {
            $formatted[] = $day_map[$day];
        }
    }
    return implode(', ', $formatted);
}

/**
 * Generate PDF and force download
 * Uses browser's print-to-PDF functionality with automatic download trigger
 */
function generatePDFDownload($html, $sy_data, $semester) {
    // Try to use DomPDF if available
    $useDomPDF = false;
    
    // Check if DomPDF is available
    if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/../../../vendor/autoload.php';
            if (class_exists('\Dompdf\Dompdf')) {
                $useDomPDF = true;
            }
        } catch (Exception $e) {
            // DomPDF not available
        }
    }
    
    if ($useDomPDF) {
        // Use DomPDF for server-side PDF generation
        try {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            $filename = 'Teacher_Workload_Forms_' . 
                       preg_replace('/[^a-zA-Z0-9_-]/', '_', ($sy_data['sy_name'] ?? '')) . '_' . 
                       preg_replace('/[^a-zA-Z0-9_-]/', '_', htmlspecialchars($semester)) . '_' . 
                       date('Y-m-d') . '.pdf';
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            echo $dompdf->output();
            exit;
        } catch (Exception $e) {
            error_log('DomPDF Error: ' . $e->getMessage());
            // Fall through to browser method if DomPDF fails
        }
    }
    
    // Fallback: Use browser's print-to-PDF
    // Return HTML that will be opened in new tab for printing
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
?>

