<?php
// modules/school/teachers/view.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch teacher details
$stmt = $pdo->prepare("
    SELECT t.*, u.username as u_name
    FROM   teachers t
    JOIN   users u ON t.user_id = u.id
    WHERE  t.id = :id AND t.school_id = :school_id
");
$stmt->execute([':id' => $teacher_id, ':school_id' => $school_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    $_SESSION['flash_error'] = "Teacher profile not found.";
    header('Location: index.php');
    exit;
}

// POST action for uploading documents directly if missing or changing them
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: view.php?id=' . $teacher_id);
        exit;
    }

    $doc_type = $_POST['doc_type'] ?? '';
    if (!in_array($doc_type, ['photo', 'aadhar_file', 'signature_file'])) {
        $_SESSION['flash_error'] = "Invalid document type.";
        header('Location: view.php?id=' . $teacher_id);
        exit;
    }

    $upload_dir = ROOT_PATH . 'uploads/teachers/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
            $prefix = ($doc_type === 'photo') ? 'photo_' : (($doc_type === 'aadhar_file') ? 'aadhar_' : 'sig_');
            $new_name = $prefix . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $new_name)) {
                $file_path = 'uploads/teachers/' . $new_name;

                // Delete old file if exists
                if ($teacher[$doc_type] && file_exists(ROOT_PATH . $teacher[$doc_type])) {
                    @unlink(ROOT_PATH . $teacher[$doc_type]);
                }

                // Update DB
                $stmt_up = $pdo->prepare("UPDATE teachers SET {$doc_type} = :file WHERE id = :id AND school_id = :school_id");
                $stmt_up->execute([':file' => $file_path, ':id' => $teacher_id, ':school_id' => $school_id]);

                if ($doc_type === 'photo') {
                    $stmt_u_up = $pdo->prepare("UPDATE users SET avatar = :file WHERE id = :user_id AND school_id = :school_id");
                    $stmt_u_up->execute([':file' => $file_path, ':user_id' => $teacher['user_id'], ':school_id' => $school_id]);
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

    header('Location: view.php?id=' . $teacher_id);
    exit;
}

// Fetch assigned classes
$stmt_c = $pdo->prepare("
    SELECT tc.*, c.class_name as class_name, s.section_name as section_name
    FROM   teacher_classes tc
    JOIN   classes c ON tc.class_id = c.id
    JOIN   sections s ON tc.section_id = s.id
    WHERE  tc.teacher_id = :teacher_id
    ORDER BY c.sort_order ASC, s.section_name ASC
");
$stmt_c->execute([':teacher_id' => $teacher['id']]);
$assigned_classes = $stmt_c->fetchAll();

// Parse qualifications JSON
$qualifications = [];
if (!empty($teacher['qualifications'])) {
    $qualifications = json_decode($teacher['qualifications'], true);
    if (!is_array($qualifications)) {
        $qualifications = [];
    }
}

// Fetch teacher attendance history
$stmt_att = $pdo->prepare("
    SELECT * FROM teacher_attendance
    WHERE teacher_id = :teacher_id AND school_id = :school_id
    ORDER BY date DESC
");
$stmt_att->execute([':teacher_id' => $teacher['id'], ':school_id' => $school_id]);
$attendance_records = $stmt_att->fetchAll();

// Compute attendance statistics
$total_days = count($attendance_records);
$present_days = 0;
$late_days = 0;
$absent_days = 0;
$leave_days = 0;
$half_days = 0;
foreach ($attendance_records as $rec) {
    if ($rec['status'] === 'present') $present_days++;
    elseif ($rec['status'] === 'late') $late_days++;
    elseif ($rec['status'] === 'absent') $absent_days++;
    elseif ($rec['status'] === 'leave') $leave_days++;
    elseif ($rec['status'] === 'half_day') $half_days++;
}

// Generate CSRF token for file uploads
$csrf_token = generate_csrf_token();

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>



<!-- ─── PAGE HEADER ────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">Teacher Profile</h2>
        <p class="text-xs text-muted mb-0">Detailed administrative and personal records of the faculty member.</p>
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
            <div class="p-3">
                <div class="row align-items-center g-4">
                    <!-- Profile Primary Info -->
                    <div class="col-md-4 text-center profile-left-col pe-md-4">
                        <div class="mb-3 d-flex justify-content-center">
                            <?php if (!empty($teacher['photo'])): ?>
                                <img src="<?php echo BASE_URL . sanitize($teacher['photo']); ?>" alt="Profile Photo" class="summary-avatar">
                            <?php else: ?>
                                <div class="summary-avatar-placeholder">
                                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="summary-name mb-1"><?php echo sanitize($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h4>
                        <div class="text-xs text-muted mb-2">@<?php echo sanitize($teacher['u_name']); ?></div>
                        <div class="mb-3">
                            <?php if ($teacher['status'] === 'active'): ?>
                                <span class="teacher-status-badge active">
                                    <span class="status-dot"></span> Active Faculty
                                </span>
                            <?php else: ?>
                                <span class="teacher-status-badge inactive">
                                    Inactive / Suspended
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <a href="mailto:<?php echo sanitize($teacher['email']); ?>" class="teacher-comm-btn email-btn" title="Email Teacher">
                                <i class="ph-light ph-envelope-simple"></i> Email
                            </a>
                            <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $teacher['mobile_no']); ?>" target="_blank" class="teacher-comm-btn whatsapp-btn" title="WhatsApp Teacher">
                                <i class="ph-light ph-whatsapp-logo"></i> WhatsApp
                            </a>
                        </div>
                    </div>
                    
                    <!-- Profile Secondary details (Grid) -->
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Designation</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize($teacher['designation'] ?? 'Not Assigned'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Department</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize($teacher['department'] ?? 'Not Assigned'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Staff ID No</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize($teacher['staff_id'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Biometric Code</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize($teacher['biometric_code'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Joining Date</span>
                                    <span class="detail-box-val text-dark"><?php echo !empty($teacher['joining_date']) ? date('d-M-Y', strtotime($teacher['joining_date'])) : '—'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Tabs under Profile Info -->
            <div class="border-top student-tabs-header">
                <ul class="nav nav-tabs teacher-tabs flex-nowrap border-0 m-0" id="teacherTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
                            <i class="ph-light ph-user-focus"></i> View Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false">
                            <i class="ph-light ph-calendar"></i> Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                            <i class="ph-light ph-files"></i> Documents
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
                <div class="tab-content" id="teacherTabContent">

                    <!-- TAB 1: VIEW DETAILS -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <!-- Group 1: Personal & Identity Details -->
                        <div class="detail-section-card sec-personal">
                            <div class="detail-section-title">
                                <i class="ph-light ph-identification-card"></i> Personal & Identity Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> First Name</span>
                                        <span class="detail-box-val"><?php echo sanitize($teacher['first_name']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Last Name</span>
                                        <span class="detail-box-val"><?php echo sanitize($teacher['last_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user-circle"></i> Username</span>
                                        <span class="detail-box-val">@<?php echo sanitize($teacher['u_name']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-envelope-simple"></i> Email Address</span>
                                        <span class="detail-box-val email-link"><?php echo sanitize($teacher['email']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Mobile Number</span>
                                        <span class="detail-box-val"><?php echo sanitize($teacher['mobile_no']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Alternate Mobile</span>
                                        <span class="detail-box-val <?php echo empty($teacher['alternate_mobile_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['alternate_mobile_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-whatsapp-logo"></i> WhatsApp Number</span>
                                        <span class="detail-box-val <?php echo empty($teacher['whatsapp_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['whatsapp_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-gender-intersex"></i> Gender</span>
                                        <span class="detail-box-val"><?php echo ucfirst(sanitize($teacher['gender'] ?? '—')); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-calendar"></i> Date of Birth (DOB)</span>
                                        <span class="detail-box-val"><?php echo !empty($teacher['dob']) ? date('d-M-Y', strtotime($teacher['dob'])) : '—'; ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-heart"></i> Marital Status</span>
                                        <span class="detail-box-val <?php echo empty($teacher['marital_status']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['marital_status'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Spouse Name</span>
                                        <span class="detail-box-val <?php echo empty($teacher['spouse_name']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['spouse_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Father Name</span>
                                        <span class="detail-box-val <?php echo empty($teacher['father_name']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['father_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-globe-hemisphere-east"></i> Nationality</span>
                                        <span class="detail-box-val"><?php echo sanitize($teacher['nationality'] ?? 'INDIAN'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hands-praying"></i> Religion</span>
                                        <span class="detail-box-val <?php echo empty($teacher['religion']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['religion'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-tag"></i> Category</span>
                                        <span class="detail-box-val <?php echo empty($teacher['category']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['category'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-fingerprint"></i> Biometric Code</span>
                                        <span class="detail-box-val mono"><?php echo sanitize($teacher['biometric_code'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-identification-badge"></i> Staff ID No</span>
                                        <span class="detail-box-val mono"><?php echo sanitize($teacher['staff_id'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-briefcase"></i> Designation</span>
                                        <span class="detail-box-val"><?php echo sanitize($teacher['designation'] ?? 'Not Assigned'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-buildings"></i> Department</span>
                                        <span class="detail-box-val"><?php echo sanitize($teacher['department'] ?? 'Not Assigned'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-calendar-blank"></i> Joining Date</span>
                                        <span class="detail-box-val"><?php echo !empty($teacher['joining_date']) ? date('d-M-Y', strtotime($teacher['joining_date'])) : '—'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Prior Experience & Academic Qualifications -->
                        <div class="detail-section-card sec-experience">
                            <div class="detail-section-title">
                                <i class="ph-light ph-briefcase"></i> Prior Experience & Qualifications
                            </div>
                            <div class="row g-2 mb-4">
                                <div class="col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-buildings"></i> Last Organization</span>
                                        <span class="detail-box-val <?php echo empty($teacher['last_org_name']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['last_org_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-identification-badge"></i> Last Job Position</span>
                                        <span class="detail-box-val <?php echo empty($teacher['last_job_position']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['last_job_position'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-clock"></i> Total Experience</span>
                                        <span class="detail-box-val"><?php echo intval($teacher['exp_years']); ?> years</span>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th>Qualification</th>
                                            <th>College / University</th>
                                            <th>Passing Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($qualifications)): ?>
                                             <tr>
                                                <td colspan="3" class="text-center text-muted py-3">No academic qualifications listed.</td>
                                             </tr>
                                        <?php else: ?>
                                            <?php foreach ($qualifications as $q): ?>
                                                <tr>
                                                    <td class="fw-semibold text-dark"><?php echo sanitize($q['qualification'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['college'] ?? '—'); ?></td>
                                                    <td><?php echo sanitize($q['passing_year'] ?? '—'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Group 3: Address & Bank Account Details -->
                        <div class="detail-section-card sec-address">
                            <div class="detail-section-title">
                                <i class="ph-light ph-map-pin"></i> Address & Bank Details
                            </div>
                            <div class="row g-2 mb-4">
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Pincode</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['pincode']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['pincode'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-buildings"></i> City</span>
                                        <span class="detail-box-val <?php echo empty($teacher['city']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['city'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-map-trifold"></i> State</span>
                                        <span class="detail-box-val <?php echo empty($teacher['state']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['state'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-globe"></i> Country</span>
                                        <span class="detail-box-val <?php echo empty($teacher['country']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['country'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-house-line"></i> Full Address</span>
                                        <span class="detail-box-val <?php echo empty($teacher['address']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['address'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Account Holder</span>
                                        <span class="detail-box-val <?php echo empty($teacher['bank_acc_holder']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['bank_acc_holder'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-bank"></i> Bank Name</span>
                                        <span class="detail-box-val <?php echo empty($teacher['bank_name']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['bank_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-code"></i> IFSC Code</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['bank_ifsc']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['bank_ifsc'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-credit-card"></i> Account Number</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['bank_acc_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['bank_acc_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-identification-card"></i> PAN Number</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['pan_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['pan_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-vault"></i> PF Account No</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['pf_acc_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['pf_acc_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-fingerprint"></i> UAN Number</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['uan_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['uan_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-identification-badge"></i> Aadhar Number</span>
                                        <span class="detail-box-val mono <?php echo empty($teacher['aadhar_no']) ? 'empty' : ''; ?>"><?php echo sanitize($teacher['aadhar_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 4: Assigned Classes -->
                        <div class="detail-section-card sec-classes">
                            <div class="detail-section-title">
                                <i class="ph-light ph-chalkboard-teacher"></i> Class Assignments
                            </div>
                            <div class="table-responsive">
                                <table class="teacher-detail-table w-100">
                                    <thead>
                                        <tr>
                                            <th>Class Name</th>
                                            <th>Section</th>
                                            <th>Class Teacher Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($assigned_classes)): ?>
                                             <tr>
                                                <td colspan="3" class="text-center text-muted py-3">No classes/sections currently assigned.</td>
                                             </tr>
                                        <?php else: ?>
                                            <?php foreach ($assigned_classes as $tc): ?>
                                                <tr>
                                                    <td class="fw-semibold text-dark"><?php echo sanitize($tc['class_name']); ?></td>
                                                    <td><?php echo sanitize($tc['section_name']); ?></td>
                                                    <td>
                                                        <?php if ($tc['is_class_teacher']): ?>
                                                            <span class="ct-badge-primary">
                                                                <i class="ph-light ph-crown"></i> Class Teacher
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="ct-badge-muted">Subject Teacher</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: ATTENDANCE -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                        <div class="teacher-section-title">
                            <i class="ph-light ph-calendar"></i> Attendance Overview
                        </div>

                        <!-- Stats Row -->
                        <div class="row g-2 mb-4">
                            <div class="col-4 col-sm-2">
                                <div class="att-stat-card">
                                    <div class="att-stat-icon att-icon-total">
                                        <i class="ph-light ph-hash"></i>
                                    </div>
                                    <div class="att-stat-info">
                                        <span class="att-stat-label">Total</span>
                                        <span class="att-stat-val"><?php echo $total_days; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 col-sm-2">
                                <div class="att-stat-card">
                                    <div class="att-stat-icon att-icon-present">
                                        <i class="ph-light ph-check-circle"></i>
                                    </div>
                                    <div class="att-stat-info">
                                        <span class="att-stat-label">Present</span>
                                        <span class="att-stat-val att-val-present"><?php echo $present_days; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 col-sm-2">
                                <div class="att-stat-card">
                                    <div class="att-stat-icon att-icon-late">
                                        <i class="ph-light ph-clock"></i>
                                    </div>
                                    <div class="att-stat-info">
                                        <span class="att-stat-label">Late</span>
                                        <span class="att-stat-val att-val-late"><?php echo $late_days; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 col-sm-2">
                                <div class="att-stat-card">
                                    <div class="att-stat-icon att-icon-absent">
                                        <i class="ph-light ph-x-circle"></i>
                                    </div>
                                    <div class="att-stat-info">
                                        <span class="att-stat-label">Absent</span>
                                        <span class="att-stat-val att-val-absent"><?php echo $absent_days; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 col-sm-2">
                                <div class="att-stat-card">
                                    <div class="att-stat-icon att-icon-leave">
                                        <i class="ph-light ph-calendar-blank"></i>
                                    </div>
                                    <div class="att-stat-info">
                                        <span class="att-stat-label">Leave</span>
                                        <span class="att-stat-val att-val-leave"><?php echo $leave_days; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 col-sm-2">
                                <div class="att-stat-card">
                                    <div class="att-stat-icon att-icon-halfday">
                                        <i class="ph-light ph-hourglass-simple"></i>
                                    </div>
                                    <div class="att-stat-info">
                                        <span class="att-stat-label">Half Day</span>
                                        <span class="att-stat-val att-val-halfday"><?php echo $half_days; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="teacher-detail-table w-100">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Leave/Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance_records)): ?>
                                     <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No attendance records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance_records as $rec): ?>
                                            <tr>
                                                <td class="fw-semibold text-dark"><?php echo date('d-M-Y', strtotime($rec['date'])); ?> <span class="text-muted text-xs font-normal"><?php echo date('l', strtotime($rec['date'])); ?></span></td>
                                                <td>
                                                    <?php if ($rec['status'] === 'present'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 text-xs">Present</span>
                                                    <?php elseif ($rec['status'] === 'late'): ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 text-xs">Late</span>
                                                    <?php elseif ($rec['status'] === 'absent'): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 text-xs">Absent</span>
                                                    <?php elseif ($rec['status'] === 'leave'): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 text-xs">Leave</span>
                                                    <?php elseif ($rec['status'] === 'half_day'): ?>
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-1 text-xs">Half Day</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="mono"><?php echo $rec['check_in'] ? date('h:i A', strtotime($rec['check_in'])) : '—'; ?></td>
                                                <td class="mono"><?php echo $rec['check_out'] ? date('h:i A', strtotime($rec['check_out'])) : '—'; ?></td>
                                                <td>
                                                    <?php if ($rec['status'] === 'leave' || $rec['status'] === 'half_day'): ?>
                                                        <span class="text-xs fw-semibold text-dark d-block"><?php echo sanitize($rec['leave_type'] ?? 'General'); ?></span>
                                                        <span class="text-muted text-xs"><?php echo sanitize($rec['leave_reason'] ?? ''); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted text-xs">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 3: DOCUMENTS -->
                    <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                        <div class="detail-section-card">
                            <div class="detail-section-title">
                                <i class="ph-light ph-folder-open"></i> Attached Verification Files
                            </div>
                            <p class="text-xs text-muted mb-4" style="margin-top: -10px;">You can drag and drop image (jpeg, png) or pdf files.</p>

                            <div class="row g-4">
                                <?php
                                if (!function_exists('render_doc_card')) {
                                    function render_doc_card($title, $doc_key, $teacher, $csrf_token, $teacher_id)
                                    {
                                        $file_path = $teacher[$doc_key] ?? '';
                                        $has_file = !empty($file_path);
                                        $accept_types = ($doc_key === 'aadhar_file') ? 'image/*,application/pdf' : 'image/*';
                                ?>
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="upload-doc-card <?php echo $has_file ? 'has-file' : ''; ?>" data-doc-type="<?php echo $doc_key; ?>">
                                                <form action="view.php?id=<?php echo $teacher_id; ?>" method="POST" enctype="multipart/form-data" class="upload-doc-form h-100 d-flex flex-column m-0">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="upload_doc">
                                                    <input type="hidden" name="doc_type" value="<?php echo $doc_key; ?>">
                                                    <input type="file" name="doc_file" class="upload-doc-input d-none" accept="<?php echo $accept_types; ?>" onchange="this.form.submit()">

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

                                render_doc_card("Profile Photo", "photo", $teacher, $csrf_token, $teacher_id);
                                render_doc_card("Aadhar Document", "aadhar_file", $teacher, $csrf_token, $teacher_id);
                                render_doc_card("Signature Image", "signature_file", $teacher, $csrf_token, $teacher_id);
                                ?>
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
    </div>
</div>

<!-- Meta tag for JS parameter passing without inline scripting -->
<div id="teacher-page-data"
    data-csrf-token="<?php echo $csrf_token; ?>"
    data-base-url="<?php echo BASE_URL; ?>"
    data-flash-success="<?php echo sanitize($flash_success); ?>"
    data-flash-error="<?php echo sanitize($flash_error); ?>">
</div>

<?php
require_once '../../../includes/footer.php';
?>
