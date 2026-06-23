<?php
// modules/school/expenses/index.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();

require_once '../../../config/db.php';

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: Get single expense for view / edit
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['get_expense'], $_GET['id'])) {
    header('Content-Type: application/json');
    $id   = intval($_GET['id']);
    $stmt = $pdo->prepare("
        SELECT e.*, u.username AS created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = :id AND e.school_id = :school_id AND e.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $id, ':school_id' => $school_id]);
    $expense = $stmt->fetch();
    echo $expense
        ? json_encode(['success' => true,  'expense' => $expense])
        : json_encode(['success' => false, 'message' => 'Expense not found.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: Add Expense
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.';
        header('Location: index.php'); exit;
    }

    $file_paths = [];
    if (!empty($_FILES['screenshots']['name'][0])) {
        $allowed = ['jpg','jpeg','png','webp','pdf'];
        $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/expenses/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
            if ($_FILES['screenshots']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['screenshots']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed) || $_FILES['screenshots']['size'][$i] > 5 * 1024 * 1024) continue;
            $fname = uniqid('exp_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                $file_paths[] = 'uploads/expenses/' . $fname;
            }
        }
    }

    $expense_date = str_replace('T', ' ', $_POST['expense_date'] ?? date('Y-m-d H:i'));
    if (strlen($expense_date) === 16) $expense_date .= ':00';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO expenses (
                school_id, expense_type, amount, payment_mode, payment_account,
                paid_by, paid_to, narration, payment_txn_id, expense_date,
                voucher_no, utr_reference_no, prepared_by, approved_by, received_by,
                expense_details, files, created_by, created_at
            ) VALUES (
                :school_id, :expense_type, :amount, :payment_mode, :payment_account,
                :paid_by, :paid_to, :narration, :payment_txn_id, :expense_date,
                :voucher_no, :utr_reference_no, :prepared_by, :approved_by, :received_by,
                :expense_details, :files, :created_by, NOW()
            )
        ");
        $stmt->execute([
            ':school_id'        => $school_id,
            ':expense_type'     => trim($_POST['expense_type']     ?? ''),
            ':amount'           => floatval($_POST['amount']        ?? 0),
            ':payment_mode'     => trim($_POST['payment_mode']      ?? ''),
            ':payment_account'  => trim($_POST['payment_account']   ?? ''),
            ':paid_by'          => trim($_POST['paid_by']           ?? ''),
            ':paid_to'          => trim($_POST['paid_to']           ?? ''),
            ':narration'        => trim($_POST['narration']         ?? ''),
            ':payment_txn_id'   => trim($_POST['payment_txn_id']   ?? '') ?: null,
            ':expense_date'     => $expense_date,
            ':voucher_no'       => trim($_POST['voucher_no']        ?? '') ?: null,
            ':utr_reference_no' => trim($_POST['utr_reference_no'] ?? '') ?: null,
            ':prepared_by'      => trim($_POST['prepared_by']       ?? '') ?: null,
            ':approved_by'      => trim($_POST['approved_by']       ?? '') ?: null,
            ':received_by'      => trim($_POST['received_by']       ?? '') ?: null,
            ':expense_details'  => trim($_POST['expense_details']   ?? '') ?: null,
            ':files'            => $file_paths ? json_encode($file_paths) : null,
            ':created_by'       => $_SESSION['user_id'] ?? null,
        ]);
        $pdo->commit();
        $_SESSION['flash_success'] = 'Expense added successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Failed to add expense: ' . $e->getMessage();
    }
    header('Location: index.php'); exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: Edit Expense
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_expense') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.';
        header('Location: index.php'); exit;
    }

    $id = intval($_POST['expense_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, files FROM expenses WHERE id = :id AND school_id = :school_id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id, ':school_id' => $school_id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $_SESSION['flash_error'] = 'Expense not found.'; header('Location: index.php'); exit;
    }

    $file_paths = !empty($existing['files']) ? json_decode($existing['files'], true) : [];
    if (!empty($_FILES['screenshots']['name'][0])) {
        $allowed = ['jpg','jpeg','png','webp','pdf'];
        $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/expenses/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
            if ($_FILES['screenshots']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['screenshots']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed) || $_FILES['screenshots']['size'][$i] > 5 * 1024 * 1024) continue;
            $fname = uniqid('exp_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $upload_dir . $fname)) $file_paths[] = 'uploads/expenses/' . $fname;
        }
    }

    $expense_date = str_replace('T', ' ', $_POST['expense_date'] ?? date('Y-m-d H:i'));
    if (strlen($expense_date) === 16) $expense_date .= ':00';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE expenses SET
                expense_type     = :expense_type,  amount           = :amount,
                payment_mode     = :payment_mode,  payment_account  = :payment_account,
                paid_by          = :paid_by,        paid_to          = :paid_to,
                narration        = :narration,      payment_txn_id   = :payment_txn_id,
                expense_date     = :expense_date,   voucher_no       = :voucher_no,
                utr_reference_no = :utr_reference_no,
                prepared_by      = :prepared_by,   approved_by      = :approved_by,
                received_by      = :received_by,   expense_details  = :expense_details,
                files            = :files,          updated_at       = NOW()
            WHERE id = :id AND school_id = :school_id
        ");
        $stmt->execute([
            ':expense_type'     => trim($_POST['expense_type']     ?? ''),
            ':amount'           => floatval($_POST['amount']        ?? 0),
            ':payment_mode'     => trim($_POST['payment_mode']      ?? ''),
            ':payment_account'  => trim($_POST['payment_account']   ?? ''),
            ':paid_by'          => trim($_POST['paid_by']           ?? ''),
            ':paid_to'          => trim($_POST['paid_to']           ?? ''),
            ':narration'        => trim($_POST['narration']         ?? ''),
            ':payment_txn_id'   => trim($_POST['payment_txn_id']   ?? '') ?: null,
            ':expense_date'     => $expense_date,
            ':voucher_no'       => trim($_POST['voucher_no']        ?? '') ?: null,
            ':utr_reference_no' => trim($_POST['utr_reference_no'] ?? '') ?: null,
            ':prepared_by'      => trim($_POST['prepared_by']       ?? '') ?: null,
            ':approved_by'      => trim($_POST['approved_by']       ?? '') ?: null,
            ':received_by'      => trim($_POST['received_by']       ?? '') ?: null,
            ':expense_details'  => trim($_POST['expense_details']   ?? '') ?: null,
            ':files'            => $file_paths ? json_encode($file_paths) : null,
            ':id'               => $id,
            ':school_id'        => $school_id,
        ]);
        $pdo->commit();
        $_SESSION['flash_success'] = 'Expense updated successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Failed to update expense: ' . $e->getMessage();
    }
    header('Location: index.php'); exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: Soft-delete Expense
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.'; header('Location: index.php'); exit;
    }
    $id = intval($_POST['expense_id'] ?? 0);
    $pdo->prepare("UPDATE expenses SET deleted_at = NOW() WHERE id = :id AND school_id = :school_id")
        ->execute([':id' => $id, ':school_id' => $school_id]);
    $_SESSION['flash_success'] = 'Expense deleted.';
    header('Location: index.php'); exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Data for page
// ─────────────────────────────────────────────────────────────────────────────
$stmt_cats = $pdo->prepare("SELECT name FROM expense_categories WHERE school_id = :school_id ORDER BY name ASC");
$stmt_cats->execute([':school_id' => $school_id]);
$expense_categories = $stmt_cats->fetchAll(PDO::FETCH_COLUMN);

// Per-type totals for badge pills
$stmt_totals = $pdo->prepare("SELECT expense_type, SUM(amount) AS total FROM expenses WHERE school_id = :school_id AND deleted_at IS NULL GROUP BY expense_type");
$stmt_totals->execute([':school_id' => $school_id]);
$type_totals = [];
foreach ($stmt_totals->fetchAll() as $r) { $type_totals[$r['expense_type']] = intval($r['total']); }

// Grand total
$stmt_grand = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE school_id = :school_id AND deleted_at IS NULL");
$stmt_grand->execute([':school_id' => $school_id]);
$grand_total = floatval($stmt_grand->fetchColumn());

// Today total
$stmt_today = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE school_id = :school_id AND deleted_at IS NULL AND DATE(expense_date) = CURDATE()");
$stmt_today->execute([':school_id' => $school_id]);
$today_total = floatval($stmt_today->fetchColumn());

// Staff for dropdowns (paid by, etc.)
$stmt_staff = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM teachers WHERE school_id = :school_id AND deleted_at IS NULL ORDER BY first_name ASC");
$stmt_staff->execute([':school_id' => $school_id]);
$staff_list = $stmt_staff->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
// Filters & Pagination
// ─────────────────────────────────────────────────────────────────────────────
$search       = trim($_GET['search']       ?? '');
$filter_type  = trim($_GET['expense_type'] ?? '');
$show_deleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';
$limit        = max(10, intval($_GET['limit'] ?? 25));
$page         = max(1,  intval($_GET['page']  ?? 1));
$offset       = ($page - 1) * $limit;

$where  = 'WHERE e.school_id = :school_id';
$params = [':school_id' => $school_id];

$where .= $show_deleted ? ' AND e.deleted_at IS NOT NULL' : ' AND e.deleted_at IS NULL';

if ($search !== '') {
    $where .= ' AND (e.expense_type LIKE :search OR e.utr_reference_no LIKE :search OR e.voucher_no LIKE :search OR e.narration LIKE :search OR e.payment_txn_id LIKE :search)';
    $params[':search'] = "%$search%";
}
if ($filter_type !== '') {
    $where .= ' AND e.expense_type = :expense_type';
    $params[':expense_type'] = $filter_type;
}

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM expenses e $where");
$stmt_count->execute($params);
$total_records = intval($stmt_count->fetchColumn());
$total_pages   = max(1, (int) ceil($total_records / $limit));

$stmt_data = $pdo->prepare("SELECT e.*, u.username AS created_by_name FROM expenses e LEFT JOIN users u ON e.created_by = u.id $where ORDER BY e.id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) { $stmt_data->bindValue($k, $v); }
$stmt_data->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_data->execute();
$expenses = $stmt_data->fetchAll();

$csrf_token    = generate_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Build base query string for pagination links
$base_qs = http_build_query(array_filter([
    'search'       => $search,
    'expense_type' => $filter_type,
    'limit'        => $limit !== 25 ? $limit : '',
    'deleted'      => $show_deleted ? '1' : '',
]));

require_once '../../../includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════════════════
     PAGE HEADER
═══════════════════════════════════════════════════════════════════ -->
<div class="row align-items-center mb-3 g-2">
    <div class="col-sm">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Expenses</h2>
        <p class="text-xs text-muted mb-0 mt-1">Track and manage all school expenditures.</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     TOOLBAR  (matches fee-toolbar pattern)
═══════════════════════════════════════════════════════════════════ -->
<div class="fee-toolbar mb-3">
    <!-- Left: totals + filter toggle -->
    <div class="fee-toolbar-left">
        <!-- Total pill -->
        <div class="fee-total-badge" style="background-color:#f0f9ff !important; border-color:#bae6fd !important; color:#0369a1 !important;">
            <i class="ph-light ph-wallet"></i>
            Total: <strong><?php echo number_format($grand_total, 0); ?></strong>
        </div>

        <!-- Today pill -->
        <div class="fee-total-badge" style="background-color:#f0fdf4 !important; border-color:#bbf7d0 !important; color:#166534 !important;">
            <i class="ph-light ph-calendar-check"></i>
            Today Expense: <strong><?php echo number_format($today_total, 0); ?></strong>
        </div>

        <!-- Filter funnel -->
        <button type="button" class="fee-btn-funnel" id="btnToggleFilter" title="Filter">
            <i class="ph-light ph-funnel"></i>
        </button>
    </div>

    <!-- Right: search + actions -->
    <div class="fee-toolbar-right">
        <form method="GET" action="index.php" class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Hidden carry-overs -->
            <?php if ($filter_type): ?><input type="hidden" name="expense_type" value="<?php echo sanitize($filter_type); ?>"><?php endif; ?>
            <?php if ($show_deleted): ?><input type="hidden" name="deleted" value="1"><?php endif; ?>

            <!-- Search box -->
            <div class="fee-search-container">
                <i class="ph-light ph-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>"
                       class="fee-search-input"
                       placeholder="Search by Expense type, UTR/Ref no., Voucher no., narration, txn ID...">
                <button type="submit" class="fee-search-btn"><i class="ph-light ph-magnifying-glass"></i></button>
            </div>

            <!-- Add expense -->
            <button type="button" id="btnAddExpense" class="fee-btn-primary" style="width:40px; padding:0;" title="Add Expense">
                <i class="ph-bold ph-plus"></i>
            </button>

            <!-- Export placeholder -->
            <button type="button" class="fee-btn-demand-bill" title="Export">
                <i class="ph-light ph-cloud-arrow-up"></i>
            </button>

            <!-- Deleted toggle -->
            <?php if ($show_deleted): ?>
                <a href="index.php" class="fee-btn-secondary text-danger" style="border-color:#fca5a5 !important;">Active</a>
            <?php else: ?>
                <a href="index.php?deleted=1" class="fee-btn-demand-bill text-danger" style="border-color:#fca5a5 !important;">Deleted</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     EXPENSE-TYPE BADGE PILLS  (same pattern as fees filter tags)
═══════════════════════════════════════════════════════════════════ -->
<div class="d-flex flex-wrap gap-2 mb-3" id="expenseTypeBadges">
    <?php foreach ($expense_categories as $cat):
        $cat_total  = $type_totals[$cat] ?? 0;
        $is_active  = ($filter_type === $cat);
        $qs         = http_build_query(['expense_type' => $cat, 'search' => $search]);
    ?>
        <a href="index.php?<?php echo $qs; ?>"
           class="fee-action-btn <?php echo $is_active ? 'fee-action-btn-collect' : ''; ?> text-decoration-none"
           style="height:32px; padding:0 14px; font-size:12px; width:auto;
                  <?php echo $is_active ? '' : 'background-color:#f59e0b !important; color:#fff !important;'; ?>">
            <?php echo sanitize($cat); ?> (<?php echo number_format($cat_total, 0); ?>)
        </a>
    <?php endforeach; ?>

    <?php if ($filter_type): ?>
        <a href="index.php?search=<?php echo urlencode($search); ?>"
           class="fee-btn-demand-bill text-decoration-none"
           style="height:32px; padding:0 12px; font-size:12px;">
            <i class="ph-bold ph-x"></i> Clear Filter
        </a>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     ADVANCED FILTER PANEL  (hidden by default)
═══════════════════════════════════════════════════════════════════ -->
<div class="card-body border bg-light rounded p-3 mb-3" id="filterPanel" style="display:none;">
    <form method="GET" action="index.php">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label-admin mb-1">Expense Type</label>
                <select name="expense_type" class="form-control-admin">
                    <option value="">All Types</option>
                    <?php foreach ($expense_categories as $cat): ?>
                        <option value="<?php echo sanitize($cat); ?>" <?php echo ($filter_type === $cat) ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label-admin mb-1">Show</label>
                <select name="limit" class="form-control-admin">
                    <?php foreach ([10, 25, 50, 100] as $n): ?>
                        <option value="<?php echo $n; ?>" <?php echo ($limit == $n) ? 'selected' : ''; ?>><?php echo $n; ?> entries</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($search): ?><input type="hidden" name="search" value="<?php echo sanitize($search); ?>"><?php endif; ?>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100" style="height:38px;">Apply</button>
            </div>
        </div>
    </form>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     TABLE CARD
═══════════════════════════════════════════════════════════════════ -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (empty($expenses)): ?>
                        <div class="p-5 text-center">
                            <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                <i class="ph-light ph-wallet"></i>
                            </div>
                            <h5 class="fw-semibold mt-3 font-heading">No expense records found</h5>
                            <p class="text-xs text-muted mb-0">Click the <strong>+</strong> button to add your first expense.</p>
                        </div>
                    <?php else: ?>
                        <table class="teacher-table table-premium mb-0">
                            <thead>
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>UTR/Reference No.</th>
                                    <th>Voucher No.</th>
                                    <th>Expense Type</th>
                                    <th>Amount</th>
                                    <th>Payment Mode</th>
                                    <th>Payment TXN</th>
                                    <th>Expense Date</th>
                                    <th>Paid By</th>
                                    <th>Paid To</th>
                                    <th>Narration</th>
                                    <th>Transaction ID</th>
                                    <th>Created By</th>
                                    <th>Creation Time</th>
                                    <th style="width:130px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = $offset + 1;
                                foreach ($expenses as $exp): ?>
                                    <tr>
                                        <td><span class="cell-counter"><?php echo $idx++; ?></span></td>
                                        <td class="text-xs"><?php echo sanitize($exp['utr_reference_no'] ?? '—'); ?></td>
                                        <td class="text-xs"><?php echo sanitize($exp['voucher_no'] ?? '—'); ?></td>
                                        <td>
                                            <span class="fee-action-btn fee-action-btn-update"
                                                  style="height:26px; padding:0 10px; font-size:11.5px; width:auto; cursor:default; pointer-events:none; border-radius:20px;">
                                                <?php echo sanitize($exp['expense_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?php echo number_format(floatval($exp['amount']), 2); ?></strong>
                                        </td>
                                        <td class="text-xs"><?php echo sanitize($exp['payment_mode']); ?></td>
                                        <td class="text-xs"><?php echo sanitize($exp['payment_txn_id'] ?? '—'); ?></td>
                                        <td class="text-xs">
                                            <?php if (!empty($exp['expense_date'])): ?>
                                                <?php echo date('d M, Y', strtotime($exp['expense_date'])); ?><br>
                                                <span class="text-muted"><?php echo date('h:ia', strtotime($exp['expense_date'])); ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td class="text-xs"><?php echo sanitize($exp['paid_by'] ?? '—'); ?></td>
                                        <td class="text-xs"><?php echo sanitize($exp['paid_to'] ?? '—'); ?></td>
                                        <td class="text-xs"><?php echo sanitize($exp['narration'] ?? '—'); ?></td>
                                        <td class="text-xs" style="max-width:180px; word-break:break-all;">
                                            <?php echo sanitize($exp['payment_txn_id'] ?? '—'); ?>
                                        </td>
                                        <td class="text-xs"><?php echo sanitize($exp['created_by_name'] ?? '—'); ?></td>
                                        <td class="text-xs">
                                            <?php if (!empty($exp['created_at'])): ?>
                                                <?php echo date('d M, Y', strtotime($exp['created_at'])); ?><br>
                                                <span class="text-muted"><?php echo date('h:ia', strtotime($exp['created_at'])); ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 align-items-center">
                                                <!-- View -->
                                                <button type="button"
                                                        class="teacher-action-btn action-view btn-view-expense"
                                                        data-id="<?php echo $exp['id']; ?>" title="View">
                                                    <i class="ph-light ph-eye"></i>
                                                </button>
                                                <!-- Edit -->
                                                <button type="button"
                                                        class="teacher-action-btn action-edit btn-edit-expense"
                                                        data-id="<?php echo $exp['id']; ?>" title="Edit">
                                                    <i class="ph-light ph-pencil-simple"></i>
                                                </button>
                                                <!-- Print -->
                                                <button type="button"
                                                        class="teacher-action-btn"
                                                        style="color:#d97706; border-color:rgba(217,119,6,.2); background-color:#fef3c7;"
                                                        data-id="<?php echo $exp['id']; ?>"
                                                        onclick="window.open('index.php?get_expense=1&id=<?php echo $exp['id']; ?>','_blank')"
                                                        title="Print">
                                                    <i class="ph-light ph-printer"></i>
                                                </button>
                                                <!-- Delete -->
                                                <button type="button"
                                                        class="teacher-action-btn action-delete btn-delete-expense"
                                                        data-id="<?php echo $exp['id']; ?>"
                                                        data-name="<?php echo sanitize($exp['expense_type']); ?>"
                                                        title="Delete">
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                        <span class="text-xs text-muted">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?>
                            of <?php echo $total_records; ?> entries
                        </span>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $base_qs; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $base_qs; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $base_qs; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════════
     ADD EXPENSE MODAL
═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-extrabold font-heading text-dark" id="addExpenseModalLabel">
                    <i class="ph-light ph-plus-circle me-2 text-primary"></i>Add Expense
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action"     value="add_expense">
                <div class="modal-body">
                    <?php echo expense_form_fields($expense_categories, null); ?>
                </div>
                <div class="modal-footer border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 font-heading fw-bold"
                            data-bs-dismiss="modal" style="height:38px; border-radius:8px;">Close</button>
                    <button type="submit" class="btn btn-primary px-4 font-heading fw-bold"
                            style="height:38px; border-radius:8px;">
                        <i class="ph-bold ph-floppy-disk me-1"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     EDIT EXPENSE MODAL
═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-extrabold font-heading text-dark" id="editExpenseModalLabel">
                    <i class="ph-light ph-pencil-simple me-2 text-warning"></i>Edit Expense
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data" id="editExpenseForm">
                <input type="hidden" name="csrf_token"  value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action"       value="edit_expense">
                <input type="hidden" name="expense_id"   id="edit_expense_id">
                <div class="modal-body" id="editExpenseBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-xs text-muted mt-2">Loading expense details…</p>
                    </div>
                </div>
                <div class="modal-footer border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 font-heading fw-bold"
                            data-bs-dismiss="modal" style="height:38px; border-radius:8px;">Close</button>
                    <button type="submit" class="btn btn-primary px-4 font-heading fw-bold"
                            style="height:38px; border-radius:8px;">
                        <i class="ph-bold ph-floppy-disk me-1"></i> Update Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     VIEW EXPENSE MODAL
═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-extrabold font-heading text-dark" id="viewExpenseModalLabel">
                    <i class="ph-light ph-eye me-2 text-accent"></i>Expense Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewExpenseBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-xs text-muted mt-2">Loading…</p>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary px-4 font-heading fw-bold"
                        data-bs-dismiss="modal" style="height:38px; border-radius:8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden delete form -->
<form id="deleteExpenseForm" method="POST" action="index.php" class="d-none">
    <input type="hidden" name="csrf_token"  value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action"       value="delete_expense">
    <input type="hidden" name="expense_id"   id="delete_expense_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── Toast helper ─────────────────────────────────────────── */
    const toast = (msg, icon = 'success') => {
        if (typeof Swal === 'undefined') return;
        Swal.fire({ toast: true, position: 'top-end', icon, title: msg,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true });
    };

    /* ── PHP flash messages ───────────────────────────────────── */
    <?php if ($flash_success): ?>toast(<?php echo json_encode($flash_success); ?>, 'success');<?php endif; ?>
    <?php if ($flash_error):   ?>toast(<?php echo json_encode($flash_error); ?>, 'error');<?php endif; ?>

    /* ── Filter panel toggle ──────────────────────────────────── */
    const filterPanel = document.getElementById('filterPanel');
    document.getElementById('btnToggleFilter').addEventListener('click', () => {
        filterPanel.style.display = filterPanel.style.display === 'none' ? 'block' : 'none';
    });

    /* ── Add Expense modal ────────────────────────────────────── */
    const addModal = new bootstrap.Modal(document.getElementById('addExpenseModal'));
    document.getElementById('btnAddExpense').addEventListener('click', () => addModal.show());

    /* ── View Expense ─────────────────────────────────────────── */
    const viewModal = new bootstrap.Modal(document.getElementById('viewExpenseModal'));
    document.querySelectorAll('.btn-view-expense').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            document.getElementById('viewExpenseBody').innerHTML =
                '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-xs text-muted mt-2">Loading…</p></div>';
            viewModal.show();
            fetch(`index.php?get_expense=1&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        document.getElementById('viewExpenseBody').innerHTML = '<p class="text-danger p-3">Failed to load expense.</p>'; return;
                    }
                    const e = res.expense;
                    const row = (lbl, val) => `
                        <tr>
                            <td class="fw-semibold text-xs" style="width:38%; background:#f8fafc; color:#64748b; padding:10px 14px; border-right:1px solid #e2e8f0;">${lbl}</td>
                            <td style="padding:10px 14px; font-size:13.5px;">${val || '—'}</td>
                        </tr>`;
                    let filesHtml = '—';
                    if (e.files) {
                        try {
                            const arr = JSON.parse(e.files);
                            filesHtml = arr.map(f => `<a href="<?php echo BASE_URL; ?>${f}" target="_blank" class="text-primary text-xs d-block">${f.split('/').pop()}</a>`).join('');
                        } catch(_) {}
                    }
                    document.getElementById('viewExpenseBody').innerHTML = `
                        <table class="table table-bordered table-sm mb-0" style="font-size:13.5px; border-color:#e2e8f0;">
                            <tbody>
                                ${row('Expense Type', `<span class="fee-action-btn fee-action-btn-update" style="height:24px;padding:0 10px;font-size:11.5px;width:auto;cursor:default;pointer-events:none;border-radius:20px;">${e.expense_type}</span>`)}
                                ${row('Voucher No.', e.voucher_no)}
                                ${row('Txn ID', e.payment_txn_id)}
                                ${row('Amount', `<strong class="text-dark fs-6">${parseFloat(e.amount).toFixed(2)}</strong>`)}
                                ${row('Payment Mode', e.payment_mode)}
                                ${row('Payment Account', e.payment_account)}
                                ${row('UTR/Reference No.', e.utr_reference_no)}
                                ${row('Expense Date', e.expense_date)}
                                ${row('Narration', e.narration)}
                                ${row('Expense Details', e.expense_details ? `<div class="text-xs" style="line-height:1.6">${e.expense_details}</div>` : null)}
                                ${row('Paid By', e.paid_by)}
                                ${row('Paid To', e.paid_to)}
                                ${row('Prepared By', e.prepared_by)}
                                ${row('Received By', e.received_by)}
                                ${row('Approved By', e.approved_by)}
                                ${row('Created By', e.created_by_name)}
                                ${row('Files', filesHtml)}
                            </tbody>
                        </table>`;
                })
                .catch(() => {
                    document.getElementById('viewExpenseBody').innerHTML = '<p class="text-danger p-3">Error loading data.</p>';
                });
        });
    });

    /* ── Edit Expense ─────────────────────────────────────────── */
    const editModal    = new bootstrap.Modal(document.getElementById('editExpenseModal'));
    const expCats      = <?php echo json_encode($expense_categories); ?>;
    const pmModes      = ['Cash','UPI','Bank Transfer','Cheque','DD','Online'];

    document.querySelectorAll('.btn-edit-expense').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            document.getElementById('edit_expense_id').value = id;
            document.getElementById('editExpenseBody').innerHTML =
                '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-xs text-muted mt-2">Loading…</p></div>';
            editModal.show();

            fetch(`index.php?get_expense=1&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        document.getElementById('editExpenseBody').innerHTML = '<p class="text-danger p-3">Failed to load expense.</p>'; return;
                    }
                    const e = res.expense;
                    const catOpts = expCats.map(c => `<option value="${c}" ${e.expense_type === c ? 'selected' : ''}>${c}</option>`).join('');
                    const pmOpts  = pmModes.map(m  => `<option value="${m}" ${e.payment_mode  === m ? 'selected' : ''}>${m}</option>`).join('');
                    const dtVal   = e.expense_date ? e.expense_date.replace(' ', 'T').substring(0, 16) : '';
                    const esc     = s => (s || '').replace(/"/g, '&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

                    document.getElementById('editExpenseBody').innerHTML = buildFormHtml(catOpts, pmOpts, dtVal, e, esc);
                })
                .catch(() => {
                    document.getElementById('editExpenseBody').innerHTML = '<p class="text-danger p-3">Error loading data.</p>';
                });
        });
    });

    function buildFormHtml(catOpts, pmOpts, dtVal, e, esc) {
        return `
        <h6 class="text-primary mb-3" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.07em;border-left:3px solid var(--color-accent);padding-left:10px;font-weight:700;">
            Basic Information
        </h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Expense Type *</label>
                <select name="expense_type" class="form-control-admin" required>
                    <option value="">Select type</option>${catOpts}
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Total Amount *</label>
                <input type="number" name="amount" class="form-control-admin" value="${esc(e.amount)}" required min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Payment Mode *</label>
                <select name="payment_mode" class="form-control-admin" required>
                    <option value="">Select</option>${pmOpts}
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Payment Account *</label>
                <input type="text" name="payment_account" class="form-control-admin" value="${esc(e.payment_account)}" placeholder="Account used for payment">
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Expense Date *</label>
                <input type="datetime-local" name="expense_date" class="form-control-admin" value="${dtVal}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Voucher No.</label>
                <input type="text" name="voucher_no" class="form-control-admin" value="${esc(e.voucher_no)}" placeholder="Voucher number (optional)">
            </div>
        </div>

        <h6 class="text-primary mb-3" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.07em;border-left:3px solid var(--color-accent);padding-left:10px;font-weight:700;">
            Party &amp; Reference
        </h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Paid by (Staffs) *</label>
                <input type="text" name="paid_by" class="form-control-admin" value="${esc(e.paid_by)}" placeholder="Staff who paid" required>
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Expense Parties (Paid To)</label>
                <input type="text" name="paid_to" class="form-control-admin" value="${esc(e.paid_to)}" placeholder="Vendor / party paid to">
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Narration</label>
                <input type="text" name="narration" class="form-control-admin" value="${esc(e.narration)}" placeholder="Brief note">
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">Payment Transaction ID</label>
                <input type="text" name="payment_txn_id" class="form-control-admin" value="${esc(e.payment_txn_id)}" placeholder="TXN / UTR number">
            </div>
            <div class="col-md-6">
                <label class="form-label-admin mb-1">UTR / Reference No.</label>
                <input type="text" name="utr_reference_no" class="form-control-admin" value="${esc(e.utr_reference_no)}" placeholder="UTR or reference number">
            </div>
        </div>

        <h6 class="text-primary mb-3" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.07em;border-left:3px solid var(--color-accent);padding-left:10px;font-weight:700;">
            Approvals
        </h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label-admin mb-1">Prepared by (Staffs)</label>
                <input type="text" name="prepared_by" class="form-control-admin" value="${esc(e.prepared_by)}" placeholder="Preparing staff">
            </div>
            <div class="col-md-4">
                <label class="form-label-admin mb-1">Approved by (Staffs)</label>
                <input type="text" name="approved_by" class="form-control-admin" value="${esc(e.approved_by)}" placeholder="Approving staff">
            </div>
            <div class="col-md-4">
                <label class="form-label-admin mb-1">Received by (Staffs)</label>
                <input type="text" name="received_by" class="form-control-admin" value="${esc(e.received_by)}" placeholder="Receiving staff">
            </div>
        </div>

        <h6 class="text-primary mb-3" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.07em;border-left:3px solid var(--color-accent);padding-left:10px;font-weight:700;">
            Details &amp; Attachments
        </h6>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label-admin mb-1">Expense Details</label>
                <textarea name="expense_details" class="form-control-admin" rows="4" style="height:auto;" placeholder="Detailed description…">${esc(e.expense_details)}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label-admin mb-1">Screenshots <span class="text-muted fw-normal">(jpeg, jpg, png, webp or pdf — max 5 MB each)</span></label>
                <input type="file" name="screenshots[]" class="form-control-admin pt-1"
                       accept=".jpg,.jpeg,.png,.webp,.pdf" multiple style="height:auto;">
            </div>
        </div>`;
    }

    /* ── Delete Expense ───────────────────────────────────────── */
    document.querySelectorAll('.btn-delete-expense').forEach(btn => {
        btn.addEventListener('click', function () {
            const id   = this.dataset.id;
            const name = this.dataset.name;
            Swal.fire({
                title: 'Delete Expense?',
                text:  `This will soft-delete the "${name}" expense record.`,
                icon:  'warning',
                showCancelButton:    true,
                confirmButtonColor:  '#dc3545',
                cancelButtonColor:   '#6c757d',
                confirmButtonText:   'Yes, delete it!'
            }).then(result => {
                if (result.isConfirmed) {
                    document.getElementById('delete_expense_id').value = id;
                    document.getElementById('deleteExpenseForm').submit();
                }
            });
        });
    });

});
</script>

<?php
/* ─────────────────────────────────────────────────────────────────────────────
   Helper: render Add-form fields in sections (PHP-side, for Add modal only)
─────────────────────────────────────────────────────────────────────────────── */
function expense_form_fields(array $categories, ?array $data = null): string
{
    $v  = fn($f) => htmlspecialchars($data[$f] ?? '', ENT_QUOTES, 'UTF-8');
    $dt = date('Y-m-d\TH:i');

    $cat_opts = '<option value="">Select type</option>';
    foreach ($categories as $c) {
        $sel = ($data && $data['expense_type'] === $c) ? 'selected' : '';
        $cat_opts .= '<option value="' . htmlspecialchars($c, ENT_QUOTES) . "\" $sel>" . htmlspecialchars($c, ENT_QUOTES) . '</option>';
    }

    $pm_opts = '';
    foreach (['Cash','UPI','Bank Transfer','Cheque','DD','Online'] as $m) {
        $sel = ($data && $data['payment_mode'] === $m) ? 'selected' : '';
        $pm_opts .= "<option value=\"$m\" $sel>$m</option>";
    }

    $dte = $data ? str_replace(' ', 'T', substr($data['expense_date'] ?? $dt, 0, 16)) : $dt;

    $section_head = fn(string $label) => "
        <h6 class=\"text-primary mb-3\" style=\"font-size:11.5px;text-transform:uppercase;letter-spacing:.07em;border-left:3px solid var(--color-accent);padding-left:10px;font-weight:700;\">$label</h6>";

    return $section_head('Basic Information') . "
    <div class=\"row g-3 mb-4\">
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Expense Type *</label>
            <select name=\"expense_type\" class=\"form-control-admin\" required>$cat_opts</select>
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Total Amount *</label>
            <input type=\"number\" name=\"amount\" class=\"form-control-admin\" value=\"{$v('amount')}\" required min=\"0\" step=\"0.01\" placeholder=\"0.00\">
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Payment Mode *</label>
            <select name=\"payment_mode\" class=\"form-control-admin\" required>
                <option value=\"\">Select</option>$pm_opts
            </select>
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Payment Account *</label>
            <input type=\"text\" name=\"payment_account\" class=\"form-control-admin\" value=\"{$v('payment_account')}\" placeholder=\"Account used for payment\">
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Expense Date *</label>
            <input type=\"datetime-local\" name=\"expense_date\" class=\"form-control-admin\" value=\"$dte\" required>
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Voucher No.</label>
            <input type=\"text\" name=\"voucher_no\" class=\"form-control-admin\" value=\"{$v('voucher_no')}\" placeholder=\"Voucher number (optional)\">
        </div>
    </div>" .

    $section_head('Party &amp; Reference') . "
    <div class=\"row g-3 mb-4\">
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Paid by (Staffs) *</label>
            <input type=\"text\" name=\"paid_by\" class=\"form-control-admin\" value=\"{$v('paid_by')}\" placeholder=\"Staff who paid\" required>
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Expense Parties (Paid To)</label>
            <input type=\"text\" name=\"paid_to\" class=\"form-control-admin\" value=\"{$v('paid_to')}\" placeholder=\"Vendor / party paid to\">
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Narration</label>
            <input type=\"text\" name=\"narration\" class=\"form-control-admin\" value=\"{$v('narration')}\" placeholder=\"Brief note\">
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">Payment Transaction ID</label>
            <input type=\"text\" name=\"payment_txn_id\" class=\"form-control-admin\" value=\"{$v('payment_txn_id')}\" placeholder=\"TXN / UTR number\">
        </div>
        <div class=\"col-md-6\">
            <label class=\"form-label-admin mb-1\">UTR / Reference No.</label>
            <input type=\"text\" name=\"utr_reference_no\" class=\"form-control-admin\" value=\"{$v('utr_reference_no')}\" placeholder=\"UTR or reference number\">
        </div>
    </div>" .

    $section_head('Approvals') . "
    <div class=\"row g-3 mb-4\">
        <div class=\"col-md-4\">
            <label class=\"form-label-admin mb-1\">Prepared by (Staffs)</label>
            <input type=\"text\" name=\"prepared_by\" class=\"form-control-admin\" value=\"{$v('prepared_by')}\" placeholder=\"Preparing staff\">
        </div>
        <div class=\"col-md-4\">
            <label class=\"form-label-admin mb-1\">Approved by (Staffs)</label>
            <input type=\"text\" name=\"approved_by\" class=\"form-control-admin\" value=\"{$v('approved_by')}\" placeholder=\"Approving staff\">
        </div>
        <div class=\"col-md-4\">
            <label class=\"form-label-admin mb-1\">Received by (Staffs)</label>
            <input type=\"text\" name=\"received_by\" class=\"form-control-admin\" value=\"{$v('received_by')}\" placeholder=\"Receiving staff\">
        </div>
    </div>" .

    $section_head('Details &amp; Attachments') . "
    <div class=\"row g-3\">
        <div class=\"col-12\">
            <label class=\"form-label-admin mb-1\">Expense Details</label>
            <textarea name=\"expense_details\" class=\"form-control-admin\" rows=\"4\" style=\"height:auto;\" placeholder=\"Detailed description of the expense…\">{$v('expense_details')}</textarea>
        </div>
        <div class=\"col-12\">
            <label class=\"form-label-admin mb-1\">
                Screenshots
                <span class=\"text-muted fw-normal\">(Only jpeg, jpg, png, webp or pdf allowed, multiple can be uploaded, size max 5&nbsp;MB)</span>
            </label>
            <input type=\"file\" name=\"screenshots[]\" class=\"form-control-admin pt-1\" accept=\".jpg,.jpeg,.png,.webp,.pdf\" multiple style=\"height:auto;\">
        </div>
    </div>";
}

require_once '../../../includes/footer.php';
?>
