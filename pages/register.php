<?php
require_once '../includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';
        $fullName  = trim($_POST['full_name'] ?? '');
        $role      = $_POST['role'] ?? 'customer';
        $custId    = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        // Validation
        if (empty($username) || strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($fullName)) {
            $errors[] = 'Full name is required.';
        }
        if (!in_array($role, ['customer', 'employee'])) {
            $errors[] = 'Invalid role selected.';
        }

        if (empty($errors)) {
            $result = registerUser($username, $password, $fullName, $role, $custId);
            if ($result === true) {
                setFlash('success', 'Account created successfully! Please log in.');
                header('Location: login.php');
                exit;
            } else {
                $errors[] = $result; // error message string
            }
        }
    }
}

// Get existing customer IDs for the dropdown
$db = getDB();
$customers = $db->query("SELECT DISTINCT CUSTOMER_ID, FNAME, LNAME FROM JKP_CUSTOMER ORDER BY CUSTOMER_ID")->fetchAll();

$pageTitle = 'Register - NICE Insurance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-container" style="max-width:520px;">
    <div class="card auth-card">
        <div class="card-header">
            <i class="bi bi-shield-check" style="font-size:2.5rem;"></i>
            <h3>Create Account</h3>
            <small>NICE Insurance Portal</small>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Account Type</label>
                    <select class="form-select" id="role" name="role" onchange="toggleCustomerId()">
                        <option value="customer" <?= ($_POST['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer</option>
                        <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                    </select>
                </div>
                <div class="mb-3" id="customerIdGroup">
                    <label for="customer_id" class="form-label">Link to Existing Customer (optional)</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">-- New Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= e($c['CUSTOMER_ID']) ?>"
                                <?= ($_POST['customer_id'] ?? '') == $c['CUSTOMER_ID'] ? 'selected' : '' ?>>
                                <?= e($c['CUSTOMER_ID'] . ' - ' . $c['FNAME'] . ' ' . $c['LNAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-person-plus"></i> Create Account
                </button>
            </form>
            <hr>
            <p class="text-center mb-0">
                Already have an account? <a href="login.php">Sign in here</a>
            </p>
        </div>
    </div>
</div>
<script>
function toggleCustomerId() {
    const role = document.getElementById('role').value;
    document.getElementById('customerIdGroup').style.display = role === 'customer' ? 'block' : 'none';
}
toggleCustomerId();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
