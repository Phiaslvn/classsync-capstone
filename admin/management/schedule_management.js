/**
 * Schedule Management JavaScript
 * Handles all client-side logic for the schedule management component.
 */

/**
 * Determines the correct API base path based on the current page URL.
 * This ensures that API calls work correctly from any dashboard (admin, moderator, instructor).
 * Works correctly when hosted in subdirectories.
 * @returns {string} The correct relative base path to the management API.
 */
function getApiBasePath() {
  // Try to get base path from a global variable set by PHP (most reliable)
  if (typeof window.API_BASE_PATH !== "undefined" && window.API_BASE_PATH) {
    return window.API_BASE_PATH;
  }

  const path = window.location.pathname;
  const origin = window.location.origin;

  // Calculate base path from current location
  // Find the position of 'views' in the path to determine depth
  const viewsIndex = path.indexOf("/views/");

  if (viewsIndex !== -1) {
    // We're in a views subdirectory, calculate relative path
    // Count how many levels deep we are
    const pathAfterViews = path.substring(viewsIndex + 7); // +7 for '/views/'
    const depth = pathAfterViews.split("/").filter((p) => p).length;

    // Build relative path: go up (depth + 1) levels, then to admin/management
    // depth + 1 because we need to go up from views/ to root, then to admin/management
    let relativePath = "";
    for (let i = 0; i <= depth; i++) {
      relativePath += "../";
    }
    return relativePath + "admin/management/";
  }

  // Fallback: try to detect from pathname patterns
  if (path.includes("/views/admin/")) {
    return "../../admin/management/";
  }
  if (path.includes("/views/moderator/")) {
    return "../../admin/management/";
  }
  if (path.includes("/views/instructor/")) {
    return "../../admin/management/";
  }

  // Last resort: try to use absolute path based on document root
  // Extract base path from current URL
  const pathParts = path.split("/").filter((p) => p);
  const adminIndex = pathParts.indexOf("admin");
  if (adminIndex !== -1) {
    // Build path from root to admin/management
    let basePath = "/";
    for (let i = 0; i < adminIndex; i++) {
      basePath += pathParts[i] + "/";
    }
    return basePath + "admin/management/";
  }

  // Default fallback - use absolute path if we can't determine relative
  // Try to construct absolute path
  const pathToRoot = path.substring(0, path.lastIndexOf("/"));
  const levelsUp = (pathToRoot.match(/\//g) || []).length;
  if (levelsUp > 0) {
    let relativePath = "";
    for (let i = 0; i < levelsUp; i++) {
      relativePath += "../";
    }
    return relativePath + "admin/management/";
  }

  return "../admin/management/";
}

document.addEventListener("DOMContentLoaded", function () {
  // generateCalendarTimeSlots(); // This will now be called inside loadSchedules

  // Load data for filters and modal dropdowns, then load schedules
  loadScheduleFormData().then(() => {
    // Load schedules after form data is loaded
    loadSchedules();
  });

  // Add event listeners to filters
  $(
    "#syFilter, #programFilter, #yearLevelFilter, #sectionFilter, #instructorFilter, #roomFilter, #dayFilter"
  ).on("change", function () {
    loadSchedules();
  });

  // Listen for school year updates
  document.addEventListener('schoolYearUpdated', function() {
    console.log('📅 School year updated event detected, reloading schedules...');
    loadSchedules();
  });

  // Handle modal opening for adding a new schedule
  $("#scheduleModal").on("show.bs.modal", function () {
    // Set up cascading dropdowns when modal opens
    setupCascadingDropdowns();

    // Only reset if it's a new schedule (no schd_id)
    if (!$("#schd_id").val()) {
      $("#scheduleForm")[0].reset();
      $("#schd_id").val("");
      $("#scheduleModalLabel").text("Create Schedule");
      $("#schd_status").closest(".col-md-6").hide(); // Hide status field for new schedules
      
      // Helper function to wait for dropdown options and auto-select first available
      function waitAndSelectFirst(selector, timeout = 5000) {
        return new Promise((resolve) => {
          const startTime = Date.now();
          const checkInterval = setInterval(function() {
            const dropdown = $(selector);
            const firstOption = dropdown.find("option:not([value=''])").first();
            if (firstOption.length > 0) {
              clearInterval(checkInterval);
              dropdown.val(firstOption.val());
              console.log(`✅ Auto-filled ${selector}:`, firstOption.val());
              resolve(firstOption.val());
            } else if (Date.now() - startTime > timeout) {
              clearInterval(checkInterval);
              console.log(`⏱️ Timeout waiting for ${selector}`);
              resolve(null);
            }
          }, 100);
        });
      }
      
      // Helper function to wait for specific option value and select it
      function waitAndSelectValue(selector, value, timeout = 5000) {
        return new Promise((resolve) => {
          const startTime = Date.now();
          const checkInterval = setInterval(function() {
            const dropdown = $(selector);
            if (dropdown.find(`option[value="${value}"]`).length > 0) {
              clearInterval(checkInterval);
              dropdown.val(value);
              console.log(`✅ Auto-filled ${selector}:`, value);
              resolve(value);
            } else if (Date.now() - startTime > timeout) {
              clearInterval(checkInterval);
              console.log(`⏱️ Timeout waiting for ${selector} option value ${value}`);
              resolve(null);
            }
          }, 100);
        });
      }
      
      // Auto-fill sequence: School Year -> Term -> Program -> Year Level -> Section & Subject
      setTimeout(async function () {
        try {
          console.log("🚀 Starting auto-fill sequence...");
          console.log("Current SY:", window.currentActiveSY, "Term:", window.currentActiveTerm, "Program:", window.defaultProgramId);
          
          // Step 1: Auto-select School Year
          if (window.currentActiveSY) {
            const sySelected = await waitAndSelectValue("#sy_id", window.currentActiveSY, 3000);
            if (sySelected) {
              $("#sy_id").trigger("change.cascade");
              // Wait for term dropdown to populate
              await new Promise(resolve => setTimeout(resolve, 600));
            }
          }
          
          // Step 2: Auto-select Term
          if (window.currentActiveTerm !== null && window.currentActiveTerm !== undefined) {
            const termSelected = await waitAndSelectValue("#schd_term", window.currentActiveTerm, 3000);
            if (termSelected) {
              $("#schd_term").trigger("change.cascade");
              // Wait for cascades to complete
              await new Promise(resolve => setTimeout(resolve, 600));
            }
          }
          
          // Step 3: Auto-select Program
          if (window.defaultProgramId) {
            const programDropdown = $("#program_id");
            // Wait for program dropdown to have options
            const programHasOptions = await new Promise((resolve) => {
              const checkInterval = setInterval(() => {
                if (programDropdown.find("option").length > 1) { // More than just the default option
                  clearInterval(checkInterval);
                  resolve(true);
                }
              }, 100);
              setTimeout(() => {
                clearInterval(checkInterval);
                resolve(false);
              }, 3000);
            });
            
            if (programHasOptions && programDropdown.find(`option[value="${window.defaultProgramId}"]`).length > 0) {
              programDropdown.val(window.defaultProgramId).trigger("change.cascade");
              console.log("✅ Auto-filled Program:", window.defaultProgramId);
              // Wait for year level dropdown to populate (this is async via cascade)
              await new Promise(resolve => setTimeout(resolve, 1000));
            }
          }
          
          // Step 4: Auto-select first available Year Level
          const yearLevelValue = await waitAndSelectFirst("#year_level", 5000);
          if (yearLevelValue) {
            // Verify all required fields are set before triggering cascade
            const syId = $("#sy_id").val();
            const term = $("#schd_term").val();
            const programId = $("#program_id").val();
            
            console.log("📋 Prerequisites check - SY:", syId, "Term:", term, "Program:", programId, "Year Level:", yearLevelValue);
            
            if (syId && term && programId && yearLevelValue) {
              // Trigger cascade to load sections and subjects
              $("#year_level").trigger("change.cascade");
              console.log("✅ Year Level selected, triggering cascade for Section and Subject...");
              
              // Also manually trigger term cascade to ensure sections load
              $("#schd_term").trigger("change.cascade");
              
              // Wait longer for section and subject dropdowns to populate (they depend on SY, Term, Program, and Year Level)
              await new Promise(resolve => setTimeout(resolve, 2000));
              
              // Double-check and manually load sections if still empty
              const sectionDropdown = $("#sec_id");
              if (sectionDropdown.find("option:not([value=''])").length === 0) {
                console.log("⚠️ Sections still empty, manually loading...");
                loadFilteredData(
                  "sections",
                  {
                    program_id: programId,
                    year_level: yearLevelValue,
                    sy_id: syId,
                    term: term,
                  },
                  "#sec_id",
                  "sec_id",
                  "sec_name",
                  "Select Section"
                );
                await new Promise(resolve => setTimeout(resolve, 1000));
              }
              
              // Double-check and manually load subjects if still empty
              const subjectDropdown = $("#subj_id");
              if (subjectDropdown.find("option:not([value=''])").length === 0) {
                console.log("⚠️ Subjects still empty, manually loading...");
                loadFilteredData(
                  "subjects",
                  {
                    program_id: programId,
                    year_level: yearLevelValue,
                  },
                  "#subj_id",
                  "subj_id",
                  "subj_display",
                  "Select Subject"
                );
                await new Promise(resolve => setTimeout(resolve, 1000));
              }
            } else {
              console.log("⚠️ Missing prerequisites for Section/Subject cascade:", {
                syId: !!syId,
                term: !!term,
                programId: !!programId,
                yearLevel: !!yearLevelValue
              });
            }
          } else {
            console.log("⚠️ No Year Level options found");
          }
          
          // Step 5: Auto-select first available Section and Subject (can be done in parallel)
          console.log("🔍 Looking for Section and Subject options...");
          const finalSyId = $("#sy_id").val();
          const finalTerm = $("#schd_term").val();
          const finalProgramId = $("#program_id").val();
          const finalYearLevel = $("#year_level").val();
          
          console.log("📋 Final prerequisites - SY:", finalSyId, "Term:", finalTerm, "Program:", finalProgramId, "Year Level:", finalYearLevel);
          console.log("📊 Section options count:", $("#sec_id").find("option").length);
          console.log("📊 Subject options count:", $("#subj_id").find("option").length);
          
          if (finalSyId && finalTerm && finalProgramId && finalYearLevel) {
            await Promise.all([
              waitAndSelectFirst("#sec_id", 5000).then(val => {
                if (val) console.log("✅ Section auto-filled:", val);
                else {
                  console.log("⚠️ No Section options found after waiting");
                  console.log("Available options:", $("#sec_id").find("option").map((i, opt) => $(opt).val() + ": " + $(opt).text()).get());
                }
              }),
              waitAndSelectFirst("#subj_id", 5000).then(val => {
                if (val) console.log("✅ Subject auto-filled:", val);
                else {
                  console.log("⚠️ No Subject options found after waiting");
                  console.log("Available options:", $("#subj_id").find("option").map((i, opt) => $(opt).val() + ": " + $(opt).text()).get());
                }
              })
            ]);
          } else {
            console.log("⚠️ Cannot auto-fill Section/Subject - missing prerequisites");
          }
          
          console.log("✅ Auto-fill sequence completed");
        } catch (error) {
          console.error("❌ Error in auto-fill sequence:", error);
        }
      }, 300);
    }
  });

  // Handle day checkbox changes to trigger recommendations
  $('input[name="schd_day[]"]').on("change", function () {
    requestScheduleRecommendation();
  });

  // Handle form submission
  $("#saveScheduleBtn").on("click", function () {
    saveSchedule();
  });

  // Add Section button handler - permanent binding at page load
  $(document).on("click", "#addSectionBtn", function () {
    console.log("Add Section button clicked");
    const programId = $("#program_id").val();
    const yearLevel = $("#year_level").val();

    console.log("Add Section validation:", { programId, yearLevel });

    if (!programId || !yearLevel) {
      Swal.fire({
        icon: "warning",
        title: "Selection Required",
        text: "Please select a Program and Year Level first.",
        confirmButtonColor: "#800000",
      });
      return;
    }

    console.log(
      "Loading classes for program:",
      programId,
      "year level:",
      yearLevel
    );
    // Note: Add Section modal functionality moved to room_management.php component
    // to avoid conflicts. This code is disabled.
    console.log(
      "Add Section modal functionality is handled by room_management.php component"
    );
  });
});

/**
 * Reloads the schedules DataTable.
 */
function loadSchedules() {
  const selectedSy = $("#syFilter").val() || "";
  const activeSy = window.currentActiveSY || null;
  const activeTerm = window.currentActiveTerm || null;
  
  // If the selected SY matches the active SY, also filter by the active term
  // This ensures schedules are filtered by the active School Year and Semester
  let termFilter = "";
  if (selectedSy && activeSy && selectedSy == activeSy && activeTerm) {
    termFilter = activeTerm;
    console.log("Active SY selected, filtering by active term:", activeTerm);
  }
  
  const filters = {
    sy: selectedSy,
    term: termFilter,
    program: $("#programFilter").val() || "",
    year_level: $("#yearLevelFilter").val() || "",
    section: $("#sectionFilter").val() || "",
    instructor: $("#instructorFilter").val() || "",
    room: $("#roomFilter").val() || "",
    day: $("#dayFilter").val() || "",
  };

  // Remove empty filter values from params to avoid sending empty strings
  const params = new URLSearchParams();
  Object.keys(filters).forEach((key) => {
    if (filters[key]) {
      params.append(key, filters[key]);
    }
  });

  $("#scheduleSpinner").show();
  clearCalendar();

  fetch(`${getApiBasePath()}get_schedules.php?${params.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (data.data && data.data.length > 0) {
          generateCalendarTimeSlots(data.data);
          renderCalendar(data.data);
        } else {
          // If no schedules found, generate a default empty calendar
          generateCalendarTimeSlots();
          // Don't show error for empty results - it's a valid state
        }
      } else {
        // Only show error if there's an actual error
        generateCalendarTimeSlots();
        showToast("error", data.message || "Failed to load schedules.");
      }
    })
    .catch((error) => {
      console.error("Error loading schedules:", error);
      showToast("error", "An error occurred while loading schedules.");
    })
    .finally(() => {
      $("#scheduleSpinner").hide();
    });
}

/**
 * Generates the time slots for the calendar view dynamically based on schedule data.
 * @param {Array} schedules - The array of schedule objects.
 */
function generateCalendarTimeSlots(schedules = []) {
  const timeColumn = document.getElementById("timeColumn");
  const daysWrapper = document.getElementById("daysWrapper");
  timeColumn.innerHTML = ""; // Clear existing time slots
  daysWrapper.innerHTML = ""; // Clear existing day columns

  let minHour = 7;
  let maxHour = 20;

  if (schedules.length > 0) {
    minHour = Math.floor(
      Math.min(...schedules.map((s) => parseInt(s.schd_start.split(":")[0])))
    );
    maxHour = Math.ceil(
      Math.max(...schedules.map((s) => parseInt(s.schd_end.split(":")[0])))
    );
    // Add some buffer and ensure it's within reasonable school hours
    minHour = Math.max(6, minHour - 1);
    maxHour = Math.min(21, maxHour + 1);
  }

  // Generate Time Column
  for (let hour = minHour; hour < maxHour; hour++) {
    const timeSlot = document.createElement("div");
    timeSlot.className = "time-slot";
    timeSlot.textContent = `${hour % 12 === 0 ? 12 : hour % 12}:00 ${
      hour < 12 || hour === 24 ? "AM" : "PM"
    }`;
    timeColumn.appendChild(timeSlot);
  }

  // Generate Day Columns
  const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  days.forEach((day) => {
    // This part was missing from the previous diff, it's crucial.
    const dayColumn = document.createElement("div");
    dayColumn.className = "day-column";
    dayColumn.innerHTML = `<div class="calendar-header">${day}</div>
                               <div class="day-column-content" id="day-content-${day}"></div>`;
    daysWrapper.appendChild(dayColumn);
  });
}

/**
 * Clears all schedule blocks from the calendar.
 */
function clearCalendar() {
  document
    .querySelectorAll(".schedule-block")
    .forEach((block) => block.remove());
}

/**
 * Renders the fetched schedules onto the calendar grid.
 * @param {Array} schedules - The array of schedule objects.
 */
function renderCalendar(schedules) {
  const baseColors = [
    "#800000",
    "#003366",
    "#006400",
    "#4B0082",
    "#556B2F",
    "#8B4513",
    "#2F4F4F",
    "#A0522D",
    "#5F9EA0",
    "#C71585",
    "#4682B4",
    "#D2691E",
  ];
  const instructorColors = {};
  let colorIndex = 0;

  // Helper function to get a color for an instructor
  function getColorForInstructor(instId) {
    if (!instructorColors[instId]) {
      instructorColors[instId] = baseColors[colorIndex % baseColors.length];
      colorIndex++;
    }
    return instructorColors[instId];
  }

  let minHour = 7;
  if (schedules.length > 0) {
    minHour = Math.floor(
      Math.min(...schedules.map((s) => parseInt(s.schd_start.split(":")[0])))
    );
    minHour = Math.max(6, minHour - 1);
  }

  schedules.forEach((schedule) => {
    const start = schedule.schd_start.split(":");
    const end = schedule.schd_end.split(":");
    const startHour = parseInt(start[0]);
    const startMinute = parseInt(start[1]);

    // Calculate total minutes from the dynamic start of the calendar day
    const totalStartMinutes = (startHour - minHour) * 60 + startMinute;

    // Calculate duration
    const startTime = new Date(2000, 0, 1, start[0], start[1]);
    const endTime = new Date(2000, 0, 1, end[0], end[1]);
    const durationMinutes = (endTime - startTime) / 60000;

    // Each minute corresponds to 1px (since an hour slot is 60px)
    const topPosition = totalStartMinutes;
    const blockHeight = durationMinutes;

    const dayContent = document.getElementById(
      `day-content-${schedule.schd_day}`
    );

    if (dayContent) {
      const block = document.createElement("div");
      block.className = "schedule-block";
      block.style.top = `${topPosition}px`;
      block.style.height = `${blockHeight}px`;
      block.style.backgroundColor = getColorForInstructor(schedule.inst_id);

      // Add overtime class and content if applicable
      let overtimeBadge = "";
      if (schedule.is_overtime === "Yes") {
        block.classList.add("overtime-block");
        overtimeBadge = '<span class="badge bg-danger mb-1">Overtime</span>';
      }

      block.innerHTML = `
                <div class="schedule-block-content">
                    ${overtimeBadge}
                    <div class="subj-code">${schedule.subj_code} (${schedule.sec_name})</div>
                    <div class="instructor-name">${schedule.instructor_name}</div>
                    <div class="location-details">${schedule.rm_name}</div>
                    <div class="time-details">${schedule.start_time} - ${schedule.end_time}</div>
                </div>
            `;
      block.title = `${schedule.subj_desc}\n${schedule.instructor_name}\n${schedule.start_time} - ${schedule.end_time}`;
      block.onclick = () => editSchedule(schedule.schd_id);

      dayContent.appendChild(block);
    }
  });
}

/**
 * Loads necessary data for form dropdowns (school years, instructors, rooms, etc.).
 * Returns a Promise that resolves when data is loaded.
 */
function loadScheduleFormData() {
  return fetch(`${getApiBasePath()}get_schedule_form_data.php`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Populate filter dropdowns
        populateDropdown(
          "#syFilter",
          data.school_years,
          "sy_id",
          "sy_name",
          "All School Years"
        );

        // Set current school year and term as default if available
        // This ensures schedules are filtered by the active school year/semester by default
        // Use setTimeout to ensure dropdown is fully populated before setting value
        if (data.current_sy_id) {
          console.log(
            "Attempting to set current school year as default:",
            data.current_sy_id,
            "Current term:",
            data.current_term
          );
          console.log(
            "Available school years:",
            data.school_years.map((sy) => ({ id: sy.sy_id, name: sy.sy_name }))
          );

          // Set value after a small delay to ensure DOM is ready
          setTimeout(() => {
            const syFilter = $("#syFilter");
            const termFilter = $("#termFilter");
            const currentSyValue = syFilter.val();
            const currentTermValue = termFilter.val();

            console.log(
              "Current filter values before setting - SY:",
              currentSyValue,
              "Term:",
              currentTermValue
            );

            // Track if we actually changed any values
            let filterChanged = false;

            // Only set filters if they are empty (user hasn't explicitly set them)
            // This ensures the UI reflects what filters are being used
            if (!currentSyValue && syFilter.length) {
              syFilter.val(data.current_sy_id);
              console.log("✅ Set school year filter to:", data.current_sy_id);
              filterChanged = true;
            }

            // Set term filter if available and not already set
            if (
              data.current_term !== null &&
              data.current_term !== undefined &&
              !currentTermValue &&
              termFilter.length
            ) {
              termFilter.val(data.current_term);
              console.log("✅ Set term filter to:", data.current_term);
              filterChanged = true;
            }

            // Verify the school year was set
            const newSyValue = syFilter.val();
            const newTermValue = termFilter.val();
            console.log(
              "Filter values after setting - SY:",
              newSyValue,
              "Term:",
              newTermValue
            );

            if (newSyValue == data.current_sy_id || data.current_sy_id) {
              console.log(
                "✅ Successfully set current school year and term as default"
              );
              // Note: We don't trigger change event here because loadSchedules()
              // is already called after loadScheduleFormData() completes.
              // The backend will automatically use active SY/semester if filters are empty,
              // but setting the filter values here ensures UI consistency.
            } else {
              console.warn(
                "⚠️ Failed to set school year. Expected:",
                data.current_sy_id,
                "Got:",
                newSyValue
              );
              // Try to find the option and set it manually
              const optionExists =
                syFilter.find(`option[value="${data.current_sy_id}"]`).length >
                0;
              console.log("Option exists in dropdown:", optionExists);
              if (!optionExists) {
                console.error(
                  "❌ School year ID not found in dropdown options"
                );
              }
            }
          }, 100);
        } else {
          console.log("No current_sy_id found in response data");
        }
        // Format: "Program Code - Program Name" (same as modal dropdown)
        populateDropdown(
          "#programFilter",
          data.programs,
          "program_id",
          "program_display",
          "All Programs"
        );
        populateDropdown(
          "#sectionFilter",
          data.sections,
          "sec_id",
          "sec_name",
          "All Sections"
        );
        populateDropdown(
          "#instructorFilter",
          data.instructors,
          "inst_id",
          "name",
          "All Instructors"
        );
        populateDropdown(
          "#roomFilter",
          data.rooms,
          "rm_id",
          "rm_name_with_building",
          "All Rooms"
        );

        // Populate modal dropdowns
        populateDropdown(
          "#sy_id",
          data.school_years,
          "sy_id",
          "sy_name",
          "Select School Year"
        );

        // Populate Course/Program dropdown - only shows programs from admin's department
        // Format: "Program Code - Program Name" (similar to Subject dropdown)
        console.log(
          "Loading programs for department:",
          data.user_dept_name,
          "Total programs:",
          data.programs.length
        );
        populateDropdown(
          "#program_id",
          data.programs,
          "program_id",
          "program_display",
          "Select Program"
        );

        populateDropdown(
          "#subj_id",
          data.subjects,
          "subj_id",
          "subj_desc",
          "Select Subject"
        );
        populateDropdown(
          "#sec_id",
          data.sections,
          "sec_id",
          "sec_name",
          "Select Section"
        );
        populateDropdown(
          "#inst_id",
          data.instructors,
          "inst_id",
          "name",
          "Select Instructor"
        );
        populateDropdown(
          "#rm_id",
          data.rooms,
          "rm_id",
          "rm_name_with_building",
          "Select Room"
        );

        // Store default program ID for auto-selection when creating new schedule
        window.defaultProgramId = data.default_program_id || null;
        console.log("Default program ID:", window.defaultProgramId);
        
        // Store current school year and term for auto-fill in create modal
        window.currentActiveSY = data.current_sy_id || null;
        window.currentActiveTerm = data.current_term || null;
        console.log("Current Active SY:", window.currentActiveSY, "Term:", window.currentActiveTerm);

        // Auto-select default program if modal is already open (for initial load)
        if (window.defaultProgramId && !$("#schd_id").val()) {
          $("#program_id").val(window.defaultProgramId);
        }

        return data;
      } else {
        showToast("error", data.message || "Failed to load form data.");
        throw new Error(data.message || "Failed to load form data.");
      }
    })
    .catch((error) => {
      console.error("Error loading schedule form data:", error);
      showToast("error", "An error occurred while loading form data.");
      throw error;
    });
}

/**
 * Updates the schedule term filter dropdown based on the active school year and semester
 */
function updateScheduleTermFilter() {
  const termFilter = $('#termFilter');
  if (!termFilter.length) {
    console.warn('⚠️ Term filter not found');
    return;
  }
  
  // Get API base path
  let apiBasePath = '../../shared/management/';
  const path = window.location.pathname;
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
        let termValue = null;
        
        // Convert semester format to term ID: "1st Semester" -> 1, "2nd Semester" -> 2, "Mid-Year" -> 3 (Summer)
        if (semester === '1st Semester') {
          termValue = '1';
        } else if (semester === '2nd Semester') {
          termValue = '2';
        } else if (semester === 'Mid-Year') {
          termValue = '3'; // Mid/Summer
        }
        
        if (termValue && termFilter.find(`option[value="${termValue}"]`).length > 0) {
          // Update the dropdown value
          termFilter.val(termValue);
          console.log(`✅ Term filter updated to: ${termFilter.find('option:selected').text()} (value: ${termValue})`);
          
          // Trigger change event to reload schedules
          termFilter.trigger('change');
        } else {
          console.warn('⚠️ Unknown semester format or option not found:', semester, 'Term value:', termValue);
        }
      } else {
        console.warn('⚠️ No active semester found or invalid response:', data);
      }
    })
    .catch(error => {
      console.error('❌ Error updating schedule term filter:', error);
    });
}

/**
 * Helper function to populate a select dropdown.
 */
function populateDropdown(
  selector,
  items,
  valueField,
  textField,
  defaultOptionText
) {
  const dropdown = $(selector);
  dropdown.empty();
  dropdown.append(`<option value="">${defaultOptionText}</option>`);
  items.forEach((item) => {
    // Use textField if it exists, otherwise fallback to valueField
    const displayText = item[textField] || item[valueField] || "";
    dropdown.append(
      `<option value="${item[valueField]}">${displayText}</option>`
    );
  });
}

/**
 * Opens the modal to edit a schedule.
 * @param {number} schd_id The ID of the schedule to edit.
 */
function editSchedule(schd_id) {
  console.log(
    "editSchedule called with ID:",
    schd_id,
    "API path:",
    getApiBasePath()
  );
  fetch(`${getApiBasePath()}get_schedule.php?id=${schd_id}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        const schedule = data.schedule;
        $("#scheduleModalLabel").text("Edit Schedule");
        $("#schd_id").val(schedule.schd_id);

        // Ensure form data is loaded first (for cascading dropdowns)
        loadScheduleFormData()
          .then(() => {
            // Set up cascading dropdowns
            setupCascadingDropdowns();

            // Set school year first, then trigger cascade
            $("#sy_id").val(schedule.sy_id);
            $("#sy_id").trigger("change.cascade");

            // Wait for terms to load, then set term
            setTimeout(() => {
              $("#schd_term").val(schedule.schd_term);
              $("#schd_term").trigger("change.cascade");

              // Wait for term to process, then set program
              setTimeout(() => {
                if (schedule.program_id) {
                  $("#program_id").val(schedule.program_id);
                  $("#program_id").trigger("change.cascade");

                  // Wait for program to process, then set year level and instructor
                  setTimeout(() => {
                    // Set instructor AFTER program cascade completes
                    if (schedule.inst_id) {
                      $("#inst_id").val(schedule.inst_id);
                    }

                    if (schedule.year_level) {
                      $("#year_level").val(schedule.year_level);
                      $("#year_level").trigger("change.cascade");

                      // Wait for year level to process, then set subject and section
                      setTimeout(() => {
                        $("#subj_id").val(schedule.subj_id);
                        $("#sec_id").val(schedule.sec_id);
                      }, 300);
                    } else {
                      // No year level, set subject and section directly
                      $("#subj_id").val(schedule.subj_id);
                      $("#sec_id").val(schedule.sec_id);
                    }
                  }, 400);
                }
              }, 300);
            }, 300);
          })
          .catch((error) => {
            console.error("Error loading form data for edit:", error);
            // Fallback: set values directly without cascading
            $("#sy_id").val(schedule.sy_id);
            $("#schd_term").val(schedule.schd_term);
            $("#program_id").val(schedule.program_id || "");
            $("#year_level").val(schedule.year_level || "");
            $("#subj_id").val(schedule.subj_id);
            $("#sec_id").val(schedule.sec_id);
            $("#inst_id").val(schedule.inst_id);
          });

        // Set other fields that don't cascade (set these immediately)
        $("#rm_id").val(schedule.rm_id);
        $("#schd_type").val(schedule.schd_type);
        // Uncheck all day checkboxes first
        $('input[name="schd_day[]"]').prop("checked", false);
        // Check the day checkbox for the schedule's day
        $(`input[name="schd_day[]"][value="${schedule.schd_day}"]`).prop(
          "checked",
          true
        );
        $("#schd_start").val(schedule.schd_start);
        $("#schd_end").val(schedule.schd_end);
        $("#schd_status").val(schedule.schd_status).closest(".col-md-6").show();

        // Show Remove button and change Save button text for editing
        const removeBtn = $("#removeScheduleBtn");
        if (removeBtn.length) {
          removeBtn.show().attr("data-schd-id", schedule.schd_id);
        }
        $("#saveScheduleBtn").text("Update Schedule");

        var scheduleModal = new bootstrap.Modal(
          document.getElementById("scheduleModal")
        );
        scheduleModal.show();
      } else {
        showToast("error", data.message || "Failed to fetch schedule details.");
      }
    })
    .catch((error) => {
      console.error("Error fetching schedule:", error);
      console.error("API path used:", getApiBasePath());
      showToast(
        "error",
        "An error occurred while fetching schedule details: " + error.message
      );
    });
}

/**
 * Saves a new or edited schedule.
 */
function saveSchedule() {
  const form = document.getElementById("scheduleForm");
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  // Validate that at least one day is selected
  const selectedDays = $('input[name="schd_day[]"]:checked');
  if (selectedDays.length === 0) {
    Swal.fire({
      icon: "error",
      title: "Validation Error",
      text: "Please select at least one day.",
      confirmButtonColor: "#800000",
    });
    return;
  }

  const formData = new FormData(form);
  const schd_id = $("#schd_id").val();
  const url = schd_id
    ? `${getApiBasePath()}update_schedule.php`
    : `${getApiBasePath()}add_schedule.php`;

  const saveButton = $("#saveScheduleBtn");
  saveButton
    .prop("disabled", true)
    .html('<span class="spinner-border spinner-border-sm"></span> Saving...');

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast("success", data.message);
        bootstrap.Modal.getInstance(
          document.getElementById("scheduleModal")
        )?.hide();
        loadSchedules();
      } else {
        Swal.fire({
          icon: "error",
          title: "Save Failed",
          text: data.message || "An unknown error occurred.",
          confirmButtonColor: "#800000",
        });
      }
    })
    .catch((error) => {
      console.error("Error saving schedule:", error);
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "An error occurred while saving the schedule.",
        confirmButtonColor: "#800000",
      });
    })
    .finally(() => {
      saveButton.prop("disabled", false).html("Save Schedule");
    });
}

/**
 * Loads filtered data and populates a dropdown
 */
function loadFilteredData(
  type,
  filters,
  selector,
  valueField,
  textField,
  defaultOptionText
) {
  const params = new URLSearchParams();
  params.append("type", type);

  Object.keys(filters).forEach((key) => {
    if (filters[key]) {
      params.append(key, filters[key]);
    }
  });

  console.log("loadFilteredData called:", {
    type,
    filters,
    selector,
    apiPath: getApiBasePath(),
  });

  return fetch(`${getApiBasePath()}get_filtered_data.php?${params.toString()}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success && data.data) {
        populateDropdown(
          selector,
          data.data,
          valueField,
          textField,
          defaultOptionText
        );
      } else {
        console.error("Failed to load filtered data:", data.message);
        $(selector)
          .empty()
          .append(`<option value="">${defaultOptionText}</option>`);
      }
      return data;
    })
    .catch((error) => {
      console.error("Error loading filtered data:", error);
      console.error("API path used:", getApiBasePath());
      $(selector)
        .empty()
        .append(`<option value="">${defaultOptionText}</option>`);
      throw error;
    });
}

/**
 * Sets up cascading dropdown filters for the schedule modal
 */
function setupCascadingDropdowns() {
  // Remove existing handlers to avoid duplicates
  $("#sy_id").off("change.cascade");
  $("#program_id").off("change.cascade");
  $("#year_level").off("change.cascade");
  $("#schd_term").off("change.cascade");
  $("#subj_id").off("change.cascade");
  $("#inst_id").off("change.cascade");

  // School Year -> Term
  $("#sy_id").on("change.cascade", function () {
    const syId = $(this).val();
    if (syId) {
      loadFilteredData(
        "terms",
        { sy_id: syId },
        "#schd_term",
        "term_value",
        "term_name",
        "Select Term"
      );
    } else {
      $("#schd_term").empty().append('<option value="">Select Term</option>');
    }
    // Clear dependent dropdowns
    $("#program_id").val("").trigger("change.cascade");
  });

  // Program -> Year Level, Section, Instructor
  $("#program_id").on("change.cascade", function () {
    const programId = $(this).val();
    const currentInstId = $("#inst_id").val(); // Preserve current selection if from search
    if (programId) {
      // Load year levels
      loadFilteredData(
        "year_levels",
        { program_id: programId },
        "#year_level",
        "year_level",
        "year_level_name",
        "Select Year Level"
      );
      // Load instructors filtered by program's department
      loadFilteredData(
        "instructors",
        { program_id: programId },
        "#inst_id",
        "inst_id",
        "name",
        "Select Instructor"
      ).then(() => {
        // Restore selection if it was from search (exists in dropdown)
        if (currentInstId && $("#inst_id").find(`option[value="${currentInstId}"]`).length > 0) {
          $("#inst_id").val(currentInstId);
        }
      });
    } else {
      $("#year_level")
        .empty()
        .append('<option value="">Select Year Level</option>');
      $("#subj_id").empty().append('<option value="">Select Subject</option>');
      $("#sec_id").empty().append('<option value="">Select Section</option>');
      // Only clear instructor dropdown if it wasn't selected via search
      if (!$("#instructorSearch").val()) {
        $("#inst_id")
          .empty()
          .append('<option value="">Select Instructor</option>');
      }
    }
    // Clear dependent dropdowns
    $("#year_level").val("").trigger("change.cascade");
    // Don't clear instructor if it was selected via search
    if (!$("#instructorSearch").val() && !currentInstId) {
      $("#inst_id").val("");
    }
  });

  // Year Level -> Subject, Section
  $("#year_level").on("change.cascade", function () {
    const programId = $("#program_id").val();
    const yearLevel = $(this).val();
    const syId = $("#sy_id").val();
    const term = $("#schd_term").val();
    const instId = $("#inst_id").val(); // Get current instructor if selected

    if (programId && yearLevel) {
      // Load subjects filtered by program, year level, and exclude already assigned subjects
      const subjectFilters = {
        program_id: programId,
        year_level: yearLevel,
      };
      // Include instructor/SY/term filters if ALL are available to exclude already assigned subjects
      if (instId && syId && term) {
        subjectFilters.inst_id = instId;
        subjectFilters.sy_id = syId;
        subjectFilters.term = term;
      }
      loadFilteredData(
        "subjects",
        subjectFilters,
        "#subj_id",
        "subj_id",
        "subj_display",
        "Select Subject"
      );

      // Load sections if school year and term are also selected
      if (syId && term) {
        loadFilteredData(
          "sections",
          {
            program_id: programId,
            year_level: yearLevel,
            sy_id: syId,
            term: term,
          },
          "#sec_id",
          "sec_id",
          "sec_name",
          "Select Section"
        );
      } else {
        $("#sec_id").empty().append('<option value="">Select Section</option>');
      }
    } else if (programId) {
      // Only program selected, load subjects without year level filter
      // But still try to filter by instructor if available
      const subjectFilters = { program_id: programId };
      const instId = $("#inst_id").val();
      const syId = $("#sy_id").val();
      const term = $("#schd_term").val();
      if (instId && syId && term) {
        subjectFilters.inst_id = instId;
        subjectFilters.sy_id = syId;
        subjectFilters.term = term;
      }
      loadFilteredData(
        "subjects",
        subjectFilters,
        "#subj_id",
        "subj_id",
        "subj_display",
        "Select Subject"
      );
      $("#sec_id").empty().append('<option value="">Select Section</option>');
    } else {
      $("#subj_id").empty().append('<option value="">Select Subject</option>');
      $("#sec_id").empty().append('<option value="">Select Section</option>');
    }
  });

  // Term + School Year + Program + Year Level -> Section
  $("#schd_term").on("change.cascade", function () {
    const syId = $("#sy_id").val();
    const programId = $("#program_id").val();
    const yearLevel = $("#year_level").val();
    const term = $(this).val();

    if (syId && term && programId && yearLevel) {
      loadFilteredData(
        "sections",
        {
          program_id: programId,
          year_level: yearLevel,
          sy_id: syId,
          term: term,
        },
        "#sec_id",
        "sec_id",
        "sec_name",
        "Select Section"
      );
    } else {
      $("#sec_id").empty().append('<option value="">Select Section</option>');
    }

    // Try to recommend a schedule when term changes
    requestScheduleRecommendation();
  });

  // When subject changes, auto-fill lecture type if subject has no lab, and try to recommend schedule
  $("#subj_id").on("change.cascade", function () {
    const subjId = $(this).val();
    if (subjId) {
      // Get subject data to check if it has lab hours
      const selectedOption = $("#subj_id").find("option:selected");
      // We need to fetch subject details to get lab info
      // Call the filtered data to get the full subject info with lab field
      const programId = $("#program_id").val();
      const yearLevel = $("#year_level").val();

      if (programId && yearLevel) {
        const syId = $("#sy_id").val();
        const term = $("#schd_term").val();
        const instId = $("#inst_id").val();
        const subjectFilters = {
          program_id: programId,
          year_level: yearLevel,
        };
        if (instId && syId && term) {
          subjectFilters.inst_id = instId;
          subjectFilters.sy_id = syId;
          subjectFilters.term = term;
        }

        // Fetch all subjects to find the one selected
        fetch(
          `${getApiBasePath()}get_filtered_data.php?type=subjects&` +
            new URLSearchParams(subjectFilters).toString()
        )
          .then((response) => response.json())
          .then((data) => {
            if (data.success && data.data) {
              // Find the selected subject in the data
              const subject = data.data.find(
                (s) => parseInt(s.subj_id) === parseInt(subjId)
              );
              if (subject) {
                // Check if subject has no lab (0 or null lab hours)
                const hasLab =
                  subject.subj_lab && parseInt(subject.subj_lab) > 0;
                if (!hasLab) {
                  // Auto-fill type as Lecture
                  $("#schd_type").val("Lec");
                }
              }
            }
          })
          .catch((error) =>
            console.error("Error fetching subject details:", error)
          );
      }
    }
    requestScheduleRecommendation();
  });

  // When instructor changes, reload subjects with filtering
  $("#inst_id").on("change.cascade", function () {
    const programId = $("#program_id").val();
    const yearLevel = $("#year_level").val();
    const syId = $("#sy_id").val();
    const term = $("#schd_term").val();
    const instId = $(this).val();

    // Reload subjects to exclude ones already assigned to this instructor
    // Do this WHENEVER instructor changes, with whatever filters are available
    if (programId) {
      const subjectFilters = {
        program_id: programId,
      };

      // Include yearLevel if selected
      if (yearLevel) {
        subjectFilters.year_level = yearLevel;
      }

      // Include instructor for filtering
      if (instId) {
        subjectFilters.inst_id = instId;
      }

      // Include SY and term for more specific filtering
      if (syId) {
        subjectFilters.sy_id = syId;
      }
      if (term) {
        subjectFilters.term = term;
      }

      loadFilteredData(
        "subjects",
        subjectFilters,
        "#subj_id",
        "subj_id",
        "subj_display",
        "Select Subject"
      );
    }

    requestScheduleRecommendation();
  });

  // When term changes, also reload subjects to apply correct filtering
  $("#schd_term").on("change.cascade", function () {
    const programId = $("#program_id").val();
    const yearLevel = $("#year_level").val();
    const syId = $("#sy_id").val();
    const instId = $("#inst_id").val();

    // Reload subjects to exclude ones already assigned to this instructor in this SY/term
    if (programId && syId) {
      const subjectFilters = {
        program_id: programId,
      };

      if (yearLevel) {
        subjectFilters.year_level = yearLevel;
      }

      if (instId) {
        subjectFilters.inst_id = instId;
        subjectFilters.sy_id = syId;
        subjectFilters.term = $(this).val();
      }

      loadFilteredData(
        "subjects",
        subjectFilters,
        "#subj_id",
        "subj_id",
        "subj_display",
        "Select Subject"
      );
    }

    requestScheduleRecommendation();
  });

  // When room changes, also trigger schedule recommendation AND auto-fill availability
  $("#rm_id")
    .off("change.cascade change")
    .on("change.cascade change", function () {
      const rmId = $(this).val();
      const syId = $("#sy_id").val();
      const term = $("#schd_term").val();

      console.log("Room change detected:", { rmId, syId, term });

      // If room changed and we previously auto-filled, clear the day/time fields
      // This allows new recommendations for the new room
      if (window.autoFilledDayTime) {
        console.log("Clearing previously auto-filled day/time for new room recommendation");
        $('input[name="schd_day[]"]').prop("checked", false);
        $("#schd_start").val("");
        $("#schd_end").val("");
        window.autoFilledDayTime = false;
      }

      // Auto-fill room availability if all required fields are available
      // This should happen BEFORE schedule recommendation to provide better suggestions
      if (rmId && syId && term) {
        console.log("All required fields present, attempting auto-fill");
        // Auto-fill availability when room is selected
        // Pass true to indicate this is from room change (allow override)
        autoFillRoomAvailability(rmId, syId, term, true);
      } else {
        console.log("Missing required fields for auto-fill:", {
          rmId,
          syId,
          term,
        });
      }

      // Trigger recommendation (may use the auto-filled values)
      requestScheduleRecommendation();
    });

  // Clear auto-fill flag when user manually changes day or time
  $('input[name="schd_day[]"]').on("change", function() {
    // If user manually changes day, clear the auto-fill flag
    if (!window.autoFillingInProgress) {
      window.autoFilledDayTime = false;
      console.log("User manually changed day, clearing auto-fill flag");
    }
  });

  $("#schd_start, #schd_end").on("change input", function() {
    // If user manually changes time, clear the auto-fill flag
    if (!window.autoFillingInProgress) {
      window.autoFilledDayTime = false;
      console.log("User manually changed time, clearing auto-fill flag");
    }
  });

  // Also trigger auto-fill when school year or term changes (if room is already selected)
  $("#sy_id, #schd_term").on("change.cascade", function() {
    const rmId = $("#rm_id").val();
    const syId = $("#sy_id").val();
    const term = $("#schd_term").val();

    // Only auto-fill if room is selected and day/time are not yet filled
    if (rmId && syId && term) {
      const selectedDays = $('input[name="schd_day[]"]:checked').length;
      const currentStart = $("#schd_start").val();
      const currentEnd = $("#schd_end").val();

      // Only auto-fill if user hasn't filled in day/time yet
      if (selectedDays === 0 && (!currentStart || !currentEnd)) {
        console.log("SY/Term changed, checking room availability for auto-fill");
        // Small delay to ensure dropdowns are updated
        setTimeout(() => {
          autoFillRoomAvailability(rmId, syId, term);
        }, 200);
      }
    }
  });
  
  // Instructor search functionality for cross-department assignment
  setupInstructorSearch();
}

/**
 * Sets up instructor search functionality for cross-department assignment
 */
function setupInstructorSearch() {
  const searchInput = $("#instructorSearch");
  const searchResults = $("#instructorSearchResults");
  const clearBtn = $("#clearInstructorSearch");
  const instDropdown = $("#inst_id");
  let searchTimeout = null;
  
  // Debounced search function
  searchInput.on("input", function() {
    const searchTerm = $(this).val().trim();
    
    // Clear previous timeout
    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }
    
    // Hide results if search is empty
    if (searchTerm.length < 2) {
      searchResults.hide().empty();
      return;
    }
    
    // Debounce search (wait 300ms after user stops typing)
    searchTimeout = setTimeout(() => {
      searchInstructors(searchTerm);
    }, 300);
  });
  
  // Clear search button
  clearBtn.on("click", function() {
    searchInput.val("");
    searchResults.hide().empty();
    clearBtn.hide();
  });
  
  // Hide results when clicking outside
  $(document).on("click", function(e) {
    if (!$(e.target).closest("#instructorSearch, #instructorSearchResults, #clearInstructorSearch").length) {
      searchResults.hide();
    }
  });
  
  // Clear search when dropdown changes
  instDropdown.on("change", function() {
    if ($(this).val()) {
      searchInput.val("");
      searchResults.hide().empty();
      clearBtn.hide();
    }
  });
  
  // Show clear button when there's text
  searchInput.on("input", function() {
    if ($(this).val().trim().length > 0) {
      clearBtn.show();
    } else {
      clearBtn.hide();
    }
  });
  
  // Initially hide clear button
  clearBtn.hide();
}

/**
 * Searches for instructors across all departments
 */
function searchInstructors(searchTerm) {
  const searchResults = $("#instructorSearchResults");
  const instDropdown = $("#inst_id");
  
  if (searchTerm.length < 2) {
    searchResults.hide().empty();
    return;
  }
  
  // Show loading state
  searchResults.html('<div class="list-group-item text-center text-muted"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>').show();
  
  fetch(`${getApiBasePath()}search_instructors.php?search=${encodeURIComponent(searchTerm)}`)
    .then(response => response.json())
    .then(data => {
      searchResults.empty();
      
      if (data.success && data.instructors && data.instructors.length > 0) {
        // Display search results
        data.instructors.forEach(instructor => {
          const item = $(`
            <a href="#" class="list-group-item list-group-item-action instructor-search-item" 
               data-inst-id="${instructor.inst_id}" 
               data-inst-name="${instructor.name}">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">${instructor.name}</h6>
                <small class="text-muted">${instructor.dept_name}</small>
              </div>
            </a>
          `);
          
          // Handle click on search result
          item.on("click", function(e) {
            e.preventDefault();
            const instId = $(this).data("inst-id");
            const instName = $(this).data("inst-name");
            
            // Set dropdown value
            // First, check if instructor is already in dropdown
            if (instDropdown.find(`option[value="${instId}"]`).length === 0) {
              // Add option if not exists
              instDropdown.append(`<option value="${instId}">${instName} (Other Dept)</option>`);
            }
            
            // Select the instructor
            instDropdown.val(instId).trigger("change");
            
            // Clear search
            $("#instructorSearch").val("");
            searchResults.hide().empty();
            $("#clearInstructorSearch").hide();
            
            // Show success message
            showToast("success", `Selected instructor: ${instName}`);
          });
          
          searchResults.append(item);
        });
        
        searchResults.show();
      } else {
        searchResults.html('<div class="list-group-item text-center text-muted">No instructors found</div>').show();
      }
    })
    .catch(error => {
      console.error("Error searching instructors:", error);
      searchResults.html('<div class="list-group-item text-center text-danger">Error searching instructors</div>').show();
    });
}

/**
 * Auto-fills day and time based on room availability.
 * @param {number} rmId - Room ID
 * @param {number} syId - School Year ID
 * @param {number} term - Term
 * @param {boolean} forceOverride - If true, allows overriding existing values (for room changes)
 */
function autoFillRoomAvailability(rmId, syId, term, forceOverride = false) {
  console.log("autoFillRoomAvailability called with:", { rmId, syId, term, forceOverride });

  if (!rmId || !syId || !term) {
    console.log("Missing parameters for room availability check");
    return;
  }

  // Check if user has already filled in day, start time, or end time
  const selectedDays = $('input[name="schd_day[]"]:checked').length;
  const currentStart = $("#schd_start").val();
  const currentEnd = $("#schd_end").val();

  // If forceOverride is false and user has already selected day or times, don't override
  // This prevents overriding manual user input, but allows re-filling when room changes
  if (!forceOverride && (selectedDays > 0 || (currentStart && currentEnd))) {
    console.log("User has already filled in day/time and forceOverride is false, skipping auto-fill");
    return;
  }

  // Get schedule ID if editing (to exclude current schedule from overlap checks)
  const schdId = $("#schd_id").val();
  let apiUrl = `${getApiBasePath()}get_room_availability.php?rm_id=${rmId}&sy_id=${syId}&term=${term}`;
  if (schdId) {
    apiUrl += `&schd_id=${schdId}`;
  }

  fetch(apiUrl)
    .then((response) => {
      console.log("Room availability API response status:", response.status);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Room availability response data:", data);

      if (data.success && data.firstAvailable) {
        const slot = data.firstAvailable;
        console.log("Best available slot found:", slot);

        // Set flag to prevent clearing auto-fill flag during auto-fill
        window.autoFillingInProgress = true;

        // Clear all day checkboxes first (in case of room change)
        $('input[name="schd_day[]"]').prop("checked", false);

        // Auto-select the recommended day
        const dayValue = slot.day; // e.g., "Mon", "Tue", etc.
        const dayCheckbox = $(`input[name="schd_day[]"][value="${dayValue}"]`);

        console.log(`Searching for day checkbox with value "${dayValue}"`, {
          found: dayCheckbox.length,
          selector: `input[name="schd_day[]"][value="${dayValue}"]`,
        });

        if (dayCheckbox.length > 0) {
          dayCheckbox.prop("checked", true);
          // Trigger change after a small delay to avoid flag clearing
          setTimeout(() => {
            dayCheckbox.trigger("change");
            window.autoFillingInProgress = false;
          }, 50);
          console.log(`Day "${dayValue}" checkbox checked and triggered`);
        } else {
          console.warn(`Day checkbox for "${dayValue}" not found in DOM`);
          window.autoFillingInProgress = false;
        }

        // Auto-fill start and end times
        // Format times correctly - should be HH:mm format for HTML5 time input
        $("#schd_start").val(slot.startTime);
        $("#schd_end").val(slot.endTime);

        // Trigger change events after a small delay
        setTimeout(() => {
          $("#schd_start").trigger("change");
          $("#schd_end").trigger("change");
          window.autoFillingInProgress = false;
        }, 50);

        // Mark that we auto-filled these values
        window.autoFilledDayTime = true;

        console.log("Auto-filled times:", {
          day: slot.day,
          start: slot.startTime,
          end: slot.endTime,
          duration: slot.duration || "N/A",
          startFieldValue: $("#schd_start").val(),
          endFieldValue: $("#schd_end").val(),
        });

        // Verify the duration is correct (should be 90 minutes = 1 hour 30 minutes)
        const startTime = new Date(`2000-01-01T${slot.startTime}:00`);
        const endTime = new Date(`2000-01-01T${slot.endTime}:00`);
        const actualDurationMinutes = (endTime - startTime) / 60000;
        console.log("Duration verification:", {
          expected: slot.duration || 90,
          actual: actualDurationMinutes,
          start: slot.startTime,
          end: slot.endTime
        });

        // Show success message with recommendation details
        const duration = slot.duration ? ` (${Math.round(slot.duration)} min)` : "";
        showToast(
          "success",
          `Recommended: ${slot.day} from ${slot.startTime} to ${slot.endTime}${duration}`
        );

        // If there are multiple recommendations, log them for debugging
        if (data.topRecommendations && data.topRecommendations.length > 1) {
          console.log("Top recommendations:", data.topRecommendations);
        }
      } else {
        console.log(
          "No available slots found or API returned error:",
          data.message || "Unknown error"
        );
        showToast(
          "warning",
          data.message || "No available time slots for this room in the selected school year and term."
        );
      }
    })
    .catch((error) => {
      console.error("Error getting room availability:", error);
      showToast("error", "Error fetching room availability");
    });
}

/**
 * Requests a recommended schedule (day/time) for the selected instructor & subject
 * and auto-fills the start/end time and day fields if available.
 * Also considers selected days and room availability when applicable.
 */
function requestScheduleRecommendation() {
  const syId = $("#sy_id").val();
  const term = $("#schd_term").val();
  const subjId = $("#subj_id").val();
  const instId = $("#inst_id").val();
  const rmId = $("#rm_id").val();
  let schdType = $("#schd_type").val() || "Lec";

  // Only recommend if core required fields are selected
  if (!syId || !term || !subjId || !instId) {
    return;
  }

  // Do not override if user already typed both start and end time
  const currentStart = $("#schd_start").val();
  const currentEnd = $("#schd_end").val();
  if (currentStart && currentEnd) {
    return;
  }

  // Get selected days
  const selectedDays = $('input[name="schd_day[]"]:checked')
    .map(function () {
      return $(this).val();
    })
    .get();

  // Build parameters for recommendation
  const params = new URLSearchParams({
    sy_id: syId,
    schd_term: term,
    subj_id: subjId,
    inst_id: instId,
    schd_type: schdType,
  });

  // Add selected days if any are selected (recommendation will check these days first)
  if (selectedDays.length > 0) {
    params.append("selected_days", selectedDays.join(","));
  }

  // Add room ID if a room is selected (for room-based recommendations in future enhancement)
  if (rmId) {
    params.append("rm_id", rmId);
  }

  fetch(
    `${getApiBasePath()}get_schedule_recommendation.php?${params.toString()}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (!data || !data.success) {
        if (data && data.message) {
          console.warn("No schedule recommendation:", data.message);
        }
        return;
      }

      // If recommendation returned a day, check it (in case it's different from selected)
      const dayValue = data.day;
      if (dayValue && selectedDays.length === 0) {
        // Only auto-check if no days were previously selected
        const checkbox = $(`input[name="schd_day[]"][value="${dayValue}"]`);
        if (checkbox.length) {
          checkbox.prop("checked", true);
        }
      }

      // Set start and end times (HTML5 time input expects HH:MM)
      if (data.start_time && data.end_time) {
        $("#schd_start").val(data.start_time);
        $("#schd_end").val(data.end_time);
      }
    })
    .catch((error) => {
      console.error("Error getting schedule recommendation:", error);
    });
}

/**
 * A simple toast notification function.
 */
function showToast(icon, title) {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });

  Toast.fire({
    icon: icon,
    title: title,
  });
}

// Override the placeholder script in schedule_management.php
document.addEventListener("DOMContentLoaded", function () {
  const scheduleScriptTag = document.querySelector(
    "script[data-schedule-script]"
  );
  if (scheduleScriptTag) {
    scheduleScriptTag.remove();
  }
});

/**
 * Overrides for different dashboards (moderator, instructor) if needed.
 * This ensures the correct API endpoints are called based on the user's role.
 */
function adjustScheduleApiPaths() {
  // This function is now replaced by the more dynamic getApiBasePath()
  console.log("Using API base path: " + getApiBasePath());
}

/**
 * Opens the modal to add a new schedule.
 * This is triggered by the "Create Schedule" button in the UI.
 */
function openAddScheduleModal() {
  $("#scheduleForm")[0].reset();
  $("#schd_id").val("");
  $("#scheduleModalLabel").text("Create Schedule");
  $("#schd_status").closest(".col-md-6").hide();

  var scheduleModal = new bootstrap.Modal(
    document.getElementById("scheduleModal")
  );
  scheduleModal.show();
}

/*
 * REMOVED: loadClassesForAddSection function
 * This function was used by the removed Add Section modal functionality.
 * The Add Section functionality is now handled by room_management.php component.
 */

/*
 * REMOVED: autoSuggestNextSection function
 * This function was used by the removed Add Section modal with auto-fill functionality.
 * The room_management.php component uses a simpler manual input approach.
 */

/*
 * REMOVED: saveNewSection function
 * The Add Section functionality is now handled by the room_management.php component
 * which uses the addSection() function from admin_dashboard.js instead.
 */

// Event listeners for Add Section Modal - REMOVED
// The Add Section modal is now handled by room_management.php component
// which uses the addSection() function directly via onclick attribute
