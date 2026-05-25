<?php
/**
 * Room Management Component
 * Room management and room request handling
 */

// Ensure security middleware is available (in case it's not included in parent)
if (!function_exists('isAdminSupport')) {
    require_once __DIR__ . '/../../../includes/auth/security_middleware.php';
}

// Get user info for department and admin status
$userInfo = getUserInfo();
$isAdminSupport = isAdminSupport();

// Check permissions for room management actions
$canManageRooms = hasPermission('manage_rooms');
$canViewRooms = hasPermission('view_rooms');
$canApproveRoomRequests = hasPermission('approve_room_requests');
?>
<script>
// Make permissions available to JavaScript
window.canApproveRoomRequests = <?php echo $canApproveRoomRequests ? 'true' : 'false'; ?>;
window.canManageRooms = <?php echo $canManageRooms ? 'true' : 'false'; ?>;
</script>

<div class="dashboard-card shadow-sm room-management-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="flex-grow-1" style="min-width: 220px;">
            <h4 class="h4 mb-1 fw-bold">Rooms &amp; spaces</h4>
            <p class="text-muted small mb-2">Buildings, individual rooms, and booking/change requests for your department.</p>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs nav-pills-custom" id="roomManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button
                class="nav-link active"
                id="buildings-tab"
                data-bs-toggle="tab"
                data-bs-target="#buildings"
                type="button"
                role="tab"
                aria-controls="buildings"
                aria-selected="true"
            >
                <i class="bi bi-buildings me-1"></i> Buildings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                id="rooms-tab"
                data-bs-toggle="tab"
                data-bs-target="#rooms"
                type="button"
                role="tab"
                aria-controls="rooms"
                aria-selected="false"
            >
                <i class="bi bi-door-open me-1"></i> Rooms
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                id="room-requests-tab"
                data-bs-toggle="tab"
                data-bs-target="#room-requests"
                type="button"
                role="tab"
                aria-controls="room-requests"
                aria-selected="false"
            >
                <i class="bi bi-calendar-check me-1"></i> Room Requests
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content pt-4" id="roomManagementTabsContent">
        <!-- Buildings Tab -->
        <div class="tab-pane fade show active" id="buildings" role="tabpanel" aria-labelledby="buildings-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">Manage Buildings</h5>
                <?php if ($canManageRooms): ?>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBuildingModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Building
                </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table id="buildingsTable" class="table table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Building Name / Description</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Rooms Tab -->
        <div class="tab-pane fade" id="rooms" role="tabpanel" aria-labelledby="rooms-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">Manage Rooms</h5>
                <?php if ($canManageRooms): ?>
                <button
                    class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#addRoomModal"
                    id="addRoomBtn"
                    disabled
                >
                    <i class="bi bi-plus-circle me-1"></i> Add Room
                </button>
                <?php endif; ?>
            </div>

            <!-- Building Selector -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="buildingSelector" class="form-label fw-semibold">Select a Building:</label>
                    <select id="buildingSelector" class="form-select">
                        <option value="" selected disabled>Please select a building first...</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="roomsTable" class="table table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Room Name</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Room Requests Tab -->
        <div class="tab-pane fade" id="room-requests" role="tabpanel" aria-labelledby="room-requests-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">Room Requests</h5>
                <button class="btn btn-outline-primary btn-sm" onclick="loadRoomRequests()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
            
            <div class="mb-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select class="form-select form-select-sm" id="roomRequestStatusFilter">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Declined">Declined</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Room</label>
                        <select class="form-select form-select-sm" id="roomRequestRoomFilter">
                            <option value="">All Rooms</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Search</label>
                        <input type="text" class="form-control form-control-sm" id="roomRequestSearch" placeholder="Search by requester, room, or comment...">
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="roomRequestsTable" class="table table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Request Date</th>
                            <th>Room</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Requester</th>
                            <th>Department</th>
                            <th>Purpose/Comments</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            
            <!-- Archive Requests Section -->
            <div class="mt-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold">Archive Requests</h5>
                    <button class="btn btn-outline-primary btn-sm" onclick="loadArchivedRoomRequests()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="archivedRoomRequestsTable" class="table table-hover" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>Request Date</th>
                                <th>Room</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Requester</th>
                                <th>Department</th>
                                <th>Purpose/Comments</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Building Modal -->
<div class="modal fade" id="addBuildingModal" tabindex="-1" aria-labelledby="addBuildingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBuildingModalLabel">Add New Building</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <?php if (!$canManageRooms): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>View-Only Access:</strong> You have view-only access to rooms. Creating or editing buildings is not permitted.
                </div>
                <?php endif; ?>
                <form id="addBuildingForm"<?php if (!$canManageRooms): ?> class="form-disabled"<?php endif; ?>>
                    <div class="mb-3">
                        <label for="add_bd_desc" class="form-label">Building Name / Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_bd_desc" name="bd_desc" required<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                    </div>

                    <div class="mb-3">
                        <label for="add_bd_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_bd_status" name="bd_status" required<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                            <option value="Used" selected>Used</option>
                            <option value="Unused">Unused</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($canManageRooms): ?>
                <button type="button" class="btn btn-primary" onclick="addBuilding()">Add Building</button>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Access Denied:</strong> You do not have permission to manage rooms.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="addSectionForm">
                    <input type="hidden" id="add_sec_class_id" name="class_id">

                    <div class="mb-3">
                        <label for="add_sec_num" class="form-label">Section Number <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="add_sec_num" name="sec_num" required min="1">
                        <div class="form-text">e.g., 1, 2, 3...</div>
                    </div>

                    <div class="mb-3">
                        <label for="add_sec_name" class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_sec_name" name="sec_name" required placeholder="Enter section name (e.g., A, B, C)">
                        <div class="form-text">e.g., BSIT 1-A, BSCS 2-B...</div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="addSection()">Add Section</button>
            </div>
        </div>
    </div>
</div>

<script>
// Modal handling for Add Section Modal - Set class_id when modal is shown
document.addEventListener('DOMContentLoaded', function() {
    const addSectionModal = document.getElementById('addSectionModal');
    
    if (addSectionModal) {
        // Only listen for the show event to set the class_id
        // Bootstrap handles the modal toggling via data-bs-toggle attribute
        addSectionModal.addEventListener('show.bs.modal', function() {
            const classSelector = document.getElementById('classSelector');
            const classIdInput = document.getElementById('add_sec_class_id');
            if (classSelector && classIdInput) {
                classIdInput.value = classSelector.value || '';
            }
        });
        
        // Reset form when modal is hidden
        addSectionModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('addSectionForm');
            if (form) {
                form.reset();
            }
        });
    }
});
</script>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="editSectionForm">
                    <input type="hidden" id="edit_sec_id" name="sec_id">

                    <div class="mb-3">
                        <label for="edit_sec_num" class="form-label">Section Number <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_sec_num" name="sec_num" required min="1">
                    </div>

                    <div class="mb-3">
                        <label for="edit_sec_name" class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_sec_name" name="sec_name" required>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateSection()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClassModalLabel">Add New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="addClassForm">
                    <div class="mb-3">
                        <label for="add_class_sy" class="form-label">School Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_class_sy" name="sy_id" required></select>
                    </div>

                    <div class="mb-3">
                        <label for="add_class_curr" class="form-label">Curriculum <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_class_curr" name="curr_id" required></select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_class_lvl" class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_class_lvl" name="class_lvl" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="add_class_term" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_class_term" name="class_term" required>
                                <option value="1">1st Term</option>
                                <option value="2">2nd Term</option>
                                <option value="3">Summer</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="add_class_secno" class="form-label">Number of Sections <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="add_class_secno" name="class_secno" required min="1" value="1">
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="addClass()">Add Class</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editClassModalLabel">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="editClassForm">
                    <input type="hidden" id="edit_class_id" name="class_id">

                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <p class="form-control-plaintext" id="edit_class_sy_text"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Curriculum</label>
                        <p class="form-control-plaintext" id="edit_class_curr_text"></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year Level</label>
                            <p class="form-control-plaintext" id="edit_class_lvl_text"></p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term</label>
                            <p class="form-control-plaintext" id="edit_class_term_text"></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_class_secno" class="form-label">Number of Sections <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_class_secno" name="class_secno" required min="1">
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateClass()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoomModalLabel">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <?php if (!$canManageRooms): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>View-Only Access:</strong> You have view-only access to rooms. Creating or editing rooms is not permitted.
                </div>
                <?php endif; ?>
                <form id="addRoomForm"<?php if (!$canManageRooms): ?> class="form-disabled"<?php endif; ?>>
                    <div class="mb-3">
                        <label for="add_rm_bd_id" class="form-label">Building <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_rm_bd_id" name="bd_id" required<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                            <option value="">Select a building...</option>
                        </select>
                        <small class="form-text text-muted">Select the building where this room is located.</small>
                    </div>

                    <div class="mb-3">
                        <label for="add_rm_name" class="form-label">Room Name / Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_rm_name" name="rm_name" required<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_rm_type" class="form-label">Room Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_rm_type" name="rm_type" required<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                                <option value="Lec" selected>Lecture</option>
                                <option value="Lab">Laboratory</option>
                                <option value="Special">Special</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="add_rm_capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="add_rm_capacity" name="rm_capacity" required min="1"<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="add_rm_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_rm_status" name="rm_status" required<?php if (!$canManageRooms): ?> disabled<?php endif; ?>>
                            <option value="Used" selected>Used</option>
                            <option value="Unused">Unused</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="add_rm_features" class="form-label">Features (Optional)</label>
                        <textarea class="form-control" id="add_rm_features" name="rm_features" rows="2" placeholder="e.g., Air-conditioned, Projector available"<?php if (!$canManageRooms): ?> disabled<?php endif; ?>></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($canManageRooms): ?>
                <button type="button" class="btn btn-primary" onclick="addRoom()">Add Room</button>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Access Denied:</strong> You do not have permission to manage rooms.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRoomModalLabel">Edit Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="editRoomForm">
                    <input type="hidden" id="edit_rm_id" name="rm_id">

                    <div class="mb-3">
                        <label for="edit_rm_name" class="form-label">Room Name / Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_rm_name" name="rm_name" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_rm_type" class="form-label">Room Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_rm_type" name="rm_type" required>
                                <option value="Lec">Lecture</option>
                                <option value="Lab">Laboratory</option>
                                <option value="Special">Special</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="edit_rm_capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_rm_capacity" name="rm_capacity" required min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_rm_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_rm_status" name="rm_status" required>
                            <option value="Used">Used</option>
                            <option value="Unused">Unused</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_rm_features" class="form-label">Features (Optional)</label>
                        <textarea class="form-control" id="edit_rm_features" name="rm_features" rows="2"></textarea>
                    </div>
                    
                    <?php
                    // Only show department selector for Admin Support
                    if ($isAdminSupport):
                    ?>
                    <div class="mb-3">
                        <label for="edit_rm_dept_id" class="form-label">Department</label>
                        <select class="form-select" id="edit_rm_dept_id" name="dept_id">
                            <option value="">No Department (Shared)</option>
                            <!-- Will be populated dynamically -->
                        </select>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateRoom()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Room Access Management Modal -->
<div class="modal fade" id="roomAccessModal" tabindex="-1" aria-labelledby="roomAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomAccessModalLabel">Manage Room Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="roomAccessContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Room Schedule View Modal - REMOVED: Duplicate of modal in dashboard_overview.php -->
<!-- The modal is defined in dashboard_overview.php to avoid duplicate IDs -->

<!-- Room Request Modal -->
<div class="modal fade" id="roomRequestModal" tabindex="-1" aria-labelledby="roomRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomRequestModalLabel">Request To Use Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="roomRequestForm">
                    <input type="hidden" id="request_rm_id" name="rm_id">
                    <input type="hidden" id="request_rm_name" name="rm_name">
                    
                    <div class="mb-3">
                        <label class="form-label">Room</label>
                        <input type="text" class="form-control" id="request_room_display" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="request_day" class="form-label">Day <span class="text-danger">*</span></label>
                            <select class="form-select" id="request_day" name="day" required>
                                <option value="">Select Day</option>
                                <option value="Mon">Monday</option>
                                <option value="Tue">Tuesday</option>
                                <option value="Wed">Wednesday</option>
                                <option value="Thu">Thursday</option>
                                <option value="Fri">Friday</option>
                                <option value="Sat">Saturday</option>
                                <option value="Sun">Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="request_time_slot" class="form-label">Available Time Slot <span class="text-danger">*</span></label>
                            <select class="form-select" id="request_time_slot" name="time_slot" required>
                                <option value="">Select Time Slot</option>
                            </select>
                            <small class="form-text text-muted">Select an available time slot to see the time range</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="request_start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="request_start_time" name="start_time" required>
                            <small class="form-text text-muted">Select start time within the available slot</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="request_end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="request_end_time" name="end_time" required>
                            <small class="form-text text-muted">Select end time within the available slot</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="request_duration" class="form-label">Duration <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="request_duration" name="duration" min="0.5" max="8" step="0.5" value="1" readonly>
                                <span class="input-group-text">hours</span>
                            </div>
                            <small class="form-text text-muted">Automatically calculated from start and end time</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="alert alert-info mb-0" id="time_range_info" style="display: none;">
                                <small><i class="bi bi-info-circle me-1"></i><span id="time_range_text">Available time range will appear here</span></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="request_comment" class="form-label">Purpose/Comments</label>
                        <textarea class="form-control" id="request_comment" name="comment" rows="3" placeholder="Please specify the purpose of using this room..."></textarea>
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

<!-- Edit Building Modal -->
<div class="modal fade" id="editBuildingModal" tabindex="-1" aria-labelledby="editBuildingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBuildingModalLabel">Edit Building</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="editBuildingForm">
                    <input type="hidden" id="edit_bd_id" name="bd_id">

                    <div class="mb-3">
                        <label for="edit_bd_desc" class="form-label">Building Name / Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_bd_desc" name="bd_desc" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_bd_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_bd_status" name="bd_status" required>
                            <option value="Used">Used</option>
                            <option value="Unused">Unused</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateBuilding()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Form Disabled State - Used when user doesn't have permission */
.form-disabled {
    pointer-events: none;
    opacity: 0.6;
}

.form-disabled input,
.form-disabled select,
.form-disabled textarea {
    cursor: not-allowed;
    background-color: #f5f5f5;
}

/* Room Schedule Calendar Grid Styles */
#roomScheduleModal .calendar-container {
    position: relative;
    margin-top: 1rem;
}

#roomScheduleModal .calendar-grid {
    display: flex;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow-x: auto;
    overflow-y: auto;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
    max-height: 55vh;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    align-items: flex-start;
}

#roomScheduleModal .time-column {
    width: 100px;
    flex-shrink: 0;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
    border-right: 2px solid #e0e0e0;
    position: sticky;
    left: 0;
    z-index: 10;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

#roomScheduleModal .time-slot {
    height: 60px;
    min-height: 60px;
    max-height: 60px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #495057;
    border-bottom: 1px solid #e9ecef;
    position: relative;
    margin: 0;
    box-sizing: border-box;
    flex-shrink: 0;
}

#roomScheduleModal .time-slot::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 30px;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, #d0d7de 50%, transparent 100%);
    border-top: 1px dashed #d0d7de;
}

#roomScheduleModal .time-slot-half {
    position: absolute;
    top: 32px;
    font-size: 0.65rem;
    color: #868e96;
    font-weight: 400;
}

#roomScheduleModal .days-wrapper {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    flex-grow: 1;
    background-color: #ffffff;
}

#roomScheduleModal .day-column {
    position: relative;
    border-right: 1px solid #e9ecef;
    background-color: #ffffff;
}

#roomScheduleModal .day-column:last-child {
    border-right: none;
}

#roomScheduleModal .day-column-content {
    position: relative;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 59px,
        #f8f9fa 59px,
        #f8f9fa 60px
    );
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

#roomScheduleModal .calendar-header {
    text-align: center;
    padding: 1rem 0.5rem;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    background: linear-gradient(135deg, #800000 0%, #990000 100%);
    color: #ffffff;
    border-bottom: 2px solid #660000;
    position: sticky;
    top: 0;
    z-index: 5;
    text-transform: uppercase;
    margin: 0;
    box-sizing: border-box;
    flex-shrink: 0;
}

#roomScheduleModal .schedule-block {
    position: absolute;
    left: 8px;
    right: 8px;
    background: #800000;
    color: #ffffff;
    border-radius: 0 8px 8px 0;
    padding: 0;
    font-size: 0.75rem;
    overflow: hidden;
    cursor: pointer;
    border-left: 5px solid #660000;
    border-top: 1px solid rgba(255, 255, 255, 0.3);
    z-index: 2;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3), 0 2px 6px rgba(0, 0, 0, 0.2);
    min-height: 60px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#roomScheduleModal .schedule-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4), 0 4px 10px rgba(0, 0, 0, 0.3);
}

#roomScheduleModal .schedule-block-content {
    padding: 8px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
</style>
