<?php
// modules/school/leads/sources.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: sources.php');
        exit;
    }

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            $_SESSION['flash_error'] = "Source name is required.";
            header('Location: sources.php');
            exit;
        }

        // Unique check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lead_sources WHERE school_id = :school_id AND name = :name");
        $stmt->execute([':school_id' => $school_id, ':name' => $name]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "This lead source already exists.";
            header('Location: sources.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO lead_sources (school_id, name) VALUES (:school_id, :name)");
            $stmt->execute([':school_id' => $school_id, ':name' => $name]);
            $_SESSION['flash_success'] = "Lead Source created successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to create source: " . $e->getMessage();
        }
        header('Location: sources.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            $_SESSION['flash_error'] = "Source name is required.";
            header('Location: sources.php');
            exit;
        }

        // Verify belongs to school
        $stmt = $pdo->prepare("SELECT * FROM lead_sources WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = "Source not found.";
            header('Location: sources.php');
            exit;
        }

        // Unique check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lead_sources WHERE school_id = :school_id AND name = :name AND id != :id");
        $stmt->execute([':school_id' => $school_id, ':name' => $name, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "Another lead source with this name already exists.";
            header('Location: sources.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE lead_sources SET name = :name WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':name' => $name, ':id' => $id, ':school_id' => $school_id]);
            $_SESSION['flash_success'] = "Lead Source updated successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to update source: " . $e->getMessage();
        }
        header('Location: sources.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // Verify belongs to school
        $stmt = $pdo->prepare("SELECT * FROM lead_sources WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = "Source not found.";
            header('Location: sources.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM lead_sources WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':id' => $id, ':school_id' => $school_id]);
            $_SESSION['flash_success'] = "Lead Source deleted successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to delete source.";
        }
        header('Location: sources.php');
        exit;
    }
}

// Fetch sources
$stmt = $pdo->prepare("SELECT * FROM lead_sources WHERE school_id = :school_id ORDER BY name ASC");
$stmt->execute([':school_id' => $school_id]);
$sources = $stmt->fetchAll();

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
        <h2 class="mb-1 font-heading fw-extrabold">Lead Sources</h2>
        <p class="text-xs text-muted mb-0">Configure and manage various channels from which prospective inquiries originate.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <button type="button" class="btn-admin-action" data-bs-toggle="modal" data-bs-target="#addSourceModal">
            <i class="ph-bold ph-plus"></i> Add Lead Source
        </button>
    </div>
</div>

<!-- Sources Table Card -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-header">
                <h6>All Lead Channels</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($sources)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-link"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No lead channels set up</h5>
                            <p class="text-xs text-muted mb-4">Initialize your first lead source (e.g. Facebook) to begin.</p>
                            <button type="button" class="btn-admin-action" data-bs-toggle="modal" data-bs-target="#addSourceModal">
                                <i class="ph-bold ph-plus"></i> Add Lead Source
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Channel Name</th>
                                    <th>Created At</th>
                                    <th class="col-action-width">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sources as $src): ?>
                                    <tr>
                                        <!-- Source Name -->
                                        <td class="fw-semibold text-xs">
                                            <div class="activity-cell">
                                                <div class="icon-circle-sm activity-icon-blue">
                                                    <i class="ph-light ph-link"></i>
                                                </div>
                                                <span><?php echo sanitize($src['name']); ?></span>
                                            </div>
                                        </td>

                                        <!-- Created At -->
                                        <td class="text-xs text-muted">
                                            <?php echo date('M d, Y', strtotime($src['created_at'])); ?>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <!-- Edit Button -->
                                                <button type="button" 
                                                        class="btn btn-xs btn-outline-primary py-1 px-2 text-xxs font-heading edit-source-btn" 
                                                        title="Edit Source"
                                                        data-id="<?php echo $src['id']; ?>"
                                                        data-name="<?php echo sanitize($src['name']); ?>">
                                                    Edit
                                                </button>

                                                <!-- Delete Button -->
                                                <button type="button" 
                                                        class="btn btn-xs btn-outline-danger py-1 px-2 text-xxs font-heading delete-source-btn" 
                                                        title="Delete Source"
                                                        data-id="<?php echo $src['id']; ?>"
                                                        data-name="<?php echo sanitize($src['name']); ?>">
                                                    Delete
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
<div class="modal fade" id="addSourceModal" tabindex="-1" aria-labelledby="addSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-light py-2.5 px-3">
                <h6 class="modal-title font-heading fw-bold" id="addSourceModalLabel">Add Lead Source</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sources.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body p-3">
                    <label class="form-label-admin">Source Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control-admin" required placeholder="e.g. Facebook, Instagram, Flyer">
                </div>
                <div class="modal-footer p-2.5 bg-light">
                    <button type="button" class="btn btn-xs btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-xs btn-primary font-heading px-3">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editSourceModal" tabindex="-1" aria-labelledby="editSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content shadow border-0" style="border-radius: 12px;">
            <div class="modal-header bg-light py-2.5 px-3">
                <h6 class="modal-title font-heading fw-bold" id="editSourceModalLabel">Edit Lead Source</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sources.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body p-3">
                    <label class="form-label-admin">Source Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control-admin" required>
                </div>
                <div class="modal-footer p-2.5 bg-light">
                    <button type="button" class="btn btn-xs btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-xs btn-primary font-heading px-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteSourceForm" action="sources.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Click
    document.querySelectorAll('.edit-source-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            const myModal = new bootstrap.Modal(document.getElementById('editSourceModal'));
            myModal.show();
        });
    });

    // Delete Click
    document.querySelectorAll('.delete-source-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;

            Swal.fire({
                title: 'Delete Source?',
                text: `Are you sure you want to permanently delete lead channel "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteSourceForm').submit();
                }
            });
        });
    });
});
</script>

<?php
require_once '../../../includes/footer.php';
?>
