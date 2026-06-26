<?php
require_once 'config/helpers.php';
auth_check(); // Protect page

require_once 'config/db.php'; // Include database connection

// Include the page layout header and style components
require_once 'includes/header.php';

$role_name = $_SESSION['role_name'] ?? '';
?>

<?php if ($role_name === 'super_admin'): ?>
    <!-- Welcome Banner Section -->
    <div class="row align-items-center mb-4 g-3 welcome-header">
        <div class="col-12">
            <h2 class="mb-1 font-heading fw-extrabold">
                Platform Administration
            </h2>
            <p class="text-xs text-muted mb-0">
                Good morning, <?php echo sanitize($_SESSION['first_name'] ?? 'Admin'); ?>.
                Welcome to the SaaS management portal. You can manage registered schools, plans, and check platform performance.
            </p>
        </div>
    </div>
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
    // ─── School Admin Dashboard Data ───────────────────────────────────────────
    $school_id = (int)($_SESSION['school_id'] ?? 1);
    $user_id   = (int)($_SESSION['user_id']   ?? 0);

    // Time-aware greeting
    $hour = (int)date('G'); // 0-23
    if ($hour < 12)      { $greeting = 'Good morning'; }
    elseif ($hour < 17)  { $greeting = 'Good afternoon'; }
    else                 { $greeting = 'Good evening'; }

    // 1. Total Students
    $stmt_stud = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_stud->execute([':sid' => $school_id]);
    $total_students = (int)$stmt_stud->fetchColumn();

    // 1b. Active Students
    $stmt_active_stud = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :sid AND status = 'active' AND deleted_at IS NULL");
    $stmt_active_stud->execute([':sid' => $school_id]);
    $active_students = (int)$stmt_active_stud->fetchColumn();

    // 2. Active Teachers
    $stmt_teach = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_teach->execute([':sid' => $school_id]);
    $total_teachers = (int)$stmt_teach->fetchColumn();

    // 2b. Total Parents
    $stmt_parents = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_parents->execute([':sid' => $school_id]);
    $total_parents = (int)$stmt_parents->fetchColumn();

    // 2c. Total Leads / Inquiries
    $stmt_leads = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_leads->execute([':sid' => $school_id]);
    $total_leads = (int)$stmt_leads->fetchColumn();

    // 2d. New leads this week
    $stmt_leads_week = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE school_id = :sid AND deleted_at IS NULL AND WEEK(created_at) = WEEK(NOW())");
    $stmt_leads_week->execute([':sid' => $school_id]);
    $new_leads_week = (int)$stmt_leads_week->fetchColumn();

    // 3. Fees totals
    $stmt_assigned = $pdo->prepare("
        SELECT COALESCE(SUM(sfi.amount), 0)
        FROM student_fee_items sfi
        JOIN students s ON sfi.student_id = s.id
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL
    ");
    $stmt_assigned->execute([':sid' => $school_id]);
    $total_fees_assigned = (float)$stmt_assigned->fetchColumn();

    $stmt_collected = $pdo->prepare("
        SELECT COALESCE(SUM(sfi.paid_amount), 0)
        FROM student_fee_items sfi
        JOIN students s ON sfi.student_id = s.id
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL
    ");
    $stmt_collected->execute([':sid' => $school_id]);
    $total_fees_collected = (float)$stmt_collected->fetchColumn();

    $total_fees_outstanding = max(0.0, $total_fees_assigned - $total_fees_collected);
    $fee_target_percent = $total_fees_assigned > 0 ? round(($total_fees_collected / $total_fees_assigned) * 100, 1) : 0;

    // Fee defaulters count
    $stmt_def = $pdo->prepare("
        SELECT COUNT(DISTINCT sfi.student_id)
        FROM student_fee_items sfi
        JOIN students s ON sfi.student_id = s.id
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND sfi.paid_amount < sfi.amount AND s.deleted_at IS NULL
    ");
    $stmt_def->execute([':sid' => $school_id]);
    $total_defaulters = (int)$stmt_def->fetchColumn();

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
    $tuition_collected   = $fee_heads['Tuition Fee']   ?? 0.0;
    $transport_collected = $fee_heads['Transport Fee'] ?? 0.0;
    $hostel_collected    = $fee_heads['Hostel Fee']    ?? 0.0;

    // 5. Total Expenses
    $stmt_exp = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE school_id = :sid AND deleted_at IS NULL");
    $stmt_exp->execute([':sid' => $school_id]);
    $expenses_spent = (float)$stmt_exp->fetchColumn();
    $expenses_limit = 550000.00;
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

    // 6b. Recent Leads
    $stmt_recent_leads = $pdo->prepare("
        SELECT l.*, c.class_name
        FROM leads l
        LEFT JOIN classes c ON l.class_id = c.id
        WHERE l.school_id = :sid AND l.deleted_at IS NULL
        ORDER BY l.id DESC
        LIMIT 5
    ");
    $stmt_recent_leads->execute([':sid' => $school_id]);
    $recent_leads = $stmt_recent_leads->fetchAll();

    // 6c. Recent Expenses
    $stmt_recent_expenses = $pdo->prepare("
        SELECT * FROM expenses
        WHERE school_id = :sid AND deleted_at IS NULL
        ORDER BY id DESC
        LIMIT 5
    ");
    $stmt_recent_expenses->execute([':sid' => $school_id]);
    $recent_expenses = $stmt_recent_expenses->fetchAll();

    // 6d. Leads Status Breakdown
    $stmt_lead_status = $pdo->prepare("
        SELECT status, COUNT(*) AS count
        FROM leads
        WHERE school_id = :sid AND deleted_at IS NULL
        GROUP BY status
    ");
    $stmt_lead_status->execute([':sid' => $school_id]);
    $lead_statuses_data = [];
    while ($row = $stmt_lead_status->fetch()) {
        $lead_statuses_data[$row['status'] ?: 'Interested'] = (int)$row['count'];
    }
    $lead_pipeline_labels = array_keys($lead_statuses_data);
    $lead_pipeline_counts = array_values($lead_statuses_data);

    // 6e. Lead Sources Breakdown
    $stmt_lead_source = $pdo->prepare("
        SELECT COALESCE(source, 'Direct') AS source_name, COUNT(*) AS count
        FROM leads
        WHERE school_id = :sid AND deleted_at IS NULL
        GROUP BY source
    ");
    $stmt_lead_source->execute([':sid' => $school_id]);
    $lead_sources_data = [];
    while ($row = $stmt_lead_source->fetch()) {
        $lead_sources_data[$row['source_name']] = (int)$row['count'];
    }

    // 6f. Student Status Breakdown
    $stmt_stud_status = $pdo->prepare("
        SELECT status, COUNT(*) AS count
        FROM students
        WHERE school_id = :sid AND deleted_at IS NULL
        GROUP BY status
    ");
    $stmt_stud_status->execute([':sid' => $school_id]);
    $student_statuses = ['active' => 0, 'suspended' => 0, 'passed' => 0, 'dropped' => 0, 'inactive' => 0];
    while ($row = $stmt_stud_status->fetch()) {
        $student_statuses[$row['status']] = (int)$row['count'];
    }

    // 6g. RTE vs Non-RTE
    $stmt_rte = $pdo->prepare("SELECT is_rte, COUNT(*) AS count FROM students WHERE school_id = :sid AND deleted_at IS NULL GROUP BY is_rte");
    $stmt_rte->execute([':sid' => $school_id]);
    $rte_count = 0; $non_rte_count = 0;
    while ($row = $stmt_rte->fetch()) {
        if ($row['is_rte'] === 'yes') { $rte_count = (int)$row['count']; }
        else { $non_rte_count = (int)$row['count']; }
    }

    // 6h. Students by class (top 8 by sort_order)
    $stmt_by_class = $pdo->prepare("
        SELECT c.class_name, c.roman_number, COUNT(s.id) AS cnt
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id AND s.school_id = c.school_id AND s.deleted_at IS NULL
        WHERE c.school_id = :sid AND c.status = 'active'
        GROUP BY c.id, c.class_name, c.roman_number
        ORDER BY c.id ASC
        LIMIT 8
    ");
    $stmt_by_class->execute([':sid' => $school_id]);
    $students_by_class = $stmt_by_class->fetchAll();

    // 6i. Recent activity: merge last 5 fee payments + 3 leads
    $stmt_act_fee = $pdo->prepare("
        SELECT 'fee' AS type, CONCAT(s.first_name,' ',s.last_name) AS label,
               CONCAT('₹',FORMAT(fp.amount_paid,0),' collected') AS detail,
               fp.payment_date AS act_time
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        WHERE fp.school_id = :sid
        ORDER BY fp.id DESC LIMIT 4
    ");
    $stmt_act_fee->execute([':sid' => $school_id]);
    $act_fee = $stmt_act_fee->fetchAll();

    $stmt_act_lead = $pdo->prepare("
        SELECT 'lead' AS type, CONCAT(first_name, ' ', COALESCE(last_name, '')) AS label,
               CONCAT('New inquiry - ',COALESCE(status,'Interested')) AS detail,
               created_at AS act_time
        FROM leads
        WHERE school_id = :sid AND deleted_at IS NULL
        ORDER BY id DESC LIMIT 3
    ");
    $stmt_act_lead->execute([':sid' => $school_id]);
    $act_lead = $stmt_act_lead->fetchAll();

    $recent_activity = array_merge($act_fee, $act_lead);
    usort($recent_activity, fn($a,$b) => strtotime($b['act_time']) - strtotime($a['act_time']));
    $recent_activity = array_slice($recent_activity, 0, 6);

    // 7. Monthly chart data
    $stmt_chart_coll = $pdo->prepare("
        SELECT MONTH(payment_date) AS m, SUM(amount_paid) AS total
        FROM fee_payments
        WHERE school_id = :sid AND YEAR(payment_date) = YEAR(CURDATE())
        GROUP BY MONTH(payment_date)
    ");
    $stmt_chart_coll->execute([':sid' => $school_id]);
    $coll_by_month = array_fill(1, 12, 0.0);
    while ($row = $stmt_chart_coll->fetch()) { $coll_by_month[(int)$row['m']] = (float)$row['total']; }

    $stmt_chart_out = $pdo->prepare("
        SELECT MONTH(sfi.created_at) AS m, SUM(sfi.amount - sfi.paid_amount) AS total
        FROM student_fee_items sfi
        JOIN students s ON sfi.student_id = s.id
        WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL AND YEAR(sfi.created_at) = YEAR(CURDATE())
        GROUP BY MONTH(sfi.created_at)
    ");
    $stmt_chart_out->execute([':sid' => $school_id]);
    $out_by_month = array_fill(1, 12, 0.0);
    while ($row = $stmt_chart_out->fetch()) { $out_by_month[(int)$row['m']] = (float)$row['total']; }

    $months_names = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $current_month_num    = (int)date('m');
    $display_months_count = max(6, $current_month_num);
    $chart_months      = array_slice($months_names, 0, $display_months_count);
    $chart_collected   = array_slice(array_values($coll_by_month), 0, $display_months_count);
    $chart_outstanding = array_slice(array_values($out_by_month),  0, $display_months_count);

    // 8. DB-backed To-Do list (auto-create table inline)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `dashboard_todos` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `school_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `title` varchar(255) NOT NULL,
          `due_label` varchar(100) DEFAULT NULL,
          `is_completed` tinyint(1) NOT NULL DEFAULT 0,
          `sort_order` int(11) NOT NULL DEFAULT 0,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_school_user` (`school_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt_todos = $pdo->prepare("SELECT * FROM dashboard_todos WHERE school_id = :sid AND user_id = :uid ORDER BY is_completed ASC, sort_order ASC, id ASC LIMIT 10");
        $stmt_todos->execute([':sid' => $school_id, ':uid' => $user_id]);
        $db_todos = $stmt_todos->fetchAll();
    } catch (Exception $e) {
        $db_todos = [];
    }
    ?>
    <div id="dashboard-data"
         data-chart-months='<?php echo json_encode($chart_months); ?>'
         data-chart-collected='<?php echo json_encode($chart_collected); ?>'
         data-chart-outstanding='<?php echo json_encode($chart_outstanding); ?>'
         data-expense-percentage="<?php echo $expense_percentage; ?>"
         data-lead-labels='<?php echo json_encode($lead_pipeline_labels); ?>'
         data-lead-counts='<?php echo json_encode($lead_pipeline_counts); ?>'
         data-assistant-url="<?php echo BASE_URL; ?>modules/school/dashboard/assistant.php"
         data-todos-url="<?php echo BASE_URL; ?>modules/school/dashboard/todos.php"
         data-fee-percent="<?php echo $fee_target_percent; ?>"
         data-total-students="<?php echo $total_students; ?>"
         data-school-name="<?php echo sanitize($_SESSION['school_name'] ?? ''); ?>"
         data-greeting="<?php echo $greeting; ?>"
         hidden>
    </div>
    <!-- Main Dashboard Glassmorphism Container with Background Blobs -->
    <div class="glass-bg-blob blob-primary"></div>
    <div class="glass-bg-blob blob-success"></div>

    <div class="dashboard-glass-container">
        <!-- Dashboard Sub-Header Greeting & Quick Actions -->
        <div class="dashboard-sub-header">
            <div class="welcome-msg-block d-flex align-items-center gap-3">
                <?php
                $user_avatar_url = "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100";
                if (!empty($_SESSION['avatar'])) {
                    $avatar_path = ROOT_PATH . "uploads/profile/" . $_SESSION['avatar'];
                    if (file_exists($avatar_path)) {
                        $user_avatar_url = BASE_URL . "uploads/profile/" . $_SESSION['avatar'];
                    }
                }
                ?>
                <img src="<?php echo $user_avatar_url; ?>" alt="User Avatar" class="dashboard-avatar-img rounded-circle border border-2 border-white shadow-sm">
                <div>
                    <h1 class="mb-0"><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?>!</h1>
                    <p class="mb-0">Let's make this day productive at <?php echo htmlspecialchars($_SESSION['school_name'] ?? 'School'); ?>.</p>
                </div>
            </div>
            <div class="welcome-stats-block">
                <div class="stat-pill-item">
                    <span class="stat-pill-label">Total Students</span>
                    <span class="stat-pill-value">
                        <?php echo number_format($total_students); ?>
                        <i class="ti ti-arrow-up-right"></i>
                    </span>
                </div>
                <div class="stat-pill-item">
                    <span class="stat-pill-label">Fee Collected</span>
                    <span class="stat-pill-value">
                        <?php echo $fee_target_percent; ?>%
                        <i class="ti ti-arrow-up-right"></i>
                    </span>
                </div>
                <a href="<?php echo BASE_URL; ?>modules/school/students/index.php" class="btn-primary-pill">
                    <i class="ti ti-plus"></i> Add Student
                </a>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="container-fluid px-3">

            <!-- KPI Analytics Cards Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card-premium h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xxs fw-semibold text-uppercase text-muted">Students</span>
                                <div class="activity-icon-wrapper activity-icon-blue" style="width:34px;height:34px;">
                                    <i class="ti ti-users fs-6"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-0 font-heading"><?php echo number_format($total_students); ?></h3>
                            <span class="text-xxs text-muted"><?php echo number_format($active_students); ?> active</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card-premium h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xxs fw-semibold text-uppercase text-muted">Teachers</span>
                                <div class="activity-icon-wrapper activity-icon-indigo" style="width:34px;height:34px;">
                                    <i class="ti ti-school fs-6"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-0 font-heading"><?php echo number_format($total_teachers); ?></h3>
                            <span class="text-xxs text-muted"><?php echo number_format($total_parents); ?> parents</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card-premium h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xxs fw-semibold text-uppercase text-muted">Fee Collected</span>
                                <div class="activity-icon-wrapper activity-icon-amber" style="width:34px;height:34px;">
                                    <i class="ti ti-coin-rupee fs-6"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-0 font-heading"><?php echo $fee_target_percent; ?>%</h3>
                            <span class="text-xxs text-muted">₹<?php echo number_format($total_fees_collected, 0); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card-premium h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xxs fw-semibold text-uppercase text-muted">Pending Fees</span>
                                <div class="activity-icon-wrapper activity-icon-red" style="width:34px;height:34px;">
                                    <i class="ti ti-alert-triangle fs-6"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-0 font-heading"><?php echo number_format($total_defaulters); ?></h3>
                            <span class="text-xxs text-muted">₹<?php echo number_format($total_fees_outstanding, 0); ?> due</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card-premium h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xxs fw-semibold text-uppercase text-muted">Leads</span>
                                <div class="activity-icon-wrapper activity-icon-blue" style="width:34px;height:34px;background:rgba(34,197,94,.12);">
                                    <i class="ti ti-antenna-bars-5 fs-6" style="color:#16a34a;"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-0 font-heading"><?php echo number_format($total_leads); ?></h3>
                            <span class="text-xxs text-muted"><?php echo $new_leads_week; ?> new this week</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card-premium h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xxs fw-semibold text-uppercase text-muted">Expenses</span>
                                <div class="activity-icon-wrapper activity-icon-indigo" style="width:34px;height:34px;background:rgba(239,68,68,.12);">
                                    <i class="ti ti-receipt fs-6" style="color:#dc2626;"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-0 font-heading">₹<?php echo number_format($expenses_spent, 0); ?></h3>
                            <span class="text-xxs text-muted">total recorded</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Col 1: Virtual Assistant Card -->
                <div class="col-xl-5 col-lg-6">
                    <!-- Outer Tabs Switcher -->
                    <div class="assistant-tabs" id="assistantTabs" role="tablist">
                        <button class="assistant-tab-btn" id="help-tab" type="button" title="Help">
                            <i class="ti ti-help-circle fs-5"></i>
                        </button>
                        <button class="assistant-tab-btn active" id="chat-tab" type="button" title="Virtual Assistant">
                            <i class="ti ti-message-2 fs-5"></i>
                        </button>
                        <button class="assistant-tab-btn" id="settings-tab" type="button" title="Settings">
                            <i class="ti ti-settings fs-5"></i>
                        </button>
                    </div>

                    <!-- Main Glassmorphic Assistant Card -->
                    <div class="glass-panel" style="min-height: 350px;">
                        <!-- Virtual assistant avatar in the top right corner -->
                        <div class="chat-avatar-wrapper">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=100" alt="Assistant Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        <div class="chat-body mt-4" id="chatBody">
                            <div class="chat-bubble" id="chatWelcomeBubble">
                                Namaste! Welcome to <strong><?php echo sanitize($_SESSION['school_name'] ?? 'your school'); ?></strong> dashboard. Ask me about students, fees, teachers, or leads!
                                <span class="time"><?php echo date('g:i A'); ?></span>
                            </div>
                        </div>

                        <!-- Input line at the bottom -->
                        <div class="chat-input-wrapper">
                            <input type="text" id="chatInput" placeholder="Ask about students, fees, teachers...">
                            <div class="chat-input-actions">
                                <i class="ti ti-send" id="chatSendBtn" title="Send message" role="button" tabindex="0"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Col 2: My Activity Timeline Card -->
                <div class="col-xl-7 col-lg-6">
                    <div class="glass-panel" style="min-height: 350px;">
                        <div class="timeline-header">
                            <div>
                                <h5 class="fw-bold mb-0" style="font-family: var(--font-heading); color: #2D2D35;">My activity</h5>
                                <span class="text-xs text-muted">What is waiting for you today</span>
                            </div>
                            <button class="circle-btn-util" title="Calendar">
                                <i class="ti ti-calendar fs-5"></i>
                            </button>
                        </div>

                        <!-- Dotted Vertical Grid Lines Timeline Slider -->
                        <div class="timeline-grid">
                            <!-- Hour Column 07:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">07:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 08:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">08:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 09:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">09:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 10:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">10:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 11:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">11:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 12:00 -->
                            <div class="timeline-hour-col" style="font-weight: bold; color: #2D2D35;">
                                <span class="timeline-hour-label" style="color: #2D2D35;">12:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 01:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">01:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>
                            <!-- Hour Column 02:00 -->
                            <div class="timeline-hour-col">
                                <span class="timeline-hour-label">02:00</span>
                                <div class="timeline-dotted-line"></div>
                            </div>

                            <!-- Pill Event Blocks -->
                            <!-- Lime green: Staff Meeting -->
                            <div class="timeline-event-block event-lime">
                                <div class="event-info">
                                    <span class="event-title">Staff Meeting</span>
                                    <span class="event-subtitle">Brighton School Staff Room</span>
                                </div>
                                <div class="event-avatars">
                                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=60" alt="Avatar" class="event-avatar-img">
                                    <span class="event-avatar-more">+4</span>
                                </div>
                            </div>

                            <!-- Blue-grey: CBSE Syllabus review -->
                            <div class="timeline-event-block event-blue-grey">
                                <div class="event-info">
                                    <span class="event-title">CBSE syllabus review</span>
                                    <span class="event-subtitle">Conference Room</span>
                                </div>
                                <div class="event-avatars">
                                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=60" alt="Avatar" class="event-avatar-img">
                                    <span class="event-avatar-more">+8</span>
                                </div>
                            </div>

                            <!-- Lavender: Chai & Samosa break -->
                            <div class="timeline-event-block event-lavender">
                                <div class="event-info">
                                    <span class="event-title">Chai & Samosa break</span>
                                    <span class="event-subtitle">Canteen</span>
                                </div>
                            </div>

                            <!-- Current time tracker indicator vertical black line -->
                            <div class="timeline-current-indicator">
                                <div class="timeline-indicator-dot"></div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Col 2: Recent Activity Card -->
                <div class="col-xl-7 col-lg-6">
                    <div class="glass-panel" id="activityPanel">
                        <div class="timeline-header">
                            <div>
                                <h5 class="fw-bold mb-0 font-heading">Recent Activity</h5>
                                <span class="text-xs text-muted">Latest transactions &amp; inquiries</span>
                            </div>
                            <a href="<?php echo BASE_URL; ?>modules/school/fees/collected-log.php" class="circle-btn-util" title="View All">
                                <i class="ti ti-arrow-up-right fs-5"></i>
                            </a>
                        </div>
                        <div class="dashboard-activity-feed">
                            <?php if (empty($recent_activity)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="ti ti-timeline fs-2 d-block mb-2"></i>
                                <span class="text-xs">No recent activity yet.</span>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recent_activity as $act): ?>
                            <div class="dash-activity-item">
                                <div class="dash-activity-icon <?php echo $act['type'] === 'fee' ? 'act-icon-green' : 'act-icon-blue'; ?>">
                                    <i class="ti <?php echo $act['type'] === 'fee' ? 'ti-coin-rupee' : 'ti-user-plus'; ?>"></i>
                                </div>
                                <div class="dash-activity-body">
                                    <span class="dash-activity-label"><?php echo sanitize($act['label']); ?></span>
                                    <span class="dash-activity-detail"><?php echo sanitize($act['detail']); ?></span>
                                </div>
                                <div class="dash-activity-time">
                                    <?php echo date('d M, g:i A', strtotime($act['act_time'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($students_by_class)): ?>
                        <div class="class-breakdown-section">
                            <h6 class="class-breakdown-title">Students by Class</h6>
                            <div class="class-breakdown-grid">
                                <?php foreach ($students_by_class as $cls): ?>
                                <div class="class-breakdown-pill">
                                    <span class="cbp-roman"><?php echo sanitize($cls['roman_number'] ?: $cls['class_name']); ?></span>
                                    <span class="cbp-count"><?php echo (int)$cls['cnt']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-0">
                <!-- Col 3: DB-Backed To-Do List -->
                <div class="col-xl-5 col-lg-6">
                    <div class="glass-panel todo-card-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold mb-0 font-heading">To-do list</h5>
                                <span class="text-xs text-muted"><?php echo date('l, d M Y'); ?></span>
                            </div>
                            <button class="circle-btn-util" id="todoAddToggleBtn" title="Add task">
                                <i class="ti ti-plus fs-5"></i>
                            </button>
                        </div>
                        <div class="todo-add-form" id="todoAddForm">
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" class="form-control form-control-sm" id="todoTitleInput" placeholder="New task title..." maxlength="255">
                                <input type="text" class="form-control form-control-sm todo-due-input" id="todoDueInput" placeholder="Due (e.g. Today)" maxlength="100">
                                <button class="btn btn-sm btn-primary" id="todoSubmitBtn" type="button">Add</button>
                            </div>
                        </div>
                        <div class="todo-list-wrapper" id="todoListWrapper">
                            <?php if (empty($db_todos)): ?>
                            <div class="text-center text-muted py-4" id="todoEmptyMsg">
                                <i class="ti ti-clipboard-list fs-2 d-block mb-2"></i>
                                <span class="text-xs">No tasks yet. Click + to add one!</span>
                            </div>
                            <?php else: ?>
                            <?php foreach ($db_todos as $todo): ?>
                            <div class="todo-item" data-todo-id="<?php echo (int)$todo['id']; ?>">
                                <div class="todo-left">
                                    <div class="todo-check-btn <?php echo $todo['is_completed'] ? 'completed' : ''; ?>" data-todo-id="<?php echo (int)$todo['id']; ?>">
                                        <i class="ti ti-check"></i>
                                    </div>
                                    <div class="todo-text-block">
                                        <span class="todo-title-txt <?php echo $todo['is_completed'] ? 'completed' : ''; ?>">
                                            <?php echo sanitize($todo['title']); ?>
                                        </span>
                                        <?php if ($todo['due_label']): ?>
                                        <span class="todo-meta-txt"><?php echo sanitize($todo['due_label']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="todo-right">
                                    <button class="todo-delete-btn" data-todo-id="<?php echo (int)$todo['id']; ?>" title="Delete task" type="button">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Col 4: Summary Curve Chart Card -->
                <div class="col-xl-7 col-lg-6">
                    <div class="glass-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold mb-0 font-heading">Summary</h5>
                                <span class="text-xs text-muted">Track school financial collection</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="circle-btn-util" title="Chart Options">
                                    <i class="ti ti-adjustments-horizontal fs-5"></i>
                                </button>
                                <button class="circle-btn-util" title="Expanded View">
                                    <i class="ti ti-arrow-up-right fs-5"></i>
                                </button>
                            </div>
                        </div>
                        <div class="summary-chart-container">
                            <!-- Bezier curve chart using Chart.js -->
                            <canvas id="summaryChart"></canvas>
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
