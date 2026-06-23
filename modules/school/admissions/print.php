<?php
// modules/school/admissions/print.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$student_id = intval($_GET['id'] ?? 0);
if (!$student_id) {
    die("Invalid Student ID.");
}

// Fetch Student details
$stmt = $pdo->prepare("
    SELECT s.*, c.name as class_name, sec.name as section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.id = :id AND s.school_id = :school_id
");
$stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found or access denied.");
}

// Fetch School branding details
$stmt_school = $pdo->prepare("SELECT * FROM schools WHERE id = :school_id");
$stmt_school->execute([':school_id' => $school_id]);
$school = $stmt_school->fetch();

// Fetch field configuration settings
$stmt_settings = $pdo->prepare("SELECT show_fields FROM admission_form_settings WHERE school_id = :school_id");
$stmt_settings->execute([':school_id' => $school_id]);
$settings_record = $stmt_settings->fetch();

$show_fields = [];
if ($settings_record) {
    $show_fields = json_decode($settings_record['show_fields'], true) ?: [];
} else {
    // Default checked fields
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

// Map checkbox keys to human-friendly labels & student fields
$fields_map = [
    'name' => [
        'label' => 'Name',
        'value' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))
    ],
    'mobile_no' => [
        'label' => 'Mobile No.',
        'value' => $student['mobile_no'] ?? '—'
    ],
    'whatsapp_no' => [
        'label' => 'Whatsapp No.',
        'value' => $student['whatsapp_no'] ?? '—'
    ],
    'alternate_mobile_no' => [
        'label' => 'Alternate Number',
        'value' => $student['alternate_no'] ?? '—'
    ],
    'email' => [
        'label' => 'Email Address',
        'value' => $student['email'] ?? '—'
    ],
    'pen_no' => [
        'label' => 'PEN No.',
        'value' => $student['pen_no'] ?? '—'
    ],
    'apaar_id' => [
        'label' => 'APAAR ID',
        'value' => $student['apaar_id'] ?? '—'
    ],
    'admission_no' => [
        'label' => 'Admission No.',
        'value' => ($student['admission_no_prefix'] ?? '') . ($student['admission_no'] ?? '—')
    ],
    'registration_no' => [
        'label' => 'Registration No.',
        'value' => $student['registration_no'] ?? '—'
    ],
    'general_registration_no' => [
        'label' => 'General Registration No.',
        'value' => $student['general_reg_no'] ?? '—'
    ],
    'enrollment_no' => [
        'label' => 'Enrollment No.',
        'value' => $student['enrollment_no'] ?? '—'
    ],
    'sr_no' => [
        'label' => 'SR No.',
        'value' => $student['sr_no'] ?? '—'
    ],
    'srn_no' => [
        'label' => 'SRN No.',
        'value' => $student['srn_no'] ?? '—'
    ],
    'class_name' => [
        'label' => 'Class Name',
        'value' => $student['class_name'] ?? '—'
    ],
    'section_name' => [
        'label' => 'Section Name',
        'value' => $student['section_name'] ?? '—'
    ],
    'stream' => [
        'label' => 'Stream',
        'value' => $student['stream'] ?? '—'
    ],
    'house_block' => [
        'label' => 'HouseBlock',
        'value' => $student['house_block'] ?? '—'
    ],
    'medium' => [
        'label' => 'Medium',
        'value' => $student['education_medium'] ?? '—'
    ],
    'gender' => [
        'label' => 'Gender',
        'value' => ucfirst($student['gender'] ?? '—')
    ],
    'address' => [
        'label' => 'Address',
        'value' => $student['address'] ?? '—'
    ],
    'pincode' => [
        'label' => 'Pincode',
        'value' => $student['pincode'] ?? '—'
    ],
    'city' => [
        'label' => 'City',
        'value' => $student['city'] ?? '—'
    ],
    'state' => [
        'label' => 'State',
        'value' => $student['state'] ?? '—'
    ],
    'country' => [
        'label' => 'Country',
        'value' => $student['country'] ?? '—'
    ],
    'aadhar_no' => [
        'label' => 'Aadhar No.',
        'value' => $student['aadhar_no'] ?? '—'
    ],
    'blood_group' => [
        'label' => 'Blood Group',
        'value' => $student['blood_group'] ?? '—'
    ],
    'caste' => [
        'label' => 'Caste',
        'value' => $student['caste'] ?? '—'
    ],
    'category' => [
        'label' => 'Category',
        'value' => $student['category'] ?? '—'
    ],
    'religion' => [
        'label' => 'Religion',
        'value' => $student['religion'] ?? '—'
    ],
    'nationality' => [
        'label' => 'Nationality',
        'value' => $student['nationality'] ?? '—'
    ],
    'date_of_birth' => [
        'label' => 'Date of Birth',
        'value' => (!empty($student['dob'])) ? date('d-M-Y', strtotime($student['dob'])) : '—'
    ],
    'place_of_birth' => [
        'label' => 'Place of Birth',
        'value' => $student['place_of_birth'] ?? '—'
    ],
    'admission_type' => [
        'label' => 'Admission Type',
        'value' => $student['admission_type'] ?? '—'
    ],
    'is_rte_student' => [
        'label' => 'Is RTE Student?',
        'value' => (isset($student['is_rte']) && strtolower($student['is_rte']) === 'yes') ? 'Yes' : 'No'
    ],
    'is_bpl_student' => [
        'label' => 'Is BPL Student?',
        'value' => (isset($student['is_bpl']) && strtolower($student['is_bpl']) === 'yes') ? 'Yes' : 'No'
    ],
    'child_with_special_needs' => [
        'label' => 'Child with Special Needs',
        'value' => (isset($student['special_needs']) && strtolower($student['special_needs']) === 'yes') ? 'Yes' : 'No'
    ],
    'rte_application_no' => [
        'label' => 'RTE Application No.',
        'value' => $student['rte_application_no'] ?? '—'
    ],
    'attended_school' => [
        'label' => 'Attended School',
        'value' => $student['attended_school'] ?? '—'
    ],
    'attended_classes' => [
        'label' => 'Attended Classes',
        'value' => $student['attended_classes'] ?? '—'
    ],
    'school_affiliated' => [
        'label' => 'School Affiliated',
        'value' => $student['school_affiliated'] ?? '—'
    ],
    'last_session' => [
        'label' => 'Last Session',
        'value' => $student['last_session'] ?? '—'
    ],
    'roll_no' => [
        'label' => 'Roll No.',
        'value' => $student['roll_no'] ?? '—'
    ],
    'transport' => [
        'label' => 'Transport',
        'value' => $student['transport'] ?? '—'
    ],
    'transport_fees' => [
        'label' => 'Transport Fees',
        'value' => number_format($student['transport_fees'] ?? 0, 2)
    ],
    'total_fees' => [
        'label' => 'School Total Fees',
        'value' => number_format($student['total_fees'] ?? 0, 2)
    ],
    'discount_head' => [
        'label' => 'Discount Head',
        'value' => $student['discount_head'] ?? '—'
    ],
    'gross_total_fees' => [
        'label' => 'Gross Total Fees',
        'value' => number_format(($student['total_fees'] ?? 0) - ($student['total_discount'] ?? 0), 2)
    ],
    'fine' => [
        'label' => 'Fine',
        'value' => number_format($student['fine_amount'] ?? 0, 2)
    ],
    'total_paid' => [
        'label' => 'Paid Fees',
        'value' => number_format($student['total_paid'] ?? 0, 2)
    ],
    'discount' => [
        'label' => 'Discount',
        'value' => number_format($student['total_discount'] ?? 0, 2)
    ],
    'balance_fees' => [
        'label' => 'Balance Fees',
        'value' => number_format(($student['total_fees'] ?? 0) - ($student['total_paid'] ?? 0), 2)
    ],
    'mother_name' => [
        'label' => "Mother's Name",
        'value' => $student['mother_name'] ?? '—'
    ],
    'father_name' => [
        'label' => "Father's Name",
        'value' => $student['father_name'] ?? '—'
    ],
    'guardian_name' => [
        'label' => "Guardian's Name",
        'value' => $student['guardian_name'] ?? '—'
    ],
    'mother_qualification' => [
        'label' => 'Mother Qualification',
        'value' => $student['mother_qualification'] ?? '—'
    ],
    'father_qualification' => [
        'label' => 'Father Qualification',
        'value' => $student['father_qualification'] ?? '—'
    ],
    'guardian_qualification' => [
        'label' => 'Guardian Qualification',
        'value' => $student['guardian_qualification'] ?? '—'
    ],
    'mother_occupation' => [
        'label' => 'Mother Occupation',
        'value' => $student['mother_occupation'] ?? '—'
    ],
    'father_occupation' => [
        'label' => 'Father Occupation',
        'value' => $student['father_occupation'] ?? '—'
    ],
    'guardian_occupation' => [
        'label' => 'Guardian Occupation',
        'value' => $student['guardian_occupation'] ?? '—'
    ],
    'mother_address' => [
        'label' => 'Mother Residential Address',
        'value' => $student['mother_address'] ?? '—'
    ],
    'father_address' => [
        'label' => 'Father Residential Address',
        'value' => $student['father_address'] ?? '—'
    ],
    'guardian_address' => [
        'label' => 'Guardian Residential Address',
        'value' => $student['guardian_address'] ?? '—'
    ],
    'mother_official_address' => [
        'label' => 'Mother Official Address',
        'value' => $student['mother_official_address'] ?? '—'
    ],
    'father_official_address' => [
        'label' => 'Father Official Address',
        'value' => $student['father_official_address'] ?? '—'
    ],
    'guardian_official_address' => [
        'label' => 'Guardian Official Address',
        'value' => $student['guardian_official_address'] ?? '—'
    ],
    'mother_income' => [
        'label' => 'Mother Income',
        'value' => $student['mother_income'] ?? '—'
    ],
    'father_income' => [
        'label' => 'Father Income',
        'value' => $student['father_income'] ?? '—'
    ],
    'guardian_income' => [
        'label' => 'Guardian Income',
        'value' => $student['guardian_income'] ?? '—'
    ],
    'mother_email' => [
        'label' => 'Mother Email',
        'value' => $student['mother_email'] ?? '—'
    ],
    'father_email' => [
        'label' => 'Father Email',
        'value' => $student['father_email'] ?? '—'
    ],
    'guardian_email' => [
        'label' => 'Guardian Email',
        'value' => $student['guardian_email'] ?? '—'
    ],
    'mother_mobile' => [
        'label' => 'Mother Mobile',
        'value' => $student['mother_mobile'] ?? '—'
    ],
    'father_mobile' => [
        'label' => 'Father Mobile',
        'value' => $student['father_mobile'] ?? '—'
    ],
    'guardian_mobile' => [
        'label' => 'Guardian Mobile',
        'value' => $student['guardian_mobile'] ?? '—'
    ],
    'biometric_code' => [
        'label' => 'Biometric Code',
        'value' => $student['biometric_code'] ?? '—'
    ],
    'transfer_certificate_no' => [
        'label' => 'Transfer Certificate No.',
        'value' => $student['tc_no'] ?? '—'
    ],
    'transfer_certificate_date' => [
        'label' => 'Transfer Certificate Date',
        'value' => (!empty($student['tc_issue_date'])) ? date('d-M-Y', strtotime($student['tc_issue_date'])) : '—'
    ],
    'admission_date' => [
        'label' => 'Admission Date',
        'value' => (!empty($student['admission_date'])) ? date('d-M-Y', strtotime($student['admission_date'])) : '—'
    ],
    'scholarship_id' => [
        'label' => 'Scholarship ID',
        'value' => $student['scholarship_id'] ?? '—'
    ],
    'scholarship_password' => [
        'label' => 'Scholarship Password',
        'value' => $student['scholarship_password'] ?? '—'
    ],
    'domicile_application_no' => [
        'label' => 'Domicile Application No.',
        'value' => $student['domicile_app_no'] ?? '—'
    ],
    'income_application_no' => [
        'label' => 'Income Application No.',
        'value' => $student['income_app_no'] ?? '—'
    ],
    'caste_application_no' => [
        'label' => 'Caste Application No.',
        'value' => $student['caste_app_no'] ?? '—'
    ],
    'dob_certificate_no' => [
        'label' => 'DOB Certificate No.',
        'value' => $student['dob_certificate_no'] ?? '—'
    ],
    'mother_aadhar' => [
        'label' => 'Mother Aadhar No.',
        'value' => $student['mother_aadhar'] ?? '—'
    ],
    'father_aadhar' => [
        'label' => 'Father Aadhar No.',
        'value' => $student['father_aadhar'] ?? '—'
    ],
    'guardian_aadhar' => [
        'label' => 'Guardian Aadhar No.',
        'value' => $student['guardian_aadhar'] ?? '—'
    ],
    'height' => [
        'label' => 'Height',
        'value' => $student['height'] ?? '—'
    ],
    'weight' => [
        'label' => 'Weight',
        'value' => $student['weight'] ?? '—'
    ],
    'bank_name' => [
        'label' => 'Bank Name',
        'value' => $student['bank_name'] ?? '—'
    ],
    'bank_branch' => [
        'label' => 'Bank Branch',
        'value' => $student['bank_branch'] ?? '—'
    ],
    'bank_account_no' => [
        'label' => 'Bank Account No.',
        'value' => $student['bank_account_no'] ?? '—'
    ],
    'ifsc_code' => [
        'label' => 'Bank IFSC',
        'value' => $student['ifsc_code'] ?? '—'
    ],
    'bank_account_holder' => [
        'label' => 'Account Holder',
        'value' => $student['bank_account_holder'] ?? '—'
    ],
    'pan_no' => [
        'label' => 'PAN No.',
        'value' => $student['pan_no'] ?? '—'
    ],
    'official_bank_name' => [
        'label' => 'Official Bank Name',
        'value' => $student['official_bank_name'] ?? '—'
    ],
    'official_bank_branch' => [
        'label' => 'Official Bank Branch',
        'value' => $student['official_bank_branch'] ?? '—'
    ],
    'official_bank_account_no' => [
        'label' => 'Official Bank Account No.',
        'value' => $student['official_bank_account_no'] ?? '—'
    ],
    'official_bank_ifsc' => [
        'label' => 'Official Bank IFSC',
        'value' => $student['official_bank_ifsc'] ?? '—'
    ],
    'official_account_holder' => [
        'label' => 'Official Account Holder',
        'value' => $student['official_account_holder'] ?? '—'
    ],
    'official_upi' => [
        'label' => 'Official UPI',
        'value' => $student['official_upi'] ?? '—'
    ],
    'referred_by' => [
        'label' => 'Referred By',
        'value' => $student['referred_by'] ?? '—'
    ],
    'enrolled_session' => [
        'label' => 'Enrolled Session',
        'value' => $student['enrolled_session'] ?? '—'
    ],
    'enrolled_year' => [
        'label' => 'Enrolled Year',
        'value' => $student['enrolled_year'] ?? '—'
    ],
    'enrolled_classes' => [
        'label' => 'Enrolled Classes',
        'value' => $student['enrolled_classes'] ?? '—'
    ],
    'govt_student_id' => [
        'label' => 'Govt Student ID',
        'value' => $student['govt_student_id'] ?? '—'
    ],
    'govt_family_id' => [
        'label' => 'Govt Family ID',
        'value' => $student['govt_family_id'] ?? '—'
    ],
    'dropout' => [
        'label' => 'Dropout',
        'value' => $student['dropout'] ?? '—'
    ],
    'dropout_reason' => [
        'label' => 'Dropout Reason',
        'value' => $student['dropout_reason'] ?? '—'
    ],
    'dropout_date' => [
        'label' => 'Dropout Date',
        'value' => (!empty($student['dropout_date'])) ? date('d-M-Y', strtotime($student['dropout_date'])) : '—'
    ],
    'samagra_id' => [
        'label' => 'Samagra ID',
        'value' => $student['samagra_id'] ?? '—'
    ],
    'last_active' => [
        'label' => 'Last Active',
        'value' => $student['last_active'] ?? '—'
    ],
    'status' => [
        'label' => 'Status',
        'value' => ucfirst($student['status'] ?? '—')
    ],
    'created_at' => [
        'label' => 'Account Creation Date',
        'value' => (!empty($student['created_at'])) ? date('d-M-Y', strtotime($student['created_at'])) : '—'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admission Form - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <!-- Include Poppins Google font & Phosphor icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="../../../assets/css/main.css">
</head>
<body class="admission-print-view">

    <div class="admission-print-container">
        
        <!-- School Branding Header -->
        <div class="admission-print-header">
            <div class="admission-print-logo-section">
                <?php if (!empty($school['logo'])): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($school['logo']); ?>" alt="School Logo" class="admission-print-logo">
                <?php else: ?>
                    <div class="admission-print-logo-placeholder">S</div>
                <?php endif; ?>
                <div class="admission-print-school-info">
                    <h2><?php echo htmlspecialchars($school['name'] ?? 'SchoolSaaS Academy'); ?></h2>
                    <p><?php echo htmlspecialchars($school['address'] ?? 'Education Center City, Country'); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($school['phone'] ?? '—'); ?> | Email: <?php echo htmlspecialchars($school['email'] ?? '—'); ?></p>
                </div>
            </div>
            
            <!-- Photo Slot (if checked or by default) -->
            <div class="admission-print-photo-container">
                <div class="admission-print-photo-box">
                    <?php if (!empty($student['photo'])): ?>
                        <img src="<?php echo BASE_URL . htmlspecialchars($student['photo']); ?>" alt="Student Photo">
                    <?php else: ?>
                        Affix Student<br>Photo Here
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form Title -->
        <div class="admission-print-title">
            <h3>Student Admission Form</h3>
        </div>

        <!-- Form Fields Grid (filtered based on show_fields settings) -->
        <div class="admission-print-grid">
            <?php 
            foreach ($show_fields as $field_key) {
                if (isset($fields_map[$field_key])) {
                    $item = $fields_map[$field_key];
                    ?>
                    <div class="admission-print-field">
                        <span class="field-label"><?php echo htmlspecialchars($item['label']); ?>:</span>
                        <span class="field-value"><?php echo htmlspecialchars($item['value']); ?></span>
                    </div>
                    <?php
                }
            }
            ?>
        </div>

        <!-- Signatures block -->
        <div class="admission-print-signatures">
            <div class="admission-print-sig-line">Student Signature</div>
            <div class="admission-print-sig-line">Parent / Guardian Signature</div>
            <div class="admission-print-sig-line">Principal Signature</div>
        </div>

    </div>

</body>
</html>
