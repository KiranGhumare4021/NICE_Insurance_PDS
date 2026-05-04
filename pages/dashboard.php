
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
    $totalDrivers      = $db->query("SELECT COUNT(*) AS cnt FROM JKP_DRIVERS")->fetch()['cnt'];
    $totalAutoRev      = $db->query("SELECT COALESCE(SUM(INVOICE_AMOUNT),0) AS total FROM JKP_AUTO_INVOICE")->fetch()['total'];
    $totalHomeRev      = $db->query("SELECT COALESCE(SUM(INVOICE_AMOUNT),0) AS total FROM JKP_HOME_INVOICE")->fetch()['total'];

    $autoActive  = $db->query("SELECT COUNT(*) AS cnt FROM JKP_AUTO_POLICY WHERE AUTO_STATUS='C'")->fetch()['cnt'];
    $autoExpired = $db->query("SELECT COUNT(*) AS cnt FROM JKP_AUTO_POLICY WHERE AUTO_STATUS='E'")->fetch()['cnt'];
    $homeActive  = $db->query("SELECT COUNT(*) AS cnt FROM JKP_HOME_POLICY WHERE HOME_STATUS='C'")->fetch()['cnt'];
    $homeExpired = $db->query("SELECT COUNT(*) AS cnt FROM JKP_HOME_POLICY WHERE HOME_STATUS='E'")->fetch()['cnt'];

    $vOwned    = $db->query("SELECT COUNT(*) AS cnt FROM JKP_VEHICLES WHERE V_STATUS='O'")->fetch()['cnt'];
    $vFinanced = $db->query("SELECT COUNT(*) AS cnt FROM JKP_VEHICLES WHERE V_STATUS='F'")->fetch()['cnt'];
    $vLeased   = $db->query("SELECT COUNT(*) AS cnt FROM JKP_VEHICLES WHERE V_STATUS='L'")->fetch()['cnt'];

    $homeTypeData = $db->query("SELECT HOME_TYPE, COUNT(*) AS cnt FROM JKP_HOMES GROUP BY HOME_TYPE")->fetchAll();

    $autoPayTypes = $db->query("SELECT PAYMENT_TYPE, COUNT(*) AS cnt FROM JKP_AUTO_PAYMENT GROUP BY PAYMENT_TYPE")->fetchAll();
    $homePayTypes = $db->query("SELECT PAYMENT_TYPE, COUNT(*) AS cnt FROM JKP_HOME_PAYMENT GROUP BY PAYMENT_TYPE")->fetchAll();
    $payMethodCounts = [];
    foreach ($autoPayTypes as $r) { $payMethodCounts[$r['PAYMENT_TYPE']] = ($payMethodCounts[$r['PAYMENT_TYPE']] ?? 0) + $r['cnt']; }
    foreach ($homePayTypes as $r) { $payMethodCounts[$r['PAYMENT_TYPE']] = ($payMethodCounts[$r['PAYMENT_TYPE']] ?? 0) + $r['cnt']; }

    $topCustomers = $db->query("
        SELECT c.FNAME, c.LNAME, COALESCE(a.total,0)+COALESCE(h.total,0) AS GRAND_TOTAL
        FROM JKP_CUSTOMER c
        LEFT JOIN (SELECT ap.CUSTOMER_ID, ap.CUSTOMER_TYPE, SUM(i.INVOICE_AMOUNT) AS total FROM JKP_AUTO_POLICY ap JOIN JKP_AUTO_INVOICE i ON i.AUTO_POLICY_ID=ap.AUTO_POLICY_ID GROUP BY ap.CUSTOMER_ID, ap.CUSTOMER_TYPE) a ON a.CUSTOMER_ID=c.CUSTOMER_ID AND a.CUSTOMER_TYPE=c.CUSTOMER_TYPE
        LEFT JOIN (SELECT hp.CUSTOMER_ID, hp.CUSTOMER_TYPE, SUM(i.INVOICE_AMOUNT) AS total FROM JKP_HOME_POLICY hp JOIN JKP_HOME_INVOICE i ON i.HOME_POLICY_ID=hp.HOME_POLICY_ID GROUP BY hp.CUSTOMER_ID, hp.CUSTOMER_TYPE) h ON h.CUSTOMER_ID=c.CUSTOMER_ID AND h.CUSTOMER_TYPE=c.CUSTOMER_TYPE
        ORDER BY GRAND_TOTAL DESC LIMIT 5
    ")->fetchAll();

    $autoInv = $db->query("SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT, ap.CUSTOMER_ID, c.FNAME, c.LNAME FROM JKP_AUTO_INVOICE i JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID=i.AUTO_POLICY_ID JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID=ap.CUSTOMER_ID AND c.CUSTOMER_TYPE=ap.CUSTOMER_TYPE")->fetchAll();
    foreach ($autoInv as &$row) { $row['SOURCE'] = 'AUTO'; } unset($row);

    $homeInv = $db->query("SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT, hp.CUSTOMER_ID, c.FNAME, c.LNAME FROM JKP_HOME_INVOICE i JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID=i.HOME_POLICY_ID JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID=hp.CUSTOMER_ID AND c.CUSTOMER_TYPE=hp.CUSTOMER_TYPE")->fetchAll();
    foreach ($homeInv as &$row) { $row['SOURCE'] = 'HOME'; } unset($row);

    $recentInvoices = array_merge($autoInv, $homeInv);
    usort($recentInvoices, fn($a,$b) => strtotime($b['INVOICE_DATE']) - strtotime($a['INVOICE_DATE']));
    $recentInvoices = array_slice($recentInvoices, 0, 8);

} else {
    $custId = getCurrentCustomerId();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM JKP_AUTO_POLICY WHERE CUSTOMER_ID=?"); $stmt->execute([$custId]); $totalAutoPolicies = $stmt->fetch()['cnt'];
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM JKP_HOME_POLICY WHERE CUSTOMER_ID=?"); $stmt->execute([$custId]); $totalHomePolicies = $stmt->fetch()['cnt'];
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM JKP_VEHICLES WHERE CUSTOMER_ID=?"); $stmt->execute([$custId]); $totalVehicles = $stmt->fetch()['cnt'];

    $stmt = $db->prepare("SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT FROM JKP_AUTO_INVOICE i JOIN JKP_AUTO_POLICY ap ON ap.AUTO_POLICY_ID=i.AUTO_POLICY_ID WHERE ap.CUSTOMER_ID=? ORDER BY i.INVOICE_DATE DESC");
    $stmt->execute([$custId]); $autoInv = $stmt->fetchAll(); foreach ($autoInv as &$row) { $row['SOURCE'] = 'AUTO'; } unset($row);

    $stmt = $db->prepare("SELECT i.INVOICE_ID, i.INVOICE_DATE, i.INVOICE_AMOUNT FROM JKP_HOME_INVOICE i JOIN JKP_HOME_POLICY hp ON hp.HOME_POLICY_ID=i.HOME_POLICY_ID WHERE hp.CUSTOMER_ID=? ORDER BY i.INVOICE_DATE DESC");
    $stmt->execute([$custId]); $homeInv = $stmt->fetchAll(); foreach ($homeInv as &$row) { $row['SOURCE'] = 'HOME'; } unset($row);

    $recentInvoices = array_merge($autoInv, $homeInv);
    usort($recentInvoices, fn($a,$b) => strtotime($b['INVOICE_DATE']) - strtotime($a['INVOICE_DATE']));
    $recentInvoices = array_slice($recentInvoices, 0, 8);
}

include '../includes/header.php';
?>

<style>
    .dash-header { padding: 1.5rem 0 1rem; }
    .dash-header h1 { font-weight: 800; font-size: 1.9rem; color: #57068C; }
    .dash-header .greeting { color: #6b5f78; font-size: 0.95rem; }
    .dash-header .date-badge { background: #f3e8fc; color: #57068C; border: 1px solid #e0cff0; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }

    .kpi-card { background: #fff; border: 1px solid #e8e3ee; border-radius: 14px; padding: 1.25rem; transition: all 0.3s; position: relative; overflow: hidden; box-shadow: 0 2px 8px rgba(87,6,140,0.06); }
    .kpi-card::before { content:''; position:absolute; top:0;left:0;right:0; height:3px; border-radius:14px 14px 0 0; }
    .kpi-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(87,6,140,0.12); }
    .kpi-card .kpi-icon { width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem; }
    .kpi-card .kpi-value { font-weight:800;font-size:1.75rem;color:#2d2438;line-height:1; }
    .kpi-card .kpi-label { color:#6b5f78;font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;font-weight:500; }

    .kpi-violet::before{background:linear-gradient(90deg,#57068C,#8B2FC9);} .kpi-violet .kpi-icon{background:#f3e8fc;color:#57068C;}
    .kpi-green::before{background:linear-gradient(90deg,#0f9d58,#34d399);} .kpi-green .kpi-icon{background:#e6f7ef;color:#0f9d58;}
    .kpi-blue::before{background:linear-gradient(90deg,#4285f4,#7baaf7);} .kpi-blue .kpi-icon{background:#e8f0fe;color:#4285f4;}
    .kpi-amber::before{background:linear-gradient(90deg,#f29900,#fbbf24);} .kpi-amber .kpi-icon{background:#fef3e2;color:#f29900;}
    .kpi-rose::before{background:linear-gradient(90deg,#d93025,#f87171);} .kpi-rose .kpi-icon{background:#fde8e8;color:#d93025;}
    .kpi-teal::before{background:linear-gradient(90deg,#0097a7,#26c6da);} .kpi-teal .kpi-icon{background:#e0f7fa;color:#0097a7;}

    .rev-card { border-radius:14px; padding:1.25rem; }
    .rev-card.auto-rev { background:linear-gradient(135deg,#e6f7ef,#f0faf5); border:1px solid #c3e6d1; }
    .rev-card.home-rev { background:linear-gradient(135deg,#f3e8fc,#f8f0fd); border:1px solid #e0cff0; }
    .rev-card .rev-amount { font-weight:800;font-size:1.5rem;color:#2d2438; }
    .rev-card .rev-label { color:#6b5f78;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px; }

    .chart-panel { background:#fff; border:1px solid #e8e3ee; border-radius:14px; padding:1.5rem; box-shadow:0 2px 8px rgba(87,6,140,0.05); }
    .chart-panel h6 { font-weight:700;color:#57068C;font-size:0.9rem;margin-bottom:1rem; }
    .chart-container.small { max-height:220px; }

    .dash-table { background:#fff; border:1px solid #e8e3ee; border-radius:14px; padding:1.5rem; box-shadow:0 2px 8px rgba(87,6,140,0.05); }
    .dash-table h6 { font-weight:700;color:#57068C;font-size:0.9rem;margin-bottom:1rem; }
    .dash-table table { color:#2d2438;margin-bottom:0; }
    .dash-table thead th { background:#57068C;color:#fff;font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;border:none;padding:0.65rem 0.75rem; }
    .dash-table thead th:first-child{border-radius:8px 0 0 8px;} .dash-table thead th:last-child{border-radius:0 8px 8px 0;}
    .dash-table tbody td { border-bottom:1px solid #f4f1f7;padding:0.65rem 0.75rem;font-size:0.88rem; }
    .dash-table tbody tr:hover { background:#faf9fb; }
    .badge-auto { background:#e6f7ef;color:#0f9d58;font-weight:600; }
    .badge-home { background:#f3e8fc;color:#57068C;font-weight:600; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="dash-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-grid-1x2-fill"></i> Dashboard</h1>
        <span class="greeting">Welcome back, <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
    </div>
    <span class="date-badge"><i class="bi bi-calendar3"></i> <?= date('l, M d, Y') ?></span>
</div>

<?php if (isEmployee()): ?>

<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6"><div class="kpi-card kpi-violet"><div class="kpi-icon mb-2"><i class="bi bi-people-fill"></i></div><div class="kpi-value"><?= $totalCustomers ?></div><div class="kpi-label">Customers</div></div></div>
    <div class="col-xl-2 col-md-4 col-sm-6"><div class="kpi-card kpi-green"><div class="kpi-icon mb-2"><i class="bi bi-car-front-fill"></i></div><div class="kpi-value"><?= $totalAutoPolicies ?></div><div class="kpi-label">Auto Policies</div></div></div>
    <div class="col-xl-2 col-md-4 col-sm-6"><div class="kpi-card kpi-blue"><div class="kpi-icon mb-2"><i class="bi bi-house-fill"></i></div><div class="kpi-value"><?= $totalHomePolicies ?></div><div class="kpi-label">Home Policies</div></div></div>
    <div class="col-xl-2 col-md-4 col-sm-6"><div class="kpi-card kpi-amber"><div class="kpi-icon mb-2"><i class="bi bi-truck"></i></div><div class="kpi-value"><?= $totalVehicles ?></div><div class="kpi-label">Vehicles</div></div></div>
    <div class="col-xl-2 col-md-4 col-sm-6"><div class="kpi-card kpi-rose"><div class="kpi-icon mb-2"><i class="bi bi-houses-fill"></i></div><div class="kpi-value"><?= $totalHomes ?></div><div class="kpi-label">Insured Homes</div></div></div>
    <div class="col-xl-2 col-md-4 col-sm-6"><div class="kpi-card kpi-teal"><div class="kpi-icon mb-2"><i class="bi bi-person-vcard-fill"></i></div><div class="kpi-value"><?= $totalDrivers ?></div><div class="kpi-label">Drivers</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="rev-card auto-rev d-flex justify-content-between align-items-center">
            <div><div class="rev-label">Auto Insurance Revenue</div><div class="rev-amount">$<?= number_format($totalAutoRev, 2) ?></div></div>
            <i class="bi bi-car-front-fill" style="font-size:2.5rem;color:rgba(15,157,88,0.2);"></i>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rev-card home-rev d-flex justify-content-between align-items-center">
            <div><div class="rev-label">Home Insurance Revenue</div><div class="rev-amount">$<?= number_format($totalHomeRev, 2) ?></div></div>
            <i class="bi bi-house-fill" style="font-size:2.5rem;color:rgba(87,6,140,0.15);"></i>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4"><div class="chart-panel"><h6><i class="bi bi-pie-chart-fill"></i> Revenue Split</h6><div class="chart-container small"><canvas id="revenueChart"></canvas></div></div></div>
    <div class="col-lg-4"><div class="chart-panel"><h6><i class="bi bi-shield-check"></i> Policy Status</h6><div class="chart-container small"><canvas id="policyStatusChart"></canvas></div></div></div>
    <div class="col-lg-4"><div class="chart-panel"><h6><i class="bi bi-car-front"></i> Vehicle Ownership</h6><div class="chart-container small"><canvas id="vehicleChart"></canvas></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6"><div class="chart-panel"><h6><i class="bi bi-trophy-fill"></i> Top 5 Customers by Revenue</h6><div class="chart-container" style="height:260px;"><canvas id="topCustomersChart"></canvas></div></div></div>
    <div class="col-lg-3"><div class="chart-panel"><h6><i class="bi bi-credit-card"></i> Payment Methods</h6><div class="chart-container small"><canvas id="paymentChart"></canvas></div></div></div>
    <div class="col-lg-3"><div class="chart-panel"><h6><i class="bi bi-house-door"></i> Home Types</h6><div class="chart-container small"><canvas id="homeTypeChart"></canvas></div></div></div>
</div>

<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="kpi-card kpi-green"><div class="kpi-icon mb-2"><i class="bi bi-car-front-fill"></i></div><div class="kpi-value"><?= $totalAutoPolicies ?></div><div class="kpi-label">Auto Policies</div></div></div>
    <div class="col-md-4"><div class="kpi-card kpi-blue"><div class="kpi-icon mb-2"><i class="bi bi-house-fill"></i></div><div class="kpi-value"><?= $totalHomePolicies ?></div><div class="kpi-label">Home Policies</div></div></div>
    <div class="col-md-4"><div class="kpi-card kpi-amber"><div class="kpi-icon mb-2"><i class="bi bi-truck"></i></div><div class="kpi-value"><?= $totalVehicles ?></div><div class="kpi-label">Vehicles</div></div></div>
</div>
<?php endif; ?>

<div class="dash-table mb-4">
    <h6><i class="bi bi-clock-history"></i> Recent Invoices</h6>
    <table class="table table-sm">
        <thead><tr><th>Type</th><th>Invoice #</th><?php if (isEmployee()): ?><th>Customer</th><?php endif; ?><th>Date</th><th>Amount</th></tr></thead>
        <tbody>
            <?php if (empty($recentInvoices)): ?>
                <tr><td colspan="<?= isEmployee()?5:4 ?>" class="text-center" style="color:#6b5f78;">No invoices found.</td></tr>
            <?php else: foreach ($recentInvoices as $inv): ?>
                <tr>
                    <td><span class="badge <?= $inv['SOURCE']==='AUTO'?'badge-auto':'badge-home' ?>"><?= e($inv['SOURCE']) ?></span></td>
                    <td><?= e($inv['INVOICE_ID']) ?></td>
                    <?php if (isEmployee()): ?><td><?= e(($inv['FNAME']??'').' '.($inv['LNAME']??'')) ?></td><?php endif; ?>
                    <td><?= date('M d, Y', strtotime($inv['INVOICE_DATE'])) ?></td>
                    <td style="color:#0f9d58;font-weight:600;">$<?= number_format($inv['INVOICE_AMOUNT'], 2) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if (isEmployee()): ?>
<script>
Chart.defaults.color = '#6b5f78';
Chart.defaults.borderColor = 'rgba(87,6,140,0.06)';

new Chart(document.getElementById('revenueChart'), {
    type:'doughnut',
    data:{labels:['Auto Revenue','Home Revenue'],datasets:[{data:[<?=$totalAutoRev?>,<?=$totalHomeRev?>],backgroundColor:['#0f9d58','#57068C'],borderWidth:0,hoverOffset:8}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{padding:15,usePointStyle:true}}}}
});

new Chart(document.getElementById('policyStatusChart'), {
    type:'bar',
    data:{labels:['Auto','Home'],datasets:[{label:'Active',data:[<?=$autoActive?>,<?=$homeActive?>],backgroundColor:'#0f9d58',borderRadius:6},{label:'Expired',data:[<?=$autoExpired?>,<?=$homeExpired?>],backgroundColor:'#d93025',borderRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true,ticks:{stepSize:1}}},plugins:{legend:{position:'bottom',labels:{padding:15,usePointStyle:true}}}}
});

new Chart(document.getElementById('vehicleChart'), {
    type:'doughnut',
    data:{labels:['Owned','Financed','Leased'],datasets:[{data:[<?=$vOwned?>,<?=$vFinanced?>,<?=$vLeased?>],backgroundColor:['#f29900','#4285f4','#d93025'],borderWidth:0,hoverOffset:8}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{padding:15,usePointStyle:true}}}}
});

new Chart(document.getElementById('topCustomersChart'), {
    type:'bar',
    data:{labels:[<?php foreach($topCustomers as $tc):?>'<?=e($tc['FNAME'].' '.$tc['LNAME'])?>',<?php endforeach;?>],
        datasets:[{label:'Total Revenue ($)',data:[<?php foreach($topCustomers as $tc):?><?=$tc['GRAND_TOTAL']?>,<?php endforeach;?>],
        backgroundColor:['#57068C','#0f9d58','#f29900','#4285f4','#d93025'],borderRadius:8,barThickness:28}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,scales:{x:{grid:{display:false},ticks:{callback:v=>'$'+v.toLocaleString()}},y:{grid:{display:false}}},plugins:{legend:{display:false}}}
});

var payLabels={'C':'Credit','D':'Debit','P':'PayPal','K':'Check'};
new Chart(document.getElementById('paymentChart'), {
    type:'pie',
    data:{labels:[<?php foreach($payMethodCounts as $k=>$v):?>payLabels['<?=$k?>']||'<?=$k?>',<?php endforeach;?>],
        datasets:[{data:[<?php foreach($payMethodCounts as $k=>$v):?><?=$v?>,<?php endforeach;?>],backgroundColor:['#57068C','#0f9d58','#f29900','#d93025'],borderWidth:2,borderColor:'#fff',hoverOffset:8}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:10,usePointStyle:true,font:{size:11}}},tooltip:{callbacks:{label:function(ctx){var total=ctx.dataset.data.reduce((a,b)=>a+b,0);var pct=Math.round(ctx.raw/total*100);return ctx.label+': '+ctx.raw+' ('+pct+'%)'}}}}}
});

var htLabels={'S':'Single Family','M':'Multi-Family','C':'Condo','T':'Town House'};
new Chart(document.getElementById('homeTypeChart'), {
    type:'pie',
    data:{labels:[<?php foreach($homeTypeData as $ht):?>htLabels['<?=$ht['HOME_TYPE']?>']||'<?=$ht['HOME_TYPE']?>',<?php endforeach;?>],
        datasets:[{data:[<?php foreach($homeTypeData as $ht):?><?=$ht['cnt']?>,<?php endforeach;?>],backgroundColor:['#4285f4','#57068C','#d93025','#f29900'],borderWidth:0,hoverOffset:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:10,usePointStyle:true,font:{size:11}}}}}
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
