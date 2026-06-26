<?php
// modules/school/admissions/index.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']); // Only school admins
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// Fetch all active students in this school
$stmt = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.father_name, s.admission_no, s.admission_no_prefix, s.roll_no,
           c.name as class_name, sec.name as section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.school_id = :school_id AND s.status = 'active'
    ORDER BY s.id DESC
");
$stmt->execute([':school_id' => $school_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../../includes/header.php';
?>

<!-- Admissions Module Container (JS binds here via ID and data-students) -->
<div id="admissions-module-container" data-students="<?php echo htmlspecialchars(json_encode($students), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ── Page Heading ──────────────────────────────────────────────────────────── -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12">
            <h2 class="mb-1 font-heading fw-extrabold text-dark">All Admission Forms</h2>
            <p class="text-xs text-muted mb-0">
                Generate, download and print student admission forms based on active field settings.
            </p>
        </div>
    </div>

    <!-- ── Datatables Filter Box & Search Card ────────────────────────────────────── -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card-premium">
                <!-- Toolbar matching school directory style -->
                <div class="teacher-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="settings.php" class="teacher-header-btn btn-sky" title="Configure Print Fields">
                            <i class="ph-bold ph-gear"></i>
                        </a>
                    </div>

                    <div class="d-flex align-items-center gap-3 w-100 w-sm-auto ms-auto justify-content-end">
                        <div class="table-search-box m-0">
                            <i class="ph-light ph-magnifying-glass"></i>
                            <input type="search" id="admissionsSearchInput" placeholder="Search students...">
                        </div>
                        <div class="d-flex align-items-center gap-1 teacher-length-select">
                            Show
                            <select id="admissionsLengthSelect" class="length-select-inner">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="teacher-total-badge" id="admissionsTotalBadge">
                            <i class="ph-light ph-users-three"></i>
                            Total: <span class="count-num">0</span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <!-- Responsive Table -->
                    <div class="table-responsive">
                        <table id="admissionsTable" class="teacher-detail-table w-100">
                            <thead>
                                <tr>
                                    <th class="th-w-60">#</th>
                                    <th class="th-w-140">Admission No.</th>
                                    <th class="th-w-100">Roll No.</th>
                                    <th>Student Name</th>
                                    <th>Father Name</th>
                                    <th class="th-w-110">Class</th>
                                    <th class="th-w-90">Section</th>
                                    <th class="th-w-120 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs font-primary">
                                <!-- Populated dynamically by javascript (app.js) -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Datatable Pagination Footer -->
                    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap g-3 font-primary text-xs text-secondary fw-medium">
                        <div id="admissionsInfo">
                            Showing 0 to 0 of 0 entries
                        </div>
                        <div class="d-flex gap-1" id="admissionsPagination">
                            <!-- Populated dynamically by javascript (app.js) -->
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<?php
require_once '../../../includes/footer.php';
?>
