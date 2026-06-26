<?php
/**
 * Dashboard Todos - AJAX Handler
 * Actions: add, toggle, delete
 * Returns JSON
 */
require_once __DIR__ . '/../../../config/helpers.php';
auth_check();
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$school_id = (int)($_SESSION['school_id'] ?? 0);
$user_id   = (int)($_SESSION['user_id']   ?? 0);

if (!$school_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Ensure the table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `dashboard_todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `due_label` varchar(100) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school_user` (`school_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $title     = trim($_POST['title'] ?? '');
    $due_label = trim($_POST['due_label'] ?? '');
    if ($title === '') {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO dashboard_todos (school_id, user_id, title, due_label) VALUES (:sid, :uid, :title, :due)");
    $stmt->execute([':sid' => $school_id, ':uid' => $user_id, ':title' => $title, ':due' => $due_label ?: null]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE dashboard_todos SET is_completed = 1 - is_completed WHERE id = :id AND school_id = :sid AND user_id = :uid");
    $stmt->execute([':id' => $id, ':sid' => $school_id, ':uid' => $user_id]);
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM dashboard_todos WHERE id = :id AND school_id = :sid AND user_id = :uid");
    $stmt->execute([':id' => $id, ':sid' => $school_id, ':uid' => $user_id]);
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
