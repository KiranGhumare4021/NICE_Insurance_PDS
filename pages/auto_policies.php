<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Auto Policies - NICE Insurance';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO JKP_AUTO_POLICY (CUSTOMER_ID, CUSTOMER_TYPE, AUTO_POLICY_ID, AUTO_START_DATE, AUTO_END_DATE, AUTO_AMOUNT, AUTO_STATUS)
                VALUES (?, 'A', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['customer_id'],
                $_POST['policy_id'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['amount'],
                $_POST['status'],
            ]);
            $db->commit();
            setFlash('success', 'Auto policy created successfully.');
        }

        if ($action === 'update' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE JKP_AUTO_POLICY SET AUTO_START_DATE=?, AUTO_END_DATE=?, AUTO_AMOUNT=?, AUTO_STATUS=?
                WHERE AUTO_POLICY_ID=?");
            $stmt->execute([
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['amount'],
                $_POST['status'],
                $_POST['policy_id'],
            ]);
            $db->commit();
            setFlash('success', 'Auto policy updated.');
        }

        if ($action === 'delete' && isEmployee()) {
            $db->beginTransaction();
            // Delete payments linked to invoices for this policy
            $db->prepare("DELETE p FROM JKP_AUTO_PAYMENT p JOIN JKP_AUTO_INVOICE i ON i.INVOICE_ID = p.INVOICE_ID WHERE i.AUTO_POLICY_ID = ?")->execute([$_POST['policy_id']]);
            // Delete invoices for this policy
            $db->prepare("DELETE FROM JKP_AUTO_INVOICE WHERE AUTO_POLICY_ID = ?")->execute([$_POST['policy_id']]);
            // Delete the policy
            $db->prepare("DELETE FROM JKP_AUTO_POLICY WHERE AUTO_POLICY_ID = ?")->execute([$_POST['policy_id']]);
            $db->commit();
            setFlash('success', 'Auto policy deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: auto_policies.php');
    exit;
}

// Fetch policies
if (isEmployee()) {
    $policies = $db->query("
        SELECT ap.*, c.FNAME, c.LNAME
        FROM JKP_AUTO_POLICY ap
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = ap.CUSTOMER_ID AND c.CUSTOMER_TYPE = ap.CUSTOMER_TYPE
        ORDER BY ap.CUSTOMER_ID
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT ap.*, c.FNAME, c.LNAME
        FROM JKP_AUTO_POLICY ap
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = ap.CUSTOMER_ID AND c.CUSTOMER_TYPE = ap.CUSTOMER_TYPE
        WHERE ap.CUSTOMER_ID = ?
    ");
    $stmt->execute([getCurrentCustomerId()]);
    $policies = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-car-front"></i> Auto Policies</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Policy
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>Policy ID</th>
                <th>Customer</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Amount</th>
                <th>Status</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($policies)): ?>
            <tr><td colspan="7" class="text-center text-muted">No auto policies found.</td></tr>
        <?php else: ?>
        <?php foreach ($policies as $p): ?>
            <tr>
                <td><?= e($p['AUTO_POLICY_ID']) ?></td>
                <td><?= e($p['FNAME'] . ' ' . $p['LNAME']) ?> (<?= e($p['CUSTOMER_ID']) ?>)</td>
                <td><?= date('M d, Y', strtotime($p['AUTO_START_DATE'])) ?></td>
                <td><?= date('M d, Y', strtotime($p['AUTO_END_DATE'])) ?></td>
                <td>$<?= number_format($p['AUTO_AMOUNT'], 2) ?></td>
                <td>
                    <span class="badge <?= $p['AUTO_STATUS'] === 'C' ? 'badge-current' : 'badge-expired' ?>">
                        <?= $p['AUTO_STATUS'] === 'C' ? 'Current' : 'Expired' ?>
                    </span>
                </td>
                <?php if (isEmployee()): ?>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick='openEditPolicy(<?= json_encode($p) ?>)'>
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline;" id="delp-<?= e($p['AUTO_POLICY_ID']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="policy_id" value="<?= e($p['AUTO_POLICY_ID']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action"
                                onclick="confirmDelete('delp-<?= e($p['AUTO_POLICY_ID']) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (isEmployee()): ?>
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Add Auto Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Customer ID</label><input type="number" class="form-control" name="customer_id" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Policy ID</label><input type="number" class="form-control" name="policy_id" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Amount ($)</label><input type="number" step="0.01" class="form-control" name="amount" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" name="status" required><option value="C">Current</option><option value="E">Expired</option></select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="policy_id" id="ep_pid">
                <div class="modal-header"><h5 class="modal-title">Edit Auto Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" id="ep_sdate" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" id="ep_edate" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Amount ($)</label><input type="number" step="0.01" class="form-control" name="amount" id="ep_amt" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="ep_status" required><option value="C">Current</option><option value="E">Expired</option></select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditPolicy(p) {
    document.getElementById('ep_pid').value = p.AUTO_POLICY_ID;
    document.getElementById('ep_sdate').value = p.AUTO_START_DATE;
    document.getElementById('ep_edate').value = p.AUTO_END_DATE;
    document.getElementById('ep_amt').value = p.AUTO_AMOUNT;
    document.getElementById('ep_status').value = p.AUTO_STATUS;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
