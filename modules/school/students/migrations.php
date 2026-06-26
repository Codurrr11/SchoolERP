<?php
// modules/school/students/migrations.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// AJAX endpoint: Get eligible students for migration
if (isset($_GET['get_eligible_students'])) {
    header('Content-Type: application/json');
    $session_id = intval($_GET['session_id']);
    $class_id = intval($_GET['class_id']);
    $section_id = intval($_GET['section_id']);

    $stmt = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.admission_no, s.roll_no, u.username as u_name
        FROM   students s
        JOIN   users u ON s.user_id = u.id
        WHERE  s.school_id = :school_id
          AND  s.session_id = :session_id
          AND  s.class_id = :class_id
          AND  s.section_id = :section_id
          AND  s.deleted_at IS NULL
          AND  s.status = 'active'
        ORDER  BY s.first_name ASC
    ");
    $stmt->execute([
        ':school_id' => $school_id,
        ':session_id' => $session_id,
        ':class_id' => $class_id,
        ':section_id' => $section_id
    ]);
    $students_list = $stmt->fetchAll();
    echo json_encode(['success' => true, 'students' => $students_list]);
    exit;
}

// AJAX endpoint: Get details of a previous migration
if (isset($_GET['get_migration_details']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $mid = intval($_GET['id']);

    $stmt = $pdo->prepare("SELECT * FROM student_migrations WHERE id = :id AND school_id = :school_id");
    $stmt->execute([':id' => $mid, ':school_id' => $school_id]);
    $migration = $stmt->fetch();

    if ($migration) {
        $student_ids = json_decode($migration['student_ids'], true);
        if (!empty($student_ids)) {
            $in_clause = implode(',', array_map('intval', $student_ids));
            $stmt_students = $pdo->query("
                SELECT s.first_name, s.last_name, s.admission_no, s.roll_no, u.username as u_name
                FROM   students s
                JOIN   users u ON s.user_id = u.id
                WHERE  s.id IN ($in_clause)
                ORDER  BY s.first_name ASC
            ");
            $students_list = $stmt_students->fetchAll();
            echo json_encode(['success' => true, 'data' => $migration, 'students' => $students_list]);
        } else {
            echo json_encode(['success' => true, 'data' => $migration, 'students' => []]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Migration batch not found.']);
    }
    exit;
}

// ─── POST ACTIONS HANDLING ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
        header('Location: migrations.php');
        exit;
    }

    // Add Migration batch
    if ($action === 'migrate') {
        $from_session_id = intval($_POST['from_session_id'] ?? 0);
        $from_class_id   = intval($_POST['from_class_id']   ?? 0);
        $from_section_id = intval($_POST['from_section_id'] ?? 0);
        
        $to_session_id   = intval($_POST['to_session_id']   ?? 0);
        $to_class_id     = intval($_POST['to_class_id']     ?? 0);
        $to_section_id   = intval($_POST['to_section_id']   ?? 0);

        $student_ids = $_POST['student_ids'] ?? [];

        if (empty($from_session_id) || empty($from_class_id) || empty($from_section_id) ||
            empty($to_session_id) || empty($to_class_id) || empty($to_section_id)) {
            $_SESSION['flash_error'] = "All Source and Target session, class, and section selections are required.";
            header('Location: migrations.php');
            exit;
        }

        if (empty($student_ids) || !is_array($student_ids)) {
            $_SESSION['flash_error'] = "Please select at least one student to migrate.";
            header('Location: migrations.php');
            exit;
        }

        $student_ids = array_map('intval', $student_ids);

        try {
            $pdo->beginTransaction();

            $total_students = count($student_ids);
            $student_ids_json = json_encode($student_ids);
            $migrated_by = ($_SESSION['first_name'] ?? 'Admin') . ' ' . ($_SESSION['last_name'] ?? '');

            // 1. Update students session, class, and section
            $in_clause = implode(',', $student_ids);
            $stmt_update = $pdo->prepare("
                UPDATE students 
                SET session_id = :to_session_id, 
                    class_id = :to_class_id, 
                    section_id = :to_section_id 
                WHERE id IN ($in_clause) AND school_id = :school_id
            ");
            $stmt_update->execute([
                ':to_session_id' => $to_session_id,
                ':to_class_id' => $to_class_id,
                ':to_section_id' => $to_section_id,
                ':school_id' => $school_id
            ]);

            // 2. Log in student_migrations
            $stmt_log = $pdo->prepare("
                INSERT INTO student_migrations (
                    school_id, from_session_id, to_session_id, 
                    from_class_id, to_class_id, from_section_id, to_section_id, 
                    total_students, student_ids, migrated_by
                ) VALUES (
                    :school_id, :from_session_id, :to_session_id,
                    :from_class_id, :to_class_id, :from_section_id, :to_section_id,
                    :total_students, :student_ids, :migrated_by
                )
            ");
            $stmt_log->execute([
                ':school_id'       => $school_id,
                ':from_session_id' => $from_session_id,
                ':to_session_id'   => $to_session_id,
                ':from_class_id'   => $from_class_id,
                ':to_class_id'     => $to_class_id,
                ':from_section_id' => $from_section_id,
                ':to_section_id'   => $to_section_id,
                ':total_students'  => $total_students,
                ':student_ids'     => $student_ids_json,
                ':migrated_by'     => $migrated_by
            ]);

            $pdo->commit();
            $_SESSION['flash_success'] = "Successfully migrated/promoted $total_students student(s) to the new session/class/section!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Migration failed: " . $e->getMessage();
        }
        header('Location: migrations.php');
        exit;
    }
}

// Fetch sessions, classes and sections for dropdowns
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = :school_id ORDER BY id DESC");
$stmt->execute([':school_id' => $school_id]);
$all_sessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = :school_id ORDER BY sort_order ASC");
$stmt->execute([':school_id' => $school_id]);
$all_classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM sections WHERE school_id = :school_id ORDER BY section_name ASC");
$stmt->execute([':school_id' => $school_id]);
$all_sections = $stmt->fetchAll();

$sections_by_class = [];
foreach ($all_sections as $s) {
    $sections_by_class[$s['class_id']][] = [
        'id' => $s['id'],
        'name' => $s['section_name']
    ];
}

// ─── QUERY MIGRATIONS LIST ──────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT sm.*, 
           fs.name as from_session, ts.name as to_session,
           fc.name as from_class, tc.name as to_class,
           fsec.name as from_section, tsec.name as to_section
    FROM   student_migrations sm
    LEFT JOIN academic_sessions fs ON sm.from_session_id = fs.id
    LEFT JOIN academic_sessions ts ON sm.to_session_id = ts.id
    LEFT JOIN classes fc ON sm.from_class_id = fc.id
    LEFT JOIN classes tc ON sm.to_class_id = tc.id
    LEFT JOIN sections fsec ON sm.from_section_id = fsec.id
    LEFT JOIN sections tsec ON sm.to_section_id = tsec.id
    WHERE  sm.school_id = :school_id
    ORDER  BY sm.id DESC
");
$stmt->execute([':school_id' => $school_id]);
$migrations = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">All Migrations</h2>
        <p class="text-xs text-muted mb-0">Track and manage student promotions and session migrations.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addMigrationModal">
            <i class="ph-light ph-plus fs-6"></i> Add Migration
        </button>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3 w-100 justify-content-between">
                    <div class="table-search-box m-0">
                        <i class="ph-light ph-magnifying-glass"></i>
                        <input type="text" placeholder="Search migrations..." id="migrationSearchInput" class="table-search-input">
                    </div>
                    <div class="teacher-total-badge">
                        <i class="ph-light ph-clock-counter-clockwise"></i>
                        Total Batches: <span class="count-num"><?php echo count($migrations); ?></span>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($migrations)): ?>
                        <div class="p-5 text-center">
                            <div class="trash-empty-icon mx-auto mb-3">
                                <i class="ph-light ph-arrows-merge"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No Migrations Logged</h5>
                            <p class="text-xs text-muted mb-0">No students have been migrated or promoted in this school yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0 align-middle" id="migrationsTable">
                            <thead>
                                <tr>
                                    <th class="th-w-50">#</th>
                                    <th>From Session</th>
                                    <th>To Session</th>
                                    <th>From Class</th>
                                    <th>From Section</th>
                                    <th>To Class</th>
                                    <th>To Section</th>
                                    <th>Total Students</th>
                                    <th>Migration Date</th>
                                    <th>Migrated By</th>
                                    <th class="th-w-80">Action</th>
                                </tr>
                            </thead>
                            <tbody id="migrationsTableBody">
                                <?php $idx = 1;
                                foreach ($migrations as $m): ?>
                                    <tr>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <td><span class="fw-semibold"><?php echo sanitize($m['from_session'] ?? '—'); ?></span></td>
                                        <td><span class="fw-semibold"><?php echo sanitize($m['to_session'] ?? '—'); ?></span></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary px-2 py-0.5 rounded text-xxs"><?php echo sanitize($m['from_class'] ?? '—'); ?></span></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary px-2 py-0.5 rounded text-xxs"><?php echo sanitize($m['from_section'] ?? '—'); ?></span></td>
                                        <td><span class="badge bg-primary-subtle text-primary px-2 py-0.5 rounded text-xxs"><?php echo sanitize($m['to_class'] ?? '—'); ?></span></td>
                                        <td><span class="badge bg-primary-subtle text-primary px-2 py-0.5 rounded text-xxs"><?php echo sanitize($m['to_section'] ?? '—'); ?></span></td>
                                        <td><span class="fw-bold text-success"><?php echo intval($m['total_students']); ?></span></td>
                                        <td><span class="text-xxs text-muted"><?php echo date('d-m-Y, h:i a', strtotime($m['created_at'])); ?></span></td>
                                        <td><span class="text-xs"><?php echo sanitize($m['migrated_by'] ?? '—'); ?></span></td>
                                        <td>
                                            <button type="button" class="teacher-action-btn action-view view-migration-btn" data-id="<?php echo $m['id']; ?>" title="View Migrated Students">
                                                <i class="ph-light ph-eye"></i>
                                            </button>
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

<!-- Modal: View Migrated Students -->
<div class="modal fade" id="viewMigrationModal" tabindex="-1" aria-labelledby="viewMigrationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="viewMigrationModalLabel">Migrated Students List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light text-xxs text-muted text-uppercase">
                            <tr>
                                <th class="th-w-50">#</th>
                                <th>Admission No.</th>
                                <th>Roll No.</th>
                                <th>Student Details</th>
                            </tr>
                        </thead>
                        <tbody id="migrated_students_tbody" class="text-xs">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Migration Promotion -->
<div class="modal fade" id="addMigrationModal" tabindex="-1" aria-labelledby="addMigrationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addMigrationModalLabel">Promote / Migrate Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="migrations.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="migrate">

                <div class="modal-body">
                    <!-- Source Selection -->
                    <h6 class="text-primary text-uppercase mb-3 font-semibold text-xs"><i class="ph-fill ph-sign-out"></i> SOURCE (Promoting From):</h6>
                    <div class="modal-section-card mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">From Session <span class="text-danger">*</span></label>
                                <select name="from_session_id" id="migrate_from_session" class="form-control-admin" required>
                                    <option value="">-- Select Session --</option>
                                    <?php foreach ($all_sessions as $ses): ?>
                                        <option value="<?php echo $ses['id']; ?>"><?php echo sanitize($ses['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">From Class <span class="text-danger">*</span></label>
                                <select name="from_class_id" id="migrate_from_class" class="form-control-admin" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($all_classes as $c):
                                        $c_secs = $sections_by_class[$c['id']] ?? [];
                                        $sec_data_attr = htmlspecialchars(json_encode($c_secs), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <option value="<?php echo $c['id']; ?>" data-sections="<?php echo $sec_data_attr; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">From Section <span class="text-danger">*</span></label>
                                <select name="from_section_id" id="migrate_from_section" class="form-control-admin" required>
                                    <option value="">-- Select Section --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Target Selection -->
                    <h6 class="text-success text-uppercase mb-3 font-semibold text-xs"><i class="ph-fill ph-sign-in"></i> TARGET (Promoting To):</h6>
                    <div class="modal-section-card mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-admin">To Session <span class="text-danger">*</span></label>
                                <select name="to_session_id" id="migrate_to_session" class="form-control-admin" required>
                                    <option value="">-- Select Session --</option>
                                    <?php foreach ($all_sessions as $ses): ?>
                                        <option value="<?php echo $ses['id']; ?>"><?php echo sanitize($ses['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">To Class <span class="text-danger">*</span></label>
                                <select name="to_class_id" id="migrate_to_class" class="form-control-admin" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($all_classes as $c):
                                        $c_secs = $sections_by_class[$c['id']] ?? [];
                                        $sec_data_attr = htmlspecialchars(json_encode($c_secs), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <option value="<?php echo $c['id']; ?>" data-sections="<?php echo $sec_data_attr; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-admin">To Section <span class="text-danger">*</span></label>
                                <select name="to_section_id" id="migrate_to_section" class="form-control-admin" required>
                                    <option value="">-- Select Section --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Students Checklist Selection -->
                    <h6 class="text-secondary text-uppercase mb-3 font-semibold text-xs d-flex align-items-center justify-content-between">
                        <span><i class="ph-fill ph-users"></i> SELECT STUDENTS TO PROMOTE:</span>
                        <span class="d-flex align-items-center gap-1 fw-normal text-none">
                            <input type="checkbox" class="form-check-input mt-0" id="selectAllMigrate" disabled>
                            <label class="form-check-label text-xxs cursor-pointer" for="selectAllMigrate">Select All</label>
                        </span>
                    </h6>
                    <div class="modal-section-card">
                        <div id="migrate_students_list_container" class="border rounded p-3 bg-light overflow-auto migrate-students-list-container">
                            <div class="text-xs text-muted text-center p-3">Select From Session, Class, and Section to load students.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Migrate / Promote Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="student-page-data" 
     data-csrf-token="<?php echo $csrf_token; ?>" 
     data-base-url="<?php echo BASE_URL; ?>"
     data-flash-success="<?php echo sanitize($flash_success); ?>"
     data-flash-error="<?php echo sanitize($flash_error); ?>">
</div>

<?php require_once '../../../includes/footer.php'; ?>
