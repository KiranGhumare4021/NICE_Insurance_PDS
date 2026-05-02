<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Drivers - NICE Insurance';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO JKP_DRIVERS (DRIVER_ID, FNAME, MNAME, LNAME, LICENSE_NO, LICENSE_STATE, DRIVER_AGE, VEHICLES_VIN)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['driver_id'], $_POST['fname'], $_POST['mname'] ?: null, $_POST['lname'],
                $_POST['license_no'], $_POST['license_state'], $_POST['driver_age'], $_POST['vehicles_vin']
            ]);
            $db->commit();
            setFlash('success', 'Driver added.');
        }

        if ($action === 'update' && isEmployee()) {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE JKP_DRIVERS SET FNAME=?, MNAME=?, LNAME=?, LICENSE_NO=?, LICENSE_STATE=?, DRIVER_AGE=?, VEHICLES_VIN=? WHERE DRIVER_ID=?");
            $stmt->execute([
                $_POST['fname'], $_POST['mname'] ?: null, $_POST['lname'],
                $_POST['license_no'], $_POST['license_state'], $_POST['driver_age'], $_POST['vehicles_vin'], $_POST['driver_id']
            ]);
            $db->commit();
            setFlash('success', 'Driver updated.');
        }

        if ($action === 'delete' && isEmployee()) {
            $db->beginTransaction();
            $db->prepare("DELETE FROM JKP_DRIVERS WHERE DRIVER_ID=?")->execute([$_POST['driver_id']]);
            $db->commit();
            setFlash('success', 'Driver deleted.');
        }
    } catch (PDOException $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('error', 'Error: ' . $ex->getMessage());
    }
    header('Location: drivers.php');
    exit;
}

if (isEmployee()) {
    $drivers = $db->query("
        SELECT d.*, v.MAKE, v.MODEL, v.V_YEAR
        FROM JKP_DRIVERS d
        JOIN JKP_VEHICLES v ON v.VIN = d.VEHICLES_VIN
        ORDER BY d.DRIVER_ID
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT d.*, v.MAKE, v.MODEL, v.V_YEAR
        FROM JKP_DRIVERS d
        JOIN JKP_VEHICLES v ON v.VIN = d.VEHICLES_VIN
        WHERE v.CUSTOMER_ID = ?
    ");
    $stmt->execute([getCurrentCustomerId()]);
    $drivers = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-person-vcard"></i> Drivers</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Driver
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>License #</th>
                <th>State</th>
                <th>Date of Birth</th>
                <th>Vehicle</th>
                <?php if (isEmployee()): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($drivers)): ?>
            <tr><td colspan="7" class="text-center text-muted">No drivers found.</td></tr>
        <?php else: ?>
        <?php foreach ($drivers as $d): ?>
            <tr>
                <td><?= e($d['DRIVER_ID']) ?></td>
                <td><?= e($d['FNAME'] . ' ' . ($d['MNAME'] ? $d['MNAME'] . ' ' : '') . $d['LNAME']) ?></td>
                <td><code><?= e($d['LICENSE_NO']) ?></code></td>
                <td><?= e($d['LICENSE_STATE']) ?></td>
                <td><?= date('M d, Y', strtotime($d['DRIVER_AGE'])) ?></td>
                <td><?= e($d['V_YEAR'] . ' ' . $d['MAKE'] . ' ' . $d['MODEL']) ?></td>
                <?php if (isEmployee()): ?>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick='openEditD(<?= json_encode($d) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline;" id="deld-<?= e($d['DRIVER_ID']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="driver_id" value="<?= e($d['DRIVER_ID']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete('deld-<?= e($d['DRIVER_ID']) ?>')"><i class="bi bi-trash"></i></button>
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
                <div class="modal-header"><h5 class="modal-title">Add Driver</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Driver ID</label><input type="number" class="form-control" name="driver_id" required></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">First Name</label><input type="text" class="form-control" name="fname" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Middle Name</label><input type="text" class="form-control" name="mname"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Last Name</label><input type="text" class="form-control" name="lname" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">License #</label><input type="text" class="form-control" name="license_no" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">License State</label><input type="text" class="form-control" name="license_state" maxlength="2" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="driver_age" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Vehicle VIN</label><input type="text" class="form-control" name="vehicles_vin" maxlength="17" required></div>
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
                <input type="hidden" name="driver_id" id="ed_did">
                <div class="modal-header"><h5 class="modal-title">Edit Driver</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">First Name</label><input type="text" class="form-control" name="fname" id="ed_fn" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Middle Name</label><input type="text" class="form-control" name="mname" id="ed_mn"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Last Name</label><input type="text" class="form-control" name="lname" id="ed_ln" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">License #</label><input type="text" class="form-control" name="license_no" id="ed_lic" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">License State</label><input type="text" class="form-control" name="license_state" id="ed_ls" maxlength="2" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="driver_age" id="ed_dob" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Vehicle VIN</label><input type="text" class="form-control" name="vehicles_vin" id="ed_vin" maxlength="17" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditD(d) {
    document.getElementById('ed_did').value = d.DRIVER_ID;
    document.getElementById('ed_fn').value = d.FNAME;
    document.getElementById('ed_mn').value = d.MNAME || '';
    document.getElementById('ed_ln').value = d.LNAME;
    document.getElementById('ed_lic').value = d.LICENSE_NO;
    document.getElementById('ed_ls').value = d.LICENSE_STATE;
    document.getElementById('ed_dob').value = d.DRIVER_AGE;
    document.getElementById('ed_vin').value = d.VEHICLES_VIN;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
