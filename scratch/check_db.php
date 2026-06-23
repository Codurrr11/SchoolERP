<?php
require_once dirname(__DIR__) . '/config/db.php';
echo "Tables:\n";
try {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo " - " . $row[0] . "\n";
        $columnsStmt = $pdo->query("DESCRIBE `" . $row[0] . "`");
        while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   * " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
