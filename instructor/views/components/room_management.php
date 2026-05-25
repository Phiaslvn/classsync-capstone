<?php
/**
 * Instructor Room Availability Component
 * Automatic Room Usage System - View room availability with time-based filtering
 * No request system - rooms are automatically allocated based on schedules and access grants
 */
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Room Availability</h4>
                <p class="text-muted mb-0">View available and occupied rooms based on schedules and access grants</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="loadRoomAvailability()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4" id="roomSummaryCards">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-building fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold" id="totalRoomsCount">0</div>
                                <div class="small">Total Rooms</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold" id="availableRoomsCount">0</div>
                                <div class="small">Available</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-x-circle fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold" id="occupiedRoomsCount">0</div>
                                <div class="small">Occupied</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-funnel me-2"></i>Filters
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="availabilityDateFilter" class="form-label">Date</label>
                        <input type="date" class="form-control" id="availabilityDateFilter" value="<?php echo date('Y-m-d'); ?>" onchange="loadRoomAvailability()">
                    </div>
                    <div class="col-md-3">
                        <label for="availabilityStartTimeFilter" class="form-label">Start Time (Optional)</label>
                        <input type="time" class="form-control" id="availabilityStartTimeFilter" onchange="loadRoomAvailability()">
                    </div>
                    <div class="col-md-3">
                        <label for="availabilityEndTimeFilter" class="form-label">End Time (Optional)</label>
                        <input type="time" class="form-control" id="availabilityEndTimeFilter" onchange="loadRoomAvailability()">
                    </div>
                    <div class="col-md-3">
                        <label for="availabilityRoomTypeFilter" class="form-label">Room Type</label>
                        <select class="form-select" id="availabilityRoomTypeFilter" onchange="loadRoomAvailability()">
                            <option value="">All Types</option>
                            <option value="Lab">Laboratory</option>
                            <option value="Lec">Lecture</option>
                            <option value="Special">Special</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="availabilityDeptFilter" class="form-label">Department</label>
                        <select class="form-select" id="availabilityDeptFilter" onchange="loadRoomAvailability()">
                            <option value="">All Departments</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button class="btn btn-secondary w-100" onclick="clearAvailabilityFilters()">
                                <i class="bi bi-x-circle me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Availability Tabs -->
        <ul class="nav nav-tabs mb-3" id="roomAvailabilityTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-rooms-tab" data-bs-toggle="tab" data-bs-target="#all-rooms" type="button" role="tab">
                    <i class="bi bi-list-ul me-1"></i>All Rooms
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="available-rooms-tab" data-bs-toggle="tab" data-bs-target="#available-rooms" type="button" role="tab">
                    <i class="bi bi-check-circle me-1"></i>Available
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="occupied-rooms-tab" data-bs-toggle="tab" data-bs-target="#occupied-rooms" type="button" role="tab">
                    <i class="bi bi-x-circle me-1"></i>Occupied
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="roomAvailabilityTabContent">
            <!-- All Rooms Tab -->
            <div class="tab-pane fade show active" id="all-rooms" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div id="allRoomsContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading room availability...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Rooms Tab -->
            <div class="tab-pane fade" id="available-rooms" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div id="availableRoomsContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading available rooms...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Occupied Rooms Tab -->
            <div class="tab-pane fade" id="occupied-rooms" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div id="occupiedRoomsContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading occupied rooms...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load departments for filter
function loadDepartments() {
    fetch('../../admin/management/get_departments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.departments) {
                const deptSelect = document.getElementById('availabilityDeptFilter');
                deptSelect.innerHTML = '<option value="">All Departments</option>';
                data.departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.dept_id;
                    option.textContent = dept.dept_name;
                    deptSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading departments:', error);
        });
}

// Load room availability
function loadRoomAvailability() {
    const date = document.getElementById('availabilityDateFilter').value;
    const startTime = document.getElementById('availabilityStartTimeFilter').value;
    const endTime = document.getElementById('availabilityEndTimeFilter').value;
    const roomType = document.getElementById('availabilityRoomTypeFilter').value;
    const deptId = document.getElementById('availabilityDeptFilter').value;

    // Build query string
    const params = new URLSearchParams();
    params.append('date', date);
    if (startTime) params.append('start_time', startTime);
    if (endTime) params.append('end_time', endTime);
    if (roomType) params.append('room_type', roomType);
    if (deptId) params.append('dept_id', deptId);

    // Show loading state
    showLoadingState();

    fetch(`../../instructor/reports/get_room_availability.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                updateRoomAvailability(data.data);
            } else {
                showErrorState(data.message || 'Failed to load room availability');
            }
        })
        .catch(error => {
            console.error('Error loading room availability:', error);
            showErrorState('An error occurred while loading room availability');
        });
}

// Update room availability display
function updateRoomAvailability(data) {
    // Update summary cards
    document.getElementById('totalRoomsCount').textContent = data.summary.total || 0;
    document.getElementById('availableRoomsCount').textContent = data.summary.available || 0;
    document.getElementById('occupiedRoomsCount').textContent = data.summary.occupied || 0;

    // Render all rooms
    renderRooms(data.all_rooms, 'allRoomsContainer');

    // Render available rooms
    renderRooms(data.available_rooms, 'availableRoomsContainer');

    // Render occupied rooms
    renderRooms(data.occupied_rooms, 'occupiedRoomsContainer');
}

// Render rooms in a container
function renderRooms(rooms, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!rooms || rooms.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="mt-3 text-muted">No rooms found</p>
            </div>
        `;
        return;
    }

    let html = '<div class="row g-3">';
    
    rooms.forEach(room => {
        const availabilityBadge = room.is_occupied 
            ? '<span class="badge bg-warning"><i class="bi bi-x-circle me-1"></i>Occupied</span>'
            : '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Available</span>';
        
        const accessBadge = room.access_type === 'Own'
            ? '<span class="badge bg-primary">Own Department</span>'
            : '<span class="badge bg-info">Shared Access</span>';

        const typeColors = {
            'Lab': 'danger',
            'Lec': 'primary',
            'Special': 'secondary'
        };
        const typeColor = typeColors[room.rm_type] || 'secondary';
        const typeBadge = `<span class="badge bg-${typeColor}">${room.rm_type}</span>`;

        // Build current occupancy info
        let occupancyInfo = '';
        if (room.is_occupied && room.current_occupancy) {
            const occ = room.current_occupancy;
            occupancyInfo = `
                <div class="alert alert-warning mb-0 mt-2">
                    <strong><i class="bi bi-clock me-1"></i>Currently Occupied:</strong><br>
                    <small>
                        ${occ.subj_code || 'N/A'} - ${occ.sec_name || ''}<br>
                        ${occ.instructor_name || 'N/A'}<br>
                        ${formatTime(occ.schd_start)} - ${formatTime(occ.schd_end)}
                    </small>
                </div>
            `;
        }

        // Build schedules list
        let schedulesHtml = '';
        if (room.schedules && room.schedules.length > 0) {
            schedulesHtml = '<div class="mt-2"><strong>Occupied Schedules:</strong><ul class="list-unstyled mb-0">';
            room.schedules.forEach(schedule => {
                schedulesHtml += `
                    <li class="small">
                        <i class="bi bi-clock me-1"></i>
                        ${formatTime(schedule.schd_start)} - ${formatTime(schedule.schd_end)}: 
                        ${schedule.subj_code || 'N/A'} (${schedule.sec_name || ''}) - ${schedule.instructor_name || 'N/A'}
                    </li>
                `;
            });
            schedulesHtml += '</ul></div>';
        }
        
        // Build available time slots list
        let availableSlotsHtml = '';
        if (room.available_slots && room.available_slots.length > 0) {
            availableSlotsHtml = '<div class="mt-2"><strong class="text-success"><i class="bi bi-check-circle me-1"></i>Available Time Slots:</strong><ul class="list-unstyled mb-0">';
            room.available_slots.forEach(slot => {
                const duration = calculateDuration(slot.start, slot.end);
                availableSlotsHtml += `
                    <li class="small text-success">
                        <i class="bi bi-clock me-1"></i>
                        <strong>${slot.start_formatted || formatTime(slot.start)} - ${slot.end_formatted || formatTime(slot.end)}</strong>
                        <span class="text-muted">(${duration})</span>
                    </li>
                `;
            });
            availableSlotsHtml += '</ul></div>';
        } else if (!room.is_occupied && (!room.schedules || room.schedules.length === 0)) {
            availableSlotsHtml = '<div class="mt-2"><strong class="text-success"><i class="bi bi-check-circle me-1"></i>Available:</strong> <span class="text-success">Full day (7:00 AM - 8:30 PM)</span></div>';
        }

        html += `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 ${room.is_occupied ? 'border-warning' : 'border-success'}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">${escapeHtml(room.rm_name)}</h6>
                            <small class="text-muted">${escapeHtml(room.building_name)}</small>
                        </div>
                        <div>
                            ${availabilityBadge}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            ${typeBadge} ${accessBadge}
                        </div>
                        <div class="mb-2">
                            <strong>Department:</strong> ${escapeHtml(room.dept_name || 'N/A')}<br>
                            <strong>Capacity:</strong> ${room.rm_capacity} seats<br>
                            ${room.rm_features ? `<strong>Features:</strong> ${escapeHtml(room.rm_features)}<br>` : ''}
                        </div>
                        ${occupancyInfo}
                        ${schedulesHtml}
                        ${availableSlotsHtml}
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

// Show loading state
function showLoadingState() {
    const containers = ['allRoomsContainer', 'availableRoomsContainer', 'occupiedRoomsContainer'];
    containers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading room availability...</p>
                </div>
            `;
        }
    });
}

// Show error state
function showErrorState(message) {
    const containers = ['allRoomsContainer', 'availableRoomsContainer', 'occupiedRoomsContainer'];
    containers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(message)}
                </div>
            `;
        }
    });
}

// Clear filters
function clearAvailabilityFilters() {
    document.getElementById('availabilityDateFilter').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('availabilityStartTimeFilter').value = '';
    document.getElementById('availabilityEndTimeFilter').value = '';
    document.getElementById('availabilityRoomTypeFilter').value = '';
    document.getElementById('availabilityDeptFilter').value = '';
    loadRoomAvailability();
}

// Format time to 12-hour format
function formatTime(time) {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

// Calculate duration between two times
function calculateDuration(start, end) {
    if (!start || !end) return '';
    const startTime = new Date('2000-01-01T' + start);
    const endTime = new Date('2000-01-01T' + end);
    const diffMs = endTime - startTime;
    const diffHours = diffMs / (1000 * 60 * 60);
    if (diffHours < 1) {
        return Math.round(diffHours * 60) + ' min';
    }
    return diffHours.toFixed(1) + ' hr' + (diffHours !== 1 ? 's' : '');
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize when component loads
document.addEventListener('DOMContentLoaded', function() {
    loadDepartments();
    loadRoomAvailability();
});
</script>
