<?php
// Include session configuration at the very beginning
require_once __DIR__ . '/../config/session.php';

// Handle verification messages
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IT Scheduling System | Homepage</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/main.css">
  <style>
    /* Ensure navbar uses correct EVSU-OCC theme color */
    .navbar.bg-maroon,
    nav.navbar.bg-maroon,
    .navbar-dark.bg-maroon {
      background-color: #8B0000 !important; /* EVSU Maroon */
      background: #8B0000 !important; /* EVSU Maroon */
    }
    
    /* Professional badge styles for search results - Improved Visibility */
    .badge.badge-day,
    #searchResultsBody .badge-day,
    .table .badge-day {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(96, 165, 250, 0.2) 100%) !important;
      color: #1e40af !important;
      border: 1.5px solid rgba(59, 130, 246, 0.3) !important;
      padding: 0.5rem 0.875rem !important;
      font-size: 0.8125rem !important;
      font-weight: 600 !important;
      border-radius: 8px !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
      box-shadow: 0 1px 3px rgba(59, 130, 246, 0.15) !important;
      letter-spacing: 0.3px !important;
      display: inline-block !important;
      min-width: 50px !important;
      text-align: center !important;
    }
    
    .badge.badge-day:hover,
    #searchResultsBody .badge-day:hover,
    .table .badge-day:hover {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(96, 165, 250, 0.25) 100%) !important;
      border-color: rgba(59, 130, 246, 0.4) !important;
      box-shadow: 0 2px 6px rgba(59, 130, 246, 0.2) !important;
      transform: translateY(-1px) !important;
    }
    
    .badge.badge-time,
    #searchResultsBody .badge-time,
    .table .badge-time {
      background: linear-gradient(135deg, rgba(220, 38, 38, 0.15) 0%, rgba(239, 68, 68, 0.2) 100%) !important;
      color: #991b1b !important;
      border: 1.5px solid rgba(220, 38, 38, 0.3) !important;
      padding: 0.5rem 0.875rem !important;
      font-size: 0.8125rem !important;
      font-weight: 600 !important;
      border-radius: 8px !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
      box-shadow: 0 1px 3px rgba(220, 38, 38, 0.15) !important;
      letter-spacing: 0.2px !important;
      display: inline-block !important;
      white-space: nowrap !important;
    }
    
    .badge.badge-time:hover,
    #searchResultsBody .badge-time:hover,
    .table .badge-time:hover {
      background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(239, 68, 68, 0.25) 100%) !important;
      border-color: rgba(220, 38, 38, 0.4) !important;
      box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2) !important;
      transform: translateY(-1px) !important;
    }
    
    /* Override Bootstrap default badge styles */
    #searchResultsBody .badge.bg-dark,
    #searchResultsBody .badge.bg-primary,
    #searchResultsBody .badge.text-white {
      background-color: transparent !important;
      color: inherit !important;
    }
    
    /* Enhanced table styling */
    #searchResultsBody .table {
      border-collapse: separate;
      border-spacing: 0;
    }
    
    #searchResultsBody .table thead th {
      background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
      border-bottom: 2px solid #dee2e6;
      font-weight: 600;
      color: #495057;
      padding: 1rem 0.75rem;
      font-size: 0.9375rem;
      letter-spacing: 0.3px;
      text-transform: uppercase;
    }
    
    #searchResultsBody .table tbody tr {
      transition: all 0.15s ease;
      border-bottom: 1px solid #f0f0f0;
    }
    
    #searchResultsBody .table tbody tr:hover {
      background-color: rgba(128, 0, 0, 0.02);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }
    
    #searchResultsBody .table tbody td {
      padding: 1rem 0.75rem;
      vertical-align: middle;
    }
    
    /* Additional polish for badge containers */
    #searchResultsBody .table tbody td:nth-child(2),
    #searchResultsBody .table tbody td:nth-child(3) {
      text-align: center;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/img/evsu-logo.png" alt="EVSU Logo" width="40" height="40" class="me-2">
      <span>Eastern Visayas State University</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav gap-3 align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="#top">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item">
          <a class="btn btn-maroon rounded-pill px-4 d-flex align-items-center gap-2"
             href="../views/auth/login_admin.php">
            <span class="bi bi-person-circle"></span> Staff Login
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Verification Messages -->
<?php if ($message || $error): ?>
<div class="position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 80px; width: 90%; max-width: 600px;">
  <?php if ($message === 'verification_success'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-lg" role="alert">
      <i class="bi bi-check-circle me-2"></i>
      <strong>Email Verified Successfully!</strong> Your account has been activated. You can now log in to the system.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif ($message === 'already_verified'): ?>
    <div class="alert alert-info alert-dismissible fade show shadow-lg" role="alert">
      <i class="bi bi-info-circle me-2"></i>
      <strong>Account Already Verified!</strong> Your account is already active. You can log in to the system.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif ($error === 'invalid_token'): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-lg" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <strong>Invalid Verification!</strong> The verification link is invalid or has expired. Please use the OTP verification system or contact the administrator.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif ($error === 'verification_failed'): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-lg" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <strong>Verification Failed!</strong> There was an error verifying your account. Please try again or contact the administrator.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Hero Section -->
<header id="top" class="hero-section d-flex align-items-center" style="position:relative; min-height:100vh;">
  <div style="
    position:absolute;
    inset:0;
    background: url('assets/img/image.png') no-repeat center center/cover;
    z-index:1;
    opacity:1;
  "></div>
  <div style="
    position:absolute;
    inset:0;
    background: rgba(155, 138, 138, 0.6);
    z-index:2;
  "></div>
  <div class="container text-center" style="position:relative; bottom:50px; z-index:3;">
    <img src="assets/img/evsu-logo.png" alt="Hero Image"  style="max-width: 220px; width: 100%; height: auto; position:relative; bottom:30px; " >
    <h1 class="display-3 fw-bold mb-3 text-white">Welcome to <span class="text-maroon">ClassSync</span></h1>
    <p class="lead mb-4 text-white">Streamlining Class Scheduling for Eastern Visayas State University - Ormoc City Campus</p>
    <a href="#about" class="btn btn-maroon btn-lg px-5 shadow mb-5">Learn More</a>
    <!-- Search Bar -->
    <form id="scheduleSearchForm" class="row justify-content-center g-2">
      <div class="col-12 col-md-6">
        <div class="input-group shadow-sm">
          <input type="text" id="scheduleSearchInput" class="form-control" placeholder="Search schedules..." autocomplete="off">
          <button id="clearSearchBtn" class="btn btn-light border" type="button" style="display: none; background-color: #f8f9fa !important;" title="Clear search">
            <span class="bi bi-x-circle"></span>
          </button>
          <button id="searchTypeDropdown" class="btn btn-maroon dropdown-toggle" type="button" data-bs-toggle="dropdown" data-selected-type="all">
            <span class="bi bi-funnel"></span> All
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" data-type="all">All</a></li>
            <li><a class="dropdown-item" href="#" data-type="instructor">Instructor's Schedule</a></li>
            <li><a class="dropdown-item" href="#" data-type="class">Class Schedule</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-type="department">Department</a></li>
            <li><a class="dropdown-item" href="#" data-type="section">Section/Class</a></li>
            <li><a class="dropdown-item" href="#" data-type="program">Course/Program</a></li>
          </ul>
        </div>
      </div>
      <div class="col-12 col-md-auto">
        <button type="submit" class="btn btn-maroon rounded-pill px-4">
          <span class="bi bi-search"></span> Search
        </button>
      </div>
    </form>
  </div>
</header>

<!-- About -->
<section id="about" class="bg-white py-5" style="display: none;">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold mb-4 text-maroon">About ClassSync</h2>
      <p class="lead text-muted mx-auto" style="max-width: 800px;">
        ClassSync is a comprehensive scheduling management system designed specifically for 
        Eastern Visayas State University - Ormoc City Campus. Our platform streamlines 
        academic operations and enhances the efficiency of class scheduling across all departments.
      </p>
    </div>
    
    <div class="row g-4 mt-4">
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <i class="bi bi-calendar-week text-maroon" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-3">Schedule Management</h5>
            <p class="text-muted mb-0">
              Create, manage, and track class schedules with multi-day support, 
              automatic conflict detection, and workload monitoring for instructors.
            </p>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <i class="bi bi-people text-maroon" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-3">User Management</h5>
            <p class="text-muted mb-0">
              Comprehensive user administration with role-based access control, 
              permission management, and department-scoped data access.
            </p>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <i class="bi bi-building text-maroon" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-3">Room Management</h5>
            <p class="text-muted mb-0">
              Efficient room allocation with conflict detection, cross-department 
              room requests, and building management capabilities.
            </p>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <i class="bi bi-shield-check text-maroon" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-3">Security & Permissions</h5>
            <p class="text-muted mb-0">
              Advanced security features including CSRF protection, audit logging, 
              and granular permission system for different user roles.
            </p>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <i class="bi bi-search text-maroon" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-3">Public Schedule Search</h5>
            <p class="text-muted mb-0">
              Easy-to-use public search functionality allowing students and visitors 
              to find schedules by instructor, class, department, section, or program.
            </p>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <i class="bi bi-bar-chart text-maroon" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-3">Reports & Analytics</h5>
            <p class="text-muted mb-0">
              Comprehensive reporting system with dashboard statistics, audit logs, 
              and workload analysis for better decision-making.
            </p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-5">
      <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm bg-light">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-3 text-maroon">Key Features</h5>
            <div class="row">
              <div class="col-md-6">
                <ul class="list-unstyled">
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Multi-role access (Admin Support, Admin, Moderator, Instructor)</li>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Workload conflict detection</li>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Room availability checking</li>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>OTP-based email verification</li>
                </ul>
              </div>
              <div class="col-md-6">
                <ul class="list-unstyled">
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Academic period management</li>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Department-scoped data access</li>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Real-time permission updates</li>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Comprehensive audit trail</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Contact -->
<section id="contact" class="bg-light py-5" style="display: none;">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold mb-4 text-maroon">Contact Us</h2>
    </div>
    
    <div class="row g-4">
      <!-- Vision, Mission, Core Values -->
      <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <h4 class="fw-bold text-maroon mb-4 text-center">Eastern Visayas State University</h4>
            
            <div class="mb-4">
              <h5 class="fw-bold text-maroon mb-3">
                <i class="bi bi-eye-fill me-2"></i>VISION
              </h5>
              <p class="fs-5 text-dark mb-0">
                A premier institution of learning in the ASEAN region by 2040.
              </p>
            </div>
            
            <hr class="my-4">
            
            <div class="mb-4">
              <h5 class="fw-bold text-maroon mb-3">
                <i class="bi bi-bullseye me-2"></i>MISSION
              </h5>
              <p class="fs-5 text-dark mb-0">
                Develop Competent and Productive Professionals with Positive Values for Sustainable Development.
              </p>
            </div>
            
            <hr class="my-4">
            
            <div class="mb-4">
              <h5 class="fw-bold text-maroon mb-3">
                <i class="bi bi-star-fill me-2"></i>CORE VALUES
              </h5>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded">
                    <span class="display-6 fw-bold text-maroon me-3">E</span>
                    <span class="fs-5">Excellence</span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded">
                    <span class="display-6 fw-bold text-maroon me-3">V</span>
                    <span class="fs-5">Virtue</span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded">
                    <span class="display-6 fw-bold text-maroon me-3">S</span>
                    <span class="fs-5">Service</span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center p-3 bg-light rounded">
                    <span class="display-6 fw-bold text-maroon me-3">U</span>
                    <span class="fs-5">Unity</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Contact Information -->
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4 text-center">
            <h5 class="fw-bold text-maroon mb-3">
              <i class="bi bi-envelope-fill me-2"></i>Get in Touch
            </h5>
            <p class="fs-5 mb-3">
              For inquiries about ClassSync, please contact us at:
            </p>
            <p class="mb-0">
              <a href="mailto:evsu_classsync@classsync.site" class="btn btn-maroon btn-lg">
                <i class="bi bi-envelope me-2"></i>evsu_classsync@classsync.site
              </a>
            </p>
            <p class="text-muted mt-3 mb-0">
              <i class="bi bi-geo-alt-fill me-2"></i>Eastern Visayas State University - Ormoc City Campus
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Search Results Section -->
<section id="search-results" class="bg-white py-5" style="display: none;">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="fw-bold mb-3 text-maroon">
        <i class="bi bi-calendar-check me-2"></i>Search Results
      </h2>
      <div id="searchResultsCount" class="text-muted mb-3"></div>
    </div>
    
    <!-- Loading Spinner -->
    <div id="searchLoadingSpinner" class="text-center py-5" style="display: none;">
      <div class="spinner-border text-maroon" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-3 text-muted">Searching schedules...</p>
    </div>
    
    <!-- Results Container -->
    <div id="searchResultsContainer">
      <div id="searchResultsBody">
        <!-- Results will be displayed here -->
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <p class="mb-0">&copy; 2025 ClassSync | Eastern Visayas State University - Ormoc City Campus</p>
  </div>
</footer>

<!-- jQuery (if needed for any functionality) -->
<script src="/assets/js/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script src="/assets/js/schedule_search.js"></script>
<script src="/assets/js/navigation.js"></script>
</body>
</html>