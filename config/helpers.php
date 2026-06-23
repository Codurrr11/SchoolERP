<?php
// config/helpers.php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/constants.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function auth_check($allowed_roles = []) {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    
    $user_role = $_SESSION['role_name'] ?? '';
    if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function enforce_tenant() {
    $role_name = $_SESSION['role_name'] ?? '';
    if ($role_name !== 'super_admin' && empty($_SESSION['school_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    return $_SESSION['school_id'] ?? null;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function get_academic_sessions($school_id) {
    global $pdo;
    if (!$pdo) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY start_date DESC");
    $stmt->execute([':school_id' => $school_id]);
    return $stmt->fetchAll();
}

function init_academic_session() {
    global $pdo;
    if (empty($_SESSION['school_id']) || !$pdo) return;

    $school_id = $_SESSION['school_id'];

    // Handle session change request via URL parameter
    if (isset($_GET['change_session_id'])) {
        $change_id = intval($_GET['change_session_id']);
        $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $change_id]);
        $sess = $stmt->fetch();
        if ($sess) {
            $_SESSION['academic_session_id'] = $sess['id'];
            $_SESSION['academic_session_name'] = $sess['name'];
            
            // Redirect back to clean URL
            $url = strtok($_SERVER["REQUEST_URI"], '?');
            $queryParams = $_GET;
            unset($queryParams['change_session_id']);
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }
            header("Location: " . $url);
            exit;
        }
    }

    // Default: If no active academic session in session, load the configured current one
    if (empty($_SESSION['academic_session_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id AND is_current = 1");
        $stmt->execute([':school_id' => $school_id]);
        $current = $stmt->fetch();
        
        if (!$current) {
            // Fallback: Use latest session
            $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY start_date DESC LIMIT 1");
            $stmt->execute([':school_id' => $school_id]);
            $current = $stmt->fetch();
        }

        if ($current) {
            $_SESSION['academic_session_id'] = $current['id'];
            $_SESSION['academic_session_name'] = $current['name'];
        } else {
            $_SESSION['academic_session_id'] = null;
            $_SESSION['academic_session_name'] = 'No Session';
        }
    }
}

