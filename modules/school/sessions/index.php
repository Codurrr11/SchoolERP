<?php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Handle Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: index.php');
        exit;
    }

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        // Validations
        if (empty($name) || empty($start_date) || empty($end_date)) {
            $_SESSION['flash_error'] = "All fields are required.";
            header('Location: index.php');
            exit;
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            $_SESSION['flash_error'] = "End Date must be after Start Date.";
            header('Location: index.php');
            exit;
        }

        // Unique Name Check for this school
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM academic_sessions WHERE school_id = :school_id AND name = :name");
        $stmt->execute([':school_id' => $school_id, ':name' => $name]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "An academic session with this name already exists.";
            header('Location: index.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            if ($is_current === 1) {
                // Reset all other sessions as not current
                $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 0 WHERE school_id = :school_id");
                $stmt->execute([':school_id' => $school_id]);
            }

            // Insert new session
            $stmt = $pdo->prepare("
                INSERT INTO academic_sessions (school_id, name, start_date, end_date, is_current)
                VALUES (:school_id, :name, :start_date, :end_date, :is_current)
            ");
            $stmt->execute([
                ':school_id' => $school_id,
                ':name' => $name,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':is_current' => $is_current
            ]);
            
            $new_session_id = $pdo->lastInsertId();

            if ($is_current === 1) {
                $_SESSION['academic_session_id'] = $new_session_id;
                $_SESSION['academic_session_name'] = $name;
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Academic Session created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to create academic session: " . $e->getMessage();
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        // Verify session belongs to school
        $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $_SESSION['flash_error'] = "Session not found.";
            header('Location: index.php');
            exit;
        }

        // Validations
        if (empty($name) || empty($start_date) || empty($end_date)) {
            $_SESSION['flash_error'] = "All fields are required.";
            header('Location: index.php');
            exit;
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            $_SESSION['flash_error'] = "End Date must be after Start Date.";
            header('Location: index.php');
            exit;
        }

        // Unique Check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM academic_sessions WHERE school_id = :school_id AND name = :name AND id != :id");
        $stmt->execute([':school_id' => $school_id, ':name' => $name, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['flash_error'] = "An academic session with this name already exists.";
            header('Location: index.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            if ($is_current === 1) {
                // Reset other sessions as not current
                $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 0 WHERE school_id = :school_id");
                $stmt->execute([':school_id' => $school_id]);
            }

            // Update session
            $stmt = $pdo->prepare("
                UPDATE academic_sessions 
                SET    name = :name, start_date = :start_date, end_date = :end_date, is_current = :is_current
                WHERE  school_id = :school_id AND id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':is_current' => $is_current,
                ':school_id' => $school_id,
                ':id' => $id
            ]);

            // Sync $_SESSION values if editing the active session
            if ($is_current === 1 || $_SESSION['academic_session_id'] == $id) {
                if ($is_current === 1) {
                    $_SESSION['academic_session_id'] = $id;
                    $_SESSION['academic_session_name'] = $name;
                } else {
                    // It was the active session, but is_current was unticked. Clear it.
                    $_SESSION['academic_session_id'] = null;
                    $_SESSION['academic_session_name'] = 'No Session';
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Academic Session updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to update academic session: " . $e->getMessage();
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'make_current') {
        $id = intval($_POST['id'] ?? 0);

        // Verify session belongs to school
        $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        $sess = $stmt->fetch();

        if (!$sess) {
            $_SESSION['flash_error'] = "Session not found.";
            header('Location: index.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Set all to 0
            $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 0 WHERE school_id = :school_id");
            $stmt->execute([':school_id' => $school_id]);

            // Set target to 1
            $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 1 WHERE school_id = :school_id AND id = :id");
            $stmt->execute([':school_id' => $school_id, ':id' => $id]);

            // Sync session
            $_SESSION['academic_session_id'] = $sess['id'];
            $_SESSION['academic_session_name'] = $sess['name'];

            $pdo->commit();
            $_SESSION['flash_success'] = "Session '{$sess['name']}' is now the current active session.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Failed to change active session: " . $e->getMessage();
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // Verify session belongs to school
        $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id AND id = :id");
        $stmt->execute([':school_id' => $school_id, ':id' => $id]);
        $sess = $stmt->fetch();

        if (!$sess) {
            $_SESSION['flash_error'] = "Session not found.";
            header('Location: index.php');
            exit;
        }

        if ($sess['is_current'] == 1) {
            $_SESSION['flash_error'] = "You cannot delete the current active session. Switch to another active session first.";
            header('Location: index.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM academic_sessions WHERE school_id = :school_id AND id = :id");
            $stmt->execute([':school_id' => $school_id, ':id' => $id]);

            // Clear session variables if this was cached as the selected session (even if not marked as is_current)
            if (isset($_SESSION['academic_session_id']) && $_SESSION['academic_session_id'] == $id) {
                unset($_SESSION['academic_session_id'], $_SESSION['academic_session_name']);
            }

            $_SESSION['flash_success'] = "Academic Session deleted successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to delete academic session (it may have active dependancies).";
        }

        header('Location: index.php');
        exit;
    }
}

// Fetch Sessions
$sessions = get_academic_sessions($school_id);
$csrf_token = generate_csrf_token();

// Pull flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- Page Header -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">Academic Sessions</h2>
        <p class="text-xs text-muted mb-0">Configure and manage academic years for student enrollment and planning.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <button type="button" class="btn-admin-action" data-bs-toggle="modal" data-bs-target="#addSessionModal">
            <i class="ph-bold ph-plus"></i> Add Session
        </button>
    </div>
</div>

<!-- Success/Error Alerts -->
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
        title: 'Validation Error',
        text: <?php echo json_encode($flash_error); ?>,
        confirmButtonColor: '#6366f1',
        customClass: {
            confirmButton: 'swal-btn-custom'
        }
    });
});
</script>
<?php endif; ?>

<!-- Sessions Table Card -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-header">
                <h6>All Academic Sessions</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($sessions)): ?>
                        <!-- Empty State -->
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-calendar"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No academic sessions found</h5>
                            <p class="text-xs text-muted mb-4">Initialize your first academic year (e.g. 2026-27) to get started.</p>
                            <button type="button" class="btn-admin-action" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                                <i class="ph-bold ph-plus"></i> Add Session
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Session Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th class="col-action-width">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $sess): ?>
                                    <tr>
                                        <!-- Session Name -->
                                        <td class="fw-semibold">
                                            <div class="activity-cell">
                                                <div class="icon-circle-sm <?php echo $sess['is_current'] ? 'activity-icon-blue' : 'activity-icon-indigo'; ?>">
                                                    <i class="ph-light ph-calendar-blank"></i>
                                                </div>
                                                <span><?php echo sanitize($sess['name']); ?></span>
                                            </div>
                                        </td>

                                        <!-- Start Date -->
                                        <td class="text-xs">
                                            <?php echo date('M d, Y', strtotime($sess['start_date'])); ?>
                                        </td>

                                        <!-- End Date -->
                                        <td class="text-xs">
                                            <?php echo date('M d, Y', strtotime($sess['end_date'])); ?>
                                        </td>

                                        <!-- Status Badge -->
                                        <td>
                                            <?php if ($sess['is_current']): ?>
                                                <span class="badge bg-success-light text-success text-xxs py-1.5 px-2.5 rounded-pill fw-semibold">
                                                    <i class="ph-fill ph-circle text-success me-1"></i> Current Session
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted text-xxs py-1.5 px-2.5 rounded-pill fw-semibold">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!$sess['is_current']): ?>
                                                    <!-- Make Current Action -->
                                                    <form action="index.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="make_current">
                                                        <input type="hidden" name="id" value="<?php echo $sess['id']; ?>">
                                                        <button type="submit" class="btn btn-xs btn-outline-primary py-1 px-2 text-xxs font-heading" title="Set as Current">
                                                            Make Active
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Edit Action Button -->
                                                <button type="button" 
                                                        class="btn-admin-action-square text-primary edit-session-btn" 
                                                        title="Edit Session"
                                                        data-id="<?php echo $sess['id']; ?>"
                                                        data-name="<?php echo sanitize($sess['name']); ?>"
                                                        data-start="<?php echo $sess['start_date']; ?>"
                                                        data-end="<?php echo $sess['end_date']; ?>"
                                                        data-current="<?php echo $sess['is_current']; ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editSessionModal">
                                                    <i class="ph-light ph-pencil"></i>
                                                </button>

                                                <!-- Delete Action Button -->
                                                <?php if (!$sess['is_current']): ?>
                                                    <button type="button" 
                                                            class="btn-admin-action-square text-danger delete-session-btn" 
                                                            title="Delete Session"
                                                            data-id="<?php echo $sess['id']; ?>"
                                                            data-name="<?php echo sanitize($sess['name']); ?>">
                                                        <i class="ph-light ph-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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

<!-- ─── ADD SESSION MODAL ────────────────────────────────────────────────── -->
<div class="modal fade" id="addSessionModal" tabindex="-1" aria-labelledby="addSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: var(--border-radius-lg);">
            <div class="modal-header border-0 bg-light py-3 px-4" style="border-top-left-radius: var(--border-radius-lg); border-top-right-radius: var(--border-radius-lg);">
                <h6 class="modal-title font-heading fw-bold" id="addSessionModalLabel">Add Academic Session</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="add_name" class="form-label-admin">Session Name <span class="text-danger">*</span></label>
                            <input type="text" id="add_name" name="name" class="form-control-admin" required placeholder="e.g. 2026-27">
                            <span class="text-xxs text-muted d-block mt-1">Use a clear format like YYYY-YY or YYYY-YYYY.</span>
                        </div>
                        <div class="col-sm-6">
                            <label for="add_start_date" class="form-label-admin">Start Date <span class="text-danger">*</span></label>
                            <input type="date" id="add_start_date" name="start_date" class="form-control-admin" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="add_end_date" class="form-label-admin">End Date <span class="text-danger">*</span></label>
                            <input type="date" id="add_end_date" name="end_date" class="form-control-admin" required>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current" id="add_is_current" value="1">
                                <label class="form-check-label text-xs fw-semibold" for="add_is_current">
                                    Set as Current Active Session
                                </label>
                            </div>
                            <span class="text-xxs text-muted d-block mt-1">Checking this will automatically deactivate all other sessions.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light" style="border-bottom-left-radius: var(--border-radius-lg); border-bottom-right-radius: var(--border-radius-lg);">
                    <button type="button" class="btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-action">Create Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─── EDIT SESSION MODAL ────────────────────────────────────────────────── -->
<div class="modal fade" id="editSessionModal" tabindex="-1" aria-labelledby="editSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: var(--border-radius-lg);">
            <div class="modal-header border-0 bg-light py-3 px-4" style="border-top-left-radius: var(--border-radius-lg); border-top-right-radius: var(--border-radius-lg);">
                <h6 class="modal-title font-heading fw-bold" id="editSessionModalLabel">Edit Academic Session</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="edit_name" class="form-label-admin">Session Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_name" name="name" class="form-control-admin" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="edit_start_date" class="form-label-admin">Start Date <span class="text-danger">*</span></label>
                            <input type="date" id="edit_start_date" name="start_date" class="form-control-admin" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="edit_end_date" class="form-label-admin">End Date <span class="text-danger">*</span></label>
                            <input type="date" id="edit_end_date" name="end_date" class="form-control-admin" required>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current" id="edit_is_current" value="1">
                                <label class="form-check-label text-xs fw-semibold" for="edit_is_current">
                                    Set as Current Active Session
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light" style="border-bottom-left-radius: var(--border-radius-lg); border-bottom-right-radius: var(--border-radius-lg);">
                    <button type="button" class="btn-admin-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-admin-action">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteSessionForm" action="index.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Button Handler: Populate fields
    const editButtons = document.querySelectorAll('.edit-session-btn');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_start_date').value = this.dataset.start;
            document.getElementById('edit_end_date').value = this.dataset.end;
            
            const isCurrent = parseInt(this.dataset.current) === 1;
            const checkbox = document.getElementById('edit_is_current');
            checkbox.checked = isCurrent;
            
            // If already current, prevent user from unticking it to avoid empty active state
            if (isCurrent) {
                checkbox.setAttribute('disabled', 'disabled');
                // Insert a hidden field to preserve value because disabled checkbox doesn't send in POST
                let hiddenInput = document.getElementById('hidden_edit_is_current');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'is_current';
                    hiddenInput.id = 'hidden_edit_is_current';
                    hiddenInput.value = '1';
                    checkbox.parentNode.appendChild(hiddenInput);
                }
            } else {
                checkbox.removeAttribute('disabled');
                const hiddenInput = document.getElementById('hidden_edit_is_current');
                if (hiddenInput) {
                    hiddenInput.remove();
                }
            }
        });
    });

    // Delete confirmation with SweetAlert2
    const deleteButtons = document.querySelectorAll('.delete-session-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;

            Swal.fire({
                title: 'Delete Session?',
                text: `Are you sure you want to permanently delete the academic session "${name}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, Delete it!',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'swal-danger-btn-custom',
                    cancelButton: 'swal-cancel-btn-custom'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteSessionForm').submit();
                }
            });
        });
    });
});
</script>

<?php
require_once '../../../includes/footer.php';
?>
