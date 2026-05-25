<?php
/**
 * Manage Roles tab – role-permission matrix saved to role_permissions table.
 * Expects: $roles, $permissions, $role_permissions (role_id => [permission_id, ...]), $csrf_token
 */
$permissions_by_module = [];
foreach ($permissions as $p) {
    $mod = $p['module'] ?? 'other';
    if (!isset($permissions_by_module[$mod])) $permissions_by_module[$mod] = [];
    $permissions_by_module[$mod][] = $p;
}
?>

<div class="dashboard-card">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h5 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Role permissions</h5>
      <p class="text-muted small mb-0">Configure which permissions each role has. Changes apply system-wide.</p>
    </div>
  </div>

  <form method="post" action="index.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="update_permissions" value="1">

    <div class="table-responsive permission-matrix-scroll" role="region" aria-label="Role permissions matrix">
      <table class="table table-bordered permission-matrix">
        <thead>
          <tr>
            <th class="perm-col-permission">Permission</th>
            <?php foreach ($roles as $r): ?>
              <th class="text-center perm-col-role"><?= htmlspecialchars($r['role_name']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permissions_by_module as $module => $perms): ?>
            <tr class="table-light">
              <td colspan="<?= count($roles) + 1 ?>" class="fw-bold text-muted small text-uppercase"><?= htmlspecialchars($module) ?></td>
            </tr>
            <?php foreach ($perms as $p): 
              $pid = (int) $p['id'];
              $label = $p['permission_display_name'] ?? $p['permission_name'] ?? 'Permission ' . $pid;
            ?>
            <tr>
              <td class="perm-cell-label"><?= htmlspecialchars($label) ?></td>
              <?php foreach ($roles as $r): 
                $rid = (int) $r['id'];
                $checked = isset($role_permissions[$rid]) && in_array($pid, $role_permissions[$rid], true);
              ?>
                <td class="text-center">
                  <input type="checkbox" class="form-check-input" name="permissions[<?= $rid ?>][]" value="<?= $pid ?>" <?= $checked ? ' checked' : '' ?>>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      <button type="submit" class="btn btn-maroon"><i class="bi bi-check-lg me-1"></i> Save role permissions</button>
    </div>
  </form>
</div>
