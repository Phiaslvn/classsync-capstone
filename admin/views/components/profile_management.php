<?php
/**
 * Profile Management Component
 * User profile editing and management
 */
?>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">Profile Management</h2>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Profile Picture</h6>
                </div>
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img id="profilePicture" 
                             src="<?= htmlspecialchars($profileData['profile_picture']) ?>" 
                             alt="Profile Picture" 
                             class="rounded-circle" 
                             width="120" 
                             height="120" 
                             style="object-fit: cover; border: 3px solid #dee2e6;"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiByeD0iNjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iNjAiIGN5PSI0NSIgcj0iMjAiIGZpbGw9IiM5Y2E5YWEiLz4KPHBhdGggZD0iTTMwIDkwQzMwIDc1LjY0MDYgNDIuNjQwNiA2MyA1NyA2M0g2M0M3Ny4zNTk0IDYzIDkwIDc1LjY0MDYgOTAgOTBWMTAwSDMwVjkwWiIgZmlsbD0iIzljYTlhYSIvPgo8L3N2Zz4K';">
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-sm" onclick="document.getElementById('profileImageInput').click()">
                            <i class="bi bi-camera me-1"></i>Change Picture
                        </button>
                        <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="uploadProfilePicture(this)">
                        <button class="btn btn-outline-danger btn-sm" onclick="removeProfilePicture()">
                            <i class="bi bi-trash me-1"></i>Remove Picture
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Profile Information</h6>
                </div>
                <div class="card-body">
                    <!-- Success/Error Messages -->
                    <div id="profileMessage" class="alert alert-dismissible fade" role="alert" style="display: none;">
                        <span id="profileMessageText"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    
                    <form id="profileForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="fname" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="fname" name="fname" value="<?= htmlspecialchars($userDisplayData['fname'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lname" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="lname" name="lname" value="<?= htmlspecialchars($userDisplayData['lname'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="minitial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="minitial" name="minitial" value="<?= htmlspecialchars($userDisplayData['minitial'] ?? '') ?>" maxlength="1">
                            </div>
                            <div class="col-md-6">
                                <label for="acc_user" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="acc_user" name="acc_user" value="<?= htmlspecialchars($userDisplayData['acc_user'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="acc_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="acc_email" name="acc_email" value="<?= htmlspecialchars($userDisplayData['acc_email'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <hr class="my-3">
                                <h6 class="text-muted mb-3">Change Password (Optional)</h6>
                            </div>
                            <div class="col-12">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <div class="form-text">Required only if you want to change your password</div>
                            </div>
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                <i class="bi bi-check-lg me-1"></i>Update Profile
                            </button>
                            <p class="form-text text-muted small mt-2 mb-0">To change your password, fill the fields above and click <strong>Update Profile</strong>.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
