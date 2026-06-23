<?php
// modules/school/fees/transport-structure.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$csrf_token = generate_csrf_token();

// ── AJAX Endpoint: Fetch students of a route ──────────────────────────────────
if (isset($_GET['get_route_students'])) {
    $route_id = intval($_GET['get_route_students']);
    $stmt = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.admission_no, s.admission_no_prefix,
               c.name as class_name, sec.name as section_name, s.mobile_no
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.transport_route_id = :route_id AND s.school_id = :sid AND s.deleted_at IS NULL
        ORDER BY s.first_name ASC, s.last_name ASC
    ");
    $stmt->execute([':route_id' => $route_id, ':sid' => $school_id]);
    $route_students = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($route_students);
    exit;
}

// ── Filter inputs ─────────────────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$limit        = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? intval($_GET['limit']) : 20;
$page_num     = (isset($_GET['page']) && is_numeric($_GET['page'])) ? intval($_GET['page']) : 1;
$offset       = ($page_num - 1) * $limit;
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == 1 ? 1 : 0;

// Fetch sessions for dropdown
$stmt_sess = $pdo->prepare("SELECT name FROM academic_sessions WHERE school_id = :school_id ORDER BY id DESC");
$stmt_sess->execute([':school_id' => $school_id]);
$sessions = $stmt_sess->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('2026 - 2027', $sessions)) {
    array_unshift($sessions, '2026 - 2027');
}
$current_session = $_GET['session'] ?? ($_SESSION['academic_session_name'] ?? '2026 - 2027');

// Mockup Drivers list
$mockup_drivers = [
    ['name' => 'Driver 1', 'mobile' => '9999999999', 'aadhaar' => '1234123412341234'],
    ['name' => 'aman', 'mobile' => '990001100', 'aadhaar' => '5045064556655665'],
    ['name' => 'varun', 'mobile' => '8877665544', 'aadhaar' => '2233445566778899'],
    ['name' => 'Pappu', 'mobile' => '9760424799', 'aadhaar' => '9988776655443322'],
    ['name' => 'Monu', 'mobile' => '9368302360', 'aadhaar' => '1122334455667788'],
    ['name' => 'Ajay', 'mobile' => '7042426897', 'aadhaar' => '5566778899001122'],
    ['name' => 'Veersingh', 'mobile' => '9310944034', 'aadhaar' => '3344556677889900'],
    ['name' => 'Major singh', 'mobile' => '1234567890', 'aadhaar' => '169902728828282928'],
    ['name' => 'Jeevan Ram', 'mobile' => '1234567890', 'aadhaar' => '7766554433221100'],
    ['name' => 'NAYAZ', 'mobile' => '9988554477', 'aadhaar' => '789456112325'],
    ['name' => 'Rajpal', 'mobile' => '9368302360', 'aadhaar' => '8899001122334455']
];

// ── Process POST requests (Add, Edit, Delete, Restore) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header("Location: transport-structure.php?session=" . urlencode($current_session));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ADD ROUTE
    if ($action === 'add_route') {
        $route_name       = trim($_POST['route_name'] ?? '');
        $session_val      = trim($_POST['session'] ?? $current_session);
        $vehicle_no       = trim($_POST['vehicle_no'] ?? '');
        $vehicle_type     = trim($_POST['vehicle_type'] ?? '');
        $vehicle_cond     = trim($_POST['vehicle_condition'] ?? '');
        $driver_name      = trim($_POST['driver_name'] ?? '');
        $driver_mobile    = trim($_POST['driver_mobile'] ?? '');
        $driver_aadhaar   = trim($_POST['driver_aadhaar'] ?? '');
        $fine_type        = trim($_POST['fine_type'] ?? 'none');
        $fine_amount      = floatval($_POST['fine_amount'] ?? 0);
        $sub_day_date     = trim($_POST['sub_day_date'] ?? '');

        // Process route stops array into JSON
        $stops = $_POST['stops'] ?? [];
        $route_structure_arr = [];
        if (!empty($stops['starting_from'])) {
            for ($i = 0; $i < count($stops['starting_from']); $i++) {
                $start = trim($stops['starting_from'][$i]);
                $to    = trim($stops['stop_to'][$i]);
                $fee   = floatval($stops['fees'][$i]);
                if ($start !== '' || $to !== '') {
                    $route_structure_arr[] = [
                        'starting_from' => $start,
                        'stop_to'       => $to,
                        'fees'          => $fee
                    ];
                }
            }
        }
        $route_structure = json_encode($route_structure_arr);

        if (empty($route_name)) {
            $_SESSION['flash_error'] = "Route name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO transport_routes
                    (school_id, route_name, route_structure, session, vehicle_no, vehicle_type, vehicle_condition, driver_name, driver_mobile, driver_aadhaar, fine_type, fine_amount, sub_day_date)
                    VALUES
                    (:sid, :route_name, :route_structure, :session, :vehicle_no, :vehicle_type, :vehicle_cond, :driver_name, :driver_mobile, :driver_aadhaar, :fine_type, :fine_amount, :sub_day_date)
                ");
                $stmt->execute([
                    ':sid'             => $school_id,
                    ':route_name'      => $route_name,
                    ':route_structure' => $route_structure,
                    ':session'         => $session_val,
                    ':vehicle_no'      => $vehicle_no,
                    ':vehicle_type'    => $vehicle_type,
                    ':vehicle_cond'    => $vehicle_cond,
                    ':driver_name'     => $driver_name,
                    ':driver_mobile'   => $driver_mobile,
                    ':driver_aadhaar'  => $driver_aadhaar,
                    ':fine_type'        => $fine_type,
                    ':fine_amount'      => $fine_amount,
                    ':sub_day_date'     => $sub_day_date
                ]);
                $_SESSION['flash_success'] = "Transport route created successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to create transport route: " . $e->getMessage();
            }
        }
        header("Location: transport-structure.php?session=" . urlencode($current_session));
        exit;
    }

    // EDIT ROUTE
    if ($action === 'edit_route') {
        $id               = intval($_POST['id'] ?? 0);
        $route_name       = trim($_POST['route_name'] ?? '');
        $session_val      = trim($_POST['session'] ?? $current_session);
        $vehicle_no       = trim($_POST['vehicle_no'] ?? '');
        $vehicle_type     = trim($_POST['vehicle_type'] ?? '');
        $vehicle_cond     = trim($_POST['vehicle_condition'] ?? '');
        $driver_name      = trim($_POST['driver_name'] ?? '');
        $driver_mobile    = trim($_POST['driver_mobile'] ?? '');
        $driver_aadhaar   = trim($_POST['driver_aadhaar'] ?? '');
        $fine_type        = trim($_POST['fine_type'] ?? 'none');
        $fine_amount      = floatval($_POST['fine_amount'] ?? 0);
        $sub_day_date     = trim($_POST['sub_day_date'] ?? '');

        // Process route stops array into JSON
        $stops = $_POST['stops'] ?? [];
        $route_structure_arr = [];
        if (!empty($stops['starting_from'])) {
            for ($i = 0; $i < count($stops['starting_from']); $i++) {
                $start = trim($stops['starting_from'][$i]);
                $to    = trim($stops['stop_to'][$i]);
                $fee   = floatval($stops['fees'][$i]);
                if ($start !== '' || $to !== '') {
                    $route_structure_arr[] = [
                        'starting_from' => $start,
                        'stop_to'       => $to,
                        'fees'          => $fee
                    ];
                }
            }
        }
        $route_structure = json_encode($route_structure_arr);

        if (empty($route_name) || $id <= 0) {
            $_SESSION['flash_error'] = "Route name and valid ID are required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE transport_routes
                    SET route_name = :route_name,
                        route_structure = :route_structure,
                        session = :session,
                        vehicle_no = :vehicle_no,
                        vehicle_type = :vehicle_type,
                        vehicle_condition = :vehicle_cond,
                        driver_name = :driver_name,
                        driver_mobile = :driver_mobile,
                        driver_aadhaar = :driver_aadhaar,
                        fine_type = :fine_type,
                        fine_amount = :fine_amount,
                        sub_day_date = :sub_day_date
                    WHERE id = :id AND school_id = :sid
                ");
                $stmt->execute([
                    ':route_name'      => $route_name,
                    ':route_structure' => $route_structure,
                    ':session'         => $session_val,
                    ':vehicle_no'      => $vehicle_no,
                    ':vehicle_type'    => $vehicle_type,
                    ':vehicle_cond'    => $vehicle_cond,
                    ':driver_name'     => $driver_name,
                    ':driver_mobile'   => $driver_mobile,
                    ':driver_aadhaar'  => $driver_aadhaar,
                    ':fine_type'        => $fine_type,
                    ':fine_amount'      => $fine_amount,
                    ':sub_day_date'     => $sub_day_date,
                    ':id'              => $id,
                    ':sid'             => $school_id
                ]);
                $_SESSION['flash_success'] = "Transport route updated successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to update transport route: " . $e->getMessage();
            }
        }
        header("Location: transport-structure.php?session=" . urlencode($current_session));
        exit;
    }

    // DELETE ROUTE
    if ($action === 'delete_route') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE transport_routes SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND school_id = :sid");
                $stmt->execute([':id' => $id, ':sid' => $school_id]);
                $_SESSION['flash_success'] = "Transport route deleted successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to delete transport route: " . $e->getMessage();
            }
        }
        header("Location: transport-structure.php?session=" . urlencode($current_session));
        exit;
    }

    // RESTORE ROUTE
    if ($action === 'restore_route') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE transport_routes SET deleted_at = NULL WHERE id = :id AND school_id = :sid");
                $stmt->execute([':id' => $id, ':sid' => $school_id]);
                $_SESSION['flash_success'] = "Transport route restored successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to restore transport route: " . $e->getMessage();
            }
        }
        header("Location: transport-structure.php?session=" . urlencode($current_session) . "&show_deleted=1");
        exit;
    }
}

// ── Dynamic stats badge calculations ─────────────────────────────────────────
$stmt_stats = $pdo->prepare("
    SELECT SUM(sfi.amount) as total_fees, SUM(sfi.discount_amount) as total_discount
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND sfi.fee_name = 'Transport Fees' AND s.deleted_at IS NULL
");
$stmt_stats->execute([':sid' => $school_id]);
$stats = $stmt_stats->fetch();
$total_school_fees = floatval($stats['total_fees'] ?? 53000); // fallback to mockup values if no items
$total_school_discount = floatval($stats['total_discount'] ?? 100);
$gross_total_fees = $total_school_fees - $total_school_discount;

// ── Build routes query ────────────────────────────────────────────────────────
$where  = "WHERE r.school_id = :sid";
$params = [':sid' => $school_id];

if ($show_deleted) {
    $where .= " AND r.deleted_at IS NOT NULL";
} else {
    $where .= " AND r.deleted_at IS NULL";
}

if ($search) {
    $where .= " AND r.route_name LIKE :search";
    $params[':search'] = "%$search%";
}

// Count total records
$stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM transport_routes r $where");
$stmt_cnt->execute($params);
$total_records = (int)$stmt_cnt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $limit));

// Fetch paginated routes with student counts
$stmt_r = $pdo->prepare("
    SELECT r.*, COUNT(s.id) AS student_count
    FROM transport_routes r
    LEFT JOIN students s ON s.transport_route_id = r.id AND s.deleted_at IS NULL
    $where
    GROUP BY r.id
    ORDER BY r.id DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $stmt_r->bindValue($k, $v);
$stmt_r->bindValue(':lim', $limit,  PDO::PARAM_INT);
$stmt_r->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt_r->execute();
$routes = $stmt_r->fetchAll();

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading & Subtitle ──────────────────────────────────────────────── -->
<div class="row align-items-center mb-2 g-3">
    <div class="col-12">
        <h2 class="mb-1 font-heading fw-extrabold text-dark">Transport Structure</h2>

    </div>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible fade show font-secondary text-xs mb-3 mt-2" role="alert" style="border-radius: 8px;">
        <i class="ph-light ph-check-circle me-1" style="font-size: 14px; vertical-align: middle;"></i> <?php echo htmlspecialchars($flash_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible fade show font-secondary text-xs mb-3 mt-2" role="alert" style="border-radius: 8px;">
        <i class="ph-light ph-warning-circle me-1" style="font-size: 14px; vertical-align: middle;"></i> <?php echo htmlspecialchars($flash_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- ── Actions & Filters Toolbar Card ─────────────────────────────────────────── -->
<div class="row mb-4 mt-3">
    <div class="col-12">
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius); padding:1.25rem; box-shadow: var(--shadow-sm);">

            <!-- Row 1: Left Action Buttons & Right Session Dropdown -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <button type="button" class="teacher-header-btn btn-accent" data-bs-toggle="modal" data-bs-target="#addRouteModal" title="Add Transport Route">
                        <i class="ph-light ph-plus"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="text-sm font-heading fw-bold" style="color:var(--color-text-primary);">Sessions</span>
                    <form method="GET" action="transport-structure.php" class="m-0" id="sessionForm">
                        <select name="session" class="form-control-admin font-secondary text-secondary" style="width:140px; height:38px; border-radius:6px;" onchange="this.form.submit()">
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $current_session === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Row 2: Stats Badges Group & Right-aligned Deleted Link -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge font-secondary px-3 py-2" style="background-color: var(--info-light); color: var(--info); border: 1px solid rgba(14, 165, 233, 0.25); font-size:12px; font-weight:600; border-radius: 6px;">
                        Total Fees: (<?php echo number_format($total_school_fees, 0, '', ''); ?>)
                    </span>
                    <span class="badge font-secondary px-3 py-2" style="background-color: var(--brand-light); color: var(--brand); border: 1px solid rgba(99, 102, 241, 0.25); font-size:12px; font-weight:600; border-radius: 6px;">
                        Head Discount: (<?php echo number_format($total_school_discount, 0, '', ''); ?>)
                    </span>
                    <span class="badge font-secondary px-3 py-2" style="background-color: var(--success-light); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.25); font-size:12px; font-weight:600; border-radius: 6px;">
                        Gross Total Fees: (<?php echo number_format($gross_total_fees, 0, '', ''); ?>)
                    </span>
                </div>
                <div>
                    <?php if ($show_deleted): ?>
                        <a href="transport-structure.php?session=<?php echo urlencode($current_session); ?>" class="text-sm font-heading fw-semibold text-decoration-none" style="color: var(--color-primary); cursor: pointer;">Active Routes</a>
                    <?php else: ?>
                        <a href="transport-structure.php?session=<?php echo urlencode($current_session); ?>&show_deleted=1" class="text-sm font-heading fw-semibold text-decoration-none" style="color: var(--color-danger); cursor: pointer;">Deleted</a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── Transport Table ────────────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">

            <!-- Table Sub-toolbar Controls -->
            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom: 0;">
                <div class="fee-toolbar-left d-flex align-items-center gap-1">
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">Show</span>
                    <form method="GET" action="transport-structure.php" class="d-inline-block m-0" id="limitForm">
                        <input type="hidden" name="session" value="<?php echo htmlspecialchars($current_session); ?>">
                        <input type="hidden" name="show_deleted" value="<?php echo $show_deleted; ?>">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="limit" class="form-control-admin font-secondary text-secondary" style="width:64px; display:inline-block; height:34px; border-radius:4px; padding: 2px 8px;" onchange="this.form.submit()">
                            <option value="20" <?php echo $limit === 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </form>
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">entries</span>
                </div>
                <div class="fee-toolbar-right">
                    <form method="GET" action="transport-structure.php" class="m-0 d-flex align-items-center gap-1">
                        <input type="hidden" name="session" value="<?php echo htmlspecialchars($current_session); ?>">
                        <input type="hidden" name="show_deleted" value="<?php echo $show_deleted; ?>">
                        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                        <label class="text-xs font-secondary mb-0" style="color:var(--color-text-primary);">Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control-admin font-secondary" style="width:160px; height:32px; border-radius:4px; padding:2px 8px;">
                    </form>
                </div>
            </div>

            <!-- Table Container -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="teacher-table table-premium mb-0 align-middle" id="transportTable">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="min-width: 140px;">Route Name</th>
                                <th style="min-width: 260px;">Route Structure</th>
                                <th style="min-width: 110px;">Session</th>
                                <th style="min-width: 90px;">Students</th>
                                <th style="min-width: 180px;">Vehicle</th>
                                <th style="min-width: 180px;">Driver</th>
                                <th style="min-width: 120px;">Fine</th>
                                <th style="min-width: 110px;">Sub. Day/Date</th>
                                <th style="min-width: 130px;">Created At</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($routes)): ?>
                                <tr>
                                    <td colspan="11" class="text-center p-5">
                                        <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                            <i class="ph-light ph-bus"></i>
                                        </div>
                                        <h5 class="fw-bold mt-3 mb-1 font-heading">No Transport Routes Setup</h5>
                                        <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Create your first transport route using the plus button above.</p>
                                    </td>
                                </tr>
                                <?php else:
                                $idx = $offset + 1;
                                foreach ($routes as $row):
                                ?>
                                    <tr>
                                        <td class="font-secondary"><?php echo $idx++; ?></td>
                                        <td>
                                            <a href="#" class="fw-bold font-heading text-decoration-none text-primary view-usage-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo sanitize($row['route_name']); ?>" style="font-size:14px;">
                                                <?php echo sanitize($row['route_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="text-xs font-secondary text-secondary" style="line-height:1.5;">
                                                <?php
                                                $decoded_stops = json_decode($row['route_structure'], true);
                                                if (is_array($decoded_stops)) {
                                                    foreach ($decoded_stops as $st) {
                                                        echo sanitize($st['starting_from']) . ' - ' . sanitize($st['stop_to']) . ' (' . number_format($st['fees'], 0, '', '') . ')<br>';
                                                    }
                                                } else {
                                                    echo nl2br(sanitize($row['route_structure']));
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="font-secondary text-xs"><?php echo sanitize($row['session']); ?></td>
                                        <td class="font-secondary text-xs fw-bold"><?php echo $row['student_count']; ?></td>
                                        <td>
                                            <div class="text-xs font-secondary text-secondary" style="line-height:1.5;">
                                                <?php if (!empty($row['vehicle_no'])): ?>
                                                    <div><span class="text-primary fw-semibold">Vehicle No:</span> <?php echo sanitize($row['vehicle_no']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['vehicle_type'])): ?>
                                                    <div><span class="text-primary fw-semibold">Vehicle Type:</span> <?php echo sanitize($row['vehicle_type']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['vehicle_condition'])): ?>
                                                    <div><span class="text-primary fw-semibold">Vehicle Condition:</span> <?php echo sanitize($row['vehicle_condition']); ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($row['vehicle_no']) && empty($row['vehicle_type']) && empty($row['vehicle_condition'])): ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-xs font-secondary text-secondary" style="line-height:1.5;">
                                                <?php if (!empty($row['driver_name'])): ?>
                                                    <div><span class="text-primary fw-semibold">Driver Name:</span> <?php echo sanitize($row['driver_name']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['driver_mobile'])): ?>
                                                    <div><span class="text-primary fw-semibold">Mobile:</span> <?php echo sanitize($row['driver_mobile']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['driver_aadhaar'])): ?>
                                                    <div><span class="text-primary fw-semibold">Aadhaar:</span> <?php echo sanitize($row['driver_aadhaar']); ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($row['driver_name']) && empty($row['driver_mobile']) && empty($row['driver_aadhaar'])): ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['fine_type'] !== 'none' && $row['fine_amount'] > 0): ?>
                                                <div class="text-xs font-secondary text-secondary" style="line-height:1.5;">
                                                    <div><span class="fw-semibold">Type:</span> <?php echo sanitize($row['fine_type']); ?></div>
                                                    <div><span class="fw-semibold">Amount:</span> <?php echo number_format($row['fine_amount'], 0, '', ''); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-secondary text-xs text-center"><?php echo sanitize($row['sub_day_date']) ?: '—'; ?></td>
                                        <td>
                                            <span class="text-xs font-secondary text-secondary">
                                                <?php echo date('d M, Y', strtotime($row['created_at'])); ?><br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 4px; justify-content: center; align-items: center; min-width: 92px; flex-wrap: wrap;">
                                                <!-- View button -->
                                                <button type="button" class="teacher-action-btn action-view view-usage-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo sanitize($row['route_name']); ?>" title="View Usage">
                                                    <i class="ph-bold ph-eye"></i>
                                                </button>

                                                <?php if ($row['deleted_at'] === null): ?>
                                                    <!-- Edit button -->
                                                    <button type="button" class="teacher-action-btn action-edit edit-route-btn"
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-name="<?php echo sanitize($row['route_name']); ?>"
                                                        data-structure="<?php echo sanitize($row['route_structure']); ?>"
                                                        data-session="<?php echo sanitize($row['session']); ?>"
                                                        data-vehicle-no="<?php echo sanitize($row['vehicle_no']); ?>"
                                                        data-vehicle-type="<?php echo sanitize($row['vehicle_type']); ?>"
                                                        data-vehicle-cond="<?php echo sanitize($row['vehicle_condition']); ?>"
                                                        data-driver-name="<?php echo sanitize($row['driver_name']); ?>"
                                                        data-driver-mobile="<?php echo sanitize($row['driver_mobile']); ?>"
                                                        data-driver-aadhaar="<?php echo sanitize($row['driver_aadhaar']); ?>"
                                                        data-fine-type="<?php echo sanitize($row['fine_type']); ?>"
                                                        data-fine-amount="<?php echo number_format($row['fine_amount'], 2, '.', ''); ?>"
                                                        data-sub-day-date="<?php echo sanitize($row['sub_day_date']); ?>"
                                                        title="Edit Route">
                                                        <i class="ph-bold ph-pencil"></i>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button type="button" class="teacher-action-btn action-delete delete-route-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo sanitize($row['route_name']); ?>" title="Delete Route">
                                                        <i class="ph-bold ph-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Restore button -->
                                                    <button type="button" class="teacher-action-btn action-restore restore-route-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo sanitize($row['route_name']); ?>" title="Restore Route">
                                                        <i class="ph-bold ph-arrow-counter-clockwise"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Footer -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center px-4 py-3">
                    <span class="text-xs text-muted font-secondary">Showing page <?php echo $page_num; ?> of <?php echo $total_pages; ?></span>
                    <nav>
                        <ul class="pagination pagination-sm m-0 gap-1">
                            <li class="page-item <?php echo $page_num <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="transport-structure.php?session=<?php echo urlencode($current_session); ?>&show_deleted=<?php echo $show_deleted; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page_num - 1; ?>">Previous</a>
                            </li>
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <li class="page-item <?php echo $p === $page_num ? 'active' : ''; ?>">
                                    <a class="page-link" href="transport-structure.php?session=<?php echo urlencode($current_session); ?>&show_deleted=<?php echo $show_deleted; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page_num >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="transport-structure.php?session=<?php echo urlencode($current_session); ?>&show_deleted=<?php echo $show_deleted; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page_num + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ── Add Route Modal ───────────────────────────────────────────────────────── -->
<div class="modal fade" id="addRouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 animate-scale" style="border-radius: var(--radius-lg);">
            <div class="modal-header border-0 bg-light px-4 py-3">
                <div>
                    <h5 class="modal-title font-heading fw-extrabold text-dark" style="font-size: 18px;">Add Transport</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="transport-structure.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_route">
                <input type="hidden" name="session" value="<?php echo htmlspecialchars($current_session); ?>">

                <div class="modal-body p-4">
                    <!-- Row 1: Route Name, Vehicle No. -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-admin mb-1">Route Name *</label>
                            <input type="text" name="route_name" class="form-control-admin" placeholder="Modipuram via Hapur Adda-Begumpul" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin mb-1">Vehicle No.</label>
                            <input type="text" name="vehicle_no" class="form-control-admin" placeholder="e.g. UP33AB1234">
                        </div>
                    </div>

                    <!-- Row 2: Vehicle Type, Vehicle Condition, Drivers -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Vehicle Type</label>
                            <input type="text" name="vehicle_type" class="form-control-admin" placeholder="e.g. school van">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Vehicle Condition</label>
                            <input type="text" name="vehicle_condition" class="form-control-admin" placeholder="e.g. new">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Drivers</label>
                            <select class="form-control-admin add-drivers-selector">
                                <option value="">Select</option>
                                <?php foreach ($mockup_drivers as $d): ?>
                                    <option value="<?php echo htmlspecialchars(json_encode($d)); ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3: Driver Name, Driver Mobile, Driver Aadhaar -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control-admin add-driver-name" placeholder="Driver Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Driver Mobile No.</label>
                            <input type="text" name="driver_mobile" class="form-control-admin add-driver-mobile" placeholder="Driver Mobile No.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Driver Aadhaar No.</label>
                            <input type="text" name="driver_aadhaar" class="form-control-admin add-driver-aadhaar" placeholder="Driver Aadhaar No.">
                        </div>
                    </div>

                    <!-- Row 4: Auto add fine, Fine amount, Fees receiving day -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Auto add fine</label>
                            <span class="text-xxs text-muted d-block mb-1" style="text-transform:none; font-weight:normal; letter-spacing:normal;">(Select the fine type you want to apply)</span>
                            <select name="fine_type" class="form-control-admin">
                                <option value="none">None</option>
                                <option value="daily" selected>Daily</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fine amount</label>
                            <span class="text-xxs text-muted d-block mb-1" style="text-transform:none; font-weight:normal; letter-spacing:normal;">(Enter the amount you want to be applied)</span>
                            <input type="number" name="fine_amount" class="form-control-admin" min="0" step="0.01" placeholder="e.g. 50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fees receiving day</label>
                            <span class="text-xxs text-muted d-block mb-1" style="text-transform:none; font-weight:normal; letter-spacing:normal;">Select the day of month to receive the fees</span>
                            <input type="text" name="sub_day_date" class="form-control-admin" placeholder="e.g. 2">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Dynamic Stops Section -->
                    <h5 class="font-heading fw-bold text-dark mb-1" style="font-size:16px;">Route Stops Monthly Fees</h5>
                    <p class="text-xs text-muted font-secondary mb-3">
                        Enter the route stops and monthly fees for each stop. You can add multiple stops by clicking the plus button.
                    </p>

                    <div class="add-stops-container">
                        <!-- Headings -->
                        <div class="row g-3 mb-2 font-secondary fw-bold text-secondary text-xs">
                            <div class="col-md-4">Starting From (KM/School)</div>
                            <div class="col-md-4">Stop To (KM/Area)</div>
                            <div class="col-md-3">Fees (Monthly)</div>
                            <div class="col-md-1"></div>
                        </div>

                        <!-- Rows Wrapper -->
                        <div class="add-stops-rows">
                            <div class="row g-3 mb-2 align-items-center stop-item-row">
                                <div class="col-md-4">
                                    <input type="text" name="stops[starting_from][]" class="form-control-admin" placeholder="e.g. School">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="stops[stop_to][]" class="form-control-admin" placeholder="e.g. Modipuram">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="stops[fees][]" class="form-control-admin" placeholder="e.g. 500" min="0">
                                </div>
                                <div class="col-md-1 text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-stop-row-btn" style="border-radius:4px; padding: 4px 8px;"><i class="ph-bold ph-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center my-3">
                        <button type="button" class="btn btn-primary add-stop-row-btn" style="border-radius:6px; background-color:#007bff; border:none; width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="ph-bold ph-plus" style="color:#fff; font-size:16px;"></i>
                        </button>
                    </div>

                </div>

                <div class="modal-footer border-0 bg-light d-flex justify-content-between px-4 py-3" style="border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                    <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color: #a0aec0; color: #fff; border-radius: 6px; font-size: 13px; border: none;">Close</button>
                    <button type="submit" class="btn font-secondary fw-semibold px-4 py-2" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none;">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Route Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="editRouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 animate-scale" style="border-radius: var(--radius-lg);">
            <div class="modal-header border-0 bg-light px-4 py-3">
                <div>
                    <h5 class="modal-title font-heading fw-extrabold text-dark" style="font-size: 18px;">Edit Transport</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="transport-structure.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit_route">
                <input type="hidden" name="id" id="edit_route_id">
                <input type="hidden" name="session" id="edit_session">

                <div class="modal-body p-4">
                    <!-- Row 1: Route Name, Vehicle No. -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-admin mb-1">Route Name *</label>
                            <input type="text" name="route_name" id="edit_route_name" class="form-control-admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin mb-1">Vehicle No.</label>
                            <input type="text" name="vehicle_no" id="edit_vehicle_no" class="form-control-admin">
                        </div>
                    </div>

                    <!-- Row 2: Vehicle Type, Vehicle Condition, Drivers -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Vehicle Type</label>
                            <input type="text" name="vehicle_type" id="edit_vehicle_type" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Vehicle Condition</label>
                            <input type="text" name="vehicle_condition" id="edit_vehicle_condition" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Drivers</label>
                            <select class="form-control-admin edit-drivers-selector">
                                <option value="">Select</option>
                                <?php foreach ($mockup_drivers as $d): ?>
                                    <option value="<?php echo htmlspecialchars(json_encode($d)); ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3: Driver Name, Driver Mobile, Driver Aadhaar -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Driver Name</label>
                            <input type="text" name="driver_name" id="edit_driver_name" class="form-control-admin edit-driver-name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Driver Mobile No.</label>
                            <input type="text" name="driver_mobile" id="edit_driver_mobile" class="form-control-admin edit-driver-mobile">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Driver Aadhaar No.</label>
                            <input type="text" name="driver_aadhaar" id="edit_driver_aadhaar" class="form-control-admin edit-driver-aadhaar">
                        </div>
                    </div>

                    <!-- Row 4: Auto add fine, Fine amount, Fees receiving day -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Auto add fine</label>
                            <span class="text-xxs text-muted d-block mb-1" style="text-transform:none; font-weight:normal; letter-spacing:normal;">(Select the fine type you want to apply)</span>
                            <select name="fine_type" id="edit_fine_type" class="form-control-admin">
                                <option value="none">None</option>
                                <option value="daily">Daily</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fine amount</label>
                            <span class="text-xxs text-muted d-block mb-1" style="text-transform:none; font-weight:normal; letter-spacing:normal;">(Enter the amount you want to be applied)</span>
                            <input type="number" name="fine_amount" id="edit_fine_amount" class="form-control-admin" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fees receiving day</label>
                            <span class="text-xxs text-muted d-block mb-1" style="text-transform:none; font-weight:normal; letter-spacing:normal;">Select the day of month to receive the fees</span>
                            <input type="text" name="sub_day_date" id="edit_sub_day_date" class="form-control-admin">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Dynamic Stops Section -->
                    <h5 class="font-heading fw-bold text-dark mb-1" style="font-size:16px;">Route Stops Monthly Fees</h5>
                    <p class="text-xs text-muted font-secondary mb-3">
                        Enter the route stops and monthly fees for each stop. You can add multiple stops by clicking the plus button.
                    </p>

                    <div class="edit-stops-container">
                        <!-- Headings -->
                        <div class="row g-3 mb-2 font-secondary fw-bold text-secondary text-xs">
                            <div class="col-md-4">Starting From (KM/School)</div>
                            <div class="col-md-4">Stop To (KM/Area)</div>
                            <div class="col-md-3">Fees (Monthly)</div>
                            <div class="col-md-1"></div>
                        </div>

                        <!-- Rows Wrapper -->
                        <div class="edit-stops-rows">
                            <!-- Populated dynamically via JS on edit click -->
                        </div>
                    </div>

                    <div class="text-center my-3">
                        <button type="button" class="btn btn-primary edit-add-stop-row-btn" style="border-radius:6px; background-color:#007bff; border:none; width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="ph-bold ph-plus" style="color:#fff; font-size:16px;"></i>
                        </button>
                    </div>

                </div>

                <div class="modal-footer border-0 bg-light d-flex justify-content-between px-4 py-3" style="border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                    <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color: #a0aec0; color: #fff; border-radius: 6px; font-size: 13px; border: none;">Close</button>
                    <button type="submit" class="btn font-secondary fw-semibold px-4 py-2" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none;">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── View Usage Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="viewRouteUsageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 animate-scale" style="border-radius: var(--radius-lg);">
            <div class="modal-header border-0 bg-light px-4 py-3">
                <div>
                    <h5 class="modal-title font-heading fw-extrabold text-dark" id="view_route_title" style="font-size: 16px;">Transport Usage Report</h5>
                    <span class="text-xxs text-muted d-block mt-0.5">Students currently assigned to this route</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="teacher-table table-premium mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Admission No.</th>
                                <th>Student Name</th>
                                <th>Class & Section</th>
                                <th>Mobile No.</th>
                            </tr>
                        </thead>
                        <tbody id="usage_students_body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3" style="border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color: #a0aec0; color: #fff; border-radius: 6px; font-size: 13px; border: none;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden POST action forms for Deletions and Restorations -->
<form id="deleteRouteForm" action="transport-structure.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete_route">
    <input type="hidden" name="id" id="delete_route_id_input">
</form>

<form id="restoreRouteForm" action="transport-structure.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="restore_route">
    <input type="hidden" name="id" id="restore_route_id_input">
</form>

<!-- JS Logic for Modal populations & Swals -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Driver select helpers
        const addDriversSelector = document.querySelector('.add-drivers-selector');
        if (addDriversSelector) {
            addDriversSelector.addEventListener('change', function() {
                const dataStr = this.value;
                const nameInput = document.querySelector('.add-driver-name');
                const mobileInput = document.querySelector('.add-driver-mobile');
                const aadhaarInput = document.querySelector('.add-driver-aadhaar');

                if (dataStr) {
                    const driverObj = JSON.parse(dataStr);
                    nameInput.value = driverObj.name || '';
                    mobileInput.value = driverObj.mobile || '';
                    aadhaarInput.value = driverObj.aadhaar || '';
                } else {
                    nameInput.value = '';
                    mobileInput.value = '';
                    aadhaarInput.value = '';
                }
            });
        }

        const editDriversSelector = document.querySelector('.edit-drivers-selector');
        if (editDriversSelector) {
            editDriversSelector.addEventListener('change', function() {
                const dataStr = this.value;
                const nameInput = document.querySelector('.edit-driver-name');
                const mobileInput = document.querySelector('.edit-driver-mobile');
                const aadhaarInput = document.querySelector('.edit-driver-aadhaar');

                if (dataStr) {
                    const driverObj = JSON.parse(dataStr);
                    nameInput.value = driverObj.name || '';
                    mobileInput.value = driverObj.mobile || '';
                    aadhaarInput.value = driverObj.aadhaar || '';
                } else {
                    nameInput.value = '';
                    mobileInput.value = '';
                    aadhaarInput.value = '';
                }
            });
        }

        // Dynamic stops adding/removing logic for Add Modal
        const addStopsRows = document.querySelector('.add-stops-rows');
        const addStopRowBtn = document.querySelector('.add-stop-row-btn');

        if (addStopRowBtn && addStopsRows) {
            addStopRowBtn.addEventListener('click', () => {
                const newRow = document.createElement('div');
                newRow.className = 'row g-3 mb-2 align-items-center stop-item-row';
                newRow.innerHTML = `
                <div class="col-md-4">
                    <input type="text" name="stops[starting_from][]" class="form-control-admin" placeholder="e.g. School">
                </div>
                <div class="col-md-4">
                    <input type="text" name="stops[stop_to][]" class="form-control-admin" placeholder="e.g. Modipuram">
                </div>
                <div class="col-md-3">
                    <input type="number" name="stops[fees][]" class="form-control-admin" placeholder="e.g. 500" min="0">
                </div>
                <div class="col-md-1 text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-stop-row-btn" style="border-radius:4px; padding: 4px 8px;"><i class="ph-bold ph-trash"></i></button>
                </div>
            `;
                addStopsRows.appendChild(newRow);
            });
        }

        // Dynamic stops adding/removing logic for Edit Modal
        const editStopsRows = document.querySelector('.edit-stops-rows');
        const editAddStopRowBtn = document.querySelector('.edit-add-stop-row-btn');

        if (editAddStopRowBtn && editStopsRows) {
            editAddStopRowBtn.addEventListener('click', () => {
                const newRow = document.createElement('div');
                newRow.className = 'row g-3 mb-2 align-items-center stop-item-row';
                newRow.innerHTML = `
                <div class="col-md-4">
                    <input type="text" name="stops[starting_from][]" class="form-control-admin" placeholder="e.g. School">
                </div>
                <div class="col-md-4">
                    <input type="text" name="stops[stop_to][]" class="form-control-admin" placeholder="e.g. Modipuram">
                </div>
                <div class="col-md-3">
                    <input type="number" name="stops[fees][]" class="form-control-admin" placeholder="e.g. 500" min="0">
                </div>
                <div class="col-md-1 text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-stop-row-btn" style="border-radius:4px; padding: 4px 8px;"><i class="ph-bold ph-trash"></i></button>
                </div>
            `;
                editStopsRows.appendChild(newRow);
            });
        }

        // Event delegation for removing stop rows
        document.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-stop-row-btn');
            if (removeBtn) {
                const row = removeBtn.closest('.stop-item-row');
                if (row) {
                    // Ensure at least one row remains
                    const parent = row.parentNode;
                    if (parent.querySelectorAll('.stop-item-row').length > 1) {
                        row.remove();
                    } else {
                        // Just clear inputs
                        row.querySelectorAll('input').forEach(input => input.value = '');
                    }
                }
            }
        });

        // Edit Route modal triggers
        const editRouteBtns = document.querySelectorAll('.edit-route-btn');
        const editModalEl = document.getElementById('editRouteModal');
        const editModal = new bootstrap.Modal(editModalEl);

        editRouteBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_route_id').value = this.getAttribute('data-id');
                document.getElementById('edit_route_name').value = this.getAttribute('data-name');
                document.getElementById('edit_vehicle_no').value = this.getAttribute('data-vehicle-no');
                document.getElementById('edit_vehicle_type').value = this.getAttribute('data-vehicle-type');
                document.getElementById('edit_vehicle_condition').value = this.getAttribute('data-vehicle-cond');

                document.getElementById('edit_driver_name').value = this.getAttribute('data-driver-name');
                document.getElementById('edit_driver_mobile').value = this.getAttribute('data-driver-mobile');
                document.getElementById('edit_driver_aadhaar').value = this.getAttribute('data-driver-aadhaar');

                // Set fine type
                selectOptionByValue(document.getElementById('edit_fine_type'), this.getAttribute('data-fine-type'));

                document.getElementById('edit_fine_amount').value = this.getAttribute('data-fine-amount');
                document.getElementById('edit_sub_day_date').value = this.getAttribute('data-sub-day-date');
                document.getElementById('edit_session').value = this.getAttribute('data-session');

                // Populate stops structure
                const stopsDataRaw = this.getAttribute('data-structure');
                let stopsData = [];
                try {
                    stopsData = JSON.parse(stopsDataRaw);
                } catch (e) {
                    // In case it was stored in old text format, split by lines
                    if (stopsDataRaw) {
                        const lines = stopsDataRaw.split('\n');
                        lines.forEach(l => {
                            const match = l.match(/^(.*?)\s*-\s*(.*?)\s*\((\d+)\)$/);
                            if (match) {
                                stopsData.push({
                                    starting_from: match[1].trim(),
                                    stop_to: match[2].trim(),
                                    fees: parseFloat(match[3])
                                });
                            } else {
                                stopsData.push({
                                    starting_from: l.trim(),
                                    stop_to: '',
                                    fees: 0
                                });
                            }
                        });
                    }
                }

                editStopsRows.innerHTML = '';
                if (!Array.isArray(stopsData) || stopsData.length === 0) {
                    stopsData = [{
                        starting_from: '',
                        stop_to: '',
                        fees: 0
                    }];
                }

                stopsData.forEach(st => {
                    const tr = document.createElement('div');
                    tr.className = 'row g-3 mb-2 align-items-center stop-item-row';
                    tr.innerHTML = `
                    <div class="col-md-4">
                        <input type="text" name="stops[starting_from][]" class="form-control-admin" placeholder="e.g. School" value="${escapeHtml(st.starting_from || '')}">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="stops[stop_to][]" class="form-control-admin" placeholder="e.g. Modipuram" value="${escapeHtml(st.stop_to || '')}">
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="stops[fees][]" class="form-control-admin" placeholder="e.g. 500" min="0" value="${st.fees || 0}">
                    </div>
                    <div class="col-md-1 text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-stop-row-btn" style="border-radius:4px; padding: 4px 8px;"><i class="ph-bold ph-trash"></i></button>
                    </div>
                `;
                    editStopsRows.appendChild(tr);
                });

                editModal.show();
            });
        });

        function selectOptionByValue(selectEl, value) {
            if (!selectEl) return;
            const val = (value || '').toLowerCase().trim();
            for (let i = 0; i < selectEl.options.length; i++) {
                if (selectEl.options[i].value.toLowerCase().trim() === val) {
                    selectEl.selectedIndex = i;
                    return;
                }
            }
            selectEl.selectedIndex = 0;
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Delete confirmation (Swal)
        const deleteBtns = document.querySelectorAll('.delete-route-btn');
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                Swal.fire({
                    title: 'Delete Route?',
                    text: `Are you sure you want to delete the route "${name}"? This will move it to the deleted filter and unassign students.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#DC2626',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Yes, Delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('delete_route_id_input').value = id;
                        document.getElementById('deleteRouteForm').submit();
                    }
                });
            });
        });

        // Restore confirmation (Swal)
        const restoreBtns = document.querySelectorAll('.restore-route-btn');
        restoreBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                Swal.fire({
                    title: 'Restore Route?',
                    text: `Are you sure you want to restore the route "${name}"?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4F46E5',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Yes, Restore!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('restore_route_id_input').value = id;
                        document.getElementById('restoreRouteForm').submit();
                    }
                });
            });
        });

        // View Usage / Student allocation Report modal
        const viewUsageBtns = document.querySelectorAll('.view-usage-btn');
        const viewModalEl = document.getElementById('viewRouteUsageModal');
        const viewModal = new bootstrap.Modal(viewModalEl);

        viewUsageBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                document.getElementById('view_route_title').innerText = `Transport Usage Report: ${name}`;

                const tbody = document.getElementById('usage_students_body');
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> Loading...</td></tr>`;

                viewModal.show();

                fetch(`transport-structure.php?get_route_students=${id}`)
                    .then(res => res.json())
                    .then(data => {
                        tbody.innerHTML = '';
                        if (data.length === 0) {
                            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No students assigned to this route.</td></tr>`;
                        } else {
                            data.forEach((student, index) => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                <td>${index + 1}</td>
                                <td><span class="fw-bold">${(student.admission_no_prefix || '') + student.admission_no}</span></td>
                                <td>${student.first_name} ${student.last_name || ''}</td>
                                <td>${student.class_name || ''} - ${student.section_name || ''}</td>
                                <td>${student.mobile_no || '—'}</td>
                            `;
                                tbody.appendChild(tr);
                            });
                        }
                    })
                    .catch(err => {
                        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load students.</td></tr>`;
                    });
            });
        });
    });
</script>

<?php
require_once '../../../includes/footer.php';
?>
