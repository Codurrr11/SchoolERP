<!-- App Thin Sidebar Navigation -->
<aside class="app-sidebar" id="appSidebar">

    <!-- Mobile Close & Logo Header -->
    <div class="sidebar-brand-header">
        <div class="d-flex align-items-center gap-2 py-2">
            <div class="sidebar-brand-logo">
                <i class="ph-light ph-graduation-cap fs-4 text-white"></i>
            </div>
            <span class="sidebar-brand-text">SchoolSaaS</span>
        </div>
        <button type="button" id="sidebarCloseBtn" aria-label="Close Navigation">
            <i class="ph-light ph-x fs-5"></i>
        </button>
    </div>
    <div class="sidebar-brand-divider"></div>

    <!-- Middle Section: Navigation Scrollable Stack -->
    <div class="sidebar-body">

        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        $current_uri = $_SERVER['PHP_SELF'];
        $role_name = $_SESSION['role_name'] ?? '';

        $is_dashboard    = ($current_page == 'index.php' && strpos($current_uri, '/modules/') === false);
        $is_sessions     = (strpos($current_uri, '/modules/school/sessions/') !== false);
        $is_teachers     = (strpos($current_uri, '/modules/school/teachers/') !== false);
        $is_students     = (strpos($current_uri, '/modules/school/students/') !== false);
        $is_parents      = (strpos($current_uri, '/modules/school/parents/') !== false);
        $is_fees         = (strpos($current_uri, '/modules/school/fees/') !== false);
        $is_expenses     = (strpos($current_uri, '/modules/school/expenses/') !== false);
        $is_bank_accounts = (strpos($current_uri, '/modules/school/bank-accounts/') !== false);
        $is_leads        = (strpos($current_uri, '/modules/school/leads/') !== false);
        $is_admissions   = (strpos($current_uri, '/modules/school/admissions/') !== false);

        if ($role_name === 'super_admin'):
        ?>
            <!-- Super Admin Menu -->

            <!-- 1. Dashboard -->
            <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-nav-item <?php echo ($is_dashboard) ? 'active' : ''; ?>" title="Dashboard">
                <i class="ph-light ph-house"></i>
                <span class="nav-label">Dashboard</span>
            </a>

            <!-- 2. School Management -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuSchools" class="sidebar-nav-item <?php echo ($current_page == 'schools.php' || $current_page == 'schools-edit.php') ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="false" title="School Management">
                    <i class="ph-light ph-building"></i>
                    <span class="nav-label">School Management</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($current_page == 'schools.php' || $current_page == 'schools-edit.php') ? 'show' : ''; ?>" id="submenuSchools">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/admin/schools.php" class="submenu-item <?php echo ($current_page == 'schools.php') ? 'active' : ''; ?>"><i class="ph-light ph-list"></i> School List</a>
                        <a href="<?php echo BASE_URL; ?>modules/admin/schools-edit.php" class="submenu-item <?php echo ($current_page == 'schools-edit.php') ? 'active' : ''; ?>"><i class="ph-light ph-plus-circle"></i> Register School</a>
                    </div>
                </div>
            </div>

            <!-- Spacer -->
            <div style="height: 1.5rem; width: 100%;"></div>

            <!-- Log Out -->
            <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-nav-item text-danger mb-3" title="Log Out">
                <i class="ph-light ph-sign-out"></i>
                <span class="nav-label">Log Out</span>
            </a>

        <?php else: ?>
            <!-- Default / School Admin Menu -->

            <!-- 1. Dashboard -->
            <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-nav-item <?php echo ($is_dashboard) ? 'active' : ''; ?>" title="Dashboard">
                <i class="ph-light ph-house"></i>
                <span class="nav-label">Dashboard</span>
            </a>

            <!-- 2. Leads / Inquiry -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuLeads" class="sidebar-nav-item <?php echo ($is_leads) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_leads) ? 'true' : 'false'; ?>" title="Leads & Inquiries">
                    <i class="ph-light ph-address-book"></i>
                    <span class="nav-label">Leads / Inquiry</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_leads) ? 'show' : ''; ?>" id="submenuLeads">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/leads/index.php" class="submenu-item <?php echo ($is_leads && $current_page == 'index.php') ? 'active' : ''; ?>"><i class="ph-light ph-users"></i> All Leads</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/leads/assigned.php" class="submenu-item <?php echo ($is_leads && $current_page == 'assigned.php') ? 'active' : ''; ?>"><i class="ph-light ph-user-check"></i> Lead Assigned</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/leads/sources.php" class="submenu-item <?php echo ($is_leads && $current_page == 'sources.php') ? 'active' : ''; ?>"><i class="ph-light ph-link"></i> Lead Sources</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/leads/status.php" class="submenu-item <?php echo ($is_leads && $current_page == 'status.php') ? 'active' : ''; ?>"><i class="ph-light ph-pulse"></i> Lead Status</a>
                    </div>
                </div>
            </div>

            <!-- 3. Students -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuStudents" class="sidebar-nav-item <?php echo ($is_students) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_students) ? 'true' : 'false'; ?>" title="Students">
                    <i class="ph-light ph-graduation-cap"></i>
                    <span class="nav-label">Students</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_students) ? 'show' : ''; ?>" id="submenuStudents">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/students/index.php" class="submenu-item <?php echo ($is_students && $current_page == 'index.php') ? 'active' : ''; ?>"><i class="ph-light ph-users"></i> All Students</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-list-checks"></i> Students Bulk Edit</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/students/trash.php" class="submenu-item <?php echo ($is_students && $current_page == 'trash.php') ? 'active' : ''; ?>"><i class="ph-light ph-trash"></i> Deleted Students</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/students/passed.php" class="submenu-item <?php echo ($is_students && $current_page == 'passed.php') ? 'active' : ''; ?>"><i class="ph-light ph-student"></i> Passed Students</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/students/dropped.php" class="submenu-item <?php echo ($is_students && $current_page == 'dropped.php') ? 'active' : ''; ?>"><i class="ph-light ph-user-minus"></i> Dropped Students</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/students/suspended.php" class="submenu-item <?php echo ($is_students && $current_page == 'suspended.php') ? 'active' : ''; ?>"><i class="ph-light ph-prohibit"></i> Suspended Students</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/students/migrations.php" class="submenu-item <?php echo ($is_students && $current_page == 'migrations.php') ? 'active' : ''; ?>"><i class="ph-light ph-arrows-clockwise"></i> Migration / Promotion</a>
                    </div>
                </div>
            </div>

            <!-- 4. Teachers -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuTeachers" class="sidebar-nav-item <?php echo ($is_teachers) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_teachers) ? 'true' : 'false'; ?>" title="Teachers">
                    <i class="ph-light ph-presentation-chart"></i>
                    <span class="nav-label">Teachers</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_teachers) ? 'show' : ''; ?>" id="submenuTeachers">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/teachers/index.php" class="submenu-item <?php echo ($is_teachers) ? 'active' : ''; ?>"><i class="ph-light ph-book-open"></i> Teacher Directory</a>
                    </div>
                </div>
            </div>

            <!-- 5. Parents / Siblings -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuParents" class="sidebar-nav-item <?php echo ($is_parents) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_parents) ? 'true' : 'false'; ?>" title="Parents / Siblings">
                    <i class="ph-light ph-heart"></i>
                    <span class="nav-label">Parents / Siblings</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_parents) ? 'show' : ''; ?>" id="submenuParents">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/parents/index.php" class="submenu-item <?php echo ($is_parents && $current_page == 'index.php') ? 'active' : ''; ?>"><i class="ph-light ph-users"></i> All Parents</a>
                    </div>
                </div>
            </div>

            <!-- 6. Fees -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuFees" class="sidebar-nav-item <?php echo ($is_fees) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_fees) ? 'true' : 'false'; ?>" title="Fees">
                    <i class="ph-light ph-coins"></i>
                    <span class="nav-label">Fees</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_fees) ? 'show' : ''; ?>" id="submenuFees">
                    <div class="sidebar-submenu">
                        <!-- Collect / Demand Bill -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/index.php" class="submenu-item <?php echo ($is_fees && $current_page == 'index.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-money"></i> Collect / Demand Bill
                        </a>

                        <!-- Fees Defaulters -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/defaulters.php" class="submenu-item <?php echo ($is_fees && $current_page == 'defaulters.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-arrow-clockwise"></i> Fees Defaulters
                        </a>

                        <!-- Collected Fees Log -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/collected-log.php" class="submenu-item <?php echo ($is_fees && $current_page == 'collected-log.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-receipt"></i> Collected Fees Log
                        </a>

                        <!-- Fees Percentage Report -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/percentage-report.php" class="submenu-item <?php echo ($is_fees && $current_page == 'percentage-report.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-chart-pie"></i> Fees Percentage Report
                        </a>

                        <!-- Fees Collection Report -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/collection-report.php" class="submenu-item <?php echo ($is_fees && $current_page == 'collection-report.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-chart-line"></i> Fees Collection Report
                        </a>

                        <!-- Daily Collection Report -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/daily-collection-report.php" class="submenu-item <?php echo ($is_fees && $current_page == 'daily-collection-report.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-pencil-simple-line"></i> Daily Collection Report
                        </a>

                        <!-- Monthly Fees Collection -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/monthly-fees-collection.php" class="submenu-item <?php echo ($is_fees && $current_page == 'monthly-fees-collection.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-desktop"></i> Monthly Fees Collection
                        </a>

                        <!-- Fees Structure [Setup] -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/fees-structure.php" class="submenu-item <?php echo ($is_fees && $current_page == 'fees-structure.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-tree-structure"></i> Fees Structure [Setup]
                        </a>

                        <!-- Fees Structure Report -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/fees-structure-report.php" class="submenu-item <?php echo ($is_fees && $current_page == 'fees-structure-report.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-chart-bar"></i> Fees Structure Report
                        </a>

                        <!-- Student Fees Structure Log -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/student-fees-structure.php" class="submenu-item <?php echo ($is_fees && $current_page == 'student-fees-structure.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-user-circle"></i> Student Fees Structure
                        </a>

                        <!-- Transport Structure [Setup] -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/transport-structure.php" class="submenu-item <?php echo ($is_fees && $current_page == 'transport-structure.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-bus"></i> Transport Structure [Setup]
                        </a>

                        <!-- Transport Structure Report -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/transport-structure-report.php" class="submenu-item <?php echo ($is_fees && $current_page == 'transport-structure-report.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-chart-bar"></i> Transport Structure Report
                        </a>

                        <!-- Online Fee Payments -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/online-payments.php" class="submenu-item <?php echo ($is_fees && $current_page == 'online-payments.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-credit-card"></i> Online Fee Payments
                        </a>

                        <!-- Fees Setting -->
                        <a href="<?php echo BASE_URL; ?>modules/school/fees/fees-settings.php" class="submenu-item <?php echo ($is_fees && $current_page == 'fees-settings.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-gear-six"></i> Fees Setting
                        </a>
                    </div>
                </div>
            </div>

            <!-- 7. Expenses -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuExpenses" class="sidebar-nav-item <?php echo ($is_expenses) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_expenses) ? 'true' : 'false'; ?>" title="Expenses">
                    <i class="ph-light ph-wallet"></i>
                    <span class="nav-label">Expenses</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_expenses) ? 'show' : ''; ?>" id="submenuExpenses">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/expenses/index.php" class="submenu-item <?php echo ($is_expenses && $current_page == 'index.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-book-open"></i> All Expenses
                        </a>
                        <!-- TODO: Add expense-categories setup page -->
                        <a href="#" class="submenu-item">
                            <i class="ph-light ph-gear-six"></i> Category Setup
                        </a>
                    </div>
                </div>
            </div>

            <!-- 8. Bank Accounts -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuBankAccounts" class="sidebar-nav-item <?php echo $is_bank_accounts ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo $is_bank_accounts ? 'true' : 'false'; ?>" title="Bank Accounts">
                    <i class="ph-light ph-bank"></i>
                    <span class="nav-label">Bank Accounts</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo $is_bank_accounts ? 'show' : ''; ?>" id="submenuBankAccounts">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/bank-accounts/payment-bank-accounts.php" class="submenu-item <?php echo ($is_bank_accounts && $current_page == 'payment-bank-accounts.php') ? 'active' : ''; ?>">
                            <i class="ph-light ph-credit-card"></i> Payment Bank Accounts
                        </a>
                    </div>
                </div>
            </div>

            <!-- 9. Academic Setup -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuAcademicSetup" class="sidebar-nav-item <?php echo ($is_sessions) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_sessions) ? 'true' : 'false'; ?>" title="Academic Setup">
                    <i class="ph-light ph-database"></i>
                    <span class="nav-label">Academic Setup</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_sessions) ? 'show' : ''; ?>" id="submenuAcademicSetup">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/sessions/index.php" class="submenu-item <?php echo ($is_sessions) ? 'active' : ''; ?>"><i class="ph-light ph-calendar"></i> Sessions</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-graduation-cap"></i> Class Setup</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-squares-four"></i> Section Setup</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-book-open"></i> Subject Setup</a>
                    </div>
                </div>
            </div>

            <!-- 10. Print Admission Forms -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuAdmission" class="sidebar-nav-item <?php echo ($is_admissions) ? 'active' : ''; ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo ($is_admissions) ? 'true' : 'false'; ?>" title="Print Admission Forms">
                    <i class="ph-light ph-notebook"></i>
                    <span class="nav-label">Print Admission Forms</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse <?php echo ($is_admissions) ? 'show' : ''; ?>" id="submenuAdmission">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>modules/school/admissions/index.php" class="submenu-item <?php echo ($is_admissions && $current_page == 'index.php') ? 'active' : ''; ?>"><i class="ph-light ph-file-xls"></i> All Admission Forms</a>
                        <a href="<?php echo BASE_URL; ?>modules/school/admissions/settings.php" class="submenu-item <?php echo ($is_admissions && $current_page == 'settings.php') ? 'active' : ''; ?>"><i class="ph-light ph-printer"></i> Admission Form Print Settings</a>
                    </div>
                </div>
            </div>

            <!-- 11. Tags -->
            <a href="#" class="sidebar-nav-item" title="Tags">
                <i class="ph-light ph-tag"></i>
                <span class="nav-label">Tags</span>
            </a>

            <!-- 12. Employees -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuEmployees" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Employees">
                    <i class="ph-light ph-users"></i>
                    <span class="nav-label">Employees</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuEmployees">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-users"></i> Employee Directory</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-coins"></i> Salary Payroll</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-briefcase"></i> Designations</a>
                    </div>
                </div>
            </div>

            <!-- 13. Mobile App Users -->
            <a href="#" class="sidebar-nav-item" title="Mobile App Users">
                <i class="ph-light ph-device-mobile"></i>
                <span class="nav-label">Mobile App Users</span>
            </a>

            <!-- 14. Attendance -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuAttendance" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Attendance">
                    <i class="ph-light ph-calendar-check"></i>
                    <span class="nav-label">Attendance</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuAttendance">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-calendar"></i> Daily Attendance</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-chart-bar"></i> Attendance Reports</a>
                    </div>
                </div>
            </div>

            <!-- 15. Leave -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuLeave" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Leave">
                    <i class="ph-light ph-scroll"></i>
                    <span class="nav-label">Leave</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuLeave">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-file-text"></i> Leave Applications</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-tag"></i> Leave Categories</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-scales"></i> Leave Balance</a>
                    </div>
                </div>
            </div>

            <!-- 16. Extra Income -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuIncome" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Extra Income">
                    <i class="ph-light ph-trend-up"></i>
                    <span class="nav-label">Extra Income</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuIncome">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-book-open"></i> Income Ledger</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-gear-six"></i> Category Setup</a>
                    </div>
                </div>
            </div>

            <!-- 17. Ledger & Day Book -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuLedger" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Ledger & Day Book">
                    <i class="ph-light ph-book-open"></i>
                    <span class="nav-label">Ledger & Day Book</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuLedger">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-notebook"></i> General Ledger</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-calendar-blank"></i> Day Book Summary</a>
                    </div>
                </div>
            </div>

            <!-- 18. Text SMS -->
            <a href="#" class="sidebar-nav-item" title="Text SMS">
                <i class="ph-light ph-chat-centered-text"></i>
                <span class="nav-label">Text SMS</span>
            </a>

            <!-- 19. Notice On App [FREE] -->
            <a href="#" class="sidebar-nav-item" title="Notice On App [FREE]">
                <i class="ph-light ph-megaphone"></i>
                <span class="nav-label">Notice On App [FREE]</span>
            </a>

            <!-- 20. WhatsApp [Meta API] (Pro) -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuWhatsapp" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="WhatsApp [Meta API]">
                    <i class="ph-light ph-whatsapp-logo"></i>
                    <span class="nav-label">WhatsApp [Meta API] <span class="badge-pro">Pro</span></span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuWhatsapp">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-megaphone"></i> WhatsApp Campaigns</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-sliders-horizontal"></i> Template Settings</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-clock-counter-clockwise"></i> Broadcast Logs</a>
                    </div>
                </div>
            </div>

            <!-- 21. Notifications -->
            <a href="#" class="sidebar-nav-item" title="Notifications">
                <i class="ph-light ph-bell"></i>
                <span class="nav-label">Notifications</span>
            </a>

            <!-- 22. ID Cards -->
            <a href="#" class="sidebar-nav-item" title="ID Cards">
                <i class="ph-light ph-identification-badge"></i>
                <span class="nav-label">ID Cards</span>
            </a>

            <!-- 23. House / Blocks -->
            <a href="#" class="sidebar-nav-item" title="House / Blocks">
                <i class="ph-light ph-building"></i>
                <span class="nav-label">House / Blocks</span>
            </a>

            <!-- 24. Lesson Plans -->
            <a href="#" class="sidebar-nav-item" title="Lesson Plans">
                <i class="ph-light ph-list-checks"></i>
                <span class="nav-label">Lesson Plans</span>
            </a>

            <!-- 25. Home Work -->
            <a href="#" class="sidebar-nav-item" title="Home Work">
                <i class="ph-light ph-bookmark-simple"></i>
                <span class="nav-label">Home Work</span>
            </a>

            <!-- 26. Gate Pass -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuGate" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Gate Pass">
                    <i class="ph-light ph-sign-in"></i>
                    <span class="nav-label">Gate Pass</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuGate">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-key"></i> Issue Gate Pass</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-clipboard-text"></i> Visitor Logs</a>
                    </div>
                </div>
            </div>

            <!-- 27. Admit Cards -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuAdmit" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Admit Cards">
                    <i class="ph-light ph-credit-card"></i>
                    <span class="nav-label">Admit Cards</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuAdmit">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-credit-card"></i> Generate Admit Cards</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-printer"></i> Print Settings</a>
                    </div>
                </div>
            </div>

            <!-- 28. Marksheets -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuMarksheets" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Marksheets">
                    <i class="ph-light ph-percent"></i>
                    <span class="nav-label">Marksheets</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuMarksheets">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-pencil-simple"></i> Enter Marks</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-file-xls"></i> Generate Marksheets</a>
                    </div>
                </div>
            </div>

            <!-- 29. Certificates -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuCertificates" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Certificates">
                    <i class="ph-light ph-medal"></i>
                    <span class="nav-label">Certificates</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuCertificates">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-medal"></i> Bonafide Certificates</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-shield"></i> Character Certificates</a>
                    </div>
                </div>
            </div>

            <!-- 30. Custom Forms -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuForms" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Custom Forms">
                    <i class="ph-light ph-file-text"></i>
                    <span class="nav-label">Custom Forms</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuForms">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-plus-circle"></i> Form Builder</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-clock-counter-clockwise"></i> Submission Logs</a>
                    </div>
                </div>
            </div>

            <!-- 31. Transfer Certificate -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuTC" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Transfer Certificate">
                    <i class="ph-light ph-arrows-left-right"></i>
                    <span class="nav-label">Transfer Certificate</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuTC">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-file-arrow-up"></i> Issue TC</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-book-open"></i> TC Register</a>
                    </div>
                </div>
            </div>

            <!-- 32. Study Material -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuMaterial" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Study Material">
                    <i class="ph-light ph-folder-open"></i>
                    <span class="nav-label">Study Material</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuMaterial">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-book-open"></i> Syllabus Setup</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-upload-simple"></i> Upload Materials</a>
                    </div>
                </div>
            </div>

            <!-- 33. Events/Gallery -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuEvents" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Events/Gallery">
                    <i class="ph-light ph-image"></i>
                    <span class="nav-label">Events/Gallery</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuEvents">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-calendar"></i> School Events</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-image"></i> Photo Gallery</a>
                    </div>
                </div>
            </div>

            <!-- 34. Academic Calendar -->
            <div class="w-100 sidebar-nav-dropdown">
                <a href="#submenuCalendar" class="sidebar-nav-item" data-bs-toggle="collapse" role="button" aria-expanded="false" title="Academic Calendar">
                    <i class="ph-light ph-calendar"></i>
                    <span class="nav-label">Academic Calendar</span>
                    <i class="ph-light ph-caret-down dropdown-arrow ms-auto nav-label"></i>
                </a>
                <div class="collapse" id="submenuCalendar">
                    <div class="sidebar-submenu">
                        <a href="#" class="submenu-item"><i class="ph-light ph-calendar-blank"></i> Term Dates</a>
                        <a href="#" class="submenu-item"><i class="ph-light ph-calendar-x"></i> Holiday List</a>
                    </div>
                </div>
            </div>

            <!-- 35. Complaints/Feedback -->
            <a href="#" class="sidebar-nav-item" title="Complaints / Feedback">
                <i class="ph-light ph-chats"></i>
                <span class="nav-label">Complaints/Feedback</span>
            </a>

            <!-- 36. Support Tickets -->
            <a href="#" class="sidebar-nav-item" title="Support Tickets">
                <i class="ph-light ph-ticket"></i>
                <span class="nav-label">Support Tickets</span>
            </a>

            <!-- 37. Settings -->
            <a href="#" class="sidebar-nav-item" title="Settings">
                <i class="ph-light ph-gear-six"></i>
                <span class="nav-label">Settings</span>
            </a>

            <!-- 38. Edit Profile -->
            <a href="#" class="sidebar-nav-item" title="Edit Profile">
                <i class="ph-light ph-user-gear"></i>
                <span class="nav-label">Edit Profile</span>
            </a>

            <!-- 39. Two Authentication -->
            <a href="#" class="sidebar-nav-item" title="Two Factor Authentication">
                <i class="ph-light ph-shield-check"></i>
                <span class="nav-label">Two Authentication</span>
            </a>

            <!-- Spacer -->
            <div style="height: 1.5rem; width: 100%;"></div>

            <!-- 40. Log Out -->
            <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-nav-item text-danger mb-3" title="Log Out">
                <i class="ph-light ph-sign-out"></i>
                <span class="nav-label">Log Out</span>
            </a>

        <?php endif; ?>

    </div>
</aside>
