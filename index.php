<?php
require_once 'config/helpers.php';
auth_check(); // Protect page

require_once 'config/db.php'; // Include database connection

// Include the page layout header and style components
require_once 'includes/header.php';

$role_name = $_SESSION['role_name'] ?? '';
?>

<!-- Welcome Banner Section -->
<div class="row align-items-center mb-4 g-3 welcome-header">
    <div class="col-12">
        <h2 class="mb-1 font-heading fw-extrabold">
            <?php
            if ($role_name === 'super_admin') {
                echo 'Platform Administration';
            } else {
                echo sanitize($_SESSION['school_name'] ?? 'School Dashboard');
            }
            ?>
        </h2>
        <p class="text-xs text-muted mb-0">
            Good morning, <?php echo sanitize($_SESSION['first_name'] ?? 'Admin'); ?>.
            <?php if ($role_name === 'super_admin'): ?>
                Welcome to the SaaS management portal. You can manage registered schools, plans, and check platform performance.
            <?php else: ?>
                Stay on top of your school's tasks, fee collections, and student status.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($role_name === 'super_admin'): ?>
    <?php
    // Fetch Super Admin Stats
    $school_count_stmt = $pdo->query("SELECT COUNT(*) FROM schools WHERE deleted_at IS NULL");
    $total_schools = $school_count_stmt->fetchColumn();

    $user_count_stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $user_count_stmt->fetchColumn();

    $active_schools_stmt = $pdo->query("SELECT COUNT(*) FROM schools WHERE status = 'active' AND deleted_at IS NULL");
    $active_schools = $active_schools_stmt->fetchColumn();
    ?>
    <!-- Super Admin Dashboard Content -->
    <div class="row g-4 mb-4">
        <!-- Card 1: Total Schools -->
        <div class="col-lg-4 col-md-6">
            <div class="card-premium">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-xs fw-semibold text-uppercase text-muted">Total Schools</span>
                        <div class="activity-icon-wrapper activity-icon-blue" style="width: 38px; height: 38px;">
                            <i class="ti ti-building fs-5"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1 font-heading"><?php echo $total_schools; ?></h2>
                    <span class="text-xxs text-muted">registered in platform</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Active Schools -->
        <div class="col-lg-4 col-md-6">
            <div class="card-premium">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-xs fw-semibold text-uppercase text-muted">Active Schools</span>
                        <div class="activity-icon-wrapper activity-icon-indigo" style="width: 38px; height: 38px;">
                            <i class="ti ti-shield-check fs-5"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1 font-heading"><?php echo $active_schools; ?></h2>
                    <span class="text-xxs text-muted">actively running tenants</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Platform Users -->
        <div class="col-lg-4 col-md-6">
            <div class="card-premium">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-xs fw-semibold text-uppercase text-muted">System Users</span>
                        <div class="activity-icon-wrapper activity-icon-amber" style="width: 38px; height: 38px;">
                            <i class="ti ti-users fs-5"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1 font-heading"><?php echo $total_users; ?></h2>
                    <span class="text-xxs text-muted">admins, teachers, & parents</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts Card -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card-premium">
                <div class="card-header">
                    <h6>Quick Platform Operations</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <a href="<?php echo BASE_URL; ?>modules/admin/schools-edit.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-2 text-decoration-none">
                                <i class="ti ti-circle-plus fs-3 text-primary"></i>
                                <span class="fw-semibold text-xs text-uppercase text-muted">Register School</span>
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a href="<?php echo BASE_URL; ?>modules/admin/schools.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-2 text-decoration-none">
                                <i class="ti ti-list fs-3 text-primary"></i>
                                <span class="fw-semibold text-xs text-uppercase text-muted">View School List</span>
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-secondary w-100 py-3 d-flex flex-column align-items-center gap-2 text-decoration-none">
                                <i class="ti ti-logout fs-3 text-danger"></i>
                                <span class="fw-semibold text-xs text-uppercase text-muted">Log Out System</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: 
    // Fetch School Admin Stats
    $school_id = $_SESSION['school_id'] ?? 1;

    // 1. Total Students
    $stmt_stud = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_stud->execute([':sid' => $school_id]);
    $total_students = (int)$stmt_stud->fetchColumn();

    // 2. Active Teachers
    $stmt_teach = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_teach->execute([':sid' => $school_id]);
    $total_teachers = (int)$stmt_teach->fetchColumn();

    // 3. Fees totals
    // Total Fee Assigned (Revenue)
    $stmt_assigned = $pdo->prepare("
        SELECT COALESCE(SUM(sfi.amount), 0) 
        FROM student_fee_items sfi 
        JOIN students s ON sfi.student_id = s.id 
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL
    ");
    $stmt_assigned->execute([':sid' => $school_id]);
    $total_fees_assigned = (float)$stmt_assigned->fetchColumn();

    // Total Fee Collected
    $stmt_collected = $pdo->prepare("
        SELECT COALESCE(SUM(sfi.paid_amount), 0) 
        FROM student_fee_items sfi 
        JOIN students s ON sfi.student_id = s.id 
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL
    ");
    $stmt_collected->execute([':sid' => $school_id]);
    $total_fees_collected = (float)$stmt_collected->fetchColumn();

    // Total Outstanding Dues
    $total_fees_outstanding = max(0.0, $total_fees_assigned - $total_fees_collected);

    // 4. Fee heads (Tuition, Transport, Hostel)
    $stmt_head = $pdo->prepare("
        SELECT sfi.fee_type, COALESCE(SUM(sfi.paid_amount), 0) AS collected
        FROM student_fee_items sfi
        JOIN students s ON sfi.student_id = s.id
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL
        GROUP BY sfi.fee_type
    ");
    $stmt_head->execute([':sid' => $school_id]);
    $fee_heads = [];
    while ($row = $stmt_head->fetch()) {
        $fee_heads[$row['fee_type']] = (float)$row['collected'];
    }
    $tuition_collected = $fee_heads['Tuition Fee'] ?? 0.0;
    $transport_collected = $fee_heads['Transport Fee'] ?? 0.0;
    $hostel_collected = $fee_heads['Hostel Fee'] ?? 0.0;

    // 5. Administrative Expenses Spent
    $stmt_exp = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM expenses 
        WHERE school_id = :sid AND deleted_at IS NULL
    ");
    $stmt_exp->execute([':sid' => $school_id]);
    $expenses_spent = (float)$stmt_exp->fetchColumn();
    $expenses_limit = 550000.00; // static limit
    $expense_percentage = $expenses_limit > 0 ? min(100.0, ($expenses_spent / $expenses_limit) * 100) : 0;

    // 6. Recent Fee Transactions
    $stmt_recent = $pdo->prepare("
        SELECT fp.*, s.first_name, s.last_name 
        FROM fee_payments fp 
        JOIN students s ON fp.student_id = s.id 
        WHERE fp.school_id = :sid 
        ORDER BY fp.id DESC 
        LIMIT 5
    ");
    $stmt_recent->execute([':sid' => $school_id]);
    $recent_payments = $stmt_recent->fetchAll();

    // 7. Dynamic Monthly chart data
    $stmt_chart_coll = $pdo->prepare("
        SELECT MONTH(payment_date) AS m, SUM(amount_paid) AS total 
        FROM fee_payments 
        WHERE school_id = :sid AND YEAR(payment_date) = YEAR(CURDATE())
        GROUP BY MONTH(payment_date)
    ");
    $stmt_chart_coll->execute([':sid' => $school_id]);
    $coll_by_month = array_fill(1, 12, 0.0);
    while ($row = $stmt_chart_coll->fetch()) {
        $coll_by_month[(int)$row['m']] = (float)$row['total'];
    }

    $stmt_chart_out = $pdo->prepare("
        SELECT MONTH(sfi.created_at) AS m, SUM(sfi.amount - sfi.paid_amount) AS total 
        FROM student_fee_items sfi
        JOIN students s ON sfi.student_id = s.id
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL AND YEAR(sfi.created_at) = YEAR(CURDATE())
        GROUP BY MONTH(sfi.created_at)
    ");
    $stmt_chart_out->execute([':sid' => $school_id]);
    $out_by_month = array_fill(1, 12, 0.0);
    while ($row = $stmt_chart_out->fetch()) {
        $out_by_month[(int)$row['m']] = (float)$row['total'];
    }

    $months_names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    $current_month_num = (int)date('m');
    $display_months_count = max(6, $current_month_num);
    
    $chart_months = array_slice($months_names, 0, $display_months_count);
    $chart_collected = array_slice(array_values($coll_by_month), 0, $display_months_count);
    $chart_outstanding = array_slice(array_values($out_by_month), 0, $display_months_count);
    ?>
    <div id="dashboard-data"
         data-chart-months='<?php echo json_encode($chart_months); ?>'
         data-chart-collected='<?php echo json_encode($chart_collected); ?>'
         data-chart-outstanding='<?php echo json_encode($chart_outstanding); ?>'
         data-expense-percentage="<?php echo $expense_percentage; ?>"
         style="display: none;">
    </div>
    <!-- Main Dashboard Glassmorphism Container with Background Blobs -->
    <div class="glass-bg-blob blob-primary"></div>
    <div class="glass-bg-blob blob-success"></div>

    <div class="dashboard-glass-container">
        
        <!-- Row 1: Horizontal Metric Cards -->
        <div class="row g-4 mb-4">
            <!-- Metric 1: Total Students (Accent Color Background) -->
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card metric-card-accent">
                    <div class="metric-top">
                        <span class="metric-label text-white-50">Total Students</span>
                        <div class="metric-icon-bg bg-white-20 text-white">
                            <i class="ti ti-users"></i>
                        </div>
                    </div>
                    <div class="metric-bottom-content">
                        <div class="metric-value font-secondary text-white"><?php echo number_format($total_students); ?></div>
                        <div class="metric-trend text-white-50"><i class="ti ti-arrow-up-right"></i> 5.2% this session</div>
                    </div>
                </div>
            </div>

            <!-- Metric 2: Active Teachers -->
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-top">
                        <span class="metric-label">Active Teachers</span>
                        <div class="metric-icon-bg">
                            <i class="ti ti-presentation"></i>
                        </div>
                    </div>
                    <div class="metric-bottom-content">
                        <div class="metric-value font-secondary"><?php echo number_format($total_teachers); ?></div>
                        <div class="metric-trend"><i class="ti ti-circle-check text-success"></i> Status: Active</div>
                    </div>
                </div>
            </div>

            <!-- Metric 3: Fee Collected -->
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-top">
                        <span class="metric-label">Collected Fees</span>
                        <div class="metric-icon-bg text-success">
                            <i class="ti ti-coins"></i>
                        </div>
                    </div>
                    <div class="metric-bottom-content">
                        <div class="metric-value font-secondary">₹<?php echo number_format($total_fees_collected); ?></div>
                        <div class="metric-trend text-success"><i class="ti ti-arrow-up-right"></i> 12% vs last month</div>
                    </div>
                </div>
            </div>

            <!-- Metric 4: Outstanding Fees -->
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-top">
                        <span class="metric-label">Outstanding Dues</span>
                        <div class="metric-icon-bg text-danger">
                            <i class="ti ti-alert-circle"></i>
                        </div>
                    </div>
                    <div class="metric-bottom-content">
                        <div class="metric-value font-secondary">₹<?php echo number_format($total_fees_outstanding); ?></div>
                        <div class="metric-trend text-danger"><i class="ti ti-clock"></i> Notices sent</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Fee Collection Chart & Total Fee Revenue / Heads -->
        <div class="row g-4 mb-4">
            <!-- Left Column: Fee Collection Chart (Wide 8-col grid) -->
            <div class="col-xl-8 col-lg-7">
                <div class="card-premium">
                    <div class="card-header">
                        <div>
                            <h6 class="font-primary fw-bold text-sm">Fee Collection</h6>
                            <span class="card-subtitle text-xxs text-muted">Collected vs Outstanding dues in current term</span>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color legend-collected"></span>
                                <span>Collected</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color legend-outstanding"></span>
                                <span>Outstanding</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="dashboard-chart-wrapper">
                            <!-- Chart.js canvas render viewport -->
                            <canvas id="incomeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Total Fee Revenue & Heads (Vertical 4-col grid) -->
            <div class="col-xl-4 col-lg-5">
                <div class="card-premium">
                    <div class="card-body d-flex flex-column justify-content-between h-100">
                        <!-- Balance Top Info -->
                        <div class="balance-top">
                            <div>
                                <span class="balance-label">Total Fee Revenue</span>
                                <div class="balance-value font-secondary mb-1">₹<?php echo number_format($total_fees_assigned, 2); ?></div>
                            </div>
                            <div class="currency-selector" title="Select Currency">
                                <!-- Custom Inline Indian Flag SVG -->
                                <svg class="currency-flag" viewBox="0 0 16 10" width="16" height="10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="16" height="3.33" fill="#FF9933" />
                                    <rect y="3.33" width="16" height="3.33" fill="#FFFFFF" />
                                    <rect y="6.66" width="16" height="3.34" fill="#138808" />
                                    <circle cx="8" cy="5" r="1.1" fill="none" stroke="#000080" stroke-width="0.3" />
                                    <line x1="8" y1="3.9" x2="8" y2="6.1" stroke="#000080" stroke-width="0.2" />
                                    <line x1="6.9" y1="5" x2="9.1" y2="5" stroke="#000080" stroke-width="0.2" />
                                </svg>
                                <span>INR</span>
                                <i class="ti ti-chevron-down ms-0.5"></i>
                            </div>
                        </div>

                        <!-- Balance Trend -->
                        <div class="mb-3">
                            <span class="trend-badge trend-up mb-0">
                                <i class="ti ti-arrow-up-right"></i> 7.8%
                            </span>
                            <span class="text-xxs text-muted ms-1">than last academic term</span>
                        </div>

                        <!-- Quick Action Buttons -->
                        <div class="balance-actions mb-4">
                            <button class="btn-balance-action btn-balance-action-primary me-2">
                                <i class="ti ti-coins"></i> Collect Fee
                            </button>
                            <button class="btn-balance-action btn-balance-action-secondary">
                                <i class="ti ti-circle-plus"></i> Record Expense
                            </button>
                        </div>

                        <!-- Wallets Subsection -->
                        <div>
                            <div class="wallets-title mb-2">
                                Fee Heads <span class="text-muted">| Primary collections</span>
                            </div>
                            <div class="wallets-list gap-2 d-flex flex-column">
                                <!-- Tuition Fee -->
                                <div class="wallet-item">
                                    <div class="wallet-left">
                                        <div class="wallet-flag-wrapper">
                                            <i class="ti ti-school text-primary"></i>
                                        </div>
                                        <div class="wallet-details">
                                            <span class="wallet-name">Tuition Fee</span>
                                            <span class="wallet-limit">Primary school collections</span>
                                        </div>
                                    </div>
                                    <div class="wallet-right">
                                        <span class="wallet-val">₹<?php echo number_format($tuition_collected, 2); ?></span>
                                        <span class="wallet-status active"><i class="ph-fill ph-circle wallet-status-dot"></i> Active</span>
                                    </div>
                                </div>

                                <!-- Transport Fee -->
                                <div class="wallet-item">
                                    <div class="wallet-left">
                                        <div class="wallet-flag-wrapper">
                                            <i class="ti ti-bus text-success"></i>
                                        </div>
                                        <div class="wallet-details">
                                            <span class="wallet-name">Transport Fee</span>
                                            <span class="wallet-limit">Bus route collections</span>
                                        </div>
                                    </div>
                                    <div class="wallet-right">
                                        <span class="wallet-val">₹<?php echo number_format($transport_collected, 2); ?></span>
                                        <span class="wallet-status active"><i class="ph-fill ph-circle wallet-status-dot"></i> Active</span>
                                    </div>
                                </div>

                                <!-- Hostel Fee -->
                                <div class="wallet-item">
                                    <div class="wallet-left">
                                        <div class="wallet-flag-wrapper">
                                            <i class="ti ti-home text-warning"></i>
                                        </div>
                                        <div class="wallet-details">
                                            <span class="wallet-name">Hostel Fee</span>
                                            <span class="wallet-limit">Residential block dues</span>
                                        </div>
                                    </div>
                                    <div class="wallet-right">
                                        <span class="wallet-val">₹<?php echo number_format($hostel_collected, 2); ?></span>
                                        <span class="wallet-status active"><i class="ph-fill ph-circle wallet-status-dot"></i> Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Recent Fee Transactions & Expenses/Cards Stack -->
        <div class="row g-4">
            <!-- Left Column: Recent Fee Transactions Table (Wide 8-col grid) -->
            <div class="col-xl-8 col-lg-7">
                <div class="card-premium">
                    <div class="card-header">
                        <h6 class="font-primary fw-bold text-sm">Recent Fee Transactions</h6>
                        <div class="table-header-controls">
                            <!-- Rounded Filter & Search Controls -->
                            <div class="table-search-box">
                                <i class="ti ti-search"></i>
                                <input type="text" placeholder="Search receipt..." id="activitiesSearchInput">
                            </div>
                            <button class="btn-filter-table">
                                <i class="ti ti-adjustments-horizontal"></i> Filter
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table-premium mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="col-checkbox-width">
                                            <input type="checkbox" class="table-checkbox">
                                        </th>
                                        <th>Receipt No</th>
                                        <th>Student / Activity</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date & Time</th>
                                        <th class="col-action-width"></th>
                                    </tr>
                                </thead>
                                <tbody id="activitiesTableBody">
                                <?php if (empty($recent_payments)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted p-4 text-xs">No recent transactions.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_payments as $rp): ?>
                                        <tr>
                                            <td><input type="checkbox" class="table-checkbox"></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($rp['transaction_id'] ?? 'REC_' . sprintf('%06d', $rp['id'])); ?></td>
                                            <td>
                                                <div class="activity-cell">
                                                    <div class="activity-icon-wrapper activity-icon-blue">
                                                        <i class="ti ti-school"></i>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($rp['first_name'] . ' ' . $rp['last_name']); ?> (Fee Paid)</span>
                                                </div>
                                            </td>
                                            <td class="fw-semibold">₹<?php echo number_format($rp['amount_paid']); ?></td>
                                            <td>
                                                <span class="status-pill status-completed">
                                                    <span class="status-dot"></span>Completed
                                                </span>
                                            </td>
                                            <td class="text-muted"><?php echo date('d M, Y h:i A', strtotime($rp['payment_date'])); ?></td>
                                            <td>
                                                <button class="btn-row-action" title="More Options">
                                                    <i class="ti ti-dots"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Administrative Expenses & Cards (Vertical 4-col grid) -->
            <div class="col-xl-4 col-lg-5 d-flex flex-column gap-4">
                <!-- Card D: Spending Limit Progress -->
                <div class="card-premium limit-card p-4">
                    <div class="limit-header">
                        <span class="font-secondary fw-semibold text-xs text-muted text-uppercase">Administrative Expenses</span>
                    </div>
                    <!-- Progress Limit Met -->
                    <div class="limit-header my-2 d-flex justify-content-between align-items-baseline">
                        <span class="limit-spent fw-bold text-lg">₹<?php echo number_format($expenses_spent); ?> <span class="text-xxs text-muted fw-normal">spent of</span></span>
                        <span class="limit-total text-sm fw-semibold text-muted">₹<?php echo number_format($expenses_limit); ?></span>
                    </div>
                    <div class="limit-progress-bar">
                        <div class="limit-progress-fill animate-bar admin-expense-progress-fill"></div>
                    </div>
                </div>

                <!-- Card E: School Bank Cards Deck -->
                <div class="card-premium p-4 flex-grow-1 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-primary fw-bold mb-0 text-sm">School Cards</h6>
                        <button class="btn-add-card"><i class="ti ti-plus"></i> Register card</button>
                    </div>

                    <div class="cards-deck-wrapper d-flex justify-content-center w-100" style="padding-right: 50px;">
                        <div class="cards-deck">
                            <!-- Card 1: Main A/C Card -->
                            <div class="cc-card cc-card-black" id="cardPrimary">
                                <div class="cc-top">
                                    <div class="cc-chip-logo">
                                        <div class="cc-chip"></div>
                                        <i class="ti ti-wifi cc-wifi"></i>
                                    </div>
                                    <span class="cc-status-badge">Main A/C</span>
                                </div>
                                <div class="cc-number">**** **** **** 6782</div>
                                <div class="cc-bottom">
                                    <div class="cc-details">
                                        <div>
                                            <span class="cc-meta-label">EXP</span>
                                            <span class="cc-meta-val">09/29</span>
                                        </div>
                                        <div>
                                            <span class="cc-meta-label">CVV</span>
                                            <span class="cc-meta-val">611</span>
                                        </div>
                                    </div>
                                    <div class="cc-brand-logo">
                                        <div class="circle-logo">
                                            <span class="circle-red"></span>
                                            <span class="circle-yellow"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Sports & Culture Fund Card -->
                            <div class="cc-card cc-card-blue" id="cardSecondary">
                                <div class="cc-top">
                                    <div class="cc-chip-logo">
                                        <div class="cc-chip"></div>
                                        <i class="ti ti-wifi cc-wifi"></i>
                                    </div>
                                    <span class="cc-status-badge">Sports Fund</span>
                                </div>
                                <div class="cc-number">**** **** **** 4356</div>
                                <div class="cc-bottom">
                                    <div class="cc-details">
                                        <div>
                                            <span class="cc-meta-label">EXP</span>
                                            <span class="cc-meta-val">12/28</span>
                                        </div>
                                        <div>
                                            <span class="cc-meta-label">CVV</span>
                                            <span class="cc-meta-val">422</span>
                                        </div>
                                    </div>
                                    <div class="cc-brand-logo">
                                        <div class="circle-logo">
                                            <span class="circle-red"></span>
                                            <span class="circle-yellow"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
<?php endif; ?>

<?php
// Include the page layout footer tag
require_once 'includes/footer.php';
?>
