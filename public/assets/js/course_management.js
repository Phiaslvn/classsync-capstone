/**
 * Course Management JavaScript Functions
 * Handles all course/program management functionality
 */

// Course Management Functions
// Prevent redeclaration error
if (typeof __COURSE_CACHE__ === 'undefined') {
    var __COURSE_CACHE__ = {};
}

function loadCourseData() {
  console.log('Loading course data...');
  
  // Determine correct path based on current location
  const coursePath = window.location.pathname.includes('/views/admin/') 
    ? '../../admin/management/get_course_data.php' 
    : 'get_course_data.php';
  
  fetch(coursePath)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        __COURSE_CACHE__ = data.data;
        updateCourseStatistics();
        populateCourseFilters();
        renderCourseTables();
        renderCampusHierarchy();
        console.log('✅ Course data loaded successfully');
      } else {
        console.error('❌ Failed to load course data:', data.message);
        showCourseError('Failed to load course data: ' + data.message);
      }
    })
    .catch(error => {
      console.error('❌ Error loading course data:', error);
      showCourseError('Error loading course data: ' + error.message);
    });
}

function updateCourseStatistics() {
  const stats = __COURSE_CACHE__.statistics;
  
  const elements = {
    'totalColleges': stats.total_colleges,
    'totalDepartments': stats.total_departments,
    'totalPrograms': stats.total_programs,
    'totalSubjects': stats.total_subjects
  };
  
  Object.entries(elements).forEach(([id, value]) => {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
  });
}

function populateCourseFilters() {
  // Populate college filter
  const collegeFilter = document.getElementById('collegeFilter');
  if (collegeFilter) {
    collegeFilter.innerHTML = '<option value="">All Colleges</option>';
    __COURSE_CACHE__.colleges.forEach(college => {
      const option = document.createElement('option');
      option.value = college.college_id;
      option.textContent = college.college_name;
      collegeFilter.appendChild(option);
    });
  }
  
  // Populate department filter
  const deptFilter = document.getElementById('departmentFilter');
  if (deptFilter) {
    deptFilter.innerHTML = '<option value="">All Departments</option>';
    __COURSE_CACHE__.departments.forEach(dept => {
      const option = document.createElement('option');
      option.value = dept.dept_id;
      option.textContent = `${dept.dept_name} (${dept.college_name})`;
      deptFilter.appendChild(option);
    });
  }
  
  // Populate program filter
  const programFilter = document.getElementById('programFilter');
  if (programFilter) {
    programFilter.innerHTML = '<option value="">All Programs</option>';
    __COURSE_CACHE__.programs.forEach(program => {
      const option = document.createElement('option');
      option.value = program.program_id;
      option.textContent = `${program.program_name} (${program.dept_name})`;
      programFilter.appendChild(option);
    });
  }
  
  // Populate department dropdowns in modals
  populateModalDropdowns();
}

function populateModalDropdowns() {
  // Populate department dropdowns in add modals
  const deptDropdowns = ['program_dept_id', 'curr_dept_id'];
  deptDropdowns.forEach(dropdownId => {
    const dropdown = document.getElementById(dropdownId);
    if (dropdown) {
      dropdown.innerHTML = '<option value="">Select Department</option>';
      __COURSE_CACHE__.departments.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept.dept_id;
        option.textContent = `${dept.dept_name} (${dept.college_name})`;
        dropdown.appendChild(option);
      });
    }
  });
  
  // Populate curriculum dropdown in add subject modal
  const currDropdown = document.getElementById('subj_curr_id');
  if (currDropdown) {
    currDropdown.innerHTML = '<option value="">Select Curriculum</option>';
    __COURSE_CACHE__.curriculums.forEach(curr => {
      const option = document.createElement('option');
      option.value = curr.curr_id;
      option.textContent = `${curr.curr_name} (${curr.dept_name})`;
      currDropdown.appendChild(option);
    });
  }
}

function renderCourseTables() {
  renderProgramsTable();
  renderCurriculumsTable();
  renderSubjectsTable();
}

function renderProgramsTable() {
  const tbody = document.getElementById('programsTableBody');
  if (!tbody) return;
  
  tbody.innerHTML = '';
  
  __COURSE_CACHE__.programs.forEach(program => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${program.program_id}</td>
      <td><span class="badge bg-primary">${program.program_code}</span></td>
      <td>${program.program_name}</td>
      <td>${program.dept_name}</td>
      <td>${program.college_name}</td>
      <td><span class="badge bg-${program.program_status === 'Active' ? 'success' : 'secondary'}">${program.program_status}</span></td>
      <td>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" onclick="editProgram(${program.program_id})" title="Edit Program">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(${program.program_id})" title="Delete Program">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </td>
    `;
    tbody.appendChild(row);
  });
}

function renderCurriculumsTable() {
  const tbody = document.getElementById('curriculumsTableBody');
  if (!tbody) return;
  
  tbody.innerHTML = '';
  
  __COURSE_CACHE__.curriculums.forEach(curriculum => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${curriculum.curr_id}</td>
      <td>${curriculum.curr_name}</td>
      <td><span class="badge bg-info">${curriculum.curr_lvl}${getOrdinalSuffix(curriculum.curr_lvl)} Year</span></td>
      <td>${curriculum.curr_yr}</td>
      <td>${curriculum.dept_name}</td>
      <td><span class="badge bg-warning">${curriculum.subjects_count}</span></td>
      <td>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" onclick="editCurriculum(${curriculum.curr_id})" title="Edit Curriculum">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteCurriculum(${curriculum.curr_id})" title="Delete Curriculum">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </td>
    `;
    tbody.appendChild(row);
  });
}

function renderSubjectsTable() {
  const tbody = document.getElementById('subjectsTableBody');
  if (!tbody) return;
  
  tbody.innerHTML = '';
  
  __COURSE_CACHE__.subjects.forEach(subject => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${subject.subj_id}</td>
      <td><span class="badge bg-primary">${subject.subj_code}</span></td>
      <td>${subject.subj_desc}</td>
      <td><span class="badge bg-info">${subject.subj_unit}</span></td>
      <td><span class="badge bg-secondary">${subject.subj_lvl}${getOrdinalSuffix(subject.subj_lvl)}</span></td>
      <td><span class="badge bg-warning">${subject.subj_term}${getOrdinalSuffix(subject.subj_term)}</span></td>
      <td><span class="badge bg-${getCategoryColor(subject.subj_category)}">${subject.subj_category}</span></td>
      <td>${subject.curr_name}</td>
      <td>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" onclick="editSubject(${subject.subj_id})" title="Edit Subject">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteSubject(${subject.subj_id})" title="Delete Subject">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </td>
    `;
    tbody.appendChild(row);
  });
}

function renderCampusHierarchy() {
  const container = document.getElementById('campusHierarchy');
  if (!container) return;
  
  container.innerHTML = '';
  
  __COURSE_CACHE__.hierarchy.forEach(college => {
    const collegeCard = document.createElement('div');
    collegeCard.className = 'card mb-3';
    collegeCard.innerHTML = `
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
          <i class="bi bi-building me-2"></i>${college.name} (${college.code})
          <span class="badge bg-light text-dark ms-2">${college.departments.length} Departments</span>
        </h5>
      </div>
      <div class="card-body">
        ${college.departments.map(dept => `
          <div class="mb-3">
            <h6 class="text-success">
              <i class="bi bi-diagram-2 me-2"></i>${dept.name} (${dept.code})
              <span class="badge bg-success ms-2">${dept.programs.length} Programs</span>
              <span class="badge bg-info ms-1">${dept.curriculums.length} Curriculums</span>
            </h6>
            <div class="ms-4">
              <div class="row">
                <div class="col-md-6">
                  <h6 class="text-info">Programs:</h6>
                  ${dept.programs.map(program => `
                    <div class="mb-1">
                      <span class="badge bg-primary">${program.code}</span> ${program.name}
                    </div>
                  `).join('')}
                </div>
                <div class="col-md-6">
                  <h6 class="text-warning">Curriculums:</h6>
                  ${dept.curriculums.map(curr => `
                    <div class="mb-1">
                      <span class="badge bg-warning">${curr.name}</span> (${curr.subjects_count} subjects)
                    </div>
                  `).join('')}
                </div>
              </div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
    container.appendChild(collegeCard);
  });
}

// Utility functions
function getOrdinalSuffix(num) {
  const j = num % 10;
  const k = num % 100;
  if (j === 1 && k !== 11) return 'st';
  if (j === 2 && k !== 12) return 'nd';
  if (j === 3 && k !== 13) return 'rd';
  return 'th';
}

function getCategoryColor(category) {
  switch(category) {
    case 'Major': return 'danger';
    case 'Minor': return 'warning';
    case 'GENED': return 'info';
    default: return 'secondary';
  }
}

// Save functions
function saveProgram() {
  const form = document.getElementById('addProgramForm');
  const formData = new FormData(form);
  const btn = event.target;
  const original = btn.innerHTML;
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  fetch('add_program.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Success!',
            text: 'Program added successfully',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          });
        }
        bootstrap.Modal.getInstance(document.getElementById('addProgramModal')).hide();
        form.reset();
        loadCourseData();
      } else {
        throw new Error(data.message || 'Failed to add program');
      }
    })
    .catch(error => {
      console.error('Error adding program:', error);
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Error!',
          text: error.message,
          icon: 'error'
        });
      } else {
        alert('Error: ' + error.message);
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

function saveCurriculum() {
  const form = document.getElementById('addCurriculumForm');
  const formData = new FormData(form);
  const btn = event.target;
  const original = btn.innerHTML;
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  fetch('add_curriculum.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Success!',
            text: 'Curriculum added successfully',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          });
        }
        bootstrap.Modal.getInstance(document.getElementById('addCurriculumModal')).hide();
        form.reset();
        loadCourseData();
      } else {
        throw new Error(data.message || 'Failed to add curriculum');
      }
    })
    .catch(error => {
      console.error('Error adding curriculum:', error);
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Error!',
          text: error.message,
          icon: 'error'
        });
      } else {
        alert('Error: ' + error.message);
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

function saveSubject() {
  const form = document.getElementById('addSubjectForm');
  const formData = new FormData(form);
  const btn = event.target;
  const original = btn.innerHTML;
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  fetch('add_subject.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Success!',
            text: 'Subject added successfully',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          });
        }
        bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
        form.reset();
        loadCourseData();
      } else {
        throw new Error(data.message || 'Failed to add subject');
      }
    })
    .catch(error => {
      console.error('Error adding subject:', error);
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Error!',
          text: error.message,
          icon: 'error'
        });
      } else {
        alert('Error: ' + error.message);
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

function showCourseError(message) {
  console.error('Course Error:', message);
}

// Placeholder functions for edit/delete operations
function editProgram(programId) {
  console.log('Edit program:', programId);
  // TODO: Implement edit program functionality
}

function deleteProgram(programId) {
  console.log('Delete program:', programId);
  // TODO: Implement delete program functionality
}

function editCurriculum(curriculumId) {
  console.log('Edit curriculum:', curriculumId);
  // TODO: Implement edit curriculum functionality
}

function deleteCurriculum(curriculumId) {
  console.log('Delete curriculum:', curriculumId);
  // TODO: Implement delete curriculum functionality
}

function editSubject(subjectId) {
  console.log('Edit subject:', subjectId);
  // TODO: Implement edit subject functionality
}

function deleteSubject(subjectId) {
  console.log('Delete subject:', subjectId);
  // TODO: Implement delete subject functionality
}

// Add Program function for Course Management
function saveProgramCourse() {
  const form = document.getElementById('addProgramFormCourse');
  const formData = new FormData(form);
  const btn = document.getElementById('saveProgramBtnCourse');
  const original = btn.innerHTML;
  
  // Validate required fields
  const requiredFields = ['program_name', 'program_code', 'dept_id'];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      alert(`Please fill in the ${field.replace('_', ' ')} field.`);
      return;
    }
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  // Send data to backend
  fetch('add_program.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Show success message
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Success!',
          text: data.message || 'Program added successfully!',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
      } else {
        alert(data.message || 'Program added successfully!');
      }
      
      // Reset form and close modal
      form.reset();
      document.getElementById('program_status_course').value = 'Active'; // Reset to default
      bootstrap.Modal.getInstance(document.getElementById('addProgramModalCourse')).hide();
      
      // Refresh course data
      if (typeof loadCourseData === 'function') {
        loadCourseData();
      }
    } else {
      throw new Error(data.message || 'Failed to add program');
    }
  })
  .catch(error => {
    console.error('Error adding program:', error);
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Error!',
        text: error.message,
        icon: 'error'
      });
    } else {
      alert('Error: ' + error.message);
    }
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = original;
  });
}
