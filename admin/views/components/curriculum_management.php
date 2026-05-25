<?php
/**
 * Curriculum Management Component
 * Handles curriculum creation, editing, and management
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
    error_log("Error fetching programs in curriculum_management.php: " . $e->getMessage());
    // Continue with empty programs array
}
?>

<div class="dashboard-card curriculum-management-card">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div class="flex-grow-1" style="min-width: 220px;">
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-mortarboard me-2"></i>Curricula — <?= htmlspecialchars($userDisplayData['dept_name']) ?>
            </h4>
            <p class="text-muted small mb-2">Official course lists (revisions, effective years). Subjects are linked to these in the <strong>Subjects</strong> tab.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-shrink-0">
            <button type="button" class="btn btn-maroon" onclick="openAddCurriculumModal()">
                <i class="bi bi-plus-circle me-2"></i>Add Curriculum
            </button>
        </div>
    </div>

    <!-- Curriculum Table -->
    <div class="card">
    <div class="card-body">
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
        <div class="table-responsive">
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

<!-- Add Program/Course Modal -->
<!-- <div class="modal fade" id="addProgramFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-mortarboard me-2"></i>Add New Program/Course
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProgramFormManagement">
                    <input type="hidden" name="dept_id" value="<?= $currentDeptId ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Program/Course Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="program_name" required placeholder="e.g., Bachelor of Science in Computer Science">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="program_code" required placeholder="e.g., BSCS" maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Program Type <span class="text-danger">*</span></label>
                            <select name="program_type" class="form-select" required>
                                <option value="">Select Program Type</option>
                                <option value="Bachelor's Degree">Bachelor's Degree</option>
                                <option value="Master's Degree">Master's Degree</option>
                                <option value="Doctorate">Doctorate</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Associate Degree">Associate Degree</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select name="academic_year" class="form-select" required>
                                <option value="">Select Academic Year</option>
                                <option value="2024-2025">2024-2025</option>
                                <option value="2023-2024">2023-2024</option>
                                <option value="2022-2023">2022-2023</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                            <select name="duration" class="form-select" required>
                                <option value="">Select Duration</option>
                                <option value="1">1 Year</option>
                                <option value="2">2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                                <option value="5">5 Years</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Units</label>
                            <input type="number" class="form-control" name="total_units" placeholder="e.g., 120" min="0" max="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tuition Fee (per semester)</label>
                            <input type="number" class="form-control" name="tuition_fee" placeholder="e.g., 15000" min="0" step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Program Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the program..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Program Objectives</label>
                            <textarea class="form-control" name="objectives" rows="3" placeholder="Program objectives and learning outcomes..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Admission Requirements</label>
                            <textarea class="form-control" name="requirements" rows="2" placeholder="Admission requirements and prerequisites..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="saveProgramBtn" onclick="saveProgram()">
                    <i class="bi bi-save me-2"></i>Save Program
                </button>
            </div>
        </div>
    </div>
</div> -->
