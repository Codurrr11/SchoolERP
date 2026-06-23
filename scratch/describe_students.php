<?php
require_once dirname(__DIR__) . '/config/db.php';
$stmt = $pdo->query('DESCRIBE students');
print_r($stmt->fetchAll());
