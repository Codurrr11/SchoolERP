/**
 * SchoolSaaS Premium Dashboard JS Core
 * Handles responsive sidebar navigation, theme selections, search systems, metrics visualization,
 * and consolidated logic for teachers and students modules.
 */

document.addEventListener("DOMContentLoaded", () => {
  // Select elements
  const appLayout = document.querySelector(".app-layout");
  const sidebarToggleBtn = document.getElementById("sidebarToggleBtn");

  // Create mobile drawer overlay if not present
  let sidebarOverlay = document.getElementById("sidebarOverlay");
  if (!sidebarOverlay) {
    sidebarOverlay = document.createElement("div");
    sidebarOverlay.className = "sidebar-overlay";
    sidebarOverlay.id = "sidebarOverlay";
    appLayout.appendChild(sidebarOverlay);
  }

  const themeSunBtn = document.getElementById("themeSunBtn");
  const themeMoonBtn = document.getElementById("themeMoonBtn");

  // Utility check for viewport size
  const isMobile = () => window.innerWidth < 992;

  /**
   * Toggles Sidebar State
   * - Mobile: opens/closes sliding navigation drawer
   * - Desktop: collapses/expands the navigation sidebar width between 72px and 260px
   */
  function toggleSidebar() {
    if (isMobile()) {
      appLayout.classList.toggle("mobile-sidebar-active");
      appLayout.classList.remove("sidebar-expanded"); // Ensure desktop state is clean on mobile
    } else {
      appLayout.classList.toggle("sidebar-expanded");
      appLayout.classList.remove("mobile-sidebar-active"); // Ensure mobile drawer is closed

      // Save state preference
      const isExpanded = appLayout.classList.contains("sidebar-expanded");
      localStorage.setItem("sidebar-expanded", isExpanded ? "true" : "false");
    }
  }

  // Restore saved sidebar preference on load (only for desktop)
  if (!isMobile()) {
    const savedSidebarState = localStorage.getItem("sidebar-expanded");
    if (savedSidebarState === "true") {
      appLayout.classList.add("sidebar-expanded");
    } else {
      appLayout.classList.remove("sidebar-expanded");
    }
  }

  if (sidebarToggleBtn) {
    sidebarToggleBtn.addEventListener("click", toggleSidebar);
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", () => {
      appLayout.classList.remove("mobile-sidebar-active");
    });
  }

  const sidebarCloseBtn = document.getElementById("sidebarCloseBtn");
  if (sidebarCloseBtn) {
    sidebarCloseBtn.addEventListener("click", () => {
      appLayout.classList.remove("mobile-sidebar-active");
    });
  }

  /**
   * Dropdown Submenu Auto-Expand Sidebar Helper
   * If the sidebar is collapsed and the user clicks a 2-level dropdown trigger icon,
   * we expand the sidebar first and then slide down the submenu.
   */

  const submenuTriggers = document.querySelectorAll(
    '.sidebar-nav-dropdown a[data-bs-toggle="collapse"]',
  );
  submenuTriggers.forEach((trigger) => {
    trigger.addEventListener("click", function (e) {
      if (isMobile()) return; // Only process on desktop viewports

      if (!appLayout.classList.contains("sidebar-expanded")) {
        // Prevent bootstrap collapse from running immediately in collapsed sidebar
        e.preventDefault();
        e.stopPropagation();

        // Open the sidebar first
        appLayout.classList.add("sidebar-expanded");
        localStorage.setItem("sidebar-expanded", "true");

        // Retrieve target collapse element ID
        const targetSelector = this.getAttribute("href");
        const targetEl = document.querySelector(targetSelector);
        if (targetEl) {
          // Initialize and show the Bootstrap Collapse submenu
          setTimeout(() => {
            const bsCollapse = new bootstrap.Collapse(targetEl, {
              toggle: true,
            });
          }, 250); // Delay slightly to allow the sidebar width transition to complete smoothly
        }
      }
    });
  });

  /**
   * Light / Dark Mode Toggle Logic
   */
  if (themeSunBtn && themeMoonBtn) {
    // Light Mode Button click handler
    themeSunBtn.addEventListener("click", () => {
      themeSunBtn.classList.add("active");
      themeMoonBtn.classList.remove("active");
      document.body.classList.remove("dark-theme");
      document.documentElement.classList.remove("dark-mode");
      localStorage.setItem("dashboard-theme", "light");
    });

    // Dark Mode Button click handler

    themeMoonBtn.addEventListener("click", () => {
      themeMoonBtn.classList.add("active");
      themeSunBtn.classList.remove("active");
      document.body.classList.add("dark-theme");
      document.documentElement.classList.add("dark-mode");
      localStorage.setItem("dashboard-theme", "dark");
    });

    // Restore saved theme preference on load (Defaults to light mode)
    const savedTheme = localStorage.getItem("dashboard-theme");
    if (savedTheme === "dark") {
      themeMoonBtn.click();
    } else {
      themeSunBtn.click(); // Default Light Mode Enforced
    }
  }

  /**
   * Simple Table Search Filter Action (Dashboard Activities)
   */
  const searchInput = document.getElementById("activitiesSearchInput");
  const tableBody = document.getElementById("activitiesTableBody");

  if (searchInput && tableBody) {
    searchInput.addEventListener("input", (e) => {
      const query = e.target.value.toLowerCase().trim();
      const rows = tableBody.querySelectorAll("tr");

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });
  }

  /**
   * Responsive Tabs Label Sync
   * Changes the text label on the mobile tabs dropdown button to the current selected item
   */
  const mobileDropdownBtn = document.getElementById("mobileMenuDropdown");
  const mobileDropdownItems = document.querySelectorAll(
    "#mobileTabsContainer .dropdown-item",
  );

  if (mobileDropdownBtn && mobileDropdownItems.length > 0) {
    mobileDropdownItems.forEach((item) => {
      item.addEventListener("click", function (e) {
        // Remove active classes
        mobileDropdownItems.forEach((i) => i.classList.remove("active"));

        // Add active class to clicked item
        this.classList.add("active");

        // Update Mobile Button label
        const labelText = this.getAttribute("data-value");
        mobileDropdownBtn.querySelector("span").textContent = labelText;
      });
    });
  }

  /**
   * Chart.js Configuration: Collected vs Outstanding Double Bar Chart
   */
  const chartCanvas = document.getElementById("incomeChart");
  if (chartCanvas) {
    const ctx = chartCanvas.getContext("2d");

    // Create diagonal stripe pattern for Outstanding bar chart
    const stripeCanvas = document.createElement("canvas");
    const stripeCtx = stripeCanvas.getContext("2d");
    stripeCanvas.width = 12;
    stripeCanvas.height = 12;

    // Draw diagonal blue lines
    stripeCtx.strokeStyle = "#6366f1";
    stripeCtx.lineWidth = 3;
    stripeCtx.lineCap = "square";
    stripeCtx.beginPath();
    stripeCtx.moveTo(0, 12);
    stripeCtx.lineTo(12, 0);
    stripeCtx.stroke();

    const lossPattern = ctx.createPattern(stripeCanvas, "repeat");

    // Dynamic chart data from database attributes if available
    const dashboardDataEl = document.getElementById("dashboard-data");
    let months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"];
    let collectedData = [
      22000, 12000, 10000, 21000, 15000, 23000, 12000, 14000,
    ];
    let outstandingData = [
      36000, 38000, 36000, 38000, 39000, 51000, 38000, 35000,
    ];

    if (dashboardDataEl) {
      try {
        const rawMonths = dashboardDataEl.getAttribute("data-chart-months");
        const rawCollected = dashboardDataEl.getAttribute("data-chart-collected");
        const rawOutstanding = dashboardDataEl.getAttribute("data-chart-outstanding");
        
        if (rawMonths) months = JSON.parse(rawMonths);
        if (rawCollected) collectedData = JSON.parse(rawCollected).map(Number);
        if (rawOutstanding) outstandingData = JSON.parse(rawOutstanding).map(Number);
      } catch (e) {
        console.error("Error parsing dashboard dynamic chart data", e);
      }

      // Update administrative expense progress bar width dynamically
      const expensePercentage = dashboardDataEl.getAttribute("data-expense-percentage");
      const progressFill = document.querySelector(".admin-expense-progress-fill");
      if (progressFill && expensePercentage !== null) {
        progressFill.style.width = expensePercentage + "%";
      }
    }

    new Chart(ctx, {
      type: "bar",
      data: {
        labels: months,
        datasets: [
          {
            label: "Collected",
            data: collectedData,
            backgroundColor: "#111827", // Dark charcoal/navy
            borderWidth: 0,
            borderRadius: 6,
            borderSkipped: false,
            barPercentage: 0.7,
            categoryPercentage: 0.6,
          },
          {
            label: "Outstanding",
            data: outstandingData,
            backgroundColor: lossPattern, // Stripe pattern
            borderColor: "#6366f1",
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false,
            barPercentage: 0.7,
            categoryPercentage: 0.6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false, // We use our own HTML custom header legend
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                let label = context.dataset.label || "";
                if (label) {
                  label += ": ";
                }
                if (context.parsed.y !== null) {
                  label += new Intl.NumberFormat("en-IN", {
                    style: "currency",
                    currency: "INR",
                    maximumFractionDigits: 0,
                  }).format(context.parsed.y);
                }
                return label;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: "#E5E7EB",
              drawBorder: false,
            },
            ticks: {
              color: "#9CA3AF",
              font: {
                size: 10,
                family: "Glegoo",
              },
              callback: function (value) {
                if (value === 0) return "00";
                return "₹" + value / 1000 + "k";
              },
            },
          },
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: "#9CA3AF",
              font: {
                size: 10,
                family: "Glegoo",
              },
            },
          },
        },
      },
    });
  }

  /**
   * Keyboard Shortcuts (Ctrl + K) to Focus Search Box
   */
  document.addEventListener("keydown", (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      const tableSearchInput =
        document.getElementById("activitiesSearchInput") ||
        document.getElementById("studentSearchInput") ||
        document.getElementById("teacherSearchInput");
      if (tableSearchInput) {
        tableSearchInput.focus();
      }
    }
  });

  // ==========================================
  // DOCUMENTS REPLACE FORMS & PRINT TRIGGERS (SHARED)
  // ==========================================
  document.querySelectorAll(".replace-file-trigger").forEach((btn) => {
    btn.addEventListener("click", function () {
      const actionsDiv = this.closest(".doc-actions");
      if (actionsDiv) {
        const formWrapper = actionsDiv.querySelector(".upload-form-wrapper");
        if (formWrapper) {
          formWrapper.classList.remove("d-none");
        }
        this.classList.add("d-none");
      }
    });
  });

  document.querySelectorAll(".cancel-replace-trigger").forEach((btn) => {
    btn.addEventListener("click", function () {
      const actionsDiv = this.closest(".doc-actions");
      if (actionsDiv) {
        const formWrapper = actionsDiv.querySelector(".upload-form-wrapper");
        const replaceBtn = actionsDiv.querySelector(".replace-file-trigger");
        if (formWrapper) {
          formWrapper.classList.add("d-none");
        }
        if (replaceBtn) {
          replaceBtn.classList.remove("d-none");
        }
      }
    });
  });

  document.querySelectorAll(".print-page-trigger").forEach((btn) => {
    btn.addEventListener("click", function () {
      window.print();
    });
  });

  // ==========================================
  // STUDENT VIEW TAB INITIALIZATION
  // ==========================================
  const studentTabElement = document.getElementById("studentTab");
  if (studentTabElement) {
    // Initialize Bootstrap Tab functionality
    const tabButtons = studentTabElement.querySelectorAll(".nav-link");
    tabButtons.forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault();

        // Get the target tab pane ID
        const targetId = this.getAttribute("data-bs-target");
        if (!targetId) return;

        // Hide all tab panes
        const allPanes = document.querySelectorAll(".tab-pane");
        allPanes.forEach((pane) => {
          pane.classList.remove("show", "active");
        });

        // Remove active class from all tab buttons
        tabButtons.forEach((btn) => {
          btn.classList.remove("active");
          btn.setAttribute("aria-selected", "false");
        });

        // Show the selected tab pane
        const targetPane = document.querySelector(targetId);
        if (targetPane) {
          targetPane.classList.add("show", "active");
        }

        // Mark the clicked button as active
        this.classList.add("active");
        this.setAttribute("aria-selected", "true");

        // Update hash in URL quietly
        window.history.pushState(null, null, targetId);
      });
    });

    // Hash-based tab persistence on page load
    const hash = window.location.hash;
    if (hash) {
      const activeTabBtn = studentTabElement.querySelector(
        `[data-bs-target="${hash}"]`,
      );
      if (activeTabBtn) {
        activeTabBtn.click();
      }
    }
  }

  // Attendance Month Selection Filter Navigation
  const attendanceMonthSelect = document.getElementById(
    "attendanceMonthSelect",
  );
  if (attendanceMonthSelect) {
    attendanceMonthSelect.addEventListener("change", function () {
      const month = this.value;
      const studentId = this.getAttribute("data-student-id");
      const year =
        document.getElementById("attendanceYearSelect")?.value ||
        new Date().getFullYear();
      window.location.href = `view.php?id=${studentId}&month=${month}&year=${year}#attendance`;
    });
  }

  // Global client-side CSV Export for Attendance Table
  window.downloadAttendanceCSV = function () {
    const table = document.getElementById("attendanceTable");
    if (!table) return;

    const rows = table.querySelectorAll("tr");
    let csv = [];

    for (let i = 0; i < rows.length; i++) {
      let row = [];
      const cols = rows[i].querySelectorAll("td, th");

      for (let j = 0; j < cols.length; j++) {
        // Clean cell text
        let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
        // Escape quotes
        text = text.replace(/"/g, '""');
        row.push(`"${text}"`);
      }
      csv.push(row.join(","));
    }

    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");

    // Create clean file name based on student page data or document title
    const heading =
      document.querySelector("h4.summary-name")?.innerText || "student";
    const cleanHeading = heading.toLowerCase().replace(/[^a-z0-9]+/g, "_");
    const dateText =
      document.querySelector("#attendance h4")?.innerText || "attendance";
    const cleanDate = dateText.toLowerCase().replace(/[^a-z0-9]+/g, "_");

    link.setAttribute("href", encodedUri);
    link.setAttribute(
      "download",
      `attendance_${cleanHeading}_${cleanDate}.csv`,
    );
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // ==========================================
  // STUDENTS MODULE LOGIC
  // ==========================================
  const studentMeta = document.getElementById("student-page-data");
  if (studentMeta) {
    const BASE_URL = studentMeta.dataset.baseUrl || "";
    const csrfToken = studentMeta.dataset.csrfToken || "";
    const flashSuccess = studentMeta.dataset.flashSuccess || "";
    const flashError = studentMeta.dataset.flashError || "";

    // Success/Error SweetAlert Popups
    if (flashSuccess) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: flashSuccess,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
      });
    }

    if (flashError) {
      Swal.fire({
        icon: "error",
        title: "Operation Error",
        text: flashError,
        confirmButtonColor: "#6366f1",
      });
    }

    // Select All and Bulk Actions Enable/Disable
    const selectAllCheckbox = document.getElementById("selectAllCheckbox");
    const selectCheckboxes = document.querySelectorAll(
      ".student-select-checkbox",
    );
    const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

    function updateBulkDeleteState() {
      if (!bulkDeleteBtn) return;
      const checkedCount = document.querySelectorAll(
        ".student-select-checkbox:checked",
      ).length;
      bulkDeleteBtn.disabled = checkedCount === 0;
    }

    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener("change", function () {
        selectCheckboxes.forEach((cb) => {
          cb.checked = selectAllCheckbox.checked;
        });
        updateBulkDeleteState();
      });
    }

    selectCheckboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        if (selectAllCheckbox) {
          const totalCount = selectCheckboxes.length;
          const checkedCount = document.querySelectorAll(
            ".student-select-checkbox:checked",
          ).length;
          selectAllCheckbox.checked = totalCount === checkedCount;
        }
        updateBulkDeleteState();
      });
    });

    if (bulkDeleteBtn) {
      bulkDeleteBtn.addEventListener("click", function (e) {
        e.preventDefault();
        Swal.fire({
          title: "Are you sure?",
          text: "Do you want to move the selected students to trash?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#ef4444",
          cancelButtonColor: "#64748b",
          confirmButtonText: "Yes, move to trash",
        }).then((result) => {
          if (result.isConfirmed) {
            document.getElementById("bulkDeleteForm").submit();
          }
        });
      });
    }

    // Search filter
    const searchInput = document.getElementById("studentSearchInput");
    const tableBody = document.getElementById("studentsTableBody");
    if (searchInput && tableBody) {
      searchInput.addEventListener("input", function () {
        const query = searchInput.value.toLowerCase();
        const rows = tableBody.querySelectorAll("tr");
        rows.forEach((row) => {
          const text = row.innerText.toLowerCase();
          row.style.display = text.includes(query) ? "" : "none";
        });
      });
    }

    // Delete Confirmation individual
    const deleteForm = document.getElementById("deleteStudentForm");
    const deleteIdInput = document.getElementById("delete_student_id");
    document.querySelectorAll(".delete-student-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        const id = this.dataset.id;
        const name = this.dataset.name;
        Swal.fire({
          title: "Are you sure?",
          text: `Do you want to move student "${name}" to trash?`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#ef4444",
          cancelButtonColor: "#64748b",
          confirmButtonText: "Yes, delete it!",
        }).then((result) => {
          if (result.isConfirmed && deleteForm && deleteIdInput) {
            deleteIdInput.value = id;
            deleteForm.submit();
          }
        });
      });
    });

    // Helper escape HTML
    function escapeHtml(str) {
      if (!str) return "";
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // Dynamic Qualifications Table
    function addQualificationRow(
      tableId,
      qualVal = "",
      yearVal = "",
      rollVal = "",
      marksVal = "",
      pctVal = "",
      subVal = "",
      schoolVal = "",
    ) {
      const tbody = document.getElementById(tableId);
      if (!tbody) return;

      const row = document.createElement("tr");
      row.innerHTML = `
                <td><input type="text" name="qualification[]" class="form-control-admin py-1 fs-7" placeholder="Qualification" value="${escapeHtml(qualVal)}" required></td>
                <td><input type="text" name="passing_year[]" class="form-control-admin py-1 fs-7" placeholder="Year" value="${escapeHtml(yearVal)}"></td>
                <td><input type="text" name="roll_no[]" class="form-control-admin py-1 fs-7" placeholder="Roll No" value="${escapeHtml(rollVal)}"></td>
                <td><input type="text" name="obtained_marks[]" class="form-control-admin py-1 fs-7" placeholder="Marks" value="${escapeHtml(marksVal)}"></td>
                <td><input type="text" name="percentage[]" class="form-control-admin py-1 fs-7" placeholder="%" value="${escapeHtml(pctVal)}"></td>
                <td><input type="text" name="subjects[]" class="form-control-admin py-1 fs-7" placeholder="Subjects" value="${escapeHtml(subVal)}"></td>
                <td><input type="text" name="school_college_name[]" class="form-control-admin py-1 fs-7" placeholder="School/College" value="${escapeHtml(schoolVal)}"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger border-0 p-1 remove-qual-row"><i class="ph-light ph-trash"></i></button></td>
            `;
      tbody.appendChild(row);
      row
        .querySelector(".remove-qual-row")
        .addEventListener("click", () => row.remove());
    }

    const addBtn = document.getElementById("addQualificationRowBtn");
    if (addBtn) {
      addBtn.addEventListener("click", () =>
        addQualificationRow("qualificationsTbody"),
      );
    }

    const editAddBtn = document.getElementById("edit_addQualificationRowBtn");
    if (editAddBtn) {
      editAddBtn.addEventListener("click", () =>
        addQualificationRow("edit_qualificationsTbody"),
      );
    }

    // ==========================================
    // PARENTS MODULE LOGIC (ENHANCED)
    // ==========================================
    const parentMeta = document.getElementById("parent-page-data");
    if (parentMeta) {
      const BASE_URL = parentMeta.dataset.baseUrl || "";
      const csrfToken = parentMeta.dataset.csrfToken || "";
      const flashSuccess = parentMeta.dataset.flashSuccess || "";
      const flashError = parentMeta.dataset.flashError || "";

      if (flashSuccess) {
        Swal.fire({
          toast: true,
          position: "top-end",
          icon: "success",
          title: flashSuccess,
          showConfirmButton: false,
          timer: 4000,
          timerProgressBar: true,
        });
      }

      if (flashError) {
        Swal.fire({
          icon: "error",
          title: "Operation Error",
          text: flashError,
          confirmButtonColor: "#6366f1",
        });
      }

      // Select All and Bulk Actions
      const selectAllCheckbox = document.getElementById("selectAllCheckbox");
      const selectCheckboxes = document.querySelectorAll(
        ".parent-select-checkbox",
      );
      const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

      function updateBulkDeleteState() {
        if (!bulkDeleteBtn) return;
        const checkedCount = document.querySelectorAll(
          ".parent-select-checkbox:checked",
        ).length;
        bulkDeleteBtn.disabled = checkedCount === 0;

        // Match Students UI: Change button style when enabled
        if (checkedCount > 0) {
          bulkDeleteBtn.classList.remove("disabled");
        } else {
          bulkDeleteBtn.classList.add("disabled");
        }
      }

      if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function () {
          selectCheckboxes.forEach(
            (cb) => (cb.checked = selectAllCheckbox.checked),
          );
          updateBulkDeleteState();
        });
      }

      selectCheckboxes.forEach((cb) => {
        cb.addEventListener("change", function () {
          if (selectAllCheckbox) {
            const totalCount = selectCheckboxes.length;
            const checkedCount = document.querySelectorAll(
              ".parent-select-checkbox:checked",
            ).length;
            selectAllCheckbox.checked = totalCount === checkedCount;
          }
          updateBulkDeleteState();
        });
      });

      if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener("click", function (e) {
          e.preventDefault();
          Swal.fire({
            title: "Are you sure?",
            text: "Do you want to move the selected parents to trash?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Yes, move to trash",
          }).then((result) => {
            if (result.isConfirmed) {
              const form = document.getElementById("bulkDeleteForm");
              const inputContainer =
                document.getElementById("bulkDeleteInputs");
              inputContainer.innerHTML = "";
              document
                .querySelectorAll(".parent-select-checkbox:checked")
                .forEach((cb) => {
                  const input = document.createElement("input");
                  input.type = "hidden";
                  input.name = "ids[]";
                  input.value = cb.value;
                  inputContainer.appendChild(input);
                });
              form.submit();
            }
          });
        });
      }

      // Search filter
      const searchInput = document.getElementById("parentSearchInput");
      const tableBody = document.getElementById("parentsTableBody");
      if (searchInput && tableBody) {
        searchInput.addEventListener("input", function () {
          const query = searchInput.value.toLowerCase();
          const rows = tableBody.querySelectorAll("tr");
          rows.forEach((row) => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? "" : "none";
          });
        });
      }

      // Delete Confirmation individual
      const deleteForm = document.getElementById("deleteParentForm");
      const deleteIdInput = document.getElementById("delete_parent_id");
      document.querySelectorAll(".delete-parent-btn").forEach((btn) => {
        btn.addEventListener("click", function () {
          const id = this.dataset.id;
          const name = this.dataset.name;
          Swal.fire({
            title: "Are you sure?",
            text: `Do you want to move parent "${name}" to trash?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Yes, delete it!",
          }).then((result) => {
            if (result.isConfirmed && deleteForm && deleteIdInput) {
              deleteIdInput.value = id;
              deleteForm.submit();
            }
          });
        });
      });

      // Edit Button AJAX data load (ENHANCED with all fields)
      const editButtons = document.querySelectorAll(".edit-parent-btn");
      editButtons.forEach((btn) => {
        btn.addEventListener("click", function () {
          const id = this.dataset.id;
          fetch(`index.php?get_parent_details=1&id=${id}`)
            .then((res) => res.json())
            .then((res) => {
              if (res.success) {
                const data = res.data;

                // Personal Details
                document.getElementById("edit_parent_id").value = data.id || "";
                document.getElementById("edit_first_name").value =
                  data.first_name || "";
                document.getElementById("edit_last_name").value =
                  data.last_name || "";
                document.getElementById("edit_mobile").value =
                  data.mobile || "";
                document.getElementById("edit_alternate_mobile").value =
                  data.alternate_mobile || "";
                document.getElementById("edit_whatsapp_no").value =
                  data.whatsapp_no || "";
                document.getElementById("edit_email").value = data.email || "";
                document.getElementById("edit_gender").value =
                  data.gender || "male";
                document.getElementById("edit_parent_type").value =
                  data.parent_type || "Father";
                document.getElementById("edit_qualification").value =
                  data.qualification || "";
                document.getElementById("edit_aadhaar_no").value =
                  data.aadhaar_no || "";

                // Employment
                document.getElementById("edit_company_name").value =
                  data.company_name || "";
                document.getElementById("edit_designation").value =
                  data.designation || "";
                document.getElementById("edit_company_address").value =
                  data.company_address || "";
                document.getElementById("edit_company_phone").value =
                  data.company_phone || "";

                // Address
                document.getElementById("edit_address").value =
                  data.address || "";
                document.getElementById("edit_pincode").value =
                  data.pincode || "";
                document.getElementById("edit_city").value = data.city || "";
                document.getElementById("edit_state").value = data.state || "";
                document.getElementById("edit_country").value =
                  data.country || "India";

                // Student Mapping
                const studentSelect =
                  document.getElementById("edit_student_ids");
                const studentIds = data.student_ids || [];
                Array.from(studentSelect.options).forEach((opt) => {
                  opt.selected = studentIds.includes(parseInt(opt.value));
                });

                new bootstrap.Modal(
                  document.getElementById("editParentModal"),
                ).show();
              }
            });
        });
      });
    }

    // Dynamic Section list change on Class selection
    function setupClassSectionHandlers(classSelectId, sectionSelectId) {
      const classSelect = document.getElementById(classSelectId);
      const sectionSelect = document.getElementById(sectionSelectId);
      if (!classSelect || !sectionSelect) return;

      classSelect.addEventListener("change", function () {
        const selectedOption = classSelect.options[classSelect.selectedIndex];
        const sectionsJson = selectedOption.dataset.sections || "[]";
        const sections = JSON.parse(sectionsJson);

        sectionSelect.innerHTML =
          '<option value="">-- Select Sections --</option>';
        sections.forEach((sec) => {
          const opt = document.createElement("option");
          opt.value = sec.id;
          opt.textContent = sec.name;
          sectionSelect.appendChild(opt);
        });
      });
    }
    setupClassSectionHandlers("student_class_select", "student_section_select");
    setupClassSectionHandlers(
      "edit_student_class_select",
      "edit_student_section_select",
    );

    // Edit Button AJAX data load
    const editButtons = document.querySelectorAll(".edit-student-btn");
    editButtons.forEach((btn) => {
      btn.addEventListener("click", function () {
        const id = this.dataset.id;
        fetch(`index.php?get_student_details=1&id=${id}`)
          .then((res) => res.json())
          .then((res) => {
            if (res.success) {
              const data = res.data;

              document.getElementById("edit_student_id").value = data.id || "";
              document.getElementById("edit_apaar_id").value =
                data.apaar_id || "";
              document.getElementById("edit_pen_no").value = data.pen_no || "";
              document.getElementById("edit_registration_no_prefix").value =
                data.registration_no_prefix || "";
              document.getElementById("edit_registration_no").value =
                data.registration_no || "";
              document.getElementById("edit_enrollment_no_prefix").value =
                data.enrollment_no_prefix || "";
              document.getElementById("edit_enrollment_no").value =
                data.enrollment_no || "";
              document.getElementById("edit_sr_no_prefix").value =
                data.sr_no_prefix || "";
              document.getElementById("edit_sr_no").value = data.sr_no || "";
              document.getElementById("edit_general_reg_no").value =
                data.general_reg_no || "";
              document.getElementById("edit_admission_no_prefix").value =
                data.admission_no_prefix || "";
              document.getElementById("edit_admission_no").value =
                data.admission_no || "";
              document.getElementById("edit_admission_date").value =
                data.admission_date || "";
              document.getElementById("edit_srn_no").value = data.srn_no || "";
              document.getElementById("edit_roll_no").value =
                data.roll_no || "";
              document.getElementById("edit_stream").value = data.stream || "";
              document.getElementById("edit_education_medium").value =
                data.education_medium || "";
              document.getElementById("edit_referred_by").value =
                data.referred_by || "";

              const rteYes = document.getElementById("edit_is_rte_yes");
              const rteNo = document.getElementById("edit_is_rte_no");
              if (data.is_rte === "yes") {
                rteYes.checked = true;
              } else {
                rteNo.checked = true;
              }

              document.getElementById("edit_enrolled_session").value =
                data.enrolled_session || "";
              document.getElementById("edit_enrolled_year").value =
                data.enrolled_year || "";

              const specYes = document.getElementById("edit_special_needs_yes");
              const specNo = document.getElementById("edit_special_needs_no");
              if (data.special_needs === "yes") {
                specYes.checked = true;
              } else {
                specNo.checked = true;
              }

              const bplYes = document.getElementById("edit_is_bpl_yes");
              const bplNo = document.getElementById("edit_is_bpl_no");
              if (data.is_bpl === "yes") {
                bplYes.checked = true;
              } else {
                bplNo.checked = true;
              }

              document.getElementById("edit_house_block").value =
                data.house_block || "";

              document.getElementById("edit_first_name").value =
                data.first_name || "";
              document.getElementById("edit_last_name").value =
                data.last_name || "";
              document.getElementById("edit_father_name").value =
                data.father_name || "";
              document.getElementById("edit_mobile_no").value =
                data.mobile_no || "";
              document.getElementById("edit_alternate_no").value =
                data.alternate_no || "";
              document.getElementById("edit_whatsapp_no").value =
                data.whatsapp_no || "";
              document.getElementById("edit_email").value = data.email || "";
              document.getElementById("edit_blood_group").value =
                data.blood_group || "";
              document.getElementById("edit_height").value = data.height || "";
              document.getElementById("edit_weight").value = data.weight || "";
              document.getElementById("edit_dob").value = data.dob || "";
              document.getElementById("edit_place_of_birth").value =
                data.place_of_birth || "";
              document.getElementById("edit_dob_certificate_no").value =
                data.dob_certificate_no || "";

              document.getElementById("edit_income_app_no").value =
                data.income_app_no || "";
              document.getElementById("edit_caste_app_no").value =
                data.caste_app_no || "";
              document.getElementById("edit_domicile_app_no").value =
                data.domicile_app_no || "";

              document.getElementById("edit_nationality").value =
                data.nationality || "INDIAN";
              document.getElementById("edit_religion").value =
                data.religion || "";
              document.getElementById("edit_category").value =
                data.category || "";
              document.getElementById("edit_caste").value = data.caste || "";

              document.getElementById("edit_aadhar_no").value =
                data.aadhar_no || "";

              document.getElementById("edit_tc_no").value = data.tc_no || "";
              document.getElementById("edit_tc_issue_date").value =
                data.tc_issue_date || "";

              document.getElementById("edit_scholarship_id").value =
                data.scholarship_id || "";
              document.getElementById("edit_scholarship_password").value =
                data.scholarship_password || "";

              document.getElementById("edit_govt_student_id").value =
                data.govt_student_id || "";
              document.getElementById("edit_govt_family_id").value =
                data.govt_family_id || "";
              document.getElementById("edit_samagra_id").value =
                data.samagra_id || "";

              document.getElementById("edit_bank_name").value =
                data.bank_name || "";
              document.getElementById("edit_bank_branch").value =
                data.bank_branch || "";
              document.getElementById("edit_ifsc_code").value =
                data.ifsc_code || "";
              document.getElementById("edit_bank_account_holder").value =
                data.bank_account_holder || "";
              document.getElementById("edit_bank_account_no").value =
                data.bank_account_no || "";
              document.getElementById("edit_pan_no").value = data.pan_no || "";

              document.getElementById("edit_mother_name").value =
                data.mother_name || "";
              document.getElementById("edit_guardian_name").value =
                data.guardian_name || "";
              document.getElementById("edit_mother_qualification").value =
                data.mother_qualification || "";
              document.getElementById("edit_father_qualification").value =
                data.father_qualification || "";
              document.getElementById("edit_guardian_qualification").value =
                data.guardian_qualification || "";
              document.getElementById("edit_mother_address").value =
                data.mother_address || "";
              document.getElementById("edit_father_address").value =
                data.father_address || "";
              document.getElementById("edit_guardian_address").value =
                data.guardian_address || "";
              document.getElementById("edit_mother_occupation").value =
                data.mother_occupation || "";
              document.getElementById("edit_father_occupation").value =
                data.father_occupation || "";
              document.getElementById("edit_guardian_occupation").value =
                data.guardian_occupation || "";
              document.getElementById("edit_mother_official_address").value =
                data.mother_official_address || "";
              document.getElementById("edit_father_official_address").value =
                data.father_official_address || "";
              document.getElementById("edit_guardian_official_address").value =
                data.guardian_official_address || "";
              document.getElementById("edit_mother_income").value =
                data.mother_income || "";
              document.getElementById("edit_father_income").value =
                data.father_income || "";
              document.getElementById("edit_guardian_income").value =
                data.guardian_income || "";
              document.getElementById("edit_mother_email").value =
                data.mother_email || "";
              document.getElementById("edit_father_email").value =
                data.father_email || "";
              document.getElementById("edit_guardian_email").value =
                data.guardian_email || "";
              document.getElementById("edit_mother_mobile").value =
                data.mother_mobile || "";
              document.getElementById("edit_father_mobile").value =
                data.father_mobile || "";
              document.getElementById("edit_guardian_mobile").value =
                data.guardian_mobile || "";
              document.getElementById("edit_mother_aadhar").value =
                data.mother_aadhar || "";
              document.getElementById("edit_father_aadhar").value =
                data.father_aadhar || "";
              document.getElementById("edit_guardian_aadhar").value =
                data.guardian_aadhar || "";

              const catCertHelp = document.getElementById(
                "edit_category_certificate_help",
              );
              if (data.category_certificate) {
                catCertHelp.innerHTML = `Current: <a href="${BASE_URL}${data.category_certificate}" target="_blank">${data.category_certificate.split("/").pop()}</a>`;
              } else {
                catCertHelp.innerHTML = "";
              }

              const aadharFileHelp = document.getElementById(
                "edit_aadhar_file_help",
              );
              if (data.aadhar_file) {
                aadharFileHelp.innerHTML = `Current: <a href="${BASE_URL}${data.aadhar_file}" target="_blank">${data.aadhar_file.split("/").pop()}</a>`;
              } else {
                aadharFileHelp.innerHTML = "";
              }

              const tcFileHelp = document.getElementById("edit_tc_file_help");
              if (data.tc_file) {
                tcFileHelp.innerHTML = `Current: <a href="${BASE_URL}${data.tc_file}" target="_blank">${data.tc_file.split("/").pop()}</a>`;
              } else {
                tcFileHelp.innerHTML = "";
              }

              const motherPhotoHelp = document.getElementById(
                "edit_mother_photo_help",
              );
              if (data.mother_photo) {
                motherPhotoHelp.innerHTML = `Current: <a href="${BASE_URL}${data.mother_photo}" target="_blank">${data.mother_photo.split("/").pop()}</a>`;
              } else {
                motherPhotoHelp.innerHTML = "";
              }

              const fatherPhotoHelp = document.getElementById(
                "edit_father_photo_help",
              );
              if (data.father_photo) {
                fatherPhotoHelp.innerHTML = `Current: <a href="${BASE_URL}${data.father_photo}" target="_blank">${data.father_photo.split("/").pop()}</a>`;
              } else {
                fatherPhotoHelp.innerHTML = "";
              }

              const guardianPhotoHelp = document.getElementById(
                "edit_guardian_photo_help",
              );
              if (data.guardian_photo) {
                guardianPhotoHelp.innerHTML = `Current: <a href="${BASE_URL}${data.guardian_photo}" target="_blank">${data.guardian_photo.split("/").pop()}</a>`;
              } else {
                guardianPhotoHelp.innerHTML = "";
              }

              document.getElementById("edit_total_fees").value =
                data.total_fees || 0.0;
              document.getElementById("edit_total_paid").value =
                data.total_paid || 0.0;
              document.getElementById("edit_total_discount").value =
                data.total_discount || 0.0;
              document.getElementById("edit_fine_amount").value =
                data.fine_amount || 0.0;

              document.getElementById("edit_biometric_code").value =
                data.biometric_code || "";
              document.getElementById("edit_username").value =
                data.u_name || "";
              document.getElementById("edit_password").value = "";

              if (data.gender) {
                const genRad = document.getElementById(
                  `edit_gender_${data.gender}`,
                );
                if (genRad) genRad.checked = true;
              }

              const classSelect = document.getElementById(
                "edit_student_class_select",
              );
              classSelect.value = data.class_id || "";

              const changeEvent = new Event("change");
              classSelect.dispatchEvent(changeEvent);
              document.getElementById("edit_student_section_select").value =
                data.section_id || "";

              document.getElementById("edit_enrolled_class_select").value =
                data.enrolled_class_id || "";

              const photoHelp = document.getElementById("edit_photo_help");
              if (data.photo) {
                photoHelp.innerHTML = `Current: <a href="${BASE_URL}${data.photo}" target="_blank">${data.photo.split("/").pop()}</a>`;
              } else {
                photoHelp.innerHTML =
                  "Allowed: JPEG, JPG, PNG, WEBP. Max 10MB.";
              }

              const dobHelp = document.getElementById("edit_dob_cert_help");
              if (data.dob_certificate) {
                dobHelp.innerHTML = `Current: <a href="${BASE_URL}${data.dob_certificate}" target="_blank">${data.dob_certificate.split("/").pop()}</a>`;
              } else {
                dobHelp.innerHTML = "Allowed: JPEG, JPG, PNG, PDF. Max 10MB.";
              }

              const qualTbody = document.getElementById(
                "edit_qualificationsTbody",
              );
              qualTbody.innerHTML = "";
              let qualifications = [];
              if (data.qualifications) {
                try {
                  qualifications = JSON.parse(data.qualifications);
                } catch (e) {}
              }
              if (qualifications.length === 0) {
                addQualificationRow("edit_qualificationsTbody");
              } else {
                qualifications.forEach((q) => {
                  addQualificationRow(
                    "edit_qualificationsTbody",
                    q.qualification,
                    q.passing_year,
                    q.roll_no,
                    q.obtained_marks,
                    q.percentage,
                    q.subjects,
                    q.school_college_name,
                  );
                });
              }

              const editModal = new bootstrap.Modal(
                document.getElementById("editStudentModal"),
              );
              editModal.show();
            } else {
              Swal.fire(
                "Error",
                res.message || "Failed to fetch student details",
                "error",
              );
            }
          })
          .catch((err) => {
            console.error(err);
            Swal.fire("Error", "Unable to fetch data", "error");
          });
      });
    });

    // Trash Bin handlers
    const selectAllTrash = document.getElementById("selectAllTrash");
    const trashCheckboxes = document.querySelectorAll(".trash-select-checkbox");
    const bulkRestoreBtn = document.getElementById("bulkRestoreBtn");
    const bulkForceDeleteBtn = document.getElementById("bulkForceDeleteBtn");

    function updateTrashBulkButtons() {
      if (!bulkRestoreBtn || !bulkForceDeleteBtn) return;
      const checkedCount = document.querySelectorAll(
        ".trash-select-checkbox:checked",
      ).length;
      bulkRestoreBtn.disabled = checkedCount === 0;
      bulkForceDeleteBtn.disabled = checkedCount === 0;
    }

    if (selectAllTrash) {
      selectAllTrash.addEventListener("change", function () {
        trashCheckboxes.forEach((cb) => {
          cb.checked = this.checked;
        });
        updateTrashBulkButtons();
      });
    }

    trashCheckboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        if (selectAllTrash) {
          const totalCount = trashCheckboxes.length;
          const checkedCount = document.querySelectorAll(
            ".trash-select-checkbox:checked",
          ).length;
          selectAllTrash.checked = totalCount === checkedCount;
        }
        updateTrashBulkButtons();
      });
    });

    const trashSearchInput = document.getElementById("trashSearchInput");
    const trashTableBody = document.getElementById("trashTableBody");
    if (trashSearchInput && trashTableBody) {
      trashSearchInput.addEventListener("input", function () {
        const query = trashSearchInput.value.toLowerCase().trim();
        const rows = trashTableBody.querySelectorAll("tr");
        rows.forEach((row) => {
          const text = row.innerText.toLowerCase();
          row.style.display = !query || text.includes(query) ? "" : "none";
        });
      });
    }

    document.querySelectorAll(".confirm-restore-btn").forEach((btn) => {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        const form = this.closest("form");
        Swal.fire({
          icon: "question",
          title: "Restore Student?",
          text: "This student will be moved back to the Active Students Directory.",
          showCancelButton: true,
          confirmButtonText: "Yes, Restore",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#10b981",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) form.submit();
        });
      });
    });

    document.querySelectorAll(".confirm-force-delete-btn").forEach((btn) => {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        const form = this.closest("form");
        Swal.fire({
          icon: "warning",
          title: "Permanently Delete?",
          html: '<span style="color:#6b7280;font-size:14px;">This action <strong>cannot be undone</strong>. All qualifications, attendance records, and files will be erased forever.</span>',
          showCancelButton: true,
          confirmButtonText: "Delete Permanently",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#ef4444",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) form.submit();
        });
      });
    });

    if (bulkRestoreBtn) {
      bulkRestoreBtn.addEventListener("click", function () {
        const selected = [
          ...document.querySelectorAll(".trash-select-checkbox:checked"),
        ].map((cb) => cb.value);
        if (!selected.length) return;

        Swal.fire({
          icon: "question",
          title: `Restore ${selected.length} student(s)?`,
          text: "They will be moved back to the Active Students Directory.",
          showCancelButton: true,
          confirmButtonText: "Yes, Restore All",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#10b981",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) {
            const container = document.getElementById("bulkRestoreIds");
            if (container) {
              container.innerHTML = selected
                .map((id) => `<input type="hidden" name="ids[]" value="${id}">`)
                .join("");
              document.getElementById("bulkRestoreForm").submit();
            }
          }
        });
      });
    }

    if (bulkForceDeleteBtn) {
      bulkForceDeleteBtn.addEventListener("click", function () {
        const selected = [
          ...document.querySelectorAll(".trash-select-checkbox:checked"),
        ].map((cb) => cb.value);
        if (!selected.length) return;

        Swal.fire({
          icon: "warning",
          title: `Permanently delete ${selected.length} student(s)?`,
          html: '<span style="color:#6b7280;font-size:14px;">All selected records and files will be <strong>erased forever</strong>. This cannot be undone.</span>',
          showCancelButton: true,
          confirmButtonText: "Delete All Permanently",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#ef4444",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) {
            const container = document.getElementById("bulkForceDeleteIds");
            if (container) {
              container.innerHTML = selected
                .map((id) => `<input type="hidden" name="ids[]" value="${id}">`)
                .join("");
              document.getElementById("bulkForceDeleteForm").submit();
            }
          }
        });
      });
    }
  }

  // ==========================================
  // TEACHERS MODULE LOGIC
  // ==========================================
  const teacherMeta = document.getElementById("teacher-page-data");
  if (teacherMeta) {
    const BASE_URL = teacherMeta.dataset.baseUrl || "";
    const csrfToken = teacherMeta.dataset.csrfToken || "";
    const flashSuccess = teacherMeta.dataset.flashSuccess || "";
    const flashError = teacherMeta.dataset.flashError || "";

    // Success/Error SweetAlert Popups
    if (flashSuccess) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: flashSuccess,
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        customClass: {
          popup: "swal-toast-custom",
        },
      });
    }

    if (flashError) {
      Swal.fire({
        icon: "error",
        title: "Operation Error",
        text: flashError,
        confirmButtonColor: "#6366f1",
        customClass: {
          confirmButton: "swal-btn-custom",
        },
      });
    }

    // Search filter functionality
    const searchInput = document.getElementById("teacherSearchInput");
    const tableRows = document.querySelectorAll("#teachersTableBody tr");

    if (searchInput) {
      searchInput.addEventListener("input", function () {
        const val = this.value.toLowerCase().trim();
        tableRows.forEach((row) => {
          const text = row.innerText.toLowerCase();
          if (text.includes(val)) {
            row.style.display = "";
          } else {
            row.style.display = "none";
          }
        });
      });
    }

    // Select All and Bulk Delete buttons
    const selectAll = document.getElementById("selectAllCheckbox");
    const checkboxes = document.querySelectorAll(".teacher-select-checkbox");
    const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

    function toggleBulkDeleteBtn() {
      const checkedCount = document.querySelectorAll(
        ".teacher-select-checkbox:checked",
      ).length;
      if (bulkDeleteBtn) {
        bulkDeleteBtn.disabled = checkedCount === 0;
      }
    }

    if (selectAll) {
      selectAll.addEventListener("change", function () {
        checkboxes.forEach((cb) => {
          cb.checked = this.checked;
        });
        toggleBulkDeleteBtn();
      });
    }

    checkboxes.forEach((cb) => {
      cb.addEventListener("change", toggleBulkDeleteBtn);
    });

    if (bulkDeleteBtn) {
      bulkDeleteBtn.addEventListener("click", function () {
        Swal.fire({
          title: "Delete Selected Teachers?",
          text: "Are you sure you want to move all selected teachers to trash?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#DC2626",
          cancelButtonColor: "#64748B",
          confirmButtonText: "Yes, Move to Trash",
          cancelButtonText: "Cancel",
          customClass: {
            confirmButton: "swal-danger-btn-custom",
            cancelButton: "swal-cancel-btn-custom",
          },
        }).then((result) => {
          if (result.isConfirmed) {
            document.getElementById("bulkDeleteForm").submit();
          }
        });
      });
    }

    // Individual delete trigger
    const deleteButtons = document.querySelectorAll(".delete-teacher-btn");
    deleteButtons.forEach((btn) => {
      btn.addEventListener("click", function () {
        const id = this.dataset.id;
        const name = this.dataset.name;

        Swal.fire({
          title: "Delete Teacher?",
          text: `Are you sure you want to move the teacher profile for "${name}" to trash?`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#DC2626",
          cancelButtonColor: "#64748B",
          confirmButtonText: "Yes, Delete!",
          cancelButtonText: "Cancel",
          customClass: {
            confirmButton: "swal-danger-btn-custom",
            cancelButton: "swal-cancel-btn-custom",
          },
        }).then((result) => {
          if (result.isConfirmed) {
            const form = document.getElementById("deleteTeacherForm");
            const input = document.getElementById("delete_teacher_id");
            if (form && input) {
              input.value = id;
              form.submit();
            }
          }
        });
      });
    });

    // Helper escape HTML
    function escapeHtml(str) {
      if (!str) return "";
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // Dynamic Qualifications Rows
    const addQualBtn = document.getElementById("addQualificationRowBtn");
    const qualTbody = document.getElementById("qualificationsTbody");

    if (addQualBtn && qualTbody) {
      addQualBtn.addEventListener("click", function () {
        const newRow = document.createElement("tr");

        // Build options for years
        let yearOptions = '<option value="">Select</option>';
        const currentYear = new Date().getFullYear();
        for (let y = currentYear; y >= 1980; y--) {
          yearOptions += `<option value="${y}">${y}</option>`;
        }

        newRow.innerHTML = `
                <td>
                    <select name="qualification[]" class="form-control-admin py-1.5 fs-7">
                        <option value="">Select</option>
                        <option value="B.Ed">B.Ed</option>
                        <option value="M.Ed">M.Ed</option>
                        <option value="B.Sc">B.Sc</option>
                        <option value="M.Sc">M.Sc</option>
                        <option value="B.A">B.A</option>
                        <option value="M.A">M.A</option>
                        <option value="Ph.D">Ph.D</option>
                        <option value="Other">Other</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="college[]" class="form-control-admin py-1.5 fs-7" placeholder="College name">
                </td>
                <td>
                    <select name="passing_year[]" class="form-control-admin py-1.5 fs-7">
                        ${yearOptions}
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 remove-qual-row"><i class="ph-light ph-trash fs-6"></i></button>
                </td>
            `;

        qualTbody.appendChild(newRow);
        newRow
          .querySelector(".remove-qual-row")
          .addEventListener("click", function () {
            newRow.remove();
          });
      });
    }

    // Attach delete handler to initial rows
    document.querySelectorAll(".remove-qual-row").forEach((btn) => {
      btn.addEventListener("click", function () {
        this.closest("tr").remove();
      });
    });

    // Enabling Class Teacher toggle only when Class-Section checkbox is ticked
    const assignCheckboxes = document.querySelectorAll(
      ".class-assign-checkbox",
    );
    assignCheckboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        const row = this.closest("tr");
        const classTeacherCb = row.querySelector(".class-teacher-checkbox");
        if (classTeacherCb) {
          if (this.checked) {
            classTeacherCb.removeAttribute("disabled");
          } else {
            classTeacherCb.setAttribute("disabled", "disabled");
            classTeacherCb.checked = false;
          }
        }
      });
    });

    // Edit Qualifications Row builder
    function addEditQualificationRow(
      qualVal = "",
      collegeVal = "",
      yearVal = "",
    ) {
      const editQualTbody = document.getElementById("edit_qualificationsTbody");
      if (!editQualTbody) return;
      const newRow = document.createElement("tr");

      // Build options for years
      let yearOptions = '<option value="">Select</option>';
      const currentYear = new Date().getFullYear();
      for (let y = currentYear; y >= 1980; y--) {
        const selected = y == yearVal ? "selected" : "";
        yearOptions += `<option value="${y}" ${selected}>${y}</option>`;
      }

      const qualOptions = [
        "B.Ed",
        "M.Ed",
        "B.Sc",
        "M.Sc",
        "B.A",
        "M.A",
        "Ph.D",
        "Other",
      ]
        .map(
          (q) =>
            `<option value="${q}" ${q === qualVal ? "selected" : ""}>${q}</option>`,
        )
        .join("");

      newRow.innerHTML = `
            <td>
                <select name="qualification[]" class="form-control-admin py-1.5 fs-7">
                    <option value="">Select</option>
                    ${qualOptions}
                </select>
            </td>
            <td>
                <input type="text" name="college[]" class="form-control-admin py-1.5 fs-7" placeholder="College name" value="${escapeHtml(collegeVal)}">
            </td>
            <td>
                <select name="passing_year[]" class="form-control-admin py-1.5 fs-7">
                    ${yearOptions}
                </select>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 remove-qual-row"><i class="ph-light ph-trash fs-6"></i></button>
            </td>
        `;

      editQualTbody.appendChild(newRow);
      newRow
        .querySelector(".remove-qual-row")
        .addEventListener("click", function () {
          newRow.remove();
        });
    }

    // Dynamic Edit Qualifications Add Row Btn
    const editAddQualBtn = document.getElementById(
      "edit_addQualificationRowBtn",
    );
    if (editAddQualBtn) {
      editAddQualBtn.addEventListener("click", function () {
        addEditQualificationRow();
      });
    }

    // Edit Modal toggle Class Teacher Switch when Class-Section checkbox is ticked
    const editAssignCheckboxes = document.querySelectorAll(
      ".edit-class-assign-checkbox",
    );
    editAssignCheckboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        const row = this.closest("tr");
        const classTeacherCb = row.querySelector(
          ".edit-class-teacher-checkbox",
        );
        if (classTeacherCb) {
          if (this.checked) {
            classTeacherCb.removeAttribute("disabled");
          } else {
            classTeacherCb.setAttribute("disabled", "disabled");
            classTeacherCb.checked = false;
          }
        }
      });
    });

    // Click handler for edit button
    const editButtons = document.querySelectorAll(".edit-teacher-btn");
    editButtons.forEach((btn) => {
      btn.addEventListener("click", function () {
        const id = this.dataset.id;

        fetch(`index.php?get_teacher_details=1&id=${id}`)
          .then((res) => res.json())
          .then((res) => {
            if (res.success) {
              const data = res.data;

              document.getElementById("edit_teacher_id").value = data.id;
              document.getElementById("edit_staff_id").value =
                data.staff_id || "";
              document.getElementById("edit_joining_date").value =
                data.joining_date || "";
              document.getElementById("edit_first_name").value =
                data.first_name || "";
              document.getElementById("edit_last_name").value =
                data.last_name || "";
              document.getElementById("edit_email").value = data.email || "";
              document.getElementById("edit_mobile_no").value =
                data.mobile_no || "";
              document.getElementById("edit_alternate_mobile_no").value =
                data.alternate_mobile_no || "";
              document.getElementById("edit_whatsapp_no").value =
                data.whatsapp_no || "";

              if (data.gender) {
                const genderRadio = document.getElementById(
                  `edit_gender_${data.gender}`,
                );
                if (genderRadio) genderRadio.checked = true;
              } else {
                document
                  .querySelectorAll('input[name="gender"]')
                  .forEach((el) => (el.checked = false));
              }

              document.getElementById("edit_dob").value = data.dob || "";
              document.getElementById("edit_marital_status").value =
                data.marital_status || "";
              document.getElementById("edit_spouse_name").value =
                data.spouse_name || "";
              document.getElementById("edit_father_name").value =
                data.father_name || "";
              document.getElementById("edit_nationality").value =
                data.nationality || "INDIAN";
              document.getElementById("edit_religion").value =
                data.religion || "";
              document.getElementById("edit_category").value =
                data.category || "";

              document.getElementById("edit_last_org_name").value =
                data.last_org_name || "";
              document.getElementById("edit_last_job_position").value =
                data.last_job_position || "";
              document.getElementById("edit_exp_years").value =
                data.exp_years || 0;

              document.getElementById("edit_pincode").value =
                data.pincode || "";
              document.getElementById("edit_city").value = data.city || "";
              document.getElementById("edit_state").value = data.state || "";
              document.getElementById("edit_country").value =
                data.country || "India";
              document.getElementById("edit_address").value =
                data.address || "";

              document.getElementById("edit_bank_acc_holder").value =
                data.bank_acc_holder || "";
              document.getElementById("edit_bank_name").value =
                data.bank_name || "";
              document.getElementById("edit_bank_ifsc").value =
                data.bank_ifsc || "";
              document.getElementById("edit_bank_acc_no").value =
                data.bank_acc_no || "";
              document.getElementById("edit_pan_no").value = data.pan_no || "";
              document.getElementById("edit_pf_acc_no").value =
                data.pf_acc_no || "";
              document.getElementById("edit_uan_no").value = data.uan_no || "";
              document.getElementById("edit_aadhar_no").value =
                data.aadhar_no || "";

              document.getElementById("edit_designation").value =
                data.designation || "";
              document.getElementById("edit_department").value =
                data.department || "";

              document.getElementById("edit_username").value =
                data.u_name || "";
              document.getElementById("edit_password").value = "";
              document.getElementById("edit_biometric_code").value =
                data.biometric_code || "";

              const photoHelp = document.getElementById("edit_photo_help");
              if (data.photo) {
                photoHelp.innerHTML = `Current: <a href="${BASE_URL}${data.photo}" target="_blank">${data.photo.split("/").pop()}</a>`;
              } else {
                photoHelp.innerHTML =
                  "Allowed only JPEG, JPG, PNG, WEBP. Size max 10MB.";
              }

              const aadharHelp = document.getElementById("edit_aadhar_help");
              if (data.aadhar_file) {
                aadharHelp.innerHTML = `Current: <a href="${BASE_URL}${data.aadhar_file}" target="_blank">${data.aadhar_file.split("/").pop()}</a>`;
              } else {
                aadharHelp.innerHTML =
                  "Allowed only JPEG, JPG, PNG, WEBP & PDF. Size max 10MB.";
              }

              const sigHelp = document.getElementById("edit_signature_help");
              if (data.signature_file) {
                sigHelp.innerHTML = `Current: <a href="${BASE_URL}${data.signature_file}" target="_blank">${data.signature_file.split("/").pop()}</a>`;
              } else {
                sigHelp.innerHTML =
                  "Allowed only JPEG, JPG, PNG, WEBP. Size max 10MB.";
              }

              const editQualTbody = document.getElementById(
                "edit_qualificationsTbody",
              );
              editQualTbody.innerHTML = "";

              let qualifications = [];
              if (data.qualifications) {
                try {
                  qualifications = JSON.parse(data.qualifications);
                } catch (e) {}
              }

              if (qualifications.length === 0) {
                addEditQualificationRow();
              } else {
                qualifications.forEach((q) => {
                  addEditQualificationRow(
                    q.qualification,
                    q.college,
                    q.passing_year,
                  );
                });
              }

              document
                .querySelectorAll(".edit-class-assign-checkbox")
                .forEach((cb) => {
                  cb.checked = false;
                  const row = cb.closest("tr");
                  const ctCb = row.querySelector(
                    ".edit-class-teacher-checkbox",
                  );
                  if (ctCb) {
                    ctCb.checked = false;
                    ctCb.setAttribute("disabled", "disabled");
                  }
                });

              if (data.classes && data.classes.length > 0) {
                data.classes.forEach((c) => {
                  const key = `${c.class_id}_${c.section_id}`;
                  const cb = document.querySelector(
                    `.edit-class-assign-checkbox[value="${key}"]`,
                  );
                  if (cb) {
                    cb.checked = true;
                    const row = cb.closest("tr");
                    const ctCb = row.querySelector(
                      ".edit-class-teacher-checkbox",
                    );
                    if (ctCb) {
                      ctCb.removeAttribute("disabled");
                      if (c.is_class_teacher == 1) {
                        ctCb.checked = true;
                      }
                    }
                  }
                });
              }

              const editModal = new bootstrap.Modal(
                document.getElementById("editTeacherModal"),
              );
              editModal.show();
            } else {
              Swal.fire(
                "Error",
                res.message || "Could not fetch teacher details",
                "error",
              );
            }
          })
          .catch((err) => {
            console.error(err);
            Swal.fire("Error", "Failed to retrieve data", "error");
          });
      });
    });

    // --- TRASH BIN SPECIFIC HANDLERS ---
    const selectAllTrash = document.getElementById("selectAllTrash");
    const trashCheckboxes = document.querySelectorAll(".trash-select-checkbox");
    const bulkRestoreBtn = document.getElementById("bulkRestoreBtn");
    const bulkForceDeleteBtn = document.getElementById("bulkForceDeleteBtn");

    function updateTrashBulkButtons() {
      const checked = document.querySelectorAll(
        ".trash-select-checkbox:checked",
      ).length;
      if (bulkRestoreBtn) bulkRestoreBtn.disabled = checked === 0;
      if (bulkForceDeleteBtn) bulkForceDeleteBtn.disabled = checked === 0;
    }

    if (selectAllTrash) {
      selectAllTrash.addEventListener("change", function () {
        trashCheckboxes.forEach((cb) => {
          cb.checked = this.checked;
        });
        updateTrashBulkButtons();
      });
    }
    trashCheckboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        if (!this.checked && selectAllTrash) selectAllTrash.checked = false;
        updateTrashBulkButtons();
      });
    });

    const trashSearchInput = document.getElementById("trashSearchInput");
    if (trashSearchInput) {
      trashSearchInput.addEventListener("input", function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll("#trashTableBody tr").forEach((row) => {
          row.style.display =
            !q || row.dataset.search.includes(q) ? "" : "none";
        });
      });
    }

    // Restore single confirmation via delegated listener
    document.querySelectorAll(".confirm-restore-teacher-btn").forEach((btn) => {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        const form = this.closest("form");
        Swal.fire({
          icon: "question",
          title: "Restore Teacher?",
          text: "This teacher will be moved back to the Active Directory.",
          showCancelButton: true,
          confirmButtonText: "Yes, Restore",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#10b981",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) form.submit();
        });
      });
    });

    // Permanent delete single confirmation via delegated listener
    document
      .querySelectorAll(".confirm-force-delete-teacher-btn")
      .forEach((btn) => {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          const form = this.closest("form");
          Swal.fire({
            icon: "warning",
            title: "Permanently Delete?",
            html: '<span style="color:#6b7280;font-size:14px;">This action <strong>cannot be undone</strong>. All files and records will be erased forever.</span>',
            showCancelButton: true,
            confirmButtonText: "Delete Permanently",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#6b7280",
          }).then((result) => {
            if (result.isConfirmed) form.submit();
          });
        });
      });

    // Bulk Restore
    if (bulkRestoreBtn) {
      bulkRestoreBtn.addEventListener("click", function () {
        const selected = [
          ...document.querySelectorAll(".trash-select-checkbox:checked"),
        ].map((cb) => cb.value);
        if (!selected.length) return;

        Swal.fire({
          icon: "question",
          title: `Restore ${selected.length} teacher(s)?`,
          text: "They will be moved back to the Active Directory.",
          showCancelButton: true,
          confirmButtonText: "Yes, Restore All",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#10b981",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) {
            const container = document.getElementById("bulkRestoreIds");
            if (container) {
              container.innerHTML = selected
                .map((id) => `<input type="hidden" name="ids[]" value="${id}">`)
                .join("");
              document.getElementById("bulkRestoreForm").submit();
            }
          }
        });
      });
    }

    // Bulk Force Delete
    if (bulkForceDeleteBtn) {
      bulkForceDeleteBtn.addEventListener("click", function () {
        const selected = [
          ...document.querySelectorAll(".trash-select-checkbox:checked"),
        ].map((cb) => cb.value);
        if (!selected.length) return;

        Swal.fire({
          icon: "warning",
          title: `Permanently delete ${selected.length} teacher(s)?`,
          html: '<span style="color:#6b7280;font-size:14px;">All selected records and files will be <strong>erased forever</strong>. This cannot be undone.</span>',
          showCancelButton: true,
          confirmButtonText: "Delete All Permanently",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#ef4444",
          cancelButtonColor: "#6b7280",
        }).then((result) => {
          if (result.isConfirmed) {
            const container = document.getElementById("bulkForceDeleteIds");
            if (container) {
              container.innerHTML = selected
                .map((id) => `<input type="hidden" name="ids[]" value="${id}">`)
                .join("");
              document.getElementById("bulkForceDeleteForm").submit();
            }
          }
        });
      });
    }
  }

  // ==========================================
  // ADMIT CARDS DATATABLE CONTROLLER
  // ==========================================
  const admitcardsPane = document.getElementById("admitcards");
  if (admitcardsPane) {
    const rawData = admitcardsPane.getAttribute("data-admit-cards");
    let admitCards = [];
    try {
      admitCards = JSON.parse(rawData) || [];
    } catch (e) {
      console.error("Failed to parse admit cards data", e);
    }

    const searchInput = document.getElementById("admitCardsSearchInput");
    const lengthSelect = document.getElementById("admitCardsLengthSelect");
    const tableBody = document.querySelector("#admitCardsTable tbody");
    const infoDiv = document.getElementById("admitCardsInfo");
    const paginationDiv = document.getElementById("admitCardsPagination");

    let currentPage = 1;
    let pageSize = parseInt(lengthSelect?.value || "20");
    let searchQuery = "";

    function renderTable() {
      if (!tableBody) return;

      // Filter data
      const filtered = admitCards.filter((card) => {
        const query = searchQuery.toLowerCase();
        return (
          card.title.toLowerCase().includes(query) ||
          card.session.toLowerCase().includes(query) ||
          card.status.toLowerCase().includes(query) ||
          card.created_at.toLowerCase().includes(query)
        );
      });

      const totalItems = filtered.length;
      const totalPages = Math.ceil(totalItems / pageSize) || 1;

      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      const startIndex = (currentPage - 1) * pageSize;
      const endIndex = Math.min(startIndex + pageSize, totalItems);

      // Slice page items
      const pageItems = filtered.slice(startIndex, endIndex);

      // Render rows
      if (pageItems.length === 0) {
        tableBody.innerHTML = `
          <tr>
            <td colspan="6" class="text-center text-muted py-4">No matching records found</td>
          </tr>
        `;
      } else {
        tableBody.innerHTML = pageItems
          .map((card, index) => {
            const actualIndex = startIndex + index + 1;
            return `
              <tr style="border-bottom: 1px solid var(--color-border); vertical-align: middle;">
                <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary); font-weight: 500;">${actualIndex}</td>
                <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-primary); font-weight: 600;">${card.title}</td>
                <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);">${card.session}</td>
                <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);">${card.status}</td>
                <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);">${card.created_at}</td>
                <td style="padding: 8px 10px; border: 1px solid var(--color-border); text-align: center;">
                  <div class="d-flex justify-content-center gap-2">
                    <a href="#" class="btn btn-sm text-white d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; border-radius: 4px; background-color: var(--color-accent); border-color: var(--color-accent);" title="View Admit Card">
                      <i class="ph-bold ph-eye text-white fs-6"></i>
                    </a>
                    <a href="#" class="btn btn-sm text-white d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; border-radius: 4px; background-color: var(--brand-dark); border-color: var(--brand-dark);" title="Download Admit Card">
                      <i class="ph-bold ph-cloud-arrow-down text-white fs-6"></i>
                    </a>
                  </div>
                </td>
              </tr>
            `;
          })
          .join("");
      }

      // Update info string
      if (infoDiv) {
        if (totalItems === 0) {
          infoDiv.textContent = "Showing 0 to 0 of 0 entries";
        } else {
          infoDiv.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalItems} entries`;
        }
      }

      // Render pagination
      if (paginationDiv) {
        let paginationHtml = "";

        // Previous button
        const prevDisabled = currentPage === 1 ? "disabled" : "";
        paginationHtml += `
          <button type="button" class="btn btn-xxs btn-outline-secondary px-2 py-1 ${prevDisabled}" data-page="${currentPage - 1}" style="font-size: var(--text-xs); border-radius: 4px; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text-secondary); font-weight: 500;">Previous</button>
        `;

        // Page numbers
        for (let p = 1; p <= totalPages; p++) {
          const activeStyle =
            p === currentPage
              ? "background-color: var(--color-primary) !important; color: #fff !important; border-color: var(--color-primary) !important;"
              : "background: var(--color-surface); color: var(--color-text-secondary);";
          paginationHtml += `
            <button type="button" class="btn btn-xxs btn-outline-secondary px-2.5 py-1" data-page="${p}" style="font-size: var(--text-xs); border-radius: 4px; border: 1px solid var(--color-border); font-weight: 500; ${activeStyle}">${p}</button>
          `;
        }

        // Next button
        const nextDisabled = currentPage === totalPages ? "disabled" : "";
        paginationHtml += `
          <button type="button" class="btn btn-xxs btn-outline-secondary px-2 py-1 ${nextDisabled}" data-page="${currentPage + 1}" style="font-size: var(--text-xs); border-radius: 4px; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text-secondary); font-weight: 500;">Next</button>
        `;

        paginationDiv.innerHTML = paginationHtml;

        // Add pagination listeners
        paginationDiv.querySelectorAll("button").forEach((btn) => {
          btn.addEventListener("click", function () {
            if (this.classList.contains("disabled")) return;
            const targetPage = parseInt(this.getAttribute("data-page"));
            if (targetPage >= 1 && targetPage <= totalPages) {
              currentPage = targetPage;
              renderTable();
            }
          });
        });
      }
    }

    // Attach search listener
    if (searchInput) {
      searchInput.addEventListener("input", function () {
        searchQuery = this.value;
        currentPage = 1; // reset to first page on search
        renderTable();
      });
    }

    // Attach length change listener
    if (lengthSelect) {
      lengthSelect.addEventListener("change", function () {
        pageSize = parseInt(this.value);
        currentPage = 1;
        renderTable();
      });
    }

    // Initial render
    renderTable();
  }

  // ==========================================
  // SLIDE TABS SLIDER CONTROLS NAVIGATION
  // ==========================================
  const tabHeaders = document.querySelectorAll(".student-tabs-header");
  tabHeaders.forEach((header) => {
    const tabList = header.querySelector(".student-tabs, .teacher-tabs");
    if (!tabList) return;

    // Create left & right scroll buttons
    const leftBtn = document.createElement("button");
    leftBtn.type = "button";
    leftBtn.className = "tab-scroll-btn tab-scroll-left";
    leftBtn.innerHTML = '<i class="ph-light ph-caret-left"></i>';

    const rightBtn = document.createElement("button");
    rightBtn.type = "button";
    rightBtn.className = "tab-scroll-btn tab-scroll-right";
    rightBtn.innerHTML = '<i class="ph-light ph-caret-right"></i>';

    header.appendChild(leftBtn);
    header.appendChild(rightBtn);

    function updateScrollButtons() {
      const scrollLeft = tabList.scrollLeft;
      const scrollWidth = tabList.scrollWidth;
      const clientWidth = tabList.clientWidth;

      // Show left button if we are scrolled right
      if (scrollLeft > 5) {
        leftBtn.classList.add("visible");
      } else {
        leftBtn.classList.remove("visible");
      }

      // Show right button if there is more to scroll
      if (scrollLeft < scrollWidth - clientWidth - 5) {
        rightBtn.classList.add("visible");
      } else {
        rightBtn.classList.remove("visible");
      }
    }

    // Scroll amount on click
    const scrollAmount = 180;

    leftBtn.addEventListener("click", () => {
      tabList.scrollBy({ left: -scrollAmount, behavior: "smooth" });
    });

    rightBtn.addEventListener("click", () => {
      tabList.scrollBy({ left: scrollAmount, behavior: "smooth" });
    });

    // Listen to scroll events on tab list
    tabList.addEventListener("scroll", updateScrollButtons);

    // Listen to window resize events
    window.addEventListener("resize", updateScrollButtons);

    // Initial check (delay slightly to ensure rendering completes)
    setTimeout(updateScrollButtons, 300);
  });

  // ==================================================================
  // SCHOOL MANAGEMENT (SUPER ADMIN PORTAL) JS LOGIC
  // ==================================================================
  
  // ── Schools list page handlers ────────────────────────────────────
  const schoolsListEl = document.getElementById("schools-list-container");
  if (schoolsListEl) {
    const isRegistered = schoolsListEl.getAttribute("data-registered") === "1";
    const isDeleted = schoolsListEl.getAttribute("data-deleted") === "1";
    const isDeleteError = schoolsListEl.getAttribute("data-delete-error") === "1";
    const isInvalidRequest = schoolsListEl.getAttribute("data-invalid-request") === "1";

    if (isRegistered) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: "🎉 School registered successfully!",
        text: "The school admin can now log in using the credentials you set.",
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true,
        customClass: { popup: "swal-toast-custom" }
      });
    } else if (isDeleted) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: "🎉 School deleted successfully!",
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        customClass: { popup: "swal-toast-custom" }
      });
    } else if (isDeleteError) {
      Swal.fire({
        icon: "error",
        title: "Database Error",
        text: "Could not delete the school. Please try again.",
        confirmButtonColor: "#6366f1",
        confirmButtonText: "Okay",
        customClass: { confirmButton: "swal-btn-custom" }
      });
    } else if (isInvalidRequest) {
      Swal.fire({
        icon: "error",
        title: "Invalid Request",
        text: "Invalid request parameters or token validation failed.",
        confirmButtonColor: "#6366f1",
        confirmButtonText: "Okay",
        customClass: { confirmButton: "swal-btn-custom" }
      });
    }

    const schoolSearch = document.getElementById("schoolSearchInput");
    if (schoolSearch) {
      schoolSearch.addEventListener("keyup", function () {
        const query = this.value.toLowerCase();
        document.querySelectorAll(".table-premium tbody tr").forEach(function (row) {
          row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
        });
      });
    }
  }

  // ── School Create/Edit form handlers ─────────────────────────────
  const schoolForm = document.getElementById("schoolForm");
  if (schoolForm) {
    const toastType = schoolForm.getAttribute("data-toast-type");
    const toastMsg = schoolForm.getAttribute("data-toast-message");
    const csrfToken = schoolForm.getAttribute("data-csrf-token");

    if (toastType === "success") {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: toastMsg,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        customClass: { popup: "swal-toast-custom" }
      });
    } else if (toastType === "error") {
      Swal.fire({
        icon: "error",
        title: "Something went wrong",
        text: toastMsg,
        confirmButtonColor: "#6366f1",
        confirmButtonText: "Fix It",
        customClass: { confirmButton: "swal-btn-custom" }
      });
    }

    const submitBtn = document.getElementById("submitBtn");
    const submitIcon = document.getElementById("submitIcon");
    const submitLabel = document.getElementById("submitLabel");

    schoolForm.addEventListener("submit", function (e) {
      if (!schoolForm.checkValidity()) {
        schoolForm.reportValidity();
        e.preventDefault();
        return;
      }
      submitBtn.disabled = true;
      submitIcon.className = "ph-light ph-spinner-gap spin-icon";
      submitLabel.textContent = "Saving…";
    });

    document.querySelectorAll(".btn-toggle-password").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const targetId = btn.dataset.target;
        const input = document.getElementById(targetId);
        const eyeIcon = document.getElementById("eye_" + targetId);
        const isVisible = input.type === "text";
        input.type = isVisible ? "password" : "text";
        eyeIcon.className = isVisible ? "ph-light ph-eye" : "ph-light ph-eye-slash";
      });
    });

    const pwdInput = document.getElementById("admin_password");
    const strengthFill = document.getElementById("strengthFill");
    const strengthLabel = document.getElementById("strengthLabel");

    if (pwdInput) {
      pwdInput.addEventListener("input", function () {
        const val = pwdInput.value;
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const labels = ["Too short", "Weak", "Fair", "Good", "Strong"];
        const colors = ["#ef4444", "#f59e0b", "#f59e0b", "#10b981", "#6366f1"];
        const pct = [0, 25, 50, 75, 100];

        strengthFill.style.width = pct[score] + "%";
        strengthFill.style.backgroundColor = colors[score];
        strengthLabel.textContent = val.length ? labels[score] : "Enter a password to check strength.";
      });
    }

    const deleteBtn = document.getElementById("deleteSchoolBtn");
    if (deleteBtn) {
      deleteBtn.addEventListener("click", function () {
        const schoolName = deleteBtn.dataset.name;
        const schoolId = deleteBtn.dataset.id;
        Swal.fire({
          icon: "warning",
          title: "Delete \"" + schoolName + "\"?",
          html: "This will permanently remove the school and all associated data.<br><br><strong>This action cannot be undone.</strong>",
          showCancelButton: true,
          confirmButtonText: "Yes, Delete",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#ef4444",
          cancelButtonColor: "#e5e7eb",
          customClass: {
            cancelButton: "swal-cancel-btn-custom",
            confirmButton: "swal-danger-btn-custom"
          },
          reverseButtons: true
        }).then(function (result) {
          if (result.isConfirmed) {
            window.location.href = "schools-delete.php?id=" + schoolId + "&csrf=" + encodeURIComponent(csrfToken);
          }
        });
      });
    }

    const nameInput = document.getElementById("name");
    const slugInput = document.getElementById("slug");
    if (nameInput && slugInput) {
      nameInput.addEventListener("input", function () {
        if (slugInput.dataset.manual) return;
        slugInput.value = nameInput.value
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, "-")
          .replace(/^-+|-+$/g, "");
      });
      slugInput.addEventListener("input", function () {
        slugInput.dataset.manual = "1";
      });
    }

    const sessStart = document.getElementById("session_start_date");
    const sessEnd = document.getElementById("session_end_date");
    const sessName = document.getElementById("session_name");

    if (sessStart && sessEnd && sessName) {
      function autoPrefillSessionName() {
        if (sessName.dataset.manual) return;
        const startVal = sessStart.value;
        const endVal = sessEnd.value;
        if (startVal && endVal) {
          const startDate = new Date(startVal);
          const endDate = new Date(endVal);
          const startYear = startDate.getFullYear();
          const endYear = endDate.getFullYear();
          if (!isNaN(startYear) && !isNaN(endYear)) {
            if (startYear === endYear) {
              sessName.value = String(startYear);
            } else {
              const endYearShort = String(endYear).slice(-2);
              sessName.value = startYear + "-" + endYearShort;
            }
          }
        }
      }

      sessStart.addEventListener("change", autoPrefillSessionName);
      sessEnd.addEventListener("change", autoPrefillSessionName);
      sessName.addEventListener("input", function () {
        sessName.dataset.manual = "1";
      });
    }
  }

  // ==========================================
  // ADMISSIONS DATATABLE CONTROLLER
  // ==========================================
  const admissionsContainer = document.getElementById("admissions-module-container");
  if (admissionsContainer) {
    const rawData = admissionsContainer.getAttribute("data-students");
    let students = [];
    try {
      students = JSON.parse(rawData) || [];
    } catch (e) {
      console.error("Failed to parse students data", e);
    }

    const searchInput = document.getElementById("admissionsSearchInput");
    const lengthSelect = document.getElementById("admissionsLengthSelect");
    const tableBody = document.querySelector("#admissionsTable tbody");
    const infoDiv = document.getElementById("admissionsInfo");
    const paginationDiv = document.getElementById("admissionsPagination");

    let currentPage = 1;
    let pageSize = parseInt(lengthSelect?.value || "20");
    let searchQuery = "";

    function renderTable() {
      if (!tableBody) return;

      const filtered = students.filter((s) => {
        const query = searchQuery.toLowerCase().trim();
        if (!query) return true;
        const name = (s.first_name + " " + s.last_name).toLowerCase();
        const father = (s.father_name || "").toLowerCase();
        const admNo = ((s.admission_no_prefix || "") + s.admission_no).toLowerCase();
        const rollNo = (s.roll_no || "").toLowerCase();
        const cls = (s.class_name || "").toLowerCase();
        const sec = (s.section_name || "").toLowerCase();

        return (
          name.includes(query) ||
          father.includes(query) ||
          admNo.includes(query) ||
          rollNo.includes(query) ||
          cls.includes(query) ||
          sec.includes(query)
        );
      });

      const totalItems = filtered.length;
      const totalPages = Math.ceil(totalItems / pageSize) || 1;

      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      const startIndex = (currentPage - 1) * pageSize;
      const endIndex = Math.min(startIndex + pageSize, totalItems);
      const pageItems = filtered.slice(startIndex, endIndex);

      if (pageItems.length === 0) {
        tableBody.innerHTML = `
          <tr>
            <td colspan="8" class="text-center text-muted py-4">No matching records found</td>
          </tr>
        `;
      } else {
        tableBody.innerHTML = pageItems
          .map((s, index) => {
            const actualIndex = startIndex + index + 1;
            const fullName = `${s.first_name} ${s.last_name}`;
            const admissionNo = (s.admission_no_prefix || "") + s.admission_no;
            return `
              <tr>
                <td><span class="cell-counter">${actualIndex}</span></td>
                <td><span class="fw-bold text-dark">${admissionNo}</span></td>
                <td><span class="font-secondary">${s.roll_no || "—"}</span></td>
                <td><span class="fw-bold text-dark">${fullName}</span></td>
                <td><span>${s.father_name || "—"}</span></td>
                <td><span>${s.class_name || "—"}</span></td>
                <td><span>${s.section_name || "—"}</span></td>
                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    <a href="print.php?id=${s.id}&format=print" target="_blank" class="btn btn-sm btn-print d-flex align-items-center justify-content-center" title="Print Admission Form">
                      <i class="ph-bold ph-printer"></i>
                    </a>
                    <a href="print.php?id=${s.id}&format=download" target="_blank" class="btn btn-sm btn-download d-flex align-items-center justify-content-center" title="Download PDF Form">
                      <i class="ph-bold ph-cloud-arrow-down"></i>
                    </a>
                  </div>
                </td>
              </tr>
            `;
          })
          .join("");
      }

      const totalBadge = document.getElementById("admissionsTotalBadge");
      if (totalBadge) {
        const countSpan = totalBadge.querySelector(".count-num");
        if (countSpan) {
          countSpan.textContent = totalItems;
        }
      }

      if (infoDiv) {
        if (totalItems === 0) {
          infoDiv.textContent = "Showing 0 to 0 of 0 entries";
        } else {
          infoDiv.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalItems} entries`;
        }
      }

      if (paginationDiv) {
        let paginationHtml = "";
        const prevDisabled = currentPage === 1 ? "disabled" : "";
        paginationHtml += `
          <button type="button" class="btn btn-xxs btn-outline-secondary px-2 py-1 dt-pag-btn ${prevDisabled}" data-page="${currentPage - 1}">Previous</button>
        `;

        for (let p = 1; p <= totalPages; p++) {
          const activeClass = p === currentPage ? "active" : "";
          paginationHtml += `
            <button type="button" class="btn btn-xxs btn-outline-secondary px-2.5 py-1 dt-pag-btn ${activeClass}" data-page="${p}">${p}</button>
          `;
        }

        const nextDisabled = currentPage === totalPages ? "disabled" : "";
        paginationHtml += `
          <button type="button" class="btn btn-xxs btn-outline-secondary px-2 py-1 dt-pag-btn ${nextDisabled}" data-page="${currentPage + 1}">Next</button>
        `;

        paginationDiv.innerHTML = paginationHtml;

        paginationDiv.querySelectorAll("button").forEach((btn) => {
          btn.addEventListener("click", function () {
            if (this.classList.contains("disabled")) return;
            const targetPage = parseInt(this.getAttribute("data-page"));
            if (targetPage >= 1 && targetPage <= totalPages) {
              currentPage = targetPage;
              renderTable();
            }
          });
        });
      }
    }

    if (searchInput) {
      searchInput.addEventListener("input", function () {
        searchQuery = this.value;
        currentPage = 1;
        renderTable();
      });
    }

    if (lengthSelect) {
      lengthSelect.addEventListener("change", function () {
        pageSize = parseInt(this.value);
        currentPage = 1;
        renderTable();
      });
    }

    renderTable();
  }

  // ==========================================
  // ADMISSIONS SETTINGS CONTROLLER (Toggles check/uncheck all)
  // ==========================================
  const checkUncheckAll = document.getElementById("checkUncheckAll");
  if (checkUncheckAll) {
    const checkboxes = document.querySelectorAll(".admission-field-checkbox");
    
    checkUncheckAll.addEventListener("change", function () {
      const isChecked = this.checked;
      checkboxes.forEach((cb) => {
        cb.checked = isChecked;
      });
    });

    checkboxes.forEach((cb) => {
      cb.addEventListener("change", function () {
        if (!this.checked) {
          checkUncheckAll.checked = false;
        } else {
          // If all individual checkboxes are checked, check the "All" box
          const allChecked = [...checkboxes].every((item) => item.checked);
          if (allChecked) {
            checkUncheckAll.checked = true;
          }
        }
      });
    });
  }

  // Auto-print on admission print page load
  if (document.body.classList.contains("admission-print-view")) {
    window.print();
  }
});
