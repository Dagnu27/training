<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_login();

// Get all sales
$sales = $pdo->query("
    SELECT s.*, COUNT(si.id) as item_count
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    GROUP BY s.id
    ORDER BY s.sold_at DESC
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<h2>Sales History</h2>

<table class="table">
    <tr>
        <th>ID</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Items</th>
        <th>Total</th>
        <th>Receipt</th>
    </tr>
    <?php foreach($sales as $s): ?>
    <tr>
        <td>#<?= $s['id'] ?></td>
        <td><?= date('d/m/Y h:i A', strtotime($s['sold_at'])) ?></td>
        <td><?= htmlspecialchars($s['customer_name'] ?: 'Walk-in') ?></td>
        <td><?= $s['item_count'] ?></td>
        <td>₹<?= number_format($s['total'], 2) ?></td>
        <td>
            <a href="../includes/receipt.php?sale_id=<?= $s['id'] ?>" 
               target="_blank" 
               class="btn btn-sm btn-outline-primary">
                <i class="fas fa-print"></i> Print
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php include '../includes/footer.php'; ?>