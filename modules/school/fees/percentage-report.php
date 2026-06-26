<?php
// modules/school/fees/percentage-report.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Fetch classes for dropdown
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

// Fetch sections for dropdown
$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();

// Filter inputs
$class_id   = $_GET['class_id']   ?? '';
$section_id = $_GET['section_id'] ?? '';
$below_pct  = isset($_GET['below_pct']) && is_numeric($_GET['below_pct']) ? floatval($_GET['below_pct']) : 75;

// Build WHERE
$where  = "WHERE s.school_id = :school_id AND s.deleted_at IS NULL";
$params = [':school_id' => $school_id];

if ($class_id) {
    $where .= " AND s.class_id = :class_id";
    $params[':class_id'] = intval($class_id);
}

if ($section_id) {
    $where .= " AND s.section_id = :section_id";
    $params[':section_id'] = intval($section_id);
}

// Fetch students with fee summary
$sql = "
    SELECT
        s.id,
        s.first_name,
        s.last_name,
        s.admission_no,
        s.admission_no_prefix,
        s.father_name,
        c.name  AS class_name,
        sec.name AS section_name,
        s.total_fees,
        s.total_paid,
        s.total_discount,
        s.fine_amount
    FROM students s
    LEFT JOIN classes  c   ON s.class_id   = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    $where
    ORDER BY c.id ASC, sec.id ASC, s.first_name ASC
";

$stmt_data = $pdo->prepare($sql);
$stmt_data->execute($params);
$all_students = $stmt_data->fetchAll();

// Filter by below_pct in PHP (after fetching)
$students = [];
foreach ($all_students as $row) {
    $total_fees     = floatval($row['total_fees']);
    $total_paid     = floatval($row['total_paid']);
    $total_discount = floatval($row['total_discount']);
    $fine_amount    = floatval($row['fine_amount']);

    $net = $total_fees + $fine_amount - $total_discount;
    $paid_pct = $net > 0 ? ($total_paid / $net) * 100 : 0;

    if ($paid_pct < $below_pct) {
        $row['_net']      = $net;
        $row['_balance']  = max(0, $net - $total_paid);
        $row['_paid_pct'] = $paid_pct;
        $students[]       = $row;
    }
}

$student_count = count($students);

require_once '../../../includes/header.php';
?>

<!-- Page Heading -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-12">
        <h2 class="mb-1 font-heading fw-extrabold text-dark">Fees Percentage Report</h2>
    </div>
</div>

<!-- Filters -->
<div class="row mb-3 g-3">
    <div class="col-12">
        <form method="GET" action="percentage-report.php">
            <div class="fee-toolbar" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding:14px 18px; box-shadow: var(--shadow-sm);">
                <div class="fee-toolbar-left gap-3" style="display:flex; align-items:flex-end; flex-wrap:wrap; gap:14px;">

                    <!-- Class -->
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <label class="form-label-admin mb-0">Class</label>
                        <select name="class_id" class="form-control-admin" style="min-width:140px;">
                            <option value="">1</option>
                            <?php foreach ($all_classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sections -->
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <label class="form-label-admin mb-0">Sections</label>
                        <select name="section_id" class="form-control-admin" style="min-width:140px;">
                            <option value="">A</option>
                            <?php foreach ($all_sections as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $section_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Below % -->
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <label class="form-label-admin mb-0">Below %</label>
                        <input type="number" name="below_pct" value="<?php echo htmlspecialchars($below_pct); ?>" min="0" max="100" step="1" class="form-control-admin" style="width:100px;">
                    </div>

                    <!-- Filter Button -->
                    <div style="padding-bottom:0;">
                        <button type="submit" class="btn font-secondary fw-semibold px-4" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none; height:38px; margin-top:20px;">Filter</button>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<!-- Info Bar -->
<div class="row mb-3">
    <div class="col-12">
        <div style="background: var(--info-light); border: 1px solid rgba(14, 165, 233, 0.25); border-radius: 6px; padding:10px 18px; display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:14px; font-weight:500; color: var(--color-text-primary);">
                Students below <?php echo number_format($below_pct, 0); ?>% payment:
                <strong style="color: var(--danger);"><?php echo $student_count; ?></strong>
            </span>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="exportToExcel()" class="btn btn-sm font-secondary fw-semibold px-3 py-1.5" style="background-color: var(--success); color: #fff; border-radius: 6px; border: none; font-size: 12px;">Excel</button>
                <button type="button" onclick="exportToPDF()" class="btn btn-sm font-secondary fw-semibold px-3 py-1.5" style="background-color: var(--danger); color: #fff; border-radius: 6px; border: none; font-size: 12px;">PDF</button>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($students)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-student"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No students found below <?php echo number_format($below_pct, 0); ?>%</h5>
                            <p class="text-xs text-muted mb-0">Try adjusting your filters or percentage threshold.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle" id="percentageReportTable">
                            <thead>
                                <tr>
                                    <th>S.No.</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Sections</th>
                                    <th>Roll No.</th>
                                    <th>Father's Name</th>
                                    <th>Total Fees</th>
                                    <th>Paid</th>
                                    <th>Discount</th>
                                    <th>Fine</th>
                                    <th>Balance</th>
                                    <th>Paid %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $sno = 1; foreach ($students as $row): ?>
                                    <tr>
                                        <td><?php echo $sno++; ?></td>
                                        <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td><?php echo sanitize($row['class_name'] ?? '—'); ?></td>
                                        <td><?php echo sanitize($row['section_name'] ?? '—'); ?></td>
                                        <td><?php echo sanitize($row['admission_no'] ? ($row['admission_no_prefix'] . $row['admission_no']) : '—'); ?></td>
                                        <td><?php echo sanitize($row['father_name'] ?: '—'); ?></td>
                                        <td><?php echo number_format($row['total_fees'], 0); ?></td>
                                        <td><?php echo number_format($row['total_paid'], 0); ?></td>
                                        <td><?php echo number_format($row['total_discount'], 0); ?></td>
                                        <td><?php echo number_format($row['fine_amount'], 0); ?></td>
                                        <td style="color:#e53e3e; font-weight:600;"><?php echo number_format($row['_balance'], 0); ?></td>
                                        <td style="color:#e53e3e; font-weight:700;"><?php echo number_format($row['_paid_pct'], 0); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('percentageReportTable');
    if (!table) { alert('No data to export.'); return; }
    let csv = [];
    for (const row of table.rows) {
        const cols = [];
        for (const cell of row.cells) {
            cols.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(cols.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'fees_percentage_report.csv';
    link.click();
}

function exportToPDF() {
    window.print();
}
</script>

<?php
require_once '../../../includes/footer.php';
?>
