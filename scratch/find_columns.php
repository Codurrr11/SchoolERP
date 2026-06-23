<?php
// scratch/find_columns.php
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $stmt_cols = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            $name = strtolower($col['Field']);
            if (strpos($name, 'status') !== false || 
                strpos($name, 'pass') !== false || 
                strpos($name, 'drop') !== false || 
                strpos($name, 'suspend') !== false || 
                strpos($name, 'migrate') !== false || 
                strpos($name, 'promote') !== false) {
                echo "Table: $table | Column: {$col['Field']} ({$col['Type']})\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
