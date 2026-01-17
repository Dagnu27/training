<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

// Get medicines expiring in next 30 days
$stmt = $pdo->prepare("
    SELECT 
        m.id, m.name, m.dosage, m.price,
        b.id AS batch_id,
        b.batch_number,
        b.expiry_date,
        b.remaining_stock,
        b.purchase_price,
        DATEDIFF(b.expiry_date, CURDATE()) AS days_left
    FROM batches b
    JOIN medicines m ON b.medicine_id = m.id
    WHERE b.remaining_stock > 0
      AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY b.expiry_date ASC, b.remaining_stock DESC
");
$stmt->execute();
$expiring = $stmt->fetchAll();

// Calculate summary stats
$total_batches = count($expiring);
$critical_batches = array_filter($expiring, fn($item) => $item['days_left'] <= 7);
$warning_batches = array_filter($expiring, fn($item) => $item['days_left'] > 7 && $item['days_left'] <= 30);
$total_value = array_sum(array_map(fn($item) => $item['remaining_stock'] * $item['purchase_price'], $expiring));
?>

<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Expiry Report</h2>
        <p class="text-muted mb-0">Medicines expiring within next 30 days</p>
    </div>
    <div class="d-flex gap-2">
        <a href="../inventory/stock-in.php" class="btn btn-outline-primary">
            <i class="fas fa-truck-loading me-1"></i> Stock In
        </a>
        <a href="../dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Critical (≤7 days)</div>
                        <div class="fw-bold fs-4 text-danger"><?= count($critical_batches) ?></div>
                    </div>
                    <i class="fas fa-fire fa-2x text-danger opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Warning (8-30 days)</div>
                        <div class="fw-bold fs-4 text-warning"><?= count($warning_batches) ?></div>
                    </div>
                    <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Batches</div>
                        <div class="fw-bold fs-4"><?= $total_batches ?></div>
                    </div>
                    <i class="fas fa-boxes fa-2x text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Value</div>
                        <div class="fw-bold fs-4">₹<?= number_format($total_value, 2) ?></div>
                    </div>
                    <i class="fas fa-rupee-sign fa-2x text-success opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Info -->
<div class="alert alert-info mb-4">
    <div class="d-flex align-items-center">
        <i class="fas fa-calendar-alt fa-2x me-3 text-info"></i>
        <div>
            <h6 class="alert-heading mb-1">Date Range</h6>
            <p class="mb-0">
                Showing batches expiring between 
                <strong><?= date('M j, Y') ?></strong> and 
                <strong><?= date('M j, Y', strtotime('+30 days')) ?></strong>.
            </p>
        </div>
    </div>
</div>

<?php if (empty($expiring)): ?>
<!-- Empty State -->
<div class="card text-center">
    <div class="card-body py-5">
        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
        <h4>No Medicines Expiring Soon</h4>
        <p class="text-muted mb-4">All stock is fresh for at least the next 30 days.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="../inventory/stock-in.php" class="btn btn-primary">
                <i class="fas fa-truck-loading me-1"></i> Add New Stock
            </a>
            <a href="../medicines/list.php" class="btn btn-outline-primary">
                <i class="fas fa-pills me-1"></i> View All Medicines
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Expiry Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Expiring Batches</h5>
            <div class="d-flex gap-2">
                <span class="badge bg-danger">
                    <i class="fas fa-fire me-1"></i> Critical (≤7 days)
                </span>
                <span class="badge bg-warning text-dark">
                    <i class="fas fa-clock me-1"></i> Warning (8-30 days)
                </span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th class="text-center">Stock</th>
                        <th>Purchase Value</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiring as $item): 
                        $days = (int)$item['days_left'];
                        $isCritical = $days <= 7;
                        $batch_value = $item['remaining_stock'] * $item['purchase_price'];
                    ?>
                    <tr class="<?= $isCritical ? 'table-danger' : 'table-warning' ?>">
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($item['dosage'] ?: '—') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($item['batch_number']) ?></span>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($item['expiry_date'])) ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator status-<?= $isCritical ? 'danger' : 'warning' ?> me-2"></span>
                                <span class="fw-bold"><?= $days ?> day<?= $days !== 1 ? 's' : '' ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $item['remaining_stock'] <= 5 ? 'danger' : 'secondary' ?>">
                                <?= $item['remaining_stock'] ?> unit<?= $item['remaining_stock'] !== 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td class="fw-bold">
                            ₹<?= number_format($batch_value, 2) ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="../medicines/edit.php?id=<?= $item['id'] ?>" 
                                   class="btn btn-outline-primary" title="Edit Medicine">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="../inventory/stock-out.php?q=<?= urlencode($item['name']) ?>" 
                                   class="btn btn-outline-success" title="Sell Now">
                                    <i class="fas fa-cash-register"></i>
                                </a>
                                <?php if ($isCritical): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="alert('⚠️ This batch expires in <?= $days ?> days! Consider discounting or priority sale.')"
                                        title="Critical Alert">
                                    <i class="fas fa-exclamation"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="fw-bold text-end">Total Value at Risk:</td>
                        <td colspan="2" class="fw-bold text-danger fs-5">
                            $<?= number_format($total_value, 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Recommendations -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-start-danger">
                    <div class="card-body">
                        <h6 class="fw-bold text-danger mb-3">
                            <i class="fas fa-fire me-2"></i>Critical Items Action Plan
                        </h6>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> Prioritize selling immediately</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> Consider discounting (10-20%)</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> Notify staff for priority attention</li>
                            <li><i class="fas fa-check-circle text-danger me-2"></i> Mark for return to supplier if possible</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-start-warning">
                    <div class="card-body">
                        <h6 class="fw-bold text-warning mb-3">
                            <i class="fas fa-clock me-2"></i>Warning Items Strategy
                        </h6>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Include in promotional sales</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Bundle with other products</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Review supplier lead times</li>
                            <li><i class="fas fa-check-circle text-warning me-2"></i> Schedule for next month's review</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export/Print Options -->
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                Report generated on <?= date('F j, Y \a\t g:i A') ?>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-print me-1"></i> Print Report
                </button>
                <a href="?export=csv" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-file-csv me-1"></i> Export CSV
                </a>
            </div>
        </div>
    </div>
</div>

<!-- CSV Export Logic -->
<?php
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expiry_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Medicine', 'Batch', 'Expiry Date', 'Days Left', 'Stock', 'Purchase Value', 'Status']);
    
    foreach ($expiring as $item) {
        $status = $item['days_left'] <= 7 ? 'Critical' : 'Warning';
        $value = $item['remaining_stock'] * $item['purchase_price'];
        
        fputcsv($output, [
            $item['name'],
            $item['batch_number'],
            $item['expiry_date'],
            $item['days_left'],
            $item['remaining_stock'],
            number_format($value, 2),
            $status
        ]);
    }
    
    fclose($output);
    exit();
}
?>
<?php endif; ?>

<script>
// Highlight critical items
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Auto-refresh every 5 minutes
    setTimeout(() => {
        window.location.reload();
    }, 300000); // 5 minutes
});
</script>

<?php include '../includes/footer.php'; ?>