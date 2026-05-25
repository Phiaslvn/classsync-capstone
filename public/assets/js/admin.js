/**
 * Admin Dashboard JavaScript
 * Extracted from admin_dashboard.php for better organization
 */

// Dashboard Data Loading Functions
function loadDashboardData() {
  // Check if we're on moderator dashboard - data is already loaded in PHP
  if (window.location.pathname.includes('/views/moderator/')) {
    console.log('🔵 [DEBUG] Moderator dashboard detected - skipping API call (data already loaded in PHP)');
    return; // Data is already loaded in dashboard_overview.php component
  }
  
  // Check if overview tab is visible before loading
  const overviewTab = document.getElementById('overview');
  const overviewTabContent = document.querySelector('#overview.tab-content');
  const isOverviewVisible = (overviewTab && window.getComputedStyle(overviewTab).display !== 'none') ||
                            (overviewTabContent && window.getComputedStyle(overviewTabContent).display !== 'none');
  
  if (!isOverviewVisible) {
    console.log('⚠️ Overview tab not visible - skipping dashboard data load');
    return; // Don't load if overview tab is not visible
  }
  
  console.log('Loading dashboard data...');
  
  // Determine correct path based on current location
  // Admin dashboard uses API endpoint
  let dashboardPath;
  if (window.location.pathname.includes('/views/admin/')) {
    dashboardPath = '../../admin/reports/get_dashboard_data.php';
  } else {
    dashboardPath = 'get_dashboard_data.php';
  }
  
  console.log('🔵 [DEBUG] Dashboard path:', dashboardPath);
  
  fetch(dashboardPath)
    .then(response => {
      console.log('Dashboard response status:', response.status);
      if (!response.ok) {
        if (response.status === 403) {
          // Check if it's a permission error or authentication error
          return response.json().then(data => {
            if (data.message && data.message.includes('permission')) {
              console.warn('⚠️ Permission denied - user does not have required permissions');
              // Don't throw error - just return empty data
              return { success: false, message: data.message, permissionError: true };
            }
            throw new Error('Authentication required - please log in again');
          }).catch(() => {
            throw new Error('Authentication required - please log in again');
          });
        }
        if (response.status === 404) {
          console.error('❌ Dashboard endpoint not found:', dashboardPath);
          throw new Error(`Dashboard endpoint not found. Please check the file path.`);
        }
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Dashboard data loaded:', data);
      
      // Handle permission errors silently
      if (data.permissionError) {
        console.warn('⚠️ Permission error - suppressing error message');
        // Don't show error or try fallback - user doesn't have permission
        return;
      }
      
      if (data.success) {
        updateDashboardStatistics(data.data);
        // Only load accounts if users tab is visible (check permission)
        const usersTabLink = document.querySelector('a[onclick*="roles"], a[href="#roles"]');
        if (usersTabLink && usersTabLink.offsetParent !== null) {
          loadAccounts();
        }
      } else {
        console.error('Dashboard data error:', data.message);
        // Only show error if overview is still visible
        if (isOverviewVisible) {
          showDashboardError('Dashboard Error: ' + (data.message || 'Unknown error'));
          // Try to load basic stats as fallback
          loadBasicStats();
        }
      }
    })
    .catch(error => {
      console.error('Dashboard data fetch error:', error);
      console.error('Error details:', {
        name: error.name,
        message: error.message,
        stack: error.stack
      });
      
      // Only show error if overview tab is still visible
      if (!isOverviewVisible) {
        console.warn('⚠️ Dashboard error suppressed - overview tab is not visible');
        return;
      }
      
      let errorMessage = 'Failed to load dashboard data. ';
      if (error.message.includes('Authentication required')) {
        errorMessage += 'Please log in again.';
      } else if (error.message.includes('403')) {
        errorMessage += 'Access denied. Please check your permissions.';
      } else if (error.message.includes('404')) {
        errorMessage += 'Dashboard service not found.';
      } else if (error.message.includes('500')) {
        errorMessage += 'Server error. Please try again later.';
      } else {
        errorMessage += 'Please refresh the page.';
      }
      
      showDashboardError(errorMessage);
      // Try to load basic stats as fallback
      loadBasicStats();
    });
}

function showDashboardError(message) {
  // Only show errors if the overview tab is currently active/visible
  const overviewTab = document.getElementById('overview');
  const isOverviewActive = overviewTab && (
    overviewTab.style.display !== 'none' && 
    overviewTab.style.display !== '' ||
    window.getComputedStyle(overviewTab).display !== 'none'
  );
  
  // Check if overview tab content is visible
  const overviewTabContent = document.querySelector('#overview.tab-content');
  const isOverviewVisible = overviewTabContent && (
    overviewTabContent.style.display !== 'none' &&
    window.getComputedStyle(overviewTabContent).display !== 'none'
  );
  
  // Only show error if overview is actually visible
  if (!isOverviewActive && !isOverviewVisible) {
    console.warn('⚠️ Dashboard error suppressed - overview tab is not active:', message);
    return; // Don't show error if overview tab is not active
  }
  
  console.error('Dashboard Error:', message);
  
  // Show error in the overview statistics
  const errorElements = [
    'totalUsers', 'activeUsers', 'pendingUsers', 'inactiveUsers',
    'totalUsersCount', 'moderatorsCount', 'instructorsCount', 
    'activeUsersCount', 'pendingUsersCount', 'regularUsersCount'
  ];
  
  errorElements.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = 'Error';
      element.style.color = 'red';
    }
  });
  
  // Show a user-friendly error message
  const errorDiv = document.createElement('div');
  errorDiv.className = 'alert alert-danger alert-dismissible fade show';
  errorDiv.innerHTML = `
    <strong>Error:</strong> ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  
  // Insert at the top of the dashboard content
  const dashboardContent = document.querySelector('.dashboard-content') || document.body;
  dashboardContent.insertBefore(errorDiv, dashboardContent.firstChild);
}

function updateDashboardStatistics(stats) {
  console.log('Updating dashboard statistics:', stats);
  
  // Update overview statistics
  const totalUsersEl = document.getElementById('totalUsers');
  const activeUsersEl = document.getElementById('activeUsers');
  const pendingUsersEl = document.getElementById('pendingUsers');
  const inactiveUsersEl = document.getElementById('inactiveUsers');
  
  console.log('Overview elements found:', {
    totalUsers: !!totalUsersEl,
    activeUsers: !!activeUsersEl,
    pendingUsers: !!pendingUsersEl,
    inactiveUsers: !!inactiveUsersEl
  });
  
  if (totalUsersEl) totalUsersEl.textContent = stats.users.total_users || 0;
  if (activeUsersEl) activeUsersEl.textContent = stats.users.active_users || 0;
  if (pendingUsersEl) pendingUsersEl.textContent = stats.users.pending_users || 0;
  if (inactiveUsersEl) inactiveUsersEl.textContent = stats.users.inactive_users || 0;
  
  // Update user management component statistics
  console.log('Updating user management statistics:', stats.users);
  const totalUsersCountEl = document.getElementById('totalUsersCount');
  const activeUsersCountEl = document.getElementById('activeUsersCount');
  const pendingUsersCountEl = document.getElementById('pendingUsersCount');
  const moderatorsEl = document.getElementById('moderatorsCount');
  const instructorsEl = document.getElementById('instructorsCount');
  const regularUsersEl = document.getElementById('regularUsersCount');
  
  if (totalUsersCountEl) totalUsersCountEl.textContent = stats.users.total_users || 0;
  if (activeUsersCountEl) activeUsersCountEl.textContent = stats.users.active_users || 0;
  if (pendingUsersCountEl) pendingUsersCountEl.textContent = stats.users.pending_users || 0;
  if (moderatorsEl) moderatorsEl.textContent = stats.users.moderators_count || 0;
  if (instructorsEl) instructorsEl.textContent = stats.instructors.total_instructors || 0;
  if (regularUsersEl) regularUsersEl.textContent = stats.instructors.regular_instructors || 0;
  
  console.log('User management statistics updated');
  
  // Update instructor statistics
  const totalInstructorsEl = document.getElementById('totalInstructors');
  const regularInstructorsEl = document.getElementById('regularInstructors');
  const parttimeInstructorsEl = document.getElementById('parttimeInstructors');
  const contractualInstructorsEl = document.getElementById('contractualInstructors');
  const pendingInstructorsEl = document.getElementById('pendingInstructors');
  
  if (totalInstructorsEl) totalInstructorsEl.textContent = stats.instructors.total_instructors || 0;
  if (regularInstructorsEl) regularInstructorsEl.textContent = stats.instructors.regular_instructors || 0;
  if (parttimeInstructorsEl) parttimeInstructorsEl.textContent = stats.instructors.parttime_instructors || 0;
  if (contractualInstructorsEl) contractualInstructorsEl.textContent = stats.instructors.contractual_instructors || 0;
  if (pendingInstructorsEl) pendingInstructorsEl.textContent = stats.instructors.pending_instructors || 0;
}

function loadBasicStats() {
  // Check if overview tab is visible before loading
  const overviewTab = document.getElementById('overview');
  const overviewTabContent = document.querySelector('#overview.tab-content');
  const isOverviewVisible = (overviewTab && window.getComputedStyle(overviewTab).display !== 'none') ||
                            (overviewTabContent && window.getComputedStyle(overviewTabContent).display !== 'none');
  
  if (!isOverviewVisible) {
    console.log('⚠️ Overview tab not visible - skipping basic stats load');
    return; // Don't load if overview tab is not visible
  }
  
  console.log('Loading basic stats as fallback...');
  
  // Determine correct path based on current location
  const statsPath = window.location.pathname.includes('/views/admin/') 
    ? '../../admin/reports/get_basic_stats.php' 
    : 'get_basic_stats.php';
  
  fetch(statsPath)
    .then(response => {
      console.log('Basic stats response status:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Basic stats loaded:', data);
      if (data.success) {
        // Update with basic stats
        const totalUsersEl = document.getElementById('totalUsers');
        const totalUsersCountEl = document.getElementById('totalUsersCount');
        const activeUsersEl = document.getElementById('activeUsers');
        const activeUsersCountEl = document.getElementById('activeUsersCount');
        const pendingUsersEl = document.getElementById('pendingUsers');
        const pendingUsersCountEl = document.getElementById('pendingUsersCount');
        
        if (totalUsersEl) totalUsersEl.textContent = data.data.users.total_users || 0;
        if (totalUsersCountEl) totalUsersCountEl.textContent = data.data.users.total_users || 0;
        if (activeUsersEl) activeUsersEl.textContent = data.data.users.active_users || 0;
        if (activeUsersCountEl) activeUsersCountEl.textContent = data.data.users.active_users || 0;
        if (pendingUsersEl) pendingUsersEl.textContent = data.data.users.pending_users || 0;
        if (pendingUsersCountEl) pendingUsersCountEl.textContent = data.data.users.pending_users || 0;
        
        console.log('Basic stats updated successfully');
      } else {
        console.error('Basic stats error:', data.message);
        showDashboardError('Failed to load basic statistics: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Basic stats fetch error:', error);
      showDashboardError('Failed to load any dashboard data. Please check your connection and refresh the page.');
    });
}

// Mobile Detection Utility
function isMobileViewport() {
  return window.innerWidth <= 991.98; // Bootstrap's md breakpoint
}

// Tab Navigation Functions
// Ensure this is the global showTab function
window.showTab = function(tabId, element) {
  // Clean up any lingering offcanvas backdrops when switching tabs
  const offcanvas = document.getElementById('sidebarOffcanvas');
  const isOffcanvasShowing = offcanvas && offcanvas.classList.contains('show');
  
  if (!isOffcanvasShowing) {
    // Remove all backdrops if offcanvas is not showing
    const backdrops = document.querySelectorAll('.offcanvas-backdrop');
    backdrops.forEach(backdrop => {
      backdrop.remove();
    });
    
    // Clean up body classes
    document.body.classList.remove('offcanvas-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }
  
  // Mobile Responsiveness: Hide overview on mobile when switching to other tabs
  const isMobile = isMobileViewport();
  const overviewTab = document.getElementById('overview');
  
  // CRITICAL: On mobile, hide overview FIRST before hiding other tabs
  // This ensures overview is removed from layout before showing new tab
  if (isMobile && overviewTab && tabId !== 'overview') {
    // Hide overview immediately and synchronously
    overviewTab.style.display = 'none';
    overviewTab.style.visibility = 'hidden';
    overviewTab.classList.add('mobile-tab-hidden');
    overviewTab.classList.remove('mobile-tab-active');
    // Force layout recalculation by reading offsetHeight
    void overviewTab.offsetHeight;
  }
  
  // Hide all tabs
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.style.display = 'none';
    tab.style.visibility = 'hidden';
    // Remove mobile-specific classes
    tab.classList.remove('mobile-tab-active', 'mobile-tab-hidden');
  });
  
  // Show target tab
  const targetTabElement = document.getElementById(tabId);
  if (targetTabElement) {
    targetTabElement.style.display = 'block';
    targetTabElement.style.visibility = 'visible';
    targetTabElement.classList.add('mobile-tab-active');
    
    // On mobile: Handle overview visibility
    if (isMobile && overviewTab) {
      if (tabId === 'overview') {
        // Show overview when switching back to it
        overviewTab.style.display = 'block';
        overviewTab.style.visibility = 'visible';
        overviewTab.classList.remove('mobile-tab-hidden');
        overviewTab.classList.add('mobile-tab-active');
      }
      // If switching to non-overview tab, overview is already hidden above
    }
    
    // Scroll to top of main content on mobile when switching tabs
    // Use immediate scroll first, then smooth scroll after layout update
    if (isMobile) {
      // Force immediate scroll to top (no animation)
      window.scrollTo({ top: 0, behavior: 'auto' });
      document.documentElement.scrollTop = 0;
      document.body.scrollTop = 0;
      
      // Then use requestAnimationFrame to ensure layout has fully updated
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          const mainContent = document.querySelector('.main-content') || document.querySelector('.container-fluid');
          if (mainContent) {
            // Ensure we're at the top
            window.scrollTo({ top: 0, behavior: 'auto' });
            mainContent.scrollIntoView({ behavior: 'auto', block: 'start' });
          }
        });
      });
    }
  }

  // CRITICAL: Remove active class from ALL navigation links FIRST (both sidebar and offcanvas)
  // This must happen before adding active to prevent multiple highlights
  const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
  allNavLinks.forEach(link => {
    link.classList.remove('active');
  });
  
  // Add active class only to the clicked element (if provided)
  if (element) {
    // Ensure element is a nav-link
    if (element.classList.contains('nav-link')) {
      element.classList.add('active');
      
      // Also find and activate the corresponding link in the other sidebar (mobile/desktop)
      const correspondingLink = Array.from(allNavLinks).find(link => {
        if (link === element) return false;
        
        // Match by onclick content or href
        const linkOnclick = (link.getAttribute('onclick') || '').trim();
        const linkHref = (link.getAttribute('href') || '').trim();
        const elementOnclick = (element.getAttribute('onclick') || '').trim();
        const elementHref = (element.getAttribute('href') || '').trim();
        
        // Match if onclick or href are the same, or if both reference the same tabId
        return (linkOnclick === elementOnclick && linkOnclick !== '') ||
               (linkHref === elementHref && linkHref !== '' && linkHref !== '#') ||
               (linkOnclick.includes(tabId) && elementOnclick.includes(tabId));
      });
      
      if (correspondingLink) {
        correspondingLink.classList.add('active');
      }
    }
  }
  // persist last active tab
  try { localStorage.setItem('admin_active_tab', tabId); } catch (e) {}
  
  // Load data for specific tabs
  if (tabId === 'overview') {
    loadDashboardData();
  }
  // Load accounts when Users tab is selected
  else if (tabId === 'roles') {
    console.log('Loading accounts for roles tab...');
    loadAccounts();
  }
  // Load subjects when Subjects tab is selected
  else if (tabId === 'curriculum') {
    console.log('Loading subjects for curriculum tab...');
    if (typeof loadSubjects === 'function') {
      loadSubjects();
    } else if (typeof loadSubjectStatistics === 'function') {
      loadSubjectStatistics();
    }
  }
  // Load curriculum data when curriculum management tab is selected
  else if (tabId === 'curriculum_management') {
    console.log('Loading curriculum data for curriculum management tab...');
    if (typeof loadCurriculumData === 'function') {
      loadCurriculumData();
    }
  }
  // Load course data when course management tab is selected
  else if (tabId === 'course_management') {
    console.log('Loading course data for course management tab...');
    if (typeof loadCourseData === 'function') {
      loadCourseData();
    }
  }
  // Load room data when Rooms tab is selected
  else if (tabId === 'room_requests') {
    console.log('Loading room data for room_requests tab...');
    // Room management component should auto-initialize, but trigger if needed
    setTimeout(() => {
      const roomsTab = document.querySelector('#rooms-tab');
      if (roomsTab && typeof roomsTab.click === 'function') {
        // Let the component handle its own initialization
        console.log('Room management component should auto-initialize');
      }
    }, 100);
  }
  // Load schedules when Schedules tab is selected
  else if (tabId === 'schedule') {
    console.log('Loading schedules for schedule tab...');
    if (typeof loadClassSelector === 'function') {
      loadClassSelector();
    }
    if (typeof loadSchedules === 'function') {
      loadSchedules();
    }
  }
};

// Handle window resize to update mobile tab behavior dynamically
let resizeTimeout;
window.addEventListener('resize', function() {
  clearTimeout(resizeTimeout);
  resizeTimeout = setTimeout(function() {
    // Check if we need to update tab visibility based on new viewport size
    const isMobile = isMobileViewport();
    const overviewTab = document.getElementById('overview');
    const activeTab = document.querySelector('.tab-content.mobile-tab-active');
    
    if (overviewTab && activeTab) {
      const activeTabId = activeTab.id;
      
      // On mobile: Hide overview when other tabs are active
      if (isMobile && activeTabId !== 'overview') {
        overviewTab.style.display = 'none';
        overviewTab.classList.add('mobile-tab-hidden');
        overviewTab.classList.remove('mobile-tab-active');
      } 
      // On desktop: Show overview normally (it will be hidden/shown by showTab)
      else if (!isMobile) {
        // Remove mobile-specific classes on desktop
        overviewTab.classList.remove('mobile-tab-hidden', 'mobile-tab-active');
        // Only hide if it's not the active tab
        if (activeTabId !== 'overview') {
          overviewTab.style.display = 'none';
        }
      }
    }
  }, 250); // Debounce resize events
});

function closeOffcanvas() {
  const offcanvasEl = document.getElementById('sidebarOffcanvas');
  const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
  if (bsOffcanvas) bsOffcanvas.hide();
}

// Profile Management Functions
function loadProfileData() {
  // Check if we're on moderator dashboard - profile data is already loaded in PHP
  if (window.location.pathname.includes('/views/moderator/')) {
    console.log('🔵 [DEBUG] Moderator dashboard detected - skipping profile API call (data already loaded in PHP)');
    // Profile data is already available from dashboard controller
    // Just update the profile image if needed
    const profileImg = document.getElementById('adminProfileImg');
    const profilePreview = document.getElementById('profilePreview');
    if (profileImg && profileImg.src && profileImg.src.includes('data:image')) {
      // Profile image is already set from PHP
      console.log('🔵 [DEBUG] Profile image already loaded from PHP');
    }
    return;
  }
  
  // Load current profile photo if exists (for admin dashboard only)
  // Determine correct path based on current location
  let profilePath;
  if (window.location.pathname.includes('/views/admin/')) {
    profilePath = '../../admin/reports/get_profile_data.php';
  } else {
    profilePath = 'get_profile_data.php';
  }
  
  fetch(profilePath)
    .then(response => {
      if (!response.ok) {
        if (response.status === 403) {
          return response.json().then(data => {
            if (data.message && data.message.includes('permission')) {
              console.warn('⚠️ Permission denied for profile data');
              return { success: false, permissionError: true };
            }
            throw new Error('Authentication required');
          });
        }
        if (response.status === 404) {
          console.error('❌ Profile endpoint not found:', profilePath);
          throw new Error(`Profile endpoint not found`);
        }
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      // Handle permission errors silently
      if (data.permissionError) {
        console.warn('⚠️ Permission error for profile - suppressing error');
        return;
      }
      
      if (data.success && data.profile_photo) {
        document.getElementById('profilePreview').src = data.profile_photo;
        document.getElementById('adminProfileImg').src = data.profile_photo;
        // Update mobile profile image too
        const mobileImg = document.getElementById('adminProfileImgMobile');
        if (mobileImg) mobileImg.src = data.profile_photo;
      }
    })
    .catch(error => {
      // Only log errors, don't show alerts for permission/404 errors
      if (error.message.includes('endpoint not found') || error.message.includes('Permission')) {
        console.warn('⚠️ Profile data not loaded:', error.message);
      } else {
        console.log('Profile data not loaded:', error);
      }
    });
}

function saveProfile() {
  const form = document.getElementById('profileForm');
  const formData = new FormData(form);
  
  // Validate password confirmation
  const newPassword = document.getElementById('new_password').value;
  const confirmPassword = document.getElementById('confirm_password').value;
  
  if (newPassword && newPassword !== confirmPassword) {
    alert('New passwords do not match!');
    return;
  }

  // Show loading state
  const saveBtn = document.querySelector('[onclick="saveProfile()"]');
  const originalText = saveBtn.textContent;
  saveBtn.textContent = 'Saving...';
  saveBtn.disabled = true;

  fetch('../update_profile.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Profile updated successfully!');
      // Update the main profile image
      if (data.profile_photo) {
        document.getElementById('adminProfileImg').src = data.profile_photo;
        // Update mobile profile image too
        const mobileImg = document.getElementById('adminProfileImgMobile');
        if (mobileImg) mobileImg.src = data.profile_photo;
      }
      // Update username display
      const newUsername = document.getElementById('acc_user').value;
      document.querySelector('.fw-semibold.mt-2').textContent = newUsername;
      // Close modal
      const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
      modal.hide();
    } else {
      alert('Error: ' + (data.message || 'Failed to update profile'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating profile');
  })
  .finally(() => {
    saveBtn.textContent = originalText;
    saveBtn.disabled = false;
  });
}

function updateProfile() {
  console.log('updateProfile function called');
  const form = document.getElementById('profileForm');
  if (!form) {
    console.error('Profile form not found!');
    alert('Profile form not found. Please refresh the page.');
    return;
  }
  
  const formData = new FormData(form);
  formData.append('acc_id', window.currentUserId || 0);
  
  console.log('Form data prepared:', {
    fname: formData.get('fname'),
    lname: formData.get('lname'),
    acc_user: formData.get('acc_user'),
    acc_email: formData.get('acc_email'),
    acc_id: formData.get('acc_id')
  });
  
  // Helper function to show validation error with SweetAlert2
  function showValidationError(message) {
    Swal.fire({
      icon: 'warning',
      title: 'Validation Error',
      text: message,
      confirmButtonColor: '#800000',
      confirmButtonText: 'OK'
    });
  }
  
  // Validate required fields
  const requiredFields = ['fname', 'lname', 'acc_user', 'acc_email'];
  for (let field of requiredFields) {
    const value = formData.get(field);
    if (!value || value.trim() === '') {
      showValidationError(`Please fill in the required field: ${field.replace('_', ' ').toUpperCase()}`);
      document.getElementById(field).focus();
      return;
    }
  }
  
  // Validate email format
  const email = formData.get('acc_email');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showValidationError('Please enter a valid email address');
    document.getElementById('acc_email').focus();
    return;
  }
  
  // Validate password fields if any are filled
  const currentPassword = formData.get('current_password');
  const newPassword = formData.get('new_password');
  const confirmPassword = formData.get('confirm_password');
  
  if (newPassword && !currentPassword) {
    showValidationError('Current password is required to change password');
    document.getElementById('current_password').focus();
    return;
  }
  
  if (newPassword && newPassword !== confirmPassword) {
    showValidationError('New passwords do not match');
    document.getElementById('confirm_password').focus();
    return;
  }
  
  if (newPassword && newPassword.length < 6) {
    showValidationError('New password must be at least 6 characters long');
    document.getElementById('new_password').focus();
    return;
  }
  
  // Clear any existing messages
  const messageEl = document.getElementById('profileMessage');
  if (messageEl) {
    messageEl.style.display = 'none';
  }
  
  const updateBtn = document.querySelector('button[onclick="updateProfile()"]');
  const originalText = updateBtn.innerHTML;
  updateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
  updateBtn.disabled = true;
  
  console.log('Sending request to update_profile.php');
  fetch('update_profile.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    console.log('Response received:', response.status, response.statusText);
    return response.json();
  })
  .then(data => {
    console.log('Response data:', data);
    
    if (data.success) {
      // Show beautiful success message with SweetAlert2
      Swal.fire({
        icon: 'success',
        title: 'Profile Updated!',
        text: 'Your profile has been updated successfully.',
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Great!',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: true,
        allowOutsideClick: false,
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          content: 'swal2-content-custom'
        }
      }).then(() => {
        // Update the display name in the sidebar if it exists
        const displayName = document.querySelector('.fw-semibold.mt-2');
        if (displayName) {
          const fname = formData.get('fname');
          const lname = formData.get('lname');
          displayName.textContent = `${fname} ${lname}`;
        }
        
        // Clear password fields on success
        document.getElementById('current_password').value = '';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        
        // Hide any existing inline messages
        const messageEl = document.getElementById('profileMessage');
        if (messageEl) {
          messageEl.style.display = 'none';
        }
      });
    } else {
      // Show error message with SweetAlert2
      Swal.fire({
        icon: 'error',
        title: 'Update Failed',
        text: data.message || 'Failed to update profile. Please try again.',
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Try Again',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          content: 'swal2-content-custom'
        }
      });
    }
  })
  .catch(error => {
    console.error('Error:', error);
    
    // Show network error with SweetAlert2
    Swal.fire({
      icon: 'error',
      title: 'Connection Error',
      text: 'Unable to connect to the server. Please check your internet connection and try again.',
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'Retry',
      customClass: {
        popup: 'swal2-popup-custom',
        title: 'swal2-title-custom',
        content: 'swal2-content-custom'
      }
    });
  })
  .finally(() => {
    updateBtn.innerHTML = originalText;
    updateBtn.disabled = false;
  });
}

// Tab Management
function showTab(tabName, buttonElement) {
  // Hide all tab contents
  const tabContents = document.querySelectorAll('.tab-content');
  tabContents.forEach(tab => {
    tab.style.display = 'none';
  });
  
  // Remove active class from all buttons
  const buttons = document.querySelectorAll('.btn');
  buttons.forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Show selected tab
  const selectedTab = document.getElementById(tabName);
  if (selectedTab) {
    selectedTab.style.display = 'block';
  }
  
  // Add active class to clicked button
  if (buttonElement) {
    buttonElement.classList.add('active');
  }
  
  // Load data for specific tabs
  if (tabName === 'roles') {
    loadAccounts(); // Load user management data
  }
}

// Dashboard Data Loading
function loadDashboardData() {
  // Check if we're on moderator dashboard - data is already loaded in PHP
  if (window.location.pathname.includes('/views/moderator/')) {
    console.log('🔵 [DEBUG] Moderator dashboard detected - skipping API call (data already loaded in PHP)');
    return; // Data is already loaded in dashboard_overview.php component
  }
  
  // Add loading animation to stat numbers
  const statNumbers = document.querySelectorAll('.stat-number, .h4, .h5');
  statNumbers.forEach(el => {
    if (el.textContent === '-') {
      el.innerHTML = '<span class="loading"></span>';
    }
  });

  // Determine correct path based on current location (admin dashboard only)
  const dashboardPath = window.location.pathname.includes('/views/admin/') 
    ? '../../admin/reports/get_dashboard_data.php' 
    : 'get_dashboard_data.php';
  
  fetch(dashboardPath)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        const stats = data.data;
        
        // Update user statistics
        document.getElementById('totalUsers').textContent = stats.users.total_users || 0;
        document.getElementById('activeUsers').textContent = stats.users.active_users || 0;
        document.getElementById('pendingUsers').textContent = stats.users.pending_users || 0;
        document.getElementById('inactiveUsers').textContent = stats.users.inactive_users || 0;
        
        // Update user management component statistics
        console.log('Updating user management statistics:', stats.users);
        const totalUsersEl = document.getElementById('totalUsersCount');
        const activeUsersEl = document.getElementById('activeUsersCount');
        const pendingUsersEl = document.getElementById('pendingUsersCount');
        const moderatorsEl = document.getElementById('moderatorsCount');
        const instructorsEl = document.getElementById('instructorsCount');
        const regularUsersEl = document.getElementById('regularUsersCount');
        
        if (totalUsersEl) totalUsersEl.textContent = stats.users.total_users || 0;
        if (activeUsersEl) activeUsersEl.textContent = stats.users.active_users || 0;
        if (pendingUsersEl) pendingUsersEl.textContent = stats.users.pending_users || 0;
        if (moderatorsEl) moderatorsEl.textContent = stats.users.moderators_count || 0;
        if (instructorsEl) instructorsEl.textContent = stats.instructors.total_instructors || 0;
        if (regularUsersEl) regularUsersEl.textContent = stats.instructors.regular_instructors || 0;
        
        console.log('User management statistics updated');
        
        // Update instructor statistics
        document.getElementById('totalInstructors').textContent = stats.instructors.total_instructors || 0;
        document.getElementById('regularInstructors').textContent = stats.instructors.regular_instructors || 0;
        document.getElementById('parttimeInstructors').textContent = stats.instructors.parttime_instructors || 0;
        // Render instructor by-program breakdown if available
        const byProgram = (stats.instructors && stats.instructors.by_program) ? stats.instructors.by_program : [];
        const byProgramEl = document.getElementById('instructorByProgram');
        if (byProgramEl) {
          byProgramEl.innerHTML = '';
          if (Array.isArray(byProgram) && byProgram.length > 0) {
            const totalInstr = Number(stats.instructors.total_instructors || 0) || 0;
            byProgram.forEach(item => {
              const name = item.program || item.course || 'Program';
              const count = Number(item.count || 0);
              const percent = totalInstr > 0 ? Math.round((count / totalInstr) * 100) : 0;
              const wrapper = document.createElement('div');
              wrapper.className = 'mb-2';
              wrapper.innerHTML = `
                <div class="d-flex justify-content-between small">
                  <span>${name}</span><span class="fw-semibold">${count}</span>
                </div>
                <div class="progress" style="height:6px;">
                  <div class="progress-bar bg-success" role="progressbar" style="width:${percent}%;" aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              `;
              byProgramEl.appendChild(wrapper);
            });
          } else {
            byProgramEl.innerHTML = '<div class="text-muted small">No breakdown available</div>';
          }
        }
        
        // Update student statistics
        const totalStudentsEl = document.getElementById('totalStudents');
        if (totalStudentsEl) totalStudentsEl.textContent = stats.students.total_students || 0;
        
        // Update room statistics
        const totalRoomsEl = document.getElementById('totalRooms');
        const usedRoomsEl = document.getElementById('usedRooms');
        const unusedRoomsEl = document.getElementById('unusedRooms');
        const labRoomsEl = document.getElementById('labRooms');
        const lectureRoomsEl = document.getElementById('lectureRooms');
        const specialRoomsEl = document.getElementById('specialRooms');
        
        if (totalRoomsEl) totalRoomsEl.textContent = stats.rooms.total_rooms || 0;
        if (usedRoomsEl) usedRoomsEl.textContent = stats.rooms.used_rooms || 0;
        if (unusedRoomsEl) unusedRoomsEl.textContent = stats.rooms.unused_rooms || 0;
        if (labRoomsEl) labRoomsEl.textContent = stats.rooms.lab_rooms || 0;
        if (lectureRoomsEl) lectureRoomsEl.textContent = stats.rooms.lecture_rooms || 0;
        if (specialRoomsEl) specialRoomsEl.textContent = stats.rooms.special_rooms || 0;
        
        // Update schedule statistics
        const totalSchedulesEl = document.getElementById('totalSchedules');
        const activeSchedulesEl = document.getElementById('activeSchedules');
        
        if (totalSchedulesEl) totalSchedulesEl.textContent = stats.schedules.total_schedules || 0;
        if (activeSchedulesEl) activeSchedulesEl.textContent = stats.schedules.active_schedules || 0;
        
        const totalConflictsEl = document.getElementById('totalConflicts');
        if (totalConflictsEl) totalConflictsEl.textContent = stats.conflicts.total_conflicts || 0;
        
        // Update room request statistics
        const pendingRequestsEl = document.getElementById('pendingRequests');
        const acceptedRequestsEl = document.getElementById('acceptedRequests');
        
        if (pendingRequestsEl) pendingRequestsEl.textContent = stats.room_requests.pending_requests || 0;
        if (acceptedRequestsEl) acceptedRequestsEl.textContent = stats.room_requests.accepted_requests || 0;
        
        const declinedRequestsEl = document.getElementById('declinedRequests');
        if (declinedRequestsEl) declinedRequestsEl.textContent = stats.room_requests.declined_requests || 0;
        
        // Update subject statistics
        const totalSubjectsEl = document.getElementById('totalSubjects');
        const majorSubjectsEl = document.getElementById('majorSubjects');
        
        if (totalSubjectsEl) totalSubjectsEl.textContent = stats.subjects.total_subjects || 0;
        if (majorSubjectsEl) majorSubjectsEl.textContent = stats.subjects.major_subjects || 0;
        
        const genedSubjectsEl = document.getElementById('genedSubjects');
        if (genedSubjectsEl) genedSubjectsEl.textContent = stats.subjects.gened_subjects || 0;
        
        // Update curriculum statistics
        if (stats.curricula) {
          const totalCurriculaEl = document.getElementById('totalCurricula');
          if (totalCurriculaEl) totalCurriculaEl.textContent = stats.curricula.total_curricula || 0;
          
          const firstYearCurriculaEl = document.getElementById('firstYearCurricula');
          const secondYearCurriculaEl = document.getElementById('secondYearCurricula');
          const thirdYearCurriculaEl = document.getElementById('thirdYearCurricula');
          const fourthYearCurriculaEl = document.getElementById('fourthYearCurricula');
          
          if (firstYearCurriculaEl) firstYearCurriculaEl.textContent = stats.curricula.first_year_curricula || 0;
          if (secondYearCurriculaEl) secondYearCurriculaEl.textContent = stats.curricula.second_year_curricula || 0;
          if (thirdYearCurriculaEl) thirdYearCurriculaEl.textContent = stats.curricula.third_year_curricula || 0;
          if (fourthYearCurriculaEl) fourthYearCurriculaEl.textContent = stats.curricula.fourth_year_curricula || 0;
        }
        
        // Update classes and sections statistics
        if (stats.classes) {
          const totalClassesEl = document.getElementById('totalClasses');
          if (totalClassesEl) totalClassesEl.textContent = stats.classes.total_classes || 0;
          
          const totalSectionsEl = document.getElementById('totalSections');
          const firstTermClassesEl = document.getElementById('firstTermClasses');
          const secondTermClassesEl = document.getElementById('secondTermClasses');
          
          if (totalSectionsEl) totalSectionsEl.textContent = stats.classes.total_sections || 0;
          if (firstTermClassesEl) firstTermClassesEl.textContent = stats.classes.first_term_classes || 0;
          if (secondTermClassesEl) secondTermClassesEl.textContent = stats.classes.second_term_classes || 0;
        }
        
        // Update department overview
        if (stats.department) {
          const departmentNameEl = document.getElementById('departmentName');
          if (departmentNameEl) departmentNameEl.textContent = stats.department.dept_name || 'All Departments';
        }
        
        // Update department-specific instructor and student counts
        const totalInstructorsDeptEl = document.getElementById('totalInstructorsDept');
        const totalStudentsDeptEl = document.getElementById('totalStudentsDept');
        
        if (totalInstructorsDeptEl) totalInstructorsDeptEl.textContent = stats.instructors.total_instructors || 0;
        if (totalStudentsDeptEl) totalStudentsDeptEl.textContent = stats.students.total_students || 0;
        
        // Update department resources
        if (stats.buildings) {
          const totalBuildingsDeptEl = document.getElementById('totalBuildingsDept');
          if (totalBuildingsDeptEl) totalBuildingsDeptEl.textContent = stats.buildings.total_buildings || 0;
        }
        
        const totalRoomsDeptEl = document.getElementById('totalRoomsDept');
        const totalSchedulesDeptEl = document.getElementById('totalSchedulesDept');
        
        if (totalRoomsDeptEl) totalRoomsDeptEl.textContent = stats.rooms.total_rooms || 0;
        if (totalSchedulesDeptEl) totalSchedulesDeptEl.textContent = stats.schedules.total_schedules || 0;
        
        // Update department summary if available
        if (stats.department_summary) {
          console.log('Department Summary:', stats.department_summary);
          // You can add more department summary handling here if needed
        }
      }
    })
    .catch(error => {
      console.error('Dashboard data not loaded:', error);
      
      // Check if overview tab is visible before showing errors
      const overviewTab = document.getElementById('overview');
      const overviewTabContent = document.querySelector('#overview.tab-content');
      const isOverviewVisible = (overviewTab && window.getComputedStyle(overviewTab).display !== 'none') ||
                                (overviewTabContent && window.getComputedStyle(overviewTabContent).display !== 'none');
      
      if (!isOverviewVisible) {
        console.warn('⚠️ Dashboard error suppressed - overview tab is not visible');
        return;
      }
      
      // Check if it's an authentication error
      if (error.message.includes('403') || error.message.includes('Unauthorized')) {
        // Try to load basic stats without authentication
        console.log('Authentication failed, trying basic stats...');
        loadBasicStats();
      } else {
        // Show generic error message
        const errorMessage = document.createElement('div');
        errorMessage.className = 'alert alert-danger alert-dismissible fade show';
        errorMessage.innerHTML = `
          <i class="bi bi-exclamation-circle me-2"></i>
          <strong>Error:</strong> Failed to load dashboard data. Please refresh the page.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const dashboardContent = document.querySelector('.dashboard-content') || document.querySelector('.container-fluid');
        if (dashboardContent) {
          dashboardContent.insertBefore(errorMessage, dashboardContent.firstChild);
        }
      }
    });
    
    // Only load user management data if users tab is visible
    const usersTabLink = document.querySelector('a[onclick*="roles"], a[href="#roles"]');
    if (usersTabLink && usersTabLink.offsetParent !== null) {
      loadAccounts();
    }
}

// Function to load basic statistics without authentication
function loadBasicStats() {
  // Determine correct path based on current location
  const statsPath = window.location.pathname.includes('/views/admin/') 
    ? '../../admin/reports/get_basic_stats.php' 
    : 'get_basic_stats.php';
  
  fetch(statsPath)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const stats = data.data;
        
        // Update user statistics with basic data
        document.getElementById('totalUsers').textContent = stats.users.total_users || 0;
        document.getElementById('activeUsers').textContent = stats.users.active_users || 0;
        document.getElementById('pendingUsers').textContent = stats.users.pending_users || 0;
        document.getElementById('inactiveUsers').textContent = stats.users.inactive_users || 0;
        
        // Update instructor statistics
        if (stats.instructors) {
          document.getElementById('totalInstructors').textContent = stats.instructors.total_instructors || 0;
        }
        
        // Show info message that this is basic data
        const infoMessage = document.createElement('div');
        infoMessage.className = 'alert alert-info alert-dismissible fade show';
        infoMessage.innerHTML = `
          <i class="bi bi-info-circle me-2"></i>
          <strong>Basic Statistics:</strong> Showing basic data. <a href="login_admin.php" class="alert-link">Log in</a> for complete dashboard features.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const dashboardContent = document.querySelector('.dashboard-content') || document.querySelector('.container-fluid');
        if (dashboardContent) {
          dashboardContent.insertBefore(infoMessage, dashboardContent.firstChild);
        }
        
        console.log('Basic statistics loaded successfully');
      } else {
        throw new Error(data.message || 'Failed to load basic statistics');
      }
    })
    .catch(error => {
      console.error('Failed to load basic statistics:', error);
      
      // Only show error if overview tab is still visible
      const overviewTab = document.getElementById('overview');
      const overviewTabContent = document.querySelector('#overview.tab-content');
      const isOverviewVisible = (overviewTab && window.getComputedStyle(overviewTab).display !== 'none') ||
                                (overviewTabContent && window.getComputedStyle(overviewTabContent).display !== 'none');
      
      if (!isOverviewVisible) {
        console.warn('⚠️ Basic stats error suppressed - overview tab is not visible');
        return;
      }
      
      // Show authentication required message
      const errorMessage = document.createElement('div');
      errorMessage.className = 'alert alert-warning alert-dismissible fade show';
      errorMessage.innerHTML = `
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Authentication Required:</strong> Please <a href="login_admin.php" class="alert-link">log in</a> to view dashboard data.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      
      const dashboardContent = document.querySelector('.dashboard-content') || document.querySelector('.container-fluid');
      if (dashboardContent) {
        dashboardContent.insertBefore(errorMessage, dashboardContent.firstChild);
      }
      
      // Set default values
      document.getElementById('totalUsers').textContent = 'Login Required';
      document.getElementById('activeUsers').textContent = '-';
      document.getElementById('pendingUsers').textContent = '-';
      document.getElementById('inactiveUsers').textContent = '-';
    });
}

// Step Navigation Variables
let adminCurrentStep = 1;
// Prevent redeclaration error - use window scope to avoid conflicts
if (typeof window.totalSteps === 'undefined') {
  window.totalSteps = 5;
}
// Use window.totalSteps directly - do not declare a const to avoid redeclaration errors

function changeStep(direction) {
  const newStep = adminCurrentStep + direction;
  
  console.log('changeStep - direction:', direction, 'adminCurrentStep:', adminCurrentStep, 'newStep:', newStep, 'totalSteps:', window.totalSteps);
  
  if (newStep < 1 || newStep > window.totalSteps) {
    console.log('Invalid step range, returning');
    return;
  }
  
  // Validate current step before proceeding
  if (direction > 0 && !validateCurrentStep()) {
    console.log('Validation failed, returning');
    return;
  }
  
  // Hide current step
  document.getElementById(`step${adminCurrentStep}`).classList.add('d-none');
  document.querySelector(`[data-step="${adminCurrentStep}"]`).classList.remove('active');
  
  // Show new step
  adminCurrentStep = newStep;
  document.getElementById(`step${adminCurrentStep}`).classList.remove('d-none');
  document.querySelector(`[data-step="${adminCurrentStep}"]`).classList.add('active');
  
  console.log('Step changed to:', adminCurrentStep);
  
  // Update step indicators
  updateStepIndicators();
  
  // Update buttons
  updateNavigationButtons();
  
  // Update summary if on last step
  if (adminCurrentStep === totalSteps) {
    console.log('On last step, updating summary');
    updateAccountSummary();
  }
}

function validateCurrentStep() {
  const step = document.getElementById(`step${adminCurrentStep}`);
  const requiredFields = step.querySelectorAll('[required]');
  
  for (let field of requiredFields) {
    if (!field.value.trim()) {
      field.focus();
      field.classList.add('is-invalid');
      alert(`Please fill in the required field: ${field.previousElementSibling.textContent.replace('*', '').trim()}`);
      return false;
    } else {
      field.classList.remove('is-invalid');
    }
  }
  
      // Special validations
      if (adminCurrentStep === 2) {
        // Password validation removed - using default password "evsu-occ"
      }
  
  return true;
}

function updateStepIndicators() {
  const steps = document.querySelectorAll('.step');
  steps.forEach((step, index) => {
    const stepNumber = index + 1;
    step.classList.remove('active', 'completed');
    
    if (stepNumber < adminCurrentStep) {
      step.classList.add('completed');
    } else if (stepNumber === adminCurrentStep) {
      step.classList.add('active');
    }
  });
}

function updateNavigationButtons() {
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const submitBtn = document.getElementById('submitBtn');
  
    console.log('updateNavigationButtons - adminCurrentStep:', adminCurrentStep, 'totalSteps:', window.totalSteps);
  
  // Previous button
  if (adminCurrentStep === 1) {
    prevBtn.style.display = 'none';
  } else {
    prevBtn.style.display = 'inline-block';
  }
  
  // Next/Submit button
  if (adminCurrentStep === totalSteps) {
    console.log('Showing submit button, hiding next button');
    nextBtn.classList.add('d-none');
    submitBtn.classList.remove('d-none');
  } else {
    console.log('Showing next button, hiding submit button');
    nextBtn.classList.remove('d-none');
    submitBtn.classList.add('d-none');
  }
}

function updateAccountSummary() {
  const form = document.getElementById('addAccountForm');
  const formData = new FormData(form);
  
  document.getElementById('summary_name').textContent = 
    `${formData.get('fname')} ${formData.get('minitial')} ${formData.get('lname')}`.replace(/\s+/g, ' ').trim();
  document.getElementById('summary_username').textContent = formData.get('acc_user');
  document.getElementById('summary_email').textContent = formData.get('acc_email');
  
  const roleSelect = document.getElementById('role_id');
  const roleText = roleSelect.options[roleSelect.selectedIndex].text;
  document.getElementById('summary_role').textContent = roleText;
  
  // Instructor details
  const instStatusSelect = document.getElementById('inst_status');
  const instStatusText = instStatusSelect.options[instStatusSelect.selectedIndex].text;
  document.getElementById('summary_inst_status').textContent = instStatusText;
  
  const rankSelect = document.getElementById('rank');
  const rankText = rankSelect.options[rankSelect.selectedIndex].text;
  document.getElementById('summary_rank').textContent = rankText;
  
  const designationSelect = document.getElementById('designation');
  const designationText = designationSelect.options[designationSelect.selectedIndex].text;
  document.getElementById('summary_designation').textContent = designationText;
  
  document.getElementById('summary_inst_email').textContent = formData.get('inst_email') || 'Not provided';
  document.getElementById('summary_inst_phone').textContent = formData.get('inst_phone') || 'Not provided';
  
  // Status is automatically set to "Active" - no need to get from form
}

function addAccount() {
  const form = document.getElementById('addAccountForm');
  const formData = new FormData(form);
  
  // Validate required fields
  const requiredFields = form.querySelectorAll('[required]');
  for (let field of requiredFields) {
    if (!field.value.trim()) {
      field.focus();
      field.classList.add('is-invalid');
      alert(`Please fill in the required field: ${field.previousElementSibling.textContent.replace('*', '').trim()}`);
      return;
    } else {
      field.classList.remove('is-invalid');
    }
  }

  // Show loading state
  const submitBtn = document.getElementById('submitBtn');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Creating Account...';
  submitBtn.disabled = true;

  fetch('../../admin/management/add_account.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      let message = data.message || 'Account created successfully!';
      if (data.verification_required) {
        message += '\n\nA verification code (OTP) has been sent to the instructor\'s email. They must verify their email using the OTP code before they can login.';
      }
      
      // Show success message with SweetAlert2 style
      alert(message);
      
      // Reset form and close modal
      form.reset();
      const modal = bootstrap.Modal.getInstance(document.getElementById('addAccountModal'));
      modal.hide();
      
      // Reload dashboard data
      loadDashboardData();
      
      // If Users tab is visible, refresh users list
      const rolesTab = document.getElementById('roles');
      if (rolesTab && rolesTab.style.display !== 'none') {
        loadAccounts();
      }
    } else {
      alert('Error: ' + (data.message || 'Failed to create account'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while creating account');
  })
  .finally(() => {
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  });
}

function resetStepNavigation() {
  adminCurrentStep = 1;
  
  // Hide all steps except first
  for (let i = 2; i <= window.totalSteps; i++) {
    document.getElementById(`step${i}`).classList.add('d-none');
  }
  document.getElementById('step1').classList.remove('d-none');
  
  // Reset step indicators
  updateStepIndicators();
  updateNavigationButtons();
}

// Enhanced Users Tab Logic
// Prevent redeclaration error
if (typeof __ACCOUNTS_CACHE__ === 'undefined') {
  var __ACCOUNTS_CACHE__ = [];
}
if (typeof __USERS_STATS__ === 'undefined') {
  var __USERS_STATS__ = { total: 0, moderators: 0, instructors: 0, pending: 0 };
}

function loadAccounts() {
  showUsersLoadingState();
  const params = new URLSearchParams({ dept_id: window.currentDeptId || 0 });
  console.log('Loading accounts for dept_id:', window.currentDeptId || 0);
  
  // Determine correct path based on current location
  // Both admin and moderator dashboards use the same admin endpoints
  let accountsPath;
  if (window.location.pathname.includes('/views/admin/')) {
    accountsPath = '../../admin/management/get_accounts.php';
  } else if (window.location.pathname.includes('/views/moderator/')) {
    accountsPath = '../../admin/management/get_accounts.php';
  } else {
    accountsPath = 'get_accounts.php';
  }
  
  fetch(accountsPath + '?' + params.toString())
    .then(r => {
      if (!r.ok) {
        if (r.status === 403) {
          return r.json().then(data => {
            if (data.message && data.message.includes('permission')) {
              console.warn('⚠️ Permission denied for accounts');
              return { success: false, permissionError: true, accounts: [] };
            }
            throw new Error('Authentication required');
          });
        }
        if (r.status === 404) {
          console.error('❌ Accounts endpoint not found:', accountsPath);
          throw new Error(`Accounts endpoint not found`);
        }
        throw new Error(`HTTP ${r.status}: ${r.statusText}`);
      }
      return r.json();
    })
    .then(data => {
      console.log('Accounts API Response:', data);
      
      // Handle permission errors silently
      if (data.permissionError) {
        console.warn('⚠️ Permission error for accounts - suppressing error');
        return { success: false, accounts: [] };
      }
      
      if (!data.success) throw new Error(data.message || 'Failed to load accounts');
      __ACCOUNTS_CACHE__ = Array.isArray(data.accounts) ? data.accounts.map(n => ({
        ...n,
        full_name: [n.first_name, n.middle_initial, n.last_name].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim()
      })) : [];
      console.log('Processed accounts cache:', __ACCOUNTS_CACHE__);
      
      // Calculate statistics
      __USERS_STATS__ = {
        total: __ACCOUNTS_CACHE__.length,
        moderators: __ACCOUNTS_CACHE__.filter(a => Number(a.role_id) === 3).length,
        instructors: __ACCOUNTS_CACHE__.filter(a => Number(a.role_id) === 4).length,
        pending: __ACCOUNTS_CACHE__.filter(a => a.acc_status === 'Pending').length,
        regular: __ACCOUNTS_CACHE__.filter(a => a.inst_status === 'Regular').length,
        parttime: __ACCOUNTS_CACHE__.filter(a => a.inst_status === 'Part-Time').length,
        contractual: __ACCOUNTS_CACHE__.filter(a => a.inst_status === 'Contractual').length
      };
      
      updateUsersStatistics();
      attachAccountsFiltersIfNeeded();
      console.log('About to call applyAccountsFilters...');
      
      // Test if table elements exist
      const tableBody = document.getElementById('usersTableBody');
      const tableContainer = document.getElementById('usersTableContainer');
      console.log('Table elements test:', {
        tableBody: !!tableBody,
        tableContainer: !!tableContainer,
        tableBodyElement: tableBody,
        tableContainerElement: tableContainer
      });
      
      applyAccountsFilters();
      console.log('applyAccountsFilters called successfully');
      hideUsersLoadingState();
    })
    .catch(err => {
      console.error('Accounts load error:', err);
      hideUsersLoadingState();
      showUsersEmptyState();
    });
}

function refreshUsersData() {
  loadAccounts();
}

function applyAccountsFilters() {
  console.log('🔥 applyAccountsFilters function called!');
  console.log('applyAccountsFilters called, cache length:', __ACCOUNTS_CACHE__?.length || 0);
  console.log('__ACCOUNTS_CACHE__:', __ACCOUNTS_CACHE__);
  if (!__ACCOUNTS_CACHE__ || __ACCOUNTS_CACHE__.length === 0) {
    console.log('No accounts in cache, showing empty state');
    showUsersEmptyState();
    return;
  }
  
  let filteredAccounts = [...__ACCOUNTS_CACHE__];
  
  // Apply status filter
  const statusFilter = document.getElementById('accountsStatusFilter')?.value;
  if (statusFilter) {
    filteredAccounts = filteredAccounts.filter(acc => acc.acc_status === statusFilter);
  }
  
  // Apply role filter
  const roleFilter = document.getElementById('accountsRoleFilter')?.value;
  if (roleFilter) {
    filteredAccounts = filteredAccounts.filter(acc => Number(acc.role_id) === Number(roleFilter));
  }
  
  // Apply rank filter
  const rankFilter = document.getElementById('accountsRankFilter')?.value;
  if (rankFilter) {
    filteredAccounts = filteredAccounts.filter(acc => acc.rank === rankFilter);
  }
  
  renderUsersTable(filteredAccounts);
}

// This function is now replaced by renderUsersTable above

function createUserTableRow(account) {
  const row = document.createElement('tr');
  
  const statusBadge = getStatusBadge(account.acc_status);
  const roleBadge = getRoleBadge(account.role_name);
  
  // Format hours - show 0 if null/undefined
  const instHrs = account.instruction_hours ?? 0;
  const resHrs = account.research_hours ?? 0;
  const extHrs = account.extension_hours ?? 0;
  const prodHrs = account.production_hours ?? 0;
  const consHrs = account.consultation_hours ?? 0;
  const totalHrs = account.total_hours ?? 0;
  
  row.innerHTML = `
    <td>${account.acc_id}</td>
    <td>
      <div class="fw-medium">${account.full_name}</div>
    </td>
    <td>
      <span class="text-muted">@${account.acc_user}</span>
    </td>
    <td>${account.acc_email}</td>
    <td>${roleBadge}</td>
    <td>${statusBadge}</td>
    <td>
      ${account.inst_status ? `<span class="badge bg-info">${account.inst_status}</span>` : '-'}
    </td>
    <td>
      ${account.rank ? `<span class="text-muted">${account.rank}</span>` : '-'}
    </td>
    <td>
      ${account.designation && account.designation !== 'None' ? `<span class="text-muted">${account.designation}</span>` : '-'}
    </td>
    <td class="text-center">${instHrs > 0 ? instHrs : '-'}</td>
    <td class="text-center">${resHrs > 0 ? resHrs : '-'}</td>
    <td class="text-center">${extHrs > 0 ? extHrs : '-'}</td>
    <td class="text-center">${prodHrs > 0 ? prodHrs : '-'}</td>
    <td class="text-center">${consHrs > 0 ? consHrs : '-'}</td>
    <td class="text-center fw-bold">${totalHrs > 0 ? totalHrs : '-'}</td>
    <td>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-outline-primary" onclick="editUser(${account.acc_id})" title="Edit User">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${account.acc_id})" title="Delete User">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </td>
  `;
  
  return row;
}

function getStatusBadge(status) {
  const badges = {
    'Active': 'success',
    'Pending': 'warning',
    'Inactive': 'secondary'
  };
  const color = badges[status] || 'secondary';
  return `<span class="badge bg-${color}">${status}</span>`;
}

function getRoleBadge(roleName) {
  const badges = {
    'Moderator': 'info',
    'User': 'primary',
    'Admin': 'danger',
    'Admin support': 'dark'
  };
  const color = badges[roleName] || 'secondary';
  return `<span class="badge bg-${color}">${roleName}</span>`;
}

function attachAccountsFiltersIfNeeded() {
  // Attach event listeners to filter dropdowns
  const statusFilter = document.getElementById('accountsStatusFilter');
  const roleFilter = document.getElementById('accountsRoleFilter');
  const rankFilter = document.getElementById('accountsRankFilter');
  
  if (statusFilter) {
    statusFilter.addEventListener('change', applyAccountsFilters);
  }
  if (roleFilter) {
    roleFilter.addEventListener('change', applyAccountsFilters);
  }
  if (rankFilter) {
    rankFilter.addEventListener('change', applyAccountsFilters);
  }
}

function editUser(accId) {
  console.log('Edit user:', accId);
  
  // Find the user in the accounts cache
  const user = __ACCOUNTS_CACHE__.find(account => account.acc_id == accId);
  if (!user) {
    console.error('User not found:', accId);
    alert('User not found');
    return;
  }
  
  // Populate the edit modal with user data
  document.getElementById('edit_acc_id').value = user.acc_id;
  
  // Account Information
  document.getElementById('edit_fname').value = user.first_name || '';
  document.getElementById('edit_lname').value = user.last_name || '';
  document.getElementById('edit_minitial').value = user.middle_initial || '';
  document.getElementById('edit_acc_user').value = user.acc_user || '';
  document.getElementById('edit_acc_email').value = user.acc_email || '';
  document.getElementById('edit_dept_id').value = user.dept_id || '';
  
  // Instructor Information
  document.getElementById('edit_inst_status').value = user.inst_status || '';
  document.getElementById('edit_rank').value = user.rank || '';
  document.getElementById('edit_designation').value = user.designation || '';
  document.getElementById('edit_inst_email').value = user.inst_email || '';
  document.getElementById('edit_inst_phone').value = user.inst_phone || '';
  
  // Show the edit modal
  const modal = new bootstrap.Modal(document.getElementById('editAccountModal'));
  modal.show();
}

function deleteUser(accId) {
  // Find the user in the accounts cache to get their name
  const user = __ACCOUNTS_CACHE__.find(account => account.acc_id == accId);
  const userName = user ? `${user.first_name} ${user.last_name}` : 'this user';
  
  // Use SweetAlert2 for better confirmation dialog
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Delete User Account',
      html: `Are you sure you want to delete <strong>${userName}</strong>?<br><br>
             <span class="text-danger">This action cannot be undone!</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        performDeleteUser(accId, userName);
      }
    });
  } else {
    // Fallback to regular confirm if SweetAlert2 is not available
    if (confirm(`Are you sure you want to delete ${userName}? This action cannot be undone!`)) {
      performDeleteUser(accId, userName);
    }
  }
}

function performDeleteUser(accId, userName) {
  console.log('Deleting user:', accId);
  
  // Show loading state
  const deleteBtn = event.target;
  const originalText = deleteBtn.innerHTML;
  deleteBtn.disabled = true;
  deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
  
  // Prepare form data
  const formData = new FormData();
  formData.append('acc_id', accId);
  
  // Send delete request
  fetch('delete_account.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove user from cache
      const userIndex = __ACCOUNTS_CACHE__.findIndex(account => account.acc_id == accId);
      if (userIndex >= 0) {
        __ACCOUNTS_CACHE__.splice(userIndex, 1);
      }
      
      // Refresh the table
      applyAccountsFilters();
      
      // Show success message
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Deleted!',
          text: `${userName} has been deleted successfully.`,
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
      } else {
        alert(`${userName} has been deleted successfully.`);
      }
    } else {
      throw new Error(data.message || 'Failed to delete user');
    }
  })
  .catch(error => {
    console.error('Delete user error:', error);
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
    // Restore button state
    deleteBtn.disabled = false;
    deleteBtn.innerHTML = originalText;
  });
}

function showUsersLoadingState() {
  const loadingState = document.getElementById('usersLoadingState');
  const tableContainer = document.getElementById('usersTableContainer');
  const emptyState = document.getElementById('usersEmptyState');
  
  if (loadingState) loadingState.style.display = 'block';
  if (tableContainer) tableContainer.style.display = 'none';
  if (emptyState) emptyState.style.display = 'none';
}

function hideUsersLoadingState() {
  const loadingState = document.getElementById('usersLoadingState');
  const tableContainer = document.getElementById('usersTableContainer');
  
  if (loadingState) loadingState.style.display = 'none';
  if (tableContainer) tableContainer.style.display = 'block';
}

function showUsersEmptyState() {
  const emptyState = document.getElementById('usersEmptyState');
  const tableContainer = document.getElementById('usersTableContainer');
  const loadingState = document.getElementById('usersLoadingState');
  
  if (emptyState) emptyState.style.display = 'block';
  if (tableContainer) tableContainer.style.display = 'none';
  if (loadingState) loadingState.style.display = 'none';
}

function updateUsersStatistics() {
  const totalUsersEl = document.getElementById('totalUsersCount');
  const moderatorsEl = document.getElementById('moderatorsCount');
  const instructorsEl = document.getElementById('instructorsCount');
  const activeUsersEl = document.getElementById('activeUsersCount');
  const pendingUsersEl = document.getElementById('pendingUsersCount');
  const regularUsersEl = document.getElementById('regularUsersCount');
  
  if (totalUsersEl) totalUsersEl.textContent = __USERS_STATS__.total || 0;
  if (moderatorsEl) moderatorsEl.textContent = __USERS_STATS__.moderators || 0;
  if (instructorsEl) instructorsEl.textContent = __USERS_STATS__.instructors || 0;
  if (activeUsersEl) activeUsersEl.textContent = __USERS_STATS__.total || 0; // All users are active by default
  if (pendingUsersEl) pendingUsersEl.textContent = __USERS_STATS__.pending || 0;
  if (regularUsersEl) regularUsersEl.textContent = __USERS_STATS__.regular || 0;
}

function attachAccountsFiltersIfNeeded() {
  const searchEl = document.getElementById('accountsSearch');
  if (searchEl && !searchEl.__bound) {
    searchEl.addEventListener('input', applyAccountsFilters);
    const ids = ['accountsSort','accountsStatusFilter','accountsRoleFilter','accountsRankFilter','accountsAlphaFilter'];
    ids.forEach(id => { const el = document.getElementById(id); if (el && !el.__bound){ el.addEventListener('change', applyAccountsFilters); el.__bound = true; } });
    searchEl.__bound = true;
  }
}

// Removed duplicate function - using the main one below

// Removed duplicate function - using the main one below

function createUserCard(user) {
  const photo = user.photo_url || `https://ui-avatars.com/api/?name=${encodeHtml(user.full_name || user.acc_user || 'User')}&background=800000&color=fff&size=80`;
  const roleBadge = Number(user.role_id) === 3 ? 
    '<span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Moderator</span>' : 
    '<span class="badge bg-info"><i class="bi bi-person-badge me-1"></i>Instructor</span>';
  
  const statusBadge = user.acc_status === 'Active' ? 
    '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>' :
    user.acc_status === 'Pending' ?
    '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>' :
    '<span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Inactive</span>';
  
  const employmentBadge = user.inst_status ? 
    `<span class="badge bg-primary"><i class="bi bi-briefcase me-1"></i>${escapeHtml(user.inst_status)}</span>` : '';
  
  const rankBadge = user.rank ? 
    `<span class="badge bg-light text-dark border"><i class="bi bi-mortarboard me-1"></i>${escapeHtml(user.rank)}</span>` : '';
  
  const designationBadge = user.designation && user.designation !== 'None' ? 
    `<span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i>${escapeHtml(user.designation)}</span>` : '';
  
  const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never';
  
  return `
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 user-card">
        <div class="card-body">
          <div class="d-flex align-items-start mb-3">
            <img src="${photo}" class="rounded-circle me-3" alt="Profile" width="60" height="60" style="object-fit: cover;">
            <div class="flex-grow-1">
              <h6 class="card-title mb-1 fw-bold">${escapeHtml(user.full_name || user.acc_user || '')}</h6>
              <p class="text-muted small mb-2">@${escapeHtml(user.acc_user || '')}</p>
              <div class="d-flex flex-wrap gap-1 mb-2">
                ${roleBadge}
                ${statusBadge}
                ${employmentBadge}
                ${rankBadge}
                ${designationBadge}
              </div>
            </div>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" data-edit-account="${user.acc_id}">
                  <i class="bi bi-pencil me-2"></i>Edit
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="viewUserDetails(${user.acc_id})">
                  <i class="bi bi-eye me-2"></i>View Details
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="deactivateUser(${user.acc_id})">
                  <i class="bi bi-person-x me-2"></i>Deactivate
                </a></li>
              </ul>
            </div>
          </div>
          
          <div class="row g-2 text-center">
            <div class="col-6">
              <div class="border rounded p-2">
                <div class="small text-muted">Email</div>
                <div class="fw-semibold small">${escapeHtml(user.acc_email || '')}</div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded p-2">
                <div class="small text-muted">Last Login</div>
                <div class="fw-semibold small">${lastLogin}</div>
              </div>
            </div>
          </div>
          
          ${user.inst_email ? `
            <div class="mt-3 pt-3 border-top">
              <div class="row g-2 text-center">
                <div class="col-6">
                  <div class="small text-muted">Personal Email</div>
                  <div class="fw-semibold small">${escapeHtml(user.inst_email)}</div>
                </div>
                <div class="col-6">
                  <div class="small text-muted">Phone</div>
                  <div class="fw-semibold small">${escapeHtml(user.inst_phone || 'N/A')}</div>
                </div>
              </div>
            </div>
          ` : ''}
          
          ${user.rank || (user.designation && user.designation !== 'None') ? `
            <div class="mt-3 pt-3 border-top">
              <div class="row g-2 text-center">
                ${user.rank ? `
                  <div class="col-6">
                    <div class="small text-muted">Academic Rank</div>
                    <div class="fw-semibold small">${escapeHtml(user.rank)}</div>
                  </div>
                ` : ''}
                ${user.designation && user.designation !== 'None' ? `
                  <div class="col-6">
                    <div class="small text-muted">Designation</div>
                    <div class="fw-semibold small">${escapeHtml(user.designation)}</div>
                  </div>
                ` : ''}
              </div>
            </div>
          ` : ''}
        </div>
      </div>
    </div>
  `;
}

function viewUserDetails(userId) {
  const user = __ACCOUNTS_CACHE__.find(u => String(u.acc_id) === String(userId));
  if (!user) return;
  
  // Create a detailed view modal or redirect to user details page
  alert(`View details for: ${user.full_name}\nEmail: ${user.acc_email}\nRole: ${user.role_id === 3 ? 'Moderator' : 'Instructor'}\nStatus: ${user.inst_status || 'N/A'}`);
}

function deactivateUser(userId) {
  if (!confirm('Are you sure you want to deactivate this user?')) return;
  
  // Here you would typically make an AJAX call to deactivate the user
  alert('User deactivation functionality would be implemented here.');
}

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function openEditAccountModal(accId) {
  const acc = __ACCOUNTS_CACHE__.find(a => String(a.acc_id) === String(accId));
  if (!acc) return;
  const form = document.getElementById('editAccountForm');
  if (!form) return;
  form.reset();
  form.elements['acc_id'].value = acc.acc_id;
  form.elements['fname'].value = acc.first_name || '';
  form.elements['lname'].value = acc.last_name || '';
  form.elements['minitial'].value = acc.middle_initial || '';
  form.elements['acc_user'].value = acc.acc_user || '';
  form.elements['acc_email'].value = acc.acc_email || '';
  form.elements['rank'].value = acc.rank || '';
  form.elements['designation'].value = acc.designation || '';
  form.elements['inst_status'].value = acc.inst_status || '';
  form.elements['inst_email'].value = acc.inst_email || '';
  form.elements['inst_phone'].value = acc.inst_phone || '';
  
  // Update hint message based on employment status
  updateEmploymentStatusHint(acc.inst_status || '');
  
  const modal = new bootstrap.Modal(document.getElementById('editAccountModal'));
  modal.show();
}

// Function to update employment status hint message
function updateEmploymentStatusHint(status) {
  const hintEl = document.getElementById('inst_status_hint');
  const hintTextEl = document.getElementById('inst_status_hint_text');
  
  if (!hintEl || !hintTextEl) return;
  
  switch(status) {
    case 'Part-Time':
      hintTextEl.textContent = 'Part-Time instructors have a maximum workload of 20 hours per week.';
      hintEl.style.display = 'block';
      hintEl.className = 'form-text text-warning';
      break;
    case 'Contractual':
      hintTextEl.textContent = 'Contractual instructors have a maximum workload of 15 hours per week.';
      hintEl.style.display = 'block';
      hintEl.className = 'form-text text-warning';
      break;
    case 'Regular':
      hintTextEl.textContent = 'Regular instructors have a maximum workload of 40 hours per week (overtime allowed).';
      hintEl.style.display = 'block';
      hintEl.className = 'form-text text-info';
      break;
    default:
      hintEl.style.display = 'none';
  }
}

// Add event listener for employment status change in Edit Account modal
document.addEventListener('DOMContentLoaded', function() {
  const editInstStatus = document.getElementById('edit_inst_status');
  if (editInstStatus) {
    editInstStatus.addEventListener('change', function() {
      updateEmploymentStatusHint(this.value);
    });
  }
});

function saveAccountEdits() {
  const form = document.getElementById('editAccountForm');
  
  // Validate required fields before submitting
  const instStatus = form.elements['inst_status'].value;
  if (!instStatus || instStatus.trim() === '') {
    alert('Please select an Employment Status');
    form.elements['inst_status'].focus();
    return;
  }
  
  const formData = new FormData(form);
  const btn = document.getElementById('saveAccountEditsBtn');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving';
  
  // Debug: Log form data
  console.log('Saving account with inst_status:', instStatus);
  
  // Determine correct path based on current location
  // Determine correct path based on current location
  // Both admin and moderator dashboards use the same admin endpoints
  let updatePath;
  if (window.location.pathname.includes('/views/admin/')) {
    updatePath = '../../admin/management/update_account.php';
  } else if (window.location.pathname.includes('/views/moderator/')) {
    updatePath = '../../admin/management/update_account.php';
  } else {
    updatePath = 'update_account.php';
  }
  
  fetch(updatePath, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error(data.message || 'Failed to update account');
      const id = formData.get('acc_id');
      const idx = __ACCOUNTS_CACHE__.findIndex(a => String(a.acc_id) === String(id));
      if (idx >= 0) {
        __ACCOUNTS_CACHE__[idx] = { ...__ACCOUNTS_CACHE__[idx],
          first_name: formData.get('fname'),
          last_name: formData.get('lname'),
          middle_initial: formData.get('minitial'),
          acc_user: formData.get('acc_user'),
          acc_email: formData.get('acc_email'),
          dept_id: formData.get('dept_id'),
          designation: formData.get('designation'),
          inst_status: formData.get('inst_status'),
          rank: formData.get('rank'),
          inst_email: formData.get('inst_email'),
          inst_phone: formData.get('inst_phone')
        };
        const a = __ACCOUNTS_CACHE__[idx];
        a.full_name = [a.first_name, a.middle_initial, a.last_name].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
      }
      applyAccountsFilters();
      bootstrap.Modal.getInstance(document.getElementById('editAccountModal')).hide();
    })
    .catch(err => alert(err.message))
    .finally(() => { btn.disabled = false; btn.innerHTML = original; });
}

// Profile Picture Management
// NOTE:
// - Uses unified endpoint `shared/profile/upload_profile_picture.php`
// - That script reads the logged-in user from session, so we don't need to send acc_id
// - Form field MUST be named `profile_picture` to match the PHP script
function uploadProfilePicture(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];

    // Validate file type
    if (!file.type.startsWith('image/')) {
      alert('Please select an image file.');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('File size must be less than 5MB.');
      return;
    }

    const formData = new FormData();
    // unified upload script expects `profile_picture`
    formData.append('profile_picture', file);

    // Show loading state
    const uploadBtn = input.previousElementSibling;
    const originalText = uploadBtn ? uploadBtn.innerHTML : '';
    if (uploadBtn) {
      uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
      uploadBtn.disabled = true;
    }

    // Use unified shared upload endpoint (works for Admin, Admin Support, Instructor, etc.)
    // From any dashboard under `/views/*/`, `../../shared/profile/upload_profile_picture.php` is correct.
    fetch('../../shared/profile/upload_profile_picture.php', {
      method: 'POST',
      body: formData
    })
      .then(response => {
        if (!response.ok) {
          return response.text().then(text => {
            throw new Error(`Upload failed (${response.status}). ${text.substring(0, 200)}`);
          });
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          const imagePath = data.image_path || data.profile_picture;

          // Build full URL relative to current location
          const basePath = window.location.origin;
          const fullImageUrl = `${basePath}/public/${imagePath}?t=${Date.now()}`;

          // Update main profile image if present
          const mainImg = document.getElementById('profilePicture');
          if (mainImg) {
            mainImg.src = fullImageUrl;
          }

          // Update sidebar/profile images using helper functions if available
          if (typeof updateSidebarProfilePicture === 'function') {
            updateSidebarProfilePicture(fullImageUrl);
          }
          if (typeof updateAdminSidebarProfilePicture === 'function') {
            updateAdminSidebarProfilePicture(fullImageUrl);
          }

          if (window.Swal) {
            Swal.fire('Success', data.message || 'Profile picture updated successfully!', 'success');
          } else {
            alert(data.message || 'Profile picture updated successfully!');
          }
        } else {
          const msg = data.message || 'Failed to upload image';
          if (window.Swal) {
            Swal.fire('Error', msg, 'error');
          } else {
            alert('Error: ' + msg);
          }
        }
      })
      .catch(error => {
        console.error('Error uploading profile picture:', error);
        const msg = error.message || 'Error uploading image. Please try again.';
        if (window.Swal) {
          Swal.fire('Error', msg, 'error');
        } else {
          alert(msg);
        }
      })
      .finally(() => {
        if (uploadBtn) {
          uploadBtn.innerHTML = originalText;
          uploadBtn.disabled = false;
        }
      });
  }
}

function removeProfilePicture() {
  if (confirm('Are you sure you want to remove your profile picture?')) {
    fetch('../remove_profile_picture.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        acc_id: window.currentUserId || 0
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update to default image
        const defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+';
        document.getElementById('profilePicture').src = defaultImage;
        // Update sidebar profile pictures
        if (typeof updateSidebarProfilePictureToDefault === 'function') {
          updateSidebarProfilePictureToDefault();
        }
        if (typeof updateAdminSidebarProfilePictureToDefault === 'function') {
          updateAdminSidebarProfilePictureToDefault();
        }
        alert('Profile picture removed successfully!');
      } else {
        alert('Error: ' + (data.message || 'Failed to remove image'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error removing image. Please try again.');
    });
  }
}

// Password Management
function changePassword() {
  // Use SweetAlert2 for password input
  Swal.fire({
    title: 'Change Password',
    html: `
      <div class="mb-3">
        <label class="form-label">Current Password</label>
        <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password">
      </div>
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
      </div>
    `,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonColor: '#800000',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Change Password',
    cancelButtonText: 'Cancel',
    customClass: {
      popup: 'swal2-popup-custom',
      title: 'swal2-title-custom',
      content: 'swal2-content-custom'
    },
    preConfirm: () => {
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      
      if (!currentPassword) {
        Swal.showValidationMessage('Current password is required');
        return false;
      }
      
      if (!newPassword) {
        Swal.showValidationMessage('New password is required');
        return false;
      }
      
      if (newPassword !== confirmPassword) {
        Swal.showValidationMessage('Passwords do not match');
        return false;
      }
      
      if (newPassword.length < 6) {
        Swal.showValidationMessage('Password must be at least 6 characters long');
        return false;
      }
      
      return { currentPassword, newPassword, confirmPassword };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const { currentPassword, newPassword, confirmPassword } = result.value;
  
      const formData = new FormData();
      formData.append('current_password', currentPassword);
      formData.append('new_password', newPassword);
      formData.append('confirm_password', confirmPassword);
      
      // Show loading state
      Swal.fire({
        title: 'Changing Password...',
        text: 'Please wait while we update your password.',
        allowOutsideClick: false,
        showConfirmButton: false,
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          content: 'swal2-content-custom'
        },
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      fetch('../change_password.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Password Changed!',
            text: 'Your password has been updated successfully.',
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Great!',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              content: 'swal2-content-custom'
            }
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Password Change Failed',
            text: data.message || 'Failed to change password. Please try again.',
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Try Again',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              content: 'swal2-content-custom'
            }
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Connection Error',
          text: 'Unable to connect to the server. Please try again.',
          confirmButtonColor: '#dc3545',
          confirmButtonText: 'Retry',
          customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            content: 'swal2-content-custom'
          }
        });
      });
    }
  });
}

// Curriculum Management Functions
function openAddCurriculumModal() {
    const addModal = new bootstrap.Modal(document.getElementById('addCurriculumModal'));
    addModal.show();
}

function saveCurriculum() {
    const form = document.getElementById('addCurriculumForm');
    const formData = new FormData(form);
    
    // Validate form
    const requiredFields = ['program', 'year', 'description', 'status'];
    for (let field of requiredFields) {
        if (!formData.get(field)) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Field',
                text: `Please fill in the ${field} field.`,
                confirmButtonColor: '#800000',
                confirmButtonText: 'OK'
            });
            return;
        }
    }
    
    const saveBtn = document.querySelector('#addCurriculumModal .btn-maroon');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    saveBtn.disabled = true;
    
    // Simulate API call (replace with actual endpoint)
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'Curriculum Added!',
            text: 'The curriculum has been successfully added.',
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Great!',
            customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                content: 'swal2-content-custom'
            }
        }).then(() => {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCurriculumModal'));
            modal.hide();
            
            // Reset form
            form.reset();
            
            // Refresh curricula list
            refreshCurricula();
        });
        
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }, 1500);
}

function refreshCurricula() {
    // Simulate loading data
    const refreshBtn = document.querySelector('button[onclick="refreshCurricula()"]');
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing...';
    refreshBtn.disabled = true;
    
    setTimeout(() => {
        // Update summary cards with sample data
        updateSummaryCards();
        
        // Update table (for now, keep the "no data" state)
        // In a real implementation, this would fetch from the server
        
        refreshBtn.innerHTML = originalText;
        refreshBtn.disabled = false;
        
        Swal.fire({
            icon: 'success',
            title: 'Refreshed!',
            text: 'Curriculum data has been refreshed.',
            confirmButtonColor: '#28a745',
            confirmButtonText: 'OK',
            timer: 2000,
            showConfirmButton: false
        });
    }, 1000);
}

function updateSummaryCards() {
    // Sample data - replace with actual API calls
    const sampleData = {
        totalCurricula: 0,
        totalPrograms: 0,
        activeCurricula: 0,
        totalSubjects: 0
    };
    
    document.getElementById('totalCurricula').textContent = sampleData.totalCurricula;
    document.getElementById('totalPrograms').textContent = sampleData.totalPrograms;
    document.getElementById('activeCurricula').textContent = sampleData.activeCurricula;
    document.getElementById('totalSubjects').textContent = sampleData.totalSubjects;
}

function loadSampleCurriculaData() {
    // Sample curriculum data for demonstration
    const sampleCurricula = [
        {
            id: 1,
            program: 'BSIT',
            year: '2024-2025',
            description: 'Bachelor of Science in Information Technology - Updated Curriculum',
            status: 'active',
            subjects: 45
        },
        {
            id: 2,
            program: 'BSCS',
            year: '2024-2025',
            description: 'Bachelor of Science in Computer Science - New Curriculum',
            status: 'draft',
            subjects: 42
        },
        {
            id: 3,
            program: 'BSIS',
            year: '2023-2024',
            description: 'Bachelor of Science in Information Systems - Legacy Curriculum',
            status: 'inactive',
            subjects: 40
        }
    ];
    
    // Update summary cards
    const totalCurricula = sampleCurricula.length;
    const totalPrograms = new Set(sampleCurricula.map(c => c.program)).size;
    const activeCurricula = sampleCurricula.filter(c => c.status === 'active').length;
    const totalSubjects = sampleCurricula.reduce((sum, c) => sum + c.subjects, 0);
    
    document.getElementById('totalCurricula').textContent = totalCurricula;
    document.getElementById('totalPrograms').textContent = totalPrograms;
    document.getElementById('activeCurricula').textContent = activeCurricula;
    document.getElementById('totalSubjects').textContent = totalSubjects;
    
    // Update table
    updateCurriculaTable(sampleCurricula);
}

function updateCurriculaTable(curricula) {
    const tbody = document.getElementById('curriculaTableBody');
    
    if (curricula.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="text-muted">
                        <i class="bi bi-folder fs-1 d-block mb-3"></i>
                        <h5>No curricula found</h5>
                        <p class="mb-0">Start by adding your first curriculum</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = curricula.map((curriculum, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>
                <span class="fw-semibold">${curriculum.program}</span>
                <br>
                <small class="text-muted">${getProgramFullName(curriculum.program)}</small>
            </td>
            <td>${curriculum.year}</td>
            <td>${curriculum.description}</td>
            <td>
                <span class="badge badge-${curriculum.status}">
                    ${curriculum.status.charAt(0).toUpperCase() + curriculum.status.slice(1)}
                </span>
            </td>
            <td>
                <span class="fw-semibold">${curriculum.subjects}</span>
                <br>
                <small class="text-muted">subjects</small>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="viewCurriculum(${curriculum.id})" title="View">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="editCurriculum(${curriculum.id})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteCurriculum(${curriculum.id})" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getProgramFullName(programCode) {
    const programs = {
        'BSIT': 'Bachelor of Science in Information Technology',
        'BSCS': 'Bachelor of Science in Computer Science',
        'BSIS': 'Bachelor of Science in Information Systems'
    };
    return programs[programCode] || programCode;
}

function viewCurriculum(id) {
    Swal.fire({
        icon: 'info',
        title: 'View Curriculum',
        text: `Viewing curriculum with ID: ${id}`,
        confirmButtonColor: '#800000',
        confirmButtonText: 'OK'
    });
}

function deleteCurriculum(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Curriculum',
        text: 'Are you sure you want to delete this curriculum?',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'The curriculum has been deleted.',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'OK'
            });
        }
    });
}

function loadCurriculaData() {
    // This function would load actual curriculum data from the server
    // For now, load sample data for demonstration
    loadSampleCurriculaData();
}

// Initialize curriculum modal when opened
document.addEventListener('DOMContentLoaded', function() {
    const curriculumModal = document.getElementById('curriculumModal');
    if (curriculumModal) {
        curriculumModal.addEventListener('shown.bs.modal', function() {
            loadCurriculaData();
        });
    }
    
    // Handle filter changes
    const programFilter = document.getElementById('programFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (programFilter) {
        programFilter.addEventListener('change', function() {
            // Filter curricula by program
            console.log('Filtering by program:', this.value);
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            // Filter curricula by status
            console.log('Filtering by status:', this.value);
        });
    }
});

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
  const changePhotoBtn = document.getElementById('changePhotoBtn');
  const profilePhotoInput = document.getElementById('profilePhotoInput');
  const profilePreview = document.getElementById('profilePreview');
  const adminProfileImg = document.getElementById('adminProfileImg');

  // Load current profile data
  loadProfileData();
  
  // Restore last active tab (default to dashboard)
  let initialTab = 'overview';
  try { initialTab = localStorage.getItem('admin_active_tab') || 'overview'; } catch (e) {}
  const sidebarLink = document.querySelector(`.sidebar .nav-link[onclick*="${initialTab}"]`);
  showTab(initialTab, sidebarLink);

  // Photo change functionality
  if (changePhotoBtn && profilePhotoInput) {
    changePhotoBtn.addEventListener('click', function() {
      profilePhotoInput.click();
    });

    profilePhotoInput.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          profilePreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Initialize modal event listeners
  const modal = document.getElementById('addAccountModal');
  if (modal) {
    modal.addEventListener('hidden.bs.modal', function() {
      // Reset form when modal is closed
      const form = document.getElementById('addAccountForm');
      if (form) {
        form.reset();
        // Remove any validation classes
        form.querySelectorAll('.is-invalid').forEach(field => {
          field.classList.remove('is-invalid');
        });
        // Reset role-based field states
        resetRoleBasedFields();
      }
    });
  }
  
  // Handle role selection change for add account form
  const roleSelect = document.getElementById('add_role_id');
  if (roleSelect) {
    roleSelect.addEventListener('change', function() {
      handleRoleChange(this.value);
    });
  }
  
  // Function to handle role selection changes
  function handleRoleChange(roleId) {
    const rankField = document.getElementById('add_rank');
    const designationField = document.getElementById('add_designation');
    
    if (roleId === '3') { // Moderator role
      // Disable academic rank and designation for moderators
      if (rankField) {
        rankField.disabled = true;
        rankField.required = false;
        rankField.value = ''; // Clear the value
        rankField.classList.add('text-muted');
      }
      if (designationField) {
        designationField.disabled = true;
        designationField.value = 'None'; // Set to default
        designationField.classList.add('text-muted');
      }
    } else { // Instructor role or other roles
      // Enable academic rank and designation for instructors
      if (rankField) {
        rankField.disabled = false;
        rankField.required = true;
        rankField.classList.remove('text-muted');
      }
      if (designationField) {
        designationField.disabled = false;
        designationField.classList.remove('text-muted');
      }
    }
  }
  
  // Function to reset role-based fields to default state
  function resetRoleBasedFields() {
    const rankField = document.getElementById('add_rank');
    const designationField = document.getElementById('add_designation');
    
    if (rankField) {
      rankField.disabled = false;
      rankField.required = true;
      rankField.value = '';
      rankField.classList.remove('text-muted');
    }
    if (designationField) {
      designationField.disabled = false;
      designationField.value = 'None';
      designationField.classList.remove('text-muted');
    }
  }
});
function applyAccountsFilters() {
  console.log("🔥 applyAccountsFilters function called!");

  try {
    if (!Array.isArray(__ACCOUNTS_CACHE__)) {
      console.error("❌ __ACCOUNTS_CACHE__ is not an array:", __ACCOUNTS_CACHE__);
      return;
    }

    console.log("✅ __ACCOUNTS_CACHE__ length:", __ACCOUNTS_CACHE__.length);

    const q = (document.getElementById('accountsSearch')?.value || '').toLowerCase().trim();
    const sort = document.getElementById('accountsSort')?.value || 'newest';
    const status = document.getElementById('accountsStatusFilter')?.value || '';
    const role = document.getElementById('accountsRoleFilter')?.value || '';
    const rank = document.getElementById('accountsRankFilter')?.value || '';
    const alpha = document.getElementById('accountsAlphaFilter')?.value || '';

    let list = __ACCOUNTS_CACHE__.slice();
    console.log("📦 Before filters, users:", list);

    // Filters
    if (q) list = list.filter(a => 
      (a.full_name || '').toLowerCase().includes(q) ||
      (a.acc_user || '').toLowerCase().includes(q) ||
      (a.acc_email || '').toLowerCase().includes(q)
    );
    if (status) list = list.filter(a => (a.inst_status || '') === status);
    if (role) list = list.filter(a => String(a.role_id) === String(role));
    if (rank) list = list.filter(a => (a.rank || '') === rank);
    if (alpha === 'A-M') list = list.filter(a => /^[a-mA-M]/.test((a.last_name || a.full_name || '')));
    if (alpha === 'N-Z') list = list.filter(a => /^[n-zN-Z]/.test((a.last_name || a.full_name || '')));

    // Sorting
    list.sort((a, b) => {
      const aid = Number(a.acc_id || 0), bid = Number(b.acc_id || 0);
      if (sort === 'newest') return bid - aid;
      if (sort === 'oldest') return aid - bid;
      const an = (a.full_name || '').toLowerCase();
      const bn = (b.full_name || '').toLowerCase();
      if (sort === 'name_asc') return an.localeCompare(bn);
      if (sort === 'name_desc') return bn.localeCompare(an);
      return 0;
    });

    console.log("📊 After filters, users:", list);

    // Render results using the table-based function
    renderUsersTable(list);

  } catch (err) {
    console.error("❌ Error in applyAccountsFilters:", err);
  }
}

function renderUsersTable(users) {
  console.log("🔥 renderUsersTable called with users:", users);

  try {
    const tableBody = document.getElementById('usersTableBody');
    const tableContainer = document.getElementById('usersTableContainer');
    
    if (!tableBody || !tableContainer) {
      console.error("❌ Table elements not found:", {
        tableBody: !!tableBody,
        tableContainer: !!tableContainer
      });
      return;
    }

    if (!Array.isArray(users) || users.length === 0) {
      console.warn("⚠️ No users to render");
      showUsersEmptyState();
      return;
    }

    // Clear existing rows
    tableBody.innerHTML = '';
    
    // Add user rows
    users.forEach(account => {
      const tableRow = createUserTableRow(account);
      tableBody.appendChild(tableRow);
    });
    
    // Show table and hide empty state
    tableContainer.style.display = 'block';
    const emptyState = document.getElementById('usersEmptyState');
    if (emptyState) emptyState.style.display = 'none';
    
    console.log(`✅ Rendered ${users.length} users in table`);

  } catch (err) {
    console.error("❌ Error in renderUsersTable:", err);
  }
}

// Course Management Functions
// Prevent redeclaration error
if (typeof __COURSE_CACHE__ === 'undefined') {
  var __COURSE_CACHE__ = {};
}

function loadCourseData() {
  console.log('Loading course data...');
  
  // Determine correct path based on current location
  // Determine correct path based on current location
  // Both admin and moderator dashboards use the same admin endpoints
  let coursePath;
  if (window.location.pathname.includes('/views/admin/')) {
    coursePath = '../../admin/management/get_course_data.php';
  } else if (window.location.pathname.includes('/views/moderator/')) {
    coursePath = '../../admin/management/get_course_data.php';
  } else {
    coursePath = 'get_course_data.php';
  }
  
  fetch(coursePath)
    .then(response => {
      if (!response.ok) {
        if (response.status === 403) {
          return response.json().then(data => {
            if (data.message && data.message.includes('permission')) {
              console.warn('⚠️ Permission denied for course data');
              return { success: false, permissionError: true };
            }
            throw new Error('Authentication required');
          });
        }
        if (response.status === 404) {
          console.error('❌ Course endpoint not found:', coursePath);
          throw new Error(`Course endpoint not found`);
        }
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      // Handle permission errors silently
      if (data.permissionError) {
        console.warn('⚠️ Permission error for course data - suppressing error');
        return;
      }
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

// Curriculum Management Functions
// Note: The actual implementation is in admin/assets/js/admin_dashboard.js
// This is just a placeholder to ensure the function exists before admin_dashboard.js loads
// Curriculum Management Functions
// Note: The actual implementation is in admin/assets/js/admin_dashboard.js
// Since admin_dashboard.js loads BEFORE this file, we should NOT override the real function
// Only define stub if the real function doesn't exist (shouldn't happen in normal flow)
if (typeof loadCurriculumData === 'undefined') {
  function loadCurriculumData() {
    console.warn('⚠️ Stub loadCurriculumData() called - admin_dashboard.js should have loaded first');
    console.warn('   This stub should not be used. Check script loading order.');
  }
}

function showCurriculumLoading(show) {
  const loading = document.getElementById('curriculumLoading');
  const grid = document.getElementById('curriculumGrid');
  const empty = document.getElementById('curriculumEmpty');
  
  if (show) {
    if (loading) loading.style.display = 'block';
    if (grid) grid.style.display = 'none';
    if (empty) empty.style.display = 'none';
  } else {
    if (loading) loading.style.display = 'none';
    if (grid) grid.style.display = 'block';
  }
}

function updateCurriculumStats(data) {
  const totalCurriculums = document.getElementById('totalCurriculums');
  const activeCurriculums = document.getElementById('activeCurriculums');
  const pendingCurriculums = document.getElementById('pendingCurriculums');
  const totalSubjects = document.getElementById('totalSubjects');
  
  if (totalCurriculums) totalCurriculums.textContent = data.totalCurriculums;
  if (activeCurriculums) activeCurriculums.textContent = data.activeCurriculums;
  if (pendingCurriculums) pendingCurriculums.textContent = data.pendingCurriculums;
  if (totalSubjects) totalSubjects.textContent = data.totalSubjects;
}

function renderCurriculumGrid(curriculums) {
  const grid = document.getElementById('curriculumGrid');
  const empty = document.getElementById('curriculumEmpty');
  
  if (!curriculums || curriculums.length === 0) {
    if (grid) grid.style.display = 'none';
    if (empty) empty.style.display = 'block';
    return;
  }
  
  if (grid) grid.style.display = 'block';
  if (empty) empty.style.display = 'none';
  
  grid.innerHTML = curriculums.map(curriculum => `
    <div class="col-lg-6 col-xl-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h6 class="card-title mb-1">${curriculum.name}</h6>
              <small class="text-muted">${curriculum.code} • ${curriculum.year}</small>
            </div>
            <span class="badge ${getStatusBadgeClass(curriculum.status)}">${curriculum.status}</span>
          </div>
          <p class="card-text text-muted small mb-3">${curriculum.description}</p>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              <i class="bi bi-book me-1"></i>${curriculum.subjects} subjects
            </small>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="viewCurriculum(${curriculum.id})" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-success" onclick="editCurriculum(${curriculum.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="deleteCurriculum(${curriculum.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `).join('');
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'active': return 'bg-success';
    case 'pending': return 'bg-warning';
    case 'inactive': return 'bg-secondary';
    default: return 'bg-secondary';
  }
}

function refreshCurriculumData() {
  loadCurriculumData();
}

function filterCurriculums() {
  const searchTerm = document.getElementById('curriculumSearchInput').value.toLowerCase();
  const statusFilter = document.getElementById('curriculumStatusFilter').value;
  const yearFilter = document.getElementById('curriculumYearFilter').value;
  
  // This would filter the existing curriculum data
  // For now, just reload the data
  loadCurriculumData();
}

function openAddCurriculumModal() {
  const modal = new bootstrap.Modal(document.getElementById('addCurriculumModal'));
  modal.show();
}

function saveCurriculum() {
  const form = document.getElementById('addCurriculumForm');
  const formData = new FormData(form);
  const btn = document.getElementById('saveCurriculumBtn');
  const original = btn.innerHTML;
  
  // Validate required fields
  const requiredFields = ['curriculum_name', 'curriculum_code', 'academic_year', 'status', 'duration'];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      alert(`Please fill in the ${field.replace('_', ' ')} field.`);
      return;
    }
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  // Simulate API call - replace with actual endpoint
  setTimeout(() => {
    try {
      // Mock success response
      const mockResponse = {
        success: true,
        message: 'Curriculum added successfully!',
        data: {
          id: Math.floor(Math.random() * 1000),
          name: formData.get('curriculum_name'),
          code: formData.get('curriculum_code'),
          year: formData.get('academic_year'),
          status: formData.get('status'),
          duration: formData.get('duration'),
          units: formData.get('total_units'),
          description: formData.get('description'),
          objectives: formData.get('objectives')
        }
      };
      
      if (mockResponse.success) {
        alert('Curriculum added successfully!');
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('addCurriculumModal')).hide();
        
        // Refresh curriculum data
        loadCurriculumData();
      } else {
        throw new Error(mockResponse.message || 'Failed to add curriculum');
      }
    } catch (error) {
      alert('Error: ' + error.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  }, 1500);
}

function viewCurriculum(id) {
  alert(`View curriculum details for ID: ${id}`);
}

function deleteCurriculum(id) {
  if (confirm('Are you sure you want to delete this curriculum?')) {
    alert(`Delete curriculum with ID: ${id}`);
    loadCurriculumData(); // Refresh the list
  }
}

// Program/Course Management Functions
// function openAddProgramModal() {
//   const modal = new bootstrap.Modal(document.getElementById('addProgramModal'));
//   modal.show();
// }

function openAddCurriculumModal() {
  const modal = new bootstrap.Modal(document.getElementById('addCurriculumModal'));
  modal.show();
}

function openAddSubjectModal() {
  const modal = new bootstrap.Modal(document.getElementById('addSubjectModal'));
  modal.show();
}

function saveProgram() {
  const form = document.getElementById('addProgramForm');
  const formData = new FormData(form);
  const btn = document.getElementById('saveProgramBtn');
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
      bootstrap.Modal.getInstance(document.getElementById('addProgramModal')).hide();
      
      // Refresh course data if course management tab is active
      const courseTab = document.getElementById('course_management');
      if (courseTab && courseTab.style.display !== 'none') {
        if (typeof loadCourseData === 'function') {
          loadCourseData();
        }
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

function saveCurriculum() {
  const form = document.getElementById('addCurriculumForm');
  const formData = new FormData(form);
  const btn = document.getElementById('saveCurriculumBtn');
  const original = btn.innerHTML;
  
  // Validate required fields
  const requiredFields = ['curr_name', 'dept_id'];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      alert(`Please fill in the ${field.replace('_', ' ')} field.`);
      return;
    }
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  // Send data to backend
  fetch('add_curriculum.php', {
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
          text: data.message || 'Curriculum added successfully!',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
      } else {
        alert(data.message || 'Curriculum added successfully!');
      }
      
      // Reset form and close modal
      form.reset();
      bootstrap.Modal.getInstance(document.getElementById('addCurriculumModal')).hide();
      
      // Refresh course data if course management tab is active
      const courseTab = document.getElementById('course_management');
      if (courseTab && courseTab.style.display !== 'none') {
        if (typeof loadCourseData === 'function') {
          loadCourseData();
        }
      }
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
  const btn = document.getElementById('saveSubjectBtn');
  const original = btn.innerHTML;
  
  // Validate required fields
  const requiredFields = ['subject_name', 'subject_code', 'dept_id'];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      alert(`Please fill in the ${field.replace('_', ' ')} field.`);
      return;
    }
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  // Send data to backend
  fetch('add_subject.php', {
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
          text: data.message || 'Subject added successfully!',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
      } else {
        alert(data.message || 'Subject added successfully!');
      }
      
      // Reset form and close modal
      form.reset();
      bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
      
      // Refresh course data if course management tab is active
      const courseTab = document.getElementById('course_management');
      if (courseTab && courseTab.style.display !== 'none') {
        if (typeof loadCourseData === 'function') {
          loadCourseData();
        }
      }
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

function saveCurriculum(formId, modalId) {
    const form = document.getElementById(formId);
    if (!form) {
        console.error('Form not found:', formId);
        return;
    }
    const formData = new FormData(form);
    const btn = form.closest('.modal-content').querySelector('.btn-primary');
    const original = btn.innerHTML;

    // Basic Frontend Validation
    const requiredFields = ['curr_name', 'dept_id', 'curr_lvl', 'curr_yr'];
    for (const fieldName of requiredFields) {
        if (!formData.get(fieldName)) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: `Please fill in the required field: ${fieldName.replace('_', ' ')}`,
                confirmButtonColor: '#800000'
            });
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch('../../admin/management/add_curriculum.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Curriculum added successfully!',
                timer: 2000,
                showConfirmButton: false
            });

            const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modal) {
                modal.hide();
            }
            form.reset();

            // Refresh data if the relevant tab is active
            if (typeof loadCourseData === 'function' && document.getElementById('course_management')?.style.display !== 'none') {
                loadCourseData();
            }
            if (typeof loadCurriculumData === 'function' && document.getElementById('curriculum_management')?.style.display !== 'none') {
                loadCurriculumData();
            }
        } else {
            throw new Error(data.message || 'Failed to add curriculum.');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: error.message
        });
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = original;
    });
}