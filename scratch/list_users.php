<?php
require_once __DIR__ . '/../config/db.php';
$password_hash = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = 'admin@brightonschool.com'");
$stmt->execute([':password' => $password_hash]);
echo "Password for admin@brightonschool.com reset to password123 successfully.\n";
