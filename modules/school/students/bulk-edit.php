<?php
// modules/school/students/bulk-edit.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Helper function to safely extract strings from deeply nested arrays
if (!function_exists('get_flat_string')) {
    function get_flat_string($val)
    {
        if (is_array($val)) {
            $first_val = reset($val);
            return is_array($first_val) ? get_flat_string($first_val) : trim((string)$first_val);
        }
        return trim((string)$val);
    }
}

// Fetch sessions, classes and sections for dropdowns
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY id DESC");
$stmt->execute([':school_id' => $school_id]);
$all_sessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY section_name ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();

$sections_by_class = [];
foreach ($all_sections as $s) {
    $sections_by_class[$s['class_id']][] = [
        'id' => $s['id'],
        'name' => $s['section_name']
    ];
}

// Define the comprehensive fields list (exact matching edit modal form inputs)
$fields = [
    'first_name' => ['label' => 'First Name', 'type' => 'text', 'required' => true, 'width' => 'form-control-table-md'],
    'last_name' => ['label' => 'Last Name', 'type' => 'text', 'width' => 'form-control-table-md'],
    'session_id' => ['label' => 'Session', 'type' => 'session', 'width' => 'form-control-table-md'],
    'class_id' => ['label' => 'Class', 'type' => 'class', 'width' => 'form-control-table-md'],
    'section_id' => ['label' => 'Section', 'type' => 'section', 'width' => 'form-control-table-md'],
    'status' => ['label' => 'Status', 'type' => 'status', 'width' => 'form-control-table-md'],
    'roll_no' => ['label' => 'Roll No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'admission_no_prefix' => ['label' => 'Adm. Prefix', 'type' => 'text', 'width' => 'form-control-table-xs'],
    'admission_no' => ['label' => 'Admission No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'admission_date' => ['label' => 'Admission Date', 'type' => 'date', 'width' => 'form-control-table-md'],
    'apaar_id' => ['label' => 'APAAR ID', 'type' => 'text', 'width' => 'form-control-table-md'],
    'pen_no' => ['label' => 'PEN No.', 'type' => 'text', 'width' => 'form-control-table-md'],
    'registration_no_prefix' => ['label' => 'Reg. Prefix', 'type' => 'text', 'width' => 'form-control-table-xs'],
    'registration_no' => ['label' => 'Registration No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'enrollment_no_prefix' => ['label' => 'Enroll. Prefix', 'type' => 'text', 'width' => 'form-control-table-xs'],
    'enrollment_no' => ['label' => 'Enrollment No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'sr_no_prefix' => ['label' => 'SR Prefix', 'type' => 'text', 'width' => 'form-control-table-xs'],
    'sr_no' => ['label' => 'SR No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'general_reg_no' => ['label' => 'Gen. Reg No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'srn_no' => ['label' => 'SRN No.', 'type' => 'text', 'width' => 'form-control-table-md'],
    'stream' => ['label' => 'Stream', 'type' => 'stream', 'width' => 'form-control-table-sm'],
    'education_medium' => ['label' => 'Medium', 'type' => 'medium', 'width' => 'form-control-table-sm'],
    'referred_by' => ['label' => 'Referred By', 'type' => 'referred_by', 'width' => 'form-control-table-sm'],
    'is_rte' => ['label' => 'Is RTE?', 'type' => 'yes_no', 'width' => 'form-control-table-xs'],
    'enrolled_session' => ['label' => 'Enrolled Session', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'enrolled_class_id' => ['label' => 'Enrolled Class', 'type' => 'enrolled_class', 'width' => 'form-control-table-md'],
    'enrolled_year' => ['label' => 'Enrolled Year', 'type' => 'year', 'width' => 'form-control-table-sm'],
    'special_needs' => ['label' => 'Special Needs?', 'type' => 'yes_no', 'width' => 'form-control-table-xs'],
    'is_bpl' => ['label' => 'Is BPL?', 'type' => 'yes_no', 'width' => 'form-control-table-xs'],
    'house_block' => ['label' => 'House Block', 'type' => 'text', 'width' => 'form-control-table-sm'],

    // Parents
    'father_name' => ['label' => 'Father Name', 'type' => 'text', 'width' => 'form-control-table-md'],
    'father_qualification' => ['label' => 'Father Qual.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'father_address' => ['label' => 'Father Address', 'type' => 'text', 'width' => 'form-control-table-lg'],
    'father_occupation' => ['label' => 'Father Occ.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'father_official_address' => ['label' => 'Father Off. Addr.', 'type' => 'text', 'width' => 'form-control-table-lg'],
    'father_income' => ['label' => 'Father Income', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'father_email' => ['label' => 'Father Email', 'type' => 'text', 'width' => 'form-control-table-md'],
    'father_mobile' => ['label' => 'Father Mobile', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'father_aadhar' => ['label' => 'Father Aadhar', 'type' => 'text', 'width' => 'form-control-table-sm'],

    'mother_name' => ['label' => 'Mother Name', 'type' => 'text', 'width' => 'form-control-table-md'],
    'mother_qualification' => ['label' => 'Mother Qual.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'mother_address' => ['label' => 'Mother Address', 'type' => 'text', 'width' => 'form-control-table-lg'],
    'mother_occupation' => ['label' => 'Mother Occ.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'mother_official_address' => ['label' => 'Mother Off. Addr.', 'type' => 'text', 'width' => 'form-control-table-lg'],
    'mother_income' => ['label' => 'Mother Income', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'mother_email' => ['label' => 'Mother Email', 'type' => 'text', 'width' => 'form-control-table-md'],
    'mother_mobile' => ['label' => 'Mother Mobile', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'mother_aadhar' => ['label' => 'Mother Aadhar', 'type' => 'text', 'width' => 'form-control-table-sm'],

    'guardian_name' => ['label' => 'Guardian Name', 'type' => 'text', 'width' => 'form-control-table-md'],
    'guardian_qualification' => ['label' => 'Guardian Qual.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'guardian_address' => ['label' => 'Guardian Address', 'type' => 'text', 'width' => 'form-control-table-lg'],
    'guardian_occupation' => ['label' => 'Guardian Occ.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'guardian_official_address' => ['label' => 'Guardian Off. Addr.', 'type' => 'text', 'width' => 'form-control-table-lg'],
    'guardian_income' => ['label' => 'Guardian Income', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'guardian_email' => ['label' => 'Guardian Email', 'type' => 'text', 'width' => 'form-control-table-md'],
    'guardian_mobile' => ['label' => 'Guardian Mobile', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'guardian_aadhar' => ['label' => 'Guardian Aadhar', 'type' => 'text', 'width' => 'form-control-table-sm'],

    // Contact
    'mobile_no' => ['label' => 'Mobile No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'alternate_no' => ['label' => 'Alt. Mobile', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'whatsapp_no' => ['label' => 'WhatsApp No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'email' => ['label' => 'Email', 'type' => 'text', 'width' => 'form-control-table-md'],
    'gender' => ['label' => 'Gender', 'type' => 'gender', 'width' => 'form-control-table-sm'],
    'blood_group' => ['label' => 'Blood Group', 'type' => 'blood_group', 'width' => 'form-control-table-sm'],
    'height' => ['label' => 'Height', 'type' => 'text', 'width' => 'form-control-table-xs'],
    'weight' => ['label' => 'Weight', 'type' => 'text', 'width' => 'form-control-table-xs'],
    'dob' => ['label' => 'DOB', 'type' => 'date', 'width' => 'form-control-table-md'],
    'place_of_birth' => ['label' => 'Birth Place', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'dob_certificate_no' => ['label' => 'DOB Cert. No.', 'type' => 'text', 'width' => 'form-control-table-sm'],

    // Finance
    'total_fees' => ['label' => 'Total Fees', 'type' => 'number', 'width' => 'form-control-table-sm'],
    'total_paid' => ['label' => 'Total Paid', 'type' => 'number', 'width' => 'form-control-table-sm'],
    'total_discount' => ['label' => 'Total Discount', 'type' => 'number', 'width' => 'form-control-table-sm'],
    'fine_amount' => ['label' => 'Fine Amount', 'type' => 'number', 'width' => 'form-control-table-sm'],
    'biometric_code' => ['label' => 'Biometric Code', 'type' => 'text', 'width' => 'form-control-table-sm'],

    // Docs & Religion
    'income_app_no' => ['label' => 'Income App No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'caste_app_no' => ['label' => 'Caste App No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'domicile_app_no' => ['label' => 'Domicile App No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'nationality' => ['label' => 'Nationality', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'religion' => ['label' => 'Religion', 'type' => 'religion', 'width' => 'form-control-table-sm'],
    'category' => ['label' => 'Category', 'type' => 'category', 'width' => 'form-control-table-sm'],
    'caste' => ['label' => 'Caste', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'aadhar_no' => ['label' => 'Aadhar No.', 'type' => 'text', 'width' => 'form-control-table-sm'],

    // TC
    'tc_no' => ['label' => 'TC No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'tc_issue_date' => ['label' => 'TC Issue Date', 'type' => 'date', 'width' => 'form-control-table-md'],

    // Scholarship & Govt
    'scholarship_id' => ['label' => 'Scholarship ID', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'scholarship_password' => ['label' => 'Scholarship Pwd', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'govt_student_id' => ['label' => 'Govt Student ID', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'govt_family_id' => ['label' => 'Govt Family ID', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'samagra_id' => ['label' => 'Samagra ID', 'type' => 'text', 'width' => 'form-control-table-sm'],

    // Bank
    'bank_name' => ['label' => 'Bank Name', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'bank_branch' => ['label' => 'Bank Branch', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'ifsc_code' => ['label' => 'IFSC Code', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'bank_account_holder' => ['label' => 'Account Holder', 'type' => 'text', 'width' => 'form-control-table-md'],
    'bank_account_no' => ['label' => 'Account No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
    'pan_no' => ['label' => 'PAN No.', 'type' => 'text', 'width' => 'form-control-table-sm'],
];

// ─── POST HANDLING ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: bulk-edit.php');
        exit;
    }

    $students_data = $_POST['students'] ?? [];
    try {
        $pdo->beginTransaction();

        // Build dynamic UPDATE query based on configured fields to guarantee sync
        $sql_parts = [];
        foreach ($fields as $field_name => $field_meta) {
            $sql_parts[] = "{$field_name} = :{$field_name}";
        }
        $update_sql = "UPDATE students SET " . implode(", ", $sql_parts) . " WHERE id = :id AND school_id = :school_id";
        $stmt = $pdo->prepare($update_sql);

        $stmt_user = $pdo->prepare("
            UPDATE users SET
                first_name = :first_name,
                last_name = :last_name,
                phone = :phone,
                status = :user_status
            WHERE id = (SELECT user_id FROM students WHERE id = :id) AND school_id = :school_id
        ");

        foreach ($students_data as $sid => $data) {
            $sid = intval($sid);
            $first_name = trim(get_flat_string($data['first_name'] ?? ''));
            if (empty($first_name)) {
                continue; // Skip invalid records
            }

            $bind_params = [
                ':id' => $sid,
                ':school_id' => $school_id
            ];

            foreach ($fields as $field_name => $field_meta) {
                $val = $data[$field_name] ?? '';
                if (is_array($val)) {
                    $val = get_flat_string($val);
                }
                $val = trim((string)$val);

                // Handle nulls for empty/optional inputs
                if ($val === '') {
                    $bind_params[":{$field_name}"] = null;
                } else {
                    if ($field_meta['type'] === 'number') {
                        $bind_params[":{$field_name}"] = floatval($val);
                    } elseif (in_array($field_meta['type'], ['class', 'section', 'session', 'enrolled_class'])) {
                        $bind_params[":{$field_name}"] = intval($val);
                    } else {
                        $bind_params[":{$field_name}"] = $val;
                    }
                }
            }

            $stmt->execute($bind_params);

            // Sync user status and names
            $status = trim(get_flat_string($data['status'] ?? 'active'));
            $mobile_no = trim(get_flat_string($data['mobile_no'] ?? ''));
            $last_name = trim(get_flat_string($data['last_name'] ?? ''));

            $user_status = 'inactive';
            if ($status === 'active') {
                $user_status = 'active';
            } elseif ($status === 'suspended') {
                $user_status = 'suspended';
            }

            $stmt_user->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':phone' => $mobile_no,
                ':user_status' => $user_status,
                ':id' => $sid,
                ':school_id' => $school_id
            ]);
        }

        $pdo->commit();
        $_SESSION['flash_success'] = "Students updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
    }

    $query_params = array_intersect_key($_GET, array_flip(['class_id', 'section_id', 'status', 'search', 'page']));
    header('Location: bulk-edit.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : ''));
    exit;
}

// ─── QUERY DATA ─────────────────────────────────────────────────────────────
$filter_class_id = !empty($_GET['class_id']) ? intval($_GET['class_id']) : null;
$filter_section_id = !empty($_GET['section_id']) ? intval($_GET['section_id']) : null;
$filter_status = !empty($_GET['status']) ? trim($_GET['status']) : '';
$filter_search = !empty($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = ["s.school_id = :school_id", "s.deleted_at IS NULL"];
$params = [':school_id' => $school_id];

if ($filter_class_id) {
    $where_clauses[] = "s.class_id = :class_id";
    $params[':class_id'] = $filter_class_id;
}
if ($filter_section_id) {
    $where_clauses[] = "s.section_id = :section_id";
    $params[':section_id'] = $filter_section_id;
}
if ($filter_status) {
    $where_clauses[] = "s.status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_search !== '') {
    $where_clauses[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.admission_no LIKE :search OR s.roll_no LIKE :search OR s.father_name LIKE :search OR s.mobile_no LIKE :search)";
    $params[':search'] = '%' . $filter_search . '%';
}

$where_sql = implode(" AND ", $where_clauses);

// Pagination setup
$limit = 10; // 10 rows per page to prevent heavy DOM rendering due to large column count
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE {$where_sql}");
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$stmt_students = $pdo->prepare("
    SELECT s.*, u.username as u_name
    FROM   students s
    JOIN   users u ON s.user_id = u.id
    WHERE  {$where_sql}
    ORDER  BY s.id DESC
    LIMIT  :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt_students->bindValue($key, $val);
}
$stmt_students->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_students->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_students->execute();
$students = $stmt_students->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-1 font-heading fw-extrabold">Students Bulk Edit</h2>
        <p class="text-xs text-muted mb-0">Modify multiple student profiles in a single row-based grid layout containing all student details.</p>
    </div>
</div>

<!-- Meta tag for JS parameter passing without inline scripting -->
<div id="student-page-data"
    data-csrf-token="<?php echo $csrf_token; ?>"
    data-base-url="<?php echo BASE_URL; ?>"
    data-flash-success="<?php echo htmlspecialchars($flash_success ?? ''); ?>"
    data-flash-error="<?php echo htmlspecialchars($flash_error ?? ''); ?>">
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card-premium p-4 mb-4">
            <!-- Filter Toolbar -->
            <form method="GET" action="bulk-edit.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label-admin">Class</label>
                    <select name="class_id" class="form-control-admin">
                        <option value="">All Classes</option>
                        <?php foreach ($all_classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($filter_class_id === intval($c['id'])) ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-admin">Section</label>
                    <select name="section_id" class="form-control-admin">
                        <option value="">All Sections</option>
                        <?php foreach ($all_sections as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($filter_section_id === intval($s['id'])) ? 'selected' : ''; ?>>
                                <?php echo sanitize($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-admin">Status</label>
                    <select name="status" class="form-control-admin">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="passed" <?php echo ($filter_status === 'passed') ? 'selected' : ''; ?>>Passed</option>
                        <option value="dropped" <?php echo ($filter_status === 'dropped') ? 'selected' : ''; ?>>Dropped</option>
                        <option value="suspended" <?php echo ($filter_status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-admin">Search</label>
                    <input type="text" name="search" class="form-control-admin" placeholder="Search..." value="<?php echo sanitize($filter_search); ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 py-2"><i class="ph-light ph-funnel"></i> Filter</button>
                    <a href="bulk-edit.php" class="btn btn-secondary btn-sm w-100 py-2"><i class="ph-light ph-x"></i> Reset</a>
                </div>
            </form>
        </div>

        <div class="card-premium">
            <form method="POST" action="bulk-edit.php<?php echo (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="bulk_update">

                <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm px-4 py-2"><i class="ph-fill ph-floppy-disk"></i> Save Changes</button>
                    </div>
                    <div class="teacher-total-badge">
                        <i class="ph-light ph-users"></i>
                        Total Found: <span class="count-num"><?php echo $total_rows; ?></span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <?php if (empty($students)): ?>
                            <div class="p-5 text-center">
                                <div class="trash-empty-icon mx-auto mb-3">
                                    <i class="ph-light ph-users"></i>
                                </div>
                                <h5 class="fw-semibold mt-3">No students found</h5>
                                <p class="text-xs text-muted mb-0">Try adjusting your filters or search terms.</p>
                            </div>
                        <?php else: ?>
                            <table class="teacher-table table-premium mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <?php foreach ($fields as $field_name => $field_meta): ?>
                                            <th><?php echo $field_meta['label']; ?> <?php echo !empty($field_meta['required']) ? '<span class="text-danger">*</span>' : ''; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $idx = $offset + 1;
                                    foreach ($students as $s):
                                    ?>
                                        <tr>
                                            <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                            <?php foreach ($fields as $field_name => $field_meta):
                                                $input_width = $field_meta['width'] ?? 'form-control-table-md';
                                                $current_val = $s[$field_name] ?? '';
                                            ?>
                                                <td>
                                                    <?php if ($field_meta['type'] === 'text'): ?>
                                                        <input type="text"
                                                            name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]"
                                                            value="<?php echo sanitize($current_val); ?>"
                                                            class="form-control-table <?php echo $input_width; ?>"
                                                            <?php echo !empty($field_meta['required']) ? 'required' : ''; ?>>

                                                    <?php elseif ($field_meta['type'] === 'number'): ?>
                                                        <input type="number"
                                                            step="0.01"
                                                            name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]"
                                                            value="<?php echo sanitize($current_val); ?>"
                                                            class="form-control-table <?php echo $input_width; ?>">

                                                    <?php elseif ($field_meta['type'] === 'date'): ?>
                                                        <input type="date"
                                                            name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]"
                                                            value="<?php echo sanitize($current_val); ?>"
                                                            class="form-control-table <?php echo $input_width; ?>">

                                                    <?php elseif ($field_meta['type'] === 'session'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Session --</option>
                                                            <?php foreach ($all_sessions as $ses): ?>
                                                                <option value="<?php echo $ses['id']; ?>" <?php echo (intval($current_val) === intval($ses['id'])) ? 'selected' : ''; ?>>
                                                                    <?php echo sanitize($ses['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'class' || $field_meta['type'] === 'enrolled_class'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Class --</option>
                                                            <?php foreach ($all_classes as $c): ?>
                                                                <option value="<?php echo $c['id']; ?>" <?php echo (intval($current_val) === intval($c['id'])) ? 'selected' : ''; ?>>
                                                                    <?php echo sanitize($c['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'section'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Section --</option>
                                                            <?php foreach ($all_sections as $sec): ?>
                                                                <option value="<?php echo $sec['id']; ?>" <?php echo (intval($current_val) === intval($sec['id'])) ? 'selected' : ''; ?>>
                                                                    <?php echo sanitize($sec['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'status'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>" required>
                                                            <option value="active" <?php echo ($current_val === 'active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo ($current_val === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                            <option value="passed" <?php echo ($current_val === 'passed') ? 'selected' : ''; ?>>Passed</option>
                                                            <option value="dropped" <?php echo ($current_val === 'dropped') ? 'selected' : ''; ?>>Dropped</option>
                                                            <option value="suspended" <?php echo ($current_val === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'yes_no'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="no" <?php echo ($current_val === 'no') ? 'selected' : ''; ?>>No</option>
                                                            <option value="yes" <?php echo ($current_val === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'gender'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Gender --</option>
                                                            <option value="male" <?php echo ($current_val === 'male') ? 'selected' : ''; ?>>Male</option>
                                                            <option value="female" <?php echo ($current_val === 'female') ? 'selected' : ''; ?>>Female</option>
                                                            <option value="other" <?php echo ($current_val === 'other') ? 'selected' : ''; ?>>Other</option>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'blood_group'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Select --</option>
                                                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                                                                <option value="<?php echo $bg; ?>" <?php echo ($current_val === $bg) ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'stream'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Stream --</option>
                                                            <option value="Science" <?php echo ($current_val === 'Science') ? 'selected' : ''; ?>>Science</option>
                                                            <option value="Commerce" <?php echo ($current_val === 'Commerce') ? 'selected' : ''; ?>>Commerce</option>
                                                            <option value="Arts" <?php echo ($current_val === 'Arts') ? 'selected' : ''; ?>>Arts</option>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'medium'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Medium --</option>
                                                            <option value="English" <?php echo ($current_val === 'English') ? 'selected' : ''; ?>>English</option>
                                                            <option value="Hindi" <?php echo ($current_val === 'Hindi') ? 'selected' : ''; ?>>Hindi</option>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'referred_by'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Select --</option>
                                                            <option value="Direct" <?php echo ($current_val === 'Direct') ? 'selected' : ''; ?>>Direct</option>
                                                            <option value="Staff" <?php echo ($current_val === 'Staff') ? 'selected' : ''; ?>>Staff</option>
                                                            <option value="Agent" <?php echo ($current_val === 'Agent') ? 'selected' : ''; ?>>Agent</option>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'religion'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Select --</option>
                                                            <?php foreach (['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Other'] as $rel): ?>
                                                                <option value="<?php echo $rel; ?>" <?php echo ($current_val === $rel) ? 'selected' : ''; ?>><?php echo $rel; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'category'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Select --</option>
                                                            <?php foreach (['General', 'OBC', 'SC', 'ST', 'EWS'] as $cat): ?>
                                                                <option value="<?php echo $cat; ?>" <?php echo ($current_val === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                    <?php elseif ($field_meta['type'] === 'year'): ?>
                                                        <select name="students[<?php echo $s['id']; ?>][<?php echo $field_name; ?>]" class="form-control-table <?php echo $input_width; ?>">
                                                            <option value="">-- Select --</option>
                                                            <?php for ($y = date('Y'); $y >= 2010; $y--): ?>
                                                                <option value="<?php echo $y; ?>" <?php echo (intval($current_val) === $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
                        <div class="text-xs text-muted">
                            Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> (Total <strong><?php echo $total_rows; ?></strong> students)
                        </div>
                        <ul class="pagination pagination-sm m-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <?php $prev_query = array_merge($_GET, ['page' => $page - 1]); ?>
                                <a class="page-link" href="bulk-edit.php?<?php echo http_build_query($prev_query); ?>">Previous</a>
                            </li>

                            <?php
                            $start_p = max(1, $page - 2);
                            $end_p = min($total_pages, $page + 2);
                            for ($p = $start_p; $p <= $end_p; $p++):
                                $p_query = array_merge($_GET, ['page' => $p]);
                            ?>
                                <li class="page-item <?php echo ($page === $p) ? 'active' : ''; ?>">
                                    <a class="page-link" href="bulk-edit.php?<?php echo http_build_query($p_query); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <?php $next_query = array_merge($_GET, ['page' => $page + 1]); ?>
                                <a class="page-link" href="bulk-edit.php?<?php echo http_build_query($next_query); ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
