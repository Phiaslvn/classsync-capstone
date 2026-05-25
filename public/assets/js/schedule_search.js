/**
 * Schedule Search Functionality
 * Handles public schedule search for visitors/students
 */

// Initialize search functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('scheduleSearchForm');
    const searchInput = document.getElementById('scheduleSearchInput');
    const searchTypeDropdown = document.getElementById('searchTypeDropdown');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    
    // Handle form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Handle dropdown item clicks
    if (searchTypeDropdown) {
        const dropdownItems = searchTypeDropdown.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const type = this.getAttribute('data-type');
                const text = this.textContent.trim();
                
                // Update dropdown button text
                const dropdownButton = searchTypeDropdown.querySelector('.dropdown-toggle');
                if (dropdownButton) {
                    dropdownButton.innerHTML = `<span class="bi bi-funnel"></span> ${text}`;
                }
                
                // Store selected type
                searchTypeDropdown.setAttribute('data-selected-type', type);
            });
        });
    }
    
    // Handle clear button click
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearSearch();
        });
    }
    
    // Show/hide clear button based on input value
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            toggleClearButton();
        });
        
        // Allow Enter key to trigger search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }
    
    // Initial check for clear button visibility
    toggleClearButton();
});

/**
 * Performs the schedule search
 */
function performSearch() {
    const searchInput = document.getElementById('scheduleSearchInput');
    const searchTypeDropdown = document.getElementById('searchTypeDropdown');
    const resultsSection = document.getElementById('search-results');
    const resultsContainer = document.getElementById('searchResultsContainer');
    const resultsCount = document.getElementById('searchResultsCount');
    const loadingSpinner = document.getElementById('searchLoadingSpinner');
    const resultsBody = document.getElementById('searchResultsBody');
    
    if (!searchInput || !resultsSection) return;
    
    const query = searchInput.value.trim();
    const searchType = searchTypeDropdown?.getAttribute('data-selected-type') || 'all';
    
    // Validate query
    if (!query) {
        alert('Please enter a search term');
        return;
    }
    
    // Show section and loading state
    if (resultsSection) {
        resultsSection.style.display = 'block';
    }
    if (loadingSpinner) loadingSpinner.style.display = 'block';
    if (resultsBody) resultsBody.innerHTML = '';
    if (resultsCount) resultsCount.textContent = 'Searching...';
    
    // Show clear button if there's a query
    toggleClearButton();
    
    // Build API URL (relative to public folder)
    let apiUrl = `api/search_schedules.php?q=${encodeURIComponent(query)}&type=${encodeURIComponent(searchType)}`;
    
    // Add specific search parameters based on search type
    if (searchType === 'instructor' && query) {
        apiUrl += `&instructor=${encodeURIComponent(query)}`;
    } else if (searchType === 'program' && query) {
        apiUrl += `&program=${encodeURIComponent(query)}`;
    } else if (searchType === 'department' && query) {
        apiUrl += `&department=${encodeURIComponent(query)}`;
    } else if (searchType === 'section' && query) {
        apiUrl += `&section=${encodeURIComponent(query)}`;
    }
    
    // Fetch results
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            
            if (data.success && data.data) {
                displaySearchResults(data.data, query);
                if (resultsCount) {
                    resultsCount.textContent = `Found ${data.count || data.data.length} result(s)`;
                }
            } else {
                showNoResults(query);
                if (resultsCount) {
                    resultsCount.textContent = 'No results found';
                }
            }
            
            // Scroll to results section
            scrollToResults();
        })
        .catch(error => {
            console.error('Search error:', error);
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            showError('An error occurred while searching. Please try again.');
            if (resultsCount) {
                resultsCount.textContent = 'Error occurred';
            }
            scrollToResults();
        });
}

/**
 * Scrolls to the search results section smoothly
 */
function scrollToResults() {
    const resultsSection = document.getElementById('search-results');
    if (resultsSection) {
        // Account for fixed navbar
        const navbarHeight = 70; // Adjust based on your navbar height
        const sectionPosition = resultsSection.offsetTop - navbarHeight;
        
        window.scrollTo({
            top: sectionPosition,
            behavior: 'smooth'
        });
    }
}

/**
 * Displays search results in the section
 */
function displaySearchResults(schedules, query) {
    const resultsBody = document.getElementById('searchResultsBody');
    
    if (!resultsBody) return;
    
    if (schedules.length === 0) {
        showNoResults(query);
        return;
    }
    
    // Group schedules by instructor
    const groupedSchedules = groupSchedules(schedules);
    
    let html = '';
    
    // Display grouped results
    Object.keys(groupedSchedules).forEach(groupKey => {
        const groupSchedules = groupedSchedules[groupKey];
        const firstSchedule = groupSchedules[0];
        
        html += `
            <div class="schedule-group mb-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-maroon text-white">
                        <h5 class="mb-0 fw-bold" style="color: #ffffff !important;">
                            <i class="bi bi-person-circle me-2"></i>${escapeHtml(firstSchedule.instructor_name)}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="text-white fw-bold">Subject</th>
                                        <th class="text-white fw-bold">Day</th>
                                        <th class="text-white fw-bold">Time</th>
                                        <th class="text-white fw-bold">Room</th>
                                        <th class="text-white fw-bold">Section</th>
                                        <th class="text-white fw-bold">Program</th>
                                        <th class="text-white fw-bold">Department</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;
        
        groupSchedules.forEach(schedule => {
            html += `
                <tr>
                    <td>
                        <div class="fw-bold" style="color: #212529;">${escapeHtml(schedule.subj_code)}</div>
                        <small style="color: #6c757d;">${escapeHtml(schedule.subj_desc)}</small>
                    </td>
                    <td><span class="badge badge-day fw-bold">${escapeHtml(schedule.schd_day)}</span></td>
                    <td>
                        <span class="badge badge-time fw-bold">${escapeHtml(schedule.start_time)} - ${escapeHtml(schedule.end_time)}</span>
                    </td>
                    <td>
                        <div class="fw-bold" style="color: #212529;">${escapeHtml(schedule.rm_name)}</div>
                        <small style="color: #6c757d;">${escapeHtml(schedule.bd_desc)}</small>
                    </td>
                    <td class="fw-semibold" style="color: #212529;">${escapeHtml(schedule.sec_name || 'N/A')}</td>
                    <td>
                        <div class="fw-bold" style="color: #212529;">${escapeHtml(schedule.program_code || '')}</div>
                        <small style="color: #6c757d;">${escapeHtml(schedule.program_name || '')}</small>
                    </td>
                    <td>
                        <div class="fw-bold" style="color: #212529;">${escapeHtml(schedule.dept_code || '')}</div>
                        <small style="color: #6c757d;">${escapeHtml(schedule.dept_name || 'N/A')}</small>
                    </td>
                </tr>
            `;
        });
        
        html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    resultsBody.innerHTML = html;
}

/**
 * Groups schedules by instructor
 */
function groupSchedules(schedules) {
    const grouped = {};
    
    schedules.forEach(schedule => {
        const key = schedule.instructor_name || 'Unknown';
        if (!grouped[key]) {
            grouped[key] = [];
        }
        grouped[key].push(schedule);
    });
    
    // Sort each group by day and time
    Object.keys(grouped).forEach(key => {
        grouped[key].sort((a, b) => {
            const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const dayDiff = dayOrder.indexOf(a.schd_day) - dayOrder.indexOf(b.schd_day);
            if (dayDiff !== 0) return dayDiff;
            return a.schd_start.localeCompare(b.schd_start);
        });
    });
    
    return grouped;
}

/**
 * Shows no results message
 */
function showNoResults(query) {
    const resultsBody = document.getElementById('searchResultsBody');
    if (!resultsBody) return;
    
    resultsBody.innerHTML = `
        <div class="text-center py-5">
            <i class="bi bi-search display-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No schedules found</h5>
            <p class="text-muted">No schedules match your search "${escapeHtml(query)}".</p>
            <p class="text-muted small">Try searching by instructor name, subject code, or program name.</p>
        </div>
    `;
}

/**
 * Shows error message
 */
function showError(message) {
    const resultsBody = document.getElementById('searchResultsBody');
    if (!resultsBody) return;
    
    resultsBody.innerHTML = `
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${escapeHtml(message)}
        </div>
    `;
}

/**
 * Clears the search input and hides results
 */
function clearSearch() {
    const searchInput = document.getElementById('scheduleSearchInput');
    const resultsSection = document.getElementById('search-results');
    const resultsBody = document.getElementById('searchResultsBody');
    const resultsCount = document.getElementById('searchResultsCount');
    const loadingSpinner = document.getElementById('searchLoadingSpinner');
    
    // Clear input
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Hide results section
    if (resultsSection) {
        resultsSection.style.display = 'none';
    }
    
    // Clear results content
    if (resultsBody) {
        resultsBody.innerHTML = '';
    }
    
    if (resultsCount) {
        resultsCount.textContent = '';
    }
    
    if (loadingSpinner) {
        loadingSpinner.style.display = 'none';
    }
    
    // Hide clear button
    toggleClearButton();
    
    // Reset search type to "All"
    const searchTypeDropdown = document.getElementById('searchTypeDropdown');
    if (searchTypeDropdown) {
        searchTypeDropdown.setAttribute('data-selected-type', 'all');
        const dropdownButton = searchTypeDropdown.querySelector('.dropdown-toggle');
        if (dropdownButton) {
            dropdownButton.innerHTML = '<span class="bi bi-funnel"></span> All';
        }
    }
    
    // Focus back on input
    if (searchInput) {
        searchInput.focus();
    }
}

/**
 * Toggles the visibility of the clear button based on input value
 */
function toggleClearButton() {
    const searchInput = document.getElementById('scheduleSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    
    if (searchInput && clearSearchBtn) {
        if (searchInput.value.trim().length > 0) {
            clearSearchBtn.style.display = 'block';
        } else {
            clearSearchBtn.style.display = 'none';
        }
    }
}

/**
 * Escapes HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
