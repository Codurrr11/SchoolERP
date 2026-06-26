<?php
// modules/school/leads/assigned.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Helper function to safely extract strings
if (!function_exists('get_flat_string')) {
    function get_flat_string($val) {
        if (is_array($val)) {
            $first_val = reset($val);
            return is_array($first_val) ? get_flat_string($first_val) : trim((string)$first_val);
        }
        return trim((string)$val);
    }
}

// AJAX endpoint to fetch lead details for modals
if (isset($_GET['get_lead_details']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $lead_id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("
        SELECT l.*, c.name as class_name, u.first_name as creator_first, u.last_name as creator_last
        FROM leads l
        LEFT JOIN classes c ON l.class_id = c.id
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.id = :id AND l.school_id = :school_id AND l.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $lead_id, ':school_id' => $school_id]);
    $lead_data = $stmt->fetch();
    
    if ($lead_data) {
        echo json_encode(['success' => true, 'data' => $lead_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lead not found.']);
    }
    exit;
}

// Fetch helper data
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, first_name, last_name, username FROM users WHERE school_id = :school_id AND deleted_at IS NULL ORDER BY first_name ASC");
$stmt->execute([':school_id' => $school_id]);
$all_users = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM lead_sources WHERE school_id = :school_id ORDER BY name ASC");
$stmt->execute([':school_id' => $school_id]);
$lead_sources = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM lead_statuses WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$lead_statuses = $stmt->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: assigned.php');
        exit;
    }
    
    // Edit Lead
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile_no = trim($_POST['mobile_no'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);
        $source = trim($_POST['source'] ?? '');
        $referred_by = trim($_POST['referred_by'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $dob = $_POST['dob'] ?? null;
        if (empty($dob)) $dob = null;
        
        // Parents Details
        $mother_name = trim($_POST['mother_name'] ?? '');
        $mother_qualification = trim($_POST['mother_qualification'] ?? '');
        $mother_address = trim($_POST['mother_address'] ?? '');
        $mother_occupation = trim($_POST['mother_occupation'] ?? '');
        $mother_official_address = trim($_POST['mother_official_address'] ?? '');
        $mother_income = trim($_POST['mother_income'] ?? '');
        $mother_email = trim($_POST['mother_email'] ?? '');
        $mother_mobile = trim($_POST['mother_mobile'] ?? '');
        
        $father_name = trim($_POST['father_name'] ?? '');
        $father_qualification = trim($_POST['father_qualification'] ?? '');
        $father_address = trim($_POST['father_address'] ?? '');
        $father_occupation = trim($_POST['father_occupation'] ?? '');
        $father_official_address = trim($_POST['father_official_address'] ?? '');
        $father_income = trim($_POST['father_income'] ?? '');
        $father_email = trim($_POST['father_email'] ?? '');
        $father_mobile = trim($_POST['father_mobile'] ?? '');
        
        // Religion/Category
        $nationality = trim($_POST['nationality'] ?? 'INDIAN');
        $religion = trim($_POST['religion'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $aadhar_no = trim($_POST['aadhar_no'] ?? '');
        
        // Last School Details
        $last_school_name = trim($_POST['last_school_name'] ?? '');
        $last_school_class = trim($_POST['last_school_class'] ?? '');
        $last_school_affiliation = trim($_POST['last_school_affiliation'] ?? '');
        
        // Location
        $pincode = trim($_POST['pincode'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? 'India');
        $address = trim($_POST['address'] ?? '');
        
        // Lead Info
        $status = trim($_POST['status'] ?? 'Interested');
        $remark = trim($_POST['remark'] ?? '');
        
        if (empty($first_name) || empty($mobile_no) || empty($class_id)) {
            $_SESSION['flash_error'] = "First Name, Mobile Number, and Class are required fields.";
            header('Location: assigned.php');
            exit;
        }
        
        // Check lead belongs to school and is assigned to me
        $stmt = $pdo->prepare("SELECT photo FROM leads WHERE id = :id AND school_id = :school_id AND assigned_to = :assigned_to AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':school_id' => $school_id, ':assigned_to' => $_SESSION['user_id']]);
        $existing = $stmt->fetch();
        if (!$existing) {
            $_SESSION['flash_error'] = "Lead not found or access denied.";
            header('Location: assigned.php');
            exit;
        }
        
        $photo_path = $existing['photo'];
        $upload_dir = ROOT_PATH . 'uploads/leads/';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'lead_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'uploads/leads/' . $new_name;
            }
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE leads SET
                    class_id = :class_id, source = :source, referred_by = :referred_by,
                    first_name = :first_name, last_name = :last_name, email = :email, mobile_no = :mobile_no,
                    gender = :gender, dob = :dob,
                    mother_name = :mother_name, mother_qualification = :mother_qualification, mother_address = :mother_address,
                    mother_occupation = :mother_occupation, mother_official_address = :mother_official_address, mother_income = :mother_income,
                    mother_email = :mother_email, mother_mobile = :mother_mobile,
                    father_name = :father_name, father_qualification = :father_qualification, father_address = :father_address,
                    father_occupation = :father_occupation, father_official_address = :father_official_address, father_income = :father_income,
                    father_email = :father_email, father_mobile = :father_mobile,
                    nationality = :nationality, religion = :religion, category = :category, aadhar_no = :aadhar_no,
                    last_school_name = :last_school_name, last_school_class = :last_school_class, last_school_affiliation = :last_school_affiliation,
                    pincode = :pincode, city = :city, state = :state, country = :country, address = :address,
                    status = :status, remark = :remark, photo = :photo
                WHERE id = :id AND school_id = :school_id AND assigned_to = :assigned_to
            ");
            
            $stmt->execute([
                ':class_id' => $class_id,
                ':source' => $source,
                ':referred_by' => $referred_by,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':mobile_no' => $mobile_no,
                ':gender' => !empty($gender) ? $gender : null,
                ':dob' => $dob,
                ':mother_name' => $mother_name,
                ':mother_qualification' => $mother_qualification,
                ':mother_address' => $mother_address,
                ':mother_occupation' => $mother_occupation,
                ':mother_official_address' => $mother_official_address,
                ':mother_income' => $mother_income,
                ':mother_email' => $mother_email,
                ':mother_mobile' => $mother_mobile,
                ':father_name' => $father_name,
                ':father_qualification' => $father_qualification,
                ':father_address' => $father_address,
                ':father_occupation' => $father_occupation,
                ':father_official_address' => $father_official_address,
                ':father_income' => $father_income,
                ':father_email' => $father_email,
                ':father_mobile' => $father_mobile,
                ':nationality' => $nationality,
                ':religion' => $religion,
                ':category' => $category,
                ':aadhar_no' => $aadhar_no,
                ':last_school_name' => $last_school_name,
                ':last_school_class' => $last_school_class,
                ':last_school_affiliation' => $last_school_affiliation,
                ':pincode' => $pincode,
                ':city' => $city,
                ':state' => $state,
                ':country' => $country,
                ':address' => $address,
                ':status' => $status,
                ':remark' => $remark,
                ':photo' => $photo_path,
                ':id' => $id,
                ':school_id' => $school_id,
                ':assigned_to' => $_SESSION['user_id']
            ]);
            
            $_SESSION['flash_success'] = "Lead successfully updated!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to update lead: " . $e->getMessage();
        }
        header('Location: assigned.php');
        exit;
    }
    
    // Assign Lead
    if ($action === 'assign') {
        $id = intval($_POST['id'] ?? 0);
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        if ($assigned_to === 0) $assigned_to = null;
        
        try {
            $stmt = $pdo->prepare("UPDATE leads SET assigned_to = :assigned_to WHERE id = :id AND school_id = :school_id AND assigned_to = :me");
            $stmt->execute([':assigned_to' => $assigned_to, ':id' => $id, ':school_id' => $school_id, ':me' => $_SESSION['user_id']]);
            $_SESSION['flash_success'] = "Lead successfully assigned!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to assign lead: " . $e->getMessage();
        }
        header('Location: assigned.php');
        exit;
    }
    
    // Schedule/Remark/Status update
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Interested');
        $remark = trim($_POST['remark'] ?? '');
        $scheduled_at = $_POST['scheduled_at'] ?? null;
        if (empty($scheduled_at)) $scheduled_at = null;
        
        try {
            $stmt = $pdo->prepare("UPDATE leads SET status = :status, remark = :remark, scheduled_at = :scheduled_at WHERE id = :id AND school_id = :school_id AND assigned_to = :assigned_to");
            $stmt->execute([
                ':status' => $status,
                ':remark' => $remark,
                ':scheduled_at' => $scheduled_at,
                ':id' => $id,
                ':school_id' => $school_id,
                ':assigned_to' => $_SESSION['user_id']
            ]);
            $_SESSION['flash_success'] = "Lead follow-up status updated!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to update follow-up: " . $e->getMessage();
        }
        header('Location: assigned.php');
        exit;
    }
    
    // Convert to Admission
    if ($action === 'convert') {
        $id = intval($_POST['id'] ?? 0);
        
        // Fetch lead details
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id AND school_id = :school_id AND assigned_to = :assigned_to AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':school_id' => $school_id, ':assigned_to' => $_SESSION['user_id']]);
        $lead = $stmt->fetch();
        
        if (!$lead) {
            $_SESSION['flash_error'] = "Lead not found or access denied.";
            header('Location: assigned.php');
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Check if user already converted or student user exists
            $username = strtolower($lead['first_name']) . rand(100, 999);
            $stmt_ucheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt_ucheck->execute([':username' => $username]);
            while ($stmt_ucheck->fetchColumn() > 0) {
                $username = strtolower($lead['first_name']) . rand(100, 999);
                $stmt_ucheck->execute([':username' => $username]);
            }
            
            // 1. Create User
            $stmt_user = $pdo->prepare("
                INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, gender, dob, status)
                VALUES (:school_id, 5, :username, :first_name, :last_name, :email, :phone, :password, :gender, :dob, 'active')
            ");
            $stmt_user->execute([
                ':school_id' => $school_id,
                ':username' => $username,
                ':first_name' => $lead['first_name'],
                ':last_name' => $lead['last_name'] ?? '',
                ':email' => !empty($lead['email']) ? $lead['email'] : $username . '@schoolerp.local',
                ':phone' => $lead['mobile_no'],
                ':password' => password_hash('student123', PASSWORD_DEFAULT),
                ':gender' => $lead['gender'],
                ':dob' => $lead['dob']
            ]);
            $new_user_id = $pdo->lastInsertId();
            
            // 2. Create Student
            $stmt_student = $pdo->prepare("
                INSERT INTO students (
                    school_id, user_id, session_id, class_id, first_name, last_name, 
                    father_name, mother_name, mobile_no, dob, gender, address, photo, status,
                    nationality, religion, category, aadhar_no, referred_by
                ) VALUES (
                    :school_id, :user_id, :session_id, :class_id, :first_name, :last_name, 
                    :father_name, :mother_name, :mobile_no, :dob, :gender, :address, :photo, 'active',
                    :nationality, :religion, :category, :aadhar_no, :referred_by
                )
            ");
            $stmt_student->execute([
                ':school_id' => $school_id,
                ':user_id' => $new_user_id,
                ':session_id' => $lead['session_id'],
                ':class_id' => $lead['class_id'],
                ':first_name' => $lead['first_name'],
                ':last_name' => $lead['last_name'] ?? '',
                ':father_name' => $lead['father_name'] ?? '',
                ':mother_name' => $lead['mother_name'] ?? '',
                ':mobile_no' => $lead['mobile_no'],
                ':dob' => $lead['dob'],
                ':gender' => $lead['gender'],
                ':address' => $lead['address'],
                ':photo' => $lead['photo'],
                ':nationality' => $lead['nationality'],
                ':religion' => $lead['religion'],
                ':category' => $lead['category'],
                ':aadhar_no' => $lead['aadhar_no'],
                ':referred_by' => $lead['referred_by']
            ]);
            
            // 3. Update Lead status
            $stmt_up_lead = $pdo->prepare("UPDATE leads SET status = 'Admission Created', remark = 'Admission created' WHERE id = :id");
            $stmt_up_lead->execute([':id' => $id]);
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Converted to student successfully! Username: $username, Default Password: student123";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to convert lead: " . $e->getMessage();
        }
        header('Location: assigned.php');
        exit;
    }
}

// Fetch leads based on active status filter & assigned to me
$active_tab = $_GET['tab'] ?? 'Interested';
$search_query = trim($_GET['search'] ?? '');

$query_str = "
    SELECT l.*, c.name as class_name, u.first_name as creator_first, u.last_name as creator_last,
           assign.first_name as assign_first, assign.last_name as assign_last
    FROM leads l
    LEFT JOIN classes c ON l.class_id = c.id
    LEFT JOIN users u ON l.created_by = u.id
    LEFT JOIN users assign ON l.assigned_to = assign.id
    WHERE l.school_id = :school_id AND l.assigned_to = :assigned_to AND l.deleted_at IS NULL
";

$params = [':school_id' => $school_id, ':assigned_to' => $_SESSION['user_id']];

if ($active_tab !== 'All') {
    $query_str .= " AND l.status = :status";
    $params[':status'] = $active_tab;
}

if (!empty($search_query)) {
    $query_str .= " AND (l.first_name LIKE :search OR l.last_name LIKE :search OR l.mobile_no LIKE :search OR l.father_name LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

$query_str .= " ORDER BY l.id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Dynamic count metrics for top tabs (assigned to me)
$stmt_counts = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM leads 
    WHERE school_id = :school_id AND assigned_to = :assigned_to AND deleted_at IS NULL 
    GROUP BY status
");
$stmt_counts->execute([':school_id' => $school_id, ':assigned_to' => $_SESSION['user_id']]);
$raw_counts = $stmt_counts->fetchAll();

$counts = [
    'Interested' => 0,
    'not interested' => 0,
    'ADMIN' => 0,
    'follow-up' => 0,
    'call back' => 0,
    'Intersted' => 0,
    'Admission Created' => 0
];
$total_leads = 0;
foreach ($raw_counts as $rc) {
    if (isset($counts[$rc['status']])) {
        $counts[$rc['status']] = $rc['count'];
    }
    $total_leads += $rc['count'];
}

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- SweetAlert Setup & Notifications -->
<?php if ($flash_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: <?php echo json_encode($flash_success); ?>,
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true,
        customClass: { popup: 'swal-toast-custom' }
    });
});
</script>
<?php endif; ?>

<?php if ($flash_error): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Error Occurred',
        text: <?php echo json_encode($flash_error); ?>,
        confirmButtonColor: '#2563EB',
        customClass: { confirmButton: 'swal-btn-custom' }
    });
});
</script>
<?php endif; ?>

<!-- Header Panel -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">My Assigned Leads</h2>
        <p class="text-xs text-muted mb-0">Follow up on prospective student inquiries assigned directly to you.</p>
    </div>
</div>

<!-- Controls & Table Section -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="teacher-header-btn btn-sky" title="Export My Leads">
                        <i class="ph-bold ph-cloud-arrow-down"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                    <form method="GET" action="assigned.php" class="table-search-box m-0">
                        <i class="ph-light ph-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Search my leads..." value="<?php echo sanitize($search_query); ?>" style="height: 34px; padding-top: 0; padding-bottom: 0;">
                        <input type="hidden" name="tab" value="<?php echo sanitize($active_tab); ?>">
                    </form>
                    <div class="teacher-total-badge bg-info text-white border-0 py-1.5 px-3 rounded-pill text-xxs font-heading fw-extrabold">
                        Total Assigned: <?php echo $total_leads; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Status Filters -->
            <div class="px-4 pt-3 pb-2 border-bottom d-flex flex-wrap gap-2 align-items-center lead-filter-tabs">
                <?php
                $tab_classes = [
                    'Interested'       => 'tab-interested',
                    'not interested'   => 'tab-not-interested',
                    'ADMIN'            => 'tab-admin',
                    'follow-up'        => 'tab-follow-up',
                    'call back'        => 'tab-call-back',
                    'Intersted'        => 'tab-intersted',
                    'Admission Created'=> 'tab-admission-created'
                ];
                foreach ($counts as $t_name => $t_count):
                    $tc_class    = $tab_classes[$t_name] ?? 'tab-neutral';
                    $active_flag = ($active_tab === $t_name) ? 'active' : '';
                ?>
                    <a href="assigned.php?tab=<?php echo urlencode($t_name); ?>&search=<?php echo urlencode($search_query); ?>"
                       class="btn-filter <?php echo $tc_class; ?> <?php echo $active_flag; ?>">
                        <?php echo $t_name; ?> (<?php echo $t_count; ?>)
                    </a>
                <?php endforeach; ?>
                <a href="assigned.php?tab=All&search=<?php echo urlencode($search_query); ?>"
                   class="btn-filter tab-all <?php echo ($active_tab === 'All') ? 'active' : ''; ?>">
                    Show All
                </a>
            </div>

            <!-- Table Viewport -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($leads)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-user-check"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No assigned inquiries found</h5>
                            <p class="text-xs text-muted">You do not have any leads assigned under this category.</p>
                        </div>
                    <?php else: ?>
                        <table class="table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Student</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Applied For</th>
                                    <th>Created By</th>
                                    <th>Scheduled At</th>
                                    <th>Status</th>
                                    <th>Remark</th>
                                    <th style="width: 80px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($leads as $l): 
                                ?>
                                    <tr>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        
                                        <!-- Student Details -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($l['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . sanitize($l['photo']); ?>" alt="Profile" class="student-avatar">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder">
                                                        <?php echo strtoupper(substr($l['first_name'], 0, 1) . substr($l['last_name'] ?? '', 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column">
                                                    <span class="student-name-link"><?php echo sanitize($l['first_name'] . ' ' . $l['last_name']); ?></span>
                                                    <span class="text-xxs text-muted">Father: <strong class="text-dark"><?php echo sanitize($l['father_name'] ?? '—'); ?></strong></span>
                                                    <span class="text-xxs text-muted">Mother: <strong class="text-dark"><?php echo sanitize($l['mother_name'] ?? '—'); ?></strong></span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Contacts -->
                                        <td class="text-xs">
                                            <div class="d-flex flex-column">
                                                <span>
                                                    Mobile No.: <strong class="text-dark"><?php echo sanitize($l['mobile_no']); ?></strong>
                                                    <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $l['mobile_no']); ?>" target="_blank" class="whatsapp-icon text-success ms-1" title="WhatsApp Chat">
                                                        <i class="ph-fill ph-whatsapp-logo"></i>
                                                    </a>
                                                </span>
                                                <span>F. Mobile No.: <strong class="text-muted"><?php echo sanitize($l['father_mobile'] ?? '—'); ?></strong></span>
                                                <span>M. Mobile No.: <strong class="text-muted"><?php echo sanitize($l['mother_mobile'] ?? '—'); ?></strong></span>
                                            </div>
                                        </td>
                                        
                                        <!-- Address -->
                                        <td class="text-xs text-muted cell-truncate">
                                            <?php echo sanitize($l['address'] ?? '—'); ?>
                                        </td>
                                        
                                        <!-- Applied For Class -->
                                        <td class="text-xs fw-semibold text-center">
                                            <?php echo sanitize($l['class_name'] ?? '—'); ?>
                                        </td>
                                        
                                        <!-- Created By -->
                                        <td class="text-xxs">
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold text-dark"><?php echo sanitize($l['creator_first'] . ' ' . $l['creator_last']); ?></span>
                                                <span class="text-muted"><?php echo date('d M, Y (h:i:s a)', strtotime($l['created_at'])); ?></span>
                                            </div>
                                        </td>
                                        
                                        <!-- Scheduled At -->
                                        <td class="text-xxs text-muted">
                                            <?php if (!empty($l['scheduled_at'])): ?>
                                                <span class="fw-semibold text-dark"><i class="ph-light ph-clock me-1"></i><?php echo date('d M, Y h:i:s a', strtotime($l['scheduled_at'])); ?></span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Status badge -->
                                        <td>
                                            <?php
                                            $badges = [
                                                'Interested'       => 'bg-success',
                                                'not interested'   => 'bg-danger',
                                                'ADMIN'            => 'bg-secondary',
                                                'follow-up'        => 'bg-primary',
                                                'call back'        => 'bg-warning',
                                                'Intersted'        => 'bg-success',
                                                'Admission Created'=> 'bg-info'
                                            ];
                                            $b_class = $badges[$l['status']] ?? 'bg-light text-muted';
                                            ?>
                                            <span class="badge <?php echo $b_class; ?>">
                                                <?php echo $l['status']; ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Remark -->
                                        <td class="text-xs text-muted cell-truncate">
                                            <?php echo sanitize($l['remark'] ?? '—'); ?>
                                        </td>
                                        
                                        <!-- Actions Dropdown -->
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="teacher-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                    <i class="ph-bold ph-list"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                    <li><a class="dropdown-item py-2 text-xs view-lead-btn" href="#" data-id="<?php echo $l['id']; ?>"><i class="ph-light ph-eye me-2"></i> View</a></li>
                                                    <li><a class="dropdown-item py-2 text-xs assign-lead-btn" href="#" data-id="<?php echo $l['id']; ?>"><i class="ph-light ph-user-circle me-2"></i> Assign</a></li>
                                                    <li><a class="dropdown-item py-2 text-xs schedule-lead-btn" href="#" data-id="<?php echo $l['id']; ?>"><i class="ph-light ph-clock me-2"></i> Sch./Remark/Status</a></li>
                                                    <?php if ($l['status'] !== 'Admission Created'): ?>
                                                        <li><a class="dropdown-item py-2 text-xs convert-lead-btn text-success" href="#" data-id="<?php echo $l['id']; ?>"><i class="ph-light ph-user-check me-2"></i> Convert To Admission</a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item py-2 text-xs edit-lead-btn" href="#" data-id="<?php echo $l['id']; ?>"><i class="ph-light ph-pencil me-2"></i> Edit</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal" tabindex="-1" aria-labelledby="editLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-admin py-3 px-4">
                <h5 class="modal-title font-heading fw-bold text-dark" id="editLeadModalLabel">Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="assigned.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body p-4">
                    <!-- Dropdowns Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label-admin">Admission Classes <span class="text-danger">*</span></label>
                            <select name="class_id" id="edit_class_id" class="form-control-admin" required>
                                <option value="">-- Select Classes --</option>
                                <?php foreach ($all_classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Source</label>
                            <select name="source" id="edit_source" class="form-control-admin">
                                <option value="">-- Select --</option>
                                <?php foreach ($lead_sources as $src): ?>
                                    <option value="<?php echo sanitize($src['name']); ?>"><?php echo sanitize($src['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Referred By</label>
                            <input type="text" name="referred_by" id="edit_referred_by" class="form-control-admin">
                        </div>
                    </div>
                    
                    <!-- Personal Details -->
                    <h6 class="font-heading fw-extrabold text-primary mb-3">Personal Details:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label-admin">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control-admin" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Mobile no. <span class="text-danger">*</span></label>
                            <input type="text" name="mobile_no" id="edit_mobile_no" class="form-control-admin" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin d-block">Gender</label>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="radio" name="gender" id="edit_gender_male" value="male">
                                <label class="form-check-label text-xs" for="edit_gender_male">Male</label>
                            </div>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="radio" name="gender" id="edit_gender_female" value="female">
                                <label class="form-check-label text-xs" for="edit_gender_female">Female</label>
                            </div>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="radio" name="gender" id="edit_gender_other" value="other">
                                <label class="form-check-label text-xs" for="edit_gender_other">Other</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">DOB</label>
                            <input type="date" name="dob" id="edit_dob" class="form-control-admin">
                        </div>
                    </div>
                    
                    <!-- Parents Details Table -->
                    <h6 class="font-heading fw-extrabold text-primary mb-3">Parents Details:</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm align-middle text-xs mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width:160px;">Details</th>
                                    <th>Mother</th>
                                    <th>Father/Guardian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Name</strong></td>
                                    <td><input type="text" name="mother_name" id="edit_mother_name" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_name" id="edit_father_name" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Qualification</strong></td>
                                    <td><input type="text" name="mother_qualification" id="edit_mother_qualification" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_qualification" id="edit_father_qualification" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Residential Address</strong></td>
                                    <td><input type="text" name="mother_address" id="edit_mother_address" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_address" id="edit_father_address" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Occupation</strong></td>
                                    <td><input type="text" name="mother_occupation" id="edit_mother_occupation" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_occupation" id="edit_father_occupation" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Official Address</strong></td>
                                    <td><input type="text" name="mother_official_address" id="edit_mother_official_address" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_official_address" id="edit_father_official_address" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Annual Income</strong></td>
                                    <td><input type="text" name="mother_income" id="edit_mother_income" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_income" id="edit_father_income" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Email Address</strong></td>
                                    <td><input type="email" name="mother_email" id="edit_mother_email" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="email" name="father_email" id="edit_father_email" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                                <tr>
                                    <td><strong>Mobile No.</strong></td>
                                    <td><input type="text" name="mother_mobile" id="edit_mother_mobile" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                    <td><input type="text" name="father_mobile" id="edit_father_mobile" class="form-control form-control-sm border-0 bg-light-focus"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Religion & Category -->
                    <h6 class="font-heading fw-extrabold text-primary mb-3">Religion & Category:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label-admin">Nationality</label>
                            <input type="text" name="nationality" id="edit_nationality" class="form-control-admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">Religion</label>
                            <input type="text" name="religion" id="edit_religion" class="form-control-admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">Category</label>
                            <input type="text" name="category" id="edit_category" class="form-control-admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">Aadhar No.</label>
                            <input type="text" name="aadhar_no" id="edit_aadhar_no" class="form-control-admin">
                        </div>
                    </div>
                    
                    <!-- Last School Details -->
                    <h6 class="font-heading fw-extrabold text-primary mb-3">Last School Details (If Any):</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label-admin">Name & Address of School</label>
                            <input type="text" name="last_school_name" id="edit_last_school_name" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Attended Classes</label>
                            <input type="text" name="last_school_class" id="edit_last_school_class" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Last School Affiliated to</label>
                            <input type="text" name="last_school_affiliation" id="edit_last_school_affiliation" class="form-control-admin">
                        </div>
                    </div>
                    
                    <!-- Address / Location -->
                    <h6 class="font-heading fw-extrabold text-primary mb-3">Address & Area:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label-admin">Pincode</label>
                            <input type="text" name="pincode" id="edit_pincode" class="form-control-admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">City</label>
                            <input type="text" name="city" id="edit_city" class="form-control-admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">State</label>
                            <input type="text" name="state" id="edit_state" class="form-control-admin">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">Country</label>
                            <input type="text" name="country" id="edit_country" class="form-control-admin" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label-admin">Full Address</label>
                            <textarea name="address" id="edit_address" class="form-control-admin" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <!-- Photo & Follow-up Details -->
                    <h6 class="font-heading fw-extrabold text-primary mb-3">Lead Status & Uploads:</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-admin">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-control-admin" required>
                                <?php foreach ($counts as $t_name => $t_count): ?>
                                    <option value="<?php echo $t_name; ?>"><?php echo $t_name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Remark</label>
                            <input type="text" name="remark" id="edit_remark" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin">Photo <span class="text-xxs text-muted">(jpeg, jpg, png, webp allowed)</span></label>
                            <input type="file" name="photo" class="form-control-admin" accept="image/*">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer modal-footer-admin p-3">
                    <button type="button" class="btn btn-admin-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary font-heading px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewLeadModal" tabindex="-1" aria-labelledby="viewLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-admin py-3 px-4">
                <h5 class="modal-title font-heading fw-bold text-dark" id="viewLeadModalLabel">Lead Inquiry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-xs">
                <div class="row g-3 align-items-center mb-4 pb-3 border-bottom">
                    <div class="col-sm-3 text-center">
                        <img id="view_photo" src="" alt="Profile Photo" class="img-thumbnail rounded-circle shadow-sm" style="width:100px; height:100px; object-fit:cover; display:none;">
                        <div id="view_photo_placeholder" class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary mx-auto shadow-sm" style="width:100px; height:100px; font-size:32px; font-weight:700;"></div>
                    </div>
                    <div class="col-sm-9">
                        <h4 id="view_fullname" class="fw-bold mb-1 text-primary"></h4>
                        <p class="text-muted mb-2" style="font-size: 13px;">Applying for: <strong id="view_class" class="text-dark"></strong></p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-secondary py-1.5 px-3 rounded-pill text-xxs font-heading" id="view_source_badge"></span>
                            <span class="badge py-1.5 px-3 rounded-pill text-xxs font-heading" id="view_status_badge"></span>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="font-heading fw-bold border-bottom pb-2 mb-2 text-dark">Personal Information</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td style="width:120px;" class="text-muted">Mobile No.</td><td class="fw-semibold" id="view_mobile"></td></tr>
                            <tr><td class="text-muted">Email</td><td id="view_email"></td></tr>
                            <tr><td class="text-muted">Gender</td><td id="view_gender" class="text-capitalize"></td></tr>
                            <tr><td class="text-muted">DOB</td><td id="view_dob"></td></tr>
                            <tr><td class="text-muted">Referred By</td><td id="view_referred_by"></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="font-heading fw-bold border-bottom pb-2 mb-2 text-dark">Location & Info</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td style="width:120px;" class="text-muted">Address</td><td id="view_address"></td></tr>
                            <tr><td class="text-muted">City</td><td id="view_city"></td></tr>
                            <tr><td class="text-muted">State</td><td id="view_state"></td></tr>
                            <tr><td class="text-muted">Pincode</td><td id="view_pincode"></td></tr>
                        </table>
                    </div>
                    
                    <div class="col-12 mt-3">
                        <h6 class="font-heading fw-bold border-bottom pb-2 mb-2 text-dark">Parents details</h6>
                        <div class="row g-3">
                            <div class="col-sm-6 border-end">
                                <span class="fw-bold text-secondary d-block mb-1">Father/Guardian</span>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td style="width:120px;" class="text-muted">Name</td><td id="view_father_name"></td></tr>
                                    <tr><td class="text-muted">Mobile No.</td><td id="view_father_mobile"></td></tr>
                                    <tr><td class="text-muted">Qualification</td><td id="view_father_qualification"></td></tr>
                                    <tr><td class="text-muted">Occupation</td><td id="view_father_occupation"></td></tr>
                                </table>
                            </div>
                            <div class="col-sm-6">
                                <span class="fw-bold text-secondary d-block mb-1">Mother</span>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td style="width:120px;" class="text-muted">Name</td><td id="view_mother_name"></td></tr>
                                    <tr><td class="text-muted">Mobile No.</td><td id="view_mother_mobile"></td></tr>
                                    <tr><td class="text-muted">Qualification</td><td id="view_mother_qualification"></td></tr>
                                    <tr><td class="text-muted">Occupation</td><td id="view_mother_occupation"></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 mt-3">
                        <h6 class="font-heading fw-bold border-bottom pb-2 mb-2 text-dark">Lead History & Follow Up</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td style="width:140px;" class="text-muted">Created By</td><td id="view_created_by"></td></tr>
                            <tr><td class="text-muted">Scheduled Follow Up</td><td id="view_scheduled_at" class="fw-semibold"></td></tr>
                            <tr><td class="text-muted">Remark</td><td id="view_remark" class="text-dark bg-light p-2 rounded"></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-footer-admin p-3">
                <button type="button" class="btn btn-admin-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Lead Modal -->
<div class="modal fade" id="assignLeadModal" tabindex="-1" aria-labelledby="assignLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header modal-header-admin py-2 px-3">
                <h6 class="modal-title font-heading fw-bold" id="assignLeadModalLabel">Assign Lead</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="assigned.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="id" id="assign_id">
                
                <div class="modal-body p-3">
                    <label class="form-label-admin">Select Staff Member</label>
                    <select name="assigned_to" id="assign_user" class="form-control-admin" required>
                        <option value="0">Unassigned (None)</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo sanitize($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['username'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer modal-footer-admin p-2">
                    <button type="button" class="btn btn-xs btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-xs btn-primary font-heading px-3">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule / Remark / Status Modal -->
<div class="modal fade" id="scheduleLeadModal" tabindex="-1" aria-labelledby="scheduleLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:450px;">
        <div class="modal-content">
            <div class="modal-header modal-header-admin py-2 px-3">
                <h6 class="modal-title font-heading fw-bold" id="scheduleLeadModalLabel">Schedule / Follow-up Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="assigned.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="schedule_id">
                
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label-admin">Scheduled Follow up Time</label>
                        <input type="datetime-local" name="scheduled_at" id="schedule_time" class="form-control-admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-admin">Status</label>
                        <select name="status" id="schedule_status" class="form-control-admin" required>
                            <?php foreach ($counts as $t_name => $t_count): ?>
                                <option value="<?php echo $t_name; ?>"><?php echo $t_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-admin">Remark / Notes</label>
                        <textarea name="remark" id="schedule_remark" class="form-control-admin" rows="3" placeholder="Enter follow-up details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer modal-footer-admin p-2">
                    <button type="button" class="btn btn-xs btn-admin-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-xs btn-primary font-heading px-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="convertLeadForm" action="assigned.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="convert">
    <input type="hidden" name="id" id="convert_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // View Action Click
    document.querySelectorAll('.view-lead-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            fetch('assigned.php?get_lead_details=1&id=' + id)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        document.getElementById('view_fullname').innerText = (data.first_name || '') + ' ' + (data.last_name || '');
                        document.getElementById('view_class').innerText = data.class_name || '—';
                        document.getElementById('view_source_badge').innerText = 'Source: ' + (data.source || 'Direct');
                        document.getElementById('view_status_badge').innerText = data.status;
                        
                        // Status color
                        const colors = {
                            'Interested': 'bg-success', 'not interested': 'bg-danger', 'ADMIN': 'bg-secondary',
                            'follow-up': 'bg-primary', 'call back': 'bg-warning text-dark', 'Intersted': 'bg-dark',
                            'Admission Created': 'bg-info'
                        };
                        document.getElementById('view_status_badge').className = 'badge py-1.5 px-3 rounded-pill text-xxs font-heading ' + (colors[data.status] || 'bg-light text-muted');
                        
                        document.getElementById('view_mobile').innerText = data.mobile_no || '—';
                        document.getElementById('view_email').innerText = data.email || '—';
                        document.getElementById('view_gender').innerText = data.gender || '—';
                        document.getElementById('view_dob').innerText = data.dob || '—';
                        document.getElementById('view_referred_by').innerText = data.referred_by || '—';
                        document.getElementById('view_address').innerText = data.address || '—';
                        document.getElementById('view_city').innerText = data.city || '—';
                        document.getElementById('view_state').innerText = data.state || '—';
                        document.getElementById('view_pincode').innerText = data.pincode || '—';
                        
                        document.getElementById('view_father_name').innerText = data.father_name || '—';
                        document.getElementById('view_father_mobile').innerText = data.father_mobile || '—';
                        document.getElementById('view_father_qualification').innerText = data.father_qualification || '—';
                        document.getElementById('view_father_occupation').innerText = data.father_occupation || '—';
                        
                        document.getElementById('view_mother_name').innerText = data.mother_name || '—';
                        document.getElementById('view_mother_mobile').innerText = data.mother_mobile || '—';
                        document.getElementById('view_mother_qualification').innerText = data.mother_qualification || '—';
                        document.getElementById('view_mother_occupation').innerText = data.mother_occupation || '—';
                        
                        document.getElementById('view_created_by').innerText = (data.creator_first || '') + ' ' + (data.creator_last || '') + ' (' + data.created_at + ')';
                        document.getElementById('view_scheduled_at').innerText = data.scheduled_at || 'None Scheduled';
                        document.getElementById('view_remark').innerText = data.remark || 'No remark entered.';
                        
                        const photoImg = document.getElementById('view_photo');
                        const photoPlaceholder = document.getElementById('view_photo_placeholder');
                        if (data.photo) {
                            photoImg.src = '<?php echo BASE_URL; ?>' + data.photo;
                            photoImg.style.display = 'block';
                            photoPlaceholder.style.display = 'none';
                        } else {
                            photoImg.style.display = 'none';
                            photoPlaceholder.style.display = 'flex';
                            photoPlaceholder.innerText = data.first_name.substr(0,1).toUpperCase() + (data.last_name ? data.last_name.substr(0,1).toUpperCase() : '');
                        }
                        
                        const myModal = new bootstrap.Modal(document.getElementById('viewLeadModal'));
                        myModal.show();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
        });
    });

    // Assign Action Click
    document.querySelectorAll('.assign-lead-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            fetch('assigned.php?get_lead_details=1&id=' + id)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        document.getElementById('assign_id').value = res.data.id;
                        document.getElementById('assign_user').value = res.data.assigned_to || '0';
                        const myModal = new bootstrap.Modal(document.getElementById('assignLeadModal'));
                        myModal.show();
                    }
                });
        });
    });

    // Schedule Action Click
    document.querySelectorAll('.schedule-lead-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            fetch('assigned.php?get_lead_details=1&id=' + id)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        document.getElementById('schedule_id').value = data.id;
                        document.getElementById('schedule_status').value = data.status;
                        document.getElementById('schedule_remark').value = data.remark || '';
                        
                        if (data.scheduled_at) {
                            const d = new Date(data.scheduled_at);
                            const formatted = d.getFullYear() + '-' + 
                                ('0' + (d.getMonth()+1)).slice(-2) + '-' + 
                                ('0' + d.getDate()).slice(-2) + 'T' + 
                                ('0' + d.getHours()).slice(-2) + ':' + 
                                ('0' + d.getMinutes()).slice(-2);
                            document.getElementById('schedule_time').value = formatted;
                        } else {
                            document.getElementById('schedule_time').value = '';
                        }
                        
                        const myModal = new bootstrap.Modal(document.getElementById('scheduleLeadModal'));
                        myModal.show();
                    }
                });
        });
    });

    // Edit Action Click
    document.querySelectorAll('.edit-lead-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            fetch('assigned.php?get_lead_details=1&id=' + id)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        document.getElementById('edit_id').value = data.id;
                        document.getElementById('edit_class_id').value = data.class_id || '';
                        document.getElementById('edit_source').value = data.source || '';
                        document.getElementById('edit_referred_by').value = data.referred_by || '';
                        document.getElementById('edit_first_name').value = data.first_name;
                        document.getElementById('edit_last_name').value = data.last_name || '';
                        document.getElementById('edit_email').value = data.email || '';
                        document.getElementById('edit_mobile_no').value = data.mobile_no;
                        document.getElementById('edit_dob').value = data.dob || '';
                        
                        if (data.gender === 'male') document.getElementById('edit_gender_male').checked = true;
                        else if (data.gender === 'female') document.getElementById('edit_gender_female').checked = true;
                        else if (data.gender === 'other') document.getElementById('edit_gender_other').checked = true;
                        
                        document.getElementById('edit_mother_name').value = data.mother_name || '';
                        document.getElementById('edit_mother_qualification').value = data.mother_qualification || '';
                        document.getElementById('edit_mother_address').value = data.mother_address || '';
                        document.getElementById('edit_mother_occupation').value = data.mother_occupation || '';
                        document.getElementById('edit_mother_official_address').value = data.mother_official_address || '';
                        document.getElementById('edit_mother_income').value = data.mother_income || '';
                        document.getElementById('edit_mother_email').value = data.mother_email || '';
                        document.getElementById('edit_mother_mobile').value = data.mother_mobile || '';
                        
                        document.getElementById('edit_father_name').value = data.father_name || '';
                        document.getElementById('edit_father_qualification').value = data.father_qualification || '';
                        document.getElementById('edit_father_address').value = data.father_address || '';
                        document.getElementById('edit_father_occupation').value = data.father_occupation || '';
                        document.getElementById('edit_father_official_address').value = data.father_official_address || '';
                        document.getElementById('edit_father_income').value = data.father_income || '';
                        document.getElementById('edit_father_email').value = data.father_email || '';
                        document.getElementById('edit_father_mobile').value = data.father_mobile || '';
                        
                        document.getElementById('edit_nationality').value = data.nationality || 'INDIAN';
                        document.getElementById('edit_religion').value = data.religion || '';
                        document.getElementById('edit_category').value = data.category || '';
                        document.getElementById('edit_aadhar_no').value = data.aadhar_no || '';
                        
                        document.getElementById('edit_last_school_name').value = data.last_school_name || '';
                        document.getElementById('edit_last_school_class').value = data.last_school_class || '';
                        document.getElementById('edit_last_school_affiliation').value = data.last_school_affiliation || '';
                        
                        document.getElementById('edit_pincode').value = data.pincode || '';
                        document.getElementById('edit_city').value = data.city || '';
                        document.getElementById('edit_state').value = data.state || '';
                        document.getElementById('edit_country').value = data.country || 'India';
                        document.getElementById('edit_address').value = data.address || '';
                        
                        document.getElementById('edit_status').value = data.status;
                        document.getElementById('edit_remark').value = data.remark || '';
                        
                        const myModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
                        myModal.show();
                    }
                });
        });
    });

    // Convert to Student confirmation
    document.querySelectorAll('.convert-lead-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            Swal.fire({
                title: 'Convert to Student?',
                text: 'This will automatically register a student profile and auth user with default credentials. Continue?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, Register Student!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('convert_id').value = id;
                    document.getElementById('convertLeadForm').submit();
                }
            });
        });
    });
});
</script>

<?php
require_once '../../../includes/footer.php';
?>
