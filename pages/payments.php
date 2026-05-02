<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Payments - NICE Insurance';

$payTypes = ['P' => 'PayPal', 'C' => 'Credit', 'D' => 'Debit', 'K' => 'Check'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '') && isEmployee()) {
    $action = $_POST['action'] ?? '';
    $source = $_POST['source'] ?? 'AUTO';
    try {
        if ($action === 'create') {
            $db->beginTransaction();
            $table = $source === 'HOME' ? 'JKP_HOME_PAYMENT' : 'JKP_AUTO_PAYMENT';
            $stmt = $db->prepare("INSERT INTO $table (PAYMENT_ID, PAYMENT_DATE, PAYMENT_TYPE, INVOICE_ID) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['payment_id'], $_POST['payment_date'], $_POST['payment_type'], $_POST['invoice_id']]);
            $db->commit();
            setFlash('success', 'Payment recorded.');
        }

        if ($action === 'delete') {
            $db->beginTransaction();
            $table = $source === 'HOME' ? 'JKP_HOME_PAYMENT' : 'JKP_AUTO_PAYMENT';
            $db->prepare("DELETE FROM $table WHERE PAYMENT_ID=?")->execute([$_POST['payment_id']]);
            $db->commit();
            setFlash('success', 'Payment deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: payments.php');
    exit;
}

// Fetch payments - join through invoice -> policy -> customer
if (isEmployee()) {
    $autoPayments = $db->query("
        SELECT p.PAYMENT_ID, p.PAYMENT_DATE, p.PAYMENT_TYPE, p.INVOICE_ID, i.INVOICE_AMOUNT, ap.CUSTOMER_ID, c.FNAME, c.LNAME
        FROM JKP_AUTO_PAYMENT p
        JOIN JKP_AUTO_INVOICE i ON i.INVOICE_ID = p.INVOICE_ID
        JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID = i.AUTO_POLICY_ID
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = ap.CUSTOMER_ID AND c.CUSTOMER_TYPE = ap.CUSTOMER_TYPE
    ")->fetchAll();
    foreach ($autoPayments as &$row) { $row['SOURCE'] = 'AUTO'; }
    unset($row);

    $homePayments = $db->query("
        SELECT p.PAYMENT_ID, p.PAYMENT_DATE, p.PAYMENT_TYPE, p.INVOICE_ID, i.INVOICE_AMOUNT, hp.CUSTOMER_ID, c.FNAME, c.LNAME
        FROM JKP_HOME_PAYMENT p
        JOIN JKP_HOME_INVOICE i ON i.INVOICE_ID = p.INVOICE_ID
        JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID = i.HOME_POLICY_ID
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = hp.CUSTOMER_ID AND c.CUSTOMER_TYPE = hp.CUSTOMER_TYPE
    ")->fetchAll();
    foreach ($homePayments as &$row) { $row['SOURCE'] = 'HOME'; }
    unset($row);
} else {
    $custId = getCurrentCustomerId();

    $stmt = $db->prepare("
        SELECT p.PAYMENT_ID, p.PAYMENT_DATE, p.PAYMENT_TYPE, p.INVOICE_ID, i.INVOICE_AMOUNT
        FROM JKP_AUTO_PAYMENT p
        JOIN JKP_AUTO_INVOICE i ON i.INVOICE_ID = p.INVOICE_ID
        JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID = i.AUTO_POLICY_ID
        WHERE ap.CUSTOMER_ID = ?
    ");
    $stmt->execute([$custId]);
    $autoPayments = $stmt->fetchAll();
    foreach ($autoPayments as &$row) { $row['SOURCE'] = 'AUTO'; }
    unset($row);

    $stmt = $db->prepare("
        SELECT p.PAYMENT_ID, p.PAYMENT_DATE, p.PAYMENT_TYPE, p.INVOICE_ID, i.INVOICE_AMOUNT
        FROM JKP_HOME_PAYMENT p
        JOIN JKP_HOME_INVOICE i ON i.INVOICE_ID = p.INVOICE_ID
        JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID = i.HOME_POLICY_ID
        WHERE hp.CUSTOMER_ID = ?
    ");
    $stmt->execute([$custId]);
    $homePayments = $stmt->fetchAll();
    foreach ($homePayments as &$row) { $row['SOURCE'] = 'HOME'; }
    unset($row);
}

$payments = array_merge($autoPayments, $homePayments);
usort($payments, function($a, $b) {
    return strtotime($b['PAYMENT_DATE']) - strtotime($a['PAYMENT_DATE']);
});

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-credit-card"></i> Payments</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Record Payment
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>Type</th>
                <th>Payment #</th>
                <?php if (isEmployee()): ?><th>Customer</th><?php endif; ?>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Method</th>
                <th>Invoice Amount</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($payments)): ?>
            <tr><td colspan="<?= isEmployee() ? 8 : 6 ?>" class="text-center text-muted">No payments found.</td></tr>
        <?php else: ?>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><span class="badge bg-<?= $p['SOURCE'] === 'AUTO' ? 'success' : 'info' ?>"><?= e($p['SOURCE']) ?></span></td>
                <td><?= e($p['PAYMENT_ID']) ?></td>
                <?php if (isEmployee()): ?>
                    <td><?= e($p['FNAME'] . ' ' . $p['LNAME']) ?> (<?= e($p['CUSTOMER_ID']) ?>)</td>
                <?php endif; ?>
                <td><?= e($p['INVOICE_ID']) ?></td>
                <td><?= date('M d, Y', strtotime($p['PAYMENT_DATE'])) ?></td>
                <td><span class="badge bg-dark"><?= e($payTypes[$p['PAYMENT_TYPE']] ?? $p['PAYMENT_TYPE']) ?></span></td>
                <td>$<?= number_format($p['INVOICE_AMOUNT'], 2) ?></td>
                <?php if (isEmployee()): ?>
                <td>
                    <form method="POST" style="display:inline;" id="delp-<?= e($p['PAYMENT_ID']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="payment_id" value="<?= e($p['PAYMENT_ID']) ?>">
                        <input type="hidden" name="source" value="<?= e($p['SOURCE']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete('delp-<?= e($p['PAYMENT_ID']) ?>')"><i class="bi bi-trash"></i></button>
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
                <div class="modal-header"><h5 class="modal-title">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Payment ID</label><input type="number" class="form-control" name="payment_id" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Type</label><select class="form-select" name="source" required><option value="AUTO">Auto</option><option value="HOME">Home</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Invoice ID</label><input type="number" class="form-control" name="invoice_id" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Payment Date</label><input type="date" class="form-control" name="payment_date" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_type" required>
                            <option value="C">Credit Card</option>
                            <option value="D">Debit Card</option>
                            <option value="P">PayPal</option>
                            <option value="K">Check</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
