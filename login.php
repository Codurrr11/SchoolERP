<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = 'Super Admin account created successfully! You can now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE (u.email = :login_email OR u.username = :login_username) AND u.status = 'active'");
            $stmt->execute([':login_email' => $email, ':login_username' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session data
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name']  = $user['last_name'];
                $_SESSION['email']      = $user['email'];
                $_SESSION['role_id']    = $user['role_id'];
                $_SESSION['role_name']  = $user['role_name'];
                $_SESSION['school_id']  = $user['school_id'];

                // Fetch school name for school-level roles
                $_SESSION['school_name'] = null;
                if (!empty($user['school_id'])) {
                    $sn = $pdo->prepare("SELECT name FROM schools WHERE id = :id AND deleted_at IS NULL LIMIT 1");
                    $sn->execute([':id' => $user['school_id']]);
                    $school_row = $sn->fetch();
                    $_SESSION['school_name'] = $school_row ? $school_row['name'] : null;
                }

                // Update last login timestamp
                $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id")
                    ->execute([':id' => $user['id']]);

                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
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
    <title>Login - SchoolSaaS</title>
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
    <link href="assets/css/main.css?v=2.0" rel="stylesheet">
    <link href="assets/css/responsive.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <!-- Left: Form Section -->
        <div class="auth-form-section">
            <div class="auth-form-wrapper">
                <div class="auth-logo-icon d-lg-none">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="24" height="24" rx="6" fill="#FFFFFF"/>
                        <path d="M6 18L18 6" stroke="var(--color-accent)" stroke-width="4" stroke-linecap="round"/>
                        <path d="M11 18L18 11" stroke="var(--color-accent)" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </div>
                
                <h3 class="auth-title">Welcome Back</h3>
                <p class="auth-subtitle mb-4">Login to access your SchoolSaaS dashboard</p>
                
                <?php if (!empty($success)): ?>
                    <div class="alert-custom alert-custom-success">
                        <i class="ph-fill ph-check-circle"></i> <?php echo sanitize($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert-custom alert-custom-danger">
                        <i class="ph-fill ph-warning-circle"></i> <?php echo sanitize($error); ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group-custom">
                        <label for="email">Email or Username</label>
                        <input type="text" id="email" name="email" class="form-control-custom" required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group-custom">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control-custom" required>
                    </div>
                    
                    <button type="submit" class="btn-auth-submit mt-3">Login</button>
                </form>
                
                <div class="auth-footer mt-4">
                    Don't have an admin account? <a href="register.php" class="auth-link">Register Setup</a>
                </div>
            </div>
        </div>
        
        <!-- Right: Info Panel Section -->
        <div class="auth-info-section">
            <div class="auth-info-bg-pattern"></div>
            <div class="auth-info-header">
                <div class="info-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="24" height="24" rx="6" fill="#111827"/>
                        <path d="M6 18L18 6" stroke="var(--color-accent)" stroke-width="4" stroke-linecap="round"/>
                        <path d="M11 18L18 11" stroke="var(--color-accent)" stroke-width="3" stroke-linecap="round"/>
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
