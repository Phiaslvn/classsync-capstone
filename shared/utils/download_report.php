<?php
/**
 * Report Download Handler
 * Generates and downloads reports in PDF, CSV, and DOC formats
 */

require_once 'includes/db_connect.php';
require_once 'includes/dashboard_permissions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userInfo = getUserInfo();
if (!$userInfo) {
    http_response_code(401);
    echo json_encode(['error' => 'User information not found']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$reportType = $input['reportType'] ?? 'schedule_summary';
$format = $input['format'] ?? 'pdf';
$dateRange = $input['dateRange'] ?? 'current_week';
$startDate = $input['startDate'] ?? '';
$endDate = $input['endDate'] ?? '';
$userId = $input['userId'] ?? $userInfo['acc_id'];
$userName = $input['userName'] ?? $userInfo['fname'] . ' ' . $userInfo['lname'];
$department = $input['department'] ?? $userInfo['dept_name'] ?? 'All Departments';

// Get schedule data
$schedules = [];
try {
    $stmt = $conn->prepare("
        SELECT s.schd_id, s.schd_day, s.schd_start, s.schd_end, s.schd_type,
               r.rm_name, sub.subj_code, s.schd_status
        FROM schedule s
        LEFT JOIN room r ON s.rm_id = r.rm_id
        LEFT JOIN subject sub ON s.subj_id = sub.subj_id
        WHERE s.inst_id = ?
        ORDER BY s.schd_day, s.schd_start
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting schedules: " . $e->getMessage());
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = $reportType . '_' . $timestamp;

// Set headers based on format
switch ($format) {
    case 'pdf':
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        generatePDFReport($reportType, $schedules, $userName, $department);
        break;
        
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        generateCSVReport($reportType, $schedules, $userName, $department);
        break;
        
    case 'doc':
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename="' . $filename . '.doc"');
        generateDOCReport($reportType, $schedules, $userName, $department);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid format']);
        exit;
}

function generatePDFReport($reportType, $schedules, $userName, $department) {
    // Generate HTML content like DOC format
    $html = generateReportHTML($reportType, $schedules, $userName, $department);
    
    // Convert HTML to PDF using a more sophisticated approach
    $pdf = createHTMLBasedPDF($html);
    echo $pdf;
}

function generateReportText($reportType, $schedules, $userName, $department) {
    $text = "                    EASTERN VISAYAS STATE UNIVERSITY\n";
    $text .= "                           ORMOC CAMPUS\n";
    $text .= "                    OFFICIAL REPORT DOCUMENT\n\n";
    $text .= "REPORT TYPE: " . strtoupper(str_replace('_', ' ', $reportType)) . "\n";
    $text .= "GENERATED FOR: " . $userName . "\n";
    $text .= "DEPARTMENT: " . $department . "\n";
    $text .= "GENERATED DATE: " . date('F d, Y \a\t h:i A') . "\n\n";
    
    switch ($reportType) {
        case 'schedule_summary':
            $text .= "SCHEDULE SUMMARY REPORT\n\n";
            $text .= sprintf("%-20s %-10s\n", "METRIC", "VALUE");
            $text .= str_repeat("-", 32) . "\n";
            $text .= sprintf("%-20s %-10d\n", "Total Schedules", count($schedules));
            $text .= sprintf("%-20s %-10d\n", "Active Schedules", count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Active')));
            $text .= sprintf("%-20s %-10d\n", "Inactive Schedules", count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Inactive')));
            $text .= sprintf("%-20s %-10d\n", "Pending Schedules", count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Pending')));
            $text .= sprintf("%-20s %-10.1f\n", "Weekly Hours", count($schedules) * 1.5);
            $text .= sprintf("%-20s %-10d\n", "Unique Rooms", count(array_unique(array_column($schedules, 'rm_name'))));
            break;
            
        case 'teaching_load':
            $text .= "TEACHING LOAD ANALYSIS\n\n";
            $text .= sprintf("%-10s %-8s %-6s %-9s %-6s %-8s\n", "DAY", "CLASSES", "HOURS", "SUBJECTS", "ROOMS", "LOAD %");
            $text .= str_repeat("-", 50) . "\n";
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($days as $day) {
                $daySchedules = array_filter($schedules, fn($s) => $s['schd_day'] === $day);
                $classes = count($daySchedules);
                $hours = $classes * 1.5;
                $subjects = count(array_unique(array_column($daySchedules, 'subj_code')));
                $rooms = count(array_unique(array_column($daySchedules, 'rm_name')));
                $loadPercent = round(($hours / 8) * 100);
                $text .= sprintf("%-10s %-8d %-6.1f %-9d %-6d %-8d%%\n", $day, $classes, $hours, $subjects, $rooms, $loadPercent);
            }
            break;
            
        case 'room_utilization':
            $text .= "ROOM UTILIZATION REPORT\n\n";
            $text .= sprintf("%-15s %-8s %-10s\n", "ROOM", "CLASSES", "PERCENTAGE");
            $text .= str_repeat("-", 35) . "\n";
            $roomCounts = [];
            foreach ($schedules as $schedule) {
                $room = $schedule['rm_name'] ?? 'N/A';
                $roomCounts[$room] = ($roomCounts[$room] ?? 0) + 1;
            }
            $total = count($schedules);
            foreach ($roomCounts as $room => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                $text .= sprintf("%-15s %-8d %-10.2f%%\n", $room, $count, $percentage);
            }
            break;
            
        case 'subject_analysis':
            $text .= "SUBJECT ANALYSIS REPORT\n\n";
            $text .= sprintf("%-15s %-8s %-10s\n", "SUBJECT", "CLASSES", "PERCENTAGE");
            $text .= str_repeat("-", 35) . "\n";
            $subjectCounts = [];
            foreach ($schedules as $schedule) {
                $subject = $schedule['subj_code'] ?? 'N/A';
                $subjectCounts[$subject] = ($subjectCounts[$subject] ?? 0) + 1;
            }
            $total = count($schedules);
            foreach ($subjectCounts as $subject => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                $text .= sprintf("%-15s %-8d %-10.2f%%\n", $subject, $count, $percentage);
            }
            break;
            
        case 'weekly_overview':
            $text .= "WEEKLY SCHEDULE OVERVIEW\n\n";
            $timeSlots = ['8:00 AM', '9:30 AM', '11:00 AM', '1:00 PM', '2:30 PM', '4:00 PM'];
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $text .= sprintf("%-10s", "TIME");
            foreach ($days as $day) {
                $text .= sprintf("%-12s", $day);
            }
            $text .= "\n" . str_repeat("-", 80) . "\n";
            foreach ($timeSlots as $timeSlot) {
                $text .= sprintf("%-10s", $timeSlot);
                foreach ($days as $day) {
                    $daySchedules = array_filter($schedules, fn($s) => $s['schd_day'] === $day && $s['schd_start'] === $timeSlot);
                    if (count($daySchedules) > 0) {
                        $schedule = $daySchedules[0];
                        $text .= sprintf("%-12s", $schedule['subj_code']);
                    } else {
                        $text .= sprintf("%-12s", "-");
                    }
                }
                $text .= "\n";
            }
            break;
            
        case 'performance_metrics':
            $text .= "PERFORMANCE METRICS REPORT\n\n";
            $text .= sprintf("%-20s %-10s\n", "METRIC", "VALUE");
            $text .= str_repeat("-", 32) . "\n";
            $text .= sprintf("%-20s %-10s\n", "Schedule Completion", "95%");
            $text .= sprintf("%-20s %-10s\n", "Punctuality Rate", "98%");
            $text .= sprintf("%-20s %-10s\n", "Teaching Efficiency", "92%");
            break;
            
        case 'my_schedules':
            $text .= "MY SCHEDULES REPORT\n\n";
            if (empty($schedules)) {
                $text .= "No schedules found for this user.\n";
            } else {
                $text .= sprintf("%-8s %-12s %-12s %-15s %-15s %-10s %-10s\n", "ID", "DAY", "START TIME", "END TIME", "SUBJECT", "ROOM", "STATUS");
                $text .= str_repeat("-", 90) . "\n";
                foreach ($schedules as $schedule) {
                    $text .= sprintf("%-8s %-12s %-12s %-15s %-15s %-10s %-10s\n", 
                        $schedule['schd_id'],
                        $schedule['schd_day'],
                        $schedule['schd_start'],
                        $schedule['schd_end'],
                        $schedule['subj_code'] ?? 'N/A',
                        $schedule['rm_name'] ?? 'N/A',
                        $schedule['schd_status']
                    );
                }
            }
            break;
    }
    
    // Add footer
    $text .= "\n\n";
    $text .= "This is an official document generated by the EVSU-OC Scheduling System\n";
    $text .= "Eastern Visayas State University - Ormoc Campus\n";
    $text .= "Generated on " . date('F d, Y \a\t h:i A') . "\n";
    
    return $text;
}

function createHTMLBasedPDF($html) {
    // Create a PDF that matches the DOC format exactly
    $pdf = "%PDF-1.4\n";
    
    // Catalog object
    $pdf .= "1 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Catalog\n";
    $pdf .= "/Pages 2 0 R\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n\n";
    
    // Pages object
    $pdf .= "2 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Pages\n";
    $pdf .= "/Kids [3 0 R]\n";
    $pdf .= "/Count 1\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n\n";
    
    // Page object
    $pdf .= "3 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Page\n";
    $pdf .= "/Parent 2 0 R\n";
    $pdf .= "/MediaBox [0 0 612 792]\n";
    $pdf .= "/Contents 4 0 R\n";
    $pdf .= "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >>\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n\n";
    
    // Font objects
    $pdf .= "5 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Font\n";
    $pdf .= "/Subtype /Type1\n";
    $pdf .= "/BaseFont /Helvetica\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n\n";
    
    $pdf .= "6 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Font\n";
    $pdf .= "/Subtype /Type1\n";
    $pdf .= "/BaseFont /Helvetica-Bold\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n\n";
    
    // Content stream - convert HTML to PDF format
    $stream = convertHTMLToPDFStream($html);
    $pdf .= "4 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Length " . strlen($stream) . "\n";
    $pdf .= ">>\n";
    $pdf .= "stream\n";
    $pdf .= $stream;
    $pdf .= "\nendstream\n";
    $pdf .= "endobj\n\n";
    
    // Cross-reference table
    $pdf .= "xref\n";
    $pdf .= "0 7\n";
    $pdf .= "0000000000 65535 f \n";
    $pdf .= "0000000009 00000 n \n";
    $pdf .= "0000000058 00000 n \n";
    $pdf .= "0000000115 00000 n \n";
    $pdf .= "0000000204 00000 n \n";
    $pdf .= "0000000300 00000 n \n";
    $pdf .= "0000000400 00000 n \n";
    
    // Trailer
    $pdf .= "trailer\n";
    $pdf .= "<<\n";
    $pdf .= "/Size 7\n";
    $pdf .= "/Root 1 0 R\n";
    $pdf .= ">>\n";
    $pdf .= "startxref\n";
    $pdf .= "0\n";
    $pdf .= "%%EOF\n";
    
    return $pdf;
}

function convertHTMLToPDFStream($html) {
    $stream = "BT\n";
    $stream .= "/F1 12 Tf\n";
    $stream .= "50 750 Td\n";
    
    // Parse HTML and convert to PDF format
    $lines = parseHTMLToPDFLines($html);
    $y = 750;
    
    foreach ($lines as $line) {
        if ($y < 50) {
            $stream .= "ET\n";
            $stream .= "BT\n";
            $stream .= "/F1 12 Tf\n";
            $stream .= "50 750 Td\n";
            $y = 750;
        }
        
        // Clean the line for PDF
        $line = str_replace('\\', '\\\\', $line);
        $line = str_replace('(', '\\(', $line);
        $line = str_replace(')', '\\)', $line);
        
        // Add text to stream
        $stream .= "(" . $line . ") Tj\n";
        
        // Adjust line spacing
        if (strpos($line, 'EASTERN VISAYAS STATE UNIVERSITY') !== false || 
            strpos($line, 'ORMOC CAMPUS') !== false ||
            strpos($line, 'OFFICIAL REPORT DOCUMENT') !== false) {
            $stream .= "0 -20 Td\n";  // More space for headers
            $y -= 20;
        } else if (strpos($line, 'REPORT TYPE:') !== false || 
                   strpos($line, 'GENERATED FOR:') !== false ||
                   strpos($line, 'DEPARTMENT:') !== false ||
                   strpos($line, 'GENERATED DATE:') !== false) {
            $stream .= "0 -18 Td\n";  // Space for info lines
            $y -= 18;
        } else if (strpos($line, 'MY SCHEDULES REPORT') !== false) {
            $stream .= "0 -20 Td\n";  // Space for section title
            $y -= 20;
        } else if (strpos($line, '---') !== false) {
            $stream .= "0 -8 Td\n";   // Less space for separators
            $y -= 8;
        } else {
            $stream .= "0 -15 Td\n";  // Normal spacing
            $y -= 15;
        }
    }
    
    $stream .= "ET\n";
    return $stream;
}

function parseHTMLToPDFLines($html) {
    $lines = [];
    
    // Extract header information
    if (preg_match('/<div class=\'school-name\'>(.*?)<\/div>/', $html, $matches)) {
        $lines[] = "                    " . $matches[1];
    }
    if (preg_match('/<div class=\'campus\'>(.*?)<\/div>/', $html, $matches)) {
        $lines[] = "                           " . $matches[1];
    }
    if (preg_match('/<div class=\'report-title\'>(.*?)<\/div>/', $html, $matches)) {
        $lines[] = "                    " . $matches[1];
    }
    
    $lines[] = ""; // Empty line
    
    // Extract report info
    if (preg_match('/<strong>REPORT TYPE:<\/strong> (.*?)<\/p>/', $html, $matches)) {
        $lines[] = "REPORT TYPE: " . $matches[1];
    }
    if (preg_match('/<strong>GENERATED FOR:<\/strong> (.*?)<\/p>/', $html, $matches)) {
        $lines[] = "GENERATED FOR: " . $matches[1];
    }
    if (preg_match('/<strong>DEPARTMENT:<\/strong> (.*?)<\/p>/', $html, $matches)) {
        $lines[] = "DEPARTMENT: " . $matches[1];
    }
    if (preg_match('/<strong>GENERATED DATE:<\/strong> (.*?)<\/p>/', $html, $matches)) {
        $lines[] = "GENERATED DATE: " . $matches[1];
    }
    
    $lines[] = ""; // Empty line
    
    // Extract section title
    if (preg_match('/<div class=\'section-title\'><strong>(.*?)<\/strong><\/div>/', $html, $matches)) {
        $lines[] = $matches[1];
        $lines[] = ""; // Empty line
    }
    
    // Extract table data
    if (preg_match('/<table.*?>(.*?)<\/table>/s', $html, $tableMatch)) {
        $tableContent = $tableMatch[1];
        
        // Extract header row
        if (preg_match('/<tr.*?>(.*?)<\/tr>/s', $tableContent, $headerMatch)) {
            $headerCells = [];
            preg_match_all('/<th[^>]*>(.*?)<\/th>/', $headerMatch[1], $headerCells);
            if (!empty($headerCells[1])) {
                $lines[] = sprintf("%-8s %-12s %-12s %-15s %-15s %-10s %-10s", 
                    $headerCells[1][0], $headerCells[1][1], $headerCells[1][2], 
                    $headerCells[1][3], $headerCells[1][4], $headerCells[1][5], $headerCells[1][6]);
                $lines[] = str_repeat("-", 90);
            }
        }
        
        // Extract data rows
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $tableContent, $rowMatches);
        foreach ($rowMatches[1] as $row) {
            if (strpos($row, '<th') === false) { // Skip header row
                $cells = [];
                preg_match_all('/<td[^>]*>(.*?)<\/td>/', $row, $cells);
                if (!empty($cells[1])) {
                    $lines[] = sprintf("%-8s %-12s %-12s %-15s %-15s %-10s %-10s", 
                        $cells[1][0], $cells[1][1], $cells[1][2], 
                        $cells[1][3], $cells[1][4], $cells[1][5], $cells[1][6]);
                }
            }
        }
    }
    
    $lines[] = ""; // Empty line
    $lines[] = "This is an official document generated by the EVSU-OC Scheduling System";
    $lines[] = "Eastern Visayas State University - Ormoc Campus";
    $lines[] = "Generated on " . date('F d, Y \a\t h:i A');
    
    return $lines;
}

function generateCSVReport($reportType, $schedules, $userName, $department) {
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, ['Report Type', 'Generated For', 'Department', 'Generated Date']);
    fputcsv($output, [ucwords(str_replace('_', ' ', $reportType)), $userName, $department, date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    switch ($reportType) {
        case 'schedule_summary':
            fputcsv($output, ['Schedule Summary Report']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Schedules', count($schedules)]);
            fputcsv($output, ['Active Schedules', count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Active'))]);
            fputcsv($output, ['Inactive Schedules', count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Inactive'))]);
            fputcsv($output, ['Pending Schedules', count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Pending'))]);
            fputcsv($output, ['Weekly Hours', count($schedules) * 1.5]);
            fputcsv($output, ['Unique Rooms', count(array_unique(array_column($schedules, 'rm_name')))]);
            break;
            
        case 'teaching_load':
            fputcsv($output, ['Teaching Load Report']);
            fputcsv($output, ['Day', 'Classes', 'Hours', 'Subjects', 'Rooms', 'Load %']);
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($days as $day) {
                $daySchedules = array_filter($schedules, fn($s) => $s['schd_day'] === $day);
                $classes = count($daySchedules);
                $hours = $classes * 1.5;
                $subjects = count(array_unique(array_column($daySchedules, 'subj_code')));
                $rooms = count(array_unique(array_column($daySchedules, 'rm_name')));
                $loadPercent = round(($hours / 8) * 100);
                fputcsv($output, [$day, $classes, $hours, $subjects, $rooms, $loadPercent . '%']);
            }
            break;
            
        case 'room_utilization':
            fputcsv($output, ['Room Utilization Report']);
            fputcsv($output, ['Room', 'Classes', 'Percentage']);
            $roomCounts = [];
            foreach ($schedules as $schedule) {
                $room = $schedule['rm_name'] ?? 'N/A';
                $roomCounts[$room] = ($roomCounts[$room] ?? 0) + 1;
            }
            $total = count($schedules);
            foreach ($roomCounts as $room => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                fputcsv($output, [$room, $count, $percentage . '%']);
            }
            break;
            
        case 'subject_analysis':
            fputcsv($output, ['Subject Analysis Report']);
            fputcsv($output, ['Subject', 'Classes', 'Percentage']);
            $subjectCounts = [];
            foreach ($schedules as $schedule) {
                $subject = $schedule['subj_code'] ?? 'N/A';
                $subjectCounts[$subject] = ($subjectCounts[$subject] ?? 0) + 1;
            }
            $total = count($schedules);
            foreach ($subjectCounts as $subject => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                fputcsv($output, [$subject, $count, $percentage . '%']);
            }
            break;
            
        case 'weekly_overview':
            fputcsv($output, ['Weekly Schedule Overview']);
            fputcsv($output, ['Time', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
            $timeSlots = ['8:00 AM', '9:30 AM', '11:00 AM', '1:00 PM', '2:30 PM', '4:00 PM'];
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($timeSlots as $timeSlot) {
                $row = [$timeSlot];
                foreach ($days as $day) {
                    $daySchedules = array_filter($schedules, fn($s) => $s['schd_day'] === $day && $s['schd_start'] === $timeSlot);
                    if (count($daySchedules) > 0) {
                        $schedule = $daySchedules[0];
                        $row[] = $schedule['subj_code'] . ' (' . $schedule['rm_name'] . ')';
                    } else {
                        $row[] = '-';
                    }
                }
                fputcsv($output, $row);
            }
            break;
            
        case 'performance_metrics':
            fputcsv($output, ['Performance Metrics Report']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Schedule Completion', '95%']);
            fputcsv($output, ['Punctuality Rate', '98%']);
            fputcsv($output, ['Teaching Efficiency', '92%']);
            break;
    }
    
    fclose($output);
}

function generateDOCReport($reportType, $schedules, $userName, $department) {
    $html = generateReportHTML($reportType, $schedules, $userName, $department);
    
    // Convert HTML to DOC format with professional styling
    $doc = "<html xmlns:o='urn:schemas-microsoft-com:office:office' 
                xmlns:w='urn:schemas-microsoft-com:office:word' 
                xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset='utf-8'>
                <title>EVSU-OC Report</title>
                <style>
                    body { 
                        font-family: 'Times New Roman', serif; 
                        margin: 40px; 
                        line-height: 1.6;
                        color: #333;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 3px solid #8B0000;
                        padding-bottom: 20px;
                    }
                    .school-name {
                        font-size: 18px;
                        font-weight: bold;
                        color: #8B0000;
                        margin-bottom: 5px;
                        text-align: center;
                    }
                    .campus {
                        font-size: 16px;
                        color: #8B0000;
                        margin-bottom: 15px;
                        text-align: center;
                    }
                    .report-title {
                        font-size: 14px;
                        color: #666;
                        font-style: italic;
                        text-align: center;
                    }
                    .report-info {
                        margin: 20px 0;
                        padding: 15px;
                        background-color: #f8f9fa;
                        border-left: 4px solid #8B0000;
                    }
                    table { 
                        border-collapse: collapse; 
                        width: 100%; 
                        margin: 20px 0;
                    }
                    th, td { 
                        border: 1px solid #ddd; 
                        padding: 12px; 
                        text-align: left; 
                    }
                    th { 
                        background-color: #8B0000; 
                        color: white;
                        font-weight: bold;
                    }
                    .section-title {
                        color: #8B0000;
                        font-size: 16px;
                        font-weight: bold;
                        margin: 25px 0 15px 0;
                        border-bottom: 2px solid #8B0000;
                        padding-bottom: 5px;
                    }
                    .footer {
                        margin-top: 40px;
                        text-align: center;
                        font-size: 12px;
                        color: #666;
                        border-top: 1px solid #ddd;
                        padding-top: 15px;
                    }
                </style>
            </head>
            <body>" . $html . "</body></html>";
    
    echo $doc;
}

function generateReportHTML($reportType, $schedules, $userName, $department) {
    $html = "<div class='header'>
                <div class='school-name'>EASTERN VISAYAS STATE UNIVERSITY</div>
                <div class='campus'>ORMOC CAMPUS</div>
                <div class='report-title'>OFFICIAL REPORT DOCUMENT</div>
            </div>
            
            <div class='report-info'>
                <p><strong>REPORT TYPE:</strong> " . strtoupper(str_replace('_', ' ', $reportType)) . "</p>
                <p><strong>GENERATED FOR:</strong> $userName</p>
                <p><strong>DEPARTMENT:</strong> $department</p>
                <p><strong>GENERATED DATE:</strong> " . date('F d, Y \a\t h:i A') . "</p>
            </div>";
    
    switch ($reportType) {
        case 'schedule_summary':
            $html .= "<div class='section-title'>Schedule Summary</div>
                     <table>
                         <tr><th>Metric</th><th>Value</th></tr>
                         <tr><td>Total Schedules</td><td>" . count($schedules) . "</td></tr>
                         <tr><td>Active Schedules</td><td>" . count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Active')) . "</td></tr>
                         <tr><td>Inactive Schedules</td><td>" . count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Inactive')) . "</td></tr>
                         <tr><td>Pending Schedules</td><td>" . count(array_filter($schedules, fn($s) => $s['schd_status'] === 'Pending')) . "</td></tr>
                         <tr><td>Weekly Hours</td><td>" . (count($schedules) * 1.5) . "</td></tr>
                         <tr><td>Unique Rooms</td><td>" . count(array_unique(array_column($schedules, 'rm_name'))) . "</td></tr>
                     </table>";
            break;
            
        case 'teaching_load':
            $html .= "<div class='section-title'>Teaching Load Analysis</div>
                     <table>
                         <tr><th>Day</th><th>Classes</th><th>Hours</th><th>Subjects</th><th>Rooms</th><th>Load %</th></tr>";
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($days as $day) {
                $daySchedules = array_filter($schedules, fn($s) => $s['schd_day'] === $day);
                $classes = count($daySchedules);
                $hours = $classes * 1.5;
                $subjects = count(array_unique(array_column($daySchedules, 'subj_code')));
                $rooms = count(array_unique(array_column($daySchedules, 'rm_name')));
                $loadPercent = round(($hours / 8) * 100);
                $html .= "<tr><td>$day</td><td>$classes</td><td>$hours</td><td>$subjects</td><td>$rooms</td><td>$loadPercent%</td></tr>";
            }
            $html .= "</table>";
            break;
            
        case 'room_utilization':
            $html .= "<div class='section-title'>Room Utilization</div>
                     <table>
                         <tr><th>Room</th><th>Classes</th><th>Percentage</th></tr>";
            $roomCounts = [];
            foreach ($schedules as $schedule) {
                $room = $schedule['rm_name'] ?? 'N/A';
                $roomCounts[$room] = ($roomCounts[$room] ?? 0) + 1;
            }
            $total = count($schedules);
            foreach ($roomCounts as $room => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                $html .= "<tr><td>$room</td><td>$count</td><td>$percentage%</td></tr>";
            }
            $html .= "</table>";
            break;
            
        case 'subject_analysis':
            $html .= "<div class='section-title'>Subject Analysis</div>
                     <table>
                         <tr><th>Subject</th><th>Classes</th><th>Percentage</th></tr>";
            $subjectCounts = [];
            foreach ($schedules as $schedule) {
                $subject = $schedule['subj_code'] ?? 'N/A';
                $subjectCounts[$subject] = ($subjectCounts[$subject] ?? 0) + 1;
            }
            $total = count($schedules);
            foreach ($subjectCounts as $subject => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                $html .= "<tr><td>$subject</td><td>$count</td><td>$percentage%</td></tr>";
            }
            $html .= "</table>";
            break;
            
        case 'weekly_overview':
            $html .= "<div class='section-title'>Weekly Schedule Overview</div>
                     <table>
                         <tr><th>Time</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th><th>Saturday</th></tr>";
            $timeSlots = ['8:00 AM', '9:30 AM', '11:00 AM', '1:00 PM', '2:30 PM', '4:00 PM'];
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($timeSlots as $timeSlot) {
                $html .= "<tr><td>$timeSlot</td>";
                foreach ($days as $day) {
                    $daySchedules = array_filter($schedules, fn($s) => $s['schd_day'] === $day && $s['schd_start'] === $timeSlot);
                    if (count($daySchedules) > 0) {
                        $schedule = $daySchedules[0];
                        $html .= "<td>" . $schedule['subj_code'] . " (" . $schedule['rm_name'] . ")</td>";
                    } else {
                        $html .= "<td>-</td>";
                    }
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
            break;
            
        case 'performance_metrics':
            $html .= "<div class='section-title'>Performance Metrics</div>
                     <table>
                         <tr><th>Metric</th><th>Value</th></tr>
                         <tr><td>Schedule Completion</td><td>95%</td></tr>
                         <tr><td>Punctuality Rate</td><td>98%</td></tr>
                         <tr><td>Teaching Efficiency</td><td>92%</td></tr>
                     </table>";
            break;
            
        case 'my_schedules':
            $html .= "<div class='section-title'><strong>MY SCHEDULES REPORT</strong></div>";
            if (empty($schedules)) {
                $html .= "<p>No schedules found for this user.</p>";
            } else {
                $html .= "<table>
                             <tr>
                                 <th>ID</th>
                                 <th>Day</th>
                                 <th>Start Time</th>
                                 <th>End Time</th>
                                 <th>Subject</th>
                                 <th>Room</th>
                                 <th>Status</th>
                             </tr>";
                foreach ($schedules as $schedule) {
                    $statusClass = '';
                    switch($schedule['schd_status']) {
                        case 'Active': $statusClass = 'success'; break;
                        case 'Inactive': $statusClass = 'danger'; break;
                        case 'Pending': $statusClass = 'warning'; break;
                        default: $statusClass = 'secondary'; break;
                    }
                    $html .= "<tr>
                                 <td>" . $schedule['schd_id'] . "</td>
                                 <td>" . $schedule['schd_day'] . "</td>
                                 <td>" . $schedule['schd_start'] . "</td>
                                 <td>" . $schedule['schd_end'] . "</td>
                                 <td>" . ($schedule['subj_code'] ?? 'N/A') . "</td>
                                 <td>" . ($schedule['rm_name'] ?? 'N/A') . "</td>
                                 <td><span class='badge bg-" . $statusClass . "'>" . $schedule['schd_status'] . "</span></td>
                             </tr>";
                }
                $html .= "</table>";
            }
            break;
    }
    
    // Add footer
    $html .= "<div class='footer'>
                <p>This is an official document generated by the EVSU-OC Scheduling System</p>
                <p>Eastern Visayas State University - Ormoc Campus</p>
                <p>Generated on " . date('F d, Y \a\t h:i A') . "</p>
            </div>";
    
    return $html;
}

$conn->close();
?>
