<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_login();

// Stats
$total_medicines = $pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
$total_batches = $pdo->query("SELECT COUNT(*) FROM batches WHERE remaining_stock > 0")->fetchColumn();

// Low stock: ≤ 10 units remaining
$low_stock_count = $pdo->query("
    SELECT COUNT(*) FROM batches 
    WHERE remaining_stock > 0 AND remaining_stock <= 10
")->fetchColumn();

// Expiring in ≤30 days
$expiring_count = $pdo->query("
    SELECT COUNT(*) FROM batches 
    WHERE remaining_stock > 0 
      AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();

// Today's sales
$today_sales = $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM sales 
    WHERE DATE(sold_at) = CURDATE()
")->fetchColumn();

// Recent 5 sales
$recent_sales = $pdo->query("
    SELECT s.id, s.customer_name, s.total, s.sold_at,
           GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS medicines
    FROM sales s
    JOIN sale_items si ON s.id = si.sale_id
    JOIN medicines m ON si.medicine_id = m.id
    GROUP BY s.id
    ORDER BY s.sold_at DESC
    LIMIT 5
")->fetchAll();

// Low stock items
$low_stock_items = $pdo->query("
    SELECT m.name, b.batch_number, b.remaining_stock
    FROM batches b
    JOIN medicines m ON b.medicine_id = m.id
    WHERE b.remaining_stock > 0 AND b.remaining_stock <= 10
    ORDER BY b.remaining_stock ASC
    LIMIT 5
")->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<!-- Welcome Banner -->
<div class="card mb-4 border-0" style="background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);">
    <div class="card-body text-white">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</h2>
                <p class="mb-0">Here's what's happening with your pharmacy today.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-capsules fa-4x opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="label">Total Medicines</div>
                <div class="number"><?= number_format($total_medicines) ?></div>
                <small class="text-muted">Registered in system</small>
            </div>
            <i class="fas fa-pills text-primary"></i>
        </div>
        <div class="mt-3">
            <a href="medicines/list.php" class="btn btn-sm btn-outline-primary">View All →</a>
        </div>
    </div>

    <div class="stat-card warning">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="label">Low Stock</div>
                <div class="number"><?= number_format($low_stock_count) ?></div>
                <small class="text-muted">≤10 units remaining</small>
            </div>
            <i class="fas fa-exclamation-triangle text-warning"></i>
        </div>
        <div class="mt-3">
            <a href="medicines/list.php?low_stock=1" class="btn btn-sm btn-outline-warning">Check →</a>
        </div>
    </div>

    <div class="stat-card danger">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="label">Expiring Soon</div>
                <div class="number"><?= number_format($expiring_count) ?></div>
                <small class="text-muted">Within 30 days</small>
            </div>
            <i class="fas fa-calendar-times text-danger"></i>
        </div>
        <div class="mt-3">
            <a href="reports/expiry.php" class="btn btn-sm btn-outline-danger">View Report →</a>
        </div>
    </div>

    <div class="stat-card success">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="label">Today's Sales</div>
                <div class="number">$<?= number_format($today_sales, 2) ?></div>
                <small class="text-muted">Revenue today</small>
            </div>
            <i class="fas fa-rupee-sign text-success"></i>
        </div>
        <div class="mt-3">
            <a href="inventory/stock-out.php" class="btn btn-sm btn-outline-success">New Sale →</a>
        </div>
    </div>
</div>

<div class="row mt-4">

<?php
$today_sales = $pdo->query("
    SELECT COUNT(*) as count, SUM(total) as total 
    FROM sales WHERE DATE(sold_at) = CURDATE()
")->fetch();
?>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sun me-2 text-warning"></i>Today's Summary</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-6">
                <div class="fw-bold fs-3"><?= $today_sales['count'] ?></div>
                <div class="text-muted">Sales Today</div>
            </div>
            <div class="col-6">
                <div class="fw-bold fs-3 text-success">$<?= number_format($today_sales['total'] ?? 0, 2) ?></div>
                <div class="text-muted">Revenue Today</div>
            </div>
        </div>
    </div>
</div>
    <!-- Recent Sales -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Sales</h5>
                <a href="inventory/stock-out.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> New Sale
                </a>
            </div>
            <div class="card-body">
                <?php if ($recent_sales): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Medicines</th>
                                <th>Total</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td class="fw-bold">#<?= $sale['id'] ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($sale['medicines']) ?></small>
                                </td>
                                <td class="fw-bold text-success">$<?= number_format($sale['total'], 2) ?></td>
                                <td>
                                    <small class="text-muted"><?= date('g:i A', strtotime($sale['sold_at'])) ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>


<th>Receipt</th>
<td>
    <a href="includes/receipt.php?sale_id=<?= $sale['id'] ?>" 
       target="_blank" 
       class="btn btn-sm btn-outline-info">
        <i class="fas fa-print"></i>
    </a>
</td>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <h5>No sales yet</h5>
                    <p class="text-muted">Start making sales to see them here.</p>
                    <a href="inventory/stock-out.php" class="btn btn-primary">Make First Sale</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock & Quick Actions -->
    <div class="col-lg-5">
        <!-- Low Stock Warning -->
        <?php if ($low_stock_items): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($low_stock_items as $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($item['batch_number']) ?></small>
                        </div>
                        <span class="badge bg-danger"><?= $item['remaining_stock'] ?> left</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <a href="medicines/list.php?low_stock=1" class="btn btn-sm btn-warning w-100">View All Low Stock →</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="medicines/add.php" class="btn btn-outline-primary text-start">
                        <i class="fas fa-plus-circle me-2"></i> Add New Medicine
                    </a>
                    <a href="inventory/stock-in.php" class="btn btn-outline-success text-start">
                        <i class="fas fa-truck-loading me-2"></i> Stock In (Purchase)
                    </a>
                    <a href="inventory/stock-out.php" class="btn btn-outline-info text-start">
                        <i class="fas fa-cash-register me-2"></i> Sell Medicine
                    </a>
                    <a href="reports/expiry.php" class="btn btn-outline-warning text-start">
                        <i class="fas fa-calendar-times me-2"></i> Check Expiry
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>