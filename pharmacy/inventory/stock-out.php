<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

$error = '';
$success = '';
$cart = [];

// Initialize cart in session if not exists
if (!isset($_SESSION['sale_cart'])) {
    $_SESSION['sale_cart'] = [];
}

// After sale success message
if ($success && isset($sale_id)) {
    echo '<a href="includes/receipt.php?sale_id=' . $sale_id . '" target="_blank" class="btn btn-sm btn-info">
            <i class="fas fa-print"></i> Print Receipt
          </a>';
}
// Handle "Add to Cart" via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $batch_id = (int)($_POST['batch_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);

    if (!$batch_id || $quantity <= 0) {
        $error = "Invalid batch or quantity.";
    } else {
        // Validate batch exists & has enough stock
        $stmt = $pdo->prepare("
            SELECT b.id, b.medicine_id, m.name, b.batch_number, b.expiry_date, b.remaining_stock, m.price
            FROM batches b
            JOIN medicines m ON b.medicine_id = m.id
            WHERE b.id = ? AND b.remaining_stock >= ? AND b.expiry_date >= CURDATE()
        ");
        $stmt->execute([$batch_id, $quantity]);
        $batch = $stmt->fetch();

        if (!$batch) {
            $error = "❌ Batch not found, insufficient stock, or expired.";
        } else {
            // Check if batch already in cart
            $found = false;
            foreach ($_SESSION['sale_cart'] as $key => $item) {
                if ($item['batch_id'] == $batch_id) {
                    $_SESSION['sale_cart'][$key]['quantity'] += $quantity;
                    $_SESSION['sale_cart'][$key]['total'] = $_SESSION['sale_cart'][$key]['quantity'] * $item['unit_price'];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['sale_cart'][] = [
                    'batch_id' => $batch_id,
                    'medicine_name' => $batch['name'],
                    'batch_number' => $batch['batch_number'],
                    'expiry_date' => $batch['expiry_date'],
                    'quantity' => $quantity,
                    'unit_price' => $batch['price'],
                    'total' => $batch['price'] * $quantity
                ];
            }
            
            $success = "✅ Added to cart: {$batch['name']} × {$quantity}";
        }
    }
}

// Handle "Finalize Sale"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_sale') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $cart = $_SESSION['sale_cart'] ?? [];

    if (empty($cart)) {
        $error = "Cart is empty. Add items first.";
    } else {
        try {
            $pdo->beginTransaction();

            // Insert sale header
            $stmt = $pdo->prepare("INSERT INTO sales (customer_name, total) VALUES (?, ?)");
            $total = array_sum(array_column($cart, 'total'));
            $stmt->execute([$customer_name ?: null, $total]);
            $sale_id = $pdo->lastInsertId();

            // Insert sale items & update batch stock
            $itemStmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, batch_id, medicine_id, quantity, unit_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $updateBatch = $pdo->prepare("
                UPDATE batches SET remaining_stock = remaining_stock - ? WHERE id = ?
            ");

            foreach ($cart as $item) {
                // Get medicine_id from batch securely
                $stmt = $pdo->prepare("SELECT medicine_id FROM batches WHERE id = ?");
                $stmt->execute([$item['batch_id']]);
                $mid = $stmt->fetchColumn();
                
                if (!$mid) throw new Exception("Batch {$item['batch_id']} invalid");

                $itemStmt->execute([
                    $sale_id,
                    $item['batch_id'],
                    $mid,
                    $item['quantity'],
                    $item['unit_price']
                ]);
                $updateBatch->execute([$item['quantity'], $item['batch_id']]);
            }

            $pdo->commit();
            $success = "🎉 Sale #{$sale_id} completed successfully!";
            
            // Clear cart
            unset($_SESSION['sale_cart']);
            
        } catch (Exception $e) {
            $pdo->rollback();
            error_log("Sale failed: " . $e->getMessage());
            $error = "❌ Transaction failed. Please try again.";
        }
    }
}

// Get current cart
$cart = $_SESSION['sale_cart'] ?? [];

// Search medicine
$search = trim($_GET['q'] ?? '');
$batches = [];
if ($search) {
    $stmt = $pdo->prepare("
        SELECT 
            b.id, b.batch_number, b.expiry_date, b.remaining_stock,
            m.name, m.dosage, m.price
        FROM batches b
        JOIN medicines m ON b.medicine_id = m.id
        WHERE b.remaining_stock > 0 
          AND b.expiry_date >= CURDATE()
          AND (m.name LIKE ? OR m.generic_name LIKE ? OR b.batch_number LIKE ?)
        ORDER BY b.expiry_date ASC, b.remaining_stock DESC
        LIMIT 15
    ");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
    $batches = $stmt->fetchAll();
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $index = (int)$_GET['remove'];
    if (isset($_SESSION['sale_cart'][$index])) {
        unset($_SESSION['sale_cart'][$index]);
        $_SESSION['sale_cart'] = array_values($_SESSION['sale_cart']); // reindex
    }
    header('Location: stock-out.php');
    exit();
}
?>

<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="fas fa-cash-register me-2 text-success"></i>Point of Sale</h2>
        <p class="text-muted mb-0">Sell medicines to customers</p>
    </div>
    <a href="../dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Left: Search & Inventory -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-search me-2 text-primary"></i>Search Medicine</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="input-group input-group-lg">
                        <input type="text" name="q" class="form-control" 
                               placeholder="Search by medicine name, generic name, or batch number..." 
                               value="<?= htmlspecialchars($search) ?>" autofocus>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>

                <?php if ($search && empty($batches)): ?>
                <div class="alert alert-warning text-center py-4">
                    <i class="fas fa-search fa-2x mb-3 text-warning"></i>
                    <h5>No results found</h5>
                    <p class="text-muted mb-0">No in-stock batches found for "<?= htmlspecialchars($search) ?>".</p>
                </div>
                <?php endif; ?>

                <?php if ($batches): ?>
                <div class="list-group">
                    <?php foreach ($batches as $b):
                        $days = (new DateTime($b['expiry_date']))->diff(new DateTime())->days;
                        $expiry_class = $days <= 7 ? 'danger' : ($days <= 30 ? 'warning' : 'success');
                    ?>
                    <div class="list-group-item mb-2 rounded">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($b['name']) ?></h6>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($b['dosage'] ?: '—') ?> | 
                                            Batch: <span class="fw-bold"><?= htmlspecialchars($b['batch_number']) ?></span>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= $expiry_class ?>">
                                        Exp: <?= date('M y', strtotime($b['expiry_date'])) ?>
                                        <?php if ($days <= 30): ?>
                                            <small class="ms-1">(<?= $days ?>d)</small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-secondary">Stock: <?= $b['remaining_stock'] ?></span>
                                        <span class="badge bg-success ms-2">₹<?= number_format($b['price'], 2) ?></span>
                                    </div>
                                    <form method="POST" class="d-inline" 
                                          onsubmit="return confirmAdd(<?= $b['id'] ?>, '<?= addslashes($b['name']) ?>', <?= $b['remaining_stock'] ?>)">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                                        <div class="input-group input-group-sm" style="width: 140px;">
                                            <input type="number" name="quantity" class="form-control text-center" 
                                                   value="1" min="1" max="<?= $b['remaining_stock'] ?>" required>
                                            <button class="btn btn-sm btn-success" type="submit">
                                                <i class="fas fa-cart-plus"></i> Add
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Cart & Checkout -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h5>
                    <span class="badge bg-light text-dark fs-6"><?= count($cart) ?> items</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($cart)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                    <h5>Your cart is empty</h5>
                    <p class="text-muted">Search and add medicines to start a sale.</p>
                </div>
                <?php else: ?>
                <!-- Cart Items -->
                <div class="table-responsive mb-4">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $cart_total = 0; ?>
                            <?php foreach ($cart as $i => $item): 
                                $cart_total += $item['total'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($item['medicine_name']) ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($item['batch_number']) ?> | 
                                        <?= date('M y', strtotime($item['expiry_date'])) ?>
                                    </small>
                                </td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end fw-bold">₹<?= number_format($item['total'], 2) ?></td>
                                <td class="text-center">
                                    <a href="?remove=<?= $i ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Remove this item?')" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="fw-bold">Total Amount</td>
                                <td colspan="2" class="text-end fw-bold fs-5 text-success">
                                    ₹<?= number_format($cart_total, 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Checkout Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="finalize_sale">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user me-1 text-primary"></i>Customer Information (Optional)
                        </label>
                        <input type="text" name="customer_name" class="form-control" 
                               placeholder="Enter customer name (or leave for walk-in)">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg py-3">
                            <i class="fas fa-receipt me-2"></i> 
                            Complete Sale (₹<?= number_format($cart_total, 2) ?>)
                        </button>
                        
                        <a href="?clear_cart=true" class="btn btn-outline-danger" 
                           onclick="return confirm('Clear entire cart?')">
                            <i class="fas fa-trash me-1"></i> Clear Cart
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
             
<script>
// Confirm before adding to cart
function confirmAdd(batchId, name, maxQty) {
    const form = document.querySelector(`form[onsubmit*="${batchId}"]`);
    const qtyInput = form.querySelector('input[name="quantity"]');
    const qty = parseInt(qtyInput.value);
    
    if (isNaN(qty) || qty < 1) {
        alert(`Please enter a valid quantity for ${name}`);
        return false;
    }
    
    if (qty > maxQty) {
        alert(`Only ${maxQty} units available for ${name}`);
        return false;
    }
    
    return confirm(`Add ${qty} × ${name} to cart?`);
}

// Auto-focus search on load
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
    
    // Handle clear cart
    if (window.location.search.includes('clear_cart=true')) {
        if (confirm('Are you sure you want to clear the entire cart?')) {
            window.location.href = 'stock-out.php?clear=1';
        }
    }
});
</script>

<?php
// Handle clear cart
if (isset($_GET['clear'])) {
    unset($_SESSION['sale_cart']);
    header('Location: stock-out.php');
    exit();
}
?>

<?php include '../includes/footer.php'; ?>