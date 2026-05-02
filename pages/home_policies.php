<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Home Policies - NICE Insurance';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO JKP_HOME_POLICY (CUSTOMER_ID, CUSTOMER_TYPE, HOME_POLICY_ID, HOME_START_DATE, HOME_END_DATE, HOME_AMOUNT, HOME_STATUS)
                VALUES (?, 'H', ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['customer_id'], $_POST['policy_id'], $_POST['start_date'], $_POST['end_date'], $_POST['amount'], $_POST['status']]);
            $db->commit();
            setFlash('success', 'Home policy created.');
        }

        if ($action === 'update' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE JKP_HOME_POLICY SET HOME_START_DATE=?, HOME_END_DATE=?, HOME_AMOUNT=?, HOME_STATUS=? WHERE HOME_POLICY_ID=?");
            $stmt->execute([$_POST['start_date'], $_POST['end_date'], $_POST['amount'], $_POST['status'], $_POST['policy_id']]);
            $db->commit();
            setFlash('success', 'Home policy updated.');
        }

        if ($action === 'delete' && isEmployee()) {
            $db->beginTransaction();
            $db->prepare("DELETE p FROM JKP_HOME_PAYMENT p JOIN JKP_HOME_INVOICE i ON i.INVOICE_ID = p.INVOICE_ID WHERE i.HOME_POLICY_ID = ?")->execute([$_POST['policy_id']]);
            $db->prepare("DELETE FROM JKP_HOME_INVOICE WHERE HOME_POLICY_ID = ?")->execute([$_POST['policy_id']]);
            $db->prepare("DELETE FROM JKP_HOME_POLICY WHERE HOME_POLICY_ID = ?")->execute([$_POST['policy_id']]);
            $db->commit();
            setFlash('success', 'Home policy deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: home_policies.php');
    exit;
}

if (isEmployee()) {
    $policies = $db->query("
        SELECT hp.*, c.FNAME, c.LNAME
        FROM JKP_HOME_POLICY hp
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = hp.CUSTOMER_ID AND c.CUSTOMER_TYPE = hp.CUSTOMER_TYPE
        ORDER BY hp.CUSTOMER_ID
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT hp.*, c.FNAME, c.LNAME
        FROM JKP_HOME_POLICY hp
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = hp.CUSTOMER_ID AND c.CUSTOMER_TYPE = hp.CUSTOMER_TYPE
        WHERE hp.CUSTOMER_ID = ?
    ");
    $stmt->execute([getCurrentCustomerId()]);
    $policies = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-house"></i> Home Policies</h2>
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
                <th>Premium</th>
                <th>Status</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($policies)): ?>
            <tr><td colspan="7" class="text-center text-muted">No home policies found.</td></tr>
        <?php else: ?>
        <?php foreach ($policies as $p): ?>
            <tr>
                <td><?= e($p['HOME_POLICY_ID']) ?></td>
                <td><?= e($p['FNAME'] . ' ' . $p['LNAME']) ?> (<?= e($p['CUSTOMER_ID']) ?>)</td>
                <td><?= date('M d, Y', strtotime($p['HOME_START_DATE'])) ?></td>
                <td><?= date('M d, Y', strtotime($p['HOME_END_DATE'])) ?></td>
                <td>$<?= number_format($p['HOME_AMOUNT'], 2) ?></td>
                <td><span class="badge <?= $p['HOME_STATUS'] === 'C' ? 'badge-current' : 'badge-expired' ?>"><?= $p['HOME_STATUS'] === 'C' ? 'Current' : 'Expired' ?></span></td>
                <?php if (isEmployee()): ?>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick='openEditHP(<?= json_encode($p) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline;" id="delhp-<?= e($p['HOME_POLICY_ID']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="policy_id" value="<?= e($p['HOME_POLICY_ID']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete('delhp-<?= e($p['HOME_POLICY_ID']) ?>')"><i class="bi bi-trash"></i></button>
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
                <div class="modal-header"><h5 class="modal-title">Add Home Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
                        <div class="col-md-6 mb-3"><label class="form-label">Premium ($)</label><input type="number" step="0.01" class="form-control" name="amount" required></div>
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
                <input type="hidden" name="policy_id" id="ehp_pid">
                <div class="modal-header"><h5 class="modal-title">Edit Home Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" id="ehp_sdate" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" id="ehp_edate" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Premium ($)</label><input type="number" step="0.01" class="form-control" name="amount" id="ehp_amt" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="ehp_status" required><option value="C">Current</option><option value="E">Expired</option></select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditHP(p) {
    document.getElementById('ehp_pid').value = p.HOME_POLICY_ID;
    document.getElementById('ehp_sdate').value = p.HOME_START_DATE;
    document.getElementById('ehp_edate').value = p.HOME_END_DATE;
    document.getElementById('ehp_amt').value = p.HOME_AMOUNT;
    document.getElementById('ehp_status').value = p.HOME_STATUS;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
