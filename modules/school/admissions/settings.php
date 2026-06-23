<?php
// modules/school/admissions/settings.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Form Fields array
$all_fields = [
    'name' => 'Name',
    'mobile_no' => 'Mobile No.',
    'whatsapp_no' => 'Whatsapp No.',
    'alternate_mobile_no' => 'Alternate Number',
    'email' => 'Email Address',
    'pen_no' => 'PEN No.',
    'apaar_id' => 'APAAR ID',
    'admission_no' => 'Admission No.',
    'registration_no' => 'Registration No.',
    'general_registration_no' => 'General Registration No.',
    'enrollment_no' => 'Enrollment No.',
    'sr_no' => 'SR No.',
    'srn_no' => 'SRN No.',
    'class_name' => 'Classes Name',
    'section_name' => 'Classes Sections',
    'stream' => 'Stream',
    'house_block' => 'HouseBlock',
    'medium' => 'Medium',
    'gender' => 'Gender',
    'address' => 'Address',
    'pincode' => 'Pincode',
    'city' => 'city',
    'state' => 'state',
    'country' => 'country',
    'aadhar_no' => 'Aadhar No.',
    'blood_group' => 'Blood Group',
    'caste' => 'Caste',
    'category' => 'Category',
    'religion' => 'Religion',
    'nationality' => 'Nationality',
    'date_of_birth' => 'Date of Birth',
    'place_of_birth' => 'Place of Birth',
    'admission_type' => 'Admission Type',
    'is_rte_student' => 'Is RTE Student?',
    'is_bpl_student' => 'Is BPL Student?',
    'child_with_special_needs' => 'Child with special needs',
    'rte_application_no' => 'RTE Application No.',
    'attended_school' => 'Attended School',
    'attended_classes' => 'Attended Classes',
    'school_affiliated' => 'School Affiliated',
    'last_session' => 'Last Session',
    'roll_no' => 'Roll No.',
    'transport' => 'Transport',
    'transport_fees' => 'Transport Fees',
    'total_fees' => 'School Total Fees',
    'discount_head' => 'discount head',
    'gross_total_fees' => 'gross_total_fees',
    'fine' => 'Fine',
    'total_paid' => 'paid_fees',
    'discount' => 'discount',
    'balance_fees' => 'balance_fees',
    'mother_name' => "Mother's Name",
    'father_name' => "Father's Name",
    'guardian_name' => "Guardian's Name",
    'mother_qualification' => 'Mother Qualification',
    'father_qualification' => 'Father Qualification',
    'guardian_qualification' => 'guardian Qualification',
    'mother_occupation' => 'Mother Occupation',
    'father_occupation' => 'Father Occupation',
    'guardian_occupation' => 'guardian Occupation',
    'mother_address' => 'Mother Residential Address',
    'father_address' => 'Father Residential Address',
    'guardian_address' => 'guardian Residential Address',
    'mother_official_address' => 'Mother official address',
    'father_official_address' => 'Father official address',
    'guardian_official_address' => 'guardian official address',
    'mother_income' => 'mother income',
    'father_income' => 'father income',
    'guardian_income' => 'guardian income',
    'mother_email' => 'mother email',
    'father_email' => 'father email',
    'guardian_email' => 'guardian email',
    'mother_mobile' => 'mother mobile',
    'father_mobile' => 'father mobile',
    'guardian_mobile' => 'guardian mobile',
    'biometric_code' => 'Biometric Code',
    'transfer_certificate_no' => 'Transfer Certificate No.',
    'transfer_certificate_date' => 'Transfer Certificate Date',
    'admission_date' => 'Admission Date',
    'scholarship_id' => 'Scholarship ID',
    'scholarship_password' => 'Scholarship Password',
    'domicile_application_no' => 'Domicile Application No.',
    'income_application_no' => 'Income Application No.',
    'caste_application_no' => 'Caste Application No.',
    'dob_certificate_no' => 'DOB Certificate No.',
    'mother_aadhar' => 'Mother Aadhar No.',
    'father_aadhar' => 'Father Aadhar No.',
    'guardian_aadhar' => 'Guardian Aadhar No.',
    'height' => 'Height',
    'weight' => 'Weight',
    'bank_name' => 'Bank Name',
    'bank_branch' => 'Bank Branch',
    'bank_account_no' => 'Bank Account No.',
    'ifsc_code' => 'Bank IFSC',
    'bank_account_holder' => 'Account Holder',
    'pan_no' => 'PAN No.',
    'official_bank_name' => 'Official Bank Name',
    'official_bank_branch' => 'Official Bank Branch',
    'official_bank_account_no' => 'Official Bank Account No.',
    'official_bank_ifsc' => 'Official Bank IFSC',
    'official_account_holder' => 'Official Account Holder',
    'official_upi' => 'Official UPI',
    'referred_by' => 'Referred By',
    'enrolled_session' => 'Enrolled Session',
    'enrolled_year' => 'Enrolled Year',
    'enrolled_classes' => 'Enrolled Classes',
    'govt_student_id' => 'Govt Student ID',
    'govt_family_id' => 'Govt Family ID',
    'dropout' => 'Dropout',
    'dropout_reason' => 'Dropout Reason',
    'dropout_date' => 'Dropout Date',
    'samagra_id' => 'Samagra ID',
    'last_active' => 'last_active',
    'status' => 'Status',
    'created_at' => 'Account Creation Date'
];

// POST Action: Save configuration settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: settings.php');
        exit;
    }

    $selected_fields = $_POST['show_fields'] ?? [];
    $show_fields_json = json_encode($selected_fields);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admission_form_settings (school_id, show_fields) 
            VALUES (:school_id, :show_fields)
            ON DUPLICATE KEY UPDATE show_fields = :show_fields_update
        ");
        $stmt->execute([
            ':school_id' => $school_id,
            ':show_fields' => $show_fields_json,
            ':show_fields_update' => $show_fields_json
        ]);

        $_SESSION['flash_success'] = "Admission form field configuration saved successfully.";
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Failed to save configuration settings: " . $e->getMessage();
        header('Location: settings.php');
        exit;
    }
}

// GET: Load existing settings
$stmt = $pdo->prepare("SELECT show_fields FROM admission_form_settings WHERE school_id = :school_id");
$stmt->execute([':school_id' => $school_id]);
$settings_record = $stmt->fetch();

$show_fields = [];
if ($settings_record) {
    $show_fields = json_decode($settings_record['show_fields'], true) ?: [];
} else {
    // Default checked fields from screenshot 2
    $show_fields = [
        'name', 'mobile_no', 'whatsapp_no', 'admission_no', 'registration_no', 
        'enrollment_no', 'sr_no', 'class_name', 'section_name', 'gender', 
        'city', 'state', 'country', 'blood_group', 'caste', 'category', 
        'religion', 'nationality', 'date_of_birth', 'admission_type', 
        'mother_name', 'father_name', 'father_occupation', 'mother_mobile', 
        'father_mobile', 'admission_date', 'dob_certificate_no', 
        'mother_aadhar', 'father_aadhar'
    ];
}

// Check if all fields are checked to set select all toggle
$all_checked = (count($show_fields) === count($all_fields));

$csrf_token = generate_csrf_token();

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-back-premium" title="Back">
                <i class="ph-bold ph-arrow-left fs-5 text-secondary"></i>
            </a>
            <h2 class="mb-0 font-heading fw-extrabold text-dark text-2xl">Configure Fields</h2>
        </div>
    </div>
</div>

<!-- ── Settings Config Container ──────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <form method="POST" action="settings.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="admission-settings-container">
                <!-- Checkbox Settings Header -->
                <div class="admission-settings-header">
                    <h5 class="fw-bold mb-0 font-heading text-dark text-base">
                        Configure which fields to show in the Admission Form.
                    </h5>
                    <!-- Check / Uncheck All -->
                    <label class="d-flex align-items-center gap-2 font-primary text-xs text-dark fw-bold cursor-pointer">
                        <input type="checkbox" id="checkUncheckAll" class="admission-toggle-checkbox" <?php echo $all_checked ? 'checked' : ''; ?>>
                        Check / Uncheck All
                    </label>
                </div>

                <!-- Fields Grid -->
                <div class="admission-settings-grid">
                    <?php foreach ($all_fields as $key => $label): ?>
                        <label class="admission-settings-item">
                            <input type="checkbox" name="show_fields[]" value="<?php echo $key; ?>" class="admission-field-checkbox" <?php echo in_array($key, $show_fields) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Footer Save / Cancel Controls -->
                <div class="admission-settings-footer">
                    <a href="index.php" class="btn btn-secondary-premium">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-info-premium">
                        Save Settings
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<?php
require_once '../../../includes/footer.php';
?>
