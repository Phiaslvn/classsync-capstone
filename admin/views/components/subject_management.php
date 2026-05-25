<?php
/**
 * Curriculum & Subject Management Component
 * Combined curriculum and subject listing and management for admins
 */

// Ensure database connection is available
if (!isset($conn)) {
    require_once __DIR__ . '/../../../config/database.php';
}

// Ensure security middleware functions are available
if (!function_exists('getUserInfo')) {
    require_once __DIR__ . '/../../../includes/auth/security_middleware.php';
}

// Get currentDeptId from parent scope if available, otherwise fetch it
if (!isset($currentDeptId)) {
    $userInfo = getUserInfo();
    $currentDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
}

// Check if user is Admin Support
$isAdminSupport = function_exists('isAdminSupport') ? isAdminSupport() : false;

// Fetch programs for the current department
$programs = [];
try {
    if ($currentDeptId > 0 || $isAdminSupport) {
        $programQuery = "SELECT program_id, program_code, program_name, 
                                CONCAT(program_code, ' - ', program_name) as program_display
                         FROM program 
                         WHERE program_status = 'Active'";
        
        if (!$isAdminSupport && $currentDeptId > 0) {
            $programQuery .= " AND dept_id = ?";
            $programStmt = $conn->prepare($programQuery);
            if ($programStmt) {
                $programStmt->bind_param("i", $currentDeptId);
                $programStmt->execute();
                $programResult = $programStmt->get_result();
                while ($row = $programResult->fetch_assoc()) {
                    $programs[] = $row;
                }
                $programStmt->close();
            }
        } else {
            $programStmt = $conn->prepare($programQuery);
            if ($programStmt) {
                $programStmt->execute();
                $programResult = $programStmt->get_result();
                while ($row = $programResult->fetch_assoc()) {
                    $programs[] = $row;
                }
                $programStmt->close();
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching programs in subject_management.php: " . $e->getMessage());
    // Continue with empty programs array
}
?>

<!-- Curriculum Management Section -->
<div class="dashboard-card mb-4">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-mortarboard me-2"></i>Curricula — <?= htmlspecialchars($userDisplayData['dept_name']) ?>
            </h4>
            <p class="text-muted mb-0 small">Official course lists for your department. Subjects are added and linked under <strong>Subjects</strong> below.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-maroon" onclick="openAddCurriculumModal()">
                <i class="bi bi-plus-circle me-2"></i>Add Curriculum
            </button>
        </div>
    </div>

    <!-- Curriculum Table (no inner card — single dashboard panel) -->
    <div class="curriculum-table-region">
            <div id="curriculumLoading" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading curricula...</p>
            </div>

            <div id="curriculumEmpty" class="text-center py-5" style="display: none;">
                <i class="bi bi-folder-x fs-1 text-muted mb-3"></i>
                <h5>No Curricula Found</h5>
                <p class="text-muted">There are no curricula to display for this department.</p>
                <button class="btn btn-primary" onclick="openAddCurriculumModal()">
                    <i class="bi bi-plus-circle me-1"></i>Add the First One
                </button>
            </div>

            <div id="curriculumTableContainer" style="display: none;">
                <div class="table-responsive pt-1">
                    <table class="table table-hover" id="curriculaTable" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Effective Start Year</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded here by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</div>

<!-- Subject Management Section -->
<div class="dashboard-card subject-management-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="flex-grow-1" style="min-width: 220px;">
            <h2 class="h4 mb-1">Subjects</h2>
        </div>
        <div class="d-flex gap-2 align-items-center flex-shrink-0 flex-wrap">
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#updateCurriculumModal">
                <i class="bi bi-arrow-repeat me-1"></i>Update Curriculum
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="bi bi-plus-circle me-1"></i>Add Subject
            </button>
        </div>
    </div>

    <!-- Filters: program, curriculum, term (+ search). — minimal chrome (no nested cards) -->
    <div class="row g-2 mb-3 align-items-end subject-filters-row">
        <div class="col-md-3 col-sm-6">
            <label class="form-label small text-muted mb-1" for="subjectProgramFilter">Program</label>
            <select class="form-select form-select-sm border-0 border-bottom rounded-0 shadow-none bg-transparent px-0" id="subjectProgramFilter">
                <option value="">All Programs</option>
                <!-- Programs will be populated via JavaScript -->
            </select>
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label small text-muted mb-1" for="subjectCurriculumFilter">Curriculum</label>
            <select class="form-select form-select-sm border-0 border-bottom rounded-0 shadow-none bg-transparent px-0" id="subjectCurriculumFilter">
                <option value="">All Curricula</option>
                <?php
                // Populate curriculum options
                $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : ($_SESSION['dept_id'] ?? 0);
                $curricula_stmt = $conn->prepare("SELECT curr_id, curr_name, curr_yr, curr_effective_start_year FROM curriculum WHERE dept_id = ? ORDER BY COALESCE(NULLIF(curr_yr, ''), CAST(curr_effective_start_year AS CHAR)) DESC, curr_name ASC");
                $curricula_stmt->bind_param("i", $dept_id);
                $curricula_stmt->execute();
                $curricula = $curricula_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $curricula_stmt->close();
                foreach ($curricula as $curriculum) {
                    // Use curr_yr if available, otherwise fallback to curr_effective_start_year, or show nothing
                    $year_display = '';
                    if (!empty($curriculum['curr_yr'])) {
                        $year_display = ' (' . htmlspecialchars($curriculum['curr_yr']) . ')';
                    } elseif (!empty($curriculum['curr_effective_start_year'])) {
                        $year_display = ' (' . htmlspecialchars($curriculum['curr_effective_start_year']) . ')';
                    }
                    echo '<option value="' . htmlspecialchars($curriculum['curr_id']) . '">' . htmlspecialchars($curriculum['curr_name']) . $year_display . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label small text-muted mb-1" for="subjectSemesterFilter">Term</label>
            <select class="form-select form-select-sm border-0 border-bottom rounded-0 shadow-none bg-transparent px-0" id="subjectSemesterFilter">
                <option value="">All terms</option>
                <?php
                // Get active semester - check settings table first (primary source), then fallback to active_school_year_semester
                $active_semester_value = '';
                try {
                    // Method 1: Check settings table (preferred - stores "1st Semester", "2nd Semester", or "Mid-Year")
                    $settings_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_semester' LIMIT 1");
                    if ($settings_stmt) {
                        $settings_stmt->execute();
                        $settings_result = $settings_stmt->get_result();
                        if ($settings_row = $settings_result->fetch_assoc()) {
                            $semester = $settings_row['setting_value'];
                            // Convert semester format to term ID: "1st Semester" -> 1, "2nd Semester" -> 2, "Mid-Year" -> 3 (Summer)
                            if ($semester === '1st Semester') {
                                $active_semester_value = '1';
                            } elseif ($semester === '2nd Semester') {
                                $active_semester_value = '2';
                            } elseif ($semester === 'Mid-Year') {
                                $active_semester_value = '3'; // Mid/Summer
                            }
                        }
                        $settings_stmt->close();
                    }
                    
                    // Method 2: Fallback to active_school_year_semester table if settings table has no data
                    if (empty($active_semester_value)) {
                        $active_sem_stmt = $conn->prepare("SELECT semester FROM active_school_year_semester WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
                        if ($active_sem_stmt) {
                            $active_sem_stmt->execute();
                            $active_sem_result = $active_sem_stmt->get_result();
                            if ($active_sem_row = $active_sem_result->fetch_assoc()) {
                                $semester = $active_sem_row['semester'];
                                // Convert semester to term ID: '1st' -> 1, '2nd' -> 2, 'Summer' -> 3
                                if ($semester === '1st') {
                                    $active_semester_value = '1';
                                } elseif ($semester === '2nd') {
                                    $active_semester_value = '2';
                                } elseif ($semester === 'Summer') {
                                    $active_semester_value = '3';
                                }
                            }
                            $active_sem_stmt->close();
                        }
                    }
                } catch (Exception $e) {
                    // If error, leave default as empty (no selection)
                    error_log("Error getting active semester: " . $e->getMessage());
                }
                
                // Output options with active semester selected
                $selected_1 = ($active_semester_value === '1') ? 'selected' : '';
                $selected_2 = ($active_semester_value === '2') ? 'selected' : '';
                $selected_3 = ($active_semester_value === '3') ? 'selected' : '';
                ?>
                <option value="1" <?php echo $selected_1; ?>>1st Term</option>
                <option value="2" <?php echo $selected_2; ?>>2nd Term</option>
                <option value="3" <?php echo $selected_3; ?>>Summer</option>
            </select>
        </div>
        <div class="col-md-4 col-sm-12">
            <label class="form-label small text-muted mb-1" for="subjectSearchInput">Search</label>
            <input type="text" class="form-control form-control-sm border-0 border-bottom rounded-0 shadow-none bg-transparent px-0" id="subjectSearchInput" placeholder="Code or title…">
        </div>
    </div>

    <!-- Subject Statistics (counts match current filters) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="totalSubjectsCount">-</div>
                    <small class="text-muted">Total Subjects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="majorSubjectsCount">-</div>
                    <small class="text-muted">Major</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="minorSubjectsCount">-</div>
                    <small class="text-muted">Minor</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="genedSubjectsCount">-</div>
                    <small class="text-muted">GENED</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Subjects tables (no extra card wrapper) -->
    <div class="pt-1" id="filteredSubjectsContainer">
            <div id="subjectsLoadingState" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading subjects...</p>
            </div>
            
            <div id="subjectsErrorState" class="text-center py-5" style="display: none;">
                <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                <h5>Error Loading Subjects</h5>
                <p class="text-muted" id="subjectsErrorMessage">Failed to load subjects. Please try again.</p>
                <button class="btn btn-outline-primary" onclick="loadSubjects()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                </button>
            </div>
            
            <div id="subjectsPlaceholder" class="text-center py-5">
                <i class="bi bi-calendar-event fs-1 text-muted mb-3"></i>
                <h5 class="mb-2">Your subject list</h5>
                <p class="text-muted small mb-0">Tables load by <strong>year level</strong> and <strong>term</strong> (all terms or one). Use <strong>Add Subject</strong> to add a course to a curriculum.</p>
            </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubjectModalLabel">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSubjectForm">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <label for="subj_code" class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subj_code" name="subj_code" required placeholder="e.g., IT 113" onkeyup="checkSubjectCodeDebounced()">
                            <div id="subjectCodeValidation" class="form-text"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="subj_category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="subj_category" name="subj_category" required>
                                <option value="">Select Category</option>
                                <option value="Major">Major</option>
                                <option value="Minor">Minor</option>
                                <option value="GENED">General Education</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="subj_desc" class="form-label">Subject Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="subj_desc" name="subj_desc" rows="2" required placeholder="e.g., Introduction to Computing"></textarea>
                        </div>
                        
                        <!-- Hours and Units -->
                        <div class="col-md-3">
                            <label for="subj_lec" class="form-label">Lec Hours <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="subj_lec" name="subj_lec" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="subj_lab" class="form-label">Lab Hours <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="subj_lab" name="subj_lab" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="subj_unit" class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="subj_unit" name="subj_unit" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="subj_min" class="form-label">Min. Grade</label>
                            <input type="number" class="form-control" id="subj_min" name="subj_min" min="0" max="100" value="75" required>
                        </div>
                        
                        <!-- Academic Level and Term -->
                        <div class="col-md-6">
                            <label for="subj_lvl" class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select class="form-select" id="subj_lvl" name="subj_lvl" required>
                                <option value="">Select Level</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="subj_term" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="subj_term" name="subj_term" required>
                                <option value="">Select Term</option>
                                <option value="1">1st Term</option>
                                <option value="2">2nd Term</option>
                                <option value="3">Summer</option>
                            </select>
                        </div>
                        
                       
                            <!-- Department ID is now a hidden field, automatically set -->
                            <input type="hidden" id="add_subject_dept_id" name="dept_id" required>
                     
                        <div class="col-md-6">
                            <label for="add_subject_program_id" class="form-label">Program <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_subject_program_id" name="program_id" required>
                                <option value="">Select Program</option>
                            </select>
                            <small class="form-text text-muted">Choose program before selecting curriculum.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="add_subject_curr_id" class="form-label">Curriculum <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_subject_curr_id" name="curr_id" required>
                                <option value="">Select Curriculum</option>
                            </select>
                            <small class="form-text text-muted">List is filtered using the selected program.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSubjectFromManagement('addSubjectForm', 'addSubjectModal')">Save Subject</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubjectModalLabel">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSubjectForm">
                    <input type="hidden" id="edit_subj_id" name="subj_id">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <label for="edit_subj_code" class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_subj_code" name="subj_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_subj_category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_subj_category" name="subj_category" required>
                                <option value="">Select Category</option>
                                <option value="Major">Major</option>
                                <option value="Minor">Minor</option>
                                <option value="GENED">General Education</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="edit_subj_desc" class="form-label">Subject Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_subj_desc" name="subj_desc" rows="2" required></textarea>
                        </div>
                        
                        <!-- Hours and Units -->
                        <div class="col-md-3">
                            <label for="edit_subj_lec" class="form-label">Lec Hours <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_subj_lec" name="subj_lec" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_subj_lab" class="form-label">Lab Hours <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_subj_lab" name="subj_lab" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_subj_unit" class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_subj_unit" name="subj_unit" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_subj_min" class="form-label">Min. Grade</label>
                            <input type="number" class="form-control" id="edit_subj_min" name="subj_min" min="0" max="100" required>
                        </div>
                        
                        <!-- Academic Level and Term -->
                        <div class="col-md-6">
                            <label for="edit_subj_lvl" class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_subj_lvl" name="subj_lvl" required>
                                <option value="">Select Level</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_subj_term" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_subj_term" name="subj_term" required>
                                <option value="">Select Term</option>
                                <option value="1">1st Term</option>
                                <option value="2">2nd Term</option>
                                <option value="3">Summer</option>
                            </select>
                        </div>
                        
                        <!-- Program and Curriculum Selection (Editable) -->
                        <div class="col-md-6">
                            <label for="edit_subject_program_id" class="form-label">Program <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_subject_program_id" name="program_id" required>
                                <option value="">Select Program</option>
                            </select>
                            <small class="form-text text-muted">Changing program refreshes available curricula.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_subject_curr_id" class="form-label">Curriculum <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_subject_curr_id" name="curr_id" required>
                                <option value="">Select Curriculum</option>
                            </select>
                            <small class="form-text text-muted">Pick the curriculum used by this subject.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateSubject()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- View Subject Modal -->
<div class="modal fade" id="viewSubjectModal" tabindex="-1" aria-labelledby="viewSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSubjectModalLabel">Subject Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Subject Code</label>
                        <p class="fw-bold fs-5" id="view_subj_code">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Category</label>
                        <p><span class="badge" id="view_subj_category">-</span></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small">Subject Description</label>
                        <p id="view_subj_desc">-</p>
                    </div>
                    
                    <div class="col-12"><hr class="my-1"></div>

                    <!-- Hours and Units -->
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Lec Hours</label>
                        <p id="view_subj_lec">-</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Lab Hours</label>
                        <p id="view_subj_lab">-</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Units</label>
                        <p id="view_subj_unit">-</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Min. Grade</label>
                        <p id="view_subj_min">-</p>
                    </div>

                    <!-- Academic Level and Term -->
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Year Level</label>
                        <p id="view_subj_lvl">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Term</label>
                        <p id="view_subj_term">-</p>
                    </div>

                    <!-- Program and Curriculum -->
                    <div class="col-12">
                        <label class="form-label text-muted small">Program & Curriculum</label>
                        <p class="mb-0"><strong id="view_program_name"></strong> (<span id="view_curriculum_name"></span>)</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Curriculum Modal -->
<div class="modal fade" id="updateCurriculumModal" tabindex="-1" aria-labelledby="updateCurriculumModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title" id="updateCurriculumModalLabel">
                    <i class="bi bi-arrow-repeat me-2"></i>Update Curriculum for Subjects
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateCurriculumForm">
                    <div class="alert" style="background-color: rgba(235, 30, 30, 0.1); border-color: var(--primary); color: var(--primary);">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Important:</strong> Curriculum is the source of truth for subjects. Year levels do NOT own copies of subjects - they only reference a curriculum.<br><br>
                        <strong>What this does:</strong> Updates which curriculum the <strong>selected year level(s) only</strong> reference. After update:<br>
                        • <strong>Selected year level(s)</strong> will show exactly the subjects defined in the new curriculum<br>
                        • <strong>Unselected year levels</strong> will continue using their existing curriculum (unchanged)<br>
                        • No copying, no merging - just changing the reference for selected year levels only
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Select Year Level(s) <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="yearLevelAll" name="year_levels[]" value="all">
                                <label class="form-check-label" for="yearLevelAll">
                                    <strong>All Years</strong>
                                </label>
                            </div>
                            <hr class="my-2">
                            <div class="form-check">
                                <input class="form-check-input year-level-checkbox" type="checkbox" id="yearLevel1" name="year_levels[]" value="1">
                                <label class="form-check-label" for="yearLevel1">1st Year</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input year-level-checkbox" type="checkbox" id="yearLevel2" name="year_levels[]" value="2">
                                <label class="form-check-label" for="yearLevel2">2nd Year</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input year-level-checkbox" type="checkbox" id="yearLevel3" name="year_levels[]" value="3">
                                <label class="form-check-label" for="yearLevel3">3rd Year</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input year-level-checkbox" type="checkbox" id="yearLevel4" name="year_levels[]" value="4">
                                <label class="form-check-label" for="yearLevel4">4th Year</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input year-level-checkbox" type="checkbox" id="yearLevel5" name="year_levels[]" value="5">
                                <label class="form-check-label" for="yearLevel5">5th Year</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="update_curr_program_id" class="form-label">Program <span class="text-danger">*</span></label>
                            <select class="form-select" id="update_curr_program_id" name="program_id" required>
                                <option value="">Select Program</option>
                            </select>
                            <small class="form-text text-muted">Select the program for the subjects to update</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="update_curr_curr_id" class="form-label">New Curriculum <span class="text-danger">*</span></label>
                            <select class="form-select" id="update_curr_curr_id" name="curr_id" required>
                                <option value="">Select Curriculum</option>
                            </select>
                            <small class="form-text text-muted">Select the new curriculum to assign</small>
                        </div>
                        
                        <div class="col-12">
                            <div class="card" id="currentCurriculumDisplay" style="display: none; border-color: var(--primary) !important;">
                                <div class="card-header bg-maroon text-white">
                                    <i class="bi bi-info-circle me-2"></i>Current Curriculum Usage by Year Level
                                </div>
                                <div class="card-body">
                                    <div id="currentCurriculumInfo" class="small">
                                        <em>Select a program to see current curriculum usage...</em>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-warning" id="updateCurriculumPreview" style="display: none;">
                                <strong>Preview:</strong> <span id="updateCurriculumPreviewText"></span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-maroon" onclick="submitUpdateCurriculum()">
                    <i class="bi bi-arrow-repeat me-2"></i>Update Curriculum
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Curriculum Modal -->
<div class="modal fade" id="addCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title" id="addCurriculumModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add New Curriculum
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addCurriculumFormManagement">
                    <input type="hidden" id="dept_id" name="dept_id" value="<?= $currentDeptId ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Curriculum Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="curr_name" name="curr_name" required placeholder="e.g., Bachelor of Science in Computer Science">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="curr_status" name="curr_status" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Effective Start Year</label>
                            <select id="curr_effective_start_year" name="curr_effective_start_year" class="form-select">
                                <option value="">Select Start Year</option>
                                <?php
                                $currentYear = date('Y');
                                $startYear = $currentYear + 5;
                                $endYear = $currentYear - 20;
                                for ($year = $startYear; $year >= $endYear; $year--) {
                                    $selected = ($year == $currentYear) ? 'selected' : '';
                                    echo "<option value=\"$year\" $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Effective End Year</label>
                            <select id="curr_effective_end_year" name="curr_effective_end_year" class="form-select">
                                <option value="">Select End Year (Optional)</option>
                                <option value="">No End Date</option>
                                <?php
                                $currentYear = date('Y');
                                $startYear = $currentYear + 10;
                                $endYear = $currentYear - 10;
                                for ($year = $startYear; $year >= $endYear; $year--) {
                                    echo "<option value=\"$year\">$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-maroon js-save-curriculum-btn" id="saveCurriculumBtn" onclick="saveCurriculumFromModal('addCurriculumFormManagement', 'addCurriculumModal')"><i class="bi bi-save me-2"></i>Save Curriculum</button>
            </div>
        </div>
    </div>
</div>

<!-- View Curriculum Modal -->
<div class="modal fade" id="viewCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>View Curriculum Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Curriculum Name</label>
                        <p class="fw-bold fs-5" id="view_curr_name">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Status</label>
                        <p id="view_curr_status">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Effective Start Year</label>
                        <p id="view_curr_effective_start_year">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Effective End Year</label>
                        <p id="view_curr_effective_end_year">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Curriculum Modal -->
<div class="modal fade" id="editCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Curriculum
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editCurriculumForm">
                    <input type="hidden" id="edit_curr_id" name="curr_id">
                    <input type="hidden" id="edit_dept_ids" name="dept_id" value="<?= $currentDeptId ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Curriculum Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_curr_name" name="curr_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="edit_curr_status" name="curr_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Effective Start Year</label>
                            <select id="edit_curr_effective_start_year" name="curr_effective_start_year" class="form-select">
                                <option value="">Select Start Year</option>
                                <?php
                                $currentYear = date('Y');
                                $startYear = $currentYear + 5;
                                $endYear = $currentYear - 20;
                                for ($year = $startYear; $year >= $endYear; $year--) {
                                    echo "<option value=\"$year\">$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Effective End Year</label>
                            <select id="edit_curr_effective_end_year" name="curr_effective_end_year" class="form-select">
                                <option value="">Select End Year (Optional)</option>
                                <option value="">No End Date</option>
                                <?php
                                $currentYear = date('Y');
                                $startYear = $currentYear + 10;
                                $endYear = $currentYear - 10;
                                for ($year = $startYear; $year >= $endYear; $year--) {
                                    echo "<option value=\"$year\">$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="saveCurriculumEdits()">
                    <i class="bi bi-save me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
