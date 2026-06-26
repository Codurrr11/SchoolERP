<?php
// modules/school/classes/index.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf_token = generate_csrf_token();

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token.";
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Add Class
    if ($action === 'add') {
        $class_name = trim($_POST['class_name'] ?? '');
        $roman_number = trim($_POST['roman_number'] ?? '');
        $class_code = trim($_POST['class_code'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');

        if (empty($class_name)) {
            $_SESSION['flash_error'] = "Class Name is required.";
            header('Location: index.php');
            exit;
        }

        try {
            // Check if class_name already exists globally
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = :class_name");
            $stmt->execute([':class_name' => $class_name]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "A class with this name already exists.";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO classes (school_id, class_name, name, roman_number, class_code, sort_order, status)
                VALUES (:school_id, :class_name, :name, :roman_number, :class_code, :sort_order, :status)
            ");
            $stmt->execute([
                ':school_id' => $school_id,
                ':class_name' => $class_name,
                ':name' => $class_name, // legacy compat
                ':roman_number' => $roman_number ?: null,
                ':class_code' => $class_code ?: null,
                ':sort_order' => $sort_order,
                ':status' => $status
            ]);

            $_SESSION['flash_success'] = "Class added successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error adding class: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }

    // Edit Class
    if ($action === 'edit') {
        $class_id = intval($_POST['class_id'] ?? 0);
        $class_name = trim($_POST['class_name'] ?? '');
        $roman_number = trim($_POST['roman_number'] ?? '');
        $class_code = trim($_POST['class_code'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');

        if (empty($class_name) || empty($class_id)) {
            $_SESSION['flash_error'] = "Class Name is required.";
            header('Location: index.php');
            exit;
        }

        try {
            // Check if class_name already exists in another class
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = :class_name AND id != :id");
            $stmt->execute([':class_name' => $class_name, ':id' => $class_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Another class with this name already exists.";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE classes
                SET class_name = :class_name, name = :name, roman_number = :roman_number, class_code = :class_code, sort_order = :sort_order, status = :status
                WHERE id = :id AND school_id = :school_id
            ");
            $stmt->execute([
                ':class_name' => $class_name,
                ':name' => $class_name, // legacy compat
                ':roman_number' => $roman_number ?: null,
                ':class_code' => $class_code ?: null,
                ':sort_order' => $sort_order,
                ':status' => $status,
                ':id' => $class_id,
                ':school_id' => $school_id
            ]);

            $_SESSION['flash_success'] = "Class updated successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error updating class: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }

    // Delete Class
    if ($action === 'delete') {
        $class_id = intval($_POST['class_id'] ?? 0);

        try {
            // Check if student exists in this class
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = :class_id AND school_id = :school_id AND deleted_at IS NULL");
            $stmt->execute([':class_id' => $class_id, ':school_id' => $school_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Cannot delete class. Active students are assigned to it.";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM classes WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':id' => $class_id, ':school_id' => $school_id]);

            $_SESSION['flash_success'] = "Class deleted successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error deleting class: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }
}

// Fetch all classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY id ASC");
$stmt->execute([':school_id' => $school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../../includes/header.php';
?>

<div class="container-fluid py-4 font-primary">
    <!-- Page Heading -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1 font-heading fw-extrabold text-dark">Manage Classes</h2>
                <p class="text-xs text-muted mb-0">Configure academic class naming, sorting, and displays.</p>
            </div>
            <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="ph-bold ph-plus-circle me-1"></i> Add New Class
            </button>
        </div>
    </div>

    <!-- Classes Table Card -->
    <div class="card-premium border-0 shadow-sm rounded-4 p-0 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="teacher-detail-table w-100 align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="th-w-60">Sort Order</th>
                            <th>Class Name</th>
                            <th>Roman Number</th>
                            <th>Class Code</th>
                            <th>Status</th>
                            <th class="text-center th-w-120">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No classes added yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes as $c): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo $c['sort_order']; ?></td>
                                    <td><span class="fw-bold text-dark fs-6"><?php echo sanitize($c['class_name']); ?></span></td>
                                    <td><span class="badge bg-secondary-light text-dark fs-7 px-2.5 py-1.5 fw-semibold"><?php echo sanitize($c['roman_number'] ?: '—'); ?></span></td>
                                    <td class="text-muted fw-mono"><?php echo sanitize($c['class_code'] ?: '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($c['status'] === 'active') ? 'success' : 'danger'; ?>-light text-<?php echo ($c['status'] === 'active') ? 'success' : 'danger'; ?> fs-8 px-2 py-1 rounded">
                                            <?php echo ucfirst($c['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button class="teacher-action-btn edit-class-btn"
                                                data-id="<?php echo $c['id']; ?>"
                                                data-name="<?php echo sanitize($c['class_name']); ?>"
                                                data-roman="<?php echo sanitize($c['roman_number']); ?>"
                                                data-code="<?php echo sanitize($c['class_code']); ?>"
                                                data-sort="<?php echo $c['sort_order']; ?>"
                                                data-status="<?php echo $c['status']; ?>"
                                                title="Edit Class">
                                                <i class="ph-bold ph-pencil-simple-line text-primary"></i>
                                            </button>
                                            <button class="teacher-action-btn delete-class-btn"
                                                data-id="<?php echo $c['id']; ?>"
                                                data-name="<?php echo sanitize($c['class_name']); ?>"
                                                title="Delete Class">
                                                <i class="ph-bold ph-trash text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Add New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-body py-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-admin">Class Name *</label>
                            <input type="text" name="class_name" class="form-control-admin" placeholder="e.g. Class 10" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Roman Number</label>
                            <input type="text" name="roman_number" class="form-control-admin" placeholder="e.g. X">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Class Code</label>
                            <input type="text" name="class_code" class="form-control-admin" placeholder="e.g. C10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Sort Order *</label>
                            <input type="number" name="sort_order" class="form-control-admin" value="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Status</label>
                            <select name="status" class="form-control-admin">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Add Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="class_id" id="edit_class_id">
                <div class="modal-body py-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-admin">Class Name *</label>
                            <input type="text" name="class_name" id="edit_class_name" class="form-control-admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Roman Number</label>
                            <input type="text" name="roman_number" id="edit_roman_number" class="form-control-admin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Class Code</label>
                            <input type="text" name="class_code" id="edit_class_code" class="form-control-admin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Sort Order *</label>
                            <input type="number" name="sort_order" id="edit_sort_order" class="form-control-admin" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Status</label>
                            <select name="status" id="edit_status" class="form-control-admin">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Class Modal Form -->
<form id="deleteClassForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="class_id" id="delete_class_id">
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Flash Messages Alert
        const successMsg = '<?php echo $flash_success; ?>';
        const errorMsg = '<?php echo $flash_error; ?>';
        if (successMsg) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: successMsg,
                timer: 2000,
                showConfirmButton: false
            });
        }
        if (errorMsg) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: errorMsg,
                confirmButtonColor: '#3085d6'
            });
        }

        // Populate Edit Modal
        document.querySelectorAll('.edit-class-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_class_id').value = this.dataset.id;
                document.getElementById('edit_class_name').value = this.dataset.name;
                document.getElementById('edit_roman_number').value = this.dataset.roman;
                document.getElementById('edit_class_code').value = this.dataset.code;
                document.getElementById('edit_sort_order').value = this.dataset.sort;
                document.getElementById('edit_status').value = this.dataset.status;

                const editModal = new bootstrap.Modal(document.getElementById('editClassModal'));
                editModal.show();
            });
        });

        // Delete Confirmation
        document.querySelectorAll('.delete-class-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const classId = this.dataset.id;
                const className = this.dataset.name;

                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete "${className}". This action cannot be undone!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('delete_class_id').value = classId;
                        document.getElementById('deleteClassForm').submit();
                    }
                });
            });
        });
    });
</script>

<?php
require_once '../../../includes/footer.php';
?>
