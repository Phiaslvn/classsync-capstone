<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';
require_once __DIR__ . '/../../includes/utils/program_year_level_curriculum.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $sec_num = (int)($_POST['sec_num'] ?? 0);
    $sec_name = trim($_POST['sec_name'] ?? '');
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

    if (empty($class_id) || empty($sec_num) || empty($sec_name)) {
        $response['message'] = 'All fields are required.';
    } else {
        try {
            // Get class information including curr_id and class_lvl (year level)
            $class_stmt = $conn->prepare("SELECT class_secno, curr_id, class_lvl, (SELECT COUNT(*) FROM section WHERE class_id = ?) as current_sections FROM class WHERE class_id = ?");
            $class_stmt->bind_param("ii", $class_id, $class_id);
            $class_stmt->execute();
            $class_info = $class_stmt->get_result()->fetch_assoc();
            $class_stmt->close();

            if (!$class_info) {
                $response['message'] = 'Class not found.';
                echo json_encode($response);
                exit;
            }

            $curr_id = $class_info['curr_id'];
            $class_lvl = $class_info['class_lvl'];

            // If program_id not provided, try multiple methods to find it
            if (empty($program_id)) {
                // Method 1: Try to get from program_year_level_curriculum mapping table
                $table_check = $conn->query("SHOW TABLES LIKE 'program_year_level_curriculum'");
                $mapping_table_exists = $table_check && $table_check->num_rows > 0;
                if ($table_check) $table_check->close();

                if ($mapping_table_exists) {
                    if (pylcurriculum_has_sy_id_column($conn)) {
                        $activeSy = get_active_school_year_id_from_settings($conn);
                        if ($activeSy !== null) {
                            $program_query = "SELECT program_id FROM program_year_level_curriculum 
                                             WHERE curr_id = ? AND year_level = ? 
                                             AND (sy_id = ? OR sy_id IS NULL)
                                             ORDER BY CASE WHEN sy_id = ? THEN 0 ELSE 1 END
                                             LIMIT 1";
                            $program_stmt = $conn->prepare($program_query);
                            $program_stmt->bind_param("iiii", $curr_id, $class_lvl, $activeSy, $activeSy);
                        } else {
                            $program_query = "SELECT program_id FROM program_year_level_curriculum 
                                             WHERE curr_id = ? AND year_level = ? AND sy_id IS NULL
                                             LIMIT 1";
                            $program_stmt = $conn->prepare($program_query);
                            $program_stmt->bind_param("ii", $curr_id, $class_lvl);
                        }
                    } else {
                        $program_query = "SELECT program_id FROM program_year_level_curriculum 
                                         WHERE curr_id = ? AND year_level = ? 
                                         LIMIT 1";
                        $program_stmt = $conn->prepare($program_query);
                        $program_stmt->bind_param("ii", $curr_id, $class_lvl);
                    }
                    $program_stmt->execute();
                    $program_result = $program_stmt->get_result();
                    
                    if ($program_row = $program_result->fetch_assoc()) {
                        $program_id = (int)$program_row['program_id'];
                    }
                    $program_stmt->close();
                }

                // Method 2: If still not found, try to get from existing sections in the same class
                if (empty($program_id)) {
                    $existing_sec_query = "SELECT program_id FROM section 
                                          WHERE class_id = ? AND program_id IS NOT NULL 
                                          LIMIT 1";
                    $existing_sec_stmt = $conn->prepare($existing_sec_query);
                    $existing_sec_stmt->bind_param("i", $class_id);
                    $existing_sec_stmt->execute();
                    $existing_sec_result = $existing_sec_stmt->get_result();
                    
                    if ($existing_sec_row = $existing_sec_result->fetch_assoc()) {
                        $program_id = (int)$existing_sec_row['program_id'];
                    }
                    $existing_sec_stmt->close();
                }

                // Method 3: If still not found, try to get from subjects in the curriculum for this year level
                if (empty($program_id)) {
                    $subject_query = "SELECT program_id, COUNT(*) as cnt FROM subject 
                                      WHERE curr_id = ? AND subj_lvl = ? AND program_id IS NOT NULL 
                                      GROUP BY program_id 
                                      ORDER BY cnt DESC 
                                      LIMIT 1";
                    $subject_stmt = $conn->prepare($subject_query);
                    $subject_stmt->bind_param("ii", $curr_id, $class_lvl);
                    $subject_stmt->execute();
                    $subject_result = $subject_stmt->get_result();
                    
                    if ($subject_row = $subject_result->fetch_assoc()) {
                        $program_id = (int)$subject_row['program_id'];
                    }
                    $subject_stmt->close();
                }

                // Method 4: If still not found, try to extract program_code from section name (e.g., "BSIT 1-E" -> "BSIT")
                if (empty($program_id) && !empty($sec_name)) {
                    // Extract potential program code from section name (first word before space)
                    $name_parts = explode(' ', trim($sec_name));
                    if (!empty($name_parts[0])) {
                        $potential_code = strtoupper($name_parts[0]);
                        $code_query = "SELECT program_id FROM program 
                                      WHERE program_code = ? AND program_status = 'Active' 
                                      LIMIT 1";
                        $code_stmt = $conn->prepare($code_query);
                        $code_stmt->bind_param("s", $potential_code);
                        $code_stmt->execute();
                        $code_result = $code_stmt->get_result();
                        
                        if ($code_row = $code_result->fetch_assoc()) {
                            $program_id = (int)$code_row['program_id'];
                        }
                        $code_stmt->close();
                    }
                }

                // Method 5: If still not found, try to get from curriculum's department (if only one active program)
                if (empty($program_id)) {
                    $dept_program_query = "SELECT c.dept_id, 
                                          (SELECT program_id FROM program 
                                           WHERE dept_id = c.dept_id 
                                           AND program_status = 'Active' 
                                           ORDER BY program_id ASC 
                                           LIMIT 1) as program_id
                                          FROM curriculum c 
                                          WHERE c.curr_id = ?";
                    $dept_program_stmt = $conn->prepare($dept_program_query);
                    $dept_program_stmt->bind_param("i", $curr_id);
                    $dept_program_stmt->execute();
                    $dept_program_result = $dept_program_stmt->get_result();
                    
                    if ($dept_program_row = $dept_program_result->fetch_assoc()) {
                        // Check if department has exactly one active program
                        $dept_id = (int)$dept_program_row['dept_id'];
                        $count_query = "SELECT COUNT(*) as cnt FROM program 
                                       WHERE dept_id = ? AND program_status = 'Active'";
                        $count_stmt = $conn->prepare($count_query);
                        $count_stmt->bind_param("i", $dept_id);
                        $count_stmt->execute();
                        $count_result = $count_stmt->get_result();
                        $count_row = $count_result->fetch_assoc();
                        
                        // Only use if exactly one program in department
                        if ($count_row && (int)$count_row['cnt'] == 1 && !empty($dept_program_row['program_id'])) {
                            $program_id = (int)$dept_program_row['program_id'];
                        }
                        $count_stmt->close();
                    }
                    $dept_program_stmt->close();
                }
            }

            // Allow unlimited sections - remove the restriction
            // Note: The class_secno field indicates the planned number of sections, 
            // but we allow adding more sections if needed for flexibility
            
            // Check for duplicate section number or name within the same class
            $duplicate_check = $conn->prepare("SELECT sec_id FROM section WHERE class_id = ? AND (sec_num = ? OR sec_name = ?)");
            $duplicate_check->bind_param("iis", $class_id, $sec_num, $sec_name);
            $duplicate_check->execute();
            $duplicate_result = $duplicate_check->get_result();
            $duplicate_check->close();

            if ($duplicate_result->num_rows > 0) {
                $response['message'] = 'A section with this number or name already exists for this class.';
            } else {
                // Check if program_id column exists in section table
                $check_col = $conn->query("SHOW COLUMNS FROM section LIKE 'program_id'");
                $has_program_id = $check_col && $check_col->num_rows > 0;
                if ($check_col) $check_col->close();

                // Insert section with or without program_id
                if ($has_program_id && !empty($program_id)) {
                    $stmt = $conn->prepare("INSERT INTO section (class_id, program_id, sec_num, sec_name) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiis", $class_id, $program_id, $sec_num, $sec_name);
                } else {
                    $stmt = $conn->prepare("INSERT INTO section (class_id, sec_num, sec_name) VALUES (?, ?, ?)");
                    $stmt->bind_param("iis", $class_id, $sec_num, $sec_name);
                }

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Section added successfully!';
                    if (!empty($program_id)) {
                        $response['program_id'] = $program_id;
                    }
                } else {
                    $response['message'] = 'Failed to add section. Database error occurred.';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            http_response_code(500);
        }
    }
}

echo json_encode($response);
?>