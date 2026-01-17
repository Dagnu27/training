<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $generic = trim($_POST['generic_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);

    // Validation
    if (!$name) {
        $error = "Medicine name is required.";
    } elseif ($price === false || $price < 0) {
        $error = "Valid price is required (e.g., 25.50).";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO medicines (name, generic_name, dosage, price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $generic ?: null,
                $dosage ?: null,
                $price
            ]);

            $medicine_id = $pdo->lastInsertId();
            $success = true;

            $_SESSION['success'] = "✅ Medicine <strong>" . htmlspecialchars($name) . "</strong> added successfully!";
            header("Location: list.php");
            exit();

        } catch (Exception $e) {
            error_log("Add medicine failed: " . $e->getMessage());
            $error = "❌ Failed to save. Please try again.";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-plus-circle me-2 text-success"></i>Add New Medicine</h2>
        <p class="text-muted mb-0">Register a new medicine in the system</p>
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
                <h5 class="mb-0">Medicine Details</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" id="add-medicine-form">
                    <div class="mb-4">
                        <label for="name" class="form-label fw-bold">
                            <i class="fas fa-capsules me-1 text-primary"></i>Medicine Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="name" name="name" class="form-control form-control-lg" 
                               placeholder="e.g., Crocin, Amoxil, Dolo 650"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
                        <div class="form-text">Brand or trade name</div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="generic_name" class="form-label fw-bold">
                                <i class="fas fa-flask me-1 text-info"></i>Generic Name
                            </label>
                            <input type="text" id="generic_name" name="generic_name" class="form-control"
                                   placeholder="e.g., Paracetamol, Amoxicillin"
                                   value="<?= htmlspecialchars($_POST['generic_name'] ?? '') ?>">
                            <div class="form-text">Active ingredient</div>
                        </div>
                        <div class="col-md-6">
                            <label for="dosage" class="form-label fw-bold">
                                <i class="fas fa-prescription me-1 text-warning"></i>Dosage/Form
                            </label>
                            <input type="text" id="dosage" name="dosage" class="form-control"
                                   placeholder="e.g., 500mg tablet, 10ml syrup"
                                   value="<?= htmlspecialchars($_POST['dosage'] ?? '') ?>">
                            <div class="form-text">Strength and form</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="price" class="form-label fw-bold">
                            <i class="fas fa-tag me-1 text-success"></i>Selling Price (₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-success text-white">
                                <i class="fas fa-rupee-sign"></i>
                            </span>
                            <input type="number" id="price" name="price" class="form-control" 
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                        </div>
                        <div class="form-text">Price per unit (tablet, strip, bottle, etc.)</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="list.php" class="btn btn-lg btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-lg btn-success">
                            <i class="fas fa-save me-1"></i> Save Medicine
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
                <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Guidelines</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-star text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Required Fields</h6>
                                <p class="text-muted small mb-0">Medicine name and price are mandatory.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-check-circle text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Price Format</h6>
                                <p class="text-muted small mb-0">Use decimal values (e.g., 12.50, 45.00).</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-arrow-right text-info"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Next Steps</h6>
                                <p class="text-muted small mb-0">After saving, add stock batches from Inventory → Stock In.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 p-2 rounded me-3">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Important</h6>
                                <p class="text-muted small mb-0">Medicine won't appear in sales until stock is added.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <div class="d-flex">
                        <i class="fas fa-lightbulb fa-2x me-3 text-info"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Quick Tip</h6>
                            <p class="mb-0 small">For better tracking, always add generic name. This helps in searching and managing inventory.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>