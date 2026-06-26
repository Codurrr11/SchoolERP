<?php
// modules/school/fees/defaulters.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

function get_default_fee_items($student)
{
    return [
        [
            'fee_name' => 'Admission Fee',
            'fee_type' => 'Yearly',
            'apply_to' => 'All',
            'linked_to' => 'Apr',
            'amount' => 3000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 2900.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => 'Old Fee',
            'fee_type' => 'Due',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 0.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => 'School Fee',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 14400.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 14400.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => 'Siksan',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 8400.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'Test Fees',
            'fee_type' => 'Yearly',
            'apply_to' => 'All',
            'linked_to' => 'Aug',
            'amount' => 500.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'Tuition Fees',
            'fee_type' => 'Yearly',
            'apply_to' => 'New',
            'linked_to' => 'Dec',
            'amount' => 25000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'Test By Raghav',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 2400.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'Test',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 1200.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'Ok',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 12000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'New Test Fee',
            'fee_type' => 'Due',
            'apply_to' => 'All',
            'linked_to' => 'Apr',
            'amount' => 0.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'New Admission Gdg',
            'fee_type' => 'Separate',
            'apply_to' => 'New',
            'linked_to' => null,
            'amount' => 10000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'Tution Fee',
            'fee_type' => 'Yearly',
            'apply_to' => 'All',
            'linked_to' => 'Apr',
            'amount' => 8000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 8000.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => 'Admission',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 12000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 12000.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => 'Term Fee-1',
            'fee_type' => 'Separate',
            'apply_to' => 'New',
            'linked_to' => null,
            'amount' => 1000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 0,
            'route_details' => null
        ],
        [
            'fee_name' => 'June',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 9000.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => '1210',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 14400.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => '1210 2',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 14400.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => null
        ],
        [
            'fee_name' => 'Transport Fees',
            'fee_type' => 'Monthly',
            'apply_to' => 'All',
            'linked_to' => null,
            'amount' => 22800.00,
            'discount_type' => '',
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'remark' => '',
            'is_active' => 1,
            'route_details' => 'Route 18 - 0 - 5'
        ]
    ];
}

// 1. AJAX Endpoint to fetch student details and fee items
if (isset($_GET['get_student_fees']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $sid = intval($_GET['id']);

    $stmt = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.admission_no, s.is_rte, s.is_bpl, s.father_name,
               c.name as class_name, sec.name as section_name,
               s.total_fees, s.total_paid, s.total_discount, s.fine_amount
        FROM   students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE  s.id = :id AND s.school_id = :school_id
    ");
    $stmt->execute([':id' => $sid, ':school_id' => $school_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        $stmt_f = $pdo->prepare("
            SELECT * FROM student_fee_items
            WHERE student_id = :student_id
            ORDER BY sort_order ASC
        ");
        $stmt_f->execute([':student_id' => $sid]);
        $items = $stmt_f->fetchAll();

        if (empty($items)) {
            $items = get_default_fee_items($student_data);
        }

        echo json_encode([
            'success' => true,
            'student' => $student_data,
            'items' => $items
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
    }
    exit;
}

// 2. Form submission handler for Save Fees Structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_fees_structure') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid CSRF token.";
        header("Location: defaulters.php");
        exit;
    }

    $student_id = intval($_POST['student_id']);
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = :id AND school_id = :school_id");
    $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = "Student not found.";
        header("Location: defaulters.php");
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt_del = $pdo->prepare("DELETE FROM student_fee_items WHERE student_id = :student_id");
        $stmt_del->execute([':student_id' => $student_id]);

        $total_fees = 0.00;
        $total_discount = 0.00;

        $stmt_ins = $pdo->prepare("
            INSERT INTO student_fee_items (
                student_id, fee_name, fee_type, apply_to, linked_to, amount,
                discount_type, discount_amount, paid_amount, remark, is_active, route_details
            ) VALUES (
                :student_id, :fee_name, :fee_type, :apply_to, :linked_to, :amount,
                :discount_type, :discount_amount, :paid_amount, :remark, :is_active, :route_details
            )
        ");

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $is_active = isset($item['is_active']) ? 1 : 0;
                $amount = floatval($item['amount'] ?? 0.00);
                $discount_amount = floatval($item['discount_amount'] ?? 0.00);
                $paid_amount = floatval($item['paid_amount'] ?? 0.00);

                $stmt_ins->execute([
                    ':student_id' => $student_id,
                    ':fee_name' => $item['fee_name'] ?? '',
                    ':fee_type' => $item['fee_type'] ?? '',
                    ':apply_to' => $item['apply_to'] ?? '',
                    ':linked_to' => !empty($item['linked_to']) ? $item['linked_to'] : null,
                    ':amount' => $amount,
                    ':discount_type' => $item['discount_type'] ?? '',
                    ':discount_amount' => $discount_amount,
                    ':paid_amount' => $paid_amount,
                    ':remark' => $item['remark'] ?? '',
                    ':is_active' => $is_active,
                    ':route_details' => !empty($item['route_details']) ? $item['route_details'] : null
                ]);

                if ($is_active) {
                    $total_fees += $amount;
                    $total_discount += $discount_amount;
                }
            }
        }

        $stmt_upd = $pdo->prepare("
            UPDATE students
            SET total_fees = :total_fees, total_discount = :total_discount
            WHERE id = :student_id
        ");
        $stmt_upd->execute([
            ':total_fees' => $total_fees,
            ':total_discount' => $total_discount,
            ':student_id' => $student_id
        ]);

        $pdo->commit();
        $_SESSION['flash_success'] = "Fees structure updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Failed to update fees structure: " . $e->getMessage();
    }
    header("Location: defaulters.php");
    exit;
}

// 3. Form submission handler for Delete Fees Structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_fees_structure') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid CSRF token.";
        header("Location: defaulters.php");
        exit;
    }

    $student_id = intval($_POST['student_id']);
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = :id AND school_id = :school_id");
    $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = "Student not found.";
        header("Location: defaulters.php");
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt_del = $pdo->prepare("DELETE FROM student_fee_items WHERE student_id = :student_id");
        $stmt_del->execute([':student_id' => $student_id]);

        $stmt_upd = $pdo->prepare("
            UPDATE students
            SET total_fees = 0.00, total_paid = 0.00, total_discount = 0.00, fine_amount = 0.00
            WHERE id = :student_id
        ");
        $stmt_upd->execute([':student_id' => $student_id]);

        $pdo->commit();
        $_SESSION['flash_success'] = "Fees structure deleted successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Failed to delete fees structure: " . $e->getMessage();
    }
    header("Location: defaulters.php");
    exit;
}

// 4. Form submission handler for Collect Fees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_fees') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid CSRF token.";
        header("Location: defaulters.php");
        exit;
    }
    
    $student_id = intval($_POST['student_id']);
    $stmt = $pdo->prepare("SELECT id, total_fees, total_paid, total_discount, fine_amount FROM students WHERE id = :id AND school_id = :school_id");
    $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = "Student not found.";
        header("Location: defaulters.php");
        exit;
    }
    
    // Handle file upload
    $screenshot_path = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['screenshot']['tmp_name'];
        $file_name = $_FILES['screenshot']['name'];
        $file_size = $_FILES['screenshot']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_exts)) {
            $_SESSION['flash_error'] = "Invalid file extension. Only JPG, JPEG, PNG & WEBP are allowed.";
            header("Location: defaulters.php");
            exit;
        }
        
        if ($file_size > 10 * 1024 * 1024) { // 10MB
            $_SESSION['flash_error'] = "File size exceeds 10MB limit.";
            header("Location: defaulters.php");
            exit;
        }
        
        $upload_dir = ROOT_PATH . 'uploads/fees_screenshots/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_file_name = uniqid('fee_', true) . '.' . $file_ext;
        $dest_path = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $dest_path)) {
            $screenshot_path = 'uploads/fees_screenshots/' . $new_file_name;
        } else {
            $_SESSION['flash_error'] = "Failed to upload screenshot.";
            header("Location: defaulters.php");
            exit;
        }
    }
    
    $amount_to_allocate = floatval($_POST['amount'] ?? 0.00);
    $selected_ids = $_POST['fee_items_to_pay'] ?? []; // Array of student_fee_items IDs
    
    $pdo->beginTransaction();
    try {
        // Fetch all active fee items for this student
        $stmt_f = $pdo->prepare("SELECT * FROM student_fee_items WHERE student_id = :student_id AND is_active = 1 ORDER BY sort_order ASC");
        $stmt_f->execute([':student_id' => $student_id]);
        $fee_items = $stmt_f->fetchAll();
        
        $eligible_items = [];
        foreach ($fee_items as $item) {
            // Calculate remaining balance for this item
            $net_due = floatval($item['amount']);
            $disc = floatval($item['discount_amount']);
            if ($item['discount_type'] === 'Percentage') {
                $net_due -= ($net_due * ($disc / 100));
            } else if ($item['discount_type'] === 'Fixed') {
                $net_due -= $disc;
            }
            if ($net_due < 0) $net_due = 0;
            $rem = $net_due - floatval($item['paid_amount']);
            
            if ($rem <= 0) continue; // Already fully paid
            
            // If specific items were checked, only allocate to those items
            if (!empty($selected_ids) && !in_array($item['id'], $selected_ids)) {
                continue;
            }
            
            $eligible_items[] = [
                'id' => $item['id'],
                'current_paid' => floatval($item['paid_amount']),
                'remaining' => $rem
            ];
        }
        
        // Allocate the payment amount
        $temp_amount = $amount_to_allocate;
        $update_item_stmt = $pdo->prepare("UPDATE student_fee_items SET paid_amount = :paid_amount WHERE id = :id");
        
        foreach ($eligible_items as $el) {
            if ($temp_amount <= 0) break;
            
            $pay = min($temp_amount, $el['remaining']);
            $new_paid = $el['current_paid'] + $pay;
            
            $update_item_stmt->execute([
                ':paid_amount' => $new_paid,
                ':id' => $el['id']
            ]);
            
            $temp_amount -= $pay;
        }
        
        // Update the student's total_paid in the students table
        $stmt_sum = $pdo->prepare("SELECT SUM(paid_amount) FROM student_fee_items WHERE student_id = :student_id AND is_active = 1");
        $stmt_sum->execute([':student_id' => $student_id]);
        $sum_paid = floatval($stmt_sum->fetchColumn());
        
        $stmt_upd_stud = $pdo->prepare("
            UPDATE students 
            SET total_paid = :total_paid
            WHERE id = :student_id
        ");
        $stmt_upd_stud->execute([
            ':total_paid' => $sum_paid,
            ':student_id' => $student_id
        ]);
        
        // Log transaction in fee_payments table
        $stmt_payment = $pdo->prepare("
            INSERT INTO fee_payments (
                school_id, student_id, amount_paid, fine_amount, payment_date, 
                payment_method, transaction_id, screenshot, remarks
            ) VALUES (
                :school_id, :student_id, :amount_paid, :fine_amount, :payment_date,
                :payment_method, :transaction_id, :screenshot, :remarks
            )
        ");
        
        $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d H:i:s');
        $parsed_time = strtotime($payment_date);
        if ($parsed_time !== false) {
            $payment_date = date('Y-m-d H:i:s', $parsed_time);
        }

        $stmt_payment->execute([
            ':school_id' => $school_id,
            ':student_id' => $student_id,
            ':amount_paid' => $amount_to_allocate,
            ':fine_amount' => 0.00,
            ':payment_date' => $payment_date,
            ':payment_method' => $_POST['payment_mode'] ?? 'Cash',
            ':transaction_id' => !empty($_POST['txn_no']) ? $_POST['txn_no'] : null,
            ':screenshot' => $screenshot_path,
            ':remarks' => !empty($_POST['remarks']) ? $_POST['remarks'] : null
        ]);
        
        $pdo->commit();
        $_SESSION['flash_success'] = "Fees payment collected successfully. Amount paid: " . number_format($amount_to_allocate, 2);
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Failed to collect fees: " . $e->getMessage();
    }
    header("Location: defaulters.php");
    exit;
}


// Fetch sessions, classes and sections for dropdowns
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY id DESC");
$stmt->execute([':school_id' => $school_id]);
$all_sessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();

// Get filter inputs
$search = $_GET['search'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$section_id = $_GET['section_id'] ?? '';
$type = $_GET['type'] ?? '';
$min_due = $_GET['min_due'] ?? '';
$max_due = $_GET['max_due'] ?? '';
$is_migrated = $_GET['is_migrated'] ?? 'All';

// Pagination setup
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Sort setupz
$order_by_opt = $_GET['order_by'] ?? 'Name asc';
$order_clause = "s.first_name ASC, s.last_name ASC";
if ($order_by_opt === 'Name desc') {
    $order_clause = "s.first_name DESC, s.last_name DESC";
} elseif ($order_by_opt === 'Admission No asc') {
    $order_clause = "s.admission_no ASC";
} elseif ($order_by_opt === 'Admission No desc') {
    $order_clause = "s.admission_no DESC";
} elseif ($order_by_opt === 'Balance asc') {
    $order_clause = "(s.total_fees + s.fine_amount - s.total_discount - s.total_paid) ASC";
} elseif ($order_by_opt === 'Balance desc') {
    $order_clause = "(s.total_fees + s.fine_amount - s.total_discount - s.total_paid) DESC";
}

// Build SQL where clause
$where = "WHERE s.school_id = :school_id AND s.deleted_at IS NULL";
$params = [':school_id' => $school_id];

// ALWAYS show only students with a fee structure created and who have a balance (Defaulters)
$where .= " AND s.total_fees > 0 AND (s.total_fees + s.fine_amount - s.total_discount - s.total_paid) > 0";

if ($search) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.mobile_no LIKE :search OR u.username LIKE :search OR s.father_name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Check if Class "All" checkbox is NOT checked
$class_all_checked = isset($_GET['class_all']) && $_GET['class_all'] == '1';
if ($class_id && !$class_all_checked) {
    $where .= " AND s.class_id = :class_id";
    $params[':class_id'] = intval($class_id);
}

if ($section_id) {
    $where .= " AND s.section_id = :section_id";
    $params[':section_id'] = intval($section_id);
}

if ($type) {
    if ($type === 'RTE') {
        $where .= " AND s.is_rte = 'yes'";
    } elseif ($type === 'BPL') {
        $where .= " AND s.is_bpl = 'yes'";
    } elseif ($type === 'Non-RTE') {
        $where .= " AND s.is_rte = 'no'";
    }
}

if ($min_due !== '') {
    $where .= " AND (s.total_fees + s.fine_amount - s.total_discount - s.total_paid) >= :min_due";
    $params[':min_due'] = floatval($min_due);
}

if ($max_due !== '') {
    $where .= " AND (s.total_fees + s.fine_amount - s.total_discount - s.total_paid) <= :max_due";
    $params[':max_due'] = floatval($max_due);
}

// Count total matching records for pagination
$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM students s
    JOIN users u ON s.user_id = u.id
    $where
");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated matching records
$sql = "
    SELECT s.*, u.username as u_name, c.name as class_name, sec.name as section_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    $where
    ORDER BY $order_clause
    LIMIT :limit OFFSET :offset
";

$stmt_data = $pdo->prepare($sql);
// Bind params
foreach ($params as $key => $val) {
    $stmt_data->bindValue($key, $val);
}
$stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_data->execute();
$students = $stmt_data->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- Fees Header Area -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-12">
        <h2 class="mb-1 font-heading fw-extrabold text-dark">Fee Defaulters</h2>

    </div>
</div>

<div class="row mb-3 g-3">
    <div class="col-12">
        <form method="GET" action="defaulters.php" id="feeFilterForm">
            <!-- Filter Toolbar -->
            <div class="fee-toolbar">
                <div class="fee-toolbar-left">
                    <button type="button" class="fee-btn-funnel" id="toggleFiltersBtn" title="Toggle Search Options">
                        <i class="ph-light ph-funnel"></i>
                    </button>
                    <button type="button" class="fee-btn-demand-bill" id="demandBillBtn" title="Download Demand Bill">
                        <i class="ph-light ph-cloud-arrow-down"></i> Demand bill
                    </button>
                </div>

                <div class="fee-toolbar-right">
                    <div class="fee-search-container">
                        <i class="ph-light ph-magnifying-glass text-muted"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, mobile, father name, username" class="fee-search-input">
                        <button type="submit" class="fee-search-btn">
                            <i class="ph-light ph-magnifying-glass"></i>
                        </button>
                    </div>
                    <div class="fee-total-badge">
                        <i class="ph-light ph-graduation-cap"></i>
                        Total Defaulters: <span class="count-num"><?php echo $total_records; ?></span>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Grid -->
            <div class="card-body p-3 border-top bg-light" id="filterPanel">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-admin mb-0">Select Classes:</label>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="class_all" id="classAllCheckbox" value="1" <?php echo $class_all_checked ? 'checked' : ''; ?>>
                                <label class="form-check-label text-xs" for="classAllCheckbox">All</label>
                            </div>
                        </div>
                        <select name="class_id" id="classSelect" class="form-control-admin" <?php echo $class_all_checked ? 'disabled' : ''; ?>>
                            <option value="">-- Select Classes --</option>
                            <?php foreach ($all_classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Select Sections:</label>
                        <select name="section_id" class="form-control-admin">
                            <option value="">-- Select Sections --</option>
                            <?php foreach ($all_sections as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $section_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">Select Type:</label>
                        <select name="type" class="form-control-admin">
                            <option value="">Select Type</option>
                            <option value="RTE" <?php echo $type === 'RTE' ? 'selected' : ''; ?>>RTE</option>
                            <option value="BPL" <?php echo $type === 'BPL' ? 'selected' : ''; ?>>BPL</option>
                            <option value="Non-RTE" <?php echo $type === 'Non-RTE' ? 'selected' : ''; ?>>Non-RTE</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">Min. Due Amount:</label>
                        <input type="number" name="min_due" value="<?php echo htmlspecialchars($min_due); ?>" class="form-control-admin" placeholder="Min. Due">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin mb-1">Max. Due Amount:</label>
                        <input type="number" name="max_due" value="<?php echo htmlspecialchars($max_due); ?>" class="form-control-admin" placeholder="Max. Due">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Is Migrated?</label>
                        <select name="is_migrated" class="form-control-admin">
                            <option value="All" <?php echo $is_migrated === 'All' ? 'selected' : ''; ?>>All</option>
                            <option value="Yes" <?php echo $is_migrated === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="No" <?php echo $is_migrated === 'No' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Show Results:</label>
                        <select name="limit" class="form-control-admin">
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-admin mb-1">Order By:</label>
                        <select name="order_by" class="form-control-admin">
                            <option value="Name asc" <?php echo $order_by_opt === 'Name asc' ? 'selected' : ''; ?>>Name asc</option>
                            <option value="Name desc" <?php echo $order_by_opt === 'Name desc' ? 'selected' : ''; ?>>Name desc</option>
                            <option value="Admission No asc" <?php echo $order_by_opt === 'Admission No asc' ? 'selected' : ''; ?>>Admission No asc</option>
                            <option value="Admission No desc" <?php echo $order_by_opt === 'Admission No desc' ? 'selected' : ''; ?>>Admission No desc</option>
                            <option value="Balance asc" <?php echo $order_by_opt === 'Balance asc' ? 'selected' : ''; ?>>Balance asc</option>
                            <option value="Balance desc" <?php echo $order_by_opt === 'Balance desc' ? 'selected' : ''; ?>>Balance desc</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" style="height:38px;">
                            <i class="ph-light ph-funnel"></i> Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($students)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-coins"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No matching defaulter records found</h5>
                            <p class="text-xs text-muted mb-0">Adjust your filter options to list defaulters.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">S.No.</th>
                                    <th style="width: 180px;">Details</th>
                                    <th>Student</th>
                                    <th style="width: 260px;">Fees</th>
                                    <th style="width: 190px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = $offset + 1;
                                foreach ($students as $s):
                                    $balance = floatval($s['total_fees']) + floatval($s['fine_amount']) - floatval($s['total_discount']) - floatval($s['total_paid']);
                                    $student_initials = strtoupper(substr($s['first_name'], 0, 1) . (isset($s['last_name']) ? substr($s['last_name'], 0, 1) : ''));
                                    $gender_title = ($s['gender'] === 'female') ? 'D/O' : 'S/O';
                                ?>
                                    <tr>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <td>
                                            <div class="d-flex flex-column text-sm font-semibold gap-1">
                                                <span>Admission No.: <strong class="text-dark"><?php echo sanitize($s['admission_no'] ? ($s['admission_no_prefix'] . $s['admission_no']) : '—'); ?></strong></span>
                                                <span>Roll No.: <strong class="text-dark"><?php echo sanitize($s['roll_no'] ?? '—'); ?></strong></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($s['photo']) && file_exists('../../../' . $s['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . $s['photo']; ?>" class="student-avatar" alt="Photo">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder">
                                                        <?php echo $student_initials; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="<?php echo BASE_URL; ?>modules/school/students/view.php?id=<?php echo $s['id']; ?>" class="student-name-link" style="font-size: 14.5px !important;">
                                                        <?php echo sanitize($s['first_name'] . ' ' . $s['last_name'] . ' ' . $gender_title . ' ' . ($s['father_name'] ?? '—')); ?>
                                                    </a>
                                                    <span class="text-sm text-secondary">Username: <strong class="text-dark"><?php echo sanitize($s['u_name']); ?></strong></span>
                                                    <span class="text-sm text-secondary">Classes: <strong class="text-dark"><?php echo sanitize(($s['class_name'] ?? '') . '-' . ($s['section_name'] ?? '')); ?></strong></span>
                                                    <span class="text-sm text-secondary">Mobile: <strong class="text-dark"><?php echo sanitize($s['mobile_no'] ?? '—'); ?></strong></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fee-breakdown-box">
                                                <div class="fee-breakdown-row">
                                                    <span class="fee-lbl">Total Fees:</span>
                                                    <span class="fee-val fee-val-total"><?php echo number_format($s['total_fees'], 2); ?></span>
                                                </div>
                                                <div class="fee-breakdown-row">
                                                    <span class="fee-lbl">Total Fine:</span>
                                                    <span class="fee-val fee-val-fine"><?php echo number_format($s['fine_amount'], 2); ?></span>
                                                </div>
                                                <div class="fee-breakdown-row">
                                                    <span class="fee-lbl">Total Discount:</span>
                                                    <span class="fee-val fee-val-discount"><?php echo number_format($s['total_discount'], 2); ?></span>
                                                </div>
                                                <div class="fee-breakdown-row">
                                                    <span class="fee-lbl">Total Paid:</span>
                                                    <span class="fee-val fee-val-paid"><?php echo number_format($s['total_paid'], 2); ?></span>
                                                </div>
                                                <div class="fee-breakdown-row">
                                                    <span class="fee-lbl fw-bold">Total Balance:</span>
                                                    <span class="fee-val fee-val-balance fw-extrabold"><?php echo number_format($balance, 2); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fee-actions-container">
                                                <button type="button" class="fee-action-btn fee-action-btn-update action-update-fee" data-student-name="<?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>" data-id="<?php echo $s['id']; ?>">
                                                    Update Fees Structure
                                                </button>
                                                <button type="button" class="fee-action-btn fee-action-btn-collect action-collect-fee" data-student-name="<?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>" data-id="<?php echo $s['id']; ?>">
                                                    Collect Fees
                                                </button>
                                                <button type="button" class="fee-action-btn fee-action-btn-demand action-demand-fee" data-student-name="<?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>" data-id="<?php echo $s['id']; ?>">
                                                    Demand Bill
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination area -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                        <span class="text-xs text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</span>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>&type=<?php echo urlencode($type); ?>&min_due=<?php echo urlencode($min_due); ?>&max_due=<?php echo urlencode($max_due); ?>&is_migrated=<?php echo urlencode($is_migrated); ?>&order_by=<?php echo urlencode($order_by_opt); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>&type=<?php echo urlencode($type); ?>&min_due=<?php echo urlencode($min_due); ?>&max_due=<?php echo urlencode($max_due); ?>&is_migrated=<?php echo urlencode($is_migrated); ?>&order_by=<?php echo urlencode($order_by_opt); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>&type=<?php echo urlencode($type); ?>&min_due=<?php echo urlencode($min_due); ?>&max_due=<?php echo urlencode($max_due); ?>&is_migrated=<?php echo urlencode($is_migrated); ?>&order_by=<?php echo urlencode($order_by_opt); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // 1. Toggle Filter Panel card
        const toggleBtn = document.getElementById('toggleFiltersBtn');
        const filterPanel = document.getElementById('filterPanel');
        if (toggleBtn && filterPanel) {
            toggleBtn.addEventListener('click', function() {
                if (filterPanel.style.display === 'none') {
                    filterPanel.style.display = 'block';
                    toggleBtn.classList.add('active');
                } else {
                    filterPanel.style.display = 'none';
                    toggleBtn.classList.remove('active');
                }
            });

            // Hide panel by default unless filters are active
            const hasActiveFilters = <?php echo ($class_id || $section_id || $type || $min_due || $max_due || $is_migrated !== 'All') ? 'true' : 'false'; ?>;
            if (!hasActiveFilters) {
                filterPanel.style.display = 'none';
            } else {
                toggleBtn.classList.add('active');
            }
        }

        // 2. Class Select "All" Checkbox Logic
        const classAllCheckbox = document.getElementById('classAllCheckbox');
        const classSelect = document.getElementById('classSelect');
        if (classAllCheckbox && classSelect) {
            classAllCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    classSelect.value = '';
                    classSelect.disabled = true;
                } else {
                    classSelect.disabled = false;
                }
            });
        }

        // 3. Action Buttons Alert Trigger
        const showToast = (message, icon = 'info') => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        };

        const updateFeesModalEl = document.getElementById('updateFeesModal');
        let updateFeesModal = null;
        if (updateFeesModalEl) {
             updateFeesModal = new bootstrap.Modal(updateFeesModalEl, {
                 focus: false
             });
        }

        let currentFeeItems = [];

        // Render the dynamic table of fee items
        function renderFeeItemsTable() {
            const tbody = document.getElementById('fees_structure_tbody');
            tbody.innerHTML = '';

            currentFeeItems.forEach((item, idx) => {
                const isPaid = parseFloat(item.paid_amount || 0) > 0;
                const isChecked = parseInt(item.is_active || 0) === 1;
                const amount = parseFloat(item.amount || 0);
                const discAmount = parseFloat(item.discount_amount || 0);

                let rowTotal = amount;
                if (isChecked) {
                    if (item.discount_type === 'Percentage') {
                        rowTotal = amount - (amount * (discAmount / 100));
                    } else if (item.discount_type === 'Fixed') {
                        rowTotal = amount - discAmount;
                    }
                } else {
                    rowTotal = 0;
                }
                if (rowTotal < 0) rowTotal = 0;

                const tr = document.createElement('tr');

                let feeTypeHtml = `
                <div class="fees-type-title text-sm font-semibold">${item.fee_name}</div>
                <div class="fees-type-sub text-xs text-muted" style="line-height:1.4;">
                    Type: ${item.fee_type}<br>
                    Apply To: ${item.apply_to}
                    ${item.linked_to ? '<br>Linked To: ' + item.linked_to : ''}
                    ${item.route_details ? `<br><span class="text-primary font-semibold d-inline-flex align-items-center gap-1">(${item.route_details}) <i class="ph-bold ph-pencil-simple edit-route-btn text-muted" style="cursor:pointer;" data-index="${idx}" title="Edit Route"></i></span>` : ''}
                </div>
                <input type="hidden" name="items[${idx}][fee_name]" value="${item.fee_name}">
                <input type="hidden" name="items[${idx}][fee_type]" value="${item.fee_type}">
                <input type="hidden" name="items[${idx}][apply_to]" value="${item.apply_to}">
                <input type="hidden" name="items[${idx}][linked_to]" value="${item.linked_to || ''}">
                <input type="hidden" name="items[${idx}][route_details]" value="${item.route_details || ''}">
                <input type="hidden" name="items[${idx}][paid_amount]" value="${item.paid_amount || 0}">
            `;

                let feesColHtml = `
                <input type="number" name="items[${idx}][amount]" class="fees-input-small item-amount-input"
                       value="${amount}" min="0" step="0.01" data-index="${idx}"
                       ${(isPaid || !isChecked) ? 'disabled' : ''}>
                ${isPaid ? '<span class="fees-badge-locked">It can\'t be changed.</span>' : ''}
            `;

                let discountColHtml = `
                <div class="d-flex align-items-center gap-1">
                    <select name="items[${idx}][discount_type]" class="fees-select-small item-discount-type"
                            data-index="${idx}" ${(isPaid || !isChecked) ? 'disabled' : ''}>
                        <option value="" ${item.discount_type === '' ? 'selected' : ''}></option>
                        <option value="Fixed" ${item.discount_type === 'Fixed' ? 'selected' : ''}>Fixed</option>
                        <option value="Percentage" ${item.discount_type === 'Percentage' ? 'selected' : ''}>%</option>
                    </select>
                    <input type="number" name="items[${idx}][discount_amount]" class="fees-input-small item-discount-amount"
                           value="${discAmount}" min="0" step="0.01" style="width: 70px;" data-index="${idx}"
                           ${(isPaid || !isChecked) ? 'disabled' : ''}>
                </div>
            `;

                let totalColHtml = `
                <input type="text" class="fees-input-small item-total-input" value="${rowTotal.toFixed(2)}" disabled>
                ${isPaid ? `<span class="fees-badge-paid">Paid: ${parseFloat(item.paid_amount).toFixed(2)}</span>` : ''}
            `;

                let remarkColHtml = `
                <input type="text" name="items[${idx}][remark]" class="form-control form-control-sm item-remark-input"
                       value="${item.remark || ''}" style="height: 30px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--color-border);"
                       data-index="${idx}" ${!isChecked ? 'disabled' : ''}>
            `;

                let toggleColHtml = `
                <div class="form-check form-switch d-flex justify-content-center p-0 m-0">
                    <input class="form-check-input m-0 item-active-toggle" type="checkbox"
                           name="items[${idx}][is_active]" value="1" data-index="${idx}"
                           ${isChecked ? 'checked' : ''} ${isPaid ? 'disabled' : ''} style="cursor: pointer;">
                </div>
            `;

                tr.innerHTML = `
                <td>${feeTypeHtml}</td>
                <td>${feesColHtml}</td>
                <td>${discountColHtml}</td>
                <td>${totalColHtml}</td>
                <td>${remarkColHtml}</td>
                <td style="text-align: center;">${toggleColHtml}</td>
            `;

                tbody.appendChild(tr);
            });

            recalculateTotals();
            attachTableEventListeners();
        }

        // Attach event listeners for real-time calculations inside the table
        function attachTableEventListeners() {
            document.querySelectorAll('.item-amount-input').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(this.dataset.index);
                    currentFeeItems[idx].amount = parseFloat(this.value) || 0;
                    updateRowTotal(this);
                });
            });

            document.querySelectorAll('.item-discount-type').forEach(select => {
                select.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.index);
                    currentFeeItems[idx].discount_type = this.value;
                    updateRowTotal(this);
                });
            });

            document.querySelectorAll('.item-discount-amount').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(this.dataset.index);
                    currentFeeItems[idx].discount_amount = parseFloat(this.value) || 0;
                    updateRowTotal(this);
                });
            });

            document.querySelectorAll('.item-remark-input').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(this.dataset.index);
                    currentFeeItems[idx].remark = this.value;
                });
            });

            document.querySelectorAll('.item-active-toggle').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.index);
                    const isChecked = this.checked;
                    currentFeeItems[idx].is_active = isChecked ? 1 : 0;

                    const tr = this.closest('tr');
                    const amtInput = tr.querySelector('.item-amount-input');
                    const discType = tr.querySelector('.item-discount-type');
                    const discAmt = tr.querySelector('.item-discount-amount');
                    const rmkInput = tr.querySelector('.item-remark-input');

                    const isPaid = parseFloat(currentFeeItems[idx].paid_amount || 0) > 0;

                    if (isChecked) {
                        if (!isPaid) {
                            amtInput.disabled = false;
                            discType.disabled = false;
                            discAmt.disabled = false;
                        }
                        rmkInput.disabled = false;
                    } else {
                        amtInput.disabled = true;
                        discType.disabled = true;
                        discAmt.disabled = true;
                        rmkInput.disabled = true;
                    }

                    updateRowTotal(this);
                });
            });

            document.querySelectorAll('.edit-route-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.index);
                    const currentRoute = currentFeeItems[idx].route_details || '';

                    Swal.fire({
                        title: 'Edit Route Details',
                        input: 'text',
                        inputValue: currentRoute,
                        inputLabel: 'Enter route description for Transport Fees:',
                        showCancelButton: true,
                        confirmButtonText: 'Update',
                        confirmButtonColor: '#0d6efd',
                        inputValidator: (value) => {
                            if (!value) {
                                return 'You need to write something!';
                            }
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            currentFeeItems[idx].route_details = result.value;
                            renderFeeItemsTable();
                        }
                    });
                });
            });
        }

        // Update individual row totals in real-time
        function updateRowTotal(elem) {
            const tr = elem.closest('tr');
            const idx = parseInt(elem.dataset.index);
            const item = currentFeeItems[idx];

            const amount = parseFloat(item.amount || 0);
            const discAmount = parseFloat(item.discount_amount || 0);

            let rowTotal = amount;
            if (parseInt(item.is_active || 0) === 1) {
                if (item.discount_type === 'Percentage') {
                    rowTotal = amount - (amount * (discAmount / 100));
                } else if (item.discount_type === 'Fixed') {
                    rowTotal = amount - discAmount;
                }
            } else {
                rowTotal = 0;
            }

            if (rowTotal < 0) rowTotal = 0;

            const totalInput = tr.querySelector('.item-total-input');
            if (totalInput) {
                totalInput.value = rowTotal.toFixed(2);
            }

            recalculateTotals();
        }

        // Calculate and update bottom footer totals
        function recalculateTotals() {
            let totalFees = 0;
            let totalDiscount = 0;
            let grandTotal = 0;

            currentFeeItems.forEach(item => {
                if (parseInt(item.is_active || 0) === 1) {
                    const amt = parseFloat(item.amount || 0);
                    const discAmt = parseFloat(item.discount_amount || 0);

                    totalFees += amt;

                    let actualDisc = 0;
                    if (item.discount_type === 'Percentage') {
                        actualDisc = amt * (discAmt / 100);
                    } else if (item.discount_type === 'Fixed') {
                        actualDisc = discAmt;
                    }

                    totalDiscount += actualDisc;
                    let rowTotal = amt - actualDisc;
                    if (rowTotal < 0) rowTotal = 0;
                    grandTotal += rowTotal;
                }
            });

            document.getElementById('footer_total_fees').textContent = totalFees.toFixed(2);
            document.getElementById('footer_total_discount').textContent = totalDiscount.toFixed(2);
            document.getElementById('footer_grand_total').textContent = grandTotal.toFixed(2);
        }

        // Toggle all fee items checkbox logic
        const toggleAllCheckbox = document.getElementById('toggle_all_fee_items');
        if (toggleAllCheckbox) {
            toggleAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                currentFeeItems.forEach(item => {
                    const isPaid = parseFloat(item.paid_amount || 0) > 0;
                    if (!isPaid) {
                        item.is_active = isChecked ? 1 : 0;
                    }
                });
                renderFeeItemsTable();
            });
        }

        // Add/Edit Transport Fees button logic
        const addTransportFeesBtn = document.getElementById('addTransportFeesBtn');
        if (addTransportFeesBtn) {
            addTransportFeesBtn.addEventListener('click', function() {
                let transportItem = currentFeeItems.find(item => item.fee_name === 'Transport Fees');

                Swal.fire({
                    title: 'Add/Edit Transport Fees',
                    html: `
                    <div class="mb-3 text-start">
                        <label class="form-label-admin mb-1">Transport Fee Amount:</label>
                        <input type="number" id="swal_trans_amount" class="form-control-admin" placeholder="Enter amount" value="${transportItem ? transportItem.amount : '22800.00'}">
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label-admin mb-1">Route Details:</label>
                        <input type="text" id="swal_trans_route" class="form-control-admin" placeholder="Route e.g. Route 18 - 0 - 5" value="${transportItem && transportItem.route_details ? transportItem.route_details : 'Route 18 - 0 - 5'}">
                    </div>
                `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Add/Update',
                    confirmButtonColor: '#0d6efd',
                    preConfirm: () => {
                        const amount = parseFloat(document.getElementById('swal_trans_amount').value) || 0;
                        const route = document.getElementById('swal_trans_route').value.trim();
                        if (!amount) {
                            Swal.showValidationMessage('Amount is required!');
                        }
                        if (!route) {
                            Swal.showValidationMessage('Route details are required!');
                        }
                        return {
                            amount,
                            route
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const data = result.value;
                        if (transportItem) {
                            transportItem.amount = data.amount;
                            transportItem.route_details = data.route;
                            transportItem.is_active = 1;
                        } else {
                            currentFeeItems.push({
                                fee_name: 'Transport Fees',
                                fee_type: 'Monthly',
                                apply_to: 'All',
                                linked_to: null,
                                amount: data.amount,
                                discount_type: '',
                                discount_amount: 0.00,
                                paid_amount: 0.00,
                                remark: '',
                                is_active: 1,
                                route_details: data.route
                            });
                        }
                        renderFeeItemsTable();
                        showToast('Transport fees item updated.', 'success');
                    }
                });
            });
        }

        // Delete entire fees structure confirmation
        const deleteFeesStructBtn = document.getElementById('deleteFeesStructBtn');
        if (deleteFeesStructBtn) {
            deleteFeesStructBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This will delete the entire fees structure and reset all received/due balances for this student!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteFeesStructureForm').submit();
                    }
                });
            });
        }

        // Form submission helper to enable disabled fields so they submit
        const updateFeesForm = document.getElementById('updateFeesForm');
        if (updateFeesForm) {
            updateFeesForm.addEventListener('submit', function() {
                this.querySelectorAll('input:disabled, select:disabled').forEach(input => {
                    input.removeAttribute('disabled');
                });
            });
        }

        // Update Fees button click listener
        document.querySelectorAll('.action-update-fee').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.dataset.id;

                Swal.fire({
                    title: 'Loading...',
                    text: 'Fetching student fees details.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch(`defaulters.php?get_student_fees=1&id=${studentId}`)
                    .then(res => res.json())
                    .then(res => {
                        Swal.close();
                        if (res.success) {
                            const student = res.student;
                            currentFeeItems = res.items;

                            document.getElementById('modal_student_name').textContent = student.first_name + ' ' + (student.last_name || '');
                            document.getElementById('modal_student_id').value = student.id;
                            document.getElementById('delete_modal_student_id').value = student.id;

                            document.getElementById('modal_det_admission_no').textContent = student.admission_no || '—';
                            document.getElementById('modal_det_admission_type').textContent = student.is_rte === 'yes' ? 'RTE' : (student.is_bpl === 'yes' ? 'BPL' : 'New');
                            document.getElementById('modal_det_student_name').textContent = student.first_name + ' ' + (student.last_name || '');
                            document.getElementById('modal_det_class_section').textContent = (student.class_name || '') + '-' + (student.section_name || '');
                            document.getElementById('modal_det_father_name').textContent = student.father_name || '—';

                            const totalPaid = parseFloat(student.total_paid || 0);
                            const totalDiscount = parseFloat(student.total_discount || 0);
                            const fineAmount = parseFloat(student.fine_amount || 0);
                            const totalReceived = totalPaid + fineAmount;

                            document.getElementById('modal_rec_fees').textContent = totalPaid.toFixed(2);
                            document.getElementById('modal_rec_discount').textContent = totalDiscount.toFixed(2);
                            document.getElementById('modal_rec_fine').textContent = fineAmount.toFixed(2);
                            document.getElementById('modal_rec_discount_fine').textContent = '0.00';
                            document.getElementById('modal_rec_total').textContent = totalReceived.toFixed(2);

                            renderFeeItemsTable();
                            updateFeesModal.show();
                        } else {
                            Swal.fire('Error', res.message || 'Failed to fetch details.', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.close();
                        console.error(err);
                        Swal.fire('Error', 'An error occurred while fetching data.', 'error');
                    });
            });
        });

        const collectFeesModalEl = document.getElementById('collectFeesModal');
        let collectFeesModal = null;
        if (collectFeesModalEl) {
             collectFeesModal = new bootstrap.Modal(collectFeesModalEl, {
                 focus: false
             });
        }
        
        let collectStudentObj = null;
        let collectFeeItems = [];
        
        // Toggle Select Fee Types Dropdown Menu
        const dropdownBtn = document.getElementById('feeTypesDropdownBtn');
        const dropdownMenu = document.getElementById('feeTypesDropdownMenu');
        if (dropdownBtn && dropdownMenu) {
            dropdownBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdownMenu.style.display === 'block') {
                    dropdownMenu.style.display = 'none';
                } else {
                    dropdownMenu.style.display = 'block';
                }
            });
            document.addEventListener('click', function(e) {
                if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                }
            });
        }

        // Helper to format date-time: DD-MM-YYYY HH:MM:SS
        function getFormattedCurrentDateTime() {
            const now = new Date();
            const dd = String(now.getDate()).padStart(2, '0');
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const yyyy = now.getFullYear();
            const hh = String(now.getHours()).padStart(2, '0');
            const min = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            return `${dd}-${mm}-${yyyy} ${hh}:${min}:${ss}`;
        }

        let collectInputMode = 'amount';

        // Dynamic fee calculation & display updates
        function updateCollectFeesCalculations() {
            if (!collectStudentObj) return;

            let totalFees = parseFloat(collectStudentObj.total_fees || 0);
            let totalFine = parseFloat(collectStudentObj.fine_amount || 0);
            let currentPaid = parseFloat(collectStudentObj.total_paid || 0);
            let totalDiscount = parseFloat(collectStudentObj.total_discount || 0);
            let baseBalance = totalFees + totalFine - totalDiscount - currentPaid;
            if (baseBalance < 0) baseBalance = 0;

            const amountInput = document.getElementById('collect_amount_input');
            const dueFeesInput = document.getElementById('collect_due_fees');
            
            let newPaid = currentPaid;
            let newBal = baseBalance;
            let finalDue = 0;

            if (collectInputMode === 'amount') {
                const enteredAmount = parseFloat(amountInput.value) || 0;
                newPaid = currentPaid + enteredAmount;
                newBal = baseBalance - enteredAmount;
                if (newBal < 0) newBal = 0;
                finalDue = enteredAmount;
                
                // Clear checked fee types in selection box
                document.querySelectorAll('.fee-type-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                document.getElementById('collect_select_all_fees').checked = false;
                document.getElementById('selectedFeeTypesText').textContent = "Click to select fee types";
                document.getElementById('selectedFeeTypesText').classList.add('text-muted');
            } else {
                // Calculate sum of checked fee items
                let checkedSum = 0;
                let checkedNames = [];
                const checkboxes = document.querySelectorAll('.fee-type-checkbox:checked');
                
                checkboxes.forEach(cb => {
                    const idx = parseInt(cb.dataset.index);
                    const item = collectFeeItems[idx];
                    
                    // Calculate remaining due for item
                    const net = parseFloat(item.amount || 0);
                    const disc = parseFloat(item.discount_amount || 0);
                    let net_due = net;
                    if (item.discount_type === 'Percentage') {
                        net_due = net - (net * (disc / 100));
                    } else if (item.discount_type === 'Fixed') {
                        net_due = net - disc;
                    }
                    if (net_due < 0) net_due = 0;
                    
                    const rem = net_due - parseFloat(item.paid_amount || 0);
                    if (rem > 0) {
                        checkedSum += rem;
                        checkedNames.push(item.fee_name);
                    }
                });

                if (checkboxes.length > 0) {
                    amountInput.value = checkedSum.toFixed(2);
                    newPaid = currentPaid + checkedSum;
                    newBal = baseBalance - checkedSum;
                    if (newBal < 0) newBal = 0;
                    finalDue = checkedSum;
                    
                    document.getElementById('selectedFeeTypesText').textContent = checkedNames.join(', ');
                    document.getElementById('selectedFeeTypesText').classList.remove('text-muted');
                    
                    // Check if all visible fee items are selected
                    const visibleCheckboxes = document.querySelectorAll('.fee-type-item:not(.d-none) .fee-type-checkbox');
                    const visibleChecked = document.querySelectorAll('.fee-type-item:not(.d-none) .fee-type-checkbox:checked');
                    document.getElementById('collect_select_all_fees').checked = (visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked.length);
                } else {
                    amountInput.value = '';
                    newPaid = currentPaid;
                    newBal = baseBalance;
                    finalDue = 0.00;
                    document.getElementById('selectedFeeTypesText').textContent = "Click to select fee types";
                    document.getElementById('selectedFeeTypesText').classList.add('text-muted');
                    document.getElementById('collect_select_all_fees').checked = false;
                }
            }

            // Update display labels dynamically in the top tables
            document.getElementById('collect_det_paid_fees').textContent = newPaid.toFixed(2);
            document.getElementById('collect_det_bal_fees').textContent = newBal.toFixed(2);
            dueFeesInput.value = finalDue.toFixed(2);
        }

        // Render list of fee items in the selection box
        function renderCollectFeeDropdown() {
            const menu = document.getElementById('feeTypesDropdownMenu');
            menu.innerHTML = '';
            
            collectFeeItems.forEach((item, idx) => {
                // Calculate remaining balance for this item
                const net = parseFloat(item.amount || 0);
                const disc = parseFloat(item.discount_amount || 0);
                let net_due = net;
                if (item.discount_type === 'Percentage') {
                    net_due = net - (net * (disc / 100));
                } else if (item.discount_type === 'Fixed') {
                    net_due = net - disc;
                }
                if (net_due < 0) net_due = 0;
                
                const rem = net_due - parseFloat(item.paid_amount || 0);
                if (rem <= 0) return; // Hide items that are already paid

                const div = document.createElement('div');
                div.className = 'fee-type-item';
                div.dataset.month = item.linked_to || '';
                div.dataset.type = item.fee_type || '';
                
                div.innerHTML = `
                    <input type="checkbox" name="fee_items_to_pay[]" value="${item.id}" class="fee-type-checkbox" data-index="${idx}" style="cursor: pointer;">
                    <div class="d-flex justify-content-between align-items-center w-100" style="font-weight: 500; cursor: pointer;">
                        <span>${item.fee_name} ${item.linked_to ? '(' + item.linked_to + ')' : ''}</span>
                        <span class="text-xs text-primary">Due: ${rem.toFixed(2)}</span>
                    </div>
                `;
                
                // Toggle checkbox on item click
                div.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox') {
                        const cb = this.querySelector('.fee-type-checkbox');
                        cb.checked = !cb.checked;
                    }
                    collectInputMode = 'types';
                    updateCollectFeesCalculations();
                });
                
                menu.appendChild(div);
            });
        }

        // Month filter change logic
        const monthFilter = document.getElementById('collect_month_filter');
        if (monthFilter) {
            monthFilter.addEventListener('change', function() {
                const selectedMonth = this.value;
                const items = document.querySelectorAll('.fee-type-item');
                
                items.forEach(item => {
                    const month = item.dataset.month;
                    const type = item.dataset.type;
                    
                    if (selectedMonth === 'All' || month === selectedMonth || (type === 'Monthly' && month === '')) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                        // Uncheck checkbox when hidden
                        const cb = item.querySelector('.fee-type-checkbox');
                        if (cb) cb.checked = false;
                    }
                });
                
                updateCollectFeesCalculations();
            });
        }

        // Select All checkbox logic
        const selectAllFeesCb = document.getElementById('collect_select_all_fees');
        if (selectAllFeesCb) {
            selectAllFeesCb.addEventListener('change', function() {
                const isChecked = this.checked;
                const visibleCheckboxes = document.querySelectorAll('.fee-type-item:not(.d-none) .fee-type-checkbox');
                
                visibleCheckboxes.forEach(cb => {
                    cb.checked = isChecked;
                });
                collectInputMode = 'types';
                updateCollectFeesCalculations();
            });
        }

        // Amount input change logic
        const collectAmountInput = document.getElementById('collect_amount_input');
        if (collectAmountInput) {
            collectAmountInput.addEventListener('input', function() {
                collectInputMode = 'amount';
                updateCollectFeesCalculations();
            });
            collectAmountInput.addEventListener('focus', function() {
                collectInputMode = 'amount';
                updateCollectFeesCalculations();
            });
        }

        // Trigger loading and opening collect fees modal
        document.querySelectorAll('.action-collect-fee').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.dataset.id;
                
                Swal.fire({
                    title: 'Loading...',
                    text: 'Fetching student fees details.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch(`defaulters.php?get_student_fees=1&id=${studentId}`)
                    .then(res => res.json())
                    .then(res => {
                        Swal.close();
                        if (res.success) {
                            collectStudentObj = res.student;
                            collectFeeItems = res.items;
                            
                            // Populate Student details
                            document.getElementById('collect_student_id').value = collectStudentObj.id;
                            document.getElementById('collect_det_admission_no').textContent = collectStudentObj.admission_no || '—';
                            document.getElementById('collect_det_student_name').textContent = collectStudentObj.first_name + ' ' + (collectStudentObj.last_name || '');
                            document.getElementById('collect_det_class_section').textContent = (collectStudentObj.class_name || '') + '-' + (collectStudentObj.section_name || '');
                            document.getElementById('collect_det_father_name').textContent = collectStudentObj.father_name || '—';
                            
                            // Populate Fees details
                            const totalFees = parseFloat(collectStudentObj.total_fees || 0);
                            const totalFine = parseFloat(collectStudentObj.fine_amount || 0);
                            const totalPaid = parseFloat(collectStudentObj.total_paid || 0);
                            const totalDiscount = parseFloat(collectStudentObj.total_discount || 0);
                            const balFees = totalFees + totalFine - totalDiscount - totalPaid;
                            
                            document.getElementById('collect_det_total_fees').textContent = totalFees.toFixed(2);
                            document.getElementById('collect_det_total_fine').textContent = totalFine.toFixed(2);
                            document.getElementById('collect_det_paid_fees').textContent = totalPaid.toFixed(2);
                            document.getElementById('collect_det_discount').textContent = totalDiscount.toFixed(2);
                            document.getElementById('collect_det_bal_fees').textContent = (balFees < 0 ? 0 : balFees).toFixed(2);
                            
                            // Reset input fields
                            collectInputMode = 'amount';
                            document.getElementById('collect_amount_input').value = '';
                            document.getElementById('collect_due_fees').value = '0.00';
                            document.getElementById('collect_payment_date').value = getFormattedCurrentDateTime();
                            document.getElementById('collect_payment_mode').value = '';
                            document.getElementById('collect_txn_no').value = '';
                            document.getElementById('collect_screenshot').value = '';
                            document.getElementById('collect_remarks').value = '';
                            
                            // Reset filter
                            if (monthFilter) monthFilter.value = 'All';
                            if (selectAllFeesCb) selectAllFeesCb.checked = false;
                            
                            // Render dropdown select list
                            renderCollectFeeDropdown();
                            
                            // Show modal
                            collectFeesModal.show();
                        } else {
                            Swal.fire('Error', res.message || 'Failed to fetch details.', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.close();
                        console.error(err);
                        Swal.fire('Error', 'An error occurred while fetching data.', 'error');
                    });
            });
        });

        document.querySelectorAll('.action-demand-fee').forEach(btn => {
            btn.addEventListener('click', function() {
                const name = this.dataset.studentName;
                showToast(`Demand Bill generated for ${name}`, 'success');
            });
        });

        const demandBillBtn = document.getElementById('demandBillBtn');
        const demandBillModalEl = document.getElementById('demandBillModal');
        let demandBillModal = null;
        if (demandBillModalEl) {
            demandBillModal = new bootstrap.Modal(demandBillModalEl, {
                focus: false
            });
        }
        if (demandBillBtn && demandBillModal) {
            demandBillBtn.addEventListener('click', function() {
                demandBillModal.show();
            });
        }

        // Pill select toggling logic
        document.querySelectorAll('.demand-pill').forEach(pill => {
            pill.addEventListener('click', function(e) {
                this.classList.toggle('active');
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = this.classList.contains('active');
                }
            });
        });

        // Select All Classes
        const selectAllClassesCb = document.getElementById('selectAllClasses');
        if (selectAllClassesCb) {
            selectAllClassesCb.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('#classesPillBox .demand-pill').forEach(pill => {
                    const checkbox = pill.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = isChecked;
                    }
                    if (isChecked) {
                        pill.classList.add('active');
                    } else {
                        pill.classList.remove('active');
                    }
                });
            });
        }

        // Select All Months
        const selectAllMonthsCb = document.getElementById('selectAllMonths');
        if (selectAllMonthsCb) {
            selectAllMonthsCb.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('#monthsPillBox .demand-pill').forEach(pill => {
                    const checkbox = pill.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = isChecked;
                    }
                    if (isChecked) {
                        pill.classList.add('active');
                    } else {
                        pill.classList.remove('active');
                    }
                });
            });
        }

        // Rich Text format helper
        window.formatDoc = function(cmd, value = null) {
            document.execCommand(cmd, false, value);
        };

        // Form submit rich text sync and alert
        const demandBillForm = document.getElementById('demandBillForm');
        if (demandBillForm) {
            demandBillForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const msgEditor = document.getElementById('demandMessageEditor');
                const msgInput = document.getElementById('demandMessageInput');
                if (msgEditor && msgInput) {
                    msgInput.value = msgEditor.innerHTML;
                }

                const rmkEditor = document.getElementById('demandRemarkEditor');
                const rmkInput = document.getElementById('demandRemarkInput');
                if (rmkEditor && rmkInput) {
                    rmkInput.value = rmkEditor.innerHTML;
                }

                if (demandBillModal) {
                    demandBillModal.hide();
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Demand Bill PDF Generated',
                    text: 'Bulk Demand Bill generation initiated successfully.',
                    confirmButtonColor: '#0d6efd'
                });
            });
        }
    });
</script>

<!-- Download Demand Bill Modal -->
<div class="modal fade" id="demandBillModal" tabindex="-1" aria-labelledby="demandBillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-extrabold font-heading text-dark" id="demandBillModalLabel">Download Demand Bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="#" method="POST" id="demandBillForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="modal-body d-flex flex-column gap-3 pt-2">

                    <!-- Select Classes -->
                    <div>
                        <div class="demand-modal-label-row">
                            <label class="demand-modal-label">Select Classes:</label>
                            <span class="demand-select-all">
                                <input type="checkbox" id="selectAllClasses"> Select All
                            </span>
                        </div>
                        <div class="demand-pill-box" id="classesPillBox">
                            <?php foreach ($all_classes as $c): ?>
                                <div class="demand-pill" data-type="class" data-value="<?php echo $c['id']; ?>">
                                    <span><?php echo sanitize($c['name']); ?></span>
                                    <i class="ph-bold ph-x demand-pill-close"></i>
                                    <input type="checkbox" name="classes[]" value="<?php echo $c['id']; ?>" class="d-none">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Select Sections -->
                    <div>
                        <div class="demand-modal-label-row">
                            <label class="demand-modal-label">Select Sections:</label>
                        </div>
                        <div class="demand-pill-box" id="sectionsPillBox">
                            <?php foreach ($all_sections as $s): ?>
                                <div class="demand-pill" data-type="section" data-value="<?php echo $s['id']; ?>">
                                    <span><?php echo sanitize($s['name']); ?></span>
                                    <i class="ph-bold ph-x demand-pill-close"></i>
                                    <input type="checkbox" name="sections[]" value="<?php echo $s['id']; ?>" class="d-none">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Select Months -->
                    <div>
                        <div class="demand-modal-label-row">
                            <label class="demand-modal-label">Select months:</label>
                            <span class="demand-select-all">
                                <input type="checkbox" id="selectAllMonths"> Select All
                            </span>
                        </div>
                        <div class="demand-pill-box" id="monthsPillBox">
                            <?php
                            $academic_months = [
                                'Apr' => 'April',
                                'May' => 'May',
                                'Jun' => 'June',
                                'Jul' => 'July',
                                'Aug' => 'August',
                                'Sep' => 'September',
                                'Oct' => 'October',
                                'Nov' => 'November',
                                'Dec' => 'December',
                                'Jan' => 'January',
                                'Feb' => 'February',
                                'Mar' => 'March'
                            ];
                            foreach ($academic_months as $short => $full):
                                $is_default = in_array($short, ['Apr', 'May', 'Jun']);
                            ?>
                                <div class="demand-pill <?php echo $is_default ? 'active' : ''; ?>" data-type="month" data-value="<?php echo $short; ?>">
                                    <span><?php echo $short; ?></span>
                                    <i class="ph-bold ph-x demand-pill-close"></i>
                                    <input type="checkbox" name="months[]" value="<?php echo $short; ?>" <?php echo $is_default ? 'checked' : ''; ?> class="d-none">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Separate Fees -->
                    <div>
                        <label class="demand-modal-label mb-1">Separate Fees <span class="text-muted fw-normal text-xs">(These fees are not linked to any month)</span>:</label>
                        <input type="text" name="separate_fees" class="form-control-admin" placeholder="Enter separate fee headers if any">
                    </div>

                    <!-- Heading -->
                    <div>
                        <label class="demand-modal-label mb-1">Heading:</label>
                        <input type="text" name="heading" value="Demand Bill" class="form-control-admin" required>
                    </div>

                    <!-- Message -->
                    <div>
                        <label class="demand-modal-label mb-1">Message:</label>
                        <div class="demand-editor-container">
                            <div class="demand-editor-toolbar">
                                <select class="demand-editor-select" onchange="formatDoc('formatBlock', this.value); this.selectedIndex=0;">
                                    <option value="" selected disabled>Paragraph</option>
                                    <option value="H1">Heading 1</option>
                                    <option value="H2">Heading 2</option>
                                    <option value="H3">Heading 3</option>
                                    <option value="p">Paragraph</option>
                                </select>
                                <select class="demand-editor-select" onchange="formatDoc('fontSize', this.value); this.selectedIndex=0;">
                                    <option value="" selected disabled>14px</option>
                                    <option value="1">10px</option>
                                    <option value="2">12px</option>
                                    <option value="3">14px</option>
                                    <option value="4">16px</option>
                                    <option value="5">18px</option>
                                </select>
                                <div class="demand-editor-btn-group">
                                    <button type="button" class="demand-editor-btn fw-bold" onclick="formatDoc('bold')" title="Bold">B</button>
                                    <button type="button" class="demand-editor-btn fst-italic" onclick="formatDoc('italic')" title="Italic">I</button>
                                    <button type="button" class="demand-editor-btn text-decoration-underline" onclick="formatDoc('underline')" title="Underline">U</button>
                                    <button type="button" class="demand-editor-btn text-decoration-line-through" onclick="formatDoc('strikeThrough')" title="Strikethrough">S</button>
                                </div>
                                <div class="demand-editor-btn-group">
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyLeft')" title="Align Left"><i class="ph-bold ph-align-left"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyCenter')" title="Align Center"><i class="ph-bold ph-align-center"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyRight')" title="Align Right"><i class="ph-bold ph-align-right"></i></button>
                                </div>
                                <div class="demand-editor-btn-group">
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('insertUnorderedList')" title="Bullet List"><i class="ph-bold ph-list-bullets"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('createLink', prompt('Enter URL:'))" title="Insert Link"><i class="ph-bold ph-link"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('insertImage', prompt('Enter Image URL:'))" title="Insert Image"><i class="ph-bold ph-image"></i></button>
                                </div>
                            </div>
                            <div class="demand-editor-textarea" contenteditable="true" id="demandMessageEditor">Dear Parent, Due Fee for your ward is detailed below. Please deposit the same (ignore if already paid).</div>
                            <input type="hidden" name="message" id="demandMessageInput">
                        </div>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="demand-modal-label mb-1">Date:</label>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" class="form-control-admin" required>
                    </div>

                    <!-- Remark -->
                    <div>
                        <label class="demand-modal-label mb-1">Remark:</label>
                        <div class="demand-editor-container">
                            <div class="demand-editor-toolbar">
                                <select class="demand-editor-select" onchange="formatDoc('formatBlock', this.value); this.selectedIndex=0;">
                                    <option value="" selected disabled>Paragraph</option>
                                    <option value="H1">Heading 1</option>
                                    <option value="H2">Heading 2</option>
                                    <option value="H3">Heading 3</option>
                                    <option value="p">Paragraph</option>
                                </select>
                                <select class="demand-editor-select" onchange="formatDoc('fontSize', this.value); this.selectedIndex=0;">
                                    <option value="" selected disabled>14px</option>
                                    <option value="1">10px</option>
                                    <option value="2">12px</option>
                                    <option value="3">14px</option>
                                    <option value="4">16px</option>
                                    <option value="5">18px</option>
                                </select>
                                <div class="demand-editor-btn-group">
                                    <button type="button" class="demand-editor-btn fw-bold" onclick="formatDoc('bold')" title="Bold">B</button>
                                    <button type="button" class="demand-editor-btn fst-italic" onclick="formatDoc('italic')" title="Italic">I</button>
                                    <button type="button" class="demand-editor-btn text-decoration-underline" onclick="formatDoc('underline')" title="Underline">U</button>
                                    <button type="button" class="demand-editor-btn text-decoration-line-through" onclick="formatDoc('strikeThrough')" title="Strikethrough">S</button>
                                </div>
                                <div class="demand-editor-btn-group">
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyLeft')" title="Align Left"><i class="ph-bold ph-align-left"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyCenter')" title="Align Center"><i class="ph-bold ph-align-center"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyRight')" title="Align Right"><i class="ph-bold ph-align-right"></i></button>
                                </div>
                                <div class="demand-editor-btn-group">
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('insertUnorderedList')" title="Bullet List"><i class="ph-bold ph-list-bullets"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('createLink', prompt('Enter URL:'))" title="Insert Link"><i class="ph-bold ph-link"></i></button>
                                    <button type="button" class="demand-editor-btn" onclick="formatDoc('insertImage', prompt('Enter Image URL:'))" title="Insert Image"><i class="ph-bold ph-image"></i></button>
                                </div>
                            </div>
                            <div class="demand-editor-textarea" contenteditable="true" id="demandRemarkEditor">Please pay your ward's all fee dues till 10th of Jun 2026 in case of non-submission, your ward's name will be cut off from the Attendance register.</div>
                            <input type="hidden" name="remark" id="demandRemarkInput">
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top-0 pt-0 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary font-heading fw-bold px-4" data-bs-dismiss="modal" style="background-color: #92a498; border-color: #92a498; height: 38px; border-radius: 8px;">Close</button>
                    <button type="submit" class="btn btn-primary font-heading fw-bold px-4" style="background-color: #0d6efd; border-color: #0d6efd; height: 38px; border-radius: 8px;">Download</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Fees Structure Modal -->
<div class="modal fade" id="updateFeesModal" tabindex="-1" aria-labelledby="updateFeesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-extrabold font-heading text-dark" id="updateFeesModalLabel">Update Fees Structure of <span id="modal_student_name" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="defaulters.php" method="POST" id="updateFeesForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="save_fees_structure">
                <input type="hidden" name="student_id" id="modal_student_id">

                <div class="modal-body d-flex flex-column gap-3 pt-2">

                    <!-- Alert note -->
                    <div class="alert alert-warning text-sm py-2 px-3 m-0" style="border-radius: 8px; font-weight: 500; color: #b45309; background-color: #fef3c7; border-color: #fde68a;">
                        <strong>Note:</strong> Once you have paid the fees of any specific field, you can't modify that field.
                    </div>

                    <!-- Student details -->
                    <div>
                        <div class="fw-bold text-xs text-secondary uppercase mb-2 font-heading" style="letter-spacing: 0.5px;">Student details:</div>
                        <div class="fees-details-table">
                            <div class="fees-details-item">Admission No.: <strong id="modal_det_admission_no">—</strong></div>
                            <div class="fees-details-item">Admission Type.: <strong id="modal_det_admission_type">New</strong></div>
                            <div class="fees-details-item">Student Name: <strong id="modal_det_student_name">—</strong></div>
                            <div class="fees-details-item">Class & Sections: <strong id="modal_det_class_section">—</strong></div>
                            <div class="fees-details-item">Father Name: <strong id="modal_det_father_name">—</strong></div>
                        </div>
                    </div>

                    <!-- Received Fees -->
                    <div>
                        <div class="fw-bold text-xs text-secondary uppercase mb-2 font-heading" style="letter-spacing: 0.5px;">Received Fees:</div>
                        <div class="fees-received-table">
                            <div class="fees-received-item">Received Fees: <strong id="modal_rec_fees">0.00</strong></div>
                            <div class="fees-received-item">Discount Fees: <strong id="modal_rec_discount">0.00</strong></div>
                            <div class="fees-received-item">Received Fine: <strong id="modal_rec_fine">0.00</strong></div>
                            <div class="fees-received-item">Discount Fine: <strong id="modal_rec_discount_fine">0.00</strong></div>
                            <div class="fees-received-item">Total Received: <strong id="modal_rec_total">0.00</strong></div>
                        </div>
                    </div>

                    <!-- Table section -->
                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: 8px;">
                        <table class="fees-struct-table table mb-0 align-middle">
                            <thead class="position-sticky top-0 bg-white" style="z-index: 10;">
                                <tr>
                                    <th style="width: 250px;">Fees Types</th>
                                    <th style="width: 140px;">Fees</th>
                                    <th style="width: 200px;">Discount</th>
                                    <th style="width: 130px;">Total</th>
                                    <th>Remark</th>
                                    <th style="width: 80px; text-align: center;">
                                        <div class="form-check form-switch d-inline-block p-0 m-0">
                                            <input class="form-check-input m-0" type="checkbox" id="toggle_all_fee_items" style="cursor: pointer;">
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="fees_structure_tbody">
                                <!-- Generated Dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="fees-total-row-bg">
                                    <td class="fw-bold" style="padding: 10px 14px !important;">Total</td>
                                    <td class="fw-bold" id="footer_total_fees" style="padding: 10px 14px !important;">0.00</td>
                                    <td class="fw-bold" id="footer_total_discount" style="padding: 10px 14px !important;">0.00</td>
                                    <td class="fw-bold" id="footer_grand_total" style="padding: 10px 14px !important;" colspan="3">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>
                <div class="modal-footer border-top-0 pt-0 d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-primary font-heading fw-bold px-3 me-2" id="addTransportFeesBtn" style="background-color: #0d6efd; border-color: #0d6efd; height: 38px; border-radius: 8px;">
                            <i class="ph-bold ph-plus"></i> Add Transport Fees
                        </button>
                        <button type="button" class="btn btn-danger font-heading fw-bold px-3" id="deleteFeesStructBtn" style="background-color: #dc3545; border-color: #dc3545; height: 38px; border-radius: 8px;">
                            Delete Fees Structure
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary font-heading fw-bold px-4 me-2" data-bs-dismiss="modal" style="background-color: #92a498; border-color: #92a498; height: 38px; border-radius: 8px;">Close</button>
                        <button type="submit" class="btn btn-primary font-heading fw-bold px-4" style="background-color: #0d6efd; border-color: #0d6efd; height: 38px; border-radius: 8px;">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Collect Fees Modal -->
<div class="modal fade" id="collectFeesModal" tabindex="-1" aria-labelledby="collectFeesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-extrabold font-heading text-dark" id="collectFeesModalLabel">Collect Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="defaulters.php" method="POST" id="collectFeesForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="collect_fees">
                <input type="hidden" name="student_id" id="collect_student_id">
                
                <div class="modal-body d-flex flex-column gap-4 pt-2">
                    
                    <!-- Row containing Student and Fees details side-by-side -->
                    <div class="row g-3">
                        <!-- Student Details -->
                        <div class="col-lg-6">
                            <div class="fw-bold text-xs text-secondary uppercase mb-2 font-heading" style="letter-spacing: 0.5px;">Student details:</div>
                            <div class="collect-student-table">
                                <div class="collect-cell-lbl">Admission No.:</div>
                                <div class="collect-cell-val" id="collect_det_admission_no">—</div>
                                <div class="collect-cell-lbl">Student Name:</div>
                                <div class="collect-cell-val" id="collect_det_student_name">—</div>
                                <div class="collect-cell-lbl">Classes & Sections:</div>
                                <div class="collect-cell-val" id="collect_det_class_section">—</div>
                                <div class="collect-cell-lbl">Father Name:</div>
                                <div class="collect-cell-val" id="collect_det_father_name">—</div>
                            </div>
                        </div>
                        
                        <!-- Fees Details -->
                        <div class="col-lg-6">
                            <div class="fw-bold text-xs text-secondary uppercase mb-2 font-heading" style="letter-spacing: 0.5px;">Fees details:</div>
                            <div class="collect-fees-table">
                                <div class="collect-cell-lbl">Total Fees:</div>
                                <div class="collect-cell-val" id="collect_det_total_fees">—</div>
                                <div class="collect-cell-lbl">Total Fine:</div>
                                <div class="collect-cell-val" id="collect_det_total_fine">—</div>
                                <div class="collect-cell-lbl">Paid Fees:</div>
                                <div class="collect-cell-val text-success fw-bold" id="collect_det_paid_fees">—</div>
                                <div class="collect-cell-lbl">Discount:</div>
                                <div class="collect-cell-val text-danger fw-bold" id="collect_det_discount">—</div>
                                <div class="collect-cell-lbl">Bal. Fees:</div>
                                <div class="collect-cell-val text-primary fw-extrabold" id="collect_det_bal_fees">—</div>
                                <div class="collect-cell-lbl"></div>
                                <div class="collect-cell-val"></div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-1" style="color: var(--color-border);">
                    
                    <!-- Section: Enter amount or select fees type to pay -->
                    <div>
                        <div class="fw-extrabold text-sm text-dark mb-3 font-heading">Enter amount or select fees type to pay:</div>
                        <div class="row g-4 align-items-start">
                            
                            <!-- Left Column: Enter Amount -->
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label-admin fw-bold mb-0">Enter Amount</label>
                                    <div class="text-xxs text-muted mt-0 mb-2" style="line-height: 1.3;">(Please enter an amount to adjust with the fees automatically.)</div>
                                    <input type="number" name="amount" id="collect_amount_input" class="form-control-admin" placeholder="0.00" min="0" step="0.01">
                                </div>
                            </div>
                            
                            <!-- Middle Column: OR Divider -->
                            <div class="col-md-2 text-center align-self-center">
                                <span class="fw-bold text-secondary text-sm">OR</span>
                            </div>
                            
                            <!-- Right Column: Select Fees Types -->
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label-admin fw-bold mb-1">Select Fees Types *:</label>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="form-check p-0 m-0 d-flex align-items-center gap-2">
                                            <input class="form-check-input ms-0 position-static" type="checkbox" id="collect_select_all_fees" style="cursor: pointer;">
                                            <label class="form-check-label text-xs fw-semibold" for="collect_select_all_fees" style="cursor: pointer;">Select All Fees</label>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-xxs text-muted fw-semibold" style="white-space: nowrap;">Search by Month:</span>
                                            <select id="collect_month_filter" class="form-control-admin py-0 px-2" style="height: 26px; font-size: 11.5px; width: 120px;">
                                                <option value="All">Filter by month</option>
                                                <option value="Apr">April</option>
                                                <option value="May">May</option>
                                                <option value="Jun">June</option>
                                                <option value="Jul">July</option>
                                                <option value="Aug">August</option>
                                                <option value="Sep">September</option>
                                                <option value="Oct">October</option>
                                                <option value="Nov">November</option>
                                                <option value="Dec">December</option>
                                                <option value="Jan">January</option>
                                                <option value="Feb">February</option>
                                                <option value="Mar">March</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Dropdown select container -->
                                    <div class="fee-types-select-box">
                                        <div class="form-control-admin d-flex justify-content-between align-items-center" id="feeTypesDropdownBtn" style="cursor: pointer;">
                                            <span id="selectedFeeTypesText" class="text-muted text-xs">Click to select fee types</span>
                                            <i class="ph-bold ph-caret-down text-muted" style="font-size: 11px;"></i>
                                        </div>
                                        <div class="fee-types-dropdown-menu" id="feeTypesDropdownMenu">
                                            <!-- List dynamically generated in JS -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Form fields (Due Fees, Payment Date, Payment Mode, UTR, Screenshot, Remark) -->
                    <div class="row g-3">
                        
                        <!-- Left Sub-column (Labels on Left, Inputs on Right) -->
                        <div class="col-md-12 d-flex flex-column gap-3">
                            
                            <!-- Due Fees Row -->
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label-admin fw-semibold mb-0" style="font-size: 13.5px;">Due Fees</label>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" id="collect_due_fees" class="form-control-admin bg-light" value="0.00" readonly style="height: 38px;">
                                </div>
                            </div>
                            
                            <!-- Payment Date Row -->
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label-admin fw-semibold mb-0" style="font-size: 13.5px;">Payment Date: *</label>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" name="payment_date" id="collect_payment_date" class="form-control-admin" required style="height: 38px;">
                                </div>
                            </div>
                            
                            <!-- Payment Mode Row -->
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label-admin fw-semibold mb-0" style="font-size: 13.5px;">Payment Mode: *</label>
                                </div>
                                <div class="col-md-8">
                                    <select name="payment_mode" id="collect_payment_mode" class="form-control-admin" required style="height: 38px;">
                                        <option value="">Select</option>
                                        <option value="Cash">Cash</option>
                                        <option value="UPI">UPI</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="DD">DD</option>
                                        <option value="Online">Online</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- UTR/Reference Row -->
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label-admin fw-semibold mb-0" style="font-size: 13.5px;">UTR/Reference/TXN No.:</label>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" name="txn_no" id="collect_txn_no" class="form-control-admin" placeholder="Enter transaction number" style="height: 38px;">
                                </div>
                            </div>
                            
                            <!-- Screenshot Row -->
                            <div class="row align-items-start">
                                <div class="col-md-4 pt-1">
                                    <label class="form-label-admin fw-semibold mb-0" style="font-size: 13.5px;">Screenshot: (If any)</label>
                                    <div class="text-xxs text-muted" style="line-height: 1.3;">Only JPEG, PNG, WEBP & JPG are allowed and max 10mb size.</div>
                                </div>
                                <div class="col-md-8">
                                    <input type="file" name="screenshot" id="collect_screenshot" class="form-control-admin pt-1" accept=".jpg,.jpeg,.png,.webp" style="height: 38px;">
                                </div>
                            </div>
                            
                            <!-- Remark Row -->
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label-admin fw-semibold mb-0" style="font-size: 13.5px;">Remark: (If any)</label>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" name="remarks" id="collect_remarks" class="form-control-admin" placeholder="Enter remark if any" style="height: 38px;">
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer border-top-0 pt-0 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary font-heading fw-bold px-4" style="background-color: #0d6efd; border-color: #0d6efd; height: 38px; border-radius: 8px;">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form action="defaulters.php" method="POST" id="deleteFeesStructureForm" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete_fees_structure">
    <input type="hidden" name="student_id" id="delete_modal_student_id">
</form>

<?php
require_once '../../../includes/footer.php';
?>
