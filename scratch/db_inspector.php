<?php
// scratch/db_inspector.php
require_once __DIR__ . '/../config/db.php';

try {
    echo "--- TABLES ---\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "Table: $table\n";
    }

    echo "\n--- DESCRIBE students ---\n";
    $stmt = $pdo->query("DESCRIBE students");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']} - {$col['Default']} - {$col['Extra']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
