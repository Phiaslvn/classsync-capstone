/**
 * Room Access Grants Management
 * Separate file to handle Room Access Grants functionality
 * - Room Access Grants Given: Shows all schedules (calendar view)
 * - Room Access Grants Received: Shows available slots with request buttons (list view)
 */

// Global variables for room access grants
let roomAccessGrantsListModalOpening = false;
let currentRoomAccessGrantsAvailableSlots = {};

/**
 * Open Room Access Grants List Modal (for Room Access Grants Received)
 * Shows available time slots with request buttons - LIST VIEW ONLY, NO CALENDAR
 */
function openRoomAccessGrantsListModal(rmId, rmName) {
  console.log("=== openRoomAccessGrantsListModal called ===", { rmId, rmName });
  console.log(
    "FORCING LIST MODAL - Calendar modal should NEVER be used for Room Access Grants Received"
  );

  // CRITICAL: Immediately close calendar modal if it exists and is open
  const calendarModalEl = document.getElementById("roomScheduleModal");
  if (calendarModalEl) {
    console.warn("Calendar modal found, closing it immediately");
    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
      const calendarModal = bootstrap.Modal.getInstance(calendarModalEl);
      if (calendarModal) {
        calendarModal.hide();
      }
    }
    // Also hide via direct DOM manipulation
    calendarModalEl.classList.remove("show");
    calendarModalEl.style.display = "none";
    const backdrop = document.querySelector(".modal-backdrop");
    if (backdrop) backdrop.remove();
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
  if (roomAccessGrantsListModalOpening) {
    console.log("List modal already opening, ignoring duplicate call");
    return;
  }
  roomAccessGrantsListModalOpening = true;

  proceedWithListModal();

  function proceedWithListModal() {
    // Store room info for request modal
    if (typeof activeRoomId !== "undefined") {
      activeRoomId = rmId;
    }
    if (typeof activeRoomName !== "undefined") {
      activeRoomName = rmName || "Room";
    }
    if (typeof window !== "undefined") {
      window.currentRoomScheduleListRmId = rmId; // Store for date filter refresh
      window.currentRoomScheduleListRmName = rmName || "Room";
    }

    let modalEl = document.getElementById("roomScheduleListModal");
    if (!modalEl) {
      console.error(
        "Room schedule list modal (roomScheduleListModal) not found in DOM"
      );
      console.error("Available modals:", {
        calendarModal: !!document.getElementById("roomScheduleModal"),
        listModal: !!document.getElementById("roomScheduleListModal"),
      });
      roomAccessGrantsListModalOpening = false;
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

    // Double-check we have the right modal
    if (modalEl.id !== "roomScheduleListModal") {
      console.error(
        "Wrong modal selected! Expected roomScheduleListModal, got:",
        modalEl.id
      );
      roomAccessGrantsListModalOpening = false;
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
      roomAccessGrantsListModalOpening = false;
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

    // Get date filter value if set (from the list modal)
    const listModalForFetch = document.getElementById("roomScheduleListModal");
    const dateFilterEl = listModalForFetch
      ? listModalForFetch.querySelector("#roomScheduleDateFilter")
      : document.getElementById("roomScheduleDateFilter");
    const dateFilter =
      dateFilterEl && dateFilterEl.value ? dateFilterEl.value.trim() : "";

    // Build API URL with date filter if provided
    let apiUrl = `../../admin/management/get_room_schedules.php?rm_id=${rmId}`;
    if (dateFilter) {
      apiUrl += `&date=${encodeURIComponent(dateFilter)}`;
    }

    // Fetch schedule data - for Room Access Grants Received, user is NOT the owner, so we get available slots
    fetch(apiUrl)
      .then((res) => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then((data) => {
        roomAccessGrantsListModalOpening = false;

        // Hide loading, show content
        if (loadingEl) loadingEl.style.display = "none";
        if (contentEl) {
          contentEl.style.display = "block";

          // CRITICAL: Clear any calendar structure that might exist
          // Force clear everything to ensure no calendar elements remain
          console.log(
            "Clearing content element - ensuring NO calendar elements"
          );
          contentEl.innerHTML = ""; // Clear everything first

          // CRITICAL: Remove ALL possible calendar-related elements
          const calendarSelectors = [
            ".calendar-container",
            ".calendar-grid",
            "#roomScheduleTimeColumn",
            "#roomScheduleDaysWrapper",
            ".schedule-block",
            ".time-column",
            ".days-wrapper",
            ".time-slot",
            ".day-column",
            ".day-column-content",
          ];

          calendarSelectors.forEach((selector) => {
            const elements = contentEl.querySelectorAll(selector);
            if (elements.length > 0) {
              console.warn(
                `Found ${elements.length} calendar element(s) with selector "${selector}", removing them`
              );
              elements.forEach((el) => el.remove());
            }
          });

          // Also check parent modal to ensure no calendar structure exists
          const parentModal = contentEl.closest(".modal");
          if (parentModal && parentModal.id === "roomScheduleListModal") {
            const modalCalendarElements = parentModal.querySelectorAll(
              ".calendar-container, .calendar-grid, #roomScheduleTimeColumn, #roomScheduleDaysWrapper"
            );
            if (modalCalendarElements.length > 0) {
              console.warn(
                `Found ${modalCalendarElements.length} calendar elements in list modal, removing them`
              );
              modalCalendarElements.forEach((el) => el.remove());
            }
          }

          console.log("Content cleared - ready for list view only");

          // For Room Access Grants Received, show available slots (user is not room owner)
          // The API returns { is_room_owner: false, view_type: 'available', data: { "Mon": [...], ... } } for available slots
          if (
            data &&
            (data.is_room_owner === false || data.view_type === "available") &&
            data.data &&
            typeof data.data === "object" &&
            !Array.isArray(data.data)
          ) {
            console.log(
              "✓ Valid available slots data received, rendering LIST view"
            );

            // Store available slots for request modal
            currentRoomAccessGrantsAvailableSlots = data.data;
            if (typeof currentRoomScheduleAvailableSlots !== "undefined") {
              currentRoomScheduleAvailableSlots = data.data;
            }

            // CRITICAL: Final check - ensure contentEl is in list modal, not calendar modal
            const finalCheck = contentEl.closest(".modal");
            if (finalCheck && finalCheck.id !== "roomScheduleListModal") {
              console.error(
                "FATAL: Content element is in wrong modal!",
                finalCheck.id
              );
              console.error("Expected: roomScheduleListModal");
              return;
            }

            // Render available slots as table with request buttons - LIST VIEW ONLY
            console.log(
              "Calling renderRoomAccessGrantsAvailableSlotsTable - LIST VIEW ONLY"
            );
            renderRoomAccessGrantsAvailableSlotsTable(data.data, contentEl);
          } else if (data && Array.isArray(data)) {
            // Fallback: if data is array, filter for available slots
            const availableSlots = data.filter(
              (s) => s.is_available_slot === true
            );
            if (availableSlots.length > 0) {
              renderRoomAccessGrantsAvailableSlotsTableList(
                availableSlots,
                contentEl
              );
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
        roomAccessGrantsListModalOpening = false;

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
      roomAccessGrantsListModalOpening = false;
      currentRoomAccessGrantsAvailableSlots = {};
      if (typeof currentRoomScheduleAvailableSlots !== "undefined") {
        currentRoomScheduleAvailableSlots = {};
      }
      
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

    // CRITICAL: Final check before showing - ensure calendar modal is NOT being shown
    const calendarCheck = document.getElementById("roomScheduleModal");
    if (calendarCheck && calendarCheck.classList.contains("show")) {
      console.error("ERROR: Calendar modal is still showing! Closing it now.");
      calendarCheck.classList.remove("show");
      calendarCheck.style.display = "none";
      const backdrop = document.querySelector(".modal-backdrop");
      if (backdrop) backdrop.remove();
    }

    // Show the LIST modal
    console.log("Opening LIST modal (roomScheduleListModal)");
    modal.show();

    // Verify list modal is shown
    setTimeout(() => {
      if (modalEl.classList.contains("show")) {
        console.log("✓ LIST modal is now visible");
      } else {
        console.error("ERROR: LIST modal did not open properly");
      }

      // Final safety check - ensure calendar modal is not visible
      const calendarFinalCheck = document.getElementById("roomScheduleModal");
      if (calendarFinalCheck && calendarFinalCheck.classList.contains("show")) {
        console.error(
          "CRITICAL ERROR: Calendar modal opened instead of list modal! Closing it."
        );
        if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
          const calendarModal = bootstrap.Modal.getInstance(calendarFinalCheck);
          if (calendarModal) calendarModal.hide();
        }
        calendarFinalCheck.classList.remove("show");
        calendarFinalCheck.style.display = "none";
      }
    }, 100);
  }
}

/**
 * Render available slots as table for Room Access Grants Received
 * LIST VIEW ONLY - NO CALENDAR ELEMENTS
 */
function renderRoomAccessGrantsAvailableSlotsTable(availableSlots, contentEl) {
  console.log(
    "Rendering available slots for Room Access Grants:",
    availableSlots
  );
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
      "ERROR: renderRoomAccessGrantsAvailableSlotsTable called on calendar modal!"
    );
    console.error("Parent modal ID:", parentModal.id);
    return;
  }

  // CRITICAL: Remove ANY calendar-related elements that might have been accidentally added
  const calendarContainer = contentEl.querySelector(".calendar-container");
  if (calendarContainer) {
    console.warn("Found calendar container in list modal, removing it");
    calendarContainer.remove();
  }

  // Remove calendar grid elements
  const calendarGrid = contentEl.querySelector(".calendar-grid");
  if (calendarGrid) {
    console.warn("Found calendar grid in list modal, removing it");
    calendarGrid.remove();
  }

  // Remove time column and days wrapper
  const timeColumn = contentEl.querySelector("#roomScheduleTimeColumn");
  const daysWrapper = contentEl.querySelector("#roomScheduleDaysWrapper");
  if (timeColumn || daysWrapper) {
    console.warn("Found calendar structure in list modal, removing it");
    if (timeColumn) timeColumn.remove();
    if (daysWrapper) daysWrapper.remove();
  }

  // Remove any schedule blocks (from calendar view)
  const scheduleBlocks = contentEl.querySelectorAll(".schedule-block");
  if (scheduleBlocks.length > 0) {
    console.warn(
      `Found ${scheduleBlocks.length} schedule blocks in list modal, removing them`
    );
    scheduleBlocks.forEach((block) => block.remove());
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

  // CRITICAL: Final check - ensure we're in the list modal, not calendar modal
  const finalModalCheck = contentEl.closest(".modal");
  if (finalModalCheck && finalModalCheck.id !== "roomScheduleListModal") {
    console.error(
      "FATAL: renderRoomAccessGrantsAvailableSlotsTable called on wrong modal:",
      finalModalCheck.id
    );
    console.error("Expected: roomScheduleListModal, Got:", finalModalCheck.id);
    return;
  }

  // Build availability table - LIST VIEW ONLY, NO CALENDAR
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

        // Escape HTML for safety
        const dayEscaped = escapeHtmlForRoomAccessGrants(day);
        const timeDisplayEscaped = escapeHtmlForRoomAccessGrants(
          `${slot.start_formatted} - ${slot.end_formatted}`
        );
        const durationEscaped = escapeHtmlForRoomAccessGrants(durationText);

        html += `
          <tr>
            <td style="padding: 0.75rem; vertical-align: middle;"><strong>${dayEscaped}</strong></td>
            <td style="padding: 0.75rem; vertical-align: middle;">
              <span class="badge bg-success" style="font-size: 0.875rem; padding: 0.5rem 0.75rem;">
                <i class="bi bi-clock me-1"></i>${timeDisplayEscaped}
              </span>
            </td>
            <td style="padding: 0.75rem; vertical-align: middle; color: #6c757d;">${durationEscaped}</td>
            <td class="text-center" style="padding: 0.75rem; vertical-align: middle;">
              <button class="btn btn-sm btn-primary" onclick="if(typeof openRoomRequestModalForSlot === 'function') { openRoomRequestModalForSlot('${dayEscaped}', '${timeSlotValue}'); } else { console.error('openRoomRequestModalForSlot function not found'); }" title="Request this time slot" style="min-width: 100px;">
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
    <div class="mt-3 text-center">
      <small class="text-muted">
        <i class="bi bi-info-circle me-1"></i>
        Showing <strong>${totalSlots}</strong> available time slot(s) for this room.
      </small>
    </div>
  `;

  // Clear and set content
  contentEl.innerHTML = "";
  contentEl.innerHTML = html;

  console.log(
    `Rendered ${totalSlots} available slot(s) for Room Access Grants list modal`
  );
  console.log(
    "Content element after rendering:",
    contentEl.innerHTML.substring(0, 200)
  );
}

/**
 * Render available slots as table/list (fallback for array format)
 */
function renderRoomAccessGrantsAvailableSlotsTableList(schedules, contentEl) {
  console.log(
    "Rendering available slots as table/list (array format) for Room Access Grants:",
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
    <div class="table-responsive" style="max-height: 50vh; overflow-y: auto;">
      <table class="table table-hover table-bordered" style="margin-bottom: 0;">
        <thead class="table-light" style="position: sticky; top: 0; z-index: 10; background-color: #f8f9fa;">
          <tr>
            <th style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Day</th>
            <th style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Time Slot</th>
            <th style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Duration</th>
            <th class="text-center" style="padding: 0.75rem; font-weight: 600; border-bottom: 2px solid #dee2e6;">Action</th>
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
    const dayDisplay = escapeHtmlForRoomAccessGrants(slot.schd_day || "-");
    const timeDisplay = escapeHtmlForRoomAccessGrants(timeSlotValue);
    const durationDisplay = escapeHtmlForRoomAccessGrants(durationText);

    // Escape single quotes in timeSlotValue for onclick handler
    const timeSlotValueEscaped = timeSlotValue
      .replace(/'/g, "\\'")
      .replace(/"/g, "&quot;");

    tableHTML += `
      <tr>
        <td style="padding: 0.75rem; vertical-align: middle;"><strong>${dayDisplay}</strong></td>
        <td style="padding: 0.75rem; vertical-align: middle;">
          <span class="badge bg-success" style="font-size: 0.875rem; padding: 0.5rem 0.75rem;">
            <i class="bi bi-clock me-1"></i>${timeDisplay}
          </span>
        </td>
        <td style="padding: 0.75rem; vertical-align: middle; color: #6c757d;">${durationDisplay}</td>
        <td class="text-center" style="padding: 0.75rem; vertical-align: middle;">
          <button class="btn btn-sm btn-primary" onclick="if(typeof openRoomRequestModalForSlot === 'function') { openRoomRequestModalForSlot('${dayDisplay}', '${timeSlotValueEscaped}'); } else { console.error('openRoomRequestModalForSlot function not found'); }" title="Request this time slot" style="min-width: 100px;">
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
    <div class="mt-3 text-center">
      <small class="text-muted">
        <i class="bi bi-info-circle me-1"></i>
        Showing <strong>${schedules.length}</strong> available time slot(s) for this room.
      </small>
    </div>
  `;

  // Replace content with table
  contentEl.innerHTML = "";
  contentEl.innerHTML = tableHTML;

  console.log(
    `Rendered ${schedules.length} available slot(s) as table for Room Access Grants`
  );
}

/**
 * Simple HTML escape function for Room Access Grants
 */
function escapeHtmlForRoomAccessGrants(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Make functions globally accessible IMMEDIATELY
if (typeof window !== "undefined") {
  window.openRoomAccessGrantsListModal = openRoomAccessGrantsListModal;
  window.renderRoomAccessGrantsAvailableSlotsTable =
    renderRoomAccessGrantsAvailableSlotsTable;
  window.renderRoomAccessGrantsAvailableSlotsTableList =
    renderRoomAccessGrantsAvailableSlotsTableList;

  // CRITICAL: Override any attempts to open calendar modal for Room Access Grants Received
  // This ensures list modal is ALWAYS used
  console.log("Room Access Grants functions registered globally");
  console.log(
    "openRoomAccessGrantsListModal available:",
    typeof window.openRoomAccessGrantsListModal === "function"
  );
}

// Also register on DOMContentLoaded as backup
if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      if (typeof window !== "undefined") {
        window.openRoomAccessGrantsListModal = openRoomAccessGrantsListModal;
        window.renderRoomAccessGrantsAvailableSlotsTable =
          renderRoomAccessGrantsAvailableSlotsTable;
        window.renderRoomAccessGrantsAvailableSlotsTableList =
          renderRoomAccessGrantsAvailableSlotsTableList;
        console.log(
          "Room Access Grants functions re-registered on DOMContentLoaded"
        );
      }
    });
  } else {
    // DOM already loaded
    if (typeof window !== "undefined") {
      window.openRoomAccessGrantsListModal = openRoomAccessGrantsListModal;
      window.renderRoomAccessGrantsAvailableSlotsTable =
        renderRoomAccessGrantsAvailableSlotsTable;
      window.renderRoomAccessGrantsAvailableSlotsTableList =
        renderRoomAccessGrantsAvailableSlotsTableList;
      window.applyRoomScheduleDateFilter = applyRoomScheduleDateFilter;
      window.clearRoomScheduleDateFilter = clearRoomScheduleDateFilter;
    }
  }
}

/**
 * Apply date filter to room schedule list
 */
function applyRoomScheduleDateFilter() {
  // Find the date filter in the currently visible list modal
  const listModal = document.getElementById("roomScheduleListModal");
  let dateFilterEl = null;

  if (listModal) {
    // Look within the list modal first
    dateFilterEl = listModal.querySelector("#roomScheduleDateFilter");
  }

  // Fallback: try global search (in case modal is not in DOM yet)
  if (!dateFilterEl) {
    dateFilterEl = document.getElementById("roomScheduleDateFilter");
  }

  if (!dateFilterEl) {
    console.error("Date filter input not found");
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Filter Not Found",
        text: "Date filter input not found. Please refresh the page.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  const dateValue = dateFilterEl.value ? dateFilterEl.value.trim() : "";
  console.log("Date filter value:", dateValue, "Element:", dateFilterEl);

  // Check if date is selected (required for filtering)
  if (!dateValue) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "warning",
        title: "Date Required",
        text: "Please select a date to filter available time slots.",
        confirmButtonColor: "#800000",
      });
    }
    return;
  }

  // Re-fetch data with date filter
  if (window.currentRoomScheduleListRmId) {
    openRoomAccessGrantsListModal(
      window.currentRoomScheduleListRmId,
      window.currentRoomScheduleListRmName
    );
  } else {
    console.error("No room ID stored for list modal");
  }
}

/**
 * Clear date filter and reload data
 */
function clearRoomScheduleDateFilter() {
  // Find the date filter in the currently visible list modal
  const listModal = document.getElementById("roomScheduleListModal");
  const dateFilterEl = listModal
    ? listModal.querySelector("#roomScheduleDateFilter")
    : document.getElementById("roomScheduleDateFilter");

  if (dateFilterEl) {
    dateFilterEl.value = "";
  }

  // Re-fetch data without date filter
  if (window.currentRoomScheduleListRmId) {
    openRoomAccessGrantsListModal(
      window.currentRoomScheduleListRmId,
      window.currentRoomScheduleListRmName
    );
  }
}
