<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

$cardsStmt = $pdo->query('SELECT COUNT(*) AS total_products, COALESCE(SUM(stock_quantity), 0) AS total_stock FROM products WHERE is_active = 1');
$cards = $cardsStmt->fetch();

$salesTodayStmt = $pdo->query('SELECT COALESCE(SUM(total_amount), 0) AS sales_today FROM sales WHERE DATE(created_at) = CURDATE()');
$salesToday = (float) ($salesTodayStmt->fetchColumn() ?: 0);

$lowStockStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity <= :threshold');
$lowStockStmt->execute(['threshold' => LOW_STOCK_THRESHOLD]);
$lowStockCount = (int) $lowStockStmt->fetchColumn();

$months = [];
for ($i = 11; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime('-' . $i . ' months'));
    $months[$monthKey] = 0.0;
}

$monthlyStmt = $pdo->query('SELECT DATE_FORMAT(created_at, "%Y-%m") AS month_key, SUM(total_amount) AS total_sales
                           FROM sales
                           WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                           GROUP BY DATE_FORMAT(created_at, "%Y-%m")
                           ORDER BY month_key ASC');

foreach ($monthlyStmt->fetchAll() as $row) {
    if (isset($months[$row['month_key']])) {
        $months[$row['month_key']] = (float) $row['total_sales'];
    }
}

$topSellingStmt = $pdo->query('SELECT p.brand, SUM(si.quantity) AS qty, MAX(c.name) AS category_name
                              FROM sales_items si
                              JOIN products p ON p.id = si.product_id AND p.is_active = 1
                              LEFT JOIN categories c ON c.id = p.category_id
                              GROUP BY p.brand
                              ORDER BY qty DESC
                              LIMIT 8');
$topSelling = $topSellingStmt->fetchAll();

$inventoryByCategoryStmt = $pdo->query('SELECT c.name AS category_name, COALESCE(SUM(p.stock_quantity), 0) AS stock_total
                                        FROM categories c
                                        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                                        GROUP BY c.id, c.name
                                        ORDER BY c.name ASC');
$inventoryByCategory = $inventoryByCategoryStmt->fetchAll();

$salesDistributionStmt = $pdo->query('SELECT c.name AS category_name, COALESCE(SUM(si.total), 0) AS revenue
                                     FROM categories c
                                     LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                                     LEFT JOIN sales_items si ON si.product_id = p.id
                                     GROUP BY c.id, c.name
                                     ORDER BY revenue DESC');
$salesDistribution = $salesDistributionStmt->fetchAll();

$response = [
    'cards' => [
        'total_products' => (int) ($cards['total_products'] ?? 0),
        'total_stock' => (int) ($cards['total_stock'] ?? 0),
        'sales_today' => $salesToday,
        'low_stock_alerts' => $lowStockCount,
    ],
    'monthly_sales' => [
        'labels' => array_map(
            static fn(string $k): string => date('M Y', strtotime($k . '-01')),
            array_keys($months)
        ),
        'values' => array_values($months),
    ],
    'top_selling' => [
        'labels' => array_map(static fn(array $item): string => (string) $item['brand'], $topSelling),
        'values' => array_map(static fn(array $item): int => (int) $item['qty'], $topSelling),
        'category_names' => array_map(static fn(array $item): string => (string) ($item['category_name'] ?? ''), $topSelling),
    ],
    'inventory_by_category' => [
        'labels' => array_map(static fn(array $item): string => (string) $item['category_name'], $inventoryByCategory),
        'values' => array_map(static fn(array $item): int => (int) $item['stock_total'], $inventoryByCategory),
    ],
    'sales_distribution' => [
        'labels' => array_map(static fn(array $item): string => (string) $item['category_name'], $salesDistribution),
        'values' => array_map(static fn(array $item): float => (float) $item['revenue'], $salesDistribution),
    ],
];

echo json_encode($response, JSON_THROW_ON_ERROR);
