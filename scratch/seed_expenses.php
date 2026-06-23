<?php
// scratch/seed_expenses.php
require_once dirname(__DIR__) . '/config/db.php';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE school_id = 1");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "Expenses table already has data!\n";
    } else {
        $sql = "INSERT INTO expenses (
            school_id, expense_type, amount, payment_mode, payment_account,
            paid_by, paid_to, narration, payment_txn_id, expense_date,
            voucher_no, utr_reference_no, prepared_by, approved_by, received_by,
            expense_details, created_by, created_at
        ) VALUES (
            :school_id, :expense_type, :amount, :payment_mode, :payment_account,
            :paid_by, :paid_to, :narration, :payment_txn_id, :expense_date,
            :voucher_no, :utr_reference_no, :prepared_by, :approved_by, :received_by,
            :expense_details, :created_by, NOW()
        )";

        $stmt1 = $pdo->prepare($sql);
        $stmt1->execute([
            ':school_id'        => 1,
            ':expense_type'     => 'Refreshment',
            ':amount'           => 450.00,
            ':payment_mode'     => 'Cash',
            ':payment_account'  => 'Cash in Hand',
            ':paid_by'          => 'Madhu Singh',
            ':paid_to'          => 'Sharma Tea Stall',
            ':narration'        => 'Tea and snacks for staff meeting',
            ':payment_txn_id'   => null,
            ':expense_date'     => '2026-06-20 10:30:00',
            ':voucher_no'       => 'V-2026-001',
            ':utr_reference_no' => null,
            ':prepared_by'      => 'Madhu Singh',
            ':approved_by'      => 'Admin',
            ':received_by'      => 'Ramesh Sharma',
            ':expense_details'  => 'Refreshment for weekly review meeting.',
            ':created_by'       => 2
        ]);

        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute([
            ':school_id'        => 1,
            ':expense_type'     => 'Stationery',
            ':amount'           => 1200.00,
            ':payment_mode'     => 'UPI',
            ':payment_account'  => 'HDFC Bank',
            ':paid_by'          => 'Kunal Verma',
            ':paid_to'          => 'Vikas Stationers',
            ':narration'        => 'A4 paper packets and white board markers',
            ':payment_txn_id'   => 'TXN1234567890',
            ':expense_date'     => '2026-06-19 14:15:00',
            ':voucher_no'       => 'V-2026-002',
            ':utr_reference_no' => 'UTR9876543210',
            ':prepared_by'      => 'Kunal Verma',
            ':approved_by'      => 'Admin',
            ':received_by'      => 'Vikas Gupta',
            ':expense_details'  => 'Purchase of office stationery.',
            ':created_by'       => 2
        ]);

        echo "2 sample expense records successfully inserted!\n";
    }
} catch (Exception $e) {
    echo "Error seeding database: " . $e->getMessage() . "\n";
}
