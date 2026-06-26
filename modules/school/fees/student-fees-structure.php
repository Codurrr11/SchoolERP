<?php
// modules/school/fees/student-fees-structure.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// ── Filter inputs ─────────────────────────────────────────────────────────────
$search     = trim($_GET['search']     ?? '');
$class_id   = $_GET['class_id']        ?? '';
$section_id = $_GET['section_id']      ?? '';
$limit      = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? intval($_GET['limit']) : 30;
$page_num   = (isset($_GET['page'])  && is_numeric($_GET['page']))  ? intval($_GET['page'])  : 1;
$offset     = ($page_num - 1) * $limit;

// ── Fetch dropdown data ───────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id=:sid ORDER BY sort_order ASC");
$stmt->execute([':sid' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id=:sid ORDER BY sort_order ASC");
$stmt->execute([':sid' => $school_id]);
$all_sections = $stmt->fetchAll();

// ── Fetch distinct fee names (column headers) ─────────────────────────────────
$stmt_fn = $pdo->prepare("
    SELECT DISTINCT sfi.fee_name
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL
    ORDER BY sfi.fee_name ASC
");
$stmt_fn->execute([':sid' => $school_id]);
$fee_names = $stmt_fn->fetchAll(PDO::FETCH_COLUMN);

// ── Build student WHERE clause ────────────────────────────────────────────────
$where  = "WHERE s.school_id = :sid AND s.deleted_at IS NULL";
$params = [':sid' => $school_id];

if ($search) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search
                     OR s.mobile_no LIKE :search OR s.admission_no LIKE :search
                     OR s.father_name LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($class_id) {
    $where .= " AND s.class_id = :cid";
    $params[':cid'] = intval($class_id);
}
if ($section_id) {
    $where .= " AND s.section_id = :secid";
    $params[':secid'] = intval($section_id);
}

// ── Count ─────────────────────────────────────────────────────────────────────
$stmt_cnt = $pdo->prepare("
    SELECT COUNT(*)
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    $where
");
$stmt_cnt->execute($params);
$total_records = (int)$stmt_cnt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $limit));

// ── Fetch paginated students ──────────────────────────────────────────────────
$stmt_s = $pdo->prepare("
    SELECT s.*, u.username AS u_name, c.name AS class_name, sec.name AS section_name
    FROM students s
    LEFT JOIN users u   ON s.user_id   = u.id
    LEFT JOIN classes c ON s.class_id  = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    $where
    ORDER BY s.first_name ASC, s.last_name ASC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $stmt_s->bindValue($k, $v);
$stmt_s->bindValue(':lim', $limit,  PDO::PARAM_INT);
$stmt_s->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt_s->execute();
$students = $stmt_s->fetchAll();

// ── Fetch fee items for all students on this page ─────────────────────────────
$student_ids = array_column($students, 'id');
$fee_items_by_student = [];

if (!empty($student_ids)) {
    $in = implode(',', array_map('intval', $student_ids));
    $stmt_fi = $pdo->query("
        SELECT student_id, fee_name, amount, discount_amount, paid_amount, is_active
        FROM student_fee_items
        WHERE student_id IN ($in)
        ORDER BY fee_name ASC
    ");
    foreach ($stmt_fi->fetchAll() as $fi) {
        $fee_items_by_student[$fi['student_id']][$fi['fee_name']] = $fi;
    }
}

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Student Fees Structure</h2>
    </div>
</div>

<!-- ── Filter Toolbar ────────────────────────────────────────────────────────── -->
<div class="row mb-3 g-3">
    <div class="col-12">
        <form method="GET" action="student-fees-structure.php" id="sfsFilterForm">
            <div class="fee-toolbar">
                <div class="fee-toolbar-left">
                    <button type="button" class="fee-btn-funnel" id="sfsToggleFilters" title="Toggle Filters">
                        <i class="ph-light ph-funnel"></i>
                    </button>
                </div>
                <div class="fee-toolbar-right">
                    <!-- Search -->
                    <div class="fee-search-container">
                        <i class="ph-light ph-magnifying-glass text-muted"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by name, mobile, username…" class="fee-search-input font-secondary">
                        <button type="submit" class="fee-search-btn"><i class="ph-light ph-magnifying-glass"></i></button>
                    </div>
                    <!-- Limit -->
                    <select name="limit" class="form-control-admin font-secondary text-secondary"
                            style="width:80px; height:38px;" onchange="document.getElementById('sfsFilterForm').submit()">
                        <option value="20"  <?php echo $limit == 20  ? 'selected' : ''; ?>>20</option>
                        <option value="30"  <?php echo $limit == 30  ? 'selected' : ''; ?>>30</option>
                        <option value="50"  <?php echo $limit == 50  ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <!-- Total badge -->
                    <div class="fee-total-badge font-secondary">
                        <i class="ph-light ph-graduation-cap"></i>
                        Total Students: <span class="count-num"><?php echo $total_records; ?></span>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="card-body p-3 border bg-light mt-2 rounded" id="sfsFilterPanel" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-admin mb-1">Select Class:</label>
                        <select name="class_id" class="form-control-admin">
                            <option value="">-- All Classes --</option>
                            <?php foreach ($all_classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin mb-1">Select Section:</label>
                        <select name="section_id" class="form-control-admin">
                            <option value="">-- All Sections --</option>
                            <?php foreach ($all_sections as $sec): ?>
                                <option value="<?php echo $sec['id']; ?>" <?php echo $section_id == $sec['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($sec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary fw-semibold" style="height:38px; padding:0 1.5rem;">
                            <i class="ph-light ph-funnel me-1"></i>Filter
                        </button>
                        <a href="student-fees-structure.php" class="btn btn-outline-secondary fw-semibold" style="height:38px; padding:0 1.2rem;">
                            <i class="ph-light ph-arrow-counter-clockwise me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Table ──────────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive sfs-table-scroll">
                    <?php if (empty($students)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-user-focus"></i>
                            </div>
                            <h5 class="fw-bold mt-3 mb-1 font-heading">No students found</h5>
                            <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Adjust your filter options to list students.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 sfs-table" id="sfsTable">
                            <thead>
                                <tr>
                                    <th class="sfs-th-sticky sfs-th-adm">Admission No.</th>
                                    <th class="sfs-th-sticky sfs-th-student">Student</th>
                                    <!-- Session due fees aggregate -->
                                    <th class="text-center sfs-th-fee sfs-th-session">
                                        <?php echo htmlspecialchars(date('Y').'-'.(date('Y')+1)); ?> Due Fees
                                    </th>
                                    <!-- Dynamic fee type columns -->
                                    <?php foreach ($fee_names as $fn): ?>
                                        <th class="text-center sfs-th-fee"><?php echo sanitize($fn); ?></th>
                                    <?php endforeach; ?>
                                    <th class="text-center sfs-th-total-fees">Total Fees</th>
                                    <th class="text-center sfs-th-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s):
                                    $sid         = $s['id'];
                                    $initials    = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'] ?? '', 0, 1));
                                    $gender_lbl  = ($s['gender'] ?? '') === 'female' ? 'D/O' : 'S/O';
                                    $items       = $fee_items_by_student[$sid] ?? [];

                                    // Session aggregate
                                    $agg_fees = $agg_fine = $agg_disc = $agg_paid = 0;
                                    foreach ($items as $fi) {
                                        if ($fi['is_active']) {
                                            $agg_fees += (float)$fi['amount'];
                                            $agg_disc += (float)$fi['discount_amount'];
                                            $agg_paid += (float)$fi['paid_amount'];
                                        }
                                    }
                                    $agg_fine    = (float)($s['fine_amount'] ?? 0);
                                    $agg_balance = $agg_fees + $agg_fine - $agg_disc - $agg_paid;
                                ?>
                                    <tr class="sfs-student-row" data-student-id="<?php echo $sid; ?>">
                                        <!-- Admission No -->
                                        <td class="sfs-td-sticky sfs-td-adm">
                                            <span class="fw-bold font-secondary" style="color:var(--color-accent); font-size:13px;">
                                                <?php echo sanitize($s['admission_no'] ? ($s['admission_no_prefix'] ?? '').$s['admission_no'] : '—'); ?>
                                            </span>
                                        </td>

                                        <!-- Student Info -->
                                        <td class="sfs-td-sticky sfs-td-student">
                                            <div class="d-flex align-items-start gap-2">
                                                <?php if (!empty($s['photo']) && file_exists('../../../'.$s['photo'])): ?>
                                                    <img src="<?php echo BASE_URL.$s['photo']; ?>" class="sfs-avatar" alt="">
                                                <?php else: ?>
                                                    <div class="sfs-avatar-placeholder"><?php echo $initials; ?></div>
                                                <?php endif; ?>
                                                <div class="sfs-student-info">
                                                    <a href="<?php echo BASE_URL; ?>modules/school/students/view.php?id=<?php echo $sid; ?>"
                                                       class="sfs-student-name">
                                                        <?php echo sanitize($s['first_name'].' '.($s['last_name'] ?? '').' '.$gender_lbl.' '.($s['father_name'] ?? '—')); ?>
                                                    </a>
                                                    <span>Username: <strong><?php echo sanitize($s['u_name'] ?? '—'); ?></strong></span>
                                                    <span>Classes: <strong><?php echo sanitize(($s['class_name'] ?? '').' '.($s['section_name'] ?? '')); ?></strong></span>
                                                    <span>Mobile: <strong><?php echo sanitize($s['mobile_no'] ?? '—'); ?></strong></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Session aggregate column -->
                                        <td class="text-center sfs-td-fee sfs-td-session">
                                            <?php if ($agg_fees > 0 || $agg_paid > 0): ?>
                                                <div class="sfs-fee-cell">
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Fees:</span><span class="sfs-fee-val"><?php echo number_format($agg_fees, 0); ?></span></div>
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Fine:</span><span class="sfs-fee-val sfs-val-fine"><?php echo number_format($agg_fine, 0); ?></span></div>
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Discount:</span><span class="sfs-fee-val sfs-val-disc"><?php echo number_format($agg_disc, 0); ?></span></div>
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Paid:</span><span class="sfs-fee-val sfs-val-paid"><?php echo number_format($agg_paid, 0); ?></span></div>
                                                    <div class="sfs-fee-row sfs-balance-row"><span class="sfs-fee-lbl">Balance:</span><span class="sfs-fee-val <?php echo $agg_balance > 0 ? 'sfs-val-due' : 'sfs-val-ok'; ?>"><?php echo number_format($agg_balance, 0); ?></span></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="sfs-na">N/A</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Per fee-type columns -->
                                        <?php foreach ($fee_names as $fn):
                                            $fi  = $items[$fn] ?? null;
                                            $act = $fi && $fi['is_active'];
                                            if ($act):
                                                $f_fees = (float)$fi['amount'];
                                                $f_fine = 0;
                                                $f_disc = (float)$fi['discount_amount'];
                                                $f_paid = (float)$fi['paid_amount'];
                                                $f_bal  = $f_fees + $f_fine - $f_disc - $f_paid;
                                        ?>
                                            <td class="text-center sfs-td-fee">
                                                <div class="sfs-fee-cell">
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Fees:</span><span class="sfs-fee-val"><?php echo number_format($f_fees, 0); ?></span></div>
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Fine:</span><span class="sfs-fee-val sfs-val-fine"><?php echo number_format($f_fine, 0); ?></span></div>
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Discount:</span><span class="sfs-fee-val sfs-val-disc"><?php echo number_format($f_disc, 0); ?></span></div>
                                                    <div class="sfs-fee-row"><span class="sfs-fee-lbl">Paid:</span><span class="sfs-fee-val sfs-val-paid"><?php echo number_format($f_paid, 0); ?></span></div>
                                                    <div class="sfs-fee-row sfs-balance-row"><span class="sfs-fee-lbl">Balance:</span><span class="sfs-fee-val <?php echo $f_bal > 0 ? 'sfs-val-due' : 'sfs-val-ok'; ?>"><?php echo number_format($f_bal, 0); ?></span></div>
                                                </div>
                                            </td>
                                        <?php   else: ?>
                                            <td class="text-center sfs-td-fee"><span class="sfs-na">N/A</span></td>
                                        <?php   endif;
                                        endforeach; ?>

                                        <!-- Total Fees -->
                                        <td class="text-center sfs-td-total">
                                            <span class="fw-bold font-secondary sfs-total-val">
                                                <?php echo number_format((float)($s['total_fees'] ?? 0), 0); ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-center sfs-td-actions">
                                            <button type="button"
                                                class="teacher-action-btn action-view"
                                                title="Print Fee Structure"
                                                onclick="sfsPrintRow(<?php echo $sid; ?>)">
                                                <i class="ph-light ph-printer"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- ── Pagination ──────────────────────────────────────────────── -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                        <span class="cell-counter font-secondary">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> results
                        </span>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page_num <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link font-secondary"
                                       href="?page=<?php echo $page_num-1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo urlencode($class_id); ?>&section_id=<?php echo urlencode($section_id); ?>">
                                        <i class="ph-light ph-caret-left"></i>
                                    </a>
                                </li>
                                <?php
                                $from = max(1, $page_num - 2);
                                $to   = min($total_pages, $page_num + 2);
                                for ($i = $from; $i <= $to; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page_num ? 'active' : ''; ?>">
                                        <a class="page-link font-secondary"
                                           href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo urlencode($class_id); ?>&section_id=<?php echo urlencode($section_id); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page_num >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link font-secondary"
                                       href="?page=<?php echo $page_num+1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo urlencode($class_id); ?>&section_id=<?php echo urlencode($section_id); ?>">
                                        <i class="ph-light ph-caret-right"></i>
                                    </a>
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
// Toggle filter panel
document.getElementById('sfsToggleFilters').addEventListener('click', function () {
    const panel = document.getElementById('sfsFilterPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
});
// Auto-show filter panel if any filter is active
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('class_id') || params.get('section_id')) {
        document.getElementById('sfsFilterPanel').style.display = 'block';
    }
})();

// Print a single student fee row
function sfsPrintRow(studentId) {
    const row = document.querySelector('tr[data-student-id="' + studentId + '"]');
    if (!row) return;

    const headers = [];
    document.querySelectorAll('#sfsTable thead tr th').forEach(function (th) {
        headers.push(th.innerText.trim());
    });

    const cells = [];
    row.querySelectorAll('td').forEach(function (td) {
        cells.push(td.innerText.trim().replace(/\n+/g, ' | '));
    });

    const schoolName = document.querySelector('.sidebar-school-name')?.innerText || 'School ERP';
    const printDate  = new Date().toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });

    let tableRows = '<tr>';
    headers.forEach(function (h) {
        tableRows += '<th>' + h + '</th>';
    });
    tableRows += '</tr><tr>';
    cells.forEach(function (c) {
        tableRows += '<td>' + c + '</td>';
    });
    tableRows += '</tr>';

    const win = window.open('', '_blank', 'width=1100,height=700');
    win.document.write(`<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Fee Structure - Student #` + studentId + `</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; color: #222; }
  h2   { margin: 0 0 4px; font-size: 16px; }
  p    { margin: 0 0 12px; font-size: 11px; color: #555; }
  table{ width: 100%; border-collapse: collapse; }
  th   { background: #1e40af; color: #fff; padding: 6px 8px; font-size: 11px; text-align: left; white-space: nowrap; }
  td   { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; vertical-align: top; }
  tr:nth-child(even) td { background: #f8fafc; }
  @media print { body { margin: 10mm; } }
</style>
</head>
<body>
<h2>` + schoolName + ` — Student Fees Structure</h2>
<p>Printed on ` + printDate + `</p>
<div style="overflow-x:auto;">
  <table>` + tableRows + `</table>
</div>
<script>window.onload=function(){window.print();}<\/script>
</body>
</html>`);
    win.document.close();
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
