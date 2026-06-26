<?php
/**
 * Dashboard Virtual Assistant - AJAX Endpoint
 * Accepts POST: query (string)
 * Returns JSON: { reply: string }
 * All data is scoped to school_id from session.
 */
require_once __DIR__ . '/../../../config/helpers.php';
auth_check();
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['reply' => 'Invalid request method.']);
    exit;
}

$school_id  = (int)($_SESSION['school_id'] ?? 0);
$user_query = trim($_POST['query'] ?? '');

if (!$school_id || $user_query === '') {
    echo json_encode(['reply' => 'Please type a question I can help you with.']);
    exit;
}

$q = strtolower($user_query);

// ─── INTENT DETECTION ───────────────────────────────────────────────────────

// Helper: fetch single column
function db_val($pdo, $sql, $params = []) {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchColumn();
}

$reply = '';

// 1. STUDENTS
if (preg_match('/\bstudent(s)?\b/', $q)) {
    $total = (int)db_val($pdo, "SELECT COUNT(*) FROM students WHERE school_id = :sid AND deleted_at IS NULL", [':sid' => $school_id]);
    $active = (int)db_val($pdo, "SELECT COUNT(*) FROM students WHERE school_id = :sid AND status = 'active' AND deleted_at IS NULL", [':sid' => $school_id]);
    $dropped = (int)db_val($pdo, "SELECT COUNT(*) FROM students WHERE school_id = :sid AND status = 'dropped' AND deleted_at IS NULL", [':sid' => $school_id]);
    $reply = "📚 There are <strong>{$total}</strong> students enrolled. "
           . "<strong>{$active}</strong> are currently active, and <strong>{$dropped}</strong> have dropped out.";
}

// 2. TEACHERS / STAFF
elseif (preg_match('/\bteacher(s)?\b|\bstaff\b/', $q)) {
    $total = (int)db_val($pdo, "SELECT COUNT(*) FROM teachers WHERE school_id = :sid AND deleted_at IS NULL", [':sid' => $school_id]);
    $active = (int)db_val($pdo, "SELECT COUNT(*) FROM teachers WHERE school_id = :sid AND status = 'active' AND deleted_at IS NULL", [':sid' => $school_id]);
    $reply = "👩‍🏫 You have <strong>{$total}</strong> staff members, of which <strong>{$active}</strong> are currently active.";
}

// 3. FEE / FEES
elseif (preg_match('/\bfee(s)?\b|\bcollection\b|\bpayment(s)?\b/', $q)) {
    $assigned = (float)db_val($pdo,
        "SELECT COALESCE(SUM(sfi.amount), 0) FROM student_fee_items sfi JOIN students s ON sfi.student_id = s.id WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL",
        [':sid' => $school_id]);
    $collected = (float)db_val($pdo,
        "SELECT COALESCE(SUM(sfi.paid_amount), 0) FROM student_fee_items sfi JOIN students s ON sfi.student_id = s.id WHERE s.school_id = :sid AND sfi.is_active = 1 AND s.deleted_at IS NULL",
        [':sid' => $school_id]);
    $outstanding = max(0, $assigned - $collected);
    $pct = $assigned > 0 ? round(($collected / $assigned) * 100, 1) : 0;
    $reply = "💰 Total fee assigned: <strong>₹" . number_format($assigned, 2) . "</strong>. "
           . "Collected so far: <strong>₹" . number_format($collected, 2) . "</strong> ({$pct}%). "
           . "Outstanding: <strong>₹" . number_format($outstanding, 2) . "</strong>.";
}

// 4. DEFAULTERS
elseif (preg_match('/\bdefaulter(s)?\b|\bdue(s)?\b|\bpending\b/', $q)) {
    $defaulters = (int)db_val($pdo,
        "SELECT COUNT(DISTINCT sfi.student_id) FROM student_fee_items sfi JOIN students s ON sfi.student_id = s.id WHERE s.school_id = :sid AND sfi.is_active = 1 AND sfi.paid_amount < sfi.amount AND s.deleted_at IS NULL",
        [':sid' => $school_id]);
    $reply = "⚠️ There are <strong>{$defaulters}</strong> students with pending fee dues. You can view the full list in the Fees → Defaulters section.";
}

// 5. LEADS / ADMISSIONS
elseif (preg_match('/\blead(s)?\b|\binquir(y|ies)\b|\badmission(s)?\b/', $q)) {
    $total = (int)db_val($pdo, "SELECT COUNT(*) FROM leads WHERE school_id = :sid AND deleted_at IS NULL", [':sid' => $school_id]);
    $new = (int)db_val($pdo, "SELECT COUNT(*) FROM leads WHERE school_id = :sid AND deleted_at IS NULL AND WEEK(created_at) = WEEK(NOW())", [':sid' => $school_id]);
    $reply = "🎯 You have <strong>{$total}</strong> total leads/inquiries, with <strong>{$new}</strong> new ones this week. Check the Leads module for details.";
}

// 6. EXPENSES
elseif (preg_match('/\bexpense(s)?\b|\bcost(s)?\b|\bspend\b|\bspent\b/', $q)) {
    $total_exp = (float)db_val($pdo, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE school_id = :sid AND deleted_at IS NULL", [':sid' => $school_id]);
    $this_month = (float)db_val($pdo, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE school_id = :sid AND deleted_at IS NULL AND MONTH(expense_date) = MONTH(NOW()) AND YEAR(expense_date) = YEAR(NOW())", [':sid' => $school_id]);
    $reply = "📊 Total expenses recorded: <strong>₹" . number_format($total_exp, 2) . "</strong>. "
           . "This month's expenses: <strong>₹" . number_format($this_month, 2) . "</strong>.";
}

// 7. CLASS / CLASSES
elseif (preg_match('/\bclass(es)?\b|\bsection(s)?\b/', $q)) {
    $cls_count = (int)db_val($pdo, "SELECT COUNT(*) FROM classes WHERE school_id = :sid AND status = 'active'", [':sid' => $school_id]);
    $sec_count = (int)db_val($pdo, "SELECT COUNT(*) FROM sections WHERE school_id = :sid AND status = 'active'", [':sid' => $school_id]);
    $reply = "🏫 There are <strong>{$cls_count}</strong> active classes with <strong>{$sec_count}</strong> sections configured. Manage them in Class Management.";
}

// 8. SESSION / ACADEMIC YEAR
elseif (preg_match('/\bsession(s)?\b|\bacademic year\b/', $q)) {
    $sess = $pdo->prepare("SELECT name, start_date, end_date FROM academic_sessions WHERE school_id = :sid AND is_current = 1 LIMIT 1");
    $sess->execute([':sid' => $school_id]);
    $row = $sess->fetch();
    if ($row) {
        $reply = "📅 Current academic session: <strong>{$row['name']}</strong> ("
               . date('d M Y', strtotime($row['start_date'])) . " – "
               . date('d M Y', strtotime($row['end_date'])) . ").";
    } else {
        $reply = "📅 No active academic session found. Please configure one in the Sessions module.";
    }
}

// 9. HELLO / HI / GREETINGS
elseif (preg_match('/\b(hello|hi|hey|namaste|good|help|assist)\b/', $q)) {
    $school_name = sanitize($_SESSION['school_name'] ?? 'your school');
    $reply = "👋 Hello! I'm your school assistant for <strong>{$school_name}</strong>. "
           . "You can ask me about students, teachers, fees, defaulters, leads, expenses, classes, or the current academic session!";
}

// 10. PARENTS
elseif (preg_match('/\bparent(s)?\b/', $q)) {
    $total = (int)db_val($pdo, "SELECT COUNT(*) FROM parents WHERE school_id = :sid AND deleted_at IS NULL", [':sid' => $school_id]);
    $reply = "👨‍👩‍👧 There are <strong>{$total}</strong> parents registered in the system.";
}

// FALLBACK
else {
    $reply = "🤔 I can help you with information about <strong>students, teachers, fees, defaulters, leads, classes, expenses</strong>, or the <strong>academic session</strong>. Try asking about one of these!";
}

echo json_encode(['reply' => $reply]);
