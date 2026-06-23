<?php
// modules/school/parents/view.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$parent_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch parent details
$stmt = $pdo->prepare("
    SELECT p.*, u.username as u_name
    FROM   parents p
    JOIN   users u ON p.user_id = u.id
    WHERE  p.id = :id AND p.school_id = :school_id
");
$stmt->execute([':id' => $parent_id, ':school_id' => $school_id]);
$parent = $stmt->fetch();

if (!$parent) {
    $_SESSION['flash_error'] = "Parent profile not found.";
    header('Location: index.php');
    exit;
}

// Fetch linked students
$stmt_s = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.roll_no, c.name as class_name, sec.name as section_name, s.photo, s.gender, s.admission_no_prefix, s.admission_no
    FROM   students s
    JOIN   parent_students ps ON s.id = ps.student_id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE  ps.parent_id = :parent_id AND s.school_id = :school_id AND s.deleted_at IS NULL
");
$stmt_s->execute([':parent_id' => $parent_id, ':school_id' => $school_id]);
$linked_students = $stmt_s->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">Parent Profile</h2>
        <p class="text-xs text-muted mb-0">Detailed personal and linked student records.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <a href="index.php" class="btn-admin-secondary">
            <i class="ph-light ph-arrow-left"></i> Back to Directory
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card-premium teacher-summary-card p-0">
            <div class="p-4">
                <div class="row align-items-center g-4">
                    <div class="col-md-4 text-center profile-left-col pe-md-4">
                        <div class="mb-3 d-flex justify-content-center">
                            <div class="summary-avatar-placeholder">
                                <?php echo strtoupper(substr($parent['first_name'], 0, 1) . substr($parent['last_name'] ?? '', 0, 1)); ?>
                            </div>
                        </div>
                        <h4 class="summary-name mb-1"><?php echo sanitize($parent['first_name'] . ' ' . $parent['last_name']); ?></h4>
                        <div class="text-xs text-muted mb-2">@<?php echo sanitize($parent['u_name']); ?></div>
                        <div class="mb-3">
                            <?php if ($parent['status'] === 'active'): ?>
                                <span class="teacher-status-badge active">
                                    <span class="status-dot"></span> Active Parent
                                </span>
                            <?php else: ?>
                                <span class="teacher-status-badge inactive">
                                    Inactive / Suspended
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <a href="mailto:<?php echo sanitize($parent['email']); ?>" class="teacher-comm-btn email-btn" title="Email Parent">
                                <i class="ph-light ph-envelope-simple"></i> Email
                            </a>
                            <?php if (!empty($parent['mobile'])): ?>
                                <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $parent['whatsapp_no'] ?? $parent['mobile']); ?>" target="_blank" class="teacher-comm-btn whatsapp-btn" title="WhatsApp Parent">
                                    <i class="ph-light ph-whatsapp-logo"></i> WhatsApp
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Parent Type</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize($parent['parent_type']); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Mobile Number</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize($parent['mobile'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Aadhar No</span>
                                    <span class="detail-box-val mono text-dark"><?php echo sanitize($parent['aadhaar_no'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Occupation</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize($parent['occupation'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">City</span>
                                    <span class="detail-box-val text-dark"><?php echo sanitize($parent['city'] ?? '—'); ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="detail-box">
                                    <span class="detail-box-label">Linked Children</span>
                                    <span class="detail-box-val">
                                        <span class="badge bg-info text-white"><?php echo count($linked_students); ?> Students</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-top student-tabs-header">
                <ul class="nav nav-tabs teacher-tabs flex-nowrap border-0 m-0" id="parentTab" role="tablist" style="overflow-x: auto; white-space: nowrap;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
                            <i class="ph-light ph-user-focus"></i> View Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="false">
                            <i class="ph-light ph-graduation-cap"></i> Linked Children
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card-premium p-0">
            <div class="card-body p-4 text-dark">
                <div class="tab-content" id="parentTabContent">

                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        
                        <div class="detail-section-card sec-personal mb-4">
                            <div class="detail-section-title">
                                <i class="ph-light ph-identification-card"></i> Personal & Contact Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> First Name</span>
                                        <span class="detail-box-val"><?php echo sanitize($parent['first_name']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user"></i> Last Name</span>
                                        <span class="detail-box-val"><?php echo sanitize($parent['last_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-user-circle"></i> Username</span>
                                        <span class="detail-box-val">@<?php echo sanitize($parent['u_name']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-envelope-simple"></i> Email Address</span>
                                        <span class="detail-box-val email-link"><?php echo sanitize($parent['email'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Mobile Number</span>
                                        <span class="detail-box-val mono"><?php echo sanitize($parent['mobile'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Alternate Mobile</span>
                                        <span class="detail-box-val mono <?php echo empty($parent['alternate_mobile']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['alternate_mobile'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-whatsapp-logo"></i> WhatsApp Number</span>
                                        <span class="detail-box-val mono <?php echo empty($parent['whatsapp_no']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['whatsapp_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-gender-intersex"></i> Gender</span>
                                        <span class="detail-box-val"><?php echo ucfirst(sanitize($parent['gender'] ?? '—')); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-users"></i> Parent Type</span>
                                        <span class="detail-box-val"><?php echo sanitize($parent['parent_type'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-identification-badge"></i> Aadhar Number</span>
                                        <span class="detail-box-val mono <?php echo empty($parent['aadhaar_no']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['aadhaar_no'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-certificate"></i> Qualification</span>
                                        <span class="detail-box-val <?php echo empty($parent['qualification']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['qualification'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section-card sec-experience mb-4">
                            <div class="detail-section-title">
                                <i class="ph-light ph-briefcase"></i> Employment Details
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-bag"></i> Occupation</span>
                                        <span class="detail-box-val <?php echo empty($parent['occupation']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['occupation'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-buildings"></i> Company / Business</span>
                                        <span class="detail-box-val <?php echo empty($parent['company_name']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['company_name'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-identification-badge"></i> Designation</span>
                                        <span class="detail-box-val <?php echo empty($parent['designation']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['designation'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-phone"></i> Company Phone</span>
                                        <span class="detail-box-val mono <?php echo empty($parent['company_phone']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['company_phone'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-map-pin-line"></i> Company Address</span>
                                        <span class="detail-box-val <?php echo empty($parent['company_address']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['company_address'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section-card sec-address">
                            <div class="detail-section-title">
                                <i class="ph-light ph-map-pin"></i> Residential Address
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-hash"></i> Pincode</span>
                                        <span class="detail-box-val mono <?php echo empty($parent['pincode']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['pincode'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-buildings"></i> City</span>
                                        <span class="detail-box-val <?php echo empty($parent['city']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['city'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-map-trifold"></i> State</span>
                                        <span class="detail-box-val <?php echo empty($parent['state']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['state'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-globe"></i> Country</span>
                                        <span class="detail-box-val <?php echo empty($parent['country']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['country'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="detail-box">
                                        <span class="detail-box-label"><i class="ph-light ph-house-line"></i> Full Address</span>
                                        <span class="detail-box-val <?php echo empty($parent['address']) ? 'empty' : ''; ?>"><?php echo sanitize($parent['address'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="students" role="tabpanel" aria-labelledby="students-tab">
                        <div class="teacher-section-title mb-4">
                            <i class="ph-light ph-graduation-cap"></i> Linked Children (Siblings)
                        </div>

                        <?php if (empty($linked_students)): ?>
                            <div class="text-center py-5 border rounded-3 bg-light">
                                <i class="ph-light ph-users-three fs-1 text-muted mb-3 d-block"></i>
                                <p class="text-muted mb-0">No students are currently linked to this parent.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($linked_students as $student): ?>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="modal-section-card p-3 transition-all h-100 border-light-hover shadow-sm-hover">
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if (!empty($student['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . sanitize($student['photo']); ?>" class="student-avatar" style="width:50px; height:50px;" alt="Student">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder" style="width:50px; height:50px; font-size:1.2rem;">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'] ?? '', 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 fw-bold">
                                                        <a href="../students/view.php?id=<?php echo $student['id']; ?>" class="text-dark text-decoration-none">
                                                            <?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?>
                                                        </a>
                                                    </h6>
                                                    <p class="text-xs text-muted mb-1"><?php echo sanitize(($student['class_name'] ?? '') . ' - ' . ($student['section_name'] ?? '')); ?></p>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="text-xxs text-muted">Adm No: <strong class="text-dark"><?php echo sanitize(($student['admission_no_prefix'] ?? '') . $student['admission_no']); ?></strong></span>
                                                        <span class="text-xxs text-muted">Roll: <strong class="text-dark"><?php echo sanitize($student['roll_no'] ?? '—'); ?></strong></span>
                                                    </div>
                                                </div>
                                                <a href="../students/view.php?id=<?php echo $student['id']; ?>" class="teacher-action-btn action-view">
                                                    <i class="ph-bold ph-arrow-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>