<?php
// modules/school/teachers/trash.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$csrf_token = generate_csrf_token();

// ─── POST ACTIONS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: trash.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ── Restore single teacher ────────────────────────────────────────────────
    if ($action === 'restore') {
        $teacher_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id AND school_id = :school_id AND deleted_at IS NOT NULL");
        $stmt->execute([':id' => $teacher_id, ':school_id' => $school_id]);
        $teacher = $stmt->fetch();

        if ($teacher) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE teachers SET deleted_at = NULL WHERE id = :id AND school_id = :school_id")
                    ->execute([':id' => $teacher_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = :id AND school_id = :school_id")
                    ->execute([':id' => $teacher['user_id'], ':school_id' => $school_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = "Teacher restored successfully and is now active.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to restore teacher: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }

    // ── Permanently delete single teacher ────────────────────────────────────
    if ($action === 'force_delete') {
        $teacher_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id AND school_id = :school_id AND deleted_at IS NOT NULL");
        $stmt->execute([':id' => $teacher_id, ':school_id' => $school_id]);
        $teacher = $stmt->fetch();

        if ($teacher) {
            try {
                $pdo->beginTransaction();

                // Delete physical files
                $files = [$teacher['photo'], $teacher['aadhar_file'], $teacher['signature_file']];
                foreach ($files as $f) {
                    if (!empty($f)) {
                        $full = 'c:/xampp/htdocs/schoolerp/' . $f;
                        if (file_exists($full)) @unlink($full);
                    }
                }

                // Remove DB records
                $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = :id")->execute([':id' => $teacher_id]);
                $pdo->prepare("DELETE FROM teachers WHERE id = :id AND school_id = :school_id")->execute([':id' => $teacher_id, ':school_id' => $school_id]);
                $pdo->prepare("DELETE FROM users WHERE id = :id AND school_id = :school_id")
                    ->execute([':id' => $teacher['user_id'], ':school_id' => $school_id]);

                $pdo->commit();
                $_SESSION['flash_success'] = "Teacher permanently deleted.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to permanently delete teacher: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }

    // ── Bulk restore ──────────────────────────────────────────────────────────
    if ($action === 'bulk_restore') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            try {
                $pdo->beginTransaction();
                $in = implode(',', $ids);
                $now = date('Y-m-d H:i:s');

                $rows = $pdo->query("SELECT user_id FROM teachers WHERE id IN ($in) AND school_id = $school_id AND deleted_at IS NOT NULL")->fetchAll();
                $user_ids = array_column($rows, 'user_id');

                $pdo->exec("UPDATE teachers SET deleted_at = NULL WHERE id IN ($in) AND school_id = $school_id");
                if (!empty($user_ids)) {
                    $u_in = implode(',', $user_ids);
                    $pdo->exec("UPDATE users SET deleted_at = NULL WHERE id IN ($u_in) AND school_id = $school_id");
                }
                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " teacher(s) restored successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Bulk restore failed: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }

    // ── Bulk permanent delete ─────────────────────────────────────────────────
    if ($action === 'bulk_force_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            try {
                $pdo->beginTransaction();
                $in = implode(',', $ids);

                $rows = $pdo->query("SELECT * FROM teachers WHERE id IN ($in) AND school_id = $school_id AND deleted_at IS NOT NULL")->fetchAll();
                $user_ids = [];
                foreach ($rows as $t) {
                    $user_ids[] = $t['user_id'];
                    foreach ([$t['photo'], $t['aadhar_file'], $t['signature_file']] as $f) {
                        if (!empty($f)) {
                            $full = 'c:/xampp/htdocs/schoolerp/' . $f;
                            if (file_exists($full)) @unlink($full);
                        }
                    }
                }

                $pdo->exec("DELETE FROM teacher_classes WHERE teacher_id IN ($in)");
                $pdo->exec("DELETE FROM teachers WHERE id IN ($in) AND school_id = $school_id");
                if (!empty($user_ids)) {
                    $u_in = implode(',', $user_ids);
                    $pdo->exec("DELETE FROM users WHERE id IN ($u_in) AND school_id = $school_id");
                }
                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " teacher(s) permanently deleted.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Bulk delete failed: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }
}

// ─── QUERY: Soft-deleted teachers ────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.*, u.username AS u_name
    FROM   teachers t
    JOIN   users u ON t.user_id = u.id
    WHERE  t.school_id = :school_id
      AND  t.deleted_at IS NOT NULL
    ORDER  BY t.deleted_at DESC
");
$stmt->execute([':school_id' => $school_id]);
$deleted_teachers = $stmt->fetchAll();

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- ─── PAGE HEADER ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <div class="d-flex align-items-center gap-3 mb-1">
            <a href="index.php" class="trash-back-btn" title="Back to Active Teachers">
                <i class="ph-light ph-arrow-left"></i>
            </a>
            <h2 class="mb-0 font-heading fw-extrabold">Trash Bin</h2>
            <?php if (!empty($deleted_teachers)): ?>
                <span class="trash-count-badge"><?php echo count($deleted_teachers); ?> deleted</span>
            <?php endif; ?>
        </div>
        <p class="text-xs text-muted mb-0 ms-5 ps-2">
            Soft-deleted teachers are stored here. Restore them or permanently remove them from the system.
        </p>
    </div>
</div>

<!-- Meta tag for JS parameter passing without inline scripting -->
<div id="teacher-page-data"
    data-csrf-token="<?php echo $csrf_token; ?>"
    data-base-url="<?php echo BASE_URL; ?>"
    data-flash-success="<?php echo htmlspecialchars($flash_success ?? ''); ?>"
    data-flash-error="<?php echo htmlspecialchars($flash_error ?? ''); ?>">
</div>

<!-- ─── TRASH TABLE ──────────────────────────────────────────────────────────── -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">

            <!-- Toolbar -->
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Back to Active Directory -->
                    <a href="index.php" class="teacher-header-btn btn-accent" title="Active Teacher Directory">
                        <i class="ph-light ph-users-three"></i>
                    </a>
                    <!-- Bulk Restore -->
                    <button type="button" class="teacher-header-btn btn-sky" id="bulkRestoreBtn" disabled title="Restore Selected">
                        <i class="ph-light ph-arrow-counter-clockwise"></i>
                    </button>
                    <!-- Bulk Permanent Delete -->
                    <button type="button" class="teacher-header-btn btn-red" id="bulkForceDeleteBtn" disabled title="Permanently Delete Selected">
                        <i class="ph-light ph-trash"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                    <!-- Search -->
                    <div class="table-search-box m-0">
                        <i class="ph-light ph-magnifying-glass"></i>
                        <input type="text" placeholder="Search deleted teachers..." id="trashSearchInput" style="height:34px;padding-top:0;padding-bottom:0;">
                    </div>
                    <!-- Count Badge (danger style) -->
                    <div class="teacher-total-badge" style="background:rgba(239,68,68,0.07);border-color:rgba(239,68,68,0.18);color:#dc2626;">
                        <i class="ph-light ph-trash"></i>
                        Deleted: <span class="count-num"><?php echo count($deleted_teachers); ?></span>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($deleted_teachers)): ?>
                        <!-- Empty State -->
                        <div class="p-5 text-center">
                            <div class="trash-empty-icon mx-auto mb-3">
                                <i class="ph-light ph-check-circle"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">Trash is empty</h5>
                            <p class="text-xs text-muted mb-4">No deleted teachers found. When you delete a teacher, they will appear here for recovery.</p>
                            <a href="index.php" class="btn-admin-action mx-auto">
                                <i class="ph-light ph-users-three"></i> Back to Teachers
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Hidden bulk-action forms -->
                        <form id="bulkRestoreForm" action="trash.php" method="POST" style="display:none;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="bulk_restore">
                            <div id="bulkRestoreIds"></div>
                        </form>
                        <form id="bulkForceDeleteForm" action="trash.php" method="POST" style="display:none;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="bulk_force_delete">
                            <div id="bulkForceDeleteIds"></div>
                        </form>

                        <table class="teacher-table table-premium mb-0 align-middle" id="trashTable">
                            <thead>
                                <tr>
                                    <th style="width:46px;">
                                        <input type="checkbox" class="table-checkbox" id="selectAllTrash">
                                    </th>
                                    <th style="width:50px;">#</th>
                                    <th>Profile</th>
                                    <th>Name &amp; Contact</th>
                                    <th>Username</th>
                                    <th>Department</th>
                                    <th>Deleted On</th>
                                    <th style="width:130px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="trashTableBody">
                                <?php $idx = 1;
                                foreach ($deleted_teachers as $t): ?>
                                    <tr data-search="<?php echo strtolower(htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' ' . $t['email'] . ' ' . $t['u_name'])); ?>">
                                        <!-- Checkbox -->
                                        <td>
                                            <input type="checkbox" class="table-checkbox trash-select-checkbox" value="<?php echo $t['id']; ?>">
                                        </td>
                                        <!-- Counter -->
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <!-- Avatar -->
                                        <td>
                                            <?php if (!empty($t['photo'])): ?>
                                                <img src="<?php echo BASE_URL . sanitize($t['photo']); ?>" alt="Profile" class="teacher-avatar" style="filter:grayscale(0.5);opacity:0.85;">
                                            <?php else: ?>
                                                <div class="teacher-avatar-placeholder" style="opacity:0.7;">
                                                    <?php echo strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Name & Contact -->
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="teacher-name-link" style="color:var(--color-text-secondary);">
                                                    <?php echo sanitize($t['first_name'] . ' ' . $t['last_name']); ?>
                                                </span>
                                                <span class="teacher-detail-email">Email: <?php echo sanitize($t['email']); ?></span>
                                                <?php if (!empty($t['mobile_no'])): ?>
                                                    <span class="teacher-detail-mobile">Mobile: <?php echo sanitize($t['mobile_no']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <!-- Username -->
                                        <td><span class="teacher-username"><?php echo sanitize($t['u_name']); ?></span></td>
                                        <!-- Department -->
                                        <td>
                                            <?php if (!empty($t['department'])): ?>
                                                <span class="class-pill class-pill-secondary"><?php echo sanitize($t['department']); ?></span>
                                            <?php else: ?>
                                                <span style="color:#cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Deleted On -->
                                        <td>
                                            <span class="trash-deleted-date">
                                                <i class="ph-light ph-clock"></i>
                                                <?php echo date('d M Y, h:i A', strtotime($t['deleted_at'])); ?>
                                            </span>
                                        </td>
                                        <!-- Actions -->
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <!-- Restore -->
                                                <form action="trash.php" method="POST" class="d-inline trash-restore-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                    <button type="button"
                                                        class="teacher-action-btn action-restore confirm-restore-teacher-btn"
                                                        title="Restore Teacher">
                                                        <i class="ph-light ph-arrow-counter-clockwise"></i>
                                                    </button>
                                                </form>
                                                <!-- Permanent Delete -->
                                                <form action="trash.php" method="POST" class="d-inline trash-force-delete-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="force_delete">
                                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                    <button type="button"
                                                        class="teacher-action-btn action-delete confirm-force-delete-teacher-btn"
                                                        title="Permanently Delete">
                                                        <i class="ph-light ph-trash"></i>
                                                    </button>
                                                </form>
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



<?php require_once '../../../includes/footer.php'; ?>
