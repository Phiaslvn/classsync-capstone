<?php
/**
 * User Management tab – list, add, edit, delete, edit permissions.
 * Expects: $users, $roles, $departments, $csrf_token
 */
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<div class="dashboard-card">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="bi bi-people me-2"></i>User Management</h5>
    <button type="button" class="btn btn-maroon" data-bs-toggle="modal" data-bs-target="#addUserModal">
      <i class="bi bi-plus-lg me-1"></i> Add User
    </button>
  </div>

  <div class="card border mb-3">
    <div class="card-body py-3">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="fw-semibold text-dark small"><i class="bi bi-funnel me-1"></i>Search &amp; filters</span>
        <span class="text-muted small ms-auto" id="asUserFilterCount"></span>
      </div>
      <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
          <label class="form-label small fw-semibold mb-1" for="asUserSearch">Search</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control border-start-0" id="asUserSearch" placeholder="Name, username, or email…" autocomplete="off">
          </div>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small fw-semibold mb-1" for="asUserRoleFilter">Role</label>
          <select class="form-select form-select-sm" id="asUserRoleFilter">
            <option value="">All roles</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r['role_name']) ?>"><?= htmlspecialchars($r['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label small fw-semibold mb-1" for="asUserDeptFilter">Department</label>
          <select class="form-select form-select-sm" id="asUserDeptFilter">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= htmlspecialchars($d['dept_name']) ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small fw-semibold mb-1" for="asUserStatusFilter">Status</label>
          <select class="form-select form-select-sm" id="asUserStatusFilter">
            <option value="">All statuses</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Pending">Pending</option>
          </select>
        </div>
        <div class="col-lg-1 col-md-6 d-grid">
          <label class="form-label small fw-semibold mb-1 d-none d-lg-block">&nbsp;</label>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="asUserFilterClear" title="Reset filters">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Department</th>
          <th>Status</th>
          <th width="180">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): 
          $status = $u['acc_status'] ?? 'Active';
          $status_class = $status === 'Active' ? 'success' : ($status === 'Deleted' ? 'secondary' : 'warning');
          $role_name = $u['role_name'] ?? '';
          $dept_name = trim((string)($u['dept_name'] ?? ''));
          $search_blob = strtolower(trim(
            ($u['fname'] ?? '') . ' ' . ($u['lname'] ?? '') . ' ' . ($u['acc_user'] ?? '') . ' ' . ($u['acc_email'] ?? '') . ' ' . $dept_name . ' ' . $role_name
          ));
        ?>
        <tr class="as-user-row" data-user-id="<?= (int)$u['acc_id'] ?>"
            data-role="<?= htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8') ?>"
            data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
            data-dept="<?= htmlspecialchars($dept_name, ENT_QUOTES, 'UTF-8') ?>"
            data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>">
          <td><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
          <td><?= htmlspecialchars($u['acc_user']) ?></td>
          <td><span class="badge bg-primary"><?= htmlspecialchars($u['role_name'] ?? '—') ?></span></td>
          <td><?= htmlspecialchars($u['dept_name'] ?? '—') ?></td>
          <td><span class="badge bg-<?= $status_class ?>"><?= htmlspecialchars($status) ?></span></td>
          <td>
            <?php if ($status !== 'Deleted'): ?>
            <button type="button" class="btn btn-sm btn-outline-primary edit-user-btn" data-id="<?= (int)$u['acc_id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-info perm-user-btn" data-id="<?= (int)$u['acc_id'] ?>" data-name="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>" title="Permissions"><i class="bi bi-shield-lock"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger delete-user-btn" data-id="<?= (int)$u['acc_id'] ?>" data-name="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>" title="Delete"><i class="bi bi-trash"></i></button>
            <?php else: ?>
            <span class="text-muted small">Archived</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add User Modal – styled like Add Department modal -->
<div class="modal fade as-modal-component as-modal-wide" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable as-modal-dialog-component">
    <div class="modal-content as-modal-content-component">
      <div class="modal-header as-modal-header-component">
        <div class="as-modal-header-left">
          <i class="bi bi-person-plus as-modal-header-icon" aria-hidden="true"></i>
          <h5 class="modal-title as-modal-title" id="addUserModalLabel">Add User</h5>
        </div>
        <button type="button" class="btn-close as-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="save_user.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="role_id" id="addRoleId" value="">
        <div class="modal-body as-modal-body-component">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="as-modal-label">First name <span class="as-modal-asterisk">*</span></label>
              <input type="text" name="fname" class="as-modal-input" required>
            </div>
            <div class="col-md-6">
              <label class="as-modal-label">Last name <span class="as-modal-asterisk">*</span></label>
              <input type="text" name="lname" class="as-modal-input" required>
            </div>
            <div class="col-12">
              <label class="as-modal-label">Middle initial <span class="text-muted">(Optional)</span></label>
              <input type="text" name="minitial" class="as-modal-input" maxlength="10" placeholder="e.g. M">
            </div>
            <div class="col-md-6">
              <label class="as-modal-label">Username <span class="as-modal-asterisk">*</span></label>
              <input type="text" name="acc_user" class="as-modal-input" required>
            </div>
            <div class="col-md-6">
              <label class="as-modal-label">Email <span class="as-modal-asterisk">*</span></label>
              <input type="email" name="acc_email" class="as-modal-input" required>
            </div>
            <div class="col-md-6">
              <label class="as-modal-label">Role <span class="as-modal-asterisk">*</span></label>
              <select class="as-modal-input as-modal-select" id="addRoleSelect" required>
                <option value="">Select role</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="as-modal-label">Department</label>
              <select class="as-modal-input as-modal-select" name="dept_id">
                <option value="0">— None —</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer as-modal-footer-component">
          <button type="button" class="as-modal-btn as-modal-btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
          <button type="submit" class="as-modal-btn as-modal-btn-submit"><i class="bi bi-check-lg me-1"></i>Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit User Modal (same large size as Add New Department) -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="edit_user.php" id="editUserForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="acc_id" id="editAccId">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">First name <span class="text-danger">*</span></label>
              <input type="text" name="fname" id="editFname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Last name <span class="text-danger">*</span></label>
              <input type="text" name="lname" id="editLname" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Middle initial <span class="text-muted">(Optional)</span></label>
              <input type="text" name="minitial" id="editMinitial" class="form-control" maxlength="10">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
              <input type="text" name="acc_user" id="editAccUser" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <select class="form-select" name="acc_status" id="editAccStatus">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Pending">Pending</option>
                <option value="Deleted">Deleted</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
              <select class="form-select" name="role_id" id="editRoleId" required>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
          <button type="submit" class="btn btn-maroon"><i class="bi bi-check-lg me-1"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Permissions Modal (same large size as Add New Department) -->
<div class="modal fade" id="permUserModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/admin_support/permissions_backend/save_permissions.php" id="permUserForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="acc_id" id="permAccId">
        <div class="modal-header">
          <h5 class="modal-title">Edit Permissions · <span id="permUserName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <p class="text-muted small mb-3">User overrides for role-based permissions. Check to allow.</p>
          <div id="permCheckboxes" class="row g-2"></div>
        </div>
        <div class="modal-footer border-0 bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
          <button type="submit" class="btn btn-maroon"><i class="bi bi-check-lg me-1"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function() {
  var basePath = '<?= addslashes($base_path) ?>';

  function applyAsUserFilters() {
    var q = (document.getElementById('asUserSearch') && document.getElementById('asUserSearch').value || '').toLowerCase().trim();
    var role = document.getElementById('asUserRoleFilter') ? document.getElementById('asUserRoleFilter').value : '';
    var dept = document.getElementById('asUserDeptFilter') ? document.getElementById('asUserDeptFilter').value : '';
    var status = document.getElementById('asUserStatusFilter') ? document.getElementById('asUserStatusFilter').value : '';
    var rows = document.querySelectorAll('.as-user-row');
    rows.forEach(function(row) {
      var show = true;
      if (q) {
        var hay = (row.getAttribute('data-search') || '').toLowerCase();
        if (hay.indexOf(q) === -1) show = false;
      }
      if (role && (row.getAttribute('data-role') || '') !== role) show = false;
      if (dept && (row.getAttribute('data-dept') || '').trim() !== dept) show = false;
      if (status && (row.getAttribute('data-status') || '') !== status) show = false;
      row.style.display = show ? '' : 'none';
    });
    var visible = 0;
    rows.forEach(function(row) { if (row.style.display !== 'none') visible++; });
    var countEl = document.getElementById('asUserFilterCount');
    if (countEl) countEl.textContent = visible + ' of ' + rows.length + ' shown';
  }

  var searchEl = document.getElementById('asUserSearch');
  var roleEl = document.getElementById('asUserRoleFilter');
  var deptEl = document.getElementById('asUserDeptFilter');
  var statusEl = document.getElementById('asUserStatusFilter');
  var clearEl = document.getElementById('asUserFilterClear');
  if (searchEl) searchEl.addEventListener('input', applyAsUserFilters);
  if (roleEl) roleEl.addEventListener('change', applyAsUserFilters);
  if (deptEl) deptEl.addEventListener('change', applyAsUserFilters);
  if (statusEl) statusEl.addEventListener('change', applyAsUserFilters);
  if (clearEl) clearEl.addEventListener('click', function() {
    if (searchEl) searchEl.value = '';
    if (roleEl) roleEl.value = '';
    if (deptEl) deptEl.value = '';
    if (statusEl) statusEl.value = '';
    applyAsUserFilters();
  });
  applyAsUserFilters();

  document.getElementById('addRoleSelect').addEventListener('change', function() {
    document.getElementById('addRoleId').value = this.value || '';
  });

  document.querySelectorAll('.edit-user-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', basePath + '/get_user_data_for_edit.php');
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success && res.user) {
          var u = res.user;
          document.getElementById('editAccId').value = u.acc_id;
          document.getElementById('editFname').value = u.fname || '';
          document.getElementById('editLname').value = u.lname || '';
          document.getElementById('editMinitial').value = u.minitial || '';
          document.getElementById('editAccUser').value = u.acc_user || '';
          document.getElementById('editAccStatus').value = u.acc_status || 'Active';
          document.getElementById('editRoleId').value = u.role_id || '';
          document.getElementById('editUserForm').action = basePath + '/edit_user.php';
          new bootstrap.Modal(document.getElementById('editUserModal')).show();
        } else {
          alert(res.message || 'Failed to load user');
        }
      };
      xhr.send('user_id=' + encodeURIComponent(id));
    });
  });

  document.querySelectorAll('.perm-user-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var name = this.getAttribute('data-name');
      document.getElementById('permAccId').value = id;
      document.getElementById('permUserName').textContent = name;
      document.getElementById('permUserForm').action = '/admin_support/permissions_backend/save_permissions.php';
        fetch('/admin_support/permissions_backend/get_user_permissions.php?acc_id=' + id).then(function(r) { return r.json(); }).then(function(ids) {
        if (!Array.isArray(ids)) ids = [];
        fetch('/admin_support/permissions_backend/get_all_permissions.php').then(function(r) { return r.json(); }).then(function(data) {
          var html = '';
          if (data.success && data.permissions) {
            data.permissions.forEach(function(p) {
              var checked = ids.indexOf(parseInt(p.id, 10)) !== -1;
              var val = checked ? '1' : '0';
              html += '<div class="col-md-6"><div class="form-check">' +
                '<input type="hidden" name="permissions[' + p.id + ']" value="' + val + '">' +
                '<input class="form-check-input perm-cb" type="checkbox" value="1" id="p' + p.id + '" data-pid="' + p.id + '"' + (checked ? ' checked' : '') + '>' +
                '<label class="form-check-label" for="p' + p.id + '">' + (p.permission_display_name || p.permission_name) + '</label></div></div>';
            });
          }
          document.getElementById('permCheckboxes').innerHTML = html || '<p class="text-muted">No permissions defined.</p>';
          document.querySelectorAll('#permCheckboxes .perm-cb').forEach(function(cb) {
            cb.addEventListener('change', function() {
              var hid = this.closest('.form-check').querySelector('input[type="hidden"]');
              if (hid) hid.value = this.checked ? '1' : '0';
            });
          });
        });
      });
      new bootstrap.Modal(document.getElementById('permUserModal')).show();
    });
  });

  document.querySelectorAll('.delete-user-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var name = this.getAttribute('data-name');
      if (!confirm('Archive user “‘ + name + ’”? They will no longer be able to log in.')) return;
      var form = document.createElement('form');
      form.method = 'post';
      form.action = basePath + '/delete_user.php';
      var tok = document.createElement('input');
      tok.type = 'hidden'; tok.name = 'csrf_token'; tok.value = '<?= addslashes($csrf_token) ?>';
      var uid = document.createElement('input');
      uid.type = 'hidden'; uid.name = 'user_id'; uid.value = id;
      var sub = document.createElement('input');
      sub.type = 'hidden'; sub.name = 'delete_user'; sub.value = '1';
      form.appendChild(tok); form.appendChild(uid); form.appendChild(sub);
      document.body.appendChild(form);
      form.submit();
    });
  });
})();
</script>
