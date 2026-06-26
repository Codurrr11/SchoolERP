<?php
// modules/school/students/dropped.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
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

// ─── POST ACTIONS HANDLING ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: dropped.php');
        exit;
    }

    // Toggle Status
    if ($action === 'toggle_status') {
        $student_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
        $student = $stmt->fetch();

        if ($student) {
            $new_status = ($student['status'] === 'active') ? 'inactive' : 'active';
            $user_status = ($new_status === 'active') ? 'active' : 'inactive';

            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE students SET status = :status WHERE id = :id AND school_id = :school_id")->execute([':status' => $new_status, ':id' => $student_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id AND school_id = :school_id")->execute([':status' => $user_status, ':user_id' => $student['user_id'], ':school_id' => $school_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = "Student status updated successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to update status: " . $e->getMessage();
            }
        }
        header('Location: dropped.php');
        exit;
    }

    // Delete Student (Soft Delete)
    if ($action === 'delete') {
        $student_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND school_id = :school_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
        $student = $stmt->fetch();

        if ($student) {
            try {
                $pdo->beginTransaction();
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE students SET deleted_at = :now WHERE id = :id AND school_id = :school_id")->execute([':now' => $now, ':id' => $student_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET deleted_at = :now WHERE id = :id AND school_id = :school_id")
                    ->execute([':now' => $now, ':id' => $student['user_id'], ':school_id' => $school_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = "Student moved to Trash successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to delete student: " . $e->getMessage();
            }
        }
        header('Location: dropped.php');
        exit;
    }

    // Bulk Delete
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            try {
                $pdo->beginTransaction();
                $now = date('Y-m-d H:i:s');
                $in_clause = implode(',', $ids);

                $stmt = $pdo->query("SELECT user_id FROM students WHERE id IN ($in_clause) AND school_id = $school_id AND deleted_at IS NULL");
                $user_ids = array_column($stmt->fetchAll(), 'user_id');

                $pdo->exec("UPDATE students SET deleted_at = '$now' WHERE id IN ($in_clause) AND school_id = $school_id");
                if (!empty($user_ids)) {
                    $u_in_clause = implode(',', $user_ids);
                    $pdo->exec("UPDATE users SET deleted_at = '$now' WHERE id IN ($u_in_clause) AND school_id = $school_id");
                }
                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " student(s) moved to Trash!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Bulk delete failed: " . $e->getMessage();
            }
        }
        header('Location: dropped.php');
        exit;
    }

    // Edit Student
    if ($action === 'edit') {
        $student_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
        $student = $stmt->fetch();

        if (!$student) {
            $_SESSION['flash_error'] = "Student not found.";
            header('Location: dropped.php');
            exit;
        }

        $user_id = $student['user_id'];
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile_no = trim($_POST['mobile_no'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($first_name) || empty($email) || empty($username)) {
            $_SESSION['flash_error'] = "First Name, Email, and Username are required fields.";
            header('Location: dropped.php');
            exit;
        }

        // Unique username validation
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :user_id");
        $stmt->execute([':username' => $username, ':user_id' => $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Username is already taken.";
            header('Location: dropped.php');
            exit;
        }

        // Upload Directory
        $upload_dir = ROOT_PATH . 'uploads/students/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        // Photo file replacement
        $photo_path = $student['photo'];
        $new_photo_uploaded = false;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'photo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'uploads/students/' . $new_name;
                $new_photo_uploaded = true;
            }
        }

        // DOB certificate file replacement
        $dob_cert_path = $student['dob_certificate'];
        $new_dob_uploaded = false;
        if (isset($_FILES['dob_certificate']) && $_FILES['dob_certificate']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['dob_certificate']['name'], PATHINFO_EXTENSION));
            $new_name = 'dob_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['dob_certificate']['tmp_name'], $upload_dir . $new_name)) {
                $dob_cert_path = 'uploads/students/' . $new_name;
                $new_dob_uploaded = true;
            }
        }

        // Category Certificate file replacement
        $category_cert_path = $student['category_certificate'];
        $new_category_cert_uploaded = false;
        if (isset($_FILES['category_certificate']) && $_FILES['category_certificate']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['category_certificate']['name'], PATHINFO_EXTENSION));
            $new_name = 'cat_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['category_certificate']['tmp_name'], $upload_dir . $new_name)) {
                $category_cert_path = 'uploads/students/' . $new_name;
                $new_category_cert_uploaded = true;
            }
        }

        // Aadhar file replacement
        $aadhar_file_path = $student['aadhar_file'];
        $new_aadhar_file_uploaded = false;
        if (isset($_FILES['aadhar_file']) && $_FILES['aadhar_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['aadhar_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'aadhar_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['aadhar_file']['tmp_name'], $upload_dir . $new_name)) {
                $aadhar_file_path = 'uploads/students/' . $new_name;
                $new_aadhar_file_uploaded = true;
            }
        }

        // TC file replacement
        $tc_file_path = $student['tc_file'];
        $new_tc_file_uploaded = false;
        if (isset($_FILES['tc_file']) && $_FILES['tc_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['tc_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'tc_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['tc_file']['tmp_name'], $upload_dir . $new_name)) {
                $tc_file_path = 'uploads/students/' . $new_name;
                $new_tc_file_uploaded = true;
            }
        }

        // Mother photo replacement
        $mother_photo_path = $student['mother_photo'];
        $new_mother_photo_uploaded = false;
        if (isset($_FILES['mother_photo']) && $_FILES['mother_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['mother_photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'mother_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['mother_photo']['tmp_name'], $upload_dir . $new_name)) {
                $mother_photo_path = 'uploads/students/' . $new_name;
                $new_mother_photo_uploaded = true;
            }
        }

        // Father photo replacement
        $father_photo_path = $student['father_photo'];
        $new_father_photo_uploaded = false;
        if (isset($_FILES['father_photo']) && $_FILES['father_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['father_photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'father_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['father_photo']['tmp_name'], $upload_dir . $new_name)) {
                $father_photo_path = 'uploads/students/' . $new_name;
                $new_father_photo_uploaded = true;
            }
        }

        // Guardian photo replacement
        $guardian_photo_path = $student['guardian_photo'];
        $new_guardian_photo_uploaded = false;
        if (isset($_FILES['guardian_photo']) && $_FILES['guardian_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['guardian_photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'guardian_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['guardian_photo']['tmp_name'], $upload_dir . $new_name)) {
                $guardian_photo_path = 'uploads/students/' . $new_name;
                $new_guardian_photo_uploaded = true;
            }
        }

        try {
            $pdo->beginTransaction();

            $status_posted = $_POST['status'] ?? 'dropped';
            $user_status_val = 'active';
            if ($status_posted === 'inactive' || $status_posted === 'passed' || $status_posted === 'dropped') {
                $user_status_val = 'inactive';
            } elseif ($status_posted === 'suspended') {
                $user_status_val = 'suspended';
            }

            // 1. Update Auth User
            $gender_val = !empty($_POST['gender']) ? strtolower($_POST['gender']) : null;
            $dob_val = !empty($_POST['dob']) ? $_POST['dob'] : null;

            if (!empty($password)) {
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        username = :username,
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        password = :password,
                        gender = :gender,
                        dob = :dob,
                        status = :status
                    WHERE id = :id AND school_id = :school_id
                ");
                $stmt->execute([
                    ':username' => $username,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':phone' => $mobile_no,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':gender' => $gender_val,
                    ':dob' => $dob_val,
                    ':status' => $user_status_val,
                    ':id' => $user_id,
                    ':school_id' => $school_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        username = :username,
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        gender = :gender,
                        dob = :dob,
                        status = :status
                    WHERE id = :id AND school_id = :school_id
                ");
                $stmt->execute([
                    ':username' => $username,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':phone' => $mobile_no,
                    ':gender' => $gender_val,
                    ':dob' => $dob_val,
                    ':status' => $user_status_val,
                    ':id' => $user_id,
                    ':school_id' => $school_id
                ]);
            }

            // 2. Update Student details
            $stmt_student_update = $pdo->prepare("
                UPDATE students SET
                    session_id = :session_id,
                    class_id = :class_id,
                    section_id = :section_id,
                    apaar_id = :apaar_id,
                    pen_no = :pen_no,
                    registration_no_prefix = :registration_no_prefix,
                    registration_no = :registration_no,
                    enrollment_no_prefix = :enrollment_no_prefix,
                    enrollment_no = :enrollment_no,
                    sr_no_prefix = :sr_no_prefix,
                    sr_no = :sr_no,
                    general_reg_no = :general_reg_no,
                    admission_no_prefix = :admission_no_prefix,
                    admission_no = :admission_no,
                    admission_date = :admission_date,
                    srn_no = :srn_no,
                    roll_no = :roll_no,
                    stream = :stream,
                    education_medium = :education_medium,
                    photo = :photo,
                    referred_by = :referred_by,
                    is_rte = :is_rte,
                    enrolled_session = :enrolled_session,
                    enrolled_class_id = :enrolled_class_id,
                    enrolled_year = :enrolled_year,
                    special_needs = :special_needs,
                    is_bpl = :is_bpl,
                    house_block = :house_block,
                    first_name = :first_name,
                    last_name = :last_name,
                    father_name = :father_name,
                    mobile_no = :mobile_no,
                    alternate_no = :alternate_no,
                    whatsapp_no = :whatsapp_no,
                    email = :email,
                    gender = :gender,
                    blood_group = :blood_group,
                    height = :height,
                    weight = :weight,
                    dob = :dob,
                    place_of_birth = :place_of_birth,
                    dob_certificate = :dob_certificate,
                    dob_certificate_no = :dob_certificate_no,
                    total_fees = :total_fees,
                    total_paid = :total_paid,
                    total_discount = :total_discount,
                    fine_amount = :fine_amount,
                    biometric_code = :biometric_code,
                    status = :status,
                    income_app_no = :income_app_no,
                    caste_app_no = :caste_app_no,
                    domicile_app_no = :domicile_app_no,
                    nationality = :nationality,
                    religion = :religion,
                    category = :category,
                    caste = :caste,
                    category_certificate = :category_certificate,
                    aadhar_no = :aadhar_no,
                    aadhar_file = :aadhar_file,
                    tc_no = :tc_no,
                    tc_issue_date = :tc_issue_date,
                    tc_file = :tc_file,
                    scholarship_id = :scholarship_id,
                    scholarship_password = :scholarship_password,
                    govt_student_id = :govt_student_id,
                    govt_family_id = :govt_family_id,
                    samagra_id = :samagra_id,
                    bank_name = :bank_name,
                    bank_branch = :bank_branch,
                    ifsc_code = :ifsc_code,
                    bank_account_holder = :bank_account_holder,
                    bank_account_no = :bank_account_no,
                    pan_no = :pan_no,
                    mother_name = :mother_name,
                    mother_qualification = :mother_qualification,
                    mother_address = :mother_address,
                    mother_occupation = :mother_occupation,
                    mother_official_address = :mother_official_address,
                    mother_income = :mother_income,
                    mother_email = :mother_email,
                    mother_mobile = :mother_mobile,
                    mother_aadhar = :mother_aadhar,
                    mother_photo = :mother_photo,
                    father_qualification = :father_qualification,
                    father_address = :father_address,
                    father_occupation = :father_occupation,
                    father_official_address = :father_official_address,
                    father_income = :father_income,
                    father_email = :father_email,
                    father_mobile = :father_mobile,
                    father_aadhar = :father_aadhar,
                    father_photo = :father_photo,
                    guardian_name = :guardian_name,
                    guardian_qualification = :guardian_qualification,
                    guardian_address = :guardian_address,
                    guardian_occupation = :guardian_occupation,
                    guardian_official_address = :guardian_official_address,
                    guardian_income = :guardian_income,
                    guardian_email = :guardian_email,
                    guardian_mobile = :guardian_mobile,
                    guardian_aadhar = :guardian_aadhar,
                    guardian_photo = :guardian_photo
                WHERE id = :id AND school_id = :school_id
            ");

            $stmt_student_update->execute([
                ':session_id' => !empty($_POST['session_id']) ? intval($_POST['session_id']) : null,
                ':class_id' => !empty($_POST['class_id']) ? intval($_POST['class_id']) : null,
                ':section_id' => !empty($_POST['section_id']) ? intval($_POST['section_id']) : null,
                ':apaar_id' => !empty($_POST['apaar_id']) ? trim($_POST['apaar_id']) : null,
                ':pen_no' => !empty($_POST['pen_no']) ? trim($_POST['pen_no']) : null,
                ':registration_no_prefix' => !empty($_POST['registration_no_prefix']) ? trim($_POST['registration_no_prefix']) : null,
                ':registration_no' => !empty($_POST['registration_no']) ? trim($_POST['registration_no']) : null,
                ':enrollment_no_prefix' => !empty($_POST['enrollment_no_prefix']) ? trim($_POST['enrollment_no_prefix']) : null,
                ':enrollment_no' => !empty($_POST['enrollment_no']) ? trim($_POST['enrollment_no']) : null,
                ':sr_no_prefix' => !empty($_POST['sr_no_prefix']) ? trim($_POST['sr_no_prefix']) : null,
                ':sr_no' => !empty($_POST['sr_no']) ? trim($_POST['sr_no']) : null,
                ':general_reg_no' => !empty($_POST['general_reg_no']) ? trim($_POST['general_reg_no']) : null,
                ':admission_no_prefix' => !empty($_POST['admission_no_prefix']) ? get_flat_string($_POST['admission_no_prefix']) : null,
                ':admission_no' => !empty($_POST['admission_no']) ? get_flat_string($_POST['admission_no']) : null,
                ':admission_date' => !empty($_POST['admission_date']) ? $_POST['admission_date'] : null,
                ':srn_no' => !empty($_POST['srn_no']) ? get_flat_string($_POST['srn_no']) : null,
                ':roll_no' => !empty($_POST['roll_no']) ? get_flat_string($_POST['roll_no']) : null,
                ':stream' => !empty($_POST['stream']) ? $_POST['stream'] : null,
                ':education_medium' => !empty($_POST['education_medium']) ? $_POST['education_medium'] : null,
                ':photo' => $photo_path,
                ':referred_by' => !empty($_POST['referred_by']) ? $_POST['referred_by'] : null,
                ':is_rte' => !empty($_POST['is_rte']) ? $_POST['is_rte'] : 'no',
                ':enrolled_session' => !empty($_POST['enrolled_session']) ? trim($_POST['enrolled_session']) : null,
                ':enrolled_class_id' => !empty($_POST['enrolled_class_id']) ? intval($_POST['enrolled_class_id']) : null,
                ':enrolled_year' => !empty($_POST['enrolled_year']) ? $_POST['enrolled_year'] : null,
                ':special_needs' => !empty($_POST['special_needs']) ? $_POST['special_needs'] : 'no',
                ':is_bpl' => !empty($_POST['is_bpl']) ? $_POST['is_bpl'] : 'no',
                ':house_block' => !empty($_POST['house_block']) ? $_POST['house_block'] : null,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':father_name' => !empty($_POST['father_name']) ? trim($_POST['father_name']) : null,
                ':mobile_no' => $mobile_no,
                ':alternate_no' => !empty($_POST['alternate_no']) ? trim($_POST['alternate_no']) : null,
                ':whatsapp_no' => !empty($_POST['whatsapp_no']) ? trim($_POST['whatsapp_no']) : null,
                ':email' => $email,
                ':gender' => $gender_val,
                ':blood_group' => !empty($_POST['blood_group']) ? $_POST['blood_group'] : null,
                ':height' => !empty($_POST['height']) ? trim($_POST['height']) : null,
                ':weight' => !empty($_POST['weight']) ? trim($_POST['weight']) : null,
                ':dob' => $dob_val,
                ':place_of_birth' => !empty($_POST['place_of_birth']) ? trim($_POST['place_of_birth']) : null,
                ':dob_certificate' => $dob_cert_path,
                ':dob_certificate_no' => !empty($_POST['dob_certificate_no']) ? trim($_POST['dob_certificate_no']) : null,
                ':total_fees' => !empty($_POST['total_fees']) ? floatval($_POST['total_fees']) : 0.00,
                ':total_paid' => !empty($_POST['total_paid']) ? floatval($_POST['total_paid']) : 0.00,
                ':total_discount' => !empty($_POST['total_discount']) ? floatval($_POST['total_discount']) : 0.00,
                ':fine_amount' => !empty($_POST['fine_amount']) ? floatval($_POST['fine_amount']) : 0.00,
                ':biometric_code' => !empty($_POST['biometric_code']) ? trim($_POST['biometric_code']) : null,
                ':status' => $status_posted,
                ':income_app_no' => !empty($_POST['income_app_no']) ? trim($_POST['income_app_no']) : null,
                ':caste_app_no' => !empty($_POST['caste_app_no']) ? trim($_POST['caste_app_no']) : null,
                ':domicile_app_no' => !empty($_POST['domicile_app_no']) ? trim($_POST['domicile_app_no']) : null,
                ':nationality' => !empty($_POST['nationality']) ? trim($_POST['nationality']) : 'INDIAN',
                ':religion' => !empty($_POST['religion']) ? trim($_POST['religion']) : null,
                ':category' => !empty($_POST['category']) ? trim($_POST['category']) : null,
                ':caste' => !empty($_POST['caste']) ? trim($_POST['caste']) : null,
                ':category_certificate' => $category_cert_path,
                ':aadhar_no' => !empty($_POST['aadhar_no']) ? trim($_POST['aadhar_no']) : null,
                ':aadhar_file' => $aadhar_file_path,
                ':tc_no' => !empty($_POST['tc_no']) ? trim($_POST['tc_no']) : null,
                ':tc_issue_date' => !empty($_POST['tc_issue_date']) ? $_POST['tc_issue_date'] : null,
                ':tc_file' => $tc_file_path,
                ':scholarship_id' => !empty($_POST['scholarship_id']) ? trim($_POST['scholarship_id']) : null,
                ':scholarship_password' => !empty($_POST['scholarship_password']) ? trim($_POST['scholarship_password']) : null,
                ':govt_student_id' => !empty($_POST['govt_student_id']) ? trim($_POST['govt_student_id']) : null,
                ':govt_family_id' => !empty($_POST['govt_family_id']) ? trim($_POST['govt_family_id']) : null,
                ':samagra_id' => !empty($_POST['samagra_id']) ? trim($_POST['samagra_id']) : null,
                ':bank_name' => !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null,
                ':bank_branch' => !empty($_POST['bank_branch']) ? trim($_POST['bank_branch']) : null,
                ':ifsc_code' => !empty($_POST['ifsc_code']) ? trim($_POST['ifsc_code']) : null,
                ':bank_account_holder' => !empty($_POST['bank_account_holder']) ? trim($_POST['bank_account_holder']) : null,
                ':bank_account_no' => !empty($_POST['bank_account_no']) ? trim($_POST['bank_account_no']) : null,
                ':pan_no' => !empty($_POST['pan_no']) ? trim($_POST['pan_no']) : null,
                ':mother_name' => !empty($_POST['mother_name']) ? trim($_POST['mother_name']) : null,
                ':mother_qualification' => !empty($_POST['mother_qualification']) ? trim($_POST['mother_qualification']) : null,
                ':mother_address' => !empty($_POST['mother_address']) ? trim($_POST['mother_address']) : null,
                ':mother_occupation' => !empty($_POST['mother_occupation']) ? trim($_POST['mother_occupation']) : null,
                ':mother_official_address' => !empty($_POST['mother_official_address']) ? trim($_POST['mother_official_address']) : null,
                ':mother_income' => !empty($_POST['mother_income']) ? trim($_POST['mother_income']) : null,
                ':mother_email' => !empty($_POST['mother_email']) ? trim($_POST['mother_email']) : null,
                ':mother_mobile' => !empty($_POST['mother_mobile']) ? trim($_POST['mother_mobile']) : null,
                ':mother_aadhar' => !empty($_POST['mother_aadhar']) ? trim($_POST['mother_aadhar']) : null,
                ':mother_photo' => $mother_photo_path,
                ':father_qualification' => !empty($_POST['father_qualification']) ? trim($_POST['father_qualification']) : null,
                ':father_address' => !empty($_POST['father_address']) ? trim($_POST['father_address']) : null,
                ':father_occupation' => !empty($_POST['father_occupation']) ? trim($_POST['father_occupation']) : null,
                ':father_official_address' => !empty($_POST['father_official_address']) ? trim($_POST['father_official_address']) : null,
                ':father_income' => !empty($_POST['father_income']) ? trim($_POST['father_income']) : null,
                ':father_email' => !empty($_POST['father_email']) ? trim($_POST['father_email']) : null,
                ':father_mobile' => !empty($_POST['father_mobile']) ? trim($_POST['father_mobile']) : null,
                ':father_aadhar' => !empty($_POST['father_aadhar']) ? trim($_POST['father_aadhar']) : null,
                ':father_photo' => $father_photo_path,
                ':guardian_name' => !empty($_POST['guardian_name']) ? trim($_POST['guardian_name']) : null,
                ':guardian_qualification' => !empty($_POST['guardian_qualification']) ? trim($_POST['guardian_qualification']) : null,
                ':guardian_address' => !empty($_POST['guardian_address']) ? trim($_POST['guardian_address']) : null,
                ':guardian_occupation' => !empty($_POST['guardian_occupation']) ? trim($_POST['guardian_occupation']) : null,
                ':guardian_official_address' => !empty($_POST['guardian_official_address']) ? trim($_POST['guardian_official_address']) : null,
                ':guardian_income' => !empty($_POST['guardian_income']) ? trim($_POST['guardian_income']) : null,
                ':guardian_email' => !empty($_POST['guardian_email']) ? trim($_POST['guardian_email']) : null,
                ':guardian_mobile' => !empty($_POST['guardian_mobile']) ? trim($_POST['guardian_mobile']) : null,
                ':guardian_aadhar' => !empty($_POST['guardian_aadhar']) ? trim($_POST['guardian_aadhar']) : null,
                ':guardian_photo' => $guardian_photo_path,
                ':id' => $student_id,
                ':school_id' => $school_id
            ]);

            // 3. Create Qualifications details securely
            $qual_names   = $_POST['qualification'] ?? [];
            $qual_years   = $_POST['passing_year'] ?? [];
            $qual_rolls   = $_POST['roll_no'] ?? [];
            $qual_marks   = $_POST['obtained_marks'] ?? [];
            $qual_pcts    = $_POST['percentage'] ?? [];
            $qual_subs    = $_POST['subjects'] ?? [];
            $qual_schools = $_POST['school_college_name'] ?? [];

            if (!is_array($qual_names)) $qual_names = [$qual_names];

            $stmt_qual = $pdo->prepare("
                INSERT INTO student_qualifications (student_id, qualification, passing_year, roll_no, obtained_marks, percentage, subjects, school_college_name)
                VALUES (:student_id, :qualification, :passing_year, :roll_no, :obtained_marks, :percentage, :subjects, :school_college_name)
            ");

            for ($i = 0; $i < count($qual_names); $i++) {
                $flatten = function ($val) {
                    while (is_array($val)) {
                        $val = reset($val);
                    }
                    return is_scalar($val) ? trim((string)$val) : '';
                };

                $q_name   = $flatten($qual_names[$i] ?? '');
                $q_year   = $flatten($qual_years[$i] ?? '');
                $q_roll   = $flatten($qual_rolls[$i] ?? '');
                $q_mark   = $flatten($qual_marks[$i] ?? '');
                $q_pct    = $flatten($qual_pcts[$i] ?? '');
                $q_sub    = $flatten($qual_subs[$i] ?? '');
                $q_school = $flatten($qual_schools[$i] ?? '');

                if ($q_name !== '') {
                    $stmt_qual->execute([
                        ':student_id'          => $student_id,
                        ':qualification'       => $q_name,
                        ':passing_year'        => $q_year,
                        ':roll_no'             => $q_roll,
                        ':obtained_marks'      => $q_mark,
                        ':percentage'          => $q_pct,
                        ':subjects'            => $q_sub,
                        ':school_college_name' => $q_school
                    ]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Student updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($new_photo_uploaded && $photo_path && file_exists($upload_dir . basename($photo_path))) @unlink($upload_dir . basename($photo_path));
            if ($new_dob_uploaded && $dob_cert_path && file_exists($upload_dir . basename($dob_cert_path))) @unlink($upload_dir . basename($dob_cert_path));
            $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        }
        header('Location: dropped.php');
        exit;
    }
}

// ─── QUERY DATA ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.*, u.username as u_name, c.name as class_name, sec.name as section_name
    FROM   students s
    JOIN   users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE  s.school_id = :school_id
      AND  s.deleted_at IS NULL
      AND  s.status = 'dropped'
    ORDER  BY s.id DESC
");
$stmt->execute([':school_id' => $school_id]);
$students = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-1 font-heading fw-extrabold">Dropped Students</h2>
        <p class="text-xs text-muted mb-0">Directory of students who have dropped out of school.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">

            <!-- Toolbar -->
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="teacher-header-btn btn-red" id="bulkDeleteBtn" disabled title="Move Selected to Trash">
                        <i class="ph-light ph-trash"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                    <div class="table-search-box m-0">
                        <i class="ph-light ph-magnifying-glass"></i>
                        <input type="text" placeholder="Search dropped students..." id="studentSearchInput" class="table-search-input">
                    </div>
                    <div class="teacher-total-badge">
                        <i class="ph-light ph-user-minus"></i>
                        Total Students: <span class="count-num"><?php echo count($students); ?></span>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($students)): ?>
                        <div class="p-5 text-center">
                            <div class="trash-empty-icon mx-auto mb-3">
                                <i class="ph-light ph-users"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No Dropped Students</h5>
                            <p class="text-xs text-muted mb-0">No student records are currently marked as Dropped.</p>
                        </div>
                    <?php else: ?>
                        <form id="bulkDeleteForm" action="dropped.php" method="POST" class="d-none">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="bulk_delete">
                            <div id="bulkDeleteInputs"></div>
                        </form>

                        <table class="teacher-table table-premium mb-0 align-middle" id="studentsTable">
                            <thead>
                                <tr>
                                    <th class="th-w-46"><input type="checkbox" class="table-checkbox" id="selectAllCheckbox"></th>
                                    <th class="th-w-50">#</th>
                                    <th>Admission No.</th>
                                    <th>Roll No.</th>
                                    <th>Student</th>
                                    <th>Fees</th>
                                    <th>Status</th>
                                    <th>Remark</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
                                    <th class="th-w-120">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="studentsTableBody">
                                <?php $idx = 1;
                                foreach ($students as $s): ?>
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="<?php echo $s['id']; ?>" class="table-checkbox student-select-checkbox"></td>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <td><span class="fw-bold"><?php echo sanitize(($s['admission_no_prefix'] ?? '') . $s['admission_no']); ?></span></td>
                                        <td><span class="mono"><?php echo sanitize($s['roll_no'] ?? '—'); ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($s['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . sanitize($s['photo']); ?>" alt="Profile" class="student-avatar">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder">
                                                        <?php echo strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'] ?? '', 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column">
                                                    <a href="view.php?id=<?php echo $s['id']; ?>" class="student-name-link">
                                                        <?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>
                                                    </a>
                                                    <span class="text-xs text-muted">Username: <strong><?php echo sanitize($s['u_name']); ?></strong></span>
                                                    <span class="text-xs text-muted">Classes: <strong><?php echo sanitize(($s['class_name'] ?? '') . '-' . ($s['section_name'] ?? '')); ?></strong></span>
                                                    <span class="text-xs text-muted">Father name: <?php echo sanitize($s['father_name'] ?? '—'); ?></span>
                                                    <span class="text-xs text-muted">Mobile: <?php echo sanitize($s['mobile_no'] ?? '—'); ?></span>
                                                    <span class="text-xs text-muted">WhatsApp: <a href="https://wa.me/<?php echo sanitize($s['whatsapp_no'] ?? ''); ?>" target="_blank" class="whatsapp-link text-xxs">Send <i class="ph-fill ph-whatsapp-logo"></i></a></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column text-xs text-secondary gap-0.5">
                                                <span class="text-primary font-semibold">Total Fees: <?php echo number_format($s['total_fees'], 2); ?></span>
                                                <span class="text-success">Total Paid: <?php echo number_format($s['total_paid'], 2); ?></span>
                                                <span class="text-info">Total Discount: <?php echo number_format($s['total_discount'], 2); ?></span>
                                                <span class="text-warning">Fine Amount: <?php echo number_format($s['fine_amount'], 2); ?></span>
                                                <?php
                                                $balance = $s['total_fees'] - $s['total_paid'] - $s['total_discount'] + $s['fine_amount'];
                                                if ($balance > 0): ?>
                                                    <span class="text-danger font-semibold">Total Balance: <?php echo number_format($balance, 2); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success w-fit px-2 py-0.5 mt-1 rounded text-xxs">No Balance</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <form action="dropped.php" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                <div class="form-check form-switch teacher-status-switch p-0 m-0">
                                                    <input class="form-check-input ms-0" type="checkbox" role="switch" <?php echo ($s['status'] === 'active') ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                </div>
                                            </form>
                                        </td>
                                        <td><span class="text-xs"><?php echo sanitize($s['remark'] ?? '—'); ?></span></td>
                                        <td><span class="text-xxs text-muted"><?php echo date('d M, Y<br>h:i a', strtotime($s['created_at'])); ?></span></td>
                                        <td><span class="text-xxs text-muted"><?php echo date('d M, Y<br>h:i a', strtotime($s['updated_at'])); ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <a href="view.php?id=<?php echo $s['id']; ?>" class="teacher-action-btn action-view" title="View Profile"><i class="ph-light ph-eye"></i></a>
                                                <button type="button" class="teacher-action-btn action-edit edit-student-btn" data-id="<?php echo $s['id']; ?>" title="Edit Student"><i class="ph-light ph-pencil-simple"></i></button>
                                                <button type="button" class="teacher-action-btn action-delete delete-student-btn" data-id="<?php echo $s['id']; ?>" data-name="<?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>" title="Delete Student"><i class="ph-light ph-trash"></i></button>
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

<form id="deleteStudentForm" action="dropped.php" method="POST" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_student_id">
</form>

<!-- Include Edit Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editStudentModalLabel">Edit Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dropped.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_student_id">

                <div class="modal-body">
                    <h6 class="text-primary text-uppercase mb-3">Admission Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">APAAR ID</label>
                                <input type="text" name="apaar_id" id="edit_apaar_id" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">PEN No.</label>
                                <input type="text" name="pen_no" id="edit_pen_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Registration No.</label>
                                <div class="input-group">
                                    <input type="text" name="registration_no_prefix" id="edit_registration_no_prefix" class="form-control-admin w-25" placeholder="Prefix">
                                    <input type="text" name="registration_no" id="edit_registration_no" class="form-control-admin w-75" placeholder="Number">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Enrollment No.</label>
                                <div class="input-group">
                                    <input type="text" name="enrollment_no_prefix" id="edit_enrollment_no_prefix" class="form-control-admin w-25">
                                    <input type="text" name="enrollment_no" id="edit_enrollment_no" class="form-control-admin w-75">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">SR No.</label>
                                <div class="input-group">
                                    <input type="text" name="sr_no_prefix" id="edit_sr_no_prefix" class="form-control-admin w-25">
                                    <input type="text" name="sr_no" id="edit_sr_no" class="form-control-admin w-75">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">General Reg No.</label>
                                <input type="text" name="general_reg_no" id="edit_general_reg_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Admission No.</label>
                                <div class="input-group">
                                    <input type="text" name="admission_no_prefix" id="edit_admission_no_prefix" class="form-control-admin w-25">
                                    <input type="text" name="admission_no" id="edit_admission_no" class="form-control-admin w-75">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Admission Date</label>
                                <input type="date" name="admission_date" id="edit_admission_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">SRN No.</label>
                                <input type="text" name="srn_no" id="edit_srn_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Roll No.</label>
                                <input type="text" name="roll_no" id="edit_roll_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select Stream</label>
                                <select name="stream" id="edit_stream" class="form-control-admin">
                                    <option value="">-- Select Stream --</option>
                                    <option value="Science">Science</option>
                                    <option value="Commerce">Commerce</option>
                                    <option value="Arts">Arts</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select Medium</label>
                                <select name="education_medium" id="edit_education_medium" class="form-control-admin">
                                    <option value="">-- Select medium --</option>
                                    <option value="English">English</option>
                                    <option value="Hindi">Hindi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Student's Photo</label>
                                <input type="file" name="photo" class="form-control-admin" accept="image/*">
                                <small class="text-xs text-muted" id="edit_photo_help"></small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Referred By</label>
                                <select name="referred_by" id="edit_referred_by" class="form-control-admin">
                                    <option value="">-- Select --</option>
                                    <option value="Direct">Direct</option>
                                    <option value="Staff">Staff</option>
                                    <option value="Agent">Agent</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Is RTE Student?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="is_rte" id="edit_is_rte_yes" value="yes" class="form-check-input"> Yes</label>
                                    <label class="form-check-label"><input type="radio" name="is_rte" id="edit_is_rte_no" value="no" class="form-check-input"> No</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Session</label>
                                <select name="session_id" id="edit_session_id" class="form-control-admin">
                                    <?php foreach ($all_sessions as $ses): ?>
                                        <option value="<?php echo $ses['id']; ?>"><?php echo sanitize($ses['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Student Status</label>
                                <select name="status" id="edit_status" class="form-control-admin" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="passed">Passed</option>
                                    <option value="dropped">Dropped</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Enrolled Session</label>
                                <input type="text" name="enrolled_session" id="edit_enrolled_session" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Enrolled Classes</label>
                                <select name="enrolled_class_id" id="edit_enrolled_class_select" class="form-control-admin">
                                    <option value="">-- Select Classes --</option>
                                    <?php foreach ($all_classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Enrolled Year</label>
                                <select name="enrolled_year" id="edit_enrolled_year" class="form-control-admin">
                                    <option value="">-- Select year --</option>
                                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Child with special needs?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="special_needs" id="edit_special_needs_yes" value="yes" class="form-check-input"> Yes</label>
                                    <label class="form-check-label"><input type="radio" name="special_needs" id="edit_special_needs_no" value="no" class="form-check-input"> No</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Is BPL Student?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="is_bpl" id="edit_is_bpl_yes" value="yes" class="form-check-input"> Yes</label>
                                    <label class="form-check-label"><input type="radio" name="is_bpl" id="edit_is_bpl_no" value="no" class="form-check-input"> No</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select house/block</label>
                                <input type="text" name="house_block" id="edit_house_block" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Academic Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Select Classes <span class="text-danger">*</span></label>
                                <select name="class_id" id="edit_student_class_select" class="form-control-admin" required>
                                    <option value="">-- Select Classes --</option>
                                    <?php foreach ($all_classes as $c):
                                        $c_secs = $sections_by_class[$c['id']] ?? [];
                                        $sec_data_attr = htmlspecialchars(json_encode($c_secs), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <option value="<?php echo $c['id']; ?>" data-sections="<?php echo $sec_data_attr; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Select Sections <span class="text-danger">*</span></label>
                                <select name="section_id" id="edit_student_section_select" class="form-control-admin" required>
                                    <option value="">-- Select Sections --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Student Personal Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Name</label>
                                <input type="text" name="father_name" id="edit_father_name" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mobile Number</label>
                                <input type="text" name="mobile_no" id="edit_mobile_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Alternate Mobile Number</label>
                                <input type="text" name="alternate_no" id="edit_alternate_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">WhatsApp Number</label>
                                <input type="text" name="whatsapp_no" id="edit_whatsapp_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="edit_email" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Gender</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="gender" id="edit_gender_male" value="male" class="form-check-input"> Male</label>
                                    <label class="form-check-label"><input type="radio" name="gender" id="edit_gender_female" value="female" class="form-check-input"> Female</label>
                                    <label class="form-check-label"><input type="radio" name="gender" id="edit_gender_other" value="other" class="form-check-input"> Other</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select Blood Group</label>
                                <select name="blood_group" id="edit_blood_group" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Height</label>
                                <input type="text" name="height" id="edit_height" class="form-control-admin" placeholder="e.g. 150cm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Weight</label>
                                <input type="text" name="weight" id="edit_weight" class="form-control-admin" placeholder="e.g. 45kg">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Date of Birth</label>
                                <input type="date" name="dob" id="edit_dob" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Place of birth</label>
                                <input type="text" name="place_of_birth" id="edit_place_of_birth" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Date of Birth Certificate</label>
                                <input type="file" name="dob_certificate" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted" id="edit_dob_cert_help"></small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Date of Birth Certificate Number</label>
                                <input type="text" name="dob_certificate_no" id="edit_dob_certificate_no" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Student Categories Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Income Certificate Application No.</label>
                                <input type="text" name="income_app_no" id="edit_income_app_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Caste Certificate Application No.</label>
                                <input type="text" name="caste_app_no" id="edit_caste_app_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Domicile Certificate Application No.</label>
                                <input type="text" name="domicile_app_no" id="edit_domicile_app_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Nationality</label>
                                <input type="text" name="nationality" id="edit_nationality" class="form-control-admin" value="INDIAN">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select Religion</label>
                                <input type="text" name="religion" id="edit_religion" class="form-control-admin" placeholder="e.g. Hindu, Muslim, Christian">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select Category</label>
                                <input type="text" name="category" id="edit_category" class="form-control-admin" placeholder="e.g. General, OBC, SC, ST">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Caste</label>
                                <input type="text" name="caste" id="edit_caste" class="form-control-admin">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label-admin">Category Certificate (Image/PDF)</label>
                                <input type="file" name="category_certificate" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted" id="edit_category_certificate_help"></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Identity / Transfer Certificate Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Aadhar Card No.</label>
                                <input type="text" name="aadhar_no" id="edit_aadhar_no" class="form-control-admin">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Aadhar file (Image/PDF)</label>
                                <input type="file" name="aadhar_file" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted" id="edit_aadhar_file_help"></small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Transfer Certificate No.</label>
                                <input type="text" name="tc_no" id="edit_tc_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">TC issue Date</label>
                                <input type="date" name="tc_issue_date" id="edit_tc_issue_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">TC file (Image/PDF)</label>
                                <input type="file" name="tc_file" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted" id="edit_tc_file_help"></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Scholarship Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Scholarship ID</label>
                                <input type="text" name="scholarship_id" id="edit_scholarship_id" class="form-control-admin">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Scholarship Password</label>
                                <input type="text" name="scholarship_password" id="edit_scholarship_password" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Government Identification ID:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Government Student ID</label>
                                <input type="text" name="govt_student_id" id="edit_govt_student_id" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Government Family ID</label>
                                <input type="text" name="govt_family_id" id="edit_govt_family_id" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Samagra ID</label>
                                <input type="text" name="samagra_id" id="edit_samagra_id" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Student Bank Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Name</label>
                                <input type="text" name="bank_name" id="edit_bank_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Branch</label>
                                <input type="text" name="bank_branch" id="edit_bank_branch" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">IFSC Code</label>
                                <input type="text" name="ifsc_code" id="edit_ifsc_code" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Account Holder Name</label>
                                <input type="text" name="bank_account_holder" id="edit_bank_account_holder" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Account Number</label>
                                <input type="text" name="bank_account_no" id="edit_bank_account_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">PAN Number</label>
                                <input type="text" name="pan_no" id="edit_pan_no" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Parents / Guardian Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <!-- Mother -->
                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Name</label>
                                <input type="text" name="mother_name" id="edit_mother_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Mobile No.</label>
                                <input type="text" name="mother_mobile" id="edit_mother_mobile" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Aadhaar Card</label>
                                <input type="text" name="mother_aadhar" id="edit_mother_aadhar" class="form-control-admin">
                            </div>

                            <!-- Father -->
                            <div class="col-md-4">
                                <label class="form-label-admin">Father's Mobile No.</label>
                                <input type="text" name="father_mobile" id="edit_father_mobile" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father's Aadhaar Card</label>
                                <input type="text" name="father_aadhar" id="edit_father_aadhar" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Qualification</label>
                                <select name="father_qualification" id="edit_father_qualification" class="form-control-admin py-1 fs-7">
                                    <option value="">Select</option>
                                    <option value="Under Graduate">Under Graduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Post Graduate">Post Graduate</option>
                                    <option value="Doctorate">Doctorate</option>
                                </select>
                            </div>

                            <!-- Guardian -->
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Name</label>
                                <input type="text" name="guardian_name" id="edit_guardian_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Mobile No.</label>
                                <input type="text" name="guardian_mobile" id="edit_guardian_mobile" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Aadhaar Card</label>
                                <input type="text" name="guardian_aadhar" id="edit_guardian_aadhar" class="form-control-admin">
                            </div>

                            <!-- Qualification lists -->
                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Qualification</label>
                                <select name="mother_qualification" id="edit_mother_qualification" class="form-control-admin py-1 fs-7">
                                    <option value="">Select</option>
                                    <option value="Under Graduate">Under Graduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Post Graduate">Post Graduate</option>
                                    <option value="Doctorate">Doctorate</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Qualification</label>
                                <select name="guardian_qualification" id="edit_guardian_qualification" class="form-control-admin py-1 fs-7">
                                    <option value="">Select</option>
                                    <option value="Under Graduate">Under Graduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Post Graduate">Post Graduate</option>
                                    <option value="Doctorate">Doctorate</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Address</label>
                                <textarea name="father_address" id="edit_father_address" class="form-control-admin py-1 fs-7" rows="1"></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Address</label>
                                <textarea name="mother_address" id="edit_mother_address" class="form-control-admin py-1 fs-7" rows="1"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Address</label>
                                <textarea name="guardian_address" id="edit_guardian_address" class="form-control-admin py-1 fs-7" rows="1"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Occupation</label>
                                <input type="text" name="father_occupation" id="edit_father_occupation" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Occupation</label>
                                <input type="text" name="mother_occupation" id="edit_mother_occupation" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Occupation</label>
                                <input type="text" name="guardian_occupation" id="edit_guardian_occupation" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Official Address</label>
                                <textarea name="father_official_address" id="edit_father_official_address" class="form-control-admin py-1 fs-7" rows="1"></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Official Address</label>
                                <textarea name="mother_official_address" id="edit_mother_official_address" class="form-control-admin py-1 fs-7" rows="1"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Official Address</label>
                                <textarea name="guardian_official_address" id="edit_guardian_official_address" class="form-control-admin py-1 fs-7" rows="1"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Annual Income</label>
                                <input type="text" name="father_income" id="edit_father_income" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Annual Income</label>
                                <input type="text" name="mother_income" id="edit_mother_income" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Annual Income</label>
                                <input type="text" name="guardian_income" id="edit_guardian_income" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father Email ID</label>
                                <input type="email" name="father_email" id="edit_father_email" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mother Email ID</label>
                                <input type="email" name="mother_email" id="edit_mother_email" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian Email ID</label>
                                <input type="email" name="guardian_email" id="edit_guardian_email" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Mother's Photo</label>
                                <input type="file" name="mother_photo" class="form-control-admin" accept="image/*">
                                <small class="text-xs text-muted" id="edit_mother_photo_help"></small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Father's Photo</label>
                                <input type="file" name="father_photo" class="form-control-admin" accept="image/*">
                                <small class="text-xs text-muted" id="edit_father_photo_help"></small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Guardian's Photo</label>
                                <input type="file" name="guardian_photo" class="form-control-admin" accept="image/*">
                                <small class="text-xs text-muted" id="edit_guardian_photo_help"></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Student Fee Structure Setup:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label-admin">Total Fees</label>
                                <input type="number" step="0.01" name="total_fees" id="edit_total_fees" class="form-control-admin" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Total Paid</label>
                                <input type="number" step="0.01" name="total_paid" id="edit_total_paid" class="form-control-admin" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Total Discount</label>
                                <input type="number" step="0.01" name="total_discount" id="edit_total_discount" class="form-control-admin" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Fine Amount</label>
                                <input type="number" step="0.01" name="fine_amount" id="edit_fine_amount" class="form-control-admin" value="0.00">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mt-4 mb-3">Biometric & Portal Logins:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Biometric / RFID Code</label>
                                <input type="text" name="biometric_code" id="edit_biometric_code" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" id="edit_username" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Password (leave blank to keep unchanged)</label>
                                <input type="password" name="password" id="edit_password" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <!-- Qualifications details Table -->
                    <h6 class="text-primary text-uppercase mt-4 mb-3">Qualifications Details:</h6>
                    <div class="modal-section-card">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Qualification</th>
                                        <th>Passing Year</th>
                                        <th>Roll No</th>
                                        <th>Obtained Marks</th>
                                        <th>%</th>
                                        <th>Subjects</th>
                                        <th>School/College Name</th>
                                        <th class="th-w-50">Remove</th>
                                    </tr>
                                </thead>
                                <tbody id="edit_qualificationsTbody">
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="edit_addQualificationRowBtn">
                            <i class="ph-light ph-plus"></i> Add Qualification Row
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="student-page-data" 
     data-csrf-token="<?php echo $csrf_token; ?>" 
     data-base-url="<?php echo BASE_URL; ?>"
     data-flash-success="<?php echo sanitize($flash_success); ?>"
     data-flash-error="<?php echo sanitize($flash_error); ?>">
</div>

<?php require_once '../../../includes/footer.php'; ?>
