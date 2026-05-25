<?php
/**
 * Subject Management Component
 * Subject listing and management for moderators
 */
?>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">Subject Management</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="loadSubjects()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Subject Statistics -->
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
                    <div class="h5 mb-1" id="activeSubjectsCount">-</div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="lectureSubjectsCount">-</div>
                    <small class="text-muted">Lecture</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="labSubjectsCount">-</div>
                    <small class="text-muted">Laboratory</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <select class="form-select" id="subjectTypeFilter" onchange="loadSubjects()">
                <option value="">All Types</option>
                <option value="Lecture">Lecture</option>
                <option value="Laboratory">Laboratory</option>
                <option value="Lecture-Laboratory">Lecture-Laboratory</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="subjectStatusFilter" onchange="loadSubjects()">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" id="subjectSearchInput" placeholder="Search subjects..." onkeyup="searchSubjects()">
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary w-100" onclick="clearSubjectFilters()">
                <i class="bi bi-x-circle me-1"></i>Clear Filters
            </button>
        </div>
    </div>

    <!-- Subjects Table -->
    <div class="card">
        <div class="card-body">
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
            
            <div id="subjectsTableContainer">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Units</th>
                                <th>Status</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subjectsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-book fs-1 d-block mb-3"></i>
                                        <h5>No subjects found</h5>
                                        <p class="mb-0">Click refresh to load subjects</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Subjects pagination" id="subjectsPagination">
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
