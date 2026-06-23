<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

// Check if a Super Admin (role_id = 1) already exists in the database
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = 1");
$row = $stmt->fetch();
$admin_exists = ($row['count'] > 0);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$admin_exists) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if the email is already in use (even though super admin should be unique, users table might have duplicate emails for other roles later)
            // For super admin school_id is NULL
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND school_id IS NULL");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered as a platform admin.';
            } else {
                // Insert Super Admin
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                try {
                    $insert_stmt = $pdo->prepare("INSERT INTO users (role_id, school_id, first_name, last_name, email, phone, password, status) VALUES (1, NULL, :first_name, :last_name, :email, :phone, :password, 'active')");
                    $insert_stmt->execute([
                        ':first_name' => $first_name,
                        ':last_name'  => $last_name,
                        ':email'      => $email,
                        ':phone'      => $phone,
                        ':password'   => $hashed_password
                    ]);

                    header('Location: login.php?registered=1');
                    exit;
                } catch (\PDOException $e) {
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Registration - SchoolSaaS</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Metrophobic&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Phosphor Icons CDN -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/light/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css" />

    <!-- Base & Responsive Stylesheets -->
    <link href="assets/css/main.css?v=1.7" rel="stylesheet">
    <link href="assets/css/responsive.css" rel="stylesheet">
</head>

<body>
    <div class="auth-container">
        <!-- Left: Form Section -->
        <div class="auth-form-section">
            <div class="auth-form-wrapper">
                <div class="auth-logo-icon d-lg-none">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="24" height="24" rx="6" fill="#FFFFFF" />
                        <path d="M6 18L18 6" stroke="var(--color-accent)" stroke-width="4" stroke-linecap="round" />
                        <path d="M11 18L18 11" stroke="var(--color-accent)" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </div>

                <h3 class="auth-title">Super Admin Setup</h3>
                <p class="auth-subtitle mb-4">Initialize the SchoolSaaS platform administrator account</p>

                <?php if ($admin_exists): ?>
                    <div class="alert-custom alert-custom-info">
                        <i class="ph-fill ph-info"></i>
                        A platform super admin has already been registered. For security reasons, additional public super admin registrations are disabled.
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn-auth-submit d-flex align-items-center justify-content-center text-decoration-none">
                            Go to Login
                        </a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert-custom alert-custom-danger">
                            <i class="ph-fill ph-warning-circle"></i> <?php echo sanitize($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="register.php" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <div class="row">
                            <div class="col-md-6 form-group-custom">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-control-custom" required value="<?php echo isset($_POST['first_name']) ? sanitize($_POST['first_name']) : ''; ?>">
                            </div>

                            <div class="col-md-6 form-group-custom">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-control-custom" required value="<?php echo isset($_POST['last_name']) ? sanitize($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group-custom">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control-custom" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                        </div>

                        <div class="form-group-custom">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control-custom" value="<?php echo isset($_POST['phone']) ? sanitize($_POST['phone']) : ''; ?>">
                        </div>

                        <div class="form-group-custom">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" class="form-control-custom" required minlength="6">
                        </div>

                        <div class="form-group-custom">
                            <label for="password_confirm">Confirm Password *</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control-custom" required minlength="6">
                        </div>

                        <button type="submit" class="btn-auth-submit mt-3">Create Admin Account</button>
                    </form>

                    <div class="auth-footer mt-4">
                        Already have an account? <a href="login.php" class="auth-link">Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Info Panel Section -->
        <div class="auth-info-section">
            <div class="auth-info-bg-pattern"></div>
            <div class="auth-info-header">
                <div class="info-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="24" height="24" rx="6" fill="#111827" />
                        <path d="M6 18L18 6" stroke="var(--color-accent)" stroke-width="4" stroke-linecap="round" />
                        <path d="M11 18L18 11" stroke="var(--color-accent)" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <span>SchoolSaaS</span>
                </div>
            </div>

            <div class="auth-info-body">
                <h2 class="auth-info-title">Simplifying School Administration and Operations.</h2>
                <p class="auth-info-text">Unlock a comprehensive suite of management tools tailored for administrators, teachers, parents, and students.</p>

                <div class="auth-feature-list">
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">
                            <i class="ph-light ph-shield-check fs-5"></i>
                        </div>
                        <div>
                            <div class="auth-feature-title">Secure & Multi-Tenant</div>
                            <div class="auth-feature-desc">Dedicated isolation guarantees your school's data security.</div>
                        </div>
                    </div>

                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">
                            <i class="ph-light ph-users fs-5"></i>
                        </div>
                        <div>
                            <div class="auth-feature-title">Role-Based Gated Access</div>
                            <div class="auth-feature-desc">One portal, customized experiences for all system roles.</div>
                        </div>
                    </div>

                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">
                            <i class="ph-light ph-chart-bar fs-5"></i>
                        </div>
                        <div>
                            <div class="auth-feature-title">Analytics & Reports</div>
                            <div class="auth-feature-desc">Real-time statistics for finance, performance, and attendance.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-info-footer">
                &copy; 2026 SchoolSaaS Platform. Powered by Finexy Design System.
            </div>
        </div>
    </div>
</body>

</html>
