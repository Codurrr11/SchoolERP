<?php
require_once dirname(__DIR__) . '/config/db.php';
try {
    echo "DESCRIBE fee_payments:\n";
    $columnsStmt = $pdo->query("DESCRIBE fee_payments");
    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   * " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\nDESCRIBE student_fee_items:\n";
    $columnsStmt = $pdo->query("DESCRIBE student_fee_items");
    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   * " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\nSAMPLE FEE PAYMENTS:\n";
    $stmt = $pdo->query("SELECT * FROM fee_payments LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
