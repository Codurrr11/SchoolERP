<?php
// modules/school/sections/index.php
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

    // Add Section
    if ($action === 'add') {
        $class_id = intval($_POST['class_id'] ?? 0);
        $section_name = trim($_POST['section_name'] ?? '');
        $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;
        $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
        $status = trim($_POST['status'] ?? 'active');

        if (empty($class_id) || empty($section_name)) {
            $_SESSION['flash_error'] = "Class and Section Name are required.";
            header('Location: index.php');
            exit;
        }

        try {
            // Check if section already exists for this class
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE class_id = :class_id AND section_name = :section_name");
            $stmt->execute([':class_id' => $class_id, ':section_name' => $section_name]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "This section already exists for the selected class.";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO sections (school_id, class_id, section_name, name, class_teacher_id, capacity, status)
                VALUES (:school_id, :class_id, :section_name, :name, :class_teacher_id, :capacity, :status)
            ");
            $stmt->execute([
                ':school_id' => $school_id,
                ':class_id' => $class_id,
                ':section_name' => $section_name,
                ':name' => $section_name, // legacy compat
                ':class_teacher_id' => $class_teacher_id,
                ':capacity' => $capacity,
                ':status' => $status
            ]);

            $_SESSION['flash_success'] = "Section added successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error adding section: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }

    // Edit Section
    if ($action === 'edit') {
        $section_id = intval($_POST['section_id'] ?? 0);
        $class_id = intval($_POST['class_id'] ?? 0);
        $section_name = trim($_POST['section_name'] ?? '');
        $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;
        $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
        $status = trim($_POST['status'] ?? 'active');

        if (empty($class_id) || empty($section_name) || empty($section_id)) {
            $_SESSION['flash_error'] = "Class and Section Name are required.";
            header('Location: index.php');
            exit;
        }

        try {
            // Check if section already exists for this class in another record
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE class_id = :class_id AND section_name = :section_name AND id != :id");
            $stmt->execute([':class_id' => $class_id, ':section_name' => $section_name, ':id' => $section_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Another section with this name already exists for the selected class.";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE sections 
                SET class_id = :class_id, section_name = :section_name, name = :name, class_teacher_id = :class_teacher_id, capacity = :capacity, status = :status
                WHERE id = :id AND school_id = :school_id
            ");
            $stmt->execute([
                ':class_id' => $class_id,
                ':section_name' => $section_name,
                ':name' => $section_name, // legacy compat
                ':class_teacher_id' => $class_teacher_id,
                ':capacity' => $capacity,
                ':status' => $status,
                ':id' => $section_id,
                ':school_id' => $school_id
            ]);

            $_SESSION['flash_success'] = "Section updated successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error updating section: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }

    // Delete Section
    if ($action === 'delete') {
        $section_id = intval($_POST['section_id'] ?? 0);

        try {
            // Check if student exists in this section
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = :section_id AND school_id = :school_id AND deleted_at IS NULL");
            $stmt->execute([':section_id' => $section_id, ':school_id' => $school_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Cannot delete section. Active students are assigned to it.";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':id' => $section_id, ':school_id' => $school_id]);

            $_SESSION['flash_success'] = "Section deleted successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error deleting section: " . $e->getMessage();
        }
        header('Location: index.php');
        exit;
    }
}

// Fetch all classes for dropdown & reference
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY id ASC");
$stmt->execute([':school_id' => $school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all teachers for dropdown
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM teachers WHERE school_id = :school_id AND status = 'active' ORDER BY first_name ASC");
$stmt->execute([':school_id' => $school_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all sections with class name and class teacher name
$stmt = $pdo->prepare("
    SELECT sec.*, c.class_name, t.first_name as teacher_first, t.last_name as teacher_last
    FROM sections sec
    JOIN classes c ON sec.class_id = c.id
    LEFT JOIN teachers t ON sec.class_teacher_id = t.id
    WHERE sec.school_id = :school_id
    ORDER BY c.id ASC, sec.section_name ASC
");
$stmt->execute([':school_id' => $school_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../../includes/header.php';
?>

<div class="container-fluid py-4 font-primary">
    <!-- Page Heading -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1 font-heading fw-extrabold text-dark">Manage Sections</h2>
                <p class="text-xs text-muted mb-0">Configure class-specific sections, capacity, and assign class teachers.</p>
            </div>
            <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                <i class="ph-bold ph-plus-circle me-1"></i> Add New Section
            </button>
        </div>
    </div>

    <!-- Sections Table Card -->
    <div class="card-premium border-0 shadow-sm rounded-4 p-0 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="teacher-detail-table w-100 align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section Name</th>
                            <th>Class Teacher</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th class="text-center th-w-120">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <?php if (empty($sections)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No sections added yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sections as $s): ?>
                                <tr>
                                    <td><span class="fw-bold text-dark fs-6"><?php echo sanitize($s['class_name']); ?></span></td>
                                    <td><span class="badge bg-secondary-light text-dark fs-7 px-3 py-1.5 fw-bold"><?php echo sanitize($s['section_name']); ?></span></td>
                                    <td>
                                        <?php if ($s['class_teacher_id']): ?>
                                            <span class="text-dark fw-semibold"><?php echo sanitize($s['teacher_first'] . ' ' . $s['teacher_last']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted italic">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted fw-semibold"><?php echo $s['capacity'] ?: '—'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($s['status'] === 'active') ? 'success' : 'danger'; ?>-light text-<?php echo ($s['status'] === 'active') ? 'success' : 'danger'; ?> fs-8 px-2 py-1 rounded">
                                            <?php echo ucfirst($s['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button class="teacher-action-btn edit-section-btn" 
                                                    data-id="<?php echo $s['id']; ?>"
                                                    data-class-id="<?php echo $s['class_id']; ?>"
                                                    data-name="<?php echo sanitize($s['section_name']); ?>"
                                                    data-teacher="<?php echo $s['class_teacher_id']; ?>"
                                                    data-capacity="<?php echo $s['capacity']; ?>"
                                                    data-status="<?php echo $s['status']; ?>"
                                                    title="Edit Section">
                                                <i class="ph-bold ph-pencil-simple-line text-primary"></i>
                                            </button>
                                            <button class="teacher-action-btn delete-section-btn" 
                                                    data-id="<?php echo $s['id']; ?>"
                                                    data-name="<?php echo sanitize($s['class_name'] . ' - ' . $s['section_name']); ?>"
                                                    title="Delete Section">
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

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-body py-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-admin">Class *</label>
                            <select name="class_id" class="form-control-admin" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-admin">Section Name *</label>
                            <input type="text" name="section_name" class="form-control-admin" placeholder="e.g. A" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label-admin">Class Teacher</label>
                            <select name="class_teacher_id" class="form-control-admin">
                                <option value="">-- Select Teacher (Optional) --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Capacity</label>
                            <input type="number" name="capacity" class="form-control-admin" placeholder="e.g. 40">
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
                    <button type="submit" class="btn btn-primary btn-sm px-3">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Edit Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-body py-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-admin">Class *</label>
                            <select name="class_id" id="edit_class_id" class="form-control-admin" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-admin">Section Name *</label>
                            <input type="text" name="section_name" id="edit_section_name" class="form-control-admin" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label-admin">Class Teacher</label>
                            <select name="class_teacher_id" id="edit_class_teacher_id" class="form-control-admin">
                                <option value="">-- Select Teacher (Optional) --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">Capacity</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control-admin">
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

<!-- Delete Section Modal Form -->
<form id="deleteSectionForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="section_id" id="delete_section_id">
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
    document.querySelectorAll('.edit-section-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_section_id').value = this.dataset.id;
            document.getElementById('edit_class_id').value = this.dataset.classId;
            document.getElementById('edit_section_name').value = this.dataset.name;
            document.getElementById('edit_class_teacher_id').value = this.dataset.teacher || '';
            document.getElementById('edit_capacity').value = this.dataset.capacity || '';
            document.getElementById('edit_status').value = this.dataset.status;
            
            const editModal = new bootstrap.Modal(document.getElementById('editSectionModal'));
            editModal.show();
        });
    });

    // Delete Confirmation
    document.querySelectorAll('.delete-section-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sectionId = this.dataset.id;
            const sectionName = this.dataset.name;

            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete "${sectionName}". This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_section_id').value = sectionId;
                    document.getElementById('deleteSectionForm').submit();
                }
            });
        });
    });
});
</script>

<?php
require_once '../../../includes/footer.php';
?>
