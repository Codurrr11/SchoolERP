<?php
// scratch/test_real_student.php
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/db.php';

try {
    $stmt = $pdo->query("SELECT id, school_id, father_name, mother_name FROM students WHERE deleted_at IS NULL");
    $all_students = $stmt->fetchAll();
    echo "Found " . count($all_students) . " students.\n";

    $stmt_sib = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.roll_no, c.name as class_name, sec.name as section_name, s.photo, s.admission_no_prefix, s.admission_no, s.total_fees, s.total_paid, s.total_discount, s.fine_amount
        FROM   students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE  s.school_id = :school_id
          AND  s.id != :id
          AND  (
              (:father_name1 != '' AND s.father_name = :father_name2)
              OR (:mother_name1 != '' AND s.mother_name = :mother_name2)
          )
          AND  s.deleted_at IS NULL
    ");

    foreach ($all_students as $student) {
        if (!empty($student['father_name']) || !empty($student['mother_name'])) {
            $father_name_val = $student['father_name'] ?? '';
            $mother_name_val = $student['mother_name'] ?? '';
            
            try {
                $stmt_sib->execute([
                    ':school_id' => $student['school_id'],
                    ':id' => $student['id'],
                    ':father_name1' => $father_name_val,
                    ':father_name2' => $father_name_val,
                    ':mother_name1' => $mother_name_val,
                    ':mother_name2' => $mother_name_val
                ]);
                $results = $stmt_sib->fetchAll();
            } catch (Exception $e) {
                echo "FAIL for Student ID " . $student['id'] . ": " . $e->getMessage() . "\n";
            }
        }
    }
    echo "Test completed.\n";
} catch (Exception $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
