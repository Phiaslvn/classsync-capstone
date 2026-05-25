<?php
/**
 * Curriculum Management Component
 * Curriculum listing and management for moderators
 */
?>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">Curriculum Management</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="loadCurricula()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Curriculum Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="totalCurriculaCount">-</div>
                    <small class="text-muted">Total Curricula</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="activeCurriculaCount">-</div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="draftCurriculaCount">-</div>
                    <small class="text-muted">Draft</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="totalSubjectsCount">-</div>
                    <small class="text-muted">Total Subjects</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <select class="form-select" id="curriculumStatusFilter" onchange="loadCurricula()">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Draft">Draft</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="curriculumDeptFilter" onchange="loadCurricula()">
                <option value="">All Departments</option>
                <option value="1">Computer Studies</option>
                <option value="2">Industrial Technology</option>
                <option value="3">Teacher Education</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" id="curriculumSearchInput" placeholder="Search curricula..." onkeyup="searchCurricula()">
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary w-100" onclick="clearCurriculumFilters()">
                <i class="bi bi-x-circle me-1"></i>Clear Filters
            </button>
        </div>
    </div>

    <!-- Curricula Table -->
    <div class="card">
        <div class="card-body">
            <div id="curriculaLoadingState" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading curricula...</p>
            </div>
            
            <div id="curriculaErrorState" class="text-center py-5" style="display: none;">
                <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                <h5>Error Loading Curricula</h5>
                <p class="text-muted" id="curriculaErrorMessage">Failed to load curricula. Please try again.</p>
                <button class="btn btn-outline-primary" onclick="loadCurricula()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                </button>
            </div>
            
            <div id="curriculaTableContainer">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Subjects</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="curriculaTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-folder fs-1 d-block mb-3"></i>
                                        <h5>No curricula found</h5>
                                        <p class="mb-0">Click refresh to load curricula</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Curricula pagination" id="curriculaPagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <span class="page-link">Previous</span>
                        </li>
                        <li class="page-item active">
                            <span class="page-link">1</span>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">Next</span>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>
