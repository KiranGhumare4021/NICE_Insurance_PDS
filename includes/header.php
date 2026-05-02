<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'NICE Insurance') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-shield-check"></i> NICE Insurance
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="customers.php"><i class="bi bi-people"></i> Customers</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-file-earmark-text"></i> Policies
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="auto_policies.php">Auto Policies</a></li>
                        <li><a class="dropdown-item" href="home_policies.php">Home Policies</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-car-front"></i> Assets
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="vehicles.php">Vehicles</a></li>
                        <li><a class="dropdown-item" href="drivers.php">Drivers</a></li>
                        <li><a class="dropdown-item" href="homes.php">Homes</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-credit-card"></i> Billing
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="invoices.php">Invoices</a></li>
                        <li><a class="dropdown-item" href="payments.php">Payments</a></li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link text-light">
                        <i class="bi bi-person-circle"></i>
                        <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?>
                        <span class="badge bg-<?= isEmployee() ? 'warning' : 'info' ?>">
                            <?= e(ucfirst($_SESSION['role'])) ?>
                        </span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid mt-3">
    <?php
    $flashSuccess = getFlash('success');
    $flashError   = getFlash('error');
    if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif;
    if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
