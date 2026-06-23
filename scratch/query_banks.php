<?php
require_once dirname(__DIR__) . '/config/db.php';
try {
    echo "USERS LIST:\n";
    $stmt = $pdo->query("SELECT id, username, email, role_id FROM users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
