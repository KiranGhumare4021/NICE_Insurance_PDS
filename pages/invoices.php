<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Invoices - NICE Insurance';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '') && isEmployee()) {
    $action = $_POST['action'] ?? '';
    $source = $_POST['source'] ?? 'AUTO';
    try {
        if ($action === 'create') {
            $db->beginTransaction();
            if ($source === 'HOME') {
                $stmt = $db->prepare("INSERT INTO JKP_HOME_INVOICE (INVOICE_ID, INVOICE_DATE, DUE_DATE, INVOICE_AMOUNT, HOME_POLICY_ID) VALUES (?, ?, ?, ?, ?)");
            } else {
                $stmt = $db->prepare("INSERT INTO JKP_AUTO_INVOICE (INVOICE_ID, INVOICE_DATE, DUE_DATE, INVOICE_AMOUNT, AUTO_POLICY_ID) VALUES (?, ?, ?, ?, ?)");
            }
            $stmt->execute([$_POST['invoice_id'], $_POST['invoice_date'], $_POST['due_date'], $_POST['amount'], $_POST['policy_id']]);
            $db->commit();
            setFlash('success', 'Invoice created.');
        }

        if ($action === 'delete') {
            $db->beginTransaction();
            $payTable = $source === 'HOME' ? 'JKP_HOME_PAYMENT' : 'JKP_AUTO_PAYMENT';
            $invTable = $source === 'HOME' ? 'JKP_HOME_INVOICE' : 'JKP_AUTO_INVOICE';
            $db->prepare("DELETE FROM $payTable WHERE INVOICE_ID=?")->execute([$_POST['invoice_id']]);
            $db->prepare("DELETE FROM $invTable WHERE INVOICE_ID=?")->execute([$_POST['invoice_id']]);
            $db->commit();
            setFlash('success', 'Invoice and payments deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: invoices.php');
    exit;
}

// Fetch invoices - join through policy to get customer name
if (isEmployee()) {
    $autoInv = $db->query("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.DUE_DATE, i.INVOICE_AMOUNT, i.AUTO_POLICY_ID AS POLICY_ID, ap.CUSTOMER_ID, c.FNAME, c.LNAME
        FROM JKP_AUTO_INVOICE i
        JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID = i.AUTO_POLICY_ID
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = ap.CUSTOMER_ID AND c.CUSTOMER_TYPE = ap.CUSTOMER_TYPE
    ")->fetchAll();
    foreach ($autoInv as &$row) { $row['SOURCE'] = 'AUTO'; }
    unset($row);

    $homeInv = $db->query("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.DUE_DATE, i.INVOICE_AMOUNT, i.HOME_POLICY_ID AS POLICY_ID, hp.CUSTOMER_ID, c.FNAME, c.LNAME
        FROM JKP_HOME_INVOICE i
        JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID = i.HOME_POLICY_ID
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = hp.CUSTOMER_ID AND c.CUSTOMER_TYPE = hp.CUSTOMER_TYPE
    ")->fetchAll();
    foreach ($homeInv as &$row) { $row['SOURCE'] = 'HOME'; }
    unset($row);
} else {
    $custId = getCurrentCustomerId();

    $stmt = $db->prepare("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.DUE_DATE, i.INVOICE_AMOUNT, i.AUTO_POLICY_ID AS POLICY_ID
        FROM JKP_AUTO_INVOICE i
        JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID = i.AUTO_POLICY_ID
        WHERE ap.CUSTOMER_ID = ?
    ");
    $stmt->execute([$custId]);
    $autoInv = $stmt->fetchAll();
    foreach ($autoInv as &$row) { $row['SOURCE'] = 'AUTO'; }
    unset($row);

    $stmt = $db->prepare("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.DUE_DATE, i.INVOICE_AMOUNT, i.HOME_POLICY_ID AS POLICY_ID
        FROM JKP_HOME_INVOICE i
        JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID = i.HOME_POLICY_ID
        WHERE hp.CUSTOMER_ID = ?
    ");
    $stmt->execute([$custId]);
    $homeInv = $stmt->fetchAll();
    foreach ($homeInv as &$row) { $row['SOURCE'] = 'HOME'; }
    unset($row);
}

$invoices = array_merge($autoInv, $homeInv);
usort($invoices, function($a, $b) {
    return strtotime($b['INVOICE_DATE']) - strtotime($a['INVOICE_DATE']);
});

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-receipt"></i> Invoices</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Create Invoice
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>Type</th>
                <th>Invoice #</th>
                <th>Policy ID</th>
                <?php if (isEmployee()): ?><th>Customer</th><?php endif; ?>
                <th>Invoice Date</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Status</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($invoices)): ?>
            <tr><td colspan="<?= isEmployee() ? 9 : 7 ?>" class="text-center text-muted">No invoices found.</td></tr>
        <?php else: ?>
        <?php foreach ($invoices as $inv):
            $isPastDue = strtotime($inv['DUE_DATE']) < time();
        ?>
            <tr>
                <td><span class="badge bg-<?= $inv['SOURCE'] === 'AUTO' ? 'success' : 'info' ?>"><?= e($inv['SOURCE']) ?></span></td>
                <td><?= e($inv['INVOICE_ID']) ?></td>
                <td><?= e($inv['POLICY_ID']) ?></td>
                <?php if (isEmployee()): ?>
                    <td><?= e($inv['FNAME'] . ' ' . $inv['LNAME']) ?> (<?= e($inv['CUSTOMER_ID']) ?>)</td>
                <?php endif; ?>
                <td><?= date('M d, Y', strtotime($inv['INVOICE_DATE'])) ?></td>
                <td><?= date('M d, Y', strtotime($inv['DUE_DATE'])) ?></td>
                <td>$<?= number_format($inv['INVOICE_AMOUNT'], 2) ?></td>
                <td><span class="badge bg-<?= $isPastDue ? 'danger' : 'warning' ?>"><?= $isPastDue ? 'Past Due' : 'Pending' ?></span></td>
                <?php if (isEmployee()): ?>
                <td>
                    <form method="POST" style="display:inline;" id="deli-<?= e($inv['INVOICE_ID']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="invoice_id" value="<?= e($inv['INVOICE_ID']) ?>">
                        <input type="hidden" name="source" value="<?= e($inv['SOURCE']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete('deli-<?= e($inv['INVOICE_ID']) ?>')"><i class="bi bi-trash"></i></button>
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
                <div class="modal-header"><h5 class="modal-title">Create Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Invoice ID</label><input type="number" class="form-control" name="invoice_id" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Type</label><select class="form-select" name="source" required><option value="AUTO">Auto</option><option value="HOME">Home</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Policy ID (Auto Policy ID or Home Policy ID)</label><input type="number" class="form-control" name="policy_id" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Invoice Date</label><input type="date" class="form-control" name="invoice_date" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Due Date</label><input type="date" class="form-control" name="due_date" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Amount ($)</label><input type="number" step="0.01" class="form-control" name="amount" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
