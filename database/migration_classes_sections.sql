-- Database migration script
-- Standardizing classes and sections tables

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Alter classes table to add new columns
ALTER TABLE classes ADD COLUMN class_name varchar(50) DEFAULT NULL;
ALTER TABLE classes ADD COLUMN roman_number varchar(10) DEFAULT NULL;
ALTER TABLE classes ADD COLUMN class_code varchar(20) DEFAULT NULL;
ALTER TABLE classes ADD COLUMN sort_order int(11) DEFAULT 0;
ALTER TABLE classes ADD COLUMN status varchar(20) DEFAULT 'active';
ALTER TABLE classes ADD COLUMN updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();

-- Update existing classes
UPDATE classes SET class_name = 'Class 1', roman_number = 'I', sort_order = 3, class_code = 'C1', name = 'Class 1' WHERE id = 1;
UPDATE classes SET class_name = 'Class 2', roman_number = 'II', sort_order = 4, class_code = 'C2', name = 'Class 2' WHERE id = 2;
UPDATE classes SET class_name = 'Class 3', roman_number = 'III', sort_order = 5, class_code = 'C3', name = 'Class 3' WHERE id = 3;
UPDATE classes SET class_name = 'Class 4', roman_number = 'IV', sort_order = 6, class_code = 'C4', name = 'Class 4' WHERE id = 4;
UPDATE classes SET class_name = 'Nursery', roman_number = 'NUR', sort_order = 0, class_code = 'NUR', name = 'Nursery' WHERE id = 5;
UPDATE classes SET class_name = 'Class 5', roman_number = 'V', sort_order = 7, class_code = 'C5', name = 'Class 5' WHERE id = 6;

-- Insert other standard classes
INSERT INTO classes (school_id, class_name, name, roman_number, class_code, sort_order, status) VALUES
(1, 'LKG', 'LKG', 'LKG', 'LKG', 1, 'active'),
(1, 'UKG', 'UKG', 'UKG', 'UKG', 2, 'active'),
(1, 'Class 6', 'Class 6', 'VI', 'C6', 8, 'active'),
(1, 'Class 7', 'Class 7', 'VII', 'C7', 9, 'active'),
(1, 'Class 8', 'Class 8', 'VIII', 'C8', 10, 'active'),
(1, 'Class 9', 'Class 9', 'IX', 'C9', 11, 'active'),
(1, 'Class 10', 'Class 10', 'X', 'C10', 12, 'active'),
(1, 'Class 11', 'Class 11', 'XI', 'C11', 13, 'active'),
(1, 'Class 12', 'Class 12', 'XII', 'C12', 14, 'active');

-- Add constraints on classes table
ALTER TABLE classes MODIFY COLUMN class_name varchar(50) NOT NULL;
ALTER TABLE classes ADD UNIQUE INDEX uq_class_name (class_name);

-- 2. Migrate sections table to class-specific sections
-- Rename old sections table
RENAME TABLE sections TO sections_old;

-- Create new sections table
CREATE TABLE sections (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `class_teacher_id` bigint(20) UNSIGNED DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_section` (`class_id`, `section_name`),
  CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default sections A and B for all classes
INSERT INTO sections (school_id, class_id, section_name, name, status)
SELECT c.school_id, c.id, 'A', 'A', 'active' FROM classes c;

INSERT INTO sections (school_id, class_id, section_name, name, status)
SELECT c.school_id, c.id, 'B', 'B', 'active' FROM classes c;

-- Drop foreign key on teacher_classes temporarily
ALTER TABLE teacher_classes DROP FOREIGN KEY teacher_classes_ibfk_3;

-- Update sections references in students table
UPDATE students s
JOIN sections new_sec ON new_sec.class_id = s.class_id
JOIN sections_old old_sec ON old_sec.id = s.section_id
SET s.section_id = new_sec.id
WHERE new_sec.section_name = old_sec.name;

-- Update sections references in teacher_classes table
UPDATE teacher_classes tc
JOIN sections new_sec ON new_sec.class_id = tc.class_id
JOIN sections_old old_sec ON old_sec.id = tc.section_id
SET tc.section_id = new_sec.id
WHERE new_sec.section_name = old_sec.name;

-- Update sections references in student_migrations table
UPDATE student_migrations sm
JOIN sections new_from_sec ON new_from_sec.class_id = sm.from_class_id
JOIN sections_old old_from_sec ON old_from_sec.id = sm.from_section_id
SET sm.from_section_id = new_from_sec.id
WHERE new_from_sec.section_name = old_from_sec.name;

UPDATE student_migrations sm
JOIN sections new_to_sec ON new_to_sec.class_id = sm.to_class_id
JOIN sections_old old_to_sec ON old_to_sec.id = sm.to_section_id
SET sm.to_section_id = new_to_sec.id
WHERE new_to_sec.section_name = old_to_sec.name;

-- Re-add foreign keys and cleanup
ALTER TABLE teacher_classes ADD CONSTRAINT teacher_classes_ibfk_3 FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE;

DROP TABLE sections_old;

SET FOREIGN_KEY_CHECKS = 1;
