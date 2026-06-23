<?php
// modules/school/fees/fees-settings.php
// Blueprint reference: modules/school/fees/index.php (auth/csrf/db patterns, form-control-admin, demand-pill, Swal)
// DB: requires `fees_settings` table — see fees_settings_migration.sql in this same folder. Run it once.
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// ── Static option lists ──────────────────────────────────────────────
$ALL_MONTHS = [
    'Apr' => 'April', 'May' => 'May', 'Jun' => 'June', 'Jul' => 'July',
    'Aug' => 'August', 'Sep' => 'September', 'Oct' => 'October', 'Nov' => 'November',
    'Dec' => 'December', 'Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March'
];

$RECEIPT_FIELD_OPTIONS = [
    'Session', 'Receipt No.', 'Payment Mode', 'Payment Date', 'Name', 'Class',
    'Father Name', 'Fees Type', 'Fees', 'Fine', 'Fine Discount', 'Fees in Words', 'Remark'
];

$HEADER_DETAIL_TOGGLES = [
    'show_logo'                => 'Show Logo on Fees Receipt/Structure?',
    'show_org_address'         => 'Show Organisation address on Fees Receipt/Structure?',
    'show_affiliation_code'    => 'Show Affiliation Code on Fees Receipt/Structure?',
    'show_affiliated_to'       => 'Show Affiliated to on Fees Receipt/Structure?',
    'show_org_code'            => 'Show Organisation Code on Fees Receipt/Structure?',
    'show_phone'                => 'Show phone on Fees Receipt/Structure?',
    'show_email'                => 'Show email on Fees Receipt/Structure?',
    'show_watermark_receipt'   => 'Show watermark on Fees Receipt/Structure?',
    'show_watermark_demand'    => 'Show watermark on Demand Bill?',
    'show_tagline'              => 'Do you want to show Tagline on Fee Receipt?',
    'show_gst'                  => 'Do you want to show GST No. on Fees Receipt?',
    'show_accountant_signature' => 'Show Accountant Signature on Fees Receipt?',
    'print_qr_demand'          => 'Do you want to Print QR on Demand Bill?',
    'print_qr_receipt'         => 'Do you want to Print QR on Fees Receipt?',
];

$BEHAVIOR_TOGGLES = [
    'regen_receipt_no_on_delete'   => 'Generate new receipt no. if fees deleted',
    'regen_receipt_no_on_session'  => 'Generate fees receipt no. from 1 when session change',
    'hide_amount_in_words'         => 'Hide Receiving Amount in words from receipt?',
];

$PRINT_TOGGLES = [
    'print_single_page'        => 'Print student & account fee receipt on single page?',
    'open_print_next_tab'      => 'Open receipt to print in next tab after fees collection?',
    'hide_received_by'         => "Hide 'Fees Received By' on fee receipts?",
    'admin_select_past_date'   => 'Only admin can select past date while paying fees?',
];

$RECEIPT_PRINT_LAYOUTS = ['Layout 1', 'Layout 2', 'Layout 3']; // TODO: confirm actual layout names with design/print module

$SMS_TEMPLATES = [
    'Online Paymnt Received - Parent' => "Dear {USER_NAME},\n\nThank you for an online payment of Rs. {TEXT}. Download fee receipt from 'paid fee log' section or visit school - {SCHOOL_NAME}\n\n- Vedmarg School ERP",
    'Fee Received Alert!'             => "Dear {USER_NAME},\n\nWe received Rs. {TEXT}. For more details, check receipt or install app https://vedmarg.com/app/ - {SCHOOL_NAME}\n\nVedmarg",
];
$DEFAULTER_SMS_TEMPLATES = [
    'Fees Defaulter Reminder' => "Dear {USER_NAME},\n\nYour ward has pending fee dues of Rs. {TEXT}. Please clear the dues at the earliest - {SCHOOL_NAME}",
];

// ── POST: save settings ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_fees_settings') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid CSRF token.";
        header("Location: fees-settings.php");
        exit;
    }

    // Existing row (needed for receipt no. change detection + existing QR path)
    $stmt = $pdo->prepare("SELECT * FROM fees_settings WHERE school_id = :school_id");
    $stmt->execute([':school_id' => $school_id]);
    $existing = $stmt->fetch();

    $fees_months = isset($_POST['fees_months']) && is_array($_POST['fees_months']) ? implode(',', $_POST['fees_months']) : '';
    $transport_fees_months = isset($_POST['transport_fees_months']) && is_array($_POST['transport_fees_months']) ? implode(',', $_POST['transport_fees_months']) : '';
    $receipt_fields = isset($_POST['receipt_fields']) && is_array($_POST['receipt_fields']) ? implode(',', $_POST['receipt_fields']) : '';

    $receipt_label = trim($_POST['receipt_label'] ?? 'Fees Receipt');
    $receipt_prefix = trim($_POST['receipt_prefix'] ?? '');
    $start_receipt_no = trim($_POST['start_receipt_no'] ?? '');

    // Only bump the "last updated on" stamp if the receipt number actually changed
    $receipt_no_updated_at = $existing['receipt_no_updated_at'] ?? null;
    if (!$existing || $existing['start_receipt_no'] !== $start_receipt_no) {
        $receipt_no_updated_at = date('Y-m-d H:i:s');
    }

    $font_header_school_name = intval($_POST['font_header_school_name'] ?? 18);
    $font_header_details = intval($_POST['font_header_details'] ?? 12);
    $font_receipt_title = intval($_POST['font_receipt_title'] ?? 14);
    $font_other_details = intval($_POST['font_other_details'] ?? 12);
    $logo_width = intval($_POST['logo_width'] ?? 60);

    $fine_enabled = isset($_POST['fine_enabled']) ? 1 : 0;
    $fine_same_day = isset($_POST['fine_same_day']) ? 1 : 0;

    // Header Details + Behavior + Print toggles -> single JSON blob
    $receipt_options = [];
    foreach (array_keys($HEADER_DETAIL_TOGGLES) as $key) {
        $receipt_options[$key] = isset($_POST['ro_' . $key]) ? 1 : 0;
    }
    foreach (array_keys($BEHAVIOR_TOGGLES) as $key) {
        $receipt_options[$key] = isset($_POST['ro_' . $key]) ? 1 : 0;
    }
    foreach (array_keys($PRINT_TOGGLES) as $key) {
        $receipt_options[$key] = isset($_POST['ro_' . $key]) ? 1 : 0;
    }
    $receipt_print_layout = trim($_POST['receipt_print_layout'] ?? '');

    // Notifications -> single JSON blob
    $notifications = [
        'sms_fees_received_enabled'            => isset($_POST['sms_fees_received_enabled']) ? 1 : 0,
        'sms_fees_received_template'           => trim($_POST['sms_fees_received_template'] ?? ''),
        'sms_fees_received_gateway_enabled'    => isset($_POST['sms_fees_received_gateway_enabled']) ? 1 : 0,
        'sms_defaulter_enabled'                => isset($_POST['sms_defaulter_enabled']) ? 1 : 0,

        'app_fees_received_enabled'            => isset($_POST['app_fees_received_enabled']) ? 1 : 0,
        'app_fees_received_gateway_enabled'    => isset($_POST['app_fees_received_gateway_enabled']) ? 1 : 0,
        'app_defaulter_enabled'                => isset($_POST['app_defaulter_enabled']) ? 1 : 0,

        'admin_fees_collection_sms_enabled'         => isset($_POST['admin_fees_collection_sms_enabled']) ? 1 : 0,
        'admin_fees_collection_mobiles'             => trim($_POST['admin_fees_collection_mobiles'] ?? ''),
        'admin_fees_collection_template'            => trim($_POST['admin_fees_collection_template'] ?? ''),

        'admin_fees_collection_gateway_sms_enabled' => isset($_POST['admin_fees_collection_gateway_sms_enabled']) ? 1 : 0,
        'admin_fees_collection_gateway_mobiles'     => trim($_POST['admin_fees_collection_gateway_mobiles'] ?? ''),
        'admin_fees_collection_gateway_template'    => trim($_POST['admin_fees_collection_gateway_template'] ?? ''),

        'admin_daily_total_sms_enabled'    => isset($_POST['admin_daily_total_sms_enabled']) ? 1 : 0,
        'admin_daily_total_mobiles'        => trim($_POST['admin_daily_total_mobiles'] ?? ''),
        'admin_daily_total_time'           => trim($_POST['admin_daily_total_time'] ?? '10:00 AM'),
        'admin_daily_total_template'       => trim($_POST['admin_daily_total_template'] ?? ''),

        'wa_fees_received_enabled'  => isset($_POST['wa_fees_received_enabled']) ? 1 : 0,
        'wa_fees_received_template' => trim($_POST['wa_fees_received_template'] ?? ''),
        'wa_defaulter_enabled'      => isset($_POST['wa_defaulter_enabled']) ? 1 : 0,
        'wa_defaulter_template'     => trim($_POST['wa_defaulter_template'] ?? ''),
    ];

    $receipt_note = $_POST['receipt_note'] ?? '';
    $migrated_students_enabled = isset($_POST['migrated_students_enabled']) ? 1 : 0;
    $low_fees_notice_enabled = isset($_POST['low_fees_notice_enabled']) ? 1 : 0;

    // QR code upload (optional, multipart)
    $qr_code_path = $existing['qr_code_path'] ?? null;
    if (isset($_POST['remove_qr_code']) && $_POST['remove_qr_code'] === '1') {
        $qr_code_path = null; // TODO: optionally unlink() the old file from disk
    }
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['qr_code']['tmp_name'];
        $file_name = $_FILES['qr_code']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_exts) && $_FILES['qr_code']['size'] <= 5 * 1024 * 1024) {
            $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/qr_codes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_file_name = 'qr_' . $school_id . '_' . uniqid() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $qr_code_path = 'uploads/qr_codes/' . $new_file_name;
            }
        } else {
            $_SESSION['flash_error'] = "QR Code must be JPG, JPEG, PNG or WEBP and under 5MB.";
        }
    }

    if ($existing) {
        $stmt_upd = $pdo->prepare("
            UPDATE fees_settings SET
                fees_months = :fees_months, transport_fees_months = :transport_fees_months,
                receipt_label = :receipt_label, receipt_prefix = :receipt_prefix,
                start_receipt_no = :start_receipt_no, receipt_no_updated_at = :receipt_no_updated_at,
                font_header_school_name = :font_header_school_name, font_header_details = :font_header_details,
                font_receipt_title = :font_receipt_title, font_other_details = :font_other_details,
                logo_width = :logo_width, receipt_fields = :receipt_fields, qr_code_path = :qr_code_path,
                fine_enabled = :fine_enabled, fine_same_day = :fine_same_day,
                receipt_options = :receipt_options, receipt_print_layout = :receipt_print_layout,
                notifications = :notifications, receipt_note = :receipt_note,
                migrated_students_enabled = :migrated_students_enabled, low_fees_notice_enabled = :low_fees_notice_enabled
            WHERE school_id = :school_id
        ");
    } else {
        $stmt_upd = $pdo->prepare("
            INSERT INTO fees_settings (
                fees_months, transport_fees_months, receipt_label, receipt_prefix,
                start_receipt_no, receipt_no_updated_at, font_header_school_name, font_header_details,
                font_receipt_title, font_other_details, logo_width, receipt_fields, qr_code_path,
                fine_enabled, fine_same_day, receipt_options, receipt_print_layout,
                notifications, receipt_note, migrated_students_enabled, low_fees_notice_enabled, school_id
            ) VALUES (
                :fees_months, :transport_fees_months, :receipt_label, :receipt_prefix,
                :start_receipt_no, :receipt_no_updated_at, :font_header_school_name, :font_header_details,
                :font_receipt_title, :font_other_details, :logo_width, :receipt_fields, :qr_code_path,
                :fine_enabled, :fine_same_day, :receipt_options, :receipt_print_layout,
                :notifications, :receipt_note, :migrated_students_enabled, :low_fees_notice_enabled, :school_id
            )
        ");
    }

    $stmt_upd->execute([
        ':fees_months' => $fees_months,
        ':transport_fees_months' => $transport_fees_months,
        ':receipt_label' => $receipt_label,
        ':receipt_prefix' => $receipt_prefix,
        ':start_receipt_no' => $start_receipt_no,
        ':receipt_no_updated_at' => $receipt_no_updated_at,
        ':font_header_school_name' => $font_header_school_name,
        ':font_header_details' => $font_header_details,
        ':font_receipt_title' => $font_receipt_title,
        ':font_other_details' => $font_other_details,
        ':logo_width' => $logo_width,
        ':receipt_fields' => $receipt_fields,
        ':qr_code_path' => $qr_code_path,
        ':fine_enabled' => $fine_enabled,
        ':fine_same_day' => $fine_same_day,
        ':receipt_options' => json_encode($receipt_options),
        ':receipt_print_layout' => $receipt_print_layout,
        ':notifications' => json_encode($notifications),
        ':receipt_note' => $receipt_note,
        ':migrated_students_enabled' => $migrated_students_enabled,
        ':low_fees_notice_enabled' => $low_fees_notice_enabled,
        ':school_id' => $school_id,
    ]);

    $_SESSION['flash_success'] = "Fees settings saved successfully.";
    header("Location: fees-settings.php");
    exit;
}

// ── GET: load existing settings (or defaults) ─────────────────────────
$stmt = $pdo->prepare("SELECT * FROM fees_settings WHERE school_id = :school_id");
$stmt->execute([':school_id' => $school_id]);
$settings = $stmt->fetch();

$selected_fees_months = $settings ? array_filter(explode(',', $settings['fees_months'] ?? '')) : array_keys($ALL_MONTHS);
$selected_transport_months = $settings ? array_filter(explode(',', $settings['transport_fees_months'] ?? '')) : array_keys($ALL_MONTHS);
$selected_receipt_fields = $settings ? array_filter(explode(',', $settings['receipt_fields'] ?? '')) : []; // empty = show all

$receipt_label = $settings['receipt_label'] ?? 'Fees Receipt';
$receipt_prefix = $settings['receipt_prefix'] ?? 'REC';
$start_receipt_no = $settings['start_receipt_no'] ?? '';
$receipt_no_updated_at = $settings['receipt_no_updated_at'] ?? null;

$font_header_school_name = $settings['font_header_school_name'] ?? 18;
$font_header_details = $settings['font_header_details'] ?? 12;
$font_receipt_title = $settings['font_receipt_title'] ?? 14;
$font_other_details = $settings['font_other_details'] ?? 12;
$logo_width = $settings['logo_width'] ?? 60;

$qr_code_path = $settings['qr_code_path'] ?? null;
$fine_enabled = $settings ? (int)$settings['fine_enabled'] : 1;
$fine_same_day = $settings ? (int)$settings['fine_same_day'] : 1;

$receipt_options = $settings && $settings['receipt_options'] ? json_decode($settings['receipt_options'], true) : [];
$receipt_print_layout = $settings['receipt_print_layout'] ?? '';

$notifications = $settings && $settings['notifications'] ? json_decode($settings['notifications'], true) : [];
$n = function ($key, $default = null) use ($notifications) {
    return $notifications[$key] ?? $default;
};

$receipt_note = $settings['receipt_note'] ?? '';
$migrated_students_enabled = $settings ? (int)$settings['migrated_students_enabled'] : 1;
$low_fees_notice_enabled = $settings ? (int)$settings['low_fees_notice_enabled'] : 0;

// Helper: is a header/behavior/print toggle checked? (defaults to checked=on for fresh installs, matching screenshot defaults)
function ro_checked($options, $key, $default = true)
{
    if (!array_key_exists($key, $options)) return $default;
    return (bool)$options[$key];
}

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- Fees Settings Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-8">
        <h2 class="mb-1 font-heading fw-extrabold text-dark">Fees Setting</h2>
        <p class="text-xs text-muted mb-0">Make the config changes.</p>
    </div>
    <div class="col-sm-4 text-sm-end">
        <button type="submit" form="feesSettingsForm" class="btn btn-primary font-heading fw-bold px-4" style="background-color: #0d6efd; border-color: #0d6efd; height: 38px; border-radius: 8px;">
            <i class="ph-bold ph-floppy-disk"></i> Save
        </button>
    </div>
</div>

<form action="fees-settings.php" method="POST" enctype="multipart/form-data" id="feesSettingsForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="save_fees_settings">
    <input type="hidden" name="remove_qr_code" id="remove_qr_code_flag" value="0">

    <div class="card-premium p-4 mb-4">

        <!-- Fees Months -->
        <div class="settings-row-head">
            <label class="form-label-admin fw-bold mb-1">Fees Months <i class="ph-light ph-info" title="Months this school collects regular fees for"></i></label>
            <span class="demand-select-all">
                <input type="checkbox" id="selectAllFeesMonths"> Select All?
            </span>
        </div>
        <div class="demand-pill-box mb-4" id="feesMonthsPillBox">
            <?php foreach ($ALL_MONTHS as $short => $full):
                $active = in_array($short, $selected_fees_months);
            ?>
                <div class="demand-pill <?php echo $active ? 'active' : ''; ?>" data-type="fees_month" data-value="<?php echo $short; ?>">
                    <span><?php echo $short; ?></span>
                    <i class="ph-bold ph-x demand-pill-close"></i>
                    <input type="checkbox" name="fees_months[]" value="<?php echo $short; ?>" <?php echo $active ? 'checked' : ''; ?> class="d-none">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Transport Fees Months -->
        <div class="settings-row-head">
            <label class="form-label-admin fw-bold mb-1">Transport Fees Months <i class="ph-light ph-info"></i></label>
            <span class="demand-select-all">
                <input type="checkbox" id="selectAllTransportMonths"> Select All?
            </span>
        </div>
        <div class="demand-pill-box mb-4" id="transportMonthsPillBox">
            <?php foreach ($ALL_MONTHS as $short => $full):
                $active = in_array($short, $selected_transport_months);
            ?>
                <div class="demand-pill <?php echo $active ? 'active' : ''; ?>" data-type="transport_month" data-value="<?php echo $short; ?>">
                    <span><?php echo $short; ?></span>
                    <i class="ph-bold ph-x demand-pill-close"></i>
                    <input type="checkbox" name="transport_fees_months[]" value="<?php echo $short; ?>" <?php echo $active ? 'checked' : ''; ?> class="d-none">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Receipt Label / Prefix / Start No -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label-admin fw-semibold mb-1">Fees Receipt Label</label>
                <input type="text" name="receipt_label" class="form-control-admin" value="<?php echo sanitize($receipt_label); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-admin fw-semibold mb-1">Fees Receipt Prefix</label>
                <input type="text" name="receipt_prefix" class="form-control-admin" value="<?php echo sanitize($receipt_prefix); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-admin fw-semibold mb-1">Start Receipt No. From</label>
                <input type="text" name="start_receipt_no" class="form-control-admin" value="<?php echo sanitize($start_receipt_no); ?>">
                <?php if ($receipt_no_updated_at): ?>
                    <div class="text-xxs text-muted mt-1">
                        <strong>Last updated on:</strong> <?php echo date('d M, Y g:ia', strtotime($receipt_no_updated_at)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Receipt Font Sizes -->
        <label class="form-label-admin fw-bold mb-2">Receipt Font Sizes (px)</label>
        <div class="row g-3 mb-1">
            <div class="col-md-3">
                <label class="form-label-admin mb-1">Header School Name</label>
                <input type="number" name="font_header_school_name" class="form-control-admin" value="<?php echo (int)$font_header_school_name; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label-admin mb-1">Header Details</label>
                <input type="number" name="font_header_details" class="form-control-admin" value="<?php echo (int)$font_header_details; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label-admin mb-1">Receipt Title</label>
                <input type="number" name="font_receipt_title" class="form-control-admin" value="<?php echo (int)$font_receipt_title; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label-admin mb-1">Other Details</label>
                <input type="number" name="font_other_details" class="form-control-admin" value="<?php echo (int)$font_other_details; ?>">
            </div>
        </div>
        <div class="text-xxs text-muted mb-4">Leave blank to use default size for each receipt layout.</div>

        <!-- Logo Width -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label-admin fw-bold mb-1">Logo Width (px)</label>
                <input type="number" name="logo_width" class="form-control-admin" value="<?php echo (int)$logo_width; ?>">
            </div>
        </div>

        <!-- Fields to show on Fee Receipt -->
        <div class="settings-row-head">
            <label class="form-label-admin fw-bold mb-1">Select the fields you want to show on <u>Fee Receipt</u></label>
            <span class="demand-select-all">
                <input type="checkbox" id="selectAllReceiptFields"> Select All?
            </span>
        </div>
        <div class="text-xxs text-muted mb-2">Make it blank to show everything on fee receipt (default).</div>
        <div class="demand-pill-box mb-4" id="receiptFieldsPillBox">
            <?php foreach ($RECEIPT_FIELD_OPTIONS as $field):
                $active = in_array($field, $selected_receipt_fields);
            ?>
                <div class="demand-pill <?php echo $active ? 'active' : ''; ?>" data-type="receipt_field" data-value="<?php echo sanitize($field); ?>">
                    <span><?php echo sanitize($field); ?></span>
                    <i class="ph-bold ph-x demand-pill-close"></i>
                    <input type="checkbox" name="receipt_fields[]" value="<?php echo sanitize($field); ?>" <?php echo $active ? 'checked' : ''; ?> class="d-none">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Payment QR Code -->
        <label class="form-label-admin fw-bold mb-2">Payment QR Code</label>
        <div class="mb-1">
            <label class="form-label-admin mb-1">Upload QR Code</label>
            <div class="d-flex align-items-center gap-3">
                <input type="file" name="qr_code" id="qr_code_input" class="form-control-admin" accept=".jpg,.jpeg,.png,.webp" style="max-width: 280px;">
                <div class="qr-upload-preview-box" id="qrPreviewBox" style="<?php echo $qr_code_path ? '' : 'display:none;'; ?>">
                    <img src="<?php echo $qr_code_path ? BASE_URL . sanitize($qr_code_path) : ''; ?>" id="qrPreviewImg" alt="QR Code">
                    <button type="button" class="qr-remove-btn" id="qrRemoveBtn" title="Remove QR Code"><i class="ph-bold ph-x"></i></button>
                </div>
            </div>
        </div>

        <hr class="my-4" style="color: var(--color-border);">

        <!-- Fine -->
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label-admin fw-bold mb-2">Fine</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fine_enabled" id="fine_enabled" value="1" <?php echo $fine_enabled ? 'checked' : ''; ?>>
                    <label class="form-check-label text-sm" for="fine_enabled">Enable Late Fees Fine</label>
                </div>
                <div class="text-xxs text-muted">Do you want to show & apply Late Fees Fine in fees modules?</div>
            </div>
            <div class="col-md-6">
                <label class="form-label-admin fw-bold mb-2">&nbsp;</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fine_same_day" id="fine_same_day" value="1" <?php echo $fine_same_day ? 'checked' : ''; ?>>
                    <label class="form-check-label text-sm" for="fine_same_day">Apply Same Day Fine</label>
                </div>
                <div class="text-xxs text-muted">Do you want to apply fine on the <u>Fees Submission Day</u> also?</div>
            </div>
        </div>
    </div>

    <hr class="my-4" style="color: var(--color-border);">

    <!-- Fees Receipt Options -->
    <h4 class="font-heading fw-extrabold text-dark mb-3">Fees Receipt Options</h4>
    <div class="card-premium p-4 mb-4">
        <div class="row g-4">
            <div class="col-lg-6">
                <label class="form-label-admin fw-bold mb-2">Header Details</label>
                <?php foreach ($HEADER_DETAIL_TOGGLES as $key => $label): ?>
                    <div class="settings-toggle-row">
                        <span class="text-sm"><?php echo $label; ?></span>
                        <input class="form-check-input m-0" type="checkbox" name="ro_<?php echo $key; ?>" value="1" <?php echo ro_checked($receipt_options, $key, !in_array($key, ['show_gst', 'print_qr_demand', 'print_qr_receipt'])) ? 'checked' : ''; ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-6">
                <label class="form-label-admin fw-bold mb-2">Behavior and Printing</label>
                <?php foreach ($BEHAVIOR_TOGGLES as $key => $label): ?>
                    <div class="settings-toggle-row">
                        <span class="text-sm"><?php echo $label; ?></span>
                        <input class="form-check-input m-0" type="checkbox" name="ro_<?php echo $key; ?>" value="1" <?php echo ro_checked($receipt_options, $key, $key !== 'hide_amount_in_words') ? 'checked' : ''; ?>>
                    </div>
                <?php endforeach; ?>

                <label class="form-label-admin fw-bold mb-2 mt-4">Receipt Print</label>
                <label class="form-label-admin mb-1">Receipt Print Layout</label>
                <select name="receipt_print_layout" class="form-control-admin mb-1">
                    <option value="">Select</option>
                    <?php foreach ($RECEIPT_PRINT_LAYOUTS as $layout): ?>
                        <option value="<?php echo sanitize($layout); ?>" <?php echo $receipt_print_layout === $layout ? 'selected' : ''; ?>><?php echo sanitize($layout); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="text-xxs text-muted mb-3">If selected, this layout will be used for fee receipt print/download and legacy toggles below are ignored for layout selection.</div>

                <?php foreach ($PRINT_TOGGLES as $key => $label): ?>
                    <div class="settings-toggle-row">
                        <span class="text-sm"><?php echo $label; ?></span>
                        <input class="form-check-input m-0" type="checkbox" name="ro_<?php echo $key; ?>" value="1" <?php echo ro_checked($receipt_options, $key, $key !== 'hide_received_by') ? 'checked' : ''; ?>>
                    </div>
                    <?php if (in_array($key, ['hide_received_by', 'admin_select_past_date'])): ?>
                        <a href="#" class="text-xxs d-inline-block mb-2">View sample</a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <hr class="my-4" style="color: var(--color-border);">

    <!-- Notifications -->
    <h4 class="font-heading fw-extrabold text-dark mb-3">Notifications</h4>
    <div class="card-premium p-4 mb-4">

        <div class="row g-4 mb-3">
            <!-- Auto SMS Notifications -->
            <div class="col-lg-6">
                <label class="form-label-admin fw-bold mb-2">Auto SMS Notifications</label>

                <div class="settings-toggle-row">
                    <span class="text-sm"><u>Fees Received</u> SMS to student/parents?</span>
                    <input class="form-check-input m-0" type="checkbox" id="sms_fees_received_enabled" name="sms_fees_received_enabled" value="1" <?php echo $n('sms_fees_received_enabled', true) ? 'checked' : ''; ?>>
                </div>
                <div class="settings-template-block" id="sms_fees_received_block" style="<?php echo $n('sms_fees_received_enabled', true) ? '' : 'display:none;'; ?>">
                    <label class="form-label-admin mb-1">Select Fees Received SMS/Notification Template</label>
                    <select name="sms_fees_received_template" class="form-control-admin template-select" data-preview="sms_fees_received_preview" data-templates="fee_received">
                        <?php foreach ($SMS_TEMPLATES as $tname => $ttext): ?>
                            <option value="<?php echo sanitize($tname); ?>" <?php echo $n('sms_fees_received_template', 'Online Paymnt Received - Parent') === $tname ? 'selected' : ''; ?>><?php echo sanitize($tname); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="template-preview-box" id="sms_fees_received_preview"><?php echo nl2br(sanitize($SMS_TEMPLATES[$n('sms_fees_received_template', 'Online Paymnt Received - Parent')] ?? reset($SMS_TEMPLATES))); ?></div>
                </div>

                <div class="settings-toggle-row mt-2">
                    <span class="text-sm"><u>Fees Received</u> SMS to student/parents paid through payment gateway?</span>
                    <input class="form-check-input m-0" type="checkbox" name="sms_fees_received_gateway_enabled" value="1" <?php echo $n('sms_fees_received_gateway_enabled', false) ? 'checked' : ''; ?>>
                </div>
                <div class="settings-toggle-row">
                    <span class="text-sm">Send <u>Defaulter</u> SMS to parents/students?</span>
                    <input class="form-check-input m-0" type="checkbox" name="sms_defaulter_enabled" value="1" <?php echo $n('sms_defaulter_enabled', false) ? 'checked' : ''; ?>>
                </div>
            </div>

            <!-- Auto App Notifications -->
            <div class="col-lg-6">
                <label class="form-label-admin fw-bold mb-2">Auto App Notifications</label>
                <div class="settings-toggle-row">
                    <span class="text-sm"><u>Fees Received</u> Notification to student/parents App?</span>
                    <input class="form-check-input m-0" type="checkbox" name="app_fees_received_enabled" value="1" <?php echo $n('app_fees_received_enabled', true) ? 'checked' : ''; ?>>
                </div>
                <div class="settings-toggle-row">
                    <span class="text-sm">Send <u>Fees Received</u> through payment gateway Notification to student/parents App?</span>
                    <input class="form-check-input m-0" type="checkbox" name="app_fees_received_gateway_enabled" value="1" <?php echo $n('app_fees_received_gateway_enabled', true) ? 'checked' : ''; ?>>
                </div>
                <div class="settings-toggle-row">
                    <span class="text-sm">Send <u>Defaulter</u> Notification to parents/students?</span>
                    <input class="form-check-input m-0" type="checkbox" name="app_defaulter_enabled" value="1" <?php echo $n('app_defaulter_enabled', true) ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>

        <hr style="color: var(--color-border);">

        <!-- For Admin -->
        <label class="form-label-admin fw-bold mb-2 mt-2">For Admin</label>

        <div class="settings-toggle-row">
            <span class="text-sm">Do you want to receive <u>Fees Collection</u> SMS?</span>
            <input class="form-check-input m-0" type="checkbox" id="admin_fees_collection_sms_enabled" name="admin_fees_collection_sms_enabled" value="1" <?php echo $n('admin_fees_collection_sms_enabled', true) ? 'checked' : ''; ?>>
        </div>
        <div class="settings-template-block" id="admin_fees_collection_block" style="<?php echo $n('admin_fees_collection_sms_enabled', true) ? '' : 'display:none;'; ?>">
            <label class="form-label-admin mb-1">Enter the mobile numbers <span class="text-muted fw-normal">(Separate by comma ,)</span></label>
            <div class="mobile-tag-box" data-hidden-input="admin_fees_collection_mobiles_hidden">
                <?php foreach (array_filter(explode(',', $n('admin_fees_collection_mobiles', ''))) as $num): ?>
                    <span class="mobile-tag"><?php echo sanitize($num); ?><i class="ph-bold ph-x mobile-tag-remove"></i></span>
                <?php endforeach; ?>
                <input type="text" class="mobile-tag-input-field" placeholder="Enter mobile no.">
            </div>
            <input type="hidden" name="admin_fees_collection_mobiles" id="admin_fees_collection_mobiles_hidden" value="<?php echo sanitize($n('admin_fees_collection_mobiles', '')); ?>">

            <label class="form-label-admin mb-1 mt-2">Select SMS Template</label>
            <select name="admin_fees_collection_template" class="form-control-admin template-select" data-preview="admin_fees_collection_preview">
                <?php foreach ($SMS_TEMPLATES as $tname => $ttext): ?>
                    <option value="<?php echo sanitize($tname); ?>" <?php echo $n('admin_fees_collection_template', 'Online Paymnt Received - Parent') === $tname ? 'selected' : ''; ?>><?php echo sanitize($tname); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="template-preview-box" id="admin_fees_collection_preview"><?php echo nl2br(sanitize($SMS_TEMPLATES[$n('admin_fees_collection_template', 'Online Paymnt Received - Parent')] ?? reset($SMS_TEMPLATES))); ?></div>
        </div>

        <div class="settings-toggle-row mt-2">
            <span class="text-sm">Do you want to receive <u>Fees Collection</u> SMS paid through payment gateway?</span>
            <input class="form-check-input m-0" type="checkbox" id="admin_fees_collection_gateway_sms_enabled" name="admin_fees_collection_gateway_sms_enabled" value="1" <?php echo $n('admin_fees_collection_gateway_sms_enabled', true) ? 'checked' : ''; ?>>
        </div>
        <div class="settings-template-block" id="admin_fees_collection_gateway_block" style="<?php echo $n('admin_fees_collection_gateway_sms_enabled', true) ? '' : 'display:none;'; ?>">
            <label class="form-label-admin mb-1">Enter the mobile numbers <span class="text-muted fw-normal">(Separate by comma ,)</span></label>
            <div class="mobile-tag-box" data-hidden-input="admin_fees_collection_gateway_mobiles_hidden">
                <?php foreach (array_filter(explode(',', $n('admin_fees_collection_gateway_mobiles', ''))) as $num): ?>
                    <span class="mobile-tag"><?php echo sanitize($num); ?><i class="ph-bold ph-x mobile-tag-remove"></i></span>
                <?php endforeach; ?>
                <input type="text" class="mobile-tag-input-field" placeholder="Enter mobile no.">
            </div>
            <input type="hidden" name="admin_fees_collection_gateway_mobiles" id="admin_fees_collection_gateway_mobiles_hidden" value="<?php echo sanitize($n('admin_fees_collection_gateway_mobiles', '')); ?>">

            <label class="form-label-admin mb-1 mt-2">Select SMS Template</label>
            <select name="admin_fees_collection_gateway_template" class="form-control-admin template-select" data-preview="admin_fees_collection_gateway_preview">
                <?php foreach ($SMS_TEMPLATES as $tname => $ttext): ?>
                    <option value="<?php echo sanitize($tname); ?>" <?php echo $n('admin_fees_collection_gateway_template', 'Fee Received Alert!') === $tname ? 'selected' : ''; ?>><?php echo sanitize($tname); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="template-preview-box" id="admin_fees_collection_gateway_preview"><?php echo nl2br(sanitize($SMS_TEMPLATES[$n('admin_fees_collection_gateway_template', 'Fee Received Alert!')] ?? 'Fee Received Alert!')); ?></div>
        </div>

        <div class="settings-toggle-row mt-2">
            <span class="text-sm">Do you want to receive <u>Daily Total Collection</u> SMS?</span>
            <input class="form-check-input m-0" type="checkbox" id="admin_daily_total_sms_enabled" name="admin_daily_total_sms_enabled" value="1" <?php echo $n('admin_daily_total_sms_enabled', true) ? 'checked' : ''; ?>>
        </div>
        <div class="settings-template-block" id="admin_daily_total_block" style="<?php echo $n('admin_daily_total_sms_enabled', true) ? '' : 'display:none;'; ?>">
            <label class="form-label-admin mb-1">Enter the mobile numbers <span class="text-muted fw-normal">(Separate by comma ,)</span></label>
            <div class="mobile-tag-box" data-hidden-input="admin_daily_total_mobiles_hidden">
                <?php foreach (array_filter(explode(',', $n('admin_daily_total_mobiles', ''))) as $num): ?>
                    <span class="mobile-tag"><?php echo sanitize($num); ?><i class="ph-bold ph-x mobile-tag-remove"></i></span>
                <?php endforeach; ?>
                <input type="text" class="mobile-tag-input-field" placeholder="Enter mobile no.">
            </div>
            <input type="hidden" name="admin_daily_total_mobiles" id="admin_daily_total_mobiles_hidden" value="<?php echo sanitize($n('admin_daily_total_mobiles', '')); ?>">

            <label class="form-label-admin mb-1 mt-2">What time do you want to receive the collection report?</label>
            <select name="admin_daily_total_time" class="form-control-admin mb-2" style="max-width: 220px;">
                <?php
                $time_opts = [];
                for ($h = 0; $h < 24; $h++) {
                    foreach ([0, 30] as $m) {
                        $t = date('g:i A', strtotime(sprintf('%02d:%02d', $h, $m)));
                        $time_opts[] = $t;
                    }
                }
                $selected_time = $n('admin_daily_total_time', '10:00 AM');
                foreach ($time_opts as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo $selected_time === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label-admin mb-1">Select SMS Template</label>
            <select name="admin_daily_total_template" class="form-control-admin template-select" data-preview="admin_daily_total_preview">
                <?php foreach ($SMS_TEMPLATES as $tname => $ttext): ?>
                    <option value="<?php echo sanitize($tname); ?>" <?php echo $n('admin_daily_total_template', 'Online Paymnt Received - Parent') === $tname ? 'selected' : ''; ?>><?php echo sanitize($tname); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="template-preview-box" id="admin_daily_total_preview"><?php echo nl2br(sanitize($SMS_TEMPLATES[$n('admin_daily_total_template', 'Online Paymnt Received - Parent')] ?? reset($SMS_TEMPLATES))); ?></div>
        </div>

        <hr style="color: var(--color-border);">

        <!-- WhatsApp Notification -->
        <label class="form-label-admin fw-bold mb-2 mt-2">WhatsApp Notification</label>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="settings-toggle-row">
                    <span class="text-sm">Do you want to send <u>Fees Received</u> WhatsApp to parents/students?</span>
                    <input class="form-check-input m-0" type="checkbox" name="wa_fees_received_enabled" value="1" <?php echo $n('wa_fees_received_enabled', true) ? 'checked' : ''; ?>>
                </div>
                <label class="form-label-admin mb-1">Select Fees Received WhatsApp Template</label>
                <select name="wa_fees_received_template" class="form-control-admin">
                    <option value="">Select template</option>
                    <!-- TODO: populate from WhatsApp [Meta API] template module once that feature is built -->
                </select>
            </div>
            <div class="col-lg-6">
                <div class="settings-toggle-row">
                    <span class="text-sm">Do you want to send <u>Fees Defaulter</u> WhatsApp to parents/students?</span>
                    <input class="form-check-input m-0" type="checkbox" name="wa_defaulter_enabled" value="1" <?php echo $n('wa_defaulter_enabled', true) ? 'checked' : ''; ?>>
                </div>
                <label class="form-label-admin mb-1">Select Fees Defaulter WhatsApp Template</label>
                <select name="wa_defaulter_template" class="form-control-admin">
                    <option value="">Select template</option>
                    <!-- TODO: populate from WhatsApp [Meta API] template module once that feature is built -->
                </select>
            </div>
        </div>
    </div>

    <!-- Note below fee receipt -->
    <label class="form-label-admin fw-bold mb-2">Write a note or instruction to show below the fees receipt.</label>
    <div class="demand-editor-container mb-4">
        <div class="demand-editor-toolbar">
            <select class="demand-editor-select" onchange="formatDoc('formatBlock', this.value); this.selectedIndex=0;">
                <option value="" selected disabled>Paragraph</option>
                <option value="H1">Heading 1</option>
                <option value="H2">Heading 2</option>
                <option value="H3">Heading 3</option>
                <option value="p">Paragraph</option>
            </select>
            <select class="demand-editor-select" onchange="formatDoc('fontSize', this.value); this.selectedIndex=0;">
                <option value="" selected disabled>14px</option>
                <option value="1">10px</option>
                <option value="2">12px</option>
                <option value="3">14px</option>
                <option value="4">16px</option>
                <option value="5">18px</option>
            </select>
            <div class="demand-editor-btn-group">
                <button type="button" class="demand-editor-btn fw-bold" onclick="formatDoc('bold')" title="Bold">B</button>
                <button type="button" class="demand-editor-btn fst-italic" onclick="formatDoc('italic')" title="Italic">I</button>
                <button type="button" class="demand-editor-btn text-decoration-underline" onclick="formatDoc('underline')" title="Underline">U</button>
                <button type="button" class="demand-editor-btn" onclick="formatDoc('strikeThrough')" title="Strikethrough">—</button>
            </div>
            <div class="demand-editor-btn-group">
                <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyLeft')" title="Align Left"><i class="ph-bold ph-align-left"></i></button>
                <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyCenter')" title="Align Center"><i class="ph-bold ph-align-center"></i></button>
                <button type="button" class="demand-editor-btn" onclick="formatDoc('justifyRight')" title="Align Right"><i class="ph-bold ph-align-right"></i></button>
            </div>
            <div class="demand-editor-btn-group">
                <button type="button" class="demand-editor-btn" onclick="formatDoc('insertUnorderedList')" title="Bullet List"><i class="ph-bold ph-list-bullets"></i></button>
                <button type="button" class="demand-editor-btn" onclick="formatDoc('createLink', prompt('Enter URL:'))" title="Insert Link"><i class="ph-bold ph-link"></i></button>
                <button type="button" class="demand-editor-btn" onclick="formatDoc('insertImage', prompt('Enter Image URL:'))" title="Insert Image"><i class="ph-bold ph-image"></i></button>
            </div>
        </div>
        <div class="demand-editor-textarea" contenteditable="true" id="receiptNoteEditor"><?php echo $receipt_note; ?></div>
        <input type="hidden" name="receipt_note" id="receiptNoteInput">
    </div>

    <!-- Migrated Students -->
    <label class="form-label-admin fw-bold mb-1">Fees structure updation or collection of <u>Migrated Students</u></label>
    <div class="text-xxs text-muted mb-2">If you Migrate/Promote the student to a New Session, you can move Due Fees along with migration. Enable this option so that the due fees could be collected from migrated session only and no one could not make any change to collected fees or fees structure of old session.</div>
    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="migrated_students_enabled" id="migrated_students_enabled" value="1" <?php echo $migrated_students_enabled ? 'checked' : ''; ?>>
        <label class="form-check-label text-sm" for="migrated_students_enabled">Do you want to disable fees collection &amp; structure updation of migrated students?</label>
    </div>

    <!-- Low Fees Payment Notice -->
    <label class="form-label-admin fw-bold mb-2">Low Fees Payment Notice</label>
    <div class="form-check mb-5">
        <input class="form-check-input" type="checkbox" name="low_fees_notice_enabled" id="low_fees_notice_enabled" value="1" <?php echo $low_fees_notice_enabled ? 'checked' : ''; ?>>
        <label class="form-check-label text-sm" for="low_fees_notice_enabled"><u>Enable low fees payment notice on dashboard</u></label>
    </div>

</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // ── Generic demand-pill multi-select toggle (Fees Months / Transport Months / Receipt Fields) ──
        function wirePillBox(boxId, selectAllId) {
            const box = document.getElementById(boxId);
            const selectAll = document.getElementById(selectAllId);
            if (!box) return;

            function syncSelectAllState() {
                if (!selectAll) return;
                const pills = box.querySelectorAll('.demand-pill');
                const activeCount = box.querySelectorAll('.demand-pill.active').length;
                selectAll.checked = pills.length > 0 && activeCount === pills.length;
            }

            box.querySelectorAll('.demand-pill').forEach(pill => {
                pill.addEventListener('click', function() {
                    this.classList.toggle('active');
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    if (checkbox) checkbox.checked = this.classList.contains('active');
                    syncSelectAllState();
                });
            });

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const isChecked = this.checked;
                    box.querySelectorAll('.demand-pill').forEach(pill => {
                        const checkbox = pill.querySelector('input[type="checkbox"]');
                        if (checkbox) checkbox.checked = isChecked;
                        pill.classList.toggle('active', isChecked);
                    });
                });
            }

            syncSelectAllState();
        }

        wirePillBox('feesMonthsPillBox', 'selectAllFeesMonths');
        wirePillBox('transportMonthsPillBox', 'selectAllTransportMonths');
        wirePillBox('receiptFieldsPillBox', 'selectAllReceiptFields');

        // ── Mobile number tag inputs (For Admin section) ──
        function wireMobileTagBox(box) {
            const hiddenInput = document.getElementById(box.dataset.hiddenInput);
            const textField = box.querySelector('.mobile-tag-input-field');

            function syncHidden() {
                const tags = Array.from(box.querySelectorAll('.mobile-tag')).map(t => t.firstChild.textContent.trim());
                if (hiddenInput) hiddenInput.value = tags.join(',');
            }

            function addTag(value) {
                value = value.trim().replace(/,$/, '');
                if (!value) return;
                const tag = document.createElement('span');
                tag.className = 'mobile-tag';
                tag.textContent = value;
                const removeIcon = document.createElement('i');
                removeIcon.className = 'ph-bold ph-x mobile-tag-remove';
                removeIcon.addEventListener('click', function() {
                    tag.remove();
                    syncHidden();
                });
                tag.appendChild(removeIcon);
                box.insertBefore(tag, textField);
                syncHidden();
            }

            box.querySelectorAll('.mobile-tag-remove').forEach(icon => {
                icon.addEventListener('click', function() {
                    icon.closest('.mobile-tag').remove();
                    syncHidden();
                });
            });

            if (textField) {
                textField.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ',') {
                        e.preventDefault();
                        addTag(textField.value);
                        textField.value = '';
                    }
                });
                textField.addEventListener('blur', function() {
                    if (textField.value.trim()) {
                        addTag(textField.value);
                        textField.value = '';
                    }
                });
            }

            box.addEventListener('click', function(e) {
                if (e.target === box) textField.focus();
            });

            syncHidden();
        }

        document.querySelectorAll('.mobile-tag-box').forEach(wireMobileTagBox);

        // ── Toggle visibility of template blocks based on parent checkbox ──
        function wireToggleBlock(checkboxId, blockId) {
            const cb = document.getElementById(checkboxId);
            const block = document.getElementById(blockId);
            if (!cb || !block) return;
            cb.addEventListener('change', function() {
                block.style.display = this.checked ? '' : 'none';
            });
        }
        wireToggleBlock('sms_fees_received_enabled', 'sms_fees_received_block');
        wireToggleBlock('admin_fees_collection_sms_enabled', 'admin_fees_collection_block');
        wireToggleBlock('admin_fees_collection_gateway_sms_enabled', 'admin_fees_collection_gateway_block');
        wireToggleBlock('admin_daily_total_sms_enabled', 'admin_daily_total_block');

        // ── SMS template preview swap ──
        const SMS_TEMPLATE_TEXT = <?php echo json_encode($SMS_TEMPLATES); ?>;
        document.querySelectorAll('.template-select').forEach(select => {
            select.addEventListener('change', function() {
                const previewEl = document.getElementById(this.dataset.preview);
                if (!previewEl) return;
                const text = SMS_TEMPLATE_TEXT[this.value] || '';
                previewEl.innerHTML = text.replace(/\n/g, '<br>');
            });
        });

        // ── QR Code upload preview / remove ──
        const qrInput = document.getElementById('qr_code_input');
        const qrPreviewBox = document.getElementById('qrPreviewBox');
        const qrPreviewImg = document.getElementById('qrPreviewImg');
        const qrRemoveBtn = document.getElementById('qrRemoveBtn');
        const qrRemoveFlag = document.getElementById('remove_qr_code_flag');

        if (qrInput) {
            qrInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        qrPreviewImg.src = e.target.result;
                        qrPreviewBox.style.display = '';
                        qrRemoveFlag.value = '0';
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        if (qrRemoveBtn) {
            qrRemoveBtn.addEventListener('click', function() {
                qrPreviewBox.style.display = 'none';
                qrPreviewImg.src = '';
                if (qrInput) qrInput.value = '';
                qrRemoveFlag.value = '1';
            });
        }

        // ── Rich text editor helper (shared pattern with Demand Bill modal) ──
        window.formatDoc = function(cmd, value = null) {
            document.execCommand(cmd, false, value);
        };

        // ── Form submit: sync contenteditable note + confirm via toast ──
        const form = document.getElementById('feesSettingsForm');
        if (form) {
            form.addEventListener('submit', function() {
                const editor = document.getElementById('receiptNoteEditor');
                const input = document.getElementById('receiptNoteInput');
                if (editor && input) input.value = editor.innerHTML;
            });
        }

        <?php if ($flash_success): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: <?php echo json_encode($flash_success); ?>,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>
        <?php if ($flash_error): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: <?php echo json_encode($flash_error); ?>,
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true
            });
        <?php endif; ?>
    });
</script>

<?php
require_once '../../../includes/footer.php';
?>
