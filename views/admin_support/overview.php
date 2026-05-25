<?php
// ✅ Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/database.php';

// ✅ Fetch logged-in user
$query = "
    SELECT a.fname, a.lname, r.role_name
    FROM account a
    JOIN user_roles ur ON a.acc_id = ur.acc_id
    JOIN roles r ON ur.role_id = r.id
    WHERE a.acc_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['acc_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$fname = $user ? $user['fname'] : "Unknown";
$lname = $user ? $user['lname'] : "";
$role  = $user ? $user['role_name'] : "Unknown";
$username = $fname . " " . $lname;

// ✅ Roles you want to display
$roles = ['Admin support', 'Admin', 'Moderator', 'User'];
$counts = [];

// ✅ Count users per role
foreach ($roles as $r) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM account a
        JOIN user_roles ur ON a.acc_id = ur.acc_id
        JOIN roles r ON ur.role_id = r.id
        WHERE r.role_name = ? AND a.acc_status = 'Active'
    ");
    $stmt->bind_param("s", $r);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $counts[$r] = $row['total'];
    $stmt->close();
}

// ✅ Get schedule counts by department and time period
// Map display names to department names in database
$deptMapping = [
    'Computer Studies' => 'Computer Studies Department',
    'Education' => 'Teacher Education Department',
    'Technology' => 'Industrial Technology Department'
];

$deptStats = [];

foreach ($deptMapping as $displayName => $deptName) {
    // Get department ID
    $deptStmt = $conn->prepare("SELECT dept_id FROM department WHERE dept_name = ? AND dept_status = 'Active' LIMIT 1");
    $deptStmt->bind_param("s", $deptName);
    $deptStmt->execute();
    $deptResult = $deptStmt->get_result();
    $deptRow = $deptResult->fetch_assoc();
    $deptStmt->close();
    
    if (!$deptRow) {
        // If department not found, set all counts to 0
        $deptStats[$displayName] = ['morning' => 0, 'afternoon' => 0, 'evening' => 0];
        continue;
    }
    
    $deptId = $deptRow['dept_id'];
    
    // Count Morning schedules (7:00 AM - 12:00 PM)
    $morningStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedule s
        WHERE s.dept_id = ? 
        AND s.schd_status = 'Active'
        AND TIME(s.schd_start) >= '07:00:00'
        AND TIME(s.schd_start) < '12:00:00'
    ");
    $morningStmt->bind_param("i", $deptId);
    $morningStmt->execute();
    $morningResult = $morningStmt->get_result();
    $morningRow = $morningResult->fetch_assoc();
    $morning = $morningRow['count'] ?? 0;
    $morningStmt->close();
    
    // Count Afternoon schedules (12:00 PM - 5:00 PM)
    $afternoonStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedule s
        WHERE s.dept_id = ? 
        AND s.schd_status = 'Active'
        AND TIME(s.schd_start) >= '12:00:00'
        AND TIME(s.schd_start) < '17:00:00'
    ");
    $afternoonStmt->bind_param("i", $deptId);
    $afternoonStmt->execute();
    $afternoonResult = $afternoonStmt->get_result();
    $afternoonRow = $afternoonResult->fetch_assoc();
    $afternoon = $afternoonRow['count'] ?? 0;
    $afternoonStmt->close();
    
    // Count Evening schedules (5:00 PM - 8:30 PM)
    $eveningStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedule s
        WHERE s.dept_id = ? 
        AND s.schd_status = 'Active'
        AND TIME(s.schd_start) >= '17:00:00'
        AND TIME(s.schd_start) <= '20:30:00'
    ");
    $eveningStmt->bind_param("i", $deptId);
    $eveningStmt->execute();
    $eveningResult = $eveningStmt->get_result();
    $eveningRow = $eveningResult->fetch_assoc();
    $evening = $eveningRow['count'] ?? 0;
    $eveningStmt->close();
    
    $deptStats[$displayName] = [
        'morning' => $morning,
        'afternoon' => $afternoon,
        'evening' => $evening
    ];
}
?>

<!-- Overview Dashboard Content -->
<div class="row mb-4 as-support-welcome">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h3 class="mb-1">Welcome back, <?php echo htmlspecialchars($fname); ?>!</h3>
                <p class="mb-0 as-welcome-sub">Here’s your system administration overview.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-box as-support-search position-relative">
                    <i class="bi bi-search position-absolute" style="top: 50%; transform: translateY(-50%);"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search users, departments, schedules..." style="width: 250px; max-width: 100%;">
                    <div id="searchResults" class="search-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e9ecef; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000; max-height: 300px; overflow-y: auto;"></div>
                </div>
                <div class="user-profile d-flex align-items-center" style="cursor: pointer;" onclick="toggleUserMenu()">
                    <div class="profile-pic" style="width: 35px; height: 35px; background: linear-gradient(135deg, #800000, #5a0000); border-radius: 50%; display: flex; align-items: center; justify-content-center; color: white; font-weight: bold; margin-right: 8px; font-size: 12px;">
                        <?php echo strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #800000; font-size: 0.9rem;"><?php echo strtoupper($role); ?></div>
                        <div style="font-size: 0.75rem; color: #6c757d;"><?php echo htmlspecialchars($username); ?></div>
                    </div>
                    <i class="bi bi-chevron-down ms-2" style="color: #6c757d;"></i>
                    <div id="userMenu" class="user-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e9ecef; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000; width: 200px;">
                        <div class="p-3 border-bottom">
                            <div class="fw-bold" style="color: #800000;"><?php echo htmlspecialchars($username); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($role); ?></small>
                        </div>
                        <div class="user-menu-item p-3" style="cursor: pointer; border-bottom: 1px solid #f8f9fa;" onclick="showProfileModal()">
                            <i class="bi bi-person me-2"></i>View Profile
                        </div>
                        <div class="user-menu-item p-3" style="cursor: pointer; border-bottom: 1px solid #f8f9fa;" onclick="showSettingsModal()">
                            <i class="bi bi-gear me-2"></i>Settings
                        </div>
                        <div class="user-menu-item p-3" style="cursor: pointer;" onclick="logout()">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </div>
                    </div>
                </div>
            </div>
      </div>
    </div>
</div>

<!-- Department Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="stats-card as-dept-card" onclick="showDepartmentDetails('Computer Studies')">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h6 class="mb-1">Computer Studies</h6>
                    <div class="d-flex align-items-center text-muted small">
                        <i class="bi bi-building me-2"></i>
                        <span>Department</span>
                    </div>
                </div>
                <i class="bi bi-arrow-right"></i>
            </div>
            <div class="dept-stats">
                <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-sun me-2" style="font-size: 0.875rem; color: #6c757d;"></i>
                        <span style="font-size: 0.875rem; color: #6c757d;">Morning</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Computer Studies']['morning'] ?? 0; ?></span>
                </div>
                <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-cloud me-2" style="font-size: 0.875rem; color: #6c757d;"></i>
                        <span style="font-size: 0.875rem; color: #6c757d;">Afternoon</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Computer Studies']['afternoon'] ?? 0; ?></span>
                </div>
                <div class="stat-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-moon me-2" style="font-size: 0.875rem; color: #6c757d;"></i>
                        <span style="font-size: 0.875rem; color: #6c757d;">Evening</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Computer Studies']['evening'] ?? 0; ?></span>
                </div>
            </div>
      </div>
    </div>
    
    
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="stats-card as-dept-card" onclick="showDepartmentDetails('Education')">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h6 class="mb-1">Education</h6>
                    <div class="d-flex align-items-center text-muted small">
                        <i class="bi bi-clock me-2"></i>
                        <span>Department</span>
                    </div>
                </div>
                <i class="bi bi-arrow-right"></i>
            </div>
            <div class="dept-stats">
                <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-sun me-2" style="color: #ffc107; font-size: 0.875rem;"></i>
                        <span style="color: #6c757d; font-size: 0.875rem;">Morning</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Education']['morning'] ?? 0; ?></span>
                </div>
                <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-cloud me-2" style="color: #6c757d; font-size: 0.875rem;"></i>
                        <span style="color: #6c757d; font-size: 0.875rem;">Afternoon</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Education']['afternoon'] ?? 0; ?></span>
                </div>
                <div class="stat-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-moon me-2" style="color: #6c757d; font-size: 0.875rem;"></i>
                        <span style="color: #6c757d; font-size: 0.875rem;">Evening</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Education']['evening'] ?? 0; ?></span>
                </div>
            </div>
      </div>
    </div>
    
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="stats-card as-dept-card" onclick="showDepartmentDetails('Technology')">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h6 class="mb-1">Technology</h6>
                    <div class="d-flex align-items-center text-muted small">
                        <i class="bi bi-building me-2"></i>
                        <span>Department</span>
                    </div>
                </div>
                <i class="bi bi-arrow-right"></i>
            </div>
            <div class="dept-stats">
                <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-sun me-2" style="font-size: 0.875rem; color: #6c757d;"></i>
                        <span style="font-size: 0.875rem; color: #6c757d;">Morning</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Technology']['morning'] ?? 0; ?></span>
                </div>
                <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-cloud me-2" style="font-size: 0.875rem; color: #6c757d;"></i>
                        <span style="font-size: 0.875rem; color: #6c757d;">Afternoon</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Technology']['afternoon'] ?? 0; ?></span>
                </div>
                <div class="stat-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-moon me-2" style="font-size: 0.875rem; color: #6c757d;"></i>
                        <span style="font-size: 0.875rem; color: #6c757d;">Evening</span>
                    </div>
                    <span class="stat-number"><?php echo $deptStats['Technology']['evening'] ?? 0; ?></span>
                </div>
            </div>
      </div>
    </div>
  </div>

<!-- Charts Row -->
<div class="row">
    <!-- Weekly Schedule Chart -->
    <div class="col-lg-8 mb-4">
        <div class="dashboard-card" style="min-height: 400px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0" style="color: #495057; font-weight: 600; font-size: 1.125rem;">Weekly Schedule</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="exportSelect" style="border-radius: 20px; border: 1px solid #e9ecef; font-size: 0.8rem; padding: 0.25rem 0.5rem;" onchange="exportChartData()">
                        <option value="">Export data</option>
                        <option value="pdf">Export as PDF</option>
                        <option value="excel">Export as Excel</option>
                        <option value="csv">Export as CSV</option>
                    </select>
                    <select class="form-select form-select-sm" id="timeRangeSelect" style="border-radius: 20px; border: 1px solid #e9ecef; font-size: 0.8rem; padding: 0.25rem 0.5rem;" onchange="updateChartTimeRange()">
                        <option value="7">Last 7 Days</option>
                        <option value="14">Last 14 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 3 Months</option>
                    </select>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- EVSU-OCC Departments Chart -->
    <div class="col-lg-4 mb-4">
        <div class="dashboard-card" style="min-height: 400px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0" style="color: #495057; font-weight: 600; font-size: 1.125rem;">EVSU-OCC Departments</h5>
                <i class="bi bi-arrow-right" style="color: #6c757d; font-size: 0.875rem; cursor: pointer;" onclick="showDepartmentBreakdown()"></i>
            </div>
            <div class="text-center">
                <div style="height: 200px; position: relative;">
                    <canvas id="departmentsChart"></canvas>
                </div>
                <div class="mt-3">
                    <div class="legend-item d-flex align-items-center justify-content-center mb-2">
                        <div style="width: 10px; height: 10px; background: #800000; border-radius: 2px; margin-right: 6px;"></div>
                        <span style="font-size: 0.875rem; color: #6c757d;">Computer Studies</span>
                    </div>
                    <div class="legend-item d-flex align-items-center justify-content-center mb-2">
                        <div style="width: 10px; height: 10px; background: #4a90e2; border-radius: 2px; margin-right: 6px;"></div>
                        <span style="font-size: 0.875rem; color: #6c757d;">Education</span>
                    </div>
                    <div class="legend-item d-flex align-items-center justify-content-center">
                        <div style="width: 10px; height: 10px; background: #7bb3f0; border-radius: 2px; margin-right: 6px;"></div>
                        <span style="font-size: 0.875rem; color: #6c757d;">Technology</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/chart.min.js"></script>
<script>
// Chart.js configurations
document.addEventListener('DOMContentLoaded', function() {
    // Weekly Schedule Chart
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Computer Studies',
                data: [<?php echo $counts['Admin support']; ?>, <?php echo $counts['Admin']; ?>, <?php echo $counts['Moderator']; ?>, <?php echo $counts['User']; ?>, <?php echo $counts['Admin support'] + 2; ?>, <?php echo $counts['Admin'] + 1; ?>, <?php echo $counts['Moderator'] + 3; ?>],
                borderColor: '#800000',
                backgroundColor: 'rgba(128, 0, 0, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Education',
                data: [15, 12, 18, 14, 16, 10, 13],
                borderColor: '#4a90e2',
                backgroundColor: 'rgba(74, 144, 226, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Technology',
                data: [9, 11, 8, 12, 10, 7, 9],
                borderColor: '#7bb3f0',
                backgroundColor: 'rgba(123, 179, 240, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                point: {
                    radius: 0
                }
            }
        }
    });

    // Departments Chart
    const departmentsCtx = document.getElementById('departmentsChart').getContext('2d');
    new Chart(departmentsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Computer Studies', 'Education', 'Technology'],
            datasets: [{
                data: [<?php echo $counts['Admin support'] + $counts['Admin'] + $counts['Moderator'] + $counts['User']; ?>, 15, 9],
                backgroundColor: ['#800000', '#4a90e2', '#7bb3f0'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '70%'
        }
    });
});

// Hover effects for cards
document.addEventListener('DOMContentLoaded', function() {
    const deptCards = document.querySelectorAll('.dept-card');
    deptCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });

    const chartCards = document.querySelectorAll('.chart-card');
    chartCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 5px 20px rgba(0,0,0,0.1)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });
});

// Interactive functionality
let weeklyChart, departmentsChart;

// Search functionality with real API
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    const query = this.value.trim();
    const resultsContainer = document.getElementById('searchResults');
    
    // Clear previous timeout
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }
    
    // Show loading state
    resultsContainer.innerHTML = '<div class="p-3 text-center"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>';
    resultsContainer.style.display = 'block';
    
    // Debounce search requests
    searchTimeout = setTimeout(() => {
        performSearch(query, resultsContainer);
    }, 300);
});

// Helper function to escape HTML
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function performSearch(query, resultsContainer) {
    // Use correct relative path to search_api.php
    const searchUrl = '../../admin_support/search_api.php?q=' + encodeURIComponent(query) + '&limit=8';
    
    fetch(searchUrl)
        .then(response => {
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Expected JSON but got:', text.substring(0, 200));
                    throw new Error('Server returned non-JSON response');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.results && data.results.length > 0) {
                resultsContainer.innerHTML = data.results.map(result => {
                    return `
                        <div class="search-result-item p-3 border-bottom" style="cursor: pointer;" onclick="handleSearchResult('${escapeHtml(result.type)}', '${escapeHtml(result.id)}', '${escapeHtml(result.title)}')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-${escapeHtml(result.icon || 'circle')} me-2" style="color: #800000;"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold" style="color: #333;">${escapeHtml(result.title)}</div>
                                    <div class="text-muted" style="font-size: 0.85rem;">${escapeHtml(result.subtitle || '')}</div>
                                    <div class="text-muted" style="font-size: 0.8rem;">${escapeHtml(result.description || '')}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark" style="font-size: 0.7rem;">${escapeHtml(result.type)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                resultsContainer.innerHTML = '<div class="p-3 text-muted text-center"><i class="bi bi-search me-2"></i>No results found for "' + escapeHtml(query) + '"</div>';
            }
            resultsContainer.style.display = 'block';
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsContainer.innerHTML = '<div class="p-3 text-danger text-center"><i class="bi bi-exclamation-triangle me-2"></i>Search error occurred. Please try again.</div>';
            resultsContainer.style.display = 'block';
        });
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-box')) {
        document.getElementById('searchResults').style.display = 'none';
    }
});

// Keyboard navigation for search
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    const resultsContainer = document.getElementById('searchResults');
    const resultItems = resultsContainer.querySelectorAll('.search-result-item');
    
    if (resultsContainer.style.display === 'none' || resultItems.length === 0) {
        return;
    }
    
    let currentIndex = -1;
    resultItems.forEach((item, index) => {
        if (item.classList.contains('active')) {
            currentIndex = index;
        }
    });
    
    switch(e.key) {
        case 'ArrowDown':
            e.preventDefault();
            if (currentIndex < resultItems.length - 1) {
                if (currentIndex >= 0) {
                    resultItems[currentIndex].classList.remove('active');
                }
                currentIndex++;
                resultItems[currentIndex].classList.add('active');
                resultItems[currentIndex].scrollIntoView({ block: 'nearest' });
            }
            break;
        case 'ArrowUp':
            e.preventDefault();
            if (currentIndex > 0) {
                if (currentIndex >= 0) {
                    resultItems[currentIndex].classList.remove('active');
                }
                currentIndex--;
                resultItems[currentIndex].classList.add('active');
                resultItems[currentIndex].scrollIntoView({ block: 'nearest' });
            }
            break;
        case 'Enter':
            e.preventDefault();
            if (currentIndex >= 0 && resultItems[currentIndex]) {
                resultItems[currentIndex].click();
            }
            break;
        case 'Escape':
            resultsContainer.style.display = 'none';
            this.blur();
            break;
    }
});

// Notification functionality removed - using header notification system instead

// User menu functionality
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Department details modal
function showDepartmentDetails(department) {
    const modal = `
        <div class="modal fade" id="departmentModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-building me-2"></i>${department} Department Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Statistics</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Total Students:</strong> ${department === 'Computer Studies' ? '150' : department === 'Education' ? '120' : '80'}</li>
                                    <li><strong>Instructors:</strong> ${department === 'Computer Studies' ? '12' : department === 'Education' ? '10' : '8'}</li>
                                    <li><strong>Active Courses:</strong> ${department === 'Computer Studies' ? '25' : department === 'Education' ? '20' : '15'}</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Recent Activity</h6>
                                <div class="activity-item mb-2">
                                    <small class="text-muted">2 hours ago</small>
                                    <div>New student registration</div>
                                </div>
                                <div class="activity-item mb-2">
                                    <small class="text-muted">5 hours ago</small>
                                    <div>Schedule updated for CS101</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Close</button>
                        <button type="button" class="btn btn-maroon"><i class="bi bi-check-lg me-1"></i>View Full Report</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('departmentModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modal);
    
    // Show modal
    const modalElement = new bootstrap.Modal(document.getElementById('departmentModal'));
    modalElement.show();
}

// Chart export functionality
function exportChartData() {
    const format = document.getElementById('exportSelect').value;
    if (!format) return;
    
    // Simulate export
    alert(`Exporting chart data as ${format.toUpperCase()}...`);
    
    // Reset selection
    document.getElementById('exportSelect').value = '';
}

// Chart time range update
function updateChartTimeRange() {
    const days = document.getElementById('timeRangeSelect').value;
    alert(`Updating chart to show last ${days} days...`);
    
    // Here you would update the chart data
    // For now, just show an alert
}

// Department breakdown
function showDepartmentBreakdown() {
    alert('Showing detailed department breakdown...');
}

// Search result handler
function handleSearchResult(type, id, title) {
    const searchInput = document.getElementById('searchInput');
    const resultsContainer = document.getElementById('searchResults');
    
    // Hide search results
    resultsContainer.style.display = 'none';
    searchInput.value = '';
    
    // Handle different result types
    switch(type) {
        case 'user':
            // Navigate to user management or show user details
            showUserDetails(id, title);
            break;
        case 'department':
            // Navigate to department management
            showDepartmentDetails(title);
            break;
        case 'instructor':
            // Navigate to instructor management
            showInstructorDetails(id, title);
            break;
        case 'program':
            // Navigate to program management
            showProgramDetails(id, title);
            break;
        case 'audit':
            // Navigate to audit logs
            showAuditDetails(id, title);
            break;
        default:
            alert(`Selected ${type}: ${title}`);
    }
}

// User details modal
function showUserDetails(userId, userName) {
    // Create and show user details modal
    const modal = `
        <div class="modal fade" id="userDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person me-2"></i>User Details: ${userName}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center">
                            <i class="bi bi-person-circle text-muted" style="font-size: 4rem;"></i>
                            <h6 class="mt-2">${userName}</h6>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>User ID:</strong> ${userId}
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong> <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Close</button>
                        <button type="button" class="btn btn-maroon" onclick="window.location.href='user_management.php?user_id=${userId}'"><i class="bi bi-check-lg me-1"></i>View Full Profile</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('userDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modal);
    
    // Show modal
    const modalElement = new bootstrap.Modal(document.getElementById('userDetailsModal'));
    modalElement.show();
}

// Instructor details
function showInstructorDetails(instructorId, instructorName) {
    alert(`Instructor: ${instructorName} (ID: ${instructorId})`);
    // You can implement instructor details modal here
}

// Program details
function showProgramDetails(programId, programName) {
    alert(`Program: ${programName} (ID: ${programId})`);
    // You can implement program details modal here
}

// Audit details
function showAuditDetails(auditId, auditAction) {
    alert(`Audit Log: ${auditAction} (ID: ${auditId})`);
    // You can implement audit details modal here
}

// User menu functions
function showProfileModal() {
    alert('Opening profile modal...');
    document.getElementById('userMenu').style.display = 'none';
}

function showSettingsModal() {
    alert('Opening settings modal...');
    document.getElementById('userMenu').style.display = 'none';
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
    document.getElementById('userMenu').style.display = 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-profile')) {
        document.getElementById('userMenu').style.display = 'none';
    }
});
</script>

<style>
/* Custom styles for the EVSU-OCC dashboard - Match Admin Dashboard Design */

/* Responsive adjustments */
@media (max-width: 1200px) {
    .stats-card .stat-number {
        font-size: 2rem !important;
    }
    
    .dashboard-card {
        padding: 1.25rem !important;
    }
}

@media (max-width: 991.98px) {
    /* Hide desktop header elements on mobile */
    .d-flex.justify-content-between.align-items-center.mb-4 {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
        margin-bottom: 1rem !important;
    }
    
    .d-flex.align-items-center.gap-3 {
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 0.75rem !important;
    }
    
    .search-box {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .search-box input {
        width: 100% !important;
        max-width: 100% !important;
        font-size: 0.9rem !important;
        padding: 0.5rem 0.75rem 0.5rem 2.5rem !important;
    }
    
    .user-profile {
        align-self: stretch !important;
        width: 100% !important;
        padding: 0.75rem !important;
        border-radius: 8px !important;
        background: #f8f9fa !important;
    }
    
    .user-profile .profile-pic {
        width: 40px !important;
        height: 40px !important;
        font-size: 14px !important;
    }
    
    /* Stack department cards */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .col-lg-4,
    .col-md-6,
    .col-lg-8 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        margin-bottom: 1rem !important;
    }
    
    .stats-card {
        margin-bottom: 1rem !important;
        padding: 1rem !important;
    }
    
    .stats-card .stat-number {
        font-size: 2rem !important;
    }
    
    .stats-card .stat-label {
        font-size: 0.75rem !important;
    }
    
    /* Make charts responsive */
    .col-lg-8,
    .col-lg-4 {
        margin-bottom: 1.5rem !important;
    }
    
    canvas {
        max-width: 100% !important;
        height: auto !important;
    }
    
    /* Adjust welcome message */
    h3 {
        font-size: 1.25rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    p {
        font-size: 0.85rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    /* Responsive form selects */
    .form-select-sm {
        font-size: 0.8rem !important;
        padding: 0.35rem 0.5rem !important;
    }
    
    /* Responsive dashboard cards */
    .dashboard-card {
        padding: 1rem !important;
        margin-bottom: 1rem !important;
    }
    
    .dashboard-card h5 {
        font-size: 1rem !important;
        margin-bottom: 0.75rem !important;
    }
}

@media (max-width: 768px) {
    .dept-card {
        min-height: 180px;
        padding: 1rem !important;
    }
    
    .chart-card {
        min-height: 350px;
        padding: 1rem !important;
    }
    
    .search-box input {
        width: 100% !important;
        font-size: 0.85rem !important;
    }
    
    .user-profile {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
    }
    
    .user-profile > div:last-child {
        display: none !important; /* Hide dropdown arrow on mobile */
    }
    
    /* Make charts smaller but visible */
    #weeklyChart,
    #departmentsChart {
        max-height: 250px !important;
    }
    
    .dashboard-card canvas {
        max-height: 250px !important;
    }
    
    /* Responsive stat items */
    .stat-item {
        padding: 0.5rem 0 !important;
        font-size: 0.85rem !important;
    }
    
    .stat-item .stat-number {
        font-size: 1.25rem !important;
    }
    
    /* Stack chart controls */
    .d-flex.gap-2 {
        flex-direction: column !important;
        gap: 0.5rem !important;
    }
    
    .d-flex.gap-2 .form-select-sm {
        width: 100% !important;
    }
}

@media (max-width: 576px) {
    .dept-card {
        min-height: 160px;
        padding: 0.75rem !important;
    }
    
    .chart-card {
        min-height: 300px;
        padding: 0.75rem !important;
    }
    
    .search-box input {
        width: 100% !important;
        font-size: 0.8rem !important;
        padding: 0.4rem 0.6rem 0.4rem 2.25rem !important;
    }
    
    /* Keep charts visible but smaller */
    #weeklyChart,
    #departmentsChart {
        max-height: 200px !important;
        display: block !important;
    }
    
    .dashboard-card canvas {
        max-height: 200px !important;
    }
    
    /* Responsive welcome section */
    h3 {
        font-size: 1.1rem !important;
    }
    
    p {
        font-size: 0.8rem !important;
    }
    
    /* Smaller stat numbers */
    .stats-card .stat-number {
        font-size: 1.75rem !important;
    }
    
    .stats-card .stat-label {
        font-size: 0.7rem !important;
    }
    
    /* Compact stat items */
    .stat-item {
        padding: 0.4rem 0 !important;
        font-size: 0.8rem !important;
    }
    
    .stat-item .stat-number {
        font-size: 1.1rem !important;
    }
    
    /* Smaller icons */
    .bi {
        font-size: 0.9rem !important;
    }
    
    /* Compact dashboard cards */
    .dashboard-card {
        padding: 0.75rem !important;
    }
    
    .dashboard-card h5 {
        font-size: 0.95rem !important;
    }
}

@media (max-width: 400px) {
    h3 {
        font-size: 1rem !important;
    }
    
    .stats-card .stat-number {
        font-size: 1.5rem !important;
    }
    
    .dashboard-card {
        padding: 0.5rem !important;
    }
    
    #weeklyChart,
    #departmentsChart {
        max-height: 150px !important;
    }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Ensure proper spacing and alignment */
.row {
    margin-left: 0;
    margin-right: 0;
}

.col-lg-3, .col-lg-4, .col-lg-8, .col-md-6 {
    padding-left: 8px;
    padding-right: 8px;
}

/* Fix chart container sizing */
canvas {
    max-width: 100%;
    height: auto;
}

/* Interactive element styles */
.search-results {
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    max-height: 400px;
    overflow-y: auto;
}

.search-result-item:hover,
.search-result-item.active {
    background-color: #f8f9fa;
    transform: translateX(2px);
    transition: all 0.2s ease;
}

.search-result-item.active {
    background-color: #e9ecef;
    border-left: 3px solid #800000;
}

.search-result-item {
    transition: all 0.2s ease;
}

.search-result-item:last-child {
    border-bottom: none !important;
}

/* Search loading animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.bi-hourglass-split {
    animation: spin 1s linear infinite;
}

/* Search result badges */
.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Search input focus */
#searchInput:focus {
    border-color: #800000;
    box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
}

.notification-dropdown {
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.user-menu {
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.user-menu-item:hover {
    background-color: #f8f9fa;
}

/* Button styles */
.btn-maroon {
    background-color: #800000;
    border-color: #800000;
    color: white;
}

.btn-maroon:hover {
    background-color: #660000;
    border-color: #660000;
    color: white;
}

/* Modal styles */
.modal-header {
    border-bottom: 1px solid #dee2e6;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
}

/* Animation for dropdowns */
.notification-dropdown,
.user-menu,
.search-results {
    animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>