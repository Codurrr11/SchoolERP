<?php
// modules/school/students/view.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch student details
$stmt = $pdo->prepare("
    SELECT s.*, u.username as u_name, c.name as class_name, sec.name as section_name,
           ec.name as enrolled_class_name
    FROM   students s
    JOIN   users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN classes ec ON s.enrolled_class_id = ec.id
    WHERE  s.id = :id AND s.school_id = :school_id
");
$stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['flash_error'] = "Student profile not found.";
    header('Location: index.php');
    exit;
}

// Fetch student siblings
$siblings = [];
if (!empty($student['father_name']) || !empty($student['mother_name'])) {
    $conditions = [];
    $params = [
        ':school_id' => $school_id,
        ':id' => $student_id
    ];

    if (!empty($student['father_name'])) {
        $conditions[] = "s.father_name = :father_name";
        $params[':father_name'] = $student['father_name'];
    }
    if (!empty($student['mother_name'])) {
        $conditions[] = "s.mother_name = :mother_name";
        $params[':mother_name'] = $student['mother_name'];
    }

    $where_clause = implode(" OR ", $conditions);

    $stmt_sib = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.roll_no, c.name as class_name, sec.name as section_name, s.photo, s.admission_no_prefix, s.admission_no, s.total_fees, s.total_paid, s.total_discount, s.fine_amount
        FROM   students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE  s.school_id = :school_id
          AND  s.id != :id
          AND  ({$where_clause})
          AND  s.deleted_at IS NULL
    ");
    $stmt_sib->execute($params);
    $siblings = $stmt_sib->fetchAll();
}

// POST action for uploading documents directly if missing or changing them
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: view.php?id=' . $student_id);
        exit;
    }

    $doc_type = $_POST['doc_type'] ?? '';
    if (!in_array($doc_type, [
        'photo', 'dob_certificate', 'category_certificate', 'aadhar_file', 'tc_file', 
        'mother_photo', 'father_photo', 'guardian_photo', 
        'mother_aadhar_file', 'father_aadhar_file', 'guardian_aadhar_file', 'profile_file'
    ])) {
        $_SESSION['flash_error'] = "Invalid document type.";
        header('Location: view.php?id=' . $student_id);
        exit;
    }

    $upload_dir = 'c:/xampp/htdocs/schoolerp/uploads/students/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
            $prefix_map = [
                'photo' => 'photo_',
                'dob_certificate' => 'dob_',
                'category_certificate' => 'cat_',
                'aadhar_file' => 'aadhar_',
                'tc_file' => 'tc_',
                'mother_photo' => 'mother_',
                'father_photo' => 'father_',
                'guardian_photo' => 'guardian_',
                'mother_aadhar_file' => 'mother_aadhar_',
                'father_aadhar_file' => 'father_aadhar_',
                'guardian_aadhar_file' => 'guardian_aadhar_',
                'profile_file' => 'profile_'
            ];
            $prefix = $prefix_map[$doc_type] ?? 'doc_';
            $new_name = $prefix . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $new_name)) {
                $file_path = 'uploads/students/' . $new_name;

                // Delete old file if exists
                if ($student[$doc_type] && file_exists('c:/xampp/htdocs/schoolerp/' . $student[$doc_type])) {
                    @unlink('c:/xampp/htdocs/schoolerp/' . $student[$doc_type]);
                }

                // Update DB
                $stmt_up = $pdo->prepare("UPDATE students SET {$doc_type} = :file WHERE id = :id AND school_id = :school_id");
                $stmt_up->execute([':file' => $file_path, ':id' => $student_id, ':school_id' => $school_id]);

                if ($doc_type === 'photo') {
                    $stmt_u_up = $pdo->prepare("UPDATE users SET avatar = :file WHERE id = :user_id AND school_id = :school_id");
                    $stmt_u_up->execute([':file' => $file_path, ':user_id' => $student['user_id'], ':school_id' => $school_id]);
                }

                $_SESSION['flash_success'] = ucfirst(str_replace('_', ' ', $doc_type)) . " updated successfully!";
            } else {
                $_SESSION['flash_error'] = "Failed to save the uploaded file.";
            }
        } else {
            $_SESSION['flash_error'] = "Invalid file type. Only JPG, JPEG, PNG, GIF, and PDF are allowed.";
        }
    } else {
        $_SESSION['flash_error'] = "No file uploaded or upload error occurred.";
    }

    header('Location: view.php?id=' . $student_id);
    exit;
}

// Fetch student qualifications details
$stmt_q = $pdo->prepare("
    SELECT * FROM student_qualifications
    WHERE student_id = :student_id
");
$stmt_q->execute([':student_id' => $student['id']]);
$qualifications = $stmt_q->fetchAll();

// Fetch student attendance records
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Query attendance records for the selected month and year
$stmt_month_att = $pdo->prepare("
    SELECT * FROM student_attendance
    WHERE student_id = :student_id 
      AND school_id = :school_id
      AND MONTH(date) = :month
      AND YEAR(date) = :year
");
$stmt_month_att->execute([
    ':student_id' => $student['id'],
    ':school_id' => $school_id,
    ':month' => $selected_month,
    ':year' => $selected_year
]);
$month_attendance_records = $stmt_month_att->fetchAll();

// Map records by date and count stats
$attendance_by_date = [];
$count_present = 0;
$count_absent = 0;
$count_pending = 0;
$count_holidays = 0;
$count_leave = 0;

foreach ($month_attendance_records as $rec) {
    $attendance_by_date[$rec['date']] = $rec;
    
    if ($rec['status'] === 'present') {
        $count_present++;
    } elseif ($rec['status'] === 'absent') {
        $count_absent++;
    } elseif ($rec['status'] === 'leave') {
        $count_leave++;
    } elseif ($rec['status'] === 'late') {
        $count_present++;
    } elseif ($rec['status'] === 'half_day') {
        $count_present++;
    }
}


$csrf_token = generate_csrf_token();

// Define mock admit cards list
$admit_cards_data = [
    [
        'id' => 1,
        'title' => 'Unit-1 2026',
        'session' => '2026 - 2027',
        'status' => 'Active',
        'created_at' => '12 Mar, 2026 02:16:18pm'
    ],
    [
        'id' => 2,
        'title' => 'Terminal-1 2026',
        'session' => '2026 - 2027',
        'status' => 'Active',
        'created_at' => '15 May, 2026 10:30:45am'
    ],
    [
        'id' => 3,
        'title' => 'Unit-2 2026',
        'session' => '2026 - 2027',
        'status' => 'Active',
        'created_at' => '20 Aug, 2026 11:15:20am'
    ],
    [
        'id' => 4,
        'title' => 'Final Exam 2026',
        'session' => '2026 - 2027',
        'status' => 'Active',
        'created_at' => '10 Nov, 2026 03:45:12pm'
    ]
];

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fee_balance = $student['total_fees'] - $student['total_paid'] - $student['total_discount'] + $student['fine_amount'];

// Fetch active fee items
$stmt_fee_items = $pdo->prepare("SELECT * FROM student_fee_items WHERE student_id = :student_id AND is_active = 1 ORDER BY id ASC");
$stmt_fee_items->execute([':student_id' => $student['id']]);
$fee_items = $stmt_fee_items->fetchAll();

// Fetch inactive (deleted) fee items
$stmt_deleted_fee_items = $pdo->prepare("SELECT * FROM student_fee_items WHERE student_id = :student_id AND is_active = 0 ORDER BY id ASC");
$stmt_deleted_fee_items->execute([':student_id' => $student['id']]);
$deleted_fee_items = $stmt_deleted_fee_items->fetchAll();

// Fetch payments log
$stmt_payments = $pdo->prepare("SELECT * FROM fee_payments WHERE student_id = :student_id AND school_id = :school_id ORDER BY id DESC");
$stmt_payments->execute([':student_id' => $student['id'], ':school_id' => $school_id]);
$payments = $stmt_payments->fetchAll();

require_once '../../../includes/header.php';
?>

<!-- ─── PAGE HEADER ────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">Student Profile</h2>
        <p class="text-xs text-muted mb-0">Detailed academic, personal, and administrative records of the student.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <a href="index.php" class="btn-admin-secondary">
            <i class="ph-light ph-arrow-left"></i> Back to Directory
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- FULL WIDTH COLUMN: QUICK SUMMARY CARD -->
    <div class="col-12">
        <div class="card-premium teacher-summary-card p-0">
            <div class="p-4">
                <div class="row align-items-center g-4">
                    <!-- Profile Primary Info -->
                    <div class="col-md-4 text-center profile-left-col pe-md-4">
                        <div class="mb-3 d-flex justify-content-center">
                            <?php if (!empty($student['photo'])): ?>
                                <img src="<?php echo BASE_URL . sanitize($student['photo']); ?>" alt="Profile Photo" class="summary-avatar">
                            <?php else: ?>
                                <div class="summary-avatar-placeholder">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'] ?? '', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="summary-name mb-1"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                        <div class="text-xs text-muted mb-2">@<?php echo sanitize($student['u_name']); ?></div>
                        <div class="mb-3">
                            <?php if ($student['status'] === 'active'): ?>
                                <span class="teacher-status-badge active">
                                    <span class="status-dot"></span> Active Student
                                </span>
                            <?php else: ?>
                                <span class="teacher-status-badge inactive">
                                    Inactive / Suspended
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <a href="mailto:<?php echo sanitize($student['email']); ?>" class="teacher-comm-btn email-btn" title="Email Student">
                                <i class="ph-light ph-envelope-simple"></i> Email
                            </a>
                            <?php if (!empty($student['mobile_no'])): ?>
                                <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $student['mobile_no']); ?>" target="_blank" class="teacher-comm-btn whatsapp-btn" title="WhatsApp Parent">
                                    <i class="ph-light ph-whatsapp-logo"></i> WhatsApp
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Secondary details (Grid) -->
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Class & Section</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize(($student['class_name'] ?? '') . ' - ' . ($student['section_name'] ?? '')); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Roll Number</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize($student['roll_no'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Admission No</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize(($student['admission_no_prefix'] ?? '') . $student['admission_no']); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Father's Name</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize($student['father_name'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Biometric Code</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize($student['biometric_code'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Fees Balance</span>
                                    <span class="detail-box-val">
                                        <?php if ($fee_balance <= 0): ?>
                                            <span class="badge bg-success text-white">No Balance</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white"><?php echo intval($fee_balance); ?> Pending</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs under Profile Info -->
            <div class="border-top student-tabs-header">
                <ul class="nav nav-tabs student-tabs flex-nowrap border-0 m-0" id="studentTab" role="tablist" style="overflow-x: auto; white-space: nowrap;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
                            <i class="ph-light ph-user-focus"></i> View Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="siblings-tab" data-bs-toggle="tab" data-bs-target="#siblings" type="button" role="tab" aria-controls="siblings" aria-selected="false">
                            <i class="ph-light ph-users-three"></i> Siblings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees" type="button" role="tab" aria-controls="fees" aria-selected="false">
                            <i class="ph-light ph-coins"></i> Fees
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="deleted-fees-tab" data-bs-toggle="tab" data-bs-target="#deleted-fees" type="button" role="tab" aria-controls="deleted-fees" aria-selected="false">
                            <i class="ph-light ph-trash-simple"></i> Deleted Fees Structures
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab" aria-controls="inventory" aria-selected="false">
                            <i class="ph-light ph-package"></i> Inventory
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="idcard-tab" data-bs-toggle="tab" data-bs-target="#idcard" type="button" role="tab" aria-controls="idcard" aria-selected="false">
                            <i class="ph-light ph-identification-card"></i> Download ID Card
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false">
                            <i class="ph-light ph-calendar"></i> Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admitcards-tab" data-bs-toggle="tab" data-bs-target="#admitcards" type="button" role="tab" aria-controls="admitcards" aria-selected="false">
                            <i class="ph-light ph-ticket"></i> Admit Cards
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab" aria-controls="subjects" aria-selected="false">
                            <i class="ph-light ph-book-open"></i> Subjects
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="offlinetests-tab" data-bs-toggle="tab" data-bs-target="#offlinetests" type="button" role="tab" aria-controls="offlinetests" aria-selected="false">
                            <i class="ph-light ph-file-text"></i> Offline Tests
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="marksheets-tab" data-bs-toggle="tab" data-bs-target="#marksheets" type="button" role="tab" aria-controls="marksheets" aria-selected="false">
                            <i class="ph-light ph-certificate"></i> Marksheets
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="homework-tab" data-bs-toggle="tab" data-bs-target="#homework" type="button" role="tab" aria-controls="homework" aria-selected="false">
                            <i class="ph-light ph-house-line"></i> Homework
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                            <i class="ph-light ph-files"></i> Documents
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                            <i class="ph-light ph-activity"></i> Activity
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- FULL WIDTH COLUMN: DETAILED TABS CONTENT -->
    <div class="col-12">
        <div class="card-premium p-0">
            <!-- Tabs Content Wrapper -->
            <div class="card-body p-4 text-dark">
                <div class="tab-content" id="studentTabContent">

                    <!-- TAB 1: VIEW DETAILS -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">

                        <!-- Group 1: Admission details -->
                        <div class="detail-section-card sec-classes">
                            <div class="detail-section-title">
                                <i class="ph-light ph-chalkboard-teacher"></i> Admission & Enrollment Records
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> APAAR ID</span>
                                        <span class="detail-box-val <?php echo empty($student['apaar_id']) ? 'empty' : ''; ?>"><?php echo sanitize($student['apaar_id'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> PEN No.</span>
                                        <span class="detail-box-val <?php echo empty($student['pen_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['pen_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Registration No</span>
                                        <span class="detail-box-val mono"><?php echo sanitize(($student['registration_no_prefix'] ?? '') . $student['registration_no']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Enrollment No</span>
                                        <span class="detail-box-val mono"><?php echo sanitize(($student['enrollment_no_prefix'] ?? '') . $student['enrollment_no']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> SR No</span>
                                        <span class="detail-box-val mono"><?php echo sanitize(($student['sr_no_prefix'] ?? '') . $student['sr_no']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> General Reg No.</span>
                                        <span class="detail-box-val <?php echo empty($student['general_reg_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['general_reg_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-calendar"></i> Admission Date</span>
                                        <span class="detail-box-val"><?php echo !empty($student['admission_date']) ? date('d-M-Y', strtotime($student['admission_date'])) : '—'; ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> SRN No</span>
                                        <span class="detail-box-val <?php echo empty($student['srn_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['srn_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Applied Class</span>
                                        <span class="detail-box-val"><?php echo sanitize($student['class_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Applied Section</span>
                                        <span class="detail-box-val"><?php echo sanitize($student['section_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Stream</span>
                                        <span class="detail-box-val <?php echo empty($student['stream']) ? 'empty' : ''; ?>"><?php echo sanitize($student['stream'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Education Medium</span>
                                        <span class="detail-box-val <?php echo empty($student['education_medium']) ? 'empty' : ''; ?>"><?php echo sanitize($student['education_medium'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user-plus"></i> Referred By</span>
                                        <span class="detail-box-val <?php echo empty($student['referred_by']) ? 'empty' : ''; ?>"><?php echo sanitize($student['referred_by'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-check-square"></i> Is RTE Student?</span>
                                        <span class="detail-box-val text-uppercase"><?php echo sanitize($student['is_rte'] ?? 'no'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-calendar-blank"></i> Enrolled Session</span>
                                        <span class="detail-box-val <?php echo empty($student['enrolled_session']) ? 'empty' : ''; ?>"><?php echo sanitize($student['enrolled_session'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-chalkboard-teacher"></i> Enrolled Class</span>
                                        <span class="detail-box-val <?php echo empty($student['enrolled_class_name']) ? 'empty' : ''; ?>"><?php echo sanitize($student['enrolled_class_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-calendar-blank"></i> Enrolled Year</span>
                                        <span class="detail-box-val <?php echo empty($student['enrolled_year']) ? 'empty' : ''; ?>"><?php echo sanitize($student['enrolled_year'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-check-square"></i> Special Needs?</span>
                                        <span class="detail-box-val text-uppercase"><?php echo sanitize($student['special_needs'] ?? 'no'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-check-square"></i> Is BPL Student?</span>
                                        <span class="detail-box-val text-uppercase"><?php echo sanitize($student['is_bpl'] ?? 'no'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-house-line"></i> House / Block</span>
                                        <span class="detail-box-val <?php echo empty($student['house_block']) ? 'empty' : ''; ?>"><?php echo sanitize($student['house_block'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Personal details -->
                        <div class="detail-section-card sec-personal">
                            <div class="detail-section-title">
                                <i class="ph-light ph-identification-card"></i> Personal Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> First Name</span>
                                        <span class="detail-box-val"><?php echo sanitize($student['first_name']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Last Name</span>
                                        <span class="detail-box-val <?php echo empty($student['last_name']) ? 'empty' : ''; ?>"><?php echo sanitize($student['last_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Father Name</span>
                                        <span class="detail-box-val <?php echo empty($student['father_name']) ? 'empty' : ''; ?>"><?php echo sanitize($student['father_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Mobile Number</span>
                                        <span class="detail-box-val <?php echo empty($student['mobile_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['mobile_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Alternate Mobile</span>
                                        <span class="detail-box-val <?php echo empty($student['alternate_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['alternate_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-whatsapp-logo"></i> WhatsApp Number</span>
                                        <span class="detail-box-val <?php echo empty($student['whatsapp_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['whatsapp_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-envelope-simple"></i> Email Address</span>
                                        <span class="detail-box-val email-link <?php echo empty($student['email']) ? 'empty' : ''; ?>"><?php echo sanitize($student['email'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-gender-intersex"></i> Gender</span>
                                        <span class="detail-box-val"><?php echo ucfirst(sanitize($student['gender'] ?? '—')); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-drop"></i> Blood Group</span>
                                        <span class="detail-box-val <?php echo empty($student['blood_group']) ? 'empty' : ''; ?>"><?php echo sanitize($student['blood_group'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-arrows-down-up"></i> Height</span>
                                        <span class="detail-box-val <?php echo empty($student['height']) ? 'empty' : ''; ?>"><?php echo sanitize($student['height'] ?? '—'); ?> cm</span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-scales"></i> Weight</span>
                                        <span class="detail-box-val <?php echo empty($student['weight']) ? 'empty' : ''; ?>"><?php echo sanitize($student['weight'] ?? '—'); ?> kg</span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-calendar"></i> Date of Birth (DOB)</span>
                                        <span class="detail-box-val"><?php echo !empty($student['dob']) ? date('d-M-Y', strtotime($student['dob'])) : '—'; ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-house-line"></i> Place of Birth</span>
                                        <span class="detail-box-val <?php echo empty($student['place_of_birth']) ? 'empty' : ''; ?>"><?php echo sanitize($student['place_of_birth'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> DOB Certificate No</span>
                                        <span class="detail-box-val mono <?php echo empty($student['dob_certificate_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['dob_certificate_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 3: Financial/Fee Info -->
                        <div class="detail-section-card sec-address">
                            <div class="detail-section-title">
                                <i class="ph-light ph-credit-card"></i> Financial / Fee Ledger
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Total Fees</span>
                                        <span class="detail-box-val font-bold"><?php echo intval($student['total_fees']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Total Paid</span>
                                        <span class="detail-box-val text-success font-bold"><?php echo intval($student['total_paid']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Total Discount</span>
                                        <span class="detail-box-val text-info font-bold"><?php echo intval($student['total_discount']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Fine Amount</span>
                                        <span class="detail-box-val text-danger font-bold"><?php echo intval($student['fine_amount']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 4: Previous qualifications -->
                        <div class="detail-section-card sec-experience">
                            <div class="detail-section-title">
                                <i class="ph-light ph-graduation-cap"></i> Previous Qualifications
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th>Qualification</th>
                                            <th>Pass. Year</th>
                                            <th>Roll No.</th>
                                            <th>Obt. Marks</th>
                                            <th>%</th>
                                            <th>Subjects</th>
                                            <th>School/College</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($qualifications)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">No academic qualifications listed.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($qualifications as $q): ?>
                                                <tr>
                                                    <td class="fw-semibold text-dark"><?php echo sanitize($q['qualification'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['passing_year'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['roll_no'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['obtained_marks'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['percentage'] ?? '—'); ?>%</td>
                                                    <td><?php echo sanitize($q['subjects'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['school_college_name'] ?? '—'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Group 5: Income, Caste & Domicile Details -->
                        <div class="detail-section-card sec-classes">
                            <div class="detail-section-title">
                                <i class="ph-light ph-files"></i> Income, Caste & Domicile Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Income Application No.</span>
                                        <span class="detail-box-val <?php echo empty($student['income_app_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['income_app_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Caste Application No.</span>
                                        <span class="detail-box-val <?php echo empty($student['caste_app_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['caste_app_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Domicile Application No.</span>
                                        <span class="detail-box-val <?php echo empty($student['domicile_app_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['domicile_app_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 6: Parents Details -->
                        <div class="detail-section-card sec-personal">
                            <div class="detail-section-title">
                                <i class="ph-light ph-users"></i> Parents Details
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th>Details</th>
                                            <th>Mother</th>
                                            <th>Father</th>
                                            <th>Guardian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold">Name</td>
                                            <td class="<?php echo empty($student['mother_name']) ? 'text-muted' : 'fw-semibold text-dark'; ?>"><?php echo sanitize($student['mother_name'] ?? '—'); ?></td>
                                            <td class="<?php echo empty($student['father_name']) ? 'text-muted' : 'fw-semibold text-dark'; ?>"><?php echo sanitize($student['father_name'] ?? '—'); ?></td>
                                            <td class="<?php echo empty($student['guardian_name']) ? 'text-muted' : 'fw-semibold text-dark'; ?>"><?php echo sanitize($student['guardian_name'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Qualification</td>
                                            <td><?php echo sanitize($student['mother_qualification'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_qualification'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_qualification'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Residential Address</td>
                                            <td><?php echo sanitize($student['mother_address'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_address'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_address'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Occupation</td>
                                            <td><?php echo sanitize($student['mother_occupation'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_occupation'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_occupation'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Official Address</td>
                                            <td><?php echo sanitize($student['mother_official_address'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_official_address'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_official_address'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Annual Income</td>
                                            <td><?php echo sanitize($student['mother_income'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_income'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_income'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Email Address</td>
                                            <td><?php echo sanitize($student['mother_email'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_email'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_email'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Mobile No.</td>
                                            <td><?php echo sanitize($student['mother_mobile'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['father_mobile'] ?? '—'); ?></td>
                                            <td><?php echo sanitize($student['guardian_mobile'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Aadhar No.</td>
                                            <td class="mono"><?php echo sanitize($student['mother_aadhar'] ?? '—'); ?></td>
                                            <td class="mono"><?php echo sanitize($student['father_aadhar'] ?? '—'); ?></td>
                                            <td class="mono"><?php echo sanitize($student['guardian_aadhar'] ?? '—'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Group 7: Religion & Category -->
                        <div class="detail-section-card sec-classes">
                            <div class="detail-section-title">
                                <i class="ph-light ph-hand-heart"></i> Religion & Category Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Nationality</span>
                                        <span class="detail-box-val"><?php echo sanitize($student['nationality'] ?? 'INDIAN'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Religion</span>
                                        <span class="detail-box-val <?php echo empty($student['religion']) ? 'empty' : ''; ?>"><?php echo sanitize($student['religion'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Category</span>
                                        <span class="detail-box-val <?php echo empty($student['category']) ? 'empty' : ''; ?>"><?php echo sanitize($student['category'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Caste</span>
                                        <span class="detail-box-val <?php echo empty($student['caste']) ? 'empty' : ''; ?>"><?php echo sanitize($student['caste'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 8: Aadhar & TC Details -->
                        <div class="detail-section-card sec-personal">
                            <div class="detail-section-title">
                                <i class="ph-light ph-identification-badge"></i> Aadhar & Transfer Certificate (TC)
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Aadhar No.</span>
                                        <span class="detail-box-val mono <?php echo empty($student['aadhar_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['aadhar_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">TC Number</span>
                                        <span class="detail-box-val mono <?php echo empty($student['tc_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['tc_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">TC Date of Issue</span>
                                        <span class="detail-box-val"><?php echo !empty($student['tc_issue_date']) ? date('d-M-Y', strtotime($student['tc_issue_date'])) : '—'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 9: Scholarship & Govt Portal IDs -->
                        <div class="detail-section-card sec-classes">
                            <div class="detail-section-title">
                                <i class="ph-light ph-identification-card"></i> Scholarship & Government Portal IDs
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Scholarship ID</span>
                                        <span class="detail-box-val mono <?php echo empty($student['scholarship_id']) ? 'empty' : ''; ?>"><?php echo sanitize($student['scholarship_id'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Govt. Student ID</span>
                                        <span class="detail-box-val mono <?php echo empty($student['govt_student_id']) ? 'empty' : ''; ?>"><?php echo sanitize($student['govt_student_id'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Govt. Family ID</span>
                                        <span class="detail-box-val mono <?php echo empty($student['govt_family_id']) ? 'empty' : ''; ?>"><?php echo sanitize($student['govt_family_id'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Samagra ID</span>
                                        <span class="detail-box-val mono <?php echo empty($student['samagra_id']) ? 'empty' : ''; ?>"><?php echo sanitize($student['samagra_id'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 10: Bank Account Details -->
                        <div class="detail-section-card sec-address">
                            <div class="detail-section-title">
                                <i class="ph-light ph-bank"></i> Bank Account Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Bank Name</span>
                                        <span class="detail-box-val <?php echo empty($student['bank_name']) ? 'empty' : ''; ?>"><?php echo sanitize($student['bank_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Branch</span>
                                        <span class="detail-box-val <?php echo empty($student['bank_branch']) ? 'empty' : ''; ?>"><?php echo sanitize($student['bank_branch'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">IFSC Code</span>
                                        <span class="detail-box-val mono <?php echo empty($student['ifsc_code']) ? 'empty' : ''; ?>"><?php echo sanitize($student['ifsc_code'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Account Holder Name</span>
                                        <span class="detail-box-val <?php echo empty($student['bank_account_holder']) ? 'empty' : ''; ?>"><?php echo sanitize($student['bank_account_holder'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">Account No.</span>
                                        <span class="detail-box-val mono <?php echo empty($student['bank_account_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['bank_account_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label">PAN Number</span>
                                        <span class="detail-box-val mono <?php echo empty($student['pan_no']) ? 'empty' : ''; ?>"><?php echo sanitize($student['pan_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: SIBLINGS -->
                    <div class="tab-pane fade" id="siblings" role="tabpanel" aria-labelledby="siblings-tab">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-users-three"></i> Student Siblings
                        </div>
                        <?php if (empty($siblings)): ?>
                            <div class="p-5 text-center bg-light rounded-3 border">
                                <i class="ph-light ph-users-three text-muted fs-1 mb-3"></i>
                                <h6 class="fw-semibold">No Siblings Linked</h6>
                                <p class="text-xs text-muted mb-0">No other students share the same father/mother name in the platform.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th style="width: 150px;">Admission No.</th>
                                            <th>Student</th>
                                            <th style="width: 260px;">Fees</th>
                                            <th style="width: 150px;">Class</th>
                                            <th style="width: 120px;">Roll No.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <?php foreach ($siblings as $sib):
                                            $sib_balance = floatval($sib['total_fees']) + floatval($sib['fine_amount']) - floatval($sib['total_discount']) - floatval($sib['total_paid']);
                                            $sib_initials = strtoupper(substr($sib['first_name'], 0, 1) . (isset($sib['last_name']) ? substr($sib['last_name'], 0, 1) : ''));
                                        ?>
                                            <tr>
                                                <td class="mono font-semibold text-dark"><?php echo sanitize(($sib['admission_no_prefix'] ?? '') . $sib['admission_no']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if (!empty($sib['photo']) && file_exists('../../../' . $sib['photo'])): ?>
                                                            <img src="<?php echo BASE_URL . sanitize($sib['photo']); ?>" class="student-avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" alt="Photo">
                                                        <?php else: ?>
                                                            <div class="student-avatar-placeholder" style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: #f1f5f9; color: #475569; font-weight: 700; font-size: 11px;">
                                                                <?php echo $sib_initials; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <a href="view.php?id=<?php echo $sib['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                                            <?php echo sanitize($sib['first_name'] . ' ' . $sib['last_name']); ?>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fee-breakdown-box py-1.5 px-2 text-xxs" style="background-color: #f8fafc; border-radius: 6px; border: 1px solid var(--color-border); max-width: 220px; line-height: 1.45;">
                                                        <div class="d-flex justify-content-between mb-0.5">
                                                            <span class="text-muted">Total Fees:</span>
                                                            <span class="fw-semibold text-dark"><?php echo number_format($sib['total_fees'], 2); ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-0.5">
                                                            <span class="text-muted">Total Paid:</span>
                                                            <span class="fw-semibold text-success"><?php echo number_format($sib['total_paid'], 2); ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Total Balance:</span>
                                                            <span class="fw-bold text-danger"><?php echo number_format($sib_balance, 2); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo sanitize(($sib['class_name'] ?? '') . ' - ' . ($sib['section_name'] ?? '')); ?></td>
                                                <td class="mono"><?php echo sanitize($sib['roll_no'] ?? '—'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB: FEES -->
                    <div class="tab-pane fade" id="fees" role="tabpanel" aria-labelledby="fees-tab">

                        <!-- Row 1: Fees Summary & Fees follow ups -->
                        <div class="row g-4 mb-4">
                            <div class="col-lg-4">
                                <div class="detail-section-card h-100 m-0">
                                    <div class="detail-section-title">
                                        <i class="ph-light ph-coins"></i> Fees Summary
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless align-middle mb-0 text-xs text-dark" style="font-weight: 500;">
                                            <tbody>
                                                <tr style="border-bottom: 1px solid var(--color-border);">
                                                    <td class="text-muted py-2">Total Fees</td>
                                                    <td class="text-end fw-semibold py-2"><?php echo number_format($student['total_fees'], 2); ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid var(--color-border);">
                                                    <td class="text-muted py-2">Total Fine</td>
                                                    <td class="text-end fw-semibold text-danger py-2"><?php echo number_format($student['fine_amount'], 2); ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid var(--color-border);">
                                                    <td class="text-muted py-2">Total Discount</td>
                                                    <td class="text-end fw-semibold text-info py-2"><?php echo number_format($student['total_discount'], 2); ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid var(--color-border);">
                                                    <td class="text-muted py-2">Total Paid Fees</td>
                                                    <td class="text-end fw-semibold text-success py-2"><?php echo number_format($student['total_paid'], 2); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Total Balance Fees</td>
                                                    <td class="text-end py-2">
                                                        <?php if ($fee_balance <= 0): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold">No Balance</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold"><?php echo number_format($fee_balance, 2); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="detail-section-card h-100 m-0">
                                    <div class="detail-section-title">
                                        <i class="ph-light ph-calendar"></i> Fees follow ups
                                    </div>
                                    <div class="table-responsive">
                                        <table class="teacher-detail-table w-100">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50px;">#</th>
                                                    <th>Remark</th>
                                                    <th>Reminder Date</th>
                                                    <th>Added By</th>
                                                    <th>Added On</th>
                                                    <th>Updated On</th>
                                                    <th>Notification</th>
                                                    <th style="width: 80px; text-align: center;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-xs">
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">No data available in table</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Fees Structure Card -->
                        <div class="detail-section-card mb-4">
                            <div class="detail-section-title">
                                <i class="ph-light ph-table"></i> Fees Structure
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th>Fees Type</th>
                                            <th>Total Fees</th>
                                            <th>Discount Head</th>
                                            <th>Gross Total Fees</th>
                                            <th>Fine</th>
                                            <th>Paid Fees</th>
                                            <th>Discount</th>
                                            <th>Balance Fees</th>
                                            <th>Last Updated</th>
                                            <th>Created At</th>
                                            <th style="width: 100px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <?php if (empty($fee_items)): ?>
                                            <tr>
                                                <td colspan="11" class="text-center text-muted py-4">No fee items assigned yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($fee_items as $item):
                                                $gross = $item['amount'] - $item['discount_amount'];
                                                $balance = $gross - $item['paid_amount'];
                                            ?>
                                                <tr>
                                                    <td class="fw-semibold text-dark"><?php echo sanitize($item['fee_name']); ?></td>
                                                    <td><?php echo number_format($item['amount'], 2); ?></td>
                                                    <td class="text-info"><?php echo sanitize($item['discount_type'] ?? '—'); ?></td>
                                                    <td class="fw-semibold"><?php echo number_format($gross, 2); ?></td>
                                                    <td>0.00</td>
                                                    <td class="text-success"><?php echo number_format($item['paid_amount'], 2); ?></td>
                                                    <td class="text-info"><?php echo number_format($item['discount_amount'], 2); ?></td>
                                                    <td class="fw-bold <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                                        <?php echo number_format(max(0, $balance), 2); ?>
                                                    </td>
                                                    <td><?php echo date('d-M-Y h:i A', strtotime($item['updated_at'])); ?></td>
                                                    <td><?php echo date('d-M-Y h:i A', strtotime($item['created_at'])); ?></td>
                                                    <td class="text-center">
                                                        <a href="#" class="text-danger me-2" title="Delete"><i class="ph-bold ph-trash fs-6"></i></a>
                                                        <a href="#" class="text-primary" title="Sync/Restore"><i class="ph-bold ph-arrows-counter-clockwise fs-6"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Row 3: Fees Log Card -->
                        <div class="detail-section-card">
                            <div class="detail-section-title d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="ph-light ph-clock-counter-clockwise"></i> Fees Log
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn-admin-secondary py-1 px-2.5 text-xxs d-flex align-items-center gap-1" style="height: auto; font-size: 11px;">
                                        <i class="ph-bold ph-printer"></i> Print
                                    </button>
                                    <button class="btn-admin-secondary py-1 px-2.5 text-xxs d-flex align-items-center gap-1" style="height: auto; font-size: 11px;">
                                        <i class="ph-bold ph-file-xls"></i> Excel
                                    </button>
                                    <button class="btn-admin-secondary py-1 px-2.5 text-xxs d-flex align-items-center gap-1" style="height: auto; font-size: 11px;">
                                        <i class="ph-bold ph-file-pdf"></i> PDF
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100 text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Receipt No.</th>
                                            <th>Fees</th>
                                            <th>Fine</th>
                                            <th>Total</th>
                                            <th>Paid Fees (Current)</th>
                                            <th>Fees Discount</th>
                                            <th>Fine Discount</th>
                                            <th>Total Discount</th>
                                            <th>Balance Fees</th>
                                            <th>Fees Type</th>
                                            <th>Payment Mode</th>
                                            <th>Reference No.</th>
                                            <th>Screenshot</th>
                                            <th>Remark</th>
                                            <th>Txn ID</th>
                                            <th>Received At</th>
                                            <th>Added By</th>
                                            <th>Added At</th>
                                            <th>Updated By</th>
                                            <th>Updated At</th>
                                            <th style="width: 80px; text-align: center;">Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <?php if (empty($payments)): ?>
                                            <tr>
                                                <td colspan="21" class="text-center text-muted py-4">No payments recorded yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($payments as $payment):
                                                $receipt_no = 'RCP-' . date('Y', strtotime($payment['payment_date'])) . '-' . str_pad($payment['id'], 4, '0', STR_PAD_LEFT);
                                                $fees_portion = $payment['amount_paid'] - $payment['fine_amount'];
                                                $total_portion = $payment['amount_paid'];
                                            ?>
                                                <tr>
                                                    <td class="fw-bold text-dark"><?php echo $receipt_no; ?></td>
                                                    <td><?php echo number_format($fees_portion, 2); ?></td>
                                                    <td><?php echo number_format($payment['fine_amount'], 2); ?></td>
                                                    <td class="fw-semibold"><?php echo number_format($total_portion, 2); ?></td>
                                                    <td class="text-success fw-semibold"><?php echo number_format($payment['amount_paid'], 2); ?></td>
                                                    <td>0.00</td>
                                                    <td>0.00</td>
                                                    <td>0.00</td>
                                                    <td class="fw-bold"><?php echo number_format(max(0, $fee_balance), 2); ?></td>
                                                    <td><span class="badge bg-secondary-subtle text-secondary px-2 py-0.5 rounded">Fees Payment</span></td>
                                                    <td><span class="badge bg-info-subtle text-info px-2 py-0.5 rounded"><?php echo sanitize($payment['payment_method']); ?></span></td>
                                                    <td class="mono"><?php echo sanitize($payment['transaction_id'] ?? '—'); ?></td>
                                                    <td>
                                                        <?php if (!empty($payment['screenshot'])): ?>
                                                            <a href="<?php echo BASE_URL . sanitize($payment['screenshot']); ?>" target="_blank" class="d-inline-flex align-items-center gap-1 badge bg-primary-subtle text-primary border-0 py-1 px-2 text-xxs">
                                                                <i class="ph-light ph-image"></i> View
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-wrap" style="max-width: 150px;"><?php echo sanitize($payment['remarks'] ?? '—'); ?></td>
                                                    <td class="mono"><?php echo sanitize($payment['transaction_id'] ?? '—'); ?></td>
                                                    <td><?php echo date('d-M-Y h:i A', strtotime($payment['payment_date'])); ?></td>
                                                    <td>Admin</td>
                                                    <td><?php echo date('d-M-Y h:i A', strtotime($payment['created_at'])); ?></td>
                                                    <td>—</td>
                                                    <td>—</td>
                                                    <td class="text-center">
                                                        <a href="#" class="text-warning me-2" title="Edit Receipt"><i class="ph-bold ph-pencil-simple fs-6"></i></a>
                                                        <a href="#" class="text-primary" title="View/Download Receipt"><i class="ph-bold ph-file-text fs-6"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: DELETED FEES -->
                    <div class="tab-pane fade" id="deleted-fees" role="tabpanel" aria-labelledby="deleted-fees-tab">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-trash-simple"></i> Deleted Fees Structures
                        </div>
                        <div class="table-responsive">
                            <table class="teacher-detail-table w-100">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Structure Name</th>
                                        <th>Total Fees</th>
                                        <th>Head Discount</th>
                                        <th>Gross Total Fees</th>
                                        <th>Paid</th>
                                        <th>Deleted By</th>
                                        <th>Deleted Remark</th>
                                        <th>Deleted At</th>
                                        <th>Created At</th>
                                        <th style="width: 80px; text-align: center;">Restore</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs">
                                    <?php if (empty($deleted_fee_items)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-4">No deleted fee structures recorded yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $counter = 1;
                                        foreach ($deleted_fee_items as $item):
                                            $gross = $item['amount'] - $item['discount_amount'];
                                        ?>
                                            <tr>
                                                <td class="mono"><?php echo $counter++; ?></td>
                                                <td class="fw-semibold text-dark"><?php echo sanitize($item['fee_name']); ?></td>
                                                <td><?php echo number_format($item['amount'], 2); ?></td>
                                                <td class="text-info"><?php echo sanitize($item['discount_type'] ?? '—'); ?></td>
                                                <td class="fw-semibold"><?php echo number_format($gross, 2); ?></td>
                                                <td class="text-success"><?php echo number_format($item['paid_amount'], 2); ?></td>
                                                <td>Admin</td>
                                                <td class="text-wrap" style="max-width: 150px;"><?php echo sanitize($item['remark'] ?? '—'); ?></td>
                                                <td><?php echo date('d-M-Y h:i A', strtotime($item['updated_at'])); ?></td>
                                                <td><?php echo date('d-M-Y h:i A', strtotime($item['created_at'])); ?></td>
                                                <td class="text-center">
                                                    <a href="#" class="text-primary" title="Restore"><i class="ph-bold ph-arrows-counter-clockwise fs-6"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB: INVENTORY -->
                    <div class="tab-pane fade" id="inventory" role="tabpanel" aria-labelledby="inventory-tab">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-package"></i> Inventory
                        </div>

                        <!-- Row 1: Inventory Summary -->
                        <div class="row g-4 mb-4">
                            <div class="col-lg-4 col-md-6">
                                <div class="detail-section-card m-0">
                                    <div class="detail-section-title">
                                        <i class="ph-light ph-calculator"></i> Inventory Summary
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless align-middle mb-0 text-xs text-dark" style="font-weight: 500;">
                                            <tbody>
                                                <tr style="border-bottom: 1px solid var(--color-border);">
                                                    <td class="text-muted py-2">Total Sales</td>
                                                    <td class="text-end fw-semibold py-2">0.00</td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid var(--color-border);">
                                                    <td class="text-muted py-2">Total Paid</td>
                                                    <td class="text-end fw-semibold text-success py-2">0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Total Balance</td>
                                                    <td class="text-end fw-semibold text-danger py-2">0.00</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Sales -->
                        <div class="detail-section-card mb-4">
                            <div class="detail-section-title">
                                <i class="ph-light ph-receipt"></i> Sales
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th>Invoice No.</th>
                                            <th>Invoice Date</th>
                                            <th>Products</th>
                                            <th>Total Amount</th>
                                            <th>Total Paid</th>
                                            <th>Total Balance</th>
                                            <th style="width: 100px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">No Inventory Found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Row 3: Payment Logs -->
                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-clock-counter-clockwise"></i> Payment Logs
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th>Invoice No.</th>
                                            <th>Paid On</th>
                                            <th>Paid Amount</th>
                                            <th>Payment Mode</th>
                                            <th>UTR</th>
                                            <th>Screenshot</th>
                                            <th>Remarks</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">No payments recorded yet.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: DOWNLOAD ID CARD -->
                    <div class="tab-pane fade" id="idcard" role="tabpanel" aria-labelledby="idcard-tab">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-identification-card"></i> Student ID Card
                        </div>
                        <p class="text-xs text-muted mb-4">Printable smart student identity card layout. Direct download ready.</p>

                        <div class="d-flex flex-column align-items-center mb-4">
                            <!-- Premium CSS ID Card -->
                            <div class="student-id-card border rounded-3 p-3 text-center shadow-sm">
                                <!-- Badge Background Pattern -->
                                <div class="id-card-top-decoration"></div>
                                <h6 class="text-uppercase fw-bold text-xs mb-1 id-card-header-label">SchoolSaaS ERP</h6>
                                <p class="text-xxs text-white-50 mb-3">SaaS Academy Excellence</p>

                                <div class="mx-auto mb-3 id-card-avatar-container">
                                    <?php if (!empty($student['photo'])): ?>
                                        <img src="<?php echo BASE_URL . sanitize($student['photo']); ?>" alt="ID Card Photo" class="id-card-img">
                                    <?php else: ?>
                                        <div class="text-dark fw-bold fs-5 id-card-avatar-placeholder">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'] ?? '', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h5 class="fw-bold fs-6 mb-1 text-white"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                                <span class="badge bg-accent text-dark mb-3 px-2 py-0.5 fw-bold text-xxs">Student</span>

                                <div class="text-start text-xxs text-light px-2 py-2 mb-3 rounded id-card-info-box">
                                    <div class="row g-1">
                                        <div class="col-5 text-white-50">Roll No:</div>
                                        <div class="col-7 fw-bold"><?php echo sanitize($student['roll_no'] ?? '—'); ?></div>

                                        <div class="col-5 text-white-50">Class:</div>
                                        <div class="col-7 fw-bold"><?php echo sanitize($student['class_name'] ?? '—') . ' - ' . sanitize($student['section_name'] ?? '—'); ?></div>

                                        <div class="col-5 text-white-50">Blood Group:</div>
                                        <div class="col-7 fw-bold text-danger"><?php echo sanitize($student['blood_group'] ?? '—'); ?></div>

                                        <div class="col-5 text-white-50">Emergency:</div>
                                        <div class="col-7 fw-bold"><?php echo sanitize($student['mobile_no'] ?? '—'); ?></div>
                                    </div>
                                </div>

                                <div class="pt-2 border-top border-secondary">
                                    <!-- Barcode representation -->
                                    <div class="bg-white mx-auto rounded p-1 mb-1 id-card-barcode-container">
                                        <div class="id-card-barcode-lines"></div>
                                    </div>
                                    <span class="text-xxs text-white-50 mono">RFID: <?php echo sanitize($student['biometric_code'] ?? 'BIO-99021'); ?></span>
                                </div>
                            </div>

                            <button type="button" class="btn btn-primary btn-sm mt-4 print-page-trigger">
                                <i class="ph-light ph-printer"></i> Print / Download ID Card
                            </button>
                        </div>
                    </div>

                    <!-- TAB 2: ATTENDANCE -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                        <!-- Header with Title, Stats and Filters -->
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap g-3">
                            <div>
                                <h4 class="mb-1 fw-bold text-dark font-heading" style="font-size: var(--text-lg);"><?php echo date('M, Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?></h4>
                                <div class="text-xs text-secondary font-primary" style="font-size: var(--text-xs); font-weight: 500;">
                                    <strong>Present:</strong> <span class="text-success"><?php echo $count_present; ?></span>, 
                                    <strong>Absent:</strong> <span class="text-danger"><?php echo $count_absent; ?></span>, 
                                    <strong>Pending:</strong> <span class="text-warning"><?php echo $count_pending; ?></span>, 
                                    <strong>Holidays:</strong> <span class="text-info"><?php echo $count_holidays; ?></span>, 
                                    <strong>Leave:</strong> <span class="text-primary"><?php echo $count_leave; ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <select id="attendanceMonthSelect" data-student-id="<?php echo $student['id']; ?>" class="form-select form-select-sm" style="width: 130px; font-size: var(--text-xs); border-radius: var(--radius-xs); padding: 6px 12px; border: 1px solid var(--color-border); font-family: var(--font-primary); font-weight: 500; cursor: pointer; color: var(--color-text-secondary); background-color: var(--color-surface);">
                                    <?php
                                    for ($m = 1; $m <= 12; $m++) {
                                        $m_name = date('F', mktime(0, 0, 0, $m, 1));
                                        $m_short = date('M', mktime(0, 0, 0, $m, 1));
                                        $selected = ($m == $selected_month) ? 'selected' : '';
                                        echo "<option value='{$m}' {$selected}>{$m_short}</option>";
                                    }
                                    ?>
                                </select>
                                <select id="attendanceYearSelect" data-student-id="<?php echo $student['id']; ?>" class="form-select form-select-sm d-none" style="width: 90px;">
                                    <?php
                                    $curr_year = intval(date('Y'));
                                    for ($y = $curr_year - 5; $y <= $curr_year + 5; $y++) {
                                        $selected = ($y == $selected_year) ? 'selected' : '';
                                        echo "<option value='{$y}' {$selected}>{$y}</option>";
                                    }
                                    ?>
                                </select>
                                <button type="button" onclick="downloadAttendanceCSV()" class="btn btn-sm btn-primary d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; border-radius: var(--radius-xs); padding: 0; background-color: var(--color-accent); border-color: var(--color-accent); transition: var(--transition);" title="Download CSV">
                                    <i class="ph-bold ph-cloud-arrow-down fs-5 text-white"></i>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="attendanceTable" class="teacher-detail-table w-100" style="border: 1px solid var(--color-border); border-collapse: collapse; background-color: var(--color-surface);">
                                <thead>
                                    <tr style="background-color: #f1f8f5;">
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Date</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Day</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Status</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Punch In Time</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Punch Out Time</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Working Hours</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Late/Half Day</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Punch Mode</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Leave/Holiday</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Remark</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Marked On</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs font-primary">
                                    <?php
                                    $days_in_month = date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
                                    for ($day = 1; $day <= $days_in_month; $day++):
                                        $date_str = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
                                        $day_name = date('D', mktime(0, 0, 0, $selected_month, $day, $selected_year));
                                        $day_num_str = sprintf('%02d', $day);
                                        
                                        $has_record = isset($attendance_by_date[$date_str]);
                                        $rec = $has_record ? $attendance_by_date[$date_str] : null;
                                        
                                        $status_html = '';
                                        $punch_in = '';
                                        $punch_out = '';
                                        $working_hours = '';
                                        $late_half_day = '';
                                        $punch_mode = '';
                                        $leave_holiday = '';
                                        $remark = '';
                                        $marked_on = '';
                                        
                                        if ($has_record) {
                                            // Status Badge
                                            if ($rec['status'] === 'present') {
                                                $status_html = '<span style="background-color: #e6fcf5; color: #0ca678; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 11.5px; border: 1px solid #c3fae8; display: inline-block;">Present</span>';
                                            } elseif ($rec['status'] === 'absent') {
                                                $status_html = '<span style="background-color: #fff4e6; color: #f76707; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 11.5px; border: 1px solid #ffe8cc; display: inline-block;">Absent</span>';
                                            } elseif ($rec['status'] === 'late') {
                                                $status_html = '<span style="background-color: #fff9db; color: #f59f00; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 11.5px; border: 1px solid #fff3bf; display: inline-block;">Late</span>';
                                            } elseif ($rec['status'] === 'half_day') {
                                                $status_html = '<span style="background-color: #e3fafc; color: #0c8599; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 11.5px; border: 1px solid #c5f6fa; display: inline-block;">Half Day</span>';
                                            } elseif ($rec['status'] === 'leave') {
                                                $status_html = '<span style="background-color: #e7f5ff; color: #1c7ed6; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 11.5px; border: 1px solid #d0ebff; display: inline-block;">Leave</span>';
                                            }
                                            
                                            $punch_in = $rec['check_in'] ? date('h:i A', strtotime($rec['check_in'])) : '';
                                            $punch_out = $rec['check_out'] ? date('h:i A', strtotime($rec['check_out'])) : '';
                                            
                                            // Calculate Working Hours
                                            if ($rec['check_in'] && $rec['check_out']) {
                                                $t1 = strtotime($rec['check_in']);
                                                $t2 = strtotime($rec['check_out']);
                                                $diff = $t2 - $t1;
                                                if ($diff > 0) {
                                                    $hours = floor($diff / 3600);
                                                    $minutes = floor(($diff % 3600) / 60);
                                                    $working_hours = "{$hours}h {$minutes}m";
                                                }
                                            }
                                            
                                            // Late / Half Day description
                                            if ($rec['status'] === 'late') {
                                                $late_half_day = 'Late';
                                            } elseif ($rec['status'] === 'half_day') {
                                                $late_half_day = 'Half Day';
                                            }
                                            
                                            $punch_mode = 'ERP';
                                            
                                            if ($rec['status'] === 'leave') {
                                                $leave_holiday = sanitize($rec['leave_type'] ?? 'Leave');
                                            }
                                            
                                            $remark = sanitize($rec['leave_reason'] ?? '');
                                            $marked_on = date('d M, Y', strtotime($rec['created_at']));
                                        }
                                    ?>
                                        <tr style="border-bottom: 1px solid var(--color-border);">
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary); font-weight: 500;"><?php echo $day_num_str; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $day_name; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border);"><?php echo $status_html; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);" class="mono"><?php echo $punch_in; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);" class="mono"><?php echo $punch_out; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $working_hours; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $late_half_day; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $punch_mode; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $leave_holiday; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $remark; ?></td>
                                            <td style="padding: 8px 10px; border: 1px solid var(--color-border); color: var(--color-text-secondary);"><?php echo $marked_on; ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- TAB: ADMIT CARDS -->
                    <div class="tab-pane fade" id="admitcards" role="tabpanel" aria-labelledby="admitcards-tab" data-admit-cards="<?php echo htmlspecialchars(json_encode($admit_cards_data), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-ticket"></i> Examination Admit Cards
                        </div>

                        <!-- Datatable Controls -->
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap g-3 font-primary text-xs" style="font-weight: 500;">
                            <div class="d-flex align-items-center gap-1">
                                Show
                                <select id="admitCardsLengthSelect" class="form-select form-select-sm" style="width: auto; padding: 2px 8px; border-radius: 4px; border: 1px solid var(--color-border); font-size: var(--text-xs); color: var(--color-text-secondary); cursor: pointer; background-color: var(--color-surface);">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                entries
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                Search:
                                <input type="search" id="admitCardsSearchInput" class="form-control form-control-sm" style="width: 180px; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--color-border); font-size: var(--text-xs); background-color: var(--color-surface); color: var(--color-text-primary);">
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table id="admitCardsTable" class="teacher-detail-table w-100" style="border: 1px solid var(--color-border); border-collapse: collapse; background-color: var(--color-surface);">
                                <thead>
                                    <tr style="background-color: #f1f8f5;">
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 60px;">#</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Title</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Session</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Status</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Created At</th>
                                        <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 120px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs font-primary">
                                    <!-- Populated via Javascript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Datatable Footer -->
                        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap g-3 font-primary text-xs text-secondary" style="font-weight: 500;">
                            <div id="admitCardsInfo">
                                Showing 0 to 0 of 0 entries
                            </div>
                            <div class="d-flex gap-1" id="admitCardsPagination">
                                <!-- Populated via Javascript -->
                            </div>
                        </div>
                    </div>

                    <!-- TAB: SUBJECTS -->
                    <!-- TAB: SUBJECTS -->
                    <div class="tab-pane fade" id="subjects" role="tabpanel" aria-labelledby="subjects-tab">
                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-book-open"></i> Subjects
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100" style="border: 1px solid var(--color-border); border-collapse: collapse; background-color: var(--color-surface);">
                                    <thead>
                                        <tr style="background-color: #f1f8f5;">
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 60px; text-align: center;">#</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 120px;">Code</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Name</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 120px;">Type</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 150px;">Weekly Classes</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Teacher</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 120px; text-align: center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <?php
                                        $assigned_subjects = [
                                            [
                                                'code' => 'PHY-301',
                                                'name' => 'PHYSICS',
                                                'type' => 'grade',
                                                'classes' => '5 Periods',
                                                'teacher' => 'Mr. Karan Kumar',
                                                'initials' => 'KK',
                                                'status' => 'Core'
                                            ],
                                            [
                                                'code' => 'MTH-302',
                                                'name' => 'MATH',
                                                'type' => 'grade',
                                                'classes' => '6 Periods',
                                                'teacher' => 'Ms. Sneha Patel',
                                                'initials' => 'SP',
                                                'status' => 'Core'
                                            ],
                                            [
                                                'code' => 'ENG-303',
                                                'name' => 'ENGLISH',
                                                'type' => 'grade',
                                                'classes' => '4 Periods',
                                                'teacher' => 'Ms. Anjali Sharma',
                                                'initials' => 'AS',
                                                'status' => 'Core'
                                            ],
                                            [
                                                'code' => 'ENG-304',
                                                'name' => 'English',
                                                'type' => 'discipline',
                                                'classes' => '2 Periods',
                                                'teacher' => 'Ms. Anjali Sharma',
                                                'initials' => 'AS',
                                                'status' => 'Co-Scholastic'
                                            ],
                                            [
                                                'code' => 'HIN-305',
                                                'name' => 'Hindi',
                                                'type' => 'grade',
                                                'classes' => '4 Periods',
                                                'teacher' => 'Mr. Vijay Singh',
                                                'initials' => 'VS',
                                                'status' => 'Core'
                                            ]
                                        ];

                                        $idx = 1;
                                        foreach ($assigned_subjects as $sub):
                                            $type_badge = ($sub['type'] === 'grade')
                                                ? 'bg-success-subtle text-success border border-success-subtle'
                                                : 'bg-warning-subtle text-warning border border-warning-subtle';
                                            $status_badge = ($sub['status'] === 'Core')
                                                ? 'bg-primary-subtle text-primary border border-primary-subtle'
                                                : 'bg-info-subtle text-info border border-info-subtle';
                                        ?>
                                            <tr>
                                                <td style="padding: 10px; border: 1px solid var(--color-border); text-align: center;"><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                                <td style="padding: 10px; border: 1px solid var(--color-border);" class="mono fw-bold"><?php echo htmlspecialchars($sub['code']); ?></td>
                                                <td style="padding: 10px; border: 1px solid var(--color-border);" class="fw-semibold text-dark"><?php echo htmlspecialchars($sub['name']); ?></td>
                                                <td style="padding: 10px; border: 1px solid var(--color-border);"><span class="badge <?php echo $type_badge; ?> px-2 py-0.5 rounded"><?php echo htmlspecialchars($sub['type']); ?></span></td>
                                                <td style="padding: 10px; border: 1px solid var(--color-border);"><?php echo htmlspecialchars($sub['classes']); ?></td>
                                                <td style="padding: 10px; border: 1px solid var(--color-border);">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="student-avatar-placeholder" style="width: 24px; height: 24px; font-size: 9px;"><?php echo htmlspecialchars($sub['initials']); ?></div>
                                                        <span><?php echo htmlspecialchars($sub['teacher']); ?></span>
                                                    </div>
                                                </td>
                                                <td style="padding: 10px; border: 1px solid var(--color-border); text-align: center;"><span class="badge <?php echo $status_badge; ?> px-2.5 py-0.5 rounded-pill fw-bold" style="font-size: 10px;"><?php echo htmlspecialchars($sub['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: OFFLINE TESTS -->
                    <div class="tab-pane fade" id="offlinetests" role="tabpanel" aria-labelledby="offlinetests-tab">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-file-text"></i> Offline Tests & Class Assessments
                        </div>
                        <div class="table-responsive">
                            <table class="teacher-detail-table w-100">
                                <thead>
                                    <tr>
                                        <th>Test Date</th>
                                        <th>Test Name</th>
                                        <th>Subject</th>
                                        <th>Max Marks</th>
                                        <th>Marks Obtained</th>
                                        <th>Class Avg</th>
                                        <th>Result / Grade</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs">
                                    <tr>
                                        <td><?php echo date('d-M-Y', strtotime('-4 days')); ?></td>
                                        <td class="fw-semibold text-dark">Unit Test 1 (Algebra)</td>
                                        <td>Mathematics</td>
                                        <td>25</td>
                                        <td class="fw-bold text-success">23</td>
                                        <td>18</td>
                                        <td><span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded">A+</span></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo date('d-M-Y', strtotime('-2 weeks')); ?></td>
                                        <td class="fw-semibold text-dark">Grammar Monthly Test</td>
                                        <td>English</td>
                                        <td>50</td>
                                        <td class="fw-bold text-success">44</td>
                                        <td>36</td>
                                        <td><span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded">A</span></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo date('d-M-Y', strtotime('-3 weeks')); ?></td>
                                        <td class="fw-semibold text-dark">Chemical Properties Class Test</td>
                                        <td>Science</td>
                                        <td>20</td>
                                        <td class="fw-bold text-warning">15</td>
                                        <td>14</td>
                                        <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 rounded">B</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB: MARKSHEETS -->
                    <div class="tab-pane fade" id="marksheets" role="tabpanel" aria-labelledby="marksheets-tab">
                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-certificate"></i> Marksheets
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100" style="border: 1px solid var(--color-border); border-collapse: collapse; background-color: var(--color-surface);">
                                    <thead>
                                        <tr style="background-color: #f1f8f5;">
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 60px; text-align: center;">S.No.</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Session</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Class</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Obt. Marks</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Max. Marks</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Percentage</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Result</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Rank</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Performance</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Last Updated</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Created At</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); text-align: center; width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <tr>
                                            <td colspan="12" class="text-center text-muted py-4" style="padding: 20px; border: 1px solid var(--color-border);">No marksheets found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: HOMEWORK -->
                    <div class="tab-pane fade" id="homework" role="tabpanel" aria-labelledby="homework-tab">
                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-house-line"></i> Homework
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100" style="border: 1px solid var(--color-border); border-collapse: collapse; background-color: var(--color-surface);">
                                    <thead>
                                        <tr style="background-color: #f1f8f5;">
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading); width: 60px; text-align: center;">S.No.</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Title</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Subject</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Class</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Session</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Submission Date</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Assigned By</th>
                                            <th style="padding: 10px; font-weight: 600; color: var(--color-text-primary); font-size: var(--text-xs); border: 1px solid var(--color-border); font-family: var(--font-heading);">Assigned At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4" style="padding: 20px; border: 1px solid var(--color-border);">No homework found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: ACTIVITY -->
                    <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">

                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-activity"></i> Activities
                            </div>
                            <div class="table-responsive">
                                <table class="activity-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px; text-align: center;">#</th>
                                            <th style="width: 260px;">Date</th>
                                            <th style="width: 140px;">Event</th>
                                            <th style="width: 100px;">Old</th>
                                            <th>New</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Define the fields and values in the exact order requested by the user
                                        $activity_fields = [
                                            'First Name' => !empty($student['first_name']) ? $student['first_name'] : 'aa',
                                            'Type' => 'old',
                                            'Last Name' => !empty($student['last_name']) ? $student['last_name'] : '',
                                            'Mobile' => !empty($student['mobile_no']) ? $student['mobile_no'] : '',
                                            'Whatsapp' => !empty($student['whatsapp_no']) ? $student['whatsapp_no'] : '',
                                            'Email' => !empty($student['email']) ? $student['email'] : '',
                                            'City' => 'test',
                                            'State' => 'test',
                                            'Country' => 'test',
                                            'Pincode' => 'test',
                                            'Address' => !empty($student['father_address']) ? $student['father_address'] : '',
                                            'Section' => !empty($student['section_name']) ? $student['section_name'] : 'A',
                                            'Username' => !empty($student['u_name']) ? $student['u_name'] : '',
                                            'Registration No' => !empty($student['registration_no']) ? $student['registration_no'] : '101',
                                            'Pen No' => !empty($student['pen_no']) ? $student['pen_no'] : '',
                                            'Pan No' => !empty($student['pan_no']) ? $student['pan_no'] : '',
                                            'Enrollment No' => !empty($student['enrollment_no']) ? $student['enrollment_no'] : '',
                                            'Admission No' => !empty($student['admission_no']) ? $student['admission_no'] : '',
                                            'Sr No' => !empty($student['sr_no']) ? $student['sr_no'] : '',
                                            'Srn No' => !empty($student['srn_no']) ? $student['srn_no'] : '',
                                            'Admission Date' => !empty($student['admission_date']) ? date('d-m-Y', strtotime($student['admission_date'])) : '',
                                            'Roll No' => !empty($student['roll_no']) ? $student['roll_no'] : '',
                                            'Blood Group' => !empty($student['blood_group']) ? $student['blood_group'] : '',
                                            'Height' => !empty($student['height']) ? $student['height'] : '',
                                            'Weight' => !empty($student['weight']) ? $student['weight'] : '',
                                            'DOB' => !empty($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : '',
                                            'Mother Name' => !empty($student['mother_name']) ? $student['mother_name'] : '',
                                            'Father Name' => !empty($student['father_name']) ? $student['father_name'] : '',
                                            'Mother Qualification' => !empty($student['mother_qualification']) ? $student['mother_qualification'] : '',
                                            'Father Qualification' => !empty($student['father_qualification']) ? $student['father_qualification'] : '',
                                            'Mother Residential Address' => !empty($student['mother_address']) ? $student['mother_address'] : '',
                                            'Father Residential Address' => !empty($student['father_address']) ? $student['father_address'] : '',
                                            'Mother Occupation' => !empty($student['mother_occupation']) ? $student['mother_occupation'] : '',
                                            'Father Occupation' => !empty($student['father_occupation']) ? $student['father_occupation'] : '',
                                            'Mother Official Address' => !empty($student['mother_official_address']) ? $student['mother_official_address'] : '',
                                            'Father Official Address' => !empty($student['father_official_address']) ? $student['father_official_address'] : '',
                                            'Mother Income' => !empty($student['mother_income']) ? $student['mother_income'] : '',
                                            'Father Income' => !empty($student['father_income']) ? $student['father_income'] : '',
                                            'Mother Email' => !empty($student['mother_email']) ? $student['mother_email'] : '',
                                            'Father Email' => !empty($student['father_email']) ? $student['father_email'] : '',
                                            'Mother Mobile' => !empty($student['mother_mobile']) ? $student['mother_mobile'] : '',
                                            'Father Mobile' => !empty($student['father_mobile']) ? $student['father_mobile'] : '',
                                            'Nationality' => !empty($student['nationality']) ? $student['nationality'] : 'indian',
                                            'Religion' => !empty($student['religion']) ? $student['religion'] : '',
                                            'Category' => !empty($student['category']) ? $student['category'] : '',
                                            'Aadhar No' => !empty($student['aadhar_no']) ? $student['aadhar_no'] : '',
                                            'Attended School' => '',
                                            'Attended Class' => '',
                                            'School Affiliated' => '',
                                            'TC No' => !empty($student['tc_no']) ? $student['tc_no'] : '',
                                            'TC Date' => !empty($student['tc_issue_date']) ? date('d-m-Y', strtotime($student['tc_issue_date'])) : '',
                                            'Caste' => !empty($student['caste']) ? $student['caste'] : '',
                                            'Medium' => !empty($student['education_medium']) ? $student['education_medium'] : '',
                                            'Domicile Application No' => !empty($student['domicile_app_no']) ? $student['domicile_app_no'] : '',
                                            'Caste Application No' => !empty($student['caste_app_no']) ? $student['caste_app_no'] : '',
                                            'Income Application No' => !empty($student['income_app_no']) ? $student['income_app_no'] : '',
                                            'Is RTE Student' => !empty($student['is_rte']) ? ($student['is_rte'] === 'yes' ? '1' : '0') : '0',
                                            'RTE Application No' => '',
                                            'Alternate Number' => !empty($student['alternate_no']) ? $student['alternate_no'] : '',
                                            'Guardian Name' => !empty($student['guardian_name']) ? $student['guardian_name'] : '',
                                            'Guardian Qualification' => !empty($student['guardian_qualification']) ? $student['guardian_qualification'] : '',
                                            'Guardian Residential Address' => !empty($student['guardian_address']) ? $student['guardian_address'] : '',
                                            'Guardian Occupation' => !empty($student['guardian_occupation']) ? $student['guardian_occupation'] : '',
                                            'Guardian Official Address' => !empty($student['guardian_official_address']) ? $student['guardian_official_address'] : '',
                                            'Guardian Income' => !empty($student['guardian_income']) ? $student['guardian_income'] : '',
                                            'Guardian Email' => !empty($student['guardian_email']) ? $student['guardian_email'] : '',
                                            'Guardian Mobile' => !empty($student['guardian_mobile']) ? $student['guardian_mobile'] : '',
                                            'Guardian Aadhar No' => !empty($student['guardian_aadhar']) ? $student['guardian_aadhar'] : '',
                                            'Father Aadhar No' => !empty($student['father_aadhar']) ? $student['father_aadhar'] : '',
                                            'Mother Aadhar No' => !empty($student['mother_aadhar']) ? $student['mother_aadhar'] : '',
                                            'Bank Name' => !empty($student['bank_name']) ? $student['bank_name'] : '',
                                            'Bank Branch' => !empty($student['bank_branch']) ? $student['bank_branch'] : '',
                                            'Bank Account No' => !empty($student['bank_account_no']) ? $student['bank_account_no'] : '',
                                            'Bank IFSC' => !empty($student['ifsc_code']) ? $student['ifsc_code'] : '',
                                            'Last Session' => '',
                                            'Scholarship Password' => !empty($student['scholarship_password']) ? $student['scholarship_password'] : '',
                                            'Scholarship ID' => !empty($student['scholarship_id']) ? $student['scholarship_id'] : '',
                                            'DOB Application No' => !empty($student['dob_certificate_no']) ? $student['dob_certificate_no'] : '',
                                            'Enrolled Session' => !empty($student['enrolled_session']) ? $student['enrolled_session'] : '',
                                            'Enrolled Year' => !empty($student['enrolled_year']) ? $student['enrolled_year'] : '',
                                            'Enrolled Class' => !empty($student['enrolled_class_name']) ? $student['enrolled_class_name'] : '',
                                            'Dropout Date' => '',
                                            'Is BPL Student' => !empty($student['is_bpl']) ? ($student['is_bpl'] === 'yes' ? '1' : '0') : '0',
                                            'Account Holder' => !empty($student['bank_account_holder']) ? $student['bank_account_holder'] : '',
                                            'Dropout Reason' => '',
                                            'Disability Remark' => '',
                                            'House ID' => '',
                                            'APAAR ID' => !empty($student['apaar_id']) ? $student['apaar_id'] : '',
                                            'Govt Student ID' => !empty($student['govt_student_id']) ? $student['govt_student_id'] : '',
                                            'Govt Family ID' => !empty($student['govt_family_id']) ? $student['govt_family_id'] : '',
                                            'Samagra ID' => !empty($student['samagra_id']) ? $student['samagra_id'] : '',
                                            'Official Bank Name' => '',
                                            'Official Bank Branch' => '',
                                            'Official Bank IFSC' => '',
                                            'Official Account Holder' => '',
                                            'Remark' => '',
                                            'General Registration No' => !empty($student['general_reg_no']) ? $student['general_reg_no'] : '',
                                            'Official Bank Account No' => '',
                                            'Official UPI' => '',
                                            'Name' => !empty($student['first_name']) ? ($student['first_name'] . ' ' . $student['last_name']) : 'aa',
                                            'Has Disability' => !empty($student['special_needs']) ? ($student['special_needs'] === 'yes' ? '1' : '0') : '0',
                                            'Dropout' => '0',
                                            'Class' => '123',
                                            'Qualifications' => '',
                                            'Classes ID' => '22976',
                                            'Class Name' => !empty($student['class_name']) ? $student['class_name'] : '3',
                                            'Registration No Prefix' => !empty($student['registration_no_prefix']) ? $student['registration_no_prefix'] : '',
                                            'Session' => '2026 - 2027',
                                            'Section ID' => '16740',
                                            'Phone' => !empty($student['mobile_no']) ? $student['mobile_no'] : '',
                                            'Gender' => !empty($student['gender']) ? ucfirst($student['gender']) : '',
                                            'Status' => !empty($student['status']) ? ucfirst($student['status']) : '',
                                            'Website' => '',
                                            'Location' => '',
                                            'Moved By' => '',
                                            'Stream ID' => '',
                                            'Created At' => !empty($student['created_at']) ? date('Y-m-d\TH:i:s.000000\Z', strtotime($student['created_at'])) : '2026-06-07T14:39:46.000000Z',
                                            'Deleted At' => '',
                                            'Deleted By' => '',
                                            'Updated At' => !empty($student['updated_at']) ? date('Y-m-d\TH:i:s.000000\Z', strtotime($student['updated_at'])) : '2026-06-07T14:39:46.000000Z',
                                            'Is Migrated' => '0',
                                            'Parent Type' => '',
                                            'Restored At' => '',
                                            'Restored By' => '',
                                            'Reference ID' => '',
                                            'Department ID' => '',
                                            'Biometric Code' => !empty($student['biometric_code']) ? $student['biometric_code'] : '0',
                                            'Deleted Remark' => '',
                                            'No Of Organizations' => '',
                                            'Migrated From Session' => '2025 - 2026'
                                        ];
                                        ?>
                                        <tr>
                                            <td style="text-align: center;">1</td>
                                            <td style="line-height: 1.6; font-family: var(--font-primary) !important;">
                                                <?php echo date('d M, Y h:i:sa', strtotime($student['created_at'] ?: '2026-06-07 14:39:46')); ?><br>
                                                <span style="font-weight: 600; color: var(--color-text-secondary); display: block; margin-top: 4px;">JOHN ALICE</span>
                                            </td>
                                            <td style="font-family: var(--font-primary) !important; color: var(--color-text-secondary); font-weight: 500;">created</td>
                                            <td></td>
                                            <td style="padding: 0 !important;">
                                                <table style="width: 100%; border-collapse: collapse; margin: 0; border: none; height: 100%;">
                                                    <tbody>
                                                        <tr>
                                                            <td style="width: 100px; padding: 12px 16px; border-right: 1px solid var(--color-border) !important; border-bottom: none !important; border-top: none !important; border-left: none !important; font-weight: 600; color: var(--color-text-secondary); background-color: var(--color-surface); vertical-align: top; text-align: left; font-family: var(--font-heading) !important; font-size: 14px !important;">Data</td>
                                                            <td style="padding: 0 !important; border-right: none !important; border-bottom: none !important; border-top: none !important; border-left: none !important; vertical-align: top;">
                                                                <table class="activity-fields-subtable">
                                                                    <tbody>
                                                                        <?php 
                                                                        $field_keys = array_keys($activity_fields);
                                                                        $last_field_key = end($field_keys);
                                                                        foreach ($activity_fields as $field => $val): 
                                                                            $is_last = ($field === $last_field_key);
                                                                            $border_bottom_style = $is_last ? 'border-bottom: none !important;' : 'border-bottom: 1px solid var(--color-border) !important;';
                                                                        ?>
                                                                            <tr>
                                                                                <td style="border-right: 1px solid var(--color-border) !important; border-top: none !important; border-left: none !important; <?php echo $border_bottom_style; ?> width: 240px; font-weight: 600; color: var(--color-text-secondary); font-family: var(--font-heading); background-color: var(--gray-50);"><?php echo htmlspecialchars($field); ?></td>
                                                                                <td style="border-right: none !important; border-top: none !important; border-left: none !important; <?php echo $border_bottom_style; ?> color: var(--color-text-primary); font-family: var(--font-primary); word-break: break-all; background-color: #ffffff;"><?php echo htmlspecialchars($val); ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: DOCUMENTS -->
                    <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-folder-open"></i> Documents
                            </div>
                            <p class="text-xs text-muted mb-4" style="margin-top: -10px;">You can drag and drop image (jpeg, png) or pdf files.</p>
                            
                            <div class="row g-4">
                                <?php
                                if (!function_exists('render_doc_card')) {
                                    function render_doc_card($title, $doc_key, $student, $csrf_token, $student_id)
                                    {
                                        $file_path = $student[$doc_key] ?? '';
                                        $has_file = !empty($file_path);
                                ?>
                                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                            <div class="upload-doc-card <?php echo $has_file ? 'has-file' : ''; ?>" data-doc-type="<?php echo $doc_key; ?>">
                                                <form action="view.php?id=<?php echo $student_id; ?>" method="POST" enctype="multipart/form-data" class="upload-doc-form h-100 d-flex flex-column m-0">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="upload_doc">
                                                    <input type="hidden" name="doc_type" value="<?php echo $doc_key; ?>">
                                                    <input type="file" name="doc_file" class="upload-doc-input d-none" accept="image/*,application/pdf" onchange="this.form.submit()">
                                                    
                                                    <!-- Clickable Drop Zone -->
                                                    <div class="upload-doc-zone flex-grow-1 d-flex flex-column align-items-center justify-content-center text-center p-3 trigger-file-select">
                                                        <?php if ($has_file): ?>
                                                            <?php 
                                                            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                            if ($ext === 'pdf'): 
                                                            ?>
                                                                <div class="uploaded-doc-preview text-danger d-flex flex-column align-items-center justify-content-center">
                                                                    <i class="ph-light ph-file-pdf" style="font-size: 44px;"></i>
                                                                    <div class="text-xxs text-muted mt-1 text-truncate" style="max-width: 140px;"><?php echo htmlspecialchars(basename($file_path)); ?></div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="uploaded-doc-preview d-flex align-items-center justify-content-center">
                                                                    <img src="<?php echo BASE_URL . sanitize($file_path); ?>" class="uploaded-doc-img" alt="<?php echo htmlspecialchars($title); ?>">
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <div class="upload-doc-prompt">
                                                                <div class="text-xs text-secondary fw-semibold">Drag and Drop</div>
                                                                <div class="text-xxs text-muted my-1">OR</div>
                                                                <div class="text-xs text-secondary fw-semibold">Click on upload button</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Card Footer -->
                                                    <div class="upload-doc-bar d-flex align-items-center justify-content-between px-3 py-2">
                                                        <?php if ($has_file): ?>
                                                            <div class="d-flex gap-1.5 align-items-center">
                                                                <a href="<?php echo BASE_URL . sanitize($file_path); ?>" target="_blank" class="upload-doc-btn btn-view" title="View Document">
                                                                    <i class="ph-bold ph-eye"></i>
                                                                </a>
                                                                <button type="button" class="upload-doc-btn btn-upload trigger-file-select" title="Replace Document">
                                                                    <i class="ph-bold ph-arrows-counter-clockwise"></i>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <button type="button" class="upload-doc-btn btn-upload trigger-file-select" title="Upload Document">
                                                                    <i class="ph-bold ph-upload-simple"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <span class="upload-doc-title text-xs fw-bold text-dark"><?php echo htmlspecialchars($title); ?></span>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                <?php
                                    }
                                }

                                render_doc_card("Aadhar", "aadhar_file", $student, $csrf_token, $student_id);
                                render_doc_card("Photo", "photo", $student, $csrf_token, $student_id);
                                render_doc_card("Birth Certificate", "dob_certificate", $student, $csrf_token, $student_id);
                                render_doc_card("Father Photo", "father_photo", $student, $csrf_token, $student_id);
                                render_doc_card("Mother Aadhar", "mother_aadhar_file", $student, $csrf_token, $student_id);
                                render_doc_card("Father Aadhar", "father_aadhar_file", $student, $csrf_token, $student_id);
                                render_doc_card("Guardian Photo", "guardian_photo", $student, $csrf_token, $student_id);
                                render_doc_card("Guardian Aadhar", "guardian_aadhar_file", $student, $csrf_token, $student_id);
                                render_doc_card("Profile", "profile_file", $student, $csrf_token, $student_id);
                                render_doc_card("Caste Certificate", "category_certificate", $student, $csrf_token, $student_id);
                                render_doc_card("Mother Photo", "mother_photo", $student, $csrf_token, $student_id);
                                ?>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // 1. Click to trigger upload
                        document.querySelectorAll('.trigger-file-select').forEach(function(zone) {
                            zone.addEventListener('click', function(e) {
                                // If we click an anchor link (view file), do not trigger file input
                                if (e.target.closest('a')) return;
                                
                                var card = this.closest('.upload-doc-card');
                                var input = card.querySelector('.upload-doc-input');
                                input.click();
                            });
                        });

                        // 2. Drag and drop file upload
                        document.querySelectorAll('.upload-doc-card').forEach(function(card) {
                            var zone = card.querySelector('.upload-doc-zone');
                            var input = card.querySelector('.upload-doc-input');
                            var form = card.querySelector('.upload-doc-form');

                            // Prevent defaults
                            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                                card.addEventListener(eventName, preventDefaults, false);
                            });

                            function preventDefaults(e) {
                                e.preventDefault();
                                e.stopPropagation();
                            }

                            // Highlight/unhighlight
                            ['dragenter', 'dragover'].forEach(eventName => {
                                card.addEventListener(eventName, function() {
                                    card.classList.add('drag-over');
                                }, false);
                            });

                            ['dragleave', 'drop'].forEach(eventName => {
                                card.addEventListener(eventName, function() {
                                    card.classList.remove('drag-over');
                                }, false);
                            });

                            // Handle drop
                            card.addEventListener('drop', function(e) {
                                var dt = e.dataTransfer;
                                var files = dt.files;

                                if (files && files.length > 0) {
                                    input.files = files;
                                    form.submit();
                                }
                            }, false);
                        });
                    });
                    </script>

                </div>
            </div>
        </div>
    </div>


    <!-- Meta tag for JS parameter passing without inline scripting -->
    <div id="student-page-data"
        data-csrf-token="<?php echo $csrf_token; ?>"
        data-base-url="<?php echo BASE_URL; ?>"
        data-flash-success="<?php echo sanitize($flash_success); ?>"
        data-flash-error="<?php echo sanitize($flash_error); ?>">
    </div>


    <?php
    require_once '../../../includes/footer.php';
    ?>
