<?php
// modules/school/bank-accounts/payment-bank-accounts.php
require_once '../../../config/helpers.php';
auth_check(['school_admin']);
$school_id = enforce_tenant();
require_once '../../../config/db.php';

// ── Ensure table exists (safe idempotent migration) ──────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `payment_bank_accounts` (
        `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        `school_id`         INT UNSIGNED     NOT NULL,
        `bank_name`         VARCHAR(255)     NOT NULL DEFAULT '',
        `branch`            VARCHAR(255)     NOT NULL DEFAULT '',
        `ifsc_code`         VARCHAR(20)      NOT NULL DEFAULT '',
        `address`           TEXT             NOT NULL,
        `account_holder`    VARCHAR(255)     NOT NULL DEFAULT '',
        `account_no`        VARCHAR(50)      NOT NULL DEFAULT '',
        `linked_mobile`     VARCHAR(20)      NOT NULL DEFAULT '',
        `linked_email`      VARCHAR(255)     NOT NULL DEFAULT '',
        `bank_mobile`       VARCHAR(20)      NOT NULL DEFAULT '',
        `bank_email`        VARCHAR(255)     NOT NULL DEFAULT '',
        `upi`               VARCHAR(100)     NOT NULL DEFAULT '',
        `payment_modes`     VARCHAR(255)     NOT NULL DEFAULT '',
        `opening_balance`   DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
        `status`            ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
        `remark`            TEXT,
        `added_by`          INT UNSIGNED     NOT NULL DEFAULT 0,
        `created_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_school_id` (`school_id`),
        KEY `idx_status`    (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Helpers ──────────────────────────────────────────────────────────────────
$added_by_id   = $_SESSION['user_id'] ?? 0;
$added_by_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($added_by_name === '') {
    $added_by_name = $_SESSION['username'] ?? 'Admin';
}

// ── Build bank list from existing records + a curated Indian bank list ────────
$db_banks = [];
try {
    $b_stmt = $pdo->prepare("SELECT DISTINCT bank_name FROM payment_bank_accounts WHERE school_id = :sid AND bank_name != '' ORDER BY bank_name ASC");
    $b_stmt->execute([':sid' => $school_id]);
    $db_banks = $b_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$default_banks = [
    'Abhyudaya Cooperative Bank Limited',
    'Allahabad Bank',
    'Andhra Bank',
    'Axis Bank',
    'Bank of Baroda',
    'Bank of India',
    'Bank of Maharashtra',
    'Canara Bank',
    'Central Bank of India',
    'City Union Bank',
    'Corporation Bank',
    'DCB Bank',
    'Dena Bank',
    'Federal Bank',
    'HDFC Bank',
    'Himachal Pradesh State Cooperative Bank Ltd',
    'ICICI Bank',
    'IDBI Bank',
    'IDFC First Bank',
    'Indian Bank',
    'Indian Overseas Bank',
    'IndusInd Bank',
    'Jammu & Kashmir Bank',
    'Karnataka Bank',
    'Karur Vysya Bank',
    'Kotak Mahindra Bank',
    'Lakshmi Vilas Bank',
    'Oriental Bank of Commerce',
    'Punjab & Sind Bank',
    'Punjab National Bank',
    'RBL Bank',
    'South Indian Bank',
    'State Bank of India',
    'Syndicate Bank',
    'UCO Bank',
    'Union Bank of India',
    'United Bank of India',
    'Vijaya Bank',
    'Yes Bank',
];
$bank_list = array_unique(array_merge($default_banks, $db_banks));
sort($bank_list);

// ── POST: Add ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO payment_bank_accounts
                (school_id, bank_name, branch, ifsc_code, address, account_holder, account_no,
                 linked_mobile, linked_email, bank_mobile, bank_email, upi, payment_modes,
                 opening_balance, status, remark, added_by)
            VALUES
                (:school_id, :bank_name, :branch, :ifsc_code, :address, :account_holder, :account_no,
                 :linked_mobile, :linked_email, :bank_mobile, :bank_email, :upi, :payment_modes,
                 :opening_balance, :status, :remark, :added_by)
        ");
        $stmt->execute([
            ':school_id'       => $school_id,
            ':bank_name'       => trim($_POST['bank_name']       ?? ''),
            ':branch'          => trim($_POST['branch']          ?? ''),
            ':ifsc_code'       => trim($_POST['ifsc_code']       ?? ''),
            ':address'         => trim($_POST['address']         ?? ''),
            ':account_holder'  => trim($_POST['account_holder']  ?? ''),
            ':account_no'      => trim($_POST['account_no']      ?? ''),
            ':linked_mobile'   => trim($_POST['linked_mobile']   ?? ''),
            ':linked_email'    => trim($_POST['linked_email']    ?? ''),
            ':bank_mobile'     => trim($_POST['bank_mobile']     ?? ''),
            ':bank_email'      => trim($_POST['bank_email']      ?? ''),
            ':upi'             => trim($_POST['upi']             ?? ''),
            ':payment_modes'   => implode(',', array_filter(array_map('trim', (array)($_POST['payment_modes'] ?? [])))),
            ':opening_balance' => (float)($_POST['opening_balance'] ?? 0),
            ':status'          => in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active',
            ':remark'          => trim($_POST['remark']          ?? ''),
            ':added_by'        => $added_by_id,
        ]);
        $_SESSION['flash_success'] = 'Bank account added successfully!';
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Failed to add bank account: ' . $e->getMessage();
    }
    header('Location: payment-bank-accounts.php');
    exit;
}

// ── POST: Edit ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE payment_bank_accounts SET
                    bank_name       = :bank_name,
                    branch          = :branch,
                    ifsc_code       = :ifsc_code,
                    address         = :address,
                    account_holder  = :account_holder,
                    account_no      = :account_no,
                    linked_mobile   = :linked_mobile,
                    linked_email    = :linked_email,
                    bank_mobile     = :bank_mobile,
                    bank_email      = :bank_email,
                    upi             = :upi,
                    payment_modes   = :payment_modes,
                    opening_balance = :opening_balance,
                    status          = :status,
                    remark          = :remark
                WHERE id = :id AND school_id = :school_id
            ");
            $stmt->execute([
                ':bank_name'       => trim($_POST['bank_name']       ?? ''),
                ':branch'          => trim($_POST['branch']          ?? ''),
                ':ifsc_code'       => trim($_POST['ifsc_code']       ?? ''),
                ':address'         => trim($_POST['address']         ?? ''),
                ':account_holder'  => trim($_POST['account_holder']  ?? ''),
                ':account_no'      => trim($_POST['account_no']      ?? ''),
                ':linked_mobile'   => trim($_POST['linked_mobile']   ?? ''),
                ':linked_email'    => trim($_POST['linked_email']    ?? ''),
                ':bank_mobile'     => trim($_POST['bank_mobile']     ?? ''),
                ':bank_email'      => trim($_POST['bank_email']      ?? ''),
                ':upi'             => trim($_POST['upi']             ?? ''),
                ':payment_modes'   => implode(',', array_filter(array_map('trim', (array)($_POST['payment_modes'] ?? [])))),
                ':opening_balance' => (float)($_POST['opening_balance'] ?? 0),
                ':status'          => in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active',
                ':remark'          => trim($_POST['remark']          ?? ''),
                ':id'              => $id,
                ':school_id'       => $school_id,
            ]);
            $_SESSION['flash_success'] = 'Bank account updated successfully!';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update bank account: ' . $e->getMessage();
        }
    }
    header('Location: payment-bank-accounts.php');
    exit;
}

// ── POST: Delete ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM payment_bank_accounts WHERE id = :id AND school_id = :school_id");
            $stmt->execute([':id' => $id, ':school_id' => $school_id]);
            $_SESSION['flash_success'] = 'Bank account deleted successfully!';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to delete bank account: ' . $e->getMessage();
        }
    }
    header('Location: payment-bank-accounts.php');
    exit;
}

// ── Fetch records ─────────────────────────────────────────────────────────────
$limit  = isset($_GET['limit'])  && is_numeric($_GET['limit'])  ? max(1, (int)$_GET['limit'])  : 20;
$page   = isset($_GET['page'])   && is_numeric($_GET['page'])   ? max(1, (int)$_GET['page'])   : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where  = "WHERE school_id = :school_id";
$params = [':school_id' => $school_id];
if ($search !== '') {
    $where .= " AND (bank_name LIKE :s OR branch LIKE :s OR ifsc_code LIKE :s OR account_no LIKE :s OR account_holder LIKE :s OR payment_modes LIKE :s)";
    $params[':s'] = "%$search%";
}

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_bank_accounts $where");
$total_stmt->execute($params);
$total_rows  = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $limit));

$data_stmt = $pdo->prepare("SELECT * FROM payment_bank_accounts $where ORDER BY id DESC LIMIT :limit OFFSET :offset");
$data_stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
if ($search !== '') $data_stmt->bindValue(':s', "%$search%");
$data_stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$data_stmt->execute();
$accounts = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Added-by display name lookup ──────────────────────────────────────────────
// TODO: Replace with a confirmed join to your users table once schema is finalised.
$added_by_map = [];
$user_ids = array_unique(array_filter(array_column($accounts, 'added_by')));
if (!empty($user_ids)) {
    $in = implode(',', array_map('intval', $user_ids));
    try {
        $u_stmt = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM users WHERE id IN ($in)");
        foreach ($u_stmt->fetchAll() as $u) {
            $added_by_map[$u['id']] = strtoupper(trim($u['full_name']));
        }
    } catch (Exception $e) { /* silently ignore if columns differ */ }
}

// ── Flash messages ────────────────────────────────────────────────────────────
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../../includes/header.php';
?>

<!-- ── Page Heading ───────────────────────────────────────────────────────── -->
<div class="row align-items-center mb-3 g-3">
    <div class="col-12">
        <h2 class="mb-0 font-heading fw-extrabold text-dark">Payment Bank Accounts</h2>
    </div>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible fade show font-secondary text-xs mb-3" role="alert" style="border-radius:8px;">
        <i class="ph-light ph-check-circle me-1" style="font-size:14px;vertical-align:middle;"></i>
        <?php echo htmlspecialchars($flash_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible fade show font-secondary text-xs mb-3" role="alert" style="border-radius:8px;">
        <i class="ph-light ph-warning-circle me-1" style="font-size:14px;vertical-align:middle;"></i>
        <?php echo htmlspecialchars($flash_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- ── Main Card ─────────────────────────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card-premium">

            <!-- Toolbar -->
            <div class="fee-toolbar" style="border-bottom:1px solid var(--color-border);border-radius:0;box-shadow:none;background:var(--gray-50);margin-bottom:0;">
                <div class="fee-toolbar-left d-flex align-items-center gap-2">
                    <button type="button" class="teacher-header-btn btn-accent" data-bs-toggle="modal" data-bs-target="#addBankAccountModal" title="Add Bank Account">
                        <i class="ph-light ph-plus"></i>
                    </button>
                    <span class="text-xs font-secondary" style="color:var(--color-text-muted);">Show</span>
                    <form method="GET" action="payment-bank-accounts.php" class="m-0 d-flex align-items-center gap-1">
                        <select name="limit" class="form-control-admin font-secondary text-secondary" style="width:64px;display:inline-block;height:34px;border-radius:4px;padding:2px 8px;" onchange="this.form.submit()">
                            <?php foreach ([10, 20, 50, 100] as $lv): ?>
                                <option value="<?php echo $lv; ?>" <?php echo $limit == $lv ? 'selected' : ''; ?>><?php echo $lv; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-xs font-secondary" style="color:var(--color-text-muted);">entries</span>
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                    </form>
                </div>
                <div class="fee-toolbar-right">
                    <form method="GET" action="payment-bank-accounts.php" class="d-flex align-items-center gap-1 m-0">
                        <label class="text-xs font-secondary mb-0" style="color:var(--color-text-primary);">Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control-admin font-secondary" style="width:170px;height:32px;border-radius:4px;padding:2px 8px;">
                        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="teacher-table table-premium mb-0 align-middle" id="bankAccountsTable">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="min-width:200px;">Bank Name</th>
                                <th style="min-width:140px;">Branch</th>
                                <th style="min-width:120px;">IFSC Code</th>
                                <th style="min-width:160px;">Address</th>
                                <th style="min-width:160px;">Account Holder Name</th>
                                <th style="min-width:160px;">Account No.</th>
                                <th style="min-width:130px;">Linked Mobile No.</th>
                                <th style="min-width:160px;">Linked Email</th>
                                <th style="min-width:130px;">Bank Mobile No.</th>
                                <th style="min-width:160px;">Bank Email</th>
                                <th style="min-width:130px;">UPI</th>
                                <th style="min-width:160px;">Payment Modes</th>
                                <th style="min-width:110px;">Opening Bal.</th>
                                <th style="min-width:90px;">Status</th>
                                <th style="min-width:100px;">Remark</th>
                                <th style="min-width:130px;">Added By</th>
                                <th style="min-width:140px;">Created At</th>
                                <th style="min-width:140px;">Updated At</th>
                                <th style="width:90px;text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="20" class="text-center p-5">
                                    <div class="icon-circle-lg activity-icon-blue mx-auto mb-3">
                                        <i class="ph-light ph-bank"></i>
                                    </div>
                                    <h5 class="fw-bold mt-3 mb-1 font-heading">No Bank Accounts Added</h5>
                                    <p class="text-xs mb-0 font-secondary" style="color:var(--color-text-muted);">Click the + button above to add your first payment bank account.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $row_num = $offset + 1;
                            foreach ($accounts as $acc):
                                $display_added_by = $added_by_map[$acc['added_by']] ?? strtoupper($added_by_name);
                            ?>
                            <tr>
                                <td class="font-secondary"><?php echo $row_num++; ?></td>
                                <td><span class="fw-semibold font-secondary text-xs" style="color:var(--color-text-primary);"><?php echo htmlspecialchars($acc['bank_name']); ?></span></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['branch']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['ifsc_code']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['address']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['account_holder']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['account_no']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['linked_mobile']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['linked_email']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['bank_mobile']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['bank_email']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['upi']); ?></td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['payment_modes']); ?></td>
                                <td class="font-secondary text-xs fw-semibold"><?php echo number_format((float)$acc['opening_balance'], 2); ?></td>
                                <td>
                                    <?php if ($acc['status'] === 'Active'): ?>
                                        <span class="badge text-white px-2 py-1 rounded font-secondary" style="font-size:11px;font-weight:600;background-color:var(--success)!important;">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-white px-2 py-1 rounded font-secondary" style="font-size:11px;font-weight:600;background-color:#a0aec0!important;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-secondary text-xs"><?php echo htmlspecialchars($acc['remark']); ?></td>
                                <td class="font-secondary text-xs fw-semibold" style="color:var(--color-text-primary);"><?php echo htmlspecialchars($display_added_by); ?></td>
                                <td>
                                    <span class="text-xs font-secondary fw-semibold" style="color:var(--color-text-secondary);">
                                        <?php echo date('d M, Y', strtotime($acc['created_at'])); ?><br>
                                        <small class="text-muted fw-normal" style="font-size:11px;"><?php echo date('h:i:sa', strtotime($acc['created_at'])); ?></small>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs font-secondary fw-semibold" style="color:var(--color-text-secondary);">
                                        <?php echo date('d M, Y', strtotime($acc['updated_at'])); ?><br>
                                        <small class="text-muted fw-normal" style="font-size:11px;"><?php echo date('h:i:sa', strtotime($acc['updated_at'])); ?></small>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button class="teacher-action-btn action-edit edit-bank-btn"
                                                data-id="<?php echo $acc['id']; ?>"
                                                data-bank-name="<?php echo htmlspecialchars($acc['bank_name']); ?>"
                                                data-branch="<?php echo htmlspecialchars($acc['branch']); ?>"
                                                data-ifsc="<?php echo htmlspecialchars($acc['ifsc_code']); ?>"
                                                data-address="<?php echo htmlspecialchars($acc['address']); ?>"
                                                data-holder="<?php echo htmlspecialchars($acc['account_holder']); ?>"
                                                data-account-no="<?php echo htmlspecialchars($acc['account_no']); ?>"
                                                data-linked-mobile="<?php echo htmlspecialchars($acc['linked_mobile']); ?>"
                                                data-linked-email="<?php echo htmlspecialchars($acc['linked_email']); ?>"
                                                data-bank-mobile="<?php echo htmlspecialchars($acc['bank_mobile']); ?>"
                                                data-bank-email="<?php echo htmlspecialchars($acc['bank_email']); ?>"
                                                data-upi="<?php echo htmlspecialchars($acc['upi']); ?>"
                                                data-payment-modes="<?php echo htmlspecialchars($acc['payment_modes']); ?>"
                                                data-opening-balance="<?php echo $acc['opening_balance']; ?>"
                                                data-status="<?php echo htmlspecialchars($acc['status']); ?>"
                                                data-remark="<?php echo htmlspecialchars($acc['remark']); ?>"
                                                title="Edit">
                                            <i class="ph-light ph-pencil-simple"></i>
                                        </button>
                                        <button class="teacher-action-btn action-delete delete-bank-btn"
                                                data-id="<?php echo $acc['id']; ?>"
                                                data-bank-name="<?php echo htmlspecialchars($acc['bank_name']); ?>"
                                                title="Delete">
                                            <i class="ph-light ph-trash"></i>
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

            <!-- Pagination Footer -->
            <div class="d-flex justify-content-between align-items-center px-3 py-2 font-secondary text-xs" style="border-top:1px solid var(--color-border);color:var(--color-text-muted);">
                <span>
                    <?php if ($total_rows === 0): ?>
                        Showing 0 entries
                    <?php else: ?>
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries
                    <?php endif; ?>
                </span>
                <nav>
                    <ul class="pagination pagination-sm mb-0 gap-1">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link font-secondary" href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                                <a class="page-link font-secondary" href="?page=<?php echo $p; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link font-secondary" href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<!-- ══ ADD MODAL ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addBankAccountModal" tabindex="-1" aria-labelledby="addBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius:var(--border-radius-lg);background:var(--color-surface);">
            <div class="modal-header border-0 bg-light py-3 px-4 d-flex justify-content-between align-items-center" style="border-top-left-radius:var(--border-radius-lg);border-top-right-radius:var(--border-radius-lg);">
                <h5 class="modal-title font-heading fw-bold mb-0" id="addBankAccountModalLabel" style="color:var(--color-text-primary);font-size:18px;">Add Payment Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="payment-bank-accounts.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-dark text-start">
                    <div class="row g-3">
                        <!-- Row 1: Bank, Branch, IFSC Code -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" id="add_bank_name" class="form-control-admin" required placeholder="Enter bank name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Branch <span class="text-danger">*</span></label>
                            <input type="text" name="branch" id="add_branch" class="form-control-admin" required placeholder="Enter branch name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">IFSC Code</label>
                            <input type="text" name="ifsc_code" id="add_ifsc_code" class="form-control-admin" placeholder="Enter IFSC code">
                        </div>
                        <!-- Row 2: Bank Address, Account Holder Name, Account No -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank Address</label>
                            <input type="text" name="address" class="form-control-admin" placeholder="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Account Holder Name</label>
                            <input type="text" name="account_holder" class="form-control-admin" placeholder="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Account No.</label>
                            <input type="text" name="account_no" class="form-control-admin" placeholder="">
                        </div>
                        <!-- Row 3: Linked Mobile, Linked Email, UPI ID -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Linked Mobile No.</label>
                            <input type="text" name="linked_mobile" class="form-control-admin" placeholder="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Linked Email</label>
                            <input type="email" name="linked_email" class="form-control-admin" placeholder="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">UPI ID</label>
                            <input type="text" name="upi" class="form-control-admin" placeholder="">
                        </div>
                        <!-- Row 4: Bank Contact No, Bank Email, Remark -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank Contact No.</label>
                            <input type="text" name="bank_mobile" class="form-control-admin" placeholder="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank Email</label>
                            <input type="email" name="bank_email" class="form-control-admin" placeholder="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Remark (If any)</label>
                            <input type="text" name="remark" class="form-control-admin" placeholder="">
                        </div>
                        <!-- Row 5: Payment Modes, Status, Opening Balance -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Payment Modes <span class="text-danger">*</span></label>
                            <div class="payment-modes-wrapper">
                                <select id="add_payment_mode_select" class="form-control-admin mb-2">
                                    <option value="" disabled selected>-- Select Payment Mode --</option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="NEFT">NEFT</option>
                                    <option value="RTGS">RTGS</option>
                                    <option value="NEFT/RTGS">NEFT/RTGS</option>
                                    <option value="IMPS">IMPS</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="DD">DD</option>
                                    <option value="Card">Card</option>
                                    <option value="Neft">Neft</option>
                                    <option value="Payment gateway">Payment gateway</option>
                                    <option value="Additional Test Method">Additional Test Method</option>
                                </select>
                                <select name="payment_modes[]" id="add_payment_modes" multiple style="display:none;" required>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="NEFT">NEFT</option>
                                    <option value="RTGS">RTGS</option>
                                    <option value="NEFT/RTGS">NEFT/RTGS</option>
                                    <option value="IMPS">IMPS</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="DD">DD</option>
                                    <option value="Card">Card</option>
                                    <option value="Neft">Neft</option>
                                    <option value="Payment gateway">Payment gateway</option>
                                    <option value="Additional Test Method">Additional Test Method</option>
                                </select>
                                <div id="add_payment_modes_tags" class="payment-modes-tags-container"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control-admin">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Current Opening Balance</label>
                            <small class="d-block font-secondary" style="color:var(--color-text-muted);font-size:11px;margin-bottom:4px;">Please enter the correct amount, it's noneditable.</small>
                            <input type="number" name="opening_balance" class="form-control-admin" step="0.01" min="0" placeholder="">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light d-flex justify-content-between px-4 py-3" style="border-bottom-left-radius:var(--border-radius-lg);border-bottom-right-radius:var(--border-radius-lg);">
                    <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color:#a0aec0;color:#fff;border-radius:6px;font-size:13px;border:none;">Close</button>
                    <button type="submit" class="btn font-secondary fw-semibold px-4 py-2" style="background-color:var(--color-accent);color:#fff;border-radius:6px;font-size:13px;border:none;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ EDIT MODAL ═════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1" aria-labelledby="editBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius:var(--border-radius-lg);background:var(--color-surface);">
            <div class="modal-header border-0 bg-light py-3 px-4 d-flex justify-content-between align-items-center" style="border-top-left-radius:var(--border-radius-lg);border-top-right-radius:var(--border-radius-lg);">
                <h5 class="modal-title font-heading fw-bold mb-0" id="editBankAccountModalLabel" style="color:var(--color-text-primary);font-size:18px;">Edit Payment Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="payment-bank-accounts.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4 text-dark text-start">
                    <div class="row g-3">
                        <!-- Row 1: Bank, Branch, IFSC Code -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" id="edit_bank_name" class="form-control-admin" required placeholder="Enter bank name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Branch <span class="text-danger">*</span></label>
                            <input type="text" name="branch" id="edit_branch" class="form-control-admin" required placeholder="Enter branch name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">IFSC Code</label>
                            <input type="text" name="ifsc_code" id="edit_ifsc_code" class="form-control-admin" placeholder="Enter IFSC code">
                        </div>
                        <!-- Row 2: Bank Address, Account Holder Name, Account No -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank Address</label>
                            <input type="text" name="address" id="edit_address" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Account Holder Name</label>
                            <input type="text" name="account_holder" id="edit_holder" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Account No.</label>
                            <input type="text" name="account_no" id="edit_account_no" class="form-control-admin">
                        </div>
                        <!-- Row 3: Linked Mobile, Linked Email, UPI ID -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Linked Mobile No.</label>
                            <input type="text" name="linked_mobile" id="edit_linked_mobile" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Linked Email</label>
                            <input type="email" name="linked_email" id="edit_linked_email" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">UPI ID</label>
                            <input type="text" name="upi" id="edit_upi" class="form-control-admin">
                        </div>
                        <!-- Row 4: Bank Contact No, Bank Email, Remark -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank Contact No.</label>
                            <input type="text" name="bank_mobile" id="edit_bank_mobile" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Bank Email</label>
                            <input type="email" name="bank_email" id="edit_bank_email" class="form-control-admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Remark (If any)</label>
                            <input type="text" name="remark" id="edit_remark" class="form-control-admin">
                        </div>
                        <!-- Row 5: Payment Modes, Status, Opening Balance (readonly) -->
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Payment Modes <span class="text-danger">*</span></label>
                            <div class="payment-modes-wrapper">
                                <select id="edit_payment_mode_select" class="form-control-admin mb-2">
                                    <option value="" disabled selected>-- Select Payment Mode --</option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="NEFT">NEFT</option>
                                    <option value="RTGS">RTGS</option>
                                    <option value="NEFT/RTGS">NEFT/RTGS</option>
                                    <option value="IMPS">IMPS</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="DD">DD</option>
                                    <option value="Card">Card</option>
                                    <option value="Neft">Neft</option>
                                    <option value="Payment gateway">Payment gateway</option>
                                    <option value="Additional Test Method">Additional Test Method</option>
                                </select>
                                <select name="payment_modes[]" id="edit_payment_modes" multiple style="display:none;" required>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="NEFT">NEFT</option>
                                    <option value="RTGS">RTGS</option>
                                    <option value="NEFT/RTGS">NEFT/RTGS</option>
                                    <option value="IMPS">IMPS</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="DD">DD</option>
                                    <option value="Card">Card</option>
                                    <option value="Neft">Neft</option>
                                    <option value="Payment gateway">Payment gateway</option>
                                    <option value="Additional Test Method">Additional Test Method</option>
                                </select>
                                <div id="edit_payment_modes_tags" class="payment-modes-tags-container"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-control-admin">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-admin mb-1">Current Opening Balance</label>
                            <small class="d-block font-secondary" style="color:var(--color-text-muted);font-size:11px;margin-bottom:4px;">Please enter the correct amount, it's noneditable.</small>
                            <input type="number" name="opening_balance" id="edit_opening_balance" class="form-control-admin" step="0.01" min="0" readonly style="background:var(--gray-50);cursor:not-allowed;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light d-flex justify-content-between px-4 py-3" style="border-bottom-left-radius:var(--border-radius-lg);border-bottom-right-radius:var(--border-radius-lg);">
                    <button type="button" class="btn font-secondary fw-semibold px-4 py-2" data-bs-dismiss="modal" style="background-color:#a0aec0;color:#fff;border-radius:6px;font-size:13px;border:none;">Close</button>
                    <button type="submit" class="btn font-secondary fw-semibold px-4 py-2" style="background-color:var(--color-accent);color:#fff;border-radius:6px;font-size:13px;border:none;">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteBankForm" action="payment-bank-accounts.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_bank_id">
</form>

<script>
// ── Tag-based multi-select manager ──────────────────────────────────────────
function initPaymentModesTagManager(visualSelectId, hiddenSelectId, tagsContainerId) {
    var visualSel = document.getElementById(visualSelectId);
    var hiddenSel = document.getElementById(hiddenSelectId);
    var tagsCont  = document.getElementById(tagsContainerId);

    function updateTagsUI() {
        tagsCont.innerHTML = '';
        
        // Loop through all options in hidden select and render tags for selected ones
        for (var i = 0; i < hiddenSel.options.length; i++) {
            var opt = hiddenSel.options[i];
            if (opt.selected) {
                createTagElement(opt.value);
            }
        }
    }

    function createTagElement(value) {
        var tag = document.createElement('span');
        tag.className = 'payment-mode-tag';
        
        var removeBtn = document.createElement('span');
        removeBtn.className = 'payment-mode-tag-remove';
        removeBtn.innerHTML = '×';
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            removeValue(value);
        });
        
        tag.appendChild(removeBtn);
        tag.appendChild(document.createTextNode(' ' + value));
        tagsCont.appendChild(tag);
    }

    function addValue(value) {
        if (!value) return;
        var opt = hiddenSel.querySelector('option[value="' + value + '"]');
        if (opt) {
            opt.selected = true;
            hiddenSel.dispatchEvent(new Event('change'));
            updateTagsUI();
        }
        visualSel.value = ''; // Reset select to placeholder
    }

    function removeValue(value) {
        var opt = hiddenSel.querySelector('option[value="' + value + '"]');
        if (opt) {
            opt.selected = false;
            hiddenSel.dispatchEvent(new Event('change'));
            updateTagsUI();
        }
    }

    visualSel.addEventListener('change', function() {
        addValue(visualSel.value);
    });

    // Initial setup
    updateTagsUI();

    return {
        refresh: updateTagsUI,
        setValues: function(valuesArray) {
            // Deselect all
            for (var i = 0; i < hiddenSel.options.length; i++) {
                hiddenSel.options[i].selected = false;
            }
            // Select specified ones
            valuesArray.forEach(function(val) {
                var opt = hiddenSel.querySelector('option[value="' + val + '"]');
                if (opt) {
                    opt.selected = true;
                } else if (val.trim() !== '') {
                    // Dynamic fallback
                    var newOpt = document.createElement('option');
                    newOpt.value = val;
                    newOpt.text = val;
                    newOpt.selected = true;
                    hiddenSel.appendChild(newOpt);
                }
            });
            hiddenSel.dispatchEvent(new Event('change'));
            updateTagsUI();
        }
    };
}

document.addEventListener('DOMContentLoaded', function () {
    var addTagManager = initPaymentModesTagManager('add_payment_mode_select', 'add_payment_modes', 'add_payment_modes_tags');
    var editTagManager = initPaymentModesTagManager('edit_payment_mode_select', 'edit_payment_modes', 'edit_payment_modes_tags');

    // ── Edit modal population ────────────────────────────────────────────────────
    document.querySelectorAll('.edit-bank-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id            = this.dataset.id;
            var bankName      = this.dataset.bankName;
            var branch        = this.dataset.branch;
            var ifsc          = this.dataset.ifsc;
            var address       = this.dataset.address;
            var holder        = this.dataset.holder;
            var accountNo     = this.dataset.accountNo;
            var linkedMobile  = this.dataset.linkedMobile;
            var linkedEmail   = this.dataset.linkedEmail;
            var upi           = this.dataset.upi;
            var bankMobile    = this.dataset.bankMobile;
            var bankEmail     = this.dataset.bankEmail;
            var remark        = this.dataset.remark;
            var paymentModes  = this.dataset.paymentModes;   // comma-separated
            var status        = this.dataset.status;
            var openingBal    = this.dataset.openingBalance;

            document.getElementById('edit_id').value             = id;
            document.getElementById('edit_bank_name').value      = bankName;
            document.getElementById('edit_branch').value         = branch;
            document.getElementById('edit_ifsc_code').value      = ifsc;
            document.getElementById('edit_address').value        = address;
            document.getElementById('edit_holder').value         = holder;
            document.getElementById('edit_account_no').value     = accountNo;
            document.getElementById('edit_linked_mobile').value  = linkedMobile;
            document.getElementById('edit_linked_email').value   = linkedEmail;
            document.getElementById('edit_upi').value            = upi;
            document.getElementById('edit_bank_mobile').value    = bankMobile;
            document.getElementById('edit_bank_email').value     = bankEmail;
            document.getElementById('edit_remark').value         = remark;
            document.getElementById('edit_opening_balance').value = openingBal;

            // Status
            var statusSel = document.getElementById('edit_status');
            for (var i = 0; i < statusSel.options.length; i++) {
                if (statusSel.options[i].value === status) { statusSel.selectedIndex = i; break; }
            }

            // Payment Modes
            var modes = paymentModes ? paymentModes.split(',').map(function(m){ return m.trim(); }) : [];
            editTagManager.setValues(modes);

            new bootstrap.Modal(document.getElementById('editBankAccountModal')).show();
        });
    });

    // ── Delete confirmation ───────────────────────────────────────────────────
    document.querySelectorAll('.delete-bank-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.dataset.id, name = this.dataset.bankName;
            Swal.fire({
                title: 'Delete Bank Account?',
                text: 'Are you sure you want to delete "' + name + '"? This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, Delete it!',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('delete_bank_id').value = id;
                    document.getElementById('deleteBankForm').submit();
                }
            });
        });
    });

});
</script>

<?php require_once '../../../includes/footer.php'; ?>
