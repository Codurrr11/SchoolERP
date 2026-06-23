<?php
require_once dirname(__DIR__) . '/config/db.php';
try {
    $stmt = $pdo->prepare("SELECT * FROM student_attendance WHERE student_id = 18");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($records);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
