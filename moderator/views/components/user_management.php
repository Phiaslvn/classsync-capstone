<?php
/**
 * User Management Component
 * User listing and management for moderators
 */
?>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">User Management</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="loadAccounts()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
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
                    <div class="h5 mb-1" id="inactiveUsersCount">-</div>
                    <small class="text-muted">Inactive</small>
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
    </div>

    <!-- Filters -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <select class="form-select" id="roleFilter" onchange="loadAccounts()">
                <option value="">All Roles</option>
                <option value="1">Admin</option>
                <option value="2">Admin Support</option>
                <option value="3">Moderator</option>
                <option value="4">Instructor</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="statusFilter" onchange="loadAccounts()">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Pending">Pending</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" id="searchInput" placeholder="Search users..." onkeyup="searchUsers()">
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                <i class="bi bi-x-circle me-1"></i>Clear Filters
            </button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div id="usersLoadingState" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading users...</p>
            </div>
            
            <div id="usersErrorState" class="text-center py-5" style="display: none;">
                <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                <h5>Error Loading Users</h5>
                <p class="text-muted" id="usersErrorMessage">Failed to load users. Please try again.</p>
                <button class="btn btn-outline-primary" onclick="loadAccounts()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                </button>
            </div>
            
            <div id="usersTableContainer">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-3"></i>
                                        <h5>No users found</h5>
                                        <p class="mb-0">Click refresh to load users</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Users pagination" id="usersPagination">
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
