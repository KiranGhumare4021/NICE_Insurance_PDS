<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Vehicles - NICE Insurance';

$statusMap = ['L' => 'Leased', 'F' => 'Financed', 'O' => 'Owned'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO JKP_VEHICLES (VIN, MAKE, MODEL, V_YEAR, V_STATUS, CUSTOMER_ID, CUSTOMER_TYPE)
                VALUES (?, ?, ?, ?, ?, ?, 'A')");
            $stmt->execute([$_POST['vin'], $_POST['make'], $_POST['model'], $_POST['v_year'], $_POST['v_status'], $_POST['customer_id']]);
            $db->commit();
            setFlash('success', 'Vehicle added.');
        }

        if ($action === 'update' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE JKP_VEHICLES SET MAKE=?, MODEL=?, V_YEAR=?, V_STATUS=?, CUSTOMER_ID=? WHERE VIN=?");
            $stmt->execute([$_POST['make'], $_POST['model'], $_POST['v_year'], $_POST['v_status'], $_POST['customer_id'], $_POST['vin']]);
            $db->commit();
            setFlash('success', 'Vehicle updated.');
        }

        if ($action === 'delete' && isEmployee()) {
            $db->beginTransaction();
            // Delete drivers linked to this vehicle first (column is VEHICLES_VIN)
            $db->prepare("DELETE FROM JKP_DRIVERS WHERE VEHICLES_VIN=?")->execute([$_POST['vin']]);
            $db->prepare("DELETE FROM JKP_VEHICLES WHERE VIN=?")->execute([$_POST['vin']]);
            $db->commit();
            setFlash('success', 'Vehicle and linked drivers deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: vehicles.php');
    exit;
}

if (isEmployee()) {
    $vehicles = $db->query("
        SELECT v.*, c.FNAME, c.LNAME
        FROM JKP_VEHICLES v
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = v.CUSTOMER_ID AND c.CUSTOMER_TYPE = v.CUSTOMER_TYPE
        ORDER BY v.CUSTOMER_ID
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT v.*, c.FNAME, c.LNAME
        FROM JKP_VEHICLES v
        JOIN JKP_CUSTOMER c ON c.CUSTOMER_ID = v.CUSTOMER_ID AND c.CUSTOMER_TYPE = v.CUSTOMER_TYPE
        WHERE v.CUSTOMER_ID = ?
    ");
    $stmt->execute([getCurrentCustomerId()]);
    $vehicles = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-car-front"></i> Vehicles</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Vehicle
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>VIN</th>
                <th>Make</th>
                <th>Model</th>
                <th>Year</th>
                <th>Status</th>
                <th>Customer</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($vehicles)): ?>
            <tr><td colspan="7" class="text-center text-muted">No vehicles found.</td></tr>
        <?php else: ?>
        <?php foreach ($vehicles as $v): ?>
            <tr>
                <td><code><?= e($v['VIN']) ?></code></td>
                <td><?= e($v['MAKE']) ?></td>
                <td><?= e($v['MODEL']) ?></td>
                <td><?= e($v['V_YEAR']) ?></td>
                <td><span class="badge bg-secondary"><?= e($statusMap[$v['V_STATUS']] ?? $v['V_STATUS']) ?></span></td>
                <td><?= e($v['FNAME'] . ' ' . $v['LNAME']) ?> (<?= e($v['CUSTOMER_ID']) ?>)</td>
                <?php if (isEmployee()): ?>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick='openEditV(<?= json_encode($v) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline;" id="delv-<?= e(md5($v['VIN'])) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="vin" value="<?= e($v['VIN']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete('delv-<?= e(md5($v['VIN'])) ?>')"><i class="bi bi-trash"></i></button>
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
                <div class="modal-header"><h5 class="modal-title">Add Vehicle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">VIN (17 chars)</label><input type="text" class="form-control" name="vin" maxlength="17" required></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Make</label><input type="text" class="form-control" name="make" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Model</label><input type="text" class="form-control" name="model" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Year</label><input type="number" class="form-control" name="v_year" min="1900" max="2030" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" name="v_status" required><option value="O">Owned</option><option value="F">Financed</option><option value="L">Leased</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Customer ID</label><input type="number" class="form-control" name="customer_id" required></div>
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
                <input type="hidden" name="vin" id="ev_vin">
                <div class="modal-header"><h5 class="modal-title">Edit Vehicle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Make</label><input type="text" class="form-control" name="make" id="ev_make" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Model</label><input type="text" class="form-control" name="model" id="ev_model" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Year</label><input type="number" class="form-control" name="v_year" id="ev_year" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" name="v_status" id="ev_status" required><option value="O">Owned</option><option value="F">Financed</option><option value="L">Leased</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Customer ID</label><input type="number" class="form-control" name="customer_id" id="ev_cid" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditV(v) {
    document.getElementById('ev_vin').value = v.VIN;
    document.getElementById('ev_make').value = v.MAKE;
    document.getElementById('ev_model').value = v.MODEL;
    document.getElementById('ev_year').value = v.V_YEAR;
    document.getElementById('ev_status').value = v.V_STATUS;
    document.getElementById('ev_cid').value = v.CUSTOMER_ID;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
