<?php
// modules/school/fees/collected-log.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Fetch classes and sections for dropdowns
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY id ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY id ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();

// Get filter inputs
$search = $_GET['search'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$section_id = $_GET['section_id'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Pagination setup
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Build SQL where clause
$where = "WHERE fp.school_id = :school_id";
$params = [':school_id' => $school_id];

if ($search) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.admission_no LIKE :search OR fp.transaction_id LIKE :search OR s.father_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($class_id) {
    $where .= " AND s.class_id = :class_id";
    $params[':class_id'] = intval($class_id);
}

if ($section_id) {
    $where .= " AND s.section_id = :section_id";
    $params[':section_id'] = intval($section_id);
}

if ($payment_method) {
    $where .= " AND fp.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method;
}

if ($start_date) {
    $where .= " AND fp.payment_date >= :start_date";
    $params[':start_date'] = $start_date . " 00:00:00";
}

if ($end_date) {
    $where .= " AND fp.payment_date <= :end_date";
    $params[':end_date'] = $end_date . " 23:59:59";
}

// Count total matching records for pagination
$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    $where
");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated matching records
$sql = "
    SELECT fp.*, s.first_name, s.last_name, s.admission_no, s.admission_no_prefix, s.father_name, s.photo, s.gender,
           c.name as class_name, sec.name as section_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    $where
    ORDER BY fp.payment_date DESC, fp.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt_data = $pdo->prepare($sql);
// Bind params
foreach ($params as $key => $val) {
    $stmt_data->bindValue($key, $val);
}
$stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_data->execute();
$payments = $stmt_data->fetchAll();

require_once '../../../includes/header.php';
?>

<!-- Fees Header Area -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-12">
        <h2 class="mb-1 font-heading fw-extrabold text-dark">Collected Fees Log</h2>
    </div>
</div>

<div class="row mb-3 g-3">
    <div class="col-12">
        <form method="GET" action="collected-log.php" id="feeLogFilterForm">
            <!-- Filter Toolbar -->
            <div class="fee-toolbar">
                <div class="fee-toolbar-left">
                    <button type="button" class="fee-btn-funnel" id="toggleFiltersBtn" title="Toggle Search Options">
                        <i class="ph-light ph-funnel"></i>
                    </button>
                    <button type="button" class="fee-btn-demand-bill" onclick="window.print();" title="Print Log">
                        <i class="ph-light ph-printer"></i> Print Log
                    </button>
                </div>

                <div class="fee-toolbar-right">
                    <div class="fee-search-container">
                        <i class="ph-light ph-magnifying-glass text-muted"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, admission no, txn ID" class="fee-search-input">
                        <button type="submit" class="fee-search-btn">
                            <i class="ph-light ph-magnifying-glass"></i>
                        </button>
                    </div>
                    <div class="fee-total-badge">
                        <i class="ph-light ph-receipt"></i>
                        Total Transactions: <span class="count-num"><?php echo $total_records; ?></span>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Grid -->
            <div class="card-body p-3 border-top bg-light" id="filterPanel">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Select Class:</label>
                        <select name="class_id" id="classSelect" class="form-control-admin">
                            <option value="">-- All Classes --</option>
                            <?php foreach ($all_classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Select Section:</label>
                        <select name="section_id" class="form-control-admin">
                            <option value="">-- All Sections --</option>
                            <?php foreach ($all_sections as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $section_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">Payment Mode:</label>
                        <select name="payment_method" class="form-control-admin">
                            <option value="">-- All Modes --</option>
                            <option value="Cash" <?php echo $payment_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer" <?php echo $payment_method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Cheque" <?php echo $payment_method === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="Online" <?php echo $payment_method === 'Online' ? 'selected' : ''; ?>>Online</option>
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

                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Show Results:</label>
                        <select name="limit" class="form-control-admin">
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-9 d-flex align-items-end justify-content-end">
                        <button type="submit" class="btn font-secondary fw-semibold px-4" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none; height: 38px;">
                            <i class="ph-light ph-funnel"></i> Filter Records
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($payments)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-coins"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No fee collection logs found</h5>
                            <p class="text-xs text-muted mb-0">Adjust your filter options or select a different date range.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">S.No.</th>
                                    <th style="width: 150px;">Date & Time</th>
                                    <th style="width: 180px;">Transaction Details</th>
                                    <th>Student</th>
                                    <th style="width: 150px;">Payment Mode</th>
                                    <th style="width: 150px; text-align: right;">Amount Paid</th>
                                    <th style="width: 120px;">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = $offset + 1;
                                foreach ($payments as $p):
                                    $student_initials = strtoupper(substr($p['first_name'], 0, 1) . (isset($p['last_name']) ? substr($p['last_name'], 0, 1) : ''));
                                    $gender_title = ($p['gender'] === 'female') ? 'D/O' : 'S/O';
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
                                            <div class="d-flex flex-column text-sm font-semibold gap-1">
                                                <span>Txn ID: <strong class="text-primary"><?php echo sanitize($p['transaction_id'] ?: '—'); ?></strong></span>
                                                <?php if($p['remarks']): ?>
                                                    <span class="text-xs text-muted font-normal" title="<?php echo htmlspecialchars($p['remarks']); ?>">
                                                        Note: <?php echo sanitize(substr($p['remarks'], 0, 30)) . (strlen($p['remarks']) > 30 ? '...' : ''); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($p['photo']) && file_exists('../../../' . $p['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . $p['photo']; ?>" class="student-avatar" alt="Photo">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder">
                                                        <?php echo $student_initials; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="font-semibold text-dark" style="font-size: 14px;">
                                                        <?php echo sanitize($p['first_name'] . ' ' . $p['last_name'] . ' ' . $gender_title . ' ' . ($p['father_name'] ?? '—')); ?>
                                                    </span>
                                                    <span class="text-xs text-secondary">Adm No: <strong class="text-dark"><?php echo sanitize($p['admission_no'] ? ($p['admission_no_prefix'] . $p['admission_no']) : '—'); ?></strong> | Class: <strong class="text-dark"><?php echo sanitize(($p['class_name'] ?? '') . '-' . ($p['section_name'] ?? '')); ?></strong></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 font-semibold text-xs rounded">
                                                <i class="ph-fill ph-wallet me-1 text-muted"></i> <?php echo sanitize($p['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <span class="text-dark fw-bold" style="font-size: 14.5px;">
                                                <?php echo number_format($p['amount_paid'], 2); ?>
                                            </span>
                                            <?php if (floatval($p['fine_amount']) > 0): ?>
                                                <br><small class="text-danger">Fine: <?php echo number_format($p['fine_amount'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['screenshot']): ?>
                                                <button type="button" class="btn btn-xs btn-outline-primary view-screenshot-btn" data-img="<?php echo BASE_URL . $p['screenshot']; ?>" style="font-size:11px; padding: 2px 6px; border-radius: 4px;">
                                                    <i class="ph-light ph-image"></i> View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination area -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                        <span class="text-xs text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</span>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>&payment_method=<?php echo urlencode($payment_method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>&payment_method=<?php echo urlencode($payment_method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>&payment_method=<?php echo urlencode($payment_method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">Next</a>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle Filter Panel
        const toggleBtn = document.getElementById('toggleFiltersBtn');
        const filterPanel = document.getElementById('filterPanel');
        if (toggleBtn && filterPanel) {
            toggleBtn.addEventListener('click', function() {
                if (filterPanel.style.display === 'none') {
                    filterPanel.style.display = 'block';
                    toggleBtn.classList.add('active');
                } else {
                    filterPanel.style.display = 'none';
                    toggleBtn.classList.remove('active');
                }
            });

            // Hide panel by default unless filters are active
            const hasActiveFilters = <?php echo ($class_id || $section_id || $payment_method || $start_date || $end_date) ? 'true' : 'false'; ?>;
            if (!hasActiveFilters) {
                filterPanel.style.display = 'none';
            } else {
                toggleBtn.classList.add('active');
            }
        }

        // View Screenshot in SweetAlert2
        document.querySelectorAll('.view-screenshot-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const imgUrl = this.dataset.img;
                Swal.fire({
                    title: 'Payment Receipt Screenshot',
                    imageUrl: imgUrl,
                    imageAlt: 'Receipt Screenshot',
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6c757d',
                    customClass: {
                        image: 'img-fluid rounded border shadow-sm'
                    }
                });
            });
        });
    });
</script>

<?php
require_once '../../../includes/footer.php';
?>
