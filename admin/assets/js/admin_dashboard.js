/**
 * Admin Dashboard JavaScript
 * Extracted from admin_dashboard.php for better organization
 */

// ============================================================
// GLOBAL TAB STATE REGISTRY - Initialize FIRST before any code runs
// ============================================================
// This Set tracks all initialized tabs to ensure idempotent initialization.
// Each tab initializes exactly once, regardless of how it's activated.
// 
// Why this is needed:
// - Tabs can be activated programmatically (bootstrap.Tab.show())
// - Tabs can be activated by user clicks (isTrusted: true)
// - Tabs can be activated by custom readiness logic
// - Without this registry, tabs initialize multiple times causing:
//   - Duplicate API calls
//   - Duplicate DataTable initializations
//   - Race conditions
//   - Noisy console logs
// ============================================================
window.TAB_STATE = window.TAB_STATE || new Set();

// DEBUG_MODE: Controls verbose logging and stack traces
// Set to true for detailed debugging, false for production
window.DEBUG_MODE = window.DEBUG_MODE !== undefined ? window.DEBUG_MODE : false;

/**
 * Initialize a tab exactly once (idempotent)
 * 
 * This helper ensures tabs initialize only once, regardless of:
 * - How many times the function is called
 * - Whether activation is programmatic or user-initiated
 * - Timing delays or race conditions
 * 
 * @param {string} tabId - The unique identifier for the tab
 * @param {Function} initCallback - The initialization function to run once
 * @param {Object} options - Optional configuration
 * @param {boolean} options.force - Force re-initialization even if already initialized
 * @returns {boolean} - Returns true if initialization occurred, false if skipped
 * 
 * @example
 * initTabOnce('rooms-tab', () => {
 *   initializeRoomsTab();
 * });
 */
window.initTabOnce = function(tabId, initCallback, options = {}) {
  if (!tabId || typeof tabId !== 'string') {
    console.error('[TAB STATE] Invalid tabId provided to initTabOnce:', tabId);
    return false;
  }
  
  if (!initCallback || typeof initCallback !== 'function') {
    console.error('[TAB STATE] Invalid callback provided to initTabOnce for tab:', tabId);
    return false;
  }
  
  // Check if already initialized (unless force is true)
  if (!options.force && window.TAB_STATE.has(tabId)) {
    if (window.DEBUG_MODE) {
      console.log('[TAB STATE] Tab already initialized, skipping:', tabId);
    }
    return false;
  }
  
  try {
    // Mark as initializing immediately to prevent race conditions
    window.TAB_STATE.add(tabId);
    
    if (window.DEBUG_MODE) {
      console.log('[TAB STATE] Initializing tab:', tabId);
    }
    
    // Execute the initialization callback
    initCallback();
    
    return true;
  } catch (error) {
    // On error, remove from registry to allow retry
    window.TAB_STATE.delete(tabId);
    console.error('[TAB STATE] Error initializing tab:', tabId, error);
    throw error;
  }
};

// Dashboard Data Loading Functions
function loadDashboardData() {
  // Check if old overview stat elements exist (new overview doesn't have them)
  const totalUsersEl = document.getElementById("totalUsers");
  const totalInstructorsEl = document.getElementById("totalInstructors");
  const totalRoomsEl = document.getElementById("totalRooms");
  const totalSchedulesEl = document.getElementById("totalSchedules");

  // If none of the old stat elements exist, this is the new overview - skip loading
  if (
    !totalUsersEl &&
    !totalInstructorsEl &&
    !totalRoomsEl &&
    !totalSchedulesEl
  ) {
    console.log("New overview detected - skipping dashboard data load");
    return;
  }

  console.log("Loading dashboard data...");

  fetch("../../admin/reports/get_dashboard_data.php")
    .then((response) => {
      console.log("Dashboard response status:", response.status);
      if (!response.ok) {
        if (response.status === 403) {
          throw new Error("Authentication required - please log in again");
        }
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Dashboard data loaded:", data);
      if (data.success) {
        updateDashboardStatistics(data.data);
        // Only load accounts data if user has permission and the Users tab is visible
        // Check if Users tab exists in sidebar (it will be hidden if user doesn't have permission)
        const usersTabLink = document.querySelector(
          'a[onclick*="roles"], a[href="#roles"]'
        );
        if (usersTabLink && usersTabLink.offsetParent !== null) {
          // Tab is visible, user has permission - load accounts
          loadAccounts();
        } else {
          console.log("Users tab not visible - skipping loadAccounts()");
        }
      } else {
        console.error("Dashboard data error:", data.message);
        showDashboardError(
          "Dashboard Error: " + (data.message || "Unknown error")
        );
        // Try to load basic stats as fallback
        loadBasicStats();
      }
    })
    .catch((error) => {
      console.error("Dashboard data fetch error:", error);
      console.error("Error details:", {
        name: error.name,
        message: error.message,
        stack: error.stack,
      });

      let errorMessage = "Failed to load dashboard data. ";
      if (error.message.includes("Authentication required")) {
        errorMessage += "Please log in again.";
      } else if (error.message.includes("403")) {
        errorMessage += "Access denied. Please check your permissions.";
      } else if (error.message.includes("404")) {
        errorMessage += "Dashboard service not found.";
      } else if (error.message.includes("500")) {
        errorMessage += "Server error. Please try again later.";
      } else {
        errorMessage += "Please refresh the page.";
      }

      showDashboardError(errorMessage);
      // Try to load basic stats as fallback
      loadBasicStats();
    });
}

function showDashboardError(message) {
  console.error("Dashboard Error:", message);

  // Show error in the overview statistics
  const errorElements = [
    "totalUsers",
    "activeUsers",
    "pendingUsers",
    "inactiveUsers",
    "totalUsersCount",
    "moderatorsCount",
    "instructorsCount",
    "activeUsersCount",
    "pendingUsersCount",
    "regularUsersCount",
  ];

  errorElements.forEach((id) => {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = "Error";
      element.style.color = "red";
    }
  });

  // Show a user-friendly error message
  const errorDiv = document.createElement("div");
  errorDiv.className = "alert alert-danger alert-dismissible fade show";
  errorDiv.innerHTML = `
    <strong>Error:</strong> ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;

  // Insert at the top of the dashboard content
  const dashboardContent =
    document.querySelector(".dashboard-content") || document.body;
  dashboardContent.insertBefore(errorDiv, dashboardContent.firstChild);
}

function updateDashboardStatistics(stats) {
  console.log("Updating dashboard statistics:", stats);

  // Update overview statistics
  const totalUsersEl = document.getElementById("totalUsers");
  const activeUsersEl = document.getElementById("activeUsers");
  const pendingUsersEl = document.getElementById("pendingUsers");
  const inactiveUsersEl = document.getElementById("inactiveUsers");

  console.log("Overview elements found:", {
    totalUsers: !!totalUsersEl,
    activeUsers: !!activeUsersEl,
    pendingUsers: !!pendingUsersEl,
    inactiveUsers: !!inactiveUsersEl,
  });

  if (totalUsersEl) totalUsersEl.textContent = stats.users.total_users || 0;
  if (activeUsersEl) activeUsersEl.textContent = stats.users.active_users || 0;
  if (pendingUsersEl)
    pendingUsersEl.textContent = stats.users.pending_users || 0;
  if (inactiveUsersEl)
    inactiveUsersEl.textContent = stats.users.inactive_users || 0;

  // Update user management component statistics
  console.log("Updating user management statistics:", stats.users);
  const totalUsersCountEl = document.getElementById("totalUsersCount");
  const activeUsersCountEl = document.getElementById("activeUsersCount");
  const pendingUsersCountEl = document.getElementById("pendingUsersCount");
  const moderatorsEl = document.getElementById("moderatorsCount");
  const instructorsEl = document.getElementById("instructorsCount");
  const regularUsersEl = document.getElementById("regularUsersCount");

  if (totalUsersCountEl)
    totalUsersCountEl.textContent = stats.users.total_users || 0;
  if (activeUsersCountEl)
    activeUsersCountEl.textContent = stats.users.active_users || 0;
  if (pendingUsersCountEl)
    pendingUsersCountEl.textContent = stats.users.pending_users || 0;
  if (moderatorsEl)
    moderatorsEl.textContent = stats.users.moderators_count || 0;
  if (instructorsEl)
    instructorsEl.textContent = stats.instructors.total_instructors || 0;
  if (regularUsersEl)
    regularUsersEl.textContent = stats.instructors.regular_instructors || 0;

  console.log("User management statistics updated");

  // Update instructor statistics
  const totalInstructorsEl = document.getElementById("totalInstructors");
  const regularInstructorsEl = document.getElementById("regularInstructors");
  const parttimeInstructorsEl = document.getElementById("parttimeInstructors");
  const contractualInstructorsEl = document.getElementById(
    "contractualInstructors"
  );
  const pendingInstructorsEl = document.getElementById("pendingInstructors");

  if (totalInstructorsEl)
    totalInstructorsEl.textContent = stats.instructors.total_instructors || 0;
  if (regularInstructorsEl)
    regularInstructorsEl.textContent =
      stats.instructors.regular_instructors || 0;
  if (parttimeInstructorsEl)
    parttimeInstructorsEl.textContent =
      stats.instructors.parttime_instructors || 0;
  if (contractualInstructorsEl)
    contractualInstructorsEl.textContent =
      stats.instructors.contractual_instructors || 0;
  if (pendingInstructorsEl)
    pendingInstructorsEl.textContent =
      stats.instructors.pending_instructors || 0;
}

function loadBasicStats() {
  console.log("Loading basic stats as fallback...");
  fetch("../../admin/reports/get_basic_stats.php")
    .then((response) => {
      console.log("Basic stats response status:", response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Basic stats loaded:", data);
      if (data.success) {
        // Update with basic stats
        const totalUsersEl = document.getElementById("totalUsers");
        const totalUsersCountEl = document.getElementById("totalUsersCount");
        const activeUsersEl = document.getElementById("activeUsers");
        const activeUsersCountEl = document.getElementById("activeUsersCount");
        const pendingUsersEl = document.getElementById("pendingUsers");
        const pendingUsersCountEl =
          document.getElementById("pendingUsersCount");

        if (totalUsersEl)
          totalUsersEl.textContent = data.data.users.total_users || 0;
        if (totalUsersCountEl)
          totalUsersCountEl.textContent = data.data.users.total_users || 0;
        if (activeUsersEl)
          activeUsersEl.textContent = data.data.users.active_users || 0;
        if (activeUsersCountEl)
          activeUsersCountEl.textContent = data.data.users.active_users || 0;
        if (pendingUsersEl)
          pendingUsersEl.textContent = data.data.users.pending_users || 0;
        if (pendingUsersCountEl)
          pendingUsersCountEl.textContent = data.data.users.pending_users || 0;

        console.log("Basic stats updated successfully");
      } else {
        console.error("Basic stats error:", data.message);
        showDashboardError(
          "Failed to load basic statistics: " +
            (data.message || "Unknown error")
        );
      }
    })
    .catch((error) => {
      console.error("Basic stats fetch error:", error);
      showDashboardError(
        "Failed to load any dashboard data. Please check your connection and refresh the page."
      );
    });
}

/**
 * Shows a permission error modal when user doesn't have access
 * Only shows error if the tab is visible in sidebar (user might have permission but API failed)
 * @param {string} pageName - Name of the page/tab (e.g., "Subjects", "Schedules")
 */
function showPermissionError(pageName) {
  // Map page names to tab IDs/selectors
  const tabMap = {
    Users: "roles",
    Rooms: "room_requests",
    Schedules: "schedule",
    Subjects: "curriculum",
    Curriculum: "curriculum_management",
  };

  const tabId = tabMap[pageName];
  if (tabId) {
    // Check if tab is visible in sidebar - if not, user doesn't have permission (silent)
    const tabLink = document.querySelector(
      `a[onclick*="${tabId}"], a[href="#${tabId}"]`
    );
    if (!tabLink || tabLink.offsetParent === null) {
      console.log(
        `Tab "${pageName}" not visible in sidebar - silently ignoring permission error`
      );
      return; // Don't show error if tab is hidden (user doesn't have permission)
    }
  }

  // Tab is visible but API returned error - show error message
  Swal.fire({
    icon: "error",
    title: `Error Loading ${pageName}`,
    text: `Unauthorized access. You do not have permission to view ${pageName.toLowerCase()}.`,
    footer: "Access denied. Please check your permissions.",
    confirmButtonText: "OK",
  });
}

/**
 * Checks if user has permission to access a specific tab
 * @param {string} tabId - The tab ID to check
 * @param {Array} requiredPermissions - Array of permission strings (user needs at least one)
 * @returns {Promise<boolean>} - True if user has permission, false otherwise
 */
async function checkTabPermission(tabId, requiredPermissions) {
  try {
    // Make a test API call to check permissions
    // We'll use a lightweight endpoint that checks permissions
    const testEndpoints = {
      schedule: "../../admin/management/get_schedules.php?test=1",
      curriculum:
        "../../admin/management/get_subjects.php?draw=0&start=0&length=1",
      curriculum_management: "../../admin/management/get_curricula.php?test=1",
      course_management: "../../admin/management/get_programs.php?test=1",
      roomManagementTabs: "../../admin/management/get_rooms.php?test=1",
      roles: "../../admin/management/get_accounts.php?test=1",
    };

    const endpoint = testEndpoints[tabId];
    if (!endpoint) {
      // If no endpoint defined, assume permission is granted (for tabs like overview)
      return true;
    }

    const response = await fetch(endpoint);

    // If 403, user doesn't have permission
    if (response.status === 403) {
      return false;
    }

    // If 200 or other status, check the response
    if (response.ok) {
      const data = await response.json();
      // Some APIs return success: false with error message for unauthorized
      if (
        data.success === false &&
        (data.message?.includes("Unauthorized") ||
          data.error?.includes("Unauthorized"))
      ) {
        return false;
      }
    }

    return true;
  } catch (error) {
    console.error("Error checking permission:", error);
    // On error, allow access (fail open) - the actual API will handle the error
    return true;
  }
}

// ============================================================
// Tab Navigation - Bootstrap 5 Native Implementation
// ============================================================
// REMOVED: showTab() function - Bootstrap handles tabs natively
// Main tabs now use Bootstrap tab structure with data-bs-toggle="tab"
// All tab switching is handled by Bootstrap automatically
// Initialization happens ONLY in shown.bs.tab event handler
// ============================================================

// Track if user has explicitly selected a tab (prevents auto-reset to overview)
window.userSelectedTab = null;
window.tabInitialized = false;

/**
 * Legacy showTab() - DEPRECATED
 * Kept for backward compatibility with external code that may call it
 * Now just delegates to Bootstrap Tab API
 * 
 * @deprecated Use Bootstrap tabs natively - this function will be removed
 */
/**
 * Resolve sidebar tab trigger so desktop/mobile duplicates don't pick the hidden offcanvas first.
 */
function resolveMainTabTrigger(tabId, element) {
  if (element && element.classList && element.classList.contains("nav-link")) {
    return element;
  }
  const isDesktop = window.innerWidth >= 992;
  if (isDesktop) {
    const desktop = document.querySelector(
      `.sidebar.d-none.d-lg-flex a.nav-link[href="#${tabId}"]`
    );
    if (desktop) return desktop;
  }
  const mobile = document.querySelector(
    `.sidebar-offcanvas a.nav-link[href="#${tabId}"]`
  );
  if (mobile) return mobile;
  return document.querySelector(`a.nav-link[href="#${tabId}"]`);
}

window.showTab = function (tabId, element) {
  // Close mobile sidebar if open (full width when activating from avatar/name)
  const offcanvasEl = document.getElementById("sidebarOffcanvas");
  if (offcanvasEl && typeof bootstrap !== "undefined" && bootstrap.Offcanvas) {
    const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
    if (bsOffcanvas && bsOffcanvas._isShown && window.innerWidth < 992) {
      bsOffcanvas.hide();
    }
  }

  const navLink = resolveMainTabTrigger(tabId, element);

  if (navLink && typeof bootstrap !== "undefined" && bootstrap.Tab) {
    try {
      const tab = bootstrap.Tab.getOrCreateInstance(navLink);
      tab.show();
      return;
    } catch (error) {
      console.error("[TAB TRACK] Error using Bootstrap Tab API:", error);
    }
  }

  // Fallback if no matching nav-link (misconfigured menu): toggle panes manually
  const pane = document.getElementById(tabId);
  const tabContent = document.getElementById("mainTabContent");
  if (
    pane &&
    tabContent &&
    pane.classList.contains("tab-pane") &&
    typeof bootstrap !== "undefined" &&
    bootstrap.Tab &&
    bootstrap.Tab.getOrCreateInstance
  ) {
    tabContent.querySelectorAll(".tab-pane").forEach((p) => {
      p.classList.remove("show", "active");
    });
    pane.classList.add("show", "active");
    document
      .querySelectorAll(
        ".sidebar .nav-link, .sidebar-offcanvas .nav-link"
      )
      .forEach((lnk) => lnk.classList.remove("active"));
    if (navLink) navLink.classList.add("active");
    if (typeof loadTabContent === "function") {
      loadTabContent(tabId);
    }
  }
};

const __DEFAULT_SIDEBAR_AVATAR_SVG =
  "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+";

/**
 * Update sidebar / header avatars after upload (paths from API: assets/uploads/...).
 */
window.updateSidebarProfilePicture = function (imagePath) {
  if (!imagePath) return;
  const bust = (imagePath.indexOf("?") >= 0 ? "&" : "?") + "t=" + Date.now();
  let src;
  if (
    imagePath.startsWith("http") ||
    imagePath.startsWith("data:") ||
    imagePath.startsWith("/")
  ) {
    src = imagePath + bust;
  } else {
    src = "../../public/" + imagePath.replace(/^\//, "") + bust;
  }
  const ids = [
    "adminProfileImg",
    "sidebarProfileImg",
    "mobileAdminProfileImg",
    "mobileSidebarProfileImg",
  ];
  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.src = src;
  });
};

window.updateSidebarProfilePictureToDefault = function () {
  const ids = [
    "adminProfileImg",
    "sidebarProfileImg",
    "mobileAdminProfileImg",
    "mobileSidebarProfileImg",
  ];
  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.src = __DEFAULT_SIDEBAR_AVATAR_SVG;
  });
};

window.updateAdminSidebarProfilePicture = window.updateSidebarProfilePicture;
window.updateAdminSidebarProfilePictureToDefault =
  window.updateSidebarProfilePictureToDefault;

/**
 * Loads content for a specific tab
 * NOTE: This is called from initTabOnce wrapper in shown.bs.tab handler
 * No need for its own guard - the caller already ensures single execution
 * @param {string} tabId - The tab ID to load
 */
function loadTabContent(tabId) {
  // Reload dashboard data when overview tab is selected
  if (tabId === "overview") {
    loadDashboardData();
  }
  // Profile tab doesn't need special loading - content is already in DOM
  else if (tabId === "profile") {
    console.log("Profile tab selected - content already loaded");
    // Profile content is static, no need to load data
  }
  // Load accounts when Accounts tab is selected
  else if (tabId === "roles") {
    console.log("Loading accounts for roles tab...");
    loadAccounts();
  }
  // All other tabs (curriculum, schedule, curriculum_management, course_management)
  // are handled directly in the shown.bs.tab handler
  // This function only handles simple data loading for overview, profile, and roles
}

// Schedule Filter Modal Functions - REMOVED (not in use)
// Filters are now handled directly on the schedule management page

window.clearScheduleFilters = function () {
  // Clear sessionStorage
  sessionStorage.removeItem("scheduleFilterProgram");
  sessionStorage.removeItem("scheduleFilterYearLevel");

  // Clear the filter dropdowns in the schedule management page
  if (document.getElementById("programFilter")) {
    document.getElementById("programFilter").value = "";
  }
  if (document.getElementById("yearLevelFilter")) {
    document.getElementById("yearLevelFilter").value = "";
  }

  // Clear the schedule calendar
  if (typeof clearCalendar === "function") {
    clearCalendar();
  }
};

// Global function to close mobile sidebar offcanvas
window.closeOffcanvas = function () {
  const offcanvasEl = document.getElementById("sidebarOffcanvas");
  if (offcanvasEl && typeof bootstrap !== "undefined" && bootstrap.Offcanvas) {
    const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
    if (bsOffcanvas) {
      bsOffcanvas.hide();
    }
  }
};

// Profile Management Functions
function loadProfileData() {
  // Load current profile photo if exists
  fetch("../../admin/reports/get_profile_data.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.profile_photo) {
        document.getElementById("profilePreview").src = data.profile_photo;
        document.getElementById("adminProfileImg").src = data.profile_photo;
        // Update mobile profile image too
        const mobileImg = document.getElementById("adminProfileImgMobile");
        if (mobileImg) mobileImg.src = data.profile_photo;
      }
    })
    .catch((error) => console.log("Profile data not loaded:", error));
}

function saveProfile() {
  const form = document.getElementById("profileForm");
  const formData = new FormData(form);

  // Validate password confirmation
  const newPassword = document.getElementById("new_password").value;
  const confirmPassword = document.getElementById("confirm_password").value;

  if (newPassword && newPassword !== confirmPassword) {
    alert("New passwords do not match!");
    return;
  }

  // Show loading state
  const saveBtn = document.querySelector('[onclick="saveProfile()"]');
  const originalText = saveBtn.textContent;
  saveBtn.textContent = "Saving...";
  saveBtn.disabled = true;

  fetch("../../views/admin/update_profile.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert("Profile updated successfully!");
        // Update the main profile image
        if (data.profile_photo) {
          document.getElementById("adminProfileImg").src = data.profile_photo;
          // Update mobile profile image too
          const mobileImg = document.getElementById("adminProfileImgMobile");
          if (mobileImg) mobileImg.src = data.profile_photo;
        }
        // Update username display
        const newUsername = document.getElementById("acc_user").value;
        document.querySelector(".fw-semibold.mt-2").textContent = newUsername;
        // Close modal using ModalManager if available, otherwise Bootstrap API
        if (typeof ModalManager !== "undefined") {
          ModalManager.close("profileModal");
        } else {
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("profileModal")
          );
          if (modal) modal.hide();
        }
      } else {
        alert("Error: " + (data.message || "Failed to update profile"));
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("An error occurred while updating profile");
    })
    .finally(() => {
      saveBtn.textContent = originalText;
      saveBtn.disabled = false;
    });
}

function updateProfile() {
  console.log("updateProfile function called");
  const form = document.getElementById("profileForm");
  if (!form) {
    console.error("Profile form not found!");
    alert("Profile form not found. Please refresh the page.");
    return;
  }

  const formData = new FormData(form);
  formData.append("acc_id", window.currentUserId || 0);

  console.log("Form data prepared:", {
    fname: formData.get("fname"),
    lname: formData.get("lname"),
    acc_user: formData.get("acc_user"),
    acc_email: formData.get("acc_email"),
    acc_id: formData.get("acc_id"),
  });

  // Helper function to show validation error with SweetAlert2
  function showValidationError(message) {
    Swal.fire({
      icon: "warning",
      title: "Validation Error",
      text: message,
      confirmButtonColor: "#800000",
      confirmButtonText: "OK",
    });
  }

  // Validate required fields
  const requiredFields = ["fname", "lname", "acc_user", "acc_email"];
  for (let field of requiredFields) {
    const value = formData.get(field);
    if (!value || value.trim() === "") {
      showValidationError(
        `Please fill in the required field: ${field
          .replace("_", " ")
          .toUpperCase()}`
      );
      document.getElementById(field).focus();
      return;
    }
  }

  // Validate email format
  const email = formData.get("acc_email");
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showValidationError("Please enter a valid email address");
    document.getElementById("acc_email").focus();
    return;
  }

  // Validate password fields if any are fillsed
  const currentPassword = formData.get("current_password");
  const newPassword = formData.get("new_password");
  const confirmPassword = formData.get("confirm_password");

  if (newPassword && !currentPassword) {
    showValidationError("Current password is required to change password");
    document.getElementById("current_password").focus();
    return;
  }

  if (newPassword && newPassword !== confirmPassword) {
    showValidationError("New passwords do not match");
    document.getElementById("confirm_password").focus();
    return;
  }

  if (newPassword && newPassword.length < 6) {
    showValidationError("New password must be at least 6 characters long");
    document.getElementById("new_password").focus();
    return;
  }

  // Clear any existing messages
  const messageEl = document.getElementById("profileMessage");
  if (messageEl) {
    messageEl.style.display = "none";
  }

  const updateBtn = document.querySelector('button[onclick="updateProfile()"]');
  const originalText = updateBtn.innerHTML;
  updateBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
  updateBtn.disabled = true;

  console.log("Sending request to update_profile.php");
  fetch("../../views/admin/update_profile.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      console.log("Response received:", response.status, response.statusText);
      return response.json();
    })
    .then((data) => {
      console.log("Response data:", data);

      if (data.success) {
        // Show beautiful success message with SweetAlert2
        Swal.fire({
          icon: "success",
          title: "Profile Updated!",
          text: "Your profile has been updated successfully.",
          confirmButtonColor: "#28a745",
          confirmButtonText: "Great!",
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: true,
          allowOutsideClick: false,
          customClass: {
            popup: "swal2-popup-custom",
            title: "swal2-title-custom",
            content: "swal2-content-custom",
          },
        }).then(() => {
          // Update the display name in the sidebar if it exists
          const displayName = document.querySelector(".fw-semibold.mt-2");
          if (displayName) {
            const fname = formData.get("fname");
            const lname = formData.get("lname");
            displayName.textContent = `${fname} ${lname}`;
          }

          // Clear password fields on success
          document.getElementById("current_password").value = "";
          document.getElementById("new_password").value = "";
          document.getElementById("confirm_password").value = "";

          // Hide any existing inline messages
          const messageEl = document.getElementById("profileMessage");
          if (messageEl) {
            messageEl.style.display = "none";
          }
        });
      } else {
        // Show error message with SweetAlert2
        Swal.fire({
          icon: "error",
          title: "Update Failed",
          text: data.message || "Failed to update profile. Please try again.",
          confirmButtonColor: "#dc3545",
          confirmButtonText: "Try Again",
          customClass: {
            popup: "swal2-popup-custom",
            title: "swal2-title-custom",
            content: "swal2-content-custom",
          },
        });
      }
    })
    .catch((error) => {
      console.error("Error:", error);

      // Show network error with SweetAlert2
      Swal.fire({
        icon: "error",
        title: "Connection Error",
        text: "Unable to connect to the server. Please check your internet connection and try again.",
        confirmButtonColor: "#dc3545",
        confirmButtonText: "Retry",
        customClass: {
          popup: "swal2-popup-custom",
          title: "swal2-title-custom",
          content: "swal2-content-custom",
        },
      });
    })
    .finally(() => {
      updateBtn.innerHTML = originalText;
      updateBtn.disabled = false;
    });
}

// Dashboard Data Loading
function loadDashboardData() {
  // Check if old overview stat elements exist (new overview doesn't have them)
  const totalUsersEl = document.getElementById("totalUsers");
  const totalInstructorsEl = document.getElementById("totalInstructors");
  const totalRoomsEl = document.getElementById("totalRooms");
  const totalSchedulesEl = document.getElementById("totalSchedules");

  // If none of the old stat elements exist, this is the new overview - skip loading
  if (
    !totalUsersEl &&
    !totalInstructorsEl &&
    !totalRoomsEl &&
    !totalSchedulesEl
  ) {
    console.log("New overview detected - skipping dashboard data load");
    return;
  }

  // Add loading animation to stat numbers
  const statNumbers = document.querySelectorAll(".stat-number, .h4, .h5");
  statNumbers.forEach((el) => {
    if (el.textContent === "-") {
      el.innerHTML = '<span class="loading"></span>';
    }
  });

  fetch("../../admin/reports/get_dashboard_data.php")
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        const stats = data.data;

        // Update user statistics
        const activeUsersEl = document.getElementById("activeUsers");
        const pendingUsersEl = document.getElementById("pendingUsers");
        const inactiveUsersEl = document.getElementById("inactiveUsers");

        if (totalUsersEl)
          totalUsersEl.textContent = stats.users?.total_users || 0;
        if (activeUsersEl)
          activeUsersEl.textContent = stats.users?.active_users || 0;
        if (pendingUsersEl)
          pendingUsersEl.textContent = stats.users?.pending_users || 0;
        if (inactiveUsersEl)
          inactiveUsersEl.textContent = stats.users?.inactive_users || 0;

        // Update user management component statistics
        console.log("Updating user management statistics:", stats.users);
        const totalUsersCountEl = document.getElementById("totalUsersCount");
        const activeUsersCountEl = document.getElementById("activeUsersCount");
        const pendingUsersCountEl =
          document.getElementById("pendingUsersCount");
        const moderatorsEl = document.getElementById("moderatorsCount");
        const instructorsEl = document.getElementById("instructorsCount");
        const regularUsersEl = document.getElementById("regularUsersCount");

        if (totalUsersCountEl)
          totalUsersCountEl.textContent = stats.users.total_users || 0;
        if (activeUsersCountEl)
          activeUsersCountEl.textContent = stats.users.active_users || 0;
        if (pendingUsersCountEl)
          pendingUsersCountEl.textContent = stats.users.pending_users || 0;
        if (moderatorsEl)
          moderatorsEl.textContent = stats.users.moderators_count || 0;
        if (instructorsEl)
          instructorsEl.textContent = stats.instructors.total_instructors || 0;
        if (regularUsersEl)
          regularUsersEl.textContent =
            stats.instructors.regular_instructors || 0;

        console.log("User management statistics updated");

        // Update instructor statistics
        const regularInstructorsEl =
          document.getElementById("regularInstructors");
        const parttimeInstructorsEl = document.getElementById(
          "parttimeInstructors"
        );
        const pendingInstructorsEl =
          document.getElementById("pendingInstructors");

        if (totalInstructorsEl)
          totalInstructorsEl.textContent =
            stats.instructors?.total_instructors || 0;
        if (regularInstructorsEl)
          regularInstructorsEl.textContent =
            stats.instructors?.regular_instructors || 0;
        if (parttimeInstructorsEl)
          parttimeInstructorsEl.textContent =
            stats.instructors?.parttime_instructors || 0;
        if (pendingInstructorsEl)
          pendingInstructorsEl.textContent =
            stats.instructors?.pending_instructors || 0;
        // Render instructor by-program breakdown if available
        const byProgram =
          stats.instructors && stats.instructors.by_program
            ? stats.instructors.by_program
            : [];
        const byProgramEl = document.getElementById("instructorByProgram");
        if (byProgramEl) {
          byProgramEl.innerHTML = "";
          if (Array.isArray(byProgram) && byProgram.length > 0) {
            const totalInstr =
              Number(stats.instructors.total_instructors || 0) || 0;
            byProgram.forEach((item) => {
              const name = item.program || item.course || "Program";
              const count = Number(item.count || 0);
              const percent =
                totalInstr > 0 ? Math.round((count / totalInstr) * 100) : 0;
              const wrapper = document.createElement("div");
              wrapper.className = "mb-2";
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
            byProgramEl.innerHTML =
              '<div class="text-muted small">No breakdown available</div>';
          }
        }

        // Update student statistics
        const totalStudentsEl = document.getElementById("totalStudents");
        if (totalStudentsEl)
          totalStudentsEl.textContent = stats.students.total_students || 0;

        // Update room statistics
        const totalRoomsEl = document.getElementById("totalRooms");
        const usedRoomsEl = document.getElementById("usedRooms");
        const unusedRoomsEl = document.getElementById("unusedRooms");
        const labRoomsEl = document.getElementById("labRooms");
        const lectureRoomsEl = document.getElementById("lectureRooms");
        const specialRoomsEl = document.getElementById("specialRooms");

        if (totalRoomsEl)
          totalRoomsEl.textContent = stats.rooms?.total_rooms || 0;
        if (usedRoomsEl) usedRoomsEl.textContent = stats.rooms?.used_rooms || 0;
        if (unusedRoomsEl)
          unusedRoomsEl.textContent = stats.rooms?.unused_rooms || 0;
        if (labRoomsEl) labRoomsEl.textContent = stats.rooms?.lab_rooms || 0;
        if (lectureRoomsEl)
          lectureRoomsEl.textContent = stats.rooms?.lecture_rooms || 0;
        if (specialRoomsEl)
          specialRoomsEl.textContent = stats.rooms?.special_rooms || 0;

        // Update schedule statistics
        const totalSchedulesEl = document.getElementById("totalSchedules");
        const activeSchedulesEl = document.getElementById("activeSchedules");
        const totalConflictsEl = document.getElementById("totalConflicts");
        const pendingRequestsEl = document.getElementById("pendingRequests");

        if (totalSchedulesEl)
          totalSchedulesEl.textContent = stats.schedules?.total_schedules || 0;
        if (activeSchedulesEl)
          activeSchedulesEl.textContent =
            stats.schedules?.active_schedules || 0;
        if (totalConflictsEl)
          totalConflictsEl.textContent = stats.conflicts?.total_conflicts || 0;
        if (pendingRequestsEl)
          pendingRequestsEl.textContent =
            stats.room_requests?.pending_requests || 0;

        // Update room request statistics (if elements exist)
        const acceptedRequestsEl = document.getElementById("acceptedRequests");
        const declinedRequestsEl = document.getElementById("declinedRequests");

        if (acceptedRequestsEl)
          acceptedRequestsEl.textContent =
            stats.room_requests?.accepted_requests || 0;
        if (declinedRequestsEl)
          declinedRequestsEl.textContent =
            stats.room_requests?.declined_requests || 0;

        // Update subject statistics
        const totalSubjectsEl = document.getElementById("totalSubjects");
        const majorSubjectsEl = document.getElementById("majorSubjects");

        if (totalSubjectsEl)
          totalSubjectsEl.textContent = stats.subjects.total_subjects || 0;
        if (majorSubjectsEl)
          majorSubjectsEl.textContent = stats.subjects.major_subjects || 0;

        const genedSubjectsEl = document.getElementById("genedSubjects");
        if (genedSubjectsEl)
          genedSubjectsEl.textContent = stats.subjects.gened_subjects || 0;

        // Update curriculum statistics
        if (stats.curricula) {
          const totalCurriculaEl = document.getElementById("totalCurricula");
          if (totalCurriculaEl)
            totalCurriculaEl.textContent = stats.curricula.total_curricula || 0;

          const firstYearCurriculaEl =
            document.getElementById("firstYearCurricula");
          const secondYearCurriculaEl = document.getElementById(
            "secondYearCurricula"
          );
          const thirdYearCurriculaEl =
            document.getElementById("thirdYearCurricula");
          const fourthYearCurriculaEl = document.getElementById(
            "fourthYearCurricula"
          );

          if (firstYearCurriculaEl)
            firstYearCurriculaEl.textContent =
              stats.curricula.first_year_curricula || 0;
          if (secondYearCurriculaEl)
            secondYearCurriculaEl.textContent =
              stats.curricula.second_year_curricula || 0;
          if (thirdYearCurriculaEl)
            thirdYearCurriculaEl.textContent =
              stats.curricula.third_year_curricula || 0;
          if (fourthYearCurriculaEl)
            fourthYearCurriculaEl.textContent =
              stats.curricula.fourth_year_curricula || 0;
        }

        // Update classes and sections statistics
        if (stats.classes) {
          const totalClassesEl = document.getElementById("totalClasses");
          if (totalClassesEl)
            totalClassesEl.textContent = stats.classes.total_classes || 0;

          const totalSectionsEl = document.getElementById("totalSections");
          const firstTermClassesEl =
            document.getElementById("firstTermClasses");
          const secondTermClassesEl =
            document.getElementById("secondTermClasses");

          if (totalSectionsEl)
            totalSectionsEl.textContent = stats.classes.total_sections || 0;
          if (firstTermClassesEl)
            firstTermClassesEl.textContent =
              stats.classes.first_term_classes || 0;
          if (secondTermClassesEl)
            secondTermClassesEl.textContent =
              stats.classes.second_term_classes || 0;
        }

        // Update department overview
        if (stats.department) {
          const departmentNameEl = document.getElementById("departmentName");
          if (departmentNameEl)
            departmentNameEl.textContent =
              stats.department.dept_name || "All Departments";
        }

        // Update department-specific instructor and student counts
        const totalInstructorsDeptEl = document.getElementById(
          "totalInstructorsDept"
        );
        const totalStudentsDeptEl =
          document.getElementById("totalStudentsDept");

        if (totalInstructorsDeptEl)
          totalInstructorsDeptEl.textContent =
            stats.instructors?.total_instructors || 0;
        if (totalStudentsDeptEl)
          totalStudentsDeptEl.textContent = stats.students?.total_students || 0;

        // Update department resources
        if (stats.buildings) {
          const totalBuildingsDeptEl =
            document.getElementById("totalBuildingsDept");
          if (totalBuildingsDeptEl)
            totalBuildingsDeptEl.textContent =
              stats.buildings?.total_buildings || 0;
        }

        const totalRoomsDeptEl = document.getElementById("totalRoomsDept");
        const totalSchedulesDeptEl =
          document.getElementById("totalSchedulesDept");

        if (totalRoomsDeptEl)
          totalRoomsDeptEl.textContent = stats.rooms?.total_rooms || 0;
        if (totalSchedulesDeptEl)
          totalSchedulesDeptEl.textContent =
            stats.schedules?.total_schedules || 0;

        // Update department summary if available
        if (stats.department_summary) {
          console.log("Department Summary:", stats.department_summary);
          // You can add more department summary handling here if needed
        }
      }
    })
    .catch((error) => {
      console.error("Dashboard data not loaded:", error);

      // Check if it's an authentication error
      if (
        error.message.includes("403") ||
        error.message.includes("Unauthorized")
      ) {
        // Try to load basic stats without authentication
        console.log("Authentication failed, trying basic stats...");
        loadBasicStats();
      } else {
        // Show generic error message
        const errorMessage = document.createElement("div");
        errorMessage.className =
          "alert alert-danger alert-dismissible fade show";
        errorMessage.innerHTML = `
          <i class="bi bi-exclamation-circle me-2"></i>
          <strong>Error:</strong> Failed to load dashboard data. Please refresh the page.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const dashboardContent =
          document.querySelector(".dashboard-content") ||
          document.querySelector(".container-fluid");
        if (dashboardContent) {
          dashboardContent.insertBefore(
            errorMessage,
            dashboardContent.firstChild
          );
        }
      }
    });

  // Load user management data
  loadAccounts();
}

// Function to load basic statistics without authentication
function loadBasicStats() {
  fetch("../../admin/reports/get_basic_stats.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const stats = data.data;

        // Update user statistics with basic data
        document.getElementById("totalUsers").textContent =
          stats.users.total_users || 0;
        document.getElementById("activeUsers").textContent =
          stats.users.active_users || 0;
        document.getElementById("pendingUsers").textContent =
          stats.users.pending_users || 0;
        document.getElementById("inactiveUsers").textContent =
          stats.users.inactive_users || 0;

        // Update instructor statistics
        if (stats.instructors) {
          document.getElementById("totalInstructors").textContent =
            stats.instructors.total_instructors || 0;
        }

        // Show info message that this is basic data
        const infoMessage = document.createElement("div");
        infoMessage.className = "alert alert-info alert-dismissible fade show";
        infoMessage.innerHTML = `
          <i class="bi bi-info-circle me-2"></i>
          <strong>Basic Statistics:</strong> Showing basic data. <a href="login_admin.php" class="alert-link">Log in</a> for complete dashboard features.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const dashboardContent =
          document.querySelector(".dashboard-content") ||
          document.querySelector(".container-fluid");
        if (dashboardContent) {
          dashboardContent.insertBefore(
            infoMessage,
            dashboardContent.firstChild
          );
        }

        console.log("Basic statistics loaded successfully");
      } else {
        throw new Error(data.message || "Failed to load basic statistics");
      }
    })
    .catch((error) => {
      console.error("Failed to load basic statistics:", error);

      // Show authentication required message
      const errorMessage = document.createElement("div");
      errorMessage.className =
        "alert alert-warning alert-dismissible fade show";
      errorMessage.innerHTML = `
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Authentication Required:</strong> Please <a href="login_admin.php" class="alert-link">log in</a> to view dashboard data.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;

      const dashboardContent =
        document.querySelector(".dashboard-content") ||
        document.querySelector(".container-fluid");
      if (dashboardContent) {
        dashboardContent.insertBefore(
          errorMessage,
          dashboardContent.firstChild
        );
      }

      // Set default values
      document.getElementById("totalUsers").textContent = "Login Required";
      document.getElementById("activeUsers").textContent = "-";
      document.getElementById("pendingUsers").textContent = "-";
      document.getElementById("inactiveUsers").textContent = "-";
    });
}

// Step Navigation Variables
let currentStep = 1;
// Prevent redeclaration error - use window scope to avoid conflicts
if (typeof window.totalSteps === 'undefined') {
  window.totalSteps = 5;
}
// Use window.totalSteps directly - do not declare a const to avoid redeclaration errors

function changeStep(direction) {
  const newStep = currentStep + direction;

  console.log(
    "changeStep - direction:",
    direction,
    "currentStep:",
    currentStep,
    "newStep:",
    newStep,
    "totalSteps:",
    window.totalSteps
  );

  if (newStep < 1 || newStep > window.totalSteps) {
    console.log("Invalid step range, returning");
    return;
  }

  // Validate current step before proceeding
  if (direction > 0 && !validateCurrentStep()) {
    console.log("Validation failed, returning");
    return;
  }

  // Hide current step
  document.getElementById(`step${currentStep}`).classList.add("d-none");
  document
    .querySelector(`[data-step="${currentStep}"]`)
    .classList.remove("active");

  // Show new step
  currentStep = newStep;
  document.getElementById(`step${currentStep}`).classList.remove("d-none");
  document
    .querySelector(`[data-step="${currentStep}"]`)
    .classList.add("active");

  console.log("Step changed to:", currentStep);

  // Update step indicators
  updateStepIndicators();

  // Update buttons
  updateNavigationButtons();

  // Update summary if on last step
  if (currentStep === window.totalSteps) {
    console.log("On last step, updating summary");
    updateAccountSummary();
  }
}

function validateCurrentStep() {
  const step = document.getElementById(`step${currentStep}`);
  const requiredFields = step.querySelectorAll("[required]");

  for (let field of requiredFields) {
    // Skip rank validation if employment status is Part-Time
    if (field.id === "add_rank" && field.required) {
      const instStatusEl = document.getElementById("add_inst_status");
      const instStatus = instStatusEl ? instStatusEl.value : "";
      if (instStatus === "Part-Time") {
        // Rank is optional for Part-Time, skip validation
        field.classList.remove("is-invalid");
        continue;
      }
    }

    if (!field.value.trim()) {
      field.focus();
      field.classList.add("is-invalid");
      alert(
        `Please fill in the required field: ${field.previousElementSibling.textContent
          .replace("*", "")
          .trim()}`
      );
      return false;
    } else {
      field.classList.remove("is-invalid");
    }
  }

  // Special validations
  if (currentStep === 2) {
    // Password validation removed - using default password "evsu-occ"
  }

  return true;
}

function updateStepIndicators() {
  const steps = document.querySelectorAll(".step");
  steps.forEach((step, index) => {
    const stepNumber = index + 1;
    step.classList.remove("active", "completed");

    if (stepNumber < currentStep) {
      step.classList.add("completed");
    } else if (stepNumber === currentStep) {
      step.classList.add("active");
    }
  });
}

function updateNavigationButtons() {
  const prevBtn = document.getElementById("prevBtn");
  const nextBtn = document.getElementById("nextBtn");
  const submitBtn = document.getElementById("submitBtn");

  console.log(
    "updateNavigationButtons - currentStep:",
    currentStep,
    "totalSteps:",
    window.totalSteps
  );

  // Previous button
  if (currentStep === 1) {
    prevBtn.style.display = "none";
  } else {
    prevBtn.style.display = "inline-block";
  }

  // Next/Submit button
  if (currentStep === window.totalSteps) {
    console.log("Showing submit button, hiding next button");
    nextBtn.classList.add("d-none");
    submitBtn.classList.remove("d-none");
  } else {
    console.log("Showing next button, hiding submit button");
    nextBtn.classList.remove("d-none");
    submitBtn.classList.add("d-none");
  }
}

function updateAccountSummary() {
  const form = document.getElementById("addAccountForm");
  const formData = new FormData(form);

  document.getElementById("summary_name").textContent = `${formData.get(
    "fname"
  )} ${formData.get("minitial")} ${formData.get("lname")}`
    .replace(/\s+/g, " ")
    .trim();
  document.getElementById("summary_username").textContent =
    formData.get("acc_user");
  document.getElementById("summary_email").textContent =
    formData.get("acc_email");

  // Handle role display (checkboxes for add form)
  const roleCheckboxes = form.querySelectorAll(
    'input[name="role_ids[]"]:checked'
  );
  let roleTexts = [];
  if (roleCheckboxes.length > 0) {
    roleCheckboxes.forEach((cb) => {
      if (cb.value === "3") roleTexts.push("Moderator");
      else if (cb.value === "4") roleTexts.push("Instructor");
    });
  }
  document.getElementById("summary_role").textContent =
    roleTexts.length > 0 ? roleTexts.join(", ") : "None selected";

  // Instructor details (use add_ prefix for add form)
  const instStatusSelect = document.getElementById("add_inst_status");
  const instStatusText =
    instStatusSelect && instStatusSelect.options[instStatusSelect.selectedIndex]
      ? instStatusSelect.options[instStatusSelect.selectedIndex].text
      : "-";
  const summaryInstStatus = document.getElementById("summary_inst_status");
  if (summaryInstStatus) summaryInstStatus.textContent = instStatusText;

  const rankSelect = document.getElementById("add_rank");
  const rankText =
    rankSelect && rankSelect.options[rankSelect.selectedIndex]
      ? rankSelect.options[rankSelect.selectedIndex].text
      : "-";
  const summaryRank = document.getElementById("summary_rank");
  if (summaryRank) summaryRank.textContent = rankText;

  const designationSelect = document.getElementById("add_designation");
  const designationText =
    designationSelect &&
    designationSelect.options[designationSelect.selectedIndex]
      ? designationSelect.options[designationSelect.selectedIndex].text
      : "-";
  const summaryDesignation = document.getElementById("summary_designation");
  if (summaryDesignation) summaryDesignation.textContent = designationText;

  document.getElementById("summary_inst_email").textContent =
    formData.get("inst_email") || "Not provided";
  document.getElementById("summary_inst_phone").textContent =
    formData.get("inst_phone") || "Not provided";

  // Status is automatically set to "Active" - no need to get from form
}

function addAccount() {
  const form = document.getElementById("addAccountForm");
  const formData = new FormData(form);

  // Validate role checkboxes (at least one must be selected)
  const roleCheckboxes = form.querySelectorAll('input[name="role_ids[]"]');
  const checkedRoles = Array.from(roleCheckboxes).filter((cb) => cb.checked);
  if (checkedRoles.length === 0) {
    const roleError = document.getElementById("role_ids_error");
    if (roleError) {
      roleError.style.display = "block";
    }
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "Validation Error",
        text: "Please select at least one role.",
        confirmButtonColor: "#800000",
        confirmButtonText: "OK",
      });
    } else {
      alert("Please select at least one role.");
    }
    return;
  } else {
    const roleError = document.getElementById("role_ids_error");
    if (roleError) {
      roleError.style.display = "none";
    }
  }

  // CRITICAL: Remove any existing role_ids from FormData and manually add checked ones
  // This ensures PHP receives role_ids as an array
  // FormData doesn't always handle checkbox arrays correctly, so we need to manually append
  // Try both methods: with brackets and without (PHP should handle both)
  formData.delete("role_ids[]");
  formData.delete("role_ids"); // Also delete without brackets just in case

  // Append each checked role - using 'role_ids[]' format which PHP will parse as $_POST['role_ids'] array
  checkedRoles.forEach((checkbox) => {
    formData.append("role_ids[]", checkbox.value);
    console.log("Added role_id to FormData:", checkbox.value, "as role_ids[]");
  });

  // Also try appending without brackets as fallback (PHP will create array if multiple values)
  // But only if the bracket method doesn't work, so comment this out for now
  // checkedRoles.forEach(checkbox => {
  //   formData.append('role_ids', checkbox.value);
  // });

  // Debug: Log form data after adding role_ids
  console.log("Form data being sent (after adding role_ids):");
  for (let [key, value] of formData.entries()) {
    console.log(key + ":", value);
  }

  // Validate required fields
  const requiredFields = form.querySelectorAll("[required]");
  for (let field of requiredFields) {
    // Skip rank validation if employment status is Part-Time
    if (field.id === "add_rank" && field.required) {
      const instStatusEl = document.getElementById("add_inst_status");
      const instStatus = instStatusEl ? instStatusEl.value : "";
      if (instStatus === "Part-Time") {
        // Rank is optional for Part-Time, skip validation
        field.classList.remove("is-invalid");
        continue;
      }
    }

    if (!field.value.trim()) {
      field.focus();
      field.classList.add("is-invalid");

      // Show validation error with SweetAlert
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "warning",
          title: "Validation Error",
          text: `Please fill in the required field: ${field.previousElementSibling.textContent
            .replace("*", "")
            .trim()}`,
          confirmButtonColor: "#800000",
          confirmButtonText: "OK",
        });
      } else {
        alert(
          `Please fill in the required field: ${field.previousElementSibling.textContent
            .replace("*", "")
            .trim()}`
        );
      }
      return;
    } else {
      field.classList.remove("is-invalid");
    }
  }

  // Check for duplicate name + role before submission
  const fname = form.querySelector("#add_fname")?.value.trim() || "";
  const lname = form.querySelector("#add_lname")?.value.trim() || "";
  const minitial = form.querySelector("#add_minitial")?.value.trim() || "";
  const acc_email = form.querySelector("#add_acc_email")?.value.trim() || "";

  if (fname && lname) {
    // Prepare data for duplicate check
    const checkData = new FormData();
    checkData.append("fname", fname);
    checkData.append("lname", lname);
    if (minitial) {
      checkData.append("minitial", minitial);
    }
    if (acc_email) {
      checkData.append("acc_email", acc_email);
    }
    // Add all checked role_ids
    checkedRoles.forEach((checkbox) => {
      checkData.append("role_ids[]", checkbox.value);
    });

    // If no roles checked, add Instructor (4) as default
    if (checkedRoles.length === 0) {
      checkData.append("role_ids[]", "4");
    }
    
    // Add dept_id to allow checking if account already has this department
    // This enables "add existing instructor to different department" functionality
    const deptId = window.currentDeptId || 0;
    if (deptId > 0) {
      checkData.append("dept_id", deptId);
    }

    // Show loading state
    const submitBtn = document.getElementById("submitBtn");
    const originalText = submitBtn ? submitBtn.innerHTML : "";
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Checking...';
    }

    // Check for duplicates
    return fetch("../../admin/management/check_duplicate_account.php", {
      method: "POST",
      body: checkData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (submitBtn) {
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }

        // Check for duplicates: name+role in same dept, or email already in this dept
        // If email exists but in different dept, that's OK (will add to new dept)
        const isDuplicate = !data.success || 
                           data.name_exists || 
                           (data.email_exists && data.email_already_in_dept);
        
        if (isDuplicate) {
          // Duplicate found, show styled error modal
          let errorTitle = "Duplicate Account Detected";
          let errorMessage = "";
          let errorDetails = "";
          let hasNameDuplicate = false;
          let hasEmailDuplicate = false;

          // Check for name + role duplication
          if (data.name_exists) {
            hasNameDuplicate = true;
            const existingName =
              data.existing_name ||
              fname + " " + (minitial ? minitial + ". " : "") + lname;
            let roleBadges = "";

            if (data.duplicate_roles && data.duplicate_roles.length > 0) {
              const roleNames = data.duplicate_roles.map((r) => r.role_name);
              roleBadges = roleNames
                .map((name) => `<span class="badge bg-danger">${name}</span>`)
                .join(" ");
            } else {
              // Fallback if duplicate_roles is not provided
              roleBadges = '<span class="badge bg-danger">Same Role</span>';
            }

            errorMessage = hasEmailDuplicate
              ? `Multiple issues detected with this account:`
              : `A user with this name already exists with the following role(s):`;

            errorDetails += `
              <div class="duplicate-alert-content">
                <div class="duplicate-user-info">
                  <div class="duplicate-section-header">
                    <i class="bi bi-person-fill me-2"></i>
                    <strong>Name & Role Conflict</strong>
                  </div>
                  <div class="user-name-display mt-2">
                    <i class="bi bi-person-circle me-2"></i>
                    <strong>${existingName}</strong>
                  </div>
                  <div class="user-roles-display mt-2">
                    ${roleBadges}
                  </div>
                </div>
                <div class="alert-warning-box mt-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <span>Cannot create duplicate account with the same name and role combination.</span>
                </div>
              </div>
            `;
          }

          // Check for email duplication (only if already in this department)
          if (data.email_exists && data.email_already_in_dept) {
            hasEmailDuplicate = true;
            const existingEmail = data.existing_email || acc_email;

            if (!hasNameDuplicate) {
              errorMessage = `The email address is already registered in this department.`;
            } else {
              errorMessage = `Multiple issues detected with this account:`;
            }

            errorDetails += `
              <div class="duplicate-alert-content ${
                hasNameDuplicate ? "mt-3" : ""
              }">
                <div class="duplicate-user-info">
                  <div class="duplicate-section-header">
                    <i class="bi bi-envelope-fill me-2"></i>
                    <strong>Email Conflict</strong>
                  </div>
                  <div class="user-email-display mt-2">
                    <i class="bi bi-envelope me-2"></i>
                    <strong>${existingEmail}</strong>
                  </div>
                </div>
                <div class="alert-warning-box mt-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <span>This email address is already registered in this department. Please use a different email address.</span>
                </div>
              </div>
            `;
          }

          // Fallback message if no specific details
          if (!errorDetails) {
            errorMessage = data.message || "Duplicate account detected";
            errorDetails = `
              <div class="duplicate-alert-content">
                <div class="alert-warning-box">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <span>${errorMessage}</span>
                </div>
              </div>
            `;
          }

          // Always use styled modal
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "error",
              title: errorTitle,
              html: `
                <div class="duplicate-alert-modal">
                  <p class="duplicate-alert-message">${errorMessage}</p>
                  ${errorDetails}
                </div>
              `,
              confirmButtonColor: "#800000",
              confirmButtonText:
                '<i class="bi bi-check-lg me-1"></i>Understood',
              customClass: {
                popup: "duplicate-alert-popup",
                title: "duplicate-alert-title",
                confirmButton: "duplicate-alert-button",
                htmlContainer: "duplicate-alert-container",
              },
              buttonsStyling: true,
              allowOutsideClick: true,
              allowEscapeKey: true,
              showClass: {
                popup: "animate__animated animate__fadeInDown animate__faster",
              },
              hideClass: {
                popup: "animate__animated animate__fadeOutUp animate__faster",
              },
            });
          } else {
            // Fallback to alert if SweetAlert2 is not available
            alert(
              errorMessage +
                (errorDetails
                  ? "\n\n" + errorDetails.replace(/<[^>]*>/g, "")
                  : "")
            );
          }
          return;
        }

        // No duplicate found, proceed with account creation
        // If email exists but in different department, show info message
        if (data.email_exists_but_new_dept) {
          console.log("Account with this email exists in another department. Will add to current department.");
          // Optional: Show a brief info toast (non-blocking)
          if (typeof showToast === 'function') {
            showToast('info', 'This instructor already exists. They will be added to your department.');
          }
        }
        proceedWithAccountCreation(form, formData, submitBtn, originalText);
      })
      .catch((error) => {
        console.error("Error checking for duplicates:", error);
        if (submitBtn) {
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }
        // On error, proceed anyway (server will catch it)
        proceedWithAccountCreation(form, formData, submitBtn, originalText);
      });
  } else {
    // No name provided, proceed normally
    proceedWithAccountCreation(
      form,
      formData,
      document.getElementById("submitBtn"),
      ""
    );
  }
}

function proceedWithAccountCreation(form, formData, submitBtn, originalText) {
  // Show loading state
  if (submitBtn) {
    const btnText =
      originalText ||
      submitBtn.innerHTML ||
      '<i class="bi bi-hourglass-split me-2"></i>Creating Account...';
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="bi bi-hourglass-split me-2"></i>Creating Account...';
  }

  fetch("../../admin/management/add_account.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      // Check if response is OK
      if (!response.ok) {
        return response.text().then((text) => {
          console.error("Server error response:", text);
          throw new Error(
            `Server error (${response.status}): ${text.substring(0, 200)}`
          );
        });
      }

      // Check content type
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        return response.text().then((text) => {
          console.error("Non-JSON response:", text);
          throw new Error(
            "Server returned non-JSON response. This usually means a PHP error occurred. Check server logs."
          );
        });
      }

      return response.json();
    })
    .then((data) => {
      if (data.success) {
        // Reset form and close modal first
        form.reset();
        if (typeof ModalManager !== "undefined") {
          ModalManager.close("addAccountModal");
        } else {
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("addAccountModal")
          );
          if (modal) modal.hide();
        }

        // Show beautiful success message with SweetAlert
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "success",
            title: "Account Created Successfully!",
            html: data.verification_required
              ? `<p>${data.message || "Account created successfully!"}</p>
               <p class="mt-3"><strong>📧 Verification Email Sent</strong></p>
               <p class="text-muted">A verification code (OTP) has been sent to the instructor's email address. They must verify their email using the OTP code before they can login.</p>`
              : `<p>${data.message || "Account created successfully!"}</p>`,
            confirmButtonColor: "#28a745",
            confirmButtonText: "Great!",
            timer: 5000,
            timerProgressBar: true,
            customClass: {
              popup: "swal2-popup-custom",
              title: "swal2-title-custom",
              content: "swal2-content-custom",
            },
          });
        } else {
          let message = data.message || "Account created successfully!";
          if (data.verification_required) {
            message +=
              "\n\nA verification code (OTP) has been sent to the instructor's email. They must verify their email using the OTP code before they can login.";
          }
          alert(message);
        }

        // Reload dashboard data
        loadDashboardData();

        // If Users tab is visible, refresh users list
        const rolesTab = document.getElementById("roles");
        if (rolesTab && rolesTab.style.display !== "none") {
          loadAccounts();
        }
      } else {
        // Check if it's a duplicate error and show styled modal
        // Check both flags and message content for duplicate keywords
        const isDuplicateError =
          data.name_exists ||
          data.email_exists ||
          (data.message &&
            (data.message.toLowerCase().includes("duplicate") ||
              data.message.toLowerCase().includes("already exists") ||
              data.message.toLowerCase().includes("already registered")));

        if (isDuplicateError) {
          // Use the same styled duplicate modal
          let errorTitle = "Duplicate Account Detected";
          let errorMessage = "";
          let errorDetails = "";
          let hasNameDuplicate = false;
          let hasEmailDuplicate = false;

          // Check for name + role duplication
          if (data.name_exists) {
            hasNameDuplicate = true;
            const existingName = data.existing_name || "";
            let roleBadges = "";

            if (data.duplicate_roles && data.duplicate_roles.length > 0) {
              const roleNames = data.duplicate_roles.map((r) => r.role_name);
              roleBadges = roleNames
                .map((name) => `<span class="badge bg-danger">${name}</span>`)
                .join(" ");
            } else {
              roleBadges = '<span class="badge bg-danger">Same Role</span>';
            }

            errorMessage = hasEmailDuplicate
              ? `Multiple issues detected with this account:`
              : `A user with this name already exists with the following role(s):`;

            errorDetails += `
              <div class="duplicate-alert-content">
                <div class="duplicate-user-info">
                  <div class="duplicate-section-header">
                    <i class="bi bi-person-fill me-2"></i>
                    <strong>Name & Role Conflict</strong>
                  </div>
                  <div class="user-name-display mt-2">
                    <i class="bi bi-person-circle me-2"></i>
                    <strong>${existingName}</strong>
                  </div>
                  <div class="user-roles-display mt-2">
                    ${roleBadges}
                  </div>
                </div>
                <div class="alert-warning-box mt-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <span>Cannot create duplicate account with the same name and role combination.</span>
                </div>
              </div>
            `;
          }

          // Check for email duplication
          if (data.email_exists) {
            hasEmailDuplicate = true;
            const existingEmail = data.existing_email || "";

            if (!hasNameDuplicate) {
              errorMessage = `The email address is already registered.`;
            } else {
              errorMessage = `Multiple issues detected with this account:`;
            }

            errorDetails += `
              <div class="duplicate-alert-content ${
                hasNameDuplicate ? "mt-3" : ""
              }">
                <div class="duplicate-user-info">
                  <div class="duplicate-section-header">
                    <i class="bi bi-envelope-fill me-2"></i>
                    <strong>Email Conflict</strong>
                  </div>
                  <div class="user-email-display mt-2">
                    <i class="bi bi-envelope me-2"></i>
                    <strong>${existingEmail}</strong>
                  </div>
                </div>
                <div class="alert-warning-box mt-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <span>This email address is already in use. Please use a different email address.</span>
                </div>
              </div>
            `;
          }

          // Fallback message if no specific details - try to extract from message
          if (!errorDetails) {
            errorMessage = data.message || "Duplicate account detected";
            const messageText = data.message || "";
            let extractedInfo = "";

            // Try to extract information from message if flags aren't set
            const nameMatch =
              messageText.match(/name\s+"([^"]+)"/i) ||
              messageText.match(/name\s+([^"]+?)\s+already/i);
            const roleMatch =
              messageText.match(/role[:\s]+([^.]+)/i) ||
              messageText.match(/with the role[:\s]+([^.]+)/i);
            const emailMatch =
              messageText.match(/email[:\s]+([^\s,\.]+)/i) ||
              messageText.match(/"([^"]+@[^"]+)"/i);

            if (nameMatch || roleMatch || emailMatch) {
              extractedInfo = '<div class="duplicate-alert-content">';
              if (nameMatch && roleMatch) {
                extractedInfo += `
                  <div class="duplicate-user-info">
                    <div class="duplicate-section-header">
                      <i class="bi bi-person-fill me-2"></i>
                      <strong>Name & Role Conflict</strong>
                    </div>
                    <div class="user-name-display mt-2">
                      <i class="bi bi-person-circle me-2"></i>
                      <strong>${nameMatch[1].trim()}</strong>
                    </div>
                    <div class="user-roles-display mt-2">
                      <span class="badge bg-danger">${roleMatch[1].trim()}</span>
                    </div>
                  </div>
                `;
                errorMessage = `A user with this name already exists with the following role:`;
              } else if (emailMatch) {
                extractedInfo += `
                  <div class="duplicate-user-info">
                    <div class="duplicate-section-header">
                      <i class="bi bi-envelope-fill me-2"></i>
                      <strong>Email Conflict</strong>
                    </div>
                    <div class="user-email-display mt-2">
                      <i class="bi bi-envelope me-2"></i>
                      <strong>${emailMatch[1].trim()}</strong>
                    </div>
                  </div>
                `;
                errorMessage = `The email address is already registered.`;
              }
              extractedInfo += `
                <div class="alert-warning-box mt-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <span>${messageText}</span>
                </div>
              </div>`;
              errorDetails = extractedInfo;
            } else {
              errorDetails = `
                <div class="duplicate-alert-content">
                  <div class="alert-warning-box">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span>${errorMessage}</span>
                  </div>
                </div>
              `;
            }
          }

          // Show styled duplicate modal
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "error",
              title: errorTitle,
              html: `
                <div class="duplicate-alert-modal">
                  <p class="duplicate-alert-message">${errorMessage}</p>
                  ${errorDetails}
                </div>
              `,
              confirmButtonColor: "#800000",
              confirmButtonText:
                '<i class="bi bi-check-lg me-1"></i>Understood',
              customClass: {
                popup: "duplicate-alert-popup",
                title: "duplicate-alert-title",
                confirmButton: "duplicate-alert-button",
                htmlContainer: "duplicate-alert-container",
              },
              buttonsStyling: true,
              allowOutsideClick: true,
              allowEscapeKey: true,
              showClass: {
                popup: "animate__animated animate__fadeInDown animate__faster",
              },
              hideClass: {
                popup: "animate__animated animate__fadeOutUp animate__faster",
              },
            });
          } else {
            alert(
              errorMessage +
                (errorDetails
                  ? "\n\n" + errorDetails.replace(/<[^>]*>/g, "")
                  : "")
            );
          }
        } else {
          // Regular error (not duplicate)
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "error",
              title: "Account Creation Failed",
              text:
                data.message || "Failed to create account. Please try again.",
              confirmButtonColor: "#dc3545",
              confirmButtonText: "OK",
              customClass: {
                popup: "swal2-popup-custom",
                title: "swal2-title-custom",
                content: "swal2-content-custom",
              },
            });
          } else {
            alert("Error: " + (data.message || "Failed to create account"));
          }
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      console.error("Error details:", {
        message: error.message,
        stack: error.stack,
      });

      // Show detailed error message
      let errorMessage = "An error occurred while creating account.";
      if (error.message) {
        errorMessage += "\n\nError: " + error.message;
      }
      errorMessage +=
        "\n\nPlease check the browser console (F12) for more details.";

      // Show network error with SweetAlert
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "error",
          title: "Account Creation Error",
          html:
            "<p>" +
            errorMessage.replace(/\n/g, "<br>") +
            '</p><p class="text-muted small mt-2">Check the browser console (F12) for technical details.</p>',
          confirmButtonColor: "#dc3545",
          confirmButtonText: "OK",
          customClass: {
            popup: "swal2-popup-custom",
            title: "swal2-title-custom",
            content: "swal2-content-custom",
          },
        });
      } else {
        alert(errorMessage);
      }
    })
    .finally(() => {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
}

function resetStepNavigation() {
  currentStep = 1;

  // Hide all steps except first
  for (let i = 2; i <= window.totalSteps; i++) {
    document.getElementById(`step${i}`).classList.add("d-none");
  }
  document.getElementById("step1").classList.remove("d-none");

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
  console.log("🔵 [DEBUG] loadAccounts() called");
  console.log("🔵 [DEBUG] window.currentDeptId:", window.currentDeptId);
  console.log("🔵 [DEBUG] window.currentUserId:", window.currentUserId);
  console.log("🔵 [DEBUG] window.isAdminSupport:", window.isAdminSupport);

  // Check if Users tab is visible before loading (permission check)
  // Try multiple selectors to find the users/roles tab
  const usersTabLink = document.querySelector(
    'a[onclick*="roles"], a[href="#roles"], a[href*="roles"], a[onclick*="showTab(\'roles\']'
  );
  console.log("🔵 [DEBUG] usersTabLink found:", !!usersTabLink);

  if (usersTabLink) {
    const computedStyle = window.getComputedStyle(usersTabLink);
    console.log(
      "🔵 [DEBUG] usersTabLink.offsetParent:",
      usersTabLink.offsetParent
    );
    console.log(
      "🔵 [DEBUG] usersTabLink.style.display:",
      computedStyle.display
    );
    console.log(
      "🔵 [DEBUG] usersTabLink.style.visibility:",
      computedStyle.visibility
    );
    console.log(
      "🔵 [DEBUG] usersTabLink.parentElement:",
      usersTabLink.parentElement?.tagName
    );
    console.log(
      "🔵 [DEBUG] usersTabLink.parentElement display:",
      usersTabLink.parentElement
        ? window.getComputedStyle(usersTabLink.parentElement).display
        : "N/A"
    );
    console.log(
      "🔵 [DEBUG] usersTabLink.textContent:",
      usersTabLink.textContent?.trim()
    );
    console.log("🔵 [DEBUG] usersTabLink.href:", usersTabLink.href);
    console.log(
      "🔵 [DEBUG] usersTabLink.onclick:",
      usersTabLink.onclick?.toString()
    );

    // Check if tab is actually visible (not hidden by CSS or parent)
    const isVisible =
      usersTabLink.offsetParent !== null &&
      computedStyle.display !== "none" &&
      computedStyle.visibility !== "hidden";
    console.log("🔵 [DEBUG] Tab is visible:", isVisible);

    // If tab is not visible, check if it's because of permissions or CSS
    if (!isVisible) {
      console.warn("⚠️ [DEBUG] Users tab found but not visible");
      console.warn("⚠️ [DEBUG] This might be a CSS issue or permission issue");
      console.warn(
        "⚠️ [DEBUG] Attempting to load anyway if user has permission..."
      );

      // Don't return early - let the API check permissions instead
      // The API will return 403 if user doesn't have permission
    }
  } else {
    console.warn("⚠️ [DEBUG] Users tab link not found in DOM");
    console.warn("⚠️ [DEBUG] Searching for all sidebar links...");

    // Debug: List all sidebar links
    const allSidebarLinks = document.querySelectorAll(
      ".sidebar a, .offcanvas-body a"
    );
    console.log("🔵 [DEBUG] All sidebar links found:", allSidebarLinks.length);
    allSidebarLinks.forEach((link, index) => {
      if (
        link.textContent?.includes("User") ||
        link.href?.includes("roles") ||
        link.onclick?.toString().includes("roles")
      ) {
        console.log(`🔵 [DEBUG] Potential users tab link ${index}:`, {
          text: link.textContent?.trim(),
          href: link.href,
          onclick: link.onclick?.toString(),
          display: window.getComputedStyle(link).display,
          visible: link.offsetParent !== null,
        });
      }
    });

    // Don't return early - let the API check permissions instead
    console.warn(
      "⚠️ [DEBUG] Proceeding with API call - API will check permissions"
    );
  }

  // Always proceed with API call - let the backend check permissions
  // The API will return 403 if user doesn't have permission
  showUsersLoadingState();
  const params = new URLSearchParams({ dept_id: window.currentDeptId || 0 });
  const apiUrl = "../../admin/management/get_accounts.php?" + params.toString();
  console.log("🔵 [DEBUG] Fetching from URL:", apiUrl);
  console.log("🔵 [DEBUG] Request params:", {
    dept_id: window.currentDeptId || 0,
  });
  console.log(
    "🔵 [DEBUG] Proceeding with API call - backend will verify permissions"
  );

  return fetch(apiUrl)
    .then((r) => {
      console.log("🔵 [DEBUG] Response status:", r.status);
      console.log(
        "🔵 [DEBUG] Response headers:",
        Object.fromEntries(r.headers.entries())
      );

      if (r.status === 403) {
        // Silently handle permission errors - don't show error message
        console.warn(
          "⚠️ [DEBUG] Permission denied for viewing users - this is expected if user lacks permission"
        );
        hideUsersLoadingState();
        showUsersEmptyState();
        return { success: false, accounts: [] };
      }

      if (!r.ok) {
        console.error("❌ [DEBUG] Response not OK:", r.status, r.statusText);
        return r.text().then((text) => {
          console.error("❌ [DEBUG] Response body:", text);
          throw new Error(`HTTP ${r.status}: ${r.statusText}`);
        });
      }

      return r.json();
    })
    .then((data) => {
      console.log("🔵 [DEBUG] ========== ACCOUNTS API RESPONSE ==========");
      console.log("🔵 [DEBUG] Full response:", data);
      console.log("🔵 [DEBUG] Response success:", data.success);
      console.log(
        "🔵 [DEBUG] Accounts count:",
        Array.isArray(data.accounts) ? data.accounts.length : "NOT AN ARRAY"
      );
      console.log("🔵 [DEBUG] Debug info:", data.debug);

      if (data.debug) {
        console.log("🔵 [DEBUG] Allowed roles:", data.debug.allowedRoles);
        console.log(
          "🔵 [DEBUG] Role distribution:",
          data.debug.roleDistribution
        );
        console.log("🔵 [DEBUG] Department ID:", data.debug.deptId);
        console.log("🔵 [DEBUG] User Dept ID:", data.debug.userDeptId);
        console.log("🔵 [DEBUG] Is Admin Support:", data.debug.isAdminSupport);
        console.log(
          "🔵 [DEBUG] Has User Permission:",
          data.debug.hasUserPermission
        );
        console.log("🔵 [DEBUG] Sample account:", data.debug.sampleAccount);
      }

      console.log("🔵 [DEBUG] ============================================");
      if (!data.success) {
        if (data.message && data.message.includes("Unauthorized")) {
          // Silently handle permission errors - don't show error message
          console.log(
            "Unauthorized access to users - this is expected if user lacks permission"
          );
          hideUsersLoadingState();
          showUsersEmptyState();
          return { success: false, accounts: [] };
        }
        throw new Error(data.message || "Failed to load accounts");
      }
      __ACCOUNTS_CACHE__ = Array.isArray(data.accounts)
        ? data.accounts.map((n) => {
            const nameParts = [
              n.first_name,
              n.middle_initial ? n.middle_initial + "." : null,
              n.last_name,
              n.suffix,
            ].filter(Boolean);
            return {
              ...n,
              full_name: nameParts.join(" ").replace(/\s+/g, " ").trim(),
            };
          })
        : [];

      console.log(
        "🔵 [DEBUG] Processed accounts cache length:",
        __ACCOUNTS_CACHE__.length
      );
      console.log("🔵 [DEBUG] Processed accounts cache:", __ACCOUNTS_CACHE__);

      // Debug: Log role distribution in cache
      const cacheRoleDist = {};
      __ACCOUNTS_CACHE__.forEach((acc) => {
        const roleName = acc.role_name || `Role ID: ${acc.role_id}`;
        cacheRoleDist[roleName] = (cacheRoleDist[roleName] || 0) + 1;
      });
      console.log("🔵 [DEBUG] Cache role distribution:", cacheRoleDist);

      // Debug: Log instructor status distribution
      const instStatusDist = {};
      __ACCOUNTS_CACHE__.forEach((acc) => {
        const status = acc.inst_status || "No Status";
        instStatusDist[status] = (instStatusDist[status] || 0) + 1;
      });
      console.log("🔵 [DEBUG] Instructor status distribution:", instStatusDist);
      // Debug: Check if inst_id and inst_status are present
      if (__ACCOUNTS_CACHE__.length > 0) {
        console.log("Sample account data check:", {
          acc_id: __ACCOUNTS_CACHE__[0].acc_id,
          inst_id: __ACCOUNTS_CACHE__[0].inst_id,
          inst_status: __ACCOUNTS_CACHE__[0].inst_status,
          full_name: __ACCOUNTS_CACHE__[0].full_name,
          administration_hours: __ACCOUNTS_CACHE__[0].administration_hours,
          instruction_hours: __ACCOUNTS_CACHE__[0].instruction_hours,
          research_hours: __ACCOUNTS_CACHE__[0].research_hours,
        });
        // Log all accounts' inst_status values
        __ACCOUNTS_CACHE__.forEach((acc) => {
          console.log(
            `Account ${acc.acc_id} (${acc.full_name}): inst_status = "${acc.inst_status}", workload hours:`,
            {
              administration_hours: acc.administration_hours,
              instruction_hours: acc.instruction_hours,
              research_hours: acc.research_hours,
            }
          );
        });
      }

      // Calculate statistics
      __USERS_STATS__ = {
        total: __ACCOUNTS_CACHE__.length,
        moderators: __ACCOUNTS_CACHE__.filter((a) => Number(a.role_id) === 3)
          .length,
        instructors: __ACCOUNTS_CACHE__.filter((a) => Number(a.role_id) === 4)
          .length,
        pending: __ACCOUNTS_CACHE__.filter((a) => a.acc_status === "Pending")
          .length,
        regular: __ACCOUNTS_CACHE__.filter((a) => a.inst_status === "Regular")
          .length,
        parttime: __ACCOUNTS_CACHE__.filter(
          (a) => a.inst_status === "Part-Time"
        ).length,
        contractual: __ACCOUNTS_CACHE__.filter(
          (a) => a.inst_status === "Contractual"
        ).length,
      };

      updateUsersStatistics();

      // Load rank filter options from workload_policy table
      loadRankFilterOptions();

      // Attach filters and apply
      setTimeout(() => {
        attachAccountsFiltersIfNeeded();
        applyAccountsFilters();
      }, 300);

      hideUsersLoadingState();
      return data; // Return data for promise chaining
    })
    .catch((err) => {
      console.error("Accounts load error:", err);
      hideUsersLoadingState();
      showUsersEmptyState();
      throw err; // Re-throw to allow promise chaining
    });
}

function refreshUsersData() {
  loadAccounts();
}

// Store programs data for filtering
// Prevent redeclaration error
if (typeof __USER_PROGRAMS_CACHE__ === 'undefined') {
    var __USER_PROGRAMS_CACHE__ = [];
}

// DUPLICATE FUNCTION REMOVED - Using the applyAccountsFilters function at line 2729
// This function has been consolidated into the main applyAccountsFilters function below

/**
 * Loads programs for the user program filter dropdown
 */
function loadProgramsForUserFilter() {
  const programFilter = document.getElementById("accountsProgramFilter");
  if (!programFilter) {
    console.warn("accountsProgramFilter element not found");
    // Retry after a short delay in case the DOM isn't ready
    setTimeout(() => {
      const retryFilter = document.getElementById("accountsProgramFilter");
      if (retryFilter) {
        console.log("Retrying loadProgramsForUserFilter...");
        loadProgramsForUserFilter();
      }
    }, 500);
    return;
  }

  // Get API base path
  const path = window.location.pathname;
  let apiBasePath = "../../admin/management/";
  if (path.includes("/views/admin/")) {
    apiBasePath = "../../admin/management/";
  } else if (path.includes("/views/moderator/")) {
    apiBasePath = "../../admin/management/";
  }

  programFilter.innerHTML = '<option value="">Loading Programs...</option>';

  fetch(`${apiBasePath}get_schedule_form_data.php`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Programs data loaded:", data);
      programFilter.innerHTML = '<option value="">All Programs</option>';
      __USER_PROGRAMS_CACHE__ = [];

      if (data.success && data.programs && data.programs.length > 0) {
        console.log(`Loading ${data.programs.length} programs into filter`);
        data.programs.forEach((program) => {
          const option = document.createElement("option");
          option.value = program.program_id;
          const displayText =
            program.program_display ||
            (program.program_code && program.program_name
              ? `${program.program_code} - ${program.program_name}`
              : program.program_name || program.program_code || "Unknown");
          option.textContent = displayText;
          programFilter.appendChild(option);

          // Store program data with department info for filtering (ensure numeric types)
          __USER_PROGRAMS_CACHE__.push({
            program_id: Number(program.program_id),
            dept_id: Number(program.dept_id) || null,
            program_name: program.program_name,
            program_code: program.program_code,
          });
        });
        console.log("Programs cache populated:", __USER_PROGRAMS_CACHE__);
      } else {
        console.warn("No programs available");
        const option = document.createElement("option");
        option.value = "";
        option.textContent = "No programs available";
        option.disabled = true;
        programFilter.appendChild(option);
      }
    })
    .catch((error) => {
      console.error("Error loading programs for user filter:", error);
      programFilter.innerHTML =
        '<option value="">Error loading programs</option>';
    });
}

/**
 * Loads rank and designation filter options for the Users tab.
 * Academic Rank options are now defined statically (not tied to workload_policy values),
 * while Designations are still loaded from the workload_policy table.
 */
function loadRankFilterOptions() {
  const rankFilter = document.getElementById("accountsRankFilter");
  const designationFilter = document.getElementById(
    "accountsDesignationFilter"
  );

  console.log(
    "Loading rank and designation filter options from workload_policy..."
  );

  fetch("../../admin/management/get_workload_policy.php")
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Filter options data received:", data);

      // -------- Academic Rank filter options (static list) --------
      if (rankFilter) {
        const ACADEMIC_RANKS = [
          "University Professor",
          "Professor VI",
          "Professor V",
          "Professor IV",
          "Professor III",
          "Professor II",
          "Professor I",
          "Associate Professor V",
          "Associate Professor IV",
          "Associate Professor III",
          "Associate Professor II",
          "Associate Professor I",
          "Assistant Professor IV",
          "Assistant Professor III",
          "Assistant Professor II",
          "Assistant Professor I",
          "Instructor III",
          "Instructor II",
          "Instructor I",
        ];

        // Keep the default "All Ranks" option
        rankFilter.innerHTML = '<option value="">All Ranks</option>';

        ACADEMIC_RANKS.forEach((rank) => {
          const option = document.createElement("option");
          option.value = rank;
          option.textContent = rank;
          rankFilter.appendChild(option);
        });
      }

      // Load designation filter options
      if (
        designationFilter &&
        data.success &&
        data.data &&
        data.data.designations
      ) {
        // Keep the default "All Designations" option
        designationFilter.innerHTML =
          '<option value="">All Designations</option>';

        // Add "None" option first (for users without designation)
        const noneOption = document.createElement("option");
        noneOption.value = "None";
        noneOption.textContent = "None";
        designationFilter.appendChild(noneOption);

        // Add all designation options from database (excluding "None" if it exists)
        if (Array.isArray(data.data.designations)) {
          console.log(
            "Adding",
            data.data.designations.length,
            "designation options to filter"
          );
          data.data.designations.forEach((designation) => {
            // Skip "None" as we already added it above
            if (designation !== "None") {
              const option = document.createElement("option");
              option.value = designation;
              option.textContent = designation;
              designationFilter.appendChild(option);
            }
          });
        }
      } else if (designationFilter) {
        console.error(
          "Failed to load designation filter options:",
          data.message
        );
        designationFilter.innerHTML =
          '<option value="">All Designations</option>';
        // Add "None" as fallback
        const noneOption = document.createElement("option");
        noneOption.value = "None";
        noneOption.textContent = "None";
        designationFilter.appendChild(noneOption);
      }
    })
    .catch((error) => {
      console.error("Error loading filter options:", error);
      // Fallback to default options if API fails
      if (rankFilter) {
        rankFilter.innerHTML = '<option value="">All Ranks</option>';
      }
      if (designationFilter) {
        designationFilter.innerHTML =
          '<option value="">All Designations</option>';
      }
    });
}

// This function is now replaced by renderUsersTable above

function createUserTableRow(account) {
  const row = document.createElement("tr");

  const statusBadge = getStatusBadge(account.acc_status);
  const roleBadge = getRoleBadge(account.role_name);

  // Debug: Log inst_id to verify it's available
  if (!account.inst_id) {
    console.log(
      "Account without inst_id:",
      account.acc_id,
      account.full_name,
      account
    );
  }

  // Escape name for use in onclick attribute
  const escapedName = (account.full_name || "")
    .replace(/'/g, "\\'")
    .replace(/"/g, "&quot;");
  const instId = account.inst_id || 0;
  const disabledAttr = !account.inst_id ? "disabled" : "";

  // Determine if we should show Set Inactive button (for active users) or Set Active button (for inactive users)
  const accStatus = (account.acc_status || "").trim();
  const isActive = accStatus === "Active";
  const isInactive = accStatus === "Inactive" || accStatus === "Pending";

  console.log(
    "Creating row for:",
    account.acc_id,
    "acc_status:",
    accStatus,
    "isActive:",
    isActive,
    "isInactive:",
    isInactive
  );

  row.innerHTML = `
    <td>${account.acc_id}</td>
    <td>
      <div class="fw-medium">${account.full_name}</div>
    </td>
    <td>
      <span class="text-muted">@${account.acc_user}</span>
    </td>
    <td>${account.acc_email}</td>
    <td>
      <div class="d-flex align-items-center gap-1">
        ${roleBadge}
        ${
          typeof window.isAdminSupport !== "undefined" &&
          window.isAdminSupport === true
            ? `
          <button class="btn btn-xs btn-outline-success p-0" onclick="promoteRole(${account.acc_id}, '${escapedName}')" title="Promote Role" style="width: 20px; height: 20px; font-size: 10px; padding: 0;">
            <i class="bi bi-arrow-up"></i>
          </button>
          <button class="btn btn-xs btn-outline-warning p-0" onclick="demoteRole(${account.acc_id}, '${escapedName}')" title="Demote Role" style="width: 20px; height: 20px; font-size: 10px; padding: 0;">
            <i class="bi bi-arrow-down"></i>
          </button>
        `
            : ""
        }
      </div>
    </td>
    <td>${statusBadge}</td>
    <td>
      ${
        account.inst_status && account.inst_status.trim() !== ""
          ? `<span class="text-muted">${account.inst_status}</span>`
          : "-"
      }
    </td>
    <td>
      <div class="d-flex align-items-center gap-1">
        ${
          account.rank ? `<span class="text-muted">${account.rank}</span>` : "-"
        }
        ${
          account.inst_id
            ? `
          <button class="btn btn-xs btn-outline-success p-0" onclick="promoteRank(${account.acc_id}, '${escapedName}')" title="Promote Rank" style="width: 20px; height: 20px; font-size: 10px; padding: 0;">
            <i class="bi bi-arrow-up"></i>
          </button>
          <button class="btn btn-xs btn-outline-warning p-0" onclick="demoteRank(${account.acc_id}, '${escapedName}')" title="Demote Rank" style="width: 20px; height: 20px; font-size: 10px; padding: 0;">
            <i class="bi bi-arrow-down"></i>
          </button>
        `
            : ""
        }
      </div>
    </td>
    <td>
      <div class="d-flex align-items-center gap-1">
        ${
          account.designation && account.designation !== "None"
            ? `<span class="text-muted">${account.designation}</span>`
            : "-"
        }
        ${
          account.inst_id
            ? `
          <button class="btn btn-xs btn-outline-success p-0" onclick="promoteDesignation(${account.acc_id}, '${escapedName}')" title="Promote Designation" style="width: 20px; height: 20px; font-size: 10px; padding: 0;">
            <i class="bi bi-arrow-up"></i>
          </button>
          <button class="btn btn-xs btn-outline-warning p-0" onclick="demoteDesignation(${account.acc_id}, '${escapedName}')" title="Demote Designation" style="width: 20px; height: 20px; font-size: 10px; padding: 0;">
            <i class="bi bi-arrow-down"></i>
          </button>
        `
            : ""
        }
      </div>
    </td>
    <td class="text-center">
      <div class="d-flex gap-1 justify-content-center align-items-center">
        <button class="btn btn-sm btn-outline-info" onclick="viewInstructorSchedules(${instId}, '${escapedName}')" title="View Schedules" ${disabledAttr} style="min-width: 38px;">
          <i class="bi bi-eye"></i>
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="editUser(${
          account.acc_id
        })" title="Edit User">
          <i class="bi bi-pencil"></i>
        </button>
        ${
          // Show "Remove from my department" button if user is active and not the current admin
          isActive && account.acc_id != (window.currentUserId || 0)
            ? `<button class="btn btn-sm btn-outline-secondary" onclick="removeInstructorFromDepartment(${account.acc_id}, ${instId}, '${escapedName}')" title="Remove from My Department"><i class="bi bi-box-arrow-right"></i></button>`
            : ""
        }
        ${
          isActive
            ? `<button class="btn btn-sm btn-outline-warning" onclick="setUserInactive(${account.acc_id}, '${escapedName}')" title="Set Inactive"><i class="bi bi-pause-circle"></i></button>`
            : ""
        }
        ${
          isInactive
            ? `<button class="btn btn-sm btn-outline-success" onclick="setUserActive(${account.acc_id}, '${escapedName}')" title="Set Active"><i class="bi bi-person-check"></i></button>`
            : ""
        }
        ${
          isInactive
            ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${account.acc_id})" title="Delete User"><i class="bi bi-trash"></i></button>`
            : ""
        }
      </div>
    </td>
  `;

  return row;
}

function getStatusBadge(status) {
  // Only show green badge for "Active" status, plain text for others
  if (status === "Active") {
    return `<span class="badge bg-success">${status}</span>`;
  }
  return `<span class="text-muted">${status || "-"}</span>`;
}

function getRoleBadge(roleName) {
  // Show plain text for roles (no colors)
  return `<span class="text-muted">${roleName || "-"}</span>`;
}

function attachAccountsFiltersIfNeeded() {
  // Prevent duplicate event listeners
  if (attachAccountsFiltersIfNeeded.__bound) {
    return;
  }
  attachAccountsFiltersIfNeeded.__bound = true;

  // Attach event listeners to filter dropdowns
  const statusFilter = document.getElementById("accountsStatusFilter");
  const roleFilter = document.getElementById("accountsRoleFilter");
  const rankFilter = document.getElementById("accountsRankFilter");
  const designationFilter = document.getElementById(
    "accountsDesignationFilter"
  );
  const searchInput = document.getElementById("accountsSearch");
  const sortSelect = document.getElementById("accountsSort");

  if (statusFilter && !statusFilter.__bound) {
    statusFilter.addEventListener("change", applyAccountsFilters);
    statusFilter.__bound = true;
  }
  if (roleFilter && !roleFilter.__bound) {
    roleFilter.addEventListener("change", applyAccountsFilters);
    roleFilter.__bound = true;
  }
  if (rankFilter && !rankFilter.__bound) {
    rankFilter.addEventListener("change", applyAccountsFilters);
    rankFilter.__bound = true;
  }
  if (designationFilter && !designationFilter.__bound) {
    designationFilter.addEventListener("change", applyAccountsFilters);
    designationFilter.__bound = true;
  }
  if (searchInput && !searchInput.__bound) {
    searchInput.addEventListener("input", applyAccountsFilters);
    searchInput.__bound = true;
  }
  if (sortSelect && !sortSelect.__bound) {
    sortSelect.addEventListener("change", applyAccountsFilters);
    sortSelect.__bound = true;
  }
}

function editUser(accId) {
  console.log("Edit user:", accId);

  // Find the user in the accounts cache
  const user = __ACCOUNTS_CACHE__.find((account) => account.acc_id == accId);
  if (!user) {
    console.error("User not found:", accId);
    alert("User not found");
    return;
  }

  console.log("User data for editing:", {
    acc_id: user.acc_id,
    inst_status: user.inst_status,
    rank: user.rank,
    designation: user.designation,
    inst_id: user.inst_id,
  });

  // Store workload hours values globally to set when step 3 becomes active
  window.__EDIT_ACCOUNT_WORKLOAD_HOURS__ = {
    administration_hours:
      user.administration_hours !== null &&
      user.administration_hours !== undefined
        ? parseInt(user.administration_hours)
        : 0,
    instruction_hours:
      user.instruction_hours !== null && user.instruction_hours !== undefined
        ? parseInt(user.instruction_hours)
        : 0,
    research_hours:
      user.research_hours !== null && user.research_hours !== undefined
        ? parseInt(user.research_hours)
        : 0,
    extension_hours:
      user.extension_hours !== null && user.extension_hours !== undefined
        ? parseInt(user.extension_hours)
        : 0,
    instructional_functions_hours:
      user.instructional_functions_hours !== null &&
      user.instructional_functions_hours !== undefined
        ? parseInt(user.instructional_functions_hours)
        : 0,
    consultation_hours:
      user.consultation_hours !== null && user.consultation_hours !== undefined
        ? parseInt(user.consultation_hours)
        : 0,
  };

  console.log(
    "Stored workload hours for account:",
    user.acc_id,
    window.__EDIT_ACCOUNT_WORKLOAD_HOURS__
  );
  console.log("User workload hours in cache:", {
    administration_hours: user.administration_hours,
    instruction_hours: user.instruction_hours,
    research_hours: user.research_hours,
    extension_hours: user.extension_hours,
    instructional_functions_hours: user.instructional_functions_hours,
    consultation_hours: user.consultation_hours,
  });

  // Populate the edit modal with user data
  document.getElementById("edit_acc_id").value = user.acc_id;

  // Account Information
  document.getElementById("edit_fname").value = user.first_name || "";
  document.getElementById("edit_lname").value = user.last_name || "";
  document.getElementById("edit_minitial").value = user.middle_initial || "";
  document.getElementById("edit_suffix").value = user.suffix || "";
  document.getElementById("edit_acc_user").value = user.acc_user || "";
  document.getElementById("edit_acc_email").value = user.acc_email || "";
  document.getElementById("edit_dept_id").value = user.dept_id || "";

  // Store rank and designation values to set after dropdowns are populated
  const rankValue = user.rank || "";
  const designationValue = user.designation || "None";

  // Instructor phone field
  const phoneEl = document.getElementById("edit_inst_phone");
  if (phoneEl) {
    phoneEl.value = user.inst_phone || "";
  }

  // Initialize step to 1 before showing modal
  window.editAccountCurrentStep = 1;

  // Show the edit modal first
  const modal = new bootstrap.Modal(
    document.getElementById("editAccountModal")
  );
  modal.show();

  // Set employment status after modal is shown to ensure dropdown is ready
  setTimeout(() => {
    const instStatusEl = document.getElementById("edit_inst_status");
    if (instStatusEl) {
      const instStatus = (user.inst_status || "").trim();
      console.log(
        "Setting inst_status dropdown to:",
        instStatus,
        "for user:",
        user.acc_id,
        "Full user object:",
        user
      );

      // Set the value
      instStatusEl.value = instStatus;

      // Verify it was set correctly
      if (instStatus && instStatusEl.value !== instStatus) {
        console.warn(
          "inst_status dropdown value mismatch. Tried to set:",
          instStatus,
          "but got:",
          instStatusEl.value
        );
        console.log(
          "Available options:",
          Array.from(instStatusEl.options).map((opt) => ({
            value: opt.value,
            text: opt.text,
          }))
        );
      } else if (instStatus) {
        console.log("inst_status successfully set to:", instStatusEl.value);
      } else {
        console.log(
          "inst_status is empty/null for user:",
          user.acc_id,
          "leaving dropdown as default"
        );
      }
    } else {
      console.error("edit_inst_status element not found after modal shown!");
    }
  }, 100);

  // Load programs after modal is shown and set program_id
  setTimeout(() => {
    const programSelect = document.getElementById("edit_program_id");
    if (programSelect) {
      const apiBasePath =
        typeof getApiBasePath === "function"
          ? getApiBasePath()
          : "../../admin/management/";
      const params = new URLSearchParams({
        dept_id: window.currentDeptId || 0,
      });
      fetch(`${apiBasePath}get_schedule_form_data.php?${params.toString()}`)
        .then((response) => response.json())
        .then((data) => {
          programSelect.innerHTML =
            '<option value="">Select Program/Course</option>';
          if (data.success && data.programs && data.programs.length > 0) {
            data.programs.forEach((program) => {
              const option = document.createElement("option");
              option.value = program.program_id;
              const displayText =
                program.program_display ||
                (program.program_code && program.program_name
                  ? `${program.program_code} - ${program.program_name}`
                  : program.program_name || program.program_code || "Unknown");
              option.textContent = displayText;
              programSelect.appendChild(option);
            });
          }

          // Set program_id after options are loaded
          if (user.program_id) {
            programSelect.value = user.program_id;
            console.log("Set program_id to:", user.program_id);
          }
        })
        .catch((error) => {
          console.error("Error loading programs for Edit Account:", error);
          if (programSelect) {
            programSelect.innerHTML =
              '<option value="">Error Loading Programs</option>';
          }
        });
    }
  }, 100);

  // Store values to set after workload policy data is loaded
  // These will be passed to loadWorkloadPolicyData via the shown.bs.modal event
  window.__EDIT_ACCOUNT_RANK_DESIGNATION__ = {
    rank: rankValue,
    designation: designationValue,
  };

  // After modal is shown, ensure fields are properly enabled/disabled based on role
  // Use setTimeout to ensure modal is fully rendered
  setTimeout(() => {
    const instStatus = user.inst_status || "";
    if (instStatus) {
      handleEmploymentStatusChange(instStatus, "edit");
    }
  }, 500);
}

function setUserInactive(accId, userName) {
  // Find the user in the accounts cache to get their name
  const user = __ACCOUNTS_CACHE__.find((account) => account.acc_id == accId);
  const displayName = user
    ? `${user.first_name} ${user.last_name}`
    : userName || "this user";

  const apiBasePath =
    typeof getApiBasePath === "function"
      ? getApiBasePath()
      : "../../admin/management/";

  // Helper to actually perform the status update (after confirmation)
  const doDeactivate = (hasSchedules, removedInfoText) => {
    const formData = new FormData();
    formData.append("acc_id", accId);
    formData.append("status", "Inactive");

    fetch(`${apiBasePath}update_account_status.php`, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data.success) {
          throw new Error(data.message || "Failed to set user inactive");
        }

        const baseMessage = `${displayName} has been set to inactive.`;
        const extra =
          removedInfoText ||
          (data.removed_schedules > 0
            ? ` All ${data.removed_schedules} existing schedules and related workload/room requests were removed.`
            : "");

        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "success",
            title: "Success!",
            text: baseMessage + extra,
            timer: 2500,
            showConfirmButton: false,
          });
        } else {
          alert(baseMessage + extra);
        }

        // Reload accounts to refresh both tables
        loadAccounts();
      })
      .catch((error) => {
        console.error("Error setting user inactive:", error);
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "error",
            title: "Error",
            text:
              error.message || "Failed to set user inactive. Please try again.",
            confirmButtonColor: "#dc3545",
          });
        } else {
          alert(error.message || "Failed to set user inactive");
        }
      });
  };

  const confirmDeactivate = (
    hasSchedules,
    scheduleCount,
    workloadCount,
    requestCount
  ) => {
    const hasAny =
      hasSchedules ||
      scheduleCount > 0 ||
      workloadCount > 0 ||
      requestCount > 0;

    const scheduleText = hasAny
      ? `<br><br><span class="text-danger">
           This instructor currently has assigned schedules and/or workload records.
           Setting them to <strong>Inactive</strong> will permanently remove all their assigned subjects,
           workload entries, and room requests.
         </span>`
      : `<br><br><span class="text-warning">
           This user will be moved to the inactive users table.
         </span>`;

    if (typeof Swal !== "undefined") {
      Swal.fire({
        title: "Set User Inactive",
        html: `Are you sure you want to set <strong>${displayName}</strong> to inactive?${scheduleText}`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#ffc107",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, set inactive",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (result.isConfirmed) {
          // If we already know there are schedules, clean them up before final status change
          if (hasAny) {
            const cleanupForm = new FormData();
            cleanupForm.append("acc_id", accId);
            fetch(`${apiBasePath}deactivate_instructor_dependencies.php`, {
              method: "POST",
              body: cleanupForm,
            })
              .then((r) => r.json())
              .then((cleanData) => {
                if (!cleanData.success) {
                  throw new Error(
                    cleanData.message || "Failed to remove assigned schedules."
                  );
                }
                const infoText = cleanData.has_schedules
                  ? ` All assigned subjects and related workload/room requests were removed.`
                  : "";
                doDeactivate(cleanData.has_schedules, infoText);
              })
              .catch((err) => {
                console.error(
                  "Error cleaning up schedules before deactivation:",
                  err
                );
                if (typeof Swal !== "undefined") {
                  Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                      err.message ||
                      "Failed to remove assigned schedules. User was not set inactive.",
                    confirmButtonColor: "#dc3545",
                  });
                } else {
                  alert(
                    err.message ||
                      "Failed to remove assigned schedules. User was not set inactive."
                  );
                }
              });
          } else {
            doDeactivate(false, "");
          }
        }
      });
    } else {
      // Fallback to regular confirm if SweetAlert2 is not available
      const extra = hasAny
        ? " This will also permanently remove all their assigned subjects, workloads, and room requests."
        : "";
      if (
        confirm(
          `Are you sure you want to set ${displayName} to inactive?${extra}`
        )
      ) {
        if (hasAny) {
          const cleanupForm = new FormData();
          cleanupForm.append("acc_id", accId);
          fetch(`${apiBasePath}deactivate_instructor_dependencies.php`, {
            method: "POST",
            body: cleanupForm,
          })
            .then((r) => r.json())
            .then((cleanData) => {
              if (!cleanData.success) {
                throw new Error(
                  cleanData.message || "Failed to remove assigned schedules."
                );
              }
              const infoText = cleanData.has_schedules
                ? " All assigned subjects and related workload/room requests were removed."
                : "";
              doDeactivate(cleanData.has_schedules, infoText);
            })
            .catch((err) => {
              console.error(
                "Error cleaning up schedules before deactivation:",
                err
              );
              alert(
                err.message ||
                  "Failed to remove assigned schedules. User was not set inactive."
              );
            });
        } else {
          doDeactivate(false, "");
        }
      }
    }
  };

  // First, quickly check on the server if this instructor has schedules/workload/requests
  const preCheckForm = new FormData();
  preCheckForm.append("acc_id", accId);
  fetch(`${apiBasePath}deactivate_instructor_dependencies.php`, {
    method: "POST",
    body: preCheckForm,
  })
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) {
        throw new Error(data.message || "Failed to check existing schedules.");
      }
      confirmDeactivate(
        data.has_schedules,
        data.removed_schedules || 0,
        data.removed_workloads || 0,
        data.removed_requests || 0
      );
    })
    .catch((err) => {
      console.error(
        "Error checking existing schedules before deactivation:",
        err
      );
      // If the pre-check fails, fall back to simple confirmation without schedule info
      confirmDeactivate(false, 0, 0, 0);
    });
}

function setUserActive(accId, userName) {
  // Find the user in the accounts cache to get their name
  const user = __ACCOUNTS_CACHE__.find((account) => account.acc_id == accId);
  const displayName = user
    ? `${user.first_name} ${user.last_name}`
    : userName || "this user";

  // Use SweetAlert2 for better confirmation dialog
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Set User Active",
      html: `Are you sure you want to set <strong>${displayName}</strong> to active?<br><br>
             <span class="text-success">This user will be moved to the active users table.</span>`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#28a745",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes, set active",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        performSetUserActive(accId, displayName);
      }
    });
  } else {
    // Fallback to regular confirm if SweetAlert2 is not available
    if (confirm(`Are you sure you want to set ${displayName} to active?`)) {
      performSetUserActive(accId, displayName);
    }
  }
}

function performSetUserActive(accId, userName) {
  const apiBasePath =
    typeof getApiBasePath === "function"
      ? getApiBasePath()
      : "../../admin/management/";
  const formData = new FormData();
  formData.append("acc_id", accId);
  formData.append("status", "Active");

  fetch(`${apiBasePath}update_account_status.php`, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Show success message
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "success",
            title: "Success!",
            text: `${userName} has been set to active.`,
            timer: 2000,
            showConfirmButton: false,
          });
        } else {
          alert(`${userName} has been set to active.`);
        }

        // Reload accounts to refresh both tables
        loadAccounts();
      } else {
        throw new Error(data.message || "Failed to set user active");
      }
    })
    .catch((error) => {
      console.error("Error setting user active:", error);
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: error.message || "Failed to set user active. Please try again.",
          confirmButtonColor: "#dc3545",
        });
      } else {
        alert(error.message || "Failed to set user active");
      }
    });
}

// Promote/Demote Functions
function promoteRank(accId, userName) {
  const proceed = () => {
    const formData = new FormData();
    formData.append("acc_id", accId);
    formData.append("action", "promote");

    fetch("../../admin/management/promote_demote_rank.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("success", data.message || "Rank promoted successfully");
          } else {
            alert(data.message || "Rank promoted successfully");
          }
          refreshUsersData();
        } else {
          if (typeof showToast === "function") {
            showToast("error", data.message || "Failed to promote rank");
          } else {
            alert(data.message || "Failed to promote rank");
          }
        }
      })
      .catch((error) => {
        console.error("Error promoting rank:", error);
        if (typeof showToast === "function") {
          showToast("error", "An error occurred while promoting rank");
        } else {
          alert("An error occurred while promoting rank");
        }
      });
  };

  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Promote rank?",
      text: `Are you sure you want to promote ${userName}'s rank?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, promote",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#0d6efd",
      cancelButtonColor: "#6c757d",
    }).then((result) => {
      if (result.isConfirmed) {
        proceed();
      }
    });
  } else if (confirm(`Are you sure you want to promote ${userName}'s rank?`)) {
    proceed();
  }
}

function demoteRank(accId, userName) {
  const proceed = () => {
    const formData = new FormData();
    formData.append("acc_id", accId);
    formData.append("action", "demote");

    fetch("../../admin/management/promote_demote_rank.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("success", data.message || "Rank demoted successfully");
          } else {
            alert(data.message || "Rank demoted successfully");
          }
          refreshUsersData();
        } else {
          if (typeof showToast === "function") {
            showToast("error", data.message || "Failed to demote rank");
          } else {
            alert(data.message || "Failed to demote rank");
          }
        }
      })
      .catch((error) => {
        console.error("Error demoting rank:", error);
        if (typeof showToast === "function") {
          showToast("error", "An error occurred while demoting rank");
        } else {
          alert("An error occurred while demoting rank");
        }
      });
  };

  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Demote rank?",
      text: `Are you sure you want to demote ${userName}'s rank?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, demote",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
    }).then((result) => {
      if (result.isConfirmed) {
        proceed();
      }
    });
  } else if (confirm(`Are you sure you want to demote ${userName}'s rank?`)) {
    proceed();
  }
}

function promoteDesignation(accId, userName) {
  if (!confirm(`Are you sure you want to promote ${userName}'s designation?`)) {
    return;
  }

  const formData = new FormData();
  formData.append("acc_id", accId);
  formData.append("action", "promote");

  fetch("../../admin/management/promote_demote_designation.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (typeof showToast === "function") {
          showToast(
            "success",
            data.message || "Designation promoted successfully"
          );
        } else {
          alert(data.message || "Designation promoted successfully");
        }
        refreshUsersData();
      } else {
        if (typeof showToast === "function") {
          showToast("error", data.message || "Failed to promote designation");
        } else {
          alert(data.message || "Failed to promote designation");
        }
      }
    })
    .catch((error) => {
      console.error("Error promoting designation:", error);
      if (typeof showToast === "function") {
        showToast("error", "An error occurred while promoting designation");
      } else {
        alert("An error occurred while promoting designation");
      }
    });
}

function demoteDesignation(accId, userName) {
  if (!confirm(`Are you sure you want to demote ${userName}'s designation?`)) {
    return;
  }

  const formData = new FormData();
  formData.append("acc_id", accId);
  formData.append("action", "demote");

  fetch("../../admin/management/promote_demote_designation.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (typeof showToast === "function") {
          showToast(
            "success",
            data.message || "Designation demoted successfully"
          );
        } else {
          alert(data.message || "Designation demoted successfully");
        }
        refreshUsersData();
      } else {
        if (typeof showToast === "function") {
          showToast("error", data.message || "Failed to demote designation");
        } else {
          alert(data.message || "Failed to demote designation");
        }
      }
    })
    .catch((error) => {
      console.error("Error demoting designation:", error);
      if (typeof showToast === "function") {
        showToast("error", "An error occurred while demoting designation");
      } else {
        alert("An error occurred while demoting designation");
      }
    });
}

function promoteRole(accId, userName) {
  if (!confirm(`Are you sure you want to promote ${userName}'s role?`)) {
    return;
  }

  const formData = new FormData();
  formData.append("acc_id", accId);
  formData.append("action", "promote");

  fetch("../../admin/management/promote_demote_role.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (typeof showToast === "function") {
          showToast("success", data.message || "Role promoted successfully");
        } else {
          alert(data.message || "Role promoted successfully");
        }
        refreshUsersData();
      } else {
        if (typeof showToast === "function") {
          showToast("error", data.message || "Failed to promote role");
        } else {
          alert(data.message || "Failed to promote role");
        }
      }
    })
    .catch((error) => {
      console.error("Error promoting role:", error);
      if (typeof showToast === "function") {
        showToast("error", "An error occurred while promoting role");
      } else {
        alert("An error occurred while promoting role");
      }
    });
}

function demoteRole(accId, userName) {
  if (!confirm(`Are you sure you want to demote ${userName}'s role?`)) {
    return;
  }

  const formData = new FormData();
  formData.append("acc_id", accId);
  formData.append("action", "demote");

  fetch("../../admin/management/promote_demote_role.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (typeof showToast === "function") {
          showToast("success", data.message || "Role demoted successfully");
        } else {
          alert(data.message || "Role demoted successfully");
        }
        refreshUsersData();
      } else {
        if (typeof showToast === "function") {
          showToast("error", data.message || "Failed to demote role");
        } else {
          alert(data.message || "Failed to demote role");
        }
      }
    })
    .catch((error) => {
      console.error("Error demoting role:", error);
      if (typeof showToast === "function") {
        showToast("error", "An error occurred while demoting role");
      } else {
        alert("An error occurred while demoting role");
      }
    });
}

function deleteUser(accId) {
  // Find the user in the accounts cache to get their name
  const user = __ACCOUNTS_CACHE__.find((account) => account.acc_id == accId);
  const userName = user ? `${user.first_name} ${user.last_name}` : "this user";

  // Use SweetAlert2 for better confirmation dialog
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Delete User Account",
      html: `Are you sure you want to delete <strong>${userName}</strong>?<br><br>
             <span class="text-danger">This action cannot be undone!</span>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, delete it!",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        performDeleteUser(accId, userName);
      }
    });
  } else {
    // Fallback to regular confirm if SweetAlert2 is not available
    if (
      confirm(
        `Are you sure you want to delete ${userName}? This action cannot be undone!`
      )
    ) {
      performDeleteUser(accId, userName);
    }
  }
}

// Store schedules data globally for tab switching
let __CURRENT_INSTRUCTOR_SCHEDULES__ = null;
let __CURRENT_INSTRUCTOR_ID__ = null;
let __CURRENT_INSTRUCTOR_ROLE__ = null;
let __CURRENT_SCHEDULE_COUNT__ = 0;

function viewInstructorSchedules(instId, instructorName) {
  if (!instId || instId === 0) {
    showToast(
      "warning",
      "This user is not an instructor or instructor ID is missing."
    );
    return;
  }

  // Store current instructor ID
  __CURRENT_INSTRUCTOR_ID__ = instId;
  __CURRENT_INSTRUCTOR_SCHEDULES__ = null;
  __CURRENT_INSTRUCTOR_ROLE__ = null;
  __CURRENT_SCHEDULE_COUNT__ = 0;

  // Show modal and loading state
  const modalElement = document.getElementById("viewSchedulesModal");
  const modal = new bootstrap.Modal(modalElement);

  // Set up tab event listeners when modal is shown
  modalElement.addEventListener(
    "shown.bs.modal",
    function () {
      setupScheduleTabListeners();
    },
    { once: true }
  );

  modal.show();

  // Reset modal content
  const loadingEl = document.getElementById("schedulesLoading");
  const errorEl = document.getElementById("schedulesError");
  const contentEl = document.getElementById("schedulesContent");
  const noSchedulesEl = document.getElementById("noSchedules");

  if (loadingEl) loadingEl.style.display = "block";
  if (errorEl) errorEl.style.display = "none";
  if (contentEl) contentEl.style.display = "none";
  if (noSchedulesEl) noSchedulesEl.style.display = "none";

  const nameDisplayEl = document.getElementById("instructorNameDisplay");
  if (nameDisplayEl) nameDisplayEl.textContent = instructorName || "Instructor";

  // Clear existing schedule blocks
  document
    .querySelectorAll("#instructorScheduleDaysWrapper .schedule-block")
    .forEach((block) => block.remove());

  // Reset tabs to Information tab (first tab)
  const scheduleTab = document.getElementById("schedule-tab");
  const informationTab = document.getElementById("information-tab");
  const schedulePane = document.getElementById("schedule-pane");
  const informationPane = document.getElementById("information-pane");

  if (scheduleTab && informationTab && schedulePane && informationPane) {
    informationTab.classList.add("active");
    scheduleTab.classList.remove("active");
    informationPane.classList.add("show", "active");
    schedulePane.classList.remove("show", "active");
    informationPane.style.display = "block";
    schedulePane.style.display = "none";

    // Trigger Bootstrap tab switch to Information tab
    const informationTabTrigger = new bootstrap.Tab(informationTab);
    informationTabTrigger.show();
  }

  // Find instructor in accounts cache for information
  const instructor = __ACCOUNTS_CACHE__.find(
    (account) => account.inst_id == instId
  );
  console.log("Looking for instructor with instId:", instId);
  console.log("Found instructor:", instructor);
  if (instructor) {
    // Store role for display
    __CURRENT_INSTRUCTOR_ROLE__ = instructor.role_name || "User";
    populateInstructorInformation(instructor);
    console.log("Instructor information populated");

    // Update subtitle to show role (Information tab is default)
    updateModalSubtitle(__CURRENT_INSTRUCTOR_ROLE__);
  } else {
    console.warn("Instructor not found in cache, clearing information fields");
    // Clear information fields if instructor not found
    clearInstructorInformation();
    __CURRENT_INSTRUCTOR_ROLE__ = "User";
    updateModalSubtitle(__CURRENT_INSTRUCTOR_ROLE__);
  }

  // Fetch schedules
  const apiBasePath = getApiBasePath
    ? getApiBasePath()
    : "../../admin/management/";
  const url = `${apiBasePath}get_instructor_schedules.php?instructor_id=${instId}`;

  fetch(url)
    .then((response) => {
      if (!response.ok) {
        if (response.status === 403) {
          throw new Error("You do not have permission to view schedules.");
        } else if (response.status === 404) {
          throw new Error("Instructor not found.");
        } else {
          throw new Error("Failed to load schedules. Please try again.");
        }
      }
      return response.json();
    })
    .then((data) => {
      console.log("Schedules data received:", data);

      // Always hide loading
      const loadingEl = document.getElementById("schedulesLoading");
      if (loadingEl) {
        loadingEl.style.display = "none";
      }

      if (!data.success) {
        document.getElementById("schedulesError").textContent =
          data.message || "Failed to load schedules.";
        document.getElementById("schedulesError").style.display = "block";
        return;
      }

      const schedules = data.schedules || [];
      const count = schedules.length;

      console.log("Schedules count:", count, "Schedules:", schedules);

      // Store schedules globally
      __CURRENT_INSTRUCTOR_SCHEDULES__ = schedules;
      __CURRENT_SCHEDULE_COUNT__ = count;

      // Don't update subtitle here - it will be updated based on active tab
      // The Information tab is default, so role is already shown

      // Show content area - use !important to override any CSS
      const contentEl = document.getElementById("schedulesContent");
      if (contentEl) {
        contentEl.style.display = "block";
        contentEl.style.visibility = "visible";
        contentEl.style.opacity = "1";
        console.log("schedulesContent displayed");
      } else {
        console.error("schedulesContent element not found!");
        return;
      }

      // Ensure tab content container is visible
      const tabContent = document.getElementById("instructorModalTabContent");
      if (tabContent) {
        tabContent.style.display = "block";
        tabContent.style.visibility = "visible";
        console.log("Tab content container made visible");
      }

      // Ensure tab panes are visible - Information tab is default
      const schedulePane = document.getElementById("schedule-pane");
      const informationPane = document.getElementById("information-pane");

      if (informationPane) {
        informationPane.classList.add("show", "active");
        informationPane.style.display = "block";
        informationPane.style.visibility = "visible";
        informationPane.style.opacity = "1";
        console.log("Information pane made visible (default)");
      } else {
        console.error("information-pane element not found!");
      }

      if (schedulePane) {
        schedulePane.classList.remove("show", "active");
        schedulePane.style.display = "none";
      }

      // Don't render schedule immediately - Information tab is default
      // Schedule will render when Schedule tab is clicked
      console.log(
        "Schedules loaded:",
        count,
        "schedules. Will render when Schedule tab is clicked."
      );

      if (count === 0) {
        // Show no schedules message (will show when Schedule tab is clicked)
        const noSchedulesEl = document.getElementById("noSchedules");
        if (noSchedulesEl) {
          // Keep it hidden for now, will show when Schedule tab is active
          noSchedulesEl.style.display = "none";
        }
      }
    })
    .catch((error) => {
      console.error("Error loading schedules:", error);
      const loadingEl = document.getElementById("schedulesLoading");
      const errorEl = document.getElementById("schedulesError");
      if (loadingEl) loadingEl.style.display = "none";
      if (errorEl) {
        errorEl.textContent =
          error.message || "An error occurred while loading schedules.";
        errorEl.style.display = "block";
      }
    });
}

// Function to render schedule grid
function renderScheduleGrid(schedules) {
  console.log("renderScheduleGrid called with schedules:", schedules);

  // Ensure schedule pane is visible
  const schedulePane = document.getElementById("schedule-pane");
  if (!schedulePane) {
    console.error("schedule-pane not found!");
    return;
  }

  // Make sure pane is visible with all necessary styles
  schedulePane.classList.add("show", "active");
  schedulePane.style.display = "block";
  schedulePane.style.visibility = "visible";
  schedulePane.style.opacity = "1";
  schedulePane.style.height = "auto";
  schedulePane.style.minHeight = "400px";

  console.log("Schedule pane visibility forced:", {
    display: schedulePane.style.display,
    classes: schedulePane.className,
    computedDisplay: window.getComputedStyle(schedulePane).display,
  });

  // Clear existing schedule blocks first
  document
    .querySelectorAll("#instructorScheduleDaysWrapper .schedule-block")
    .forEach((block) => block.remove());

  const count = schedules ? schedules.length : 0;
  console.log("Schedule count:", count);

  if (count === 0) {
    const noSchedulesEl = document.getElementById("noSchedules");
    if (noSchedulesEl) {
      noSchedulesEl.style.display = "block";
    }
    // Clear the grid structure if no schedules
    const timeColumn = document.getElementById("instructorScheduleTimeColumn");
    const daysWrapper = document.getElementById(
      "instructorScheduleDaysWrapper"
    );
    if (timeColumn) {
      timeColumn.innerHTML =
        '<div class="calendar-header" style="position: sticky; top: 0; z-index: 11;">Time</div>';
    }
    if (daysWrapper) {
      daysWrapper.innerHTML = "";
    }
    console.log("No schedules to render");
    return;
  }

  const noSchedulesEl = document.getElementById("noSchedules");
  if (noSchedulesEl) {
    noSchedulesEl.style.display = "none";
  }

  // Generate calendar structure
  console.log("Generating calendar structure...");
  try {
    generateInstructorScheduleGrid(schedules);
    console.log("Calendar structure generated successfully");

    // Force visibility of calendar containers
    const calendarContainer = document.querySelector(
      "#schedule-pane .calendar-container"
    );
    const calendarGrid = document.querySelector(
      "#schedule-pane .calendar-grid"
    );
    const timeColumn = document.getElementById("instructorScheduleTimeColumn");
    const daysWrapper = document.getElementById(
      "instructorScheduleDaysWrapper"
    );

    if (calendarContainer) {
      calendarContainer.style.display = "block";
      calendarContainer.style.visibility = "visible";
      calendarContainer.style.opacity = "1";
      console.log("Calendar container made visible");
    }

    if (calendarGrid) {
      calendarGrid.style.display = "flex";
      calendarGrid.style.visibility = "visible";
      calendarGrid.style.opacity = "1";
      calendarGrid.style.minHeight = "400px";
      console.log("Calendar grid made visible");
    }

    if (timeColumn) {
      timeColumn.style.display = "block";
      timeColumn.style.visibility = "visible";
      timeColumn.style.width = "100px";
      console.log("Time column made visible");
    }

    if (daysWrapper) {
      daysWrapper.style.display = "grid";
      daysWrapper.style.visibility = "visible";
      daysWrapper.style.gridTemplateColumns = "repeat(7, 1fr)";
      console.log("Days wrapper made visible");
    }
  } catch (error) {
    console.error("Error generating calendar structure:", error);
    console.error("Error stack:", error.stack);
    return;
  }

  // Render schedules in grid
  console.log("Rendering schedules in grid...");
  try {
    renderInstructorSchedulesGrid(schedules);
    console.log("Schedules rendered in grid successfully");
  } catch (error) {
    console.error("Error rendering schedules:", error);
    console.error("Error stack:", error.stack);
  }

  // Verify grid was created and force visibility
  const timeColumn = document.getElementById("instructorScheduleTimeColumn");
  const daysWrapper = document.getElementById("instructorScheduleDaysWrapper");
  console.log(
    "Grid verification - Time column children:",
    timeColumn ? timeColumn.children.length : "not found"
  );
  console.log(
    "Grid verification - Days wrapper children:",
    daysWrapper ? daysWrapper.children.length : "not found"
  );

  // Final visibility check
  if (timeColumn && daysWrapper) {
    const calendarGrid = document.querySelector(
      "#schedule-pane .calendar-grid"
    );
    if (calendarGrid) {
      const computedStyle = window.getComputedStyle(calendarGrid);
      console.log("Calendar grid computed styles:", {
        display: computedStyle.display,
        visibility: computedStyle.visibility,
        opacity: computedStyle.opacity,
        height: computedStyle.height,
        width: computedStyle.width,
      });
    }
  }

  console.log("Schedule grid rendering complete");
}

// Global function to handle schedule tab click (called from onclick in HTML)
function handleScheduleTabClick() {
  console.log(
    "handleScheduleTabClick called",
    __CURRENT_INSTRUCTOR_SCHEDULES__
  );
  // Small delay to ensure Bootstrap has switched the tab
  setTimeout(() => {
    const schedulePane = document.getElementById("schedule-pane");
    const informationPane = document.getElementById("information-pane");

    if (schedulePane) {
      schedulePane.classList.add("show", "active");
      schedulePane.style.display = "block";
    }
    if (informationPane) {
      informationPane.classList.remove("show", "active");
      informationPane.style.display = "none";
    }

    // Update subtitle to show schedule count
    updateModalSubtitle(
      `Total: ${__CURRENT_SCHEDULE_COUNT__} schedule${
        __CURRENT_SCHEDULE_COUNT__ !== 1 ? "s" : ""
      }`
    );

    if (
      schedulePane &&
      schedulePane.classList.contains("active") &&
      __CURRENT_INSTRUCTOR_SCHEDULES__ !== null
    ) {
      console.log("Rendering schedule grid");
      renderScheduleGrid(__CURRENT_INSTRUCTOR_SCHEDULES__);
    } else if (__CURRENT_INSTRUCTOR_SCHEDULES__ === null) {
      console.log("No schedule data available yet");
    } else {
      console.log("Schedule pane not active yet, retrying...");
      // Retry after a bit more delay
      setTimeout(() => {
        const schedulePane = document.getElementById("schedule-pane");
        if (
          schedulePane &&
          schedulePane.classList.contains("active") &&
          __CURRENT_INSTRUCTOR_SCHEDULES__ !== null
        ) {
          renderScheduleGrid(__CURRENT_INSTRUCTOR_SCHEDULES__);
        }
      }, 200);
    }
  }, 100);
}

// Function to update modal subtitle based on active tab
function updateModalSubtitle(text) {
  const countEl = document.getElementById("schedulesCount");
  if (countEl) {
    countEl.textContent = text;
  }
}

// Global function to handle information tab click
function handleInformationTabClick() {
  console.log("handleInformationTabClick called");
  setTimeout(() => {
    const schedulePane = document.getElementById("schedule-pane");
    const informationPane = document.getElementById("information-pane");

    if (schedulePane) {
      schedulePane.classList.remove("show", "active");
      schedulePane.style.display = "none";
    }
    if (informationPane) {
      informationPane.classList.add("show", "active");
      informationPane.style.display = "block";
      informationPane.style.visibility = "visible";
      informationPane.style.opacity = "1";
    }

    // Update subtitle to show role
    if (__CURRENT_INSTRUCTOR_ROLE__) {
      updateModalSubtitle(__CURRENT_INSTRUCTOR_ROLE__);
    }
  }, 100);
}

// Function to set up tab listeners when modal is shown
function setupScheduleTabListeners() {
  // Listen for Bootstrap tab events on the tab list
  const tabList = document.getElementById("instructorModalTabs");

  if (tabList) {
    // Listen for Bootstrap tab events on the tab list
    tabList.addEventListener("shown.bs.tab", function (event) {
      const targetId = event.target.getAttribute("data-bs-target");
      console.log("Tab switched to:", targetId);

      if (targetId === "#information-pane") {
        // Information tab was shown
        const schedulePane = document.getElementById("schedule-pane");
        const informationPane = document.getElementById("information-pane");

        if (schedulePane) {
          schedulePane.classList.remove("show", "active");
          schedulePane.style.display = "none";
        }
        if (informationPane) {
          informationPane.classList.add("show", "active");
          informationPane.style.display = "block";
          informationPane.style.visibility = "visible";
          informationPane.style.opacity = "1";
        }

        // Update subtitle to show role
        if (__CURRENT_INSTRUCTOR_ROLE__) {
          updateModalSubtitle(__CURRENT_INSTRUCTOR_ROLE__);
        }
      } else if (targetId === "#schedule-pane") {
        // Schedule tab was shown - render the schedule grid
        const schedulePane = document.getElementById("schedule-pane");
        const informationPane = document.getElementById("information-pane");

        if (schedulePane) {
          schedulePane.classList.add("show", "active");
          schedulePane.style.display = "block";
          schedulePane.style.visibility = "visible";
          schedulePane.style.opacity = "1";
        }
        if (informationPane) {
          informationPane.classList.remove("show", "active");
          informationPane.style.display = "none";
        }

        // Update subtitle to show schedule count
        updateModalSubtitle(
          `Total: ${__CURRENT_SCHEDULE_COUNT__} schedule${
            __CURRENT_SCHEDULE_COUNT__ !== 1 ? "s" : ""
          }`
        );

        console.log(
          "Schedule tab shown via Bootstrap event, rendering grid...",
          __CURRENT_INSTRUCTOR_SCHEDULES__
        );
        if (__CURRENT_INSTRUCTOR_SCHEDULES__ !== null) {
          renderScheduleGrid(__CURRENT_INSTRUCTOR_SCHEDULES__);
        }
      } else {
        // Other tab was shown - clear schedule grid to save resources
        document
          .querySelectorAll("#instructorScheduleDaysWrapper .schedule-block")
          .forEach((block) => block.remove());
      }
    });
  }
}

function populateInstructorInformation(instructor) {
  // Basic Information
  const infoFullNameEl = document.getElementById("infoFullName");
  const infoUsernameEl = document.getElementById("infoUsername");
  const infoEmailEl = document.getElementById("infoEmail");
  const infoDepartmentEl = document.getElementById("infoDepartment");

  if (infoFullNameEl) infoFullNameEl.textContent = instructor.full_name || "-";
  if (infoUsernameEl)
    infoUsernameEl.textContent = "@" + (instructor.acc_user || "-");
  if (infoEmailEl) infoEmailEl.textContent = instructor.acc_email || "-";
  if (infoDepartmentEl)
    infoDepartmentEl.textContent = instructor.dept_name || "-";

  // Employment Details
  const infoEmploymentStatusEl = document.getElementById(
    "infoEmploymentStatus"
  );
  const infoRankEl = document.getElementById("infoRank");
  const infoDesignationEl = document.getElementById("infoDesignation");
  const infoPhoneEl = document.getElementById("infoPhone");

  if (infoEmploymentStatusEl)
    infoEmploymentStatusEl.textContent = instructor.inst_status || "-";
  if (infoRankEl) infoRankEl.textContent = instructor.rank || "-";
  if (infoDesignationEl)
    infoDesignationEl.textContent =
      instructor.designation && instructor.designation !== "None"
        ? instructor.designation
        : "-";
  if (infoPhoneEl) infoPhoneEl.textContent = instructor.inst_phone || "-";

  // Workload Summary
  const infoAdministrationHoursEl = document.getElementById(
    "infoAdministrationHours"
  );
  const infoInstructionHoursEl = document.getElementById(
    "infoInstructionHours"
  );
  const infoResearchHoursEl = document.getElementById("infoResearchHours");
  const infoExtensionHoursEl = document.getElementById("infoExtensionHours");
  const infoProductionHoursEl = document.getElementById("infoProductionHours");
  const infoConsultationHoursEl = document.getElementById(
    "infoConsultationHours"
  );
  const infoTotalHoursEl = document.getElementById("infoTotalHours");

  if (infoAdministrationHoursEl) {
    infoAdministrationHoursEl.textContent =
      instructor.administration_hours !== null &&
      instructor.administration_hours !== undefined
        ? instructor.administration_hours
        : "-";
  }
  if (infoInstructionHoursEl) {
    infoInstructionHoursEl.textContent =
      instructor.instruction_hours !== null &&
      instructor.instruction_hours !== undefined
        ? instructor.instruction_hours
        : "-";
  }
  if (infoResearchHoursEl) {
    infoResearchHoursEl.textContent =
      instructor.research_hours !== null &&
      instructor.research_hours !== undefined
        ? instructor.research_hours
        : "-";
  }
  if (infoExtensionHoursEl) {
    infoExtensionHoursEl.textContent =
      instructor.extension_hours !== null &&
      instructor.extension_hours !== undefined
        ? instructor.extension_hours
        : "-";
  }
  if (infoProductionHoursEl) {
    infoProductionHoursEl.textContent =
      instructor.production_hours !== null &&
      instructor.production_hours !== undefined
        ? instructor.production_hours
        : "-";
  }
  if (infoConsultationHoursEl) {
    infoConsultationHoursEl.textContent =
      instructor.consultation_hours !== null &&
      instructor.consultation_hours !== undefined
        ? instructor.consultation_hours
        : "-";
  }
  if (infoTotalHoursEl) {
    // Calculate total hours as sum of all workload components
    const administrationHours = parseInt(instructor.administration_hours) || 0;
    const instructionHours = parseInt(instructor.instruction_hours) || 0;
    const researchHours = parseInt(instructor.research_hours) || 0;
    const extensionHours = parseInt(instructor.extension_hours) || 0;
    const productionHours = parseInt(instructor.production_hours) || 0;
    const consultationHours = parseInt(instructor.consultation_hours) || 0;

    const totalHours =
      administrationHours +
      instructionHours +
      researchHours +
      extensionHours +
      productionHours +
      consultationHours;
    infoTotalHoursEl.textContent = totalHours;
  }
}

function clearInstructorInformation() {
  const fields = [
    "infoFullName",
    "infoUsername",
    "infoEmail",
    "infoDepartment",
    "infoEmploymentStatus",
    "infoRank",
    "infoDesignation",
    "infoPhone",
    "infoAdministrationHours",
    "infoInstructionHours",
    "infoResearchHours",
    "infoExtensionHours",
    "infoProductionHours",
    "infoConsultationHours",
    "infoTotalHours",
  ];

  fields.forEach((fieldId) => {
    const el = document.getElementById(fieldId);
    if (el) el.textContent = "-";
  });
}

let instructorScheduleMinHour = 7; // Store minHour for use in render function

function generateInstructorScheduleGrid(schedules) {
  console.log("generateInstructorScheduleGrid called");
  const timeColumn = document.getElementById("instructorScheduleTimeColumn");
  const daysWrapper = document.getElementById("instructorScheduleDaysWrapper");

  if (!timeColumn) {
    console.error("instructorScheduleTimeColumn not found!");
    return;
  }
  if (!daysWrapper) {
    console.error("instructorScheduleDaysWrapper not found!");
    return;
  }

  console.log("Found time column and days wrapper, generating grid...");

  // Clear existing content
  timeColumn.innerHTML =
    '<div class="calendar-header" style="position: sticky; top: 0; z-index: 11;">Time</div>';
  daysWrapper.innerHTML = "";

  // Fixed time range: 7:00 AM to 8:30 PM (same as schedules page)
  const minHour = 7;
  const maxHour = 21; // 21 means up to 8:30 PM (hour 20 + :30)

  // Store minHour globally for use in render function
  instructorScheduleMinHour = minHour;

  // Generate time slots (30-minute intervals) - same as schedules page
  for (let hour = minHour; hour < maxHour; hour++) {
    const timeSlot = document.createElement("div");
    timeSlot.className = "time-slot";

    const hourLabel = document.createElement("span");
    hourLabel.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:00 ${
      hour < 12 ? "AM" : "PM"
    }`;
    timeSlot.appendChild(hourLabel);

    const halfHourLabel = document.createElement("div");
    halfHourLabel.className = "time-slot-half";
    halfHourLabel.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:30`;
    timeSlot.appendChild(halfHourLabel);

    timeColumn.appendChild(timeSlot);
  }

  console.log("Time slots generated:", timeColumn.children.length);

  // Generate day columns
  const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  const totalHours = maxHour - minHour; // 14 hours (7 AM to 8:30 PM)
  // Each hour has 2 time slots (00 and 30), each slot is 60px high
  // So total height = hours * 2 slots/hour * 60px/slot
  const totalHeight = totalHours * 2 * 60; // 120px per hour

  days.forEach((day) => {
    const dayColumn = document.createElement("div");
    dayColumn.className = "day-column";
    dayColumn.id = `instructor-day-col-${day}`;
    dayColumn.innerHTML = `
      <div class="calendar-header">${day}</div>
      <div class="day-column-content" id="instructor-day-content-${day}" style="height: ${totalHeight}px;"></div>
    `;
    daysWrapper.appendChild(dayColumn);
  });

  console.log("Day columns generated:", daysWrapper.children.length);
  console.log("Grid generation complete");
}

function renderInstructorSchedulesGrid(schedules) {
  // Clear existing blocks
  document
    .querySelectorAll("#instructorScheduleDaysWrapper .schedule-block")
    .forEach((block) => block.remove());

  // Color scheme for schedule types
  const typeColors = {
    Lec: "#800000", // Maroon
    Lab: "#FF6B35", // Orange
    Special: "#4B0082", // Indigo
  };

  schedules.forEach((schedule) => {
    const day = schedule.schedule?.day || "Mon";
    const startTime = schedule.schedule?.start_time || "08:00";
    const endTime = schedule.schedule?.end_time || "09:00";
    const scheduleType = schedule.schedule?.type || "Lec";

    // Parse time
    const [startHour, startMin] = startTime.split(":").map(Number);
    const [endHour, endMin] = endTime.split(":").map(Number);

    // Calculate position and height using the stored minHour
    const startMinutes = startHour * 60 + startMin;
    const endMinutes = endHour * 60 + endMin;
    const duration = endMinutes - startMinutes;
    const gridStartMinutes = instructorScheduleMinHour * 60;
    const topPosition = ((startMinutes - gridStartMinutes) / 60) * 60; // 60px per hour
    const blockHeight = Math.max(60, (duration / 60) * 60); // Minimum 60px, 60px per hour

    // Get day content container
    const dayContent = document.getElementById(`instructor-day-content-${day}`);
    if (!dayContent) return;

    // Create schedule block
    const block = document.createElement("div");
    block.className = "schedule-block";
    block.style.top = `${topPosition}px`;
    block.style.height = `${blockHeight}px`;
    block.style.background = typeColors[scheduleType] || typeColors["Lec"];
    block.style.borderLeftColor = typeColors[scheduleType] || typeColors["Lec"];

    // Format time display
    const timeDisplay = `${startTime} - ${endTime}`;

    // Format room display
    const roomDisplay = schedule.room?.name || "-";

    // Format subject code
    const subjCode = schedule.subject?.code || "-";
    const subjName = schedule.subject?.name || "-";

    // Check if overtime - use the is_overtime field from database
    const isOvertime =
      schedule.schedule?.is_overtime === "Yes" ||
      schedule.is_overtime === "Yes";
    const overtimeBadge = isOvertime
      ? '<span class="badge bg-danger mb-1" style="font-size: 0.65rem;">OVERTIME</span>'
      : "";

    block.innerHTML = `
      <div class="schedule-block-content">
        ${overtimeBadge}
        <div class="subj-code-simple" style="font-weight: 800; font-size: 0.85rem; margin-bottom: 4px;">${escapeHtml(
          subjCode
        )}</div>
        <div class="instructor-simple" style="font-size: 0.7rem; margin-bottom: 2px; opacity: 0.95;">${escapeHtml(
          subjName
        )}</div>
        <div class="room-simple" style="font-size: 0.65rem; margin-bottom: 2px; opacity: 0.9;">${escapeHtml(
          roomDisplay
        )}</div>
        <div class="time-simple" style="font-size: 0.65rem; margin-bottom: 2px; opacity: 0.9;">${escapeHtml(
          timeDisplay
        )}</div>
        <div class="type-simple" style="font-size: 0.7rem; font-weight: 600; margin-top: auto;">${escapeHtml(
          scheduleType
        )}</div>
      </div>
    `;

    block.title = `${subjCode} - ${subjName}\n${roomDisplay}\n${timeDisplay}\n${scheduleType}`;

    dayContent.appendChild(block);
  });
}

/**
 * Remove instructor from current admin's department (only removes department association, not the account)
 */
function removeInstructorFromDepartment(accId, instId, userName) {
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Remove from My Department",
      html: `Are you sure you want to remove <strong>${userName}</strong> from your department?<br><br>
             <span class="text-warning">This will only remove them from your department. Their account will remain active.</span>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#800000",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes, remove them",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        performRemoveFromDepartment(accId, instId, userName);
      }
    });
  } else {
    if (
      confirm(
        `Are you sure you want to remove ${userName} from your department? This will only remove the department association, not delete their account.`
      )
    ) {
      performRemoveFromDepartment(accId, instId, userName);
    }
  }
}

function performRemoveFromDepartment(accId, instId, userName) {
  console.log("Removing instructor from department:", accId, instId);

  // Prepare form data
  const formData = new FormData();
  formData.append("acc_id", accId);
  if (instId > 0) {
    formData.append("inst_id", instId);
  }

  // Send remove request
  fetch("../../admin/management/remove_instructor_from_department.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Refresh the accounts list
        if (typeof loadAccounts === "function") {
          loadAccounts();
        } else if (typeof applyAccountsFilters === "function") {
          applyAccountsFilters();
        }

        // Show success message
        if (typeof Swal !== "undefined") {
          Swal.fire({
            title: "Removed Successfully!",
            html: `<strong>${userName}</strong> has been removed from your department.<br><br>
                   <span class="text-muted small">Their account remains active. They can be added back using "Add Existing Instructor".</span>`,
            icon: "success",
            confirmButtonColor: "#800000",
            confirmButtonText: "OK",
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
          });
        } else {
          alert(`${userName} has been removed from your department.`);
        }
      } else {
        throw new Error(data.message || "Failed to remove instructor from department");
      }
    })
    .catch((error) => {
      console.error("Remove from department error:", error);
      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: "Removal Failed",
          text: error.message || "Failed to remove instructor from department. Please try again.",
          icon: "error",
          confirmButtonColor: "#dc3545",
          confirmButtonText: "OK",
        });
      } else {
        alert(error.message || "Failed to remove instructor from department. Please try again.");
      }
    });
}

function performDeleteUser(accId, userName) {
  console.log("Deleting user:", accId);

  // Show loading state
  const deleteBtn = event.target;
  const originalText = deleteBtn.innerHTML;
  deleteBtn.disabled = true;
  deleteBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

  // Prepare form data
  const formData = new FormData();
  formData.append("acc_id", accId);

  // Send delete request
  fetch("../../admin/management/delete_account.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Remove user from cache
        const userIndex = __ACCOUNTS_CACHE__.findIndex(
          (account) => account.acc_id == accId
        );
        if (userIndex >= 0) {
          __ACCOUNTS_CACHE__.splice(userIndex, 1);
        }

        // Refresh the table
        applyAccountsFilters();

        // Show success message
        if (typeof Swal !== "undefined") {
          Swal.fire({
            title: "Account Deleted!",
            html: `<strong>${userName}</strong> has been deleted successfully.`,
            icon: "success",
            confirmButtonColor: "#28a745",
            confirmButtonText: "OK",
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
            customClass: {
              popup: "swal2-popup-custom",
              title: "swal2-title-custom",
              content: "swal2-content-custom",
            },
          });
        } else {
          alert(`${userName} has been deleted successfully.`);
        }
      } else {
        throw new Error(data.message || "Failed to delete user");
      }
    })
    .catch((error) => {
      console.error("Delete user error:", error);
      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: "Deletion Failed",
          text: error.message || "Failed to delete account. Please try again.",
          icon: "error",
          confirmButtonColor: "#dc3545",
          confirmButtonText: "OK",
          customClass: {
            popup: "swal2-popup-custom",
            title: "swal2-title-custom",
            content: "swal2-content-custom",
          },
        });
      } else {
        alert("Error: " + error.message);
      }
    })
    .finally(() => {
      // Restore button state
      deleteBtn.disabled = false;
      deleteBtn.innerHTML = originalText;
    });
}

function showUsersLoadingState() {
  const loadingState = document.getElementById("usersLoadingState");
  const tableContainer = document.getElementById("usersTableContainer");
  const emptyState = document.getElementById("usersEmptyState");

  if (loadingState) loadingState.style.display = "block";
  if (tableContainer) tableContainer.style.display = "none";
  if (emptyState) emptyState.style.display = "none";
}

function hideUsersLoadingState() {
  const loadingState = document.getElementById("usersLoadingState");
  const tableContainer = document.getElementById("usersTableContainer");

  if (loadingState) loadingState.style.display = "none";
  if (tableContainer) tableContainer.style.display = "block";
}

function showUsersEmptyState() {
  const emptyState = document.getElementById("usersEmptyState");
  const tableContainer = document.getElementById("usersTableContainer");
  const loadingState = document.getElementById("usersLoadingState");

  if (emptyState) emptyState.style.display = "block";
  if (tableContainer) tableContainer.style.display = "none";
  if (loadingState) loadingState.style.display = "none";
}

function updateUsersStatistics() {
  const totalUsersEl = document.getElementById("totalUsersCount");
  const moderatorsEl = document.getElementById("moderatorsCount");
  const instructorsEl = document.getElementById("instructorsCount");
  const activeUsersEl = document.getElementById("activeUsersCount");
  const pendingUsersEl = document.getElementById("pendingUsersCount");
  const regularUsersEl = document.getElementById("regularUsersCount");
  const partTimeUsersEl = document.getElementById("partTimeUsersCount");

  if (totalUsersEl) totalUsersEl.textContent = __USERS_STATS__.total || 0;
  if (moderatorsEl) moderatorsEl.textContent = __USERS_STATS__.moderators || 0;
  if (instructorsEl)
    instructorsEl.textContent = __USERS_STATS__.instructors || 0;
  if (activeUsersEl) activeUsersEl.textContent = __USERS_STATS__.total || 0; // All users are active by default
  if (pendingUsersEl) pendingUsersEl.textContent = __USERS_STATS__.pending || 0;
  if (regularUsersEl) regularUsersEl.textContent = __USERS_STATS__.regular || 0;
  if (partTimeUsersEl)
    partTimeUsersEl.textContent = __USERS_STATS__.parttime || 0;
}

// Duplicate function removed - using the main attachAccountsFiltersIfNeeded above

function createUserCard(user) {
  const photo =
    user.photo_url ||
    `https://ui-avatars.com/api/?name=${encodeHtml(
      user.full_name || user.acc_user || "User"
    )}&background=800000&color=fff&size=80`;
  const roleBadge =
    Number(user.role_id) === 3
      ? '<span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Moderator</span>'
      : '<span class="badge bg-info"><i class="bi bi-person-badge me-1"></i>Instructor</span>';

  const statusBadge =
    user.acc_status === "Active"
      ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>'
      : user.acc_status === "Pending"
      ? '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>'
      : '<span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Inactive</span>';

  const employmentBadge = user.inst_status
    ? `<span class="badge bg-primary"><i class="bi bi-briefcase me-1"></i>${escapeHtml(
        user.inst_status
      )}</span>`
    : "";

  const rankBadge = user.rank
    ? `<span class="badge bg-light text-dark border"><i class="bi bi-mortarboard me-1"></i>${escapeHtml(
        user.rank
      )}</span>`
    : "";

  const designationBadge =
    user.designation && user.designation !== "None"
      ? `<span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i>${escapeHtml(
          user.designation
        )}</span>`
      : "";

  const lastLogin = user.last_login
    ? new Date(user.last_login).toLocaleDateString()
    : "Never";

  return `
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 user-card">
        <div class="card-body">
          <div class="d-flex align-items-start mb-3">
            <img src="${photo}" class="rounded-circle me-3" alt="Profile" width="60" height="60" style="object-fit: cover;">
            <div class="flex-grow-1">
              <h6 class="card-title mb-1 fw-bold">${escapeHtml(
                user.full_name || user.acc_user || ""
              )}</h6>
              <p class="text-muted small mb-2">@${escapeHtml(
                user.acc_user || ""
              )}</p>
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
                <li><a class="dropdown-item" href="#" data-edit-account="${
                  user.acc_id
                }">
                  <i class="bi bi-pencil me-2"></i>Edit
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="viewUserDetails(${
                  user.acc_id
                })">
                  <i class="bi bi-eye me-2"></i>View Details
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="deactivateUser(${
                  user.acc_id
                })">
                  <i class="bi bi-person-x me-2"></i>Deactivate
                </a></li>
              </ul>
            </div>
          </div>
          
          <div class="row g-2 text-center">
            <div class="col-6">
              <div class="border rounded p-2">
                <div class="small text-muted">Email</div>
                <div class="fw-semibold small">${escapeHtml(
                  user.acc_email || ""
                )}</div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded p-2">
                <div class="small text-muted">Last Login</div>
                <div class="fw-semibold small">${lastLogin}</div>
              </div>
            </div>
          </div>
          
          ${
            user.inst_email
              ? `
            <div class="mt-3 pt-3 border-top">
              <div class="row g-2 text-center">
                <div class="col-6">
                  <div class="small text-muted">Personal Email</div>
                  <div class="fw-semibold small">${escapeHtml(
                    user.inst_email
                  )}</div>
                </div>
                <div class="col-6">
                  <div class="small text-muted">Phone</div>
                  <div class="fw-semibold small">${escapeHtml(
                    user.inst_phone || "N/A"
                  )}</div>
                </div>
              </div>
            </div>
          `
              : ""
          }
          
          ${
            user.rank || (user.designation && user.designation !== "None")
              ? `
            <div class="mt-3 pt-3 border-top">
              <div class="row g-2 text-center">
                ${
                  user.rank
                    ? `
                  <div class="col-6">
                    <div class="small text-muted">Academic Rank</div>
                    <div class="fw-semibold small">${escapeHtml(
                      user.rank
                    )}</div>
                  </div>
                `
                    : ""
                }
                ${
                  user.designation && user.designation !== "None"
                    ? `
                  <div class="col-6">
                    <div class="small text-muted">Designation</div>
                    <div class="fw-semibold small">${escapeHtml(
                      user.designation
                    )}</div>
                  </div>
                `
                    : ""
                }
              </div>
            </div>
          `
              : ""
          }
        </div>
      </div>
    </div>
  `;
}

function viewUserDetails(userId) {
  const user = __ACCOUNTS_CACHE__.find(
    (u) => String(u.acc_id) === String(userId)
  );
  if (!user) return;

  // Create a detailed view modal or redirect to user details page
  alert(
    `View details for: ${user.full_name}\nEmail: ${user.acc_email}\nRole: ${
      user.role_id === 3 ? "Moderator" : "Instructor"
    }\nStatus: ${user.inst_status || "N/A"}`
  );
}

function deactivateUser(userId) {
  if (!confirm("Are you sure you want to deactivate this user?")) return;

  // Here you would typically make an AJAX call to deactivate the user
  alert("User deactivation functionality would be implemented here.");
}

function escapeHtml(str) {
  return String(str || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Edit Account Modal Step Navigation - Define functions globally first
window.editAccountCurrentStep = 1;
window.editAccountTotalSteps = 4;

// Global functions for onclick handlers (must be defined immediately, outside IIFE)
window.handleEditAccountNext = function (event) {
  // Prevent any default behavior
  if (event) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
  }

  console.log(
    "handleEditAccountNext called, current step:",
    window.editAccountCurrentStep
  );

  // Ensure we have the modal
  const modal = document.getElementById("editAccountModal");
  if (!modal) {
    console.error("Edit Account modal not found in handleEditAccountNext");
    return false;
  }

  // Validate current step
  let isValid = false;
  try {
    isValid = validateEditAccountStep();
    console.log("Validation result:", isValid);
  } catch (error) {
    console.error("Error during validation:", error);
    // Continue anyway if validation throws an error
    isValid = true;
  }

  if (isValid) {
    if (window.editAccountCurrentStep < window.editAccountTotalSteps) {
      window.editAccountCurrentStep++;
      console.log("Moving to step:", window.editAccountCurrentStep);

      // Force update the display
      setTimeout(function () {
        updateEditAccountStepDisplay();
        if (window.editAccountCurrentStep === 4) {
          updateEditAccountReviewInfo();
        }
      }, 10);

      return false; // Prevent form submission
    } else {
      console.log("Already at last step");
    }
  } else {
    console.log("Validation failed for step:", window.editAccountCurrentStep);
  }

  return false; // Always return false to prevent form submission
};

window.handleEditAccountPrev = function (event) {
  // Prevent any default behavior
  if (event) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation(); // Prevent other listeners from firing
  }

  console.log("=== handleEditAccountPrev called ===");
  console.log("Current step BEFORE decrement:", window.editAccountCurrentStep);

  if (window.editAccountCurrentStep > 1) {
    window.editAccountCurrentStep--;
    console.log("✅ Moving to step:", window.editAccountCurrentStep);
    updateEditAccountStepDisplay();
  } else {
    console.warn("⚠️ Cannot go to previous step - already at step 1");
  }

  return false; // Always return false to prevent form submission
};

// Helper functions - must be defined globally
window.updateEditAccountStepDisplay = function () {
  // Update progress indicator
  const modal = document.getElementById("editAccountModal");
  if (!modal) {
    console.error(
      "Edit Account modal not found in updateEditAccountStepDisplay"
    );
    return;
  }

  console.log("Updating step display for step:", window.editAccountCurrentStep);

  // Update progress steps indicator
  modal.querySelectorAll(".progress-steps .step").forEach((step, index) => {
    const stepNum = index + 1;
    step.classList.remove("active", "completed");
    if (stepNum < window.editAccountCurrentStep) {
      step.classList.add("completed");
    } else if (stepNum === window.editAccountCurrentStep) {
      step.classList.add("active");
    }
  });

  // Update form steps - hide all first, then show current
  modal.querySelectorAll(".form-step").forEach((step) => {
    step.classList.remove("active");
    const stepNum = parseInt(step.getAttribute("data-step"));
    if (stepNum === window.editAccountCurrentStep) {
      step.classList.add("active");
      console.log("Activated form step", stepNum);

      // If step 3 (Workload Hours) is activated, set the workload hours values
      if (stepNum === 3) {
        console.log("Step 3 activated, checking for workload hours...");
        console.log(
          "Stored workload hours:",
          window.__EDIT_ACCOUNT_WORKLOAD_HOURS__
        );

        if (window.__EDIT_ACCOUNT_WORKLOAD_HOURS__) {
          setTimeout(() => {
            const form = document.getElementById("editAccountForm");
            if (!form) {
              console.error("Edit account form not found");
              return;
            }

            const setWorkloadField = (name, id, value) => {
              const field = form.elements[name] || document.getElementById(id);
              if (field) {
                const oldValue = field.value;
                field.value = value;
                console.log(
                  `✅ Set ${name} (${id}) from "${oldValue}" to:`,
                  value
                );
                return true;
              } else {
                console.warn(`❌ Field ${name} (${id}) not found`);
                return false;
              }
            };

            const wh = window.__EDIT_ACCOUNT_WORKLOAD_HOURS__;
            console.log("Setting workload hours:", wh);

            setWorkloadField(
              "administration_hours",
              "edit_administration_hours",
              wh.administration_hours
            );
            setWorkloadField(
              "instruction_hours",
              "edit_instruction_hours",
              wh.instruction_hours
            );
            setWorkloadField(
              "research_hours",
              "edit_research_hours",
              wh.research_hours
            );
            setWorkloadField(
              "extension_hours",
              "edit_extension_hours",
              wh.extension_hours
            );
            setWorkloadField(
              "instructional_functions_hours",
              "edit_instructional_functions_hours",
              wh.instructional_functions_hours
            );
            setWorkloadField(
              "consultation_hours",
              "edit_consultation_hours",
              wh.consultation_hours
            );
          }, 150);
        } else {
          console.warn(
            "⚠️ No workload hours stored! Account cache may need to be refreshed."
          );
        }
      }
    }
  });

  // Update buttons
  const prevBtn = document.getElementById("edit_prevStepBtn");
  const nextBtn = document.getElementById("edit_nextStepBtn");
  const submitBtn = document.getElementById("edit_submitBtn");

  if (prevBtn)
    prevBtn.style.display =
      window.editAccountCurrentStep > 1 ? "block" : "none";
  if (nextBtn)
    nextBtn.style.display =
      window.editAccountCurrentStep < window.editAccountTotalSteps
        ? "block"
        : "none";
  if (submitBtn)
    submitBtn.style.display =
      window.editAccountCurrentStep === window.editAccountTotalSteps
        ? "block"
        : "none";

  console.log(
    "Step display updated. Current step:",
    window.editAccountCurrentStep
  );
};

window.validateEditAccountStep = function () {
  const modal = document.getElementById("editAccountModal");
  if (!modal) {
    console.error("Edit Account modal not found");
    return false;
  }

  const currentStepElement = modal.querySelector(
    `.form-step[data-step="${window.editAccountCurrentStep}"]`
  );
  if (!currentStepElement) {
    console.error(
      "Current step element not found for step:",
      window.editAccountCurrentStep
    );
    return false;
  }

  const requiredInputs = currentStepElement.querySelectorAll(
    "input[required], select[required]"
  );
  console.log(
    "Validating step",
    window.editAccountCurrentStep,
    "- found",
    requiredInputs.length,
    "required inputs"
  );
  let isValid = true;

  requiredInputs.forEach((input) => {
    // Skip rank validation if employment status is Part-Time
    if (
      (input.id === "edit_rank" || input.id === "add_rank") &&
      input.required
    ) {
      const instStatusEl = document.getElementById(
        input.id === "edit_rank" ? "edit_inst_status" : "add_inst_status"
      );
      const instStatus = instStatusEl ? instStatusEl.value : "";
      if (instStatus === "Part-Time") {
        // Rank is optional for Part-Time, skip validation
        console.log("Skipping rank validation for Part-Time instructor");
        input.classList.remove("is-invalid", "is-valid");
        return;
      }
    }

    const value = input.value ? input.value.trim() : "";
    if (!value) {
      isValid = false;
      input.classList.add("is-invalid");
      console.log("Invalid field:", input.id || input.name, "is empty");
    } else {
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");
    }
  });

  if (!isValid) {
    console.log("Validation failed for step", window.editAccountCurrentStep);
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "Validation Error",
        text: "Please fill in all required fields before proceeding.",
        confirmButtonColor: "#800000",
      });
    } else {
      alert("Please fill in all required fields before proceeding.");
    }
  } else {
    console.log("Validation passed for step", window.editAccountCurrentStep);
  }

  return isValid;
};

window.updateEditAccountReviewInfo = function () {
  // Scope all selectors to the Edit Account modal to avoid ID collisions
  const editAccountModal = document.getElementById("editAccountModal");
  const q = (selector) => editAccountModal?.querySelector(selector);

  const fname = q("#edit_fname")?.value.trim() || "";
  const lname = q("#edit_lname")?.value.trim() || "";
  const minitial = q("#edit_minitial")?.value.trim() || "";
  const suffix = q("#edit_suffix")?.value.trim() || "";
  const email = q("#edit_acc_email")?.value.trim() || "";
  const username = q("#edit_acc_user")?.value.trim() || "";
  const phone = q("#edit_inst_phone")?.value.trim() || "";
  const status = q("#edit_inst_status")?.value || "";
  const programId = q("#edit_program_id")?.value || "";
  const rank = q("#edit_rank")?.value || "";
  const designation = q("#edit_designation")?.value || "";
  const adminHours = q("#edit_administration_hours")?.value || "0";
  const instructionHours = q("#edit_instruction_hours")?.value || "0";
  const researchHours = q("#edit_research_hours")?.value || "0";
  const extensionHours = q("#edit_extension_hours")?.value || "0";
  const instFuncHours =
    q("#edit_instructional_functions_hours")?.value || "0";
  const consultationHours = q("#edit_consultation_hours")?.value || "0";

  const fullName = `${fname} ${minitial ? minitial + "." : ""} ${lname}${
    suffix ? " " + suffix : ""
  }`.trim();

  // Get program name
  const programSelect = q("#edit_program_id");
  const programName =
    programSelect && programSelect.options[programSelect.selectedIndex]
      ? programSelect.options[programSelect.selectedIndex].text
      : "-";

  // Get rank name
  const rankSelect = document.getElementById("edit_rank");
  const rankName =
    rankSelect && rankSelect.options[rankSelect.selectedIndex]
      ? rankSelect.options[rankSelect.selectedIndex].text
      : "-";

  // Get designation name
  const designationSelect = document.getElementById("edit_designation");
  const designationName =
    designationSelect &&
    designationSelect.options[designationSelect.selectedIndex]
      ? designationSelect.options[designationSelect.selectedIndex].text
      : "-";

  // Update review fields
  const reviewName = document.getElementById("edit_reviewName");
  const reviewEmail = document.getElementById("edit_reviewEmail");
  const reviewPhone = document.getElementById("edit_reviewPhone");
  const reviewUsername = document.getElementById("edit_reviewUsername");
  const reviewStatus = document.getElementById("edit_reviewStatus");
  const reviewProgram = document.getElementById("edit_reviewProgram");
  const reviewRank = document.getElementById("edit_reviewRank");
  const reviewDesignation = document.getElementById("edit_reviewDesignation");
  const reviewAdminHours = document.getElementById("edit_reviewAdminHours");
  const reviewInstructionHours = document.getElementById(
    "edit_reviewInstructionHours"
  );
  const reviewResearchHours = document.getElementById(
    "edit_reviewResearchHours"
  );
  const reviewExtensionHours = document.getElementById(
    "edit_reviewExtensionHours"
  );
  const reviewInstFuncHours = document.getElementById(
    "edit_reviewInstFuncHours"
  );
  const reviewConsultationHours = document.getElementById(
    "edit_reviewConsultationHours"
  );

  if (reviewName) reviewName.textContent = fullName || "-";
  if (reviewEmail) reviewEmail.textContent = email || "-";
  if (reviewPhone) reviewPhone.textContent = phone || "-";
  if (reviewUsername) reviewUsername.textContent = username || "-";
  if (reviewStatus) reviewStatus.textContent = status || "-";
  if (reviewProgram) reviewProgram.textContent = programName;
  if (reviewRank) reviewRank.textContent = rankName;
  if (reviewDesignation) reviewDesignation.textContent = designationName;
  if (reviewAdminHours) reviewAdminHours.textContent = adminHours || "0";
  if (reviewInstructionHours)
    reviewInstructionHours.textContent = instructionHours || "0";
  if (reviewResearchHours)
    reviewResearchHours.textContent = researchHours || "0";
  if (reviewExtensionHours)
    reviewExtensionHours.textContent = extensionHours || "0";
  if (reviewInstFuncHours)
    reviewInstFuncHours.textContent = instFuncHours || "0";
  if (reviewConsultationHours)
    reviewConsultationHours.textContent = consultationHours || "0";
};

// Initialize Edit Account Modal functionality
(function () {
  "use strict";

  // Also keep event delegation as backup
  document.addEventListener("click", function (e) {
    // Check if click is on Next button or its children
    const nextBtn = e.target.closest("#edit_nextStepBtn");
    if (nextBtn) {
      e.preventDefault();
      e.stopPropagation();
      window.handleEditAccountNext();
      return false;
    }

    // Check if click is on Previous button or its children
    const prevBtn = e.target.closest("#edit_prevStepBtn");
    if (prevBtn) {
      e.preventDefault();
      e.stopPropagation();
      window.handleEditAccountPrev();
      return false;
    }
  });

  // Initialize when Edit Account modal is shown
  document.addEventListener("DOMContentLoaded", function () {
    const editAccountModal = document.getElementById("editAccountModal");
    if (editAccountModal) {
      // Prevent form submission on Enter key or accidental submit
      const editAccountForm = document.getElementById("editAccountForm");
      if (editAccountForm) {
        editAccountForm.addEventListener("submit", function (e) {
          e.preventDefault();
          e.stopPropagation();
          console.log("Form submit prevented");
          return false;
        });
      }
      // Add direct event listeners to buttons as fallback
      const nextBtn = document.getElementById("edit_nextStepBtn");
      const prevBtn = document.getElementById("edit_prevStepBtn");

      if (nextBtn) {
        nextBtn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          console.log(
            "Edit Account Next button clicked (direct listener), current step:",
            window.editAccountCurrentStep
          );
          if (validateEditAccountStep()) {
            if (window.editAccountCurrentStep < window.editAccountTotalSteps) {
              window.editAccountCurrentStep++;
              console.log("Moving to step:", window.editAccountCurrentStep);
              updateEditAccountStepDisplay();
              if (window.editAccountCurrentStep === 4) {
                updateEditAccountReviewInfo();
              }
            }
          } else {
            console.log(
              "Validation failed for step:",
              window.editAccountCurrentStep
            );
          }
        });
      }

      // Previous button uses onclick handler in HTML (handleEditAccountPrev), no duplicate listener needed

      // Track if modal was just opened (fresh open) vs already open
      let isModalFreshOpen = false;
      let isModalCurrentlyOpen = false;

      editAccountModal.addEventListener("show.bs.modal", function () {
        // Only reset to step 1 on a fresh open (when modal was hidden before)
        // Don't reset if modal is already open (user is navigating between steps)
        if (
          !isModalCurrentlyOpen &&
          (typeof window.editAccountCurrentStep === "undefined" ||
            isModalFreshOpen)
        ) {
          window.editAccountCurrentStep = 1;
          updateEditAccountStepDisplay();
        }
        isModalFreshOpen = false; // Reset flag after handling
        isModalCurrentlyOpen = true; // Mark modal as open

        // Re-attach event listeners when modal is shown (in case buttons were recreated)
        const nextBtn = document.getElementById("edit_nextStepBtn");
        const prevBtn = document.getElementById("edit_prevStepBtn");

        if (nextBtn && !nextBtn.hasAttribute("data-listener-attached")) {
          nextBtn.setAttribute("data-listener-attached", "true");
          nextBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log(
              "Edit Account Next button clicked, current step:",
              window.editAccountCurrentStep
            );
            if (validateEditAccountStep()) {
              if (
                window.editAccountCurrentStep < window.editAccountTotalSteps
              ) {
                window.editAccountCurrentStep++;
                console.log("Moving to step:", window.editAccountCurrentStep);
                updateEditAccountStepDisplay();
                if (window.editAccountCurrentStep === 4) {
                  updateEditAccountReviewInfo();
                }
              }
            } else {
              console.log(
                "Validation failed for step:",
                window.editAccountCurrentStep
              );
            }
          });
        }

        // Don't add duplicate listener - onclick handler in HTML already handles this
        // The onclick="return handleEditAccountPrev(event);" in HTML is sufficient
      });

      editAccountModal.addEventListener("hidden.bs.modal", function () {
        // Mark that modal was closed, so next open will be fresh
        isModalFreshOpen = true;
        isModalCurrentlyOpen = false; // Mark modal as closed
        window.editAccountCurrentStep = 1;
        updateEditAccountStepDisplay();
        // Remove validation classes
        const modal = document.getElementById("editAccountModal");
        if (modal) {
          modal.querySelectorAll(".is-invalid, .is-valid").forEach((el) => {
            el.classList.remove("is-invalid", "is-valid");
          });
        }
      });
    }
  });
})();

function openEditAccountModal(accId) {
  const acc = __ACCOUNTS_CACHE__.find(
    (a) => String(a.acc_id) === String(accId)
  );
  if (!acc) {
    console.error("Account not found in cache for accId:", accId);
    return;
  }
  console.log("Opening edit modal for account:", acc);
  console.log("Account workload hours in cache:", {
    administration_hours: acc.administration_hours,
    instruction_hours: acc.instruction_hours,
    research_hours: acc.research_hours,
    extension_hours: acc.extension_hours,
    instructional_functions_hours: acc.instructional_functions_hours,
    consultation_hours: acc.consultation_hours,
  });
  const form = document.getElementById("editAccountForm");
  if (!form) {
    console.error("Edit account form not found");
    return;
  }
  form.reset();

  // Reset step to 1
  if (typeof updateEditAccountStepDisplay === "function") {
    const editAccountModal = document.getElementById("editAccountModal");
    if (editAccountModal) {
      // Set step to 1 before showing
      window.editAccountCurrentStep = 1;
    }
  }

  form.elements["acc_id"].value = acc.acc_id;
  form.elements["fname"].value = acc.first_name || "";
  form.elements["lname"].value = acc.last_name || "";
  form.elements["minitial"].value = acc.middle_initial || "";
  form.elements["suffix"].value = acc.suffix || "";
  form.elements["acc_user"].value = acc.acc_user || "";
  form.elements["acc_email"].value = acc.acc_email || "";
  form.elements["inst_status"].value = acc.inst_status || "";
  form.elements["inst_email"].value = acc.inst_email || "";
  form.elements["inst_phone"].value = acc.inst_phone || "";

  // Store rank and designation values to set after dropdowns are populated
  const rankValue = acc.rank || "";
  const designationValue = acc.designation || "None";

  // Store workload hours values globally to set when step 3 becomes active
  // Check if workload hours exist in account data, otherwise they might be 0 or undefined
  window.__EDIT_ACCOUNT_WORKLOAD_HOURS__ = {
    administration_hours:
      acc.administration_hours !== null &&
      acc.administration_hours !== undefined
        ? parseInt(acc.administration_hours)
        : 0,
    instruction_hours:
      acc.instruction_hours !== null && acc.instruction_hours !== undefined
        ? parseInt(acc.instruction_hours)
        : 0,
    research_hours:
      acc.research_hours !== null && acc.research_hours !== undefined
        ? parseInt(acc.research_hours)
        : 0,
    extension_hours:
      acc.extension_hours !== null && acc.extension_hours !== undefined
        ? parseInt(acc.extension_hours)
        : 0,
    instructional_functions_hours:
      acc.instructional_functions_hours !== null &&
      acc.instructional_functions_hours !== undefined
        ? parseInt(acc.instructional_functions_hours)
        : 0,
    consultation_hours:
      acc.consultation_hours !== null && acc.consultation_hours !== undefined
        ? parseInt(acc.consultation_hours)
        : 0,
  };

  console.log(
    "Stored workload hours for account:",
    acc.acc_id,
    window.__EDIT_ACCOUNT_WORKLOAD_HOURS__
  );
  console.log("Account data workload fields:", {
    administration_hours: acc.administration_hours,
    instruction_hours: acc.instruction_hours,
    research_hours: acc.research_hours,
    extension_hours: acc.extension_hours,
    instructional_functions_hours: acc.instructional_functions_hours,
    consultation_hours: acc.consultation_hours,
  });

  const modal = new bootstrap.Modal(
    document.getElementById("editAccountModal")
  );
  modal.show();

  // Update step display after modal is shown
  setTimeout(() => {
    if (typeof updateEditAccountStepDisplay === "function") {
      updateEditAccountStepDisplay();
    }

    if (rankValue) {
      const rankSelect = document.getElementById("edit_rank");
      if (rankSelect) {
        rankSelect.value = rankValue;
      }
    }
    if (designationValue) {
      const designationSelect = document.getElementById("edit_designation");
      if (designationSelect) {
        designationSelect.value = designationValue;
      }
    }
  }, 300); // Small delay to ensure dropdowns are populated
}

function saveAccountEdits(event) {
  // Get the button from the event, or fallback to finding by ID
  let btn = null;
  if (event && event.target) {
    btn = event.target;
  } else if (event && event.currentTarget) {
    btn = event.currentTarget;
  } else {
    // Fallback: try to find by ID
    btn = document.getElementById("edit_submitBtn");
    if (!btn) {
      btn = document.getElementById("saveAccountEditsBtn");
    }
  }

  // If still no button found, try to find it from the modal
  if (!btn) {
    const modal = document.getElementById("editAccountModal");
    if (modal) {
      btn =
        modal.querySelector("#edit_submitBtn") ||
        modal.querySelector("#saveAccountEditsBtn");
    }
  }

  if (!btn) {
    console.error("Save button not found! Event:", event);
    console.error(
      "Available buttons in modal:",
      document.querySelectorAll("#editAccountModal button")
    );
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Save button not found. Please refresh the page (Ctrl+F5) and try again.",
        confirmButtonColor: "#dc3545",
      });
    } else {
      alert(
        "Save button not found. Please refresh the page (Ctrl+F5) and try again."
      );
    }
    return false;
  }

  const form = document.getElementById("editAccountForm");
  if (!form) {
    console.error("Edit account form not found!");
    return false;
  }

  // Get the employment status element and value
  const instStatusEl = document.getElementById("edit_inst_status");
  const instStatusValue = instStatusEl ? instStatusEl.value.trim() : "";

  // Validate required fields before submission
  if (!instStatusValue) {
    console.error("Employment Status is required but empty!");
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Validation Error",
        text: "Employment Status is required. Please select a status.",
        confirmButtonColor: "#800000",
      });
    }
    // Focus on the dropdown to help user
    if (instStatusEl) {
      instStatusEl.focus();
    }
    return false;
  }

  // Create form data
  const formData = new FormData(form);

  // Ensure inst_status is included in form data (in case it's missing)
  if (!formData.has("inst_status") || !formData.get("inst_status")) {
    formData.set("inst_status", instStatusValue);
    console.log("Added inst_status to FormData:", instStatusValue);
  }

  // Debug: Log the inst_status value being sent
  const formInstStatus = formData.get("inst_status");
  console.log(
    "Form submission - inst_status value from FormData:",
    formInstStatus
  );
  console.log("Form submission - inst_status element value:", instStatusValue);
  console.log("Form submission - All form data:", Object.fromEntries(formData));

  if (!formInstStatus) {
    console.error(
      "WARNING: inst_status is empty in FormData! This should not happen if the form field has a value."
    );
    return false;
  }

  // Store original button state
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving';

  // Use relative path (same pattern as other functions in this file)
  const apiPath = "../../admin/management/update_account.php";

  console.log("Updating account via:", apiPath, "from:", window.location.href);
  fetch(apiPath, { method: "POST", body: formData })
    .then((r) => {
      console.log("Update response status:", r.status, r.statusText);
      console.log("Response URL:", r.url);

      // Check if response is actually JSON
      const contentType = r.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        return r.text().then((text) => {
          console.error("Non-JSON response received:", text.substring(0, 500));
          throw new Error(
            "Server returned non-JSON response. Check browser console for details."
          );
        });
      }

      return r.json();
    })
    .then((data) => {
      console.log("Update response data:", data);
      if (!data.success) {
        console.error("Update failed:", data.message);
        throw new Error(data.message || "Failed to update account");
      }

      console.log("Update successful, reloading accounts...");
      // Reload accounts from server to get updated data including workload policy
      // This ensures Rank, Designation, Status, and workload hours are all up-to-date
      return loadAccounts();
    })
    .then(() => {
      console.log("Accounts reloaded, refreshing table...");
      // After reloading, apply filters to refresh the table display
      applyAccountsFilters();
      safeHideModal("editAccountModal");

      // Show success message
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "success",
          title: "Success!",
          text: "Account updated successfully",
          timer: 2000,
          showConfirmButton: false,
        });
      }
    })
    .catch((err) => {
      console.error("Error updating account:", err);
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "error",
          title: "Update Failed",
          text: err.message || "Failed to update account. Please try again.",
          confirmButtonColor: "#dc3545",
          confirmButtonText: "OK",
          customClass: {
            popup: "swal2-popup-custom",
            title: "swal2-title-custom",
            content: "swal2-content-custom",
          },
        });
      } else {
        alert(err.message || "Failed to update account");
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

// Profile Picture Management
function uploadProfilePicture(input) {
  console.log("uploadProfilePicture called with input:", input);
  console.log("input.files:", input.files);
  console.log(
    "input.files.length:",
    input.files ? input.files.length : "no files property"
  );

  if (input.files && input.files[0]) {
    const file = input.files[0];
    console.log("Selected file:", file);
    console.log("File type:", file.type);
    console.log("File size:", file.size);
    console.log("File name:", file.name);

    // Validate file type
    if (!file.type.startsWith("image/")) {
      Swal.fire({
        icon: "error",
        title: "Invalid File Type",
        text: "Please select an image file.",
        confirmButtonColor: "#800000",
        confirmButtonText: "OK",
      });
      input.value = "";
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      Swal.fire({
        icon: "error",
        title: "File Too Large",
        text: "File size must be less than 5MB.",
        confirmButtonColor: "#800000",
        confirmButtonText: "OK",
      });
      input.value = "";
      return;
    }

    const formData = new FormData();
    formData.append("profile_picture", file);

    console.log("FormData contents:");
    for (let [key, value] of formData.entries()) {
      console.log(key, value);
    }

    // Show loading with SweetAlert2
    Swal.fire({
      title: "Uploading Picture...",
      text: "Please wait while we upload your profile picture.",
      icon: "info",
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    console.log(
      "Sending request to: ../../shared/profile/upload_profile_picture.php"
    );
    fetch("../../shared/profile/upload_profile_picture.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        console.log("Response status:", response.status);
        console.log("Response headers:", response.headers);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Response data:", data);
        Swal.close();
        if (data.success) {
          // Update profile picture (add ../../public/ prefix for admin dashboard)
          const newImagePath =
            "../../public/" + data.image_path + "?t=" + new Date().getTime();
          const profilePictureEl = document.getElementById("profilePicture");
          if (profilePictureEl) {
            profilePictureEl.src = newImagePath;
          }

          // Update dropdown profile picture
          const dropdownProfileImg = document.querySelector(
            '#userMenu img[alt="Profile"]'
          );
          if (dropdownProfileImg) {
            dropdownProfileImg.src = newImagePath;
          }

          // Update header profile picture (if exists)
          const headerProfileImg = document.querySelector(
            ".user-profile .profile-pic img"
          );
          if (headerProfileImg) {
            headerProfileImg.src = newImagePath;
          }

          // Update sidebar profile pictures
          if (typeof updateSidebarProfilePicture === "function") {
            updateSidebarProfilePicture(data.image_path);
          }
          if (typeof updateAdminSidebarProfilePicture === "function") {
            updateAdminSidebarProfilePicture(data.image_path);
          }
          Swal.fire({
            icon: "success",
            title: "Success!",
            text: data.message || "Profile picture uploaded successfully!",
            confirmButtonColor: "#800000",
            confirmButtonText: "OK",
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Error!",
            text: data.message || "Failed to upload image.",
            confirmButtonColor: "#800000",
            confirmButtonText: "OK",
          });
        }
      })
      .catch((error) => {
        Swal.close();
        console.error("Error:", error);
        Swal.fire({
          icon: "error",
          title: "Upload Failed!",
          text: "An error occurred while uploading your profile picture. Please try again.",
          confirmButtonColor: "#800000",
          confirmButtonText: "OK",
        });
      });
  }
}

function removeProfilePicture() {
  Swal.fire({
    title: "Remove Profile Picture?",
    text: "Are you sure you want to remove your profile picture?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#800000",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Yes, remove it!",
    cancelButtonText: "Cancel",
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading
      Swal.fire({
        title: "Removing Picture...",
        text: "Please wait while we remove your profile picture.",
        icon: "info",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      fetch("../../shared/profile/remove_profile_picture.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({}),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then((data) => {
          Swal.close();
          if (data.success) {
            // Update to default image
            const defaultImage =
              "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+";

            // Update profile picture in profile management
            const profilePictureEl = document.getElementById("profilePicture");
            if (profilePictureEl) {
              profilePictureEl.src = defaultImage;
            }

            // Update dropdown profile picture
            const dropdownProfileImg = document.querySelector(
              '#userMenu img[alt="Profile"]'
            );
            if (dropdownProfileImg) {
              dropdownProfileImg.src = defaultImage;
            }

            // Update header profile picture (if exists)
            const headerProfileImg = document.querySelector(
              ".user-profile .profile-pic img"
            );
            if (headerProfileImg) {
              headerProfileImg.src = defaultImage;
            }

            // Update sidebar profile pictures
            if (typeof updateSidebarProfilePictureToDefault === "function") {
              updateSidebarProfilePictureToDefault();
            }
            if (
              typeof updateAdminSidebarProfilePictureToDefault === "function"
            ) {
              updateAdminSidebarProfilePictureToDefault();
            }
            Swal.fire({
              icon: "success",
              title: "Success!",
              text: data.message || "Profile picture removed successfully!",
              confirmButtonColor: "#800000",
              confirmButtonText: "OK",
              timer: 3000,
              timerProgressBar: true,
              showConfirmButton: true,
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Error!",
              text: data.message || "Failed to remove profile picture.",
              confirmButtonColor: "#800000",
              confirmButtonText: "OK",
            });
          }
        })
        .catch((error) => {
          Swal.close();
          console.error("Error:", error);
          Swal.fire({
            icon: "error",
            title: "Error!",
            text: "An error occurred while removing your profile picture. Please try again.",
            confirmButtonColor: "#800000",
            confirmButtonText: "OK",
          });
        });
    }
  });
}

// Curriculum Management Functions
// Prevent redeclaration error
if (typeof __CURRICULUM_CACHE__ === 'undefined') {
    var __CURRICULUM_CACHE__ = [];
}

function refreshCurriculumData() {
  console.log("refreshCurriculumData() called");
  const refreshBtn = document.querySelector(
    'button[onclick="refreshCurriculumData()"]'
  );
  if (!refreshBtn) {
    // If no refresh button, just load the data (e.g., on first tab switch)
    console.log("No refresh button found, loading curriculum data directly...");
    loadCurriculumData();
    return;
  }

  const originalHtml = refreshBtn.innerHTML;
  refreshBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Refreshing...';
  refreshBtn.disabled = true;

  loadCurriculumData();

  // Restore button after a delay to ensure data has time to load
  setTimeout(() => {
    refreshBtn.innerHTML = originalHtml;
    refreshBtn.disabled = false;
  }, 1500);
}

// Ensure this function is in global scope and not overridden
window.loadCurriculumData = function loadCurriculumData() {
  console.log("🔄 loadCurriculumData() called");
  
  // Check if curriculum elements exist in DOM - wait for them if not ready
  const totalCurriculumsEl = document.getElementById("totalCurriculums");
  const curriculumTableEl = document.getElementById("curriculaTable");
  const curriculumTableContainer = document.getElementById("curriculumTableContainer");
  const curriculumLoading = document.getElementById("curriculumLoading");
  const curriculumEmpty = document.getElementById("curriculumEmpty");
  
  console.log("🔍 Checking for curriculum elements:", {
    totalCurriculumsEl: !!totalCurriculumsEl,
    curriculumTableEl: !!curriculumTableEl,
    curriculumTableContainer: !!curriculumTableContainer,
    curriculumLoading: !!curriculumLoading,
    curriculumEmpty: !!curriculumEmpty
  });
  
  // Check if we're in the curriculum/subjects tab (which has the curriculum management section)
  const curriculumTab = document.getElementById("curriculum");
  
  // More lenient visibility check - check multiple ways
  let isCurriculumTabVisible = false;
  if (curriculumTab) {
    const computedStyle = window.getComputedStyle(curriculumTab);
    isCurriculumTabVisible = (
      curriculumTab.style.display !== "none" && 
      curriculumTab.style.display !== "" &&
      computedStyle.display !== "none" &&
      curriculumTab.offsetParent !== null
    );
  }
  
  // Also check if curriculum elements exist (they might be in the DOM even if tab check fails)
  const hasCurriculumElements = curriculumTableEl || curriculumTableContainer || totalCurriculumsEl;
  
  console.log("🔍 Curriculum tab visibility check:", {
    curriculumTab: !!curriculumTab,
    display: curriculumTab ? curriculumTab.style.display : "N/A",
    computedDisplay: curriculumTab ? window.getComputedStyle(curriculumTab).display : "N/A",
    offsetParent: curriculumTab ? curriculumTab.offsetParent !== null : "N/A",
    isCurriculumTabVisible: isCurriculumTabVisible,
    hasCurriculumElements: hasCurriculumElements
  });
  
  // Proceed if tab is visible OR if curriculum elements exist
  if (!isCurriculumTabVisible && !hasCurriculumElements) {
    console.log("ℹ️ Curriculum tab not visible and no elements found, skipping curriculum data load");
    return;
  }
  
  // Always proceed to load data if we got here
  console.log("✅ Proceeding to load curriculum data...");
  
  // Load the data immediately
  loadCurriculumDataInternal();
}; // End of window.loadCurriculumData - ensures it's in global scope and can't be overridden

// Internal function that actually fetches and renders the data
function loadCurriculumDataInternal() {
  console.log("✅ Loading curriculum data (internal function)...");
  
  const curriculumLoading = document.getElementById("curriculumLoading");
  if (curriculumLoading) {
    showCurriculumLoading(true);
  }

  // Ensure we pass the department ID to the backend
  const deptId = window.currentDeptId || 0;
  console.log("Department ID for curriculum:", deptId);

  // If no department ID, try to get it from the user session or allow Admin Support to see all
  const apiUrl =
    deptId > 0
      ? `../../admin/management/get_curricula.php?dept_id=${deptId}`
      : `../../admin/management/get_curricula.php`; // Admin Support can see all

  console.log("Fetching curriculum from:", apiUrl);

  fetch(apiUrl)
    .then((response) => {
      if (response.status === 403) {
        // Check if Curriculum tab is visible - if not, silently handle
        const curriculumTabLink = document.querySelector(
          'a[onclick*="curriculum_management"], a[href="#curriculum_management"]'
        );
        if (curriculumTabLink && curriculumTabLink.offsetParent !== null) {
          showPermissionError("Curriculum");
        } else {
          console.log(
            "Curriculum tab not visible - silently handling permission error"
          );
        }
        throw new Error("Unauthorized");
      }
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Curriculum API Response:", data);
      if (data.success && data.data) {
        __CURRICULUM_CACHE__ = data.data.curricula || [];
        console.log(
          "Curriculum data loaded:",
          __CURRICULUM_CACHE__.length,
          "items"
        );
        if (__CURRICULUM_CACHE__.length > 0) {
          console.log("Sample curriculum (full object):", __CURRICULUM_CACHE__[0]);
          console.log("Sample curriculum program data:", {
            program_id: __CURRICULUM_CACHE__[0].program_id,
            program_code: __CURRICULUM_CACHE__[0].program_code,
            program_name: __CURRICULUM_CACHE__[0].program_name,
            type_of_program_id: typeof __CURRICULUM_CACHE__[0].program_id
          });
          
          // Check all curricula for program data
          const curriculaWithPrograms = __CURRICULUM_CACHE__.filter(c => 
            c.program_id && (c.program_name || c.program_code)
          );
          console.log(`Found ${curriculaWithPrograms.length} curricula with program data:`, curriculaWithPrograms);
        }

        // Always update stats and render table, even if empty
        console.log("📊 Updating curriculum stats with", __CURRICULUM_CACHE__.length, "curricula");
        updateCurriculumStats(__CURRICULUM_CACHE__);
        renderCurriculumTable(__CURRICULUM_CACHE__);
        
        if (__CURRICULUM_CACHE__.length === 0) {
          console.warn("⚠️ No curricula found in response, but stats updated with empty array");
        }
      } else {
        console.error("❌ Curriculum API returned error:", data);
        if (data.message && data.message.includes("Unauthorized")) {
          // Check if Curriculum tab is visible - if not, silently handle
          const curriculumTabLink = document.querySelector(
            'a[onclick*="curriculum_management"], a[href="#curriculum_management"]'
          );
          if (curriculumTabLink && curriculumTabLink.offsetParent !== null) {
            showPermissionError("Curriculum");
          } else {
            console.log(
              "Curriculum tab not visible - silently handling permission error"
            );
          }
        } else {
          throw new Error(data.message || "Failed to load curriculum data.");
        }
      }
    })
    .catch((error) => {
      console.error("Error loading curriculum data:", error);
      if (error.message !== "Unauthorized") {
        updateCurriculumStats([]); // Clear stats on error
        renderCurriculumTable([]); // Render an empty table
      }
    })
    .finally(() => {
      showCurriculumLoading(false); // Always hide spinner
    });
}

function updateCurriculumStats(curricula) {
  console.log("📊 updateCurriculumStats called with:", curricula);
  
  const totalCurriculums = document.getElementById("totalCurriculums");
  const activeCurriculums = document.getElementById("activeCurriculums");
  const pendingCurriculums = document.getElementById("pendingCurriculums");
  const totalSubjects = document.getElementById("totalSubjects");

  console.log("📊 Stats elements found:", {
    totalCurriculums: !!totalCurriculums,
    activeCurriculums: !!activeCurriculums,
    pendingCurriculums: !!pendingCurriculums,
    totalSubjects: !!totalSubjects
  });

  if (!curricula) {
    console.warn("⚠️ No curricula data provided to updateCurriculumStats");
    // Set to 0 if no data
    if (totalCurriculums) totalCurriculums.textContent = "0";
    if (activeCurriculums) activeCurriculums.textContent = "0";
    if (pendingCurriculums) pendingCurriculums.textContent = "0";
    if (totalSubjects) totalSubjects.textContent = "0";
    return;
  }

  const total = curricula.length;
  const active = curricula.filter((c) => c.curr_status === "active").length;
  const pending = curricula.filter((c) => c.curr_status === "pending").length;

  console.log("📊 Calculated stats:", { total, active, pending });

  if (totalCurriculums) {
    totalCurriculums.textContent = total;
    console.log("✅ Updated totalCurriculums:", total);
  } else {
    console.warn("⚠️ totalCurriculums element not found");
  }
  
  if (activeCurriculums) {
    activeCurriculums.textContent = active;
    console.log("✅ Updated activeCurriculums:", active);
  } else {
    console.warn("⚠️ activeCurriculums element not found");
  }
  
  if (pendingCurriculums) {
    pendingCurriculums.textContent = pending;
    console.log("✅ Updated pendingCurriculums:", pending);
  } else {
    console.warn("⚠️ pendingCurriculums element not found");
  }
  
  // A simple count of subjects from all curricula can be done here if needed
  if (totalSubjects) {
    // This is a rough estimate, a dedicated API call would be better for accuracy
    // For now, set to 0 or calculate from curricula if subject count is available
    totalSubjects.textContent = "0"; // Placeholder until we have subject count
  }
}

function showCurriculumLoading(show) {
  const loadingState = document.getElementById("curriculumLoading");
  const table = document.getElementById("curriculaTable");
  const emptyState = document.getElementById("curriculumEmpty");
  const tableContainer = document.getElementById("curriculumTableContainer");

  if (!loadingState || !tableContainer || !emptyState) return;

  if (show) {
    loadingState.style.display = "block";
    tableContainer.style.display = "none";
    emptyState.style.display = "none";
  } else {
    loadingState.style.display = "none";
  }
}

function renderCurriculumTable(curricula) {
  console.log(
    "🎨 Rendering curriculum table with " +
      (curricula ? curricula.length : 0) +
      " records."
  );
  const tableId = "#curriculaTable";
  let tableContainer = document.getElementById("curriculumTableContainer");
  const emptyState = document.getElementById("curriculumEmpty");
  let tableElement = document.querySelector(tableId);

  console.log("🔍 renderCurriculumTable - Initial element check:", {
    tableContainer: !!tableContainer,
    tableElement: !!tableElement,
    emptyState: !!emptyState,
    curriculaCount: curricula ? curricula.length : 0
  });

  // If table elements don't exist, wait and retry multiple times
  if (!tableContainer || !tableElement) {
    console.warn("⚠️ Curriculum table elements not found initially, waiting and retrying...");
    
    // Retry with exponential backoff - up to 5 attempts over 2.5 seconds
    let retryCount = 0;
    const maxRetries = 5;
    const retryDelays = [200, 300, 400, 500, 600]; // Total: 2000ms
    
    const retryFindElements = () => {
      retryCount++;
      tableContainer = document.getElementById("curriculumTableContainer");
      tableElement = document.querySelector("#curriculaTable");
      
      console.log(`🔄 Retry ${retryCount}/${maxRetries}:`, {
        tableContainer: !!tableContainer,
        tableElement: !!tableElement
      });
      
      if (tableContainer && tableElement) {
        console.log("✅ Curriculum elements found on retry, proceeding with rendering...");
        // Elements found, proceed with rendering
        proceedWithRendering();
      } else if (retryCount < maxRetries) {
        // Wait and retry again
        setTimeout(retryFindElements, retryDelays[retryCount - 1]);
      } else {
        // Final attempt failed - log but don't give up completely
        console.error("❌ Curriculum table elements not found after all retries");
        console.error("   tableContainer:", tableContainer ? "found" : "NOT FOUND");
        console.error("   tableElement:", tableElement ? "found" : "NOT FOUND");
        console.error("   Attempting to find elements in entire document...");
        
        // Last resort: search more broadly
        const allTables = document.querySelectorAll('table[id*="curricula"], table[id*="curriculum"]');
        const allContainers = document.querySelectorAll('[id*="curriculumTableContainer"]');
        
        if (allTables.length > 0 || allContainers.length > 0) {
          console.log("   Found potential elements:", {
            tables: allTables.length,
            containers: allContainers.length
          });
        }
        
        // Don't return - let it try to proceed anyway if we have data
        if (curricula && curricula.length > 0) {
          console.warn("⚠️ Proceeding anyway since we have curriculum data - elements might appear later");
          // Store data for later rendering attempt
          setTimeout(() => {
            const finalContainer = document.getElementById("curriculumTableContainer");
            const finalElement = document.querySelector("#curriculaTable");
            if (finalContainer && finalElement) {
              console.log("✅ Elements found on delayed check, rendering now...");
              renderCurriculumTable(curricula);
            }
          }, 1000);
        }
        return;
      }
    };
    
    // Start retry sequence
    setTimeout(retryFindElements, retryDelays[0]);
    return; // Exit early, will continue via retry callback
  }
  
  // Elements found, proceed with rendering immediately
  proceedWithRendering();
  
  function proceedWithRendering() {
    // Re-check elements one more time (they might have changed)
    if (!tableContainer) tableContainer = document.getElementById("curriculumTableContainer");
    if (!tableElement) tableElement = document.querySelector(tableId);
    
    if (!tableContainer || !tableElement) {
      console.error("❌ Elements lost during retry - cannot proceed with rendering");
      return;
    }

    console.log("✅ Proceeding with curriculum table rendering...");

    // Ensure curricula is an array
    if (!Array.isArray(curricula)) {
      console.warn("curricula is not an array:", typeof curricula, curricula);
      curricula = [];
    }

    // Destroy existing DataTable if it exists
    if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableId)) {
      console.log("🗑️ Destroying existing DataTable instance...");
      $(tableId).DataTable().destroy();
    }

    // Show/hide empty state
    if (curricula.length === 0) {
      console.log("📭 No curricula to display - showing empty state");
      if (tableContainer) tableContainer.style.display = "none";
      if (emptyState) emptyState.style.display = "block";
      return;
    } else {
      console.log(`📊 Showing table with ${curricula.length} curricula`);
      // CRITICAL: Show the table container before initializing DataTable
      if (tableContainer) {
        tableContainer.style.display = "block";
        console.log("✅ Table container displayed");
      }
      if (emptyState) emptyState.style.display = "none";
    }

    // Initialize DataTable
    try {
      console.log("🔧 Initializing DataTable for curriculum table...");
      // Clear any existing table content to prevent column misalignment
      $(tableId + " tbody").empty();
      
      $(tableId).DataTable({
        data: curricula,
        destroy: true, // Destroy any existing DataTable instance
        autoWidth: false, // Prevent auto-width calculation issues
        columns: [
          { 
            data: "curr_name",
            title: "Name",
            render: (data) => "<strong>" + escapeHtml(data) + "</strong>"
          },
          {
            data: "curr_effective_start_year",
            title: "Effective Start Year",
            render: (data) => {
              if (!data) return "-";
              return escapeHtml(data);
            },
          },
          {
            data: "curr_status",
            title: "Status",
            className: "text-center",
            render: (data) => {
              const badgeClass = getStatusBadgeClass(data);
              return `<span class="badge bg-${badgeClass}">${escapeHtml(
                data
              )}</span>`;
            },
          },
          {
            data: null,
            title: "Actions",
            orderable: false,
            className: "text-center",
            render: (data, type, row) => {
              return `                 
                <div class="d-flex gap-1 justify-content-center">
                  <button class="btn btn-sm btn-outline-secondary" onclick="viewCurriculum(${row.curr_id})" title="View Curriculum">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-primary" onclick="editCurriculum(${row.curr_id})" title="Edit Curriculum">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteCurriculum(${row.curr_id})" title="Delete Curriculum">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>              
              `;
            },
          },
        ],
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
        order: [
          [0, "asc"],
        ], // Order by name asc
        language: {
          emptyTable: "No curriculum data available.",
        },
      });
      console.log("✅ Curriculum DataTable initialized successfully");
    } catch (error) {
      console.error("❌ Error initializing Curriculum DataTable:", error);
      console.error("   Error details:", error.message, error.stack);
      if (tableContainer) tableContainer.style.display = "none";
      if (emptyState) emptyState.style.display = "block";
    }
  } // End of proceedWithRendering function
} // End of renderCurriculumTable function

function getStatusBadgeClass(status) {
  if (status === "active") return "success";
  if (status === "pending") return "warning";
  return "secondary"; // for 'inactive' and others
}

function viewCurriculum(id) {
  const curriculum = __CURRICULUM_CACHE__.find((c) => c.curr_id == id);
  if (!curriculum) {
    Swal.fire("Error", "Curriculum data not found.", "error");
    return;
  }

  console.log("Viewing curriculum:", curriculum); // Debug log

  // Populate the view modal fields
  document.getElementById("view_curr_name").textContent =
    curriculum.curr_name || "-";

  // Effective years
  const startYear = curriculum.curr_effective_start_year || "Not specified";
  const endYear = curriculum.curr_effective_end_year || "Not specified";
  document.getElementById("view_curr_effective_start_year").textContent =
    startYear;
  document.getElementById("view_curr_effective_end_year").textContent = endYear;

  const statusBadge = `<span class="badge bg-${getStatusBadgeClass(
    curriculum.curr_status
  )}">${curriculum.curr_status}</span>`;
  document.getElementById("view_curr_status").innerHTML = statusBadge;

  // Show the modal
  const viewModal = new bootstrap.Modal(
    document.getElementById("viewCurriculumModal")
  );
  viewModal.show();
}

function editCurriculum(id) {
  const curriculum = __CURRICULUM_CACHE__.find((c) => c.curr_id == id);
  if (!curriculum) {
    Swal.fire("Error", "Curriculum data not found.", "error");
    return;
  }

  // Populate the modal fields
  document.getElementById("edit_curr_id").value = curriculum.curr_id;
  document.getElementById("edit_curr_name").value = curriculum.curr_name;
  document.getElementById("edit_curr_status").value = curriculum.curr_status;
  document.getElementById("edit_dept_ids").value = curriculum.dept_id; // Ensure this ID matches your edit form's department input
  document.getElementById("edit_curr_effective_start_year").value =
    curriculum.curr_effective_start_year || "";
  document.getElementById("edit_curr_effective_end_year").value =
    curriculum.curr_effective_end_year || "";

  // Show the modal
  const editModal = new bootstrap.Modal(
    document.getElementById("editCurriculumModal")
  );
  editModal.show();
}

function deleteCurriculum(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      fetch("../../admin/views/components/delete_curriculum.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ curr_id: id }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire(
              "Deleted!",
              data.message || "The curriculum has been removed.",
              "success"
            );
            loadCurriculumData(); // Refresh the curriculum data table
            // Refresh subject tables to reflect curriculum deletion
            if (typeof refreshAllSubjectTables === "function") {
              refreshAllSubjectTables();
            }
          } else {
            throw new Error(data.message || "Failed to delete curriculum.");
          }
        })
        .catch((error) => {
          Swal.fire("Deletion Failed", error.message, "error");
        });
    }
  });
}

function openAddCurriculumModal() {
  const addModal = new bootstrap.Modal(
    document.getElementById("addCurriculumModal")
  );
  addModal.show();
}

function saveCurriculumEdits() {
  const form = document.getElementById("editCurriculumForm");
  const formData = new FormData(form);
  const saveBtn = document.querySelector("#editCurriculumModal .btn-success");

  // Enhanced Frontend Validation - only require essential fields
  const requiredFields = [
    "curr_id",
    "curr_name",
  ];
  for (const fieldName of requiredFields) {
    if (!formData.get(fieldName)) {
      Swal.fire({
        icon: "warning",
        title: "Missing Required Field",
        text: `The field "${fieldName.replace(
          /_/g,
          " "
        )}" is missing. Please refresh and try again.`,
        confirmButtonColor: "#800000",
      });
      return;
    }
  }
  // Also check the hidden dept_id field
  if (!formData.get("dept_id")) {
    Swal.fire({
      icon: "warning",
      title: "Missing Department ID",
      text: `The department ID is missing. Please refresh and try again.`,
      confirmButtonColor: "#800000",
    });
    return;
  }

  const originalText = saveBtn.innerHTML;
  saveBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  saveBtn.disabled = true;

  fetch("../../admin/management/update_curriculum.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Updated!",
          text: data.message || "Curriculum has been updated successfully.",
        });
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("editCurriculumModal")
        );
        modal.hide();
        loadCurriculumData(); // Refresh the curriculum table
        // Refresh subject tables to reflect curriculum changes
        if (typeof refreshAllSubjectTables === "function") {
          refreshAllSubjectTables();
        }
      } else {
        throw new Error(data.message || "Failed to update curriculum.");
      }
    })
    .catch((error) => {
      Swal.fire({
        icon: "error",
        title: "Update Failed",
        text: error.message,
      }).then(() => {
        // Log the full error object to the console for detailed debugging
        console.error("Full error details:", error);
      });
    })
    .finally(() => {
      saveBtn.innerHTML = originalText;
      saveBtn.disabled = false;
    });
}

function saveCurriculum() {
  const form = document.getElementById("addCurriculumFormManagement");
  const formData = new FormData(form);

  // Validate form
  const requiredFields = [
    "curr_name",
    "curr_code",
    "curr_yr",
    "curr_status",
    "curr_lvl",
  ];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      Swal.fire({
        icon: "warning",
        title: "Required Field",
        text: `Please fill in the ${field.replace(/_/g, " ")} field.`,
        confirmButtonColor: "#800000",
        confirmButtonText: "OK",
      });
      return;
    }
  }

  const saveBtn = document.querySelector(
    "#addCurriculumModal .js-save-curriculum-btn"
  );
  const originalText = saveBtn.innerHTML;
  saveBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  saveBtn.disabled = true;

  fetch("../../admin/management/add_curriculum.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Curriculum Added!",
          text: data.message || "The curriculum has been successfully added.",
          confirmButtonColor: "#28a745",
        }).then(() => {
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("addCurriculumModal")
          );
          modal.hide();
          form.reset();
          loadCurriculumData(); // Refresh the curriculum data
          // Refresh subject tables to reflect curriculum changes
          if (typeof refreshAllSubjectTables === "function") {
            refreshAllSubjectTables();
          }
        });
      } else {
        throw new Error(data.message || "Failed to add curriculum.");
      }
    })
    .catch((error) => {
      Swal.fire({
        icon: "error",
        title: "Oops...",
        text: error.message,
      });
    })
    .finally(() => {
      saveBtn.innerHTML = originalText;
      saveBtn.disabled = false;
    });
}

// Initialize on DOM load
document.addEventListener("DOMContentLoaded", function () {
  const changePhotoBtn = document.getElementById("changePhotoBtn");
  const profilePhotoInput = document.getElementById("profilePhotoInput");
  const profilePreview = document.getElementById("profilePreview");
  const adminProfileImg = document.getElementById("adminProfileImg");

  // Load current profile data
  loadProfileData();
  
  // Check if curriculum section exists and load data if it does
  // This ensures curriculum data loads even if the tab is already visible on page load
  setTimeout(() => {
    const curriculumTab = document.getElementById("curriculum");
    const curriculumTable = document.getElementById("curriculaTable");
    const curriculumTableContainer = document.getElementById("curriculumTableContainer");
    const totalCurriculums = document.getElementById("totalCurriculums");
    
    // Check if curriculum tab is visible or if curriculum elements exist
    const isTabVisible = curriculumTab && curriculumTab.style.display !== "none";
    const hasElements = curriculumTable || curriculumTableContainer || totalCurriculums;
    
    if ((isTabVisible || hasElements) && typeof loadCurriculumData === "function") {
      console.log("📖 Curriculum section detected in DOM (on page load), loading data automatically...");
      console.log("   Tab visible:", isTabVisible, "Elements found:", hasElements);
      loadCurriculumData();
    } else {
      console.log("ℹ️ Curriculum section not found on page load - will load when tab is clicked");
    }
  }, 800); // Increased delay to ensure DOM is fully ready

  // Handle curriculum filter changes
  const searchInput = document.getElementById("curriculumSearchInput");
  const statusFilter = document.getElementById("curriculumStatusFilter");
  const yearFilter = document.getElementById("curriculumYearFilter");
  if (searchInput)
    searchInput.addEventListener("keyup", applyCurriculumFilters);
  if (statusFilter)
    statusFilter.addEventListener("change", applyCurriculumFilters);
  if (yearFilter) yearFilter.addEventListener("change", applyCurriculumFilters);

  // ============================================================
  // REMOVED: ensureSingleActiveTab() function
  // ============================================================
  // Bootstrap automatically ensures only one tab is active at a time
  // through its native tab management system. Manual DOM manipulation
  // conflicts with Bootstrap's event-driven tab state management.
  // Bootstrap handles:
  // - Active class management on nav-links
  // - Tab pane visibility (show/hide)
  // - Event firing (shown.bs.tab, hidden.bs.tab)
  // ============================================================

  // Check if user has already selected a tab (don't reset if they have)
  if (window.userSelectedTab && window.tabInitialized) {
    console.log(
      "User has already selected tab:",
      window.userSelectedTab,
      "- skipping initialization reset"
    );
    // Only load dashboard data if overview is active
    if (
      window.userSelectedTab === "overview" &&
      typeof loadDashboardData === "function"
    ) {
      requestAnimationFrame(() => {
        loadDashboardData();
      });
    }
    return; // Exit early - don't reset user's selection
  }

  // Check if a tab is already active (user might have clicked before initialization)
  const activeTabContent = document.querySelector(
    '.tab-content[style*="block"]'
  );
  const activeNavLink = document.querySelector(
    `.sidebar .nav-link.active, .sidebar-offcanvas .nav-link.active`
  );

  if (activeTabContent && activeTabContent.id) {
    const alreadyActiveTab = activeTabContent.id;
    // Verify the active nav link matches the active tab
    if (activeNavLink) {
      const onclickAttr = activeNavLink.getAttribute("onclick");
      if (onclickAttr && onclickAttr.includes(alreadyActiveTab)) {
        // User has already selected a tab, preserve it - don't reset
        console.log(
          "Tab already active:",
          alreadyActiveTab,
          "- preserving user selection"
        );
        window.userSelectedTab = alreadyActiveTab;
        window.tabInitialized = true;
        // Load data for the active tab
        if (
          alreadyActiveTab === "overview" &&
          typeof loadDashboardData === "function"
        ) {
          requestAnimationFrame(() => {
            loadDashboardData();
          });
        } else if (
          alreadyActiveTab === "curriculum" &&
          typeof loadCurriculumData === "function"
        ) {
          // Load curriculum data if curriculum (Subjects) tab is already active
          console.log("📖 Curriculum (Subjects) tab already active on load, loading curriculum data...");
          requestAnimationFrame(() => {
            loadCurriculumData();
          });
        } else if (
          alreadyActiveTab === "curriculum_management" &&
          typeof loadCurriculumData === "function"
        ) {
          // Load curriculum data if curriculum management tab is already active
          console.log("📖 Curriculum Management tab already active on load, loading data...");
          requestAnimationFrame(() => {
            loadCurriculumData();
          });
        }
        return; // Exit early to preserve current state
      }
    }
  }

  // Always show Overview tab on initial page load (default behavior)
  let initialTab = "overview";

  // Clear any stored tab preference to ensure Overview is always shown on refresh
  try {
    localStorage.removeItem("admin_active_tab");
  } catch (e) {}

  // ============================================================
  // Bootstrap Tab Initialization - No Manual DOM Manipulation
  // ============================================================
  // Bootstrap handles tab visibility automatically via tab-pane classes
  // We only need to ensure the Overview tab link is active
  // Bootstrap will handle showing/hiding tab panes based on active nav-link
  // ============================================================
  
  // Find Overview tab link by href (Bootstrap way) or onclick (legacy)
  const sidebarLink = document.querySelector(
    `.sidebar .nav-link[href="#${initialTab}"], .sidebar .nav-link[onclick*="${initialTab}"]`
  );
  const mobileSidebarLink = document.querySelector(
    `.sidebar-offcanvas .nav-link[href="#${initialTab}"], .sidebar-offcanvas .nav-link[onclick*="${initialTab}"]`
  );

  // Activate Overview tab using Bootstrap Tab API (if available)
  // This ensures Bootstrap handles the tab state properly
  if (sidebarLink && typeof bootstrap !== "undefined" && bootstrap.Tab) {
    try {
      // Use Bootstrap Tab API to activate Overview
      const overviewTab = bootstrap.Tab.getOrCreateInstance(sidebarLink);
      overviewTab.show(); // This will fire shown.bs.tab and handle initialization
    } catch (error) {
      console.warn("[TAB INIT] Could not use Bootstrap Tab API, using fallback:", error);
      // Fallback: Just add active class and let Bootstrap CSS handle visibility
      sidebarLink.classList.add("active");
      if (mobileSidebarLink) {
        mobileSidebarLink.classList.add("active");
      }
    }
  } else if (sidebarLink) {
    // Fallback if Bootstrap not available: just add active class
    sidebarLink.classList.add("active");
    if (mobileSidebarLink) {
      mobileSidebarLink.classList.add("active");
    }
  }

  // Mark as initialized
  window.tabInitialized = true;
  
  // Load overview data (Bootstrap will handle tab visibility)
  if (typeof loadDashboardData === "function") {
    // Use initTabOnce to ensure it only loads once
    window.initTabOnce("main_overview", () => {
      loadDashboardData();
    });
  }

  // ============================================================
  // REMOVED: Manual tab manipulation setInterval
  // ============================================================
  // Bootstrap handles all tab state automatically via its event system
  // No need for periodic checks or manual DOM manipulation
  // Tab state is managed by Bootstrap's shown.bs.tab and hidden.bs.tab events
  // ============================================================

  // Optional: If you want to restore last tab on refresh, uncomment below and remove localStorage.removeItem above
  // try { initialTab = localStorage.getItem('admin_active_tab') || 'overview'; } catch (e) {}

  // Photo change functionality
  if (changePhotoBtn && profilePhotoInput) {
    changePhotoBtn.addEventListener("click", function () {
      profilePhotoInput.click();
    });

    profilePhotoInput.addEventListener("change", function () {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          profilePreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Initialize modal event listeners
  const modal = document.getElementById("addAccountModal");
  if (modal) {
    // Load workload policy data and programs when modal is shown
    modal.addEventListener("shown.bs.modal", function () {
      loadWorkloadPolicyData();
      loadProgramsForAddAccount();
      // Ensure rank and designation fields are properly configured
      // Part-Time: Disable designation, enable rank
      // Moderator: Disable both
      // Regular/Contractual: Enable both
      const rankField = document.getElementById("add_rank");
      const designationField = document.getElementById("add_designation");
      const moderatorCheckbox = document.getElementById("add_role_moderator");
      const hasModeratorRole = moderatorCheckbox && moderatorCheckbox.checked;
      const instStatusSelect = document.getElementById("add_inst_status");
      const instStatus = instStatusSelect ? instStatusSelect.value : "";
      const isPartTime = instStatus === "Part-Time";

      // Handle rank field
      if (rankField) {
        if (hasModeratorRole) {
          rankField.disabled = true;
          rankField.required = false;
          rankField.classList.add("text-muted");
        } else if (isPartTime) {
          rankField.disabled = false;
          rankField.required = false; // Optional for Part-Time
          rankField.classList.remove("text-muted");
        } else {
          rankField.disabled = false;
          rankField.required = true; // Required for Regular/Contractual
          rankField.classList.remove("text-muted");
        }
      }
      
      // Handle designation field
      if (designationField) {
        if (hasModeratorRole || isPartTime) {
          designationField.disabled = true;
          designationField.value = "None";
          designationField.required = false;
          designationField.classList.add("text-muted");
        } else {
          designationField.disabled = false;
          designationField.classList.remove("text-muted");
        }
      }

      // If employment status is already set, trigger the handler to ensure fields are correct
      // Use setTimeout to ensure DOM is fully ready
      setTimeout(() => {
        if (instStatus) {
          handleEmploymentStatusChange(instStatus, "add");
          // Also handle rank change to set instruction hours
          updateInstructionHours("add");
        } else {
          // Even if no status is set, ensure fields are enabled (except for Moderator)
          // Part-Time will be handled when status is selected
          if (rankField && roleId !== "3") {
            rankField.disabled = false;
            rankField.required = true;
            rankField.classList.remove("text-muted");
          }
          if (designationField && roleId !== "3") {
            designationField.disabled = false;
            designationField.classList.remove("text-muted");
          }
          // Handle rank change even if no status set
          updateInstructionHours("add");
        }
      }, 100);
    });

    modal.addEventListener("hidden.bs.modal", function () {
      // Reset form when modal is closed
      const form = document.getElementById("addAccountForm");
      if (form) {
        form.reset();
        // Remove any validation classes
        form.querySelectorAll(".is-invalid").forEach((field) => {
          field.classList.remove("is-invalid");
        });
        // Reset role-based field states
        resetRoleBasedFields();
      }
    });
  }

  // Function to load programs for Add Account modal
  function loadProgramsForAddAccount() {
    const programSelect = document.getElementById("add_program_id");
    if (!programSelect) return;

    programSelect.innerHTML = '<option value="">Loading Programs...</option>';

    const apiBasePath = getApiBasePath
      ? getApiBasePath()
      : "../../admin/management/";
    fetch(`${apiBasePath}get_schedule_form_data.php`)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        programSelect.innerHTML =
          '<option value="">Select Program/Course</option>';

        if (data.success && data.programs && data.programs.length > 0) {
          console.log(
            `Loading ${data.programs.length} programs for Add Account modal`
          );
          data.programs.forEach((program) => {
            const option = document.createElement("option");
            option.value = program.program_id;
            const displayText =
              program.program_display ||
              (program.program_code && program.program_name
                ? `${program.program_code} - ${program.program_name}`
                : program.program_name || program.program_code || "Unknown");
            option.textContent = displayText;
            programSelect.appendChild(option);
          });
        } else {
          const option = document.createElement("option");
          option.value = "";
          option.textContent = "No programs available";
          option.disabled = true;
          programSelect.appendChild(option);
        }
      })
      .catch((error) => {
        console.error("Error loading programs for Add Account:", error);
        programSelect.innerHTML =
          '<option value="">Error Loading Programs</option>';
      });
  }

  // Function to load programs for Edit Account modal (exposed globally)
  window.loadProgramsForEditAccount = function (selectedProgramId = "") {
    const programSelect = document.getElementById("edit_program_id");
    if (!programSelect) return;

    programSelect.innerHTML = '<option value="">Loading Programs...</option>';

    const apiBasePath = getApiBasePath
      ? getApiBasePath()
      : "../../admin/management/";
    const params = new URLSearchParams({ dept_id: window.currentDeptId || 0 });
    fetch(`${apiBasePath}get_schedule_form_data.php?${params.toString()}`)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        programSelect.innerHTML =
          '<option value="">Select Program/Course</option>';

        if (data.success && data.programs && data.programs.length > 0) {
          console.log(
            `Loading ${data.programs.length} programs for Edit Account modal`
          );
          data.programs.forEach((program) => {
            const option = document.createElement("option");
            option.value = program.program_id;
            const displayText =
              program.program_display ||
              (program.program_code && program.program_name
                ? `${program.program_code} - ${program.program_name}`
                : program.program_name || program.program_code || "Unknown");
            option.textContent = displayText;
            if (
              selectedProgramId &&
              String(program.program_id) === String(selectedProgramId)
            ) {
              option.selected = true;
            }
            programSelect.appendChild(option);
          });
        } else {
          const option = document.createElement("option");
          option.value = "";
          option.textContent = "No programs available";
          option.disabled = true;
          programSelect.appendChild(option);
        }
      })
      .catch((error) => {
        console.error("Error loading programs for Edit Account:", error);
        programSelect.innerHTML =
          '<option value="">Error Loading Programs</option>';
      });
  };

  // Function to load workload policy data (ranks and designations) from database.
  // Academic Rank options are now populated from a static list so they no longer
  // depend on the workload_policy table values; designations still come from DB.
  function loadWorkloadPolicyData(modalType = "add", valuesToSet = null) {
    const rankSelectId = modalType === "edit" ? "edit_rank" : "add_rank";
    const designationSelectId =
      modalType === "edit" ? "edit_designation" : "add_designation";

    console.log("Loading workload policy data for modal type:", modalType);
    console.log("Rank select ID:", rankSelectId);
    console.log("Designation select ID:", designationSelectId);
    if (valuesToSet) {
      console.log("Values to set after loading:", valuesToSet);
    }

    fetch("../../admin/management/get_workload_policy.php")
      .then((response) => {
        console.log("Workload policy API response status:", response.status);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Workload policy data received:", data);
        if (data.success && data.data) {
          // Store workload policy hours data globally for use in instruction hours calculation
          if (data.data.rank_hours) {
            window.WORKLOAD_RANK_HOURS = data.data.rank_hours;
            console.log("Stored rank hours:", window.WORKLOAD_RANK_HOURS);
          }
          if (data.data.designation_hours) {
            window.WORKLOAD_DESIGNATION_HOURS = data.data.designation_hours;
            console.log("Stored designation hours:", window.WORKLOAD_DESIGNATION_HOURS);
          }
          
          // Store all 6 workload fields for ranks and designations
          if (data.data.rank_workloads) {
            window.WORKLOAD_RANK_WORKLOADS = data.data.rank_workloads;
            console.log("Stored rank workloads (all 6 fields):", window.WORKLOAD_RANK_WORKLOADS);
          }
          if (data.data.designation_workloads) {
            window.WORKLOAD_DESIGNATION_WORKLOADS = data.data.designation_workloads;
            console.log("Stored designation workloads (all 6 fields):", window.WORKLOAD_DESIGNATION_WORKLOADS);
          }
          
          // Populate Academic Rank dropdown using a fixed list (not from workload_policy table)
          const rankSelect = document.getElementById(rankSelectId);
          if (rankSelect) {
            console.log(
              "Rank select element found, populating (static list)..."
            );
            const currentValue =
              rankSelect.value ||
              (valuesToSet && valuesToSet.rank ? valuesToSet.rank : ""); // Preserve current selection or use provided value
            rankSelect.innerHTML = '<option value="">Select Rank</option>';

            const ACADEMIC_RANKS = [
              "University Professor",
              "Professor VI",
              "Professor V",
              "Professor IV",
              "Professor III",
              "Professor II",
              "Professor I",
              "Associate Professor V",
              "Associate Professor IV",
              "Associate Professor III",
              "Associate Professor II",
              "Associate Professor I",
              "Assistant Professor IV",
              "Assistant Professor III",
              "Assistant Professor II",
              "Assistant Professor I",
              "Instructor III",
              "Instructor II",
              "Instructor I",
            ];

            ACADEMIC_RANKS.forEach((rank) => {
              const option = document.createElement("option");
              option.value = rank;
              option.textContent = rank;
              rankSelect.appendChild(option);
            });

            // Restore previous selection if it exists
            if (currentValue) {
              rankSelect.value = currentValue;
              console.log("Set rank to:", currentValue);
            }
          } else {
            console.error("Rank select element not found:", rankSelectId);
          }

          // Populate Designation dropdown
          const designationSelect =
            document.getElementById(designationSelectId);
          if (designationSelect) {
            console.log("Designation select element found, populating...");
            // Keep the default "None" option
            const currentValue =
              designationSelect.value ||
              (valuesToSet && valuesToSet.designation
                ? valuesToSet.designation
                : "None"); // Preserve current selection or use provided value
            designationSelect.innerHTML = '<option value="None">None</option>';

            // Add designation options from database (excluding "None" if it's already there)
            if (
              data.data.designations &&
              Array.isArray(data.data.designations)
            ) {
              console.log(
                "Adding",
                data.data.designations.length,
                "designation options"
              );
              data.data.designations.forEach((designation) => {
                // Skip "None" as it's already the default
                if (designation !== "None") {
                  const option = document.createElement("option");
                  option.value = designation;
                  option.textContent = designation;
                  designationSelect.appendChild(option);
                }
              });
            } else {
              console.warn(
                "No designations data found or not an array:",
                data.data.designations
              );
            }

            // Restore previous selection if it exists
            if (currentValue) {
              designationSelect.value = currentValue;
              console.log("Set designation to:", currentValue);
            }
          } else {
            console.error(
              "Designation select element not found:",
              designationSelectId
            );
          }
        } else {
          console.error(
            "Failed to load workload policy data. Success:",
            data.success,
            "Message:",
            data.message
          );
        }
      })
      .catch((error) => {
        console.error("Error loading workload policy data:", error);
        console.error("Error details:", error.message, error.stack);
        // Show user-friendly error message
        const rankSelect = document.getElementById(rankSelectId);
        const designationSelect = document.getElementById(designationSelectId);
        if (rankSelect) {
          rankSelect.innerHTML =
            '<option value="">Error loading ranks</option>';
        }
        if (designationSelect) {
          designationSelect.innerHTML =
            '<option value="None">Error loading designations</option>';
        }
      });
  }

  // Initialize edit modal event listener
  const editModal = document.getElementById("editAccountModal");
  if (editModal) {
    editModal.addEventListener("shown.bs.modal", function () {
      // Get stored rank and designation values if they exist
      const valuesToSet = window.__EDIT_ACCOUNT_RANK_DESIGNATION__ || null;
      loadWorkloadPolicyData("edit", valuesToSet);
      // Clear the stored values after use
      window.__EDIT_ACCOUNT_RANK_DESIGNATION__ = null;
      // Check employment status and disable fields if Part-Time
      const currentStatus = document.getElementById("edit_inst_status")?.value;
      if (currentStatus) {
        handleEmploymentStatusChange(currentStatus, "edit");
      }
      // Handle instruction hours update when modal is shown
      setTimeout(() => {
        updateInstructionHours("edit");
      }, 150);
    });
  }

  // Handle role selection change for add account form (checkboxes)
  const roleCheckboxes = document.querySelectorAll('input[name="role_ids[]"]');
  if (roleCheckboxes.length > 0) {
    roleCheckboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        handleRoleChange();
      });
    });
  }

  // Handle employment status change for add account form
  const addInstStatusSelect = document.getElementById("add_inst_status");
  if (addInstStatusSelect) {
    addInstStatusSelect.addEventListener("change", function () {
      handleEmploymentStatusChange(this.value, "add");
      // Also handle instruction hours when employment status changes
      updateInstructionHours("add");
    });
  }

  // Handle employment status change for edit account form
  const editInstStatusSelect = document.getElementById("edit_inst_status");
  if (editInstStatusSelect) {
    editInstStatusSelect.addEventListener("change", function () {
      handleEmploymentStatusChange(this.value, "edit");
      // Also handle instruction hours when employment status changes
      updateInstructionHours("edit");
    });
  }

  // Also handle when edit modal is shown to ensure fields are properly enabled/disabled
  if (editModal) {
    editModal.addEventListener("shown.bs.modal", function () {
      const currentStatus = document.getElementById("edit_inst_status")?.value;
      if (currentStatus) {
        handleEmploymentStatusChange(currentStatus, "edit");
        // Also handle instruction hours when modal is shown
        updateInstructionHours("edit");
      }
    });
  }

  // Handle rank selection change for add account form
  const addRankSelect = document.getElementById("add_rank");
  if (addRankSelect) {
    addRankSelect.addEventListener("change", function () {
      handleRankChange("add");
    });
  }

  // Handle rank selection change for edit account form
  const editRankSelect = document.getElementById("edit_rank");
  if (editRankSelect) {
    editRankSelect.addEventListener("change", function () {
      handleRankChange("edit");
    });
  }

  // Handle designation selection change for add account form
  const addDesignationSelect = document.getElementById("add_designation");
  if (addDesignationSelect) {
    addDesignationSelect.addEventListener("change", function () {
      handleDesignationChange("add");
    });
  }

  // Handle designation selection change for edit account form
  const editDesignationSelect = document.getElementById("edit_designation");
  if (editDesignationSelect) {
    editDesignationSelect.addEventListener("change", function () {
      handleDesignationChange("edit");
    });
  }

  // Function to get instruction hours based on academic rank
  function getInstructionHoursByRank(rank) {
    if (!rank) return null;

    // First try to get from workload policy table (if available)
    if (window.WORKLOAD_RANK_HOURS && window.WORKLOAD_RANK_HOURS[rank]) {
      const hours = window.WORKLOAD_RANK_HOURS[rank];
      if (hours > 0) {
        return hours;
      }
    }

    // Fallback to hardcoded values
    // University Professor = 6
    if (rank === "University Professor") {
      return 6;
    }
    // Professor I-VI = 9
    if (
      rank === "Professor I" ||
      rank === "Professor II" ||
      rank === "Professor III" ||
      rank === "Professor IV" ||
      rank === "Professor V" ||
      rank === "Professor VI"
    ) {
      return 9;
    }
    // Associate Professor I-V = 12
    if (
      rank === "Associate Professor I" ||
      rank === "Associate Professor II" ||
      rank === "Associate Professor III" ||
      rank === "Associate Professor IV" ||
      rank === "Associate Professor V"
    ) {
      return 12;
    }
    // Assistant Professor I-IV = 15
    if (
      rank === "Assistant Professor I" ||
      rank === "Assistant Professor II" ||
      rank === "Assistant Professor III" ||
      rank === "Assistant Professor IV"
    ) {
      return 15;
    }
    // Instructor I-III = 18
    if (
      rank === "Instructor I" ||
      rank === "Instructor II" ||
      rank === "Instructor III"
    ) {
      return 18;
    }

    return null;
  }

  // Function to get instruction hours based on designation
  function getInstructionHoursByDesignation(designation) {
    if (!designation || designation === "None") return null;

    // Get from workload policy table
    if (window.WORKLOAD_DESIGNATION_HOURS && window.WORKLOAD_DESIGNATION_HOURS[designation]) {
      const hours = window.WORKLOAD_DESIGNATION_HOURS[designation];
      if (hours > 0) {
        return hours;
      }
    }

    return null;
  }

  // Function to update all 6 workload hours based on designation and rank priority
  // Priority: Designation (if Regular) > Rank > Manual input
  function updateInstructionHours(modalType) {
    const rankFieldId = modalType === "edit" ? "edit_rank" : "add_rank";
    const designationFieldId = modalType === "edit" ? "edit_designation" : "add_designation";
    const instStatusFieldId =
      modalType === "edit" ? "edit_inst_status" : "add_inst_status";

    const rankField = document.getElementById(rankFieldId);
    const designationField = document.getElementById(designationFieldId);
    const instStatusField = document.getElementById(instStatusFieldId);

    if (!rankField && !designationField) {
      return;
    }

    const selectedRank = rankField ? rankField.value : "";
    const selectedDesignation = designationField ? designationField.value : "";
    const employmentStatus = instStatusField ? instStatusField.value : "";

    let workloadData = null;
    let source = "";

    // Part-Time users cannot have designation - only use rank
    if (employmentStatus === "Part-Time") {
      // Skip designation check entirely for Part-Time
      // Only check rank
      if (
        selectedRank &&
        window.WORKLOAD_RANK_WORKLOADS &&
        window.WORKLOAD_RANK_WORKLOADS[selectedRank]
      ) {
        workloadData = window.WORKLOAD_RANK_WORKLOADS[selectedRank];
        source = "rank";
      }
    } else {
      // Regular/Contractual: Priority 1 - Check designation first (if designation exists and is not 'None')
      if (
        selectedDesignation &&
        selectedDesignation !== "None" &&
        window.WORKLOAD_DESIGNATION_WORKLOADS &&
        window.WORKLOAD_DESIGNATION_WORKLOADS[selectedDesignation]
      ) {
        workloadData = window.WORKLOAD_DESIGNATION_WORKLOADS[selectedDesignation];
        source = "designation";
      }
      // Priority 2: Check rank if designation didn't provide data
      else if (
        selectedRank &&
        window.WORKLOAD_RANK_WORKLOADS &&
        window.WORKLOAD_RANK_WORKLOADS[selectedRank]
      ) {
        workloadData = window.WORKLOAD_RANK_WORKLOADS[selectedRank];
        source = "rank";
      }
    }

    // If we have workload data, populate all 6 fields
    if (workloadData) {
      // Get all 6 field IDs
      const fieldIds = {
        administration_hours: modalType === "edit" ? "edit_administration_hours" : "add_administration_hours",
        instruction_hours: modalType === "edit" ? "edit_instruction_hours" : "add_instruction_hours",
        research_hours: modalType === "edit" ? "edit_research_hours" : "add_research_hours",
        extension_hours: modalType === "edit" ? "edit_extension_hours" : "add_extension_hours",
        instructional_functions_hours: modalType === "edit" ? "edit_instructional_functions_hours" : "add_instructional_functions_hours",
        consultation_hours: modalType === "edit" ? "edit_consultation_hours" : "add_consultation_hours"
      };

      // Populate all 6 fields
      const adminField = document.getElementById(fieldIds.administration_hours);
      const instructionField = document.getElementById(fieldIds.instruction_hours);
      const researchField = document.getElementById(fieldIds.research_hours);
      const extensionField = document.getElementById(fieldIds.extension_hours);
      const instFuncField = document.getElementById(fieldIds.instructional_functions_hours);
      const consultationField = document.getElementById(fieldIds.consultation_hours);

      if (adminField) {
        adminField.value = workloadData.administration_hours || 0;
        adminField.readOnly = false; // Always editable
        adminField.style.backgroundColor = "";
        adminField.style.cursor = "";
      }

      if (instructionField) {
        instructionField.value = workloadData.instruction_hours || 0;
        // Make instruction hours read-only for Regular users, editable for Part-Time
        if (employmentStatus !== "Part-Time") {
          instructionField.readOnly = true;
          instructionField.style.backgroundColor = "#f8f9fa";
          instructionField.style.cursor = "not-allowed";
        } else {
          instructionField.readOnly = false;
          instructionField.style.backgroundColor = "";
          instructionField.style.cursor = "";
        }
      }

      if (researchField) {
        researchField.value = workloadData.research_hours || 0;
        researchField.readOnly = false; // Always editable
        researchField.style.backgroundColor = "";
        researchField.style.cursor = "";
      }

      if (extensionField) {
        extensionField.value = workloadData.extension_hours || 0;
        extensionField.readOnly = false; // Always editable
        extensionField.style.backgroundColor = "";
        extensionField.style.cursor = "";
      }

      if (instFuncField) {
        // Map production_hours to instructional_functions_hours
        instFuncField.value = workloadData.production_hours || 0;
        instFuncField.readOnly = false; // Always editable
        instFuncField.style.backgroundColor = "";
        instFuncField.style.cursor = "";
      }

      if (consultationField) {
        consultationField.value = workloadData.consultation_hours || 0;
        consultationField.readOnly = false; // Always editable
        consultationField.style.backgroundColor = "";
        consultationField.style.cursor = "";
      }

      console.log(
        `${source === "designation" ? "Designation" : "Rank"} "${source === "designation" ? selectedDesignation : selectedRank}" selected: All 6 workload fields auto-populated`,
        workloadData
      );
    } else {
      // No workload data found, allow manual input
      const instructionField = document.getElementById(
        modalType === "edit" ? "edit_instruction_hours" : "add_instruction_hours"
      );
      if (instructionField) {
        instructionField.readOnly = false;
        instructionField.style.backgroundColor = "";
        instructionField.style.cursor = "";
      }
      console.log("No designation/rank selected: All fields are editable");
    }
  }

  // Function to handle rank changes and update instruction hours
  function handleRankChange(modalType) {
    updateInstructionHours(modalType);
  }

  // Function to handle designation changes and update instruction hours
  function handleDesignationChange(modalType) {
    updateInstructionHours(modalType);
  }

  // Function to handle employment status changes
  function handleEmploymentStatusChange(status, modalType) {
    const rankFieldId = modalType === "edit" ? "edit_rank" : "add_rank";
    const designationFieldId =
      modalType === "edit" ? "edit_designation" : "add_designation";

    const rankField = document.getElementById(rankFieldId);
    const designationField = document.getElementById(designationFieldId);

    console.log("handleEmploymentStatusChange called:", {
      status,
      modalType,
      rankField: !!rankField,
      designationField: !!designationField,
    });

    // Also update instruction hours when employment status changes
    updateInstructionHours(modalType);

    // Note: Part-Time instructors can now have a rank (per campus policy)
    // Only disable rank/designation based on role, NOT employment status
    // Enable academic rank and designation for ALL employment statuses (including Part-Time)
    // But check if role allows it (for add modal) or just enable (for edit modal)
    if (rankField) {
      // Only disable if Moderator role (for add modal)
      if (modalType === "add") {
        const moderatorCheckbox = document.getElementById("add_role_moderator");
        const hasModeratorRole = moderatorCheckbox && moderatorCheckbox.checked;
        console.log(
          "Add modal - Has Moderator Role:",
          hasModeratorRole,
          "Status:",
          status
        );
        if (hasModeratorRole) {
          // Moderator role - disable rank
          rankField.disabled = true;
          rankField.required = false;
          rankField.value = "";
          rankField.classList.add("text-muted");
          console.log("Rank field disabled (Moderator role)");
        } else {
          // Instructor role or other - enable rank
          rankField.disabled = false;
          // Make rank optional (not required) for Part-Time instructors
          if (status === "Part-Time") {
            rankField.required = false;
            // Update label to hide asterisk for Part-Time
            const rankLabel = rankField.previousElementSibling;
            const asterisk = rankLabel
              ? rankLabel.querySelector(".rank-asterisk")
              : null;
            if (asterisk) {
              asterisk.style.display = "none";
            }
            console.log("Rank field enabled but optional (Part-Time status)");
          } else {
            rankField.required = true;
            // Update label to show asterisk for Regular/Contractual
            const rankLabel = rankField.previousElementSibling;
            const asterisk = rankLabel
              ? rankLabel.querySelector(".rank-asterisk")
              : null;
            if (asterisk) {
              asterisk.style.display = "inline";
            }
            console.log(
              "Rank field enabled and required (Regular/Contractual status)"
            );
          }
          rankField.classList.remove("text-muted");
        }
      } else {
        // For edit modal, enable it but make optional for Part-Time
        rankField.disabled = false;
        const instStatusEl = document.getElementById("edit_inst_status");
        const currentStatus = instStatusEl ? instStatusEl.value : "";
        if (currentStatus === "Part-Time") {
          rankField.required = false;
          // Update label to hide asterisk for Part-Time
          const rankLabel = rankField.previousElementSibling;
          const asterisk = rankLabel
            ? rankLabel.querySelector(".rank-asterisk, .text-danger")
            : null;
          if (asterisk) {
            asterisk.style.display = "none";
          }
          console.log(
            "Rank field enabled but optional (Edit modal, Part-Time status)"
          );
        } else {
          rankField.required = true;
          // Update label to show asterisk for Regular/Contractual
          const rankLabel = rankField.previousElementSibling;
          const asterisk = rankLabel
            ? rankLabel.querySelector(".rank-asterisk, .text-danger")
            : null;
          if (asterisk) {
            asterisk.style.display = "inline";
          }
          console.log(
            "Rank field enabled and required (Edit modal, Regular/Contractual status)"
          );
        }
        rankField.classList.remove("text-muted");
      }
    }
    if (designationField) {
      // Part-Time users cannot have designation - disable it
      if (status === "Part-Time") {
        designationField.disabled = true;
        designationField.value = "None";
        designationField.required = false;
        designationField.classList.add("text-muted");
        console.log("Designation field disabled (Part-Time status - Part-Time users cannot have designation)");
      } else {
        // Regular/Contractual - check role restrictions
        if (modalType === "add") {
          const moderatorCheckbox = document.getElementById("add_role_moderator");
          const hasModeratorRole = moderatorCheckbox && moderatorCheckbox.checked;
          if (hasModeratorRole) {
            // Moderator role - disable designation
            designationField.disabled = true;
            designationField.value = "None";
            designationField.classList.add("text-muted");
            console.log("Designation field disabled (Moderator role)");
          } else {
            // Instructor role or other - enable designation for Regular/Contractual
            designationField.disabled = false;
            designationField.classList.remove("text-muted");
            console.log(
              "Designation field enabled (Instructor role, Status:",
              status + ")"
            );
          }
        } else {
          // For edit modal, enable it for Regular/Contractual
          designationField.disabled = false;
          designationField.classList.remove("text-muted");
          console.log("Designation field enabled (Edit modal, Regular/Contractual status)");
        }
      }
    }
  }

  // Function to handle role selection changes (checkboxes)
  function handleRoleChange() {
    const rankField = document.getElementById("add_rank");
    const designationField = document.getElementById("add_designation");
    const instStatusField = document.getElementById("add_inst_status");
    const instStatus = instStatusField ? instStatusField.value : "";

    // Check if Moderator role (3) is selected
    const moderatorCheckbox = document.getElementById("add_role_moderator");
    const hasModeratorRole = moderatorCheckbox && moderatorCheckbox.checked;

    // Part-Time users cannot have designation
    const isPartTime = instStatus === "Part-Time";

    if (hasModeratorRole) {
      // Moderator role is selected
      // Disable academic rank and designation for moderators
      if (rankField) {
        rankField.disabled = true;
        rankField.required = false;
        rankField.value = ""; // Clear the value
        rankField.classList.add("text-muted");
      }
      if (designationField) {
        designationField.disabled = true;
        designationField.value = "None"; // Set to default
        designationField.classList.add("text-muted");
      }
    } else if (isPartTime) {
      // Part-Time status: Disable designation, enable rank
      if (rankField) {
        rankField.disabled = false;
        rankField.required = false; // Optional for Part-Time
        rankField.classList.remove("text-muted");
      }
      if (designationField) {
        designationField.disabled = true;
        designationField.value = "None"; // Force to None
        designationField.required = false;
        designationField.classList.add("text-muted");
      }
    } else {
      // Regular/Contractual: Enable both rank and designation
      if (rankField) {
        rankField.disabled = false;
        rankField.required = true; // Required for Regular/Contractual
        rankField.classList.remove("text-muted");
        // Update label to show asterisk for Regular/Contractual
        const rankLabel = rankField.previousElementSibling;
        const asterisk = rankLabel
          ? rankLabel.querySelector(".rank-asterisk")
          : null;
        if (asterisk) {
          asterisk.style.display = "inline";
        }
        console.log(
          "Rank field enabled and required (Regular/Contractual status)"
        );
      }
      if (designationField) {
        designationField.disabled = false;
        designationField.classList.remove("text-muted");
        console.log("Designation field enabled (Regular/Contractual status)");
      }
    }
    
    // Update workload hours when role changes (in case status is already set)
    updateInstructionHours("add");
  }

  // Function to reset role-based fields to default state
  function resetRoleBasedFields() {
    const rankField = document.getElementById("add_rank");
    const designationField = document.getElementById("add_designation");
    const instStatusField = document.getElementById("add_inst_status");
    const moderatorCheckbox = document.getElementById("add_role_moderator");
    const hasModeratorRole = moderatorCheckbox && moderatorCheckbox.checked;
    const instStatus = instStatusField ? instStatusField.value : "";
    const isPartTime = instStatus === "Part-Time";

    // Check if should be disabled based on role and employment status
    // Part-Time: Disable designation, enable rank
    // Moderator: Disable both
    // Regular/Contractual: Enable both

    if (rankField) {
      if (hasModeratorRole) {
        // Moderator: Disable rank
        rankField.disabled = true;
        rankField.required = false;
        rankField.value = "";
        rankField.classList.add("text-muted");
      } else if (isPartTime) {
        // Part-Time: Enable rank (optional)
        rankField.disabled = false;
        rankField.required = false;
        rankField.classList.remove("text-muted");
      } else {
        // Regular/Contractual: Enable rank (required)
        rankField.disabled = false;
        rankField.required = true;
        rankField.classList.remove("text-muted");
      }
    }
    if (designationField) {
      if (hasModeratorRole || isPartTime) {
        // Moderator or Part-Time: Disable designation
        designationField.disabled = true;
        designationField.value = "None";
        designationField.required = false;
        designationField.classList.add("text-muted");
      } else {
        // Regular/Contractual: Enable designation
        designationField.disabled = false;
        designationField.classList.remove("text-muted");
      }
    }
  }
});
function applyAccountsFilters() {
  console.log("🔥 applyAccountsFilters function called!");

  try {
    if (!Array.isArray(__ACCOUNTS_CACHE__)) {
      console.error(
        "❌ __ACCOUNTS_CACHE__ is not an array:",
        __ACCOUNTS_CACHE__
      );
      return;
    }

    console.log("✅ __ACCOUNTS_CACHE__ length:", __ACCOUNTS_CACHE__.length);

    const q = (document.getElementById("accountsSearch")?.value || "")
      .toLowerCase()
      .trim();
    const sort = document.getElementById("accountsSort")?.value || "newest";
    console.log("📊 Sort option selected:", sort);
    const status = document.getElementById("accountsStatusFilter")?.value || "";
    const role = document.getElementById("accountsRoleFilter")?.value || "";
    const rank = document.getElementById("accountsRankFilter")?.value || "";
    const designation =
      document.getElementById("accountsDesignationFilter")?.value || "";
    const alpha = document.getElementById("accountsAlphaFilter")?.value || "";

    let list = __ACCOUNTS_CACHE__.slice();
    console.log("📦 Before filters, users:", list);

    // Filters
    if (q)
      list = list.filter(
        (a) =>
          (a.full_name || "").toLowerCase().includes(q) ||
          (a.acc_user || "").toLowerCase().includes(q) ||
          (a.acc_email || "").toLowerCase().includes(q)
      );
    if (status) list = list.filter((a) => (a.inst_status || "") === status);
    if (role) list = list.filter((a) => String(a.role_id) === String(role));
    if (rank) list = list.filter((a) => (a.rank || "") === rank);
    if (designation) {
      list = list.filter((a) => {
        // Normalize designation: null, empty string, or 'None' all become 'None' for comparison
        const accDesignation =
          a.designation &&
          typeof a.designation === "string" &&
          a.designation.trim() !== "" &&
          a.designation !== "None"
            ? a.designation.trim()
            : "None";
        return accDesignation === designation;
      });
    }
    if (alpha === "A-M")
      list = list.filter((a) =>
        /^[a-mA-M]/.test(a.last_name || a.full_name || "")
      );
    if (alpha === "N-Z")
      list = list.filter((a) =>
        /^[n-zN-Z]/.test(a.last_name || a.full_name || "")
      );

    // Sorting
    list.sort((a, b) => {
      const aid = Number(a.acc_id || 0),
        bid = Number(b.acc_id || 0);
      if (sort === "newest") return bid - aid;
      if (sort === "oldest") return aid - bid;
      const an = (a.full_name || "").toLowerCase();
      const bn = (b.full_name || "").toLowerCase();
      if (sort === "name_asc") return an.localeCompare(bn);
      if (sort === "name_desc") return bn.localeCompare(an);
      if (sort === "rank_desc") {
        // Rank hierarchy: University Professor (highest) to Instructor I (lowest)
        const rankOrder = {
          "University Professor": 1,
          "Professor VI": 2,
          "Professor V": 3,
          "Professor IV": 4,
          "Professor III": 5,
          "Professor II": 6,
          "Professor I": 7,
          "Associate Professor V": 8,
          "Associate Professor IV": 9,
          "Associate Professor III": 10,
          "Associate Professor II": 11,
          "Associate Professor I": 12,
          "Assistant Professor IV": 13,
          "Assistant Professor III": 14,
          "Assistant Professor II": 15,
          "Assistant Professor I": 16,
          "Instructor III": 17,
          "Instructor II": 18,
          "Instructor I": 19,
        };
        const aRank = (a.rank || "").trim();
        const bRank = (b.rank || "").trim();
        const aOrder = rankOrder[aRank] || 999; // Users without rank go to the end
        const bOrder = rankOrder[bRank] || 999;

        console.log(
          `🔍 Sorting: ${a.full_name} (${aRank}, order: ${aOrder}) vs ${b.full_name} (${bRank}, order: ${bOrder})`
        );

        // If ranks are equal, sort by name as secondary sort
        if (aOrder === bOrder) {
          const an = (a.full_name || "").toLowerCase();
          const bn = (b.full_name || "").toLowerCase();
          return an.localeCompare(bn);
        }

        return aOrder - bOrder; // Lower number = higher rank, so ascending order
      }
      return 0;
    });

    console.log("📊 After filters, users:", list);
    console.log("📊 Sort applied:", sort);
    if (sort === "rank_desc") {
      console.log(
        "🎯 Rank sorting applied. Sample ranks:",
        list.slice(0, 5).map((u) => ({ name: u.full_name, rank: u.rank }))
      );
    }

    // Render results using the table-based function
    renderUsersTable(list);
  } catch (err) {
    console.error("❌ Error in applyAccountsFilters:", err);
  }
}

function renderUsersTable(users) {
  console.log("🔵 [DEBUG] ========== renderUsersTable ==========");
  console.log("🔵 [DEBUG] renderUsersTable called with users:", users);
  console.log(
    "🔵 [DEBUG] Users array length:",
    Array.isArray(users) ? users.length : "NOT AN ARRAY"
  );
  console.log("🔵 [DEBUG] Users array type:", typeof users);

  try {
    // Separate active and inactive users
    const activeUsers = users.filter(
      (account) => account.acc_status === "Active"
    );
    const inactiveUsers = users.filter(
      (account) =>
        account.acc_status === "Inactive" || account.acc_status === "Pending"
    );

    console.log("🔵 [DEBUG] Active users count:", activeUsers.length);
    console.log("🔵 [DEBUG] Inactive users count:", inactiveUsers.length);

    if (activeUsers.length > 0) {
      console.log("🔵 [DEBUG] Sample active user:", {
        acc_id: activeUsers[0].acc_id,
        full_name: activeUsers[0].full_name,
        role_name: activeUsers[0].role_name,
        role_id: activeUsers[0].role_id,
        inst_status: activeUsers[0].inst_status,
        acc_status: activeUsers[0].acc_status,
      });
    }

    // Render active users
    console.log(
      "🔵 [DEBUG] Calling renderActiveUsersTable with",
      activeUsers.length,
      "users"
    );
    renderActiveUsersTable(activeUsers);

    // Render inactive users
    console.log(
      "🔵 [DEBUG] Calling renderInactiveUsersTable with",
      inactiveUsers.length,
      "users"
    );
    renderInactiveUsersTable(inactiveUsers);

    console.log("🔵 [DEBUG] =====================================");
  } catch (err) {
    console.error("❌ [DEBUG] Error in renderUsersTable:", err);
    console.error("❌ [DEBUG] Error stack:", err.stack);
  }
}

function renderActiveUsersTable(users) {
  console.log("🔵 [DEBUG] ========== renderActiveUsersTable ==========");
  console.log(
    "🔵 [DEBUG] renderActiveUsersTable called with",
    users.length,
    "users"
  );

  try {
    const tableBody = document.getElementById("usersTableBody");
    const tableContainer = document.getElementById("usersTableContainer");

    console.log("🔵 [DEBUG] tableBody found:", !!tableBody);
    console.log("🔵 [DEBUG] tableContainer found:", !!tableContainer);

    if (!tableBody || !tableContainer) {
      console.error("❌ [DEBUG] Active table elements not found");
      console.error("❌ [DEBUG] tableBody:", tableBody);
      console.error("❌ [DEBUG] tableContainer:", tableContainer);
      return;
    }

    // Clear existing rows
    tableBody.innerHTML = "";
    console.log("🔵 [DEBUG] Cleared table body");

    if (!Array.isArray(users) || users.length === 0) {
      console.warn("⚠️ [DEBUG] No active users to render");
      console.warn("⚠️ [DEBUG] users is array:", Array.isArray(users));
      console.warn("⚠️ [DEBUG] users length:", users.length);
      showUsersEmptyState();
      return;
    }

    console.log("🔵 [DEBUG] Rendering", users.length, "user rows");

    // Add user rows
    let renderedCount = 0;
    users.forEach((account, index) => {
      console.log(`🔵 [DEBUG] Rendering user ${index + 1}/${users.length}:`, {
        acc_id: account.acc_id,
        full_name: account.full_name,
        role_name: account.role_name,
        role_id: account.role_id,
        inst_status: account.inst_status,
      });

      try {
        const tableRow = createUserTableRow(account);
        // Debug: Verify View button is in the row
        const viewButton = tableRow.querySelector(".btn-outline-info");
        if (!viewButton) {
          console.warn(
            `⚠️ [DEBUG] View button not found in row for: ${account.acc_id} (${account.full_name})`
          );
        }
        tableBody.appendChild(tableRow);
        renderedCount++;
      } catch (rowErr) {
        console.error(
          `❌ [DEBUG] Error rendering row for user ${account.acc_id}:`,
          rowErr
        );
      }
    });

    console.log(`🔵 [DEBUG] Successfully rendered ${renderedCount} rows`);

    // Show table and hide empty state
    tableContainer.style.display = "block";
    const emptyState = document.getElementById("usersEmptyState");
    if (emptyState) {
      emptyState.style.display = "none";
      console.log("🔵 [DEBUG] Hidden empty state");
    }

    console.log(`✅ [DEBUG] Rendered ${renderedCount} active users in table`);
    console.log("🔵 [DEBUG] ============================================");
  } catch (err) {
    console.error("❌ [DEBUG] Error in renderActiveUsersTable:", err);
    console.error("❌ [DEBUG] Error stack:", err.stack);
  }
}

function renderInactiveUsersTable(users) {
  try {
    const tableBody = document.getElementById("inactiveUsersTableBody");
    const tableContainer = document.getElementById(
      "inactiveUsersTableContainer"
    );
    const emptyState = document.getElementById("inactiveUsersEmptyState");

    if (!tableBody || !tableContainer) {
      console.error("❌ Inactive table elements not found");
      return;
    }

    // Clear existing rows
    tableBody.innerHTML = "";

    if (!Array.isArray(users) || users.length === 0) {
      console.warn("⚠️ No inactive users to render");
      if (tableContainer) tableContainer.style.display = "none";
      if (emptyState) emptyState.style.display = "block";
      return;
    }

    // Add user rows
    users.forEach((account) => {
      const tableRow = createUserTableRow(account);
      tableBody.appendChild(tableRow);
    });

    // Show table and hide empty state
    tableContainer.style.display = "block";
    if (emptyState) emptyState.style.display = "none";

    console.log(`✅ Rendered ${users.length} inactive users in table`);
  } catch (err) {
    console.error("❌ Error in renderInactiveUsersTable:", err);
  }
}

// Course Management Functions
// Prevent redeclaration error
if (typeof __COURSE_CACHE__ === 'undefined') {
    var __COURSE_CACHE__ = {};
}

function loadCourseData() {
  console.log("Loading course data...");

  fetch("../../admin/management/get_course_data.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        __COURSE_CACHE__ = data.data;
        updateCourseStatistics();
        populateCourseFilters();
        renderCourseTables();
        renderCampusHierarchy();
        console.log("✅ Course data loaded successfully");
      } else {
        console.error("❌ Failed to load course data:", data.message);
        showCourseError("Failed to load course data: " + data.message);
      }
    })
    .catch((error) => {
      console.error("❌ Error loading course data:", error);
      showCourseError("Error loading course data: " + error.message);
    });
}

function updateCourseStatistics() {
  const stats = __COURSE_CACHE__.statistics;

  const elements = {
    totalColleges: stats.total_colleges,
    totalDepartments: stats.total_departments,
    totalPrograms: stats.total_programs,
    totalSubjects: stats.total_subjects,
  };

  Object.entries(elements).forEach(([id, value]) => {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
  });
}

function saveCurriculum() {
  const form = document.getElementById("addCurriculumForm");
  const formData = new FormData(form);
  const btn = document.getElementById("saveCurriculumBtn");
  const original = btn.innerHTML;

  // Validate required fields
  const requiredFields = [
    "curriculum_name",
    "curriculum_code",
    "academic_year",
    "status",
    "duration",
  ];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      alert(`Please fill in the ${field.replace("_", " ")} field.`);
      return;
    }
  }

  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  // Simulate API call - replace with actual endpoint
  setTimeout(() => {
    try {
      // Mock success response
      const mockResponse = {
        success: true,
        message: "Curriculum added successfully!",
        data: {
          id: Math.floor(Math.random() * 1000),
          name: formData.get("curriculum_name"),
          code: formData.get("curriculum_code"),
          year: formData.get("academic_year"),
          status: formData.get("status"),
          duration: formData.get("duration"),
          units: formData.get("total_units"),
          description: formData.get("description"),
          objectives: formData.get("objectives"),
        },
      };

      if (mockResponse.success) {
        alert("Curriculum added successfully!");
        form.reset();
        safeHideModal("addCurriculumModal");

        // Refresh curriculum data
        loadCurriculumData();
      } else {
        throw new Error(mockResponse.message || "Failed to add curriculum");
      }
    } catch (error) {
      alert("Error: " + error.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  }, 1500);
}

function saveCurriculum() {
  const form = document.getElementById("addCurriculumForm");
  const formData = new FormData(form);
  const btn = document.getElementById("saveCurriculumBtn");
  const original = btn.innerHTML;

  // // Validate required fields
  // const requiredFields = ['curriculum_name', 'dept_id'];
  // for (let field of requiredFields) {
  //   if (!formData.get(field)) {
  //     alert(`Please fill in the ${field.replace('_', ' ')} field.`);
  //     return;
  //   }
  // }

  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  // Send data to backend
  fetch("../../admin/management/add_curriculum.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Show success message
        if (typeof Swal !== "undefined") {
          Swal.fire({
            title: "Success!",
            text: data.message || "Curriculum added successfully!",
            icon: "success",
            timer: 2000,
            showConfirmButton: false,
          });
        } else {
          alert(data.message || "Curriculum added successfully!");
        }

        // Reset form and close modal
        form.reset();
        safeHideModal("addCurriculumModal");

        // Refresh course data if course management tab is active
        const courseTab = document.getElementById("course_management");
        if (courseTab && courseTab.style.display !== "none") {
          if (typeof loadCourseData === "function") {
            loadCourseData();
          }
        }
        
        // Refresh curriculum data
        if (typeof loadCurriculumData === "function") {
          loadCurriculumData();
        }
        
        // Refresh subject tables to reflect curriculum changes
        if (typeof refreshAllSubjectTables === "function") {
          refreshAllSubjectTables();
        }
      } else {
        throw new Error(data.message || "Failed to add curriculum");
      }
    })
    .catch((error) => {
      console.error("Error adding curriculum:", error);
      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: "Error!",
          text: error.message,
          icon: "error",
        });
      } else {
        alert("Error: " + error.message);
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

function saveCurriculumFromModal(formId, modalId) {
  const form = document.getElementById(formId);
  if (!form) {
    console.error("Form not found:", formId);
    Swal.fire({
      icon: "error",
      title: "Form Error",
      text: "The form could not be found. Please refresh the page.",
    });
    return;
  }
  const formData = new FormData(form);
  const btn = form
    .closest(".modal-content")
    .querySelector(".js-save-curriculum-btn");
  const original = btn.innerHTML;

  // Frontend Validation - only require fields that exist in the form
  // Add default values for status (always active) and optional curr_type
  if (!formData.get("curr_status")) {
    formData.append("curr_status", "active");
  }
  
  const requiredFields = ["curr_name", "dept_id"];
  for (const fieldName of requiredFields) {
    if (!formData.get(fieldName)) {
      Swal.fire({
        icon: "warning",
        title: "Missing Information",
        text: `Please fill in the required field: ${fieldName.replace(
          /_/g,
          " "
        )}`,
        confirmButtonColor: "#800000",
      });
      return;
    }
  }

  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  fetch("../../admin/management/add_curriculum.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const modalInstance = bootstrap.Modal.getInstance(
          document.getElementById(modalId)
        );
        if (modalInstance) {
          modalInstance.hide();
        }

        // Forcefully remove the backdrop after a short delay to allow the modal to close
        setTimeout(() => {
          const backdrops = document.querySelectorAll(".modal-backdrop");
          backdrops.forEach((backdrop) => backdrop.remove());
          document.body.style.overflow = "auto"; // Restore body scrolling
        }, 500); // 500ms delay

        Swal.fire({
          icon: "success",
          title: "Success!",
          text: data.message,
          timer: 2000,
          showConfirmButton: false,
        });
        form.reset();
        // Reload the curriculum data to update the table
        if (typeof loadCurriculumData === "function") {
          loadCurriculumData();
        }
        // Refresh subject tables to reflect curriculum changes
        if (typeof refreshAllSubjectTables === "function") {
          refreshAllSubjectTables();
        }
      } else {
        throw new Error(data.message || "Failed to add curriculum.");
      }
    })
    .catch((error) =>
      Swal.fire({ icon: "error", title: "Oops...", text: error.message })
    )
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

/**
 * Populates a dropdown with data from an array of objects.
 * @param {HTMLSelectElement} dropdown - The select element to populate.
 * @param {Array} items - The array of objects to use for options.
 * @param {string} valueField - The property name for the option value.
 * @param {string} textField - The property name for the option text.
 * @param {string} placeholder - The text for the initial disabled option.
 */
function populateDropdown(dropdown, items, valueField, textField, placeholder) {
  dropdown.innerHTML = `<option value="">${placeholder}</option>`;
  items.forEach((item) => {
    const option = document.createElement("option");
    option.value = item[valueField];
    option.textContent = item[textField];
    dropdown.appendChild(option);
  });
}
/**
 * Handles the submission of the "Add Subject" form from the subject management page.
 * @param {string} formId - The ID of the form to submit.
 * @param {string} modalId - The ID of the modal to hide on success.
 */
function saveSubjectFromManagement(formId, modalId) {
  const form = document.getElementById(formId);
  if (!form) {
    console.error("Form not found:", formId);
    return;
  }
  const formData = new FormData(form);
  const saveButton = document.querySelector(`#${modalId} .btn-primary`);

  // Basic validation
  const requiredFields = [
    "subj_code",
    "subj_desc",
    "subj_unit",
    "subj_lvl",
    "subj_term",
    "subj_category",
    "program_id",
    "curr_id",
  ];
  for (let field of requiredFields) {
    if (!formData.get(field)) {
      Swal.fire(
        "Validation Error",
        `Please fill in the required field: ${field.replace(/_/g, " ")}`,
        "warning"
      );
      return;
    }
  }

  saveButton.disabled = true;
  saveButton.innerHTML =
    '<span class="spinner-border spinner-border-sm"></span> Saving...';

  fetch("../../admin/management/add_subject.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      // Parse JSON response regardless of status code
      return response
        .json()
        .then((data) => {
          // If response is not OK (e.g., 409 Conflict for duplicate), throw error with message
          if (!response.ok || !data.success) {
            throw new Error(data.message || "Failed to add subject.");
          }
          return data;
        })
        .catch((error) => {
          // If JSON parsing fails, check if it's a network error
          if (error instanceof SyntaxError) {
            return response.text().then((text) => {
              throw new Error(
                "Server returned invalid response. Please try again."
              );
            });
          }
          throw error;
        });
    })
    .then((data) => {
      // This will only execute if data.success is true

      // --- START: Sequential Modal & UI Update Logic ---

      // 1. Hide the modal first.
      return new Promise((resolve) => {
        const modalInstance = bootstrap.Modal.getInstance(
          document.getElementById(modalId)
        );
        if (modalInstance) {
          const modalElement = document.getElementById(modalId);
          modalElement.addEventListener(
            "hidden.bs.modal",
            () => {
              // Clean up any lingering backdrops and restore scrolling
              document
                .querySelectorAll(".modal-backdrop")
                .forEach((el) => el.remove());
              document.body.style.overflow = "auto";
              resolve(data.message);
            },
            { once: true }
          );
          modalInstance.hide();
        } else {
          resolve(data.message); // Modal not found, resolve immediately
        }
      });
    })
    .then((successMessage) => {
      // 2. Show the success message.
      return Swal.fire(
        "Success",
        successMessage || "Subject added successfully!",
        "success"
      );
    })
    .then(() => {
      // 3. After the success message is closed, refresh the tables.
      form.reset();
      const newSubjectData = {
        subj_category: formData.get("subj_category"),
        curr_id: formData.get("curr_id"),
        subj_term: formData.get("subj_term"),
        subj_lvl: formData.get("subj_lvl"),
        subj_code: formData.get("subj_code"),
        subj_desc: formData.get("subj_desc"),
      };

      const filteredTables = document.querySelectorAll(
        ".filtered-table-wrapper"
      );
      filteredTables.forEach((tableWrapper) => {
        const filters = JSON.parse(tableWrapper.dataset.filters || "{}");
        const search = (filters.search || "").toLowerCase();
        const categoryMatch =
          !filters.category ||
          filters.category === newSubjectData.subj_category;
        const curriculumMatch =
          !filters.curriculumId ||
          filters.curriculumId === newSubjectData.curr_id;
        const semesterMatch =
          !filters.semester || filters.semester === newSubjectData.subj_term;
        const yearLevelMatch =
          !filters.yearLevel || filters.yearLevel === newSubjectData.subj_lvl;
        const searchMatch =
          !search ||
          newSubjectData.subj_code.toLowerCase().includes(search) ||
          newSubjectData.subj_desc.toLowerCase().includes(search);

        if (
          categoryMatch &&
          curriculumMatch &&
          semesterMatch &&
          yearLevelMatch &&
          searchMatch
        ) {
          const tableId = tableWrapper.querySelector("table").id;
          if (tableId) {
            console.log(
              `New subject matches filters for table #${tableId}. Refreshing...`
            );
            $(`#${tableId}`).DataTable().ajax.reload();
          }
        }
      });
      // --- END: Sequential Modal & UI Update Logic ---
    })
    .catch((error) => {
      console.error("Save subject error:", error);
      // Show appropriate error message based on the error
      const errorMessage =
        error.message || "A network error occurred. Please try again.";
      Swal.fire({
        title: "Error",
        text: errorMessage,
        icon: "error",
        confirmButtonColor: "#800000",
        confirmButtonText: "OK",
      });
    })
    .finally(() => {
      saveButton.disabled = false;
      saveButton.innerHTML = "Save Subject";
    });
}

/**
 * Subject bar filters (program, curriculum, term, search). Year/category are layout-only.
 */
function getSubjectManagementFilters() {
  const programFilter = document.getElementById("subjectProgramFilter");
  return {
    programId: programFilter ? programFilter.value : "",
    curriculumId: document.getElementById("subjectCurriculumFilter")?.value || "",
    semester: document.getElementById("subjectSemesterFilter")?.value ?? "",
    search: (document.getElementById("subjectSearchInput")?.value || "").trim(),
  };
}

/**
 * Which term columns to show: all three, or one when a specific term is selected.
 */
function getSubjectTermSlices(semesterVal) {
  if (semesterVal === "" || semesterVal === null || semesterVal === undefined) {
    return [
      { value: "1", label: "1st Term" },
      { value: "2", label: "2nd Term" },
      { value: "3", label: "Summer" },
    ];
  }
  const labels = { 1: "1st Term", 2: "2nd Term", 3: "Summer" };
  const key = String(semesterVal);
  return [{ value: key, label: labels[key] || `Term ${key}` }];
}

/**
 * Renders the subjects data into the table.
 * @param {Array} subjects - An array of subject objects.
 * @param {object} [options] - termSectionEl: hide when empty; skipStatsUpdate: do not drive stats cards from this table
 */
function renderSubjectsTable(subjects, tableId, ajaxUrl, options = {}) {
  const { termSectionEl, skipStatsUpdate } = options;
  const tableSelector = $(`#${tableId}`);
  const tableElement = document.getElementById(tableId);

  // Check if table element exists in DOM
  if (!tableElement) {
    console.error(`Table element #${tableId} not found in DOM. Cannot initialize DataTable.`);
    return null;
  }

  if ($.fn.DataTable.isDataTable(tableSelector)) {
    tableSelector.DataTable().destroy();
  }

  const dataTable = tableSelector.DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: ajaxUrl,
      dataSrc: function (json) {
        // Handle error responses
        if (json && json.error) {
          console.error("API Error:", json.error);
          // Show error message
          Swal.fire({
            icon: "warning",
            title: "Access Denied",
            text: json.error || "You do not have permission to view subjects.",
            timer: 3000,
            showConfirmButton: false,
          });
          if (termSectionEl) termSectionEl.style.display = "none";
          // Return empty array so DataTables can still render
          return [];
        }

        if (termSectionEl) {
          const rf = parseInt(json.recordsFiltered, 10);
          const hasRows = Array.isArray(json.data) && json.data.length > 0;
          const show = (rf > 0) || hasRows;
          termSectionEl.style.display = show ? "" : "none";
        }

        if (json && json.statistics && !skipStatsUpdate) {
          updateSubjectStats(json.statistics, false);
        }

        // Return the data array for DataTables (now directly in json.data)
        return json.data || [];
      },
      error: function (xhr, error, thrown) {
        console.error("DataTables Ajax error:", error, thrown);
        console.error("Response:", xhr.responseText);
        console.error("Status:", xhr.status);

        // Try to parse error response
        let errorMessage =
          "Failed to load subjects. Please check your connection and try again.";
        let errorDetails = error;

        try {
          const errorResponse = JSON.parse(xhr.responseText);
          if (errorResponse.message) {
            errorMessage = errorResponse.message;
          } else if (errorResponse.error) {
            errorMessage = errorResponse.error;
          }
        } catch (e) {
          // Response is not JSON, use default message
        }

        // Show user-friendly error message
        Swal.fire({
          icon: "error",
          title: "Error Loading Subjects",
          text: errorMessage,
          footer:
            xhr.status === 403
              ? "Access denied. Please check your permissions."
              : xhr.status === 500
              ? "Server error. Please contact administrator."
              : error === "parsererror"
              ? "Invalid response format from server"
              : error,
        });
      },
    },
    columns: [
      {
        data: "subj_code",
        title: "Subject Code",
        render: (data) => `<strong>${escapeHtml(data)}</strong>`,
      },
      {
        data: "subj_desc",
        title: "Descriptive Title",
        render: (data) => escapeHtml(data),
      },
      { data: "subj_lec", title: "Lec", className: "text-center" },
      { data: "subj_lab", title: "Lab", className: "text-center" },
      { data: "subj_unit", title: "Units", className: "text-center" },
      {
        data: "subj_id",
        title: "Actions",
        orderable: false,
        render: (data, type, row) => `
                    <div class="d-flex gap-1 justify-content-center">
                        <button class="btn btn-sm btn-outline-info" onclick="viewSubject(${data})" title="View Subject">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="editSubject(${data})" title="Edit Subject">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSubject(${data})" title="Delete Subject">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>`,
      },
    ],
    responsive: true,
    paging: true,
    searching: false,
    language: {
      emptyTable:
        "No subjects for this term yet. Adjust program, curriculum, or search.",
    },
    footerCallback: function (row, data, start, end, display) {
      var api = this.api();

      // Helper function to sum a column
      const sumColumn = (colIndex) => {
        return api
          .column(colIndex, { page: "current" })
          .data()
          .reduce(function (a, b) {
            return Number(a) + Number(b);
          }, 0);
      };

      const lecTotal = sumColumn(2);
      const labTotal = sumColumn(3);
      const unitsTotal = sumColumn(4);

      $(api.column(2).footer()).html(lecTotal);
      $(api.column(3).footer()).html(labTotal);
      $(api.column(4).footer()).html(unitsTotal);
    },
  });

  console.log(`✅ DataTable initialized for #${tableId}`);
  return dataTable;
}

/**
 * Creates and adds a new filtered subject table to the page.
 * If program and curriculum are both selected, creates separate tables for each year level.
 * @param {boolean} silent - If true, don't show success alert (for auto-loading)
 */
function addFilteredSubjectTable(silent = false) {
  const container = document.getElementById("filteredSubjectsContainer");
  const placeholder = document.getElementById("subjectsPlaceholder");
  if (placeholder) {
    placeholder.style.display = "none";
  }

  // 1. Read filter values
  const programFilter = document.getElementById("subjectProgramFilter");
  const programId = programFilter ? programFilter.value : "";

  const filters = {
    ...getSubjectManagementFilters(),
    programId: programId,
  };

  // 2. If no program is selected, always show ALL programs grouped by program
  const showAllPrograms = !filters.programId;

  // 3. When program is not selected, group by program + year level
  if (showAllPrograms) {
    console.log("📊 No program selected - loading all programs with year-level subjects...");
    
    // Get API base path
    const path = window.location.pathname;
    let apiBasePath = "../../admin/management/";
    if (path.includes("/views/admin/")) {
      apiBasePath = "../../admin/management/";
    } else if (path.includes("/views/moderator/")) {
      apiBasePath = "../../admin/management/";
    }

    // Get department ID for filtering
    const deptId = window.currentDeptId || 0;
    const programsUrl = deptId > 0 
      ? `${apiBasePath}get_programs_by_department.php?dept_id=${deptId}`
      : `${apiBasePath}get_programs_by_department.php`;

    // Reset aggregated stats when starting to load all programs
    aggregatedSubjectStats = { total: 0, major: 0, minor: 0, gened: 0 };
    isAggregatingStats = true; // Enable aggregation mode
    
    // Load statistics for ALL subjects (no program filter) when showing all programs
    if (typeof loadSubjectStatistics === "function") {
      console.log("📊 Loading statistics for ALL subjects (all programs view)...");
      const pfAll = getSubjectManagementFilters();
      loadSubjectStatistics(null, {
        curriculumId: pfAll.curriculumId,
        term: pfAll.semester === "" ? "" : pfAll.semester,
        search: pfAll.search,
      });
    }

    // Fetch all programs for the department
    fetch(programsUrl)
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.programs && data.programs.length > 0) {
          console.log(`📊 Found ${data.programs.length} programs, creating tables for each...`);
          // Create year-level tables for each program
          data.programs.forEach((program) => {
            createProgramYearLevelTables(program, filters, container, silent);
          });
        } else {
          // No programs found, create default year-level tables without program filter
          console.log("⚠️ No programs found, creating default year-level tables...");
          createYearLevelTablesForProgram(null, filters, container, silent);
        }
      })
      .catch((error) => {
        console.error("Error loading programs:", error);
        // Fallback: create default year-level tables
        createYearLevelTablesForProgram(null, filters, container, silent);
      });
    return; // Exit early, tables will be created in the promise callbacks
  }

  // 4. A specific program is selected — reuse year × term layout (same as multi-program view)
  const selIdx = programFilter ? programFilter.selectedIndex : -1;
  const programLabel =
    programFilter && selIdx >= 0
      ? programFilter.options[selIdx]?.text || "Program"
      : "Program";
  const syntheticProgram = {
    program_id: filters.programId,
    program_name: programLabel,
    program_display: programLabel,
  };
  createYearLevelTablesForProgram(syntheticProgram, filters, container, true);
  if (!silent) {
    Swal.fire({
      icon: "success",
      title: "Tables loaded",
      text: "Subjects are grouped by year level and term.",
      timer: 2000,
      showConfirmButton: false,
    });
  }
}

/**
 * Creates year-level tables for a specific program (or all subjects if no program)
 */
function createYearLevelTablesForProgram(program, filters, container, silent) {
  // Create separate tables for each year level (1st year to 5th year)
  const yearLevels = [
    { value: "1", label: "1st Year" },
    { value: "2", label: "2nd Year" },
    { value: "3", label: "3rd Year" },
    { value: "4", label: "4th Year" },
    { value: "5", label: "5th Year" },
  ];

  // Create a wrapper for all year level tables
  // If container is a program wrapper, create year-level wrapper inside it
  // Otherwise, create standalone year-level wrapper
  const isProgramContainer = container && container.classList.contains('year-level-container');
  const yearLevelGroupWrapper = document.createElement("div");
  yearLevelGroupWrapper.className = "year-level-group-wrapper mb-4" + (isProgramContainer ? "" : " mb-5");
  const programFilters = program ? { ...filters, programId: program.program_id } : filters;
  yearLevelGroupWrapper.dataset.filters = JSON.stringify(programFilters);

  // Build base filter description (without year level)
  const programFilter = document.getElementById("subjectProgramFilter");
  let programName = "";
  if (program) {
    programName = program.program_name || program.program_display || 
                  (program.program_code && program.program_name ? `${program.program_code} - ${program.program_name}` : 
                   program.program_code || "Unknown Program");
  } else if (filters.programId && programFilter) {
    programName = programFilter.options[programFilter.selectedIndex]?.text || "Selected";
  }
  
  let baseFilterDescription = [
    programName ? `Program: ${programName}` : null,
    filters.curriculumId
      ? `Curriculum: ${$("#subjectCurriculumFilter option:selected").text()}`
      : null,
    filters.semester !== "" && filters.semester !== undefined && filters.semester !== null
      ? `Term: ${$("#subjectSemesterFilter option:selected").text()}`
      : null,
    filters.search ? `Search: "${filters.search}"` : null,
  ]
    .filter(Boolean)
    .join(" | ");

  const programId = program ? program.program_id : filters.programId;

  // Only create header if not inside a program container (program container already has its own header)
  if (!isProgramContainer) {
    const groupHeader = document.createElement("div");
    groupHeader.className =
      "d-flex justify-content-between align-items-center mb-3 py-2 border-bottom";
    const officialBtn =
      programId && String(programId).trim() !== ""
        ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="openOfficialSubjectPrintForProgram(${parseInt(programId, 10)})" title="Official full list for this program (all year levels & terms in database)">
        <i class="bi bi-printer"></i> Official print
      </button>`
        : "";
    groupHeader.innerHTML = `
      <h5 class="mb-0 text-primary fw-bold">${
        baseFilterDescription || (programName ? `Program: ${programName}` : "Filtered Subjects")
      }</h5>
      <div class="d-flex gap-2 flex-shrink-0 align-items-center">
        ${officialBtn}
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.year-level-group-wrapper').remove()">
          <i class="bi bi-x-lg"></i> Remove All
        </button>
      </div>
    `;
    yearLevelGroupWrapper.appendChild(groupHeader);
  }

  // Create a table for each year level
  // First, fetch curriculum usage for each year level if program is selected and no curriculum filter
  let curriculumUsagePromise = Promise.resolve({});

  if (programId && !filters.curriculumId) {
    // Fetch curriculum usage for each year level to show which curriculum each uses
    console.log(`📚 Fetching curriculum usage for program ID: ${programId}`);
    curriculumUsagePromise = fetch(
      `../../admin/management/get_curriculum_usage_by_year_level.php?program_id=${programId}`
    )
      .then((response) => {
        if (!response.ok) {
          console.error(`❌ Failed to fetch curriculum usage: HTTP ${response.status}`);
          return {};
        }
        return response.json();
      })
      .then((data) => {
        console.log(`📚 Curriculum usage response for program ${programId}:`, data);
        if (data.success && data.data && data.data.usage) {
          console.log(`✅ Found curriculum usage:`, data.data.usage);
          return data.data.usage;
        } else {
          console.warn(`⚠️ No curriculum usage found for program ${programId}:`, data.message || 'No usage data');
          return {};
        }
      })
      .catch((error) => {
        console.error("❌ Error loading curriculum usage:", error);
        return {};
      });
  } else if (!programId) {
    // If no program selected, we can't show curriculum usage per year level
    // But we'll still create tables for each year level (they'll show "Not Set" for curriculum)
    console.log(
      "⚠️ No program selected - curriculum usage cannot be determined per year level"
    );
  }

  // Wait for curriculum usage, then create tables
  curriculumUsagePromise.then((curriculumUsage) => {
    console.log(`📚 Creating tables with curriculum usage:`, curriculumUsage);
    yearLevels.forEach((yearLevel) => {
      const yearSection = document.createElement("div");
      yearSection.className = "subject-year-section mb-3 pb-2 border-bottom border-secondary border-opacity-25";
      yearSection.dataset.yearLevel = yearLevel.value;
      yearSection.dataset.filters = JSON.stringify({
        ...programFilters,
        yearLevel: yearLevel.value,
      });

      const yearLevelUsage =
        curriculumUsage[parseInt(yearLevel.value)] || null;
      let curriculumName = "Not Set";
      let currIdToUse = filters.curriculumId;

      console.log(`📚 Year Level ${yearLevel.value} usage:`, yearLevelUsage);

      if (filters.curriculumId) {
        curriculumName =
          $("#subjectCurriculumFilter option:selected").text() ||
          "Selected Curriculum";
        currIdToUse = filters.curriculumId;
      } else if (yearLevelUsage && yearLevelUsage.curr_id) {
        if (
          yearLevelUsage.curriculum_name &&
          yearLevelUsage.curriculum_name.trim() !== "" &&
          !yearLevelUsage.curriculum_name.startsWith("Curriculum #")
        ) {
          curriculumName = yearLevelUsage.curriculum_name;
          currIdToUse = yearLevelUsage.curr_id;
          console.log(
            `✅ Using curriculum for Year ${yearLevel.value}: ${curriculumName} (ID: ${currIdToUse})`
          );
        } else if (yearLevelUsage.curr_id) {
          console.log(
            `🔄 Curriculum ID exists (${yearLevelUsage.curr_id}) but name is missing, attempting to fetch...`
          );
          const curriculumSelect = document.getElementById("subjectCurriculumFilter");
          if (curriculumSelect) {
            const option = curriculumSelect.querySelector(
              `option[value="${yearLevelUsage.curr_id}"]`
            );
            if (option && option.textContent.trim()) {
              curriculumName = option.textContent.trim();
              currIdToUse = yearLevelUsage.curr_id;
              console.log(`✅ Found curriculum name from dropdown: ${curriculumName}`);
            }
          }
          if (curriculumName === "Not Set" && yearLevelUsage.curr_id) {
            if (
              yearLevelUsage.curriculum_name &&
              yearLevelUsage.curriculum_name.startsWith("Curriculum")
            ) {
              curriculumName = yearLevelUsage.curriculum_name;
            } else {
              curriculumName = `Curriculum #${yearLevelUsage.curr_id}`;
            }
            currIdToUse = yearLevelUsage.curr_id;
            fetch(`../../admin/management/get_curricula.php`)
              .then((response) => response.json())
              .then((data) => {
                const curricula =
                  data && data.success && data.data && Array.isArray(data.data.curricula)
                    ? data.data.curricula
                    : [];
                if (curricula.length > 0) {
                  const curriculum = curricula.find((c) => c.curr_id == yearLevelUsage.curr_id);
                  if (curriculum && curriculum.curr_name && curriculum.curr_name.trim() !== "") {
                    const fetchedName = curriculum.curr_name;
                    const badgeElement = yearSection.querySelector(".subject-year-header .badge");
                    if (badgeElement) {
                      badgeElement.className = "badge bg-primary text-white ms-2";
                      badgeElement.textContent = fetchedName;
                      console.log(`✅ Updated curriculum badge to: ${fetchedName}`);
                    }
                  }
                }
              })
              .catch((error) => {
                console.error(
                  `❌ Error fetching curriculum name for ID ${yearLevelUsage.curr_id}:`,
                  error
                );
              });
          }
          currIdToUse = yearLevelUsage.curr_id;
        }
      } else {
        console.warn(
          `⚠️ No curriculum usage found for Year Level ${yearLevel.value} in program ${programId}`
        );
      }

      const curriculumBadge =
        curriculumName !== "Not Set"
          ? `<span class="badge bg-primary text-white ms-2">${curriculumName}</span>`
          : `<span class="badge bg-secondary text-white ms-2">Curriculum Not Mapped</span>`;

      yearSection.dataset.currIdSuggest = currIdToUse || "";

      const termSlices = getSubjectTermSlices(filters.semester);
      let termsHtml = "";
      termSlices.forEach((termInfo) => {
        const tid = `subj_y${yearLevel.value}_t${termInfo.value}_${Date.now()}_${Math.random()
          .toString(36)
          .substr(2, 7)}`;
        termsHtml += `
        <div class="subject-term-block mb-3 ps-2 border-start border-3 border-secondary" data-term="${termInfo.value}" data-year-level="${yearLevel.value}" data-table-id="${tid}">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <span class="small fw-semibold text-muted">${termInfo.label}</span>
          </div>
          <div class="table-responsive">
            <table id="${tid}" class="table table-hover table-sm" style="width:100%">
              <thead class="table-header-maroon">
                <tr>
                  <th class="text-white">Subject Code</th>
                  <th class="text-white">Descriptive Title</th>
                  <th class="text-center text-white">Lec</th>
                  <th class="text-center text-white">Lab</th>
                  <th class="text-center text-white">Units</th>
                  <th class="text-center text-white">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading…</td></tr>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <th colspan="2" class="text-end">Total:</th>
                  <th class="text-center"></th>
                  <th class="text-center"></th>
                  <th class="text-center"></th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>`;
      });

      yearSection.innerHTML = `
        <div class="subject-year-header d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h6 class="mb-0 text-white fw-semibold rounded px-2 py-1" style="background:#800000;">
            <i class="bi bi-calendar-event me-2"></i>${yearLevel.label} ${curriculumBadge}
          </h6>
        </div>
        ${termsHtml}
      `;

      yearLevelGroupWrapper.appendChild(yearSection);
    });

    container.appendChild(yearLevelGroupWrapper);

    setTimeout(() => {
      yearLevelGroupWrapper.querySelectorAll(".subject-term-block").forEach((termBlock) => {
        const tableId = termBlock.dataset.tableId;
        const yl = termBlock.dataset.yearLevel;
        const term = termBlock.dataset.term;
        if (!tableId || !yl || !term) return;

        const yearLevelUsage = curriculumUsage[parseInt(yl, 10)] || null;
        let currIdToUse = filters.curriculumId;
        if (filters.curriculumId) {
          currIdToUse = filters.curriculumId;
        } else if (yearLevelUsage && yearLevelUsage.curr_id) {
          currIdToUse = yearLevelUsage.curr_id;
        }

        const params = new URLSearchParams({
          program_id: programId || "",
          curr_id: currIdToUse || "",
          term: term,
          level: yl,
          search: filters.search,
        });
        const ajaxUrl = `../../admin/management/get_subjects.php?${params.toString()}`;
        console.log(`📊 Initializing table ${tableId} (year ${yl}, term ${term}): ${ajaxUrl}`);
        try {
          renderSubjectsTable([], tableId, ajaxUrl, {
            termSectionEl: termBlock,
            skipStatsUpdate: true,
          });
        } catch (error) {
          console.error(`Error initializing table ${tableId}:`, error);
        }
      });
      if (typeof loadSubjectStatistics === "function") {
        const pf = getSubjectManagementFilters();
        const pid =
          programId || programFilters.programId || pf.programId || null;
        loadSubjectStatistics(pid, {
          curriculumId: pf.curriculumId,
          term: pf.semester === "" ? "" : pf.semester,
          search: pf.search,
        });
      }
    }, 150);
  });

  // Show confirmation alert (only if not silent and not showing all programs)
  if (!silent && program) {
    Swal.fire({
      icon: "success",
      title: "Tables Added!",
      text: `Created separate tables for each year level (1st - 5th Year).`,
      timer: 2000,
      showConfirmButton: false,
    });
  }
}

/**
 * Creates year-level tables for a specific program when showing all programs
 * Groups subjects by program, then by year level within each program
 */
function createProgramYearLevelTables(program, filters, container, silent) {
  // Create filters with this specific program
  const programFilters = {
    ...filters,
    programId: program.program_id
  };
  
  // Create a program-level wrapper to visually group all year-level tables for this program
  const programWrapper = document.createElement("div");
  programWrapper.className = "program-group-wrapper mb-4 pb-3 border-bottom border-primary border-opacity-25";
  programWrapper.dataset.programId = program.program_id;
  programWrapper.dataset.programName = program.program_name || program.program_code || "Unknown Program";
  
  // Create a header for the program group
  const programHeader = document.createElement("div");
  programHeader.className = "d-flex justify-content-between align-items-center mb-3 p-3 bg-maroon text-white rounded";
  const programDisplayName = program.program_display || 
                             (program.program_code && program.program_name ? `${program.program_code} - ${program.program_name}` : 
                              program.program_name || program.program_code || "Unknown Program");
  programHeader.innerHTML = `
    <h4 class="mb-0 fw-bold text-white">
      <i class="bi bi-mortarboard-fill me-2"></i>${programDisplayName}
    </h4>
    <div class="d-flex gap-2 flex-shrink-0 align-items-center">
      <button type="button" class="btn btn-sm btn-outline-light" onclick="openOfficialSubjectPrintForProgram(this.closest('.program-group-wrapper').dataset.programId)" title="Official full subject list for this program (opens printable page)">
        <i class="bi bi-printer"></i> Print program
      </button>
      <button type="button" class="btn btn-sm btn-light" onclick="this.closest('.program-group-wrapper').remove()">
        <i class="bi bi-x-lg"></i> Remove
      </button>
    </div>
  `;
  programWrapper.appendChild(programHeader);
  
  // Create a container for year-level tables within this program
  const yearLevelContainer = document.createElement("div");
  yearLevelContainer.className = "year-level-container";
  programWrapper.appendChild(yearLevelContainer);
  
  // Append program wrapper to main container first
  container.appendChild(programWrapper);
  
  // Now create year-level tables within the program wrapper
  createYearLevelTablesForProgram(program, programFilters, yearLevelContainer, true); // Always silent when showing all programs
}

/**
 * Sets up automatic filter updates for subject tables
 * When filters change, automatically updates the existing table
 */
let subjectFilterListenersSetup = false;
let subjectFilterSetupAttempts = 0;
const MAX_SETUP_ATTEMPTS = 10;

function setupSubjectFilterAutoUpdate() {
  // Prevent duplicate event listeners if already successfully set up
  if (subjectFilterListenersSetup) {
    return;
  }

  // Get all filter elements (year/category are shown in tables, not as dropdown filters)
  const programFilter = document.getElementById("subjectProgramFilter");
  const curriculumFilter = document.getElementById("subjectCurriculumFilter");
  const semesterFilter = document.getElementById("subjectSemesterFilter");
  const searchInput = document.getElementById("subjectSearchInput");

  // Check if required elements exist
  if (!curriculumFilter || !semesterFilter || !searchInput) {
    // Filters not available yet - retry if we haven't exceeded max attempts
    subjectFilterSetupAttempts++;
    if (subjectFilterSetupAttempts < MAX_SETUP_ATTEMPTS) {
      console.log(
        `Subject filters not ready yet, retrying... (attempt ${subjectFilterSetupAttempts}/${MAX_SETUP_ATTEMPTS})`
      );
      setTimeout(() => {
        setupSubjectFilterAutoUpdate();
      }, 200);
    } else {
      console.warn(
        "Failed to setup subject filters after max attempts. Some filter elements may be missing."
      );
    }
    return;
  }

  // Reset attempts counter on success
  subjectFilterSetupAttempts = 0;

  // Mark as setup
  subjectFilterListenersSetup = true;

  // Load programs for the program filter dropdown
  if (programFilter) {
    loadProgramsForSubjectFilter();
  }

  // Function to update the main table when filters change
  const updateSubjectTable = () => {
    console.log("🔄 updateSubjectTable called - filter change detected");

    const container = document.getElementById("filteredSubjectsContainer");
    if (!container) {
      console.warn("❌ filteredSubjectsContainer not found");
      return;
    }

    const filters = {
      ...getSubjectManagementFilters(),
      programId: programFilter ? programFilter.value : "",
    };

    console.log("📊 Current filter values:", filters);

    const statsOpts = {
      curriculumId: filters.curriculumId || "",
      term: filters.semester === "" ? "" : filters.semester,
      search: (filters.search || "").trim(),
    };
    if (typeof loadSubjectStatistics === "function") {
      loadSubjectStatistics(filters.programId || null, statsOpts);
    }

    // addFilteredSubjectTable creates grouped tables (program-group-wrapper > year-level-group-wrapper)
    // So we should always look for grouped tables, not single tables
    const programGroupWrapper = container.querySelector(".program-group-wrapper");
    const programGroupWrappers = container.querySelectorAll(".program-group-wrapper");
    const yearLevelGroupWrapper = container.querySelector(
      ".year-level-group-wrapper:not(.program-group-wrapper .year-level-group-wrapper)"
    );
    const mainTableWrapper = container.querySelector(
      ".filtered-table-wrapper:not(.year-level-group-wrapper .filtered-table-wrapper):not(.program-group-wrapper .filtered-table-wrapper)"
    );

    // If no tables exist at all, create them (always creates grouped tables by program)
    if (!programGroupWrapper && !yearLevelGroupWrapper && !mainTableWrapper) {
      console.log("⚠️ No tables found, creating new grouped tables by program...");
      if (typeof addFilteredSubjectTable === "function") {
        addFilteredSubjectTable(true); // silent mode for auto-updates
      }
      return;
    }

    // If we have an old single table but no grouped wrapper, remove it and create grouped tables
    if (mainTableWrapper && !programGroupWrapper && !yearLevelGroupWrapper) {
      console.log("⚠️ Found old single table, converting to grouped tables by program...");
      mainTableWrapper.remove();
      if (typeof addFilteredSubjectTable === "function") {
        addFilteredSubjectTable(true); // silent mode for auto-updates
      }
      return;
    }

    // If no program is selected, ensure we are grouped by program
    if (!filters.programId) {
      if (programGroupWrappers.length === 0) {
        // We have year-level or single tables, but no program grouping
        if (yearLevelGroupWrapper) yearLevelGroupWrapper.remove();
        if (mainTableWrapper) mainTableWrapper.remove();
        if (typeof addFilteredSubjectTable === "function") {
          addFilteredSubjectTable(true); // silent mode for auto-updates
        }
        return;
      }

      // Recreate grouped-by-program tables on any filter change
      console.log("🔄 No program selected - recreating grouped-by-program tables...");
      programGroupWrappers.forEach((wrapper) => wrapper.remove());
      if (yearLevelGroupWrapper) yearLevelGroupWrapper.remove();
      if (mainTableWrapper) mainTableWrapper.remove();
      if (typeof addFilteredSubjectTable === "function") {
        addFilteredSubjectTable(true); // silent mode for auto-updates
      }
      return;
    }

    // If grouped tables exist, update them with new filters
    // Check for program-group-wrapper first (newest structure), then year-level-group-wrapper
    if (programGroupWrapper || yearLevelGroupWrapper) {
      const wrapperForStored =
        yearLevelGroupWrapper ||
        container.querySelector(".year-level-group-wrapper");
      let storedSnap = {};
      if (wrapperForStored) {
        try {
          storedSnap = JSON.parse(wrapperForStored.dataset.filters || "{}");
        } catch (e) {
          storedSnap = {};
        }
      }
      const wasAllTerms =
        storedSnap.semester === "" ||
        storedSnap.semester === undefined ||
        storedSnap.semester === null;
      const nowAllTerms =
        filters.semester === "" ||
        filters.semester === undefined ||
        filters.semester === null;
      if (wasAllTerms !== nowAllTerms) {
        programGroupWrappers.forEach((w) => w.remove());
        if (yearLevelGroupWrapper) yearLevelGroupWrapper.remove();
        if (mainTableWrapper) mainTableWrapper.remove();
        if (typeof addFilteredSubjectTable === "function") {
          addFilteredSubjectTable(true);
        }
        return;
      }

      // If program is selected and no curriculum filter is set, recreate tables
      // to ensure correct curriculum per year level (since curriculum usage per year level depends on program)
      if (filters.programId && !filters.curriculumId) {
        // Check if the stored filters have a different program (program changed)
        let storedFilters = {};
        const wrapperToCheck = programGroupWrapper || yearLevelGroupWrapper;
        if (wrapperToCheck) {
          try {
            storedFilters = JSON.parse(
              wrapperToCheck.dataset.filters || "{}"
            );
          } catch (e) {
            // Ignore parse errors
          }
        }

        // If program changed or this is first time with program selected, recreate tables
        // Also recreate if we have program-group-wrapper but a program filter is now selected (should show single program)
        if (
          !storedFilters.programId ||
          storedFilters.programId !== filters.programId ||
          (programGroupWrapper && filters.programId) // If showing all programs but now a program is selected
        ) {
          console.log(
            "🔄 Program filter changed - recreating tables to show selected program..."
          );
          // Remove all wrappers to recreate
          if (programGroupWrapper) programGroupWrapper.remove();
          if (yearLevelGroupWrapper) yearLevelGroupWrapper.remove();
          if (typeof addFilteredSubjectTable === "function") {
            addFilteredSubjectTable(true); // silent mode for auto-updates
          }
          return;
        }
      }

      // Otherwise, update existing tables with new filters
      // First, fetch curriculum usage if program is selected and no curriculum filter
      // This is needed to determine which curriculum each year level uses
      let curriculumUsagePromise = Promise.resolve({});
      if (filters.programId && !filters.curriculumId) {
        curriculumUsagePromise = fetch(
          `../../admin/management/get_curriculum_usage_by_year_level.php?program_id=${filters.programId}`
        )
          .then((response) => response.json())
          .then((data) => {
            if (data.success && data.data && data.data.usage) {
              return data.data.usage;
            }
            return {};
          })
          .catch((error) => {
            console.error("Error loading curriculum usage:", error);
            return {};
          });
      }

      curriculumUsagePromise.then((curriculumUsage) => {
        // Find year-level-group-wrapper - could be inside program-group-wrapper or standalone
        const actualYearLevelWrapper = programGroupWrapper 
          ? programGroupWrapper.querySelector(".year-level-group-wrapper") || yearLevelGroupWrapper
          : yearLevelGroupWrapper;
        
        if (!actualYearLevelWrapper) {
          console.warn("⚠️ No year-level-group-wrapper found for updating tables");
          return;
        }
        
        const yearLevelLabels = {
          1: "1st Year",
          2: "2nd Year",
          3: "3rd Year",
          4: "4th Year",
          5: "5th Year",
        };

        actualYearLevelWrapper.querySelectorAll(".subject-year-section").forEach((yearSection) => {
          const yearLevel = yearSection.dataset.yearLevel;
          if (!yearLevel) return;

          let currIdToUse = filters.curriculumId;
          let curriculumName = "Not Set";

          if (filters.curriculumId) {
            curriculumName =
              curriculumFilter.options[curriculumFilter.selectedIndex]?.text ||
              "Selected Curriculum";
            currIdToUse = filters.curriculumId;
          } else if (curriculumUsage[parseInt(yearLevel, 10)]) {
            const yearLevelUsage = curriculumUsage[parseInt(yearLevel, 10)];
            curriculumName = yearLevelUsage.curriculum_name || "Not Set";
            currIdToUse = yearLevelUsage.curr_id || "";
          }

          const curriculumBadge =
            curriculumName !== "Not Set"
              ? `<span class="badge bg-primary text-white ms-2">${curriculumName}</span>`
              : `<span class="badge bg-secondary text-white ms-2">Curriculum Not Mapped</span>`;

          const headerH6 = yearSection.querySelector(".subject-year-header h6");
          if (headerH6) {
            headerH6.className = "mb-0 text-white fw-semibold rounded px-2 py-1";
            headerH6.style.background = "#800000";
            headerH6.innerHTML = `<i class="bi bi-calendar-event me-2"></i>${
              yearLevelLabels[yearLevel] || yearLevel + " Year"
            } ${curriculumBadge}`;
          }

          yearSection.dataset.filters = JSON.stringify({
            ...filters,
            yearLevel: yearLevel,
          });

          yearSection.querySelectorAll(".subject-term-block").forEach((termBlock) => {
            const tableId = termBlock.dataset.tableId;
            const term = termBlock.dataset.term;
            if (!tableId || !term) return;

            const params = new URLSearchParams({
              program_id: filters.programId || "",
              curr_id: currIdToUse || "",
              term: term,
              level: yearLevel,
              search: filters.search,
            });
            const ajaxUrl = `../../admin/management/get_subjects.php?${params.toString()}`;

            const table = $(`#${tableId}`);
            if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
              table.DataTable().ajax.url(ajaxUrl).load();
            } else {
              renderSubjectsTable([], tableId, ajaxUrl, {
                termSectionEl: termBlock,
                skipStatsUpdate: true,
              });
            }
          });
        });

        // Fallback: legacy single table wrappers (no year sections)
        actualYearLevelWrapper.querySelectorAll(".filtered-table-wrapper").forEach((tableWrapper) => {
          if (tableWrapper.closest(".subject-year-section")) return;
          const yearLevel = tableWrapper.dataset.yearLevel;
          const tableId =
            tableWrapper.dataset.tableId || tableWrapper.querySelector("table")?.id;
          if (!tableId || !yearLevel) return;
          let currIdToUse = filters.curriculumId;
          if (filters.curriculumId) {
            currIdToUse = filters.curriculumId;
          } else if (curriculumUsage[parseInt(yearLevel, 10)]) {
            currIdToUse = curriculumUsage[parseInt(yearLevel, 10)].curr_id || "";
          }
          const params = new URLSearchParams({
            program_id: filters.programId || "",
            curr_id: currIdToUse || "",
            term: filters.semester,
            level: yearLevel,
            search: filters.search,
          });
          const ajaxUrl = `../../admin/management/get_subjects.php?${params.toString()}`;
          const table = $(`#${tableId}`);
          if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
            table.DataTable().ajax.url(ajaxUrl).load();
          }
        });

        // Update group header (only if not inside program-group-wrapper, which has its own header)
        if (!programGroupWrapper && actualYearLevelWrapper) {
          const groupHeader = actualYearLevelWrapper.querySelector(".bg-light");
          if (groupHeader) {
            let baseFilterDescription = [
              filters.programId
                ? `Program: ${
                    programFilter.options[programFilter.selectedIndex]?.text ||
                    "Selected"
                  }`
                : null,
              filters.curriculumId
                ? `Curriculum: ${
                    curriculumFilter.options[curriculumFilter.selectedIndex]
                      ?.text || "Selected"
                  }`
                : null,
              filters.semester
                ? `Term: ${
                    semesterFilter.options[semesterFilter.selectedIndex]?.text ||
                    filters.semester
                  }`
                : null,
              filters.search ? `Search: "${filters.search}"` : null,
            ]
              .filter(Boolean)
              .join(" | ");

            const headerTitle = groupHeader.querySelector("h5");
            if (headerTitle) {
              headerTitle.textContent =
                baseFilterDescription || "Filtered Subjects";
            }
          }
        }

        // Update stored filters in group wrapper for change detection
        if (actualYearLevelWrapper) {
          actualYearLevelWrapper.dataset.filters = JSON.stringify(filters);
        }
      });

      return;
    }

    // If we reach here, there's no grouped wrapper - create tables
    console.log("⚠️ No grouped tables found, creating new tables...");
    if (typeof addFilteredSubjectTable === "function") {
      addFilteredSubjectTable(true); // silent mode for auto-updates
    }
  };

  // Debounce function for search input
  let searchTimeout;
  const debouncedUpdate = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(updateSubjectTable, 500); // Wait 500ms after user stops typing
  };

  // Add event listeners to all filters with explicit logging
  const attachListener = (element, event, handler, name) => {
    try {
      if (element) {
        element.addEventListener(event, handler);
        console.log(
          `✅ ${name} event listener attached to`,
          element.id || element
        );
        return true;
      } else {
        console.warn(`⚠️ ${name} element not found`);
        return false;
      }
    } catch (e) {
      console.error(`❌ Failed to attach ${name} listener:`, e);
      return false;
    }
  };

  // Attach all listeners
  attachListener(programFilter, "change", updateSubjectTable, "Program filter");
  attachListener(
    curriculumFilter,
    "change",
    updateSubjectTable,
    "Curriculum filter"
  );
  attachListener(
    semesterFilter,
    "change",
    updateSubjectTable,
    "Semester filter"
  );
  attachListener(searchInput, "input", debouncedUpdate, "Search input");

  // Also add a test listener to verify the element exists and can receive events
  if (categoryFilter) {
    categoryFilter.addEventListener("change", function (e) {
      console.log(
        "🧪 TEST: Category filter change event fired!",
        e.target.value
      );
    });
  }

  console.log("✅ Subject filter auto-update setup completed successfully");
  console.log("📋 Filter elements status:", {
    categoryFilter: !!categoryFilter,
    programFilter: !!programFilter,
    curriculumFilter: !!curriculumFilter,
    semesterFilter: !!semesterFilter,
    yearLevelFilter: !!yearLevelFilter,
    searchInput: !!searchInput,
  });
  
  // Listen for school year updates to refresh semester filter (only set up once)
  if (!window.subjectSemesterFilterListenerSetup) {
    document.addEventListener('schoolYearUpdated', function() {
      console.log('📅 School year updated event detected, updating semester filter...');
      updateSubjectSemesterFilter();
    });
    window.subjectSemesterFilterListenerSetup = true;
    console.log('✅ Subject semester filter event listener set up');
  }
}

/**
 * Updates the subject semester filter dropdown based on the active school year and semester
 */
function updateSubjectSemesterFilter() {
  const semesterFilter = document.getElementById('subjectSemesterFilter');
  if (!semesterFilter) {
    console.warn('⚠️ Subject semester filter not found');
    return;
  }
  
  // Get API base path
  const path = window.location.pathname;
  let apiBasePath = '../../shared/management/';
  if (path.includes('/views/admin/')) {
    apiBasePath = '../../shared/management/';
  } else if (path.includes('/views/moderator/')) {
    apiBasePath = '../../shared/management/';
  } else if (path.includes('/views/instructor/')) {
    apiBasePath = '../../shared/management/';
  }
  
  // Fetch current school year and semester
  fetch(apiBasePath + 'get_current_sy_semester.php')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.data && data.data.semester) {
        const semester = data.data.semester;
        let semesterValue = '';
        
        // Convert semester format to term ID: "1st Semester" -> 1, "2nd Semester" -> 2, "Mid-Year" -> 3 (Summer)
        if (semester === '1st Semester') {
          semesterValue = '1';
        } else if (semester === '2nd Semester') {
          semesterValue = '2';
        } else if (semester === 'Mid-Year') {
          semesterValue = '3'; // Mid/Summer
        }
        
        if (semesterValue) {
          // Update the dropdown value
          semesterFilter.value = semesterValue;
          console.log(`✅ Semester filter updated to: ${semesterFilter.options[semesterFilter.selectedIndex].text} (value: ${semesterValue})`);
          
          // Trigger change event to update the table if the filter auto-update is set up
          const changeEvent = new Event('change', { bubbles: true });
          semesterFilter.dispatchEvent(changeEvent);
        } else {
          console.warn('⚠️ Unknown semester format:', semester);
        }
      } else {
        console.warn('⚠️ No active semester found or invalid response:', data);
      }
    })
    .catch(error => {
      console.error('❌ Error updating subject semester filter:', error);
    });
}

/**
 * Loads programs for the subject program filter dropdown
 */
function loadProgramsForSubjectFilter() {
  const programFilter = document.getElementById("subjectProgramFilter");
  if (!programFilter) {
    return;
  }

  // Get API base path
  const path = window.location.pathname;
  let apiBasePath = "../../admin/management/";
  if (path.includes("/views/admin/")) {
    apiBasePath = "../../admin/management/";
  } else if (path.includes("/views/moderator/")) {
    apiBasePath = "../../admin/management/";
  } else if (path.includes("/views/instructor/")) {
    apiBasePath = "../../instructor/api/";
  }

  programFilter.innerHTML = '<option value="">Loading Programs...</option>';

  fetch(`${apiBasePath}get_schedule_form_data.php`)
    .then((response) => response.json())
    .then((data) => {
      programFilter.innerHTML = '<option value="">All Programs</option>';

      if (data.success && data.programs && data.programs.length > 0) {
        data.programs.forEach((program) => {
          const option = document.createElement("option");
          option.value = program.program_id;
          const displayText =
            program.program_display ||
            (program.program_code && program.program_name
              ? `${program.program_code} - ${program.program_name}`
              : program.program_name || program.program_code || "Unknown");
          option.textContent = displayText;
          programFilter.appendChild(option);
        });
      } else {
        const option = document.createElement("option");
        option.value = "";
        option.textContent = "No programs available";
        option.disabled = true;
        programFilter.appendChild(option);
      }
    })
    .catch((error) => {
      console.error("Error loading programs for subject filter:", error);
      programFilter.innerHTML =
        '<option value="">Error loading programs</option>';
    });
}


function buildOfficialSubjectPrintUrl(programId) {
  const params = new URLSearchParams();
  params.set("program_id", String(programId));
  const curr = document.getElementById("subjectCurriculumFilter");
  const term = document.getElementById("subjectSemesterFilter");
  const search = document.getElementById("subjectSearchInput");
  if (curr && curr.value) params.set("curr_id", curr.value);
  if (term && term.value !== "") params.set("term", term.value);
  const q = search && search.value ? search.value.trim() : "";
  if (q) params.set("search", q);
  return `../../admin/management/print_subjects_by_program.php?${params.toString()}`;
}

function openOfficialSubjectPrintForProgram(programId) {
  const id = parseInt(programId, 10);
  if (!id || Number.isNaN(id)) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "Program required",
        text: "Use «Print program» on each program when viewing all programs, or «Official print» on the subject list header when a program is selected.",
      });
    } else {
      alert("Choose a program first.");
    }
    return;
  }
  window.open(buildOfficialSubjectPrintUrl(id), "_blank", "noopener,noreferrer");
}

// --- Helper functions for renderSubjectsTable ---

function getYearLevelText(level) {
  const suffixes = ["st", "nd", "rd", "th", "th"];
  return `${level}${suffixes[level - 1] || "th"} Year`;
}

function getTermText(term) {
  const terms = { 1: "1st Term", 2: "2nd Term", 3: "Summer" };
  return terms[term] || "N/A";
}

function getCategoryBadge(category) {
  const colors = { Major: "danger", Minor: "warning", GENED: "info" };
  return colors[category] || "secondary";
}

// Placeholder for future edit/delete functionality
async function viewSubject(id) {
  try {
    // 1. Fetch the details for the specific subject
    const subjectDetailsResponse = await fetch(
      `../../admin/management/get_subject_details.php?id=${id}`
    );
    const subjectData = await subjectDetailsResponse.json();

    if (!subjectData.success) {
      throw new Error(
        subjectData.message || "Failed to fetch subject details."
      );
    }
    const subject = subjectData.data;

    // 2. Populate the view modal fields
    document.getElementById("view_subj_code").textContent =
      subject.subj_code || "-";
    document.getElementById("view_subj_desc").textContent =
      subject.subj_desc || "-";
    document.getElementById("view_subj_lec").textContent = subject.subj_lec;
    document.getElementById("view_subj_lab").textContent = subject.subj_lab;
    document.getElementById("view_subj_unit").textContent = subject.subj_unit;
    document.getElementById("view_subj_min").textContent = subject.subj_min;
    document.getElementById("view_subj_lvl").textContent = getYearLevelText(
      subject.subj_lvl
    );
    document.getElementById("view_subj_term").textContent = getTermText(
      subject.subj_term
    );
    const subjectProgramEl = document.querySelector(
      "#viewSubjectModal #view_program_name"
    );
    if (subjectProgramEl) {
      subjectProgramEl.textContent = subject.program_name || "N/A";
    }
    document.getElementById("view_curriculum_name").textContent =
      subject.curr_name || "N/A";

    // Handle category badge
    const categoryBadge = document.getElementById("view_subj_category");
    categoryBadge.textContent = subject.subj_category;
    categoryBadge.className = `badge bg-${getCategoryBadge(
      subject.subj_category
    )}`;

    // 3. Finally, show the modal
    const viewModal = new bootstrap.Modal(
      document.getElementById("viewSubjectModal")
    );
    viewModal.show();
  } catch (error) {
    console.error("View subject error:", error);
    Swal.fire("Error", error.message || "A network error occurred.", "error");
  }
}

async function editSubject(id) {
  try {
    // 1. Fetch the details for the specific subject
    const subjectDetailsResponse = await fetch(
      `../../admin/management/get_subject_details.php?id=${id}`
    );
    const subjectData = await subjectDetailsResponse.json();

    if (!subjectData.success) {
      throw new Error(
        subjectData.message || "Failed to fetch subject details."
      );
    }
    const subject = subjectData.data;

    // 2. Populate the static form fields first
    document.getElementById("edit_subj_id").value = subject.subj_id;
    document.getElementById("edit_subj_code").value = subject.subj_code;
    document.getElementById("edit_subj_desc").value = subject.subj_desc;
    document.getElementById("edit_subj_lec").value = subject.subj_lec;
    document.getElementById("edit_subj_lab").value = subject.subj_lab;
    document.getElementById("edit_subj_unit").value = subject.subj_unit;
    document.getElementById("edit_subj_min").value = subject.subj_min;
    document.getElementById("edit_subj_lvl").value = subject.subj_lvl;
    document.getElementById("edit_subj_term").value = subject.subj_term;
    document.getElementById("edit_subj_category").value = subject.subj_category;

    // 3. Fetch all programs and curricula to populate dropdowns
    const programDropdown = document.getElementById("edit_subject_program_id");
    const curriculumDropdown = document.getElementById("edit_subject_curr_id");

    const programsResponse = await fetch(
      `../../admin/management/get_programs_by_department.php`
    );
    const programsData = await programsResponse.json();
    if (programsData.success) {
      populateDropdown(
        programDropdown,
        programsData.programs,
        "program_id",
        "program_name",
        "Select Program"
      );
    }

    const curriculaResponse = await fetch(
      `../../admin/management/get_curricula.php?program_id=${encodeURIComponent(subject.program_id)}`
    );
    const curriculaData = await curriculaResponse.json();
    if (curriculaData.success) {
      const allCurricula = curriculaData.data && Array.isArray(curriculaData.data.curricula)
        ? curriculaData.data.curricula
        : [];
      const buildCurriculumText = (curriculum) => {
        if (curriculum.curr_yr && String(curriculum.curr_yr).trim() !== "") {
          return `${curriculum.curr_name} (${curriculum.curr_yr})`;
        }
        if (curriculum.curr_effective_start_year) {
          return `${curriculum.curr_name} (${curriculum.curr_effective_start_year})`;
        }
        return curriculum.curr_name;
      };

      const populateCurriculumByProgram = (selectedProgramId, selectedCurrId = "") => {
        const filteredCurricula = selectedProgramId
          ? allCurricula.filter((curriculum) =>
              !curriculum.program_id || String(curriculum.program_id) === String(selectedProgramId)
            )
          : allCurricula;

        curriculumDropdown.innerHTML = '<option value="">Select Curriculum</option>';
        filteredCurricula.forEach((curriculum) => {
          const option = document.createElement("option");
          option.value = curriculum.curr_id;
          option.textContent = buildCurriculumText(curriculum);
          curriculumDropdown.appendChild(option);
        });

        if (selectedCurrId) {
          curriculumDropdown.value = String(selectedCurrId);
          if (curriculumDropdown.value !== String(selectedCurrId)) {
            const fallbackOption = document.createElement("option");
            fallbackOption.value = String(selectedCurrId);
            fallbackOption.textContent = subject.curr_name || "Current Curriculum";
            curriculumDropdown.appendChild(fallbackOption);
            curriculumDropdown.value = String(selectedCurrId);
          }
        }
      };

      if (!programDropdown.dataset.curriculumSyncBound) {
        programDropdown.addEventListener("change", function () {
          populateCurriculumByProgram(this.value);
        });
        programDropdown.dataset.curriculumSyncBound = "true";
      }

      populateCurriculumByProgram(subject.program_id, subject.curr_id);
    }

    // 4. NOW set the selected values, after the dropdowns are populated
    programDropdown.value = subject.program_id;
    if (!curriculumDropdown.value) {
      curriculumDropdown.value = subject.curr_id;
    }

    // 5. Finally, show the modal
    const editModal = new bootstrap.Modal(
      document.getElementById("editSubjectModal")
    );
    editModal.show();
  } catch (error) {
    console.error("Edit subject error:", error);
    Swal.fire("Error", error.message || "A network error occurred.", "error");
  }
}

function updateSubject() {
  const form = document.getElementById("editSubjectForm");
  const formData = new FormData(form);
  const saveButton = document.querySelector("#editSubjectModal .btn-primary");

  saveButton.disabled = true;
  saveButton.innerHTML =
    '<span class="spinner-border spinner-border-sm"></span> Saving...';

  fetch("../../admin/management/update_subject.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", "Subject updated successfully!", "success");
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("editSubjectModal")
        );
        modal.hide();
        refreshAllSubjectTables(); // Refresh all subject tables
      } else {
        Swal.fire(
          "Error",
          data.message || "Failed to update subject.",
          "error"
        );
      }
    })
    .catch((error) => Swal.fire("Error", "A network error occurred.", "error"))
    .finally(() => {
      saveButton.disabled = false;
      saveButton.innerHTML = "Save Changes";
    });
}

function deleteSubject(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("subj_id", id);

      fetch("../../admin/management/delete_subject.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire(
              "Deleted!",
              data.message || "The subject has been deleted.",
              "success"
            );
            refreshAllSubjectTables(); // Refresh all subject tables
          } else {
            Swal.fire(
              "Error",
              data.message || "Failed to delete subject.",
              "error"
            );
          }
        })
        .catch((error) =>
          Swal.fire("Error", "A network error occurred.", "error")
        );
    }
  });
}

/**
 * Refreshes all subject DataTables in the filteredSubjectsContainer
 * This function is called after delete/edit operations to update the display
 * Also refreshes statistics and shows user feedback
 */
function refreshAllSubjectTables(forceRecreate = false) {
  console.log("🔄 Refreshing subject tables and statistics...", forceRecreate ? "(forcing recreation)" : "");

  // Find the refresh button to show loading state
  const refreshBtn = document.querySelector(
    'button[onclick*="refreshAllSubjectTables"]'
  );
  const originalBtnHtml = refreshBtn ? refreshBtn.innerHTML : "";

  // Show loading state on button
  if (refreshBtn) {
    refreshBtn.disabled = true;
    refreshBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing...';
  }

  const container = document.getElementById("filteredSubjectsContainer");
  if (!container) {
    console.warn(
      "⚠️ filteredSubjectsContainer not found, trying alternative method"
    );
    // If container doesn't exist, try to reload subjects using the old method
    if (typeof loadSubjects === "function") {
      loadSubjects();
    }

    // Restore button
    setTimeout(() => {
      if (refreshBtn) {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML = originalBtnHtml;
      }
    }, 500);

    // Show success message
    Swal.fire({
      icon: "success",
      title: "Refreshed",
      text: "Subject data has been refreshed.",
      timer: 1500,
      showConfirmButton: false,
    });
    return;
  }

  // If forceRecreate is true (e.g., after curriculum update), remove existing tables and recreate
  if (forceRecreate) {
    console.log("🔄 Force recreating subject tables (curriculum mapping changed)...");
    // Remove all wrapper types: program-group, year-level-group, or filtered-table
    const programGroupWrapper = container.querySelector('.program-group-wrapper');
    const yearLevelGroupWrapper = container.querySelector('.year-level-group-wrapper:not(.program-group-wrapper .year-level-group-wrapper)');
    const mainTableWrapper = container.querySelector('.filtered-table-wrapper:not(.year-level-group-wrapper .filtered-table-wrapper):not(.program-group-wrapper .filtered-table-wrapper)');
    
    if (programGroupWrapper) {
      programGroupWrapper.remove();
    }
    if (yearLevelGroupWrapper) {
      yearLevelGroupWrapper.remove();
    }
    if (mainTableWrapper) {
      mainTableWrapper.remove();
    }
    
    // Recreate tables with updated curriculum mapping
    if (typeof addFilteredSubjectTable === "function") {
      addFilteredSubjectTable(true); // silent mode
    } else if (typeof loadSubjects === "function") {
      loadSubjects();
    }
    
    // Restore button and show success
    setTimeout(() => {
      if (refreshBtn) {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML = originalBtnHtml;
      }
      
      Swal.fire({
        icon: "success",
        title: "Updated",
        text: "Subject tables have been updated with new curriculum.",
        timer: 1500,
        showConfirmButton: false,
      });
    }, 500);
    return;
  }

  // Tables: new layout uses [data-table-id] on term blocks; legacy uses .filtered-table-wrapper
  const tableWrappers = container.querySelectorAll(
    "[data-table-id], .filtered-table-wrapper, .year-level-group-wrapper .filtered-table-wrapper, .program-group-wrapper .filtered-table-wrapper"
  );

  if (tableWrappers.length === 0) {
    console.log("ℹ️ No subject tables found, creating a new one...");
    // No tables exist, try to load one
    if (typeof addFilteredSubjectTable === "function") {
      addFilteredSubjectTable(true); // silent mode
    } else if (typeof loadSubjects === "function") {
      loadSubjects();
    }

    const programFilter = document.getElementById("subjectProgramFilter");
    const pf = getSubjectManagementFilters();
    const isDefaultState =
      (!programFilter || !programFilter.value) &&
      !pf.curriculumId &&
      (pf.semester === "" || pf.semester === null || pf.semester === undefined) &&
      !pf.search;

    if (typeof loadSubjectStatistics === "function") {
      const statsOpts = {
        curriculumId: pf.curriculumId,
        term: pf.semester === "" ? "" : pf.semester,
        search: pf.search,
      };
      if (isDefaultState) {
        console.log("📊 Default state detected - loading statistics for ALL subjects...");
        loadSubjectStatistics(null, statsOpts);
      } else {
        const programId = programFilter && programFilter.value ? programFilter.value : null;
        console.log("📊 Filters applied - loading statistics for program:", programId);
        loadSubjectStatistics(programId, statsOpts);
      }
    }

    // Restore button
    setTimeout(() => {
      if (refreshBtn) {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML = originalBtnHtml;
      }
    }, 1000);

    // Show success message
    Swal.fire({
      icon: "success",
      title: "Refreshed",
      text: "Subject data has been refreshed.",
      timer: 1500,
      showConfirmButton: false,
    });
    return;
  }

  // Refresh each DataTable
  let refreshCount = 0;
  tableWrappers.forEach((wrapper) => {
    const tableId =
      wrapper.dataset.tableId || wrapper.querySelector("table")?.id;
    if (tableId) {
      const table = $(`#${tableId}`);
      if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
        // Reload the DataTable with current filters
        const dataTable = table.DataTable();
        dataTable.ajax.reload(null, false); // false = don't reset paging
        refreshCount++;
        console.log(`✅ Refreshed table: ${tableId}`);
      }
    }
  });

  const programFilter = document.getElementById("subjectProgramFilter");
  const programId =
    programFilter && programFilter.value ? programFilter.value : null;
  const pfR = getSubjectManagementFilters();
  if (typeof loadSubjectStatistics === "function") {
    loadSubjectStatistics(programId, {
      curriculumId: pfR.curriculumId,
      term: pfR.semester === "" ? "" : pfR.semester,
      search: pfR.search,
    });
  }

  console.log(`✅ Refreshed ${refreshCount} subject table(s) and statistics`);

  // Restore button after a short delay to ensure data is loaded
  setTimeout(() => {
    if (refreshBtn) {
      refreshBtn.disabled = false;
      refreshBtn.innerHTML = originalBtnHtml;
    }

    // Show success message
    Swal.fire({
      icon: "success",
      title: "Refreshed",
      text:
        refreshCount > 0
          ? `Refreshed ${refreshCount} table(s) and updated statistics.`
          : "Refreshed subject data and updated statistics.",
      timer: 2000,
      showConfirmButton: false,
    });
  }, 800);
}

// --- Subject Management Functions ---

let subjectCache = [];
let subjectSearchTimeout;

// Note: refreshAllSubjectTables() is defined above (line 11180) with forceRecreate parameter
// This duplicate definition has been removed to avoid conflicts

/**
 * Fetches subjects from the server with current filters and pagination.
 * @param {number} page - The page number to fetch.
 */
function loadSubjects(page = 1) {
  // This function is now effectively replaced by addFilteredSubjectTable
  const f = getSubjectManagementFilters();
  const curriculumId = document.getElementById("subjectCurriculumFilter")?.value || "";
  const params = new URLSearchParams({
    page,
    curr_id: curriculumId,
    term: f.semester,
    search: f.search,
    limit: 10, // You can adjust this value
  });

  fetch(`../../admin/management/get_subjects.php?${params.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      // API returns: { draw, recordsTotal, recordsFiltered, data, statistics }
      if (data && !data.error) {
        subjectCache = data.data || [];
        // Statistics are at the root level of the response, not inside data
        if (data.statistics) {
          updateSubjectStats(data.statistics);
        }
        // Note: pagination and curricula might not be in this response format
        // renderSubjectsPagination(data.data.pagination);
        // populateCurriculumFilter(data.data.curricula);
      } else {
        showSubjectsError(
          data.error || data.message || "Failed to load subjects"
        );
      }
    })
    .catch((error) => {
      console.error("Error loading subjects:", error);
      showSubjectsError("A network error occurred. Please try again.");
    })
    .finally(() => {
      // showSubjectsLoading(false);
    });
}

/**
 * Loads subject statistics independently from the API.
 * @param {string|number} programId - Optional program ID to filter statistics by program
 */
function loadSubjectStatistics(programId = null, opts = {}) {
  // Get API base path
  const path = window.location.pathname;
  let apiBasePath = "../../admin/management/";
  if (path.includes("/views/admin/")) {
    apiBasePath = "../../admin/management/";
  } else if (path.includes("/views/moderator/")) {
    apiBasePath = "../../admin/management/";
  }

  // Build query parameters - use minimal params to get ALL subjects statistics
  // When programId is null, we want ALL subjects from ALL programs
  const params = new URLSearchParams({
    draw: 1,
    start: 0,
    length: 0, // We only need statistics, not the actual data
  });

  // Only add program filter if explicitly provided
  // When null/undefined, don't add it so API returns all subjects
  if (programId && programId !== "" && programId !== "null") {
    params.append("program_id", programId);
    console.log(`📊 Loading statistics for program ID: ${programId}`);
  } else {
    console.log("📊 Loading statistics for ALL subjects (all programs)");
  }

  if (opts.curriculumId) {
    params.append("curr_id", opts.curriculumId);
  }
  if (opts.term !== undefined && opts.term !== null && opts.term !== "") {
    params.append("term", opts.term);
  }
  if (opts.search) {
    params.append("search", opts.search);
  }

  // Call the API with minimal parameters to get statistics
  const apiUrl = `${apiBasePath}get_subjects.php?${params.toString()}`;
  console.log(`📊 Fetching statistics from: ${apiUrl}`);
  
  fetch(apiUrl)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("📊 Statistics API response:", data);
      console.log("📊 Full API response:", JSON.stringify(data, null, 2));
      
      // Statistics are at the root level of the response, not inside data
      if (data && data.statistics) {
        console.log("📊 Updating stats with:", data.statistics);
        // When loadSubjectStatistics is called directly, always replace (not aggregate)
        // This is the authoritative source for total statistics
        isAggregatingStats = false; // Disable aggregation when loading directly
        aggregatedSubjectStats = { total: 0, major: 0, minor: 0, gened: 0 }; // Reset
        updateSubjectStats(data.statistics, false);
      } else {
        console.warn("⚠️ Statistics not found in response. Full response:", data);
        console.warn("⚠️ Response keys:", data ? Object.keys(data) : "No data");
        // Set default values
        updateSubjectStats({ total: 0, major: 0, minor: 0, gened: 0 }, false);
      }
    })
    .catch((error) => {
      console.error("Error loading subject statistics:", error);
      // Set default values on error
      updateSubjectStats({ total: 0, major: 0, minor: 0, gened: 0 });
    });
}

// Global variable to track aggregated statistics from all tables
let aggregatedSubjectStats = { total: 0, major: 0, minor: 0, gened: 0 };
let isAggregatingStats = false; // Flag to determine if we should aggregate or replace

/**
 * Updates the statistics cards with new data.
 * @param {Object} stats - Statistics object with total, major, minor, gened
 * @param {boolean} aggregate - If true, add to existing stats; if false, replace
 */
function updateSubjectStats(stats, aggregate = false) {
  console.log("📊 updateSubjectStats called with:", stats, "aggregate:", aggregate);
  const totalEl = document.getElementById("totalSubjectsCount");
  const majorEl = document.getElementById("majorSubjectsCount");
  const minorEl = document.getElementById("minorSubjectsCount");
  const genedEl = document.getElementById("genedSubjectsCount");

  console.log("📊 Stats elements found:", {
    totalEl: !!totalEl,
    majorEl: !!majorEl,
    minorEl: !!minorEl,
    genedEl: !!genedEl
  });

  if (!stats) {
    console.warn("⚠️ No statistics provided to updateSubjectStats");
    return;
  }
  
  // Validate stats object
  if (typeof stats !== 'object') {
    console.error("❌ Invalid stats object:", stats);
    return;
  }

  // If aggregating, add to existing stats; otherwise, replace
  if (aggregate) {
    aggregatedSubjectStats.total += (stats.total || 0);
    aggregatedSubjectStats.major += (stats.major || 0);
    aggregatedSubjectStats.minor += (stats.minor || 0);
    aggregatedSubjectStats.gened += (stats.gened || 0);
  } else {
    // Replace mode - reset aggregated stats first
    aggregatedSubjectStats = {
      total: stats.total || 0,
      major: stats.major || 0,
      minor: stats.minor || 0,
      gened: stats.gened || 0
    };
  }

  // Update DOM elements with the final stats
  const finalStats = aggregate ? aggregatedSubjectStats : stats;
  
  console.log("📊 Final stats to display:", finalStats);

  if (totalEl) {
    const totalValue = finalStats.total || 0;
    totalEl.textContent = totalValue;
    console.log("✅ Updated totalSubjectsCount:", totalValue);
  } else {
    console.warn("⚠️ totalSubjectsCount element not found in DOM");
  }

  if (majorEl) {
    const majorValue = finalStats.major || 0;
    majorEl.textContent = majorValue;
    console.log("✅ Updated majorSubjectsCount:", majorValue);
  } else {
    console.warn("⚠️ majorSubjectsCount element not found in DOM");
  }

  if (minorEl) {
    const minorValue = finalStats.minor || 0;
    minorEl.textContent = minorValue;
    console.log("✅ Updated minorSubjectsCount:", minorValue);
  } else {
    console.warn("⚠️ minorSubjectsCount element not found in DOM");
  }

  if (genedEl) {
    const genedValue = finalStats.gened || 0;
    genedEl.textContent = genedValue;
    console.log("✅ Updated genedSubjectsCount:", genedValue);
  } else {
    console.warn("⚠️ genedSubjectsCount element not found in DOM");
  }
  
  console.log("📊 Stats update complete. Final DOM values:", {
    total: totalEl ? totalEl.textContent : "N/A",
    major: majorEl ? majorEl.textContent : "N/A",
    minor: minorEl ? minorEl.textContent : "N/A",
    gened: genedEl ? genedEl.textContent : "N/A"
  });
}

/**
 * Populates the curriculum filter dropdown.
 */
function populateCurriculumFilter(curricula) {
  const filter = document.getElementById("subjectCurriculumFilter");
  if (filter.options.length > 1) return;

  curricula.forEach((curr) => {
    // Use curr_yr if available, otherwise fallback to curr_effective_start_year, or show nothing
    let yearDisplay = '';
    if (curr.curr_yr && curr.curr_yr.trim() !== '') {
      yearDisplay = ` (${curr.curr_yr})`;
    } else if (curr.curr_effective_start_year) {
      yearDisplay = ` (${curr.curr_effective_start_year})`;
    }
    const option = new Option(
      `${curr.curr_name}${yearDisplay}`,
      curr.curr_id
    );
    filter.add(option);
  });
}

/**
 * Debounced search function.
 */
function searchSubjects() {
  // This is no longer needed as we are using a button to trigger the filter.
  // clearTimeout(subjectSearchTimeout);
  // subjectSearchTimeout = setTimeout(() => {
  //     loadSubjects(1);
  // }, 300);
}

function clearSubjectFilters() {
  // This function is no longer needed with the new UI.
  // document.getElementById('subjectCategoryFilter').value = '';
  // document.getElementById('subjectCurriculumFilter').value = '';
  // document.getElementById('subjectSemesterFilter').value = '';
  // document.getElementById('subjectYearLevelFilter').value = '';
  // document.getElementById('subjectSearchInput').value = '';
  // loadSubjects(1);
}

function showSubjectsLoading(isLoading) {
  document.getElementById("subjectsLoadingState").style.display = isLoading
    ? "block"
    : "none";
  document.getElementById("subjectsTableContainer").style.display = isLoading
    ? "none"
    : "block";
  document.getElementById("subjectsErrorState").style.display = "none";
}

function showSubjectsError(message) {
  document.getElementById("subjectsLoadingState").style.display = "none";
  document.getElementById("subjectsTableContainer").style.display = "none";
  document.getElementById("subjectsErrorState").style.display = "block";
  document.getElementById("subjectsErrorMessage").textContent = message;
}

// --- Real-time Subject Code Validation ---

let subjectCodeCheckTimeout;

/**
 * Debounces the subject code validation to avoid excessive API calls.
 */
function checkSubjectCodeDebounced() {
  clearTimeout(subjectCodeCheckTimeout);
  subjectCodeCheckTimeout = setTimeout(checkSubjectCode, 500); // 500ms delay
}

/**
 * Performs the real-time validation of the subject code.
 */
async function checkSubjectCode() {
  const codeInput = document.getElementById("subj_code");
  const curriculumSelect = document.getElementById("add_subject_curr_id");
  const programSelect = document.getElementById("add_subject_program_id");
  const categorySelect = document.getElementById("subj_category");
  const validationDiv = document.getElementById("subjectCodeValidation");
  const saveButton = document.querySelector("#addSubjectModal .btn-primary");

  const code = codeInput.value.trim();
  const currId = curriculumSelect.value;
  const programId = programSelect ? programSelect.value : '';
  const category = categorySelect ? categorySelect.value : '';

  if (code === "" || currId === "") {
    validationDiv.innerHTML = "";
    return;
  }

  validationDiv.innerHTML = `<span class="text-muted">Checking...</span>`;

  try {
    let url = `../../admin/management/check_subject_code.php?subj_code=${encodeURIComponent(code)}&curr_id=${currId}`;
    if (programId) {
      url += `&program_id=${encodeURIComponent(programId)}`;
    }
    if (category) {
      url += `&subj_category=${encodeURIComponent(category)}`;
    }
    
    const response = await fetch(url);
    const data = await response.json();

    if (data.success && data.data.available) {
      validationDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill"></i> ${data.data.message}</span>`;
      saveButton.disabled = false;
    } else {
      validationDiv.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill"></i> ${data.data.message}</span>`;
      saveButton.disabled = true;
    }
  } catch (error) {
    console.error("Subject code validation error:", error);
    validationDiv.innerHTML = `<span class="text-warning">Could not validate code.</span>`;
    saveButton.disabled = false; // Allow submission if validation fails
  }
}

function showSubjectsError(message) {
  document.getElementById("subjectsLoadingState").style.display = "none";
  document.getElementById("subjectsTableContainer").style.display = "none";
  document.getElementById("subjectsErrorState").style.display = "block";
  document.getElementById("subjectsErrorMessage").textContent = message;
}

// --- Course Management Functions ---

// -- Render Table list Program Function --

// Prevent redeclaration error
if (typeof __PROGRAMS_CACHE__ === 'undefined') {
    var __PROGRAMS_CACHE__ = [];
}

function showProgramsLoading(show) {
  const loadingState = document.getElementById("programsLoadingState");
  const tableContainer = document.getElementById("programsTableContainer");
  const errorState = document.getElementById("subjectsErrorState"); // Corrected ID from your PHP

  if (loadingState) loadingState.style.display = show ? "block" : "none";
  if (tableContainer) tableContainer.style.display = show ? "none" : "block";
  if (errorState) errorState.style.display = "none"; // Always hide error state when loading
}

function showProgramsError(message) {
  const loadingState = document.getElementById("programsLoadingState");
  const tableContainer = document.getElementById("programsTableContainer");
  const errorState = document.getElementById("subjectsErrorState"); // Using existing error state
  const errorMessageEl = document.getElementById("subjectsErrorMessage");

  if (loadingState) loadingState.style.display = "none";
  if (tableContainer) tableContainer.style.display = "none";
  if (errorState) {
    errorState.style.display = "block";
    // Update error message to be program-specific
    const errorTitle = errorState.querySelector("h5");
    if (errorTitle) errorTitle.textContent = "Error Loading Programs";
  }
  if (errorMessageEl) errorMessageEl.textContent = message;
}

function refreshloadPrograms() {
  const refreshBtn = document.querySelector(
    'button[onclick="refreshloadPrograms()"]'
  );
  if (!refreshBtn) {
    loadProgramsData();
    return Promise.resolve();
  }

  const originalContent = refreshBtn.innerHTML;
  refreshBtn.disabled = true;
  refreshBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...';
  loadProgramsData();

  // Return a promise so we can wait for it to complete
  return new Promise((resolve) => {
    // Restore button after a delay
    setTimeout(() => {
      refreshBtn.disabled = false;
      refreshBtn.innerHTML = originalContent;
      resolve();
    }, 1500); // Simulate a delay for better UX
  });
}

function loadProgramsData() {
  console.log("Loading programs data...");
  showProgramsLoading(true);

  // Check if we should show all programs (for course management page)
  // Admin Support can see all programs, regular admins see their department only
  const showAll = window.isAdminSupport || false;
  const apiUrl = showAll
    ? "../../admin/management/get_programs.php?show_all=1"
    : "../../admin/management/get_programs.php";

  console.log("Fetching programs from:", apiUrl);
  console.log("Show all programs:", showAll);

  fetch(apiUrl)
    .then((response) => {
      if (response.status === 403) {
        showPermissionError("Course");
        throw new Error("Unauthorized");
      }
      if (!response.ok) {
        throw new Error("Network response was not ok: " + response.statusText);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Programs API Response:", data); // Debug log
      if (data.success) {
        // The data structure is { success: true, data: { programs: [...] } }
        __PROGRAMS_CACHE__ =
          data.data && data.data.programs ? data.data.programs : [];
        console.log("Programs loaded:", __PROGRAMS_CACHE__.length, "items");

        // Debug: Log first program's structure
        if (__PROGRAMS_CACHE__.length > 0) {
          const firstProg = __PROGRAMS_CACHE__[0];
          console.log("First program structure:", {
            program_id: firstProg.program_id,
            program_code: firstProg.program_code,
            program_name: firstProg.program_name,
            program_status: firstProg.program_status,
            all_keys: Object.keys(firstProg),
          });
        }

        // Debug: Log status values to check for case issues
        __PROGRAMS_CACHE__.forEach((prog) => {
          if (!prog.program_name || !prog.program_status) {
            console.warn(
              `Program ${prog.program_code} (ID: ${prog.program_id}) missing fields:`,
              {
                has_name: !!prog.program_name,
                has_status: !!prog.program_status,
                name_value: prog.program_name,
                status_value: prog.program_status,
              }
            );
          }
        });

        // Debug: Count programs by department
        const deptCounts = {};
        __PROGRAMS_CACHE__.forEach((prog) => {
          const dept = prog.dept_name || "Unknown";
          deptCounts[dept] = (deptCounts[dept] || 0) + 1;
        });
        console.log("Programs by Department:", deptCounts);

        // Always render the table, even if empty
        renderProgramsTable(__PROGRAMS_CACHE__);
        if (__PROGRAMS_CACHE__.length > 0) {
          console.log(
            "✅ Programs data loaded successfully:",
            __PROGRAMS_CACHE__.length,
            "programs"
          );
        } else {
          console.warn("⚠️ No programs found in response");
          // Show empty state in the table
        }
      } else {
        throw new Error(data.message || "Failed to load programs data.");
      }
    })
    .catch((error) => {
      console.error("❌ Error loading programs data:", error);
      if (
        error.message === "Unauthorized" ||
        error.message.includes("Unauthorized")
      ) {
        // Permission error already shown by showPermissionError
        showProgramsLoading(false);
      } else {
        showProgramsError(error.message);
      }
    })
    .finally(() => {
      showProgramsLoading(false);
    });
}

function renderProgramsTable(programs) {
  console.log(
    "Rendering programs table with",
    programs ? programs.length : 0,
    "items"
  );
  const tableId = "#programsTable";

  // Ensure programs is an array
  if (!Array.isArray(programs)) {
    console.warn("programs is not an array:", typeof programs, programs);
    programs = [];
  }

  // Destroy existing DataTable instance if it exists
  if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableId)) {
    $(tableId).DataTable().destroy();
  }

  // Clear the tbody first
  const tbody = document.querySelector("#programsTable tbody");
  if (tbody) {
    tbody.innerHTML = "";
  }

  // Show table container and hide error state
  const tableContainer = document.getElementById("programsTableContainer");
  const errorState = document.getElementById("subjectsErrorState"); // Note: ID might be wrong but checking
  if (tableContainer) {
    tableContainer.style.display = "block";
  }
  if (errorState) {
    errorState.style.display = "none";
  }

  // Check if DataTables is available
  if (typeof $.fn.DataTable === "undefined") {
    console.error("DataTables is not loaded!");
    // Fallback: render manually
    if (tbody && programs.length > 0) {
      programs.forEach((program) => {
        const row = document.createElement("tr");
        row.innerHTML = `
                    <td><strong>${escapeHtml(
                      program.program_code || ""
                    )}</strong></td>
                    <td>${escapeHtml(program.program_name || "")}</td>
                    <td>${escapeHtml(program.dept_name || "N/A")}</td>
                    <td>${escapeHtml(program.program_desc || "N/A")}</td>
                    <td><span class="badge bg-${
                      program.program_status &&
                      program.program_status.toLowerCase() === "active"
                        ? "success"
                        : "secondary"
                    }">${escapeHtml(
          program.program_status || "N/A"
        )}</span></td>
                    <td>${
                      program.created_at
                        ? new Date(program.created_at).toLocaleDateString()
                        : "N/A"
                    }</td>
                    <td>${
                      program.updated_at
                        ? new Date(program.updated_at).toLocaleDateString()
                        : "N/A"
                    }</td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="viewProgram(${
                              program.program_id
                            })" title="View Program">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="editProgram(${
                              program.program_id
                            })" title="Edit Program">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(${
                              program.program_id
                            })" title="Delete Program">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
        tbody.appendChild(row);
      });
    }
    return;
  }

  // Initialize DataTables
  try {
    // Destroy existing DataTable instance if it exists
    if ($.fn.DataTable.isDataTable(tableId)) {
      $(tableId).DataTable().destroy();
    }

    // Clear tbody but keep thead
    const tbody = document.querySelector("#programsTable tbody");
    if (tbody) {
      tbody.innerHTML = "";
    }

    // Debug: Log first program to check data structure
    if (programs && programs.length > 0) {
      console.log("Sample program data:", programs[0]);
      console.log(
        "Program name:",
        programs[0].program_name,
        "Type:",
        typeof programs[0].program_name
      );
      console.log(
        "Program status:",
        programs[0].program_status,
        "Type:",
        typeof programs[0].program_status
      );
      console.log("All program fields:", Object.keys(programs[0]));

      // Check if fields are actually present
      programs.forEach((prog, index) => {
        if (!prog.program_name || !prog.program_status) {
          console.warn(
            `Program ${index} (ID: ${prog.program_id}) missing fields:`,
            {
              has_name: !!prog.program_name,
              has_status: !!prog.program_status,
              program_code: prog.program_code,
              all_fields: Object.keys(prog),
            }
          );
        }
      });
    }

    // Ensure we have valid data
    const validPrograms = (programs || []).map((prog) => {
      // Ensure program_name and program_status are present
      return {
        ...prog,
        program_name: prog.program_name || prog.program_code || "N/A",
        program_status: prog.program_status || "Active",
      };
    });

    $(tableId).DataTable({
      data: validPrograms,
      columns: [
        {
          data: "program_code",
          render: (data) => `<strong>${escapeHtml(data || "")}</strong>`,
        },
        {
          data: "program_name",
          render: (data, type, row) => {
            // Use row object if data is empty (fallback)
            const name = data || (row && row.program_name) || "N/A";
            return escapeHtml(name);
          },
        },
        {
          data: "dept_name",
          render: (data) => escapeHtml(data || "N/A"),
        },
        {
          data: "program_years",
          render: (data) => {
            const years = data || 0;
            return years > 0
              ? `${years} ${years === 1 ? "Year" : "Years"}`
              : "N/A";
          },
        },
        {
          data: "major_track",
          render: (data) => {
            const track = data ? escapeHtml(data.trim()) : "";
            return track || "-";
          },
        },
        {
          data: "total_units_required",
          render: (data) => {
            const units = data || 0;
            return units > 0 ? units.toString() : "-";
          },
        },
        {
          data: "program_status",
          render: (data, type, row) => {
            // Use row object if data is empty (fallback)
            const status = data || (row && row.program_status) || "Active";
            const isActive = status.toLowerCase() === "active";
            return `<span class="badge bg-${
              isActive ? "success" : "secondary"
            }">${escapeHtml(status || "N/A")}</span>`;
          },
        },
        {
          data: "created_at",
          render: (data) =>
            data ? new Date(data).toLocaleDateString() : "N/A",
        },
        {
          data: "updated_at",
          render: (data) =>
            data ? new Date(data).toLocaleDateString() : "N/A",
        },
        {
          data: "program_id",
          orderable: false,
          render: (data) => `
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="viewProgram(${data})" title="View Program">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="editProgram(${data})" title="Edit Program">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(${data})" title="Delete Program">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>`,
        },
      ],
      responsive: true,
      language: {
        emptyTable:
          "No programs found. Click refresh to load or add a new one.",
      },
      pageLength: 25,
      order: [[1, "asc"]], // Sort by program name by default
    });
    console.log("✅ DataTable initialized successfully");
  } catch (error) {
    console.error("❌ Error initializing DataTable:", error);
    showProgramsError("Failed to render table: " + error.message);
  }
}

function openAddProgram() {
  const addmodal = new bootstrap.Modal(
    document.getElementById("addProgramFormModal")
  );
  addmodal.show();
}

// -- Save Program Function --
function saveProgramFromModal(formId, modalId) {
  const form = document.getElementById(formId);
  if (!form) {
    console.error("Form not found:", formId);
    Swal.fire(
      "Error",
      "The form could not be found. Please refresh the page.",
      "error"
    );
    return;
  }

  const formData = new FormData(form);
  const btn = form.closest(".modal-content").querySelector(".btn-primary");
  const original = btn.innerHTML;

  // Basic Frontend Validation
  const requiredFields = ["program_code", "program_name", "dept_id"];
  for (const fieldName of requiredFields) {
    if (!formData.get(fieldName)) {
      Swal.fire({
        icon: "warning",
        title: "Missing Information",
        text: `Please fill in the required field: ${fieldName.replace(
          /_/g,
          " "
        )}`,
        confirmButtonColor: "#800000",
      });
      return;
    }
  }

  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  fetch("../../admin/management/add_program.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Success!",
          text: data.message || "Program added successfully!",
          timer: 2000,
          showConfirmButton: false,
        });

        const modal = bootstrap.Modal.getInstance(
          document.getElementById(modalId)
        );
        if (modal) {
          modal.hide();
        }
        form.reset();

        // Refresh course data if the tab is active
        if (
          typeof loadProgramsData === "function" &&
          document.getElementById("course_management")?.style.display !== "none"
        ) {
          loadProgramsData();
        }
      } else {
        throw new Error(data.message || "Failed to add program.");
      }
    })
    .catch((error) =>
      Swal.fire({ icon: "error", title: "Oops...", text: error.message })
    )
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    });
}

function viewProgram(programId) {
  // Try to find program in cache first
  let program = __PROGRAMS_CACHE__.find(
    (p) => String(p.program_id) === String(programId)
  );

  // If not found in cache, fetch fresh data
  if (!program) {
    console.log(
      "Program not found in cache, fetching fresh data for program ID:",
      programId
    );
    fetch(`../../admin/management/get_programs.php`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.data && data.data.programs) {
          __PROGRAMS_CACHE__ = data.data.programs;
          program = __PROGRAMS_CACHE__.find(
            (p) => String(p.program_id) === String(programId)
          );
          if (program) {
            populateViewModal(program);
          } else {
            Swal.fire("Error", "Program not found.", "error");
          }
        } else {
          Swal.fire("Error", "Failed to load program data.", "error");
        }
      })
      .catch((error) => {
        console.error("Error fetching program:", error);
        Swal.fire("Error", "Failed to load program data.", "error");
      });
    return;
  }

  // Populate modal with cached data
  populateViewModal(program);
}

function populateViewModal(program) {
  console.log("Populating view modal with program data:", program);

  // Populate the view modal fields with proper null/empty handling
  document.getElementById("view_program_code").textContent =
    program.program_code || "-";
  const programNameEl = document.querySelector(
    "#viewProgramModal #view_program_name"
  );
  if (programNameEl) {
    programNameEl.textContent = program.program_name || "-";
  }

  // Handle effective_academic_year - show actual value or "Not specified"
  const academicYear = program.effective_academic_year;
  document.getElementById("view_effective_academic_year").textContent =
    academicYear && academicYear.trim() !== "" ? academicYear : "Not specified";

  // Handle program_type - show actual value or "Not specified"
  const programType = program.program_type;
  document.getElementById("view_program_type").textContent =
    programType && programType.trim() !== "" ? programType : "Not specified";

  // Handle total_units_required - show number or "Not specified"
  const totalUnits = program.total_units_required;
  document.getElementById("view_total_units_required").textContent =
    totalUnits !== null && totalUnits !== undefined && totalUnits !== ""
      ? totalUnits
      : "Not specified";

  // Handle major_track - show value or "Not specified" (this is optional)
  const majorTrack = program.major_track;
  document.getElementById("view_major_track").textContent =
    majorTrack && majorTrack.trim() !== "" ? majorTrack : "Not specified";

  // Handle program_years
  const programYears = program.program_years || 0;
  document.getElementById(
    "view_program_years"
  ).textContent = `${programYears} Year${programYears > 1 ? "s" : ""}`;

  // Handle description
  const programDesc = program.program_desc;
  document.getElementById("view_program_desc").textContent =
    programDesc && programDesc.trim() !== ""
      ? programDesc
      : "No description provided.";

  // Handle department
  document.getElementById("view_dept_name").textContent =
    program.dept_name || "Not specified";

  // Handle status with proper badge
  const statusValue = program.program_status || "Unknown";
  const isActive = statusValue && statusValue.toLowerCase() === "active";
  const statusBadge = `<span class="badge bg-${
    isActive ? "success" : "secondary"
  }">${statusValue}</span>`;
  document.getElementById("view_program_status").innerHTML = statusBadge;

  // Show the modal
  const viewModal = new bootstrap.Modal(
    document.getElementById("viewProgramModal")
  );
  viewModal.show();
}

function editProgram(programId) {
  const program = __PROGRAMS_CACHE__.find(
    (p) => String(p.program_id) === String(programId)
  );
  if (!program) {
    Swal.fire("Error", "Program data not found in cache.", "error");
    return;
  }

  // Populate the edit modal fields
  // Use programId parameter directly as fallback if program.program_id is missing
  const programIdValue = program.program_id || programId;
  if (!programIdValue) {
    console.error("Error: program_id is missing!", { program, programId });
    Swal.fire("Error", "Program ID is missing. Cannot edit program.", "error");
    return;
  }
  
  // Set program_id first and verify it was set
  // Use unique ID for Edit Program modal to avoid conflicts with other modals
  const programIdField = document.getElementById("edit_program_modal_program_id");
  if (!programIdField) {
    console.error("Error: edit_program_modal_program_id field not found in DOM!", {
      modalExists: !!document.getElementById("editProgramModal"),
      formExists: !!document.getElementById("editProgramForm"),
    });
    Swal.fire("Error", "Edit form not found. Please refresh the page.", "error");
    return;
  }
  
  // Verify it's the correct element type (should be hidden input, not select)
  if (programIdField.tagName !== "INPUT" || programIdField.type !== "hidden") {
    console.error("Error: edit_program_modal_program_id is not a hidden input!", {
      tagName: programIdField.tagName,
      type: programIdField.type,
      element: programIdField,
    });
    Swal.fire("Error", "Form structure error. Please refresh the page.", "error");
    return;
  }
  
  programIdField.value = programIdValue;
  
  // Verify the value was set correctly
  if (programIdField.value !== String(programIdValue)) {
    console.error("Error: Failed to set program_id!", {
      expected: programIdValue,
      actual: programIdField.value,
      field: programIdField,
    });
    Swal.fire("Error", "Failed to set Program ID. Please refresh the page.", "error");
    return;
  }
  
  document.getElementById("edit_program_code").value = program.program_code;
  document.getElementById("edit_program_name").value = program.program_name;
  document.getElementById("edit_effective_academic_year").value =
    program.effective_academic_year || "";
  document.getElementById("edit_program_type").value =
    program.program_type || "";
  document.getElementById("edit_total_units_required").value =
    program.total_units_required || "";
  document.getElementById("edit_major_track").value = program.major_track || "";
  document.getElementById("edit_program_desc").value =
    program.program_desc || "";
  document.getElementById("edit_program_years").value =
    program.program_years || 4;
  // Ensure status is properly set (normalize case)
  const statusValue = program.program_status
    ? program.program_status.charAt(0).toUpperCase() +
      program.program_status.slice(1).toLowerCase()
    : "Active";
  document.getElementById("edit_program_status").value = statusValue;
  // Use program.dept_id if available, otherwise fall back to window.currentDeptId
  const deptId = program.dept_id || window.currentDeptId || 0;
  document.getElementById("edit_program_modal_dept_id").value = deptId;

  console.log("Editing program:", {
    id: program.program_id,
    programId_param: programId,
    programIdValue_set: programIdValue,
    programIdField_value: programIdField.value,
    status: program.program_status,
    normalized_status: statusValue,
  });

  // Show the modal
  const editModal = new bootstrap.Modal(
    document.getElementById("editProgramModal")
  );
  editModal.show();
}

function saveProgramEdits() {
  const form = document.getElementById("editProgramForm");
  const formData = new FormData(form);
  const saveBtn = document.querySelector("#editProgramModal .btn-primary");
  const originalText = saveBtn.innerHTML;

  // Validate program_id before submitting
  const programId = formData.get("program_id");
  if (!programId || programId.trim() === "") {
    console.error("Error: program_id is missing from form!", {
      formData: Object.fromEntries(formData),
      hiddenField: document.getElementById("edit_program_modal_program_id")?.value,
    });
    Swal.fire("Error", "Program ID is missing. Please refresh the page and try again.", "error");
    return;
  }

  // Log form data for debugging
  console.log("Saving program with data:", {
    program_id: formData.get("program_id"),
    program_status: formData.get("program_status"),
    program_code: formData.get("program_code"),
    program_name: formData.get("program_name"),
  });

  saveBtn.disabled = true;
  saveBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  fetch("../../admin/management/update_program.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      // Check if response is actually JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        return response.text().then((text) => {
          console.error("Non-JSON response:", text);
          throw new Error(
            "Server returned non-JSON response. Check console for details."
          );
        });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        // Get the form data to update cache immediately
        const form = document.getElementById("editProgramForm");
        const formData = new FormData(form);
        const programId = parseInt(formData.get("program_id"));

        // Update the cache entry immediately if it exists
        const cacheIndex = __PROGRAMS_CACHE__.findIndex(
          (p) => String(p.program_id) === String(programId)
        );
        if (cacheIndex !== -1) {
          // Update the cached program with form values
          __PROGRAMS_CACHE__[cacheIndex].program_code =
            formData.get("program_code");
          __PROGRAMS_CACHE__[cacheIndex].program_name =
            formData.get("program_name");
          __PROGRAMS_CACHE__[cacheIndex].effective_academic_year = formData.get(
            "effective_academic_year"
          );
          __PROGRAMS_CACHE__[cacheIndex].program_type =
            formData.get("program_type");
          __PROGRAMS_CACHE__[cacheIndex].total_units_required = parseInt(
            formData.get("total_units_required")
          );
          __PROGRAMS_CACHE__[cacheIndex].major_track =
            formData.get("major_track");
          __PROGRAMS_CACHE__[cacheIndex].program_desc =
            formData.get("program_desc");
          __PROGRAMS_CACHE__[cacheIndex].program_years = parseInt(
            formData.get("program_years")
          );
          __PROGRAMS_CACHE__[cacheIndex].program_status =
            formData.get("program_status");
          console.log(
            "Updated cache entry for program:",
            __PROGRAMS_CACHE__[cacheIndex]
          );
        }

        Swal.fire("Success!", data.message, "success");
        safeHideModal("editProgramModal");
        // Reload programs to ensure table is updated
        refreshloadPrograms().then(() => {
          console.log("Programs refreshed after edit");
        });
      } else {
        throw new Error(data.message || "Failed to update program.");
      }
    })
    .catch((error) => Swal.fire("Error", error.message, "error"))
    .finally(() => {
      saveBtn.disabled = false;
      saveBtn.innerHTML = originalText;
    });
}

function deleteProgram(programId) {
  const program = __PROGRAMS_CACHE__.find(
    (p) => String(p.program_id) === String(programId)
  );
  const programName = program ? program.program_name : "the selected program";

  Swal.fire({
    title: "Are you sure?",
    html: `You are about to delete <strong>${escapeHtml(
      programName
    )}</strong>. <br><br><span class="text-danger">This action cannot be undone!</span>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
    customClass: {
      popup: "swal2-popup-custom",
      title: "swal2-title-custom",
      content: "swal2-content-custom",
    },
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("program_id", programId);

      fetch("../../admin/management/delete_program.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire("Deleted!", data.message, "success");
            refreshloadPrograms(); // Refresh the table
          } else {
            throw new Error(data.message || "Failed to delete program.");
          }
        })
        .catch((error) => Swal.fire("Error", error.message, "error"));
    }
  });
}

// --- Building Management Functions ---

let buildingsTable;
function fetchDataArray(url) {
  // Helper to fetch JSON and normalize to array
  return fetch(url)
    .then((resp) => {
      // Check for permission errors
      if (resp.status === 403) {
        return resp.json().then((json) => {
          throw new Error(json.message || "Unauthorized access");
        });
      }
      if (!resp.ok) {
        throw new Error(`HTTP ${resp.status}: ${resp.statusText}`);
      }
      return resp.json();
    })
    .then((json) => {
      if (!json) return [];
      if (Array.isArray(json)) return json;
      if (json.data && Array.isArray(json.data)) return json.data;
      // Check for error messages in response
      if (json.success === false && json.message) {
        throw new Error(json.message);
      }
      console.error("Unexpected response for", url, json);
      return [];
    })
    .catch((err) => {
      console.error("Fetch error for", url, err);
      // Re-throw permission errors so they can be handled by the caller
      if (
        err.message &&
        (err.message.includes("Unauthorized") || err.message.includes("403"))
      ) {
        throw err;
      }
      return [];
    });
}

// Ensure debug / placeholder CSS is injected once
function ensureDTStyles() {
  if (document.getElementById("dt-debug-styles")) return;
  const css = `
    .dt-empty-placeholder { padding: 24px; text-align: center; color: #6c757d; border: 1px dashed rgba(0,0,0,0.05); border-radius: 6px; background: rgba(0,0,0,0.02); }
    .dt-empty-placeholder h5 { margin: 0 0 6px 0; font-weight: 600; }
    .dt-debug-badge { position: relative; z-index: 2; }
    .table-responsive { min-height: 60px; }
  `;
  const s = document.createElement("style");
  s.id = "dt-debug-styles";
  s.type = "text/css";
  s.appendChild(document.createTextNode(css));
  document.head.appendChild(s);
}

// Update table UI: show/hide placeholder message when count === 0
function updateTableUI(tableSelector, count, hintMessage) {
  ensureDTStyles();
  try {
    const $wrap = $(tableSelector).closest(".table-responsive");
    if (!$wrap || !$wrap.length) return;
    $wrap.find(".dt-empty-placeholder").remove();
    if (!count || count === 0) {
      const msg = hintMessage || "No records found.";
      $wrap.append(
        `<div class="dt-empty-placeholder mt-2"> <h5>No records</h5><div class="small">${escapeHtml(
          msg
        )}</div></div>`
      );
    }
  } catch (err) {
    console.error("updateTableUI error", err);
  }
}

// Expose a global debug helper so it is available even if functions run before DOMContentLoaded
window.debugDataTable = function (name, tableInstance, selector) {
  try {
    const isDT = !!(tableInstance && typeof tableInstance.rows === "function");
    console.log(`[DT DEBUG] ${name} - DataTable initialized: ${isDT}`, {
      selector,
    });
    if (!isDT) {
      if ($.fn.DataTable.isDataTable(selector)) {
        const dt = $(selector).DataTable();
        console.log(
          `[DT DEBUG] ${name} - Found DataTable via selector. Rows: ${dt
            .rows()
            .count()}`,
          dt.rows().data().toArray().slice(0, 3)
        );
      } else {
        console.log(
          `[DT DEBUG] ${name} - No DataTable instance found for selector ${selector}`
        );
      }
      return;
    }

    const rowCount = tableInstance.rows().count();
    const sample =
      (tableInstance.rows().data() &&
        tableInstance.rows().data().toArray &&
        tableInstance.rows().data().toArray().slice(0, 3)) ||
      [];
    console.log(`[DT DEBUG] ${name} - Rows: ${rowCount}`, { sample });
    if (rowCount && sample.length)
      console.log(`[DT DEBUG] ${name} - First row sample:`, sample[0]);
  } catch (err) {
    console.error(`[DT DEBUG] ${name} - Error while debugging DataTable:`, err);
  }
};

// Safely hide a Bootstrap modal and clean any leftover backdrop or body classes
function safeHideModal(modalId) {
  try {
    const el = document.getElementById(modalId);
    if (!el) return;
    const instance = bootstrap.Modal.getInstance(el);
    if (instance && typeof instance.hide === "function") instance.hide();
    // remove any lingering backdrops
    document
      .querySelectorAll(".modal-backdrop")
      .forEach((b) => b.parentNode && b.parentNode.removeChild(b));
    // ensure body scroll / classes are reset
    document.body.classList.remove("modal-open");
    document.body.style.paddingRight = "";
  } catch (err) {
    console.error("safeHideModal error for", modalId, err);
  }
}

// Alternative function to force-show Add Section Modal (backup method)
window.forceShowAddSectionModal = function (classId) {
  console.log(
    "🟡 [DEBUG] forceShowAddSectionModal called with classId:",
    classId
  );

  let modalEl = document.getElementById("addSectionModal");
  if (!modalEl) {
    console.error("❌ [DEBUG] Modal element not found");
    return false;
  }

  // Check if modal needs to be moved to body
  let parent = modalEl.parentElement;
  let needsMove = false;
  while (parent && parent !== document.body) {
    const parentStyle = window.getComputedStyle(parent);
    if (
      parentStyle.overflow === "hidden" ||
      parentStyle.overflowX === "hidden" ||
      parentStyle.overflowY === "hidden"
    ) {
      needsMove = true;
      break;
    }
    parent = parent.parentElement;
  }

  if (needsMove) {
    console.log("🔄 [DEBUG] Moving modal to body...");
    const modalClone = modalEl.cloneNode(true);
    modalEl.remove();
    document.body.appendChild(modalClone);
    modalEl = document.getElementById("addSectionModal");
  }

  // Set class_id
  const classIdInput = document.getElementById("add_sec_class_id");
  if (classIdInput && classId) {
    classIdInput.value = classId;
  }

  // Force show using aggressive DOM manipulation
  modalEl.style.cssText = `
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 1105 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
  `;
  modalEl.classList.add("show");
  modalEl.setAttribute("aria-hidden", "false");
  modalEl.setAttribute("aria-modal", "true");

  // Fix modal-dialog
  const modalDialog = modalEl.querySelector(".modal-dialog");
  if (modalDialog) {
    modalDialog.style.cssText = `
      position: relative !important;
      margin: 1.75rem auto !important;
      z-index: 1106 !important;
      max-width: 500px !important;
    `;
  }

  // Add body classes
  document.body.classList.add("modal-open");
  document.body.style.overflow = "hidden";

  // Create or update backdrop
  let backdrop = document.querySelector(".modal-backdrop");
  if (!backdrop) {
    backdrop = document.createElement("div");
    backdrop.className = "modal-backdrop fade show";
    document.body.appendChild(backdrop);
  }
  backdrop.style.cssText = `
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 1100 !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
    opacity: 1 !important;
    display: block !important;
  `;

  // Reset form
  const form = document.getElementById("addSectionForm");
  if (form) {
    form.reset();
    if (classIdInput && classId) {
      classIdInput.value = classId;
    }
  }

  // Verify visibility
  setTimeout(() => {
    const rect = modalEl.getBoundingClientRect();
    console.log("🔍 [DEBUG] Force show verification:", {
      display: window.getComputedStyle(modalEl).display,
      rect: rect,
      isVisible: rect.width > 0 && rect.height > 0 && rect.top >= 0,
    });
  }, 50);

  console.log("✅ [DEBUG] Modal force-shown successfully");
  return true;
};

function initializeBuildingsTable() {
  // Create table if not exists
  if (!$.fn.DataTable.isDataTable("#buildingsTable")) {
    buildingsTable = $("#buildingsTable").DataTable({
      data: [],
      columns: [
        { data: "bd_id", title: "ID" },
        { data: "bd_desc", title: "Building Name / Description" },
        {
          data: "dept_name",
          title: "Department",
          render: function (data, type, row) {
            if (data) {
              return `<span class="text-dark">${escapeHtml(data)}</span>`;
            } else if (row.dept_id === null || row.dept_id === undefined) {
              return `<span class="text-muted"><i class="bi bi-share"></i> Shared/Unassigned</span>`;
            } else {
              return `<span class="text-muted">-</span>`;
            }
          },
          defaultContent: '<span class="text-muted">-</span>',
        },
        {
          data: "bd_status",
          title: "Status",
          render: function (data) {
            const badgeClass = data === "Used" ? "bg-success" : "bg-secondary";
            return `<span class="badge ${badgeClass}">${data}</span>`;
          },
        },
        {
          data: null,
          title: "Actions",
          orderable: false,
          className: "text-center",
          render: function (data, type, row) {
            return `
              <button class="btn-edit-building btn btn-sm btn-outline-primary" data-bd-id="${row.bd_id}" title="Edit Building">
                <i class="bi bi-pencil-square"></i> Edit
              </button>
              <button class="btn-delete-building btn btn-sm btn-outline-danger" data-bd-id="${row.bd_id}" title="Delete Building">
                <i class="bi bi-trash"></i> Delete
              </button>
            `;
          },
        },
      ],
      responsive: true,
      language: { emptyTable: "No buildings found. Add one to get started." },
    });
  }

  // Load data and populate
  fetchDataArray("../../admin/management/get_buildings.php")
    .then((data) => {
      console.log(
        "[DT FETCH] buildings fetched",
        Array.isArray(data)
          ? data.length
          : data && data.data
          ? data.data.length
          : "unknown"
      );
      if (Array.isArray(data) && data.length)
        console.log("[DT FETCH] sample building:", data[0]);
      if (!buildingsTable) return;
      buildingsTable.clear();
      buildingsTable.rows.add(data);
      buildingsTable.draw();
      // Update UI placeholder and badge
      updateTableUI(
        "#buildingsTable",
        buildingsTable.rows().count(),
        "Add a building to get started."
      );
      debugDataTable("Buildings", buildingsTable, "#buildingsTable");
    })
    .catch((error) => {
      console.error("Error loading buildings:", error);
      if (
        error.message &&
        (error.message.includes("403") ||
          error.message.includes("Unauthorized"))
      ) {
        showPermissionError("Rooms");
      }
    });
}

function addBuilding() {
  // Check if form is disabled (user doesn't have permission)
  const form = document.getElementById("addBuildingForm");
  if (form && form.classList.contains("form-disabled")) {
    Swal.fire(
      "Access Denied",
      "You do not have permission to manage rooms.",
      "error"
    );
    return;
  }

  const formData = new FormData(form);

  if (!form.checkValidity()) {
    Swal.fire(
      "Validation Error",
      "Please fill in all required fields.",
      "warning"
    );
    return;
  }

  fetch("../../admin/management/add_building.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("addBuildingModal");
        form.reset();
        // Refresh via fetchDataArray to normalize response shapes
        fetchDataArray("../../admin/management/get_buildings.php").then(
          (data) => {
            if (!buildingsTable) return;
            buildingsTable.clear();
            buildingsTable.rows.add(data);
            buildingsTable.draw();
          }
        );
      } else {
        Swal.fire("Error", data.message, "error");
      }
    })
    .catch((error) =>
      Swal.fire(
        "Error",
        "An error occurred while adding the building.",
        "error"
      )
    );
}

function editBuilding(id, desc, status) {
  document.getElementById("edit_bd_id").value = id;
  document.getElementById("edit_bd_desc").value = desc;
  document.getElementById("edit_bd_status").value = status;

  const editModal = new bootstrap.Modal(
    document.getElementById("editBuildingModal")
  );
  editModal.show();
}

function updateBuilding() {
  const form = document.getElementById("editBuildingForm");
  const formData = new FormData(form);

  if (!form.checkValidity()) {
    Swal.fire(
      "Validation Error",
      "Please fill in all required fields.",
      "warning"
    );
    return;
  }

  fetch("../../admin/management/update_building.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("editBuildingModal");
        // Refresh via fetchDataArray to normalize response shapes
        fetchDataArray("../../admin/management/get_buildings.php").then(
          (data) => {
            if (!buildingsTable) return;
            buildingsTable.clear();
            buildingsTable.rows.add(data);
            buildingsTable.draw();
          }
        );
      } else {
        Swal.fire("Error", data.message, "error");
      }
    })
    .catch((error) =>
      Swal.fire(
        "Error",
        "An error occurred while updating the building.",
        "error"
      )
    );
}

function deleteBuilding(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this! Deleting a building requires that no rooms are associated with it.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("bd_id", id);

      fetch("../../admin/management/delete_building.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire("Deleted!", data.message, "success");
            // Refresh via fetchDataArray to normalize response shapes
            fetchDataArray("../../admin/management/get_buildings.php").then(
              (data) => {
                if (!buildingsTable) return;
                buildingsTable.clear();
                buildingsTable.rows.add(data);
                buildingsTable.draw();
              }
            );
          } else {
            Swal.fire("Error", data.message, "error");
          }
        })
        .catch((error) =>
          Swal.fire(
            "Error",
            "An error occurred while deleting the building.",
            "error"
          )
        );
    }
  });
}

// --- Room Management Functions ---

let roomsTable;
// Guard: prevent duplicate initialization of Rooms tab
let roomsTabInitialized = false;
// Track initialization attempts to allow retry on failure
let roomsTabInitAttempts = 0;
const MAX_INIT_ATTEMPTS = 3;

/**
 * Check if Rooms tab pane is visible
 * Uses multiple methods for reliable detection
 */
function isRoomsTabVisible() {
  const roomsTabPane = document.getElementById("rooms");
  if (!roomsTabPane) return false;
  
  // Check Bootstrap classes
  const hasShow = roomsTabPane.classList.contains("show");
  const hasActive = roomsTabPane.classList.contains("active");
  
  // Check computed style (more reliable)
  const style = window.getComputedStyle(roomsTabPane);
  const isDisplayed = style.display !== "none";
  const isVisible = style.visibility !== "hidden";
  const hasOpacity = parseFloat(style.opacity) > 0;
  
  // Check if parent tab is also visible
  const parentTab = document.getElementById("room_requests");
  const parentVisible = parentTab && 
    window.getComputedStyle(parentTab).display !== "none";
  
  const result = hasShow && hasActive && isDisplayed && isVisible && 
                    hasOpacity && parentVisible;
  
  console.log("[ROOMS DEBUG] Tab visibility check:", {
    hasShow,
    hasActive,
    isDisplayed,
    isVisible,
    hasOpacity,
    parentVisible,
    result: result
  });
  
  return result;
}

function initializeRoomsTab() {
  // ============================================================
  // INITIALIZATION FUNCTION - NEVER ACTIVATES TABS
  // ============================================================
  // This function:
  // ✅ Loads data
  // ✅ Initializes DataTables
  // ✅ Sets up event handlers
  // ❌ NEVER activates or switches tabs
  
  // Check if already successfully initialized
  if (roomsTabInitialized) {
    console.log("[ROOMS DEBUG] initializeRoomsTab() already initialized, skipping duplicate call");
    // Still ensure event handlers are attached (in case DOM was recreated)
    const selector = $("#buildingSelector");
    if (selector.length && !selector.data("handlers-attached")) {
      setupBuildingSelectorHandlers(selector);
    }
    return;
  }
  
  // Increment attempt counter
  roomsTabInitAttempts++;
  
  // Check if we've exceeded max attempts
  if (roomsTabInitAttempts > MAX_INIT_ATTEMPTS) {
    console.error("[ROOMS DEBUG] Max initialization attempts reached. Resetting counter.");
    roomsTabInitAttempts = 0;
    // Allow retry by resetting flag
    roomsTabInitialized = false;
  }
  
  console.log("[ROOMS DEBUG] initializeRoomsTab() called - INITIALIZATION ONLY, NO TAB CHANGES (attempt:", roomsTabInitAttempts, ")");
  
  try {
    const selector = $("#buildingSelector");
    
    if (!selector.length) {
      console.warn("[ROOMS DEBUG] Building selector not found in DOM. Tab may not be loaded yet.");
      // Reset flag to allow retry
      roomsTabInitialized = false;
      return;
    }
    
    // Check if selector is already populated (has more than just the placeholder)
    const isAlreadyPopulated = selector.find("option").length > 1;
    const currentValue = selector.val();
    console.log("[ROOMS DEBUG] Selector state:", {
      isAlreadyPopulated,
      currentValue,
      optionCount: selector.find("option").length,
      roomsTableExists: !!roomsTable,
      roomsTableRows: roomsTable ? roomsTable.rows().count() : 0
    });
    
    // Setup event handlers first (before any async operations)
    setupBuildingSelectorHandlers(selector);
    
    // Check if there's a pending building selection from when tab wasn't visible
    if (window._pendingBuildingSelection) {
      const pendingValue = window._pendingBuildingSelection;
      delete window._pendingBuildingSelection;
      console.log("[ROOMS DEBUG] Found pending building selection:", pendingValue);
      
      // Validate pending value
      if (pendingValue && !isNaN(pendingValue) && parseInt(pendingValue) > 0) {
        selector.val(pendingValue);
        $("#addRoomBtn").prop("disabled", false);
        
        // Ensure tab is visible before initializing table
        if (isRoomsTabVisible()) {
          initializeRoomsTable(pendingValue);
        } else {
          console.log("[ROOMS DEBUG] Tab still not visible, re-storing pending selection");
          window._pendingBuildingSelection = pendingValue;
        }
      }
      
      // If selector is not populated, still populate it
      if (!isAlreadyPopulated) {
        populateBuildingSelector();
      }
      return; // Exit early after handling pending selection
    }
    
    // Only populate if not already populated
    if (!isAlreadyPopulated) {
      console.log("[ROOMS DEBUG] Selector not populated, calling populateBuildingSelector()");
      populateBuildingSelector();
    } else {
      // If already populated, check if a building is selected and load rooms
      console.log("[ROOMS DEBUG] Selector already populated, checking current selection");
      if (currentValue && !isNaN(currentValue) && parseInt(currentValue) > 0) {
        console.log("[ROOMS DEBUG] Building already selected:", currentValue, "- loading rooms");
        $("#addRoomBtn").prop("disabled", false);
        
        // Ensure tab is visible before initializing
        if (isRoomsTabVisible()) {
          initializeRoomsTable(currentValue);
        } else {
          console.log("[ROOMS DEBUG] Rooms tab not visible - deferring room table initialization");
          window._pendingBuildingSelection = currentValue;
        }
      } else {
        console.log("[ROOMS DEBUG] No valid building selected, selector value:", currentValue);
        // Initialize empty table if tab is visible
        if (isRoomsTabVisible()) {
          initializeRoomsTable(null);
        }
      }
    }
    
    // Mark as successfully initialized
    roomsTabInitialized = true;
    roomsTabInitAttempts = 0; // Reset counter on success
    
  } catch (error) {
    console.error("[ROOMS DEBUG] Error during rooms tab initialization:", error);
    // Reset flag to allow retry
    roomsTabInitialized = false;
    throw error; // Re-throw to allow caller to handle
  }
}

/**
 * Setup building selector change handlers
 * Separated for reusability and cleaner code
 */
function setupBuildingSelectorHandlers(selector) {
  // Remove any existing change handlers to prevent stacking
  selector.off("change.roomsTab");
  console.log("[ROOMS DEBUG] Removed old change handlers");
  
  // Attach change handler with namespace to allow easy removal
  selector.on("change.roomsTab", function () {
    const buildingId = $(this).val();
    console.log("[ROOMS DEBUG] Building selector changed to:", buildingId);
    
    // Validate that buildingId is a valid numeric ID
    if (buildingId && !isNaN(buildingId) && parseInt(buildingId) > 0) {
      console.log("[ROOMS DEBUG] Valid building ID, loading rooms for building:", buildingId);
      $("#addRoomBtn").prop("disabled", false);
      
      // Ensure tab is visible before loading
      if (isRoomsTabVisible()) {
        initializeRoomsTable(buildingId);
      } else {
        console.log("[ROOMS DEBUG] Tab not visible, storing selection for later");
        window._pendingBuildingSelection = buildingId;
      }
    } else {
      console.log("[ROOMS DEBUG] Invalid or empty building ID, clearing rooms table");
      $("#addRoomBtn").prop("disabled", true);
      if (roomsTable) {
        console.log("[ROOMS DEBUG] Clearing rooms table (rows before clear:", roomsTable.rows().count(), ")");
        roomsTable.clear().draw();
      } else if (isRoomsTabVisible()) {
        // Initialize empty table if not already initialized
        initializeRoomsTable(null);
      }
    }
  });
  
  // Mark handlers as attached
  selector.data("handlers-attached", true);
  console.log("[ROOMS DEBUG] Change handler attached");
}

// Global variables for room access management are set in dashboard.php
// window.currentUserDeptId and window.isAdminSupport

// Load departments for room add/edit modals (Admin Support only)
function loadDepartmentsForRoomAdd() {
  const deptField = document.getElementById("add_rm_dept_id");
  if (!deptField) return;

  fetch("../../admin/management/get_available_departments.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.data) {
        deptField.innerHTML =
          '<option value="">Select Department (Optional)</option>';
        data.data.forEach((dept) => {
          const option = document.createElement("option");
          option.value = dept.dept_id;
          option.textContent = dept.dept_name;
          deptField.appendChild(option);
        });
      }
    })
    .catch((error) => console.error("Error loading departments:", error));
}

function loadDepartmentsForRoomEdit() {
  const deptField = document.getElementById("edit_rm_dept_id");
  if (!deptField) return;

  fetch("../../admin/management/get_available_departments.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.data) {
        const currentValue = deptField.value;
        deptField.innerHTML =
          '<option value="">No Department (Shared)</option>';
        data.data.forEach((dept) => {
          const option = document.createElement("option");
          option.value = dept.dept_id;
          option.textContent = dept.dept_name;
          deptField.appendChild(option);
        });
        deptField.value = currentValue;
      }
    })
    .catch((error) => console.error("Error loading departments:", error));
}

// Room Access Management Functions
function openRoomAccessModal(rmId, rmName) {
  const modal = new bootstrap.Modal(document.getElementById("roomAccessModal"));
  const content = document.getElementById("roomAccessContent");

  // Update modal title
  document.getElementById(
    "roomAccessModalLabel"
  ).textContent = `Manage Access: ${rmName || "Room"}`;

  // Show loading
  content.innerHTML = `
    <div class="text-center py-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
  `;

  modal.show();

  // Load current access and available departments
  Promise.all([
    fetch(`../../admin/management/manage_room_access.php?rm_id=${rmId}`).then(
      (r) => r.json()
    ),
    fetch("../../admin/management/get_available_departments.php").then((r) =>
      r.json()
    ),
  ])
    .then(([accessData, deptData]) => {
      if (!accessData.success || !deptData.success) {
        content.innerHTML =
          '<div class="alert alert-danger">Failed to load room access data.</div>';
        return;
      }

      const currentAccess = accessData.data || [];
      const availableDepts = deptData.data || [];
      const grantedDeptIds = currentAccess.map((a) => a.granted_to_dept_id);

      // Filter out departments that already have access
      const deptsToGrant = availableDepts.filter(
        (d) => !grantedDeptIds.includes(d.dept_id)
      );

      let html = `
      <div class="mb-4">
        <h6 class="fw-bold mb-3">Grant Access to Other Departments</h6>
        <div class="row g-2 mb-3">
          <div class="col-md-8">
            <select class="form-select" id="grantAccessDeptSelect">
              <option value="">Select a department...</option>
    `;

      deptsToGrant.forEach((dept) => {
        html += `<option value="${dept.dept_id}">${dept.dept_name}</option>`;
      });

      html += `
            </select>
          </div>
          <div class="col-md-4">
            <button class="btn btn-primary w-100" onclick="grantRoomAccess(${rmId})">
              <i class="bi bi-plus-circle me-1"></i> Grant Access
            </button>
          </div>
        </div>
      </div>
      
      <div>
        <h6 class="fw-bold mb-3">Current Access Permissions</h6>
    `;

      if (currentAccess.length === 0) {
        html +=
          '<div class="alert alert-info">No departments have been granted access to this room.</div>';
      } else {
        html +=
          '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Department</th><th>Granted By</th><th>Granted On</th><th>Actions</th></tr></thead><tbody>';

        currentAccess.forEach((access) => {
          const grantedBy =
            access.fname && access.lname
              ? `${access.fname} ${access.lname}`
              : "System";
          const grantedDate = new Date(access.granted_at).toLocaleDateString();
          html += `
          <tr>
            <td>${escapeHtml(access.dept_name)}</td>
            <td>${escapeHtml(grantedBy)}</td>
            <td>${grantedDate}</td>
            <td>
              <button class="btn btn-sm btn-outline-danger" onclick="revokeRoomAccess(${
                access.access_id
              }, ${rmId})">
                <i class="bi bi-x-circle me-1"></i> Revoke
              </button>
            </td>
          </tr>
        `;
        });

        html += "</tbody></table></div>";
      }

      html += "</div>";
      content.innerHTML = html;
    })
    .catch((error) => {
      console.error("Error loading room access:", error);
      content.innerHTML =
        '<div class="alert alert-danger">An error occurred while loading room access data.</div>';
    });
}

function grantRoomAccess(rmId) {
  const deptId = document.getElementById("grantAccessDeptSelect").value;

  if (!deptId) {
    Swal.fire({
      icon: "warning",
      title: "Selection Required",
      text: "Please select a department to grant access.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  fetch("../../admin/management/manage_room_access.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ rm_id: rmId, granted_to_dept_id: deptId }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Access Granted",
          text: data.message,
          confirmButtonColor: "#28a745",
          timer: 2000,
        });
        // Reload the modal content
        const rmName = document
          .getElementById("roomAccessModalLabel")
          .textContent.replace("Manage Access: ", "");
        openRoomAccessModal(rmId, rmName);
      } else {
        Swal.fire({
          icon: "error",
          title: "Failed",
          text: data.message,
          confirmButtonColor: "#dc3545",
        });
      }
    })
    .catch((error) => {
      console.error("Error granting access:", error);
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "An error occurred while granting access.",
        confirmButtonColor: "#dc3545",
      });
    });
}

function revokeRoomAccess(accessId, rmId) {
  // Validate parameters
  if (!accessId || !rmId) {
    console.error("Invalid parameters:", { accessId, rmId });
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Invalid parameters. Please refresh the page and try again.",
      confirmButtonColor: "#dc3545",
    });
    return;
  }

  Swal.fire({
    title: "Revoke Access?",
    text: "Are you sure you want to revoke access for this department?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc3545",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Yes, revoke it!",
  }).then((result) => {
    if (result.isConfirmed) {
      fetch("../../admin/management/manage_room_access.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ access_id: accessId }),
      })
        .then((response) => {
          // Check if response is OK
          if (!response.ok) {
            return response.text().then((text) => {
              throw new Error(`HTTP ${response.status}: ${text}`);
            });
          }
          return response.json();
        })
        .then((data) => {
          if (data.success) {
            Swal.fire({
              icon: "success",
              title: "Access Revoked",
              text: data.message || "Room access revoked successfully.",
              confirmButtonColor: "#28a745",
              timer: 2000,
            });
            // Reload the modal content after a brief delay
            setTimeout(() => {
              const modalLabel = document.getElementById(
                "roomAccessModalLabel"
              );
              if (modalLabel) {
                const rmName =
                  modalLabel.textContent.replace("Manage Access: ", "") ||
                  "Room";
                openRoomAccessModal(rmId, rmName);
              } else {
                console.error("Room access modal label not found");
              }
            }, 500);
          } else {
            Swal.fire({
              icon: "error",
              title: "Failed",
              text: data.message || "Failed to revoke access.",
              confirmButtonColor: "#dc3545",
            });
          }
        })
        .catch((error) => {
          console.error("Error revoking access:", error);
          let errorMessage = "An error occurred while revoking access.";
          if (error.message) {
            errorMessage += ` (${error.message})`;
          }
          Swal.fire({
            icon: "error",
            title: "Error",
            text: errorMessage,
            confirmButtonColor: "#dc3545",
          });
        });
    }
  });
}

// Room Schedule View Functions - Simplified and Safe
// Core pattern: Fetch data immediately, render ONLY after modal fires shown.bs.modal
let roomScheduleDataCache = null;
let activeRoomId = null;
let activeRoomName = null;
let currentRoomScheduleRmId = null; // Room ID for calendar modal (synced with activeRoomId)
let currentRoomScheduleAvailableSlots = {}; // Store original available slots format for request modal

// Initialize event delegation for room schedule table rows
function initializeRoomScheduleEventDelegation() {
  // Use event delegation on the document to catch clicks on dynamically loaded rows
  // Remove any existing listeners first to prevent duplicates
  const existingHandler = window._roomScheduleClickHandler;
  if (existingHandler) {
    document.removeEventListener("click", existingHandler);
  }

  // Create and store the handler
  window._roomScheduleClickHandler = function (event) {
    handleRoomScheduleRowClick(event);
  };

  // Add event delegation for table rows with data-room-schedule-id
  document.addEventListener("click", window._roomScheduleClickHandler, true); // Use capture phase for better reliability
  console.log("Room schedule event delegation initialized");
}

function handleRoomScheduleRowClick(event) {
  // Check if clicked element or its parent has data-room-schedule-id
  let target = event.target;
  let row = null;

  // Traverse up to find the TR element with data attributes
  while (target && target !== document.body) {
    if (
      target.tagName === "TR" &&
      target.hasAttribute("data-room-schedule-id")
    ) {
      row = target;
      break;
    }
    target = target.parentElement;
  }

  if (!row) return;

  const rmId = row.getAttribute("data-room-schedule-id");
  const rmName = row.getAttribute("data-room-schedule-name");

  if (rmId) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation(); // Prevent other handlers
    console.log("Room row clicked:", { rmId, rmName });
    // Call immediately - no delay needed
    openRoomScheduleModal(parseInt(rmId), rmName || "Room");
  }
}

/**
 * Initialize room schedule modal - Simple initialization check
 * No complex lifecycle management needed with the new pattern
 */
function initializeRoomScheduleModal() {
  const modalElement = document.getElementById("roomScheduleModal");

  if (!modalElement) {
    console.warn(
      "Room schedule modal not found in DOM. It may be loaded dynamically."
    );
    return;
  }

  // Verify Bootstrap is available
  if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
    console.error(
      "Bootstrap 5 Modal is not available. Ensure bootstrap.bundle.min.js is loaded."
    );
    return;
  }

  console.log("Room schedule modal initialized successfully");
}

/**
 * Open room schedule modal - Clean and Safe Implementation
 * Fetches data immediately, renders only after modal is fully visible
 */
function openRoomScheduleModal(rmId, rmName) {
  console.log("openRoomScheduleModal called:", { rmId, rmName });

  // CRITICAL: Check if this is being called from Room Access Grants Received
  // If so, redirect to list modal instead
  const stackTrace = new Error().stack;
  if (stackTrace && stackTrace.includes("Room Access Grants Received")) {
    console.warn(
      "openRoomScheduleModal called from Room Access Grants Received - redirecting to list modal"
    );
    if (typeof openRoomAccessGrantsListModal === "function") {
      openRoomAccessGrantsListModal(rmId, rmName);
      return;
    }
  }

  // Prevent multiple simultaneous opens
  if (window._roomScheduleModalOpening) {
    console.log("Modal already opening, ignoring duplicate call");
    return;
  }
  window._roomScheduleModalOpening = true;

  activeRoomId = rmId;
  activeRoomName = rmName || "Room";
  currentRoomScheduleRmId = rmId; // Store for date filter functions

  // CRITICAL: Check if openRoomAccessGrantsListModal exists and should be used instead
  // This prevents calendar modal from opening for Room Access Grants Received
  if (typeof openRoomAccessGrantsListModal === "function") {
    // Check if we're in a context where list modal should be used
    // (This is a safety check - the onclick should already call the right function)
    const clickedElement =
      document.activeElement || (window.event && window.event.target);
    if (clickedElement) {
      const row = clickedElement.closest("tr");
      if (
        row &&
        row.getAttribute("title") &&
        row.getAttribute("title").includes("available time slots")
      ) {
        console.warn(
          "openRoomScheduleModal called but should use list modal - redirecting"
        );
        window._roomScheduleModalOpening = false;
        openRoomAccessGrantsListModal(rmId, rmName);
        return;
      }
    }
  }

  let modalEl = document.getElementById("roomScheduleModal");
  if (!modalEl) {
    console.error("Room schedule modal not found in DOM");
    window._roomScheduleModalOpening = false;
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Modal Not Found",
        text: "The room schedule modal is not available. Please refresh the page.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Ensure modal is directly in body (not inside hidden tab-content) for proper visibility
  // Bootstrap modals should be at body level, but if it's in a hidden tab, move it
  const parentTab = modalEl.closest(".tab-content");
  if (parentTab) {
    const parentDisplay = window.getComputedStyle(parentTab).display;
    if (parentDisplay === "none") {
      console.log(
        "Modal is in hidden tab-content, moving to body for visibility"
      );
      // Move modal to body (this preserves event listeners)
      document.body.appendChild(modalEl);
      // Get fresh reference after moving
      modalEl = document.getElementById("roomScheduleModal");
    } else if (modalEl.parentElement !== document.body) {
      // Even if parent is visible, move to body for proper Bootstrap behavior
      console.log("Moving modal to body for proper Bootstrap behavior");
      document.body.appendChild(modalEl);
      // Get fresh reference after moving
      modalEl = document.getElementById("roomScheduleModal");
    }
  } else if (modalEl.parentElement !== document.body) {
    // Modal is not in a tab-content but also not in body, move it
    console.log("Moving modal to body");
    document.body.appendChild(modalEl);
    // Get fresh reference after moving
    modalEl = document.getElementById("roomScheduleModal");
  }

  // Check Bootstrap availability
  if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
    console.error(
      "Bootstrap 5 Modal is not available. Ensure bootstrap.bundle.min.js is loaded."
    );
    window._roomScheduleModalOpening = false;
    return;
  }

  // Get or create modal instance (after ensuring modal is in body)
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  // Update modal title
  const modalLabel = document.getElementById("roomScheduleModalLabel");
  if (modalLabel) {
    modalLabel.textContent = `Schedule: ${activeRoomName}`;
  }

  // Show "View Room Reservations" button
  const viewAcceptedBtn = document.getElementById("viewAcceptedRequestsBtn");
  if (viewAcceptedBtn) {
    // Store room ID in multiple places for reliability
    if (typeof window !== "undefined") {
      window.currentRoomIdForAcceptedRequests = rmId;
      window.currentRoomScheduleRmId = rmId;
    }
    // Store room ID in button data attribute as backup
    viewAcceptedBtn.setAttribute("data-room-id", rmId);
    viewAcceptedBtn.dataset.roomId = rmId;
    // Store in modal data attribute too
    if (modalEl) {
      modalEl.setAttribute("data-room-id", rmId);
      modalEl.dataset.roomId = rmId;
    }
    // Show button by removing d-none class and adding d-inline-flex
    viewAcceptedBtn.classList.remove("d-none");
    viewAcceptedBtn.classList.add("d-inline-flex");
    viewAcceptedBtn.style.visibility = "visible";
    viewAcceptedBtn.style.opacity = "1";
    console.log("View Room Reservations button shown for room ID:", rmId);
  } else {
    console.warn("View Room Reservations button not found in DOM");
  }

  // Update alert message (will be updated when data loads)
  const alertEl = document.getElementById("roomScheduleAlert");
  if (alertEl) {
    alertEl.innerHTML =
      '<i class="bi bi-calendar-check me-2"></i><strong>Room Schedules</strong> - Loading...';
  }

  // Show loading spinner, hide calendar grid
  const loadingEl = document.getElementById("roomScheduleLoading");
  const contentEl = document.getElementById("roomScheduleContent");
  if (loadingEl) loadingEl.style.display = "block";
  if (contentEl) {
    contentEl.style.display = "none";

    // Restore calendar grid HTML structure if it was replaced by error messages
    // Check if calendar elements exist, if not, restore them
    const timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
    const daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");

    if (!timeColumn || !daysWrapper) {
      console.log("Restoring calendar grid HTML structure");
      // Restore the original calendar grid structure
      contentEl.innerHTML = `
        <div class="alert alert-info mb-3" id="roomScheduleAlert">
          <i class="bi bi-calendar-check me-2"></i>
          <strong>Occupied Schedules</strong> - The following schedules are assigned to this room.
        </div>
        <div class="calendar-container" style="margin-top: 1rem;">
          <div class="calendar-grid" style="display: flex; border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 70vh; overflow-y: auto;">
            <div class="time-column" id="roomScheduleTimeColumn" style="width: 100px; flex-shrink: 0; background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); border-right: 2px solid #e0e0e0; position: sticky; left: 0; z-index: 10;">
              <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11;">Time</div>
            </div>
            <div class="days-wrapper" id="roomScheduleDaysWrapper" style="display: grid; grid-template-columns: repeat(7, 1fr); flex-grow: 1; background-color: #ffffff;">
            </div>
          </div>
        </div>
      `;
    } else {
      // Clear previous calendar grid content but keep structure
      const header = timeColumn.querySelector(".calendar-header");
      if (header) {
        timeColumn.innerHTML = "";
        timeColumn.appendChild(header);
      } else {
        timeColumn.innerHTML =
          '<div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11;">Time</div>';
      }
      daysWrapper.innerHTML = "";
    }
  }

  // Reset cache
  roomScheduleDataCache = null;

  // Get date filter value if set (from the calendar modal)
  const dateFilterElModal = document.getElementById(
    "roomScheduleCalendarDateFilter"
  );
  const dateFilterModal =
    dateFilterElModal && dateFilterElModal.value
      ? dateFilterElModal.value.trim()
      : "";

  // Build API URL with date filter if provided
  let apiUrlModal = `../../admin/management/get_room_schedules.php?rm_id=${rmId}`;
  if (dateFilterModal) {
    apiUrlModal += `&date=${encodeURIComponent(dateFilterModal)}`;
  }

  // Fetch data immediately (before modal opens)
  fetch(apiUrlModal)
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error("Failed to parse JSON response:", text);
          throw new Error("Invalid JSON response from server");
        }
      });
    })
    .then((data) => {
      console.log("Room schedule API response:", data);

      // Store raw schedule data for calendar grid rendering (keep original format)
      if (data && data.success !== false) {
        if (data.view_type === "occupied" && Array.isArray(data.data)) {
          // Store raw schedule data - calendar grid needs original format with schd_day, schd_start, schd_end, etc.
          roomScheduleDataCache = data.data;

          // Store available slots if provided (for room owners)
          if (
            data.available_slots &&
            typeof data.available_slots === "object"
          ) {
            currentRoomScheduleAvailableSlots = data.available_slots;

            // Transform available slots to include in calendar display
            const transformedSlots = [];
            Object.keys(data.available_slots).forEach((day) => {
              if (
                Array.isArray(data.available_slots[day]) &&
                data.available_slots[day].length > 0
              ) {
                data.available_slots[day].forEach((slot) => {
                  // Parse formatted time to get HH:MM:SS
                  const parseTime = (timeStr, timestampFallback) => {
                    if (timeStr) {
                      const match = timeStr.match(
                        /(\d{1,2}):(\d{2})\s*(AM|PM)/i
                      );
                      if (match) {
                        let hour = parseInt(match[1]);
                        const minute = parseInt(match[2]);
                        const period = match[3].toUpperCase();
                        if (period === "PM" && hour !== 12) hour += 12;
                        if (period === "AM" && hour === 12) hour = 0;
                        return `${String(hour).padStart(2, "0")}:${String(
                          minute
                        ).padStart(2, "0")}:00`;
                      }
                    }
                    // Fallback: use timestamp if available
                    if (
                      timestampFallback !== undefined &&
                      timestampFallback !== null
                    ) {
                      const date = new Date(timestampFallback * 1000);
                      return `${String(date.getHours()).padStart(
                        2,
                        "0"
                      )}:${String(date.getMinutes()).padStart(2, "0")}:00`;
                    }
                    return null;
                  };

                  const startTime = parseTime(slot.start_formatted, slot.start);
                  const endTime = parseTime(slot.end_formatted, slot.end);

                  // Only add if we have valid times
                  if (!startTime || !endTime) {
                    console.warn("Invalid time for available slot:", slot);
                    return;
                  }

                  transformedSlots.push({
                    schd_day: day,
                    schd_start: startTime,
                    schd_end: endTime,
                    start_time: slot.start_formatted,
                    end_time: slot.end_formatted,
                    subj_code: "Available",
                    subj_desc: "Available Time Slot",
                    instructor_name: "-",
                    sec_name: "-",
                    schd_type: "Available",
                    is_available_slot: true,
                  });
                });
              }
            });

            // Combine occupied schedules with available slots for display
            // Available slots will be rendered with different styling (green/dashed)
            roomScheduleDataCache = [...data.data, ...transformedSlots];
          } else {
            currentRoomScheduleAvailableSlots = {};
          }

          // Update alert message based on whether user is room owner
          const alertEl = document.getElementById("roomScheduleAlert");
          if (alertEl) {
            if (data.is_room_owner === false) {
              // User is viewing a granted room (not the owner)
              alertEl.className = "alert alert-warning mb-3";
              alertEl.innerHTML =
                '<i class="bi bi-info-circle me-2"></i><strong>Granted Room Schedules</strong> - Viewing schedules for a room granted to your department.';
            } else {
              // User is the room owner - show both occupied and available
              const hasAvailable =
                currentRoomScheduleAvailableSlots &&
                Object.keys(currentRoomScheduleAvailableSlots).length > 0 &&
                Object.values(currentRoomScheduleAvailableSlots).some(
                  (slots) => Array.isArray(slots) && slots.length > 0
                );
              const occupiedCount = data.data ? data.data.length : 0;
              if (hasAvailable) {
                alertEl.className = "alert alert-info mb-3";
                alertEl.innerHTML = `<i class="bi bi-calendar-check me-2"></i><strong>Room Schedules</strong> - Showing ${occupiedCount} occupied schedule(s). Available time slots are shown in <span class="badge bg-success">green with dashed border</span>.`;
              } else {
                alertEl.className = "alert alert-info mb-3";
                alertEl.innerHTML = `<i class="bi bi-calendar-check me-2"></i><strong>Occupied Schedules</strong> - ${occupiedCount} schedule(s) assigned to this room.`;
              }
            }
          }
        } else if (
          data.view_type === "available" &&
          typeof data.data === "object" &&
          data.data !== null
        ) {
          // Store original available slots format for request modal (before transformation)
          const availableSlots = data.data;
          currentRoomScheduleAvailableSlots = availableSlots;

          console.log("Stored currentRoomScheduleAvailableSlots:", {
            availableSlots,
            keys: Object.keys(availableSlots),
            slotCount: Object.keys(availableSlots).reduce(
              (sum, day) =>
                sum +
                (Array.isArray(availableSlots[day])
                  ? availableSlots[day].length
                  : 0),
              0
            ),
            currentRoomScheduleAvailableSlots,
            sampleDay:
              availableSlots["Mon"] ||
              availableSlots[Object.keys(availableSlots)[0]] ||
              "no slots",
          });

          // Transform available slots into a format that can be rendered in calendar
          // Convert from { "Mon": [{start, end, ...}], ... } to array format with schd_day, schd_start, schd_end
          // CRITICAL: Ensure time conversion is accurate for proper alignment
          const transformedSlots = [];

          Object.keys(availableSlots).forEach((day) => {
            if (
              Array.isArray(availableSlots[day]) &&
              availableSlots[day].length > 0
            ) {
              availableSlots[day].forEach((slot) => {
                // Convert timestamp to HH:MM:SS format for consistency
                // slot.start and slot.end are Unix timestamps in seconds (from PHP strtotime)
                // CRITICAL: Parse the formatted time strings if available to avoid timezone issues
                let startHH, startMM, endHH, endMM;

                if (slot.start_formatted && slot.end_formatted) {
                  // Parse formatted time strings (e.g., "07:00 AM" or "7:00 AM")
                  const parseFormattedTime = (timeStr) => {
                    const match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
                    if (match) {
                      let hour = parseInt(match[1]);
                      const minute = parseInt(match[2]);
                      const period = match[3].toUpperCase();
                      if (period === "PM" && hour !== 12) hour += 12;
                      if (period === "AM" && hour === 12) hour = 0;
                      return { hour, minute };
                    }
                    return null;
                  };

                  const startParsed = parseFormattedTime(slot.start_formatted);
                  const endParsed = parseFormattedTime(slot.end_formatted);

                  if (startParsed && endParsed) {
                    startHH = String(startParsed.hour).padStart(2, "0");
                    startMM = String(startParsed.minute).padStart(2, "0");
                    endHH = String(endParsed.hour).padStart(2, "0");
                    endMM = String(endParsed.minute).padStart(2, "0");
                  } else {
                    // Fallback to timestamp conversion
                    const startTime = new Date(slot.start * 1000);
                    const endTime = new Date(slot.end * 1000);
                    startHH = String(startTime.getHours()).padStart(2, "0");
                    startMM = String(startTime.getMinutes()).padStart(2, "0");
                    endHH = String(endTime.getHours()).padStart(2, "0");
                    endMM = String(endTime.getMinutes()).padStart(2, "0");
                  }
                } else {
                  // Fallback to timestamp conversion
                  const startTime = new Date(slot.start * 1000);
                  const endTime = new Date(slot.end * 1000);
                  startHH = String(startTime.getHours()).padStart(2, "0");
                  startMM = String(startTime.getMinutes()).padStart(2, "0");
                  endHH = String(endTime.getHours()).padStart(2, "0");
                  endMM = String(endTime.getMinutes()).padStart(2, "0");
                }

                transformedSlots.push({
                  schd_day: day,
                  schd_start: `${startHH}:${startMM}:00`,
                  schd_end: `${endHH}:${endMM}:00`,
                  start_time: slot.start_formatted || `${startHH}:${startMM}`,
                  end_time: slot.end_formatted || `${endHH}:${endMM}`,
                  subj_code: "Available",
                  subj_desc: "Available Time Slot",
                  instructor_name: "-",
                  sec_name: "-",
                  schd_type: "Available",
                  is_available_slot: true, // Flag to identify available slots
                });
              });
            }
          });

          roomScheduleDataCache = transformedSlots;

          // Update alert message for available slots
          const alertEl = document.getElementById("roomScheduleAlert");
          if (alertEl) {
            if (transformedSlots.length === 0) {
              alertEl.className = "alert alert-warning mb-3";
              alertEl.innerHTML =
                '<i class="bi bi-exclamation-triangle me-2"></i><strong>Fully Occupied</strong> - This room has no available time slots during the selected period.';
            } else {
              alertEl.className = "alert alert-success mb-3";
              alertEl.innerHTML = `<i class="bi bi-check-circle me-2"></i><strong>Available Time Slots</strong> - The following ${transformedSlots.length} time slot(s) are available for booking.`;
            }
          }
        } else {
          roomScheduleDataCache = [];
        }
      } else {
        roomScheduleDataCache = [];
      }

      // If modal is already shown, trigger render now that data is ready
      const modalEl = document.getElementById("roomScheduleModal");
      if (modalEl && modalEl.classList.contains("show")) {
        console.log(
          "Modal is already shown, triggering render now that data is ready"
        );
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            renderRoomScheduleCalendar();
          });
        });
      }
    })
    .catch((error) => {
      console.error("Error fetching room schedule:", error);
      roomScheduleDataCache = [];
    });

  // Render ONLY when modal is fully visible AND data is ready
  function renderRoomScheduleOnce() {
    console.log("Modal shown event fired - checking if data is ready");
    window._roomScheduleModalOpening = false; // Reset flag

    // Wait for data to be available (with timeout)
    const checkDataReady = (attempts = 0) => {
      if (
        roomScheduleDataCache !== null &&
        roomScheduleDataCache !== undefined
      ) {
        console.log("Data is ready, rendering calendar grid");
        // Use double requestAnimationFrame to ensure modal DOM is fully ready and rendered
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            renderRoomScheduleCalendar();
          });
        });
      } else if (attempts < 20) {
        // Wait up to 2 seconds (20 * 100ms) for data
        console.log(`Waiting for data... (attempt ${attempts + 1}/20)`);
        setTimeout(() => checkDataReady(attempts + 1), 100);
      } else {
        console.error("Timeout waiting for room schedule data");
        // Show error message but preserve calendar structure
        const loadingEl = document.getElementById("roomScheduleLoading");
        const contentEl = document.getElementById("roomScheduleContent");
        if (loadingEl) loadingEl.style.display = "none";
        if (contentEl) {
          contentEl.style.display = "block";
          // Check if calendar structure exists, if not restore it
          const timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
          const daysWrapper = contentEl.querySelector(
            "#roomScheduleDaysWrapper"
          );
          if (!timeColumn || !daysWrapper) {
            // Restore structure with error message
            contentEl.innerHTML = `
              <div class="alert alert-warning mb-3" id="roomScheduleAlert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Timeout waiting for schedule data. Please try again.
              </div>
              <div class="calendar-container" style="margin-top: 1rem;">
                <div class="calendar-grid" style="display: flex; border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 70vh; overflow-y: auto;">
                  <div class="time-column" id="roomScheduleTimeColumn" style="width: 100px; flex-shrink: 0; background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); border-right: 2px solid #e0e0e0; position: sticky; left: 0; z-index: 10;">
                    <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11;">Time</div>
                  </div>
                  <div class="days-wrapper" id="roomScheduleDaysWrapper" style="display: grid; grid-template-columns: repeat(7, 1fr); flex-grow: 1; background-color: #ffffff;">
                  </div>
                </div>
              </div>
            `;
          } else {
            // Calendar structure exists, just show error above it
            const existingHTML = contentEl.innerHTML;
            contentEl.innerHTML = `
              <div class="alert alert-warning mb-3" id="roomScheduleAlert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Timeout waiting for schedule data. Please try again.
              </div>
              ${existingHTML}
            `;
          }
        }
      }
    };

    // Start checking
    requestAnimationFrame(() => {
      checkDataReady();
    });
  }

  // Also handle modal hidden to reset flag and restore structure
  function handleModalHidden() {
    window._roomScheduleModalOpening = false;
    // Reset cache so next open starts fresh
    // BUT: Don't clear currentRoomScheduleAvailableSlots if we're about to open the request modal
    // (It will be preserved in openRoomRequestModalForSlot via savedAvailableSlots)
    roomScheduleDataCache = null;
    // Only clear if we're not about to open request modal (check if there's a pending request)
    if (!window._pendingRoomRequest) {
      currentRoomScheduleAvailableSlots = {};
    }

    // Reset date filter to default (empty)
    const dateFilterEl = document.getElementById(
      "roomScheduleCalendarDateFilter"
    );
    if (dateFilterEl) {
      dateFilterEl.value = "";
    }

    // Restore calendar grid structure for next open
    const contentEl = document.getElementById("roomScheduleContent");
    if (contentEl) {
      const timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
      const daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");
      if (!timeColumn || !daysWrapper) {
        // Restore structure
        contentEl.innerHTML = `
          <div class="alert alert-info mb-3">
            <i class="bi bi-calendar-check me-2"></i>
            <strong>Occupied Schedules</strong> - The following schedules are assigned to this room.
          </div>
          <div class="calendar-container" style="margin-top: 1rem;">
            <div class="calendar-grid" style="display: flex; border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 70vh; overflow-y: auto;">
              <div class="time-column" id="roomScheduleTimeColumn" style="width: 100px; flex-shrink: 0; background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); border-right: 2px solid #e0e0e0; position: sticky; left: 0; z-index: 10;">
                <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11;">Time</div>
              </div>
              <div class="days-wrapper" id="roomScheduleDaysWrapper" style="display: grid; grid-template-columns: repeat(7, 1fr); flex-grow: 1; background-color: #ffffff;">
              </div>
            </div>
          </div>
        `;
      }
    }
  }

  // Remove any existing listeners to prevent duplicates, then add new ones
  modalEl.removeEventListener("shown.bs.modal", renderRoomScheduleOnce);
  modalEl.removeEventListener("hidden.bs.modal", handleModalHidden);
  modalEl.addEventListener("shown.bs.modal", renderRoomScheduleOnce, {
    once: true,
  });
  modalEl.addEventListener("hidden.bs.modal", handleModalHidden, {
    once: true,
  });

  // Force modal to be visible BEFORE showing (in case CSS is hiding it)
  console.log("Preparing modal for display...");
  modalEl.style.display = "block";
  modalEl.style.visibility = "visible";
  modalEl.style.opacity = "1";
  modalEl.style.zIndex = "1105";
  modalEl.classList.add("show");
  modalEl.removeAttribute("aria-hidden");
  modalEl.setAttribute("aria-modal", "true");

  // Ensure modal-dialog and modal-content are visible
  const modalDialog = modalEl.querySelector(".modal-dialog");
  const modalContent = modalEl.querySelector(".modal-content");
  if (modalDialog) {
    modalDialog.style.display = "block";
    modalDialog.style.visibility = "visible";
  }
  if (modalContent) {
    modalContent.style.display = "block";
    modalContent.style.visibility = "visible";
  }

  // Show the modal - Bootstrap will fire shown.bs.modal when fully visible
  console.log("Calling modal.show()...");
  try {
    modal.show();
    console.log("Modal.show() called successfully");

    // Force visibility immediately and after animation
    const forceVisibility = () => {
      if (modalEl) {
        // Force all visibility styles
        modalEl.style.setProperty("display", "block", "important");
        modalEl.style.setProperty("visibility", "visible", "important");
        modalEl.style.setProperty("opacity", "1", "important");
        modalEl.style.setProperty("z-index", "1105", "important");
        modalEl.classList.add("show");
        modalEl.removeAttribute("aria-hidden");
        modalEl.setAttribute("aria-modal", "true");

        if (modalDialog) {
          modalDialog.style.setProperty("display", "block", "important");
          modalDialog.style.setProperty("visibility", "visible", "important");
        }
        if (modalContent) {
          modalContent.style.setProperty("display", "block", "important");
          modalContent.style.setProperty("visibility", "visible", "important");
        }

        // Ensure backdrop is visible
        const backdrop = document.querySelector(".modal-backdrop");
        if (backdrop) {
          backdrop.classList.add("show");
          backdrop.style.setProperty("display", "block", "important");
          backdrop.style.setProperty("opacity", "1", "important");
          backdrop.style.setProperty("z-index", "1100", "important");
        }

        console.log("Modal visibility forced:", {
          display: modalEl.style.display,
          hasShow: modalEl.classList.contains("show"),
          visibility: modalEl.style.visibility,
          opacity: modalEl.style.opacity,
          zIndex: modalEl.style.zIndex,
          backdropExists: !!backdrop,
          modalDialogVisible: modalDialog
            ? modalDialog.style.visibility
            : "N/A",
          modalContentVisible: modalContent
            ? modalContent.style.visibility
            : "N/A",
        });
      }
    };

    // Force immediately
    forceVisibility();

    // Force again after Bootstrap animation
    setTimeout(forceVisibility, 100);
    setTimeout(forceVisibility, 300);
  } catch (error) {
    console.error("Error showing modal:", error);
    window._roomScheduleModalOpening = false;
    // Fallback: try manual display
    try {
      modalEl.classList.add("show");
      modalEl.style.display = "block";
      modalEl.removeAttribute("aria-hidden");
      document.body.classList.add("modal-open");
      // Create backdrop
      const backdrop = document.createElement("div");
      backdrop.className = "modal-backdrop fade show";
      document.body.appendChild(backdrop);
      // Trigger render manually after a short delay
      setTimeout(() => {
        if (typeof renderRoomScheduleOnce === "function") {
          renderRoomScheduleOnce();
        }
      }, 100);
    } catch (fallbackError) {
      console.error("Fallback modal display also failed:", fallbackError);
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "error",
          title: "Modal Display Error",
          text: "Unable to display the room schedule modal. Please refresh the page and try again.",
          confirmButtonColor: "#800000",
        });
      }
    }
  }
}

/**
 * Render room schedule data into the calendar grid
 * Called only after modal is fully visible (shown.bs.modal)
 */
function renderRoomScheduleCalendar() {
  console.log(
    "renderRoomScheduleCalendar called, cache:",
    roomScheduleDataCache
  );

  // Get modal element first
  const modalEl = document.getElementById("roomScheduleModal");
  if (!modalEl) {
    console.error("Room schedule modal not found");
    return;
  }

  // Query elements - try multiple methods
  let loadingEl = modalEl.querySelector("#roomScheduleLoading");
  let contentEl = modalEl.querySelector("#roomScheduleContent");

  // Fallback to document query
  if (!loadingEl) loadingEl = document.getElementById("roomScheduleLoading");
  if (!contentEl) contentEl = document.getElementById("roomScheduleContent");

  if (!contentEl) {
    console.error("roomScheduleContent not found");
    return;
  }

  // Show content first (elements might not be queryable when hidden)
  if (loadingEl) {
    loadingEl.style.display = "none";
    loadingEl.style.visibility = "hidden";
  }
  if (contentEl) {
    contentEl.style.display = "block";
    contentEl.style.visibility = "visible";
    contentEl.style.opacity = "1";
  }

  // Check if we have schedule data
  if (roomScheduleDataCache === null || roomScheduleDataCache === undefined) {
    console.warn(
      "renderRoomScheduleCalendar: Data cache is null/undefined, waiting for fetch to complete"
    );
    // Don't replace content yet - data is still loading
    return;
  }

  // Check if calendar structure exists, if not restore it
  let timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
  let daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");

  // Fallback queries if still not found
  if (!timeColumn)
    timeColumn = modalEl.querySelector("#roomScheduleTimeColumn");
  if (!daysWrapper)
    daysWrapper = modalEl.querySelector("#roomScheduleDaysWrapper");
  if (!timeColumn)
    timeColumn = document.getElementById("roomScheduleTimeColumn");
  if (!daysWrapper)
    daysWrapper = document.getElementById("roomScheduleDaysWrapper");

  if (!timeColumn || !daysWrapper) {
    // Restore calendar structure
    console.log("Restoring calendar grid structure");
    const alertHTML = contentEl.querySelector("#roomScheduleAlert")
      ? contentEl.querySelector("#roomScheduleAlert").outerHTML
      : "";
    contentEl.innerHTML = `
      ${alertHTML}
      <div class="calendar-container" style="margin-top: 1rem;">
        <div class="calendar-grid" style="display: flex; border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 70vh; overflow-y: auto;">
          <div class="time-column" id="roomScheduleTimeColumn" style="width: 100px; flex-shrink: 0; background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); border-right: 2px solid #e0e0e0; position: sticky; left: 0; z-index: 10;">
            <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11;">Time</div>
          </div>
          <div class="days-wrapper" id="roomScheduleDaysWrapper" style="display: grid; grid-template-columns: repeat(7, 1fr); flex-grow: 1; background-color: #ffffff;">
          </div>
        </div>
      </div>
    `;
    // Re-query after restoring
    timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
    daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");
  }

  if (!timeColumn || !daysWrapper) {
    console.error("Calendar grid elements not found after restoration attempt");
    return;
  }

  // Check if we have schedule data
  if (
    !Array.isArray(roomScheduleDataCache) ||
    roomScheduleDataCache.length === 0
  ) {
    // Generate empty calendar grid structure
    generateRoomScheduleGrid([]);
    // Show "no schedules" message
    const alertEl = document.getElementById("roomScheduleAlert");
    if (alertEl) {
      alertEl.className = "alert alert-info mb-3";
      alertEl.innerHTML =
        '<i class="bi bi-info-circle me-2"></i><strong>No Schedules</strong> - This room has no scheduled classes during the selected period.';
    }
    return;
  }

  // Render as calendar grid (for Rooms tab)
  try {
    // Generate the calendar grid structure first
    generateRoomScheduleGrid(roomScheduleDataCache);
    // Then render the schedules in the grid
    renderRoomScheduleGrid(roomScheduleDataCache);
    console.log("Room schedule calendar grid rendered successfully");
  } catch (error) {
    console.error("Error rendering room schedule calendar grid:", error);
    console.error("Error stack:", error.stack);
    // Show error message
    const alertEl = document.getElementById("roomScheduleAlert");
    if (alertEl) {
      alertEl.className = "alert alert-warning mb-3";
      alertEl.innerHTML =
        '<i class="bi bi-exclamation-triangle me-2"></i><strong>Display Error</strong> - Unable to render calendar view.';
    }
  }
}

/**
 * Open Room Schedule List Modal (for Room Access Grants - shows available slots with request functionality)
 */
function openRoomScheduleListModal(rmId, rmName) {
  console.log("=== openRoomScheduleListModal called ===", { rmId, rmName });
  console.log("Function exists:", typeof openRoomScheduleListModal);

  // Ensure function is globally accessible
  if (typeof window !== "undefined") {
    window.openRoomScheduleListModal = openRoomScheduleListModal;
  }

  // Validate inputs
  if (!rmId || rmId === 0) {
    console.error("Invalid room ID:", rmId);
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Invalid Room",
        text: "Please select a valid room.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Prevent multiple simultaneous opens
  if (window._roomScheduleListModalOpening) {
    console.log("List modal already opening, ignoring duplicate call");
    return;
  }
  window._roomScheduleListModalOpening = true;

  // Store room info for request modal
  activeRoomId = rmId;
  activeRoomName = rmName || "Room";

  let modalEl = document.getElementById("roomScheduleListModal");
  if (!modalEl) {
    console.error("Room schedule list modal not found in DOM");
    window._roomScheduleListModalOpening = false;
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Modal Not Found",
        text: "The room schedule list modal is not available. Please refresh the page.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Ensure modal is directly in body
  if (modalEl.parentElement !== document.body) {
    document.body.appendChild(modalEl);
    modalEl = document.getElementById("roomScheduleListModal");
  }

  // Check Bootstrap availability
  if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
    console.error("Bootstrap 5 Modal is not available.");
    window._roomScheduleListModalOpening = false;
    return;
  }

  // Get or create modal instance
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  // Update modal title
  const modalLabel = document.getElementById("roomScheduleListModalLabel");
  if (modalLabel) {
    modalLabel.textContent = `Available Time Slots: ${rmName || "Room"}`;
  }

  // Update alert message
  const alertEl = document.getElementById("roomScheduleListAlert");
  if (alertEl) {
    alertEl.innerHTML =
      '<i class="bi bi-calendar-check me-2"></i><strong>Available Time Slots</strong> - Loading...';
  }

  // Show loading spinner, hide content
  const loadingEl = document.getElementById("roomScheduleListLoading");
  const contentEl = document.getElementById("roomScheduleListContent");
  if (loadingEl) loadingEl.style.display = "block";
  if (contentEl) {
    contentEl.style.display = "none";
  }

  // Fetch schedule data - for Room Access Grants, user is NOT the owner, so we get available slots
  fetch(`../../admin/management/get_room_schedules.php?rm_id=${rmId}`)
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res.json();
    })
    .then((data) => {
      window._roomScheduleListModalOpening = false;

      // Hide loading, show content
      if (loadingEl) loadingEl.style.display = "none";
      if (contentEl) {
        contentEl.style.display = "block";

        // CRITICAL: Clear any calendar structure that might exist
        contentEl.innerHTML = ""; // Clear everything first

        // For Room Access Grants, show available slots (user is not room owner)
        // The API returns { is_room_owner: false, data: { "Mon": [...], ... } } for available slots
        if (data && data.is_room_owner === false && data.data) {
          // Store available slots for request modal
          currentRoomScheduleAvailableSlots = data.data;

          // Render available slots as table with request buttons
          renderAvailableSlotsTableForListModal(data.data, contentEl);
        } else if (data && Array.isArray(data)) {
          // Fallback: if data is array, filter for available slots
          const availableSlots = data.filter(
            (s) => s.is_available_slot === true
          );
          if (availableSlots.length > 0) {
            renderAvailableSlotsTableList(availableSlots, contentEl);
          } else {
            contentEl.innerHTML = `
              <div class="alert alert-warning mb-3" id="roomScheduleListAlert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Fully Occupied</strong> - This room has no available time slots during the selected period.
          </div>
            `;
          }
        } else {
          contentEl.innerHTML = `
            <div class="alert alert-warning mb-3" id="roomScheduleListAlert">
              <i class="bi bi-exclamation-triangle me-2"></i>
              <strong>Fully Occupied</strong> - This room has no available time slots during the selected period.
              </div>
          `;
        }
      }
    })
    .catch((error) => {
      console.error("Error fetching room schedule:", error);
      window._roomScheduleListModalOpening = false;

      if (loadingEl) loadingEl.style.display = "none";
      if (contentEl) {
        contentEl.style.display = "block";
        contentEl.innerHTML = `
          <div class="alert alert-warning mb-3" id="roomScheduleListAlert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Error</strong> - Failed to load room schedule. Please try again.
              </div>
        `;
      }
    });

  // Handle modal hidden
  function handleListModalHidden() {
    window._roomScheduleListModalOpening = false;
    currentRoomScheduleAvailableSlots = {};

    // Reset date filter to default (empty)
    const dateFilterEl = document.getElementById("roomScheduleDateFilter");
    if (dateFilterEl) {
      dateFilterEl.value = "";
    }
  }

  modalEl.removeEventListener("hidden.bs.modal", handleListModalHidden);
  modalEl.addEventListener("hidden.bs.modal", handleListModalHidden, {
    once: true,
  });

  // Show the modal
  modal.show();
}

/**
 * Render available slots as table for list modal (for Room Access Grants)
 */
function renderAvailableSlotsTableForListModal(availableSlots, contentEl) {
  console.log("Rendering available slots for list modal:", availableSlots);
  console.log("Content element:", contentEl);
  console.log("Content element ID:", contentEl ? contentEl.id : "null");

  if (!contentEl) {
    console.error("Content element not provided");
    return;
  }

  // Make absolutely sure we're not in the calendar modal
  const parentModal = contentEl.closest(".modal");
  if (parentModal && parentModal.id === "roomScheduleModal") {
    console.error(
      "ERROR: renderAvailableSlotsTableForListModal called on calendar modal!"
    );
    console.error("Parent modal ID:", parentModal.id);
    return;
  }

  // Clear any existing calendar structure that might have been accidentally added
  const calendarContainer = contentEl.querySelector(".calendar-container");
  if (calendarContainer) {
    console.warn("Found calendar container in list modal, removing it");
    calendarContainer.remove();
  }

  // Check if there are any available slots
  const hasAvailableSlots = Object.keys(availableSlots).length > 0;

  if (!hasAvailableSlots) {
    contentEl.innerHTML = `
      <div class="alert alert-warning mb-3" id="roomScheduleListAlert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Fully Occupied</strong> - This room has no available time slots during the selected period.
            </div>
    `;
    return;
  }

  // Build availability table
  let html = `
    <div class="alert alert-success mb-3" id="roomScheduleListAlert">
      <i class="bi bi-check-circle me-2"></i>
      <strong>Available Time Slots</strong> - The following times are free for booking. Click "Request" to request a time slot.
          </div>
    <div class="table-responsive" style="max-height: 50vh; overflow-y: auto;">
      <table class="table table-hover table-bordered" style="margin-bottom: 0;">
        <thead class="table-light" style="position: sticky; top: 0; z-index: 10; background-color: #f8f9fa;">
          <tr>
            <th style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Day</th>
            <th style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Available Time</th>
            <th style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Duration</th>
            <th class="text-center" style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Action</th>
          </tr>
        </thead>
        <tbody>
  `;

  const dayOrder = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  let totalSlots = 0;

  dayOrder.forEach((day) => {
    if (availableSlots[day] && availableSlots[day].length > 0) {
      availableSlots[day].forEach((slot) => {
        totalSlots++;
        // Calculate duration in hours and minutes
        const durationMinutes = (slot.end - slot.start) / 60;
        const hours = Math.floor(durationMinutes / 60);
        const minutes = durationMinutes % 60;
        const durationText =
          hours > 0
            ? `${hours} hour${hours > 1 ? "s" : ""}${
                minutes > 0 ? ` ${minutes} min${minutes > 1 ? "s" : ""}` : ""
              }`
            : `${minutes} min${minutes > 1 ? "s" : ""}`;

        // Prepare time slot value for onclick (escape quotes)
        const timeSlotValue = (
          slot.start_formatted +
          " - " +
          slot.end_formatted
        ).replace(/'/g, "\\'");

        html += `
          <tr>
            <td><strong>${day}</strong></td>
            <td>
              <span class="badge bg-success me-2">
                <i class="bi bi-clock me-1"></i>${slot.start_formatted} - ${slot.end_formatted}
              </span>
            </td>
            <td>${durationText}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-primary" onclick="if(typeof openRoomRequestModalForSlot === 'function') { openRoomRequestModalForSlot('${day}', '${timeSlotValue}'); }" title="Request this time slot">
                <i class="bi bi-calendar-plus me-1"></i>Request
              </button>
            </td>
          </tr>
        `;
      });
    }
  });

  html += `
        </tbody>
      </table>
          </div>
    <div class="mt-3">
      <small class="text-muted">
        <i class="bi bi-info-circle me-1"></i>
        Showing ${totalSlots} available time slot(s) for this room.
      </small>
    </div>
  `;

  contentEl.innerHTML = html;

  console.log(`Rendered ${totalSlots} available slot(s) for list modal`);
}

/**
 * Render available slots as table/list (fallback for array format)
 */
function renderAvailableSlotsTableList(schedules, contentEl) {
  console.log(
    "Rendering available slots as table/list (array format):",
    schedules
  );

  if (!contentEl) {
    console.error("Content element not provided");
    return;
  }

  if (schedules.length === 0) {
    contentEl.innerHTML = `
      <div class="alert alert-warning mb-3" id="roomScheduleListAlert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Fully Occupied</strong> - This room has no available time slots during the selected period.
      </div>
    `;
    return;
  }

  // Build table HTML
  let tableHTML = `
    <div class="alert alert-success mb-3" id="roomScheduleListAlert">
      <i class="bi bi-check-circle me-2"></i>
      <strong>Available Time Slots</strong> - The following ${schedules.length} time slot(s) are available for booking.
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-bordered">
        <thead class="table-light">
          <tr>
            <th>Day</th>
            <th>Time Slot</th>
            <th>Duration</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
  `;

  // Sort by day and time
  const dayOrderMap = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  };

  schedules.sort((a, b) => {
    const dayDiff =
      (dayOrderMap[a.schd_day] || 99) - (dayOrderMap[b.schd_day] || 99);
    if (dayDiff !== 0) return dayDiff;
    return (a.schd_start || "").localeCompare(b.schd_start || "");
  });

  schedules.forEach((slot) => {
    // Calculate duration
    const startParts = (slot.schd_start || "").split(":");
    const endParts = (slot.schd_end || "").split(":");
    const startMinutes =
      parseInt(startParts[0] || 0) * 60 + parseInt(startParts[1] || 0);
    const endMinutes =
      parseInt(endParts[0] || 0) * 60 + parseInt(endParts[1] || 0);
    const durationMinutes = endMinutes - startMinutes;
    const hours = Math.floor(durationMinutes / 60);
    const minutes = durationMinutes % 60;
    const durationText =
      hours > 0
        ? `${hours} hour${hours > 1 ? "s" : ""}${
            minutes > 0 ? ` ${minutes} min${minutes > 1 ? "s" : ""}` : ""
          }`
        : `${minutes} min${minutes > 1 ? "s" : ""}`;

    // Get time slot value for request
    let timeSlotValue =
      slot.start_time && slot.end_time
        ? `${slot.start_time} - ${slot.end_time}`
        : `${(slot.schd_start || "").substring(0, 5)} - ${(
            slot.schd_end || ""
          ).substring(0, 5)}`;

    // Escape HTML for safety
    const dayDisplay = escapeHtml(slot.schd_day || "-");
    const timeDisplay = escapeHtml(timeSlotValue);
    const durationDisplay = escapeHtml(durationText);

    // Escape single quotes in timeSlotValue for onclick handler
    const timeSlotValueEscaped = timeSlotValue
      .replace(/'/g, "\\'")
      .replace(/"/g, "&quot;");

    tableHTML += `
      <tr>
        <td><strong>${dayDisplay}</strong></td>
        <td>
          <span class="badge bg-success me-2">
            <i class="bi bi-clock me-1"></i>${timeDisplay}
          </span>
        </td>
        <td>${durationDisplay}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-primary" onclick="if(typeof openRoomRequestModalForSlot === 'function') { openRoomRequestModalForSlot('${dayDisplay}', '${timeSlotValueEscaped}'); }" title="Request this time slot">
            <i class="bi bi-calendar-plus me-1"></i>Request
          </button>
        </td>
      </tr>
    `;
  });

  tableHTML += `
        </tbody>
      </table>
    </div>
  `;

  // Replace content with table
  contentEl.innerHTML = tableHTML;

  console.log(`Rendered ${schedules.length} available slot(s) as table`);
}

/**
 * Simple HTML escape function
 */
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Apply date filter to room schedule calendar modal
function applyRoomScheduleCalendarDateFilter() {
  const dateFilterEl = document.getElementById(
    "roomScheduleCalendarDateFilter"
  );

  if (!dateFilterEl) {
    console.error("Date filter input not found in calendar modal");
    return;
  }

  const dateValue = dateFilterEl.value.trim();
  if (!dateValue) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "Date Required",
        text: "Please select a date to filter room schedules.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Get room ID from activeRoomId or currentRoomScheduleRmId
  const rmId = currentRoomScheduleRmId || activeRoomId;
  if (!rmId) {
    console.error("No room ID available for date filter");
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Room ID not found. Please close and reopen the room schedule.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Set the room ID if not already set
  currentRoomScheduleRmId = rmId;

  // Reload schedule with date filter
  loadRoomSchedule();
}

// Clear date filter from room schedule calendar modal
function clearRoomScheduleCalendarDateFilter() {
  const dateFilterEl = document.getElementById(
    "roomScheduleCalendarDateFilter"
  );

  if (dateFilterEl) {
    dateFilterEl.value = "";
  }

  // Get room ID from activeRoomId or currentRoomScheduleRmId
  const rmId = currentRoomScheduleRmId || activeRoomId;
  if (!rmId) {
    console.error("No room ID available for clearing date filter");
    return;
  }

  // Set the room ID if not already set
  currentRoomScheduleRmId = rmId;

  // Reload schedule without date filter
  loadRoomSchedule();
}

// Make calendar date filter functions globally accessible immediately
if (typeof window !== "undefined") {
  window.applyRoomScheduleCalendarDateFilter =
    applyRoomScheduleCalendarDateFilter;
  window.clearRoomScheduleCalendarDateFilter =
    clearRoomScheduleCalendarDateFilter;
}

function loadRoomSchedule() {
  // Get room ID from currentRoomScheduleRmId or activeRoomId (fallback)
  const rmId = currentRoomScheduleRmId || activeRoomId;
  if (!rmId) {
    console.warn("loadRoomSchedule: No room ID set");
    return;
  }

  // Ensure currentRoomScheduleRmId is set
  if (!currentRoomScheduleRmId) {
    currentRoomScheduleRmId = rmId;
  }

  // Handle duplicate IDs by getting all content containers, but use the one in the visible modal
  let content = document.getElementById("roomScheduleContent");

  // If multiple exist, find the one in the visible modal
  if (!content) {
    const modalElement = document.getElementById("roomScheduleModal");
    if (modalElement && modalElement.classList.contains("show")) {
      content = modalElement.querySelector('[id="roomScheduleContent"]');
    }
  }

  // Fallback: use first content element if found
  if (!content) {
    const contentElements = document.querySelectorAll(
      '[id="roomScheduleContent"]'
    );
    if (contentElements.length > 0) {
      content = contentElements[0];
      console.warn(
        "Using first roomScheduleContent element (duplicate IDs detected)"
      );
    }
  }

  if (!content) {
    console.error("loadRoomSchedule: roomScheduleContent element not found");
    return;
  }

  console.log(
    "loadRoomSchedule: Loading schedule for room",
    currentRoomScheduleRmId
  );

  // Show loading
  content.innerHTML = `
    <div class="text-center py-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading room schedule...</p>
    </div>
  `;

  // Get date filter value if set (from the calendar modal)
  const dateFilterEl = document.getElementById(
    "roomScheduleCalendarDateFilter"
  );
  const dateFilter =
    dateFilterEl && dateFilterEl.value ? dateFilterEl.value.trim() : "";

  const params = new URLSearchParams({ rm_id: currentRoomScheduleRmId });
  if (dateFilter) {
    params.append("date", dateFilter);
  }

  fetch(`../../admin/management/get_room_schedules.php?${params.toString()}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error("Failed to parse JSON response:", text);
          throw new Error("Invalid JSON response from server");
        }
      });
    })
    .then((data) => {
      console.log("Room schedule API response:", data);

      // Check if response indicates failure
      if (!data) {
        console.error("No data in API response");
        content.innerHTML = `<div class="alert alert-danger">No data received from server. Please try again.</div>`;
        return;
      }

      if (data.success === false) {
        content.innerHTML = `<div class="alert alert-danger">${
          data.message || "Failed to load room schedule."
        }</div>`;
        return;
      }

      // If we get here, assume success (either success: true or no success field but has data)
      const viewType = data.view_type || "available"; // 'occupied' or 'available'

      console.log("Processing room schedule response:", {
        viewType,
        hasData: !!data.data,
        dataLength: Array.isArray(data.data)
          ? data.data.length
          : typeof data.data === "object"
          ? Object.keys(data.data).length
          : 0,
      });

      if (viewType === "occupied") {
        // For room owner: Display OCCUPIED schedules
        const schedules = data.data || [];
        console.log("Occupied schedules:", schedules);
        currentRoomScheduleData = schedules; // Store for printing
        currentRoomScheduleAvailableSlots = {}; // Not needed for owner view

        if (schedules.length === 0) {
          console.log("No schedules found for room");
          content.innerHTML = `
            <div class="alert alert-info">
              <i class="bi bi-info-circle me-2"></i>
              No schedules found for this room.
            </div>
          `;
          return;
        }

        // Group schedules by day
        const schedulesByDay = {
          Mon: [],
          Tue: [],
          Wed: [],
          Thu: [],
          Fri: [],
          Sat: [],
          Sun: [],
        };

        schedules.forEach((schedule) => {
          const day = schedule.schd_day;
          if (schedulesByDay[day]) {
            schedulesByDay[day].push(schedule);
          }
        });

        // Render schedule grid instead of table
        let html = `
          <div class="alert alert-info mb-3">
            <i class="bi bi-calendar-check me-2"></i>
            <strong>Occupied Schedules</strong> - The following schedules are assigned to this room.
          </div>
          <div class="calendar-container" style="margin-top: 1rem;">
            <div class="calendar-grid" style="display: flex; border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); max-height: 70vh; overflow-y: auto;">
              <div class="time-column" id="roomScheduleTimeColumn" style="width: 100px; flex-shrink: 0; background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); border-right: 2px solid #e0e0e0; position: sticky; left: 0; z-index: 10;">
                <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11;">Time</div>
              </div>
              <div class="days-wrapper" id="roomScheduleDaysWrapper" style="display: grid; grid-template-columns: repeat(7, 1fr); flex-grow: 1; background-color: #ffffff;">
              </div>
            </div>
          </div>
          <div class="mt-3">
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>
              Showing ${schedules.length} schedule(s) for this room.
            </small>
          </div>
        `;

        console.log("Setting content HTML, schedules count:", schedules.length);

        // Ensure content element exists
        if (!content) {
          console.error("Content element not found when trying to set HTML");
          return;
        }

        // Set the HTML first to replace loading spinner
        content.innerHTML = html;
        console.log("Content HTML set, length:", content.innerHTML.length);

        // Small delay to ensure DOM is updated before accessing elements
        // Use requestAnimationFrame for better performance and DOM readiness
        requestAnimationFrame(() => {
          // Find elements within the content container to avoid conflicts with duplicates
          const timeColumn = content.querySelector("#roomScheduleTimeColumn");
          const daysWrapper = content.querySelector("#roomScheduleDaysWrapper");

          console.log("Looking for grid elements:", {
            timeColumn: !!timeColumn,
            daysWrapper: !!daysWrapper,
            content: !!content,
            contentHTML: content ? content.innerHTML.substring(0, 100) : "N/A",
          });

          // Elements should exist - if not, it's a real error, not a timing issue
          if (!timeColumn || !daysWrapper) {
            console.error(
              "Room schedule grid elements not found after setting HTML",
              {
                timeColumn: !!timeColumn,
                daysWrapper: !!daysWrapper,
                contentHTML: content
                  ? content.innerHTML.substring(0, 300)
                  : "N/A",
                modalVisible: document
                  .getElementById("roomScheduleModal")
                  ?.classList.contains("show"),
                contentChildren: content
                  ? Array.from(content.children).map(
                      (c) => c.tagName + (c.id ? "#" + c.id : "")
                    )
                  : [],
              }
            );
            // Show a simple table as fallback
            if (content && schedules.length > 0) {
              let fallbackHtml = `
                <div class="alert alert-info mb-3">
                  <i class="bi bi-calendar-check me-2"></i>
                  <strong>Occupied Schedules</strong> - The following schedules are assigned to this room.
                </div>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Instructor</th>
                      </tr>
                    </thead>
                    <tbody>
              `;
              schedules.forEach((schedule) => {
                fallbackHtml += `
                  <tr>
                    <td>${schedule.schd_day || "N/A"}</td>
                    <td>${schedule.schd_start || ""} - ${
                  schedule.schd_end || ""
                }</td>
                    <td>${schedule.subj_code || ""} - ${
                  schedule.subj_desc || ""
                }</td>
                    <td>${schedule.instructor_name || "N/A"}</td>
                  </tr>
                `;
              });
              fallbackHtml += `
                    </tbody>
                  </table>
                </div>
              `;
              content.innerHTML = fallbackHtml;
            }
            return;
          }

          console.log(
            "Generating and rendering room schedule grid with",
            schedules.length,
            "schedules"
          );
          // Generate and render grid - modal is guaranteed visible
          try {
            generateRoomScheduleGrid(schedules);
            renderRoomScheduleGrid(schedules);
            console.log("Room schedule grid rendered successfully");
          } catch (error) {
            console.error("Error rendering room schedule grid:", error);
            console.error("Error stack:", error.stack);
            // Show fallback table on error
            if (content && schedules.length > 0) {
              let fallbackHtml = `
                <div class="alert alert-warning mb-3">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  <strong>Schedule Display Error</strong> - Showing simplified view.
                </div>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Instructor</th>
                      </tr>
                    </thead>
                    <tbody>
              `;
              schedules.forEach((schedule) => {
                fallbackHtml += `
                  <tr>
                    <td>${schedule.schd_day || "N/A"}</td>
                    <td>${schedule.schd_start || ""} - ${
                  schedule.schd_end || ""
                }</td>
                    <td>${schedule.subj_code || ""} - ${
                  schedule.subj_desc || ""
                }</td>
                    <td>${schedule.instructor_name || "N/A"}</td>
                  </tr>
                `;
              });
              fallbackHtml += `
                    </tbody>
                  </table>
                </div>
                <div class="alert alert-danger mt-3">
                  <small>Error: ${error.message}</small>
                </div>
              `;
              content.innerHTML = fallbackHtml;
            }
          }
        }, 100);
      } else {
        // For other departments: Display AVAILABLE time slots
        const availableSlots = data.data || {};
        currentRoomScheduleAvailableSlots = availableSlots; // Store for request modal

        // Check if there are any available slots
        const hasAvailableSlots = Object.keys(availableSlots).length > 0;

        if (!hasAvailableSlots) {
          content.innerHTML = `
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle me-2"></i>
              This room is fully occupied during the selected period. No available time slots.
            </div>
          `;
          return;
        }

        // Build availability table
        let html = `
          <div class="alert alert-success mb-3">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Available Time Slots</strong> - The following times are free for booking.
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead class="table-light">
                <tr>
                  <th>Day</th>
                  <th>Available Time</th>
                  <th>Duration</th>
                  <th class="text-center">Request</th>
                </tr>
              </thead>
              <tbody>
        `;

        const dayOrder = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
        let totalSlots = 0;

        dayOrder.forEach((day) => {
          if (availableSlots[day] && availableSlots[day].length > 0) {
            availableSlots[day].forEach((slot) => {
              totalSlots++;
              // Calculate duration in hours and minutes
              const durationMinutes = (slot.end - slot.start) / 60;
              const hours = Math.floor(durationMinutes / 60);
              const minutes = durationMinutes % 60;
              const durationText =
                hours > 0
                  ? `${hours} hour${hours > 1 ? "s" : ""}${
                      minutes > 0
                        ? ` ${minutes} min${minutes > 1 ? "s" : ""}`
                        : ""
                    }`
                  : `${minutes} min${minutes > 1 ? "s" : ""}`;

              // Prepare time slot value for onclick (escape quotes)
              const timeSlotValue = (
                slot.start_formatted +
                " - " +
                slot.end_formatted
              ).replace(/'/g, "\\'");

              html += `
                <tr>
                  <td><strong>${day}</strong></td>
                  <td>
                    <span class="badge bg-success me-2">
                      <i class="bi bi-clock me-1"></i>${slot.start_formatted} - ${slot.end_formatted}
                    </span>
                  </td>
                  <td>${durationText}</td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-primary" onclick="openRoomRequestModalForSlot('${day}', '${timeSlotValue}')" title="Request this time slot">
                      <i class="bi bi-calendar-plus me-1"></i>Request
                    </button>
                  </td>
                </tr>
              `;
            });
          }
        });

        html += `
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>
              Showing ${totalSlots} available time slot(s) for this room.
            </small>
          </div>
        `;

        content.innerHTML = html;
      }
    })
    .catch((error) => {
      console.error("Error loading room schedule:", error);
      console.error("Error details:", {
        message: error.message,
        stack: error.stack,
        name: error.name,
      });
      if (content) {
        content.innerHTML = `<div class="alert alert-danger">
          <strong>Error loading room schedule:</strong><br>
          ${
            error.message ||
            "An error occurred while loading the room schedule. Please try again later."
          }
          <br><small>Check the browser console for more details.</small>
        </div>`;
      }
    });
}

// Room Request Functions
function openRoomRequestModal() {
  if (!activeRoomId || !activeRoomName) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "No Room Selected",
        text: "Please select a room first.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Close schedule modal first
  const scheduleModal = bootstrap.Modal.getInstance(
    document.getElementById("roomScheduleModal")
  );
  if (scheduleModal) {
    scheduleModal.hide();
  }

  // Open request modal without pre-filling day/time
  openRoomRequestModalForSlot(null, null);
}

// Generate room schedule grid structure
function generateRoomScheduleGrid(schedules) {
  // Try multiple query methods to find elements
  const modalEl = document.getElementById("roomScheduleModal");
  let timeColumn = modalEl
    ? modalEl.querySelector("#roomScheduleTimeColumn")
    : null;
  let daysWrapper = modalEl
    ? modalEl.querySelector("#roomScheduleDaysWrapper")
    : null;

  // Fallback to document query
  if (!timeColumn)
    timeColumn = document.getElementById("roomScheduleTimeColumn");
  if (!daysWrapper)
    daysWrapper = document.getElementById("roomScheduleDaysWrapper");

  // Try querying from contentEl if still not found
  if ((!timeColumn || !daysWrapper) && modalEl) {
    const contentEl = modalEl.querySelector("#roomScheduleContent");
    if (contentEl) {
      if (!timeColumn)
        timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
      if (!daysWrapper)
        daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");
    }
  }

  if (!timeColumn || !daysWrapper) {
    console.error("generateRoomScheduleGrid: Required elements not found", {
      modalEl: !!modalEl,
      timeColumn: !!timeColumn,
      daysWrapper: !!daysWrapper,
    });
    return;
  }

  // Clear existing content (keep header)
  const header = timeColumn.querySelector(".calendar-header");
  timeColumn.innerHTML = "";
  if (header) {
    timeColumn.appendChild(header);
  } else {
    const newHeader = document.createElement("div");
    newHeader.className = "calendar-header";
    // CRITICAL: Match exact header height with day column headers
    newHeader.style.cssText =
      "text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 11; box-sizing: border-box;";
    newHeader.textContent = "Time";
    timeColumn.appendChild(newHeader);
  }
  daysWrapper.innerHTML = "";

  // Fixed time range: 7:00 AM to 8:00 PM (exactly, matching Schedule tab's Class Schedule)
  // Use same calculation as Schedule tab: 1px per minute = 60px per hour
  const minHour = 7;
  const maxHour = 20; // 20 = 8:00 PM, this is the last hour to show

  // Store minHour for use in render function
  window.roomScheduleMinHour = minHour;

  // CRITICAL: Ensure time column uses flexbox column layout for proper alignment
  timeColumn.style.display = "flex";
  timeColumn.style.flexDirection = "column";
  timeColumn.style.margin = "0";
  timeColumn.style.padding = "0";

  // Generate time slots - MATCH Schedule tab's Class Schedule structure EXACTLY
  // Schedule tab uses: 60px per hour (1px per minute), showing each hour with :00 and :30 labels
  // Loop from 7 to 20 (inclusive) to show 7:00 AM through 8:00 PM
  // Hours 7-19: show both :00 and :30 (7:00, 7:30, 8:00, 8:30, ..., 7:00 PM, 7:30 PM)
  // Hour 20: show only :00 (8:00 PM), no :30
  for (let hour = minHour; hour <= maxHour; hour++) {
    const timeSlot = document.createElement("div");
    timeSlot.className = "time-slot";
    // CRITICAL: 60px height per hour (same as Schedule tab), 1px per minute
    // This matches the Schedule tab's calendar structure exactly
    timeSlot.style.cssText =
      "height: 60px; min-height: 60px; max-height: 60px; display: flex; align-items: flex-start; justify-content: center; padding-top: 6px; font-size: 0.8rem; font-weight: 600; color: #495057; border-bottom: 1px solid #e9ecef; position: relative; margin: 0; padding-left: 0; padding-right: 0; padding-bottom: 0; box-sizing: border-box; flex-shrink: 0;";

    const hourLabel = document.createElement("span");
    hourLabel.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:00 ${
      hour < 12 ? "AM" : "PM"
    }`;
    timeSlot.appendChild(hourLabel);

    // Only show :30 slot if hour is less than 20 (8:00 PM)
    // Hour 20 (8:00 PM) should be the last slot with no :30
    if (hour < maxHour) {
      const halfHourLabel = document.createElement("div");
      halfHourLabel.className = "time-slot-half";
      // Position at exactly 30px from top (middle of 60px slot)
      halfHourLabel.style.cssText =
        "position: absolute; top: 30px; left: 0; right: 0; text-align: center; font-size: 0.65rem; color: #868e96; font-weight: 400; margin: 0; padding: 0;";
      halfHourLabel.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:30`;
      timeSlot.appendChild(halfHourLabel);
    }

    timeColumn.appendChild(timeSlot);
  }

  // Generate day columns - MATCH Schedule tab's structure EXACTLY
  const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  // Calculate total height: Use 1px per minute (same as Schedule tab)
  // Grid goes from 7:00 AM to 8:00 PM = 13 hours = 780 minutes = 780px
  const gridEndMinutes = maxHour * 60; // 8:00 PM = 1200 minutes from midnight
  const gridStartMinutes = minHour * 60; // 7:00 AM = 420 minutes from midnight
  const totalHeight = gridEndMinutes - gridStartMinutes; // 1200 - 420 = 780px (1px per minute)

  days.forEach((day) => {
    const dayColumn = document.createElement("div");
    dayColumn.className = "day-column";
    dayColumn.id = `room-day-col-${day}`;
    dayColumn.style.cssText =
      "position: relative; border-right: 1px solid #e9ecef; background-color: #ffffff; margin: 0; padding: 0;";
    // CRITICAL: Match header height exactly with time column header
    dayColumn.innerHTML = `
      <div class="calendar-header" style="text-align: center; padding: 1rem 0.5rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; background: linear-gradient(135deg, #800000 0%, #990000 100%); color: #ffffff; border-bottom: 2px solid #660000; position: sticky; top: 0; z-index: 5; text-transform: uppercase; box-sizing: border-box; margin: 0;">${day}</div>
      <div class="day-column-content" id="room-day-content-${day}" style="position: relative; background: repeating-linear-gradient(0deg, transparent, transparent 59px, #f8f9fa 59px, #f8f9fa 60px); height: ${totalHeight}px; min-height: ${totalHeight}px; max-height: ${totalHeight}px; width: 100%; overflow: visible; margin: 0; padding: 0; box-sizing: border-box; display: block;"></div>
    `;
    daysWrapper.appendChild(dayColumn);
  });

  console.log("Calendar grid structure generated successfully", {
    timeSlots: timeColumn.querySelectorAll(".time-slot").length,
    dayColumns: daysWrapper.querySelectorAll(".day-column").length,
    dayContentContainers: Array.from(
      daysWrapper.querySelectorAll('[id^="room-day-content-"]')
    ).map((el) => el.id),
  });

  return true;
}

// Render room schedules in the grid
function renderRoomScheduleGrid(schedules) {
  // Find daysWrapper - try multiple query methods
  const modalEl = document.getElementById("roomScheduleModal");
  let daysWrapper = modalEl
    ? modalEl.querySelector("#roomScheduleDaysWrapper")
    : null;
  if (!daysWrapper)
    daysWrapper = document.getElementById("roomScheduleDaysWrapper");

  // Try querying from contentEl if still not found
  if (!daysWrapper && modalEl) {
    const contentEl = modalEl.querySelector("#roomScheduleContent");
    if (contentEl) {
      daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");
    }
  }

  if (!daysWrapper) {
    console.error(
      "renderRoomScheduleGrid: daysWrapper not found after all attempts"
    );
    return;
  }

  // Clear existing blocks within the found wrapper
  daysWrapper
    .querySelectorAll(".schedule-block")
    .forEach((block) => block.remove());

  // Color scheme for schedule types
  const typeColors = {
    Lec: "#800000", // Maroon
    Lab: "#FF6B35", // Orange
    Special: "#4B0082", // Indigo
  };

  const minHour = window.roomScheduleMinHour || 7;

  schedules.forEach((schedule) => {
    const day = schedule.schd_day || "Mon";
    // Use schd_start/schd_end (TIME format HH:MM:SS) for calculations
    const startTime = schedule.schd_start || "08:00:00";
    const endTime = schedule.schd_end || "09:00:00";
    const scheduleType = schedule.schd_type || "Lec";
    const isAvailableSlot = schedule.is_available_slot === true;

    // Parse time (handle HH:MM:SS format)
    const startParts = startTime.split(":");
    const endParts = endTime.split(":");
    const startHour = parseInt(startParts[0]);
    const startMin = parseInt(startParts[1] || 0);
    const endHour = parseInt(endParts[0]);
    const endMin = parseInt(endParts[1] || 0);

    // Calculate position and height - USE EXACT SAME METHOD AS SCHEDULE TAB (Class Schedule)
    // Schedule tab uses: ((startMinutes - gridStartMinutes) / 60) * 60
    // This simplifies to: (startMinutes - gridStartMinutes) * 1 = 1px per minute
    // Each hour slot is 60px high, so 1px per minute = 60px per hour
    const startMinutes = startHour * 60 + startMin; // Total minutes from midnight
    const endMinutes = endHour * 60 + endMin; // Total minutes from midnight
    const duration = endMinutes - startMinutes; // Duration in minutes
    const gridStartMinutes = minHour * 60; // 7:00 AM = 420 minutes from midnight

    // CRITICAL: Use EXACT same calculation as Schedule tab's Class Schedule
    // topPosition = ((startMinutes - gridStartMinutes) / 60) * 60
    // This equals: (startMinutes - gridStartMinutes) * 1 = 1px per minute
    const topPosition = ((startMinutes - gridStartMinutes) / 60) * 60; // Same as Schedule tab
    let blockHeight = Math.max(60, (duration / 60) * 60); // Minimum 60px, same as Schedule tab

    // CRITICAL: Cap block height at 8:00 PM (20:00 = 20*60 = 1200 minutes from midnight)
    // Grid ends at 8:00 PM, so maximum position is (20*60 - 7*60) = 13*60 = 780px
    const gridEndMinutes = 20 * 60; // 8:00 PM = 1200 minutes from midnight
    const maxBottomPosition = ((gridEndMinutes - gridStartMinutes) / 60) * 60; // 780px (same calculation)

    // If block extends beyond 8:00 PM, clip it
    const blockBottomPosition = topPosition + blockHeight;
    if (blockBottomPosition > maxBottomPosition) {
      blockHeight = Math.max(60, maxBottomPosition - topPosition);
    }

    // Ensure minimum height for visibility (60px minimum, same as Schedule tab)
    const preciseTopPosition = Math.round(topPosition);
    const preciseBlockHeight = Math.max(60, Math.round(blockHeight));

    // Get day content container
    const dayContent = document.getElementById(`room-day-content-${day}`);
    if (!dayContent) {
      console.warn(
        `renderRoomScheduleGrid: Day content container not found for ${day}`
      );
      return;
    }

    // Format time display (convert HH:MM:SS to HH:MM AM/PM)
    function formatTimeForDisplay(timeStr) {
      if (!timeStr) return "";
      const parts = timeStr.split(":");
      const hour = parseInt(parts[0] || 0);
      const min = parts[1] || "00";
      const period = hour >= 12 ? "PM" : "AM";
      const displayHour = hour % 12 || 12;
      return `${displayHour}:${min} ${period}`;
    }

    const timeDisplay =
      schedule.start_time && schedule.end_time
        ? `${schedule.start_time} - ${schedule.end_time}`
        : `${formatTimeForDisplay(startTime)} - ${formatTimeForDisplay(
            endTime
          )}`;

    // Create schedule block
    const block = document.createElement("div");
    block.className = "schedule-block";

    // Different styling for available slots vs occupied schedules
    if (isAvailableSlot) {
      // Available slot styling - green with dashed border
      // Ensure block is absolutely positioned within the relatively positioned day content container
      block.style.cssText = `
        position: absolute;
        left: 4px;
        right: 4px;
        top: ${preciseTopPosition}px;
        height: ${preciseBlockHeight}px;
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border-radius: 8px;
        padding: 0;
        font-size: 0.75rem;
        overflow: hidden;
        cursor: pointer;
        border: 2px dashed #28a745;
        z-index: 2;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        min-height: 60px;
        box-sizing: border-box;
      `;

      // Available slot content - simpler display
      // Find the exact matching slot from currentRoomScheduleAvailableSlots to get the correct format
      let timeSlotForRequest = timeDisplay; // Default fallback
      if (
        currentRoomScheduleAvailableSlots &&
        currentRoomScheduleAvailableSlots[day]
      ) {
        // Find the slot that matches this schedule's time
        const matchingSlot = currentRoomScheduleAvailableSlots[day].find(
          (slot) => {
            // Compare by converting both to timestamps or by formatted time
            const slotStartTime = new Date(slot.start * 1000);
            const slotEndTime = new Date(slot.end * 1000);
            const scheduleStartParts = startTime.split(":");
            const scheduleEndParts = endTime.split(":");
            const scheduleStartHour = parseInt(scheduleStartParts[0]);
            const scheduleStartMin = parseInt(scheduleStartParts[1] || 0);
            const scheduleEndHour = parseInt(scheduleEndParts[0]);
            const scheduleEndMin = parseInt(scheduleEndParts[1] || 0);

            return (
              slotStartTime.getHours() === scheduleStartHour &&
              slotStartTime.getMinutes() === scheduleStartMin &&
              slotEndTime.getHours() === scheduleEndHour &&
              slotEndTime.getMinutes() === scheduleEndMin
            );
          }
        );

        if (matchingSlot) {
          // Use the exact format from the original slot (matches dropdown format)
          timeSlotForRequest = `${matchingSlot.start_formatted} - ${matchingSlot.end_formatted}`;
        }
      }

      block.innerHTML = `
        <div class="schedule-block-content" style="padding: 8px; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
          <div style="font-weight: 800; font-size: 0.9rem; margin-bottom: 4px; color: #155724;">
            <i class="bi bi-clock me-1"></i>Available
          </div>
          <div style="font-size: 0.75rem; color: #155724; font-weight: 600;">${escapeHtml(
            timeDisplay
          )}</div>
          <div style="font-size: 0.65rem; color: #6c757d; margin-top: 4px;">Click to request</div>
        </div>
      `;
      block.title = `Available Time Slot\n${day}\n${timeDisplay}\nClick to request this time slot`;
      // Add click handler to open request modal - use exact format that matches dropdown options
      block.onclick = () => {
        if (typeof openRoomRequestModalForSlot === "function") {
          console.log("Available slot clicked:", {
            day,
            timeSlotForRequest,
            timeDisplay,
          });
          openRoomRequestModalForSlot(day, timeSlotForRequest);
        }
      };
    } else {
      // Occupied schedule styling - USE SAME STYLE AS SCHEDULE TAB (Class Schedule)
      // Use instructor-based color scheme (same as Schedule tab)
      const baseColors = [
        "#28a745", // Green
        "#2E5C8A", // Blue
        "#800000", // Maroon
        "#4B0082", // Indigo
        "#FF6B35", // Orange
        "#006400", // Dark Green
        "#8B4513", // Brown
        "#2F4F4F", // Dark Slate Gray
        "#A0522D", // Sienna
        "#5F9EA0", // Cadet Blue
        "#C71585", // Medium Violet Red
        "#556B2F", // Dark Olive Green
        "#DC143C", // Crimson
        "#20B2AA", // Light Sea Green
        "#9370DB", // Medium Purple
        "#FF6347", // Tomato
        "#32CD32", // Lime Green
        "#1E90FF", // Dodger Blue
        "#FF1493", // Deep Pink
        "#00CED1", // Dark Turquoise
        "#FF8C00", // Dark Orange
        "#8A2BE2", // Blue Violet
        "#228B22", // Forest Green
        "#CD5C5C", // Indian Red
        "#4682B4", // Steel Blue
      ];

      // Helper function to get color for instructor (same as Schedule tab)
      function getColorForInstructor(instId) {
        if (!instId) return baseColors[0];
        const colorIdx = instId % baseColors.length;
        return baseColors[colorIdx];
      }

      // Helper function to darken color for border (same as Schedule tab)
      function darkenColor(color) {
        const hex = color.replace("#", "");
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        const newR = Math.max(0, Math.floor(r * 0.8));
        const newG = Math.max(0, Math.floor(g * 0.8));
        const newB = Math.max(0, Math.floor(b * 0.8));
        return (
          "#" +
          newR.toString(16).padStart(2, "0") +
          newG.toString(16).padStart(2, "0") +
          newB.toString(16).padStart(2, "0")
        );
      }

      const instId = schedule.inst_id || 0;
      const isOvertime =
        schedule.is_overtime === "Yes" || schedule.is_overtime === true;

      // Apply overtime styling if needed
      let overtimeBadge = "";
      if (isOvertime) {
        block.classList.add("overtime-block");
        overtimeBadge = '<span class="badge bg-danger mb-1">Overtime</span>';
      }

      // Format instructor name (abbreviated: First Initial. Last Name) - same as Schedule tab
      let instructorDisplay = "";
      if (schedule.instructor_name) {
        const nameParts = schedule.instructor_name.trim().split(" ");
        if (nameParts.length >= 2) {
          const firstName = nameParts[0];
          const lastName = nameParts[nameParts.length - 1];
          instructorDisplay = `${firstName.charAt(0)}. ${lastName}`;
        } else {
          instructorDisplay = schedule.instructor_name;
        }
      }

      // Format room display (just room name, no building prefix) - same as Schedule tab
      let roomDisplay = schedule.rm_name || "";

      // Check if this is a virtual/online class
      const isVirtual =
        schedule.bd_desc && schedule.bd_desc.trim().toUpperCase() === "VIRTUAL";
      const virtualBadge = isVirtual
        ? '<span class="badge bg-info mb-1" style="font-size: 0.65rem;"><i class="bi bi-laptop me-1"></i>Online</span>'
        : "";

      // Get colors
      const instructorColor = isOvertime
        ? "#dc3545"
        : getColorForInstructor(instId);
      const borderColor = isOvertime ? "#bd2130" : darkenColor(instructorColor);

      // USE SAME BLOCK STRUCTURE AS SCHEDULE TAB (Class Schedule)
      block.style.cssText = `
        position: absolute;
        left: 8px;
        right: 8px;
        top: ${preciseTopPosition}px;
        height: ${preciseBlockHeight}px;
        background: ${instructorColor};
        color: #ffffff;
        border-radius: 0 8px 8px 0;
        padding: 0;
        font-size: 0.75rem;
        overflow: hidden;
        cursor: pointer;
        border-left: 5px solid ${borderColor};
        border-top: 1px solid rgba(255, 255, 255, 0.3);
        z-index: 2;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3), 0 2px 6px rgba(0, 0, 0, 0.2);
        min-height: 1px;
        box-sizing: border-box;
      `;

      // Occupied schedule content - USE SAME STRUCTURE AS SCHEDULE TAB
      const subjCode = schedule.subj_code || "-";
      const subjDesc = schedule.subj_desc || "";

      // Match Schedule tab's block content structure exactly
      block.innerHTML = `
        <div class="schedule-block-content">
          ${overtimeBadge}
          ${virtualBadge}
          <div class="subj-code-simple">${escapeHtml(subjCode)}</div>
          <div class="instructor-simple">${escapeHtml(instructorDisplay)}</div>
          <div class="room-simple">${escapeHtml(roomDisplay)}</div>
          <div class="time-simple">${escapeHtml(timeDisplay)}</div>
          <div class="type-simple">${schedule.schd_type || "Lec"}</div>
        </div>
      `;
      block.title = `${subjDesc}\n${
        schedule.instructor_name || ""
      }\n${timeDisplay}`;
      if (schedule.schd_id) {
        block.setAttribute("data-schd-id", schedule.schd_id);
      }
      block.setAttribute("data-type", schedule.schd_type || "Lec");
    }

    // Verify block is valid before appending
    if (!block || !dayContent) {
      console.error("Cannot append block: block or dayContent is null", {
        block: !!block,
        dayContent: !!dayContent,
        day,
      });
      return;
    }

    // Append block to day content container
    try {
      dayContent.appendChild(block);
      console.log(
        `Successfully appended block to ${day} at position ${topPosition}px, height ${blockHeight}px`,
        {
          day,
          startTime,
          endTime,
          topPosition,
          blockHeight,
          isAvailableSlot,
          dayContentHeight: dayContent.style.height || dayContent.offsetHeight,
        }
      );
    } catch (error) {
      console.error(`Error appending block to ${day}:`, error, {
        block,
        dayContent,
        day,
      });
    }
  });

  // Verify blocks were actually rendered
  const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  days.forEach((day) => {
    const dayContent = document.getElementById(`room-day-content-${day}`);
    if (dayContent) {
      const blocks = dayContent.querySelectorAll(".schedule-block");
      console.log(`${day}: ${blocks.length} block(s) rendered`);
    }
  });

  console.log(
    `Finished rendering ${schedules.length} schedule(s) in calendar grid`
  );
}

// Render available slots as a simple table
function renderAvailableSlotsTable(schedules) {
  console.log("Rendering available slots as table:", schedules);

  // Get modal content element
  const modalEl = document.getElementById("roomScheduleModal");
  if (!modalEl) {
    console.error("Room schedule modal not found");
    return;
  }

  const contentEl = modalEl.querySelector("#roomScheduleContent");
  if (!contentEl) {
    console.error("roomScheduleContent not found");
    return;
  }

  // Hide loading, show content
  const loadingEl = document.getElementById("roomScheduleLoading");
  if (loadingEl) loadingEl.style.display = "none";
  contentEl.style.display = "block";

  // Filter only available slots
  const availableSlots = schedules.filter((s) => s.is_available_slot === true);

  if (availableSlots.length === 0) {
    contentEl.innerHTML = `
      <div class="alert alert-warning mb-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Fully Occupied</strong> - This room has no available time slots during the selected period.
      </div>
    `;
    return;
  }

  // Build table HTML
  let tableHTML = `
    <div class="alert alert-success mb-3">
      <i class="bi bi-check-circle me-2"></i>
      <strong>Available Time Slots</strong> - The following ${availableSlots.length} time slot(s) are available for booking.
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-bordered">
        <thead class="table-light">
          <tr>
            <th>Day</th>
            <th>Time Slot</th>
            <th>Duration</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
  `;

  // Sort by day and time
  const dayOrder = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  const dayOrderMap = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  };

  availableSlots.sort((a, b) => {
    const dayDiff =
      (dayOrderMap[a.schd_day] || 99) - (dayOrderMap[b.schd_day] || 99);
    if (dayDiff !== 0) return dayDiff;
    return a.schd_start.localeCompare(b.schd_start);
  });

  availableSlots.forEach((slot) => {
    // Calculate duration
    const startParts = slot.schd_start.split(":");
    const endParts = slot.schd_end.split(":");
    const startMinutes =
      parseInt(startParts[0]) * 60 + parseInt(startParts[1] || 0);
    const endMinutes = parseInt(endParts[0]) * 60 + parseInt(endParts[1] || 0);
    const durationMinutes = endMinutes - startMinutes;
    const hours = Math.floor(durationMinutes / 60);
    const minutes = durationMinutes % 60;
    const durationText =
      hours > 0
        ? `${hours} hour${hours > 1 ? "s" : ""}${
            minutes > 0 ? ` ${minutes} min${minutes > 1 ? "s" : ""}` : ""
          }`
        : `${minutes} min${minutes > 1 ? "s" : ""}`;

    // Get time slot value for request - use exact format from currentRoomScheduleAvailableSlots
    // Find the matching slot in currentRoomScheduleAvailableSlots to get the exact format
    let timeSlotValue =
      slot.start_time && slot.end_time
        ? `${slot.start_time} - ${slot.end_time}`
        : `${slot.schd_start.substring(0, 5)} - ${slot.schd_end.substring(
            0,
            5
          )}`;

    // Try to get exact format from currentRoomScheduleAvailableSlots
    if (
      currentRoomScheduleAvailableSlots &&
      currentRoomScheduleAvailableSlots[slot.schd_day]
    ) {
      const matchingSlot = currentRoomScheduleAvailableSlots[
        slot.schd_day
      ].find((origSlot) => {
        const origStartTime = new Date(origSlot.start * 1000);
        const origEndTime = new Date(origSlot.end * 1000);
        const slotStartParts = slot.schd_start.split(":");
        const slotEndParts = slot.schd_end.split(":");
        const slotStartHour = parseInt(slotStartParts[0]);
        const slotStartMin = parseInt(slotStartParts[1] || 0);
        const slotEndHour = parseInt(slotEndParts[0]);
        const slotEndMin = parseInt(slotEndParts[1] || 0);

        return (
          origStartTime.getHours() === slotStartHour &&
          origStartTime.getMinutes() === slotStartMin &&
          origEndTime.getHours() === slotEndHour &&
          origEndTime.getMinutes() === slotEndMin
        );
      });

      if (matchingSlot) {
        // Use the exact format from the original slot (matches dropdown format)
        timeSlotValue = `${matchingSlot.start_formatted} - ${matchingSlot.end_formatted}`;
      }
    }

    // Escape HTML for safety
    const dayDisplay = escapeHtml(slot.schd_day);
    const timeDisplay = escapeHtml(timeSlotValue);
    const durationDisplay = escapeHtml(durationText);

    // Escape single quotes in timeSlotValue for onclick handler
    const timeSlotValueEscaped = timeSlotValue
      .replace(/'/g, "\\'")
      .replace(/"/g, "&quot;");

    tableHTML += `
      <tr>
        <td><strong>${dayDisplay}</strong></td>
        <td>
          <span class="badge bg-success me-2">
            <i class="bi bi-clock me-1"></i>${timeDisplay}
          </span>
        </td>
        <td>${durationDisplay}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-primary" onclick="if(typeof openRoomRequestModalForSlot === 'function') { openRoomRequestModalForSlot('${dayDisplay}', '${timeSlotValueEscaped}'); }" title="Request this time slot">
            <i class="bi bi-calendar-plus me-1"></i>Request
          </button>
        </td>
      </tr>
    `;
  });

  tableHTML += `
        </tbody>
      </table>
    </div>
  `;

  // Replace content with table
  contentEl.innerHTML = tableHTML;

  console.log(`Rendered ${availableSlots.length} available slot(s) as table`);
}

// Render occupied schedules as a simple table
function renderOccupiedSchedulesTable(schedules) {
  console.log("Rendering occupied schedules as table:", schedules);

  // Get modal content element
  const modalEl = document.getElementById("roomScheduleModal");
  if (!modalEl) {
    console.error("Room schedule modal not found");
    return;
  }

  const contentEl = modalEl.querySelector("#roomScheduleContent");
  if (!contentEl) {
    console.error("roomScheduleContent not found");
    return;
  }

  // Hide loading, show content
  const loadingEl = document.getElementById("roomScheduleLoading");
  if (loadingEl) loadingEl.style.display = "none";
  contentEl.style.display = "block";

  // Filter out available slots (only show occupied schedules)
  const occupiedSchedules = schedules.filter(
    (s) => !s.is_available_slot || s.is_available_slot !== true
  );

  if (occupiedSchedules.length === 0) {
    contentEl.innerHTML = `
      <div class="alert alert-info mb-3">
        <i class="bi bi-info-circle me-2"></i>
        <strong>No Schedules</strong> - This room has no scheduled classes during the selected period.
      </div>
    `;
    return;
  }

  // Build table HTML
  let tableHTML = `
    <div class="alert alert-info mb-3">
      <i class="bi bi-calendar-check me-2"></i>
      <strong>Occupied Schedules</strong> - The following ${occupiedSchedules.length} schedule(s) are assigned to this room.
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-bordered">
        <thead class="table-light">
          <tr>
            <th>Day</th>
            <th>Time</th>
            <th>Subject</th>
            <th>Instructor</th>
            <th>Section</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody>
  `;

  // Sort by day and time
  const dayOrderMap = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  };

  occupiedSchedules.sort((a, b) => {
    const dayDiff =
      (dayOrderMap[a.schd_day] || 99) - (dayOrderMap[b.schd_day] || 99);
    if (dayDiff !== 0) return dayDiff;
    return (a.schd_start || "").localeCompare(b.schd_start || "");
  });

  occupiedSchedules.forEach((schedule) => {
    // Format time display
    const timeDisplay =
      schedule.start_time && schedule.end_time
        ? `${schedule.start_time} - ${schedule.end_time}`
        : `${schedule.schd_start || ""} - ${schedule.schd_end || ""}`;

    // Escape HTML for safety
    const dayDisplay = escapeHtml(schedule.schd_day || "-");
    const timeDisplayEscaped = escapeHtml(timeDisplay);
    const subjCode = escapeHtml(schedule.subj_code || "-");
    const subjDesc = escapeHtml(schedule.subj_desc || "");
    const instructor = escapeHtml(schedule.instructor_name || "-");
    const section = escapeHtml(schedule.sec_name || "-");
    const scheduleType = escapeHtml(schedule.schd_type || "-");

    tableHTML += `
      <tr>
        <td><strong>${dayDisplay}</strong></td>
        <td>${timeDisplayEscaped}</td>
        <td>
          <div><strong>${subjCode}</strong></div>
          <small class="text-muted">${subjDesc}</small>
        </td>
        <td>${instructor}</td>
        <td>${section}</td>
        <td><span class="badge bg-secondary">${scheduleType}</span></td>
      </tr>
    `;
  });

  tableHTML += `
        </tbody>
      </table>
    </div>
  `;

  // Replace content with table
  contentEl.innerHTML = tableHTML;

  console.log(
    `Rendered ${occupiedSchedules.length} occupied schedule(s) as table`
  );
}

// Render occupied schedules as a simple table
function renderOccupiedSchedulesTable(schedules) {
  console.log("Rendering occupied schedules as table:", schedules);

  // Get modal content element
  const modalEl = document.getElementById("roomScheduleModal");
  if (!modalEl) {
    console.error("Room schedule modal not found");
    return;
  }

  const contentEl = modalEl.querySelector("#roomScheduleContent");
  if (!contentEl) {
    console.error("roomScheduleContent not found");
    return;
  }

  // Hide loading, show content
  const loadingEl = document.getElementById("roomScheduleLoading");
  if (loadingEl) loadingEl.style.display = "none";
  contentEl.style.display = "block";

  // Filter out available slots (only show occupied schedules)
  const occupiedSchedules = schedules.filter(
    (s) => !s.is_available_slot || s.is_available_slot !== true
  );

  if (occupiedSchedules.length === 0) {
    contentEl.innerHTML = `
      <div class="alert alert-info mb-3">
        <i class="bi bi-info-circle me-2"></i>
        <strong>No Schedules</strong> - This room has no scheduled classes during the selected period.
      </div>
    `;
    return;
  }

  // Build table HTML
  let tableHTML = `
    <div class="alert alert-info mb-3">
      <i class="bi bi-calendar-check me-2"></i>
      <strong>Occupied Schedules</strong> - The following ${occupiedSchedules.length} schedule(s) are assigned to this room.
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-bordered">
        <thead class="table-light">
          <tr>
            <th>Day</th>
            <th>Time</th>
            <th>Subject</th>
            <th>Instructor</th>
            <th>Section</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody>
  `;

  // Sort by day and time
  const dayOrderMap = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  };

  occupiedSchedules.sort((a, b) => {
    const dayDiff =
      (dayOrderMap[a.schd_day] || 99) - (dayOrderMap[b.schd_day] || 99);
    if (dayDiff !== 0) return dayDiff;
    return (a.schd_start || "").localeCompare(b.schd_start || "");
  });

  occupiedSchedules.forEach((schedule) => {
    // Format time display
    const timeDisplay =
      schedule.start_time && schedule.end_time
        ? `${schedule.start_time} - ${schedule.end_time}`
        : `${schedule.schd_start || ""} - ${schedule.schd_end || ""}`;

    // Escape HTML for safety
    const dayDisplay = escapeHtml(schedule.schd_day || "-");
    const timeDisplayEscaped = escapeHtml(timeDisplay);
    const subjCode = escapeHtml(schedule.subj_code || "-");
    const subjDesc = escapeHtml(schedule.subj_desc || "");
    const instructor = escapeHtml(schedule.instructor_name || "-");
    const section = escapeHtml(schedule.sec_name || "-");
    const scheduleType = escapeHtml(schedule.schd_type || "-");

    tableHTML += `
      <tr>
        <td><strong>${dayDisplay}</strong></td>
        <td>${timeDisplayEscaped}</td>
        <td>
          <div><strong>${subjCode}</strong></div>
          <small class="text-muted">${subjDesc}</small>
        </td>
        <td>${instructor}</td>
        <td>${section}</td>
        <td><span class="badge bg-secondary">${scheduleType}</span></td>
      </tr>
    `;
  });

  tableHTML += `
        </tbody>
      </table>
    </div>
  `;

  // Replace content with table
  contentEl.innerHTML = tableHTML;

  console.log(
    `Rendered ${occupiedSchedules.length} occupied schedule(s) as table`
  );
}

// Print room schedule function - matches schedule tab page format
function printRoomSchedule() {
  if (!activeRoomId || !activeRoomName) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "No Room Selected",
        text: "Please select a room first.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Get schedule data from cache
  let schedules = roomScheduleDataCache || [];

  if (!schedules || schedules.length === 0) {
    Swal.fire({
      icon: "error",
      title: "Schedule Not Found",
      text: "No schedule data available to print.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Create a new window for printing
  const printWindow = window.open("", "_blank");

  if (!printWindow) {
    Swal.fire({
      icon: "error",
      title: "Print Error",
      text: "Please allow popups to print the schedule.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Set document type
  printWindow.document.open("text/html", "replace");

  // Get logo path
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/").filter((p) => p);
  const rootPath = pathParts.slice(0, -3).join("/");
  const logoPath =
    window.location.origin +
    (rootPath ? "/" + rootPath : "") +
    "/public/assets/img/evsu-logo.png";

  // Day order matching calendar: MON, TUE, WED, THU, FRI, SAT, SUN
  const days = ["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"];
  const dayMap = {
    Mon: "MON",
    Tue: "TUE",
    Wed: "WED",
    Thu: "THU",
    Fri: "FRI",
    Sat: "SAT",
    Sun: "SUN",
  };

  // Generate time slots from 7:00 AM to 8:30 PM with 30-minute intervals
  const timeSlots = [];
  for (let hour = 7; hour <= 20; hour++) {
    const period = hour < 12 ? "AM" : "PM";
    const displayHour = hour === 12 ? 12 : hour > 12 ? hour % 12 : hour;
    const hourLabel = `${displayHour}:00 ${period}`;

    // Add main hour slot
    timeSlots.push({
      hour: hour,
      label: hourLabel,
      minutes: 0,
      totalMinutes: hour * 60,
    });

    // Add 30-minute slot
    if (hour < 20) {
      timeSlots.push({
        hour: hour,
        label: `${displayHour}:30`,
        minutes: 30,
        totalMinutes: hour * 60 + 30,
      });
    }
  }

  // Add 8:30 PM as the last slot
  timeSlots.push({
    hour: 20,
    label: "8:30 PM",
    minutes: 30,
    totalMinutes: 20 * 60 + 30,
  });

  // Parse time string to minutes from midnight
  const parseTimeToMinutes = (timeStr) => {
    if (!timeStr) return null;
    // Handle HH:MM:SS format
    const parts = timeStr.split(":");
    if (parts.length >= 2) {
      const hour = parseInt(parts[0]);
      const min = parseInt(parts[1] || 0);
      return hour * 60 + min;
    }
    // Handle "10:00 AM" format
    const match = timeStr.trim().match(/(\d+):(\d+)\s*(AM|PM|am|pm)/i);
    if (match) {
      let hour = parseInt(match[1]);
      const min = parseInt(match[2]);
      const period = match[3].toUpperCase();
      if (period === "PM" && hour !== 12) hour += 12;
      if (period === "AM" && hour === 12) hour = 0;
      return hour * 60 + min;
    }
    return null;
  };

  // Format time for display (from HH:MM:SS to 12-hour format)
  const formatTimeForDisplay = (timeStr) => {
    if (!timeStr) return "";
    const parts = timeStr.split(":");
    if (parts.length >= 2) {
      const hour = parseInt(parts[0]);
      const min = parts[1] || "00";
      const period = hour < 12 ? "AM" : "PM";
      const displayHour =
        hour === 0 ? 12 : hour === 12 ? 12 : hour > 12 ? hour % 12 : hour;
      return `${displayHour}:${min} ${period}`;
    }
    return timeStr;
  };

  // Get schedule type colors
  const typeColors = {
    Lec: "#800000", // Maroon
    Lab: "#FF6B35", // Orange
    Special: "#4B0082", // Indigo
  };

  // Use transformed schedule data
  // Process schedules
  const processedSchedules = schedules
    .map((s) => {
      const day = dayMap[s.schd_day] || s.schd_day;
      const startTime = s.schd_start || "";
      const endTime = s.schd_end || "";
      const startMinutes = parseTimeToMinutes(startTime);
      const endMinutes = parseTimeToMinutes(endTime);
      const scheduleType = s.schd_type || "Lec";
      const isOvertime = s.is_overtime === "Yes" || s.is_overtime === true;

      // Format instructor name (abbreviated: First Initial. Last Name)
      let instructorDisplay = "";
      if (s.instructor_name) {
        const nameParts = s.instructor_name.trim().split(" ");
        if (nameParts.length >= 2) {
          const firstName = nameParts[0];
          const lastName = nameParts[nameParts.length - 1];
          instructorDisplay = `${firstName.charAt(0)}. ${lastName}`;
        } else {
          instructorDisplay = s.instructor_name;
        }
      }

      return {
        day: day,
        subjCode: s.subj_code || "",
        subjDesc: s.subj_desc || "",
        instructor: instructorDisplay || s.instructor_name || "",
        section: s.sec_name || "",
        time: `${formatTimeForDisplay(startTime)} - ${formatTimeForDisplay(
          endTime
        )}`,
        type: scheduleType,
        startMinutes: startMinutes,
        endMinutes: endMinutes,
        startTime: formatTimeForDisplay(startTime),
        endTime: formatTimeForDisplay(endTime),
        isOvertime: isOvertime,
        color: isOvertime
          ? "#dc3545"
          : typeColors[scheduleType] || typeColors["Lec"],
      };
    })
    .filter(
      (s) =>
        s.startMinutes !== null &&
        s.endMinutes !== null &&
        s.startMinutes < s.endMinutes
    );

  // Track which schedules have been rendered to avoid duplicates
  const renderedSchedules = new Set();

  // Build table rows
  let tableRows = "";
  timeSlots.forEach((timeSlot) => {
    tableRows += "<tr>";

    // Time cell
    const isHalfHour = timeSlot.minutes === 30;
    if (isHalfHour) {
      tableRows += `<td class="time-cell time-half">${timeSlot.label}</td>`;
    } else {
      tableRows += `<td class="time-cell time-main">${timeSlot.label}</td>`;
    }

    // Day cells
    days.forEach((day) => {
      let cellContent = "";

      // Find schedules that START in this time slot
      const cellSchedules = processedSchedules.filter((s) => {
        if (s.day !== day) return false;

        // Create unique ID for this schedule
        const scheduleId = `${s.day}-${s.subjCode}-${s.startMinutes}-${s.endMinutes}`;

        // Only show if it starts in this exact time slot and hasn't been rendered yet
        const slotStart = timeSlot.totalMinutes;
        const slotEnd = slotStart + 30; // 30-minute slots
        const startsInSlot =
          s.startMinutes >= slotStart && s.startMinutes < slotEnd;

        if (startsInSlot && !renderedSchedules.has(scheduleId)) {
          renderedSchedules.add(scheduleId);
          return true;
        }
        return false;
      });

      // Build cell content
      if (cellSchedules.length > 0) {
        cellSchedules.forEach((sched) => {
          // Calculate how many 30-minute intervals this schedule spans
          const duration = sched.endMinutes - sched.startMinutes;
          const intervalsToSpan = Math.max(1, Math.ceil(duration / 30));
          const rowHeight = 13; // Compact row height
          const totalHeight = intervalsToSpan * rowHeight - 2;

          const overtimeClass = sched.isOvertime ? "overtime-schedule" : "";
          // Format: Subject Code (bold), Instructor, Section, Time, Type
          cellContent += `<div class="schedule-item ${overtimeClass}" style="height: ${totalHeight}px; background: ${
            sched.color
          }; border-left: 4px solid ${
            sched.isOvertime ? "#bd2130" : sched.color
          };">
            <div class="subj-code-print"><strong>${escapeHtml(
              sched.subjCode
            )}</strong></div>
            <div class="instructor-print">${escapeHtml(sched.instructor)}</div>
            <div class="room-print">${escapeHtml(sched.section)}</div>
            <div class="time-print">${escapeHtml(sched.time)}</div>
            <div class="type-print">${escapeHtml(sched.type)}</div>
          </div>`;
        });
      }

      tableRows += `<td class="day-cell">${cellContent}</td>`;
    });

    tableRows += "</tr>";
  });

  // Write the print document - matching schedule tab page format
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Room Schedule - Print</title>
      <style>
        @page {
          margin: 0.3cm;
          size: letter portrait;
        }
        
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        
        body {
          font-family: 'Poppins', 'Arial', 'Helvetica', sans-serif;
          font-size: 9pt;
          line-height: 1.2;
          color: #000;
          background: #fff;
          padding: 10px;
        }
        
        .print-header {
          margin-bottom: 8px;
        }
        
        .header-top {
          display: flex;
          align-items: flex-start;
          margin-bottom: 8px;
        }
        
        .logo-section {
          margin-right: 15px;
        }
        
        .logo-section img {
          height: 50px;
          width: auto;
        }
        
        .university-info {
          flex: 1;
          text-align: center;
        }
        
        .republic-text {
          font-size: 10pt;
          color: #000;
          margin-bottom: 3px;
        }
        
        .university-name {
          font-size: 14pt;
          font-weight: bold;
          color: #800000;
          margin-bottom: 3px;
          text-transform: uppercase;
        }
        
        .campus-name {
          font-size: 11pt;
          color: #000;
          margin-bottom: 3px;
        }
        
        .office-name {
          font-size: 12pt;
          font-weight: bold;
          color: #800000;
          margin-top: 8px;
          padding-top: 8px;
          border-top: 2px solid #800000;
          text-transform: uppercase;
          letter-spacing: 1px;
        }
        
        .form-fields {
          display: flex;
          gap: 20px;
          margin-bottom: 5px;
        }
        
        .header-field {
          flex: 1;
        }
        
        .header-field label {
          font-size: 7pt;
          font-weight: 600;
          display: block;
          margin-bottom: 2px;
          color: #000;
        }
        
        .header-field input {
          border: none;
          border-bottom: 2px solid #000;
          font-size: 7pt;
          padding: 1px 0;
          width: 100%;
          background: transparent;
          outline: none;
        }
        
        /* Schedule Table Styles - EVSU-OCC Theme */
        .schedule-table {
          width: 100%;
          border-collapse: collapse;
          border: 2px solid #800000;
          margin: 0 auto;
          background: #fff;
        }
        
        .schedule-table th {
          background: #800000;
          color: #fff;
          font-weight: bold;
          padding: 4px 3px;
          text-align: center;
          border: 1px solid #660000;
          font-size: 7pt;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }
        
        .schedule-table th.time-header {
          background: #800000;
          width: 70px;
        }
        
        .schedule-table td {
          border: 1px solid #ccc;
          padding: 1px;
          vertical-align: top;
          height: 10px;
        }
        
        .schedule-table td.time-cell {
          background: #f5f5f5;
          font-weight: 600;
          text-align: center;
          border-right: 2px solid #800000;
          padding: 2px 3px;
          width: 70px;
          font-size: 7pt;
        }
        
        .time-main {
          font-size: 7pt;
          font-weight: bold;
        }
        
        .time-half {
          font-size: 6pt;
          color: #666;
        }
        
        .schedule-table td.day-cell {
          background: #fff;
          position: relative;
          height: 10px;
          vertical-align: top;
          padding: 0;
        }
        
        .schedule-item {
          border: none;
          border-radius: 2px;
          padding: 1px 2px;
          margin: 0;
          font-size: 5pt;
          page-break-inside: avoid;
          text-align: center;
          display: flex;
          flex-direction: column;
          justify-content: center;
          width: 100%;
          box-sizing: border-box;
          color: #fff;
          overflow: hidden;
        }
        
        .schedule-item.overtime-schedule {
          background: #dc3545 !important;
          border-left: 2px solid #bd2130 !important;
        }
        
        .subj-code-print {
          font-size: 6pt;
          font-weight: 900;
          margin-bottom: 0px;
          color: #fff;
        }
        
        .instructor-print, .room-print, .time-print, .type-print {
          font-size: 4pt;
          line-height: 0.9;
          color: #fff;
          margin: 0;
        }
        
        .schedule-item strong {
          font-weight: 900;
        }
        
        .print-footer {
          margin-top: 10px;
          padding-top: 8px;
          border-top: 2px solid #800000;
          text-align: center;
          font-size: 7pt;
          color: #666;
        }
        
        .print-footer p {
          margin: 2px 0;
        }
        
        @media print {
          body {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
            transform: scale(0.85);
            transform-origin: top left;
            width: 117.65%;
          }
          
          .print-header {
            page-break-after: avoid;
          }
          
          .schedule-table {
            page-break-inside: avoid;
          }
          
          .schedule-item {
            page-break-inside: avoid;
          }
          
          * {
            page-break-inside: avoid;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-header">
        <div class="header-top">
          <div class="logo-section">
            <img src="${logoPath}" alt="EVSU Logo" onerror="this.style.display='none'">
          </div>
          <div class="university-info">
            <div class="republic-text">Republic of the Philippines</div>
            <div class="university-name">EASTERN VISAYAS STATE UNIVERSITY</div>
            <div class="campus-name">Ormoc City Campus</div>
            <div class="office-name">Room Schedule</div>
          </div>
        </div>
        <div class="form-fields">
          <div class="header-field">
            <label>Room</label>
            <input type="text" value="${escapeHtml(
              activeRoomName || "Room"
            )}" readonly>
          </div>
        </div>
      </div>
      
      <table class="schedule-table">
        <thead>
          <tr>
            <th class="time-header">TIME</th>
            <th>MON</th>
            <th>TUE</th>
            <th>WED</th>
            <th>THU</th>
            <th>FRI</th>
            <th>SAT</th>
            <th>SUN</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div class="print-footer">
        <p><strong>This is an official document generated by the EVSU-OCC Scheduling System</strong></p>
        <p>Eastern Visayas State University - Ormoc City Campus</p>
      </div>
    </body>
    </html>
  `);

  printWindow.document.close();

  // Wait for content to load, then print
  printWindow.onload = function () {
    setTimeout(() => {
      printWindow.print();
    }, 500);
  };

  // Fallback if onload doesn't fire
  setTimeout(() => {
    if (printWindow.document.readyState === "complete") {
      printWindow.print();
    }
  }, 1000);
}

// Print instructor schedule function - matches schedule tab page format
function printInstructorSchedule() {
  if (!__CURRENT_INSTRUCTOR_ID__ || !__CURRENT_INSTRUCTOR_SCHEDULES__) {
    Swal.fire({
      icon: "warning",
      title: "No Schedule Data",
      text: "Please view an instructor schedule first.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Get schedule data
  let schedules = __CURRENT_INSTRUCTOR_SCHEDULES__ || [];

  if (!schedules || schedules.length === 0) {
    Swal.fire({
      icon: "error",
      title: "Schedule Not Found",
      text: "No schedule data available to print.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Get instructor name
  const instructorNameEl = document.getElementById("instructorNameDisplay");
  const instructorName = instructorNameEl
    ? instructorNameEl.textContent
    : "Instructor";

  // Create a new window for printing
  const printWindow = window.open("", "_blank");

  if (!printWindow) {
    Swal.fire({
      icon: "error",
      title: "Print Error",
      text: "Please allow popups to print the schedule.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Set document type
  printWindow.document.open("text/html", "replace");

  // Get logo path
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/").filter((p) => p);
  const rootPath = pathParts.slice(0, -3).join("/");
  const logoPath =
    window.location.origin +
    (rootPath ? "/" + rootPath : "") +
    "/public/assets/img/evsu-logo.png";

  // Day order matching calendar: MON, TUE, WED, THU, FRI, SAT, SUN
  const days = ["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"];
  const dayMap = {
    Mon: "MON",
    Tue: "TUE",
    Wed: "WED",
    Thu: "THU",
    Fri: "FRI",
    Sat: "SAT",
    Sun: "SUN",
  };

  // Generate time slots from 7:00 AM to 8:30 PM with 30-minute intervals
  const timeSlots = [];
  for (let hour = 7; hour <= 20; hour++) {
    const period = hour < 12 ? "AM" : "PM";
    const displayHour = hour === 12 ? 12 : hour > 12 ? hour % 12 : hour;
    const hourLabel = `${displayHour}:00 ${period}`;

    // Add main hour slot
    timeSlots.push({
      hour: hour,
      label: hourLabel,
      minutes: 0,
      totalMinutes: hour * 60,
    });

    // Add 30-minute slot
    if (hour < 20) {
      timeSlots.push({
        hour: hour,
        label: `${displayHour}:30`,
        minutes: 30,
        totalMinutes: hour * 60 + 30,
      });
    }
  }

  // Add 8:30 PM as the last slot
  timeSlots.push({
    hour: 20,
    label: "8:30 PM",
    minutes: 30,
    totalMinutes: 20 * 60 + 30,
  });

  // Parse time string to minutes from midnight
  const parseTimeToMinutes = (timeStr) => {
    if (!timeStr) return null;
    // Handle HH:MM:SS format
    const parts = timeStr.split(":");
    if (parts.length >= 2) {
      const hour = parseInt(parts[0]);
      const min = parseInt(parts[1] || 0);
      return hour * 60 + min;
    }
    // Handle "10:00 AM" format
    const match = timeStr.trim().match(/(\d+):(\d+)\s*(AM|PM|am|pm)/i);
    if (match) {
      let hour = parseInt(match[1]);
      const min = parseInt(match[2]);
      const period = match[3].toUpperCase();
      if (period === "PM" && hour !== 12) hour += 12;
      if (period === "AM" && hour === 12) hour = 0;
      return hour * 60 + min;
    }
    return null;
  };

  // Format time for display (from HH:MM:SS to 12-hour format)
  const formatTimeForDisplay = (timeStr) => {
    if (!timeStr) return "";
    const parts = timeStr.split(":");
    if (parts.length >= 2) {
      const hour = parseInt(parts[0]);
      const min = parts[1] || "00";
      const period = hour < 12 ? "AM" : "PM";
      const displayHour =
        hour === 0 ? 12 : hour === 12 ? 12 : hour > 12 ? hour % 12 : hour;
      return `${displayHour}:${min} ${period}`;
    }
    return timeStr;
  };

  // Get instructor colors (matching calendar rendering)
  const baseColors = [
    "#28a745",
    "#2E5C8A",
    "#800000",
    "#4B0082",
    "#FF6B35",
    "#006400",
    "#8B4513",
    "#2F4F4F",
    "#A0522D",
    "#5F9EA0",
    "#C71585",
    "#556B2F",
    "#DC143C",
    "#20B2AA",
    "#9370DB",
    "#FF6347",
    "#32CD32",
    "#1E90FF",
    "#FF1493",
    "#00CED1",
    "#FF8C00",
    "#8A2BE2",
    "#228B22",
    "#CD5C5C",
    "#4682B4",
  ];
  const instructorColors = {};

  // Process schedules - handle nested schedule structure
  const processedSchedules = schedules
    .map((s) => {
      // The API returns nested structure: schedule.schedule.day, schedule.subject.code, etc.
      const scheduleData = s.schedule || {};
      const subjectData = s.subject || {};
      const roomData = s.room || {};

      // Get day - handle nested structure from API (schedule.schedule.day)
      const day =
        dayMap[scheduleData.day] ||
        dayMap[s.schd_day] ||
        scheduleData.day ||
        s.schd_day ||
        "Mon";
      const startTime =
        scheduleData.start_time || s.schd_start || s.start_time || "";
      const endTime = scheduleData.end_time || s.schd_end || s.end_time || "";
      const startMinutes = parseTimeToMinutes(startTime);
      const endMinutes = parseTimeToMinutes(endTime);
      const scheduleType = scheduleData.type || s.schd_type || "Lec";
      const isOvertime =
        scheduleData.is_overtime === "Yes" ||
        scheduleData.is_overtime === true ||
        s.is_overtime === "Yes" ||
        s.is_overtime === true;

      // Get instructor color (use instructor ID if available)
      const instId =
        s.instructor_id || s.inst_id || __CURRENT_INSTRUCTOR_ID__ || 0;
      if (!instructorColors[instId]) {
        const colorIdx = instId % baseColors.length;
        instructorColors[instId] = baseColors[colorIdx];
      }
      const scheduleColor = isOvertime ? "#dc3545" : instructorColors[instId];

      // Format instructor name (abbreviated: First Initial. Last Name)
      let instructorDisplay = "";
      if (s.instructor_name) {
        const nameParts = s.instructor_name.trim().split(" ");
        if (nameParts.length >= 2) {
          const firstName = nameParts[0];
          const lastName = nameParts[nameParts.length - 1];
          instructorDisplay = `${firstName.charAt(0)}. ${lastName}`;
        } else {
          instructorDisplay = s.instructor_name;
        }
      }

      // Format room name - handle nested structure (schedule.room.name)
      const roomDisplay = roomData.name || s.rm_name || "";

      // Get subject code - handle nested structure (schedule.subject.code)
      const subjCode = subjectData.code || s.subj_code || "";

      return {
        day: day,
        subjCode: subjCode,
        instructor: instructorDisplay,
        room: roomDisplay,
        section: s.sec_name || "",
        time: `${formatTimeForDisplay(startTime)} - ${formatTimeForDisplay(
          endTime
        )}`,
        type: scheduleType,
        startMinutes: startMinutes,
        endMinutes: endMinutes,
        startTime: formatTimeForDisplay(startTime),
        endTime: formatTimeForDisplay(endTime),
        isOvertime: isOvertime,
        color: scheduleColor,
      };
    })
    .filter(
      (s) =>
        s.startMinutes !== null &&
        s.endMinutes !== null &&
        s.startMinutes < s.endMinutes
    );

  // Track which schedules have been rendered to avoid duplicates
  const renderedSchedules = new Set();

  // Build table rows
  let tableRows = "";
  timeSlots.forEach((timeSlot) => {
    tableRows += "<tr>";

    // Time cell
    const isHalfHour = timeSlot.minutes === 30;
    if (isHalfHour) {
      tableRows += `<td class="time-cell time-half">${timeSlot.label}</td>`;
    } else {
      tableRows += `<td class="time-cell time-main">${timeSlot.label}</td>`;
    }

    // Day cells
    days.forEach((day) => {
      let cellContent = "";

      // Find schedules that START in this time slot
      const cellSchedules = processedSchedules.filter((s) => {
        if (s.day !== day) return false;

        // Create unique ID for this schedule
        const scheduleId = `${s.day}-${s.subjCode}-${s.startMinutes}-${s.endMinutes}`;

        // Only show if it starts in this exact time slot and hasn't been rendered yet
        const slotStart = timeSlot.totalMinutes;
        const slotEnd = slotStart + 30; // 30-minute slots
        const startsInSlot =
          s.startMinutes >= slotStart && s.startMinutes < slotEnd;

        if (startsInSlot && !renderedSchedules.has(scheduleId)) {
          renderedSchedules.add(scheduleId);
          return true;
        }
        return false;
      });

      // Build cell content
      if (cellSchedules.length > 0) {
        cellSchedules.forEach((sched) => {
          // Calculate how many 30-minute intervals this schedule spans
          const duration = sched.endMinutes - sched.startMinutes;
          const intervalsToSpan = Math.max(1, Math.ceil(duration / 30));
          const rowHeight = 13; // Compact row height
          const totalHeight = intervalsToSpan * rowHeight - 2;

          const overtimeClass = sched.isOvertime ? "overtime-schedule" : "";
          // Format: Subject Code (bold), Instructor, Room, Time, Type
          cellContent += `<div class="schedule-item ${overtimeClass}" style="height: ${totalHeight}px; background: ${
            sched.color
          }; border-left: 4px solid ${
            sched.isOvertime ? "#bd2130" : sched.color
          };">
            <div class="subj-code-print"><strong>${escapeHtml(
              sched.subjCode
            )}</strong></div>
            <div class="instructor-print">${escapeHtml(sched.instructor)}</div>
            <div class="room-print">${escapeHtml(sched.room)}</div>
            <div class="time-print">${escapeHtml(sched.time)}</div>
            <div class="type-print">${escapeHtml(sched.type)}</div>
          </div>`;
        });
      }

      tableRows += `<td class="day-cell">${cellContent}</td>`;
    });

    tableRows += "</tr>";
  });

  // Write the print document - matching schedule tab page format
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Instructor Schedule - Print</title>
      <style>
        @page {
          margin: 0.3cm;
          size: letter portrait;
        }
        
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        
        body {
          font-family: 'Poppins', 'Arial', 'Helvetica', sans-serif;
          font-size: 9pt;
          line-height: 1.2;
          color: #000;
          background: #fff;
          padding: 10px;
        }
        
        .print-header {
          margin-bottom: 8px;
        }
        
        .header-top {
          display: flex;
          align-items: flex-start;
          margin-bottom: 8px;
        }
        
        .logo-section {
          margin-right: 15px;
        }
        
        .logo-section img {
          height: 50px;
          width: auto;
        }
        
        .university-info {
          flex: 1;
          text-align: center;
        }
        
        .republic-text {
          font-size: 10pt;
          color: #000;
          margin-bottom: 3px;
        }
        
        .university-name {
          font-size: 14pt;
          font-weight: bold;
          color: #800000;
          margin-bottom: 3px;
          text-transform: uppercase;
        }
        
        .campus-name {
          font-size: 11pt;
          color: #000;
          margin-bottom: 3px;
        }
        
        .office-name {
          font-size: 12pt;
          font-weight: bold;
          color: #800000;
          margin-top: 8px;
          padding-top: 8px;
          border-top: 2px solid #800000;
          text-transform: uppercase;
          letter-spacing: 1px;
        }
        
        .form-fields {
          display: flex;
          gap: 20px;
          margin-bottom: 5px;
        }
        
        .header-field {
          flex: 1;
        }
        
        .header-field label {
          font-size: 7pt;
          font-weight: 600;
          display: block;
          margin-bottom: 2px;
          color: #000;
        }
        
        .header-field input {
          border: none;
          border-bottom: 2px solid #000;
          font-size: 7pt;
          padding: 1px 0;
          width: 100%;
          background: transparent;
          outline: none;
        }
        
        /* Schedule Table Styles - EVSU-OCC Theme */
        .schedule-table {
          width: 100%;
          border-collapse: collapse;
          border: 2px solid #800000;
          margin: 0 auto;
          background: #fff;
        }
        
        .schedule-table th {
          background: #800000;
          color: #fff;
          font-weight: bold;
          padding: 4px 3px;
          text-align: center;
          border: 1px solid #660000;
          font-size: 7pt;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }
        
        .schedule-table th.time-header {
          background: #800000;
          width: 70px;
        }
        
        .schedule-table td {
          border: 1px solid #ccc;
          padding: 1px;
          vertical-align: top;
          height: 10px;
        }
        
        .schedule-table td.time-cell {
          background: #f5f5f5;
          font-weight: 600;
          text-align: center;
          border-right: 2px solid #800000;
          padding: 2px 3px;
          width: 70px;
          font-size: 7pt;
        }
        
        .time-main {
          font-size: 7pt;
          font-weight: bold;
        }
        
        .time-half {
          font-size: 6pt;
          color: #666;
        }
        
        .schedule-table td.day-cell {
          background: #fff;
          position: relative;
          height: 10px;
          vertical-align: top;
          padding: 0;
        }
        
        .schedule-item {
          border: none;
          border-radius: 2px;
          padding: 1px 2px;
          margin: 0;
          font-size: 5pt;
          page-break-inside: avoid;
          text-align: center;
          display: flex;
          flex-direction: column;
          justify-content: center;
          width: 100%;
          box-sizing: border-box;
          color: #fff;
          overflow: hidden;
        }
        
        .schedule-item.overtime-schedule {
          background: #dc3545 !important;
          border-left: 2px solid #bd2130 !important;
        }
        
        .subj-code-print {
          font-size: 6pt;
          font-weight: 900;
          margin-bottom: 0px;
          color: #fff;
        }
        
        .instructor-print, .room-print, .time-print, .type-print {
          font-size: 4pt;
          line-height: 0.9;
          color: #fff;
          margin: 0;
        }
        
        .schedule-item strong {
          font-weight: 900;
        }
        
        .print-footer {
          margin-top: 10px;
          padding-top: 8px;
          border-top: 2px solid #800000;
          text-align: center;
          font-size: 7pt;
          color: #666;
        }
        
        .print-footer p {
          margin: 2px 0;
        }
        
        @media print {
          body {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
            transform: scale(0.85);
            transform-origin: top left;
            width: 117.65%;
          }
          
          .print-header {
            page-break-after: avoid;
          }
          
          .schedule-table {
            page-break-inside: avoid;
          }
          
          .schedule-item {
            page-break-inside: avoid;
          }
          
          * {
            page-break-inside: avoid;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-header">
        <div class="header-top">
          <div class="logo-section">
            <img src="${logoPath}" alt="EVSU Logo" onerror="this.style.display='none'">
          </div>
          <div class="university-info">
            <div class="republic-text">Republic of the Philippines</div>
            <div class="university-name">EASTERN VISAYAS STATE UNIVERSITY</div>
            <div class="campus-name">Ormoc City Campus</div>
            <div class="office-name">Class Schedule</div>
          </div>
        </div>
        <div class="form-fields">
          <div class="header-field">
            <label>Name</label>
            <input type="text" value="${escapeHtml(instructorName)}" readonly>
          </div>
        </div>
      </div>
      
      <table class="schedule-table">
        <thead>
          <tr>
            <th class="time-header">TIME</th>
            <th>MON</th>
            <th>TUE</th>
            <th>WED</th>
            <th>THU</th>
            <th>FRI</th>
            <th>SAT</th>
            <th>SUN</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div class="print-footer">
        <p><strong>This is an official document generated by the EVSU-OCC Scheduling System</strong></p>
        <p>Eastern Visayas State University - Ormoc City Campus</p>
      </div>
    </body>
    </html>
  `);

  printWindow.document.close();

  // Wait for content to load, then print
  printWindow.onload = function () {
    setTimeout(() => {
      printWindow.print();
    }, 500);
  };

  // Fallback if onload doesn't fire
  setTimeout(() => {
    if (printWindow.document.readyState === "complete") {
      printWindow.print();
    }
  }, 1000);
}

// Helper function to format time
function formatTime(timeStr) {
  if (!timeStr) return "";
  // If already formatted (contains AM/PM), return as is
  if (timeStr.includes("AM") || timeStr.includes("PM")) {
    return timeStr;
  }
  const parts = timeStr.split(":");
  const hour = parseInt(parts[0]);
  const min = parts[1] || "00";
  const period = hour < 12 ? "AM" : "PM";
  const displayHour = hour % 12 === 0 ? 12 : hour % 12;
  return `${displayHour}:${min} ${period}`;
}

// Helper function to convert 12-hour format to 24-hour format
function convertTo24Hour(timeStr) {
  if (!timeStr) return "08:00:00";
  // If already in 24-hour format, return as is
  if (!timeStr.includes("AM") && !timeStr.includes("PM")) {
    return timeStr;
  }

  const isPM = timeStr.includes("PM");
  const timePart = timeStr.replace(/\s*(AM|PM)\s*/i, "");
  const parts = timePart.split(":");
  let hour = parseInt(parts[0]);
  const min = parts[1] || "00";

  if (isPM && hour !== 12) {
    hour += 12;
  } else if (!isPM && hour === 12) {
    hour = 0;
  }

  return `${hour.toString().padStart(2, "0")}:${min}:00`;
}

function openRoomRequestModalForSlot(day, timeSlot) {
  if (!activeRoomId || !activeRoomName) {
    Swal.fire({
      icon: "warning",
      title: "No Room Selected",
      text: "Please select a room first.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Get the date filter value from the room schedule modal if available
  // Check both calendar modal and list modal date filters
  let dateFilterEl = document.getElementById("roomScheduleCalendarDateFilter");
  if (!dateFilterEl || !dateFilterEl.value) {
    // Try the list modal date filter (used in room access grants)
    dateFilterEl = document.getElementById("roomScheduleDateFilter");
  }
  const filteredDate =
    dateFilterEl && dateFilterEl.value ? dateFilterEl.value.trim() : null;

  // IMPORTANT: Store available slots BEFORE closing the schedule modal
  // because handleModalHidden will clear currentRoomScheduleAvailableSlots
  const savedAvailableSlots =
    currentRoomScheduleAvailableSlots &&
    Object.keys(currentRoomScheduleAvailableSlots).length > 0
      ? JSON.parse(JSON.stringify(currentRoomScheduleAvailableSlots)) // Deep copy
      : currentRoomScheduleAvailableSlots;

  // Set flag to prevent handleModalHidden from clearing available slots
  window._pendingRoomRequest = true;

  console.log(
    "openRoomRequestModalForSlot: Saving available slots before modal close",
    {
      day,
      timeSlot,
      filteredDate,
      savedAvailableSlots,
      savedKeys: savedAvailableSlots ? Object.keys(savedAvailableSlots) : [],
      currentRoomScheduleAvailableSlots,
      currentKeys: currentRoomScheduleAvailableSlots
        ? Object.keys(currentRoomScheduleAvailableSlots)
        : [],
    }
  );

  // Check if request modal exists
  const requestModalElement = document.getElementById("roomRequestModal");
  if (!requestModalElement) {
    console.error("Room request modal not found in DOM");
    window._pendingRoomRequest = false;
    Swal.fire({
      icon: "error",
      title: "Modal Not Found",
      text: "The room request modal is not available. Please refresh the page.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  // Close schedule modal first
  const scheduleModalElement = document.getElementById("roomScheduleModal");
  const scheduleModal = scheduleModalElement
    ? bootstrap.Modal.getInstance(scheduleModalElement)
    : null;

  if (scheduleModal) {
    // Wait for schedule modal to fully close before opening request modal
    scheduleModalElement.addEventListener(
      "hidden.bs.modal",
      function onScheduleModalHidden() {
        scheduleModalElement.removeEventListener(
          "hidden.bs.modal",
          onScheduleModalHidden
        );
        // Restore saved available slots (they were cleared by handleModalHidden)
        if (savedAvailableSlots) {
          currentRoomScheduleAvailableSlots = savedAvailableSlots;
          console.log(
            "Restored currentRoomScheduleAvailableSlots after modal close",
            {
              restoredKeys: Object.keys(currentRoomScheduleAvailableSlots),
              day,
              timeSlot,
              filteredDate,
            }
          );
        }
        // Clear the flag after restoring
        window._pendingRoomRequest = false;
        console.log("Schedule modal hidden, opening request modal with:", {
          day,
          timeSlot,
          filteredDate,
          currentRoomScheduleAvailableSlots,
        });
        openRequestModal(day, timeSlot, filteredDate);
      },
      { once: true }
    );
    scheduleModal.hide();
  } else {
    // If schedule modal is not open, open request modal directly
    // Restore saved available slots if needed
    if (savedAvailableSlots) {
      currentRoomScheduleAvailableSlots = savedAvailableSlots;
    }
    // Clear the flag
    window._pendingRoomRequest = false;
    console.log(
      "Schedule modal not open, opening request modal directly with:",
      { day, timeSlot, filteredDate, currentRoomScheduleAvailableSlots }
    );
    openRequestModal(day, timeSlot, filteredDate);
  }
}

// Global variables and functions for room request modal
let currentAvailableSlot = null;

// Helper function to convert 12-hour format to 24-hour format
function convertTo24Hour(time12h) {
  if (!time12h) return "";

  // Handle formats like "07:00 AM", "7:00 AM", "07:00AM", etc.
  const time = time12h.trim().toUpperCase();
  const [timePart, period] = time.split(/\s*(AM|PM)/);
  const [hours, minutes] = timePart.split(":");

  let hour24 = parseInt(hours);
  if (period === "PM" && hour24 !== 12) {
    hour24 += 12;
  } else if (period === "AM" && hour24 === 12) {
    hour24 = 0;
  }

  return `${String(hour24).padStart(2, "0")}:${minutes || "00"}`;
}

// Calculate duration based on start and end time
function calculateDuration() {
  const startTimeInput = document.getElementById("request_start_time");
  const endTimeInput = document.getElementById("request_end_time");
  const durationInput = document.getElementById("request_duration");

  if (!startTimeInput || !endTimeInput || !durationInput) return;

  const startTime = startTimeInput.value;
  const endTime = endTimeInput.value;

  if (!startTime || !endTime) {
    durationInput.value = "";
    return;
  }

  // Parse times
  const [startHours, startMinutes] = startTime.split(":").map(Number);
  const [endHours, endMinutes] = endTime.split(":").map(Number);

  // Calculate difference in minutes
  const startTotalMinutes = startHours * 60 + startMinutes;
  const endTotalMinutes = endHours * 60 + endMinutes;

  if (endTotalMinutes <= startTotalMinutes) {
    durationInput.value = "";
    return;
  }

  const diffMinutes = endTotalMinutes - startTotalMinutes;
  const diffHours = diffMinutes / 60;

  // Round to nearest 0.5 hour
  const roundedHours = Math.round(diffHours * 2) / 2;
  durationInput.value = roundedHours.toFixed(1);
}

// Validate that selected times are within available slot
function validateTimeRange() {
  if (!currentAvailableSlot) return true;

  const startTimeInput = document.getElementById("request_start_time");
  const endTimeInput = document.getElementById("request_end_time");

  if (!startTimeInput || !endTimeInput) return true;

  const selectedStart = startTimeInput.value;
  const selectedEnd = endTimeInput.value;

  if (!selectedStart || !selectedEnd) return true;

  // Compare times (HH:MM format)
  const slotStart = currentAvailableSlot.start24;
  const slotEnd = currentAvailableSlot.end24;

  // Remove validation classes
  startTimeInput.classList.remove("is-invalid", "is-valid");
  endTimeInput.classList.remove("is-invalid", "is-valid");

  let isValid = true;
  let errorMessage = "";

  // Check if start time is within range
  if (selectedStart < slotStart || selectedStart >= slotEnd) {
    startTimeInput.classList.add("is-invalid");
    isValid = false;
    errorMessage = "Start time must be within the available time slot.";
  } else {
    startTimeInput.classList.add("is-valid");
  }

  // Check if end time is within range
  if (selectedEnd <= slotStart || selectedEnd > slotEnd) {
    endTimeInput.classList.add("is-invalid");
    isValid = false;
    if (errorMessage) errorMessage += " ";
    errorMessage += "End time must be within the available time slot.";
  } else {
    endTimeInput.classList.add("is-valid");
  }

  // Check if end time is after start time
  if (selectedEnd <= selectedStart) {
    endTimeInput.classList.add("is-invalid");
    isValid = false;
    if (errorMessage) errorMessage += " ";
    errorMessage += "End time must be after start time.";
  }

  // Show error message if invalid
  if (!isValid && errorMessage) {
    // Remove existing feedback
    const existingFeedback =
      endTimeInput.parentElement.querySelector(".invalid-feedback");
    if (existingFeedback) existingFeedback.remove();

    const feedback = document.createElement("div");
    feedback.className = "invalid-feedback";
    feedback.textContent = errorMessage;
    endTimeInput.parentElement.appendChild(feedback);
  } else {
    // Remove feedback if valid
    const existingFeedback =
      endTimeInput.parentElement.querySelector(".invalid-feedback");
    if (existingFeedback) existingFeedback.remove();
  }

  return isValid;
}

// Initialize time input event listeners (call this when modal is opened)
function initializeRoomRequestTimeInputs() {
  const startTimeInput = document.getElementById("request_start_time");
  const endTimeInput = document.getElementById("request_end_time");

  // Remove existing listeners to avoid duplicates
  if (startTimeInput) {
    const newStartInput = startTimeInput.cloneNode(true);
    startTimeInput.parentNode.replaceChild(newStartInput, startTimeInput);
    newStartInput.addEventListener("change", function () {
      validateTimeRange();
      calculateDuration();
    });
  }

  if (endTimeInput) {
    const newEndInput = endTimeInput.cloneNode(true);
    endTimeInput.parentNode.replaceChild(newEndInput, endTimeInput);
    newEndInput.addEventListener("change", function () {
      validateTimeRange();
      calculateDuration();
    });
  }
}

function openRequestModal(day, timeSlot, filteredDate = null) {
  console.log("openRequestModal called with:", {
    day,
    timeSlot,
    filteredDate,
    activeRoomId,
    activeRoomName,
    currentRoomScheduleAvailableSlots,
    availableSlotsKeys: currentRoomScheduleAvailableSlots
      ? Object.keys(currentRoomScheduleAvailableSlots)
      : "null/undefined",
    availableSlotsType: typeof currentRoomScheduleAvailableSlots,
    availableSlotsIsEmpty:
      currentRoomScheduleAvailableSlots &&
      Object.keys(currentRoomScheduleAvailableSlots).length === 0,
  });

  // Set room info
  const rmIdInput = document.getElementById("request_rm_id");
  const rmNameInput = document.getElementById("request_rm_name");
  const rmDisplayInput = document.getElementById("request_room_display");

  if (rmIdInput) rmIdInput.value = activeRoomId || "";
  if (rmNameInput) rmNameInput.value = activeRoomName || "";
  if (rmDisplayInput) rmDisplayInput.value = activeRoomName || "";

  // Reset form
  const form = document.getElementById("roomRequestForm");
  if (form) {
    form.reset();
    // Re-set room info after reset
    if (rmIdInput) rmIdInput.value = activeRoomId || "";
    if (rmNameInput) rmNameInput.value = activeRoomName || "";
    if (rmDisplayInput) rmDisplayInput.value = activeRoomName || "";
  }

  // Reset available slot
  currentAvailableSlot = null;

  // Pre-fill day and time slot if provided
  const daySelect = document.getElementById("request_day");
  const timeSlotSelect = document.getElementById("request_time_slot");
  const dateInput = document.getElementById("request_date");

  // Auto-populate day from selected date
  if (dateInput) {
    // Use filtered date if provided, otherwise default to today
    if (filteredDate) {
      dateInput.value = filteredDate;
      console.log("Using filtered date:", filteredDate);
    } else if (!dateInput.value) {
      dateInput.value = new Date().toISOString().split("T")[0];
      console.log("Using today's date:", dateInput.value);
    }

    // Auto-populate day when date changes
    const updateDayFromDate = function () {
      if (dateInput.value && daySelect) {
        const selectedDate = new Date(dateInput.value);
        const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
        const dayMap = {
          0: "Sun",
          1: "Mon",
          2: "Tue",
          3: "Wed",
          4: "Thu",
          5: "Fri",
          6: "Sat",
        };
        const dayName = dayMap[dayOfWeek];
        if (dayName && daySelect.querySelector(`option[value="${dayName}"]`)) {
          // Only update day if no specific day was provided as parameter
          // or if the provided day doesn't match the date's day
          if (!day || day !== dayName) {
            daySelect.value = dayName;
            console.log("Auto-updated day from filtered date:", dayName);
            // Trigger change event to populate time slots
            daySelect.dispatchEvent(new Event("change"));
          }
        }
      }
    };

    // Update day on date change
    dateInput.addEventListener("change", updateDayFromDate);

    // Initial update if date is already set (this will update day from filtered date)
    updateDayFromDate();
  }

  // Clear existing options
  if (timeSlotSelect) {
    timeSlotSelect.innerHTML = '<option value="">Select Time Slot</option>';
  }

  // Helper function to normalize time strings for comparison
  function normalizeTimeString(timeStr) {
    if (!timeStr) return "";
    // Remove extra spaces, convert to lowercase for comparison
    return timeStr.replace(/\s+/g, " ").trim().toLowerCase();
  }

  // Store the time slot to be set after modal is shown
  let timeSlotToSet = null;

  // If day and time slot are provided, pre-fill them
  if (day && timeSlot && daySelect && timeSlotSelect) {
    console.log("openRequestModal: Pre-filling day and time slot", {
      day,
      timeSlot,
      filteredDate,
      availableSlots: currentRoomScheduleAvailableSlots,
      availableSlotsKeys: currentRoomScheduleAvailableSlots
        ? Object.keys(currentRoomScheduleAvailableSlots)
        : [],
      daySelect: !!daySelect,
      timeSlotSelect: !!timeSlotSelect,
    });

    // Normalize day name to match API format (Mon, Tue, etc.)
    const dayNameMap = {
      Monday: "Mon",
      Tuesday: "Tue",
      Wednesday: "Wed",
      Thursday: "Thu",
      Friday: "Fri",
      Saturday: "Sat",
      Sunday: "Sun",
      MON: "Mon",
      TUE: "Tue",
      WED: "Wed",
      THU: "Thu",
      FRI: "Fri",
      SAT: "Sat",
      SUN: "Sun",
    };
    const normalizedDay = dayNameMap[day] || day;

    // If we have a filtered date, verify the day matches the date's day
    // If not, use the day from the filtered date instead
    if (filteredDate && dateInput && dateInput.value) {
      const selectedDate = new Date(dateInput.value);
      const dayOfWeek = selectedDate.getDay();
      const dayMap = {
        0: "Sun",
        1: "Mon",
        2: "Tue",
        3: "Wed",
        4: "Thu",
        5: "Fri",
        6: "Sat",
      };
      const dateDayName = dayMap[dayOfWeek];

      // Use the day from the filtered date if it doesn't match the provided day
      if (dateDayName && normalizedDay !== dateDayName) {
        console.log(
          `Day mismatch: provided day (${normalizedDay}) doesn't match filtered date's day (${dateDayName}). Using day from filtered date.`
        );
        daySelect.value = dateDayName;
      } else {
        daySelect.value = normalizedDay;
      }
    } else {
      daySelect.value = normalizedDay;
    }
    timeSlotToSet = timeSlot; // Store for later setting

    // Populate time slots for the selected day immediately
    if (
      currentRoomScheduleAvailableSlots &&
      currentRoomScheduleAvailableSlots[normalizedDay]
    ) {
      console.log(
        `Populating time slots for ${normalizedDay}:`,
        currentRoomScheduleAvailableSlots[normalizedDay]
      );
      currentRoomScheduleAvailableSlots[day].forEach((slot) => {
        const slotValue = `${slot.start_formatted} - ${slot.end_formatted}`;
        const option = document.createElement("option");
        option.value = slotValue;
        option.textContent = slotValue;
        timeSlotSelect.appendChild(option);
      });

      // Try to set the time slot value immediately - use exact string match first
      console.log("Trying to match time slot:", {
        timeSlot,
        options: Array.from(timeSlotSelect.options).map((o) => o.value),
        optionsCount: timeSlotSelect.options.length,
      });

      // First try exact match (case-sensitive)
      let matched = false;
      for (let i = 0; i < timeSlotSelect.options.length; i++) {
        const option = timeSlotSelect.options[i];
        if (option.value === timeSlot) {
          console.log(
            "Exact string match found, selecting option:",
            option.value
          );
          option.selected = true;
          timeSlotSelect.dispatchEvent(new Event("change", { bubbles: true }));
          timeSlotToSet = null; // Already set, no need to set again
          matched = true;
          break;
        }
      }

      // If exact match failed, try normalized match
      if (!matched) {
        const normalizedTimeSlot = normalizeTimeString(timeSlot);
        console.log("Trying normalized match:", normalizedTimeSlot);

        for (let i = 0; i < timeSlotSelect.options.length; i++) {
          const option = timeSlotSelect.options[i];
          if (normalizeTimeString(option.value) === normalizedTimeSlot) {
            console.log(
              "Normalized match found, selecting option:",
              option.value
            );
            option.selected = true;
            timeSlotSelect.dispatchEvent(
              new Event("change", { bubbles: true })
            );
            timeSlotToSet = null; // Already set, no need to set again
            matched = true;
            break;
          }
        }
      }

      // If still not set, try partial matching
      if (!matched && timeSlotToSet) {
        console.log(
          "Exact and normalized matches failed, trying partial matching"
        );
        const timeSlotParts = timeSlot.split(" - ");
        if (timeSlotParts.length === 2) {
          for (let i = 0; i < timeSlotSelect.options.length; i++) {
            const optionValue = timeSlotSelect.options[i].value;
            const normalizedOption = normalizeTimeString(optionValue);
            const normalizedStart = normalizeTimeString(timeSlotParts[0]);
            const normalizedEnd = normalizeTimeString(timeSlotParts[1]);

            if (
              normalizedOption.includes(normalizedStart) &&
              normalizedOption.includes(normalizedEnd)
            ) {
              console.log(
                "Partial match found, selecting option:",
                optionValue
              );
              timeSlotSelect.options[i].selected = true;
              timeSlotSelect.dispatchEvent(
                new Event("change", { bubbles: true })
              );
              timeSlotToSet = null; // Already set
              matched = true;
              break;
            }
          }
        }
      }

      if (!matched) {
        console.warn(
          "Could not match time slot, will retry after modal shown:",
          {
            timeSlot,
            availableOptions: Array.from(timeSlotSelect.options).map(
              (o) => o.value
            ),
          }
        );
      }
    } else {
      console.warn(`No available slots found for day: ${normalizedDay}`, {
        normalizedDay,
        originalDay: day,
        availableSlotsKeys: currentRoomScheduleAvailableSlots
          ? Object.keys(currentRoomScheduleAvailableSlots)
          : [],
        currentRoomScheduleAvailableSlots,
      });
    }
  } else if (day && daySelect) {
    // If only day is provided (no time slot), still set the day and populate dropdown
    const dayNameMap = {
      Monday: "Mon",
      Tuesday: "Tue",
      Wednesday: "Wed",
      Thursday: "Thu",
      Friday: "Fri",
      Saturday: "Sat",
      Sunday: "Sun",
      MON: "Mon",
      TUE: "Tue",
      WED: "Wed",
      THU: "Thu",
      FRI: "Fri",
      SAT: "Sat",
      SUN: "Sun",
    };
    const normalizedDay = dayNameMap[day] || day;
    daySelect.value = normalizedDay;

    // Trigger change event to populate time slots
    if (daySelect.dispatchEvent) {
      daySelect.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }

  // Update time slots when day changes
  if (daySelect && timeSlotSelect) {
    daySelect.onchange = function () {
      const selectedDay = this.value;
      timeSlotSelect.innerHTML = '<option value="">Select Time Slot</option>';

      // Clear time fields when day changes
      const startTimeInput = document.getElementById("request_start_time");
      const endTimeInput = document.getElementById("request_end_time");
      const durationInput = document.getElementById("request_duration");
      const timeRangeInfo = document.getElementById("time_range_info");
      const timeRangeText = document.getElementById("time_range_text");

      if (startTimeInput) startTimeInput.value = "";
      if (endTimeInput) endTimeInput.value = "";
      if (durationInput) durationInput.value = "";
      if (timeRangeInfo) timeRangeInfo.style.display = "none";
      currentAvailableSlot = null;

      // Normalize day name
      const dayNameMap = {
        Monday: "Mon",
        Tuesday: "Tue",
        Wednesday: "Wed",
        Thursday: "Thu",
        Friday: "Fri",
        Saturday: "Sat",
        Sunday: "Sun",
        MON: "Mon",
        TUE: "Tue",
        WED: "Wed",
        THU: "Thu",
        FRI: "Fri",
        SAT: "Sat",
        SUN: "Sun",
      };
      const normalizedSelectedDay = dayNameMap[selectedDay] || selectedDay;

      if (
        normalizedSelectedDay &&
        currentRoomScheduleAvailableSlots &&
        currentRoomScheduleAvailableSlots[normalizedSelectedDay]
      ) {
        console.log(
          `Populating time slots for selected day ${normalizedSelectedDay}:`,
          currentRoomScheduleAvailableSlots[normalizedSelectedDay]
        );
        currentRoomScheduleAvailableSlots[normalizedSelectedDay].forEach(
          (slot) => {
            const option = document.createElement("option");
            option.value = `${slot.start_formatted} - ${slot.end_formatted}`;
            option.textContent = `${slot.start_formatted} - ${slot.end_formatted}`;
            // Store slot data in option for later use
            option.dataset.slotStart = slot.start_time || slot.start;
            option.dataset.slotEnd = slot.end_time || slot.end;
            timeSlotSelect.appendChild(option);
          }
        );
      }
    };

    // Handle time slot selection - populate start/end time fields
    timeSlotSelect.onchange = function () {
      const selectedOption = this.options[this.selectedIndex];
      const startTimeInput = document.getElementById("request_start_time");
      const endTimeInput = document.getElementById("request_end_time");
      const durationInput = document.getElementById("request_duration");
      const timeRangeInfo = document.getElementById("time_range_info");
      const timeRangeText = document.getElementById("time_range_text");

      if (
        !selectedOption ||
        !selectedOption.value ||
        selectedOption.value === ""
      ) {
        // Clear fields if no slot selected
        if (startTimeInput) startTimeInput.value = "";
        if (endTimeInput) endTimeInput.value = "";
        if (durationInput) durationInput.value = "";
        if (timeRangeInfo) timeRangeInfo.style.display = "none";
        currentAvailableSlot = null;
        return;
      }

      // Get slot data
      const slotStart = selectedOption.dataset.slotStart;
      const slotEnd = selectedOption.dataset.slotEnd;

      // Parse the time slot string (format: "07:00 AM - 10:00 AM")
      const timeSlotParts = selectedOption.value.split(" - ");
      if (timeSlotParts.length === 2) {
        // Convert 12-hour format to 24-hour format for time inputs
        const startTime24 = convertTo24Hour(timeSlotParts[0].trim());
        const endTime24 = convertTo24Hour(timeSlotParts[1].trim());

        // Store available slot range for validation
        currentAvailableSlot = {
          start: slotStart || startTime24,
          end: slotEnd || endTime24,
          start24: startTime24,
          end24: endTime24,
        };

        // Set default values (use full available slot initially)
        if (startTimeInput) {
          startTimeInput.value = startTime24;
          startTimeInput.setAttribute("data-min", startTime24);
          startTimeInput.setAttribute("data-max", endTime24);
        }
        if (endTimeInput) {
          endTimeInput.value = endTime24;
          endTimeInput.setAttribute("data-min", startTime24);
          endTimeInput.setAttribute("data-max", endTime24);
        }

        // Calculate initial duration
        calculateDuration();

        // Show time range info
        if (timeRangeInfo && timeRangeText) {
          timeRangeText.textContent = `Available: ${timeSlotParts[0].trim()} - ${timeSlotParts[1].trim()}`;
          timeRangeInfo.style.display = "block";
        }
      }
    };
  }

  // Initialize time input event listeners
  initializeRoomRequestTimeInputs();

  // Open request modal
  const requestModalElement = document.getElementById("roomRequestModal");
  if (requestModalElement) {
    const requestModal =
      bootstrap.Modal.getOrCreateInstance(requestModalElement);

    // Wait for modal to be fully shown before ensuring time slot is selected
    const ensureTimeSlotSelected = () => {
      if (timeSlotToSet && day && timeSlotSelect) {
        // Normalize day name
        const dayNameMap = {
          Monday: "Mon",
          Tuesday: "Tue",
          Wednesday: "Wed",
          Thursday: "Thu",
          Friday: "Fri",
          Saturday: "Sat",
          Sunday: "Sun",
          MON: "Mon",
          TUE: "Tue",
          WED: "Wed",
          THU: "Thu",
          FRI: "Fri",
          SAT: "Sat",
          SUN: "Sun",
        };
        const normalizedDay = dayNameMap[day] || day;

        // If dropdown is still empty, try to populate it again
        if (
          timeSlotSelect.options.length <= 1 &&
          currentRoomScheduleAvailableSlots &&
          currentRoomScheduleAvailableSlots[normalizedDay]
        ) {
          console.log(
            "ensureTimeSlotSelected: Dropdown empty, repopulating for",
            normalizedDay
          );
          timeSlotSelect.innerHTML =
            '<option value="">Select Time Slot</option>';
          currentRoomScheduleAvailableSlots[normalizedDay].forEach((slot) => {
            const slotValue = `${slot.start_formatted} - ${slot.end_formatted}`;
            const option = document.createElement("option");
            option.value = slotValue;
            option.textContent = slotValue;
            timeSlotSelect.appendChild(option);
          });
        }

        console.log("ensureTimeSlotSelected: Attempting to set time slot", {
          timeSlotToSet,
          day,
          normalizedDay,
          optionsCount: timeSlotSelect.options.length,
          currentValue: timeSlotSelect.value,
          options: Array.from(timeSlotSelect.options).map((o) => ({
            value: o.value,
            selected: o.selected,
          })),
        });

        // First try exact string match
        let matched = false;
        for (let i = 0; i < timeSlotSelect.options.length; i++) {
          const option = timeSlotSelect.options[i];
          if (option.value === timeSlotToSet) {
            console.log(
              "ensureTimeSlotSelected: Exact string match found, selecting:",
              option.value
            );
            option.selected = true;
            timeSlotSelect.dispatchEvent(
              new Event("change", { bubbles: true })
            );
            matched = true;
            return;
          }
        }

        // Try normalized match
        if (!matched) {
          const normalizedTimeSlot = normalizeTimeString(timeSlotToSet);
          for (let i = 0; i < timeSlotSelect.options.length; i++) {
            const option = timeSlotSelect.options[i];
            if (normalizeTimeString(option.value) === normalizedTimeSlot) {
              console.log(
                "ensureTimeSlotSelected: Normalized match found, selecting:",
                option.value
              );
              option.selected = true;
              timeSlotSelect.dispatchEvent(
                new Event("change", { bubbles: true })
              );
              matched = true;
              return;
            }
          }
        }

        // Try partial matching if exact match failed
        if (!matched) {
          const timeSlotParts = timeSlotToSet.split(" - ");
          if (timeSlotParts.length === 2) {
            for (let i = 0; i < timeSlotSelect.options.length; i++) {
              const optionValue = timeSlotSelect.options[i].value;
              const normalizedOption = normalizeTimeString(optionValue);
              const normalizedStart = normalizeTimeString(timeSlotParts[0]);
              const normalizedEnd = normalizeTimeString(timeSlotParts[1]);

              if (
                normalizedOption.includes(normalizedStart) &&
                normalizedOption.includes(normalizedEnd)
              ) {
                console.log(
                  "ensureTimeSlotSelected: Partial match found, selecting:",
                  optionValue
                );
                timeSlotSelect.options[i].selected = true;
                timeSlotSelect.dispatchEvent(
                  new Event("change", { bubbles: true })
                );
                matched = true;
                return;
              }
            }
          }
        }

        if (!matched) {
          console.warn("ensureTimeSlotSelected: Could not match time slot", {
            timeSlotToSet,
            availableOptions: Array.from(timeSlotSelect.options).map(
              (o) => o.value
            ),
          });
        }
      }
    };

    // Set time slot after modal is shown
    requestModalElement.addEventListener(
      "shown.bs.modal",
      function onModalShown() {
        requestModalElement.removeEventListener("shown.bs.modal", onModalShown);
        // Use double requestAnimationFrame to ensure DOM is fully updated
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            ensureTimeSlotSelected();
            // Re-initialize time inputs after modal is shown
            initializeRoomRequestTimeInputs();
          });
        });
      },
      { once: true }
    );

    requestModal.show();
  }
}

function submitRoomRequest() {
  const form = document.getElementById("roomRequestForm");

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  // Validate time range before submitting
  if (!validateTimeRange()) {
    Swal.fire({
      icon: "error",
      title: "Invalid Time Range",
      text: "Please ensure the selected times are within the available time slot and end time is after start time.",
      confirmButtonColor: "#dc3545",
    });
    return;
  }

  const formData = new FormData(form);

  // Get start and end time from form
  const startTimeInput = document.getElementById("request_start_time");
  const endTimeInput = document.getElementById("request_end_time");
  const timeSlotSelect = document.getElementById("request_time_slot");

  // Add start_time and end_time to form data
  if (startTimeInput && startTimeInput.value) {
    formData.append("start_time", startTimeInput.value);
  }
  if (endTimeInput && endTimeInput.value) {
    formData.append("end_time", endTimeInput.value);
  }

  // Also keep time_slot for backward compatibility (format: "HH:MM AM - HH:MM PM")
  if (timeSlotSelect && timeSlotSelect.value) {
    formData.append("time_slot", timeSlotSelect.value);
  }

  // Calculate and add duration
  const durationInput = document.getElementById("request_duration");
  if (durationInput && durationInput.value) {
    formData.set("duration", durationInput.value); // Use set to override if exists
  }

  Swal.fire({
    title: "Submitting Request...",
    text: "Please wait while we process your room request.",
    icon: "info",
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  fetch("../../admin/management/submit_room_request.php", {
    method: "POST",
    body: formData,
  })
    .then(async (response) => {
      // Check if response is actually JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.error("Non-JSON response received:", text);
        throw new Error(
          "Server returned non-JSON response. This usually means a PHP error occurred. Response: " +
            text.substring(0, 500)
        );
      }
      return response.json();
    })
    .then((data) => {
      Swal.close();

      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Request Accepted!",
          text: data.message,
          confirmButtonColor: "#28a745",
          confirmButtonText: "OK",
        }).then(() => {
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("roomRequestModal")
          );
          if (modal) {
            modal.hide();
          }
          form.reset();
        });
      } else {
        console.error("Room request submission failed:", data);
        // Show detailed error message
        let errorMessage = data.message || "Failed to submit room request.";
        if (data.debug) {
          errorMessage += "\n\nTechnical Details: " + data.debug;
        }
        Swal.fire({
          icon: "error",
          title: "Failed",
          html: errorMessage.replace(/\n/g, "<br>"),
          confirmButtonColor: "#dc3545",
          width: "600px",
        });
      }
    })
    .catch((error) => {
      console.error("Error submitting room request:", error);
      Swal.close();
      // Show the actual error message in the popup
      Swal.fire({
        icon: "error",
        title: "Error",
        html:
          '<div style="text-align: left;">' +
          "<strong>An error occurred while submitting the room request.</strong><br><br>" +
          "<strong>Error Details:</strong><br>" +
          '<pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px;">' +
          escapeHtml(error.message || error.toString()) +
          "</pre>" +
          "<br><small>If this error persists, please check your PHP error logs.</small>" +
          "</div>",
        confirmButtonColor: "#dc3545",
        width: "700px",
      });
    });
}

// Async-safe guard to prevent duplicate fetches
let buildingsFetchInProgress = false;

function populateBuildingSelector() {
  // Async-safe guard: prevent duplicate fetches
  if (buildingsFetchInProgress) {
    console.log("[ROOMS DEBUG] populateBuildingSelector() already in progress, skipping duplicate fetch");
    return;
  }
  
  buildingsFetchInProgress = true;
  console.log("[ROOMS DEBUG] populateBuildingSelector() called - starting fetch");
  const selector = $("#buildingSelector");
  
  // Preserve the currently selected building value (if any)
  const currentSelectedValue = selector.val();
  const shouldPreserveSelection = currentSelectedValue && 
                                   !isNaN(currentSelectedValue) && 
                                   parseInt(currentSelectedValue) > 0;
  console.log("[ROOMS DEBUG] Preserving selection:", {
    currentSelectedValue,
    shouldPreserveSelection
  });
  
  fetch("../../admin/management/get_buildings.php")
    .then((response) => response.json())
    .then((data) => {
      console.log("[ROOMS DEBUG] Buildings fetched:", data.success ? data.data.length : "failed");
      if (data.success) {
        selector
          .empty()
          .append('<option value="" selected>Select a building...</option>');
        data.data.forEach((building) => {
          selector.append(
            `<option value="${building.bd_id}">${escapeHtml(
              building.bd_desc
            )}</option>`
          );
        });
        console.log("[ROOMS DEBUG] Selector repopulated with", data.data.length, "buildings");
        
        // Restore previously selected building if it still exists
        if (shouldPreserveSelection) {
          const buildingStillExists = data.data.some(
            (b) => b.bd_id == currentSelectedValue
          );
          console.log("[ROOMS DEBUG] Checking if building still exists:", {
            currentSelectedValue,
            buildingStillExists
          });
          if (buildingStillExists) {
            console.log("[ROOMS DEBUG] Restoring selection to building:", currentSelectedValue);
            selector.val(currentSelectedValue);
            $("#addRoomBtn").prop("disabled", false);
            // Only trigger change if Rooms tab is visible
            if (isRoomsTabVisible()) {
              console.log("[ROOMS DEBUG] Rooms tab is visible - triggering change event to load rooms");
              selector.trigger("change");
            } else {
              console.log("[ROOMS DEBUG] Rooms tab not visible - storing selection for when tab is shown");
              // Store the selection - it will be used when tab becomes visible
              window._pendingBuildingSelection = currentSelectedValue;
            }
            return; // Exit early, don't auto-select single building
          } else {
            console.log("[ROOMS DEBUG] Previously selected building no longer exists");
          }
        }
        
        // If there is only one building, auto-select it so rooms table populates immediately
        if (Array.isArray(data.data) && data.data.length === 1) {
          const onlyId = data.data[0].bd_id;
          console.log("[ROOMS DEBUG] Only one building, auto-selecting:", onlyId);
          selector.val(onlyId);
          $("#addRoomBtn").prop("disabled", false);
          // Only trigger change if Rooms tab is visible
          if (isRoomsTabVisible()) {
            console.log("[ROOMS DEBUG] Rooms tab is visible - triggering change event to load rooms");
            selector.trigger("change");
          } else {
            console.log("[ROOMS DEBUG] Rooms tab not visible - storing selection for when tab is shown");
            window._pendingBuildingSelection = onlyId;
          }
        }
      }
    })
    .catch((error) => {
      console.error("[ROOMS DEBUG] Error loading buildings for selector:", error);
      // Show user-friendly error
      Swal.fire({
        icon: "error",
        title: "Error Loading Buildings",
        text: "Failed to load buildings. Please refresh the page and try again.",
        confirmButtonColor: "#dc3545",
        timer: 5000,
      });
    })
    .finally(() => {
      // Reset async guard in finally to ensure it's always reset
      buildingsFetchInProgress = false;
      console.log("[ROOMS DEBUG] populateBuildingSelector() fetch completed, guard reset");
    });
}

/**
 * Create the rooms DataTable with all columns and event handlers
 * Separated for reusability and cleaner code
 */
function createRoomsDataTable() {
  const table = $("#roomsTable").DataTable({
    data: [],
    columns: [
      { data: "rm_name" },
      { data: "rm_type" },
      { data: "rm_capacity" },
      {
        data: "dept_name",
        render: function (data, type, row) {
          return data || '<span class="text-muted">Shared</span>';
        },
      },
      {
        data: "rm_status",
        render: function (data) {
          const badgeClass = data === "Used" ? "bg-success" : "bg-secondary";
          return `<span class="badge ${badgeClass}">${data}</span>`;
        },
      },
      {
        data: null,
        orderable: false,
        className: "text-center",
        render: function (data, type, row) {
          // Check if user can manage access (must be admin and room belongs to their department)
          const canManageAccess =
            row.dept_id &&
            (window.currentUserDeptId == row.dept_id ||
              window.isAdminSupport);
          const accessBtn = canManageAccess
            ? `
            <button class="btn-manage-access btn btn-sm btn-outline-info me-1" data-rm-id="${
              row.rm_id
            }" data-rm-name="${
                row.rm_name || ""
              }" title="Manage Access" style="min-width: 38px;">
              <i class="bi bi-share"></i>
            </button>
          `
            : "";

          // View Room Schedule button - available for all rooms the user has access to
          const viewScheduleBtn = `
            <button class="btn-view-schedule btn btn-sm btn-outline-success me-1" data-rm-id="${
              row.rm_id
            }" data-rm-name="${
            row.rm_name || ""
          }" title="View Room Schedule" style="min-width: 38px;">
              <i class="bi bi-calendar-check"></i>
            </button>
          `;

          // Edit and Delete buttons - only for rooms in user's department (or Admin Support)
          const canEditDelete =
            !row.dept_id ||
            row.dept_id == window.currentUserDeptId ||
            window.isAdminSupport;
          const editBtn = canEditDelete
            ? `
            <button class="btn-edit-room btn btn-sm btn-outline-primary me-1" data-rm-id="${row.rm_id}" title="Edit Room" style="min-width: 38px;">
              <i class="bi bi-pencil-square"></i>
            </button>
          `
            : "";
          const deleteBtn = canEditDelete
            ? `
            <button class="btn-delete-room btn btn-sm btn-outline-danger" data-rm-id="${row.rm_id}" title="Delete Room" style="min-width: 38px;">
              <i class="bi bi-trash"></i>
            </button>
          `
            : "";

          return `
            ${viewScheduleBtn}
            ${accessBtn}
            ${editBtn}
            ${deleteBtn}
          `;
        },
      },
    ],
    responsive: true,
    language: {
      emptyTable:
        "No rooms found in this building. Select a building to get started.",
    },
  });

  // Add event listener for manage access button
  $("#roomsTable").on("click", ".btn-manage-access", function () {
    const rmId = $(this).data("rm-id");
    const rmName = $(this).data("rm-name");
    openRoomAccessModal(rmId, rmName);
  });

  // Add event listener for view schedule button
  $("#roomsTable").on("click", ".btn-view-schedule", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const rmId = $(this).data("rm-id");
    const rmName = $(this).data("rm-name");
    console.log("Schedule button clicked:", { rmId, rmName });
    if (typeof openRoomScheduleModal === "function") {
      openRoomScheduleModal(rmId, rmName);
    } else {
      console.error("openRoomScheduleModal function not found");
    }
  });

  // Add event listeners for edit and delete buttons (if not already attached)
  if (!$("#roomsTable").data("edit-delete-handlers-attached")) {
    $("#roomsTable").on("click", ".btn-edit-room", function () {
      const rmId = $(this).data("rm-id");
      editRoom(rmId);
    });

    $("#roomsTable").on("click", ".btn-delete-room", function () {
      const rmId = $(this).data("rm-id");
      deleteRoom(rmId);
    });
    
    $("#roomsTable").data("edit-delete-handlers-attached", true);
  }

  return table;
}

function initializeRoomsTable(buildingId) {
  console.log("[ROOMS DEBUG] initializeRoomsTable() called with buildingId:", buildingId);
  
  // Safety check: Ensure Rooms tab is visible before initializing DataTable
  if (!isRoomsTabVisible()) {
    console.log("[ROOMS DEBUG] Rooms tab not visible - storing building ID for later initialization");
    // Store the building ID to initialize when tab becomes visible
    if (buildingId) {
      window._pendingBuildingSelection = buildingId;
    }
    return;
  }
  
  // Validate buildingId - must be a valid numeric ID (null/undefined is allowed for empty table)
  const validBuildingId = buildingId !== null && buildingId !== undefined && 
                         !isNaN(buildingId) && parseInt(buildingId) > 0;
  console.log("[ROOMS DEBUG] Building ID validation:", {
    buildingId,
    validBuildingId,
    isNaN: buildingId ? isNaN(buildingId) : "N/A",
    parsed: buildingId ? parseInt(buildingId) : "N/A",
    tabVisible: isRoomsTabVisible()
  });
  
  // Initialize or get existing DataTable
  const tableExists = $.fn.DataTable.isDataTable("#roomsTable");
  
  if (!validBuildingId) {
    console.log("[ROOMS DEBUG] Invalid building ID, initializing/clearing empty table");
    
    // If table doesn't exist, create it with empty state
    if (!tableExists) {
      console.log("[ROOMS DEBUG] Creating new empty DataTable");
      roomsTable = createRoomsDataTable();
    } else {
      // Clear existing table
      console.log("[ROOMS DEBUG] Clearing existing table (rows:", roomsTable.rows().count(), ")");
      roomsTable.clear().draw();
    }
    console.log("[ROOMS DEBUG] Empty table initialized/cleared");
    return; // Exit early if no valid building ID
  }

  // Create DataTable if it doesn't exist
  if (!tableExists) {
    console.log("[ROOMS DEBUG] Creating new DataTable for rooms");
    roomsTable = createRoomsDataTable();
  }

  const ajaxUrl = `../../admin/management/get_rooms.php?bd_id=${buildingId}`;
  console.log("[ROOMS DEBUG] Fetching rooms from:", ajaxUrl);

  // Fetch and populate rooms for the selected building
  console.log("[ROOMS DEBUG] Starting fetchDataArray for rooms");
  fetchDataArray(ajaxUrl)
    .then((data) => {
      const roomCount = Array.isArray(data)
        ? data.length
        : data && data.data
        ? data.data.length
        : "unknown";
      console.log(
        "[ROOMS DEBUG] Rooms fetched successfully:",
        {
          url: ajaxUrl,
          roomCount: roomCount,
          dataType: Array.isArray(data) ? "array" : typeof data,
          hasData: Array.isArray(data) ? data.length > 0 : false
        }
      );
      if (Array.isArray(data) && data.length)
        console.log("[ROOMS DEBUG] Sample room:", data[0]);
      if (!roomsTable) {
        console.log("[ROOMS DEBUG] ERROR: roomsTable is null after fetch!");
        return;
      }
      console.log("[ROOMS DEBUG] Clearing and populating table (rows before:", roomsTable.rows().count(), ")");
      roomsTable.clear();
      roomsTable.rows.add(data);
      roomsTable.draw();
      const finalRowCount = roomsTable.rows().count();
      console.log("[ROOMS DEBUG] Table populated (rows after:", finalRowCount, ")");
      // Update UI placeholder and badge
      updateTableUI(
        "#roomsTable",
        finalRowCount,
        "Select a building to load rooms."
      );
      debugDataTable("Rooms", roomsTable, "#roomsTable");
      
      // Additional debug: check table state after a delay
      setTimeout(() => {
        if (roomsTable) {
          const delayedRowCount = roomsTable.rows().count();
          const selectorValue = $("#buildingSelector").val();
          console.log("[ROOMS DEBUG] Table state after 3 seconds:", {
            rowCount: delayedRowCount,
            buildingSelectorValue: selectorValue,
            tableExists: $.fn.DataTable.isDataTable("#roomsTable")
          });
        }
      }, 3000);
    })
    .catch((error) => {
      console.error("[ROOMS DEBUG] Error loading rooms:", error);
      
      // Handle different error types
      if (
        error.message &&
        (error.message.includes("403") ||
          error.message.includes("Unauthorized"))
      ) {
        showPermissionError("Rooms");
        if (roomsTable) {
          roomsTable.clear().draw();
        }
      } else {
        // Show user-friendly error message
        if (roomsTable) {
          roomsTable.clear().draw();
        }
        
        // Display error in table or via notification
        Swal.fire({
          icon: "error",
          title: "Error Loading Rooms",
          text: "Failed to load rooms. Please try again or refresh the page.",
          confirmButtonColor: "#dc3545",
          timer: 5000,
        });
      }
    });
}

/**
 * Populates the building dropdown in the Add Room modal
 */
function populateBuildingDropdownInModal() {
  const modalBuildingSelect = $("#add_rm_bd_id");
  if (modalBuildingSelect.length === 0) {
    console.error("Building dropdown not found in modal");
    return;
  }

  fetch("../../admin/management/get_buildings.php")
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success && Array.isArray(data.data)) {
        modalBuildingSelect
          .empty()
          .append('<option value="">Select a building...</option>');
        if (data.data.length === 0) {
          modalBuildingSelect.append(
            '<option value="" disabled>No buildings available</option>'
          );
          Swal.fire(
            "Warning",
            "No buildings are available. Please add a building first.",
            "warning"
          );
        } else {
          data.data.forEach((building) => {
            modalBuildingSelect.append(
              `<option value="${building.bd_id}">${escapeHtml(
                building.bd_desc
              )}</option>`
            );
          });

          // Pre-select building if one is already selected on the main page
          const selectedBuilding = $("#buildingSelector").val();
          if (selectedBuilding) {
            modalBuildingSelect.val(selectedBuilding);
          }
        }
      } else {
        console.error("Invalid response from get_buildings.php:", data);
        Swal.fire(
          "Error",
          data.message || "Failed to load buildings. Please try again.",
          "error"
        );
      }
    })
    .catch((error) => {
      console.error("Error loading buildings for modal:", error);
      Swal.fire("Error", "Failed to load buildings: " + error.message, "error");
    });
}

function addRoom() {
  // Check if form is disabled (user doesn't have permission)
  const form = document.getElementById("addRoomForm");
  if (form && form.classList.contains("form-disabled")) {
    Swal.fire(
      "Access Denied",
      "You do not have permission to manage rooms.",
      "error"
    );
    return;
  }

  const formData = new FormData(form);

  if (!form.checkValidity()) {
    Swal.fire(
      "Validation Error",
      "Please fill in all required fields.",
      "warning"
    );
    return;
  }

  const buildingId = document.getElementById("add_rm_bd_id").value;
  if (!buildingId) {
    Swal.fire("Validation Error", "Please select a building.", "warning");
    return;
  }

  fetch("../../admin/management/add_room.php", {
    method: "POST",
    body: formData,
  })
    .then(async (response) => {
      const responseText = await response.clone().text();
      console.log("Raw response:", responseText);

      if (!response.ok) {
        throw new Error(
          `HTTP error! status: ${
            response.status
          }, body: ${responseText.substring(0, 200)}`
        );
      }

      // Try to parse as JSON
      try {
        return await response.json();
      } catch (e) {
        console.error("JSON parse error. Raw response:", responseText);
        throw new Error(
          `Invalid JSON response. Server returned: ${responseText.substring(
            0,
            200
          )}`
        );
      }
    })
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("addRoomModal");
        form.reset();
        // Refresh rooms for the building that was selected in the modal
        const bdId = document.getElementById("add_rm_bd_id").value;
        // Also update the main building selector if it exists
        const mainBuildingSelector =
          document.getElementById("buildingSelector");
        if (mainBuildingSelector && bdId) {
          mainBuildingSelector.value = bdId;
          mainBuildingSelector.dispatchEvent(new Event("change"));
        }
        fetchDataArray(
          `../../admin/management/get_rooms.php?bd_id=${encodeURIComponent(
            bdId
          )}`
        ).then((data) => {
          if (!roomsTable) return;
          roomsTable.clear();
          roomsTable.rows.add(data);
          roomsTable.draw();
        });
      } else {
        Swal.fire(
          "Error",
          data.message || "Failed to add room. Please try again.",
          "error"
        );
      }
    })
    .catch((error) => {
      console.error("Error adding room:", error);
      Swal.fire("Error", "Failed to add room: " + error.message, "error");
    });
}

function editRoom(roomData) {
  document.getElementById("edit_rm_id").value = roomData.rm_id;
  document.getElementById("edit_rm_name").value = roomData.rm_name;
  document.getElementById("edit_rm_type").value = roomData.rm_type;
  document.getElementById("edit_rm_capacity").value = roomData.rm_capacity;
  document.getElementById("edit_rm_status").value = roomData.rm_status;
  document.getElementById("edit_rm_features").value =
    roomData.rm_features || "";

  // Set department if Admin Support and department field exists
  const deptField = document.getElementById("edit_rm_dept_id");
  if (deptField && roomData.dept_id) {
    deptField.value = roomData.dept_id;
  }

  // Load departments if Admin Support
  if (deptField) {
    loadDepartmentsForRoomEdit();
  }

  const editModal = new bootstrap.Modal(
    document.getElementById("editRoomModal")
  );
  editModal.show();
}

function updateRoom() {
  const form = document.getElementById("editRoomForm");
  const formData = new FormData(form);

  fetch("../../admin/management/update_room.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("editRoomModal");
        // Refresh rooms for the currently selected building
        const bdId = document.getElementById("buildingSelector")
          ? document.getElementById("buildingSelector").value
          : document.getElementById("edit_rm_bd_id") &&
            document.getElementById("edit_rm_bd_id").value;
        fetchDataArray(
          `../../admin/management/get_rooms.php?bd_id=${encodeURIComponent(
            bdId
          )}`
        ).then((data) => {
          if (!roomsTable) return;
          roomsTable.clear();
          roomsTable.rows.add(data);
          roomsTable.draw();
        });
      } else {
        Swal.fire("Error", data.message, "error");
      }
    });
}

function deleteRoom(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "This action cannot be undone!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("rm_id", id);
      fetch("../../admin/management/delete_room.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire("Deleted!", data.message, "success");
            // Refresh rooms for the currently selected building
            const bdId = document.getElementById("buildingSelector")
              ? document.getElementById("buildingSelector").value
              : null;
            fetchDataArray(
              `../../admin/management/get_rooms.php?bd_id=${encodeURIComponent(
                bdId
              )}`
            ).then((data) => {
              if (!roomsTable) return;
              roomsTable.clear();
              roomsTable.rows.add(data);
              roomsTable.draw();
            });
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    }
  });
}

// --- Room Requests Management Functions ---
let roomRequestsTable;
let archivedRoomRequestsTable;

function initializeRoomRequestsTab() {
  if (!$.fn.DataTable.isDataTable("#roomRequestsTable")) {
    roomRequestsTable = $("#roomRequestsTable").DataTable({
      data: [],
      columns: [
        {
          data: "request_date",
          render: function (data, type, row) {
            return `${data}<br><small class="text-muted">${
              row.request_time || ""
            }</small>`;
          },
        },
        {
          data: "room_display",
          render: function (data) {
            return escapeHtml(data || "-");
          },
        },
        { data: "day" },
        {
          data: "request_time",
          render: function (data) {
            return data || "-";
          },
        },
        {
          data: "requester_name",
          render: function (data) {
            return escapeHtml(data || "-");
          },
        },
        {
          data: "requester_dept_name",
          render: function (data) {
            return escapeHtml(data || "-");
          },
        },
        {
          data: "req_comment",
          render: function (data) {
            return data
              ? escapeHtml(data)
              : '<span class="text-muted">-</span>';
          },
        },
        {
          data: "req_status",
          render: function (data) {
            const badgeClass =
              {
                Pending: "bg-warning",
                Accepted: "bg-success",
                Declined: "bg-danger",
                Archived: "bg-secondary",
              }[data] || "bg-secondary";
            return `<span class="badge ${badgeClass}">${data}</span>`;
          },
        },
        {
          data: null,
          orderable: false,
          className: "text-center",
          render: function (data, type, row) {
            let actions = "";
            // Only show Approve/Decline buttons if user has permission AND it's an incoming request
            // Hide buttons for outgoing requests (requests from own department) to prevent self-approval
            const canApprove = typeof window.canApproveRoomRequests !== 'undefined' ? window.canApproveRoomRequests : false;
            const canManage = typeof window.canManageRooms !== 'undefined' ? window.canManageRooms : false;
            const hasApprovePermission = canApprove || canManage;
            
            if (
              hasApprovePermission &&
              row.req_status === "Pending" &&
              row.request_type === "incoming"
            ) {
              actions = `
                <button class="btn btn-sm btn-success me-1" onclick="updateRoomRequestStatus(${row.req_id}, 'Accepted')" title="Approve Request">
                  <i class="bi bi-check-circle"></i> Approve
                </button>
                <button class="btn btn-sm btn-danger me-1" onclick="updateRoomRequestStatus(${row.req_id}, 'Declined')" title="Decline Request">
                  <i class="bi bi-x-circle"></i> Decline
                </button>
              `;
            } else if (
              row.req_status === "Pending" &&
              row.request_type === "incoming" &&
              !hasApprovePermission
            ) {
              // Show view-only message for admins without approve permissions
              actions = `<span class="text-muted small">View only</span>`;
            } else if (
              row.req_status === "Pending" &&
              row.request_type === "outgoing"
            ) {
              // Show a message for outgoing requests (own department requests)
              actions = `<span class="text-muted small">Awaiting approval</span>`;
            }
            // Add archive icon only if user has manage permissions
            if (hasApprovePermission) {
              actions += `
                <button class="btn btn-sm btn-outline-secondary" onclick="archiveRoomRequest(${row.req_id})" title="Archive Request">
                  <i class="bi bi-archive"></i>
                </button>
              `;
            }
            return actions || '<span class="text-muted">-</span>';
          },
        },
      ],
      responsive: true,
      language: { emptyTable: "No room requests found." },
    });
  }

  // Initialize archived requests table
  if (!$.fn.DataTable.isDataTable("#archivedRoomRequestsTable")) {
    archivedRoomRequestsTable = $("#archivedRoomRequestsTable").DataTable({
      data: [],
      columns: [
        {
          data: "request_date",
          render: function (data, type, row) {
            return `${data}<br><small class="text-muted">${
              row.request_time || ""
            }</small>`;
          },
        },
        {
          data: "room_display",
          render: function (data) {
            return escapeHtml(data || "-");
          },
        },
        { data: "day" },
        {
          data: "request_time",
          render: function (data) {
            return data || "-";
          },
        },
        {
          data: "requester_name",
          render: function (data) {
            return escapeHtml(data || "-");
          },
        },
        {
          data: "requester_dept_name",
          render: function (data) {
            return escapeHtml(data || "-");
          },
        },
        {
          data: "req_comment",
          render: function (data) {
            return data
              ? escapeHtml(data)
              : '<span class="text-muted">-</span>';
          },
        },
        {
          data: "req_status",
          render: function (data) {
            return `<span class="badge bg-secondary">${data}</span>`;
          },
        },
        {
          data: null,
          orderable: false,
          className: "text-center",
          render: function (data, type, row) {
            return '<span class="text-muted">-</span>';
          },
        },
      ],
      responsive: true,
      language: { emptyTable: "No archived requests found." },
    });
  }

  // Load room requests (excluding archived)
  loadRoomRequests();

  // Load archived requests
  loadArchivedRoomRequests();

  // Setup filters
  setupRoomRequestFilters();
}

function setupRoomRequestFilters() {
  // Load rooms for filter
  fetch("../../admin/management/get_rooms.php?bd_id=0") // Get all rooms
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.data) {
        const roomFilter = document.getElementById("roomRequestRoomFilter");
        if (roomFilter) {
          roomFilter.innerHTML = '<option value="">All Rooms</option>';
          data.data.forEach((room) => {
            const option = document.createElement("option");
            option.value = room.rm_id;
            option.textContent = room.rm_name;
            roomFilter.appendChild(option);
          });
        }
      }
    })
    .catch((error) => console.error("Error loading rooms for filter:", error));

  // Attach filter event listeners
  $("#roomRequestStatusFilter, #roomRequestRoomFilter").on(
    "change",
    function () {
      loadRoomRequests();
    }
  );

  $("#roomRequestSearch").on(
    "keyup",
    debounce(function () {
      loadRoomRequests();
    }, 300)
  );
}

function loadRoomRequests() {
  const statusFilter = $("#roomRequestStatusFilter").val() || "";
  const roomFilter = $("#roomRequestRoomFilter").val() || "";
  const searchFilter = $("#roomRequestSearch").val() || "";

  const params = new URLSearchParams();
  if (statusFilter) params.append("status", statusFilter);
  if (roomFilter) params.append("room", roomFilter);
  if (searchFilter) params.append("search", searchFilter);
  // Exclude archived requests from main table
  params.append("exclude_archived", "1");

  fetch(`../../admin/management/get_room_requests.php?${params.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      console.log("Room Requests Response:", data);
      console.log("Status Filter:", statusFilter);
      console.log("Number of requests:", data.data ? data.data.length : 0);

      if (!data.success) {
        console.error("Error loading room requests:", data.message);
        if (roomRequestsTable) {
          roomRequestsTable.clear().draw();
        }
        return;
      }

      if (roomRequestsTable) {
        roomRequestsTable.clear();
        roomRequestsTable.rows.add(data.data || []);
        roomRequestsTable.draw();
        console.log(
          "Room requests table updated with",
          data.data ? data.data.length : 0,
          "rows"
        );
      }
    })
    .catch((error) => {
      console.error("Error loading room requests:", error);
      if (roomRequestsTable) {
        roomRequestsTable.clear().draw();
      }
    });
}

function loadArchivedRoomRequests() {
  if (!archivedRoomRequestsTable) {
    console.warn("Archived room requests table not initialized");
    return;
  }

  const params = new URLSearchParams();
  params.append("status", "Archived");

  fetch(`../../admin/management/get_room_requests.php?${params.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      console.log("Archived Room Requests Response:", data);
      console.log(
        "Number of archived requests:",
        data.data ? data.data.length : 0
      );

      if (!data.success) {
        console.error("Error loading archived room requests:", data.message);
        archivedRoomRequestsTable.clear().draw();
        return;
      }

      // Clear and reload the table
      archivedRoomRequestsTable.clear();
      if (data.data && data.data.length > 0) {
        archivedRoomRequestsTable.rows.add(data.data);
      }
      archivedRoomRequestsTable.draw(false); // false = don't reset paging
      console.log(
        "Archived room requests table updated with",
        data.data ? data.data.length : 0,
        "rows"
      );
    })
    .catch((error) => {
      console.error("Error loading archived room requests:", error);
      archivedRoomRequestsTable.clear().draw();
    });
}

function updateRoomRequestStatus(reqId, status) {
  const statusText = status === "Accepted" ? "approve" : "decline";

  Swal.fire({
    title: `${status === "Accepted" ? "Approve" : "Decline"} Request?`,
    text: `Are you sure you want to ${statusText} this room request?`,
    icon: status === "Accepted" ? "question" : "warning",
    showCancelButton: true,
    confirmButtonColor: status === "Accepted" ? "#28a745" : "#dc3545",
    cancelButtonColor: "#6c757d",
    confirmButtonText: `Yes, ${statusText} it!`,
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("req_id", reqId);
      formData.append("req_status", status);

      fetch("../../admin/management/update_room_request.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire({
              icon: "success",
              title: "Request Updated",
              text: data.message,
              confirmButtonColor: "#28a745",
              timer: 2000,
            });
            // Reload room requests
            loadRoomRequests();
          } else {
            Swal.fire({
              icon: "error",
              title: "Failed",
              text: data.message,
              confirmButtonColor: "#dc3545",
            });
          }
        })
        .catch((error) => {
          console.error("Error updating room request:", error);
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "An error occurred while updating the room request.",
            confirmButtonColor: "#dc3545",
          });
        });
    }
  });
}

function archiveRoomRequest(reqId) {
  Swal.fire({
    title: "Archive Request?",
    text: "Are you sure you want to archive this room request?",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#6c757d",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, archive it!",
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("req_id", reqId);
      formData.append("action", "archive");

      fetch("../../admin/management/update_room_request.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Reload main table first (to remove archived request)
            loadRoomRequests();

            // Reload archive table after a brief delay to ensure backend has processed
            setTimeout(() => {
              loadArchivedRoomRequests();
            }, 300);

            Swal.fire({
              icon: "success",
              title: "Request Archived",
              text:
                data.message || "Room request has been archived successfully.",
              confirmButtonColor: "#6c757d",
              timer: 2000,
              didClose: () => {
                // Ensure archive table is refreshed after modal closes
                loadArchivedRoomRequests();
              },
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Failed",
              text: data.message || "Failed to archive room request.",
              confirmButtonColor: "#dc3545",
            });
          }
        })
        .catch((error) => {
          console.error("Error archiving room request:", error);
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "An error occurred while archiving the room request.",
            confirmButtonColor: "#dc3545",
          });
        });
    }
  });
}

// Debounce helper function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// --- Class Management Functions ---
let classesTable;

function initializeClassesTab() {
  populateClassModalDropdowns();
  initializeClassesTable();
}

function initializeClassesTable() {
  if ($.fn.DataTable.isDataTable("#classesTable")) {
    // reload via fetch to normalize response shapes
    fetchDataArray("../../admin/management/get_classes.php").then((data) => {
      classesTable.clear();
      classesTable.rows.add(data);
      classesTable.draw();
    });
    return;
  }

  classesTable = $("#classesTable").DataTable({
    data: [],
    columns: [
      { data: "sy_name" },
      { data: "curr_name" },
      {
        data: "class_lvl",
        render: (data) => `${data}${["st", "nd", "rd"][data - 1] || "th"} Year`,
      },
      {
        data: "class_term",
        render: (data) => `${data}${["st", "nd", "rd"][data - 1] || "th"} Term`,
      },
      { data: "class_secno" },
      {
        data: null,
        orderable: false,
        className: "text-center",
        render: (data, type, row) => `
          <button class="btn-edit-class btn btn-sm btn-outline-primary" data-class-id="${row.class_id}" title="Edit Class">
            <i class="bi bi-pencil-square"></i> Edit
          </button>
          <button class="btn-delete-class btn btn-sm btn-outline-danger" data-class-id="${row.class_id}" title="Delete Class">
            <i class="bi bi-trash"></i> Delete
          </button>
                `,
      },
    ],
    responsive: true,
    language: { emptyTable: "No classes found. Add one to get started." },
  });

  // Populate classes
  fetchDataArray("../../admin/management/get_classes.php").then((data) => {
    console.log(
      "[DT FETCH] classes fetched",
      Array.isArray(data)
        ? data.length
        : data && data.data
        ? data.data.length
        : "unknown"
    );
    if (Array.isArray(data) && data.length)
      console.log("[DT FETCH] sample class:", data[0]);
    if (!classesTable) return;
    classesTable.clear();
    classesTable.rows.add(data);
    classesTable.draw();
    // Update UI placeholder and badge
    updateTableUI(
      "#classesTable",
      classesTable.rows().count(),
      "Add a class to get started."
    );
    debugDataTable("Classes", classesTable, "#classesTable");
  });
}

function populateClassModalDropdowns() {
  // Fetch School Years
  fetch("../../admin/management/get_schoolyears.php") // Assuming this endpoint exists or will be created
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const select = $("#add_class_sy");
        select
          .empty()
          .append('<option value="">Select School Year...</option>');
        data.data.forEach((sy) =>
          select.append(
            `<option value="${sy.sy_id}">${escapeHtml(sy.sy_name)}</option>`
          )
        );
      }
    });

  // Fetch Curricula
  fetch("../../admin/management/get_curricula.php") // This endpoint already exists
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const select = $("#add_class_curr");
        select.empty().append('<option value="">Select Curriculum...</option>');
        data.data.curricula.forEach((curr) =>
          select.append(
            `<option value="${curr.curr_id}">${escapeHtml(curr.curr_name)} (${
              curr.curr_yr
            })</option>`
          )
        );
      }
    });
}

function addClass() {
  const form = document.getElementById("addClassForm");
  if (!form.checkValidity()) {
    Swal.fire(
      "Validation Error",
      "Please fill in all required fields.",
      "warning"
    );
    return;
  }
  const formData = new FormData(form);
  fetch("../../admin/management/add_class.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("addClassModal");
        form.reset();
        // Refresh classes list
        fetchDataArray("../../admin/management/get_classes.php").then(
          (data) => {
            if (!classesTable) return;
            classesTable.clear();
            classesTable.rows.add(data);
            classesTable.draw();
          }
        );
      } else {
        Swal.fire("Error", data.message, "error");
      }
    });
}

function editClass(classData) {
  $("#edit_class_id").val(classData.class_id);
  $("#edit_class_sy_text").text(classData.sy_name);
  $("#edit_class_curr_text").text(classData.curr_name);
  $("#edit_class_lvl_text").text(
    `${classData.class_lvl}${
      ["st", "nd", "rd"][classData.class_lvl - 1] || "th"
    } Year`
  );
  $("#edit_class_term_text").text(
    `${classData.class_term}${
      ["st", "nd", "rd"][classData.class_term - 1] || "th"
    } Term`
  );
  $("#edit_class_secno").val(classData.class_secno);

  const editModal = new bootstrap.Modal(
    document.getElementById("editClassModal")
  );
  editModal.show();
}

function updateClass() {
  const form = document.getElementById("editClassForm");
  const formData = new FormData(form);
  fetch("../../admin/management/update_class.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("editClassModal");
        // Refresh classes list
        fetchDataArray("../../admin/management/get_classes.php").then(
          (data) => {
            if (!classesTable) return;
            classesTable.clear();
            classesTable.rows.add(data);
            classesTable.draw();
          }
        );
      } else {
        Swal.fire("Error", data.message, "error");
      }
    });
}

function deleteClass(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "This action cannot be undone!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("class_id", id);
      fetch("../../admin/management/delete_class.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            Swal.fire("Deleted!", data.message, "success");
            // Refresh classes list
            fetchDataArray("../../admin/management/get_classes.php").then(
              (data) => {
                if (!classesTable) return;
                classesTable.clear();
                classesTable.rows.add(data);
                classesTable.draw();
              }
            );
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    }
  });
}

// --- Section Management Functions ---
let sectionsTable;
let classList = [];

// Global function to open Add Section modal (can be called from onclick)
window.openAddSectionModal = function () {
  console.log("🔵 [DEBUG] openAddSectionModal called");

  const classId =
    document.getElementById("classSelector")?.value ||
    $("#classSelector").val();
  if (!classId || classId === "") {
    console.warn("🔴 [DEBUG] No class selected");
    if (typeof Swal !== "undefined") {
      Swal.fire("Error", "Please select a class first.", "error");
    } else {
      alert("Please select a class first.");
    }
    return false;
  }

  console.log("🟢 [DEBUG] Opening modal for class_id:", classId);

  // Set the class_id in the hidden input
  const classIdInput = document.getElementById("add_sec_class_id");
  if (classIdInput) {
    classIdInput.value = classId;
    console.log("✅ [DEBUG] Class ID input found and set:", classId);
  } else {
    console.error("❌ [DEBUG] add_sec_class_id input not found!");
  }

  // Get the modal element
  let modalEl = document.getElementById("addSectionModal");
  if (!modalEl) {
    console.error("❌ [DEBUG] Add Section Modal not found in DOM");
    if (typeof Swal !== "undefined") {
      Swal.fire("Error", "Modal not found. Please refresh the page.", "error");
    } else {
      alert("Modal not found. Please refresh the page.");
    }
    return false;
  }

  // ALWAYS move modal to body to ensure it's not affected by any container
  if (modalEl.parentElement !== document.body) {
    console.log("🔄 [DEBUG] Moving modal to body to ensure visibility...");
    const modalClone = modalEl.cloneNode(true);
    // Preserve any event listeners by copying them
    const oldModal = modalEl;
    modalEl.remove();
    document.body.appendChild(modalClone);
    modalEl = document.getElementById("addSectionModal");

    // Re-attach event listeners if they were lost
    if (modalEl) {
      // Re-initialize any Bootstrap modal instance
      if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        // Dispose old instance if exists
        const oldInstance = bootstrap.Modal.getInstance(oldModal);
        if (oldInstance) {
          oldInstance.dispose();
        }
      }
      console.log("✅ [DEBUG] Modal moved to body");
    } else {
      console.error("❌ [DEBUG] Failed to move modal to body");
      return false;
    }
  }

  // DEBUG: Check modal element properties
  console.log("🔍 [DEBUG] Modal element found:", {
    id: modalEl.id,
    className: modalEl.className,
    parentElement: modalEl.parentElement?.tagName,
    parentId: modalEl.parentElement?.id,
    style: {
      display: window.getComputedStyle(modalEl).display,
      visibility: window.getComputedStyle(modalEl).visibility,
      opacity: window.getComputedStyle(modalEl).opacity,
      zIndex: window.getComputedStyle(modalEl).zIndex,
      position: window.getComputedStyle(modalEl).position,
    },
    offsetParent: modalEl.offsetParent,
    boundingRect: modalEl.getBoundingClientRect(),
  });

  // Check if Bootstrap is available
  if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
    console.error("❌ [DEBUG] Bootstrap Modal is not available");
    // Try jQuery fallback
    if (typeof $ !== "undefined" && $.fn.modal) {
      console.log("🔄 [DEBUG] Using jQuery modal fallback");
      $("#addSectionModal").modal("show");

      // Reset the form but keep the class_id
      const form = document.getElementById("addSectionForm");
      if (form) {
        form.reset();
        const classIdInputAfterReset =
          document.getElementById("add_sec_class_id");
        if (classIdInputAfterReset) {
          classIdInputAfterReset.value = classId;
        }
      }
      return true;
    }

    if (typeof Swal !== "undefined") {
      Swal.fire(
        "Error",
        "Bootstrap is not loaded. Please refresh the page.",
        "error"
      );
    } else {
      alert("Bootstrap is not loaded. Please refresh the page.");
    }
    return false;
  }

  // Get or create modal instance and show it
  try {
    console.log("🔄 [DEBUG] Creating Bootstrap modal instance...");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    console.log("✅ [DEBUG] Modal instance created:", modal);

    // Force modal to be visible before showing
    modalEl.style.display = "block";
    modalEl.classList.add("show");
    modalEl.setAttribute("aria-hidden", "false");
    modalEl.setAttribute("aria-modal", "true");

    // Ensure high z-index
    const computedZIndex =
      parseInt(window.getComputedStyle(modalEl).zIndex) || 1105;
    if (computedZIndex < 1105) {
      modalEl.style.zIndex = "1105";
      console.log("🔧 [DEBUG] Adjusted modal z-index to 1105");
    }

    // Check for backdrop
    const backdrop = document.querySelector(".modal-backdrop");
    if (backdrop) {
      console.log(
        "🔍 [DEBUG] Found existing backdrop, z-index:",
        window.getComputedStyle(backdrop).zIndex
      );
      backdrop.style.zIndex = "1100";
    }

    console.log("🔄 [DEBUG] Calling modal.show()...");
    modal.show();

    // Wait a bit and check if modal is actually visible
    setTimeout(() => {
      const computedStyle = window.getComputedStyle(modalEl);
      const modalDialog = modalEl.querySelector(".modal-dialog");
      const modalDialogStyle = modalDialog
        ? window.getComputedStyle(modalDialog)
        : null;
      const rect = modalEl.getBoundingClientRect();
      const dialogRect = modalDialog
        ? modalDialog.getBoundingClientRect()
        : null;

      const isVisible =
        modalEl.classList.contains("show") &&
        computedStyle.display !== "none" &&
        modalEl.getAttribute("aria-hidden") === "false";

      console.log("🔍 [DEBUG] Modal visibility check after show():", {
        hasShowClass: modalEl.classList.contains("show"),
        display: computedStyle.display,
        visibility: computedStyle.visibility,
        opacity: computedStyle.opacity,
        ariaHidden: modalEl.getAttribute("aria-hidden"),
        isVisible: isVisible,
        zIndex: computedStyle.zIndex,
        position: computedStyle.position,
        top: computedStyle.top,
        left: computedStyle.left,
        width: computedStyle.width,
        height: computedStyle.height,
        rect: {
          top: rect.top,
          left: rect.left,
          width: rect.width,
          height: rect.height,
          bottom: rect.bottom,
          right: rect.right,
        },
        parentElement: modalEl.parentElement?.tagName,
        parentDisplay: modalEl.parentElement
          ? window.getComputedStyle(modalEl.parentElement).display
          : null,
        parentOverflow: modalEl.parentElement
          ? window.getComputedStyle(modalEl.parentElement).overflow
          : null,
        modalDialog: {
          exists: !!modalDialog,
          display: modalDialogStyle?.display,
          position: modalDialogStyle?.position,
          margin: modalDialogStyle?.margin,
          zIndex: modalDialogStyle?.zIndex,
          rect: dialogRect
            ? {
                top: dialogRect.top,
                left: dialogRect.left,
                width: dialogRect.width,
                height: dialogRect.height,
              }
            : null,
        },
      });

      // Check if modal is actually on screen
      const isOnScreen =
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= window.innerHeight &&
        rect.right <= window.innerWidth &&
        rect.width > 0 &&
        rect.height > 0;

      console.log("🔍 [DEBUG] Modal on-screen check:", {
        isOnScreen: isOnScreen,
        viewport: { width: window.innerWidth, height: window.innerHeight },
        modalRect: rect,
      });

      // Force modal to be visible with aggressive CSS if needed
      if (!isVisible || !isOnScreen || rect.width === 0 || rect.height === 0) {
        console.warn(
          "⚠️ [DEBUG] Modal not properly visible, applying aggressive fixes..."
        );

        // Aggressive CSS fixes
        modalEl.style.cssText = `
          display: block !important;
          visibility: visible !important;
          opacity: 1 !important;
          z-index: 1105 !important;
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          right: 0 !important;
          bottom: 0 !important;
          width: 100vw !important;
          height: 100vh !important;
          margin: 0 !important;
          padding: 0 !important;
        `;
        modalEl.classList.add("show");
        modalEl.setAttribute("aria-hidden", "false");
        modalEl.setAttribute("aria-modal", "true");
        document.body.classList.add("modal-open");
        document.body.style.overflow = "hidden";

        // Fix modal-dialog
        if (modalDialog) {
          modalDialog.style.cssText = `
            position: relative !important;
            margin: 1.75rem auto !important;
            z-index: 1106 !important;
            max-width: 500px !important;
          `;
        }

        // Create or fix backdrop
        let backdrop = document.querySelector(".modal-backdrop");
        if (!backdrop) {
          backdrop = document.createElement("div");
          backdrop.className = "modal-backdrop fade show";
          document.body.appendChild(backdrop);
        }
        backdrop.style.cssText = `
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          width: 100vw !important;
          height: 100vh !important;
          z-index: 1100 !important;
          background-color: rgba(0, 0, 0, 0.5) !important;
          opacity: 1 !important;
        `;

        console.log("✅ [DEBUG] Aggressive CSS fixes applied");

        // Verify after fix
        setTimeout(() => {
          const newRect = modalEl.getBoundingClientRect();
          console.log("🔍 [DEBUG] After aggressive fix:", {
            display: window.getComputedStyle(modalEl).display,
            rect: newRect,
            isVisible: newRect.width > 0 && newRect.height > 0,
          });
        }, 50);
      }
    }, 100);

    // Reset the form but keep the class_id
    const form = document.getElementById("addSectionForm");
    if (form) {
      form.reset();
      // Set the class_id again after reset
      const classIdInputAfterReset =
        document.getElementById("add_sec_class_id");
      if (classIdInputAfterReset) {
        classIdInputAfterReset.value = classId;
        console.log("✅ [DEBUG] Class ID set in form:", classId);
      }
    }

    console.log("✅ [DEBUG] Modal show() called successfully");
    return true;
  } catch (error) {
    console.error("❌ [DEBUG] Error opening modal:", error);
    console.error("❌ [DEBUG] Error stack:", error.stack);

    // Alternative fallback: Try jQuery
    if (typeof $ !== "undefined" && $.fn.modal) {
      console.log("🔄 [DEBUG] Trying jQuery modal as fallback...");
      try {
        $("#addSectionModal").modal("show");
        const form = document.getElementById("addSectionForm");
        if (form) {
          form.reset();
          const classIdInputAfterReset =
            document.getElementById("add_sec_class_id");
          if (classIdInputAfterReset) {
            classIdInputAfterReset.value = classId;
          }
        }
        return true;
      } catch (jqError) {
        console.error("❌ [DEBUG] jQuery fallback also failed:", jqError);
      }
    }

    // Last resort: Direct DOM manipulation
    console.log("🔄 [DEBUG] Trying direct DOM manipulation as last resort...");
    try {
      modalEl.style.display = "block";
      modalEl.classList.add("show");
      modalEl.setAttribute("aria-hidden", "false");
      modalEl.setAttribute("aria-modal", "true");
      document.body.classList.add("modal-open");
      document.body.style.overflow = "hidden";
      document.body.style.paddingRight = "0px";

      // Create backdrop
      let backdrop = document.querySelector(".modal-backdrop");
      if (!backdrop) {
        backdrop = document.createElement("div");
        backdrop.className = "modal-backdrop fade show";
        backdrop.style.zIndex = "1100";
        document.body.appendChild(backdrop);
      }

      // Set form values
      const form = document.getElementById("addSectionForm");
      if (form) {
        form.reset();
        const classIdInputAfterReset =
          document.getElementById("add_sec_class_id");
        if (classIdInputAfterReset) {
          classIdInputAfterReset.value = classId;
        }
      }

      console.log("✅ [DEBUG] Direct DOM manipulation successful");
      return true;
    } catch (domError) {
      console.error("❌ [DEBUG] Direct DOM manipulation failed:", domError);
    }

    if (typeof Swal !== "undefined") {
      Swal.fire("Error", "Failed to open modal: " + error.message, "error");
    } else {
      alert("Failed to open modal: " + error.message);
    }
    return false;
  }
};

function initializeSectionsTab() {
  console.log("Initializing Sections Tab...");
  populateClassSelector();

  // Remove existing handlers to avoid duplicates
  $("#classSelector").off("change");
  $("#classSelector").on("change", function () {
    const classId = $(this).val();
    if (classId) {
      $("#addSectionBtnForClass").prop("disabled", false);
      initializeSectionsTable(classId);
    } else {
      $("#addSectionBtnForClass").prop("disabled", true);
      if (sectionsTable) sectionsTable.clear().draw();
      $("#classSectionInfo").text("");
    }
  });

  // Wait a bit to ensure button exists in DOM, then attach handler
  setTimeout(function () {
    const addSectionBtn = document.getElementById("addSectionBtnForClass");
    if (!addSectionBtn) {
      console.error("Add Section button not found!");
      return;
    }

    console.log("Attaching click handler to Add Section button");

    // Remove any existing click handlers (both jQuery and vanilla)
    $(addSectionBtn).off("click");

    // Attach click handler using vanilla JS for reliability
    addSectionBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      window.openAddSectionModal();
    });
    console.log("Click handler attached successfully");
  }, 100);

  // Handle modal show event as backup to set class_id
  const addSectionModal = document.getElementById("addSectionModal");
  if (addSectionModal) {
    // Use a named function so we can remove it if needed
    const handleModalShow = function (event) {
      console.log("🟢 [DEBUG] Modal show.bs.modal event fired");
      const classId =
        document.getElementById("classSelector")?.value ||
        $("#classSelector").val();
      if (!classId || classId === "") {
        console.warn("⚠️ [DEBUG] Modal opened without class selected");
      } else {
        // Ensure class_id is set
        const classIdInput = document.getElementById("add_sec_class_id");
        if (classIdInput) {
          classIdInput.value = classId;
          console.log("✅ [DEBUG] Class ID set in modal show event:", classId);
        }
      }

      // Debug modal visibility after show event
      setTimeout(() => {
        const isVisible =
          addSectionModal.classList.contains("show") &&
          window.getComputedStyle(addSectionModal).display !== "none";
        console.log("🔍 [DEBUG] Modal visibility after show event:", {
          hasShowClass: addSectionModal.classList.contains("show"),
          display: window.getComputedStyle(addSectionModal).display,
          visibility: window.getComputedStyle(addSectionModal).visibility,
          zIndex: window.getComputedStyle(addSectionModal).zIndex,
          isVisible: isVisible,
        });

        if (!isVisible) {
          console.warn(
            "⚠️ [DEBUG] Modal not visible after show event, attempting force show..."
          );
          const classId =
            document.getElementById("classSelector")?.value ||
            $("#classSelector").val();
          if (classId) {
            window.forceShowAddSectionModal(classId);
          }
        }
      }, 50);
    };

    const handleModalShown = function (event) {
      console.log(
        "✅ [DEBUG] Modal shown.bs.modal event fired - modal is now visible"
      );
    };

    const handleModalHide = function (event) {
      console.log("🟡 [DEBUG] Modal hide.bs.modal event fired");
    };

    // Remove any existing listeners to avoid duplicates
    addSectionModal.removeEventListener("show.bs.modal", handleModalShow);
    addSectionModal.removeEventListener("shown.bs.modal", handleModalShown);
    addSectionModal.removeEventListener("hide.bs.modal", handleModalHide);

    // Add event listeners
    addSectionModal.addEventListener("show.bs.modal", handleModalShow);
    addSectionModal.addEventListener("shown.bs.modal", handleModalShown);
    addSectionModal.addEventListener("hide.bs.modal", handleModalHide);

    console.log("✅ [DEBUG] Modal event listeners attached");
  } else {
    console.warn(
      "⚠️ [DEBUG] Add Section Modal element not found during initialization"
    );
  }
}

function populateClassSelector() {
  fetch("../../admin/management/get_classes.php")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        classList = data.data;
        const selector = $("#classSelector");
        selector.empty().append('<option value="">Select a class...</option>');
        classList.forEach((cls) => {
          const level = `${cls.class_lvl}${
            ["st", "nd", "rd"][cls.class_lvl - 1] || "th"
          } Year`;
          const term = `${cls.class_term}${
            ["st", "nd", "rd"][cls.class_term - 1] || "th"
          } Term`;
          const text = `${cls.sy_name} - ${cls.curr_name} - ${level} - ${term}`;
          selector.append(
            `<option value="${cls.class_id}">${escapeHtml(text)}</option>`
          );
        });
      }
    });
}

function initializeSectionsTable(classId) {
  const ajaxUrl = `../../admin/management/get_sections.php?class_id=${classId}`;

  if (!$.fn.DataTable.isDataTable("#sectionsTable")) {
    sectionsTable = $("#sectionsTable").DataTable({
      data: [],
      columns: [
        { data: "sec_num" },
        { data: "sec_name" },
        {
          data: null,
          orderable: false,
          className: "text-center",
          render: (data, type, row) => `
            <button class="btn-edit-section btn btn-sm btn-outline-primary" data-sec-id="${row.sec_id}" title="Edit Section"><i class="bi bi-pencil-square"></i> Edit</button>
            <button class="btn-delete-section btn btn-sm btn-outline-danger" data-sec-id="${row.sec_id}" title="Delete Section"><i class="bi bi-trash"></i> Delete</button>
          `,
        },
      ],
      responsive: true,
      language: { emptyTable: "No sections found for this class." },
    });
  }

  // Fetch and populate sections
  fetchDataArray(ajaxUrl).then((data) => {
    console.log(
      "[DT FETCH] sections fetched for",
      ajaxUrl,
      Array.isArray(data)
        ? data.length
        : data && data.data
        ? data.data.length
        : "unknown"
    );
    if (Array.isArray(data) && data.length)
      console.log("[DT FETCH] sample section:", data[0]);
    if (!sectionsTable) return;
    sectionsTable.clear();
    sectionsTable.rows.add(data);
    sectionsTable.draw();
    // Update UI placeholder and badge
    updateTableUI(
      "#sectionsTable",
      sectionsTable.rows().count(),
      "Select a class to load sections."
    );
    debugDataTable("Sections", sectionsTable, "#sectionsTable");
    // If server provided class_info in response, update count
    // try to fetch full response to access class_info
    fetch(`../../admin/management/get_sections.php?class_id=${classId}`)
      .then((r) => r.json())
      .then((json) => {
        if (json && json.class_info) {
          updateClassSectionInfo(
            (json.data && json.data.length) || 0,
            json.class_info.class_secno
          );
        }
      })
      .catch(() => {});
  });
}

function updateClassSectionInfo(current, total) {
  $("#classSectionInfo").html(
    `<strong>${current}</strong> of <strong>${total}</strong> sections created.`
  );
}

function addSection() {
  const form = document.getElementById("addSectionForm");
  const classId = document.getElementById("classSelector").value;

  if (!classId || classId === "") {
    Swal.fire("Error", "Please select a class first.", "error");
    return;
  }

  document.getElementById("add_sec_class_id").value = classId;
  const formData = new FormData(form);

  fetch("../../admin/management/add_section.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res.json();
    })
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("addSectionModal");
        form.reset();
        // Refresh sections and update counts using the full JSON response
        fetch(
          `../../admin/management/get_sections.php?class_id=${encodeURIComponent(
            classId
          )}`
        )
          .then((r) => {
            if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
            return r.json();
          })
          .then((json) => {
            if (!sectionsTable) return;
            const rows = json.data || json;
            sectionsTable.clear();
            sectionsTable.rows.add(rows);
            sectionsTable.draw();
            if (json && json.class_info) {
              updateClassSectionInfo(
                (json.data && json.data.length) || rows.length,
                json.class_info.class_secno
              );
            }
          })
          .catch((error) => {
            console.error("Error refreshing sections:", error);
          });
      } else {
        Swal.fire("Error", data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error adding section:", error);
      Swal.fire("Error", "Failed to add section. Please try again.", "error");
    });
}

function editSection(sectionData) {
  $("#edit_sec_id").val(sectionData.sec_id);
  $("#edit_sec_num").val(sectionData.sec_num);
  $("#edit_sec_name").val(sectionData.sec_name);
  const editModal = new bootstrap.Modal(
    document.getElementById("editSectionModal")
  );
  editModal.show();
}

function updateSection() {
  const form = document.getElementById("editSectionForm");
  const formData = new FormData(form);
  fetch("../../admin/management/update_section.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        Swal.fire("Success", data.message, "success");
        safeHideModal("editSectionModal");
        // Refresh sections for currently selected class
        const clsId = document.getElementById("classSelector")
          ? document.getElementById("classSelector").value
          : null;
        fetch(
          `../../admin/management/get_sections.php?class_id=${encodeURIComponent(
            clsId
          )}`
        )
          .then((r) => r.json())
          .then((json) => {
            if (!sectionsTable) return;
            const rows = json.data || json;
            sectionsTable.clear();
            sectionsTable.rows.add(rows);
            sectionsTable.draw();
          })
          .catch(() => {});
      } else {
        Swal.fire("Error", data.message, "error");
      }
    });
}

function deleteSection(id) {
  Swal.fire({
    title: "Are you sure?",
    text: "This action cannot be undone!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("sec_id", id);
      fetch("../../admin/management/delete_section.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            Swal.fire("Deleted!", data.message, "success");
            // Refresh sections and update counts
            const clsId = document.getElementById("classSelector")
              ? document.getElementById("classSelector").value
              : null;
            fetch(
              `../../admin/management/get_sections.php?class_id=${encodeURIComponent(
                clsId
              )}`
            )
              .then((r) => r.json())
              .then((json) => {
                if (!sectionsTable) return;
                const rows = json.data || json;
                sectionsTable.clear();
                sectionsTable.rows.add(rows);
                sectionsTable.draw();
                if (json && json.class_info) {
                  updateClassSectionInfo(
                    (json.data && json.data.length) || rows.length,
                    json.class_info.class_secno
                  );
                }
              })
              .catch(() => {});
          } else {
            Swal.fire("Error", data.message, "error");
          }
        });
    }
  });
}

// Global event delegation for Add Section button (fallback)
document.addEventListener("click", function (e) {
  if (
    e.target &&
    (e.target.id === "addSectionBtnForClass" ||
      e.target.closest("#addSectionBtnForClass"))
  ) {
    const btn =
      e.target.id === "addSectionBtnForClass"
        ? e.target
        : e.target.closest("#addSectionBtnForClass");
    if (btn && !btn.disabled) {
      console.log("🟡 [DEBUG] Add Section button clicked via event delegation");
      e.preventDefault();
      e.stopPropagation();

      // Get class ID first
      const classId =
        document.getElementById("classSelector")?.value ||
        $("#classSelector").val();
      console.log("🔍 [DEBUG] Class ID from selector:", classId);

      if (typeof window.openAddSectionModal === "function") {
        const result = window.openAddSectionModal();
        console.log("🔍 [DEBUG] openAddSectionModal returned:", result);

        // If primary method failed, try alternative
        if (!result) {
          console.log(
            "🔄 [DEBUG] Primary method failed, trying forceShowAddSectionModal..."
          );
          if (
            classId &&
            typeof window.forceShowAddSectionModal === "function"
          ) {
            window.forceShowAddSectionModal(classId);
          }
        }
      } else {
        console.error("❌ [DEBUG] openAddSectionModal function not found");
        // Last resort: try jQuery
        if (typeof $ !== "undefined" && $.fn.modal && classId) {
          console.log("🔄 [DEBUG] Trying jQuery modal as last resort...");
          $("#addSectionModal").modal("show");
          const classIdInput = document.getElementById("add_sec_class_id");
          if (classIdInput) {
            classIdInput.value = classId;
          }
        }
      }
    }
  }
});

// ============================================================
// Remove inline onclick handlers and use Bootstrap Tab API
// ============================================================
// This script runs on DOMContentLoaded to:
// 1. Remove inline onclick="showTab(...)" handlers
// 2. Ensure href="#tabId" is set correctly
// 3. Add event delegation for nav-link clicks
// This makes Bootstrap tabs the single source of truth
// ============================================================
document.addEventListener("DOMContentLoaded", function() {
  // ============================================================
  // REMOVE ALL INLINE ONCLICK HANDLERS AND CONVERT TO BOOTSTRAP TABS
  // ============================================================
  // This ensures Bootstrap is the single source of truth for tab management
  // ============================================================
  const navLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
  
  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    const onclick = link.getAttribute('onclick');
    
    // Skip if it's not a tab link (e.g., logout modal link)
    if (!href || !href.startsWith('#') || href === '#' || link.hasAttribute('data-bs-toggle') && link.getAttribute('data-bs-toggle') === 'modal') {
      return;
    }
    
    // Extract tabId from href or onclick
    let tabId = href.substring(1); // Remove # from href
    
    // If onclick exists and contains showTab, extract tabId from it (might be more accurate)
    if (onclick && onclick.includes('showTab')) {
      const match = onclick.match(/showTab\s*\(\s*['"]([^'"]+)['"]/);
      if (match) {
        tabId = match[1];
        // Update href to match tabId from onclick
        link.setAttribute('href', `#${tabId}`);
      }
    }
    
    // Check if target tab exists and is a Bootstrap tab-pane
    const targetTab = document.getElementById(tabId);
    if (targetTab && targetTab.classList.contains('tab-pane')) {
      // Remove ALL onclick handlers - Bootstrap will handle clicks via href
      if (onclick) {
        link.removeAttribute('onclick');
        if (window.DEBUG_MODE) {
          console.log('[TAB CLEANUP] Removed onclick from:', link, 'tabId:', tabId);
        }
      }
      
      // Add Bootstrap tab attributes
      link.setAttribute('data-bs-toggle', 'tab');
      link.setAttribute('role', 'tab');
      link.setAttribute('aria-controls', tabId);
      
      // Ensure href is correct
      if (link.getAttribute('href') !== `#${tabId}`) {
        link.setAttribute('href', `#${tabId}`);
      }
      
      if (window.DEBUG_MODE) {
        console.log('[TAB CLEANUP] Converted to Bootstrap tab:', link, 'tabId:', tabId);
      }
    } else if (window.DEBUG_MODE) {
      console.warn('[TAB CLEANUP] Skipped link - target tab not found or not a tab-pane:', link, 'tabId:', tabId);
    }
  });
  
  // ============================================================
  // PREVENT ONCLICK HANDLERS FROM EXECUTING (Capture Phase)
  // ============================================================
  // This runs BEFORE onclick handlers, preventing showTab() from executing
  // Bootstrap will handle the tab switch via its native tab system
  // ============================================================
  document.addEventListener('click', function(e) {
    const navLink = e.target.closest('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
    if (!navLink) return;
    
    // Skip modal links
    if (navLink.getAttribute('data-bs-toggle') === 'modal') return;
    
    const href = navLink.getAttribute('href');
    const onclick = navLink.getAttribute('onclick');
    
    // If this link has an onclick handler with showTab, prevent it and let Bootstrap handle it
    if (onclick && onclick.includes('showTab') && href && href.startsWith('#')) {
      e.preventDefault();
      e.stopPropagation();
      
      // Remove onclick if still present
      if (navLink.hasAttribute('onclick')) {
        navLink.removeAttribute('onclick');
      }
      
      // Ensure Bootstrap attributes are set
      const tabId = href.substring(1);
      const targetTab = document.getElementById(tabId);
      if (targetTab && targetTab.classList.contains('tab-pane')) {
        navLink.setAttribute('data-bs-toggle', 'tab');
        navLink.setAttribute('role', 'tab');
        navLink.setAttribute('aria-controls', tabId);
        
        // Use Bootstrap Tab API to show the tab
        if (typeof bootstrap !== "undefined" && bootstrap.Tab) {
          try {
            const tab = bootstrap.Tab.getOrCreateInstance(navLink);
            tab.show();
          } catch (error) {
            console.error('[TAB CLEANUP] Error showing tab via Bootstrap API:', error);
          }
        }
      }
      
      // Close mobile sidebar
      const offcanvasEl = document.getElementById("sidebarOffcanvas");
      if (offcanvasEl && typeof bootstrap !== "undefined" && bootstrap.Offcanvas) {
        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (bsOffcanvas && bsOffcanvas._isShown && window.innerWidth < 992) {
          bsOffcanvas.hide();
        }
      }
      
      return false;
    }
    
    // For links that already have data-bs-toggle="tab", just close mobile sidebar
    if (navLink.getAttribute('data-bs-toggle') === 'tab') {
      const offcanvasEl = document.getElementById("sidebarOffcanvas");
      if (offcanvasEl && typeof bootstrap !== "undefined" && bootstrap.Offcanvas) {
        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (bsOffcanvas && bsOffcanvas._isShown && window.innerWidth < 992) {
          bsOffcanvas.hide();
        }
      }
    }
  }, true); // Use capture phase to run BEFORE onclick handlers
});

// Initialize tables when their respective tabs are shown
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM fully loaded. Setting up DataTables initializers.");

  // Initialize room schedule event delegation
  initializeRoomScheduleEventDelegation();

  // Initialize room schedule modal (simple check)
  initializeRoomScheduleModal();

  // ============================================================
  // TAB STATE TRACKING - Enterprise-grade tab state registry
  // ============================================================
  // TAB_STATE is now defined globally at the top of admin_dashboard.js
  // This ensures it's available before any code runs, preventing ReferenceErrors
  // No need to initialize here - it's already available globally
  
  let activeInnerTabId = null; // Track the currently active inner tab
  let defaultTabInitialized = false; // Guard: only set default tab once
  window._activeRoomTab = null; // Global tracker for user-selected tab
  
  // REMOVED: Duplicate shown.bs.tab listener
  // ============================================================
  // This listener was causing duplicate initialization and noisy logs.
  // All tab tracking is now handled EXCLUSIVELY by the main shown.bs.tab
  // listener below (line ~21093) which filters programmatic activations.
  // ============================================================

  // REMOVED: Duplicate shown.bs.tab listener on roomManagementTabs element
  // ============================================================
  // This listener was causing duplicate initialization and conflicts.
  // Tab initialization is now handled EXCLUSIVELY by the document-level
  // shown.bs.tab listener (line ~21325) which has proper guards and
  // initializedTabs Set tracking.
  // ============================================================
  const roomManagementTabs = document.getElementById("roomManagementTabs");
  if (roomManagementTabs) {
    console.log(
      "Found roomManagementTabs container. Tab initialization handled by document-level listener."
    );
    // No longer attaching listener here - all initialization happens in document-level listener

    // Ensure inner roomManagement tab content is visible (sometimes server renders it hidden)
    const innerContent = document.getElementById("roomManagementTabsContent");
    if (innerContent && innerContent.style.display === "none") {
      innerContent.style.display = "block";
      console.log(
        "Unhid #roomManagementTabsContent to ensure DataTables are visible."
      );
    }

    // ============================================================
    // DEFAULT TAB INITIALIZATION - Only initialize data, NEVER set tabs
    // ============================================================
    // NOTE: Default tab is now set in ensureRoomManagementReady() AFTER container unhide
    // This function only initializes empty tables, never activates tabs
    // Tabs are set programmatically only when container is unhidden (see ensureRoomManagementReady)
    console.log("[TAB TRACK] Initializing empty tables (NO TAB CHANGES)");
    
    // Initialize empty tables for controls to appear
    // These will be populated when their respective tabs are shown
    // DO NOT activate any tabs here - that's handled in ensureRoomManagementReady()

    // Helper: debug DataTable instances and data in console
    function debugDataTable(name, tableInstance, selector) {
      try {
        const isDT = !!(
          tableInstance && typeof tableInstance.rows === "function"
        );
        console.log(`[DT DEBUG] ${name} - DataTable initialized: ${isDT}`, {
          selector,
        });
        if (!isDT) {
          // Try to detect an existing DataTable via selector
          if ($.fn.DataTable.isDataTable(selector)) {
            const dt = $(selector).DataTable();
            console.log(
              `[DT DEBUG] ${name} - Found DataTable via selector. Rows: ${dt
                .rows()
                .count()}`,
              dt.rows().data().toArray().slice(0, 3)
            );
          } else {
            console.log(
              `[DT DEBUG] ${name} - No DataTable instance found for selector ${selector}`
            );
          }
          return;
        }

        const rowCount = tableInstance.rows().count();
        const sample =
          (tableInstance.rows().data() &&
            tableInstance.rows().data().toArray &&
            tableInstance.rows().data().toArray().slice(0, 3)) ||
          [];
        console.log(`[DT DEBUG] ${name} - Rows: ${rowCount}`, { sample });
        // expose a limited snapshot for quick inspection
        if (rowCount && sample.length)
          console.log(`[DT DEBUG] ${name} - First row sample:`, sample[0]);
      } catch (err) {
        console.error(
          `[DT DEBUG] ${name} - Error while debugging DataTable:`,
          err
        );
      }
    }
    // create empty rooms and sections tables (will populate when a building/class is selected)
    // Don't pass empty string - let the function handle empty state
    initializeRoomsTable(null);
    initializeSectionsTable(null);

    // Load departments when add room modal opens (Admin Support only)
    $("#addRoomModal").on("show.bs.modal", function () {
      loadDepartmentsForRoomAdd();
      // Populate building dropdown in modal (this also pre-selects if building is selected on main page)
      populateBuildingDropdownInModal();
    });
  } else {
    // Silently skip if roomManagementTabs doesn't exist (e.g., in instructor dashboard)
    // This is expected behavior, not an error
    // Only log in debug mode or if we're definitely in admin context
    const isAdminContext = document.getElementById('overview') && 
                           (document.querySelector('.nav-link[onclick*="room"]') || 
                            window.location.pathname.includes('/admin/'));
    if (isAdminContext) {
      console.warn(
        "Could not find roomManagementTabs container. DataTables will not be initialized on tab clicks."
      );
    }
    // Otherwise, silently skip (instructor dashboard doesn't have room management)
  }

  // Listen for main dashboard tab switches: when the Room Management main tab is shown,
  // ensure inner tab content is visible and re-adjust DataTables. This handles navigating
  // away and back to the Room Management page where tables can render incorrectly.
  
  // TAB_STATE is now defined globally at the top of this file
  // No need to check or initialize here - it's already available
  
  // Ensure Room Management is ready (one-shot, idempotent)
  let roomManagementInitialized = false;
  function ensureRoomManagementReady() {
    // One-shot: if already initialized, skip
    if (roomManagementInitialized) {
      console.log("[ROOMS DEBUG] ensureRoomManagementReady() already initialized, skipping");
      return;
    }
    
    roomManagementInitialized = true;
    console.log("[ROOMS DEBUG] ensureRoomManagementReady() called - ONE TIME INITIALIZATION");
    try {
      // First, ensure the parent room_requests tab is visible
      const parentTab = document.getElementById("room_requests");
      if (parentTab) {
        parentTab.style.display = "block";
        parentTab.style.visibility = "visible";
        console.log("[ROOMS DEBUG] Ensuring parent #room_requests tab is visible");
      } else {
        console.error("[ROOMS DEBUG] Parent #room_requests tab not found!");
      }
      
      const inner = document.getElementById("roomManagementTabsContent");
      // Always ensure container is visible (it might be hidden by showTab())
      if (inner) {
        // Force visibility - this container should ALWAYS be visible when room_requests tab is shown
        inner.style.display = "block";
        inner.style.visibility = "visible";
        console.log(
          "[ROOMS DEBUG] Ensuring #roomManagementTabsContent is visible (ensureRoomManagementReady).",
          "Display:", inner.style.display,
          "Visibility:", inner.style.visibility,
          "Computed display:", window.getComputedStyle(inner).display
        );
        
        // ============================================================
        // REMOVED: Programmatic inner tab restoration
        // ============================================================
        // Bootstrap handles active states naturally based on HTML
        // Inner tabs will initialize when shown.bs.tab fires (user-initiated only)
        // No programmatic tab activation - let Bootstrap handle it
        // ============================================================
      }

      // Initialize the currently active inner tab once (no shown event when already active)
      if (!defaultTabInitialized) {
        defaultTabInitialized = true;
        const activeInnerTab = document.querySelector("#roomManagementTabs .nav-link.active");
        if (activeInnerTab && activeInnerTab.id) {
          const tabId = activeInnerTab.id;
          const activeTabTarget = activeInnerTab.getAttribute("data-bs-target");
          window._activeRoomTab = tabId;
          window.initTabOnce(tabId, () => {
            console.log("[TAB TRACK] Initializing active inner tab on ready:", tabId);
            if (activeTabTarget === "#buildings") {
              initializeBuildingsTable();
            } else if (activeTabTarget === "#rooms") {
              initializeRoomsTab();
            } else if (activeTabTarget === "#classes") {
              initializeClassesTable();
            } else if (activeTabTarget === "#sections") {
              const cls = document.getElementById("classSelector")?.value;
              if (cls) {
                initializeSectionsTable(cls);
              }
            } else if (activeTabTarget === "#room-requests") {
              initializeRoomRequestsTab();
            }
          });
        } else {
          console.warn("[TAB TRACK] No active inner tab found for initialization");
        }
      }
      // REMOVED: Tab initialization from ensureRoomManagementReady()
      // Tab initialization is now handled EXCLUSIVELY in shown.bs.tab event handler
      // This ensures each tab initializes exactly once and prevents duplicate fetches
      // ensureRoomManagementReady() ONLY handles:
      // - Visibility (display/visibility styles)
      // - Default tab selection (if no tab is active)

      setTimeout(() => {
        console.log("[ROOMS DEBUG] ensureRoomManagementReady - adjusting tables after 200ms");
        [
          "#buildingsTable",
          "#roomsTable",
          "#classesTable",
          "#sectionsTable",
        ].forEach((sel) => {
          try {
            if ($.fn.DataTable.isDataTable(sel)) {
              const dt = $(sel).DataTable();
              if (dt && dt.columns) {
                if (sel === "#roomsTable") {
                  const rowCountBefore = dt.rows().count();
                  console.log("[ROOMS DEBUG] ensureRoomManagementReady - adjusting rooms table (rows before:", rowCountBefore, ")");
                }
                dt.columns.adjust();
                if (dt.responsive) dt.responsive.recalc();
                dt.draw(false);
                if (sel === "#roomsTable") {
                  const rowCountAfter = dt.rows().count();
                  console.log("[ROOMS DEBUG] ensureRoomManagementReady - rooms table adjusted (rows after:", rowCountAfter, ")");
                }
              }
            }
          } catch (err) {
            if (sel === "#roomsTable") {
              console.error("[ROOMS DEBUG] ensureRoomManagementReady - error adjusting rooms table:", err);
            }
          }
        });
      }, 200);
    } catch (err) {
      console.error("ensureRoomManagementReady error", err);
      // Reset initialization flag on error so it can be retried
      roomManagementInitialized = false;
    }
  }

  // ============================================================
  // SINGLE SOURCE OF TRUTH: Main tab shown event handler
  // ============================================================
  // This handler:
  // ✅ Filters out programmatic tab activations (isTrusted: false)
  // ✅ Initializes tabs ONLY on user-initiated shown.bs.tab events
  // ✅ Uses TAB_STATE registry to prevent duplicate initialization
  // ✅ Logs only meaningful user interactions
  // ============================================================
  document.addEventListener("shown.bs.tab", function (e) {
    try {
      // Get target tab from href (main tabs) or data-bs-target (inner tabs)
      const targetTab = e.target.getAttribute("data-bs-target") || 
                       (e.target.getAttribute("href") ? e.target.getAttribute("href") : null);
      
      // Extract tabId from target
      const tabId = targetTab ? targetTab.replace('#', '') : (e.target.id || null);
      
      // Check if this is an inner tab (within roomManagementTabs)
      const isInnerTab = e.target.closest && e.target.closest("#roomManagementTabs");
      
      // ============================================================
      // REMOVED: isTrusted CHECK for all tabs (main and inner)
      // ============================================================
      // Bootstrap may trigger shown.bs.tab events with isTrusted: false even for user clicks,
      // especially for button-based tabs. The initTabOnce() guard prevents duplicate initialization,
      // making the isTrusted check unnecessary and potentially harmful.
      // 
      // All tabs (main and inner) now initialize when shown.bs.tab fires, regardless of isTrusted.
      // The initTabOnce() function ensures each tab initializes exactly once, preventing duplicates.
      // ============================================================
      
      // Log tab changes
      if (window.DEBUG_MODE) {
        const stackTrace = window.DEBUG_MODE ? new Error().stack : null;
        console.log("[TAB TRACK] TAB SHOWN:", e.target.id, "target:", targetTab, "tabId:", tabId, "isInnerTab:", isInnerTab, "isTrusted:", e.isTrusted, stackTrace);
      } else {
        console.log("[TAB TRACK] TAB SHOWN:", e.target.id, "target:", targetTab, "tabId:", tabId, "isInnerTab:", isInnerTab);
      }
      
      // Persist last active tab
      if (tabId) {
        try {
          localStorage.setItem("admin_active_tab", tabId);
        } catch (e) {}
      }
      
      // Mobile: hide overview pane when another tab is active (fix overview staying visible on mobile)
      if (!isInnerTab && tabId) {
        const overviewPane = document.getElementById("overview");
        const isMobile = window.innerWidth < 992;
        if (overviewPane) {
          if (isMobile && tabId !== "overview") {
            overviewPane.classList.add("mobile-tab-hidden");
          } else {
            overviewPane.classList.remove("mobile-tab-hidden");
          }
        }
      }
      
      // Handle inner tabs (Buildings/Rooms/Classes/Sections)
      if (isInnerTab) {
        // Inner tab changed - update active tab tracker and initialize if needed
        const tabId = e.target.id;
        window._activeRoomTab = tabId;
        
        // Check if tab is already active (prevent duplicate initialization on re-clicks)
        const isAlreadyActive = e.target.classList.contains("active");
        if (isAlreadyActive && window.TAB_STATE.has(tabId)) {
          if (window.DEBUG_MODE) {
            console.log("[TAB TRACK] Tab already active and initialized:", tabId, "- skipping");
          }
          return; // Skip already-active tabs
        }
        
        // Get the active tab target to determine which table to initialize
        const activeTabTarget = e.target.getAttribute("data-bs-target");
        
        // Initialize this tab exactly once using the helper function
        // This is the ONLY place where tab initialization happens
        window.initTabOnce(tabId, () => {
          console.log("[TAB TRACK] Initializing inner tab for first time:", tabId);
          
          // Initialize immediately (no setTimeout needed - Bootstrap has already shown the tab)
          if (activeTabTarget === "#buildings") {
            console.log("[TAB TRACK] Initializing Buildings tab");
            initializeBuildingsTable();
          } else if (activeTabTarget === "#rooms") {
            console.log("[TAB TRACK] Initializing Rooms tab");
            // Check for pending building selection BEFORE initialization
            // This ensures it's handled properly during initialization
            const pendingValueBeforeInit = window._pendingBuildingSelection;
            
            initializeRoomsTab(); // Has its own guard to prevent duplicate initialization
            
            // If there's still a pending selection after initialization, process it
            // (initializeRoomsTab should handle it, but this is a safety net)
            if (window._pendingBuildingSelection && isRoomsTabVisible()) {
              const pendingValue = window._pendingBuildingSelection;
              delete window._pendingBuildingSelection;
              console.log("[TAB TRACK] Processing remaining pending building selection:", pendingValue);
              const selector = $("#buildingSelector");
              if (selector.length) {
                selector.val(pendingValue);
                $("#addRoomBtn").prop("disabled", false);
                // Trigger change to load rooms now that tab is visible
                selector.trigger("change");
              }
            }
          } else if (activeTabTarget === "#classes") {
            console.log("[TAB TRACK] Initializing Classes tab");
            initializeClassesTable();
          } else if (activeTabTarget === "#sections") {
            console.log("[TAB TRACK] Initializing Sections tab");
            const cls = document.getElementById("classSelector")?.value;
            if (cls) {
              initializeSectionsTable(cls);
            } else {
              console.log("[TAB TRACK] No class selected for sections tab");
            }
          } else if (activeTabTarget === "#room-requests") {
            console.log("[TAB TRACK] Initializing Room Requests tab");
            initializeRoomRequestsTab();
          } else {
            console.warn("[TAB TRACK] Unknown tab target:", activeTabTarget);
            // Don't mark as initialized if unknown
            window.TAB_STATE.delete(tabId);
          }
        });
        return; // Don't process inner tab events further
      }
      
      // Main tab shown - handle main dashboard tabs
      // Initialize each main tab exactly once using initTabOnce
      if (tabId && !isInnerTab) {
        window.initTabOnce(`main_${tabId}`, () => {
          console.log("[TAB TRACK] Initializing main tab:", tabId);
          
          // Handle room_requests tab
          if (tabId === "room_requests") {
            console.log("[ROOMS DEBUG] Room requests tab shown, calling ensureRoomManagementReady()");
            ensureRoomManagementReady();
          }
          // Handle curriculum (subjects) tab
          else if (tabId === "curriculum") {
            console.log("📚 Subjects tab shown, initializing filters and table...");
            // Subject tab initialization is complex and handled by its own component
            // Just trigger the load functions
            if (typeof loadCurriculumData === "function") {
              loadCurriculumData();
            }
            if (typeof loadSubjectStatistics === "function") {
              loadSubjectStatistics(null);
            }
          }
          // Handle curriculum_management tab
          else if (tabId === "curriculum_management") {
            console.log("📖 Curriculum Management tab shown, loading data...");
            if (typeof loadCurriculumData === "function") {
              loadCurriculumData();
            } else if (typeof refreshCurriculumData === "function") {
              refreshCurriculumData();
            }
          }
          // Handle course_management tab
          else if (tabId === "course_management") {
            console.log("📘 Course Management tab shown, loading data...");
            if (typeof loadProgramsData === "function") {
              loadProgramsData();
            } else if (typeof loadCourseData === "function") {
              loadCourseData();
            }
          }
          // Handle schedule tab
          else if (tabId === "schedule") {
            console.log("🗓️ Schedule tab shown, initializing schedules...");
            if (typeof loadScheduleFormData === "function") {
              loadScheduleFormData().then(() => {
                if (typeof loadClassSelector === "function") {
                  loadClassSelector();
                }
                if (typeof loadSectionFilter === "function") {
                  loadSectionFilter();
                }
                if (typeof loadSchedules === "function") {
                  loadSchedules();
                }
              }).catch(() => {
                if (typeof loadSchedules === "function") {
                  loadSchedules();
                }
              });
            } else if (typeof loadSchedules === "function") {
              loadSchedules();
            }
          }
          // All other tabs use loadTabContent
          else {
            loadTabContent(tabId);
          }
        });
      }
    } catch (err) {
      console.error("Tab handler error", err);
    }
  });

  // REMOVED: MutationObserver for room_requests tab
  // Tab initialization is now handled exclusively by shown.bs.tab event
  // This eliminates redundant triggers and ensures single source of truth

  // MutationObserver: watch for when the curriculum (subjects) tab becomes visible
  try {
    const curriculumEl = document.getElementById("curriculum");
    if (curriculumEl) {
      const curriculumMO = new MutationObserver((mutations) => {
        for (const m of mutations) {
          if (
            m.type === "attributes" &&
            (m.attributeName === "style" || m.attributeName === "class")
          ) {
            const el = m.target;
            const isVisible =
              el &&
              el.style &&
              el.style.display !== "none" &&
              !el.classList.contains("d-none");
            if (isVisible) {
              console.log(
                "📚 Curriculum tab became visible (via MutationObserver), initializing..."
              );
              
              // Load curriculum data when tab becomes visible
              if (typeof loadCurriculumData === "function") {
                console.log("📊 Loading curriculum data from MutationObserver...");
                // Call with multiple delays to ensure DOM is ready
                setTimeout(() => {
                  console.log("📊 Calling loadCurriculumData() from MutationObserver (first attempt)...");
                  loadCurriculumData();
                }, 200);
                
                // Retry after longer delay
                setTimeout(() => {
                  console.log("📊 Calling loadCurriculumData() from MutationObserver (retry attempt)...");
                  loadCurriculumData();
                }, 1000);
              } else {
                console.error("❌ loadCurriculumData function not found in MutationObserver!");
              }
              
              // Reset the filter setup flag
              subjectFilterListenersSetup = false;
              subjectFilterSetupAttempts = 0;

              setTimeout(() => {
                const container = document.getElementById(
                  "filteredSubjectsContainer"
                );
                const placeholder = document.getElementById(
                  "subjectsPlaceholder"
                );

                // Hide placeholder
                if (placeholder) {
                  placeholder.style.display = "none";
                }

                // Set up filters
                if (typeof setupSubjectFilterAutoUpdate === "function") {
                  setupSubjectFilterAutoUpdate();
                }

                // Load programs
                if (typeof loadProgramsForSubjectFilter === "function") {
                  loadProgramsForSubjectFilter();
                }

                // Check and create table if needed
                setTimeout(() => {
                  if (container) {
                    const existingTables = container.querySelectorAll(
                      "[data-table-id], .year-level-group-wrapper, .filtered-table-wrapper"
                    );
                    if (
                      existingTables.length === 0 &&
                      typeof addFilteredSubjectTable === "function"
                    ) {
                      console.log(
                        "📊 Creating default table via MutationObserver..."
                      );
                      addFilteredSubjectTable(true);
                    }
                  }

                  // Load statistics
                  const programFilter = document.getElementById(
                    "subjectProgramFilter"
                  );
                  const programId =
                    programFilter && programFilter.value
                      ? programFilter.value
                      : null;
                  const pfObs = getSubjectManagementFilters();
                  if (typeof loadSubjectStatistics === "function") {
                    loadSubjectStatistics(programId, {
                      curriculumId: pfObs.curriculumId,
                      term: pfObs.semester === "" ? "" : pfObs.semester,
                      search: pfObs.search,
                    });
                  }
                }, 500);
              }, 150);
            }
          }
        }
      });
      curriculumMO.observe(curriculumEl, {
        attributes: true,
        attributeFilter: ["style", "class"],
      });
    }
  } catch (err) {
    console.error("Curriculum MutationObserver setup error", err);
  }

  function syncSubjectCurriculumFilterByProgram(programId = "") {
    const curriculumFilter = document.getElementById("subjectCurriculumFilter");
    if (!curriculumFilter) return Promise.resolve();

    curriculumFilter.innerHTML = '<option value="">Loading Curricula...</option>';
    const endpoint = programId
      ? `../../admin/management/get_curricula.php?program_id=${encodeURIComponent(programId)}`
      : "../../admin/management/get_curricula.php";

    return fetch(endpoint)
      .then((response) => response.json())
      .then((data) => {
        curriculumFilter.innerHTML = '<option value="">All Curricula</option>';
        const curricula = data && data.success && data.data && Array.isArray(data.data.curricula)
          ? data.data.curricula
          : [];

        curricula.forEach((curr) => {
          let yearDisplay = "";
          if (curr.curr_yr && String(curr.curr_yr).trim() !== "") {
            yearDisplay = ` (${curr.curr_yr})`;
          } else if (curr.curr_effective_start_year) {
            yearDisplay = ` (${curr.curr_effective_start_year})`;
          }
          const option = document.createElement("option");
          option.value = curr.curr_id;
          option.textContent = `${curr.curr_name}${yearDisplay}`;
          curriculumFilter.appendChild(option);
        });
      })
      .catch((error) => {
        console.error("Error syncing curriculum filter:", error);
        curriculumFilter.innerHTML = '<option value="">All Curricula</option>';
      });
  }

  // MutationObserver: watch for when the curriculum_management tab becomes visible
  try {
    const curriculumMgmtEl = document.getElementById("curriculum_management");
    if (curriculumMgmtEl) {
      const curriculumMgmtMO = new MutationObserver((mutations) => {
        for (const m of mutations) {
          if (
            m.type === "attributes" &&
            (m.attributeName === "style" || m.attributeName === "class")
          ) {
            const el = m.target;
            const isVisible =
              el &&
              el.style &&
              el.style.display !== "none" &&
              !el.classList.contains("d-none");
            if (isVisible) {
              console.log(
                "📖 Curriculum Management tab became visible (via MutationObserver), loading data..."
              );
              setTimeout(() => {
                if (typeof refreshCurriculumData === "function") {
                  refreshCurriculumData();
                } else if (typeof loadCurriculumData === "function") {
                  loadCurriculumData();
                }
              }, 150);
            }
          }
        }
      });
      curriculumMgmtMO.observe(curriculumMgmtEl, {
        attributes: true,
        attributeFilter: ["style", "class"],
      });
    }
  } catch (err) {
    console.error("Curriculum Management MutationObserver setup error", err);
  }

  // MutationObserver: watch for when the course_management tab becomes visible
  try {
    const courseMgmtEl = document.getElementById("course_management");
    if (courseMgmtEl) {
      const courseMgmtMO = new MutationObserver((mutations) => {
        for (const m of mutations) {
          if (
            m.type === "attributes" &&
            (m.attributeName === "style" || m.attributeName === "class")
          ) {
            const el = m.target;
            const isVisible =
              el &&
              el.style &&
              el.style.display !== "none" &&
              !el.classList.contains("d-none");
            if (isVisible) {
              console.log(
                "📘 Course Management tab became visible (via MutationObserver), loading data..."
              );
              setTimeout(() => {
                if (typeof loadProgramsData === "function") {
                  loadProgramsData();
                } else if (typeof loadCourseData === "function") {
                  loadCourseData();
                }
              }, 150);
            }
          }
        }
      });
      courseMgmtMO.observe(courseMgmtEl, {
        attributes: true,
        attributeFilter: ["style", "class"],
      });
    }
  } catch (err) {
    console.error("Course Management MutationObserver setup error", err);
  }

  // Fallback: if side menu links are used, call ensureRoomManagementReady on click
  try {
    document
      .querySelectorAll(
        'a[data-bs-target="#room_requests"], a[href="#room_requests"]'
      )
      .forEach((a) => {
        a.addEventListener("click", () =>
          setTimeout(ensureRoomManagementReady, 100)
        );
      });
  } catch (err) {}

  // Delegated handlers for table action buttons (safer than inline onclick)
  $(document).on("click", ".btn-edit-building", function (e) {
    e.preventDefault();
    if (typeof buildingsTable === "undefined" || !buildingsTable) return;
    const $tr = $(this).closest("tr");
    const rowData = buildingsTable.row($tr).data();
    if (!rowData) return;
    editBuilding(rowData.bd_id, rowData.bd_desc, rowData.bd_status);
  });

  $(document).on("click", ".btn-delete-building", function (e) {
    e.preventDefault();
    const bdId = $(this).data("bd-id");
    if (!bdId) return;
    deleteBuilding(bdId);
  });

  $(document).on("click", ".btn-edit-room", function (e) {
    e.preventDefault();
    if (typeof roomsTable === "undefined" || !roomsTable) return;
    const $tr = $(this).closest("tr");
    const rowData = roomsTable.row($tr).data();
    if (!rowData) return;
    editRoom(rowData);
  });

  $(document).on("click", ".btn-delete-room", function (e) {
    e.preventDefault();
    const rmId = $(this).data("rm-id");
    if (!rmId) return;
    deleteRoom(rmId);
  });

  $(document).on("click", ".btn-edit-class", function (e) {
    e.preventDefault();
    if (typeof classesTable === "undefined" || !classesTable) return;
    const $tr = $(this).closest("tr");
    const rowData = classesTable.row($tr).data();
    if (!rowData) return;
    editClass(rowData);
  });

  $(document).on("click", ".btn-delete-class", function (e) {
    e.preventDefault();
    const classId = $(this).data("class-id");
    if (!classId) return;
    deleteClass(classId);
  });

  $(document).on("click", ".btn-edit-section", function (e) {
    e.preventDefault();
    if (typeof sectionsTable === "undefined" || !sectionsTable) return;
    const $tr = $(this).closest("tr");
    const rowData = sectionsTable.row($tr).data();
    if (!rowData) return;
    editSection(rowData);
  });

  $(document).on("click", ".btn-delete-section", function (e) {
    e.preventDefault();
    const secId = $(this).data("sec-id");
    if (!secId) return;
    deleteSection(secId);
  });

  // Make calendar date filter functions globally accessible
  if (typeof window !== "undefined") {
    window.applyRoomScheduleCalendarDateFilter =
      applyRoomScheduleCalendarDateFilter;
    window.clearRoomScheduleCalendarDateFilter =
      clearRoomScheduleCalendarDateFilter;
    console.log("Calendar date filter functions registered globally");
  }
});

/**
 * Show print workload options (wrapper for backward compatibility)
 */
function showPrintWorkloadOptions() {
  // This function is kept for backward compatibility
  // The dropdown menu handles the options directly
  batchPrintWorkloadForms();
}

/**
 * Batch Print Workload Forms
 * Generates and prints workload forms for all instructors with teaching loads
 * Opens in landscape orientation for PDF printing
 */
function batchPrintWorkloadForms() {
  // Show loading indicator
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Generating Workload Forms",
      text: "Please wait while we prepare the forms...",
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });
  }

  // Get the base URL for the management endpoint
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/").filter((p) => p);
  const rootPath = pathParts.slice(0, -3).join("/");
  const baseUrl = window.location.origin + (rootPath ? "/" + rootPath : "");
  const formsUrl = `${baseUrl}/admin/management/generate_workload_forms.php?format=html`;

  // Open in new tab/window for printing
  const printWindow = window.open(formsUrl, "_blank", "width=1200,height=800");

  if (!printWindow) {
    if (typeof Swal !== "undefined") {
      Swal.close();
      Swal.fire({
        icon: "error",
        title: "Print Blocked",
        text: "Please allow pop-ups for this site to open the print window.",
        confirmButtonColor: "#800000",
      });
    } else {
      alert("Please allow pop-ups for this site to open the print window.");
    }
    return;
  }

  // Close loading indicator
  if (typeof Swal !== "undefined") {
    Swal.close();
  }

  // Wait for the window to load, then trigger print with landscape orientation
  printWindow.onload = function () {
    setTimeout(() => {
      // Add print styles for landscape if not already in the document
      if (!printWindow.document.querySelector("style[data-landscape]")) {
        const style = printWindow.document.createElement("style");
        style.setAttribute("data-landscape", "true");
        style.textContent = `
          @media print {
            @page {
              size: A4 landscape;
              margin: 0.4cm;
            }
            body {
              transform: none !important;
              width: 100% !important;
            }
            .workload-form {
              page-break-inside: avoid !important;
              break-inside: avoid !important;
              height: auto !important;
              max-height: 100vh !important;
            }
          }
        `;
        printWindow.document.head.appendChild(style);
      }
      printWindow.print();
    }, 1000);
  };

  // Fallback if onload doesn't fire
  setTimeout(() => {
    if (printWindow.document.readyState === "complete") {
      if (!printWindow.document.querySelector("style[data-landscape]")) {
        const style = printWindow.document.createElement("style");
        style.setAttribute("data-landscape", "true");
        style.textContent = `
          @media print {
            @page {
              size: A4 landscape;
              margin: 0.4cm;
            }
            body {
              transform: none !important;
              width: 100% !important;
            }
            .workload-form {
              page-break-inside: avoid !important;
              break-inside: avoid !important;
              height: auto !important;
              max-height: 100vh !important;
            }
          }
        `;
        printWindow.document.head.appendChild(style);
      }
      printWindow.print();
    }
  }, 3000);
}

/**
 * Show Single Print Modal
 * Opens a modal to select a single instructor for workload form printing
 */
function showSinglePrintModal() {
  const modal = new bootstrap.Modal(document.getElementById("singlePrintWorkloadModal"));
  const select = document.getElementById("singlePrintInstructorSelect");
  const confirmBtn = document.getElementById("confirmSinglePrintBtn");
  
  // Clear previous selection
  select.innerHTML = '<option value="">-- Select an instructor --</option>';
  document.getElementById("selectedInstructorInfo").style.display = "none";
  confirmBtn.disabled = true;
  
  // Get instructors from the accounts cache
  const instructors = [];
  
  // First, try to get from __ACCOUNTS_CACHE__ if available
  if (typeof __ACCOUNTS_CACHE__ !== "undefined" && Array.isArray(__ACCOUNTS_CACHE__)) {
    __ACCOUNTS_CACHE__.forEach((account) => {
      if (account.inst_id && account.inst_id > 0) {
        instructors.push({
          inst_id: account.inst_id,
          name: account.full_name || "",
          email: account.acc_email || "",
          dept: account.dept_name || ""
        });
      }
    });
  }
  
  // If still no instructors found, try parsing the table
  if (instructors.length === 0) {
    const tableBody = document.getElementById("usersTableBody");
    if (tableBody) {
      const rows = tableBody.querySelectorAll("tr");
      
      rows.forEach((row) => {
        const viewBtn = row.querySelector('button[onclick*="viewInstructorSchedules"]');
        if (viewBtn) {
          const onclickAttr = viewBtn.getAttribute("onclick");
          const instIdMatch = onclickAttr.match(/viewInstructorSchedules\((\d+)/);
          if (instIdMatch && instIdMatch[1] && instIdMatch[1] !== "0") {
            const instId = parseInt(instIdMatch[1]);
            const nameCell = row.cells[1]; // Name is in second column
            const emailCell = row.cells[3]; // Email is in fourth column
            
            if (nameCell) {
              const name = nameCell.textContent.trim();
              const email = emailCell ? emailCell.textContent.trim() : "";
              
              // Try to get department from row data attributes
              let dept = "";
              if (row.dataset.dept) {
                dept = row.dataset.dept;
              }
              
              instructors.push({
                inst_id: instId,
                name: name,
                email: email,
                dept: dept
              });
            }
          }
        }
      });
    }
  }
  
  // Populate select dropdown
  if (instructors.length === 0) {
    select.innerHTML = '<option value="">No instructors found. Please ensure users are loaded.</option>';
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "No Instructors Found",
        text: "No instructors with active schedules found. Please ensure the users table is loaded.",
        confirmButtonColor: "#800000",
      });
    }
  } else {
    // Sort instructors by name
    instructors.sort((a, b) => a.name.localeCompare(b.name));
    
    instructors.forEach((instructor) => {
      const option = document.createElement("option");
      option.value = instructor.inst_id;
      option.textContent = `${instructor.name}${instructor.dept ? ` - ${instructor.dept}` : ""}`;
      option.dataset.name = instructor.name;
      option.dataset.email = instructor.email;
      option.dataset.dept = instructor.dept;
      select.appendChild(option);
    });
  }
  
  // Add change event listener
  select.onchange = function() {
    const selectedOption = select.options[select.selectedIndex];
    if (select.value && selectedOption) {
      document.getElementById("selectedInstructorName").textContent = selectedOption.dataset.name || "-";
      document.getElementById("selectedInstructorEmail").textContent = selectedOption.dataset.email || "-";
      document.getElementById("selectedInstructorDept").textContent = selectedOption.dataset.dept || "-";
      document.getElementById("selectedInstructorInfo").style.display = "block";
      confirmBtn.disabled = false;
    } else {
      document.getElementById("selectedInstructorInfo").style.display = "none";
      confirmBtn.disabled = true;
    }
  };
  
  modal.show();
}

/**
 * Confirm Single Print
 * Prints workload form for the selected instructor
 */
function confirmSinglePrint() {
  const select = document.getElementById("singlePrintInstructorSelect");
  const instId = select.value;
  
  if (!instId || instId === "") {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "No Instructor Selected",
        text: "Please select an instructor to print their workload form.",
        confirmButtonColor: "#800000",
      });
    } else {
      alert("Please select an instructor to print their workload form.");
    }
    return;
  }
  
  // Close modal
  const modal = bootstrap.Modal.getInstance(document.getElementById("singlePrintWorkloadModal"));
  if (modal) {
    modal.hide();
  }
  
  // Show loading indicator
  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: "Generating Workload Form",
      text: "Please wait while we prepare the form...",
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });
  }
  
  // Get the base URL for the management endpoint
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/").filter((p) => p);
  const rootPath = pathParts.slice(0, -3).join("/");
  const baseUrl = window.location.origin + (rootPath ? "/" + rootPath : "");
  const formsUrl = `${baseUrl}/admin/management/generate_workload_forms.php?format=html&inst_id=${instId}`;
  
  // Open in new tab/window for printing
  const printWindow = window.open(formsUrl, "_blank", "width=1200,height=800");
  
  if (!printWindow) {
    if (typeof Swal !== "undefined") {
      Swal.close();
      Swal.fire({
        icon: "error",
        title: "Print Blocked",
        text: "Please allow pop-ups for this site to open the print window.",
        confirmButtonColor: "#800000",
      });
    } else {
      alert("Please allow pop-ups for this site to open the print window.");
    }
    return;
  }
  
  // Close loading indicator
  if (typeof Swal !== "undefined") {
    Swal.close();
  }
  
  // Wait for the window to load, then trigger print with landscape orientation
  printWindow.onload = function () {
    setTimeout(() => {
      // Add print styles for landscape if not already in the document
      if (!printWindow.document.querySelector("style[data-landscape]")) {
        const style = printWindow.document.createElement("style");
        style.setAttribute("data-landscape", "true");
        style.textContent = `
          @media print {
            @page {
              size: A4 landscape;
              margin: 0.4cm;
            }
            body {
              transform: none !important;
              width: 100% !important;
            }
            .workload-form {
              page-break-inside: avoid !important;
              break-inside: avoid !important;
              height: auto !important;
              max-height: 100vh !important;
            }
          }
        `;
        printWindow.document.head.appendChild(style);
      }
      printWindow.print();
    }, 1000);
  };
  
  // Fallback if onload doesn't fire
  setTimeout(() => {
    if (printWindow.document.readyState === "complete") {
      if (!printWindow.document.querySelector("style[data-landscape]")) {
        const style = printWindow.document.createElement("style");
        style.setAttribute("data-landscape", "true");
        style.textContent = `
          @media print {
            @page {
              size: A4 landscape;
              margin: 0.4cm;
            }
            body {
              transform: none !important;
              width: 100% !important;
            }
            .workload-form {
              page-break-inside: avoid !important;
              break-inside: avoid !important;
              height: auto !important;
              max-height: 100vh !important;
            }
          }
        `;
        printWindow.document.head.appendChild(style);
      }
      printWindow.print();
    }
  }, 3000);
}