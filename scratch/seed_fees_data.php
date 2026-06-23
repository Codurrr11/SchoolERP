<?php
// scratch/seed_fees_data.php
require_once dirname(__DIR__) . '/config/db.php';

try {
    // Truncate tables to get clean sample data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE student_fee_items");
    $pdo->exec("TRUNCATE TABLE fee_payments");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $fee_items_sql = "INSERT INTO student_fee_items 
        (student_id, fee_name, fee_type, apply_to, linked_to, amount, discount_type, discount_amount, paid_amount, remark, is_active, created_at)
        VALUES 
        (:student_id, :fee_name, :fee_type, 'All', 'Student', :amount, 'None', 0.00, :paid_amount, '', 1, NOW())";

    $payment_sql = "INSERT INTO fee_payments 
        (school_id, student_id, amount_paid, fine_amount, payment_date, payment_method, transaction_id, remarks)
        VALUES 
        (1, :student_id, :amount_paid, 0.00, :payment_date, :payment_method, :transaction_id, :remarks)";

    // Student 18: Kunal Verma
    $stmt1 = $pdo->prepare($fee_items_sql);
    $stmt1->execute([':student_id' => 18, ':fee_name' => 'Tuition Fee', ':fee_type' => 'Tuition Fee', ':amount' => 25000.00, ':paid_amount' => 25000.00]);
    $stmt1->execute([':student_id' => 18, ':fee_name' => 'Transport Fee', ':fee_type' => 'Transport Fee', ':amount' => 5000.00, ':paid_amount' => 5000.00]);
    
    $stmt2 = $pdo->prepare($payment_sql);
    $stmt2->execute([':student_id' => 18, ':amount_paid' => 25000.00, ':payment_date' => '2026-06-18 10:00:00', ':payment_method' => 'Cash', ':transaction_id' => 'TXN1001', ':remarks' => 'First Term tuition fee']);
    $stmt2->execute([':student_id' => 18, ':amount_paid' => 5000.00, ':payment_date' => '2026-06-18 10:15:00', ':payment_method' => 'Cash', ':transaction_id' => 'TXN1002', ':remarks' => 'First Term transport fee']);

    // Student 19: Sneha Patil
    $stmt1->execute([':student_id' => 19, ':fee_name' => 'Tuition Fee', ':fee_type' => 'Tuition Fee', ':amount' => 25000.00, ':paid_amount' => 15000.00]);
    $stmt2->execute([':student_id' => 19, ':amount_paid' => 15000.00, ':payment_date' => '2026-06-19 11:30:00', ':payment_method' => 'UPI', ':transaction_id' => 'TXN1003', ':remarks' => 'Partial Tuition fee payment']);

    // Student 20: Rohan Iyer
    $stmt1->execute([':student_id' => 20, ':fee_name' => 'Tuition Fee', ':fee_type' => 'Tuition Fee', ':amount' => 25000.00, ':paid_amount' => 0.00]);
    $stmt1->execute([':student_id' => 20, ':fee_name' => 'Hostel Fee', ':fee_type' => 'Hostel Fee', ':amount' => 12000.00, ':paid_amount' => 6000.00]);
    $stmt2->execute([':student_id' => 20, ':amount_paid' => 6000.00, ':payment_date' => '2026-06-19 14:00:00', ':payment_method' => 'Cheque', ':transaction_id' => 'TXN1004', ':remarks' => 'Hostel room deposit']);

    // Student 21: Priya Singh
    $stmt1->execute([':student_id' => 21, ':fee_name' => 'Tuition Fee', ':fee_type' => 'Tuition Fee', ':amount' => 25000.00, ':paid_amount' => 25000.00]);
    $stmt2->execute([':student_id' => 21, ':amount_paid' => 25000.00, ':payment_date' => '2026-06-20 09:30:00', ':payment_method' => 'Bank Transfer', ':transaction_id' => 'TXN1005', ':remarks' => 'Full tuition fee paid']);

    // Student 22: Aditya Rao
    $stmt1->execute([':student_id' => 22, ':fee_name' => 'Tuition Fee', ':fee_type' => 'Tuition Fee', ':amount' => 25000.00, ':paid_amount' => 25000.00]);
    $stmt2->execute([':student_id' => 22, ':amount_paid' => 25000.00, ':payment_date' => '2026-06-20 10:45:00', ':payment_method' => 'UPI', ':transaction_id' => 'TXN1006', ':remarks' => 'Full tuition fee paid via UPI']);

    echo "Fees data successfully seeded for 5 students!\n";
} catch (Exception $e) {
    echo "Error seeding database: " . $e->getMessage() . "\n";
}
