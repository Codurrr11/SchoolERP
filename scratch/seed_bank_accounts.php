<?php
// scratch/seed_bank_accounts.php
require_once dirname(__DIR__) . '/config/db.php';

try {
    // Check if we already have these accounts or if we should just insert them
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_bank_accounts WHERE school_id = 1 AND bank_name IN ('HDFC Bank', 'State Bank of India')");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "Bank accounts already seeded or exist!\n";
    } else {
        $sql = "INSERT INTO payment_bank_accounts 
            (school_id, bank_name, branch, ifsc_code, address, account_holder, account_no, 
             linked_mobile, linked_email, bank_mobile, bank_email, upi, payment_modes, 
             opening_balance, status, remark, added_by) 
            VALUES 
            (:school_id, :bank_name, :branch, :ifsc_code, :address, :account_holder, :account_no, 
             :linked_mobile, :linked_email, :bank_mobile, :bank_email, :upi, :payment_modes, 
             :opening_balance, :status, :remark, :added_by)";

        $stmt1 = $pdo->prepare($sql);
        $stmt1->execute([
            ':school_id' => 1,
            ':bank_name' => 'HDFC Bank',
            ':branch' => 'Main Branch Kota',
            ':ifsc_code' => 'HDFC0000256',
            ':address' => '123, Shopping Centre, Kota, Rajasthan',
            ':account_holder' => 'Brighton School Kota',
            ':account_no' => '50100234567891',
            ':linked_mobile' => '9057137074',
            ':linked_email' => 'billing@brightonschool.com',
            ':bank_mobile' => '1800223344',
            ':bank_email' => 'support@hdfcbank.com',
            ':upi' => 'brightonschool@hdfc',
            ':payment_modes' => 'Cash,UPI,NEFT,RTGS,Cheque',
            ':opening_balance' => 50000.00,
            ':status' => 'Active',
            ':remark' => 'Primary school account for fee collection',
            ':added_by' => 2
        ]);

        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute([
            ':school_id' => 1,
            ':bank_name' => 'State Bank of India',
            ':branch' => 'Borkheda Branch',
            ':ifsc_code' => 'SBIN0012345',
            ':address' => 'Borkheda Road, Near Police Station, Kota, Rajasthan',
            ':account_holder' => 'Brighton School',
            ':account_no' => '31234567890',
            ':linked_mobile' => '9057137074',
            ':linked_email' => 'contact@brightonschool.com',
            ':bank_mobile' => '1800112211',
            ':bank_email' => 'sbi.012345@sbi.co.in',
            ':upi' => 'brightonschoolsbi@sbi',
            ':payment_modes' => 'UPI,NEFT,RTGS,Cheque,Card',
            ':opening_balance' => 25000.00,
            ':status' => 'Active',
            ':remark' => 'Secondary school account for administrative expenses',
            ':added_by' => 2
        ]);

        echo "2 bank accounts successfully inserted for the school!\n";
    }
} catch (Exception $e) {
    echo "Error seeding database: " . $e->getMessage() . "\n";
}
