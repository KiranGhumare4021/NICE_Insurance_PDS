<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Dashboard - NICE Insurance';

if (isEmployee()) {
    $totalCustomers    = $db->query("SELECT COUNT(DISTINCT CUSTOMER_ID) AS cnt FROM JKP_CUSTOMER")->fetch()['cnt'];
    $totalAutoPolicies = $db->query("SELECT COUNT(*) AS cnt FROM JKP_AUTO_POLICY")->fetch()['cnt'];
    $totalHomePolicies = $db->query("SELECT COUNT(*) AS cnt FROM JKP_HOME_POLICY")->fetch()['cnt'];
    $totalVehicles     = $db->query("SELECT COUNT(*) AS cnt FROM JKP_VEHICLES")->fetch()['cnt'];
    $totalHomes        = $db->query("SELECT COUNT(*) AS cnt FROM JKP_HOMES")->fetch()['cnt'];
    $totalAutoRev      = $db->query("SELECT COALESCE(SUM(INVOICE_AMOUNT),0) AS total FROM JKP_AUTO_INVOICE")->fetch()['total'];
    $totalHomeRev      = $db->query("SELECT COALESCE(SUM(INVOICE_AMOUNT),0) AS total FROM JKP_HOME_INVOICE")->fetch()['total'];

    // Recent auto invoices: INVOICE -> AUTO_POLICY -> CUSTOMER
    $autoInv = $db->query("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT, ap.CUSTOMER_ID, c.FNAME, c.LNAME
        FROM JKP_AUTO_INVOICE i
        JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID = i.AUTO_POLICY_ID
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = ap.CUSTOMER_ID AND c.CUSTOMER_TYPE = ap.CUSTOMER_TYPE
    ")->fetchAll();
    foreach ($autoInv as &$row) { $row['SOURCE'] = 'AUTO'; }
    unset($row);

    // Recent home invoices: INVOICE -> HOME_POLICY -> CUSTOMER
    $homeInv = $db->query("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT, hp.CUSTOMER_ID, c.FNAME, c.LNAME
        FROM JKP_HOME_INVOICE i
        JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID = i.HOME_POLICY_ID
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = hp.CUSTOMER_ID AND c.CUSTOMER_TYPE = hp.CUSTOMER_TYPE
    ")->fetchAll();
    foreach ($homeInv as &$row) { $row['SOURCE'] = 'HOME'; }
    unset($row);

    $recentInvoices = array_merge($autoInv, $homeInv);
    usort($recentInvoices, function($a, $b) {
        return strtotime($b['INVOICE_DATE']) - strtotime($a['INVOICE_DATE']);
    });
    $recentInvoices = array_slice($recentInvoices, 0, 10);

} else {
    $custId = getCurrentCustomerId();

    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM JKP_AUTO_POLICY WHERE CUSTOMER_ID = ?");
    $stmt->execute([$custId]);
    $totalAutoPolicies = $stmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM JKP_HOME_POLICY WHERE CUSTOMER_ID = ?");
    $stmt->execute([$custId]);
    $totalHomePolicies = $stmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM JKP_VEHICLES WHERE CUSTOMER_ID = ?");
    $stmt->execute([$custId]);
    $totalVehicles = $stmt->fetch()['cnt'];

    // Auto invoices for this customer: go through policy
    $stmt = $db->prepare("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT
        FROM JKP_AUTO_INVOICE i
        JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID = i.AUTO_POLICY_ID
        WHERE ap.CUSTOMER_ID = ?
        ORDER BY i.INVOICE_DATE DESC
    ");
    $stmt->execute([$custId]);
    $autoInv = $stmt->fetchAll();
    foreach ($autoInv as &$row) { $row['SOURCE'] = 'AUTO'; }
    unset($row);

    $stmt = $db->prepare("
        SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT
        FROM JKP_HOME_INVOICE i
        JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID = i.HOME_POLICY_ID
        WHERE hp.CUSTOMER_ID = ?
        ORDER BY i.INVOICE_DATE DESC
    ");
    $stmt->execute([$custId]);
    $homeInv = $stmt->fetchAll();
    foreach ($homeInv as &$row) { $row['SOURCE'] = 'HOME'; }
    unset($row);

    $recentInvoices = array_merge($autoInv, $homeInv);
    usort($recentInvoices, function($a, $b) {
        return strtotime($b['INVOICE_DATE']) - strtotime($a['INVOICE_DATE']);
    });
    $recentInvoices = array_slice($recentInvoices, 0, 10);
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
    <span class="text-muted">Welcome back, <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?>!</span>
</div>

<div class="row g-4 mb-4">
    <?php if (isEmployee()): ?>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?= $totalCustomers ?></div>
                    <div class="stat-label">Customers</div>
                </div>
                <i class="bi bi-people stat-icon text-primary"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?= $totalAutoPolicies ?></div>
                    <div class="stat-label">Auto Policies</div>
                </div>
                <i class="bi bi-car-front stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?= $totalHomePolicies ?></div>
                    <div class="stat-label">Home Policies</div>
                </div>
                <i class="bi bi-house stat-icon text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?= $totalVehicles ?></div>
                    <div class="stat-label">Vehicles</div>
                </div>
                <i class="bi bi-truck stat-icon text-warning"></i>
            </div>
        </div>
    </div>
</div>

<?php if (isEmployee()): ?>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?= $totalHomes ?></div>
                    <div class="stat-label">Insured Homes</div>
                </div>
                <i class="bi bi-houses stat-icon text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">$<?= number_format($totalAutoRev, 2) ?></div>
                    <div class="stat-label">Auto Revenue</div>
                </div>
                <i class="bi bi-currency-dollar stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">$<?= number_format($totalHomeRev, 2) ?></div>
                    <div class="stat-label">Home Revenue</div>
                </div>
                <i class="bi bi-currency-dollar stat-icon text-info"></i>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="table-container">
    <h5><i class="bi bi-clock-history"></i> Recent Invoices</h5>
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>Type</th>
                <th>Invoice #</th>
                <?php if (isEmployee()): ?><th>Customer</th><?php endif; ?>
                <th>Date</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentInvoices)): ?>
                <tr><td colspan="<?= isEmployee() ? 5 : 4 ?>" class="text-center text-muted">No invoices found.</td></tr>
            <?php else: ?>
                <?php foreach ($recentInvoices as $inv): ?>
                <tr>
                    <td><span class="badge bg-<?= $inv['SOURCE'] === 'AUTO' ? 'success' : 'info' ?>"><?= e($inv['SOURCE']) ?></span></td>
                    <td><?= e($inv['INVOICE_ID']) ?></td>
                    <?php if (isEmployee()): ?>
                        <td><?= e(($inv['FNAME'] ?? '') . ' ' . ($inv['LNAME'] ?? '')) ?> (<?= e($inv['CUSTOMER_ID'] ?? '') ?>)</td>
                    <?php endif; ?>
                    <td><?= date('M d, Y', strtotime($inv['INVOICE_DATE'])) ?></td>
                    <td>$<?= number_format($inv['INVOICE_AMOUNT'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
