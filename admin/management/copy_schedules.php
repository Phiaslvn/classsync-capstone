<?php
/**
 * Copy Schedules API
 * Copies schedules from source school year/term to destination
 */

// Suppress PHP errors from appearing in output (we'll handle them ourselves)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/instructor_department_appointments.php';

header('Content-Type: application/json');

// Check database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}
if (!hasPermission('manage_schedules')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$source_sy_id = (int)($_POST['source_sy_id'] ?? 0);
$source_term = (int)($_POST['source_term'] ?? 0);
$dest_sy_id = (int)($_POST['dest_sy_id'] ?? 0);
$dest_term = (int)($_POST['dest_term'] ?? 0);
$instructor_id = (int)($_POST['instructor_id'] ?? 0); // Optional instructor ID
$program_substitution = isset($_POST['program_substitution']) && $_POST['program_substitution'] === '1'; // Program substitution enabled
$dest_program_id = $program_substitution ? (int)($_POST['dest_program_id'] ?? 0) : null; // Destination program ID when substitution is enabled
$selected_schedule_ids = isset($_POST['selected_schedules']) && is_array($_POST['selected_schedules']) 
    ? array_map('intval', $_POST['selected_schedules']) 
    : []; // Array of selected schedule IDs

// --- Validation ---
if (in_array(0, [$source_sy_id, $source_term, $dest_sy_id, $dest_term])) {
    echo json_encode(['success' => false, 'message' => 'Please select a source and destination school year and term.']);
    exit;
}

// Validate program substitution
if ($program_substitution && $dest_program_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a destination program when program substitution is enabled.']);
    exit;
}

if ($source_sy_id === $dest_sy_id && $source_term === $dest_term) {
    echo json_encode(['success' => false, 'message' => 'Source and destination cannot be the same.']);
    exit;
}

try {
    // Fetch source schedules first (conflict checking will happen later after section mapping)
    // If specific schedules are selected, only copy those; otherwise copy all
    if (!empty($selected_schedule_ids)) {
        // Copy only selected schedules
        $placeholders = implode(',', array_fill(0, count($selected_schedule_ids), '?'));
        $sql = "SELECT * FROM schedule WHERE schd_id IN ($placeholders) AND schd_status = 'Active'";
        $params = $selected_schedule_ids;
        $types = str_repeat('i', count($selected_schedule_ids));
    } else {
        // Copy all schedules from source (backward compatibility)
        $sql = "SELECT * FROM schedule WHERE sy_id = ? AND schd_term = ? AND schd_status = 'Active'";
        $params = [$source_sy_id, $source_term];
        $types = "ii";

        if ($instructor_id > 0) {
            $sql .= " AND inst_id = ?";
            $params[] = $instructor_id;
            $types .= "i";
        }
    }
    
    $source_stmt = $conn->prepare($sql);
    if (!$source_stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $source_stmt->bind_param($types, ...$params);
    $source_stmt->execute();
    $source_schedules = $source_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $source_stmt->close();

    if (count($source_schedules) === 0) {
        echo json_encode(['success' => false, 'message' => 'No active schedules found to copy.']);
        exit;
    }

    // --- Section Mapping: Find matching sections in destination ---
    // First, gather all unique sections from source schedules with their details
    $section_mapping_needed = []; // Array: [unique_key => [sec_id, sec_name, program_id, year_level, ...]]
    $section_info_cache = []; // Cache section details: [sec_id => [sec_name, program_id, year_level]]
    
    // Check if program_id column exists in section table
    $check_program_col = $conn->query("SHOW COLUMNS FROM section LIKE 'program_id'");
    $has_program_id_col = $check_program_col && $check_program_col->num_rows > 0;
    
    foreach ($source_schedules as $schedule) {
        $source_sec_id = (int)$schedule['sec_id'];
        $source_program_id = !empty($schedule['program_id']) ? (int)$schedule['program_id'] : null;
        $source_year_level = !empty($schedule['year_level']) ? (int)$schedule['year_level'] : null;
        
        // Cache section info if not already cached
        if (!isset($section_info_cache[$source_sec_id])) {
            // Get section name, program_id, and year_level from section and related tables
            if ($has_program_id_col) {
                $sec_info_query = "SELECT sec.sec_name, sec.program_id, c.class_lvl as year_level, p.program_code, p.program_name
                                   FROM section sec
                                   JOIN class c ON sec.class_id = c.class_id
                                   LEFT JOIN program p ON sec.program_id = p.program_id
                                   WHERE sec.sec_id = ?";
            } else {
                // Fallback: Get from section name and class
                $sec_info_query = "SELECT sec.sec_name, NULL as program_id, c.class_lvl as year_level, p.program_code, p.program_name
                                   FROM section sec
                                   JOIN class c ON sec.class_id = c.class_id
                                   JOIN curriculum curr ON c.curr_id = curr.curr_id
                                   LEFT JOIN program p ON curr.program_id = p.program_id
                                   WHERE sec.sec_id = ?";
            }
            
            $sec_info_stmt = $conn->prepare($sec_info_query);
            if (!$sec_info_stmt) {
                throw new Exception("Failed to prepare section info query: " . $conn->error);
            }
            
            $sec_info_stmt->bind_param("i", $source_sec_id);
            $sec_info_stmt->execute();
            $sec_info_result = $sec_info_stmt->get_result()->fetch_assoc();
            $sec_info_stmt->close();
            
            if (!$sec_info_result) {
                throw new Exception("Source section ID {$source_sec_id} not found in database.");
            }
            
            $section_info_cache[$source_sec_id] = [
                'sec_name' => $sec_info_result['sec_name'],
                'program_id' => $sec_info_result['program_id'] ? (int)$sec_info_result['program_id'] : null,
                'year_level' => $sec_info_result['year_level'] ? (int)$sec_info_result['year_level'] : null,
                'program_code' => $sec_info_result['program_code'] ?? '',
                'program_name' => $sec_info_result['program_name'] ?? ''
            ];
        }
        
        $sec_info = $section_info_cache[$source_sec_id];
        
        // If program_id or year_level not in schedule, use from section info
        if ($source_program_id === null && $sec_info['program_id'] !== null) {
            $source_program_id = $sec_info['program_id'];
        }
        if ($source_year_level === null && $sec_info['year_level'] !== null) {
            $source_year_level = $sec_info['year_level'];
        }
        
        // If still missing, get from subject
        if ($source_program_id === null) {
            $subj_prog_stmt = $conn->prepare("SELECT program_id FROM subject WHERE subj_id = ?");
            $subj_prog_stmt->bind_param("i", $schedule['subj_id']);
            $subj_prog_stmt->execute();
            $subj_prog_result = $subj_prog_stmt->get_result()->fetch_assoc();
            $subj_prog_stmt->close();
            
            if ($subj_prog_result && !empty($subj_prog_result['program_id'])) {
                $source_program_id = (int)$subj_prog_result['program_id'];
            }
        }
        
        // Create unique key for this section mapping: sec_name + program_id + year_level
        $unique_key = $sec_info['sec_name'] . '|' . ($source_program_id ?? 'NULL') . '|' . ($source_year_level ?? 'NULL');
        
        // Store section mapping info (track all source sec_ids for this unique key)
        if (!isset($section_mapping_needed[$unique_key])) {
            $section_mapping_needed[$unique_key] = [
                'source_sec_ids' => [], // Array to store all source sec_ids with this combination
                'sec_name' => $sec_info['sec_name'],
                'program_id' => $source_program_id,
                'year_level' => $source_year_level,
                'program_code' => $sec_info['program_code'],
                'program_name' => $sec_info['program_name']
            ];
        }
        // Add this source sec_id to the list for this unique key
        $section_mapping_needed[$unique_key]['source_sec_ids'][] = $source_sec_id;
    }
    
    // Now find matching sections in destination for each unique section
    $section_mapping = []; // [source_sec_id => destination_sec_id]
    $missing_sections = []; // Sections that don't exist in destination
    
    foreach ($section_mapping_needed as $unique_key => $section_info) {
        $sec_name = $section_info['sec_name'];
        $program_id = $section_info['program_id'];
        $year_level = $section_info['year_level'];
        
        // Validate that we have required information (year_level is required)
        if ($year_level === null) {
            $program_display = $section_info['program_code'] 
                ? $section_info['program_code'] . ' - ' . $section_info['program_name']
                : ($section_info['program_name'] ?: 'Program ID: ' . ($program_id ?? 'NULL'));
            
            $missing_sections[] = [
                'section_name' => $sec_name,
                'program' => $program_display,
                'year_level' => 'Unknown (year level not found)',
                'error' => 'Year level is required but could not be determined'
            ];
            continue;
        }
        
        // Find matching section in destination by: sec_name + program_id + year_level + destination SY/Term
        // If program substitution is enabled, match by name + year level only (ignore source program_id)
        if ($program_substitution && $dest_program_id !== null) {
            // Program substitution mode: Match by name + year level, but use sections from destination program
            if ($has_program_id_col) {
                // Match by name and year level, but filter by destination program_id
                $dest_sec_query = "SELECT sec.sec_id, sec.sec_name, sec.program_id, p.program_code, p.program_name
                                   FROM section sec
                                   JOIN class c ON sec.class_id = c.class_id
                                   LEFT JOIN program p ON sec.program_id = p.program_id
                                   WHERE sec.sec_name = ?
                                     AND c.sy_id = ?
                                     AND c.class_term = ?
                                     AND c.class_lvl = ?
                                     AND (sec.program_id = ? OR (sec.program_id IS NULL AND ? IS NULL))
                                   LIMIT 1";
                $dest_sec_params = [$sec_name, $dest_sy_id, $dest_term, $year_level, $dest_program_id, $dest_program_id];
                $dest_sec_types = "siiiii";
            } else {
                // If program_id column doesn't exist, match by name and year level, filter by curriculum program
                $dest_sec_query = "SELECT sec.sec_id, sec.sec_name, NULL as program_id, p.program_code, p.program_name
                                   FROM section sec
                                   JOIN class c ON sec.class_id = c.class_id
                                   JOIN curriculum curr ON c.curr_id = curr.curr_id
                                   LEFT JOIN program p ON curr.program_id = p.program_id
                                   WHERE sec.sec_name = ?
                                     AND c.sy_id = ?
                                     AND c.class_term = ?
                                     AND c.class_lvl = ?
                                     AND curr.program_id = ?
                                   LIMIT 1";
                $dest_sec_params = [$sec_name, $dest_sy_id, $dest_term, $year_level, $dest_program_id];
                $dest_sec_types = "siiii";
            }
        } else if ($has_program_id_col && $program_id !== null) {
            // Normal mode: If program_id column exists and we have program_id, match strictly
            $dest_sec_query = "SELECT sec.sec_id, sec.sec_name, sec.program_id, p.program_code, p.program_name
                               FROM section sec
                               JOIN class c ON sec.class_id = c.class_id
                               LEFT JOIN program p ON sec.program_id = p.program_id
                               WHERE sec.sec_name = ?
                                 AND c.sy_id = ?
                                 AND c.class_term = ?
                                 AND c.class_lvl = ?
                                 AND (sec.program_id = ? OR (sec.program_id IS NULL AND ? IS NULL))
                               LIMIT 1";
            $dest_sec_params = [$sec_name, $dest_sy_id, $dest_term, $year_level, $program_id, $program_id];
            $dest_sec_types = "siiiii";
        } else if ($has_program_id_col) {
            // If program_id column exists but program_id is NULL, match by name and year level only
            $dest_sec_query = "SELECT sec.sec_id, sec.sec_name, sec.program_id, p.program_code, p.program_name
                               FROM section sec
                               JOIN class c ON sec.class_id = c.class_id
                               LEFT JOIN program p ON sec.program_id = p.program_id
                               WHERE sec.sec_name = ?
                                 AND c.sy_id = ?
                                 AND c.class_term = ?
                                 AND c.class_lvl = ?
                               LIMIT 1";
            $dest_sec_params = [$sec_name, $dest_sy_id, $dest_term, $year_level];
            $dest_sec_types = "siii";
        } else {
            // If program_id column doesn't exist, match by name and year level only
            $dest_sec_query = "SELECT sec.sec_id, sec.sec_name, NULL as program_id, p.program_code, p.program_name
                               FROM section sec
                               JOIN class c ON sec.class_id = c.class_id
                               JOIN curriculum curr ON c.curr_id = curr.curr_id
                               LEFT JOIN program p ON curr.program_id = p.program_id
                               WHERE sec.sec_name = ?
                                 AND c.sy_id = ?
                                 AND c.class_term = ?
                                 AND c.class_lvl = ?
                               LIMIT 1";
            $dest_sec_params = [$sec_name, $dest_sy_id, $dest_term, $year_level];
            $dest_sec_types = "siii";
        }
        
        $dest_sec_stmt = $conn->prepare($dest_sec_query);
        if (!$dest_sec_stmt) {
            throw new Exception("Failed to prepare destination section query: " . $conn->error);
        }
        
        $dest_sec_stmt->bind_param($dest_sec_types, ...$dest_sec_params);
        $dest_sec_stmt->execute();
        $dest_sec_result = $dest_sec_stmt->get_result()->fetch_assoc();
        $dest_sec_stmt->close();
        
        if ($dest_sec_result) {
            // Found matching section - map all source sections with this unique key to destination
            $dest_sec_id = (int)$dest_sec_result['sec_id'];
            foreach ($section_info['source_sec_ids'] as $src_sec_id) {
                $section_mapping[$src_sec_id] = $dest_sec_id;
            }
        } else {
            // Section not found in destination - add to missing list
            $program_display = $section_info['program_code'] 
                ? $section_info['program_code'] . ' - ' . $section_info['program_name']
                : ($section_info['program_name'] ?: 'Program ID: ' . ($program_id ?? 'NULL'));
            
            $missing_sections[] = [
                'section_name' => $sec_name,
                'program' => $program_display,
                'year_level' => $year_level
            ];
        }
    }
    
    // If any sections are missing, return error with details
    if (!empty($missing_sections)) {
        $missing_list = [];
        foreach ($missing_sections as $missing) {
            $year_display = isset($missing['error']) ? $missing['year_level'] : "Year {$missing['year_level']}";
            $missing_list[] = "• Section '{$missing['section_name']}' for {$missing['program']} - {$year_display}";
        }
        
        $error_message = "Cannot copy schedules. The following sections do not exist in the destination term";
        if ($program_substitution && $dest_program_id !== null) {
            // Get destination program name for better error message
            $dest_prog_stmt = $conn->prepare("SELECT program_code, program_name FROM program WHERE program_id = ?");
            $dest_prog_stmt->bind_param("i", $dest_program_id);
            $dest_prog_stmt->execute();
            $dest_prog_result = $dest_prog_stmt->get_result()->fetch_assoc();
            $dest_prog_stmt->close();
            
            $dest_program_display = $dest_prog_result 
                ? ($dest_prog_result['program_code'] . ' - ' . $dest_prog_result['program_name'])
                : 'selected program';
            
            $error_message .= " for the destination program ({$dest_program_display})";
        }
        $error_message .= ":\n\n";
        $error_message .= implode("\n", $missing_list);
        $error_message .= "\n\nPlease create these sections in the destination term first using 'Auto Section Maker' or manual section creation before copying schedules.";
        
        echo json_encode([
            'success' => false, 
            'message' => $error_message,
            'missing_sections' => $missing_sections
        ]);
        exit;
    }

    // --- Conflict Detection: Check for conflicts before copying ---
    // Pre-process all schedules to map sections and check for conflicts
    $conflicts = []; // Array to collect conflict details
    
    foreach ($source_schedules as $schedule) {
        $inst_id = $schedule['inst_id'];
        $subj_id = $schedule['subj_id'];
        $source_sec_id = (int)$schedule['sec_id'];
        
        // Map source section to destination section
        if (!isset($section_mapping[$source_sec_id])) {
            continue; // Skip if section mapping not found (should not happen after validation)
        }
        $sec_id = $section_mapping[$source_sec_id];
        
        $rm_id = $schedule['rm_id'];
        $schd_day = $schedule['schd_day'];
        $schd_start = $schedule['schd_start'];
        $schd_end = $schedule['schd_end'];
        
        // Get program_id, year_level, dept_id for conflict checking (simplified version)
        $program_id = !empty($schedule['program_id']) ? (int)$schedule['program_id'] : null;
        if ($program_substitution && $dest_program_id !== null) {
            $program_id = $dest_program_id;
        }
        
        // 1. Check for exact duplicate (same subject, section, instructor, day, time)
        $duplicate_check = $conn->prepare(
            "SELECT schd_id FROM schedule 
             WHERE sy_id = ? AND schd_term = ? AND subj_id = ? AND sec_id = ? 
             AND inst_id = ? AND schd_day = ? AND schd_start = ? AND schd_end = ? 
             AND schd_status = 'Active'"
        );
        $duplicate_check->bind_param("iiiiisss", $dest_sy_id, $dest_term, $subj_id, $sec_id, $inst_id, $schd_day, $schd_start, $schd_end);
        $duplicate_check->execute();
        if ($duplicate_check->get_result()->num_rows > 0) {
            // Get subject info for error message
            $subj_info_stmt = $conn->prepare("SELECT subj_code, subj_desc FROM subject WHERE subj_id = ?");
            $subj_info_stmt->bind_param("i", $subj_id);
            $subj_info_stmt->execute();
            $subj_info = $subj_info_stmt->get_result()->fetch_assoc();
            $subj_info_stmt->close();
            
            $subj_display = $subj_info ? ($subj_info['subj_code'] . ' - ' . $subj_info['subj_desc']) : "Subject ID {$subj_id}";
            $conflicts[] = "Duplicate schedule: {$subj_display} already exists with the same section, instructor, day, and time.";
        }
        $duplicate_check->close();
        
        // 2. Check for Room Conflict (same room, same day, overlapping time) - skip for Virtual/School Ground buildings
        $room_check_stmt = $conn->prepare("SELECT b.bd_desc FROM room r JOIN building b ON r.bd_id = b.bd_id WHERE r.rm_id = ?");
        $room_check_stmt->bind_param("i", $rm_id);
        $room_check_stmt->execute();
        $room_result = $room_check_stmt->get_result()->fetch_assoc();
        $room_check_stmt->close();
        
        $allows_overlap = false;
        if ($room_result) {
            $building_name = strtoupper(trim($room_result['bd_desc']));
            $allows_overlap = ($building_name === 'VIRTUAL' || $building_name === 'SCHOOL GROUND' || $building_name === 'SCHOOL GROUNDS');
        }
        
        if (!$allows_overlap) {
            $room_conflict_stmt = $conn->prepare(
                "SELECT s.schd_id, subj.subj_code, subj.subj_desc, sec.sec_name,
                        CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
                        TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, 
                        TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time
                 FROM schedule s
                 JOIN subject subj ON s.subj_id = subj.subj_id
                 JOIN section sec ON s.sec_id = sec.sec_id
                 JOIN instructor i ON s.inst_id = i.inst_id
                 JOIN room r ON s.rm_id = r.rm_id
                 JOIN building b ON r.bd_id = b.bd_id
                 WHERE s.sy_id = ? AND s.schd_term = ? AND s.schd_day = ? 
                 AND s.rm_id = ? AND s.schd_status = 'Active' 
                 AND s.schd_start < ? AND s.schd_end > ?
                 AND UPPER(TRIM(b.bd_desc)) NOT IN ('VIRTUAL', 'SCHOOL GROUND', 'SCHOOL GROUNDS')"
            );
            $room_conflict_stmt->bind_param("iisiss", $dest_sy_id, $dest_term, $schd_day, $rm_id, $schd_end, $schd_start);
            $room_conflict_stmt->execute();
            $room_conflict_result = $room_conflict_stmt->get_result();
            if ($room_conflict_result->num_rows > 0) {
                $room_conflict = $room_conflict_result->fetch_assoc();
                $day_name = ucfirst(strtolower($schd_day));
                if (strlen($day_name) > 3) {
                    $day_name = substr($day_name, 0, 3) . '.';
                }
                $conflicts[] = "Room conflict on {$day_name}: Room is already booked for {$room_conflict['subj_code']} ({$room_conflict['sec_name']}) with {$room_conflict['instructor_name']} from {$room_conflict['start_time']} to {$room_conflict['end_time']}.";
            }
            $room_conflict_stmt->close();
        }
        
        // 3. Check for Instructor Conflict (same instructor, same day, overlapping time)
        $instructor_conflict_stmt = $conn->prepare(
            "SELECT s.schd_id, subj.subj_code, subj.subj_desc, sec.sec_name, r.rm_name,
                    TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, 
                    TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time
             FROM schedule s
             JOIN subject subj ON s.subj_id = subj.subj_id
             JOIN section sec ON s.sec_id = sec.sec_id
             JOIN room r ON s.rm_id = r.rm_id
             WHERE s.sy_id = ? AND s.schd_term = ? AND s.schd_day = ? 
             AND s.inst_id = ? AND s.schd_status = 'Active' 
             AND s.schd_start < ? AND s.schd_end > ?"
        );
        $instructor_conflict_stmt->bind_param("iisiss", $dest_sy_id, $dest_term, $schd_day, $inst_id, $schd_end, $schd_start);
        $instructor_conflict_stmt->execute();
        $instructor_conflict_result = $instructor_conflict_stmt->get_result();
        if ($instructor_conflict_result->num_rows > 0) {
            $instructor_conflict = $instructor_conflict_result->fetch_assoc();
            $day_name = ucfirst(strtolower($schd_day));
            if (strlen($day_name) > 3) {
                $day_name = substr($day_name, 0, 3) . '.';
            }
            $conflicts[] = "Instructor conflict on {$day_name}: Instructor is already scheduled for {$instructor_conflict['subj_code']} ({$instructor_conflict['sec_name']}) in room {$instructor_conflict['rm_name']} from {$instructor_conflict['start_time']} to {$instructor_conflict['end_time']}.";
        }
        $instructor_conflict_stmt->close();
        
        // 4. Check for Section Conflict (same section, same day, overlapping time) - skip for Virtual/School Ground buildings
        if (!$allows_overlap) {
            $section_conflict_stmt = $conn->prepare(
                "SELECT s.schd_id, subj.subj_code, subj.subj_desc, r.rm_name,
                        CONCAT(i.inst_fname, ' ', i.inst_lname) as instructor_name,
                        TIME_FORMAT(s.schd_start, '%h:%i %p') as start_time, 
                        TIME_FORMAT(s.schd_end, '%h:%i %p') as end_time
                 FROM schedule s
                 JOIN subject subj ON s.subj_id = subj.subj_id
                 JOIN room r ON s.rm_id = r.rm_id
                 JOIN instructor i ON s.inst_id = i.inst_id
                 JOIN building b ON r.bd_id = b.bd_id
                 WHERE s.sy_id = ? AND s.schd_term = ? AND s.schd_day = ? 
                 AND s.sec_id = ? AND s.schd_status = 'Active' 
                 AND s.schd_start < ? AND s.schd_end > ?
                 AND UPPER(TRIM(b.bd_desc)) NOT IN ('VIRTUAL', 'SCHOOL GROUND', 'SCHOOL GROUNDS')"
            );
            $section_conflict_stmt->bind_param("iisiss", $dest_sy_id, $dest_term, $schd_day, $sec_id, $schd_end, $schd_start);
            $section_conflict_stmt->execute();
            $section_conflict_result = $section_conflict_stmt->get_result();
            if ($section_conflict_result->num_rows > 0) {
                $section_conflict = $section_conflict_result->fetch_assoc();
                $day_name = ucfirst(strtolower($schd_day));
                if (strlen($day_name) > 3) {
                    $day_name = substr($day_name, 0, 3) . '.';
                }
                $conflicts[] = "Section conflict on {$day_name}: Section already has a scheduled class for {$section_conflict['subj_code']} with {$section_conflict['instructor_name']} in room {$section_conflict['rm_name']} from {$section_conflict['start_time']} to {$section_conflict['end_time']}.";
            }
            $section_conflict_stmt->close();
        }
    }
    
    // If conflicts found, report them and exit
    if (!empty($conflicts)) {
        $conflict_message = "Cannot copy schedules. The following conflicts were detected:\n\n";
        $conflict_message .= implode("\n", array_unique($conflicts));
        $conflict_message .= "\n\nPlease resolve these conflicts before copying.";
        
        echo json_encode([
            'success' => false,
            'message' => $conflict_message,
            'conflicts' => array_unique($conflicts)
        ]);
        exit;
    }

    // --- Begin Transactional Copy ---
    $conn->begin_transaction();

    // Insert statement includes all schedule fields: program_id, year_level, dept_id, sec_id, inst_id, rm_id, schd_type, schd_term, schd_day, schd_start, schd_end, schd_status, is_overtime
    $insert_stmt = $conn->prepare(
        "INSERT INTO schedule (sy_id, subj_id, sec_id, inst_id, rm_id, schd_type, schd_term, schd_day, schd_start, schd_end, schd_min, schd_status, is_overtime, program_id, year_level, dept_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    if (!$insert_stmt) {
        throw new Exception("Failed to prepare insert statement: " . $conn->error);
    }

    /** @var array<string, array{policy: array, current_minutes: float}> Per inst_id + subject dept for destination term */
    $instructor_workloads = [];
    $instructor_base_cache = []; // [inst_id => instructor row]
    $copied_count = 0;

    foreach ($source_schedules as $schedule) {
        $inst_id = $schedule['inst_id'];
        $schd_min = $schedule['schd_min'];
        
        // Get all schedule fields (with null handling)
        $subj_id = $schedule['subj_id'];
        $source_sec_id = (int)$schedule['sec_id'];
        
        // Map source section to destination section using the section mapping
        if (!isset($section_mapping[$source_sec_id])) {
            throw new Exception("Section mapping not found for source section ID {$source_sec_id}. This should not happen if validation passed.");
        }
        $sec_id = $section_mapping[$source_sec_id]; // Use mapped destination section ID
        
        $rm_id = $schedule['rm_id'];
        $schd_type = $schedule['schd_type'];
        $schd_day = $schedule['schd_day'];
        $schd_start = $schedule['schd_start'];
        $schd_end = $schedule['schd_end'];
        $schd_status = 'Active'; // Always set to Active for copied schedules
        
        // Get program_id, year_level, dept_id - first try from schedule, then from related tables
        // If program substitution is enabled, use destination program_id instead
        if ($program_substitution && $dest_program_id !== null) {
            $program_id = $dest_program_id; // Use destination program when substitution is enabled
        } else {
            $program_id = !empty($schedule['program_id']) ? (int)$schedule['program_id'] : null;
        }
        $year_level = !empty($schedule['year_level']) ? (int)$schedule['year_level'] : null;
        $dept_id = !empty($schedule['dept_id']) ? (int)$schedule['dept_id'] : null;
        
        // If program_id, year_level, or dept_id are missing, fetch from related tables
        // Skip program_id fetch if program substitution is enabled (we already have dest_program_id)
        if (($program_id === null && !$program_substitution) || $year_level === null || $dept_id === null) {
            // Get program_id and dept_id from subject table
            $subj_stmt = $conn->prepare("SELECT program_id, dept_id FROM subject WHERE subj_id = ?");
            $subj_stmt->bind_param("i", $subj_id);
            $subj_stmt->execute();
            $subj_data = $subj_stmt->get_result()->fetch_assoc();
            $subj_stmt->close();
            
            if ($subj_data) {
                // Only fetch program_id from subject if program substitution is NOT enabled
                if (!$program_substitution && $program_id === null && !empty($subj_data['program_id'])) {
                    $program_id = (int)$subj_data['program_id'];
                }
                if ($dept_id === null && !empty($subj_data['dept_id'])) {
                    $dept_id = (int)$subj_data['dept_id'];
                }
            }
            
            // If program substitution is enabled, also get dept_id from destination program if needed
            if ($program_substitution && $dest_program_id !== null && $dept_id === null) {
                $prog_dept_stmt = $conn->prepare("SELECT dept_id FROM program WHERE program_id = ?");
                $prog_dept_stmt->bind_param("i", $dest_program_id);
                $prog_dept_stmt->execute();
                $prog_dept_result = $prog_dept_stmt->get_result()->fetch_assoc();
                $prog_dept_stmt->close();
                
                if ($prog_dept_result && !empty($prog_dept_result['dept_id'])) {
                    $dept_id = (int)$prog_dept_result['dept_id'];
                }
            }
            
            // Get year_level from section -> class table (class uses class_lvl column)
            if ($year_level === null && $sec_id) {
                $sec_stmt = $conn->prepare("SELECT c.class_lvl as year_level FROM section s JOIN class c ON s.class_id = c.class_id WHERE s.sec_id = ?");
                $sec_stmt->bind_param("i", $sec_id);
                $sec_stmt->execute();
                $sec_data = $sec_stmt->get_result()->fetch_assoc();
                $sec_stmt->close();
                
                if ($sec_data && !empty($sec_data['year_level'])) {
                    $year_level = (int)$sec_data['year_level'];
                }
            }
        }

        if ($dept_id === null || (int) $dept_id === 0) {
            $dept_id = ida_get_subject_department_id($conn, (int) $subj_id);
        }
        $subj_dept_for_wl = (int) ($dept_id ?? 0);

        if (!isset($instructor_base_cache[$inst_id])) {
            $inst_stmt = $conn->prepare('SELECT inst_status, instruction_hours FROM instructor WHERE inst_id = ?');
            $inst_stmt->bind_param('i', $inst_id);
            $inst_stmt->execute();
            $instructor_base_cache[$inst_id] = $inst_stmt->get_result()->fetch_assoc() ?: [
                'inst_status' => 'Regular',
                'instruction_hours' => 40,
            ];
            $inst_stmt->close();
        }

        $wl_key = $inst_id . '_' . $subj_dept_for_wl;
        if (!isset($instructor_workloads[$wl_key])) {
            $policy = ida_get_workload_policy_for_subject_dept(
                $conn,
                (int) $inst_id,
                $subj_dept_for_wl,
                $instructor_base_cache[$inst_id]
            );
            $existing_dest = ida_sum_scheduled_minutes_for_department(
                $conn,
                (int) $inst_id,
                (int) $dest_sy_id,
                (int) $dest_term,
                $subj_dept_for_wl,
                0
            );
            $instructor_workloads[$wl_key] = [
                'policy' => $policy,
                'current_minutes' => $existing_dest,
            ];
        }

        $new_total_minutes = $instructor_workloads[$wl_key]['current_minutes'] + $schd_min;
        $policy = $instructor_workloads[$wl_key]['policy'];
        $limit_minutes = (float) $policy['instruction_hours'] * 60;

        if (in_array($policy['inst_status'], ['Part-Time', 'Contractual'], true) && $limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => "Copy failed. Instructor ID {$inst_id} would exceed their per-department workload limit in the new term for this subject's department."]);
            exit;
        }

        $is_overtime = 'No';
        if ($policy['inst_status'] === 'Regular' && $limit_minutes > 0 && $new_total_minutes > $limit_minutes) {
            $is_overtime = 'Yes';
        }

        // Insert the new schedule record with all fields
        // Types: i=int, s=string (16 parameters total)
        // 1.sy_id(i), 2.subj_id(i), 3.sec_id(i), 4.inst_id(i), 5.rm_id(i), 6.schd_type(s), 7.schd_term(i), 8.schd_day(s), 9.schd_start(s), 10.schd_end(s), 11.schd_min(i), 12.schd_status(s), 13.is_overtime(s), 14.program_id(i), 15.year_level(i), 16.dept_id(i)
        // Type string: i i i i i s i s s s i s s i i i = "iiiiisisssissiii" (16 chars)
        $insert_stmt->bind_param(
            "iiiiisisssissiii", 
            $dest_sy_id,      // 1. sy_id (destination) - int
            $subj_id,         // 2. subj_id - int
            $sec_id,          // 3. sec_id - int
            $inst_id,         // 4. inst_id - int
            $rm_id,           // 5. rm_id - int
            $schd_type,       // 6. schd_type - string
            $dest_term,       // 7. schd_term (destination) - int
            $schd_day,        // 8. schd_day - string
            $schd_start,      // 9. schd_start - string
            $schd_end,        // 10. schd_end - string
            $schd_min,        // 11. schd_min - int
            $schd_status,     // 12. schd_status - string
            $is_overtime,     // 13. is_overtime - string
            $program_id,      // 14. program_id - int
            $year_level,      // 15. year_level - int
            $dept_id          // 16. dept_id - int
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to insert schedule: " . $insert_stmt->error);
        }

        $instructor_workloads[$wl_key]['current_minutes'] = $new_total_minutes;
        $copied_count++;
    }

    $conn->commit();
    $insert_stmt->close();

    echo json_encode(['success' => true, 'message' => "Successfully copied {$copied_count} schedules."]);

} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred: ' . $e->getMessage()]);
}
?>