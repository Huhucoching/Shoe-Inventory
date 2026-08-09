<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$productId = to_positive_int($_GET['product_id'] ?? null);
$categoryId = to_positive_int($_GET['category_id'] ?? null);

$pdo = getPDO();

$productLabel = 'All products';
$categoryLabel = 'All categories';

if ($productId !== null) {
    $productNameStmt = $pdo->prepare('SELECT product_code, shoe_name FROM products WHERE id = :id LIMIT 1');
    $productNameStmt->execute(['id' => $productId]);
    $product = $productNameStmt->fetch();
    if ($product) {
        $productLabel = (string) $product['product_code'] . ' - ' . (string) $product['shoe_name'];
    }
}

if ($categoryId !== null) {
    $categoryNameStmt = $pdo->prepare('SELECT name FROM categories WHERE id = :id LIMIT 1');
    $categoryNameStmt->execute(['id' => $categoryId]);
    $category = $categoryNameStmt->fetch();
    if ($category) {
        $categoryLabel = (string) $category['name'];
    }
}

$salesWhere = [];
$salesParams = [];

if ($dateFrom !== '') {
    $salesWhere[] = 'DATE(s.created_at) >= :date_from';
    $salesParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $salesWhere[] = 'DATE(s.created_at) <= :date_to';
    $salesParams['date_to'] = $dateTo;
}
if ($productId !== null) {
    $salesWhere[] = 'si.product_id = :product_id';
    $salesParams['product_id'] = $productId;
}
if ($categoryId !== null) {
    $salesWhere[] = 'p.category_id = :category_id';
    $salesParams['category_id'] = $categoryId;
}

$salesWhereSql = $salesWhere ? 'WHERE ' . implode(' AND ', $salesWhere) : '';

$dailySql = 'SELECT DATE(s.created_at) AS sale_date, COUNT(DISTINCT s.id) AS sale_count, SUM(si.total) AS total_sales
             FROM sales_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             ' . $salesWhereSql . '
             GROUP BY DATE(s.created_at)
             ORDER BY sale_date DESC';
$dailyStmt = $pdo->prepare($dailySql);
$dailyStmt->execute($salesParams);
$dailyRows = $dailyStmt->fetchAll();

$monthlySql = 'SELECT DATE_FORMAT(s.created_at, "%Y-%m") AS sale_month, COUNT(DISTINCT s.id) AS sale_count, SUM(si.total) AS total_sales
               FROM sales_items si
               JOIN sales s ON s.id = si.sale_id
               JOIN products p ON p.id = si.product_id
               ' . $salesWhereSql . '
               GROUP BY DATE_FORMAT(s.created_at, "%Y-%m")
               ORDER BY sale_month DESC';
$monthlyStmt = $pdo->prepare($monthlySql);
$monthlyStmt->execute($salesParams);
$monthlyRows = $monthlyStmt->fetchAll();

$productWhere = [];
$productParams = [];

if ($productId !== null) {
    $productWhere[] = 'p.id = :product_id';
    $productParams['product_id'] = $productId;
}
if ($categoryId !== null) {
    $productWhere[] = 'p.category_id = :category_id';
    $productParams['category_id'] = $categoryId;
}
if ($dateFrom !== '') {
    $productWhere[] = 'DATE(p.date_added) >= :date_from';
    $productParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $productWhere[] = 'DATE(p.date_added) <= :date_to';
    $productParams['date_to'] = $dateTo;
}

$productWhereSql = $productWhere ? 'WHERE ' . implode(' AND ', $productWhere) : '';

$inventoryValueSql = 'SELECT p.product_code, p.shoe_name, p.brand, c.name AS category_name,
                             p.stock_quantity, p.purchase_price, p.selling_price,
                             (p.stock_quantity * p.purchase_price) AS stock_cost,
                             (p.stock_quantity * p.selling_price) AS stock_value
                      FROM products p
                      JOIN categories c ON c.id = p.category_id
                      ' . $productWhereSql . '
                      ORDER BY p.shoe_name ASC';
$inventoryValueStmt = $pdo->prepare($inventoryValueSql);
$inventoryValueStmt->execute($productParams);
$inventoryValueRows = $inventoryValueStmt->fetchAll();

$lowStockSql = 'SELECT p.product_code, p.shoe_name, p.brand, c.name AS category_name, p.stock_quantity
                FROM products p
                JOIN categories c ON c.id = p.category_id
                ' . ($productWhere ? $productWhereSql . ' AND p.stock_quantity <= :threshold' : 'WHERE p.stock_quantity <= :threshold') . '
                ORDER BY p.stock_quantity ASC';
$lowStockParams = $productParams;
$lowStockParams['threshold'] = LOW_STOCK_THRESHOLD;
$lowStockStmt = $pdo->prepare($lowStockSql);
$lowStockStmt->execute($lowStockParams);
$lowStockRows = $lowStockStmt->fetchAll();

$movementWhere = [];
$movementParams = [];
if ($dateFrom !== '') {
    $movementWhere[] = 'DATE(sm.created_at) >= :date_from';
    $movementParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $movementWhere[] = 'DATE(sm.created_at) <= :date_to';
    $movementParams['date_to'] = $dateTo;
}
if ($productId !== null) {
    $movementWhere[] = 'sm.product_id = :product_id';
    $movementParams['product_id'] = $productId;
}
if ($categoryId !== null) {
    $movementWhere[] = 'p.category_id = :category_id';
    $movementParams['category_id'] = $categoryId;
}

$movementWhereSql = $movementWhere ? 'WHERE ' . implode(' AND ', $movementWhere) : '';
$movementSql = 'SELECT sm.created_at, sm.movement_type, sm.quantity, sm.reason,
                       p.product_code, p.shoe_name, c.name AS category_name, u.full_name AS staff_name
                FROM stock_movements sm
                JOIN products p ON p.id = sm.product_id
                JOIN categories c ON c.id = p.category_id
                JOIN users u ON u.id = sm.staff_id
                ' . $movementWhereSql . '
                ORDER BY sm.created_at DESC';
$movementStmt = $pdo->prepare($movementSql);
$movementStmt->execute($movementParams);
$movementRows = $movementStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports Export</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #111827;
            background: #ffffff;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 18px;
        }

        h1, h2 {
            margin: 0 0 8px;
        }

        h1 {
            font-size: 22px;
        }

        h2 {
            font-size: 16px;
            margin-top: 22px;
        }

        .meta {
            font-size: 12px;
            color: #4b5563;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 12px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            .page {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            h2,
            table {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <h1>Sales and Inventory Reports</h1>
    <div class="meta">Generated: <?= e(date('Y-m-d H:i:s')) ?></div>
    <div class="meta">Date From: <?= e($dateFrom !== '' ? $dateFrom : 'All') ?> | Date To: <?= e($dateTo !== '' ? $dateTo : 'All') ?></div>
    <div class="meta">Product: <?= e($productLabel) ?> | Category: <?= e($categoryLabel) ?></div>

    <h2>Daily Sales Report</h2>
    <table>
        <thead><tr><th>Date</th><th>Sales Count</th><th>Total Sales</th></tr></thead>
        <tbody>
        <?php foreach ($dailyRows as $row): ?>
            <tr>
                <td><?= e((string) $row['sale_date']) ?></td>
                <td><?= e((string) $row['sale_count']) ?></td>
                <td><?= e(format_currency((float) $row['total_sales'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dailyRows)): ?>
            <tr><td colspan="3">No daily sales data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Monthly Sales Report</h2>
    <table>
        <thead><tr><th>Month</th><th>Sales Count</th><th>Total Sales</th></tr></thead>
        <tbody>
        <?php foreach ($monthlyRows as $row): ?>
            <tr>
                <td><?= e((string) $row['sale_month']) ?></td>
                <td><?= e((string) $row['sale_count']) ?></td>
                <td><?= e(format_currency((float) $row['total_sales'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($monthlyRows)): ?>
            <tr><td colspan="3">No monthly sales data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Inventory Value Report</h2>
    <table>
        <thead>
        <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Stock</th>
            <th>Purchase Price</th>
            <th>Selling Price</th>
            <th>Stock Cost</th>
            <th>Stock Value</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($inventoryValueRows as $row): ?>
            <tr>
                <td><?= e((string) $row['product_code']) ?> - <?= e((string) $row['shoe_name']) ?> (<?= e((string) $row['brand']) ?>)</td>
                <td><?= e((string) $row['category_name']) ?></td>
                <td><?= e((string) $row['stock_quantity']) ?></td>
                <td><?= e(format_currency((float) $row['purchase_price'])) ?></td>
                <td><?= e(format_currency((float) $row['selling_price'])) ?></td>
                <td><?= e(format_currency((float) $row['stock_cost'])) ?></td>
                <td><?= e(format_currency((float) $row['stock_value'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($inventoryValueRows)): ?>
            <tr><td colspan="7">No inventory value data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Low Stock Report (Threshold: <?= e((string) LOW_STOCK_THRESHOLD) ?>)</h2>
    <table>
        <thead><tr><th>Product</th><th>Category</th><th>Stock</th></tr></thead>
        <tbody>
        <?php foreach ($lowStockRows as $row): ?>
            <tr>
                <td><?= e((string) $row['product_code']) ?> - <?= e((string) $row['shoe_name']) ?> (<?= e((string) $row['brand']) ?>)</td>
                <td><?= e((string) $row['category_name']) ?></td>
                <td><?= e((string) $row['stock_quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($lowStockRows)): ?>
            <tr><td colspan="3">No low stock products found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Stock Movement History</h2>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Product</th><th>Category</th><th>Qty</th><th>Reason</th><th>Staff</th></tr></thead>
        <tbody>
        <?php foreach ($movementRows as $row): ?>
            <tr>
                <td><?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></td>
                <td><?= e((string) $row['movement_type']) ?></td>
                <td><?= e((string) $row['product_code']) ?> - <?= e((string) $row['shoe_name']) ?></td>
                <td><?= e((string) $row['category_name']) ?></td>
                <td><?= e((string) $row['quantity']) ?></td>
                <td><?= e((string) ($row['reason'] ?? '')) ?></td>
                <td><?= e((string) $row['staff_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($movementRows)): ?>
            <tr><td colspan="7">No stock movement records.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
window.addEventListener('load', function() {
    window.print();
});
</script>
</body>
</html>
