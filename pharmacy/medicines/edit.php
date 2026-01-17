<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

$error = '';
$success = false;
$medicine = null;

// Validate & fetch medicine
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id) || $id <= 0) {
    $_SESSION['error'] = "Invalid medicine ID.";
    header('Location: list.php');
    exit();
}
<div class="mb-3">
    <label for="barcode" class="form-label">Barcode</label>
    <input type="text" id="barcode" name="barcode" class="form-control" 
           placeholder="Scan or enter barcode" value="<?= htmlspecialchars($_POST['barcode'] ?? $medicine['barcode'] ?? '') ?>">
    <div class="form-text">For quick scanning during sales</div>
</div>
// Fetch existing medicine
$stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
$stmt->execute([(int)$id]);
$medicine = $stmt->fetch();

if (!$medicine) {
    $_SESSION['error'] = "Medicine not found.";
    header('Location: list.php');
    exit();
}

// Get current stock info
$stock_info = $pdo->prepare("
    SELECT 
        COUNT(*) as batch_count,
        SUM(remaining_stock) as total_stock,
        MIN(expiry_date) as earliest_expiry
    FROM batches 
    WHERE medicine_id = ? AND remaining_stock > 0
");
$stock_info->execute([$id]);
$stock = $stock_info->fetch();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $generic = trim($_POST['generic_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);

    if (!$name) {
        $error = "Medicine name is required.";
    } elseif ($price === false || $price < 0) {
        $error = "Valid price is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE medicines 
                SET name = ?, generic_name = ?, dosage = ?, price = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                $generic ?: null,
                $dosage ?: null,
                $price,
                $id
            ]);

            $_SESSION['success'] = "✅ <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
            header("Location: list.php");
            exit();

        } catch (Exception $e) {
            error_log("Update medicine failed: " . $e->getMessage());
            $error = "❌ Failed to update. Please try again.";
        }
    }
}

// Pre-fill form data (POST > DB)
$name = htmlspecialchars($_POST['name'] ?? $medicine['name']);
$generic = htmlspecialchars($_POST['generic_name'] ?? $medicine['generic_name']);
$dosage = htmlspecialchars($_POST['dosage'] ?? $medicine['dosage']);
$price = htmlspecialchars($_POST['price'] ?? $medicine['price']);
?>

<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-edit me-2 text-primary"></i>Edit Medicine</h2>
        <p class="text-muted mb-0">Update medicine information</p>
    </div>
    <a href="list.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<div class="row">
    <!-- Form Column -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit: <?= htmlspecialchars($medicine['name']) ?></h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="mb-4">
                        <label for="name" class="form-label fw-bold">Medicine Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control form-control-lg" 
                               value="<?= $name ?>" required>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="generic_name" class="form-label fw-bold">Generic Name</label>
                            <input type="text" id="generic_name" name="generic_name" class="form-control"
                                   value="<?= $generic ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="dosage" class="form-label fw-bold">Dosage/Form</label>
                            <input type="text" id="dosage" name="dosage" class="form-control"
                                   value="<?= $dosage ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="price" class="form-label fw-bold">Selling Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-primary text-white">
                                <i class="fas fa-rupee-sign"></i>
                            </span>
                            <input type="number" id="price" name="price" class="form-control" 
                                   step="0.01" min="0" value="<?= $price ?>" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="list.php" class="btn btn-lg btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-lg btn-primary">
                            <i class="fas fa-save me-1"></i> Update Medicine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Column -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2 text-info"></i>Medicine Information</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush mb-4">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Medicine ID</span>
                        <span class="fw-bold">#<?= (int)$medicine['id'] ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Created Date</span>
                        <span><?= date('M j, Y', strtotime($medicine['created_at'])) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Current Price</span>
                        <span class="fw-bold text-success">$<?= number_format($medicine['price'], 2) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Active Batches</span>
                        <span class="badge bg-primary"><?= $stock['batch_count'] ?? 0 ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Total Stock</span>
                        <span class="badge bg-<?= ($stock['total_stock'] ?? 0) <= 10 ? 'warning' : 'success' ?>">
                            <?= $stock['total_stock'] ?? 0 ?> units
                        </span>
                    </div>
                    <?php if (!empty($stock['earliest_expiry'])): 
                        $days = (new DateTime($stock['earliest_expiry']))->diff(new DateTime())->days;
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <span class="text-muted">Earliest Expiry</span>
                        <span class="badge bg-<?= $days <= 30 ? ($days <= 7 ? 'danger' : 'warning') : 'success' ?>">
                            <?= date('M Y', strtotime($stock['earliest_expiry'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="alert alert-warning">
                    <div class="d-flex">
                        <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Note</h6>
                            <p class="mb-0 small">Changing the price does <strong>not</strong> affect existing batches. Only new batches will use the updated price.</p>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <a href="../inventory/stock-in.php?medicine_id=<?= $medicine['id'] ?>" 
                       class="btn btn-outline-success">
                        <i class="fas fa-truck-loading me-1"></i> Add Stock for This Medicine
                    </a>
                    <a href="../reports/expiry.php" class="btn btn-outline-warning">
                        <i class="fas fa-calendar-times me-1"></i> Check Expiry Alerts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>