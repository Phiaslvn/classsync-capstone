<?php
/**
 * Role Management Component for Admin Dashboard
 * Allows department heads to manage roles within their department
 */

// Get current user info
$currentUserId = $_SESSION['acc_id'] ?? null;
$currentUserInfo = getUserInfo($currentUserId);
$currentUserRole = getUserRole($currentUserId);

// Fetch users in the same department
$users = [];
if ($currentUserInfo && $currentUserInfo['dept_id']) {
    $stmt = $conn->prepare("
        SELECT a.acc_id, a.fname, a.lname, a.acc_user, a.acc_email, a.acc_status, 
               r.role_name, r.id as role_id, d.dept_name
        FROM account a
        LEFT JOIN user_roles ur ON a.acc_id = ur.acc_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN department d ON a.dept_id = d.dept_id
        WHERE a.dept_id = ? AND a.acc_id != ?
        ORDER BY a.fname, a.lname
    ");
    $stmt->bind_param("ii", $currentUserInfo['dept_id'], $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($user = $result->fetch_assoc()) {
        $users[] = $user;
    }
    $stmt->close();
}

// Fetch available roles (Admin can assign Moderator and Instructor roles)
$availableRoles = [];
$stmt = $conn->prepare("SELECT * FROM roles WHERE id IN (3, 4) ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();

while ($role = $result->fetch_assoc()) {
    $availableRoles[] = $role;
}
$stmt->close();
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Department Role Management</h5>
            <small class="text-muted">Manage roles within your department</small>
        </div>
        
        <?php if (empty($users)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No other users in your department to manage.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Department Users</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Current Role</th>
                                    <th>New Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-user-id="<?= $user['acc_id'] ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?= strtoupper(substr($user['fname'], 0, 1) . substr($user['lname'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></strong><br>
                                                    <small class="text-muted">@<?= htmlspecialchars($user['acc_user']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($user['acc_email']) ?></td>
                                        <td>
                                            <span class="badge <?= $user['acc_status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($user['acc_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= htmlspecialchars($user['role_name'] ?? 'No Role') ?></span>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm role-select" data-user-id="<?= $user['acc_id'] ?>">
                                                <option value="">Select New Role</option>
                                                <?php foreach ($availableRoles as $role): ?>
                                                    <?php $selected = ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>
                                                    <option value="<?= $role['id'] ?>" <?= $selected ?>>
                                                        <?= htmlspecialchars($role['role_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="updateUserRole(<?= $user['acc_id'] ?>)">
                                                <i class="bi bi-arrow-repeat"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateUserRole(userId) {
    const roleSelect = document.querySelector(`select[data-user-id="${userId}"]`);
    const newRoleId = roleSelect.value;
    
    if (!newRoleId) {
        Swal.fire({
            icon: 'warning',
            title: 'Please select a role',
            text: 'You must select a new role before updating.',
            confirmButtonColor: '#800000'
        });
        return;
    }
    
    Swal.fire({
        title: 'Update User Role?',
        text: 'Are you sure you want to update this user\'s role?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#800000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'assign_user_role');
            formData.append('csrf_token', '<?= generateCSRFToken() ?>');
            formData.append('user_id', userId);
            formData.append('new_role_id', newRoleId);
            
            fetch('../../role_management_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'User role updated successfully!',
                        confirmButtonColor: '#800000'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message,
                        confirmButtonColor: '#800000'
                    });
                }
            })
            .catch(error => {
                console.error('Error updating user role:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while updating the user role.',
                    confirmButtonColor: '#800000'
                });
            });
        }
    });
}
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 14px;
    font-weight: 600;
}
</style>

