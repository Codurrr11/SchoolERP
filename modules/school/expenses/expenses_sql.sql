-- ============================================================
-- Expenses Module - SQL Schema
-- Run this in phpMyAdmin or MySQL CLI against `schoolerp` DB
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
-- ─────────────────────────────────────────────────────────────────────────────
-- Table: expense_categories
-- Stores expense type/category names per school (e.g. Staff Salary, Refreshment)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ec_school` (`school_id`),
  KEY `idx_ec_name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ─────────────────────────────────────────────────────────────────────────────
-- Table: expenses
-- Core expense ledger
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `expense_type` VARCHAR(150) NOT NULL COMMENT 'Maps to expense_categories.name',
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_mode` VARCHAR(50) NOT NULL COMMENT 'Cash, UPI, Bank Transfer, Cheque, DD, Online',
  `payment_account` VARCHAR(150) DEFAULT NULL,
  `paid_by` VARCHAR(150) DEFAULT NULL COMMENT 'Staff who paid',
  `paid_to` VARCHAR(150) DEFAULT NULL COMMENT 'Vendor / party paid to',
  `narration` VARCHAR(255) DEFAULT NULL,
  `payment_txn_id` VARCHAR(200) DEFAULT NULL,
  `expense_date` DATETIME NOT NULL,
  `voucher_no` VARCHAR(100) DEFAULT NULL,
  `utr_reference_no` VARCHAR(200) DEFAULT NULL,
  `prepared_by` VARCHAR(150) DEFAULT NULL,
  `approved_by` VARCHAR(150) DEFAULT NULL,
  `received_by` VARCHAR(150) DEFAULT NULL,
  `expense_details` TEXT DEFAULT NULL COMMENT 'Rich-text/plain notes',
  `files` TEXT DEFAULT NULL COMMENT 'JSON array of file paths',
  `created_by` INT(11) DEFAULT NULL COMMENT 'users.id',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL COMMENT 'Soft-delete timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_exp_school` (`school_id`),
  KEY `idx_exp_type` (`expense_type`),
  KEY `idx_exp_date` (`expense_date`),
  KEY `idx_exp_deleted` (`deleted_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ─────────────────────────────────────────────────────────────────────────────
-- Seed: default expense categories (adjust school_id=1 to match your school)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `expense_categories` (`school_id`, `name`, `created_at`)
VALUES (1, 'Staff Salary Payments', NOW()),
  (1, 'Refreshment', NOW()),
  (1, 'Chalkpiece', NOW()),
  (1, 'Medical Expenses', NOW()),
  (1, 'Stationery', NOW()),
  (1, 'Printing And Stationary', NOW()),
  (1, 'Book', NOW()),
  (1, 'Budget', NOW()),
  (1, 'Staff Welfare', NOW());
COMMIT;