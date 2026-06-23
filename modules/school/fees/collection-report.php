<?php
// modules/school/fees/collection-report.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// ── Summary Stats ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id=:s AND deleted_at IS NULL AND total_fees > 0");
$stmt->execute([':s' => $school_id]);
$cnt_fees_created = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id=:s AND deleted_at IS NULL AND (total_fees IS NULL OR total_fees=0)");
$stmt->execute([':s' => $school_id]);
$cnt_fees_not = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id=:s AND deleted_at IS NULL AND total_fees > 0 AND total_paid >= total_fees");
$stmt->execute([':s' => $school_id]);
$cnt_paid = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id=:s AND deleted_at IS NULL AND total_fees > 0 AND (total_paid IS NULL OR total_paid < total_fees)");
$stmt->execute([':s' => $school_id]);
$cnt_unpaid = (int)$stmt->fetchColumn();

// ── Today ─────────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT HOUR(payment_date) hr, SUM(amount_paid) total FROM fee_payments WHERE school_id=:s AND DATE(payment_date)=:d GROUP BY HOUR(payment_date) ORDER BY hr ASC");
$stmt->execute([':s' => $school_id, ':d' => $today]);
$today_rows  = $stmt->fetchAll();
$today_total = (float)array_sum(array_column($today_rows, 'total'));
$today_labels = array_map(fn($r) => (int)$r['hr'],    $today_rows);
$today_data   = array_map(fn($r) => (float)$r['total'], $today_rows);

// ── Weekly ────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT DAY(payment_date) d, SUM(amount_paid) total FROM fee_payments WHERE school_id=:s AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(payment_date) ORDER BY DATE(payment_date) ASC");
$stmt->execute([':s' => $school_id]);
$week_rows    = $stmt->fetchAll();
$weekly_total = (float)array_sum(array_column($week_rows, 'total'));
$week_labels  = array_map(fn($r) => (int)$r['d'],    $week_rows);
$week_data    = array_map(fn($r) => (float)$r['total'], $week_rows);

// ── Monthly ───────────────────────────────────────────────────────────────────
$sel_month = (int)($_GET['month'] ?? date('n'));
$sel_year  = (int)($_GET['year']  ?? date('Y'));
$stmt = $pdo->prepare("SELECT DAY(payment_date) d, SUM(amount_paid) total FROM fee_payments WHERE school_id=:s AND MONTH(payment_date)=:m AND YEAR(payment_date)=:y GROUP BY DAY(payment_date) ORDER BY d ASC");
$stmt->execute([':s' => $school_id, ':m' => $sel_month, ':y' => $sel_year]);
$mon_rows     = $stmt->fetchAll();
$monthly_total = (float)array_sum(array_column($mon_rows, 'total'));
$mon_labels   = array_map(fn($r) => (int)$r['d'],    $mon_rows);
$mon_data     = array_map(fn($r) => (float)$r['total'], $mon_rows);

// ── Receipts table ────────────────────────────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$limit    = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? intval($_GET['limit']) : 20;
$page_num = (isset($_GET['page'])  && is_numeric($_GET['page']))  ? intval($_GET['page'])  : 1;
$offset   = ($page_num - 1) * $limit;

$where  = "WHERE fp.school_id = :school_id";
$params = [':school_id' => $school_id];
if ($search) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.admission_no LIKE :search OR fp.transaction_id LIKE :search)";
    $params[':search'] = "%$search%";
}

$stmt_c = $pdo->prepare("SELECT COUNT(*) FROM fee_payments fp JOIN students s ON fp.student_id=s.id $where");
$stmt_c->execute($params);
$total_records = (int)$stmt_c->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $limit));

$sql = "SELECT fp.id receipt_no, fp.payment_date, fp.amount_paid, fp.transaction_id, fp.fine_amount,
               s.first_name, s.last_name, s.admission_no, s.admission_no_prefix, s.father_name,
               c.name class_name, sec.name section_name
        FROM fee_payments fp
        JOIN students s ON fp.student_id=s.id
        LEFT JOIN classes  c   ON s.class_id   = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        $where ORDER BY fp.payment_date DESC, fp.id DESC LIMIT :lim OFFSET :off";
$stmt_d = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt_d->bindValue($k, $v);
$stmt_d->bindValue(':lim', $limit,  PDO::PARAM_INT);
$stmt_d->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt_d->execute();
$receipts = $stmt_d->fetchAll();

$months_list = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$year_from   = date('Y') - 1;
$year_to     = date('Y') + 1;

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Fees Collection Report</h2>
    </div>
</div>

<!-- ── Stat Cards ────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4 row-cols-2 row-cols-md-3 row-cols-lg-5">

    <div class="col">
        <div class="card-premium p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle-lg activity-icon-blue">
                    <i class="ph-light ph-file-text"></i>
                </div>
                <div>
                    <span class="form-label-admin mb-1 font-secondary">Fees structure created</span>
                    <h3 class="fw-extrabold font-secondary mb-0"><?php echo number_format($cnt_fees_created); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card-premium p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle-lg activity-icon-red">
                    <i class="ph-light ph-file-x"></i>
                </div>
                <div>
                    <span class="form-label-admin mb-1 font-secondary">Fees structure not created</span>
                    <h3 class="fw-extrabold font-secondary mb-0"><?php echo number_format($cnt_fees_not); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card-premium p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle-lg" style="width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;background:#d1fae5;color:#059669;">
                    <i class="ph-light ph-user-check"></i>
                </div>
                <div>
                    <span class="form-label-admin mb-1 font-secondary">Paid students</span>
                    <h3 class="fw-extrabold font-secondary mb-0"><?php echo number_format($cnt_paid); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card-premium p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle-lg activity-icon-red">
                    <i class="ph-light ph-user-minus"></i>
                </div>
                <div>
                    <span class="form-label-admin mb-1 font-secondary">Unpaid students</span>
                    <h3 class="fw-extrabold font-secondary mb-0"><?php echo number_format($cnt_unpaid); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card-premium p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle-lg" style="width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;background:var(--color-primary-light);color:var(--color-secondary);">
                    <i class="ph-light ph-device-mobile"></i>
                </div>
                <div>
                    <span class="form-label-admin mb-1 font-secondary">App installed by</span>
                    <h3 class="fw-extrabold font-secondary mb-0">—</h3>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Today + Weekly Charts ──────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">

    <div class="col-lg-6">
        <div class="card-premium">
            <div class="card-header">
                <div>
                    <h6 class="fw-bold mb-0 font-heading">Today Fees Collection</h6>
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">Click on the bar to view detail.</span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-xs fw-bold mb-3 font-secondary" style="color:var(--danger);">Total Amount: Rs. <?php echo number_format($today_total, 0); ?></p>
                <canvas id="todayChart" style="width:100%;height:180px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-premium">
            <div class="card-header">
                <div>
                    <h6 class="fw-bold mb-0 font-heading">Weekly Fees Collection</h6>
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">Click on the bar to view detail.</span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-xs fw-bold mb-3 font-secondary" style="color:var(--danger);">Total Amount: Rs. <?php echo number_format($weekly_total, 0); ?></p>
                <canvas id="weeklyChart" style="width:100%;height:180px;"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- ── Monthly Chart ──────────────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card-premium">
            <div class="card-header">
                <div>
                    <h6 class="fw-bold mb-0 font-heading">Monthly Fees</h6>
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">Click on the bar to view detail.</span>
                </div>
                <form method="GET" action="collection-report.php" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="limit"  value="<?php echo $limit; ?>">
                    <select name="month" class="form-control-admin font-secondary text-secondary" style="width:78px;" onchange="this.form.submit()">
                        <?php foreach ($months_list as $mi => $mn): $v = $mi + 1; ?>
                            <option value="<?php echo $v; ?>" <?php echo $sel_month === $v ? 'selected' : ''; ?>><?php echo $mn; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="year" class="form-control-admin font-secondary text-secondary" style="width:108px;" onchange="this.form.submit()">
                        <?php for ($y = $year_from; $y <= $year_to; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $sel_year === $y ? 'selected' : ''; ?>><?php echo ($y-1).' - '.$y; ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <p class="text-xs fw-bold mb-3 font-secondary" style="color:var(--danger);">Total Amount: Rs. <?php echo number_format($monthly_total, 0); ?></p>
                <canvas id="monthlyChart" style="width:100%;height:200px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Receipts Table Toolbar ─────────────────────────────────────────────────── -->
<div class="row mb-3 g-3">
    <div class="col-12">
        <div class="fee-toolbar">
            <div class="fee-toolbar-left">
                <span class="fw-bold text-sm font-heading" style="color:var(--color-text-primary);">Fees collection report by structure</span>
            </div>
            <div class="fee-toolbar-right">
                <form method="GET" action="collection-report.php" class="d-flex align-items-center gap-2" id="toolbarForm">
                    <input type="hidden" name="month" value="<?php echo $sel_month; ?>">
                    <input type="hidden" name="year"  value="<?php echo $sel_year; ?>">
                    
                    <!-- Search Input -->
                    <div class="fee-search-container">
                        <i class="ph-light ph-magnifying-glass text-muted"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student, receipt…" class="fee-search-input font-secondary">
                        <button type="submit" class="fee-search-btn">
                            <i class="ph-light ph-magnifying-glass"></i>
                        </button>
                    </div>

                    <!-- Limit Dropdown -->
                    <select name="limit" class="form-control-admin font-secondary text-secondary" style="width:75px; height:38px;" onchange="document.getElementById('toolbarForm').submit()">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </form>

                <div class="fee-total-badge font-secondary">
                    <i class="ph-light ph-users"></i>
                    Total Students: <span class="count-num font-secondary"><?php echo $total_records; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Table Card ────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($receipts)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-receipt"></i>
                            </div>
                            <h5 class="fw-bold mt-3 mb-1 font-heading">No receipts found</h5>
                            <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Collect fees first or adjust your search filter.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle" id="collectionTable">
                            <thead>
                                <tr>
                                    <th>Receipt No.</th>
                                    <th>Date</th>
                                    <th>Admission No.</th>
                                    <th>Student Name</th>
                                    <th>Classes</th>
                                    <th>Sections</th>
                                    <th>Father Name</th>
                                    <th><?php echo (date('Y')-1).'-'.date('Y'); ?> due fees</th>
                                    <th><?php echo (date('Y')-1).'-'.date('Y'); ?> due fees - Fine</th>
                                    <th>Admission Fee</th>
                                    <th>Admission Fee - Fine</th>
                                    <th>Old</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $r): ?>
                                    <tr>
                                        <td>
                                            <label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer;">
                                                <input type="checkbox" class="table-checkbox">
                                                <span class="fw-bold text-xs font-secondary" style="color:var(--color-accent);">
                                                    <?php echo sanitize($r['transaction_id'] ?: $r['receipt_no']); ?>
                                                </span>
                                            </label>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-sm font-secondary" style="color:var(--color-text-primary);">
                                                <?php echo date('d M, Y', strtotime($r['payment_date'])); ?>
                                            </span><br>
                                            <span class="cell-counter font-secondary"><?php echo date('h:i:sa', strtotime($r['payment_date'])); ?></span>
                                        </td>
                                        <td class="font-secondary"><?php echo sanitize($r['admission_no'] ? ($r['admission_no_prefix'].$r['admission_no']) : '—'); ?></td>
                                        <td class="fw-bold font-secondary" style="color:var(--color-text-primary);"><?php echo sanitize($r['first_name'].' '.$r['last_name']); ?></td>
                                        <td>
                                            <?php if (!empty($r['class_name'])): ?>
                                                <span class="teacher-username font-secondary"><?php echo sanitize($r['class_name']); ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td class="font-secondary"><?php echo sanitize($r['section_name'] ?? '—'); ?></td>
                                        <td class="font-secondary"><?php echo sanitize($r['father_name'] ?: '—'); ?></td>
                                        <td class="font-secondary"><?php echo number_format($r['amount_paid'], 0); ?></td>
                                        <td class="font-secondary"><?php echo number_format((float)($r['fine_amount'] ?? 0), 0); ?></td>
                                        <td class="fw-bold font-secondary" style="color:var(--color-accent);">0</td>
                                        <td class="fw-bold font-secondary" style="color:var(--color-accent);">0</td>
                                        <td class="font-secondary">0</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                        <span class="cell-counter font-secondary">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</span>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page_num <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link font-secondary" href="?page=<?php echo $page_num-1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&month=<?php echo $sel_month; ?>&year=<?php echo $sel_year; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i === $page_num) ? 'active' : ''; ?>">
                                        <a class="page-link font-secondary" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&month=<?php echo $sel_month; ?>&year=<?php echo $sel_year; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page_num >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link font-secondary" href="?page=<?php echo $page_num+1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&month=<?php echo $sel_month; ?>&year=<?php echo $sel_year; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function makeAreaChart(id, labels, data) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,0.12)',
                borderWidth: 2,
                pointRadius: data.length < 6 ? 5 : 3,
                pointBackgroundColor: '#22c55e',
                fill: true,
                tension: 0.42
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: { label: c => 'Rs. ' + c.parsed.y.toLocaleString('en-IN') }
                }
            },
            scales: {
                x: { 
                    grid: { color: 'rgba(0,0,0,.04)' }, 
                    ticks: { color: '#94a3b8', font: { family: 'Poppins', size: 11 } } 
                },
                y: { 
                    grid: { color: 'rgba(0,0,0,.04)' }, 
                    ticks: { 
                        color: '#94a3b8', 
                        font: { family: 'Poppins', size: 11 },
                        callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v 
                    } 
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    makeAreaChart('todayChart',   <?php echo json_encode($today_labels); ?>, <?php echo json_encode($today_data); ?>);
    makeAreaChart('weeklyChart',  <?php echo json_encode($week_labels); ?>,  <?php echo json_encode($week_data); ?>);
    makeAreaChart('monthlyChart', <?php echo json_encode($mon_labels); ?>,   <?php echo json_encode($mon_data); ?>);
});
</script>

<?php require_once '../../../includes/footer.php'; ?>

