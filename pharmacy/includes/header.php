<?php
// Prevent direct access
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    exit('❌ Direct access not allowed.');
}

// Ensure session & auth are loaded
if (!isset($_SESSION)) {
    session_start();
}

// Redirect to login if accessed without auth (e.g., direct URL)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role = $_SESSION['role'] ?? 'user';

// Determine base path for navigation
$current_dir = dirname($_SERVER['PHP_SELF']);
$is_root = $current_dir === '/pharmacy' || $current_dir === '/';
$base_path = $is_root ? '' : '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?= $base_path ?>dashboard.php">
            <i class="fas fa-capsules me-2"></i>
            <span>PharmaSys</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'active' : '' ?>" 
                       href="<?= $base_path ?>dashboard.php">
                       <i class="fas fa-home me-1"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['SCRIPT_NAME']), ['list.php','add.php','edit.php']) ? 'active' : '' ?>" 
                       href="#" id="medMenu" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-pills me-1"></i> Medicines
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $base_path ?>medicines/list.php"><i class="fas fa-list me-2"></i> View All</a></li>
                        <li><a class="dropdown-item" href="<?= $base_path ?>medicines/add.php"><i class="fas fa-plus me-2"></i> Add New</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['SCRIPT_NAME']), ['stock-in.php','stock-out.php']) ? 'active' : '' ?>" 
                       href="#" id="invMenu" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-warehouse me-1"></i> Inventory
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $base_path ?>inventory/stock-in.php"><i class="fas fa-truck-loading me-2"></i> Stock In</a></li>
                        <li><a class="dropdown-item" href="<?= $base_path ?>inventory/stock-out.php"><i class="fas fa-cash-register me-2"></i> Stock Out</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'expiry.php' ? 'active' : '' ?>" 
                       href="<?= $base_path ?>reports/expiry.php">
                        <i class="fas fa-exclamation-triangle me-1"></i> Expiry Alerts
                    </a>
                </li>
            </ul>

            <!-- User dropdown -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                       id="userMenu" role="button" data-bs-toggle="dropdown">
                        <div class="me-2">
                            <div class="fw-bold"><?= $username ?></div>
                            <small class="text-white-50"><?= ucfirst($role) ?></small>
                        </div>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>logout.php">
                                <i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash messages -->
<div class="container mt-3">
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>
</div>

<!-- Toast container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">PharmaSys</strong>
            <small>Just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            <!-- Dynamic content -->
        </div>
    </div>
</div>

<main class="container py-4">