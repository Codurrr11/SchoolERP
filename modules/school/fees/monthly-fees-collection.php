<?php
// modules/school/fees/monthly-fees-collection.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// Define the exact column headers requested by the user
$months = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];
$cols = [
    'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar',
    '2025 - 2026 Due Fees', 'Old Fee',
    '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
    'Term Fee-1_1', 'Term Fee-1_2', 'Total'
];

function get_col_label($col) {
    if ($col === 'Term Fee-1_1' || $col === 'Term Fee-1_2') {
        return 'Term Fee-1';
    }
    return $col;
}

// 1. Fetch classes
$stmt_cl = $pdo->prepare("SELECT id, name FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt_cl->execute([':school_id' => $school_id]);
$classes = $stmt_cl->fetchAll();

// 2. Build Matrix Data
$matrix = [];
foreach ($classes as $cl) {
    $class_id = $cl['id'];
    foreach ($cols as $col) {
        $matrix[$class_id][$col] = ['total' => 0.0, 'paid' => 0.0, 'discount' => 0.0, 'balance' => 0.0];
    }
    
    // Fetch students in this class
    $stmt_st = $pdo->prepare("SELECT id FROM students WHERE class_id = :class_id AND school_id = :school_id AND deleted_at IS NULL");
    $stmt_st->execute([':class_id' => $class_id, ':school_id' => $school_id]);
    $student_ids = $stmt_st->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($student_ids)) {
        $in_clause = implode(',', array_map('intval', $student_ids));
        $stmt_fi = $pdo->prepare("SELECT * FROM student_fee_items WHERE student_id IN ($in_clause) AND is_active = 1");
        $stmt_fi->execute();
        $fee_items = $stmt_fi->fetchAll();
        
        foreach ($fee_items as $item) {
            $amt  = (float)$item['amount'];
            $paid = (float)$item['paid_amount'];
            $disc = (float)$item['discount_amount'];
            $bal  = max(0.0, $amt - $disc - $paid);
            
            $fname = trim($item['fee_name']);
            $type  = $item['fee_type'];
            
            $target_col = null;
            
            if ($type === 'Monthly') {
                // Monthly fees split equally among months Apr to Mar
                foreach ($months as $m) {
                    $matrix[$class_id][$m]['total']    += $amt / 12;
                    $matrix[$class_id][$m]['paid']     += $paid / 12;
                    $matrix[$class_id][$m]['discount'] += $disc / 12;
                    $matrix[$class_id][$m]['balance']  += $bal / 12;
                }
            } else {
                if ($type === 'Due') {
                    $target_col = '2025 - 2026 Due Fees';
                } else if (strtolower($fname) === 'old' || strtolower($fname) === 'old fee' || strtolower($type) === 'old') {
                    $target_col = 'Old Fee';
                } else if (in_array($fname, ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'])) {
                    $target_col = $fname;
                } else if ($fname === 'Term Fee-1') {
                    if ($matrix[$class_id]['Term Fee-1_1']['total'] > 0) {
                        $target_col = 'Term Fee-1_2';
                    } else {
                        $target_col = 'Term Fee-1_1';
                    }
                } else {
                    // Fallback to linked month if applicable
                    $linked = $item['linked_to'];
                    if ($linked && in_array($linked, $months)) {
                        $target_col = $linked;
                    }
                }
                
                if ($target_col && isset($matrix[$class_id][$target_col])) {
                    $matrix[$class_id][$target_col]['total']    += $amt;
                    $matrix[$class_id][$target_col]['paid']     += $paid;
                    $matrix[$class_id][$target_col]['discount'] += $disc;
                    $matrix[$class_id][$target_col]['balance']  += $bal;
                }
            }
            
            // Accumulate to Row Totals
            $matrix[$class_id]['Total']['total']    += $amt;
            $matrix[$class_id]['Total']['paid']     += $paid;
            $matrix[$class_id]['Total']['discount'] += $disc;
            $matrix[$class_id]['Total']['balance']  += $bal;
        }
    }
}

// 3. CSV Download Handler
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=monthly_fees_collection_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    $header_labels = ['Classes/Month'];
    foreach ($cols as $col) {
        $header_labels[] = get_col_label($col);
    }
    fputcsv($output, $header_labels);
    
    foreach ($classes as $cl) {
        $row = [$cl['name']];
        foreach ($cols as $col) {
            $cell = $matrix[$cl['id']][$col] ?? ['total' => 0, 'paid' => 0, 'discount' => 0, 'balance' => 0];
            $row[] = sprintf(
                "Total: %d | Paid: %d | Disc: %d | Bal: %d",
                $cell['total'],
                $cell['paid'],
                $cell['discount'],
                $cell['balance']
            );
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// 4. Fetch overall summary banner stats
$stmt = $pdo->prepare("
    SELECT SUM(sfi.amount) as total_fees,
           SUM(sfi.paid_amount) as paid_fees,
           SUM(sfi.discount_amount) as discount_fees
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :school_id AND s.deleted_at IS NULL AND sfi.is_active = 1
");
$stmt->execute([':school_id' => $school_id]);
$summary = $stmt->fetch();
$total_fees    = (float)($summary['total_fees'] ?? 0);
$paid_fees     = (float)($summary['paid_fees'] ?? 0);
$discount_fees = (float)($summary['discount_fees'] ?? 0);
$balance_fees  = max(0.0, $total_fees - $discount_fees - $paid_fees);

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-1 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Monthly Fees Collection</h2>
        <p class="text-xs text-muted font-secondary mb-3 mt-1">Note: This fees does not include fine.</p>
    </div>
</div>

<!-- ── Summary Stats Banner ──────────────────────────────────────────────────── -->
<div class="row mb-4">
    <div class="col-12">
        <div class="p-3" style="background:var(--brand-light); border:1px solid var(--color-border); border-radius:var(--radius-lg); position:relative; box-shadow:var(--shadow-xs);">
            
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-4 flex-wrap text-dark">
                    <span class="font-secondary"><strong class="font-heading text-sm">Total Fees:</strong> (<?php echo number_format($total_fees, 0); ?>)</span>
                    <span class="font-secondary"><strong class="font-heading text-sm">Paid Fees:</strong> (<?php echo number_format($paid_fees, 0); ?>)</span>
                    <span class="font-secondary"><strong class="font-heading text-sm">Discount Fees:</strong> (<?php echo number_format($discount_fees, 0); ?>)</span>
                    <span class="font-secondary"><strong class="font-heading text-sm">Balance Fees:</strong> (<?php echo number_format($balance_fees, 0); ?>)</span>
                </div>
                
                <!-- Download Button -->
                <a href="?download=csv" class="btn btn-sm d-flex align-items-center justify-content-center p-2 rounded-circle shadow-sm text-white" style="width:38px; height:38px; background-color: var(--color-accent); border: none;" title="Download Report">
                    <i class="ph-light ph-cloud-arrow-down fs-5"></i>
                </a>
            </div>
            
            <div class="mt-2 text-xs font-secondary fw-semibold" style="color:var(--color-info);">
                Note: This fees does not include fine.
            </div>
            
        </div>
    </div>
</div>

<!-- ── Pivot Grid Table ──────────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="teacher-table table-premium mb-0 align-middle" id="monthlyCollectionTable" style="border: 1px solid var(--color-border);">
                        <thead>
                            <tr>
                                <th style="min-width: 140px; background:var(--gray-100); border-bottom: 2px solid var(--color-border) !important; border-right: 1px solid var(--color-border) !important;">Classes/Month</th>
                                <?php foreach ($cols as $col): ?>
                                    <th style="min-width: 155px; background:var(--gray-50); border-bottom: 2px solid var(--color-border) !important; border-right: 1px solid var(--color-border) !important; text-align: left;"><?php echo htmlspecialchars(get_col_label($col)); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $cl): $class_id = $cl['id']; ?>
                                <tr>
                                    <td class="fw-bold font-heading text-dark" style="background:var(--gray-50); border-right: 1px solid var(--color-border) !important; font-size:14px;"><?php echo sanitize($cl['name']); ?></td>
                                    <?php foreach ($cols as $col): 
                                        $cell = $matrix[$class_id][$col] ?? ['total' => 0, 'paid' => 0, 'discount' => 0, 'balance' => 0];
                                    ?>
                                        <td style="border-right: 1px solid var(--color-border) !important; border-bottom: 1px solid var(--color-border) !important;">
                                            <div class="text-xs font-secondary py-1" style="line-height: 1.45; color: var(--color-text-secondary);">
                                                <div>Total: <?php echo number_format($cell['total'], 0); ?></div>
                                                <div>Paid: <?php echo number_format($cell['paid'], 0); ?></div>
                                                <div>Discount: <?php echo number_format($cell['discount'], 0); ?></div>
                                                <div class="d-flex align-items-center gap-1 mt-1">
                                                    <span>Balance:</span>
                                                    <?php if ($cell['balance'] > 0): ?>
                                                        <span class="badge bg-danger rounded-pill px-2 py-0.5 text-white" style="font-size: 10px; font-weight: 600; line-height: 1.2; background-color: var(--danger) !important;">
                                                            <?php echo number_format($cell['balance'], 0); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
