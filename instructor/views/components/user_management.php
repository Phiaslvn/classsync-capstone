<?php
/**
 * User Management Component
 * User listing, editing, and management
 */
?>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">User Management</h2>
        <?php if ($dashboardController->hasPermission('manage_users')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="bi bi-person-plus me-1"></i>Add User
        </button>
        <?php endif; ?>
    </div>

    <!-- User Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="totalUsersCount">-</div>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="moderatorsCount">-</div>
                    <small class="text-muted">Moderators</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="instructorsCount">-</div>
                    <small class="text-muted">Instructors</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="activeUsersCount">-</div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="pendingUsersCount">-</div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h5 mb-1" id="regularUsersCount">-</div>
                    <small class="text-muted">Regular</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="accountsSearch" placeholder="Search users...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="accountsSort">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name_asc">Name A-Z</option>
                        <option value="name_desc">Name Z-A</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="accountsStatusFilter">
                        <option value="">All Status</option>
                        <option value="Regular">Regular</option>
                        <option value="Part-Time">Part-Time</option>
                        <option value="Contractual">Contractual</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="accountsRoleFilter">
                        <option value="">All Roles</option>
                        <option value="3">Moderator</option>
                        <option value="4">Instructor</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="accountsRankFilter">
                        <option value="">All Ranks</option>
                        <option value="Professor">Professor</option>
                        <option value="Associate Professor">Associate Professor</option>
                        <option value="Assistant Professor">Assistant Professor</option>
                        <option value="Instructor">Instructor</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" onclick="refreshUsersData()">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Grid -->
    <div id="usersLoadingState" style="display: none;">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading users...</p>
        </div>
    </div>

    <div id="usersEmptyState" style="display: none;">
        <div class="text-center py-5">
            <i class="bi bi-people fs-1 text-muted"></i>
            <p class="mt-3 text-muted">No users found</p>
        </div>
    </div>

    <div id="usersTableContainer">
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Instructor Status</th>
                        <th>Rank</th>
                        <th>Designation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Users will be loaded here via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAccountModalLabel">Edit Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAccountForm">
                    <input type="hidden" name="acc_id" id="edit_acc_id">
                    
                    <!-- Account Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Account Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_fname" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_fname" name="fname" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_lname" name="lname" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_minitial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="edit_minitial" name="minitial" maxlength="10">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_acc_user" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_acc_user" name="acc_user" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_acc_email" class="form-label">Account Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_acc_email" name="acc_email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_dept_id" class="form-label">Department</label>
                                <select class="form-select" id="edit_dept_id" name="dept_id">
                                    <option value="">Select Department</option>
                                    <option value="1">Computer Studies</option>
                                    <option value="2">Industrial Technology</option>
                                    <option value="3">Teacher Education</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Instructor Information Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">Instructor Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_inst_status" class="form-label">Employment Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_inst_status" name="inst_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Part-Time">Part-Time</option>
                                    <option value="Contractual">Contractual</option>
                                    <option value="Coordinator">Coordinator</option>
                                    <option value="DepartmentHead">Department Head</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_rank" class="form-label">Academic Rank</label>
                                <select class="form-select" id="edit_rank" name="rank">
                                    <option value="">Select Rank</option>
                                    <option value="University Professor">University Professor</option>
                                    <option value="Professor I-VI">Professor I-VI</option>
                                    <option value="Associate Professor I-V">Associate Professor I-V</option>
                                    <option value="Assistant Professor I-IV">Assistant Professor I-IV</option>
                                    <option value="Instructor I-III">Instructor I-III</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_designation" class="form-label">Designation</label>
                                <select class="form-select" id="edit_designation" name="designation">
                                    <option value="None">None</option>
                                    <option value="Vice President">Vice President</option>
                                    <option value="Campus Director">Campus Director</option>
                                    <option value="Dean">Dean</option>
                                    <option value="Director">Director</option>
                                    <option value="Head">Head</option>
                                    <option value="Chairperson/Coordinator/As Officer in Faculty Association">Chairperson/Coordinator/As Officer in Faculty Association</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_inst_email" class="form-label">Instructor Email</label>
                                <input type="email" class="form-control" id="edit_inst_email" name="inst_email">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_inst_phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="edit_inst_phone" name="inst_phone" maxlength="20">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAccountEditsBtn" onclick="saveAccountEdits()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

