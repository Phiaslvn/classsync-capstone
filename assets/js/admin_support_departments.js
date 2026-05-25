// Admin Support – Add Department modal behavior (shared for overview/users tabs)

document.addEventListener('DOMContentLoaded', function () {
  const addDeptModal = document.getElementById('addDepartmentModal');
  const addDeptForm = document.getElementById('addDepartmentForm');

  if (addDeptForm) {
    addDeptForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.innerHTML;

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';

      fetch('../../admin/management/add_department.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const modal = addDeptModal ? bootstrap.Modal.getInstance(addDeptModal) : null;
            if (modal) {
              modal.hide();
            }

            if (window.ensureSwalPosition) {
              window.ensureSwalPosition();
            }
            const swalInstance = window.getSwal ? window.getSwal() : null;
            if (swalInstance) {
              swalInstance.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Department added successfully.',
                timer: 2000,
                showConfirmButton: false
              }).then(() => {
                addDeptForm.reset();
                window.location.reload();
              });
            } else {
              alert(data.message || 'Department added successfully!');
              addDeptForm.reset();
              window.location.reload();
            }
          } else {
            throw new Error(data.message || 'Failed to add department.');
          }
        })
        .catch(error => {
          console.error('Error adding department:', error);
          if (window.ensureSwalPosition) {
            window.ensureSwalPosition();
          }
          const swalInstance = window.getSwal ? window.getSwal() : null;
          if (swalInstance) {
            swalInstance.fire({
              icon: 'error',
              title: 'Error',
              text: error.message || 'Failed to add department. Please try again.',
              confirmButtonColor: '#800000'
            });
          } else {
            alert(error.message || 'Failed to add department. Please try again.');
          }
        })
        .finally(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        });
    });
  }
});
