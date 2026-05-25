<?php
/**
 * Admin Support – Add New Department modal component
 * Matches Admin Support UI theme (deep red sidebar, RBAC dashboard).
 * Use with Bootstrap 5 modal JS; include this file where the modal is needed.
 */
?>
<!-- Add New Department Modal (AS theme: red header, 450px, flexbox overlay) -->
<div class="modal fade as-modal-component" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered as-modal-dialog-component">
    <div class="modal-content as-modal-content-component">
      <div class="modal-header as-modal-header-component">
        <div class="as-modal-header-left">
          <i class="bi bi-building as-modal-header-icon" aria-hidden="true"></i>
          <h5 class="modal-title as-modal-title" id="addDepartmentModalLabel">Add New Department</h5>
        </div>
        <button type="button" class="btn-close as-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addDepartmentForm" class="as-modal-form">
        <div class="modal-body as-modal-body-component">
          <div class="as-modal-field">
            <label for="dept_name" class="as-modal-label">Department Name <span class="as-modal-asterisk">*</span></label>
            <input type="text" id="dept_name" name="dept_name" class="as-modal-input" placeholder="e.g. Computer Studies Department" required>
            <div class="invalid-feedback">Please enter department name.</div>
          </div>
          <div class="as-modal-field">
            <label for="dept_code" class="as-modal-label">Department Code (Optional)</label>
            <input type="text" id="dept_code" name="dept_code" class="as-modal-input" placeholder="e.g. CS" maxlength="10">
            <small class="as-modal-helper">Short code for the department (max 10 characters)</small>
          </div>
          <div class="as-modal-field">
            <label for="dept_status" class="as-modal-label">Status <span class="as-modal-asterisk">*</span></label>
            <select id="dept_status" name="dept_status" class="as-modal-input as-modal-select" required>
              <option value="Active" selected>Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer as-modal-footer-component">
          <button type="button" class="as-modal-btn as-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="as-modal-btn as-modal-btn-submit">
            <i class="bi bi-check-lg as-modal-btn-icon" aria-hidden="true"></i>Add Department
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
