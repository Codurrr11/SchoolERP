<?php
// modules/school/leads/status.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// List of available badge colors
$color_options = [
    'success' => 'Green (Success)',
    'danger' => 'Red (Danger)',
    'secondary' => 'Grey (Secondary)',
    'primary' => 'Blue (Primary)',
    'warning text-dark' => 'Yellow (Warning)',
    'dark' => 'Dark Grey (Dark)',
    'info' => 'Teal (Info)'
];

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: status.php');
        exit;
    }

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? 'primary');

        if (empty($name)) {
            $_SESSION['flash_error'] = "Status name is required.";
            header('Location: status.php');
            exit;
        }

        // Unique check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lead_statuses WHERE school_id = :school_id AND name = :name");
        $stmt->execute([':school_id' => $school_id, ':name' => $name]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "This lead status already exists.";
            header('Location: status.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO lead_statuses (school_id, name, color) VALUES (:school_id, :name, :color)");
            $stmt->execute([':school_id' => $school_id, ':name' => $name, ':color' => $color]);
            $_SESSION['flash_success'] = "Lead Status created successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to create status: " . $e->getMessage();
        }
        header('Location: status.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? 'primary');

        if (empty($name)) {
            $_SESSION['flash_error'] = "Status name is required.";
            header('Location: status.php');
            exit;
        }

        // Verify belongs to school
        $stmt = $pdo->prepare("SELECT * FROM lead_statuses WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = "Status not found.";
            header('Location: status.php');
            exit;
        }

        // Unique check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lead_statuses WHERE school_id = :school_id AND name = :name AND id != :id");
        $stmt->execute([':school_id' => $school_id, ':name' => $name, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Another lead status with this name already exists.";
            header('Location: status.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE lead_statuses SET name = :name, color = :color WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':name' => $name, ':color' => $color, ':id' => $id, ':school_id' => $school_id]);
            $_SESSION['flash_success'] = "Lead Status updated successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to update status: " . $e->getMessage();
        }
        header('Location: status.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // Verify belongs to school
        $stmt = $pdo->prepare("SELECT * FROM lead_statuses WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = "Status not found.";
            header('Location: status.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM lead_statuses WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':id' => $id, ':school_id' => $school_id]);
            $_SESSION['flash_success'] = "Lead Status deleted successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to delete status.";
        }
        header('Location: status.php');
        exit;
    }
}

// Fetch statuses
$stmt = $pdo->prepare("SELECT * FROM lead_statuses WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$statuses = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- SweetAlert Setup & Notifications -->
<?php if ($flash_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: <?php echo json_encode($flash_success); ?>,
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true,
        customClass: { popup: 'swal-toast-custom' }
    });
});
</script>
<?php endif; ?>

<?php if ($flash_error): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Error Occurred',
        text: <?php echo json_encode($flash_error); ?>,
        confirmButtonColor: '#2563EB',
        customClass: { confirmButton: 'swal-btn-custom' }
    });
});
</script>
<?php endif; ?>

<!-- Header Panel -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">Lead Statuses</h2>
        <p class="text-xs text-muted mb-0">Configure and manage stage states for potential student workflows.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <button type="button" class="btn-admin-action" data-bs-toggle="modal" data-bs-target="#addStatusModal">
            <i class="ph-bold ph-plus"></i> Add Status
        </button>
    </div>
</div>

<!-- Statuses Table Card -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-header">
                <h6>All Stage Flows</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($statuses)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-pulse"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No lead statuses set up</h5>
                            <p class="text-xs text-muted mb-4">Initialize your first lead status (e.g. Interested) to begin.</p>
                            <button type="button" class="btn-admin-action" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                <i class="ph-bold ph-plus"></i> Add Status
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Status Name</th>
                                    <th>Color Indicator Preview</th>
                                    <th>Created At</th>
                                    <th class="col-action-width">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statuses as $stat): 
                                    // Map helper
                                    $bg_color = 'bg-primary text-white';
                                    if ($stat['color'] === 'success') $bg_color = 'bg-success text-white';
                                    elseif ($stat['color'] === 'danger') $bg_color = 'bg-danger text-white';
                                    elseif ($stat['color'] === 'secondary') $bg_color = 'bg-secondary text-white';
                                    elseif ($stat['color'] === 'warning text-dark') $bg_color = 'bg-warning text-dark';
                                    elseif ($stat['color'] === 'dark') $bg_color = 'bg-dark text-white';
                                    elseif ($stat['color'] === 'info') $bg_color = 'bg-info text-white';
                                ?>
                                    <tr>
                                        <!-- Status Name -->
                                        <td class="fw-semibold text-xs">
                                            <div class="activity-cell">
                                                <div class="icon-circle-sm activity-icon-blue">
                                                    <i class="ph-light ph-pulse"></i>
                                                </div>
                                                <span><?php echo sanitize($stat['name']); ?></span>
                                            </div>
                                        </td>

                                        <!-- Preview Badge -->
                                        <td>
                                            <span class="badge <?php echo $bg_color; ?> px-2.5 py-1.5 rounded-pill text-xxs fw-semibold">
                                                <?php echo sanitize($stat['name']); ?>
                                            </span>
                                        </td>

                                        <!-- Created At -->
                                        <td class="text-xs text-muted">
                                            <?php echo date('M d, Y', strtotime($stat['created_at'])); ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <button type="button"
                                                        class="teacher-action-btn action-edit edit-status-btn"
                                                        title="Edit Status"
                                                        data-id="<?php echo $stat['id']; ?>"
                                                        data-name="<?php echo sanitize($stat['name']); ?>"
                                                        data-color="<?php echo sanitize($stat['color']); ?>">
                                                    <i class="ph-light ph-pencil"></i>
                                                </button>

                                                <button type="button"
                                                        class="teacher-action-btn action-delete delete-status-btn"
                                                        title="Delete Status"
                                                        data-id="<?php echo $stat['id']; ?>"
                                                        data-name="<?php echo sanitize($stat['name']); ?>">
                                                    <i class="ph-light ph-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addStatusModal" tabindex="-1" aria-labelledby="addStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header modal-header-admin py-2 px-3">
                <h6 class="modal-title font-heading fw-bold" id="addStatusModalLabel">Add Lead Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="status.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label-admin">Status Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control-admin" required placeholder="e.g. Follow up, Interested">
                    </div>
                    <div>
                        <label class="form-label-admin">Color Badge Style <span class="text-danger">*</span></label>
                        <select name="color" class="form-control-admin" required>
                            <?php foreach ($color_options as $val => $label): ?>
                                <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer modal-footer-admin p-2">
                    <button type="button" class="btn btn-xs btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-xs btn-primary font-heading px-3">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header modal-header-admin py-2 px-3">
                <h6 class="modal-title font-heading fw-bold" id="editStatusModalLabel">Edit Lead Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="status.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label-admin">Status Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control-admin" required>
                    </div>
                    <div>
                        <label class="form-label-admin">Color Badge Style <span class="text-danger">*</span></label>
                        <select name="color" id="edit_color" class="form-control-admin" required>
                            <?php foreach ($color_options as $val => $label): ?>
                                <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer modal-footer-admin p-2">
                    <button type="button" class="btn btn-xs btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-xs btn-primary font-heading px-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteStatusForm" action="status.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Click
    document.querySelectorAll('.edit-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_color').value = this.dataset.color;
            const myModal = new bootstrap.Modal(document.getElementById('editStatusModal'));
            myModal.show();
        });
    });

    // Delete Click
    document.querySelectorAll('.delete-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;

            Swal.fire({
                title: 'Delete Status?',
                text: `Are you sure you want to permanently delete lead status "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteStatusForm').submit();
                }
            });
        });
    });
});
</script>

<?php
require_once '../../../includes/footer.php';
?>
