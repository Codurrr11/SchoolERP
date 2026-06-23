<?php
// modules/school/parents/index.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// AJAX endpoint for fetching parent details for edit modal
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

// ─── POST ACTIONS HANDLING ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: index.php');
        exit;
    }

    // Toggle Status
    if ($action === 'toggle_status') {
        $parent_id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE id = :id AND school_id = :school_id");
        $stmt->execute([':id' => $parent_id, ':school_id' => $school_id]);
        $parent = $stmt->fetch();
        if ($parent) {
            $new_status = ($parent['status'] === 'active') ? 'inactive' : 'active';
            $user_status = ($new_status === 'active') ? 'active' : 'inactive';
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE parents SET status = :status WHERE id = :id AND school_id = :school_id")->execute([':status' => $new_status, ':id' => $parent_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id AND school_id = :school_id")->execute([':status' => $user_status, ':user_id' => $parent['user_id'], ':school_id' => $school_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = "Parent status updated successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to update status: " . $e->getMessage();
            }
        }
        header('Location: index.php');
        exit;
    }

    // Delete Parent (Soft Delete)
    if ($action === 'delete') {
        $parent_id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE id = :id AND school_id = :school_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $parent_id, ':school_id' => $school_id]);
        $parent = $stmt->fetch();
        if ($parent) {
            try {
                $pdo->beginTransaction();
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE parents SET deleted_at = :now WHERE id = :id AND school_id = :school_id")->execute([':now' => $now, ':id' => $parent_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET deleted_at = :now WHERE id = :id AND school_id = :school_id")
                    ->execute([':now' => $now, ':id' => $parent['user_id'], ':school_id' => $school_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = "Parent moved to Trash successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to delete parent: " . $e->getMessage();
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
                $stmt = $pdo->query("SELECT user_id FROM parents WHERE id IN ($in_clause) AND school_id = $school_id AND deleted_at IS NULL");
                $user_ids = array_column($stmt->fetchAll(), 'user_id');
                $pdo->exec("UPDATE parents SET deleted_at = '$now' WHERE id IN ($in_clause) AND school_id = $school_id");
                if (!empty($user_ids)) {
                    $u_in_clause = implode(',', $user_ids);
                    $pdo->exec("UPDATE users SET deleted_at = '$now' WHERE id IN ($u_in_clause) AND school_id = $school_id");
                }
                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " parent(s) moved to Trash!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Bulk delete failed: " . $e->getMessage();
            }
        }
        header('Location: index.php');
        exit;
    }

    // Add Parent
    if ($action === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($first_name) || empty($mobile) || empty($username)) {
            $_SESSION['flash_error'] = "First Name, Mobile No, and Username are required fields.";
            header('Location: index.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Username is already taken.";
            header('Location: index.php');
            exit;
        }

        if (empty($password)) { $password = bin2hex(random_bytes(4)); }

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
                ':parent_type' => $_POST['parent_type'] ?? 'Father',
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
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Parent added successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to add parent: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }

    // Edit Parent
    if ($action === 'edit') {
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        if (empty($first_name) || empty($mobile)) {
            $_SESSION['flash_error'] = "First Name and Mobile No are required fields.";
            header('Location: index.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT user_id FROM parents WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':id' => $parent_id, ':school_id' => $school_id]);
            $user_id = $stmt->fetchColumn();
            if (!$user_id) throw new Exception("Parent not found.");

            $stmt_u = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, gender = :gender WHERE id = :id AND school_id = :school_id");
            $gender_val = !empty($_POST['gender']) ? strtolower($_POST['gender']) : 'male';
            $stmt_u->execute([':first_name' => $first_name, ':last_name' => $last_name, ':email' => $email, ':phone' => $mobile, ':gender' => $gender_val, ':id' => $user_id, ':school_id' => $school_id]);

            $stmt_p = $pdo->prepare("UPDATE parents SET first_name = :first_name, last_name = :last_name, mobile = :mobile, alternate_mobile = :alternate_mobile, whatsapp_no = :whatsapp_no, email = :email, gender = :gender, parent_type = :parent_type, aadhaar_no = :aadhaar_no, qualification = :qualification, designation = :designation, company_name = :company_name, company_address = :company_address, company_phone = :company_phone, address = :address, pincode = :pincode, city = :city, state = :state, country = :country WHERE id = :id AND school_id = :school_id");
            $stmt_p->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':mobile' => $mobile,
                ':alternate_mobile' => $_POST['alternate_mobile'] ?? null,
                ':whatsapp_no' => $_POST['whatsapp_no'] ?? null,
                ':email' => $email,
                ':gender' => $gender_val,
                ':parent_type' => $_POST['parent_type'] ?? 'Father',
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
                ':country' => $_POST['country'] ?? 'India',
                ':id' => $parent_id,
                ':school_id' => $school_id
            ]);

            $pdo->commit();
            $_SESSION['flash_success'] = "Parent updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }
}

// ─── PAGINATION & DATA FETCHING ──────────────────────────────────────────────
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$where = "WHERE p.school_id = :school_id AND p.deleted_at IS NULL";
$params = [':school_id' => $school_id];

if ($search) { 
    $where .= " AND (p.first_name LIKE :search OR p.last_name LIKE :search OR p.email LIKE :search OR p.mobile LIKE :search)"; 
    $params[':search'] = "%$search%";
}

// Count total rows for pagination
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parents p $where");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated data
$sql = "SELECT p.*, u.username as u_name FROM parents p JOIN users u ON p.user_id = u.id $where ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parents = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">Parents Directory</h2>
        <p class="text-xs text-muted mb-0">Manage parent profiles and their records.</p>
    </div>
</div>

<div class="row mb-3 g-3">
    <div class="col-12">
        <div class="card-premium teacher-directory-card">
            <div class="card-header border-0 bg-transparent p-3 d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="teacher-header-btn btn-accent" data-bs-toggle="modal" data-bs-target="#addParentModal" title="Add Parent">
                        <i class="ph-light ph-user-plus"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-sky" title="Import Parents">
                        <i class="ph-light ph-upload-simple"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-sky" title="Export Parents">
                        <i class="ph-light ph-download-simple"></i>
                    </button>
                    <button type="button" class="teacher-header-btn btn-red" id="bulkDeleteBtn" disabled title="Move Selected to Trash">
                        <i class="ph-light ph-trash"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                    <form method="GET" action="index.php" class="table-search-box m-0 d-flex align-items-center" style="background: #fff; border: 1px solid var(--color-border); border-radius: 6px; padding: 0 10px;">
                        <i class="ph-light ph-magnifying-glass text-muted"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search parents..." class="border-0 bg-transparent" style="box-shadow: none; outline: none; padding: 5px 10px; height: 34px;">
                    </form>
                    <div class="teacher-total-badge">
                        <i class="ph-light ph-users-three"></i>
                        Total: <span class="count-num"><?php echo $total_records; ?></span>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($parents)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-users"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No parents registered</h5>
                            <p class="text-xs text-muted mb-4">Register your first parent to get started.</p>
                            <button type="button" class="btn-admin-action mx-auto" data-bs-toggle="modal" data-bs-target="#addParentModal">
                                <i class="ph-light ph-plus"></i> Add Parent
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 46px;"><input type="checkbox" class="table-checkbox" id="selectAllCheckbox"></th>
                                    <th style="width: 50px;">#</th>
                                    <th>Parent Name</th>
                                    <th>Contact Info</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="parentsTableBody">
                                <?php $idx = $offset + 1; foreach ($parents as $p): ?>
                                    <tr>
                                        <td><input type="checkbox" value="<?php echo $p['id']; ?>" class="table-checkbox parent-select-checkbox"></td>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="student-avatar-placeholder">
                                                    <?php echo strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'] ?? '', 0, 1)); ?>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <a href="view.php?id=<?php echo $p['id']; ?>" class="student-name-link">
                                                        <?php echo sanitize($p['first_name'] . ' ' . $p['last_name']); ?>
                                                    </a>
                                                    <span class="text-xs text-muted">Username: <strong class="text-dark"><?php echo sanitize($p['u_name']); ?></strong></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column text-xs">
                                                <span>Mobile: <strong class="text-dark"><?php echo sanitize($p['mobile'] ?? '—'); ?></strong></span>
                                                <span>Email: <strong class="text-dark"><?php echo sanitize($p['email'] ?? '—'); ?></strong></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($p['parent_type']); ?></span></td>
                                        <td>
                                            <form action="index.php" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <div class="form-check form-switch teacher-status-switch p-0 m-0">
                                                    <input class="form-check-input ms-0" type="checkbox" role="switch" <?php echo ($p['status'] === 'active') ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                </div>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <a href="view.php?id=<?php echo $p['id']; ?>" class="teacher-action-btn action-view" title="View Profile"><i class="ph-light ph-eye"></i></a>
                                                <button type="button" class="teacher-action-btn action-edit edit-parent-btn" data-id="<?php echo $p['id']; ?>" title="Edit Parent"><i class="ph-light ph-pencil-simple"></i></button>
                                                <button type="button" class="teacher-action-btn action-delete delete-parent-btn" data-id="<?php echo $p['id']; ?>" data-name="<?php echo sanitize($p['first_name'] . ' ' . $p['last_name']); ?>" title="Delete Parent"><i class="ph-light ph-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                        <span class="text-xs text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</span>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                <?php for($i=1; $i<=$total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form id="bulkDeleteForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="bulk_delete">
    <div id="bulkDeleteInputs"></div>
</form>

<form id="deleteParentForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_parent_id">
</form>


<div class="modal fade" id="addParentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Parent Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="index.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    
                    <h6 class="text-primary text-uppercase mb-3">Personal Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">First Name *</label>
                                <input type="text" name="first_name" class="form-control-admin" placeholder="First Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last Name</label>
                                <input type="text" name="last_name" class="form-control-admin" placeholder="Last Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Mobile No. *</label>
                                <input type="text" name="mobile" class="form-control-admin" placeholder="Mobile no." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Alternate Mobile No.</label>
                                <input type="text" name="alternate_mobile" class="form-control-admin" placeholder="Alternate Mobile No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Whatsapp No.</label>
                                <input type="text" name="whatsapp_no" class="form-control-admin" placeholder="Whatsapp no.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Email</label>
                                <input type="email" name="email" class="form-control-admin" placeholder="Email">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Gender</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="gender" value="male" checked class="form-check-input"> Male</label>
                                    <label class="form-check-label"><input type="radio" name="gender" value="female" class="form-check-input"> Female</label>
                                    <label class="form-check-label"><input type="radio" name="gender" value="other" class="form-check-input"> Other</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Parent Type *</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="parent_type" value="Mother" class="form-check-input"> Mother</label>
                                    <label class="form-check-label"><input type="radio" name="parent_type" value="Father" checked class="form-check-input"> Father</label>
                                    <label class="form-check-label"><input type="radio" name="parent_type" value="Guardian" class="form-check-input"> Guardian</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Qualification</label>
                                <select name="qualification" class="form-control-admin">
                                    <option value="">Select Qualification</option>
                                    <option value="Under Graduate">Under Graduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Post Graduate">Post Graduate</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Aadhar No.</label>
                                <input type="text" name="aadhaar_no" class="form-control-admin" placeholder="Aadhar No.">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Employment:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Company/Business Name</label>
                                <input type="text" name="company_name" class="form-control-admin" placeholder="Company/Business Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Designation</label>
                                <input type="text" name="designation" class="form-control-admin" placeholder="Designation">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Company Phone</label>
                                <input type="text" name="company_phone" class="form-control-admin" placeholder="Company Phone">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-admin">Company Address</label>
                                <input type="text" name="company_address" class="form-control-admin" placeholder="Company Address">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Address Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label-admin">Residential Address</label>
                                <input type="text" name="address" class="form-control-admin" placeholder="Address">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Pincode</label>
                                <input type="text" name="pincode" class="form-control-admin" placeholder="Search pincode here...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">City</label>
                                <input type="text" name="city" class="form-control-admin" placeholder="City name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">State</label>
                                <input type="text" name="state" class="form-control-admin" placeholder="State name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Country</label>
                                <input type="text" name="country" class="form-control-admin" value="India" placeholder="Country">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">System Access Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-admin">Username *</label>
                                <input type="text" name="username" class="form-control-admin" placeholder="Username" required>
                                <small class="text-xs text-muted">Username must be unique, it'll be used for login.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-admin">Password</label>
                                <input type="password" name="password" class="form-control-admin" placeholder="Leave blank to generate randomly">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Parent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editParentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Parent Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="index.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="parent_id" id="edit_parent_id">
                
                <div class="modal-body">
                    
                    <h6 class="text-primary text-uppercase mb-3">Personal Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">First Name *</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control-admin" placeholder="First Name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control-admin" placeholder="Last Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Mobile No. *</label>
                                <input type="text" name="mobile" id="edit_mobile" class="form-control-admin" placeholder="Mobile no." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Alternate Mobile No.</label>
                                <input type="text" name="alternate_mobile" id="edit_alternate_mobile" class="form-control-admin" placeholder="Alternate Mobile No.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Whatsapp No.</label>
                                <input type="text" name="whatsapp_no" id="edit_whatsapp_no" class="form-control-admin" placeholder="Whatsapp no.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control-admin" placeholder="Email">
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
                                <label class="form-label-admin">Parent Type *</label>
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label"><input type="radio" name="parent_type" id="edit_type_mother" value="Mother" class="form-check-input"> Mother</label>
                                    <label class="form-check-label"><input type="radio" name="parent_type" id="edit_type_father" value="Father" class="form-check-input"> Father</label>
                                    <label class="form-check-label"><input type="radio" name="parent_type" id="edit_type_guardian" value="Guardian" class="form-check-input"> Guardian</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Qualification</label>
                                <select name="qualification" id="edit_qualification" class="form-control-admin">
                                    <option value="">Select Qualification</option>
                                    <option value="Under Graduate">Under Graduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Post Graduate">Post Graduate</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Aadhar No.</label>
                                <input type="text" name="aadhaar_no" id="edit_aadhaar_no" class="form-control-admin" placeholder="Aadhar No.">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Employment:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">Company/Business Name</label>
                                <input type="text" name="company_name" id="edit_company_name" class="form-control-admin" placeholder="Company/Business Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Designation</label>
                                <input type="text" name="designation" id="edit_designation" class="form-control-admin" placeholder="Designation">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">Company Phone</label>
                                <input type="text" name="company_phone" id="edit_company_phone" class="form-control-admin" placeholder="Company Phone">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-admin">Company Address</label>
                                <input type="text" name="company_address" id="edit_company_address" class="form-control-admin" placeholder="Company Address">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary text-uppercase mb-3 mt-4">Address Details:</h6>
                    <div class="modal-section-card">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label-admin">Residential Address</label>
                                <input type="text" name="address" id="edit_address" class="form-control-admin" placeholder="Address">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Pincode</label>
                                <input type="text" name="pincode" id="edit_pincode" class="form-control-admin" placeholder="Search pincode here...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">City</label>
                                <input type="text" name="city" id="edit_city" class="form-control-admin" placeholder="City name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">State</label>
                                <input type="text" name="state" id="edit_state" class="form-control-admin" placeholder="State name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-admin">Country</label>
                                <input type="text" name="country" id="edit_country" class="form-control-admin" placeholder="Country">
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

<div id="parent-page-data" data-base-url="<?php echo BASE_URL; ?>" data-csrf-token="<?php echo $csrf_token; ?>" data-flash-success="<?php echo sanitize($flash_success); ?>" data-flash-error="<?php echo sanitize($flash_error); ?>"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Toast Notifications for PHP Flash Messages
    const pageData = document.getElementById('parent-page-data');
    const flashSuccess = pageData.dataset.flashSuccess;
    const flashError = pageData.dataset.flashError;

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    if (flashSuccess) { Toast.fire({ icon: 'success', title: flashSuccess }); }
    if (flashError) { Toast.fire({ icon: 'error', title: flashError }); }

    // 2. Select All and Bulk Action Activation Logic
    const selectAllCb = document.getElementById('selectAllCheckbox');
    const rowCbs = document.querySelectorAll('.parent-select-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    function checkBulkActions() {
        const checkedCount = document.querySelectorAll('.parent-select-checkbox:checked').length;
        if(bulkDeleteBtn) bulkDeleteBtn.disabled = (checkedCount === 0);
    }

    if (selectAllCb) {
        selectAllCb.addEventListener('change', function() {
            rowCbs.forEach(cb => cb.checked = this.checked);
            checkBulkActions();
        });
    }

    rowCbs.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = document.querySelectorAll('.parent-select-checkbox:checked').length === rowCbs.length;
            if (selectAllCb) selectAllCb.checked = allChecked;
            checkBulkActions();
        });
    });

    // 3. Single Delete SweetAlert Warning
    const deleteBtns = document.querySelectorAll('.delete-parent-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            Swal.fire({
                title: 'Move to Trash?',
                text: `Are you sure you want to delete ${name}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_parent_id').value = id;
                    document.getElementById('deleteParentForm').submit();
                }
            });
        });
    });

    // 4. Bulk Delete SweetAlert Warning
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.parent-select-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) return;

            Swal.fire({
                title: `Delete ${selectedIds.length} Parents?`,
                text: "The selected records will be moved to Trash.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete them!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const inputsContainer = document.getElementById('bulkDeleteInputs');
                    inputsContainer.innerHTML = selectedIds.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
                    document.getElementById('bulkDeleteForm').submit();
                }
            });
        });
    }

    // 5. Fetch and Populate Data for Edit Modal
    const editButtons = document.querySelectorAll('.edit-parent-btn');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(`index.php?get_parent_details=1&id=${id}`)
                .then(res => res.json())
                .then(res => {
                    if(res.success) {
                        const data = res.data;
                        
                        document.getElementById('edit_parent_id').value = data.id || '';
                        document.getElementById('edit_first_name').value = data.first_name || '';
                        document.getElementById('edit_last_name').value = data.last_name || '';
                        document.getElementById('edit_mobile').value = data.mobile || '';
                        document.getElementById('edit_alternate_mobile').value = data.alternate_mobile || '';
                        document.getElementById('edit_whatsapp_no').value = data.whatsapp_no || '';
                        document.getElementById('edit_email').value = data.email || '';
                        
                        if(data.gender) {
                            const genRad = document.getElementById(`edit_gender_${data.gender}`);
                            if(genRad) genRad.checked = true;
                        }
                        if(data.parent_type) {
                            const pType = data.parent_type.toLowerCase();
                            const typeRad = document.getElementById(`edit_type_${pType}`);
                            if(typeRad) typeRad.checked = true;
                        }

                        document.getElementById('edit_qualification').value = data.qualification || '';
                        document.getElementById('edit_aadhaar_no').value = data.aadhaar_no || '';
                        document.getElementById('edit_company_name').value = data.company_name || '';
                        document.getElementById('edit_designation').value = data.designation || '';
                        document.getElementById('edit_company_address').value = data.company_address || '';
                        document.getElementById('edit_company_phone').value = data.company_phone || '';
                        document.getElementById('edit_address').value = data.address || '';
                        document.getElementById('edit_pincode').value = data.pincode || '';
                        document.getElementById('edit_city').value = data.city || '';
                        document.getElementById('edit_state').value = data.state || '';
                        document.getElementById('edit_country').value = data.country || 'India';

                        new bootstrap.Modal(document.getElementById('editParentModal')).show();
                    } else {
                        Toast.fire({ icon: 'error', title: 'Could not fetch parent details.' });
                    }
                });
        });
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>