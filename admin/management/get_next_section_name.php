<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');

$response = ['success' => false, 'suggestion' => '', 'lastSection' => null];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $class_id = (int)($_GET['class_id'] ?? 0);
    $year_level = trim($_GET['year_level'] ?? '');
    $program_id = (int)($_GET['program_id'] ?? 0);

    if (empty($class_id)) {
        $response['message'] = 'Class ID required';
        echo json_encode($response);
        exit;
    }

    try {
        // Get the class info to understand the naming pattern
        $classStmt = $conn->prepare("
            SELECT c.class_id, c.sy_id, c.class_lvl, c.class_term, curr.program_id, p.program_name, p.program_code
            FROM class c
            JOIN curriculum curr ON c.curr_id = curr.curr_id
            JOIN program p ON curr.program_id = p.program_id
            WHERE c.class_id = ?
        ");
        $classStmt->bind_param("i", $class_id);
        $classStmt->execute();
        $classResult = $classStmt->get_result();
        $classInfo = $classResult->fetch_assoc();
        $classStmt->close();

        if (!$classInfo) {
            $response['message'] = 'Class not found';
            echo json_encode($response);
            exit;
        }

        // Get sections for this class filtered by year level if provided
        // The year_level constraint comes from the class itself, so we verify the class matches the year level
        if (!empty($year_level)) {
            // Verify the class matches the selected year level before proceeding
            if ($classInfo['class_lvl'] != $year_level) {
                $response['message'] = 'Class does not match the selected year level';
                echo json_encode($response);
                exit;
            }
        }

        // Get all sections for this specific class, ordered by section number
        $sectionsStmt = $conn->prepare("
            SELECT sec_id, sec_name, sec_num
            FROM section
            WHERE class_id = ?
            ORDER BY sec_num ASC
        ");
        $sectionsStmt->bind_param("i", $class_id);
        $sectionsStmt->execute();
        $sectionsResult = $sectionsStmt->get_result();
        $sections = $sectionsResult->fetch_all(MYSQLI_ASSOC);
        $sectionsStmt->close();

        if (empty($sections)) {
            // No sections exist, suggest the first one
            $nextNum = 1;
            $suggestion = $classInfo['program_code'] . ' ' . $classInfo['class_lvl'] . '-A';
            $response['success'] = true;
            $response['suggestion'] = $suggestion;
            $response['nextNum'] = $nextNum;
            echo json_encode($response);
            exit;
        }

        // Get the last section
        $lastSection = end($sections);
        $response['lastSection'] = $lastSection['sec_name'];

        // Parse the last section name to get the letter
        // Pattern: "BSIT 1-D" or "1-D" or just "D"
        $lastSectionName = trim($lastSection['sec_name']);
        
        // Extract the last character (should be the letter)
        $lastLetter = strtoupper(substr($lastSectionName, -1));
        
        // Check if it's a valid letter
        if (ctype_alpha($lastLetter)) {
            // Get next letter
            if ($lastLetter === 'Z') {
                // If Z, wrap to AA (unlikely but handle it)
                $nextLetter = 'AA';
            } else {
                $nextLetter = chr(ord($lastLetter) + 1);
            }
            
            // Build the next section name
            // Get the prefix (everything except the last character)
            $prefix = substr($lastSectionName, 0, -1);
            $suggestion = $prefix . $nextLetter;
        } else {
            // If last character is not a letter, just suggest A
            $suggestion = $lastSectionName . 'A';
        }

        $nextNum = $lastSection['sec_num'] + 1;
        
        $response['success'] = true;
        $response['suggestion'] = $suggestion;
        $response['nextNum'] = $nextNum;

    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
