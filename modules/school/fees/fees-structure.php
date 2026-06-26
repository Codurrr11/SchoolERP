<?php
// modules/school/fees/fees-structure.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// 1. Fetch sessions for dropdown
$stmt_sess = $pdo->prepare("SELECT name FROM academic_sessions WHERE school_id = :school_id ORDER BY id DESC");
$stmt_sess->execute([':school_id' => $school_id]);
$sessions = $stmt_sess->fetchAll(PDO::FETCH_COLUMN);

$current_session = $_GET['session'] ?? ($_SESSION['academic_session_name'] ?? ($sessions[0] ?? ''));

// 2. Fetch all classes for checkbox list
$stmt_cl = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt_cl->execute([':school_id' => $school_id]);
$all_classes = $stmt_cl->fetchAll();

// 3. Process Add POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_structure') {
    $new_name = trim($_POST['fee_name'] ?? '');
    $new_type = trim($_POST['fee_type'] ?? '');
    $apply_to = $_POST['apply_to'] ?? 'all';
    $linked_to = $_POST['linked_to'] ?: null;
    
    $classes_input = $_POST['classes'] ?? [];
    
    if (!empty($new_name) && !empty($new_type)) {
        try {
            $pdo->beginTransaction();
            
            // Check if structure already exists
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) 
                FROM student_fee_items sfi
                JOIN students s ON sfi.student_id = s.id
                WHERE s.school_id = :school_id AND sfi.fee_name = :fee_name AND sfi.fee_type = :fee_type
            ");
            $stmt_check->execute([
                ':school_id' => $school_id,
                ':fee_name' => $new_name,
                ':fee_type' => $new_type
            ]);
            $count = $stmt_check->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("A fee structure with name '{$new_name}' and type '{$new_type}' already exists.");
            }
            
            // Fetch all active students for this school
            $stmt_stud = $pdo->prepare("SELECT id, class_id FROM students WHERE school_id = :school_id AND deleted_at IS NULL");
            $stmt_stud->execute([':school_id' => $school_id]);
            $students = $stmt_stud->fetchAll();
            
            $stmt_ins = $pdo->prepare("
                INSERT INTO student_fee_items (student_id, fee_name, fee_type, apply_to, linked_to, amount, is_active)
                VALUES (:student_id, :fee_name, :fee_type, :apply_to, :linked_to, :amount, :is_active)
            ");
            
            foreach ($students as $student) {
                $cid = $student['class_id'];
                $is_active = isset($classes_input[$cid]['active']) ? 1 : 0;
                $amount = $is_active ? (float)($classes_input[$cid]['amount'] ?? 0) : 0.00;
                
                $stmt_ins->execute([
                    ':student_id' => $student['id'],
                    ':fee_name' => $new_name,
                    ':fee_type' => $new_type,
                    ':apply_to' => $apply_to,
                    ':linked_to' => $linked_to,
                    ':amount' => $amount,
                    ':is_active' => $is_active
                ]);
            }
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Fees structure added successfully!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = "Failed to add fees structure: " . $e->getMessage();
        }
    }
    
    header("Location: fees-structure.php?session=" . urlencode($current_session));
    exit;
}

// Process Delete POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_structure') {
    $fee_name = trim($_POST['fee_name'] ?? '');
    $fee_type = trim($_POST['fee_type'] ?? '');
    
    if (!empty($fee_name) && !empty($fee_type)) {
        try {
            $pdo->beginTransaction();
            
            // Delete matching fee structures for students of this school
            $stmt_del = $pdo->prepare("
                DELETE sfi 
                FROM student_fee_items sfi
                JOIN students s ON sfi.student_id = s.id
                WHERE s.school_id = :school_id
                  AND sfi.fee_name = :fee_name
                  AND sfi.fee_type = :fee_type
            ");
            $stmt_del->execute([
                ':school_id' => $school_id,
                ':fee_name' => $fee_name,
                ':fee_type' => $fee_type
            ]);
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Fees structure deleted successfully!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = "Failed to delete fees structure: " . $e->getMessage();
        }
    }
    
    header("Location: fees-structure.php?session=" . urlencode($current_session));
    exit;
}

// 3. Process Update POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_structure') {
    $original_name = $_POST['original_fee_name'] ?? '';
    $original_type = $_POST['original_fee_type'] ?? '';
    
    $new_name = trim($_POST['fee_name'] ?? '');
    $new_type = trim($_POST['fee_type'] ?? '');
    $apply_to = $_POST['apply_to'] ?? 'all';
    $linked_to = $_POST['linked_to'] ?: null;
    
    $classes_input = $_POST['classes'] ?? [];
    
    if (!empty($original_name) && !empty($original_type)) {
        try {
            $pdo->beginTransaction();
            
            // Check if there are any matching rows in student_fee_items
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) 
                FROM student_fee_items sfi
                JOIN students s ON sfi.student_id = s.id
                WHERE s.school_id = :school_id AND sfi.fee_name = :fee_name AND sfi.fee_type = :fee_type
            ");
            $stmt_check->execute([
                ':school_id' => $school_id,
                ':fee_name' => $original_name,
                ':fee_type' => $original_type
            ]);
            $count = $stmt_check->fetchColumn();
            
            if ($count > 0) {
                // UPDATE METADATA for all student_fee_items matching the name/type
                $stmt_up_meta = $pdo->prepare("
                    UPDATE student_fee_items sfi
                    JOIN students s ON sfi.student_id = s.id
                    SET sfi.fee_name = :new_name,
                        sfi.fee_type = :new_type,
                        sfi.apply_to = :apply_to,
                        sfi.linked_to = :linked_to
                    WHERE s.school_id = :school_id
                      AND sfi.fee_name = :original_name
                      AND sfi.fee_type = :original_type
                ");
                $stmt_up_meta->execute([
                    ':new_name' => $new_name,
                    ':new_type' => $new_type,
                    ':apply_to' => $apply_to,
                    ':linked_to' => $linked_to,
                    ':school_id' => $school_id,
                    ':original_name' => $original_name,
                    ':original_type' => $original_type
                ]);
            } else {
                // If it's a mockup row or a new structure not yet saved, seed it into the database for all active students!
                $stmt_stud = $pdo->prepare("SELECT id, class_id FROM students WHERE school_id = :school_id AND deleted_at IS NULL");
                $stmt_stud->execute([':school_id' => $school_id]);
                $students = $stmt_stud->fetchAll();
                
                $stmt_ins = $pdo->prepare("
                    INSERT INTO student_fee_items (student_id, fee_name, fee_type, apply_to, linked_to, amount, is_active)
                    VALUES (:student_id, :fee_name, :fee_type, :apply_to, :linked_to, :amount, :is_active)
                ");
                
                foreach ($students as $student) {
                    $cid = $student['class_id'];
                    $is_active = isset($classes_input[$cid]['active']) ? 1 : 0;
                    $amount = $is_active ? (float)($classes_input[$cid]['amount'] ?? 0) : 0.00;
                    
                    $stmt_ins->execute([
                        ':student_id' => $student['id'],
                        ':fee_name' => $new_name,
                        ':fee_type' => $new_type,
                        ':apply_to' => $apply_to,
                        ':linked_to' => $linked_to,
                        ':amount' => $amount,
                        ':is_active' => $is_active
                    ]);
                }
            }
            
            // Process updates for each class
            if ($count > 0) {
                foreach ($classes_input as $class_id => $data) {
                    $is_active = isset($data['active']) ? 1 : 0;
                    $amount = (float)($data['amount'] ?? 0);
                    $sync_students = isset($data['sync_students']) ? 1 : 0;
                    
                    if ($is_active) {
                        // Check if there are students in this class who don't have this item yet, and insert it
                        $stmt_unmatched = $pdo->prepare("
                            SELECT s.id 
                            FROM students s
                            WHERE s.school_id = :school_id AND s.class_id = :class_id AND s.deleted_at IS NULL
                              AND s.id NOT IN (
                                  SELECT student_id FROM student_fee_items WHERE fee_name = :fee_name AND fee_type = :fee_type
                              )
                        ");
                        $stmt_unmatched->execute([
                            ':school_id' => $school_id,
                            ':class_id' => $class_id,
                            ':fee_name' => $new_name,
                            ':fee_type' => $new_type
                        ]);
                        $unmatched_student_ids = $stmt_unmatched->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($unmatched_student_ids)) {
                            $stmt_ins_single = $pdo->prepare("
                                INSERT INTO student_fee_items (student_id, fee_name, fee_type, apply_to, linked_to, amount, is_active)
                                VALUES (:student_id, :fee_name, :fee_type, :apply_to, :linked_to, :amount, 1)
                            ");
                            foreach ($unmatched_student_ids as $sid) {
                                $stmt_ins_single->execute([
                                    ':student_id' => $sid,
                                    ':fee_name' => $new_name,
                                    ':fee_type' => $new_type,
                                    ':apply_to' => $apply_to,
                                    ':linked_to' => $linked_to,
                                    ':amount' => $amount
                                ]);
                            }
                        }
                        
                        // Sync amounts for existing items if requested
                        if ($sync_students) {
                            $stmt_up_amount = $pdo->prepare("
                                UPDATE student_fee_items sfi
                                JOIN students s ON sfi.student_id = s.id
                                SET sfi.amount = :amount,
                                    sfi.is_active = 1
                                WHERE s.school_id = :school_id
                                  AND s.class_id = :class_id
                                  AND sfi.fee_name = :fee_name
                                  AND sfi.fee_type = :fee_type
                            ");
                            $stmt_up_amount->execute([
                                ':amount' => $amount,
                                ':school_id' => $school_id,
                                ':class_id' => $class_id,
                                ':fee_name' => $new_name,
                                ':fee_type' => $new_type
                            ]);
                        } else {
                            // Just mark active
                            $stmt_up_active = $pdo->prepare("
                                UPDATE student_fee_items sfi
                                JOIN students s ON sfi.student_id = s.id
                                SET sfi.is_active = 1
                                WHERE s.school_id = :school_id
                                  AND s.class_id = :class_id
                                  AND sfi.fee_name = :fee_name
                                  AND sfi.fee_type = :fee_type
                            ");
                            $stmt_up_active->execute([
                                ':school_id' => $school_id,
                                ':class_id' => $class_id,
                                ':fee_name' => $new_name,
                                ':fee_type' => $new_type
                            ]);
                        }
                    } else {
                        // Deactivate for this class
                        $stmt_disable = $pdo->prepare("
                            UPDATE student_fee_items sfi
                            JOIN students s ON sfi.student_id = s.id
                            SET sfi.is_active = 0
                            WHERE s.school_id = :school_id
                              AND s.class_id = :class_id
                              AND sfi.fee_name = :fee_name
                              AND sfi.fee_type = :fee_type
                        ");
                        $stmt_disable->execute([
                            ':school_id' => $school_id,
                            ':class_id' => $class_id,
                            ':fee_name' => $new_name,
                            ':fee_type' => $new_type
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Fees structure updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to update fees structure: " . $e->getMessage();
        }
    }
    
    header("Location: fees-structure.php?session=" . urlencode($current_session));
    exit;
}

// 4. Fetch overall summary banner stats
$stmt_sum = $pdo->prepare("
    SELECT SUM(CASE WHEN sfi.is_active = 1 THEN sfi.amount ELSE 0 END) as total_school_fees,
           SUM(CASE WHEN sfi.is_active = 1 THEN sfi.discount_amount ELSE 0 END) as total_school_discount
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :school_id AND s.deleted_at IS NULL
");
$stmt_sum->execute([':school_id' => $school_id]);
$summary = $stmt_sum->fetch();
$total_school_fees = (float)($summary['total_school_fees'] ?? 0);
$total_school_discount = (float)($summary['total_school_discount'] ?? 0);
$gross_total_fees = max(0.0, $total_school_fees - $total_school_discount);

// 5. Prepare structures array
$structures = [];

// 6. Fetch distinct fee structures from DB
$stmt_str = $pdo->prepare("
    SELECT sfi.fee_name, sfi.fee_type, sfi.apply_to, sfi.linked_to,
           SUM(CASE WHEN sfi.is_active = 1 THEN sfi.amount ELSE 0 END) as total_students_fees,
           SUM(CASE WHEN sfi.is_active = 1 THEN sfi.paid_amount ELSE 0 END) as received_fees,
           COUNT(DISTINCT CASE WHEN sfi.is_active = 1 THEN sfi.student_id END) as linked_count,
           COUNT(DISTINCT CASE WHEN sfi.is_active = 0 THEN sfi.student_id END) as inactive_count,
           MIN(sfi.created_at) as created_at,
           MAX(sfi.updated_at) as updated_at
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :school_id AND s.deleted_at IS NULL
    GROUP BY sfi.fee_name, sfi.fee_type, sfi.apply_to, sfi.linked_to
    ORDER BY created_at DESC
");
$stmt_str->execute([':school_id' => $school_id]);
$db_structures = $stmt_str->fetchAll();

// 7. Fetch all class average fees to build the badge configurations
$stmt_cf = $pdo->prepare("
    SELECT sfi.fee_name, sfi.fee_type, c.name as class_name, AVG(sfi.amount) as amount
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE s.school_id = :school_id AND s.deleted_at IS NULL AND sfi.is_active = 1
    GROUP BY sfi.fee_name, sfi.fee_type, c.id, c.name
    ORDER BY c.id ASC
");
$stmt_cf->execute([':school_id' => $school_id]);
$class_fees_raw = $stmt_cf->fetchAll();

$class_fees = [];
foreach ($class_fees_raw as $row) {
    $class_fees[$row['fee_name']][$row['fee_type']][] = [
        'class'  => $row['class_name'],
        'amount' => (float)$row['amount']
    ];
}

foreach ($db_structures as $db_row) {
    $exists = false;
    foreach ($structures as $s) {
        if (strtolower($s['fee_name']) === strtolower($db_row['fee_name']) && strtolower($s['fee_type']) === strtolower($db_row['fee_type'])) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $name = $db_row['fee_name'];
        $type = $db_row['fee_type'];
        $classes = $class_fees[$name][$type] ?? [];

        $structures[] = [
            'id' => null,
            'fee_name' => $name,
            'fee_type' => $type,
            'apply_to' => $db_row['apply_to'] ?: 'all',
            'linked_to' => $db_row['linked_to'] ?: '',
            'total_students_fees' => (float)$db_row['total_students_fees'],
            'received_fees' => (float)$db_row['received_fees'],
            'linked_count' => (int)$db_row['linked_count'],
            'inactive_count' => (int)$db_row['inactive_count'],
            'sub_day_date' => 10,
            'fine_type' => 'monthly',
            'fine_amount' => 100,
            'updated_at' => $db_row['updated_at'],
            'created_at' => $db_row['created_at'],
            'classes' => $classes
        ];
    }
}

// 8. Load flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Build class name lookup
$class_name_to_id = [];
foreach ($all_classes as $class) {
    $class_name_to_id[strtolower(trim($class['name']))] = (int)$class['id'];
}

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading & Flash Messages ─────────────────────────────────────────── -->
<div class="row align-items-center mb-3 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Fees Structure</h2>
    </div>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible fade show font-secondary text-xs mb-3" role="alert" style="border-radius: 8px;">
        <i class="ph-light ph-check-circle me-1" style="font-size: 14px; vertical-align: middle;"></i> <?php echo htmlspecialchars($flash_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible fade show font-secondary text-xs mb-3" role="alert" style="border-radius: 8px;">
        <i class="ph-light ph-warning-circle me-1" style="font-size: 14px; vertical-align: middle;"></i> <?php echo htmlspecialchars($flash_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- ── Actions & Filters Toolbar Card ─────────────────────────────────────────── -->
<div class="row mb-4">
    <div class="col-12">
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius); padding:1.25rem; box-shadow: var(--shadow-sm);">
            
            <!-- Row 1: Left Action Buttons & Right Session Dropdown -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <button type="button" class="teacher-header-btn btn-accent" data-bs-toggle="modal" data-bs-target="#addFeeStructureModal" title="Add Fee Structure">
                        <i class="ph-light ph-plus"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-sky" title="Backup / Restore">
                        <i class="ph-light ph-cloud-arrow-down"></i>
                    </button>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <span class="text-sm font-heading fw-bold" style="color:var(--color-text-primary);">Sessions</span>
                    <form method="GET" action="fees-structure.php" class="m-0">
                        <select name="session" class="form-control-admin font-secondary text-secondary" style="width:140px; height:38px; border-radius:6px;" onchange="this.form.submit()">
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $current_session === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
            
            <!-- Row 2: Stats Badges Group & Right-aligned Deleted Link -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge font-secondary px-3 py-2" style="background-color: var(--info-light); color: var(--info); border: 1px solid rgba(14, 165, 233, 0.25); font-size:12px; font-weight:600; border-radius: 6px;">
                        Total Fees: (<?php echo number_format($total_school_fees, 0, '', ''); ?>)
                    </span>
                    <span class="badge font-secondary px-3 py-2" style="background-color: var(--brand-light); color: var(--brand); border: 1px solid rgba(99, 102, 241, 0.25); font-size:12px; font-weight:600; border-radius: 6px;">
                        Head Discount: (<?php echo number_format($total_school_discount, 0, '', ''); ?>)
                    </span>
                    <span class="badge font-secondary px-3 py-2" style="background-color: var(--success-light); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.25); font-size:12px; font-weight:600; border-radius: 6px;">
                        Gross Total Fees: (<?php echo number_format($gross_total_fees, 0, '', ''); ?>)
                    </span>
                </div>
                <div>
                    <a href="#" class="text-sm font-heading fw-semibold text-decoration-none" style="color: var(--color-danger); cursor: pointer;">Deleted</a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- ── Fees Structure Table ──────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            
            <!-- Table Sub-toolbar Controls -->
            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border); border-radius:0; box-shadow:none; background:var(--gray-50); margin-bottom: 0;">
                <div class="fee-toolbar-left d-flex align-items-center gap-1">
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">Show</span>
                    <select class="form-control-admin font-secondary text-secondary" style="width:64px; display:inline-block; height:34px; border-radius:4px; padding: 2px 8px;">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">entries</span>
                </div>
                <div class="fee-toolbar-right">
                    <div class="d-flex align-items-center gap-1">
                        <label class="text-xs font-secondary mb-0" style="color:var(--color-text-primary);">Search:</label>
                        <input type="text" class="form-control-admin font-secondary" id="tableSearch" style="width:160px; height:32px; border-radius:4px; padding:2px 8px;">
                    </div>
                </div>
            </div>
            
            <!-- Table Container -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="teacher-table table-premium mb-0 align-middle" id="structureTable">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="min-width: 200px;">Fees Type</th>
                                <th style="min-width: 240px;">Classes Wise Fees</th>
                                <th style="min-width: 220px;">Fee Details</th>
                                <th style="min-width: 130px;">Fine</th>
                                <th style="min-width: 140px;">Last Updated</th>
                                <th style="min-width: 140px;">Created At</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($structures)): ?>
                                <tr>
                                    <td colspan="8" class="text-center p-5">
                                        <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                            <i class="ph-light ph-tree-structure"></i>
                                        </div>
                                        <h5 class="fw-bold mt-3 mb-1 font-heading">No Fee Structures Setup</h5>
                                        <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Create your first fee category using the plus button above.</p>
                                    </td>
                                </tr>
                            <?php else: 
                                $idx = 1;
                                $total_count = count($structures);
                                foreach ($structures as $row_index => $row):
                                    $name = $row['fee_name'];
                                    $type = $row['fee_type'];
                                    $row_num = $row['id'] ?? ($total_count - $row_index);
                                    
                                    // Standardize classes wise details for JS parameter parsing
                                    $mapped_classes = [];
                                    foreach ($row['classes'] as $cf) {
                                        $cname = strtolower(trim($cf['class']));
                                        if (isset($class_name_to_id[$cname])) {
                                            $cid = $class_name_to_id[$cname];
                                            $mapped_classes[$cid] = (float)$cf['amount'];
                                        }
                                    }
                                    $classes_json = json_encode($mapped_classes);
                                ?>
                                    <tr>
                                        <td class="font-secondary"><?php echo $row_num; ?></td>
                                        <td>
                                            <a href="#" class="fw-bold font-heading text-decoration-none text-primary" style="font-size:14px;"><?php echo sanitize($name); ?></a>
                                            <div class="text-xxs font-secondary mt-1 text-muted" style="line-height: 1.5;">
                                                <div>Session: <?php echo htmlspecialchars($current_session); ?></div>
                                                <div>Duration: <?php echo htmlspecialchars($type); ?></div>
                                                <div>Apply To: <?php echo htmlspecialchars($row['apply_to']); ?></div>
                                                <div>Link To: <?php echo htmlspecialchars($row['linked_to']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php if (empty($row['classes'])): ?>
                                                    <span class="text-xs text-muted font-secondary">—</span>
                                                <?php else: ?>
                                                    <?php foreach ($row['classes'] as $cf): ?>
                                                        <span class="class-pill class-pill-primary font-secondary">
                                                            <?php echo sanitize($cf['class']); ?>: <?php echo number_format($cf['amount'], 0, '', ''); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-xs font-secondary text-secondary" style="line-height: 1.6;">
                                                <div>Total Student's Fees: <span class="fw-bold text-dark"><?php echo number_format($row['total_students_fees'], 0, '', ''); ?></span></div>
                                                <div class="d-flex align-items-center gap-1 my-0.5">
                                                    <span>Received Fees:</span>
                                                    <span class="badge text-white px-2 py-1 rounded" style="font-size:10px; font-weight:600; background-color: var(--success) !important;"><?php echo number_format($row['received_fees'], 0, '', ''); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-1 my-0.5">
                                                    <span>Linked to:</span>
                                                    <span class="badge text-white px-2 py-1 rounded" style="font-size:10px; font-weight:600; background-color: var(--brand) !important;"><?php echo $row['linked_count']; ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <span>Inactive:</span>
                                                    <span class="badge text-white px-2 py-1 rounded" style="font-size:10px; font-weight:600; background-color: var(--brand) !important;"><?php echo $row['inactive_count']; ?></span>
                                                </div>
                                                <div class="mt-1">
                                                    Sub. Day/Date: <span class="fw-semibold text-dark"><?php echo $row['sub_day_date']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-xs font-secondary text-secondary" style="line-height: 1.5;">
                                                <div>Type: <?php echo htmlspecialchars($row['fine_type']); ?></div>
                                                <div>Amount: <?php echo number_format($row['fine_amount'], 0, '', ''); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-xs font-secondary" style="color: var(--color-text-secondary); font-weight:600;">
                                                <?php echo date('d M, Y', strtotime($row['updated_at'])); ?><br>
                                                <small class="text-muted font-normal" style="font-size:11px;"><?php echo date('h:i:sa', strtotime($row['updated_at'])); ?></small>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-xs font-secondary" style="color: var(--color-text-secondary); font-weight:600;">
                                                <?php echo date('d M, Y', strtotime($row['created_at'])); ?><br>
                                                <small class="text-muted font-normal" style="font-size:11px;"><?php echo date('h:i:sa', strtotime($row['created_at'])); ?></small>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: grid; grid-template-columns: repeat(3, 28px); gap: 4px; justify-content: center; align-items: center; min-width: 92px;">
                                                <!-- Edit button -->
                                                <button class="teacher-action-btn action-edit edit-structure-btn" 
                                                        data-fee-name="<?php echo htmlspecialchars($name); ?>" 
                                                        data-fee-type="<?php echo htmlspecialchars($type); ?>" 
                                                        data-apply-to="<?php echo htmlspecialchars($row['apply_to']); ?>" 
                                                        data-linked-to="<?php echo htmlspecialchars($row['linked_to']); ?>" 
                                                        data-sub-day-date="<?php echo htmlspecialchars($row['sub_day_date']); ?>" 
                                                        data-fine-type="<?php echo htmlspecialchars($row['fine_type']); ?>" 
                                                        data-fine-amount="<?php echo htmlspecialchars($row['fine_amount']); ?>" 
                                                        data-classes="<?php echo htmlspecialchars($classes_json); ?>" 
                                                        title="Edit">
                                                    <i class="ph-light ph-pencil-simple"></i>
                                                </button>
                                                <!-- Copy button -->
                                                <button class="teacher-action-btn copy-structure-btn" 
                                                        data-fee-name="<?php echo htmlspecialchars($name); ?>" 
                                                        data-fee-type="<?php echo htmlspecialchars($type); ?>" 
                                                        data-apply-to="<?php echo htmlspecialchars($row['apply_to']); ?>" 
                                                        data-linked-to="<?php echo htmlspecialchars($row['linked_to']); ?>" 
                                                        data-sub-day-date="<?php echo htmlspecialchars($row['sub_day_date']); ?>" 
                                                        data-fine-type="<?php echo htmlspecialchars($row['fine_type']); ?>" 
                                                        data-fine-amount="<?php echo htmlspecialchars($row['fine_amount']); ?>" 
                                                        data-classes="<?php echo htmlspecialchars($classes_json); ?>" 
                                                        title="Copy">
                                                    <i class="ph-light ph-copy"></i>
                                                </button>
                                                <!-- Delete button -->
                                                <button class="teacher-action-btn action-delete delete-structure-btn" 
                                                        data-fee-name="<?php echo htmlspecialchars($name); ?>" 
                                                        data-fee-type="<?php echo htmlspecialchars($type); ?>" 
                                                        title="Delete">
                                                    <i class="ph-light ph-trash"></i>
                                                </button>
                                                <!-- History button -->
                                                <button class="teacher-action-btn action-view" style="grid-column: 1;" title="History">
                                                    <i class="ph-light ph-clock-counter-clockwise"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- ── Edit Fees Structure Modal Popup ────────────────────────────────────────── -->
<div class="modal fade" id="editFeeStructureModal" tabindex="-1" aria-labelledby="editFeeStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: var(--border-radius-lg); background: var(--color-surface);">
            <div class="modal-header border-0 bg-light py-3 px-4 d-flex justify-content-between align-items-center" style="border-top-left-radius: var(--border-radius-lg); border-top-right-radius: var(--border-radius-lg);">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="modal-title font-heading fw-bold mb-0" id="editFeeStructureModalLabel" style="color: var(--color-text-primary); font-size: 18px;">Edit Fees Structure</h5>
                    <button type="button" class="btn text-white px-3 py-1 font-secondary" style="background-color: var(--color-accent); font-size: 11px; border-radius: 20px; border: none; font-weight: 600;" disabled>Need help? Call Us</button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="fees-structure.php" method="POST">
                <input type="hidden" name="action" value="update_structure">
                <input type="hidden" name="original_fee_name" id="modal_original_fee_name">
                <input type="hidden" name="original_fee_type" id="modal_original_fee_type">
                
                <div class="modal-body p-4 text-dark text-start">
                    <!-- Row 1: Title, Apply To, RTE -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Fees Title *</label>
                            <input type="text" name="fee_name" id="modal_fee_name" class="form-control-admin" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Apply this fees to students type *</label>
                            <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="apply_to" id="apply_all" value="all">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="apply_all">All</label>
                                </div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="apply_to" id="apply_new" value="new">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="apply_new">New</label>
                                </div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="apply_to" id="apply_old" value="old">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="apply_old">Old</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Do you want to apply fees on RTE students? *</label>
                            <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="rte_status" id="rte_yes" value="yes">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="rte_yes">Yes</label>
                                </div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="rte_status" id="rte_no" value="no">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="rte_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Row 2: Duration, Link month, Receiving day -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fees Duration *</label>
                            <span class="text-xxs text-muted d-block mb-1">(Select duration how do you want the fees to be taken.)</span>
                            <select name="fee_type" id="modal_fee_type" class="form-control-admin" required>
                                <option value="Monthly">Monthly</option>
                                <option value="Yearly">Yearly</option>
                                <option value="Due">Due</option>
                                <option value="Separate">Separate</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Link a month</label>
                            <span class="text-xxs text-muted d-block mb-1">(You can link yearly, due fee to a month)</span>
                            <select name="linked_to" id="modal_linked_to" class="form-control-admin">
                                <option value="">Select Month</option>
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
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fees receiving day</label>
                            <span class="text-xxs text-muted d-block mb-1">Select the day of month to receive the fees</span>
                            <input type="number" name="receiving_day" id="modal_receiving_day" class="form-control-admin" min="1" max="31" value="5">
                        </div>
                    </div>
                    
                    <!-- Row 3: Auto add fine, Fine amount -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label-admin mb-0">Auto add fine *</label>
                            <span class="text-xxs text-muted d-block mb-1">(Select the fine type you want to apply)</span>
                            <select name="fine_type" id="modal_fine_type" class="form-control-admin">
                                <option value="Monthly">Monthly</option>
                                <option value="Daily">Daily</option>
                                <option value="One-time">One-time</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin mb-0">Fine amount *</label>
                            <span class="text-xxs text-muted d-block mb-1">(Enter the amount you want to be applied)</span>
                            <input type="number" name="fine_amount" id="modal_fine_amount" class="form-control-admin" min="0" value="50">
                        </div>
                    </div>
                    
                    <!-- Row 4: Class Grid -->
                    <div class="row g-3">
                        <!-- Column 1: Class Selection -->
                        <div class="col-md-4">
                            <h6 class="font-heading fw-bold text-dark mb-2" style="font-size: 14px;">Class</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="modal_select_all_classes">
                                <label class="form-check-label text-xs fw-bold font-secondary" for="modal_select_all_classes">Select all classes</label>
                            </div>
                            <div class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); padding: 10px; border-radius: 8px; background: var(--color-bg);">
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="form-check">
                                        <input class="form-check-input class-checkbox" type="checkbox" name="classes[<?php echo $class['id']; ?>][active]" id="class_active_<?php echo $class['id']; ?>" value="1" data-class-id="<?php echo $class['id']; ?>">
                                        <label class="form-check-label text-xs fw-semibold font-secondary" for="class_active_<?php echo $class['id']; ?>">
                                            <?php echo sanitize($class['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Column 2: Fees Amount Inputs -->
                        <div class="col-md-4">
                            <h6 class="font-heading fw-bold text-dark mb-2" style="font-size: 14px;">Fees</h6>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="modal_copy_fees_all">
                                <label class="form-check-label text-xs fw-bold font-secondary" for="modal_copy_fees_all">Copy same fees to all</label>
                            </div>
                            <span class="text-xxs text-muted d-block mb-2">(First field value will be copied to others.)</span>
                            <div class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); padding: 10px; border-radius: 8px; background: var(--color-bg);">
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="d-flex align-items-center" style="height: 22px; margin-bottom: 2px;">
                                        <input type="number" name="classes[<?php echo $class['id']; ?>][amount]" id="class_amount_<?php echo $class['id']; ?>" class="form-control-admin py-1 px-2 text-xs font-secondary class-fee-input" style="height:28px;" placeholder="0" data-class-id="<?php echo $class['id']; ?>" disabled>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Column 3: Update Student's Checkbox -->
                        <div class="col-md-4">
                            <h6 class="font-heading fw-bold text-dark mb-2" style="font-size: 14px;">Update student's fees structure</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="modal_select_all_update_students">
                                <label class="form-check-label text-xs fw-bold font-secondary" for="modal_select_all_update_students">Select all classes</label>
                            </div>
                            <div class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); padding: 10px; border-radius: 8px; background: var(--color-bg);">
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="form-check">
                                        <input class="form-check-input sync-checkbox" type="checkbox" name="classes[<?php echo $class['id']; ?>][sync_students]" id="sync_students_<?php echo $class['id']; ?>" value="1" data-class-id="<?php echo $class['id']; ?>">
                                        <label class="form-check-label text-xs fw-semibold font-secondary" for="sync_students_<?php echo $class['id']; ?>">
                                            <?php echo sanitize($class['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 bg-light d-flex justify-content-between px-4 py-3" style="border-bottom-left-radius: var(--border-radius-lg); border-bottom-right-radius: var(--border-radius-lg);">
                    <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color: #a0aec0; color: #fff; border-radius: 6px; font-size: 13px; border: none;">Close</button>
                    <button type="submit" class="btn font-secondary fw-semibold px-4 py-2" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none;">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Fees Structure Modal Popup ────────────────────────────────────────── -->
<div class="modal fade" id="addFeeStructureModal" tabindex="-1" aria-labelledby="addFeeStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: var(--border-radius-lg); background: var(--color-surface);">
            <div class="modal-header border-0 bg-light py-3 px-4 d-flex justify-content-between align-items-center" style="border-top-left-radius: var(--border-radius-lg); border-top-right-radius: var(--border-radius-lg);">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="modal-title font-heading fw-bold mb-0" id="addFeeStructureModalLabel" style="color: var(--color-text-primary); font-size: 18px;">Add Fees Structure</h5>
                    <button type="button" class="btn text-white px-3 py-1 font-secondary" style="background-color: var(--color-accent); font-size: 11px; border-radius: 20px; border: none; font-weight: 600;" disabled>Need help? Call Us</button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="fees-structure.php" method="POST">
                <input type="hidden" name="action" value="add_structure">
                
                <div class="modal-body p-4 text-dark text-start">
                    <!-- Row 1: Title, Apply To, RTE -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Fees Title *</label>
                            <input type="text" name="fee_name" id="add_fee_name" class="form-control-admin" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Apply this fees to students type *</label>
                            <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="apply_to" id="add_apply_all" value="all" checked>
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="add_apply_all">All</label>
                                </div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="apply_to" id="add_apply_new" value="new">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="add_apply_new">New</label>
                                </div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="apply_to" id="add_apply_old" value="old">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="add_apply_old">Old</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Do you want to apply fees on RTE students? *</label>
                            <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="rte_status" id="add_rte_yes" value="yes">
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="add_rte_yes">Yes</label>
                                </div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="rte_status" id="add_rte_no" value="no" checked>
                                    <label class="form-check-label text-xs fw-semibold font-secondary" for="add_rte_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Row 2: Duration, Link month, Receiving day -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fees Duration *</label>
                            <span class="text-xxs text-muted d-block mb-1">(Select duration how do you want the fees to be taken.)</span>
                            <select name="fee_type" id="add_fee_type" class="form-control-admin" required>
                                <option value="Monthly">Monthly</option>
                                <option value="Yearly">Yearly</option>
                                <option value="Due">Due</option>
                                <option value="Separate">Separate</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Link a month</label>
                            <span class="text-xxs text-muted d-block mb-1">(You can link yearly, due fee to a month)</span>
                            <select name="linked_to" id="add_linked_to" class="form-control-admin">
                                <option value="">Select Month</option>
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
                        <div class="col-md-4">
                            <label class="form-label-admin mb-0">Fees receiving day</label>
                            <span class="text-xxs text-muted d-block mb-1">Select the day of month to receive the fees</span>
                            <input type="number" name="receiving_day" id="add_receiving_day" class="form-control-admin" min="1" max="31" value="5">
                        </div>
                    </div>
                    
                    <!-- Row 3: Auto add fine, Fine amount -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label-admin mb-0">Auto add fine *</label>
                            <span class="text-xxs text-muted d-block mb-1">(Select the fine type you want to apply)</span>
                            <select name="fine_type" id="add_fine_type" class="form-control-admin">
                                <option value="Monthly">Monthly</option>
                                <option value="Daily">Daily</option>
                                <option value="One-time">One-time</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin mb-0">Fine amount *</label>
                            <span class="text-xxs text-muted d-block mb-1">(Enter the amount you want to be applied)</span>
                            <input type="number" name="fine_amount" id="add_fine_amount" class="form-control-admin" min="0" value="50">
                        </div>
                    </div>
                    
                    <!-- Row 4: Class Grid -->
                    <div class="row g-3">
                        <!-- Column 1: Class Selection -->
                        <div class="col-md-4">
                            <h6 class="font-heading fw-bold text-dark mb-2" style="font-size: 14px;">Class</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="add_select_all_classes">
                                <label class="form-check-label text-xs fw-bold font-secondary" for="add_select_all_classes">Select all classes</label>
                            </div>
                            <div class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); padding: 10px; border-radius: 8px; background: var(--color-bg);">
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="form-check">
                                        <input class="form-check-input add-class-checkbox" type="checkbox" name="classes[<?php echo $class['id']; ?>][active]" id="add_class_active_<?php echo $class['id']; ?>" value="1" data-class-id="<?php echo $class['id']; ?>">
                                        <label class="form-check-label text-xs fw-semibold font-secondary" for="add_class_active_<?php echo $class['id']; ?>">
                                            <?php echo sanitize($class['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Column 2: Fees Amount Inputs -->
                        <div class="col-md-4">
                            <h6 class="font-heading fw-bold text-dark mb-2" style="font-size: 14px;">Fees</h6>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="add_copy_fees_all">
                                <label class="form-check-label text-xs fw-bold font-secondary" for="add_copy_fees_all">Copy same fees to all</label>
                            </div>
                            <span class="text-xxs text-muted d-block mb-2">(First field value will be copied to others.)</span>
                            <div class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); padding: 10px; border-radius: 8px; background: var(--color-bg);">
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="d-flex align-items-center" style="height: 22px; margin-bottom: 2px;">
                                        <input type="number" name="classes[<?php echo $class['id']; ?>][amount]" id="add_class_amount_<?php echo $class['id']; ?>" class="form-control-admin py-1 px-2 text-xs font-secondary add-class-fee-input" style="height:28px;" placeholder="0" data-class-id="<?php echo $class['id']; ?>" disabled>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Column 3: Update Student's Checkbox -->
                        <div class="col-md-4">
                            <h6 class="font-heading fw-bold text-dark mb-2" style="font-size: 14px;">Update student's fees structure</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="add_select_all_update_students">
                                <label class="form-check-label text-xs fw-bold font-secondary" for="add_select_all_update_students">Select all classes</label>
                            </div>
                            <div class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); padding: 10px; border-radius: 8px; background: var(--color-bg);">
                                <?php foreach ($all_classes as $class): ?>
                                    <div class="form-check">
                                        <input class="form-check-input add-sync-checkbox" type="checkbox" name="classes[<?php echo $class['id']; ?>][sync_students]" id="add_sync_students_<?php echo $class['id']; ?>" value="1" data-class-id="<?php echo $class['id']; ?>">
                                        <label class="form-check-label text-xs fw-semibold font-secondary" for="add_sync_students_<?php echo $class['id']; ?>">
                                            <?php echo sanitize($class['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 bg-light d-flex justify-content-between px-4 py-3" style="border-bottom-left-radius: var(--border-radius-lg); border-bottom-right-radius: var(--border-radius-lg);">
                    <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color: #a0aec0; color: #fff; border-radius: 6px; font-size: 13px; border: none;">Close</button>
                    <button type="submit" class="btn font-secondary fw-semibold px-4 py-2" style="background-color: var(--color-accent); color: #fff; border-radius: 6px; font-size: 13px; border: none;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteStructureForm" action="fees-structure.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_structure">
    <input type="hidden" name="fee_name" id="delete_fee_name">
    <input type="hidden" name="fee_type" id="delete_fee_type">
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Simple frontend search functionality
    const searchInput = document.getElementById('tableSearch');
    const tableRows = document.querySelectorAll('#structureTable tbody tr');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            tableRows.forEach(row => {
                if (row.querySelector('td[colspan]')) return; // skip empty row
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Modal population handler
    const editButtons = document.querySelectorAll('.edit-structure-btn');
    const modalEl = document.getElementById('editFeeStructureModal');
    const modalInstance = new bootstrap.Modal(modalEl);
    
    // Form fields in modal
    const originalFeeName = document.getElementById('modal_original_fee_name');
    const originalFeeType = document.getElementById('modal_original_fee_type');
    const feeNameInput = document.getElementById('modal_fee_name');
    const feeTypeSelect = document.getElementById('modal_fee_type');
    const linkedToSelect = document.getElementById('modal_linked_to');
    const receivingDayInput = document.getElementById('modal_receiving_day');
    const fineTypeSelect = document.getElementById('modal_fine_type');
    const fineAmountInput = document.getElementById('modal_fine_amount');
    
    editButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const name = this.getAttribute('data-fee-name');
            const type = this.getAttribute('data-fee-type');
            const applyTo = this.getAttribute('data-apply-to');
            const linkedTo = this.getAttribute('data-linked-to');
            const subDayDate = this.getAttribute('data-sub-day-date');
            const fineType = this.getAttribute('data-fine-type');
            const fineAmount = this.getAttribute('data-fine-amount');
            const classesData = JSON.parse(this.getAttribute('data-classes') || '{}');
            
            // Set values
            originalFeeName.value = name;
            originalFeeType.value = type;
            feeNameInput.value = name;
            
            selectOptionByValue(feeTypeSelect, type);
            selectOptionByValue(linkedToSelect, linkedTo);
            selectOptionByValue(fineTypeSelect, fineType);
            
            receivingDayInput.value = subDayDate;
            fineAmountInput.value = fineAmount;
            
            // Radio apply_to
            const applyRadio = document.querySelector(`input[name="apply_to"][value="${applyTo.toLowerCase()}"]`);
            if (applyRadio) applyRadio.checked = true;
            
            // Radio rte_status (default No)
            const rteRadio = document.querySelector(`input[name="rte_status"][value="no"]`);
            if (rteRadio) rteRadio.checked = true;
            
            // Reset modal class list state
            document.querySelectorAll('.class-checkbox').forEach(cb => {
                cb.checked = false;
                const cid = cb.getAttribute('data-class-id');
                const amtInput = document.getElementById(`class_amount_${cid}`);
                if (amtInput) {
                    amtInput.value = '';
                    amtInput.disabled = true;
                }
            });
            document.querySelectorAll('.sync-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('modal_select_all_classes').checked = false;
            document.getElementById('modal_select_all_update_students').checked = false;
            document.getElementById('modal_copy_fees_all').checked = false;
            
            // Populate checkboxes
            Object.keys(classesData).forEach(cid => {
                const cb = document.getElementById(`class_active_${cid}`);
                const amtInput = document.getElementById(`class_amount_${cid}`);
                if (cb) {
                    cb.checked = true;
                    if (amtInput) {
                        amtInput.disabled = false;
                        amtInput.value = classesData[cid];
                    }
                }
            });
            
            modalInstance.show();
        });
    });
    
    function selectOptionByValue(selectEl, value) {
        if (!selectEl) return;
        const normalizedVal = (value || '').trim().toLowerCase();
        for (let i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].value.toLowerCase() === normalizedVal) {
                selectEl.selectedIndex = i;
                return;
            }
        }
        selectEl.selectedIndex = 0;
    }
    
    // Class checkbox event triggers toggle of input
    document.querySelectorAll('.class-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const cid = this.getAttribute('data-class-id');
            const amtInput = document.getElementById(`class_amount_${cid}`);
            if (amtInput) {
                amtInput.disabled = !this.checked;
                if (!this.checked) {
                    amtInput.value = '';
                } else {
                    const firstActive = Array.from(document.querySelectorAll('.class-fee-input')).find(input => !input.disabled && input.value !== '');
                    amtInput.value = firstActive ? firstActive.value : '0';
                }
            }
        });
    });
    
    // Select all classes behavior
    const selectAllClasses = document.getElementById('modal_select_all_classes');
    if (selectAllClasses) {
        selectAllClasses.addEventListener('change', function() {
            document.querySelectorAll('.class-checkbox').forEach(cb => {
                cb.checked = this.checked;
                const cid = cb.getAttribute('data-class-id');
                const amtInput = document.getElementById(`class_amount_${cid}`);
                if (amtInput) {
                    amtInput.disabled = !this.checked;
                    if (this.checked && amtInput.value === '') {
                        amtInput.value = '0';
                    }
                }
            });
        });
    }
    
    // Select all sync classes behavior
    const selectAllSync = document.getElementById('modal_select_all_update_students');
    if (selectAllSync) {
        selectAllSync.addEventListener('change', function() {
            document.querySelectorAll('.sync-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
    // Copy fee amount to all checked classes
    const copyFees = document.getElementById('modal_copy_fees_all');
    if (copyFees) {
        copyFees.addEventListener('change', function() {
            if (this.checked) {
                const activeInputs = Array.from(document.querySelectorAll('.class-fee-input')).filter(input => !input.disabled);
                if (activeInputs.length > 0) {
                    const val = activeInputs[0].value || '0';
                    activeInputs.forEach(input => {
                        input.value = val;
                    });
                }
            }
        });
    }
    
    // Add Modal script logic
    // Add Class checkbox event triggers toggle of input
    document.querySelectorAll('.add-class-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const cid = this.getAttribute('data-class-id');
            const amtInput = document.getElementById(`add_class_amount_${cid}`);
            if (amtInput) {
                amtInput.disabled = !this.checked;
                if (!this.checked) {
                    amtInput.value = '';
                } else {
                    const firstActive = Array.from(document.querySelectorAll('.add-class-fee-input')).find(input => !input.disabled && input.value !== '');
                    amtInput.value = firstActive ? firstActive.value : '0';
                }
            }
        });
    });
    
    // Select all classes behavior for Add Modal
    const selectAllAddClasses = document.getElementById('add_select_all_classes');
    if (selectAllAddClasses) {
        selectAllAddClasses.addEventListener('change', function() {
            document.querySelectorAll('.add-class-checkbox').forEach(cb => {
                cb.checked = this.checked;
                const cid = cb.getAttribute('data-class-id');
                const amtInput = document.getElementById(`add_class_amount_${cid}`);
                if (amtInput) {
                    amtInput.disabled = !this.checked;
                    if (this.checked && amtInput.value === '') {
                        amtInput.value = '0';
                    }
                }
            });
        });
    }
    
    // Select all sync classes behavior for Add Modal
    const selectAllAddSync = document.getElementById('add_select_all_update_students');
    if (selectAllAddSync) {
        selectAllAddSync.addEventListener('change', function() {
            document.querySelectorAll('.add-sync-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
    // Copy fee amount to all checked classes for Add Modal
    const copyAddFees = document.getElementById('add_copy_fees_all');
    if (copyAddFees) {
        copyAddFees.addEventListener('change', function() {
            if (this.checked) {
                const activeInputs = Array.from(document.querySelectorAll('.add-class-fee-input')).filter(input => !input.disabled);
                if (activeInputs.length > 0) {
                    const val = activeInputs[0].value || '0';
                    activeInputs.forEach(input => {
                        input.value = val;
                    });
                }
            }
        });
    }
    
    // Copy Modal population handler
    const copyButtons = document.querySelectorAll('.copy-structure-btn');
    const addModalEl = document.getElementById('addFeeStructureModal');
    const addModalInstance = new bootstrap.Modal(addModalEl);
    
    // Form fields in add modal
    const addFeeNameInput = document.getElementById('add_fee_name');
    const addFeeTypeSelect = document.getElementById('add_fee_type');
    const addLinkedToSelect = document.getElementById('add_linked_to');
    const addReceivingDayInput = document.getElementById('add_receiving_day');
    const addFineTypeSelect = document.getElementById('add_fine_type');
    const addFineAmountInput = document.getElementById('add_fine_amount');
    
    copyButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const name = this.getAttribute('data-fee-name');
            const type = this.getAttribute('data-fee-type');
            const applyTo = this.getAttribute('data-apply-to');
            const linkedTo = this.getAttribute('data-linked-to');
            const subDayDate = this.getAttribute('data-sub-day-date');
            const fineType = this.getAttribute('data-fine-type');
            const fineAmount = this.getAttribute('data-fine-amount');
            const classesData = JSON.parse(this.getAttribute('data-classes') || '{}');
            
            // Set values
            addFeeNameInput.value = name + ' (Copy)';
            
            selectOptionByValue(addFeeTypeSelect, type);
            selectOptionByValue(addLinkedToSelect, linkedTo);
            selectOptionByValue(addFineTypeSelect, fineType);
            
            addReceivingDayInput.value = subDayDate;
            addFineAmountInput.value = fineAmount;
            
            // Radio apply_to
            const applyRadio = document.querySelector(`input[name="apply_to"][id^="add_apply_"][value="${applyTo.toLowerCase()}"]`);
            if (applyRadio) applyRadio.checked = true;
            
            // Radio rte_status (default No)
            const rteRadio = document.querySelector(`input[name="rte_status"][id="add_rte_no"]`);
            if (rteRadio) rteRadio.checked = true;
            
            // Reset class checkboxes first
            document.querySelectorAll('.add-class-checkbox').forEach(cb => {
                cb.checked = false;
                const cid = cb.getAttribute('data-class-id');
                const amtInput = document.getElementById(`add_class_amount_${cid}`);
                if (amtInput) {
                    amtInput.value = '';
                    amtInput.disabled = true;
                }
            });
            document.querySelectorAll('.add-sync-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('add_select_all_classes').checked = false;
            document.getElementById('add_select_all_update_students').checked = false;
            document.getElementById('add_copy_fees_all').checked = false;
            
            // Populate checkboxes
            Object.keys(classesData).forEach(cid => {
                const cb = document.getElementById(`add_class_active_${cid}`);
                const amtInput = document.getElementById(`add_class_amount_${cid}`);
                if (cb) {
                    cb.checked = true;
                    if (amtInput) {
                        amtInput.disabled = false;
                        amtInput.value = classesData[cid];
                    }
                }
            });
            
            addModalInstance.show();
        });
    });

    // Delete structure handler using Swal
    const deleteBtnList = document.querySelectorAll('.delete-structure-btn');
    deleteBtnList.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const name = this.getAttribute('data-fee-name');
            const type = this.getAttribute('data-fee-type');
            
            Swal.fire({
                title: 'Delete Fee Structure?',
                text: `Are you sure you want to permanently delete the fee structure "${name}" (${type})? This will delete the fee configuration for all students and cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, Delete it!',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'swal-danger-btn-custom',
                    cancelButton: 'swal-cancel-btn-custom'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_fee_name').value = name;
                    document.getElementById('delete_fee_type').value = type;
                    document.getElementById('deleteStructureForm').submit();
                }
            });
        });
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
