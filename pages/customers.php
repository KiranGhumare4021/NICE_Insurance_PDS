<?php
require_once '../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Customers - NICE Insurance';

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' && isEmployee()) {
            $stmt = $db->prepare("INSERT INTO JKP_CUSTOMER (CUSTOMER_ID, CUSTOMER_TYPE, FNAME, LNAME, MNAME, STREET_NUMBER, CITY, STATE, ZIPCODE, GENDER, MARITAL_STATUS)
                VALUES (:cid, :ctype, :fn, :ln, :mn, :street, :city, :state, :zip, :gender, :ms)");
            $stmt->execute([
                ':cid'    => $_POST['customer_id'],
                ':ctype'  => $_POST['customer_type'],
                ':fn'     => $_POST['fname'],
                ':ln'     => $_POST['lname'],
                ':mn'     => $_POST['mname'] ?: null,
                ':street' => $_POST['street_number'],
                ':city'   => $_POST['city'],
                ':state'  => $_POST['state'],
                ':zip'    => $_POST['zipcode'],
                ':gender' => $_POST['gender'] ?: null,
                ':ms'     => $_POST['marital_status'],
            ]);
            setFlash('success', 'Customer created successfully.');
        }

        if ($action === 'update') {
            // Customers can edit their own info; employees can edit anyone
            if (!isEmployee() && $_POST['customer_id'] != getCurrentCustomerId()) {
                setFlash('error', 'You can only edit your own information.');
            } else {
                $stmt = $db->prepare("UPDATE JKP_CUSTOMER SET FNAME=:fn, LNAME=:ln, MNAME=:mn, STREET_NUMBER=:street, CITY=:city, STATE=:state, ZIPCODE=:zip, GENDER=:gender, MARITAL_STATUS=:ms
                    WHERE CUSTOMER_ID=:cid AND CUSTOMER_TYPE=:ctype");
                $stmt->execute([
                    ':fn'     => $_POST['fname'],
                    ':ln'     => $_POST['lname'],
                    ':mn'     => $_POST['mname'] ?: null,
                    ':street' => $_POST['street_number'],
                    ':city'   => $_POST['city'],
                    ':state'  => $_POST['state'],
                    ':zip'    => $_POST['zipcode'],
                    ':gender' => $_POST['gender'] ?: null,
                    ':ms'     => $_POST['marital_status'],
                    ':cid'    => $_POST['customer_id'],
                    ':ctype'  => $_POST['customer_type'],
                ]);
                setFlash('success', 'Customer updated successfully.');
            }
        }

        if ($action === 'delete' && isEmployee()) {
            $stmt = $db->prepare("DELETE FROM JKP_CUSTOMER WHERE CUSTOMER_ID=:cid AND CUSTOMER_TYPE=:ctype");
            $stmt->execute([':cid' => $_POST['customer_id'], ':ctype' => $_POST['customer_type']]);
            setFlash('success', 'Customer deleted successfully.');
        }
    } catch (PDOException $ex) {
        setFlash('error', 'Database error: ' . $ex->getMessage());
    }

    header('Location: customers.php');
    exit;
}

// Fetch customers
if (isEmployee()) {
    $customers = $db->query("SELECT * FROM JKP_CUSTOMER ORDER BY CUSTOMER_ID")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT * FROM JKP_CUSTOMER WHERE CUSTOMER_ID = :cid");
    $stmt->execute([':cid' => getCurrentCustomerId()]);
    $customers = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-people"></i> Customers</h2>
    <?php if (isEmployee()): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Customer
        </button>
    <?php endif; ?>
</div>

<div class="table-container">
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Name</th>
                <th>Address</th>
                <th>City</th>
                <th>State</th>
                <th>Zip</th>
                <th>Gender</th>
                <th>Marital</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><?= e($c['CUSTOMER_ID']) ?></td>
                <td><span class="badge bg-<?= $c['CUSTOMER_TYPE'] === 'A' ? 'success' : 'info' ?>"><?= $c['CUSTOMER_TYPE'] === 'A' ? 'Auto' : 'Home' ?></span></td>
                <td><?= e($c['FNAME'] . ' ' . ($c['MNAME'] ? $c['MNAME'] . ' ' : '') . $c['LNAME']) ?></td>
                <td><?= e($c['STREET_NUMBER']) ?></td>
                <td><?= e($c['CITY']) ?></td>
                <td><?= e($c['STATE']) ?></td>
                <td><?= e($c['ZIPCODE']) ?></td>
                <td><?= e($c['GENDER'] ?: '-') ?></td>
                <td><?= e($c['MARITAL_STATUS']) ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick='openEdit(<?= json_encode($c) ?>)'>
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php if (isEmployee()): ?>
                    <form method="POST" style="display:inline;" id="del-<?= e($c['CUSTOMER_ID'] . '-' . $c['CUSTOMER_TYPE']) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" value="<?= e($c['CUSTOMER_ID']) ?>">
                        <input type="hidden" name="customer_type" value="<?= e($c['CUSTOMER_TYPE']) ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-action"
                                onclick="confirmDelete('del-<?= e($c['CUSTOMER_ID'] . '-' . $c['CUSTOMER_TYPE']) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Customer Modal -->
<?php if (isEmployee()): ?>
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer ID</label>
                            <input type="number" class="form-control" name="customer_id" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Insurance Type</label>
                            <select class="form-select" name="customer_type" required>
                                <option value="A">Auto</option>
                                <option value="H">Home</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="fname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="mname">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Street Address</label>
                        <input type="text" class="form-control" name="street_number" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" maxlength="30" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Zipcode</label>
                            <input type="text" class="form-control" name="zipcode" maxlength="5" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">--</option>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" name="marital_status" required>
                                <option value="S">Single</option>
                                <option value="M">Married</option>
                                <option value="W">Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <input type="hidden" name="customer_type" id="edit_customer_type">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="fname" id="edit_fname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="mname" id="edit_mname">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lname" id="edit_lname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Street Address</label>
                        <input type="text" class="form-control" name="street_number" id="edit_street_number" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" id="edit_city" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" id="edit_state" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Zipcode</label>
                            <input type="text" class="form-control" name="zipcode" id="edit_zipcode" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" id="edit_gender">
                                <option value="">--</option>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" name="marital_status" id="edit_marital_status" required>
                                <option value="S">Single</option>
                                <option value="M">Married</option>
                                <option value="W">Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(c) {
    document.getElementById('edit_customer_id').value = c.CUSTOMER_ID;
    document.getElementById('edit_customer_type').value = c.CUSTOMER_TYPE;
    document.getElementById('edit_fname').value = c.FNAME;
    document.getElementById('edit_mname').value = c.MNAME || '';
    document.getElementById('edit_lname').value = c.LNAME;
    document.getElementById('edit_street_number').value = c.STREET_NUMBER;
    document.getElementById('edit_city').value = c.CITY;
    document.getElementById('edit_state').value = c.STATE;
    document.getElementById('edit_zipcode').value = c.ZIPCODE;
    document.getElementById('edit_gender').value = c.GENDER || '';
    document.getElementById('edit_marital_status').value = c.MARITAL_STATUS;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
