<?php
// scratch/update_db.php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Alter students status enum
    echo "Altering students table status enum...\n";
    $pdo->exec("ALTER TABLE students MODIFY COLUMN status ENUM('active', 'inactive', 'passed', 'dropped', 'suspended') DEFAULT 'active'");

    // 2. Create student_migrations table
    echo "Creating student_migrations table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            from_session_id BIGINT UNSIGNED NOT NULL,
            to_session_id BIGINT UNSIGNED NOT NULL,
            from_class_id BIGINT UNSIGNED NULL,
            to_class_id BIGINT UNSIGNED NULL,
            from_section_id BIGINT UNSIGNED NULL,
            to_section_id BIGINT UNSIGNED NULL,
            total_students INT NOT NULL,
            student_ids TEXT NOT NULL,
            migrated_by VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->commit();
    echo "Database updated successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
