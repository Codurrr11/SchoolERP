<?php
require_once '../../config/helpers.php';
auth_check(['super_admin']); // Only super admin

// Fetch all registered schools with their admin user details
require_once '../../config/db.php';
$stmt = $pdo->query("
    SELECT s.*,
           u.first_name AS admin_first,
           u.last_name  AS admin_last,
           u.email      AS admin_email
    FROM   schools s
    LEFT JOIN users u ON u.school_id = s.id AND u.role_id = 2 AND u.deleted_at IS NULL
    WHERE  s.deleted_at IS NULL
    ORDER  BY s.id DESC
");
$schools = $stmt->fetchAll();

// Flash message from redirect
$registered = isset($_GET['registered']) && $_GET['registered'] == 1;
$deleted = isset($_GET['deleted']) && $_GET['deleted'] == 1;
$delete_error = isset($_GET['delete_error']) && $_GET['delete_error'] == 1;
$invalid_request = isset($_GET['invalid_request']) && $_GET['invalid_request'] == 1;

require_once '../../includes/header.php';
?>

<!-- Page Header -->
<div class="row align-items-center mb-4 g-3"
     id="schools-list-container"
     data-registered="<?php echo $registered ? '1' : '0'; ?>"
     data-deleted="<?php echo $deleted ? '1' : '0'; ?>"
     data-delete-error="<?php echo $delete_error ? '1' : '0'; ?>"
     data-invalid-request="<?php echo $invalid_request ? '1' : '0'; ?>">
    <div class="col-sm-6">
        <h2 class="mb-1 font-heading fw-extrabold">School Management</h2>
        <p class="text-xs text-muted mb-0">Register and manage academic institutions on the platform.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <a href="schools-edit.php" class="btn-admin-action">
            <i class="ti ti-plus"></i> Register School
        </a>
    </div>
</div>



<!-- Schools Table Card -->
<div class="row g-4">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-header">
                <h6>All Registered Schools</h6>
                <div class="table-header-controls">
                    <div class="table-search-box">
                        <i class="ti ti-search"></i>
                        <input type="text" placeholder="Search schools..." id="schoolSearchInput">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($schools)): ?>
                        <!-- Empty State -->
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ti ti-building"></i>
                            </div>
                            <h5 class="fw-semibold mt-3">No schools registered yet</h5>
                            <p class="text-xs text-muted mb-4">Initialize the first tenant school on the platform to get started.</p>
                            <a href="schools-edit.php" class="btn-admin-action">
                                <i class="ti ti-plus"></i> Register Your First School
                            </a>
                        </div>
                    <?php else: ?>
                        <table class="table-premium mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>School Name</th>
                                    <th>Admin Name</th>
                                    <th>Admin Login Email</th>
                                    <th>School Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th class="col-action-width">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schools as $school): ?>
                                    <tr>
                                        <!-- School Name -->
                                        <td class="fw-semibold">
                                            <div class="activity-cell">
                                                <div class="icon-circle-sm activity-icon-blue">
                                                    <i class="ti ti-building-bank"></i>
                                                </div>
                                                <span><?php echo sanitize($school['name']); ?></span>
                                            </div>
                                        </td>

                                        <!-- Admin Name -->
                                        <td class="text-xs">
                                            <?php echo $school['admin_first']
                                                ? sanitize($school['admin_first'] . ' ' . $school['admin_last'])
                                                : '<span class="text-muted">—</span>'; ?>
                                        </td>

                                        <!-- Admin Login Email -->
                                        <td class="text-xs">
                                            <?php echo $school['admin_email']
                                                ? sanitize($school['admin_email'])
                                                : '<span class="text-muted">—</span>'; ?>
                                        </td>

                                        <!-- School Contact Email -->
                                        <td class="text-xs text-muted"><?php echo sanitize($school['email']); ?></td>

                                        <!-- Phone -->
                                        <td class="text-xs"><?php echo sanitize($school['phone'] ?: '—'); ?></td>

                                        <!-- Status -->
                                        <td>
                                            <?php if ($school['status'] === 'active'): ?>
                                                <span class="status-pill status-completed text-xs">
                                                    <span class="status-dot"></span>Active
                                                </span>
                                            <?php elseif ($school['status'] === 'suspended'): ?>
                                                <span class="status-pill status-pending text-xs">
                                                    <span class="status-dot"></span>Suspended
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill status-inprogress text-xs">
                                                    <span class="status-dot"></span>Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Edit -->
                                        <td>
                                            <a href="schools-edit.php?id=<?php echo $school['id']; ?>"
                                               class="btn-row-action d-inline-flex align-items-center justify-content-center"
                                               title="Edit School">
                                                <i class="ti ti-pencil fs-5"></i>
                                            </a>
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

<?php
require_once '../../includes/footer.php';
?>
