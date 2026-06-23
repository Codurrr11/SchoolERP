<!-- App Top Header Bar -->
<header class="app-topbar">

    <!-- Left Section: Mobile Toggle & Brand Logo -->
    <div class="d-flex align-items-center">
        <!-- Sidebar Drawer Toggle for Mobile (below 992px) and Collapsible Sidebar Toggle for Desktop -->
        <button type="button" id="sidebarToggleBtn" aria-label="Toggle Sidebar Navigation">
            <i class="ti ti-menu-2 fs-4"></i>
        </button>

        <!-- Brand Branding -->
        <a href="<?php echo BASE_URL; ?>index.php" class="topbar-brand ms-2 text-decoration-none">
            <div class="brand-logo-wrapper">
                <i class="ti ti-school fs-4 text-white"></i>
            </div>
            <span class="brand-text">SchoolSaaS</span>
        </a>

        <!-- Academic Session Selector -->
        <?php if (!empty($_SESSION['school_id'])): ?>
            <?php
            $sessions_list = get_academic_sessions($_SESSION['school_id']);
            $current_sess_id = $_SESSION['academic_session_id'] ?? null;
            $current_sess_name = $_SESSION['academic_session_name'] ?? 'No Session';
            ?>
            <div class="dropdown ms-2 ms-sm-3">
                <button class="btn btn-session-selector dropdown-toggle" type="button" id="sessionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-calendar-check"></i>
                    <span>Session: <?php echo sanitize($current_sess_name); ?></span>
                </button>
                <ul class="dropdown-menu shadow-md border-0 mt-2" aria-labelledby="sessionDropdown">
                    <?php if (empty($sessions_list)): ?>
                        <li><a class="dropdown-item text-xs py-2 disabled" href="#">No Sessions Found</a></li>
                    <?php else: ?>
                        <?php foreach ($sessions_list as $s): ?>
                            <?php
                            $isActive = ($s['id'] == $current_sess_id);
                            // Rebuild URL with new query parameter
                            $url = strtok($_SERVER["REQUEST_URI"], '?');
                            $queryParams = $_GET;
                            $queryParams['change_session_id'] = $s['id'];
                            $targetUrl = $url . '?' . http_build_query($queryParams);
                            ?>
                            <li>
                                <a class="dropdown-item text-xs py-2 <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo sanitize($targetUrl); ?>">
                                    Session: <?php echo sanitize($s['name']); ?> <?php echo $s['is_current'] ? '(Active)' : ''; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        <?php else: ?>
            <!-- Static placeholder or no session selector for Platform Admin -->
            <div class="ms-2 ms-sm-3 d-none d-md-block">
                <span class="badge bg-secondary text-xs py-2 px-3">Platform Admin</span>
            </div>
        <?php endif; ?>
    </div>


    <!-- Center Section: Responsive Navigation Menu -->
    <div class="topbar-menu-container">
        <!-- Desktop Horizontal Pill Menu (shown on lg screens) -->
        <div class="topbar-menu-pill d-none d-lg-flex">
            <a href="<?php echo BASE_URL; ?>index.php" class="menu-item active">Overview</a>
            <a href="#" class="menu-item">Activity</a>
            <a href="#" class="menu-item">Manage</a>
            <a href="#" class="menu-item">Program</a>
            <a href="#" class="menu-item">Account</a>
            <a href="#" class="menu-item">Reports</a>
        </div>
    </div>

    <!-- Right Section: General Actions & User Profile Dropdown -->
    <div class="topbar-actions">
        <!-- Search Action Button -->
        <button type="button" class="btn-topbar-action d-none d-md-flex" id="mobileSearchToggleBtn" aria-label="Search Records" title="Search (Ctrl + K)">
            <i class="ti ti-search"></i>
        </button>

        <!-- Notification Bell (with notification dot) -->
        <button type="button" class="btn-topbar-action position-relative d-none d-md-flex" aria-label="Notifications" title="Notifications">
            <i class="ti ti-bell"></i>
            <span class="badge-dot"></span>
        </button>

        <!-- Settings Button -->
        <button type="button" class="btn-topbar-action d-none d-md-flex" aria-label="Settings" title="Settings">
            <i class="ti ti-settings"></i>
        </button>

        <!-- Theme Switch Toggle -->
        <div class="topbar-theme-toggle ms-1 me-1 d-none d-md-flex">
            <button type="button" class="theme-btn active" id="themeSunBtn" title="Light Mode">
                <i class="ti ti-sun"></i>
            </button>
            <button type="button" class="theme-btn" id="themeMoonBtn" title="Dark Mode">
                <i class="ti ti-moon"></i>
            </button>
        </div>

        <!-- Profile Widget -->
        <div class="dropdown ms-2">
            <button class="btn-profile-dropdown dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100" alt="Alex Mercer Profile" class="profile-avatar">
                <div class="profile-info d-none d-md-block">
                    <span class="profile-name d-block"><?php echo sanitize(($_SESSION['first_name'] ?? 'Guest') . ' ' . ($_SESSION['last_name'] ?? '')); ?></span>
                    <span class="profile-email d-block"><?php echo sanitize($_SESSION['email'] ?? 'guest@school.com'); ?></span>
                </div>
                <i class="ti ti-chevron-down chevron-icon d-none d-md-inline-block"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-md border-0 mt-2 py-2" aria-labelledby="profileDropdown">
                <li><a class="dropdown-item text-xs py-2 d-flex align-items-center gap-2" href="#"><i class="ti ti-user text-muted fs-6"></i> My Profile</a></li>
                <li><a class="dropdown-item text-xs py-2 d-flex align-items-center gap-2" href="#"><i class="ti ti-settings text-muted fs-6"></i> Account Settings</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item text-xs py-2 d-flex align-items-center gap-2 text-danger" href="<?php echo BASE_URL; ?>logout.php"><i class="ti ti-logout fs-6"></i> Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>