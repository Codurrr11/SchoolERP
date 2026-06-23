<?php
require_once '../../config/helpers.php';
auth_check(['super_admin']);
require_once '../../config/db.php';

$id      = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = ($id > 0);

// ── Admin login fields ───────────────────────────────────────────────────────
$admin_first_name  = '';
$admin_last_name   = '';
$admin_email       = '';
$admin_exists      = false;

// ── Field defaults ────────────────────────────────────────────────────────────
$name             = '';
$slug             = '';
$email            = '';
$phone            = '';
$website          = '';
$address          = '';
$timezone         = 'Asia/Kolkata';
$status           = 'active';

$session_id         = 0;
$session_name       = '2026-27';
$session_start_date = '2026-04-01';
$session_end_date   = '2027-03-31';

$toast_type    = '';   // 'success' | 'error'
$toast_message = '';

// ── Prefill for edit mode ─────────────────────────────────────────────────────
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);
    $school = $stmt->fetch();
    if (!$school) { header('Location: schools.php'); exit; }

    $name     = $school['name'];
    $slug     = $school['slug'];
    $email    = $school['email'];
    $phone    = $school['phone'];
    $website  = $school['website'];
    $address  = $school['address'];
    $timezone = $school['timezone'];
    $status   = $school['status'];

    $au = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE school_id=:sid AND role_id=2 AND deleted_at IS NULL LIMIT 1");
    $au->execute([':sid' => $id]);
    $admin_user = $au->fetch();
    if ($admin_user) {
        $admin_exists     = true;
        $admin_first_name = $admin_user['first_name'];
        $admin_last_name  = $admin_user['last_name'];
        $admin_email      = $admin_user['email'];
    }

    // Load active academic session for edit mode
    $s_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id AND is_current = 1 LIMIT 1");
    $s_stmt->execute([':school_id' => $id]);
    $current_session = $s_stmt->fetch();
    if (!$current_session) {
        $s_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY start_date DESC LIMIT 1");
        $s_stmt->execute([':school_id' => $id]);
        $current_session = $s_stmt->fetch();
    }
    if ($current_session) {
        $session_id         = $current_session['id'];
        $session_name       = $current_session['name'];
        $session_start_date = $current_session['start_date'];
        $session_end_date   = $current_session['end_date'];
    }
}

// ── Form submission ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $toast_type    = 'error';
        $toast_message = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $name             = trim($_POST['name']             ?? '');
        $slug             = trim($_POST['slug']             ?? '');
        $email            = trim($_POST['email']            ?? '');
        $phone            = trim($_POST['phone']            ?? '');
        $website          = trim($_POST['website']          ?? '');
        $address          = trim($_POST['address']          ?? '');
        $timezone         = trim($_POST['timezone']         ?? 'Asia/Kolkata');
        $status           = trim($_POST['status']           ?? 'active');
        $admin_first_name = trim($_POST['admin_first_name'] ?? '');
        $admin_last_name  = trim($_POST['admin_last_name']  ?? '');
        $admin_email      = trim($_POST['admin_email']      ?? '');
        $admin_password   = $_POST['admin_password']        ?? '';
        $admin_confirm    = $_POST['admin_confirm_password'] ?? '';

        $session_id         = intval($_POST['session_id'] ?? 0);
        $session_name       = trim($_POST['session_name'] ?? '');
        $session_start_date = $_POST['session_start_date'] ?? '';
        $session_end_date   = $_POST['session_end_date'] ?? '';

        // Auto-generate slug
        if (empty($slug) && !empty($name)) {
            $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), '-');
        } else {
            $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)), '-');
        }

        // Re-check if admin exists (POST context — handles schools registered before this feature)
        if ($is_edit) {
            $au_check = $pdo->prepare("SELECT id FROM users WHERE school_id=:sid AND role_id=2 AND deleted_at IS NULL LIMIT 1");
            $au_check->execute([':sid' => $id]);
            $admin_exists = (bool) $au_check->fetch();
        }

        // ── Validation ────────────────────────────────────────────────────────
        $err = '';
        $need_admin_create = !$is_edit || ($is_edit && !$admin_exists);
        $password_provided = !empty($admin_password);

        if (empty($name) || empty($slug) || empty($email)) {
            $err = 'School Name, Slug and Contact Email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid school contact email.';
        } elseif ($need_admin_create && (empty($admin_first_name) || empty($admin_last_name) || empty($admin_email))) {
            $err = 'Admin first name, last name and login email are required.';
        } elseif ($need_admin_create && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid admin login email.';
        } elseif ($need_admin_create && strlen($admin_password) < 6) {
            $err = 'Admin password must be at least 6 characters.';
        } elseif ($need_admin_create && $admin_password !== $admin_confirm) {
            $err = 'Passwords do not match. Please re-enter.';
        } elseif (empty($session_name) || empty($session_start_date) || empty($session_end_date)) {
            $err = 'Session Name, Start Date and End Date are required.';
        } elseif (strtotime($session_end_date) <= strtotime($session_start_date)) {
            $err = 'Session End Date must be after the Start Date.';
        } elseif ($is_edit && $admin_exists && $password_provided) {
            // Optional password update on edit — validate only if filled
            if (strlen($admin_password) < 6) {
                $err = 'New password must be at least 6 characters.';
            } elseif ($admin_password !== $admin_confirm) {
                $err = 'Passwords do not match. Please re-enter.';
            }
        }

        if ($err) {
            $toast_type    = 'error';
            $toast_message = $err;
        } else {
            // Slug uniqueness
            $s = $pdo->prepare("SELECT id FROM schools WHERE slug=:slug AND deleted_at IS NULL" . ($is_edit ? " AND id!=:id" : ""));
            $s->execute($is_edit ? [':slug'=>$slug,':id'=>$id] : [':slug'=>$slug]);
            if ($s->fetch()) {
                $toast_type = 'error'; $toast_message = 'This URL slug is already in use.';
            } else {
                // School email uniqueness
                $s = $pdo->prepare("SELECT id FROM schools WHERE email=:email AND deleted_at IS NULL" . ($is_edit ? " AND id!=:id" : ""));
                $s->execute($is_edit ? [':email'=>$email,':id'=>$id] : [':email'=>$email]);
                if ($s->fetch()) {
                    $toast_type = 'error'; $toast_message = 'This school contact email is already registered.';
                } elseif ($need_admin_create) {
                    // Admin email uniqueness
                    $s = $pdo->prepare("SELECT id FROM users WHERE email=:email LIMIT 1");
                    $s->execute([':email' => $admin_email]);
                    if ($s->fetch()) {
                        $toast_type = 'error'; $toast_message = 'An account with this admin email already exists.';
                    }
                }

                if (empty($toast_message)) {
                    try {
                        $pdo->beginTransaction();
                        if ($is_edit) {
                            // Update school record
                            $pdo->prepare("UPDATE schools SET name=:name,slug=:slug,email=:email,phone=:phone,website=:website,address=:address,timezone=:timezone,status=:status WHERE id=:id")
                                ->execute([':name'=>$name,':slug'=>$slug,':email'=>$email,':phone'=>$phone,':website'=>$website,':address'=>$address,':timezone'=>$timezone,':status'=>$status,':id'=>$id]);

                            if ($admin_exists) {
                                // UPDATE existing admin user (name + email)
                                if (!empty($admin_first_name)) {
                                    $pdo->prepare("UPDATE users SET first_name=:fn,last_name=:ln,email=:email WHERE school_id=:sid AND role_id=2 AND deleted_at IS NULL")
                                        ->execute([':fn'=>$admin_first_name,':ln'=>$admin_last_name,':email'=>$admin_email,':sid'=>$id]);
                                }
                                // Also update password if provided
                                if ($password_provided) {
                                    $hashed = password_hash($admin_password, PASSWORD_DEFAULT);
                                    $pdo->prepare("UPDATE users SET password=:pwd WHERE school_id=:sid AND role_id=2 AND deleted_at IS NULL")
                                        ->execute([':pwd'=>$hashed,':sid'=>$id]);
                                }
                            } else {
                                // INSERT new admin user for this school (first time setup)
                                $pdo->prepare("INSERT INTO users (school_id,role_id,first_name,last_name,email,password,status) VALUES (:sid,2,:fn,:ln,:email,:pwd,'active')")
                                    ->execute([':sid'=>$id,':fn'=>$admin_first_name,':ln'=>$admin_last_name,':email'=>$admin_email,':pwd'=>password_hash($admin_password, PASSWORD_DEFAULT)]);
                            }

                            // UPDATE/INSERT academic session inside Edit School context
                            if ($session_id > 0) {
                                $pdo->prepare("UPDATE academic_sessions SET name = :sname, start_date = :sstart, end_date = :send WHERE id = :sid AND school_id = :school_id")
                                    ->execute([
                                        ':sname' => $session_name,
                                        ':sstart' => $session_start_date,
                                        ':send' => $session_end_date,
                                        ':sid' => $session_id,
                                        ':school_id' => $id
                                    ]);
                            } else {
                                $pdo->prepare("INSERT INTO academic_sessions (school_id, name, start_date, end_date, is_current) VALUES (:sid, :sname, :sstart, :send, 1)")
                                    ->execute([
                                        ':sid' => $id,
                                        ':sname' => $session_name,
                                        ':sstart' => $session_start_date,
                                        ':send' => $session_end_date
                                    ]);
                            }

                            $pdo->commit();
                            $toast_type    = 'success';
                            $toast_message = $password_provided
                                ? 'School details and admin password updated successfully!'
                                : 'School details updated successfully!';
                        } else {
                            $pdo->prepare("INSERT INTO schools (name,slug,email,phone,website,address,timezone,status) VALUES (:name,:slug,:email,:phone,:website,:address,:timezone,:status)")
                                ->execute([':name'=>$name,':slug'=>$slug,':email'=>$email,':phone'=>$phone,':website'=>$website,':address'=>$address,':timezone'=>$timezone,':status'=>$status]);
                            $new_id = $pdo->lastInsertId();
                            $pdo->prepare("INSERT INTO users (school_id,role_id,first_name,last_name,email,password,status) VALUES (:sid,2,:fn,:ln,:email,:pwd,'active')")
                                ->execute([':sid'=>$new_id,':fn'=>$admin_first_name,':ln'=>$admin_last_name,':email'=>$admin_email,':pwd'=>password_hash($admin_password, PASSWORD_DEFAULT)]);
                            
                            // Insert Initial Academic Session
                            $pdo->prepare("INSERT INTO academic_sessions (school_id, name, start_date, end_date, is_current) VALUES (:sid, :sname, :sstart, :send, 1)")
                                ->execute([
                                    ':sid' => $new_id,
                                    ':sname' => $session_name,
                                    ':sstart' => $session_start_date,
                                    ':send' => $session_end_date
                                ]);

                            $pdo->commit();
                            header('Location: schools.php?registered=1');
                            exit;
                        }
                    } catch (\PDOException $e) {
                        $pdo->rollBack();
                        $toast_type    = 'error';
                        $toast_message = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();
require_once '../../includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">
            <?php echo $is_edit ? 'Edit School Details' : 'Register New School'; ?>
        </h2>
        <p class="text-xs text-muted mb-0">
            <?php echo $is_edit
                ? 'Update school profile and administrator details.'
                : 'Fill in all fields to register a new school and create its admin login.'; ?>
        </p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <a href="schools.php" class="btn-admin-secondary">
            <i class="ti ti-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<!-- ─── Form ─────────────────────────────────────────────────────────────────── -->
<form id="schoolForm"
      action="schools-edit.php<?php echo $is_edit ? '?id='.$id : ''; ?>"
      method="POST"
      autocomplete="off"
      novalidate
      data-toast-type="<?php echo htmlspecialchars($toast_type); ?>"
      data-toast-message="<?php echo htmlspecialchars($toast_message); ?>"
      data-csrf-token="<?php echo $csrf_token; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

    <div class="row g-4 align-items-start">

        <!-- ─── Left Column (8/12) ──────────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- School Information Card -->
            <div class="card-premium mb-4">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-section-icon">
                            <i class="ti ti-building"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">School Information</h6>
                            <span class="text-xxs text-muted">Basic details about the institution</span>
                        </div>
                    </div>
                    <span class="form-required-note">* Required fields</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label-admin">School Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control-admin" required
                                   value="<?php echo sanitize($name); ?>"
                                   placeholder="e.g. Brighton School Kota">
                        </div>
                        <div class="col-md-6">
                            <label for="slug" class="form-label-admin">URL Slug
                                <span class="form-label-hint">auto-generated if empty</span>
                            </label>
                            <input type="text" id="slug" name="slug" class="form-control-admin"
                                   value="<?php echo sanitize($slug); ?>"
                                   placeholder="e.g. brighton-kota">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label-admin">Contact Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control-admin" required
                                   value="<?php echo sanitize($email); ?>"
                                   placeholder="e.g. info@brightonschool.com">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label-admin">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control-admin"
                                   value="<?php echo sanitize($phone); ?>"
                                   placeholder="e.g. +91 9876543210">
                        </div>
                        <div class="col-md-6">
                            <label for="website" class="form-label-admin">Website URL</label>
                            <input type="url" id="website" name="website" class="form-control-admin"
                                   value="<?php echo sanitize($website); ?>"
                                   placeholder="e.g. https://brightonschool.com">
                        </div>
                        <div class="col-md-6">
                            <label for="timezone" class="form-label-admin">Timezone</label>
                            <select id="timezone" name="timezone" class="form-select-admin">
                                <option value="Asia/Kolkata"     <?php echo $timezone==='Asia/Kolkata'     ?'selected':''; ?>>Asia/Kolkata (IST)</option>
                                <option value="UTC"              <?php echo $timezone==='UTC'              ?'selected':''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo $timezone==='America/New_York' ?'selected':''; ?>>America/New_York (EST)</option>
                                <option value="Europe/London"    <?php echo $timezone==='Europe/London'    ?'selected':''; ?>>Europe/London (GMT)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label-admin">Address</label>
                            <textarea id="address" name="address" class="form-control-admin" rows="2"
                                      placeholder="Full street address..."><?php echo sanitize($address); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Credentials Card -->
            <div class="card-premium">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-section-icon form-section-icon-indigo">
                            <i class="ph-light ph-user-gear"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">School Admin Login Credentials</h6>
                            <span class="text-xxs text-muted">
                                <?php
                                    if (!$is_edit) {
                                        echo 'Create login credentials for the school administrator.';
                                    } elseif (!$admin_exists) {
                                        echo '⚠️ No admin set up yet — create login credentials below.';
                                    } else {
                                        echo 'Update admin details. Leave password blank to keep it unchanged.';
                                    }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="admin_first_name" class="form-label-admin">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="admin_first_name" name="admin_first_name" class="form-control-admin"
                                   <?php echo !$is_edit ? 'required' : ''; ?>
                                   value="<?php echo sanitize($admin_first_name); ?>"
                                   placeholder="e.g. Ramesh">
                        </div>
                        <div class="col-md-6">
                            <label for="admin_last_name" class="form-label-admin">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="admin_last_name" name="admin_last_name" class="form-control-admin"
                                   <?php echo !$is_edit ? 'required' : ''; ?>
                                   value="<?php echo sanitize($admin_last_name); ?>"
                                   placeholder="e.g. Sharma">
                        </div>
                        <div class="col-12">
                            <label for="admin_email" class="form-label-admin">Login Email <span class="text-danger">*</span></label>
                            <input type="email" id="admin_email" name="admin_email" class="form-control-admin"
                                   <?php echo !$is_edit ? 'required' : ''; ?>
                                   value="<?php echo sanitize($admin_email); ?>"
                                   placeholder="e.g. admin@brightonschool.com">
                            <div class="form-field-hint">This email is used to log in to the school dashboard.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="admin_password" class="form-label-admin">
                                Password
                                <?php if (!$is_edit || !$admin_exists): ?>
                                    <span class="text-danger">*</span>
                                <?php else: ?>
                                    <span class="form-label-hint">optional — leave blank to keep current</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-password-wrapper">
                                <input type="password" id="admin_password" name="admin_password"
                                       class="form-control-admin"
                                       <?php echo (!$is_edit || !$admin_exists) ? 'required minlength="6"' : ''; ?>
                                       placeholder="<?php echo ($is_edit && $admin_exists) ? 'Leave blank to keep current password' : 'Minimum 6 characters'; ?>">
                                <button type="button" class="btn-toggle-password" data-target="admin_password" tabindex="-1">
                                    <i class="ti ti-eye" id="eye_admin_password"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="admin_confirm_password" class="form-label-admin">
                                Confirm Password
                                <?php if (!$is_edit || !$admin_exists): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-password-wrapper">
                                <input type="password" id="admin_confirm_password" name="admin_confirm_password"
                                       class="form-control-admin"
                                       <?php echo (!$is_edit || !$admin_exists) ? 'required minlength="6"' : ''; ?>
                                       placeholder="Re-enter password">
                                <button type="button" class="btn-toggle-password" data-target="admin_confirm_password" tabindex="-1">
                                    <i class="ti ti-eye" id="eye_admin_confirm_password"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="password-strength-bar" id="passwordStrengthBar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="form-field-hint" id="strengthLabel">Enter a password to check strength.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Session Card -->
            <div class="card-premium mt-4">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-section-icon form-section-icon-blue">
                            <i class="ti ti-calendar"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">
                                <?php echo $is_edit ? 'Active Academic Session' : 'Initial Academic Session'; ?>
                            </h6>
                            <span class="text-xxs text-muted">
                                <?php echo $is_edit ? 'Manage the active academic year for this school' : 'Initialize the first academic year for this school'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="session_name" class="form-label-admin">Session Name <span class="text-danger">*</span></label>
                            <input type="text" id="session_name" name="session_name" class="form-control-admin" required
                                   value="<?php echo sanitize($session_name); ?>"
                                   placeholder="e.g. 2026-27">
                            <div class="form-field-hint">Automatically calculated from start/end dates. You can also override it manually.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="session_start_date" class="form-label-admin">Start Date <span class="text-danger">*</span></label>
                            <input type="date" id="session_start_date" name="session_start_date" class="form-control-admin" required
                                   value="<?php echo sanitize($session_start_date); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="session_end_date" class="form-label-admin">End Date <span class="text-danger">*</span></label>
                            <input type="date" id="session_end_date" name="session_end_date" class="form-control-admin" required
                                   value="<?php echo sanitize($session_end_date); ?>">
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ─── Right Sidebar (4/12) ────────────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="form-sidebar">

                <!-- Status Card -->
                <div class="card-premium mb-4">
                    <div class="card-header">
                        <h6><i class="ph-light ph-toggle-right me-2"></i>School Status</h6>
                    </div>
                    <div class="card-body">
                        <label for="status" class="form-label-admin">Current Status</label>
                        <select id="status" name="status" class="form-select-admin">
                            <option value="active"    <?php echo $status==='active'    ?'selected':''; ?>>✅ Active</option>
                            <option value="inactive"  <?php echo $status==='inactive'  ?'selected':''; ?>>⏸ Inactive</option>
                            <option value="suspended" <?php echo $status==='suspended' ?'selected':''; ?>>🚫 Suspended</option>
                        </select>
                        <div class="form-field-hint mt-2">
                            Only <strong>Active</strong> schools can log in to the platform.
                        </div>
                    </div>
                </div>

                <!-- Action Card -->
                <div class="card-premium">
                    <div class="card-body">
                        <!-- Submit Button -->
                        <button type="submit" class="btn-admin-submit" id="submitBtn">
                            <i class="ph-bold ph-floppy-disk" id="submitIcon"></i>
                            <span id="submitLabel">
                                <?php echo $is_edit ? 'Save Changes' : 'Register School'; ?>
                            </span>
                        </button>

                        <!-- Cancel -->
                        <a href="schools.php" class="btn-admin-secondary w-100 mt-2 justify-content-center">
                            <i class="ph-light ph-x-circle"></i> Cancel
                        </a>

                        <?php if ($is_edit): ?>
                        <!-- Divider -->
                        <div class="form-sidebar-divider"></div>
                        <!-- Danger zone -->
                        <button type="button" class="btn-admin-danger w-100" id="deleteSchoolBtn"
                                data-name="<?php echo sanitize($name); ?>"
                                data-id="<?php echo $id; ?>">
                            <i class="ti ti-trash"></i> Delete School
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="form-info-card mt-4">
                    <div class="form-info-card-icon">
                        <i class="ph-light ph-info"></i>
                    </div>
                    <div>
                        <div class="form-info-card-title">School Login</div>
                        <div class="form-info-card-desc">
                            The admin can log in at <strong>/login</strong> using the email and password set here.
                        </div>
                    </div>
                </div>

            </div>
        </div><!-- /col-lg-4 -->

    </div><!-- /row -->
</form>


<?php require_once '../../includes/footer.php'; ?>

