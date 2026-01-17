<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

$error = '';
$success = false;
$medicines = [];

// Fetch medicines for dropdown
$stmt = $pdo->query("SELECT id, name, generic_name, dosage, price FROM medicines ORDER BY name");
$medicines = $stmt->fetchAll();

// Pre-select medicine (e.g., from edit link)
$pre_selected_id = $_GET['medicine_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = (int)($_POST['medicine_id'] ?? 0);
    $batch_number = trim($_POST['batch_number'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $purchase_price = filter_var($_POST['purchase_price'] ?? 0, FILTER_VALIDATE_FLOAT);

    // Validate
    if (!$medicine_id || !array_column($medicines, 'id', 'id')[$medicine_id]) {
        $error = "Please select a valid medicine.";
    } elseif (!$batch_number) {
        $error = "Batch number is required.";
    } elseif (!$expiry_date || !strtotime($expiry_date)) {
        $error = "Valid expiry date (YYYY-MM-DD) is required.";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } elseif ($purchase_price === false || $purchase_price < 0) {
        $error = "Valid purchase price is required.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert batch
            $stmt = $pdo->prepare("
                INSERT INTO batches (medicine_id, batch_number, expiry_date, quantity, purchase_price, remaining_stock)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $medicine_id,
                $batch_number,
                $expiry_date,
                $quantity,
                $purchase_price,
                $quantity // initial remaining = total
            ]);

            $pdo->commit();
            $success = true;

            $_SESSION['success'] = "✅ Batch <strong>" . htmlspecialchars($batch_number) . "</strong> added successfully!";
            
            // Redirect to clear POST
            $redirect = $medicine_id ? "stock-in.php?medicine_id=$medicine_id" : "stock-in.php";
            header("Location: $redirect");
            exit();

        } catch (Exception $e) {
            $pdo->rollback();
            error_log("Stock-in failed: " . $e->getMessage());
            $error = "❌ Failed to record stock. Please try again.";
        }
    }
}

// Recent batches
$recent_batches = $pdo->query("
    SELECT b.batch_number, b.expiry_date, m.name, b.remaining_stock, b.created_at
    FROM batches b
    JOIN medicines m ON b.medicine_id = m.id
    ORDER BY b.created_at DESC
    LIMIT 5
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-truck-loading me-2 text-success"></i>Stock In</h2>
        <p class="text-muted mb-0">Add new purchase batches to inventory</p>
    </div>
    <a href="../medicines/list.php" class="btn btn-outline-secondary">
        <i class="fas fa-pills me-1"></i> Manage Medicines
    </a>
</div>

<div class="row">
    <!-- Form Column -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Batch</h5>
                <p class="text-muted mb-0 small">Record purchase details for inventory tracking</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label for="medicine_id" class="form-label fw-bold">
                            <i class="fas fa-capsules me-1 text-primary"></i>Medicine <span class="text-danger">*</span>
                        </label>
                        <select id="medicine_id" name="medicine_id" class="form-select form-select-lg" required>
                            <option value="">— Select Medicine —</option>
                            <?php foreach ($medicines as $m): ?>
                            <option value="<?= $m['id'] ?>" 
                                    <?= ($pre_selected_id && $m['id'] == $pre_selected_id) || 
                                         (isset($_POST['medicine_id']) && $m['id'] == $_POST['medicine_id']) 
                                        ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['name']) ?> 
                                <?= $m['dosage'] ? "({$m['dosage']})" : '' ?> 
                                — $<?= number_format($m['price'], 2) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the medicine for this batch</div>
                    </div>

                    <div class="mb-4">
                        <label for="batch_number" class="form-label fw-bold">
                            <i class="fas fa-barcode me-1 text-info"></i>Batch Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="batch_number" name="batch_number" class="form-control form-control-lg"
                               value="<?= htmlspecialchars($_POST['batch_number'] ?? '') ?>" 
                               placeholder="e.g., B24A1001, MFG2024-001" required>
                        <div class="form-text">Unique identifier for this batch</div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="expiry_date" class="form-label fw-bold">
                                <i class="fas fa-calendar-alt me-1 text-warning"></i>Expiry Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                                   value="<?= htmlspecialchars($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+2 years'))) ?>"
                                   required>
                            <div class="form-text">Format: YYYY-MM-DD</div>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label fw-bold">
                                <i class="fas fa-boxes me-1 text-success"></i>Quantity <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="quantity" name="quantity" class="form-control"
                                   value="<?= (int)($_POST['quantity'] ?? 1) ?>" min="1" required>
                            <div class="form-text">Number of units (strips, bottles, etc.)</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="purchase_price" class="form-label fw-bold">
                            <i class="fas fa-money-bill-wave me-1 text-danger"></i>Purchase Price (₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-success text-white">
                                <i class="fas fa-rupee-sign"></i>
                            </span>
                            <input type="number" id="purchase_price" name="purchase_price" class="form-control"
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?= htmlspecialchars($_POST['purchase_price'] ?? '') ?>" required>
                        </div>
                        <div class="form-text">Cost per unit (for profit calculation)</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="reset" class="btn btn-lg btn-outline-secondary me-md-2">
                            <i class="fas fa-redo me-1"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-lg btn-success">
                            <i class="fas fa-check-circle me-1"></i> Add to Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Column -->
    <div class="col-lg-5">
        <!-- Info Card -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Information</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex">
                            <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-check text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Batch Tracking</h6>
                                <p class="text-muted small mb-0">Each batch is tracked separately with its own expiry date.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex">
                            <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-sort-amount-down text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">FIFO System</h6>
                                <p class="text-muted small mb-0">Sales pull from earliest-expiry batch first.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex">
                            <div class="bg-warning bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Expiry Alerts</h6>
                                <p class="text-muted small mb-0">Expired stock is automatically hidden from sales.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <div class="d-flex">
                        <i class="fas fa-lightbulb fa-2x me-3 text-info"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Need a new medicine?</h6>
                            <p class="mb-0 small">Go to <strong>Medicines → Add</strong> first to register it.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Batches -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Recent Batches</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_batches): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_batches as $batch): 
                        $days_left = (new DateTime($batch['expiry_date']))->diff(new DateTime())->days;
                    ?>
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($batch['name']) ?></div>
                                <small class="text-muted">
                                    Batch: <?= htmlspecialchars($batch['batch_number']) ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?= $days_left <= 30 ? ($days_left <= 7 ? 'danger' : 'warning') : 'success' ?>">
                                    <?= date('M y', strtotime($batch['expiry_date'])) ?>
                                </span>
                                <div class="mt-1">
                                    <small class="text-muted">Stock: <?= $batch['remaining_stock'] ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-muted py-3">
                    <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                    No batches added yet
                </p>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="../reports/expiry.php" class="btn btn-sm btn-outline-warning w-100">
                        <i class="fas fa-calendar-times me-1"></i> View Expiry Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>