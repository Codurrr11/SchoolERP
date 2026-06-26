<?php
// modules/school/students/index.php
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

// AJAX endpoint for fetching student details for edit modal
if (isset($_GET['get_student_details']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $sid = intval($_GET['id']);

    $stmt = $pdo->prepare("
        SELECT s.*, u.username as u_name, ps.parent_id
        FROM   students s
        JOIN   users u ON s.user_id = u.id
        LEFT JOIN parent_students ps ON s.id = ps.student_id
        WHERE  s.id = :id AND s.school_id = :school_id
    ");
    $stmt->execute([':id' => $sid, ':school_id' => $school_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        // Fetch student qualifications
        $stmt_q = $pdo->prepare("
            SELECT qualification, passing_year, roll_no, obtained_marks, percentage, subjects, school_college_name
            FROM   student_qualifications
            WHERE  student_id = :student_id
        ");
        $stmt_q->execute([':student_id' => $sid]);
        $student_data['qualifications'] = json_encode($stmt_q->fetchAll());

        echo json_encode(['success' => true, 'data' => $student_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
    }
    exit;
}

// AJAX endpoint for fetching parent details
if (isset($_GET['get_parent_details']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $pid = intval($_GET['id']);

    $stmt = $pdo->prepare("
        SELECT p.*, u.username as u_name
        FROM   parents p
        JOIN   users u ON p.user_id = u.id
        WHERE  p.id = :id AND p.school_id = :school_id
    ");
    $stmt->execute([':id' => $pid, ':school_id' => $school_id]);
    $parent_data = $stmt->fetch();

    if ($parent_data) {
        echo json_encode(['success' => true, 'data' => $parent_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Parent not found.']);
    }
    exit;
}


// Fetch sessions, classes and sections for dropdowns
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY id DESC");
$stmt->execute([':school_id' => $school_id]);
$all_sessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY id ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY section_name ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();

// Fetch parents for parent selection dropdown
$stmt = $pdo->prepare("SELECT * FROM parents WHERE school_id = :school_id AND deleted_at IS NULL ORDER BY first_name ASC");
$stmt->execute([':school_id' => $school_id]);
$all_parents = $stmt->fetchAll();


$sections_by_class = [];
foreach ($all_sections as $s) {
    $sections_by_class[$s['class_id']][] = [
        'id' => $s['id'],
        'name' => $s['section_name']
    ];
}

// Fetch fee structures grouped by class to pass to JavaScript
$stmt_fee_structs = $pdo->prepare("
    SELECT s.class_id, sfi.fee_name, sfi.fee_type, sfi.amount, sfi.apply_to, sfi.linked_to
    FROM student_fee_items sfi
    JOIN students s ON sfi.student_id = s.id
    WHERE s.school_id = :school_id AND sfi.is_active = 1 AND s.deleted_at IS NULL
    GROUP BY s.class_id, sfi.fee_name, sfi.fee_type
");
$stmt_fee_structs->execute([':school_id' => $school_id]);
$fee_structs_raw = $stmt_fee_structs->fetchAll(PDO::FETCH_ASSOC);

$class_fees_json_data = [];
foreach ($fee_structs_raw as $fs) {
    $class_fees_json_data[$fs['class_id']][] = [
        'fee_name' => $fs['fee_name'],
        'fee_type' => $fs['fee_type'],
        'amount' => (float)$fs['amount'],
        'apply_to' => $fs['apply_to'],
        'linked_to' => $fs['linked_to']
    ];
}

// ─── POST ACTIONS HANDLING ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: index.php');
        exit;
    }

    // Add Parent (AJAX Action)
    if ($action === 'add_parent') {
        header('Content-Type: application/json');
        
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($first_name) || empty($mobile) || empty($username)) {
            echo json_encode(['success' => false, 'message' => 'First Name, Mobile No, and Username are required fields.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
            exit;
        }

        if (empty($password)) { 
            $password = bin2hex(random_bytes(4)); // Random 8 character password
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, gender, status)
                VALUES (:school_id, 4, :username, :first_name, :last_name, :email, :phone, :password, :gender, 'active')
            ");
            $gender_val = !empty($_POST['gender']) ? strtolower($_POST['gender']) : 'male';
            $stmt->execute([
                ':school_id' => $school_id,
                ':username' => $username,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':phone' => $mobile,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':gender' => $gender_val
            ]);
            $user_id = $pdo->lastInsertId();

            $stmt_parent = $pdo->prepare("
                INSERT INTO parents (
                    school_id, user_id, first_name, last_name, mobile, alternate_mobile, whatsapp_no, email,
                    gender, parent_type, aadhaar_no, qualification, designation, company_name, company_address, company_phone, address, pincode, city, state, country, status
                ) VALUES (
                    :school_id, :user_id, :first_name, :last_name, :mobile, :alternate_mobile, :whatsapp_no, :email,
                    :gender, :parent_type, :aadhaar_no, :qualification, :designation, :company_name, :company_address, :company_phone, :address, :pincode, :city, :state, :country, 'active'
                )
            ");
            $parent_type = $_POST['parent_type'] ?? 'Father';
            $stmt_parent->execute([
                ':school_id' => $school_id,
                ':user_id' => $user_id,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':mobile' => $mobile,
                ':alternate_mobile' => $_POST['alternate_mobile'] ?? null,
                ':whatsapp_no' => $_POST['whatsapp_no'] ?? null,
                ':email' => $email,
                ':gender' => $gender_val,
                ':parent_type' => $parent_type,
                ':aadhaar_no' => $_POST['aadhaar_no'] ?? null,
                ':qualification' => $_POST['qualification'] ?? null,
                ':designation' => $_POST['designation'] ?? null,
                ':company_name' => $_POST['company_name'] ?? null,
                ':company_address' => $_POST['company_address'] ?? null,
                ':company_phone' => $_POST['company_phone'] ?? null,
                ':address' => $_POST['address'] ?? null,
                ':pincode' => $_POST['pincode'] ?? null,
                ':city' => $_POST['city'] ?? null,
                ':state' => $_POST['state'] ?? null,
                ':country' => $_POST['country'] ?? 'India'
            ]);
            $parent_id = $pdo->lastInsertId();
            
            $pdo->commit();
            $display_name = trim($first_name . ' ' . $last_name) . ' (' . ($mobile ?: 'No Mobile') . ') - ' . $parent_type;
            echo json_encode([
                'success' => true,
                'parent_id' => $parent_id,
                'display_name' => $display_name
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to create parent: ' . $e->getMessage()]);
        }
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
        header('Location: index.php');
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
        header('Location: index.php');
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
        header('Location: index.php');
        exit;
    }

    // Add Student
    if ($action === 'add') {
        // Retrieve and trim POST inputs
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile_no = trim($_POST['mobile_no'] ?? '');
        $alternate_no = trim($_POST['alternate_no'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $dob = trim($_POST['dob'] ?? '');
        $admission_date = trim($_POST['admission_date'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);
        $section_id = intval($_POST['section_id'] ?? 0);
        $session_id = intval($_POST['session_id'] ?? 0);
        $gender = trim($_POST['gender'] ?? '');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $admission_no = trim($_POST['admission_no'] ?? '');
        $aadhar_no = trim($_POST['aadhar_no'] ?? '');
        $pen_no = trim($_POST['pen_no'] ?? '');
        $apaar_id = trim($_POST['apaar_id'] ?? '');

        // Parent Information
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $guardian_name = trim($_POST['guardian_name'] ?? '');
        $father_mobile = trim($_POST['father_mobile'] ?? '');
        $mother_mobile = trim($_POST['mother_mobile'] ?? '');
        $guardian_mobile = trim($_POST['guardian_mobile'] ?? '');
        $guardian_address = trim($_POST['guardian_address'] ?? '');

        // Address Information
        $address_val = trim($_POST['address'] ?? '');
        $city_val = trim($_POST['city'] ?? '');
        $district_val = trim($_POST['district'] ?? '');
        if (empty($district_val)) {
            $district_val = $city_val;
        }
        $state_val = trim($_POST['state'] ?? '');
        $pincode_val = trim($_POST['pincode'] ?? '');
        $country_val = trim($_POST['country'] ?? 'INDIA');
        if (empty($country_val)) {
            $country_val = 'INDIA';
        }

        // Category & Reservation
        $category = trim($_POST['category'] ?? '');
        $religion = trim($_POST['religion'] ?? '');
        $is_minority = trim($_POST['is_minority'] ?? '');
        $is_rte = trim($_POST['is_rte'] ?? 'no');
        $rte_application_no = null;
        if ($is_rte === 'yes') {
            $rte_application_no = trim($_POST['rte_application_no'] ?? '');
        }
        $is_bpl = trim($_POST['is_bpl'] ?? 'no');
        $special_needs = trim($_POST['special_needs'] ?? 'no');

        // Warning bypass flag
        $name_dob_bypass = trim($_POST['name_dob_bypass'] ?? '');

        // Server-Side Validations
        if (empty($first_name) || strlen($first_name) < 2) {
            $_SESSION['flash_error'] = "Student First Name is required and must be minimum 2 characters.";
            header('Location: index.php');
            exit;
        }
        if (empty($gender)) {
            $_SESSION['flash_error'] = "Gender is required.";
            header('Location: index.php');
            exit;
        }
        if (empty($dob)) {
            $_SESSION['flash_error'] = "Date of Birth is required.";
            header('Location: index.php');
            exit;
        }
        if (strtotime($dob) > time()) {
            $_SESSION['flash_error'] = "Date of Birth cannot be a future date.";
            header('Location: index.php');
            exit;
        }
        if (empty($admission_date)) {
            $_SESSION['flash_error'] = "Admission Date is required.";
            header('Location: index.php');
            exit;
        }
        if (strtotime($admission_date) < strtotime($dob)) {
            $_SESSION['flash_error'] = "Admission Date cannot be before Date of Birth.";
            header('Location: index.php');
            exit;
        }
        if (empty($class_id) || empty($section_id) || empty($session_id)) {
            $_SESSION['flash_error'] = "Class, Section, and Academic Session are required.";
            header('Location: index.php');
            exit;
        }
        if (!empty($aadhar_no) && !preg_match('/^\d{12}$/', $aadhar_no)) {
            $_SESSION['flash_error'] = "Aadhaar Number must be exactly 12 digits.";
            header('Location: index.php');
            exit;
        }
        if (empty($mobile_no) || !preg_match('/^\d{10}$/', $mobile_no)) {
            $_SESSION['flash_error'] = "Primary Mobile Number is required and must be a valid 10-digit Indian number.";
            header('Location: index.php');
            exit;
        }
        if (!empty($alternate_no) && !preg_match('/^\d{10}$/', $alternate_no)) {
            $_SESSION['flash_error'] = "Alternate Mobile Number must be a valid 10-digit Indian number if entered.";
            header('Location: index.php');
            exit;
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Please provide a valid student Email address format.";
            header('Location: index.php');
            exit;
        }

        // Parent / Guardian Validation
        if (empty($father_name) && empty($mother_name)) {
            if (empty($guardian_name) || empty($guardian_mobile) || empty($guardian_address)) {
                $_SESSION['flash_error'] = "Guardian Name, Mobile, and Address are required if Parent details are unavailable.";
                header('Location: index.php');
                exit;
            }
        } else {
            if (empty($father_name)) {
                $_SESSION['flash_error'] = "Father Name is required.";
                header('Location: index.php');
                exit;
            }
            if (empty($mother_name)) {
                $_SESSION['flash_error'] = "Mother Name is required.";
                header('Location: index.php');
                exit;
            }
        }

        if (!empty($father_mobile) && !preg_match('/^\d{10}$/', $father_mobile)) {
            $_SESSION['flash_error'] = "Father Mobile must be a valid 10-digit Indian number.";
            header('Location: index.php');
            exit;
        }
        if (!empty($mother_mobile) && !preg_match('/^\d{10}$/', $mother_mobile)) {
            $_SESSION['flash_error'] = "Mother Mobile must be a valid 10-digit Indian number.";
            header('Location: index.php');
            exit;
        }
        if (!empty($guardian_mobile) && !preg_match('/^\d{10}$/', $guardian_mobile)) {
            $_SESSION['flash_error'] = "Guardian Mobile must be a valid 10-digit Indian number.";
            header('Location: index.php');
            exit;
        }

        // Address Validation
        if (empty($address_val) || empty($city_val) || empty($district_val) || empty($state_val) || empty($pincode_val)) {
            $_SESSION['flash_error'] = "Current Address, State, District, City, and PIN Code are required.";
            header('Location: index.php');
            exit;
        }
        if (!preg_match('/^\d{6}$/', $pincode_val)) {
            $_SESSION['flash_error'] = "PIN Code must be a valid 6-digit Indian PIN code.";
            header('Location: index.php');
            exit;
        }

        // Category & Reservation Validation
        if (empty($category)) {
            $_SESSION['flash_error'] = "Category is required.";
            header('Location: index.php');
            exit;
        }

        // Document Validations (file inputs checks)
        $photo_uploaded = (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK);
        $dob_uploaded = (isset($_FILES['dob_certificate']) && $_FILES['dob_certificate']['error'] === UPLOAD_ERR_OK);
        $aadhar_uploaded = (isset($_FILES['aadhar_file']) && $_FILES['aadhar_file']['error'] === UPLOAD_ERR_OK);
        $cat_uploaded = (isset($_FILES['category_certificate']) && $_FILES['category_certificate']['error'] === UPLOAD_ERR_OK);

        if (!$photo_uploaded) {
            $_SESSION['flash_error'] = "Passport-size Student Photo is required.";
            header('Location: index.php');
            exit;
        }
        if (!$dob_uploaded) {
            $_SESSION['flash_error'] = "Birth Certificate document is required.";
            header('Location: index.php');
            exit;
        }
        if (!empty($aadhar_no) && !$aadhar_uploaded) {
            $_SESSION['flash_error'] = "Aadhaar Card document upload is required when Aadhaar Number is provided.";
            header('Location: index.php');
            exit;
        }
        if ($is_rte === 'yes') {
            if (!$aadhar_uploaded) {
                $_SESSION['flash_error'] = "Aadhaar Card document upload is mandatory for RTE beneficiaries.";
                header('Location: index.php');
                exit;
            }
            if (!$dob_uploaded) {
                $_SESSION['flash_error'] = "Birth Certificate document upload is mandatory for RTE beneficiaries.";
                header('Location: index.php');
                exit;
            }
        }
        if (in_array($category, ['SC', 'ST', 'OBC']) && !$cat_uploaded) {
            $_SESSION['flash_error'] = "Caste Certificate document upload is required for SC/ST/OBC reservation categories.";
            header('Location: index.php');
            exit;
        }
        if (($category === 'EWS' || $is_bpl === 'yes') && !$cat_uploaded) {
            $_SESSION['flash_error'] = "Income Certificate (upload as Category Certificate) is required for EWS/BPL reservation status.";
            header('Location: index.php');
            exit;
        }

        // Unique username validation
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Username is already taken.";
            header('Location: index.php');
            exit;
        }

        // Verify Class Capacity Limit (max 40 active students per section)
        $stmt_cap = $pdo->prepare("
            SELECT COUNT(*)
            FROM students
            WHERE class_id = :class_id
              AND section_id = :section_id
              AND deleted_at IS NULL
              AND status = 'active'
        ");
        $stmt_cap->execute([':class_id' => $class_id, ':section_id' => $section_id]);
        if ($stmt_cap->fetchColumn() >= 40) {
            $_SESSION['flash_error'] = "Admission failed: The selected Class and Section has reached its maximum virtual capacity limit (40).";
            header('Location: index.php');
            exit;
        }

        // Verify Fee Structure exists
        $stmt_fee_check = $pdo->prepare("
            SELECT COUNT(*)
            FROM student_fee_items sfi
            JOIN students s ON sfi.student_id = s.id
            WHERE s.school_id = :school_id AND s.class_id = :class_id AND sfi.is_active = 1
        ");
        $stmt_fee_check->execute([':school_id' => $school_id, ':class_id' => $class_id]);
        if ($stmt_fee_check->fetchColumn() == 0) {
            $_SESSION['flash_error'] = "Admission failed: No active fee structure exists for the selected class. Admission is prevented.";
            header('Location: index.php');
            exit;
        }

        // Auto-generate Admission Number if empty
        if (empty($admission_no)) {
            $stmt_max_adm = $pdo->prepare("
                SELECT admission_no
                FROM students
                WHERE school_id = :school_id
                  AND admission_no REGEXP '^[0-9]+$'
                ORDER BY CAST(admission_no AS UNSIGNED) DESC
                LIMIT 1
            ");
            $stmt_max_adm->execute([':school_id' => $school_id]);
            $max_adm = $stmt_max_adm->fetchColumn();
            $admission_no = $max_adm ? strval(intval($max_adm) + 1) : "5001";
        }

        // Duplicate checks
        // a. Same Admission Number
        $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :school_id AND admission_no = :admission_no AND deleted_at IS NULL");
        $stmt_dup->execute([':school_id' => $school_id, ':admission_no' => $admission_no]);
        if ($stmt_dup->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Admission Number '{$admission_no}' is already assigned to another active student.";
            header('Location: index.php');
            exit;
        }

        // b. Same Aadhaar Number
        if (!empty($aadhar_no)) {
            $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :school_id AND aadhar_no = :aadhar_no AND deleted_at IS NULL");
            $stmt_dup->execute([':school_id' => $school_id, ':aadhar_no' => $aadhar_no]);
            if ($stmt_dup->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "A student with Aadhaar Number '{$aadhar_no}' is already registered.";
                header('Location: index.php');
                exit;
            }
        }

        // c. Same PEN/APAAR ID
        if (!empty($pen_no)) {
            $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :school_id AND pen_no = :pen_no AND deleted_at IS NULL");
            $stmt_dup->execute([':school_id' => $school_id, ':pen_no' => $pen_no]);
            if ($stmt_dup->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "A student with PEN Number '{$pen_no}' is already registered.";
                header('Location: index.php');
                exit;
            }
        }
        if (!empty($apaar_id)) {
            $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :school_id AND apaar_id = :apaar_id AND deleted_at IS NULL");
            $stmt_dup->execute([':school_id' => $school_id, ':apaar_id' => $apaar_id]);
            if ($stmt_dup->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "A student with APAAR ID '{$apaar_id}' is already registered.";
                header('Location: index.php');
                exit;
            }
        }

        // d. Same Roll Number in Class/Section
        if (!empty($roll_no)) {
            $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :school_id AND class_id = :class_id AND section_id = :section_id AND roll_no = :roll_no AND deleted_at IS NULL");
            $stmt_dup->execute([
                ':school_id' => $school_id,
                ':class_id' => $class_id,
                ':section_id' => $section_id,
                ':roll_no' => $roll_no
            ]);
            if ($stmt_dup->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Roll Number '{$roll_no}' is already taken in the selected Class & Section.";
                header('Location: index.php');
                exit;
            }
        }

        // e. Warning Only: Same Student Name + DOB Combination
        if ($name_dob_bypass !== 'yes') {
            $stmt_dup = $pdo->prepare("
                SELECT COUNT(*)
                FROM students
                WHERE school_id = :school_id
                  AND LOWER(first_name) = LOWER(:first_name)
                  AND LOWER(last_name) = LOWER(:last_name)
                  AND dob = :dob
                  AND deleted_at IS NULL
            ");
            $stmt_dup->execute([
                ':school_id' => $school_id,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':dob' => $dob
            ]);
            if ($stmt_dup->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Warning: A student with the same name and DOB already exists. Check the bypass box to confirm and save.";
                header('Location: index.php');
                exit;
            }
        }

        // Religion adjustment if minority checkbox is active
        if ($is_minority === 'yes') {
            $religion = empty($religion) ? '(Minority)' : $religion . ' (Minority)';
        }

        // Concatenate District into address
        $full_address = $address_val . ", District: " . $district_val;

        if (empty($password)) {
            $password = bin2hex(random_bytes(4)); // Random 8 character password
        }

        // Upload Directory
        $upload_dir = ROOT_PATH . 'uploads/students/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $photo_path = null;
        if ($photo_uploaded) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'photo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'uploads/students/' . $new_name;
            }
        }

        $dob_cert_path = null;
        if ($dob_uploaded) {
            $ext = strtolower(pathinfo($_FILES['dob_certificate']['name'], PATHINFO_EXTENSION));
            $new_name = 'dob_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['dob_certificate']['tmp_name'], $upload_dir . $new_name)) {
                $dob_cert_path = 'uploads/students/' . $new_name;
            }
        }

        $category_cert_path = null;
        if ($cat_uploaded) {
            $ext = strtolower(pathinfo($_FILES['category_certificate']['name'], PATHINFO_EXTENSION));
            $new_name = 'cat_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['category_certificate']['tmp_name'], $upload_dir . $new_name)) {
                $category_cert_path = 'uploads/students/' . $new_name;
            }
        }

        $aadhar_file_path = null;
        if ($aadhar_uploaded) {
            $ext = strtolower(pathinfo($_FILES['aadhar_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'aadhar_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['aadhar_file']['tmp_name'], $upload_dir . $new_name)) {
                $aadhar_file_path = 'uploads/students/' . $new_name;
            }
        }

        $tc_file_path = null;
        if (isset($_FILES['tc_file']) && $_FILES['tc_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['tc_file']['name'], PATHINFO_EXTENSION));
            $new_name = 'tc_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['tc_file']['tmp_name'], $upload_dir . $new_name)) {
                $tc_file_path = 'uploads/students/' . $new_name;
            }
        }

        $mother_photo_path = null;
        if (isset($_FILES['mother_photo']) && $_FILES['mother_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['mother_photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'mother_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['mother_photo']['tmp_name'], $upload_dir . $new_name)) {
                $mother_photo_path = 'uploads/students/' . $new_name;
            }
        }

        $father_photo_path = null;
        if (isset($_FILES['father_photo']) && $_FILES['father_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['father_photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'father_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['father_photo']['tmp_name'], $upload_dir . $new_name)) {
                $father_photo_path = 'uploads/students/' . $new_name;
            }
        }

        $guardian_photo_path = null;
        if (isset($_FILES['guardian_photo']) && $_FILES['guardian_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['guardian_photo']['name'], PATHINFO_EXTENSION));
            $new_name = 'guardian_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['guardian_photo']['tmp_name'], $upload_dir . $new_name)) {
                $guardian_photo_path = 'uploads/students/' . $new_name;
            }
        }

        // Fetch fee structures for selected class to calculate total and prepare seeding rows
        $stmt_class_fees = $pdo->prepare("
            SELECT sfi.fee_name, sfi.fee_type, sfi.amount, sfi.apply_to, sfi.linked_to
            FROM student_fee_items sfi
            JOIN students s ON sfi.student_id = s.id
            WHERE s.school_id = :school_id AND s.class_id = :class_id AND sfi.is_active = 1
            GROUP BY sfi.fee_name, sfi.fee_type
        ");
        $stmt_class_fees->execute([':school_id' => $school_id, ':class_id' => $class_id]);
        $class_fees = $stmt_class_fees->fetchAll(PDO::FETCH_ASSOC);

        $calculated_total_fees = 0.00;
        $student_fee_rows = [];
        foreach ($class_fees as $cf) {
            $amt = (float)$cf['amount'];
            $isTuition = (stripos($cf['fee_type'], 'tuition') !== false || stripos($cf['fee_name'], 'tuition') !== false);
            $remark = '';
            if ($is_rte === 'yes' && $isTuition) {
                $amt = 0.00;
                $remark = 'Waived under RTE';
            }
            $calculated_total_fees += $amt;
            $student_fee_rows[] = [
                'fee_name' => $cf['fee_name'],
                'fee_type' => $cf['fee_type'],
                'apply_to' => $cf['apply_to'],
                'linked_to' => $cf['linked_to'],
                'amount' => $amt,
                'remark' => $remark
            ];
        }

        try {
            $pdo->beginTransaction();

            // 1. Create Auth User
            $stmt = $pdo->prepare("
                INSERT INTO users (school_id, role_id, username, first_name, last_name, email, phone, password, gender, dob, address, pincode, city, state, country, status)
                VALUES (:school_id, 5, :username, :first_name, :last_name, :email, :phone, :password, :gender, :dob, :address, :pincode, :city, :state, :country, 'active')
            ");
            $gender_val = !empty($gender) ? strtolower($gender) : null;
            $dob_val = !empty($dob) ? $dob : null;

            $stmt->execute([
                ':school_id' => $school_id,
                ':username' => $username,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':phone' => $mobile_no,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':gender' => $gender_val,
                ':dob' => $dob_val,
                ':address' => $full_address,
                ':pincode' => $pincode_val,
                ':city' => $city_val,
                ':state' => $state_val,
                ':country' => $country_val
            ]);
            $user_id = $pdo->lastInsertId();

            // 2. Create Student Record
            $stmt_student = $pdo->prepare("
                INSERT INTO students (
                    school_id, user_id, session_id, class_id, section_id,
                    apaar_id, pen_no, registration_no_prefix, registration_no,
                    enrollment_no_prefix, enrollment_no, sr_no_prefix, sr_no, general_reg_no,
                    admission_no_prefix, admission_no, admission_date, srn_no, roll_no,
                    stream, education_medium, photo, referred_by, is_rte, rte_application_no,
                    enrolled_session, enrolled_class_id, enrolled_year, special_needs, is_bpl, house_block,
                    first_name, last_name, father_name, mobile_no, alternate_no, whatsapp_no, email,
                    gender, blood_group, height, weight, dob, place_of_birth, dob_certificate, dob_certificate_no,
                    total_fees, total_paid, total_discount, fine_amount, biometric_code, status,
                    income_app_no, caste_app_no, domicile_app_no, nationality, religion, category, caste, category_certificate,
                    aadhar_no, aadhar_file, tc_no, tc_issue_date, tc_file, scholarship_id, scholarship_password,
                    govt_student_id, govt_family_id, samagra_id, bank_name, bank_branch, ifsc_code, bank_account_holder, bank_account_no, pan_no,
                    mother_name, mother_qualification, mother_address, mother_occupation, mother_official_address, mother_income, mother_email, mother_mobile, mother_aadhar, mother_photo,
                    father_qualification, father_address, father_occupation, father_official_address, father_income, father_email, father_mobile, father_aadhar, father_photo,
                    guardian_name, guardian_qualification, guardian_address, guardian_occupation, guardian_official_address, guardian_income, guardian_email, guardian_mobile, guardian_aadhar, guardian_photo
                ) VALUES (
                    :school_id, :user_id, :session_id, :class_id, :section_id,
                    :apaar_id, :pen_no, :registration_no_prefix, :registration_no,
                    :enrollment_no_prefix, :enrollment_no, :sr_no_prefix, :sr_no, :general_reg_no,
                    :admission_no_prefix, :admission_no, :admission_date, :srn_no, :roll_no,
                    :stream, :education_medium, :photo, :referred_by, :is_rte, :rte_application_no,
                    :enrolled_session, :enrolled_class_id, :enrolled_year, :special_needs, :is_bpl, :house_block,
                    :first_name, :last_name, :father_name, :mobile_no, :alternate_no, :whatsapp_no, :email,
                    :gender, :blood_group, :height, :weight, :dob, :place_of_birth, :dob_certificate, :dob_certificate_no,
                    :total_fees, :total_paid, :total_discount, :fine_amount, :biometric_code, 'active',
                    :income_app_no, :caste_app_no, :domicile_app_no, :nationality, :religion, :category, :caste, :category_certificate,
                    :aadhar_no, :aadhar_file, :tc_no, :tc_issue_date, :tc_file, :scholarship_id, :scholarship_password,
                    :govt_student_id, :govt_family_id, :samagra_id, :bank_name, :bank_branch, :ifsc_code, :bank_account_holder, :bank_account_no, :pan_no,
                    :mother_name, :mother_qualification, :mother_address, :mother_occupation, :mother_official_address, :mother_income, :mother_email, :mother_mobile, :mother_aadhar, :mother_photo,
                    :father_qualification, :father_address, :father_occupation, :father_official_address, :father_income, :father_email, :father_mobile, :father_aadhar, :father_photo,
                    :guardian_name, :guardian_qualification, :guardian_address, :guardian_occupation, :guardian_official_address, :guardian_income, :guardian_email, :guardian_mobile, :guardian_aadhar, :guardian_photo
                )
            ");

            $stmt_student->execute([
                ':school_id' => $school_id,
                ':user_id' => $user_id,
                ':session_id' => $session_id,
                ':class_id' => $class_id,
                ':section_id' => $section_id,
                ':apaar_id' => !empty($apaar_id) ? $apaar_id : null,
                ':pen_no' => !empty($pen_no) ? $pen_no : null,
                ':registration_no_prefix' => !empty($_POST['registration_no_prefix']) ? trim($_POST['registration_no_prefix']) : null,
                ':registration_no' => !empty($_POST['registration_no']) ? trim($_POST['registration_no']) : null,
                ':enrollment_no_prefix' => !empty($_POST['enrollment_no_prefix']) ? trim($_POST['enrollment_no_prefix']) : null,
                ':enrollment_no' => !empty($_POST['enrollment_no']) ? trim($_POST['enrollment_no']) : null,
                ':sr_no_prefix' => !empty($_POST['sr_no_prefix']) ? trim($_POST['sr_no_prefix']) : null,
                ':sr_no' => !empty($_POST['sr_no']) ? trim($_POST['sr_no']) : null,
                ':general_reg_no' => !empty($_POST['general_reg_no']) ? trim($_POST['general_reg_no']) : null,
                ':admission_no_prefix' => !empty($_POST['admission_no_prefix']) ? get_flat_string($_POST['admission_no_prefix']) : null,
                ':admission_no' => $admission_no,
                ':admission_date' => !empty($_POST['admission_date']) ? $_POST['admission_date'] : null,
                ':srn_no' => !empty($_POST['srn_no']) ? get_flat_string($_POST['srn_no']) : null,
                ':roll_no' => !empty($roll_no) ? $roll_no : null,
                ':stream' => !empty($_POST['stream']) ? $_POST['stream'] : null,
                ':education_medium' => !empty($_POST['education_medium']) ? $_POST['education_medium'] : null,
                ':photo' => $photo_path,
                ':referred_by' => !empty($_POST['referred_by']) ? $_POST['referred_by'] : null,
                ':is_rte' => $is_rte,
                ':rte_application_no' => $rte_application_no,
                ':enrolled_session' => !empty($_POST['enrolled_session']) ? trim($_POST['enrolled_session']) : null,
                ':enrolled_class_id' => !empty($_POST['enrolled_class_id']) ? intval($_POST['enrolled_class_id']) : null,
                ':enrolled_year' => !empty($_POST['enrolled_year']) ? $_POST['enrolled_year'] : null,
                ':special_needs' => $special_needs,
                ':is_bpl' => $is_bpl,
                ':house_block' => !empty($_POST['house_block']) ? $_POST['house_block'] : null,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':father_name' => !empty($father_name) ? $father_name : null,
                ':mobile_no' => $mobile_no,
                ':alternate_no' => !empty($alternate_no) ? $alternate_no : null,
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
                ':total_fees' => (isset($_POST['generate_fees']) && $_POST['generate_fees'] === 'yes') ? $calculated_total_fees : 0.00,
                ':total_paid' => !empty($_POST['total_paid']) ? floatval($_POST['total_paid']) : 0.00,
                ':total_discount' => !empty($_POST['total_discount']) ? floatval($_POST['total_discount']) : 0.00,
                ':fine_amount' => !empty($_POST['fine_amount']) ? floatval($_POST['fine_amount']) : 0.00,
                ':biometric_code' => !empty($_POST['biometric_code']) ? trim($_POST['biometric_code']) : null,
                ':income_app_no' => !empty($_POST['income_app_no']) ? trim($_POST['income_app_no']) : null,
                ':caste_app_no' => !empty($_POST['caste_app_no']) ? trim($_POST['caste_app_no']) : null,
                ':domicile_app_no' => !empty($_POST['domicile_app_no']) ? trim($_POST['domicile_app_no']) : null,
                ':nationality' => !empty($_POST['nationality']) ? trim($_POST['nationality']) : 'INDIAN',
                ':religion' => $religion,
                ':category' => $category,
                ':caste' => !empty($_POST['caste']) ? trim($_POST['caste']) : null,
                ':category_certificate' => $category_cert_path,
                ':aadhar_no' => !empty($aadhar_no) ? $aadhar_no : null,
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
                ':mother_name' => !empty($mother_name) ? $mother_name : null,
                ':mother_qualification' => !empty($_POST['mother_qualification']) ? trim($_POST['mother_qualification']) : null,
                ':mother_address' => !empty($_POST['mother_address']) ? trim($_POST['mother_address']) : null,
                ':mother_occupation' => !empty($_POST['mother_occupation']) ? trim($_POST['mother_occupation']) : null,
                ':mother_official_address' => !empty($_POST['mother_official_address']) ? trim($_POST['mother_official_address']) : null,
                ':mother_income' => !empty($_POST['mother_income']) ? trim($_POST['mother_income']) : null,
                ':mother_email' => !empty($_POST['mother_email']) ? trim($_POST['mother_email']) : null,
                ':mother_mobile' => !empty($mother_mobile) ? $mother_mobile : null,
                ':mother_aadhar' => !empty($_POST['mother_aadhar']) ? trim($_POST['mother_aadhar']) : null,
                ':mother_photo' => $mother_photo_path,
                ':father_qualification' => !empty($_POST['father_qualification']) ? trim($_POST['father_qualification']) : null,
                ':father_address' => !empty($_POST['father_address']) ? trim($_POST['father_address']) : null,
                ':father_occupation' => !empty($_POST['father_occupation']) ? trim($_POST['father_occupation']) : null,
                ':father_official_address' => !empty($_POST['father_official_address']) ? trim($_POST['father_official_address']) : null,
                ':father_income' => !empty($_POST['father_income']) ? trim($_POST['father_income']) : null,
                ':father_email' => !empty($_POST['father_email']) ? trim($_POST['father_email']) : null,
                ':father_mobile' => !empty($father_mobile) ? $father_mobile : null,
                ':father_aadhar' => !empty($_POST['father_aadhar']) ? trim($_POST['father_aadhar']) : null,
                ':father_photo' => $father_photo_path,
                ':guardian_name' => !empty($guardian_name) ? $guardian_name : null,
                ':guardian_qualification' => !empty($_POST['guardian_qualification']) ? trim($_POST['guardian_qualification']) : null,
                ':guardian_address' => !empty($guardian_address) ? $guardian_address : null,
                ':guardian_occupation' => !empty($_POST['guardian_occupation']) ? trim($_POST['guardian_occupation']) : null,
                ':guardian_official_address' => !empty($_POST['guardian_official_address']) ? trim($_POST['guardian_official_address']) : null,
                ':guardian_income' => !empty($_POST['guardian_income']) ? trim($_POST['guardian_income']) : null,
                ':guardian_email' => !empty($_POST['guardian_email']) ? trim($_POST['guardian_email']) : null,
                ':guardian_mobile' => !empty($guardian_mobile) ? $guardian_mobile : null,
                ':guardian_aadhar' => !empty($_POST['guardian_aadhar']) ? trim($_POST['guardian_aadhar']) : null,
                ':guardian_photo' => $guardian_photo_path
            ]);
            $student_id = $pdo->lastInsertId();

            // Link parent account if selected
            $parent_id_selected = isset($_POST['parent_id_select']) ? intval($_POST['parent_id_select']) : 0;
            if ($parent_id_selected > 0) {
                $pdo->prepare("INSERT INTO parent_students (parent_id, student_id) VALUES (:parent_id, :student_id)")
                    ->execute([':parent_id' => $parent_id_selected, ':student_id' => $student_id]);
            }

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
                // Foolproof inline function to flatten any nested array into a string
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

            // 4. Create Student Fee Items (auto-seeding)
            if (isset($_POST['generate_fees']) && $_POST['generate_fees'] === 'yes') {
                $stmt_ins_fee = $pdo->prepare("
                    INSERT INTO student_fee_items (student_id, fee_name, fee_type, apply_to, linked_to, amount, is_active, remark)
                    VALUES (:student_id, :fee_name, :fee_type, :apply_to, :linked_to, :amount, 1, :remark)
                ");
                foreach ($student_fee_rows as $row) {
                    $stmt_ins_fee->execute([
                        ':student_id' => $student_id,
                        ':fee_name' => $row['fee_name'],
                        ':fee_type' => $row['fee_type'],
                        ':apply_to' => $row['apply_to'],
                        ':linked_to' => $row['linked_to'],
                        ':amount' => $row['amount'],
                        ':remark' => $row['remark']
                    ]);
                }
            }

            $pdo->commit();

            // Log successful admission to log file
            $log_dir = ROOT_PATH . 'uploads/students/';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0777, true);
            }
            $log_file = $log_dir . 'admission_activity.log';
            $log_msg = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Admitted student '{$first_name} {$last_name}' (Admission No: {$admission_no}) to Class ID {$class_id}, Section ID {$section_id} by User ID " . ($_SESSION['user_id'] ?? 'unknown') . "\n";
            @file_put_contents($log_file, $log_msg, FILE_APPEND);

            $_SESSION['flash_success'] = "Student registered successfully! Username: $username, Password: $password";
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($photo_path && file_exists($upload_dir . basename($photo_path))) @unlink($upload_dir . basename($photo_path));
            if ($dob_cert_path && file_exists($upload_dir . basename($dob_cert_path))) @unlink($upload_dir . basename($dob_cert_path));
            $_SESSION['flash_error'] = "Registration failed: " . $e->getMessage();
        }
        header('Location: index.php');
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
            header('Location: index.php');
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
            header('Location: index.php');
            exit;
        }

        // Unique username validation
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :user_id");
        $stmt->execute([':username' => $username, ':user_id' => $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Username is already taken.";
            header('Location: index.php');
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

            // 1. Update Auth User
            $gender_val = !empty($_POST['gender']) ? strtolower($_POST['gender']) : null;
            $dob_val = !empty($_POST['dob']) ? $_POST['dob'] : null;

            $status_posted = $_POST['status'] ?? 'active';
            $user_status_val = 'active';
            if ($status_posted === 'inactive' || $status_posted === 'passed' || $status_posted === 'dropped') {
                $user_status_val = 'inactive';
            } elseif ($status_posted === 'suspended') {
                $user_status_val = 'suspended';
            }

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
                    rte_application_no = :rte_application_no,
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
                ':rte_application_no' => (!empty($_POST['is_rte']) && $_POST['is_rte'] === 'yes') ? trim($_POST['rte_application_no'] ?? '') : null,
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

            // Update parent relationship
            $parent_id_selected = isset($_POST['parent_id_select']) ? intval($_POST['parent_id_select']) : 0;
            $pdo->prepare("DELETE FROM parent_students WHERE student_id = :student_id")->execute([':student_id' => $student_id]);
            if ($parent_id_selected > 0) {
                $pdo->prepare("INSERT INTO parent_students (parent_id, student_id) VALUES (:parent_id, :student_id)")
                    ->execute([':parent_id' => $parent_id_selected, ':student_id' => $student_id]);
            }

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
                // Foolproof inline function to flatten any nested array into a string
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

            if ($new_photo_uploaded && $student['photo'] && file_exists(ROOT_PATH . $student['photo'])) {
                @unlink(ROOT_PATH . $student['photo']);
            }
            if ($new_dob_uploaded && $student['dob_certificate'] && file_exists(ROOT_PATH . $student['dob_certificate'])) {
                @unlink(ROOT_PATH . $student['dob_certificate']);
            }
            if ($new_category_cert_uploaded && $student['category_certificate'] && file_exists(ROOT_PATH . $student['category_certificate'])) {
                @unlink(ROOT_PATH . $student['category_certificate']);
            }
            if ($new_aadhar_file_uploaded && $student['aadhar_file'] && file_exists(ROOT_PATH . $student['aadhar_file'])) {
                @unlink(ROOT_PATH . $student['aadhar_file']);
            }
            if ($new_tc_file_uploaded && $student['tc_file'] && file_exists(ROOT_PATH . $student['tc_file'])) {
                @unlink(ROOT_PATH . $student['tc_file']);
            }
            if ($new_mother_photo_uploaded && $student['mother_photo'] && file_exists(ROOT_PATH . $student['mother_photo'])) {
                @unlink(ROOT_PATH . $student['mother_photo']);
            }
            if ($new_father_photo_uploaded && $student['father_photo'] && file_exists(ROOT_PATH . $student['father_photo'])) {
                @unlink(ROOT_PATH . $student['father_photo']);
            }
            if ($new_guardian_photo_uploaded && $student['guardian_photo'] && file_exists(ROOT_PATH . $student['guardian_photo'])) {
                @unlink(ROOT_PATH . $student['guardian_photo']);
            }

            $_SESSION['flash_success'] = "Student details updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($new_photo_uploaded && $photo_path && file_exists($upload_dir . basename($photo_path))) @unlink($upload_dir . basename($photo_path));
            if ($new_dob_uploaded && $dob_cert_path && file_exists($upload_dir . basename($dob_cert_path))) @unlink($upload_dir . basename($dob_cert_path));
            $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        }
        header('Location: index.php');
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
      AND  s.status IN ('active', 'inactive')
    ORDER  BY s.id DESC
");
$stmt->execute([':school_id' => $school_id]);
$students = $stmt->fetchAll();

$missing_bio_count = 0;
foreach ($students as $s) {
    if (empty($s['biometric_code'])) {
        $missing_bio_count++;
    }
}

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <h2 class="mb-1 font-heading fw-extrabold">Students</h2>
        <p class="text-xs text-muted mb-0">Manage school students admission record, qualifications, fees overview, and directory.</p>
    </div>
</div>

<?php if ($missing_bio_count > 0): ?>
    <div class="student-bio-alert mb-4">
        <i class="ph-light ph-warning-circle alert-icon"></i>
        <span><strong><?php echo $missing_bio_count; ?> students</strong> do not have Biometric registration. Register them to track attendance automatically.</span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="teacher-header-btn btn-accent" data-bs-toggle="modal" data-bs-target="#addStudentModal" title="Add Student">
                        <i class="ph-light ph-user-plus"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-sky" title="Import Students">
                        <i class="ph-light ph-upload-simple"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-sky" title="Export Students">
                        <i class="ph-light ph-download-simple"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-red" id="bulkDeleteBtn" disabled title="Move Selected to Trash">
                        <i class="ph-light ph-trash"></i>
                    </button>
                    <a href="trash.php" class="teacher-header-btn btn-red" title="Trash Bin — Deleted Students">
                        <i class="ph-light ph-recycle"></i>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                    <div class="table-search-box m-0">
                        <i class="ph-light ph-magnifying-glass"></i>
                        <input type="text" placeholder="Search students..." id="studentSearchInput" style="height: 34px; padding-top: 0; padding-bottom: 0;">
                    </div>
                    <div class="teacher-total-badge">
                        <i class="ph-light ph-users-three"></i>
                        Total: <span class="count-num"><?php echo count($students); ?></span>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($students)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-student"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No students registered</h5>
                            <p class="text-xs text-muted mb-4">Admit your first student to get started.</p>
                            <button type="button" class="btn-admin-action mx-auto" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="ph-light ph-plus"></i> Add Student
                            </button>
                        </div>
                    <?php else: ?>
                        <form id="bulkDeleteForm" action="index.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="bulk_delete">

                            <table class="teacher-table table-premium mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 46px;"><input type="checkbox" class="table-checkbox" id="selectAllCheckbox"></th>
                                        <th style="width: 50px;">#</th>
                                        <th>Admission No.</th>
                                        <th>Roll No.</th>
                                        <th>Biometric Code</th>
                                        <th>Student</th>
                                        <th>Fees</th>
                                        <th>Status</th>
                                        <th style="width: 100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsTableBody">
                                    <?php
                                    $idx = 1;
                                    foreach ($students as $s):
                                        $fee_balance = $s['total_fees'] - $s['total_paid'] - $s['total_discount'] + $s['fine_amount'];
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" value="<?php echo $s['id']; ?>" class="table-checkbox student-select-checkbox"></td>
                                            <td><span class="cell-counter"><?php echo $idx++; ?></span></td>

                                            <td><span class="fw-bold"><?php echo sanitize(($s['admission_no_prefix'] ?? '') . $s['admission_no']); ?></span></td>

                                            <td><span class="mono"><?php echo sanitize($s['roll_no'] ?? '—'); ?></span></td>

                                            <td>
                                                <?php if (!empty($s['biometric_code'])): ?>
                                                    <span class="cell-biometric"><?php echo sanitize($s['biometric_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="cell-biometric" style="color:#cbd5e1; border-color:#f1f5f9;">—</span>
                                                <?php endif; ?>
                                            </td>

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
                                                        <span class="text-xs text-muted">Username: <strong class="text-dark"><?php echo sanitize($s['u_name']); ?></strong></span>
                                                        <span class="text-xs text-muted">Classes: <strong class="text-dark"><?php echo sanitize(($s['class_name'] ?? '') . '-' . ($s['section_name'] ?? '')); ?></strong></span>
                                                        <span class="text-xs text-muted">Father name: <strong class="text-dark"><?php echo sanitize($s['father_name'] ?? '—'); ?></strong></span>
                                                        <span class="text-xs text-muted">
                                                            Mobile: <strong class="text-dark"><?php echo sanitize($s['mobile_no'] ?? '—'); ?></strong>
                                                            <?php if (!empty($s['mobile_no'])): ?>
                                                                <a href="https://wa.me/91<?php echo preg_replace('/\D/', '', $s['mobile_no']); ?>" target="_blank" class="whatsapp-icon text-decoration-none" title="Send WhatsApp">
                                                                    <i class="ph-light ph-whatsapp-logo"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="d-flex flex-column text-xs">
                                                    <span>Total Fees: <strong class="text-dark"><?php echo intval($s['total_fees']); ?></strong></span>
                                                    <span>Total Paid: <strong class="text-dark text-success"><?php echo intval($s['total_paid']); ?></strong></span>
                                                    <span>Total Discount: <strong class="text-dark text-info"><?php echo intval($s['total_discount']); ?></strong></span>
                                                    <span>Fine Amount: <strong class="text-dark text-danger"><?php echo intval($s['fine_amount']); ?></strong></span>
                                                    <span>Total Balance:
                                                        <?php if ($fee_balance <= 0): ?>
                                                            <span class="badge bg-success text-white">No Balance</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger text-white"><?php echo intval($fee_balance); ?> Pending</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <form action="index.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                    <div class="form-check form-switch teacher-status-switch p-0 m-0">
                                                        <input class="form-check-input ms-0" type="checkbox" role="switch" <?php echo ($s['status'] === 'active') ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                    </div>
                                                </form>
                                            </td>

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
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteStudentForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_student_id">
</form>

<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addStudentModalLabel">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStudentForm" action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-primary text-uppercase mb-0">Admission Details:</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-xs fw-bold text-dark">Session</span>
                            <select name="session_id" class="form-control-admin py-1" style="width: auto; min-width: 140px;">
                                <?php foreach ($all_sessions as $ses): ?>
                                    <option value="<?php echo $ses['id']; ?>" <?php echo $ses['is_current'] ? 'selected' : ''; ?>><?php echo sanitize($ses['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">APAAR ID</label>
                                <input type="text" name="apaar_id" class="form-control-admin" placeholder="APAAR ID">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">PEN No.</label>
                                <input type="text" name="pen_no" class="form-control-admin" placeholder="PEN No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Registration No.</label>
                                <div class="input-group">
                                    <input type="text" name="registration_no_prefix" class="form-control-admin w-25" placeholder="Prefix" value="Prefix">
                                    <input type="text" name="registration_no" class="form-control-admin w-75" placeholder="Number" value="238">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Enrollment No.</label>
                                <div class="input-group">
                                    <input type="text" name="enrollment_no_prefix" class="form-control-admin w-25" value="26">
                                    <input type="text" name="enrollment_no" class="form-control-admin w-75" value="12345684">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">SR No.</label>
                                <div class="input-group">
                                    <input type="text" name="sr_no_prefix" class="form-control-admin w-25" value="26">
                                    <input type="text" name="sr_no" class="form-control-admin w-75" value="3434350">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">General Registration No.</label>
                                <input type="text" name="general_reg_no" class="form-control-admin" placeholder="General Registration No.">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Admission No. *</label>
                                <div class="input-group">
                                    <input type="text" name="admission_no_prefix" class="form-control-admin w-25" value="<?php echo date('Y'); ?>">
                                    <input type="text" name="admission_no" class="form-control-admin w-75" placeholder="Admission No" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Admission Date</label>
                                <input type="date" name="admission_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">SRN No.</label>
                                <input type="text" name="srn_no" class="form-control-admin" placeholder="SRN No.">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Roll No. *</label>
                                <input type="text" name="roll_no" class="form-control-admin" placeholder="Roll No." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Classes (Applied for) *</label>
                                <select name="class_id" id="student_class_select" class="form-control-admin" required>
                                    <option value="">-- Select Classes --</option>
                                    <?php foreach ($all_classes as $c):
                                        $class_sections = $sections_by_class[$c['id']] ?? [];
                                    ?>
                                        <option value="<?php echo $c['id']; ?>" data-sections='<?php echo json_encode($class_sections); ?>'>
                                            <?php echo sanitize($c['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Sections *</label>
                                <select name="section_id" id="student_section_select" class="form-control-admin" required>
                                    <option value="">-- Select Sections --</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Stream</label>
                                <select name="stream" class="form-control-admin">
                                    <option value="">-- Select Stream --</option>
                                    <option value="General">General</option>
                                    <option value="Science">Science</option>
                                    <option value="Commerce">Commerce</option>
                                    <option value="Arts">Arts</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Education Medium</label>
                                <select name="education_medium" class="form-control-admin">
                                    <option value="">-- Select medium --</option>
                                    <option value="English">English</option>
                                    <option value="Hindi">Hindi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Student's Photo</label>
                                <input type="file" name="photo" class="form-control-admin" accept="image/*">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Referred By</label>
                                <select name="referred_by" class="form-control-admin">
                                    <option value="">-- Select --</option>
                                    <option value="Direct">Direct</option>
                                    <option value="Staff">Staff</option>
                                    <option value="Agent">Agent</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Is RTE Student?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="is_rte" id="add_is_rte_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="add_is_rte_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="is_rte" id="add_is_rte_no" value="no" checked class="form-check-input">
                                        <label class="form-check-label" for="add_is_rte_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 rte-conditional-field d-none">
                                <!-- Placeholder to maintain row grid alignment -->
                            </div>

                            <div class="col-md-4 rte-conditional-field d-none" id="rte_app_no_container">
                                <label class="form-label-admin">RTE Application No</label>
                                <input type="text" name="rte_application_no" id="add_rte_application_no" class="form-control-admin" placeholder="RTE Application No">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Enrolled Session</label>
                                <input type="text" name="enrolled_session" class="form-control-admin" placeholder="Enrolled Session">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Enrolled Classes</label>
                                <select name="enrolled_class_id" class="form-control-admin">
                                    <option value="">-- Select Classes --</option>
                                    <?php foreach ($all_classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Enrolled Year</label>
                                <select name="enrolled_year" class="form-control-admin">
                                    <option value="">-- Select year --</option>
                                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Child with special needs?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="special_needs" id="add_special_needs_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="add_special_needs_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="special_needs" id="add_special_needs_no" value="no" checked class="form-check-input">
                                        <label class="form-check-label" for="add_special_needs_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Remark</label>
                                <input type="text" name="admission_remark" class="form-control-admin" placeholder="Remark">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Is BPL Student?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="is_bpl" id="add_is_bpl_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="add_is_bpl_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="is_bpl" id="add_is_bpl_no" value="no" checked class="form-check-input">
                                        <label class="form-check-label" for="add_is_bpl_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select house/block</label>
                                <select name="house_block" class="form-control-admin">
                                    <option value="">-- Select --</option>
                                    <option value="Red">Red</option>
                                    <option value="Green">Green</option>
                                    <option value="Blue">Blue</option>
                                    <option value="Yellow">Yellow</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Personal Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">First Name *</label>
                                <input type="text" name="first_name" id="student_first_name" class="form-control-admin" placeholder="First Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last Name</label>
                                <input type="text" name="last_name" id="student_last_name" class="form-control-admin" placeholder="Last Name">
                            </div>
                            <div class="col-md-12 d-none" id="duplicate-warning-container">
                                <div class="alert alert-warning mb-0 text-xs font-secondary py-2">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span><i class="ph-light ph-warning-circle"></i> A student with the same name and DOB already exists.</span>
                                        <div class="form-check mb-0 fw-bold text-danger ms-2">
                                            <input type="checkbox" name="name_dob_bypass" id="add_name_dob_bypass" value="yes" class="form-check-input">
                                            <label class="form-check-label" for="add_name_dob_bypass">Bypass Warning & Save anyway</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mobile no.</label>
                                <input type="text" name="mobile_no" class="form-control-admin" placeholder="Mobile no.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Alternate Number</label>
                                <input type="text" name="alternate_no" class="form-control-admin" placeholder="Alternate Number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Whatsapp no.</label>
                                <input type="text" name="whatsapp_no" class="form-control-admin" placeholder="Whatsapp no.">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Email</label>
                                <input type="email" name="email" class="form-control-admin" placeholder="Email">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Gender</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="add_gender_male" value="male" checked class="form-check-input">
                                        <label class="form-check-label" for="add_gender_male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="add_gender_female" value="female" class="form-check-input">
                                        <label class="form-check-label" for="add_gender_female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="add_gender_other" value="other" class="form-check-input">
                                        <label class="form-check-label" for="add_gender_other">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Blood Group</label>
                                <select name="blood_group" class="form-control-admin">
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
                                <input type="text" name="height" class="form-control-admin" placeholder="Height">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Weight</label>
                                <input type="text" name="weight" class="form-control-admin" placeholder="Weight">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">DOB *</label>
                                <input type="date" name="dob" id="student_dob" class="form-control-admin" required>
                                <small class="text-xs text-muted d-block mt-1" id="student_age_display"></small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Place of Birth</label>
                                <input type="text" name="place_of_birth" class="form-control-admin" placeholder="Place of Birth">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">DOB Certificate</label>
                                <input type="file" name="dob_certificate" class="form-control-admin" accept="image/*,application/pdf">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">DOB Certificate No.</label>
                                <input type="text" name="dob_certificate_no" class="form-control-admin" placeholder="DOB Certificate No.">
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fee fields to satisfy JS validation/logic -->
                    <div style="display:none;">
                        <input type="number" step="0.01" name="total_fees" id="student_total_fees" value="0.00">
                        <input type="number" step="0.01" name="total_paid" id="student_total_paid" value="0.00">
                        <input type="number" step="0.01" name="total_discount" id="student_total_discount" value="0.00">
                        <input type="number" step="0.01" name="fine_amount" id="student_fine_amount" value="0.00">
                        <div id="fee-breakdown-container"></div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Previous Qualifications Details:</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-xs" id="qualificationsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Qualification</th>
                                    <th>Pass. Year</th>
                                    <th>Roll No.</th>
                                    <th>Obt. Marks</th>
                                    <th>%</th>
                                    <th>Subjects</th>
                                    <th>Sch/Coll. Name</th>
                                    <th style="width: 50px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="qualificationsTbody">
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-2 mb-4">
                        <button type="button" class="btn btn-primary p-0 d-flex align-items-center justify-content-center" id="addQualificationRowBtn" style="width: 32px; height: 32px; border-radius: 4px;">
                            <i class="ph-bold ph-plus" style="font-size: 16px;"></i>
                        </button>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Income, Caste & Domicile Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Income Application No.</label>
                                <input type="text" name="income_app_no" class="form-control-admin" placeholder="Income Application No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Caste Application No.</label>
                                <input type="text" name="caste_app_no" class="form-control-admin" placeholder="Caste Application No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Domicile Application No.</label>
                                <input type="text" name="domicile_app_no" class="form-control-admin" placeholder="Domicile Application No.">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Parents Details:</h6>
                    <p class="text-xs text-muted mb-2" style="margin-top:-6px;">You can link the children with a parent account to make siblings.</p>
                    <div class="modal-section-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-xs fw-bold text-dark">Select parent</span>
                            <div class="d-flex align-items-center gap-2">
                                <select name="parent_id_select" id="parent_id_select" class="form-control-admin py-1" style="width: auto; min-width: 250px;">
                                    <option value="">-- Select Parent --</option>
                                    <?php foreach ($all_parents as $p): 
                                        $display = sanitize(trim($p['first_name'] . ' ' . $p['last_name']) . ' (' . ($p['mobile'] ?: 'No Mobile') . ') - ' . $p['parent_type']);
                                    ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo $display; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-primary p-0 d-flex align-items-center justify-content-center" id="addParentBtn" style="width: 32px; height: 32px; border-radius: 4px;">
                                    <i class="ph-bold ph-plus" style="font-size: 16px;"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-premium align-middle text-xs mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:120px;">Details</th>
                                        <th style="min-width:200px;">Mother</th>
                                        <th style="min-width:200px;">Father</th>
                                        <th style="min-width:200px;">Guardian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Name</strong></td>
                                        <td><input type="text" name="mother_name" class="form-control-admin py-1 fs-7" placeholder="Mother's Name"></td>
                                        <td><input type="text" name="father_name" class="form-control-admin py-1 fs-7" placeholder="Father's Name"></td>
                                        <td><input type="text" name="guardian_name" class="form-control-admin py-1 fs-7" placeholder="Guardian's Name"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Qualification</strong></td>
                                        <td>
                                            <select name="mother_qualification" class="form-control-admin py-1 fs-7">
                                                <option value="">Select</option>
                                                <option value="Under Graduate">Under Graduate</option>
                                                <option value="Graduate">Graduate</option>
                                                <option value="Post Graduate">Post Graduate</option>
                                                <option value="Doctorate">Doctorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="father_qualification" class="form-control-admin py-1 fs-7">
                                                <option value="">Select</option>
                                                <option value="Under Graduate">Under Graduate</option>
                                                <option value="Graduate">Graduate</option>
                                                <option value="Post Graduate">Post Graduate</option>
                                                <option value="Doctorate">Doctorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="guardian_qualification" class="form-control-admin py-1 fs-7">
                                                <option value="">Select</option>
                                                <option value="Under Graduate">Under Graduate</option>
                                                <option value="Graduate">Graduate</option>
                                                <option value="Post Graduate">Post Graduate</option>
                                                <option value="Doctorate">Doctorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Residential Address</strong></td>
                                        <td><input type="text" name="mother_address" class="form-control-admin py-1 fs-7" placeholder="Residential Address"></td>
                                        <td><input type="text" name="father_address" class="form-control-admin py-1 fs-7" placeholder="Residential Address"></td>
                                        <td><input type="text" name="guardian_address" class="form-control-admin py-1 fs-7" placeholder="Residential Address"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Occupation</strong></td>
                                        <td><input type="text" name="mother_occupation" class="form-control-admin py-1 fs-7" placeholder="Occupation"></td>
                                        <td><input type="text" name="father_occupation" class="form-control-admin py-1 fs-7" placeholder="Occupation"></td>
                                        <td><input type="text" name="guardian_occupation" class="form-control-admin py-1 fs-7" placeholder="Occupation"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Official Address</strong></td>
                                        <td><input type="text" name="mother_official_address" class="form-control-admin py-1 fs-7" placeholder="Official Address"></td>
                                        <td><input type="text" name="father_official_address" class="form-control-admin py-1 fs-7" placeholder="Official Address"></td>
                                        <td><input type="text" name="guardian_official_address" class="form-control-admin py-1 fs-7" placeholder="Official Address"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Annual Income</strong></td>
                                        <td><input type="text" name="mother_income" class="form-control-admin py-1 fs-7" placeholder="Annual Income"></td>
                                        <td><input type="text" name="father_income" class="form-control-admin py-1 fs-7" placeholder="Annual Income"></td>
                                        <td><input type="text" name="guardian_income" class="form-control-admin py-1 fs-7" placeholder="Annual Income"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email Address</strong></td>
                                        <td><input type="email" name="mother_email" class="form-control-admin py-1 fs-7" placeholder="Email Address"></td>
                                        <td><input type="email" name="father_email" class="form-control-admin py-1 fs-7" placeholder="Email Address"></td>
                                        <td><input type="email" name="guardian_email" class="form-control-admin py-1 fs-7" placeholder="Email Address"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mobile No.</strong></td>
                                        <td><input type="text" name="mother_mobile" class="form-control-admin py-1 fs-7" placeholder="Mobile No."></td>
                                        <td><input type="text" name="father_mobile" class="form-control-admin py-1 fs-7" placeholder="Mobile No."></td>
                                        <td><input type="text" name="guardian_mobile" class="form-control-admin py-1 fs-7" placeholder="Mobile No."></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Aadhar No.</strong></td>
                                        <td><input type="text" name="mother_aadhar" class="form-control-admin py-1 fs-7" placeholder="Aadhar No."></td>
                                        <td><input type="text" name="father_aadhar" class="form-control-admin py-1 fs-7" placeholder="Aadhar No."></td>
                                        <td><input type="text" name="guardian_aadhar" class="form-control-admin py-1 fs-7" placeholder="Aadhar No."></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Photo</strong></td>
                                        <td><input type="file" name="mother_photo" class="form-control-admin py-1 fs-7" accept="image/*"></td>
                                        <td><input type="file" name="father_photo" class="form-control-admin py-1 fs-7" accept="image/*"></td>
                                        <td><input type="file" name="guardian_photo" class="form-control-admin py-1 fs-7" accept="image/*"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Religion & Category:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label-admin">Nationality</label>
                                <input type="text" name="nationality" class="form-control-admin" value="INDIAN">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Religion</label>
                                <input type="text" name="religion" class="form-control-admin" placeholder="Religion">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Category *</label>
                                <input type="text" name="category" class="form-control-admin" placeholder="Category" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Caste</label>
                                <input type="text" name="caste" class="form-control-admin" placeholder="Caste">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Is Minority Student?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_minority" id="add_is_minority" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="add_is_minority">Yes</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-admin">Category Certificate</label>
                                <input type="file" name="category_certificate" class="form-control-admin" accept="image/*,application/pdf">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Aadhar & Attachment:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Aadhar No.</label>
                                <input type="text" name="aadhar_no" class="form-control-admin" placeholder="Aadhar No.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Attach Aadhar</label>
                                <input type="file" name="aadhar_file" class="form-control-admin" accept="image/*,application/pdf">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Transfer Certificate:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Transfer Certificate No.</label>
                                <input type="text" name="tc_no" class="form-control-admin" placeholder="Transfer Certificate No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Date of Issue</label>
                                <input type="date" name="tc_issue_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Transfer Certificate</label>
                                <input type="file" name="tc_file" class="form-control-admin" accept="image/*,application/pdf">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Scholarship:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Scholarship ID</label>
                                <input type="text" name="scholarship_id" class="form-control-admin" placeholder="Scholarship ID">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Scholarship Password</label>
                                <input type="password" name="scholarship_password" class="form-control-admin" placeholder="Scholarship Password">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Govt Portal ID:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Govt. Student ID on Portal</label>
                                <input type="text" name="govt_student_id" class="form-control-admin" placeholder="Govt. Student ID on Portal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Govt. Family ID on Portal</label>
                                <input type="text" name="govt_family_id" class="form-control-admin" placeholder="Govt. Family ID on Portal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Samagra ID</label>
                                <input type="text" name="samagra_id" class="form-control-admin" placeholder="Samagra ID">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Bank Details For Official Work:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control-admin" placeholder="Bank Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Branch</label>
                                <input type="text" name="bank_branch" class="form-control-admin" placeholder="Bank Branch">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control-admin" placeholder="IFSC Code">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Account Holder Name</label>
                                <input type="text" name="bank_account_holder" class="form-control-admin" placeholder="Account Holder Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Account No.</label>
                                <input type="text" name="bank_account_no" class="form-control-admin" placeholder="Account No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">UPI</label>
                                <input type="text" name="pan_no" class="form-control-admin" placeholder="UPI">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Last School Details (If Any):</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Name & Address of School</label>
                                <input type="text" name="last_school_name" class="form-control-admin" placeholder="Name & Address of School">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Attended Classes</label>
                                <select name="last_school_attended_classes" class="form-control-admin">
                                    <option value="">Select</option>
                                    <?php foreach ($all_classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last School Affiliated to</label>
                                <select name="last_school_affiliated" class="form-control-admin">
                                    <option value="">Select</option>
                                    <option value="CBSE">CBSE</option>
                                    <option value="ICSE">ICSE</option>
                                    <option value="State Board">State Board</option>
                                    <option value="IB">IB</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last Session</label>
                                <input type="text" name="last_school_session" class="form-control-admin" placeholder="Last Session">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Is Student Dropout?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="is_dropout" id="add_is_dropout_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="add_is_dropout_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="is_dropout" id="add_is_dropout_no" value="no" checked class="form-check-input">
                                        <label class="form-check-label" for="add_is_dropout_no">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Student Address:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label-admin">Address *</label>
                                <textarea name="address" class="form-control-admin" rows="2" placeholder="Address" required></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Pincode *</label>
                                <input type="text" name="pincode" class="form-control-admin" placeholder="Search pincode here..." required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">City *</label>
                                <input type="text" name="city" class="form-control-admin" placeholder="City name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">State *</label>
                                <input type="text" name="state" class="form-control-admin" placeholder="State name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Country *</label>
                                <input type="text" name="country" class="form-control-admin" placeholder="Country" value="INDIA" required>
                            </div>
                            <input type="hidden" name="district" id="student_district" value="">
                        </div>
                    </div>

                    <div class="modal-section-card mt-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Student admission type *</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="admission_type" id="add_admission_type_new" value="New" checked class="form-check-input">
                                        <label class="form-check-label" for="add_admission_type_new">New</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="admission_type" id="add_admission_type_old" value="Old" class="form-check-input">
                                        <label class="form-check-label" for="add_admission_type_old">Old</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Remark if any</label>
                                <input type="text" name="remark_if_any" class="form-control-admin" placeholder="Remark if any">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label-admin fw-bold">Do you want to generate the following along with this account?</label>
                            <div class="d-flex gap-4 mt-2">
                                <div class="form-check">
                                    <input type="checkbox" name="generate_fees" id="add_generate_fees" value="yes" checked class="form-check-input">
                                    <label class="form-check-label" for="add_generate_fees">
                                        Fees structure <span class="text-muted">(It'll create the student fees only if the fees structures exist.)</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="generate_id_card" id="add_generate_id_card" value="yes" class="form-check-input">
                                    <label class="form-check-label" for="add_generate_id_card">
                                        ID card <span class="text-muted">(It'll create the student ID card only if the id cards exist.)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Details for login</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Username *</label>
                                <span class="text-muted text-xxs d-block" style="margin-top: -4px; margin-bottom: 4px;">(Username must be unique, it'll be used for login)</span>
                                <input type="text" name="username" class="form-control-admin" placeholder="Username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Password</label>
                                <span class="text-muted text-xxs d-block" style="margin-top: -4px; margin-bottom: 4px;">(If you don't enter, a random password will be generated)</span>
                                <input type="password" name="password" class="form-control-admin" placeholder="Password">
                            </div>
                            <input type="hidden" name="biometric_code" value="">
                        </div>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #8b9e8f; border-color: #8b9e8f;">Close</button>
                    <button type="submit" class="btn btn-primary" style="background-color: #0d6efd; border-color: #0d6efd;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editStudentModalLabel">Edit Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data">
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
                                    <input type="text" name="registration_no_prefix" id="edit_registration_no_prefix" class="form-control-admin w-25">
                                    <input type="text" name="registration_no" id="edit_registration_no" class="form-control-admin w-75">
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
                                <label class="form-label-admin">General Registration No.</label>
                                <input type="text" name="general_reg_no" id="edit_general_reg_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Admission No. *</label>
                                <div class="input-group">
                                    <input type="text" name="admission_no_prefix" id="edit_admission_no_prefix" class="form-control-admin w-25">
                                    <input type="text" name="admission_no" id="edit_admission_no" class="form-control-admin w-75" required>
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
                                <label class="form-label-admin">Roll No. *</label>
                                <input type="text" name="roll_no" id="edit_roll_no" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Classes (Applied for) *</label>
                                <select name="class_id" id="edit_student_class_select" class="form-control-admin" required>
                                    <option value="">-- Select Classes --</option>
                                    <?php foreach ($all_classes as $c):
                                        $class_sections = $sections_by_class[$c['id']] ?? [];
                                    ?>
                                        <option value="<?php echo $c['id']; ?>" data-sections='<?php echo json_encode($class_sections); ?>'>
                                            <?php echo sanitize($c['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Sections *</label>
                                <select name="section_id" id="edit_student_section_select" class="form-control-admin" required>
                                    <option value="">-- Select Sections --</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Stream</label>
                                <select name="stream" id="edit_stream" class="form-control-admin">
                                    <option value="">-- Select Stream --</option>
                                    <option value="General">General</option>
                                    <option value="Science">Science</option>
                                    <option value="Commerce">Commerce</option>
                                    <option value="Arts">Arts</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Education Medium</label>
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
                                    <div class="form-check">
                                        <input type="radio" name="is_rte" id="edit_is_rte_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="edit_is_rte_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="is_rte" id="edit_is_rte_no" value="no" class="form-check-input">
                                        <label class="form-check-label" for="edit_is_rte_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 edit-rte-conditional-field d-none">
                                <!-- Placeholder to maintain row grid alignment -->
                            </div>

                            <div class="col-md-4 edit-rte-conditional-field d-none" id="edit_rte_app_no_container">
                                <label class="form-label-admin">RTE Application No</label>
                                <input type="text" name="rte_application_no" id="edit_rte_application_no" class="form-control-admin" placeholder="RTE Application No">
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
                                    <div class="form-check">
                                        <input type="radio" name="special_needs" id="edit_special_needs_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="edit_special_needs_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="special_needs" id="edit_special_needs_no" value="no" class="form-check-input">
                                        <label class="form-check-label" for="edit_special_needs_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Is BPL Student?</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="is_bpl" id="edit_is_bpl_yes" value="yes" class="form-check-input">
                                        <label class="form-check-label" for="edit_is_bpl_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="is_bpl" id="edit_is_bpl_no" value="no" class="form-check-input">
                                        <label class="form-check-label" for="edit_is_bpl_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Select house/block</label>
                                <select name="house_block" id="edit_house_block" class="form-control-admin">
                                    <option value="">-- Select --</option>
                                    <option value="Red">Red</option>
                                    <option value="Green">Green</option>
                                    <option value="Blue">Blue</option>
                                    <option value="Yellow">Yellow</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Personal Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">First Name *</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control-admin" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Father's Name</label>
                                <input type="text" name="father_name" id="edit_father_name" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Mobile no.</label>
                                <input type="text" name="mobile_no" id="edit_mobile_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Alternate Number</label>
                                <input type="text" name="alternate_no" id="edit_alternate_no" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Whatsapp no.</label>
                                <input type="text" name="whatsapp_no" id="edit_whatsapp_no" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Gender</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="edit_gender_male" value="male" class="form-check-input">
                                        <label class="form-check-label" for="edit_gender_male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="edit_gender_female" value="female" class="form-check-input">
                                        <label class="form-check-label" for="edit_gender_female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="edit_gender_other" value="other" class="form-check-input">
                                        <label class="form-check-label" for="edit_gender_other">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Blood Group</label>
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
                                <label class="form-label-admin">Height (cm)</label>
                                <input type="text" name="height" id="edit_height" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Weight (kg)</label>
                                <input type="text" name="weight" id="edit_weight" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">DOB</label>
                                <input type="date" name="dob" id="edit_dob" class="form-control-admin">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-admin">Place of Birth</label>
                                <input type="text" name="place_of_birth" id="edit_place_of_birth" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">DOB Certificate</label>
                                <input type="file" name="dob_certificate" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted" id="edit_dob_cert_help"></small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">DOB Certificate No.</label>
                                <input type="text" name="dob_certificate_no" id="edit_dob_certificate_no" class="form-control-admin">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Details for login</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Username *</label>
                                <input type="text" name="username" id="edit_username" class="form-control-admin" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Password</label>
                                <input type="password" name="password" id="edit_password" class="form-control-admin" placeholder="Leave blank to keep current">
                            </div>
                            <input type="hidden" name="biometric_code" id="edit_biometric_code">
                        </div>
                    </div>

                    <!-- Hidden fee fields to satisfy edit submit logic -->
                    <input type="hidden" name="total_fees" id="edit_total_fees">
                    <input type="hidden" name="total_paid" id="edit_total_paid">
                    <input type="hidden" name="total_discount" id="edit_total_discount">
                    <input type="hidden" name="fine_amount" id="edit_fine_amount">

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Previous Qualifications Details:</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-xs" id="edit_qualificationsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Qualification</th>
                                    <th>Pass. Year</th>
                                    <th>Roll No.</th>
                                    <th>Obt. Marks</th>
                                    <th>%</th>
                                    <th>Subjects</th>
                                    <th>Sch/Coll. Name</th>
                                    <th style="width: 50px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="edit_qualificationsTbody">
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-2 mb-4">
                        <button type="button" class="btn btn-primary p-0 d-flex align-items-center justify-content-center" id="edit_addQualificationRowBtn" style="width: 32px; height: 32px; border-radius: 4px;">
                            <i class="ph-bold ph-plus" style="font-size: 16px;"></i>
                        </button>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Income, Caste & Domicile Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Income Application No.</label>
                                <input type="text" name="income_app_no" id="edit_income_app_no" class="form-control-admin" placeholder="Income Application No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Caste Application No.</label>
                                <input type="text" name="caste_app_no" id="edit_caste_app_no" class="form-control-admin" placeholder="Caste Application No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Domicile Application No.</label>
                                <input type="text" name="domicile_app_no" id="edit_domicile_app_no" class="form-control-admin" placeholder="Domicile Application No.">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Parents Details:</h6>
                    <p class="text-xs text-muted mb-2" style="margin-top:-6px;">You can link the children with a parent account to make siblings.</p>
                    <div class="modal-section-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-xs fw-bold text-dark">Select parent</span>
                            <div class="d-flex align-items-center gap-2">
                                <select name="parent_id_select" id="edit_parent_id_select" class="form-control-admin py-1" style="width: auto; min-width: 250px;">
                                    <option value="">-- Select Parent --</option>
                                    <?php foreach ($all_parents as $p): 
                                        $display = sanitize(trim($p['first_name'] . ' ' . $p['last_name']) . ' (' . ($p['mobile'] ?: 'No Mobile') . ') - ' . $p['parent_type']);
                                    ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo $display; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-primary p-0 d-flex align-items-center justify-content-center" id="edit_addParentBtn" style="width: 32px; height: 32px; border-radius: 4px;">
                                    <i class="ph-bold ph-plus" style="font-size: 16px;"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-premium align-middle text-xs mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:120px;">Details</th>
                                        <th style="min-width:200px;">Mother</th>
                                        <th style="min-width:200px;">Father</th>
                                        <th style="min-width:200px;">Guardian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Name</strong></td>
                                        <td><input type="text" name="mother_name" id="edit_mother_name" class="form-control-admin py-1 fs-7" placeholder="Mother's Name"></td>
                                        <td><input type="text" name="father_name" id="edit_father_name" class="form-control-admin py-1 fs-7" placeholder="Father's Name"></td>
                                        <td><input type="text" name="guardian_name" id="edit_guardian_name" class="form-control-admin py-1 fs-7" placeholder="Guardian's Name"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Qualification</strong></td>
                                        <td>
                                            <select name="mother_qualification" id="edit_mother_qualification" class="form-control-admin py-1 fs-7">
                                                <option value="">Select</option>
                                                <option value="Under Graduate">Under Graduate</option>
                                                <option value="Graduate">Graduate</option>
                                                <option value="Post Graduate">Post Graduate</option>
                                                <option value="Doctorate">Doctorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="father_qualification" id="edit_father_qualification" class="form-control-admin py-1 fs-7">
                                                <option value="">Select</option>
                                                <option value="Under Graduate">Under Graduate</option>
                                                <option value="Graduate">Graduate</option>
                                                <option value="Post Graduate">Post Graduate</option>
                                                <option value="Doctorate">Doctorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="guardian_qualification" id="edit_guardian_qualification" class="form-control-admin py-1 fs-7">
                                                <option value="">Select</option>
                                                <option value="Under Graduate">Under Graduate</option>
                                                <option value="Graduate">Graduate</option>
                                                <option value="Post Graduate">Post Graduate</option>
                                                <option value="Doctorate">Doctorate</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Residential Address</strong></td>
                                        <td><input type="text" name="mother_address" id="edit_mother_address" class="form-control-admin py-1 fs-7" placeholder="Residential Address"></td>
                                        <td><input type="text" name="father_address" id="edit_father_address" class="form-control-admin py-1 fs-7" placeholder="Residential Address"></td>
                                        <td><input type="text" name="guardian_address" id="edit_guardian_address" class="form-control-admin py-1 fs-7" placeholder="Residential Address"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Occupation</strong></td>
                                        <td><input type="text" name="mother_occupation" id="edit_mother_occupation" class="form-control-admin py-1 fs-7" placeholder="Occupation"></td>
                                        <td><input type="text" name="father_occupation" id="edit_father_occupation" class="form-control-admin py-1 fs-7" placeholder="Occupation"></td>
                                        <td><input type="text" name="guardian_occupation" id="edit_guardian_occupation" class="form-control-admin py-1 fs-7" placeholder="Occupation"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Official Address</strong></td>
                                        <td><input type="text" name="mother_official_address" id="edit_mother_official_address" class="form-control-admin py-1 fs-7" placeholder="Official Address"></td>
                                        <td><input type="text" name="father_official_address" id="edit_father_official_address" class="form-control-admin py-1 fs-7" placeholder="Official Address"></td>
                                        <td><input type="text" name="guardian_official_address" id="edit_guardian_official_address" class="form-control-admin py-1 fs-7" placeholder="Official Address"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Annual Income</strong></td>
                                        <td><input type="text" name="mother_income" id="edit_mother_income" class="form-control-admin py-1 fs-7" placeholder="Annual Income"></td>
                                        <td><input type="text" name="father_income" id="edit_father_income" class="form-control-admin py-1 fs-7" placeholder="Annual Income"></td>
                                        <td><input type="text" name="guardian_income" id="edit_guardian_income" class="form-control-admin py-1 fs-7" placeholder="Annual Income"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email Address</strong></td>
                                        <td><input type="email" name="mother_email" id="edit_mother_email" class="form-control-admin py-1 fs-7" placeholder="Email Address"></td>
                                        <td><input type="email" name="father_email" id="edit_father_email" class="form-control-admin py-1 fs-7" placeholder="Email Address"></td>
                                        <td><input type="email" name="guardian_email" id="edit_guardian_email" class="form-control-admin py-1 fs-7" placeholder="Email Address"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mobile No.</strong></td>
                                        <td><input type="text" name="mother_mobile" id="edit_mother_mobile" class="form-control-admin py-1 fs-7" placeholder="Mobile No."></td>
                                        <td><input type="text" name="father_mobile" id="edit_father_mobile" class="form-control-admin py-1 fs-7" placeholder="Mobile No."></td>
                                        <td><input type="text" name="guardian_mobile" id="edit_guardian_mobile" class="form-control-admin py-1 fs-7" placeholder="Mobile No."></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Aadhar No.</strong></td>
                                        <td><input type="text" name="mother_aadhar" id="edit_mother_aadhar" class="form-control-admin py-1 fs-7" placeholder="Aadhar No."></td>
                                        <td><input type="text" name="father_aadhar" id="edit_father_aadhar" class="form-control-admin py-1 fs-7" placeholder="Aadhar No."></td>
                                        <td><input type="text" name="guardian_aadhar" id="edit_guardian_aadhar" class="form-control-admin py-1 fs-7" placeholder="Aadhar No."></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Photo</strong></td>
                                        <td>
                                            <input type="file" name="mother_photo" class="form-control-admin py-1 fs-7" accept="image/*">
                                            <small class="text-xs text-muted d-block mt-1" id="edit_mother_photo_help"></small>
                                        </td>
                                        <td>
                                            <input type="file" name="father_photo" class="form-control-admin py-1 fs-7" accept="image/*">
                                            <small class="text-xs text-muted d-block mt-1" id="edit_father_photo_help"></small>
                                        </td>
                                        <td>
                                            <input type="file" name="guardian_photo" class="form-control-admin py-1 fs-7" accept="image/*">
                                            <small class="text-xs text-muted d-block mt-1" id="edit_guardian_photo_help"></small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Religion & Category:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label-admin">Nationality</label>
                                <input type="text" name="nationality" id="edit_nationality" class="form-control-admin">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Religion</label>
                                <input type="text" name="religion" id="edit_religion" class="form-control-admin" placeholder="Religion">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Category</label>
                                <input type="text" name="category" id="edit_category" class="form-control-admin" placeholder="Category">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Caste</label>
                                <input type="text" name="caste" id="edit_caste" class="form-control-admin" placeholder="Caste">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-admin">Category Certificate</label>
                                <input type="file" name="category_certificate" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted d-block mt-1" id="edit_category_certificate_help"></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Aadhar & Attachment:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Aadhar No.</label>
                                <input type="text" name="aadhar_no" id="edit_aadhar_no" class="form-control-admin" placeholder="Aadhar No.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Attach Aadhar</label>
                                <input type="file" name="aadhar_file" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted d-block mt-1" id="edit_aadhar_file_help"></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Transfer Certificate:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Transfer Certificate No.</label>
                                <input type="text" name="tc_no" id="edit_tc_no" class="form-control-admin" placeholder="Transfer Certificate No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Date of Issue</label>
                                <input type="date" name="tc_issue_date" id="edit_tc_issue_date" class="form-control-admin">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Transfer Certificate</label>
                                <input type="file" name="tc_file" class="form-control-admin" accept="image/*,application/pdf">
                                <small class="text-xs text-muted d-block mt-1" id="edit_tc_file_help"></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Scholarship:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Scholarship ID</label>
                                <input type="text" name="scholarship_id" id="edit_scholarship_id" class="form-control-admin" placeholder="Scholarship ID">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Scholarship Password</label>
                                <input type="password" name="scholarship_password" id="edit_scholarship_password" class="form-control-admin" placeholder="Scholarship Password">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Govt Portal ID:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Govt. Student ID on Portal</label>
                                <input type="text" name="govt_student_id" id="edit_govt_student_id" class="form-control-admin" placeholder="Govt. Student ID on Portal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Govt. Family ID on Portal</label>
                                <input type="text" name="govt_family_id" id="edit_govt_family_id" class="form-control-admin" placeholder="Govt. Family ID on Portal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Samagra ID</label>
                                <input type="text" name="samagra_id" id="edit_samagra_id" class="form-control-admin" placeholder="Samagra ID">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Bank Details For Official Work:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Name</label>
                                <input type="text" name="bank_name" id="edit_bank_name" class="form-control-admin" placeholder="Bank Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Bank Branch</label>
                                <input type="text" name="bank_branch" id="edit_bank_branch" class="form-control-admin" placeholder="Bank Branch">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">IFSC Code</label>
                                <input type="text" name="ifsc_code" id="edit_ifsc_code" class="form-control-admin" placeholder="IFSC Code">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Account Holder Name</label>
                                <input type="text" name="bank_account_holder" id="edit_bank_account_holder" class="form-control-admin" placeholder="Account Holder Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Account No.</label>
                                <input type="text" name="bank_account_no" id="edit_bank_account_no" class="form-control-admin" placeholder="Account No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">UPI</label>
                                <input type="text" name="pan_no" id="edit_pan_no" class="form-control-admin" placeholder="UPI">
                            </div>
                        </div>
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

<div class="modal fade" id="addParentModal" tabindex="-1" aria-labelledby="addParentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addParentModalLabel">Create Parent Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addParentFormModal">
                <div class="modal-body">
                    <!-- Personal Details -->
                    <h6 class="text-primary text-uppercase mb-3">Personal Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">First Name *</label>
                                <input type="text" name="first_name" class="form-control-admin" placeholder="First Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Last Name</label>
                                <input type="text" name="last_name" class="form-control-admin" placeholder="Last Name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Mobile No. *</label>
                                <input type="text" name="mobile" class="form-control-admin" placeholder="Mobile No." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Alternate Mobile No.</label>
                                <input type="text" name="alternate_mobile" class="form-control-admin" placeholder="Alternate Mobile No.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Whatsapp No.</label>
                                <input type="text" name="whatsapp_no" class="form-control-admin" placeholder="Whatsapp No.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Email</label>
                                <input type="email" name="email" class="form-control-admin" placeholder="Email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Gender</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="p_gender_male" value="male" checked class="form-check-input">
                                        <label class="form-check-label" for="p_gender_male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="p_gender_female" value="female" class="form-check-input">
                                        <label class="form-check-label" for="p_gender_female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="gender" id="p_gender_other" value="other" class="form-check-input">
                                        <label class="form-check-label" for="p_gender_other">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Parent Type *</label>
                                <div class="d-flex gap-3 mt-2">
                                    <div class="form-check">
                                        <input type="radio" name="parent_type" id="p_type_mother" value="Mother" class="form-check-input">
                                        <label class="form-check-label" for="p_type_mother">Mother</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="parent_type" id="p_type_father" value="Father" checked class="form-check-input">
                                        <label class="form-check-label" for="p_type_father">Father</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="parent_type" id="p_type_guardian" value="Guardian" class="form-check-input">
                                        <label class="form-check-label" for="p_type_guardian">Guardian</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Qualification</label>
                                <select name="qualification" class="form-control-admin">
                                    <option value="">Select Qualification</option>
                                    <option value="Under Graduate">Under Graduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Post Graduate">Post Graduate</option>
                                    <option value="Doctorate">Doctorate</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Aadhar No.</label>
                                <input type="text" name="aadhaar_no" class="form-control-admin" placeholder="Aadhar No.">
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details -->
                    <h6 class="text-primary text-uppercase mb-3 mt-4">Employment:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Company/Business</label>
                                <input type="text" name="company_name" class="form-control-admin" placeholder="Company/Business">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Designation</label>
                                <input type="text" name="designation" class="form-control-admin" placeholder="Designation">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Company Address</label>
                                <input type="text" name="company_address" class="form-control-admin" placeholder="Company Address">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Company Phone</label>
                                <input type="text" name="company_phone" class="form-control-admin" placeholder="Company Phone">
                            </div>
                        </div>
                    </div>

                    <!-- Address Details -->
                    <h6 class="text-primary text-uppercase mb-3 mt-4">Address:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label-admin">Address</label>
                                <input type="text" name="address" class="form-control-admin" placeholder="Address">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Pincode</label>
                                <input type="text" name="pincode" class="form-control-admin" placeholder="Search pincode here...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">City</label>
                                <input type="text" name="city" class="form-control-admin" placeholder="City name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">State</label>
                                <input type="text" name="state" class="form-control-admin" placeholder="State name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Country</label>
                                <input type="text" name="country" class="form-control-admin" value="India" placeholder="Country">
                            </div>
                        </div>
                    </div>

                    <!-- Account Login details -->
                    <h6 class="text-primary text-uppercase mb-3 mt-4">Details for Account Login:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Username *</label>
                                <input type="text" name="username" class="form-control-admin" placeholder="Username (must be unique)" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Password</label>
                                <input type="password" name="password" class="form-control-admin" placeholder="Password (if empty, random will be generated)">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="student-page-data"
    data-csrf-token="<?php echo $csrf_token; ?>"
    data-base-url="<?php echo BASE_URL; ?>"
    data-flash-success="<?php echo sanitize($flash_success); ?>"
    data-flash-error="<?php echo sanitize($flash_error); ?>"
    data-fees='<?php echo json_encode($class_fees_json_data); ?>'>
</div>


<?php
require_once '../../../includes/footer.php';
?>
