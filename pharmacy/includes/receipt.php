<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$sale_id = (int)($_GET['sale_id'] ?? 0);

// Get sale details
$stmt = $pdo->prepare("
    SELECT 
        s.id, s.customer_name, s.total, s.sold_at,
        GROUP_CONCAT(
            CONCAT(m.name, ' (', si.quantity, ' × ₹', si.unit_price, ') = ₹', si.quantity * si.unit_price) 
            SEPARATOR '\n'
        ) as items
    FROM sales s
    JOIN sale_items si ON s.id = si.sale_id
    JOIN medicines m ON si.medicine_id = m.id
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("<h3>❌ Receipt not found</h3><p>Sale ID #$sale_id doesn't exist.</p>");
}

// Get pharmacy info (you can customize this)
$pharmacy_name = "PharmaSys Pharmacy";
$pharmacy_address = "123 Medical Street, City";
$pharmacy_phone = "+91 98765 43210";
$pharmacy_gst = "GSTIN: 27ABCDE1234F1Z5";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $sale_id ?></title>
    <style>
        /* Receipt styling - printer friendly */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
        }
        
        body {
            padding: 15px;
            max-width: 300px;
            margin: 0 auto;
            background: white;
        }
        
        .receipt {
            border: 1px solid #ddd;
            padding: 15px;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .header h2 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .sale-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .items {
            margin: 15px 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px dotted #ddd;
            font-size: 13px;
        }
        
        .item-name {
            flex: 2;
        }
        
        .item-qty {
            flex: 1;
            text-align: center;
        }
        
        .item-price {
            flex: 1;
            text-align: right;
        }
        
        .total-section {
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
            text-align: right;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 11px;
            color: #666;
            border-top: 1px dashed #ddd;
            padding-top: 10px;
        }
        
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        
        .btn {
            padding: 8px 15px;
            margin: 0 5px;
            border: none;
            background: #007bff;
            color: white;
            cursor: pointer;
            border-radius: 4px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        /* Print styles */
        @media print {
            body {
                padding: 0;
            }
            
            .receipt {
                border: none;
                padding: 0;
            }
            
            .actions, .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Pharmacy Header -->
        <div class="header">
            <h2><?= htmlspecialchars($pharmacy_name) ?></h2>
            <p><?= htmlspecialchars($pharmacy_address) ?></p>
            <p>📞 <?= htmlspecialchars($pharmacy_phone) ?></p>
            <p><?= htmlspecialchars($pharmacy_gst) ?></p>
        </div>
        
        <!-- Sale Info -->
        <div class="sale-info">
            <div>
                <strong>Receipt #:</strong> <?= $sale_id ?><br>
                <strong>Date:</strong> <?= date('d/m/Y', strtotime($sale['sold_at'])) ?><br>
                <strong>Time:</strong> <?= date('h:i A', strtotime($sale['sold_at'])) ?>
            </div>
            <div>
                <?php if ($sale['customer_name']): ?>
                <strong>Customer:</strong><br>
                <?= htmlspecialchars($sale['customer_name']) ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Items -->
        <div class="items">
            <div style="display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                <div>Description</div>
                <div>Qty</div>
                <div>Amount</div>
            </div>
            
            <?php
            // Get detailed items
            $stmt = $pdo->prepare("
                SELECT m.name, si.quantity, si.unit_price, 
                       (si.quantity * si.unit_price) as total
                FROM sale_items si
                JOIN medicines m ON si.medicine_id = m.id
                WHERE si.sale_id = ?
            ");
            $stmt->execute([$sale_id]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item):
            ?>
            <div class="item-row">
                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="item-qty"><?= $item['quantity'] ?></div>
                <div class="item-price">$<?= number_format($item['total'], 2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Total Section -->
        <div class="total-section">
            <div class="total-row">
                <div>Subtotal:</div>
                <div>$<?= number_format($sale['total'], 2) ?></div>
            </div>
            <div class="total-row">
                <div>Tax (GST):</div>
                <div>$0.00</div>
            </div>
            <div class="total-row grand-total">
                <div>GRAND TOTAL:</div>
                <div>$<?= number_format($sale['total'], 2) ?></div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>** Thank you for your visit! **</p>
            <p>Please keep this receipt for returns/exchanges</p>
            <p>Returns accepted within 7 days with receipt</p>
            <p>For queries: <?= htmlspecialchars($pharmacy_phone) ?></p>
            <p style="margin-top: 10px;">--------------------------------</p>
            <p style="font-size: 10px;">System generated receipt - Valid without signature</p>
        </div>
    </div>
    
    <!-- Action Buttons (Hidden when printing) -->
    <div class="actions no-print">
        <button class="btn" onclick="window.print()">
            🖨️ Print Receipt
        </button>
        <button class="btn" onclick="window.close()" style="background: #6c757d;">
            ❌ Close Window
        </button>
    </div>
    
    <script>
        // Auto print and close (optional)
        window.onload = function() {
            // Uncomment next line for auto-print
            // window.print();
            
            // Auto-close after 30 seconds if printed
            setTimeout(function() {
                if (window.location.search.includes('auto=1')) {
                    window.close();
                }
            }, 30000);
        };
        
        // Handle print event
        window.addEventListener('afterprint', function() {
            // Close window after printing (optional)
            // window.close();
        });
    </script>
</body>
</html>