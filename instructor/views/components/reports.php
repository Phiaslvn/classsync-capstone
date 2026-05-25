<?php
/**
 * Instructor Reports Component
 * Allows instructors to view academic reports and analytics
 */
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Academic Reports</h4>
                <p class="text-muted mb-0">View academic reports and analytics for your classes</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshReports()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
                <button class="btn btn-primary btn-sm" onclick="generateReport()">
                    <i class="bi bi-file-earmark-text me-1"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" onchange="filterReports()">
                            <option value="">All Reports</option>
                            <option value="attendance">Attendance Report</option>
                            <option value="grades">Grades Report</option>
                            <option value="schedule">Schedule Report</option>
                            <option value="workload">Workload Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="dateFrom" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="dateFrom" onchange="filterReports()">
                    </div>
                    <div class="col-md-3">
                        <label for="dateTo" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="dateTo" onchange="filterReports()">
                    </div>
                    <div class="col-md-3">
                        <label for="reportSearch" class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="reportSearch" 
                               placeholder="Search reports..."
                               onkeyup="searchReports()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Report Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold">25</div>
                                <div class="small">Total Students</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-calendar-check fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold">95%</div>
                                <div class="small">Average Attendance</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-book fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold">5</div>
                                <div class="small">Active Subjects</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-clock fs-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fs-4 fw-bold">18</div>
                                <div class="small">Hours/Week</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>Available Reports
                </h5>
            </div>
            <div class="card-body">
                <div id="reportsContainer">
                    <!-- Loading state -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading reports...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text me-2"></i>Generate Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="generateReportForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reportTypeSelect" class="form-label">Report Type</label>
                            <select class="form-select" id="reportTypeSelect" name="reportType" required>
                                <option value="">Select Report Type</option>
                                <option value="attendance">Attendance Report</option>
                                <option value="grades">Grades Report</option>
                                <option value="schedule">Schedule Report</option>
                                <option value="workload">Workload Report</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="subjectSelect" class="form-label">Subject</label>
                            <select class="form-select" id="subjectSelect" name="subjectId" required>
                                <option value="">Select Subject</option>
                                <option value="1">CS101 - Programming Fundamentals</option>
                                <option value="2">CS201 - Data Structures</option>
                                <option value="3">CS301 - Database Systems</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="startDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="endDate" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reportFormat" class="form-label">Report Format</label>
                        <select class="form-select" id="reportFormat" name="format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitReportGeneration()">
                    <i class="bi bi-download me-1"></i>Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load reports
function loadReports() {
    console.log('Loading reports...');
    
    // Simulate loading reports (replace with actual API call)
    setTimeout(() => {
        const reportsContainer = document.getElementById('reportsContainer');
        if (reportsContainer) {
            reportsContainer.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Report Name</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Date Range</th>
                                <th>Generated</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="fw-bold">CS101 Attendance Report</div>
                                    <small class="text-muted">Programming Fundamentals</small>
                                </td>
                                <td><span class="badge bg-primary">Attendance</span></td>
                                <td>CS101</td>
                                <td>Jan 1 - Jan 31, 2024</td>
                                <td>2024-01-31</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadReport(1)">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewReport(1)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="fw-bold">CS201 Grades Report</div>
                                    <small class="text-muted">Data Structures</small>
                                </td>
                                <td><span class="badge bg-success">Grades</span></td>
                                <td>CS201</td>
                                <td>Jan 1 - Jan 31, 2024</td>
                                <td>2024-01-31</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadReport(2)">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewReport(2)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="fw-bold">Weekly Schedule Report</div>
                                    <small class="text-muted">All Subjects</small>
                                </td>
                                <td><span class="badge bg-info">Schedule</span></td>
                                <td>All</td>
                                <td>Jan 1 - Jan 7, 2024</td>
                                <td>2024-01-07</td>
                                <td><span class="badge bg-warning">Processing</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                        <i class="bi bi-hourglass-split"></i>
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

// Function to refresh reports
function refreshReports() {
    console.log('Refreshing reports...');
    loadReports();
}

// Function to filter reports
function filterReports() {
    console.log('Filtering reports...');
    // Implement filtering logic
}

// Function to search reports
function searchReports() {
    console.log('Searching reports...');
    // Implement search logic
}

// Function to generate report
function generateReport() {
    const modal = new bootstrap.Modal(document.getElementById('generateReportModal'));
    modal.show();
}

// Function to submit report generation
function submitReportGeneration() {
    const form = document.getElementById('generateReportForm');
    const formData = new FormData(form);
    
    // Show loading
    Swal.fire({
        title: 'Generating Report...',
        text: 'Please wait while we generate your report.',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Simulate API call
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Report Generated!',
            text: 'Your report has been generated successfully.',
            confirmButtonColor: '#800000',
            confirmButtonText: 'OK'
        }).then(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('generateReportModal'));
            modal.hide();
            form.reset();
            loadReports(); // Refresh the list
        });
    }, 3000);
}

// Function to download report
function downloadReport(reportId) {
    Swal.fire({
        title: 'Downloading Report...',
        text: 'Please wait while we prepare your download.',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Simulate download
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Download Started!',
            text: 'Your report download has started.',
            confirmButtonColor: '#800000',
            confirmButtonText: 'OK'
        });
    }, 2000);
}

// Function to view report
function viewReport(reportId) {
    Swal.fire({
        title: 'Report Preview',
        text: `Previewing report ID: ${reportId}`,
        icon: 'info',
        confirmButtonColor: '#800000',
        confirmButtonText: 'OK'
    });
}

// Initialize reports when component loads
document.addEventListener('DOMContentLoaded', function() {
    loadReports();
});
</script>