<?php
// modules/school/fees/fees-structure-report.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// ── Session / filter inputs ───────────────────────────────────────────────────
$stmt_sess = $pdo->prepare("SELECT name FROM academic_sessions WHERE school_id = :sid ORDER BY id DESC");
$stmt_sess->execute([':sid' => $school_id]);
$sessions = $stmt_sess->fetchAll(PDO::FETCH_COLUMN);
$current_session = trim($_GET['session'] ?? ($sessions[0] ?? ''));

$search   = trim($_GET['search'] ?? '');
$limit    = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? intval($_GET['limit']) : 20;
$page_num = (isset($_GET['page'])  && is_numeric($_GET['page']))  ? intval($_GET['page'])  : 1;
$offset   = ($page_num - 1) * $limit;

// ── Banner stat badges ────────────────────────────────────────────────────────
$stmt_banner = $pdo->prepare("
    SELECT
        SUM(sfi.amount)                                                        AS gross_fees,
        SUM(sfi.discount_amount)                                               AS head_discount,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                      AS final_fees,
        SUM(sfi.paid_amount)                                                   AS paid_fine,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                      AS gross_total,
        0                                                                      AS fees_discount,
        0                                                                      AS fine_discount,
        SUM(sfi.discount_amount)                                               AS total_discount,
        SUM(sfi.paid_amount)                                                   AS paid_fees,
        0                                                                      AS paid_fine2,
        SUM(sfi.paid_amount)                                                   AS total_paid,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)) AS balance
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1
");
$stmt_banner->execute([':sid' => $school_id]);
$banner = $stmt_banner->fetch();
$b = function($k) use ($banner) { return number_format((float)($banner[$k] ?? 0), 0, '.', ''); };

// ── Table 1: Summary by fee type ─────────────────────────────────────────────
$where_params = [':sid' => $school_id];
$where_extra  = '';
if ($search !== '') {
    $where_extra .= " AND sfi.fee_name LIKE :search";
    $where_params[':search'] = "%$search%";
}

$stmt_cnt = $pdo->prepare("
    SELECT COUNT(DISTINCT sfi.fee_name)
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1
    $where_extra
");
$stmt_cnt->execute($where_params);
$total_records = (int)$stmt_cnt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_records / $limit));

$stmt_summary = $pdo->prepare("
    SELECT
        sfi.fee_name                                                      AS fees_type,
        SUM(sfi.amount)                                                   AS total_fees,
        SUM(sfi.discount_amount)                                          AS discount_head,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                AS final_fees,
        0                                                                 AS fine,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                AS gross_total,
        0                                                                 AS fees_discount,
        0                                                                 AS fine_discount,
        SUM(sfi.discount_amount)                                          AS total_discount,
        SUM(sfi.paid_amount)                                              AS paid_fees,
        0                                                                 AS paid_fine,
        SUM(sfi.paid_amount)                                              AS total_paid,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)) AS balance
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1
    $where_extra
    GROUP BY sfi.fee_name
    ORDER BY sfi.fee_name ASC
    LIMIT :lim OFFSET :off
");
foreach ($where_params as $k => $v) $stmt_summary->bindValue($k, $v);
$stmt_summary->bindValue(':lim', $limit,  PDO::PARAM_INT);
$stmt_summary->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt_summary->execute();
$summary_rows = $stmt_summary->fetchAll();

$stmt_totals = $pdo->prepare("
    SELECT
        SUM(sfi.amount)                                                   AS total_fees,
        SUM(sfi.discount_amount)                                          AS discount_head,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                AS final_fees,
        0                                                                 AS fine,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                AS gross_total,
        0 AS fees_discount, 0 AS fine_discount,
        SUM(sfi.discount_amount)                                          AS total_discount,
        SUM(sfi.paid_amount)                                              AS paid_fees,
        0                                                                 AS paid_fine,
        SUM(sfi.paid_amount)                                              AS total_paid,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0) - COALESCE(sfi.paid_amount,0)) AS balance
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1
");
$stmt_totals->execute([':sid' => $school_id]);
$totals = $stmt_totals->fetch();

// ── Table 2: Summary by class ─────────────────────────────────────────────────
// All distinct fee type names (for header columns)
$stmt_ftypes = $pdo->prepare("
    SELECT DISTINCT sfi.fee_name
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1
    ORDER BY sfi.fee_name ASC
");
$stmt_ftypes->execute([':sid' => $school_id]);
$fee_type_names = $stmt_ftypes->fetchAll(PDO::FETCH_COLUMN);

// Class-level aggregates per fee type
$stmt_class = $pdo->prepare("
    SELECT
        c.name                                                            AS class_name,
        sfi.fee_name,
        SUM(sfi.amount)                                                   AS total,
        SUM(sfi.discount_amount)                                          AS head_discount,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                AS final_fees,
        SUM(sfi.amount - COALESCE(sfi.discount_amount,0))                AS gross_total,
        SUM(sfi.paid_amount)                                              AS paid_fees
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.school_id = :sid AND s.deleted_at IS NULL AND sfi.is_active = 1
    GROUP BY c.id, c.name, sfi.fee_name
    ORDER BY c.name ASC, sfi.fee_name ASC
");
$stmt_class->execute([':sid' => $school_id]);
$class_raw = $stmt_class->fetchAll();

// Restructure: class_name => [ fee_name => data ]
$class_data = [];
foreach ($class_raw as $row) {
    $cn = $row['class_name'] ?? '(No Class)';
    $class_data[$cn][$row['fee_name']] = $row;
}

// Sub-column labels (12 per group)
$sub_cols = [
    'Total', 'Head Discount', 'Final', 'Fine', 'Gross Total',
    'Fees Discount', 'Fine Discount', 'Total Discount',
    'Paid Fees', 'Paid Fine', 'Total Paid', 'Balance'
];

// Helper: build 12-value array from a fee-type data row
function build12($d) {
    $total  = (float)($d['total']        ?? 0);
    $hd     = (float)($d['head_discount'] ?? 0);
    $final  = (float)($d['final_fees']   ?? 0);
    $gross  = (float)($d['gross_total']  ?? 0);
    $paid   = (float)($d['paid_fees']    ?? 0);
    $bal    = $gross - $paid;
    return [$total, $hd, $final, 0, $gross, 0, 0, $hd, $paid, 0, $paid, $bal];
}

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Fees Structure Report</h2>
    </div>
</div>

<!-- ── Banner Stat Badges ────────────────────────────────────────────────────── -->
<div class="row mb-3 g-2">
    <div class="col-12">
        <div class="fsr-banner-wrap">
            <span class="fsr-badge fsr-badge-blue">Gross Fees: <?php echo $b('gross_fees'); ?></span>
            <span class="fsr-badge fsr-badge-purple">Discount head Amount (<?php echo $b('head_discount'); ?>)</span>
            <span class="fsr-badge fsr-badge-green">Final Fees: (<?php echo $b('final_fees'); ?>)</span>
            <span class="fsr-badge fsr-badge-red">Fine Amount (<?php echo $b('paid_fine'); ?>)</span>
            <span class="fsr-badge fsr-badge-teal">Gross Total: <?php echo $b('gross_total'); ?></span>
            <span class="fsr-badge fsr-badge-orange">Fees Discount: <?php echo $b('fees_discount'); ?></span>
            <span class="fsr-badge fsr-badge-pink">Fine Discount: <?php echo $b('fine_discount'); ?></span>
            <span class="fsr-badge fsr-badge-indigo">Total Discount: <?php echo $b('total_discount'); ?></span>
        </div>
    </div>
</div>

<!-- ── Table 1: Fees structure summary ───────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">

            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom:0;">
                <div class="fee-toolbar-left">
                    <span class="fw-bold text-sm font-heading" style="color:var(--color-text-primary);">Fees structure summary</span>
                </div>
                <div class="fee-toolbar-right">
                    <form method="GET" action="fees-structure-report.php" class="d-flex align-items-center gap-2" id="filterForm">
                        <div class="fee-search-container">
                            <i class="ph-light ph-magnifying-glass text-muted"></i>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search fee type…" class="fee-search-input font-secondary">
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
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3"><i class="ph-light ph-chart-bar"></i></div>
                            <h5 class="fw-bold mt-3 mb-1 font-heading">No fee structure data found</h5>
                            <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Create fee structures first or adjust your search.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle fsr-table" id="summaryTable">
                            <thead>
                                <tr>
                                    <th>Fees Type</th>
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
                                ?>
                                    <tr>
                                        <td><a href="#" class="fw-bold font-heading text-decoration-none fsr-fee-link"><?php echo sanitize($row['fees_type']); ?></a></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['total_fees'],    0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['discount_head'], 0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['final_fees'],    0); ?></td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['gross_total'],   0); ?></td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['total_discount'],0); ?></td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['paid_fees'],     0); ?></td>
                                        <td class="text-end font-secondary fsr-zero">0</td>
                                        <td class="text-end font-secondary"><?php echo number_format((float)$row['total_paid'],    0); ?></td>
                                        <td class="text-end font-secondary <?php echo $bal_class; ?>"><?php echo number_format($balance, 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fsr-total-row">
                                    <td class="fw-bold">Total</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['total_fees'],    0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['discount_head'], 0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['final_fees'],    0); ?></td>
                                    <td class="text-end fw-bold">0</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['gross_total'],   0); ?></td>
                                    <td class="text-end fw-bold fsr-zero">0</td>
                                    <td class="text-end fw-bold fsr-zero">0</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['total_discount'],0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['paid_fees'],     0); ?></td>
                                    <td class="text-end fw-bold">0</td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['total_paid'],    0); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format((float)$totals['balance'],       0); ?></td>
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

<!-- ── Table 2: Fees structure summary by classes ─────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-premium">

            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom:0;">
                <div class="fee-toolbar-left">
                    <span class="fw-bold text-sm font-heading" style="color:var(--color-text-primary);">Fees structure summary by classes</span>
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
                            <h5 class="fw-bold mt-3 mb-1 font-heading">No class data found</h5>
                            <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Assign students to classes with active fee structures.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle fsr-table" id="classTable">
                            <thead>
                                <!-- ── Row 1: group labels ─────────────────────────────────── -->
                                <tr class="fsr-thead-row1">
                                    <!-- FEES TYPES spans both header rows -->
                                    <th rowspan="2" class="fsr-th-fees-types">FEES TYPES</th>

                                    <!-- Session aggregate group -->
                                    <th colspan="12" class="text-center fsr-th-group fsr-th-group-session">
                                        <?php echo htmlspecialchars($current_session ?: date('Y').'-'.(date('Y')+1)); ?> due fees
                                    </th>

                                    <!-- One group per fee type -->
                                    <?php foreach ($fee_type_names as $ftn): ?>
                                        <th colspan="12" class="text-center fsr-th-group">
                                            <?php echo sanitize($ftn); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>

                                <!-- ── Row 2: 12 sub-column labels per group ───────────────── -->
                                <tr class="fsr-thead-row2">
                                    <?php
                                    // session group + one set per fee type
                                    $repeat = 1 + count($fee_type_names);
                                    for ($g = 0; $g < $repeat; $g++):
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
                                // Grand totals accumulators for footer (12 values per group)
                                $grand_sess  = array_fill(0, 12, 0);
                                $grand_types = [];
                                foreach ($fee_type_names as $ftn) $grand_types[$ftn] = array_fill(0, 12, 0);

                                foreach ($class_data as $cname => $fee_map):
                                    // Session group: sum all fee types for this class
                                    $s = [0,0,0,0,0,0,0,0,0,0,0,0];
                                    foreach ($fee_map as $d) {
                                        $v = build12($d);
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

                                        <!-- Per fee-type 12 cells each -->
                                        <?php foreach ($fee_type_names as $ftn):
                                            $d  = $fee_map[$ftn] ?? null;
                                            $fv = $d ? build12($d) : array_fill(0, 12, 0);
                                            foreach ($fv as $i => $val) $grand_types[$ftn][$i] += $val;
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
                                    <!-- Session totals -->
                                    <?php foreach ($grand_sess as $i => $v):
                                        $is_bal = ($i === 11);
                                        $bc     = $is_bal ? ($v > 0 ? ' fsr-balance-due' : ' fsr-balance-ok') : '';
                                    ?>
                                        <td class="text-center fw-bold<?php echo $bc; ?>"><?php echo number_format($v, 0); ?></td>
                                    <?php endforeach; ?>
                                    <!-- Per fee-type totals -->
                                    <?php foreach ($fee_type_names as $ftn):
                                        foreach ($grand_types[$ftn] as $i => $v):
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

<script>
document.getElementById('exportCsvBtn').addEventListener('click', function () {
    exportTableToCsv('summaryTable', 'fees_structure_summary.csv');
});

const exportClassBtn = document.getElementById('exportClassCsvBtn');
if (exportClassBtn) {
    exportClassBtn.addEventListener('click', function () {
        exportTableToCsv('classTable', 'fees_structure_by_class.csv');
    });
}

function exportTableToCsv(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    let csv = [];
    for (const row of table.rows) {
        let cols = [];
        for (const cell of row.cells) {
            let text = cell.innerText.replace(/"/g, '""').replace(/\n/g, ' ');
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
</script>

<?php require_once '../../../includes/footer.php'; ?>
