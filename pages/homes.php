<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Homes - NICE Insurance';

$homeTypes = ['S' => 'Single Family', 'M' => 'Multi-Family', 'C' => 'Condo', 'T' => 'Town House'];
$poolTypes = ['U' => 'Underground', 'O' => 'Overground', 'M' => 'Multiple', 'I' => 'Indoor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO JKP_HOMES (HOME_ID, PURCHASE_DATE, HOME_TYPE, HOME_VALUE, HOME_SIZE, FIRE_ALARM, HOME_ALARM, POOL, BASEMENT, CUSTOMER_ID, CUSTOMER_TYPE)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'H')");
            $stmt->execute([
                $_POST['home_id'], $_POST['purchase_date'], $_POST['home_type'], $_POST['home_value'],
                $_POST['home_size'], $_POST['fire_alarm'], $_POST['home_alarm'], $_POST['pool'] ?: null,
                $_POST['basement'], $_POST['customer_id']
            ]);
            $db->commit();
            setFlash('success', 'Home added.');
        }

        if ($action === 'update' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE JKP_HOMES SET PURCHASE_DATE=?, HOME_TYPE=?, HOME_VALUE=?, HOME_SIZE=?, FIRE_ALARM=?, HOME_ALARM=?, POOL=?, BASEMENT=? WHERE HOME_ID=?");
            $stmt->execute([
                $_POST['purchase_date'], $_POST['home_type'], $_POST['home_value'], $_POST['home_size'],
                $_POST['fire_alarm'], $_POST['home_alarm'], $_POST['pool'] ?: null, $_POST['basement'], $_POST['home_id']
            ]);
            $db->commit();
            setFlash('success', 'Home updated.');
        }

        if ($action === 'delete' && isEmployee()) {
            $db->beginTransaction();
            $db->prepare("DELETE FROM JKP_HOMES WHERE HOME_ID=?")->execute([$_POST['home_id']]);
            $db->commit();
            setFlash('success', 'Home deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: homes.php');
    exit;
}

if (isEmployee()) {
    $homes = $db->query("
        SELECT h.*, c.FNAME, c.LNAME
        FROM JKP_HOMES h
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = h.CUSTOMER_ID AND c.CUSTOMER_TYPE = h.CUSTOMER_TYPE
        ORDER BY h.CUSTOMER_ID
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT h.*, c.FNAME, c.LNAME
        FROM JKP_HOMES h
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = h.CUSTOMER_ID AND c.CUSTOMER_TYPE = h.CUSTOMER_TYPE
        WHERE h.CUSTOMER_ID = ?
    ");
    $stmt->execute([getCurrentCustomerId()]);
    $homes = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-houses"></i> Homes</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Home
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Value</th>
                <th>Size (sqft)</th>
                <th>Fire</th>
                <th>Security</th>
                <th>Pool</th>
                <th>Basement</th>
                <th>Purchased</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($homes)): ?>
            <tr><td colspan="11" class="text-center text-muted">No homes found.</td></tr>
        <?php else: ?>
        <?php foreach ($homes as $h): ?>
            <tr>
                <td><?= e($h['HOME_ID']) ?></td>
                <td><?= e($h['FNAME'] . ' ' . $h['LNAME']) ?></td>
                <td><?= e($homeTypes[$h['HOME_TYPE']] ?? $h['HOME_TYPE']) ?></td>
                <td>$<?= number_format($h['HOME_VALUE'], 2) ?></td>
                <td><?= number_format($h['HOME_SIZE']) ?></td>
                <td><?= $h['FIRE_ALARM'] === '1' ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td><?= $h['HOME_ALARM'] === '1' ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td><?= e($poolTypes[$h['POOL']] ?? 'None') ?></td>
                <td><?= $h['BASEMENT'] === '1' ? 'Yes' : 'No' ?></td>
                <td><?= date('M d, Y', strtotime($h['PURCHASE_DATE'])) ?></td>
                <?php if (isEmployee()): ?>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick='openEditH(<?= json_encode($h) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline;" id="delh-<?= e($h['HOME_ID']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="home_id" value="<?= e($h['HOME_ID']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete('delh-<?= e($h['HOME_ID']) ?>')"><i class="bi bi-trash"></i></button>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Add Home</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Home ID</label><input type="number" class="form-control" name="home_id" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Customer ID</label><input type="number" class="form-control" name="customer_id" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Purchase Date</label><input type="date" class="form-control" name="purchase_date" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Type</label><select class="form-select" name="home_type" required><?php foreach ($homeTypes as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Value ($)</label><input type="number" step="0.01" class="form-control" name="home_value" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Size (sqft)</label><input type="number" step="0.01" class="form-control" name="home_size" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Fire Alarm</label><select class="form-select" name="fire_alarm" required><option value="1">Yes</option><option value="0">No</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Security</label><select class="form-select" name="home_alarm" required><option value="1">Yes</option><option value="0">No</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Pool</label><select class="form-select" name="pool"><option value="">None</option><?php foreach ($poolTypes as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Basement</label><select class="form-select" name="basement" required><option value="1">Yes</option><option value="0">No</option></select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="home_id" id="eh_hid">
                <div class="modal-header"><h5 class="modal-title">Edit Home</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Purchase Date</label><input type="date" class="form-control" name="purchase_date" id="eh_pd" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Type</label><select class="form-select" name="home_type" id="eh_ht" required><?php foreach ($homeTypes as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Value ($)</label><input type="number" step="0.01" class="form-control" name="home_value" id="eh_hv" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Size</label><input type="number" step="0.01" class="form-control" name="home_size" id="eh_hs" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Fire Alarm</label><select class="form-select" name="fire_alarm" id="eh_fa" required><option value="1">Yes</option><option value="0">No</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Security</label><select class="form-select" name="home_alarm" id="eh_ha" required><option value="1">Yes</option><option value="0">No</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Basement</label><select class="form-select" name="basement" id="eh_base" required><option value="1">Yes</option><option value="0">No</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Pool</label><select class="form-select" name="pool" id="eh_pool"><option value="">None</option><?php foreach ($poolTypes as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditH(h) {
    document.getElementById('eh_hid').value = h.HOME_ID;
    document.getElementById('eh_pd').value = h.PURCHASE_DATE;
    document.getElementById('eh_ht').value = h.HOME_TYPE;
    document.getElementById('eh_hv').value = h.HOME_VALUE;
    document.getElementById('eh_hs').value = h.HOME_SIZE;
    document.getElementById('eh_fa').value = h.FIRE_ALARM;
    document.getElementById('eh_ha').value = h.HOME_ALARM;
    document.getElementById('eh_pool').value = h.POOL || '';
    document.getElementById('eh_base').value = h.BASEMENT;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
