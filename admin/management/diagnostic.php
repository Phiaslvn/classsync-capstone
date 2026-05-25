<?php
/**
 * Comprehensive Diagnostic Tool for Schedule Management
 * URL: /admin/management/diagnostic.php
 * 
 * This file helps debug the auto-fill, section suggestion, and room availability features.
 */

session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

$test = $_GET['test'] ?? 'all';
$response = ['status' => 'error', 'tests' => []];

try {
    // Test 1: Check if database connection works
    if ($test === 'all' || $test === 'db') {
        $response['tests']['database'] = [
            'success' => true,
            'message' => 'Database connection OK'
        ];
    }

    // Test 2: Check section API
    if ($test === 'all' || $test === 'section') {
        $class_id = (int)($_GET['class_id'] ?? 1);
        $year_level = $_GET['year_level'] ?? '1';
        $program_id = (int)($_GET['program_id'] ?? 1);

        $classStmt = $conn->prepare("SELECT class_id, class_lvl FROM class WHERE class_id = ?");
        $classStmt->bind_param("i", $class_id);
        $classStmt->execute();
        $classInfo = $classStmt->get_result()->fetch_assoc();
        $classStmt->close();

        $response['tests']['section_api'] = [
            'parameters' => ['class_id' => $class_id, 'year_level' => $year_level, 'program_id' => $program_id],
            'class_found' => $classInfo !== null,
            'class_info' => $classInfo,
            'message' => 'Test API call to get_next_section_name.php with these parameters'
        ];
    }

    // Test 3: Check room availability API
    if ($test === 'all' || $test === 'room') {
        $rm_id = (int)($_GET['rm_id'] ?? 1);
        $sy_id = (int)($_GET['sy_id'] ?? 1);
        $term = (int)($_GET['term'] ?? 1);

        $roomStmt = $conn->prepare("SELECT rm_id, rm_name FROM room WHERE rm_id = ?");
        $roomStmt->bind_param("i", $rm_id);
        $roomStmt->execute();
        $roomInfo = $roomStmt->get_result()->fetch_assoc();
        $roomStmt->close();

        $response['tests']['room_api'] = [
            'parameters' => ['rm_id' => $rm_id, 'sy_id' => $sy_id, 'term' => $term],
            'room_found' => $roomInfo !== null,
            'room_info' => $roomInfo,
            'message' => 'Test API call to get_room_availability.php with these parameters'
        ];
    }

    // Test 4: Check recommendation API
    if ($test === 'all' || $test === 'recommendation') {
        $sy_id = (int)($_GET['sy_id'] ?? 1);
        $term = (int)($_GET['term'] ?? 1);
        $subj_id = (int)($_GET['subj_id'] ?? 1);
        $inst_id = (int)($_GET['inst_id'] ?? 1);

        $subjStmt = $conn->prepare("SELECT subj_id, subj_code FROM subject WHERE subj_id = ?");
        $subjStmt->bind_param("i", $subj_id);
        $subjStmt->execute();
        $subjInfo = $subjStmt->get_result()->fetch_assoc();
        $subjStmt->close();

        $instStmt = $conn->prepare("SELECT inst_id, CONCAT(inst_fname, ' ', inst_lname) as inst_name FROM instructor WHERE inst_id = ?");
        $instStmt->bind_param("i", $inst_id);
        $instStmt->execute();
        $instInfo = $instStmt->get_result()->fetch_assoc();
        $instStmt->close();

        $response['tests']['recommendation_api'] = [
            'parameters' => ['sy_id' => $sy_id, 'term' => $term, 'subj_id' => $subj_id, 'inst_id' => $inst_id],
            'subject_found' => $subjInfo !== null,
            'subject_info' => $subjInfo,
            'instructor_found' => $instInfo !== null,
            'instructor_info' => $instInfo,
            'message' => 'Test API call to get_schedule_recommendation.php with these parameters'
        ];
    }

    // Test 5: Check add_section.php
    if ($test === 'all' || $test === 'add_section') {
        $class_id = (int)($_GET['class_id'] ?? 1);
        
        $classStmt = $conn->prepare("
            SELECT c.class_id, c.class_lvl, c.class_secno, 
                   (SELECT COUNT(*) FROM section WHERE class_id = c.class_id) as current_sections
            FROM class c
            WHERE c.class_id = ?
        ");
        $classStmt->bind_param("i", $class_id);
        $classStmt->execute();
        $classInfo = $classStmt->get_result()->fetch_assoc();
        $classStmt->close();

        $response['tests']['add_section_validation'] = [
            'class_id' => $class_id,
            'class_found' => $classInfo !== null,
            'class_info' => $classInfo,
            'can_add_section' => $classInfo ? ($classInfo['current_sections'] < $classInfo['class_secno']) : false,
            'message' => 'Check if section can be added to this class'
        ];
    }

    $response['status'] = 'success';

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
