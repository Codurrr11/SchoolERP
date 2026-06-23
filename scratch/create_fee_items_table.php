<?php
require_once dirname(__DIR__) . '/config/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `student_fee_items` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `student_id` BIGINT UNSIGNED NOT NULL,
        `fee_name` VARCHAR(100) NOT NULL,
        `fee_type` VARCHAR(50) NOT NULL,
        `apply_to` VARCHAR(50) NOT NULL,
        `linked_to` VARCHAR(50) NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `discount_type` VARCHAR(20) NULL,
        `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `remark` VARCHAR(255) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 0,
        `route_details` VARCHAR(100) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "student_fee_items table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
