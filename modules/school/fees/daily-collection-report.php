<?php
// modules/school/fees/daily-collection-report.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $mode       = $_POST['payment_mode'] ?? '';
    $format     = $_POST['format'] ?? 'Excel';

    if (!empty($start_date) && !empty($end_date)) {
        // Build SQL
        $where = "WHERE fp.school_id = :school_id AND fp.payment_date >= :start AND fp.payment_date <= :end";
        $params = [
            ':school_id' => $school_id,
            ':start'     => $start_date . ' 00:00:00',
            ':end'       => $end_date . ' 23:59:59'
        ];

        if ($mode && $mode !== 'All Payment Modes') {
            $where .= " AND fp.payment_method = :mode";
            $params[':mode'] = $mode;
        }

        $sql = "SELECT fp.*, s.first_name, s.last_name, s.admission_no, s.admission_no_prefix,
                       c.name as class_name, sec.name as section_name
                FROM fee_payments fp
                JOIN students s ON fp.student_id = s.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                $where
                ORDER BY fp.payment_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        if ($format === 'Excel') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=daily_fees_collection_report_' . $start_date . '_to_' . $end_date . '.csv');
            $output = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($output, ['Receipt No', 'Date & Time', 'Admission No', 'Student Name', 'Class-Section', 'Payment Mode', 'Amount Paid', 'Fine Amount', 'Remarks']);
            
            foreach ($payments as $p) {
                fputcsv($output, [
                    $p['transaction_id'] ?: $p['id'],
                    date('d-m-Y h:i A', strtotime($p['payment_date'])),
                    $p['admission_no'] ? ($p['admission_no_prefix'] . $p['admission_no']) : '—',
                    $p['first_name'] . ' ' . $p['last_name'],
                    ($p['class_name'] ?? '') . '-' . ($p['section_name'] ?? ''),
                    $p['payment_method'],
                    number_format($p['amount_paid'], 2),
                    number_format($p['fine_amount'], 2),
                    $p['remarks']
                ]);
            }
            fclose($output);
            exit;
        } else {
            // PDF: render a clean print layout
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Daily Fees Collection Report Printout</title>
                <style>
                    body { font-family: 'Poppins', sans-serif; margin: 30px; color: #333; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h2 { margin: 0; font-family: 'Metrophobic', sans-serif; font-weight: bold; }
                    .header p { margin: 5px 0 0 0; font-size: 14px; color: #666; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
                    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                    th { background-color: #f8fafc; font-weight: 600; }
                    .text-right { text-align: right; }
                    .fw-bold { font-weight: bold; }
                </style>
            </head>
            <body onload="window.print();">
                <div class="header">
                    <h2>Daily Fees Collection Report</h2>
                    <p>Period: <?php echo date('d-m-Y', strtotime($start_date)); ?> to <?php echo date('d-m-Y', strtotime($end_date)); ?> | Mode: <?php echo htmlspecialchars($mode); ?></p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Receipt / Txn ID</th>
                            <th>Date & Time</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Class-Section</th>
                            <th>Payment Mode</th>
                            <th class="text-right">Amount Paid</th>
                            <th class="text-right">Fine Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_paid = 0;
                        $total_fine = 0;
                        if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">No records found for the selected filters.</td>
                            </tr>
                        <?php else: 
                            foreach ($payments as $p): 
                                $total_paid += $p['amount_paid'];
                                $total_fine += $p['fine_amount'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['transaction_id'] ?: $p['id']); ?></td>
                                <td><?php echo date('d-m-Y h:i A', strtotime($p['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($p['admission_no'] ? ($p['admission_no_prefix'] . $p['admission_no']) : '—'); ?></td>
                                <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                                <td><?php echo htmlspecialchars(($p['class_name'] ?? '') . '-' . ($p['section_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                <td class="text-right"><?php echo number_format($p['amount_paid'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($p['fine_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="fw-bold">
                                <td colspan="6" class="text-right">Total:</td>
                                <td class="text-right"><?php echo number_format($total_paid, 2); ?></td>
                                <td class="text-right"><?php echo number_format($total_fine, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            exit;
        }
    }
}

// Default values for dates
$default_start = date('Y-m-d', strtotime('-1 week'));
$default_end   = date('Y-m-d');

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Daily Fees Collection Report</h2>
    </div>
</div>

<!-- ── Filter & Download Card ────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-header">
                <h6 class="fw-bold mb-0 font-heading">Filter & Download Report</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="daily-collection-report.php">
                    <div class="row g-3 mb-4">
                        
                        <!-- Start Date -->
                        <div class="col-md-3">
                            <label class="form-label-admin font-secondary mb-1">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" value="<?php echo $default_start; ?>" class="form-control-admin font-secondary text-secondary" required>
                        </div>
                        
                        <!-- End Date -->
                        <div class="col-md-3">
                            <label class="form-label-admin font-secondary mb-1">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" value="<?php echo $default_end; ?>" class="form-control-admin font-secondary text-secondary" required>
                        </div>
                        
                        <!-- Payment Modes -->
                        <div class="col-md-3">
                            <label class="form-label-admin font-secondary mb-1">Payment Modes</label>
                            <select name="payment_mode" class="form-control-admin font-secondary text-secondary">
                                <option value="All Payment Modes">All Payment Modes</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>
                        
                        <!-- Format -->
                        <div class="col-md-3">
                            <label class="form-label-admin font-secondary mb-1">Format</label>
                            <select name="format" class="form-control-admin font-secondary text-secondary">
                                <option value="Excel">Excel</option>
                                <option value="PDF">PDF</option>
                            </select>
                        </div>
                        
                    </div>
                    
                    <!-- Submit / Download Button -->
                    <div class="d-flex justify-content-start">
                        <button type="submit" class="btn font-secondary fw-semibold px-4" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none; height: 38px;">
                            Download
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
