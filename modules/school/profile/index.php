<?php
// modules/school/profile/index.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$user_id = intval($_SESSION['user_id']);

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND school_id = :school_id");
$stmt->execute([':id' => $user_id, ':school_id' => $school_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash_error'] = "User profile not found.";
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// CSRF check and POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: index.php');
        exit;
    }

    if ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_name = $_FILES['avatar']['name'];
            $file_size = $_FILES['avatar']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                $_SESSION['flash_error'] = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
                header('Location: index.php');
                exit;
            }

            if ($file_size > 2 * 1024 * 1024) {
                $_SESSION['flash_error'] = "File size exceeds limit of 2MB.";
                header('Location: index.php');
                exit;
            }

            $upload_dir = ROOT_PATH . 'uploads/profile/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                // Delete old avatar
                if (!empty($user['avatar'])) {
                    $old_file = $upload_dir . $user['avatar'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }

                $stmt_up = $pdo->prepare("UPDATE users SET avatar = :avatar WHERE id = :id AND school_id = :school_id");
                $stmt_up->execute([':avatar' => $new_filename, ':id' => $user_id, ':school_id' => $school_id]);

                $_SESSION['avatar'] = $new_filename;
                $_SESSION['flash_success'] = "Profile picture updated successfully!";
            } else {
                $_SESSION['flash_error'] = "Failed to save uploaded file.";
            }
        } else {
            $_SESSION['flash_error'] = "Please choose a valid file to upload.";
        }
        header('Location: index.php');
        exit;
    }

    if ($action === 'update_about') {
        $gender = trim($_POST['gender'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (!in_array($gender, ['male', 'female', 'other'])) {
            $_SESSION['flash_error'] = "Invalid gender selected.";
            header('Location: index.php');
            exit;
        }

        $stmt_up = $pdo->prepare("UPDATE users SET gender = :gender, bio = :bio WHERE id = :id AND school_id = :school_id");
        $stmt_up->execute([
            ':gender' => $gender,
            ':bio' => $bio,
            ':id' => $user_id,
            ':school_id' => $school_id
        ]);

        $_SESSION['flash_success'] = "About details updated successfully!";
        header('Location: index.php');
        exit;
    }

    if ($action === 'update_contact') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $alternate_phone = trim($_POST['alternate_phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $_SESSION['flash_error'] = "First Name, Last Name, and Email are required fields.";
            header('Location: index.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Invalid email format.";
            header('Location: index.php');
            exit;
        }

        // Email unique check
        if ($email !== $user['email']) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
            $stmt_check->execute([':email' => $email, ':id' => $user_id]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "The email address is already in use by another account.";
                header('Location: index.php');
                exit;
            }
        }

        $stmt_up = $pdo->prepare("
            UPDATE users
            SET    first_name = :first_name,
                   last_name = :last_name,
                   phone = :phone,
                   alternate_phone = :alternate_phone,
                   email = :email,
                   website = :website,
                   pincode = :pincode,
                   city = :city,
                   state = :state,
                   country = :country,
                   address = :address
            WHERE  id = :id AND school_id = :school_id
        ");
        $stmt_up->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':phone' => $phone,
            ':alternate_phone' => $alternate_phone,
            ':email' => $email,
            ':website' => $website,
            ':pincode' => $pincode,
            ':city' => $city,
            ':state' => $state,
            ':country' => $country,
            ':address' => $address,
            ':id' => $user_id,
            ':school_id' => $school_id
        ]);

        // Sync session data
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['email'] = $email;

        $_SESSION['flash_success'] = "Contact details updated successfully!";
        header('Location: index.php');
        exit;
    }

    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['flash_error'] = "All password fields are required.";
            header('Location: index.php');
            exit;
        }

        // Fetch user from DB to verify current password
        $stmt_pw = $pdo->prepare("SELECT password FROM users WHERE id = :id AND school_id = :school_id");
        $stmt_pw->execute([':id' => $user_id, ':school_id' => $school_id]);
        $pw_hash = $stmt_pw->fetchColumn();

        if (!$pw_hash || !password_verify($current_password, $pw_hash)) {
            $_SESSION['flash_error'] = "Incorrect current password.";
            header('Location: index.php');
            exit;
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['flash_error'] = "New password and confirmation password do not match.";
            header('Location: index.php');
            exit;
        }

        if (strlen($new_password) < 6) {
            $_SESSION['flash_error'] = "New password must be at least 6 characters long.";
            header('Location: index.php');
            exit;
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt_up = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id AND school_id = :school_id");
        $stmt_up->execute([
            ':password' => $new_hash,
            ':id' => $user_id,
            ':school_id' => $school_id
        ]);

        $_SESSION['flash_success'] = "Password updated successfully!";
        header('Location: index.php');
        exit;
    }

    if ($action === 'delete_account') {
        try {
            $pdo->beginTransaction();
            // Delete user
            $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = :id AND school_id = :school_id");
            $stmt_del->execute([':id' => $user_id, ':school_id' => $school_id]);

            $pdo->commit();

            // Clear session and redirect to login
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();

            header('Location: ' . BASE_URL . 'login.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to delete account: " . $e->getMessage();
            header('Location: index.php');
            exit;
        }
    }
}

// Generate token & handle flash
$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- Page Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-12">
        <h2 class="mb-1 font-heading fw-extrabold">My Profile</h2>
        <p class="text-xs text-muted mb-0">View, update, and manage your account profile and contact details.</p>
    </div>
</div>

<!-- Metadata div for Javascript flash alerts -->
<div id="profile-page-data"
    data-flash-success="<?php echo sanitize($flash_success); ?>"
    data-flash-error="<?php echo sanitize($flash_error); ?>">
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-panel p-0" style="overflow: hidden;">
            <div class="row g-0">
                <!-- Left Nav Tabs -->
                <div class="col-md-3 border-end">
                    <div class="nav flex-column nav-pills profile-tabs-nav py-2" id="profileTabs" role="tablist" aria-orientation="vertical">
                        <button class="nav-link active" id="tab-avatar-btn" data-bs-toggle="pill" data-bs-target="#tab-avatar" type="button" role="tab" aria-controls="tab-avatar" aria-selected="true">
                            <i class="ph-light ph-image me-2 fs-6 align-middle"></i> Profile Picture
                        </button>
                        <button class="nav-link" id="tab-about-btn" data-bs-toggle="pill" data-bs-target="#tab-about" type="button" role="tab" aria-controls="tab-about" aria-selected="false">
                            <i class="ph-light ph-user me-2 fs-6 align-middle"></i> About
                        </button>
                        <button class="nav-link" id="tab-contact-btn" data-bs-toggle="pill" data-bs-target="#tab-contact" type="button" role="tab" aria-controls="tab-contact" aria-selected="false">
                            <i class="ph-light ph-address-book me-2 fs-6 align-middle"></i> Contact Details
                        </button>
                        <button class="nav-link" id="tab-password-btn" data-bs-toggle="pill" data-bs-target="#tab-password" type="button" role="tab" aria-controls="tab-password" aria-selected="false">
                            <i class="ph-light ph-lock me-2 fs-6 align-middle"></i> Update Password
                        </button>
                        <button class="nav-link" id="tab-support-btn" data-bs-toggle="pill" data-bs-target="#tab-support" type="button" role="tab" aria-controls="tab-support" aria-selected="false">
                            <i class="ph-light ph-chat-centered-text me-2 fs-6 align-middle"></i> Support Section
                        </button>
                        <button class="nav-link text-danger" id="tab-delete-btn" data-bs-toggle="pill" data-bs-target="#tab-delete" type="button" role="tab" aria-controls="tab-delete" aria-selected="false">
                            <i class="ph-light ph-trash me-2 fs-6 align-middle"></i> Delete Account
                        </button>
                    </div>
                </div>

                <!-- Right Form Content -->
                <div class="col-md-9 profile-form-content">
                    <div class="card-body p-4 p-lg-5">
                        <div class="tab-content" id="profileTabsContent">
                            <!-- 1. Profile Picture -->
                            <div class="tab-pane fade show active" id="tab-avatar" role="tabpanel" aria-labelledby="tab-avatar-btn">
                                <h5 class="fw-bold mb-1 font-heading">Profile Picture</h5>
                                <p class="text-xs text-muted mb-4">Upload a high-quality photo to update your dashboard avatar display.</p>

                                <form action="index.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_avatar">

                                    <div class="d-flex flex-column flex-sm-row align-items-center gap-4 py-3">
                                        <div class="avatar-preview-wrapper position-relative">
                                            <?php
                                            $avatar_url = "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=300";
                                            if (!empty($user['avatar'])) {
                                                $path = "../../../uploads/profile/" . $user['avatar'];
                                                if (file_exists($path)) {
                                                    $avatar_url = BASE_URL . "uploads/profile/" . $user['avatar'];
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo $avatar_url; ?>" id="avatar_preview_img" class="rounded-circle border border-3 border-primary shadow-sm" style="width: 130px; height: 130px; object-fit: cover;" alt="Avatar Preview">
                                        </div>
                                        <div class="flex-grow-1 text-center text-sm-start">
                                            <input type="file" name="avatar" id="avatar_file_input" accept="image/*" class="d-none">
                                            <button type="button" class="btn btn-outline-primary btn-sm px-4 fw-semibold" id="btn-select-pic">
                                                <i class="ph-bold ph-upload-simple me-2"></i> Select pic
                                            </button>
                                            <div class="text-xxs text-muted mt-2">
                                                Allowed formats: JPG, JPEG, PNG, GIF, WEBP.<br>
                                                Maximum file size: 2 Megabytes (2 MB).
                                            </div>
                                        </div>
                                    </div>
                                    <hr class="my-4">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                                    </div>
                                </form>
                            </div>

                            <!-- 2. About -->
                            <div class="tab-pane fade" id="tab-about" role="tabpanel" aria-labelledby="tab-about-btn">
                                <h5 class="fw-bold mb-1 font-heading">About</h5>
                                <p class="text-xs text-muted mb-4">Set your demographic preferences and write a short biography introduction.</p>

                                <form action="index.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_about">

                                    <div class="mb-4">
                                        <label class="form-label-admin d-block mb-3">Gender Selection</label>
                                        <div class="d-flex align-items-center gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male" <?php echo ($user['gender'] === 'male') ? 'checked' : ''; ?>>
                                                <label class="form-check-label text-xs fw-semibold" for="gender_male">Male</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female" <?php echo ($user['gender'] === 'female') ? 'checked' : ''; ?>>
                                                <label class="form-check-label text-xs fw-semibold" for="gender_female">Female</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="gender" id="gender_other" value="other" <?php echo ($user['gender'] === 'other') ? 'checked' : ''; ?>>
                                                <label class="form-check-label text-xs fw-semibold" for="gender_other">Other</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="bio" class="form-label-admin">Biographical Statement</label>
                                        <textarea id="bio" name="bio" class="form-control-admin" rows="5" placeholder="Write something about yourself..."><?php echo sanitize($user['bio'] ?? ''); ?></textarea>
                                    </div>

                                    <hr class="my-4">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                                    </div>
                                </form>
                            </div>

                            <!-- 3. Contact Details -->
                            <div class="tab-pane fade" id="tab-contact" role="tabpanel" aria-labelledby="tab-contact-btn">
                                <h5 class="fw-bold mb-1 font-heading">Contact Details</h5>
                                <p class="text-xs text-muted mb-4">Manage your administrative name, contact phone numbers, online link, and office location address.</p>

                                <form action="index.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_contact">

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label-admin">First Name <span class="text-danger">*</span></label>
                                            <input type="text" id="first_name" name="first_name" class="form-control-admin" required value="<?php echo sanitize($user['first_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label-admin">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" id="last_name" name="last_name" class="form-control-admin" required value="<?php echo sanitize($user['last_name'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label-admin">Primary Phone</label>
                                            <input type="text" id="phone" name="phone" class="form-control-admin" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="alternate_phone" class="form-label-admin">Alternate Phone</label>
                                            <input type="text" id="alternate_phone" name="alternate_phone" class="form-control-admin" value="<?php echo sanitize($user['alternate_phone'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="email" class="form-label-admin">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" id="email" name="email" class="form-control-admin" required value="<?php echo sanitize($user['email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="website" class="form-label-admin">Website Link</label>
                                            <input type="url" id="website" name="website" class="form-control-admin" placeholder="https://example.com" value="<?php echo sanitize($user['website'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label for="pincode" class="form-label-admin">Pincode</label>
                                            <input type="text" id="pincode" name="pincode" class="form-control-admin" value="<?php echo sanitize($user['pincode'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="city" class="form-label-admin">City</label>
                                            <input type="text" id="city" name="city" class="form-control-admin" value="<?php echo sanitize($user['city'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="state" class="form-label-admin">State</label>
                                            <input type="text" id="state" name="state" class="form-control-admin" value="<?php echo sanitize($user['state'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label for="country" class="form-label-admin">Country</label>
                                            <input type="text" id="country" name="country" class="form-control-admin" value="<?php echo sanitize($user['country'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-8">
                                            <label for="address" class="form-label-admin">Location Address</label>
                                            <input type="text" id="address" name="address" class="form-control-admin" value="<?php echo sanitize($user['address'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <hr class="my-4">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                                    </div>
                                </form>
                            </div>

                            <!-- 4. Update Password -->
                            <div class="tab-pane fade" id="tab-password" role="tabpanel" aria-labelledby="tab-password-btn">
                                <h5 class="fw-bold mb-1 font-heading">Update Password</h5>
                                <p class="text-xs text-muted mb-4">Set a new secure password for your administrator profile credentials.</p>

                                <form action="index.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_password">

                                    <div class="mb-3">
                                        <label for="current_password" class="form-label-admin">Current Password <span class="text-danger">*</span></label>
                                        <input type="password" id="current_password" name="current_password" class="form-control-admin" required placeholder="••••••••">
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label-admin">New Password <span class="text-danger">*</span></label>
                                            <input type="password" id="new_password" name="new_password" class="form-control-admin" required placeholder="••••••••">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label-admin">Confirm New Password <span class="text-danger">*</span></label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control-admin" required placeholder="••••••••">
                                        </div>
                                    </div>
                                    <hr class="my-4">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary px-4">Update Password</button>
                                    </div>
                                </form>
                            </div>

                            <!-- 5. Support Section -->
                            <div class="tab-pane fade" id="tab-support" role="tabpanel" aria-labelledby="tab-support-btn">
                                <h5 class="fw-bold mb-1 font-heading">Support Section</h5>
                                <p class="text-xs text-muted mb-4">Get in touch with our Sales & Support representatives for system help and billing assistance.</p>

                                <div class="row g-3">
                                    <!-- Sales Helpline -->
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 support-info-box d-flex align-items-center gap-3">
                                            <div class="icon-circle-md bg-primary-light text-primary flex-shrink-0">
                                                <i class="ph-bold ph-phone fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="text-xxs text-muted d-block uppercase fw-bold" style="letter-spacing: 0.5px;">Sales & Billing</span>
                                                <a href="tel:+919999988888" class="fw-semibold text-sm text-decoration-none hover-primary">+91 99999 88888</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Support Helpline -->
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 support-info-box d-flex align-items-center gap-3">
                                            <div class="icon-circle-md bg-success-light text-success flex-shrink-0">
                                                <i class="ph-bold ph-phone fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="text-xxs text-muted d-block uppercase fw-bold" style="letter-spacing: 0.5px;">Support Helpline</span>
                                                <a href="tel:+918888877777" class="fw-semibold text-sm text-decoration-none hover-primary">+91 88888 77777</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WhatsApp Sales -->
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 support-info-box d-flex align-items-center gap-3">
                                            <div class="icon-circle-md bg-success-light text-success flex-shrink-0">
                                                <i class="ph-bold ph-whatsapp-logo fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="text-xxs text-muted d-block uppercase fw-bold" style="letter-spacing: 0.5px;">Chat with Sales</span>
                                                <a href="https://wa.me/919999988888" target="_blank" rel="noopener noreferrer" class="fw-semibold text-sm text-decoration-none hover-primary">Open Chat (Sales)</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WhatsApp Support -->
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 support-info-box d-flex align-items-center gap-3">
                                            <div class="icon-circle-md bg-success-light text-success flex-shrink-0">
                                                <i class="ph-bold ph-whatsapp-logo fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="text-xxs text-muted d-block uppercase fw-bold" style="letter-spacing: 0.5px;">Chat with Support</span>
                                                <a href="https://wa.me/918888877777" target="_blank" rel="noopener noreferrer" class="fw-semibold text-sm text-decoration-none hover-primary">Open Chat (Support)</a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Support Email -->
                                    <div class="col-md-12">
                                        <div class="p-3 border rounded-3 support-info-box d-flex align-items-center gap-3">
                                            <div class="icon-circle-md bg-indigo-light text-indigo flex-shrink-0">
                                                <i class="ph-bold ph-envelope fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="text-xxs text-muted d-block uppercase fw-bold" style="letter-spacing: 0.5px;">Support Email Desk</span>
                                                <a href="mailto:support@schoolsaas.com" class="fw-semibold text-sm text-decoration-none hover-primary">support@schoolsaas.com</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Delete Account -->
                            <div class="tab-pane fade" id="tab-delete" role="tabpanel" aria-labelledby="tab-delete-btn">
                                <h5 class="fw-bold mb-1 font-heading text-danger">Delete Account</h5>
                                <p class="text-xs text-muted mb-4">Permanently erase your administrator settings and account database information.</p>

                                <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-3 p-3 mb-4">
                                    <i class="ph-bold ph-warning-circle fs-4 text-danger"></i>
                                    <div>
                                        <strong class="d-block mb-1" style="font-size: 13px;">Danger Zone</strong>
                                        <span class="text-xs text-danger">Permanently delete your account. This action is irreversible.</span>
                                    </div>
                                </div>

                                <p class="text-xs text-muted">
                                    To delete your live SaaS tenant instance, please submit a formal request via the Support Email Desk or contact the admin panel billing support line directly.
                                </p>

                                <hr class="my-4">
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-danger px-4" id="btn-delete-account">Delete My Account</button>
                                </div>
                                <form id="deleteAccountForm" action="index.php" method="POST" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="delete_account">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../../../includes/footer.php';
?>
