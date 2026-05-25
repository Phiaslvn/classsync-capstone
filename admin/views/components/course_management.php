<?php
/**
 * Course Management Component
 * Subject listing and management for admins
 */

// Ensure security middleware is available (in case it's not included in parent)
if (!function_exists('getUserInfo')) {
    require_once __DIR__ . '/../../../includes/auth/security_middleware.php';
}

// Get user info for department ID
$userInfo = getUserInfo();
$currentDeptId = $userInfo ? (int)$userInfo['dept_id'] : 0;
?>

<div class="dashboard-card course-management-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div class="flex-grow-1" style="min-width: 220px;">
            <h2 class="h4 mb-1">Programs</h2>
            <p class="text-muted small mb-2">Degree or program offerings for your department (code, name, duration). <strong>Subjects</strong> and <strong>Curricula</strong> build on these.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-shrink-0">
            <button class="btn btn-outline-primary btn-sm" onclick="refreshloadPrograms()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            <button class="btn btn-primary btn-sm" onclick="openAddProgram()">
                <i class="bi bi-plus-circle me-1"></i>Add Program
            </button>
        </div>
    </div>

    <!-- Programs Table -->
    <div class="card">
        <div class="card-body">
            <div id="programsLoadingState" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading programs...</p>
            </div>
            
            <div id="subjectsErrorState" class="text-center py-5" style="display: none;">
                <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                <h5>Error Loading Subjects</h5>
                <p class="text-muted" id="subjectsErrorMessage">Failed to load subjects. Please try again.</p>
                <button class="btn btn-outline-primary" onclick="loadPrograms()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                </button>
            </div>
            
            <div id="programsTableContainer">
                <div class="table-responsive">
                    <table id="programsTable" class="table table-hover" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Program Code</th>
                                <th scope="col">Program Name</th>
                                <th scope="col">Department</th>
                                <th scope="col">Program Duration</th>
                                <th scope="col">Major Track</th>
                                <th scope="col">Total Units</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created At</th>
                                <th scope="col">Updated At</th>
                                <th scope="col" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="programsTableBody">
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-book fs-1 d-block mb-3"></i>
                                        <h5>No Course found</h5>
                                        <p class="mb-0">Click refresh to load course or add a new one.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProgramModalLabel">Add New Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addProgramFormManagement">
                    <p class="text-muted small mb-0">Required fields are marked with <span class="text-danger">*</span>. Other fields are optional.</p>
                    <div class="row g-3 mt-1">
                    <input type="hidden" id="dept_id" name="dept_id" value="<?= $currentDeptId ?>">
                        <div class="col-md-6">
                            <label for="program_code" class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="program_code_course" name="program_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="program_name" class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="program_name_course" name="program_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="effective_academic_year" class="form-label">Effective Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" id="effective_academic_year_course" name="effective_academic_year" required>
                                <option value="">Select Academic Year</option>
                                <?php
                                $currentYear = date('Y');
                                $startYear = $currentYear + 5;
                                $endYear = $currentYear - 10;
                                for ($year = $startYear; $year >= $endYear; $year--) {
                                    $academicYear = $year . '-' . ($year + 1);
                                    $selected = ($year == $currentYear) ? 'selected' : '';
                                    echo "<option value=\"$academicYear\" $selected>$academicYear</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="program_type" class="form-label">Program Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="program_type_course" name="program_type" required>
                                <option value="">Select Program Type</option>
                                <option value="BS">BS (Bachelor of Science)</option>
                                <option value="BTVTED">BTVTED (Bachelor of Technical-Vocational Teacher Education)</option>
                                <option value="BEED">BEED (Bachelor of Elementary Education)</option>
                                <option value="BSED">BSED (Bachelor of Secondary Education)</option>
                                <option value="BSPED">BSPED (Bachelor of Special Needs Education)</option>
                                <option value="BPE">BPE (Bachelor of Physical Education)</option>
                                <option value="BSE">BSE (Bachelor of Science in Engineering)</option>
                                <option value="BSA">BSA (Bachelor of Science in Agriculture)</option>
                                <option value="BSN">BSN (Bachelor of Science in Nursing)</option>
                                <option value="BSBA">BSBA (Bachelor of Science in Business Administration)</option>
                                <option value="BSCrim">BSCrim (Bachelor of Science in Criminology)</option>
                                <option value="BSAcc">BSAcc (Bachelor of Science in Accountancy)</option>
                                <option value="Master's Degree">Master's Degree</option>
                                <option value="Doctorate">Doctorate</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Associate Degree">Associate Degree</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="total_units_required" class="form-label">Total Units Required <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="total_units_required_course" name="total_units_required" required min="0" max="300" placeholder="e.g., 120">
                            <small class="form-text text-muted">Total number of units required to complete the program</small>
                        </div>
                        <div class="col-md-6">
                            <label for="major_track" class="form-label">Major / Track <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="major_track_course" name="major_track" placeholder="e.g., Computer Science, Information Technology" maxlength="100">
                            <small class="form-text text-muted">Leave blank if not applicable.</small>
                        </div>
                        <!-- <div class="col-md-6">
                            <label for="program_dept_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="program_dept_id_course" name="dept_id" required>
                                <option value="">Select Department</option>
                            </select>
                        </div> -->
                        <div class="col-md-6">
                            <label for="program_years" class="form-label">Program Duration (Years) <span class="text-danger">*</span></label>
                            <select class="form-select" id="program_years_course" name="program_years" required>
                                <option value="">Select Years</option>
                                <option value="2">2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4" selected>4 Years</option>
                                <option value="5">5 Years</option>
                                <option value="6">6 Years</option>
                            </select>
                            <small class="form-text text-muted">Select how many years this program takes to complete</small>
                        </div>
                        <div class="col-md-6">
                            <label for="program_status" class="form-label">Status <span class="text-muted fw-normal">(optional)</span></label>
                            <select class="form-select" id="program_status_course" name="program_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <small class="form-text text-muted">Defaults to Active if left as shown.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveProgramBtn" onclick="saveProgramFromModal('addProgramFormManagement','addProgramFormModal')">Save Program</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editProgramForm">
                    <input type="hidden" id="edit_program_modal_program_id" name="program_id">
                    <input type="hidden" id="edit_program_modal_dept_id" name="dept_id" value="<?= $currentDeptId ?>">
                    <p class="text-muted small mb-0">Required fields are marked with <span class="text-danger">*</span>. Other fields are optional.</p>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="edit_program_code" class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_program_code" name="program_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_program_name" class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_program_name" name="program_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_effective_academic_year" class="form-label">Effective Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_effective_academic_year" name="effective_academic_year" required>
                                <option value="">Select Academic Year</option>
                                <?php
                                $currentYear = date('Y');
                                $startYear = $currentYear + 5;
                                $endYear = $currentYear - 10;
                                for ($year = $startYear; $year >= $endYear; $year--) {
                                    $academicYear = $year . '-' . ($year + 1);
                                    echo "<option value=\"$academicYear\">$academicYear</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_program_type" class="form-label">Program Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_program_type" name="program_type" required>
                                <option value="">Select Program Type</option>
                                <option value="BS">BS (Bachelor of Science)</option>
                                <option value="BTVTED">BTVTED (Bachelor of Technical-Vocational Teacher Education)</option>
                                <option value="BEED">BEED (Bachelor of Elementary Education)</option>
                                <option value="BSED">BSED (Bachelor of Secondary Education)</option>
                                <option value="BSPED">BSPED (Bachelor of Special Needs Education)</option>
                                <option value="BPE">BPE (Bachelor of Physical Education)</option>
                                <option value="BSE">BSE (Bachelor of Science in Engineering)</option>
                                <option value="BSA">BSA (Bachelor of Science in Agriculture)</option>
                                <option value="BSN">BSN (Bachelor of Science in Nursing)</option>
                                <option value="BSBA">BSBA (Bachelor of Science in Business Administration)</option>
                                <option value="BSCrim">BSCrim (Bachelor of Science in Criminology)</option>
                                <option value="BSAcc">BSAcc (Bachelor of Science in Accountancy)</option>
                                <option value="Master's Degree">Master's Degree</option>
                                <option value="Doctorate">Doctorate</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Associate Degree">Associate Degree</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_total_units_required" class="form-label">Total Units Required <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_total_units_required" name="total_units_required" required min="0" max="300" placeholder="e.g., 120">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_major_track" class="form-label">Major / Track <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="edit_major_track" name="major_track" placeholder="e.g., Computer Science, Information Technology" maxlength="100">
                            <small class="form-text text-muted">Leave blank if not applicable.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_program_years" class="form-label">Program Duration (Years) <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_program_years" name="program_years" required>
                                <option value="">Select Years</option>
                                <option value="2">2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                                <option value="5">5 Years</option>
                                <option value="6">6 Years</option>
                            </select>
                            <small class="form-text text-muted">Select how many years this program takes to complete</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_program_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_program_status" name="program_status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <small class="form-text text-muted">Inactive hides the program from normal selection lists.</small>
                        </div>
                        <div class="col-12">
                            <label for="edit_program_desc" class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control" id="edit_program_desc" name="program_desc" rows="3" placeholder="Short notes about this program (if any)"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProgramEdits()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- View Program Details Modal -->
<div class="modal fade" id="viewProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>View Program Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Program Code</label>
                        <p class="fw-bold fs-5" id="view_program_code">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Program Name</label>
                        <p class="fw-bold fs-5" id="view_program_name">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Effective Academic Year</label>
                        <p id="view_effective_academic_year">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Program Type</label>
                        <p class="fw-bold" id="view_program_type">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Total Units Required</label>
                        <p id="view_total_units_required">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Major / Track</label>
                        <p id="view_major_track">-</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Status</label>
                        <p id="view_program_status">-</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Duration</label>
                        <p id="view_program_years">-</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Department</label>
                        <p id="view_dept_name">-</p>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small">Description</label>
                        <p id="view_program_desc" class="text-break fst-italic">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
