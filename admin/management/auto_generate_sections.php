<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth/security_middleware.php';

header('Content-Type: application/json');
if (!$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}
if (!hasPermission('manage_schedules')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$response = ['success' => false, 'message' => 'An error occurred.', 'created' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sy_id = (int)($_POST['sy_id'] ?? 0);
    $term = (int)($_POST['term'] ?? 0);
    $program_id = (int)($_POST['program_id'] ?? 0);
    $year_level = (int)($_POST['year_level'] ?? 0);
    $num_sections = (int)($_POST['num_sections'] ?? 0);
    
    // DEBUG: Log input parameters
    error_log("Auto Section Maker - Input Parameters: sy_id={$sy_id}, term={$term}, program_id={$program_id}, year_level={$year_level}, num_sections={$num_sections}");
    
    if (empty($sy_id) || empty($term) || empty($program_id) || empty($year_level) || empty($num_sections)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit;
    }
    
    if ($num_sections < 1 || $num_sections > 26) {
        $response['message'] = 'Number of sections must be between 1 and 26.';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Get program information (department and code for naming)
        $prog_stmt = $conn->prepare("SELECT program_id, program_code, program_name, dept_id FROM program WHERE program_id = ? LIMIT 1");
        $prog_stmt->bind_param("i", $program_id);
        $prog_stmt->execute();
        $prog_result = $prog_stmt->get_result();
        $program = $prog_result->fetch_assoc();
        $prog_stmt->close();
        
        if (!$program || !$program['dept_id']) {
            $response['message'] = 'Program not found or has no department assigned.';
            echo json_encode($response);
            exit;
        }
        
        $dept_id = $program['dept_id'];
        $program_code = $program['program_code']; // e.g., "BEED", "BSIT", "BSED-SCIENCE"
        
        // DEBUG: Log program info
        error_log("Auto Section Maker - Program Info: program_code={$program_code}, program_name={$program['program_name']}, dept_id={$dept_id}");
        
        // Get curriculum for the program's department
        // Curriculum and Program are related through department (both have dept_id)
        $curr_stmt = $conn->prepare("SELECT curr_id FROM curriculum WHERE dept_id = ? AND (curr_status = 'Active' OR curr_status = 'active') ORDER BY curr_yr DESC, curr_name ASC LIMIT 1");
        $curr_stmt->bind_param("i", $dept_id);
        $curr_stmt->execute();
        $curr_result = $curr_stmt->get_result();
        $curriculum = $curr_result->fetch_assoc();
        $curr_stmt->close();
        
        if (!$curriculum) {
            $response['message'] = 'No active curriculum found for the selected program\'s department.';
            echo json_encode($response);
            exit;
        }
        
        $curr_id = $curriculum['curr_id'];
        
        // DEBUG: Log curriculum info
        error_log("Auto Section Maker - Curriculum Info: curr_id={$curr_id}, dept_id={$dept_id}");
        
        // Check if class exists
        $class_stmt = $conn->prepare("SELECT class_id, class_secno FROM class WHERE sy_id = ? AND curr_id = ? AND class_lvl = ? AND class_term = ?");
        $class_stmt->bind_param("iiii", $sy_id, $curr_id, $year_level, $term);
        $class_stmt->execute();
        $class_result = $class_stmt->get_result();
        $existing_class = $class_result->fetch_assoc();
        $class_stmt->close();
        
        // DEBUG: Log class lookup results
        if ($existing_class) {
            error_log("Auto Section Maker - Class Found: class_id={$existing_class['class_id']}, class_secno={$existing_class['class_secno']}");
        } else {
            error_log("Auto Section Maker - No existing class found for sy_id={$sy_id}, curr_id={$curr_id}, class_lvl={$year_level}, class_term={$term}");
        }
        
        $class_id = null;
        
        if ($existing_class) {
            // Class exists, use it
            $class_id = $existing_class['class_id'];
            // Note: No maximum section limit - sections can be created based on num_sections input
            // The class_secno field is just for reference, not a hard limit
        } else {
            // Class doesn't exist, create it
            // Use num_sections as initial class_secno for reference (not a hard limit)
            $create_class_stmt = $conn->prepare("INSERT INTO class (sy_id, curr_id, class_lvl, class_term, class_secno) VALUES (?, ?, ?, ?, ?)");
            $create_class_stmt->bind_param("iiiii", $sy_id, $curr_id, $year_level, $term, $num_sections);
            
            if ($create_class_stmt->execute()) {
                $class_id = $conn->insert_id;
                error_log("Auto Section Maker - Created new class: class_id={$class_id}");
            } else {
                $response['message'] = 'Failed to create class: ' . $conn->error;
                echo json_encode($response);
                exit;
            }
            $create_class_stmt->close();
        }
        
        // Get the count of existing sections for this program and year level
        // sec_num should be sequential based on total sections created for this program + year level
        // Check if program_id column exists in section table
        $check_col = $conn->query("SHOW COLUMNS FROM section LIKE 'program_id'");
        $has_program_id = $check_col && $check_col->num_rows > 0;
        if ($check_col) $check_col->close();
        
        // Build section name patterns to match BOTH formats:
        // Format 1: "BSIT 1-%" (with space) - e.g., "BSIT 1-A"
        // Format 2: "BSIT1-%" (without space) - e.g., "BSIT1-A"
        // This handles cases where sections were created with different naming conventions
        $section_name_pattern_with_space = $program_code . ' ' . $year_level . '-%';
        $section_name_pattern_without_space = $program_code . $year_level . '-%';
        
        // Get all existing section names for this program + year level + school year + term
        // CRITICAL: Only get sections from the CURRENT school year and term to allow same-named sections in different SY/terms
        // Strategy: Find sections matching EITHER name pattern format (with or without space)
        // Filter by program_id when available to ensure we only count sections from the same program
        if ($has_program_id) {
            // Find sections matching either pattern format, ONLY from the current school year and term
            // This ensures sections with the same name can exist in different school years/terms
            $existing_sec_stmt = $conn->prepare("
                SELECT DISTINCT sec.sec_name, sec.sec_num
                FROM section sec
                JOIN class cls ON sec.class_id = cls.class_id
                WHERE cls.sy_id = ?           -- MUST match current school year
                  AND cls.class_term = ?     -- MUST match current term
                  AND cls.class_lvl = ?       -- MUST match current year level
                  AND (
                      sec.sec_name LIKE ?
                      OR sec.sec_name LIKE ?
                  )
                  AND (
                      -- Match by exact program_id (same program - most accurate)
                      sec.program_id = ?
                      OR
                      -- Include sections with NULL program_id that match the name pattern
                      -- BUT only if they're in the same school year/term (already filtered above)
                      sec.program_id IS NULL
                  )
                ORDER BY sec.sec_num ASC
            ");
            $existing_sec_stmt->bind_param("iiissi", $sy_id, $term, $year_level, $section_name_pattern_with_space, $section_name_pattern_without_space, $program_id);
        } else {
            // Fallback: Get sections by class year level and section name pattern (both formats)
            // CRITICAL: Only from the current school year and term
            $existing_sec_stmt = $conn->prepare("
                SELECT DISTINCT sec.sec_name, sec.sec_num
                FROM section sec
                JOIN class cls ON sec.class_id = cls.class_id
                WHERE cls.sy_id = ?           -- MUST match current school year
                  AND cls.class_term = ?      -- MUST match current term
                  AND cls.class_lvl = ?       -- MUST match current year level
                  AND (
                      sec.sec_name LIKE ?
                      OR sec.sec_name LIKE ?
                  )
                ORDER BY sec.sec_num ASC
            ");
            $existing_sec_stmt->bind_param("iiiss", $sy_id, $term, $year_level, $section_name_pattern_with_space, $section_name_pattern_without_space);
        }
        
        if (!$existing_sec_stmt) {
            throw new Exception("Failed to prepare existing sections query: " . $conn->error);
        }
        
        $existing_sec_stmt->execute();
        $existing_sec_result = $existing_sec_stmt->get_result();
        
        if (!$existing_sec_result) {
            throw new Exception("Failed to execute existing sections query: " . $existing_sec_stmt->error);
        }
        
        // DEBUG: Log the query being executed
        $query_debug = "Query params: sy_id={$sy_id}, term={$term}, year_level={$year_level}, pattern_with_space={$section_name_pattern_with_space}, pattern_without_space={$section_name_pattern_without_space}";
        if ($has_program_id) {
            $query_debug .= ", program_id={$program_id}";
        }
        error_log("Auto Section Maker - Initial Section Query: {$query_debug}");
        
        $existing_sections = [];
        $existing_section_names = [];
        $max_existing_sec_num = 0;
        $detected_format = null; // Will store the format used in existing sections ('with_space' or 'without_space')
        
        // Filter sections to only count those that truly match our program context
        // Also detect which naming format is being used
        $raw_results_count = 0;
        while ($row = $existing_sec_result->fetch_assoc()) {
            $raw_results_count++;
            $sec_name = $row['sec_name'];
            
            // Verify the section name matches our expected pattern (with OR without space)
            // Pattern 1: "{program_code} {year_level}-{letter}" (with space)
            // Pattern 2: "{program_code}{year_level}-{letter}" (without space)
            $pattern_with_space = '/^' . preg_quote($program_code, '/') . '\s+' . $year_level . '-[A-Z]+$/';
            $pattern_without_space = '/^' . preg_quote($program_code, '/') . $year_level . '-[A-Z]+$/';
            
            $matches_with_space = preg_match($pattern_with_space, $sec_name);
            $matches_without_space = preg_match($pattern_without_space, $sec_name);
            
            if ($matches_with_space || $matches_without_space) {
                // This section matches our program/year level pattern - count it
                $existing_sections[] = $row;
                $existing_section_names[] = $sec_name;
                $max_existing_sec_num = max($max_existing_sec_num, (int)$row['sec_num']);
                
                // Detect which format is being used (use the first match to determine format)
                if ($detected_format === null) {
                    $detected_format = $matches_with_space ? 'with_space' : 'without_space';
                }
            }
        }
        $existing_sec_stmt->close();
        
        // DEBUG: Log initial query results
        error_log("Auto Section Maker - Initial Query Results: raw_count={$raw_results_count}, matched_count=" . count($existing_sections));
        if (count($existing_sections) > 0) {
            error_log("Auto Section Maker - Existing Section Names: " . implode(', ', $existing_section_names));
        }
        
        // If no existing sections found, default to 'with_space' format
        if ($detected_format === null) {
            $detected_format = 'with_space';
        }
        
        $total_existing_sections = count($existing_sections);
        
        // Extract section letters from existing section names to determine what letters are already used
        // This approach is more robust than relying on sec_num values which might be inconsistent
        $used_letters = [];
        foreach ($existing_section_names as $existing_name) {
            // Extract letter from section name (e.g., "BSIT 1-A" -> "A")
            if (preg_match('/-([A-Z]+)$/', $existing_name, $matches)) {
                $used_letters[] = strtoupper($matches[1]);
            }
        }
        
        // Determine starting section number - use max from existing sections, or start from 1
        $start_sec_num = ($max_existing_sec_num > 0) ? $max_existing_sec_num + 1 : 1;
        
        // Generate section names starting from the next available letter
        // Use the same format as existing sections (with or without space) for consistency
        $section_letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $sections_to_create = [];
        $current_sec_num = $start_sec_num;
        
        // Determine the format for new section names based on detected format
        // If existing sections use "BSIT1-A" (without space), use the same format
        // If existing sections use "BSIT 1-A" (with space) or no existing sections, use with space format
        $name_separator = ($detected_format === 'without_space') ? '' : ' ';
        
        // First, try single letters (A-Z)
        foreach ($section_letters as $letter) {
            if (count($sections_to_create) >= $num_sections) {
                break; // We have enough sections
            }
            
            // Use the detected format (with or without space)
            $sec_name = "{$program_code}{$name_separator}{$year_level}-{$letter}";
            
            // Only skip if this letter is already used in the CURRENT school year/term
            // The duplicate check later (lines 354-388) will do a more thorough check
            // including checking both name formats and school year/term
            if (!in_array($letter, $used_letters)) {
                $sections_to_create[] = [
                    'sec_num' => $current_sec_num,
                    'sec_letter' => $letter,
                    'sec_name' => $sec_name
                ];
                $current_sec_num++;
            }
        }
        
        // If we still need more sections, use double letters (AA, AB, AC, etc.)
        if (count($sections_to_create) < $num_sections) {
            foreach ($section_letters as $first_letter) {
                if (count($sections_to_create) >= $num_sections) {
                    break;
                }
                
                foreach ($section_letters as $second_letter) {
                    if (count($sections_to_create) >= $num_sections) {
                        break;
                    }
                    
                    $letter = $first_letter . $second_letter;
                    // Use the detected format (with or without space)
                    $sec_name = "{$program_code}{$name_separator}{$year_level}-{$letter}";
                    
                    // Only skip if this letter combination is already used in the CURRENT school year/term
                    // The duplicate check later will do a more thorough check
                    if (!in_array($letter, $used_letters)) {
                        $sections_to_create[] = [
                            'sec_num' => $current_sec_num,
                            'sec_letter' => $letter,
                            'sec_name' => $sec_name
                        ];
                        $current_sec_num++;
                    }
                }
            }
        }
        
        // Generate sections - only create the ones that don't exist
        $created_sections = [];
        $skipped_sections = [];
        
        foreach ($sections_to_create as $sec_data) {
            $sec_num = $sec_data['sec_num'];
            $sec_name = $sec_data['sec_name'];
            $label = $sec_name;
            
            // Note: We skip the section number check here because it's too restrictive
            // It only checks class_id + sec_num, which doesn't account for school year/term
            // The duplicate name check below is more accurate as it checks SY/term/year_level
            // This allows sections with the same sec_num to exist in different contexts if needed
            
            // Original check (commented out - was too restrictive):
            // $check_stmt = $conn->prepare("SELECT sec_id, sec_name FROM section WHERE class_id = ? AND sec_num = ?");
            // The duplicate name check below will catch any real duplicates
            
            // AUTHORITATIVE DUPLICATE CHECK: Check if a section with the same name already exists
            // CRITICAL: This check MUST filter by school year, term, and year level
            // This allows sections with the same name to exist in different school years/terms
            // Check BOTH formats (with and without space) to catch duplicates regardless of format
            try {
                // Generate the alternative format name (with space vs without space)
                if ($detected_format === 'without_space') {
                    // Current format is without space (e.g., "BSIT1-A"), check with space version ("BSIT 1-A")
                    $sec_name_alt_check = str_replace($program_code . $year_level, $program_code . ' ' . $year_level, $sec_name);
                } else {
                    // Current format is with space (e.g., "BSIT 1-A"), check without space version ("BSIT1-A")
                    $sec_name_alt_check = str_replace($program_code . ' ' . $year_level, $program_code . $year_level, $sec_name);
                }
                
                // If str_replace didn't change anything (pattern not found), generate manually
                if ($sec_name_alt_check === $sec_name) {
                    // Extract the letter from the current section name
                    if (preg_match('/-([A-Z]+)$/', $sec_name, $matches)) {
                        $letter = $matches[1];
                        // Generate alternative format
                        if ($detected_format === 'without_space') {
                            $sec_name_alt_check = $program_code . ' ' . $year_level . '-' . $letter;
                        } else {
                            $sec_name_alt_check = $program_code . $year_level . '-' . $letter;
                        }
                    } else {
                        // Fallback: just use the original name
                        $sec_name_alt_check = $sec_name;
                    }
                }
                
                // Simplified query - check for section name in either format
                // Match by exact section name (both formats) for same SY/term/year_level
                if ($has_program_id && $program_id > 0) {
                    // Check with program_id filter
                    $name_check_stmt = $conn->prepare("
                        SELECT sec.sec_id
                        FROM section sec
                        JOIN class cls ON sec.class_id = cls.class_id
                        WHERE cls.sy_id = ?
                          AND cls.class_term = ?
                          AND cls.class_lvl = ?
                          AND (
                              sec.sec_name = ?
                              OR sec.sec_name = ?
                          )
                          AND (
                              sec.program_id = ?
                              OR sec.program_id IS NULL
                          )
                        LIMIT 1
                    ");
                    $name_check_stmt->bind_param("iiissi", $sy_id, $term, $year_level, $sec_name, $sec_name_alt_check, $program_id);
                } else {
                    // Check without program_id filter (program_id column doesn't exist or is NULL)
                    $name_check_stmt = $conn->prepare("
                        SELECT sec.sec_id
                        FROM section sec
                        JOIN class cls ON sec.class_id = cls.class_id
                        WHERE cls.sy_id = ?
                          AND cls.class_term = ?
                          AND cls.class_lvl = ?
                          AND (
                              sec.sec_name = ?
                              OR sec.sec_name = ?
                          )
                        LIMIT 1
                    ");
                    $name_check_stmt->bind_param("iiiss", $sy_id, $term, $year_level, $sec_name, $sec_name_alt_check);
                }
                
                if (!$name_check_stmt) {
                    throw new Exception("Failed to prepare duplicate check query: " . $conn->error);
                }
                
                $name_check_stmt->execute();
                $name_check_result = $name_check_stmt->get_result();
                
                // DEBUG: Log duplicate check query details
                $duplicate_check_params = "sy_id={$sy_id}, term={$term}, year_level={$year_level}, sec_name={$sec_name}, sec_name_alt={$sec_name_alt_check}";
                if ($has_program_id && $program_id > 0) {
                    $duplicate_check_params .= ", program_id={$program_id}";
                }
                error_log("Auto Section Maker - Duplicate Check Query: {$duplicate_check_params}");
                
                if ($name_check_result && $name_check_result->num_rows > 0) {
                    // Section with this name already exists for this SY/term/year level, skip it
                    $duplicate_row = $name_check_result->fetch_assoc();
                    error_log("Auto Section Maker - Section skipped (duplicate name found): sec_name={$sec_name}, found_sec_id={$duplicate_row['sec_id']}, class_id={$class_id}, query_params: sy_id={$sy_id}, term={$term}, year_level={$year_level}");
                    $skipped_sections[] = $sec_name;
                    $name_check_stmt->close();
                    continue;
                } else {
                    error_log("Auto Section Maker - No duplicate found for: sec_name={$sec_name}, will attempt to create");
                }
                
                if ($name_check_stmt) {
                    $name_check_stmt->close();
                }
            } catch (Exception $e) {
                // If duplicate check fails, log error but continue (don't block section creation)
                error_log("Duplicate check error for section {$sec_name}: " . $e->getMessage());
                // Continue to try creating the section anyway
            }
            
            // Insert section (include program_id if column exists)
            // Use $has_program_id that was already checked earlier
            try {
                if ($has_program_id) {
                    $insert_stmt = $conn->prepare("INSERT INTO section (class_id, program_id, sec_num, sec_name) VALUES (?, ?, ?, ?)");
                    if (!$insert_stmt) {
                        throw new Exception("Failed to prepare insert statement: " . $conn->error);
                    }
                    $insert_stmt->bind_param("iiis", $class_id, $program_id, $sec_num, $sec_name);
                } else {
                    $insert_stmt = $conn->prepare("INSERT INTO section (class_id, sec_num, sec_name) VALUES (?, ?, ?)");
                    if (!$insert_stmt) {
                        throw new Exception("Failed to prepare insert statement: " . $conn->error);
                    }
                    $insert_stmt->bind_param("iis", $class_id, $sec_num, $sec_name);
                }
                
                if ($insert_stmt->execute()) {
                    $created_sections[] = [
                        'sec_id' => $conn->insert_id,
                        'sec_num' => $sec_num,
                        'sec_name' => $sec_name,
                        'label' => $label,
                        'program_code' => $program_code
                    ];
                } else {
                    $error_msg = $insert_stmt->error ?: 'Unknown error';
                    $skipped_sections[] = $sec_name . ' (insert failed: ' . $error_msg . ')';
                }
                $insert_stmt->close();
            } catch (Exception $e) {
                $skipped_sections[] = $sec_name . ' (insert failed: ' . $e->getMessage() . ')';
                if (isset($insert_stmt)) {
                    $insert_stmt->close();
                }
            }
        }
        
        // Build response message - only show successfully created sections
        $created_count = count($created_sections);
        $skipped_count = count($skipped_sections);
        
        // DEBUG: Log final results
        error_log("Auto Section Maker - Final Results: created_count={$created_count}, skipped_count={$skipped_count}, class_id={$class_id}");
        if ($skipped_count > 0) {
            error_log("Auto Section Maker - Skipped Sections: " . implode(', ', array_unique($skipped_sections)));
        }
        if ($created_count > 0) {
            error_log("Auto Section Maker - Created Sections: " . implode(', ', array_column($created_sections, 'sec_name')));
        }
        
        // Add debug info to response for troubleshooting
        // Ensure all variables are defined before adding to debug
        $response['debug'] = [
            'input_params' => [
                'sy_id' => $sy_id ?? null,
                'term' => $term ?? null,
                'program_id' => $program_id ?? null,
                'year_level' => $year_level ?? null,
                'num_sections' => $num_sections ?? null
            ],
            'class_id' => $class_id ?? null,
            'program_code' => $program_code ?? 'NOT_SET',
            'curr_id' => $curr_id ?? null,
            'existing_sections_found' => isset($existing_sections) ? count($existing_sections) : 0,
            'existing_section_names' => isset($existing_section_names) ? $existing_section_names : [],
            'sections_to_create_count' => isset($sections_to_create) ? count($sections_to_create) : 0,
            'sections_to_create' => isset($sections_to_create) ? array_column($sections_to_create, 'sec_name') : [],
            'skipped_sections' => isset($skipped_sections) ? array_unique($skipped_sections) : []
        ];
        
        if ($created_count > 0) {
            $response['success'] = true;
            $message = "Successfully created {$created_count} section(s).";
            if ($skipped_count > 0) {
                $message .= " {$skipped_count} section(s) were skipped because they already exist.";
            }
            $response['message'] = $message;
            $response['created'] = $created_sections;
            $response['class_id'] = $class_id;
            if ($skipped_count > 0) {
                $response['skipped'] = $skipped_sections;
            }
        } else {
            if ($skipped_count > 0) {
                $response['message'] = "No new sections were created. All {$skipped_count} requested section(s) already exist: " . implode(', ', array_unique($skipped_sections));
                $response['skipped'] = array_unique($skipped_sections); // Include skipped sections in response for debugging
            } else {
                $response['message'] = 'No new sections were created. All requested sections already exist.';
            }
        }
        
        // Ensure debug info is always included (re-add if it was lost)
        if (!isset($response['debug'])) {
            $response['debug'] = [
                'input_params' => [
                    'sy_id' => $sy_id ?? null,
                    'term' => $term ?? null,
                    'program_id' => $program_id ?? null,
                    'year_level' => $year_level ?? null,
                    'num_sections' => $num_sections ?? null
                ],
                'class_id' => $class_id ?? null,
                'program_code' => $program_code ?? 'NOT_SET',
                'curr_id' => $curr_id ?? null,
                'existing_sections_found' => isset($existing_sections) ? count($existing_sections) : 0,
                'existing_section_names' => isset($existing_section_names) ? $existing_section_names : [],
                'sections_to_create_count' => isset($sections_to_create) ? count($sections_to_create) : 0,
                'sections_to_create' => isset($sections_to_create) ? array_column($sections_to_create, 'sec_name') : [],
                'skipped_sections' => isset($skipped_sections) ? array_unique($skipped_sections) : []
            ];
        }
        
    } catch (Exception $e) {
        // Log the full error for debugging
        error_log("Auto Section Maker Error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        $response['success'] = false;
        $response['message'] = 'An error occurred while generating sections: ' . $e->getMessage();
        // Include more details in development mode
        if (ini_get('display_errors')) {
            $response['error_details'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }
    } catch (Error $e) {
        // Catch PHP 7+ errors (TypeError, etc.)
        error_log("Auto Section Maker Fatal Error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        $response['success'] = false;
        $response['message'] = 'A fatal error occurred while generating sections: ' . $e->getMessage();
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Invalid request method.';
}

// Always return valid JSON, even on error
header('Content-Type: application/json');
echo json_encode($response);
?>

