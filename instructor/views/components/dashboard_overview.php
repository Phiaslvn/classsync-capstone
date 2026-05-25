<?php
/**
 * Instructor Dashboard Overview Component
 * Displays statistics and quick actions for instructors
 */
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Dashboard Overview</h4>
                <p class="text-muted mb-0">Welcome to your instructor dashboard</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
                <button class="btn btn-primary btn-sm" onclick="showContent('schedule', this)">
                    <i class="bi bi-calendar-week me-1"></i>My Schedules
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4" id="statsContainer">
            <!-- Stats will be loaded dynamically -->
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading dashboard data...</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- My Schedules -->
                            <div class="col-md-6 col-lg-4">
                                <div class="d-grid">
                                    <button class="btn btn-outline-primary btn-lg h-100" onclick="showContent('schedule', this)">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="bi bi-calendar-week fs-1 me-3"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">My Schedules</div>
                                                <small class="text-muted">View and manage your class schedules</small>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Subjects -->
                            <div class="col-md-6 col-lg-4">
                                <div class="d-grid">
                                    <button class="btn btn-outline-success btn-lg h-100" onclick="showContent('curriculum', this)">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="bi bi-book fs-1 me-3"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">Subjects</div>
                                                <small class="text-muted">View assigned subjects and curriculum</small>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Room Requests -->
                            <div class="col-md-6 col-lg-4">
                                <div class="d-grid">
                                    <button class="btn btn-outline-warning btn-lg h-100" onclick="showContent('room_requests', this)">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="bi bi-building fs-1 me-3"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">Room Requests</div>
                                                <small class="text-muted">Request rooms for your classes</small>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Reports -->
                            <div class="col-md-6 col-lg-4">
                                <div class="d-grid">
                                    <button class="btn btn-outline-info btn-lg h-100" onclick="showContent('reports', this)">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="bi bi-file-earmark-text fs-1 me-3"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">Reports</div>
                                                <small class="text-muted">View academic reports and analytics</small>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Profile -->
                            <div class="col-md-6 col-lg-4">
                                <div class="d-grid">
                                    <button class="btn btn-outline-secondary btn-lg h-100" onclick="showContent('profile', this)">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="bi bi-person-gear fs-1 me-3"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">Profile</div>
                                                <small class="text-muted">Manage your account settings</small>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to update dashboard statistics
function updateDashboardUI(data) {
    console.log('Updating dashboard UI with data:', data);
    
    if (data.success && data.data) {
        const stats = data.data;
        const statsContainer = document.getElementById('statsContainer');
        
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fs-4 fw-bold">${stats.users?.total_users || 0}</div>
                                    <div class="small">Total Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-person-check fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fs-4 fw-bold">${stats.instructors?.total_instructors || 0}</div>
                                    <div class="small">Instructors</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-calendar-week fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fs-4 fw-bold">${stats.schedules?.total_schedules || 0}</div>
                                    <div class="small">Schedules</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-building fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fs-4 fw-bold">${stats.rooms?.total_rooms || 0}</div>
                                    <div class="small">Rooms</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    } else {
        // Show error state
        const statsContainer = document.getElementById('statsContainer');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Unable to load dashboard statistics. Please try refreshing the page.
                    </div>
                </div>
            `;
        }
    }
}

// Function to refresh dashboard
function refreshDashboard() {
    console.log('Refreshing dashboard...');
    loadDashboardData();
}
</script>