<?php
/**
 * Instructor Schedule Management Component
 * Allows instructors to view and manage their class schedules
 */
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">My Schedules</h4>
                <p class="text-muted mb-0">View and manage your class schedules</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshSchedules()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
                <button class="btn btn-primary btn-sm" onclick="requestRoom()">
                    <i class="bi bi-building me-1"></i>Request Room
                </button>
            </div>
        </div>

        <!-- Schedule Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="scheduleFilter" class="form-label">Filter by Status</label>
                        <select class="form-select" id="scheduleFilter" onchange="filterSchedules()">
                            <option value="">All Schedules</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="typeFilter" class="form-label">Filter by Type</label>
                        <select class="form-select" id="typeFilter" onchange="filterSchedules()">
                            <option value="">All Types</option>
                            <option value="Lab">Laboratory</option>
                            <option value="Lec">Lecture</option>
                            <option value="Special">Special</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="dayFilter" class="form-label">Filter by Day</label>
                        <select class="form-select" id="dayFilter" onchange="filterSchedules()">
                            <option value="">All Days</option>
                            <option value="Mon">Monday</option>
                            <option value="Tue">Tuesday</option>
                            <option value="Wed">Wednesday</option>
                            <option value="Thu">Thursday</option>
                            <option value="Fri">Friday</option>
                            <option value="Sat">Saturday</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="searchInput" class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="searchInput" 
                               placeholder="Search schedules..."
                               onkeyup="searchSchedules()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedules Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-week me-2"></i>Class Schedules
                </h5>
            </div>
            <div class="card-body">
                <div id="schedulesContainer">
                    <!-- Loading state -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading your schedules...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Room Request Modal -->
<div class="modal fade" id="roomRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-building me-2"></i>Request Room
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="roomRequestForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="requestDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="requestDate" name="requestDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="requestTime" class="form-label">Time</label>
                            <input type="time" class="form-control" id="requestTime" name="requestTime" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="roomType" class="form-label">Room Type</label>
                            <select class="form-select" id="roomType" name="roomType" required>
                                <option value="">Select Room Type</option>
                                <option value="Lab">Laboratory</option>
                                <option value="Lec">Lecture</option>
                                <option value="Special">Special</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="duration" class="form-label">Duration (hours)</label>
                            <input type="number" class="form-control" id="duration" name="duration" min="1" max="8" value="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requestComment" class="form-label">Comments</label>
                        <textarea class="form-control" id="requestComment" name="requestComment" rows="3" placeholder="Any special requirements or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRoomRequest()">
                    <i class="bi bi-send me-1"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load schedules
function loadSchedules() {
    console.log('Loading schedules...');
    
    // Simulate loading schedules (replace with actual API call)
    setTimeout(() => {
        const schedulesContainer = document.getElementById('schedulesContainer');
        if (schedulesContainer) {
            schedulesContainer.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="fw-bold">Programming Fundamentals</div>
                                    <small class="text-muted">CS101</small>
                                </td>
                                <td>Monday</td>
                                <td>08:00 - 10:00</td>
                                <td>Lab 1</td>
                                <td><span class="badge bg-info">Laboratory</span></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSchedule(1)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="fw-bold">Data Structures</div>
                                    <small class="text-muted">CS201</small>
                                </td>
                                <td>Wednesday</td>
                                <td>10:00 - 12:00</td>
                                <td>Room 201</td>
                                <td><span class="badge bg-primary">Lecture</span></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSchedule(2)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="fw-bold">Database Systems</div>
                                    <small class="text-muted">CS301</small>
                                </td>
                                <td>Friday</td>
                                <td>14:00 - 16:00</td>
                                <td>Lab 2</td>
                                <td><span class="badge bg-info">Laboratory</span></td>
                                <td><span class="badge bg-warning">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSchedule(3)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
        }
    }, 1000);
}

// Function to refresh schedules
function refreshSchedules() {
    console.log('Refreshing schedules...');
    loadSchedules();
}

// Function to filter schedules
function filterSchedules() {
    console.log('Filtering schedules...');
    // Implement filtering logic
}

// Function to search schedules
function searchSchedules() {
    console.log('Searching schedules...');
    // Implement search logic
}

// Function to request room
function requestRoom() {
    const modal = new bootstrap.Modal(document.getElementById('roomRequestModal'));
    modal.show();
}

// Function to submit room request
function submitRoomRequest() {
    const form = document.getElementById('roomRequestForm');
    const formData = new FormData(form);
    
    // Show loading
    Swal.fire({
        title: 'Submitting Request...',
        text: 'Please wait while we process your room request.',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Simulate API call
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Request Submitted!',
            text: 'Your room request has been submitted successfully.',
            confirmButtonColor: '#800000',
            confirmButtonText: 'OK'
        }).then(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('roomRequestModal'));
            modal.hide();
            form.reset();
        });
    }, 2000);
}

// Function to view schedule details
function viewSchedule(scheduleId) {
    Swal.fire({
        title: 'Schedule Details',
        text: `Viewing details for schedule ID: ${scheduleId}`,
        icon: 'info',
        confirmButtonColor: '#800000',
        confirmButtonText: 'OK'
    });
}

// Initialize schedules when component loads
document.addEventListener('DOMContentLoaded', function() {
    loadSchedules();
});
</script>