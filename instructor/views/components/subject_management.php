<?php
/**
 * Instructor Subject Management Component
 * Allows instructors to view their assigned subjects
 */
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">My Subjects</h4>
                <p class="text-muted mb-0">View your assigned subjects and curriculum</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshSubjects()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Subject Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="subjectFilter" class="form-label">Filter by Category</label>
                        <select class="form-select" id="subjectFilter" onchange="filterSubjects()">
                            <option value="">All Categories</option>
                            <option value="Major">Major</option>
                            <option value="Minor">Minor</option>
                            <option value="GENED">General Education</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="levelFilter" class="form-label">Filter by Level</label>
                        <select class="form-select" id="levelFilter" onchange="filterSubjects()">
                            <option value="">All Levels</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="termFilter" class="form-label">Filter by Term</label>
                        <select class="form-select" id="termFilter" onchange="filterSubjects()">
                            <option value="">All Terms</option>
                            <option value="1">1st Term</option>
                            <option value="2">2nd Term</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="subjectSearch" class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="subjectSearch" 
                               placeholder="Search subjects..."
                               onkeyup="searchSubjects()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-book me-2"></i>Assigned Subjects
                </h5>
            </div>
            <div class="card-body">
                <div id="subjectsContainer">
                    <!-- Loading state -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading your subjects...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load subjects
function loadSubjects() {
    console.log('Loading subjects...');
    
    // Simulate loading subjects (replace with actual API call)
    setTimeout(() => {
        const subjectsContainer = document.getElementById('subjectsContainer');
        if (subjectsContainer) {
            subjectsContainer.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Category</th>
                                <th>Level</th>
                                <th>Term</th>
                                <th>Units</th>
                                <th>Hours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="fw-bold text-primary">CS101</span></td>
                                <td>
                                    <div class="fw-bold">Programming Fundamentals</div>
                                    <small class="text-muted">Introduction to programming concepts</small>
                                </td>
                                <td><span class="badge bg-primary">Major</span></td>
                                <td><span class="badge bg-info">1st Year</span></td>
                                <td><span class="badge bg-warning">1st Term</span></td>
                                <td>3</td>
                                <td>54</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSubject('CS101')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-bold text-primary">CS201</span></td>
                                <td>
                                    <div class="fw-bold">Data Structures</div>
                                    <small class="text-muted">Advanced programming concepts</small>
                                </td>
                                <td><span class="badge bg-primary">Major</span></td>
                                <td><span class="badge bg-info">2nd Year</span></td>
                                <td><span class="badge bg-warning">1st Term</span></td>
                                <td>3</td>
                                <td>54</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSubject('CS201')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-bold text-primary">CS301</span></td>
                                <td>
                                    <div class="fw-bold">Database Systems</div>
                                    <small class="text-muted">Database design and management</small>
                                </td>
                                <td><span class="badge bg-primary">Major</span></td>
                                <td><span class="badge bg-info">3rd Year</span></td>
                                <td><span class="badge bg-warning">2nd Term</span></td>
                                <td>3</td>
                                <td>54</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSubject('CS301')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-bold text-success">ENG101</span></td>
                                <td>
                                    <div class="fw-bold">English Communication</div>
                                    <small class="text-muted">General Education subject</small>
                                </td>
                                <td><span class="badge bg-success">GENED</span></td>
                                <td><span class="badge bg-info">1st Year</span></td>
                                <td><span class="badge bg-warning">1st Term</span></td>
                                <td>3</td>
                                <td>54</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSubject('ENG101')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
        }
    }, 1000);
}

// Function to refresh subjects
function refreshSubjects() {
    console.log('Refreshing subjects...');
    loadSubjects();
}

// Function to filter subjects
function filterSubjects() {
    console.log('Filtering subjects...');
    // Implement filtering logic
}

// Function to search subjects
function searchSubjects() {
    console.log('Searching subjects...');
    // Implement search logic
}

// Function to view subject details
function viewSubject(subjectCode) {
    Swal.fire({
        title: 'Subject Details',
        html: `
            <div class="text-start">
                <h6>${subjectCode} - Programming Fundamentals</h6>
                <p><strong>Description:</strong> Introduction to programming concepts and problem-solving techniques.</p>
                <p><strong>Category:</strong> Major Subject</p>
                <p><strong>Level:</strong> 1st Year</p>
                <p><strong>Term:</strong> 1st Term</p>
                <p><strong>Units:</strong> 3</p>
                <p><strong>Hours:</strong> 54</p>
                <p><strong>Prerequisites:</strong> None</p>
            </div>
        `,
        icon: 'info',
        confirmButtonColor: '#800000',
        confirmButtonText: 'OK'
    });
}

// Initialize subjects when component loads
document.addEventListener('DOMContentLoaded', function() {
    loadSubjects();
});
</script>