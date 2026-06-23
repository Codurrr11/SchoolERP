<?php
require_once dirname(__DIR__) . '/config/db.php';
try {
    echo "TABLES LIST:\n";
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo " - " . $row[0] . "\n";
    }

    echo "\nDESCRIBE expenses:\n";
    $columnsStmt = $pdo->query("DESCRIBE expenses");
    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   * " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\nDESCRIBE expense_categories:\n";
    $columnsStmt = $pdo->query("DESCRIBE expense_categories");
    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   * " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\nEXPENSE CATEGORIES LIST:\n";
    $stmt = $pdo->query("SELECT * FROM expense_categories");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
