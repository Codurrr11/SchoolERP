-- ============================================================
-- SchoolERP Schema Fix Migration
-- Generated: 2026-06-26
-- Fixes: sort_order, class_name, section structure, etc.
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. FIX: classes table — add missing columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE `classes`
  ADD COLUMN IF NOT EXISTS `class_name` varchar(100) DEFAULT NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `roman_number` varchar(20) DEFAULT NULL AFTER `class_name`,
  ADD COLUMN IF NOT EXISTS `class_code` varchar(30) DEFAULT NULL AFTER `roman_number`,
  ADD COLUMN IF NOT EXISTS `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `class_code`,
  ADD COLUMN IF NOT EXISTS `status` enum('active','inactive') NOT NULL DEFAULT 'active' AFTER `sort_order`;

-- Back-fill class_name from name where class_name is empty
UPDATE `classes` SET `class_name` = `name` WHERE `class_name` IS NULL OR `class_name` = '';

-- Set sensible sort_order based on id if all are 0
UPDATE `classes` SET `sort_order` = `id` WHERE `sort_order` = 0;

-- ────────────────────────────────────────────────────────────
-- 2. FIX: sections table — add missing columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE `sections`
  ADD COLUMN IF NOT EXISTS `class_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `school_id`,
  ADD COLUMN IF NOT EXISTS `section_name` varchar(100) DEFAULT NULL AFTER `class_id`,
  ADD COLUMN IF NOT EXISTS `class_teacher_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `section_name`,
  ADD COLUMN IF NOT EXISTS `capacity` int(11) DEFAULT NULL AFTER `class_teacher_id`,
  ADD COLUMN IF NOT EXISTS `status` enum('active','inactive') NOT NULL DEFAULT 'active' AFTER `capacity`;

-- Back-fill section_name from name where section_name is empty
UPDATE `sections` SET `section_name` = `name` WHERE `section_name` IS NULL OR `section_name` = '';

-- ────────────────────────────────────────────────────────────
-- 3. FIX: lead_statuses table — add sort_order column
-- ────────────────────────────────────────────────────────────
ALTER TABLE `lead_statuses`
  ADD COLUMN IF NOT EXISTS `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `color`;

-- Set sensible sort_order based on id
UPDATE `lead_statuses` SET `sort_order` = `id` WHERE `sort_order` = 0;

-- ────────────────────────────────────────────────────────────
-- 4. FIX: student_fee_items table — add sort_order column
-- ────────────────────────────────────────────────────────────
ALTER TABLE `student_fee_items`
  ADD COLUMN IF NOT EXISTS `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `is_active`;

-- Set sensible sort_order based on id
UPDATE `student_fee_items` SET `sort_order` = `id` WHERE `sort_order` = 0;

-- ────────────────────────────────────────────────────────────
-- Done.
-- ────────────────────────────────────────────────────────────
