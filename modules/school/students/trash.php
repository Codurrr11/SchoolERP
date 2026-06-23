<?php
// modules/school/students/trash.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

$csrf_token = generate_csrf_token();

// ─── POST ACTIONS HANDLING ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: trash.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ── Restore single student ───────────────────────────────────────────────
    if ($action === 'restore') {
        $student_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND school_id = :school_id AND deleted_at IS NOT NULL");
        $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
        $student = $stmt->fetch();

        if ($student) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE students SET deleted_at = NULL WHERE id = :id AND school_id = :school_id")
                    ->execute([':id' => $student_id, ':school_id' => $school_id]);
                $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = :id AND school_id = :school_id")
                    ->execute([':id' => $student['user_id'], ':school_id' => $school_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = "Student restored successfully and is now active.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to restore student: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }

    // ── Permanently delete single student ───────────────────────────────────
    if ($action === 'force_delete') {
        $student_id = intval($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND school_id = :school_id AND deleted_at IS NOT NULL");
        $stmt->execute([':id' => $student_id, ':school_id' => $school_id]);
        $student = $stmt->fetch();

        if ($student) {
            try {
                $pdo->beginTransaction();

                // Delete physical files
                $files = [$student['photo'], $student['dob_certificate']];
                foreach ($files as $f) {
                    if (!empty($f)) {
                        $full = 'c:/xampp/htdocs/schoolerp/' . $f;
                        if (file_exists($full)) @unlink($full);
                    }
                }

                // Remove DB records
                $pdo->prepare("DELETE FROM student_qualifications WHERE student_id = :id")->execute([':id' => $student_id]);
                $pdo->prepare("DELETE FROM student_attendance WHERE student_id = :id")->execute([':id' => $student_id]);
                $pdo->prepare("DELETE FROM students WHERE id = :id AND school_id = :school_id")->execute([':id' => $student_id, ':school_id' => $school_id]);
                $pdo->prepare("DELETE FROM users WHERE id = :id AND school_id = :school_id")
                    ->execute([':id' => $student['user_id'], ':school_id' => $school_id]);

                $pdo->commit();
                $_SESSION['flash_success'] = "Student permanently deleted.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Failed to permanently delete student: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }

    // ── Bulk restore ─────────────────────────────────────────────────────────
    if ($action === 'bulk_restore') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            try {
                $pdo->beginTransaction();
                $in = implode(',', $ids);

                $rows = $pdo->query("SELECT user_id FROM students WHERE id IN ($in) AND school_id = $school_id AND deleted_at IS NOT NULL")->fetchAll();
                $user_ids = array_column($rows, 'user_id');

                $pdo->exec("UPDATE students SET deleted_at = NULL WHERE id IN ($in) AND school_id = $school_id");
                if (!empty($user_ids)) {
                    $u_in = implode(',', $user_ids);
                    $pdo->exec("UPDATE users SET deleted_at = NULL WHERE id IN ($u_in) AND school_id = $school_id");
                }
                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " student(s) restored successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Bulk restore failed: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }

    // ── Bulk permanent delete ────────────────────────────────────────────────
    if ($action === 'bulk_force_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            try {
                $pdo->beginTransaction();
                $in = implode(',', $ids);

                $rows = $pdo->query("SELECT * FROM students WHERE id IN ($in) AND school_id = $school_id AND deleted_at IS NOT NULL")->fetchAll();
                $user_ids = [];
                foreach ($rows as $s) {
                    $user_ids[] = $s['user_id'];
                    foreach ([$s['photo'], $s['dob_certificate']] as $f) {
                        if (!empty($f)) {
                            $full = 'c:/xampp/htdocs/schoolerp/' . $f;
                            if (file_exists($full)) @unlink($full);
                        }
                    }
                }

                $pdo->exec("DELETE FROM student_qualifications WHERE student_id IN ($in)");
                $pdo->exec("DELETE FROM student_attendance WHERE student_id IN ($in)");
                $pdo->exec("DELETE FROM students WHERE id IN ($in) AND school_id = $school_id");
                if (!empty($user_ids)) {
                    $u_in = implode(',', $user_ids);
                    $pdo->exec("DELETE FROM users WHERE id IN ($u_in) AND school_id = $school_id");
                }
                $pdo->commit();
                $_SESSION['flash_success'] = count($ids) . " student(s) permanently deleted.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Bulk delete failed: " . $e->getMessage();
            }
        }
        header('Location: trash.php');
        exit;
    }
}

// ─── QUERY: Soft-deleted students ───────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.*, u.username AS u_name, c.name AS class_name, sec.name AS section_name
    FROM   students s
    JOIN   users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE  s.school_id = :school_id
      AND  s.deleted_at IS NOT NULL
    ORDER  BY s.deleted_at DESC
");
$stmt->execute([':school_id' => $school_id]);
$deleted_students = $stmt->fetchAll();

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- ─── PAGE HEADER ──────────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-12">
        <div class="d-flex align-items-center gap-3 mb-1">
            <a href="index.php" class="trash-back-btn" title="Back to Active Students">
                <i class="ph-light ph-arrow-left"></i>
            </a>
            <h2 class="mb-0 font-heading fw-extrabold">Student Trash Bin</h2>
            <?php if (!empty($deleted_students)): ?>
                <span class="trash-count-badge"><?php echo count($deleted_students); ?> deleted</span>
            <?php endif; ?>
        </div>
        <p class="text-xs text-muted mb-0 ms-5 ps-2">
            Soft-deleted student records are stored here. Restore them or permanently erase them from the system.
        </p>
    </div>
</div>

<!-- ─── TRASH TABLE ──────────────────────────────────────────────────────────── -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">

            <!-- Toolbar -->
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Back to Active Directory -->
                    <a href="index.php" class="teacher-header-btn btn-accent" title="Active Student Directory">
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
                        <input type="text" placeholder="Search deleted students..." id="trashSearchInput" style="height:34px;padding-top:0;padding-bottom:0;">
                    </div>
                    <!-- Count Badge -->
                    <div class="teacher-total-badge" style="background:rgba(239,68,68,0.07);border-color:rgba(239,68,68,0.18);color:#dc2626;">
                        <i class="ph-light ph-trash"></i>
                        Deleted: <span class="count-num"><?php echo count($deleted_students); ?></span>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($deleted_students)): ?>
                        <!-- Empty State -->
                        <div class="p-5 text-center">
                            <div class="trash-empty-icon mx-auto mb-3">
                                <i class="ph-light ph-check-circle"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">Trash is empty</h5>
                            <p class="text-xs text-muted mb-4">No deleted students found. Recovered students will be placed back into classes.</p>
                            <a href="index.php" class="btn-admin-action mx-auto">
                                <i class="ph-light ph-users-three"></i> Back to Directory
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Hidden bulk forms -->
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
                                    <th>Admission No.</th>
                                    <th>Roll No.</th>
                                    <th>Student Details</th>
                                    <th>Deleted On</th>
                                    <th style="width:130px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="trashTableBody">
                                <?php $idx = 1;
                                foreach ($deleted_students as $s): ?>
                                    <tr>
                                        <!-- Checkbox -->
                                        <td>
                                            <input type="checkbox" class="table-checkbox trash-select-checkbox" value="<?php echo $s['id']; ?>">
                                        </td>
                                        <!-- Counter -->
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <!-- Admission No -->
                                        <td><span class="fw-bold"><?php echo sanitize(($s['admission_no_prefix'] ?? '') . $s['admission_no']); ?></span></td>
                                        <!-- Roll No -->
                                        <td><span class="mono"><?php echo sanitize($s['roll_no'] ?? '—'); ?></span></td>
                                        <!-- Details -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($s['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . sanitize($s['photo']); ?>" alt="Profile" class="student-avatar" style="filter:grayscale(0.5);opacity:0.85;">
                                                <?php else: ?>
                                                    <div class="student-avatar-placeholder" style="opacity:0.7;">
                                                        <?php echo strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'] ?? '', 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column">
                                                    <span class="student-name-link" style="color:var(--color-text-secondary); pointer-events: none;">
                                                        <?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>
                                                    </span>
                                                    <span class="text-xs text-muted">Username: <strong><?php echo sanitize($s['u_name']); ?></strong></span>
                                                    <span class="text-xs text-muted">Classes: <strong><?php echo sanitize(($s['class_name'] ?? '') . '-' . ($s['section_name'] ?? '')); ?></strong></span>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Deleted On -->
                                        <td>
                                            <span class="trash-deleted-date">
                                                <i class="ph-light ph-clock"></i>
                                                <?php echo date('d M Y, h:i A', strtotime($s['deleted_at'])); ?>
                                            </span>
                                        </td>
                                        <!-- Actions -->
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <!-- Restore -->
                                                <form action="trash.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                    <button type="button"
                                                        class="teacher-action-btn action-restore confirm-restore-btn"
                                                        title="Restore Student">
                                                        <i class="ph-light ph-arrow-counter-clockwise"></i>
                                                    </button>
                                                </form>
                                                <!-- Permanent Delete -->
                                                <form action="trash.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="force_delete">
                                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                    <button type="button"
                                                        class="teacher-action-btn action-delete confirm-force-delete-btn"
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

<!-- Meta element for parameters passing to JS (no inline scripting) -->
<div id="student-page-data" 
     data-csrf-token="<?php echo $csrf_token; ?>" 
     data-base-url="<?php echo BASE_URL; ?>"
     data-flash-success="<?php echo sanitize($flash_success); ?>"
     data-flash-error="<?php echo sanitize($flash_error); ?>">
</div>


<?php require_once '../../../includes/footer.php'; ?>
