<?php
// modules/school/fees/online-payments.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// ── Ensure the table exists (safe idempotent migration) ──────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `online_fee_payments` (
        `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        `school_id`      INT UNSIGNED     NOT NULL,
        `student_id`     INT UNSIGNED     NOT NULL,
        `order_id`       VARCHAR(120)     NOT NULL DEFAULT '',
        `payment_id`     VARCHAR(120)     NOT NULL DEFAULT '',
        `payment_method` VARCHAR(80)      NOT NULL DEFAULT '',
        `amount_types`   VARCHAR(255)     NOT NULL DEFAULT '',
        `amount`         DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
        `status`         ENUM('Success','Failed','Incomplete') NOT NULL DEFAULT 'Incomplete',
        `payment_date`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_school_id` (`school_id`),
        KEY `idx_student_id` (`student_id`),
        KEY `idx_status`    (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Filters ──────────────────────────────────────────────────────────────────
$search         = trim($_GET['search']         ?? '');
$filter_status  = trim($_GET['filter_status']  ?? '');
$filter_method  = trim($_GET['filter_method']  ?? '');
$start_date     = trim($_GET['start_date']     ?? '');
$end_date       = trim($_GET['end_date']       ?? '');

// ── Pagination ───────────────────────────────────────────────────────────────
$limit  = isset($_GET['limit'])  && is_numeric($_GET['limit'])  ? intval($_GET['limit'])  : 20;
$page   = isset($_GET['page'])   && is_numeric($_GET['page'])   ? intval($_GET['page'])   : 1;
$offset = ($page - 1) * $limit;

// ── WHERE builder ────────────────────────────────────────────────────────────
$where  = "WHERE op.school_id = :school_id";
$params = [':school_id' => $school_id];

if ($search !== '') {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search
                     OR s.admission_no LIKE :search OR op.order_id LIKE :search
                     OR op.payment_id LIKE :search OR s.father_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filter_status !== '') {
    $where .= " AND op.status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_method !== '') {
    $where .= " AND op.payment_method = :method";
    $params[':method'] = $filter_method;
}
if ($start_date !== '') {
    $where .= " AND op.payment_date >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
}
if ($end_date !== '') {
    $where .= " AND op.payment_date <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
}

// ── Aggregate stats (always for whole school, ignore pagination filters) ─────
$stmt_stats = $pdo->prepare("
    SELECT
        COUNT(*) AS total_transactions,
        COALESCE(SUM(amount), 0) AS total_amount,
        SUM(status = 'Success')    AS total_success,
        SUM(status = 'Failed')     AS total_failed,
        SUM(status = 'Incomplete') AS total_incomplete
    FROM online_fee_payments
    WHERE school_id = :school_id
");
$stmt_stats->execute([':school_id' => $school_id]);
$stats = $stmt_stats->fetch();

// ── Count for pagination ──────────────────────────────────────────────────────
$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM online_fee_payments op
    JOIN students s ON op.student_id = s.id
    $where
");
$stmt_count->execute($params);
$total_records = (int)$stmt_count->fetchColumn();
$total_pages   = $limit > 0 ? (int)ceil($total_records / $limit) : 1;

// ── Paginated data ────────────────────────────────────────────────────────────
$sql = "
    SELECT op.*,
           s.first_name, s.last_name, s.admission_no, s.admission_no_prefix,
           s.father_name, s.photo, s.gender,
           c.name   AS class_name,
           sec.name AS section_name
    FROM   online_fee_payments op
    JOIN   students  s   ON op.student_id  = s.id
    LEFT JOIN classes   c   ON s.class_id    = c.id
    LEFT JOIN sections  sec ON s.section_id  = sec.id
    $where
    ORDER BY op.payment_date DESC, op.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt_data = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt_data->bindValue($k, $v);
}
$stmt_data->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_data->execute();
$payments = $stmt_data->fetchAll();

// ── Build pagination query-string helper ──────────────────────────────────────
function pag_qs(array $get, int $page): string {
    $q = $get;
    $q['page'] = $page;
    unset($q['page']); // rebuild
    $q = array_merge($q, ['page' => $page]);
    return '?' . http_build_query($q);
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-12">
        <h2 class="mb-1 font-heading fw-extrabold text-dark">Online Payments</h2>
    </div>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible fade show text-sm py-2 px-3 mb-3" role="alert">
        <?php echo sanitize($flash_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible fade show text-sm py-2 px-3 mb-3" role="alert">
        <?php echo sanitize($flash_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ── Stats Bar ──────────────────────────────────────────────────────────── -->
<div class="row mb-3 g-3">
    <div class="col-12">
        <div class="card-premium p-3">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-sm text-dark">Transactions:</span>
                    <span class="badge bg-primary rounded-pill px-2 py-1" style="font-size: 12px;">
                        <?php echo number_format((int)$stats['total_transactions']); ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-sm text-dark">Amount:</span>
                    <span class="badge bg-success rounded-pill px-2 py-1" style="font-size: 12px;">
                        <?php echo number_format((float)$stats['total_amount'], 2); ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-sm text-dark">Success:</span>
                    <span class="badge rounded-pill px-2 py-1" style="background-color: #16a34a; font-size: 12px;">
                        <?php echo (int)$stats['total_success']; ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-sm text-dark">Failed:</span>
                    <span class="badge rounded-pill px-2 py-1" style="background-color: #d97706; font-size: 12px;">
                        <?php echo (int)$stats['total_failed']; ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-sm text-dark">Incomplete:</span>
                    <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 12px;">
                        <?php echo (int)$stats['total_incomplete']; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Filter Toolbar ─────────────────────────────────────────────────────── -->
<div class="row mb-3 g-3">
    <div class="col-12">
        <form method="GET" action="online-payments.php" id="onlinePayFilterForm">
            <div class="fee-toolbar">
                <div class="fee-toolbar-left">
                    <button type="button" class="fee-btn-funnel" id="toggleFiltersBtn" title="Toggle Filters">
                        <i class="ph-light ph-funnel"></i>
                    </button>
                    <button type="button" class="fee-btn-demand-bill" onclick="window.print();" title="Print">
                        <i class="ph-light ph-printer"></i> Print
                    </button>
                </div>
                <div class="fee-toolbar-right">
                    <div class="fee-search-container">
                        <i class="ph-light ph-magnifying-glass text-muted"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by name, admission no, order ID, payment ID"
                               class="fee-search-input">
                        <button type="submit" class="fee-search-btn">
                            <i class="ph-light ph-magnifying-glass"></i>
                        </button>
                    </div>
                    <div class="fee-total-badge">
                        <i class="ph-light ph-credit-card"></i>
                        Total: <span class="count-num"><?php echo $total_records; ?></span>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="card-body p-3 border-top bg-light" id="filterPanel" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Payment Status:</label>
                        <select name="filter_status" class="form-control-admin">
                            <option value="">-- All Statuses --</option>
                            <option value="Success"    <?php echo $filter_status === 'Success'    ? 'selected' : ''; ?>>Success</option>
                            <option value="Failed"     <?php echo $filter_status === 'Failed'     ? 'selected' : ''; ?>>Failed</option>
                            <option value="Incomplete" <?php echo $filter_status === 'Incomplete' ? 'selected' : ''; ?>>Incomplete</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Payment Method:</label>
                        <select name="filter_method" class="form-control-admin">
                            <option value="">-- All Methods --</option>
                            <option value="UPI"          <?php echo $filter_method === 'UPI'          ? 'selected' : ''; ?>>UPI</option>
                            <option value="Net Banking"  <?php echo $filter_method === 'Net Banking'  ? 'selected' : ''; ?>>Net Banking</option>
                            <option value="Card"         <?php echo $filter_method === 'Card'         ? 'selected' : ''; ?>>Card</option>
                            <option value="Wallet"       <?php echo $filter_method === 'Wallet'       ? 'selected' : ''; ?>>Wallet</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">Start Date:</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control-admin">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">End Date:</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control-admin">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">Show Results:</label>
                        <select name="limit" class="form-control-admin">
                            <option value="20"  <?php echo $limit === 20  ? 'selected' : ''; ?>>20</option>
                            <option value="50"  <?php echo $limit === 50  ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary fw-semibold px-4" style="height:38px;">
                            <i class="ph-light ph-funnel"></i> Filter Records
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Table Card ─────────────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($payments)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-credit-card"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No online payment records found</h5>
                            <p class="text-xs text-muted mb-0">Payments made through the parent/student app will appear here.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width:55px;">#</th>
                                    <th style="width:140px;">Date</th>
                                    <th>Student</th>
                                    <th style="width:130px;">Payment Status</th>
                                    <th style="width:130px;">Payment Method</th>
                                    <th style="width:120px; text-align:right;">Paid Amount</th>
                                    <th style="width:160px;">Amount Types</th>
                                    <th style="width:180px;">Order ID</th>
                                    <th style="width:180px;">Payment ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $idx = $offset + 1;
                                foreach ($payments as $p):
                                    $initials    = strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'] ?? '', 0, 1));
                                    $gender_lbl  = ($p['gender'] === 'female') ? 'D/O' : 'S/O';
                                    $adm_no      = $p['admission_no'] ? ($p['admission_no_prefix'] . $p['admission_no']) : '—';

                                    // Status badge colours
                                    $status_cls  = match($p['status']) {
                                        'Success'    => 'bg-success',
                                        'Failed'     => 'bg-warning text-dark',
                                        'Incomplete' => 'bg-danger',
                                        default      => 'bg-secondary',
                                    };
                                ?>
                                    <tr>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <td>
                                            <span class="text-sm font-semibold text-dark">
                                                <?php echo date('d-m-Y', strtotime($p['payment_date'])); ?><br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($p['payment_date'])); ?></small>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($p['photo']) && file_exists('../../../' . $p['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . $p['photo']; ?>" class="student-avatar" alt="Photo">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder"><?php echo $initials; ?></div>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="<?php echo BASE_URL; ?>modules/school/students/view.php?id=<?php echo $p['student_id']; ?>"
                                                       class="student-name-link" style="font-size:14px;">
                                                        <?php echo sanitize($p['first_name'] . ' ' . ($p['last_name'] ?? '') . ' ' . $gender_lbl . ' ' . ($p['father_name'] ?? '—')); ?>
                                                    </a>
                                                    <span class="text-xs text-secondary">
                                                        Adm No: <strong class="text-dark"><?php echo sanitize($adm_no); ?></strong>
                                                        | Class: <strong class="text-dark"><?php echo sanitize(($p['class_name'] ?? '') . '-' . ($p['section_name'] ?? '')); ?></strong>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_cls; ?> px-2 py-1 rounded" style="font-size:11.5px;">
                                                <?php echo sanitize($p['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 text-xs font-semibold rounded">
                                                <i class="ph-fill ph-wallet me-1 text-muted"></i>
                                                <?php echo $p['payment_method'] !== '' ? sanitize($p['payment_method']) : '—'; ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <span class="text-dark fw-bold" style="font-size:14px;">
                                                <?php echo number_format((float)$p['amount'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-sm text-secondary">
                                                <?php echo $p['amount_types'] !== '' ? sanitize($p['amount_types']) : '—'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-xs font-semibold text-primary" style="word-break:break-all;">
                                                <?php echo $p['order_id'] !== '' ? sanitize($p['order_id']) : '—'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-xs font-semibold text-dark" style="word-break:break-all;">
                                                <?php echo $p['payment_id'] !== '' ? sanitize($p['payment_id']) : '—'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- ── Pagination ─────────────────────────────────────────── -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                        <span class="text-xs text-muted">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
                        </span>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo pag_qs($_GET, $page - 1); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo pag_qs($_GET, $i); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo pag_qs($_GET, $page + 1); ?>">Next</a>
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
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn  = document.getElementById('toggleFiltersBtn');
    const filterPanel = document.getElementById('filterPanel');
    if (toggleBtn && filterPanel) {
        const hasActiveFilters = <?php echo ($filter_status || $filter_method || $start_date || $end_date) ? 'true' : 'false'; ?>;
        if (hasActiveFilters) {
            filterPanel.style.display = 'block';
            toggleBtn.classList.add('active');
        }
        toggleBtn.addEventListener('click', function () {
            const isVisible = filterPanel.style.display !== 'none';
            filterPanel.style.display = isVisible ? 'none' : 'block';
            toggleBtn.classList.toggle('active', !isVisible);
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
