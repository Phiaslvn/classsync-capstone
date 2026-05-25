// ============================================
// Add Existing Instructor to Department Feature
// ============================================

// Global variable to store selected instructor
let selectedInstructorForDept = null;

// Initialize the "Add Existing Instructor" modal when it's shown
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('addExistingInstructorModal');
  if (modal) {
    modal.addEventListener('shown.bs.modal', function() {
      setupExistingInstructorSearch();
      // Reset state
      selectedInstructorForDept = null;
      const searchInput = document.getElementById('existingInstructorSearch');
      const searchResults = document.getElementById('existingInstructorSearchResults');
      const selectedInfo = document.getElementById('selectedInstructorInfo');
      const addBtn = document.getElementById('addInstructorToDeptBtn');
      if (searchInput) searchInput.value = '';
      if (searchResults) searchResults.style.display = 'none';
      if (selectedInfo) selectedInfo.style.display = 'none';
      if (addBtn) addBtn.disabled = true;
    });
    
    modal.addEventListener('hidden.bs.modal', function() {
      // Clean up when modal is closed
      selectedInstructorForDept = null;
      const searchInput = document.getElementById('existingInstructorSearch');
      const searchResults = document.getElementById('existingInstructorSearchResults');
      const selectedInfo = document.getElementById('selectedInstructorInfo');
      if (searchInput) searchInput.value = '';
      if (searchResults) {
        searchResults.innerHTML = '';
        searchResults.style.display = 'none';
      }
      if (selectedInfo) selectedInfo.style.display = 'none';
    });
  }
});

/**
 * Sets up the instructor search functionality for adding existing instructor to department
 */
function setupExistingInstructorSearch() {
  const searchInput = document.getElementById('existingInstructorSearch');
  const searchResults = document.getElementById('existingInstructorSearchResults');
  const clearBtn = document.getElementById('clearExistingInstructorSearch');
  let searchTimeout = null;
  
  if (!searchInput) return;
  
  // Clear previous timeout
  const performSearch = () => {
    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }
    
    const searchTerm = searchInput.value.trim();
    
    // Hide results if search is empty
    if (searchTerm.length < 2) {
      if (searchResults) {
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
      }
      if (clearBtn) clearBtn.style.display = 'none';
      return;
    }
    
    // Show clear button
    if (clearBtn) clearBtn.style.display = 'block';
    
    // Debounce search (wait 300ms after user stops typing)
    searchTimeout = setTimeout(() => {
      searchExistingInstructors(searchTerm);
    }, 300);
  };
  
  // Search on input
  searchInput.addEventListener('input', performSearch);
  
  // Clear button
  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      searchInput.value = '';
      if (searchResults) {
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
      }
      clearBtn.style.display = 'none';
      selectedInstructorForDept = null;
      const selectedInfo = document.getElementById('selectedInstructorInfo');
      const addBtn = document.getElementById('addInstructorToDeptBtn');
      if (selectedInfo) selectedInfo.style.display = 'none';
      if (addBtn) addBtn.disabled = true;
    });
  }
  
  // Hide results when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('#existingInstructorSearch') && 
        !e.target.closest('#existingInstructorSearchResults') && 
        !e.target.closest('#clearExistingInstructorSearch')) {
      // Don't hide if instructor is selected
      if (!selectedInstructorForDept && searchResults) {
        searchResults.style.display = 'none';
      }
    }
  });
}

/**
 * Searches for existing instructors
 */
function searchExistingInstructors(searchTerm) {
  const searchResults = document.getElementById('existingInstructorSearchResults');
  
  if (searchTerm.length < 2 || !searchResults) {
    if (searchResults) searchResults.style.display = 'none';
    return;
  }
  
  // Show loading state
  searchResults.innerHTML = '<div class="list-group-item text-center text-muted"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>';
  searchResults.style.display = 'block';
  
  const apiBasePath = '../../admin/management/';
  fetch(`${apiBasePath}search_instructors.php?search=${encodeURIComponent(searchTerm)}`)
    .then(response => response.json())
    .then(data => {
      if (!searchResults) return;
      searchResults.innerHTML = '';
      
      if (data.success && data.instructors && data.instructors.length > 0) {
        // Display search results
        data.instructors.forEach(instructor => {
          // Format departments list
          let deptList = 'None';
          if (instructor.departments && instructor.departments.length > 0) {
            deptList = instructor.departments.map(d => d.dept_name).join(', ');
          } else if (instructor.dept_name && instructor.dept_name !== 'No Department') {
            deptList = instructor.dept_name;
          }
          
          const item = document.createElement('a');
          item.href = '#';
          item.className = 'list-group-item list-group-item-action instructor-search-item';
          item.setAttribute('data-inst-id', instructor.inst_id);
          item.setAttribute('data-acc-id', instructor.acc_id);
          item.innerHTML = `
            <div class="d-flex w-100 justify-content-between align-items-center">
              <div>
                <h6 class="mb-1">${instructor.name}</h6>
                <small class="text-muted">
                  <i class="bi bi-building me-1"></i>Departments: ${deptList}
                </small>
              </div>
              <i class="bi bi-chevron-right text-muted"></i>
            </div>
          `;
          
          // Handle click on search result
          item.addEventListener('click', function(e) {
            e.preventDefault();
            selectInstructorForDept(instructor);
          });
          
          searchResults.appendChild(item);
        });
        
        searchResults.style.display = 'block';
      } else {
        searchResults.innerHTML = '<div class="list-group-item text-center text-muted">No instructors found</div>';
        searchResults.style.display = 'block';
      }
    })
    .catch(error => {
      console.error('Error searching instructors:', error);
      if (searchResults) {
        searchResults.innerHTML = '<div class="list-group-item text-center text-danger">Error searching instructors</div>';
        searchResults.style.display = 'block';
      }
    });
}

/**
 * Selects an instructor for adding to department
 */
function selectInstructorForDept(instructor) {
  selectedInstructorForDept = instructor;
  
  // Hide search results
  const searchResults = document.getElementById('existingInstructorSearchResults');
  if (searchResults) searchResults.style.display = 'none';
  
  // Show selected instructor info
  const infoDiv = document.getElementById('selectedInstructorInfo');
  const nameSpan = document.getElementById('selectedInstructorName');
  const deptsSpan = document.getElementById('selectedInstructorDepts');
  
  if (nameSpan) nameSpan.textContent = instructor.name;
  
  // Format departments
  let deptList = 'None';
  if (instructor.departments && instructor.departments.length > 0) {
    deptList = instructor.departments.map(d => d.dept_name).join(', ');
  } else if (instructor.dept_name && instructor.dept_name !== 'No Department') {
    deptList = instructor.dept_name;
  }
  
  if (deptsSpan) deptsSpan.textContent = deptList || 'None';
  if (infoDiv) infoDiv.style.display = 'block';
  
  // Enable add button
  const addBtn = document.getElementById('addInstructorToDeptBtn');
  if (addBtn) addBtn.disabled = false;
}

/**
 * Adds the selected instructor to the current admin's department
 */
function addInstructorToDepartment() {
  if (!selectedInstructorForDept) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: 'No Instructor Selected',
        text: 'Please select an instructor first.',
        confirmButtonColor: '#800000'
      });
    } else {
      alert('Please select an instructor first.');
    }
    return;
  }
  
  const btn = document.getElementById('addInstructorToDeptBtn');
  if (!btn) return;
  
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Adding...';
  
  const formData = new FormData();
  formData.append('inst_id', selectedInstructorForDept.inst_id);
  
  fetch('../../admin/management/add_instructor_to_department.php', {
    method: 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      btn.innerHTML = originalText;
      
      if (data.success) {
        // Show success message
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: data.message,
            confirmButtonColor: '#800000',
            confirmButtonText: 'OK'
          }).then(() => {
            // Close modal
            const modalElement = document.getElementById('addExistingInstructorModal');
            if (modalElement) {
              const modal = bootstrap.Modal.getInstance(modalElement);
              if (modal) {
                modal.hide();
              }
            }
            // Reload accounts list
            if (typeof loadAccounts === 'function') {
              loadAccounts();
            }
          });
        } else {
          alert(data.message);
          // Close modal
          const modalElement = document.getElementById('addExistingInstructorModal');
          if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
              modal.hide();
            }
          }
          // Reload accounts list
          if (typeof loadAccounts === 'function') {
            loadAccounts();
          }
        }
      } else {
        // Show error
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.message || 'Failed to add instructor to department.',
            confirmButtonColor: '#800000'
          });
        } else {
          alert(data.message || 'Failed to add instructor to department.');
        }
        btn.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error adding instructor to department:', error);
      btn.innerHTML = originalText;
      btn.disabled = false;
      
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while adding the instructor. Please try again.',
          confirmButtonColor: '#800000'
        });
      } else {
        alert('An error occurred while adding the instructor. Please try again.');
      }
    });
}
