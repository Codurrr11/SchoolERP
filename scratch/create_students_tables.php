<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/db.php';

try {
    // 1. Drop existing tables if needed or create if not exists
    $sql = "
    CREATE TABLE IF NOT EXISTS `students` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `school_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
        `session_id` BIGINT UNSIGNED NULL,
        `class_id` BIGINT UNSIGNED NULL,
        `section_id` BIGINT UNSIGNED NULL,
        
        -- Admission Details
        `apaar_id` VARCHAR(50) NULL,
        `pen_no` VARCHAR(50) NULL,
        `registration_no_prefix` VARCHAR(20) NULL,
        `registration_no` VARCHAR(50) NULL,
        `enrollment_no_prefix` VARCHAR(20) NULL,
        `enrollment_no` VARCHAR(50) NULL,
        `sr_no_prefix` VARCHAR(20) NULL,
        `sr_no` VARCHAR(50) NULL,
        `general_reg_no` VARCHAR(50) NULL,
        `admission_no_prefix` VARCHAR(20) NULL,
        `admission_no` VARCHAR(50) NULL,
        `admission_date` DATE NULL,
        `srn_no` VARCHAR(50) NULL,
        `roll_no` VARCHAR(50) NULL,
        `stream` VARCHAR(50) NULL,
        `education_medium` VARCHAR(50) NULL,
        `photo` VARCHAR(255) NULL,
        `referred_by` VARCHAR(100) NULL,
        `is_rte` ENUM('yes', 'no') DEFAULT 'no',
        `enrolled_session` VARCHAR(50) NULL,
        `enrolled_class_id` BIGINT UNSIGNED NULL,
        `enrolled_year` VARCHAR(10) NULL,
        `special_needs` ENUM('yes', 'no') DEFAULT 'no',
        `is_bpl` ENUM('yes', 'no') DEFAULT 'no',
        `house_block` VARCHAR(100) NULL,

        -- Personal Details
        `first_name` VARCHAR(80) NOT NULL,
        `last_name` VARCHAR(80) NULL,
        `father_name` VARCHAR(100) NULL,
        `mobile_no` VARCHAR(20) NULL,
        `alternate_no` VARCHAR(20) NULL,
        `whatsapp_no` VARCHAR(20) NULL,
        `email` VARCHAR(150) NULL,
        `gender` ENUM('male', 'female', 'other') DEFAULT 'male',
        `blood_group` VARCHAR(10) NULL,
        `height` VARCHAR(20) NULL,
        `weight` VARCHAR(20) NULL,
        `dob` DATE NULL,
        `place_of_birth` VARCHAR(100) NULL,
        `dob_certificate` VARCHAR(255) NULL,
        `dob_certificate_no` VARCHAR(100) NULL,
        
        -- Fee details for fast dashboard
        `total_fees` DECIMAL(10,2) DEFAULT 0.00,
        `total_paid` DECIMAL(10,2) DEFAULT 0.00,
        `total_discount` DECIMAL(10,2) DEFAULT 0.00,
        `fine_amount` DECIMAL(10,2) DEFAULT 0.00,

        `biometric_code` VARCHAR(50) NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL,
        
        FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `student_qualifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `student_id` BIGINT UNSIGNED NOT NULL,
        `qualification` VARCHAR(150) NOT NULL,
        `passing_year` VARCHAR(20) NULL,
        `roll_no` VARCHAR(50) NULL,
        `obtained_marks` VARCHAR(50) NULL,
        `percentage` VARCHAR(20) NULL,
        `subjects` VARCHAR(255) NULL,
        `school_college_name` VARCHAR(150) NULL,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `student_attendance` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `school_id` BIGINT UNSIGNED NOT NULL,
        `student_id` BIGINT UNSIGNED NOT NULL,
        `date` DATE NOT NULL,
        `status` ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'present',
        `check_in` TIME NULL,
        `check_out` TIME NULL,
        `leave_type` VARCHAR(50) NULL,
        `leave_reason` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_student_date` (`student_id`, `date`),
        FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Student tables created successfully.\n";

    // 2. Insert mock student data
    // Let's find school_id and a session_id
    $school = $pdo->query("SELECT id FROM schools LIMIT 1")->fetch();
    $session = $pdo->query("SELECT id FROM academic_sessions LIMIT 1")->fetch();
    $class = $pdo->query("SELECT id FROM classes WHERE name = 'Class 3' LIMIT 1")->fetch();
    $section = $pdo->query("SELECT id FROM sections WHERE name = 'A' LIMIT 1")->fetch();

    if ($school && $session && $class && $section) {
        $school_id = $school['id'];
        $session_id = $session['id'];
        $class_id = $class['id'];
        $section_id = $section['id'];

        // Create user first
        $stmt_user = $pdo->prepare("
            INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, status)
            VALUES (:school_id, 5, 'aaaa4', 'aa', 'tyagi', 'aa@tyagi.com', '9999999999', :password, 'active')
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        ");
        $stmt_user->execute([
            ':school_id' => $school_id,
            ':password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);
        $user_id = $pdo->lastInsertId();

        // Create student
        $stmt_stud = $pdo->prepare("
            INSERT INTO students (
                school_id, user_id, session_id, class_id, section_id,
                first_name, last_name, father_name, mobile_no, alternate_no, whatsapp_no, email,
                total_fees, total_paid, total_discount, fine_amount, status
            ) VALUES (
                :school_id, :user_id, :session_id, :class_id, :section_id,
                'aa', 'tyagi', 'Father Name', '9999999999', '', '9999999999', 'aa@tyagi.com',
                74600.00, 69600.00, 5000.00, 0.00, 'active'
            )
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        ");
        $stmt_stud->execute([
            ':school_id' => $school_id,
            ':user_id' => $user_id,
            ':session_id' => $session_id,
            ':class_id' => $class_id,
            ':section_id' => $section_id
        ]);
        $student_id = $pdo->lastInsertId();
        echo "Mock student aa tyagi created successfully.\n";

        // Seed 10 more mock students
        $first_names = ['Rahul', 'Anjali', 'Karan', 'Sneha', 'Vijay', 'Pooja', 'Amit', 'Neha', 'Rohan', 'Simran'];
        $last_names = ['Sharma', 'Verma', 'Kumar', 'Patel', 'Singh', 'Gupta', 'Mehta', 'Joshi', 'Chawla', 'Gill'];
        
        $stmt_ins_user = $pdo->prepare("
            INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, status)
            VALUES (:school_id, 5, :username, :first_name, :last_name, :email, :phone, :password, 'active')
        ");
        
        $stmt_ins_stud = $pdo->prepare("
            INSERT INTO students (
                school_id, user_id, session_id, class_id, section_id,
                first_name, last_name, father_name, mobile_no, whatsapp_no, email,
                total_fees, total_paid, total_discount, fine_amount, status
            ) VALUES (
                :school_id, :user_id, :session_id, :class_id, :section_id,
                :first_name, :last_name, :father_name, :mobile_no, :whatsapp_no, :email,
                :total_fees, :total_paid, :total_discount, :fine_amount, 'active'
            )
        ");

        for ($k = 0; $k < 10; $k++) {
            $fname = $first_names[$k];
            $lname = $last_names[$k];
            $uname = strtolower($fname . $k . rand(10, 99));
            $email = $uname . "@school.com";
            $mobile = "98765432" . sprintf("%02d", $k);
            
            $stmt_ins_user->execute([
                ':school_id' => $school_id,
                ':username' => $uname,
                ':first_name' => $fname,
                ':last_name' => $lname,
                ':email' => $email,
                ':phone' => $mobile,
                ':password' => password_hash('password123', PASSWORD_DEFAULT)
            ]);
            $u_id = $pdo->lastInsertId();

            $tot_fees = rand(30000, 80000);
            $tot_disc = rand(1000, 8000);
            $tot_paid = $tot_fees - $tot_disc - rand(0, 10000);
            
            $stmt_ins_stud->execute([
                ':school_id' => $school_id,
                ':user_id' => $u_id,
                ':session_id' => $session_id,
                ':class_id' => $class_id,
                ':section_id' => $section_id,
                ':first_name' => $fname,
                ':last_name' => $lname,
                ':father_name' => 'Mr. ' . $lname,
                ':mobile_no' => $mobile,
                ':whatsapp_no' => $mobile,
                ':email' => $email,
                ':total_fees' => $tot_fees,
                ':total_paid' => $tot_paid,
                ':total_discount' => $tot_disc,
                ':fine_amount' => 0.00
            ]);
            
            $stud_id = $pdo->lastInsertId();
            
            // Seed attendance for each student
            $stmt_att_ins = $pdo->prepare("
                INSERT INTO student_attendance (school_id, student_id, date, status, check_in, check_out)
                VALUES (:school_id, :student_id, :date, :status, :check_in, :check_out)
            ");
            for ($d = 0; $d < 15; $d++) {
                $time = strtotime("-$d days");
                if (date('N', $time) == 7) continue;
                $date_str = date('Y-m-d', $time);
                $status = (rand(1, 100) > 10) ? 'present' : 'absent';
                $check_in = ($status === 'present') ? '08:35:00' : null;
                $check_out = ($status === 'present') ? '14:30:00' : null;
                $stmt_att_ins->execute([
                    ':school_id' => $school_id,
                    ':student_id' => $stud_id,
                    ':date' => $date_str,
                    ':status' => $status,
                    ':check_in' => $check_in,
                    ':check_out' => $check_out
                ]);
            }
        }
        echo "Seeded 10 other mock students with attendance record.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
