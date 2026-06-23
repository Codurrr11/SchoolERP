<?php
require_once '../../config/helpers.php';
auth_check(['super_admin']); // Only super admin
require_once '../../config/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrf = $_GET['csrf'] ?? '';

if ($id > 0 && verify_csrf_token($csrf)) {
    try {
        $pdo->beginTransaction();
        
        // 1. Soft delete the school record
        $stmt = $pdo->prepare("UPDATE schools SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // 2. Soft delete the users (admins) belonging to this school
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE school_id = :id");
        $stmt->execute([':id' => $id]);

        // 3. Soft delete the academic sessions for this school
        $stmt = $pdo->prepare("UPDATE academic_sessions SET deleted_at = NOW() WHERE school_id = :id");
        $stmt->execute([':id' => $id]);

        $pdo->commit();
        
        // Redirect back with successful deleted parameter
        header('Location: schools.php?deleted=1');
        exit;
    } catch (\PDOException $e) {
        $pdo->rollBack();
        header('Location: schools.php?delete_error=1');
        exit;
    }
} else {
    header('Location: schools.php?invalid_request=1');
    exit;
}
