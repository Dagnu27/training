<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "✅ Medicine deleted successfully.";
    } catch (Exception $e) {
        error_log("Delete medicine failed: " . $e->getMessage());
        $_SESSION['error'] = "❌ Unable to delete. Medicine may have existing stock.";
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$show_low_stock = isset($_GET['low_stock']);

// Build query
$sql = "
    SELECT 
        m.id, m.name, m.generic_name, m.dosage, m.price,
        COUNT(b.id) AS batch_count,
        COALESCE(SUM(b.remaining_stock), 0) AS total_stock,
        MIN(b.expiry_date) AS earliest_expiry
    FROM medicines m
    LEFT JOIN batches b ON m.id = b.medicine_id AND b.remaining_stock > 0
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.dosage LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($show_low_stock) {
    $sql .= " AND COALESCE(SUM(b.remaining_stock), 0) <= 10";
}

$sql .= " GROUP BY m.id ORDER BY m.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Count totals for header
$total_stock = array_sum(array_column($medicines, 'total_stock'));
$low_stock_count = count(array_filter($medicines, function($m) { 
    return $m['total_stock'] <= 10 && $m['total_stock'] > 0; 
}));
?>

<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-pills me-2 text-primary"></i>Medicine Inventory</h2>
        <p class="text-muted mb-0">Manage all medicines in your pharmacy</p>
    </div>
    <a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Medicine
    </a>
</div>

<!-- Stats Summary -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start-primary">
            <div class="card-body">
                <div class="text-muted small">Total Medicines</div>
                <div class="fw-bold fs-4"><?= count($medicines) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-success">
            <div class="card-body">
                <div class="text-muted small">Total Stock</div>
                <div class="fw-bold fs-4"><?= number_format($total_stock) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-warning">
            <div class="card-body">
                <div class="text-muted small">Low Stock Items</div>
                <div class="fw-bold fs-4"><?= $low_stock_count ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start-info">
            <div class="card-body">
                <div class="text-muted small">Active Batches</div>
                <div class="fw-bold fs-4"><?= array_sum(array_column($medicines, 'batch_count')) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label">Search Medicines</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="search-medicines" name="search" class="form-control"
                           placeholder="Search by name, generic, or dosage..." 
                           value="<?= htmlspecialchars($search) ?>" autofocus>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" id="low_stock" name="low_stock" 
                           <?= $show_low_stock ? 'checked' : '' ?>>
                    <label class="form-check-label" for="low_stock">
                        Show Low Stock Only (≤10)
                    </label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <?php if ($search || $show_low_stock): ?>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Medicine Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Medicine List</h5>
        <span class="badge bg-primary"><?= count($medicines) ?> items</span>
    </div>
    <div class="card-body">
        <?php if (empty($medicines)): ?>
            <div class="text-center py-5">
                <i class="fas fa-pills fa-4x text-muted mb-3"></i>
                <h5>No medicines found</h5>
                <?php if ($search || $show_low_stock): ?>
                    <p class="text-muted">Try adjusting your search or filters.</p>
                    <a href="list.php" class="btn btn-sm btn-outline-primary">Clear Filters</a>
                <?php else: ?>
                    <p class="text-muted mb-4">Add your first medicine to get started.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add First Medicine
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="medicine-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Generic Name</th>
                        <th>Dosage</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th>Expiry</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $m): 
                        $stock = (int)$m['total_stock'];
                        $is_low = $stock <= 10 && $stock > 0;
                        $is_out = $stock === 0;
                        $has_expiry = !empty($m['earliest_expiry']);
                        
                        if ($has_expiry) {
                            $days_left = (new DateTime($m['earliest_expiry']))->diff(new DateTime())->days;
                            $expiry_badge = $days_left <= 30 ? ($days_left <= 7 ? 'danger' : 'warning') : 'success';
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($m['name']) ?></div>
                            <?php if ($m['batch_count'] > 0): ?>
                            <small class="text-muted"><?= $m['batch_count'] ?> batch<?= $m['batch_count'] !== 1 ? 'es' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($m['generic_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($m['dosage'] ?: '—') ?></td>
                        <td class="text-end fw-bold">₹<?= number_format($m['price'], 2) ?></td>
                        <td class="text-center">
                            <?php if ($is_out): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($is_low): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-exclamation-triangle me-1"></i><?= $stock ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $stock ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($has_expiry): ?>
                                <span class="badge bg-<?= $expiry_badge ?>">
                                    <?= date('M Y', strtotime($m['earliest_expiry'])) ?>
                                    <?php if ($days_left <= 30): ?>
                                        <small class="ms-1">(<?= $days_left ?>d)</small>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <!-- This is where the "Add Stock" button should be -->
                                <a href="../inventory/stock-in.php?medicine_id=<?= $m['id'] ?>" 
                                   class="btn btn-outline-success" title="Add Stock">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <form method="POST" class="d-inline" 
                                      onsubmit="return confirm('Delete \n\n<?= addslashes($m['name']) ?>?\n\nThis will remove all associated batches.')">
                                    <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
