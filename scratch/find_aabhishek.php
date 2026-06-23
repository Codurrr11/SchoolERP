<?php
// scratch/find_aabhishek.php
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT s.id as student_id, s.first_name, s.last_name, s.status as s_status, s.deleted_at,
               u.id as user_id, u.status as u_status
        FROM   students s
        JOIN   users u ON s.user_id = u.id
        WHERE  s.first_name LIKE '%aabhishek%' OR s.admission_no = '1451/PRE'
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($results);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
