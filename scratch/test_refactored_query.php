<?php
// scratch/test_refactored_query.php
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/db.php';

try {
    $stmt = $pdo->query("SELECT id, school_id, father_name, mother_name FROM students WHERE deleted_at IS NULL");
    $all_students = $stmt->fetchAll();
    echo "Found " . count($all_students) . " students.\n";

    foreach ($all_students as $student) {
        $siblings = [];
        $student_id = $student['id'];
        $school_id = $student['school_id'];
        
        if (!empty($student['father_name']) || !empty($student['mother_name'])) {
            $conditions = [];
            $params = [
                ':school_id' => $school_id,
                ':id' => $student_id
            ];

            if (!empty($student['father_name'])) {
                $conditions[] = "s.father_name = :father_name";
                $params[':father_name'] = $student['father_name'];
            }
            if (!empty($student['mother_name'])) {
                $conditions[] = "s.mother_name = :mother_name";
                $params[':mother_name'] = $student['mother_name'];
            }

            $where_clause = implode(" OR ", $conditions);

            try {
                $stmt_sib = $pdo->prepare("
                    SELECT s.id, s.first_name, s.last_name, s.roll_no, c.name as class_name, sec.name as section_name, s.photo, s.admission_no_prefix, s.admission_no, s.total_fees, s.total_paid, s.total_discount, s.fine_amount
                    FROM   students s
                    LEFT JOIN classes c ON s.class_id = c.id
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    WHERE  s.school_id = :school_id
                      AND  s.id != :id
                      AND  ({$where_clause})
                      AND  s.deleted_at IS NULL
                ");
                $stmt_sib->execute($params);
                $siblings = $stmt_sib->fetchAll();
                echo "Student ID {$student_id}: successfully fetched " . count($siblings) . " siblings.\n";
            } catch (Exception $e) {
                echo "FAIL for Student ID " . $student['id'] . ": " . $e->getMessage() . "\n";
            }
        } else {
            echo "Student ID {$student_id}: no parent names, skipped.\n";
        }
    }
    echo "Test completed.\n";
} catch (Exception $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
