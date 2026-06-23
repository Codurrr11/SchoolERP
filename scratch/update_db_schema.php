<?php
// scratch/update_db_schema.php
require_once dirname(__DIR__) . '/config/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `fee_payments` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `school_id` BIGINT UNSIGNED NOT NULL,
        `student_id` BIGINT UNSIGNED NOT NULL,
        `amount_paid` DECIMAL(10,2) NOT NULL,
        `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_date` DATETIME NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `transaction_id` VARCHAR(100) NULL,
        `screenshot` VARCHAR(255) NULL,
        `remarks` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "fee_payments table created or verified successfully.\n";
} catch (Exception $e) {
    echo "Error creating fee_payments table: " . $e->getMessage() . "\n";
}
