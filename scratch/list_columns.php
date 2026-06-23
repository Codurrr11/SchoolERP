<?php
require_once dirname(__DIR__) . '/config/db.php';
$stmt = $pdo->query('DESCRIBE students');
$columns = array_column($stmt->fetchAll(), 'Field');
print_r($columns);
