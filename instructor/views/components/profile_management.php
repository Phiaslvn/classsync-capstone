<?php
/**
 * Instructor Profile Management Component
 * Allows instructors to manage their profile information
 */

// Get current user info
$currentUserInfo = getUserInfo();
$profilePicture = null;

// Process profile picture path
if (!empty($currentUserInfo['profile_picture'])) {
    $dbPath = $currentUserInfo['profile_picture'];
    
    // Check if it's a data URI (default image)
    if (strpos($dbPath, 'data:image') === 0) {
        $profilePicture = $dbPath;
    } else {
        // Convert database path to correct web path
        // Database might have: assets/uploads/profile_pictures/... or public/assets/uploads/profile_pictures/...
        
        // Remove any leading slashes or relative paths
        $cleanPath = ltrim($dbPath, '/');
        
        // Build possible file system paths to check
        $baseDir = __DIR__ . '/../../';
        $possibleFilePaths = [
            $baseDir . 'public/assets/uploads/profile_pictures/' . basename($cleanPath),
            $baseDir . 'assets/uploads/profile_pictures/' . basename($cleanPath),
            $baseDir . 'public/' . $cleanPath,
            $baseDir . $cleanPath
        ];
        
        // Build possible web paths
        $possibleWebPaths = [
            '../../public/assets/uploads/profile_pictures/' . basename($cleanPath),
            '../../assets/uploads/profile_pictures/' . basename($cleanPath),
            '../../public/' . $cleanPath,
            '../../' . $cleanPath
        ];
        
        // Check which file actually exists
        $foundPath = null;
        foreach ($possibleFilePaths as $index => $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $foundPath = $possibleWebPaths[$index];
                break;
            }
        }
        
        if ($foundPath) {
            $profilePicture = $foundPath . '?t=' . time();
        } else {
            // File doesn't exist - set to null to show default placeholder
            // This handles broken image references in the database
            $profilePicture = null;
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Profile Management</h4>
                <p class="text-muted mb-0">Manage your account information and settings</p>
            </div>
        </div>

        <div class="row">
            <!-- Profile Picture Section -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-circle me-2"></i>Profile Picture
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if ($profilePicture): ?>
                                <img id="profilePicture" 
                                     src="<?= htmlspecialchars($profilePicture) ?>" 
                                     alt="Profile Picture" 
                                     class="rounded-circle" 
                                     style="width: 150px; height: 150px; object-fit: cover;"
                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light border" 
                                     style="width: 150px; height: 150px; margin: 0 auto; display: none;">
                                    <i class="bi bi-person-fill text-muted" style="font-size: 4rem;"></i>
                                </div>
                            <?php else: ?>
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light border" 
                                     style="width: 150px; height: 150px; margin: 0 auto;">
                                    <i class="bi bi-person-fill text-muted" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <input type="file" 
                                   id="profilePictureInput" 
                                   accept="image/*" 
                                   style="display: none;"
                                   onchange="uploadProfilePicture(this)">
                            <button class="btn btn-primary" onclick="document.getElementById('profilePictureInput').click()">
                                <i class="bi bi-upload me-1"></i>Upload Picture
                            </button>
                            <?php if ($profilePicture): ?>
                            <button class="btn btn-outline-danger" onclick="removeProfilePicture()">
                                <i class="bi bi-trash me-1"></i>Remove Picture
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <small class="text-muted mt-2 d-block">
                            Supported formats: JPEG, PNG, WebP<br>
                            Maximum size: 5MB
                        </small>
                    </div>
                </div>
            </div>

            <!-- Profile Information -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-lines-fill me-2"></i>Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="profileForm" onsubmit="updateProfile(); return false;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fname" class="form-label">First Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="fname" 
                                           name="fname" 
                                           value="<?= htmlspecialchars($currentUserInfo['fname'] ?? '') ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lname" class="form-label">Last Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="lname" 
                                           name="lname" 
                                           value="<?= htmlspecialchars($currentUserInfo['lname'] ?? '') ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="minitial" class="form-label">Middle Initial</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="minitial" 
                                           name="minitial" 
                                           value="<?= htmlspecialchars($currentUserInfo['minitial'] ?? '') ?>" 
                                           maxlength="10">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="acc_user" class="form-label">Username</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="acc_user" 
                                           name="acc_user" 
                                           value="<?= htmlspecialchars($currentUserInfo['acc_user'] ?? '') ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="acc_email" class="form-label">Email Address</label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="acc_email" 
                                           name="acc_email" 
                                           value="<?= htmlspecialchars($currentUserInfo['acc_email'] ?? '') ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="dept_name" class="form-label">Department</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="dept_name" 
                                           value="<?= htmlspecialchars($currentUserInfo['dept_name'] ?? 'No Department') ?>" 
                                           readonly>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="acc_status" class="form-label">Account Status</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="acc_status" 
                                           value="<?= htmlspecialchars($currentUserInfo['acc_status'] ?? 'Active') ?>" 
                                           readonly>
                                </div>
                            </div>

                            <div class="row">
                            <div class="col-12">
                                <hr class="my-3">
                                <h6 class="text-muted mb-3">Change Password (optional)</h6>
                                <p class="small text-muted">To change your password, fill all three fields below and click <strong>Update Profile</strong>.</p>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password"
                                       class="form-control"
                                       id="current_password"
                                       name="current_password"
                                       autocomplete="current-password">
                                <div class="form-text">Required only if you set a new password.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password"
                                       class="form-control"
                                       id="new_password"
                                       name="new_password"
                                       minlength="6"
                                       autocomplete="new-password">
                                <div class="form-text">Minimum 6 characters.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password"
                                       class="form-control"
                                       id="confirm_password"
                                       name="confirm_password"
                                       minlength="6"
                                       autocomplete="new-password">
                            </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Update Profile
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to reset profile form
function resetForm() {
    document.getElementById('profileForm').reset();
}

(function () {
    const confirmEl = document.getElementById('confirm_password');
    if (!confirmEl) return;
    confirmEl.addEventListener('input', function () {
        const newPassword = document.getElementById('new_password').value;
        if (newPassword !== this.value) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
})();
</script>