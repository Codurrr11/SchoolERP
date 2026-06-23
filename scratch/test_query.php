<?php
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/db.php';

try {
    $stmt_sib = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.roll_no, c.name as class_name, sec.name as section_name, s.photo,
               s.admission_no_prefix, s.admission_no, s.total_fees, s.total_paid, s.total_discount, s.fine_amount
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
    $stmt_sib->execute([
        ':school_id' => 1,
        ':id' => 1,
        ':father_name1' => 'Test',
        ':father_name2' => 'Test',
        ':mother_name1' => 'Test',
        ':mother_name2' => 'Test'
    ]);
    echo "SQL query executes successfully!\n";
} catch (Exception $e) {
    echo "SQL Query failed with error: " . $e->getMessage() . "\n";
}
