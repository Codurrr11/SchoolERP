<?php
// scratch/query_students.php
require_once __DIR__ . '/../config/db.php';

try {
    echo "--- DISTINCT student status ---\n";
    $stmt = $pdo->query("SELECT status, COUNT(*) FROM students GROUP BY status");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n--- DISTINCT user status ---\n";
    $stmt = $pdo->query("SELECT status, COUNT(*) FROM users GROUP BY status");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n--- Sample students ---\n";
    $stmt = $pdo->query("SELECT id, first_name, last_name, status, deleted_at FROM students LIMIT 10");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
