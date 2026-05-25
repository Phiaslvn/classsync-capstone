<?php
/**
 * Course Management Component
 * Comprehensive course/program management interface
 */
?>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">Course & Program Management</h2>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="openAddProgram()">
                <i class="bi bi-plus-circle me-1"></i>Add Program
            </button>

            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
                <i class="bi bi-book me-1"></i>Add Curriculum
            </button>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="bi bi-journal-text me-1"></i>Add Subject
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <div class="h4 mb-1 text-primary" id="totalColleges">-</div>
                    <small class="text-muted">Colleges</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <div class="h4 mb-1 text-success" id="totalDepartments">-</div>
                    <small class="text-muted">Departments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body">
                    <div class="h4 mb-1 text-info" id="totalPrograms">-</div>
                    <small class="text-muted">Programs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <div class="h4 mb-1 text-warning" id="totalSubjects">-</div>
                    <small class="text-muted">Subjects</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <select class="form-select" id="collegeFilter">
                <option value="">All Colleges</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="departmentFilter">
                <option value="">All Departments</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="programFilter">
                <option value="">All Programs</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" id="searchInput" placeholder="Search courses, programs...">
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="courseTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button" role="tab">
                <i class="bi bi-mortarboard me-1"></i>Programs
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="curriculums-tab" data-bs-toggle="tab" data-bs-target="#curriculums" type="button" role="tab">
                <i class="bi bi-book me-1"></i>Curriculums
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab">
                <i class="bi bi-journal-text me-1"></i>Subjects
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="hierarchy-tab" data-bs-toggle="tab" data-bs-target="#hierarchy" type="button" role="tab">
                <i class="bi bi-diagram-3 me-1"></i>Campus Hierarchy
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content mt-4" id="courseTabContent">
        <!-- Programs Tab -->
        <div class="tab-pane fade show active" id="programs" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover" id="programsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Program Code</th>
                            <th>Program Name</th>
                            <th>Department</th>
                            <th>College</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="programsTableBody">
                        <!-- Programs will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Campus Hierarchy Tab -->
        <div class="tab-pane fade" id="hierarchy" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <div id="campusHierarchy">
                        <!-- Hierarchical view will be loaded here -->
                    </div>
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
                    <div class="row g-3">
                    <input type="hidden" id="dept_id" name="dept_id" value="<?= $currentDeptId ?>">
                        <div class="col-md-6">
                            <label for="program_code" class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="program_code_course" name="program_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="program_name" class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="program_name_course" name="program_name" required>
                        </div>
                        <!-- <div class="col-md-6">
                            <label for="program_dept_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="program_dept_id_course" name="dept_id" required>
                                <option value="">Select Department</option>
                            </select>
                        </div> -->
                        <div class="col-md-6">
                            <label for="program_status" class="form-label">Status</label>
                            <select class="form-select" id="program_status_course" name="program_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="program_desc" class="form-label">Description</label>
                            <textarea class="form-control" id="program_desc_course" name="program_desc" rows="3"></textarea>
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


