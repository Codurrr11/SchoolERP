<?php
// modules/school/teachers/index.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// AJAX endpoint for fetching teacher details
if (isset($_GET['get_teacher_details']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $tid = intval($_GET['id']);

    $stmt = $pdo->prepare("
        SELECT t.*, u.username as u_name
        FROM   teachers t
        JOIN   users u ON t.user_id = u.id
        WHERE  t.id = :id AND t.school_id = :school_id
    ");
    $stmt->execute([':id' => $tid, ':school_id' => $school_id]);
    $teacher_data = $stmt->fetch();

    if ($teacher_data) {
        $stmt_c = $pdo->prepare("
            SELECT class_id, section_id, is_class_teacher
            FROM   teacher_classes
            WHERE  teacher_id = :teacher_id
        ");
        $stmt_c->execute([':teacher_id' => $tid]);
        $teacher_data['classes'] = $stmt_c->fetchAll();

        echo json_encode(['success' => true, 'data' => $teacher_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found.']);
    }
    exit;
}

// ─── AUTO-SEED DEFAULT CLASSES & SECTIONS ──────────────────────────────────
// Ensure classes exist for this school
$stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = :school_id");
$stmt->execute([':school_id' => $school_id]);
if ($stmt->fetchColumn() == 0) {
    $default_classes = [
        ['class_name' => 'Nursery',  'roman_number' => 'NUR',  'class_code' => 'NUR',  'sort_order' => 0],
        ['class_name' => 'LKG',      'roman_number' => 'LKG',  'class_code' => 'LKG',  'sort_order' => 1],
        ['class_name' => 'UKG',      'roman_number' => 'UKG',  'class_code' => 'UKG',  'sort_order' => 2],
        ['class_name' => 'Class 1',  'roman_number' => 'I',    'class_code' => 'C1',   'sort_order' => 3],
        ['class_name' => 'Class 2',  'roman_number' => 'II',   'class_code' => 'C2',   'sort_order' => 4],
        ['class_name' => 'Class 3',  'roman_number' => 'III',  'class_code' => 'C3',   'sort_order' => 5],
        ['class_name' => 'Class 4',  'roman_number' => 'IV',   'class_code' => 'C4',   'sort_order' => 6],
        ['class_name' => 'Class 5',  'roman_number' => 'V',    'class_code' => 'C5',   'sort_order' => 7],
        ['class_name' => 'Class 6',  'roman_number' => 'VI',   'class_code' => 'C6',   'sort_order' => 8],
        ['class_name' => 'Class 7',  'roman_number' => 'VII',  'class_code' => 'C7',   'sort_order' => 9],
        ['class_name' => 'Class 8',  'roman_number' => 'VIII', 'class_code' => 'C8',   'sort_order' => 10],
        ['class_name' => 'Class 9',  'roman_number' => 'IX',   'class_code' => 'C9',   'sort_order' => 11],
        ['class_name' => 'Class 10', 'roman_number' => 'X',    'class_code' => 'C10',  'sort_order' => 12],
        ['class_name' => 'Class 11', 'roman_number' => 'XI',   'class_code' => 'C11',  'sort_order' => 13],
        ['class_name' => 'Class 12', 'roman_number' => 'XII',  'class_code' => 'C12',  'sort_order' => 14],
    ];
    $stmt_ins = $pdo->prepare("INSERT INTO classes (school_id, class_name, name, roman_number, class_code, sort_order, status) VALUES (:school_id, :class_name, :name, :roman_number, :class_code, :sort_order, 'active')");
    foreach ($default_classes as $cls) {
        $stmt_ins->execute([':school_id' => $school_id, ':class_name' => $cls['class_name'], ':name' => $cls['class_name'], ':roman_number' => $cls['roman_number'], ':class_code' => $cls['class_code'], ':sort_order' => $cls['sort_order']]);
    }
}

// Ensure class-specific sections exist for this school
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE school_id = :school_id");
$stmt->execute([':school_id' => $school_id]);
if ($stmt->fetchColumn() == 0) {
    $stmt_cls = $pdo->prepare("SELECT id FROM classes WHERE school_id = :school_id");
    $stmt_cls->execute([':school_id' => $school_id]);
    $cls_ids = $stmt_cls->fetchAll(PDO::FETCH_COLUMN);
    $stmt_ins = $pdo->prepare("INSERT INTO sections (school_id, class_id, section_name, name, status) VALUES (:school_id, :class_id, :section_name, :name, 'active')");
    foreach ($cls_ids as $cls_id) {
        foreach (['A', 'B'] as $sname) {
            $stmt_ins->execute([':school_id' => $school_id, ':class_id' => $cls_id, ':section_name' => $sname, ':name' => $sname]);
        }
    }
}

// Ensure at least one sample teacher exists for visual verification
$stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = :school_id");
$stmt->execute([':school_id' => $school_id]);
if ($stmt->fetchColumn() == 0) {
    try {
        $pdo->beginTransaction();

        // 1. Create User login credentials
        $stmt_user = $pdo->prepare("
            INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, status)
            VALUES (:school_id, 3, 'madhusingh00', 'Madhu', 'Singh', 'Madhu@1994', '9876543213', :password, 'active')
        ");
        $stmt_user->execute([
            ':school_id' => $school_id,
            ':password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);
        $user_id = $pdo->lastInsertId();

        // 2. Create Teacher records matching screenshot details
        $stmt_t = $pdo->prepare("
            INSERT INTO teachers (school_id, user_id, staff_id, joining_date, first_name, last_name, email, mobile_no, status, designation, department)
            VALUES (:school_id, :user_id, 'STF001', '2026-06-01', 'Madhu', 'Singh', 'Madhu@1994', '9876543213', 'active', 'Primary Teacher', 'Academic')
        ");
        $stmt_t->execute([
            ':school_id' => $school_id,
            ':user_id' => $user_id
        ]);
        $teacher_id = $pdo->lastInsertId();

        // 3. Assign default Class 1, 2, 3, 4, 5 with Section A
        $stmt_c = $pdo->prepare("
            SELECT c.id as class_id, s.id as section_id
            FROM classes c
            JOIN sections s ON s.class_id = c.id AND s.section_name = 'A'
            WHERE c.school_id = :school_id AND c.class_name IN ('Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5')
        ");
        $stmt_c->execute([':school_id' => $school_id]);
        $rows = $stmt_c->fetchAll();

        $stmt_tc = $pdo->prepare("
            INSERT INTO teacher_classes (teacher_id, class_id, section_id, is_class_teacher)
            VALUES (:teacher_id, :class_id, :section_id, 0)
        ");
        foreach ($rows as $row) {
            $stmt_tc->execute([
                ':teacher_id' => $teacher_id,
                ':class_id' => $row['class_id'],
                ':section_id' => $row['section_id']
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

// Load classes and sections for UI select/assignment
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY section_name ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();


// ─── POST ACTIONS HANDLING ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: index.php');
        exit;
    }

    // Toggle Status Action
    if ($action === 'toggle_status') {
        $teacher_id = intval($_POST['id'] ?? 0);

        // Fetch teacher and associated user
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $teacher_id, ':school_id' => $school_id]);
        $teacher = $stmt->fetch();

        if ($teacher) {
            $new_status = ($teacher['status'] === 'active') ? 'inactive' : 'active';
            $user_status = ($new_status === 'active') ? 'active' : 'inactive';

            try {
                $pdo->beginTransaction();

                // Update teachers status
                $stmt = $pdo->prepare("UPDATE teachers SET status = :status WHERE id = :id AND school_id = :school_id");
                $stmt->execute([':status' => $new_status, ':id' => $teacher_id, ':school_id' => $school_id]);

                // Update users status
                $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id AND school_id = :school_id");
                $stmt->execute([':status' => $user_status, ':user_id' => $teacher['user_id'], ':school_id' => $school_id]);

                $pdo->commit();
                $_SESSION['flash_success'] = "Teacher status updated to " . ucfirst($new_status) . "!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to update teacher status: " . $e->getMessage();
            }
        }
        header('Location: index.php');
        exit;
    }

    // Delete Teacher Action (Soft Delete)
    if ($action === 'delete') {
        $teacher_id = intval($_POST['id'] ?? 0);

        // Fetch teacher
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id AND school_id = :school_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $teacher_id, ':school_id' => $school_id]);
        $teacher = $stmt->fetch();

        if ($teacher) {
            try {
                $pdo->beginTransaction();

                // Soft delete: stamp deleted_at in both tables
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE teachers SET deleted_at = :now WHERE id = :id AND school_id = :school_id")
                    ->execute([':now' => $now, ':id' => $teacher_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET deleted_at = :now WHERE id = :id AND school_id = :school_id")
                    ->execute([':now' => $now, ':id' => $teacher['user_id'], ':school_id' => $school_id]);

                $pdo->commit();
                $_SESSION['flash_success'] = "Teacher moved to Trash. You can restore them from the Trash Bin.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to delete teacher: " . $e->getMessage();
            }
        }
        header('Location: index.php');
        exit;
    }

    // Bulk Delete Action (Soft Delete)
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);

            try {
                $pdo->beginTransaction();
                $now = date('Y-m-d H:i:s');
                $in_clause = implode(',', $ids);

                // Fetch user_ids to soft-delete in users table too
                $stmt = $pdo->query("SELECT user_id FROM teachers WHERE id IN ($in_clause) AND school_id = $school_id AND deleted_at IS NULL");
                $rows = $stmt->fetchAll();
                $user_ids = array_column($rows, 'user_id');

                // Soft-delete teachers
                $pdo->exec("UPDATE teachers SET deleted_at = '$now' WHERE id IN ($in_clause) AND school_id = $school_id");

                // Soft-delete associated users
                if (!empty($user_ids)) {
                    $u_in_clause = implode(',', $user_ids);
                    $pdo->exec("UPDATE users SET deleted_at = '$now' WHERE id IN ($u_in_clause) AND school_id = $school_id");
                }

                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " teacher(s) moved to Trash successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to move selected teachers to Trash: " . $e->getMessage();
            }
        }
        header('Location: index.php');
        exit;
    }

    // Add Teacher Action
    if ($action === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $mobile_no  = trim($_POST['mobile_no'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';

        // Validations
        if (empty($first_name) || empty($email) || empty($mobile_no) || empty($username)) {
            $_SESSION['flash_error'] = "First name, Email, Mobile no, and Username are required fields.";
            header('Location: index.php');
            exit;
        }

        // Check unique username in users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "This username is already taken. Please choose another.";
            header('Location: index.php');
            exit;
        }

        // Check unique email for this school in users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND school_id = :school_id");
        $stmt->execute([':email' => $email, ':school_id' => $school_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "A user with this email is already registered in this school.";
            header('Location: index.php');
            exit;
        }

        // Generate password if empty
        if (empty($password)) {
            $password = bin2hex(random_bytes(4)); // Random 8 character hex password
        }

        // Handle File Uploads
        $upload_dir = ROOT_PATH . 'uploads/teachers/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'photo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'uploads/teachers/' . $new_name;
            }
        }

        $aadhar_file_path = null;
        if (isset($_FILES['aadhar_file']) && $_FILES['aadhar_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['aadhar_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'aadhar_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['aadhar_file']['tmp_name'], $upload_dir . $new_name)) {
                $aadhar_file_path = 'uploads/teachers/' . $new_name;
            }
        }

        $signature_path = null;
        if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'sig_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $upload_dir . $new_name)) {
                $signature_path = 'uploads/teachers/' . $new_name;
            }
        }

        // Qualifications logic
        $qualifications = [];
        $qual_names = $_POST['qualification'] ?? [];
        $qual_colleges = $_POST['college'] ?? [];
        $qual_years = $_POST['passing_year'] ?? [];

        for ($i = 0; $i < count($qual_names); $i++) {
            if (!empty(trim($qual_names[$i]))) {
                $qualifications[] = [
                    'qualification' => trim($qual_names[$i]),
                    'college' => trim($qual_colleges[$i] ?? ''),
                    'passing_year' => trim($qual_years[$i] ?? '')
                ];
            }
        }
        $qualifications_json = json_encode($qualifications);

        try {
            $pdo->beginTransaction();

            // 1. Create Auth User
            $stmt = $pdo->prepare("
                INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, gender, dob, status)
                VALUES (:school_id, 3, :username, :first_name, :last_name, :email, :phone, :password, :gender, :dob, 'active')
            ");

            $gender_val = !empty($_POST['gender']) ? strtolower($_POST['gender']) : null;
            $dob_val = !empty($_POST['dob']) ? $_POST['dob'] : null;

            $stmt->execute([
                ':school_id' => $school_id,
                ':username' => $username,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':phone' => $mobile_no,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':gender' => $gender_val,
                ':dob' => $dob_val
            ]);

            $user_id = $pdo->lastInsertId();

            // 2. Create Teacher details
            $stmt_teacher = $pdo->prepare("
                INSERT INTO teachers (
                    school_id, user_id, staff_id, joining_date, photo, first_name, last_name, email, mobile_no, alternate_mobile_no, whatsapp_no,
                    gender, dob, marital_status, spouse_name, father_name, nationality, religion, category,
                    last_org_name, last_job_position, exp_years, qualifications,
                    pincode, city, state, country, address,
                    bank_acc_holder, bank_name, bank_ifsc, bank_acc_no, pan_no, pf_acc_no, uan_no,
                    aadhar_no, aadhar_file, signature_file, designation, department, biometric_code, status
                ) VALUES (
                    :school_id, :user_id, :staff_id, :joining_date, :photo, :first_name, :last_name, :email, :mobile_no, :alternate_mobile_no, :whatsapp_no,
                    :gender, :dob, :marital_status, :spouse_name, :father_name, :nationality, :religion, :category,
                    :last_org_name, :last_job_position, :exp_years, :qualifications,
                    :pincode, :city, :state, :country, :address,
                    :bank_acc_holder, :bank_name, :bank_ifsc, :bank_acc_no, :pan_no, :pf_acc_no, :uan_no,
                    :aadhar_no, :aadhar_file, :signature_file, :designation, :department, :biometric_code, 'active'
                )
            ");

            $exp_val = intval($_POST['exp_years'] ?? 0);

            $stmt_teacher->execute([
                ':school_id' => $school_id,
                ':user_id' => $user_id,
                ':staff_id' => !empty($_POST['staff_id']) ? trim($_POST['staff_id']) : null,
                ':joining_date' => !empty($_POST['joining_date']) ? $_POST['joining_date'] : null,
                ':photo' => $photo_path,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':mobile_no' => $mobile_no,
                ':alternate_mobile_no' => !empty($_POST['alternate_mobile_no']) ? trim($_POST['alternate_mobile_no']) : null,
                ':whatsapp_no' => !empty($_POST['whatsapp_no']) ? trim($_POST['whatsapp_no']) : null,
                ':gender' => $gender_val,
                ':dob' => $dob_val,
                ':marital_status' => !empty($_POST['marital_status']) ? $_POST['marital_status'] : null,
                ':spouse_name' => !empty($_POST['spouse_name']) ? trim($_POST['spouse_name']) : null,
                ':father_name' => !empty($_POST['father_name']) ? trim($_POST['father_name']) : null,
                ':nationality' => !empty($_POST['nationality']) ? trim($_POST['nationality']) : 'INDIAN',
                ':religion' => !empty($_POST['religion']) ? $_POST['religion'] : null,
                ':category' => !empty($_POST['category']) ? $_POST['category'] : null,
                ':last_org_name' => !empty($_POST['last_org_name']) ? trim($_POST['last_org_name']) : null,
                ':last_job_position' => !empty($_POST['last_job_position']) ? trim($_POST['last_job_position']) : null,
                ':exp_years' => $exp_val,
                ':qualifications' => $qualifications_json,
                ':pincode' => !empty($_POST['pincode']) ? trim($_POST['pincode']) : null,
                ':city' => !empty($_POST['city']) ? trim($_POST['city']) : null,
                ':state' => !empty($_POST['state']) ? trim($_POST['state']) : null,
                ':country' => !empty($_POST['country']) ? trim($_POST['country']) : null,
                ':address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
                ':bank_acc_holder' => !empty($_POST['bank_acc_holder']) ? trim($_POST['bank_acc_holder']) : null,
                ':bank_name' => !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null,
                ':bank_ifsc' => !empty($_POST['bank_ifsc']) ? trim($_POST['bank_ifsc']) : null,
                ':bank_acc_no' => !empty($_POST['bank_acc_no']) ? trim($_POST['bank_acc_no']) : null,
                ':pan_no' => !empty($_POST['pan_no']) ? trim($_POST['pan_no']) : null,
                ':pf_acc_no' => !empty($_POST['pf_acc_no']) ? trim($_POST['pf_acc_no']) : null,
                ':uan_no' => !empty($_POST['uan_no']) ? trim($_POST['uan_no']) : null,
                ':aadhar_no' => !empty($_POST['aadhar_no']) ? trim($_POST['aadhar_no']) : null,
                ':aadhar_file' => $aadhar_file_path,
                ':signature_file' => $signature_path,
                ':designation' => !empty($_POST['designation']) ? trim($_POST['designation']) : null,
                ':department' => !empty($_POST['department']) ? $_POST['department'] : null,
                ':biometric_code' => !empty($_POST['biometric_code']) ? trim($_POST['biometric_code']) : null
            ]);

            $teacher_id = $pdo->lastInsertId();

            // 3. Assign Classes and Sections
            $classes_post = $_POST['assign_classes'] ?? [];
            $class_teachers_post = $_POST['class_teachers'] ?? []; // Array mapping class_id_section_id to 1/0

            if (!empty($classes_post)) {
                $stmt_class = $pdo->prepare("
                    INSERT INTO teacher_classes (teacher_id, class_id, section_id, is_class_teacher)
                    VALUES (:teacher_id, :class_id, :section_id, :is_class_teacher)
                ");

                foreach ($classes_post as $assignment_key) {
                    // key format is classId_sectionId
                    $parts = explode('_', $assignment_key);
                    if (count($parts) === 2) {
                        $cls_id = intval($parts[0]);
                        $sec_id = intval($parts[1]);
                        $is_ct = isset($class_teachers_post[$assignment_key]) ? 1 : 0;

                        $stmt_class->execute([
                            ':teacher_id' => $teacher_id,
                            ':class_id' => $cls_id,
                            ':section_id' => $sec_id,
                            ':is_class_teacher' => $is_ct
                        ]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Teacher created successfully! Login username: " . $username . ", Temporary password: " . $password;
        } catch (Exception $e) {
            $pdo->rollBack();
            // Delete uploaded files since transaction failed
            if ($photo_path && file_exists($upload_dir . basename($photo_path))) @unlink($upload_dir . basename($photo_path));
            if ($aadhar_file_path && file_exists($upload_dir . basename($aadhar_file_path))) @unlink($upload_dir . basename($aadhar_file_path));
            if ($signature_path && file_exists($upload_dir . basename($signature_path))) @unlink($upload_dir . basename($signature_path));

            $_SESSION['flash_error'] = "Failed to register teacher: " . $e->getMessage();
        }

        header('Location: index.php');
        exit;
    }

    // Edit Teacher Action
    if ($action === 'edit') {
        $teacher_id = intval($_POST['id'] ?? 0);

        // Fetch teacher to ensure they exist and belong to this school
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $teacher_id, ':school_id' => $school_id]);
        $teacher = $stmt->fetch();

        if (!$teacher) {
            $_SESSION['flash_error'] = "Teacher profile not found.";
            header('Location: index.php');
            exit;
        }

        $user_id = $teacher['user_id'];

        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $mobile_no  = trim($_POST['mobile_no'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';

        // Validations
        if (empty($first_name) || empty($email) || empty($mobile_no) || empty($username)) {
            $_SESSION['flash_error'] = "First name, Email, Mobile no, and Username are required fields.";
            header('Location: index.php');
            exit;
        }

        // Check unique username in users (excluding current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :user_id");
        $stmt->execute([':username' => $username, ':user_id' => $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "This username is already taken. Please choose another.";
            header('Location: index.php');
            exit;
        }

        // Check unique email for this school in users (excluding current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND school_id = :school_id AND id != :user_id");
        $stmt->execute([':email' => $email, ':school_id' => $school_id, ':user_id' => $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "A user with this email is already registered in this school.";
            header('Location: index.php');
            exit;
        }

        // Handle File Uploads
        $upload_dir = ROOT_PATH . 'uploads/teachers/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        // New files or keep old files
        $photo_path = $teacher['photo'];
        $new_photo_uploaded = false;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'photo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'uploads/teachers/' . $new_name;
                $new_photo_uploaded = true;
            }
        }

        $aadhar_file_path = $teacher['aadhar_file'];
        $new_aadhar_uploaded = false;
        if (isset($_FILES['aadhar_file']) && $_FILES['aadhar_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['aadhar_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'aadhar_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['aadhar_file']['tmp_name'], $upload_dir . $new_name)) {
                $aadhar_file_path = 'uploads/teachers/' . $new_name;
                $new_aadhar_uploaded = true;
            }
        }

        $signature_path = $teacher['signature_file'];
        $new_signature_uploaded = false;
        if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'sig_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $upload_dir . $new_name)) {
                $signature_path = 'uploads/teachers/' . $new_name;
                $new_signature_uploaded = true;
            }
        }

        // Qualifications logic
        $qualifications = [];
        $qual_names = $_POST['qualification'] ?? [];
        $qual_colleges = $_POST['college'] ?? [];
        $qual_years = $_POST['passing_year'] ?? [];

        for ($i = 0; $i < count($qual_names); $i++) {
            if (!empty(trim($qual_names[$i]))) {
                $qualifications[] = [
                    'qualification' => trim($qual_names[$i]),
                    'college' => trim($qual_colleges[$i] ?? ''),
                    'passing_year' => trim($qual_years[$i] ?? '')
                ];
            }
        }
        $qualifications_json = json_encode($qualifications);

        try {
            $pdo->beginTransaction();

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
                        dob = :dob
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
                        dob = :dob
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
                    ':id' => $user_id,
                    ':school_id' => $school_id
                ]);
            }

            // 2. Update Teacher details
            $stmt_teacher_update = $pdo->prepare("
                UPDATE teachers SET
                    staff_id = :staff_id,
                    joining_date = :joining_date,
                    photo = :photo,
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    mobile_no = :mobile_no,
                    alternate_mobile_no = :alternate_mobile_no,
                    whatsapp_no = :whatsapp_no,
                    gender = :gender,
                    dob = :dob,
                    marital_status = :marital_status,
                    spouse_name = :spouse_name,
                    father_name = :father_name,
                    nationality = :nationality,
                    religion = :religion,
                    category = :category,
                    last_org_name = :last_org_name,
                    last_job_position = :last_job_position,
                    exp_years = :exp_years,
                    qualifications = :qualifications,
                    pincode = :pincode,
                    city = :city,
                    state = :state,
                    country = :country,
                    address = :address,
                    bank_acc_holder = :bank_acc_holder,
                    bank_name = :bank_name,
                    bank_ifsc = :bank_ifsc,
                    bank_acc_no = :bank_acc_no,
                    pan_no = :pan_no,
                    pf_acc_no = :pf_acc_no,
                    uan_no = :uan_no,
                    aadhar_no = :aadhar_no,
                    aadhar_file = :aadhar_file,
                    signature_file = :signature_file,
                    designation = :designation,
                    department = :department,
                    biometric_code = :biometric_code
                WHERE id = :id AND school_id = :school_id
            ");

            $exp_val = intval($_POST['exp_years'] ?? 0);

            $stmt_teacher_update->execute([
                ':staff_id' => !empty($_POST['staff_id']) ? trim($_POST['staff_id']) : null,
                ':joining_date' => !empty($_POST['joining_date']) ? $_POST['joining_date'] : null,
                ':photo' => $photo_path,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':mobile_no' => $mobile_no,
                ':alternate_mobile_no' => !empty($_POST['alternate_mobile_no']) ? trim($_POST['alternate_mobile_no']) : null,
                ':whatsapp_no' => !empty($_POST['whatsapp_no']) ? trim($_POST['whatsapp_no']) : null,
                ':gender' => $gender_val,
                ':dob' => $dob_val,
                ':marital_status' => !empty($_POST['marital_status']) ? $_POST['marital_status'] : null,
                ':spouse_name' => !empty($_POST['spouse_name']) ? trim($_POST['spouse_name']) : null,
                ':father_name' => !empty($_POST['father_name']) ? trim($_POST['father_name']) : null,
                ':nationality' => !empty($_POST['nationality']) ? trim($_POST['nationality']) : 'INDIAN',
                ':religion' => !empty($_POST['religion']) ? $_POST['religion'] : null,
                ':category' => !empty($_POST['category']) ? $_POST['category'] : null,
                ':last_org_name' => !empty($_POST['last_org_name']) ? trim($_POST['last_org_name']) : null,
                ':last_job_position' => !empty($_POST['last_job_position']) ? trim($_POST['last_job_position']) : null,
                ':exp_years' => $exp_val,
                ':qualifications' => $qualifications_json,
                ':pincode' => !empty($_POST['pincode']) ? trim($_POST['pincode']) : null,
                ':city' => !empty($_POST['city']) ? trim($_POST['city']) : null,
                ':state' => !empty($_POST['state']) ? trim($_POST['state']) : null,
                ':country' => !empty($_POST['country']) ? trim($_POST['country']) : null,
                ':address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
                ':bank_acc_holder' => !empty($_POST['bank_acc_holder']) ? trim($_POST['bank_acc_holder']) : null,
                ':bank_name' => !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null,
                ':bank_ifsc' => !empty($_POST['bank_ifsc']) ? trim($_POST['bank_ifsc']) : null,
                ':bank_acc_no' => !empty($_POST['bank_acc_no']) ? trim($_POST['bank_acc_no']) : null,
                ':pan_no' => !empty($_POST['pan_no']) ? trim($_POST['pan_no']) : null,
                ':pf_acc_no' => !empty($_POST['pf_acc_no']) ? trim($_POST['pf_acc_no']) : null,
                ':uan_no' => !empty($_POST['uan_no']) ? trim($_POST['uan_no']) : null,
                ':aadhar_no' => !empty($_POST['aadhar_no']) ? trim($_POST['aadhar_no']) : null,
                ':aadhar_file' => $aadhar_file_path,
                ':signature_file' => $signature_path,
                ':designation' => !empty($_POST['designation']) ? trim($_POST['designation']) : null,
                ':department' => !empty($_POST['department']) ? $_POST['department'] : null,
                ':biometric_code' => !empty($_POST['biometric_code']) ? trim($_POST['biometric_code']) : null,
                ':id' => $teacher_id,
                ':school_id' => $school_id
            ]);

            // 3. Clear and Assign Classes and Sections
            $stmt_del = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = :teacher_id");
            $stmt_del->execute([':teacher_id' => $teacher_id]);

            $classes_post = $_POST['assign_classes'] ?? [];
            $class_teachers_post = $_POST['class_teachers'] ?? [];

            if (!empty($classes_post)) {
                $stmt_class = $pdo->prepare("
                    INSERT INTO teacher_classes (teacher_id, class_id, section_id, is_class_teacher)
                    VALUES (:teacher_id, :class_id, :section_id, :is_class_teacher)
                ");

                foreach ($classes_post as $assignment_key) {
                    $parts = explode('_', $assignment_key);
                    if (count($parts) === 2) {
                        $cls_id = intval($parts[0]);
                        $sec_id = intval($parts[1]);
                        $is_ct = isset($class_teachers_post[$assignment_key]) ? 1 : 0;

                        $stmt_class->execute([
                            ':teacher_id' => $teacher_id,
                            ':class_id' => $cls_id,
                            ':section_id' => $sec_id,
                            ':is_class_teacher' => $is_ct
                        ]);
                    }
                }
            }

            $pdo->commit();

            // Delete old files since new ones were successfully committed and updated
            if ($new_photo_uploaded && $teacher['photo'] && file_exists(ROOT_PATH . $teacher['photo'])) {
                @unlink(ROOT_PATH . $teacher['photo']);
            }
            if ($new_aadhar_uploaded && $teacher['aadhar_file'] && file_exists(ROOT_PATH . $teacher['aadhar_file'])) {
                @unlink(ROOT_PATH . $teacher['aadhar_file']);
            }
            if ($new_signature_uploaded && $teacher['signature_file'] && file_exists(ROOT_PATH . $teacher['signature_file'])) {
                @unlink(ROOT_PATH . $teacher['signature_file']);
            }

            $_SESSION['flash_success'] = "Teacher profile updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            // Delete newly uploaded files since transaction failed
            if ($new_photo_uploaded && $photo_path && file_exists($upload_dir . basename($photo_path))) @unlink($upload_dir . basename($photo_path));
            if ($new_aadhar_uploaded && $aadhar_file_path && file_exists($upload_dir . basename($aadhar_file_path))) @unlink($upload_dir . basename($aadhar_file_path));
            if ($new_signature_uploaded && $signature_path && file_exists($upload_dir . basename($signature_path))) @unlink($upload_dir . basename($signature_path));

            $_SESSION['flash_error'] = "Failed to update teacher: " . $e->getMessage();
        }

        header('Location: index.php');
        exit;
    }
}


// ─── QUERY DATA ─────────────────────────────────────────────────────────────
// Fetch all teachers in this school
$stmt = $pdo->prepare("
    SELECT t.*, u.username as u_name
    FROM   teachers t
    JOIN   users u ON t.user_id = u.id
    WHERE  t.school_id = :school_id
      AND  t.deleted_at IS NULL
    ORDER  BY t.id DESC
");
$stmt->execute([':school_id' => $school_id]);
$teachers = $stmt->fetchAll();

// Map teacher_classes list to each teacher
$teachers_mapped = [];
foreach ($teachers as $t) {
    // Fetch classes
    $stmt_c = $pdo->prepare("
        SELECT tc.*, c.class_name as class_name, s.section_name as section_name
        FROM   teacher_classes tc
        JOIN   classes c ON tc.class_id = c.id
        JOIN   sections s ON tc.section_id = s.id
        WHERE  tc.teacher_id = :teacher_id
    ");
    $stmt_c->execute([':teacher_id' => $t['id']]);
    $t['classes'] = $stmt_c->fetchAll();

    $teachers_mapped[] = $t;
}

// Calculate biometric registration warnings
$missing_bio_count = 0;
foreach ($teachers_mapped as $t) {
    if (empty($t['biometric_code'])) {
        $missing_bio_count++;
    }
}

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- ─── PAGE HEADER & NOTIFICATIONS ───────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-1 font-heading fw-extrabold">Teachers</h2>
        <p class="text-xs text-muted mb-0">Manage school faculty members, qualifications, bank records, and class assignments.</p>
    </div>
</div>

<!-- Warning Banner if Biometric missing -->
<?php if ($missing_bio_count > 0): ?>
    <div class="teacher-bio-alert mb-4">
        <i class="ph-light ph-warning-circle alert-icon"></i>
        <span><strong><?php echo $missing_bio_count; ?> teachers</strong> do not have Biometric registration. Register them to track attendance automatically.</span>
    </div>
<?php endif; ?>


<!-- Meta tag for JS parameter passing without inline scripting -->
<div id="teacher-page-data"
    data-csrf-token="<?php echo $csrf_token; ?>"
    data-base-url="<?php echo BASE_URL; ?>"
    data-flash-success="<?php echo htmlspecialchars($flash_success ?? ''); ?>"
    data-flash-error="<?php echo htmlspecialchars($flash_error ?? ''); ?>">
</div>

<!-- ─── TEACHERS DIRECTORY GRID & DATA TABLE ────────────────────────────────── -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <!-- Button Group -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- 1. Add Teacher -->
                    <button type="button" class="teacher-header-btn btn-accent" data-bs-toggle="modal" data-bs-target="#addTeacherModal" title="Add Teacher">
                        <i class="ph-light ph-user-plus"></i>
                    </button>
                    <!-- 2. Import Teachers -->
                    <button type="button" class="teacher-header-btn btn-sky" title="Import Teachers">
                        <i class="ph-light ph-upload-simple"></i>
                    </button>
                    <!-- 3. Export Teachers -->
                    <button type="button" class="teacher-header-btn btn-sky" title="Export Teachers">
                        <i class="ph-light ph-download-simple"></i>
                    </button>
                    <!-- 4. Credentials -->
                    <button type="button" class="teacher-header-btn btn-accent" title="Access Credentials">
                        <i class="ph-light ph-key"></i>
                    </button>
                    <!-- 5. Bulk Edit -->
                    <button type="button" class="teacher-header-btn btn-accent" title="Bulk Edit List">
                        <i class="ph-light ph-pencil-simple"></i>
                    </button>
                    <!-- 6. Bulk Delete -->
                    <button type="button" class="teacher-header-btn btn-red" id="bulkDeleteBtn" disabled title="Move Selected to Trash">
                        <i class="ph-light ph-trash"></i>
                    </button>
                    <!-- 7. Trash Bin -->
                    <a href="trash.php" class="teacher-header-btn btn-red" title="Trash Bin — Deleted Teachers">
                        <i class="ph-light ph-recycle"></i>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                    <!-- Search Input -->
                    <div class="table-search-box m-0">
                        <i class="ph-light ph-magnifying-glass"></i>
                        <input type="text" placeholder="Search teachers..." id="teacherSearchInput" style="height: 34px; padding-top: 0; padding-bottom: 0;">
                    </div>
                    <!-- Total Counter Badge -->
                    <div class="teacher-total-badge">
                        <i class="ph-light ph-users-three"></i>
                        Total: <span class="count-num"><?php echo count($teachers_mapped); ?></span>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($teachers_mapped)): ?>
                        <!-- Empty State -->
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-chalkboard-teacher"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No teachers registered</h5>
                            <p class="text-xs text-muted mb-4">Add your faculty members and assign them classes to start managing them.</p>
                            <button type="button" class="btn-admin-action mx-auto" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                                <i class="ph-light ph-plus"></i> Add Teacher
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Data Table -->
                        <form id="bulkDeleteForm" action="index.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="bulk_delete">

                            <table class="teacher-table table-premium mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 46px;">
                                            <input type="checkbox" class="table-checkbox" id="selectAllCheckbox">
                                        </th>
                                        <th style="width: 50px;">#</th>
                                        <th>Biometric Code</th>
                                        <th>Profile</th>
                                        <th>Username</th>
                                        <th>Teacher Details</th>
                                        <th>Assigned Classes & Sections</th>
                                        <th>Joining Date</th>
                                        <th style="width: 80px;">Status</th>
                                        <th style="width: 100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="teachersTableBody">
                                    <?php
                                    $idx = 1;
                                    foreach ($teachers_mapped as $t):
                                    ?>
                                        <tr>
                                            <!-- Checkbox -->
                                            <td>
                                                <input type="checkbox" name="ids[]" value="<?php echo $t['id']; ?>" class="table-checkbox teacher-select-checkbox">
                                            </td>

                                            <!-- Counter -->
                                            <td><span class="cell-counter"><?php echo $idx++; ?></span></td>

                                            <!-- Biometric Code -->
                                            <td>
                                                <?php if (!empty($t['biometric_code'])): ?>
                                                    <span class="cell-biometric"><?php echo sanitize($t['biometric_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="cell-biometric" style="color:#cbd5e1; border-color:#f1f5f9;">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Profile Image -->
                                            <td>
                                                <?php if (!empty($t['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . sanitize($t['photo']); ?>" alt="Profile" class="teacher-avatar">
                                                <?php else: ?>
                                                    <div class="teacher-avatar-placeholder">
                                                        <?php echo strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Username -->
                                            <td>
                                                <span class="teacher-username"><?php echo sanitize($t['u_name']); ?></span>
                                            </td>

                                            <!-- Details -->
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <a href="view.php?id=<?php echo $t['id']; ?>" class="teacher-name-link">
                                                        <?php echo sanitize($t['first_name'] . ' ' . $t['last_name']); ?>
                                                    </a>
                                                    <span class="teacher-detail-email">Email: <?php echo sanitize($t['email']); ?></span>
                                                    <span class="teacher-detail-mobile">
                                                        Mobile: <?php echo sanitize($t['mobile_no']); ?>
                                                        <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $t['mobile_no']); ?>" target="_blank" class="whatsapp-icon text-decoration-none" title="Send WhatsApp">
                                                            <i class="ph-light ph-whatsapp-logo"></i>
                                                        </a>
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Assigned Classes -->
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if (empty($t['classes'])): ?>
                                                        <span class="cell-biometric" style="color:#cbd5e1; border-color:#f1f5f9;">—</span>
                                                    <?php else: ?>
                                                        <?php foreach ($t['classes'] as $tc): ?>
                                                            <span class="class-pill <?php echo $tc['is_class_teacher'] ? 'class-pill-primary' : 'class-pill-secondary'; ?>">
                                                                <?php echo sanitize($tc['class_name'] . ' - ' . $tc['section_name']); ?>
                                                                <?php if ($tc['is_class_teacher']): ?>
                                                                    <i class="ph-light ph-crown ct-icon" title="Class Teacher"></i>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Joining Date -->
                                            <td>
                                                <span class="teacher-joining-date">
                                                    <?php echo !empty($t['joining_date']) ? date('d-m-Y', strtotime($t['joining_date'])) : '—'; ?>
                                                </span>
                                            </td>

                                            <!-- Status Toggle -->
                                            <td>
                                                <form action="index.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                    <div class="form-check form-switch teacher-status-switch p-0 m-0">
                                                        <input class="form-check-input ms-0" type="checkbox" role="switch" <?php echo ($t['status'] === 'active') ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                    </div>
                                                </form>
                                            </td>

                                            <!-- Actions -->
                                            <td>
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="view.php?id=<?php echo $t['id']; ?>" class="teacher-action-btn action-view" title="View Profile">
                                                        <i class="ph-light ph-eye"></i>
                                                    </a>

                                                    <button type="button" class="teacher-action-btn action-edit edit-teacher-btn" data-id="<?php echo $t['id']; ?>" title="Edit Teacher">
                                                        <i class="ph-light ph-pencil-simple"></i>
                                                    </button>

                                                    <button type="button" class="teacher-action-btn action-delete delete-teacher-btn" data-id="<?php echo $t['id']; ?>" data-name="<?php echo sanitize($t['first_name'] . ' ' . $t['last_name']); ?>" title="Delete Teacher">
                                                        <i class="ph-light ph-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Hidden Individual Delete Form -->
<form id="deleteTeacherForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_teacher_id">
</form>


<!-- ─── ADD TEACHER POPUP MODAL FORM ────────────────────────── -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: var(--border-radius-lg);">
            <div class="modal-header border-0 bg-light py-3 px-4" style="border-top-left-radius: var(--border-radius-lg); border-top-right-radius: var(--border-radius-lg);">
                <h6 class="modal-title font-heading fw-bold" id="addTeacherModalLabel" style="color: var(--color-accent-hover); font-size: 15px; text-transform: uppercase; letter-spacing: 0.05em;"><i class="ph-light ph-user-plus me-2" style="font-size: 18px; vertical-align: middle;"></i>Add Teacher Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="index.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-body p-4 text-dark text-start">

                    <!-- 1. JOINING DETAILS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-calendar-blank me-2"></i>Joining Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="staff_id" class="form-label-admin">Staff ID No.</label>
                                <input type="text" id="staff_id" name="staff_id" class="form-control-admin" placeholder="e.g. STF0092">
                            </div>
                            <div class="col-md-4">
                                <label for="joining_date" class="form-label-admin">Joining Date</label>
                                <input type="date" id="joining_date" name="joining_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="photo" class="form-label-admin">Teacher's Photo</label>
                                <input type="file" id="photo" name="photo" class="form-control-admin" accept="image/jpeg,image/jpg,image/png,image/webp">
                                <span class="text-xxs text-muted d-block mt-1">Allowed only JPEG, JPG, PNG, WEBP. Size max 10MB.</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. PERSONAL DETAILS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-user me-2"></i>Personal Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="first_name" class="form-label-admin">First name <span class="text-danger">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label for="last_name" class="form-label-admin">Last name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="email" class="form-label-admin">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control-admin" required>
                            </div>

                            <div class="col-md-4">
                                <label for="mobile_no" class="form-label-admin">Mobile no. <span class="text-danger">*</span></label>
                                <input type="tel" id="mobile_no" name="mobile_no" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label for="alternate_mobile_no" class="form-label-admin">Alternate Mobile No.</label>
                                <input type="tel" id="alternate_mobile_no" name="alternate_mobile_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="whatsapp_no" class="form-label-admin">Whatsapp No.</label>
                                <input type="tel" id="whatsapp_no" name="whatsapp_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin d-block mb-2">Gender</label>
                                <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male">
                                        <label class="form-check-label text-xs" for="gender_male" style="font-family: var(--font-secondary) !important; font-weight: 600;">Male</label>
                                    </div>
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female">
                                        <label class="form-check-label text-xs" for="gender_female" style="font-family: var(--font-secondary) !important; font-weight: 600;">Female</label>
                                    </div>
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="radio" name="gender" id="gender_other" value="other">
                                        <label class="form-check-label text-xs" for="gender_other" style="font-family: var(--font-secondary) !important; font-weight: 600;">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="dob" class="form-label-admin">DOB</label>
                                <input type="date" id="dob" name="dob" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="marital_status" class="form-label-admin">Marital Status</label>
                                <select id="marital_status" name="marital_status" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="spouse_name" class="form-label-admin">Spouse name</label>
                                <input type="text" id="spouse_name" name="spouse_name" class="form-control-admin">
                            </div>
                            <div class="col-md-6">
                                <label for="father_name" class="form-label-admin">Father name</label>
                                <input type="text" id="father_name" name="father_name" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <!-- 3. RELIGION & CATEGORY -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-globe-hemisphere-east me-2"></i>Religion & Category</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="nationality" class="form-label-admin">Nationality</label>
                                <select id="nationality" name="nationality" class="form-control-admin">
                                    <option value="INDIAN" selected>INDIAN</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="religion" class="form-label-admin">Religion</label>
                                <select id="religion" name="religion" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Muslim">Muslim</option>
                                    <option value="Christian">Christian</option>
                                    <option value="Sikh">Sikh</option>
                                    <option value="Buddhist">Buddhist</option>
                                    <option value="Jain">Jain</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label-admin">Category</label>
                                <select id="category" name="category" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="General">General</option>
                                    <option value="OBC">OBC</option>
                                    <option value="SC">SC</option>
                                    <option value="ST">ST</option>
                                    <option value="EWS">EWS</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 4. EXPERIENCE -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-briefcase me-2"></i>Experience (If Any)</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="last_org_name" class="form-label-admin">Last organization name</label>
                                <input type="text" id="last_org_name" name="last_org_name" class="form-control-admin" placeholder="e.g. Green Valley School">
                            </div>
                            <div class="col-md-4">
                                <label for="last_job_position" class="form-label-admin">Last job position</label>
                                <input type="text" id="last_job_position" name="last_job_position" class="form-control-admin" placeholder="e.g. PGT Teacher">
                            </div>
                            <div class="col-md-4">
                                <label for="exp_years" class="form-label-admin">How many years of experience do you have?</label>
                                <input type="number" id="exp_years" name="exp_years" class="form-control-admin" min="0" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- 5. QUALIFICATION (DYNAMIC GRID) -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-graduation-cap me-2"></i>Qualifications</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-2 text-xs" id="qualificationsTable">
                                <thead class="bg-light font-heading fw-semibold">
                                    <tr>
                                        <th>Qualification</th>
                                        <th>College / University</th>
                                        <th>Passing Year</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="qualificationsTbody">
                                    <tr>
                                        <td>
                                            <select name="qualification[]" class="form-control-admin py-1.5 fs-7">
                                                <option value="">Select</option>
                                                <option value="B.Ed">B.Ed</option>
                                                <option value="M.Ed">M.Ed</option>
                                                <option value="B.Sc">B.Sc</option>
                                                <option value="M.Sc">M.Sc</option>
                                                <option value="B.A">B.A</option>
                                                <option value="M.A">M.A</option>
                                                <option value="Ph.D">Ph.D</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="college[]" class="form-control-admin py-1.5 fs-7" placeholder="College name">
                                        </td>
                                        <td>
                                            <select name="passing_year[]" class="form-control-admin py-1.5 fs-7">
                                                <option value="">Select</option>
                                                <?php
                                                $curr_yr = intval(date('Y'));
                                                for ($y = $curr_yr; $y >= 1980; $y--):
                                                ?>
                                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 remove-qual-row"><i class="ph-light ph-trash fs-6"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary py-1.5 px-3 fs-7" id="addQualificationRowBtn" style="font-family: var(--font-secondary) !important; border-radius: 8px; font-weight: 700;">
                                    <i class="ph-light ph-plus-circle align-middle me-1"></i> Add Qualification
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 6. RESIDENTIAL ADDRESS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-map-pin me-2"></i>Residential Address</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="pincode" class="form-label-admin">Pincode</label>
                                <input type="text" id="pincode" name="pincode" class="form-control-admin" placeholder="e.g. 250001">
                            </div>
                            <div class="col-md-3">
                                <label for="city" class="form-label-admin">City</label>
                                <input type="text" id="city" name="city" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="state" class="form-label-admin">State</label>
                                <input type="text" id="state" name="state" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="country" class="form-label-admin">Country</label>
                                <input type="text" id="country" name="country" class="form-control-admin" value="India">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label-admin">Address</label>
                                <input type="text" id="address" name="address" class="form-control-admin" placeholder="House/Flat No, Block, Area, Road details">
                            </div>
                        </div>
                    </div>

                    <!-- 7. BANK ACCOUNT DETAILS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-bank me-2"></i>Bank Account Details</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="bank_acc_holder" class="form-label-admin">Account Holder Name</label>
                                <input type="text" id="bank_acc_holder" name="bank_acc_holder" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="bank_name" class="form-label-admin">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="bank_ifsc" class="form-label-admin">IFSC Code</label>
                                <input type="text" id="bank_ifsc" name="bank_ifsc" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="bank_acc_no" class="form-label-admin">Account No.</label>
                                <input type="text" id="bank_acc_no" name="bank_acc_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label for="pan_no" class="form-label-admin">PAN No.</label>
                                <input type="text" id="pan_no" name="pan_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="pf_acc_no" class="form-label-admin">PF Account Number</label>
                                <input type="text" id="pf_acc_no" name="pf_acc_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="uan_no" class="form-label-admin">Universal Account Number</label>
                                <input type="text" id="uan_no" name="uan_no" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <!-- 8. AADHAR & SIGNATURE -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-identification-badge me-2"></i>Aadhar & Signature Documents</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="aadhar_no" class="form-label-admin">Aadhar No.</label>
                                <input type="text" id="aadhar_no" name="aadhar_no" class="form-control-admin" placeholder="e.g. 1234 5678 9012">
                            </div>
                            <div class="col-md-4">
                                <label for="aadhar_file" class="form-label-admin">Attach Aadhar</label>
                                <input type="file" id="aadhar_file" name="aadhar_file" class="form-control-admin" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">
                                <span class="text-xxs text-muted d-block mt-1">Allowed only JPEG, JPG, PNG, WEBP & PDF. Size max 10MB.</span>
                            </div>
                            <div class="col-md-4">
                                <label for="signature_file" class="form-label-admin">Signature</label>
                                <input type="file" id="signature_file" name="signature_file" class="form-control-admin" accept="image/jpeg,image/jpg,image/png,image/webp">
                                <span class="text-xxs text-muted d-block mt-1">Allowed only JPEG, JPG, PNG, WEBP. Size max 10MB.</span>
                            </div>
                        </div>
                    </div>

                    <!-- 9. ASSIGN CLASS & SECTION -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-chalkboard me-2"></i>Assign Classes & Sections</h6>
                        <p class="text-xxs text-muted mb-3" style="margin-top: -6px;">You can assign multiple classes & sections to a teacher.</p>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-bordered align-middle text-xs mb-0">
                                <thead class="bg-light font-heading fw-semibold">
                                    <tr>
                                        <th style="width: 60px; text-align: center;">Assign</th>
                                        <th>Classes</th>
                                        <th>Sections</th>
                                        <th style="width: 140px; text-align: center;">Class Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_classes) || empty($all_sections)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No classes/sections seeded.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        foreach ($all_classes as $cls):
                                            foreach ($all_sections as $sec):
                                                $assign_key = $cls['id'] . '_' . $sec['id'];
                                        ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="assign_classes[]" value="<?php echo $assign_key; ?>" class="form-check-input class-assign-checkbox">
                                                    </td>
                                                    <td><span class="fw-semibold"><?php echo sanitize($cls['name']); ?></span></td>
                                                    <td><span><?php echo sanitize($sec['name']); ?></span></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="class_teachers[<?php echo $assign_key; ?>]" value="1" class="form-check-input class-teacher-checkbox" disabled>
                                                    </td>
                                                </tr>
                                        <?php
                                            endforeach;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 10. DESIGNATION & DEPARTMENT -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-identification-card me-2"></i>Academic Role</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="designation" class="form-label-admin">Designation</label>
                                <input type="text" id="designation" name="designation" class="form-control-admin" placeholder="Ex. Physics Teacher">
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label-admin">Department</label>
                                <select id="department" name="department" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Administrative">Administrative</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Support Staff">Support Staff</option>
                                    <option value="IT">IT</option>
                                    <option value="Finance">Finance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 11. DETAILS FOR LOGIN -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-key me-2"></i>Credentials & Login Setup</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label-admin">Username <span class="text-danger">*</span></label>
                                <input type="text" id="username" name="username" class="form-control-admin" placeholder="e.g. madhusingh00" required>
                                <span class="text-xxs text-muted d-block mt-1">Username must be unique, it'll be used for login.</span>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label-admin">Password</label>
                                <input type="password" id="password" name="password" class="form-control-admin" placeholder="Leave blank to auto-generate password">
                                <span class="text-xxs text-muted d-block mt-1">If you don't enter, a random password shall be used.</span>
                            </div>
                            <div class="col-md-6">
                                <label for="biometric_code" class="form-label-admin">Biometric Code (Optional)</label>
                                <input type="text" id="biometric_code" name="biometric_code" class="form-control-admin" placeholder="e.g. BIO_8872">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 p-3 bg-light" style="border-bottom-left-radius: var(--border-radius-lg); border-bottom-right-radius: var(--border-radius-lg);">
                    <button type="button" class="btn-admin-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn-admin-action">Register Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ─── EDIT TEACHER POPUP MODAL FORM ────────────────────────── -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: var(--border-radius-lg);">
            <div class="modal-header border-0 bg-light py-3 px-4" style="border-top-left-radius: var(--border-radius-lg); border-top-right-radius: var(--border-radius-lg);">
                <h6 class="modal-title font-heading fw-bold" id="editTeacherModalLabel" style="color: var(--color-accent-hover); font-size: 15px; text-transform: uppercase; letter-spacing: 0.05em;"><i class="ph-light ph-pencil-simple me-2" style="font-size: 18px; vertical-align: middle;"></i>Edit Teacher Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="index.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_teacher_id">

                <div class="modal-body p-4 text-dark text-start">

                    <!-- 1. JOINING DETAILS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-calendar-blank me-2"></i>Joining Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_staff_id" class="form-label-admin">Staff ID No.</label>
                                <input type="text" id="edit_staff_id" name="staff_id" class="form-control-admin" placeholder="e.g. STF0092">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_joining_date" class="form-label-admin">Joining Date</label>
                                <input type="date" id="edit_joining_date" name="joining_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_photo" class="form-label-admin">Teacher's Photo</label>
                                <input type="file" id="edit_photo" name="photo" class="form-control-admin" accept="image/jpeg,image/jpg,image/png,image/webp">
                                <span class="text-xxs text-muted d-block mt-1" id="edit_photo_help"></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. PERSONAL DETAILS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-user me-2"></i>Personal Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_first_name" class="form-label-admin">First name <span class="text-danger">*</span></label>
                                <input type="text" id="edit_first_name" name="first_name" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_last_name" class="form-label-admin">Last name</label>
                                <input type="text" id="edit_last_name" name="last_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_email" class="form-label-admin">Email <span class="text-danger">*</span></label>
                                <input type="email" id="edit_email" name="email" class="form-control-admin" required>
                            </div>

                            <div class="col-md-4">
                                <label for="edit_mobile_no" class="form-label-admin">Mobile no. <span class="text-danger">*</span></label>
                                <input type="tel" id="edit_mobile_no" name="mobile_no" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_alternate_mobile_no" class="form-label-admin">Alternate Mobile No.</label>
                                <input type="tel" id="edit_alternate_mobile_no" name="alternate_mobile_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_whatsapp_no" class="form-label-admin">Whatsapp No.</label>
                                <input type="tel" id="edit_whatsapp_no" name="whatsapp_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin d-block mb-2">Gender</label>
                                <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="radio" name="gender" id="edit_gender_male" value="male">
                                        <label class="form-check-label text-xs" for="edit_gender_male" style="font-family: var(--font-secondary) !important; font-weight: 600;">Male</label>
                                    </div>
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="radio" name="gender" id="edit_gender_female" value="female">
                                        <label class="form-check-label text-xs" for="edit_gender_female" style="font-family: var(--font-secondary) !important; font-weight: 600;">Female</label>
                                    </div>
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="radio" name="gender" id="edit_gender_other" value="other">
                                        <label class="form-check-label text-xs" for="edit_gender_other" style="font-family: var(--font-secondary) !important; font-weight: 600;">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_dob" class="form-label-admin">DOB</label>
                                <input type="date" id="edit_dob" name="dob" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_marital_status" class="form-label-admin">Marital Status</label>
                                <select id="edit_marital_status" name="marital_status" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_spouse_name" class="form-label-admin">Spouse name</label>
                                <input type="text" id="edit_spouse_name" name="spouse_name" class="form-control-admin">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_father_name" class="form-label-admin">Father name</label>
                                <input type="text" id="edit_father_name" name="father_name" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <!-- 3. RELIGION & CATEGORY -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-globe-hemisphere-east me-2"></i>Religion & Category</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_nationality" class="form-label-admin">Nationality</label>
                                <select id="edit_nationality" name="nationality" class="form-control-admin">
                                    <option value="INDIAN">INDIAN</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_religion" class="form-label-admin">Religion</label>
                                <select id="edit_religion" name="religion" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Muslim">Muslim</option>
                                    <option value="Christian">Christian</option>
                                    <option value="Sikh">Sikh</option>
                                    <option value="Buddhist">Buddhist</option>
                                    <option value="Jain">Jain</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_category" class="form-label-admin">Category</label>
                                <select id="edit_category" name="category" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="General">General</option>
                                    <option value="OBC">OBC</option>
                                    <option value="SC">SC</option>
                                    <option value="ST">ST</option>
                                    <option value="EWS">EWS</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 4. EXPERIENCE -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-briefcase me-2"></i>Experience (If Any)</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_last_org_name" class="form-label-admin">Last organization name</label>
                                <input type="text" id="edit_last_org_name" name="last_org_name" class="form-control-admin" placeholder="e.g. Green Valley School">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_last_job_position" class="form-label-admin">Last job position</label>
                                <input type="text" id="edit_last_job_position" name="last_job_position" class="form-control-admin" placeholder="e.g. PGT Teacher">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_exp_years" class="form-label-admin">How many years of experience do you have?</label>
                                <input type="number" id="edit_exp_years" name="exp_years" class="form-control-admin" min="0" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- 5. QUALIFICATION (DYNAMIC GRID) -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-graduation-cap me-2"></i>Qualifications</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-2 text-xs" id="edit_qualificationsTable">
                                <thead class="bg-light font-heading fw-semibold">
                                    <tr>
                                        <th>Qualification</th>
                                        <th>College / University</th>
                                        <th>Passing Year</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="edit_qualificationsTbody">
                                </tbody>
                            </table>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary py-1.5 px-3 fs-7" id="edit_addQualificationRowBtn" style="font-family: var(--font-secondary) !important; border-radius: 8px; font-weight: 700;">
                                    <i class="ph-light ph-plus-circle align-middle me-1"></i> Add Qualification
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 6. RESIDENTIAL ADDRESS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-map-pin me-2"></i>Residential Address</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="edit_pincode" class="form-label-admin">Pincode</label>
                                <input type="text" id="edit_pincode" name="pincode" class="form-control-admin" placeholder="e.g. 250001">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_city" class="form-label-admin">City</label>
                                <input type="text" id="edit_city" name="city" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_state" class="form-label-admin">State</label>
                                <input type="text" id="edit_state" name="state" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_country" class="form-label-admin">Country</label>
                                <input type="text" id="edit_country" name="country" class="form-control-admin" value="India">
                            </div>
                            <div class="col-12">
                                <label for="edit_address" class="form-label-admin">Address</label>
                                <input type="text" id="edit_address" name="address" class="form-control-admin" placeholder="House/Flat No, Block, Area, Road details">
                            </div>
                        </div>
                    </div>

                    <!-- 7. BANK ACCOUNT DETAILS -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-bank me-2"></i>Bank Account Details</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="edit_bank_acc_holder" class="form-label-admin">Account Holder Name</label>
                                <input type="text" id="edit_bank_acc_holder" name="bank_acc_holder" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_bank_name" class="form-label-admin">Bank Name</label>
                                <input type="text" id="edit_bank_name" name="bank_name" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_bank_ifsc" class="form-label-admin">IFSC Code</label>
                                <input type="text" id="edit_bank_ifsc" name="bank_ifsc" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_bank_acc_no" class="form-label-admin">Account No.</label>
                                <input type="text" id="edit_bank_acc_no" name="bank_acc_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label for="edit_pan_no" class="form-label-admin">PAN No.</label>
                                <input type="text" id="edit_pan_no" name="pan_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_pf_acc_no" class="form-label-admin">PF Account Number</label>
                                <input type="text" id="edit_pf_acc_no" name="pf_acc_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_uan_no" class="form-label-admin">Universal Account Number</label>
                                <input type="text" id="edit_uan_no" name="uan_no" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <!-- 8. AADHAR & SIGNATURE -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-identification-badge me-2"></i>Aadhar & Signature Documents</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_aadhar_no" class="form-label-admin">Aadhar No.</label>
                                <input type="text" id="edit_aadhar_no" name="aadhar_no" class="form-control-admin" placeholder="e.g. 1234 5678 9012">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_aadhar_file" class="form-label-admin">Attach Aadhar</label>
                                <input type="file" id="edit_aadhar_file" name="aadhar_file" class="form-control-admin" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">
                                <span class="text-xxs text-muted d-block mt-1" id="edit_aadhar_help"></span>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_signature_file" class="form-label-admin">Signature</label>
                                <input type="file" id="edit_signature_file" name="signature_file" class="form-control-admin" accept="image/jpeg,image/jpg,image/png,image/webp">
                                <span class="text-xxs text-muted d-block mt-1" id="edit_signature_help"></span>
                            </div>
                        </div>
                    </div>

                    <!-- 9. ASSIGN CLASS & SECTION -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-chalkboard me-2"></i>Assign Classes & Sections</h6>
                        <p class="text-xxs text-muted mb-3" style="margin-top: -6px;">You can assign multiple classes & sections to a teacher.</p>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-bordered align-middle text-xs mb-0">
                                <thead class="bg-light font-heading fw-semibold">
                                    <tr>
                                        <th style="width: 60px; text-align: center;">Assign</th>
                                        <th>Classes</th>
                                        <th>Sections</th>
                                        <th style="width: 140px; text-align: center;">Class Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_classes) || empty($all_sections)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No classes/sections seeded.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        foreach ($all_classes as $cls):
                                            foreach ($all_sections as $sec):
                                                $assign_key = $cls['id'] . '_' . $sec['id'];
                                        ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="assign_classes[]" value="<?php echo $assign_key; ?>" class="form-check-input edit-class-assign-checkbox">
                                                    </td>
                                                    <td><span class="fw-semibold"><?php echo sanitize($cls['name']); ?></span></td>
                                                    <td><span><?php echo sanitize($sec['name']); ?></span></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="class_teachers[<?php echo $assign_key; ?>]" value="1" class="form-check-input edit-class-teacher-checkbox" disabled>
                                                    </td>
                                                </tr>
                                        <?php
                                            endforeach;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 10. DESIGNATION & DEPARTMENT -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-identification-card me-2"></i>Academic Role</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_designation" class="form-label-admin">Designation</label>
                                <input type="text" id="edit_designation" name="designation" class="form-control-admin" placeholder="Ex. Physics Teacher">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_department" class="form-label-admin">Department</label>
                                <select id="edit_department" name="department" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Administrative">Administrative</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Support Staff">Support Staff</option>
                                    <option value="IT">IT</option>
                                    <option value="Finance">Finance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 11. DETAILS FOR LOGIN -->
                    <div class="modal-section-card">
                        <h6 class="font-heading fw-bold text-primary"><i class="ph-light ph-key me-2"></i>Credentials & Login Setup</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_username" class="form-label-admin">Username <span class="text-danger">*</span></label>
                                <input type="text" id="edit_username" name="username" class="form-control-admin" placeholder="e.g. madhusingh00" required>
                                <span class="text-xxs text-muted d-block mt-1">Username must be unique, it'll be used for login.</span>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_password" class="form-label-admin">Password</label>
                                <input type="password" id="edit_password" name="password" class="form-control-admin" placeholder="Leave blank to keep existing password">
                                <span class="text-xxs text-muted d-block mt-1">Leave blank if you do not wish to change the password.</span>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_biometric_code" class="form-label-admin">Biometric Code (Optional)</label>
                                <input type="text" id="edit_biometric_code" name="biometric_code" class="form-control-admin" placeholder="e.g. BIO_8872">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 p-3 bg-light" style="border-bottom-left-radius: var(--border-radius-lg); border-bottom-right-radius: var(--border-radius-lg);">
                    <button type="button" class="btn-admin-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn-admin-action">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>




<?php
require_once '../../../includes/footer.php';
?>
