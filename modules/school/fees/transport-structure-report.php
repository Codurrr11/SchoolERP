<?php
// modules/school/fees/transport-structure-report.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// ── Session filter ────────────────────────────────────────────────────────────
$stmt_sess = $pdo->prepare("SELECT name FROM academic_sessions WHERE school_id = :sid ORDER BY id DESC");
$stmt_sess->execute([':sid' => $school_id]);
$sessions = $stmt_sess->fetchAll(PDO::FETCH_COLUMN);
$current_session = trim($_GET['session'] ?? ($sessions[0] ?? ''));

$search   = trim($_GET['search'] ?? '');
$limit    = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? intval($_GET['limit']) : 20;
$page_num = (isset($_GET['page'])  && is_numeric($_GET['page']))  ? intval($_GET['page'])  : 1;
$offset   = ($page_num - 1) * $limit;

// ── Banner stats: aggregate all transport student_fee_items ───────────────────
$stmt_banner = $pdo->prepare("
    SELECT
        COALESCE(SUM(sfi.amount), 0)                                                   AS gross_fees,
        COALESCE(SUM(sfi.discount_amount), 0)                                          AS head_discount,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                 AS final_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                 AS gross_total,
        COALESCE(SUM(sfi.discount_amount), 0)                                          AS total_discount,
        COALESCE(SUM(sfi.paid_amount), 0)                                              AS paid_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)), 0) AS balance
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid
      AND s.deleted_at IS NULL
      AND sfi.is_active = 1
      AND sfi.fee_name = 'Transport Fees'
");
$stmt_banner->execute([':sid' => $school_id]);
$banner = $stmt_banner->fetch();
$b = fn($k) => number_format((float)($banner[$k] ?? 0), 0, '.', '');

// ── Distinct transport route stop names used in student_fee_items.route_details
// We pivot by route_details so each unique route becomes a column group.
// Also pull all distinct stops from transport_routes.route_structure JSON.
$stmt_routes = $pdo->prepare("
    SELECT r.id, r.route_name, r.route_structure
    FROM transport_routes r
    WHERE r.school_id = :sid AND r.deleted_at IS NULL
    ORDER BY r.id ASC
");
$stmt_routes->execute([':sid' => $school_id]);
$all_routes = $stmt_routes->fetchAll();

// Build a flat list of distinct stop labels (Starting – Stop) from all routes
$all_stop_labels = [];
foreach ($all_routes as $rt) {
    $stops = json_decode($rt['route_structure'], true);
    if (is_array($stops)) {
        foreach ($stops as $st) {
            $label = trim(($st['starting_from'] ?? '') . ' - ' . ($st['stop_to'] ?? ''));
            if ($label !== ' - ' && $label !== '') {
                $all_stop_labels[$label] = $rt['route_name'];
            }
        }
    }
}
$all_stop_labels = array_unique(array_keys($all_stop_labels));

// Sub-column labels for each group (same 12 as fees-structure-report)
$sub_cols = [
    'Total', 'Head Discount', 'Final', 'Fine', 'Gross Total',
    'Fees Discount', 'Fine Discount', 'Total Discount',
    'Paid Fees', 'Paid Fine', 'Total Paid', 'Balance'
];

// ── Table 1: Summary by route / stop ─────────────────────────────────────────
// Aggregate per route (using sfi.route_details which stores "Route X - stop - Y")
$where_params = [':sid' => $school_id];
$where_extra  = '';
if ($search !== '') {
    $where_extra .= " AND (r.route_name LIKE :search OR sfi.route_details LIKE :search2)";
    $where_params[':search']  = "%$search%";
    $where_params[':search2'] = "%$search%";
}

// Count distinct routes for pagination
$stmt_cnt = $pdo->prepare("
    SELECT COUNT(DISTINCT r.id)
    FROM transport_routes r
    LEFT JOIN students s ON s.transport_route_id = r.id AND s.deleted_at IS NULL
    LEFT JOIN student_fee_items sfi ON sfi.student_id = s.id AND sfi.fee_name = 'Transport Fees' AND sfi.is_active = 1
    WHERE r.school_id = :sid AND r.deleted_at IS NULL
    $where_extra
");
$stmt_cnt->execute($where_params);
$total_records = (int)$stmt_cnt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $limit));

// Aggregate per route
$stmt_summary = $pdo->prepare("
    SELECT
        r.id                                                                         AS route_id,
        r.route_name,
        r.route_structure,
        COUNT(DISTINCT s.id)                                                         AS student_count,
        COALESCE(SUM(sfi.amount), 0)                                                 AS total_fees,
        COALESCE(SUM(sfi.discount_amount), 0)                                        AS head_discount,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)               AS final_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)               AS gross_total,
        COALESCE(SUM(sfi.discount_amount), 0)                                        AS total_discount,
        COALESCE(SUM(sfi.paid_amount), 0)                                            AS paid_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)), 0) AS balance
    FROM transport_routes r
    LEFT JOIN students s ON s.transport_route_id = r.id AND s.deleted_at IS NULL
    LEFT JOIN student_fee_items sfi ON sfi.student_id = s.id AND sfi.fee_name = 'Transport Fees' AND sfi.is_active = 1
    WHERE r.school_id = :sid AND r.deleted_at IS NULL
    $where_extra
    GROUP BY r.id, r.route_name, r.route_structure
    ORDER BY r.id ASC
    LIMIT :lim OFFSET :off
");
foreach ($where_params as $k => $v) $stmt_summary->bindValue($k, $v);
$stmt_summary->bindValue(':lim', $limit,  PDO::PARAM_INT);
$stmt_summary->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt_summary->execute();
$summary_rows = $stmt_summary->fetchAll();

// Grand totals for Table 1 footer
$stmt_totals = $pdo->prepare("
    SELECT
        COALESCE(SUM(sfi.amount), 0)                                                   AS total_fees,
        COALESCE(SUM(sfi.discount_amount), 0)                                          AS head_discount,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                 AS final_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                 AS gross_total,
        COALESCE(SUM(sfi.discount_amount), 0)                                          AS total_discount,
        COALESCE(SUM(sfi.paid_amount), 0)                                              AS paid_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)), 0) AS balance
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1 AND sfi.fee_name = 'Transport Fees'
");
$stmt_totals->execute([':sid' => $school_id]);
$totals = $stmt_totals->fetch();

// ── Table 2: Per-class aggregates broken by route ─────────────────────────────
$stmt_class_data = $pdo->prepare("
    SELECT
        c.name                                                                        AS class_name,
        r.route_name,
        r.route_structure,
        COALESCE(SUM(sfi.amount), 0)                                                  AS total_fees,
        COALESCE(SUM(sfi.discount_amount), 0)                                         AS head_discount,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                AS final_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                AS gross_total,
        COALESCE(SUM(sfi.discount_amount), 0)                                         AS total_discount,
        COALESCE(SUM(sfi.paid_amount), 0)                                             AS paid_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)), 0) AS balance
    FROM transport_routes r
    JOIN students s ON s.transport_route_id = r.id AND s.deleted_at IS NULL
    JOIN student_fee_items sfi ON sfi.student_id = s.id AND sfi.fee_name = 'Transport Fees' AND sfi.is_active = 1
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE r.school_id = :sid AND r.deleted_at IS NULL
    GROUP BY c.id, c.name, r.id, r.route_name, r.route_structure
    ORDER BY c.name ASC, r.route_name ASC
");
$stmt_class_data->execute([':sid' => $school_id]);
$class_raw = $stmt_class_data->fetchAll();

// Restructure: class_name => [ route_name => data ]
$class_data   = [];
$route_names  = [];
foreach ($class_raw as $row) {
    $cn = $row['class_name'] ?? '(No Class)';
    $rn = $row['route_name'];
    $class_data[$cn][$rn] = $row;
    $route_names[$rn]     = true;
}
$route_names = array_keys($route_names);

// build 12-value array for table 2 cells
function tr_build12(array $d): array {
    $total  = (float)($d['total_fees']    ?? 0);
    $hd     = (float)($d['head_discount'] ?? 0);
    $final  = (float)($d['final_fees']    ?? 0);
    $gross  = (float)($d['gross_total']   ?? 0);
    $paid   = (float)($d['paid_fees']     ?? 0);
    $bal    = $gross - $paid;
    return [$total, $hd, $final, 0, $gross, 0, 0, $hd, $paid, 0, $paid, $bal];
}

// ── Per-stop breakdown data for Table 3 (bottom section) ─────────────────────
// For each route, list each stop with its per-student fee amounts
// We aggregate student_fee_items by route_details field
$stmt_stops = $pdo->prepare("
    SELECT
        r.route_name,
        sfi.route_details                                                             AS stop_label,
        COUNT(DISTINCT sfi.student_id)                                                AS student_count,
        COALESCE(SUM(sfi.amount), 0)                                                  AS total_fees,
        COALESCE(SUM(sfi.discount_amount), 0)                                         AS head_discount,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0)), 0)                AS final_fees,
        COALESCE(SUM(sfi.paid_amount), 0)                                             AS paid_fees,
        COALESCE(SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)), 0) AS balance
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    JOIN transport_routes r ON s.transport_route_id = r.id
    WHERE s.school_id = :sid
      AND s.deleted_at IS NULL
      AND sfi.fee_name = 'Transport Fees'
      AND sfi.is_active = 1
      AND r.deleted_at IS NULL
    GROUP BY r.id, r.route_name, sfi.route_details
    ORDER BY r.route_name ASC, sfi.route_details ASC
");
$stmt_stops->execute([':sid' => $school_id]);
$stops_raw = $stmt_stops->fetchAll();

// Restructure: route_name => [ stops[] ]
$stops_data = [];
foreach ($stops_raw as $row) {
    $stops_data[$row['route_name']][] = $row;
}

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Transport Fees Structure Report</h2>
    </div>
</div>

<!-- ── Banner Stat Badges ─────────────────────────────────────────────────────── -->
<div class="row mb-3 g-2">
    <div class="col-12">
        <div class="fsr-banner-wrap">
            <span class="fsr-badge fsr-badge-blue">Gross Fees: <?php echo $b('gross_fees'); ?></span>
            <span class="fsr-badge fsr-badge-purple">Discount Head Amount (<?php echo $b('head_discount'); ?>)</span>
            <span class="fsr-badge fsr-badge-green">Final Fees: (<?php echo $b('final_fees'); ?>)</span>
            <span class="fsr-badge fsr-badge-red">Fine Amount (0)</span>
            <span class="fsr-badge fsr-badge-teal">Gross Total: <?php echo $b('gross_total'); ?></span>
            <span class="fsr-badge fsr-badge-orange">Fees Discount: 0</span>
            <span class="fsr-badge fsr-badge-pink">Fine Discount: 0</span>
            <span class="fsr-badge fsr-badge-indigo">Total Discount: <?php echo $b('total_discount'); ?></span>
        </div>
    </div>
</div>

<!-- ── Table 1: Transport route summary ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">

            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom:0;">
                <div class="fee-toolbar-left">
                    <span class="fw-bold text-sm font-heading" style="color:var(--color-text-primary);">Transport Fees Structure Summary</span>
                </div>
                <div class="fee-toolbar-right">
                    <form method="GET" action="transport-structure-report.php" class="d-flex align-items-center gap-2" id="filterForm">
                        <div class="fee-search-container">
                            <i class="ph-light ph-magnifying-glass text-muted"></i>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search route…" class="fee-search-input font-secondary">
                            <button type="submit" class="fee-search-btn"><i class="ph-light ph-magnifying-glass"></i></button>
                        </div>
                        <select name="limit" class="form-control-admin font-secondary text-secondary" style="width:75px; height:38px;" onchange="document.getElementById('filterForm').submit()">
                            <option value="10"  <?php echo $limit == 10  ? 'selected' : ''; ?>>10</option>
                            <option value="20"  <?php echo $limit == 20  ? 'selected' : ''; ?>>20</option>
                            <option value="50"  <?php echo $limit == 50  ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </form>
                    <button class="teacher-header-btn btn-sky" title="Print" onclick="window.print()"><i class="ph-light ph-printer"></i></button>
                    <button class="teacher-header-btn" title="Export CSV" id="exportCsvBtn" style="background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text-primary);"><i class="ph-light ph-file-csv"></i></button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($summary_rows)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3"><i class="ph-light ph-bus"></i></div>
                            <h5 class="fw-bold mt-3 mb-1 font-heading">No transport fee data found</h5>
                            <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Create transport routes and assign students first.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle fsr-table" id="summaryTable">
                            <thead>
                                <tr>
                                    <th>Route Name</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-end">Total Fees</th>
                                    <th class="text-end">Discount Head</th>
                                    <th class="text-end">Final Fees</th>
                                    <th class="text-end">Fine</th>
                                    <th class="text-end">Gross Total</th>
                                    <th class="text-end">Fees Discount</th>
                                    <th class="text-end">Fine Discount</th>
                                    <th class="text-end">Total Discount</th>
                                    <th class="text-end">Paid Fees</th>
                                    <th class="text-end">Paid Fine</th>
                                    <th class="text-end">Total Paid</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary_rows as $row):
                                    $balance   = (float)$row['balance'];
                                    $bal_class = $balance > 0 ? 'fsr-balance-due' : 'fsr-balance-ok';

                                    // Parse stops for route structure display
                                    $stops_decoded = json_decode($row['route_structure'], true);
                                    $stops_text    = [];
                                    if (is_array($stops_decoded)) {
                                        foreach ($stops_decoded as $st) {
                                            $stops_text[] = sanitize(($st['starting_from'] ?? '') . ' – ' . ($st['stop_to'] ?? '') . ' (₹' . number_format((float)($st['fees'] ?? 0), 0) . ')');
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold font-heading text-primary" style="font-size:13.5px;"><?php echo sanitize($row['route_name']); ?></span>
                                            <?php if (!empty($stops_text)): ?>
                                                <div class="text-xxs text-secondary font-secondary mt-1" style="line-height:1.5;">
                                                    <?php echo implode('<br>', $stops_text); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-secondary fw-bold"><?php echo (int)$row['student_count']; ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['total_fees'],    0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['head_discount'], 0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['final_fees'],    0); ?></td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['gross_total'],   0); ?></td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['total_discount'],0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['paid_fees'],     0); ?></td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['paid_fees'],     0); ?></td>
                                        <td class="text-end font-secondary <?php echo $bal_class; ?>"><?php echo number_format($balance, 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fsr-total-row">
                                    <td class="fw-bold" colspan="2">Total</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['total_fees'],    0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['head_discount'], 0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['final_fees'],    0); ?></td>
                                    <td class="text-end fw-bold">0</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['gross_total'],   0); ?></td>
                                    <td class="text-end fw-bold fsr-zero">0</td>
                                    <td class="text-end fw-bold fsr-zero">0</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['total_discount'],0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['paid_fees'],     0); ?></td>
                                    <td class="text-end fw-bold">0</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['paid_fees'],     0); ?></td>
                                    <td class="text-end fw-bold <?php echo (float)$totals['balance'] > 0 ? 'fsr-balance-due' : 'fsr-balance-ok'; ?>"><?php echo number_format((float)$totals['balance'], 0); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                        <span class="cell-counter font-secondary">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</span>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page_num <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link font-secondary" href="?page=<?php echo $page_num - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i === $page_num) ? 'active' : ''; ?>">
                                        <a class="page-link font-secondary" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page_num >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link font-secondary" href="?page=<?php echo $page_num + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Table 2: Transport fees by classes ────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">

            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom:0;">
                <div class="fee-toolbar-left">
                    <span class="fw-bold text-sm font-heading" style="color:var(--color-text-primary);">Transport Fees Structure Summary by Classes</span>
                </div>
                <div class="fee-toolbar-right">
                    <button class="teacher-header-btn btn-sky" title="Print" onclick="window.print()"><i class="ph-light ph-printer"></i></button>
                    <button class="teacher-header-btn" title="Export CSV" id="exportClassCsvBtn" style="background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text-primary);"><i class="ph-light ph-file-csv"></i></button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($class_data)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3"><i class="ph-light ph-graduation-cap"></i></div>
                            <h5 class="fw-bold mt-3 mb-1 font-heading">No class transport data found</h5>
                            <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Assign students with transport routes to classes.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle fsr-table" id="classTable">
                            <thead>
                                <!-- Row 1: group label headers -->
                                <tr class="fsr-thead-row1">
                                    <th rowspan="2" class="fsr-th-fees-types">FEES TYPES</th>

                                    <!-- Session aggregate group -->
                                    <th colspan="12" class="text-center fsr-th-group fsr-th-group-session">
                                        <?php echo htmlspecialchars($current_session ?: date('Y').'-'.(date('Y')+1)); ?> due fees
                                    </th>

                                    <!-- One group per route -->
                                    <?php foreach ($route_names as $rn): ?>
                                        <th colspan="12" class="text-center fsr-th-group">
                                            <?php echo sanitize($rn); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>

                                <!-- Row 2: 12 sub-column labels per group -->
                                <tr class="fsr-thead-row2">
                                    <?php
                                    $groups = 1 + count($route_names);
                                    for ($g = 0; $g < $groups; $g++):
                                        foreach ($sub_cols as $sc):
                                    ?>
                                        <th class="text-center fsr-th-sub"><?php echo $sc; ?></th>
                                    <?php
                                        endforeach;
                                    endfor;
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $grand_sess  = array_fill(0, 12, 0);
                                $grand_types = [];
                                foreach ($route_names as $rn) $grand_types[$rn] = array_fill(0, 12, 0);

                                foreach ($class_data as $cname => $route_map):
                                    // Session group: sum all routes for this class
                                    $s = array_fill(0, 12, 0);
                                    foreach ($route_map as $d) {
                                        $v = tr_build12($d);
                                        foreach ($v as $i => $val) $s[$i] += $val;
                                    }
                                    foreach ($s as $i => $val) $grand_sess[$i] += $val;
                                ?>
                                    <tr>
                                        <td class="fw-bold font-secondary fsr-class-name"><?php echo sanitize($cname); ?></td>

                                        <!-- Session aggregate 12 cells -->
                                        <?php foreach ($s as $i => $v):
                                            $is_bal = ($i === 11);
                                            $bc     = $is_bal ? ($v > 0 ? ' fsr-balance-due' : ' fsr-balance-ok') : '';
                                            $dim    = (!$v && !$is_bal) ? ' fsr-zero' : '';
                                        ?>
                                            <td class="text-center font-secondary fsr-class-cell<?php echo $bc.$dim; ?>">
                                                <?php echo number_format($v, 0); ?>
                                            </td>
                                        <?php endforeach; ?>

                                        <!-- Per-route 12 cells each -->
                                        <?php foreach ($route_names as $rn):
                                            $d  = $route_map[$rn] ?? null;
                                            $fv = $d ? tr_build12($d) : array_fill(0, 12, 0);
                                            foreach ($fv as $i => $val) $grand_types[$rn][$i] += $val;
                                            foreach ($fv as $i => $v):
                                                $is_bal = ($i === 11);
                                                $bc     = $is_bal ? ($v > 0 ? ' fsr-balance-due' : ' fsr-balance-ok') : '';
                                                $dim    = (!$v && !$is_bal) ? ' fsr-zero' : '';
                                        ?>
                                                <td class="text-center font-secondary fsr-class-cell<?php echo $bc.$dim; ?>">
                                                    <?php echo number_format($v, 0); ?>
                                                </td>
                                        <?php   endforeach;
                                        endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fsr-total-row">
                                    <td class="fw-bold">Total</td>
                                    <?php foreach ($grand_sess as $i => $v):
                                        $is_bal = ($i === 11);
                                        $bc     = $is_bal ? ($v > 0 ? ' fsr-balance-due' : ' fsr-balance-ok') : '';
                                    ?>
                                        <td class="text-center fw-bold<?php echo $bc; ?>"><?php echo number_format($v, 0); ?></td>
                                    <?php endforeach; ?>
                                    <?php foreach ($route_names as $rn):
                                        foreach ($grand_types[$rn] as $i => $v):
                                            $is_bal = ($i === 11);
                                            $bc     = $is_bal ? ($v > 0 ? ' fsr-balance-due' : ' fsr-balance-ok') : '';
                                    ?>
                                            <td class="text-center fw-bold<?php echo $bc; ?>"><?php echo number_format($v, 0); ?></td>
                                    <?php    endforeach;
                                    endforeach; ?>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Table 3: Per-stop breakdown for each route ─────────────────────────────── -->
<?php if (!empty($stops_data)): ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">

            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom:0;">
                <div class="fee-toolbar-left">
                    <span class="fw-bold text-sm font-heading" style="color:var(--color-text-primary);">Transport Fees – Stop-wise Breakdown</span>
                </div>
                <div class="fee-toolbar-right">
                    <button class="teacher-header-btn btn-sky" title="Print" onclick="window.print()"><i class="ph-light ph-printer"></i></button>
                    <button class="teacher-header-btn" title="Export CSV" id="exportStopCsvBtn" style="background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text-primary);"><i class="ph-light ph-file-csv"></i></button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="teacher-table table-premium mb-0 align-middle fsr-table" id="stopTable">
                        <thead>
                            <tr>
                                <th>Route / Stop</th>
                                <th class="text-center">Students</th>
                                <th class="text-end">Total Fees</th>
                                <th class="text-end">Head Discount</th>
                                <th class="text-end">Final Fees</th>
                                <th class="text-end">Paid Fees</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stop_grand = ['student_count'=>0,'total_fees'=>0,'head_discount'=>0,'final_fees'=>0,'paid_fees'=>0,'balance'=>0];
                            foreach ($stops_data as $rname => $stops):
                                // Route header row
                                $rt_total = array_fill_keys(['student_count','total_fees','head_discount','final_fees','paid_fees','balance'], 0);
                                foreach ($stops as $st) {
                                    foreach (['student_count','total_fees','head_discount','final_fees','paid_fees','balance'] as $k) {
                                        $rt_total[$k] += (float)$st[$k];
                                    }
                                }
                            ?>
                                <!-- Route subtotal row (acts as group header) -->
                                <tr style="background: var(--gray-50);">
                                    <td class="fw-bold font-heading text-primary" colspan="1" style="font-size:13px; padding-left:14px;">
                                        <i class="ph-light ph-bus me-1"></i><?php echo sanitize($rname); ?>
                                    </td>
                                    <td class="text-center fw-bold font-secondary"><?php echo (int)$rt_total['student_count']; ?></td>
                                    <td class="text-end fw-bold font-secondary"><?php echo number_format($rt_total['total_fees'],    0); ?></td>
                                    <td class="text-end fw-bold font-secondary"><?php echo number_format($rt_total['head_discount'], 0); ?></td>
                                    <td class="text-end fw-bold font-secondary"><?php echo number_format($rt_total['final_fees'],    0); ?></td>
                                    <td class="text-end fw-bold font-secondary"><?php echo number_format($rt_total['paid_fees'],     0); ?></td>
                                    <td class="text-end fw-bold font-secondary <?php echo (float)$rt_total['balance'] > 0 ? 'fsr-balance-due' : 'fsr-balance-ok'; ?>">
                                        <?php echo number_format((float)$rt_total['balance'], 0); ?>
                                    </td>
                                </tr>

                                <?php foreach ($stops as $st):
                                    $sl = $st['stop_label'] ?: '—';
                                    foreach (['student_count','total_fees','head_discount','final_fees','paid_fees','balance'] as $k) {
                                        $stop_grand[$k] += (float)$st[$k];
                                    }
                                ?>
                                    <tr>
                                        <td class="font-secondary text-sm" style="padding-left:32px; color:var(--color-text-secondary);">
                                            <i class="ph-light ph-map-pin me-1" style="font-size:11px;"></i><?php echo sanitize($sl); ?>
                                        </td>
                                        <td class="text-center font-secondary"><?php echo (int)$st['student_count']; ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$st['total_fees'],    0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$st['head_discount'], 0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$st['final_fees'],    0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$st['paid_fees'],     0); ?></td>
                                        <td class="text-end font-secondary <?php echo (float)$st['balance'] > 0 ? 'fsr-balance-due' : 'fsr-balance-ok'; ?>">
                                            <?php echo number_format((float)$st['balance'], 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fsr-total-row">
                                <td class="fw-bold">Grand Total</td>
                                <td class="text-center fw-bold"><?php echo (int)$stop_grand['student_count']; ?></td>
                                <td class="text-end fw-bold"><?php echo number_format((float)$stop_grand['total_fees'],    0); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format((float)$stop_grand['head_discount'], 0); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format((float)$stop_grand['final_fees'],    0); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format((float)$stop_grand['paid_fees'],     0); ?></td>
                                <td class="text-end fw-bold <?php echo (float)$stop_grand['balance'] > 0 ? 'fsr-balance-due' : 'fsr-balance-ok'; ?>">
                                    <?php echo number_format((float)$stop_grand['balance'], 0); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── CSV Export helper (reused from fees-structure-report pattern) ─────────────
function exportTableToCsv(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    let csv = [];
    for (const row of table.rows) {
        let cols = [];
        for (const cell of row.cells) {
            let text = cell.innerText.replace(/"/g, '""').replace(/\n/g, ' ').trim();
            cols.push('"' + text + '"');
        }
        csv.push(cols.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', function () {
    const exportBtn = document.getElementById('exportCsvBtn');
    if (exportBtn) exportBtn.addEventListener('click', function () {
        exportTableToCsv('summaryTable', 'transport_fees_summary.csv');
    });

    const exportClassBtn = document.getElementById('exportClassCsvBtn');
    if (exportClassBtn) exportClassBtn.addEventListener('click', function () {
        exportTableToCsv('classTable', 'transport_fees_by_class.csv');
    });

    const exportStopBtn = document.getElementById('exportStopCsvBtn');
    if (exportStopBtn) exportStopBtn.addEventListener('click', function () {
        exportTableToCsv('stopTable', 'transport_fees_stop_breakdown.csv');
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
